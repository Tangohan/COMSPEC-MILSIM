<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseAuditLogRepository;
use App\Repositories\SseIdentityEventRepository;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SseWantedNoticeRepository;
use App\Repositories\SseWantedSettingsRepository;
use App\Support\SseWantedCatalog as C;
use App\Support\SseWantedRealNames;

/**
 * Avis de recherche posés depuis la fiche d'une identité fictive : validation, un seul avis actif par identité, levée avec
 * motif obligatoire, trace dans la chronologie de l'identité et au journal d'audit SSE. L'avis est un fait de jeu de rôle :
 * aucune donnée réelle (ni membre, ni organisation).
 *
 * Les droits sont ceux de {@see SseWantedAccess} ; l'identité doit appartenir à la communauté de la session.
 */
final class SseWantedService
{
    public const MIN_TEXT = 3;

    public function __construct(
        private ?SseWantedNoticeRepository $notices = null,
        private ?SseIdentityRepository $identities = null,
        private ?SseIdentityEventRepository $events = null,
        private ?SseWantedSettingsRepository $settings = null,
        private ?SseAuditLogRepository $audit = null,
    ) {
        $this->notices ??= new SseWantedNoticeRepository();
        $this->identities ??= new SseIdentityRepository();
        $this->events ??= new SseIdentityEventRepository();
        $this->settings ??= new SseWantedSettingsRepository();
    }

    /**
     * Valide la saisie du formulaire « Placer un avis de recherche ».
     *
     * @param array<string, mixed> $input
     * @param array<string, mixed> $config réglages de la communauté
     * @return array{data: array<string, mixed>, errors: list<string>}
     */
    public static function validate(array $input, array $config, ?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $errors = [];
        $types = C::types($config);
        $type = is_string($input['notice_type'] ?? null) ? $input['notice_type'] : '';
        if (!isset($types[$type])) {
            $errors[] = 'Choisissez un type d’avis.';
            $type = (string) (array_key_first($types) ?? 'poi');
        }
        $threat = is_string($input['threat_level'] ?? null) ? $input['threat_level'] : '';
        if (!isset(C::THREATS[$threat])) {
            $errors[] = 'Choisissez un niveau de menace.';
            $threat = 'medium';
        }
        $armed = is_string($input['armed'] ?? null) ? $input['armed'] : 'unknown';
        if (!isset(C::ARMED[$armed])) {
            $errors[] = 'État « armé et dangereux » invalide.';
            $armed = 'unknown';
        }
        $reason = C::text($input['reason'] ?? '', C::REASON_MAX);
        if (mb_strlen($reason) < self::MIN_TEXT) {
            $errors[] = 'Indiquez les motifs ou la raison de l’intérêt (au moins quelques mots).';
        }
        $instructions = C::text($input['instructions'] ?? '', C::INSTRUCTIONS_MAX);
        if ($instructions === '') {
            $instructions = C::text($config['default_instructions_fr'] ?? '', C::INSTRUCTIONS_MAX);
        }
        $issuedRaw = $input['issued_at'] ?? '';
        $issued = C::dateTime($issuedRaw);
        if (trim(is_scalar($issuedRaw) ? (string) $issuedRaw : '') !== '' && $issued === null) {
            $errors[] = 'Date d’émission invalide.';
        }
        $issued ??= $now->format('Y-m-d H:i:s');
        $expiresRaw = $input['expires_at'] ?? '';
        $expires = C::dateTime($expiresRaw);
        if (trim(is_scalar($expiresRaw) ? (string) $expiresRaw : '') !== '' && $expires === null) {
            $errors[] = 'Date d’expiration invalide.';
        }
        if ($expires === null && (int) ($config['default_expiry_days'] ?? 0) > 0 && trim(is_scalar($expiresRaw) ? (string) $expiresRaw : '') === '') {
            $expires = $now->modify('+' . (int) $config['default_expiry_days'] . ' days')->format('Y-m-d H:i:s');
        }
        if ($expires !== null && $expires <= $issued) {
            $errors[] = 'La date d’expiration doit suivre la date d’émission.';
        }
        $seenRaw = $input['last_seen_at'] ?? '';
        $seen = C::dateTime($seenRaw);
        if (trim(is_scalar($seenRaw) ? (string) $seenRaw : '') !== '' && $seen === null) {
            $errors[] = 'Date du dernier lieu connu invalide.';
        }
        $contactLabel = C::line($input['contact_label'] ?? ($config['contact_label'] ?? ''), 160);
        $contactDetail = C::line($input['contact_detail'] ?? ($config['contact_detail'] ?? ''), 200);
        foreach ([$contactLabel, $contactDetail, $reason, $instructions] as $text) {
            $hit = SseWantedRealNames::find($text);
            if ($hit !== null) {
                $errors[] = 'Un avis de jeu de rôle ne cite pas d’organisation réelle (« ' . $hit . ' ») : utilisez un service fictif.';
                break;
            }
        }

        return [
            'data' => [
                'notice_type' => $type,
                'threat_level' => $threat,
                'armed' => $armed,
                'reason' => $reason,
                'instructions' => $instructions,
                'zone' => C::line($input['zone'] ?? '', 120) ?: null,
                'last_seen_place' => C::line($input['last_seen_place'] ?? '', 160) ?: null,
                'last_seen_at' => $seen,
                'contact_label' => $contactLabel !== '' ? $contactLabel : null,
                'contact_detail' => $contactDetail !== '' ? $contactDetail : null,
                'issued_at' => $issued,
                'expires_at' => $expires,
            ],
            'errors' => array_values(array_unique($errors)),
        ];
    }

