<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseInterestCaseHistoryRepository;
use App\Repositories\SseInterestCaseLinkRepository;
use App\Repositories\SseInterestCaseRepository as Cases;
use App\Support\SseInterestCaseCatalog as Catalog;
use App\Support\SseInterestMarkup;

/**
 * Dossiers d'intérêt SSE : création, lecture, modification, changement d'état, suppression logique et définitive.
 *
 * Toutes les règles vivent ici, pas dans le contrôleur : validation serveur stricte, droits (SseInterestActor), plafond
 * d'habilitation sur la liste, la fiche, la recherche et les exports, accès nominatifs, transitions d'état (Catalog::TRANSITIONS),
 * verrou optimiste (numéro de version), historique champ par champ, journal d'audit SSE et notifications (centre existant, une
 * seule par événement et par personne). La communauté est toujours celle de l'acteur : aucune méthode ne lit un tenant ailleurs.
 */
final class SseInterestCaseService
{
    /** Champs texte de l'ouverture d'un signalement, hors formulaire court : longueur maximale. */
    private const LEGACY_TEXT = [
        'apparent_sex' => 32, 'estimated_age_range' => 64, 'suspected_nationality' => 100, 'origin_operator' => 160,
        'source_label' => 200, 'source_reliability' => 32, 'mission_label' => 160,
        'observed_elements' => 8000, 'analysis_facts' => 8000, 'analysis_assumptions' => 8000, 'analysis_contradictions' => 8000,
        'analysis_questions' => 8000, 'collection_needs' => 8000, 'operational_risk' => 8000, 'recommendations' => 8000,
    ];

    /** Champs modifiables en place (le reste passe par une action dédiée). */
    private const EDITABLE = [
        'temporary_designation', 'opening_reason', 'description', 'case_type', 'interest_level', 'classification',
        'assignee_user_id', 'due_at', 'tags', 'suspected_alias', 'suspected_affiliation', 'confidence_level',
    ];

    private const EXTENDED_ONLY = ['case_type', 'classification', 'assignee_user_id', 'due_at', 'tags'];

    private ?\Closure $audit;
    private ?\Closure $notify;
    private ?\Closure $blockers;
    private ?\Closure $clock;

    /**
     * @param array<string, string> $types catalogue des types de la communauté
     */
    public function __construct(
        private Cases $cases,
        private SseInterestCaseHistoryRepository $history,
        private SseInterestCaseLinkRepository $links,
        private SseInterestLinkResolver $resolver,
        ?\Closure $audit = null,
        ?\Closure $notify = null,
        ?\Closure $blockers = null,
        private array $types = Catalog::DEFAULT_TYPES,
        ?\Closure $clock = null,
    ) {
        $this->audit = $audit;
        $this->notify = $notify;
        $this->blockers = $blockers;
        $this->clock = $clock;
    }

    /** @return array<string, string> */
    public function types(): array
    {
        return $this->types;
    }

    public function repository(): Cases
    {
        return $this->cases;
    }

    private function now(): string
    {
        return $this->clock !== null ? (string) ($this->clock)() : date('Y-m-d H:i:s');
    }

    // ------------------------------------------------------------------ Lecture

    /**
     * Filtres, tri et page d'une requête de la file (la saisie est assainie ici, jamais passée telle quelle au dépôt).
     *
     * @param array<string, mixed> $query
     * @return array{filters: array<string, mixed>, sort: string, page: int, per_page: int}
     */
    public function normalizeQuery(SseInterestActor $actor, array $query): array
    {
        $tab = (string) ($query['onglet'] ?? $query['tab'] ?? Catalog::TAB_OPEN);
        if ($tab !== 'tous' && !isset(Catalog::TABS[$tab]) && $tab !== Catalog::TAB_TRASH) {
            $tab = Catalog::TAB_OPEN;
        }
        if ($tab === Catalog::TAB_TRASH && !$actor->canDelete && !$actor->canManage) {
            $tab = Catalog::TAB_OPEN;
        }
        $assignee = (string) ($query['responsable'] ?? $query['assignee'] ?? '');
        $assigneeId = match (true) {
            $assignee === 'moi' => (int) $actor->userId,
            $assignee === 'aucun' => -1,
            ctype_digit($assignee) => (int) $assignee,
            default => 0,
        };
        $priority = (string) ($query['priorite'] ?? $query['priority'] ?? '');
        $type = (string) ($query['type'] ?? '');
        $class = (string) ($query['classification'] ?? '');
        $status = (string) ($query['etat'] ?? $query['status'] ?? '');
        $sort = (string) ($query['tri'] ?? $query['sort'] ?? 'recent');

        return [
            'filters' => [
                'tab' => $tab === 'tous' ? '' : $tab,
                'q' => mb_substr(trim((string) ($query['q'] ?? '')), 0, 120),
                'priority' => isset(Cases::INTEREST[$priority]) ? $priority : '',
                'type' => isset($this->types[$type]) ? $type : '',
                'assignee' => $assigneeId,
                'classification' => isset(SseRedactionService::LEVELS[$class]) ? $class : '',
                'overdue' => !empty($query['en_retard']) || !empty($query['overdue']),
                'status' => isset(Cases::STATUSES[$status]) ? $status : '',
            ],
            'sort' => isset(Catalog::SORTS[$sort]) ? $sort : 'recent',
            'page' => max(1, (int) ($query['page'] ?? 1)),
            'per_page' => max(5, min(Catalog::PER_PAGE_MAX, (int) ($query['par_page'] ?? Catalog::PER_PAGE))),
        ];
    }

    /**
     * Une page de la file pour cet acteur : ce qu'il n'a pas le droit de lire n'y figure pas, ne compte nulle part.
     *
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function page(SseInterestActor $actor, array $query): array
    {
        $this->requireRead($actor);
        $q = $this->normalizeQuery($actor, $query);
        $res = $this->cases->queryPage($actor->tenantId, $q['filters'], $actor->viewer(), $q['sort'], $q['page'], $q['per_page']);
        $res['rows'] = $this->decorate($actor->tenantId, $res['rows']);
        $res['query'] = $q;
        $res['capabilities'] = $this->cases->capabilities();
        $res['degraded'] = !$this->cases->isFullySupported();

        return $res;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public function decorate(int $tenantId, array $rows): array
    {
        $labels = $this->cases->memberLabels($tenantId, array_map(static fn (array $r): int => (int) ($r['assignee_user_id'] ?? 0), $rows));
        foreach ($rows as &$row) {
            $row['assignee_label'] = $row['assignee_user_id'] !== null ? ($labels[(int) $row['assignee_user_id']] ?? 'Membre') : null;
            $row['type_label'] = ($row['case_type'] ?? '') !== '' ? ($this->types[(string) $row['case_type']] ?? (string) $row['case_type']) : null;
            $row['classification_label'] = SseRedactionService::levelLabel((string) $row['classification']);
        }
        unset($row);

        return $rows;
    }

    /**
     * Un dossier lisible par l'acteur, sinon « introuvable » (on ne dit pas qu'il existe : protection contre l'énumération d'identifiants).
     *
     * @return array<string, mixed>
     */
    public function find(SseInterestActor $actor, int $id): array
    {
        $this->requireRead($actor);
        $case = $id > 0 ? $this->cases->findForTenant($id, $actor->tenantId) : null;
        if ($case === null || !$this->canSee($actor, $case)) {
            throw SseInterestCaseException::notFound();
        }

        return $this->decorate($actor->tenantId, [$case])[0];
    }

