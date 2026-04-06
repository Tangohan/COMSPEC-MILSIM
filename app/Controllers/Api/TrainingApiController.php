<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Training\TrainingService;
use App\Services\Training\TrainingProgressService;
use App\Services\Training\TrainingQuizService;
use App\Services\Training\TrainingCertificateService;
use App\Services\Training\TrainingCertificateShareService;
use App\Services\Training\TrainingAssignmentService;
use App\Repositories\TrainingEnrollmentRepository;
use App\Repositories\TrainingLessonRepository;
use App\Repositories\TrainingModuleRepository;
use App\Repositories\TrainingQuizRepository;
use App\Repositories\TrainingResourceRepository;
use App\Repositories\TrainingCourseRepository;
use App\Repositories\DocumentRepository;
use App\Repositories\ModerationArtifactRepository;
use App\Services\Documents\DocumentAccessService;
use App\Services\Audit\AuditService;
use App\Services\Moderation\ModerationArtifactState;
use App\Services\Platform\FeatureGateService;
use App\Core\Gate;

class TrainingApiController
{
    /** Corps JSON déjà lu (php://input n’est lisible qu’une fois par requête). */
    private ?array $cachedJsonBody = null;

    public function __construct(
        private TrainingService $trainingService,
        private TrainingProgressService $progressService,
        private TrainingQuizService $quizService,
        private TrainingCertificateService $certificateService,
        private TrainingAssignmentService $assignmentService,
        private TrainingEnrollmentRepository $enrollmentRepository,
        private TrainingQuizRepository $quizRepository,
        private TrainingCourseRepository $courseRepository,
        private FeatureGateService $featureGate,
        private TrainingResourceRepository $resourceRepository,
        private TrainingLessonRepository $lessonRepository,
        private TrainingModuleRepository $moduleRepository,
        private TrainingCertificateShareService $certificateShareService,
        private DocumentRepository $documentRepository,
        private DocumentAccessService $documentAccessService,
        private ModerationArtifactRepository $moderationArtifactRepository,
        private AuditService $auditService,
    ) {}

    private function tenantId(): int
    {
        $id = Session::get('tenant_id');
        if (!$id) {
            throw new \RuntimeException('Unauthorized', 401);
        }
        return (int) $id;
    }

    private function userId(): int
    {
        $id = Session::get('user_id');
        if (!$id) {
            throw new \RuntimeException('Unauthorized', 401);
        }
        return (int) $id;
    }

