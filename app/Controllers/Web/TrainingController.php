<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\DocumentLinkRepository;
use App\Repositories\DocumentRepository;
use App\Repositories\TrainingRepository;
use App\Repositories\TrainingCourseRepository;
use App\Repositories\TrainingEnrollmentRepository;
use App\Repositories\TrainingLessonRepository;
use App\Repositories\TrainingQuizRepository;
use App\Repositories\TrainingResourceRepository;
use App\Services\Training\TrainingService;
use App\Services\Training\TrainingProgressService;
use App\Services\Training\TrainingCertificateService;
use App\Services\Training\TrainingQuizService;
use App\Services\Training\TrainingAssignmentService;
use App\Services\Training\TrainingEnrollmentPolicyService;
use App\Services\Training\TrainingStaffAlertService;
use App\Repositories\TrainingCourseLmsSocialRepository;
use App\Repositories\TrainingLessonFeedbackRepository;
use App\Services\Platform\FeatureGateService;
use App\Services\Training\TrainingCertificateShareService;
use App\Services\Training\TrainingAuditService;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\PersonnelAssignmentRepository;
use App\Repositories\UserRepository;
use App\Repositories\HrCharterRepository;
use App\Repositories\TrainingFormationCustomPageRepository;
use App\Repositories\RoleRepository;
use App\Support\TrainingFormationCustomPageRenderer;
use App\Support\TrainingLmsStaffAccess;
use App\Core\Csrf;
use App\Services\Analytics\AnalyticsEventCategory;
use App\Services\Analytics\AnalyticsEventName;
use App\Services\Analytics\AnalyticsEventService;
use App\Services\Analytics\AnalyticsSubjectType;

class TrainingController
{
    public function __construct(
        private TrainingRepository $trainingRepository,
        private DocumentLinkRepository $documentLinkRepository,
        private DocumentRepository $documentRepository,
        private TrainingService $trainingService,
        private TrainingEnrollmentRepository $enrollmentRepository,
        private TrainingProgressService $progressService,
        private TrainingCertificateService $certificateService,
        private TrainingLessonRepository $lessonRepository,
        private TrainingResourceRepository $resourceRepository,
        private TrainingQuizService $quizService,
        private FeatureGateService $featureGate,
        private TrainingAssignmentService $assignmentService,
        private TrainingEnrollmentPolicyService $enrollmentPolicyService,
        private TrainingCourseLmsSocialRepository $lmsSocialRepository,
        private TrainingCourseRepository $trainingCourseRepository,
        private TrainingStaffAlertService $trainingStaffAlertService,
        private TrainingQuizRepository $trainingQuizRepository,
        private TrainingCertificateShareService $certificateShareService,
        private TrainingAuditService $trainingAuditService,
        private TrainingLessonFeedbackRepository $lessonFeedbackRepository,
        private PersonnelProfileRepository $personnelProfileRepository,
        private PersonnelAssignmentRepository $personnelAssignmentRepository,
        private UserRepository $userRepository,
        private AnalyticsEventService $analyticsEventService,
        private HrCharterRepository $hrCharterRepository,
        private TrainingFormationCustomPageRepository $formationCustomPageRepository,
        private RoleRepository $roleRepository,
        private \App\Services\Training\TrainingFormationCustomPageExportPdfService $formationCustomPagePdfExport,
    ) {}

    /**
     * Données d’affichage « personnel RP » pour la page d’attestation partagée (lien signé).
     *
     * @return array{operator_display_name: string, callsign: string, unit_label: string, portrait_url: string}|null
     */
    private function attestationPublicPersonnelPresentation(?array $cert): ?array
    {
        if ($cert === null) {
            return null;
        }
        $tenantId = (int) ($cert['tenant_id'] ?? 0);
        $learnerId = (int) ($cert['user_id'] ?? 0);
        if ($tenantId < 1 || $learnerId < 1) {
            return null;
        }
        $pp = $this->personnelProfileRepository->getByUserId($learnerId) ?? [];
        $userRow = $this->userRepository->findById($learnerId, $tenantId) ?? [];
        $characterName = trim((string) ($pp['character_name'] ?? ''));
        $displayName = trim((string) ($userRow['display_name'] ?? ''));
        $operatorDisplay = $characterName !== '' ? $characterName : $displayName;
        $callsign = trim((string) ($pp['callsign'] ?? ''));
        if ($callsign === '' && isset($userRow['callsign'])) {
            $callsign = trim((string) $userRow['callsign']);
        }
        $assignments = $this->personnelAssignmentRepository->listActiveForUserResolved($learnerId);
        $unitLabel = trim((string) (($assignments[0] ?? [])['unit_name'] ?? ''));
        $portraitPath = trim((string) ($pp['character_portrait_path'] ?? ''));
        $portraitUrl = '';
        if ($portraitPath !== '') {
            $base = rtrim(url(''), '/');
            $portraitUrl = $base . '/' . ltrim($portraitPath, '/');
        }

        return [
            'operator_display_name' => $operatorDisplay,
            'callsign' => $callsign,
            'unit_label' => $unitLabel,
            'portrait_url' => $portraitUrl,
        ];
    }

    private function trainingQuizHasPassingAttempt(int $enrollmentId, int $quizId): bool
    {
        return $this->quizService->findPassingAttempt($enrollmentId, $quizId) !== null;
    }

    /**
     * Cartes de progression pour le sommaire LMS (leçons validées + évaluations réussies).
     *
     * @param array<string, mixed> $course
     * @return array{0: array<int, true>, 1: array<int, true>}
     */
    private function trainingLmsProgressMaps(int $enrollmentId, array $course): array
    {
        $completedLessonIds = [];
        $progressDetail = $this->progressService->getProgressForEnrollment($enrollmentId);
        foreach ($progressDetail['progress'] ?? [] as $row) {
            if ((string) ($row['status'] ?? '') !== 'completed') {
                continue;
            }
            $lid = (int) ($row['lesson_id'] ?? 0);
            if ($lid > 0) {
                $completedLessonIds[$lid] = true;
            }
        }
        $passedQuizIds = [];
        foreach ($course['modules'] ?? [] as $mod) {
            if (!is_array($mod)) {
                continue;
            }
            foreach ($mod['quizzes'] ?? [] as $qz) {
                if (!is_array($qz)) {
                    continue;
                }
                $qid = (int) ($qz['id'] ?? 0);
                if ($qid > 0 && $this->trainingQuizHasPassingAttempt($enrollmentId, $qid)) {
                    $passedQuizIds[$qid] = true;
                }
            }
        }

        return [$completedLessonIds, $passedQuizIds];
    }

    private function responseIfHrCharterBlocking(Request $request, int $tenantId, ?int $userId): ?Response
    {
        if ($userId === null || $userId < 1) {
            return null;
        }
        if (!$this->hrCharterRepository->schemaReady()) {
            return null;
        }
        if (!$this->hrCharterRepository->userMustAcknowledgeBeforeTraining($tenantId, $userId)) {
            return null;
        }
        $path = $request->path();
        if ($path === '' || $path[0] !== '/') {
            $path = '/formations';
        }
        if (str_starts_with($path, '//')) {
            $path = '/formations';
        }

        return Response::redirect(url('account/charte-formations') . '?' . http_build_query(['redirect' => $path]));
    }

