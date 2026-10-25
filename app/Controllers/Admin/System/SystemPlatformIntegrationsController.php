<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Auth\AuthService;
use App\Services\Email\TenantMailSender;
use App\Services\Integrations\DiscordLinkedRoles;
use App\Services\Integrations\DiscordWebhookService;
use App\Services\Platform\PlatformIntegrationSettings;

/**
 * Intégrations de la plateforme (/admin/system/integrations, administrateur de la plateforme seulement) : salons
 * Discord de l'équipe Athena, rôles liés de Discord (comptes du site) et envoi commun des e-mails. Tout est enregistré
 * en base, plus rien dans le .env. Les communautés règlent les leurs dans le back-office (Intégrations externes).
 */
final class SystemPlatformIntegrationsController
{
    private const BASE = 'admin/system/integrations';

    public function __construct(private ?AuthService $auth = null)
    {
    }

    public function index(Request $request, array $params = []): Response
    {
        $user = $this->auth()->user() ?? [];

        return Response::view('layout.main', [
            'title' => 'Intégrations de la plateforme',
            'content' => 'admin.system.platform_integrations',
            'platform_mail_sender' => (new TenantMailSender())->state(TenantMailSender::PLATFORM),
            'mail_providers' => TenantMailSender::PROVIDERS,
            'platform_env_mailer' => strtolower(trim((string) \env('MAIL_MAILER', 'file'))),
            'platform_discord' => (new PlatformIntegrationSettings())->state(),
            'platform_linked_roles' => (new DiscordLinkedRoles())->adminState(),
            'admin_email' => (string) ($user['email'] ?? ''),
            'isPlatformAdminShell' => true,
            'backOfficePageCss' => ['platform-admin.css', 'back-office-integrations.css'],
        ]);
    }

    public function saveDiscord(Request $request, array $params = []): Response
    {
        if (($denied = $this->guard($request)) !== null) {
            return $denied;
        }
        $result = (new PlatformIntegrationSettings())->save([
            PlatformIntegrationSettings::MOD_REPORTS => (string) $request->input('mod_reports_webhook', ''),
            PlatformIntegrationSettings::STARTUP_CHECK => (string) $request->input('startup_check_webhook', ''),
        ]);
        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);

        return Response::redirect(url(self::BASE) . '#salons-discord');
    }

    public function testDiscord(Request $request, array $params = []): Response
    {
        if (($denied = $this->guard($request)) !== null) {
            return $denied;
        }
        $channel = (string) $request->input('channel', '');
        $labels = [
            PlatformIntegrationSettings::MOD_REPORTS => 'Signalements du mod (plateforme)',
            PlatformIntegrationSettings::STARTUP_CHECK => 'Checks de démarrage (plateforme)',
        ];
        $url = PlatformIntegrationSettings::webhook($channel);
        if (!isset($labels[$channel]) || $url === '') {
            Session::flash('error', 'Aucun salon n’est enregistré pour cet usage. Collez le lien webhook, enregistrez, puis réessayez.');

            return Response::redirect(url(self::BASE) . '#salons-discord');
        }
        $result = (new DiscordWebhookService())->send($url, 'Essai Athena : salon « ' . $labels[$channel] . ' » opérationnel.');
        Session::flash(
            !empty($result['ok']) ? 'success' : 'error',
            !empty($result['ok']) ? 'Un message d’essai a été envoyé dans le salon.' : (string) ($result['error'] ?? 'Discord a refusé le message d’essai.')
        );

        return Response::redirect(url(self::BASE) . '#salons-discord');
    }

    public function saveLinkedRoles(Request $request, array $params = []): Response
    {
        if (($denied = $this->guard($request)) !== null) {
            return $denied;
        }
        $result = (new DiscordLinkedRoles())->saveConfig(
            (string) $request->input('linked_roles_client_id', ''),
            (string) $request->input('linked_roles_client_secret', ''),
            (string) $request->input('linked_roles_clear_secret', '') === '1'
        );
        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);

        return Response::redirect(url(self::BASE) . '#roles-lies');
    }

    public function saveMail(Request $request, array $params = []): Response
    {
        if (($denied = $this->guard($request)) !== null) {
            return $denied;
        }
        $input = [];
        foreach (['enabled', 'provider', 'host', 'port', 'encryption', 'username', 'password', 'clear_password', 'from_address', 'from_name', 'reply_to'] as $field) {
            $input[$field] = $request->input($field, '');
        }
        $result = (new TenantMailSender())->save(TenantMailSender::PLATFORM, $input);
        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);

        return Response::redirect(url(self::BASE) . '#emails');
    }

    public function testMail(Request $request, array $params = []): Response
    {
        if (($denied = $this->guard($request)) !== null) {
            return $denied;
        }
        $user = $this->auth()->user() ?? [];
        $result = (new TenantMailSender())->sendTest(TenantMailSender::PLATFORM, trim((string) ($user['email'] ?? '')), email_brand_name());
        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);

        return Response::redirect(url(self::BASE) . '#emails');
    }

    private function auth(): AuthService
    {
        return $this->auth ??= Container::get(AuthService::class);
    }

    /** Les routes passent par SystemAdminMiddleware ; reste le jeton du formulaire. */
    private function guard(Request $request): ?Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url(self::BASE));
        }

        return null;
    }
}
