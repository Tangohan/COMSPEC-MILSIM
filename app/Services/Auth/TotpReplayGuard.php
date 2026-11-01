<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Support\LazyDatabaseConnection;
use PDO;

/**
 * Anti-rejeu des codes de l'application d'authentification : un code (un pas de 30 s) ne sert qu'une fois.
 * On garde le dernier pas accepté par membre ; un code d'un pas égal ou antérieur est refusé, même s'il est
 * encore dans la fenêtre de tolérance d'horloge.
 *
 * Table user_totp_used_steps : étape user_totp_used_steps de la file active (run-migrations.php) ;
 * ensureSchema() reste un filet de sécurité idempotent.
 * SQL portable MySQL / SQLite (tests).
 */
final class TotpReplayGuard
{
    use LazyDatabaseConnection;

    private ?bool $ready = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    public function ensureSchema(): bool
    {
        if ($this->ready !== null) {
            return $this->ready;
        }
        try {
            $this->pdo()->exec(
                'CREATE TABLE IF NOT EXISTS user_totp_used_steps (
                    user_id INT NOT NULL PRIMARY KEY,
                    last_step BIGINT NOT NULL,
                    updated_at DATETIME NULL
                )'
            );
            $this->ready = true;
        } catch (\Throwable) {
            $this->ready = false;
        }

        return $this->ready;
    }

    /**
     * Réserve le pas $step pour ce membre. false = déjà utilisé (ou un pas plus récent l'a été).
     * Base indisponible : true (on ne bloque pas toutes les connexions pour une protection d'appoint).
     */
    public function claim(int $userId, int $step): bool
    {
        if ($userId < 1 || !$this->ensureSchema()) {
            return true;
        }
        $now = date('Y-m-d H:i:s');
        try {
            $up = $this->pdo()->prepare('UPDATE user_totp_used_steps SET last_step = ?, updated_at = ? WHERE user_id = ? AND last_step < ?');
            $up->execute([$step, $now, $userId, $step]);
            if ($up->rowCount() === 1) {
                return true;
            }
            $exists = $this->pdo()->prepare('SELECT 1 FROM user_totp_used_steps WHERE user_id = ?');
            $exists->execute([$userId]);
            if ($exists->fetchColumn() !== false) {
                return false;
            }
            $this->pdo()->prepare('INSERT INTO user_totp_used_steps (user_id, last_step, updated_at) VALUES (?, ?, ?)')
                ->execute([$userId, $step, $now]);

            return true;
        } catch (\PDOException) {
            // Insertion concurrente du même membre : l'autre requête a gagné ce pas.
            return false;
        }
    }
}