    /** @param array<string, mixed> $case */
    public function canSee(SseInterestActor $actor, array $case): bool
    {
        if ((int) ($case['tenant_id'] ?? 0) !== $actor->tenantId || !$actor->canRead) {
            return false;
        }
        if (!empty($case['deleted_at']) && !$actor->canDelete && !$actor->canManage) {
            return false;
        }
        if (SseRedactionService::levelRank((string) ($case['classification'] ?? 'interne')) > $actor->maxRank()) {
            return false;
        }

        return $this->cases->userCanAccessCase($case, $actor->userId, $actor->canManage);
    }

    /**
     * Tout ce que la fiche affiche, rassemblé et filtré pour l'acteur.
     *
     * @return array<string, mixed>
     */
    public function detail(SseInterestActor $actor, int $id, int $historyLimit = 100): array
    {
        $case = $this->find($actor, $id);
        $t = $actor->tenantId;
        $caps = $this->cases->capabilities();
        $labels = $this->cases->memberLabels($t, [(int) ($case['created_by'] ?? 0), (int) ($case['deleted_by'] ?? 0)]);
        $hist = $this->history->listForCase($t, $id, $historyLimit);
        $watchers = $this->links->watchers($t, $id);

        return [
            'case' => $case,
            'capabilities' => $caps,
            'degraded' => !$this->cases->isFullySupported(),
            'links' => $this->presentLinks($actor, $this->links->listForCase($t, $id)),
            'history' => $this->presentHistory($t, $hist),
            'history_total' => $this->history->countForCase($t, $id),
            'updates' => $this->cases->listUpdates($t, $id),
            'watching' => $actor->userId !== null && in_array($actor->userId, $watchers, true),
            'watchers_count' => count($watchers),
            'creator_label' => $labels[(int) ($case['created_by'] ?? 0)] ?? null,
            'deleted_by_label' => $labels[(int) ($case['deleted_by'] ?? 0)] ?? null,
            'next_statuses' => $this->nextStatuses($actor, $case),
            'purge_blockers' => !empty($case['deleted_at']) && $actor->canManage ? $this->purgeBlockers($actor->tenantId, $case) : [],
            'days_left' => !empty($case['deleted_at']) ? $this->trashDaysLeft((string) $case['deleted_at']) : null,
        ];
    }

    /**
     * États que l'acteur peut proposer depuis l'état courant (transitions valides, droits compris).
     *
     * @param array<string, mixed> $case
     * @return list<array{key: string, label: string, needs_reason: bool}>
     */
    public function nextStatuses(SseInterestActor $actor, array $case): array
    {
        if (!empty($case['deleted_at']) || !$actor->canWrite) {
            return [];
        }
        $from = (string) $case['status'];
        $out = [];
        foreach (Catalog::nextStatuses($from) as $to) {
            if (in_array($to, Catalog::MANAGER_TARGETS, true) && !$actor->canManage) {
                continue;
            }
            if ($to === 'archive' && !$actor->canDelete) {
                continue;
            }
            $out[] = ['key' => $to, 'label' => Cases::STATUSES[$to] ?? $to, 'needs_reason' => Catalog::needsReason($from, $to)];
        }

        return $out;
    }

    /**
     * Aperçu du tiroir : l'essentiel d'un dossier, sans la fiche entière.
     *
     * @return array<string, mixed>
     */
    public function preview(SseInterestActor $actor, int $id): array
    {
        $case = $this->find($actor, $id);
        $t = $actor->tenantId;

        return [
            'id' => (int) $case['id'],
            'reference' => (string) $case['reference_code'],
            'title' => (string) $case['temporary_designation'],
            'status' => (string) $case['status'],
            'status_label' => (string) $case['status_label'],
            'priority' => (string) $case['interest_level'],
            'priority_code' => (string) $case['priority_code'],
            'priority_label' => (string) $case['interest_label'],
            'classification' => (string) $case['classification'],
            'classification_label' => (string) $case['classification_label'],
            'type_label' => $case['type_label'],
            'assignee_label' => $case['assignee_label'],
            'due_at' => $case['due_at'] ?? null,
            'is_overdue' => (bool) $case['is_overdue'],
            'tags' => $case['tag_list'],
            'reason_html' => SseInterestMarkup::toHtml(mb_substr((string) $case['opening_reason'], 0, 600)),
            'description_html' => SseInterestMarkup::toHtml(mb_substr((string) ($case['description'] ?? ''), 0, 600)),
            'links_count' => $this->links->countForCase($t, $id),
            'updated_at' => (string) $case['updated_at'],
            'is_deleted' => (bool) $case['is_deleted'],
        ];
    }

    // ------------------------------------------------------------------ Création

    /**
     * Ouvre un dossier. Brouillon : seul le titre est exigé ; sinon motif, signature et champs valides.
     *
     * @param array<string, mixed> $input
     * @return array{id: int, reference: string, links_failed: int}
     */
    public function create(SseInterestActor $actor, array $input): array
    {
        $this->requireWrite($actor);
        $draft = !empty($input['draft']);
        [$data, $errors] = $this->validate($actor, $input, true, $draft);
        if (!$draft && empty($input['digital_signature'])) {
            $errors['digital_signature'] = 'Cochez la signature numérique du signalement.';
        }
        if ($errors !== []) {
            throw SseInterestCaseException::validation($errors);
        }
        $data['status'] = $draft ? 'brouillon' : 'signalement_recu';
        $data['created_by'] = $actor->userId;
        $data['origin_operator'] = ($data['origin_operator'] ?? null) ?: $actor->label;
        $data['signed_by_label'] = $actor->label;
        $data['signed_at'] = $draft ? null : $this->now();
        $id = $this->cases->create($actor->tenantId, $data);
        if ($id < 1) {
            throw SseInterestCaseException::rule('Le dossier n’a pas pu être enregistré. Réessayez ; si le problème continue, prévenez un administrateur.');
        }
        $case = $this->cases->findForTenant($id, $actor->tenantId) ?? ['reference_code' => '', 'id' => $id];
        $this->history->add($actor->tenantId, $id, [
            'action' => 'create', 'new_value' => (string) $data['temporary_designation'], 'reason' => $draft ? 'Brouillon' : null,
            'actor_user_id' => $actor->userId, 'actor_label' => $actor->label, 'version_after' => 1,
        ]);
        if ($actor->userId !== null) {
            $this->links->watch($actor->tenantId, $id, $actor->userId);
        }
        $this->audit($actor, 'interest_case.create', $id, null, null, ['reference' => $case['reference_code'], 'status' => $data['status']]);
        $assignee = (int) ($data['assignee_user_id'] ?? 0);
        if ($assignee > 0 && $assignee !== $actor->userId && $this->recipientMaySee($case, $assignee)) {
            $this->notifyUsers($actor, $case, [$assignee], 'assigned', 'Un dossier d’intérêt vous est confié.');
        }

        $failed = 0;
        if ($this->cases->capabilities()['links']) {
            foreach (array_slice((array) ($input['pending_links'] ?? []), 0, 10) as $raw) {
                $parts = explode(':', (string) $raw, 2);
                if (count($parts) !== 2 || !isset(Catalog::LINK_TYPES[$parts[0]])) {
                    $failed++;
                    continue;
                }
                try {
                    $this->addLink($actor, $id, $parts[0], $parts[0] === 'vehicle' ? '' : $parts[1], $parts[0] === 'vehicle' ? $parts[1] : '', '');
                } catch (SseInterestCaseException) {
                    $failed++;
                }
            }
        }

        return ['id' => $id, 'reference' => (string) $case['reference_code'], 'links_failed' => $failed];
    }

