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
use App\Services\Aar\AarReportAdminService;
use App\Services\Aar\AarReportPdfService;
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

    private const BASE = 'back-office/atak/comptes-rendus';

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('dashboard'));
        }
        $forbidden = ModuleFeatureAccess::guardAtak('view', self::BASE);
        if ($forbidden instanceof Response) {
            return $forbidden;
        }

        $canManage = ModuleFeatureAccess::allows(RolePermissionMatrixCatalog::MODULE_ATAK, 'manage');
        $all = $this->reports->listForTenant($tenantId, []);
        $missions = $this->presentedMissions($tenantId);
        $missing = AarReportAdminService::missionsWithoutReport($missions, $all);

        $tab = (string) $request->query('vue', 'rapports');
        if (!in_array($tab, ['rapports', 'actions', 'missions'], true)) {
            $tab = 'rapports';
        }
        $status = trim((string) $request->query('status', ''));
        if ($status !== '' && !isset(AarReportAdminService::STATUSES[$status])) {
            $status = '';
        }
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => $status,
            'author' => trim((string) $request->query('auteur', '')),
            'period' => max(0, (int) $request->query('periode', 0)),
            'open_actions' => (string) $request->query('open_actions', '') === '1',
        ];
        $actionState = (string) $request->query('etat', 'ouvertes');
        if (!in_array($actionState, ['ouvertes', 'retard', 'closes', 'toutes'], true)) {
            $actionState = 'ouvertes';
        }
        $actions = array_values(array_filter(
            AarReportAdminService::actions($all),
            static fn (array $a): bool => match ($actionState) {
                'ouvertes' => $a['state'] === 'open',
                'retard' => $a['overdue'],
                'closes' => $a['state'] === 'closed',
                default => true,
            }
        ));

        return Response::view('layout.main', [
            'content' => 'admin.aar_reports.index',
            'title' => 'Comptes rendus post-op',
            'isBackOfficeShell' => true,
            'boPageGroup' => 'Opérations',
            'boPageTitle' => 'Comptes rendus',
            'boPageKicker' => 'OPÉRATIONS · RETOURS',
            'boPageSubtitle' => 'Les retours rédigés après chaque opération : relecture, validation et suivi des actions décidées.',
            'backOfficePageCss' => ['back-office-aar.css'],
            'aarAll' => $all,
            'aarReports' => AarReportAdminService::filter($all, $filters),
            'aarFilters' => $filters,
            'aarCounts' => AarReportAdminService::statusCounts($all),
            'aarAuthors' => AarReportAdminService::authors($all),
            'aarKpis' => AarReportAdminService::kpis($all, $missing),
            'aarActions' => $actions,
            'aarActionState' => $actionState,
            'aarMissing' => $missing,
            'aarTab' => $tab,
            'aarCanManage' => $canManage,
            'aarTemplateCount' => count($this->templates->listForTenant($tenantId, true)),
            'csrfToken' => Csrf::token(),
        ]);
    }

    public function create(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('dashboard'));
        }
        $forbidden = ModuleFeatureAccess::guardAtak('manage', self::BASE);
        if ($forbidden instanceof Response) {
            return $forbidden;
        }

        $missions = $this->presentedMissions($tenantId);
        $missing = AarReportAdminService::missionsWithoutReport($missions, $this->reports->listForTenant($tenantId, []));
        $prefill = [];
        $missionId = (int) $request->query('mission', 0);
        foreach ($missions as $m) {
            if ((int) $m['id'] === $missionId) {
                $prefill = [
                    'mission_cycle_id' => $missionId,
                    'operation_label' => (string) $m['title'],
                    'title' => 'Compte rendu — ' . (string) $m['title'],
                ];
            }
        }

        return Response::view('layout.main', [
            'content' => 'admin.aar_reports.create',
            'title' => 'Nouveau compte rendu',
            'isBackOfficeShell' => true,
            'boPageGroup' => 'Opérations',
            'boPageTitle' => 'Nouveau compte rendu',
            'boPageKicker' => 'OPÉRATIONS · RETOURS',
            'boPageSubtitle' => 'Racontez ce qui était prévu, ce qui s’est passé, ce qu’on en retient et ce qu’on fait ensuite.',
            'boPageQuick' => [
                ['label' => '← Comptes rendus', 'href' => url(self::BASE)],
                ['label' => 'Modèles', 'href' => url(self::BASE . '/modeles')],
            ],
            'backOfficePageCss' => ['back-office-aar.css'],
            'aarReport' => $prefill,
            'aarMissions' => $missions,
            'aarMissing' => $missing,
            'aarTemplates' => $this->templates->listForTenant($tenantId, true),
            'csrfToken' => Csrf::token(),
        ]);
    }

    public function show(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('dashboard'));
        }
        $forbidden = ModuleFeatureAccess::guardAtak('view', self::BASE);
        if ($forbidden instanceof Response) {
            return $forbidden;
        }

        $id = (int) ($params['id'] ?? 0);
        $report = $this->reports->findForTenant($tenantId, $id);
        if ($report === null) {
            Session::flash('error', 'Ce compte rendu est introuvable.');
            return Response::redirect(url(self::BASE));
        }

        $mission = null;
        $missionId = (int) ($report['mission_cycle_id'] ?? 0);
        if ($missionId > 0) {
            $row = $this->cycles->findForTenant($tenantId, $missionId);
            $mission = is_array($row) ? $this->cycles->present($row) : null;
        }
        $template = null;
        if ((int) ($report['template_id'] ?? 0) > 0) {
            $template = $this->templates->findForTenant($tenantId, (int) $report['template_id']);
        }
        $operationLabel = trim((string) ($report['operation_label'] ?? '')) !== '' ? (string) $report['operation_label'] : (string) ($report['title'] ?? 'Compte rendu');

        return Response::view('layout.main', [
            'content' => 'admin.aar_reports.show',
            'title' => 'Compte rendu — ' . $operationLabel,
            'isBackOfficeShell' => true,
            'boPageGroup' => 'Opérations',
            'boPageTitle' => $operationLabel,
            'boSkipPageHead' => true,
            'backOfficePageCss' => ['back-office-aar.css'],
            'aarReport' => $report,
            'aarMission' => $mission,
            'aarTemplate' => $template,
            'aarCanManage' => ModuleFeatureAccess::allows(RolePermissionMatrixCatalog::MODULE_ATAK, 'manage'),
            'aarMissionPdfUrl' => self::missionAarPdfUrl($mission),
            'csrfToken' => Csrf::token(),
        ]);
    }

    public function edit(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('dashboard'));
        }
        $forbidden = ModuleFeatureAccess::guardAtak('manage', self::BASE);
        if ($forbidden instanceof Response) {
            return $forbidden;
        }

        $id = (int) ($params['id'] ?? 0);
        $report = $this->reports->findForTenant($tenantId, $id);
        if ($report === null) {
            Session::flash('error', 'Ce compte rendu est introuvable.');
            return Response::redirect(url(self::BASE));
        }

        $operationLabel = trim((string) ($report['operation_label'] ?? $report['mission_title'] ?? ''));

        return Response::view('layout.main', [
            'content' => 'admin.aar_reports.edit',
            'title' => 'Modifier — ' . ($operationLabel !== '' ? $operationLabel : (string) ($report['title'] ?? '')),
            'isBackOfficeShell' => true,
            'boPageGroup' => 'Opérations',
            'boPageTitle' => 'Modifier le compte rendu',
            'boPageKicker' => (string) ($report['reference_code'] ?? 'OPÉRATIONS · RETOURS'),
            'boPageSubtitle' => $operationLabel !== '' ? $operationLabel : (string) ($report['title'] ?? ''),
            'boPageQuick' => [
                ['label' => '← Retour à la fiche', 'href' => url(self::BASE . '/' . $id)],
            ],
            'backOfficePageCss' => ['back-office-aar.css'],
            'aarReport' => $report,
            'aarMissions' => $this->missionsForEdit($tenantId, $report),
            'csrfToken' => Csrf::token(),
        ]);
    }

    public function status(Request $request, array $params = []): Response
    {
        $ctx = $this->reportContext($request, $params);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $userId, $report] = $ctx;
        $to = (string) $request->input('status', '');
        $from = (string) ($report['status'] ?? 'pending');
        if (!AarReportAdminService::canTransition($from, $to)) {
            Session::flash('error', 'Ce changement de statut n’est pas possible depuis « ' . AarReportAdminService::statusLabel($from) . ' ».');
        } else {
            $this->reports->setStatus($tenantId, (int) $report['id'], $to, $userId);
            Session::flash('success', match ($to) {
                'validated' => 'Compte rendu validé.',
                'in_review' => 'Compte rendu passé en relecture.',
                'missing' => 'Compte rendu marqué manquant.',
                default => 'Compte rendu renvoyé à son auteur.',
            });
        }

        return Response::redirect(url(self::BASE . '/' . (int) $report['id']));
    }

    public function toggleAction(Request $request, array $params = []): Response
    {
        $ctx = $this->reportContext($request, $params);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, , $report] = $ctx;
        $state = (string) $request->input('state', '');
        $index = (int) $request->input('index', -1);
        $next = AarReportAdminService::toggleAction(
            is_array($report['open_actions'] ?? null) ? $report['open_actions'] : [],
            is_array($report['closed_actions'] ?? null) ? $report['closed_actions'] : [],
            $state,
            $index
        );
        if ($next === null) {
            Session::flash('error', 'Cette action a déjà été modifiée. Rechargez la page.');
        } else {
            $this->reports->saveActions($tenantId, (int) $report['id'], $next['open'], $next['closed']);
            Session::flash('success', $state === 'open' ? 'Action clôturée.' : 'Action rouverte.');
        }
        $back = (string) $request->input('retour', '');

        return Response::redirect($back === 'suivi'
            ? url(self::BASE) . '?vue=actions'
            : url(self::BASE . '/' . (int) $report['id']) . '#actions');
    }

    public function destroy(Request $request, array $params = []): Response
    {
        $ctx = $this->reportContext($request, $params);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, , $report] = $ctx;
        $this->reports->delete($tenantId, (int) $report['id']);
        Session::flash('success', 'Compte rendu ' . (string) ($report['reference_code'] ?? '') . ' supprimé.');

        return Response::redirect(url(self::BASE));
    }

    public function pdf(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('dashboard'));
        }
        $forbidden = ModuleFeatureAccess::guardAtak('view', self::BASE);
        if ($forbidden instanceof Response) {
            return $forbidden;
        }
        $report = $this->reports->findForTenant($tenantId, (int) ($params['id'] ?? 0));
        if ($report === null) {
            Session::flash('error', 'Ce compte rendu est introuvable.');
            return Response::redirect(url(self::BASE));
        }

        return (new AarReportPdfService())->export($report);
    }

    public function exportCsv(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('dashboard'));
        }
        $forbidden = ModuleFeatureAccess::guardAtak('view', self::BASE);
        if ($forbidden instanceof Response) {
            return $forbidden;
        }
        $rows = AarReportAdminService::filter($this->reports->listForTenant($tenantId, []), [
            'q' => trim((string) $request->query('q', '')),
            'status' => trim((string) $request->query('status', '')),
            'author' => trim((string) $request->query('auteur', '')),
            'period' => max(0, (int) $request->query('periode', 0)),
            'open_actions' => (string) $request->query('open_actions', '') === '1',
        ]);

        return (new Response())
            ->setStatusCode(200)
            ->header('Content-Type', 'text/csv; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="comptes-rendus-' . date('Ymd-Hi') . '.csv"')
            ->header('Cache-Control', 'private, no-store')
            ->setBody(AarReportAdminService::csv($rows));
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
            return Response::redirect(url(self::BASE . '/nouveau'));
        }
        $forbidden = ModuleFeatureAccess::guardAtak('manage', self::BASE);
        if ($forbidden instanceof Response) {
            return $forbidden;
        }

        $built = $this->payloadFromRequest($request, $tenantId);
        if ($built['error'] !== null) {
            Session::flash('error', $built['error']);
            $mission = (int) $request->input('mission_cycle_id', 0);
            return Response::redirect(url(self::BASE . '/nouveau') . ($mission > 0 ? '?mission=' . $mission : ''));
        }

        try {
            $saved = $this->reports->save($tenantId, null, $userId, $built['payload']);
        } catch (\DomainException $e) {
            // Cycle de mission d'une autre communauté, ou supprimé entre-temps : rien n'est enregistré (SEC-032).
            Session::flash('error', $e->getMessage());
            return Response::redirect(url(self::BASE . '/nouveau'));
        }
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

        $forbidden = ModuleFeatureAccess::guardAtak('manage', self::BASE);
        if ($forbidden instanceof Response) {
            return $forbidden;
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

        try {
            $this->reports->save($tenantId, $id, $userId, $built['payload']);
        } catch (\DomainException $e) {
            // Cycle de mission d'une autre communauté, ou supprimé entre-temps : le compte rendu reste tel quel (SEC-032).
            Session::flash('error', $e->getMessage());
            return Response::redirect(url('back-office/atak/comptes-rendus/' . $id . '/edit'));
        }
        Session::flash('success', 'Compte rendu mis à jour.');

        return Response::redirect(url('back-office/atak/comptes-rendus/' . $id));
    }

    public function templatesIndex(Request $request, array $params = []): Response
    {
        $ctx = $this->templatesContext('manage');
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $usage = [];
        foreach ($this->reports->listForTenant($ctx['tenant_id'], []) as $r) {
            $tid = (int) ($r['template_id'] ?? 0);
            if ($tid > 0) {
                $usage[$tid] = ($usage[$tid] ?? 0) + 1;
            }
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
                ['label' => '← Comptes rendus', 'href' => url('back-office/atak/comptes-rendus')],
                ['label' => '+ Nouveau modèle', 'href' => url('back-office/atak/comptes-rendus/modeles/nouveau')],
            ],
            'backOfficePageCss' => ['back-office-aar.css'],
            'aarTemplates' => $this->templates->listForTenant($ctx['tenant_id']),
            'aarTemplateUsage' => $usage,
            'csrfToken' => Csrf::token(),
        ]);
    }

    public function templatesDuplicate(Request $request, array $params = []): Response
    {
        $ctx = $this->templatesContext('manage');
        if ($ctx instanceof Response) {
            return $ctx;
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');
            return Response::redirect(url(self::BASE . '/modeles'));
        }
        $template = $this->templates->findForTenant($ctx['tenant_id'], (int) ($params['id'] ?? 0));
        if ($template === null) {
            Session::flash('error', 'Ce modèle est introuvable.');
            return Response::redirect(url(self::BASE . '/modeles'));
        }
        $saved = $this->templates->save($ctx['tenant_id'], null, $ctx['user_id'], [
            'title' => mb_substr((string) ($template['title'] ?? 'Modèle'), 0, 188) . ' (copie)',
            'description' => $template['description'] ?? null,
            'status' => 'active',
            'fields' => AarCustomForm::normalizeFields(is_array($template['fields'] ?? null) ? $template['fields'] : []),
        ]);
        Session::flash('success', 'Modèle dupliqué : ajustez la copie puis enregistrez.');

        return Response::redirect(url(self::BASE . '/modeles/' . (int) ($saved['id'] ?? 0)));
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
     * @return list<array<string, mixed>>
     */
    private function presentedMissions(int $tenantId): array
    {
        try {
            return array_map(fn (array $row) => $this->cycles->present($row), $this->cycles->listForTenant($tenantId, 100));
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Missions du formulaire de modification : les 100 plus récentes de la communauté, plus la mission déjà liée quand
     * elle est plus ancienne (DEF-039). Sans elle, la liste ne la proposait pas, le formulaire envoyait « Aucune
     * mission » et l'enregistrement effaçait le lien sans message. Seul un cycle de la communauté est ajouté.
     *
     * @param array<string, mixed> $report
     * @return list<array<string, mixed>>
     */
    private function missionsForEdit(int $tenantId, array $report): array
    {
        $missions = $this->presentedMissions($tenantId);
        $linkedId = (int) ($report['mission_cycle_id'] ?? 0);
        if ($linkedId < 1) {
            return $missions;
        }
        foreach ($missions as $mission) {
            if ((int) ($mission['id'] ?? 0) === $linkedId) {
                return $missions;
            }
        }
        try {
            $row = $this->cycles->findForTenant($tenantId, $linkedId);
        } catch (\Throwable) {
            return $missions;
        }
        if (is_array($row)) {
            $missions[] = $this->cycles->present($row);
        }

        return $missions;
    }

    /**
     * Synthèse PDF de la mission liée : AAR de son bac de relecture (replay_mission_id) sur sa
     * fenêtre. L’id du cycle n’est pas un missionId : /api/operations/aar le refuse (SEC-008).
     *
     * @param array<string, mixed>|null $mission cycle présenté (TheatreMissionCycleRepository::present)
     */
    public static function missionAarPdfUrl(?array $mission): ?string
    {
        $replayMissionId = trim((string) ($mission['replay_mission_id'] ?? ''));
        if ($replayMissionId === '') {
            return null;
        }
        $window = is_array($mission['window'] ?? null) ? $mission['window'] : [];
        $q = http_build_query(array_filter([
            'from' => $window['from'] ?? null,
            'to' => $window['to'] ?? null,
        ], static fn ($v) => $v !== null && $v !== ''));

        return url('api/operations/aar/' . rawurlencode($replayMissionId) . '/export.pdf') . ($q !== '' ? '?' . $q : '');
    }

    /**
     * Contrôles communs aux actions POST sur un compte rendu (droit, jeton, existence).
     *
     * @return array{0:int,1:int,2:array<string, mixed>}|Response
     */
    private function reportContext(Request $request, array $params): array|Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId < 1 || $userId < 1) {
            return Response::redirect(url('dashboard'));
        }
        $forbidden = ModuleFeatureAccess::guardAtak('manage', self::BASE);
        if ($forbidden instanceof Response) {
            return $forbidden;
        }
        $id = (int) ($params['id'] ?? 0);
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');
            return Response::redirect(url(self::BASE . ($id > 0 ? '/' . $id : '')));
        }
        $report = $this->reports->findForTenant($tenantId, $id);
        if ($report === null) {
            Session::flash('error', 'Ce compte rendu est introuvable.');
            return Response::redirect(url(self::BASE));
        }

        return [$tenantId, $userId, $report];
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
        $rows = $request->input('actions', null);
        if (is_array($rows)) {
            $split = AarReportAdminService::actionsFromRows($rows);
            $payload['open_actions'] = $split['open'];
            $payload['closed_actions'] = $split['closed'];
        }
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
                ['label' => '← Tous les modèles', 'href' => url('back-office/atak/comptes-rendus/modeles')],
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
