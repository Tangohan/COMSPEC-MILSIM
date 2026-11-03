<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Droits d'accès de l'Academy : règles de la communauté (qui crée, modifie, publie, inscrit, valide, délivre,
 * consulte les statistiques), public d'un cours et réglages d'un cours (auteur, brouillon privé).
 * Tables créées par l'étape academy_access_rules (3520). Sans elles, rien n'est réglé : comportement d'origine.
 */
final class AcademyAccessRepository
{
    private PDO $pdo;
    private ?bool $ready = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
    }

    public function ready(): bool
    {
        if ($this->ready === null) {
            try {
                $this->pdo->query('SELECT 1 FROM academy_access_rules WHERE 1 = 0');
                $this->pdo->query('SELECT 1 FROM academy_course_settings WHERE 1 = 0');
                $this->ready = true;
            } catch (\PDOException) {
                $this->ready = false;
            }
        }
        return $this->ready;
    }

    /**
     * Règles d'une portée : '' = communauté, sinon le public du cours.
     *
     * @return array<string, list<array{type:string, id:int}>> droit => sujets
     */
    public function rules(int $tenantId, string $courseKey = ''): array
    {
        if (!$this->ready()) {
            return [];
        }
        $stmt = $this->pdo->prepare('SELECT capability, subject_type, subject_id FROM academy_access_rules WHERE tenant_id = ? AND course_key = ? ORDER BY capability, subject_type, subject_id');
        $stmt->execute([$tenantId, $courseKey]);
        $rules = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rules[(string) $row['capability']][] = ['type' => (string) $row['subject_type'], 'id' => (int) $row['subject_id']];
        }
        return $rules;
    }

    /**
     * Public de tous les cours restreints de la communauté, en une requête.
     *
     * @return array<string, list<array{type:string, id:int}>> clé du cours => sujets
     */
    public function courseAudiences(int $tenantId): array
    {
        if (!$this->ready()) {
            return [];
        }
        $stmt = $this->pdo->prepare("SELECT course_key, subject_type, subject_id FROM academy_access_rules WHERE tenant_id = ? AND course_key <> '' AND capability = 'view'");
        $stmt->execute([$tenantId]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[(string) $row['course_key']][] = ['type' => (string) $row['subject_type'], 'id' => (int) $row['subject_id']];
        }
        return $out;
    }

    /**
     * Remplace les sujets d'un droit (dans une transaction). Liste nulle = retour au réglage par défaut.
     *
     * @param list<array{type:string, id:int}>|null $subjects
     */
    public function replaceRule(int $tenantId, string $courseKey, string $capability, ?array $subjects, int $by): void
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('DELETE FROM academy_access_rules WHERE tenant_id = ? AND course_key = ? AND capability = ?');
            $stmt->execute([$tenantId, $courseKey, $capability]);
            if ($subjects !== null) {
                $insert = $this->pdo->prepare('INSERT INTO academy_access_rules (tenant_id, course_key, capability, subject_type, subject_id, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $seen = [];
                foreach ($subjects as $subject) {
                    $id = $subject['type'] . ':' . $subject['id'];
                    if (isset($seen[$id])) {
                        continue;
                    }
                    $seen[$id] = true;
                    $insert->execute([$tenantId, $courseKey, $capability, $subject['type'], $subject['id'], $by ?: null, date('Y-m-d H:i:s')]);
                }
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /** @return array{owner_id:?int, audience:string, private_draft:bool} */
    public function settings(int $tenantId, string $courseKey): array
    {
        return $this->allSettings($tenantId)[$courseKey] ?? self::defaults();
    }

    /** @return array{owner_id:?int, audience:string, private_draft:bool} */
    public static function defaults(): array
    {
        return ['owner_id' => null, 'audience' => 'community', 'private_draft' => false];
    }

    /** @return array<string, array{owner_id:?int, audience:string, private_draft:bool}> */
    public function allSettings(int $tenantId): array
    {
        if (!$this->ready()) {
            return [];
        }
        $stmt = $this->pdo->prepare('SELECT course_key, owner_id, audience, private_draft FROM academy_course_settings WHERE tenant_id = ?');
        $stmt->execute([$tenantId]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[(string) $row['course_key']] = [
                'owner_id' => $row['owner_id'] !== null ? (int) $row['owner_id'] : null,
                'audience' => $row['audience'] === 'restricted' ? 'restricted' : 'community',
                'private_draft' => (bool) $row['private_draft'],
            ];
        }
        return $out;
    }

    /** @param array{owner_id?:?int, audience?:string, private_draft?:bool} $changes */
    public function saveSettings(int $tenantId, string $courseKey, array $changes, int $by): void
    {
        if (!$this->ready()) {
            return;
        }
        $current = $this->settings($tenantId, $courseKey);
        $next = array_replace($current, array_intersect_key($changes, $current));
        $stmt = $this->pdo->prepare('DELETE FROM academy_course_settings WHERE tenant_id = ? AND course_key = ?');
        $stmt->execute([$tenantId, $courseKey]);
        $stmt = $this->pdo->prepare('INSERT INTO academy_course_settings (tenant_id, course_key, owner_id, audience, private_draft, updated_by, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$tenantId, $courseKey, $next['owner_id'], $next['audience'] === 'restricted' ? 'restricted' : 'community', (int) $next['private_draft'], $by ?: null, date('Y-m-d H:i:s')]);
    }

    /** Une nouvelle version ou une copie reprend le public et le brouillon privé de son original ; l'auteur est celui qui la crée. */
    public function copyCourse(int $tenantId, string $from, string $to, int $owner): void
    {
        if (!$this->ready()) {
            return;
        }
        $settings = $this->settings($tenantId, $from);
        $this->saveSettings($tenantId, $to, ['owner_id' => $owner, 'audience' => $settings['audience'], 'private_draft' => $settings['private_draft']], $owner);
        $view = $this->rules($tenantId, $from)['view'] ?? null;
        if ($view !== null) {
            $this->replaceRule($tenantId, $to, 'view', $view, $owner);
        }
    }

    /** Une formation supprimée définitivement emporte ses réglages. */
    public function forget(int $tenantId, string $courseKey): void
    {
        if (!$this->ready() || $courseKey === '') {
            return;
        }
        foreach (['academy_access_rules', 'academy_course_settings'] as $table) {
            $stmt = $this->pdo->prepare('DELETE FROM ' . $table . ' WHERE tenant_id = ? AND course_key = ?');
            $stmt->execute([$tenantId, $courseKey]);
        }
    }

    /**
     * Rôles d'accès, fonctions et unités (avec leurs unités parentes) d'un membre dans la communauté.
     *
     * @return array{roles:list<int>, jobs:list<int>, units:list<int>}
     */
    public function memberships(int $tenantId, int $userId): array
    {
        $now = date('Y-m-d H:i:s');
        $roles = $this->column('SELECT role_id FROM tenant_user_roles WHERE tenant_id = ? AND user_id = ? AND (valid_until IS NULL OR valid_until > ?)', [$tenantId, $userId, $now]);
        $roles = array_merge($roles, $this->column('SELECT u.role_id FROM users u INNER JOIN roles r ON r.id = u.role_id AND r.tenant_id = ? WHERE u.id = ?', [$tenantId, $userId]));
        $jobs = $this->column('SELECT personnel_job_role_id FROM personnel_profile_job_roles WHERE tenant_id = ? AND user_id = ?', [$tenantId, $userId]);
        $units = $this->column('SELECT uu.unit_id FROM user_units uu INNER JOIN units un ON un.id = uu.unit_id AND un.tenant_id = ? WHERE uu.user_id = ? AND (uu.ended_at IS NULL OR uu.ended_at > ?)', [$tenantId, $userId, $now]);
        $units = array_merge($units, $this->column("SELECT pa.unit_id FROM personnel_assignments pa INNER JOIN units un ON un.id = pa.unit_id AND un.tenant_id = ? WHERE pa.user_id = ? AND pa.status = 'active' AND (pa.ended_at IS NULL OR pa.ended_at > ?)", [$tenantId, $userId, substr($now, 0, 10)]));
        if ($units !== []) {
            // Un droit donné à une unité vaut pour ses sous-unités.
            $parents = [];
            foreach ($this->rows('SELECT id, parent_id FROM units WHERE tenant_id = ?', [$tenantId]) as $row) {
                $parents[(int) $row['id']] = (int) ($row['parent_id'] ?? 0);
            }
            foreach ($units as $unit) {
                $guard = 0;
                while (($parents[$unit] ?? 0) > 0 && $guard++ < 32) {
                    $unit = $parents[$unit];
                    $units[] = $unit;
                }
            }
        }
        $clean = static fn (array $ids): array => array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        return ['roles' => $clean($roles), 'jobs' => $clean($jobs), 'units' => $clean($units)];
    }

    /** @return array<int, string> rôles d'accès de la communauté (Membre, RH, Gestionnaire, rôles créés) */
    public function accessRoleOptions(int $tenantId): array
    {
        $out = [];
        try {
            foreach ((new RoleRepository())->forTenantAccessProfiles($tenantId) as $role) {
                $out[(int) $role['id']] = (string) ($role['name'] ?? '');
            }
        } catch (\Throwable) {
        }
        return $out;
    }

    /** @return array<int, string> fonctions (emplois) de la communauté */
    public function jobOptions(int $tenantId): array
    {
        $out = [];
        foreach ($this->rows('SELECT id, name FROM personnel_job_roles WHERE tenant_id = ? ORDER BY name', [$tenantId]) as $row) {
            $out[(int) $row['id']] = (string) $row['name'];
        }
        return $out;
    }

    /** @return array<int, string> unités de la communauté */
    public function unitOptions(int $tenantId): array
    {
        $out = [];
        foreach ($this->rows('SELECT id, name FROM units WHERE tenant_id = ? ORDER BY name', [$tenantId]) as $row) {
            $out[(int) $row['id']] = (string) $row['name'];
        }
        return $out;
    }

    /**
     * Filtres du tableau de suivi pour toute la communauté en trois lectures : unités (affectations actives), fonctions
     * et cohortes (groupes de formation) de chaque membre, et la liste des cohortes.
     *
     * @return array{units: array<int, list<int>>, jobs: array<int, list<int>>, groups: array<int, list<int>>, groupOptions: array<int, string>}
     */
    public function trackerFacets(int $tenantId): array
    {
        $now = date('Y-m-d H:i:s');
        $out = ['units' => [], 'jobs' => [], 'groups' => [], 'groupOptions' => []];
        $collect = function (string $bucket, string $sql, array $params) use (&$out): void {
            foreach ($this->rows($sql, $params) as $row) {
                $out[$bucket][(int) $row['user_id']][] = (int) $row['ref_id'];
            }
        };
        $collect('units', 'SELECT uu.user_id, uu.unit_id AS ref_id FROM user_units uu INNER JOIN units un ON un.id = uu.unit_id AND un.tenant_id = ? WHERE uu.ended_at IS NULL OR uu.ended_at > ?', [$tenantId, $now]);
        $collect('units', "SELECT pa.user_id, pa.unit_id AS ref_id FROM personnel_assignments pa INNER JOIN units un ON un.id = pa.unit_id AND un.tenant_id = ? WHERE pa.status = 'active' AND (pa.ended_at IS NULL OR pa.ended_at > ?)", [$tenantId, substr($now, 0, 10)]);
        $collect('jobs', 'SELECT user_id, personnel_job_role_id AS ref_id FROM personnel_profile_job_roles WHERE tenant_id = ?', [$tenantId]);
        $collect('groups', 'SELECT m.user_id, m.group_id AS ref_id FROM training_group_members m INNER JOIN training_groups g ON g.id = m.group_id AND g.tenant_id = ?', [$tenantId]);
        foreach ($this->rows('SELECT id, name FROM training_groups WHERE tenant_id = ? ORDER BY name', [$tenantId]) as $row) {
            $out['groupOptions'][(int) $row['id']] = (string) $row['name'];
        }
        foreach (['units', 'jobs', 'groups'] as $bucket) {
            $out[$bucket] = array_map(static fn (array $ids): array => array_values(array_unique($ids)), $out[$bucket]);
        }
        return $out;
    }

    /**
     * Séances du LMS à venir où le membre est désigné encadrant.
     *
     * @return list<array{course_id:int, title:string, slug:string, starts_at:string, label:string, location:string}>
     */
    public function upcomingSessionsFor(int $tenantId, int $userId, int $limit = 6): array
    {
        $rows = $this->rows('SELECT s.course_id, s.starts_at, s.label, s.location, c.title, c.slug FROM training_course_sessions s INNER JOIN training_courses c ON c.id = s.course_id WHERE s.tenant_id = ? AND s.instructor_user_id = ? AND s.starts_at >= ? ORDER BY s.starts_at LIMIT ' . max(1, min(20, $limit)), [$tenantId, $userId, date('Y-m-d H:i:s', time() - 3600)]);
        return array_map(static fn (array $row): array => ['course_id' => (int) $row['course_id'], 'title' => (string) ($row['title'] ?? ''), 'slug' => (string) ($row['slug'] ?? ''),
            'starts_at' => (string) $row['starts_at'], 'label' => (string) ($row['label'] ?? ''), 'location' => (string) ($row['location'] ?? '')], $rows);
    }

    /** @return list<int> */
    private function column(string $sql, array $params): array
    {
        return array_map(static fn (array $row): int => (int) reset($row), $this->rows($sql, $params));
    }

    /** Lecture tolérante : une table absente (ancienne installation) ne bloque jamais l'Academy. */
    private function rows(string $sql, array $params): array
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException) {
            return [];
        }
    }
}
