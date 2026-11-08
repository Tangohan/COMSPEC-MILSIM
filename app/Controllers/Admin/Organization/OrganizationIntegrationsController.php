<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TenantApiKeyRepository;
use App\Repositories\TenantRepository;
use App\Services\Audit\AuditAction;
use App\Services\Auth\AuthService;
use App\Services\Email\TenantMailSender;
use App\Services\Integrations\DiscordEventRelayService;
use App\Services\Integrations\DiscordWebhookDirectory;
use App\Services\Integrations\DiscordWebhookService;
use App\Support\ProfileInviteDiscordMessage;
use App\Services\Platform\FeatureGateService;
use App\Services\Sse\SseTransmissionDiscordService;
use App\Support\DiscordWebhookCatalog;

final class OrganizationIntegrationsController
{
    private const AVAILABLE_SCOPES = ['events:read'];

    public function __construct(
        private AuthService $auth,
        private TenantApiKeyRepository $apiKeys,
        private FeatureGateService $featureGate,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $user = $this->auth->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }
        $gate = Gate::getInstance();
        if (!$gate->allows('admin.organization')) {
            return (new Response())->setStatusCode(403)->setBody('Accès refusé.');
        }
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        if ($tenantId < 2) {
            return Response::redirect(url('dashboard'));
        }
        $planAllowed = $this->featureGate->allows($tenantId, 'advanced_integrations');
        $keys = $planAllowed ? $this->apiKeys->listForTenant($tenantId) : [];
        $flashKey = Session::getFlash('new_integration_key');
        $discord = new DiscordEventRelayService();
        $directory = new DiscordWebhookDirectory();
        $sse = new SseTransmissionDiscordService();

