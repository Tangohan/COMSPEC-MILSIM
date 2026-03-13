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

    public function upsert(int $userId, array $data): void
    {
        $fields = ['first_name', 'last_name', 'birth_date', 'nationality', 'timezone', 'language', 'bio', 'phone', 'emergency_contact'];
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
}