    private function requestContentType(): string
    {
        return strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? ''));
    }

    /** Le client attend une réponse JSON (corps JSON ou en-tête Accept). */
    private function clientExpectsJsonResponse(): bool
    {
        if (str_contains($this->requestContentType(), 'application/json')) {
            return true;
        }
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));

        return str_contains($accept, 'application/json');
    }

    private function body(Request $request): array
    {
        $contentType = $this->requestContentType();
        if (str_contains($contentType, 'application/json')) {
            if ($this->cachedJsonBody !== null) {
                return $this->cachedJsonBody;
            }
            $raw = file_get_contents('php://input');
            $decoded = json_decode($raw, true);
            $this->cachedJsonBody = is_array($decoded) ? $decoded : [];

            return $this->cachedJsonBody;
        }

        if ($this->cachedJsonBody !== null) {
            return $this->cachedJsonBody;
        }

        $merged = array_merge($request->all(), $_POST);
        if ($merged === [] && strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) === 'POST') {
            $raw = file_get_contents('php://input');
            $trim = ltrim((string) $raw);
            if ($trim !== '' && ($trim[0] === '{' || $trim[0] === '[')) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $this->cachedJsonBody = $decoded;

                    return $this->cachedJsonBody;
                }
            }
        }

        return $merged;
    }

    private function validateCsrf(Request $request): void
    {
        $body = $this->body($request);
        $token = $request->input('_csrf_token') ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($body['_csrf_token'] ?? null);
        if (!$token || !Csrf::validate($token)) {
            throw new \RuntimeException('Invalid CSRF token', 403);
        }
    }

    /** Alignement avec le web : pas d’accès API aux formations si le plan ne l’autorise pas. */
    /**
     * @param list<array<string, mixed>> $questions
     * @return list<array<string, mixed>>
     */
    private function sanitizeQuizQuestionsForLearner(array $questions): array
    {
        $out = [];
        foreach ($questions as $q) {
            $row = $q;
            if (isset($row['explanation'])) {
                unset($row['explanation']);
            }
            if (isset($row['answers']) && is_array($row['answers'])) {
                $ans = [];
                foreach ($row['answers'] as $a) {
                    if (!is_array($a)) {
                        continue;
                    }
                    $a2 = $a;
                    unset($a2['is_correct']);
                    $ans[] = $a2;
                }
                $qType = (string) ($row['question_type'] ?? 'single_choice');
                if (in_array($qType, ['single_choice', 'true_false', 'multiple_choice'], true)) {
                    shuffle($ans);
                }
                $row['answers'] = $ans;
            }
            $out[] = $row;
        }

        return $out;
    }

    private function assertTrainingAllowed(): ?Response
    {
        try {
            $tenantId = $this->tenantId();
        } catch (\RuntimeException) {
            return Response::json(['error' => 'Non autorisé.'], 401);
        }
        if (! $this->featureGate->allows($tenantId, 'training')) {
            return Response::json(['error' => 'Cette fonctionnalité n’est pas disponible sur votre plan.'], 403);
        }

        return null;
    }

    public function catalogue(Request $request, array $params = []): Response
    {
        $blocked = $this->assertTrainingAllowed();
        if ($blocked !== null) {
            return $blocked;
        }
        $tenantId = $this->tenantId();
        $userId = Session::get('user_id') ? (int) Session::get('user_id') : null;
        $category = $request->query('category');
        $search = $request->query('search');
        $courses = $this->trainingService->getCatalogue($tenantId, $userId, $category, $search);
        return Response::json(['courses' => $courses]);
    }

    public function courseDetail(Request $request, array $params = []): Response
    {
        $blocked = $this->assertTrainingAllowed();
        if ($blocked !== null) {
            return $blocked;
        }
        $tenantId = $this->tenantId();
        $userId = $this->userId();
        $id = $params['id'] ?? '';
        $course = $this->trainingService->getCourseBySlugOrId($tenantId, $id);
        if (!$course) {
            return Response::json(['error' => 'Formation non trouvée.'], 404);
        }
        $courseId = (int) $course['id'];
        $course = $this->trainingService->getCourseWithStructure($courseId, $tenantId);
        $enrollment = $this->enrollmentRepository->findByCourseAndUser($courseId, $userId);
        if ($enrollment && in_array((string) ($enrollment['status'] ?? ''), training_enrollment_inactive_for_member_ui_statuses(), true)) {
            $enrollment = null;
        }
        $progressPercent = 0;
        if ($enrollment && $this->trainingService->canAccessCourse($userId, $courseId, $tenantId)) {
            $progressPercent = $this->trainingService->getGlobalProgress((int) $enrollment['id']);
        }
        return Response::json([
            'course' => $course,
            'enrollment' => $enrollment,
            'progress_percent' => $progressPercent,
        ]);
    }

    public function enroll(Request $request, array $params = []): Response
    {
        $blocked = $this->assertTrainingAllowed();
        if ($blocked !== null) {
            return $blocked;
        }
        $this->validateCsrf($request);
        $tenantId = $this->tenantId();
        $userId = $this->userId();
        $body = $this->body($request);
        $courseId = (int) ($request->input('course_id') ?? $body['course_id'] ?? 0);
        if (!$courseId) {
            return Response::json(['error' => 'course_id requis.'], 400);
        }
        try {
            $motivation = trim((string) ($request->input('enrollment_motivation') ?? $body['enrollment_motivation'] ?? ''));
            $motivation = $motivation === '' ? null : mb_substr($motivation, 0, 4000);
            $enrollmentId = $this->assignmentService->assignUser($courseId, $userId, $tenantId, $userId, 'self_enroll', null, $motivation);
            $enrollment = $this->enrollmentRepository->findById($enrollmentId, $tenantId);
            if ($enrollment && (($enrollment['status'] ?? '') === 'assigned')) {
                $this->progressService->startEnrollment($enrollmentId, $tenantId, $userId);
                $enrollment = $this->enrollmentRepository->findById($enrollmentId, $tenantId);
            }
            return Response::json(['enrollment' => $enrollment]);
        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 400);
        }
    }

    public function progress(Request $request, array $params = []): Response
    {
        $blocked = $this->assertTrainingAllowed();
        if ($blocked !== null) {
            return $blocked;
        }
        $userId = $this->userId();
        $enrollmentId = (int) ($params['id'] ?? 0);
        $enrollment = $this->enrollmentRepository->findById($enrollmentId, $this->tenantId());
        if (!$enrollment || (int) $enrollment['user_id'] !== $userId) {
            return Response::json(['error' => 'Non autorisé.'], 403);
        }
        $progress = $this->progressService->getProgressForEnrollment($enrollmentId);
        return Response::json($progress);
    }

    public function progressLesson(Request $request, array $params = []): Response
    {
        $blocked = $this->assertTrainingAllowed();
        if ($blocked !== null) {
            return $blocked;
        }
        try {
            $this->validateCsrf($request);
        } catch (\RuntimeException) {
            $wantsJsonEarly = $this->clientExpectsJsonResponse();
            if ($wantsJsonEarly) {
                return Response::json(['error' => 'Session expirée ou sécurité : rechargez la page de la leçon puis réessayez.'], 403);
            }
            Session::flash('error', 'Session expirée. Rechargez la page puis réessayez.');

            return Response::redirect(url('formations'));
        }
        $tenantId = $this->tenantId();
        $userId = $this->userId();
        $wantsJson = $this->clientExpectsJsonResponse();
        $body = $this->body($request);
        $enrollmentId = (int) ($request->input('enrollment_id') ?? $body['enrollment_id'] ?? 0);
        $lessonId = (int) ($request->input('lesson_id') ?? $body['lesson_id'] ?? 0);
        $status = $request->input('status') ?? $body['status'] ?? 'completed';
        $timeSpent = (int) ($request->input('time_spent_seconds') ?? $body['time_spent_seconds'] ?? 0);

        $redirectToCourse = function () use ($enrollmentId, $tenantId): Response {
            $enrollment = $this->enrollmentRepository->findById($enrollmentId, $tenantId);
            if (!$enrollment) {
                return Response::redirect(url('formations'));
            }
            $slug = trim((string) ($enrollment['course_slug'] ?? ''));

            return Response::redirect($slug !== '' ? url('formations/' . rawurlencode($slug)) : url('formations'));
        };

        if (!$enrollmentId || !$lessonId) {
            if (!$wantsJson) {
                Session::flash('error', 'Données de progression incomplètes. Rechargez la page de la leçon et réessayez.');

                return Response::redirect(url('formations'));
            }

            return Response::json(['error' => 'enrollment_id et lesson_id requis.'], 400);
        }
        try {
            if ($status === 'in_progress') {
                $this->progressService->markLessonStarted($enrollmentId, $lessonId, $tenantId, $userId, $timeSpent);
            } else {
                $this->progressService->markLessonCompleted($enrollmentId, $lessonId, $tenantId, $userId, $timeSpent);
            }
            $progress = $this->progressService->getProgressForEnrollment($enrollmentId);
            if (!$wantsJson) {
                Session::flash('success', 'Votre progression a été enregistrée.');

                return $redirectToCourse();
            }

            return Response::json($progress);
        } catch (\Throwable $e) {
            if (!$wantsJson) {
                Session::flash('error', 'Impossible d’enregistrer la progression. Réessayez dans un instant.');

                return $redirectToCourse();
            }

            return Response::json(['error' => $e->getMessage()], 400);
        }
    }

    public function quizStart(Request $request, array $params = []): Response
    {
        $blocked = $this->assertTrainingAllowed();
        if ($blocked !== null) {
            return $blocked;
        }
        $this->validateCsrf($request);
        $tenantId = $this->tenantId();
        $userId = $this->userId();
        $body = $this->body($request);
        $quizId = (int) ($request->input('quiz_id') ?? $body['quiz_id'] ?? 0);
        $enrollmentId = (int) ($request->input('enrollment_id') ?? $body['enrollment_id'] ?? 0);
        if (!$quizId || !$enrollmentId) {
            return Response::json(['error' => 'quiz_id et enrollment_id requis.'], 400);
        }
        try {
            $attempt = $this->quizService->startAttempt($quizId, $enrollmentId, $tenantId, $userId);
            $quiz = $this->quizRepository->findQuizById($quizId);
            $questions = $this->quizRepository->listQuestionsByQuizId($quizId, (bool) ($quiz['randomize_questions'] ?? 0));
            $questionsWithAnswers = [];
            foreach ($questions as $q) {
                $q['answers'] = $this->quizRepository->listAnswersByQuestionId((int) $q['id']);
                unset($q['explanation']);
                $questionsWithAnswers[] = $q;
            }
            $questionsWithAnswers = $this->sanitizeQuizQuestionsForLearner($questionsWithAnswers);
            $attemptOut = $attempt;
            if (is_array($attemptOut)) {
                $attemptOut['started_at_rfc3339'] = $this->quizService->startedAtToRfc3339Utc(
                    isset($attemptOut['started_at']) ? (string) $attemptOut['started_at'] : null
                );
            }

            return Response::json([
                'attempt' => $attemptOut,
                'questions' => $questionsWithAnswers,
                'time_limit_minutes' => $quiz['time_limit_minutes'] ?? null,
            ]);
        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 400);
        }
    }

    public function quizAttempt(Request $request, array $params = []): Response
    {
        $blocked = $this->assertTrainingAllowed();
        if ($blocked !== null) {
            return $blocked;
        }
        $userId = $this->userId();
        $attemptId = (int) ($params['id'] ?? 0);
        $attempt = $this->quizService->getAttempt($attemptId, $this->tenantId(), $userId);
        if (!$attempt) {
            return Response::json(['error' => 'Tentative non trouvée.'], 404);
        }
        $this->quizService->markAttemptExpiredIfTimeElapsed($attemptId);
        $attempt = $this->quizService->getAttempt($attemptId, $this->tenantId(), $userId);
        if (!$attempt) {
            return Response::json(['error' => 'Tentative non trouvée.'], 404);
        }
        if (($attempt['status'] ?? '') === 'in_progress') {
            $quiz = $this->quizRepository->findQuizById((int) ($attempt['quiz_id'] ?? 0));
            if ($quiz) {
                $questions = $this->quizRepository->listQuestionsByQuizId((int) $quiz['id'], (bool) ($quiz['randomize_questions'] ?? 0));
                $questionsWithAnswers = [];
                foreach ($questions as $q) {
                    $q['answers'] = $this->quizRepository->listAnswersByQuestionId((int) $q['id']);
                    unset($q['explanation']);
                    $questionsWithAnswers[] = $q;
                }
                $attempt['questions'] = $this->sanitizeQuizQuestionsForLearner($questionsWithAnswers);
                $attempt['time_limit_minutes'] = $quiz['time_limit_minutes'] ?? null;
                $attempt['passing_score'] = $quiz['passing_score'] ?? null;
                $attempt['started_at_rfc3339'] = $this->quizService->startedAtToRfc3339Utc(
                    isset($attempt['started_at']) ? (string) $attempt['started_at'] : null
                );
            }
        }
        return Response::json($attempt);
    }

    public function quizSubmit(Request $request, array $params = []): Response
    {
        $blocked = $this->assertTrainingAllowed();
        if ($blocked !== null) {
            return $blocked;
        }
        try {
            $this->validateCsrf($request);
        } catch (\RuntimeException) {
            return Response::json(['error' => 'Session expirée ou sécurité : rechargez la page du questionnaire puis réessayez.'], 403);
        }
        $tenantId = $this->tenantId();
        $userId = $this->userId();
        $body = $this->body($request);
        $attemptId = (int) ($request->input('attempt_id') ?? $body['attempt_id'] ?? 0);
        $responses = $body['responses'] ?? $request->input('responses') ?? [];
        if (!is_array($responses)) {
            $responses = [];
        }
        if (!$attemptId) {
            return Response::json(['error' => 'Données de soumission incomplètes. Rechargez la page.'], 400);
        }
        try {
            $attempt = $this->quizService->submitAttempt($attemptId, $responses, $tenantId, $userId);
            return Response::json(['attempt' => $attempt]);
        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 400);
        }
    }

    public function certificateByEnrollment(Request $request, array $params = []): Response
    {
        $blocked = $this->assertTrainingAllowed();
        if ($blocked !== null) {
            return $blocked;
        }
        $userId = $this->userId();
        $enrollmentId = (int) ($params['id'] ?? 0);
        $enrollment = $this->enrollmentRepository->findById($enrollmentId, $this->tenantId());
        if (!$enrollment || (int) $enrollment['user_id'] !== $userId) {
            return Response::json(['error' => 'Non autorisé.'], 403);
        }
        $cert = $this->certificateService->getByEnrollment($enrollmentId);
        if (!$cert) {
            $cert = $this->certificateService->issueCertificate($enrollmentId, $this->tenantId(), $userId);
        }
        return Response::json(['certificate' => $cert]);
    }

    public function certificateDownload(Request $request, array $params = []): Response
    {
        $blocked = $this->assertTrainingAllowed();
        if ($blocked !== null) {
            return $blocked;
        }
        $userId = $this->userId();
        $id = (int) ($params['id'] ?? 0);
        $cert = $this->certificateService->getById($id, $this->tenantId());
        if (!$cert || (int) $cert['user_id'] !== $userId) {
            return Response::json(['error' => 'Certificat non trouvé.'], 404);
        }
        $pdfPath = $cert['pdf_path'];
        if (empty($pdfPath)) {
            return Response::json(['error' => 'PDF non disponible.'], 404);
        }
        if (!str_starts_with($pdfPath, '/') && !preg_match('#^[A-Za-z]:#', $pdfPath)) {
            $pdfPath = base_path($pdfPath);
        }
        if (!is_file($pdfPath)) {
            return Response::json(['error' => 'PDF non disponible.'], 404);
        }
        $response = new Response();
        $response->header('Content-Type', 'application/pdf');
        $response->header('Content-Disposition', 'attachment; filename="attestation-' . (int) $cert['id'] . '.pdf"');
        $response->setBodyStream(static function () use ($pdfPath) {
            readfile($pdfPath);
        });
        return $response;
    }

    /** Lien de consultation publique (signé, durée limitée) pour le titulaire de l’attestation. */
    public function certificateConsultationLink(Request $request, array $params = []): Response
    {
        $blocked = $this->assertTrainingAllowed();
        if ($blocked !== null) {
            return $blocked;
        }
        $userId = $this->userId();
        $id = (int) ($params['id'] ?? 0);
        $cert = $this->certificateService->getById($id, $this->tenantId());
        if (!$cert || (int) ($cert['user_id'] ?? 0) !== $userId) {
            return Response::json(['error' => 'Non autorisé.'], 403);
        }
        // Aligné sur l’affichage attestation : statut absent en BDD = traité comme valide (lignes historiques).
        $statusRaw = (string) ($cert['status'] ?? 'valid');
        if ($statusRaw !== 'valid') {
            return Response::json(['error' => 'Document indisponible.'], 404);
        }
        $mint = $this->certificateShareService->mint($id);
        $url = $this->certificateShareService->buildConsultationUrl($id, $mint['token'], $mint['expires_at']);

        return Response::json([
            'consultation_url' => $url,
            'expires_at' => $mint['expires_at'],
        ]);
    }

    /** Téléchargement sécurisé d’une ressource de leçon (fichier sur serveur). */
    public function lessonResourceDownload(Request $request, array $params = []): Response
    {
        $blocked = $this->assertTrainingAllowed();
        if ($blocked !== null) {
            return $blocked;
        }
        $tenantId = $this->tenantId();
        $userId = $this->userId();
        $rid = (int) ($params['id'] ?? 0);
        if ($rid < 1) {
            return (new Response())->setStatusCode(404)->header('Content-Type', 'text/plain; charset=utf-8')->setBody('Ressource introuvable.');
        }
        $res = $this->resourceRepository->findById($rid);
        if (!$res || empty($res['file_path']) || (string) ($res['resource_type'] ?? '') === 'library_document') {
            return (new Response())->setStatusCode(404)->header('Content-Type', 'text/plain; charset=utf-8')->setBody('Ressource introuvable.');
        }
        $lesson = $this->lessonRepository->findById((int) $res['lesson_id']);
        if (!$lesson) {
            return (new Response())->setStatusCode(404)->header('Content-Type', 'text/plain; charset=utf-8')->setBody('Ressource introuvable.');
        }
        $mod = $this->moduleRepository->findById((int) $lesson['module_id']);
        if (!$mod) {
            return (new Response())->setStatusCode(404)->header('Content-Type', 'text/plain; charset=utf-8')->setBody('Ressource introuvable.');
        }
        $courseId = (int) $mod['course_id'];
        if (!$this->courseRepository->findByIdForViewer($courseId, $tenantId)) {
            return (new Response())->setStatusCode(404)->header('Content-Type', 'text/plain; charset=utf-8')->setBody('Ressource introuvable.');
        }
        $enrollment = $this->enrollmentRepository->findByCourseAndUser($courseId, $userId);
        if (!$enrollment || in_array((string) ($enrollment['status'] ?? ''), training_enrollment_inactive_for_member_ui_statuses(), true)) {
            return (new Response())->setStatusCode(403)->header('Content-Type', 'text/plain; charset=utf-8')->setBody('Accès non autorisé.');
        }
        if (!$this->trainingService->canAccessCourse($userId, $courseId, $tenantId)) {
            return (new Response())->setStatusCode(403)->header('Content-Type', 'text/plain; charset=utf-8')->setBody('Accès non autorisé.');
        }
        $path = (string) $res['file_path'];
        if (!str_starts_with($path, '/') && !preg_match('#^[A-Za-z]:#', $path)) {
            $path = base_path($path);
        }
        if (!is_file($path) || !is_readable($path)) {
            return (new Response())->setStatusCode(404)->header('Content-Type', 'text/plain; charset=utf-8')->setBody('Fichier indisponible.');
        }
        $mime = trim((string) ($res['mime_type'] ?? ''));
        if ($mime === '') {
            $mime = 'application/octet-stream';
        }
        $safeName = preg_replace('/[^a-zA-Z0-9._-]+/', '_', basename($path)) ?: 'fichier';
        $response = new Response();
        $response->header('Content-Type', $mime);
        $response->header('Content-Disposition', 'attachment; filename="' . $safeName . '"');
        $response->setBodyStream(static function () use ($path): void {
            readfile($path);
        });

        return $response;
    }

    /**
     * Fichier d’un document du centre rattaché à la ressource (inscription au parcours + droits document, sans gate documents.view).
     */
    public function lessonResourceLinkedDocument(Request $request, array $params = []): Response
    {
        $blocked = $this->assertTrainingAllowed();
        if ($blocked !== null) {
            return $blocked;
        }
        $tenantId = $this->tenantId();
        $userId = $this->userId();
        $rid = (int) ($params['id'] ?? 0);
        if ($rid < 1) {
            return (new Response())->setStatusCode(404)->header('Content-Type', 'text/plain; charset=utf-8')->setBody('Ressource introuvable.');
        }
        $res = $this->resourceRepository->findById($rid);
        if (!$res || empty($res['document_id']) || (string) ($res['resource_type'] ?? '') !== 'library_document') {
            return (new Response())->setStatusCode(404)->header('Content-Type', 'text/plain; charset=utf-8')->setBody('Ressource introuvable.');
        }
        $lesson = $this->lessonRepository->findById((int) $res['lesson_id']);
        if (!$lesson) {
            return (new Response())->setStatusCode(404)->header('Content-Type', 'text/plain; charset=utf-8')->setBody('Ressource introuvable.');
        }
        $mod = $this->moduleRepository->findById((int) $lesson['module_id']);
        if (!$mod) {
            return (new Response())->setStatusCode(404)->header('Content-Type', 'text/plain; charset=utf-8')->setBody('Ressource introuvable.');
        }
        $courseId = (int) $mod['course_id'];
        if (!$this->courseRepository->findByIdForViewer($courseId, $tenantId)) {
            return (new Response())->setStatusCode(404)->header('Content-Type', 'text/plain; charset=utf-8')->setBody('Ressource introuvable.');
        }
        $enrollment = $this->enrollmentRepository->findByCourseAndUser($courseId, $userId);
        if (!$enrollment || in_array((string) ($enrollment['status'] ?? ''), training_enrollment_inactive_for_member_ui_statuses(), true)) {
            return (new Response())->setStatusCode(403)->header('Content-Type', 'text/plain; charset=utf-8')->setBody('Accès non autorisé.');
        }
        if (!$this->trainingService->canAccessCourse($userId, $courseId, $tenantId)) {
            return (new Response())->setStatusCode(403)->header('Content-Type', 'text/plain; charset=utf-8')->setBody('Accès non autorisé.');
        }

        $docId = (int) $res['document_id'];
        $doc = $this->documentRepository->findById($docId, $tenantId);
        if (!$doc || empty($doc['file_path'])) {
            return (new Response())->setStatusCode(404)->header('Content-Type', 'text/plain; charset=utf-8')->setBody('Document indisponible.');
        }
        if (($doc['status'] ?? '') !== 'published') {
            return (new Response())->setStatusCode(404)->header('Content-Type', 'text/plain; charset=utf-8')->setBody('Document indisponible.');
        }
        if (!$this->documentAccessService->canRead($doc, $userId, $tenantId)) {
            return (new Response())->setStatusCode(403)->header('Content-Type', 'text/plain; charset=utf-8')->setBody('Accès non autorisé à ce document.');
        }
        if ($this->linkedDocumentFileBlockedForLearner($doc)) {
            return (new Response())->setStatusCode(403)->header('Content-Type', 'text/plain; charset=utf-8')->setBody('Fichier non disponible.');
        }

        $fullPath = base_path('storage/documents/' . $doc['file_path']);
        if (!is_file($fullPath)) {
            return (new Response())->setStatusCode(404)->header('Content-Type', 'text/plain; charset=utf-8')->setBody('Fichier indisponible.');
        }

        $inline = trim((string) $request->query('inline', '')) === '1';
        $this->auditService->logDocumentDownloaded($tenantId, $userId, $docId);

        $mime = trim((string) ($doc['mime_type'] ?? '')) ?: 'application/octet-stream';
        $disp = $inline ? 'inline' : 'attachment';
        $fname = basename((string) $doc['file_path']);
        $safeName = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $fname) ?: 'fichier';

        $response = new Response();
        $response->header('Content-Type', $mime);
        $response->header('Content-Disposition', $disp . '; filename="' . $safeName . '"');
        $response->header('Content-Length', (string) filesize($fullPath));
        $response->setBodyStream(static function () use ($fullPath): void {
            $h = fopen($fullPath, 'rb');
            if ($h) {
                fpassthru($h);
                fclose($h);
            }
        });

        return $response;
    }

    /** @param array<string, mixed> $doc */
    private function linkedDocumentFileBlockedForLearner(array $doc): bool
    {
        if (!$this->moderationArtifactRepository->tableExists()) {
            return false;
        }
        $versionId = (int) ($doc['version_id'] ?? 0);
        if ($versionId <= 0) {
            return false;
        }
        if ($this->learnerMayBypassDocumentModerationBlock()) {
            return false;
        }
        $row = $this->moderationArtifactRepository->findByDocumentVersionId($versionId);
        if (!$row) {
            return false;
        }
        $st = (string) ($row['state'] ?? '');

        return in_array($st, [
            ModerationArtifactState::PENDING_SCAN,
            ModerationArtifactState::QUARANTINED,
            ModerationArtifactState::REJECTED,
        ], true);
    }

    private function learnerMayBypassDocumentModerationBlock(): bool
    {
        $gate = Gate::getInstance();

        return (function_exists('can') && (can('forum.moderate') || can('forum.moderate_organization')))
            || $gate->allows('admin.organization')
            || $gate->allows('admin.access')
            || $gate->allows('admin.system');
    }

    // ---------- Admin API ----------
    public function adminCourses(Request $request, array $params = []): Response
    {
        $blocked = $this->assertTrainingAllowed();
        if ($blocked !== null) {
            return $blocked;
        }
        $this->requireTrainingAccess();
        $tenantId = $this->tenantId();
        $courses = $this->courseRepository->listForTenant($tenantId, null);
        return Response::json(['courses' => $courses]);
    }

    public function adminCourseSave(Request $request, array $params = []): Response
    {
        $blocked = $this->assertTrainingAllowed();
        if ($blocked !== null) {
            return $blocked;
        }
        $this->validateCsrf($request);
        $this->requireTrainingAccess();
        $tenantId = $this->tenantId();
        $userId = $this->userId();
        $id = (int) ($params['id'] ?? $request->input('id') ?? 0);
        $data = $this->body($request) ?: $request->all();
        if ($id) {
            $course = $this->courseRepository->findById($id, $tenantId);
            if (!$course) {
                return Response::json(['error' => 'Formation non trouvée.'], 404);
            }
            $this->courseRepository->update($id, [
                'title' => $data['title'] ?? $course['title'],
                'slug' => $data['slug'] ?? $course['slug'],
                'course_code' => array_key_exists('course_code', $data) ? $data['course_code'] : ($course['course_code'] ?? null),
                'short_description' => $data['short_description'] ?? $course['short_description'],
                'description' => $data['description'] ?? $course['description'],
                'learning_objectives' => array_key_exists('learning_objectives', $data) ? $data['learning_objectives'] : ($course['learning_objectives'] ?? null),
                'theme_json' => array_key_exists('theme_json', $data) ? $data['theme_json'] : ($course['theme_json'] ?? null),
                'thumbnail_path' => array_key_exists('thumbnail_path', $data) ? $data['thumbnail_path'] : $course['thumbnail_path'],
                'banner_path' => array_key_exists('banner_path', $data) ? $data['banner_path'] : $course['banner_path'],
                'showcase_cycle_date' => array_key_exists('showcase_cycle_date', $data) ? $data['showcase_cycle_date'] : ($course['showcase_cycle_date'] ?? null),
                'showcase_location' => array_key_exists('showcase_location', $data) ? $data['showcase_location'] : ($course['showcase_location'] ?? null),
                'showcase_badge' => $data['showcase_badge'] ?? ($course['showcase_badge'] ?? 'open'),
                'showcase_card_style' => $data['showcase_card_style'] ?? ($course['showcase_card_style'] ?? 'default'),
                'showcase_sort_order' => array_key_exists('showcase_sort_order', $data) ? $data['showcase_sort_order'] : ($course['showcase_sort_order'] ?? null),
                'visibility' => $data['visibility'] ?? $course['visibility'],
                'updated_by' => $userId,
            ]);
            return Response::json(['id' => $id]);
        }
        $slug = $data['slug'] ?? preg_replace('/[^a-z0-9-]/', '-', strtolower((string) ($data['title'] ?? '')));
        if ($this->courseRepository->slugExists($tenantId, $slug)) {
            return Response::json(['error' => 'Ce slug existe déjà.'], 400);
        }
        $newId = $this->courseRepository->create($tenantId, [
            'title' => $data['title'] ?? 'Nouvelle formation',
            'slug' => $slug,
            'course_code' => $data['course_code'] ?? null,
            'short_description' => $data['short_description'] ?? null,
            'description' => $data['description'] ?? null,
            'learning_objectives' => $data['learning_objectives'] ?? null,
            'theme_json' => $data['theme_json'] ?? null,
            'thumbnail_path' => $data['thumbnail_path'] ?? null,
            'banner_path' => $data['banner_path'] ?? null,
            'visibility' => $data['visibility'] ?? 'draft',
            'created_by' => $userId,
        ]);
        $showcasePatch = [];
        foreach (['showcase_cycle_date', 'showcase_location', 'showcase_badge', 'showcase_card_style', 'showcase_sort_order'] as $sk) {
            if (array_key_exists($sk, $data)) {
                $showcasePatch[$sk] = $data[$sk];
            }
        }
        if ($showcasePatch !== []) {
            $showcasePatch['updated_by'] = $userId;
            $this->courseRepository->update($newId, $showcasePatch);
        }
        return Response::json(['id' => $newId]);
    }

    public function adminAssign(Request $request, array $params = []): Response
    {
        $blocked = $this->assertTrainingAllowed();
        if ($blocked !== null) {
            return $blocked;
        }
        $this->validateCsrf($request);
        $this->requireTrainingAssignOrManage();
        $tenantId = $this->tenantId();
        $userId = $this->userId();
        $body = $this->body($request);
        $courseId = (int) ($request->input('course_id') ?? $body['course_id'] ?? 0);
        $userIds = $request->input('user_ids') ?? $body['user_ids'] ?? [];
        $roleId = $request->input('role_id') ?? $body['role_id'] ?? null;
        $unitId = $request->input('unit_id') ?? $body['unit_id'] ?? null;
        $expiresAt = $request->input('expires_at') ?? $body['expires_at'] ?? null;
        if (!$courseId) {
            return Response::json(['error' => 'course_id requis.'], 400);
        }
        try {
            if ($roleId !== null && $roleId !== '') {
                $count = $this->assignmentService->assignByRole($courseId, (int) $roleId, $tenantId, $userId, $expiresAt);
                return Response::json(['assigned' => $count]);
            }
            if ($unitId !== null && $unitId !== '') {
                $count = $this->assignmentService->assignByUnit($courseId, (int) $unitId, $tenantId, $userId, $expiresAt);
                return Response::json(['assigned' => $count]);
            }
            $count = 0;
            foreach ((array) $userIds as $uid) {
                $this->assignmentService->assignUser($courseId, (int) $uid, $tenantId, $userId, 'manual', $expiresAt);
                $count++;
            }
            return Response::json(['assigned' => $count]);
        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 400);
        }
    }

    /** Admin formations : admin, training.manage ou droits de création / édition LMS. */
    private function requireTrainingAccess(): void
    {
        $gate = \App\Core\Gate::getInstance();
        if ($gate->allows('admin.access') || $gate->allows('training.manage')
            || $gate->allows('training.create') || $gate->allows('training.update')
            || $gate->allows('training.delete') || $gate->allows('training.publish')) {
            return;
        }
        throw new \RuntimeException('Accès refusé.', 403);
    }

    /** Assignations : admin, training.manage ou training.assign. */
    private function requireTrainingAssignOrManage(): void
    {
        $gate = \App\Core\Gate::getInstance();
        if ($gate->allows('admin.access') || $gate->allows('training.manage') || $gate->allows('training.assign')) {
            return;
        }
        throw new \RuntimeException('Accès refusé.', 403);
    }
}
