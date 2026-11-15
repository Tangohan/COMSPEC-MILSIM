<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SilentSchemaMigration;

final class SseInterestCaseRepository
{
    public const STATUSES = [
        'brouillon' => 'Brouillon', 'signalement_recu' => 'Signalement reçu',
        'a_qualifier' => 'À qualifier', 'en_attente' => 'En attente', 'en_collecte' => 'En collecte',
        'en_analyse' => 'En analyse', 'rapprochements_detectes' => 'Rapprochements détectés',
        'en_validation' => 'En validation', 'correspondance_probable' => 'Correspondance probable',
        'identite_consolidee' => 'Identité consolidée', 'identite_infirme' => 'Identité infirmée',
        'sans_suite' => 'Sans suite', 'archive' => 'Archivé',
    ];
    public const CONFIDENCE = ['non_evalue' => 'Non évalué', 'tres_faible' => 'Très faible', 'faible' => 'Faible', 'modere' => 'Modéré', 'eleve' => 'Élevé', 'tres_eleve' => 'Très élevé', 'confirme' => 'Confirmé'];
    public const INTEREST = ['courant' => 'Courant', 'a_surveiller' => 'À surveiller', 'prioritaire' => 'Prioritaire', 'critique' => 'Critique'];

    public const ACL_DESTINATAIRE = 'destinataire';
    public const ACL_INTERDIT = 'interdit';

    /** @var array<string, array{seconds: int, label: string}> */
    public const COOLDOWNS = [
        'status' => ['seconds' => 300, 'label' => 'Changer l’état du dossier'],
        'constitute' => ['seconds' => 600, 'label' => 'Constituer le dossier'],
        'cross_decide' => ['seconds' => 120, 'label' => 'Trancher un rapprochement'],
        'open_mesh' => ['seconds' => 300, 'label' => 'Ouvrir une investigation'],
        'publish' => ['seconds' => 600, 'label' => 'Soumettre à validation'],
    ];

    /** Colonnes ajoutées par l'étape sse_interest_case_suite (database/steps/4750_sse_interest_case_suite.php). */
    public const EXTENDED_COLUMNS = ['version', 'classification', 'case_type', 'assignee_user_id', 'due_at', 'tags', 'archive_from_status', 'deleted_at', 'deleted_by', 'delete_reason'];

    /** Colonnes qu'une mise à jour peut poser (liste blanche : jamais un nom de colonne venu de la saisie). */
    private const WRITABLE = [
        'temporary_designation', 'suspected_alias', 'suspected_affiliation', 'apparent_sex', 'estimated_age_range', 'suspected_nationality',
        'status', 'confidence_level', 'interest_level', 'opening_reason', 'description', 'archived_at', 'validated_by', 'validated_at',
        'classification', 'case_type', 'assignee_user_id', 'due_at', 'tags', 'archive_from_status', 'deleted_at', 'deleted_by', 'delete_reason',
    ];

    /** @var array{columns: bool, history: bool, links: bool, watchers: bool, acl: bool}|null */
    private ?array $caps = null;

    public function __construct(private ?Database $db = null)
    {
        $this->db ??= Database::getInstance();
        SilentSchemaMigration::runMany([
            base_path('bootstrap/atak_sse_interest_cases_migration.php'),
            base_path('bootstrap/atak_sse_interest_case_enrichment_migration.php'),
        ]);
    }

    /**
     * Ce que la base sait faire. Sans l'étape sse_interest_case_suite, le module reste utilisable (liste, création, fiche,
     * changement d'état) mais sans version, responsable, échéance, classification, corbeille, historique ni éléments liés.
     *
     * @return array{columns: bool, history: bool, links: bool, watchers: bool, acl: bool}
     */
    public function capabilities(): array
    {
        if ($this->caps !== null) {
            return $this->caps;
        }
        $probe = function (string $sql): bool {
            try {
                $this->db->fetchAll($sql);

                return true;
            } catch (\Throwable) {
                return false;
            }
        };

        return $this->caps = [
            'columns' => $probe('SELECT ' . implode(', ', self::EXTENDED_COLUMNS) . ' FROM sse_interest_cases WHERE 1 = 0'),
            'history' => $probe('SELECT id, old_value, new_value FROM sse_interest_case_history WHERE 1 = 0'),
            'links' => $probe('SELECT id, label FROM sse_interest_case_links WHERE 1 = 0'),
            'watchers' => $probe('SELECT id FROM sse_interest_case_watchers WHERE 1 = 0'),
            'acl' => $probe('SELECT id FROM sse_interest_case_acl WHERE 1 = 0'),
        ];
    }