        return Response::view('layout.main', [
            'title' => 'Intégrations',
            'content' => 'admin.organization.integrations',
            'integration_keys' => $keys,
            'new_integration_key_plain' => is_string($flashKey) ? $flashKey : null,
            'available_scopes' => self::AVAILABLE_SCOPES,
            'api_keys_allowed' => $planAllowed,
            'discord_events' => DiscordWebhookCatalog::events(),
            'discord_state' => $discord->state($tenantId),
            'discord_relays' => $sse->publicRelays($tenantId),
            'use_community_relay' => $sse->usesCommunityRelay($tenantId),
            'community_relay_ready' => $sse->communityRelayReady($tenantId),
            'mail_kinds' => array_values(array_filter(\App\Support\TenantEmailKind::all(), static fn (string $kind): bool => $gate->allows(\App\Support\TenantEmailKind::permissionForKind($kind)))),
            'discord_org' => $directory->organisation($tenantId),
            'discord_webhooks' => $directory->list($tenantId),
            'invite_template' => $directory->inviteTemplate($tenantId),
            'invite_variables' => ProfileInviteDiscordMessage::variables(),
            'community_name' => (string) ((new TenantRepository())->findById($tenantId)['name'] ?? ''),
        ]);
    }

    public function saveMail(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/integrations'));
        }
        $denied = $this->guardOrg();
        if ($denied !== null) {
            return $denied;
        }
        $input = [];
        foreach (['enabled', 'provider', 'host', 'port', 'encryption', 'username', 'password', 'clear_password', 'from_address', 'from_name', 'reply_to'] as $field) {
            $input[$field] = $request->input($field, '');
        }
        $result = (new TenantMailSender())->save((int) Session::get('tenant_id'), $input);
        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);

        return Response::redirect(url('back-office/integrations') . '#emails-communaute');
    }

    public function testMail(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/integrations'));
        }
        $denied = $this->guardOrg();
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $user = $this->auth->user() ?? [];
        $tenant = (new TenantRepository())->findById($tenantId) ?? [];
        $result = (new TenantMailSender())->sendTest($tenantId, trim((string) ($user['email'] ?? '')), (string) ($tenant['name'] ?? 'Athena'));
        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);

        return Response::redirect(url('back-office/integrations') . '#emails-communaute');
    }

    public function saveDiscord(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/integrations'));
        }
        $denied = $this->guardOrg();
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $events = $request->input('discord_event', []);
        $events = is_array($events) ? $events : [];
        $result = (new DiscordEventRelayService())->saveEvents($tenantId, $events);
        $this->audit(AuditAction::DISCORD_WEBHOOK_SAVED, $tenantId, 'usages');
        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);

        return Response::redirect(url('back-office/integrations') . '#discord-usages');
    }

    /** Salon de l'organisation : un seul lien pour tous les usages qui n'ont pas de salon à part. */
    public function saveDiscordOrganisation(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/integrations'));
        }
        $denied = $this->guardOrg();
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $directory = new DiscordWebhookDirectory();
        $relay = new DiscordEventRelayService();
        $wasConfigured = $directory->organisation($tenantId)['configured'];
        $neverSaved = $relay->state($tenantId)['never_saved'];
        $clear = (string) $request->input('clear', '') === '1';
        $result = $directory->saveOrganisation(
            $tenantId,
            (string) $request->input('url', ''),
            (string) $request->input('name', ''),
            (string) $request->input('channel', ''),
            $clear
        );
        $message = $result['message'];
        if ($result['ok'] && !$clear && !$wasConfigured && $neverSaved && $directory->organisation($tenantId)['configured']) {
            // Premier salon d'une communauté qui n'avait rien réglé : tout y part, les usages se coupent ensuite un par un.
            $all = [];
            foreach (DiscordWebhookCatalog::keys() as $key) {
                $all[$key] = ['on' => '1', 'dest' => DiscordWebhookDirectory::ORGANISATION];
            }
            $relay->saveEvents($tenantId, $all);
            $message .= ' Tous les usages y sont publiés : coupez ceux dont vous ne voulez pas dans la liste ci-dessous.';
        }
        if ($result['ok'] && !$clear) {
            $directory->refreshChannel($tenantId, DiscordWebhookDirectory::ORGANISATION);
        }
        if ($result['ok']) {
            $this->audit($clear ? AuditAction::DISCORD_WEBHOOK_REMOVED : AuditAction::DISCORD_WEBHOOK_SAVED, $tenantId, 'organisation');
        }
        Session::flash($result['ok'] ? 'success' : 'error', $message);

        return Response::redirect(url('back-office/integrations') . '#discord-organisation');
    }

    /** Mode avancé : salon à part (nom, salon, lien), pour envoyer certains usages ailleurs. */
    public function addDiscordWebhook(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/integrations'));
        }
        $denied = $this->guardOrg();
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $result = (new DiscordWebhookDirectory())->add(
            $tenantId,
            (string) $request->input('name', ''),
            (string) $request->input('channel', ''),
            (string) $request->input('url', '')
        );
        if ($result['ok']) {
            (new DiscordWebhookDirectory())->refreshChannel($tenantId, (string) ($result['id'] ?? ''));
            $this->audit(AuditAction::DISCORD_WEBHOOK_SAVED, $tenantId, 'salon:' . ($result['id'] ?? ''));
        }
        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);

        return Response::redirect(url('back-office/integrations') . '#discord-avance');
    }

    /** Actualise uniquement le nom du salon, sans envoyer de message. */
    public function refreshDiscordChannel(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            return Response::redirect(url('back-office/integrations'));
        }
        $denied = $this->guardOrg();
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        if ((new \App\Services\Security\FileRateLimiter())->tooManyAttempts('discord_channel_refresh|' . $tenantId, 10, 60)) {
            Session::flash('error', __('events.discord_rate_limited'));
        } else {
            $result = (new DiscordWebhookDirectory())->refreshChannel($tenantId, (string) $request->input('webhook_id', ''));
            Session::flash($result['ok'] ? 'success' : 'error', $result['message']);
        }

        return Response::redirect(url('back-office/integrations') . '#discord-organisation');
    }

    public function removeDiscordWebhook(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/integrations'));
        }
        $denied = $this->guardOrg();
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (string) ($params['webhookId'] ?? '');
        $result = (new DiscordWebhookDirectory())->remove($tenantId, $id);
        if ($result['ok']) {
            $this->audit(AuditAction::DISCORD_WEBHOOK_REMOVED, $tenantId, 'salon:' . $id);
        }
        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);

        return Response::redirect(url('back-office/integrations') . '#discord-avance');
    }

    /**
     * Bouton « Tester » (réponse affichée sur place) : salon enregistré (id) ou lien en cours de saisie (url).
     * Dix essais par minute et par communauté.
     */
    public function testDiscordWebhook(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            return Response::json(['ok' => false, 'message' => 'Session expirée. Rechargez la page.'], 419);
        }
        $user = $this->auth->user();
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        if (!$user || $tenantId < 2 || !Gate::getInstance()->allows('admin.organization')) {
            return Response::json(['ok' => false, 'message' => 'Accès refusé.'], 403);
        }
        try {
            if ((new \App\Services\Security\FileRateLimiter())->tooManyAttempts('discord_webhook_test|' . $tenantId, 10, 60)) {
                return Response::json(['ok' => false, 'message' => 'Trop d’essais d’affilée. Patientez une minute.'], 429);
            }
        } catch (\Throwable) {
        }
        $id = trim((string) $request->input('id', DiscordWebhookDirectory::ORGANISATION));
        $typed = (string) $request->input('url', '');
        $tenant = (new TenantRepository())->findById($tenantId) ?? [];
        $result = (new DiscordEventRelayService())->testDestination($tenantId, $id, $typed, (string) ($tenant['name'] ?? ''));
        $this->audit(AuditAction::DISCORD_WEBHOOK_TESTED, $tenantId, (trim($typed) !== '' ? 'lien saisi' : 'salon:' . $id) . ($result['ok'] ? ';ok' : ';refus'));

        return Response::json($result, $result['ok'] ? 200 : 422);
    }

    /** Modèle du message Discord des liens « compléter son profil ». */
    public function saveInviteTemplate(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/integrations'));
        }
        $denied = $this->guardOrg();
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $reset = (string) $request->input('reset', '') === '1';
        (new DiscordWebhookDirectory())->saveInviteTemplate($tenantId, $reset ? '' : (string) $request->input('template', ''));
        $this->audit(AuditAction::DISCORD_WEBHOOK_SAVED, $tenantId, 'modele-invitation');
        Session::flash('success', $reset ? 'Message d’invitation remis par défaut.' : 'Message d’invitation enregistré.');

        return Response::redirect(url('back-office/integrations') . '#discord-message');
    }

    private function audit(string $action, int $tenantId, string $detail): void
    {
        try {
            \App\Core\Container::get(\App\Services\Audit\AuditService::class)
                ->log($action, $tenantId, (int) Session::get('user_id') ?: null, 'discord_webhook', null, null, $detail);
        } catch (\Throwable) {
        }
    }


    public function testDiscord(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/integrations'));
        }
        $denied = $this->guardOrg();
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $eventKey = trim((string) $request->input('event_key', ''));
        $result = (new DiscordEventRelayService())->sendTest($tenantId, $eventKey);
        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);

        return Response::redirect(url('back-office/integrations') . '#relais-discord');
    }

    public function storeSseRelay(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/integrations'));
        }
        $denied = $this->guardOrg();
        if ($denied !== null) {
            return $denied;
        }
        $result = (new SseTransmissionDiscordService())->addRelay(
            (int) Session::get('tenant_id'),
            (string) $request->input('label', ''),
            (string) $request->input('discord_url', '')
        );
        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);

        return Response::redirect(url('back-office/integrations') . '#relais-transmissions');
    }

    public function deleteSseRelay(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/integrations'));
        }
        $denied = $this->guardOrg();
        if ($denied !== null) {
            return $denied;
        }
        $result = (new SseTransmissionDiscordService())->removeRelay(
            (int) Session::get('tenant_id'),
            (string) ($params['relayId'] ?? '')
        );
        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);

        return Response::redirect(url('back-office/integrations') . '#relais-transmissions');
    }

    public function testSseRelay(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/integrations'));
        }
        $denied = $this->guardOrg();
        if ($denied !== null) {
            return $denied;
        }
        $result = (new SseTransmissionDiscordService())->sendTest(
            (int) Session::get('tenant_id'),
            (string) ($params['relayId'] ?? '')
        );
        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);

        return Response::redirect(url('back-office/integrations') . '#relais-transmissions');
    }

    public function saveSseCommunity(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/integrations'));
        }
        $denied = $this->guardOrg();
        if ($denied !== null) {
            return $denied;
        }
        $enabled = (string) $request->input('use_community_relay', '0') === '1';
        $result = (new SseTransmissionDiscordService())->setUseCommunityRelay(
            (int) Session::get('tenant_id'),
            $enabled
        );
        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);

        return Response::redirect(url('back-office/integrations') . '#relais-transmissions');
    }

    public function create(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/integrations'));
        }
        $user = $this->auth->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }
        $gate = Gate::getInstance();
        if (!$gate->allows('admin.organization')) {
            return (new Response())->setStatusCode(403)->setBody('Accès refusé.');
        }
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        if ($tenantId < 2 || !$this->apiKeys->tableExists()) {
            Session::flash('error', 'Fonction indisponible pour cette communauté.');

            return Response::redirect(url('back-office/integrations'));
        }
        $planDenied = $this->assertAdvancedIntegrationsPlan($tenantId);
        if ($planDenied !== null) {
            return $planDenied;
        }
        $name = trim((string) $request->input('name', 'Intégration'));
        if ($name === '') {
            $name = 'Intégration';
        }
        $quota = (int) $request->input('quota_per_day', 10000);
        if ($quota < 100) {
            $quota = 100;
        } elseif ($quota > 500000) {
            $quota = 500000;
        }
        $scopes = $this->sanitizeScopes($request->input('scopes', []));
        if ($scopes === []) {
            $scopes = self::AVAILABLE_SCOPES;
        }
        $created = $this->apiKeys->createKey($tenantId, $name, $scopes, $quota);
        if ($created === null) {
            Session::flash('error', 'Impossible de créer la clé.');

            return Response::redirect(url('back-office/integrations'));
        }
        Session::flash('new_integration_key', $created['plain_key']);
        Session::flash('success', 'Clé créée : copiez-la maintenant, elle ne sera plus affichée en clair.');

        return Response::redirect(url('back-office/integrations'));
    }

    public function update(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/integrations'));
        }
        $user = $this->auth->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }
        $gate = Gate::getInstance();
        if (!$gate->allows('admin.organization')) {
            return (new Response())->setStatusCode(403)->setBody('Accès refusé.');
        }
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        $id = (int) ($params['id'] ?? 0);
        $name = trim((string) $request->input('name', ''));
        $quota = (int) $request->input('quota_per_day', 10000);
        $planDenied = $this->assertAdvancedIntegrationsPlan($tenantId);
        if ($planDenied !== null) {
            return $planDenied;
        }
        if ($tenantId < 2 || $id < 1 || $name === '') {
            Session::flash('error', 'Paramètres invalides.');

            return Response::redirect(url('back-office/integrations'));
        }
        if ($quota < 100) {
            $quota = 100;
        } elseif ($quota > 500000) {
            $quota = 500000;
        }
        $scopes = $this->sanitizeScopes($request->input('scopes', []));
        if ($scopes === []) {
            $scopes = self::AVAILABLE_SCOPES;
        }
        $this->apiKeys->updateKeySettings($id, $tenantId, $name, $quota, $scopes);
        Session::flash('success', 'Paramètres de la clé mis à jour.');

        return Response::redirect(url('back-office/integrations'));
    }

    public function revoke(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/integrations'));
        }
        $user = $this->auth->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }
        $gate = Gate::getInstance();
        if (!$gate->allows('admin.organization')) {
            return (new Response())->setStatusCode(403)->setBody('Accès refusé.');
        }
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        $id = (int) ($params['id'] ?? 0);
        $planDenied = $this->assertAdvancedIntegrationsPlan($tenantId);
        if ($planDenied !== null) {
            return $planDenied;
        }
        if ($tenantId > 1 && $id > 0) {
            $this->apiKeys->revoke($id, $tenantId);
            Session::flash('success', 'Clé révoquée.');
        }

        return Response::redirect(url('back-office/integrations'));
    }

    public function rotate(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/integrations'));
        }
        $user = $this->auth->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }
        $gate = Gate::getInstance();
        if (!$gate->allows('admin.organization')) {
            return (new Response())->setStatusCode(403)->setBody('Accès refusé.');
        }
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        $id = (int) ($params['id'] ?? 0);
        if ($tenantId < 2 || $id < 1) {
            Session::flash('error', 'Paramètres invalides.');

            return Response::redirect(url('back-office/integrations'));
        }
        $planDenied = $this->assertAdvancedIntegrationsPlan($tenantId);
        if ($planDenied !== null) {
            return $planDenied;
        }
        $current = $this->apiKeys->findForTenant($id, $tenantId);
        if (!is_array($current) || !empty($current['revoked_at'])) {
            Session::flash('error', 'Clé introuvable ou déjà révoquée.');

            return Response::redirect(url('back-office/integrations'));
        }
        $scopesRaw = (string) ($current['scopes_json'] ?? '');
        $decodedScopes = json_decode($scopesRaw, true);
        $scopes = is_array($decodedScopes) ? $this->sanitizeScopes($decodedScopes) : self::AVAILABLE_SCOPES;
        if ($scopes === []) {
            $scopes = self::AVAILABLE_SCOPES;
        }
        $newKey = $this->apiKeys->createKey(
            $tenantId,
            (string) ($current['name'] ?? 'Intégration'),
            $scopes,
            (int) ($current['quota_per_day'] ?? 10000)
        );
        if ($newKey === null) {
            Session::flash('error', 'Impossible de générer une nouvelle clé.');

            return Response::redirect(url('back-office/integrations'));
        }
        $this->apiKeys->revoke($id, $tenantId);
        Session::flash('new_integration_key', $newKey['plain_key']);
        Session::flash('success', 'Clé tournée avec succès. Mettez à jour vos services immédiatement.');

        return Response::redirect(url('back-office/integrations'));
    }

    private function guardOrg(): ?Response
    {
        $user = $this->auth->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }
        $gate = Gate::getInstance();
        if (!$gate->allows('admin.organization')) {
            return (new Response())->setStatusCode(403)->setBody('Accès refusé.');
        }
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        if ($tenantId < 2) {
            return Response::redirect(url('dashboard'));
        }

        return null;
    }

    private function assertAdvancedIntegrationsPlan(int $tenantId): ?Response
    {
        if ($tenantId < 2) {
            return null;
        }
        if (!$this->featureGate->allows($tenantId, 'advanced_integrations')) {
            return Response::view('layout.main', [
                'title' => 'Intégrations',
                'content' => 'platform.upgrade',
                'feature' => 'Connexions avancées (clés d’accès)',
                'planName' => 'Pro+',
            ]);
        }

        return null;
    }

    /**
     * @param mixed $rawScopes
     * @return list<string>
     */
    private function sanitizeScopes(mixed $rawScopes): array
    {
        $scopes = is_array($rawScopes) ? $rawScopes : [$rawScopes];
        $allowed = [];
        foreach ($scopes as $scope) {
            $value = trim((string) $scope);
            if ($value !== '' && in_array($value, self::AVAILABLE_SCOPES, true)) {
                $allowed[] = $value;
            }
        }

        return array_values(array_unique($allowed));
    }
}
