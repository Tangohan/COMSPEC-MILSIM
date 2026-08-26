<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\LazyDatabaseConnection;
use PDO;

/**
 * Journal des accès anticipés (bêta) remontés par le mod Overwatch.
 */
final class AtakBetaRegistrationRepository
{
    use LazyDatabaseConnection;


    public function __construct()
    {
        // PDO + schéma à la première requête (pas au boot Container / mod-report).
    }

    protected function onDatabaseConnected(PDO $pdo): void
    {
        try {
            require_once dirname(__DIR__, 2) . '/bootstrap/atak_beta_registrations_migration.php';
            if (function_exists('run_atak_beta_registrations_migration')) {
                run_atak_beta_registrations_migration($pdo);
            }
        } catch (\Throwable) {
        }
    }

    /**
     * Enregistre ou met à jour une inscription bêta.
     * Priorité de rapprochement : Steam → UID joueur → nouvelle ligne.
     *
     * @param array{
     *   steam_uid?: ?string,
     *   player_uid?: ?string,
     *   player_name?: ?string,
     *   client_ip?: ?string,
     *   arma_build?: ?string,
     *   arma_branch?: ?string,
     *   mod_version?: ?string,
     *   extension_version?: ?string,
     *   acknowledged?: bool
     * } $data
     * @return array{id: int, created: bool}
     */
    public function upsert(array $data): array
    {
        $steam = $this->nullIfEmpty($data['steam_uid'] ?? null);
        $playerUid = $this->nullIfEmpty($data['player_uid'] ?? null);
        $playerName = $this->truncate($this->nullIfEmpty($data['player_name'] ?? null), 128);
        $clientIp = $this->truncate($this->nullIfEmpty($data['client_ip'] ?? null), 45);
        $armaBuild = $this->truncate($this->nullIfEmpty($data['arma_build'] ?? null), 64);
        $armaBranch = $this->truncate($this->nullIfEmpty($data['arma_branch'] ?? null), 64);
        $modVersion = $this->truncate($this->nullIfEmpty($data['mod_version'] ?? null), 32);
        $extVersion = $this->truncate($this->nullIfEmpty($data['extension_version'] ?? null), 32);
        $ack = !empty($data['acknowledged']);
        $now = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');

        $existingId = null;
        if ($steam !== null) {
            $st = $this->pdo()->prepare('SELECT id FROM atak_beta_registrations WHERE steam_uid = ? LIMIT 1');
            $st->execute([$steam]);
            $existingId = $st->fetchColumn();
        }
        if ($existingId === false || $existingId === null) {
            if ($playerUid !== null) {
                $st = $this->pdo()->prepare(
                    'SELECT id FROM atak_beta_registrations WHERE player_uid = ? AND (steam_uid IS NULL OR steam_uid = "") ORDER BY id DESC LIMIT 1'
                );
                $st->execute([$playerUid]);
                $existingId = $st->fetchColumn();
            }
        }

        if ($existingId !== false && $existingId !== null) {
            $id = (int) $existingId;
            $sql = 'UPDATE atak_beta_registrations SET
                steam_uid = COALESCE(?, steam_uid),
                player_uid = COALESCE(?, player_uid),
                player_name = COALESCE(?, player_name),
                client_ip = COALESCE(?, client_ip),
                arma_build = COALESCE(?, arma_build),
                arma_branch = COALESCE(?, arma_branch),
                mod_version = COALESCE(?, mod_version),
                extension_version = COALESCE(?, extension_version),
                last_seen_at = ?,
                hit_count = hit_count + 1';
            $params = [
                $steam, $playerUid, $playerName, $clientIp,
                $armaBuild, $armaBranch, $modVersion, $extVersion, $now,
            ];
            if ($ack) {
                $sql .= ', acknowledged_at = COALESCE(acknowledged_at, ?)';
                $params[] = $now;
            }
            $sql .= ' WHERE id = ?';
            $params[] = $id;
            $this->pdo()->prepare($sql)->execute($params);

            return ['id' => $id, 'created' => false];
        }

        $st = $this->pdo()->prepare(
            'INSERT INTO atak_beta_registrations (
                steam_uid, player_uid, player_name, client_ip,
                arma_build, arma_branch, mod_version, extension_version,
                acknowledged_at, first_seen_at, last_seen_at, hit_count
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)'
        );
        $st->execute([
            $steam,
            $playerUid,
            $playerName,
            $clientIp,
            $armaBuild,
            $armaBranch,
            $modVersion,
            $extVersion,
            $ack ? $now : null,
            $now,
            $now,
        ]);