    /**
     * Place un avis sur une identité de la communauté.
     *
     * @param array<string, mixed> $input
     * @return array{ok: true, id: int, ref: string}|array{ok: false, error: string, errors: list<string>}
     */
    public function place(int $tenantId, int $identityId, array $input, SseWantedAccess $access): array
    {
        if (!$access->canManage() || $access->tenantId() !== $tenantId) {
            return ['ok' => false, 'error' => 'forbidden', 'errors' => ['Action non autorisée.']];
        }
        if (!SseWantedNoticeRepository::ready()) {
            return ['ok' => false, 'error' => 'unavailable', 'errors' => ['Les avis de recherche ne sont pas encore disponibles (étape de base de données en attente).']];
        }
        $identity = $this->identities->find($identityId, $tenantId);
        if ($identity === null) {
            return ['ok' => false, 'error' => 'identity', 'errors' => ['Identité introuvable.']];
        }
        $config = $this->settings->get($tenantId);
        $checked = self::validate($input, $config);
        if ($checked['errors'] !== []) {
            return ['ok' => false, 'error' => 'invalid', 'errors' => $checked['errors']];
        }
        $data = $checked['data'];
        $data['authority'] = (string) $config['authority_fr'];
        $id = $this->notices->create($tenantId, $identityId, $data, (string) $config['ref_format'], $access->userId());
        if ($id === null) {
            return ['ok' => false, 'error' => 'exists', 'errors' => ['Cette identité fait déjà l’objet d’un avis actif : levez-le avant d’en placer un autre.']];
        }
        $notice = $this->notices->find($tenantId, $id);
        $ref = (string) ($notice['ref'] ?? '');
        $this->trace($tenantId, $identityId, $id, 'notice.placed', sprintf(
            'Avis de recherche %s placé : %s, menace %s.',
            $ref,
            C::typeLabel((string) $data['notice_type'], $config),
            mb_strtolower(C::label(C::THREATS, (string) $data['threat_level']))
        ), ['ref' => $ref, 'type' => $data['notice_type'], 'threat' => $data['threat_level'], 'armed' => $data['armed']], $access);

        return ['ok' => true, 'id' => $id, 'ref' => $ref];
    }

