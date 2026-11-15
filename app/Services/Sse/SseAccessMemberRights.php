<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Support\SseAccessCatalog as C;

/**
 * Bloc « Accès SSE » de la fiche effectif d'un membre : ses droits effectifs par section (voir, écrire, gérer) avec
 * l'origine de chacun, ses règles d'identité (dérogations datées), l'aperçu d'un changement et son historique.
 *
 * Ne décide rien et n'écrit rien d'autre que par SseAccessRuleService : mêmes garde-fous que l'onglet « Droits d'accès »
 * du bureau SSE (seul le droit de gestion SSE, pas d'escalade, motif, dernier gestionnaire protégé, bornes de la
 * communauté, historique). La communauté est toujours celle de la session de l'appelant, jamais celle de la requête.
 */
final class SseAccessMemberRights
{
    public function __construct(
        private ?SseAccessPolicy $policy = null,
        private ?SseAccessRuleService $service = null,
        private ?SseAccessMatrix $matrix = null,
        private ?SseAccessDirectory $directory = null,
        private ?SseMemberClearance $clearance = null,
        private ?\Closure $ceiling = null,
    ) {
        $this->policy ??= SseAccessPolicy::forSession();
    }

    private function clearance(): SseMemberClearance
    {
        return $this->clearance ??= new SseMemberClearance();
    }

    /** Plafond de lecture de la personne qui agit (sa session) : ce qu'elle peut inscrire au plus haut. */
    private function actorCeiling(): string
    {
        return $this->ceiling !== null ? (string) ($this->ceiling)() : (new SseClearanceService())->maxLevel();
    }

    private function policy(): SseAccessPolicy
    {
        return $this->policy ??= SseAccessPolicy::forSession();
    }

    private function directory(): SseAccessDirectory
    {
        return $this->directory ??= new SseAccessDirectory();
    }

    private function matrix(): SseAccessMatrix
    {
        return $this->matrix ??= new SseAccessMatrix($this->policy(), $this->directory());
    }

    private function service(): SseAccessRuleService
    {
        return $this->service ??= new SseAccessRuleService($this->policy()->rules(), $this->policy()->subjects(), $this->policy(), $this->directory());
    }

    /** Le bloc existe-t-il pour cette personne ? Seulement le droit de gestion SSE, jamais un autre. */
    public function visibleTo(int $tenantId, int $actorId): bool
    {
        return $tenantId > 0 && $actorId > 0 && $this->policy()->canManageRights($actorId, ['tenant_id' => $tenantId, 'guest' => false]);
    }