    // ------------------------------------------------------------------ Modification

    /**
     * Modifie les champs présents dans $input, si le dossier est encore à la version lue (verrou optimiste).
     *
     * @param array<string, mixed> $input
     * @return array{changed: list<string>, version: int}
     */
    public function update(SseInterestActor $actor, int $id, array $input, ?int $expectedVersion, ?string $expectedUpdatedAt = null): array
    {
        $this->requireWrite($actor);
        $case = $this->find($actor, $id);
        $this->requireNotDeleted($case);
        $caps = $this->cases->capabilities();
        if ($caps['columns'] && $expectedVersion === null) {
            throw SseInterestCaseException::validation(['version' => 'La page est périmée : rechargez le dossier avant de l’enregistrer.']);
        }
        $present = array_values(array_filter(self::EDITABLE, static fn (string $f): bool => array_key_exists($f, $input)));
        if (!$caps['columns']) {
            $present = array_values(array_diff($present, self::EXTENDED_ONLY));
        }
        [$data, $errors] = $this->validate($actor, $input, false, false, $present, $case);
        $set = [];
        $changes = [];
        foreach ($present as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $old = self::norm($case[$field] ?? null);
            $new = self::norm($data[$field]);
            if ($old === $new) {
                continue;
            }
            $set[$field] = $data[$field];
            $changes[$field] = [$old, $new];
        }
        $reason = trim((string) ($input['reason'] ?? ''));
        if (isset($changes['classification']) && SseRedactionService::levelRank((string) $changes['classification'][1]) < SseRedactionService::levelRank((string) $changes['classification'][0]) && mb_strlen($reason) < 3) {
            $errors['reason'] = 'Indiquez le motif de l’abaissement de la classification.';
        }
        if (mb_strlen($reason) > Catalog::CHANGE_REASON_MAX) {
            $errors['reason'] = 'Le motif ne doit pas dépasser ' . Catalog::CHANGE_REASON_MAX . ' signes.';
        }
        if ($errors !== []) {
            throw SseInterestCaseException::validation($errors);
        }
        if ($set === []) {
            return ['changed' => [], 'version' => (int) $case['version']];
        }
        $rows = $this->cases->updateGuarded($id, $actor->tenantId, $set, $expectedVersion, $expectedUpdatedAt ?? ($case['updated_at'] ?? null));
        if ($rows < 1) {
            throw $this->conflictFor($actor, $id);
        }
        $version = $caps['columns'] ? (int) $case['version'] + 1 : (int) $case['version'];
        foreach ($changes as $field => [$old, $new]) {
            $isClass = $field === 'classification';
            $this->history->add($actor->tenantId, $id, [
                'action' => $isClass ? 'classification' : 'update', 'field_key' => $field, 'old_value' => $old, 'new_value' => $new,
                'reason' => $reason !== '' ? $reason : null, 'actor_user_id' => $actor->userId, 'actor_label' => $actor->label, 'version_after' => $version,
            ]);
        }
        $this->audit($actor, 'interest_case.update', $id, $reason, array_map(static fn (array $c): string => $c[0], $changes), array_map(static fn (array $c): string => $c[1], $changes));
        $fresh = $this->cases->findForTenant($id, $actor->tenantId) ?? $case;
        $targets = [];
        if (isset($set['assignee_user_id']) && (int) $set['assignee_user_id'] > 0) {
            $targets[] = (int) $set['assignee_user_id'];
        }
        $this->notifyUsers($actor, $fresh, $targets, 'assigned', 'Un dossier d’intérêt vous est confié.');
        $this->notifyInterested($actor, $fresh, 'updated', 'Dossier modifié : ' . implode(', ', array_map(static fn (string $f): string => mb_strtolower(Catalog::FIELD_LABELS[$f] ?? $f), array_keys($changes))) . '.', $targets);

        return ['changed' => array_keys($changes), 'version' => $version];
    }

    /**
     * Change l'état : transition valide, droit suffisant, motif quand la règle l'exige.
     *
     * @return array{from: string, to: string, version: int}
     */
    public function transition(SseInterestActor $actor, int $id, string $to, string $reason, ?int $expectedVersion, ?string $expectedUpdatedAt = null): array
    {
        $this->requireWrite($actor);
        $case = $this->find($actor, $id);
        $this->requireNotDeleted($case);
        $from = (string) $case['status'];
        $reason = trim($reason);
        if (!Catalog::knownStatus($to)) {
            throw SseInterestCaseException::validation(['status' => 'Choisissez un état valable pour ce dossier.']);
        }
        if ($from === $to) {
            throw SseInterestCaseException::rule('Le dossier est déjà « ' . (Cases::STATUSES[$to] ?? $to) . ' ».');
        }
        if (!Catalog::canTransition($from, $to)) {
            $allowed = array_map(static fn (string $s): string => '« ' . (Cases::STATUSES[$s] ?? $s) . ' »', Catalog::nextStatuses($from));
            throw SseInterestCaseException::rule(
                'Passage impossible de « ' . (Cases::STATUSES[$from] ?? $from) . ' » à « ' . (Cases::STATUSES[$to] ?? $to) . ' ».'
                . ($allowed !== [] ? ' États possibles : ' . implode(', ', $allowed) . '.' : ' Ce dossier n’a pas d’état suivant.')
            );
        }
        if (in_array($to, Catalog::MANAGER_TARGETS, true) && !$actor->canManage) {
            throw SseInterestCaseException::forbidden('Cette décision de validation est réservée aux gestionnaires des dossiers.');
        }
        if ($to === 'archive' && !$actor->canDelete) {
            throw SseInterestCaseException::forbidden('Vous n’avez pas le droit d’archiver les dossiers.');
        }
        if (Catalog::needsReason($from, $to) && mb_strlen($reason) < 3) {
            throw SseInterestCaseException::validation(['reason' => 'Indiquez le motif de ce changement d’état (3 signes au moins).']);
        }
        if (mb_strlen($reason) > Catalog::CHANGE_REASON_MAX) {
            throw SseInterestCaseException::validation(['reason' => 'Le motif ne doit pas dépasser ' . Catalog::CHANGE_REASON_MAX . ' signes.']);
        }
        $caps = $this->cases->capabilities();
        if ($caps['columns'] && $expectedVersion === null) {
            throw SseInterestCaseException::validation(['version' => 'La page est périmée : rechargez le dossier avant de changer son état.']);
        }
        $set = ['status' => $to];
        if ($to === 'archive') {
            $set['archived_at'] = $this->now();
            if ($caps['columns']) {
                $set['archive_from_status'] = $from;
            }
        }
        if (in_array($to, ['identite_consolidee', 'correspondance_probable'], true)) {
            $set['validated_by'] = $actor->userId;
            $set['validated_at'] = $this->now();
        }
        $rows = $this->cases->updateGuarded($id, $actor->tenantId, $set, $expectedVersion, $expectedUpdatedAt ?? ($case['updated_at'] ?? null));
        if ($rows < 1) {
            throw $this->conflictFor($actor, $id);
        }
        $version = $caps['columns'] ? (int) $case['version'] + 1 : (int) $case['version'];
        $this->record($actor, $id, $caps['history'], $to === 'archive' ? 'archive' : 'status', 'status', $from, $to, $reason, $version);
        $this->audit($actor, 'interest_case.status', $id, $reason, ['status' => $from], ['status' => $to]);
        $fresh = $this->cases->findForTenant($id, $actor->tenantId) ?? $case;
        $this->notifyInterested($actor, $fresh, 'status', 'État passé à « ' . (Cases::STATUSES[$to] ?? $to) . ' ».');

        return ['from' => $from, 'to' => $to, 'version' => $version];
    }

