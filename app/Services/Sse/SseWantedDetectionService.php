<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Database;
use App\Repositories\SseIdentityEventRepository;
use App\Repositories\SseWantedDetectionRepository;
use App\Repositories\SseWantedNoticeRepository;
use App\Support\SseWantedCatalog as C;

/**
 * Détections système : quand une identité sous avis de recherche ACTIF réapparaît (contrôle d'identité du terrain, scan
 * SEEK II, consultation des fichiers, photo versée, recoupement), le système crée une détection, la note dans la chronologie
 * de l'identité et prévient les utilisateurs SSE concernés par le centre de notifications (jamais de popup en jeu).
 *
 * Garanties : aucune détection pour une identité sans avis actif ; une détection par avis, type et canal dans un laps de temps
 * court (les répétitions sont comptées sur la détection existante) ; plafonds par heure et par avis ; une détection ne
 * modifie aucune donnée de l'identité ; toute erreur est absorbée (le contrôle du terrain ne doit jamais échouer pour elle).
 */
final class SseWantedDetectionService
{
    /** Laps de temps (secondes) pendant lequel une même détection est regroupée. */
    public const DEDUPE_SECONDS = 600;

    /** Détections créées au plus par avis et par heure. */
    public const HOURLY_CAP = 30;

    /** Détections créées au plus par avis, toutes durées confondues. */
    public const TOTAL_CAP = 500;

    /** Personnes prévenues au plus par détection. */
    public const MAX_RECIPIENTS = 30;

    /** @var \Closure(int, int, array<string, mixed>): void */
    private \Closure $notify;

    /** @var \Closure(int, int, ?int): list<int> */
    private \Closure $recipients;

    /**
     * @param (\Closure(int, int, array<string, mixed>): void)|null $notify prévenir un membre (communauté, compte, contenu)
     * @param (\Closure(int, int, ?int): list<int>)|null $recipients comptes à prévenir (communauté, identité, auteur de l'avis)
     */
    public function __construct(
        private ?SseWantedNoticeRepository $notices = null,
        private ?SseWantedDetectionRepository $detections = null,
        private ?SseIdentityEventRepository $events = null,
        ?\Closure $notify = null,
        ?\Closure $recipients = null,
    ) {
        $this->notices ??= new SseWantedNoticeRepository();
        $this->detections ??= new SseWantedDetectionRepository();
        $this->events ??= new SseIdentityEventRepository();
        $this->notify = $notify ?? static function (int $tenantId, int $userId, array $payload): void {
            try {
                (new \App\Repositories\MemberNotificationRepository())->create($tenantId, $userId, 'sse_wanted_detection', $payload);
            } catch (\Throwable $e) {
                error_log('Détection d’un avis de recherche : notification non envoyée : ' . $e->getMessage());
            }
        };
        $this->recipients = $recipients ?? static fn (int $tenantId, int $identityId, ?int $authorId): array => self::defaultRecipients($tenantId, $identityId, $authorId);
    }

