<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\MemberIntegrationAppointmentRepository;
use App\Repositories\MemberIntegrationRepository;
use App\Repositories\MemberIntegrationTemplateRepository;
use App\Repositories\RoleRepository;
use App\Repositories\TrainingCompetencyRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;
use App\Services\MemberIntegration\MemberIntegrationAutomationService;
use App\Services\MemberIntegration\MemberIntegrationInvitationService;
use App\Services\MemberIntegration\MemberIntegrationService;
use App\Support\MemberIntegrationCatalog;

final class MemberIntegrationAdminController
{
    public function __construct(
        private MemberIntegrationRepository $integrations,
        private MemberIntegrationTemplateRepository $templates,
        private MemberIntegrationAppointmentRepository $appointments,
        private MemberIntegrationService $service,
        private MemberIntegrationInvitationService $invitations,
        private MemberIntegrationAutomationService $automation,
        private TrainingCompetencyRepository $matrices,
        private UserRepository $users,
        private UnitRepository $units,
        private RoleRepository $roles,
    ) {}

    private function tenantId(): int
    {
        return (int) Session::get('tenant_id');
    }

    private function actorId(): int
    {
        return (int) Session::get('user_id');
    }

    private function deny(): Response
    {
        Session::flash('error', 'Vous n’êtes pas habilité à consulter l’intégration des nouveaux membres.');

        return Response::redirect(url('dashboard'));
    }

    private function canView(): bool
    {
        $g = Gate::getInstance();

        return $g->allows('member_integration.view')
            || $g->allows('member_integration.manage')
            || $g->allows('admin.organization')
            || $g->allows('admin.access');
    }

    private function canManage(): bool
    {
        $g = Gate::getInstance();

        return $g->allows('member_integration.manage') || $g->allows('admin.organization') || $g->allows('admin.access');
    }

    private function canAssign(): bool
    {
        $g = Gate::getInstance();

        return $this->canManage() || $g->allows('member_integration.assign');
    }

    private function canNote(): bool
    {
        $g = Gate::getInstance();

        return $this->canManage() || $g->allows('member_integration.note') || $this->canAssign();
    }

    private function canTemplates(): bool
    {
        $g = Gate::getInstance();

        return $g->allows('member_integration.template_manage') || $g->allows('admin.organization') || $g->allows('admin.access');
    }

    public function index(Request $request, array $params = []): Response
    {
        if (!$this->canView()) {
            return $this->deny();
        }
        $tenantId = $this->tenantId();
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => trim((string) $request->query('status', '')),
            'referent_user_id' => (int) $request->query('referent_user_id', 0),
            'unit_id' => (int) $request->query('unit_id', 0),
            'role_id' => (int) $request->query('role_id', 0),
            'matrix_id' => (int) $request->query('matrix_id', 0),
            'arrived_from' => trim((string) $request->query('arrived_from', '')),
            'arrived_to' => trim((string) $request->query('arrived_to', '')),
            'dossier_incomplete' => $request->query('dossier_incomplete', '') === '1',
            'overdue' => $request->query('overdue', '') === '1',
        ];
        $view = $request->query('vue', '') === 'colonnes' ? 'colonnes' : 'tableau';
        $rows = $this->integrations->listDashboard($tenantId, $filters, 250);
        $byStep = [];
        foreach ($rows as $row) {
            $label = trim((string) ($row['current_step_title'] ?? '')) ?: 'Sans étape en cours';
            $byStep[$label][] = $row;
        }

