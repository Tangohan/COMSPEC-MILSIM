<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\PlatformBoMessageRepository;
use App\Repositories\TenantRepository;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;
use App\Services\Platform\PlatformBoMessageService;

/**
 * Back-office › Messages de la plateforme : ce que l'administration du site a écrit à cette communauté.
 * Une communauté ne voit que les messages qui la visent ; un message n'empêche jamais d'utiliser le back-office.
 */
final class PlatformMessagesController
{
    private const PATH = 'back-office/messages-plateforme';

    public function __construct(
        private ?PlatformBoMessageService $service = null,
        private ?PlatformBoMessageRepository $repo = null,
        private ?TenantRepository $tenants = null,
        private ?Gate $gate = null,
        private ?AuditService $audit = null,
    ) {
        $this->repo ??= new PlatformBoMessageRepository();
        $this->service ??= new PlatformBoMessageService($this->repo);
        $this->tenants ??= new TenantRepository();
        $this->gate ??= Gate::getInstance();
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenant = $this->tenant();
        if ($tenant === null) {
            return Response::redirect(url('dashboard'));
        }
        $items = [];
        try {
            $items = $this->service->forTenant($tenant, (int) Session::get('user_id'), locale(), true, true, true);
        } catch (\Throwable $e) {
            error_log('[messages-plateforme] ' . $e->getMessage());
        }

        return Response::view('layout.main', [
            'content' => 'admin.organization.platform_messages',
            'title' => 'Messages de la plateforme',
            'isBackOfficeShell' => true,
            'boPageGroup' => 'Réglages et intégrations',
            'boPageTitle' => 'Messages de la plateforme',
            'platformMessages' => $items,
            'canAckPlatformMessages' => $this->canAck(),
        ]);
    }

    /** POST …/{id}/fermer : masque le message pour la personne (jamais un message urgent). */
    public function dismiss(Request $request, array $params = []): Response
    {
        $tenant = $this->tenant();
        $id = (int) ($params['id'] ?? 0);
        if ($tenant === null || !Csrf::verify($request)) {
            return $this->reply($request, false, 'Session expirée. Rechargez la page.', 419);
        }
        $row = $id > 0 ? $this->service->findForTenant($id, $tenant) : null;
        if ($row === null) {
            return $this->reply($request, false, 'Message introuvable.', 404);
        }
        if (($row['level'] ?? '') === 'urgent') {
            return $this->reply($request, false, 'Un message urgent ne se ferme pas : un gestionnaire peut l’acquitter.', 403);
        }
        $this->repo->dismiss($id, (int) $tenant['id'], (int) Session::get('user_id'));

        return $this->reply($request, true, 'Message masqué pour vous.');
    }

    /** POST …/{id}/acquitter : un gestionnaire lève un message urgent pour toute la communauté. */
    public function ack(Request $request, array $params = []): Response
    {
        $tenant = $this->tenant();
        $id = (int) ($params['id'] ?? 0);
        if ($tenant === null || !Csrf::verify($request)) {
            return $this->reply($request, false, 'Session expirée. Rechargez la page.', 419);
        }
        if (!$this->canAck()) {
            return $this->reply($request, false, 'Seul un gestionnaire de la communauté peut acquitter ce message.', 403);
        }
        $row = $id > 0 ? $this->service->findForTenant($id, $tenant) : null;
        if ($row === null) {
            return $this->reply($request, false, 'Message introuvable.', 404);
        }
        $userId = (int) Session::get('user_id');
        $this->repo->ack($id, (int) $tenant['id'], $userId);
        try {
            ($this->audit ?? Container::get(AuditService::class))->logChange(
                AuditAction::PLATFORM_BO_MESSAGE_ACKED,
                (int) $tenant['id'],
                $userId ?: null,
                'platform_bo_message',
                $id,
                [],
                ['level' => (string) $row['level']]
            );
        } catch (\Throwable $e) {
            error_log('[audit] message plateforme acquitté : ' . $e->getMessage());
        }

        return $this->reply($request, true, 'Message acquitté.');
    }

    /** GET …/{id}/lien : compte le clic de la communauté puis ouvre l'adresse du bouton (revalidée http/https). */
    public function link(Request $request, array $params = []): Response
    {
        $tenant = $this->tenant();
        $id = (int) ($params['id'] ?? 0);
        $row = ($tenant !== null && $id > 0) ? $this->service->findForTenant($id, $tenant) : null;
        $url = $row !== null ? \App\Support\PlatformBoMessage::safeUrl($row['cta_url'] ?? null) : null;
        if ($url === null) {
            Session::flash('error', 'Ce lien n’est plus disponible.');

            return Response::redirect(url(self::PATH));
        }
        $this->repo->click($id, (int) $tenant['id']);

        return Response::redirect($url);
    }

    /** @return array<string, mixed>|null */
    private function tenant(): ?array
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1 || (int) Session::get('user_id') < 1) {
            return null;
        }

        return $this->tenants->findById($tenantId);
    }

    private function canAck(): bool
    {
        return $this->gate->allows('admin.organization') || $this->gate->allows('admin.access');
    }

    private function reply(Request $request, bool $ok, string $message, int $status = 200): Response
    {
        $wantsJson = str_contains((string) $request->header('Accept'), 'application/json')
            || strtolower((string) $request->header('X-Requested-With')) === 'xmlhttprequest';
        if ($wantsJson) {
            return Response::json(['ok' => $ok, 'message' => $message], $ok ? 200 : $status);
        }
        Session::flash($ok ? 'success' : 'error', $message);

        return Response::redirect(url(self::PATH));
    }
}
