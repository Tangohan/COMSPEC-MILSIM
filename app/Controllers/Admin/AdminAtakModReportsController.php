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
        $rows = $this->modReportRepository->listRecent(200, $severityFilter);
        $total = $this->modReportRepository->countAll($severityFilter);
        $totalAll = $this->modReportRepository->countAll(null);

        return Response::view('layout.main', [
            'title' => 'Rapports Overwatch',
            'content' => 'admin.atak-mod-reports.index',
            'rows' => $rows,
            'total' => $total,
            'totalAll' => $totalAll,
            'severityFilter' => $severityFilter ?? '',
        ]);
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