    /** Catalogue des formations (nouveau LMS + legacy). */
    public function index(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        $userId = Session::get('user_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $tenantId = (int) $tenantId;
        if (!$this->featureGate->allows($tenantId, 'training')) {
            return Response::view('layout.main', [
                'title' => 'Formations',
                'content' => 'platform.upgrade',
                'feature' => 'training',
                'planName' => 'standard',
            ]);
        }
        $charterBlock = $this->responseIfHrCharterBlocking($request, $tenantId, $userId ? (int) $userId : null);
        if ($charterBlock !== null) {
            return $charterBlock;
        }
        $category = $request->query('category');
        $search = $request->query('search');
        $filterParcours = trim((string) $request->query('parcours', ''));
        $filterLevel = trim((string) $request->query('niveau', ''));
        $filterDuration = trim((string) $request->query('duree', ''));
        $filterModality = trim((string) $request->query('modalite', ''));
        $filterAvailability = trim((string) $request->query('disponibilite', ''));
        if (!in_array($filterParcours, ['', 'communaute', 'plateforme'], true)) {
            $filterParcours = '';
        }
        if (!in_array($filterDuration, ['', 'court', 'moyen', 'long'], true)) {
            $filterDuration = '';
        }
        if (!in_array($filterAvailability, ['', 'ouvert', 'non_commence', 'en_cours', 'termine'], true)) {
            $filterAvailability = '';
        }
        $courses = $this->trainingService->getCatalogue($tenantId, $userId ? (int) $userId : null, $category, $search);
        if ($filterParcours === 'communaute') {
            $courses = array_values(array_filter(
                $courses,
                static fn (array $c): bool => (string) ($c['lms_scope'] ?? TrainingCourseRepository::LMS_SCOPE_TENANT) === TrainingCourseRepository::LMS_SCOPE_TENANT
            ));
        } elseif ($filterParcours === 'plateforme') {
            $courses = array_values(array_filter(
                $courses,
                static fn (array $c): bool => (string) ($c['lms_scope'] ?? '') === TrainingCourseRepository::LMS_SCOPE_PLATFORM
            ));
        }
        $courseLevelValue = static fn (array $c): string => strtolower(trim((string) ($c['level'] ?? '')));
        if ($filterLevel !== '') {
            $courses = array_values(array_filter($courses, static function (array $c) use ($filterLevel, $courseLevelValue): bool {
                return $courseLevelValue($c) === $filterLevel;
            }));
        }
        $courseDurationBucket = static function (array $c): string {
            $mins = max(0, (int) ($c['estimated_minutes'] ?? 0));
            if ($mins <= 30) {
                return 'court';
            }
            if ($mins <= 90) {
                return 'moyen';
            }

            return 'long';
        };
        if ($filterDuration !== '') {
            $courses = array_values(array_filter($courses, static function (array $c) use ($filterDuration, $courseDurationBucket): bool {
                return $courseDurationBucket($c) === $filterDuration;
            }));
        }
        $courseModalityValue = static function (array $c): string {
            $raw = strtolower(trim((string) ($c['learning_format'] ?? $c['modality'] ?? $c['delivery_mode'] ?? 'mixte')));
            return $raw !== '' ? $raw : 'mixte';
        };
        if ($filterModality !== '') {
            $courses = array_values(array_filter($courses, static function (array $c) use ($filterModality, $courseModalityValue): bool {
                return $courseModalityValue($c) === $filterModality;
            }));
        }
        if ($filterAvailability !== '') {
            $courses = array_values(array_filter($courses, static function (array $c) use ($filterAvailability): bool {
                $enr = $c['enrollment'] ?? null;
                $status = is_array($enr) ? (string) ($enr['status'] ?? '') : '';
                $pct = max(0, min(100, (int) ($c['progress_percent'] ?? 0)));
                $state = match (true) {
                    $status === 'completed' || $pct >= 100 => 'termine',
                    $status === 'in_progress' || ($pct > 0 && $pct < 100) => 'en_cours',
                    is_array($enr) && in_array($status, ['assigned', 'pending_approval'], true) && $pct === 0 => 'non_commence',
                    default => 'ouvert',
                };

                return $state === $filterAvailability;
            }));
        }
        $coursesForCategories = $this->trainingService->getCatalogue($tenantId, $userId ? (int) $userId : null, null, null);
        $legacyEnabled = training_legacy_enabled();
        $legacyModules = $legacyEnabled ? $this->trainingRepository->listPublishedForTenant($tenantId) : [];
        $categories = array_values(array_unique(array_filter(array_map(static function ($c) {
            return isset($c['category']) && (string) $c['category'] !== '' ? (string) $c['category'] : null;
        }, $coursesForCategories))));
        sort($categories, SORT_NATURAL | SORT_FLAG_CASE);
        $levelOptions = array_values(array_unique(array_filter(array_map(static fn (array $c): string => $courseLevelValue($c), $coursesForCategories))));
        sort($levelOptions, SORT_NATURAL | SORT_FLAG_CASE);
        $modalityOptions = array_values(array_unique(array_filter(array_map(static fn (array $c): string => $courseModalityValue($c), $coursesForCategories))));
        sort($modalityOptions, SORT_NATURAL | SORT_FLAG_CASE);

        $catalogueSidebarEnrollments = [];
        if ($userId) {
            foreach ($this->enrollmentRepository->listByUserId((int) $userId, $tenantId) as $e) {
                $st = (string) ($e['status'] ?? '');
                if (in_array($st, ['withdrawn', 'revoked', 'expired'], true)) {
                    continue;
                }
                $e['progress_percent'] = $st === 'pending_approval'
                    ? 0
                    : (int) round($this->trainingService->getGlobalProgress((int) $e['id']));
                $catalogueSidebarEnrollments[] = $e;
            }
            usort($catalogueSidebarEnrollments, static function (array $a, array $b): int {
                $rk = static function (array $x): int {
                    return match ((string) ($x['status'] ?? '')) {
                        'in_progress' => 0,
                        'assigned' => 1,
                        'pending_approval' => 2,
                        'failed' => 3,
                        'completed' => 5,
                        default => 9,
                    };
                };
                $cmp = $rk($a) <=> $rk($b);
                if ($cmp !== 0) {
                    return $cmp;
                }

                return strcmp((string) ($a['course_title'] ?? ''), (string) ($b['course_title'] ?? ''));
            });
            $catalogueSidebarEnrollments = array_slice($catalogueSidebarEnrollments, 0, 8);
        }

        $this->analyticsEventService->record(
            $tenantId,
            $userId ? (int) $userId : null,
            AnalyticsEventCategory::TRAINING,
            AnalyticsEventName::TRAINING_CATALOG_VIEW,
            null,
            null,
            null,
            null
        );

        return Response::view('training.catalogue', [
            'title' => 'Formations',
            'courses' => $courses,
            'legacyModules' => $legacyModules,
            'training_legacy_enabled' => $legacyEnabled,
            'filterCategory' => $category !== null && $category !== '' ? (string) $category : null,
            'filterSearch' => $search !== null && $search !== '' ? (string) $search : null,
            'filterCategories' => $categories,
            'filterParcours' => $filterParcours,
            'filterLevel' => $filterLevel,
            'filterDuration' => $filterDuration,
            'filterModality' => $filterModality,
            'filterAvailability' => $filterAvailability,
            'filterLevelOptions' => $levelOptions,
            'filterModalityOptions' => $modalityOptions,
            'catalogueSidebarEnrollments' => $catalogueSidebarEnrollments,
        ]);
    }

    /** Sessions planifiées, préparation et qualifications (hors catalogue). */
    public function sessions(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        $userId = Session::get('user_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $tenantId = (int) $tenantId;
        if (!$this->featureGate->allows($tenantId, 'training')) {
            return Response::view('layout.main', [
                'title' => 'Sessions & suivi',
                'content' => 'platform.upgrade',
                'feature' => 'training',
                'planName' => 'standard',
            ]);
        }
        $charterBlock = $this->responseIfHrCharterBlocking($request, $tenantId, $userId ? (int) $userId : null);
        if ($charterBlock !== null) {
            return $charterBlock;
        }

        $courses = $this->trainingService->getCatalogue($tenantId, $userId ? (int) $userId : null, null, null);
        $legacyEnabled = training_legacy_enabled();
        $legacyModules = $legacyEnabled ? $this->trainingRepository->listPublishedForTenant($tenantId) : [];
        $totalModules = count($courses) + ($legacyEnabled ? count($legacyModules) : 0);

        return Response::view('training.sessions', [
            'title' => 'Sessions & suivi',
            'totalModules' => $totalModules,
        ]);
    }

    /** Mes formations (enrollments). */
    public function myTraining(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        $userId = Session::get('user_id');
        if (!$tenantId || !$userId) {
            return Response::redirect(url('login'));
        }
        $charterBlock = $this->responseIfHrCharterBlocking($request, (int) $tenantId, (int) $userId);
        if ($charterBlock !== null) {
            return $charterBlock;
        }
        $enrollments = $this->enrollmentRepository->listByUserId((int) $userId, (int) $tenantId);
        $withProgress = [];
        foreach ($enrollments as $e) {
            $st = (string) ($e['status'] ?? '');
            $e['progress_percent'] = $st === 'pending_approval'
                ? 0.0
                : $this->trainingService->getGlobalProgress((int) $e['id']);
            $withProgress[] = $e;
        }
        usort($withProgress, static function (array $a, array $b): int {
            $rank = static function (array $x): int {
                return match ($x['status'] ?? '') {
                    'in_progress' => 0,
                    'pending_approval' => 1,
                    'assigned' => 2,
                    'completed' => 3,
                    'withdrawn' => 4,
                    'revoked' => 5,
                    default => 9,
                };
            };
            $ra = $rank($a);
            $rb = $rank($b);
            if ($ra !== $rb) {
                return $ra <=> $rb;
            }

            return strcmp((string) ($b['assigned_at'] ?? ''), (string) ($a['assigned_at'] ?? ''));
        });

        $stats = [
            'total' => count($withProgress),
            'in_progress' => count(array_filter($withProgress, static fn (array $x): bool => ($x['status'] ?? '') === 'in_progress')),
            'assigned' => count(array_filter($withProgress, static fn (array $x): bool => ($x['status'] ?? '') === 'assigned')),
            'completed' => count(array_filter($withProgress, static fn (array $x): bool => ($x['status'] ?? '') === 'completed')),
            'expiring_soon' => count(array_filter($withProgress, static function (array $x): bool {
                if (empty($x['expires_at']) || in_array($x['status'] ?? '', ['completed', 'revoked', 'withdrawn', 'expired'], true)) {
                    return false;
                }
                $t = strtotime((string) $x['expires_at']);

                return $t !== false && $t <= strtotime('+30 days');
            })),
        ];

        $filter = (string) $request->query('filter', 'all');
        if (!in_array($filter, ['all', 'active', 'done', 'expiring', 'pending'], true)) {
            $filter = 'all';
        }

        return Response::view('training.my-training', [
            'title' => 'Mes formations',
            'enrollments' => $withProgress,
            'trainingStats' => $stats,
            'trainingFilter' => $filter,
        ]);
    }

    /** Saisie d’un code court pour ouvrir une formation publiée du même espace. */
    public function accessCodeForm(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        $userId = Session::get('user_id');
        if (!$tenantId || !$userId) {
            return Response::redirect(url('login'));
        }
        $tenantId = (int) $tenantId;
        if (!$this->featureGate->allows($tenantId, 'training')) {
            return Response::view('layout.main', [
                'title' => 'Formations',
                'content' => 'platform.upgrade',
                'feature' => 'training',
                'planName' => 'standard',
            ]);
        }
        $charterBlock = $this->responseIfHrCharterBlocking($request, $tenantId, (int) $userId);
        if ($charterBlock !== null) {
            return $charterBlock;
        }

        return Response::view('layout.main', [
            'title' => 'Trouver une formation par code',
            'content' => 'training.access_code',
        ]);
    }

    public function accessCodeSubmit(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        $userId = Session::get('user_id');
        if (!$tenantId || !$userId) {
            return Response::redirect(url('login'));
        }
        $tenantId = (int) $tenantId;
        if (!$this->featureGate->allows($tenantId, 'training')) {
            return Response::redirect(url('formations'));
        }
        $charterBlock = $this->responseIfHrCharterBlocking($request, $tenantId, (int) $userId);
        if ($charterBlock !== null) {
            return $charterBlock;
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée, réessayez.');

            return Response::redirect(url('formations/code-acces'));
        }
        $raw = trim((string) $request->input('access_code', ''));
        $code = function_exists('training_lms_normalize_share_code') ? training_lms_normalize_share_code($raw) : strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $raw) ?? '');
        if ($code === '') {
            Session::flash('error', 'Indiquez le code reçu.');

            return Response::redirect(url('formations/code-acces'));
        }
        $row = $this->trainingCourseRepository->findByShareCode($code);
        if (!$row) {
            Session::flash('error', 'Aucune formation ne correspond à ce code. Vérifiez la saisie ou demandez un code à jour.');

            return Response::redirect(url('formations/code-acces'));
        }
        if ((string) ($row['visibility'] ?? '') !== 'published') {
            Session::flash('error', 'Cette formation n’est pas disponible dans le catalogue pour le moment.');

            return Response::redirect(url('formations/code-acces'));
        }
        if ((int) ($row['tenant_id'] ?? 0) === $tenantId) {
            $cid = (int) ($row['id'] ?? 0);
            if ($cid > 0) {
                $this->analyticsEventService->record(
                    $tenantId,
                    (int) $userId,
                    AnalyticsEventCategory::TRAINING,
                    AnalyticsEventName::COURSE_SHARE_CODE_USED,
                    AnalyticsSubjectType::TRAINING_COURSE,
                    $cid,
                    null,
                    null
                );
            }
            $slug = trim((string) ($row['slug'] ?? ''));

            return Response::redirect($slug !== '' ? url('formations/' . rawurlencode($slug)) : url('formations'));
        }
        Session::flash(
            'error',
            'Ce code correspond à une formation d’une autre communauté. Connectez-vous à l’espace de cette communauté pour y accéder, ou demandez à votre référent de vous y inviter.'
        );

        return Response::redirect(url('formations/code-acces'));
    }

