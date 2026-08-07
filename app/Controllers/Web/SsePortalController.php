<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\SseAccessCodeRepository;
use App\Repositories\SseCaseRepository;
use App\Repositories\SseDocumentRepository;
use App\Repositories\SseInterestCaseRepository;
use App\Repositories\SseMeshRepository;
use App\Repositories\SsePersonRepository;
use App\Repositories\SsePortalSettingsRepository;
use App\Repositories\SseSiteRepository;
use App\Repositories\SseWatchlistRepository;
use App\Repositories\TheatreMissionCycleRepository;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseCasePdfService;
use App\Services\Sse\SseClearanceService;
use App\Services\Sse\SseCorrelationService;
use App\Services\Sse\SseCrossMatchService;
use App\Services\Sse\SseMeshService;
use App\Services\Sse\SseRedactionService;
use App\Services\Sse\SseReportService;
use App\Services\Sse\SseWorkspaceService;
use App\Services\Tactical\AtakActivityLogService;

final class SsePortalController
{
    public function __construct(
        private ?SseAccessCodeService $access = null,
        private ?SseAccessCodeRepository $codes = null,
        private ?SseCaseRepository $cases = null,
        private ?SseInterestCaseRepository $interestCases = null,
        private ?SsePersonRepository $persons = null,
        private ?SseWatchlistRepository $watchlist = null,
        private ?SseSiteRepository $sites = null,
        private ?SseCrossMatchService $cross = null,
        private ?SseCasePdfService $pdf = null,
        private ?SseReportService $reports = null,
        private ?SseCorrelationService $correlation = null,
        private ?SseRedactionService $redaction = null,
        private ?SseClearanceService $clearance = null,
        private ?AtakActivityLogService $activityLog = null,
        private ?SsePortalSettingsRepository $settings = null,
        private ?TheatreMissionCycleRepository $missions = null,
        private ?SseMeshRepository $meshes = null,
        private ?SseMeshService $meshService = null,
        private ?SseWorkspaceService $workspace = null,
        private ?SseDocumentRepository $documents = null,
    ) {
        $this->access ??= new SseAccessCodeService();
        $this->codes ??= new SseAccessCodeRepository();
        $this->cases ??= new SseCaseRepository();
        $this->interestCases ??= new SseInterestCaseRepository();
        $this->persons ??= new SsePersonRepository();
        $this->watchlist ??= new SseWatchlistRepository();
        $this->sites ??= new SseSiteRepository();
        $this->cross ??= new SseCrossMatchService();
        $this->pdf ??= new SseCasePdfService();
        $this->reports ??= new SseReportService();
        $this->correlation ??= new SseCorrelationService();
        $this->redaction ??= new SseRedactionService();
        $this->clearance ??= new SseClearanceService();
        $this->activityLog ??= new AtakActivityLogService();
        $this->settings ??= new SsePortalSettingsRepository();
        $this->missions ??= new TheatreMissionCycleRepository();
        $this->meshes ??= new SseMeshRepository();
        $this->meshService ??= new SseMeshService($this->meshes, $this->correlation);
        $this->workspace ??= new SseWorkspaceService(
            $this->cases,
            $this->interestCases,
            $this->persons,
            $this->sites,
            $this->meshes,
            $this->watchlist,
            $this->cross
        );
        $this->documents ??= new SseDocumentRepository();
    }

    /** Sas d’entrée (public) */
    public function gate(Request $request, array $params = []): Response
    {
        if ($this->access->hasActiveClearance()) {
            if (!$this->access->hasAcceptedConfidentiality()) {
                return Response::redirect(url('atak/sse/confidentialite'));
            }

            return Response::redirect(url('atak/sse/operations'));
        }

        // Commandement : entrée directe sans code (pour délivrer les accès).
        if ($this->access->canEnterAsStaff()) {
            $this->access->establishStaffClearance((int) Session::get('tenant_id'));

            return Response::redirect(url('atak/sse/confidentialite'));
        }

        $operator = $this->gateOperatorContext();

        return Response::view('atak.sse.gate', [
            'title' => 'Accès renseignement interpersonnel',
            'error' => Session::getFlash('error'),
            'success' => Session::getFlash('success'),
            'loggedIn' => (int) Session::get('user_id') > 0,
            'sseTheme' => sse_ui_theme(),
            'sseThemeOptions' => sse_ui_theme_options(),
            'operatorName' => $operator['name'],
            'operatorMeta' => $operator['meta'],
            'operatorInitial' => $operator['initial'],
        ]);
    }

