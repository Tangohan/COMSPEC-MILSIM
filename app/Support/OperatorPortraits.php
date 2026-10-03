<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Database;
use PDO;
use Throwable;

/**
 * Portraits opérateur (personnel_profiles.character_portrait_path) en lot, mis en cache pour la requête.
 * La photo de compte / Steam n'est jamais utilisée : seul le portrait opérateur s'affiche.
 */
final class OperatorPortraits
{
    /** @var array<string, ?string> clé "tenant:user" → URL publique ou null */
    private static array $cache = [];

    private static ?bool $hasPortraitColumn = null;

    private static ?bool $hasTenantColumn = null;

    /**
     * @param list<int|string> $userIds
     * @return array<int, string> user_id → URL du portrait (seulement ceux qui en ont un)
     */
    public static function forUsers(int $tenantId, array $userIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }

        $missing = array_values(array_filter($ids, static fn (int $id): bool => !array_key_exists($tenantId . ':' . $id, self::$cache)));
        if ($missing !== []) {
            self::load($tenantId, $missing);
        }

        $out = [];
        foreach ($ids as $id) {
            $url = self::$cache[$tenantId . ':' . $id] ?? null;
            if ($url !== null && $url !== '') {
                $out[$id] = $url;
            }
        }

        return $out;
    }

    public static function forUser(int $tenantId, int $userId): ?string
    {
        return self::forUsers($tenantId, [$userId])[$userId] ?? null;
    }

    /**
     * @param list<int> $ids
     */
    private static function load(int $tenantId, array $ids): void
    {
        foreach ($ids as $id) {
            self::$cache[$tenantId . ':' . $id] = null;
        }
        try {
            $pdo = Database::getPdo();
            if (!self::portraitColumnReady($pdo)) {
                return;
            }
            $withTenant = self::tenantColumnReady($pdo);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $st = $pdo->prepare(
                'SELECT user_id, ' . ($withTenant ? 'tenant_id' : '0 AS tenant_id') . ', character_portrait_path
                 FROM personnel_profiles
                 WHERE user_id IN (' . $placeholders . ")
                   AND character_portrait_path IS NOT NULL AND character_portrait_path <> ''"
            );
            $st->execute($ids);
            // Un même compte peut avoir un dossier par communauté : celui de la communauté courante l'emporte.
            $best = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $uid = (int) ($row['user_id'] ?? 0);
                if ($uid < 1) {
                    continue;
                }
                $isHome = $tenantId > 0 && (int) ($row['tenant_id'] ?? 0) === $tenantId;
                if (!isset($best[$uid]) || ($isHome && !$best[$uid]['home'])) {
                    $best[$uid] = ['row' => $row, 'home' => $isHome];
                }
            }
            foreach ($best as $uid => $pick) {
                $url = function_exists('personnel_operator_portrait_url') ? personnel_operator_portrait_url($pick['row']) : null;
                self::$cache[$tenantId . ':' . $uid] = $url !== null && $url !== '' ? $url : null;
            }
        } catch (Throwable) {
        }
    }

    private static function portraitColumnReady(PDO $pdo): bool
    {
        return self::$hasPortraitColumn ??= self::columnExists($pdo, 'character_portrait_path');
    }

    private static function tenantColumnReady(PDO $pdo): bool
    {
        return self::$hasTenantColumn ??= self::columnExists($pdo, 'tenant_id');
    }

    private static function columnExists(PDO $pdo, string $column): bool
    {
        try {
            $st = $pdo->prepare(
                'SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
            );
            $st->execute(['personnel_profiles', $column]);

            return (bool) $st->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }
}
