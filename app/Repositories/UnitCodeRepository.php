<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\UnitCodeBuilder;
use PDO;
use Throwable;

/**
 * Tables tenant_unit_codes et unit_codes (codes unité lisibles + identifiants administratifs).
 *
 * Voie officielle : étape unit_codes de la file active, lancée par run-migrations.php
 * (docs/technical/migrations-active-queue.md). ensureTables() reste un filet de sécurité idempotent
 * (CREATE TABLE IF NOT EXISTS du seul fichier MIGRATION_FILE). À la création, chaque communauté existante
 * reçoit son numéro administratif dans l'ordre de création (déterministe).
 */
class UnitCodeRepository
{
    public const MIGRATION_FILE = 'migrations/2026_11_01_002_unit_codes.sql';

    /** @var array<string, bool> */
    private static array $ready = [];

    public function __construct(private ?PDO $pdo = null)
    {
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    private function cacheKey(): string
    {
        return (string) spl_object_id($this->pdo());
    }

    public static function resetCache(): void
    {
        self::$ready = [];
    }

    public function displayPrefix(int $tenantId): string
    {
        try {
            $st = $this->pdo()->prepare('SELECT settings FROM tenants WHERE id = ?');
            $st->execute([$tenantId]);
            $settings = json_decode((string) $st->fetchColumn(), true);
            $raw = $settings['unit_codes']['display_prefix'] ?? '';
            return UnitCodeBuilder::normalizeInput((string) $raw, 2, 10) ?? '';
        } catch (Throwable) {
            return '';
        }
    }

    public function saveDisplayPrefix(int $tenantId, string $prefix): void
    {
        // JSON_SET preserves unrelated settings, including concurrent edits.
        $st = $this->pdo()->prepare("UPDATE tenants SET settings = JSON_SET(COALESCE(NULLIF(settings, ''), '{}'), '$.unit_codes', JSON_SET(COALESCE(JSON_EXTRACT(NULLIF(settings, ''), '$.unit_codes'), JSON_OBJECT()), '$.display_prefix', ?)) WHERE id = ?");
        $st->execute([$prefix, $tenantId]);
    }

    public function tablesExist(): bool
    {
        $key = $this->cacheKey();
        if (isset(self::$ready[$key])) {
            return self::$ready[$key];
        }
        try {
            $this->pdo()->query('SELECT 1 FROM tenant_unit_codes LIMIT 1');
            $this->pdo()->query('SELECT 1 FROM unit_codes LIMIT 1');
            $ok = true;
        } catch (Throwable) {
            $ok = false;
        }
        if ($ok) {
            self::$ready[$key] = true;
        }

        return $ok;
    }

    /** Crée les tables si besoin (idempotent) puis numérote les communautés existantes. */
    public function ensureTables(): bool
    {
        if ($this->tablesExist()) {
            return true;
        }
        $sql = @file_get_contents(dirname(__DIR__, 2) . '/' . self::MIGRATION_FILE);
        if (!is_string($sql) || trim($sql) === '') {
            return false;
        }
        foreach (self::statements($sql) as $statement) {
            try {
                $this->pdo()->exec($statement);
            } catch (Throwable $e) {
                error_log('[unit_codes] création des tables impossible : ' . $e->getMessage());

                return false;
            }
        }
        if (!$this->tablesExist()) {
            error_log('[unit_codes] tables toujours absentes après création.');

            return false;
        }
        $this->backfillTenants();

        return true;
    }

    /**
     * Découpe un fichier SQL en instructions. Un « ; » dans un texte entre guillemets
     * (commentaire de colonne…) ne coupe pas l'instruction : un découpage naïf sur « ; »
     * empêchait la création des tables en production (2026-11-01).
     *
     * @return list<string>
     */
    public static function statements(string $sql): array
    {
        $out = [];
        $buf = '';
        $quote = '';
        $len = strlen($sql);
        for ($i = 0; $i < $len; $i++) {
            $c = $sql[$i];
            if ($quote !== '') {
                $buf .= $c;
                if ($c === '\\' && $quote !== '`' && $i + 1 < $len) {
                    $buf .= $sql[++$i];
                } elseif ($c === $quote) {
                    $quote = '';
                }
                continue;
            }
            if ($c === '-' && ($sql[$i + 1] ?? '') === '-') {
                $eol = strpos($sql, "\n", $i);
                $i = $eol === false ? $len : $eol;
                $buf .= "\n";
                continue;
            }
            if ($c === "'" || $c === '"' || $c === '`') {
                $quote = $c;
            }
            if ($c === ';') {
                $out[] = trim($buf);
                $buf = '';
                continue;
            }
            $buf .= $c;
        }
        $out[] = trim($buf);

        return array_values(array_filter($out, static fn (string $s): bool => $s !== ''));
    }

    /** Numéros des communautés existantes : 1, 2, 3… dans l'ordre de création. */
    private function backfillTenants(): void
    {
        try {
            $rows = $this->pdo()->query('SELECT id, name, slug FROM tenants ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return;
        }
        foreach ($rows as $row) {
            $this->ensureTenant((int) $row['id'], (string) ($row['name'] ?? ''), (string) ($row['slug'] ?? ''));
        }
    }

    /**
     * Ligne de la communauté, créée au premier passage (code proposé + numéro suivant).
     *
     * @return array{tenant_id: int, community_code: string, admin_number: int, next_unit_number: int, is_custom: bool}|null
     */
    public function ensureTenant(int $tenantId, ?string $name = null, ?string $slug = null): ?array
    {
        if ($tenantId < 1) {
            return null;
        }
        $row = $this->findTenant($tenantId);
        if ($row !== null) {
            return $row;
        }
        if ($name === null) {
            $st = $this->pdo()->prepare('SELECT name, slug FROM tenants WHERE id = ? LIMIT 1');
            $st->execute([$tenantId]);
            $t = $st->fetch(PDO::FETCH_ASSOC);
            if (!is_array($t)) {
                return null;
            }
            $name = (string) ($t['name'] ?? '');
            $slug = (string) ($t['slug'] ?? '');
        }
        $base = UnitCodeBuilder::communityCodeFromName($name, (string) $slug);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $code = UnitCodeBuilder::uniqueVariant($base, fn (string $c): bool => $this->communityCodeTaken($c, $tenantId));
            $number = (int) $this->pdo()->query('SELECT COALESCE(MAX(admin_number), 0) FROM tenant_unit_codes')->fetchColumn() + 1;
            try {
                $st = $this->pdo()->prepare(
                    'INSERT INTO tenant_unit_codes (tenant_id, community_code, admin_number, next_unit_number, is_custom, updated_at)
                     VALUES (?, ?, ?, 1, 0, CURRENT_TIMESTAMP)'
                );
                $st->execute([$tenantId, $code, $number]);
                break;
            } catch (Throwable) {
                // Course avec une autre requête : on relit, puis on retente avec les valeurs suivantes.
                $row = $this->findTenant($tenantId);
                if ($row !== null) {
                    return $row;
                }
            }
        }

        return $this->findTenant($tenantId);
    }

    /** @return array{tenant_id: int, community_code: string, admin_number: int, next_unit_number: int, is_custom: bool}|null */
    public function findTenant(int $tenantId): ?array
    {
        $st = $this->pdo()->prepare('SELECT tenant_id, community_code, admin_number, next_unit_number, is_custom FROM tenant_unit_codes WHERE tenant_id = ? LIMIT 1');
        $st->execute([$tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        return [
            'tenant_id' => (int) $row['tenant_id'],
            'community_code' => (string) $row['community_code'],
            'admin_number' => (int) $row['admin_number'],
            'next_unit_number' => max(1, (int) $row['next_unit_number']),
            'is_custom' => (int) $row['is_custom'] === 1,
        ];
    }

    public function communityCodeTaken(string $code, int $exceptTenantId = 0): bool
    {
        $st = $this->pdo()->prepare('SELECT tenant_id FROM tenant_unit_codes WHERE UPPER(community_code) = ? AND tenant_id <> ? LIMIT 1');
        $st->execute([strtoupper($code), $exceptTenantId]);

        return $st->fetchColumn() !== false;
    }

    public function updateCommunityCode(int $tenantId, string $code): bool
    {
        $st = $this->pdo()->prepare('UPDATE tenant_unit_codes SET community_code = ?, is_custom = 1, updated_at = CURRENT_TIMESTAMP WHERE tenant_id = ?');
        $st->execute([$code, $tenantId]);

        return $st->rowCount() > 0;
    }

    public function setNextUnitNumber(int $tenantId, int $next): void
    {
        $st = $this->pdo()->prepare('UPDATE tenant_unit_codes SET next_unit_number = ? WHERE tenant_id = ? AND next_unit_number < ?');
        $st->execute([$next, $tenantId, $next]);
    }

    public function tenantName(int $tenantId): string
    {
        $st = $this->pdo()->prepare('SELECT name FROM tenants WHERE id = ? LIMIT 1');
        $st->execute([$tenantId]);

        return (string) ($st->fetchColumn() ?: '');
    }

    /** @return list<array<string, mixed>> */
    public function unitsForTenant(int $tenantId): array
    {
        $st = $this->pdo()->prepare('SELECT id, parent_id, name, code, display_order FROM units WHERE tenant_id = ? ORDER BY id ASC');
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<int, array{segment: string, code_path: string, admin_number: int}> */
    public function codesForTenant(int $tenantId): array
    {
        $st = $this->pdo()->prepare('SELECT unit_id, segment, code_path, admin_number FROM unit_codes WHERE tenant_id = ?');
        $st->execute([$tenantId]);
        $out = [];
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $out[(int) $row['unit_id']] = [
                'segment' => (string) $row['segment'],
                'code_path' => (string) $row['code_path'],
                'admin_number' => (int) $row['admin_number'],
            ];
        }

        return $out;
    }

    /**
     * Écrit les codes calculés en une transaction. Les chemins modifiés passent d'abord par une
     * valeur provisoire unique pour qu'un échange entre deux unités ne heurte pas l'unicité.
     *
     * @param array<int, array{segment: string, code_path: string}> $updates
     * @param array<int, array{segment: string, code_path: string, admin_number: int}> $inserts
     * @param list<int> $deletes
     */
    public function applyChanges(int $tenantId, array $updates, array $inserts, array $deletes): void
    {
        $pdo = $this->pdo();
        $ownTx = !$pdo->inTransaction();
        if ($ownTx) {
            $pdo->beginTransaction();
        }
        try {
            if ($deletes !== []) {
                $ph = implode(',', array_fill(0, count($deletes), '?'));
                $pdo->prepare('DELETE FROM unit_codes WHERE tenant_id = ? AND unit_id IN (' . $ph . ')')->execute(array_merge([$tenantId], $deletes));
            }
            $tmp = $pdo->prepare('UPDATE unit_codes SET code_path = ? WHERE tenant_id = ? AND unit_id = ?');
            foreach (array_keys($updates) as $unitId) {
                $tmp->execute(['~' . $unitId, $tenantId, $unitId]);
            }
            $upd = $pdo->prepare('UPDATE unit_codes SET segment = ?, code_path = ?, updated_at = CURRENT_TIMESTAMP WHERE tenant_id = ? AND unit_id = ?');
            foreach ($updates as $unitId => $c) {
                $upd->execute([$c['segment'], $c['code_path'], $tenantId, $unitId]);
            }
            $ins = $pdo->prepare('INSERT INTO unit_codes (unit_id, tenant_id, segment, code_path, admin_number, updated_at) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)');
            foreach ($inserts as $unitId => $c) {
                $ins->execute([$unitId, $tenantId, $c['segment'], $c['code_path'], $c['admin_number']]);
            }
            if ($ownTx) {
                $pdo->commit();
            }
        } catch (Throwable $e) {
            if ($ownTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Unités dont le code commence par $prefix, ou dont le numéro administratif vaut $number.
     *
     * @return list<int>
     */
    public function searchUnitIds(int $tenantId, string $prefix, ?int $number, int $limit): array
    {
        $parts = [];
        $params = [$tenantId];
        $prefix = (string) preg_replace('/[^A-Z0-9-]/', '', strtoupper($prefix));
        if ($prefix !== '') {
            // Préfixe : l'index (tenant_id, code_path) sert à la recherche.
            $parts[] = 'code_path LIKE ?';
            $params[] = $prefix . '%';
        }
        if ($number !== null && $number > 0) {
            $parts[] = 'admin_number = ?';
            $params[] = $number;
        }
        if ($parts === []) {
            return [];
        }
        $st = $this->pdo()->prepare(
            'SELECT unit_id FROM unit_codes WHERE tenant_id = ? AND (' . implode(' OR ', $parts) . ')
             ORDER BY LENGTH(code_path) ASC, code_path ASC LIMIT ' . max(1, min(50, $limit))
        );
        $st->execute($params);

        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }
}
