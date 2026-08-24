<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AarReportRepository;
use App\Repositories\AarReportTemplateRepository;
use App\Repositories\TheatreMissionCycleRepository;
use App\Services\Rbac\RolePermissionMatrixCatalog;
use App\Support\AarCustomForm;
use App\Support\ModuleFeatureAccess;

final class AdminAarReportsController
{
    public function __construct(
        private ?AarReportRepository $reports = null,
        private ?TheatreMissionCycleRepository $cycles = null,
        private ?AarReportTemplateRepository $templates = null,
    ) {
        $this->reports ??= new AarReportRepository();
        $this->cycles ??= new TheatreMissionCycleRepository();
        $this->templates ??= new AarReportTemplateRepository();
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

        $canManage = ModuleFeatureAccess::allows(RolePermissionMatrixCatalog::MODULE_ATAK, 'manage');
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

        $activeTemplates = $this->templates->listForTenant($tenantId, true);
        $quick = [
            ['label' => 'En attente', 'href' => url('back-office/atak/comptes-rendus') . '?status=pending'],
            ['label' => 'Validés', 'href' => url('back-office/atak/comptes-rendus') . '?status=validated'],
            ['label' => 'Actions ouvertes', 'href' => url('back-office/atak/comptes-rendus') . '?open_actions=1'],
        ];
        if ($canManage) {
            $quick[] = ['label' => 'Modèles', 'href' => url('back-office/atak/comptes-rendus/modeles')];
        }

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
            'boPageQuick' => $quick,
            'backOfficePageCss' => ['back-office-aar.css'],
            'aarReports' => $reports,
            'aarMissions' => array_map(fn (array $row) => $this->cycles->present($row), $this->cycles->listForTenant($tenantId, 100)),
            'aarTemplates' => $activeTemplates,
            'aarCanManageTemplates' => $canManage,
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

        $built = $this->payloadFromRequest($request, $tenantId);
        if ($built['error'] !== null) {
            Session::flash('error', $built['error']);
            return Response::redirect(url('back-office/atak/comptes-rendus') . '#nouveau');
        }

        $saved = $this->reports->save($tenantId, null, $userId, $built['payload']);
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

        $built = $this->payloadFromRequest($request, $tenantId, true, $existing);
        if ($built['error'] !== null) {
            Session::flash('error', $built['error']);
            return Response::redirect(url('back-office/atak/comptes-rendus/' . $id . '/edit'));
        }

        $this->reports->save($tenantId, $id, $userId, $built['payload']);
        Session::flash('success', 'Compte rendu mis à jour.');

        return Response::redirect(url('back-office/atak/comptes-rendus/' . $id));
    }

    public function templatesIndex(Request $request, array $params = []): Response
    {
        $ctx = $this->templatesContext('manage');
        if ($ctx instanceof Response) {
            return $ctx;
        }

        return Response::view('layout.main', [
            'content' => 'admin.aar_reports.templates',
            'title' => 'Modèles de debriefing',
            'isBackOfficeShell' => true,
            'boPageGroup' => 'Opérations',
            'boPageTitle' => 'Modèles de debriefing',
            'boPageKicker' => 'OPÉRATIONS · RETOURS',
            'boPageSubtitle' => 'Préparez des questionnaires de compte rendu (questions courtes, listes, cases à cocher, texte libre).',
            'boPageAction' => 'Nouveau modèle',
            'boPageActionUrl' => url('back-office/atak/comptes-rendus/modeles/nouveau'),
            'boPageQuick' => [
                ['label' => 'Comptes rendus', 'href' => url('back-office/atak/comptes-rendus')],
            ],
            'backOfficePageCss' => ['back-office-aar.css'],
            'aarTemplates' => $this->templates->listForTenant($ctx['tenant_id']),
            'csrfToken' => Csrf::token(),
        ]);
    }

    public function templatesCreate(Request $request, array $params = []): Response
    {
        $ctx = $this->templatesContext('manage');
        if ($ctx instanceof Response) {
            return $ctx;
        }

        return $this->templateFormView(null);
    }

    public function templatesStore(Request $request, array $params = []): Response
    {
        $ctx = $this->templatesContext('manage');
        if ($ctx instanceof Response) {
            return $ctx;
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');
            return Response::redirect(url('back-office/atak/comptes-rendus/modeles/nouveau'));
        }

        $payload = $this->templatePayloadFromRequest($request);
        $payload['fields'] = AarCustomForm::normalizeFields($payload['fields'] ?? []);
        if ($payload['fields'] === []) {
            Session::flash('error', 'Ajoutez au moins une question au modèle.');
            return Response::redirect(url('back-office/atak/comptes-rendus/modeles/nouveau'));
        }

        $saved = $this->templates->save($ctx['tenant_id'], null, $ctx['user_id'], $payload);
        Session::flash('success', 'Modèle enregistré. Vous pouvez l’utiliser pour un nouveau compte rendu.');

        return Response::redirect(url('back-office/atak/comptes-rendus/modeles/' . (int) ($saved['id'] ?? 0)));
    }

    public function templatesEdit(Request $request, array $params = []): Response
    {
        $ctx = $this->templatesContext('manage');
        if ($ctx instanceof Response) {
            return $ctx;
        }

        $id = (int) ($params['id'] ?? 0);
        $template = $this->templates->findForTenant($ctx['tenant_id'], $id);
        if ($template === null) {
            Session::flash('error', 'Ce modèle est introuvable.');
            return Response::redirect(url('back-office/atak/comptes-rendus/modeles'));
        }

        return $this->templateFormView($template);
    }

