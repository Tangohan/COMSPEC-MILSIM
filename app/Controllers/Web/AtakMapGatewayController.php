<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AtakMapRepository;
use App\Services\Auth\AuthService;
use App\Services\Platform\FeatureGateService;
use App\Services\Tactical\AtakMapGatewayService;

/**
 * Page dédiée : passerelle ATAK inter-communautés (code + validation bilatérale).
 */
final class AtakMapGatewayController
{
    public function __construct(
        private AuthService $authService,
        private AtakMapGatewayService $gateways,
        private FeatureGateService $featureGate,
        private AtakMapRepository $atakMapRepository,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $guard = $this->guardContext();
        if ($guard instanceof Response) {
            return $guard;
        }
        [$tenantId, $user, $canManage] = $guard;

        $this->gateways->schemaReady() || null;
        $items = $this->gateways->schemaReady()
            ? $this->gateways->listDecoratedForTenant($tenantId)
            : [];

        $maps = [];
        try {
            foreach ($this->atakMapRepository->getAll() as $m) {
                $maps[] = [
                    'id' => (int) ($m['id'] ?? 0),
                    'label' => (string) ($m['label'] ?? $m['slug'] ?? 'Carte'),
                ];
            }
        } catch (\Throwable) {
            $maps = [['id' => 1, 'label' => 'Carte principale']];
        }

        return Response::view('layout.main', [
            'title' => 'Passerelle ATAK inter-équipes',
            'content' => 'atak.gateway.index',
            'gateway_items' => $items,
            'gateway_can_manage' => $canManage,
            'gateway_schema_ready' => $this->gateways->schemaReady(),
            'gateway_maps' => $maps,
            'currentUser' => $user,
            'flash_success' => Session::getFlash('success'),
            'flash_error' => Session::getFlash('error'),
        ]);
    }

    public function create(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request)) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('atak/passerelle'));
        }
        $guard = $this->guardContext(true);
        if ($guard instanceof Response) {
            return $guard;
        }
        [$tenantId, $user] = $guard;
        $body = $request->getParsedBody() ?? [];
        $result = $this->gateways->create(
            $tenantId,
            (int) ($user['id'] ?? 0),
            trim((string) ($body['label'] ?? '')),
            !empty($body['share_units']),
            !empty($body['share_markers']),
            (int) ($body['host_map_id'] ?? 1)
        );
        if (empty($result['ok'])) {
            Session::flash('error', (string) ($result['error'] ?? 'Création impossible.'));

            return Response::redirect(url('atak/passerelle'));
        }
        Session::flash(
            'success',
            'Code créé : ' . (string) ($result['code'] ?? '') . '. Communiquez-le à l’autre communauté, puis validez des deux côtés.'
        );

        return Response::redirect(url('atak/passerelle'));
    }

    public function redeem(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request)) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('atak/passerelle'));
        }
        $guard = $this->guardContext(true);
        if ($guard instanceof Response) {
            return $guard;
        }
        [$tenantId, $user] = $guard;
        $body = $request->getParsedBody() ?? [];
        $result = $this->gateways->redeem(
            $tenantId,
            (int) ($user['id'] ?? 0),
            (string) ($body['join_code'] ?? ''),
            (int) ($body['partner_map_id'] ?? 1)
        );
        if (empty($result['ok'])) {
            Session::flash('error', (string) ($result['error'] ?? 'Code refusé.'));

            return Response::redirect(url('atak/passerelle'));
        }
        $status = (string) (($result['gateway']['status'] ?? ''));
        if ($status === 'active') {
            Session::flash('success', 'Passerelle active : les deux communautés ont validé.');
        } else {
            Session::flash(
                'success',
                'Communauté rattachée. Validez maintenant votre côté — l’autre communauté doit aussi confirmer.'
            );
        }

        return Response::redirect(url('atak/passerelle'));
    }

    public function accept(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request)) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('atak/passerelle'));
        }
        $guard = $this->guardContext(true);
        if ($guard instanceof Response) {
            return $guard;
        }
        [$tenantId, $user] = $guard;
        $id = (int) ($params['id'] ?? $request->getParsedBody()['gateway_id'] ?? 0);
        $result = $this->gateways->accept($tenantId, (int) ($user['id'] ?? 0), $id);
        if (empty($result['ok'])) {
            Session::flash('error', (string) ($result['error'] ?? 'Validation impossible.'));

            return Response::redirect(url('atak/passerelle'));
        }
        if (!empty($result['activated'])) {
            Session::flash('success', 'Passerelle activée. Les positions partagées sont désormais visibles selon le périmètre convenu.');
        } else {
            Session::flash('success', 'Votre validation est enregistrée. En attente de la confirmation de l’autre communauté.');
        }

        return Response::redirect(url('atak/passerelle'));
    }

    public function revoke(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request)) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('atak/passerelle'));
        }
        $guard = $this->guardContext(true);
        if ($guard instanceof Response) {
            return $guard;
        }
        [$tenantId] = $guard;
        $id = (int) ($params['id'] ?? $request->getParsedBody()['gateway_id'] ?? 0);
        $reason = trim((string) (($request->getParsedBody()['reason'] ?? '')));
        $result = $this->gateways->revoke($tenantId, $id, $reason !== '' ? $reason : null);
        if (empty($result['ok'])) {
            Session::flash('error', (string) ($result['error'] ?? 'Annulation impossible.'));
        } else {
            Session::flash('success', 'Passerelle annulée. Le partage inter-équipes est coupé.');
        }

        return Response::redirect(url('atak/passerelle'));
    }

    /**
     * @return array{0: int, 1: array<string, mixed>, 2?: bool}|Response
     */
    private function guardContext(bool $requireManage = false): array|Response
    {
        $user = $this->authService->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            Session::flash('error', 'Sélectionnez d’abord une communauté.');

            return Response::redirect(url('dashboard'));
        }
        if (!$this->featureGate->allows($tenantId, 'atak')) {
            return Response::view('layout.main', [
                'title' => 'Passerelle ATAK',
                'content' => 'platform.upgrade',
                'feature' => 'atak',
                'planName' => 'standard',
            ]);
        }
        $gate = Gate::getInstance();
        $canManage = $gate->allows('admin.organization')
            || $gate->allows('admin.access')
            || $gate->allows('admin.system');
        if ($requireManage && !$canManage) {
            Session::flash('error', 'Seuls les responsables de la communauté peuvent gérer la passerelle.');

            return Response::redirect(url('atak/passerelle'));
        }

        return $requireManage ? [$tenantId, $user] : [$tenantId, $user, $canManage];
    }
}