    /** Sortie des archives : le dossier retrouve l'état qu'il avait avant d'être archivé. */
    public function unarchive(SseInterestActor $actor, int $id, string $reason, ?int $expectedVersion): array
    {
        $this->requireWrite($actor);
        if (!$actor->canDelete) {
            throw SseInterestCaseException::forbidden('Vous n’avez pas le droit de sortir un dossier des archives.');
        }
        $case = $this->find($actor, $id);
        $this->requireNotDeleted($case);
        if ((string) $case['status'] !== 'archive') {
            throw SseInterestCaseException::rule('Ce dossier n’est pas archivé.');
        }
        $to = (string) ($case['archive_from_status'] ?? '');
        if (!Catalog::knownStatus($to) || $to === 'archive') {
            $to = 'a_qualifier';
        }
        $caps = $this->cases->capabilities();
        $rows = $this->cases->updateGuarded($id, $actor->tenantId, ['status' => $to, 'archived_at' => null] + ($caps['columns'] ? ['archive_from_status' => null] : []), $expectedVersion, (string) ($case['updated_at'] ?? ''));
        if ($rows < 1) {
            throw $this->conflictFor($actor, $id);
        }
        $version = $caps['columns'] ? (int) $case['version'] + 1 : (int) $case['version'];
        $this->record($actor, $id, $caps['history'], 'unarchive', 'status', 'archive', $to, trim($reason), $version);
        $this->audit($actor, 'interest_case.unarchive', $id, $reason, ['status' => 'archive'], ['status' => $to]);

        return ['from' => 'archive', 'to' => $to, 'version' => $version];
    }

    // ------------------------------------------------------------------ Suppression

    /** Mise à la corbeille (suppression logique) : restaurable pendant 30 jours. */
    public function trash(SseInterestActor $actor, int $id, string $reason, ?int $expectedVersion): void
    {
        $this->requireWrite($actor);
        if (!$actor->canDelete) {
            throw SseInterestCaseException::forbidden('Vous n’avez pas le droit de supprimer les dossiers.');
        }
        if (!$this->cases->capabilities()['columns']) {
            throw SseInterestCaseException::degraded('La corbeille');
        }
        $case = $this->find($actor, $id);
        $this->requireNotDeleted($case);
        $reason = trim($reason);
        if (mb_strlen($reason) < 3 || mb_strlen($reason) > Catalog::CHANGE_REASON_MAX) {
            throw SseInterestCaseException::validation(['reason' => 'Indiquez le motif de la suppression (3 à ' . Catalog::CHANGE_REASON_MAX . ' signes).']);
        }
        if ($expectedVersion === null) {
            throw SseInterestCaseException::validation(['version' => 'La page est périmée : rechargez le dossier avant de le supprimer.']);
        }
        $rows = $this->cases->updateGuarded($id, $actor->tenantId, ['deleted_at' => $this->now(), 'deleted_by' => $actor->userId, 'delete_reason' => $reason], $expectedVersion);
        if ($rows < 1) {
            throw $this->conflictFor($actor, $id);
        }
        $this->history->add($actor->tenantId, $id, ['action' => 'trash', 'reason' => $reason, 'actor_user_id' => $actor->userId, 'actor_label' => $actor->label, 'version_after' => (int) $case['version'] + 1]);
        $this->audit($actor, 'interest_case.trash', $id, $reason, ['reference' => $case['reference_code'], 'status' => $case['status']], null);
        $this->notifyInterested($actor, $case, 'trashed', 'Dossier mis à la corbeille.');
    }

    public function restore(SseInterestActor $actor, int $id): void
    {
        $this->requireWrite($actor);
        if (!$actor->canDelete) {
            throw SseInterestCaseException::forbidden('Vous n’avez pas le droit de restaurer les dossiers.');
        }
        $case = $this->find($actor, $id);
        if (empty($case['deleted_at'])) {
            throw SseInterestCaseException::rule('Ce dossier n’est pas dans la corbeille.');
        }
        if ($this->trashDaysLeft((string) $case['deleted_at']) < 1) {
            throw SseInterestCaseException::rule('Le délai de restauration de ' . Catalog::TRASH_DAYS . ' jours est dépassé.');
        }
        $rows = $this->cases->updateGuarded($id, $actor->tenantId, ['deleted_at' => null, 'deleted_by' => null, 'delete_reason' => null], (int) $case['version']);
        if ($rows < 1) {
            throw $this->conflictFor($actor, $id);
        }
        $this->history->add($actor->tenantId, $id, ['action' => 'restore', 'actor_user_id' => $actor->userId, 'actor_label' => $actor->label, 'version_after' => (int) $case['version'] + 1]);
        $this->audit($actor, 'interest_case.restore', $id, null, null, ['reference' => $case['reference_code']]);
        $this->notifyInterested($actor, $case, 'restored', 'Dossier restauré.');
    }

