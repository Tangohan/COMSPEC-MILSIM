<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\EmailTokenRepository;
use App\Repositories\ForumTopicRepository;
use App\Repositories\InterteamMissionRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;
use App\Services\Email\EmailTokenPurpose;
use App\Services\EmailService;
use App\Services\Cooperation\CooperationAnnouncementDispatcher;
use App\Services\Cooperation\CooperationAnnouncementEvents;
use App\Services\Cooperation\CooperationCatalogService;
use App\Services\Cooperation\CooperationConsentDefaults;
use App\Services\Cooperation\CooperationWorkflowService;
use App\Services\Interteam\InterteamCoopForumService;
use App\Support\CooperationDictionary;

class InterteamMissionWebController
{
    private const OTP_TTL_MIN = 15;
    private const OTP_RESEND_SEC = 60;

    public function __construct(
        private InterteamMissionRepository $interteamRepository,
        private TenantRepository $tenantRepository,
        private ForumTopicRepository $topicRepository,
        private InterteamCoopForumService $coopForumService,
        private UnitRepository $unitRepository,
        private UserRepository $userRepository,
        private EmailService $emailService,
        private EmailTokenRepository $emailTokenRepository,
        private CooperationWorkflowService $cooperationWorkflow,
        private CooperationCatalogService $cooperationCatalogService,
        private CooperationAnnouncementDispatcher $cooperationAnnouncementDispatcher
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId <= 0 || $userId <= 0) {
            Session::flash('error', 'Authentification requise.');

            return Response::redirect(url('login'));
        }
        if (!$this->interteamRepository->tableExists()) {
            Session::flash('error', 'Fonction indisponible sur cette installation.');

            return Response::redirect(url('dashboard'));
        }

        $missions = $this->interteamRepository->listForTenant($tenantId);

