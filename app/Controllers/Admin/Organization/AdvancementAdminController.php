<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AdvancementRepository;
use App\Services\Advancement\AdvancementWorkflowService;
use App\Services\Advancement\GradeScaleTemplateService;
use RuntimeException;
use Throwable;

/**
 * Administration de l'échelle de grades et des campagnes d'avancement.
 */
final class AdvancementAdminController
{
    public function __construct(
        private AdvancementRepository $repository,
        private AdvancementWorkflowService $workflow,
        private GradeScaleTemplateService $templates,
    ) {
    }

    public function grades(Request $request, array $params = []): Response
    {
        $ctx = $this->requireAdmin();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId] = $ctx;
        if (!$this->repository->tablesReady()) {
            return $this->page('Grades', 'ORGANISATION · GRADES', 'L’échelle de grades n’est pas encore installée.', 'admin.advancement.unavailable', []);
        }
        $before = count($this->repository->listGrades($tenantId, true));
        $added = $this->templates->completeForTenant($tenantId);
        if ($added > 0) {
            $message = $before === 0
                ? 'Échelle initialisée pour cette communauté (' . $added . ' grades).'
                : $added . ' grade(s) manquant(s) ajouté(s) pour compléter l’échelle.';
            Session::flash('success', $message);
        }

