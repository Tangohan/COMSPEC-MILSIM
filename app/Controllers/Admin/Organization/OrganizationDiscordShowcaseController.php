<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Auth\AuthService;
use App\Services\Community\DiscordShowcaseService;

/**
 * « Annoncer ma communauté sur Discord » (back-office, gestion de la communauté) : aperçu de la carte,
 * demande de publication ou de mise à jour, annulation, retrait. La publication attend toujours la
 * validation de l'administrateur de la plateforme (/admin/system/annuaire/discord).
 */
final class OrganizationDiscordShowcaseController
{
    private const BASE = 'back-office/community/discord';

    public function __construct(
        private AuthService $auth,
        private ?DiscordShowcaseService $showcase = null,
    ) {}

    private function showcase(): DiscordShowcaseService
    {
        return $this->showcase ??= new DiscordShowcaseService();
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        if ($tenantId === null) {
            return $this->denied();
        }
        $service = $this->showcase();
        $config = $service->config();
        $entry = $service->entry($tenantId);
        $card = $service->loadCard($tenantId);
        $payload = $card !== null
            ? DiscordShowcaseService::buildPayload($card, (string) $entry['pitch'], $config, new \DateTimeImmutable('now'))
            : null;

        return Response::view('layout.main', [
            'title' => 'Annoncer ma communauté sur Discord',
            'content' => 'admin.organization.discord_showcase',
            'showcaseEntry' => $entry,
            'showcaseCard' => $card,
            'showcasePayload' => $payload,
            'showcaseTags' => $card !== null ? DiscordShowcaseService::tagLabels($card) : [],
            'showcaseState' => DiscordShowcaseService::requestState($entry, $config['cooldown_days'], new \DateTimeImmutable('now')),
            'showcaseConfig' => $config,
            'showcaseBase' => self::BASE,
        ]);
    }

    public function request(Request $request, array $params = []): Response
    {
        $tenantId = $this->guardPost($request);
        if (!is_int($tenantId)) {
            return $tenantId;
        }
        $user = $this->auth->user() ?? [];
        $listed = $this->showcase()->loadCard($tenantId) !== null;
        $result = $this->showcase()->request($tenantId, (int) ($user['id'] ?? 0), (string) $request->input('pitch', ''), $listed);
        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);

        return Response::redirect(url(self::BASE));
    }

    public function cancel(Request $request, array $params = []): Response
    {
        $tenantId = $this->guardPost($request);
        if (!is_int($tenantId)) {
            return $tenantId;
        }
        $result = $this->showcase()->cancel($tenantId);
        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);

        return Response::redirect(url(self::BASE));
    }

    public function withdraw(Request $request, array $params = []): Response
    {
        $tenantId = $this->guardPost($request);
        if (!is_int($tenantId)) {
            return $tenantId;
        }
        $result = $this->showcase()->remove($tenantId);
        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);

        return Response::redirect(url(self::BASE));
    }

    private function tenantId(): ?int
    {
        if (!$this->auth->user() || !Gate::getInstance()->allows('admin.organization')) {
            return null;
        }
        $tenantId = (int) (Session::get('tenant_id') ?? 0);

        return $tenantId >= 2 ? $tenantId : null;
    }

    private function guardPost(Request $request): int|Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée, rechargez la page.');

            return Response::redirect(url(self::BASE));
        }

        return $this->tenantId() ?? $this->denied();
    }

    private function denied(): Response
    {
        if (!$this->auth->user()) {
            return Response::redirect(url('login'));
        }

        return (new Response())->setStatusCode(403)->setBody('Accès refusé.');
    }
}
