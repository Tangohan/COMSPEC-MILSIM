<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\LazyDatabaseConnection;
use PDO;

/**
 * « Sur mon téléphone » : liens demandés par l'ATAK en jeu (table atak_mobile_links, étape 3690).
 * Seule l'empreinte SHA-256 du jeton est gardée ; un jeton ne sert qu'une fois (redeem atomique) et seulement
 * avant son expiration. Le lien racheté devient la session mobile, valable jusqu'à session_expires_at sauf révocation.
 * Heures en UTC (gmdate), comparées côté PHP pour rester portables.
 */
class AtakMobileLinkRepository
{
    use LazyDatabaseConnection;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    public function ready(): bool
    {
        try {
            $this->pdo()->query('SELECT 1 FROM atak_mobile_links LIMIT 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param array{tenant_id: int, user_id: int, steam_uid: ?string, mission_key: ?string, map_id: int, app: string,
     *              player_name: string, pos_x: ?float, pos_y: ?float, context_json: ?string, token_hash: string,
     *              token_expires_at: string} $row
     */
    public function create(array $row): int
    {
        $st = $this->pdo()->prepare(
            'INSERT INTO atak_mobile_links
                (tenant_id, user_id, steam_uid, mission_key, map_id, app, player_name, pos_x, pos_y, context_json, token_hash, token_expires_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            $row['tenant_id'],
            $row['user_id'],
            $row['steam_uid'],
            $row['mission_key'],
            max(1, $row['map_id']),
            $row['app'],
            mb_substr($row['player_name'], 0, 80),
            $row['pos_x'],
            $row['pos_y'],
            $row['context_json'],
            $row['token_hash'],
            $row['token_expires_at'],
            gmdate('Y-m-d H:i:s'),
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * Rachat du jeton : une seule fois, avant expiration, lien non révoqué. Renvoie le lien devenu session, sinon null.
     */
    public function redeem(string $tokenHash, string $sessionExpiresAt, string $ip, string $userAgent, ?int $now = null): ?array
    {
        $nowSql = gmdate('Y-m-d H:i:s', $now ?? time());
        $st = $this->pdo()->prepare(
            'UPDATE atak_mobile_links SET redeemed_at = ?, session_expires_at = ?, last_seen_at = ?, ip = ?, user_agent = ?
             WHERE token_hash = ? AND redeemed_at IS NULL AND revoked_at IS NULL AND token_expires_at > ?'
        );
        $st->execute([$nowSql, $sessionExpiresAt, $nowSql, mb_substr($ip, 0, 45), mb_substr($userAgent, 0, 255), $tokenHash, $nowSql]);
        if ($st->rowCount() !== 1) {
            return null;
        }
        $find = $this->pdo()->prepare('SELECT * FROM atak_mobile_links WHERE token_hash = ? LIMIT 1');
        $find->execute([$tokenHash]);
        $row = $find->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** Session mobile encore valable (rachetée, non révoquée, non expirée), pour cette communauté. */
    public function findActive(int $id, int $tenantId, ?int $now = null): ?array
    {
        if ($id < 1 || $tenantId < 1) {
            return null;
        }
        $st = $this->pdo()->prepare(
            'SELECT * FROM atak_mobile_links WHERE id = ? AND tenant_id = ? AND redeemed_at IS NOT NULL AND revoked_at IS NULL
               AND session_expires_at > ? LIMIT 1'
        );
        $st->execute([$id, $tenantId, gmdate('Y-m-d H:i:s', $now ?? time())]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** Raccourcit la session d'un lien (espace d'administration : une heure). Jamais plus long qu'avant. */
    public function shortenSession(int $id, string $expiresAt): void
    {
        $this->pdo()->prepare('UPDATE atak_mobile_links SET session_expires_at = ? WHERE id = ? AND session_expires_at > ?')
            ->execute([$expiresAt, $id, $expiresAt]);
    }

    public function touch(int $id): void
    {
        $this->pdo()->prepare('UPDATE atak_mobile_links SET last_seen_at = ? WHERE id = ?')->execute([gmdate('Y-m-d H:i:s'), $id]);
    }

    /** Contexte (fiche du CR fin d'op…) remplacé, par exemple une fois la fiche remise. */
    public function setContext(int $id, ?string $json): void
    {
        $this->pdo()->prepare('UPDATE atak_mobile_links SET context_json = ? WHERE id = ?')->execute([$json, $id]);
    }

    /**
     * Sessions mobiles d'un membre (Appareils connectés) : actives d'abord, 20 au plus.
     *
     * @return list<array<string, mixed>>
     */
    public function listForUser(int $tenantId, int $userId, ?int $now = null): array
    {
        $st = $this->pdo()->prepare(
            'SELECT id, app, mission_key, player_name, redeemed_at, session_expires_at, last_seen_at, ip, user_agent, created_at
             FROM atak_mobile_links
             WHERE tenant_id = ? AND user_id = ? AND redeemed_at IS NOT NULL AND revoked_at IS NULL AND session_expires_at > ?
             ORDER BY id DESC LIMIT 20'
        );
        $st->execute([$tenantId, $userId, gmdate('Y-m-d H:i:s', $now ?? time())]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** Révocation par le membre (une session, ou toutes avec $id = 0). Renvoie le nombre de sessions coupées. */
    public function revoke(int $tenantId, int $userId, int $id = 0): int
    {
        $sql = 'UPDATE atak_mobile_links SET revoked_at = ? WHERE tenant_id = ? AND user_id = ? AND revoked_at IS NULL';
        $args = [gmdate('Y-m-d H:i:s'), $tenantId, $userId];
        if ($id > 0) {
            $sql .= ' AND id = ?';
            $args[] = $id;
        }
        $st = $this->pdo()->prepare($sql);
        $st->execute($args);

        return $st->rowCount();
    }

    /** Jetons demandés par ce joueur depuis $since (limite de débit côté jeu). */
    public function countIssuedSince(int $tenantId, int $userId, string $since): int
    {
        $st = $this->pdo()->prepare('SELECT COUNT(*) FROM atak_mobile_links WHERE tenant_id = ? AND user_id = ? AND created_at >= ?');
        $st->execute([$tenantId, $userId, $since]);

        return (int) $st->fetchColumn();
    }
}
