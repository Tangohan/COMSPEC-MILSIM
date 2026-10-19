<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TenantRepository;
use App\Services\Auth\AuthService;
use App\Services\Community\DiscordShowcaseService;

/**
 * Vitrine Discord des communautés côté administration du site (/admin/system/annuaire/discord) :
 * demandes à valider avec l'aperçu exact du message, refus, blocage, retrait, et réglages du salon
 * (lien webhook masqué, simulation ou publication réelle, salon forum, délai, tags).
 */
final class SystemDiscordShowcaseController
{
    private const BASE = 'admin/system/annuaire/discord';

    public function __construct(
        private AuthService $authService,
        private TenantRepository $tenants,
        private ?DiscordShowcaseService $showcase = null,
    ) {}

    private function showcase(): DiscordShowcaseService
    {
        return $this->showcase ??= new DiscordShowcaseService();
    }

    public function index(Request $request, array $params = []): Response
    {
        $service = $this->showcase();
        $config = $service->config();
        $now = new \DateTimeImmutable('now');
        $rows = [];
        foreach ($service->entries() as $tid => $entry) {
            $tenant = $this->tenants->findById($tid);
            $card = $service->loadCard($tid);
            $rows[] = [
                'entry' => $entry,
                'name' => (string) ($card['name'] ?? $tenant['name'] ?? ('Communauté #' . $tid)),
                'slug' => (string) ($tenant['slug'] ?? ''),
                'exists' => $tenant !== null,
                'listed' => $card !== null,
                'payload' => $card !== null ? DiscordShowcaseService::buildPayload($card, (string) $entry['pitch'], $config, $now) : null,
                'tags' => $card !== null ? DiscordShowcaseService::tagLabels($card) : [],
            ];
        }
        // Demandes en attente d'abord (les plus anciennes en premier), puis le reste par nom.
        usort($rows, static function (array $a, array $b): int {
            $pa = $a['entry']['status'] === DiscordShowcaseService::STATUS_PENDING ? 0 : 1;
            $pb = $b['entry']['status'] === DiscordShowcaseService::STATUS_PENDING ? 0 : 1;
            if ($pa !== $pb) {
                return $pa <=> $pb;
            }
            if ($pa === 0) {
                return strcmp((string) $a['entry']['requested_at'], (string) $b['entry']['requested_at']);
            }

            return strcasecmp($a['name'], $b['name']);
        });

        return Response::view('layout.main', [
            'title' => 'Vitrine Discord des communautés',
            'content' => 'admin.system.discord_showcase',
            'showcaseRows' => $rows,
            'showcaseConfig' => $config,
            'showcaseWebhookMasked' => DiscordShowcaseService::maskWebhook($service->webhook()),
            'showcaseBase' => self::BASE,
            'isPlatformAdminShell' => true,
            'backOfficePageCss' => ['platform-admin.css', 'admin-registry.css', 'discord-showcase.css'],
        ]);
    }

    public function approve(Request $request, array $params = []): Response
    {
        return $this->act($request, function (int $tid, int $adminId): array {
            $card = $this->showcase()->loadCard($tid);
            if ($card === null) {
                return ['ok' => false, 'message' => 'Cette communauté n’apparaît plus dans l’annuaire public : rien n’est publié.'];
            }

            return $this->showcase()->approve($tid, $adminId, $card);
        });
    }

    public function reject(Request $request, array $params = []): Response
    {
        return $this->act($request, fn (int $tid, int $adminId): array => $this->showcase()->reject($tid, $adminId, (string) $request->input('reason', '')));
    }

    public function block(Request $request, array $params = []): Response
    {
        return $this->act($request, fn (int $tid): array => $this->showcase()->block($tid, (string) $request->input('reason', '')));
    }

    public function unblock(Request $request, array $params = []): Response
    {
        return $this->act($request, fn (int $tid): array => $this->showcase()->unblock($tid));
    }

    public function remove(Request $request, array $params = []): Response
    {
        return $this->act($request, fn (int $tid): array => $this->showcase()->remove($tid));
    }

    public function settings(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée, rechargez la page.');

            return Response::redirect(url(self::BASE));
        }
        $tags = $request->input('tags', []);
        $result = $this->showcase()->saveConfig([
            'webhook' => (string) $request->input('webhook', ''),
            'clear_webhook' => (string) $request->input('clear_webhook', '') === '1',
            'mode' => (string) $request->input('mode', ''),
            'forum' => (string) $request->input('forum', '') === '1',
            'cooldown_days' => (int) $request->input('cooldown_days', DiscordShowcaseService::DEFAULT_COOLDOWN_DAYS),
            'tags' => is_array($tags) ? $tags : [],
        ]);
        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);

        return Response::redirect(url(self::BASE) . '#reglages');
    }

    public function check(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée, rechargez la page.');

            return Response::redirect(url(self::BASE));
        }
        $result = $this->showcase()->checkWebhook();
        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);

        return Response::redirect(url(self::BASE) . '#reglages');
    }

    /**
     * @param callable(int, int): array{ok:bool, message:string} $action
     */
    private function act(Request $request, callable $action): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée, rechargez la page.');

            return Response::redirect(url(self::BASE));
        }
        $actor = $this->authService->user();
        if (!$actor) {
            return Response::redirect(url('login'));
        }
        $tid = (int) $request->input('tenant_id');
        if ($tid < 2) {
            Session::flash('error', 'Communauté introuvable.');

            return Response::redirect(url(self::BASE));
        }
        $result = $action($tid, (int) $actor['id']);
        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);

        return Response::redirect(url(self::BASE) . '#c' . $tid);
    }
}
