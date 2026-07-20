<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\GradeRepository;
use App\Repositories\RoleRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Repositories\DocumentRepository;
use App\Repositories\TrainingCourseLmsSocialRepository;
use App\Repositories\TrainingCourseRepository;
use App\Repositories\TrainingLessonRepository;
use App\Repositories\TrainingModuleRepository;
use App\Repositories\TrainingResourceRepository;
use App\Services\Platform\FeatureGateService;
use App\Services\Documents\DocumentUploadService;
use App\Repositories\PedagogyRepository;
use App\Services\Training\TrainingAuditService;
use App\Services\Training\TrainingCoursePublicationGuard;
use App\Services\Training\TrainingCourseSessionNotificationService;
use App\Services\Training\TrainingCourseMediaUploadService;
use App\Services\Training\TrainingLessonResourceStorageService;
use App\Services\Training\TrainingPresentationKitService;
use App\Services\Training\TrainingPublicSiteImageCatalog;
use App\Services\Training\TrainingService;
use App\Services\Training\TrainingSessionInstructorGuard;
use App\Services\Training\TrainingStaffAlertService;
use App\Support\TrainingLmsStaffAccess;

class AdminTrainingStudioController
{
    private const LESSON_TYPES = [
        'richtext',
        'video',
        'video_integrated',
        'video_embed',
        'pdf',
        'audio',
        'scorm_like',
        'checklist',
        'external_link',
        'canvas',
        'quiz',
        'modals',
        'slideshow',
    ];

    private const VISIBILITY = ['draft', 'private', 'published', 'archived'];

    private const LEVELS = ['initiation', 'intermediaire', 'avance', 'expert'];

    private function canSetPlatformLmsScope(): bool
    {
        return Gate::getInstance()->allows('admin.system');
    }

    /** Onglets d’édition Studio (URL dédiées). */
    private const STUDIO_SECTIONS = ['fiche', 'presentation', 'structure'];

    private function studioEditUrl(int $courseId, string $section = 'fiche'): string
    {
        if (!in_array($section, self::STUDIO_SECTIONS, true)) {
            $section = 'fiche';
        }
        if ($section === 'fiche') {
            return training_studio_url($courseId . '/fiche');
        }

        return training_studio_url($courseId . '/' . $section);
    }

    /** Après action POST : retour vers l’onglet courant (champ caché _redirect_section). */
    private function studioRedirectAfter(Request $request, int $courseId, string $defaultSection, string $hash = ''): Response
    {
        $sec = trim((string) $request->input('_redirect_section', $defaultSection));
        if (!in_array($sec, self::STUDIO_SECTIONS, true)) {
            $sec = $defaultSection;
        }

        return Response::redirect($this->studioEditUrl($courseId, $sec) . $hash);
    }

    private function defaultCanvasJson(): string
    {
        return '{"version":1,"modals":[],"slides":[{"id":"slide-start","template":"title_hero","title":"","subtitle":"","body":"","imageUrl":"","imageCaption":"","fileUrl":"","fileLabel":"","resources":[],"primaryAction":null,"secondaryAction":null}]}';
    }

    /** @return Response|null redirect avec flash erreur */
    private function validateCanvasLessonContent(string $raw): ?string
    {
        $t = trim($raw);
        if ($t === '') {
            return $this->defaultCanvasJson();
        }
        $j = json_decode($t, true);
        if (!is_array($j) || !isset($j['slides']) || !is_array($j['slides'])) {
            return null;
        }

        return json_encode($j, JSON_UNESCAPED_UNICODE);
    }

    /** @return string|null JSON normalisé ou null si invalide (types structurés) */
    private function normalizeLessonContentForType(string $type, string $raw): ?string
    {
        if ($type === 'canvas') {
            return $this->validateCanvasLessonContent($raw);
        }
        if ($type === 'quiz') {
            return function_exists('training_lesson_validate_quiz_content')
                ? training_lesson_validate_quiz_content($raw)
                : null;
        }
        if ($type === 'modals') {
            return function_exists('training_lesson_validate_modals_content')
                ? training_lesson_validate_modals_content($raw)
                : null;
        }
        if ($type === 'slideshow') {
            return function_exists('training_lesson_validate_slideshow_content')
                ? training_lesson_validate_slideshow_content($raw)
                : null;
        }

        return $raw;
    }