        return ['id' => (int) $this->pdo()->lastInsertId(), 'created' => true];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listRecent(int $limit = 200): array
    {
        $limit = max(1, min(500, $limit));
        $st = $this->pdo()->query(
            'SELECT id, steam_uid, player_uid, player_name, client_ip,
                    arma_build, arma_branch, mod_version, extension_version,
                    acknowledged_at, first_seen_at, last_seen_at, hit_count
             FROM atak_beta_registrations
             ORDER BY last_seen_at DESC, id DESC
             LIMIT ' . $limit
        );

        $rows = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];

        return is_array($rows) ? $rows : [];
    }

    public function countAll(): int
    {
        $n = $this->pdo()->query('SELECT COUNT(*) FROM atak_beta_registrations')?->fetchColumn();

        return (int) ($n ?: 0);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        $st = $this->pdo()->prepare(
            'SELECT id, steam_uid, player_uid, player_name, client_ip,
                    arma_build, arma_branch, mod_version, extension_version,
                    acknowledged_at, first_seen_at, last_seen_at, hit_count
             FROM atak_beta_registrations WHERE id = ? LIMIT 1'
        );
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @param list<int> $ids
     * @return list<array<string, mixed>>
     */
    public function findByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $st = $this->pdo()->prepare(
            "SELECT id, steam_uid, player_uid, player_name, client_ip,
                    arma_build, arma_branch, mod_version, extension_version,
                    acknowledged_at, first_seen_at, last_seen_at, hit_count
             FROM atak_beta_registrations WHERE id IN ({$placeholders})"
        );
        $st->execute($ids);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    public function clearAcknowledged(int $id): bool
    {
        if ($id < 1) {
            return false;
        }
        $st = $this->pdo()->prepare(
            'UPDATE atak_beta_registrations SET acknowledged_at = NULL WHERE id = ? AND acknowledged_at IS NOT NULL'
        );
        $st->execute([$id]);

        return $st->rowCount() > 0;
    }

    /**
     * @param list<int> $ids
     */
    public function clearAcknowledgedMany(array $ids): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $st = $this->pdo()->prepare(
            "UPDATE atak_beta_registrations SET acknowledged_at = NULL
             WHERE id IN ({$placeholders}) AND acknowledged_at IS NOT NULL"
        );
        $st->execute($ids);

        return $st->rowCount();
    }

    public function deleteById(int $id): bool
    {
        if ($id < 1) {
            return false;
        }
        $st = $this->pdo()->prepare('DELETE FROM atak_beta_registrations WHERE id = ?');
        $st->execute([$id]);

        return $st->rowCount() > 0;
    }

    /**
     * @param list<int> $ids
     */
    public function deleteByIds(array $ids): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $st = $this->pdo()->prepare("DELETE FROM atak_beta_registrations WHERE id IN ({$placeholders})");
        $st->execute($ids);

        return $st->rowCount();
    }

    private function nullIfEmpty(?string $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $v = trim($v);
        if ($v === '' || $v === '—' || $v === '-') {
            return null;
        }

        return $v;
    }

    private function truncate(?string $v, int $max): ?string
    {
        if ($v === null) {
            return null;
        }
        if (function_exists('mb_substr')) {
            return mb_substr($v, 0, $max);
        }

        return substr($v, 0, $max);
    }
}
