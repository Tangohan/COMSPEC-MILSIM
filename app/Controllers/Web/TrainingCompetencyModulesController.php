<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TrainingCourseRepository;
use App\Services\Training\CompetencyModuleService;

/**
 * Pilotage des formations — modules compétences de l'organisation (ALPHA → DELTA)
 * et suivi des membres module par module. Accès : encadrement formation (TrainingTenantResourceMiddleware).
 */
final class TrainingCompetencyModulesController
{
    public function __construct(
        private ?CompetencyModuleService $service = null,
        private ?TrainingCourseRepository $courses = null,
    ) {
        $this->service ??= new CompetencyModuleService();
        $this->courses ??= \App\Core\Container::get(TrainingCourseRepository::class);
    }

    /** Concevoir les modules : mêmes droits que l'édition du contenu de formation. */
    public static function canDesign(): bool
    {
        $g = Gate::getInstance();

        return $g->allows('admin.organization') || $g->allows('admin.access') || $g->allows('training.manage')
            || $g->allows('training.create') || $g->allows('training.update');
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $editId = (int) $request->query('modifier', 0);
        $modules = $this->service->schemaReady() ? $this->service->modules($tenantId) : [];
        $editing = null;
        foreach ($modules as $m) {
            if ($m['id'] === $editId) {
                $editing = $m;
            }
        }
        $draft = Session::getFlash('competency_module_draft');

        return $this->view('admin.training.competency_modules', 'Modules compétences', [
            'competencySchemaReady' => $this->service->schemaReady(),
            'competencyModules' => $modules,
            'competencyEditing' => $editing,
            'competencyDraft' => is_array($draft) ? $draft : null,
            'competencyCanDesign' => self::canDesign(),
        ]);
    }

    public function save(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $back = training_lms_admin_url('competences/modules');
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée, réessayez.');

            return Response::redirect($back);
        }
        if (!self::canDesign()) {
            Session::flash('error', 'Vous pouvez suivre les membres, mais pas modifier les modules.');

            return Response::redirect($back);
        }
        $moduleId = (int) ($params['id'] ?? 0);
        $input = [
            'code' => $request->input('code', ''),
            'name' => $request->input('name', ''),
            'module_type' => $request->input('module_type', ''),
            'delivery_mode' => $request->input('delivery_mode', 'INITIAL'),
            'description' => $request->input('description', ''),
            'duration_min' => $request->input('duration_min', ''),
            'recurrence_days' => $request->input('recurrence_days', ''),
            'custom_order' => $request->input('custom_order', ''),
            'is_active' => $request->input('is_active', ''),
            'is_mandatory' => $request->input('is_mandatory', ''),
            'prereq_ids' => (array) $request->input('prereq_ids', []),
        ];
        [$id, $error] = $this->service->save($tenantId, (int) Session::get('user_id'), $moduleId > 0 ? $moduleId : null, $input);
        if ($error !== null) {
            Session::flash('error', $error);
            Session::flash('competency_module_draft', $input + ['id' => $moduleId]);

            return Response::redirect($moduleId > 0 ? $back . '?modifier=' . $moduleId . '#module-form' : $back . '#module-form');
        }
        Session::flash('success', $moduleId > 0 ? 'Module mis à jour.' : 'Module créé. Il apparaît dans le parcours des membres s’il est actif.');

        return Response::redirect($back . '#module-' . (int) $id);
    }

    public function toggle(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $back = training_lms_admin_url('competences/modules');
        if (!Csrf::validate($request->input('_csrf_token')) || !self::canDesign()) {
            Session::flash('error', 'Action non autorisée ou session expirée.');

            return Response::redirect($back);
        }
        $active = (string) $request->input('active', '0') === '1';
        $ok = $this->service->setActive($tenantId, (int) ($params['id'] ?? 0), $active);
        Session::flash($ok ? 'success' : 'error', $ok
            ? ($active ? 'Module réactivé dans le parcours.' : 'Module retiré du parcours. L’historique des membres est conservé.')
            : 'Module introuvable.');

        return Response::redirect($back . '#module-' . (int) ($params['id'] ?? 0));
    }

    public function tracking(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $module = $this->service->module($tenantId, (int) ($params['id'] ?? 0));
        if ($module === null) {
            Session::flash('error', 'Module introuvable.');

            return Response::redirect(training_lms_admin_url('competences/modules'));
        }
        $all = $this->service->modules($tenantId);
        $names = [];
        foreach ($all as $m) {
            $names[$m['id']] = $m['name'];
        }
        $filter = (string) $request->query('statut', '');

        return $this->view('admin.training.competency_module_tracking', 'Suivi — ' . $module['name'], [
            'competencyModule' => $module,
            'competencyPrereqNames' => array_values(array_filter(array_map(static fn (int $id): string => $names[$id] ?? '', $module['prereq_ids']))),
            'competencyTracking' => $this->service->tracking($tenantId, $module),
            'competencyStatusFilter' => array_key_exists($filter, CompetencyModuleService::STATUS_LABELS) ? $filter : '',
        ]);
    }

    public function record(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $moduleId = (int) ($params['id'] ?? 0);
        $back = training_lms_admin_url('competences/modules/' . $moduleId . '/suivi');
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée, réessayez.');

            return Response::redirect($back);
        }
        $module = $this->service->module($tenantId, $moduleId);
        if ($module === null) {
            Session::flash('error', 'Module introuvable.');

            return Response::redirect(training_lms_admin_url('competences/modules'));
        }
        $userIds = array_map('intval', (array) $request->input('user_ids', []));
        $single = (int) $request->input('user_id', 0);
        if ($single > 0) {
            $userIds[] = $single;
        }
        $status = (string) $request->input('status', '');
        if ($userIds === []) {
            Session::flash('error', 'Sélectionnez au moins un membre.');

            return Response::redirect($back);
        }
        $n = $this->service->record($tenantId, $module, (int) Session::get('user_id'), $userIds, $status);
        if ($n < 0) {
            Session::flash('error', 'Le suivi n’a pas pu être enregistré.');
        } else {
            $label = CompetencyModuleService::STATUS_LABELS[$status] ?? $status;
            Session::flash('success', $n === 1 ? 'Membre passé à « ' . $label . ' ».' : $n . ' membres passés à « ' . $label . ' ».');
        }

        return Response::redirect($back);
    }

    /** @param array<string, mixed> $extra */
    private function view(string $content, string $title, array $extra): Response
    {
        $tenantId = (int) Session::get('tenant_id');

        return Response::view('layout.training_lms_staff_shell', array_merge([
            'title' => $title,
            'content' => $content,
            'trainingAdminNav' => 'competences',
            'totalModules' => count($this->courses->listForTenant($tenantId, null)),
        ], $extra));
    }
}
