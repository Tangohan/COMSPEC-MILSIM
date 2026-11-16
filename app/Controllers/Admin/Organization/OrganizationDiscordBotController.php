<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\DiscordBotRepository;
use App\Services\Audit\AuditAction;
use App\Services\Integrations\DiscordBotInvite;
use App\Services\Integrations\DiscordCompanionSettings;

/**
 * Bot Discord Athena : proposer l'ajout à Discord, rattacher le serveur à la communauté, régler salons et rôles, tester, retirer.
 * Réservé aux responsables de la communauté (droit admin.organization). La communauté vient de la session, jamais d'un champ.
 */
final class OrganizationDiscordBotController
{
    private const PAGE = 'back-office/discord-bot';

    public function __construct(private ?DiscordBotRepository $repo = null, private ?DiscordBotInvite $invite = null)
    {
    }

    public function index(Request $request, array $params = []): Response
    {
        $denied = $this->guard();
        if ($denied !== null) {
            return $denied;
        }
        $tenant = (int) Session::get('tenant_id');
        $repo = $this->repo();
        $ready = $repo->schemaReady();
        $withRoles = $request->queryString('roles') === '1';
        $invite = $this->invite();
        $inviteUrl = '';
        if ($invite->configured()) {
            $nonce = $invite->newNonce();
            Session::set('discord_bot_nonce', $nonce);
            $inviteUrl = $invite->inviteUrl($invite->state($tenant, (int) Session::get('user_id'), $nonce), $withRoles);
        }

        return Response::view('layout.main', [
            'title' => 'Bot Discord Athena',
            'content' => 'admin.organization.discord_bot',
            'boPageSubtitle' => 'Le bot accompagne votre bureau des effectifs directement sur le serveur Discord de votre communauté : résumés, candidatures, événements, rappels et accueil des nouveaux arrivants.',
            'backOfficePageCss' => ['back-office-discord-bot.css'],
            'bot' => [
                'ready' => $ready,
                'status' => $ready ? $repo->status($tenant) : 'none',
                'guild' => $ready ? $repo->guildForTenant($tenant) : null,
                'settings' => $ready ? $repo->settings($tenant) : [],
                'channels' => $ready ? $repo->channels($tenant) : [],
                'roles' => $ready ? $repo->roles($tenant) : [],
                'intake' => $ready ? (new \App\Services\Intel\IntelIntakeDiscord())->settings($tenant) : [],
                'invite_url' => $inviteUrl,
                'with_roles' => $withRoles,
                'service_ready' => (new DiscordCompanionSettings())->key() !== '',
                'oauth_ready' => $invite->configured(),
                'app_warning' => (new DiscordCompanionSettings())->inviteWarning(),
                'redirect_uri' => DiscordBotInvite::redirectUri(),
                'settings_url' => url('admin/system/integrations') . '#companion',
            ],
        ]);
    }

    /** Retour de Discord après l'invitation : code, state et guild_id, ou error si la personne a refusé. */
    public function callback(Request $request, array $params = []): Response
    {
        $denied = $this->guard();
        if ($denied !== null) {
            return $denied;
        }
        $nonce = (string) Session::get('discord_bot_nonce', '');
        Session::forget('discord_bot_nonce');
        if ($request->queryString('error') !== '') {
            Session::flash('error', 'Discord n’a pas autorisé l’ajout du bot. Rien n’a été enregistré.');

            return Response::redirect(url(self::PAGE));
        }
        $tenant = (int) Session::get('tenant_id');
        $result = $this->invite()->complete(
            $request->queryString('code'),
            $request->queryString('state'),
            $request->queryString('guild_id'),
            $nonce,
            $tenant,
            (int) Session::get('user_id')
        );
        if ($result['ok']) {
            $this->audit(AuditAction::DISCORD_BOT_LINKED, $tenant, 'serveur');
            Session::flash('success', 'Serveur « ' . ($result['guild'] ?? '') . ' » relié. Le bot va se présenter dans quelques instants ; choisissez ensuite ses salons.');
        } else {
            Session::flash('error', self::errorText((string) ($result['error'] ?? '')));
        }

        return Response::redirect(url(self::PAGE));
    }

