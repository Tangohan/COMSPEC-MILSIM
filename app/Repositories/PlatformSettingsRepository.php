<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class PlatformSettingsRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function tableExists(): bool
    {
        try {
            $st = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'platform_settings' LIMIT 1");

            return (bool) ($st && $st->fetch());
        } catch (\Throwable) {
            return false;
        }
    }

    public function get(string $key, string $default = ''): string
    {
        if (!$this->tableExists()) {
            return $default;
        }
        try {
            $stmt = $this->pdo->prepare('SELECT value FROM platform_settings WHERE setting_key = ? LIMIT 1');
            $stmt->execute([$key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || !array_key_exists('value', $row)) {
                return $default;
            }

            return (string) $row['value'];
        } catch (\Throwable) {
            return $default;
        }
    }

    public function getBool(string $key, bool $default = true): bool
    {
        $raw = $this->get($key, $default ? '1' : '0');
        $v = strtolower(trim($raw));

        return in_array($v, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * @return array<string, string>
     */
    public function listAll(): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        try {
            $st = $this->pdo->query('SELECT setting_key, value FROM platform_settings ORDER BY setting_key ASC');
            if ($st === false) {
                return [];
            }
            $out = [];
            while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                $k = (string) ($row['setting_key'] ?? '');
                if ($k === '') {
                    continue;
                }
                $out[$k] = (string) ($row['value'] ?? '');
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param array<string, string> $pairs
     */
    public function setMany(array $pairs): void
    {
        if (!$this->tableExists() || $pairs === []) {
            return;
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO platform_settings (setting_key, value, updated_at) VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW()'
        );
        foreach ($pairs as $k => $v) {
            $k = substr((string) $k, 0, 100);
            $stmt->execute([$k, (string) $v]);
        }
    }
}