    private function normalizeLessonDifficulty(?string $raw): ?string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }

        return in_array($raw, self::LEVELS, true) ? $raw : null;
    }

    private function optionalVarchar(Request $request, string $key, int $maxLen): ?string
    {
        $v = trim((string) $request->input($key, ''));
        if ($v === '') {
            return null;
        }
        if (mb_strlen($v) > $maxLen) {
            $v = mb_substr($v, 0, $maxLen);
        }

        return $v;
    }

    private function optionalText(Request $request, string $key): ?string
    {
        $v = trim((string) $request->input($key, ''));

        return $v === '' ? null : $v;
    }

    /** Met à jour la trace « dernière sauvegarde sous version X » du Studio (structure ou contenu). */
    private function markCourseSavedWithCurrentStudioVersion(int $courseId): void
    {
        if (!function_exists('lms_platform_version')) {
            return;
        }
        $userId = (int) Session::get('user_id');
        try {
            $this->courseRepository->update($courseId, [
                'lms_last_saved_with_version' => lms_platform_version(),
                'updated_by' => $userId > 0 ? $userId : null,
            ]);
        } catch (\Throwable) {
            /* colonnes LMS version absentes ou BDD partielle */
        }
    }

    public function __construct(
        private TrainingCourseRepository $courseRepository,
        private TrainingModuleRepository $moduleRepository,
        private TrainingLessonRepository $lessonRepository,
        private TrainingService $trainingService,
        private TrainingAuditService $auditService,
        private FeatureGateService $featureGate,
        private TenantRepository $tenantRepository,
        private RoleRepository $roleRepository,
        private GradeRepository $gradeRepository,
        private TrainingCourseLmsSocialRepository $lmsSocialRepository,
        private TrainingResourceRepository $resourceRepository,
        private UserRepository $userRepository,
        private TrainingCourseSessionNotificationService $courseSessionNotificationService,
        private DocumentRepository $documentRepository,
        private TrainingLessonResourceStorageService $lessonResourceStorageService,
        private DocumentUploadService $documentUploadService,
        private TrainingCoursePublicationGuard $coursePublicationGuard,
        private TrainingSessionInstructorGuard $sessionInstructorGuard,
        private PedagogyRepository $pedagogyRepository,
        private TrainingCourseMediaUploadService $courseMediaUploadService,
        private TrainingPublicSiteImageCatalog $publicSiteImageCatalog,
        private TrainingPresentationKitService $presentationKitService,
        private TrainingStaffAlertService $staffAlertService,
        private \App\Repositories\TrainingFormationCustomPageRepository $formationCustomPageRepository,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $denied = $this->assertTrainingFeatureAndAccess();
        if ($denied !== null) {
            return $denied;
        }
        if (!Session::get('training_studio_preamble_ok')) {
            $tid = (int) Session::get('tenant_id');
            $courseCount = $tid > 0 ? count($this->courseRepository->listForTenant($tid, null)) : 0;

            return Response::view('layout.training_studio', [
                'content' => 'admin.training.studio_preamble',
                'title' => 'Accès Studio LMS',
                'trainingStudioMode' => 'index',
                'trainingStudioShowIntro' => false,
                'trainingStudioHideSidebar' => true,
                'trainingStudioCourseCount' => $courseCount,
                'trainingStudioCourse' => null,
                'lmsPlatformVersion' => function_exists('lms_platform_version') ? lms_platform_version() : '',
                'lmsChangelogUrl' => training_studio_url('versions'),
            ]);
        }
        $tenantId = (int) Session::get('tenant_id');
        $filter = (string) $request->query('visibility', '');
        $courses = $this->courseRepository->listForTenant($tenantId, null);
        if ($filter !== '' && in_array($filter, self::VISIBILITY, true)) {
            $courses = array_values(array_filter($courses, static fn (array $c) => ($c['visibility'] ?? '') === $filter));
        }

        return Response::view('layout.training_studio', [
            'content' => 'admin.training.studio_index',
            'title' => 'Studio LMS',
            'courses' => $courses,
            'visibilityFilter' => $filter,
            'canPublish' => $this->canPublish(),
            'trainingCourseCapacity' => $this->featureGate->trainingCourseCapacityForTenant($tenantId),
            'trainingStudioMode' => 'index',
            'trainingStudioCourseCount' => count($courses),
            'trainingStudioCourse' => null,
            'lmsPlatformVersion' => function_exists('lms_platform_version') ? lms_platform_version() : '',
            'lmsChangelogUrl' => training_studio_url('versions'),
            'studioCanSetPlatformScope' => $this->canSetPlatformLmsScope(),
            'pedagogyColumnsReady' => $this->pedagogyRepository->trainingCoursesHavePedagogyColumns(),
            'studioStaffPickUsers' => $this->userRepository->listForTenant($tenantId, null, 'active', null, 500, 0, true, null, null),
        ]);
    }

    public function postPreambleAck(Request $request, array $params = []): Response
    {
        $denied = $this->assertTrainingFeatureAndAccess();
        if ($denied !== null) {
            return $denied;
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée, réessayez.');

            return Response::redirect(training_studio_url());
        }
        Session::set('training_studio_preamble_ok', true);

        return Response::redirect(training_studio_url());
    }

    public function versionsGuide(Request $request, array $params = []): Response
    {
        $denied = $this->assertTrainingFeatureAndAccess();
        if ($denied !== null) {
            return $denied;
        }
        $entries = function_exists('lms_platform_changelog') ? lms_platform_changelog() : [];
        $tid = (int) Session::get('tenant_id');
        $courseCount = $tid > 0 ? count($this->courseRepository->listForTenant($tid, null)) : 0;

        return Response::view('layout.training_studio', [
            'content' => 'admin.training.studio_versions',
            'title' => 'Journal du Studio & versions',
            'trainingStudioMode' => 'index',
            'trainingStudioShowIntro' => false,
            'trainingStudioCourseCount' => $courseCount,
            'trainingStudioCourse' => null,
            'lmsPlatformVersion' => function_exists('lms_platform_version') ? lms_platform_version() : '',
            'lmsChangelogUrl' => training_studio_url('versions'),
            'lmsChangelogEntries' => $entries,
            'lmsPlatformLabel' => function_exists('lms_platform_config') ? (string) (lms_platform_config()['label'] ?? '') : '',
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        $denied = $this->assertTrainingFeatureAndAccess();
        if ($denied !== null) {
            return $denied;
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée, réessayez.');

            return Response::redirect(training_studio_url());
        }
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');

        $title = trim((string) $request->input('title', ''));
        if ($title === '') {
            Session::flash('error', 'Le titre est obligatoire.');

            return Response::redirect(training_studio_url());
        }
        $slugRaw = trim((string) $request->input('slug', ''));
        $slug = $slugRaw !== '' ? $slugRaw : $this->slugify($title);
        $lmsScope = TrainingCourseRepository::LMS_SCOPE_TENANT;
        if ($this->canSetPlatformLmsScope()) {
            $in = trim((string) $request->input('lms_scope', TrainingCourseRepository::LMS_SCOPE_TENANT));
            $lmsScope = $in === TrainingCourseRepository::LMS_SCOPE_PLATFORM
                ? TrainingCourseRepository::LMS_SCOPE_PLATFORM
                : TrainingCourseRepository::LMS_SCOPE_TENANT;
        }
        if ($lmsScope === TrainingCourseRepository::LMS_SCOPE_TENANT
            && !$this->featureGate->canCreateTenantCatalogTrainingCourse($tenantId)) {
            Session::flash(
                'error',
                'Vous avez atteint le nombre maximal de parcours prévus pour votre formule. Passez à une offre supérieure, supprimez un parcours existant ou contactez l’encadrement pour ajuster l’offre.'
            );

            return Response::redirect(training_studio_url());
        }
        if ($lmsScope === TrainingCourseRepository::LMS_SCOPE_PLATFORM) {
            if ($this->courseRepository->platformSlugExists($slug)) {
                Session::flash('error', 'Cet identifiant d’URL est déjà utilisé pour un autre parcours proposé sur toute la plateforme.');

                return Response::redirect(training_studio_url());
            }
        } elseif ($this->courseRepository->slugExists($tenantId, $slug)) {
            Session::flash('error', 'Ce segment d’URL est déjà utilisé pour une autre formation de votre communauté.');

            return Response::redirect(training_studio_url());
        }
        $visibility = (string) $request->input('visibility', 'draft');
        if (!in_array($visibility, self::VISIBILITY, true)) {
            $visibility = 'draft';
        }
        if ($visibility === 'published' && !$this->canPublish()) {
            Session::flash('error', 'Vous n’avez pas la permission de publier une formation.');

            return Response::redirect(training_studio_url());
        }

        $mergedPreview = [
            'visibility' => $visibility,
            'pedagogical_owner_user_id' => $this->pedagogyRepository->trainingCoursesHavePedagogyColumns()
                ? max(0, (int) $request->input('pedagogical_owner_user_id', 0)) : null,
        ];
        if ($visibility === 'published' && !$this->coursePublicationGuard->canPublish($tenantId, $mergedPreview, $userId)) {
            Session::flash('error', $this->coursePublicationGuard->lastUserMessage() ?? 'Publication impossible pour le moment.');

            return Response::redirect(training_studio_url());
        }

        $lmsVer = function_exists('lms_platform_version') ? lms_platform_version() : '1.0.0';
        $createPayload = [
            'title' => $title,
            'slug' => $slug,
            'short_description' => null,
            'description' => null,
            'thumbnail_path' => null,
            'banner_path' => null,
            'visibility' => $visibility,
            'created_by' => $userId,
            'updated_by' => $userId,
            'lms_created_with_version' => $lmsVer,
            'lms_last_saved_with_version' => $lmsVer,
            'lms_scope' => $lmsScope,
        ];
        $newId = $this->courseRepository->create($tenantId, $createPayload);
        if ($this->pedagogyRepository->trainingCoursesHavePedagogyColumns()) {
            $oid = max(0, (int) $request->input('pedagogical_owner_user_id', 0));
            $fid = max(0, (int) $request->input('final_validator_user_id', 0));
            $this->courseRepository->update($newId, [
                'pedagogical_owner_user_id' => $oid > 0 ? $oid : null,
                'final_validator_user_id' => $fid > 0 ? $fid : null,
                'updated_by' => $userId,
            ]);
        }
        $this->auditService->logCourseCreated($tenantId, $userId, $newId, [
            'title' => $title,
            'slug' => $slug,
            'visibility' => $visibility,
            'lms_scope' => $lmsScope,
        ]);
        if ($visibility === 'published') {
            $this->auditService->logCoursePublished($tenantId, $userId, $newId);
        }
        Session::flash('success', 'Formation créée.');

        return Response::redirect($this->studioEditUrl($newId, 'fiche'));
    }

    public function edit(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id < 1) {
            return Response::redirect(training_studio_url());
        }

        return Response::redirect($this->studioEditUrl($id, 'fiche'));
    }

    public function editFiche(Request $request, array $params = []): Response
    {
        return $this->renderStudioEdit($params, 'fiche');
    }

    public function editPresentation(Request $request, array $params = []): Response
    {
        return $this->renderStudioEdit($params, 'presentation');
    }

    public function editStructure(Request $request, array $params = []): Response
    {
        return $this->renderStudioEdit($params, 'structure');
    }

    private function renderStudioEdit(array $params, string $section): Response
    {
        if (!in_array($section, self::STUDIO_SECTIONS, true)) {
            $section = 'fiche';
        }
        $denied = $this->assertTrainingFeatureAndAccess();
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        $course = $this->trainingService->getCourseWithStructure($id, $tenantId, true);
        if (!$course) {
            Session::flash('error', 'Formation introuvable.');

            return Response::redirect(training_studio_url());
        }
        foreach ($course['modules'] ?? [] as $modIdx => $modRow) {
            $lessons = $modRow['lessons'] ?? [];
            foreach ($lessons as $lesIdx => $lesRow) {
                $lid = (int) ($lesRow['id'] ?? 0);
                $course['modules'][$modIdx]['lessons'][$lesIdx]['studio_resources'] = $lid > 0
                    ? $this->resourceRepository->listByLessonId($lid)
                    : [];
            }
        }
        $tenant = $this->tenantRepository->findById($tenantId);

        $modCount = count($course['modules'] ?? []);
        $lesCount = 0;
        foreach ($course['modules'] ?? [] as $m) {
            $lesCount += count($m['lessons'] ?? []);
        }
        $allCourses = $this->courseRepository->listForTenant($tenantId, null);

        $otherCourses = array_values(array_filter($allCourses, static fn (array $c): bool => (int) ($c['id'] ?? 0) !== $id));
        $roles = $this->roleRepository->forTenantOrganization($tenantId);
        $grades = $this->gradeRepository->listActive();
        $sessions = $this->lmsSocialRepository->listSessionsForCourse($id);
        $pendingQuestions = $this->lmsSocialRepository->listQuestionsForCourse($id, 200);
        $staffPickUsers = $this->userRepository->listForTenant($tenantId, null, 'active', null, 500, 0, true, null, null);
        $canPublish = $this->canPublish();
        $publishElevationRecipients = [];
        $publishElevationCooldownSec = null;
        if (!$canPublish) {
            $viewerId = (int) Session::get('user_id');
            $publishElevationRecipients = $this->staffAlertService->listPublishElevationRecipients($tenantId, $viewerId);
            $publishElevationCooldownSec = $this->staffAlertService->secondsBeforeNextPublishElevationRequest($id, $viewerId);
        }

        $sectionTitles = [
            'fiche' => 'Données & inscription',
            'presentation' => 'Présentation apprenant',
            'structure' => 'Modules & leçons',
        ];

        return Response::view('layout.training_studio', [
            'content' => 'admin.training.studio_edit',
            'title' => 'Studio — ' . (string) $course['title'] . ' — ' . ($sectionTitles[$section] ?? $section),
            'course' => $course,
            'tenant' => $tenant,
            'lessonTypes' => self::LESSON_TYPES,
            'visibilityOptions' => self::VISIBILITY,
            'levelOptions' => self::LEVELS,
            'canPublish' => $canPublish,
            'publishElevationRecipients' => $publishElevationRecipients,
            'publishElevationCooldownSec' => $publishElevationCooldownSec,
            'trainingStudioMode' => 'edit',
            'trainingStudioCourseCount' => count($allCourses),
            'trainingStudioSection' => $section,
            'lmsPlatformVersion' => function_exists('lms_platform_version') ? lms_platform_version() : '',
            'lmsChangelogUrl' => training_studio_url('versions'),
            'lmsCourseCreatedBeforeCurrent' => function_exists('lms_course_studio_created_before_current') ? lms_course_studio_created_before_current($course) : false,
            'lmsCourseLastSaveBehind' => function_exists('lms_course_studio_last_save_behind_current') ? lms_course_studio_last_save_behind_current($course) : false,
            'trainingStudioCourse' => [
                'id' => (int) $course['id'],
                'title' => (string) ($course['title'] ?? ''),
                'slug' => (string) ($course['slug'] ?? ''),
                'visibility' => (string) ($course['visibility'] ?? 'draft'),
                'module_count' => $modCount,
                'lesson_count' => $lesCount,
            ],
            'studioOtherCourses' => $otherCourses,
            'studioRoles' => $roles,
            'studioGrades' => $grades,
            'studioSessions' => $sessions,
            'studioQuestions' => $pendingQuestions,
            'studioStaffPickUsers' => $staffPickUsers,
            'studioCanSetPlatformScope' => $this->canSetPlatformLmsScope(),
            'pedagogyColumnsReady' => $this->pedagogyRepository->trainingCoursesHavePedagogyColumns(),
            'libraryDocumentsForPicker' => $this->documentRepository->listForTenant(
                $tenantId,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                'title_asc'
            ),
            'formationDocsForPicker' => $this->formationCustomPageRepository->listByTenant($tenantId, 200),
            'studioPresentationKits' => $section === 'presentation'
                ? $this->presentationKitService->listKits($tenantId)
                : [],
            'studioSiteImages' => $section === 'presentation'
                ? $this->publicSiteImageCatalog->listImages()
                : [],
        ]);
    }

    /** Aperçu structurel avec contenu caviardé (démo / validation sans fuite d’URLs ou de textes). */
    public function preview(Request $request, array $params = []): Response
    {
        $denied = $this->assertTrainingFeatureAndAccess();
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        $course = $this->trainingService->getCourseWithStructure($id, $tenantId, true);
        if (!$course) {
            Session::flash('error', 'Formation introuvable.');

            return Response::redirect(training_studio_url());
        }
        $allCourses = $this->courseRepository->listForTenant($tenantId, null);
        $modCount = count($course['modules'] ?? []);
        $lesCount = 0;
        foreach ($course['modules'] ?? [] as $m) {
            $lesCount += count($m['lessons'] ?? []);
        }

        return Response::view('layout.training_studio', [
            'content' => 'admin.training.studio_preview',
            'title' => 'Aperçu caviardé — ' . (string) ($course['title'] ?? ''),
            'trainingStudioShowIntro' => false,
            'course' => $course,
            'trainingStudioMode' => 'edit',
            'trainingStudioCourseCount' => count($allCourses),
            'trainingStudioCourse' => [
                'id' => (int) $course['id'],
                'title' => (string) ($course['title'] ?? ''),
                'slug' => (string) ($course['slug'] ?? ''),
                'visibility' => (string) ($course['visibility'] ?? 'draft'),
                'module_count' => $modCount,
                'lesson_count' => $lesCount,
            ],
        ]);
    }

    /** Fichier d’une ressource de leçon (aperçu studio, hors inscription apprenant). */
    public function resourceFile(Request $request, array $params = []): Response
    {
        $denied = $this->assertTrainingFeatureAndAccess();
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $courseId = (int) ($params['id'] ?? 0);
        $resourceId = (int) ($params['resourceId'] ?? 0);
        $course = $this->courseRepository->findById($courseId, $tenantId);
        if (!$course || $resourceId < 1) {
            return (new Response())->setStatusCode(404)->header('Content-Type', 'text/plain; charset=utf-8')->setBody('Ressource introuvable.');
        }
        $res = $this->resourceRepository->findById($resourceId);
        if (!$res || empty($res['file_path']) || (string) ($res['resource_type'] ?? '') === 'library_document') {
            return (new Response())->setStatusCode(404)->header('Content-Type', 'text/plain; charset=utf-8')->setBody('Ressource introuvable.');
        }
        $lesson = $this->lessonRepository->findById((int) ($res['lesson_id'] ?? 0));
        if (!$lesson || !$this->assertLessonInTenantCourse($lesson, $courseId, $tenantId)) {
            return (new Response())->setStatusCode(404)->header('Content-Type', 'text/plain; charset=utf-8')->setBody('Ressource introuvable.');
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
        $inline = trim((string) $request->query('inline', '')) === '1';
        $response = new Response();
        $response->header('Content-Type', $mime);
        $response->header('Content-Disposition', ($inline ? 'inline' : 'attachment') . '; filename="' . $safeName . '"');
        $response->setBodyStream(static function () use ($path): void {
            readfile($path);
        });

        return $response;
    }

    public function handle(Request $request, array $params = []): Response
    {
        $denied = $this->assertTrainingFeatureAndAccess();
        if ($denied !== null) {
            return $denied;
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée, réessayez.');

            return $this->redirectToEdit($params);
        }
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        $courseId = (int) ($params['id'] ?? 0);
        $course = $this->courseRepository->findById($courseId, $tenantId);
        if (!$course) {
            Session::flash('error', 'Formation introuvable.');

            return Response::redirect(training_studio_url());
        }

        $action = (string) $request->input('_action', '');

        return match ($action) {
            'save_course' => $this->saveCourse($request, $course, $tenantId, $userId),
            'add_session' => $this->addSession($request, $courseId, $tenantId),
            'delete_session' => $this->deleteSession($request, $courseId, $tenantId),
            'answer_question' => $this->answerQuestion($request, $courseId, $tenantId, $userId),
            'add_module' => $this->addModule($request, $courseId, $tenantId, $userId),
            'update_module' => $this->updateModule($request, $courseId, $tenantId),
            'delete_module' => $this->deleteModule($request, $courseId, $tenantId),
            'move_module' => $this->moveModule($request, $courseId, $tenantId),
            'reorder_modules' => $this->reorderModules($request, $courseId, $tenantId),
            'add_lesson' => $this->addLesson($request, $courseId, $tenantId),
            'update_lesson' => $this->updateLesson($request, $courseId, $tenantId),
            'delete_lesson' => $this->deleteLesson($request, $courseId, $tenantId),
            'move_lesson' => $this->moveLesson($request, $courseId, $tenantId),
            'reorder_lessons' => $this->reorderLessons($request, $courseId, $tenantId),
            'add_lesson_resource' => $this->addLessonResource($request, $courseId, $tenantId),
            'delete_lesson_resource' => $this->deleteLessonResource($request, $courseId, $tenantId),
            'regenerate_enrollment_share_code' => $this->regenerateEnrollmentShareCode($request, $courseId, $tenantId, $userId),
            'save_presentation_kit' => $this->savePresentationKit($request, $course, $tenantId),
            'apply_presentation_kit' => $this->applyPresentationKit($request, $course, $tenantId, $userId),
            'delete_presentation_kit' => $this->deletePresentationKit($request, $courseId, $tenantId),
            'request_publish_elevation' => $this->requestPublishElevation($course, $tenantId, $userId),
            default => $this->badAction($courseId),
        };
    }

    /**
     * @param array<string, mixed> $course
     */
    private function requestPublishElevation(array $course, int $tenantId, int $userId): Response
    {
        $courseId = (int) ($course['id'] ?? 0);
        if ($this->canPublish()) {
            Session::flash('error', 'Vous disposez déjà du droit de publier cette formation.');

            return Response::redirect($this->studioEditUrl($courseId, 'fiche'));
        }

        $result = $this->staffAlertService->requestPublishElevation($tenantId, $userId, $course);
        if (!empty($result['ok'])) {
            Session::flash('success', (string) ($result['message'] ?? 'Demande envoyée.'));
        } else {
            Session::flash('error', (string) ($result['message'] ?? 'Impossible d’envoyer la demande.'));
        }

        return Response::redirect($this->studioEditUrl($courseId, 'fiche'));
    }

    private function saveCourse(Request $request, array $course, int $tenantId, int $userId): Response
    {
        $courseId = (int) $course['id'];
        $scope = trim((string) $request->input('_studio_section', 'fiche'));
        if ($scope === 'presentation') {
            return $this->saveCoursePresentation($request, $course, $tenantId, $userId);
        }

        $oldVis = (string) ($course['visibility'] ?? '');
        $newVis = (string) $request->input('visibility', $oldVis);
        if (!in_array($newVis, self::VISIBILITY, true)) {
            $newVis = $oldVis;
        }
        if ($newVis === 'published' && $oldVis !== 'published' && !$this->canPublish()) {
            Session::flash('error', 'Vous n’avez pas la permission de publier cette formation.');

            return Response::redirect($this->studioEditUrl($courseId, 'fiche'));
        }

        $title = trim((string) $request->input('title', ''));
        if ($title === '') {
            Session::flash('error', 'Le titre est obligatoire.');

            return Response::redirect($this->studioEditUrl($courseId, 'fiche'));
        }
        $slug = trim((string) $request->input('slug', ''));
        if ($slug === '') {
            $slug = $this->slugify($title);
        }
        $currentScope = (string) ($course['lms_scope'] ?? TrainingCourseRepository::LMS_SCOPE_TENANT);
        if ($currentScope !== TrainingCourseRepository::LMS_SCOPE_PLATFORM && $currentScope !== TrainingCourseRepository::LMS_SCOPE_TENANT) {
            $currentScope = TrainingCourseRepository::LMS_SCOPE_TENANT;
        }
        $requestedScope = $currentScope;
        if ($this->canSetPlatformLmsScope()) {
            $in = trim((string) $request->input('lms_scope', $currentScope));
            $requestedScope = $in === TrainingCourseRepository::LMS_SCOPE_PLATFORM
                ? TrainingCourseRepository::LMS_SCOPE_PLATFORM
                : TrainingCourseRepository::LMS_SCOPE_TENANT;
        } elseif ($currentScope === TrainingCourseRepository::LMS_SCOPE_PLATFORM) {
            $requestedScope = TrainingCourseRepository::LMS_SCOPE_PLATFORM;
        } else {
            $requestedScope = TrainingCourseRepository::LMS_SCOPE_TENANT;
        }
        if ($requestedScope === TrainingCourseRepository::LMS_SCOPE_PLATFORM) {
            if ($this->courseRepository->platformSlugExists($slug, $courseId)) {
                Session::flash('error', 'Cet identifiant d’URL est déjà utilisé pour un autre parcours proposé sur toute la plateforme.');

                return Response::redirect($this->studioEditUrl($courseId, 'fiche'));
            }
        } elseif ($this->courseRepository->slugExists($tenantId, $slug, $courseId)) {
            Session::flash('error', 'Ce segment d’URL est déjà utilisé pour une autre formation de votre communauté.');

            return Response::redirect($this->studioEditUrl($courseId, 'fiche'));
        }

        $level = (string) $request->input('level', (string) ($course['level'] ?? 'initiation'));
        if (!in_array($level, self::LEVELS, true)) {
            $level = 'initiation';
        }

        $learningObjectives = $this->learningObjectivesJsonFromRequest($request, 'course_learning_objectives');
        $courseCode = trim((string) $request->input('course_code', ''));
        $courseCode = $courseCode === '' ? null : substr($courseCode, 0, 32);

        $policy = $this->buildEnrollmentPolicyFromRequest($request, (int) $course['id']);
        $policyJson = json_encode($policy, JSON_UNESCAPED_UNICODE);

        $patch = [
            'title' => $title,
            'slug' => $slug,
            'course_code' => $courseCode,
            'short_description' => trim((string) $request->input('short_description', '')) ?: null,
            'description' => trim((string) $request->input('description', '')) ?: null,
            'learning_objectives' => $learningObjectives,
            'enrollment_policy_json' => $policyJson,
            'category' => trim((string) $request->input('category', '')) ?: null,
            'level' => $level,
            'language_code' => substr(trim((string) $request->input('language_code', 'fr')), 0, 10) ?: 'fr',
            'estimated_minutes' => max(0, (int) $request->input('estimated_minutes', (int) ($course['estimated_minutes'] ?? 0))),
            'passing_score' => min(100, max(0, (float) $request->input('passing_score', (float) ($course['passing_score'] ?? 80)))),
            'is_mandatory' => $request->input('is_mandatory') ? 1 : 0,
            'is_certifying' => $request->input('is_certifying') ? 1 : 0,
            'validity_days' => ($v = trim((string) $request->input('validity_days', ''))) === '' ? null : max(0, (int) $v),
            'visibility' => $newVis,
            'lms_scope' => $requestedScope,
            'updated_by' => $userId,
            'theme_json' => $course['theme_json'] ?? null,
            'thumbnail_path' => trim((string) ($course['thumbnail_path'] ?? '')) !== '' ? trim((string) $course['thumbnail_path']) : null,
            'banner_path' => trim((string) ($course['banner_path'] ?? '')) !== '' ? trim((string) $course['banner_path']) : null,
            'instruction_audio_url' => trim((string) ($course['instruction_audio_url'] ?? '')) !== '' ? trim((string) $course['instruction_audio_url']) : null,
            'instruction_audio_instructor_optional' => (int) ($course['instruction_audio_instructor_optional'] ?? 1) ? 1 : 0,
            'instruction_audio_notes' => trim((string) ($course['instruction_audio_notes'] ?? '')) !== '' ? trim((string) $course['instruction_audio_notes']) : null,
        ];
        if (function_exists('lms_platform_version')) {
            $patch['lms_last_saved_with_version'] = lms_platform_version();
        }
        if ($this->pedagogyRepository->trainingCoursesHavePedagogyColumns()) {
            $oid = max(0, (int) $request->input('pedagogical_owner_user_id', (int) ($course['pedagogical_owner_user_id'] ?? 0)));
            $fid = max(0, (int) $request->input('final_validator_user_id', (int) ($course['final_validator_user_id'] ?? 0)));
            $patch['pedagogical_owner_user_id'] = $oid > 0 ? $oid : null;
            $patch['final_validator_user_id'] = $fid > 0 ? $fid : null;
        }
        $mergedForPublish = array_merge($course, $patch);
        if ($newVis === 'published' && $oldVis !== 'published'
            && !$this->coursePublicationGuard->canPublish($tenantId, $mergedForPublish, $userId)) {
            Session::flash('error', $this->coursePublicationGuard->lastUserMessage() ?? 'Publication impossible pour le moment.');

            return Response::redirect($this->studioEditUrl($courseId, 'fiche'));
        }

        $oldSnapshot = [
            'title' => $course['title'],
            'slug' => $course['slug'],
            'visibility' => $oldVis,
        ];
        $this->courseRepository->update($courseId, $patch);
        $this->auditService->logCourseUpdated($tenantId, $userId, $courseId, $oldSnapshot, [
            'title' => $title,
            'slug' => $slug,
            'visibility' => $newVis,
        ]);
        if ($newVis === 'published' && $oldVis !== 'published') {
            $this->auditService->logCoursePublished($tenantId, $userId, $courseId);
        }
        $this->ensureEnrollmentShareCodeIfMissing($courseId, $tenantId);
        Session::flash('success', 'Données de la formation enregistrées.');

        return Response::redirect($this->studioEditUrl($courseId, 'fiche'));
    }

    private function saveCoursePresentation(Request $request, array $course, int $tenantId, int $userId): Response
    {
        $courseId = (int) $course['id'];

        try {
            $thumbnailPath = $this->resolvePresentationImagePath(
                $tenantId,
                (string) ($course['thumbnail_path'] ?? ''),
                $_FILES['thumbnail_upload'] ?? null,
                trim((string) $request->input('thumbnail_path', '')),
                $request->input('thumbnail_remove', '') === '1',
                'thumbnail'
            );
            $bannerPath = $this->resolvePresentationImagePath(
                $tenantId,
                (string) ($course['banner_path'] ?? ''),
                $_FILES['banner_upload'] ?? null,
                trim((string) $request->input('banner_path', '')),
                $request->input('banner_remove', '') === '1',
                'banner'
            );

            $prevTheme = function_exists('training_lms_parse_theme')
                ? training_lms_parse_theme((string) ($course['theme_json'] ?? ''))
                : [];
            $prevLoader = trim((string) ($prevTheme['openingLoaderImage'] ?? ''));
            $loaderPath = $this->resolvePresentationImagePath(
                $tenantId,
                $prevLoader,
                $_FILES['lms_opening_loader_image_upload'] ?? null,
                trim((string) $request->input('lms_opening_loader_image', '')),
                $request->input('lms_opening_loader_image_remove', '') === '1',
                'loader'
            );

            $audioUrl = $this->resolvePresentationAudioPath(
                $tenantId,
                (string) ($course['instruction_audio_url'] ?? ''),
                $_FILES['instruction_audio_upload'] ?? null,
                trim((string) $request->input('instruction_audio_url', '')),
                $request->input('instruction_audio_remove', '') === '1'
            );
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            Session::flash('error', $e->getMessage());

            return Response::redirect($this->studioEditUrl($courseId, 'presentation'));
        }

        $themeJson = $this->buildThemeJsonFromRequest(
            $request,
            (string) ($course['theme_json'] ?? ''),
            $loaderPath
        );
        $audioNotes = trim((string) $request->input('instruction_audio_notes', ''));
        $audioNotes = $audioNotes === '' ? null : substr($audioNotes, 0, 500);

        $patch = [
            'theme_json' => $themeJson,
            'thumbnail_path' => $thumbnailPath,
            'banner_path' => $bannerPath,
            'instruction_audio_url' => $audioUrl,
            'instruction_audio_instructor_optional' => $request->input('instruction_audio_instructor_optional') ? 1 : 0,
            'instruction_audio_notes' => $audioNotes,
            'updated_by' => $userId,
        ];
        if (function_exists('lms_platform_version')) {
            $patch['lms_last_saved_with_version'] = lms_platform_version();
        }
        $this->courseRepository->update($courseId, $patch);
        $this->markCourseSavedWithCurrentStudioVersion($courseId);
        Session::flash('success', 'Présentation enregistrée.');

        return Response::redirect($this->studioEditUrl($courseId, 'presentation'));
    }

    /**
     * Résout miniature / bannière / image loader : retrait, upload, sélection bibliothèque site, ou conservation.
     *
     * @param array{name?: string, type?: string, tmp_name?: string, error?: int, size?: int}|null $file
     */
    private function resolvePresentationImagePath(
        int $tenantId,
        string $currentPath,
        ?array $file,
        string $postedPath,
        bool $remove,
        string $prefix
    ): ?string {
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

        $posted = trim($postedPath);
        if ($posted === '') {
            return $current;
        }

        $sitePick = $this->publicSiteImageCatalog->normalizePickedPath($posted);
        if ($sitePick !== null) {
            if ($current !== null && $current !== $sitePick) {
                $this->courseMediaUploadService->deleteManagedRelative($current);
            }

            return $sitePick;
        }

        // Conserve un chemin déjà enregistré (upload géré ou URL externe historique).
        if ($current !== null && $posted === $current) {
            return $current;
        }

        // Chemin déjà sous le dossier uploads géré (réutilisation / kit).
        $sanitized = str_replace(['\\', '..'], ['/', ''], $posted);
        if (str_starts_with($sanitized, 'uploads/training-course-media/')) {
            if ($current !== null && $current !== $sanitized) {
                $this->courseMediaUploadService->deleteManagedRelative($current);
            }

            return substr($sanitized, 0, 255);
        }

        if (preg_match('#^https?://#i', $posted) === 1) {
            return substr($posted, 0, 255);
        }

        // Repli : si la valeur postée est invalide, on garde l’existant.
        return $current;
    }

    /**
     * @param array{name?: string, type?: string, tmp_name?: string, error?: int, size?: int}|null $file
     */
    private function resolvePresentationAudioPath(
        int $tenantId,
        string $currentPath,
        ?array $file,
        string $postedPath,
        bool $remove
    ): ?string {
        $current = trim($currentPath) ?: null;

        $uploaded = $this->courseMediaUploadService->storeAudioUpload($tenantId, $file, 'audio');
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

        $posted = trim($postedPath);
        if ($posted === '') {
            return $current;
        }
        if ($current !== null && $posted === $current) {
            return $current;
        }
        $sanitized = str_replace(['\\', '..'], ['/', ''], $posted);
        if (str_starts_with($sanitized, 'uploads/training-course-media/')) {
            if ($current !== null && $current !== $sanitized) {
                $this->courseMediaUploadService->deleteManagedRelative($current);
            }

            return substr($sanitized, 0, 512);
        }
        if (preg_match('#^https?://#i', $posted) === 1) {
            return substr($posted, 0, 512);
        }

        return $current;
    }

    private function savePresentationKit(Request $request, array $course, int $tenantId): Response
    {
        $courseId = (int) $course['id'];
        $name = trim((string) $request->input('kit_name', ''));
        $theme = function_exists('training_lms_parse_theme')
            ? training_lms_parse_theme((string) ($course['theme_json'] ?? ''))
            : [];
        $tjRaw = trim((string) ($course['theme_json'] ?? ''));
        $payload = [
            'theme_enable' => $tjRaw !== '' && $tjRaw !== '{}',
            'accent' => (string) ($theme['accent'] ?? '#10b981'),
            'font_key' => function_exists('training_lms_theme_font_key_from_css')
                ? training_lms_theme_font_key_from_css($theme['font'] ?? null)
                : 'inter',
            'radius_key' => function_exists('training_lms_theme_radius_key_from_value')
                ? training_lms_theme_radius_key_from_value($theme['radius'] ?? null)
                : 'generous',
            'variant' => (string) ($theme['variant'] ?? 'default'),
            'opening_loader_image' => $theme['openingLoaderImage'] ?? null,
            'opening_loader_title' => $theme['openingLoaderTitle'] ?? null,
            'opening_loader_body' => $theme['openingLoaderBody'] ?? null,
            'thumbnail_path' => $course['thumbnail_path'] ?? null,
            'banner_path' => $course['banner_path'] ?? null,
            'instruction_audio_url' => $course['instruction_audio_url'] ?? null,
            'instruction_audio_instructor_optional' => (int) ($course['instruction_audio_instructor_optional'] ?? 1) === 1,
            'instruction_audio_notes' => $course['instruction_audio_notes'] ?? null,
        ];
        try {
            $this->presentationKitService->saveKit($tenantId, $name, $payload);
        } catch (\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());

            return Response::redirect($this->studioEditUrl($courseId, 'presentation') . '#studio-presentation-kits');
        }
        Session::flash('success', 'Kit de présentation enregistré pour votre communauté.');

        return Response::redirect($this->studioEditUrl($courseId, 'presentation') . '#studio-presentation-kits');
    }

    private function applyPresentationKit(Request $request, array $course, int $tenantId, int $userId): Response
    {
        $courseId = (int) $course['id'];
        $kitId = trim((string) $request->input('kit_id', ''));
        $kit = $this->presentationKitService->findKit($tenantId, $kitId);
        if ($kit === null) {
            Session::flash('error', 'Ce kit de présentation est introuvable.');

            return Response::redirect($this->studioEditUrl($courseId, 'presentation') . '#studio-presentation-kits');
        }
        $payload = is_array($kit['payload'] ?? null) ? $kit['payload'] : [];
        $payload = $this->presentationKitService->normalizePayload($payload);

        $themeJson = null;
        if (!empty($payload['theme_enable'])) {
            $fonts = function_exists('training_lms_theme_font_presets') ? training_lms_theme_font_presets() : [];
            $radii = function_exists('training_lms_theme_radius_presets') ? training_lms_theme_radius_presets() : [];
            $fontKey = (string) ($payload['font_key'] ?? 'inter');
            $radiusKey = (string) ($payload['radius_key'] ?? 'generous');
            $accent = (string) ($payload['accent'] ?? '#10b981');
            $themeJson = json_encode([
                'accent' => $accent,
                'accentRgb' => function_exists('training_lms_hex_to_rgb_csv')
                    ? training_lms_hex_to_rgb_csv($accent)
                    : '16, 185, 129',
                'font' => $fonts[$fontKey] ?? ($fonts['inter'] ?? 'Inter, system-ui, sans-serif'),
                'radius' => $radii[$radiusKey] ?? ($radii['generous'] ?? '2rem'),
                'variant' => (string) ($payload['variant'] ?? 'default'),
                'openingLoaderImage' => (string) ($payload['opening_loader_image'] ?? ''),
                'openingLoaderTitle' => (string) ($payload['opening_loader_title'] ?? ''),
                'openingLoaderBody' => (string) ($payload['opening_loader_body'] ?? ''),
            ], JSON_UNESCAPED_UNICODE);
        }

        $patch = [
            'theme_json' => $themeJson,
            'thumbnail_path' => $payload['thumbnail_path'] ?? null,
            'banner_path' => $payload['banner_path'] ?? null,
            'instruction_audio_url' => $payload['instruction_audio_url'] ?? null,
            'instruction_audio_instructor_optional' => !empty($payload['instruction_audio_instructor_optional']) ? 1 : 0,
            'instruction_audio_notes' => $payload['instruction_audio_notes'] ?? null,
            'updated_by' => $userId,
        ];
        if (function_exists('lms_platform_version')) {
            $patch['lms_last_saved_with_version'] = lms_platform_version();
        }
        $this->courseRepository->update($courseId, $patch);
        $this->markCourseSavedWithCurrentStudioVersion($courseId);
        Session::flash('success', 'Kit « ' . (string) ($kit['name'] ?? '') . ' » appliqué à cette formation.');

        return Response::redirect($this->studioEditUrl($courseId, 'presentation'));
    }

    private function deletePresentationKit(Request $request, int $courseId, int $tenantId): Response
    {
        $kitId = trim((string) $request->input('kit_id', ''));
        if (!$this->presentationKitService->deleteKit($tenantId, $kitId)) {
            Session::flash('error', 'Impossible de supprimer ce kit.');
        } else {
            Session::flash('success', 'Kit de présentation supprimé.');
        }

        return Response::redirect($this->studioEditUrl($courseId, 'presentation') . '#studio-presentation-kits');
    }

    private function regenerateEnrollmentShareCode(Request $request, int $courseId, int $tenantId, int $userId): Response
    {
        $course = $this->courseRepository->findById($courseId, $tenantId);
        if (!$course) {
            Session::flash('error', 'Formation introuvable.');

            return Response::redirect(training_studio_url());
        }
        $code = $this->pickUniqueEnrollmentShareCode($courseId);
        if ($code === null) {
            Session::flash('error', 'Impossible de générer un code unique pour le moment. Réessayez.');

            return Response::redirect($this->studioEditUrl($courseId, 'fiche') . '#studio-engagement-share');
        }
        try {
            $upd = [
                'enrollment_share_code' => $code,
                'updated_by' => $userId,
            ];
            if (function_exists('lms_platform_version')) {
                $upd['lms_last_saved_with_version'] = lms_platform_version();
            }
            $this->courseRepository->update($courseId, $upd);
        } catch (\Throwable) {
            Session::flash('error', 'Enregistrement du code impossible (migration appliquée ?).');

            return Response::redirect($this->studioEditUrl($courseId, 'fiche') . '#studio-engagement-share');
        }
        $this->auditService->logCourseUpdated($tenantId, $userId, $courseId, null, ['enrollment_share_code_regenerated' => true]);
        Session::flash('success', 'Nouveau code de partage généré.');

        return Response::redirect($this->studioEditUrl($courseId, 'fiche') . '#studio-engagement-share');
    }

    private function ensureEnrollmentShareCodeIfMissing(int $courseId, int $tenantId): void
    {
        $row = $this->courseRepository->findById($courseId, $tenantId);
        if (!$row) {
            return;
        }
        $existing = trim((string) ($row['enrollment_share_code'] ?? ''));
        if ($existing !== '') {
            return;
        }
        $code = $this->pickUniqueEnrollmentShareCode($courseId);
        if ($code === null) {
            return;
        }
        try {
            $this->courseRepository->update($courseId, ['enrollment_share_code' => $code]);
        } catch (\Throwable) {
        }
    }

    private function pickUniqueEnrollmentShareCode(int $excludeCourseId): ?string
    {
        if (!function_exists('training_lms_generate_enrollment_share_code')) {
            return null;
        }
        for ($i = 0; $i < 40; $i++) {
            $c = training_lms_generate_enrollment_share_code();
            if ($this->courseRepository->isEnrollmentShareCodeTaken($c, $excludeCourseId)) {
                continue;
            }

            return $c;
        }

        return null;
    }

    private function addModule(Request $request, int $courseId, int $tenantId, int $userId): Response
    {
        $title = trim((string) $request->input('module_title', ''));
        if ($title === '') {
            Session::flash('error', 'Le titre du module est obligatoire.');

            return $this->studioRedirectAfter($request, $courseId, 'structure');
        }
        $mid = $this->moduleRepository->create($courseId, [
            'title' => $title,
            'description' => trim((string) $request->input('module_description', '')) ?: null,
            'subtitle' => $this->optionalVarchar($request, 'module_subtitle', 255),
            'learning_objectives' => $this->learningObjectivesJsonFromRequest($request, 'module_learning_objectives'),
            'estimated_minutes' => max(0, min(99999, (int) $request->input('module_estimated_minutes', 0))),
            'is_required' => $request->input('module_is_required') ? 1 : 0,
        ]);
        $this->auditService->logCourseUpdated($tenantId, $userId, $courseId, null, ['module_created' => $mid]);
        $this->markCourseSavedWithCurrentStudioVersion($courseId);
        Session::flash('success', 'Module ajouté.');

        return $this->studioRedirectAfter($request, $courseId, 'structure');
    }

    private function updateModule(Request $request, int $courseId, int $tenantId): Response
    {
        $moduleId = (int) $request->input('module_id', 0);
        $mod = $this->assertModuleInCourse($moduleId, $courseId, $tenantId);
        if (!$mod) {
            Session::flash('error', 'Module introuvable.');

            return $this->studioRedirectAfter($request, $courseId, 'structure');
        }
        $title = trim((string) $request->input('module_title', ''));
        if ($title === '') {
            Session::flash('error', 'Le titre du module est obligatoire.');

            return $this->studioRedirectAfter($request, $courseId, 'structure');
        }
        $this->moduleRepository->update($moduleId, [
            'title' => $title,
            'description' => trim((string) $request->input('module_description', '')) ?: null,
            'subtitle' => $this->optionalVarchar($request, 'module_subtitle', 255),
            'learning_objectives' => $this->learningObjectivesJsonFromRequest($request, 'module_learning_objectives'),
            'estimated_minutes' => max(0, min(99999, (int) $request->input('module_estimated_minutes', 0))),
            'is_required' => $request->input('module_is_required') ? 1 : 0,
        ]);
        $this->markCourseSavedWithCurrentStudioVersion($courseId);
        Session::flash('success', 'Module enregistré.');

        return $this->studioRedirectAfter($request, $courseId, 'structure');
    }

    private function deleteModule(Request $request, int $courseId, int $tenantId): Response
    {
        $moduleId = (int) $request->input('module_id', 0);
        $mod = $this->assertModuleInCourse($moduleId, $courseId, $tenantId);
        if (!$mod) {
            Session::flash('error', 'Module introuvable.');

            return $this->studioRedirectAfter($request, $courseId, 'structure');
        }
        $this->moduleRepository->delete($moduleId);
        $this->markCourseSavedWithCurrentStudioVersion($courseId);
        Session::flash('success', 'Module supprimé.');

        return $this->studioRedirectAfter($request, $courseId, 'structure');
    }

    private function reorderModules(Request $request, int $courseId, int $tenantId): Response
    {
        $raw = $request->input('module_ids', []);
        $ids = is_array($raw) ? array_map('intval', $raw) : [];
        $ids = array_values(array_filter($ids, static fn (int $x): bool => $x > 0));
        $modules = $this->moduleRepository->listByCourseId($courseId);
        $valid = array_map(static fn (array $m) => (int) $m['id'], $modules);
        sort($valid);
        $sorted = $ids;
        sort($sorted);
        if ($ids === [] || count($ids) !== count($valid) || $sorted !== $valid) {
            Session::flash('error', 'Ordre des modules invalide.');

            return $this->studioRedirectAfter($request, $courseId, 'structure');
        }
        $this->moduleRepository->reorder($courseId, $ids);
        $this->markCourseSavedWithCurrentStudioVersion($courseId);
        Session::flash('success', 'Ordre des modules mis à jour.');

        return $this->studioRedirectAfter($request, $courseId, 'structure');
    }

    private function reorderLessons(Request $request, int $courseId, int $tenantId): Response
    {
        $moduleId = (int) $request->input('module_id', 0);
        $mod = $this->assertModuleInCourse($moduleId, $courseId, $tenantId);
        if (!$mod) {
            Session::flash('error', 'Module introuvable.');

            return $this->studioRedirectAfter($request, $courseId, 'structure');
        }
        $raw = $request->input('lesson_ids', []);
        $ids = is_array($raw) ? array_map('intval', $raw) : [];
        $ids = array_values(array_filter($ids, static fn (int $x): bool => $x > 0));
        $lessons = $this->lessonRepository->listByModuleId($moduleId);
        $valid = array_map(static fn (array $l) => (int) $l['id'], $lessons);
        sort($valid);
        $sorted = $ids;
        sort($sorted);
        if ($ids === [] || count($ids) !== count($valid) || $sorted !== $valid) {
            Session::flash('error', 'Ordre des leçons invalide.');

            return $this->studioRedirectAfter($request, $courseId, 'structure');
        }
        $this->lessonRepository->reorder($moduleId, $ids);
        $this->markCourseSavedWithCurrentStudioVersion($courseId);
        Session::flash('success', 'Ordre des leçons mis à jour.');

        return $this->studioRedirectAfter($request, $courseId, 'structure');
    }

    private function moveModule(Request $request, int $courseId, int $tenantId): Response
    {
        $moduleId = (int) $request->input('module_id', 0);
        $dir = (string) $request->input('direction', '');
        $mod = $this->assertModuleInCourse($moduleId, $courseId, $tenantId);
        if (!$mod) {
            return $this->studioRedirectAfter($request, $courseId, 'structure');
        }
        $modules = $this->moduleRepository->listByCourseId($courseId);
        $ids = array_map(static fn (array $m) => (int) $m['id'], $modules);
        $idx = array_search($moduleId, $ids, true);
        if ($idx === false) {
            return $this->studioRedirectAfter($request, $courseId, 'structure');
        }
        if ($dir === 'up' && $idx > 0) {
            $tmp = $ids[$idx - 1];
            $ids[$idx - 1] = $ids[$idx];
            $ids[$idx] = $tmp;
            $this->moduleRepository->reorder($courseId, $ids);
        } elseif ($dir === 'down' && $idx < count($ids) - 1) {
            $tmp = $ids[$idx + 1];
            $ids[$idx + 1] = $ids[$idx];
            $ids[$idx] = $tmp;
            $this->moduleRepository->reorder($courseId, $ids);
        }
        $this->markCourseSavedWithCurrentStudioVersion($courseId);

        return $this->studioRedirectAfter($request, $courseId, 'structure');
    }

    private function addLesson(Request $request, int $courseId, int $tenantId): Response
    {
        $moduleId = (int) $request->input('module_id', 0);
        $mod = $this->assertModuleInCourse($moduleId, $courseId, $tenantId);
        if (!$mod) {
            Session::flash('error', 'Module introuvable.');

            return $this->studioRedirectAfter($request, $courseId, 'structure');
        }
        $title = trim((string) $request->input('lesson_title', ''));
        if ($title === '') {
            Session::flash('error', 'Le titre de la leçon est obligatoire.');

            return $this->studioRedirectAfter($request, $courseId, 'structure');
        }
        $type = (string) $request->input('lesson_type', 'richtext');
        if (!in_array($type, self::LESSON_TYPES, true)) {
            $type = 'richtext';
        }
        $contentRaw = (string) $request->input('lesson_content', '');
        $contentRaw = $this->normalizeLessonContentForType($type, $contentRaw);
        if ($contentRaw === null) {
            Session::flash('error', 'Contenu de leçon invalide pour ce type — vérifiez le quiz, les modales ou le diaporama.');

            return $this->studioRedirectAfter($request, $courseId, 'structure');
        }
        $this->lessonRepository->create($moduleId, [
            'title' => $title,
            'summary' => $this->optionalVarchar($request, 'lesson_summary', 500),
            'learning_objectives' => $this->learningObjectivesJsonFromRequest($request, 'lesson_learning_objectives'),
            'instructor_notes' => $this->optionalText($request, 'lesson_instructor_notes'),
            'lesson_type' => $type,
            'content' => $contentRaw,
            'external_url' => trim((string) $request->input('lesson_external_url', '')) ?: null,
            'duration_minutes' => max(0, (int) $request->input('lesson_duration_minutes', 0)),
            'difficulty' => $this->normalizeLessonDifficulty((string) $request->input('lesson_difficulty', '')),
            'is_required' => $request->input('lesson_is_required') ? 1 : 0,
        ]);
        $this->markCourseSavedWithCurrentStudioVersion($courseId);
        Session::flash('success', 'Leçon ajoutée.');

        return $this->studioRedirectAfter($request, $courseId, 'structure');
    }

    private function updateLesson(Request $request, int $courseId, int $tenantId): Response
    {
        $lessonId = (int) $request->input('lesson_id', 0);
        $lesson = $this->lessonRepository->findById($lessonId);
        if (!$lesson || !$this->assertLessonInTenantCourse($lesson, $courseId, $tenantId)) {
            Session::flash('error', 'Leçon introuvable.');

            return $this->studioRedirectAfter($request, $courseId, 'structure');
        }
        $title = trim((string) $request->input('lesson_title', ''));
        if ($title === '') {
            Session::flash('error', 'Le titre de la leçon est obligatoire.');

            return $this->studioRedirectAfter($request, $courseId, 'structure');
        }
        $type = (string) $request->input('lesson_type', 'richtext');
        if (!in_array($type, self::LESSON_TYPES, true)) {
            $type = 'richtext';
        }
        $contentRaw = (string) $request->input('lesson_content', '');
        $contentRaw = $this->normalizeLessonContentForType($type, $contentRaw);
        if ($contentRaw === null) {
            Session::flash('error', 'Contenu de leçon invalide pour ce type — vérifiez le quiz, les modales ou le diaporama.');

            return $this->studioRedirectAfter($request, $courseId, 'structure');
        }
        $this->lessonRepository->update($lessonId, [
            'title' => $title,
            'summary' => $this->optionalVarchar($request, 'lesson_summary', 500),
            'learning_objectives' => $this->learningObjectivesJsonFromRequest($request, 'lesson_learning_objectives'),
            'instructor_notes' => $this->optionalText($request, 'lesson_instructor_notes'),
            'lesson_type' => $type,
            'content' => $contentRaw,
            'external_url' => trim((string) $request->input('lesson_external_url', '')) ?: null,
            'duration_minutes' => max(0, (int) $request->input('lesson_duration_minutes', 0)),
            'difficulty' => $this->normalizeLessonDifficulty((string) $request->input('lesson_difficulty', '')),
            'is_required' => $request->input('lesson_is_required') ? 1 : 0,
        ]);
        $this->markCourseSavedWithCurrentStudioVersion($courseId);
        Session::flash('success', 'Leçon enregistrée.');

        return $this->studioRedirectAfter($request, $courseId, 'structure');
    }

    private function deleteLesson(Request $request, int $courseId, int $tenantId): Response
    {
        $lessonId = (int) $request->input('lesson_id', 0);
        $lesson = $this->lessonRepository->findById($lessonId);
        if (!$lesson || !$this->assertLessonInTenantCourse($lesson, $courseId, $tenantId)) {
            Session::flash('error', 'Leçon introuvable.');

            return $this->studioRedirectAfter($request, $courseId, 'structure');
        }
        $this->lessonRepository->delete($lessonId);
        $this->markCourseSavedWithCurrentStudioVersion($courseId);
        Session::flash('success', 'Leçon supprimée.');

        return $this->studioRedirectAfter($request, $courseId, 'structure');
    }

    private const RESOURCE_TYPES = ['pdf', 'image', 'video', 'audio', 'zip', 'attachment', 'link', 'library_document'];

    private function addLessonResource(Request $request, int $courseId, int $tenantId): Response
    {
        $lessonId = (int) $request->input('lesson_id', 0);
        $lesson = $this->lessonRepository->findById($lessonId);
        if (!$lesson || !$this->assertLessonInTenantCourse($lesson, $courseId, $tenantId)) {
            Session::flash('error', 'Leçon introuvable.');

            return $this->studioRedirectAfter($request, $courseId, 'structure');
        }
        $hash = '#lesson-res-' . $lessonId;
        $mode = (string) $request->input('resource_add_mode', 'link');
        if (!in_array($mode, ['link', 'file', 'image', 'library', 'library_upload', 'formation_doc'], true)) {
            $mode = 'link';
        }

        if ($mode === 'formation_doc') {
            $docId = (int) $request->input('formation_doc_id', 0);
            $doc = $docId > 0 ? $this->formationCustomPageRepository->findById($docId, $tenantId) : null;
            if (!$doc) {
                Session::flash('error', 'Choisissez une Documentation HTML dans la liste.');

                return $this->studioRedirectAfter($request, $courseId, 'structure', $hash);
            }
            $title = trim((string) $request->input('resource_title', ''));
            if ($title === '') {
                $title = trim((string) ($doc['title'] ?? 'Documentation'));
            }
            $slug = (string) ($doc['slug'] ?? '');
            $extUrl = $slug !== '' ? rtrim(url(''), '/') . '/formations/page/' . rawurlencode($slug) : null;
            if ($extUrl === null) {
                Session::flash('error', 'Ce document n’a pas d’adresse publique valide.');

                return $this->studioRedirectAfter($request, $courseId, 'structure', $hash);
            }
            $this->resourceRepository->create($lessonId, [
                'resource_type' => 'link',
                'title' => mb_substr($title, 0, 255),
                'external_url' => $extUrl,
                'file_path' => null,
            ]);
            $this->markCourseSavedWithCurrentStudioVersion($courseId);
            $msg = 'Documentation liée à la leçon.';
            $visLevel = (string) ($doc['visibility_level'] ?? 'tenant');
            if (($doc['status'] ?? '') !== 'published') {
                $msg .= ' Ce document n’est pas encore publié : les apprenants ne verront le lien qu’après publication.';
            } elseif (!in_array($visLevel, ['tenant', 'internal_link', ''], true)) {
                $msg .= ' Attention : ce document a une visibilité restreinte (' . $visLevel . ') — seuls les apprenants autorisés pourront ouvrir le lien.';
            }
            Session::flash('success', $msg);

            return $this->studioRedirectAfter($request, $courseId, 'structure', $hash);
        }
        if ($mode === 'library_upload') {
            $upload = isset($_FILES['resource_library_upload']) && is_array($_FILES['resource_library_upload'])
                ? $_FILES['resource_library_upload']
                : null;
            if ($upload === null || (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                Session::flash('error', 'Choisissez un fichier à envoyer dans la bibliothèque d’assets.');

                return $this->studioRedirectAfter($request, $courseId, 'structure', $hash);
            }
            $userId = (int) Session::get('user_id');
            if ($userId < 1) {
                Session::flash('error', 'Session invalide, reconnectez-vous.');

                return $this->studioRedirectAfter($request, $courseId, 'structure', $hash);
            }
            $title = trim((string) $request->input('resource_library_title', ''));
            if ($title === '') {
                $rawName = (string) ($upload['name'] ?? 'Asset LMS');
                $title = trim((string) pathinfo($rawName, PATHINFO_FILENAME));
                if ($title === '') {
                    $title = 'Asset LMS';
                }
            }
            $slugBase = $this->documentRepository->slugify($title);
            $slug = $slugBase;
            $suffix = 2;
            while ($this->documentRepository->slugExists($tenantId, $slug)) {
                $slug = $slugBase . '-' . $suffix;
                $suffix++;
            }
            $isPrivate = (bool) $request->input('resource_library_private');
            $docId = $this->documentRepository->create([
                'tenant_id' => $tenantId,
                'title' => mb_substr($title, 0, 255),
                'slug' => $slug,
                'description' => trim((string) $request->input('resource_library_description', '')) ?: null,
                'document_type' => 'asset',
                'classification_level' => 'interne',
                'visibility_scope' => $isPrivate ? 'private' : 'tenant',
                'status' => 'published',
                'created_by' => $userId,
                'owner_user_id' => $userId,
                'author_user_id' => $userId,
            ]);
            try {
                $this->documentUploadService->uploadNewVersion($tenantId, $docId, $upload, 'Upload Studio LMS', $userId);
            } catch (\Throwable $e) {
                $this->documentRepository->deleteHard($docId, $tenantId);
                Session::flash('error', 'Upload impossible pour la bibliothèque d’assets : ' . $e->getMessage());

                return $this->studioRedirectAfter($request, $courseId, 'structure', $hash);
            }
            $this->resourceRepository->create($lessonId, [
                'resource_type' => 'library_document',
                'title' => mb_substr($title, 0, 255),
                'file_path' => null,
                'external_url' => null,
                'mime_type' => null,
                'file_size' => null,
                'document_id' => $docId,
            ]);
            $this->markCourseSavedWithCurrentStudioVersion($courseId);
            Session::flash('success', $isPrivate
                ? 'Asset importé en bibliothèque (privé) et lié à la leçon.'
                : 'Asset importé en bibliothèque et lié à la leçon.');

            return $this->studioRedirectAfter($request, $courseId, 'structure', $hash);
        }

        if ($mode === 'library') {
            $docId = (int) $request->input('document_id', 0);
            $doc = $docId > 0 ? $this->documentRepository->findById($docId, $tenantId) : null;
            if (!$doc) {
                Session::flash('error', 'Choisissez un document dans la liste ou vérifiez qu’il appartient à votre communauté.');

                return $this->studioRedirectAfter($request, $courseId, 'structure', $hash);
            }
            $title = trim((string) $request->input('resource_title', ''));
            if ($title === '') {
                $title = trim((string) ($doc['title'] ?? 'Document'));
            }
            $this->resourceRepository->create($lessonId, [
                'resource_type' => 'library_document',
                'title' => mb_substr($title, 0, 255),
                'external_url' => null,
                'file_path' => null,
                'mime_type' => null,
                'file_size' => null,
                'document_id' => $docId,
            ]);
            $this->markCourseSavedWithCurrentStudioVersion($courseId);
            $msg = 'Ressource ajoutée à la leçon.';
            if (($doc['status'] ?? '') !== 'published') {
                $msg .= ' Ce document n’est pas encore publié : les apprenants ne verront le lien qu’après publication.';
            }
            Session::flash('success', $msg);

            return $this->studioRedirectAfter($request, $courseId, 'structure', $hash);
        }

        if ($mode === 'image' || $mode === 'file') {
            $title = trim((string) $request->input('resource_title', ''));
            $type = $mode === 'image'
                ? 'image'
                : trim((string) $request->input('resource_type', 'attachment'));
            $fileTypes = ['pdf', 'image', 'video', 'audio', 'zip', 'attachment'];
            if (!in_array($type, $fileTypes, true)) {
                $type = 'attachment';
            }
            $file = isset($_FILES['resource_upload']) && is_array($_FILES['resource_upload']) ? $_FILES['resource_upload'] : null;
            $filePath = null;
            $mime = null;
            $size = null;
            if ($file !== null && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                if ($mode === 'image') {
                    $ext = strtolower((string) pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
                    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                        Session::flash('error', 'Choisissez une image au format JPG, PNG ou WebP.');

                        return $this->studioRedirectAfter($request, $courseId, 'structure', $hash);
                    }
                }
                try {
                    $stored = $this->lessonResourceStorageService->storeUpload($tenantId, $file);
                    $filePath = $stored['path'];
                    $mime = $stored['mime'];
                    $size = $stored['size'];
                    if ($title === '') {
                        $rawName = (string) ($file['name'] ?? '');
                        $fromName = trim((string) pathinfo($rawName, PATHINFO_FILENAME));
                        $title = $fromName !== '' ? $fromName : ($mode === 'image' ? 'Image' : 'Fichier');
                    }
                    if ($mode === 'image' || str_starts_with((string) $mime, 'image/')) {
                        $type = 'image';
                    }
                } catch (\Throwable $e) {
                    Session::flash('error', $e->getMessage());

                    return $this->studioRedirectAfter($request, $courseId, 'structure', $hash);
                }
            } elseif ($mode !== 'image') {
                $manual = trim((string) $request->input('resource_file_path', ''));
                $filePath = $manual === '' ? null : substr($manual, 0, 255);
            }
            if ($title === '') {
                Session::flash('error', 'Indiquez un titre pour la ressource.');

                return $this->studioRedirectAfter($request, $courseId, 'structure', $hash);
            }
            if ($filePath === null) {
                Session::flash('error', $mode === 'image'
                    ? 'Choisissez une image à envoyer.'
                    : 'Envoyez un fichier ou renseignez l’emplacement en mode avancé.');

                return $this->studioRedirectAfter($request, $courseId, 'structure', $hash);
            }
            $this->resourceRepository->create($lessonId, [
                'resource_type' => $type,
                'title' => mb_substr($title, 0, 255),
                'external_url' => null,
                'file_path' => $filePath,
                'mime_type' => $mime,
                'file_size' => $size,
            ]);
            $this->markCourseSavedWithCurrentStudioVersion($courseId);
            Session::flash('success', $type === 'image'
                ? 'Image ajoutée à la leçon.'
                : 'Ressource ajoutée à la leçon.');

            return $this->studioRedirectAfter($request, $courseId, 'structure', $hash);
        }

        $title = trim((string) $request->input('resource_title', ''));
        if ($title === '') {
            Session::flash('error', 'Indiquez un titre pour la ressource.');

            return $this->studioRedirectAfter($request, $courseId, 'structure', $hash);
        }
        $url = trim((string) $request->input('resource_external_url', ''));
        $extUrl = $url === '' ? null : substr($url, 0, 500);
        if ($extUrl === null) {
            Session::flash('error', 'Indiquez une adresse web (de préférence en https://).');

            return $this->studioRedirectAfter($request, $courseId, 'structure', $hash);
        }
        $this->resourceRepository->create($lessonId, [
            'resource_type' => 'link',
            'title' => mb_substr($title, 0, 255),
            'external_url' => $extUrl,
            'file_path' => null,
        ]);
        $this->markCourseSavedWithCurrentStudioVersion($courseId);
        Session::flash('success', 'Ressource ajoutée à la leçon.');

        return $this->studioRedirectAfter($request, $courseId, 'structure', $hash);
    }

    private function deleteLessonResource(Request $request, int $courseId, int $tenantId): Response
    {
        $rid = (int) $request->input('resource_id', 0);
        $res = $this->resourceRepository->findById($rid);
        if (!$res) {
            Session::flash('error', 'Ressource introuvable.');

            return $this->studioRedirectAfter($request, $courseId, 'structure');
        }
        $lessonId = (int) ($res['lesson_id'] ?? 0);
        $lesson = $this->lessonRepository->findById($lessonId);
        if (!$lesson || !$this->assertLessonInTenantCourse($lesson, $courseId, $tenantId)) {
            Session::flash('error', 'Ressource introuvable.');

            return $this->studioRedirectAfter($request, $courseId, 'structure');
        }
        $fp = str_replace('\\', '/', trim((string) ($res['file_path'] ?? '')));
        if ($fp !== '' && str_starts_with($fp, 'storage/uploads/training-lesson-resources/')) {
            $abs = base_path($fp);
            if (is_file($abs)) {
                @unlink($abs);
            }
        }
        $this->resourceRepository->delete($rid);
        $this->markCourseSavedWithCurrentStudioVersion($courseId);
        Session::flash('success', 'Ressource retirée.');

        return $this->studioRedirectAfter($request, $courseId, 'structure', '#lesson-res-' . $lessonId);
    }

    private function moveLesson(Request $request, int $courseId, int $tenantId): Response
    {
        $lessonId = (int) $request->input('lesson_id', 0);
        $dir = (string) $request->input('direction', '');
        $lesson = $this->lessonRepository->findById($lessonId);
        if (!$lesson || !$this->assertLessonInTenantCourse($lesson, $courseId, $tenantId)) {
            return $this->studioRedirectAfter($request, $courseId, 'structure');
        }
        $moduleId = (int) $lesson['module_id'];
        $lessons = $this->lessonRepository->listByModuleId($moduleId);
        $ids = array_map(static fn (array $l) => (int) $l['id'], $lessons);
        $idx = array_search($lessonId, $ids, true);
        if ($idx === false) {
            return $this->studioRedirectAfter($request, $courseId, 'structure');
        }
        if ($dir === 'up' && $idx > 0) {
            $a = $lessons[$idx];
            $b = $lessons[$idx - 1];
            $this->lessonRepository->update((int) $a['id'], ['position' => (int) $b['position']]);
            $this->lessonRepository->update((int) $b['id'], ['position' => (int) $a['position']]);
        } elseif ($dir === 'down' && $idx < count($ids) - 1) {
            $a = $lessons[$idx];
            $b = $lessons[$idx + 1];
            $this->lessonRepository->update((int) $a['id'], ['position' => (int) $b['position']]);
            $this->lessonRepository->update((int) $b['id'], ['position' => (int) $a['position']]);
        }
        $this->markCourseSavedWithCurrentStudioVersion($courseId);

        return $this->studioRedirectAfter($request, $courseId, 'structure');
    }

    /** @return non-empty-string|null JSON tableau d’objectifs ou null si aucun. */
    private function learningObjectivesJsonFromRequest(Request $request, string $fieldName): ?string
    {
        $raw = $request->input($fieldName);
        if (!is_array($raw)) {
            return null;
        }
        $lines = [];
        foreach ($raw as $x) {
            $t = trim((string) $x);
            if ($t !== '') {
                $lines[] = $t;
            }
        }

        return $lines === [] ? null : json_encode($lines, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Thème apprenant : formulaire visuel studio, ou null si désactivé.
     * Les clés non gérées par le formulaire sont conservées si le thème précédent était un objet JSON valide.
     *
     * @param string|null $loaderImageOverride Chemin loader déjà résolu (upload / bibliothèque), prioritaire sur le POST.
     */
    private function buildThemeJsonFromRequest(Request $request, string $previousThemeJson = '', ?string $loaderImageOverride = null): ?string
    {
        if ((int) $request->input('lms_theme_enable', 0) !== 1) {
            return null;
        }
        $base = [];
        $prev = trim($previousThemeJson);
        if ($prev !== '' && $prev !== '{}') {
            $d = json_decode($prev, true);
            if (is_array($d)) {
                $base = $d;
            }
        }
        $accent = trim((string) $request->input('lms_theme_accent', ''));
        if ($accent === '' || !preg_match('/^#[0-9A-Fa-f]{6}$/', $accent)) {
            $accent = '#10b981';
        }
        $rgb = function_exists('training_lms_hex_to_rgb_csv') ? training_lms_hex_to_rgb_csv($accent) : '16, 185, 129';
        $fontKey = trim((string) $request->input('lms_theme_font', 'inter'));
        $fonts = function_exists('training_lms_theme_font_presets') ? training_lms_theme_font_presets() : [];
        $font = $fonts[$fontKey] ?? ($fonts['inter'] ?? 'Inter, system-ui, sans-serif');
        $radiusKey = trim((string) $request->input('lms_theme_radius', 'generous'));
        $radii = function_exists('training_lms_theme_radius_presets') ? training_lms_theme_radius_presets() : [];
        $radius = $radii[$radiusKey] ?? ($radii['generous'] ?? '2rem');
        $variantKey = trim((string) $request->input('lms_theme_variant', 'default'));
        $variants = array_keys(function_exists('training_lms_theme_variant_labels_fr') ? training_lms_theme_variant_labels_fr() : []);
        $variant = in_array($variantKey, $variants, true) ? $variantKey : 'default';
        if ($loaderImageOverride !== null) {
            $loaderImage = trim($loaderImageOverride);
        } else {
            $loaderImage = trim((string) $request->input('lms_opening_loader_image', ''));
        }
        if ($loaderImage !== '') {
            $loaderImage = substr($loaderImage, 0, 255);
        }
        $loaderTitle = trim((string) $request->input('lms_opening_loader_title', ''));
        if ($loaderTitle !== '') {
            $loaderTitle = substr($loaderTitle, 0, 120);
        }
        $loaderBody = trim((string) $request->input('lms_opening_loader_body', ''));
        if ($loaderBody !== '') {
            $loaderBody = substr($loaderBody, 0, 320);
        }
        $payload = [
            'accent' => $accent,
            'accentRgb' => $rgb,
            'font' => $font,
            'radius' => $radius,
            'variant' => $variant,
            'openingLoaderImage' => $loaderImage,
            'openingLoaderTitle' => $loaderTitle,
            'openingLoaderBody' => $loaderBody,
        ];

        return json_encode(array_merge($base, $payload), JSON_UNESCAPED_UNICODE);
    }

    /** @return array<string, mixed> */
    private function buildEnrollmentPolicyFromRequest(Request $request, int $currentCourseId): array
    {
        $prereq = array_map('intval', (array) $request->input('policy_prerequisite_course_ids', []));
        $prereq = array_values(array_unique(array_filter($prereq, static fn (int $x): bool => $x > 0 && $x !== $currentCourseId)));
        $reqCerts = array_map('intval', (array) $request->input('policy_certificate_course_ids', []));
        $reqCerts = array_values(array_unique(array_filter($reqCerts, static fn (int $x): bool => $x > 0 && $x !== $currentCourseId)));
        // Audience rôles : « all » / sentinel UI (__all__, *) = aucune contrainte (liste vide). Pas de faux rôle en BDD.
        $roleAudience = trim((string) $request->input('policy_role_audience', ''));
        $rawRoleIds = (array) $request->input('policy_required_role_ids', []);
        $wantsAllRoles = $roleAudience === 'all';
        foreach ($rawRoleIds as $raw) {
            $s = trim((string) $raw);
            if ($s === '__all__' || $s === '*') {
                $wantsAllRoles = true;
                break;
            }
        }
        if ($wantsAllRoles) {
            $roleIds = [];
        } else {
            $roleIds = array_values(array_unique(array_filter(
                array_map('intval', $rawRoleIds),
                static fn (int $x): bool => $x > 0
            )));
        }
        $gradeIds = array_map('intval', (array) $request->input('policy_required_grade_ids', []));
        $gradeIds = array_values(array_unique(array_filter($gradeIds, static fn (int $x): bool => $x > 0)));
        $statuses = [];
        $posted = $request->input('policy_user_status');
        if (is_array($posted)) {
            $allowed = array_keys(function_exists('training_lms_enrollment_user_status_labels_fr') ? training_lms_enrollment_user_status_labels_fr() : []);
            foreach ($posted as $s) {
                $s = trim((string) $s);
                if ($s !== '' && in_array($s, $allowed, true)) {
                    $statuses[] = $s;
                }
            }
            $statuses = array_values(array_unique($statuses));
        }

        $approverIds = array_map('intval', (array) $request->input('policy_enrollment_approver_user_ids', []));
        $approverIds = array_values(array_unique(array_filter($approverIds, static fn (int $x): bool => $x > 0)));
        $selfEnrollAllowed = (int) $request->input('policy_self_enroll_disabled', 0) !== 1;

        return [
            'enrollments_blocked' => (int) $request->input('policy_enrollments_blocked', 0) === 1,
            'self_enroll_allowed' => $selfEnrollAllowed,
            'self_enroll_requires_approval' => $selfEnrollAllowed && (int) $request->input('policy_self_enroll_requires_approval', 0) === 1,
            'comments_enabled' => (int) $request->input('policy_comments_enabled', 0) === 1,
            'enrollment_approver_user_ids' => $approverIds,
            'prerequisite_course_ids' => $prereq,
            'require_certificate_from_course_ids' => $reqCerts,
            'required_role_ids' => $roleIds,
            'required_grade_ids' => $gradeIds,
            'required_user_statuses' => $statuses,
        ];
    }

    private function addSession(Request $request, int $courseId, int $tenantId): Response
    {
        $starts = trim((string) $request->input('session_starts_at', ''));
        $ends = trim((string) $request->input('session_ends_at', ''));
        if ($starts === '' || $ends === '') {
            Session::flash('error', 'Créneau : indiquez le début et la fin.');

            return Response::redirect($this->studioEditUrl($courseId, 'fiche') . '#studio-sessions-qa');
        }
        $instructorId = ($i = (int) $request->input('session_instructor_user_id', 0)) > 0 ? $i : null;
        if ($instructorId !== null
            && !$this->sessionInstructorGuard->canAssignInstructor($tenantId, $instructorId, $courseId)) {
            Session::flash('error', $this->sessionInstructorGuard->lastUserMessage() ?? 'Encadrant non autorisé pour ce créneau.');

            return Response::redirect($this->studioEditUrl($courseId, 'fiche') . '#studio-sessions-qa');
        }
        $id = $this->lmsSocialRepository->createSession($tenantId, $courseId, [
            'starts_at' => $starts,
            'ends_at' => $ends,
            'label' => trim((string) $request->input('session_label', '')) ?: null,
            'location' => trim((string) $request->input('session_location', '')) ?: null,
            'max_seats' => ($m = trim((string) $request->input('session_max_seats', ''))) === '' ? null : max(0, (int) $m),
            'instructor_user_id' => $instructorId,
            'audio_briefing_url' => ($a = trim((string) $request->input('session_audio_url', ''))) === '' ? null : substr($a, 0, 512),
            'notes' => trim((string) $request->input('session_notes', '')) ?: null,
        ]);
        if ($id < 1) {
            Session::flash('error', 'Créneau non enregistré — vérifiez la migration LMS engagement.');
        } else {
            Session::flash('success', 'Créneau ajouté.');
            $this->markCourseSavedWithCurrentStudioVersion($courseId);
            $actorUserId = (int) Session::get('user_id');
            $this->courseSessionNotificationService->notifyEnrolledLearnersOfNewSession(
                $tenantId,
                $courseId,
                $actorUserId,
                [
                    'starts_at' => $starts,
                    'ends_at' => $ends,
                    'label' => trim((string) $request->input('session_label', '')) ?: null,
                    'location' => trim((string) $request->input('session_location', '')) ?: null,
                ]
            );
        }

        return Response::redirect($this->studioEditUrl($courseId, 'fiche') . '#studio-sessions-qa');
    }

    private function deleteSession(Request $request, int $courseId, int $tenantId): Response
    {
        $sid = (int) $request->input('session_id', 0);
        if ($sid < 1 || !$this->lmsSocialRepository->deleteSession($sid, $tenantId, $courseId)) {
            Session::flash('error', 'Créneau introuvable.');
        } else {
            Session::flash('success', 'Créneau supprimé.');
            $this->markCourseSavedWithCurrentStudioVersion($courseId);
        }

        return Response::redirect($this->studioEditUrl($courseId, 'fiche') . '#studio-sessions-qa');
    }

    private function answerQuestion(Request $request, int $courseId, int $tenantId, int $staffUserId): Response
    {
        $qid = (int) $request->input('question_id', 0);
        $answer = trim((string) $request->input('question_answer', ''));
        if ($qid < 1 || $answer === '') {
            Session::flash('error', 'Réponse vide ou question invalide.');

            return Response::redirect($this->studioEditUrl($courseId, 'fiche') . '#studio-sessions-qa');
        }
        if (!$this->lmsSocialRepository->answerQuestion($qid, $tenantId, $courseId, $staffUserId, $answer)) {
            Session::flash('error', 'Réponse non enregistrée.');
        } else {
            Session::flash('success', 'Réponse publiée.');
            $this->markCourseSavedWithCurrentStudioVersion($courseId);
        }

        return Response::redirect($this->studioEditUrl($courseId, 'fiche') . '#studio-sessions-qa');
    }

    private function badAction(int $courseId): Response
    {
        Session::flash('error', 'Action non reconnue.');

        return Response::redirect($this->studioEditUrl($courseId, 'structure'));
    }

    private function redirectToEdit(array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id > 0) {
            return Response::redirect($this->studioEditUrl($id, 'fiche'));
        }

        return Response::redirect(training_studio_url());
    }

    private function assertModuleInCourse(int $moduleId, int $courseId, int $tenantId): ?array
    {
        $mod = $this->moduleRepository->findById($moduleId);
        if (!$mod || (int) $mod['course_id'] !== $courseId) {
            return null;
        }
        $course = $this->courseRepository->findById($courseId, $tenantId);

        return $course ? $mod : null;
    }

    private function assertLessonInTenantCourse(array $lesson, int $courseId, int $tenantId): bool
    {
        $moduleId = (int) ($lesson['module_id'] ?? 0);
        $mod = $this->moduleRepository->findById($moduleId);
        if (!$mod || (int) $mod['course_id'] !== $courseId) {
            return false;
        }
        $course = $this->courseRepository->findById($courseId, $tenantId);

        return $course !== null;
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

    /** Aligné sur {@see TrainingLmsStaffAccess} (middleware + AdminTrainingController). */
    private function hasTrainingAccess(): bool
    {
        return TrainingLmsStaffAccess::allows(Gate::getInstance());
    }

    private function canPublish(): bool
    {
        $gate = Gate::getInstance();

        return $gate->allows('admin.access') || $gate->allows('training.manage') || $gate->allows('training.publish');
    }

    private function slugify(string $title): string
    {
        $s = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title) ?: $title;
        $s = strtolower((string) $s);
        $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';

        return trim($s, '-') ?: 'formation';
    }
}
