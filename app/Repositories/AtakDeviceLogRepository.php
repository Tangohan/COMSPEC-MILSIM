<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\AtakDeviceLog;
use PDO;

/**
 * Traces d’appareil ATAK (équivalent du journal AppData Overwatch).
 */
final class AtakDeviceLogRepository
{
    public const RETENTION_DAYS = 14;
    public const MAX_PER_TERMINAL = 3000;
    public const MAX_BATCH = 80;
    public const DEDUP_SECONDS = 15;

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
        try {
            $migration = dirname(__DIR__, 2) . '/bootstrap/atak_device_logs_migration.php';
            if (is_file($migration)) {
                require_once $migration;
                if (function_exists('run_atak_device_logs_migration')) {
                    run_atak_device_logs_migration($this->pdo);
                }
            }
        } catch (\Throwable) {
            // Table via run-migrations ; ne pas casser le boot API.
        }
    }

    public function tableReady(): bool
    {
        try {
            $st = $this->pdo->query(
                "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'atak_device_logs' LIMIT 1"
            );

            return $st !== false && (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param list<array<string, mixed>> $lines
     * @return array{accepted:int, skipped:int}
     */
    public function ingest(
        int $tenantId,
        string $terminalUid,
        array $lines,
        ?string $callsign = null,
        ?string $steamUid = null,
        ?string $playerName = null,
        string $source = AtakDeviceLog::SOURCE_MOD
    ): array {
        $accepted = 0;
        $skipped = 0;
        if (!$this->tableReady() || $tenantId < 1) {
            return ['accepted' => 0, 'skipped' => count($lines)];
        }
        $uid = AtakDeviceLog::clip($terminalUid, 64);
        if ($uid === '') {
            return ['accepted' => 0, 'skipped' => count($lines)];
        }
        $source = match ($source) {
            AtakDeviceLog::SOURCE_WEB, AtakDeviceLog::SOURCE_SYSTEM => $source,
            default => AtakDeviceLog::SOURCE_MOD,
        };
        $callsign = AtakDeviceLog::clip((string) $callsign, 64);
        $steamUid = AtakDeviceLog::clip((string) $steamUid, 32);
        $playerName = AtakDeviceLog::clip((string) $playerName, 128);

        $sql = 'INSERT INTO atak_device_logs
            (tenant_id, terminal_uid, callsign, steam_uid, player_name, level, channel, message, detail_text, raw_line, source, fingerprint, logged_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $st = $this->pdo->prepare($sql);

        foreach (array_slice($lines, 0, self::MAX_BATCH) as $raw) {
            if (!is_array($raw)) {
                $skipped++;
                continue;
            }
            $line = AtakDeviceLog::normalizeLine($raw);
            if ($line === null) {
                $skipped++;
                continue;
            }
            $loggedAt = $this->normalizeDateTime($line['logged_at']);
            $fp = substr(hash('sha256', strtolower($uid . '|' . $line['level'] . '|' . $line['channel'] . '|' . $line['message'])), 0, 40);
            if ($this->isDuplicate($tenantId, $fp, $loggedAt)) {
                $skipped++;
                continue;
            }
            try {
                $st->execute([
                    $tenantId,
                    $uid,
                    $callsign !== '' ? $callsign : null,
                    $steamUid !== '' ? $steamUid : null,
                    $playerName !== '' ? $playerName : null,
                    $line['level'],
                    AtakDeviceLog::clip($line['channel'], 64),
                    $line['message'],
                    $line['detail'] !== '' ? $line['detail'] : null,
                    $line['raw_line'] !== '' ? $line['raw_line'] : null,
                    $source,
                    $fp,
                    $loggedAt,
                ]);
                $accepted++;
            } catch (\Throwable) {
                $skipped++;
            }
        }

        if ($accepted > 0) {
            $this->prune($tenantId, $uid);
        }

        return ['accepted' => $accepted, 'skipped' => $skipped];
    }

    /**
     * @param array{
     *   level?:string,
     *   channel?:string,
     *   message:string,
     *   detail?:string,
     *   source?:string
     * } $event
     */
    public function recordEvent(
        int $tenantId,
        string $terminalUid,
        array $event,
        ?string $callsign = null,
        ?string $steamUid = null,
        ?string $playerName = null
    ): bool {
        $result = $this->ingest(
            $tenantId,
            $terminalUid,
            [$event],
            $callsign,
            $steamUid,
            $playerName,
            (string) ($event['source'] ?? AtakDeviceLog::SOURCE_SYSTEM)
        );

        return $result['accepted'] > 0;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForTerminal(
        int $tenantId,
        string $terminalUid,
        int $limit = 200,
        ?string $level = null,
        ?string $search = null,
        ?int $beforeId = null
    ): array {
        if (!$this->tableReady() || $tenantId < 1) {
            return [];
        }
        $uid = AtakDeviceLog::clip($terminalUid, 64);
        if ($uid === '') {
            return [];
        }
        $limit = max(1, min(400, $limit));
        $sql = 'SELECT * FROM atak_device_logs WHERE tenant_id = ? AND terminal_uid = ?';
        $params = [$tenantId, $uid];
        $levelKey = $level !== null && $level !== '' && $level !== 'all' ? AtakDeviceLog::normalizeLevel($level) : '';
        if ($levelKey === AtakDeviceLog::LEVEL_INFO) {
            $sql .= ' AND level IN (?, ?)';
            $params[] = AtakDeviceLog::LEVEL_INFO;
            $params[] = AtakDeviceLog::LEVEL_DEBUG;
        } elseif (in_array($levelKey, [AtakDeviceLog::LEVEL_ERROR, AtakDeviceLog::LEVEL_WARN, AtakDeviceLog::LEVEL_DEBUG], true)) {
            $sql .= ' AND level = ?';
            $params[] = $levelKey;
        }
        $q = AtakDeviceLog::clip((string) $search, 120);
        if ($q !== '') {
            $sql .= ' AND (message LIKE ? OR channel LIKE ? OR IFNULL(detail_text, \'\') LIKE ?)';
            $like = '%' . $this->likeEscape($q) . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if ($beforeId !== null && $beforeId > 0) {
            $sql .= ' AND id < ?';
            $params[] = $beforeId;
        }
        $sql .= ' ORDER BY id DESC LIMIT ' . $limit;
        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countForTerminal(int $tenantId, string $terminalUid): int
    {
        if (!$this->tableReady() || $tenantId < 1) {
            return 0;
        }
        $uid = AtakDeviceLog::clip($terminalUid, 64);
        if ($uid === '') {
            return 0;
        }
        $st = $this->pdo->prepare(
            'SELECT COUNT(*) FROM atak_device_logs WHERE tenant_id = ? AND terminal_uid = ?'
        );
        $st->execute([$tenantId, $uid]);

        return (int) $st->fetchColumn();
    }

    private function isDuplicate(int $tenantId, string $fingerprint, string $loggedAt): bool
    {
        try {
            $st = $this->pdo->prepare(
                'SELECT 1 FROM atak_device_logs
                 WHERE tenant_id = ? AND fingerprint = ?
                   AND logged_at >= DATE_SUB(?, INTERVAL ' . self::DEDUP_SECONDS . ' SECOND)
                 LIMIT 1'
            );
            $st->execute([$tenantId, $fingerprint, $loggedAt]);

            return (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    private function prune(int $tenantId, string $terminalUid): void
    {
        try {
            $this->pdo->prepare(
                'DELETE FROM atak_device_logs WHERE tenant_id = ? AND logged_at < DATE_SUB(NOW(), INTERVAL ' . self::RETENTION_DAYS . ' DAY)'
            )->execute([$tenantId]);
        } catch (\Throwable) {
        }
        try {
            $st = $this->pdo->prepare(
                'SELECT id FROM atak_device_logs
                 WHERE tenant_id = ? AND terminal_uid = ?
                 ORDER BY id DESC LIMIT 1 OFFSET ' . self::MAX_PER_TERMINAL
            );
            $st->execute([$tenantId, $terminalUid]);
            $oldestKeep = (int) $st->fetchColumn();
            if ($oldestKeep > 0) {
                $this->pdo->prepare(
                    'DELETE FROM atak_device_logs WHERE tenant_id = ? AND terminal_uid = ? AND id <= ?'
                )->execute([$tenantId, $terminalUid, $oldestKeep]);
            }
        } catch (\Throwable) {
        }
    }

    private function normalizeDateTime(?string $raw): string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return date('Y-m-d H:i:s');
        }
        $ts = strtotime($raw);

        return $ts !== false ? date('Y-m-d H:i:s', $ts) : date('Y-m-d H:i:s');
    }

    private function likeEscape(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
