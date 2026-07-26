<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Chargement groupé des champs nécessaires au nom public forum (évite N+1).
 */
class ForumAuthorIdentityRepository
{
    private PDO $pdo;

    private ?bool $hasDisplaySettingsTable = null;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    private function hasDisplaySettingsTable(): bool
    {
        if ($this->hasDisplaySettingsTable !== null) {
            return $this->hasDisplaySettingsTable;
        }
        $stmt = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_profile_display_settings' LIMIT 1");
        $this->hasDisplaySettingsTable = (bool) ($stmt && $stmt->fetchColumn());

        return $this->hasDisplaySettingsTable;
    }

    /**
     * @param int[] $userIds
     * @return array<int, array<string, mixed>>
     */
    public function fetchMapForTenantAndUserIds(int $tenantId, array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), fn ($id) => $id > 0)));
        if ($userIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $params = array_merge([$tenantId], $userIds);
        $upsJoin = $this->hasDisplaySettingsTable()
            ? 'LEFT JOIN user_profile_display_settings ups ON ups.user_id = u.id'
            : '';
        $upsCols = $this->hasDisplaySettingsTable()
            ? 'ups.forum_alias AS author_forum_alias, ups.forum_label_mode AS author_forum_label_mode'
            : 'NULL AS author_forum_alias, NULL AS author_forum_label_mode';

        $sql = "SELECT u.id AS author_user_id, u.email AS author_email, u.display_name AS author_name, u.callsign AS author_callsign,
                up.first_name AS author_first_name, up.last_name AS author_last_name,
                pp.character_name AS author_character_name,
                $upsCols
         FROM users u
         LEFT JOIN user_profiles up ON up.user_id = u.id
         LEFT JOIN personnel_profiles pp ON pp.user_id = u.id
         $upsJoin
         WHERE u.tenant_id = ? AND u.id IN ($placeholders)";
        try {
            // Prefer including deleted_at when the column exists (soft-delete seal).
            $sqlWithDeleted = str_replace(
                'u.callsign AS author_callsign,',
                'u.callsign AS author_callsign, u.deleted_at AS author_deleted_at,',
                $sql
            );
            $stmt = $this->pdo->prepare($sqlWithDeleted);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            try {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (\PDOException $e2) {
                $sql2 = "SELECT u.id AS author_user_id, u.email AS author_email, u.display_name AS author_name, u.callsign AS author_callsign,
                up.first_name AS author_first_name, up.last_name AS author_last_name,
                NULL AS author_character_name,
                NULL AS author_forum_alias, NULL AS author_forum_label_mode
         FROM users u
         LEFT JOIN user_profiles up ON up.user_id = u.id
         WHERE u.tenant_id = ? AND u.id IN ($placeholders)";
                $stmt = $this->pdo->prepare($sql2);
                $stmt->execute($params);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
        $map = [];
        foreach ($rows as $row) {
            $id = (int) ($row['author_user_id'] ?? 0);
            if ($id > 0) {
                $map[$id] = $row;
            }
        }

        return $map;
    }
}