    public function isFullySupported(): bool
    {
        $caps = $this->capabilities();

        return $caps['columns'] && $caps['history'] && $caps['links'] && $caps['watchers'];
    }

    /** @return list<array<string,mixed>> */
    public function listForTenant(int $tenantId, array $filters = []): array
    {
        $where = ['tenant_id = :tenant'];
        $params = ['tenant' => $tenantId];
        if ($this->capabilities()['columns']) {
            $where[] = 'deleted_at IS NULL';
        }
        if (isset(self::STATUSES[(string) ($filters['status'] ?? '')])) {
            $where[] = 'status = :status'; $params['status'] = $filters['status'];
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . $q . '%';
            $where[] = '(reference_code LIKE :q_ref OR temporary_designation LIKE :q_desig OR suspected_alias LIKE :q_alias)';
            $params['q_ref'] = $like;
            $params['q_desig'] = $like;
            $params['q_alias'] = $like;
        }
        $rows = $this->db->fetchAll('SELECT * FROM sse_interest_cases WHERE ' . implode(' AND ', $where) . ' ORDER BY updated_at DESC LIMIT 200', $params);
        return array_map(fn (array $r): array => $this->hydrate($r), $rows);
    }

    /**
     * Page de la file « ticketing » : onglet, recherche plein texte, filtres, tri, pagination, compteurs par onglet.
     * Le lecteur (accès nominatifs et plafond d'habilitation) filtre dans la requête : une page n'est jamais « trouée ».
     *
     * @param array{tab?: string, q?: string, priority?: string, type?: string, assignee?: int, classification?: string, overdue?: bool, status?: string} $filters
     * @param array{user_id?: ?int, bypass_acl?: bool, max_rank?: ?int} $viewer
     * @return array{rows: list<array<string,mixed>>, total: int, page: int, pages: int, per_page: int, counts: array<string,int>}
     */
    public function queryPage(int $tenantId, array $filters, array $viewer, string $sort = 'recent', int $page = 1, int $perPage = 25): array
    {
        $perPage = max(5, min(100, $perPage));
        $caps = $this->capabilities();
        $tab = (string) ($filters['tab'] ?? '');

        $params = [];
        $base = $this->whereClause($tenantId, $filters, $viewer, $params);
        $where = $base;
        if ($caps['columns'] && $tab === 'corbeille') {
            $where[] = 'c.deleted_at IS NOT NULL';
        } else {
            if ($caps['columns']) {
                $where[] = 'c.deleted_at IS NULL';
            }
            $statuses = \App\Support\SseInterestCaseCatalog::statusesOf($tab);
            if ($statuses !== []) {
                $marks = [];
                foreach ($statuses as $i => $st) {
                    $marks[] = ':tab' . $i;
                    $params['tab' . $i] = $st;
                }
                $where[] = 'c.status IN (' . implode(',', $marks) . ')';
            }
        }

        $total = (int) ($this->db->fetchOne('SELECT COUNT(*) AS n FROM sse_interest_cases c WHERE ' . implode(' AND ', $where), $params)['n'] ?? 0);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($pages, $page));
        $rows = $this->db->fetchAll(
            'SELECT c.* FROM sse_interest_cases c WHERE ' . implode(' AND ', $where)
            . ' ORDER BY ' . $this->orderBy($sort, $caps['columns']) . ' LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage),
            $params
        );

        // Compteurs : mêmes filtres que la file, sans l'onglet.
        $countParams = [];
        $countWhere = $this->whereClause($tenantId, $filters, $viewer, $countParams);
        if ($caps['columns']) {
            $countWhere[] = 'c.deleted_at IS NULL';
        }
        $byStatus = [];
        foreach ($this->db->fetchAll('SELECT c.status AS st, COUNT(*) AS n FROM sse_interest_cases c WHERE ' . implode(' AND ', $countWhere) . ' GROUP BY c.status', $countParams) as $row) {
            $byStatus[(string) $row['st']] = (int) $row['n'];
        }
        $counts = [];
        foreach (\App\Support\SseInterestCaseCatalog::TABS as $key => $meta) {
            $counts[$key] = array_sum(array_map(static fn (string $s): int => $byStatus[$s] ?? 0, $meta['statuses']));
        }
        $counts['corbeille'] = 0;
        if ($caps['columns']) {
            $trashParams = [];
            $trashWhere = $this->whereClause($tenantId, $filters, $viewer, $trashParams);
            $trashWhere[] = 'c.deleted_at IS NOT NULL';
            $counts['corbeille'] = (int) ($this->db->fetchOne('SELECT COUNT(*) AS n FROM sse_interest_cases c WHERE ' . implode(' AND ', $trashWhere), $trashParams)['n'] ?? 0);
        }

