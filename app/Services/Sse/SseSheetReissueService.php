<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseAuditLogRepository;
use App\Repositories\SseSheetRepository;
use App\Support\SseRedactionExplain;
use App\Support\SseSheetCatalog as D;
use App\Support\SseUsClassification;

/**
 * Réémission d'un document émis à un niveau de diffusion SUPÉRIEUR : même fiche, mêmes réglages, mêmes textes saisis, une nouvelle
 * référence liée à l'ancienne (instantané `reissue`), l'ancien document intact. Jamais au-dessus de l'habilitation de l'opérateur,
 * jamais sans motif, toujours journalisée (sse_audit_log et chronologie de l'identité par l'émission).
 *
 * Les textes qui contenaient une barre de caviardage ne peuvent pas être repris tels quels : ils reviennent à la trame du modèle,
 * résolue au nouveau niveau ; les textes saisis sans barre sont conservés.
 */
final class SseSheetReissueService
{
    public const REASON_MIN = 5;

    public const REASON_MAX = 300;

    public function __construct(
        private ?SseSheetRepository $repo = null,
        private ?SseSheetFactsService $facts = null,
        private ?SseSheetService $service = null,
    ) {
        $this->repo ??= new SseSheetRepository();
        $this->facts ??= new SseSheetFactsService();
        $this->service ??= new SseSheetService($this->facts);
    }

    /**
     * Niveaux auxquels un document peut être réémis par cet opérateur : au-dessus de sa diffusion, jusqu'à son habilitation.
     *
     * @return list<string>
     */
    public static function levelsAbove(string $issueLevel, SseSheetAccess $access): array
    {
        $from = SseRedactionService::levelRank($issueLevel);

        return array_values(array_filter(
            array_keys(SseRedactionService::LEVELS),
            static fn (string $l): bool => SseRedactionService::levelRank($l) > $from && SseRedactionService::levelRank($l) <= SseRedactionService::levelRank($access->level())
        ));
    }

    /**
     * Niveau minimal qui rend lisibles tous les passages masqués d'un document (null : rien à lever).
     *
     * @param array<string, int> $by
     */
    public static function levelNeeded(array $by): ?string
    {
        $top = null;
        foreach (array_keys($by) as $cat) {
            if ($cat === '' || !isset(SseRedactionService::CATEGORIES[$cat])) {
                continue;
            }
            $need = SseRedactionExplain::needCode((string) $cat);
            if ($top === null || SseRedactionService::levelRank($need) > SseRedactionService::levelRank($top)) {
                $top = $need;
            }
        }

        return $top;
    }

