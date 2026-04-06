<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TenantRepository;
use App\Repositories\TrainingCourseRepository;
use App\Repositories\TrainingEnrollmentRepository;
use App\Repositories\TrainingCertificateRepository;
use App\Repositories\UserRepository;
use App\Services\EmailService;
use App\Services\Training\TrainingAuditService;
use App\Services\Training\TrainingAssignmentService;
use App\Services\Training\TrainingEnrollmentPolicyService;
use App\Services\Training\TrainingProgressService;

class AdminTrainingController
{
    public function __construct(
        private TrainingCourseRepository $courseRepository,
        private TrainingEnrollmentRepository $enrollmentRepository,
        private TrainingCertificateRepository $certificateRepository,
        private TrainingAssignmentService $assignmentService,
        private TrainingAuditService $auditService,
        private TenantRepository $tenantRepository,
        private TrainingProgressService $progressService,
        private EmailService $emailService,
        private UserRepository $userRepository,
        private TrainingEnrollmentPolicyService $enrollmentPolicyService
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
            'trainingAdminNav' => 'dashboard',
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
            'trainingAdminNav' => 'courses',
            'courses' => $courses,
        ]);
    }

    public function enrollments(Request $request, array $params = []): Response
    {
        $courseId = (int) ($params['id'] ?? $request->query('course_id') ?? 0);
        if (!$this->userCanViewEnrollmentsScreen($courseId)) {
            throw new \RuntimeException('Accès refusé.', 403);
        }
        $enrollments = [];
        if ($courseId) {
            $enrollments = $this->enrollmentRepository->listByCourseId($courseId);
        }
        $courses = $this->courseRepository->listForTenant((int) Session::get('tenant_id'), null);
        $actorId = (int) Session::get('user_id');
        $courseById = [];
        foreach ($courses as $c) {
            $courseById[(int) ($c['id'] ?? 0)] = $c;
        }
        $approvalRights = [];
        foreach ($enrollments as $e) {
            $eid = (int) ($e['id'] ?? 0);
            $cid = (int) ($e['course_id'] ?? 0);
            $approvalRights[$eid] = $this->canActorManagePendingEnrollment($courseById[$cid] ?? [], $actorId);
        }

        return Response::view('layout.main', [
            'content' => 'admin.training.enrollments',
            'title' => 'Assignations',
            'trainingAdminNav' => 'enrollments',
            'enrollments' => $enrollments,
            'courses' => $courses,
            'selectedCourseId' => $courseId,
            'trainingEnrollmentApprovalRights' => $approvalRights,
        ]);
    }

    public function approveEnrollment(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $actorId = (int) Session::get('user_id');
        $enrollmentId = (int) ($params['id'] ?? 0);
        $redirect = Response::redirect(url('admin/training/enrollments'));
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée, réessayez.');

            return $redirect;
        }
        $enrollment = $enrollmentId > 0 ? $this->enrollmentRepository->findById($enrollmentId, $tenantId) : null;
        if (!$enrollment || ($enrollment['status'] ?? '') !== 'pending_approval') {
            Session::flash('error', 'Demande introuvable ou déjà traitée.');

            return $redirect;
        }
        $courseId = (int) ($enrollment['course_id'] ?? 0);
        $course = $this->courseRepository->findById($courseId, $tenantId);
        if (!$course) {
            Session::flash('error', 'Formation introuvable.');

            return $redirect;
        }
        if (!$this->canActorManagePendingEnrollment($course, $actorId)) {
            Session::flash('error', 'Vous n’avez pas l’autorisation de valider cette inscription.');

            return $redirect;
        }
        $userId = (int) ($enrollment['user_id'] ?? 0);
        $this->enrollmentRepository->update($enrollmentId, ['status' => 'assigned']);
        try {
            $this->progressService->startEnrollment($enrollmentId, $tenantId, $userId);
        } catch (\Throwable $e) {
            Session::flash('error', 'Le statut a été mis à jour, mais le démarrage du parcours a échoué. Vérifiez la structure de la formation.');

            return Response::redirect(url('admin/training/enrollments?course_id=' . $courseId));
        }
        $this->auditService->logEnrollmentAssigned($tenantId, $actorId, $enrollmentId, [
            'user_id' => $userId,
            'course_id' => $courseId,
            'assignment_type' => 'self_enroll',
            'approved_from_pending' => true,
        ]);
        $this->notifyLearnerSelfEnrollApproved($tenantId, $userId, $course);
        Session::flash('success', 'Inscription validée. L’apprenant peut commencer le parcours.');

        return Response::redirect(url('admin/training/enrollments?course_id=' . $courseId));
    }

    public function declineEnrollment(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $actorId = (int) Session::get('user_id');
        $enrollmentId = (int) ($params['id'] ?? 0);
        $redirect = Response::redirect(url('admin/training/enrollments'));
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée, réessayez.');

            return $redirect;
        }
        $enrollment = $enrollmentId > 0 ? $this->enrollmentRepository->findById($enrollmentId, $tenantId) : null;
        if (!$enrollment || ($enrollment['status'] ?? '') !== 'pending_approval') {
            Session::flash('error', 'Demande introuvable ou déjà traitée.');

            return $redirect;
        }
        $courseId = (int) ($enrollment['course_id'] ?? 0);
        $course = $this->courseRepository->findById($courseId, $tenantId);
        if (!$course) {
            Session::flash('error', 'Formation introuvable.');

            return $redirect;
        }
        if (!$this->canActorManagePendingEnrollment($course, $actorId)) {
            Session::flash('error', 'Vous n’avez pas l’autorisation de refuser cette inscription.');

            return $redirect;
        }
        $userId = (int) ($enrollment['user_id'] ?? 0);
        $this->enrollmentRepository->update($enrollmentId, ['status' => 'revoked']);
        $this->auditService->logEnrollmentAssigned($tenantId, $actorId, $enrollmentId, [
            'user_id' => $userId,
            'course_id' => $courseId,
            'assignment_type' => 'self_enroll',
            'declined_from_pending' => true,
        ]);
        $this->notifyLearnerSelfEnrollDeclined($tenantId, $userId, $course);
        Session::flash('success', 'La demande d’inscription a été refusée. Un message a été envoyé à l’apprenant.');

        return Response::redirect(url('admin/training/enrollments?course_id=' . $courseId));
    }

    public function reports(Request $request, array $params = []): Response
    {
        $this->requireTrainingAccess();
        $tenantId = (int) Session::get('tenant_id');
        $courses = $this->courseRepository->listForTenant($tenantId, null);
        return Response::view('layout.main', [
            'content' => 'admin.training.reports',
            'title' => 'Rapports',
            'trainingAdminNav' => 'reports',
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
            'trainingAdminNav' => 'certificates',
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
            'trainingAdminNav' => 'audit',
            'logs' => $logs,
        ]);
    }

    /** Personnalisation vitrine (dashboard public / cartes). */
    public function courseShowcase(Request $request, array $params = []): Response
    {
        $this->requireTrainingAccess();
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        $course = $this->courseRepository->findById($id, $tenantId);
        if (!$course) {
            Session::flash('error', 'Formation introuvable.');

            return Response::redirect(url('admin/training/courses'));
        }

        if ($request->method() === 'POST') {
            if (!Csrf::validate($request->input('_csrf_token'))) {
                Session::flash('error', 'Session expirée, réessayez.');

                return Response::redirect(url('admin/training/courses/' . $id . '/showcase'));
            }

            $badge = (string) $request->input('showcase_badge', 'open');
            if (!in_array($badge, ['open', 'full', 'coming_soon', 'closed'], true)) {
                $badge = 'open';
            }
            $cardStyle = (string) $request->input('showcase_card_style', 'default');
            if (!in_array($cardStyle, ['default', 'grayscale'], true)) {
                $cardStyle = 'default';
            }

            $cycleRaw = trim((string) $request->input('showcase_cycle_date', ''));
            $showcaseCycleDate = $cycleRaw === '' ? null : $cycleRaw;

            $sortRaw = trim((string) $request->input('showcase_sort_order', ''));
            $sortOrder = $sortRaw === '' ? null : max(0, (int) $sortRaw);

            $this->courseRepository->update($id, [
                'thumbnail_path' => trim((string) $request->input('thumbnail_path', '')) ?: null,
                'banner_path' => trim((string) $request->input('banner_path', '')) ?: null,
                'short_description' => trim((string) $request->input('short_description', '')) ?: null,
                'description' => trim((string) $request->input('description', '')) ?: null,
                'showcase_cycle_date' => $showcaseCycleDate,
                'showcase_location' => trim((string) $request->input('showcase_location', '')) ?: null,
                'showcase_badge' => $badge,
                'showcase_card_style' => $cardStyle,
                'showcase_sort_order' => $sortOrder,
                'updated_by' => (int) Session::get('user_id'),
            ]);

            Session::flash('success', 'Vitrine et médias enregistrés.');

            return Response::redirect(url('admin/training/courses/' . $id . '/showcase'));
        }

        $tenant = $this->tenantRepository->findById($tenantId);

        return Response::view('layout.main', [
            'content' => 'admin.training.course_showcase',
            'title' => 'Vitrine — ' . (string) $course['title'],
            'trainingAdminNav' => 'showcase',
            'course' => $course,
            'tenant' => $tenant,
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

    /** Liste des inscriptions : staff formation, ou formateur habilité pour la formation déjà sélectionnée. */
    private function userCanViewEnrollmentsScreen(int $selectedCourseId): bool
    {
        $gate = Gate::getInstance();
        if ($gate->allows('admin.access') || $gate->allows('training.manage') || $gate->allows('training.assign')) {
            return true;
        }
        if ($gate->allows('training.create') || $gate->allows('training.update')
            || $gate->allows('training.delete') || $gate->allows('training.publish')) {
            return true;
        }
        if ($selectedCourseId < 1) {
            return false;
        }
        $tenantId = (int) Session::get('tenant_id');
        $actorId = (int) Session::get('user_id');
        $course = $this->courseRepository->findById($selectedCourseId, $tenantId);

        return $course !== null && $this->canActorManagePendingEnrollment($course, $actorId);
    }

    /**
     * @param array<string, mixed> $course
     */
    private function canActorManagePendingEnrollment(array $course, int $actorId): bool
    {
        if ($course === []) {
            return false;
        }
        $gate = Gate::getInstance();
        if ($gate->allows('admin.access') || $gate->allows('training.manage') || $gate->allows('training.assign')) {
            return true;
        }
        $policy = $this->enrollmentPolicyService->decodePolicy($course['enrollment_policy_json'] ?? null);
        foreach ($policy['enrollment_approver_user_ids'] ?? [] as $x) {
            if ((int) $x === $actorId) {
                return true;
            }
        }
        $creator = (int) ($course['created_by'] ?? 0);

        return $creator > 0 && $creator === $actorId;
    }

    /** @param array<string, mixed> $course */
    private function notifyLearnerSelfEnrollApproved(int $tenantId, int $userId, array $course): void
    {
        try {
            $user = $this->userRepository->findById($userId, $tenantId);
            if (!$user) {
                return;
            }
            $email = trim((string) ($user['email'] ?? ''));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return;
            }
            $tenant = $this->tenantRepository->findById($tenantId);
            $tenantName = $tenant ? (string) ($tenant['name'] ?? 'Communauté') : 'Communauté';
            if (function_exists('community_display_name') && $tenant) {
                $tenantName = community_display_name($tenant);
            }
            $display = trim((string) ($user['display_name'] ?? ''));
            if ($display === '') {
                $display = trim((string) ($user['callsign'] ?? ''));
            }
            if ($display === '') {
                $display = $email;
            }
            $slug = trim((string) ($course['slug'] ?? ''));
            $courseUrl = $slug !== '' ? \url('formations/' . rawurlencode($slug)) : \url('formations/mes-formations');
            $this->emailService->sendTrainingSelfEnrollApproved(
                $email,
                $display,
                $tenantName,
                (string) ($course['title'] ?? 'Formation'),
                $courseUrl,
                $tenantId
            );
        } catch (\Throwable) {
        }
    }

    /** @param array<string, mixed> $course */
    private function notifyLearnerSelfEnrollDeclined(int $tenantId, int $userId, array $course): void
    {
        try {
            $user = $this->userRepository->findById($userId, $tenantId);
            if (!$user) {
                return;
            }
            $email = trim((string) ($user['email'] ?? ''));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return;
            }
            $tenant = $this->tenantRepository->findById($tenantId);
            $tenantName = $tenant ? (string) ($tenant['name'] ?? 'Communauté') : 'Communauté';
            if (function_exists('community_display_name') && $tenant) {
                $tenantName = community_display_name($tenant);
            }
            $display = trim((string) ($user['display_name'] ?? ''));
            if ($display === '') {
                $display = trim((string) ($user['callsign'] ?? ''));
            }
            if ($display === '') {
                $display = $email;
            }
            $this->emailService->sendTrainingSelfEnrollDeclined(
                $email,
                $display,
                $tenantName,
                (string) ($course['title'] ?? 'Formation'),
                $tenantId
            );
        } catch (\Throwable) {
        }
    }
}