        return Response::view('layout.main', [
            'title' => 'Intégration des nouveaux membres',
            'content' => 'admin.member_integration.index',
            'rows' => $rows,
            'byStep' => $byStep,
            'filters' => $filters,
            'viewMode' => $view,
            'statusLabels' => MemberIntegrationCatalog::statusLabels(),
            'units' => $this->units->listFlatForStructure($tenantId),
            'roles' => $this->roles->forTenantOrganization($tenantId),
            'matrices' => $this->matrices->listMatrices($tenantId),
            'staff' => $this->users->allForTenant($tenantId),
            'canManage' => $this->canManage(),
            'canAssign' => $this->canAssign(),
            'canTemplates' => $this->canTemplates(),
        ]);
    }

    public function show(Request $request, array $params = []): Response
    {
        if (!$this->canView()) {
            return $this->deny();
        }
        $tenantId = $this->tenantId();
        $id = (int) ($params['id'] ?? 0);
        $row = $this->integrations->findForTenant($tenantId, $id);
        if (!$row) {
            Session::flash('error', 'Ce parcours d’intégration est introuvable.');

            return Response::redirect(url('back-office/integration-membres'));
        }
        $userId = (int) $row['user_id'];
        $user = $this->users->findById($userId, $tenantId) ?? [];
        $this->service->refresh($tenantId, $id, $this->actorId());
        $row = $this->integrations->findForTenant($tenantId, $id) ?? $row;
        $row['display_name'] = (string) ($user['display_name'] ?? $user['callsign'] ?? $user['email'] ?? 'Membre');

        return Response::view('layout.main', [
            'title' => 'Parcours d’intégration',
            'content' => 'admin.member_integration.show',
            'integration' => $row,
            'steps' => $this->integrations->listSteps($tenantId, $id),
            'events' => $this->integrations->listEvents($tenantId, $id),
            'referents' => $this->integrations->listReferents($tenantId, $id),
            'appointments' => $this->appointments->listForIntegration($tenantId, $id),
            'matricesAssigned' => $this->matrices->listAssignmentsForUser($tenantId, $userId),
            'matricesAll' => $this->matrices->listMatrices($tenantId),
            'dossier' => $this->service->dossierSnapshot($userId, $user, $tenantId),
            'staff' => $this->users->allForTenant($tenantId),
            'statusLabels' => MemberIntegrationCatalog::statusLabels(),
            'stepTypeLabels' => MemberIntegrationCatalog::stepTypeLabels(),
            'canManage' => $this->canManage(),
            'canAssign' => $this->canAssign(),
            'canNote' => $this->canNote(),
        ]);
    }

    public function note(Request $request, array $params = []): Response
    {
        if (!$this->canNote() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Action impossible.');

            return Response::redirect(url('back-office/integration-membres'));
        }
        $id = (int) ($params['id'] ?? 0);
        $ok = $this->service->addNote(
            $this->tenantId(),
            $id,
            $this->actorId(),
            (string) $request->input('message', ''),
            $request->input('visible_member') === '1'
        );
        Session::flash($ok ? 'success' : 'error', $ok ? 'Note enregistrée.' : 'Le message n’a pas pu être enregistré.');

        return Response::redirect(url('back-office/integration-membres/' . $id));
    }

    public function completeStep(Request $request, array $params = []): Response
    {
        if (!$this->canAssign() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Action impossible.');

            return Response::redirect(url('back-office/integration-membres'));
        }
        $id = (int) ($params['id'] ?? 0);
        $stepId = (int) $request->input('step_id', 0);
        $force = $request->input('force') === '1';
        $res = $this->service->completeStep($this->tenantId(), $id, $stepId, $this->actorId(), $force, (string) $request->input('reason', ''));
        Session::flash(!empty($res['ok']) ? 'success' : 'error', (string) ($res['message'] ?? (!empty($res['ok']) ? 'Étape validée.' : 'Validation impossible.')));

        return Response::redirect(url('back-office/integration-membres/' . $id));
    }

    public function referents(Request $request, array $params = []): Response
    {
        if (!$this->canAssign() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Action impossible.');

            return Response::redirect(url('back-office/integration-membres'));
        }
        $id = (int) ($params['id'] ?? 0);
        $primary = (int) $request->input('primary_referent_user_id', 0);
        $secondary = array_map('intval', (array) $request->input('secondary_referent_user_ids', []));
        $ok = $this->service->assignReferents($this->tenantId(), $id, $primary, $secondary, $this->actorId());
        Session::flash($ok ? 'success' : 'error', $ok ? 'Référents mis à jour.' : 'Mise à jour impossible.');

        return Response::redirect(url('back-office/integration-membres/' . $id));
    }

    public function matrix(Request $request, array $params = []): Response
    {
        if (!$this->canAssign() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Action impossible.');

            return Response::redirect(url('back-office/integration-membres'));
        }
        $id = (int) ($params['id'] ?? 0);
        $row = $this->integrations->findForTenant($this->tenantId(), $id);
        if (!$row) {
            return Response::redirect(url('back-office/integration-membres'));
        }
        $action = (string) $request->input('action', '');
        $matrixId = (int) $request->input('matrix_id', 0);
        $userId = (int) $row['user_id'];
        if ($action === 'assign') {
            $this->service->assignMatrix($this->tenantId(), $userId, $matrixId, $this->actorId(), $id);
            Session::flash('success', 'Le membre a été ajouté au groupe de suivi.');
        } elseif ($action === 'unassign') {
            $this->service->unassignMatrix($this->tenantId(), $userId, $matrixId, $this->actorId(), $id);
            Session::flash('success', 'Le membre a été retiré du groupe de suivi.');
        } elseif ($action === 'auto') {
            $n = $this->service->autoDetectMatrices($this->tenantId(), $matrixId, $this->actorId());
            Session::flash('success', $n . ' membre(s) placé(s) automatiquement dans le groupe.');
        }

        return Response::redirect(url('back-office/integration-membres/' . $id));
    }

    public function appointment(Request $request, array $params = []): Response
    {
        if (!$this->canAssign() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Action impossible.');

            return Response::redirect(url('back-office/integration-membres'));
        }
        $id = (int) ($params['id'] ?? 0);
        $row = $this->integrations->findForTenant($this->tenantId(), $id);
        if (!$row) {
            return Response::redirect(url('back-office/integration-membres'));
        }
        $participants = [(int) $row['user_id']];
        $extra = (int) $request->input('extra_participant_user_id', 0);
        if ($extra > 0) {
            $participants[] = $extra;
        }
        $res = $this->invitations->createAppointmentWithInvites($this->tenantId(), [
            'integration_id' => $id,
            'step_id' => (int) $request->input('step_id', 0) ?: null,
            'title' => (string) $request->input('title', ''),
            'description' => (string) $request->input('description', ''),
            'event_type' => (string) $request->input('event_type', 'accueil'),
            'starts_at' => str_replace('T', ' ', (string) $request->input('starts_at', '')),
            'ends_at' => str_replace('T', ' ', (string) $request->input('ends_at', '')),
            'timezone' => (string) $request->input('timezone', 'Europe/Paris'),
            'location' => (string) $request->input('location', ''),
            'meeting_url' => (string) $request->input('meeting_url', ''),
            'organizer_user_id' => $this->actorId(),
            'max_attendees' => (int) $request->input('max_attendees', 0) ?: null,
            'personal_message' => (string) $request->input('personal_message', ''),
        ], $participants, $this->actorId());
        Session::flash(!empty($res['ok']) ? 'success' : 'error', (string) ($res['message'] ?? (!empty($res['ok']) ? 'Invitation envoyée.' : 'Création impossible.')));

        return Response::redirect(url('back-office/integration-membres/' . $id));
    }

    public function cancel(Request $request, array $params = []): Response
    {
        if (!$this->canManage() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Action impossible.');

            return Response::redirect(url('back-office/integration-membres'));
        }
        $id = (int) ($params['id'] ?? 0);
        $ok = $this->service->cancel($this->tenantId(), $id, $this->actorId(), (string) $request->input('reason', ''));
        Session::flash($ok ? 'success' : 'error', $ok ? 'Parcours annulé.' : 'Indiquez un motif pour annuler.');

        return Response::redirect(url('back-office/integration-membres/' . $id));
    }

    public function reopen(Request $request, array $params = []): Response
    {
        if (!$this->canManage() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Action impossible.');

            return Response::redirect(url('back-office/integration-membres'));
        }
        $id = (int) ($params['id'] ?? 0);
        $ok = $this->service->reopen($this->tenantId(), $id, $this->actorId());
        Session::flash($ok ? 'success' : 'error', $ok ? 'Parcours rouvert.' : 'Impossible de rouvrir (un autre suivi est déjà en cours).');

        return Response::redirect(url('back-office/integration-membres/' . $id));
    }

    public function templates(Request $request, array $params = []): Response
    {
        if (!$this->canTemplates()) {
            return $this->deny();
        }
        $tenantId = $this->tenantId();
        $this->templates->ensureDefaultRecruitTemplate($tenantId, $this->actorId());

        return Response::view('layout.main', [
            'title' => 'Modèles de parcours d’intégration',
            'content' => 'admin.member_integration.templates',
            'templates' => $this->templates->listForTenant($tenantId),
            'canTemplates' => true,
        ]);
    }

    public function templateEdit(Request $request, array $params = []): Response
    {
        if (!$this->canTemplates()) {
            return $this->deny();
        }
        $tenantId = $this->tenantId();
        $id = (int) ($params['id'] ?? 0);
        $tpl = $id > 0 ? $this->templates->findForTenant($tenantId, $id) : null;

        return Response::view('layout.main', [
            'title' => $tpl ? 'Modifier le parcours' : 'Nouveau parcours',
            'boPageTitle' => $tpl ? 'Modifier le parcours' : 'Nouveau modèle de parcours',
            'content' => 'admin.member_integration.template_form',
            'template' => $tpl,
            'steps' => $tpl ? $this->templates->listSteps($tenantId, (int) $tpl['id']) : [],
            'stepTypes' => MemberIntegrationCatalog::stepTypeLabels(),
            'responsible' => MemberIntegrationCatalog::responsibleLabels(),
            'matrices' => $this->matrices->listMatrices($tenantId),
        ]);
    }

    public function templateSave(Request $request, array $params = []): Response
    {
        if (!$this->canTemplates() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Action impossible.');

            return Response::redirect(url('back-office/integration-membres/modeles'));
        }
        $tenantId = $this->tenantId();
        $id = (int) ($params['id'] ?? 0);
        $data = [
            'name' => (string) $request->input('name', ''),
            'description' => (string) $request->input('description', ''),
            'duration_days' => (int) $request->input('duration_days', 30),
            'is_active' => $request->input('is_active') === '1',
            'referent_rule' => (string) $request->input('referent_rule', 'none'),
            'default_referent_user_id' => (int) $request->input('default_referent_user_id', 0),
        ];
        if ($id < 1) {
            $id = $this->templates->create($tenantId, $data, $this->actorId());
        } else {
            $this->templates->update($tenantId, $id, $data, true);
        }
        $rawSteps = $request->input('steps', []);
        $steps = [];
        if (is_array($rawSteps)) {
            foreach ($rawSteps as $s) {
                if (!is_array($s) || trim((string) ($s['title'] ?? '')) === '') {
                    continue;
                }
                $steps[] = $s;
            }
        }
        if ($id > 0 && $steps !== []) {
            $this->templates->replaceSteps($tenantId, $id, $steps);
        }
        Session::flash('success', 'Modèle enregistré. Les suivis déjà commencés conservent leur version.');

        return Response::redirect(url('back-office/integration-membres/modeles'));
    }

    public function backfill(Request $request, array $params = []): Response
    {
        if (!$this->canManage()) {
            return $this->deny();
        }
        $since = trim((string) $request->query('since', date('Y-m-d', strtotime('-30 days'))));
        $preview = $this->automation->previewBackfill($this->tenantId(), $since);

        return Response::view('layout.main', [
            'title' => 'Reprise des arrivées récentes',
            'content' => 'admin.member_integration.backfill',
            'preview' => $preview,
            'since' => $since,
        ]);
    }

    public function backfillRun(Request $request, array $params = []): Response
    {
        if (!$this->canManage() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Action impossible.');

            return Response::redirect(url('back-office/integration-membres/reprise'));
        }
        $since = trim((string) $request->input('since', ''));
        $ids = array_map('intval', (array) $request->input('user_ids', []));
        $out = $this->automation->executeBackfill($this->tenantId(), $this->actorId(), $since !== '' ? $since : null, $ids);
        Session::flash(
            'success',
            'Reprise : ' . $out['created'] . ' créé(s), ' . $out['ignored'] . ' ignoré(s), ' . $out['errors'] . ' erreur(s).'
        );

        return Response::redirect(url('back-office/integration-membres/reprise'));
    }

    public function calendar(Request $request, array $params = []): Response
    {
        if (!$this->canView()) {
            return $this->deny();
        }
        $id = (int) ($params['id'] ?? 0);
        $body = $this->invitations->buildIcs($this->tenantId(), $id);
        if ($body === null) {
            return (new Response())->setStatusCode(404)->setBody('Introuvable.');
        }
        $resp = new Response();
        $resp->header('Content-Type', 'text/calendar; charset=utf-8');
        $resp->header('Content-Disposition', 'attachment; filename="rendez-vous.ics"');
        $resp->setBody($body);

        return $resp;
    }

    public function startManual(Request $request, array $params = []): Response
    {
        if (!$this->canAssign() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Action impossible.');

            return Response::redirect(url('back-office/integration-membres'));
        }
        $userId = (int) $request->input('user_id', 0);
        $res = $this->automation->ensureForNewMember(
            $this->tenantId(),
            $userId,
            $this->actorId(),
            MemberIntegrationCatalog::SOURCE_MANUAL
        );
        Session::flash(
            !empty($res['ok']) ? 'success' : 'error',
            !empty($res['created'])
                ? 'Parcours ouvert.'
                : (string) ($res['message'] ?? 'Aucun nouveau parcours (déjà en cours ou modèle manquant).')
        );

        return Response::redirect(!empty($res['integration_id'])
            ? url('back-office/integration-membres/' . (int) $res['integration_id'])
            : url('back-office/integration-membres'));
    }
}