    public function templatesUpdate(Request $request, array $params = []): Response
    {
        $ctx = $this->templatesContext('manage');
        if ($ctx instanceof Response) {
            return $ctx;
        }

        $id = (int) ($params['id'] ?? 0);
        $template = $this->templates->findForTenant($ctx['tenant_id'], $id);
        if ($template === null) {
            Session::flash('error', 'Ce modèle est introuvable.');
            return Response::redirect(url('back-office/atak/comptes-rendus/modeles'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');
            return Response::redirect(url('back-office/atak/comptes-rendus/modeles/' . $id));
        }

        $payload = $this->templatePayloadFromRequest($request);
        $payload['fields'] = AarCustomForm::normalizeFields($payload['fields'] ?? []);
        if ($payload['fields'] === []) {
            Session::flash('error', 'Ajoutez au moins une question au modèle.');
            return Response::redirect(url('back-office/atak/comptes-rendus/modeles/' . $id));
        }

        $this->templates->save($ctx['tenant_id'], $id, $ctx['user_id'], $payload);
        Session::flash('success', 'Modèle mis à jour. Les comptes rendus déjà déposés conservent leurs questions d’origine.');

        return Response::redirect(url('back-office/atak/comptes-rendus/modeles'));
    }

    public function templatesArchive(Request $request, array $params = []): Response
    {
        $ctx = $this->templatesContext('manage');
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $id = (int) ($params['id'] ?? 0);
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');
            return Response::redirect(url('back-office/atak/comptes-rendus/modeles'));
        }
        $this->templates->archive($ctx['tenant_id'], $id);
        Session::flash('success', 'Modèle archivé. Il n’apparaît plus pour les nouveaux comptes rendus.');

        return Response::redirect(url('back-office/atak/comptes-rendus/modeles'));
    }

    public function templatesRestore(Request $request, array $params = []): Response
    {
        $ctx = $this->templatesContext('manage');
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $id = (int) ($params['id'] ?? 0);
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');
            return Response::redirect(url('back-office/atak/comptes-rendus/modeles'));
        }
        $this->templates->restore($ctx['tenant_id'], $id);
        Session::flash('success', 'Modèle réactivé.');

        return Response::redirect(url('back-office/atak/comptes-rendus/modeles'));
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

    /**
     * @param array<string, mixed>|null $existing
     * @return array{payload: array<string, mixed>, error: ?string}
     */
    private function payloadFromRequest(Request $request, int $tenantId, bool $allowStatus = false, ?array $existing = null): array
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

        $rawAnswers = $request->input('answers', []);
        if (!is_array($rawAnswers)) {
            $rawAnswers = [];
        }

        if ($existing !== null && !empty($existing['is_custom'])) {
            $fields = is_array($existing['custom_fields'] ?? null) ? $existing['custom_fields'] : [];
            $bundle = AarCustomForm::collectAnswers($fields, $rawAnswers);
            $missing = AarCustomForm::missingRequired($fields, $bundle['answers']);
            if ($missing !== []) {
                return ['payload' => [], 'error' => 'Merci de répondre à : ' . implode(', ', $missing) . '.'];
            }
            $payload['template_id'] = (int) ($existing['template_id'] ?? 0);
            $payload['custom_answers'] = $bundle;

            return ['payload' => $payload, 'error' => null];
        }

        $templateId = (int) $request->input('template_id', 0);
        if ($templateId > 0) {
            $template = $this->templates->findForTenant($tenantId, $templateId);
            if ($template === null || ($template['status'] ?? '') !== 'active') {
                return ['payload' => [], 'error' => 'Ce modèle de debriefing n’est plus disponible.'];
            }
            $fields = is_array($template['fields'] ?? null) ? $template['fields'] : [];
            if ($fields === []) {
                return ['payload' => [], 'error' => 'Ce modèle n’a aucune question. Choisissez-en un autre ou le formulaire standard.'];
            }
            $bundle = AarCustomForm::collectAnswers($fields, $rawAnswers);
            $missing = AarCustomForm::missingRequired($fields, $bundle['answers']);
            if ($missing !== []) {
                return ['payload' => [], 'error' => 'Merci de répondre à : ' . implode(', ', $missing) . '.'];
            }
            $payload['template_id'] = $templateId;
            $payload['custom_answers'] = $bundle;
        }

        return ['payload' => $payload, 'error' => null];
    }

    /**
     * @return array{tenant_id:int,user_id:int}|Response
     */
    private function templatesContext(string $action): array|Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId < 1 || $userId < 1) {
            return Response::redirect(url('dashboard'));
        }
        $forbidden = ModuleFeatureAccess::guardAtak($action, 'back-office/atak/comptes-rendus');
        if ($forbidden instanceof Response) {
            return $forbidden;
        }

        return ['tenant_id' => $tenantId, 'user_id' => $userId];
    }

    /**
     * @param array<string, mixed>|null $template
     */
    private function templateFormView(?array $template): Response
    {
        $isEdit = $template !== null;
        $title = $isEdit
            ? 'Modifier le modèle'
            : 'Nouveau modèle de debriefing';

        return Response::view('layout.main', [
            'content' => 'admin.aar_reports.template_form',
            'title' => $title,
            'isBackOfficeShell' => true,
            'boPageGroup' => 'Opérations',
            'boPageTitle' => $title,
            'boPageKicker' => 'OPÉRATIONS · RETOURS',
            'boPageSubtitle' => 'Composez les questions que les opérateurs rempliront après l’opération.',
            'boPageQuick' => [
                ['label' => 'Tous les modèles', 'href' => url('back-office/atak/comptes-rendus/modeles')],
            ],
            'backOfficePageCss' => ['back-office-aar.css'],
            'aarTemplate' => $template ?? [],
            'csrfToken' => Csrf::token(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function templatePayloadFromRequest(Request $request): array
    {
        return [
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'status' => $request->input('status', 'active'),
            'fields' => $request->input('fields', []),
        ];
    }
}
