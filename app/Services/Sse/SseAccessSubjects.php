<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Database;
use App\Support\AdministrativePositionCatalog;
use App\Support\SseAccessCatalog as C;
use PDO;

/**
 * Bénéficiaires des règles de droits d'accès SSE : à quoi un membre « appartient » (niveaux d'accès, unités, unités
 * parentes) et inversement qui appartient à un niveau d'accès ou à une unité. Tout est borné par communauté : un
 * identifiant qui n'est pas de la communauté n'existe pas (ni libellé, ni membres).
 *
 * Tolère les vieilles bases (tables ou colonnes absentes) : l'appartenance manquante est simplement vide.
 */
class SseAccessSubjects
{
    /** @var array<string, array{roles: list<int>, units: list<int>, tree: list<int>}> */
    private array $userMemo = [];

    /** @var array<int, array<int, int>> */
    private array $parentMemo = [];

    /** @var array<string, bool> */
    private array $tableMemo = [];

    /** @var array<string, array{jobs: list<int>, qualifs: list<int>}> fonctions et qualifications valides par membre (« tenant:user ») */
    private array $facetMemo = [];

    /** @var array<int, array<int, string>> positions de service : id du rôle d'affichage => libellé, par communauté */
    private array $positionMemo = [];

    public function __construct(private ?PDO $pdo = null)
    {
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    private function tableExists(string $table): bool
    {
        if (!isset($this->tableMemo[$table])) {
            try {
                $this->pdo()->query('SELECT 1 FROM ' . $table . ' LIMIT 1');
                $this->tableMemo[$table] = true;
            } catch (\Throwable) {
                $this->tableMemo[$table] = false;
            }
        }

        return $this->tableMemo[$table];
    }

    /** Oublie les appartenances lues (après un changement de rôle ou d'unité, ou dans un test). */
    public function reset(): void
    {
        $this->userMemo = [];
        $this->parentMemo = [];
        $this->facetMemo = [];
        $this->positionMemo = [];
    }

    /** Oublie ce qui a été lu d'un seul membre (changement de position, de fonction ou de qualification). */
    public function forget(int $tenantId, int $userId): void
    {
        if ($tenantId < 1) {
            // Communauté inconnue de l'appelant (qualification) : toutes celles lues pour ce membre.
            foreach ([&$this->userMemo, &$this->facetMemo] as &$memo) {
                foreach (array_keys($memo) as $key) {
                    if (str_ends_with((string) $key, ':' . $userId)) {
                        unset($memo[$key]);
                    }
                }
            }

            return;
        }
        unset($this->userMemo[$tenantId . ':' . $userId], $this->facetMemo[$tenantId . ':' . $userId]);
    }

    /**
     * Niveaux d'accès, unités et unités parentes d'un membre.
     *
     * @return array{roles: list<int>, units: list<int>, tree: list<int>}
     */
    public function forUser(int $tenantId, int $userId): array
    {
        $key = $tenantId . ':' . $userId;
        if (isset($this->userMemo[$key])) {
            return $this->userMemo[$key];
        }
        $out = ['roles' => [], 'units' => [], 'tree' => []];
        if ($tenantId < 1 || $userId < 1) {
            return $this->userMemo[$key] = $out;
        }
        $out['roles'] = $this->roleIdsForUser($tenantId, $userId);
        $out['units'] = $this->unitIdsForUser($tenantId, $userId);
        $tree = [];
        foreach ($out['units'] as $unitId) {
            foreach ($this->ancestorsAndSelf($tenantId, $unitId) as $id) {
                $tree[$id] = true;
            }
        }
        $out['tree'] = array_map('intval', array_keys($tree));

        return $this->userMemo[$key] = $out;
    }

    /** @return list<int> */
    private function roleIdsForUser(int $tenantId, int $userId): array
    {
        $ids = [];
        $now = date('Y-m-d H:i:s');
        try {
            if ($this->tableExists('tenant_user_roles')) {
                $st = $this->pdo()->prepare(
                    'SELECT role_id FROM tenant_user_roles WHERE user_id = ? AND tenant_id = ?
                       AND (valid_from IS NULL OR valid_from <= ?) AND (valid_until IS NULL OR valid_until > ?)'
                );
                $st->execute([$userId, $tenantId, $now, $now]);
                foreach ($st->fetchAll(PDO::FETCH_COLUMN) ?: [] as $v) {
                    $ids[(int) $v] = true;
                }
            }
        } catch (\Throwable) {
        }
        try {
            $st = $this->pdo()->prepare('SELECT role_id FROM users WHERE id = ? LIMIT 1');
            $st->execute([$userId]);
            $v = (int) $st->fetchColumn();
            if ($v > 0) {
                $ids[$v] = true;
            }
        } catch (\Throwable) {
        }
        try {
            if ($this->tableExists('user_roles')) {
                $st = $this->pdo()->prepare('SELECT role_id FROM user_roles WHERE user_id = ?');
                $st->execute([$userId]);
                foreach ($st->fetchAll(PDO::FETCH_COLUMN) ?: [] as $v) {
                    $ids[(int) $v] = true;
                }
            }
        } catch (\Throwable) {
        }
        unset($ids[0]);
        if ($ids === []) {
            return [];
        }

        // Seuls comptent les niveaux d'accès de cette communauté.
        return $this->keepTenantRoles($tenantId, array_map('intval', array_keys($ids)));
    }

    /**
     * @param list<int> $roleIds
     * @return list<int>
     */
    private function keepTenantRoles(int $tenantId, array $roleIds): array
    {
        if ($roleIds === []) {
            return [];
        }
        try {
            $ph = implode(',', array_fill(0, count($roleIds), '?'));
            $st = $this->pdo()->prepare('SELECT id FROM roles WHERE tenant_id = ? AND id IN (' . $ph . ')');
            $st->execute(array_merge([$tenantId], $roleIds));

            return array_values(array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []));
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return list<int> */
    private function unitIdsForUser(int $tenantId, int $userId): array
    {
        $ids = [];
        $queries = [
            'SELECT uu.unit_id FROM user_units uu INNER JOIN units un ON un.id = uu.unit_id AND un.tenant_id = ?
              WHERE uu.user_id = ? AND (uu.ended_at IS NULL OR uu.ended_at > ?)' => ['user_units', true],
            'SELECT pa.unit_id FROM personnel_assignments pa INNER JOIN units un ON un.id = pa.unit_id AND un.tenant_id = ?
              WHERE pa.user_id = ? AND pa.status = \'active\' AND (pa.ended_at IS NULL OR pa.ended_at > ?)' => ['personnel_assignments', true],
            'SELECT pp.primary_unit_id FROM personnel_profiles pp INNER JOIN units un ON un.id = pp.primary_unit_id AND un.tenant_id = ?
              WHERE pp.user_id = ? AND pp.primary_unit_id IS NOT NULL AND ? IS NOT NULL' => ['personnel_profiles', true],
        ];
        $now = date('Y-m-d H:i:s');
        foreach ($queries as $sql => [$table]) {
            try {
                if (!$this->tableExists($table)) {
                    continue;
                }
                $st = $this->pdo()->prepare($sql);
                $st->execute([$tenantId, $userId, $now]);
                foreach ($st->fetchAll(PDO::FETCH_COLUMN) ?: [] as $v) {
                    if ((int) $v > 0) {
                        $ids[(int) $v] = true;
                    }
                }
            } catch (\Throwable) {
            }
        }

        return array_map('intval', array_keys($ids));
    }

    /** @return array<int, int> id d'unité => id du parent (0 : racine), pour la communauté */
    private function parents(int $tenantId): array
    {
        if (!isset($this->parentMemo[$tenantId])) {
            $map = [];
            try {
                $st = $this->pdo()->prepare('SELECT id, parent_id FROM units WHERE tenant_id = ?');
                $st->execute([$tenantId]);
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                    $map[(int) $r['id']] = (int) ($r['parent_id'] ?? 0);
                }
            } catch (\Throwable) {
            }
            $this->parentMemo[$tenantId] = $map;
        }

        return $this->parentMemo[$tenantId];
    }

    /** @return list<int> l'unité puis ses parents jusqu'à la racine (protégé contre les boucles) */
    public function ancestorsAndSelf(int $tenantId, int $unitId): array
    {
        $parents = $this->parents($tenantId);
        $out = [];
        $cur = $unitId;
        while ($cur > 0 && !isset($out[$cur]) && isset($parents[$cur])) {
            $out[$cur] = true;
            $cur = $parents[$cur];
        }

        return array_map('intval', array_keys($out));
    }

    /** @return list<int> l'unité et toutes ses sous-unités */
    public function descendantsAndSelf(int $tenantId, int $unitId): array
    {
        $parents = $this->parents($tenantId);
        if (!isset($parents[$unitId])) {
            return [];
        }
        $children = [];
        foreach ($parents as $id => $parent) {
            $children[$parent][] = $id;
        }
        $seen = [];
        $stack = [$unitId];
        while ($stack !== []) {
            $id = (int) array_pop($stack);
            if ($id < 1 || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            foreach ($children[$id] ?? [] as $child) {
                $stack[] = $child;
            }
        }

        return array_map('intval', array_keys($seen));
    }

    /** Les identifiants existent-ils dans la communauté ? (jamais d'identifiant d'une autre communauté) */
    public function exists(int $tenantId, string $type, int $id): bool
    {
        return $this->label($tenantId, $type, $id) !== null;
    }

    /** Libellé d'un bénéficiaire de la communauté ; null s'il n'en fait pas partie. */
    public function label(int $tenantId, string $type, int $id): ?string
    {
        if ($tenantId < 1 || $id < 1) {
            return null;
        }
        try {
            switch ($type) {
                case C::SUBJECT_ROLE:
                    $st = $this->pdo()->prepare('SELECT name FROM roles WHERE tenant_id = ? AND id = ? LIMIT 1');
                    $st->execute([$tenantId, $id]);
                    $name = $st->fetchColumn();

                    return $name === false ? null : (string) $name;
                case C::SUBJECT_UNIT:
                case C::SUBJECT_UNIT_TREE:
                    $st = $this->pdo()->prepare('SELECT name FROM units WHERE tenant_id = ? AND id = ? LIMIT 1');
                    $st->execute([$tenantId, $id]);
                    $name = $st->fetchColumn();
                    if ($name === false) {
                        return null;
                    }

                    return (string) $name . ($type === C::SUBJECT_UNIT_TREE ? ' et sous-unités' : '');
                case C::SUBJECT_USER:
                    return $this->userLabel($tenantId, $id);
                case C::SUBJECT_POSITION:
                    return $this->positionRoles($tenantId)[$id] ?? null;
                case C::SUBJECT_JOB:
                    $st = $this->pdo()->prepare('SELECT name FROM personnel_job_roles WHERE tenant_id = ? AND id = ? LIMIT 1');
                    $st->execute([$tenantId, $id]);
                    $name = $st->fetchColumn();

                    return $name === false ? null : (string) $name;
                case C::SUBJECT_QUALIF:
                    $st = $this->pdo()->prepare('SELECT name FROM personnel_qualification_definitions WHERE tenant_id = ? AND id = ? LIMIT 1');
                    $st->execute([$tenantId, $id]);
                    $name = $st->fetchColumn();

                    return $name === false ? null : (string) $name;
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    /** Nom d'un membre de la communauté (ligne users) ; null s'il n'en est pas. Le filtre d'annuaire est appliqué ailleurs. */
    private function userLabel(int $tenantId, int $userId): ?string
    {
        $st = $this->pdo()->prepare('SELECT display_name, tenant_id FROM users WHERE id = ? LIMIT 1');
        $st->execute([$userId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        if ((int) ($row['tenant_id'] ?? 0) !== $tenantId && !$this->hasActiveMembership($tenantId, $userId)) {
            return null;
        }
        $name = trim((string) ($row['display_name'] ?? ''));

        return $name !== '' ? $name : 'Membre #' . $userId;
    }

    private function hasActiveMembership(int $tenantId, int $userId): bool
    {
        if (!$this->tableExists('user_community_memberships')) {
            return false;
        }
        try {
            $st = $this->pdo()->prepare(
                "SELECT 1 FROM user_community_memberships WHERE user_id = ? AND tenant_id = ? AND status = 'active' LIMIT 1"
            );
            $st->execute([$userId, $tenantId]);

            return $st->fetchColumn() !== false;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Membres de la communauté couverts par un bénéficiaire (comptes actifs), plafonnés.
     *
     * @return list<int>
     */
    public function membersOf(int $tenantId, string $type, int $id, int $cap = 2000): array
    {
        if ($tenantId < 1 || $id < 1 || !$this->exists($tenantId, $type, $id)) {
            return [];
        }
        $ids = [];
        try {
            switch ($type) {
                case C::SUBJECT_USER:
                    return [$id];
                case C::SUBJECT_ROLE:
                case C::SUBJECT_POSITION:
                    $ids = $this->userIdsWithRole($tenantId, $id);
                    break;
                case C::SUBJECT_JOB:
                    $ids = $this->userIdsWithJob($tenantId, $id);
                    break;
                case C::SUBJECT_QUALIF:
                    $ids = $this->userIdsWithQualification($tenantId, $id);
                    break;
                case C::SUBJECT_UNIT:
                    $ids = $this->userIdsInUnits($tenantId, [$id]);
                    break;
                case C::SUBJECT_UNIT_TREE:
                    $ids = $this->userIdsInUnits($tenantId, $this->descendantsAndSelf($tenantId, $id));
                    break;
            }
        } catch (\Throwable) {
            return [];
        }

        return array_slice(array_values(array_unique(array_map('intval', $ids))), 0, max(1, $cap));
    }

    /** @return list<int> */
    private function userIdsWithRole(int $tenantId, int $roleId): array
    {
        $ids = [];
        $now = date('Y-m-d H:i:s');
        $sources = [
            ['SELECT id FROM users WHERE tenant_id = ? AND role_id = ?', [$tenantId, $roleId], null],
            [
                'SELECT user_id FROM tenant_user_roles WHERE tenant_id = ? AND role_id = ?
                   AND (valid_from IS NULL OR valid_from <= ?) AND (valid_until IS NULL OR valid_until > ?)',
                [$tenantId, $roleId, $now, $now],
                'tenant_user_roles',
            ],
            [
                'SELECT ur.user_id FROM user_roles ur INNER JOIN users u ON u.id = ur.user_id AND u.tenant_id = ? WHERE ur.role_id = ?',
                [$tenantId, $roleId],
                'user_roles',
            ],
        ];
        foreach ($sources as [$sql, $params, $table]) {
            try {
                if ($table !== null && !$this->tableExists($table)) {
                    continue;
                }
                $st = $this->pdo()->prepare($sql);
                $st->execute($params);
                foreach ($st->fetchAll(PDO::FETCH_COLUMN) ?: [] as $v) {
                    $ids[(int) $v] = true;
                }
            } catch (\Throwable) {
            }
        }
        unset($ids[0]);

        return array_map('intval', array_keys($ids));
    }

    /**
     * @param list<int> $unitIds
     * @return list<int>
     */
    private function userIdsInUnits(int $tenantId, array $unitIds): array
    {
        if ($unitIds === []) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($unitIds), '?'));
        $now = date('Y-m-d H:i:s');
        $ids = [];
        $sources = [
            ['SELECT uu.user_id FROM user_units uu WHERE uu.unit_id IN (' . $ph . ') AND (uu.ended_at IS NULL OR uu.ended_at > ?)', 'user_units', true],
            ['SELECT pa.user_id FROM personnel_assignments pa WHERE pa.unit_id IN (' . $ph . ") AND pa.status = 'active' AND (pa.ended_at IS NULL OR pa.ended_at > ?)", 'personnel_assignments', true],
            ['SELECT pp.user_id FROM personnel_profiles pp WHERE pp.primary_unit_id IN (' . $ph . ') AND ? IS NOT NULL', 'personnel_profiles', true],
        ];
        foreach ($sources as [$sql, $table]) {
            try {
                if (!$this->tableExists($table)) {
                    continue;
                }
                $st = $this->pdo()->prepare($sql);
                $st->execute(array_merge($unitIds, [$now]));
                foreach ($st->fetchAll(PDO::FETCH_COLUMN) ?: [] as $v) {
                    $ids[(int) $v] = true;
                }
            } catch (\Throwable) {
            }
        }
        unset($ids[0]);

        return array_map('intval', array_keys($ids));
    }

    // ------------------------------------------------------------------ Positions, fonctions, qualifications

    /**
     * Positions de service (administratives) de la communauté : id du rôle d'affichage => libellé. Les quatre positions du
     * site et celles de la communauté, seulement quand leur rôle existe déjà (il se crée à la première attribution).
     *
     * @return array<int, string>
     */
    public function positionRoles(int $tenantId): array
    {
        if ($tenantId < 1) {
            return [];
        }
        if (isset($this->positionMemo[$tenantId])) {
            return $this->positionMemo[$tenantId];
        }
        $labels = [];
        foreach (AdministrativePositionCatalog::all($tenantId) as $position) {
            $labels[(string) $position['slug']] = (string) $position['label'];
        }
        $out = [];
        if ($labels !== []) {
            try {
                $ph = implode(',', array_fill(0, count($labels), '?'));
                $st = $this->pdo()->prepare('SELECT id, slug FROM roles WHERE tenant_id = ? AND slug IN (' . $ph . ')');
                $st->execute(array_merge([$tenantId], array_keys($labels)));
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                    $out[(int) $r['id']] = $labels[(string) $r['slug']] ?? (string) $r['slug'];
                }
            } catch (\Throwable) {
                $out = [];
            }
        }

        return $this->positionMemo[$tenantId] = $out;
    }

    /**
     * Fonctions (emplois) et qualifications valides d'un membre. Une qualification compte seulement si elle est obtenue,
     * valide ou bientôt échue ET que sa date d'échéance n'est pas passée : expirée, suspendue, révoquée ou en cours, elle
     * ne donne rien. Les positions de service se lisent avec les niveaux d'accès (forUser, « roles »).
     *
     * @return array{jobs: list<int>, qualifs: list<int>}
     */
    public function facetsFor(int $tenantId, int $userId): array
    {
        $key = $tenantId . ':' . $userId;
        if (!isset($this->facetMemo[$key])) {
            $this->preloadFacets($tenantId, [$userId]);
        }

        return $this->facetMemo[$key] ?? ['jobs' => [], 'qualifs' => []];
    }

    /**
     * Charge en deux requêtes les fonctions et qualifications de plusieurs membres (évite une requête par membre quand on
     * évalue toute la communauté : « qui a accès », aperçu).
     *
     * @param list<int> $userIds
     */
    public function preloadFacets(int $tenantId, array $userIds): void
    {
        $todo = [];
        foreach ($userIds as $uid) {
            $uid = (int) $uid;
            if ($uid > 0 && !isset($this->facetMemo[$tenantId . ':' . $uid])) {
                $todo[$uid] = true;
            }
        }
        if ($tenantId < 1 || $todo === []) {
            return;
        }
        foreach (array_chunk(array_keys($todo), 400) as $chunk) {
            $found = array_fill_keys($chunk, ['jobs' => [], 'qualifs' => []]);
            $ph = implode(',', array_fill(0, count($chunk), '?'));
            try {
                if ($this->tableExists('personnel_profile_job_roles')) {
                    $st = $this->pdo()->prepare('SELECT user_id, personnel_job_role_id FROM personnel_profile_job_roles WHERE tenant_id = ? AND user_id IN (' . $ph . ')');
                    $st->execute(array_merge([$tenantId], $chunk));
                    foreach ($st->fetchAll(PDO::FETCH_NUM) ?: [] as [$uid, $job]) {
                        $found[(int) $uid]['jobs'][(int) $job] = (int) $job;
                    }
                }
            } catch (\Throwable) {
            }
            foreach ($this->validQualificationRows($tenantId, ' AND pq.user_id IN (' . $ph . ')', $chunk) as [$uid, $def]) {
                $found[$uid]['qualifs'][$def] = $def;
            }
            foreach ($found as $uid => $facets) {
                $this->facetMemo[$tenantId . ':' . $uid] = ['jobs' => array_values($facets['jobs']), 'qualifs' => array_values($facets['qualifs'])];
            }
        }
    }

    /**
     * Lignes (membre, qualification) valides aujourd'hui, avec la définition de la communauté (jamais celle d'une autre).
     *
     * @param list<int|string> $params
     * @return list<array{0: int, 1: int}>
     */
    private function validQualificationRows(int $tenantId, string $extraWhere, array $params): array
    {
        if (!$this->tableExists('personnel_qualifications') || !$this->tableExists('personnel_qualification_definitions')) {
            return [];
        }
        $today = date('Y-m-d');
        $base = 'SELECT pq.user_id, pq.definition_id FROM personnel_qualifications pq
                 INNER JOIN personnel_qualification_definitions d ON d.id = pq.definition_id AND d.tenant_id = ?
                 WHERE pq.definition_id IS NOT NULL AND (pq.expires_at IS NULL OR pq.expires_at >= ?)';
        // « admin_status » (référentiel enrichi) l'emporte sur « status » ; sur une vieille base sans cette colonne, « status » seul.
        $variants = [
            $base . " AND COALESCE(pq.admin_status, pq.status) IN ('obtained', 'valid', 'expiring')",
            $base . " AND pq.status IN ('obtained', 'valid', 'expiring')",
        ];
        foreach ($variants as $sql) {
            try {
                $st = $this->pdo()->prepare($sql . $extraWhere);
                $st->execute(array_merge([$tenantId, $today], $params));

                return array_map(static fn (array $r): array => [(int) $r[0], (int) $r[1]], $st->fetchAll(PDO::FETCH_NUM) ?: []);
            } catch (\Throwable) {
            }
        }

        return [];
    }

    /** @return list<int> */
    private function userIdsWithJob(int $tenantId, int $jobId): array
    {
        if (!$this->tableExists('personnel_profile_job_roles')) {
            return [];
        }
        $st = $this->pdo()->prepare('SELECT user_id FROM personnel_profile_job_roles WHERE tenant_id = ? AND personnel_job_role_id = ?');
        $st->execute([$tenantId, $jobId]);

        return array_values(array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []));
    }

    /** @return list<int> */
    private function userIdsWithQualification(int $tenantId, int $definitionId): array
    {
        $ids = [];
        foreach ($this->validQualificationRows($tenantId, ' AND pq.definition_id = ?', [$definitionId]) as [$uid]) {
            $ids[$uid] = true;
        }

        return array_map('intval', array_keys($ids));
    }

    /**
     * Positions de service, fonctions et qualifications de la communauté, pour le champ « ajouter un bénéficiaire ».
     *
     * @return array{positions: list<array{id: int, label: string}>, jobs: list<array{id: int, label: string}>, qualifs: list<array{id: int, label: string}>}
     */
    public function simpleOptions(int $tenantId): array
    {
        $out = ['positions' => [], 'jobs' => [], 'qualifs' => []];
        if ($tenantId < 1) {
            return $out;
        }
        foreach ($this->positionRoles($tenantId) as $id => $label) {
            $out['positions'][] = ['id' => $id, 'label' => $label];
        }
        $queries = [
            'jobs' => 'SELECT id, name FROM personnel_job_roles WHERE tenant_id = ? ORDER BY name ASC LIMIT 600',
            'qualifs' => 'SELECT id, name FROM personnel_qualification_definitions WHERE tenant_id = ? AND archived_at IS NULL AND COALESCE(is_active, 1) = 1 ORDER BY name ASC LIMIT 600',
        ];
        foreach ($queries as $key => $sql) {
            try {
                $st = $this->pdo()->prepare($sql);
                $st->execute([$tenantId]);
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                    $out[$key][] = ['id' => (int) $r['id'], 'label' => (string) $r['name']];
                }
            } catch (\Throwable) {
            }
        }

        return $out;
    }

    /**
     * Niveaux d'accès et unités de la communauté, pour les listes de choix.
     *
     * @return array{roles: list<array{id: int, label: string}>, units: list<array{id: int, label: string}>}
     */
    public function options(int $tenantId): array
    {
        $roles = [];
        $units = [];
        if ($tenantId < 1) {
            return ['roles' => $roles, 'units' => $units];
        }
        try {
            $st = $this->pdo()->prepare('SELECT id, name FROM roles WHERE tenant_id = ? ORDER BY name ASC LIMIT 400');
            $st->execute([$tenantId]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $roles[] = ['id' => (int) $r['id'], 'label' => (string) $r['name']];
            }
        } catch (\Throwable) {
        }
        try {
            $st = $this->pdo()->prepare('SELECT id, name FROM units WHERE tenant_id = ? ORDER BY name ASC LIMIT 600');
            $st->execute([$tenantId]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $units[] = ['id' => (int) $r['id'], 'label' => (string) $r['name']];
            }
        } catch (\Throwable) {
        }

        return ['roles' => $roles, 'units' => $units];
    }

    /**
     * Membres titulaires d'un droit parmi une liste de droits (niveaux d'accès qui l'ont, puis leurs membres) : sert à
     * repérer les gestionnaires SSE sans charger les droits de chaque membre. Les droits individuels ne sont pas vus.
     *
     * @param list<string> $permissionSlugs
     * @return list<int>
     */
    public function holdersOfAnyPermission(int $tenantId, array $permissionSlugs): array
    {
        if ($tenantId < 1 || $permissionSlugs === []) {
            return [];
        }
        $roleIds = [];
        try {
            $ph = implode(',', array_fill(0, count($permissionSlugs), '?'));
            $st = $this->pdo()->prepare(
                'SELECT DISTINCT rp.role_id FROM role_permissions rp
                   INNER JOIN permissions p ON p.id = rp.permission_id
                   INNER JOIN roles r ON r.id = rp.role_id AND r.tenant_id = ?
                  WHERE p.slug IN (' . $ph . ')'
            );
            $st->execute(array_merge([$tenantId], array_values($permissionSlugs)));
            $roleIds = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
        } catch (\Throwable) {
            return [];
        }
        $users = [];
        foreach ($roleIds as $roleId) {
            foreach ($this->userIdsWithRole($tenantId, $roleId) as $uid) {
                $users[$uid] = true;
            }
        }

        return array_map('intval', array_keys($users));
    }
}
