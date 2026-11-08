<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\BackOffice\Menu\BackOfficeMenuState;
use App\Services\BackOffice\Menu\FavoritesList;

/**
 * Menu latéral du back-office : pastilles en JSON (mise à jour sans rechargement) et favoris.
 *
 * Droits : toute personne connectée qui a un menu back-office ; chaque compteur et chaque favori passe par les droits
 * de la personne dans la communauté de la session (jamais une autre). Écriture : jeton CSRF vérifié ici en plus du
 * filet global /back-office/. Réponse JSON pour un appel en arrière-plan (back-office-menu.js), sinon message et
 * retour à la page d'origine : les boutons marchent sans JavaScript.
 */
final class BackOfficeMenuController
{
    public function __construct(private BackOfficeMenuState $state)
    {
    }

    /** GET /back-office/menu/pastilles */
    public function badges(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId < 1 || $userId < 1) {
            return Response::json(['ok' => false, 'badges' => []], 403);
        }
        $gate = Gate::getInstance();
        $rows = $this->state->badges()->compute(
            $tenantId,
            $userId,
            static fn (string $perm): bool => $gate->allows($perm),
            static fn (string $path): bool => self::visible($path)
        );
        $out = [];
        foreach ($rows as $key => $row) {
            $out[$key] = ['n' => $row['count'], 'important' => $row['important'], 'title' => $row['title']];
        }

        return Response::json(['ok' => true, 'badges' => $out, 'poll' => $this->state->badges()->pollSeconds()]);
    }

    /**
     * POST /back-office/menu/favoris — op = « toggle|chemin[|libellé] », « up|chemin », « down|chemin », « remove|chemin »
     * ou « order » avec order[] (glisser-déposer).
     */
    public function favorite(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        $back = FavoritesList::normalizePath($request->inputString('return')) ?? 'back-office';
        if ($tenantId < 1 || $userId < 1) {
            return $this->reply($request, false, 'Aucune communauté active.', [], $back, 403);
        }
        if (!Csrf::verify($request)) {
            return $this->reply($request, false, 'Session expirée. Rechargez la page.', [], $back, 419);
        }

        // « toggle|chemin|libellé » : l'étoile de l'en-tête porte le titre de la page, même sans JavaScript.
        [$verb, $rawPath, $opLabel] = array_pad(explode('|', $request->inputString('op'), 3), 3, '');
        $verb = strtolower(trim($verb));
        $list = $this->state->favorites($tenantId, $userId);
        $max = $this->state->favoritesMax();

        if ($verb === 'order') {
            $order = $request->input('order');
            $order = is_array($order) ? array_values(array_filter($order, 'is_string')) : [];
            $list = FavoritesList::reorder($list, array_slice($order, 0, 50));

            return $this->save($request, $tenantId, $userId, $list, 'Ordre des favoris enregistré.', $back);
        }

        $path = FavoritesList::normalizePath($rawPath);
        if ($path === null) {
            return $this->reply($request, false, 'Page invalide.', $list, $back, 422);
        }

        switch ($verb) {
            case 'toggle':
                if (!FavoritesList::has($list, $path) && !self::visible($path)) {
                    return $this->reply($request, false, 'Vous n’avez pas accès à cette page.', $list, $back, 403);
                }
                [$list, $result] = FavoritesList::toggle($list, $path, $request->inputString('label') !== '' ? $request->inputString('label') : $opLabel, $max);
                if ($result === 'full') {
                    return $this->reply($request, false, sprintf('%d favoris au plus. Retirez-en un avant d’en ajouter.', $max), $list, $back, 422);
                }

                return $this->save($request, $tenantId, $userId, $list, $result === 'added' ? 'Page ajoutée aux favoris.' : 'Page retirée des favoris.', $back);
            case 'remove':
                return $this->save($request, $tenantId, $userId, FavoritesList::remove($list, $path), 'Page retirée des favoris.', $back);
            case 'up':
            case 'down':
                return $this->save($request, $tenantId, $userId, FavoritesList::move($list, $path, $verb === 'up' ? -1 : 1), 'Ordre des favoris enregistré.', $back);
        }

        return $this->reply($request, false, 'Action inconnue.', $list, $back, 422);
    }

    /** La page est-elle encore dans le menu de la personne (droits + profil de communauté) ? */
    public static function visible(string $path): bool
    {
        $path = trim((string) strtok($path, '?'), '/');
        if ($path === '') {
            return false;
        }
        if (function_exists('portal_nav_href_permission_allowed')) {
            return portal_nav_href_permission_allowed($path);
        }

        return function_exists('back_office_nav_href_permission_allowed') ? back_office_nav_href_permission_allowed($path) : false;
    }

    /** @param list<array{path: string, label: string}> $list */
    private function save(Request $request, int $tenantId, int $userId, array $list, string $done, string $back): Response
    {
        if (!$this->state->saveFavorites($tenantId, $userId, $list)) {
            return $this->reply($request, false, 'Favoris indisponibles pour le moment (mise à jour de la base en attente).', $list, $back, 503);
        }

        return $this->reply($request, true, $done, $list, $back, 200);
    }

    /** @param list<array{path: string, label: string}> $list */
    private function reply(Request $request, bool $ok, string $message, array $list, string $back, int $status): Response
    {
        if (!self::wantsJson($request)) {
            Session::flash($ok ? 'success' : 'error', $message);

            return Response::redirect(url($back));
        }

        return Response::json([
            'ok' => $ok,
            'message' => $message,
            'favorites' => array_map(static fn (array $r): string => $r['path'], $list),
            'max' => $this->state->favoritesMax(),
        ], $status);
    }

    private static function wantsJson(Request $request): bool
    {
        return str_contains(strtolower((string) $request->header('Accept')), 'application/json')
            || strtolower((string) $request->header('X-Requested-With')) === 'xmlhttprequest';
    }
}
