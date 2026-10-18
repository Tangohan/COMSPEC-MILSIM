<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Request;
use App\Core\Response;

/**
 * Allège les réponses JSON de l'API envoyées au jeu (COMSPEC Link) : le mod interroge Athena toutes les 2 à 10 s
 * (messages, marqueurs, formes, ordres…) et recevait chaque fois la liste complète, même inchangée.
 *
 * - ETag : empreinte du corps. Si COMSPEC Link renvoie la même (If-None-Match), réponse 304 sans corps ; il
 *   ressert alors la copie qu'il a gardée. Le corps n'est jamais modifié : une réponse qui change (horodatage
 *   serveur compris) repart en entier.
 * - gzip : corps compressé quand le client l'accepte (JSON : 5 à 10 fois plus petit).
 *
 * Seulement pour COMSPEC Link (User-Agent « COMSPECExtension/… » ou « COMSPECATAKNativeExtension/… »), en GET
 * sur /api/, réponse 200 JSON : le site et les navigateurs ne voient aucune différence.
 */
final class GameApiResponseCompactor
{
    /** En dessous, l'en-tête gzip coûte plus qu'il ne rapporte. */
    public const GZIP_MIN_BYTES = 1024;

    public static function isGameClient(?string $userAgent): bool
    {
        $ua = (string) $userAgent;

        return str_starts_with($ua, 'COMSPECExtension/') || str_starts_with($ua, 'COMSPECATAKNativeExtension/');
    }

    public static function etagFor(string $body): string
    {
        return 'W/"' . substr(hash('sha1', $body), 0, 20) . '"';
    }

    public static function apply(Request $request, Response $response): void
    {
        if (!$request->isGet() || !str_starts_with($request->path(), '/api/')) {
            return;
        }
        if (!self::isGameClient($request->userAgent())) {
            return;
        }
        if ($response->statusCode() !== 200 || $response->hasBodyStream()) {
            return;
        }
        if (!str_starts_with((string) ($response->headerValue('Content-Type') ?? ''), 'application/json')) {
            return;
        }
        $body = $response->body();
        if ($body === '') {
            return;
        }

        $etag = self::etagFor($body);
        $response->header('ETag', $etag);
        $response->header('Vary', 'Accept-Encoding');
        if (self::matches($request->header('If-None-Match'), $etag)) {
            $response->setStatusCode(304)->setBody('');
            return;
        }

        $accept = strtolower((string) ($request->header('Accept-Encoding') ?? ''));
        if (strlen($body) >= self::GZIP_MIN_BYTES && str_contains($accept, 'gzip') && function_exists('gzencode')) {
            $gz = gzencode($body, 5);
            if (is_string($gz) && strlen($gz) < strlen($body)) {
                $response->header('Content-Encoding', 'gzip');
                $response->setBody($gz);
            }
        }
    }

    private static function matches(?string $ifNoneMatch, string $etag): bool
    {
        if ($ifNoneMatch === null || trim($ifNoneMatch) === '') {
            return false;
        }
        $bare = static fn (string $t): string => preg_replace('/^W\//', '', trim($t)) ?? '';
        foreach (explode(',', $ifNoneMatch) as $candidate) {
            if ($bare($candidate) === $bare($etag)) {
                return true;
            }
        }

        return false;
    }
}