    /**
     * @return array{ok: bool, error: string, message: string, id: int, ref: string, old_ref: string, issue: ?array<string, mixed>}
     */
    public function reissue(int $tenantId, int $issueId, string $level, string $reason, SseSheetAccess $access): array
    {
        $fail = static fn (string $code, string $msg, ?array $issue = null): array => ['ok' => false, 'error' => $code, 'message' => $msg, 'id' => 0, 'ref' => '', 'old_ref' => '', 'issue' => $issue];
        if (!SseSheetRepository::ready()) {
            return $fail('unavailable', 'Les fiches PDF ne sont pas disponibles.');
        }
        $issue = $this->repo->findIssue($tenantId, $issueId);
        if ($issue === null || $issue['model'] === 'notice' || !D::exists((string) $issue['model'])) {
            return $fail('notfound', 'Document introuvable.');
        }
        // Lire l'ancien document, et pouvoir émettre ce modèle : les mêmes droits que pour l'émettre.
        if (!$access->canDraft() || SseRedactionService::levelRank((string) $issue['classification']) > SseRedactionService::levelRank($access->level())) {
            return $fail('forbidden', 'Ce document est classé au-dessus de votre habilitation.', $issue);
        }
        $denial = $access->denial((string) $issue['model']);
        if ($denial !== null) {
            return $fail('forbidden', $denial, $issue);
        }
        $reason = trim((string) preg_replace('/\s+/u', ' ', $reason));
        if (mb_strlen($reason, 'UTF-8') < self::REASON_MIN) {
            return $fail('reason', 'Indiquez le motif de la réémission (5 caractères au moins).', $issue);
        }
        $reason = mb_substr($reason, 0, self::REASON_MAX, 'UTF-8');
        if (!isset(SseRedactionService::LEVELS[$level]) || SseRedactionService::levelRank($level) <= SseRedactionService::levelRank((string) $issue['classification'])) {
            return $fail('level', 'Choisissez un niveau de diffusion supérieur à celui du document.', $issue);
        }
        if (SseRedactionService::levelRank($level) > SseRedactionService::levelRank($access->level())) {
            return $fail('ceiling', 'Ce niveau dépasse votre habilitation : demandez la réémission à un responsable habilité.', $issue);
        }
        $subject = $this->subjectOf($tenantId, $issue, $access);
        if ($subject === null) {
            return $fail('notfound', 'Fiche introuvable.', $issue);
        }
        $snap = is_array($issue['snapshot']) ? $issue['snapshot'] : [];
        $input = is_array($snap['params'] ?? null) ? $snap['params'] : [];
        $input['lang'] = $issue['lang'];
        $input['faces'] = $issue['faces'];
        $input['classification'] = $level;
        $texts = [];
        foreach ((array) $issue['texts'] as $k => $v) {
            if (is_string($v) && !str_contains($v, SseRedactionExplain::RUN)) {
                $texts[(string) $k] = $v;
            }
        }
        $input['texts'] = $texts;
        $input['_reissue'] = ['of' => (int) $issue['id'], 'ref' => (string) $issue['ref'], 'from' => (string) $issue['classification'], 'reason' => $reason];
        $res = $this->service->issue($tenantId, $subject, (string) $issue['model'], $input, $access);
        if (!$res['ok'] || $res['id'] < 1) {
            return $fail('issue', $res['errors'] !== [] ? implode(' ', $res['errors']) : 'Le document n’a pas pu être émis.', $issue);
        }
        $this->audit($tenantId, $issue, $res, $level, $reason, $access);

        return ['ok' => true, 'error' => '', 'message' => '', 'id' => $res['id'], 'ref' => $res['ref'], 'old_ref' => (string) $issue['ref'], 'issue' => $issue];
    }

    /**
     * Fiche du document, relue avec les droits de l'opérateur (une identité cachée au demandeur reste introuvable).
     *
     * @param array<string, mixed> $issue
     * @return array<string, mixed>|null
     */
    private function subjectOf(int $tenantId, array $issue, SseSheetAccess $access): ?array
    {
        if (($issue['person_id'] ?? null) !== null) {
            return $this->facts->subject($tenantId, 'person', (int) $issue['person_id'], $access);
        }
        if (($issue['identity_id'] ?? null) !== null) {
            return $this->facts->subject($tenantId, 'identity', (int) $issue['identity_id'], $access);
        }

        return null;
    }

    /**
     * Adresse de retour de la fiche d'un document (jamais lue dans la requête).
     *
     * @param array<string, mixed> $issue
     */
    public static function backUrl(array $issue): string
    {
        if (($issue['person_id'] ?? null) !== null) {
            return url('atak/sse/identites/' . (int) $issue['person_id'] . '?fiche=personne') . '#documents-emis';
        }
        if (($issue['identity_id'] ?? null) !== null) {
            return url('atak/sse/papiers/identites/' . (int) $issue['identity_id']) . '#documents-emis';
        }

        return url('atak/sse/identites');
    }

    /**
     * @param array<string, mixed> $issue
     * @param array<string, mixed> $res
     */
    private function audit(int $tenantId, array $issue, array $res, string $level, string $reason, SseSheetAccess $access): void
    {
        try {
            (new SseAuditLogRepository())->write([
                'tenant_id' => $tenantId, 'actor_user_id' => $access->userId(), 'actor_label' => $access->actorLabel() !== '' ? $access->actorLabel() : 'Fiches PDF',
                'action' => 'document.reissued_higher', 'object_type' => 'sse_doc_issue', 'object_id' => (int) $issue['id'], 'reason' => $reason,
                'before' => ['ref' => (string) $issue['ref'], 'level' => SseUsClassification::abbr((string) $issue['classification'])],
                'after' => ['ref' => (string) $res['ref'], 'id' => (int) $res['id'], 'level' => SseUsClassification::abbr($level)],
            ]);
        } catch (\Throwable $e) {
            error_log('Fiches PDF : audit de la réémission indisponible : ' . $e->getMessage());
        }
    }
}
