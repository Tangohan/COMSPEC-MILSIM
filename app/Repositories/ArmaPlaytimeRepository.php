<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\LazyDatabaseConnection;

use PDO;

class ArmaPlaytimeRepository
{
    use LazyDatabaseConnection;


    public const CONTEXT_SERVER = 'server';
    public const CONTEXT_ZEUS = 'zeus';
    public const CONTEXT_EDITOR = 'editor';

    /** @var list<string> */
    public const CONTEXTS = [self::CONTEXT_SERVER, self::CONTEXT_ZEUS, self::CONTEXT_EDITOR];

    private static ?bool $tableReady = null;

    private static ?bool $contextColumnsReady = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    public static function normalizeContext(string $context): string
    {
        $raw = strtolower(trim($context));

        return in_array($raw, self::CONTEXTS, true) ? $raw : self::CONTEXT_SERVER;
    }

    public function schemaReady(): bool
    {
        if (self::$tableReady === null) {
            try {
                $st = $this->pdo()->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_arma_playtime' LIMIT 1");
                self::$tableReady = $st && (bool) $st->fetchColumn();
            } catch (\Throwable) {
                self::$tableReady = false;
            }
        }

        return self::$tableReady;
    }

    public function contextColumnsReady(): bool
    {
        if (!$this->schemaReady()) {
            return false;
        }
        if (self::$contextColumnsReady === null) {
            try {
                $st = $this->pdo()->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_arma_playtime' AND COLUMN_NAME = 'server_seconds' LIMIT 1");
                self::$contextColumnsReady = $st && (bool) $st->fetchColumn();
            } catch (\Throwable) {
                self::$contextColumnsReady = false;
            }
        }

        return self::$contextColumnsReady;
    }

    public function addSeconds(int $tenantId, int $userId, int $seconds, string $context = self::CONTEXT_SERVER): void
    {
        if ($seconds <= 0 || !$this->schemaReady()) {
            return;
        }
        $context = self::normalizeContext($context);
        if ($this->contextColumnsReady()) {
            $server = $context === self::CONTEXT_SERVER ? $seconds : 0;
            $zeus = $context === self::CONTEXT_ZEUS ? $seconds : 0;
            $editor = $context === self::CONTEXT_EDITOR ? $seconds : 0;
            $stmt = $this->pdo()->prepare(
                'INSERT INTO user_arma_playtime (
                    tenant_id, user_id, total_seconds, server_seconds, zeus_seconds, editor_seconds,
                    last_report_at, created_at, updated_at
                 ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW())
                 ON DUPLICATE KEY UPDATE
                    total_seconds = user_arma_playtime.total_seconds + ?,
                    server_seconds = user_arma_playtime.server_seconds + ?,
                    zeus_seconds = user_arma_playtime.zeus_seconds + ?,
                    editor_seconds = user_arma_playtime.editor_seconds + ?,
                    last_report_at = NOW(),
                    updated_at = NOW()'
            );
            $stmt->execute([
                $tenantId, $userId, $seconds, $server, $zeus, $editor,
                $seconds, $server, $zeus, $editor,
            ]);

            return;
        }
        $stmt = $this->pdo()->prepare(
            'INSERT INTO user_arma_playtime (tenant_id, user_id, total_seconds, last_report_at, created_at, updated_at)
             VALUES (?, ?, ?, NOW(), NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                total_seconds = user_arma_playtime.total_seconds + ?,
                last_report_at = NOW(),
                updated_at = NOW()'
        );
        $stmt->execute([$tenantId, $userId, $seconds, $seconds]);
    }

    /**
     * @return array{total_seconds: int, server_seconds: int, zeus_seconds: int, editor_seconds: int, last_report_at: ?string}|null
     */
    public function getSummaryForUser(int $tenantId, int $userId): ?array
    {
        if (!$this->schemaReady()) {
            return null;
        }
        $cols = $this->contextColumnsReady()
            ? 'total_seconds, server_seconds, zeus_seconds, editor_seconds, last_report_at'
            : 'total_seconds, last_report_at';
        $stmt = $this->pdo()->prepare("SELECT {$cols} FROM user_arma_playtime WHERE tenant_id = ? AND user_id = ? LIMIT 1");
        $stmt->execute([$tenantId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->normalizeSummaryRow($row) : null;
    }

    /**
     * @param list<int> $userIds
     * @return array<int, array{total_seconds: int, server_seconds: int, zeus_seconds: int, editor_seconds: int, last_report_at: ?string}>
     */
    public function summariesForUsers(int $tenantId, array $userIds): array
    {
        if (!$this->schemaReady() || $tenantId < 1) {
            return [];
        }
        $ids = [];
        foreach ($userIds as $id) {
            $n = (int) $id;
            if ($n > 0) {
                $ids[$n] = $n;
            }
        }
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge([$tenantId], array_values($ids));
        $cols = $this->contextColumnsReady()
            ? 'user_id, total_seconds, server_seconds, zeus_seconds, editor_seconds, last_report_at'
            : 'user_id, total_seconds, last_report_at';
        $stmt = $this->pdo()->prepare(
            "SELECT {$cols}
             FROM user_arma_playtime
             WHERE tenant_id = ? AND user_id IN ({$placeholders})"
        );
        $stmt->execute($params);
        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!is_array($row)) {
                continue;
            }
            $uid = (int) ($row['user_id'] ?? 0);
            if ($uid < 1) {
                continue;
            }
            $out[$uid] = $this->normalizeSummaryRow($row);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $row
     * @return array{total_seconds: int, server_seconds: int, zeus_seconds: int, editor_seconds: int, last_report_at: ?string}
     */
    private function normalizeSummaryRow(array $row): array
    {
        return [
            'total_seconds' => (int) ($row['total_seconds'] ?? 0),
            'server_seconds' => (int) ($row['server_seconds'] ?? 0),
            'zeus_seconds' => (int) ($row['zeus_seconds'] ?? 0),
            'editor_seconds' => (int) ($row['editor_seconds'] ?? 0),
            'last_report_at' => isset($row['last_report_at']) && $row['last_report_at'] !== null && $row['last_report_at'] !== ''
                ? (string) $row['last_report_at']
                : null,
        ];
    }
}