        return Response::view('layout.main', [
            'content' => 'back_office.cooperation.missions.index',
            'title' => 'Coopération inter-unités',
            'interteamMissions' => $missions,
            'cooperationKpis' => $this->interteamRepository->cooperationKpisForTenant($tenantId),
            'cooperationActionsRequired' => $this->interteamRepository->cooperationActionsRequiredForTenant($tenantId, $userId),
            'csrfToken' => Csrf::token(),
        ]);
    }

    public function create(Request $request, array $params = []): Response
    {
        if (!$this->assertInterteamAccess($request)) {
            return Response::redirect(url('dashboard'));
        }

        return Response::view('layout.main', [
            'content' => 'back_office.cooperation.missions.create',
            'title' => 'Nouvelle coopération',
            'csrfToken' => Csrf::token(),
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        if (!$this->assertInterteamAccess($request)) {
            return Response::redirect(url('dashboard'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Jeton de sécurité invalide.');

            return Response::redirect(cooperation_mission_create_url());
        }
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        $title = trim((string) $request->input('title', ''));
        if (strlen($title) < 3 || strlen($title) > 255) {
            Session::flash('error', 'Le titre doit faire entre 3 et 255 caractères.');

            return Response::redirect(cooperation_mission_create_url());
        }
        $slug = $this->uniqueSlugFromTitle($title);
        $id = $this->interteamRepository->createMission($title, $slug, $tenantId, $userId);
        $this->interteamRepository->logEvent($id, $userId, $tenantId, 'mission_created', ['title' => $title]);
        $this->cooperationAnnouncementDispatcher->dispatch(CooperationAnnouncementEvents::MISSION_CREATED, $id, $userId, $tenantId, []);
        Session::flash('success', 'Coopération créée. Invitez les unités partenaires, puis validez le lancement lorsque chacune a accepté.');

        return Response::redirect(cooperation_mission_show_url($id));
    }

    public function show(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId <= 0 || $userId <= 0) {
            Session::flash('error', 'Authentification requise.');

            return Response::redirect(url('login'));
        }
        $id = (int) ($params['id'] ?? 0);
        $workspace = $this->buildMissionWorkspace($id, $tenantId, $userId);
        if ($workspace === null) {
            if (!$this->interteamRepository->tableExists()) {
                return Response::redirect(url('dashboard'));
            }
            Session::flash('error', 'Coopération introuvable ou accès refusé.');

            return Response::redirect(cooperation_mission_index_url());
        }

        return Response::view('layout.main', array_merge($workspace, [
            'content' => 'back_office.cooperation.missions.show',
            'title' => (string) ($workspace['interteamMission']['title'] ?? 'Coopération'),
            'cooperationMissionNavActive' => 'overview',
            'csrfToken' => Csrf::token(),
        ]));
    }

    public function edit(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId <= 0 || $userId <= 0) {
            return Response::redirect(url('login'));
        }
        $id = (int) ($params['id'] ?? 0);
        $workspace = $this->buildMissionWorkspace($id, $tenantId, $userId);
        if ($workspace === null) {
            Session::flash('error', 'Coopération introuvable ou accès refusé.');

            return Response::redirect(cooperation_mission_index_url());
        }
        if (!$workspace['interteamCanPilot']) {
            Session::flash('error', 'Action réservée aux unités habilitées à piloter cette coopération.');

            return Response::redirect(cooperation_mission_show_url($id));
        }

        $leadForCatalog = (int) ($workspace['interteamMission']['created_by_tenant_id'] ?? $tenantId);
        $typologyChoices = $this->cooperationCatalogService->typologyChoicesForTenant($leadForCatalog);
        $curTypo = trim((string) ($workspace['interteamMission']['cooperation_typology'] ?? ''));
        if ($curTypo !== '' && !isset($typologyChoices[$curTypo])) {
            $typologyChoices[$curTypo] = $this->cooperationCatalogService->typologyLabelForTenant($leadForCatalog, $curTypo);
        }

        return Response::view('layout.main', array_merge($workspace, [
            'content' => 'back_office.cooperation.missions.edit',
            'title' => 'Proposition — ' . (string) ($workspace['interteamMission']['title'] ?? ''),
            'cooperationMissionNavActive' => 'edit',
            'cooperationTypologyChoices' => $typologyChoices,
            'cooperationPriorityChoices' => CooperationDictionary::priorityChoices(),
            'interteamProposalFieldsEnabled' => $this->interteamRepository->columnExists('interteam_missions', 'cooperation_typology'),
            'csrfToken' => Csrf::token(),
        ]));
    }

    public function exchange(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId <= 0 || $userId <= 0) {
            return Response::redirect(url('login'));
        }
        $id = (int) ($params['id'] ?? 0);
        $workspace = $this->buildMissionWorkspace($id, $tenantId, $userId);
        if ($workspace === null) {
            Session::flash('error', 'Coopération introuvable ou accès refusé.');

            return Response::redirect(cooperation_mission_index_url());
        }

        return Response::view('layout.main', array_merge($workspace, [
            'content' => 'back_office.cooperation.missions.exchange',
            'title' => 'Espace commun — ' . (string) ($workspace['interteamMission']['title'] ?? ''),
            'cooperationMissionNavActive' => 'exchange',
            'csrfToken' => Csrf::token(),
        ]));
    }

    public function exportTimelineCsv(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId <= 0 || $userId <= 0) {
            return Response::redirect(url('login'));
        }
        $id = (int) ($params['id'] ?? 0);
        if ($this->buildMissionWorkspace($id, $tenantId, $userId) === null) {
            return Response::redirect(cooperation_mission_index_url());
        }
        $canExport = $this->interteamRepository->tenantCanPilotMission($id, $tenantId)
            || (function_exists('can') && can('cooperation.audit.view'));
        if (!$canExport) {
            Session::flash('error', 'Cet export est réservé au pilotage ou à l’audit de la coopération.');

            return Response::redirect(cooperation_mission_timeline_url($id));
        }
        $events = $this->interteamRepository->listEventsPaginated($id, 1, 800);
        $rows = [];
        $rows[] = 'date;acteur;evenement';
        foreach ($events as $ev) {
            $rows[] = implode(';', [
                str_replace(';', ',', (string) ($ev['created_at'] ?? '')),
                str_replace(';', ',', (string) ($ev['actor_display_name'] ?? '')),
                str_replace(';', ',', CooperationDictionary::eventTypeLabel((string) ($ev['event_type'] ?? ''))),
            ]);
        }
        $csv = implode("\n", $rows) . "\n";
        $out = new Response();
        $out->header('Content-Type', 'text/csv; charset=utf-8');
        $out->header('Content-Disposition', 'attachment; filename="cooperation-' . $id . '-journal.csv"');
        $out->setBody($csv);

        return $out;
    }

    public function timeline(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId <= 0 || $userId <= 0) {
            return Response::redirect(url('login'));
        }
        $id = (int) ($params['id'] ?? 0);
        $workspace = $this->buildMissionWorkspace($id, $tenantId, $userId);
        if ($workspace === null) {
            Session::flash('error', 'Coopération introuvable ou accès refusé.');

            return Response::redirect(cooperation_mission_index_url());
        }
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 30;
        $total = $this->interteamRepository->countEvents($id);
        $workspace['interteamEvents'] = $this->interteamRepository->listEventsPaginated($id, $page, $perPage);
        $workspace['cooperationTimelinePage'] = $page;
        $workspace['cooperationTimelinePerPage'] = $perPage;
        $workspace['cooperationTimelineTotal'] = $total;

        return Response::view('layout.main', array_merge($workspace, [
            'content' => 'back_office.cooperation.missions.timeline',
            'title' => 'Chronologie — ' . (string) ($workspace['interteamMission']['title'] ?? ''),
            'cooperationMissionNavActive' => 'timeline',
            'csrfToken' => Csrf::token(),
        ]));
    }

    public function meeting(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId <= 0 || $userId <= 0) {
            return Response::redirect(url('login'));
        }
        $id = (int) ($params['id'] ?? 0);
        $workspace = $this->buildMissionWorkspace($id, $tenantId, $userId);
        if ($workspace === null) {
            Session::flash('error', 'Coopération introuvable ou accès refusé.');

            return Response::redirect(cooperation_mission_index_url());
        }
        $workspace['interteamMeetings'] = $this->interteamRepository->listMeetings($id, 20);

        return Response::view('layout.main', array_merge($workspace, [
            'content' => 'back_office.cooperation.missions.meeting',
            'title' => 'Réunion — ' . (string) ($workspace['interteamMission']['title'] ?? ''),
            'cooperationMissionNavActive' => 'meeting',
            'csrfToken' => Csrf::token(),
        ]));
    }

    public function orbat(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId <= 0 || $userId <= 0) {
            return Response::redirect(url('login'));
        }
        $id = (int) ($params['id'] ?? 0);
        $workspace = $this->buildMissionWorkspace($id, $tenantId, $userId);
        if ($workspace === null) {
            Session::flash('error', 'Coopération introuvable ou accès refusé.');

            return Response::redirect(cooperation_mission_index_url());
        }

        return Response::view('layout.main', array_merge($workspace, [
            'content' => 'back_office.cooperation.missions.orbat',
            'title' => 'Structures & liaisons — ' . (string) ($workspace['interteamMission']['title'] ?? ''),
            'cooperationMissionNavActive' => 'orbat',
            'csrfToken' => Csrf::token(),
        ]));
    }

    public function archive(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId <= 0 || $userId <= 0) {
            return Response::redirect(url('login'));
        }
        $id = (int) ($params['id'] ?? 0);
        $workspace = $this->buildMissionWorkspace($id, $tenantId, $userId);
        if ($workspace === null) {
            Session::flash('error', 'Coopération introuvable ou accès refusé.');

            return Response::redirect(cooperation_mission_index_url());
        }

        return Response::view('layout.main', array_merge($workspace, [
            'content' => 'back_office.cooperation.missions.archive',
            'title' => 'Clôture — ' . (string) ($workspace['interteamMission']['title'] ?? ''),
            'cooperationMissionNavActive' => 'archive',
            'csrfToken' => Csrf::token(),
        ]));
    }

    public function negotiate(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId <= 0 || $userId <= 0) {
            return Response::redirect(url('login'));
        }
        $id = (int) ($params['id'] ?? 0);
        $workspace = $this->buildMissionWorkspace($id, $tenantId, $userId);
        if ($workspace === null) {
            Session::flash('error', 'Coopération introuvable ou accès refusé.');

            return Response::redirect(cooperation_mission_index_url());
        }

        return Response::view('layout.main', array_merge($workspace, [
            'content' => 'back_office.cooperation.missions.negotiate',
            'title' => 'Négociation — ' . (string) ($workspace['interteamMission']['title'] ?? ''),
            'cooperationMissionNavActive' => 'negotiate',
            'csrfToken' => Csrf::token(),
        ]));
    }

    public function rex(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId <= 0 || $userId <= 0) {
            return Response::redirect(url('login'));
        }
        $id = (int) ($params['id'] ?? 0);
        $workspace = $this->buildMissionWorkspace($id, $tenantId, $userId);
        if ($workspace === null) {
            Session::flash('error', 'Coopération introuvable ou accès refusé.');

            return Response::redirect(cooperation_mission_index_url());
        }

        return Response::view('layout.main', array_merge($workspace, [
            'content' => 'back_office.cooperation.missions.rex',
            'title' => 'Retour d’expérience — ' . (string) ($workspace['interteamMission']['title'] ?? ''),
            'cooperationMissionNavActive' => 'rex',
            'csrfToken' => Csrf::token(),
        ]));
    }

    public function submitCounterProposal(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Jeton de sécurité invalide.');
            $id = (int) ($params['id'] ?? 0);

            return Response::redirect($id > 0 ? cooperation_mission_negotiate_url($id) : cooperation_mission_index_url());
        }
        $id = (int) ($params['id'] ?? 0);
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if (!$this->canRespondInterteam() || !$this->interteamRepository->partnerCanProposeCounter($id, $tenantId)) {
            Session::flash('error', 'Vous ne pouvez pas transmettre de contre-proposition dans l’état actuel.');

            return Response::redirect(cooperation_mission_show_url($id));
        }
        $parts = [
            'calendar' => substr(trim((string) $request->input('cp_calendar', '')), 0, 2000),
            'support_unit' => substr(trim((string) $request->input('cp_support_unit', '')), 0, 2000),
            'scope' => substr(trim((string) $request->input('cp_scope', '')), 0, 2000),
            'sharing' => substr(trim((string) $request->input('cp_sharing', '')), 0, 2000),
            'coordination' => substr(trim((string) $request->input('cp_coordination', '')), 0, 2000),
            'conditions' => substr(trim((string) $request->input('cp_conditions', '')), 0, 2000),
        ];
        $nonEmpty = array_filter($parts, static fn (string $v): bool => $v !== '');
        if ($nonEmpty === []) {
            Session::flash('error', 'Renseignez au moins un champ pour décrire votre contre-proposition.');

            return Response::redirect(cooperation_mission_negotiate_url($id));
        }
        $this->interteamRepository->saveCounterProposal($id, $tenantId, $userId, $parts);
        Session::flash('success', 'Contre-proposition transmise à l’unité support.');

        return Response::redirect(cooperation_mission_negotiate_url($id));
    }

    public function respondCounterProposal(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Jeton de sécurité invalide.');
            $id = (int) ($params['id'] ?? 0);

            return Response::redirect($id > 0 ? cooperation_mission_negotiate_url($id) : cooperation_mission_index_url());
        }
        $id = (int) ($params['id'] ?? 0);
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if (!$this->interteamRepository->tenantCanPilotMission($id, $tenantId) || !$this->canManageInterteam()) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(cooperation_mission_show_url($id));
        }
        if (!$this->interteamRepository->counterProposalPending($id)) {
            Session::flash('error', 'Aucune contre-proposition en attente.');

            return Response::redirect(cooperation_mission_negotiate_url($id));
        }
        $decision = (string) $request->input('decision', '');
        if ($decision === 'accept') {
            $this->interteamRepository->integrateCounterProposal($id, $tenantId, $userId);
            Session::flash('success', 'Contre-proposition prise en compte. Vous pouvez poursuivre la validation avec les unités partenaires.');
        } elseif ($decision === 'decline') {
            $this->interteamRepository->declineCounterProposal($id, $tenantId, $userId);
            Session::flash('success', 'Contre-proposition refusée. L’unité partenaire peut vous en adresser une nouvelle.');
        } else {
            Session::flash('error', 'Choix invalide.');

            return Response::redirect(cooperation_mission_negotiate_url($id));
        }

        return Response::redirect(cooperation_mission_negotiate_url($id));
    }

    public function scheduleMeeting(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Jeton de sécurité invalide.');
            $id = (int) ($params['id'] ?? 0);

            return Response::redirect($id > 0 ? cooperation_mission_meeting_url($id) : cooperation_mission_index_url());
        }
        $id = (int) ($params['id'] ?? 0);
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if (!$this->interteamRepository->tenantCanPilotMission($id, $tenantId) || !$this->canManageInterteam()) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(cooperation_mission_index_url());
        }
        $title = substr(trim((string) $request->input('meeting_title', '')), 0, 255);
        $agenda = trim((string) $request->input('meeting_agenda', ''));
        if (strlen($agenda) > 20000) {
            $agenda = substr($agenda, 0, 20000);
        }
        $schedRaw = trim((string) $request->input('scheduled_at', ''));
        $sched = null;
        if ($schedRaw !== '') {
            $ts = strtotime($schedRaw);
            if ($ts !== false) {
                $sched = date('Y-m-d H:i:s', $ts);
            }
        }
        $expPart = trim((string) $request->input('expected_participants_note', ''));
        if (strlen($expPart) > 2000) {
            $expPart = substr($expPart, 0, 2000);
        }
        $mid = $this->interteamRepository->createMeeting(
            $id,
            $userId,
            $title !== '' ? $title : null,
            $agenda !== '' ? $agenda : null,
            $sched,
            $expPart !== '' ? $expPart : null
        );
        if ($mid > 0) {
            $this->interteamRepository->logEvent($id, $userId, $tenantId, 'meeting_scheduled', ['meeting_row_id' => $mid]);
        }
        Session::flash('success', 'Réunion ajoutée au journal.');

        return Response::redirect(cooperation_mission_meeting_url($id));
    }

    public function saveRex(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Jeton de sécurité invalide.');
            $id = (int) ($params['id'] ?? 0);

            return Response::redirect($id > 0 ? cooperation_mission_rex_url($id) : cooperation_mission_index_url());
        }
        $id = (int) ($params['id'] ?? 0);
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        $mission = $this->interteamRepository->findById($id);
        if (!$mission || ($mission['status'] ?? '') !== 'archived' || !$this->tenantMayContributeRex($id, $tenantId)) {
            Session::flash('error', 'Le retour d’expérience n’est pas disponible pour votre unité dans l’état actuel.');

            return Response::redirect($id > 0 ? cooperation_mission_show_url($id) : cooperation_mission_index_url());
        }
        $this->interteamRepository->upsertRex($id, $tenantId, $userId, [
            'worked_well' => $request->input('rex_worked_well', ''),
            'failed_aspects' => $request->input('rex_failed', ''),
            'coordination_incidents' => $request->input('rex_coordination', ''),
            'sharing_difficulties' => $request->input('rex_sharing', ''),
            'technical_difficulties' => $request->input('rex_technical', ''),
            'recommendations' => $request->input('rex_recommendations', ''),
            'rating_fluidity' => $request->input('rating_fluidity', ''),
            'rating_security' => $request->input('rating_security', ''),
            'rating_usefulness' => $request->input('rating_usefulness', ''),
            'rating_reactivity' => $request->input('rating_reactivity', ''),
        ]);
        $this->interteamRepository->logEvent($id, $userId, $tenantId, 'rex_submitted', []);
        Session::flash('success', 'Retour d’expérience enregistré pour votre unité.');

        return Response::redirect(cooperation_mission_rex_url($id));
    }

    public function saveProposal(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Jeton de sécurité invalide.');
            $id = (int) ($params['id'] ?? 0);

            return Response::redirect($id > 0 ? cooperation_mission_edit_url($id) : cooperation_mission_index_url());
        }
        $id = (int) ($params['id'] ?? 0);
        $tenantId = (int) Session::get('tenant_id');
        if (!$this->interteamRepository->tenantCanPilotMission($id, $tenantId) || !$this->canManageInterteam()) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(cooperation_mission_index_url());
        }
        $missionRow = $this->interteamRepository->findById($id);
        if (!$missionRow) {
            Session::flash('error', 'Coopération introuvable.');

            return Response::redirect(cooperation_mission_index_url());
        }
        $leadCatalogTid = (int) ($missionRow['created_by_tenant_id'] ?? $tenantId);
        $title = trim((string) $request->input('title', ''));
        if (strlen($title) < 3 || strlen($title) > 255) {
            Session::flash('error', 'Le titre doit faire entre 3 et 255 caractères.');

            return Response::redirect(cooperation_mission_edit_url($id));
        }
        $typRaw = (string) $request->input('cooperation_typology', '');
        $typology = $this->cooperationCatalogService->normalizeTypologyForTenant($typRaw, $leadCatalogTid);
        if ($typRaw !== '' && $typology === null) {
            Session::flash('error', 'La typologie indiquée ne correspond plus à un type disponible. Choisissez une valeur dans la liste ou laissez le champ vide.');

            return Response::redirect(cooperation_mission_edit_url($id));
        }
        $priority = CooperationDictionary::normalizePriority((string) $request->input('cooperation_priority', ''));
        $deadlineRaw = trim((string) $request->input('proposal_deadline_at', ''));
        $deadline = null;
        if ($deadlineRaw !== '') {
            $ts = strtotime($deadlineRaw);
            if ($ts !== false) {
                $deadline = date('Y-m-d H:i:s', $ts);
            }
        }
        $fields = [
            'title' => $title,
            'cooperation_typology' => $typology,
            'cooperation_priority' => $priority,
            'proposal_deadline_at' => $deadline,
        ];
        if ($this->interteamRepository->columnExists('interteam_missions', 'suspensive_conditions_json')) {
            $suspRaw = (string) $request->input('suspensive_conditions', '');
            $lines = CooperationWorkflowService::parseSuspensiveConditionsFromText($suspRaw);
            $fields['suspensive_conditions_json'] = json_encode($lines, JSON_UNESCAPED_UNICODE);
        }
        if (!$this->interteamRepository->columnExists('interteam_missions', 'cooperation_typology')) {
            unset($fields['cooperation_typology'], $fields['cooperation_priority'], $fields['proposal_deadline_at']);
        }
        $this->interteamRepository->updateMissionProposalMeta($id, $fields);
        $this->interteamRepository->logEvent($id, (int) Session::get('user_id'), $tenantId, 'mission_proposal_updated', []);
        $this->cooperationAnnouncementDispatcher->dispatch(
            CooperationAnnouncementEvents::PROPOSAL_UPDATED,
            $id,
            (int) Session::get('user_id'),
            $tenantId,
            []
        );
        Session::flash('success', 'Proposition mise à jour.');

        return Response::redirect(cooperation_mission_edit_url($id));
    }

    /**
     * Données communes aux vues d’une coopération (accès réservé aux unités engagées).
     *
     * @return array<string, mixed>|null
     */
    private function buildMissionWorkspace(int $missionId, int $tenantId, int $userId): ?array
    {
        if (!$this->interteamRepository->tableExists() || $missionId <= 0) {
            return null;
        }
        $mission = $this->interteamRepository->findById($missionId);
        if (!$mission) {
            return null;
        }
        $this->interteamRepository->recordProposalDeadlineElapsedIfNeeded($missionId);
        $mission = $this->interteamRepository->findById($missionId);
        if (!$mission) {
            return null;
        }
        $participants = $this->interteamRepository->listParticipants($missionId);
        $inMission = false;
        foreach ($participants as $p) {
            if ((int) ($p['tenant_id'] ?? 0) === $tenantId) {
                $inMission = true;
                break;
            }
        }
        if (!$inMission) {
            return null;
        }

        $isForumHost = $this->interteamRepository->tenantIsForumHost($missionId, $tenantId);
        $canPilot = $this->interteamRepository->tenantCanPilotMission($missionId, $tenantId);
        $canManage = $canPilot && $this->canManageInterteam();
        $canRespond = $this->canRespondInterteam();
        $partnerPicker = $canPilot ? $this->tenantRepository->listBasicExcluding($tenantId) : [];
        $status = (string) ($mission['status'] ?? '');
        $grants = ($status === 'active')
            ? $this->interteamRepository->listGrantsForMission($missionId)
            : [];
        $topicChoices = ($isForumHost && $canManage && $status === 'active')
            ? $this->topicRepository->listRecentTitlesForTenant($tenantId, 100)
            : [];
        $events = $this->interteamRepository->listEvents($missionId, 60);
        $meetings = $this->interteamRepository->listMeetings($missionId, 10);
        $orbatBlocks = $this->buildOrbatBlocks($participants);
        $jitsiRoom = $this->jitsiRoomName($missionId);
        $jitsiDomain = trim((string) env('JITSI_DOMAIN', 'meet.jit.si'));
        $jitsiEnabled = $jitsiDomain !== '' && filter_var((string) env('JITSI_COOP_ENABLED', '1'), FILTER_VALIDATE_BOOLEAN);
        $consentDone = !$this->interteamRepository->consentsTableExists()
            || $this->interteamRepository->hasVerifiedConsent($missionId, $userId);
        $coopTopicId = (int) ($mission['coop_forum_topic_id'] ?? 0);
        $missionSlug = (string) ($mission['slug'] ?? '');
        $coopTopicUrl = ($coopTopicId > 0 && $missionSlug !== '')
            ? url('forum/coop/' . rawurlencode($missionSlug) . '/sujet/' . $coopTopicId)
            : '';

        $partnerCanCounter = $this->interteamRepository->partnerCanProposeCounter($missionId, $tenantId);
        $counterPending = $this->interteamRepository->counterProposalPending($missionId);
        $interteamRexRow = $this->interteamRepository->findRexForTenant($missionId, $tenantId);
        $interteamRexList = [];
        $canReadConsolidatedRex = ($mission['status'] ?? '') === 'archived'
            && ($this->interteamRepository->tenantCanPilotMission($missionId, $tenantId)
                || (function_exists('can') && can('cooperation.rex.read')));
        if ($canReadConsolidatedRex) {
            $interteamRexList = $this->interteamRepository->listRexForMission($missionId);
        }

        $typoKeyForLabel = trim((string) ($mission['cooperation_typology'] ?? ''));
        $leadForTypo = (int) ($mission['created_by_tenant_id'] ?? 0);
        $interteamCooperationTypologyLabel = '';
        if ($typoKeyForLabel !== '') {
            $interteamCooperationTypologyLabel = $this->cooperationCatalogService->typologyLabelForTenant(
                $leadForTypo > 0 ? $leadForTypo : $tenantId,
                $typoKeyForLabel
            );
        }
        $operationalStage = trim((string) ($mission['operational_stage'] ?? 'opord_draft'));
        if ($operationalStage === '') {
            $operationalStage = 'opord_draft';
        }

        return [
            'interteamMission' => $mission,
            'interteamParticipants' => $participants,
            'interteamGrants' => $grants,
            'interteamIsLead' => $isForumHost,
            'interteamCanManage' => $canManage,
            'interteamCanPilot' => $canPilot,
            'interteamCanRespond' => $canRespond,
            'interteamPartnerPicker' => $partnerPicker,
            'interteamTopicChoices' => $topicChoices,
            'interteamEvents' => $events,
            'interteamMeetings' => $meetings,
            'interteamOrbatBlocks' => $orbatBlocks,
            'interteamJitsiRoom' => $jitsiRoom,
            'interteamJitsiDomain' => $jitsiDomain,
            'interteamJitsiEnabled' => $jitsiEnabled,
            'interteamConsentDone' => $consentDone,
            'interteamCoopTopicUrl' => $coopTopicUrl,
            'trainingCompetencyCommandUrl' => url('back-office/ressources/training/competences/commandement'),
            'sessionTenantId' => $tenantId,
            'interteamPartnerCanCounter' => $partnerCanCounter,
            'interteamCounterPending' => $counterPending,
            'interteamRexRow' => $interteamRexRow,
            'interteamRexList' => $interteamRexList,
            'interteamCanReadConsolidatedRex' => $canReadConsolidatedRex,
            'interteamMissionMembers' => $this->interteamRepository->missionMembersTableExists()
                ? $this->interteamRepository->listMissionMembers($missionId) : [],
            'cooperationRoleUserPicker' => ($canManage && $canPilot && $this->interteamRepository->missionMembersTableExists())
                ? $this->userRepository->listForTenant($tenantId, null, null, null, 120) : [],
            'cooperationActivationSnapshot' => $this->decodeActivationSnapshot($mission),
            'interteamCooperationTypologyLabel' => $interteamCooperationTypologyLabel,
            'interteamOperationalStageChoices' => $this->operationalStageChoices(),
            'interteamOperationalStage' => $operationalStage,
            'interteamSitreps' => $this->interteamRepository->listSitreps($missionId, 40),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function operationalStageChoices(): array
    {
        return [
            'opord_draft' => '1) Brouillon OPORD',
            'command_validation' => '2) Validation commandement',
            'execution' => '3) Exécution (SITREP)',
            'closed_aar' => '4) Clôture + AAR',
            'corrective_actions' => '5) Actions correctives',
        ];
    }

    private function operationalStageErrorLabel(string $error): string
    {
        return match ($error) {
            'workflow_unavailable' => 'Workflow opérationnel non disponible (migration manquante).',
            'invalid_stage' => 'Statut opérationnel invalide.',
            'mission_not_found' => 'Mission introuvable.',
            'backward_transition_forbidden' => 'Retour arrière interdit par la doctrine.',
            'jump_transition_forbidden' => 'Transition impossible : respectez l’ordre des étapes.',
            'opord_required' => 'OPORD requis avant validation commandement.',
            'mission_must_be_active' => 'La mission doit être active pour passer en exécution.',
            'aar_required' => 'AAR requis pour clôturer la mission.',
            default => 'Impossible de mettre à jour le statut opérationnel.',
        };
    }

    private function normalizeDateTimeInput(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $normalized = str_replace('T', ' ', $value);
        $dt = date_create($normalized);
        if (!$dt) {
            return null;
        }

        return $dt->format('Y-m-d H:i:s');
    }

    /**
     * @return string|null
     */
    private function normalizeJsonOrNull(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $value;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeJsonOrArray(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $mission
     * @return array<string, mixed>|null
     */
    private function decodeActivationSnapshot(array $mission): ?array
    {
        $raw = $mission['activation_snapshot_json'] ?? null;
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }
        $d = json_decode($raw, true);

        return is_array($d) ? $d : null;
    }

    private function tenantMayContributeRex(int $missionId, int $tenantId): bool
    {
        if ($missionId <= 0 || $tenantId <= 0) {
            return false;
        }
        $parts = $this->interteamRepository->listParticipants($missionId);
        foreach ($parts as $p) {
            if ((int) ($p['tenant_id'] ?? 0) === $tenantId && ($p['status'] ?? '') === 'active') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array<string, mixed>> $participants
     * @return list<array{tenant_name: string, units: list<array<string, mixed>>}>
     */
    private function buildOrbatBlocks(array $participants): array
    {
        $out = [];
        foreach ($participants as $p) {
            if (($p['status'] ?? '') !== 'active') {
                continue;
            }
            $tid = (int) ($p['tenant_id'] ?? 0);
            if ($tid <= 0) {
                continue;
            }
            $name = (string) ($p['tenant_name'] ?? 'Unité');
            $units = $this->unitRepository->listPublicForTenant($tid);
            if ($units === []) {
                $units = $this->unitRepository->allForTenant($tid);
            }
            $out[] = ['tenant_name' => $name, 'units' => array_slice($units, 0, 80)];
        }

        return $out;
    }

    private function jitsiRoomName(int $missionId): string
    {
        $secret = (string) env('APP_KEY', 'comspec');

        return 'comspec-coop-' . substr(hash_hmac('sha256', (string) $missionId, $secret), 0, 24);
    }

    public function invite(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Jeton de sécurité invalide.');
            $id = (int) ($params['id'] ?? 0);

            return Response::redirect($id > 0 ? cooperation_mission_show_url($id) : cooperation_mission_index_url());
        }
        $id = (int) ($params['id'] ?? 0);
        $tenantId = (int) Session::get('tenant_id');
        if (!$this->interteamRepository->tenantCanPilotMission($id, $tenantId) || !$this->canManageInterteam()) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(cooperation_mission_index_url());
        }
        $partnerId = (int) $request->input('partner_tenant_id', 0);
        if ($partnerId <= 0 || $partnerId === $tenantId) {
            Session::flash('error', 'Unité partenaire invalide.');

            return Response::redirect(cooperation_mission_show_url($id));
        }
        $this->interteamRepository->invitePartner($id, $partnerId);
        $this->interteamRepository->markProposalSentIfDraft($id);
        $this->interteamRepository->logEvent($id, (int) Session::get('user_id'), $tenantId, 'partner_invited', ['partner_tenant_id' => $partnerId]);
        $this->cooperationAnnouncementDispatcher->dispatch(
            CooperationAnnouncementEvents::INVITATION_SENT,
            $id,
            (int) Session::get('user_id'),
            $tenantId,
            ['invited_tenant_id' => $partnerId]
        );
        Session::flash('success', 'Invitation enregistrée. L’autre unité peut accepter depuis son back-office.');

        return Response::redirect(cooperation_mission_show_url($id));
    }

    public function accept(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Jeton de sécurité invalide.');
            $id = (int) ($params['id'] ?? 0);

            return Response::redirect($id > 0 ? cooperation_mission_show_url($id) : cooperation_mission_index_url());
        }
        $id = (int) ($params['id'] ?? 0);
        $tenantId = (int) Session::get('tenant_id');
        if (!$this->canRespondInterteam()) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(cooperation_mission_show_url($id));
        }
        $this->interteamRepository->setParticipantStatus($id, $tenantId, 'active');
        $this->interteamRepository->logEvent($id, (int) Session::get('user_id'), $tenantId, 'partner_accepted', []);
        $this->cooperationAnnouncementDispatcher->dispatch(
            CooperationAnnouncementEvents::PARTNER_ACCEPTED,
            $id,
            (int) Session::get('user_id'),
            $tenantId,
            []
        );
        Session::flash('success', 'Participation confirmée.');

        return Response::redirect(cooperation_mission_show_url($id));
    }

    public function decline(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Jeton de sécurité invalide.');
            $id = (int) ($params['id'] ?? 0);

            return Response::redirect($id > 0 ? cooperation_mission_show_url($id) : cooperation_mission_index_url());
        }
        $id = (int) ($params['id'] ?? 0);
        $tenantId = (int) Session::get('tenant_id');
        if (!$this->canRespondInterteam()) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(cooperation_mission_show_url($id));
        }
        $this->interteamRepository->setParticipantStatus($id, $tenantId, 'declined');
        $this->interteamRepository->logEvent($id, (int) Session::get('user_id'), $tenantId, 'partner_declined', []);
        $this->cooperationAnnouncementDispatcher->dispatch(
            CooperationAnnouncementEvents::PARTNER_DECLINED,
            $id,
            (int) Session::get('user_id'),
            $tenantId,
            []
        );
        Session::flash('success', 'Invitation refusée.');

        return Response::redirect(cooperation_mission_index_url());
    }

    public function activate(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Jeton de sécurité invalide.');
            $id = (int) ($params['id'] ?? 0);

            return Response::redirect($id > 0 ? cooperation_mission_show_url($id) : cooperation_mission_index_url());
        }
        $id = (int) ($params['id'] ?? 0);
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if (!$this->interteamRepository->tenantCanPilotMission($id, $tenantId) || !$this->canManageInterteam()) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(cooperation_mission_index_url());
        }
        if (!$this->interteamRepository->hasPartnerInvited($id)) {
            Session::flash('error', 'Ajoutez au moins une unité partenaire avant de lancer la coopération.');

            return Response::redirect(cooperation_mission_show_url($id));
        }
        if ($this->interteamRepository->counterProposalPending($id)) {
            Session::flash('error', 'Une contre-proposition est en attente de votre réponse. Traitez-la dans l’onglet Négociation avant de lancer la coopération.');

            return Response::redirect(cooperation_mission_negotiate_url($id));
        }
        if (!$this->interteamRepository->allPartnersAccepted($id)) {
            Session::flash('error', 'Toutes les unités invitées doivent d’abord accepter.');

            return Response::redirect(cooperation_mission_show_url($id));
        }
        $this->interteamRepository->updateMissionStatus($id, 'active');
        $missionRow = $this->interteamRepository->findById($id);
        if ($missionRow) {
            $hostTid = (int) ($missionRow['created_by_tenant_id'] ?? 0);
            $snap = $this->cooperationWorkflow->buildActivationSnapshot($missionRow, $hostTid);
            $this->cooperationWorkflow->persistActivationSnapshot($id, $snap);
        }
        $this->interteamRepository->logEvent($id, $userId, $tenantId, 'mission_activated', []);
        $this->coopForumService->ensureCooperativeSpace($id);
        $this->cooperationAnnouncementDispatcher->dispatch(CooperationAnnouncementEvents::MISSION_ACTIVATED, $id, $userId, $tenantId, []);
        Session::flash('success', 'La coopération est en cours. Un fil commun a été préparé sur le brief de l’unité hôte ; les partages complémentaires restent possibles.');

        return Response::redirect(cooperation_mission_show_url($id));
    }

    public function updateOperationalStage(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Jeton de sécurité invalide.');
            $id = (int) ($params['id'] ?? 0);

            return Response::redirect($id > 0 ? cooperation_mission_show_url($id) : cooperation_mission_index_url());
        }
        $id = (int) ($params['id'] ?? 0);
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if (!$this->interteamRepository->tenantCanPilotMission($id, $tenantId) || !$this->canManageInterteam()) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(cooperation_mission_show_url($id));
        }
        $stage = trim((string) $request->input('operational_stage', ''));
        $opord = trim((string) $request->input('opord_text', ''));
        $aar = trim((string) $request->input('aar_summary', ''));
        $validationNotes = trim((string) $request->input('command_validation_notes', ''));
        $corrective = trim((string) $request->input('corrective_actions_json', ''));
        $resources = trim((string) $request->input('linked_resources_json', ''));
        $losses = trim((string) $request->input('simulated_losses_json', ''));
        $lessons = trim((string) $request->input('lessons_learned_json', ''));

        $fields = [
            'opord_text' => $opord !== '' ? $opord : null,
            'aar_summary' => $aar !== '' ? $aar : null,
            'command_validation_notes' => $validationNotes !== '' ? $validationNotes : null,
            'corrective_actions_json' => $this->normalizeJsonOrNull($corrective),
            'linked_resources_json' => $this->normalizeJsonOrNull($resources),
            'simulated_losses_json' => $this->normalizeJsonOrNull($losses),
            'lessons_learned_json' => $this->normalizeJsonOrNull($lessons),
        ];

        $result = $this->interteamRepository->updateOperationalStage($id, $stage, $fields);
        if (!($result['ok'] ?? false)) {
            Session::flash('error', $this->operationalStageErrorLabel((string) ($result['error'] ?? '')));

            return Response::redirect(cooperation_mission_show_url($id));
        }
        $this->interteamRepository->logEvent($id, $userId, $tenantId, 'operational_stage_updated', ['operational_stage' => $stage]);
        Session::flash('success', 'Statut opérationnel mis à jour.');

        return Response::redirect(cooperation_mission_show_url($id));
    }

    public function addSitrep(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Jeton de sécurité invalide.');
            $id = (int) ($params['id'] ?? 0);

            return Response::redirect($id > 0 ? cooperation_mission_show_url($id) : cooperation_mission_index_url());
        }
        $id = (int) ($params['id'] ?? 0);
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if (!$this->interteamRepository->tenantCanPilotMission($id, $tenantId) || !$this->canManageInterteam()) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(cooperation_mission_show_url($id));
        }
        $mission = $this->interteamRepository->findById($id);
        if (!$mission || (string) ($mission['operational_stage'] ?? '') !== 'execution') {
            Session::flash('error', 'Les SITREP sont ouverts pendant la phase d’exécution.');

            return Response::redirect(cooperation_mission_show_url($id));
        }
        $summary = trim((string) $request->input('sitrep_summary', ''));
        if ($summary === '') {
            Session::flash('error', 'Le contenu SITREP est obligatoire.');

            return Response::redirect(cooperation_mission_show_url($id));
        }
        $occurredAt = trim((string) $request->input('sitrep_occurred_at', ''));
        $occurredAt = $this->normalizeDateTimeInput($occurredAt);
        $payload = $this->normalizeJsonOrArray($request->input('sitrep_payload_json', ''));
        $ok = $this->interteamRepository->createSitrep($id, $userId, $tenantId, $summary, $occurredAt, $payload);
        if (!$ok) {
            Session::flash('error', 'Impossible d’enregistrer le SITREP.');

            return Response::redirect(cooperation_mission_show_url($id));
        }
        $this->interteamRepository->logEvent($id, $userId, $tenantId, 'sitrep_logged', ['summary' => mb_substr($summary, 0, 220)]);
        Session::flash('success', 'SITREP enregistré.');

        return Response::redirect(cooperation_mission_show_url($id));
    }

    public function grantTopic(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Jeton de sécurité invalide.');
            $id = (int) ($params['id'] ?? 0);

            return Response::redirect($id > 0 ? cooperation_mission_show_url($id) : cooperation_mission_index_url());
        }
        $id = (int) ($params['id'] ?? 0);
        $tenantId = (int) Session::get('tenant_id');
        $mission = $this->interteamRepository->findById($id);
        if (!$mission || ($mission['status'] ?? '') !== 'active'
            || !$this->interteamRepository->tenantIsForumHost($id, $tenantId) || !$this->canManageInterteam()) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(cooperation_mission_show_url($id));
        }
        $topicId = (int) $request->input('topic_id', 0);
        $consumerId = (int) $request->input('consumer_tenant_id', 0);
        if ($topicId <= 0 || $consumerId <= 0 || $consumerId === $tenantId) {
            Session::flash('error', 'Sujet ou unité destinataire invalide.');

            return Response::redirect(cooperation_mission_show_url($id));
        }
        $topic = $this->topicRepository->findById($topicId, $tenantId);
        if (!$topic) {
            Session::flash('error', 'Ce sujet n’existe pas dans votre brief ou n’appartient pas à votre unité.');

            return Response::redirect(cooperation_mission_show_url($id));
        }
        $this->interteamRepository->addForumGrant($id, 'topic', $topicId, $tenantId, $consumerId);
        $this->interteamRepository->logEvent($id, (int) Session::get('user_id'), $tenantId, 'topic_shared', [
            'topic_id' => $topicId,
            'consumer_tenant_id' => $consumerId,
        ]);
        Session::flash('success', 'Autorisation enregistrée. Les membres de l’unité destinataire verront l’espace dans leur brief (section coopération).');

        return Response::redirect(cooperation_mission_exchange_url($id));
    }

    public function revokeGrant(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Jeton de sécurité invalide.');
            $mid = (int) ($params['id'] ?? 0);

            return Response::redirect($mid > 0 ? cooperation_mission_show_url($mid) : cooperation_mission_index_url());
        }
        $mid = (int) ($params['id'] ?? 0);
        $grantId = (int) ($params['grantId'] ?? 0);
        $tenantId = (int) Session::get('tenant_id');
        if (!$this->interteamRepository->tenantIsForumHost($mid, $tenantId) || !$this->canManageInterteam() || $grantId <= 0) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(cooperation_mission_show_url($mid));
        }
        $this->interteamRepository->deleteGrant($grantId);
        $this->interteamRepository->logEvent($mid, (int) Session::get('user_id'), $tenantId, 'grant_revoked', ['grant_id' => $grantId]);
        Session::flash('success', 'Autorisation d’accès retirée.');

        return Response::redirect(cooperation_mission_exchange_url($mid));
    }

    public function close(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Jeton de sécurité invalide.');
            $id = (int) ($params['id'] ?? 0);

            return Response::redirect($id > 0 ? cooperation_mission_show_url($id) : cooperation_mission_index_url());
        }
        $id = (int) ($params['id'] ?? 0);
        $tenantId = (int) Session::get('tenant_id');
        if (!$this->interteamRepository->tenantCanPilotMission($id, $tenantId) || !$this->canManageInterteam()) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(cooperation_mission_index_url());
        }
        $motive = mb_substr(trim((string) $request->input('closure_motive', '')), 0, 500);
        $summary = trim((string) $request->input('closure_summary', ''));
        if (strlen($summary) > 20000) {
            $summary = mb_substr($summary, 0, 20000);
        }
        $retention = (string) $request->input('archive_retention', 'standard');
        if (!in_array($retention, ['court_terme', 'standard', 'renforce'], true)) {
            $retention = 'standard';
        }
        $this->interteamRepository->updateClosureMeta($id, [
            'closure_motive' => $motive !== '' ? $motive : null,
            'closure_summary' => $summary !== '' ? $summary : null,
            'archive_retention' => $retention,
        ]);
        $this->coopForumService->closeMission($id);
        $this->interteamRepository->logEvent($id, (int) Session::get('user_id'), $tenantId, 'mission_closed', []);
        $this->cooperationAnnouncementDispatcher->dispatch(
            CooperationAnnouncementEvents::MISSION_CLOSED,
            $id,
            (int) Session::get('user_id'),
            $tenantId,
            []
        );
        Session::flash('success', 'Coopération clôturée. Les accès partagés au brief ont été retirés et le fil commun a été clos.');

        return Response::redirect(cooperation_mission_show_url($id));
    }

    public function saveMeta(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Jeton de sécurité invalide.');
            $id = (int) ($params['id'] ?? 0);

            return Response::redirect($id > 0 ? cooperation_mission_show_url($id) : cooperation_mission_index_url());
        }
        $id = (int) ($params['id'] ?? 0);
        $tenantId = (int) Session::get('tenant_id');
        if (!$this->interteamRepository->tenantCanPilotMission($id, $tenantId) || !$this->canManageInterteam()) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(cooperation_mission_index_url());
        }
        $liaison = trim((string) $request->input('liaison_notes', ''));
        $atak1 = trim((string) $request->input('atak_endpoint_primary', ''));
        $atak2 = trim((string) $request->input('atak_endpoint_partner', ''));
        $replay = trim((string) $request->input('meeting_replay_url', ''));
        if (strlen($liaison) > 20000) {
            $liaison = substr($liaison, 0, 20000);
        }
        if (strlen($replay) > 500) {
            $replay = substr($replay, 0, 500);
        }
        if (strlen($atak1) > 255) {
            $atak1 = substr($atak1, 0, 255);
        }
        if (strlen($atak2) > 255) {
            $atak2 = substr($atak2, 0, 255);
        }
        $atakL1 = mb_substr(trim((string) $request->input('atak_primary_label', '')), 0, 160);
        $atakL2 = mb_substr(trim((string) $request->input('atak_partner_label', '')), 0, 160);
        $atakBascule = trim((string) $request->input('atak_bascule_notes', ''));
        if (strlen($atakBascule) > 20000) {
            $atakBascule = mb_substr($atakBascule, 0, 20000);
        }
        $atakSync = mb_substr(trim((string) $request->input('atak_sync_status', '')), 0, 32);
        $needs = [];
        foreach (['chef_mission', 'jtac', 'medic', 'pilote', 'analyste', 'radio', 'instructeur', 'logisticien'] as $nk) {
            if ($request->input('need_' . $nk) === '1' || $request->input('need_' . $nk) === 'on') {
                $needs[] = $nk;
            }
        }
        $needsJson = $needs !== [] ? json_encode($needs, JSON_UNESCAPED_UNICODE) : null;
        $this->interteamRepository->updateMissionMeta($id, [
            'liaison_notes' => $liaison !== '' ? $liaison : null,
            'atak_endpoint_primary' => $atak1 !== '' ? $atak1 : null,
            'atak_endpoint_partner' => $atak2 !== '' ? $atak2 : null,
            'meeting_replay_url' => $replay !== '' ? $replay : null,
            'atak_primary_label' => $atakL1 !== '' ? $atakL1 : null,
            'atak_partner_label' => $atakL2 !== '' ? $atakL2 : null,
            'atak_bascule_notes' => $atakBascule !== '' ? $atakBascule : null,
            'atak_sync_status' => $atakSync !== '' ? $atakSync : null,
            'competency_needs_json' => $needsJson,
        ]);
        $this->interteamRepository->logEvent($id, (int) Session::get('user_id'), $tenantId, 'mission_meta_updated', []);
        Session::flash('success', 'Informations de coordination enregistrées.');

        return Response::redirect(cooperation_mission_orbat_url($id));
    }

    public function promoteCoLead(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Jeton de sécurité invalide.');
            $id = (int) ($params['id'] ?? 0);

            return Response::redirect($id > 0 ? cooperation_mission_show_url($id) : cooperation_mission_index_url());
        }
        $id = (int) ($params['id'] ?? 0);
        $tenantId = (int) Session::get('tenant_id');
        if (!$this->interteamRepository->tenantIsForumHost($id, $tenantId) || !$this->canManageInterteam()) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(cooperation_mission_index_url());
        }
        $partnerTid = (int) $request->input('co_lead_tenant_id', 0);
        if (!$this->interteamRepository->promotePartnerToCoLead($id, $partnerTid)) {
            Session::flash('error', 'Impossible de promouvoir cette unité (elle doit être partenaire active).');

            return Response::redirect(cooperation_mission_show_url($id));
        }
        $this->interteamRepository->logEvent($id, (int) Session::get('user_id'), $tenantId, 'co_lead_promoted', ['tenant_id' => $partnerTid]);
        Session::flash('success', 'Co-pilote désigné : cette unité peut désormais inviter et lancer la coopération avec vous.');

        return Response::redirect(cooperation_mission_show_url($id));
    }

    public function saveExchangeLock(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Jeton de sécurité invalide.');
            $id = (int) ($params['id'] ?? 0);

            return Response::redirect($id > 0 ? cooperation_mission_exchange_url($id) : cooperation_mission_index_url());
        }
        $id = (int) ($params['id'] ?? 0);
        $tenantId = (int) Session::get('tenant_id');
        if (!$this->interteamRepository->tenantIsForumHost($id, $tenantId) || !$this->canManageInterteam()) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(cooperation_mission_index_url());
        }
        $mode = (string) $request->input('exchange_lock_mode', 'none');
        if (!in_array($mode, ['none', 'full', 'main_only', 'after_close'], true)) {
            $mode = 'none';
        }
        $this->interteamRepository->updateMissionMeta($id, ['exchange_lock_mode' => $mode]);
        $this->interteamRepository->logEvent($id, (int) Session::get('user_id'), $tenantId, 'mission_meta_updated', ['exchange_lock_mode' => $mode]);
        Session::flash('success', 'Règles d’accès à l’espace commun enregistrées.');

        return Response::redirect(cooperation_mission_exchange_url($id));
    }

    public function duplicateMission(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Jeton de sécurité invalide.');
            $id = (int) ($params['id'] ?? 0);

            return Response::redirect($id > 0 ? cooperation_mission_show_url($id) : cooperation_mission_index_url());
        }
        $id = (int) ($params['id'] ?? 0);
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if (!$this->interteamRepository->tenantCanPilotMission($id, $tenantId) || !$this->canManageInterteam()) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(cooperation_mission_index_url());
        }
        $src = $this->interteamRepository->findById($id);
        if (!$src) {
            return Response::redirect(cooperation_mission_index_url());
        }
        $baseTitle = trim((string) $request->input('duplicate_title', ''));
        if ($baseTitle === '') {
            $baseTitle = 'Copie — ' . trim((string) ($src['title'] ?? 'Coopération'));
        }
        if (strlen($baseTitle) > 255) {
            $baseTitle = mb_substr($baseTitle, 0, 255);
        }
        $slug = $this->uniqueSlugFromTitle($baseTitle);
        $newId = $this->interteamRepository->duplicateMissionAsDraft($id, $tenantId, $userId, $baseTitle, $slug);
        if ($newId <= 0) {
            Session::flash('error', 'La duplication n’a pas pu être effectuée.');

            return Response::redirect(cooperation_mission_show_url($id));
        }
        $this->cooperationAnnouncementDispatcher->dispatch(CooperationAnnouncementEvents::MISSION_CREATED, $newId, $userId, $tenantId, []);
        Session::flash('success', 'Nouvelle coopération créée à partir de celle-ci. Vérifiez le cadrage avant d’inviter à nouveau les unités.');

        return Response::redirect(cooperation_mission_show_url($newId));
    }

    public function assignMissionMember(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Jeton de sécurité invalide.');
            $id = (int) ($params['id'] ?? 0);

            return Response::redirect($id > 0 ? cooperation_mission_show_url($id) : cooperation_mission_index_url());
        }
        $id = (int) ($params['id'] ?? 0);
        $tenantId = (int) Session::get('tenant_id');
        $actorId = (int) Session::get('user_id');
        if (!$this->interteamRepository->tenantCanPilotMission($id, $tenantId) || !$this->canManageInterteam()) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(cooperation_mission_index_url());
        }
        $targetUserId = (int) $request->input('member_user_id', 0);
        $roleSlug = trim((string) $request->input('mission_role_slug', 'referent'));
        if ($targetUserId <= 0 || !$this->interteamRepository->missionMembersTableExists()) {
            Session::flash('error', 'Sélection invalide.');

            return Response::redirect(cooperation_mission_show_url($id));
        }
        $u = $this->userRepository->findById($targetUserId, $tenantId);
        if (!$u || (int) ($u['tenant_id'] ?? 0) !== $tenantId) {
            Session::flash('error', 'Ce membre n’appartient pas à votre unité.');

            return Response::redirect(cooperation_mission_show_url($id));
        }
        $this->interteamRepository->assignMissionMember($id, $targetUserId, $tenantId, $roleSlug, $actorId);
        $this->interteamRepository->logEvent($id, $actorId, $tenantId, 'mission_meta_updated', ['mission_member_role' => $roleSlug, 'user_id' => $targetUserId]);
        Session::flash('success', 'Rôle de coopération enregistré pour ce membre.');

        return Response::redirect(cooperation_mission_show_url($id));
    }

    public function startMeeting(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Jeton de sécurité invalide.');
            $id = (int) ($params['id'] ?? 0);

            return Response::redirect($id > 0 ? cooperation_mission_show_url($id) : cooperation_mission_index_url());
        }
        $id = (int) ($params['id'] ?? 0);
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if (!$this->interteamRepository->tenantCanPilotMission($id, $tenantId) || !$this->canManageInterteam()) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(cooperation_mission_index_url());
        }
        $mid = $this->interteamRepository->createMeeting($id, $userId);
        if ($mid > 0) {
            $this->interteamRepository->logEvent($id, $userId, $tenantId, 'meeting_started', ['meeting_row_id' => $mid]);
        }
        Session::flash('success', 'Réunion enregistrée dans le journal. Ouvrez l’onglet Réunion pour le salon vidéo.');

        return Response::redirect(cooperation_mission_meeting_url($id));
    }

    public function consent(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId <= 0 || $userId <= 0) {
            return Response::redirect(url('login'));
        }
        $id = (int) ($params['id'] ?? 0);
        $mission = $this->interteamRepository->findById($id);
        if (!$mission || !$this->interteamRepository->consentsTableExists()) {
            Session::flash('error', 'Demande invalide.');

            return Response::redirect(cooperation_mission_index_url());
        }
        $parts = $this->interteamRepository->listParticipants($id);
        $ok = false;
        foreach ($parts as $p) {
            if ((int) ($p['tenant_id'] ?? 0) === $tenantId && ($p['status'] ?? '') === 'active') {
                $ok = true;
                break;
            }
        }
        if (!$ok) {
            Session::flash('error', 'Accès refusé.');

            return Response::redirect(cooperation_mission_index_url());
        }
        $return = trim((string) $request->query('return', ''));
        if ($return !== '' && !str_starts_with($return, '/')) {
            $return = '';
        }

        $typo = isset($mission['cooperation_typology']) ? (string) $mission['cooperation_typology'] : null;

        return Response::view('layout.main', [
            'content' => 'back_office.cooperation.missions.consent',
            'title' => 'Autorisation de partage',
            'interteamMission' => $mission,
            'interteamConsentReturn' => $return,
            'cooperationMissionNavActive' => 'consent',
            'cooperationSuggestedShareKeys' => CooperationConsentDefaults::suggestedKeysForTypology($typo !== '' ? $typo : null),
            'csrfToken' => Csrf::token(),
        ]);
    }

    public function consentSendOtp(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Jeton de sécurité invalide.');

            return Response::redirect(cooperation_mission_index_url());
        }
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        $id = (int) ($params['id'] ?? 0);
        $mission = $this->interteamRepository->findById($id);
        if (!$mission || !$this->interteamRepository->consentsTableExists()) {
            Session::flash('error', 'Demande invalide.');

            return Response::redirect(cooperation_mission_index_url());
        }
        $keys = $this->consentKeysFromRequest($request);
        if ($keys === []) {
            Session::flash('error', 'Cochez au moins une autorisation pour continuer.');

            return Response::redirect(cooperation_mission_consent_url($id) . $this->consentReturnQuery($request));
        }
        $this->interteamRepository->upsertConsentDraft($id, $userId, $tenantId, $keys);
        $justification = trim((string) $request->input('justification_sensitive', ''));
        if ($justification !== '' && strlen($justification) > 4000) {
            $justification = mb_substr($justification, 0, 4000);
        }
        if ($justification !== '') {
            $this->interteamRepository->updateConsentJustification($id, $userId, $justification);
        }
        $last = $this->emailTokenRepository->getLatestTokenCreatedAtForUserPurpose($userId, EmailTokenPurpose::INTERTEAM_CONSENT_OTP);
        if ($last !== null && (time() - $last->getTimestamp()) < self::OTP_RESEND_SEC) {
            Session::flash('error', 'Un code vient d’être envoyé. Patientez une minute avant d’en demander un autre.');

            return Response::redirect(cooperation_mission_consent_url($id) . $this->consentReturnQuery($request));
        }
        $user = $this->userRepository->findById($userId, $tenantId);
        $email = strtolower(trim((string) ($user['email'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Votre compte n’a pas d’adresse e-mail utilisable pour recevoir le code.');

            return Response::redirect(cooperation_mission_consent_url($id) . $this->consentReturnQuery($request));
        }
        $code = (string) random_int(100000, 999999);
        $hash = hash('sha256', $code);
        $expires = new \DateTimeImmutable('+' . self::OTP_TTL_MIN . ' minutes');
        $this->emailTokenRepository->deletePendingForUserPurpose($userId, EmailTokenPurpose::INTERTEAM_CONSENT_OTP);
        $this->emailTokenRepository->create(
            $tenantId,
            $userId,
            EmailTokenPurpose::INTERTEAM_CONSENT_OTP,
            $hash,
            bin2hex(random_bytes(8)),
            $expires,
            ['mission_id' => $id]
        );
        $tenantRow = $this->tenantRepository->findById($tenantId);
        $tenantName = (string) ($tenantRow['name'] ?? 'Communauté');
        $displayName = (string) ($user['display_name'] ?? 'Membre');
        $missionTitle = (string) ($mission['title'] ?? '');
        $shareSummary = $this->formatConsentKeysForEmail($keys);
        $sent = $this->emailService->sendInterteamCooperationOtp(
            $email,
            $displayName,
            $tenantName,
            $code,
            self::OTP_TTL_MIN,
            $missionTitle,
            $tenantId,
            $shareSummary
        );
        if (!$sent) {
            Session::flash('error', 'L’e-mail n’a pas pu être envoyé. Réessayez plus tard ou vérifiez la configuration des courriels.');

            return Response::redirect(cooperation_mission_consent_url($id) . $this->consentReturnQuery($request));
        }
        Session::flash('success', 'Un code de confirmation vient d’être envoyé à votre adresse e-mail.');
        $rq = $this->consentReturnQuery($request);

        return Response::redirect(cooperation_mission_consent_url($id) . $rq);
    }

    private function consentReturnQuery(Request $request): string
    {
        $return = trim((string) $request->input('return', ''));
        if ($return === '' || !str_starts_with($return, '/')) {
            return '';
        }

        return '?return=' . rawurlencode($return);
    }

    public function consentVerifyOtp(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Jeton de sécurité invalide.');

            return Response::redirect(cooperation_mission_index_url());
        }
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        $id = (int) ($params['id'] ?? 0);
        if ($this->interteamRepository->countRecentOtpFailures($id, $userId, 900) >= 10) {
            Session::flash('error', 'Trop de tentatives récentes. Patientez un quart d’heure ou demandez un nouveau code.');

            return Response::redirect(cooperation_mission_consent_url($id) . $this->consentReturnQuery($request));
        }
        $code = preg_replace('/\D/', '', (string) $request->input('otp_code', '')) ?? '';
        if (strlen($code) !== 6) {
            Session::flash('error', 'Saisissez le code à six chiffres reçu par e-mail.');

            return Response::redirect(cooperation_mission_consent_url($id) . $this->consentReturnQuery($request));
        }
        $hash = hash('sha256', $code);
        $row = $this->emailTokenRepository->findValidByHash($hash);
        $ipPrefix = isset($_SERVER['REMOTE_ADDR']) ? mb_substr((string) $_SERVER['REMOTE_ADDR'], 0, 45) : null;
        if (!$row || (string) ($row['purpose'] ?? '') !== EmailTokenPurpose::INTERTEAM_CONSENT_OTP
            || (int) ($row['user_id'] ?? 0) !== $userId) {
            $this->interteamRepository->recordOtpAttempt($id, $userId, 'fail', $ipPrefix);
            Session::flash('error', 'Code incorrect ou expiré.');

            return Response::redirect(cooperation_mission_consent_url($id) . $this->consentReturnQuery($request));
        }
        $meta = [];
        if (!empty($row['metadata'])) {
            $meta = json_decode((string) $row['metadata'], true) ?: [];
        }
        if ((int) ($meta['mission_id'] ?? 0) !== $id) {
            $this->interteamRepository->recordOtpAttempt($id, $userId, 'fail', $ipPrefix);
            Session::flash('error', 'Ce code ne correspond pas à cette coopération.');

            return Response::redirect(cooperation_mission_consent_url($id) . $this->consentReturnQuery($request));
        }
        $this->emailTokenRepository->markConsumed((int) $row['id']);
        $this->interteamRepository->recordOtpAttempt($id, $userId, 'ok', $ipPrefix);
        $this->interteamRepository->markConsentOtpVerified($id, $userId);
        $this->interteamRepository->logEvent($id, $userId, $tenantId, 'consent_verified', []);
        Session::flash('success', 'Autorisation confirmée. Vous pouvez accéder aux échanges partagés.');
        $return = trim((string) $request->input('return', ''));
        if ($return !== '' && str_starts_with($return, '/')) {
            return Response::redirect(url(ltrim($return, '/')));
        }

        return Response::redirect(cooperation_mission_show_url($id));
    }

    /** @return list<string> */
    private function consentKeysFromRequest(Request $request): array
    {
        $allowed = ['brief', 'liaison', 'competency', 'identity', 'org_structure', 'qualification', 'readiness', 'material', 'map', 'documents', 'minutes', 'meeting', 'cert_excerpt'];
        $out = [];
        foreach ($allowed as $k) {
            if ($request->input('share_' . $k) === '1' || $request->input('share_' . $k) === 'on') {
                $out[] = $k;
            }
        }

        return $out;
    }

    /** @param list<string> $keys */
    private function formatConsentKeysForEmail(array $keys): string
    {
        $parts = [];
        foreach ($keys as $k) {
            $parts[] = CooperationDictionary::dataSharingFamilyLabel((string) $k);
        }

        return $parts !== [] ? implode(', ', $parts) : 'Autorisations sélectionnées sur le portail';
    }

    private function assertInterteamAccess(Request $request): bool
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId <= 0 || $userId <= 0) {
            Session::flash('error', 'Authentification requise.');

            return false;
        }
        if (!$this->interteamRepository->tableExists()) {
            Session::flash('error', 'Fonction indisponible.');

            return false;
        }
        if (!$this->canManageInterteam() && !(function_exists('can') && can('cooperation.missions.create'))) {
            Session::flash('error', 'Accès réservé aux personnes habilitées à piloter les coopérations inter-unités.');

            return false;
        }

        return true;
    }

    private function canManageInterteam(): bool
    {
        if (!function_exists('can')) {
            return false;
        }
        $gate = Gate::getInstance();

        return can('interteam.missions.manage')
            || can('cooperation.missions.manage')
            || $gate->allows('admin.organization')
            || $gate->allows('admin.access')
            || $gate->allows('admin.system');
    }

    private function canRespondInterteam(): bool
    {
        if (!function_exists('can')) {
            return false;
        }
        $gate = Gate::getInstance();

        return can('interteam.missions.respond')
            || can('cooperation.missions.respond')
            || $gate->allows('admin.organization')
            || $gate->allows('admin.access')
            || $gate->allows('admin.system')
            || $this->canManageInterteam();
    }

    private function uniqueSlugFromTitle(string $title): string
    {
        $base = strtolower(trim(preg_replace('/[^\p{L}\p{N}]+/u', '-', $title) ?? ''));
        $base = trim($base, '-') ?: 'mission';
        $base = substr($base, 0, 80);
        $slug = $base;
        $n = 0;
        while ($this->interteamRepository->findBySlug($slug) !== null) {
            $n++;
            $slug = $base . '-' . $n;
            if (strlen($slug) > 110) {
                $slug = 'm' . bin2hex(random_bytes(6));
            }
        }

        return $slug;
    }
}