    /**
     * @return array{
     *   member: array{user_id: int, label: string, unit: string},
     *   ready: bool,
     *   clearance: array<string, mixed>,
     *   entry: bool,
     *   rows: list<array{key: string, label: string, cells: array<string, array{applies: bool, allowed: bool, origins: list<array<string, mixed>>, own: list<int>}>}>,
     *   own: list<array<string, mixed>>,
     *   history: list<array<string, mixed>>
     * }|null null : bloc invisible (pas le droit de gestion SSE) ou membre introuvable ou protégé par l'annuaire
     */
    public function overview(int $tenantId, int $actorId, int $memberId): ?array
    {
        if (!$this->visibleTo($tenantId, $actorId)) {
            return null;
        }
        $member = $this->directory()->member($tenantId, $memberId);
        if ($member === null) {
            return null;
        }
        $effective = $this->matrix()->effectiveForMember($tenantId, $memberId);
        $now = $this->policy()->now();
        $own = [];
        foreach ($this->policy()->rules()->liveRules($tenantId) as $r) {
            if ($r['subject_type'] === C::SUBJECT_USER && (int) $r['subject_id'] === $memberId && $this->mayView($actorId, $tenantId, (string) $r['section'])) {
                $own[] = $r + [
                    'section_label' => C::sectionLabel((string) $r['section']),
                    'level_label' => C::levelLabel((string) $r['level']),
                    'state' => SseAccessMatrix::state($r, $now),
                ];
            }
        }
        $rows = [];
        foreach ($effective['sections'] as $row) {
            if (!$this->mayView($actorId, $tenantId, $row['key'])) {
                continue;
            }
            $cells = [];
            foreach (C::SIMPLE_ACCESS as $access => $def) {
                $cell = $row['cells'][$def['shown']];
                $mine = [];
                foreach ($own as $r) {
                    if ($r['section'] === $row['key'] && $r['level'] === $def['shown'] && $r['effect'] === C::EFFECT_ALLOW) {
                        $mine[] = (int) $r['id'];
                    }
                }
                $cells[$access] = ['applies' => $cell['applies'], 'allowed' => $cell['allowed'], 'origins' => $cell['origins'], 'own' => $mine];
            }
            $rows[] = ['key' => $row['key'], 'label' => $row['label'], 'cells' => $cells];
        }

        return [
            'member' => $member,
            'ready' => $this->policy()->rules()->ready(),
            'clearance' => $this->clearanceOverview($tenantId, $memberId),
            'entry' => $effective['entry'],
            'rows' => $rows,
            'own' => $own,
            'history' => array_values(array_filter(
                $this->policy()->rules()->historyForSubject($tenantId, C::SUBJECT_USER, $memberId, 30),
                fn (array $h): bool => $this->mayView($actorId, $tenantId, (string) $h['section'])
            )),
        ];
    }

    /** @return array<string, mixed> niveau d'habilitation du membre, niveaux que la personne peut inscrire, et si elle peut modifier */
    private function clearanceOverview(int $tenantId, int $memberId): array
    {
        $ceiling = $this->actorCeiling();

        return $this->clearance()->view($tenantId, $memberId) + [
            'options' => $this->clearance()->grantable($ceiling),
            'may_change' => $this->clearance()->mayChange($memberId, $ceiling),
        ];
    }

    /**
     * Accorde, modifie ou retire ($level null) l'habilitation inscrite sur la fiche du membre.
     *
     * @return array{ok: bool, message: string}
     */
    public function changeClearance(int $tenantId, int $actorId, string $actorLabel, int $memberId, ?string $level, string $reason): array
    {
        if (!$this->visibleTo($tenantId, $actorId)) {
            return ['ok' => false, 'message' => 'Seul le droit de gestion SSE permet de modifier les droits d’accès.'];
        }
        if ($this->directory()->member($tenantId, $memberId) === null) {
            return ['ok' => false, 'message' => 'Membre introuvable.'];
        }

        return $this->clearance()->change($tenantId, $actorId, $actorLabel, $memberId, $level, $reason, $this->actorCeiling());
    }

    /**
     * Ce qu'un « donner un accès » ou « retirer l'accès » créerait, avec l'effet en une phrase. N'écrit rien.
     *
     * @param array<string, mixed> $input sections (liste ; vide : toutes), access, effect, ends_at, reason
     * @return array{rules: list<array<string, mixed>>, errors: list<string>, skipped: list<string>, line: string, sim: array<string, mixed>}
     */
    public function preview(int $tenantId, int $actorId, int $memberId, array $input): array
    {
        $plan = $this->plan($tenantId, $actorId, $memberId, $input);
        $sim = $plan['errors'] === [] && $plan['rules'] !== [] ? $this->matrix()->simulate($tenantId, $plan['rules']) : [];

        return $plan + ['line' => $sim !== [] ? SseAccessMatrix::effectLine($sim) : '', 'sim' => $sim];
    }

