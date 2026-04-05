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
use App\Services\Training\TrainingAssignmentService;
use App\Repositories\TrainingEnrollmentRepository;
use App\Repositories\TrainingQuizRepository;
use App\Repositories\TrainingCourseRepository;
use App\Services\Platform\FeatureGateService;

class TrainingApiController
{
    public function __construct(
        private TrainingService $trainingService,
        private TrainingProgressService $progressService,
        private TrainingQuizService $quizService,
        private TrainingCertificateService $certificateService,
        private TrainingAssignmentService $assignmentService,
        private TrainingEnrollmentRepository $enrollmentRepository,
        private TrainingQuizRepository $quizRepository,
        private TrainingCourseRepository $courseRepository,
        private FeatureGateService $featureGate
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

    private function body(Request $request): array
    {
        $contentType = $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }
        return array_merge($request->all(), $_POST);
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
        $progressPercent = 0;
        if ($enrollment) {
            if (!$this->trainingService->canAccessCourse($userId, $courseId, $tenantId)) {
                $enrollment = null;
            } else {
                $progressPercent = $this->trainingService->getGlobalProgress((int) $enrollment['id']);
            }
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
            $enrollmentId = $this->assignmentService->assignUser($courseId, $userId, $tenantId, $userId, 'self_enroll');
            $this->progressService->startEnrollment($enrollmentId, $tenantId, $userId);
            $enrollment = $this->enrollmentRepository->findById($enrollmentId, $tenantId);
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
        $this->validateCsrf($request);
        $tenantId = $this->tenantId();
        $userId = $this->userId();
        $body = $this->body($request);
        $enrollmentId = (int) ($request->input('enrollment_id') ?? $body['enrollment_id'] ?? 0);
        $lessonId = (int) ($request->input('lesson_id') ?? $body['lesson_id'] ?? 0);
        $status = $request->input('status') ?? $body['status'] ?? 'completed';
        $timeSpent = (int) ($request->input('time_spent_seconds') ?? $body['time_spent_seconds'] ?? 0);
        if (!$enrollmentId || !$lessonId) {
            return Response::json(['error' => 'enrollment_id et lesson_id requis.'], 400);
        }
        try {
            if ($status === 'in_progress') {
                $this->progressService->markLessonStarted($enrollmentId, $lessonId, $tenantId, $userId, $timeSpent);
            } else {
                $this->progressService->markLessonCompleted($enrollmentId, $lessonId, $tenantId, $userId, $timeSpent);
            }
            $progress = $this->progressService->getProgressForEnrollment($enrollmentId);
            return Response::json($progress);
        } catch (\Throwable $e) {
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
            return Response::json([
                'attempt' => $attempt,
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
        return Response::json($attempt);
    }

    public function quizSubmit(Request $request, array $params = []): Response
    {
        $blocked = $this->assertTrainingAllowed();
        if ($blocked !== null) {
            return $blocked;
        }
        $this->validateCsrf($request);
        $tenantId = $this->tenantId();
        $userId = $this->userId();
        $body = $this->body($request);
        $attemptId = (int) ($request->input('attempt_id') ?? $body['attempt_id'] ?? 0);
        $responses = $request->input('responses') ?? $body['responses'] ?? [];
        if (!$attemptId || !is_array($responses)) {
            return Response::json(['error' => 'attempt_id et responses requis.'], 400);
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
                'short_description' => $data['short_description'] ?? $course['short_description'],
                'description' => $data['description'] ?? $course['description'],
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
            'short_description' => $data['short_description'] ?? null,
            'description' => $data['description'] ?? null,
            'visibility' => $data['visibility'] ?? 'draft',
            'created_by' => $userId,
        ]);
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
