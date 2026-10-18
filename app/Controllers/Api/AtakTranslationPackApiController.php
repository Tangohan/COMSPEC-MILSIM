<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\AtakTranslationPackRepository;
use App\Services\Security\FileRateLimiter;
use App\Support\AtakTranslationTemplate;

/**
 * API des packs de langue partagés de l'atelier public /traduire-atak (voir AtakTranslationPackRepository).
 *
 * Pas de session ni de cookie : le jeton du lien arrive dans l'en-tête X-Pack-Token (tiré du fragment # de l'URL
 * par la page), le pseudo dans le corps JSON. Pas de jeton CSRF, puisque rien ne dépend des cookies du visiteur.
 * Limites par adresse IP (fichier de compteurs, l'adresse n'est pas gardée en base).
 */
final class AtakTranslationPackApiController
{
    private const GAME_LANGUAGES = ['', 'Spanish', 'German', 'Italian', 'Polish', 'Portuguese', 'Russian', 'Czech', 'Turkish', 'Chinesesimp', 'Chinese', 'Japanese', 'Korean'];

    public function __construct(
        private ?AtakTranslationPackRepository $packs = null,
        private ?FileRateLimiter $limiter = null,
    ) {
        $this->packs ??= new AtakTranslationPackRepository();
        $this->limiter ??= new FileRateLimiter();
    }

    /** POST /api/traduire-atak/packs : nouveau pack ; renvoie les deux liens (propriétaire, contributeurs). */
    public function create(Request $request, array $params = []): Response
    {
        if (($r = $this->guard($request, 'create', 8, 3600)) !== null) {
            return $r;
        }
        $body = self::body();
        $code = AtakTranslationPackRepository::validCode($body['code'] ?? null);
        $name = AtakTranslationPackRepository::cleanLabel($body['name'] ?? '', 40);
        if ($code === null || $name === '') {
            return self::error('invalid_meta', 422);
        }
        $created = $this->repo()->create([
            'code' => $code,
            'name' => $name,
            'game' => self::game($body['game'] ?? ''),
            'author_label' => AtakTranslationPackRepository::cleanLabel($body['author_label'] ?? '', 60),
            'review_mode' => ($body['review_mode'] ?? '') === 'review' ? 'review' : 'direct',
        ]);

        return Response::json(['ok' => true] + $created, 201);
    }

    /** GET /api/traduire-atak/packs/{id}?since=N : réglages, rôle et changements depuis rev N. */
    public function show(Request $request, array $params = []): Response
    {
        if (($r = $this->guard($request, 'read', 600, 600)) !== null) {
            return $r;
        }
        $opened = $this->openPack($request, $params);
        if ($opened instanceof Response) {
            return $opened;
        }
        $since = max(0, (int) $request->query('since', 0));
        $pack = $opened['pack'];

        return Response::json([
            'ok' => true,
            'role' => $opened['role'],
            'pack' => AtakTranslationPackRepository::publicMeta($pack),
        ] + $this->repo()->changes((int) $pack['id'], $since));
    }

    /** POST /api/traduire-atak/packs/{id}/strings : {h, text, base_rev, author} ; propose si le pack est en revue. */
    public function write(Request $request, array $params = []): Response
    {
        if (($r = $this->guard($request, 'write', 900, 600)) !== null) {
            return $r;
        }
        $opened = $this->openPack($request, $params);
        if ($opened instanceof Response) {
            return $opened;
        }
        $body = self::body();
        $h = AtakTranslationPackRepository::validKey($body['h'] ?? null);
        $text = AtakTranslationPackRepository::cleanText($body['text'] ?? null);
        if ($h === null || $text === null || !AtakTranslationTemplate::hasKey($h)) {
            return self::error('invalid_string', 422);
        }
        $author = AtakTranslationPackRepository::cleanAuthor($body['author'] ?? '');
        $packId = (int) $opened['pack']['id'];
        if ($opened['role'] !== AtakTranslationPackRepository::ROLE_OWNER && $opened['pack']['review_mode'] === 'review') {
            $rev = $this->repo()->suggest($packId, $h, $text, $author);

            return Response::json(['ok' => true, 'suggested' => true, 'rev' => $rev]);
        }
        $res = $this->repo()->write($packId, $h, $text, $author, max(0, (int) ($body['base_rev'] ?? 0)));
        if (!$res['ok']) {
            return Response::json(['ok' => false, 'error' => 'conflict', 'current' => $res['conflict'] ?? null], 409);
        }

        return Response::json(['ok' => true, 'rev' => $res['rev'] ?? 0]);
    }

