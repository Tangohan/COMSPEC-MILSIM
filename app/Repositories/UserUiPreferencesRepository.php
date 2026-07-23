<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class UserUiPreferencesRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    /** @return array<string, mixed>|null */
    public function findByUserId(int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM user_ui_preferences WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @return array{theme: string, density: string, sidebar_collapsed: bool, dashboard_layout_json: mixed, favorite_modules_json: mixed}
     */
    public function getOrDefaults(int $userId, int $tenantId): array
    {
        $row = $this->findByUserId($userId);
        if ($row) {
            return [
                'theme' => (string) ($row['theme'] ?? 'system'),
                'density' => (string) ($row['density'] ?? 'compact'),
                'sidebar_collapsed' => (bool) ($row['sidebar_collapsed'] ?? false),
                'dashboard_layout_json' => $this->decodeJsonColumn($row['dashboard_layout_json'] ?? null),
                'favorite_modules_json' => $this->decodeJsonColumn($row['favorite_modules_json'] ?? null),
            ];
        }

        return [
            'theme' => 'system',
            'density' => 'compact',
            'sidebar_collapsed' => false,
            'dashboard_layout_json' => null,
            'favorite_modules_json' => null,
        ];
    }

    /**
     * @param array{theme?: string, density?: string, sidebar_collapsed?: bool, dashboard_layout_json?: mixed, favorite_modules_json?: mixed} $data
     */
    public function upsert(int $userId, int $tenantId, array $data): void
    {
        $layoutJson = array_key_exists('dashboard_layout_json', $data)
            ? $this->encodeJsonValue($data['dashboard_layout_json'])
            : null;
        $favJson = array_key_exists('favorite_modules_json', $data)
            ? $this->encodeJsonValue($data['favorite_modules_json'])
            : null;

        $existing = $this->findByUserId($userId);
        if ($existing) {
            $sets = [];
            $params = [];
            $map = [
                'theme' => 'theme',
                'density' => 'density',
                'sidebar_collapsed' => 'sidebar_collapsed',
            ];
            foreach ($map as $key => $col) {
                if (array_key_exists($key, $data)) {
                    $sets[] = "`$col` = ?";
                    $params[] = $data[$key];
                }
            }
            if (array_key_exists('dashboard_layout_json', $data)) {
                $sets[] = 'dashboard_layout_json = ?';
                $params[] = $layoutJson;
            }
            if (array_key_exists('favorite_modules_json', $data)) {
                $sets[] = 'favorite_modules_json = ?';
                $params[] = $favJson;
            }
            if (empty($sets)) {
                return;
            }
            $params[] = $userId;
            $sql = 'UPDATE user_ui_preferences SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE user_id = ?';
            $this->pdo->prepare($sql)->execute($params);

            return;
        }

        $theme = (string) ($data['theme'] ?? 'system');
        $density = (string) ($data['density'] ?? 'compact');
        $collapsed = isset($data['sidebar_collapsed']) ? (int) (bool) $data['sidebar_collapsed'] : 0;
        $stmt = $this->pdo->prepare(
            'INSERT INTO user_ui_preferences (user_id, tenant_id, theme, density, sidebar_collapsed, dashboard_layout_json, favorite_modules_json, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $userId,
            $tenantId,
            $theme,
            $density,
            $collapsed,
            $layoutJson,
            $favJson,
        ]);
    }

    private function decodeJsonColumn(mixed $raw): mixed
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_array($raw)) {
            return $raw;
        }
        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function encodeJsonValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return json_encode($decoded, JSON_THROW_ON_ERROR);
            }

            return $value;
        }

        return json_encode($value, JSON_THROW_ON_ERROR);
    }
}
