<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AdvancementRepository;
use App\Repositories\QualificationDefinitionRepository;
use App\Services\Advancement\AdvancementService;
use Throwable;

final class AdvancementController
{
    public function __construct(
        private AdvancementRepository $repository,
        private AdvancementService $service,
        private QualificationDefinitionRepository $qualifications,
    ) {}

    public function grades(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }

        return $this->view('admin.organization.advancement.grades', 'Échelle de grades', [
            'grades' => $this->repository->listGrades($tenantId, true),
            'filieres' => $this->repository->listFilieres($tenantId, true),
            'qualifications' => $this->qualifications->listForTenant($tenantId),
        ]);
    }

    public function saveGrade(Request $request, array $params = []): Response
    {
        if (!$this->validPost($request)) {
            return $this->redirectGrades('error', 'Session expirée.');
        }
        try {
            $this->repository->saveGrade($this->tenantId(), [
                'code' => $request->input('code'),
                'label' => $request->input('label'),
                'short_label' => $request->input('short_label'),
                'filiere_id' => $request->input('filiere_id'),
                'rank_order' => $request->input('rank_order'),
                'advancement_seniority_enabled' => $request->input('advancement_seniority_enabled'),
                'advancement_choice_enabled' => $request->input('advancement_choice_enabled'),
                'min_time_in_previous_grade_months' => $request->input('min_time_in_previous_grade_months'),
                'required_qualification_id' => $request->input('required_qualification_id'),
                'required_qualification_level_id' => $request->input('required_qualification_level_id'),
            ], isset($params['id']) ? (int) $params['id'] : null);

            return $this->redirectGrades('success', 'Grade enregistré.');
        } catch (Throwable $e) {
            return $this->redirectGrades('error', $e->getMessage());
        }
    }

    public function archiveGrade(Request $request, array $params = []): Response
    {
        if (!$this->validPost($request)) {
            return $this->redirectGrades('error', 'Session expirée.');
        }
        $this->repository->archiveGrade($this->tenantId(), (int) ($params['id'] ?? 0));

        return $this->redirectGrades('success', 'Grade archivé. Les historiques sont conservés.');
    }

    public function saveFiliere(Request $request, array $params = []): Response
    {
        if (!$this->validPost($request)) {
            return $this->redirectGrades('error', 'Session expirée.');
        }
        try {
            $this->repository->saveFiliere($this->tenantId(), [
                'code' => $request->input('code'),
                'label' => $request->input('label'),
                'sort_order' => $request->input('sort_order'),
            ], isset($params['id']) ? (int) $params['id'] : null);

            return $this->redirectGrades('success', 'Filière enregistrée.');
        } catch (Throwable $e) {
            return $this->redirectGrades('error', $e->getMessage());
        }
    }

    public function campaigns(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }

        return $this->view('admin.organization.advancement.campaigns', 'Campagnes d’avancement', [
            'campaigns' => $this->repository->listCampaigns($tenantId),
            'grades' => array_values(array_filter(
                $this->repository->listGrades($tenantId),
                static fn (array $g): bool => !empty($g['advancement_choice_enabled'])
            )),
            'filieres' => $this->repository->listFilieres($tenantId),
        ]);
    }

    public function createCampaign(Request $request, array $params = []): Response
    {
        if (!$this->validPost($request)) {
            return $this->redirectCampaigns('error', 'Session expirée.');
        }
        try {
            $opens = trim((string) $request->input('opens_at', ''));
            $closes = trim((string) $request->input('closes_at', ''));
            if ($opens === '' || $closes === '' || $closes < $opens) {
                throw new \RuntimeException('La fenêtre de candidature est invalide.');
            }
            $id = $this->repository->createCampaign($this->tenantId(), [
                'grade_id' => $request->input('grade_id'),
                'filiere_id' => $request->input('filiere_id'),
                'year' => $request->input('year', date('Y')),
                'opens_at' => $opens,
                'closes_at' => $closes,
                'quota_slots' => $request->input('quota_slots'),
                'status' => 'draft',
            ], $this->actorId());
            Session::flash('success', 'Campagne créée.');

            return Response::redirect(url('back-office/rh/avancement/campagnes/' . $id));
        } catch (Throwable $e) {
            return $this->redirectCampaigns('error', $e->getMessage());
        }
    }

    public function campaign(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        $id = (int) ($params['id'] ?? 0);
        $campaign = $this->repository->findCampaign($tenantId, $id);
        if ($campaign === null) {
            return $this->redirectCampaigns('error', 'Campagne introuvable.');
        }

        return $this->view('admin.organization.advancement.campaign', 'Commission d’avancement', [
            'campaign' => $campaign,
            'candidacies' => $this->repository->listCandidacies($tenantId, $id),
        ]);
    }

    public function openCampaign(Request $request, array $params = []): Response
    {
        return $this->transition($request, $params, 'draft', 'open', 'Campagne ouverte.');
    }

    public function commission(Request $request, array $params = []): Response
    {
        return $this->transition($request, $params, 'open', 'commission', 'Candidatures verrouillées. La commission est ouverte.');
    }

    public function decide(Request $request, array $params = []): Response
    {
        $campaignId = (int) ($params['id'] ?? 0);
        if (!$this->validPost($request)) {
            return $this->redirectCampaign($campaignId, 'error', 'Session expirée.');
        }
        $decision = trim((string) $request->input('decision', ''));
        if (!in_array($decision, ['', 'registered', 'not_registered'], true)) {
            return $this->redirectCampaign($campaignId, 'error', 'Décision invalide.');
        }
        $this->repository->decideCandidacy($this->tenantId(), (int) ($params['candidacyId'] ?? 0), [
            'preference_rank' => $request->input('preference_rank'),
            'commission_opinion' => $request->input('commission_opinion'),
            'decision' => $decision,
            'notes' => $request->input('notes'),
        ]);

        return $this->redirectCampaign($campaignId, 'success', 'Décision enregistrée.');
    }

    public function recheck(Request $request, array $params = []): Response
    {
        $campaignId = (int) ($params['id'] ?? 0);
        if (!$this->validPost($request)) {
            return $this->redirectCampaign($campaignId, 'error', 'Session expirée.');
        }
        try {
            $result = $this->service->recheck($this->tenantId(), (int) ($params['candidacyId'] ?? 0));

            return $this->redirectCampaign(
                $campaignId,
                $result['is_eligible'] ? 'success' : 'error',
                $result['is_eligible'] ? 'Éligibilité confirmée.' : (string) $result['eligibility_reason']
            );
        } catch (Throwable $e) {
            return $this->redirectCampaign($campaignId, 'error', $e->getMessage());
        }
    }

    public function publish(Request $request, array $params = []): Response
    {
        $campaignId = (int) ($params['id'] ?? 0);
        if (!$this->validPost($request)) {
            return $this->redirectCampaign($campaignId, 'error', 'Session expirée.');
        }
        try {
            $count = $this->service->publish($this->tenantId(), $campaignId, $this->actorId());

            return $this->redirectCampaign($campaignId, 'success', $count . ' avancement(s) publié(s).');
        } catch (Throwable $e) {
            return $this->redirectCampaign($campaignId, 'error', $e->getMessage());
        }
    }

    private function transition(Request $request, array $params, string $from, string $to, string $message): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if (!$this->validPost($request)) {
            return $this->redirectCampaign($id, 'error', 'Session expirée.');
        }
        if (!$this->repository->updateCampaignStatus($this->tenantId(), $id, $from, $to)) {
            return $this->redirectCampaign($id, 'error', 'Transition de campagne impossible.');
        }

        return $this->redirectCampaign($id, 'success', $message);
    }

    private function view(string $content, string $title, array $data): Response
    {
        return Response::view('layout.main', array_merge($data, [
            'content' => $content,
            'title' => $title,
            'boPageTitle' => $title,
            'boPageKicker' => 'ADMINISTRATION · RH',
            'boPageSubtitle' => 'Référentiel, éligibilité et décisions sans écrasement de l’historique.',
            'backOfficePageCss' => ['advancement.css'],
        ]));
    }

    private function validPost(Request $request): bool
    {
        return $this->tenantId() > 0 && $request->isPost() && Csrf::validate($request);
    }

    private function tenantId(): int
    {
        return (int) Session::get('tenant_id');
    }

    private function actorId(): ?int
    {
        $id = (int) Session::get('user_id');

        return $id > 0 ? $id : null;
    }

    private function redirectGrades(string $kind, string $message): Response
    {
        Session::flash($kind, $message);

        return Response::redirect(url('back-office/organisation/grades'));
    }

    private function redirectCampaigns(string $kind, string $message): Response
    {
        Session::flash($kind, $message);

        return Response::redirect(url('back-office/rh/avancement'));
    }

    private function redirectCampaign(int $id, string $kind, string $message): Response
    {
        Session::flash($kind, $message);

        return Response::redirect(url('back-office/rh/avancement/campagnes/' . $id));
    }
}
