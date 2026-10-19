<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\CommunityLanguages;
use PDO;

/**
 * Langue de jeu d’un joueur dans une communauté (langue principale + autres langues comprises).
 * Table créée au premier usage : aucune migration à lancer.
 */
final class MemberLanguageRepository
{
    private ?PDO $pdo;
    private static bool $schemaReady = false;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    public function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }
        $this->pdo()->exec('CREATE TABLE IF NOT EXISTS tenant_member_languages (
            tenant_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            primary_lang VARCHAR(8) DEFAULT NULL,
            other_langs VARCHAR(64) NOT NULL DEFAULT "",
            updated_by_user_id INT UNSIGNED DEFAULT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (tenant_id, user_id),
            KEY idx_tml_tenant_lang (tenant_id, primary_lang)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        self::$schemaReady = true;
    }

    /** @return array{primary_lang: ?string, other_langs: string}|null */
    public function find(int $tenantId, int $userId): ?array
    {
        $rows = $this->forUsers($tenantId, [$userId]);

        return $rows[$userId] ?? null;
    }

    /**
     * @param list<int> $userIds
     * @return array<int, array{primary_lang: ?string, other_langs: string}>
     */
    public function forUsers(int $tenantId, array $userIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $id): bool => $id > 0)));
        if ($tenantId < 1 || $ids === []) {
            return [];
        }
        try {
            $this->ensureSchema();
            $in = implode(',', array_fill(0, count($ids), '?'));
            $st = $this->pdo()->prepare(
                "SELECT user_id, primary_lang, other_langs FROM tenant_member_languages WHERE tenant_id = ? AND user_id IN ($in)"
            );
            $st->execute(array_merge([$tenantId], $ids));
            $out = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $out[(int) $row['user_id']] = [
                    'primary_lang' => $row['primary_lang'] !== null ? (string) $row['primary_lang'] : null,
                    'other_langs' => (string) ($row['other_langs'] ?? ''),
                ];
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Langue de jeu résolue pour chaque joueur (réglée, sinon langue d’interface, sinon langue de référence).
     *
     * @param list<int> $userIds
     * @param array<string, mixed> $community
     * @return array<int, array{primary: string, others: list<string>, source: 'member'|'interface'|'community'}>
     */
    public function resolvedForUsers(int $tenantId, array $userIds, array $community): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }
        $stored = $this->forUsers($tenantId, $ids);
        $interface = [];
        try {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $st = $this->pdo()->prepare("SELECT user_id, language FROM user_profiles WHERE user_id IN ($in)");
            $st->execute($ids);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $interface[(int) $row['user_id']] = (string) ($row['language'] ?? '');
            }
        } catch (\Throwable) {
            $interface = [];
        }
        $out = [];
        foreach ($ids as $id) {
            $out[$id] = CommunityLanguages::resolveMember($stored[$id] ?? null, $interface[$id] ?? null, $community);
        }

        return $out;
    }

    /**
     * @param list<string> $others
     */
    public function save(int $tenantId, int $userId, ?string $primary, array $others, ?int $actorId = null): void
    {
        $this->ensureSchema();
        $primary = CommunityLanguages::normalize($primary);
        $others = array_values(array_diff(CommunityLanguages::normalizeList($others), $primary !== null ? [$primary] : []));
        $st = $this->pdo()->prepare(
            'INSERT INTO tenant_member_languages (tenant_id, user_id, primary_lang, other_langs, updated_by_user_id, updated_at)
             VALUES (?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE primary_lang = VALUES(primary_lang), other_langs = VALUES(other_langs),
                updated_by_user_id = VALUES(updated_by_user_id), updated_at = NOW()'
        );
        $st->execute([$tenantId, $userId, $primary, implode(',', $others), $actorId]);
    }
}
