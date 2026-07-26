<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Rapports d’erreurs / bugs remontés par le mod Overwatch.
 */
final class AtakModReportRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
        try {
            $migration = dirname(__DIR__, 2) . '/bootstrap/atak_mod_reports_migration.php';
            if (is_file($migration)) {
                require_once $migration;
                if (function_exists('run_atak_mod_reports_migration')) {
                    run_atak_mod_reports_migration($this->pdo);
                }
            }
        } catch (\Throwable) {
            // Table via run-migrations / admin ; ne pas casser le boot API.
        }
    }

    /**
     * @param array{
     *   severity?:string,
     *   channel?:string,
     *   message:string,
     *   detail_text?:?string,
     *   context_json?:?string,
     *   fingerprint?:?string,
     *   source?:string,
     *   steam_uid?:?string,
     *   player_uid?:?string,
     *   player_name?:?string,
     *   callsign?:?string,
     *   client_ip?:?string,
     *   mod_version?:?string,
     *   extension_version?:?string,
     *   arma_build?:?string
     * } $data
     * @return array{id:int, created:bool, hit_count:int}
     */
    public function upsert(array $data): array
    {
        $severity = $this->normalizeSeverity((string) ($data['severity'] ?? 'error'));
        $channel = $this->clip((string) ($data['channel'] ?? 'Core'), 64) ?: 'Core';
        $message = $this->clip((string) ($data['message'] ?? ''), 512);
        if ($message === '') {
            throw new \InvalidArgumentException('message_required');
        }

        $fingerprint = $this->clip((string) ($data['fingerprint'] ?? ''), 64);
        if ($fingerprint === '') {
            $fingerprint = substr(hash('sha256', strtolower($severity . '|' . $channel . '|' . $message)), 0, 40);
        }

        $steam = $this->clip((string) ($data['steam_uid'] ?? ''), 32);
        $steam = $steam !== '' ? $steam : null;

        // Dédup : même empreinte + même Steam dans les 30 dernières minutes → incrément
        $st = $this->pdo->prepare(
            'SELECT id, hit_count FROM atak_mod_reports
             WHERE fingerprint = ?
               AND ( (? IS NULL AND steam_uid IS NULL) OR steam_uid = ? )
               AND last_seen_at >= (NOW() - INTERVAL 30 MINUTE)
             ORDER BY id DESC LIMIT 1'
        );
        $st->execute([$fingerprint, $steam, $steam]);
        $existing = $st->fetch(PDO::FETCH_ASSOC);

        if (is_array($existing)) {
            $id = (int) $existing['id'];
            $upd = $this->pdo->prepare(
                'UPDATE atak_mod_reports SET
                    hit_count = hit_count + 1,
                    last_seen_at = CURRENT_TIMESTAMP,
                    detail_text = COALESCE(?, detail_text),
                    context_json = COALESCE(?, context_json),
                    player_name = COALESCE(?, player_name),
                    callsign = COALESCE(?, callsign),
                    mod_version = COALESCE(?, mod_version),
                    extension_version = COALESCE(?, extension_version),
                    arma_build = COALESCE(?, arma_build),
                    client_ip = COALESCE(?, client_ip)
                 WHERE id = ?'
            );
            $upd->execute([
                $this->nullableText($data['detail_text'] ?? null, 8000),
                $this->nullableText($data['context_json'] ?? null, 16000),
                $this->nullableClip($data['player_name'] ?? null, 128),
                $this->nullableClip($data['callsign'] ?? null, 64),
                $this->nullableClip($data['mod_version'] ?? null, 32),
                $this->nullableClip($data['extension_version'] ?? null, 32),
                $this->nullableClip($data['arma_build'] ?? null, 64),
                $this->nullableClip($data['client_ip'] ?? null, 45),
                $id,
            ]);
            $hits = (int) $existing['hit_count'] + 1;

            return ['id' => $id, 'created' => false, 'hit_count' => $hits];
        }

        $ins = $this->pdo->prepare(
            'INSERT INTO atak_mod_reports (
                severity, channel, message, detail_text, context_json, fingerprint, source,
                steam_uid, player_uid, player_name, callsign, client_ip,
                mod_version, extension_version, arma_build
             ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $ins->execute([
            $severity,
            $channel,
            $message,
            $this->nullableText($data['detail_text'] ?? null, 8000),
            $this->nullableText($data['context_json'] ?? null, 16000),
            $fingerprint,
            $this->clip((string) ($data['source'] ?? 'auto'), 32) ?: 'auto',
            $steam,
            $this->nullableClip($data['player_uid'] ?? null, 64),
            $this->nullableClip($data['player_name'] ?? null, 128),
            $this->nullableClip($data['callsign'] ?? null, 64),
            $this->nullableClip($data['client_ip'] ?? null, 45),
            $this->nullableClip($data['mod_version'] ?? null, 32),
            $this->nullableClip($data['extension_version'] ?? null, 32),
            $this->nullableClip($data['arma_build'] ?? null, 64),
        ]);

        return ['id' => (int) $this->pdo->lastInsertId(), 'created' => true, 'hit_count' => 1];
    }

    /** @return list<array<string, mixed>> */
    public function listRecent(int $limit = 100, ?string $severity = null): array
    {
        $limit = max(1, min(500, $limit));
        if ($severity !== null && $severity !== '') {
            $sev = $this->normalizeSeverity($severity);
            $st = $this->pdo->prepare(
                'SELECT * FROM atak_mod_reports WHERE severity = ? ORDER BY last_seen_at DESC, id DESC LIMIT ' . $limit
            );
            $st->execute([$sev]);
        } else {
            $st = $this->pdo->query(
                'SELECT * FROM atak_mod_reports ORDER BY last_seen_at DESC, id DESC LIMIT ' . $limit
            );
        }

        $rows = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];

        return is_array($rows) ? $rows : [];
    }

    public function countAll(?string $severity = null): int
    {
        if ($severity !== null && $severity !== '') {
            $st = $this->pdo->prepare('SELECT COUNT(*) FROM atak_mod_reports WHERE severity = ?');
            $st->execute([$this->normalizeSeverity($severity)]);

            return (int) $st->fetchColumn();
        }
        $n = $this->pdo->query('SELECT COUNT(*) FROM atak_mod_reports')?->fetchColumn();

        return (int) $n;
    }

    public function deleteById(int $id): bool
    {
        if ($id < 1) {
            return false;
        }
        $st = $this->pdo->prepare('DELETE FROM atak_mod_reports WHERE id = ?');
        $st->execute([$id]);

        return $st->rowCount() > 0;
    }

    private function normalizeSeverity(string $raw): string
    {
        $s = strtolower(trim($raw));

        return match ($s) {
            'error', 'err', 'fatal', 'critical' => 'error',
            'warn', 'warning' => 'warn',
            'info' => 'info',
            'bug', 'player', 'user' => 'bug',
            default => 'error',
        };
    }

    private function clip(string $s, int $max): string
    {
        $s = trim($s);
        if (mb_strlen($s) <= $max) {
            return $s;
        }

        return mb_substr($s, 0, $max);
    }

    private function nullableClip(mixed $raw, int $max): ?string
    {
        if ($raw === null) {
            return null;
        }
        $s = $this->clip((string) $raw, $max);

        return $s !== '' ? $s : null;
    }

    private function nullableText(mixed $raw, int $max): ?string
    {
        if ($raw === null) {
            return null;
        }
        $s = trim((string) $raw);
        if ($s === '') {
            return null;
        }
        if (mb_strlen($s) > $max) {
            $s = mb_substr($s, 0, $max);
        }

        return $s;
    }
}