    /**
     * Lève un avis actif (« lifted ») ou le clôt par la capture (« captured »). Le motif est obligatoire.
     *
     * @return array{ok: true}|array{ok: false, error: string, errors: list<string>}
     */
    public function lift(int $tenantId, int $noticeId, string $status, string $reason, SseWantedAccess $access): array
    {
        if (!$access->canManage() || $access->tenantId() !== $tenantId) {
            return ['ok' => false, 'error' => 'forbidden', 'errors' => ['Action non autorisée.']];
        }
        if (!SseWantedNoticeRepository::ready()) {
            return ['ok' => false, 'error' => 'unavailable', 'errors' => ['Les avis de recherche ne sont pas encore disponibles.']];
        }
        $status = in_array($status, C::CLOSING, true) ? $status : 'lifted';
        $reason = C::text($reason, 500);
        if (mb_strlen($reason) < self::MIN_TEXT) {
            return ['ok' => false, 'error' => 'reason', 'errors' => ['Le motif de la levée est obligatoire.']];
        }
        $notice = $this->notices->find($tenantId, $noticeId);
        if ($notice === null) {
            return ['ok' => false, 'error' => 'notice', 'errors' => ['Avis introuvable.']];
        }
        if (!$this->notices->close($tenantId, $noticeId, $status, $reason, $access->userId())) {
            return ['ok' => false, 'error' => 'closed', 'errors' => ['Cet avis n’est plus actif.']];
        }
        $this->trace(
            $tenantId,
            (int) $notice['identity_id'],
            $noticeId,
            $status === 'captured' ? 'notice.captured' : 'notice.lifted',
            sprintf($status === 'captured' ? 'Avis %s clos : personne capturée.' : 'Avis %s levé.', (string) $notice['ref']),
            ['ref' => $notice['ref'], 'reason' => $reason],
            $access
        );

        return ['ok' => true];
    }

    /**
     * Passe en « expiré » les avis arrivés à échéance et le note dans la chronologie (une seule fois, par la fermeture).
     */
    public function expireDue(int $tenantId, ?int $identityId = null): void
    {
        if (!SseWantedNoticeRepository::ready()) {
            return;
        }
        try {
            foreach ($this->notices->expireDue($tenantId, $identityId) as $n) {
                $this->events->add($tenantId, (int) $n['identity_id'], 'notice.expired', 'Avis ' . $n['ref'] . ' expiré (date d’expiration atteinte).', ['ref' => $n['ref']], null, 'Système', 'base', 'system', (int) $n['id']);
            }
        } catch (\Throwable $e) {
            error_log('Avis de recherche : expiration non notée : ' . $e->getMessage());
        }
    }

    /**
     * Trace d'une génération de PDF (les événements ne sont jamais effacés).
     */
    public function tracePdf(int $tenantId, int $noticeId, string $lang, SseWantedAccess $access): void
    {
        $notice = $this->notices->find($tenantId, $noticeId);
        if ($notice === null) {
            return;
        }
        try {
            $this->events->add($tenantId, (int) $notice['identity_id'], 'notice.pdf', 'Notice PDF ' . strtoupper($lang) . ' générée pour l’avis ' . $notice['ref'] . '.', ['ref' => $notice['ref'], 'lang' => $lang], $access->userId(), $access->actorLabel(), 'base', 'web', $noticeId);
        } catch (\Throwable $e) {
            error_log('Avis de recherche : génération du PDF non notée : ' . $e->getMessage());
        }
    }

    /** @param array<string, mixed> $details */
    private function trace(int $tenantId, int $identityId, int $noticeId, string $type, string $summary, array $details, SseWantedAccess $access): void
    {
        try {
            $this->events->add($tenantId, $identityId, $type, $summary, $details, $access->userId(), $access->actorLabel(), 'base', 'web', $noticeId);
        } catch (\Throwable $e) {
            error_log('Avis de recherche : chronologie indisponible : ' . $e->getMessage());
        }
        try {
            ($this->audit ??= new SseAuditLogRepository())->write([
                'tenant_id' => $tenantId,
                'actor_user_id' => $access->userId(),
                'actor_label' => $access->actorLabel() !== '' ? $access->actorLabel() : 'Avis de recherche',
                'action' => $type,
                'object_type' => 'sse_wanted_notice',
                'object_id' => $noticeId,
                'reason' => (string) ($details['reason'] ?? ''),
                'after' => $details,
            ]);
        } catch (\Throwable $e) {
            error_log('Avis de recherche : audit indisponible : ' . $e->getMessage());
        }
    }
}
