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
use App\Repositories\TrainingCertificateTemplateRepository;
use App\Repositories\TrainingLessonFeedbackRepository;
use App\Repositories\UserNotificationPreferencesRepository;
use App\Repositories\UserRepository;
use App\Services\Email\EmailEvents;
use App\Services\EmailService;
use App\Services\Platform\FeatureGateService;
use App\Services\Training\TrainingAuditService;
use App\Services\Training\TrainingAssignmentService;
use App\Services\Training\TrainingCourseExchangeService;
use App\Services\Training\TrainingEnrollmentPolicyService;
use App\Services\Training\TrainingProgressService;
use App\Services\Training\TrainingCertificateService;
use App\Services\Training\TrainingCertificatePdfService;
use App\Services\Training\TrainingCertificateAssetStorageService;
use App\Support\TrainingCertificatePdfEngine;
use App\Support\TrainingLmsStaffAccess;

class AdminTrainingController
{
    public function __construct(
        private TrainingCourseRepository $courseRepository,
        private TrainingEnrollmentRepository $enrollmentRepository,
        private TrainingCertificateRepository $certificateRepository,
        private TrainingCertificateTemplateRepository $certificateTemplateRepository,
        private TrainingCertificateAssetStorageService $certificateAssetStorage,
        private TrainingAssignmentService $assignmentService,
        private TrainingAuditService $auditService,
        private TenantRepository $tenantRepository,
        private TrainingProgressService $progressService,
        private EmailService $emailService,
        private UserRepository $userRepository,
        private TrainingEnrollmentPolicyService $enrollmentPolicyService,
        private TrainingCourseExchangeService $courseExchangeService,
        private FeatureGateService $featureGate,
        private UserNotificationPreferencesRepository $notificationPreferencesRepository,
        private TrainingCertificateService $certificateService,
        private TrainingCertificatePdfService $certificatePdfService,
        private TrainingLessonFeedbackRepository $lessonFeedbackRepository,
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
        return Response::view('layout.training_lms_staff_shell', [
            'content' => 'admin.training.dashboard_body',
            'title' => 'Pilotage des formations',
            'trainingAdminNav' => 'dashboard',
            'totalModules' => count($courses),
            'stats' => [
                'courses' => count($courses),
                'enrollments' => $totalEnrollments,
                'completed' => $totalEnrollments > 0 ? round(100.0 * $completed / $totalEnrollments, 1) : 0,
                'expiringCount' => count($expiring),
            ],
            'expiring' => $expiring,
            'trainingCanExportFull' => $this->userCanExportFullCourse(),
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
            'trainingCanExportFull' => $this->userCanExportFullCourse(),
            'trainingCanDeleteCourse' => $this->userCanDeleteTrainingCourse(),
            'trainingCanEditShowcaseOrCatalog' => $this->userCanManageTrainingCourseEditorially(),
        ]);
    }

