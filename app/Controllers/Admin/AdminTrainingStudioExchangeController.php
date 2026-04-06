<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TrainingCourseRepository;
use App\Services\Platform\FeatureGateService;
use App\Services\Training\TrainingAuditService;
use App\Services\Training\TrainingCourseExchangeService;

class AdminTrainingStudioExchangeController
{
    public function __construct(
        private TrainingCourseExchangeService $exchangeService,
        private TrainingCourseRepository $courseRepository,
        private TrainingAuditService $auditService,
        private FeatureGateService $featureGate,
    ) {}

    /** Page d’import sans formation sélectionnée (nouvelle formation uniquement). */
    public function importerForm(Request $request, array $params = []): Response
    {
        $denied = $this->assertTrainingFeatureAndAccess();
        if ($denied !== null) {
            return $denied;
        }
        if (!$this->canImportNew()) {
            return $this->forbidden();
        }
        $tenantId = (int) Session::get('tenant_id');
        $courseCount = count($this->courseRepository->listForTenant($tenantId, null));

        return Response::view('layout.training_studio', [
            'content' => 'admin.training.studio_echange',
            'title' => 'Importer une formation',
            'trainingStudioMode' => 'index',
            'trainingStudioCourseCount' => $courseCount,
            'trainingStudioCourse' => null,
            'trainingStudioShowIntro' => false,
            'echangeCourse' => null,
            'echangeJsonPretty' => '',
            'echangeCanReplace' => false,
            'echangeReplaceCourseId' => null,
            'lmsPlatformVersion' => function_exists('lms_platform_version') ? lms_platform_version() : '',
            'lmsChangelogUrl' => training_studio_url('versions'),
        ]);
    }

    public function exchange(Request $request, array $params = []): Response
    {
        $denied = $this->assertTrainingFeatureAndAccess();
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $courseId = (int) ($params['id'] ?? 0);
        $course = $this->courseRepository->findById($courseId, $tenantId);
        if (!$course) {
            Session::flash('error', 'Formation introuvable.');

            return Response::redirect(training_studio_url());
        }
        $doc = $this->exchangeService->buildExportDocument($courseId, $tenantId);
        $jsonPretty = json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($jsonPretty === false) {
            $jsonPretty = '{}';
        }
        $allCourses = $this->courseRepository->listForTenant($tenantId, null);
        $modCount = count($doc['modules'] ?? []);
        $lesCount = 0;
        foreach ($doc['modules'] ?? [] as $m) {
            $lesCount += count($m['lessons'] ?? []);
        }

        return Response::view('layout.training_studio', [
            'content' => 'admin.training.studio_echange',
            'title' => 'Exporter ou importer — ' . (string) $course['title'],
            'trainingStudioMode' => 'edit',
            'trainingStudioCourseCount' => count($allCourses),
            'trainingStudioCourse' => [
                'id' => $courseId,
                'title' => (string) ($course['title'] ?? ''),
                'slug' => (string) ($course['slug'] ?? ''),
                'visibility' => (string) ($course['visibility'] ?? 'draft'),
                'module_count' => $modCount,
                'lesson_count' => $lesCount,
            ],
            'trainingStudioShowIntro' => false,
            'echangeCourse' => $course,
            'echangeJsonPretty' => $jsonPretty,
            'echangeCanReplace' => $this->canReplaceStructure(),
            'echangeReplaceCourseId' => $courseId,
            'lmsPlatformVersion' => function_exists('lms_platform_version') ? lms_platform_version() : '',
            'lmsChangelogUrl' => training_studio_url('versions'),
        ]);
    }

    public function exportDownload(Request $request, array $params = []): Response
    {
        $denied = $this->assertTrainingFeatureAndAccess();
        if ($denied !== null) {
            return $denied;
        }
        if (!$this->canExport()) {
            return $this->forbidden();
        }
        $tenantId = (int) Session::get('tenant_id');
        $courseId = (int) ($params['id'] ?? 0);
        $course = $this->courseRepository->findById($courseId, $tenantId);
        if (!$course) {
            Session::flash('error', 'Formation introuvable.');

            return Response::redirect(training_studio_url());
        }
        $doc = $this->exchangeService->buildExportDocument($courseId, $tenantId);
        $body = json_encode($doc, JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            Session::flash('error', 'Export impossible (encodage du contenu).');

            return Response::redirect(training_studio_url($courseId . '/echange'));
        }
        $slug = (string) ($course['slug'] ?? 'formation');
        $slug = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $slug) ?: 'formation';

