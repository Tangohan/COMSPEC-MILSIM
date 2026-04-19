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
use App\Repositories\PedagogyRepository;
use App\Services\Training\TrainingAuditService;
use App\Services\Training\TrainingCoursePublicationGuard;
use App\Services\Training\TrainingCourseSessionNotificationService;
use App\Services\Training\TrainingLessonResourceStorageService;
use App\Services\Training\TrainingService;
use App\Services\Training\TrainingSessionInstructorGuard;
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
        private TrainingCoursePublicationGuard $coursePublicationGuard,
        private TrainingSessionInstructorGuard $sessionInstructorGuard,
        private PedagogyRepository $pedagogyRepository,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $denied = $this->assertTrainingFeatureAndAccess();
        if ($denied !== null) {
            return $denied;
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
            'canPublish' => $this->canPublish(),
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
            default => $this->badAction($courseId),
        };
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
        $themeJson = $this->buildThemeJsonFromRequest($request, (string) ($course['theme_json'] ?? ''));
        $audioUrl = trim((string) $request->input('instruction_audio_url', ''));
        $audioUrl = $audioUrl === '' ? null : substr($audioUrl, 0, 512);
        $audioNotes = trim((string) $request->input('instruction_audio_notes', ''));
        $audioNotes = $audioNotes === '' ? null : substr($audioNotes, 0, 500);

        $patch = [
            'theme_json' => $themeJson,
            'thumbnail_path' => trim((string) $request->input('thumbnail_path', '')) ?: null,
            'banner_path' => trim((string) $request->input('banner_path', '')) ?: null,
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
        if (!in_array($mode, ['link', 'file', 'library'], true)) {
            $mode = 'link';
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

        if ($mode === 'file') {
            $title = trim((string) $request->input('resource_title', ''));
            if ($title === '') {
                Session::flash('error', 'Indiquez un titre pour la ressource.');

                return $this->studioRedirectAfter($request, $courseId, 'structure', $hash);
            }
            $type = trim((string) $request->input('resource_type', 'attachment'));
            $fileTypes = ['pdf', 'image', 'video', 'audio', 'zip', 'attachment'];
            if (!in_array($type, $fileTypes, true)) {
                $type = 'attachment';
            }
            $file = isset($_FILES['resource_upload']) && is_array($_FILES['resource_upload']) ? $_FILES['resource_upload'] : null;
            $filePath = null;
            $mime = null;
            $size = null;
            if ($file !== null && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                try {
                    $stored = $this->lessonResourceStorageService->storeUpload($tenantId, $file);
                    $filePath = $stored['path'];
                    $mime = $stored['mime'];
                    $size = $stored['size'];
                } catch (\Throwable $e) {
                    Session::flash('error', $e->getMessage());

                    return $this->studioRedirectAfter($request, $courseId, 'structure', $hash);
                }
            } else {
                $manual = trim((string) $request->input('resource_file_path', ''));
                $filePath = $manual === '' ? null : substr($manual, 0, 255);
            }
            if ($filePath === null) {
                Session::flash('error', 'Envoyez un fichier ou renseignez l’emplacement en mode avancé.');

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
            Session::flash('success', 'Ressource ajoutée à la leçon.');

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
     */
    private function buildThemeJsonFromRequest(Request $request, string $previousThemeJson = ''): ?string
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
        $loaderImage = trim((string) $request->input('lms_opening_loader_image', ''));
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
        $roleIds = array_map('intval', (array) $request->input('policy_required_role_ids', []));
        $roleIds = array_values(array_unique(array_filter($roleIds, static fn (int $x): bool => $x > 0)));
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
