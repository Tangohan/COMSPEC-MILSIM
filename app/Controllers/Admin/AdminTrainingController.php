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
use App\Services\Training\TrainingCourseMediaUploadService;
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
        private TrainingCourseMediaUploadService $courseMediaUploadService,
    ) {}

    public function dashboard(Request $request, array $params = []): Response
    {
        $this->requireTrainingAccess();
        $tenantId = (int) Session::get('tenant_id');
        $courses = $this->courseRepository->listForTenant($tenantId, null);
        $enrollmentCounts = $this->enrollmentRepository->countAndCompletedForTenantCourses($tenantId);
        $totalEnrollments = $enrollmentCounts['total'];
        $completed = $enrollmentCounts['completed'];
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
        $search = trim((string) $request->query('q', ''));
        $courses = $this->courseRepository->listForTenant($tenantId, null, null, $search !== '' ? $search : null);
        return Response::view('layout.training_lms_staff_shell', [
            'content' => 'admin.training.courses',
            'title' => 'Formations',
            'trainingAdminNav' => 'courses',
            'totalModules' => count($courses),
            'courses' => $courses,
            'coursesSearch' => $search,
            'trainingCanExportFull' => $this->userCanExportFullCourse(),
            'trainingCanDeleteCourse' => $this->userCanDeleteTrainingCourse(),
            'trainingCanEditShowcaseOrCatalog' => $this->userCanManageTrainingCourseEditorially(),
        ]);
    }

    /** Retrait du catalogue public : passage en visibilité « privé » (le contenu reste dans le studio). */
    public function courseUnpublish(Request $request, array $params = []): Response
    {
        $this->requireTrainingAccess();
        if ($denied = $this->denyUnlessEditorialTraining('courses')) {
            return $denied;
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
        $tenantId = (int) Session::get('tenant_id');
        $courseId = (int) ($params['id'] ?? $request->query('course_id') ?? 0);
        $filterExpiring = (int) $request->query('expiring', 0) === 1;
        $filterStatus = trim((string) $request->query('status', ''));
        if ($filterStatus !== 'pending_approval') {
            $filterStatus = '';
        }
        if (!$this->userCanViewEnrollmentsScreen($courseId)) {
            throw new \RuntimeException('Accès refusé.', 403);
        }

        $enrollments = [];
        if ($courseId > 0) {
            $enrollments = $this->enrollmentRepository->listByCourseId($courseId);
            if ($filterExpiring) {
                $limitTs = time() + (30 * 86400);
                $enrollments = array_values(array_filter(
                    $enrollments,
                    static function (array $e) use ($limitTs): bool {
                        $st = (string) ($e['status'] ?? '');
                        if (!in_array($st, ['assigned', 'in_progress'], true)) {
                            return false;
                        }
                        $exp = (string) ($e['expires_at'] ?? '');
                        if ($exp === '') {
                            return false;
                        }
                        $ts = strtotime($exp);

                        return $ts !== false && $ts <= $limitTs;
                    }
                ));
            } elseif ($filterStatus === 'pending_approval') {
                $enrollments = array_values(array_filter(
                    $enrollments,
                    static fn (array $e): bool => ($e['status'] ?? '') === 'pending_approval'
                ));
            }
        } elseif ($filterExpiring) {
            $enrollments = $this->assignmentService->listOverdueOrExpiring($tenantId, 30);
        } elseif ($filterStatus === 'pending_approval') {
            $enrollments = $this->enrollmentRepository->listPendingApproval($tenantId);
        }

        $courses = $this->courseRepository->listForTenant($tenantId, null);
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

        $expiringPreview = $this->assignmentService->listOverdueOrExpiring($tenantId, 30);
        $pendingPreview = $this->enrollmentRepository->listPendingApproval($tenantId);

        return Response::view('layout.training_lms_staff_shell', [
            'content' => 'admin.training.enrollments',
            'title' => 'Assignations',
            'trainingAdminNav' => 'enrollments',
            'totalModules' => count($courses),
            'enrollments' => $enrollments,
            'courses' => $courses,
            'selectedCourseId' => $courseId,
            'trainingEnrollmentApprovalRights' => $approvalRights,
            'enrollmentFilterExpiring' => $filterExpiring,
            'enrollmentFilterStatus' => $filterStatus,
            'enrollmentExpiringCount' => count($expiringPreview),
            'enrollmentPendingCount' => count($pendingPreview),
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
        return Response::view('layout.training_lms_staff_shell', [
            'content' => 'admin.training.reports',
            'title' => 'Rapports',
            'trainingAdminNav' => 'reports',
            'totalModules' => count($courses),
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

        return Response::view('layout.training_lms_staff_shell', [
            'content' => 'admin.training.lesson_feedback',
            'title' => 'Feedback post-leçon',
            'trainingAdminNav' => 'lesson_feedback',
            'totalModules' => count($courses),
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
        $search = trim((string) $request->query('q', ''));
        $certificates = $this->certificateRepository->listForTenantAdmin($tenantId, 200, $search !== '' ? $search : null);
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

        return Response::view('layout.training_lms_staff_shell', [
            'content' => 'admin.training.certificates',
            'title' => 'Certificats',
            'trainingAdminNav' => 'certificates',
            'totalModules' => $this->trainingShellTotalModules($tenantId),
            'certificates' => $certificates,
            'certificatesSearch' => $search,
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
            Session::flash(
                'error',
                TrainingCertificatePdfEngine::staffUnavailabilityHint()
                    ?? 'La génération des documents PDF n’est pas disponible sur ce serveur. Contactez l’équipe technique.'
            );

            return $redirect;
        }
        $pendingBefore = $this->certificateService->countPendingPdfDocuments($tenantId);
        $result = $this->certificateService->backfillPendingPdfDocuments($tenantId, 80);
        $generated = $result['generated'];
        $failed = $result['failed'];
        $remaining = $result['remaining'];

        if ($generated > 0 && $failed === 0) {
            Session::flash('success', $generated === 1
                ? 'Un document a été généré.'
                : $generated . ' documents ont été générés.');
        } elseif ($generated > 0 && $failed > 0) {
            Session::flash(
                'warning',
                $generated . ' document(s) généré(s), mais ' . $failed . ' échec(s). '
                    . TrainingCertificatePdfEngine::staffGenerationFailureHint(
                        $this->certificatePdfService->getLastFailureReason()
                    )
            );
        } elseif ($failed > 0) {
            Session::flash(
                'error',
                $failed . ' document(s) n’ont pas pu être générés. '
                    . TrainingCertificatePdfEngine::staffGenerationFailureHint(
                        $this->certificatePdfService->getLastFailureReason()
                    )
            );
        } elseif ($pendingBefore > 0) {
            Session::flash('info', 'Aucun document en attente n’a été trouvé lors du traitement (peut-être déjà à jour).');
        } else {
            Session::flash('info', 'Aucun document en attente : tout est déjà à jour.');
        }

        if ($remaining > 0) {
            Session::flash(
                'info',
                $remaining . ' attestation(s) supplémentaire(s) restent en attente ; relancez l’action pour les traiter.'
            );
        }

        return $redirect;
    }

    public function certificateRegenerateOne(Request $request, array $params = []): Response
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
        $certId = (int) ($params['id'] ?? 0);
        $cert = $this->certificateRepository->findById($certId, $tenantId);
        if (!$cert) {
            Session::flash('error', 'Attestation introuvable.');

            return $redirect;
        }
        if (($cert['status'] ?? '') !== 'valid') {
            Session::flash('error', 'Seules les attestations encore valides peuvent recevoir un document.');

            return $redirect;
        }
        if (!TrainingCertificatePdfEngine::isAvailable()) {
            Session::flash(
                'error',
                TrainingCertificatePdfEngine::staffUnavailabilityHint()
                    ?? 'La génération des documents PDF n’est pas disponible sur ce serveur. Contactez l’équipe technique.'
            );

            return $redirect;
        }
        $relExisting = trim((string) ($cert['pdf_path'] ?? ''));
        if ($relExisting !== '' && is_file(base_path($relExisting))) {
            @unlink(base_path($relExisting));
        }
        $path = $this->certificateService->generatePdfDocument($certId, $tenantId);
        if ($path !== null && is_file(base_path($path))) {
            Session::flash('success', 'Le document de « ' . (string) ($cert['certificate_number'] ?? $certId) . ' » a été généré.');
        } else {
            Session::flash(
                'error',
                TrainingCertificatePdfEngine::staffGenerationFailureHint(
                    $this->certificatePdfService->getLastFailureReason()
                )
            );
        }

        return $redirect;
    }

    public function certificateGabarit(Request $request, array $params = []): Response
    {
        $this->requireTrainingAccess();
        if ($denied = $this->denyUnlessEditorialTraining()) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        if ($this->pruneBrokenCertificateTemplateAssets($tenantId)) {
            Session::flash(
                'info',
                'Une image du gabarit était enregistrée mais absente du serveur ; la référence a été retirée. Réimportez le logo ou le fond si vous en avez besoin.'
            );
        }
        $tpl = $this->certificateTemplateRepository->findByTenantId($tenantId) ?? [];
        $layoutFlags = $this->certificateTemplateLayoutFlags($tpl);
        $logoRel = trim((string) ($tpl['logo_relative_path'] ?? ''));
        $fondRel = trim((string) ($tpl['background_relative_path'] ?? ''));
        $certGabaritLogoReadable = $logoRel !== '' && $this->certificateAssetStorage->absolutePath($logoRel) !== null;
        $certGabaritFondReadable = $fondRel !== '' && $this->certificateAssetStorage->absolutePath($fondRel) !== null;

        return Response::view('layout.training_lms_staff_shell', [
            'content' => 'admin.training.certificates_gabarit',
            'title' => 'Gabarit des attestations',
            'trainingAdminNav' => 'certificates_gabarit',
            'totalModules' => $this->trainingShellTotalModules($tenantId),
            'tpl' => $tpl,
            'trainingCertificatePdfAvailable' => TrainingCertificatePdfEngine::isAvailable(),
            'trainingCertificatePdfHint' => TrainingCertificatePdfEngine::staffUnavailabilityHint(),
            'certGabaritLogoReadable' => $certGabaritLogoReadable,
            'certGabaritFondReadable' => $certGabaritFondReadable,
            'certLayoutShowFinalScore' => $layoutFlags['show_final_score'],
            'certLayoutShowValidUntil' => $layoutFlags['show_valid_until'],
        ]);
    }

    public function certificateGabaritSave(Request $request, array $params = []): Response
    {
        $this->requireTrainingAccess();
        if ($denied = $this->denyUnlessEditorialTraining()) {
            return $denied;
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
        $this->pruneBrokenCertificateTemplateAssets($tenantId);
        $existing = $this->certificateTemplateRepository->findByTenantId($tenantId);
        $logoRel = is_array($existing) ? ($existing['logo_relative_path'] ?? null) : null;
        $bgRel = is_array($existing) ? ($existing['background_relative_path'] ?? null) : null;
        $logoRel = is_string($logoRel) && $logoRel !== '' ? $logoRel : null;
        $bgRel = is_string($bgRel) && $bgRel !== '' ? $bgRel : null;

        try {
            // D’abord retirer si demandé : évite d’effacer un nouveau fichier si « retirer » et « nouveau fichier » coexistent.
            if (isset($_POST['remove_logo'])) {
                $this->certificateAssetStorage->deleteRelative($logoRel);
                $logoRel = null;
            }
            if (isset($_POST['remove_background'])) {
                $this->certificateAssetStorage->deleteRelative($bgRel);
                $bgRel = null;
            }

            $logoFile = $_FILES['logo'] ?? null;
            if (is_array($logoFile) && ($logoFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $stored = $this->certificateAssetStorage->storeUpload($tenantId, $logoFile, 'logo');
                if ($stored !== null) {
                    $this->certificateAssetStorage->deleteRelative($logoRel);
                    $logoRel = $stored;
                }
            }

            $bgFile = $_FILES['background'] ?? null;
            if (is_array($bgFile) && ($bgFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $stored = $this->certificateAssetStorage->storeUpload($tenantId, $bgFile, 'fond');
                if ($stored !== null) {
                    $this->certificateAssetStorage->deleteRelative($bgRel);
                    $bgRel = $stored;
                }
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
        if ($denied = $this->denyUnlessEditorialTraining()) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        if (!TrainingCertificatePdfEngine::isAvailable()) {
            Session::flash(
                'error',
                TrainingCertificatePdfEngine::staffUnavailabilityHint()
                    ?? 'La génération des attestations PDF n’est pas prête sur ce serveur. '
                    . 'Contactez l’équipe technique pour vérifier la bibliothèque PDF et les polices associées.'
            );

            return Response::redirect(training_lms_admin_url('certificates/gabarit'));
        }

        $binary = $this->certificatePdfService->generatePreviewBinary($tenantId);
        if ($binary === null || $binary === '') {
            Session::flash('error', TrainingCertificatePdfEngine::staffGenerationFailureHint());

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
        if ($denied = $this->denyUnlessEditorialTraining()) {
            return $denied;
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
        return Response::view('layout.training_lms_staff_shell', [
            'content' => 'admin.training.audit',
            'title' => 'Journal des formations',
            'trainingAdminNav' => 'audit',
            'totalModules' => $this->trainingShellTotalModules($tenantId),
            'logs' => $logs,
        ]);
    }

    /** Personnalisation vitrine (dashboard public / cartes). */
    public function courseShowcase(Request $request, array $params = []): Response
    {
        $this->requireTrainingAccess();
        if ($denied = $this->denyUnlessEditorialTraining('courses')) {
            return $denied;
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

            try {
                $thumbnailPath = $this->resolveShowcaseMediaPath(
                    $tenantId,
                    (string) ($course['thumbnail_path'] ?? ''),
                    $_FILES['thumbnail_upload'] ?? null,
                    $request->input('thumbnail_remove', '') === '1',
                    'thumbnail'
                );
                $bannerPath = $this->resolveShowcaseMediaPath(
                    $tenantId,
                    (string) ($course['banner_path'] ?? ''),
                    $_FILES['banner_upload'] ?? null,
                    $request->input('banner_remove', '') === '1',
                    'banner'
                );
            } catch (\InvalidArgumentException|\RuntimeException $e) {
                Session::flash('error', $e->getMessage());
                return Response::redirect(training_lms_admin_url('courses/' . $id . '/showcase'));
            }

            $this->courseRepository->update($id, [
                'thumbnail_path' => $thumbnailPath,
                'banner_path' => $bannerPath,
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

        return Response::view('layout.training_lms_staff_shell', [
            'content' => 'admin.training.course_showcase',
            'title' => 'Vitrine — ' . (string) $course['title'],
            'trainingAdminNav' => 'showcase',
            'totalModules' => $this->trainingShellTotalModules($tenantId),
            'course' => $course,
            'tenant' => $tenant,
        ]);
    }

    /**
     * Détermine le chemin final (miniature ou bannière) après traitement d’un envoi de fichier éventuel :
     * priorité au retrait explicite, puis au nouveau fichier joint, sinon on conserve la valeur existante
     * (compatible avec les chemins/URLs déjà enregistrés en base).
     *
     * @param array{name?: string, type?: string, tmp_name?: string, error?: int, size?: int}|null $file
     */
    private function resolveShowcaseMediaPath(int $tenantId, string $currentPath, ?array $file, bool $remove, string $prefix): ?string
    {
        $current = trim($currentPath) ?: null;

        $uploaded = $this->courseMediaUploadService->storeUpload($tenantId, $file, $prefix);
        if ($uploaded !== null) {
            if ($current !== null) {
                $this->courseMediaUploadService->deleteManagedRelative($current);
            }

            return $uploaded;
        }

        if ($remove) {
            if ($current !== null) {
                $this->courseMediaUploadService->deleteManagedRelative($current);
            }

            return null;
        }

        return $current;
    }

    /** Même périmètre que l’export Studio : pas d’export pour le seul rôle « assignation ». */
    private function userCanExportFullCourse(): bool
    {
        $gate = Gate::getInstance();

        return $gate->allows('admin.organization') || $gate->allows('admin.access') || $gate->allows('training.manage')
            || $gate->allows('training.create') || $gate->allows('training.update');
    }

    private function userCanDeleteTrainingCourse(): bool
    {
        $gate = Gate::getInstance();

        return $gate->allows('admin.organization') || $gate->allows('admin.access') || $gate->allows('training.manage') || $gate->allows('training.delete');
    }

    /** Nombre de parcours tenant pour la sidebar du shell LMS (aligné sur le tableau de bord /formation). */
    private function trainingShellTotalModules(int $tenantId): int
    {
        return count($this->courseRepository->listForTenant($tenantId, null));
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

        return $gate->allows('admin.organization') || $gate->allows('admin.access')
            || $gate->allows('training.manage')
            || $gate->allows('training.create') || $gate->allows('training.update')
            || $gate->allows('training.delete') || $gate->allows('training.publish');
    }

    /** Refus éditorial : redirection métier (évite une page d’erreur technique). */
    private function denyUnlessEditorialTraining(string $redirectPath = 'certificates'): ?Response
    {
        if ($this->userCanManageTrainingCourseEditorially()) {
            return null;
        }
        Session::flash(
            'error',
            'Cette action est réservée aux administrateurs de la communauté ou aux responsables du contenu formation.'
        );

        return Response::redirect(training_lms_admin_url($redirectPath));
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
     * Retire des références en base les chemins vers des fichiers absents (copie de serveur, stockage non déployé, etc.).
     */
    private function pruneBrokenCertificateTemplateAssets(int $tenantId): bool
    {
        $row = $this->certificateTemplateRepository->findByTenantId($tenantId);
        if (!$row) {
            return false;
        }
        $logoP = trim((string) ($row['logo_relative_path'] ?? ''));
        $bgP = trim((string) ($row['background_relative_path'] ?? ''));
        $logoOk = $logoP === '' || $this->certificateAssetStorage->absolutePath($logoP) !== null;
        $bgOk = $bgP === '' || $this->certificateAssetStorage->absolutePath($bgP) !== null;
        if ($logoOk && $bgOk) {
            return false;
        }
        $this->certificateTemplateRepository->updateAssetRelativePaths(
            $tenantId,
            $logoOk ? ($logoP !== '' ? $logoP : null) : null,
            $bgOk ? ($bgP !== '' ? $bgP : null) : null,
        );

        return true;
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
