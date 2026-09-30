<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AdvancementCampaignRepository;
use App\Repositories\AdvancementCandidacyRepository;
use App\Repositories\AdvancementCommissionRepository;
use App\Repositories\GradeDefinitionRepository;
use App\Repositories\GradeFiliereDefinitionRepository;
use App\Repositories\OrbatBilletRepository;
use App\Repositories\UserRepository;
use App\Services\Personnel\AdvancementEligibilityService;
use App\Services\Personnel\AdvancementPublicationService;
use App\Support\AdvancementCodes;
use DateTimeImmutable;
use RuntimeException;

final class AdvancementCampaignController
{
    public function __construct(
        private AdvancementCampaignRepository $campaigns,
        private AdvancementCandidacyRepository $candidacies,
        private AdvancementCommissionRepository $commissions,
        private GradeDefinitionRepository $grades,
        private GradeFiliereDefinitionRepository $filieres,
        private AdvancementEligibilityService $eligibility,
        private AdvancementPublicationService $publication,
        private OrbatBilletRepository $billets,
        private UserRepository $users,
    ) {
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantIdOrRedirect();
        if ($tenantId instanceof Response) {
            return $tenantId;
        }

        return Response::view('layout.main', [
            'content' => 'admin.organization.advancement.campaigns_index',
            'title' => 'Avancement',
            'isBackOfficeShell' => true,
            'boPageTitle' => 'Campagnes d’avancement',
            'boPageKicker' => 'RH · AVANCEMENT',
            'boPageSubtitle' => 'Voie au choix : candidatures, commission et publication du tableau.',
            'campaigns' => $this->campaigns->listForTenant($tenantId),
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
        ]);
    }