    /** Détail d'une formation (par slug ou id). */
    public function course(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        $userId = Session::get('user_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $tenantId = (int) $tenantId;
        $charterBlock = $this->responseIfHrCharterBlocking($request, $tenantId, $userId ? (int) $userId : null);
        if ($charterBlock !== null) {
            return $charterBlock;
        }
        $slugOrId = $params['slug'] ?? $params['id'] ?? '';
        $course = $this->trainingService->getCourseBySlugOrId($tenantId, $slugOrId);
        if (!$course) {
            return Response::view('training.formation_introuvable', [
                'slug' => (string) $slugOrId,
                'context' => 'fiche',
            ])->setStatusCode(404);
        }
        $courseId = (int) $course['id'];
        $course = $this->trainingService->getCourseWithStructure($courseId, $tenantId);
        $enrollment = $userId ? $this->enrollmentRepository->findByCourseAndUser($courseId, (int) $userId) : null;
        if ($enrollment && in_array((string) ($enrollment['status'] ?? ''), training_enrollment_inactive_for_member_ui_statuses(), true)) {
            $enrollment = null;
        }
        $canAccessLearning = $userId && $enrollment
            ? $this->trainingService->canAccessCourse((int) $userId, $courseId, $tenantId)
            : false;
        $canWithdrawEnrollment = false;
        if ($userId && $enrollment && training_enrollment_can_withdraw_by_member($enrollment)) {
            $canWithdrawEnrollment = $this->certificateService->getByEnrollment((int) $enrollment['id']) === null;
        }
        $progressPercent = ($enrollment && $canAccessLearning) ? $this->trainingService->getGlobalProgress((int) $enrollment['id']) : 0;
        $certificate = ($enrollment && $canAccessLearning) ? $this->certificateService->getByEnrollment((int) $enrollment['id']) : null;
        $lmsShowCompletionBanner = false;
        if ($enrollment && $canAccessLearning && $userId) {
            $enrStatus = (string) ($enrollment['status'] ?? '');
            $cpBanner = $this->progressService->computeCourseProgress((int) $enrollment['id']);
            if ($enrStatus === 'completed' || !empty($cpBanner['completed'])) {
                $lmsShowCompletionBanner = true;
                if ($enrStatus !== 'completed' && !empty($cpBanner['completed'])) {
                    try {
                        $this->progressService->maybeCompleteEnrollmentAndNotify(
                            (int) $enrollment['id'],
                            $tenantId,
                            (int) $userId
                        );
                        $enrollment = $this->enrollmentRepository->findById((int) $enrollment['id'], $tenantId) ?: $enrollment;
                    } catch (\Throwable) {
                    }
                }
                if ((int) ($course['is_certifying'] ?? 0) === 1) {
                    if (!$certificate) {
                        $issued = $this->certificateService->issueCertificate((int) $enrollment['id'], $tenantId, (int) $userId);
                        if ($issued) {
                            $certificate = $issued;
                        }
                    }
                }
            }
        }
        $policyEval = $userId
            ? $this->enrollmentPolicyService->evaluateSelfEnroll((int) $userId, $tenantId, $course)
            : ['allowed' => false, 'messages' => []];
        $policyDisplay = $this->enrollmentPolicyService->getPublicPolicyDisplay($tenantId, $course, $userId ? (int) $userId : null);
        $policyDecoded = $this->enrollmentPolicyService->decodePolicy($course['enrollment_policy_json'] ?? null);
        $lmsCommentsEnabled = function_exists('training_lms_policy_comments_enabled')
            ? \training_lms_policy_comments_enabled($policyDecoded)
            : true;
        $isFavorite = $userId ? $this->lmsSocialRepository->isFavorite((int) $userId, $courseId) : false;
        $isLiked = $userId ? $this->lmsSocialRepository->isLiked((int) $userId, $courseId) : false;
        $sessions = $this->lmsSocialRepository->listSessionsForCourse($courseId);

        $this->analyticsEventService->record(
            $tenantId,
            $userId ? (int) $userId : null,
            AnalyticsEventCategory::TRAINING,
            AnalyticsEventName::COURSE_VIEW,
            AnalyticsSubjectType::TRAINING_COURSE,
            $courseId,
            null,
            null
        );

        $orderedLessons = function_exists('training_lms_ordered_lessons') ? training_lms_ordered_lessons($course) : [];
        $continueLesson = null;
        $firstLesson = $orderedLessons !== [] ? $orderedLessons[0] : null;
        $lessonDone = [];
        if ($canAccessLearning && $enrollment && $orderedLessons !== [] && function_exists('training_lms_next_incomplete_lesson')) {
            $enrollmentProgressDetail = $this->progressService->getProgressForEnrollment((int) $enrollment['id']);
            foreach ($enrollmentProgressDetail['progress'] ?? [] as $p) {
                if (($p['status'] ?? '') === 'completed') {
                    $lessonDone[(int) ($p['lesson_id'] ?? 0)] = true;
                }
            }
            $continueLesson = training_lms_next_incomplete_lesson($orderedLessons, $enrollmentProgressDetail['progress'] ?? []);
        }

        $gate = Gate::getInstance();
        $canPublishOperationalBoard = $gate->allows('operational.board.edit')
            || $gate->allows('admin.organization')
            || $gate->allows('admin.access')
            || $gate->allows('admin.system');

        $lmsPassedQuizIds = [];
        if ($canAccessLearning && $enrollment) {
            [, $lmsPassedQuizIds] = $this->trainingLmsProgressMaps((int) $enrollment['id'], $course);
        }

        return Response::view('training.course', [
            'title' => $course['title'],
            'course' => $course,
            'enrollment' => $enrollment,
            'progressPercent' => $progressPercent,
            'certificate' => $certificate,
            'lmsShowCompletionBanner' => $lmsShowCompletionBanner,
            'policyEval' => $policyEval,
            'policyDisplay' => $policyDisplay,
            'isFavorite' => $isFavorite,
            'isLiked' => $isLiked,
            'courseSessions' => $sessions,
            'viewerLoggedIn' => (bool) $userId,
            'continueLesson' => $continueLesson,
            'firstLesson' => $firstLesson,
            'lessonDone' => $lessonDone,
            'canAccessLearning' => $canAccessLearning,
            'lmsCommentsEnabled' => $lmsCommentsEnabled,
            'canWithdrawEnrollment' => $canWithdrawEnrollment,
            'canPublishOperationalBoard' => $canPublishOperationalBoard,
            'lmsPassedQuizIds' => $lmsPassedQuizIds,
            'analyticsBeacon' => [
                'tenantId' => $tenantId,
                'category' => AnalyticsEventCategory::TRAINING,
                'durationEvent' => AnalyticsEventName::COURSE_PAGE_DURATION,
                'subjectType' => AnalyticsSubjectType::TRAINING_COURSE,
                'subjectId' => $courseId,
            ],
        ]);
    }

    public function postWithdrawEnrollment(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId < 1 || $userId < 1) {
            return Response::redirect(url('login'));
        }
        $redirectTo = $this->trainingWithdrawRedirectUrl($request);
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée, réessayez.');

            return Response::redirect($redirectTo);
        }
        $enrollmentId = (int) $request->input('enrollment_id', 0);
        if ($enrollmentId < 1) {
            Session::flash('error', 'Demande incomplète.');

            return Response::redirect($redirectTo);
        }
        $enrollment = $this->enrollmentRepository->findById($enrollmentId, $tenantId);
        if (!$enrollment || (int) ($enrollment['user_id'] ?? 0) !== $userId) {
            Session::flash('error', 'Inscription introuvable ou non autorisée.');

            return Response::redirect($redirectTo);
        }
        if (!training_enrollment_can_withdraw_by_member($enrollment)) {
            Session::flash('error', 'Cette inscription ne peut pas être annulée dans l’état actuel.');

            return Response::redirect($redirectTo);
        }
        if ($this->certificateService->getByEnrollment($enrollmentId) !== null) {
            Session::flash('error', 'Une attestation a déjà été délivrée pour ce parcours : l’inscription ne peut plus être annulée.');

            return Response::redirect($redirectTo);
        }
        $prevStatus = (string) ($enrollment['status'] ?? '');
        $courseId = (int) ($enrollment['course_id'] ?? 0);
        $courseTitle = (string) ($enrollment['course_title'] ?? '');
        $ok = $this->enrollmentRepository->withdrawByLearner($enrollmentId, $userId, $tenantId);
        if (!$ok) {
            Session::flash('error', 'L’annulation n’a pas pu être enregistrée. Réessayez ou contactez le staff.');

            return Response::redirect($redirectTo);
        }
        $this->trainingAuditService->logEnrollmentWithdrawn($tenantId, $userId, $enrollmentId, [
            'user_id' => $userId,
            'course_id' => $courseId,
            'course_title' => $courseTitle,
            'previous_status' => $prevStatus,
        ]);
        Session::flash('success', 'Votre inscription à cette formation a été annulée.');

