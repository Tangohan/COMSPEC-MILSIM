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
use App\Services\Cooperation\CooperationProgress;
use App\Services\Cooperation\CooperationTransitionRules;
use App\Services\Cooperation\CooperationWorkflowService;
use App\Services\Interteam\InterteamCoopForumService;
use App\Support\CooperationAccess;
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
            Session::flash('error', 'Connectez-vous pour continuer.');

            return Response::redirect(url('login'));
        }
        if (!$this->interteamRepository->tableExists()) {
            Session::flash('error', 'Fonction indisponible sur cette installation.');

            return Response::redirect(url('dashboard'));
        }

        $missions = $this->interteamRepository->listForTenant($tenantId);
        $partsByMission = $this->interteamRepository->listParticipantsForMissions(array_map(static fn (array $m): int => (int) ($m['id'] ?? 0), $missions));
        $canManage = $this->canManageInterteam();
        $rows = [];
        foreach ($missions as $m) {
            $mid = (int) ($m['id'] ?? 0);
            $parts = $partsByMission[$mid] ?? [];
            $mine = CooperationTransitionRules::participantFor($tenantId, $parts);
            $isPilot = $canManage && $mine !== null && ($mine['status'] ?? '') === 'active' && in_array((string) ($mine['role'] ?? ''), ['lead', 'co_lead'], true);
            $progress = CooperationProgress::compute($m, $parts, [
                'viewer_tenant_id' => $tenantId,
                'can_pilot' => $isPilot,
                'counter_pending' => ($m['counter_proposal_status'] ?? '') === 'pending_host',
                'urls' => ['show' => cooperation_mission_show_url($mid)],
            ]);
            $engaged = [];
            foreach ($parts as $p) {
                if ((int) ($p['tenant_id'] ?? 0) !== $tenantId && in_array((string) ($p['status'] ?? ''), ['active', 'invited'], true)) {
                    $engaged[] = (string) ($p['tenant_name'] ?? '');
                }
            }
            $rows[] = [
                'mission' => $m,
                'progress' => $progress,
                'units' => $engaged,
                'action_required' => CooperationProgress::actionRequiredForViewer($progress['next_action']),
                'filter' => match (true) {
                    $progress['cancelled'] => 'cancelled',
                    (string) ($m['status'] ?? '') === 'archived' => 'closed',
                    (string) ($m['status'] ?? '') === 'active' => 'active',
                    (string) ($m['status'] ?? '') === 'pending' => 'pending',
                    default => 'draft',
                },
            ];
        }

        return Response::view('layout.main', [
            'content' => 'back_office.cooperation.missions.index',
            'title' => 'Coopération inter-unités',
            'interteamMissions' => $missions,
            'cooperationRows' => $rows,
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

        $tenantId = (int) Session::get('tenant_id');

        return Response::view('layout.main', [
            'content' => 'back_office.cooperation.missions.create',
            'title' => 'Nouvelle coopération',
            'cooperationTypologyChoices' => $this->cooperationCatalogService->typologyChoicesForTenant($tenantId),
            'cooperationPriorityChoices' => CooperationDictionary::priorityChoices(),
            'cooperationTenantChoices' => CooperationTransitionRules::invitablePicker($this->tenantRepository->listBasicExcluding($tenantId), [], $tenantId),
            'interteamProposalFieldsEnabled' => $this->interteamRepository->columnExists('interteam_missions', 'cooperation_typology'),
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

        // Assistant de création : cadrage facultatif (typologie, priorité, échéance) et unités à inviter.
        $meta = [];
        $typRaw = trim((string) $request->input('cooperation_typology', ''));
        if ($typRaw !== '') {
            $typology = $this->cooperationCatalogService->normalizeTypologyForTenant($typRaw, $tenantId);
            if ($typology !== null) {
                $meta['cooperation_typology'] = $typology;
            }
        }
        if (trim((string) $request->input('cooperation_priority', '')) !== '') {
            $meta['cooperation_priority'] = CooperationDictionary::normalizePriority((string) $request->input('cooperation_priority', ''));
        }
        $deadline = $this->normalizeDateTimeInput((string) $request->input('proposal_deadline_at', ''));
        if ($deadline !== null) {
            $meta['proposal_deadline_at'] = $deadline;
        }
        if ($meta !== []) {
            $this->interteamRepository->updateMissionProposalMeta($id, $meta);
        }
        $rawIds = $request->input('partner_tenant_ids', null);
        $ids = is_array($rawIds)
            ? array_values(array_unique(array_filter(array_map('intval', $rawIds), static fn (int $v): bool => $v > 0)))
            : [];
        $send = (string) $request->input('send_invitations', '') === '1';
        if ($send && $ids !== []) {
            [$invited, $skipped] = $this->inviteTenants($id, $tenantId, $userId, $ids, false);
            if ($invited !== []) {
                $this->interteamRepository->markProposalSentIfDraft($id);
                Session::flash('success', 'Coopération créée et invitation' . (count($invited) > 1 ? 's' : '') . ' envoyée' . (count($invited) > 1 ? 's' : '') . ' à ' . implode(', ', $invited) . '.');
            }
            if ($skipped !== []) {
                Session::flash('warning', 'Non invitée' . (count($skipped) > 1 ? 's' : '') . ' : ' . implode(' ; ', $skipped) . '.');
            }
            if ($invited === []) {
                Session::flash('success', 'Coopération enregistrée en brouillon.');
            }
        } else {
            if ($ids !== []) {
                // Brouillon : la sélection est conservée comme note pour l’envoi ultérieur.
                $this->interteamRepository->logEvent($id, $userId, $tenantId, 'mission_proposal_updated', ['draft_partner_tenant_ids' => $ids]);
            }
            Session::flash('success', 'Coopération enregistrée en brouillon. Invitez les unités partenaires depuis la synthèse lorsque le cadrage est prêt.');
        }

        return Response::redirect(cooperation_mission_show_url($id));
    }

    public function show(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId <= 0 || $userId <= 0) {
            Session::flash('error', 'Connectez-vous pour continuer.');

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
        $this->cooperationAnnouncementDispatcher->dispatch(
            CooperationAnnouncementEvents::COUNTER_PROPOSAL_SUBMITTED,
            $id,
            $userId,
            $tenantId,
            ['partner_tenant_id' => $tenantId]
        );
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
        $missionBefore = $this->interteamRepository->findById($id);
        $partnerTid = (int) ($missionBefore['counter_proposal_tenant_id'] ?? 0);
        $decision = (string) $request->input('decision', '');
        if ($decision === 'accept') {
            $this->interteamRepository->integrateCounterProposal($id, $tenantId, $userId);
            $this->cooperationAnnouncementDispatcher->dispatch(
                CooperationAnnouncementEvents::COUNTER_PROPOSAL_ACCEPTED,
                $id,
                $userId,
                $tenantId,
                ['partner_tenant_id' => $partnerTid]
            );
            Session::flash('success', 'Contre-proposition prise en compte. Vous pouvez poursuivre la validation avec les unités partenaires.');
        } elseif ($decision === 'decline') {
            $this->interteamRepository->declineCounterProposal($id, $tenantId, $userId);
            $this->cooperationAnnouncementDispatcher->dispatch(
                CooperationAnnouncementEvents::COUNTER_PROPOSAL_DECLINED,
                $id,
                $userId,
                $tenantId,
                ['partner_tenant_id' => $partnerTid]
            );
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
        $partnerPicker = $canPilot
            ? CooperationTransitionRules::invitablePicker($this->tenantRepository->listBasicExcluding($tenantId), $participants, $tenantId)
            : [];
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
        $sitreps = $this->interteamRepository->listSitreps($missionId, 40);
        $showUrl = cooperation_mission_show_url($missionId);
        $progress = CooperationProgress::compute($mission, $participants, [
            'viewer_tenant_id' => $tenantId,
            'can_pilot' => $canManage,
            'counter_pending' => $counterPending,
            'consent_done' => $consentDone,
            'rex_done' => $interteamRexRow !== null,
            'sitrep_count' => count($sitreps),
            'urls' => [
                'show' => $showUrl,
                'participants' => $showUrl . '#participants',
                'invitation' => $showUrl . '#invitation',
                'launch' => $showUrl . '#lancement',
                'conduct' => $showUrl . '#conduite',
                'negotiate' => cooperation_mission_negotiate_url($missionId),
                'exchange' => cooperation_mission_exchange_url($missionId),
                'consent' => cooperation_mission_consent_url($missionId),
                'archive' => cooperation_mission_archive_url($missionId),
                'rex' => cooperation_mission_rex_url($missionId),
            ],
        ]);

        return [
            'interteamMission' => $mission,
            'interteamParticipants' => $participants,
            'interteamGrants' => $grants,
            'interteamIsLead' => $isForumHost,
            'interteamCanManage' => $canManage,
            'interteamCanPilot' => $canPilot,
            'interteamCanRespond' => $canRespond,
            'interteamPartnerPicker' => $partnerPicker,
            'interteamInvitationRule' => CooperationTransitionRules::invitation($mission),
            'interteamLaunchReadiness' => CooperationTransitionRules::launchReadiness($mission, $participants, $counterPending),
            'interteamIsTerminal' => CooperationTransitionRules::isTerminal($mission),
            'interteamTopicChoices' => $topicChoices,
            'interteamEvents' => $events,
            'interteamMeetings' => $meetings,
            'interteamOrbatBlocks' => $orbatBlocks,
            'interteamJitsiRoom' => $jitsiRoom,
            'interteamJitsiDomain' => $jitsiDomain,
            'interteamJitsiEnabled' => $jitsiEnabled,
            'interteamConsentDone' => $consentDone,
            'interteamCoopTopicUrl' => $coopTopicUrl,
            'trainingCompetencyCommandUrl' => training_lms_admin_url('competences/commandement'),
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
            'interteamSitreps' => $sitreps,
            'cooperationProgress' => $progress,
            'cooperationConsentByTenant' => $this->interteamRepository->consentSummaryByTenant($missionId),
            'cooperationViewerConsent' => $this->interteamRepository->consentsTableExists()
                ? $this->interteamRepository->consentStatus($missionId, $userId)
                : ['state' => 'valid', 'until' => null, 'keys' => [], 'justification' => ''],
            'cooperationLastReminderByTenant' => $canManage ? $this->interteamRepository->lastInvitationReminderByTenant($missionId, 48) : [],
            'interteamCorrectiveActionsText' => $this->notesJsonToText($mission['corrective_actions_json'] ?? null),
            'interteamLinkedResourcesText' => $this->notesJsonToText($mission['linked_resources_json'] ?? null),
            'interteamSimulatedLossesText' => $this->notesJsonToText($mission['simulated_losses_json'] ?? null),
            'interteamLessonsLearnedText' => $this->notesJsonToText($mission['lessons_learned_json'] ?? null),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function operationalStageChoices(): array
    {
        return [
            'opord_draft' => '1) Brouillon d’ordre d’opération',
            'command_validation' => '2) Validation par le commandement',
            'execution' => '3) Exécution (points de situation)',
            'closed_aar' => '4) Clôture et bilan',
            'corrective_actions' => '5) Actions correctives',
        ];
    }

    private function operationalStageErrorLabel(string $error): string
    {
        return match ($error) {
            'workflow_unavailable' => 'Le suivi de conduite n’est pas encore disponible pour cette installation.',
            'invalid_stage' => 'Étape de conduite non reconnue.',
            'mission_not_found' => 'Dossier de coopération introuvable.',
            'backward_transition_forbidden' => 'Retour en arrière non autorisé : respectez l’ordre des étapes.',
            'jump_transition_forbidden' => 'Impossible de sauter une étape : avancez dans l’ordre prévu.',
            'opord_required' => 'Rédigez l’ordre d’opération avant de demander la validation du commandement.',
            'mission_must_be_active' => 'La coopération doit être lancée pour passer en exécution.',
            'aar_required' => 'Rédigez le bilan avant de clôturer.',
            default => 'Impossible de mettre à jour l’étape de conduite.',
        };
    }

    /**
     * Texte libre → JSON notes (compatibilité colonnes JSON existantes).
     */
    private function notesTextToJson(?string $text): ?string
    {
        $text = trim((string) $text);
        if ($text === '') {
            return null;
        }
        if (strlen($text) > 20000) {
            $text = mb_substr($text, 0, 20000);
        }

        return json_encode(['notes' => $text], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Extrait un texte affichable depuis une colonne JSON (notes ou chaîne brute).
     */
    private function notesJsonToText(mixed $raw): string
    {
        if ($raw === null || $raw === '') {
            return '';
        }
        if (is_array($raw)) {
            if (isset($raw['notes']) && is_string($raw['notes'])) {
                return $raw['notes'];
            }

            return trim(implode("\n", array_map(static fn ($v): string => is_scalar($v) ? (string) $v : '', $raw)));
        }
        $s = trim((string) $raw);
        if ($s === '') {
            return '';
        }
        $decoded = json_decode($s, true);
        if (is_array($decoded)) {
            if (isset($decoded['notes']) && is_string($decoded['notes'])) {
                return $decoded['notes'];
            }

            return $s;
        }

        return $s;
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

    /*
     * Actions courtes : réponse JSON si le client la demande (amélioration progressive),
     * sinon redirection + message flash comme avant. Les règles et contrôles sont identiques.
     */
    public function invite(Request $request, array $params = []): Response
    {
        return $this->ajaxify($this->handleInvite($request, $params));
    }

    public function accept(Request $request, array $params = []): Response
    {
        return $this->ajaxify($this->handleAccept($request, $params));
    }

    public function decline(Request $request, array $params = []): Response
    {
        return $this->ajaxify($this->handleDecline($request, $params));
    }

    public function remindPartner(Request $request, array $params = []): Response
    {
        return $this->ajaxify($this->handleRemindPartner($request, $params));
    }

    public function removePartner(Request $request, array $params = []): Response
    {
        return $this->ajaxify($this->handleRemovePartner($request, $params));
    }

    public function revokeGrant(Request $request, array $params = []): Response
    {
        return $this->ajaxify($this->handleRevokeGrant($request, $params));
    }

    public function addSitrep(Request $request, array $params = []): Response
    {
        return $this->ajaxify($this->handleAddSitrep($request, $params));
    }

    public function assignMissionMember(Request $request, array $params = []): Response
    {
        return $this->ajaxify($this->handleAssignMissionMember($request, $params));
    }

    /** Champ à signaler à côté de l’erreur (réponse JSON). */
    private ?string $errorField = null;

    private function wantsJson(): bool
    {
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        $xrw = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));

        return str_contains($accept, 'application/json') || $xrw === 'xmlhttprequest';
    }

    /**
     * Convertit « flash + redirection » en JSON {ok, variant, message, warning, redirect, field}.
     */
    private function ajaxify(Response $response): Response
    {
        if (!$this->wantsJson()) {
            return $response;
        }
        $error = Session::getFlash('error');
        $success = Session::getFlash('success');
        $warning = Session::getFlash('warning');
        $ok = $error === null || $error === '';
        $message = (string) ($ok ? ($success ?? $warning ?? '') : $error);
        $payload = [
            'ok' => $ok,
            'variant' => $ok ? ($success !== null ? 'success' : 'warning') : 'error',
            'message' => $message,
            'warning' => $ok && $success !== null && $warning !== null ? (string) $warning : null,
            'redirect' => $response->headerValue('Location'),
            'field' => $ok ? null : $this->errorField,
        ];
        $json = Response::json($payload, $ok ? 200 : 422);
        $json->header('Cache-Control', 'no-store');

        return $json;
    }

    private function handleInvite(Request $request, array $params = []): Response
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
        $mission = $this->interteamRepository->findById($id);
        if (!$mission) {
            return Response::redirect(cooperation_mission_index_url());
        }
        $rule = CooperationTransitionRules::invitation($mission);
        if (!$rule['allowed']) {
            Session::flash('error', CooperationTransitionRules::reasonLabel($rule['reason']));

            return Response::redirect(cooperation_mission_show_url($id));
        }
        if ($rule['reinforcement'] && (string) $request->input('confirm_reinforcement', '') !== '1') {
            Session::flash('error', 'La coopération est en cours : confirmez qu’il s’agit d’un renfort pour inviter une nouvelle unité.');

            return Response::redirect(cooperation_mission_show_url($id));
        }

        // Une ou plusieurs unités (sélection multiple) ; « partner_tenant_id » reste accepté seul.
        $raw = $request->input('partner_tenant_ids', null);
        $ids = is_array($raw) ? $raw : [];
        $single = (int) $request->input('partner_tenant_id', 0);
        if ($single > 0) {
            $ids[] = $single;
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $v): bool => $v > 0)));
        if ($ids === []) {
            $this->errorField = 'partner_tenant_ids';
            Session::flash('error', 'Choisissez au moins une unité à inviter.');

            return Response::redirect(cooperation_mission_show_url($id));
        }
        $ids = array_slice($ids, 0, 20);

        [$invited, $skipped] = $this->inviteTenants($id, $tenantId, $userId, $ids, $rule['reinforcement']);
        if ($invited !== []) {
            $this->interteamRepository->markProposalSentIfDraft($id);
            Session::flash('success', (count($invited) > 1 ? 'Invitations envoyées à ' : 'Invitation envoyée à ') . implode(', ', $invited)
                . '. Chaque unité peut accepter ou refuser depuis son back-office.');
        }
        if ($skipped !== []) {
            Session::flash($invited === [] ? 'error' : 'warning', 'Non invitée' . (count($skipped) > 1 ? 's' : '') . ' : ' . implode(' ; ', $skipped) . '.');
        }

        return Response::redirect(cooperation_mission_show_url($id));
    }

    /**
     * Invite une liste d’unités (contrôles de règles inclus). Retourne [noms invités, motifs d’exclusion].
     *
     * @param list<int> $ids
     * @return array{0: list<string>, 1: list<string>}
     */
    private function inviteTenants(int $missionId, int $tenantId, int $userId, array $ids, bool $reinforcement): array
    {
        $participants = $this->interteamRepository->listParticipants($missionId);
        $invited = [];
        $skipped = [];
        foreach (array_slice($ids, 0, 20) as $partnerId) {
            $check = CooperationTransitionRules::canInviteTenant($partnerId, $tenantId, $participants);
            $tenantRow = $this->tenantRepository->findById($partnerId);
            $name = (string) ($tenantRow['name'] ?? ('Unité #' . $partnerId));
            if (!$check['allowed'] || !$tenantRow || $partnerId <= 1) {
                $skipped[] = $name . ' (' . mb_strtolower(rtrim(CooperationTransitionRules::reasonLabel($tenantRow ? $check['reason'] : 'invalid_tenant'), '.')) . ')';
                continue;
            }
            $this->interteamRepository->invitePartner($missionId, $partnerId);
            $this->interteamRepository->logEvent($missionId, $userId, $tenantId, 'partner_invited', [
                'partner_tenant_id' => $partnerId,
                'reinforcement' => $reinforcement,
            ]);
            $this->cooperationAnnouncementDispatcher->dispatch(
                CooperationAnnouncementEvents::INVITATION_SENT,
                $missionId,
                $userId,
                $tenantId,
                ['invited_tenant_id' => $partnerId]
            );
            $invited[] = $name;
            $participants[] = ['tenant_id' => $partnerId, 'status' => 'invited', 'role' => 'partner', 'tenant_name' => $name];
        }

        return [$invited, $skipped];
    }

    private function handleAccept(Request $request, array $params = []): Response
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
        $mission = $this->interteamRepository->findById($id);
        $check = $mission
            ? CooperationTransitionRules::canRespondToInvitation($mission, $tenantId, $this->interteamRepository->listParticipants($id))
            : ['allowed' => false, 'reason' => 'not_invited'];
        if (!$check['allowed']) {
            Session::flash('error', CooperationTransitionRules::reasonLabel($check['reason']));

            return Response::redirect($mission ? cooperation_mission_show_url($id) : cooperation_mission_index_url());
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

    private function handleDecline(Request $request, array $params = []): Response
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
        $mission = $this->interteamRepository->findById($id);
        $check = $mission
            ? CooperationTransitionRules::canRespondToInvitation($mission, $tenantId, $this->interteamRepository->listParticipants($id))
            : ['allowed' => false, 'reason' => 'not_invited'];
        if (!$check['allowed']) {
            Session::flash('error', CooperationTransitionRules::reasonLabel($check['reason']));

            return Response::redirect($mission ? cooperation_mission_show_url($id) : cooperation_mission_index_url());
        }
        $reason = mb_substr(trim((string) $request->input('decline_reason', '')), 0, 1000);
        $this->interteamRepository->setParticipantStatus($id, $tenantId, 'declined');
        $this->interteamRepository->logEvent($id, (int) Session::get('user_id'), $tenantId, 'partner_declined', $reason !== '' ? ['reason' => $reason] : []);
        $this->cooperationAnnouncementDispatcher->dispatch(
            CooperationAnnouncementEvents::PARTNER_DECLINED,
            $id,
            (int) Session::get('user_id'),
            $tenantId,
            $reason !== '' ? ['reason' => $reason] : []
        );
        Session::flash('success', 'Invitation refusée. L’unité support en est informée' . ($reason !== '' ? ', avec votre motif.' : '.'));

        return Response::redirect(cooperation_mission_index_url());
    }

    /**
     * Retrait d’une unité (invitation en attente ou unité engagée) par le pilotage.
     */
    private function handleRemovePartner(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Jeton de sécurité invalide.');

            return Response::redirect($id > 0 ? cooperation_mission_show_url($id) : cooperation_mission_index_url());
        }
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if (!$this->interteamRepository->tenantCanPilotMission($id, $tenantId) || !$this->canManageInterteam()) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(cooperation_mission_index_url());
        }
        $mission = $this->interteamRepository->findById($id);
        if (!$mission) {
            return Response::redirect(cooperation_mission_index_url());
        }
        $targetTid = (int) $request->input('partner_tenant_id', 0);
        $participants = $this->interteamRepository->listParticipants($id);
        $check = CooperationTransitionRules::canRemovePartner($mission, $targetTid, $tenantId, $participants);
        if (!$check['allowed']) {
            Session::flash('error', CooperationTransitionRules::reasonLabel($check['reason']));

            return Response::redirect(cooperation_mission_show_url($id));
        }
        $reason = mb_substr(trim((string) $request->input('remove_reason', '')), 0, 1000);
        $this->interteamRepository->setParticipantStatus($id, $targetTid, 'left');
        $revoked = $this->interteamRepository->deleteGrantsForConsumer($id, $targetTid);
        $this->interteamRepository->logEvent($id, $userId, $tenantId, 'partner_removed', array_filter([
            'partner_tenant_id' => $targetTid,
            'previous_status' => $check['was'],
            'grants_revoked' => $revoked,
            'reason' => $reason,
        ], static fn ($v) => $v !== '' && $v !== 0));
        $this->cooperationAnnouncementDispatcher->dispatch(
            CooperationAnnouncementEvents::PARTNER_REMOVED,
            $id,
            $userId,
            $tenantId,
            ['partner_tenant_id' => $targetTid, 'reason' => $reason]
        );
        $p = CooperationTransitionRules::participantFor($targetTid, $participants);
        $name = (string) ($p['tenant_name'] ?? 'L’unité');
        Session::flash('success', $check['was'] === 'invited'
            ? 'Invitation retirée : ' . $name . ' ne fait plus partie des unités sollicitées.'
            : $name . ' a été retirée de la coopération' . ($revoked > 0 ? ' ; ses accès partagés au brief sont fermés.' : '.'));

        return Response::redirect(cooperation_mission_show_url($id));
    }

    /**
     * Annulation d’une proposition non lancée (phase « cancelled »), motif obligatoire.
     */
    public function cancelProposal(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Jeton de sécurité invalide.');

            return Response::redirect($id > 0 ? cooperation_mission_archive_url($id) : cooperation_mission_index_url());
        }
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if (!$this->interteamRepository->tenantCanPilotMission($id, $tenantId) || !$this->canManageInterteam()) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(cooperation_mission_index_url());
        }
        $mission = $this->interteamRepository->findById($id);
        if (!$mission) {
            return Response::redirect(cooperation_mission_index_url());
        }
        $motive = mb_substr(trim((string) $request->input('cancel_motive', '')), 0, 500);
        $check = CooperationTransitionRules::canCancelProposal($mission, $motive);
        if (!$check['allowed']) {
            Session::flash('error', CooperationTransitionRules::reasonLabel($check['reason']));

            return Response::redirect(cooperation_mission_archive_url($id));
        }
        // Unités à prévenir : celles qui étaient invitées ou avaient déjà accepté.
        $notify = [];
        foreach ($this->interteamRepository->listParticipants($id) as $p) {
            if ((string) ($p['role'] ?? '') !== 'lead' && in_array((string) ($p['status'] ?? ''), ['invited', 'active'], true)) {
                $notify[] = (int) ($p['tenant_id'] ?? 0);
            }
        }
        $this->interteamRepository->updateClosureMeta($id, ['closure_motive' => $motive]);
        $this->coopForumService->closeMission($id);
        $this->interteamRepository->markCancelled($id);
        $this->interteamRepository->logEvent($id, $userId, $tenantId, 'proposal_cancelled', ['reason' => $motive]);
        $this->cooperationAnnouncementDispatcher->dispatch(
            CooperationAnnouncementEvents::PROPOSAL_CANCELLED,
            $id,
            $userId,
            $tenantId,
            ['reason' => $motive, 'notify_tenant_ids' => $notify]
        );
        Session::flash('success', 'Proposition annulée. Les unités sollicitées en sont informées ; le dossier reste consultable dans la liste.');

        return Response::redirect(cooperation_mission_show_url($id));
    }

    /**
     * Suspension d’une coopération lancée : fil commun et conduite gelés, motif obligatoire.
     */
    public function suspend(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Jeton de sécurité invalide.');

            return Response::redirect($id > 0 ? cooperation_mission_show_url($id) : cooperation_mission_index_url());
        }
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if (!$this->interteamRepository->tenantCanPilotMission($id, $tenantId) || !$this->canManageInterteam()) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(cooperation_mission_index_url());
        }
        $mission = $this->interteamRepository->findById($id);
        $motive = mb_substr(trim((string) $request->input('suspend_motive', '')), 0, 500);
        $check = $mission ? CooperationTransitionRules::canSuspend($mission, $motive) : ['allowed' => false, 'reason' => 'mission_terminal'];
        if (!$check['allowed']) {
            Session::flash('error', CooperationTransitionRules::reasonLabel($check['reason']));

            return Response::redirect(cooperation_mission_show_url($id));
        }
        $this->interteamRepository->setPhase($id, 'suspended');
        $this->interteamRepository->logEvent($id, $userId, $tenantId, 'mission_suspended', ['reason' => $motive]);
        $this->cooperationAnnouncementDispatcher->dispatch(
            CooperationAnnouncementEvents::MISSION_SUSPENDED,
            $id,
            $userId,
            $tenantId,
            ['reason' => $motive]
        );
        Session::flash('success', 'Coopération suspendue : l’espace commun est en lecture seule et la conduite est gelée jusqu’à la reprise.');

        return Response::redirect(cooperation_mission_show_url($id));
    }

    public function resume(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Jeton de sécurité invalide.');

            return Response::redirect($id > 0 ? cooperation_mission_show_url($id) : cooperation_mission_index_url());
        }
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if (!$this->interteamRepository->tenantCanPilotMission($id, $tenantId) || !$this->canManageInterteam()) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(cooperation_mission_index_url());
        }
        $mission = $this->interteamRepository->findById($id);
        $check = $mission ? CooperationTransitionRules::canResume($mission) : ['allowed' => false, 'reason' => 'not_suspended', 'phase' => ''];
        if (!$check['allowed']) {
            Session::flash('error', CooperationTransitionRules::reasonLabel($check['reason']));

            return Response::redirect(cooperation_mission_show_url($id));
        }
        $this->interteamRepository->setPhase($id, $check['phase']);
        $this->interteamRepository->logEvent($id, $userId, $tenantId, 'mission_resumed', ['phase' => $check['phase']]);
        $this->cooperationAnnouncementDispatcher->dispatch(CooperationAnnouncementEvents::MISSION_RESUMED, $id, $userId, $tenantId, []);
        Session::flash('success', 'Coopération reprise : l’espace commun et la conduite sont de nouveau disponibles.');

        return Response::redirect(cooperation_mission_show_url($id));
    }

    /**
     * Relance manuelle d’une unité dont l’invitation est sans réponse (une fois par 24 h et par unité).
     */
    private function handleRemindPartner(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Jeton de sécurité invalide.');

            return Response::redirect($id > 0 ? cooperation_mission_show_url($id) : cooperation_mission_index_url());
        }
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if (!$this->interteamRepository->tenantCanPilotMission($id, $tenantId) || !$this->canManageInterteam()) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(cooperation_mission_index_url());
        }
        $mission = $this->interteamRepository->findById($id);
        if (!$mission) {
            return Response::redirect(cooperation_mission_index_url());
        }
        $targetTid = (int) $request->input('partner_tenant_id', 0);
        $participants = $this->interteamRepository->listParticipants($id);
        $last = $this->interteamRepository->lastInvitationReminderByTenant($id, 48)[$targetTid] ?? null;
        $check = CooperationTransitionRules::canRemind($mission, $targetTid, $participants, $last);
        if (!$check['allowed']) {
            Session::flash('error', CooperationTransitionRules::reasonLabel($check['reason']));

            return Response::redirect(cooperation_mission_show_url($id) . '#participants');
        }
        $this->interteamRepository->logEvent($id, $userId, $tenantId, 'invitation_reminder', ['partner_tenant_id' => $targetTid, 'manual' => true]);
        $this->cooperationAnnouncementDispatcher->dispatch(
            CooperationAnnouncementEvents::INVITATION_REMINDER,
            $id,
            $userId,
            $tenantId,
            ['invited_tenant_id' => $targetTid]
        );
        $p = CooperationTransitionRules::participantFor($targetTid, $participants);
        Session::flash('success', 'Relance envoyée à ' . (string) ($p['tenant_name'] ?? 'l’unité') . ' (responsables habilités).');

        return Response::redirect(cooperation_mission_show_url($id) . '#participants');
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
        $mission = $this->interteamRepository->findById($id);
        if (!$mission) {
            return Response::redirect(cooperation_mission_index_url());
        }
        $ready = CooperationTransitionRules::launchReadiness(
            $mission,
            $this->interteamRepository->listParticipants($id),
            $this->interteamRepository->counterProposalPending($id)
        );
        if (!$ready['ok']) {
            $msg = CooperationTransitionRules::reasonLabel($ready['reason']);
            if ($ready['reason'] === 'invitations_pending' && $ready['pending'] !== []) {
                $msg .= ' En attente : ' . implode(', ', $ready['pending']) . '.';
            }
            Session::flash('error', $msg);

            return Response::redirect($ready['reason'] === 'counter_proposal_pending'
                ? cooperation_mission_negotiate_url($id)
                : cooperation_mission_show_url($id));
        }
        $this->interteamRepository->updateMissionStatus($id, 'active');
        // Le lancement ouvre la préparation (OPORD, validation) ; la phase passe à « active » à l’exécution.
        $this->interteamRepository->setPhase(
            $id,
            CooperationTransitionRules::phaseForStage((string) ($mission['operational_stage'] ?? 'opord_draft'))
        );
        $missionRow = $this->interteamRepository->findById($id);
        if ($missionRow) {
            $hostTid = (int) ($missionRow['created_by_tenant_id'] ?? 0);
            $snap = $this->cooperationWorkflow->buildActivationSnapshot($missionRow, $hostTid);
            $this->cooperationWorkflow->persistActivationSnapshot($id, $snap);
        }
        $this->interteamRepository->logEvent($id, $userId, $tenantId, 'mission_activated', [
            'accepted' => $ready['accepted'],
            'ignored' => $ready['ignored'],
        ]);
        $this->coopForumService->ensureCooperativeSpace($id);
        $this->cooperationAnnouncementDispatcher->dispatch(CooperationAnnouncementEvents::MISSION_ACTIVATED, $id, $userId, $tenantId, []);
        Session::flash('success', 'La coopération est en cours avec ' . implode(', ', $ready['accepted']) . '. Un fil commun a été préparé sur le brief de l’unité support.'
            . ($ready['ignored'] !== [] ? ' Non engagées (refus ou retrait) : ' . implode(', ', $ready['ignored']) . '.' : ''));

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
        $missionNow = $this->interteamRepository->findById($id);
        $conduct = $missionNow ? CooperationTransitionRules::canConduct($missionNow) : ['allowed' => false, 'reason' => 'mission_terminal'];
        if (!$conduct['allowed']) {
            Session::flash('error', CooperationTransitionRules::reasonLabel($conduct['reason']));

            return Response::redirect(cooperation_mission_show_url($id));
        }
        $stage = trim((string) $request->input('operational_stage', ''));
        // Seuls les champs envoyés sont modifiés : le formulaire n’affiche en écriture que ceux de
        // l’étape courante (les autres restent en lecture), il ne doit pas effacer le reste.
        $present = static function (string ...$names) use ($request): ?string {
            foreach ($names as $n) {
                $v = $request->input($n, null);
                if ($v !== null && !is_array($v)) {
                    return trim((string) $v);
                }
            }

            return null;
        };
        $fields = [];
        foreach (['opord_text', 'aar_summary', 'command_validation_notes'] as $col) {
            $v = $present($col);
            if ($v !== null) {
                $fields[$col] = $v !== '' ? $v : null;
            }
        }
        foreach ([
            'corrective_actions_json' => ['corrective_actions_text', 'corrective_actions_json'],
            'linked_resources_json' => ['linked_resources_text', 'linked_resources_json'],
            'simulated_losses_json' => ['simulated_losses_text', 'simulated_losses_json'],
            'lessons_learned_json' => ['lessons_learned_text', 'lessons_learned_json'],
        ] as $col => $names) {
            $v = $present(...$names);
            if ($v !== null) {
                $fields[$col] = $this->notesTextToJson($v) ?? $this->normalizeJsonOrNull($v);
            }
        }

        $result = $this->interteamRepository->updateOperationalStage($id, $stage, $fields);
        if (!($result['ok'] ?? false)) {
            Session::flash('error', $this->operationalStageErrorLabel((string) ($result['error'] ?? '')));

            return Response::redirect(cooperation_mission_show_url($id));
        }
        $choices = $this->operationalStageChoices();
        $stageChanged = (string) ($missionNow['operational_stage'] ?? 'opord_draft') !== $stage;
        if ($stageChanged) {
            if ((string) ($missionNow['status'] ?? '') === 'active') {
                $this->interteamRepository->setPhase($id, CooperationTransitionRules::phaseForStage($stage));
            }
            $this->interteamRepository->logEvent($id, $userId, $tenantId, 'operational_stage_updated', ['operational_stage' => $stage]);
            $this->cooperationAnnouncementDispatcher->dispatch(
                CooperationAnnouncementEvents::OPERATIONAL_STAGE_UPDATED,
                $id,
                $userId,
                $tenantId,
                ['stage_label' => (string) ($choices[$stage] ?? $stage)]
            );
        }
        Session::flash('success', $stageChanged
            ? 'Étape de conduite : ' . (string) ($choices[$stage] ?? $stage) . '.'
            : 'Conduite enregistrée.');

        return Response::redirect(cooperation_mission_show_url($id));
    }

    private function handleAddSitrep(Request $request, array $params = []): Response
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
        $conduct = $mission ? CooperationTransitionRules::canConduct($mission) : ['allowed' => false, 'reason' => 'mission_terminal'];
        if ($mission && !$conduct['allowed']) {
            Session::flash('error', CooperationTransitionRules::reasonLabel($conduct['reason']));

            return Response::redirect(cooperation_mission_show_url($id));
        }
        if (!$mission || (string) ($mission['operational_stage'] ?? '') !== 'execution') {
            Session::flash('error', 'Les points de situation sont ouverts pendant la phase d’exécution.');

            return Response::redirect(cooperation_mission_show_url($id));
        }
        $summary = trim((string) $request->input('sitrep_summary', ''));
        if ($summary === '') {
            $this->errorField = 'sitrep_summary';
            Session::flash('error', 'Le contenu du point de situation est obligatoire.');

            return Response::redirect(cooperation_mission_show_url($id));
        }
        $occurredAt = trim((string) $request->input('sitrep_occurred_at', ''));
        $occurredAt = $this->normalizeDateTimeInput($occurredAt);
        $extraNotes = trim((string) $request->input('sitrep_notes', $request->input('sitrep_payload_json', '')));
        $payload = null;
        if ($extraNotes !== '') {
            $asJson = $this->normalizeJsonOrArray($extraNotes);
            $payload = $asJson ?? ['notes' => mb_substr($extraNotes, 0, 4000)];
        }
        $ok = $this->interteamRepository->createSitrep($id, $userId, $tenantId, $summary, $occurredAt, $payload);
        if (!$ok) {
            Session::flash('error', 'Impossible d’enregistrer le point de situation.');

            return Response::redirect(cooperation_mission_show_url($id));
        }
        $this->interteamRepository->logEvent($id, $userId, $tenantId, 'sitrep_logged', ['summary' => mb_substr($summary, 0, 220)]);
        // Facultatif : sans effet si l’administration a désactivé ses gabarits (courriel livré désactivé).
        $this->cooperationAnnouncementDispatcher->dispatch(
            CooperationAnnouncementEvents::SITREP_ADDED,
            $id,
            $userId,
            $tenantId,
            ['sitrep_summary' => $summary, 'exclude_user_id' => $userId]
        );
        Session::flash('success', 'Point de situation enregistré.');

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

    private function handleRevokeGrant(Request $request, array $params = []): Response
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
        if (!$this->interteamRepository->deleteGrant($grantId, $mid)) {
            Session::flash('error', 'Cette autorisation n’existe pas sur cette coopération (déjà retirée ?).');

            return Response::redirect(cooperation_mission_exchange_url($mid));
        }
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
        $missionRow = $this->interteamRepository->findById($id);
        $canClose = $missionRow ? CooperationTransitionRules::canClose($missionRow) : ['allowed' => false, 'reason' => 'mission_terminal'];
        if (!$canClose['allowed']) {
            Session::flash('error', CooperationTransitionRules::reasonLabel($canClose['reason']));

            return Response::redirect(cooperation_mission_archive_url($id));
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
        // Mise à jour partielle : seuls les champs présents dans le formulaire envoyé sont modifiés.
        // (La page Réunion n’envoie que le lien de compte rendu, la page Structures le reste :
        // auparavant chaque enregistrement effaçait les champs de l’autre page.)
        $limits = [
            'liaison_notes' => 20000,
            'atak_endpoint_primary' => 255,
            'atak_endpoint_partner' => 255,
            'meeting_replay_url' => 500,
            'atak_primary_label' => 160,
            'atak_partner_label' => 160,
            'atak_bascule_notes' => 20000,
            'atak_sync_status' => 32,
        ];
        $fields = [];
        foreach ($limits as $key => $max) {
            $raw = $request->input($key, null);
            if ($raw === null || is_array($raw)) {
                continue;
            }
            $val = mb_substr(trim((string) $raw), 0, $max);
            $fields[$key] = $val !== '' ? $val : null;
        }
        if ((string) $request->input('needs_submitted', '') === '1') {
            $needs = [];
            foreach (array_keys(CooperationDictionary::competencyNeedLabels()) as $nk) {
                if ($request->input('need_' . $nk) === '1' || $request->input('need_' . $nk) === 'on') {
                    $needs[] = $nk;
                }
            }
            $fields['competency_needs_json'] = $needs !== [] ? json_encode($needs, JSON_UNESCAPED_UNICODE) : null;
        }
        if ($fields === []) {
            Session::flash('error', 'Aucune information à enregistrer.');

            return Response::redirect(cooperation_mission_orbat_url($id));
        }
        $this->interteamRepository->updateMissionMeta($id, $fields);
        $this->interteamRepository->logEvent($id, (int) Session::get('user_id'), $tenantId, 'mission_meta_updated', []);
        if (array_keys($fields) === ['meeting_replay_url']) {
            Session::flash('success', 'Lien de compte rendu enregistré.');

            return Response::redirect(cooperation_mission_meeting_url($id));
        }
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
        $this->cooperationAnnouncementDispatcher->dispatch(
            CooperationAnnouncementEvents::CO_LEAD_DESIGNATED,
            $id,
            (int) Session::get('user_id'),
            $tenantId,
            ['partner_tenant_id' => $partnerTid, 'invited_tenant_id' => $partnerTid]
        );
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

    private function handleAssignMissionMember(Request $request, array $params = []): Response
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
        $roleChoices = CooperationDictionary::missionMemberRoleChoices();
        $roleLabel = $roleChoices[$roleSlug] ?? $roleSlug;
        $displayName = trim((string) ($u['display_name'] ?? $u['name'] ?? $u['email'] ?? ''));
        $this->interteamRepository->assignMissionMember($id, $targetUserId, $tenantId, $roleSlug, $actorId);
        $this->interteamRepository->logEvent($id, $actorId, $tenantId, 'mission_member_assigned', [
            'mission_member_role' => $roleSlug,
            'user_id' => $targetUserId,
        ]);
        $this->cooperationAnnouncementDispatcher->dispatch(
            CooperationAnnouncementEvents::MEMBER_DESIGNATED,
            $id,
            $actorId,
            $tenantId,
            [
                'notify_user_id' => $targetUserId,
                'role_label' => $roleLabel,
                'member_display_name' => $displayName,
            ]
        );
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
        $workspace = $this->buildMissionWorkspace($id, $tenantId, $userId) ?? [];
        // Code en attente pour cette coopération : le parcours s’ouvre directement sur l’étape 2.
        $pendingOtp = $this->emailTokenRepository->findLatestPendingForUserPurpose($userId, EmailTokenPurpose::INTERTEAM_CONSENT_OTP);
        $pendingMeta = $pendingOtp ? (json_decode((string) ($pendingOtp['metadata'] ?? ''), true) ?: []) : [];
        $otpSentAt = ($pendingOtp && (int) ($pendingMeta['mission_id'] ?? 0) === $id) ? strtotime((string) $pendingOtp['created_at']) : false;
        $consentStatus = $this->interteamRepository->consentStatus($id, $userId);
        $forceStep1 = (string) $request->query('etape', '') === '1';

        return Response::view('layout.main', $workspace + [
            'content' => 'back_office.cooperation.missions.consent',
            'title' => 'Autorisation de partage',
            'interteamMission' => $mission,
            'interteamConsentReturn' => $return,
            'cooperationMissionNavActive' => 'consent',
            'cooperationSuggestedShareKeys' => CooperationConsentDefaults::suggestedKeysForTypology($typo !== '' ? $typo : null),
            'cooperationConsentStatus' => $consentStatus,
            'cooperationConsentStep' => ($otpSentAt !== false && !$forceStep1) ? 2 : 1,
            'cooperationConsentResendIn' => $otpSentAt !== false ? max(0, self::OTP_RESEND_SEC - (time() - $otpSentAt)) : 0,
            'cooperationConsentTtlHours' => CooperationConsentDefaults::consentTtlHours(),
            'cooperationConsentOtpTtlMinutes' => self::OTP_TTL_MIN,
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
        // Seules les unités engagées (participation confirmée) demandent un code.
        $engaged = false;
        foreach ($this->interteamRepository->listParticipants($id) as $p) {
            if ((int) ($p['tenant_id'] ?? 0) === $tenantId && ($p['status'] ?? '') === 'active') {
                $engaged = true;
            }
        }
        if (!$engaged) {
            Session::flash('error', 'Accès refusé.');

            return Response::redirect(cooperation_mission_index_url());
        }
        $resend = (string) $request->input('resend', '') === '1';
        $previous = $this->interteamRepository->consentStatus($id, $userId);
        $keys = $resend ? $previous['keys'] : $this->consentKeysFromRequest($request);
        if ($keys === []) {
            Session::flash('error', 'Cochez au moins une famille de données pour continuer.');

            return Response::redirect(cooperation_mission_consent_url($id) . $this->consentReturnQuery($request, ['etape' => '1']));
        }
        $justification = $resend
            ? trim($previous['justification'])
            : mb_substr(trim((string) $request->input('justification_sensitive', '')), 0, 4000);
        $sensitive = array_values(array_filter($keys, [CooperationDictionary::class, 'isSensitiveDataFamily']));
        if ($sensitive !== [] && mb_strlen($justification) < 10) {
            Session::flash('error', 'Vous partagez des données sensibles (' . implode(', ', array_map([CooperationDictionary::class, 'dataSharingFamilyLabel'], $sensitive)) . ') : justifiez ce partage en quelques mots (10 caractères au moins).');

            return Response::redirect(cooperation_mission_consent_url($id) . $this->consentReturnQuery($request, ['etape' => '1']));
        }
        if (!$resend) {
            $this->interteamRepository->upsertConsentDraft($id, $userId, $tenantId, $keys);
            $this->interteamRepository->updateConsentJustification($id, $userId, $sensitive !== [] ? $justification : null);
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

    /** @param array<string, string> $extra */
    private function consentReturnQuery(Request $request, array $extra = []): string
    {
        $q = $extra;
        $return = trim((string) $request->input('return', ''));
        if ($return !== '' && str_starts_with($return, '/') && !str_starts_with($return, '//')) {
            $q['return'] = $return;
        }

        return $q !== [] ? '?' . http_build_query($q) : '';
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
        $after = $this->interteamRepository->consentStatus($id, $userId);
        $untilTs = $after['until'] !== null ? strtotime($after['until']) : false;
        Session::flash('success', 'Autorisation confirmée' . ($untilTs !== false ? ' jusqu’au ' . date('d/m/Y à H:i', $untilTs) : '') . '. Vous pouvez accéder aux échanges partagés.');
        $return = trim((string) $request->input('return', ''));
        if ($return !== '' && str_starts_with($return, '/')) {
            return Response::redirect(url(ltrim($return, '/')));
        }

        return Response::redirect(cooperation_mission_show_url($id));
    }

    /** @return list<string> */
    private function consentKeysFromRequest(Request $request): array
    {
        $allowed = CooperationDictionary::dataSharingFamilyKeys();
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
            Session::flash('error', 'Connectez-vous pour continuer.');

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
        return CooperationAccess::canManage();
    }

    private function canRespondInterteam(): bool
    {
        return CooperationAccess::canRespond();
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
