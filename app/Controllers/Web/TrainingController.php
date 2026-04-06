<?php

declare(strict_types=1);

namespace App\Controllers\Web;

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
use App\Services\Platform\FeatureGateService;
use App\Services\Training\TrainingCertificateShareService;
use App\Core\Csrf;

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
    ) {}

    private function trainingQuizHasPassingAttempt(int $enrollmentId, int $quizId): bool
    {
        $attempts = $this->trainingQuizRepository->listAttemptsByEnrollmentAndQuiz($enrollmentId, $quizId);
        foreach ($attempts as $a) {
            if ((int) ($a['passed'] ?? 0) === 1) {
                return true;
            }
        }

        return false;
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
        $category = $request->query('category');
        $search = $request->query('search');
        $courses = $this->trainingService->getCatalogue($tenantId, $userId ? (int) $userId : null, $category, $search);
        $coursesForCategories = $this->trainingService->getCatalogue($tenantId, $userId ? (int) $userId : null, null, null);
        $legacyEnabled = training_legacy_enabled();
        $legacyModules = $legacyEnabled ? $this->trainingRepository->listPublishedForTenant($tenantId) : [];
        $categories = array_values(array_unique(array_filter(array_map(static function ($c) {
            return isset($c['category']) && (string) $c['category'] !== '' ? (string) $c['category'] : null;
        }, $coursesForCategories))));
        sort($categories, SORT_NATURAL | SORT_FLAG_CASE);

        return Response::view('training.catalogue', [
            'title' => 'Formations',
            'courses' => $courses,
            'legacyModules' => $legacyModules,
            'training_legacy_enabled' => $legacyEnabled,
            'filterCategory' => $category !== null && $category !== '' ? (string) $category : null,
            'filterSearch' => $search !== null && $search !== '' ? (string) $search : null,
            'filterCategories' => $categories,
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
                    'revoked' => 4,
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
                if (empty($x['expires_at']) || in_array($x['status'] ?? '', ['completed', 'revoked'], true)) {
                    return false;
                }
                $t = strtotime((string) $x['expires_at']);

                return $t !== false && $t <= strtotime('+30 days');
            })),
        ];

        $filter = (string) $request->query('filter', 'all');
        $displayed = $withProgress;
        if ($filter === 'active') {
            $displayed = array_values(array_filter($withProgress, static fn (array $x): bool => in_array($x['status'] ?? '', ['assigned', 'in_progress'], true)));
        } elseif ($filter === 'done') {
            $displayed = array_values(array_filter($withProgress, static fn (array $x): bool => ($x['status'] ?? '') === 'completed'));
        }

        return Response::view('layout.main', [
            'title' => 'Mes formations',
            'content' => 'training.my-training',
            'enrollments' => $displayed,
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
        if ($enrollment && in_array((string) ($enrollment['status'] ?? ''), ['revoked', 'expired'], true)) {
            $enrollment = null;
        }
        $canAccessLearning = $userId && $enrollment
            ? $this->trainingService->canAccessCourse((int) $userId, $courseId, $tenantId)
            : false;
        $progressPercent = ($enrollment && $canAccessLearning) ? $this->trainingService->getGlobalProgress((int) $enrollment['id']) : 0;
        $certificate = ($enrollment && $canAccessLearning) ? $this->certificateService->getByEnrollment((int) $enrollment['id']) : null;
        $policyEval = $userId
            ? $this->enrollmentPolicyService->evaluateSelfEnroll((int) $userId, $tenantId, $course)
            : ['allowed' => false, 'messages' => []];
        $policyDisplay = $this->enrollmentPolicyService->getPublicPolicyDisplay($tenantId, $course, $userId ? (int) $userId : null);
        $policyDecoded = $this->enrollmentPolicyService->decodePolicy($course['enrollment_policy_json'] ?? null);
        $lmsCommentsEnabled = function_exists('training_lms_policy_comments_enabled')
            ? \training_lms_policy_comments_enabled($policyDecoded)
            : true;
        $isFavorite = $userId ? $this->lmsSocialRepository->isFavorite((int) $userId, $courseId) : false;
        $sessions = $this->lmsSocialRepository->listSessionsForCourse($courseId);

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

        return Response::view('training.course', [
            'title' => $course['title'],
            'course' => $course,
            'enrollment' => $enrollment,
            'progressPercent' => $progressPercent,
            'certificate' => $certificate,
            'policyEval' => $policyEval,
            'policyDisplay' => $policyDisplay,
            'isFavorite' => $isFavorite,
            'courseSessions' => $sessions,
            'viewerLoggedIn' => (bool) $userId,
            'continueLesson' => $continueLesson,
            'firstLesson' => $firstLesson,
            'lessonDone' => $lessonDone,
            'canAccessLearning' => $canAccessLearning,
            'lmsCommentsEnabled' => $lmsCommentsEnabled,
        ]);
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
        if ($enrollment && in_array((string) ($enrollment['status'] ?? ''), ['revoked', 'expired'], true)) {
            $enrollment = null;
        }
        $canAccessLearning = $userId && $enrollment
            ? $this->trainingService->canAccessCourse((int) $userId, $courseId, $tenantId)
            : false;
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
            return $this->show($request, $params);
        }

        return Response::view('training.formation_introuvable', [
            'slug' => (string) $slug,
            'context' => 'fiche',
        ])->setStatusCode(404);
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
        $attemptId = (int) ($params['id'] ?? 0);
        if ($attemptId < 1) {
            return Response::view('training.quiz_not_found', [])->setStatusCode(404);
        }
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
        $progressPercent = $this->trainingService->getGlobalProgress((int) $enrollment['id']);
        $quizMeta = $attempt['quiz'] ?? [];
        $quizTitle = trim((string) ($quizMeta['title'] ?? '')) ?: 'Quiz';

        return Response::view('training.quiz', [
            'title' => $quizTitle,
            'attemptId' => $attemptId,
            'course' => $course,
            'enrollment' => $enrollment,
            'progressPercent' => $progressPercent,
            'quizTitle' => $quizTitle,
            'timeLimitMinutes' => $quizMeta['time_limit_minutes'] ?? null,
            'passingScore' => $quizMeta['passing_score'] ?? null,
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
        $id = (int) ($params['id'] ?? 0);
        $cert = $this->certificateService->getById($id, (int) $tenantId);
        if (!$cert || (int) $cert['user_id'] !== (int) $userId) {
            return (new Response())->setStatusCode(404)->setBody('Certificat non trouvé.');
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
        if (!$cert || ($cert['status'] ?? '') !== 'valid') {
            return (new Response())->setStatusCode(404)->setBody('Document introuvable.');
        }
        $canonical = $this->certificateShareService->buildConsultationUrl($id, $token, $exp);
        $courseTitle = (string) ($cert['course_title'] ?? 'Formation');
        $ogTitle = 'Attestation — ' . $courseTitle;
        $issued = !empty($cert['issued_at']) ? date('d/m/Y', strtotime((string) $cert['issued_at'])) : '';
        $ogDesc = $issued !== '' ? ('Délivrée le ' . $issued . ' — référence ' . (string) ($cert['certificate_number'] ?? '') . '.') : 'Attestation de formation.';

        return Response::view('training.certificate', [
            'title' => 'Attestation',
            'certificate' => $cert,
            'publicConsultationView' => true,
            'og_url' => $canonical,
            'og_title' => $ogTitle,
            'og_description' => $ogDesc,
        ]);
    }

    /** Legacy: détail module (legacy_training_modules par slug). */
    public function show(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
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