        return Response::redirect($redirectTo);
    }

    private function trainingWithdrawRedirectUrl(Request $request): string
    {
        $to = trim((string) $request->input('return_path', ''));
        if ($to !== '' && str_starts_with($to, 'formations/') && !str_contains($to, '..') && strlen($to) < 400) {
            return url($to);
        }

        return url('formations/mes-formations');
    }

    public function postEnroll(Request $request, array $params = []): Response
    {
        return $this->redirectCourseAfter($request, function () use ($request): void {
            $tenantId = (int) Session::get('tenant_id');
            $userId = (int) Session::get('user_id');
            $courseId = (int) $request->input('course_id', 0);
            if ($courseId < 1 || $tenantId < 1 || $userId < 1) {
                Session::flash('error', 'Données invalides.');

                return;
            }
            if (!Csrf::validate((string) $request->input('_csrf_token'))) {
                Session::flash('error', 'Session expirée.');

                return;
            }
            try {
                $motivation = trim((string) $request->input('enrollment_motivation', ''));
                $motivation = $motivation === '' ? null : mb_substr($motivation, 0, 4000);
                $eid = $this->assignmentService->assignUser($courseId, $userId, $tenantId, $userId, 'self_enroll', null, $motivation);
                $enr = $this->enrollmentRepository->findById($eid, $tenantId);
                if ($enr && (($enr['status'] ?? '') === 'assigned')) {
                    $this->progressService->startEnrollment($eid, $tenantId, $userId);
                }
                $st = $enr ? (string) ($enr['status'] ?? '') : '';
                Session::flash(
                    'success',
                    $st === 'pending_approval'
                        ? 'Demande enregistrée. Un formateur doit valider votre inscription avant l’accès au parcours.'
                        : 'Inscription confirmée.'
                );
            } catch (\Throwable $e) {
                Session::flash('error', $e->getMessage());
            }
        });
    }

    public function postFavorite(Request $request, array $params = []): Response
    {
        return $this->redirectCourseAfter($request, function () use ($request): void {
            $tenantId = (int) Session::get('tenant_id');
            $userId = (int) Session::get('user_id');
            $courseId = (int) $request->input('course_id', 0);
            $on = (int) $request->input('favorite', 1) === 1;
            if ($courseId < 1 || !$tenantId || !$userId) {
                Session::flash('error', 'Données invalides.');

                return;
            }
            if (!Csrf::validate((string) $request->input('_csrf_token'))) {
                Session::flash('error', 'Session expirée.');

                return;
            }
            $this->lmsSocialRepository->setFavorite($tenantId, $userId, $courseId, $on);
            Session::flash('success', $on ? 'Ajouté aux favoris.' : 'Retiré des favoris.');
        });
    }

    public function postLike(Request $request, array $params = []): Response
    {
        return $this->redirectCourseAfter($request, function () use ($request): void {
            $tenantId = (int) Session::get('tenant_id');
            $userId = (int) Session::get('user_id');
            $courseId = (int) $request->input('course_id', 0);
            $on = (int) $request->input('like', 1) === 1;
            if ($courseId < 1 || !$tenantId || !$userId) {
                Session::flash('error', 'Données invalides.');

                return;
            }
            if (!Csrf::validate((string) $request->input('_csrf_token'))) {
                Session::flash('error', 'Session expirée.');

                return;
            }
            $this->lmsSocialRepository->setLike($tenantId, $userId, $courseId, $on);
            Session::flash('success', $on ? 'Merci pour votre soutien.' : '« J’aime » retiré.');
        });
    }

    public function postReview(Request $request, array $params = []): Response
    {
        return $this->redirectCourseAfter($request, function () use ($request): void {
            $tenantId = (int) Session::get('tenant_id');
            $userId = (int) Session::get('user_id');
            $courseId = (int) $request->input('course_id', 0);
            $rating = max(1, min(5, (int) $request->input('rating', 5)));
            $body = trim((string) $request->input('review_body', ''));
            $kind = ((string) $request->input('review_kind', 'rating')) === 'review' ? 'review' : 'rating';
            if ($courseId < 1 || !$tenantId || !$userId) {
                Session::flash('error', 'Données invalides.');

                return;
            }
            if (!Csrf::validate((string) $request->input('_csrf_token'))) {
                Session::flash('error', 'Session expirée.');

                return;
            }
            $this->lmsSocialRepository->upsertReview($tenantId, $userId, $courseId, $rating, null, $body === '' ? null : $body, $kind);
            Session::flash('success', $kind === 'review' ? 'Revue enregistrée.' : 'Avis enregistré.');
        });
    }

    public function postQuestion(Request $request, array $params = []): Response
    {
        return $this->redirectCourseAfter($request, function () use ($request): void {
            $tenantId = (int) Session::get('tenant_id');
            $userId = (int) Session::get('user_id');
            $courseId = (int) $request->input('course_id', 0);
            $text = trim((string) $request->input('question_text', ''));
            if ($courseId < 1 || !$tenantId || !$userId || $text === '') {
                Session::flash('error', 'Question vide ou invalide.');

                return;
            }
            if (!Csrf::validate((string) $request->input('_csrf_token'))) {
                Session::flash('error', 'Session expirée.');

                return;
            }
            $id = $this->lmsSocialRepository->addQuestion($tenantId, $userId, $courseId, $text);
            Session::flash('success', $id > 0 ? 'Question envoyée.' : 'Question non enregistrée (migration ?).');
        });
    }

    public function postComment(Request $request, array $params = []): Response
    {
        return $this->redirectCourseAfter($request, function () use ($request): void {
            $tenantId = (int) Session::get('tenant_id');
            $userId = (int) Session::get('user_id');
            $courseId = (int) $request->input('course_id', 0);
            $body = trim((string) $request->input('comment_body', ''));
            $parentId = (int) $request->input('parent_id', 0);

            if ($courseId < 1 || !$tenantId || !$userId || $body === '') {
                Session::flash('error', 'Commentaire vide ou invalide.');

                return;
            }
            if (!Csrf::validate((string) $request->input('_csrf_token'))) {
                Session::flash('error', 'Session expirée.');

                return;
            }
            $courseRow = $this->trainingService->getCourseBySlugOrId($tenantId, (string) $request->input('course_slug', ''));
            if (!$courseRow || (int) ($courseRow['id'] ?? 0) !== $courseId) {
                $courseRow = $this->trainingCourseRepository->findById($courseId, $tenantId);
            }
            $pol = $courseRow ? $this->enrollmentPolicyService->decodePolicy($courseRow['enrollment_policy_json'] ?? null) : [];
            if (!function_exists('training_lms_policy_comments_enabled') || !\training_lms_policy_comments_enabled($pol)) {
                Session::flash('error', 'Les commentaires sont désactivés pour cette formation.');

                return;
            }
            $id = $this->lmsSocialRepository->addComment($tenantId, $userId, $courseId, $body, $parentId > 0 ? $parentId : null);
            Session::flash('success', $id > 0 ? 'Commentaire publié.' : 'Non enregistré (migration ?).');
        });
    }

    /** @param callable(): void $fn */
    private function redirectCourseAfter(Request $request, callable $fn): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $uidPost = Session::get('user_id');
        $charterBlock = $this->responseIfHrCharterBlocking($request, (int) $tenantId, $uidPost ? (int) $uidPost : null);
        if ($charterBlock !== null) {
            return $charterBlock;
        }
        $slug = trim((string) $request->input('course_slug', ''));
        $fn();
        if ($slug !== '') {
            if (trim((string) $request->input('social_return', '')) === 'echanges') {
                return Response::redirect(url('formations/' . rawurlencode($slug) . '/echanges'));
            }

            return Response::redirect(url('formations/' . rawurlencode($slug)));
        }

        return Response::redirect(url('formations'));
    }

    /** Page dédiée : avis, note, questions et commentaires (hors fiche formation / leçons). */
    public function courseExchanges(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        $userId = Session::get('user_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $tenantId = (int) $tenantId;
        $charterBlock = $this->responseIfHrCharterBlocking($request, $tenantId, $userId ? (int) $userId : null);
        if ($charterBlock !== null) {
            return $charterBlock;
        }
        $slug = trim((string) ($params['slug'] ?? ''));
        if ($slug === '') {
            return Response::redirect(url('formations'));
        }
        $course = $this->trainingService->getCourseBySlugOrId($tenantId, $slug);
        if (!$course) {
            return Response::view('training.formation_introuvable', [
                'slug' => $slug,
                'context' => 'echanges',
            ])->setStatusCode(404);
        }
        $courseId = (int) $course['id'];
        $course = $this->trainingService->getCourseWithStructure($courseId, $tenantId);
        $enrollment = $userId ? $this->enrollmentRepository->findByCourseAndUser($courseId, (int) $userId) : null;
        if ($enrollment && in_array((string) ($enrollment['status'] ?? ''), training_enrollment_inactive_for_member_ui_statuses(), true)) {
            $enrollment = null;
        }
        $canAccessLearning = $userId && $enrollment
            ? $this->trainingService->canAccessCourse((int) $userId, $courseId, $tenantId)
            : false;
        $canWithdrawEnrollment = false;
        if ($userId && $enrollment && training_enrollment_can_withdraw_by_member($enrollment)) {
            $canWithdrawEnrollment = $this->certificateService->getByEnrollment((int) $enrollment['id']) === null;
        }
        $progressPercent = ($enrollment && $canAccessLearning) ? $this->trainingService->getGlobalProgress((int) $enrollment['id']) : 0;
        $policyDecoded = $this->enrollmentPolicyService->decodePolicy($course['enrollment_policy_json'] ?? null);
        $lmsCommentsEnabled = function_exists('training_lms_policy_comments_enabled')
            ? \training_lms_policy_comments_enabled($policyDecoded)
            : true;

        $reviews = $this->lmsSocialRepository->listPublishedReviews($courseId);
        $avgRating = $this->lmsSocialRepository->averageRating($courseId);
        $questions = $this->lmsSocialRepository->listQuestionsForCourse($courseId);
        $comments = $this->lmsSocialRepository->listCommentsForCourse($courseId);
        $userReview = $userId ? $this->lmsSocialRepository->findUserReview((int) $userId, $courseId, 'rating') : null;

        $orderedLessons = function_exists('training_lms_ordered_lessons') ? training_lms_ordered_lessons($course) : [];
        $firstLesson = $orderedLessons !== [] ? $orderedLessons[0] : null;

        return Response::view('training.course_exchanges', [
            'title' => 'Avis et échanges — ' . (string) ($course['title'] ?? ''),
            'course' => $course,
            'enrollment' => $enrollment,
            'progressPercent' => $progressPercent,
            'courseReviews' => $reviews,
            'courseAvgRating' => $avgRating,
            'courseQuestions' => $questions,
            'courseComments' => $comments,
            'userReview' => $userReview,
            'viewerLoggedIn' => (bool) $userId,
            'firstLesson' => $firstLesson,
            'canAccessLearning' => $canAccessLearning,
            'lmsCommentsEnabled' => $lmsCommentsEnabled,
            'canWithdrawEnrollment' => $canWithdrawEnrollment,
        ]);
    }

    /** Route unique /formations/{slug} : détail formation LMS si slug trouvé en training_courses, sinon legacy module. */
    public function showBySlug(Request $request, array $params = []): Response
    {
        $slug = $params['slug'] ?? '';
        if ($slug === '') {
            return Response::redirect(url('formations'));
        }
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $tenantId = (int) $tenantId;
        $course = $this->trainingService->getCourseBySlugOrId($tenantId, $slug);
        if ($course) {
            $params['id'] = $slug;
            return $this->course($request, $params);
        }
        if (training_legacy_enabled()) {
            $legacyUid = Session::get('user_id');
            $charterLegacy = $this->responseIfHrCharterBlocking($request, $tenantId, $legacyUid ? (int) $legacyUid : null);
            if ($charterLegacy !== null) {
                return $charterLegacy;
            }

            return $this->show($request, $params);
        }

        return Response::view('training.formation_introuvable', [
            'slug' => (string) $slug,
            'context' => 'fiche',
        ])->setStatusCode(404);
    }

    /**
     * Page HTML autonome publiée (pilotage : /formation/pages-html).
     * URL publique : /formations/page/{slug}.
     */
    public function formationCustomPage(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        $userId = Session::get('user_id');
        if (!$tenantId || !$userId) {
            return Response::redirect(url('login'));
        }
        $tenantId = (int) $tenantId;
        $userId = (int) $userId;
        if (!$this->featureGate->allows($tenantId, 'training')) {
            return Response::view('layout.main', [
                'title' => 'Formations',
                'content' => 'platform.upgrade',
                'feature' => 'training',
                'planName' => 'standard',
            ]);
        }
        $charterBlock = $this->responseIfHrCharterBlocking($request, $tenantId, $userId);
        if ($charterBlock !== null) {
            return $charterBlock;
        }
        $slug = trim(rawurldecode((string) ($params['slug'] ?? '')));
        if ($slug === '') {
            return Response::redirect(url('formations'));
        }
        $row = $this->formationCustomPageRepository->findPublishedBySlug($tenantId, $slug);
        if ($row === null || !$this->canViewFormationCustomPage($row, $tenantId, $userId)) {
            return Response::view('training.formation_introuvable', [
                'slug' => $slug,
                'context' => 'documentation',
            ])->setStatusCode(404);
        }
        $this->formationCustomPageRepository->incrementView((int) $row['id'], $tenantId, $userId);
        $base = rtrim(url(''), '/');
        $pdfUrl = url('formations/page/' . rawurlencode($slug) . '/pdf');
        $full = TrainingFormationCustomPageRenderer::render($row, $base, null, $pdfUrl);

        return (new Response())
            ->header('Content-Type', 'text/html; charset=utf-8')
            ->setBody($full);
    }

    public function formationCustomPageExportPdf(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        $userId = Session::get('user_id');
        if (!$tenantId || !$userId) {
            return Response::redirect(url('login'));
        }
        $tenantId = (int) $tenantId;
        $userId = (int) $userId;
        if (!$this->featureGate->allows($tenantId, 'training')) {
            return Response::redirect(url('formations'));
        }
        $slug = trim(rawurldecode((string) ($params['slug'] ?? '')));
        $row = $slug !== '' ? $this->formationCustomPageRepository->findPublishedBySlug($tenantId, $slug) : null;
        if ($row === null || !$this->canViewFormationCustomPage($row, $tenantId, $userId)) {
            return (new Response())->setStatusCode(404)->setBody('Documentation introuvable.');
        }
        $binary = $this->formationCustomPagePdfExport->generateBinary($row);
        if ($binary === null) {
            Session::flash('error', $this->formationCustomPagePdfExport->getLastFailureReason() ?? 'Impossible de générer le PDF.');

            return Response::redirect(url('formations/page/' . rawurlencode($slug)));
        }
        $filename = 'documentation-' . $slug . '.pdf';

        return (new Response())
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($binary);
    }

    /**
     * Applique visibility_level / allowed_roles_json d'une Documentation HTML.
     * Le staff LMS (TrainingLmsStaffAccess) voit toujours tout, comme dans le studio d'édition.
     *
     * @param array<string,mixed> $row
     */
    private function canViewFormationCustomPage(array $row, int $tenantId, int $userId): bool
    {
        $level = (string) ($row['visibility_level'] ?? 'tenant');
        if ($level === 'tenant' || $level === 'internal_link' || $level === '') {
            return true;
        }
        if (TrainingLmsStaffAccess::allows(Gate::getInstance())) {
            return true;
        }
        if ($level === 'staff_private') {
            return false;
        }
        if ($level === 'role_based') {
            $allowed = $this->allowedRoleTokensFromJson((string) ($row['allowed_roles_json'] ?? ''));
            if ($allowed === []) {
                // Niveau restreint choisi mais aucun rôle configuré : on ferme l'accès plutôt que
                // de dégrader silencieusement vers un accès « tenant » non voulu par l'auteur.
                return false;
            }
            $userRoles = $this->roleRepository->listOrganizationRolesForUsers($tenantId, [$userId])[$userId] ?? [];
            foreach ($userRoles as $role) {
                $slug = strtolower(trim((string) ($role['slug'] ?? '')));
                $name = strtolower(trim((string) ($role['name'] ?? '')));
                if (($slug !== '' && in_array($slug, $allowed, true)) || ($name !== '' && in_array($name, $allowed, true))) {
                    return true;
                }
            }

            return false;
        }

        return true;
    }

    /** @return list<string> tokens en minuscule (slug ou nom de rôle attendu) */
    private function allowedRoleTokensFromJson(string $json): array
    {
        $json = trim($json);
        if ($json === '') {
            return [];
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }
        $out = [];
        foreach ($decoded as $v) {
            $t = strtolower(trim((string) $v));
            if ($t !== '') {
                $out[] = $t;
            }
        }

        return $out;
    }

    /** Lecture d'une leçon. */
    public function lesson(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        $userId = Session::get('user_id');
        if (!$tenantId || !$userId) {
            return Response::redirect(url('login'));
        }
        $tenantId = (int) $tenantId;
        $userId = (int) $userId;
        $charterBlock = $this->responseIfHrCharterBlocking($request, $tenantId, $userId);
        if ($charterBlock !== null) {
            return $charterBlock;
        }
        $lessonId = (int) ($params['id'] ?? 0);
        $enrollmentId = (int) ($request->query('enrollment_id') ?? 0);
        if (!$lessonId || !$enrollmentId) {
            return (new Response())->setStatusCode(404)->setBody('Paramètres manquants.');
        }
        $enrollment = $this->enrollmentRepository->findById($enrollmentId, $tenantId);
        if (!$enrollment || (int) $enrollment['user_id'] !== $userId) {
            return (new Response())->setStatusCode(403)->setBody('Accès refusé.');
        }
        $courseId = (int) $enrollment['course_id'];
        if (!$this->trainingService->canAccessCourse($userId, $courseId, $tenantId)) {
            $slug = trim((string) ($enrollment['course_slug'] ?? ''));
            Session::flash('error', 'Votre accès à cette formation n’est pas encore actif ou a été retiré.');

            return Response::redirect($slug !== '' ? url('formations/' . rawurlencode($slug)) : url('formations/mes-formations'));
        }
        $lesson = $this->lessonRepository->findById($lessonId);
        if (!$lesson) {
            return (new Response())->setStatusCode(404)->setBody('Leçon non trouvée.');
        }
        $resources = $this->resourceRepository->listByLessonId($lessonId);
        $progress = $this->progressService->getProgressForEnrollment($enrollmentId);
        $course = $this->trainingService->getCourseWithStructure($courseId, $tenantId);
        $currentModule = null;
        foreach ($course['modules'] ?? [] as $mod) {
            foreach ($mod['lessons'] ?? [] as $l) {
                if ((int) ($l['id'] ?? 0) === $lessonId) {
                    $currentModule = $mod;
                    break 2;
                }
            }
        }

        $orderedLessons = function_exists('training_lms_ordered_lessons') ? training_lms_ordered_lessons($course) : [];
        $prevLesson = null;
        $nextLesson = null;
        $lessonStep = null;
        $idx = null;
        foreach ($orderedLessons as $i => $l) {
            if ((int) ($l['id'] ?? 0) === $lessonId) {
                $idx = $i;
                break;
            }
        }
        if ($idx !== null) {
            if ($idx > 0) {
                $prevLesson = $orderedLessons[$idx - 1];
            }
            if ($idx < count($orderedLessons) - 1) {
                $nextLesson = $orderedLessons[$idx + 1];
            }
            $lessonStep = ['current' => $idx + 1, 'total' => count($orderedLessons)];
        }

        $footerNext = null;
        if (function_exists('training_lms_footer_next_step')) {
            $footerNext = training_lms_footer_next_step(
                $course,
                $lessonId,
                fn (int $qid): bool => $this->trainingQuizHasPassingAttempt($enrollmentId, $qid)
            );
        }
        if ($footerNext === null) {
            $footerNext = $nextLesson !== null
                ? ['kind' => 'lesson', 'lesson' => $nextLesson]
                : ['kind' => 'echanges'];
        }

        $moduleLessonStep = null;
        if ($currentModule !== null) {
            $modLessons = $currentModule['lessons'] ?? [];
            if (is_array($modLessons) && $modLessons !== []) {
                $totalInModule = count($modLessons);
                foreach ($modLessons as $mi => $l) {
                    if ((int) ($l['id'] ?? 0) === $lessonId) {
                        $moduleLessonStep = ['current' => (int) $mi + 1, 'total' => $totalInModule];
                        break;
                    }
                }
            }
        }

        $lessonFeedback = $this->lessonFeedbackRepository->findForEnrollmentLessonUser($enrollmentId, $lessonId, $userId);
        $eventRecommendation = null;
        if ($currentModule !== null && (int) ($currentModule['is_required'] ?? 0) === 1) {
            $lessonDone = [];
            foreach (($progress['progress'] ?? []) as $row) {
                if ((string) ($row['status'] ?? '') === 'completed') {
                    $lessonDone[(int) ($row['lesson_id'] ?? 0)] = true;
                }
            }
            $moduleComplete = true;
            foreach (($currentModule['lessons'] ?? []) as $ml) {
                $mlid = (int) ($ml['id'] ?? 0);
                if ($mlid < 1 || !isset($lessonDone[$mlid])) {
                    $moduleComplete = false;
                    break;
                }
            }
            if ($moduleComplete) {
                $sessions = $this->lmsSocialRepository->listSessionsForCourse($courseId);
                $now = time();
                foreach ($sessions as $session) {
                    $startsAt = strtotime((string) ($session['starts_at'] ?? ''));
                    if ($startsAt === false || $startsAt < $now) {
                        continue;
                    }
                    $eventRecommendation = $session;
                    break;
                }
            }
        }

        [$lmsCompletedLessonIds, $lmsPassedQuizIds] = $this->trainingLmsProgressMaps($enrollmentId, $course);
        $cpFooter = $this->progressService->computeCourseProgress($enrollmentId);
        $courseCompletedForFooter = !empty($cpFooter['completed'])
            || (string) ($enrollment['status'] ?? '') === 'completed';
        $certificateUrlForFooter = '';
        if ($courseCompletedForFooter) {
            $certFooter = $this->certificateService->getByEnrollment($enrollmentId);
            if ($certFooter && (int) ($certFooter['id'] ?? 0) > 0) {
                $certificateUrlForFooter = url('formations/certificate/' . (int) $certFooter['id']);
            } elseif ((int) ($course['is_certifying'] ?? 0) === 1) {
                try {
                    $issuedFooter = $this->certificateService->issueCertificate($enrollmentId, $tenantId, $userId);
                    if ($issuedFooter && (int) ($issuedFooter['id'] ?? 0) > 0) {
                        $certificateUrlForFooter = url('formations/certificate/' . (int) $issuedFooter['id']);
                    }
                } catch (\Throwable) {
                }
            }
        }

        return Response::view('training.lesson', [
            'title' => $lesson['title'],
            'lesson' => $lesson,
            'enrollment' => $enrollment,
            'resources' => $resources,
            'progress' => $progress,
            'course' => $course,
            'currentLessonId' => $lessonId,
            'currentModule' => $currentModule,
            'prevLesson' => $prevLesson,
            'nextLesson' => $nextLesson,
            'footerNext' => $footerNext,
            'lessonStep' => $lessonStep,
            'moduleLessonStep' => $moduleLessonStep,
            'lessonFeedback' => $lessonFeedback,
            'eventRecommendation' => $eventRecommendation,
            'lmsPassedQuizIds' => $lmsPassedQuizIds,
            'courseCompletedForFooter' => $courseCompletedForFooter,
            'certificateUrlForFooter' => $certificateUrlForFooter,
        ]);
    }

    /** Synthèse de fin de module : leçons, évaluations, attestation si parcours terminé. */
    public function moduleBilan(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        $userId = Session::get('user_id');
        if (!$tenantId || !$userId) {
            return Response::redirect(url('login'));
        }
        $tenantId = (int) $tenantId;
        $userId = (int) $userId;
        if (!$this->featureGate->allows($tenantId, 'training')) {
            return Response::redirect(url('formations'));
        }
        $charterBlock = $this->responseIfHrCharterBlocking($request, $tenantId, $userId);
        if ($charterBlock !== null) {
            return $charterBlock;
        }
        $enrollmentId = (int) $request->query('enrollment_id', 0);
        $moduleId = (int) $request->query('module_id', 0);
        if ($enrollmentId < 1 || $moduleId < 1) {
            Session::flash('error', 'Paramètres manquants pour afficher la synthèse du module.');

            return Response::redirect(url('formations/mes-formations'));
        }
        $enrollment = $this->enrollmentRepository->findById($enrollmentId, $tenantId);
        if (!$enrollment || (int) $enrollment['user_id'] !== $userId) {
            return (new Response())->setStatusCode(403)->setBody('Accès refusé.');
        }
        $courseId = (int) $enrollment['course_id'];
        if (!$this->trainingService->canAccessCourse($userId, $courseId, $tenantId)) {
            Session::flash('error', 'Votre accès à cette formation n’est pas actif.');

            return Response::redirect(url('formations/mes-formations'));
        }
        $bilan = $this->progressService->getModuleBilan($enrollmentId, $moduleId, $tenantId, $userId);
        if ($bilan === null) {
            return (new Response())->setStatusCode(404)->setBody('Module introuvable pour cette formation.');
        }
        $course = $this->trainingService->getCourseWithStructure($courseId, $tenantId);
        $certificate = null;
        $cp = $this->progressService->computeCourseProgress($enrollmentId);
        if (!empty($cp['completed']) && ($enrollment['status'] ?? '') === 'completed') {
            $certificate = $this->certificateService->getByEnrollment($enrollmentId);
            $courseRow = $this->trainingCourseRepository->findById($courseId, $tenantId);
            if (!$certificate && $courseRow && (int) ($courseRow['is_certifying'] ?? 0) === 1) {
                $certificate = $this->certificateService->issueCertificate($enrollmentId, $tenantId, $userId);
            }
        }
        $waitSec = $this->trainingStaffAlertService->secondsBeforeNextModuleNotify($enrollmentId, $moduleId);
        $canNotifyStaff = $waitSec === null;

        return Response::view('training.module_bilan', [
            'title' => 'Synthèse du module',
            'course' => $course,
            'enrollment' => $enrollment,
            'bilan' => $bilan,
            'certificate' => $certificate,
            'canNotifyStaff' => $canNotifyStaff,
            'notifyCooldownHours' => $waitSec !== null ? max(1, (int) ceil($waitSec / 3600)) : null,
        ]);
    }

    public function moduleBilanNotifyStaff(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        $userId = Session::get('user_id');
        if (!$tenantId || !$userId) {
            return Response::redirect(url('login'));
        }
        $tenantId = (int) $tenantId;
        $userId = (int) $userId;
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée, réessayez.');

            return Response::redirect(url('formations/mes-formations'));
        }
        $charterBlock = $this->responseIfHrCharterBlocking($request, $tenantId, $userId);
        if ($charterBlock !== null) {
            return $charterBlock;
        }
        $enrollmentId = (int) $request->input('enrollment_id', 0);
        $moduleId = (int) $request->input('module_id', 0);
        if ($enrollmentId < 1 || $moduleId < 1) {
            Session::flash('error', 'Données invalides.');

            return Response::redirect(url('formations/mes-formations'));
        }
        $enrollment = $this->enrollmentRepository->findById($enrollmentId, $tenantId);
        if (!$enrollment || (int) $enrollment['user_id'] !== $userId) {
            return Response::redirect(url('formations/mes-formations'));
        }
        $courseId = (int) $enrollment['course_id'];
        $courseRow = $this->trainingCourseRepository->findById($courseId, $tenantId);
        if (!$courseRow) {
            Session::flash('error', 'Formation introuvable.');

            return Response::redirect(url('formations/mes-formations'));
        }
        $bilan = $this->progressService->getModuleBilan($enrollmentId, $moduleId, $tenantId, $userId);
        if ($bilan === null) {
            Session::flash('error', 'Module introuvable.');

            return Response::redirect(url('formations/mes-formations'));
        }
        if (!empty($bilan['module_validated'])) {
            Session::flash('success', 'Ce module est déjà entièrement validé — aucune alerte nécessaire.');

            return Response::redirect(url('formations/bilan-module?enrollment_id=' . $enrollmentId . '&module_id=' . $moduleId));
        }
        $moduleTitle = (string) ($bilan['module']['title'] ?? 'Module');
        $sent = $this->trainingStaffAlertService->notifyModuleBlockedByLearner(
            $tenantId,
            $userId,
            $courseRow,
            $moduleId,
            $moduleTitle,
            $enrollmentId,
            $bilan['gaps']
        );
        if ($sent) {
            Session::flash('success', 'Votre demande a été transmise au personnel pédagogique. Vous recevrez une réponse par les canaux habituels de la communauté.');
        } else {
            Session::flash('error', 'Une alerte a déjà été envoyée récemment pour ce module. Réessayez plus tard si la situation n’a pas évolué.');
        }

        return Response::redirect(url('formations/bilan-module?enrollment_id=' . $enrollmentId . '&module_id=' . $moduleId));
    }

    /** Démarre une tentative quiz (formulaire web) et redirige vers la page de passage. */
    public function startQuiz(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        $userId = Session::get('user_id');
        if (!$tenantId || !$userId) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée, réessayez.');

            return Response::redirect(url('formations'));
        }
        $tenantId = (int) $tenantId;
        $userId = (int) $userId;
        if (!$this->featureGate->allows($tenantId, 'training')) {
            return Response::redirect(url('formations'));
        }
        $charterBlock = $this->responseIfHrCharterBlocking($request, $tenantId, $userId);
        if ($charterBlock !== null) {
            return $charterBlock;
        }
        $quizId = (int) $request->input('quiz_id', 0);
        $enrollmentId = (int) $request->input('enrollment_id', 0);
        if (!$quizId || !$enrollmentId) {
            Session::flash('error', 'Paramètres quiz invalides.');

            return Response::redirect(url('formations'));
        }
        try {
            $attempt = $this->quizService->startAttempt($quizId, $enrollmentId, $tenantId, $userId);
            $aid = (int) ($attempt['id'] ?? 0);
            if ($aid <= 0) {
                throw new \RuntimeException('Tentative invalide.');
            }

            return Response::redirect(url('formations/quiz/' . $aid));
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());

            return Response::redirect(url('formations'));
        }
    }

    /** Page quiz (tentative). */
    public function quiz(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        $userId = Session::get('user_id');
        if (!$tenantId || !$userId) {
            return Response::redirect(url('login'));
        }
        $tenantId = (int) $tenantId;
        $userId = (int) $userId;
        $charterBlock = $this->responseIfHrCharterBlocking($request, $tenantId, $userId);
        if ($charterBlock !== null) {
            return $charterBlock;
        }
        $attemptId = (int) ($params['id'] ?? 0);
        if ($attemptId < 1) {
            return Response::view('training.quiz_not_found', [])->setStatusCode(404);
        }
        $this->quizService->markAttemptExpiredIfTimeElapsed($attemptId);
        $attempt = $this->quizService->getAttempt($attemptId, $tenantId, $userId);
        if (!$attempt) {
            return Response::view('training.quiz_not_found', [])->setStatusCode(404);
        }
        $enrollment = $this->enrollmentRepository->findById((int) $attempt['enrollment_id'], $tenantId);
        $courseId = $enrollment ? (int) $enrollment['course_id'] : 0;
        $course = $courseId > 0 ? $this->trainingService->getCourseWithStructure($courseId, $tenantId) : null;
        if (!$course || !$enrollment) {
            return Response::view('training.quiz_not_found', [])->setStatusCode(404);
        }

        $enrollmentId = (int) $enrollment['id'];
        $quizId = (int) ($attempt['quiz_id'] ?? 0);
        // Tentative ouverte alors qu’une réussite existe déjà → basculer sur la tentative réussie.
        if (($attempt['status'] ?? '') === 'in_progress' && $quizId > 0) {
            $passed = $this->quizService->findPassingAttempt($enrollmentId, $quizId);
            if ($passed !== null) {
                $passedId = (int) ($passed['id'] ?? 0);
                if ($passedId > 0 && $passedId !== $attemptId) {
                    $this->quizService->markAttemptExpiredIfTimeElapsed($attemptId);
                    $this->trainingQuizRepository->updateAttempt($attemptId, ['status' => 'expired']);

                    return Response::redirect(url('formations/quiz/' . $passedId));
                }
            }
        }

        $progressPercent = $this->trainingService->getGlobalProgress($enrollmentId);
        $quizMeta = $attempt['quiz'] ?? [];
        $quizTitle = trim((string) ($quizMeta['title'] ?? '')) ?: 'Quiz';
        [$lmsCompletedLessonIds, $lmsPassedQuizIds] = $this->trainingLmsProgressMaps($enrollmentId, $course);

        $courseSlug = trim((string) ($course['slug'] ?? $enrollment['course_slug'] ?? ''));
        $courseUrl = $courseSlug !== '' ? url('formations/' . rawurlencode($courseSlug)) : url('formations/mes-formations');
        $echangesUrl = $courseSlug !== '' ? url('formations/' . rawurlencode($courseSlug) . '/echanges') : '';
        $certificate = $this->certificateService->getByEnrollment($enrollmentId);
        $courseDone = (string) ($enrollment['status'] ?? '') === 'completed'
            || !empty($this->progressService->computeCourseProgress($enrollmentId)['completed']);
        $certificateUrl = ($certificate && (int) ($certificate['id'] ?? 0) > 0)
            ? url('formations/certificate/' . (int) $certificate['id'])
            : '';

        return Response::view('training.quiz', [
            'title' => $quizTitle,
            'attemptId' => $attemptId,
            'course' => $course,
            'enrollment' => $enrollment,
            'progressPercent' => $progressPercent,
            'quizTitle' => $quizTitle,
            'quizId' => (int) ($quizMeta['id'] ?? $quizId),
            'timeLimitMinutes' => $quizMeta['time_limit_minutes'] ?? null,
            'passingScore' => $quizMeta['passing_score'] ?? null,
            'lmsCompletedLessonIds' => $lmsCompletedLessonIds,
            'lmsPassedQuizIds' => $lmsPassedQuizIds,
            'quizCourseUrl' => $courseUrl,
            'quizEchangesUrl' => $echangesUrl,
            'quizCertificateUrl' => $certificateUrl,
            'quizCourseCompleted' => $courseDone,
            'quizIsCertifying' => (int) ($course['is_certifying'] ?? 0) === 1,
        ]);
    }

    /** Page certificat. */
    public function certificate(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        $userId = Session::get('user_id');
        if (!$tenantId || !$userId) {
            return Response::redirect(url('login'));
        }
        $charterBlock = $this->responseIfHrCharterBlocking($request, (int) $tenantId, (int) $userId);
        if ($charterBlock !== null) {
            return $charterBlock;
        }
        $id = (int) ($params['id'] ?? 0);
        $cert = $this->certificateService->getById($id, (int) $tenantId);
        if (!$cert || (int) $cert['user_id'] !== (int) $userId) {
            return (new Response())->setStatusCode(404)->setBody('Certificat non trouvé.');
        }
        // Filet de sécurité : si le document n’a pas encore été produit (ex. panne temporaire
        // au moment de l’émission), on retente ici pour que le membre n’ait pas besoin d’attendre
        // une action du staff.
        if ((string) ($cert['status'] ?? '') === 'valid') {
            $rel = trim((string) ($cert['pdf_path'] ?? ''));
            if ($rel === '' || !is_file(base_path($rel))) {
                $healed = $this->certificateService->ensurePdfForCertificate($id, (int) $tenantId);
                if ($healed) {
                    $cert = $healed;
                }
            }
        }

        return Response::view('training.certificate', [
            'title' => 'Attestation',
            'certificate' => $cert,
            'publicConsultationView' => false,
            'consultationApiUrl' => url('api/training/certificates/' . $id . '/consultation-lien'),
        ]);
    }

    /** Consultation publique signée (sans compte), pour partage contrôlé. */
    public function certificatePublic(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $token = (string) $request->query('t', '');
        $exp = (int) $request->query('e', 0);
        if (!$this->certificateShareService->verify($id, $token, $exp)) {
            return (new Response())->setStatusCode(403)->setBody('Ce lien de consultation n’est plus valide ou a expiré.');
        }
        $cert = $this->certificateService->getById($id, null);
        $statusRaw = (string) ($cert['status'] ?? 'valid');
        if (!$cert || $statusRaw !== 'valid') {
            return (new Response())->setStatusCode(404)->setBody('Document introuvable.');
        }
        $canonical = $this->certificateShareService->buildConsultationUrl($id, $token, $exp);
        $courseTitle = (string) ($cert['course_title'] ?? 'Formation');
        $sharePersonnel = $this->attestationPublicPersonnelPresentation($cert);
        $honorName = trim((string) ($sharePersonnel['operator_display_name'] ?? ''));
        $ogTitle = $honorName !== ''
            ? ('Attestation — ' . $honorName . ' — ' . $courseTitle)
            : ('Attestation — ' . $courseTitle);
        $issued = !empty($cert['issued_at']) ? date('d/m/Y', strtotime((string) $cert['issued_at'])) : '';
        $ogDesc = $issued !== '' ? ('Délivrée le ' . $issued . ' — référence ' . (string) ($cert['certificate_number'] ?? '') . '.') : 'Attestation de formation.';
        if ($honorName !== '') {
            $ogDesc = $honorName . ' a validé le parcours « ' . $courseTitle . ' ». ' . $ogDesc;
        }

        return Response::view('training.certificate', [
            'title' => 'Attestation',
            'certificate' => $cert,
            'publicConsultationView' => true,
            'og_url' => $canonical,
            'og_title' => $ogTitle,
            'og_description' => $ogDesc,
            'attestationSharePersonnel' => $sharePersonnel,
        ]);
    }

    /** Legacy: détail module (legacy_training_modules par slug). */
    public function show(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $legacyUid = Session::get('user_id');
        $charterBlock = $this->responseIfHrCharterBlocking($request, (int) $tenantId, $legacyUid ? (int) $legacyUid : null);
        if ($charterBlock !== null) {
            return $charterBlock;
        }
        $slug = $params['slug'] ?? '';
        $module = $this->trainingRepository->findBySlug($slug, (int) $tenantId);
        if (!$module) {
            return (new Response())->setStatusCode(404)->setBody('Module non trouvé.');
        }
        $linkedDocumentIds = $this->documentLinkRepository->getDocumentIdsForEntity('training', (int) $module['id']);
        $linkedDocuments = $this->documentRepository->findPublishedByIds($linkedDocumentIds, (int) $tenantId);
        return Response::view('training.show', [
            'title' => $module['title'],
            'module' => $module,
            'linkedDocuments' => $linkedDocuments,
        ]);
    }
}
