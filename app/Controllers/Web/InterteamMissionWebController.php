<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\ForumTopicRepository;
use App\Repositories\InterteamMissionRepository;
use App\Repositories\TenantRepository;

class InterteamMissionWebController
{
    public function __construct(
        private InterteamMissionRepository $interteamRepository,
        private TenantRepository $tenantRepository,
        private ForumTopicRepository $topicRepository
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
            'content' => 'admin.interteam_missions.index',
            'title' => 'Missions inter-unités',
            'interteamMissions' => $missions,
            'csrfToken' => Csrf::token(),
        ]);
    }

    public function create(Request $request, array $params = []): Response
    {
        if (!$this->assertInterteamAccess($request)) {
            return Response::redirect(url('dashboard'));
        }

        return Response::view('layout.main', [
            'content' => 'admin.interteam_missions.create',
            'title' => 'Nouvelle mission inter-unités',
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

            return Response::redirect(url('admin/interteam-missions/create'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        $title = trim((string) $request->input('title', ''));
        if (strlen($title) < 3 || strlen($title) > 255) {
            Session::flash('error', 'Le titre doit faire entre 3 et 255 caractères.');

            return Response::redirect(url('admin/interteam-missions/create'));
        }
        $slug = $this->uniqueSlugFromTitle($title);
        $id = $this->interteamRepository->createMission($title, $slug, $tenantId, $userId);
        Session::flash('success', 'Mission créée. Ajoutez des unités partenaires puis activez-la lorsque tout le monde a accepté.');

        return Response::redirect(url('admin/interteam-missions/' . $id));
    }

    public function show(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId <= 0 || $userId <= 0) {
            Session::flash('error', 'Authentification requise.');

            return Response::redirect(url('login'));
        }
        if (!$this->interteamRepository->tableExists()) {
            return Response::redirect(url('dashboard'));
        }
        $id = (int) ($params['id'] ?? 0);
        $mission = $this->interteamRepository->findById($id);
        if (!$mission) {
            Session::flash('error', 'Mission introuvable.');

            return Response::redirect(url('admin/interteam-missions'));
        }
        $participants = $this->interteamRepository->listParticipants($id);
        $inMission = false;
        foreach ($participants as $p) {
            if ((int) ($p['tenant_id'] ?? 0) === $tenantId) {
                $inMission = true;
                break;
            }
        }
        if (!$inMission) {
            Session::flash('error', 'Accès refusé.');

            return Response::redirect(url('admin/interteam-missions'));
        }

        $isLead = $this->interteamRepository->tenantIsLead($id, $tenantId);
        $canManage = $isLead && $this->canManageInterteam();
        $canRespond = $this->canRespondInterteam();
        $partnerPicker = $canManage ? $this->tenantRepository->listBasicExcluding($tenantId) : [];
        $grants = $canManage && ($mission['status'] ?? '') === 'active'
            ? $this->interteamRepository->listGrantsForMission($id)
            : [];

        return Response::view('layout.main', [
            'content' => 'admin.interteam_missions.show',
            'title' => (string) ($mission['title'] ?? 'Mission'),
            'interteamMission' => $mission,
            'interteamParticipants' => $participants,
            'interteamGrants' => $grants,
            'interteamIsLead' => $isLead,
            'interteamCanManage' => $canManage,
            'interteamCanRespond' => $canRespond,
            'interteamPartnerPicker' => $partnerPicker,
            'csrfToken' => Csrf::token(),
            'sessionTenantId' => $tenantId,
        ]);
    }

    public function invite(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Jeton de sécurité invalide.');
            $id = (int) ($params['id'] ?? 0);

            return Response::redirect($id > 0 ? url('admin/interteam-missions/' . $id) : url('admin/interteam-missions'));
        }
        $id = (int) ($params['id'] ?? 0);
        $tenantId = (int) Session::get('tenant_id');
        if (!$this->interteamRepository->tenantIsLead($id, $tenantId) || !$this->canManageInterteam()) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('admin/interteam-missions'));
        }
        $partnerId = (int) $request->input('partner_tenant_id', 0);
        if ($partnerId <= 0 || $partnerId === $tenantId) {
            Session::flash('error', 'Unité partenaire invalide.');

            return Response::redirect(url('admin/interteam-missions/' . $id));
        }
        $this->interteamRepository->invitePartner($id, $partnerId);
        Session::flash('success', 'Invitation enregistrée. L’autre unité peut accepter depuis sa propre administration.');

        return Response::redirect(url('admin/interteam-missions/' . $id));
    }

    public function accept(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Jeton de sécurité invalide.');
            $id = (int) ($params['id'] ?? 0);

            return Response::redirect($id > 0 ? url('admin/interteam-missions/' . $id) : url('admin/interteam-missions'));
        }
        $id = (int) ($params['id'] ?? 0);
        $tenantId = (int) Session::get('tenant_id');
        if (!$this->canRespondInterteam()) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('admin/interteam-missions/' . $id));
        }
        $this->interteamRepository->setParticipantStatus($id, $tenantId, 'active');
        Session::flash('success', 'Participation confirmée.');

        return Response::redirect(url('admin/interteam-missions/' . $id));
    }

    public function decline(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Jeton de sécurité invalide.');
            $id = (int) ($params['id'] ?? 0);

            return Response::redirect($id > 0 ? url('admin/interteam-missions/' . $id) : url('admin/interteam-missions'));
        }
        $id = (int) ($params['id'] ?? 0);
        $tenantId = (int) Session::get('tenant_id');
        if (!$this->canRespondInterteam()) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('admin/interteam-missions/' . $id));
        }
        $this->interteamRepository->setParticipantStatus($id, $tenantId, 'declined');
        Session::flash('success', 'Invitation refusée.');

        return Response::redirect(url('admin/interteam-missions'));
    }

    public function activate(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Jeton de sécurité invalide.');
            $id = (int) ($params['id'] ?? 0);

            return Response::redirect($id > 0 ? url('admin/interteam-missions/' . $id) : url('admin/interteam-missions'));
        }
        $id = (int) ($params['id'] ?? 0);
        $tenantId = (int) Session::get('tenant_id');
        if (!$this->interteamRepository->tenantIsLead($id, $tenantId) || !$this->canManageInterteam()) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('admin/interteam-missions'));
        }
        if (!$this->interteamRepository->hasPartnerInvited($id)) {
            Session::flash('error', 'Ajoutez au moins une unité partenaire avant d’activer la mission.');

            return Response::redirect(url('admin/interteam-missions/' . $id));
        }
        if (!$this->interteamRepository->allPartnersAccepted($id)) {
            Session::flash('error', 'Toutes les unités invitées doivent d’abord accepter la mission.');

            return Response::redirect(url('admin/interteam-missions/' . $id));
        }
        $this->interteamRepository->updateMissionStatus($id, 'active');
        Session::flash('success', 'La mission est opérationnelle. Vous pouvez partager des sujets du brief avec les unités partenaires.');

        return Response::redirect(url('admin/interteam-missions/' . $id));
    }

    public function grantTopic(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Jeton de sécurité invalide.');
            $id = (int) ($params['id'] ?? 0);

            return Response::redirect($id > 0 ? url('admin/interteam-missions/' . $id) : url('admin/interteam-missions'));
        }
        $id = (int) ($params['id'] ?? 0);
        $tenantId = (int) Session::get('tenant_id');
        $mission = $this->interteamRepository->findById($id);
        if (!$mission || ($mission['status'] ?? '') !== 'active' || !$this->interteamRepository->tenantIsLead($id, $tenantId) || !$this->canManageInterteam()) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('admin/interteam-missions/' . $id));
        }
        $topicId = (int) $request->input('topic_id', 0);
        $consumerId = (int) $request->input('consumer_tenant_id', 0);
        if ($topicId <= 0 || $consumerId <= 0 || $consumerId === $tenantId) {
            Session::flash('error', 'Sujet ou unité destinataire invalide.');

            return Response::redirect(url('admin/interteam-missions/' . $id));
        }
        $topic = $this->topicRepository->findById($topicId, $tenantId);
        if (!$topic) {
            Session::flash('error', 'Ce sujet n’existe pas dans votre brief ou n’appartient pas à votre unité.');

            return Response::redirect(url('admin/interteam-missions/' . $id));
        }
        $this->interteamRepository->addForumGrant($id, 'topic', $topicId, $tenantId, $consumerId);
        Session::flash('success', 'Partage enregistré. Les membres de l’unité destinataire verront le sujet dans leur brief (section coopération).');

        return Response::redirect(url('admin/interteam-missions/' . $id));
    }

    public function revokeGrant(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Jeton de sécurité invalide.');
            $mid = (int) ($params['id'] ?? 0);

            return Response::redirect($mid > 0 ? url('admin/interteam-missions/' . $mid) : url('admin/interteam-missions'));
        }
        $mid = (int) ($params['id'] ?? 0);
        $grantId = (int) ($params['grantId'] ?? 0);
        $tenantId = (int) Session::get('tenant_id');
        if (!$this->interteamRepository->tenantIsLead($mid, $tenantId) || !$this->canManageInterteam() || $grantId <= 0) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('admin/interteam-missions/' . $mid));
        }
        $this->interteamRepository->deleteGrant($grantId);
        Session::flash('success', 'Partage retiré.');

        return Response::redirect(url('admin/interteam-missions/' . $mid));
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
        if (!$this->canManageInterteam()) {
            Session::flash('error', 'Accès réservé aux personnes habilitées à piloter les missions inter-unités.');

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