    /** Retrait du catalogue public : passage en visibilité « privé » (le contenu reste dans le studio). */
    public function courseUnpublish(Request $request, array $params = []): Response
    {
        $this->requireTrainingAccess();
        if (!$this->userCanManageTrainingCourseEditorially()) {
            throw new \RuntimeException('Accès refusé.', 403);
        }
        if (!$request->isPost()) {
            return Response::redirect(training_lms_admin_url('courses'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(training_lms_admin_url('courses'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        $courseId = (int) ($params['id'] ?? 0);
        $course = $this->courseRepository->findById($courseId, $tenantId);
        if (!$course) {
            Session::flash('error', 'Formation introuvable.');

            return Response::redirect(training_lms_admin_url('courses'));
        }
        if ((string) ($course['visibility'] ?? '') !== 'published') {
            Session::flash('error', 'Cette formation n’était pas visible sur le catalogue public.');

            return Response::redirect(training_lms_admin_url('courses'));
        }
        $this->courseRepository->update($courseId, [
            'visibility' => 'private',
            'updated_by' => $userId > 0 ? $userId : null,
        ]);
        $this->auditService->logCourseUpdated($tenantId, $userId, $courseId, ['visibility' => 'published'], ['visibility' => 'private']);
        Session::flash('success', 'La formation a été retirée du catalogue. Elle reste accessible depuis le studio et l’administration.');

        return Response::redirect(training_lms_admin_url('courses'));
    }

    /** Suppression définitive du parcours et des données associées (inscriptions, progression, etc.). */
    public function courseDelete(Request $request, array $params = []): Response
    {
        $this->requireTrainingAccess();
        if (!$this->userCanDeleteTrainingCourse()) {
            throw new \RuntimeException('Accès refusé.', 403);
        }
        if (!$request->isPost()) {
            return Response::redirect(training_lms_admin_url('courses'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(training_lms_admin_url('courses'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        $courseId = (int) ($params['id'] ?? 0);
        $course = $this->courseRepository->findById($courseId, $tenantId);
        if (!$course) {
            Session::flash('error', 'Formation introuvable.');

            return Response::redirect(training_lms_admin_url('courses'));
        }
        $snapshot = [
            'title' => (string) ($course['title'] ?? ''),
            'slug' => (string) ($course['slug'] ?? ''),
            'visibility' => (string) ($course['visibility'] ?? ''),
        ];
        $ok = $this->courseRepository->deleteByIdForTenant($courseId, $tenantId);
        if (!$ok) {
            Session::flash('error', 'Suppression impossible.');

            return Response::redirect(training_lms_admin_url('courses'));
        }
        $this->auditService->logCourseDeleted($tenantId, $userId, $courseId, $snapshot);
        Session::flash('success', 'La formation et les données liées (inscriptions, contenu) ont été supprimées.');

        return Response::redirect(training_lms_admin_url('courses'));
    }

    /**
     * Export JSON complet d’une formation (même format que l’échange Studio) — sauvegarde / transfert.
     */
    public function exportCourse(Request $request, array $params = []): Response
    {
        $this->requireTrainingAccess();
        $tenantId = (int) Session::get('tenant_id');
        if (!$this->featureGate->allows($tenantId, 'training')) {
            return Response::view('layout.main', [
                'title' => 'Formations',
                'content' => 'platform.upgrade',
                'feature' => 'training',
                'planName' => 'standard',
            ]);
        }
        if (!$this->userCanExportFullCourse()) {
            throw new \RuntimeException('Accès refusé.', 403);
        }
        $courseId = (int) ($params['id'] ?? 0);
        $course = $this->courseRepository->findById($courseId, $tenantId);
        if (!$course) {
            Session::flash('error', 'Formation introuvable.');

            return Response::redirect(training_lms_admin_url('courses'));
        }
        $doc = $this->courseExchangeService->buildExportDocument($courseId, $tenantId);
        $body = json_encode($doc, JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            Session::flash('error', 'Export impossible : le contenu n’a pas pu être encodé.');

            return Response::redirect(training_lms_admin_url('courses'));
        }
        $slug = (string) ($course['slug'] ?? 'formation');
        $slug = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $slug) ?: 'formation';

        return (new Response())
            ->setStatusCode(200)
            ->header('Content-Type', 'application/json; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="formation-' . $slug . '.json"')
            ->setBody($body);
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
        $redirect = Response::redirect(training_lms_admin_url('enrollments'));
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

            return Response::redirect(training_lms_admin_url('enrollments') . '?course_id=' . $courseId);
        }
        $this->auditService->logEnrollmentAssigned($tenantId, $actorId, $enrollmentId, [
            'user_id' => $userId,
            'course_id' => $courseId,
            'assignment_type' => 'self_enroll',
            'approved_from_pending' => true,
        ]);
        $this->notifyLearnerSelfEnrollApproved($tenantId, $userId, $course);
        Session::flash('success', 'Inscription validée. L’apprenant peut commencer le parcours.');

        return Response::redirect(training_lms_admin_url('enrollments') . '?course_id=' . $courseId);
    }

    public function declineEnrollment(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $actorId = (int) Session::get('user_id');
        $enrollmentId = (int) ($params['id'] ?? 0);
        $redirect = Response::redirect(training_lms_admin_url('enrollments'));
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

        return Response::redirect(training_lms_admin_url('enrollments') . '?course_id=' . $courseId);
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

    public function lessonFeedback(Request $request, array $params = []): Response
    {
        $this->requireTrainingAccess();
        $tenantId = (int) Session::get('tenant_id');
        $courseId = (int) $request->query('course_id', 0);
        $courses = $this->courseRepository->listForTenant($tenantId, null);
        $feedbackRows = $this->lessonFeedbackRepository->listRecentForTenant($tenantId, $courseId > 0 ? $courseId : null, 200);
        $stats = $this->lessonFeedbackRepository->aggregateForTenant($tenantId, $courseId > 0 ? $courseId : null);

        return Response::view('layout.main', [
            'content' => 'admin.training.lesson_feedback',
            'title' => 'Feedback post-leçon',
            'trainingAdminNav' => 'lesson_feedback',
            'courses' => $courses,
            'selectedCourseId' => $courseId,
            'lessonFeedbackRows' => $feedbackRows,
            'lessonFeedbackStats' => $stats,
        ]);
    }

    public function certificates(Request $request, array $params = []): Response
    {
        $this->requireTrainingAccess();
        $tenantId = (int) Session::get('tenant_id');
        $certificates = $this->certificateRepository->listForTenantAdmin($tenantId, 200);
        $pdfReady = TrainingCertificatePdfEngine::isAvailable();
        $pendingPdf = 0;
        foreach ($certificates as $c) {
            if (($c['status'] ?? '') !== 'valid') {
                continue;
            }
            $rel = trim((string) ($c['pdf_path'] ?? ''));
            if ($rel === '' || !is_file(base_path($rel))) {
                $pendingPdf++;
            }
        }

        return Response::view('layout.main', [
            'content' => 'admin.training.certificates',
            'title' => 'Certificats',
            'trainingAdminNav' => 'certificates',
            'certificates' => $certificates,
            'trainingCertificatesPdfReady' => $pdfReady,
            'trainingCertificatesPendingPdf' => $pendingPdf,
        ]);
    }

    public function certificatesGeneratePendingPdfs(Request $request, array $params = []): Response
    {
        $this->requireTrainingAccess();
        $redirect = Response::redirect(training_lms_admin_url('certificates'));
        if (!$request->isPost()) {
            return $redirect;
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return $redirect;
        }
        $tenantId = (int) Session::get('tenant_id');
        if (!TrainingCertificatePdfEngine::isAvailable()) {
            Session::flash('error', 'La génération des documents PDF n’est pas disponible sur ce serveur. Contactez l’équipe technique.');

            return $redirect;
        }
        $n = $this->certificateService->backfillPendingPdfDocuments($tenantId, 80);
        if ($n > 0) {
            Session::flash('success', $n === 1
                ? 'Un document PDF a été généré.'
                : $n . ' documents PDF ont été générés.');
        } else {
            Session::flash('info', 'Aucun PDF en attente : la file est vide ou les attestations sont déjà à jour.');
        }

        return $redirect;
    }

    public function certificateGabarit(Request $request, array $params = []): Response
    {
        $this->requireTrainingAccess();
        if (!$this->userCanManageTrainingCourseEditorially()) {
            throw new \RuntimeException('Accès refusé.', 403);
        }
        $tenantId = (int) Session::get('tenant_id');
        $tpl = $this->certificateTemplateRepository->findByTenantId($tenantId) ?? [];
        $layoutFlags = $this->certificateTemplateLayoutFlags($tpl);

        return Response::view('layout.main', [
            'content' => 'admin.training.certificates_gabarit',
            'title' => 'Gabarit des attestations',
            'trainingAdminNav' => 'certificates_gabarit',
            'tpl' => $tpl,
            'trainingCertificatePdfAvailable' => TrainingCertificatePdfEngine::isAvailable(),
            'certLayoutShowFinalScore' => $layoutFlags['show_final_score'],
            'certLayoutShowValidUntil' => $layoutFlags['show_valid_until'],
        ]);
    }

    public function certificateGabaritSave(Request $request, array $params = []): Response
    {
        $this->requireTrainingAccess();
        if (!$this->userCanManageTrainingCourseEditorially()) {
            throw new \RuntimeException('Accès refusé.', 403);
        }
        $redirect = Response::redirect(training_lms_admin_url('certificates/gabarit'));
        if ($request->method() !== 'POST') {
            return $redirect;
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée, réessayez.');

            return $redirect;
        }
        $tenantId = (int) Session::get('tenant_id');
        $existing = $this->certificateTemplateRepository->findByTenantId($tenantId);
        $logoRel = $existing['logo_relative_path'] ?? null;
        $bgRel = $existing['background_relative_path'] ?? null;

        try {
            $logoFile = $_FILES['logo'] ?? null;
            if (is_array($logoFile) && ($logoFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $stored = $this->certificateAssetStorage->storeUpload($tenantId, $logoFile, 'logo');
                if ($stored !== null) {
                    $this->certificateAssetStorage->deleteRelative(is_string($logoRel) ? $logoRel : null);
                    $logoRel = $stored;
                }
            }
            if ($request->input('remove_logo')) {
                $this->certificateAssetStorage->deleteRelative(is_string($logoRel) ? $logoRel : null);
                $logoRel = null;
            }

            $bgFile = $_FILES['background'] ?? null;
            if (is_array($bgFile) && ($bgFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $stored = $this->certificateAssetStorage->storeUpload($tenantId, $bgFile, 'fond');
                if ($stored !== null) {
                    $this->certificateAssetStorage->deleteRelative(is_string($bgRel) ? $bgRel : null);
                    $bgRel = $stored;
                }
            }
            if ($request->input('remove_background')) {
                $this->certificateAssetStorage->deleteRelative(is_string($bgRel) ? $bgRel : null);
                $bgRel = null;
            }

            $layoutJson = json_encode([
                'show_final_score' => (string) $request->input('layout_show_final_score', '0') === '1',
                'show_valid_until' => (string) $request->input('layout_show_valid_until', '0') === '1',
            ], JSON_UNESCAPED_UNICODE);
            $this->certificateTemplateRepository->upsertForTenant($tenantId, [
                'name' => (string) $request->input('name', 'Modèle par défaut'),
                'headline' => (string) $request->input('headline', 'Attestation de formation'),
                'subtitle' => (string) $request->input('subtitle', ''),
                'footer_legal' => (string) $request->input('footer_legal', ''),
                'primary_hex' => (string) $request->input('primary_hex', '#0f172a'),
                'accent_hex' => (string) $request->input('accent_hex', '#059669'),
                'logo_relative_path' => is_string($logoRel) ? $logoRel : null,
                'background_relative_path' => is_string($bgRel) ? $bgRel : null,
                'layout_json' => $layoutJson,
            ]);
            Session::flash('success', 'Le gabarit a été enregistré. Les prochaines attestations générées utiliseront ces réglages.');
        } catch (\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
        } catch (\Throwable) {
            Session::flash('error', 'Impossible d’enregistrer le gabarit. Réessayez plus tard.');
        }

        return $redirect;
    }

    public function certificateGabaritExamplePdf(Request $request, array $params = []): Response
    {
        $this->requireTrainingAccess();
        if (!$this->userCanManageTrainingCourseEditorially()) {
            throw new \RuntimeException('Accès refusé.', 403);
        }
        $tenantId = (int) Session::get('tenant_id');
        $binary = $this->certificatePdfService->generatePreviewBinary($tenantId);
        if ($binary === null || $binary === '') {
            Session::flash('error', 'La génération d’un document d’exemple n’est pas disponible sur ce serveur.');
            return Response::redirect(training_lms_admin_url('certificates/gabarit'));
        }

        return (new Response())
            ->setStatusCode(200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="attestation-exemple.pdf"')
            ->setBody($binary);
    }

    public function certificateGabaritFile(Request $request, array $params = []): Response
    {
        $this->requireTrainingAccess();
        if (!$this->userCanManageTrainingCourseEditorially()) {
            throw new \RuntimeException('Accès refusé.', 403);
        }
        $type = (string) $request->query('type', '');
        if ($type !== 'logo' && $type !== 'fond') {
            return (new Response())->setStatusCode(404)->setBody('Fichier introuvable.');
        }
        $tenantId = (int) Session::get('tenant_id');
        $tpl = $this->certificateTemplateRepository->findByTenantId($tenantId);
        if (!$tpl) {
            return (new Response())->setStatusCode(404)->setBody('Fichier introuvable.');
        }
        $rel = $type === 'logo'
            ? ($tpl['logo_relative_path'] ?? null)
            : ($tpl['background_relative_path'] ?? null);
        if (!is_string($rel) || $rel === '') {
            return (new Response())->setStatusCode(404)->setBody('Fichier introuvable.');
        }
        $abs = $this->certificateAssetStorage->absolutePath($rel);
        if ($abs === null || !is_file($abs)) {
            return (new Response())->setStatusCode(404)->setBody('Fichier introuvable.');
        }
        $data = @file_get_contents($abs);
        if ($data === false) {
            return (new Response())->setStatusCode(404)->setBody('Fichier introuvable.');
        }
        $mime = 'application/octet-stream';
        if (function_exists('finfo_open')) {
            $fi = finfo_open(FILEINFO_MIME_TYPE);
            if ($fi !== false) {
                $m = finfo_file($fi, $abs);
                finfo_close($fi);
                if (is_string($m) && $m !== '') {
                    $mime = $m;
                }
            }
        }

        return (new Response())
            ->setStatusCode(200)
            ->header('Content-Type', $mime)
            ->header('Cache-Control', 'private, max-age=120')
            ->setBody($data);
    }

    public function audit(Request $request, array $params = []): Response
    {
        $this->requireTrainingAccess();
        $tenantId = (int) Session::get('tenant_id');
        $logs = $this->auditService->listLogsForTenantDisplay($tenantId, 200);
        return Response::view('layout.main', [
            'content' => 'admin.training.audit',
            'title' => 'Journal des formations',
            'trainingAdminNav' => 'audit',
            'logs' => $logs,
        ]);
    }

    /** Personnalisation vitrine (dashboard public / cartes). */
    public function courseShowcase(Request $request, array $params = []): Response
    {
        $this->requireTrainingAccess();
        if (!$this->userCanManageTrainingCourseEditorially()) {
            throw new \RuntimeException('Accès refusé.', 403);
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        $course = $this->courseRepository->findById($id, $tenantId);
        if (!$course) {
            Session::flash('error', 'Formation introuvable.');

            return Response::redirect(training_lms_admin_url('courses'));
        }

        if ($request->method() === 'POST') {
            if (!Csrf::validate($request->input('_csrf_token'))) {
                Session::flash('error', 'Session expirée, réessayez.');

                return Response::redirect(training_lms_admin_url('courses/' . $id . '/showcase'));
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

            return Response::redirect(training_lms_admin_url('courses/' . $id . '/showcase'));
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

    /** Même périmètre que l’export Studio : pas d’export pour le seul rôle « assignation ». */
    private function userCanExportFullCourse(): bool
    {
        $gate = Gate::getInstance();

        return $gate->allows('admin.access') || $gate->allows('training.manage')
            || $gate->allows('training.create') || $gate->allows('training.update');
    }

    private function userCanDeleteTrainingCourse(): bool
    {
        $gate = Gate::getInstance();

        return $gate->allows('admin.access') || $gate->allows('training.manage') || $gate->allows('training.delete');
    }

    /**
     * Accès au shell Training Command (tenant) : aligné sur {@see AdminTrainingStudioController::hasTrainingAccess()}
     * — inclut training.assign (vue communauté, pas administration site).
     */
    private function requireTrainingAccess(): void
    {
        if (TrainingLmsStaffAccess::allows(Gate::getInstance())) {
            return;
        }
        throw new \RuntimeException('Accès refusé.', 403);
    }

    /**
     * Édition contenu / vitrine / gabarit attestations : hors seul rôle « assignation ».
     */
    private function userCanManageTrainingCourseEditorially(): bool
    {
        $gate = Gate::getInstance();

        return $gate->allows('admin.access') || $gate->allows('training.manage')
            || $gate->allows('training.create') || $gate->allows('training.update')
            || $gate->allows('training.delete') || $gate->allows('training.publish');
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
            if ($this->notificationPreferencesRepository->isEmailEventEnabled($userId, EmailEvents::TRAINING_SELF_ENROLL_APPROVED)) {
                $this->emailService->sendTrainingSelfEnrollApproved(
                    $email,
                    $display,
                    $tenantName,
                    (string) ($course['title'] ?? 'Formation'),
                    $courseUrl,
                    $tenantId
                );
            }
        } catch (\Throwable) {
        }
    }

    /**
     * @param array<string, mixed> $tplRow
     * @return array{show_final_score: bool, show_valid_until: bool}
     */
    private function certificateTemplateLayoutFlags(array $tplRow): array
    {
        $defaults = ['show_final_score' => true, 'show_valid_until' => true];
        $raw = $tplRow['layout_json'] ?? null;
        if ($raw === null || $raw === '') {
            return $defaults;
        }
        $j = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($j)) {
            return $defaults;
        }

        return [
            'show_final_score' => array_key_exists('show_final_score', $j) ? (bool) $j['show_final_score'] : $defaults['show_final_score'],
            'show_valid_until' => array_key_exists('show_valid_until', $j) ? (bool) $j['show_valid_until'] : $defaults['show_valid_until'],
        ];
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
            if ($this->notificationPreferencesRepository->isEmailEventEnabled($userId, EmailEvents::TRAINING_SELF_ENROLL_DECLINED)) {
                $this->emailService->sendTrainingSelfEnrollDeclined(
                    $email,
                    $display,
                    $tenantName,
                    (string) ($course['title'] ?? 'Formation'),
                    $tenantId
                );
            }
        } catch (\Throwable) {
        }
    }
}