        return $this->page('Grades', 'ORGANISATION · GRADES', 'Échelle de grades de la communauté. Un grade déjà attribué s’archive, il ne se supprime pas.', 'admin.advancement.grades_index', [
            'grades' => $this->repository->listGrades($tenantId, true),
            'filieres' => $this->repository->listFilieres($tenantId),
            'templates' => $this->templates->templates(),
            'personnel' => $this->repository->listPersonnel($tenantId),
            'gradeOrder' => $this->workflow->detectGradeOrder($tenantId),
        ]);
    }

    public function gradeCreate(Request $request, array $params = []): Response
    {
        $ctx = $this->requireAdmin();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId] = $ctx;

        return $this->page('Nouveau grade', 'ORGANISATION · GRADES', 'Le code reste stable même si le libellé change.', 'admin.advancement.grade_form', [
            'grade' => null,
            'filieres' => $this->repository->listFilieres($tenantId),
            'qualifications' => $this->repository->listQualifications($tenantId),
        ]);
    }

    public function gradeStore(Request $request, array $params = []): Response
    {
        $ctx = $this->guardPost('back-office/organisation/grades');
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId] = $ctx;
        try {
            $this->repository->saveGrade($tenantId, $this->gradePayload($request, $tenantId));
            Session::flash('success', 'Grade créé.');
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }

        return Response::redirect(url('back-office/organisation/grades'));
    }

    public function gradeEdit(Request $request, array $params = []): Response
    {
        $ctx = $this->requireAdmin();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId] = $ctx;
        $grade = $this->repository->findGrade((int) ($params['id'] ?? 0), $tenantId);
        if ($grade === null) {
            Session::flash('error', 'Grade introuvable.');

            return Response::redirect(url('back-office/organisation/grades'));
        }

        return $this->page('Modifier le grade', 'ORGANISATION · GRADES', 'Le code reste stable même si le libellé change.', 'admin.advancement.grade_form', [
            'grade' => $grade,
            'filieres' => $this->repository->listFilieres($tenantId),
            'qualifications' => $this->repository->listQualifications($tenantId),
        ]);
    }

    public function gradeUpdate(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $ctx = $this->guardPost('back-office/organisation/grades/' . $id . '/edit');
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId] = $ctx;
        try {
            if ($this->repository->findGrade($id, $tenantId) === null) {
                throw new RuntimeException('Grade introuvable.');
            }
            $this->repository->saveGrade($tenantId, $this->gradePayload($request, $tenantId), $id);
            Session::flash('success', 'Grade mis à jour.');
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }

        return Response::redirect(url('back-office/organisation/grades'));
    }

    public function gradeArchive(Request $request, array $params = []): Response
    {
        $ctx = $this->guardPost('back-office/organisation/grades');
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId] = $ctx;
        $this->repository->archiveGrade((int) ($params['id'] ?? 0), $tenantId);
        Session::flash('success', 'Grade archivé. L’historique des personnels qui le détiennent est conservé.');

        return Response::redirect(url('back-office/organisation/grades'));
    }

    public function gradeReorder(Request $request, array $params = []): Response
    {
        $ctx = $this->guardPost('back-office/organisation/grades');
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId] = $ctx;
        $order = $request->input('order', []);
        if (!is_array($order)) {
            $order = [];
        }
        $this->repository->reorderGrades($tenantId, array_map('intval', $order));
        Session::flash('success', 'Ordre hiérarchique enregistré.');

        return Response::redirect(url('back-office/organisation/grades'));
    }

    public function gradeAutoOrder(Request $request, array $params = []): Response
    {
        $ctx = $this->guardPost('back-office/organisation/grades');
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId] = $ctx;
        $out = $this->workflow->autoOrderGrades($tenantId);
        Session::flash('success', 'Ordre réaligné automatiquement (' . $out['reordered'] . ' grades). Les détections restent visibles sous le tableau.');

        return Response::redirect(url('back-office/organisation/grades'));
    }

    public function filiereStore(Request $request, array $params = []): Response
    {
        $ctx = $this->guardPost('back-office/organisation/grades');
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId] = $ctx;
        $code = strtoupper(trim((string) $request->input('code', '')));
        $label = trim((string) $request->input('label', ''));
        if ($code === '' || $label === '') {
            Session::flash('error', 'Code et libellé de filière sont requis.');

            return Response::redirect(url('back-office/organisation/grades'));
        }
        try {
            $this->repository->saveFiliere($tenantId, [
                'code' => $code,
                'label' => $label,
                'sort_order' => (int) $request->input('sort_order', 0),
            ]);
            Session::flash('success', 'Filière enregistrée.');
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }

        return Response::redirect(url('back-office/organisation/grades'));
    }

    public function importScale(Request $request, array $params = []): Response
    {
        $ctx = $this->guardPost('back-office/organisation/grades');
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId] = $ctx;
        $code = (string) $request->input('template', 'generique');
        $ok = $this->templates->duplicate($tenantId, $code);
        Session::flash($ok ? 'success' : 'error', $ok ? 'Échelle dupliquée dans la communauté.' : 'Modèle inconnu ou tables absentes.');

        return Response::redirect(url('back-office/organisation/grades'));
    }

    public function assignInitial(Request $request, array $params = []): Response
    {
        $ctx = $this->guardPost('back-office/organisation/grades');
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $userId] = $ctx;
        try {
            $this->workflow->assignInitialGrade(
                $tenantId,
                (int) $request->input('personnel_id', 0),
                (int) $request->input('grade_id', 0),
                (string) $request->input('obtained_at', ''),
                $userId
            );
            Session::flash('success', 'Grade initial enregistré.');
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }

        return Response::redirect(url('back-office/organisation/grades'));
    }

    public function campaigns(Request $request, array $params = []): Response
    {
        $ctx = $this->requireAdmin();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId] = $ctx;
        if (!$this->repository->tablesReady()) {
            return $this->page('Avancement', 'RH · AVANCEMENT', 'Les tables d’avancement ne sont pas encore installées.', 'admin.advancement.unavailable', []);
        }
        $this->templates->ensureForTenant($tenantId);

        return $this->page('Avancement', 'RH · AVANCEMENT', 'Campagnes au choix, par année, grade et filière.', 'admin.advancement.campaigns_index', [
            'campaigns' => $this->repository->listCampaigns($tenantId),
        ]);
    }

    public function campaignCreate(Request $request, array $params = []): Response
    {
        $ctx = $this->requireAdmin();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId] = $ctx;
        $this->templates->ensureForTenant($tenantId);

        return $this->page('Nouvelle campagne', 'RH · AVANCEMENT', 'Le quota limite le nombre de promus au moment de la publication.', 'admin.advancement.campaign_form', [
            'grades' => $this->repository->listGrades($tenantId, false),
            'filieres' => $this->repository->listFilieres($tenantId),
        ]);
    }

    public function campaignStore(Request $request, array $params = []): Response
    {
        $ctx = $this->guardPost('back-office/rh/avancement');
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $userId] = $ctx;
        $gradeId = (int) $request->input('grade_id', 0);
        $grade = $this->repository->findGrade($gradeId, $tenantId);
        if ($grade === null || empty($grade['advancement_choice_enabled'])) {
            Session::flash('error', 'Choisissez un grade ouvert à la voie choix.');

            return Response::redirect(url('back-office/rh/avancement/create'));
        }
        try {
            $id = $this->repository->insertCampaign($tenantId, [
                'grade_id' => $gradeId,
                'filiere_id' => $request->input('filiere_id') !== '' ? (int) $request->input('filiere_id', 0) : ($grade['filiere_id'] ?? null),
                'year' => (int) $request->input('year', (int) date('Y')),
                'opens_at' => (string) $request->input('opens_at', ''),
                'closes_at' => (string) $request->input('closes_at', ''),
                'quota_slots' => $request->input('quota_slots', ''),
                'created_by' => $userId,
            ]);
            Session::flash('success', 'Campagne ouverte.');

            return Response::redirect(url('back-office/rh/avancement/' . $id));
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());

            return Response::redirect(url('back-office/rh/avancement/create'));
        }
    }

    public function campaignShow(Request $request, array $params = []): Response
    {
        $ctx = $this->requireAdmin();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId] = $ctx;
        $campaign = $this->repository->findCampaign((int) ($params['id'] ?? 0), $tenantId);
        if ($campaign === null) {
            Session::flash('error', 'Campagne introuvable.');

            return Response::redirect(url('back-office/rh/avancement'));
        }
        $rows = $this->workflow->decorateCandidacies($tenantId, $campaign, $this->repository->listCandidacies((int) $campaign['id']));
        $ranked = 0;
        foreach ($rows as $row) {
            if ($row['preference_rank'] !== null && $row['preference_rank'] !== '') {
                $ranked++;
            }
        }

        return $this->page('Candidatures', 'RH · AVANCEMENT', 'L’éligibilité est recalculée à la demande : l’ancienneté et les qualifications bougent pendant la fenêtre.', 'admin.advancement.campaign_show', [
            'campaign' => $campaign,
            'candidacies' => $rows,
            'rankedTotal' => $ranked,
            'personnel' => $this->repository->listPersonnel($tenantId),
            'billets' => $this->repository->listBillets($tenantId),
            'detections' => $this->workflow->detectCandidacies($tenantId, $campaign, $rows),
        ]);
    }

    public function candidacyStore(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $ctx = $this->guardPost('back-office/rh/avancement/' . $id);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $userId] = $ctx;
        try {
            $this->workflow->createCandidacy($tenantId, $id, (int) $request->input('personnel_id', 0), $userId, [
                'mobility_requested' => $request->input('mobility_requested') === '1',
                'requested_billet_id' => $request->input('requested_billet_id'),
                'notes' => $request->input('notes'),
            ]);
            Session::flash('success', 'Candidature enregistrée.');
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }

        return Response::redirect(url('back-office/rh/avancement/' . $id));
    }

    public function recheck(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $ctx = $this->guardPost('back-office/rh/avancement/' . $id);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId] = $ctx;
        try {
            $n = $this->workflow->recheckCampaign($tenantId, $id);
            Session::flash('success', $n . ' candidature' . ($n > 1 ? 's' : '') . ' revérifiée' . ($n > 1 ? 's' : '') . '.');
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }

        return Response::redirect(url('back-office/rh/avancement/' . $id));
    }

    public function closeCampaign(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $ctx = $this->guardPost('back-office/rh/avancement/' . $id);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId] = $ctx;
        try {
            $this->workflow->closeCampaign($tenantId, $id);
            Session::flash('success', 'Fenêtre de candidature clôturée.');
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }

        return Response::redirect(url('back-office/rh/avancement/' . $id));
    }

    public function commission(Request $request, array $params = []): Response
    {
        $ctx = $this->requireAdmin();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId] = $ctx;
        $campaign = $this->repository->findCampaign((int) ($params['id'] ?? 0), $tenantId);
        if ($campaign === null) {
            return Response::redirect(url('back-office/rh/avancement'));
        }
        $commission = $this->repository->findCommission((int) $campaign['id']);
        $rows = $this->workflow->decorateCandidacies($tenantId, $campaign, $this->repository->listCandidacies((int) $campaign['id']));
        $members = is_array($commission['members'] ?? null) ? $commission['members'] : [];

        return $this->page('Commission', 'RH · AVANCEMENT', 'L’avis et la décision se saisissent ici. Un passage exceptionnel reste possible, avec motif.', 'admin.advancement.commission', [
            'campaign' => $campaign,
            'candidacies' => $rows,
            'commission' => $commission,
            'personnel' => $this->repository->listPersonnel($tenantId),
            'documents' => $this->repository->listDocuments($tenantId),
            'billets' => $this->repository->listBillets($tenantId),
            'detections' => $this->workflow->detectCandidacies($tenantId, $campaign, $rows, $members),
        ]);
    }

    public function openCommission(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $ctx = $this->guardPost('back-office/rh/avancement/' . $id);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId] = $ctx;
        try {
            $this->workflow->openCommission($tenantId, $id, (string) $request->input('meeting_date', ''), null, []);
            Session::flash('success', 'Campagne passée en commission. Les nouvelles candidatures sont verrouillées.');
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());

            return Response::redirect(url('back-office/rh/avancement/' . $id));
        }

        return Response::redirect(url('back-office/rh/avancement/' . $id . '/commission'));
    }

    public function commissionSave(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $ctx = $this->guardPost('back-office/rh/avancement/' . $id . '/commission');
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $userId] = $ctx;
        $posted = $request->input('candidacy', []);
        $rows = [];
        if (is_array($posted)) {
            foreach ($posted as $cid => $row) {
                if (!is_array($row)) {
                    continue;
                }
                $row['id'] = (int) $cid;
                $rows[] = $row;
            }
        }
        $members = [];
        $rawMembers = $request->input('members', []);
        if (is_array($rawMembers)) {
            foreach ($rawMembers as $member) {
                if (!is_array($member)) {
                    continue;
                }
                $members[] = [
                    'personnel_id' => (int) ($member['personnel_id'] ?? 0),
                    'role' => (string) ($member['role'] ?? 'titulaire'),
                ];
            }
        }
        try {
            $this->workflow->saveCommissionReview(
                $tenantId,
                $id,
                $rows,
                (string) $request->input('meeting_date', ''),
                (int) $request->input('minutes_document_id', 0),
                $members,
                $userId
            );
            Session::flash('success', 'Commission enregistrée.');
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }

        return Response::redirect(url('back-office/rh/avancement/' . $id . '/commission'));
    }

    public function commissionAutoRank(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $ctx = $this->guardPost('back-office/rh/avancement/' . $id . '/commission');
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId] = $ctx;
        $commission = $this->repository->findCommission($id);
        $members = is_array($commission['members'] ?? null) ? $commission['members'] : [];
        try {
            $out = $this->workflow->autoRankCandidacies($tenantId, $id, $members);
            Session::flash('success', 'Classement recalculé : éligibles d’abord, puis ancienneté et date de candidature (' . count($out['ranks']) . ' rangs).');
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }

        return Response::redirect(url('back-office/rh/avancement/' . $id . '/commission'));
    }

    public function publish(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $ctx = $this->guardPost('back-office/rh/avancement/' . $id . '/commission');
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $userId] = $ctx;
        try {
            $out = $this->workflow->publish($tenantId, $id, $userId);
            Session::flash('success', $out['promoted'] . ' grade' . ($out['promoted'] > 1 ? 's' : '') . ' attribué' . ($out['promoted'] > 1 ? 's' : '') . '. Le tableau est publié.');
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }

        return Response::redirect(url('back-office/rh/avancement/' . $id . '/commission'));
    }

    /**
     * @return array{0:int,1:int}|Response
     */
    private function requireAdmin(): array|Response
    {
        $userId = (int) Session::get('user_id');
        $tenantId = (int) Session::get('tenant_id');
        if ($userId < 1 || $tenantId < 1) {
            return Response::redirect(url('login'));
        }

        return [$tenantId, $userId];
    }

    /**
     * @return array{0:int,1:int}|Response
     */
    private function guardPost(string $back): array|Response
    {
        $ctx = $this->requireAdmin();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $request = new Request();
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url($back));
        }

        return $ctx;
    }

    /**
     * @return array<string, mixed>
     */
    private function gradePayload(Request $request, int $tenantId): array
    {
        $code = strtoupper(trim((string) $request->input('code', '')));
        $label = trim((string) $request->input('label', ''));
        if ($code === '' || $label === '') {
            throw new RuntimeException('Code et libellé sont requis.');
        }
        $qualId = (int) $request->input('required_qualification_id', 0);
        $qualCode = strtoupper(trim((string) $request->input('required_qualification_code', '')));
        if ($qualId < 1 && $qualCode !== '') {
            $found = $this->repository->findQualificationByCode($tenantId, $qualCode);
            $qualId = $found !== null ? (int) $found['id'] : 0;
            if ($qualId < 1) {
                throw new RuntimeException('Qualification « ' . $qualCode . ' » introuvable dans le référentiel.');
            }
        }

        return [
            'code' => $code,
            'label' => $label,
            'short_label' => trim((string) $request->input('short_label', '')),
            'filiere_id' => (int) $request->input('filiere_id', 0),
            'rank_order' => (int) $request->input('rank_order', 0),
            'advancement_seniority_enabled' => $request->input('advancement_seniority_enabled') === '1',
            'advancement_choice_enabled' => $request->input('advancement_choice_enabled') === '1',
            'min_time_in_previous_grade_months' => $request->input('min_time_in_previous_grade_months', ''),
            'required_qualification_id' => $qualId,
            'required_qualification_level_id' => (int) $request->input('required_qualification_level_id', 0),
        ];
    }

    /**
     * @param array<string, mixed> $vars
     */
    private function page(string $title, string $kicker, string $subtitle, string $content, array $vars): Response
    {
        return Response::view('layout.main', array_merge($vars, [
            'title' => $title,
            'content' => $content,
            'isBackOfficeShell' => true,
            'boPageGroup' => 'Organisation',
            'boPageKicker' => $kicker,
            'boPageTitle' => $title,
            'boPageSubtitle' => $subtitle,
            'backOfficePageCss' => ['back-office-advancement.css'],
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
        ]));
    }
}