    public function save(Request $request, array $params = []): Response
    {
        $denied = $this->guardPost($request);
        if ($denied !== null) {
            return $denied;
        }
        $tenant = (int) Session::get('tenant_id');
        $result = $this->repo()->saveSettings($tenant, [
            'announce_channel_id' => $request->input('announce_channel_id', ''),
            'applications_channel_id' => $request->input('applications_channel_id', ''),
            'events_channel_id' => $request->input('events_channel_id', ''),
            'log_channel_id' => $request->input('log_channel_id', ''),
            'member_role_id' => $request->input('member_role_id', ''),
            'manager_role_id' => $request->input('manager_role_id', ''),
            'sync_roles' => $request->input('sync_roles', ''),
            'reminders_enabled' => $request->input('reminders_enabled', ''),
            'welcome_enabled' => $request->input('welcome_enabled', ''),
        ]);
        if ($result['ok']) {
            $this->audit(AuditAction::DISCORD_BOT_SETTINGS, $tenant, 'reglages');
            Session::flash('success', 'Réglages du bot enregistrés.');
        } else {
            Session::flash('error', self::errorText((string) ($result['error'] ?? '')));
        }

        return Response::redirect(url(self::PAGE));
    }

    /**
     * Notifications des remontées du renseignement : salon, événements, rôle mentionné (réglage à part des rappels).
     */
    public function saveIntake(Request $request, array $params = []): Response
    {
        $denied = $this->guardPost($request);
        if ($denied !== null) {
            return $denied;
        }
        $tenant = (int) Session::get('tenant_id');
        $events = $request->input('intake_events', []);
        $result = (new \App\Services\Intel\IntelIntakeDiscord())->save($tenant, [
            'enabled' => $request->input('intake_enabled', ''),
            'channel_id' => (string) $request->input('intake_channel_id', ''),
            'role_id' => (string) $request->input('intake_role_id', ''),
            'events' => is_array($events) ? $events : [],
        ]);
        if ($result['ok']) {
            $this->audit(AuditAction::DISCORD_BOT_SETTINGS, $tenant, 'remontees');
            Session::flash('success', 'Notifications des remontées enregistrées.');
        } else {
            Session::flash('error', $result['error']);
        }

        return Response::redirect(url(self::PAGE) . '#dbot-remontees');
    }

    /** Bouton « Tester » : vérifie que le bot est vu et demande un message d'essai dans le salon de l'équipe. */
    public function test(Request $request, array $params = []): Response
    {
        $denied = $this->guardPost($request);
        if ($denied !== null) {
            return $denied;
        }
        $tenant = (int) Session::get('tenant_id');
        $repo = $this->repo();
        $status = $repo->status($tenant);
        $s = $repo->settings($tenant);
        $channel = (string) ($s['applications_channel_id'] ?: $s['log_channel_id'] ?: $s['announce_channel_id'] ?: '');
        if ($status === 'none') {
            Session::flash('error', 'Aucun serveur n’est relié : ajoutez d’abord le bot.');
        } elseif ($status !== 'connected') {
            Session::flash('error', 'Le bot ne répond pas encore. Vérifiez qu’il est bien sur votre serveur et que le service du bot tourne, puis réessayez dans quelques minutes.');
        } elseif ($channel === '') {
            Session::flash('error', 'Choisissez d’abord un salon (annonces, candidatures ou journal) pour recevoir le message d’essai.');
        } else {
            $repo->requestTest($tenant);
            Session::flash('success', 'Message d’essai demandé : il arrive dans le salon choisi en moins d’une minute.');
        }

        return Response::redirect(url(self::PAGE));
    }

