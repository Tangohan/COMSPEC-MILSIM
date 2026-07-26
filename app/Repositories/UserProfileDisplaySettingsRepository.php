<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class UserProfileDisplaySettingsRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function tableExists(): bool
    {
        $stmt = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_profile_display_settings' LIMIT 1");

        return (bool) ($stmt && $stmt->fetchColumn());
    }

    public function getByUserId(int $userId): ?array
    {
        if (!$this->tableExists()) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM user_profile_display_settings WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** RGPD / soft-delete : efface alias forum et préférences d’affichage. */
    public function deleteByUserId(int $userId): void
    {
        if ($userId < 1 || !$this->tableExists()) {
            return;
        }
        $stmt = $this->pdo->prepare('DELETE FROM user_profile_display_settings WHERE user_id = ?');
        $stmt->execute([$userId]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getOrDefaults(int $userId): array
    {
        $row = $this->getByUserId($userId);

        return array_merge([
            'user_id' => $userId,
            'forum_alias' => null,
            'forum_label_mode' => 'display_name',
            'forum_visible_role_id' => null,
            'show_matricule_forum' => 1,
            'show_grade_forum' => 1,
            'show_unit_forum' => 1,
            'show_bio_forum' => 1,
            'fiche_show_email_to_others' => 0,
            'fiche_show_matricule_to_others' => 1,
            'public_roster_opt_in' => 0,
            'hide_personal_info' => 0,
            'hide_forum_level' => 0,
        ], $row ?? []);
    }

    public function upsert(int $userId, array $data): void
    {
        if (!$this->tableExists()) {
            return;
        }
        $allowed = [
            'forum_alias', 'forum_label_mode', 'forum_visible_role_id',
            'show_matricule_forum', 'show_grade_forum', 'show_unit_forum', 'show_bio_forum', 'hide_forum_level',
            'fiche_show_email_to_others', 'fiche_show_matricule_to_others',
            'public_roster_opt_in', 'hide_personal_info',
        ];
        $existing = $this->getByUserId($userId);
        $sets = [];
        $params = [];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) {
                $sets[$f] = $data[$f];
            }
        }
        if ($existing) {
            if (empty($sets)) {
                return;
            }
            $sql = 'UPDATE user_profile_display_settings SET ';
            $parts = [];
            foreach ($sets as $k => $v) {
                $parts[] = "`$k` = ?";
                $params[] = $v;
            }
            $params[] = $userId;
            $sql .= implode(', ', $parts) . ', updated_at = NOW() WHERE user_id = ?';
            $this->pdo->prepare($sql)->execute($params);
        } else {
            $cols = ['user_id'];
            $placeholders = ['?'];
            $params = [$userId];
            foreach ($sets as $k => $v) {
                $cols[] = $k;
                $placeholders[] = '?';
                $params[] = $v;
            }
            $this->pdo->prepare(
                'INSERT INTO user_profile_display_settings (' . implode(',', $cols) . ') VALUES (' . implode(',', $placeholders) . ')'
            )->execute($params);
        }
    }
}