    /**
     * Appel sûr depuis les points d'entrée du jeu et du site : n'échoue jamais, ne fait rien sans avis actif.
     *
     * @param array<string, mixed> $ctx source, source_ref, place, occurred_at, operator_label, operator_user_id
     */
    public static function tryReport(int $tenantId, ?int $identityId, string $type, array $ctx = []): void
    {
        if ($identityId === null || $identityId < 1 || $tenantId < 1) {
            return;
        }
        try {
            if (!SseWantedNoticeRepository::ready()) {
                return;
            }
            (new self())->report($tenantId, $identityId, $type, $ctx);
        } catch (\Throwable $e) {
            error_log('Détection d’un avis de recherche : ' . $e->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $ctx
     * @return array{created: bool, id: int, notice_ref: string}|null null : pas d'avis actif, type inconnu ou plafond atteint
     */
    public function report(int $tenantId, int $identityId, string $type, array $ctx = []): ?array
    {
        if (!isset(C::DETECTION_TYPES[$type])) {
            return null;
        }
        $notice = $this->notices->activeForIdentity($tenantId, $identityId);
        if ($notice === null) {
            return null;
        }
        $noticeId = (int) $notice['id'];
        $source = C::pick(C::DETECTION_SOURCES, $ctx['source'] ?? null, 'system');
        $ref = C::line($ctx['source_ref'] ?? '', 80);
        $now = time();

        // Même événement renvoyé (clé du contrôle déjà vue dans la journée) : rien de plus.
        if ($ref !== '') {
            $same = $this->detections->findRecent($tenantId, $noticeId, $type, gmdate('Y-m-d H:i:s', $now - 86400), null, $ref);
            if ($same !== null) {
                return ['created' => false, 'id' => (int) $same['id'], 'notice_ref' => (string) $notice['ref']];
            }
        }
        // Une détection par avis, type et canal dans le laps court : les répétitions s'ajoutent à la première.
        $recent = $this->detections->findRecent($tenantId, $noticeId, $type, gmdate('Y-m-d H:i:s', $now - self::DEDUPE_SECONDS), $source);
        if ($recent !== null) {
            $this->detections->bump($tenantId, (int) $recent['id']);

            return ['created' => false, 'id' => (int) $recent['id'], 'notice_ref' => (string) $notice['ref']];
        }
        // Plafonds : au-delà, rien n'est créé (l'avis est déjà bien en vue).
        if ($this->detections->countSince($tenantId, $noticeId, gmdate('Y-m-d H:i:s', $now - 3600)) >= self::HOURLY_CAP
            || $this->detections->countSince($tenantId, $noticeId, '1970-01-01 00:00:00') >= self::TOTAL_CAP) {
            return null;
        }

        $occurred = C::dateTime($ctx['occurred_at'] ?? null);
        $operator = C::line($ctx['operator_label'] ?? '', 80);
        $id = $this->detections->create($tenantId, [
            'identity_id' => $identityId,
            'notice_id' => $noticeId,
            'detection_type' => $type,
            'source' => $source,
            'source_ref' => $ref !== '' ? $ref : null,
            'place' => C::line($ctx['place'] ?? '', 160) ?: null,
            'occurred_at' => $occurred,
            'operator_label' => $operator !== '' ? $operator : null,
            'operator_user_id' => ($ctx['operator_user_id'] ?? null) !== null && (int) $ctx['operator_user_id'] > 0 ? (int) $ctx['operator_user_id'] : null,
        ]);
        try {
            $this->events->add(
                $tenantId,
                $identityId,
                'detection.raised',
                'Détection système : ' . C::label(C::DETECTION_TYPES, $type) . ' (avis ' . $notice['ref'] . ').',
                array_filter(['ref' => $notice['ref'], 'type' => $type, 'source_ref' => $ref, 'place' => $ctx['place'] ?? null], static fn (mixed $v): bool => $v !== null && $v !== ''),
                null,
                $operator !== '' ? $operator : 'Système',
                'base',
                $source === 'game' ? 'game' : ($source === 'web' ? 'web' : 'system'),
                $noticeId
            );
        } catch (\Throwable $e) {
            error_log('Détection d’un avis de recherche : chronologie indisponible : ' . $e->getMessage());
        }
        $this->warn($tenantId, $identityId, $notice, $type, $id);

        return ['created' => true, 'id' => $id, 'notice_ref' => (string) $notice['ref']];
    }

    /**
     * Champ additif « wanted » de la réponse d'un contrôle d'identité : booléen, référence de l'avis, niveau de menace et
     * consigne courte. Sans avis actif (ou sans identité reconnue) : seulement « wanted: false ».
     *
     * @return array{wanted: bool, ref?: string, threat_level?: string, instruction?: string}
     */
    public function wantedPayload(int $tenantId, ?int $identityId): array
    {
        if ($identityId === null || $identityId < 1 || $tenantId < 1) {
            return ['wanted' => false];
        }
        try {
            if (!SseWantedNoticeRepository::ready()) {
                return ['wanted' => false];
            }
            $notice = $this->notices->activeForIdentity($tenantId, $identityId);
        } catch (\Throwable) {
            return ['wanted' => false];
        }
        if ($notice === null) {
            return ['wanted' => false];
        }
        $instruction = C::line($notice['instructions'] ?? '', 120);

        return [
            'wanted' => true,
            'ref' => (string) $notice['ref'],
            'threat_level' => (string) $notice['threat_level'],
            'instruction' => $instruction,
        ];
    }

    /**
     * @param array<string, mixed> $notice
     */
    private function warn(int $tenantId, int $identityId, array $notice, string $type, int $detectionId): void
    {
        try {
            $authorId = ($notice['created_by_user_id'] ?? null) !== null ? (int) $notice['created_by_user_id'] : null;
            $ids = array_slice(array_values(array_unique(array_filter(
                array_map('intval', ($this->recipients)($tenantId, $identityId, $authorId)),
                static fn (int $i): bool => $i > 0
            ))), 0, self::MAX_RECIPIENTS);
            $name = (string) ($notice['identity_name'] ?? '');
            foreach ($ids as $uid) {
                ($this->notify)($tenantId, $uid, [
                    'label' => $name !== '' ? $name : (string) $notice['ref'],
                    'ref' => (string) $notice['ref'],
                    'type' => $type,
                    'threat_level' => (string) $notice['threat_level'],
                    'detection_id' => $detectionId,
                    'identity_id' => $identityId,
                    'path' => 'atak/sse/avis/detections',
                ]);
            }
        } catch (\Throwable $e) {
            error_log('Détection d’un avis de recherche : prévenir les utilisateurs a échoué : ' . $e->getMessage());
        }
    }

    /**
     * Comptes prévenus : auteur de l'avis, responsables des fichiers SSE de la communauté et comptes qui surveillent l'identité.
     *
     * @return list<int>
     */
    private static function defaultRecipients(int $tenantId, int $identityId, ?int $authorId): array
    {
        $ids = $authorId !== null ? [$authorId] : [];
        try {
            $ids = array_merge($ids, (new \App\Repositories\UserRepository())->listActiveUserIdsWithAnyPermissionSlug($tenantId, ['atak.sse.case.manage', 'atak.sse.grant', 'atak.sse.files.admin']));
        } catch (\Throwable) {
        }
        try {
            foreach (Database::getInstance()->fetchAll('SELECT DISTINCT user_id FROM sse_file_watches WHERE tenant_id = :t AND identity_id = :i LIMIT 50', ['t' => $tenantId, 'i' => $identityId]) as $r) {
                $ids[] = (int) $r['user_id'];
            }
        } catch (\Throwable) {
        }

        return array_values(array_unique(array_filter($ids, static fn (int $i): bool => $i > 0)));
    }
}
