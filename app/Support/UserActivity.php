<?php

declare(strict_types=1);

namespace App\Support;

use PDO;

/**
 * Dernière activité réelle d'un compte (users.last_active_at).
 *
 * users.last_login_at n'est posé qu'à la connexion : les sessions restent ouvertes très longtemps, un compte actif
 * paraissait donc inactif. last_active_at est rafraîchi par AuthMiddleware au plus une fois par heure et par session
 * (l'heure du dernier rafraîchissement est gardée en session : aucune requête de plus à chaque page), et à la connexion.
 * Les calculs d'inactivité lisent la plus récente des deux dates (lastSeenSql), colonne absente ou NULL tolérés.
 */
final class UserActivity
{
    /** Clé de session : heure (timestamp) du dernier rafraîchissement. */
    public const SESSION_KEY = 'last_active_touch_at';
    /** Intervalle minimal entre deux écritures pour une même session, en secondes. */
    public const TOUCH_INTERVAL = 3600;

    /** Faut-il écrire maintenant ? Vrai si jamais fait, ou si le dernier rafraîchissement date d'une heure ou plus. */
    public static function shouldTouch(mixed $lastTouchAt, int $now): bool
    {
        if (!is_int($lastTouchAt) && !(is_string($lastTouchAt) && ctype_digit($lastTouchAt))) {
            return true;
        }

        return $now - (int) $lastTouchAt >= self::TOUCH_INTERVAL || (int) $lastTouchAt > $now;
    }

    /**
     * Écrit l'activité. Ne lève jamais : base sans la colonne (avant migration) ou erreur passagère sont ignorées.
     * Même horloge que last_login_at (NOW() de la base), pour que la comparaison des deux dates soit cohérente.
     */
    public static function touch(PDO $pdo, int $userId): bool
    {
        try {
            $clock = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? "datetime('now')" : 'NOW()';
            $st = $pdo->prepare("UPDATE users SET last_active_at = {$clock} WHERE id = ?");
            $st->execute([$userId]);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Expression SQL « dernière activité connue » : la plus récente de last_login_at et last_active_at, NULL si aucune.
     * (GREATEST renvoie NULL dès qu'un argument l'est : chaque côté retombe sur l'autre.)
     */
    public static function lastSeenSql(string $alias, bool $hasLogin, bool $hasActive): string
    {
        $login = $alias . '.last_login_at';
        $active = $alias . '.last_active_at';
        if ($hasLogin && $hasActive) {
            return "GREATEST(COALESCE({$login}, {$active}), COALESCE({$active}, {$login}))";
        }
        if ($hasActive) {
            return $active;
        }

        return $hasLogin ? $login : 'NULL';
    }
}
