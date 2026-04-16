<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\HrCharterRepository;
use App\Support\TrainingLmsStaffAccess;

final class HrCharterDocumentAdminController
{
    public function __construct(
        private HrCharterRepository $hrCharterRepository,
    ) {}

    public function edit(Request $request, array $params = []): Response
    {
        if (($deny = $this->requireAccess()) !== null) {
            return $deny;
        }
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('back-office'));
        }
        if (!$this->hrCharterRepository->schemaReady()) {
            Session::flash('error', 'La charte n’est pas encore disponible sur cette installation.');

            return Response::redirect(url(training_lms_admin_path()));
        }
        $this->hrCharterRepository->ensureSeedDocumentForTenant($tenantId);
        $doc = $this->hrCharterRepository->getActiveDocumentForTenant($tenantId);
        if ($doc === null) {
            Session::flash('error', 'Impossible de charger ou d’initialiser la charte pour cette communauté.');

            return Response::redirect(url(training_lms_admin_path()));
        }

        return Response::view('layout.main', [
            'title' => 'Charte liée aux formations',
            'content' => 'admin.training.hr_charter_edit',
            'trainingAdminNav' => 'charter',
            'hrCharterAdminDoc' => $doc,
            'hrCharterAdminCsrf' => Csrf::token(),
        ]);
    }

    public function save(Request $request, array $params = []): Response
    {
        if (($deny = $this->requireAccess()) !== null) {
            return $deny;
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée, réessayez.');

            return Response::redirect(url('back-office/ressources/training/charte-rh'));
        }
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1 || !$this->hrCharterRepository->schemaReady()) {
            return Response::redirect(url(training_lms_admin_path()));
        }
        $title = trim((string) $request->input('title', ''));
        $body = (string) $request->input('body_html', '');
        if ($title === '') {
            Session::flash('error', 'Le titre est obligatoire.');

            return Response::redirect(url('back-office/ressources/training/charte-rh'));
        }
        $ok = $this->hrCharterRepository->updateActiveDocumentContent($tenantId, $title, $body);
        Session::flash($ok ? 'success' : 'error', $ok ? 'Charte enregistrée. Les membres verront cette version sur la page dédiée.' : 'Enregistrement impossible.');

        return Response::redirect(url('back-office/ressources/training/charte-rh'));
    }

    private function requireAccess(): ?Response
    {
        if (TrainingLmsStaffAccess::allows(Gate::getInstance())) {
            return null;
        }
        Session::flash('error', 'Accès réservé aux personnes habilitées sur les formations.');

        return Response::redirect(url('back-office'));
    }
}