    /** POST /api/traduire-atak/packs/{id}/bulk (propriétaire) : {items: {h: texte}, overwrite, author}. */
    public function bulk(Request $request, array $params = []): Response
    {
        if (($r = $this->guard($request, 'bulk', 60, 600)) !== null) {
            return $r;
        }
        $opened = $this->openPack($request, $params, true);
        if ($opened instanceof Response) {
            return $opened;
        }
        $body = self::body();
        $raw = is_array($body['items'] ?? null) ? $body['items'] : [];
        if (count($raw) > AtakTranslationPackRepository::MAX_BULK) {
            return self::error('too_many', 413);
        }
        $items = [];
        foreach ($raw as $h => $text) {
            $h = AtakTranslationPackRepository::validKey((string) $h);
            $text = AtakTranslationPackRepository::cleanText($text);
            if ($h !== null && $text !== null && $text !== '' && AtakTranslationTemplate::hasKey($h)) {
                $items[$h] = $text;
            }
        }
        $n = $this->repo()->bulk((int) $opened['pack']['id'], $items, AtakTranslationPackRepository::cleanAuthor($body['author'] ?? ''), (bool) ($body['overwrite'] ?? false));

        return Response::json(['ok' => true, 'written' => $n, 'ignored' => count($raw) - count($items)]);
    }

    /** POST /api/traduire-atak/packs/{id}/suggestions/{sid} (propriétaire) : {decision: accept|reject}. */
    public function decide(Request $request, array $params = []): Response
    {
        if (($r = $this->guard($request, 'write', 900, 600)) !== null) {
            return $r;
        }
        $opened = $this->openPack($request, $params, true);
        if ($opened instanceof Response) {
            return $opened;
        }
        $decision = (string) (self::body()['decision'] ?? '');
        if (!in_array($decision, ['accept', 'reject'], true)) {
            return self::error('invalid_decision', 422);
        }
        $s = $this->repo()->decide((int) $opened['pack']['id'], (int) ($params['sid'] ?? 0), $decision === 'accept');

        return $s === null ? self::error('not_found', 404) : Response::json(['ok' => true]);
    }

    /** GET /api/traduire-atak/packs/{id}/history?h= : dernières versions d'une phrase. */
    public function history(Request $request, array $params = []): Response
    {
        if (($r = $this->guard($request, 'read', 600, 600)) !== null) {
            return $r;
        }
        $opened = $this->openPack($request, $params);
        if ($opened instanceof Response) {
            return $opened;
        }
        $h = AtakTranslationPackRepository::validKey($request->query('h'));
        if ($h === null) {
            return self::error('invalid_string', 422);
        }

        return Response::json(['ok' => true, 'history' => $this->repo()->history((int) $opened['pack']['id'], $h)]);
    }

    /** POST /api/traduire-atak/packs/{id}/revert (propriétaire) : {history_id, author}. */
    public function revert(Request $request, array $params = []): Response
    {
        if (($r = $this->guard($request, 'write', 900, 600)) !== null) {
            return $r;
        }
        $opened = $this->openPack($request, $params, true);
        if ($opened instanceof Response) {
            return $opened;
        }
        $body = self::body();
        $rev = $this->repo()->revert((int) $opened['pack']['id'], (int) ($body['history_id'] ?? 0), AtakTranslationPackRepository::cleanAuthor($body['author'] ?? ''));

        return $rev === null ? self::error('not_found', 404) : Response::json(['ok' => true, 'rev' => $rev]);
    }

