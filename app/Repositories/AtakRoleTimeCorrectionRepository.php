<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\LazyDatabaseConnection;
use PDO;

/**
 * Correction du « Temps par rôle en jeu » d'une fiche par l'encadrement : déplacer tout ou partie du temps
 * d'un rôle vers un autre (perte de synchro qui a fait tomber le rôle, slot mal nommé…).
 *
 * Le temps est déplacé jour par jour (et mission par mission) dans atak_screen_time, rien n'est perdu.
 * Chaque déplacement est journalisé dans atak_role_time_moves (qui, quand, quoi, motif, détail des lignes
 * touchées) et peut être annulé : l'annulation rend exactement les secondes prises, ligne par ligne.
 * La remontée du jeu n'est pas touchée : les prochaines parties s'ajoutent comme avant.
 */
class AtakRoleTimeCorrectionRepository
{
    use LazyDatabaseConnection;

    private static ?bool $ready = null;
    private static ?bool $missionKey = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    public function ensureSchema(): bool
    {
        if (self::$ready) {
            return true;
        }
        try {
            $this->pdo()->exec(self::createTableSql());
            self::$ready = true;
        } catch (\Throwable) {
            self::$ready = false;
        }

        return self::$ready;
    }

    public static function createTableSql(): string
    {
        return "CREATE TABLE IF NOT EXISTS atak_role_time_moves (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                from_key VARCHAR(64) NOT NULL,
                from_label VARCHAR(120) NOT NULL DEFAULT '',
                to_key VARCHAR(64) NOT NULL,
                to_label VARCHAR(120) NOT NULL DEFAULT '',
                seconds INT UNSIGNED NOT NULL DEFAULT 0,
                details MEDIUMTEXT NULL,
                reason VARCHAR(255) NOT NULL DEFAULT '',
                actor_id INT UNSIGNED DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                reverted_at DATETIME DEFAULT NULL,
                reverted_by INT UNSIGNED DEFAULT NULL,
                PRIMARY KEY (id),
                KEY idx_atak_role_time_moves_user (tenant_id, user_id, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    }

    /** Clé d'un rôle saisi à la main (« TACP ») : même forme que les rôles de slot remontés par le jeu. */
    public static function keyForLabel(string $label): string
    {
        $label = trim(preg_replace('/[\x00-\x1F\x7F]/u', ' ', $label) ?? '');

        return $label === '' ? '' : mb_substr('SLOT:' . $label, 0, 64);
    }

    /**
     * Répartit le temps à prendre sur les lignes du rôle source, les plus récentes d'abord.
     * $seconds = null : tout le temps du rôle.
     *
     * @param list<array{day: string, mission_key: string, seconds: int}> $rows triées de la plus récente à la plus ancienne
     * @return list<array{day: string, mission_key: string, seconds: int}>
     */
    public static function planTakes(array $rows, ?int $seconds): array
    {
        $left = $seconds;
        $out = [];
        foreach ($rows as $r) {
            $have = max(0, (int) $r['seconds']);
            if ($have < 1) {
                continue;
            }
            $take = $left === null ? $have : min($have, $left);
            if ($take < 1) {
                break;
            }
            $out[] = ['day' => (string) $r['day'], 'mission_key' => (string) ($r['mission_key'] ?? ''), 'seconds' => $take];
            if ($left !== null) {
                $left -= $take;
                if ($left < 1) {
                    break;
                }
            }
        }

        return $out;
    }

    /**
     * Déplace le temps d'un rôle vers un autre. Renvoie l'id du journal, ou une erreur lisible.
     *
     * @return array{ok: bool, id?: int, seconds?: int, error?: string}
     */
    public function move(int $tenantId, int $userId, string $fromKey, string $toKey, string $toLabel, ?int $seconds, int $actorId, string $reason): array
    {
        $fromKey = trim($fromKey);
        $toKey = trim($toKey);
        if ($tenantId < 1 || $userId < 1 || $fromKey === '' || $toKey === '') {
            return ['ok' => false, 'error' => 'Choisissez le rôle de départ et le rôle d’arrivée.'];
        }
        if ($fromKey === $toKey) {
            return ['ok' => false, 'error' => 'Le rôle d’arrivée doit être différent du rôle de départ.'];
        }
        if ($seconds !== null && $seconds < 60) {
            return ['ok' => false, 'error' => 'Indiquez au moins une minute, ou laissez vide pour tout déplacer.'];
        }
        if (!$this->ensureSchema()) {
            return ['ok' => false, 'error' => 'Journal des corrections indisponible (migration à passer).'];
        }
        $pdo = $this->pdo();
        $mk = $this->hasMissionKey();
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare(
                'SELECT day, ' . ($mk ? 'mission_key' : "'' AS mission_key") . ', seconds, item_label FROM atak_screen_time
                 WHERE tenant_id = ? AND user_id = ? AND kind = \'role\' AND item_key = ? AND seconds > 0
                 ORDER BY day DESC, id DESC FOR UPDATE'
            );
            $st->execute([$tenantId, $userId, $fromKey]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $fromLabel = '';
            foreach ($rows as $r) {
                $fromLabel = $fromLabel !== '' ? $fromLabel : trim((string) ($r['item_label'] ?? ''));
            }
            $takes = self::planTakes(array_map(static fn (array $r): array => [
                'day' => (string) $r['day'], 'mission_key' => (string) $r['mission_key'], 'seconds' => (int) $r['seconds'],
            ], $rows), $seconds);
            if ($takes === []) {
                $pdo->rollBack();

                return ['ok' => false, 'error' => 'Aucun temps à déplacer pour ce rôle.'];
            }
            $toLabel = $this->labelFor($tenantId, $userId, $toKey) ?? trim($toLabel);
            $toLabel = mb_substr($toLabel !== '' ? $toLabel : (str_starts_with($toKey, 'SLOT:') ? substr($toKey, 5) : $toKey), 0, 120);
            $fromLabel = mb_substr($fromLabel !== '' ? $fromLabel : (str_starts_with($fromKey, 'SLOT:') ? substr($fromKey, 5) : $fromKey), 0, 120);
            foreach ($takes as $t) {
                $this->shift($tenantId, $userId, $t, $fromKey, $toKey, $toLabel);
            }
            $total = array_sum(array_column($takes, 'seconds'));
            $pdo->prepare(
                'INSERT INTO atak_role_time_moves (tenant_id, user_id, from_key, from_label, to_key, to_label, seconds, details, reason, actor_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $tenantId, $userId, $fromKey, $fromLabel, $toKey, $toLabel, $total,
                json_encode($takes, JSON_UNESCAPED_UNICODE), mb_substr(trim($reason), 0, 255), $actorId > 0 ? $actorId : null,
            ]);
            $id = (int) $pdo->lastInsertId();
            $pdo->commit();

            return ['ok' => true, 'id' => $id, 'seconds' => $total];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Annule un déplacement : rend au rôle de départ ce qui avait été pris, jour par jour.
     * Si du temps a depuis été déplacé hors du rôle d'arrivée, seul ce qui reste est rendu.
     *
     * @return array{ok: bool, seconds?: int, error?: string}
     */
    public function revert(int $tenantId, int $userId, int $moveId, int $actorId): array
    {
        if (!$this->ensureSchema()) {
            return ['ok' => false, 'error' => 'Journal des corrections indisponible.'];
        }
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare('SELECT * FROM atak_role_time_moves WHERE id = ? AND tenant_id = ? AND user_id = ? FOR UPDATE');
            $st->execute([$moveId, $tenantId, $userId]);
            $move = $st->fetch(PDO::FETCH_ASSOC);
            if (!$move) {
                $pdo->rollBack();

                return ['ok' => false, 'error' => 'Correction introuvable.'];
            }
            if ($move['reverted_at'] !== null) {
                $pdo->rollBack();

                return ['ok' => false, 'error' => 'Cette correction a déjà été annulée.'];
            }
            $details = json_decode((string) ($move['details'] ?? '[]'), true);
            $mk = $this->hasMissionKey();
            $back = 0;
            foreach (is_array($details) ? $details : [] as $t) {
                if (!is_array($t)) {
                    continue;
                }
                $want = (int) ($t['seconds'] ?? 0);
                $have = $this->rowSeconds($tenantId, $userId, (string) ($t['day'] ?? ''), $mk ? (string) ($t['mission_key'] ?? '') : '', (string) $move['to_key']);
                $take = min($want, $have);
                if ($take < 1) {
                    continue;
                }
                $this->shift($tenantId, $userId, ['day' => (string) $t['day'], 'mission_key' => (string) ($t['mission_key'] ?? ''), 'seconds' => $take], (string) $move['to_key'], (string) $move['from_key'], (string) $move['from_label']);
                $back += $take;
            }
            $pdo->prepare('UPDATE atak_role_time_moves SET reverted_at = NOW(), reverted_by = ? WHERE id = ?')
                ->execute([$actorId > 0 ? $actorId : null, $moveId]);
            $pdo->commit();

            return ['ok' => true, 'seconds' => $back];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Journal des corrections d'un membre, la plus récente d'abord, avec le nom de qui a corrigé ou annulé.
     *
     * @return list<array<string, mixed>>
     */
    public function listForUser(int $tenantId, int $userId, int $limit = 20): array
    {
        if ($tenantId < 1 || $userId < 1 || !$this->ensureSchema()) {
            return [];
        }
        $st = $this->pdo()->prepare(
            'SELECT m.id, m.from_label, m.to_label, m.seconds, m.reason, m.created_at, m.reverted_at,
                    a.display_name AS actor_name, r.display_name AS reverted_by_name
             FROM atak_role_time_moves m
             LEFT JOIN users a ON a.id = m.actor_id
             LEFT JOIN users r ON r.id = m.reverted_by
             WHERE m.tenant_id = ? AND m.user_id = ?
             ORDER BY m.id DESC LIMIT ' . max(1, min(100, $limit))
        );
        $st->execute([$tenantId, $userId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @param array{day: string, mission_key: string, seconds: int} $t */
    private function shift(int $tenantId, int $userId, array $t, string $fromKey, string $toKey, string $toLabel): void
    {
        $pdo = $this->pdo();
        $sec = (int) $t['seconds'];
        if ($this->hasMissionKey()) {
            $pdo->prepare(
                "UPDATE atak_screen_time SET seconds = IF(seconds > ?, seconds - ?, 0)
                 WHERE tenant_id = ? AND user_id = ? AND day = ? AND mission_key = ? AND kind = 'role' AND item_key = ?"
            )->execute([$sec, $sec, $tenantId, $userId, $t['day'], $t['mission_key'], $fromKey]);
            $pdo->prepare(
                "INSERT INTO atak_screen_time (tenant_id, user_id, day, mission_key, kind, item_key, item_label, seconds)
                 VALUES (?, ?, ?, ?, 'role', ?, ?, ?)
                 ON DUPLICATE KEY UPDATE seconds = seconds + VALUES(seconds)"
            )->execute([$tenantId, $userId, $t['day'], $t['mission_key'], $toKey, $toLabel, $sec]);

            return;
        }
        $pdo->prepare(
            "UPDATE atak_screen_time SET seconds = IF(seconds > ?, seconds - ?, 0)
             WHERE tenant_id = ? AND user_id = ? AND day = ? AND kind = 'role' AND item_key = ?"
        )->execute([$sec, $sec, $tenantId, $userId, $t['day'], $fromKey]);
        $pdo->prepare(
            "INSERT INTO atak_screen_time (tenant_id, user_id, day, kind, item_key, item_label, seconds)
             VALUES (?, ?, ?, 'role', ?, ?, ?)
             ON DUPLICATE KEY UPDATE seconds = seconds + VALUES(seconds)"
        )->execute([$tenantId, $userId, $t['day'], $toKey, $toLabel, $sec]);
    }

    private function rowSeconds(int $tenantId, int $userId, string $day, string $missionKey, string $key): int
    {
        $sql = "SELECT COALESCE(SUM(seconds), 0) FROM atak_screen_time
                WHERE tenant_id = ? AND user_id = ? AND day = ? AND kind = 'role' AND item_key = ?";
        $args = [$tenantId, $userId, $day, $key];
        if ($this->hasMissionKey()) {
            $sql .= ' AND mission_key = ?';
            $args[] = $missionKey;
        }
        $st = $this->pdo()->prepare($sql);
        $st->execute($args);

        return (int) $st->fetchColumn();
    }

    private function labelFor(int $tenantId, int $userId, string $key): ?string
    {
        $st = $this->pdo()->prepare(
            "SELECT MAX(item_label) FROM atak_screen_time WHERE tenant_id = ? AND user_id = ? AND kind = 'role' AND item_key = ?"
        );
        $st->execute([$tenantId, $userId, $key]);
        $label = trim((string) $st->fetchColumn());

        return $label !== '' ? $label : null;
    }

    private function hasMissionKey(): bool
    {
        if (self::$missionKey === null) {
            try {
                $st = $this->pdo()->query(
                    "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'atak_screen_time' AND COLUMN_NAME = 'mission_key' LIMIT 1"
                );
                self::$missionKey = $st !== false && (bool) $st->fetchColumn();
            } catch (\Throwable) {
                self::$missionKey = false;
            }
        }

        return self::$missionKey;
    }
}
