<?php

declare(strict_types=1);

namespace App\Services\Watch;

/**
 * Règles du ralentissement sous charge, sans état ni base : quelles routes ne sont JAMAIS ralenties, lesquelles peuvent
 * l'être, quelles formules sont touchées à chaque palier.
 *
 * Palier (LoadLevel) : 0 = rien ; 1 = communautés Gratuit ; 2 = Gratuit et Standard ; 3 = Gratuit, Standard et Pro.
 * Pro+ n'est jamais ralenti. Rang de formule (PlanGift::rank) : 0 Gratuit, 1 Standard, 2 Pro, 3 Pro+.
 *
 * Sont ralentis, et eux seuls : les envois et lectures du jeu (clé ou jeton du mod) sous /api/, et les pages du portail
 * ouvertes en GET par un navigateur. Jamais les écritures d'un navigateur ni ses appels /api/ : le client web met en
 * pause toutes ses écritures (dont les ordres) après un 503, ce qui couperait une alerte de commandement.
 */
final class LoadShedPolicy
{
    /** Chemins exacts ou préfixes (terminés par « / » : tout le dossier) qui ne sont jamais ralentis. */
    public const NEVER = [
        // Connexion, session, compte.
        '/login', '/logout', '/register', '/forgot-password', '/reset-password', '/resend-verification', '/verify-email',
        '/security/', '/account/', '/community/switch', '/community/resolve-code', '/invitations/accept', '/i/',
        '/api/me/', '/api/alerts/', '/api/game/v1/',
        '/api/atak/client-init', '/api/atak/game-link/', '/api/atak/pair/', '/api/atak/recovery/', '/api/atak/session-restore',
        '/api/atak/beta-register',
        // Paiements et webhooks.
        '/api/stripe/', '/api/paypal/', '/platform/upgrade', '/paiement/', '/soutenir-atak', '/communities/create/pay',
        // Alertes de commandement et sécurité des joueurs (ordres, alertes plein écran, santé, appui, SALUTE).
        '/api/atak/orders', '/api/atak/overwatch/alerts', '/api/atak/tactical-alerts', '/api/atak/device-alerts',
        '/api/atak/zones/alerts', '/api/atak/salute', '/api/medical-alerts', '/api/nine-line', '/api/cas',
        '/api/fire-support', '/api/chat', '/api/pings',
        // Administration de la plateforme et outils d'exploitation.
        '/admin', '/api/admin/', '/cron/', '/run-migrations',
        // Signalement d'erreurs du mod et sondes de vie.
        '/api/atak/mod-report', '/api/atak/startup-check', '/api/atak/device-logs', '/api/atak/ping', '/api/atak/version',
        '/api/atak/whoami', '/api/health', '/api/system/',
        // Interface opérationnelle (carte ATAK, téléphone) et ressources de page.
        '/atak', '/m/', '/connect', '/assets/', '/uploads/', '/sw.js', '/manifest.webmanifest', '/favicon.ico',
        '/robots.txt', '/sitemap.xml',
    ];

    /** Part (en %) de requêtes ralenties pour une formule visée : adoucit la coupure, jamais de blocage total. */
    public const DEFAULT_PERCENT = 60;

    public static function isNeverSlowed(string $path): bool
    {
        $p = strtolower('/' . trim($path, '/'));
        $p = $p === '' ? '/' : $p;
        foreach (self::NEVER as $rule) {
            if (str_ends_with($rule, '/')) {
                if (str_starts_with($p . '/', $rule)) {
                    return true;
                }
                continue;
            }
            if ($p === $rule || str_starts_with($p, $rule . '/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Une requête peut-elle être ralentie ? Faux par défaut pour tout ce qui n'est pas explicitement visé.
     *
     * @param bool $gameClient requête portée par la clé ou le jeton du mod
     */
    public static function isSheddable(string $method, string $path, bool $gameClient): bool
    {
        $method = strtoupper($method);
        if (self::isNeverSlowed($path)) {
            return false;
        }
        $isApi = str_starts_with(strtolower($path), '/api/');
        if ($gameClient) {
            return $isApi;
        }

        return $method === 'GET' && !$isApi;
    }

    /** La formule de rang $rank est-elle visée au palier $level ? Pro+ (3) ne l'est jamais. */
    public static function tierTargeted(int $rank, int $level): bool
    {
        return $level >= 1 && $rank >= 0 && $rank < 3 && $rank < $level;
    }

    /**
     * Décision pour une requête : formule visée et tirage sous la part ralentie. $roll vaut [0, 1) (injectable en test).
     */
    public static function shouldShed(int $rank, int $level, int $percent, float $roll): bool
    {
        if (!self::tierTargeted($rank, $level)) {
            return false;
        }
        $percent = max(10, min(100, $percent));

        return $roll * 100 < $percent;
    }

    /** Attente demandée au client (secondes) : plus longue à un palier élevé, étalée par une gigue pour éviter le retour groupé. */
    public static function retryAfterSeconds(int $level, int $jitter): int
    {
        $base = [0 => 30, 1 => 30, 2 => 45, 3 => 60][max(0, min(3, $level))];

        return $base + max(0, min(15, $jitter));
    }

    /** @return list<string> */
    public static function neverList(): array
    {
        return self::NEVER;
    }
}
