<?php

declare(strict_types=1);

namespace App\Services\Community;

use App\Core\Database;
use App\Repositories\TenantRepository;
use App\Services\Audit\AuditAction;
use PDO;
use Throwable;

/**
 * Détecte les communautés « orphelines » (données tenant_id sans ligne tenants)
 * et permet de recréer la fiche manquante sans restore complet de la base.
 */
final class TenantRecoveryService
{
    /** @var list<string> */
    private const PROBE_TABLES = [
        'users',
        'roles',
        'user_community_memberships',
        'documents',
        'units',
        'forum_topics',
        'site_settings',
    ];

    /** @var list<string> */
    private const RESTORABLE_COLUMNS = [
        'name',
        'slug',
        'tenant_type',
        'logo_url',
        'settings',
        'owner_user_id',
        'plan_slug',
        'community_code',
        'stripe_customer_id',
        'stripe_subscription_id',
        'paypal_subscription_id',
        'paypal_payer_id',
        'subscription_status',
        'subscription_current_period_end',
        'created_at',
    ];

    public function __construct(
        private ?PDO $pdo = null,
        private ?TenantRepository $tenants = null,
    ) {
        $this->tenants ??= new TenantRepository($this->pdo);
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    /**
     * @return list<int>
     */
    public function listOrphanedTenantIds(): array
    {
        $existing = $this->existingTenantIds();
        $candidates = [];

        foreach (self::PROBE_TABLES as $table) {
            if (!$this->tableExists($table) || !$this->columnExists($table, 'tenant_id')) {
                continue;
            }
            try {
                $stmt = $this->pdo()->query(
                    'SELECT DISTINCT tenant_id FROM `' . $table . '` WHERE tenant_id > 1'
                );
                foreach ($stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [] as $rawId) {
                    $id = (int) $rawId;
                    if ($id > 1) {
                        $candidates[$id] = true;
                    }
                }
            } catch (Throwable) {
                continue;
            }
        }

        $orphans = [];
        foreach (array_keys($candidates) as $id) {
            if (!isset($existing[$id])) {
                $orphans[] = (int) $id;
            }
        }
        sort($orphans, SORT_NUMERIC);

        return $orphans;
    }

    /**
     * @return array{
     *   tenant_id: int,
     *   exists: bool,
     *   table_counts: array<string, int>,
     *   total_rows: int,
     *   identity_hints: array<string, mixed>,
     *   recent_purges: list<array<string, mixed>>
     * }
     */
    public function inspect(int $tenantId): array
    {
        $tenantId = max(0, $tenantId);
        $counts = [];
        $total = 0;
        foreach (self::PROBE_TABLES as $table) {
            $counts[$table] = $this->countRowsForTenant($table, $tenantId);
            $total += $counts[$table];
        }

        return [
            'tenant_id' => $tenantId,
            'exists' => $this->tenants->findById($tenantId) !== null,
            'table_counts' => $counts,
            'total_rows' => $total,
            'identity_hints' => $this->getIdentityHints($tenantId),
            'recent_purges' => $this->listRecentPurgesNearIncident($tenantId),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getIdentityHints(int $tenantId): array
    {
        if ($tenantId < 2 || !$this->tableExists('audit_logs')) {
            return [];
        }

        $hints = [];

        try {
            $stmt = $this->pdo()->prepare(
                'SELECT old_value, new_value, created_at
                 FROM audit_logs
                 WHERE action = ?
                   AND entity_type = ?
                   AND entity_id = ?
                 ORDER BY created_at DESC
                 LIMIT 1'
            );
            $stmt->execute([AuditAction::TENANT_IDENTITY_UPDATED, 'tenant', $tenantId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) {
                $payload = $this->decodeAuditJson((string) ($row['new_value'] ?? ''));
                if ($payload === []) {
                    $payload = $this->decodeAuditJson((string) ($row['old_value'] ?? ''));
                }
                if ($payload !== []) {
                    $hints['audit_identity'] = $payload;
                    $hints['audit_identity_at'] = (string) ($row['created_at'] ?? '');
                }
            }

            $stmtCreated = $this->pdo()->prepare(
                'SELECT new_value, created_at
                 FROM audit_logs
                 WHERE action = ?
                   AND entity_id = ?
                 ORDER BY created_at ASC
                 LIMIT 1'
            );
            $stmtCreated->execute([AuditAction::TENANT_CREATED, $tenantId]);
            $created = $stmtCreated->fetch(PDO::FETCH_ASSOC);
            if (is_array($created)) {
                $payload = $this->decodeAuditJson((string) ($created['new_value'] ?? ''));
                if ($payload !== []) {
                    $hints['audit_created'] = $payload;
                    $hints['audit_created_at'] = (string) ($created['created_at'] ?? '');
                }
            }
        } catch (Throwable) {
            return $hints;
        }

        return $hints;
    }

    /**
     * Extrait une ligne tenants depuis un dump SQL (INSERT ou fragment VALUES).
     *
     * @return array<string, mixed>|null
     */
    public function parseTenantRowFromSqlDump(string $content, int $tenantId): ?array
    {
        if ($tenantId < 2 || trim($content) === '') {
            return null;
        }

        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $blocks = [];
        if (preg_match_all(
            '/INSERT\s+INTO\s+[`"]?tenants[`"]?\s*(?:\(([^)]+)\))?\s*VALUES\s*(.+?);/is',
            $content,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $match) {
                $blocks[] = [
                    'columns' => isset($match[1]) ? $this->splitSqlColumnList((string) $match[1]) : [],
                    'values_blob' => (string) ($match[2] ?? ''),
                ];
            }
        }

        foreach ($blocks as $block) {
            foreach ($this->splitSqlValueTuples($block['values_blob']) as $tuple) {
                $parsed = $this->mapSqlTupleToAssoc($block['columns'], $tuple);
                if ((int) ($parsed['id'] ?? 0) === $tenantId) {
                    return $this->normalizeRestoreInput($parsed);
                }
            }
        }

        // Repli : tuple VALUES commençant par l’identifiant recherché.
        $pattern = '/\(\s*' . preg_quote((string) $tenantId, '/') . '\s*,/s';
        if (preg_match($pattern, $content, $m, PREG_OFFSET_CAPTURE)) {
            $start = (int) ($m[0][1] ?? -1);
            if ($start >= 0) {
                $tuple = $this->extractParenthesizedTuple($content, $start);
                if ($tuple !== null) {
                    $values = $this->parseSqlValueTuple($tuple);
                    if ((int) ($values[0] ?? 0) === $tenantId && count($values) >= 3) {
                        return $this->normalizeRestoreInput([
                            'id' => $tenantId,
                            'name' => (string) ($values[1] ?? ''),
                            'slug' => (string) ($values[2] ?? ''),
                        ]);
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, normalized: array<string, mixed>, errors: list<string>, warnings: list<string>}
     */
    public function validateRestore(array $input): array
    {
        $normalized = $this->normalizeRestoreInput($input);
        $errors = [];
        $warnings = [];

        $tenantId = (int) ($normalized['id'] ?? 0);
        if ($tenantId < 2) {
            $errors[] = 'Identifiant de communauté invalide (minimum 2).';
        } elseif ($this->tenants->findById($tenantId) !== null) {
            $errors[] = 'Une communauté avec cet identifiant existe déjà.';
        }

        $name = trim((string) ($normalized['name'] ?? ''));
        if ($name === '') {
            $errors[] = 'Le nom de la communauté est obligatoire.';
        }

        $slug = strtolower(trim((string) ($normalized['slug'] ?? '')));
        if ($slug === '') {
            $errors[] = 'L’adresse courte (slug) est obligatoire.';
        } elseif (!TenantSlugService::isValidFormat($slug)) {
            $errors[] = 'Format de slug invalide.';
        } elseif (TenantSlugService::isReserved($slug)) {
            $errors[] = 'Ce slug est réservé par la plateforme.';
        } elseif ($this->tenants->findBySlug($slug) !== null) {
            $errors[] = 'Ce slug est déjà utilisé par une autre communauté.';
        }

        $tenantType = TenantTypeConfig::normalizeType((string) ($normalized['tenant_type'] ?? 'full'));
        $normalized['tenant_type'] = $tenantType;

        $ownerUserId = $normalized['owner_user_id'] ?? null;
        if ($ownerUserId !== null && $ownerUserId !== '') {
            $ownerUserId = (int) $ownerUserId;
            if ($ownerUserId > 0 && !$this->userExists($ownerUserId)) {
                $warnings[] = 'Le propriétaire indiqué (#' . $ownerUserId . ') est introuvable : la communauté sera créée sans propriétaire.';
                $normalized['owner_user_id'] = null;
            } else {
                $normalized['owner_user_id'] = $ownerUserId > 0 ? $ownerUserId : null;
            }
        } else {
            $normalized['owner_user_id'] = null;
        }

        $communityCode = strtoupper(trim((string) ($normalized['community_code'] ?? '')));
        if ($communityCode !== '') {
            if ($this->tenants->isCommunityCodeTaken($communityCode, $tenantId)) {
                $errors[] = 'Ce code communauté est déjà pris.';
            }
            $normalized['community_code'] = $communityCode;
        } else {
            $normalized['community_code'] = null;
        }

        $settings = $normalized['settings'] ?? null;
        if (is_string($settings) && trim($settings) !== '') {
            $decoded = json_decode($settings, true);
            if (!is_array($decoded)) {
                $errors[] = 'Le JSON settings est invalide.';
            } else {
                $normalized['settings'] = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        } elseif (is_array($settings)) {
            $normalized['settings'] = json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            $normalized['settings'] = null;
        }

        $normalized['plan_slug'] = trim((string) ($normalized['plan_slug'] ?? 'free')) ?: 'free';
        $normalized['subscription_status'] = trim((string) ($normalized['subscription_status'] ?? 'none')) ?: 'none';

        return [
            'ok' => $errors === [],
            'normalized' => $normalized,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, tenant_id: int, errors: list<string>}
     */
    public function restore(array $input): array
    {
        $validation = $this->validateRestore($input);
        if (!$validation['ok']) {
            return [
                'ok' => false,
                'tenant_id' => (int) ($input['id'] ?? 0),
                'errors' => $validation['errors'],
            ];
        }

        $row = $validation['normalized'];
        $tenantId = (int) ($row['id'] ?? 0);

        try {
            $columns = ['id'];
            $values = [$tenantId];
            $placeholders = ['?'];

            foreach (self::RESTORABLE_COLUMNS as $column) {
                if (!$this->columnExists('tenants', $column)) {
                    continue;
                }
                if (!array_key_exists($column, $row)) {
                    continue;
                }
                $value = $row[$column];
                if ($value === null || $value === '') {
                    if (in_array($column, ['name', 'slug', 'tenant_type', 'plan_slug', 'subscription_status'], true)) {
                        continue;
                    }
                    if ($column === 'settings' || $column === 'logo_url' || str_contains($column, '_id') || $column === 'community_code') {
                        $columns[] = $column;
                        $values[] = null;
                        $placeholders[] = '?';
                    }
                    continue;
                }
                $columns[] = $column;
                $values[] = $value;
                $placeholders[] = '?';
            }

            if (!$this->columnExists('tenants', 'updated_at')) {
                // ignore
            } else {
                $columns[] = 'updated_at';
                $values[] = date('Y-m-d H:i:s');
                $placeholders[] = '?';
            }

            $sql = 'INSERT INTO tenants (`' . implode('`, `', $columns) . '`) VALUES (' . implode(', ', $placeholders) . ')';
            $stmt = $this->pdo()->prepare($sql);
            $stmt->execute($values);

            $this->bumpAutoIncrementIfNeeded($tenantId);

            return ['ok' => true, 'tenant_id' => $tenantId, 'errors' => []];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'tenant_id' => $tenantId,
                'errors' => ['Insertion impossible : ' . $e->getMessage()],
            ];
        }
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function normalizeRestoreInput(array $input): array
    {
        $out = [];
        foreach ($input as $key => $value) {
            $out[(string) $key] = $value;
        }
        if (isset($out['id'])) {
            $out['id'] = (int) $out['id'];
        }
        if (isset($out['owner_user_id']) && $out['owner_user_id'] !== null && $out['owner_user_id'] !== '') {
            $out['owner_user_id'] = (int) $out['owner_user_id'];
        }
        if (isset($out['name'])) {
            $out['name'] = trim((string) $out['name']);
        }
        if (isset($out['slug'])) {
            $out['slug'] = strtolower(trim((string) $out['slug']));
        }

        return $out;
    }

    /**
     * @return array<int, true>
     */
    private function existingTenantIds(): array
    {
        $map = [];
        try {
            $stmt = $this->pdo()->query('SELECT id FROM tenants');
            foreach ($stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [] as $rawId) {
                $map[(int) $rawId] = true;
            }
        } catch (Throwable) {
        }

        return $map;
    }

    private function countRowsForTenant(string $table, int $tenantId): int
    {
        if ($tenantId < 1 || !$this->tableExists($table) || !$this->columnExists($table, 'tenant_id')) {
            return 0;
        }
        try {
            $stmt = $this->pdo()->prepare('SELECT COUNT(*) FROM `' . $table . '` WHERE tenant_id = ?');
            $stmt->execute([$tenantId]);

            return (int) $stmt->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }

    private function tableExists(string $table): bool
    {
        if (!preg_match('/^[a-z0-9_]+$/', $table)) {
            return false;
        }
        try {
            $stmt = $this->pdo()->prepare(
                'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
            );
            $stmt->execute([$table]);

            return (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        if (!preg_match('/^[a-z0-9_]+$/', $table) || !preg_match('/^[a-z0-9_]+$/', $column)) {
            return false;
        }
        try {
            $stmt = $this->pdo()->prepare(
                'SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
            );
            $stmt->execute([$table, $column]);

            return (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }

    private function userExists(int $userId): bool
    {
        try {
            $stmt = $this->pdo()->prepare('SELECT 1 FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$userId]);

            return (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }

    private function bumpAutoIncrementIfNeeded(int $tenantId): void
    {
        try {
            $stmt = $this->pdo()->query('SELECT COALESCE(MAX(id), 0) FROM tenants');
            $maxId = (int) ($stmt ? $stmt->fetchColumn() : 0);
            if ($maxId >= $tenantId) {
                $this->pdo()->exec('ALTER TABLE tenants AUTO_INCREMENT = ' . ($maxId + 1));
            }
        } catch (Throwable) {
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listRecentPurgesNearIncident(int $tenantId): array
    {
        if (!$this->tableExists('audit_logs')) {
            return [];
        }
        try {
            $stmt = $this->pdo()->prepare(
                'SELECT id, created_at, tenant_id, user_id, entity_id, new_value
                 FROM audit_logs
                 WHERE action = ?
                   AND created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
                 ORDER BY created_at DESC
                 LIMIT 20'
            );
            $stmt->execute([AuditAction::USER_PURGED]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return is_array($rows) ? $rows : [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeAuditJson(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return list<string>
     */
    private function splitSqlColumnList(string $raw): array
    {
        $parts = array_map(
            static fn (string $p): string => trim(str_replace(['`', '"'], '', $p)),
            explode(',', $raw)
        );

        return array_values(array_filter($parts, static fn (string $p): bool => $p !== ''));
    }

    /**
     * @return list<string>
     */
    private function splitSqlValueTuples(string $blob): array
    {
        $tuples = [];
        $len = strlen($blob);
        $depth = 0;
        $start = null;
        $inString = false;
        $escape = false;

        for ($i = 0; $i < $len; $i++) {
            $ch = $blob[$i];
            if ($escape) {
                $escape = false;
                continue;
            }
            if ($ch === '\\') {
                $escape = true;
                continue;
            }
            if ($ch === "'" && !$inString) {
                $inString = true;
                continue;
            }
            if ($ch === "'" && $inString) {
                $inString = false;
                continue;
            }
            if ($inString) {
                continue;
            }
            if ($ch === '(') {
                if ($depth === 0) {
                    $start = $i;
                }
                $depth++;
                continue;
            }
            if ($ch === ')') {
                $depth--;
                if ($depth === 0 && $start !== null) {
                    $tuples[] = substr($blob, $start, $i - $start + 1);
                    $start = null;
                }
            }
        }

        return $tuples;
    }

    /**
     * @param list<string> $columns
     * @return array<string, mixed>
     */
    private function mapSqlTupleToAssoc(array $columns, string $tuple): array
    {
        $values = $this->parseSqlValueTuple($tuple);
        if ($columns === []) {
            $defaults = ['id', 'name', 'slug', 'tenant_type', 'logo_url', 'settings', 'created_at', 'updated_at'];
            $columns = array_slice($defaults, 0, count($values));
        }
        $out = [];
        foreach ($columns as $idx => $column) {
            if (!array_key_exists($idx, $values)) {
                break;
            }
            $out[$column] = $values[$idx];
        }

        return $out;
    }

    /**
     * @return list<mixed>
     */
    private function parseSqlValueTuple(string $tuple): array
    {
        $inner = trim($tuple);
        if (str_starts_with($inner, '(')) {
            $inner = substr($inner, 1, -1);
        }

        $values = [];
        $buf = '';
        $inString = false;
        $escape = false;
        $len = strlen($inner);

        for ($i = 0; $i < $len; $i++) {
            $ch = $inner[$i];
            if ($escape) {
                $buf .= $ch;
                $escape = false;
                continue;
            }
            if ($ch === '\\') {
                $escape = true;
                continue;
            }
            if ($ch === "'") {
                $inString = !$inString;
                continue;
            }
            if ($ch === ',' && !$inString) {
                $values[] = $this->normalizeSqlScalar(trim($buf));
                $buf = '';
                continue;
            }
            $buf .= $ch;
        }
        if ($buf !== '' || ($len > 0 && str_ends_with($inner, ','))) {
            $values[] = $this->normalizeSqlScalar(trim($buf));
        }

        return $values;
    }

    private function normalizeSqlScalar(string $raw): mixed
    {
        if ($raw === 'NULL' || $raw === 'null') {
            return null;
        }
        if (str_starts_with($raw, "'") && str_ends_with($raw, "'")) {
            return stripcslashes(substr($raw, 1, -1));
        }
        if (is_numeric($raw)) {
            return str_contains($raw, '.') ? (float) $raw : (int) $raw;
        }

        return $raw;
    }

    private function extractParenthesizedTuple(string $content, int $startPos): ?string
    {
        $len = strlen($content);
        $depth = 0;
        $inString = false;
        $escape = false;
        for ($i = $startPos; $i < $len; $i++) {
            $ch = $content[$i];
            if ($escape) {
                $escape = false;
                continue;
            }
            if ($ch === '\\') {
                $escape = true;
                continue;
            }
            if ($ch === "'") {
                $inString = !$inString;
                continue;
            }
            if ($inString) {
                continue;
            }
            if ($ch === '(') {
                $depth++;
            } elseif ($ch === ')') {
                $depth--;
                if ($depth === 0) {
                    return substr($content, $startPos, $i - $startPos + 1);
                }
            }
        }

        return null;
    }
}
