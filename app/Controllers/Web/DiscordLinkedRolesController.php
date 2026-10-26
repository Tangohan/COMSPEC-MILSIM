<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Integrations\DiscordLinkedRoles;

/**
 * Liaison du compte Discord pour les rôles liés : /discord/roles-lies (adresse de vérification déclarée à Discord,
 * aussi le bouton de Mon compte), retour OAuth, mise à jour et retrait depuis Mon compte › Préférences.
 */
final class DiscordLinkedRolesController
{
    private const STATE = 'discord_linked_roles_state';
    private const BACK = 'account/preferences#section-discord';

    public function __construct(private ?DiscordLinkedRoles $service = null)
    {
    }

    /** GET /discord/roles-lies : part vers Discord (le membre est connecté au site). */
    public function start(Request $request, array $params = []): Response
    {
        if (!$this->service()->configured()) {
            Session::flash('discord_link_error', 'La liaison avec Discord n’est pas encore ouverte sur le site.');

            return Response::redirect(url(self::BACK));
        }
        $state = bin2hex(random_bytes(16));
        Session::set(self::STATE, $state);

        return Response::redirect($this->service()->authorizeUrl($state));
    }

    /** GET /discord/roles-lies/retour?code=&state= */
    public function callback(Request $request, array $params = []): Response
    {
        $expected = (string) Session::get(self::STATE, '');
        Session::set(self::STATE, '');
        $state = (string) $request->query('state', '');
        $code = (string) $request->query('code', '');
        if ($expected === '' || !hash_equals($expected, $state)) {
            Session::flash('discord_link_error', 'La liaison a expiré. Recommencez depuis le bouton « Relier mon Discord ».');

            return Response::redirect(url(self::BACK));
        }
        if ($code === '' || $request->query('error') !== null) {
            Session::flash('discord_link_error', 'Liaison annulée sur Discord.');

            return Response::redirect(url(self::BACK));
        }
        $result = $this->service()->link($this->uid(), mb_substr($code, 0, 200));
        Session::flash($result['ok'] ? 'discord_link_success' : 'discord_link_error', $result['message']);

        return Response::redirect(url(self::BACK));
    }

    /** POST /account/discord/actualiser */
    public function sync(Request $request, array $params = []): Response
    {
        if (Csrf::verify($request)) {
            $result = $this->service()->sync($this->uid());
            Session::flash($result['ok'] ? 'discord_link_success' : 'discord_link_error', $result['message']);
        }

        return Response::redirect(url(self::BACK));
    }

    /** POST /account/discord/delier */
    public function unlink(Request $request, array $params = []): Response
    {
        if (Csrf::verify($request)) {
            $this->service()->unlink($this->uid());
            Session::flash('discord_link_success', 'Compte Discord délié : Discord retirera les rôles liés à Athena.');
        }

        return Response::redirect(url(self::BACK));
    }

    private function uid(): int
    {
        return (int) Session::get('user_id', 0);
    }

    private function service(): DiscordLinkedRoles
    {
        return $this->service ??= new DiscordLinkedRoles();
    }
}