    /**
     * Suppression définitive : gestionnaires seulement, dossier déjà à la corbeille, référence retapée, motif, et aucun obstacle
     * (état validé, dossier constitué, compte rendu JNET) : dans ce cas on archive au lieu de supprimer.
     */
    public function purge(SseInterestActor $actor, int $id, string $reason, string $typedReference): void
    {
        $this->requireWrite($actor);
        if (!$actor->canManage) {
            throw SseInterestCaseException::forbidden('La suppression définitive est réservée aux gestionnaires des dossiers.');
        }
        $case = $this->find($actor, $id);
        if (empty($case['deleted_at'])) {
            throw SseInterestCaseException::rule('Mettez d’abord le dossier à la corbeille : la suppression définitive ne s’applique qu’à un dossier supprimé.');
        }
        $reason = trim($reason);
        $errors = [];
        if (mb_strlen($reason) < 3 || mb_strlen($reason) > Catalog::CHANGE_REASON_MAX) {
            $errors['reason'] = 'Indiquez le motif de la suppression définitive (3 à ' . Catalog::CHANGE_REASON_MAX . ' signes).';
        }
        if (strcasecmp(trim($typedReference), (string) $case['reference_code']) !== 0) {
            $errors['confirm'] = 'Retapez la référence « ' . $case['reference_code'] . ' » pour confirmer.';
        }
        if ($errors !== []) {
            throw SseInterestCaseException::validation($errors);
        }
        $blockers = $this->purgeBlockers($actor->tenantId, $case);
        if ($blockers !== []) {
            throw SseInterestCaseException::rule('Suppression définitive impossible : ' . implode(' ; ', $blockers) . '. Restaurez le dossier puis archivez-le.');
        }
        $snapshot = ['reference' => $case['reference_code'], 'title' => $case['temporary_designation'], 'status' => $case['status'], 'classification' => $case['classification'], 'links' => $this->links->countForCase($actor->tenantId, $id)];
        if (!$this->cases->purge($id, $actor->tenantId)) {
            throw SseInterestCaseException::rule('Le dossier n’a pas pu être supprimé. Réessayez.');
        }
        $this->audit($actor, 'interest_case.purge', $id, $reason, $snapshot, null);
    }

    /**
     * Ce qui interdit de supprimer définitivement : un état validé, un dossier constitué à partir de celui-ci, un compte rendu JNET qui le cite.
     *
     * @param array<string, mixed> $case
     * @return list<string>
     */
    public function purgeBlockers(int $tenantId, array $case): array
    {
        $out = [];
        if (in_array((string) $case['status'], ['identite_consolidee', 'correspondance_probable'], true) || !empty($case['validated_at'])) {
            $out[] = 'le dossier porte une décision de validation';
        }
        if ($this->blockers !== null) {
            return array_merge($out, array_values(array_map('strval', ($this->blockers)($tenantId, $case))));
        }
        try {
            if ((new \App\Repositories\SseCaseRepository())->findByInterestCase($tenantId, (int) $case['id']) !== null) {
                $out[] = 'un dossier a été constitué à partir de celui-ci';
            }
        } catch (\Throwable) {
        }
        $jnet = $this->jnetCitations($tenantId, (int) $case['id']);
        if ($jnet > 0) {
            $out[] = 'il est cité dans ' . $jnet . ' compte(s) rendu(s) JNET';
        }

        return $out;
    }