    /** Mémorise l’apparence SSE (Bureau SSE) puis renvoie à la page d’origine. */
    public function setTheme(Request $request, array $params = []): Response
    {
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('atak/sse'));
        }

        sse_ui_theme_persist((string) $request->input('theme', 'bureau'));

        return Response::redirect($this->sseBackUrl((string) $request->input('back', '')));
    }

    /** Mémorise la mission active du portail SSE (cycle théâtre). */
    public function setMission(Request $request, array $params = []): Response
    {
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('atak/sse'));
        }

        $missionId = (int) $request->input('mission_id', 0);
        if ($missionId > 0) {
            $row = $this->missions->findForTenant($this->tenantId(), $missionId);
            if ($row === null) {
                $missionId = 0;
            }
        }
        sse_ui_mission_persist($missionId);

        return Response::redirect($this->sseBackUrl((string) $request->input('back', '')));
    }

    /** Mémorise le niveau de diffusion affiché dans la barre de contexte. */
    public function setClassification(Request $request, array $params = []): Response
    {
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('atak/sse'));
        }

        sse_ui_classification_persist((string) $request->input('classification', 'confidentiel'));

        return Response::redirect($this->sseBackUrl((string) $request->input('back', '')));
    }

    /** Entrée commandement depuis le back-office (toujours vers les codes). */
    public function staffEnter(Request $request, array $params = []): Response
    {
        if (!$this->access->canEnterAsStaff()) {
            Session::flash('error', 'Seul le commandement peut ouvrir cet accès.');

            return Response::redirect(url('atak/sse'));
        }
        $this->access->establishStaffClearance((int) Session::get('tenant_id'));
        Session::set('sse_post_ack_redirect', 'acces');
        Session::flash('success', 'Session commandement ouverte — validez l’engagement de confidentialité.');

        return Response::redirect(url('atak/sse/confidentialite'));
    }

    public function redeem(Request $request, array $params = []): Response
    {
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('atak/sse'));
        }

        sse_ui_theme_persist((string) $request->input('ui_theme', sse_ui_theme()));

        $code = strtoupper(trim((string) $request->input('access_code', '')));
        if ($code === '') {
            Session::flash('error', 'Indiquez le code d’accès fourni par votre commandement.');

            return Response::redirect(url('atak/sse'));
        }

        $userId = (int) Session::get('user_id');
        $hasPerm = $userId > 0 && function_exists('can') && can('atak.sse.access');
        $label = $userId > 0
            ? (string) (Session::get('display_name') ?? Session::get('callsign') ?? 'Membre')
            : trim((string) $request->input('guest_name', 'Invité'));

        $result = $this->access->redeem($code, $userId > 0 ? $userId : null, $hasPerm, $label);
        if (!$result['ok']) {
            Session::flash('error', $result['message']);

            return Response::redirect(url('atak/sse'));
        }

        Session::flash('success', 'Accès accordé. Validez l’engagement de confidentialité pour entrer.');

        return Response::redirect(url('atak/sse/confidentialite'));
    }

    public function logout(Request $request, array $params = []): Response
    {
        $this->access->clearSession();
        Session::forget('sse_post_ack_redirect');
        Session::flash('success', 'Session de renseignement fermée.');

        return Response::redirect(url('atak/sse'));
    }

    /** Sas d’engagement de confidentialité — obligatoire avant le bureau. */
    public function confidentiality(Request $request, array $params = []): Response
    {
        if ($this->access->hasAcceptedConfidentiality()) {
            return Response::redirect(url('atak/sse/operations'));
        }

        $operator = $this->gateOperatorContext();
        $until = (int) Session::get(SseAccessCodeService::SESSION_UNTIL, 0);

        return Response::view('atak.sse.confidentiality', [
            'title' => 'Engagement de confidentialité',
            'error' => Session::getFlash('error'),
            'success' => Session::getFlash('success'),
            'sseTheme' => sse_ui_theme(),
            'operatorName' => $operator['name'],
            'operatorMeta' => $operator['meta'],
            'operatorInitial' => $operator['initial'],
            'sessionKind' => $this->access->isGuest() ? 'Session invitée' : 'Session authentifiée',
            'expiresLabel' => $until > 0 ? date('H:i', $until) : '',
        ]);
    }

    public function confidentialityAccept(Request $request, array $params = []): Response
    {
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('atak/sse/confidentialite'));
        }

        if ((string) $request->input('acknowledge', '') !== '1') {
            Session::flash('error', 'Cochez la case pour confirmer que vous acceptez les règles.');

            return Response::redirect(url('atak/sse/confidentialite'));
        }

        $userId = (int) Session::get('user_id');
        $label = (string) (Session::get('sse_guest_label')
            ?? Session::get('display_name')
            ?? Session::get('callsign')
            ?? 'Opérateur');
        $this->access->acceptConfidentiality($userId > 0 ? $userId : null, $label);
        Session::flash('success', 'Engagement enregistré. Bienvenue dans le bureau SSE.');

        $intended = (string) Session::get('sse_post_ack_redirect', '');
        Session::forget('sse_post_ack_redirect');
        if ($intended === 'acces' && $this->canGrant()) {
            return Response::redirect(url('atak/sse/acces'));
        }

        return Response::redirect(url('atak/sse/operations'));
    }

    public function casesIndex(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        $scope = $this->access->caseScope();
        $list = $this->cases->listForTenant($tenantId, $scope, [
            'status' => $request->query('status'),
            'classification' => $request->query('classification'),
            'q' => $request->query('q'),
        ]);
        $allCases = $this->cases->listForTenant($tenantId, $scope);
        $indexCounts = ['total' => count($allCases), 'active' => 0, 'archive' => 0];
        foreach ($allCases as $case) {
            $status = (string) ($case['status'] ?? '');
            if (in_array($status, ['ouvert', 'en_cours'], true)) {
                $indexCounts['active']++;
            }
            if ($status === 'archive') {
                $indexCounts['archive']++;
            }
        }

        $counts = $this->cases->countsForCases(
            array_map(static fn (array $c): int => (int) ($c['id'] ?? 0), $list),
            $tenantId
        );
        $folderTree = $this->cases->buildTree($allCases);
        $parentQ = (int) $request->query('parent', 0);

        return $this->portalView('atak.sse.cases', [
            'title' => 'Dossiers — Renseignement interpersonnel',
            'cases' => $list,
            'caseCounts' => $counts,
            'caseTree' => $folderTree,
            'indexCounts' => $indexCounts,
            'caseLockEnabled' => $this->clearance->caseLockEnabled($tenantId),
            'screensRedacted' => $this->clearance->workingRedactionEnabled($tenantId),
            'lockedForMe' => $this->clearance->countLockedForMe($list),
            'myClearance' => $this->clearance->maxLevel(),
            'canManage' => $this->canManage(),
            'canGrant' => $this->canGrant(),
            'canExport' => $this->canExport(),
            'parentFilter' => $parentQ,
            'filters' => [
                'status' => (string) $request->query('status', ''),
                'classification' => (string) $request->query('classification', ''),
                'q' => (string) $request->query('q', ''),
            ],
            'classifications' => SseCaseRepository::CLASSIFICATION_LABELS,
            'statuses' => SseCaseRepository::STATUS_LABELS,
            'activeNav' => 'dossiers',
        ]);
    }

    public function interestCasesIndex(Request $request, array $params = []): Response
    {
        $filters = ['status' => (string) $request->query('status', ''), 'q' => (string) $request->query('q', '')];
        return $this->portalView('atak.sse.interest_cases', [
            'title' => 'Pré-SSE — investigations préparatoires',
            'interestCases' => $this->interestCases->listForTenant($this->tenantId(), $filters),
            'filters' => $filters,
            'statuses' => SseInterestCaseRepository::STATUSES,
            'canManage' => $this->canManage(),
            'activeNav' => 'interet',
        ]);
    }

    public function interestCaseCreateForm(Request $request, array $params = []): Response
    {
        if (!$this->canManage()) {
            return Response::redirect(url('atak/sse/interet'));
        }

        return $this->portalView('atak.sse.interest_case_form', [
            'title' => 'Ouvrir un dossier d’intérêt',
            'activeNav' => 'interet',
            'canManage' => true,
            'confidenceLevels' => SseInterestCaseRepository::CONFIDENCE,
            'interestLevels' => SseInterestCaseRepository::INTEREST,
        ]);
    }

    public function interestCaseStore(Request $request, array $params = []): Response
    {
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');
            return Response::redirect(url('atak/sse/interet'));
        }
        $designation = trim((string) $request->input('temporary_designation', ''));
        $reason = trim((string) $request->input('opening_reason', ''));
        if ($designation === '' || $reason === '') {
            Session::flash('error', 'La désignation temporaire et le motif d’ouverture sont obligatoires.');
            return Response::redirect(url('atak/sse/interet/nouveau'));
        }
        $fields = ['temporary_designation','suspected_alias','apparent_sex','estimated_age_range','suspected_nationality','suspected_affiliation','confidence_level','interest_level','opening_reason','origin_operator','observed_elements','analysis_facts','analysis_assumptions','analysis_contradictions','analysis_questions','collection_needs','operational_risk','recommendations','source_label','source_reliability','acquisition_at','mission_label'];
        $data = [];
        foreach ($fields as $field) $data[$field] = trim((string) $request->input($field, '')) ?: null;
        $data['temporary_designation'] = $designation; $data['opening_reason'] = $reason;
        $data['created_by'] = (int) Session::get('user_id') ?: null;
        $id = $this->interestCases->create($this->tenantId(), $data);
        Session::flash('success', 'Dossier d’intérêt ouvert. Aucune identité n’a été déduite automatiquement.');
        return Response::redirect(url('atak/sse/interet/' . $id));
    }

    public function interestCaseShow(Request $request, array $params = []): Response
    {
        $case = $this->interestCases->findForTenant((int) ($params['id'] ?? 0), $this->tenantId());
        if (!$case) {
            return Response::redirect(url('atak/sse/interet'));
        }

        $hypotheses = [];
        $rawAssumptions = trim((string) ($case['analysis_assumptions'] ?? ''));
        if ($rawAssumptions !== '') {
            foreach (preg_split('/\R+/', $rawAssumptions) ?: [] as $i => $line) {
                $line = trim((string) $line);
                if ($line === '') {
                    continue;
                }
                $hypotheses[] = [
                    'code' => 'H' . ($i + 1),
                    'text' => $line,
                    'confidence' => max(40, min(88, 55 + ($i * 7))),
                    'pros' => array_values(array_filter([
                        trim((string) ($case['analysis_facts'] ?? '')) !== '' ? 'Éléments factuels consignés' : null,
                        trim((string) ($case['observed_elements'] ?? '')) !== '' ? 'Observations terrain présentes' : null,
                    ])),
                    'cons' => array_values(array_filter([
                        trim((string) ($case['analysis_contradictions'] ?? '')) !== '' ? 'Contradictions signalées' : null,
                        trim((string) ($case['analysis_questions'] ?? '')) !== '' ? 'Questions ouvertes' : null,
                    ])),
                ];
            }
        }
        if ($hypotheses === []) {
            $hypotheses[] = [
                'code' => 'H1',
                'text' => 'La cible « ' . (string) ($case['temporary_designation'] ?? 'FALCON') . ' » correspond à une identité à consolider.',
                'confidence' => 52,
                'pros' => ['Désignation temporaire ouverte', 'Motif d’ouverture documenté'],
                'cons' => ['Identité non confirmée', 'Biométrie non consolidée'],
            ];
        }

        $proposals = [];
        try {
            $matches = $this->cross->matchPersonsAgainstWatchlist($this->tenantId());
            foreach (array_slice(is_array($matches) ? $matches : [], 0, 5) as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $person = is_array($row['person'] ?? null) ? $row['person'] : [];
                $top = is_array($row['matches'][0] ?? null) ? $row['matches'][0] : [];
                $proposals[] = [
                    'title' => 'Corrélation proposée',
                    'detail' => sprintf(
                        '%s — %s',
                        (string) ($person['display_name'] ?? 'Identité'),
                        (string) ($top['reason'] ?? 'Rapprochement à confirmer')
                    ),
                    'score' => (int) ($top['score'] ?? 0),
                ];
            }
        } catch (\Throwable) {
            $proposals = [];
        }
        if ($proposals === []) {
            $proposals[] = [
                'title' => 'Corrélation proposée',
                'detail' => 'Aucun rapprochement automatique fort — poursuivez la collecte et les croisements manuels.',
                'score' => 0,
            ];
        }

        return $this->portalView('atak.sse.interest_case_show', [
            'title' => (string) ($case['reference_code'] ?? 'Pré-SSE'),
            'interestCase' => $case,
            'hypotheses' => $hypotheses,
            'proposals' => $proposals,
            'activeNav' => 'interet',
            'canManage' => $this->canManage(),
        ]);
    }

    public function caseCreateForm(Request $request, array $params = []): Response
    {
        if (!$this->canManage()) {
            Session::flash('error', 'Vous n’êtes pas habilité à ouvrir un dossier.');

            return Response::redirect(url('atak/sse/dossiers'));
        }

        return $this->portalView('atak.sse.case_form', [
            'title' => 'Ouvrir un dossier',
            'case' => null,
            'classifications' => SseCaseRepository::CLASSIFICATION_LABELS,
            'statuses' => SseCaseRepository::STATUS_LABELS,
            'parentId' => (int) $request->query('parent', 0),
            'folderParents' => array_values(array_filter(
                $this->cases->listForTenant($this->tenantId(), $this->access->caseScope()),
                static fn (array $c): bool => !empty($c['is_folder'])
            )),
            'activeNav' => 'dossiers',
            'canManage' => true,
            'canGrant' => $this->canGrant(),
            'canExport' => $this->canExport(),
        ]);
    }

    public function caseStore(Request $request, array $params = []): Response
    {
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('atak/sse/dossiers'));
        }

        $unlockPlain = trim((string) $request->input('unlock_code', ''));
        $id = $this->cases->create([
            'tenant_id' => $this->tenantId(),
            'title' => (string) $request->input('title', ''),
            'summary' => (string) $request->input('summary', ''),
            'classification' => (string) $request->input('classification', 'encadrement'),
            'status' => 'ouvert',
            'parent_id' => (int) $request->input('parent_id', 0),
            'created_by' => (int) Session::get('user_id') ?: null,
            'unlock_code_hash' => $unlockPlain !== '' ? hash('sha256', strtoupper($unlockPlain)) : null,
        ]);
        Session::flash('success', 'Dossier ouvert.');

        return Response::redirect(url('atak/sse/dossiers/' . $id));
    }

    /** Crée un dossier / sous-dossier depuis le rail latéral. */
    public function folderStore(Request $request, array $params = []): Response
    {
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('atak/sse/dossiers'));
        }
        $title = trim((string) $request->input('title', ''));
        if ($title === '') {
            Session::flash('error', 'Indiquez un nom de dossier.');

            return Response::redirect(url('atak/sse/dossiers'));
        }
        $unlockPlain = trim((string) $request->input('unlock_code', ''));
        $parentId = (int) $request->input('parent_id', 0);
        if ($parentId > 0) {
            $parent = $this->requireCase($parentId);
            if ($parent === null || empty($parent['is_folder'])) {
                Session::flash('error', 'Le dossier parent est introuvable ou n’est pas un dossier.');

                return Response::redirect(url('atak/sse/dossiers'));
            }
        }
        $id = $this->cases->create([
            'tenant_id' => $this->tenantId(),
            'title' => $title,
            'summary' => '',
            'classification' => (string) $request->input('classification', 'encadrement'),
            'status' => 'ouvert',
            'is_folder' => true,
            'parent_id' => $parentId,
            'created_by' => (int) Session::get('user_id') ?: null,
            'unlock_code_hash' => $unlockPlain !== '' ? hash('sha256', strtoupper($unlockPlain)) : null,
        ]);
        Session::flash('success', 'Dossier créé.');

        return Response::redirect(url('atak/sse/dossiers/' . $id));
    }

    public function caseUnlock(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('atak/sse/dossiers/' . $id));
        }
        $case = $this->requireCase($id);
        if ($case === null) {
            return Response::redirect(url('atak/sse/dossiers'));
        }
        $plain = (string) $request->input('unlock_code', '');
        if (!$this->cases->verifyUnlockCode($id, $this->tenantId(), $plain)) {
            Session::flash('error', 'Mot de passe incorrect.');

            return Response::redirect(url('atak/sse/dossiers/' . $id));
        }
        $unlocked = Session::get('sse_unlocked_cases', []);
        if (!is_array($unlocked)) {
            $unlocked = [];
        }
        $unlocked[$id] = time();
        Session::set('sse_unlocked_cases', $unlocked);
        Session::flash('success', 'Dossier déverrouillé pour cette session.');

        return Response::redirect(url('atak/sse/dossiers/' . $id));
    }

    /** Capture Tacmap (image PNG base64) versée comme preuve. */
    public function caseTacmapCapture(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('atak/sse/dossiers/' . $id));
        }
        if ($this->requireCase($id) === null || !$this->caseUnlocked($id)) {
            return Response::redirect(url('atak/sse/dossiers/' . $id));
        }
        $dataUrl = (string) $request->input('image_data', '');
        if (!preg_match('#^data:image/(png|jpeg);base64,#', $dataUrl)) {
            Session::flash('error', 'Capture invalide.');

            return Response::redirect(url('atak/sse/dossiers/' . $id));
        }
        $raw = base64_decode(substr($dataUrl, (int) strpos($dataUrl, ',') + 1), true);
        if ($raw === false || strlen($raw) < 32 || strlen($raw) > 8_000_000) {
            Session::flash('error', 'Impossible d’enregistrer la capture.');

            return Response::redirect(url('atak/sse/dossiers/' . $id));
        }
        $dir = base_path('public/uploads/sse/evidence');
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $name = 'tacmap_' . $id . '_' . time() . '.png';
        if (@file_put_contents($dir . DIRECTORY_SEPARATOR . $name, $raw) === false) {
            Session::flash('error', 'Écriture de la capture impossible.');

            return Response::redirect(url('atak/sse/dossiers/' . $id));
        }
        $caption = trim((string) $request->input('caption', ''));
        $this->cases->addEvidence($id, $this->tenantId(), [
            'label' => 'Capture Tacmap',
            'caption' => $caption !== '' ? $caption : 'Vue carte au ' . date('d/m/Y H:i'),
            'image_path' => 'uploads/sse/evidence/' . $name,
            'author_label' => (string) (Session::get('sse_guest_label') ?? Session::get('display_name') ?? 'Opérateur'),
        ]);
        Session::flash('success', 'Capture de carte enregistrée dans les preuves.');

        return Response::redirect(url('atak/sse/dossiers/' . $id . '#tacmap'));
    }

    public function caseShow(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $case = $this->requireCase($id);
        if ($case === null) {
            Session::flash('error', 'Dossier introuvable ou hors de votre périmètre.');

            return Response::redirect(url('atak/sse/dossiers'));
        }

        if (!empty($case['has_unlock_code']) && !$this->caseUnlocked($id)) {
            return $this->portalView('atak.sse.case_unlock', [
                'title' => 'Déverrouiller — ' . ($case['reference_code'] ?? ''),
                'case' => $case,
                'activeNav' => 'dossiers',
                'canManage' => $this->canManage(),
                'canGrant' => $this->canGrant(),
                'canExport' => $this->canExport(),
            ]);
        }

        $this->pushRecentCase($case);

        $children = $this->cases->listChildren($id, $this->tenantId(), $this->access->caseScope());
        $childCounts = $this->cases->countsForCases(
            array_map(static fn (array $c): int => (int) ($c['id'] ?? 0), $children),
            $this->tenantId()
        );

        if (!empty($case['is_folder'])) {
            return $this->portalView('atak.sse.folder_show', [
                'title' => ($case['reference_code'] ?? '') . ' — ' . ($case['title'] ?? ''),
                'case' => $case,
                'children' => $children,
                'childCounts' => $childCounts,
                'classifications' => SseCaseRepository::CLASSIFICATION_LABELS,
                'canManage' => $this->canManage(),
                'canGrant' => $this->canGrant(),
                'canExport' => $this->canExport(),
                'activeNav' => 'dossiers',
            ]);
        }

        $links = $this->cases->listLinkedPersonIds($id, $this->tenantId());
        $people = [];
        foreach ($links as $link) {
            $p = $this->persons->findById((int) $link['person_id'], $this->tenantId());
            if ($p) {
                $people[] = $p;
            }
        }
        $available = $this->persons->listForContext($this->tenantId(), 1, ['limit' => 100]);

        $people = $this->clearance->redactPeopleForScreens($people, $this->tenantId(), $id);
        $available = $this->clearance->redactPeopleForScreens($available, $this->tenantId(), $id);

        $caseSites = $this->sites->listForCase($id, $this->tenantId());
        $siteCounts = $this->sites->countsForSites(
            array_map(static fn (array $s): int => (int) ($s['id'] ?? 0), $caseSites),
            $this->tenantId()
        );

        return $this->portalView('atak.sse.case_show', [
            'title' => $case['reference_code'] . ' — ' . $case['title'],
            'case' => $case,
            'people' => $people,
            'availablePeople' => $available,
            'caseSites' => $caseSites,
            'siteCounts' => $siteCounts,
            'notes' => $this->cases->listNotes($id, $this->tenantId()),
            'evidence' => $this->cases->listEvidence($id, $this->tenantId()),
            'classifications' => SseCaseRepository::CLASSIFICATION_LABELS,
            'statuses' => SseCaseRepository::STATUS_LABELS,
            'canManage' => $this->canManage(),
            'canGrant' => $this->canGrant(),
            'canExport' => $this->canExport(),
            'activeNav' => 'dossiers',
            'sseNeedLeaflet' => true,
        ]);
    }

    public function caseUpdate(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('atak/sse/dossiers/' . $id));
        }
        if ($this->requireCase($id) === null) {
            return Response::redirect(url('atak/sse/dossiers'));
        }
        $this->cases->update($id, $this->tenantId(), [
            'title' => (string) $request->input('title', ''),
            'summary' => (string) $request->input('summary', ''),
            'classification' => (string) $request->input('classification', 'encadrement'),
            'status' => (string) $request->input('status', 'ouvert'),
        ]);
        Session::flash('success', 'Dossier mis à jour.');

        return Response::redirect(url('atak/sse/dossiers/' . $id));
    }

    public function caseLinkPerson(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('atak/sse/dossiers/' . $id));
        }
        if ($this->requireCase($id) === null) {
            return Response::redirect(url('atak/sse/dossiers'));
        }
        $personId = (int) $request->input('person_id', 0);
        if ($personId < 1 || !$this->persons->findById($personId, $this->tenantId())) {
            Session::flash('error', 'Personne introuvable.');

            return Response::redirect(url('atak/sse/dossiers/' . $id));
        }
        $this->cases->linkPerson($id, $personId, $this->tenantId(), (int) Session::get('user_id') ?: null);
        Session::flash('success', 'Personne rattachée au dossier.');

        return Response::redirect(url('atak/sse/dossiers/' . $id));
    }

    public function caseAddNote(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('atak/sse/dossiers/' . $id));
        }
        if ($this->requireCase($id) === null) {
            return Response::redirect(url('atak/sse/dossiers'));
        }
        $body = trim((string) $request->input('body', ''));
        if ($body === '') {
            Session::flash('error', 'La note ne peut pas être vide.');

            return Response::redirect(url('atak/sse/dossiers/' . $id));
        }
        $this->cases->addNote(
            $id,
            $this->tenantId(),
            $body,
            (string) $request->input('classification', 'encadrement'),
            (int) Session::get('user_id') ?: null,
            (string) (Session::get('sse_guest_label') ?? Session::get('display_name') ?? 'Opérateur')
        );
        Session::flash('success', 'Note ajoutée.');

        return Response::redirect(url('atak/sse/dossiers/' . $id));
    }

    public function caseAddEvidence(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('atak/sse/dossiers/' . $id));
        }
        if ($this->requireCase($id) === null) {
            return Response::redirect(url('atak/sse/dossiers'));
        }
        $label = trim((string) $request->input('label', 'Preuve'));
        $caption = trim((string) $request->input('caption', ''));
        $imagePath = null;
        if (!empty($_FILES['image']['tmp_name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
            $dir = base_path('public/uploads/sse/evidence');
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            $ext = pathinfo((string) ($_FILES['image']['name'] ?? 'img.jpg'), PATHINFO_EXTENSION) ?: 'jpg';
            $ext = preg_replace('/[^a-z0-9]/i', '', $ext) ?: 'jpg';
            $name = 'ev_' . $id . '_' . time() . '.' . strtolower($ext);
            if (@move_uploaded_file($_FILES['image']['tmp_name'], $dir . DIRECTORY_SEPARATOR . $name)) {
                $imagePath = 'uploads/sse/evidence/' . $name;
            }
        }
        $this->cases->addEvidence($id, $this->tenantId(), [
            'label' => $label !== '' ? $label : 'Preuve',
            'caption' => $caption,
            'image_path' => $imagePath,
            'author_label' => (string) (Session::get('sse_guest_label') ?? Session::get('display_name') ?? 'Opérateur'),
        ]);
        Session::flash('success', 'Preuve enregistrée.');

        return Response::redirect(url('atak/sse/dossiers/' . $id));
    }

    public function casePdf(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if (!$this->canExport()) {
            Session::flash('error', 'Export non autorisé.');

            return Response::redirect(url('atak/sse/dossiers/' . $id));
        }
        $case = $this->requireCase($id);
        if ($case === null) {
            return Response::redirect(url('atak/sse/dossiers'));
        }

        $tenantId = $this->tenantId();
        $level = $this->clearance->maxLevel();

        // Un PDF sort du portail et circule ensuite tout seul : c'est le support
        // sur lequel un caviardage manquant coûte le plus cher, puisqu'on ne peut
        // plus le rattraper une fois le fichier transmis.
        $this->activityLog->record(
            $tenantId,
            1,
            'SSE_CLEARANCE',
            sprintf(
                'Export PDF du dossier %s en « %s ».',
                (string) ($case['reference_code'] ?? $id),
                SseRedactionService::levelLabel($level)
            ),
            (string) (Session::get('display_name') ?? Session::get('callsign') ?? 'Portail')
        );

        return $this->pdf->export($tenantId, $id, $level);
    }

    public function personsIndex(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        $list = $this->persons->listForContext($tenantId, 1, [
            'status' => $request->query('status'),
            'limit' => 100,
        ]);

        // Chaîne de possession : une seule requête pour tout le registre.
        $custody = $this->persons->custodyEventsForPersons(
            array_map(static fn (array $p): int => (int) ($p['id'] ?? 0), $list),
            $tenantId
        );

        // Relevés biométriques simulés + croisement listes de surveillance.
        foreach ($list as $i => $p) {
            $pid = (int) ($p['id'] ?? 0);
            $list[$i]['biometric_samples'] = $this->persons->listBiometricSamples($pid, $tenantId);
            $list[$i]['watchlist'] = $this->cross->matchOne($p, $tenantId);
            $list[$i]['custody'] = $custody[$pid] ?? [];
        }

        // Écrans de travail : rabattus seulement si la communauté l'a décidé.
        $list = $this->clearance->redactPeopleForScreens($list, $tenantId);

        $nav = (string) ($params['_nav'] ?? 'identites');

        return $this->portalView('atak.sse.persons', [
            'title' => 'Identités — objets SSE',
            'persons' => $list,
            'screensRedacted' => $this->clearance->workingRedactionEnabled($tenantId),
            'myClearance' => $this->clearance->maxLevel(),
            'canManage' => $this->canManage(),
            'canGrant' => $this->canGrant(),
            'canExport' => $this->canExport(),
            'activeNav' => $nav,
        ]);
    }

    /**
     * Comptes rendus du dossier — flash et compte rendu initial.
     * Générés à la lecture depuis les événements déjà enregistrés : aucun stockage
     * dupliqué, le document reflète toujours l'état réel du dossier.
     */
    public function caseReport(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $case = $this->requireCase($id);
        if ($case === null) {
            Session::flash('error', 'Dossier introuvable ou hors de votre périmètre.');

            return Response::redirect(url('atak/sse/dossiers'));
        }

        $tenantId = $this->tenantId();

        // Le compte rendu est servi au plafond d'habilitation du lecteur, pas en
        // intégral. Sans ça, l'écran de déclassification ne servait à rien : il
        // suffisait de venir ici pour obtenir le même contenu en clair.
        // Un lecteur pleinement habilité voit tout — le rabattement ne coûte rien
        // à ceux qui y ont droit.
        $level = $this->clearance->maxLevel();
        $partial = SseRedactionService::summarise($level)['hidden'] !== [];

        return $this->portalView('atak.sse.case_report', [
            'title' => 'Compte rendu — ' . ($case['reference_code'] ?? ''),
            'case' => $case,
            'level' => $level,
            'partial' => $partial,
            'clearanceOrigin' => $this->clearance->origin(),
            'flash' => $this->reports->buildFlashReport($id, $tenantId, $level),
            'initial' => $this->reports->buildInitialReport($id, $tenantId, $level),
            'caseDocuments' => $this->documents->listForTenant($tenantId, ['case_id' => $id]),
            'canManage' => $this->canManage(),
            'canGrant' => $this->canGrant(),
            'canExport' => $this->canExport(),
            'activeNav' => 'dossiers',
        ]);
    }

    /**
     * Ouvre un brouillon de document à partir du flash / compte rendu généré du dossier.
     */
    public function caseReportDraft(Request $request, array $params = []): Response
    {
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('atak/sse/dossiers'));
        }

        $id = (int) ($params['id'] ?? 0);
        $case = $this->requireCase($id);
        if ($case === null) {
            Session::flash('error', 'Dossier introuvable ou hors de votre périmètre.');

            return Response::redirect(url('atak/sse/dossiers'));
        }

        $tenantId = $this->tenantId();
        $level = $this->clearance->maxLevel();
        $kind = (string) $request->input('kind', 'compte_rendu');
        $type = $kind === 'flash' ? 'flash' : 'compte_rendu';
        $body = $type === 'flash'
            ? $this->reports->buildFlashReport($id, $tenantId, $level)
            : $this->reports->buildInitialReport($id, $tenantId, $level);
        $titlePrefix = $type === 'flash' ? 'Flash' : 'Compte rendu';
        $author = (string) (Session::get('sse_guest_label') ?? Session::get('display_name') ?? 'Opérateur');
        $userId = (int) Session::get('user_id') ?: null;

        $docId = $this->documents->create([
            'tenant_id' => $tenantId,
            'case_id' => $id,
            'document_type' => $type,
            'title' => $titlePrefix . ' — ' . ($case['reference_code'] ?? '') . ' — ' . ($case['title'] ?? ''),
            'body' => $body,
            'classification' => (string) ($case['classification'] ?? 'confidentiel'),
            'status' => 'brouillon',
            'created_by' => $userId,
            'updated_by' => $userId,
            'author_label' => $author,
        ]);

        $this->activityLog->record(
            $tenantId,
            1,
            'SSE_DOCUMENT',
            'Brouillon ' . $titlePrefix . ' ouvert pour ' . ($case['reference_code'] ?? ''),
            $author
        );

        Session::flash('success', 'Brouillon créé — vous pouvez maintenant le retravailler avant validation.');

        return Response::redirect(url('atak/sse/documents/' . $docId . '/modifier'));
    }

    /**
     * Arme ou désarme le verrou d'ouverture par classification.
     *
     * Réservé à ceux qui peuvent déjà octroyer des accès : armer ce verrou ferme
     * des dossiers à d'autres, ce n'est pas un réglage d'affichage.
     */
    public function caseLockToggle(Request $request, array $params = []): Response
    {
        $back = url('atak/sse/dossiers');

        if (!$this->canGrant() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect($back);
        }

        $tenantId = $this->tenantId();
        $enable = (string) $request->input('enable', '0') === '1';

        // Deux réglages sur la même bascule : le verrou d'ouverture et le
        // caviardage des écrans de travail. Ils se décident au même endroit mais
        // ne s'arment pas ensemble — fermer un dossier et noircir un registre
        // n'ont pas les mêmes conséquences sur le travail quotidien.
        $which = (string) $request->input('reglage', 'verrou');
        $key = $which === 'ecrans'
            ? SsePortalSettingsRepository::WORKING_REDACTION
            : SsePortalSettingsRepository::CASE_LOCK;

        $this->settings->setBool($tenantId, $key, $enable, (int) Session::get('user_id') ?: null);

        $labels = $which === 'ecrans'
            ? [
                'on' => 'Caviardage des écrans de travail ARMÉ : registre, fiche dossier et corrélations sont rabattus sur l’habilitation du lecteur.',
                'off' => 'Caviardage des écrans de travail DÉSARMÉ : les écrans de travail redeviennent intégraux.',
                'flash_on' => 'Écrans de travail caviardés. Les documents de diffusion l’étaient déjà.',
                'flash_off' => 'Écrans de travail intégraux. Les documents de diffusion restent rabattus.',
            ]
            : [
                'on' => 'Verrou d’ouverture par classification ARMÉ : les dossiers au-dessus de l’habilitation d’un lecteur ne s’ouvrent plus.',
                'off' => 'Verrou d’ouverture par classification DÉSARMÉ : la classification redevient un signalement.',
                'flash_on' => 'Verrou armé. Les dossiers au-dessus de l’habilitation d’un lecteur ne s’ouvrent plus pour lui.',
                'flash_off' => 'Verrou désarmé. La classification redevient un signalement, sans fermeture.',
            ];

        $this->activityLog->record(
            $tenantId,
            1,
            'SSE_CLEARANCE',
            $enable ? $labels['on'] : $labels['off'],
            (string) (Session::get('display_name') ?? Session::get('callsign') ?? 'Portail')
        );

        Session::flash('success', $enable ? $labels['flash_on'] : $labels['flash_off']);

        return Response::redirect($back);
    }

    /**
     * Déclassification : version diffusable du dossier à un niveau donné.
     *
     * Le texte caviardé est remplacé côté serveur. Une barre obtenue en CSS
     * laisserait le texte dans la page — copier-coller, code source, lecteur
     * d'écran — ce qui reviendrait à ne rien caviarder du tout.
     */
    public function caseDeclassify(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $case = $this->requireCase($id);
        if ($case === null) {
            Session::flash('error', 'Dossier introuvable ou hors de votre périmètre.');

            return Response::redirect(url('atak/sse/dossiers'));
        }

        $tenantId = $this->tenantId();

        $requested = (string) ($request->query('niveau') ?? '');
        if (!isset(SseRedactionService::LEVELS[$requested])) {
            // Sans demande explicite, le niveau le plus large : c'est celui qui
            // caviarde le plus. Ouvrir la page ne doit jamais exposer davantage
            // que ce qu'on a demandé à voir.
            $requested = SseCaseRepository::CLASS_INTERNAL;
        }

        // Le paramètre d'URL exprime un souhait, il n'accorde rien. Le plafond
        // d'habilitation de la session a toujours le dernier mot.
        $level = $this->clearance->clamp($requested);
        $maxLevel = $this->clearance->maxLevel();
        $refused = $requested !== $level;

        if ($refused) {
            $this->activityLog->record(
                $tenantId,
                1,
                'SSE_CLEARANCE',
                sprintf(
                    'Lecture « %s » demandée sur le dossier %s, servie en « %s » (habilitation insuffisante).',
                    SseRedactionService::levelLabel($requested),
                    (string) ($case['reference_code'] ?? ''),
                    SseRedactionService::levelLabel($level)
                ),
                (string) (Session::get('display_name') ?? Session::get('callsign') ?? 'Portail')
            );
        }

        $data = $this->reports->gatherForRelease($id, $tenantId, $level);

        return $this->portalView('atak.sse.case_declassify', [
            'title' => 'Déclassification — ' . ($case['reference_code'] ?? ''),
            'case' => $case,
            'level' => $level,
            'maxLevel' => $maxLevel,
            'clearanceOrigin' => $this->clearance->origin(),
            'clearanceRefused' => $refused,
            'requestedLevel' => $requested,
            'caseAboveClearance' => $this->clearance->caseAboveClearance($case),
            'levels' => SseCaseRepository::CLASSIFICATION_LABELS,
            'categories' => SseRedactionService::CATEGORIES,
            'summary' => SseRedactionService::summarise($level),
            'people' => $data['people'] ?? [],
            'sites' => $data['sites'] ?? [],
            'flash' => $this->reports->buildFlashReport($id, $tenantId, $level),
            'initial' => $this->reports->buildInitialReport($id, $tenantId, $level),
            'manual' => $this->redaction->listForCase($id, $tenantId),
            'canManage' => $this->canManage(),
            'canGrant' => $this->canGrant(),
            'canExport' => $this->canExport(),
            'activeNav' => 'dossiers',
        ]);
    }

    /** Pose un trait noir sur une zone précise. */
    public function caseRedactionStore(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $back = url('atak/sse/dossiers/' . $id . '/declassification');

        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect($back);
        }

        // Le formulaire présente « fiche » et « zone » séparément parce que c'est ainsi
        // qu'on y pense. La recomposition est faite ici et non en JavaScript : un
        // caviardage doit fonctionner même sans script, sinon la zone reste en clair
        // sans que personne s'en aperçoive.
        [$type, $targetId] = array_pad(explode(':', (string) $request->input('target', ''), 2), 2, '');
        [$field, $category] = array_pad(explode('|', (string) $request->input('field_pair', ''), 2), 2, '');

        $ok = $this->redaction->add($this->tenantId(), $id, [
            'target_type' => $type !== '' ? $type : (string) $request->input('target_type', 'person'),
            'target_id' => $targetId !== '' ? (int) $targetId : (int) $request->input('target_id', 0),
            'field' => $field !== '' ? $field : (string) $request->input('field', ''),
            'category' => $category !== '' ? $category : (string) $request->input('category', 'identite'),
            'reason' => (string) $request->input('reason', ''),
            'author_label' => (string) (Session::get('display_name') ?? Session::get('callsign') ?? 'Analyste'),
        ]);

        Session::flash($ok ? 'success' : 'error', $ok
            ? 'Zone caviardée.'
            : 'Caviardage refusé — vérifiez la fiche et la zone désignées.');

        return Response::redirect($back);
    }

    /** Retire un trait noir. */
    public function caseRedactionDelete(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $back = url('atak/sse/dossiers/' . $id . '/declassification');

        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect($back);
        }

        $this->redaction->remove((int) ($params['redactionId'] ?? 0), $this->tenantId());
        Session::flash('success', 'Caviardage retiré — la zone redevient lisible aux niveaux qui l’autorisent.');

        return Response::redirect($back);
    }

    /** Graphe de corrélation du dossier. */
    public function caseCorrelations(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $case = $this->requireCase($id);
        if ($case === null) {
            Session::flash('error', 'Dossier introuvable ou hors de votre périmètre.');

            return Response::redirect(url('atak/sse/dossiers'));
        }

        $graph = $this->correlation->graphForCase($id, $this->tenantId());

        // Le graphe désigne les personnes par leur identité : même régime que les
        // autres écrans de travail.
        if ($this->clearance->workingRedactionEnabled($this->tenantId())) {
            foreach ($graph['nodes'] as $key => $node) {
                if (($node['type'] ?? '') !== 'person') {
                    continue;
                }
                $graph['nodes'][$key]['label'] = SseRedactionService::bar((string) ($node['label'] ?? ''));
            }
        }

        return $this->portalView('atak.sse.case_correlations', [
            'title' => 'Corrélations — ' . ($case['reference_code'] ?? ''),
            'case' => $case,
            'nodes' => $graph['nodes'],
            'edges' => $graph['edges'],
            'stored' => $this->correlation->listStored($id, $this->tenantId()),
            'relationLabels' => SseCorrelationService::RELATION_LABELS,
            'reliabilityLabels' => SseCorrelationService::RELIABILITY_LABELS,
            'canManage' => $this->canManage(),
            'canGrant' => $this->canGrant(),
            'canExport' => $this->canExport(),
            'activeNav' => 'dossiers',
        ]);
    }

    /** Pose une relation d'analyste sur le dossier. */
    public function caseRelationStore(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $back = url('atak/sse/dossiers/' . $id . '/correlations');

        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect($back);
        }

        $ok = $this->correlation->addRelation($this->tenantId(), $id, [
            'from_type' => 'person',
            'from_id' => (int) $request->input('from_id', 0),
            'to_type' => 'person',
            'to_id' => (int) $request->input('to_id', 0),
            'relation' => (string) $request->input('relation', 'associe'),
            'reliability' => (string) $request->input('reliability', 'unverified'),
            'note' => (string) $request->input('note', ''),
            'author_label' => (string) (Session::get('display_name') ?? Session::get('callsign') ?? 'Analyste'),
        ]);

        Session::flash($ok ? 'success' : 'error', $ok
            ? 'Relation enregistrée.'
            : 'Relation refusée — vérifiez les deux personnes désignées.');

        return Response::redirect($back);
    }

    /** Retire une relation posée. */
    public function caseRelationDelete(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $back = url('atak/sse/dossiers/' . $id . '/correlations');

        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect($back);
        }

        $this->correlation->deleteRelation((int) ($params['relationId'] ?? 0), $this->tenantId());
        Session::flash('success', 'Relation retirée.');

        return Response::redirect($back);
    }

    public function sitesIndex(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        $list = $this->sites->listForContext($tenantId, 1, [
            'status' => $request->query('status'),
            'site_type' => $request->query('site_type'),
            'limit' => 100,
        ]);
        $counts = $this->sites->countsForSites(
            array_map(static fn (array $s): int => (int) ($s['id'] ?? 0), $list),
            $tenantId
        );

        return $this->portalView('atak.sse.sites', [
            'title' => 'Sites exploités',
            'sites' => $list,
            'siteCounts' => $counts,
            'statuses' => SseSiteRepository::STATUS_LABELS,
            'types' => SseSiteRepository::TYPE_LABELS,
            'filters' => [
                'status' => (string) $request->query('status', ''),
                'site_type' => (string) $request->query('site_type', ''),
            ],
            'canManage' => $this->canManage(),
            'canGrant' => $this->canGrant(),
            'canExport' => $this->canExport(),
            'activeNav' => 'sites',
        ]);
    }

    public function siteShow(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        $site = $this->sites->findById((int) ($params['id'] ?? 0), $tenantId);
        if ($site === null) {
            Session::flash('error', 'Site introuvable.');

            return Response::redirect(url('atak/sse/sites'));
        }

        return $this->portalView('atak.sse.site_show', [
            'title' => 'Site — ' . ($site['reference_code'] ?? ''),
            'site' => $site,
            'fiveLine' => $this->sites->buildFiveLineReport($site),
            'seizureCategories' => SseSiteRepository::SEIZURE_LABELS,
            'canManage' => $this->canManage(),
            'canGrant' => $this->canGrant(),
            'canExport' => $this->canExport(),
            'activeNav' => 'sites',
        ]);
    }

    public function siteRoomToggle(Request $request, array $params = []): Response
    {
        $siteId = (int) ($params['id'] ?? 0);
        $back = url('atak/sse/sites/' . $siteId);

        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect($back);
        }

        $roomId = (int) ($params['roomId'] ?? 0);
        $checked = (string) $request->input('checked', '0') === '1';
        if (!$this->sites->setRoomChecked($roomId, $this->tenantId(), $checked, null)) {
            Session::flash('error', 'Pièce introuvable.');

            return Response::redirect($back);
        }
        Session::flash('success', $checked ? 'Pièce marquée fouillée.' : 'Pièce remise en attente.');

        return Response::redirect($back);
    }

    public function siteCloseAction(Request $request, array $params = []): Response
    {
        $siteId = (int) ($params['id'] ?? 0);
        $back = url('atak/sse/sites/' . $siteId);

        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect($back);
        }

        $tenantId = $this->tenantId();
        $site = $this->sites->findById($siteId, $tenantId);
        if ($site === null) {
            Session::flash('error', 'Site introuvable.');

            return Response::redirect(url('atak/sse/sites'));
        }

        $summary = trim((string) $request->input('summary', ''));
        if ($summary === '') {
            $summary = $this->sites->buildFiveLineReport($site);
        }
        $actor = (string) (Session::get('callsign') ?? Session::get('display_name') ?? 'Commandement');
        $this->sites->close($siteId, $tenantId, $summary, $actor);
        Session::flash('success', 'Site clôturé — compte rendu enregistré.');

        return Response::redirect($back);
    }

    public function crossIndex(Request $request, array $params = []): Response
    {
        $matches = $this->cross->matchPersonsAgainstWatchlist($this->tenantId());
        $entries = $this->watchlist->listActive($this->tenantId());

        return $this->portalView('atak.sse.cross', [
            'title' => 'Croisements — listes de surveillance',
            'matches' => $matches,
            'entries' => $entries,
            'canManage' => $this->canManage(),
            'canGrant' => $this->canGrant(),
            'canExport' => $this->canExport(),
            'activeNav' => 'croisements',
        ]);
    }

    public function watchlistStore(Request $request, array $params = []): Response
    {
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('atak/sse/croisements'));
        }
        $this->watchlist->create([
            'tenant_id' => $this->tenantId(),
            'last_name' => (string) $request->input('last_name', ''),
            'first_name' => (string) $request->input('first_name', ''),
            'alias' => (string) $request->input('alias', ''),
            'threat_level' => (string) $request->input('threat_level', 'surveillance'),
            'notes' => (string) $request->input('notes', ''),
        ]);
        Session::flash('success', 'Entrée ajoutée à la liste de surveillance.');

        return Response::redirect(url('atak/sse/croisements'));
    }

    public function accessAdmin(Request $request, array $params = []): Response
    {
        if (!$this->canGrant()) {
            Session::flash('error', 'Seul le commandement peut délivrer des codes d’accès.');

            return Response::redirect(url('atak/sse/dossiers'));
        }
        $cases = $this->cases->listForTenant($this->tenantId(), null);
        $actionLog = $this->buildSseActionLog($this->tenantId());

        return $this->portalView('atak.sse.access', [
            'title' => 'Codes d’accès temporaires',
            'codes' => $this->codes->listActiveForTenant($this->tenantId()),
            'cases' => $cases,
            'actionLog' => $actionLog,
            'issuedPlain' => Session::getFlash('sse_issued_code'),
            'canManage' => $this->canManage(),
            'canGrant' => true,
            'canExport' => $this->canExport(),
            'activeNav' => 'acces',
        ]);
    }

    public function accessIssue(Request $request, array $params = []): Response
    {
        if (!$this->canGrant() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('atak/sse/acces'));
        }
        $result = $this->access->issue(
            $this->tenantId(),
            (int) Session::get('user_id'),
            trim((string) $request->input('label', 'Accès temporaire')),
            (string) $request->input('grant_type', 'member'),
            (int) $request->input('ttl_hours', 4),
            (int) $request->input('session_ttl_minutes', 240),
            (int) $request->input('max_uses', 1),
            ((int) $request->input('case_id', 0)) ?: null,
            (string) $request->input('clearance_level', SseCaseRepository::CLASS_INTERNAL)
        );
        if ($result['ok']) {
            Session::flash('sse_issued_code', $result['plain']);
            Session::flash('success', 'Code créé. Communiquez-le par un canal sécurisé — il ne sera plus réaffiché.');
        } else {
            Session::flash('error', $result['message'] ?? 'Impossible de créer le code.');
        }

        return Response::redirect(url('atak/sse/acces'));
    }

    public function accessRevoke(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if (!$this->canGrant() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('atak/sse/acces'));
        }
        $this->codes->revoke($id, $this->tenantId());
        $this->codes->logEvent($this->tenantId(), 'revoke', $id, null, (int) Session::get('user_id'), null, null);
        Session::flash('success', 'Code révoqué.');

        return Response::redirect(url('atak/sse/acces'));
    }

    /* ------------------------------------------------------------------
     * Toiles de données (data mesh)
     * ----------------------------------------------------------------*/

    public function meshesIndex(Request $request, array $params = []): Response
    {
        $list = $this->meshes->listForTenant($this->tenantId(), [
            'status' => $request->query('status'),
            'q' => $request->query('q'),
        ]);
        $counts = $this->meshes->countsForMeshes(
            array_map(static fn (array $m): int => (int) ($m['id'] ?? 0), $list),
            $this->tenantId()
        );

        return $this->portalView('atak.sse.meshes', [
            'title' => 'Investigations — graphe relationnel',
            'meshes' => $list,
            'meshCounts' => $counts,
            'filters' => [
                'status' => (string) $request->query('status', ''),
                'q' => (string) $request->query('q', ''),
            ],
            'statuses' => SseMeshRepository::STATUS_LABELS,
            'canManage' => $this->canManage(),
            'activeNav' => 'toiles',
        ]);
    }

    public function meshCreateForm(Request $request, array $params = []): Response
    {
        if (!$this->canManage()) {
            Session::flash('error', 'Vous n’êtes pas habilité à ouvrir une investigation.');

            return Response::redirect(url('atak/sse/toiles'));
        }
        $cases = $this->cases->listForTenant($this->tenantId(), $this->access->caseScope(), [
            'is_folder' => false,
        ]);

        return $this->portalView('atak.sse.mesh_form', [
            'title' => 'Ouvrir une investigation',
            'cases' => $cases,
            'classifications' => SseCaseRepository::CLASSIFICATION_LABELS,
            'seedCaseId' => (int) $request->query('case', 0),
            'canManage' => true,
            'activeNav' => 'toiles',
        ]);
    }

    public function meshStore(Request $request, array $params = []): Response
    {
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('atak/sse/toiles'));
        }
        $title = trim((string) $request->input('title', ''));
        if ($title === '') {
            Session::flash('error', 'Indiquez un intitulé pour la toile.');

            return Response::redirect(url('atak/sse/toiles/nouveau'));
        }
        $caseId = (int) $request->input('case_id', 0);
        if ($caseId > 0 && $this->requireCase($caseId) === null) {
            Session::flash('error', 'Dossier source introuvable ou hors périmètre.');

            return Response::redirect(url('atak/sse/toiles/nouveau'));
        }
        $id = $this->meshes->create([
            'tenant_id' => $this->tenantId(),
            'title' => $title,
            'summary' => (string) $request->input('summary', ''),
            'case_id' => $caseId > 0 ? $caseId : null,
            'classification' => (string) $request->input('classification', 'encadrement'),
            'created_by' => (int) Session::get('user_id') ?: null,
        ]);
        $seed = !empty($request->input('seed_from_case')) && $caseId > 0;
        if ($seed) {
            $stats = $this->meshService->seedFromCase($id, $caseId, $this->tenantId());
            Session::flash(
                'success',
                sprintf(
                    'Toile créée avec %d entités et %d liens importés du dossier.',
                    $stats['nodes'],
                    $stats['edges']
                )
            );
        } else {
            Session::flash('success', 'Toile créée. Ajoutez des entités sur le canevas.');
        }

        return Response::redirect(url('atak/sse/toiles/' . $id));
    }

    public function meshShow(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $mesh = $this->meshes->findById($id, $this->tenantId());
        if ($mesh === null) {
            Session::flash('error', 'Toile introuvable.');

            return Response::redirect(url('atak/sse/toiles'));
        }
        $nodes = $this->meshes->listNodes($id, $this->tenantId());
        $edges = $this->meshes->listEdges($id, $this->tenantId());
        $histogram = [];
        foreach ($nodes as $n) {
            $k = (string) ($n['kind'] ?? 'custom');
            $histogram[$k] = ($histogram[$k] ?? 0) + 1;
        }
        arsort($histogram);
        $case = null;
        if (!empty($mesh['case_id'])) {
            $case = $this->cases->findById((int) $mesh['case_id'], $this->tenantId());
        }

        return $this->portalView('atak.sse.mesh_show', [
            'title' => ($mesh['reference_code'] ?? '') . ' — ' . ($mesh['title'] ?? ''),
            'mesh' => $mesh,
            'case' => $case,
            'nodes' => $nodes,
            'edges' => $edges,
            'histogram' => $histogram,
            'kindLabels' => SseMeshRepository::KIND_LABELS,
            'relationLabels' => SseMeshRepository::RELATION_LABELS,
            'reliabilityLabels' => SseMeshRepository::EDGE_STATUS_LABELS,
            'statuses' => SseMeshRepository::STATUS_LABELS,
            'classifications' => SseCaseRepository::CLASSIFICATION_LABELS,
            'canManage' => $this->canManage(),
            'activeNav' => 'toiles',
            'sseMeshEditor' => true,
        ]);
    }

    public function meshUpdate(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('atak/sse/toiles/' . $id));
        }
        if ($this->meshes->findById($id, $this->tenantId()) === null) {
            return Response::redirect(url('atak/sse/toiles'));
        }
        $this->meshes->update($id, $this->tenantId(), [
            'title' => (string) $request->input('title', ''),
            'summary' => (string) $request->input('summary', ''),
            'classification' => (string) $request->input('classification', 'encadrement'),
            'status' => (string) $request->input('status', 'ouvert'),
        ]);
        Session::flash('success', 'Toile mise à jour.');

        return Response::redirect(url('atak/sse/toiles/' . $id));
    }

    public function meshAddNode(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('atak/sse/toiles/' . $id));
        }
        if ($this->meshes->findById($id, $this->tenantId()) === null) {
            return Response::redirect(url('atak/sse/toiles'));
        }
        $label = trim((string) $request->input('label', ''));
        if ($label === '') {
            Session::flash('error', 'Indiquez un libellé pour l’entité.');

            return Response::redirect(url('atak/sse/toiles/' . $id));
        }
        $this->meshes->addNode($id, $this->tenantId(), [
            'kind' => (string) $request->input('kind', 'custom'),
            'label' => $label,
            'detail' => (string) $request->input('detail', ''),
            'pos_x' => (float) $request->input('pos_x', 320),
            'pos_y' => (float) $request->input('pos_y', 220),
        ]);
        Session::flash('success', 'Entité ajoutée à la toile.');

        return Response::redirect(url('atak/sse/toiles/' . $id));
    }

    public function meshAddEdge(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('atak/sse/toiles/' . $id));
        }
        if ($this->meshes->findById($id, $this->tenantId()) === null) {
            return Response::redirect(url('atak/sse/toiles'));
        }
        $from = (int) $request->input('from_node_id', 0);
        $to = (int) $request->input('to_node_id', 0);
        if ($from < 1 || $to < 1 || $from === $to) {
            Session::flash('error', 'Choisissez deux entités distinctes.');

            return Response::redirect(url('atak/sse/toiles/' . $id));
        }
        $this->meshes->addEdge($id, $this->tenantId(), [
            'from_node_id' => $from,
            'to_node_id' => $to,
            'relation' => (string) $request->input('relation', 'associe'),
            'note' => (string) $request->input('note', ''),
            'reliability' => (string) $request->input('reliability', 'unverified'),
            'created_by' => (int) Session::get('user_id') ?: null,
            'author_label' => (string) (Session::get('sse_guest_label') ?? Session::get('display_name') ?? 'Analyste'),
        ]);
        Session::flash('success', 'Lien enregistré sur la toile.');

        return Response::redirect(url('atak/sse/toiles/' . $id));
    }

    public function meshDeleteNode(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $nodeId = (int) ($params['nodeId'] ?? 0);
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('atak/sse/toiles/' . $id));
        }
        $this->meshes->deleteNode($nodeId, $this->tenantId());
        Session::flash('success', 'Entité retirée de la toile.');

        return Response::redirect(url('atak/sse/toiles/' . $id));
    }

    public function meshDeleteEdge(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $edgeId = (int) ($params['edgeId'] ?? 0);
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('atak/sse/toiles/' . $id));
        }
        $this->meshes->deleteEdge($edgeId, $this->tenantId());
        Session::flash('success', 'Lien retiré.');

        return Response::redirect(url('atak/sse/toiles/' . $id));
    }

    public function meshSaveLayout(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            return Response::json(['ok' => false, 'message' => 'Action non autorisée.'], 403);
        }
        if ($this->meshes->findById($id, $this->tenantId()) === null) {
            return Response::json(['ok' => false, 'message' => 'Toile introuvable.'], 404);
        }
        $positions = $request->input('positions', []);
        if (!is_array($positions)) {
            $raw = (string) $request->input('positions_json', '');
            $decoded = json_decode($raw, true);
            $positions = is_array($decoded) ? $decoded : [];
        }
        $saved = 0;
        foreach ($positions as $row) {
            if (!is_array($row)) {
                continue;
            }
            $nid = (int) ($row['id'] ?? 0);
            if ($nid < 1) {
                continue;
            }
            if ($this->meshes->updateNodePosition($nid, $this->tenantId(), (float) ($row['x'] ?? 0), (float) ($row['y'] ?? 0))) {
                $saved++;
            }
        }

        return Response::json(['ok' => true, 'saved' => $saved]);
    }

    public function operations(Request $request, array $params = []): Response
    {
        $tower = $this->workspace->controlTower($this->tenantId(), $this->access->caseScope());

        return $this->portalView('atak.sse.operations', [
            'title' => 'Vue opérationnelle — Control Tower',
            'tower' => $tower,
            'canManage' => $this->canManage(),
            'canGrant' => $this->canGrant(),
            'activeNav' => 'operations',
        ]);
    }

    public function search(Request $request, array $params = []): Response
    {
        $q = trim((string) $request->query('q', ''));
        $results = [];
        try {
            $results = $this->workspace->globalSearch($this->tenantId(), $q, $this->access->caseScope());
        } catch (\Throwable $e) {
            error_log('[sse.search] ' . $e->getMessage() . ' — ' . $e->getFile() . ':' . $e->getLine());
            Session::flash('error', 'La recherche n’a pas pu aboutir. Réessayez dans un instant.');
        }

        return $this->portalView('atak.sse.search', [
            'title' => 'Recherche — ' . ($q !== '' ? $q : 'SSE'),
            'q' => $q,
            'results' => is_array($results) ? $results : [],
            'canManage' => $this->canManage(),
            'activeNav' => 'operations',
        ]);
    }

    public function identitiesIndex(Request $request, array $params = []): Response
    {
        return $this->personsIndex($request, array_merge($params, ['_nav' => 'identites']));
    }

    public function identityShow(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $person = $this->persons->findById($id, $this->tenantId());
        if ($person === null) {
            Session::flash('error', 'Identité introuvable.');

            return Response::redirect(url('atak/sse/identites'));
        }
        $people = $this->clearance->redactPeopleForScreens([$person], $this->tenantId(), null);
        $person = $people[0] ?? $person;

        $tech = !empty($person['biometrics_simulated']) ? 72 : 48;
        $corro = !empty($person['primary_photo']) ? 55 : 25;
        $global = (int) min(95, round(($tech + $corro + 40) / 2.2));
        $stamp = (string) ($person['created_at'] ?? '');
        $timeline = [
            [
                'at' => strlen($stamp) >= 16 ? substr($stamp, 11, 5) : date('H:i'),
                'title' => 'Création / import fiche',
                'detail' => 'Fiche identité enregistrée dans le registre SSE',
                'kind' => 'fait',
            ],
        ];
        if (!empty($person['biometrics_simulated'])) {
            $timeline[] = [
                'at' => strlen($stamp) >= 16 ? substr($stamp, 11, 5) : date('H:i'),
                'title' => 'Relevé biométrique',
                'detail' => 'Biométrie simulée associée à la fiche',
                'kind' => 'observation',
            ];
        }

        return $this->portalView('atak.sse.person_show', [
            'title' => 'IDN-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT) . ' — ' . (string) ($person['display_name'] ?? ''),
            'person' => $person,
            'objectMeta' => [
                'ref' => 'IDN-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT),
                'priority' => $global >= 70 ? 'élevée' : 'normale',
                'classification' => 'Confidentiel',
                'last_seen' => (string) ($person['updated_at'] ?? $person['created_at'] ?? '—'),
                'bio_prints' => !empty($person['biometrics_simulated']) ? 'Relevées (sim.)' : 'Non relevées',
                'bio_iris' => !empty($person['biometrics_simulated']) ? 'Relevé (sim.)' : 'Non relevé',
                'terminal' => 'SEEK / ATAK',
                'collector' => (string) ($person['submitter_callsign'] ?? '—'),
                'source' => 'Terminal terrain',
                'import' => 'Automatique',
                'integrity' => 'Vérifiée',
            ],
            'relationCounts' => [
                'Personnes' => 0,
                'Véhicules' => 0,
                'Sites' => 0,
                'Terminaux' => 1,
                'Organisations' => !empty($person['affiliation']) ? 1 : 0,
                'Événements' => count($timeline),
            ],
            'confidence' => [
                'source' => 'C',
                'credibility' => '3',
                'technical' => $tech,
                'corroboration' => $corro,
                'global' => $global,
            ],
            'reasoning' => [
                'conclusion' => 'Identité non confirmée — éléments partiels, à croiser.',
                'confidence_label' => $global >= 70 ? 'Élevée' : ($global >= 45 ? 'Moyenne' : 'Faible'),
                'pros' => array_values(array_filter([
                    !empty($person['primary_photo']) ? 'Photographie faciale disponible' : null,
                    !empty($person['biometrics_simulated']) ? 'Relevé biométrique présent' : null,
                    !empty($person['affiliation']) ? 'Affiliation déclarée' : null,
                ])),
                'cons' => array_values(array_filter([
                    empty($person['nationality']) ? 'Nationalité non confirmée' : null,
                    empty($person['id_document_present']) ? 'Document d’identité absent' : null,
                    'Biométrie incomplète ou simulée',
                ])),
                'revised_at' => gmdate('d/m/Y H:i') . 'Z',
                'analyst' => 'Cellule SSE',
            ],
            'timeline' => $timeline,
            'provenance' => [
                [
                    'at' => strlen($stamp) >= 16 ? substr($stamp, 11, 5) : date('H:i'),
                    'text' => 'Donnée créée / importée depuis le terminal',
                ],
            ],
            'canManage' => $this->canManage(),
            'activeNav' => 'identites',
        ]);
    }

    public function objectRegistry(Request $request, array $params = []): Response
    {
        $type = (string) ($params['type'] ?? $request->query('type', 'custom'));
        $map = [
            'organisations' => [
                'kinds' => ['organization'],
                'label' => 'Organisations',
                'hint' => 'Groupes, cellules et structures affiliées — réutilisables dans les dossiers et investigations.',
                'empty' => 'Aucune organisation enregistrée pour le moment. Créez-en une ou rattachez-en depuis une investigation.',
            ],
            'vehicules' => [
                'kinds' => ['vehicle'],
                'label' => 'Véhicules',
                'hint' => 'Plaques, types et observations — liables aux identités, sites et chronologies.',
                'empty' => 'Aucun véhicule au registre. Ajoutez-en depuis une investigation ou une collecte terrain.',
            ],
            'materiels' => [
                'kinds' => ['weapon', 'phone', 'terminal', 'custom'],
                'label' => 'Matériels',
                'hint' => 'Armes, terminaux et équipements saisis ou observés.',
                'empty' => 'Aucun matériel listé. Les saisies terrain et l’exploitation numérique alimentent ce registre.',
            ],
            'documents' => [
                'kinds' => ['document', 'report', 'photo'],
                'label' => 'Documents',
                'hint' => 'Pièces d’identité, notes et pièces versées aux dossiers.',
                'empty' => 'Aucun document au registre. Importez une pièce depuis un dossier ou une investigation.',
            ],
        ];
        $info = $map[$type] ?? [
            'kinds' => ['custom'],
            'label' => 'Objets',
            'hint' => 'Registre générique d’objets métier SSE.',
            'empty' => 'Aucun objet de ce type.',
        ];
        $objects = $this->meshes->listNodesByKindForTenant($this->tenantId(), $info['kinds']);

        return $this->portalView('atak.sse.object_registry', [
            'title' => $info['label'] . ' — Objets SSE',
            'objectType' => $type,
            'objectKind' => $info['kinds'][0] ?? 'custom',
            'objectLabel' => $info['label'],
            'objectHint' => $info['hint'],
            'objectEmpty' => $info['empty'],
            'objects' => $objects,
            'canManage' => $this->canManage(),
            'activeNav' => 'objets',
        ]);
    }

    public function objectCreateForm(Request $request, array $params = []): Response
    {
        if (!$this->canManage()) {
            return Response::redirect(url('atak/sse/operations'));
        }

        return $this->portalView('atak.sse.object_create', [
            'title' => 'Créer un objet',
            'type' => (string) $request->query('type', 'person'),
            'kinds' => SseMeshRepository::KIND_LABELS,
            'metaSchema' => SseMeshRepository::metaSchema(),
            'classifications' => SseCaseRepository::CLASSIFICATION_LABELS,
            'canManage' => true,
            'activeNav' => 'objets',
        ]);
    }

    public function objectStore(Request $request, array $params = []): Response
    {
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('atak/sse/objets/nouveau'));
        }
        $label = trim((string) $request->input('label', ''));
        if ($label === '') {
            Session::flash('error', 'Indiquez un libellé.');

            return Response::redirect(url('atak/sse/objets/nouveau'));
        }
        $kind = SseMeshRepository::normalizeKind((string) $request->input('kind', 'custom'));
        $metaRaw = $request->input('meta', []);
        $meta = [];
        if (is_array($metaRaw)) {
            foreach ($metaRaw as $k => $v) {
                $meta[(string) $k] = trim((string) $v);
            }
        }
        $metaLines = SseMeshRepository::formatMetaLines($kind, $meta);
        $freeDetail = trim((string) $request->input('detail', ''));
        $detailParts = $metaLines;
        if ($freeDetail !== '') {
            $detailParts[] = $freeDetail;
        }
        $detail = implode(' · ', $detailParts);
        if (mb_strlen($detail) > 250) {
            $detail = mb_substr($detail, 0, 247) . '…';
        }

        $meshId = $this->meshes->create([
            'tenant_id' => $this->tenantId(),
            'title' => 'Investigation — ' . $label,
            'summary' => 'Toile ouverte depuis la création d’objet « ' . $label . ' ».',
            'classification' => (string) $request->input('classification', 'confidentiel'),
            'created_by' => (int) Session::get('user_id') ?: null,
        ]);
        $this->meshes->addNode($meshId, $this->tenantId(), [
            'kind' => $kind,
            'label' => $label,
            'detail' => $detail,
            'meta_json' => SseMeshRepository::encodeMetaJson($meta),
            'pos_x' => 420,
            'pos_y' => 260,
        ]);
        Session::flash('success', 'Objet créé avec ses caractéristiques, placé dans une nouvelle investigation.');

        return Response::redirect(url('atak/sse/toiles/' . $meshId));
    }

    public function timeline(Request $request, array $params = []): Response
    {
        $tower = $this->workspace->controlTower($this->tenantId(), $this->access->caseScope());
        $events = [];
        foreach ($tower['activity'] as $row) {
            $events[] = [
                'at' => $row['at'] ?? '',
                'title' => 'Activité registre',
                'detail' => $row['text'] ?? '',
                'source' => 'Athena SSE',
                'kind' => $row['kind'] ?? 'observation',
            ];
        }

        return $this->portalView('atak.sse.timeline', [
            'title' => 'Chronologie unifiée',
            'events' => $events,
            'canManage' => $this->canManage(),
            'activeNav' => 'chronologie',
        ]);
    }

    public function anomalies(Request $request, array $params = []): Response
    {
        $tower = $this->workspace->controlTower($this->tenantId(), $this->access->caseScope());

        return $this->portalView('atak.sse.anomalies', [
            'title' => 'Anomalies',
            'anomalies' => $tower['alerts'] ?? [],
            'canManage' => $this->canManage(),
            'activeNav' => 'anomalies',
        ]);
    }

    public function validationQueue(Request $request, array $params = []): Response
    {
        $tower = $this->workspace->controlTower($this->tenantId(), $this->access->caseScope());

        return $this->portalView('atak.sse.validation', [
            'title' => 'Files de validation',
            'tower' => $tower,
            'canManage' => $this->canManage(),
            'activeNav' => 'validation',
        ]);
    }

    public function reportsHub(Request $request, array $params = []): Response
    {
        $cases = $this->cases->listForTenant($this->tenantId(), $this->access->caseScope(), [
            'status' => 'clos',
        ]);
        $recentClosed = [];
        foreach ($cases as $c) {
            if (!empty($c['is_folder'])) {
                continue;
            }
            $recentClosed[] = $c;
            if (count($recentClosed) >= 8) {
                break;
            }
        }
        if ($recentClosed === []) {
            foreach ($this->cases->listForTenant($this->tenantId(), $this->access->caseScope()) as $c) {
                if (!empty($c['is_folder'])) {
                    continue;
                }
                $recentClosed[] = $c;
                if (count($recentClosed) >= 8) {
                    break;
                }
            }
        }

        return $this->portalView('atak.sse.reports', [
            'title' => 'Rapports',
            'recentCases' => $recentClosed,
            'recentDocuments' => $this->documents->listForTenant($this->tenantId(), []),
            'canManage' => $this->canManage(),
            'activeNav' => 'rapports',
        ]);
    }

    /** Manuel opérateur HTML (documentation intégrée au bureau). */
    public function guide(Request $request, array $params = []): Response
    {
        return Response::view('atak.sse.guide.index', [
            'title' => 'Manuel SSE — Bureau de renseignement',
            'sseGuideRevision' => 1,
            'sseGuideRevisionLabel' => '6 août 2026',
            'ssePortalUrl' => url('atak/sse/operations'),
            'sseLabUrl' => url('atak/sse/exploitation-numerique'),
            'sseAccessUrl' => url('atak/sse/acces'),
            'canGrant' => $this->canGrant(),
        ]);
    }

    public function documentsIndex(Request $request, array $params = []): Response
    {
        $filters = [
            'status' => (string) $request->query('status', ''),
            'document_type' => (string) $request->query('type', ''),
            'q' => (string) $request->query('q', ''),
        ];

        return $this->portalView('atak.sse.documents', [
            'title' => 'Atelier de rédaction',
            'documents' => $this->documents->listForTenant($this->tenantId(), $filters),
            'typeLabels' => SseDocumentRepository::TYPE_LABELS,
            'statusLabels' => SseDocumentRepository::STATUS_LABELS,
            'filterStatus' => $filters['status'],
            'filterType' => $filters['document_type'],
            'filterQ' => $filters['q'],
            'canManage' => $this->canManage(),
            'activeNav' => 'documents',
        ]);
    }

    public function documentCreateForm(Request $request, array $params = []): Response
    {
        if (!$this->canManage()) {
            Session::flash('error', 'Vous n’avez pas l’habilitation pour rédiger un document.');

            return Response::redirect(url('atak/sse/documents'));
        }

        $type = SseDocumentRepository::normalizeType((string) $request->query('type', 'note_analyse'));
        $caseId = (int) $request->query('case_id', 0);
        $body = $this->documentTemplate($type);

        return $this->portalView('atak.sse.document_form', [
            'title' => 'Nouveau document SSE',
            'document' => null,
            'typeLabels' => SseDocumentRepository::TYPE_LABELS,
            'statusLabels' => SseDocumentRepository::STATUS_LABELS,
            'classifications' => SseCaseRepository::CLASSIFICATION_LABELS,
            'cases' => $this->cases->listForTenant($this->tenantId(), $this->access->caseScope()),
            'prefillType' => $type,
            'prefillCaseId' => $caseId,
            'prefillTitle' => '',
            'prefillBody' => $body,
            'prefillClass' => 'confidentiel',
            'prefillStatus' => 'brouillon',
            'canManage' => true,
            'activeNav' => 'documents',
        ]);
    }

    public function documentStore(Request $request, array $params = []): Response
    {
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('atak/sse/documents'));
        }

        $title = trim((string) $request->input('title', ''));
        $body = trim((string) $request->input('body', ''));
        if ($title === '' || $body === '') {
            Session::flash('error', 'Indiquez un intitulé et un corps de document.');

            return Response::redirect(url('atak/sse/documents/nouveau'));
        }

        $caseId = (int) $request->input('case_id', 0);
        if ($caseId > 0 && $this->requireCase($caseId) === null) {
            Session::flash('error', 'Le dossier lié est hors de votre périmètre.');

            return Response::redirect(url('atak/sse/documents/nouveau'));
        }

        $userId = (int) Session::get('user_id') ?: null;
        $author = (string) (Session::get('sse_guest_label') ?? Session::get('display_name') ?? 'Opérateur');
        $docId = $this->documents->create([
            'tenant_id' => $this->tenantId(),
            'case_id' => $caseId > 0 ? $caseId : null,
            'document_type' => (string) $request->input('document_type', 'note_analyse'),
            'title' => $title,
            'body' => $body,
            'classification' => (string) $request->input('classification', 'confidentiel'),
            'status' => (string) $request->input('status', 'brouillon'),
            'created_by' => $userId,
            'updated_by' => $userId,
            'author_label' => $author,
        ]);

        Session::flash('success', 'Document créé et placé en atelier.');

        return Response::redirect(url('atak/sse/documents/' . $docId));
    }

    public function documentShow(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $doc = $this->documents->findById($id, $this->tenantId());
        if ($doc === null) {
            Session::flash('error', 'Document introuvable.');

            return Response::redirect(url('atak/sse/documents'));
        }
        if (!empty($doc['case_id']) && $this->requireCase((int) $doc['case_id']) === null) {
            Session::flash('error', 'Document hors de votre périmètre.');

            return Response::redirect(url('atak/sse/documents'));
        }

        return $this->portalView('atak.sse.document_show', [
            'title' => (string) ($doc['title'] ?? 'Document'),
            'document' => $doc,
            'canManage' => $this->canManage(),
            'activeNav' => 'documents',
        ]);
    }

    public function documentEditForm(Request $request, array $params = []): Response
    {
        if (!$this->canManage()) {
            Session::flash('error', 'Vous n’avez pas l’habilitation pour modifier ce document.');

            return Response::redirect(url('atak/sse/documents'));
        }

        $id = (int) ($params['id'] ?? 0);
        $doc = $this->documents->findById($id, $this->tenantId());
        if ($doc === null) {
            Session::flash('error', 'Document introuvable.');

            return Response::redirect(url('atak/sse/documents'));
        }
        if (in_array((string) $doc['status'], ['valide', 'archive'], true)) {
            Session::flash('error', 'Ce document est figé. Ouvrez une nouvelle version si besoin.');

            return Response::redirect(url('atak/sse/documents/' . $id));
        }

        return $this->portalView('atak.sse.document_form', [
            'title' => 'Modifier — ' . ($doc['reference_code'] ?? ''),
            'document' => $doc,
            'typeLabels' => SseDocumentRepository::TYPE_LABELS,
            'statusLabels' => SseDocumentRepository::STATUS_LABELS,
            'classifications' => SseCaseRepository::CLASSIFICATION_LABELS,
            'cases' => $this->cases->listForTenant($this->tenantId(), $this->access->caseScope()),
            'canManage' => true,
            'activeNav' => 'documents',
        ]);
    }

    public function documentUpdate(Request $request, array $params = []): Response
    {
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('atak/sse/documents'));
        }

        $id = (int) ($params['id'] ?? 0);
        $doc = $this->documents->findById($id, $this->tenantId());
        if ($doc === null) {
            Session::flash('error', 'Document introuvable.');

            return Response::redirect(url('atak/sse/documents'));
        }
        if (in_array((string) $doc['status'], ['valide', 'archive'], true)) {
            Session::flash('error', 'Ce document est figé et ne peut plus être modifié.');

            return Response::redirect(url('atak/sse/documents/' . $id));
        }

        $title = trim((string) $request->input('title', ''));
        $body = trim((string) $request->input('body', ''));
        if ($title === '' || $body === '') {
            Session::flash('error', 'Indiquez un intitulé et un corps de document.');

            return Response::redirect(url('atak/sse/documents/' . $id . '/modifier'));
        }

        $caseId = (int) $request->input('case_id', 0);
        if ($caseId > 0 && $this->requireCase($caseId) === null) {
            Session::flash('error', 'Le dossier lié est hors de votre périmètre.');

            return Response::redirect(url('atak/sse/documents/' . $id . '/modifier'));
        }

        $status = SseDocumentRepository::normalizeStatus((string) $request->input('status', $doc['status']));
        $payload = [
            'title' => $title,
            'body' => $body,
            'document_type' => (string) $request->input('document_type', $doc['document_type']),
            'classification' => (string) $request->input('classification', $doc['classification']),
            'status' => $status,
            'case_id' => $caseId > 0 ? $caseId : null,
            'updated_by' => (int) Session::get('user_id') ?: null,
        ];
        if ($status === 'valide' && ($doc['status'] ?? '') !== 'valide') {
            $payload['validated_by'] = (int) Session::get('user_id') ?: null;
            $payload['validated_at'] = date('Y-m-d H:i:s');
        }

        $this->documents->update($id, $this->tenantId(), $payload);
        Session::flash('success', 'Document enregistré.');

        return Response::redirect(url('atak/sse/documents/' . $id));
    }

    public function documentStatus(Request $request, array $params = []): Response
    {
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('atak/sse/documents'));
        }

        $id = (int) ($params['id'] ?? 0);
        $doc = $this->documents->findById($id, $this->tenantId());
        if ($doc === null) {
            Session::flash('error', 'Document introuvable.');

            return Response::redirect(url('atak/sse/documents'));
        }

        $status = SseDocumentRepository::normalizeStatus((string) $request->input('status', ''));
        $payload = [
            'status' => $status,
            'updated_by' => (int) Session::get('user_id') ?: null,
        ];
        if ($status === 'valide') {
            $payload['validated_by'] = (int) Session::get('user_id') ?: null;
            $payload['validated_at'] = date('Y-m-d H:i:s');
        }
        $this->documents->update($id, $this->tenantId(), $payload);

        $messages = [
            'en_relecture' => 'Document soumis en relecture.',
            'valide' => 'Document validé — prêt pour diffusion contrôlée.',
            'archive' => 'Document archivé.',
            'brouillon' => 'Document remis en brouillon.',
        ];
        Session::flash('success', $messages[$status] ?? 'État mis à jour.');

        return Response::redirect(url('atak/sse/documents/' . $id));
    }

    public function collecteHub(Request $request, array $params = []): Response
    {
        $people = $this->persons->listForContext($this->tenantId(), 1, ['limit' => 8]);
        $sites = $this->sites->listForContext($this->tenantId(), 1, ['limit' => 8]);

        return $this->portalView('atak.sse.collecte', [
            'title' => 'Collecte terrain',
            'recentPeople' => $people,
            'recentSites' => $sites,
            'canManage' => $this->canManage(),
            'activeNav' => 'collecte',
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function portalView(string $view, array $data): Response
    {
        $data['isGuest'] = $this->access->isGuest();
        $data['clearanceUntil'] = (int) Session::get(SseAccessCodeService::SESSION_UNTIL, 0);
        $data['guestLabel'] = (string) Session::get('sse_guest_label', '');
        $data['sseTheme'] = sse_ui_theme();
        $data['sseThemeOptions'] = sse_ui_theme_options();
        $data['canGrant'] = $data['canGrant'] ?? $this->canGrant();
        $data['canManage'] = $data['canManage'] ?? $this->canManage();

        $ctx = $this->workspaceContext();
        $data['sseMissions'] = $ctx['missions'];
        $data['sseMissionId'] = $ctx['missionId'];
        $data['sseMissionLabel'] = $ctx['missionLabel'];
        $data['sseClassification'] = $ctx['classification'];
        $data['sseClassificationLabel'] = $ctx['classificationLabel'];
        $data['sseClassificationOptions'] = $ctx['classificationOptions'];

        $tenantId = $this->tenantId();
        if ($tenantId > 0 && $this->access->hasActiveClearance()) {
            $scope = $this->access->caseScope();
            $allForRail = $this->cases->listForTenant($tenantId, $scope);
            $data['sseFolderTree'] = $this->cases->buildTree($allForRail);
            $data['sseFolderParents'] = array_values(array_filter(
                $allForRail,
                static fn (array $c): bool => !empty($c['is_folder'])
            ));
            if (!isset($data['indexCounts'])) {
                $indexCounts = ['total' => count($allForRail), 'active' => 0, 'archive' => 0];
                foreach ($allForRail as $case) {
                    $status = (string) ($case['status'] ?? '');
                    if (in_array($status, ['ouvert', 'en_cours'], true)) {
                        $indexCounts['active']++;
                    }
                    if ($status === 'archive') {
                        $indexCounts['archive']++;
                    }
                }
                $data['indexCounts'] = $indexCounts;
            }
            $data['sseRecentCases'] = $this->recentCases();
        } else {
            $data['sseFolderTree'] = $data['sseFolderTree'] ?? [];
            $data['sseFolderParents'] = $data['sseFolderParents'] ?? [];
            $data['sseRecentCases'] = $data['sseRecentCases'] ?? [];
            $data['indexCounts'] = $data['indexCounts'] ?? ['total' => 0, 'active' => 0, 'archive' => 0];
        }

        return Response::view($view, $data);
    }

    /**
     * Contexte mission / diffusion pour la barre supérieure du portail.
     *
     * @return array{
     *   missions: list<array{id:int,title:string,status:string,status_label:string}>,
     *   missionId: int,
     *   missionLabel: string,
     *   classification: string,
     *   classificationLabel: string,
     *   classificationOptions: array<string,string>
     * }
     */
    private function workspaceContext(): array
    {
        $tenantId = $this->tenantId();
        $missionsRaw = $tenantId > 0 ? $this->missions->listForTenant($tenantId, 40) : [];
        $missions = [];
        foreach ($missionsRaw as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $status = (string) ($row['status'] ?? '');
            $missions[] = [
                'id' => $id,
                'title' => trim((string) ($row['title'] ?? '')) ?: ('Mission #' . $id),
                'status' => $status,
                'status_label' => TheatreMissionCycleRepository::statusLabel($status),
            ];
        }

        $missionId = sse_ui_mission_id();
        $missionLabel = 'Aucune mission';
        $found = false;
        foreach ($missions as $m) {
            if ($m['id'] === $missionId) {
                $missionLabel = $m['title'];
                $found = true;
                break;
            }
        }
        if (!$found) {
            $missionId = 0;
            if ($missions !== []) {
                foreach ($missions as $m) {
                    if ($m['status'] === TheatreMissionCycleRepository::STATUS_EN_COURS) {
                        $missionId = $m['id'];
                        $missionLabel = $m['title'];
                        break;
                    }
                }
                if ($missionId === 0) {
                    $missionId = $missions[0]['id'];
                    $missionLabel = $missions[0]['title'];
                }
            } else {
                $missionLabel = 'Aucune mission ouverte';
            }
        }

        $classification = sse_ui_classification();
        $classificationOptions = sse_ui_classification_options();

        return [
            'missions' => $missions,
            'missionId' => $missionId,
            'missionLabel' => $missionLabel,
            'classification' => $classification,
            'classificationLabel' => sse_ui_classification_label($classification),
            'classificationOptions' => $classificationOptions,
        ];
    }

    /**
     * @return array{name:string,meta:string,initial:string}
     */
    private function gateOperatorContext(): array
    {
        $userId = (int) Session::get('user_id');
        if ($userId < 1) {
            return [
                'name' => 'Opérateur',
                'meta' => 'Session invitée — code temporaire',
                'initial' => 'O',
            ];
        }
        $name = trim((string) (Session::get('display_name') ?? ''));
        $callsign = trim((string) (Session::get('callsign') ?? Session::get('arma_callsign') ?? ''));
        if ($name === '') {
            $name = $callsign !== '' ? $callsign : 'Membre Athena';
        }
        $meta = $callsign !== '' ? ('Indicatif ' . $callsign) : 'Compte Athena connecté';
        $initial = mb_strtoupper(mb_substr($name, 0, 1));

        return [
            'name' => $name,
            'meta' => $meta,
            'initial' => $initial !== '' ? $initial : 'A',
        ];
    }

    private function sseBackUrl(string $back): string
    {
        $prefix = (string) parse_url(url('atak/sse'), PHP_URL_PATH);
        $backPath = (string) (parse_url($back, PHP_URL_PATH) ?: $back);
        if ($back === '' || $prefix === '' || !str_starts_with($backPath, $prefix)) {
            return $this->access->hasActiveClearance()
                ? url('atak/sse/operations')
                : url('atak/sse');
        }

        return $back;
    }

    private function caseUnlocked(int $caseId): bool
    {
        $unlocked = Session::get('sse_unlocked_cases', []);
        if (!is_array($unlocked)) {
            return false;
        }

        return isset($unlocked[$caseId]) || isset($unlocked[(string) $caseId]);
    }

    /**
     * @param array<string, mixed> $case
     */
    private function pushRecentCase(array $case): void
    {
        $id = (int) ($case['id'] ?? 0);
        if ($id < 1) {
            return;
        }
        $recent = Session::get('sse_recent_cases', []);
        if (!is_array($recent)) {
            $recent = [];
        }
        $entry = [
            'id' => $id,
            'title' => (string) ($case['title'] ?? ''),
            'reference_code' => (string) ($case['reference_code'] ?? ''),
            'at' => date('H:i'),
        ];
        $out = [$entry];
        foreach ($recent as $row) {
            if (!is_array($row) || (int) ($row['id'] ?? 0) === $id) {
                continue;
            }
            $out[] = $row;
            if (count($out) >= 8) {
                break;
            }
        }
        Session::set('sse_recent_cases', $out);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentCases(): array
    {
        $recent = Session::get('sse_recent_cases', []);
        if (!is_array($recent)) {
            return [];
        }
        $out = [];
        foreach ($recent as $row) {
            if (is_array($row) && (int) ($row['id'] ?? 0) > 0) {
                $out[] = $row;
            }
        }

        return $out;
    }

    private function tenantId(): int
    {
        $tid = $this->access->tenantId();
        if ($tid > 0) {
            return $tid;
        }

        return (int) Session::get('tenant_id');
    }

    private function documentTemplate(string $type): string
    {
        $zulu = gmdate('d/m/Y H:i') . ' Z';

        return match ($type) {
            'flash' => "FLASH RENSEIGNEMENT\n"
                . "Date / heure : {$zulu}\n"
                . "Secteur / site :\n"
                . "Faits essentiels :\n"
                . "—\n"
                . "Impact immédiat :\n"
                . "—\n"
                . "Action demandée :\n"
                . "—\n"
                . "Source / fiabilité :\n"
                . "—\n",
            'compte_rendu' => "COMPTE RENDU D’EXPLOITATION\n"
                . "Date / heure : {$zulu}\n\n"
                . "1. Situation\n—\n\n"
                . "2. Site / environnement\n—\n\n"
                . "3. Personnel / identités\n—\n\n"
                . "4. Matériel / saisies\n—\n\n"
                . "5. Faits marquants\n—\n\n"
                . "6. Analyse et incertitudes\n—\n\n"
                . "7. Recommandations\n—\n",
            'synthese' => "SYNTHÈSE DE SITUATION\n"
                . "Date / heure : {$zulu}\n\n"
                . "Contexte\n—\n\n"
                . "Éléments consolidés\n—\n\n"
                . "Points encore non confirmés\n—\n\n"
                . "Appréciation\n—\n\n"
                . "Suite proposée\n—\n",
            'diffusion' => "VERSION DE DIFFUSION\n"
                . "Date / heure : {$zulu}\n"
                . "Niveau de diffusion visé :\n\n"
                . "Contenu expurgé\n—\n\n"
                . "Éléments volontairement omis (ne pas réintroduire)\n—\n",
            default => "NOTE D’ANALYSE\n"
                . "Date / heure : {$zulu}\n\n"
                . "Objet\n—\n\n"
                . "Éléments observés\n—\n\n"
                . "Croisements\n—\n\n"
                . "Hypothèses\n—\n\n"
                . "Limites / ce qui n’est pas établi\n—\n\n"
                . "Conclusion provisoire\n—\n",
        };
    }

    /**
     * Fusion journal codes d’accès + journal d’activité SSE (trié, récent d’abord).
     *
     * @return list<array<string, mixed>>
     */
    private function buildSseActionLog(int $tenantId, int $limit = 100): array
    {
        $access = $this->codes->listLogForTenant($tenantId, 80);
        $activity = $this->activityLog->listSseActionsForTenant($tenantId, 120);
        $merged = array_merge($access, $activity);
        usort(
            $merged,
            static fn (array $a, array $b): int => ((int) ($b['ts'] ?? 0)) <=> ((int) ($a['ts'] ?? 0))
        );

        return array_slice($merged, 0, max(1, min(150, $limit)));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function requireCase(int $id): ?array
    {
        $case = $this->cases->findById($id, $this->tenantId());
        if ($case === null) {
            return null;
        }
        $scope = $this->access->caseScope();
        if ($scope !== null && !in_array($id, $scope, true)) {
            return null;
        }

        // Verrou par classification — désarmé par défaut. Tant qu'il ne l'est pas,
        // la classification signale sans fermer : elle n'a jamais filtré depuis la
        // création du portail, et les valeurs déjà posées sur les dossiers ont été
        // choisies sans conséquence. Les transformer d'office en décisions
        // d'exclusion fermerait des dossiers que personne n'a voulu fermer.
        if ($this->clearance->caseLockEnabled($this->tenantId())
            && $this->clearance->caseAboveClearance($case)) {
            return null;
        }

        return $case;
    }

    private function canManage(): bool
    {
        if ($this->access->isGuest()) {
            return false;
        }

        return function_exists('can') && (can('atak.sse.case.manage') || can('atak.sse.grant') || can('admin.access'));
    }

    private function canGrant(): bool
    {
        if ($this->access->isGuest()) {
            return false;
        }

        return function_exists('can') && (can('atak.sse.grant') || can('admin.access'));
    }

    private function canExport(): bool
    {
        if ($this->access->isGuest()) {
            // Invité : export interdit par défaut (périmètre lecture)
            return false;
        }

        return function_exists('can') && (can('atak.sse.export') || can('admin.access'));
    }
}
