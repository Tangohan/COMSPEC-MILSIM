<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Request;
use App\Core\Response;

/**
 * Cache court du paquet d'identité (GET /api/sse/identity/pack) et de sa révision.
 *
 * - Clé : communauté + carte + opération + « since » + page. Jamais partagée entre communautés. Le paquet est le même
 *   pour tout appelant autorisé de la communauté (serveur de jeu, Zeus, session Confidentiel) : rien de propre à un
 *   compte n'y entre, et l'autorisation est vérifiée avant chaque lecture du cache.
 * - Invalidation : toute écriture réussie qui peut toucher le registre (portail SSE, papiers, couvertures, identités,
 *   envois du jeu vers /api/sse/) change la génération ; la lecture suivante recalcule. Une écriture hors HTTP (cron)
 *   se voit au plus tard à la fin du TTL.
 * - « generated_at » du paquet gardé = heure du calcul : un client qui s'en sert comme « since » relit au pire un peu
 *   plus de papiers, jamais moins.
 *
 * Désactivé en CLI comme ShortCache (tests : ShortCache::setEnabled).
 */
final class SseIdentityPackCache
{
    public const TTL = 300;

    private const GEN_NS = 'sse_idpack';

    /** Écritures qui peuvent changer le paquet (portail SSE, papiers du membre, couverture, identité, envois du jeu). */
    private const WRITE_PATH_RE = '#^/(atak/sse/|api/sse/)|/papiers|/couverture|/renseignement|identit#i';

    public static function key(int $tenantId, int $mapId, string $opKey, string $since, int $offset, int $limit): string
    {
        return 'sse_idpack:' . $tenantId . ':' . $mapId . ':'
            . substr(sha1($opKey . "\x1f" . $since . "\x1f" . $offset . "\x1f" . $limit), 0, 16)
            . ':' . ShortCache::gen(self::GEN_NS);
    }

    public static function invalidate(): void
    {
        ShortCache::bump(self::GEN_NS);
    }

    /** Après une écriture réussie qui touche le registre d'identité : le paquet suivant est recalculé. */
    public static function invalidateAfter(Request $request, Response $response): void
    {
        $method = strtoupper($request->method());
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true) || $response->statusCode() >= 400) {
            return;
        }
        if (self::touchesRegistry($request->path())) {
            self::invalidate();
        }
    }

    public static function touchesRegistry(string $path): bool
    {
        return preg_match(self::WRITE_PATH_RE, $path) === 1;
    }

    /** ETag faible tiré de la révision (stable tant que le contenu de la page ne change pas). */
    public static function etag(string $revision): string
    {
        return 'W/"idp-' . $revision . '"';
    }

    public static function ifNoneMatchHits(?string $ifNoneMatch, string $etag): bool
    {
        if ($ifNoneMatch === null || trim($ifNoneMatch) === '') {
            return false;
        }
        $bare = static fn (string $t): string => trim(preg_replace('/^W\//', '', trim($t)) ?? '', '"');
        foreach (explode(',', $ifNoneMatch) as $candidate) {
            if ($bare($candidate) === $bare($etag)) {
                return true;
            }
        }

        return false;
    }
}