    /**
     * Crée les règles d'identité (dérogation à ce membre, datée, avec motif) après la même validation que l'aperçu.
     *
     * @param array<string, mixed> $input
     * @return array{ok: bool, message: string, ids: list<int>}
     */
    public function save(int $tenantId, int $actorId, string $actorLabel, int $memberId, array $input): array
    {
        $plan = $this->plan($tenantId, $actorId, $memberId, $input);
        if ($plan['rules'] === [] && SseAccessRuleService::onlyAlreadyInPlace($plan)) {
            // Déjà en place (double clic, ou règle posée entre-temps) : ce n'est pas une erreur, et on dit ce qui existe.
            return ['ok' => true, 'message' => SseAccessRuleService::describeResult($plan, 0), 'ids' => []];
        }
        if ($plan['errors'] !== [] || $plan['rules'] === []) {
            return ['ok' => false, 'message' => $plan['errors'] !== [] ? implode(' ', $plan['errors']) : SseAccessRuleService::describeResult($plan, 0), 'ids' => []];
        }
        $ids = $this->service()->commit($tenantId, $actorId, $actorLabel, $plan['rules'], 'create');

        return $ids === []
            ? ['ok' => false, 'message' => 'Rien n’a été enregistré : réessayez.', 'ids' => []]
            : ['ok' => true, 'message' => SseAccessRuleService::describeResult($plan, count($ids)), 'ids' => $ids];
    }

    /**
     * Retire une règle d'identité de CE membre (jamais une règle d'un autre bénéficiaire ni d'une autre communauté).
     *
     * @return array{ok: bool, message: string}
     */
    public function revoke(int $tenantId, int $actorId, string $actorLabel, int $memberId, int $ruleId, string $reason = ''): array
    {
        if (!$this->visibleTo($tenantId, $actorId)) {
            return ['ok' => false, 'message' => 'Seul le droit de gestion SSE permet de modifier les droits d’accès.'];
        }
        $rule = $this->policy()->rules()->find($tenantId, $ruleId);
        if ($rule === null || $rule['subject_type'] !== C::SUBJECT_USER || (int) $rule['subject_id'] !== $memberId || !$this->mayView($actorId, $tenantId, (string) $rule['section'])) {
            return ['ok' => false, 'message' => 'Règle introuvable ou déjà retirée.'];
        }

        return $this->service()->revoke($tenantId, $actorId, $actorLabel, $ruleId, $reason !== '' ? $reason : 'Retrait depuis la fiche du membre');
    }

    /**
     * @param array<string, mixed> $input
     * @return array{rules: list<array<string, mixed>>, errors: list<string>, skipped: list<string>}
     */
    private function plan(int $tenantId, int $actorId, int $memberId, array $input): array
    {
        if (!$this->visibleTo($tenantId, $actorId)) {
            return ['rules' => [], 'errors' => ['Seul le droit de gestion SSE permet de modifier les droits d’accès.'], 'skipped' => []];
        }
        // Le membre doit être de la communauté de la session et visible dans l'annuaire (jamais un identifiant d'ailleurs).
        if ($this->directory()->member($tenantId, $memberId) === null) {
            return ['rules' => [], 'errors' => ['Bénéficiaire inconnu.'], 'skipped' => []];
        }

        return $this->service()->planSimple($tenantId, $actorId, [
            'sections' => $input['sections'] ?? [],
            'access' => $input['access'] ?? '',
            'subject_type' => C::SUBJECT_USER,
            'subject_id' => $memberId,
            'effect' => ($input['effect'] ?? '') === C::EFFECT_DENY ? C::EFFECT_DENY : C::EFFECT_ALLOW,
            'starts_at' => '',
            'ends_at' => $input['ends_at'] ?? '',
            'reason' => $input['reason'] ?? '',
            'source' => '',
        ]);
    }

    private function mayView(int $actorId, int $tenantId, string $section): bool
    {
        return C::hasSection($section) && $this->policy()->allows($actorId, $section, C::LEVEL_EXISTS, [
            'tenant_id' => $tenantId,
            'guest' => false,
            'door_mode' => SseAccessPolicy::DOOR_ENTRY,
        ]);
    }
}
