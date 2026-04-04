<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\DocumentLinkRepository;
use App\Repositories\DocumentRepository;
use App\Repositories\TrainingRepository;
use App\Repositories\TrainingEnrollmentRepository;
use App\Repositories\TrainingLessonRepository;
use App\Repositories\TrainingResourceRepository;
use App\Services\Training\TrainingService;
use App\Services\Training\TrainingProgressService;
use App\Services\Training\TrainingCertificateService;
use App\Services\Platform\FeatureGateService;

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
        private FeatureGateService $featureGate
    ) {}

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
        $legacyModules = $this->trainingRepository->listPublishedForTenant($tenantId);
        return Response::view('training.catalogue', [
            'title' => 'Catalogue Formations',
            'courses' => $courses,
            'legacyModules' => $legacyModules,
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
            $e['progress_percent'] = $this->trainingService->getGlobalProgress((int) $e['id']);
            $withProgress[] = $e;
        }
        return Response::view('training.my-training', [
            'title' => 'Mes formations',
            'enrollments' => $withProgress,
        ]);
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
            return (new Response())->setStatusCode(404)->setBody('Formation non trouvée.');
        }
        $courseId = (int) $course['id'];
        $course = $this->trainingService->getCourseWithStructure($courseId, $tenantId);
        $enrollment = $userId ? $this->enrollmentRepository->findByCourseAndUser($courseId, (int) $userId) : null;
        if ($enrollment && !$this->trainingService->canAccessCourse((int) $userId, $courseId, $tenantId)) {
            $enrollment = null;
        }
        $progressPercent = $enrollment ? $this->trainingService->getGlobalProgress((int) $enrollment['id']) : 0;
        $certificate = $enrollment ? $this->certificateService->getByEnrollment((int) $enrollment['id']) : null;
        return Response::view('training.course', [
            'title' => $course['title'],
            'course' => $course,
            'enrollment' => $enrollment,
            'progressPercent' => $progressPercent,
            'certificate' => $certificate,
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
        return $this->show($request, $params);
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
        $lesson = $this->lessonRepository->findById($lessonId);
        if (!$lesson) {
            return (new Response())->setStatusCode(404)->setBody('Leçon non trouvée.');
        }
        $resources = $this->resourceRepository->listByLessonId($lessonId);
        $progress = $this->progressService->getProgressForEnrollment($enrollmentId);
        return Response::view('training.lesson', [
            'title' => $lesson['title'],
            'lesson' => $lesson,
            'enrollment' => $enrollment,
            'resources' => $resources,
            'progress' => $progress,
        ]);
    }

    /** Page quiz (tentative). */
    public function quiz(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        $userId = Session::get('user_id');
        if (!$tenantId || !$userId) {
            return Response::redirect(url('login'));
        }
        $attemptId = (int) ($params['id'] ?? 0);
        if (!$attemptId) {
            return (new Response())->setStatusCode(404)->setBody('Tentative non trouvée.');
        }
        return Response::view('training.quiz', [
            'title' => 'Quiz',
            'attemptId' => $attemptId,
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
