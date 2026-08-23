<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\SseSuggestionQueueRepository;
use App\Repositories\SseAnalyticalRepository;
use App\Repositories\SseAccessCodeRepository;
use App\Repositories\SseCaseRepository;
use App\Repositories\SseCaseMapRepository;
use App\Repositories\SseCrossDecisionRepository;
use App\Repositories\SseDocumentRepository;
use App\Repositories\SseEntityIndexRepository;
use App\Repositories\SseIntelEventRepository;
use App\Repositories\SseInterestCaseRepository;
use App\Repositories\SseMeshRepository;
use App\Repositories\SsePersonRepository;
use App\Repositories\SsePortalSettingsRepository;
use App\Repositories\SseSiteRepository;
use App\Repositories\SseTextTemplateRepository;
use App\Repositories\SseWatchlistRepository;
use App\Repositories\TenantRepository;
use App\Repositories\TheatreMissionCycleRepository;
use App\Repositories\UserRepository;
use App\Services\Sse\SseAnalyticalEngineService;
use App\Services\Sse\SseAnalystDigestService;
use App\Services\Sse\SseCompletenessService;
use App\Services\Sse\SseContextualMentionService;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseCaseBundleService;
use App\Services\Sse\SseCasePdfService;
use App\Services\Sse\SseClearanceService;
use App\Services\Sse\SseCorrelationService;
use App\Services\Sse\SseCrossMatchService;
use App\Services\Sse\SseMeshService;
use App\Services\Sse\SseRedactionService;
use App\Services\Sse\SseReportService;
use App\Services\Sse\SseTerrainService;
use App\Services\Media\ImageCompressionService;
use App\Services\Sse\SseWorkspaceService;
use App\Services\Tactical\AtakActivityLogService;
use App\Support\SseAnalyticalCatalog;
use App\Support\SseTextVariables;

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
        private ?SseCrossDecisionRepository $crossDecisions = null,
        private ?SseTextTemplateRepository $textLibrary = null,
        private ?SseCaseMapRepository $caseMaps = null,
        private ?SseAnalyticalRepository $analytical = null,
        private ?SseContextualMentionService $contextualMentions = null,
        private ?SseSuggestionQueueRepository $suggestions = null,
        private ?SseAnalyticalEngineService $engine = null,
        private ?SseAnalystDigestService $analystDigest = null,
        private ?SseCompletenessService $completeness = null,
        private ?SseCaseBundleService $caseBundles = null,
        private ?UserRepository $users = null,
        private ?SseTerrainService $terrain = null,
        private ?SseIntelEventRepository $intelEvents = null,
        private ?SseEntityIndexRepository $entityIndex = null,
    ) {
        $this->access ??= new SseAccessCodeService();
        $this->codes ??= new SseAccessCodeRepository();
        $this->cases ??= new SseCaseRepository();
        $this->interestCases ??= new SseInterestCaseRepository();
        $this->users ??= new UserRepository();
        $this->persons ??= new SsePersonRepository();
        $this->watchlist ??= new SseWatchlistRepository();
        $this->sites ??= new SseSiteRepository();
        $this->terrain ??= new SseTerrainService();
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
        $this->crossDecisions ??= new SseCrossDecisionRepository();
        $this->textLibrary ??= new SseTextTemplateRepository();
        $this->caseMaps ??= new SseCaseMapRepository();
        $this->analytical ??= new SseAnalyticalRepository();
        $this->contextualMentions ??= new SseContextualMentionService();
        $this->suggestions ??= new SseSuggestionQueueRepository();
        $this->engine ??= new SseAnalyticalEngineService();
        if ($this->analystDigest === null) {
            try {
                $this->analystDigest = \App\Core\Container::get(SseAnalystDigestService::class);
            } catch (\Throwable) {
                $this->analystDigest = null;
            }
        }
        $this->completeness ??= new SseCompletenessService();
        $this->caseBundles ??= new SseCaseBundleService($this->cases, $this->persons, $this->sites);
        $this->intelEvents ??= new SseIntelEventRepository();
        $this->entityIndex ??= new SseEntityIndexRepository();
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
        $userId = (int) Session::get('user_id') ?: null;

        return $this->portalView('atak.sse.interest_cases', [
            'title' => 'Pré-SSE — investigations préparatoires',
            'interestCases' => $this->interestCases->listVisibleForUser(
                $this->tenantId(),
                $userId,
                $this->canBypassInterestAcl(),
                $filters
            ),
            'filters' => $filters,
            'statuses' => SseInterestCaseRepository::STATUSES,
            'canManage' => $this->canManage(),
            'activeNav' => 'interet',
        ]);
    }

    /**
     * Journal des transmissions terrain (Arma 3 / mods) — sse_intel_events.
     */
    public function transmissionsIndex(Request $request, array $params = []): Response
    {
        $filtersUi = [
            'q' => trim((string) $request->query('q', '')),
            'event_type' => strtoupper(trim((string) $request->query('event_type', ''))),
            'source' => trim((string) $request->query('source', 'TERRAIN')),
            'since' => trim((string) $request->query('since', '')),
        ];

        $listFilters = [
            'limit' => 100,
            'q' => $filtersUi['q'] !== '' ? $filtersUi['q'] : null,
            'event_type' => $filtersUi['event_type'] !== '' ? $filtersUi['event_type'] : null,
            'since' => $filtersUi['since'] !== '' ? $filtersUi['since'] . ' 00:00:00' : null,
        ];

        $source = strtoupper($filtersUi['source']);
        if ($source === 'TERRAIN' || $source === '') {
            $listFilters['source_systems'] = SseIntelEventRepository::armaTerrainSourceSystems();
            $filtersUi['source'] = 'TERRAIN';
        } elseif ($source !== 'ALL') {
            $listFilters['source_system'] = $source;
        }

        $events = $this->intelEvents->listForTenant($this->tenantId(), array_filter(
            $listFilters,
            static fn (mixed $v): bool => $v !== null && $v !== ''
        ));

        return $this->portalView('atak.sse.transmissions', [
            'title' => 'Transmissions terrain',
            'events' => $events,
            'filters' => $filtersUi,
            'eventTypes' => SseIntelEventRepository::eventTypeOptions(),
            'sourceOptions' => array_merge(
                ['TERRAIN' => 'Toutes les sources terrain (Arma)', 'ALL' => 'Toutes les sources'],
                SseIntelEventRepository::sourceSystemOptions()
            ),
            'canManage' => $this->canManage(),
            'activeNav' => 'transmissions',
        ]);
    }

    public function transmissionShow(Request $request, array $params = []): Response
    {
        $event = $this->intelEvents->findById($this->tenantId(), (int) ($params['id'] ?? 0));
        if ($event === null) {
            Session::flash('error', 'Transmission introuvable.');
            return Response::redirect(url('atak/sse/transmissions'));
        }

        $entity = null;
        $entityUuid = trim((string) ($event['entity_uuid'] ?? ''));
        if ($entityUuid !== '') {
            $entity = $this->entityIndex->findByUuid($this->tenantId(), $entityUuid);
        }

        $relatedHref = null;
        $relatedLabel = null;
        $payload = is_array($event['payload'] ?? null) ? $event['payload'] : [];
        $personId = (int) ($payload['person_id'] ?? 0);
        $siteId = (int) ($payload['site_id'] ?? 0);
        if ($personId > 0) {
            $relatedHref = url('atak/sse/identites/' . $personId);
            $relatedLabel = 'Ouvrir l’identité liée';
        } elseif ($siteId > 0) {
            $relatedHref = url('atak/sse/sites/' . $siteId);
            $relatedLabel = 'Ouvrir le site lié';
        } elseif (!empty($event['case_id'])) {
            $relatedHref = url('atak/sse/dossiers/' . (int) $event['case_id']);
            $relatedLabel = 'Ouvrir le dossier validé';
        } elseif (!empty($event['interest_case_id'])) {
            $relatedHref = url('atak/sse/interet/' . (int) $event['interest_case_id']);
            $relatedLabel = 'Ouvrir le dossier d’intérêt';
        }

        $payloadRows = SseIntelEventRepository::flattenPayloadRows($payload);
        $clientLabel = (string) ($event['client_label'] ?? SseIntelEventRepository::clientSoftwareLabel($payload));
        $sections = [];
        foreach ($payloadRows as $row) {
            $sec = (string) ($row['section'] ?? 'Compléments');
            $sections[$sec][] = $row;
        }

        return $this->portalView('atak.sse.transmission_show', [
            'title' => 'Fiche de transmission',
            'event' => $event,
            'entity' => $entity,
            'relatedHref' => $relatedHref,
            'relatedLabel' => $relatedLabel,
            'payloadRows' => $payloadRows,
            'payloadSections' => $sections,
            'clientLabel' => $clientLabel,
            'canManage' => $this->canManage(),
            'activeNav' => 'transmissions',
        ]);
    }

    public function interestCaseCreateForm(Request $request, array $params = []): Response
    {
        if (!$this->canManage()) {
            return Response::redirect(url('atak/sse/interet'));
        }

        $signerLabel = trim((string) (Session::get('sse_guest_label') ?? Session::get('display_name') ?? ''));
        if ($signerLabel === '') {
            $signerLabel = 'Analyste';
        }

        return $this->portalView('atak.sse.interest_case_form', [
            'title' => 'Ouvrir un dossier d’intérêt',
            'activeNav' => 'interet',
            'canManage' => true,
            'confidenceLevels' => SseInterestCaseRepository::CONFIDENCE,
            'interestLevels' => SseInterestCaseRepository::INTEREST,
            'signerLabel' => $signerLabel,
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
        if (!(bool) $request->input('digital_signature', false)) {
            Session::flash('error', 'La signature numérique du signalement est obligatoire.');
            return Response::redirect(url('atak/sse/interet/nouveau'));
        }
        $fields = ['temporary_designation','suspected_alias','apparent_sex','estimated_age_range','suspected_nationality','suspected_affiliation','confidence_level','interest_level','opening_reason','description','origin_operator','observed_elements','analysis_facts','analysis_assumptions','analysis_contradictions','analysis_questions','collection_needs','operational_risk','recommendations','source_label','source_reliability','acquisition_at','mission_label'];
        $data = [];
        foreach ($fields as $field) {
            $data[$field] = trim((string) $request->input($field, '')) ?: null;
        }
        $data['temporary_designation'] = $designation;
        $data['opening_reason'] = $reason;
        $data['created_by'] = (int) Session::get('user_id') ?: null;
        $signer = trim((string) $request->input('signed_by_label', ''));
        if ($signer === '') {
            $signer = trim((string) (Session::get('sse_guest_label') ?? Session::get('display_name') ?? 'Analyste'));
        }
        $data['signed_by_label'] = $signer !== '' ? $signer : 'Analyste';
        $data['signed_at'] = date('Y-m-d H:i:s');
        $id = $this->interestCases->create($this->tenantId(), $data);
        Session::flash('success', 'Dossier d’intérêt ouvert et signé numériquement. Aucune identité n’a été déduite automatiquement.');
        return Response::redirect(url('atak/sse/interet/' . $id));
    }

    public function interestCaseShow(Request $request, array $params = []): Response
    {
        $case = $this->interestCases->findForTenant((int) ($params['id'] ?? 0), $this->tenantId());
        if (!$case) {
            return Response::redirect(url('atak/sse/interet'));
        }

        $denied = $this->interestAccessDeniedResponse($case);
        if ($denied !== null) {
            return $denied;
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

        $tenantId = $this->tenantId();
        $caseId = (int) $case['id'];
        $acl = $this->interestCases->listAcl($tenantId, $caseId);
        $members = [];
        try {
            $members = $this->users->listForTenant($tenantId, null, 'active', null, 200, 0);
        } catch (\Throwable) {
            $members = [];
        }

        return $this->portalView('atak.sse.interest_case_show', [
            'title' => (string) ($case['reference_code'] ?? 'Pré-SSE'),
            'interestCase' => $case,
            'hypotheses' => $hypotheses,
            'proposals' => $this->crossProposals($caseId),
            'constitutedCase' => $this->cases->findByInterestCase($tenantId, $caseId),
            'journalUpdates' => $this->interestCases->listUpdates($tenantId, $caseId),
            'acl' => $acl,
            'tenantMembers' => $members,
            'statuses' => SseInterestCaseRepository::STATUSES,
            'cooldowns' => $this->interestCases->allCooldownStates($tenantId, $caseId),
            'activeNav' => 'interet',
            'canManage' => $this->canManage(),
        ]);
    }

    public function interestCaseDescription(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $back = url('atak/sse/interet/' . $id);
        $case = $this->requireInterestCaseManageable($id, $back, $request);
        if ($case instanceof Response) {
            return $case;
        }

        $description = trim((string) $request->input('description', ''));
        $this->interestCases->updateDescription($id, $this->tenantId(), $description !== '' ? $description : null);
        Session::flash('success', 'Description du dossier enregistrée.');

        return Response::redirect($back . '#description');
    }

    public function interestCaseJournal(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $back = url('atak/sse/interet/' . $id);
        $case = $this->requireInterestCaseManageable($id, $back, $request);
        if ($case instanceof Response) {
            return $case;
        }

        $body = trim((string) $request->input('body', ''));
        if ($body === '') {
            Session::flash('error', 'Indiquez le contenu de la mise à jour.');

            return Response::redirect($back . '#journal');
        }

        $author = (string) (Session::get('display_name') ?? Session::get('callsign') ?? 'Analyste');
        $userId = (int) Session::get('user_id') ?: null;
        $this->interestCases->addUpdate($this->tenantId(), $id, $body, $author, $userId);
        Session::flash('success', 'Mise à jour consignée au dossier.');

        return Response::redirect($back . '#journal');
    }

    public function interestCaseStatus(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $back = url('atak/sse/interet/' . $id);
        $case = $this->requireInterestCaseManageable($id, $back, $request);
        if ($case instanceof Response) {
            return $case;
        }

        $status = (string) $request->input('status', '');
        $isPublish = $status === 'en_validation';
        $actionKey = $isPublish ? 'publish' : 'status';
        $cooldown = $this->interestCooldownBlock($this->tenantId(), $id, $actionKey, $back);
        if ($cooldown !== null) {
            return $cooldown;
        }

        if (!isset(SseInterestCaseRepository::STATUSES[$status])) {
            Session::flash('error', 'Choisissez un état valable pour ce dossier.');

            return Response::redirect($back);
        }

        $prev = (string) ($case['status'] ?? '');
        if ($prev === $status) {
            Session::flash('success', 'L’état du dossier est déjà à jour.');

            return Response::redirect($back);
        }

        $this->interestCases->updateStatus($id, $this->tenantId(), $status);
        $userId = (int) Session::get('user_id') ?: null;
        $this->interestCases->touchCooldown($this->tenantId(), $id, $actionKey, $userId);

        $author = (string) (Session::get('display_name') ?? Session::get('callsign') ?? 'Analyste');
        $this->interestCases->addUpdate(
            $this->tenantId(),
            $id,
            'État passé de « ' . (SseInterestCaseRepository::STATUSES[$prev] ?? $prev) . ' » à « '
            . (SseInterestCaseRepository::STATUSES[$status] ?? $status) . ' ».',
            $author,
            $userId
        );

        Session::flash(
            'success',
            $isPublish
                ? 'Dossier soumis à validation humaine.'
                : 'État du dossier mis à jour.'
        );

        return Response::redirect($back);
    }

    public function interestCaseAcl(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $back = url('atak/sse/interet/' . $id);
        $case = $this->requireInterestCaseManageable($id, $back, $request);
        if ($case instanceof Response) {
            return $case;
        }

        $dest = $request->input('destinataires', []);
        $deny = $request->input('interdits', []);
        if (!is_array($dest)) {
            $dest = [];
        }
        if (!is_array($deny)) {
            $deny = [];
        }

        $userId = (int) Session::get('user_id') ?: null;
        $ok = $this->interestCases->replaceAcl(
            $this->tenantId(),
            $id,
            array_map('intval', $dest),
            array_map('intval', $deny),
            $userId
        );

        if (!$ok) {
            Session::flash('error', 'La diffusion n’a pas pu être enregistrée. Réessayez.');

            return Response::redirect($back . '#diffusion');
        }

        Session::flash('success', 'Destinataires et interdictions nominatives mis à jour.');

        return Response::redirect($back . '#diffusion');
    }

    public function interestCaseOpenInvestigation(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $back = url('atak/sse/interet/' . $id);
        $case = $this->requireInterestCaseManageable($id, $back, $request);
        if ($case instanceof Response) {
            return $case;
        }

        $cooldown = $this->interestCooldownBlock($this->tenantId(), $id, 'open_mesh', $back);
        if ($cooldown !== null) {
            return $cooldown;
        }

        $userId = (int) Session::get('user_id') ?: null;
        $this->interestCases->touchCooldown($this->tenantId(), $id, 'open_mesh', $userId);

        return Response::redirect(url('atak/sse/toiles/nouveau'));
    }
    /**
     * Constitue un dossier à partir d'un dossier d'intérêt instruit.
     *
     * C'est le passage qui manquait : le travail d'analyse reste au dossier
     * d'intérêt, le dossier ouvert en reprend la substance et garde trace de son
     * origine. Rien n'est déduit : seuls les éléments déjà écrits sont reportés.
     */
    public function interestCaseConstitute(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $back = url('atak/sse/interet/' . $id);

        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect($back);
        }

        $interest = $this->interestCases->findForTenant($id, $this->tenantId());
        if ($interest === null) {
            return Response::redirect(url('atak/sse/interet'));
        }

        $denied = $this->interestAccessDeniedResponse($interest);
        if ($denied !== null) {
            return $denied;
        }

        $cooldown = $this->interestCooldownBlock($this->tenantId(), $id, 'constitute', $back);
        if ($cooldown !== null) {
            return $cooldown;
        }

        $existing = $this->cases->findByInterestCase($this->tenantId(), $id);
        if ($existing !== null) {
            Session::flash('error', 'Un dossier a déjà été constitué à partir de ce dossier d’intérêt.');

            return Response::redirect(url('atak/sse/dossiers/' . (int) $existing['id']));
        }

        $summary = array_values(array_filter([
            trim((string) ($interest['opening_reason'] ?? '')),
            trim((string) ($interest['description'] ?? '')),
            trim((string) ($interest['observed_elements'] ?? '')),
            trim((string) ($interest['analysis_facts'] ?? '')),
        ], static fn (string $s): bool => $s !== ''));

        $origin = sprintf(
            'Constitué à partir du dossier d’intérêt %s (%s).',
            (string) ($interest['reference_code'] ?? ''),
            (string) ($interest['temporary_designation'] ?? 'sujet non désigné')
        );

        $caseId = $this->cases->create([
            'tenant_id' => $this->tenantId(),
            'title' => (string) ($interest['temporary_designation'] ?? 'Dossier sans titre'),
            'summary' => implode("\n\n", array_merge($summary, [$origin])),
            'classification' => SseCaseRepository::CLASS_CONFIDENTIAL,
            'status' => 'ouvert',
            'interest_case_id' => $id,
            'created_by' => (int) Session::get('user_id') ?: null,
        ]);

        $userId = (int) Session::get('user_id') ?: null;
        $this->interestCases->touchCooldown($this->tenantId(), $id, 'constitute', $userId);

        $this->activityLog->record(
            $this->tenantId(),
            1,
            'SSE_CASE',
            sprintf(
                'Dossier constitué à partir du dossier d’intérêt %s.',
                (string) ($interest['reference_code'] ?? $id)
            ),
            (string) (Session::get('display_name') ?? Session::get('callsign') ?? 'Analyste')
        );

        Session::flash('success', 'Dossier ouvert à partir du dossier d’intérêt. Rattachez-y au moins une identité pour le rendre exploitable.');

        return Response::redirect(url('atak/sse/dossiers/' . $caseId));
    }

    /**
     * Rapprochements proposés pour un dossier d'intérêt, enrichis de la décision déjà
     * prise. Les propositions sont recalculées à chaque ouverture ; seule la décision
     * humaine est conservée.
     *
     * @return list<array<string, mixed>>
     */
    private function crossProposals(int $interestCaseId): array
    {
        $tenantId = $this->tenantId();
        $decisions = $this->crossDecisions->mapForCase($tenantId, $interestCaseId);
        $proposals = [];

        try {
            $matches = $this->cross->matchPersonsAgainstWatchlist($tenantId);
            foreach (array_slice(is_array($matches) ? $matches : [], 0, 5) as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $person = is_array($row['person'] ?? null) ? $row['person'] : [];
                $top = is_array($row['matches'][0] ?? null) ? $row['matches'][0] : [];
                $entry = is_array($top['entry'] ?? null) ? $top['entry'] : [];
                $personId = (int) ($person['id'] ?? 0);
                $entryId = (int) ($entry['id'] ?? 0);
                if ($personId < 1 || $entryId < 1) {
                    continue;
                }

                $entryName = trim(
                    (string) ($entry['last_name'] ?? '') . ' ' . (string) ($entry['first_name'] ?? '')
                );
                if ($entryName === '') {
                    $entryName = (string) ($entry['alias'] ?? 'entrée surveillée');
                }

                $decision = $decisions[$personId . ':' . $entryId] ?? null;
                $proposals[] = [
                    'person_id' => $personId,
                    'entry_id' => $entryId,
                    'person_name' => (string) ($person['display_name'] ?? 'Identité'),
                    'entry_name' => $entryName,
                    'reason' => (string) ($top['reason'] ?? 'Rapprochement à confirmer'),
                    'score' => (int) ($top['score'] ?? 0),
                    'decision' => $decision,
                ];
            }
        } catch (\Throwable) {
            $proposals = [];
        }

        // Les rapprochements déjà tranchés passent en fin de liste : le travail restant
        // se lit en premier.
        usort($proposals, static function (array $a, array $b): int {
            $pending = (int) ($a['decision'] === null) <=> (int) ($b['decision'] === null);
            if ($pending !== 0) {
                return -$pending;
            }

            return (int) $b['score'] <=> (int) $a['score'];
        });

        return $proposals;
    }

    /** Tranche un rapprochement proposé : confirmé, maintenu séparé, ou à approfondir. */
    public function interestCrossDecide(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $back = url('atak/sse/interet/' . $id);

        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect($back);
        }

        $case = $this->interestCases->findForTenant($id, $this->tenantId());
        if ($case === null) {
            return Response::redirect(url('atak/sse/interet'));
        }

        $denied = $this->interestAccessDeniedResponse($case);
        if ($denied !== null) {
            return $denied;
        }

        $cooldown = $this->interestCooldownBlock($this->tenantId(), $id, 'cross_decide', $back);
        if ($cooldown !== null) {
            return $cooldown;
        }

        $personId = (int) $request->input('person_id', 0);
        $entryId = (int) $request->input('entry_id', 0);
        $decision = (string) $request->input('decision', '');
        $author = (string) (Session::get('display_name') ?? Session::get('callsign') ?? 'Analyste');

        if ($personId < 1 || $entryId < 1) {
            Session::flash('error', 'Rapprochement introuvable — rechargez la page.');

            return Response::redirect($back);
        }

        if ($decision === 'reouvrir') {
            $this->crossDecisions->clear($this->tenantId(), $id, $personId, $entryId);
            $this->interestCases->touchCooldown($this->tenantId(), $id, 'cross_decide', (int) Session::get('user_id') ?: null);
            Session::flash('success', 'Décision retirée — le rapprochement redevient à traiter.');

            return Response::redirect($back);
        }

        if (!SseCrossDecisionRepository::isDecision($decision)) {
            Session::flash('error', 'Choisissez une décision valable pour ce rapprochement.');

            return Response::redirect($back);
        }

        $ok = $this->crossDecisions->record($this->tenantId(), $id, [
            'person_id' => $personId,
            'entry_id' => $entryId,
            'decision' => $decision,
            'score' => (int) $request->input('score', 0),
            'reason' => (string) $request->input('reason', ''),
            'note' => trim((string) $request->input('note', '')) ?: null,
            'author_label' => $author,
            'decided_by' => (int) Session::get('user_id') ?: null,
        ]);

        if (!$ok) {
            Session::flash('error', 'La décision n’a pas pu être enregistrée.');

            return Response::redirect($back);
        }

        $this->interestCases->touchCooldown($this->tenantId(), $id, 'cross_decide', (int) Session::get('user_id') ?: null);

        $this->activityLog->record(
            $this->tenantId(),
            1,
            'SSE_CROSS',
            sprintf(
                '%s sur le dossier %s.',
                SseCrossDecisionRepository::decisionLabel($decision),
                (string) ($case['reference_code'] ?? $id)
            ),
            $author
        );

        Session::flash('success', match ($decision) {
            SseCrossDecisionRepository::CONFIRMED => 'Rapprochement confirmé et consigné au dossier.',
            SseCrossDecisionRepository::SEPARATE => 'Rapprochement écarté : les deux identités restent distinctes.',
            default => 'Analyse complémentaire demandée sur ce rapprochement.',
        });

        return Response::redirect($back);
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

    /** Formulaire d’import d’un scénario / pack dossier (mode gestion). */
    public function caseImportForm(Request $request, array $params = []): Response
    {
        if (!$this->canManage()) {
            Session::flash('error', 'Action réservée à la gestion des dossiers.');

            return Response::redirect(url('atak/sse/dossiers'));
        }

        $example = $this->caseBundles->exampleSkeleton();
        $exampleJson = json_encode($example, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{}';

        return $this->portalView('atak.sse.case_import', [
            'title' => 'Importer un scénario',
            'exampleJson' => $exampleJson,
            'canManage' => true,
            'activeNav' => 'dossiers',
        ]);
    }

    /** Importe un pack (fichier ou texte collé) et crée le dossier + contenus. */
    public function caseImportStore(Request $request, array $params = []): Response
    {
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('atak/sse/dossiers'));
        }

        $raw = '';
        $file = $_FILES['bundle_file'] ?? null;
        if (is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $tmp = (string) ($file['tmp_name'] ?? '');
            if ($tmp !== '' && is_uploaded_file($tmp)) {
                $raw = (string) file_get_contents($tmp);
            }
        }
        if ($raw === '') {
            $raw = (string) $request->input('bundle_text', '');
        }

        $parsed = $this->caseBundles->parseJson($raw);
        if (!$parsed['ok'] || !isset($parsed['bundle'])) {
            Session::flash('error', implode(' ', $parsed['errors']));

            return Response::redirect(url('atak/sse/dossiers/importer'));
        }

        $submitter = (string) (Session::get('callsign') ?? Session::get('display_name') ?? 'Bureau');
        $result = $this->caseBundles->import(
            $parsed['bundle'],
            $this->tenantId(),
            (int) Session::get('user_id') ?: null,
            $submitter !== '' ? $submitter : 'Bureau'
        );
        if (!$result['ok'] || empty($result['case_id'])) {
            Session::flash('error', implode(' ', $result['errors'] ?: ['Import impossible.']));

            return Response::redirect(url('atak/sse/dossiers/importer'));
        }

        $counts = $result['counts'] ?? [];
        $bits = [];
        if (!empty($counts['persons'])) {
            $bits[] = (int) $counts['persons'] . ' identité(s)';
        }
        if (!empty($counts['sites'])) {
            $bits[] = (int) $counts['sites'] . ' site(s)';
        }
        if (!empty($counts['evidence'])) {
            $bits[] = (int) $counts['evidence'] . ' pièce(s)';
        }
        $detail = $bits !== [] ? (' (' . implode(', ', $bits) . ')') : '';
        Session::flash('success', 'Scénario importé : dossier créé' . $detail . '.');

        return Response::redirect(url('atak/sse/dossiers/' . (int) $result['case_id']));
    }

    /**
     * Emport administratif : pack Athena (json), pack Arma (json) ou script terrain (sqf).
     */
    public function caseExportBundle(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if (!$this->canExport() && !$this->canManage()) {
            Session::flash('error', 'Export non autorisé pour cette session.');

            return Response::redirect(url('atak/sse/dossiers/' . $id));
        }
        $case = $this->requireCase($id);
        if ($case === null || !empty($case['is_folder'])) {
            Session::flash('error', 'Dossier introuvable.');

            return Response::redirect(url('atak/sse/dossiers'));
        }
        if ($this->caseNeedsUnlock($case)) {
            return Response::redirect(url('atak/sse/dossiers/' . $id . '/deverrouiller'));
        }

        $bundle = $this->caseBundles->exportCase($id, $this->tenantId());
        if ($bundle === null) {
            Session::flash('error', 'Impossible d’exporter ce dossier.');

            return Response::redirect(url('atak/sse/dossiers/' . $id));
        }

        $format = strtolower(trim((string) $request->query('format', 'athena')));
        $slug = preg_replace('/[^a-z0-9_\-]+/i', '_', (string) ($case['reference_code'] ?? 'dossier')) ?: 'dossier';

        if ($format === 'sqf' || $format === 'arma') {
            $arma = $this->caseBundles->toArmaPack($bundle);
            if ($format === 'sqf') {
                $response = new Response();

                return $response
                    ->header('Content-Type', 'text/plain; charset=utf-8')
                    ->header('Content-Disposition', 'attachment; filename="sse_pack_' . $slug . '.sqf"')
                    ->setBody($arma['sqf']);
            }
            $json = json_encode($arma['json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{}';
            $response = new Response();

            return $response
                ->header('Content-Type', 'application/json; charset=utf-8')
                ->header('Content-Disposition', 'attachment; filename="sse_pack_arma_' . $slug . '.json"')
                ->setBody($json);
        }

        $json = json_encode($bundle, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{}';
        $response = new Response();

        return $response
            ->header('Content-Type', 'application/json; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="sse_dossier_' . $slug . '.json"')
            ->setBody($json);
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
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('atak/sse/dossiers/' . $id . '/deverrouiller'));
        }
        $case = $this->requireCase($id);
        if ($case === null) {
            Session::flash('error', 'Dossier introuvable ou hors de votre périmètre.');

            return Response::redirect(url('atak/sse/dossiers'));
        }
        if (!$this->caseNeedsUnlock($case)) {
            return Response::redirect(url('atak/sse/dossiers/' . $id));
        }
        $plain = trim((string) $request->input('unlock_code', ''));
        if (!$this->cases->verifyUnlockCode($id, $this->tenantId(), $plain)) {
            Session::flash('error', 'Mot de passe du dossier incorrect.');

            return Response::redirect(url('atak/sse/dossiers/' . $id . '/deverrouiller'));
        }
        $this->markCaseUnlocked($id);
        Session::flash('success', 'Dossier déverrouillé pour cette session.');

        return Response::redirect(url('atak/sse/dossiers/' . $id));
    }

    /** Capture Tacmap (image PNG base64) versée comme preuve + mémorisation de la vue. */
    public function caseTacmapCapture(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('atak/sse/dossiers/' . $id));
        }
        if ($this->requireWritableCase($id) === null) {
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
        $centerLat = (float) $request->input('center_lat', 48.8566);
        $centerLng = (float) $request->input('center_lng', 2.3522);
        $zoom = (int) $request->input('zoom', 6);
        $this->cases->addEvidence($id, $this->tenantId(), [
            'label' => 'Capture Tacmap',
            'caption' => $caption !== '' ? $caption : sprintf(
                'Vue carte au %s — Z%d · %.5f, %.5f',
                date('d/m/Y H:i'),
                $zoom,
                $centerLat,
                $centerLng
            ),
            'image_path' => 'uploads/sse/evidence/' . $name,
            'author_label' => (string) (Session::get('sse_guest_label') ?? Session::get('display_name') ?? 'Opérateur'),
        ]);

        $existingMap = $this->caseMaps->getState($this->tenantId(), $id);
        $captureMeta = is_array($existingMap['snapshot_meta'] ?? null) ? $existingMap['snapshot_meta'] : [];
        $captureBasemap = strtolower(trim((string) $request->input('basemap', (string) ($captureMeta['basemap'] ?? ''))));
        $captureMeta['captured_at'] = date('c');
        $captureMeta['image'] = 'uploads/sse/evidence/' . $name;
        $captureMeta['feature_count'] = count($this->caseMaps->listFeatures($this->tenantId(), $id));
        if (in_array($captureBasemap, ['dark', 'light', 'street', 'relief'], true)) {
            $captureMeta['basemap'] = $captureBasemap;
        }

        $this->caseMaps->saveState($this->tenantId(), $id, [
            'center_lat' => $centerLat,
            'center_lng' => $centerLng,
            'zoom' => $zoom,
            'map_id' => (int) $request->input('map_id', 1),
            'atak_layer_enabled' => (bool) $request->input('atak_layer_enabled', true),
            'snapshot_meta' => $captureMeta,
        ], (int) Session::get('user_id') ?: null);

        Session::flash('success', 'Capture enregistrée : pièce versée et vue mémorisée pour ce dossier.');

        return Response::redirect(url('atak/sse/dossiers/' . $id . '#tacmap'));
    }

    /** Enregistre la vue permanente (centre, zoom, calque ATAK) sans capture image. */
    public function caseMapSave(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            return Response::json(['ok' => false, 'error' => 'Action non autorisée.'], 403);
        }
        if ($this->requireWritableCase($id) === null) {
            return Response::json(['ok' => false, 'error' => 'Dossier inaccessible.'], 404);
        }

        $existing = $this->caseMaps->getState($this->tenantId(), $id);
        $meta = is_array($existing['snapshot_meta'] ?? null) ? $existing['snapshot_meta'] : [];
        $basemap = strtolower(trim((string) $request->input('basemap', '')));
        if (in_array($basemap, ['dark', 'light', 'street', 'relief'], true)) {
            $meta['basemap'] = $basemap;
        }

        $ok = $this->caseMaps->saveState($this->tenantId(), $id, [
            'center_lat' => (float) $request->input('center_lat', 48.8566),
            'center_lng' => (float) $request->input('center_lng', 2.3522),
            'zoom' => (int) $request->input('zoom', 6),
            'map_id' => (int) $request->input('map_id', 1),
            'atak_layer_enabled' => filter_var($request->input('atak_layer_enabled', true), FILTER_VALIDATE_BOOLEAN),
            'snapshot_meta' => $meta !== [] ? $meta : null,
        ], (int) Session::get('user_id') ?: null);

        return Response::json([
            'ok' => $ok,
            'state' => $this->caseMaps->getState($this->tenantId(), $id),
        ], $ok ? 200 : 500);
    }

    public function caseMapFeatureAdd(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            return Response::json(['ok' => false, 'error' => 'Action non autorisée.'], 403);
        }
        if ($this->requireWritableCase($id) === null) {
            return Response::json(['ok' => false, 'error' => 'Dossier inaccessible.'], 404);
        }

        $feature = $this->caseMaps->addFeature($this->tenantId(), $id, [
            'kind' => (string) $request->input('kind', 'ping'),
            'label' => (string) $request->input('label', ''),
            'note' => (string) $request->input('note', ''),
            'color' => (string) $request->input('color', '#34d399'),
            'lat' => $request->input('lat'),
            'lng' => $request->input('lng'),
            'arma_x' => $request->input('arma_x'),
            'arma_y' => $request->input('arma_y'),
            'site_id' => (int) $request->input('site_id', 0),
            'created_by' => (int) Session::get('user_id') ?: null,
            'author_label' => (string) (Session::get('sse_guest_label') ?? Session::get('display_name') ?? 'Opérateur'),
        ]);

        if ($feature === null) {
            return Response::json(['ok' => false, 'error' => 'Indiquez une position (carte ou coordonnées terrain).'], 422);
        }

        // Mémorise aussi la vue courante si fournie.
        if ($request->input('center_lat') !== null) {
            $this->caseMaps->saveState($this->tenantId(), $id, [
                'center_lat' => (float) $request->input('center_lat'),
                'center_lng' => (float) $request->input('center_lng', 0),
                'zoom' => (int) $request->input('zoom', 6),
                'map_id' => (int) $request->input('map_id', 1),
                'atak_layer_enabled' => filter_var($request->input('atak_layer_enabled', true), FILTER_VALIDATE_BOOLEAN),
            ], (int) Session::get('user_id') ?: null);
        }

        return Response::json(['ok' => true, 'feature' => $feature], 201);
    }

    public function caseMapFeatureDelete(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $featureId = (int) ($params['featureId'] ?? 0);
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            return Response::json(['ok' => false, 'error' => 'Action non autorisée.'], 403);
        }
        if ($this->requireWritableCase($id) === null) {
            return Response::json(['ok' => false, 'error' => 'Dossier inaccessible.'], 404);
        }

        $ok = $this->caseMaps->deleteFeature($this->tenantId(), $id, $featureId);

        return Response::json(['ok' => $ok], $ok ? 200 : 404);
    }

    public function caseAssessmentStore(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('atak/sse/dossiers/' . $id) . '#analyse');
        }
        if ($this->requireWritableCase($id) === null) {
            Session::flash('error', 'Dossier inaccessible.');

            return Response::redirect(url('atak/sse/dossiers'));
        }

        $payload = [
            'subject_label' => (string) $request->input('subject_label', ''),
            'fact_text' => (string) $request->input('fact_text', ''),
            'source_origin' => (string) $request->input('source_origin', 'observation'),
            'source_reliability' => (string) $request->input('source_reliability', 'F'),
            'info_credibility' => (int) $request->input('info_credibility', 6),
            'corroboration_text' => (string) $request->input('corroboration_text', ''),
            'assessment_text' => (string) $request->input('assessment_text', ''),
            'confidence' => (string) $request->input('confidence', 'modere'),
            'confidence_justification' => (string) $request->input('confidence_justification', ''),
            'hypothesis_code' => (string) $request->input('hypothesis_code', 'H1'),
            'hypothesis_text' => (string) $request->input('hypothesis_text', ''),
            'temporality' => (string) $request->input('temporality', 'valable_a_date'),
            'temporality_date' => (string) $request->input('temporality_date', ''),
            'urgency' => (string) $request->input('urgency', ''),
            'divergence_code' => (string) $request->input('divergence_code', ''),
            'author_label' => $this->sseAuthorLabel(),
            'reviewer_label' => trim((string) $request->input('reviewer_label', '')) ?: null,
            'validator_label' => trim((string) $request->input('validator_label', '')) ?: null,
            'created_by' => (int) Session::get('user_id') ?: null,
        ];

        $result = $this->analytical->createAssessment($this->tenantId(), $id, $payload);
        if (!$result['ok']) {
            Session::flash('error', $result['error'] ?? 'Enregistrement impossible.');
        } else {
            $this->analytical->recordDecision($this->tenantId(), $id, [
                'decision_domain' => 'hypothese',
                'subject_label' => $payload['subject_label'] !== '' ? $payload['subject_label'] : 'Nouvelle appréciation',
                'value_before' => null,
                'value_after' => SseAnalyticalCatalog::label(SseAnalyticalCatalog::CONFIDENCE, $payload['confidence'])
                    . ' / ' . strtoupper($payload['hypothesis_code']),
                'reason' => $payload['confidence_justification'],
                'assessment_id' => $result['id'] ?? null,
                'author_label' => $payload['author_label'],
                'decided_by' => $payload['created_by'],
            ]);
            Session::flash('success', 'Appréciation analytique consignée.');
        }

        return Response::redirect(url('atak/sse/dossiers/' . $id) . '#analyse');
    }

    public function caseGapStore(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('atak/sse/dossiers/' . $id) . '#lacunes');
        }
        if ($this->requireWritableCase($id) === null) {
            Session::flash('error', 'Dossier inaccessible.');

            return Response::redirect(url('atak/sse/dossiers'));
        }

        $result = $this->analytical->createGap($this->tenantId(), $id, [
            'kind' => (string) $request->input('kind', 'lacune'),
            'title' => (string) $request->input('title', ''),
            'body' => (string) $request->input('body', ''),
            'priority' => (string) $request->input('priority', 'normale'),
            'status' => 'ouvert',
            'linked_hypothesis' => (string) $request->input('linked_hypothesis', ''),
            'confirmation_criterion' => (string) $request->input('confirmation_criterion', ''),
            'assignee_label' => (string) $request->input('assignee_label', ''),
            'due_at' => (string) $request->input('due_at', ''),
            'author_label' => $this->sseAuthorLabel(),
            'created_by' => (int) Session::get('user_id') ?: null,
        ]);
        Session::flash($result['ok'] ? 'success' : 'error', $result['ok']
            ? 'Lacune / besoin enregistré.'
            : ($result['error'] ?? 'Enregistrement impossible.'));

        return Response::redirect(url('atak/sse/dossiers/' . $id) . '#lacunes');
    }

    public function caseGapStatus(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $gapId = (int) ($params['gapId'] ?? 0);
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('atak/sse/dossiers/' . $id) . '#lacunes');
        }
        if ($this->requireWritableCase($id) === null) {
            Session::flash('error', 'Dossier inaccessible.');

            return Response::redirect(url('atak/sse/dossiers'));
        }

        $status = (string) $request->input('status', '');
        $ok = $this->analytical->updateGapStatus($this->tenantId(), $id, $gapId, $status);
        Session::flash($ok ? 'success' : 'error', $ok ? 'État mis à jour.' : 'Mise à jour impossible.');

        return Response::redirect(url('atak/sse/dossiers/' . $id) . '#lacunes');
    }

    public function caseDecisionStore(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('atak/sse/dossiers/' . $id) . '#decisions');
        }
        if ($this->requireWritableCase($id) === null) {
            Session::flash('error', 'Dossier inaccessible.');

            return Response::redirect(url('atak/sse/dossiers'));
        }

        $ok = $this->analytical->recordDecision($this->tenantId(), $id, [
            'decision_domain' => (string) $request->input('decision_domain', 'autre'),
            'subject_label' => (string) $request->input('subject_label', ''),
            'value_before' => (string) $request->input('value_before', ''),
            'value_after' => (string) $request->input('value_after', ''),
            'reason' => (string) $request->input('reason', ''),
            'author_label' => $this->sseAuthorLabel(),
            'decided_by' => (int) Session::get('user_id') ?: null,
        ]);
        Session::flash($ok ? 'success' : 'error', $ok
            ? 'Décision consignée au registre.'
            : 'Indiquez la valeur après décision et le motif.');

        return Response::redirect(url('atak/sse/dossiers/' . $id) . '#decisions');
    }

    public function caseLinkStore(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('atak/sse/dossiers/' . $id) . '#relations');
        }
        if ($this->requireWritableCase($id) === null) {
            Session::flash('error', 'Dossier inaccessible.');

            return Response::redirect(url('atak/sse/dossiers'));
        }

        $relatedRef = trim((string) $request->input('related_reference', ''));
        $relatedId = (int) $request->input('related_case_id', 0);
        if ($relatedId < 1 && $relatedRef !== '') {
            $found = $this->cases->findByReferenceCode($this->tenantId(), $relatedRef);
            $relatedId = (int) ($found['id'] ?? 0);
        }

        $result = $this->analytical->createCaseLink($this->tenantId(), $id, [
            'related_case_id' => $relatedId,
            'relation_type' => (string) $request->input('relation_type', 'connexe'),
            'note' => (string) $request->input('note', ''),
            'former_reference' => (string) $request->input('former_reference', ''),
            'author_label' => $this->sseAuthorLabel(),
            'created_by' => (int) Session::get('user_id') ?: null,
        ]);
        Session::flash($result['ok'] ? 'success' : 'error', $result['ok']
            ? 'Relation entre dossiers enregistrée.'
            : ($result['error'] ?? 'Enregistrement impossible.'));

        return Response::redirect(url('atak/sse/dossiers/' . $id) . '#relations');
    }

    public function caseLinkDelete(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $linkId = (int) ($params['linkId'] ?? 0);
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('atak/sse/dossiers/' . $id) . '#relations');
        }
        if ($this->requireWritableCase($id) === null) {
            Session::flash('error', 'Dossier inaccessible.');

            return Response::redirect(url('atak/sse/dossiers'));
        }

        $ok = $this->analytical->deleteCaseLink($this->tenantId(), $id, $linkId);
        Session::flash($ok ? 'success' : 'error', $ok ? 'Relation retirée.' : 'Suppression impossible.');

        return Response::redirect(url('atak/sse/dossiers/' . $id) . '#relations');
    }

    private function sseAuthorLabel(): string
    {
        return (string) (Session::get('sse_guest_label') ?? Session::get('display_name') ?? 'Analyste');
    }

    public function caseShow(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $case = $this->requireCase($id);
        if ($case === null) {
            Session::flash('error', 'Dossier introuvable ou hors de votre périmètre.');

            return Response::redirect(url('atak/sse/dossiers'));
        }
        if ($this->caseNeedsUnlock($case)) {
            return Response::redirect(url('atak/sse/dossiers/' . $id . '/deverrouiller'));
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
        $linkedIds = [];
        foreach ($links as $link) {
            $pid = (int) ($link['person_id'] ?? 0);
            if ($pid > 0) {
                $linkedIds[] = $pid;
            }
            $p = $this->persons->findById($pid, $this->tenantId());
            if ($p) {
                $people[] = $p;
            }
        }
        $available = $this->persons->listForContext($this->tenantId(), 1, ['limit' => 100]);
        $armaInbox = $this->persons->listArmaInbox($this->tenantId(), 0, $linkedIds, 40);

        $people = $this->clearance->redactPeopleForScreens($people, $this->tenantId(), $id);
        $available = $this->clearance->redactPeopleForScreens($available, $this->tenantId(), $id);
        $armaInbox = $this->clearance->redactPeopleForScreens($armaInbox, $this->tenantId(), $id);

        $caseSites = $this->sites->listForCase($id, $this->tenantId());
        $siteCounts = $this->sites->countsForSites(
            array_map(static fn (array $s): int => (int) ($s['id'] ?? 0), $caseSites),
            $this->tenantId()
        );
        $armaSeizures = [];
        foreach ($caseSites as $site) {
            $sid = (int) ($site['id'] ?? 0);
            if ($sid < 1) {
                continue;
            }
            foreach ($this->sites->listSeizures($sid, $this->tenantId()) as $sz) {
                $armaSeizures[] = [
                    'id' => (int) ($sz['id'] ?? 0),
                    'site_id' => $sid,
                    'site_name' => (string) ($site['name'] ?? $site['reference_code'] ?? 'Site'),
                    'label' => (string) ($sz['label'] ?? 'Saisie'),
                    'category' => (string) ($sz['category'] ?? ''),
                    'quantity' => (int) ($sz['quantity'] ?? 1),
                    'notes' => (string) ($sz['notes'] ?? ''),
                ];
            }
        }

        $casePresets = $this->loadCasePresets();

        $notes = $this->cases->listNotes($id, $this->tenantId());
        $evidence = $this->cases->listEvidence($id, $this->tenantId());
        $mapState = $this->caseMaps->getState($this->tenantId(), $id);
        $mapFeatures = $this->caseMaps->listFeatures($this->tenantId(), $id);
        $assessments = $this->analytical->listAssessments($this->tenantId(), $id);
        $intelGaps = $this->analytical->listGaps($this->tenantId(), $id);
        $analyticalDecisions = $this->analytical->listDecisions($this->tenantId(), $id);
        $caseLinks = $this->analytical->listCaseLinks($this->tenantId(), $id);
        $linkableCases = [];
        foreach ($this->cases->listForTenant($this->tenantId(), $this->access->caseScope(), ['is_folder' => 0]) as $candidate) {
            $cid = (int) ($candidate['id'] ?? 0);
            if ($cid < 1 || $cid === $id) {
                continue;
            }
            $linkableCases[] = [
                'id' => $cid,
                'reference_code' => (string) ($candidate['reference_code'] ?? ''),
                'title' => (string) ($candidate['title'] ?? ''),
            ];
        }
        $libraryEntries = $this->libraryForEditor($case);
        $contextualSuggestions = $this->contextualMentions->suggestForCase(
            $case,
            $people,
            $assessments,
            $intelGaps,
            $caseLinks,
            $libraryEntries
        );
        $executiveBrief = $this->analytical->buildExecutiveBrief(
            $case,
            $people,
            $caseSites,
            $assessments,
            $intelGaps,
            $analyticalDecisions,
            $caseLinks
        );

        return $this->portalView('atak.sse.case_show', [
            'title' => $case['reference_code'] . ' — ' . $case['title'],
            'case' => $case,
            'people' => $people,
            'availablePeople' => $available,
            'armaInbox' => $armaInbox,
            'armaSeizures' => $armaSeizures,
            'casePresets' => $casePresets,
            'caseSites' => $caseSites,
            'siteCounts' => $siteCounts,
            'notes' => $notes,
            'evidence' => $evidence,
            'mapState' => $mapState,
            'mapFeatures' => $mapFeatures,
            'assessments' => $assessments,
            'intelGaps' => $intelGaps,
            'analyticalDecisions' => $analyticalDecisions,
            'caseLinks' => $caseLinks,
            'linkableCases' => $linkableCases,
            'contextualSuggestions' => $contextualSuggestions,
            'executiveBrief' => $executiveBrief,
            'gapPresets' => SseContextualMentionService::presetGapMentions(),
            'analyticalCatalog' => [
                'origins' => SseAnalyticalCatalog::SOURCE_ORIGINS,
                'reliability' => SseAnalyticalCatalog::SOURCE_RELIABILITY,
                'credibility' => SseAnalyticalCatalog::INFO_CREDIBILITY,
                'confidence' => SseAnalyticalCatalog::CONFIDENCE,
                'temporality' => SseAnalyticalCatalog::TEMPORALITY,
                'urgency' => SseAnalyticalCatalog::URGENCY,
                'divergences' => SseAnalyticalCatalog::DIVERGENCES,
                'hypotheses' => SseAnalyticalCatalog::HYPOTHESIS_CODES,
                'gapKinds' => SseAnalyticalCatalog::GAP_KINDS,
                'gapPriorities' => SseAnalyticalCatalog::GAP_PRIORITIES,
                'gapStatuses' => SseAnalyticalCatalog::GAP_STATUSES,
                'decisionDomains' => SseAnalyticalCatalog::DECISION_DOMAINS,
                'relationTypes' => SseAnalyticalCatalog::CASE_RELATION_TYPES,
            ],
            'caseProgress' => $this->caseProgress($id, $case, $people, $caseSites, $evidence),
            'engineSuggestions' => $this->suggestions->listSuggestions($this->tenantId(), [
                'case_id' => $id,
                'status' => 'pending',
                'limit' => 20,
            ]),
            'engineSignals' => $this->suggestions->listSignals($this->tenantId(), $id, 15),
            'originInterestCase' => !empty($case['interest_case_id'])
                ? $this->interestCases->findForTenant((int) $case['interest_case_id'], $this->tenantId())
                : null,
            'classifications' => SseCaseRepository::CLASSIFICATION_LABELS,
            'statuses' => SseCaseRepository::STATUS_LABELS,
            'canManage' => $this->canManage(),
            'canGrant' => $this->canGrant(),
            'canExport' => $this->canExport(),
            'activeNav' => 'dossiers',
            'sseNeedLeaflet' => true,
        ]);
    }

    /**
     * Où en est le dossier : ce qui est fait, ce qui manque, et l'écran où le faire.
     *
     * Un dossier n'est exploitable qu'à partir du moment où il désigne quelqu'un :
     * l'identité rattachée est la seule étape qui décide de la complétude, les
     * autres jalonnent le travail sans le bloquer.
     *
     * @param array<string, mixed> $case
     * @param list<array<string, mixed>> $people
     * @param list<array<string, mixed>> $sites
     * @param list<array<string, mixed>> $evidence
     * @return array{complete: bool, done: int, total: int, steps: list<array<string, mixed>>}
     */
    private function caseProgress(int $caseId, array $case, array $people, array $sites, array $evidence): array
    {
        $eval = $this->completeness->evaluate($this->tenantId(), $case, $people, $sites, $evidence);

        return [
            'complete' => (bool) ($eval['complete'] ?? false),
            'done' => (int) ($eval['done'] ?? 0),
            'total' => (int) ($eval['total'] ?? 0),
            'steps' => $eval['steps'] ?? [],
            'score' => (int) ($eval['score'] ?? 0),
            'digest' => $eval['digest'] ?? null,
            'pending_suggestions' => (int) ($eval['pending_suggestions'] ?? 0),
        ];
    }

    public function suggestionsIndex(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        $caseId = (int) $request->query('case_id', 0);
        $q = trim((string) $request->query('q', ''));
        $caseFilter = $caseId > 0 ? $caseId : null;

        return $this->portalView('atak.sse.suggestions', [
            'title' => 'Rapprochements moteur',
            'suggestions' => $this->suggestions->listSuggestions($tenantId, [
                'case_id' => $caseFilter,
                'status' => 'pending',
                'q' => $q,
                'limit' => 100,
            ]),
            'history' => $this->suggestions->listSuggestions($tenantId, [
                'case_id' => $caseFilter,
                'statuses' => ['accepted', 'rejected', 'deferred'],
                'history' => true,
                'q' => $q,
                'limit' => 60,
            ]),
            'signals' => $this->suggestions->listSignals($tenantId, [
                'case_id' => $caseFilter,
                'q' => $q,
                'limit' => 40,
            ]),
            'pendingCount' => $this->suggestions->countPending($tenantId, $caseFilter),
            'historyCount' => $this->suggestions->countDecided($tenantId, $caseFilter),
            'filterCaseId' => $caseId,
            'searchQuery' => $q,
            'canManage' => $this->canManage(),
            'canGrant' => $this->canGrant(),
            'activeNav' => 'rapprochements',
        ]);
    }

    public function suggestionAccept(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('atak/sse/rapprochements'));
        }
        $result = $this->engine->acceptSuggestion(
            $this->tenantId(),
            $id,
            $this->sseAuthorLabel(),
            (int) Session::get('user_id') ?: null
        );
        Session::flash($result['ok'] ? 'success' : 'error', $result['ok']
            ? 'Rapprochement validé — relation analytique créée le cas échéant. Aucune fusion automatique.'
            : ($result['error'] ?? 'Validation impossible.'));

        $back = (int) $request->input('case_id', 0);

        return Response::redirect($back > 0
            ? url('atak/sse/dossiers/' . $back) . '#moteur'
            : url('atak/sse/rapprochements'));
    }

    public function suggestionReject(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('atak/sse/rapprochements'));
        }
        $result = $this->engine->rejectSuggestion(
            $this->tenantId(),
            $id,
            $this->sseAuthorLabel(),
            (int) Session::get('user_id') ?: null
        );
        Session::flash($result['ok'] ? 'success' : 'error', $result['ok']
            ? 'Proposition rejetée et conservée au registre.'
            : ($result['error'] ?? 'Rejet impossible.'));

        $back = (int) $request->input('case_id', 0);

        return Response::redirect($back > 0
            ? url('atak/sse/dossiers/' . $back) . '#moteur'
            : url('atak/sse/rapprochements'));
    }

    public function engineRunNow(Request $request, array $params = []): Response
    {
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('atak/sse/rapprochements'));
        }
        $tenantId = $this->tenantId();
        $result = $this->engine->runForTenant($tenantId);
        $mailNote = '';
        if ($this->analystDigest !== null) {
            try {
                $runId = (string) ($result['run_id'] ?? ('manual-' . time()));
                $mail = $this->analystDigest->sendForTenant(
                    $tenantId,
                    [],
                    null,
                    true,
                    $tenantId . ':engine:' . $runId
                );
                $sent = (int) ($mail['sent'] ?? 0);
                if ($sent > 0) {
                    $mailNote = sprintf(' · %d e-mail(s) analyste envoyé(s)', $sent);
                } elseif (!empty($mail['skipped_empty'])) {
                    $mailNote = ' · aucun e-mail (rien à signaler)';
                } elseif (!empty($mail['skipped_dedup'])) {
                    $mailNote = ' · e-mail déjà envoyé pour ce passage';
                } elseif (!empty($mail['skipped_no_recipient'])) {
                    $mailNote = ' · aucun destinataire e-mail (droits / préférences)';
                }
            } catch (\Throwable) {
                $mailNote = ' · e-mail non envoyé (erreur temporaire)';
            }
        }
        Session::flash('success', 'Passage moteur terminé — ' . ($result['summary'] ?? '') . $mailNote);

        return Response::redirect(url('atak/sse/rapprochements'));
    }

    public function caseUnlockForm(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $case = $this->requireCase($id);
        if ($case === null) {
            Session::flash('error', 'Dossier introuvable ou hors de votre périmètre.');

            return Response::redirect(url('atak/sse/dossiers'));
        }
        if (!$this->caseNeedsUnlock($case)) {
            return Response::redirect(url('atak/sse/dossiers/' . $id));
        }

        return $this->portalView('atak.sse.case_unlock', [
            'title' => 'Déverrouiller — ' . ($case['reference_code'] ?? ''),
            'case' => $case,
            'canManage' => $this->canManage(),
            'canGrant' => $this->canGrant(),
            'canExport' => $this->canExport(),
            'activeNav' => 'dossiers',
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
        $note = trim((string) $request->input('link_note', ''));
        $this->cases->linkPerson(
            $id,
            $personId,
            $this->tenantId(),
            (int) Session::get('user_id') ?: null,
            $note !== '' ? $note : 'Rattachement depuis le dossier'
        );
        Session::flash('success', 'Personne rattachée au dossier.');

        return Response::redirect(url('atak/sse/dossiers/' . $id));
    }

    public function caseCreatePerson(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('atak/sse/dossiers/' . $id));
        }
        if ($this->requireCase($id) === null) {
            return Response::redirect(url('atak/sse/dossiers'));
        }

        $last = trim((string) $request->input('last_name', ''));
        $first = trim((string) $request->input('first_name', ''));
        $alias = trim((string) $request->input('alias', ''));
        if ($last === '' && $first === '' && $alias === '') {
            Session::flash('error', 'Indiquez au moins un nom, un prénom ou un alias.');

            return Response::redirect(url('atak/sse/dossiers/' . $id));
        }

        $personId = $this->persons->create([
            'tenant_id' => $this->tenantId(),
            'context_id' => 1,
            'status' => (string) $request->input('status', 'civil'),
            'last_name' => $last,
            'first_name' => $first,
            'alias' => $alias,
            'nationality' => trim((string) $request->input('nationality', '')),
            'circumstances' => trim((string) $request->input('circumstances', '')),
            'affiliation' => trim((string) $request->input('affiliation', '')),
            'submitter_callsign' => (string) (Session::get('callsign') ?? Session::get('display_name') ?? 'Bureau'),
            'submitter_user_id' => (int) Session::get('user_id') ?: null,
        ]);

        $this->cases->linkPerson(
            $id,
            $personId,
            $this->tenantId(),
            (int) Session::get('user_id') ?: null,
            'Création depuis le dossier'
        );
        Session::flash('success', 'Identité créée et rattachée au dossier.');

        return Response::redirect(url('atak/sse/dossiers/' . $id));
    }

    public function caseImportSeizure(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('atak/sse/dossiers/' . $id));
        }
        if ($this->requireCase($id) === null) {
            return Response::redirect(url('atak/sse/dossiers'));
        }

        $seizureId = (int) $request->input('seizure_id', 0);
        $siteId = (int) $request->input('site_id', 0);
        if ($seizureId < 1 || $siteId < 1) {
            Session::flash('error', 'Saisie terrain introuvable.');

            return Response::redirect(url('atak/sse/dossiers/' . $id));
        }

        $caseSites = $this->sites->listForCase($id, $this->tenantId());
        $allowed = false;
        $siteName = 'Site';
        foreach ($caseSites as $s) {
            if ((int) ($s['id'] ?? 0) === $siteId) {
                $allowed = true;
                $siteName = (string) ($s['name'] ?? $s['reference_code'] ?? 'Site');
                break;
            }
        }
        if (!$allowed) {
            Session::flash('error', 'Cette saisie n’appartient pas à un site de ce dossier.');

            return Response::redirect(url('atak/sse/dossiers/' . $id));
        }

        $seizure = null;
        foreach ($this->sites->listSeizures($siteId, $this->tenantId()) as $sz) {
            if ((int) ($sz['id'] ?? 0) === $seizureId) {
                $seizure = $sz;
                break;
            }
        }
        if ($seizure === null) {
            Session::flash('error', 'Saisie introuvable.');

            return Response::redirect(url('atak/sse/dossiers/' . $id));
        }

        $label = trim((string) ($seizure['label'] ?? 'Saisie terrain'));
        $qty = (int) ($seizure['quantity'] ?? 1);
        $notes = trim((string) ($seizure['notes'] ?? ''));
        $caption = trim(implode(' · ', array_filter([
            'Remontée Arma / site ' . $siteName,
            $qty > 1 ? ('Quantité ' . $qty) : '',
            $notes,
        ])));

        $this->cases->addEvidence($id, $this->tenantId(), [
            'label' => $label !== '' ? $label : 'Saisie terrain',
            'caption' => $caption,
            'image_path' => null,
            'author_label' => (string) (Session::get('sse_guest_label') ?? Session::get('display_name') ?? 'Terrain'),
        ]);
        Session::flash('success', 'Saisie terrain versée au dossier.');

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
        $imageNote = '';
        if (!empty($_FILES['image']['tmp_name'])) {
            $stored = (new ImageCompressionService())->storeUpload(
                $_FILES['image'],
                base_path('public/uploads/sse/evidence'),
                'uploads/sse/evidence',
                'ev_' . $id
            );
            if (!$stored['ok']) {
                Session::flash('error', $stored['error'] ?? 'L’image de preuve n’a pas pu être enregistrée.');

                return Response::redirect(url('atak/sse/dossiers/' . $id));
            }
            $imagePath = $stored['relative'];
            if (!empty($stored['compressed'])) {
                $imageNote = ' Image compressée automatiquement pour respecter la taille limite.';
            }
        }
        $this->cases->addEvidence($id, $this->tenantId(), [
            'label' => $label !== '' ? $label : 'Preuve',
            'caption' => $caption,
            'image_path' => $imagePath,
            'author_label' => (string) (Session::get('sse_guest_label') ?? Session::get('display_name') ?? 'Opérateur'),
        ]);
        Session::flash('success', 'Preuve enregistrée.' . $imageNote);

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
        if ($locked = $this->redirectIfCaseLocked($case)) {
            return $locked;
        }

        $tenantId = $this->tenantId();
        [$level, $requested, $refused] = $this->resolveExportLevel($request);

        // Un PDF sort du portail et circule ensuite tout seul : c'est le support
        // sur lequel un caviardage manquant coûte le plus cher, puisqu'on ne peut
        // plus le rattraper une fois le fichier transmis.
        $this->activityLog->record(
            $tenantId,
            1,
            'SSE_CLEARANCE',
            sprintf(
                'Export PDF complet du dossier %s en « %s »%s.',
                (string) ($case['reference_code'] ?? $id),
                SseRedactionService::levelLabel($level),
                $refused
                    ? ' (demande « ' . SseRedactionService::levelLabel($requested) . ' » rabattue)'
                    : ''
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
        if ($locked = $this->redirectIfCaseLocked($case)) {
            return $locked;
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
        if ($locked = $this->redirectIfCaseLocked($case)) {
            return $locked;
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

    /**
     * Page d’ouverture du QR « sceau poste de travail » (chemise de dossier).
     * Accessible sans session SSE : le jeton porte le tenant et le dossier.
     */
    public function sealShow(Request $request, array $params = []): Response
    {
        $token = rawurldecode((string) ($params['token'] ?? ''));
        $parsed = \App\Services\Sse\SseSealTokenService::fromEnv()->parse($token);
        if ($parsed === null) {
            return Response::view('atak.sse.seal_show', [
                'title' => 'Sceau poste de travail',
                'valid' => false,
                'message' => 'Ce sceau est inconnu ou a été altéré.',
                'case' => null,
                'workstation' => null,
                'match' => false,
                'canOpen' => false,
                'caseUrl' => null,
            ]);
        }

        $case = $this->cases->findById($parsed['case_id'], $parsed['tenant_id']);
        if ($case === null) {
            return Response::view('atak.sse.seal_show', [
                'title' => 'Sceau poste de travail',
                'valid' => false,
                'message' => 'Le dossier lié à ce sceau est introuvable.',
                'case' => null,
                'workstation' => null,
                'match' => false,
                'canOpen' => false,
                'caseUrl' => null,
            ]);
        }

        $unit = (string) (Session::get('tenant_name') ?? Session::get('community_name') ?? 'Unité Athena');
        $marks = \App\Support\SseDocumentMarkings::forDocument([
            'id' => (int) ($case['id'] ?? 0),
            'reference_code' => (string) ($case['reference_code'] ?? ''),
            'title' => (string) ($case['title'] ?? ''),
            'body' => (string) ($case['summary'] ?? ''),
            'classification' => (string) ($case['classification'] ?? ''),
            'created_at' => (string) ($case['created_at'] ?? ''),
            'updated_at' => (string) ($case['updated_at'] ?? ''),
        ], $unit);
        $ws = is_array($marks['workstation'] ?? null) ? $marks['workstation'] : [];
        $match = hash_equals((string) ($ws['fingerprint_raw'] ?? ''), $parsed['fingerprint'])
            && hash_equals((string) ($ws['id'] ?? ''), $parsed['seal_id']);

        $canOpen = $this->access->hasActiveClearance()
            && $this->tenantId() === $parsed['tenant_id']
            && $this->requireCase($parsed['case_id']) !== null;

        return Response::view('atak.sse.seal_show', [
            'title' => 'Sceau poste de travail — ' . ($case['reference_code'] ?? ''),
            'valid' => true,
            'message' => $match
                ? 'Sceau reconnu : l’empreinte correspond à la chemise actuelle du dossier.'
                : 'Sceau reconnu, mais l’empreinte ne correspond plus à la synthèse actuelle — le dossier a pu être modifié depuis l’impression.',
            'case' => $case,
            'workstation' => $ws,
            'match' => $match,
            'canOpen' => $canOpen,
            'caseUrl' => $canOpen ? url('atak/sse/dossiers/' . $parsed['case_id']) : null,
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
        if ($locked = $this->redirectIfCaseLocked($case)) {
            return $locked;
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
            'nodeTypeLabels' => SseCorrelationService::NODE_TYPE_LABELS,
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

        // Les deux extrémités sont désignées par « type:identifiant » et doivent
        // appartenir au graphe du dossier : on ne relie pas un élément d'un autre
        // compartiment depuis ce formulaire.
        $graph = $this->correlation->graphForCase($id, $this->tenantId());
        $fromKey = (string) $request->input('from', '');
        $toKey = (string) $request->input('to', '');
        if ($fromKey === '' && $request->input('from_id') !== null) {
            $fromKey = 'person:' . (int) $request->input('from_id', 0);
        }
        if ($toKey === '' && $request->input('to_id') !== null) {
            $toKey = 'person:' . (int) $request->input('to_id', 0);
        }

        if (!isset($graph['nodes'][$fromKey], $graph['nodes'][$toKey]) || $fromKey === $toKey) {
            Session::flash('error', 'Choisissez deux éléments différents du dossier.');

            return Response::redirect($back);
        }

        [$fromType, $fromId] = explode(':', $fromKey, 2);
        [$toType, $toId] = explode(':', $toKey, 2);

        $ok = $this->correlation->addRelation($this->tenantId(), $id, [
            'from_type' => $fromType,
            'from_id' => (int) $fromId,
            'to_type' => $toType,
            'to_id' => (int) $toId,
            'relation' => (string) $request->input('relation', 'associe'),
            'reliability' => (string) $request->input('reliability', 'unverified'),
            'note' => (string) $request->input('note', ''),
            'author_label' => (string) (Session::get('display_name') ?? Session::get('callsign') ?? 'Analyste'),
        ]);

        Session::flash($ok ? 'success' : 'error', $ok
            ? 'Lien enregistré.'
            : 'Lien refusé — vérifiez les deux éléments désignés.');

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

        try {
            $pct = $this->terrain->refreshSiteExploitation($tenantId, (int) $site['id']);
            $site['exploitation_pct'] = $pct;
        } catch (\Throwable) {
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
        try {
            $this->terrain->refreshSiteExploitation($this->tenantId(), $siteId);
        } catch (\Throwable) {
        }
        Session::flash('success', $checked ? 'Pièce marquée fouillée.' : 'Pièce remise en attente.');

        return Response::redirect($back);
    }

    public function siteSeizureCustodyAction(Request $request, array $params = []): Response
    {
        $siteId = (int) ($params['id'] ?? 0);
        $seizureId = (int) ($params['seizureId'] ?? 0);
        $back = url('atak/sse/sites/' . $siteId);

        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect($back);
        }

        $row = $this->terrain->advanceSeizureCustody($this->tenantId(), $seizureId, [
            'custody_state' => (string) $request->input('custody_state', 'COLLECTED'),
            'packaging' => (string) $request->input('packaging', ''),
            'seal_code' => (string) $request->input('seal_code', ''),
            'actor_callsign' => (string) (Session::get('display_name') ?? Session::get('callsign') ?? 'Cellule SSE'),
        ]);
        if ($row === null) {
            Session::flash('error', 'Saisie introuvable.');

            return Response::redirect($back);
        }
        Session::flash('success', 'Chaîne de possession mise à jour.');

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
        $q = trim((string) $request->query('q', ''));
        $matches = $this->cross->matchPersonsAgainstWatchlist($this->tenantId());
        $entries = $this->watchlist->listActive($this->tenantId(), $q);

        if ($q !== '') {
            $needle = mb_strtolower($q, 'UTF-8');
            $matches = array_values(array_filter($matches, static function (array $row) use ($needle): bool {
                $person = is_array($row['person'] ?? null) ? $row['person'] : [];
                $hayPerson = mb_strtolower(implode(' ', [
                    (string) ($person['display_name'] ?? ''),
                    (string) ($person['last_name'] ?? ''),
                    (string) ($person['first_name'] ?? ''),
                    (string) ($person['alias'] ?? ''),
                ]), 'UTF-8');
                if (str_contains($hayPerson, $needle)) {
                    return true;
                }
                foreach ($row['matches'] ?? [] as $m) {
                    if (!is_array($m)) {
                        continue;
                    }
                    $entry = is_array($m['entry'] ?? null) ? $m['entry'] : [];
                    $hay = mb_strtolower(implode(' ', [
                        (string) ($entry['display_name'] ?? ''),
                        (string) ($entry['last_name'] ?? ''),
                        (string) ($entry['first_name'] ?? ''),
                        (string) ($entry['alias'] ?? ''),
                        (string) ($entry['notes'] ?? ''),
                        (string) ($m['reason'] ?? ''),
                    ]), 'UTF-8');
                    if (str_contains($hay, $needle)) {
                        return true;
                    }
                }

                return false;
            }));
            foreach ($matches as $i => $row) {
                $matches[$i]['matches'] = array_values(array_filter(
                    is_array($row['matches'] ?? null) ? $row['matches'] : [],
                    static function (array $m) use ($needle, $row): bool {
                        $person = is_array($row['person'] ?? null) ? $row['person'] : [];
                        $hayPerson = mb_strtolower(implode(' ', [
                            (string) ($person['display_name'] ?? ''),
                            (string) ($person['last_name'] ?? ''),
                            (string) ($person['first_name'] ?? ''),
                            (string) ($person['alias'] ?? ''),
                        ]), 'UTF-8');
                        if (str_contains($hayPerson, $needle)) {
                            return true;
                        }
                        $entry = is_array($m['entry'] ?? null) ? $m['entry'] : [];
                        $hay = mb_strtolower(implode(' ', [
                            (string) ($entry['display_name'] ?? ''),
                            (string) ($entry['last_name'] ?? ''),
                            (string) ($entry['first_name'] ?? ''),
                            (string) ($entry['alias'] ?? ''),
                            (string) ($entry['notes'] ?? ''),
                            (string) ($m['reason'] ?? ''),
                        ]), 'UTF-8');

                        return str_contains($hay, $needle);
                    }
                ));
            }
            $matches = array_values(array_filter(
                $matches,
                static fn (array $row): bool => ($row['matches'] ?? []) !== []
            ));
        }

        return $this->portalView('atak.sse.cross', [
            'title' => 'Croisements — listes de surveillance',
            'matches' => $matches,
            'entries' => $entries,
            'searchQuery' => $q,
            'canManage' => $this->canManage(),
            'canGrant' => $this->canGrant(),
            'canExport' => $this->canExport(),
            'activeNav' => 'croisements',
        ]);
    }

    public function gameMaster(Request $request, array $params = []): Response
    {
        if (!$this->canManage()) {
            Session::flash('error', 'Seul le personnel habilité peut ouvrir le poste maître du jeu.');

            return Response::redirect(url('atak/sse/operations'));
        }

        $q = trim((string) $request->query('q', ''));
        $entries = $this->watchlist->listActive($this->tenantId(), $q);
        $persons = $this->persons->listForContext($this->tenantId(), 1, ['limit' => 40]);
        $persons = $this->clearance->redactPeopleForScreens($persons, $this->tenantId());

        return $this->portalView('atak.sse.maitre_jeu', [
            'title' => 'Maître du jeu — SSE',
            'entries' => $entries,
            'persons' => $persons,
            'searchQuery' => $q,
            'canManage' => true,
            'canGrant' => $this->canGrant(),
            'canExport' => $this->canExport(),
            'activeNav' => 'maitre-jeu',
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

        $returnTo = trim((string) $request->input('return_to', ''));
        if ($returnTo === 'maitre-jeu') {
            return Response::redirect(url('atak/sse/maitre-jeu'));
        }

        return Response::redirect(url('atak/sse/croisements'));
    }

    public function watchlistDeactivate(Request $request, array $params = []): Response
    {
        $entryId = (int) ($params['id'] ?? 0);
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('atak/sse/croisements'));
        }
        if ($entryId < 1 || !$this->watchlist->deactivate($entryId, $this->tenantId())) {
            Session::flash('error', 'Entrée introuvable ou déjà retirée.');

            return Response::redirect(url('atak/sse/croisements'));
        }
        Session::flash('success', 'Entrée retirée de la liste de surveillance.');

        $returnTo = trim((string) $request->input('return_to', ''));
        if ($returnTo === 'maitre-jeu') {
            return Response::redirect(url('atak/sse/maitre-jeu'));
        }

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

        $caseLabels = [];
        foreach ($list as $mesh) {
            $caseId = (int) ($mesh['case_id'] ?? 0);
            if ($caseId < 1 || isset($caseLabels[$caseId])) {
                continue;
            }
            $case = $this->cases->findById($caseId, $this->tenantId());
            if ($case === null) {
                continue;
            }
            $caseLabels[$caseId] = trim(
                ((string) ($case['reference_code'] ?? '')) . ' — ' . ((string) ($case['title'] ?? ''))
            );
        }

        $openCount = 0;
        $analysisCount = 0;
        $entityTotal = 0;
        $linkTotal = 0;
        foreach ($list as $mesh) {
            $status = (string) ($mesh['status'] ?? '');
            if ($status === 'ouvert') {
                $openCount++;
            } elseif ($status === 'en_cours') {
                $analysisCount++;
            }
            $id = (int) ($mesh['id'] ?? 0);
            $entityTotal += (int) (($counts[$id]['nodes'] ?? 0));
            $linkTotal += (int) (($counts[$id]['edges'] ?? 0));
        }

        $mergeCandidates = array_values(array_filter(
            $list,
            static fn (array $m): bool => !in_array((string) ($m['status'] ?? ''), ['archive'], true)
        ));

        return $this->portalView('atak.sse.meshes', [
            'title' => 'Investigations — graphe relationnel',
            'meshes' => $list,
            'meshCounts' => $counts,
            'caseLabels' => $caseLabels,
            'metrics' => [
                'total' => count($list),
                'open' => $openCount,
                'analysis' => $analysisCount,
                'entities' => $entityTotal,
                'links' => $linkTotal,
            ],
            'mergeCandidates' => $mergeCandidates,
            'filters' => [
                'status' => (string) $request->query('status', ''),
                'q' => (string) $request->query('q', ''),
            ],
            'statuses' => SseMeshRepository::STATUS_LABELS,
            'canManage' => $this->canManage(),
            'activeNav' => 'toiles',
        ]);
    }

    public function meshMerge(Request $request, array $params = []): Response
    {
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('atak/sse/toiles'));
        }

        $rawIds = $request->input('mesh_ids', []);
        if (!is_array($rawIds)) {
            $rawIds = [];
        }
        $selectedIds = array_values(array_unique(array_filter(
            array_map('intval', $rawIds),
            static fn (int $id): bool => $id > 0
        )));

        $mode = (string) $request->input('merge_mode', 'existing');
        $tenantId = $this->tenantId();
        $userId = (int) Session::get('user_id') ?: null;

        try {
            if ($mode === 'new') {
                if (count($selectedIds) < 2) {
                    Session::flash('error', 'Pour créer une investigation regroupée, sélectionnez au moins deux toiles.');

                    return Response::redirect(url('atak/sse/toiles'));
                }
                $title = trim((string) $request->input('new_title', ''));
                if ($title === '') {
                    Session::flash('error', 'Indiquez un intitulé pour la nouvelle investigation.');

                    return Response::redirect(url('atak/sse/toiles'));
                }
                $sharedCaseId = null;
                $classification = 'encadrement';
                $first = true;
                foreach ($selectedIds as $sid) {
                    $mesh = $this->meshes->findById($sid, $tenantId);
                    if ($mesh === null) {
                        continue;
                    }
                    if ($first) {
                        $classification = (string) ($mesh['classification'] ?? 'encadrement');
                        $sharedCaseId = !empty($mesh['case_id']) ? (int) $mesh['case_id'] : null;
                        $first = false;
                        continue;
                    }
                    $caseId = !empty($mesh['case_id']) ? (int) $mesh['case_id'] : null;
                    if ($sharedCaseId !== $caseId) {
                        $sharedCaseId = null;
                    }
                }
                $targetId = $this->meshes->create([
                    'tenant_id' => $tenantId,
                    'title' => $title,
                    'summary' => 'Investigation regroupée.',
                    'case_id' => $sharedCaseId,
                    'classification' => $classification,
                    'status' => 'en_cours',
                    'created_by' => $userId,
                ]);
                $stats = $this->meshService->mergeInto($targetId, $selectedIds, $tenantId, $userId);
            } else {
                $targetId = (int) $request->input('target_id', 0);
                if ($targetId < 1) {
                    Session::flash('error', 'Choisissez l’investigation qui conservera le graphe regroupé.');

                    return Response::redirect(url('atak/sse/toiles'));
                }
                $sources = array_values(array_filter(
                    $selectedIds,
                    static fn (int $id): bool => $id !== $targetId
                ));
                if ($sources === []) {
                    Session::flash('error', 'Cochez au moins une autre investigation à intégrer dans la cible.');

                    return Response::redirect(url('atak/sse/toiles'));
                }
                $stats = $this->meshService->mergeInto($targetId, $sources, $tenantId, $userId);
            }
        } catch (\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());

            return Response::redirect(url('atak/sse/toiles'));
        } catch (\Throwable) {
            Session::flash('error', 'Le regroupement n’a pas pu être terminé. Réessayez ou ouvrez les investigations une par une.');

            return Response::redirect(url('atak/sse/toiles'));
        }

        Session::flash(
            'success',
            sprintf(
                'Investigations regroupées : %d entité(s) ajoutée(s), %d lien(s), %d toile(s) archivée(s)%s.',
                (int) ($stats['nodes_added'] ?? 0),
                (int) ($stats['edges_added'] ?? 0),
                (int) ($stats['archived'] ?? 0),
                ((int) ($stats['reused'] ?? 0)) > 0
                    ? sprintf(', %d entité(s) déjà présentes réutilisée(s)', (int) $stats['reused'])
                    : ''
            )
        );

        return Response::redirect(url('atak/sse/toiles/' . (int) ($stats['target_id'] ?? $targetId)));
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
        $payload = [];
        $raw = file_get_contents('php://input');
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }
        $csrf = (string) ($payload['_csrf_token'] ?? $request->input('_csrf_token', ''));
        if (!$this->canManage() || !Csrf::validate($csrf)) {
            return Response::json(['ok' => false, 'message' => 'Action non autorisée. Rechargez la page puis réessayez.'], 403);
        }
        if ($this->meshes->findById($id, $this->tenantId()) === null) {
            return Response::json(['ok' => false, 'message' => 'Investigation introuvable.'], 404);
        }
        $positions = $payload['positions'] ?? $request->input('positions', []);
        if (!is_array($positions)) {
            $rawPos = (string) ($payload['positions_json'] ?? $request->input('positions_json', ''));
            $decodedPos = json_decode($rawPos, true);
            $positions = is_array($decodedPos) ? $decodedPos : [];
        }
        if ($positions === []) {
            return Response::json(['ok' => false, 'message' => 'Aucune position reçue.'], 422);
        }
        $saved = 0;
        $failed = 0;
        foreach ($positions as $row) {
            if (!is_array($row)) {
                continue;
            }
            $nid = (int) ($row['id'] ?? 0);
            if ($nid < 1) {
                continue;
            }
            if ($this->meshes->updateNodePosition(
                $nid,
                $this->tenantId(),
                (float) ($row['x'] ?? 0),
                (float) ($row['y'] ?? 0),
                $id
            )) {
                $saved++;
            } else {
                $failed++;
            }
        }
        if ($saved < 1) {
            return Response::json([
                'ok' => false,
                'message' => 'Aucune position n’a pu être enregistrée.',
                'saved' => 0,
                'failed' => $failed,
            ], 422);
        }

        return Response::json([
            'ok' => true,
            'saved' => $saved,
            'failed' => $failed,
            'message' => $saved === 1
                ? 'Disposition enregistrée (1 entité).'
                : sprintf('Disposition enregistrée (%d entités).', $saved),
        ]);
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

    public function searchSuggest(Request $request, array $params = []): Response
    {
        $q = trim((string) $request->query('q', ''));
        $results = [];
        if (mb_strlen($q) >= 2) {
            try {
                $results = $this->workspace->globalSearch($this->tenantId(), $q, $this->access->caseScope(), 12);
            } catch (\Throwable $e) {
                error_log('[sse.searchSuggest] ' . $e->getMessage());
            }
        }

        return Response::json([
            'ok' => true,
            'q' => $q,
            'results' => array_map(static function (array $r): array {
                return [
                    'type' => (string) ($r['type'] ?? ''),
                    'ref' => (string) ($r['ref'] ?? ''),
                    'label' => (string) ($r['label'] ?? ''),
                    'hint' => (string) ($r['hint'] ?? ''),
                    'href' => (string) ($r['href'] ?? ''),
                ];
            }, is_array($results) ? $results : []),
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
        $terrain = [];
        try {
            $terrain = $this->terrain->personTerrainDossier($this->tenantId(), $person);
            if (($terrain['acquisition_quality_avg'] ?? null) !== null) {
                $tech = (int) $terrain['acquisition_quality_avg'];
            }
        } catch (\Throwable) {
            $terrain = [];
        }
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

        $samples = is_array($terrain['biometric_samples'] ?? null) ? $terrain['biometric_samples'] : [];
        $bioPrints = 'Non relevées';
        $bioIris = 'Non relevé';
        foreach ($samples as $s) {
            if (($s['kind'] ?? '') === 'empreintes') {
                $q = $s['quality'] ?? null;
                $bioPrints = $q !== null
                    ? sprintf('Relevées — %s (%d %%)', $s['quality_label'] ?? '', (int) $q)
                    : 'Relevées';
            }
            if (($s['kind'] ?? '') === 'iris') {
                $q = $s['quality'] ?? null;
                $bioIris = $q !== null
                    ? sprintf('Relevé — %s (%d %%)', $s['quality_label'] ?? '', (int) $q)
                    : 'Relevé';
            }
        }
        if ($bioPrints === 'Non relevées' && !empty($person['biometrics_simulated'])) {
            $bioPrints = 'Relevées (sim.)';
        }
        if ($bioIris === 'Non relevé' && !empty($person['biometrics_simulated'])) {
            $bioIris = 'Relevé (sim.)';
        }

        return $this->portalView('atak.sse.person_show', [
            'title' => 'IDN-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT) . ' — ' . (string) ($person['display_name'] ?? ''),
            'person' => $person,
            'terrain' => $terrain,
            'objectMeta' => [
                'ref' => 'IDN-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT),
                'priority' => $global >= 70 ? 'élevée' : 'normale',
                'classification' => 'Confidentiel',
                'last_seen' => (string) ($person['updated_at'] ?? $person['created_at'] ?? '—'),
                'bio_prints' => $bioPrints,
                'bio_iris' => $bioIris,
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
                    !empty($person['biometrics_simulated']) || $samples !== [] ? 'Relevé biométrique présent' : null,
                    !empty($person['affiliation']) ? 'Affiliation déclarée' : null,
                    !empty($terrain['subject_id']) ? 'Identifiant sujet attribué' : null,
                ])),
                'cons' => array_values(array_filter([
                    empty($person['nationality']) ? 'Nationalité non confirmée' : null,
                    empty($person['id_document_present']) ? 'Document d’identité absent' : null,
                    (($terrain['identity_tier'] ?? '') !== 'CONFIRMED') ? 'Identité non confirmée par analyse' : null,
                ])),
                'revised_at' => date('d/m/Y H:i') . 'Z',
                'analyst' => 'Cellule SSE',
            ],
            'timeline' => $timeline,
            'provenance' => [],
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

    public function objectIndex(Request $request, array $params = []): Response
    {
        // Ancienne URL /objets sans type → hub plutôt qu’un 404.
        return Response::redirect(url('atak/sse/operations'));
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
        $back = url('atak/sse/objets/nouveau');
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée. Rechargez la page puis réessayez.');

            return Response::redirect($back);
        }
        $label = trim((string) $request->input('label', ''));
        if ($label === '') {
            Session::flash('error', 'Indiquez un libellé.');

            return Response::redirect($back);
        }
        $kind = SseMeshRepository::normalizeKind((string) $request->input('kind', 'custom'));
        $back .= '?type=' . rawurlencode($kind);
        $metaRaw = $request->input('meta', []);
        $meta = [];
        if (is_array($metaRaw)) {
            foreach ($metaRaw as $k => $v) {
                $meta[(string) $k] = trim((string) $v);
            }
        }
        $freeDetail = trim((string) $request->input('detail', ''));
        if ($freeDetail !== '') {
            $meta['precision'] = mb_substr($freeDetail, 0, 2000);
        }

        $imageUpload = $this->storeObjectImageUpload();
        if ($imageUpload['path'] === false) {
            Session::flash(
                'error',
                $imageUpload['error']
                    ?? 'L’image jointe n’a pas pu être enregistrée. Utilisez un JPEG, PNG, WebP ou GIF (compressé automatiquement si besoin).'
            );

            return Response::redirect($back);
        }
        if (is_string($imageUpload['path']) && $imageUpload['path'] !== '') {
            $meta['image_path'] = $imageUpload['path'];
        }

        $metaLines = SseMeshRepository::formatMetaLines($kind, $meta);
        $detailParts = $metaLines;
        if ($freeDetail !== '') {
            $detailParts[] = mb_strlen($freeDetail) > 100
                ? mb_substr($freeDetail, 0, 97) . '…'
                : $freeDetail;
        }
        $detail = implode(' · ', $detailParts);
        if (mb_strlen($detail) > 250) {
            $detail = mb_substr($detail, 0, 247) . '…';
        }

        try {
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
        } catch (\Throwable $e) {
            error_log('SSE objectStore: ' . $e->getMessage());
            Session::flash('error', 'Impossible de créer l’objet pour le moment. Réessayez dans un instant.');

            return Response::redirect($back);
        }

        $okMsg = 'Objet créé avec ses caractéristiques, placé dans une nouvelle investigation.';
        if (!empty($imageUpload['compressed'])) {
            $okMsg .= ' L’image a été compressée automatiquement pour rester sous 5 Mo.';
        }
        Session::flash('success', $okMsg);

        return Response::redirect(url('atak/sse/toiles/' . $meshId));
    }

    /**
     * Enregistre une image jointe pour un objet SSE (compression si > 5 Mo ou trop grande).
     *
     * @return array{path: string|null|false, compressed: bool, error: ?string}
     */
    private function storeObjectImageUpload(): array
    {
        if (empty($_FILES['image']['tmp_name'])) {
            return ['path' => null, 'compressed' => false, 'error' => null];
        }
        $stored = (new ImageCompressionService())->storeUpload(
            $_FILES['image'],
            base_path('public/uploads/sse/objects'),
            'uploads/sse/objects',
            'obj'
        );
        if (!$stored['ok']) {
            return [
                'path' => false,
                'compressed' => false,
                'error' => $stored['error'],
            ];
        }

        return [
            'path' => $stored['relative'],
            'compressed' => (bool) $stored['compressed'],
            'error' => null,
        ];
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

        $counts = $this->documents->countsByStatus($this->tenantId());

        return $this->portalView('atak.sse.documents', [
            'title' => 'Atelier de rédaction',
            'documents' => $this->documents->listForTenant($this->tenantId(), $filters),
            'statusCounts' => $counts,
            'documentsTotal' => (int) ($counts['total'] ?? 0),
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
        $body = SseDocumentRepository::bodyTemplate($type);
        $case = $caseId > 0 ? $this->requireCase($caseId) : null;

        return $this->portalView('atak.sse.document_form', [
            'title' => 'Nouveau document SSE',
            'document' => null,
            'typeLabels' => SseDocumentRepository::TYPE_LABELS,
            'statusLabels' => SseDocumentRepository::STATUS_LABELS,
            'classifications' => SseCaseRepository::CLASSIFICATION_LABELS,
            'cases' => $this->cases->listForTenant($this->tenantId(), $this->access->caseScope()),
            'bodyTemplates' => SseDocumentRepository::bodyTemplatesByType(),
            'libraryEntries' => $this->libraryForEditor($case),
            'libraryCategories' => SseTextTemplateRepository::categories(),
            'contextualSuggestions' => $this->contextualSuggestionsForCase($case),
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

        $mentions = $this->recordLibraryUses($request, (int) $docId, $caseId, $author, $userId);

        Session::flash('success', 'Document créé et placé en atelier.' . $mentions);

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

        $case = !empty($doc['case_id']) ? $this->requireCase((int) $doc['case_id']) : null;

        return $this->portalView('atak.sse.document_form', [
            'title' => 'Modifier — ' . ($doc['reference_code'] ?? ''),
            'document' => $doc,
            'typeLabels' => SseDocumentRepository::TYPE_LABELS,
            'statusLabels' => SseDocumentRepository::STATUS_LABELS,
            'classifications' => SseCaseRepository::CLASSIFICATION_LABELS,
            'cases' => $this->cases->listForTenant($this->tenantId(), $this->access->caseScope()),
            'bodyTemplates' => SseDocumentRepository::bodyTemplatesByType(),
            'libraryEntries' => $this->libraryForEditor($case),
            'libraryCategories' => SseTextTemplateRepository::categories(),
            'contextualSuggestions' => $this->contextualSuggestionsForCase($case),
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
        $author = (string) (Session::get('sse_guest_label') ?? Session::get('display_name') ?? 'Opérateur');
        $mentions = $this->recordLibraryUses($request, $id, $caseId, $author, (int) Session::get('user_id') ?: null);
        Session::flash('success', 'Document enregistré.' . $mentions);

        return Response::redirect(url('atak/sse/documents/' . $id));
    }

    /**
     * Mentions proposées dans l'éditeur, variables déjà résolues avec ce que l'on connaît
     * du dossier lié. Le texte inséré est ensuite indépendant du modèle.
     *
     * @param array<string,mixed>|null $case
     * @return list<array<string,mixed>>
     */
    /**
     * @return array{
     *   identity_status: list<array<string,string>>,
     *   identity_quick: list<array<string,mixed>>,
     *   evidence: list<array<string,string>>
     * }
     */
    private function loadCasePresets(): array
    {
        $path = base_path('config/sse_case_presets.php');
        $raw = is_file($path) ? require $path : [];
        if (!is_array($raw)) {
            $raw = [];
        }

        return [
            'identity_status' => is_array($raw['identity_status'] ?? null) ? $raw['identity_status'] : [],
            'identity_quick' => is_array($raw['identity_quick'] ?? null) ? $raw['identity_quick'] : [],
            'evidence' => is_array($raw['evidence'] ?? null) ? $raw['evidence'] : [],
        ];
    }

    private function libraryForEditor(?array $case): array
    {
        $tenantId = $this->tenantId();
        $tenant = null;
        try {
            $tenant = (new TenantRepository())->findById($tenantId);
        } catch (\Throwable) {
            $tenant = null;
        }
        $author = (string) (Session::get('sse_guest_label') ?? Session::get('display_name') ?? '');
        $context = SseTextVariables::context($tenant, $case, $author);

        $out = [];
        foreach ($this->textLibrary->listForTenant($tenantId, ['only_active' => true]) as $row) {
            $out[] = [
                'code' => (string) $row['code'],
                'category' => (string) $row['category'],
                'category_label' => (string) $row['category_label'],
                'title' => (string) $row['title'],
                'context' => (string) $row['context'],
                'context_label' => (string) $row['context_label'],
                'doctrine' => (string) ($row['doctrine'] ?? 'neutre'),
                'doctrine_label' => (string) ($row['doctrine_label'] ?? ''),
                'fragment_kind' => (string) ($row['fragment_kind'] ?? 'bloc'),
                'version' => (int) $row['version'],
                'is_default' => (bool) $row['is_default'],
                'content' => SseTextTemplateRepository::render((string) $row['content'], $context),
                'variables' => $row['variable_list'],
            ];
        }

        return $out;
    }

    /**
     * @param array<string,mixed>|null $case
     * @return list<array<string,mixed>>
     */
    private function contextualSuggestionsForCase(?array $case): array
    {
        if ($case === null || empty($case['id'])) {
            return [];
        }
        $caseId = (int) $case['id'];
        $tenantId = $this->tenantId();
        $people = [];
        try {
            foreach ($this->cases->listLinkedPersonIds($caseId, $tenantId) as $link) {
                $p = $this->persons->findById((int) $link['person_id'], $tenantId);
                if ($p) {
                    $people[] = $p;
                }
            }
        } catch (\Throwable) {
            $people = [];
        }

        return $this->contextualMentions->suggestForCase(
            $case,
            $people,
            $this->analytical->listAssessments($tenantId, $caseId),
            $this->analytical->listGaps($tenantId, $caseId),
            $this->analytical->listCaseLinks($tenantId, $caseId),
            $this->libraryForEditor($case)
        );
    }

    /**
     * Consigne quelles mentions ont été insérées, dans quelle version, et le texte
     * effectivement porté au document. Retour : complément de message pour l'opérateur.
     */
    private function recordLibraryUses(Request $request, int $documentId, int $caseId, string $author, ?int $userId): string
    {
        $raw = trim((string) $request->input('inserted_mentions', ''));
        if ($raw === '' || $documentId < 1) {
            return '';
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || $decoded === []) {
            return '';
        }

        $uses = [];
        foreach ($decoded as $item) {
            if (!is_array($item) || trim((string) ($item['code'] ?? '')) === '') {
                continue;
            }
            $uses[] = [
                'code' => (string) $item['code'],
                'version' => (int) ($item['version'] ?? 1),
                'text' => (string) ($item['text'] ?? ''),
            ];
        }
        if ($uses === []) {
            return '';
        }

        $saved = $this->textLibrary->recordUses($this->tenantId(), $documentId, $caseId, $uses, $author, $userId);

        return $saved > 0
            ? sprintf(' %d mention%s de la bibliothèque tracée%s.', $saved, $saved > 1 ? 's' : '', $saved > 1 ? 's' : '')
            : '';
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

    // ─────────────────────────────────────────── Bibliothèque rédactionnelle

    public function textLibraryIndex(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        $filters = [
            'category' => (string) $request->query('categorie', ''),
            'context' => (string) $request->query('contexte', ''),
            'q' => (string) $request->query('q', ''),
        ];

        $entries = $this->textLibrary->listForTenant($tenantId, $filters);
        $grouped = [];
        foreach ($entries as $entry) {
            $grouped[(string) $entry['category']][] = $entry;
        }

        return $this->portalView('atak.sse.text_library', [
            'title' => 'Bibliothèque rédactionnelle',
            'entries' => $entries,
            'groupedEntries' => $grouped,
            'libraryCategories' => SseTextTemplateRepository::categories(),
            'libraryContexts' => SseTextTemplateRepository::contexts(),
            'libraryVariables' => SseTextTemplateRepository::variables(),
            'libraryCounts' => $this->textLibrary->countsByCategory($tenantId),
            'filters' => $filters,
            'editEntry' => $this->textLibrary->findById($tenantId, (int) $request->query('modifier', 0)),
            'canManage' => $this->canManage(),
            'activeNav' => 'bibliotheque',
        ]);
    }

    public function textLibraryStore(Request $request, array $params = []): Response
    {
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('atak/sse/bibliotheque'));
        }

        $id = $this->textLibrary->create($this->tenantId(), [
            'code' => (string) $request->input('code', ''),
            'category' => (string) $request->input('category', ''),
            'title' => (string) $request->input('title', ''),
            'content' => (string) $request->input('content', ''),
            'context' => (string) $request->input('context', ''),
            'classification_min' => (string) $request->input('classification_min', ''),
            'is_default' => (bool) $request->input('is_default', false),
            'sort_order' => (int) $request->input('sort_order', 100),
            'user_id' => (int) Session::get('user_id') ?: null,
        ]);

        if ($id === null) {
            Session::flash('error', 'Mention non enregistrée : vérifiez le code (unique), le titre et le texte.');
        } else {
            Session::flash('success', 'Mention ajoutée à la bibliothèque.');
        }

        return Response::redirect(url('atak/sse/bibliotheque'));
    }

    public function textLibraryUpdate(Request $request, array $params = []): Response
    {
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('atak/sse/bibliotheque'));
        }

        $id = (int) ($params['id'] ?? 0);
        $ok = $this->textLibrary->update($this->tenantId(), $id, [
            'category' => (string) $request->input('category', ''),
            'title' => (string) $request->input('title', ''),
            'content' => (string) $request->input('content', ''),
            'context' => (string) $request->input('context', ''),
            'classification_min' => (string) $request->input('classification_min', ''),
            'is_default' => (bool) $request->input('is_default', false),
            'is_active' => (bool) $request->input('is_active', false),
            'sort_order' => (int) $request->input('sort_order', 100),
            'user_id' => (int) Session::get('user_id') ?: null,
        ]);

        Session::flash(
            $ok ? 'success' : 'error',
            $ok
                ? 'Mention mise à jour. Les documents déjà rédigés conservent le texte qui y avait été porté.'
                : 'Mention non modifiée : titre et texte sont obligatoires.'
        );

        return Response::redirect(url('atak/sse/bibliotheque'));
    }

    public function textLibraryToggle(Request $request, array $params = []): Response
    {
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('atak/sse/bibliotheque'));
        }

        $active = $this->textLibrary->toggleActive($this->tenantId(), (int) ($params['id'] ?? 0));
        if ($active === null) {
            Session::flash('error', 'Mention introuvable.');
        } else {
            Session::flash('success', $active
                ? 'Mention de nouveau proposée à la rédaction.'
                : 'Mention retirée des propositions. Les textes déjà insérés restent en place.');
        }

        return Response::redirect(url('atak/sse/bibliotheque'));
    }

    public function textLibraryDelete(Request $request, array $params = []): Response
    {
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('atak/sse/bibliotheque'));
        }

        $ok = $this->textLibrary->delete($this->tenantId(), (int) ($params['id'] ?? 0));
        Session::flash(
            $ok ? 'success' : 'error',
            $ok
                ? 'Mention supprimée.'
                : 'Les mentions livrées d’origine ne se suppriment pas : retirez-les des propositions.'
        );

        return Response::redirect(url('atak/sse/bibliotheque'));
    }

    // ─────────────────────────────────────────── Lecture du dossier à l'écran

    public function caseReader(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if (!$this->canExport()) {
            Session::flash('error', 'Lecture du dossier complet non autorisée.');

            return Response::redirect(url('atak/sse/dossiers/' . $id));
        }
        $case = $this->requireCase($id);
        if ($case === null) {
            return Response::redirect(url('atak/sse/dossiers'));
        }
        if ($locked = $this->redirectIfCaseLocked($case)) {
            return $locked;
        }

        return $this->portalView('atak.sse.case_reader', [
            'title' => 'Lecture — ' . (string) ($case['reference_code'] ?? ''),
            'case' => $case,
            'levelLabel' => SseRedactionService::levelLabel($this->clearance->maxLevel()),
            'streamUrl' => url('atak/sse/dossiers/' . $id . '/pdf/flux'),
            'downloadUrl' => url('atak/sse/dossiers/' . $id . '/pdf'),
            'canManage' => $this->canManage(),
            'activeNav' => 'dossiers',
        ]);
    }

    /** Même document que l'export, servi pour lecture à l'écran plutôt qu'en téléchargement. */
    public function casePdfStream(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if (!$this->canExport()) {
            return (new Response())->setStatusCode(403)->setBody('<p>Lecture non autorisée.</p>');
        }
        $case = $this->requireCase($id);
        if ($case === null) {
            return (new Response())->setStatusCode(404)->setBody('<p>Dossier introuvable.</p>');
        }
        if ($this->clearance->caseLockEnabled($this->tenantId()) && $this->clearance->caseAboveClearance($case)) {
            return (new Response())->setStatusCode(403)->setBody('<p>Dossier verrouillé.</p>');
        }

        $tenantId = $this->tenantId();
        [$level, $requested, $refused] = $this->resolveExportLevel($request);

        // Lecture à l'écran : pas de fichier qui part circuler, mais la consultation
        // du dossier complet reste un acte à tracer.
        $this->activityLog->record(
            $tenantId,
            1,
            'SSE_CLEARANCE',
            sprintf(
                'Lecture à l’écran du dossier complet %s en « %s »%s.',
                (string) ($case['reference_code'] ?? $id),
                SseRedactionService::levelLabel($level),
                $refused
                    ? ' (demande « ' . SseRedactionService::levelLabel($requested) . ' » rabattue)'
                    : ''
            ),
            (string) (Session::get('display_name') ?? Session::get('callsign') ?? 'Portail')
        );

        return $this->pdf->export($tenantId, $id, $level, true);
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

    /**
     * Dossier accessible en écriture : périmètre OK et sas code passé si le dossier en a un.
     * Ne pas confondre avec caseUnlocked() seul — sans code dossier, unlocked reste vide
     * alors que la consultation est déjà autorisée.
     *
     * @return array<string, mixed>|null
     */
    private function requireWritableCase(int $id): ?array
    {
        $case = $this->requireCase($id);
        if ($case === null || $this->caseNeedsUnlock($case)) {
            return null;
        }

        return $case;
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
        return SseDocumentRepository::bodyTemplate($type);
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

    private function canBypassInterestAcl(): bool
    {
        if ($this->access->isGuest()) {
            return false;
        }

        return function_exists('can') && (can('atak.sse.grant') || can('admin.access'));
    }

    /** @param array<string, mixed> $case */
    private function interestAccessDeniedResponse(array $case): ?Response
    {
        $userId = (int) Session::get('user_id') ?: null;
        if ($this->interestCases->userCanAccessCase($case, $userId, $this->canBypassInterestAcl())) {
            return null;
        }

        Session::flash('error', 'Vous n’êtes pas autorisé à consulter ce dossier d’intérêt.');

        return Response::redirect(url('atak/sse/interet'));
    }

    /**
     * @return array<string, mixed>|Response
     */
    private function requireInterestCaseManageable(int $id, string $back, Request $request): array|Response
    {
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect($back);
        }

        $case = $this->interestCases->findForTenant($id, $this->tenantId());
        if ($case === null) {
            return Response::redirect(url('atak/sse/interet'));
        }

        $denied = $this->interestAccessDeniedResponse($case);
        if ($denied !== null) {
            return $denied;
        }

        return $case;
    }

    private function interestCooldownBlock(int $tenantId, int $caseId, string $actionKey, string $back): ?Response
    {
        $state = $this->interestCases->cooldownState($tenantId, $caseId, $actionKey);
        if (empty($state['blocked'])) {
            return null;
        }

        Session::flash('error', $this->interestCases->formatCooldownHuman($state));

        return Response::redirect($back);
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

    /**
     * @param array<string, mixed> $case
     */
    private function caseNeedsUnlock(array $case): bool
    {
        if (empty($case['has_unlock_code'])) {
            return false;
        }
        // Le commandement habilité à délivrer les accès peut ouvrir sans le code dossier.
        if ($this->canGrant()) {
            return false;
        }

        return !$this->caseUnlocked((int) ($case['id'] ?? 0));
    }

    private function markCaseUnlocked(int $caseId): void
    {
        $unlocked = Session::get('sse_unlocked_cases', []);
        if (!is_array($unlocked)) {
            $unlocked = [];
        }
        $unlocked[$caseId] = time();
        Session::set('sse_unlocked_cases', $unlocked);
    }

    /**
     * Redirige vers le sas de code dossier si nécessaire.
     */
    private function redirectIfCaseLocked(array $case): ?Response
    {
        if (!$this->caseNeedsUnlock($case)) {
            return null;
        }

        return Response::redirect(url('atak/sse/dossiers/' . (int) $case['id'] . '/deverrouiller'));
    }

    /**
     * Niveau d’export PDF : `?niveau=` comme sur la déclassification, sinon plafond session.
     *
     * @return array{0:string,1:string,2:bool} [servi, demandé, rabattu]
     */
    private function resolveExportLevel(Request $request): array
    {
        $requested = (string) ($request->query('niveau') ?? '');
        if ($requested === '' || !isset(SseRedactionService::LEVELS[$requested])) {
            $max = $this->clearance->maxLevel();

            return [$max, $max, false];
        }
        $level = $this->clearance->clamp($requested);

        return [$level, $requested, $requested !== $level];
    }
}