    /** Nombre de liens JNET qui citent ce dossier (cible « di-N ») : point d'extension documenté, lecture seule. */
    public function jnetCitations(int $tenantId, int $caseId): int
    {
        try {
            return (new \App\Repositories\JnetLinksRepository())->countOf($tenantId, 'cible', 'di-' . $caseId);
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Fin de délai : les dossiers à la corbeille depuis plus de 30 jours sont supprimés, sauf obstacle (ils sont alors restaurés
     * et archivés pour ne rien perdre). Appelé à l'ouverture de la corbeille, sans acteur humain.
     *
     * @return array{purged: int, archived: int}
     */
    public function sweepTrash(int $tenantId, int $max = 20): array
    {
        $out = ['purged' => 0, 'archived' => 0];
        $cutoff = date('Y-m-d H:i:s', strtotime($this->now()) - Catalog::TRASH_DAYS * 86400);
        foreach (array_slice($this->cases->expiredTrashIds($tenantId, $cutoff), 0, $max) as $id) {
            $case = $this->cases->findForTenant($id, $tenantId);
            if ($case === null) {
                continue;
            }
            $system = ['tenant_id' => $tenantId, 'user' => null, 'label' => 'Système'];
            if ($this->purgeBlockers($tenantId, $case) === []) {
                $snapshot = ['reference' => $case['reference_code'], 'title' => $case['temporary_designation'], 'status' => $case['status']];
                if ($this->cases->purge($id, $tenantId)) {
                    $this->writeAudit($tenantId, null, $system['label'], 'interest_case.purge', $id, 'Délai de ' . Catalog::TRASH_DAYS . ' jours dépassé', $snapshot, null);
                    $out['purged']++;
                }
                continue;
            }
            $this->cases->updateGuarded($id, $tenantId, ['deleted_at' => null, 'deleted_by' => null, 'delete_reason' => null, 'status' => 'archive', 'archived_at' => $this->now(), 'archive_from_status' => (string) $case['status']], (int) $case['version']);
            $this->history->add($tenantId, $id, ['action' => 'archive', 'reason' => 'Corbeille échue : dossier lié à une décision, archivé au lieu d’être supprimé.', 'actor_label' => $system['label'], 'version_after' => (int) $case['version'] + 1]);
            $this->writeAudit($tenantId, null, $system['label'], 'interest_case.archive', $id, 'Corbeille échue avec obstacle à la suppression', null, ['status' => 'archive']);
            $out['archived']++;
        }

        return $out;
    }

    public function trashDaysLeft(string $deletedAt): int
    {
        $ts = strtotime($deletedAt);
        if ($ts === false) {
            return 0;
        }

        return max(0, (int) ceil(($ts + Catalog::TRASH_DAYS * 86400 - strtotime($this->now())) / 86400));
    }

    // ------------------------------------------------------------------ Éléments liés, notes, abonnement

    /**
     * @return list<array{id: string, label: string, hint: string}>
     */
    public function searchLinks(SseInterestActor $actor, string $type, string $query): array
    {
        $this->requireWrite($actor);
        if (!in_array($type, Catalog::LINK_TYPES_WITH_TARGET, true) || mb_strlen(trim($query)) < 2) {
            return [];
        }

        return $this->resolver->search($actor, $type, $query);
    }

    /** Lie un élément. Les véhicules, sans registre, sont saisis en texte libre (plaque ou description). */
    public function addLink(SseInterestActor $actor, int $id, string $type, string $targetId, string $label, string $note): array
    {
        $this->requireWrite($actor);
        $case = $this->find($actor, $id);
        $this->requireNotDeleted($case);
        if (!$this->cases->capabilities()['links']) {
            throw SseInterestCaseException::degraded('Le rattachement d’éléments');
        }
        if (!isset(Catalog::LINK_TYPES[$type])) {
            throw SseInterestCaseException::validation(['link_type' => 'Choisissez un type d’élément à lier.']);
        }
        $note = mb_substr(trim($note), 0, Catalog::CHANGE_REASON_MAX);
        if ($type === 'vehicle') {
            $label = trim((string) preg_replace('/\s+/u', ' ', $label));
            if (mb_strlen($label) < 2 || mb_strlen($label) > 120) {
                throw SseInterestCaseException::validation(['label' => 'Indiquez la plaque ou la description du véhicule (2 à 120 signes).']);
            }
            $targetId = mb_substr(strtoupper((string) preg_replace('/[^A-Za-z0-9]+/', '', $label)) ?: md5($label), 0, 40);
        } else {
            $targetId = trim($targetId);
            if ($targetId === '' || !ctype_digit($targetId)) {
                throw SseInterestCaseException::validation(['target' => 'Choisissez un élément dans la liste de recherche.']);
            }
            $found = $this->resolver->describe($actor, $type, $targetId);
            if ($found === null || $found['masked']) {
                throw SseInterestCaseException::validation(['target' => 'Cet élément est introuvable ou vous n’avez pas le droit de le lier.']);
            }
            if ($type === 'case' || $type === 'fiche' || $type === 'document') {
                // rien de plus : le droit de lecture vérifié par le résolveur suffit
            }
            $label = $found['label'];
        }
        if ($this->links->countForCase($actor->tenantId, $id) >= Catalog::LINKS_MAX) {
            throw SseInterestCaseException::rule('Un dossier porte au plus ' . Catalog::LINKS_MAX . ' éléments liés.');
        }
        if ($this->links->exists($actor->tenantId, $id, $type, $targetId)) {
            throw SseInterestCaseException::validation(['target' => 'Cet élément est déjà lié à ce dossier.']);
        }
        $linkId = $this->links->add($actor->tenantId, $id, $type, $targetId, $label, $note, $actor->userId);
        $this->bump($actor, $id);
        $this->history->add($actor->tenantId, $id, ['action' => 'link_add', 'field_key' => $type, 'new_value' => $label, 'reason' => $note !== '' ? $note : null, 'actor_user_id' => $actor->userId, 'actor_label' => $actor->label, 'version_after' => (int) $case['version']]);
        $this->audit($actor, 'interest_case.link_add', $id, $note, null, ['type' => $type, 'target' => $targetId, 'label' => $label]);

        return ['id' => $linkId, 'label' => $label, 'type' => $type];
    }

    public function removeLink(SseInterestActor $actor, int $id, int $linkId, string $reason): void
    {
        $this->requireWrite($actor);
        $case = $this->find($actor, $id);
        $this->requireNotDeleted($case);
        $reason = trim($reason);
        if (mb_strlen($reason) < 3 || mb_strlen($reason) > Catalog::CHANGE_REASON_MAX) {
            throw SseInterestCaseException::validation(['reason' => 'Indiquez le motif du retrait (3 à ' . Catalog::CHANGE_REASON_MAX . ' signes).']);
        }
        $link = $this->links->find($actor->tenantId, $id, $linkId);
        if ($link === null) {
            throw SseInterestCaseException::rule('Cet élément n’est plus lié à ce dossier.');
        }
        $this->links->remove($actor->tenantId, $id, $linkId);
        $this->bump($actor, $id);
        $this->history->add($actor->tenantId, $id, ['action' => 'link_remove', 'field_key' => (string) $link['link_type'], 'old_value' => (string) $link['label'], 'reason' => $reason, 'actor_user_id' => $actor->userId, 'actor_label' => $actor->label, 'version_after' => (int) $case['version']]);
        $this->audit($actor, 'interest_case.link_remove', $id, $reason, ['type' => $link['link_type'], 'target' => $link['target_id'], 'label' => $link['label']], null);
    }

    /**
     * Éléments liés tels que le lecteur a le droit de les voir : titre et adresse revérifiés, sinon « masqué » sans titre.
     *
     * @param list<array<string, mixed>> $links
     * @return list<array<string, mixed>>
     */
    public function presentLinks(SseInterestActor $actor, array $links): array
    {
        $out = [];
        foreach ($links as $link) {
            $type = (string) $link['link_type'];
            $row = [
                'id' => (int) $link['id'], 'type' => $type, 'type_label' => Catalog::LINK_TYPES[$type] ?? $type,
                'note' => $link['note'] ?? null, 'created_at' => (string) $link['created_at'], 'href' => null, 'masked' => false, 'missing' => false,
                'label' => (string) $link['label'], 'hint' => '',
            ];
            if (in_array($type, Catalog::LINK_TYPES_WITH_TARGET, true)) {
                $d = $this->resolver->describe($actor, $type, (string) $link['target_id']);
                if ($d === null) {
                    $row['missing'] = true;
                    $row['label'] = 'Élément supprimé ou introuvable';
                } else {
                    $row['label'] = $d['label'];
                    $row['hint'] = $d['hint'];
                    $row['href'] = $d['href'];
                    $row['masked'] = $d['masked'];
                }
            }
            $out[] = $row;
        }

        return $out;
    }

    /** Note de la discussion (journal du dossier). */
    public function addNote(SseInterestActor $actor, int $id, string $body): void
    {
        $this->requireWrite($actor);
        $case = $this->find($actor, $id);
        $this->requireNotDeleted($case);
        $body = SseInterestMarkup::clean($body, Catalog::NOTE_MAX);
        if (mb_strlen($body) < 2) {
            throw SseInterestCaseException::validation(['body' => 'Écrivez la note avant de l’envoyer.']);
        }
        $this->cases->addUpdate($actor->tenantId, $id, $body, $actor->label, $actor->userId);
        $this->bump($actor, $id);
        $this->audit($actor, 'interest_case.note', $id, null, null, ['length' => mb_strlen($body)]);
        $this->notifyInterested($actor, $case, 'note', 'Nouvelle note dans la discussion du dossier.');
    }

    /** Trace d'un export PDF (historique et journal d'audit SSE). */
    public function recordExport(SseInterestActor $actor, int $id): void
    {
        $case = $this->find($actor, $id);
        $this->history->add($actor->tenantId, $id, ['action' => 'export', 'actor_user_id' => $actor->userId, 'actor_label' => $actor->label, 'version_after' => (int) $case['version']]);
        $this->audit($actor, 'interest_case.export', $id, null, null, ['reference' => $case['reference_code'], 'classification' => $case['classification']]);
    }

    /** Trace d'un changement de diffusion (destinataires, interdictions nominatives). */
    public function recordAclChange(SseInterestActor $actor, int $id): void
    {
        $case = $this->cases->findForTenant($id, $actor->tenantId);
        if ($case === null) {
            return;
        }
        $this->history->add($actor->tenantId, $id, ['action' => 'acl', 'actor_user_id' => $actor->userId, 'actor_label' => $actor->label, 'version_after' => (int) $case['version']]);
        $this->audit($actor, 'interest_case.acl', $id, null, null, ['reference' => $case['reference_code']]);
    }

    public function watch(SseInterestActor $actor, int $id, bool $on): bool
    {
        $this->requireRead($actor);
        $case = $this->find($actor, $id);
        if ($actor->userId === null) {
            throw SseInterestCaseException::rule('L’abonnement demande un compte membre.');
        }
        if (!$this->cases->capabilities()['watchers']) {
            throw SseInterestCaseException::degraded('L’abonnement aux dossiers');
        }

        return $on ? $this->links->watch($actor->tenantId, (int) $case['id'], $actor->userId) : $this->links->unwatch($actor->tenantId, (int) $case['id'], $actor->userId);
    }

    // ------------------------------------------------------------------ Historique

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public function presentHistory(int $tenantId, array $rows): array
    {
        $ids = [];
        foreach ($rows as $r) {
            if (($r['field_key'] ?? '') === 'assignee_user_id') {
                $ids[] = (int) $r['old_value'];
                $ids[] = (int) $r['new_value'];
            }
        }
        $labels = $this->cases->memberLabels($tenantId, $ids);
        $out = [];
        foreach ($rows as $r) {
            $field = (string) ($r['field_key'] ?? '');
            $out[] = [
                'id' => (int) $r['id'],
                'action' => (string) $r['action'],
                'action_label' => Catalog::ACTION_LABELS[(string) $r['action']] ?? (string) $r['action'],
                'field' => $field,
                'field_label' => Catalog::FIELD_LABELS[$field] ?? (Catalog::LINK_TYPES[$field] ?? ''),
                'old' => $this->displayValue($field, $r['old_value'] ?? null, $labels),
                'new' => $this->displayValue($field, $r['new_value'] ?? null, $labels),
                'reason' => $r['reason'] ?? null,
                'actor' => (string) (($r['actor_label'] ?? '') ?: 'Système'),
                'at' => (string) $r['created_at'],
                'version' => isset($r['version_after']) ? (int) $r['version_after'] : null,
            ];
        }

        return $out;
    }

    /** @param array<int, string> $memberLabels */
    private function displayValue(string $field, mixed $value, array $memberLabels): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $v = (string) $value;

        return match ($field) {
            'status' => Cases::STATUSES[$v] ?? $v,
            'interest_level' => Catalog::priorityCode($v) . ' · ' . Catalog::priorityLabel($v),
            'confidence_level' => Cases::CONFIDENCE[$v] ?? $v,
            'case_type' => $this->types[$v] ?? $v,
            'classification' => SseRedactionService::levelLabel($v),
            'assignee_user_id' => $memberLabels[(int) $v] ?? 'Membre',
            'due_at' => ($ts = strtotime($v)) !== false ? date('d/m/Y H:i', $ts) : $v,
            'description', 'opening_reason' => mb_strlen($v) > 160 ? mb_substr(SseInterestMarkup::plain($v), 0, 160) . '…' : SseInterestMarkup::plain($v),
            default => $v,
        };
    }

    // ------------------------------------------------------------------ Validation

    /**
     * @param array<string, mixed> $input
     * @param list<string>|null $only champs à valider (modification) ; null : tous (création)
     * @param array<string, mixed>|null $current
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    public function validate(SseInterestActor $actor, array $input, bool $creating, bool $draft = false, ?array $only = null, ?array $current = null): array
    {
        $only ??= self::EDITABLE;
        $columns = $this->cases->capabilities()['columns'];
        $data = [];
        $err = [];
        $text = static fn (string $k): string => trim((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string) ($input[$k] ?? '')));
        $has = static fn (string $k): bool => in_array($k, $only, true);

        if ($has('temporary_designation')) {
            $v = trim((string) preg_replace('/\s+/u', ' ', $text('temporary_designation')));
            if ($v === '' || mb_strlen($v) < 3) {
                $err['temporary_designation'] = 'Donnez un titre de 3 signes au moins.';
            } elseif (mb_strlen($v) > Catalog::TITLE_MAX) {
                $err['temporary_designation'] = 'Le titre ne doit pas dépasser ' . Catalog::TITLE_MAX . ' signes (' . mb_strlen($v) . ' saisis).';
            }
            $data['temporary_designation'] = $v;
        }
        if ($has('opening_reason')) {
            $v = SseInterestMarkup::clean((string) ($input['opening_reason'] ?? ''), Catalog::REASON_MAX + 1);
            if (mb_strlen($v) > Catalog::REASON_MAX) {
                $err['opening_reason'] = 'Le motif ne doit pas dépasser ' . Catalog::REASON_MAX . ' signes.';
            } elseif (!$draft && mb_strlen($v) < 10) {
                $err['opening_reason'] = 'Expliquez le motif d’ouverture en 10 signes au moins.';
            }
            $data['opening_reason'] = $v;
        }
        if ($has('description')) {
            $v = SseInterestMarkup::clean((string) ($input['description'] ?? ''), Catalog::DESCRIPTION_MAX + 1);
            if (mb_strlen($v) > Catalog::DESCRIPTION_MAX) {
                $err['description'] = 'La description ne doit pas dépasser ' . Catalog::DESCRIPTION_MAX . ' signes.';
            }
            $data['description'] = $v === '' ? null : $v;
        }
        foreach (['suspected_alias' => 'L’alias', 'suspected_affiliation' => 'L’affiliation'] as $k => $name) {
            if ($has($k)) {
                $v = $text($k);
                if (mb_strlen($v) > Catalog::ALIAS_MAX) {
                    $err[$k] = $name . ' ne doit pas dépasser ' . Catalog::ALIAS_MAX . ' signes.';
                }
                $data[$k] = $v === '' ? null : $v;
            }
        }
        if ($has('interest_level')) {
            $v = $text('interest_level');
            if ($v === '' && $creating) {
                $v = 'courant';
            }
            if (!isset(Cases::INTEREST[$v])) {
                $err['interest_level'] = 'Choisissez une priorité de P1 à P4.';
            }
            $data['interest_level'] = $v;
        }
        if ($has('confidence_level')) {
            $v = $text('confidence_level');
            if ($v === '' && $creating) {
                $v = 'non_evalue';
            }
            if (!isset(Cases::CONFIDENCE[$v])) {
                $err['confidence_level'] = 'Choisissez un niveau de confiance de la liste.';
            }
            $data['confidence_level'] = $v;
        }
        if ($columns) {
            if ($has('case_type')) {
                $v = $text('case_type');
                if ($v !== '' && !isset($this->types[$v])) {
                    $err['case_type'] = 'Ce type n’existe pas dans le catalogue de la communauté.';
                }
                $data['case_type'] = $v === '' ? null : $v;
            }
            if ($has('classification')) {
                $v = $text('classification');
                if ($v === '' && $creating) {
                    $v = 'interne';
                }
                if (!isset(SseRedactionService::LEVELS[$v])) {
                    $err['classification'] = 'Choisissez un niveau de classification de la liste.';
                } elseif (SseRedactionService::levelRank($v) > $actor->maxRank()) {
                    $err['classification'] = 'Vous ne pouvez pas classer un dossier au-dessus de votre habilitation (' . SseRedactionService::levelLabel($actor->maxLevel) . ').';
                }
                $data['classification'] = $v;
            }
            if ($has('assignee_user_id')) {
                $v = (int) ($input['assignee_user_id'] ?? 0);
                if ($v > 0 && $this->cases->memberLabels($actor->tenantId, [$v]) === []) {
                    $err['assignee_user_id'] = 'Ce responsable ne fait pas partie de la communauté.';
                }
                $data['assignee_user_id'] = $v > 0 ? $v : null;
            }
            if ($has('due_at')) {
                $raw = $text('due_at');
                $due = null;
                if ($raw !== '') {
                    $ts = preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1 ? strtotime($raw . ' 23:59:00') : strtotime(str_replace('T', ' ', $raw));
                    if ($ts === false || $ts < strtotime('2000-01-01') || $ts > strtotime($this->now()) + 5 * 366 * 86400) {
                        $err['due_at'] = 'Indiquez une échéance valable (date des cinq prochaines années).';
                    } else {
                        $due = date('Y-m-d H:i:s', $ts);
                        $previous = self::norm($current['due_at'] ?? null);
                        if ($creating || $previous !== $due) {
                            if ($ts < strtotime($this->now()) - 60) {
                                $err['due_at'] = 'L’échéance ne peut pas être dans le passé.';
                            }
                        }
                    }
                }
                $data['due_at'] = $due;
            }
            if ($has('tags')) {
                $data['tags'] = Catalog::joinTags(Catalog::normalizeTags($input['tags'] ?? ''));
            }
        }
        if ($creating) {
            foreach (self::LEGACY_TEXT as $k => $max) {
                $v = SseInterestMarkup::clean((string) ($input[$k] ?? ''), $max + 1);
                if (mb_strlen($v) > $max) {
                    $err[$k] = 'Ce champ ne doit pas dépasser ' . $max . ' signes.';
                }
                $data[$k] = $v === '' ? null : $v;
            }
            $acq = $text('acquisition_at');
            if ($acq !== '') {
                $ts = strtotime(str_replace('T', ' ', $acq));
                if ($ts === false) {
                    $err['acquisition_at'] = 'Indiquez une date d’acquisition valable.';
                } else {
                    $data['acquisition_at'] = date('Y-m-d H:i:s', $ts);
                }
            }
        }

        return [$data, $err];
    }

    // ------------------------------------------------------------------ Outils

    private function requireRead(SseInterestActor $actor): void
    {
        if (!$actor->canRead || $actor->tenantId < 1) {
            throw SseInterestCaseException::forbidden('Votre accès aux dossiers d’intérêt est restreint.');
        }
    }

    private function requireWrite(SseInterestActor $actor): void
    {
        $this->requireRead($actor);
        if (!$actor->canWrite) {
            throw SseInterestCaseException::forbidden('Votre habilitation ne permet pas de modifier les dossiers d’intérêt.');
        }
    }

    /** @param array<string, mixed> $case */
    private function requireNotDeleted(array $case): void
    {
        if (!empty($case['deleted_at'])) {
            throw SseInterestCaseException::rule('Ce dossier est à la corbeille : restaurez-le avant de le modifier.');
        }
    }

    /** Valeur comparable : null et chaîne vide sont équivalents, les fins de ligne sont normalisées. */
    private static function norm(mixed $v): string
    {
        if ($v === null) {
            return '';
        }
        $s = str_replace("\r\n", "\n", trim((string) $v));

        return $s;
    }

    private function conflictFor(SseInterestActor $actor, int $id): SseInterestCaseException
    {
        $case = $this->cases->findForTenant($id, $actor->tenantId);
        if ($case === null) {
            return SseInterestCaseException::notFound();
        }
        $last = $this->history->listForCase($actor->tenantId, $id, 1)[0] ?? null;
        $who = $last !== null && trim((string) ($last['actor_label'] ?? '')) !== '' ? (string) $last['actor_label'] : 'un autre opérateur';
        $when = $last !== null ? (string) $last['created_at'] : (string) ($case['updated_at'] ?? '');
        $hour = ($ts = strtotime($when)) !== false ? date('d/m/Y à H:i', $ts) : 'à l’instant';

        return SseInterestCaseException::conflict(
            'Ce dossier a été modifié par ' . $who . ' le ' . $hour . ' pendant que vous le modifiiez. Rien n’a été écrasé : rechargez la page pour voir sa version actuelle, puis refaites votre modification.'
        );
    }

    /** Un lien ou une note fait vivre le dossier (dernière activité) sans changer sa version : une édition en cours n'entre pas en conflit. */
    private function bump(SseInterestActor $actor, int $id): void
    {
        if ($this->cases->capabilities()['columns']) {
            $this->cases->touch($id, $actor->tenantId);
        }
    }

    private function record(SseInterestActor $actor, int $id, bool $historyReady, string $action, string $field, string $old, string $new, string $reason, int $version): void
    {
        if ($historyReady) {
            $this->history->add($actor->tenantId, $id, ['action' => $action, 'field_key' => $field, 'old_value' => $old, 'new_value' => $new, 'reason' => $reason !== '' ? $reason : null, 'actor_user_id' => $actor->userId, 'actor_label' => $actor->label, 'version_after' => $version]);

            return;
        }
        // Mode dégradé : sans table d'historique, le changement reste lisible dans le journal du dossier.
        $this->cases->addUpdate($actor->tenantId, $id, 'État passé de « ' . (Cases::STATUSES[$old] ?? $old) . ' » à « ' . (Cases::STATUSES[$new] ?? $new) . ' ».' . ($reason !== '' ? ' Motif : ' . $reason : ''), $actor->label, $actor->userId);
    }

    private function audit(SseInterestActor $actor, string $action, int $id, ?string $reason, mixed $before, mixed $after): void
    {
        $this->writeAudit($actor->tenantId, $actor->userId, $actor->label, $action, $id, $reason, $before, $after);
    }

    private function writeAudit(int $tenantId, ?int $userId, string $label, string $action, int $id, ?string $reason, mixed $before, mixed $after): void
    {
        $row = ['tenant_id' => $tenantId, 'actor_user_id' => $userId, 'actor_label' => $label, 'action' => $action, 'object_type' => 'interest_case', 'object_id' => $id, 'reason' => $reason, 'before' => $before, 'after' => $after];
        try {
            if ($this->audit !== null) {
                ($this->audit)($row);
            } else {
                (new \App\Repositories\SseAuditLogRepository())->write($row);
            }
        } catch (\Throwable) {
            // le journal d'audit ne doit jamais empêcher l'action
        }
    }

    /** @param array<string, mixed> $case */
    private function recipientMaySee(array $case, int $userId): bool
    {
        return $this->cases->userCanAccessCase($case, $userId, false);
    }

    /**
     * Prévient le responsable et les abonnés (jamais l'auteur), une seule fois chacun, sans titre ni contenu du dossier : seul
     * ce que la référence dit. $extra : personnes déjà prévenues par un autre message (pas de doublon).
     *
     * @param array<string, mixed> $case
     * @param list<int> $skip
     */
    private function notifyInterested(SseInterestActor $actor, array $case, string $kind, string $detail, array $skip = []): void
    {
        $t = $actor->tenantId;
        $ids = $this->links->watchers($t, (int) $case['id']);
        if (!empty($case['assignee_user_id'])) {
            $ids[] = (int) $case['assignee_user_id'];
        }
        $this->notifyUsers($actor, $case, array_values(array_diff($ids, $skip)), $kind, $detail);
    }

    /**
     * @param array<string, mixed> $case
     * @param list<int> $userIds
     */
    private function notifyUsers(SseInterestActor $actor, array $case, array $userIds, string $kind, string $detail): void
    {
        foreach (array_unique(array_filter(array_map('intval', $userIds), static fn (int $u): bool => $u > 0 && $u !== (int) $actor->userId)) as $uid) {
            if (!$this->recipientMaySee($case, $uid)) {
                continue;
            }
            $payload = ['ref' => (string) ($case['reference_code'] ?? ''), 'path' => 'atak/sse/interet/' . (int) $case['id'], 'kind' => $kind, 'by' => $actor->label, 'detail' => $detail];
            try {
                if ($this->notify !== null) {
                    ($this->notify)($actor->tenantId, $uid, $payload);
                } else {
                    (new \App\Repositories\MemberNotificationRepository())->create($actor->tenantId, $uid, 'sse_interest_case', $payload);
                }
            } catch (\Throwable) {
                // la notification n'est jamais bloquante
            }
        }
    }
}
