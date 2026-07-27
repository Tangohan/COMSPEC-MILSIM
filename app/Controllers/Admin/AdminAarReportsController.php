<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AarReportRepository;
use App\Repositories\TheatreMissionCycleRepository;
use App\Support\ModuleFeatureAccess;

final class AdminAarReportsController
{
    public function __construct(
        private ?AarReportRepository $reports = null,
        private ?TheatreMissionCycleRepository $cycles = null,
    ) {
        $this->reports ??= new AarReportRepository();
        $this->cycles ??= new TheatreMissionCycleRepository();
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('dashboard'));
        }
        $forbidden = ModuleFeatureAccess::guardAtak('view', 'back-office/atak/comptes-rendus');
        if ($forbidden instanceof Response) {
            return $forbidden;
        }

        $status = trim((string) $request->query('status', ''));
        $openActions = trim((string) $request->query('open_actions', '')) === '1';
        $allReports = $this->reports->listForTenant($tenantId, []);
        $filters = ['status' => $status];
        if ($openActions) {
            $filters['open_actions'] = true;
        }
        $reports = ($status !== '' || $openActions)
            ? $this->reports->listForTenant($tenantId, $filters)
            : $allReports;

        return Response::view('layout.main', [
            'content' => 'admin.aar_reports.index',
            'title' => 'Comptes rendus post-op',
            'isBackOfficeShell' => true,
            'boPageGroup' => 'Opérations',
            'boPageTitle' => 'Comptes rendus (AAR)',
            'boPageKicker' => 'OPÉRATIONS · RETOURS',
            'boPageSubtitle' => 'Rapports post-opération, points d’amélioration relevés et suivi de leur traitement.',
            'boPageAction' => 'Déposer un rapport',
            'boPageActionUrl' => url('back-office/atak/comptes-rendus') . '#nouveau',
            'boPageQuick' => [
                ['label' => 'En attente', 'href' => url('back-office/atak/comptes-rendus') . '?status=pending'],
                ['label' => 'Validés', 'href' => url('back-office/atak/comptes-rendus') . '?status=validated'],
                ['label' => 'Actions ouvertes', 'href' => url('back-office/atak/comptes-rendus') . '?open_actions=1'],
            ],
            'backOfficePageCss' => ['back-office-aar.css'],
            'aarReports' => $reports,
            'aarMissions' => array_map(fn (array $row) => $this->cycles->present($row), $this->cycles->listForTenant($tenantId, 100)),
            'aarStatusFilter' => $status,
            'aarOpenActionsFilter' => $openActions,
            'aarKpis' => $this->buildListKpis($allReports),
            'csrfToken' => Csrf::token(),
        ]);
    }

    public function show(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('dashboard'));
        }
        $forbidden = ModuleFeatureAccess::guardAtak('view', 'back-office/atak/comptes-rendus');
        if ($forbidden instanceof Response) {
            return $forbidden;
        }

        $id = (int) ($params['id'] ?? 0);
        $report = $this->reports->findForTenant($tenantId, $id);
        if ($report === null) {
            Session::flash('error', 'Ce compte rendu est introuvable.');
            return Response::redirect(url('back-office/atak/comptes-rendus'));
        }

        $operationLabel = trim((string) ($report['operation_label'] ?? $report['mission_title'] ?? ''));
        $pdfUrl = null;
        $missionId = (int) ($report['mission_cycle_id'] ?? 0);
        if ($missionId > 0) {
            $pdfUrl = url('api/operations/aar/' . $missionId . '/export.pdf');
        }

        return Response::view('layout.main', [
            'content' => 'admin.aar_reports.show',
            'title' => 'Compte rendu — ' . ($operationLabel !== '' ? $operationLabel : (string) ($report['title'] ?? '')),
            'isBackOfficeShell' => true,
            'boPageGroup' => 'Opérations',
            'boPageTitle' => ($operationLabel !== '' ? $operationLabel : (string) ($report['title'] ?? 'Compte rendu')),
            'boPageAction' => $pdfUrl !== null ? 'Exporter en PDF' : 'Modifier',
            'boPageActionUrl' => $pdfUrl ?? url('back-office/atak/comptes-rendus/' . $id . '/edit'),
            'boSkipPageHead' => true,
            'backOfficePageCss' => ['back-office-aar.css'],
            'aarReport' => $report,
            'aarPdfUrl' => $pdfUrl,
        ]);
    }

    public function edit(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('dashboard'));
        }
        $forbidden = ModuleFeatureAccess::guardAtak('manage', 'back-office/atak/comptes-rendus');
        if ($forbidden instanceof Response) {
            return $forbidden;
        }

        $id = (int) ($params['id'] ?? 0);
        $report = $this->reports->findForTenant($tenantId, $id);
        if ($report === null) {
            Session::flash('error', 'Ce compte rendu est introuvable.');
            return Response::redirect(url('back-office/atak/comptes-rendus'));
        }

        $operationLabel = trim((string) ($report['operation_label'] ?? $report['mission_title'] ?? ''));

        return Response::view('layout.main', [
            'content' => 'admin.aar_reports.edit',
            'title' => 'Modifier — ' . ($operationLabel !== '' ? $operationLabel : (string) ($report['title'] ?? '')),
            'isBackOfficeShell' => true,
            'boPageGroup' => 'Opérations',
            'boPageTitle' => 'Modifier le compte rendu',
            'boPageKicker' => 'OPÉRATIONS · RETOURS',
            'boPageSubtitle' => $operationLabel !== '' ? $operationLabel : (string) ($report['title'] ?? ''),
            'backOfficePageCss' => ['back-office-aar.css'],
            'aarReport' => $report,
            'aarMissions' => array_map(fn (array $row) => $this->cycles->present($row), $this->cycles->listForTenant($tenantId, 100)),
            'csrfToken' => Csrf::token(),
        ]);
    }

    /**
     * @param list<array<string, mixed>> $reports
     * @return list<array{label:string,value:string,delta:string,tone:string,pct:string,note:string}>
     */
    private function buildListKpis(array $reports): array
    {
        $twelveMonthsAgo = strtotime('-12 months');
        $reports12m = 0;
        $points = 0;
        $openActions = 0;
        $closedActions = 0;
        $withPoints = 0;
        $delaySumDays = 0.0;
        $delayCount = 0;

        foreach ($reports as $row) {
            $reportedTs = isset($row['reported_at']) ? strtotime((string) $row['reported_at']) : false;
            $in12m = $reportedTs !== false && $reportedTs >= $twelveMonthsAgo;
            if ($in12m) {
                $reports12m++;
            }

            $rowPoints = (int) (($row['totals']['points_releves'] ?? 0));
            $points += $rowPoints;
            if ($rowPoints > 0) {
                $withPoints++;
            }

            $openActions += (int) (($row['totals']['open_actions'] ?? 0));
            $closedActions += (int) (($row['totals']['closed_actions'] ?? 0));

            $missionEndTs = isset($row['mission_ended_at']) ? strtotime((string) $row['mission_ended_at']) : false;
            if ($reportedTs !== false && $missionEndTs !== false && $reportedTs >= $missionEndTs) {
                $delaySumDays += ($reportedTs - $missionEndTs) / 86400;
                $delayCount++;
            }
        }

        $total = count($reports);
        $avgDelay = $delayCount > 0 ? $delaySumDays / $delayCount : 0.0;
        $delayHours = $avgDelay * 24;
        $delayPct = $delayCount > 0 ? min(100, max(0, (int) round($delayHours / 72 * 100))) : 0;
        $delayValue = $delayCount > 0
            ? str_replace('.', ',', number_format($avgDelay, 1, '.', '')) . ' j'
            : '—';

        $reportsPct = $total > 0 ? min(100, (int) round($reports12m / max($total, 1) * 100)) : 0;
        $pointsPct = $total > 0 ? (int) round($withPoints / $total * 100) : 0;
        $actionsTotal = $openActions + $closedActions;
        $openPct = $actionsTotal > 0 ? (int) round($openActions / $actionsTotal * 100) : 0;

        return [
            [
                'label' => 'RAPPORTS 12 MOIS',
                'value' => (string) $reports12m,
                'delta' => '',
                'tone' => '#0b8a5c',
                'pct' => $reportsPct . '%',
                'note' => $total > 0 ? 'sur ' . $total . ' enregistrés' : 'aucun rapport',
            ],
            [
                'label' => 'DÉLAI MOYEN DÉPÔT',
                'value' => $delayValue,
                'delta' => '',
                'tone' => $delayCount > 0 && $delayHours <= 72 ? '#0b8a5c' : '#c98a12',
                'pct' => $delayPct . '%',
                'note' => 'cible 72 h',
            ],
            [
                'label' => 'POINTS RELEVÉS',
                'value' => (string) $points,
                'delta' => '',
                'tone' => '#1e4f80',
                'pct' => $pointsPct . '%',
                'note' => 'forts et faibles',
            ],
            [
                'label' => 'ACTIONS OUVERTES',
                'value' => (string) $openActions,
                'delta' => '',
                'tone' => $openActions > 0 ? '#c98a12' : '#0b8a5c',
                'pct' => $openPct . '%',
                'note' => $closedActions > 0 ? $closedActions . ' clôturées' : 'à suivre',
            ],
        ];
    }

    public function store(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId < 1 || $userId < 1) {
            return Response::redirect(url('dashboard'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');
            return Response::redirect(url('back-office/atak/comptes-rendus'));
        }
        $forbidden = ModuleFeatureAccess::guardAtak('manage', 'back-office/atak/comptes-rendus');
        if ($forbidden instanceof Response) {
            return $forbidden;
        }

        $saved = $this->reports->save($tenantId, null, $userId, $this->payloadFromRequest($request));
        Session::flash('success', 'Compte rendu enregistré.');
        $id = (int) ($saved['id'] ?? 0);

        return Response::redirect(url('back-office/atak/comptes-rendus' . ($id > 0 ? '/' . $id : '')));
    }

    public function update(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId < 1 || $userId < 1) {
            return Response::redirect(url('dashboard'));
        }

        $id = (int) ($params['id'] ?? 0);
        if ($id < 1) {
            Session::flash('error', 'Ce compte rendu est introuvable.');
            return Response::redirect(url('back-office/atak/comptes-rendus'));
        }

        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');
            return Response::redirect(url('back-office/atak/comptes-rendus/' . $id . '/edit'));
        }

        $existing = $this->reports->findForTenant($tenantId, $id);
        if ($existing === null) {
            Session::flash('error', 'Ce compte rendu est introuvable.');
            return Response::redirect(url('back-office/atak/comptes-rendus'));
        }

        $this->reports->save($tenantId, $id, $userId, $this->payloadFromRequest($request, true));
        Session::flash('success', 'Compte rendu mis à jour.');

        return Response::redirect(url('back-office/atak/comptes-rendus/' . $id));
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadFromRequest(Request $request, bool $allowStatus = false): array
    {
        $payload = [
            'mission_cycle_id' => $request->input('mission_cycle_id'),
            'title' => $request->input('title'),
            'operation_label' => $request->input('operation_label'),
            'summary_text' => $request->input('summary_text'),
            'strengths' => $request->input('strengths'),
            'weaknesses' => $request->input('weaknesses'),
            'open_actions' => $request->input('open_actions'),
            'closed_actions' => $request->input('closed_actions'),
            'metrics' => [
                'summary_heading' => $request->input('summary_heading'),
                'lessons_learned' => $request->input('lessons_learned'),
                'lessons_context' => $request->input('lessons_context'),
                'conclusion_text' => $request->input('conclusion_text'),
            ],
        ];
        if ($allowStatus) {
            $payload['status'] = $request->input('status');
        }

        return $payload;
    }
}