    public function unlink(Request $request, array $params = []): Response
    {
        $denied = $this->guardPost($request);
        if ($denied !== null) {
            return $denied;
        }
        if ($request->inputString('confirm') !== '1') {
            Session::flash('error', 'Cochez la confirmation pour retirer le serveur.');

            return Response::redirect(url(self::PAGE));
        }
        $tenant = (int) Session::get('tenant_id');
        $this->repo()->unlink($tenant);
        $this->audit(AuditAction::DISCORD_BOT_UNLINKED, $tenant, 'serveur');
        Session::flash('success', 'Serveur délié : le bot ne répond plus pour cette communauté. Vous pouvez aussi le retirer dans Discord (Paramètres du serveur › Intégrations).');

        return Response::redirect(url(self::PAGE));
    }

    /** « Plus tard » sur le rappel du tableau de bord. */
    public function dismiss(Request $request, array $params = []): Response
    {
        $denied = $this->guardPost($request);
        if ($denied !== null) {
            return $denied;
        }
        $this->repo()->dismiss((int) Session::get('tenant_id'), (int) Session::get('user_id'));

        return Response::redirect(url('back-office'));
    }

    /** @return string message visible pour une erreur du parcours */
    public static function errorText(string $code): string
    {
        return match ($code) {
            'state' => 'La demande d’ajout a expiré ou ne vient pas de votre session. Recommencez depuis cette page.',
            'no_guild' => 'Discord n’a pas indiqué de serveur. Choisissez un serveur dans la liste de Discord, puis autorisez.',
            'oauth' => 'Discord a refusé la vérification de votre compte. Réessayez ; si cela continue, l’administrateur de la plateforme doit vérifier les réglages Discord.',
            'not_member' => 'Ce serveur ne fait pas partie de vos serveurs Discord.',
            'not_manager' => 'Il faut la permission « Gérer le serveur » sur ce serveur Discord pour y ajouter le bot.',
            'already_bound' => 'Ce serveur est déjà relié à une communauté, ou votre communauté est déjà reliée à un autre serveur. Déliez d’abord l’ancien lien.',
            'schema' => 'Le bot Discord n’est pas encore installé sur le site (mise à jour de la base à lancer par l’administrateur).',
            'no_guild_linked' => 'Aucun serveur n’est relié.',
            'unknown_channel' => 'Ce salon n’est pas connu du bot : attendez qu’il se soit présenté sur le serveur, puis réessayez.',
            'unknown_role' => 'Ce rôle n’est pas connu du bot : attendez qu’il se soit présenté sur le serveur, puis réessayez.',
            'same_role' => 'Le rôle « membre » et le rôle « responsable » doivent être deux rôles différents.',
            default => 'L’opération a échoué. Réessayez.',
        };
    }

    private function repo(): DiscordBotRepository
    {
        return $this->repo ??= new DiscordBotRepository();
    }

    private function invite(): DiscordBotInvite
    {
        return $this->invite ??= new DiscordBotInvite();
    }

    private function guardPost(Request $request): ?Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url(self::PAGE));
        }

        return $this->guard();
    }

    private function guard(): ?Response
    {
        if (!Session::get('user_id')) {
            return Response::redirect(url('login'));
        }
        if (!Gate::getInstance()->allows('admin.organization')) {
            return (new Response())->setStatusCode(403)->setBody('Accès refusé.');
        }
        if ((int) (Session::get('tenant_id') ?? 0) < 2) {
            return Response::redirect(url('dashboard'));
        }

        return null;
    }

    private function audit(string $action, int $tenantId, string $detail): void
    {
        try {
            \App\Core\Container::get(\App\Services\Audit\AuditService::class)
                ->log($action, $tenantId, (int) Session::get('user_id') ?: null, 'discord_bot', null, null, $detail);
        } catch (\Throwable) {
        }
    }
}
