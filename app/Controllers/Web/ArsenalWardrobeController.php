<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\ArsenalWardrobeRepository;
use App\Services\Platform\FeatureGateService;
use App\Support\PlanFeatureDenial;

class ArsenalWardrobeController
{
    public function __construct(
        private ?ArsenalWardrobeRepository $repo = null,
        private ?FeatureGateService $featureGate = null,
    ) {
        $this->repo ??= new ArsenalWardrobeRepository();
        $this->featureGate ??= \App\Core\Container::get(FeatureGateService::class);
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId < 1 || $userId < 1) {
            return Response::redirect(url('login'));
        }
        if (!$this->featureGate->allows($tenantId, 'equipment')) {
            return PlanFeatureDenial::upgradeView('equipment', 'Gratuit');
        }
        if (!$this->repo->tablesReady()) {
            return Response::view('layout.main', [
                'content' => 'equipment.wardrobes',
                'title' => 'Wardrobes ACE Arsenal',
                'migrationMissing' => true,
                'wardrobes' => [],
                'collections' => [],
            ]);
        }

        return Response::view('layout.main', [
            'content' => 'equipment.wardrobes',
            'title' => 'Wardrobes ACE Arsenal',
            'migrationMissing' => false,
            'wardrobes' => $this->repo->listAccessibleWardrobes($tenantId, $userId),
            'collections' => $this->repo->listCollections($tenantId, $userId),
        ]);
    }

    public function storeCollection(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId < 1 || $userId < 1) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('equipment/wardrobes'));
        }
        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            Session::flash('error', 'Donnez un nom à la collection.');

            return Response::redirect(url('equipment/wardrobes'));
        }
        try {
            $this->repo->upsertCollection($tenantId, $userId, [
                'name' => $name,
                'description' => trim((string) $request->input('description', '')),
                'visibility' => (string) $request->input('visibility', 'personal'),
                'tags' => array_filter(array_map('trim', explode(',', (string) $request->input('tags', '')))),
            ]);
            Session::flash('success', 'Collection créée.');
        } catch (\Throwable $e) {
            Session::flash('error', 'Impossible de créer la collection.');
        }

        return Response::redirect(url('equipment/wardrobes'));
    }

    public function destroyWardrobe(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId < 1 || $userId < 1) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('equipment/wardrobes'));
        }
        $id = (int) ($params['id'] ?? 0);
        if ($id > 0) {
            $this->repo->deleteWardrobe($tenantId, $userId, $id);
            Session::flash('success', 'Wardrobe supprimée.');
        }

        return Response::redirect(url('equipment/wardrobes'));
    }

    public function destroyCollection(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId < 1 || $userId < 1) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('equipment/wardrobes'));
        }
        $id = (int) ($params['id'] ?? 0);
        if ($id > 0) {
            $this->repo->deleteCollection($tenantId, $userId, $id);
            Session::flash('success', 'Collection supprimée.');
        }

        return Response::redirect(url('equipment/wardrobes'));
    }
}
