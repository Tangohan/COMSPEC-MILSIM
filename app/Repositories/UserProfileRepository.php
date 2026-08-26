<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class UserProfileRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function getByUserId(int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM user_profiles WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Garantit une ligne `user_profiles` pour les jointures et l’édition (prénom/nom, etc.). */
    public function ensureRow(int $userId): void
    {
        $stmt = $this->pdo->prepare('INSERT IGNORE INTO user_profiles (user_id, created_at) VALUES (?, NOW())');
        $stmt->execute([$userId]);
    }

    /** RGPD : efface le profil étendu lors de l’anonymisation d’un compte. */
    public function deleteByUserId(int $userId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM user_profiles WHERE user_id = ?');
        $stmt->execute([$userId]);
    }

    public function upsert(int $userId, array $data): void
    {
        $fields = ['first_name', 'last_name', 'birth_date', 'nationality', 'country_of_residence', 'public_flag_country_code', 'discord_handle', 'timezone', 'language', 'bio', 'phone'];
        $existing = $this->getByUserId($userId);
        if ($existing) {
            $set = [];
            $params = [];
            foreach ($fields as $f) {
                if (array_key_exists($f, $data)) {
                    $set[] = "`$f` = ?";
                    $params[] = $data[$f];
                }
            }
            if (!empty($set)) {
                $params[] = $userId;
                $this->pdo->prepare('UPDATE user_profiles SET ' . implode(', ', $set) . ', updated_at = NOW() WHERE user_id = ?')->execute($params);
            }
        } else {
            $cols = ['user_id'];
            $vals = ['?'];
            $params = [$userId];
            foreach ($fields as $f) {
                if (array_key_exists($f, $data)) {
                    $cols[] = $f;
                    $vals[] = '?';
                    $params[] = $data[$f];
                }
            }
            $this->pdo->prepare('INSERT INTO user_profiles (' . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ')')->execute($params);
        }
    }

    /** @return array{persona: ?string, steps: list<int>, completed_at: ?string}|null */
    public function getOnboardingState(int $userId): ?array
    {
        if ($userId < 1 || !$this->hasOnboardingColumns()) {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'SELECT onboarding_persona, onboarding_steps_json, onboarding_completed_at FROM user_profiles WHERE user_id = ? LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $decoded = json_decode((string) ($row['onboarding_steps_json'] ?? '[]'), true);

        $storedSteps = is_array($decoded)
            ? array_filter($decoded, static fn (mixed $value): bool => is_int($value) || (is_string($value) && preg_match('/^-?\d+$/', $value) === 1))
            : [];

        return [
            'persona' => isset($row['onboarding_persona']) ? (string) $row['onboarding_persona'] : null,
            'steps' => array_values(array_map('intval', $storedSteps)),
            'completed_at' => isset($row['onboarding_completed_at']) ? (string) $row['onboarding_completed_at'] : null,
        ];
    }

    /** @param list<int> $steps */
    public function saveOnboardingState(int $userId, string $persona, array $steps, bool $completed): bool
    {
        if ($userId < 1 || !$this->hasOnboardingColumns()) {
            return false;
        }
        $this->ensureRow($userId);
        $stmt = $this->pdo->prepare(
            'UPDATE user_profiles
             SET onboarding_persona = ?, onboarding_steps_json = ?, onboarding_completed_at = ?, updated_at = NOW()
             WHERE user_id = ?'
        );
        $stmt->execute([
            $persona,
            json_encode(array_values($steps), JSON_THROW_ON_ERROR),
            $completed ? date('Y-m-d H:i:s') : null,
            $userId,
        ]);

        return true;
    }

    private function hasOnboardingColumns(): bool
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        try {
            $stmt = $this->pdo->query(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_profiles'
                   AND COLUMN_NAME IN ('onboarding_persona', 'onboarding_steps_json', 'onboarding_completed_at')"
            );
            $ready = (int) $stmt->fetchColumn() === 3;
        } catch (\Throwable) {
            $ready = false;
        }

        return $ready;
    }
}