    /** POST /api/traduire-atak/packs/{id}/settings (propriétaire) : nom, langue du jeu, auteur, mode de revue. */
    public function settings(Request $request, array $params = []): Response
    {
        if (($r = $this->guard($request, 'write', 900, 600)) !== null) {
            return $r;
        }
        $opened = $this->openPack($request, $params, true);
        if ($opened instanceof Response) {
            return $opened;
        }
        $body = self::body();
        $pack = $opened['pack'];
        $name = AtakTranslationPackRepository::cleanLabel($body['name'] ?? $pack['lang_name'], 40);
        $this->repo()->updateSettings((int) $pack['id'], [
            'name' => $name === '' ? (string) $pack['lang_name'] : $name,
            'game' => self::game($body['game'] ?? $pack['game_language']),
            'author_label' => AtakTranslationPackRepository::cleanLabel($body['author_label'] ?? $pack['author_label'], 60),
            'review_mode' => ($body['review_mode'] ?? $pack['review_mode']) === 'review' ? 'review' : 'direct',
        ]);

        return Response::json(['ok' => true]);
    }

    /** POST /api/traduire-atak/packs/{id}/rotate (propriétaire) : nouveau lien contributeur, l'ancien est coupé. */
    public function rotate(Request $request, array $params = []): Response
    {
        if (($r = $this->guard($request, 'write', 900, 600)) !== null) {
            return $r;
        }
        $opened = $this->openPack($request, $params, true);
        if ($opened instanceof Response) {
            return $opened;
        }

        return Response::json(['ok' => true, 'edit_token' => $this->repo()->rotateEditToken((int) $opened['pack']['id'])]);
    }

    /** POST /api/traduire-atak/packs/{id}/delete (propriétaire) : supprime le pack et tout son contenu. */
    public function delete(Request $request, array $params = []): Response
    {
        if (($r = $this->guard($request, 'write', 900, 600)) !== null) {
            return $r;
        }
        $opened = $this->openPack($request, $params, true);
        if ($opened instanceof Response) {
            return $opened;
        }
        $this->repo()->delete((int) $opened['pack']['id']);

        return Response::json(['ok' => true]);
    }

    // ---------- Outils ----------

    private function repo(): AtakTranslationPackRepository
    {
        return $this->packs ?? new AtakTranslationPackRepository();
    }

    /** Limite de débit par IP et tables présentes ; null si la requête peut continuer. */
    private function guard(Request $request, string $bucket, int $max, int $window): ?Response
    {
        if ($this->limiter !== null && $this->limiter->tooManyAttempts('atak-tr:' . $bucket . ':' . $request->ip(), $max, $window)) {
            return self::error('rate_limited', 429);
        }
        if (!$this->repo()->schemaReady()) {
            return self::error('unavailable', 503);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $params
     * @return array{pack: array<string, mixed>, role: string}|Response
     */
    private function openPack(Request $request, array $params, bool $ownerOnly = false): array|Response
    {
        $id = AtakTranslationPackRepository::validPublicId($params['id'] ?? null);
        $token = trim((string) $request->header('X-Pack-Token'));
        $opened = $id === null ? null : $this->repo()->open($id, $token);
        if ($opened === null) {
            // Même réponse pour un pack inconnu et un mauvais jeton : rien à deviner.
            return self::error('not_found', 404);
        }
        if ($ownerOnly && $opened['role'] !== AtakTranslationPackRepository::ROLE_OWNER) {
            return self::error('owner_only', 403);
        }

        return $opened;
    }

    /** @return array<string, mixed> */
    private static function body(): array
    {
        $raw = (string) file_get_contents('php://input');
        if ($raw === '' || strlen($raw) > 8 * 1024 * 1024) {
            return [];
        }
        $data = json_decode($raw, true);

        return is_array($data) ? $data : [];
    }

    private static function game(mixed $g): string
    {
        return is_string($g) && in_array($g, self::GAME_LANGUAGES, true) ? $g : '';
    }

    private static function error(string $code, int $status): Response
    {
        return Response::json(['ok' => false, 'error' => $code], $status);
    }
}
