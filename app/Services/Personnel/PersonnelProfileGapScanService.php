<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Core\Database;
use App\Repositories\UserRepository;
use PDO;

/**
 * Membres actifs dont le dossier a un trou lisible pour le commandement (synthèse back-office).
 */
final class PersonnelProfileGapScanService
{
    public const SHOW_LIMIT = 80;

    public const FETCH_CAP = 400;

    /** @var list<string> */
    public const ISSUE_KEYS = ['function', 'rank', 'role', 'operator_image', 'absence'];

    /** @var array<string, string> */
    public const ISSUE_LABELS = [
        'function' => 'Fonction',
        'rank' => 'Grade',
        'role' => 'Rôle',
        'operator_image' => 'Image opérateur',
        'absence' => 'Absence',
    ];

    private PDO $pdo;

    /** @var array<string, bool> */
    private array $tableExistsCache = [];

    /** @var array<string, bool> */
    private array $columnExistsCache = [];

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
    }

    /**
     * @return array{
     *   rows: list<array<string, mixed>>,
     *   total: int,
     *   shown: int,
     *   truncated: bool,
     *   counts: array{function: int, rank: int, role: int, operator_image: int, absence: int},
     *   error: string|null
     * }
     */
    public function listForTenant(int $tenantId, int $showLimit = self::SHOW_LIMIT): array
    {
        $emptyCounts = [
            'function' => 0,
            'rank' => 0,
            'role' => 0,
            'operator_image' => 0,
            'absence' => 0,
        ];
        $empty = [
            'rows' => [],
            'total' => 0,
            'shown' => 0,
            'truncated' => false,
            'counts' => $emptyCounts,
            'error' => null,
        ];
        if ($tenantId < 1) {
            return $empty;
        }

        $showLimit = max(10, min(200, $showLimit));

        try {
            $sql = $this->buildScanSql();
            $noise = $this->noiseExclusion();
            $stmt = $this->pdo->prepare(
                $sql['select'] . '
                 WHERE u.tenant_id = ? AND u.status = \'active\'
                   AND ' . $noise['sql'] . '
                 ORDER BY u.display_name ASC
                 LIMIT ?'
            );
            $stmt->execute(array_merge([$tenantId], $noise['params'], [self::FETCH_CAP]));
            $raw = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            $empty['error'] = 'La liste des profils à compléter n’est pas disponible pour le moment.';

            return $empty;
        }

        $classified = [];
        $counts = $emptyCounts;
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $issues = self::classifyRow($row);
            if ($issues['issue_count'] < 1) {
                continue;
            }
            foreach (self::ISSUE_KEYS as $key) {
                if (!empty($issues['missing'][$key])) {
                    $counts[$key]++;
                }
            }
            $classified[] = $this->presentRow($row, $issues);
        }

        usort($classified, static function (array $a, array $b): int {
            $c = ((int) ($b['issue_count'] ?? 0)) <=> ((int) ($a['issue_count'] ?? 0));
            if ($c !== 0) {
                return $c;
            }

            return strcasecmp((string) ($a['display_name'] ?? ''), (string) ($b['display_name'] ?? ''));
        });

        $total = count($classified);
        $shownRows = array_slice($classified, 0, $showLimit);

        return [
            'rows' => $shownRows,
            'total' => $total,
            'shown' => count($shownRows),
            'truncated' => count($raw) >= self::FETCH_CAP,
            'counts' => $counts,
            'error' => null,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array{
     *   missing: array{function: bool, rank: bool, role: bool, operator_image: bool, absence: bool},
     *   issue_count: int,
     *   issue_keys: list<string>,
     *   function_label: string,
     *   rank_label: string,
     *   role_label: string
     * }
     */
    public static function classifyRow(array $row): array
    {
        $functionLabel = self::firstFilled([
            $row['job_role_name'] ?? null,
            $row['primary_role'] ?? null,
        ]);
        $rankLabel = self::firstFilled([
            $row['rank_display_override'] ?? null,
            $row['rank_display'] ?? null,
            $row['grade_short'] ?? null,
            $row['grade_long'] ?? null,
        ]);
        $roleLabel = self::firstFilled([
            $row['community_role_name'] ?? null,
        ]);

        $missingFunction = $functionLabel === '';
        $hasGradeId = (int) ($row['grade_id'] ?? 0) > 0;
        $missingRank = !$hasGradeId && $rankLabel === '';
        $hasRoleId = (int) ($row['role_id'] ?? 0) > 0;
        $hasTenantRole = (int) ($row['has_tenant_role'] ?? 0) > 0;
        $missingRole = !$hasRoleId && !$hasTenantRole;
        $missingOperatorImage = self::isBlankLabel($row['character_portrait_path'] ?? null);
        $hasActiveAbsence = (int) ($row['has_active_absence'] ?? 0) === 1;
        $readiness = (int) ($row['readiness_score'] ?? 0);
        $missingAbsence = !$hasActiveAbsence && $readiness <= 0;

        $missing = [
            'function' => $missingFunction,
            'rank' => $missingRank,
            'role' => $missingRole,
            'operator_image' => $missingOperatorImage,
            'absence' => $missingAbsence,
        ];
        $keys = [];
        foreach (self::ISSUE_KEYS as $key) {
            if ($missing[$key]) {
                $keys[] = $key;
            }
        }

        return [
            'missing' => $missing,
            'issue_count' => count($keys),
            'issue_keys' => $keys,
            'function_label' => $functionLabel,
            'rank_label' => $rankLabel,
            'role_label' => $roleLabel,
        ];
    }

    public static function isBlankLabel(mixed $value): bool
    {
        $v = trim((string) $value);
        if ($v === '' || $v === '—' || $v === '-' || $v === '–') {
            return true;
        }
        $norm = mb_strtolower($v);
        $norm = str_replace(['—', '–'], '-', $norm);
        $norm = trim($norm, " \t\n\r\0\x0B-");

        return in_array($norm, [
            'non indiqué',
            'non indique',
            'non indiquée',
            'non indiquee',
            'non renseigné',
            'non renseigne',
            'non renseignée',
            'non renseignee',
        ], true);
    }

    /**
     * @param list<mixed> $values
     */
    public static function firstFilled(array $values): string
    {
        foreach ($values as $value) {
            if (!self::isBlankLabel($value)) {
                return trim((string) $value);
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $issues
     * @return array<string, mixed>
     */
    private function presentRow(array $row, array $issues): array
    {
        $id = (int) ($row['id'] ?? 0);
        $displayName = trim((string) ($row['display_name'] ?? ''));
        if ($displayName === '') {
            $displayName = trim((string) ($row['callsign'] ?? ''));
        }
        if ($displayName === '') {
            $displayName = 'Membre';
        }
        $portraitRaw = trim((string) ($row['character_portrait_path'] ?? ''));
        $avatarRaw = trim((string) ($row['avatar_url'] ?? ''));
        $portraitUrl = '';
        if ($portraitRaw !== '' && function_exists('user_media_public_url')) {
            $portraitUrl = (string) (user_media_public_url($portraitRaw) ?? '');
        } elseif ($portraitRaw !== '') {
            $portraitUrl = $portraitRaw;
        }
        $avatarUrl = '';
        if ($avatarRaw !== '' && function_exists('user_media_public_url')) {
            $avatarUrl = (string) (user_media_public_url($avatarRaw) ?? '');
        } elseif ($avatarRaw !== '') {
            $avatarUrl = $avatarRaw;
        }

        $missing = is_array($issues['missing'] ?? null) ? $issues['missing'] : [];

        return [
            'id' => $id,
            'display_name' => $displayName,
            'callsign' => trim((string) ($row['callsign'] ?? '')),
            'email' => trim((string) ($row['email'] ?? '')),
            'avatar_url' => $avatarUrl,
            'portrait_url' => $portraitUrl,
            'fiche_url' => $id > 0 ? url('back-office/users/' . $id) : '',
            'missing_function' => !empty($missing['function']),
            'missing_rank' => !empty($missing['rank']),
            'missing_role' => !empty($missing['role']),
            'missing_operator_image' => !empty($missing['operator_image']),
            'missing_absence' => !empty($missing['absence']),
            'function_label' => (string) ($issues['function_label'] ?? ''),
            'rank_label' => (string) ($issues['rank_label'] ?? ''),
            'role_label' => (string) ($issues['role_label'] ?? ''),
            'issue_keys' => is_array($issues['issue_keys'] ?? null) ? $issues['issue_keys'] : [],
            'issue_count' => (int) ($issues['issue_count'] ?? 0),
            'has_active_absence' => (int) ($row['has_active_absence'] ?? 0) === 1,
        ];
    }

    /**
     * @return array{select: string}
     */
    private function buildScanSql(): array
    {
        $grade = $this->gradesJoin();
        $job = $this->jobRoleJoin();
        $portraitSelect = $this->columnExists('personnel_profiles', 'character_portrait_path')
            ? 'pp.character_portrait_path'
            : "'' AS character_portrait_path";
        $rankDisplaySelect = $this->columnExists('personnel_profiles', 'rank_display')
            ? 'pp.rank_display'
            : "'' AS rank_display";
        $rankOverrideSelect = $this->columnExists('personnel_profiles', 'rank_display_override')
            ? 'pp.rank_display_override'
            : "'' AS rank_display_override";
        $primaryRoleSelect = $this->columnExists('personnel_profiles', 'primary_role')
            ? 'pp.primary_role'
            : "'' AS primary_role";
        $readinessSelect = $this->columnExists('personnel_profiles', 'readiness_score')
            ? 'pp.readiness_score'
            : '0 AS readiness_score';
        $gradeIdSelect = $this->columnExists('users', 'grade_id')
            ? 'u.grade_id'
            : 'NULL AS grade_id';
        $roleIdSelect = $this->columnExists('users', 'role_id')
            ? 'u.role_id'
            : 'NULL AS role_id';
        $roleNameSelect = $this->tableExists('roles')
            ? 'r.name AS community_role_name'
            : "'' AS community_role_name";
        $roleJoin = $this->tableExists('roles') && $this->columnExists('users', 'role_id')
            ? 'LEFT JOIN roles r ON r.id = u.role_id'
            : '';
        $hasTenantRoleSelect = $this->tableExists('tenant_user_roles')
            ? 'CASE WHEN EXISTS (
                    SELECT 1 FROM tenant_user_roles tur
                    WHERE tur.user_id = u.id AND tur.tenant_id = u.tenant_id
               ) THEN 1 ELSE 0 END AS has_tenant_role'
            : '0 AS has_tenant_role';
        $absenceSelect = $this->tableExists('personnel_absences')
            ? 'CASE WHEN EXISTS (
                    SELECT 1 FROM personnel_absences pa
                    WHERE pa.user_id = u.id AND pa.tenant_id = u.tenant_id
                      AND pa.status = \'active\'
                      AND pa.starts_on <= CURDATE()
                      AND (pa.ends_on IS NULL OR pa.ends_on >= CURDATE())
               ) THEN 1 ELSE 0 END AS has_active_absence'
            : '0 AS has_active_absence';

        $select = 'SELECT u.id, u.display_name, u.callsign, u.email, u.avatar_url,
                       ' . $gradeIdSelect . ',
                       ' . $roleIdSelect . ',
                       ' . $roleNameSelect . ',
                       ' . $grade['select'] . ',
                       ' . $rankDisplaySelect . ',
                       ' . $rankOverrideSelect . ',
                       ' . $primaryRoleSelect . ',
                       ' . $portraitSelect . ',
                       ' . $readinessSelect . ',
                       ' . $job['select'] . ',
                       ' . $hasTenantRoleSelect . ',
                       ' . $absenceSelect . '
                FROM users u
                LEFT JOIN personnel_profiles pp ON pp.user_id = u.id
                ' . $roleJoin . '
                ' . $grade['join'] . '
                ' . $job['join'];

        return ['select' => $select];
    }

    /**
     * @return array{join: string, select: string}
     */
    private function gradesJoin(): array
    {
        if (!$this->tableExists('grades') || !$this->columnExists('users', 'grade_id')) {
            return [
                'join' => '',
                'select' => "'' AS grade_short, '' AS grade_long",
            ];
        }
        $hasLabelLong = $this->columnExists('grades', 'label_long');
        $hasTenantId = $this->columnExists('grades', 'tenant_id');
        $join = $hasTenantId
            ? 'LEFT JOIN grades g ON g.id = u.grade_id AND g.tenant_id = u.tenant_id'
            : 'LEFT JOIN grades g ON g.id = u.grade_id';
        if ($hasLabelLong) {
            return [
                'join' => $join,
                'select' => 'g.label_short AS grade_short, g.label_long AS grade_long',
            ];
        }

        return [
            'join' => $join,
            'select' => 'g.short_name AS grade_short, g.name AS grade_long',
        ];
    }

    /**
     * @return array{join: string, select: string}
     */
    private function jobRoleJoin(): array
    {
        if (!$this->tableExists('personnel_profile_job_roles') || !$this->tableExists('personnel_job_roles')) {
            return [
                'join' => '',
                'select' => "'' AS job_role_name",
            ];
        }

        return [
            'join' => 'LEFT JOIN personnel_profile_job_roles pjrole ON pjrole.id = (
                    SELECT pj2.id FROM personnel_profile_job_roles pj2
                    WHERE pj2.user_id = u.id AND pj2.tenant_id = u.tenant_id
                    ORDER BY pj2.is_primary DESC, pj2.sort_order ASC, pj2.id ASC
                    LIMIT 1
                )
                LEFT JOIN personnel_job_roles pjr ON pjr.id = pjrole.personnel_job_role_id AND pjr.tenant_id = u.tenant_id',
            'select' => "TRIM(CONCAT(COALESCE(pjr.name, ''), IF(pjrole.role_detail IS NOT NULL AND pjrole.role_detail <> '', CONCAT(' — ', pjrole.role_detail), ''))) AS job_role_name",
        ];
    }

    /**
     * @return array{sql: string, params: list<mixed>}
     */
    private function noiseExclusion(): array
    {
        $fragments = [];
        $params = [];
        if ($this->columnExists('users', 'is_service_account')) {
            $fragments[] = '(u.is_service_account IS NULL OR u.is_service_account = 0)';
        }
        $fragments[] = 'LOWER(TRIM(u.email)) <> ?';
        $params[] = strtolower(UserRepository::SYSTEM_MODERATOR_EMAIL);
        $fragments[] = 'LOWER(TRIM(u.email)) NOT LIKE ?';
        $params[] = 'system.%@internal.local';
        $fragments[] = 'LOWER(TRIM(u.email)) NOT LIKE ?';
        $params[] = 'history.%@internal.local';
        $fragments[] = 'LOWER(u.email) NOT LIKE ?';
        $params[] = '%@demo.local';

        return ['sql' => '(' . implode(' AND ', $fragments) . ')', 'params' => $params];
    }

    private function tableExists(string $table): bool
    {
        if (array_key_exists($table, $this->tableExistsCache)) {
            return $this->tableExistsCache[$table];
        }
        try {
            $stmt = $this->pdo->prepare(
                'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
            );
            $stmt->execute([$table]);
            $this->tableExistsCache[$table] = (bool) $stmt->fetchColumn();
        } catch (\Throwable) {
            $this->tableExistsCache[$table] = false;
        }

        return $this->tableExistsCache[$table];
    }

    private function columnExists(string $table, string $column): bool
    {
        $key = $table . '.' . $column;
        if (array_key_exists($key, $this->columnExistsCache)) {
            return $this->columnExistsCache[$key];
        }
        try {
            $stmt = $this->pdo->prepare(
                'SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
            );
            $stmt->execute([$table, $column]);
            $this->columnExistsCache[$key] = (bool) $stmt->fetchColumn();
        } catch (\Throwable) {
            $this->columnExistsCache[$key] = false;
        }

        return $this->columnExistsCache[$key];
    }
}
