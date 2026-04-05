<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TrainingCourseRepository;
use App\Repositories\TrainingEnrollmentRepository;
use App\Repositories\TrainingCertificateRepository;
use App\Services\Training\TrainingAuditService;
use App\Services\Training\TrainingAssignmentService;

class AdminTrainingController
{
    public function __construct(
        private TrainingCourseRepository $courseRepository,
        private TrainingEnrollmentRepository $enrollmentRepository,
        private TrainingCertificateRepository $certificateRepository,
        private TrainingAssignmentService $assignmentService,
        private TrainingAuditService $auditService
    ) {}

    public function dashboard(Request $request, array $params = []): Response
    {
        $this->requireTrainingAccess();
        $tenantId = (int) Session::get('tenant_id');
        $courses = $this->courseRepository->listForTenant($tenantId, null);
        $totalEnrollments = 0;
        $completed = 0;
        foreach ($courses as $c) {
            $list = $this->enrollmentRepository->listByCourseId((int) $c['id']);
            $totalEnrollments += count($list);
            foreach ($list as $e) {
                if (($e['status'] ?? '') === 'completed') {
                    $completed++;
                }
            }
        }
        $expiring = $this->assignmentService->listOverdueOrExpiring($tenantId, 30);
        return Response::view('layout.main', [
            'content' => 'admin.training.dashboard',
            'title' => 'Formations — Admin',
            'stats' => [
                'courses' => count($courses),
                'enrollments' => $totalEnrollments,
                'completed' => $totalEnrollments > 0 ? round(100.0 * $completed / $totalEnrollments, 1) : 0,
                'expiringCount' => count($expiring),
            ],
            'expiring' => $expiring,
        ]);
    }

    public function courses(Request $request, array $params = []): Response
    {
        $this->requireTrainingAccess();
        $tenantId = (int) Session::get('tenant_id');
        $courses = $this->courseRepository->listForTenant($tenantId, null);
        return Response::view('layout.main', [
            'content' => 'admin.training.courses',
            'title' => 'Formations',
            'courses' => $courses,
        ]);
    }

    public function enrollments(Request $request, array $params = []): Response
    {
        $this->requireTrainingAssignOrManage();
        $courseId = (int) ($params['id'] ?? $request->query('course_id') ?? 0);
        $enrollments = [];
        if ($courseId) {
            $enrollments = $this->enrollmentRepository->listByCourseId($courseId);
        }
        $courses = $this->courseRepository->listForTenant((int) Session::get('tenant_id'), null);
        return Response::view('layout.main', [
            'content' => 'admin.training.enrollments',
            'title' => 'Assignations',
            'enrollments' => $enrollments,
            'courses' => $courses,
            'selectedCourseId' => $courseId,
        ]);
    }

    public function reports(Request $request, array $params = []): Response
    {
        $this->requireTrainingAccess();
        $tenantId = (int) Session::get('tenant_id');
        $courses = $this->courseRepository->listForTenant($tenantId, null);
        return Response::view('layout.main', [
            'content' => 'admin.training.reports',
            'title' => 'Rapports',
            'courses' => $courses,
        ]);
    }

    public function certificates(Request $request, array $params = []): Response
    {
        $this->requireTrainingAccess();
        $tenantId = (int) Session::get('tenant_id');
        $stmt = \App\Core\Database::getPdo()->prepare(
            'SELECT c.*, e.user_id, e.course_id, cr.title AS course_title FROM training_certificates c
             JOIN training_enrollments e ON e.id = c.enrollment_id
             JOIN training_courses cr ON cr.id = e.course_id
             WHERE c.tenant_id = ? ORDER BY c.issued_at DESC LIMIT 200'
        );
        $stmt->execute([$tenantId]);
        $certificates = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return Response::view('layout.main', [
            'content' => 'admin.training.certificates',
            'title' => 'Certificats',
            'certificates' => $certificates,
        ]);
    }

    public function audit(Request $request, array $params = []): Response
    {
        $this->requireTrainingAccess();
        $tenantId = (int) Session::get('tenant_id');
        $logs = $this->auditService->listLogs($tenantId, null, null, null, 200);
        return Response::view('layout.main', [
            'content' => 'admin.training.audit',
            'title' => 'Audit Formations',
            'logs' => $logs,
        ]);
    }

    /** Accès au back-office formations : admin, training.manage ou droits de création / édition LMS. */
    private function requireTrainingAccess(): void
    {
        $gate = Gate::getInstance();
        if ($gate->allows('admin.access') || $gate->allows('training.manage')
            || $gate->allows('training.create') || $gate->allows('training.update')
            || $gate->allows('training.delete') || $gate->allows('training.publish')) {
            return;
        }
        throw new \RuntimeException('Accès refusé.', 403);
    }

    /** Assignations : admin, training.manage ou training.assign (assignation seule). */
    private function requireTrainingAssignOrManage(): void
    {
        $gate = Gate::getInstance();
        if ($gate->allows('admin.access') || $gate->allows('training.manage') || $gate->allows('training.assign')) {
            return;
        }
        throw new \RuntimeException('Accès refusé.', 403);
    }
}