    public function create(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantIdOrRedirect();
        if ($tenantId instanceof Response) {
            return $tenantId;
        }

        return Response::view('layout.main', [
            'content' => 'admin.organization.advancement.campaign_form',
            'title' => 'Nouvelle campagne',
            'isBackOfficeShell' => true,
            'boPageTitle' => 'Nouvelle campagne',
            'boPageKicker' => 'RH · AVANCEMENT',
            'grades' => $this->grades->listForTenant($tenantId),
            'filieres' => $this->filieres->listForTenant($tenantId),
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        $tenantId = $this->requirePost($request, 'back-office/rh/avancement/create');
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $gradeId = (int) $request->input('grade_id', 0);
        if ($gradeId < 1) {
            Session::flash('error', 'Sélectionnez le grade visé.');

            return Response::redirect(url('back-office/rh/avancement/create'));
        }
        $id = $this->campaigns->create($tenantId, [
            'grade_id' => $gradeId,
            'filiere_id' => (int) $request->input('filiere_id', 0),
            'year' => (int) $request->input('year', date('Y')),
            'opens_at' => $request->input('opens_at'),
            'closes_at' => $request->input('closes_at'),
            'quota_slots' => $request->input('quota_slots'),
            'notes' => $request->input('notes'),
        ], (int) Session::get('user_id'));
        Session::flash('success', 'Campagne ouverte. Les personnels éligibles peuvent se porter volontaires.');

        return Response::redirect(url('back-office/rh/avancement/' . $id));
    }

    public function show(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantIdOrRedirect();
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $id = (int) ($params['id'] ?? 0);
        $campaign = $this->campaigns->find($tenantId, $id);
        if ($campaign === null) {
            Session::flash('error', 'Campagne introuvable.');

            return Response::redirect(url('back-office/rh/avancement'));
        }
        $rows = $this->candidacies->listForCampaign($id);
        $now = new DateTimeImmutable('today');
        foreach ($rows as &$row) {
            $eval = $this->eligibility->evaluate($tenantId, (int) $row['personnel_id'], (int) $campaign['grade_id'], $now);
            $row['live_eligible'] = $eval['is_eligible'];
            $row['live_reason'] = $eval['eligibility_reason'];
            $row['months_in_grade'] = $eval['months_in_grade'];
        }
        unset($row);
        $commission = $this->commissions->findByCampaign($id);
        $members = $commission ? $this->commissions->listMembers((int) $commission['id']) : [];

        return Response::view('layout.main', [
            'content' => 'admin.organization.advancement.campaign_show',
            'title' => 'Candidatures',
            'isBackOfficeShell' => true,
            'boPageTitle' => 'Candidatures — ' . (string) ($campaign['grade_label'] ?? ''),
            'boPageKicker' => 'RH · AVANCEMENT',
            'campaign' => $campaign,
            'candidacies' => $rows,
            'commission' => $commission,
            'commissionMembers' => $members,
            'billets' => $this->billets->schemaReady() ? $this->billets->listActiveForTenant($tenantId) : [],
            'members' => $this->users->listForTenant($tenantId, null, 'active', null, 200, 0, true),
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
        ]);
    }

    public function addCandidacy(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $tenantId = $this->requirePost($request, 'back-office/rh/avancement/' . $id);
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $campaign = $this->campaigns->find($tenantId, $id);
        if ($campaign === null || (string) $campaign['status'] !== AdvancementCodes::CAMPAIGN_OPEN) {
            Session::flash('error', 'Campagne non ouverte.');

            return Response::redirect(url('back-office/rh/avancement/' . $id));
        }
        $personnelId = (int) $request->input('personnel_id', 0);
        if ($personnelId < 1) {
            Session::flash('error', 'Sélectionnez un personnel.');

            return Response::redirect(url('back-office/rh/avancement/' . $id));
        }
        if ($this->candidacies->findForCampaignPersonnel($id, $personnelId) !== null) {
            Session::flash('error', 'Une candidature existe déjà pour ce personnel.');

            return Response::redirect(url('back-office/rh/avancement/' . $id));
        }
        $eval = $this->eligibility->evaluate($tenantId, $personnelId, (int) $campaign['grade_id']);
        $this->candidacies->create($id, $personnelId, [
            'is_eligible' => $eval['is_eligible'],
            'eligibility_reason' => $eval['eligibility_reason'],
            'mobility_requested' => $request->input('mobility_requested') ? 1 : 0,
            'requested_billet_id' => (int) $request->input('requested_billet_id', 0),
            'notes' => $request->input('notes'),
        ]);
        Session::flash('success', 'Candidature enregistrée.');

        return Response::redirect(url('back-office/rh/avancement/' . $id));
    }

    public function recheck(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $tenantId = $this->requirePost($request, 'back-office/rh/avancement/' . $id);
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $campaign = $this->campaigns->find($tenantId, $id);
        if ($campaign === null) {
            return Response::redirect(url('back-office/rh/avancement'));
        }
        $now = new DateTimeImmutable('today');
        foreach ($this->candidacies->listForCampaign($id) as $row) {
            $eval = $this->eligibility->evaluate($tenantId, (int) $row['personnel_id'], (int) $campaign['grade_id'], $now);
            $this->candidacies->updateEligibility((int) $row['id'], (bool) $eval['is_eligible'], $eval['eligibility_reason']);
        }
        Session::flash('success', 'Éligibilité recalculée pour toutes les candidatures.');

        return Response::redirect(url('back-office/rh/avancement/' . $id));
    }

    public function toCommission(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $tenantId = $this->requirePost($request, 'back-office/rh/avancement/' . $id);
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $this->campaigns->setStatus($tenantId, $id, AdvancementCodes::CAMPAIGN_IN_COMMISSION);
        $meeting = trim((string) $request->input('meeting_date', ''));
        $this->commissions->upsertForCampaign($id, $meeting !== '' ? $meeting : date('Y-m-d'), null);
        Session::flash('success', 'Candidatures verrouillées. Commission ouverte.');

        return Response::redirect(url('back-office/rh/avancement/' . $id . '/commission'));
    }

    public function commission(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantIdOrRedirect();
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $id = (int) ($params['id'] ?? 0);
        $campaign = $this->campaigns->find($tenantId, $id);
        if ($campaign === null) {
            return Response::redirect(url('back-office/rh/avancement'));
        }
        $commission = $this->commissions->findByCampaign($id)
            ?? ['id' => $this->commissions->upsertForCampaign($id, date('Y-m-d'), null)];
        $rows = $this->candidacies->listForCampaign($id);
        $now = new DateTimeImmutable('today');
        foreach ($rows as &$row) {
            $eval = $this->eligibility->evaluate($tenantId, (int) $row['personnel_id'], (int) $campaign['grade_id'], $now);
            $row['eval'] = $eval;
        }
        unset($row);

        return Response::view('layout.main', [
            'content' => 'admin.organization.advancement.commission',
            'title' => 'Commission d’avancement',
            'isBackOfficeShell' => true,
            'boPageTitle' => 'Commission — ' . (string) ($campaign['grade_label'] ?? ''),
            'boPageKicker' => 'RH · COMMISSION',
            'campaign' => $campaign,
            'commission' => $this->commissions->findByCampaign($id) ?? $commission,
            'commissionMembers' => $this->commissions->listMembers((int) ($commission['id'] ?? 0)),
            'candidacies' => $rows,
            'members' => $this->users->listForTenant($tenantId, null, 'active', null, 200, 0, true),
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
        ]);
    }

    public function saveCommissionRow(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $tenantId = $this->requirePost($request, 'back-office/rh/avancement/' . $id . '/commission');
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $campaign = $this->campaigns->find($tenantId, $id);
        if ($campaign === null || (string) $campaign['status'] === AdvancementCodes::CAMPAIGN_PUBLISHED) {
            Session::flash('error', 'Décisions figées : tableau déjà publié.');

            return Response::redirect(url('back-office/rh/avancement/' . $id . '/commission'));
        }
        $cid = (int) $request->input('candidacy_id', 0);
        $opinion = trim((string) $request->input('commission_opinion', ''));
        $decision = trim((string) $request->input('decision', ''));
        $rank = trim((string) $request->input('preference_rank', ''));
        if ($opinion === AdvancementCodes::OPINION_PROPOSED || $opinion === AdvancementCodes::OPINION_NOT_PROPOSED) {
            $this->candidacies->updateOpinion($cid, $opinion);
        }
        if ($decision === AdvancementCodes::DECISION_INSCRIBED || $decision === AdvancementCodes::DECISION_NOT_INSCRIBED) {
            $this->candidacies->updateDecision($cid, $decision, date('Y-m-d'));
        }
        $this->candidacies->updatePreferenceRank($cid, $rank !== '' ? (int) $rank : null);
        Session::flash('success', 'Avis enregistré.');

        return Response::redirect(url('back-office/rh/avancement/' . $id . '/commission'));
    }

    public function addCommissionMember(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $tenantId = $this->requirePost($request, 'back-office/rh/avancement/' . $id . '/commission');
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $commission = $this->commissions->findByCampaign($id);
        if ($commission === null) {
            return Response::redirect(url('back-office/rh/avancement/' . $id . '/commission'));
        }
        $role = (string) $request->input('role', AdvancementCodes::MEMBER_TITULAR);
        if ($role !== AdvancementCodes::MEMBER_DEPUTY) {
            $role = AdvancementCodes::MEMBER_TITULAR;
        }
        $this->commissions->addMember((int) $commission['id'], (int) $request->input('personnel_id', 0), $role);
        Session::flash('success', 'Membre ajouté à la commission.');

        return Response::redirect(url('back-office/rh/avancement/' . $id . '/commission'));
    }

    public function publish(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $tenantId = $this->requirePost($request, 'back-office/rh/avancement/' . $id . '/commission');
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        try {
            $out = $this->publication->publish(
                $tenantId,
                $id,
                (int) Session::get('user_id'),
                new DateTimeImmutable('today')
            );
            Session::flash(
                'success',
                'Tableau publié. ' . $out['promoted'] . ' grade(s) attribué(s). Action irréversible.'
            );
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        return Response::redirect(url('back-office/rh/avancement/' . $id . '/commission'));
    }

    private function tenantIdOrRedirect(): int|Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId <= 0) {
            return Response::redirect(url('login'));
        }

        return $tenantId;
    }

    private function requirePost(Request $request, string $fallback): int|Response
    {
        $tenantId = $this->tenantIdOrRedirect();
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url($fallback));
        }

        return $tenantId;
    }
}
