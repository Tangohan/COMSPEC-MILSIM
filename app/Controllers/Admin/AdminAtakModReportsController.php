<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AtakModReportRepository;
use App\Services\Auth\AuthService;

/**
 * Journal des erreurs / bugs remontés par le pack Overwatch.
 */
final class AdminAtakModReportsController
{
    private const REDIRECT = 'admin/atak-mod-reports';

    public function __construct(
        private AtakModReportRepository $modReportRepository,
        private AuthService $authService,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $severity = trim((string) ($request->query('severity') ?? ''));
        $severityFilter = $severity !== '' ? $severity : null;
        $status = trim((string) ($request->query('status') ?? ''));
        $statusFilter = $status !== '' ? $this->modReportRepository->normalizeWorkflowStatus($status) : null;
        $rows = $this->modReportRepository->listRecent(200, $severityFilter, $statusFilter);
        $total = $this->modReportRepository->countAll($severityFilter, $statusFilter);
        $totalAll = $this->modReportRepository->countAll(null, null);
        $countNew = $this->modReportRepository->countAll(null, AtakModReportRepository::STATUS_NEW);
        $countProgress = $this->modReportRepository->countAll(null, AtakModReportRepository::STATUS_IN_PROGRESS);
        $countFixed = $this->modReportRepository->countAll(null, AtakModReportRepository::STATUS_FIXED);

        return Response::view('layout.main', [
            'title' => 'Rapports Overwatch',
            'content' => 'admin.atak-mod-reports.index',
            'rows' => $rows,
            'total' => $total,
            'totalAll' => $totalAll,
            'severityFilter' => $severityFilter ?? '',
            'statusFilter' => $statusFilter ?? '',
            'statusCounts' => [
                'new' => $countNew,
                'in_progress' => $countProgress,
                'fixed' => $countFixed,
            ],
        ]);
    }

    public function updateStatus(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url(self::REDIRECT));
        }
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }

        $id = (int) $request->input('report_id');
        $status = (string) $request->input('workflow_status');
        if ($this->modReportRepository->updateWorkflowStatus($id, $status)) {
            $label = AtakModReportRepository::statusLabel(
                $this->modReportRepository->normalizeWorkflowStatus($status)
            );
            Session::flash('success', 'Statut mis à jour : ' . $label . '.');
        } else {
            Session::flash('error', 'Rapport introuvable ou statut inchangé.');
        }

        $qs = [];
        $sev = trim((string) $request->input('return_severity', ''));
        $st = trim((string) $request->input('return_status', ''));
        if ($sev !== '') {
            $qs['severity'] = $sev;
        }
        if ($st !== '') {
            $qs['status'] = $st;
        }

        return Response::redirect(url(self::REDIRECT) . ($qs !== [] ? ('?' . http_build_query($qs)) : ''));
    }

    public function delete(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url(self::REDIRECT));
        }
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }

        $id = (int) $request->input('report_id');
        if ($this->modReportRepository->deleteById($id)) {
            Session::flash('success', 'Rapport retiré du journal.');
        } else {
            Session::flash('error', 'Rapport introuvable.');
        }

        return Response::redirect(url(self::REDIRECT));
    }
}