        return (new Response())
            ->setStatusCode(200)
            ->header('Content-Type', 'application/json; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="formation-' . $slug . '.json"')
            ->setBody($body);
    }

    public function importSubmit(Request $request, array $params = []): Response
    {
        $denied = $this->assertTrainingFeatureAndAccess();
        if ($denied !== null) {
            return $denied;
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée, réessayez.');

            return Response::redirect($this->importRedirectUrl($request));
        }
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        $format = (string) $request->input('exchange_format', 'json');
        if (!in_array($format, ['json', 'html'], true)) {
            $format = 'json';
        }
        $raw = trim((string) $request->input('exchange_payload', ''));
        if ($raw === '' && isset($_FILES['exchange_file']) && is_array($_FILES['exchange_file'])
            && (int) ($_FILES['exchange_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $tmp = (string) ($_FILES['exchange_file']['tmp_name'] ?? '');
            if ($tmp !== '' && is_readable($tmp)) {
                $raw = (string) file_get_contents($tmp);
            }
        }
        $importMode = (string) $request->input('import_mode', 'new');
        $replaceId = (int) $request->input('replace_course_id', 0);
        $confirmReplace = (string) $request->input('confirm_replace_structure', '') === '1';
        $canPublish = $this->canPublish();

        try {
            if ($format === 'html') {
                if (!$this->canImportNew()) {
                    return $this->forbidden();
                }
                $fallbackTitle = trim((string) $request->input('html_course_title', ''));
                $document = $this->exchangeService->parseHtmlToDocument($raw, $fallbackTitle);
                $result = $this->exchangeService->importDocument(
                    $document,
                    $tenantId,
                    $userId,
                    false,
                    null,
                    $canPublish
                );
            } else {
                $data = TrainingCourseExchangeService::decodeJsonPayload($raw);
                if ($importMode === 'replace') {
                    if (!$this->canReplaceStructure()) {
                        return $this->forbidden();
                    }
                    if (!$confirmReplace) {
                        throw new \InvalidArgumentException('Cochez la confirmation pour remplacer la structure de cette formation.');
                    }
                    $targetId = $replaceId > 0 ? $replaceId : 0;
                    $existing = $this->courseRepository->findById($targetId, $tenantId);
                    if (!$existing) {
                        throw new \InvalidArgumentException('Formation cible introuvable.');
                    }
                    $result = $this->exchangeService->importDocument(
                        $data,
                        $tenantId,
                        $userId,
                        true,
                        $targetId,
                        $canPublish
                    );
                    $this->auditService->logCourseUpdated($tenantId, $userId, $targetId, null, [
                        'structure_reimported' => true,
                    ]);
                } else {
                    if (!$this->canImportNew()) {
                        return $this->forbidden();
                    }
                    $result = $this->exchangeService->importDocument(
                        $data,
                        $tenantId,
                        $userId,
                        false,
                        null,
                        $canPublish
                    );
                    $createdRow = $this->courseRepository->findById($result['course_id'], $tenantId);
                    $this->auditService->logCourseCreated($tenantId, $userId, $result['course_id'], [
                        'title' => (string) ($createdRow['title'] ?? ''),
                        'slug' => (string) ($createdRow['slug'] ?? ''),
                        'imported' => true,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());

            return Response::redirect($this->importRedirectUrl($request, $replaceId));
        }

        $warnings = $result['warnings'] ?? [];
        $msg = 'Formation importée.';
        if ($warnings !== []) {
            $msg .= ' Avertissements : ' . implode(' ', array_slice($warnings, 0, 5));
            if (count($warnings) > 5) {
                $msg .= ' (…)';
            }
        }
        Session::flash('success', $msg);

        return Response::redirect(training_studio_url((string) ((int) $result['course_id']) . '/fiche'));
    }

    private function importRedirectUrl(Request $request, int $replaceId = 0): string
    {
        $from = (string) $request->input('_redirect_from', '');
        if ($from === 'exchange' && $replaceId > 0) {
            return training_studio_url($replaceId . '/echange');
        }
        if ($from === 'importer') {
            return url(training_studio_path() . '/echange/importer');
        }

        return training_studio_url();
    }

    private function assertTrainingFeatureAndAccess(): ?Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $tenantId = (int) $tenantId;
        if (!$this->featureGate->allows($tenantId, 'training')) {
            return Response::view('layout.main', [
                'title' => 'Studio LMS',
                'content' => 'platform.upgrade',
                'feature' => 'training',
                'planName' => 'standard',
            ]);
        }
        if (!$this->hasTrainingAccess()) {
            $r = Response::view('layout.main', [
                'title' => 'Accès refusé — Studio LMS',
                'content' => 'admin.training.access_denied',
            ]);

            return $r->setStatusCode(403);
        }

        return null;
    }

    private function hasTrainingAccess(): bool
    {
        $gate = Gate::getInstance();

        return $gate->allows('admin.access') || $gate->allows('training.manage')
            || $gate->allows('training.assign')
            || $gate->allows('training.create') || $gate->allows('training.update')
            || $gate->allows('training.delete') || $gate->allows('training.publish');
    }

    /** Même périmètre que l’accès Studio : toute personne habilitée à ouvrir le Studio peut récupérer une sauvegarde JSON du parcours. */
    private function canExport(): bool
    {
        return $this->hasTrainingAccess();
    }

    private function canImportNew(): bool
    {
        $gate = Gate::getInstance();

        return $gate->allows('admin.access') || $gate->allows('training.manage')
            || $gate->allows('training.create');
    }

    private function canReplaceStructure(): bool
    {
        $gate = Gate::getInstance();

        return $gate->allows('admin.access') || $gate->allows('training.manage')
            || $gate->allows('training.update');
    }

    private function canPublish(): bool
    {
        $gate = Gate::getInstance();

        return $gate->allows('admin.access') || $gate->allows('training.manage')
            || $gate->allows('training.publish');
    }

    private function forbidden(): Response
    {
        $r = Response::view('layout.main', [
            'title' => 'Accès refusé',
            'content' => 'admin.training.access_denied',
        ]);

        return $r->setStatusCode(403);
    }
}
