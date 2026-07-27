<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TenantAdminSettingsRepository;
use App\Services\Doctrine\DoctrineReferential;
use App\Services\Doctrine\EchelonCatalog;
use App\Services\Doctrine\OrderFormatCatalog;
use App\Services\Doctrine\TrainingPipelineCatalog;

/**
 * Référentiel doctrinal de la communauté : choix US / FR / les deux, et consultation des
 * gabarits d’ordres, échelons et parcours de formation correspondants.
 *
 * Les catalogues sont définis en code et en lecture seule : cet écran ne crée aucune donnée.
 * Le seul élément persisté est le choix du référentiel, dans les paramètres de la communauté.
 */
final class DoctrineReferentialController
{
    public function __construct(
        private ?TenantAdminSettingsRepository $adminSettings = null,
    ) {
        $this->adminSettings ??= new TenantAdminSettingsRepository();
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('dashboard'));
        }

        $referential = $this->currentReferential($tenantId);

        return Response::view('layout.main', [
            'content' => 'admin.organization.doctrine_referential',
            'title' => 'Référentiel doctrinal',
            'isBackOfficeShell' => true,
            'boPageGroup' => 'Opérations',
            'boPageKicker' => 'OPÉRATIONS · DOCTRINE',
            'boPageTitle' => 'Référentiel doctrinal',
            'boPageSubtitle' => 'Gabarits d’ordres et de comptes rendus, échelons de commandement et parcours de formation, selon la doctrine retenue par votre communauté.',
            'doctrineReferential' => $referential,
            'doctrineReferentialLabels' => DoctrineReferential::labels(),
            'doctrineReferentialDescriptions' => DoctrineReferential::descriptions(),
            'doctrineOrderFormats' => OrderFormatCatalog::forReferential($referential),
            'doctrineEchelons' => EchelonCatalog::forReferential($referential),
            'doctrineTraining' => TrainingPipelineCatalog::forReferential($referential),
            'doctrineCanEdit' => $this->canEdit(),
        ]);
    }

    public function update(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('dashboard'));
        }
        if (!$this->canEdit()) {
            Session::flash('error', 'Vous n’avez pas les droits pour changer le référentiel doctrinal.');

            return Response::redirect(url('back-office/doctrine/referentiel'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('back-office/doctrine/referentiel'));
        }

        $chosen = DoctrineReferential::sanitize($request->input('referential'));
        $settings = $this->adminSettings->getForTenant($tenantId);
        $settings['doctrine']['referential'] = $chosen;
        $this->adminSettings->saveForTenant($tenantId, $settings);

        Session::flash('success', 'Référentiel doctrinal enregistré : ' . DoctrineReferential::label($chosen)
            . '. Les documents déjà rédigés ne sont pas modifiés.');

        return Response::redirect(url('back-office/doctrine/referentiel'));
    }

    private function currentReferential(int $tenantId): string
    {
        try {
            $settings = $this->adminSettings->getForTenant($tenantId);

            return DoctrineReferential::sanitize($settings['doctrine']['referential'] ?? null);
        } catch (\Throwable) {
            return DoctrineReferential::DEFAULT;
        }
    }

    private function canEdit(): bool
    {
        $gate = Gate::getInstance();

        return $gate->allows('admin.organization')
            || $gate->allows('admin.access')
            || $gate->allows('training.manage');
    }
}