        return [
            'rows' => array_map(fn (array $r): array => $this->hydrate($r), $rows),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'per_page' => $perPage,
            'counts' => $counts,
        ];
    }

    /**
     * Conditions communes (communauté, recherche, filtres, lecteur) ; le tenant vient toujours de l'appelant, jamais de la requête.
     *
     * @param array<string,mixed> $params
     * @return list<string>
     */
    private function whereClause(int $tenantId, array $filters, array $viewer, array &$params): array
    {
        $caps = $this->capabilities();
        $where = ['c.tenant_id = :tenant'];
        $params['tenant'] = $tenantId;

        $status = (string) ($filters['status'] ?? '');
        if ($status !== '' && isset(self::STATUSES[$status])) {
            $where[] = 'c.status = :f_status';
            $params['f_status'] = $status;
        }
        $priority = (string) ($filters['priority'] ?? '');
        if (isset(self::INTEREST[$priority])) {
            $where[] = 'c.interest_level = :f_priority';
            $params['f_priority'] = $priority;
        }
        if ($caps['columns']) {
            $type = (string) ($filters['type'] ?? '');
            if ($type !== '') {
                $where[] = 'c.case_type = :f_type';
                $params['f_type'] = $type;
            }
            $assignee = (int) ($filters['assignee'] ?? 0);
            if ($assignee > 0) {
                $where[] = 'c.assignee_user_id = :f_assignee';
                $params['f_assignee'] = $assignee;
            } elseif ($assignee === -1) {
                $where[] = 'c.assignee_user_id IS NULL';
            }
            $class = (string) ($filters['classification'] ?? '');
            if ($class !== '' && isset(\App\Services\Sse\SseRedactionService::LEVELS[$class])) {
                $where[] = 'c.classification = :f_class';
                $params['f_class'] = $class;
            }
            if (!empty($filters['overdue'])) {
                $done = array_merge(\App\Support\SseInterestCaseCatalog::statusesOf('clos'), \App\Support\SseInterestCaseCatalog::statusesOf('archives'));
                $marks = [];
                foreach ($done as $i => $st) {
                    $marks[] = ':od' . $i;
                    $params['od' . $i] = $st;
                }
                $where[] = 'c.due_at IS NOT NULL AND c.due_at < :f_now AND c.status NOT IN (' . implode(',', $marks) . ')';
                $params['f_now'] = date('Y-m-d H:i:s');
            }
            $maxRank = $viewer['max_rank'] ?? null;
            if ($maxRank !== null) {
                $allowed = array_keys(array_filter(\App\Services\Sse\SseRedactionService::LEVELS, static fn (int $rank): bool => $rank <= (int) $maxRank));
                $marks = [];
                foreach ($allowed as $i => $lv) {
                    $marks[] = ':lv' . $i;
                    $params['lv' . $i] = $lv;
                }
                $where[] = '(c.classification IS NULL OR c.classification IN (' . implode(',', $marks ?: ["'interne'"]) . '))';
            }
        }

        $words = preg_split('/\s+/u', trim((string) ($filters['q'] ?? ''))) ?: [];
        $fields = ['c.reference_code', 'c.temporary_designation', 'c.suspected_alias', 'c.suspected_affiliation', 'c.opening_reason', 'c.description'];
        if ($caps['columns']) {
            $fields[] = 'c.tags';
        }
        foreach (array_slice(array_values(array_filter($words, static fn (string $w): bool => $w !== '')), 0, 6) as $i => $word) {
            $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_substr($word, 0, 60)) . '%';
            $ors = [];
            foreach ($fields as $j => $field) {
                $ors[] = $field . ' LIKE :q' . $i . '_' . $j . " ESCAPE '!'";
                $params['q' . $i . '_' . $j] = $like;
            }
            $where[] = '(' . implode(' OR ', $ors) . ')';
        }

        if (empty($viewer['bypass_acl']) && $caps['acl']) {
            $uid = (int) ($viewer['user_id'] ?? 0);
            $where[] = "NOT EXISTS (SELECT 1 FROM sse_interest_case_acl x WHERE x.interest_case_id = c.id AND x.user_id = :acl_u1 AND x.access_mode = 'interdit')"
                . " AND (c.created_by = :acl_u2 OR NOT EXISTS (SELECT 1 FROM sse_interest_case_acl y WHERE y.interest_case_id = c.id AND y.access_mode = 'destinataire')"
                . " OR EXISTS (SELECT 1 FROM sse_interest_case_acl z WHERE z.interest_case_id = c.id AND z.user_id = :acl_u3 AND z.access_mode = 'destinataire'))";
            $params['acl_u1'] = $uid;
            $params['acl_u2'] = $uid;
            $params['acl_u3'] = $uid;
        }

        return $where;
    }

    private function orderBy(string $sort, bool $columns): string
    {
        return match ($sort) {
            'priorite' => "CASE c.interest_level WHEN 'critique' THEN 1 WHEN 'prioritaire' THEN 2 WHEN 'a_surveiller' THEN 3 ELSE 4 END ASC, c.updated_at DESC, c.id DESC",
            'echeance' => $columns ? 'CASE WHEN c.due_at IS NULL THEN 1 ELSE 0 END ASC, c.due_at ASC, c.id DESC' : 'c.updated_at DESC, c.id DESC',
            'reference' => 'c.reference_code ASC',
            'titre' => 'c.temporary_designation ASC, c.id ASC',
            default => 'c.updated_at DESC, c.id DESC',
        };
    }

    /**
     * Dossiers que la session courante peut lire (habilitation, accès nominatifs, hors corbeille) : pour la recherche globale, JNET et
     * les tableaux de bord, qui ne doivent jamais lister un dossier que la file n'afficherait pas.
     *
     * @return list<array<string,mixed>>
     */
    public function listForSession(int $tenantId, string $q = '', int $limit = 100): array
    {
        $actor = \App\Services\Sse\SseInterestActor::fromSession();
        if (!$actor->canRead || $actor->tenantId !== $tenantId) {
            return [];
        }

        return $this->queryPage($tenantId, ['tab' => '', 'q' => $q], $actor->viewer(), 'recent', 1, $limit)['rows'];
    }

    /**
     * File filtrée selon destinataires / interdits nominatifs.
     *
     * @return list<array<string,mixed>>
     */
    public function listVisibleForUser(int $tenantId, ?int $userId, bool $canBypassAcl, array $filters = []): array
    {
        $rows = $this->listForTenant($tenantId, $filters);
        if ($canBypassAcl) {
            return $rows;
        }

        return array_values(array_filter(
            $rows,
            fn (array $case): bool => $this->userCanAccessCase($case, $userId, false)
        ));
    }

    public function findForTenant(int $id, int $tenantId): ?array
    {
        $row = $this->db->fetchOne('SELECT * FROM sse_interest_cases WHERE id = :id AND tenant_id = :tenant LIMIT 1', ['id' => $id, 'tenant' => $tenantId]);
        return $row ? $this->hydrate($row) : null;
    }

    /**
     * Ouvre un dossier. La référence DI-AAAA-NNNNNN dérive de l'identifiant inséré (jamais d'un MAX() : deux ouvertures
     * simultanées ne peuvent pas se voir attribuer la même).
     */
    public function create(int $tenantId, array $data): int
    {
        $fields = ['tenant_id','reference_code','temporary_designation','suspected_alias','apparent_sex','estimated_age_range','suspected_nationality','suspected_affiliation','status','confidence_level','interest_level','opening_reason','description','origin_operator','observed_elements','analysis_facts','analysis_assumptions','analysis_contradictions','analysis_questions','collection_needs','operational_risk','recommendations','source_label','source_reliability','signed_by_label','signed_at','acquisition_at','mission_label','created_by'];
        if ($this->capabilities()['columns']) {
            array_push($fields, 'version', 'classification', 'case_type', 'assignee_user_id', 'due_at', 'tags');
        }
        $status = (string) ($data['status'] ?? 'signalement_recu');
        $values = ['tenant_id' => $tenantId, 'reference_code' => 'DI-tmp-' . bin2hex(random_bytes(8)), 'status' => isset(self::STATUSES[$status]) ? $status : 'signalement_recu', 'version' => 1];
        foreach ($fields as $field) {
            if (!array_key_exists($field, $values)) {
                $values[$field] = $data[$field] ?? null;
            }
        }
        $values['confidence_level'] = isset(self::CONFIDENCE[(string) $values['confidence_level']]) ? $values['confidence_level'] : 'non_evalue';
        $values['interest_level'] = isset(self::INTEREST[(string) $values['interest_level']]) ? $values['interest_level'] : 'courant';
        if (isset($values['classification']) && $values['classification'] === null) {
            $values['classification'] = 'interne';
        }
        foreach (['acquisition_at', 'signed_at', 'due_at'] as $dt) {
            if (!empty($values[$dt])) {
                $values[$dt] = str_replace('T', ' ', (string) $values[$dt]);
            }
        }
        $values = array_intersect_key($values, array_flip($fields));
        $id = (int) $this->db->insert('INSERT INTO sse_interest_cases (' . implode(',', $fields) . ') VALUES (:' . implode(',:', $fields) . ')', $values);
        if ($id > 0) {
            $this->db->execute(
                'UPDATE sse_interest_cases SET reference_code = :ref WHERE id = :id AND tenant_id = :t',
                ['ref' => sprintf('DI-%s-%06d', date('Y'), $id), 'id' => $id, 't' => $tenantId]
            );
        }

        return $id;
    }

    public function updateDescription(int $id, int $tenantId, ?string $description): bool
    {
        try {
            return $this->db->execute(
                'UPDATE sse_interest_cases SET description = :d WHERE id = :id AND tenant_id = :t',
                ['d' => $description !== null && trim($description) !== '' ? trim($description) : null, 'id' => $id, 't' => $tenantId]
            ) >= 0;
        } catch (\Throwable) {
            return false;
        }
    }

    public function updateStatus(int $id, int $tenantId, string $status): bool
    {
        if (!isset(self::STATUSES[$status])) {
            return false;
        }
        try {
            return $this->db->execute(
                'UPDATE sse_interest_cases SET status = :s WHERE id = :id AND tenant_id = :t',
                ['s' => $status, 'id' => $id, 't' => $tenantId]
            ) > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Mise à jour avec verrou optimiste : n'écrit que si le dossier est encore à la version (ou à l'horodatage, sans colonne
     * version) que l'auteur a lue. Retourne le nombre de lignes modifiées : 0 = quelqu'un est passé entre-temps.
     *
     * @param array<string, mixed> $set colonne => valeur (liste blanche WRITABLE)
     */
    public function updateGuarded(int $id, int $tenantId, array $set, ?int $expectedVersion, ?string $expectedUpdatedAt = null): int
    {
        $cols = [];
        $params = ['id' => $id, 't' => $tenantId, 'now' => date('Y-m-d H:i:s')];
        foreach ($set as $col => $value) {
            if (!in_array($col, self::WRITABLE, true)) {
                throw new \InvalidArgumentException('Colonne non modifiable : ' . $col);
            }
            $cols[] = $col . ' = :s_' . $col;
            $params['s_' . $col] = $value;
        }
        $cols[] = 'updated_at = :now';
        $where = 'id = :id AND tenant_id = :t';
        if ($this->capabilities()['columns']) {
            $cols[] = 'version = COALESCE(version, 1) + 1';
            if ($expectedVersion !== null) {
                $where .= ' AND COALESCE(version, 1) = (:ev + 0)';
                $params['ev'] = $expectedVersion;
            }
        } elseif ($expectedUpdatedAt !== null && $expectedUpdatedAt !== '') {
            $where .= ' AND updated_at = :eu';
            $params['eu'] = $expectedUpdatedAt;
        }

        return $this->db->execute('UPDATE sse_interest_cases SET ' . implode(', ', $cols) . ' WHERE ' . $where, $params);
    }

    /** Dernière activité : avance updated_at sans toucher à la version. */
    public function touch(int $id, int $tenantId): void
    {
        $this->db->execute('UPDATE sse_interest_cases SET updated_at = :now WHERE id = :id AND tenant_id = :t', ['now' => date('Y-m-d H:i:s'), 'id' => $id, 't' => $tenantId]);
    }

    /** Corbeille : dossiers supprimés (restaurables jusqu'au délai). @return list<array<string,mixed>> */
    public function listTrashed(int $tenantId, array $viewer, int $limit = 200): array
    {
        return $this->queryPage($tenantId, ['tab' => 'corbeille'], $viewer, 'recent', 1, min(100, $limit))['rows'];
    }

    /** @return list<int> identifiants dont la mise à la corbeille dépasse le délai */
    public function expiredTrashIds(int $tenantId, string $cutoff): array
    {
        if (!$this->capabilities()['columns']) {
            return [];
        }
        $rows = $this->db->fetchAll('SELECT id FROM sse_interest_cases WHERE tenant_id = :t AND deleted_at IS NOT NULL AND deleted_at < :c', ['t' => $tenantId, 'c' => $cutoff]);

        return array_map(static fn (array $r): int => (int) $r['id'], $rows);
    }

    /** Suppression définitive : le dossier et tout ce qui s'y rattache. La trace reste au journal d'audit SSE. */
    public function purge(int $id, int $tenantId): bool
    {
        foreach (['sse_interest_case_history', 'sse_interest_case_links', 'sse_interest_case_watchers', 'sse_interest_case_updates', 'sse_interest_case_acl', 'sse_interest_case_cooldowns'] as $table) {
            try {
                $this->db->execute('DELETE FROM ' . $table . ' WHERE interest_case_id = :c AND tenant_id = :t', ['c' => $id, 't' => $tenantId]);
            } catch (\Throwable) {
                // table absente : rien à retirer
            }
        }

        return $this->db->execute('DELETE FROM sse_interest_cases WHERE id = :id AND tenant_id = :t', ['id' => $id, 't' => $tenantId]) > 0;
    }

    /**
     * Noms des membres de la communauté pour une liste d'identifiants (responsables, auteurs).
     *
     * @param list<int> $ids
     * @return array<int, string>
     */
    public function memberLabels(int $tenantId, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $i): bool => $i > 0)));
        if ($ids === []) {
            return [];
        }
        $params = ['t' => $tenantId];
        $marks = [];
        foreach ($ids as $i => $id) {
            $marks[] = ':u' . $i;
            $params['u' . $i] = $id;
        }
        try {
            $rows = $this->db->fetchAll('SELECT id, display_name, callsign FROM users WHERE tenant_id = :t AND id IN (' . implode(',', $marks) . ')', $params);
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row['display_name'] ?? ''));
            $out[(int) $row['id']] = $name !== '' ? $name : (trim((string) ($row['callsign'] ?? '')) ?: 'Membre');
        }

        return $out;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listUpdates(int $tenantId, int $interestCaseId, int $limit = 40): array
    {
        try {
            return $this->db->fetchAll(
                'SELECT * FROM sse_interest_case_updates
                 WHERE tenant_id = :t AND interest_case_id = :c
                 ORDER BY created_at DESC, id DESC
                 LIMIT ' . max(1, min(100, $limit)),
                ['t' => $tenantId, 'c' => $interestCaseId]
            );
        } catch (\Throwable) {
            return [];
        }
    }

    public function addUpdate(int $tenantId, int $interestCaseId, string $body, ?string $authorLabel, ?int $authorUserId): int
    {
        $body = trim($body);
        if ($body === '') {
            return 0;
        }
        try {
            return (int) $this->db->insert(
                'INSERT INTO sse_interest_case_updates
                    (tenant_id, interest_case_id, body, author_label, author_user_id)
                 VALUES (:t, :c, :b, :a, :u)',
                [
                    't' => $tenantId,
                    'c' => $interestCaseId,
                    'b' => mb_substr($body, 0, 4000),
                    'a' => $authorLabel !== null && trim($authorLabel) !== '' ? trim($authorLabel) : null,
                    'u' => ($authorUserId !== null && $authorUserId > 0) ? $authorUserId : null,
                ]
            );
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * @return array{destinataires: list<array<string,mixed>>, interdits: list<array<string,mixed>>}
     */
    public function listAcl(int $tenantId, int $interestCaseId): array
    {
        $empty = ['destinataires' => [], 'interdits' => []];
        try {
            $rows = $this->db->fetchAll(
                'SELECT a.*, u.display_name, u.callsign
                 FROM sse_interest_case_acl a
                 INNER JOIN users u ON u.id = a.user_id AND u.tenant_id = a.tenant_id
                 WHERE a.tenant_id = :t AND a.interest_case_id = :c
                 ORDER BY u.display_name ASC, u.callsign ASC',
                ['t' => $tenantId, 'c' => $interestCaseId]
            );
        } catch (\Throwable) {
            return $empty;
        }

        $out = $empty;
        foreach ($rows as $row) {
            $mode = (string) ($row['access_mode'] ?? '');
            $label = trim((string) ($row['display_name'] ?? ''));
            if ($label === '') {
                $label = trim((string) ($row['callsign'] ?? '')) ?: 'Membre';
            } elseif (!empty($row['callsign'])) {
                $label .= ' (' . (string) $row['callsign'] . ')';
            }
            $row['member_label'] = $label;
            if ($mode === self::ACL_DESTINATAIRE) {
                $out['destinataires'][] = $row;
            } elseif ($mode === self::ACL_INTERDIT) {
                $out['interdits'][] = $row;
            }
        }

        return $out;
    }

    /**
     * Remplace les listes destinataires / interdits (cases à cocher).
     *
     * @param list<int> $destinataireIds
     * @param list<int> $interditIds
     */
    public function replaceAcl(int $tenantId, int $interestCaseId, array $destinataireIds, array $interditIds, ?int $actorUserId): bool
    {
        $destinataireIds = $this->normalizeUserIds($destinataireIds);
        $interditIds = $this->normalizeUserIds($interditIds);
        // Un même membre ne peut pas être à la fois destinataire et interdit.
        $interditIds = array_values(array_diff($interditIds, $destinataireIds));

        try {
            $this->db->execute(
                'DELETE FROM sse_interest_case_acl WHERE tenant_id = :t AND interest_case_id = :c',
                ['t' => $tenantId, 'c' => $interestCaseId]
            );
            foreach ($destinataireIds as $uid) {
                $this->db->insert(
                    'INSERT INTO sse_interest_case_acl (tenant_id, interest_case_id, user_id, access_mode, created_by)
                     VALUES (:t, :c, :u, :m, :b)',
                    ['t' => $tenantId, 'c' => $interestCaseId, 'u' => $uid, 'm' => self::ACL_DESTINATAIRE, 'b' => $actorUserId]
                );
            }
            foreach ($interditIds as $uid) {
                $this->db->insert(
                    'INSERT INTO sse_interest_case_acl (tenant_id, interest_case_id, user_id, access_mode, created_by)
                     VALUES (:t, :c, :u, :m, :b)',
                    ['t' => $tenantId, 'c' => $interestCaseId, 'u' => $uid, 'm' => self::ACL_INTERDIT, 'b' => $actorUserId]
                );
            }

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Accès lecture : interdit nominatif bloque ; destinataires (s’il y en a) restreignent.
     * Le créateur et les opérateurs qui contournent l’ACL (habilitation forte) restent admis.
     */
    public function userCanAccessCase(array $case, ?int $userId, bool $canBypassAcl): bool
    {
        if ($canBypassAcl) {
            return true;
        }

        $caseId = (int) ($case['id'] ?? 0);
        $tenantId = (int) ($case['tenant_id'] ?? 0);
        if ($caseId < 1 || $tenantId < 1) {
            return false;
        }

        $uid = $userId !== null && $userId > 0 ? $userId : 0;

        try {
            if ($uid > 0) {
                $denied = $this->db->fetchOne(
                    'SELECT 1 FROM sse_interest_case_acl
                     WHERE tenant_id = :t AND interest_case_id = :c AND user_id = :u AND access_mode = :m LIMIT 1',
                    ['t' => $tenantId, 'c' => $caseId, 'u' => $uid, 'm' => self::ACL_INTERDIT]
                );
                if ($denied !== null) {
                    return false;
                }
            }

            $createdBy = (int) ($case['created_by'] ?? 0);
            if ($uid > 0 && $createdBy > 0 && $uid === $createdBy) {
                return true;
            }

            $destCount = $this->db->fetchOne(
                'SELECT COUNT(*) AS n FROM sse_interest_case_acl
                 WHERE tenant_id = :t AND interest_case_id = :c AND access_mode = :m',
                ['t' => $tenantId, 'c' => $caseId, 'm' => self::ACL_DESTINATAIRE]
            );
            $n = (int) ($destCount['n'] ?? 0);
            if ($n < 1) {
                return true;
            }
            if ($uid < 1) {
                return false;
            }
            $allowed = $this->db->fetchOne(
                'SELECT 1 FROM sse_interest_case_acl
                 WHERE tenant_id = :t AND interest_case_id = :c AND user_id = :u AND access_mode = :m LIMIT 1',
                ['t' => $tenantId, 'c' => $caseId, 'u' => $uid, 'm' => self::ACL_DESTINATAIRE]
            );

            return $allowed !== null;
        } catch (\Throwable) {
            // Tables absentes : comportement historique (ouvert à la cellule SSE).
            return true;
        }
    }

    /**
     * @return array{blocked: bool, remaining_seconds: int, label: string, ready_at: ?string}
     */
    public function cooldownState(int $tenantId, int $interestCaseId, string $actionKey): array
    {
        $meta = self::COOLDOWNS[$actionKey] ?? null;
        $label = $meta['label'] ?? 'Cette action';
        $seconds = (int) ($meta['seconds'] ?? 0);
        $empty = ['blocked' => false, 'remaining_seconds' => 0, 'label' => $label, 'ready_at' => null];
        if ($seconds < 1 || $meta === null) {
            return $empty;
        }

        try {
            $row = $this->db->fetchOne(
                'SELECT last_at FROM sse_interest_case_cooldowns
                 WHERE tenant_id = :t AND interest_case_id = :c AND action_key = :a LIMIT 1',
                ['t' => $tenantId, 'c' => $interestCaseId, 'a' => $actionKey]
            );
        } catch (\Throwable) {
            return $empty;
        }

        if ($row === null || empty($row['last_at'])) {
            return $empty;
        }

        $last = strtotime((string) $row['last_at']);
        if ($last === false) {
            return $empty;
        }
        $ready = $last + $seconds;
        $remaining = $ready - time();
        if ($remaining <= 0) {
            return $empty;
        }

        return [
            'blocked' => true,
            'remaining_seconds' => $remaining,
            'label' => $label,
            'ready_at' => date('Y-m-d H:i:s', $ready),
        ];
    }

    public function touchCooldown(int $tenantId, int $interestCaseId, string $actionKey, ?int $userId): void
    {
        if (!isset(self::COOLDOWNS[$actionKey])) {
            return;
        }
        try {
            $this->db->execute(
                'INSERT INTO sse_interest_case_cooldowns (tenant_id, interest_case_id, action_key, last_at, last_by)
                 VALUES (:t, :c, :a, NOW(), :u)
                 ON DUPLICATE KEY UPDATE last_at = VALUES(last_at), last_by = VALUES(last_by)',
                [
                    't' => $tenantId,
                    'c' => $interestCaseId,
                    'a' => $actionKey,
                    'u' => ($userId !== null && $userId > 0) ? $userId : null,
                ]
            );
        } catch (\Throwable) {
            // ignore
        }
    }

    /**
     * @return array<string, array{blocked: bool, remaining_seconds: int, label: string, ready_at: ?string, human: string}>
     */
    public function allCooldownStates(int $tenantId, int $interestCaseId): array
    {
        $out = [];
        foreach (array_keys(self::COOLDOWNS) as $key) {
            $state = $this->cooldownState($tenantId, $interestCaseId, $key);
            $state['human'] = $this->formatCooldownHuman($state);
            $out[$key] = $state;
        }

        return $out;
    }

    /** @param array{blocked?: bool, remaining_seconds?: int, label?: string} $state */
    public function formatCooldownHuman(array $state): string
    {
        if (empty($state['blocked'])) {
            return '';
        }
        $sec = max(1, (int) ($state['remaining_seconds'] ?? 0));
        $mins = (int) ceil($sec / 60);
        $label = (string) ($state['label'] ?? 'Cette action');
        if ($mins <= 1) {
            return $label . ' : attendez encore environ une minute avant de recommencer.';
        }

        return $label . ' : attendez encore environ ' . $mins . ' minutes avant de recommencer.';
    }

    private function hydrate(array $row): array
    {
        $row['status_label'] = self::STATUSES[$row['status'] ?? ''] ?? 'À qualifier';
        $row['confidence_label'] = self::CONFIDENCE[$row['confidence_level'] ?? ''] ?? 'Non évalué';
        $row['interest_label'] = self::INTEREST[$row['interest_level'] ?? ''] ?? 'Courant';
        $row['description'] = $row['description'] ?? null;
        $row['priority_code'] = \App\Support\SseInterestCaseCatalog::priorityCode((string) ($row['interest_level'] ?? ''));
        $row['tab'] = \App\Support\SseInterestCaseCatalog::tabOf((string) ($row['status'] ?? ''));
        $row['version'] = max(1, (int) ($row['version'] ?? 1));
        $classification = (string) ($row['classification'] ?? '');
        $row['classification'] = isset(\App\Services\Sse\SseRedactionService::LEVELS[$classification]) ? $classification : 'interne';
        $row['tag_list'] = \App\Support\SseInterestCaseCatalog::splitTags(isset($row['tags']) ? (string) $row['tags'] : null);
        $row['assignee_user_id'] = isset($row['assignee_user_id']) && (int) $row['assignee_user_id'] > 0 ? (int) $row['assignee_user_id'] : null;
        $row['is_deleted'] = !empty($row['deleted_at']);
        $row['is_overdue'] = !empty($row['due_at'])
            && strtotime((string) $row['due_at']) !== false
            && strtotime((string) $row['due_at']) < time()
            && !in_array((string) ($row['status'] ?? ''), ['identite_consolidee', 'identite_infirme', 'sans_suite', 'archive'], true);

        return $row;
    }

    /**
     * @param list<mixed> $ids
     * @return list<int>
     */
    private function normalizeUserIds(array $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            $n = (int) $id;
            if ($n > 0) {
                $out[$n] = $n;
            }
        }

        return array_values($out);
    }
}
