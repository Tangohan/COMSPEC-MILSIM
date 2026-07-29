<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\DocumentLinkRepository;
use App\Repositories\DocumentRepository;
use App\Repositories\EquipmentClassRepository;
use App\Services\Platform\FeatureGateService;
use App\Support\PlanFeatureDenial;

class EquipmentController
{
    public function __construct(
        private EquipmentClassRepository $equipmentRepository,
        private DocumentLinkRepository $documentLinkRepository,
        private DocumentRepository $documentRepository,
        private ?FeatureGateService $featureGate = null,
    ) {
        $this->featureGate ??= \App\Core\Container::get(FeatureGateService::class);
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        if (!$this->featureGate->allows((int) $tenantId, 'equipment')) {
            return PlanFeatureDenial::upgradeView('equipment', 'Gratuit');
        }
        $classes = $this->equipmentRepository->listForTenant((int) $tenantId);
        return Response::view('layout.main', [
            'content' => 'equipment.index',
            'title' => 'Équipement',
            'equipmentClasses' => $classes,
        ]);
    }

    public function show(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        if (!$this->featureGate->allows((int) $tenantId, 'equipment')) {
            return PlanFeatureDenial::upgradeView('equipment', 'Gratuit');
        }
        $slug = $params['slug'] ?? '';
        $class = $this->equipmentRepository->findBySlug($slug, (int) $tenantId);
        if (!$class) {
            return (new Response())->setStatusCode(404)->setBody('Classe d\'équipement non trouvée.');
        }
        $linkedDocumentIds = $this->documentLinkRepository->getDocumentIdsForEntity('equipment_class', (int) $class['id']);
        $linkedDocuments = $this->documentRepository->findPublishedByIds($linkedDocumentIds, (int) $tenantId);
        return Response::view('layout.main', [
            'content' => 'equipment.show',
            'title' => $class['name'],
            'equipmentClass' => $class,
            'linkedDocuments' => $linkedDocuments,
        ]);
    }
}
