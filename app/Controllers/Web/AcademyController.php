<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AcademyRepository;
use App\Repositories\AcademyCourseShareRepository;
use App\Repositories\AcademyAccessRepository;
use App\Services\Audit\AuditService;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Repositories\TrainingEnrollmentRepository;
use App\Repositories\TrainingRepository;
use App\Services\Training\TrainingService;
use App\Services\Tenant\TenantContext;
use App\Services\Academy\AcademyAccess;
use App\Services\Academy\AcademyRights;
use App\Services\Academy\AcademyLmsBridge;
use App\Services\Academy\AcademyCurriculum;
use App\Services\Academy\AcademyBuiltInCourses;
use App\Services\Academy\AcademyOffer;
use App\Services\Academy\AcademyStudio;
use App\Services\Academy\GoogleSlidesImporter;
use App\Support\TrainingLmsStaffAccess;

final class AcademyController
{
    /** @var array{0:string,1:AcademyRights}|null */
    private ?array $rightsCache = null;

    public function home(Request $request, array $params = []): Response
    {
        // Public to guests; signed-in requests use the same account/session validation as /formations.
        if ((int) Session::get('user_id') > 0) {
            return (new \App\Middleware\AuthMiddleware())($request, fn (Request $authenticated): Response => (new \App\Middleware\TrainingModuleSanctionMiddleware())($authenticated, fn (Request $allowed): Response => $this->homeForCurrentAccount($allowed)));
        }
        return $this->homeForCurrentAccount($request);
    }

    private function lms(): AcademyLmsBridge
    {
        return new AcademyLmsBridge(
            Container::get(TrainingService::class),
            Container::get(TrainingEnrollmentRepository::class),
            training_legacy_enabled() ? Container::get(TrainingRepository::class) : null,
        );
    }

    private function homeForCurrentAccount(Request $request): Response
    {
        $account = null;
        $tenant = null;
        $accessState = 'guest';
        $legacyCourses = $legacyEnrollments = [];
        $academyCourses = $academyStates = [];
        $academyStorageReady = true;
        $staff = false;
        try {
            $userId = (int) Session::get('user_id');
            if ($userId > 0) {
                $account = Container::get(UserRepository::class)->findById($userId);
                $tenant = Container::get(TenantRepository::class)->findById(TenantContext::id());
                $accessState = !$tenant || ($tenant['slug'] ?? '') === 'default' ? 'no_tenant' : 'unpaid';
                if ($account && $tenant && $accessState === 'unpaid' && !AcademyAccess::paid($tenant)
                    && Container::get(\App\Services\Platform\FeatureGateService::class)->allows((int) $tenant['id'], 'training')) {
                    // Les formations de la communauté (ancien catalogue « Formations ») restent accessibles sans formule payante.
                    $data = $this->lms()->load((int) $tenant['id'], $userId);
                    $legacyCourses = $data['legacyCourses'];
                    $legacyEnrollments = $data['legacyEnrollments'];
                }
                if ($account && $tenant && $accessState !== 'no_tenant' && AcademyAccess::paid($tenant)) {
                    if (!Container::get(\App\Services\Platform\FeatureGateService::class)->allows((int) $tenant['id'], 'training')) {
                        return Response::view('academy.home', compact('account','tenant','legacyCourses','legacyEnrollments','staff') + ['accessState'=>'unavailable'])->header('Cache-Control','private, no-store');
                    }
                    $data = $this->lms()->load((int) $tenant['id'], $userId);
                    $legacyCourses = $data['legacyCourses'];
                    $legacyEnrollments = $data['legacyEnrollments'];
                    $accessState = 'active';
                    $staff = TrainingLmsStaffAccess::allows(Gate::getInstance());
                    try {
                        $repository = new AcademyRepository();
                        $rights = $this->rights((int) $tenant['id'], $userId);
                        $staff = $rights->entersInstructor();
                        // Cours au public restreint : seuls les membres concernés les voient au catalogue.
                        $academyCourses = $rights->visible($repository->courses((int) $tenant['id']));
                        $academyStates = $repository->states((int) $tenant['id'], $userId);
                    } catch (\PDOException | \RuntimeException) {
                        $academyCourses = array_values(AcademyBuiltInCourses::all());
                        $academyStorageReady = false;
                    }
                }
            }
        } catch (\PDOException | \RuntimeException) {
            $accessState = 'unavailable';
        }
        return Response::view('academy.home', compact('account', 'tenant', 'accessState', 'legacyCourses', 'legacyEnrollments', 'staff', 'academyCourses', 'academyStates', 'academyStorageReady'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function qr(Request $request, array $params = []): Response
    {
        $image = (new \App\Services\Qr\QrPngGenerator())->png(url('academy/classroom'), 180);
        if ($image === null) {
            return (new Response())->setStatusCode(503);
        }
        return (new Response())->header('Content-Type', $image['mime'])->header('Cache-Control', 'private, no-store')->setBody($image['body']);
    }

    public function space(Request $request, array $params = []): Response
    {
        $space = (string) ($params['space'] ?? 'dashboard');
        $spaces = ['dashboard', 'catalogue', 'courses', 'classroom', 'laboratory', 'simulators', 'exams', 'library', 'notebook', 'record', 'instructor', 'studio', 'droits'];
        if (!in_array($space, $spaces, true)) {
            return (new Response())->setStatusCode(404);
        }
        $tenantId = TenantContext::id();
        $userId = (int) Session::get('user_id');
        try {
            $rights = $this->rights($tenantId, $userId);
            $staff = $rights->entersInstructor();
            if ($space === 'droits') {
                return $this->rightsPage($request, $rights);
            }
            if ($space === 'instructor' && !$staff) {
                return $this->error('forbidden', 403);
            }
            if ($space === 'studio') {
                return $this->studio($request, $rights);
            }
            $repository = new AcademyRepository();
            if (!Container::get(\App\Services\Platform\FeatureGateService::class)->allows($tenantId, 'training')) {
                return $this->error('module_unavailable', 403);
            }
            // Aperçu d'une formation (brouillon compris), sans inscription possible : encadrement, auteur ou personne invitée.
            $preview = false;
            if ($space === 'classroom' && $request->queryString('preview') === '1' && preg_match('/\A[a-z0-9-]{1,64}\z/', $request->queryString('course'))) {
                try {
                    $previewed = $repository->editable($tenantId, $request->queryString('course')) ?? AcademyBuiltInCourses::find($request->queryString('course'));
                } catch (\PDOException) {
                    $previewed = AcademyBuiltInCourses::find($request->queryString('course'));
                }
                $preview = $previewed !== null && $rights->canPreview($previewed);
            }
            $lmsData = $this->lms()->load($tenantId, $userId);
            $tenant = Container::get(TenantRepository::class)->findById($tenantId);
            $account = ['display_name'=>(string) Session::get('display_name',''), 'callsign'=>(string) Session::get('callsign','')];
            $academyStorageReady = true;
            try {
                $states = $repository->states($tenantId, $userId);
                $courses = $space === 'instructor' ? $repository->courses($tenantId, true) : $rights->visible($repository->courses($tenantId, $preview));
            } catch (\PDOException) {
                // Existing LMS remains usable before the optional Academy tables are migrated.
                $states = [];
                $courses = array_values(AcademyBuiltInCourses::all());
                $academyStorageReady = false;
            }
            $firstEnrollment = array_key_first(array_filter($states, static fn ($key): bool => $key !== '__notes', ARRAY_FILTER_USE_KEY));
            $key = $request->queryString('course', $firstEnrollment ?? ($courses[0]['key'] ?? ''));
            $selected = null;
            foreach ($courses as $course) {
                if ($course['key'] === $key) {
                    $selected = $course;
                    break;
                }
            }
            $state = $preview ? null : ($states[$key] ?? null);
            if ($state !== null) {
                $selected = $state['snapshot'] ?? $selected;
            }
            // Catalogue et tableau de bord : tout ce que le membre peut obtenir, avec son état.
            $offer = in_array($space, ['dashboard', 'catalogue'], true)
                ? AcademyOffer::collect($tenantId, (int) Session::get('user_id'), $tenant, $courses, $states, $lmsData['legacyCourses'] ?? [], $lmsData['legacyEnrollments'] ?? [], $staff)
                : null;
            $offerFilters = AcademyOffer::filters($request->queryParams());
            $offerDetail = $space === 'catalogue' ? $request->queryString('fiche') : '';
            return Response::view('academy.space', compact('space', 'staff', 'states', 'courses', 'selected', 'state', 'account', 'tenant', 'preview', 'offer', 'offerFilters', 'offerDetail') + $lmsData + [
                'academyStorageReady' => $academyStorageReady && !$preview,
                'viewStep' => $request->queryString('step'),
                'notice' => Session::get('academy_notice', ''),
            ] + ($space === 'instructor' ? $this->instructorData($repository, $rights, $tenantId, $academyStorageReady) : ['learners' => []]))->header('Cache-Control', 'private, no-store');
        } catch (\PDOException) {
            return $this->error('unavailable', 503);
        }
    }

    public function action(Request $request, array $params = []): Response
    {
        if (!Csrf::verify($request)) {
            return $this->error('csrf', 419);
        }
        $action = (string) ($params['action'] ?? '');
        $tenantId = TenantContext::id();
        $userId = (int) Session::get('user_id');
        $key = $request->input('course', 'academy');
        if (!is_string($key) || !preg_match('/\A[a-z0-9-]{1,64}\z/', $key)) {
            return $this->error('invalid_content', 422);
        }
        try {
            if (!Container::get(\App\Services\Platform\FeatureGateService::class)->allows($tenantId, 'training')) {
                return $this->error('module_unavailable', 403);
            }
            $repository = new AcademyRepository();
            $rights = $this->rights($tenantId, $userId);
            $returnSpace = 'classroom';
            if (in_array($action, ['rights_save', 'rights_reset'], true)) {
                return $this->rightsAction($request, $rights, $action, $tenantId, $userId);
            }
            if ($action === 'course_access_save') {
                return $this->courseAccessAction($request, $repository, $rights, $key, $tenantId, $userId);
            }
            if ($action === 'assign') {
                return $this->assignAction($request, $repository, $rights, $key, $tenantId, $userId);
            }
            if ($action === 'enroll') {
                $course = $repository->course($tenantId, $key);
                // Un cours au public restreint n'existe pas pour qui n'en fait pas partie.
                if (!$course || !$rights->canSee($course)) {
                    return $this->error('not_found', 404);
                }
                $repository->enroll($tenantId, $userId, $course);
            } elseif ($action === 'notes') {
                $notes = $request->input('notes', '');
                if (!is_string($notes) || mb_strlen($notes) > 30000) {
                    return $this->error('invalid_content', 422);
                }
                $repository->saveNotes($tenantId, $userId, $notes);
                $returnSpace = 'notebook';
                if ($request->inputString('return') === 'classroom') {
                    // Carnet ouvert depuis la salle de cours : on revient à l'étape consultée.
                    Session::set('academy_notice', 'saved');
                    return Response::redirect($this->classroomUrl($key, $request->inputString('step')), 303);
                }
            } elseif (str_starts_with($action, 'studio_')) {
                // Droits vérifiés action par action (encadrement, ou éditeur invité sur cette formation).
                return $this->studioAction($request, $repository, $action, $key, $tenantId, $userId);
            } elseif (in_array($action, ['publish', 'validate'], true)) {
                if ($action === 'publish') {
                    $draft = $repository->editable($tenantId, $key);
                    if ($draft === null || !$rights->canPublish($draft)) {
                        return $this->error('forbidden', 403);
                    }
                    $repository->publishDraft($tenantId, $key);
                } else {
                    // Valider : droit « valider » de la communauté, instructeur invité sur ce cours ou auteur du cours.
                    if (!$rights->canTeach($key)) {
                        return $this->error('forbidden', 403);
                    }
                    $learnerId = (int) $request->input('learner', 0);
                    if ($learnerId === $userId) {
                        return $this->error('self_validation', 403);
                    }
                    $comment = $request->input('comment', '');
                    if (!is_string($comment)) {
                        return $this->error('invalid_content', 422);
                    }
                    $expectedChapter = $request->input('expected_chapter');
                    $certify = $rights->can('certify');
                    $repository->mutate($tenantId, $learnerId, $key, static function (array $state) use ($expectedChapter, $userId, $comment, $certify): array {
                        if (!is_scalar($expectedChapter) || (string) $expectedChapter !== (string) $state['chapter']) {
                            throw new \DomainException('locked');
                        }
                        // La validation du dernier chapitre termine le cours et délivre l'attestation : droit « délivrer ».
                        if (!$certify && (int) $state['chapter'] + 1 >= count($state['snapshot']['chapters'] ?? [])) {
                            throw new \DomainException('certify_required');
                        }
                        return AcademyCurriculum::validateChapter($state, $state['snapshot'], $userId, $comment, time());
                    });
                    $this->awardBuiltInQualification($repository, $tenantId, $learnerId, $key, $userId);
                    $this->audit('academy.learner.validated', $tenantId, $key, ['learner' => $learnerId, 'chapter' => is_scalar($expectedChapter) ? (int) $expectedChapter : null]);
                }
                $returnSpace = 'instructor';
            } else {
                $input = ['answer' => $request->input('answer'), 'answers' => $request->input('answers'), 'token' => $request->input('token'), 'reflection' => $request->input('reflection'), 'expected_stage' => $request->input('expected_stage'), 'expected_chapter' => $request->input('expected_chapter')];
                $repository->mutate($tenantId, $userId, $key, static fn (array $state): array => AcademyCurriculum::transition($state, $state['snapshot'], $action, $input, time()));
            }
            Session::set('academy_notice', 'saved');
            return Response::redirect(url('academy/' . $returnSpace . '?course=' . rawurlencode($key)), 303);
        } catch (\DomainException | \InvalidArgumentException | \JsonException $e) {
            if ($e->getMessage() === 'certify_required') {
                return $this->error('certify_required', 403);
            }
            return $this->error(in_array($e->getMessage(), ['locked', 'reflection_required', 'not_enrolled'], true) ? $e->getMessage() : 'invalid_content', 422);
        } catch (\PDOException) {
            return $this->error('unavailable', 503);
        }
    }

    /**
     * Dernier chapitre d’un cours intégré validé : la qualification Athena Academy du cours est créée si besoin dans le
     * référentiel (mode « Formation Academy ») puis attribuée par la règle automatique. Ne bloque jamais la validation.
     */
    private function awardBuiltInQualification(AcademyRepository $repository, int $tenantId, int $learnerId, string $key, int $instructorId): void
    {
        if (!isset(AcademyBuiltInCourses::qualifications()[$key])) {
            return;
        }
        try {
            $state = $repository->states($tenantId, $learnerId)[$key] ?? null;
            if (!is_array($state) || ($state['completed'] ?? false) !== true) {
                return;
            }
            $definitionId = \App\Services\Academy\AcademyBuiltInQualifications::forPdo(\App\Core\Database::getPdo())
                ->ensureDefinition($tenantId, $key, function_exists('locale') ? (string) locale() : 'fr', $instructorId);
            if ($definitionId > 0) {
                Container::get(\App\Services\Personnel\QualificationAwardRuleService::class)->runAuto($tenantId, $definitionId);
            }
        } catch (\Throwable $e) {
            error_log('[academy] qualification du cours ' . $key . ' : ' . $e->getMessage());
        }
    }

    private function classroomUrl(string $key, string $step = ''): string
    {
        return url('academy/classroom?course=' . rawurlencode($key) . (preg_match('/\A\d{1,2}\.\d\z/', $step) ? '&step=' . $step : ''));
    }

    private function studioUrl(string $key, string $step = '', string $language = ''): string
    {
        return url('academy/studio' . ($key !== '' ? '?course=' . rawurlencode($key) . (preg_match('/\A\d{1,2}\.\d\z/', $step) ? '&step=' . $step : '')
            . ($language === 'en' ? '&edit=en' : '') : ''));
    }

    private function importer(): GoogleSlidesImporter
    {
        return Container::get(GoogleSlidesImporter::class);
    }

    private function shareRepository(): ?AcademyCourseShareRepository
    {
        $shares = Container::get(AcademyCourseShareRepository::class);
        return $shares->ready() ? $shares : null;
    }

    private function accessRepository(): AcademyAccessRepository
    {
        return Container::get(AcademyAccessRepository::class);
    }

    /** Droits Academy du membre connecté, calculés une fois par requête (partages et liens ouverts dans la session compris). */
    private function rights(int $tenantId, int $userId): AcademyRights
    {
        $grants = Session::get('academy_link_grants', []);
        $cacheKey = $tenantId . ':' . $userId . ':' . md5(serialize($grants));
        if ($this->rightsCache === null || $this->rightsCache[0] !== $cacheKey) {
            $this->rightsCache = [$cacheKey, AcademyRights::current($tenantId, $userId, is_array($grants) ? $grants : [])];
        }
        return $this->rightsCache[1];
    }

    private function audit(string $action, int $tenantId, string $key, array $details = []): void
    {
        try {
            Container::get(AuditService::class)->logChange($action, $tenantId, (int) Session::get('user_id') ?: null, 'academy_course', null, [], ['course' => $key] + $details);
        } catch (\Throwable $e) {
            // Le journal ne bloque jamais l'action elle-même.
            error_log('[audit] ' . $action . ' : ' . $e->getMessage());
        }
    }

    /** @return array<int,string> membres actifs de la communauté : identifiant => indicatif ou nom */
    private function memberNames(int $tenantId): array
    {
        $members = [];
        foreach (Container::get(UserRepository::class)->listForTenant($tenantId, null, 'active', null, 500) as $member) {
            $members[(int) $member['id']] = trim((string) ($member['callsign'] ?? '')) !== '' ? (string) $member['callsign']
                : (trim(((string) ($member['first_name'] ?? '')) . ' ' . ((string) ($member['last_name'] ?? ''))) ?: '#' . (int) $member['id']);
        }
        asort($members, SORT_NATURAL | SORT_FLAG_CASE);
        return $members;
    }

    /** Listes de choix des réglages d'accès : rôles d'accès, fonctions, unités, membres. */
    private function accessOptions(int $tenantId): array
    {
        $access = $this->accessRepository();
        return ['role' => $access->accessRoleOptions($tenantId), 'job' => $access->jobOptions($tenantId), 'unit' => $access->unitOptions($tenantId), 'user' => $this->memberNames($tenantId)];
    }

    /**
     * Sujets envoyés par un formulaire de droits, gardés seulement s'ils appartiennent à la communauté.
     *
     * @return list<array{type:string,id:int}>
     */
    private function subjectsFromForm(Request $request, array $options, bool $withStaff): array
    {
        $subjects = [];
        if ($withStaff && $request->inputString('staff') === '1') {
            $subjects[] = ['type' => 'staff', 'id' => 0];
        }
        foreach (['role' => 'roles', 'job' => 'jobs', 'unit' => 'units', 'user' => 'users'] as $type => $field) {
            $values = $request->input($field, []);
            foreach (is_array($values) ? $values : [] as $value) {
                $id = is_scalar($value) && ctype_digit((string) $value) ? (int) $value : 0;
                if ($id > 0 && isset($options[$type][$id])) {
                    $subjects[] = ['type' => $type, 'id' => $id];
                }
            }
        }
        return $subjects;
    }

    /** Espace de création : accueil (formations de la communauté), ou édition d'une formation. */
    private function studio(Request $request, AcademyRights $rights): Response
    {
        $tenantId = TenantContext::id();
        $userId = (int) Session::get('user_id');
        try {
            if (!Container::get(\App\Services\Platform\FeatureGateService::class)->allows($tenantId, 'training')) {
                return $this->error('module_unavailable', 403);
            }
            $repository = new AcademyRepository();
            $shares = $this->shareRepository();
            $access = $this->accessRepository();
            $key = $request->queryString('course');
            $course = null;
            $role = null;
            if ($key !== '') {
                $course = $repository->editable($tenantId, $key) ?? AcademyBuiltInCourses::find($key);
                $role = $course !== null ? $rights->studioRole($course) : null;
                if ($course === null || $role === null) {
                    return $this->error($course === null ? 'not_found' : 'forbidden', $course === null ? 404 : 403);
                }
                if ($role === 'viewer') {
                    // Lecteur ou instructeur invité : il voit l'aperçu stagiaire, pas l'éditeur.
                    return Response::redirect(url('academy/classroom?course=' . rawurlencode($key) . '&preview=1'), 303);
                }
            }
            $manager = $rights->can('publish');
            $sharedWithMe = $rights->sharedCourses();
            if ($course === null && !$rights->entersStudio()) {
                return $this->error('forbidden', 403);
            }
            $list = [];
            if ($course === null) {
                if ($manager && $repository->lifecycleReady()) {
                    foreach ($repository->purgeTrash($tenantId) as $purged) {
                        $shares?->forget($tenantId, $purged);
                        $access->forget($tenantId, $purged);
                        $this->audit('academy.course.purged', $tenantId, $purged);
                    }
                }
                // Chacun ne voit que les cours qu'il peut ouvrir (brouillons privés des autres auteurs exclus).
                $list = array_values(array_filter($repository->studioList($tenantId), static fn (array $item): bool => $rights->studioRole($item) !== null));
            }
            $sharing = null;
            if ($course !== null && empty($course['built_in']) && $shares !== null && $rights->canConfigure($course)) {
                $sharing = ['shares' => $shares->shares($tenantId, $key), 'link' => $shares->link($tenantId, $key), 'members' => $this->memberNames($tenantId),
                    'canLink' => $rights->canPublish($course), 'access' => $this->courseAccessView($rights, $tenantId, $key, true)];
            }
            $tenant = Container::get(TenantRepository::class)->findById($tenantId);
            $account = ['display_name' => (string) Session::get('display_name', ''), 'callsign' => (string) Session::get('callsign', '')];
            $notice = (string) Session::get('academy_notice', '');
            Session::set('academy_notice', '');
            return Response::view('academy.studio', [
                'space' => 'studio', 'staff' => $manager, 'canCreate' => $rights->can('create'), 'canShare' => $sharing !== null,
                'role' => $role, 'tenant' => $tenant, 'account' => $account, 'notice' => $notice,
                'course' => $course, 'studioCourses' => $list, 'sharedWithMe' => $sharedWithMe,
                'builtInCourses' => $course === null && $rights->can('create') ? array_values(AcademyBuiltInCourses::all()) : [],
                'issues' => $course !== null ? AcademyStudio::issues($course) : [],
                'learnerCount' => $course !== null && empty($course['built_in']) ? $repository->learnerCount($tenantId, $key) : 0,
                'lifecycleReady' => $repository->lifecycleReady(), 'sharing' => $sharing, 'sharingReady' => $shares !== null,
                'viewStep' => $request->queryString('step'),
                'editLang' => $request->queryString('edit') === 'en' ? 'en' : 'fr',
                'openShare' => $request->queryString('share') === '1',
                'studioView' => $request->queryString('vue'),
            ])->header('Cache-Control', 'private, no-store');
        } catch (\PDOException) {
            // Tables Academy absentes : la création attend l'étape de migration.
            return $this->error('unavailable', 503);
        }
    }

    /** Réglages d'accès d'un cours pour le formulaire « Qui voit ce cours » (Studio et écran des droits). */
    private function courseAccessView(AcademyRights $rights, int $tenantId, string $key, bool $withOptions): array
    {
        $access = $this->accessRepository();
        return ['ready' => $access->ready(), 'settings' => $rights->settings($key), 'audience' => $rights->audience($key),
            'options' => $withOptions ? $this->accessOptions($tenantId) : []];
    }

    private function studioAction(Request $request, AcademyRepository $repository, string $action, string $key, int $tenantId, int $userId): Response
    {
        $rights = $this->rights($tenantId, $userId);
        $access = $this->accessRepository();
        $step = $request->inputString('step');
        if ($action === 'studio_new') {
            if (!$rights->can('create')) {
                return $this->error('forbidden', 403);
            }
            // Fenêtre « Nouveau cours » : titre, présentation et couverture en français, le reste se rédige dans l'éditeur.
            $blank = AcademyStudio::blankCourse();
            $start = AcademyStudio::fromForm(['title' => ['fr' => $request->inputString('new_title')], 'summary' => ['fr' => $request->inputString('new_summary')],
                'cover' => $request->inputString('new_cover'), 'chapters' => $blank['chapters']], []);
            $created = $repository->createDraft($tenantId, $userId, $start);
            // L'auteur garde la main sur son cours (modifier, régler son public), même sans droit « modifier » général.
            $access->saveSettings($tenantId, $created, ['owner_id' => $userId, 'private_draft' => $request->inputString('new_private') === '1'], $userId);
            $this->audit('academy.course.created', $tenantId, $created);
            return Response::redirect($this->studioUrl($created), 303);
        }
        if ($action === 'studio_trash_restore') {
            if (!$rights->can('publish')) {
                return $this->error('forbidden', 403);
            }
            $restored = $repository->lifecycleReady() && $repository->restoreFromTrash($tenantId, $key);
            if ($restored) {
                $this->audit('academy.course.restored_from_trash', $tenantId, $key);
            }
            Session::set('academy_notice', $restored ? 'studio_trash_restored' : 'locked');
            return Response::redirect($this->studioUrl(''), 303);
        }
        $course = $repository->editable($tenantId, $key);
        if (in_array($action, ['studio_archive', 'studio_unarchive', 'studio_delete'], true)) {
            if ($course !== null && !$rights->canPublish($course)) {
                return $this->error('forbidden', 403);
            }
            if ($course === null && !$rights->can('publish')) {
                return $this->error('forbidden', 403);
            }
            if ($course === null || !$repository->lifecycleReady()) {
                Session::set('academy_notice', $course === null ? 'not_found' : 'studio_lifecycle_pending');
                return Response::redirect($this->studioUrl(''), 303);
            }
            if ($action === 'studio_delete') {
                $expected = trim((string) ($course['title']['fr'] ?? '')) ?: trim((string) ($course['title']['en'] ?? ''));
                $typed = trim($request->inputString('confirm_title'));
                if ($expected === '' || ($typed !== $expected && $typed !== trim((string) ($course['title']['en'] ?? '')))) {
                    Session::set('academy_notice', 'studio_delete_confirm');
                    return Response::redirect($this->studioUrl(''), 303);
                }
                try {
                    $repository->trash($tenantId, $key);
                } catch (\DomainException) {
                    // Des stagiaires suivent cette formation : on propose l'archivage, qui garde leur accès.
                    Session::set('academy_notice', 'studio_delete_has_learners');
                    return Response::redirect($this->studioUrl(''), 303);
                }
                $this->audit('academy.course.trashed', $tenantId, $key);
                Session::set('academy_notice', 'studio_deleted');
                return Response::redirect($this->studioUrl(''), 303);
            }
            $repository->setArchived($tenantId, $key, $action === 'studio_archive');
            $this->audit($action === 'studio_archive' ? 'academy.course.archived' : 'academy.course.unarchived', $tenantId, $key);
            Session::set('academy_notice', $action === 'studio_archive' ? 'studio_archived' : 'studio_unarchived');
            return Response::redirect($this->studioUrl($action === 'studio_archive' ? '' : $key), 303);
        }
        if (in_array($action, ['studio_share_add', 'studio_share_remove', 'studio_link_save', 'studio_link_regenerate'], true)) {
            if ($course === null) {
                return $this->error('not_found', 404);
            }
            // Personnes invitées : auteur ou droit « publier » ; lien d'accès (jusqu'au lien public) : droit « publier ».
            $allowed = in_array($action, ['studio_share_add', 'studio_share_remove'], true) ? $rights->canConfigure($course) : $rights->canPublish($course);
            if (!$allowed) {
                return $this->error('forbidden', 403);
            }
            return $this->shareAction($request, $action, $course, $key, $tenantId, $userId);
        }
        if (in_array($action, ['studio_revise', 'studio_duplicate'], true)) {
            $source = $course ?? AcademyBuiltInCourses::find($key);
            if ($source === null) {
                return $this->error('not_found', 404);
            }
            if (!$rights->can('create') || $rights->studioRole($source) === null) {
                return $this->error('forbidden', 403);
            }
            // Nouvelle version d'un cours publié : l'original reste en ligne jusqu'à la publication. Copie : cours indépendant.
            $copy = $source;
            unset($copy['built_in'], $copy['license_code'], $copy['qualification'], $copy['demo'], $copy['replaces'], $copy['updated_at'], $copy['archived']);
            $revision = $action === 'studio_revise' && $course !== null && $course['published'];
            if ($revision) {
                $copy['replaces'] = $key;
            } else {
                foreach (['fr' => ' (copie)', 'en' => ' (copy)'] as $language => $suffix) {
                    $copy['title'][$language] = trim((string) ($copy['title'][$language] ?? '')) . $suffix;
                }
            }
            $created = $repository->createDraft($tenantId, $userId, $copy);
            if ($course !== null) {
                // La nouvelle version garde le public de l'original : elle le remplacera tel quel.
                $access->copyCourse($tenantId, $key, $created, $userId);
            } else {
                $access->saveSettings($tenantId, $created, ['owner_id' => $userId], $userId);
            }
            $this->audit($revision ? 'academy.course.revised' : 'academy.course.duplicated', $tenantId, $created, ['from' => $key]);
            Session::set('academy_notice', $revision ? 'studio_revision' : 'studio_copied');
            return Response::redirect($this->studioUrl($created, $step), 303);
        }
        if (!in_array($action, ['studio_save', 'studio_import', 'studio_publish'], true)) {
            return $this->error('invalid_content', 422);
        }
        if ($course !== null && !($action === 'studio_publish' ? $rights->canPublish($course) : $rights->canEdit($course))) {
            return $this->error('forbidden', 403);
        }
        if ($course === null && !$rights->can('edit') && !$rights->can('publish')) {
            return $this->error('forbidden', 403);
        }
        if ($course === null || $course['published'] || !empty($course['archived'])) {
            return $this->error('locked', 409);
        }
        $form = $request->input('draft');
        $draft = AcademyStudio::fromForm(is_array($form) ? $form : [], $course);
        $operation = $request->inputString('op');
        if ($operation !== '') {
            $draft = AcademyStudio::applyOperation($draft, $operation);
            if ($operation === 'add_chapter') {
                $step = (count($draft['chapters']) - 1) . '.0';
            }
        }
        $notice = 'saved';
        if ($action === 'studio_import') {
            [$draft, $notice, $step] = $this->importSlides($request, $draft, $tenantId, $step);
        }
        $repository->updateDraft($tenantId, $key, $userId, $draft);
        if ($action === 'studio_publish') {
            if (AcademyStudio::issues($draft) !== []) {
                Session::set('academy_notice', 'studio_not_ready');
                return Response::redirect($this->studioUrl($key, $step, $request->inputString('edit_lang')), 303);
            }
            $repository->publishDraft($tenantId, $key);
            $this->audit('academy.course.published', $tenantId, $key);
            $notice = 'studio_published';
        }
        Session::set('academy_notice', $notice);
        return Response::redirect($this->studioUrl($key, $step, $request->inputString('edit_lang')), 303);
    }

    /** Fenêtre « Partager » : personnes (lecteur / éditeur / instructeur) et lien d'accès. Droits vérifiés par studioAction(). */
    private function shareAction(Request $request, string $action, ?array $course, string $key, int $tenantId, int $userId): Response
    {
        $shares = $this->shareRepository();
        $back = $this->studioUrl($key) . '&share=1';
        if ($course === null) {
            return $this->error('not_found', 404);
        }
        if ($shares === null) {
            Session::set('academy_notice', 'studio_lifecycle_pending');
            return Response::redirect($back, 303);
        }
        $notice = 'saved';
        if ($action === 'studio_share_add') {
            $member = (int) $request->inputString('share_user');
            $role = $request->inputString('share_role');
            if (!in_array($role, AcademyCourseShareRepository::ROLES, true) || $member < 1
                || !Container::get(UserRepository::class)->isMemberOfTenant($member, $tenantId)) {
                Session::set('academy_notice', 'share_invalid_member');
                return Response::redirect($back, 303);
            }
            $before = $shares->roleFor($tenantId, $key, $member);
            $shares->grant($tenantId, $key, $member, $role, $userId);
            $this->audit('academy.course.shared', $tenantId, $key, ['user' => $member, 'role' => $role, 'previous_role' => $before]);
            $notice = 'share_added';
        } elseif ($action === 'studio_share_remove') {
            $member = (int) $request->inputString('share_user');
            if ($shares->revoke($tenantId, $key, $member)) {
                $this->audit('academy.course.unshared', $tenantId, $key, ['user' => $member]);
            }
            $notice = 'share_removed';
        } else {
            $access = $request->inputString('link_access');
            $role = $access === 'public' ? 'viewer' : $request->inputString('link_role', 'viewer');
            $expiry = $request->inputString('link_expiry', 'never');
            $days = ['never' => null, '1' => 1, '7' => 7, '30' => 30][$expiry] ?? null;
            $current = $shares->link($tenantId, $key);
            // Renouveler le lien garde son échéance ; « Enregistrer » applique celle choisie.
            $expiresAt = $expiry === 'keep' ? ($current !== null && $current['expires_at'] !== null ? (int) strtotime($current['expires_at']) : null)
                : ($days !== null ? time() + $days * 86400 : null);
            if ($access === 'off') {
                if ($shares->disableLink($tenantId, $key)) {
                    $this->audit('academy.course.link_disabled', $tenantId, $key);
                }
                $notice = 'share_link_off';
            } else {
                try {
                    // L'instructeur invité passe par une invitation nominative, jamais par un lien.
                    if ($role === 'instructor') {
                        throw new \InvalidArgumentException('invalid_content');
                    }
                    $shares->saveLink($tenantId, $key, $access, $role, $expiresAt, $userId, $action === 'studio_link_regenerate');
                } catch (\InvalidArgumentException) {
                    Session::set('academy_notice', 'share_invalid_link');
                    return Response::redirect($back, 303);
                }
                $this->audit($action === 'studio_link_regenerate' ? 'academy.course.link_regenerated' : 'academy.course.link_saved', $tenantId, $key, ['access' => $access, 'role' => $role, 'days' => $days]);
                $notice = $action === 'studio_link_regenerate' ? 'share_link_regenerated' : 'share_link_saved';
            }
        }
        Session::set('academy_notice', $notice);
        return Response::redirect($back, 303);
    }

    /**
     * « Qui voit ce cours » : toute la communauté, ou un public choisi (unités, rôles d'accès, fonctions, membres) ;
     * brouillon privé (auteur, personnes invitées et administrateurs). Auteur du cours ou droit « publier ».
     */
    private function courseAccessAction(Request $request, AcademyRepository $repository, AcademyRights $rights, string $key, int $tenantId, int $userId): Response
    {
        $course = $repository->editable($tenantId, $key) ?? AcademyBuiltInCourses::find($key);
        if ($course === null) {
            return $this->error('not_found', 404);
        }
        if (!$rights->canConfigure($course)) {
            return $this->error('forbidden', 403);
        }
        $back = $request->inputString('return') === 'droits' ? url('academy/droits') . '#cours-' . rawurlencode($key)
            : $this->studioUrl($key) . '&share=1';
        $access = $this->accessRepository();
        if (!$access->ready()) {
            Session::set('academy_notice', 'rights_pending');
            return Response::redirect($back, 303);
        }
        $audience = $request->inputString('audience') === 'restricted' ? 'restricted' : 'community';
        $subjects = $audience === 'restricted' ? $this->subjectsFromForm($request, $this->accessOptions($tenantId), false) : null;
        $private = empty($course['built_in']) && empty($course['published']) && $request->inputString('private_draft') === '1';
        $before = ['settings' => $rights->settings($key), 'audience' => $rights->audience($key)];
        $access->saveSettings($tenantId, $key, ['audience' => $audience, 'private_draft' => $private], $userId);
        $access->replaceRule($tenantId, $key, 'view', $subjects, $userId);
        $this->audit('academy.course.access_updated', $tenantId, $key, ['audience' => $audience, 'subjects' => $subjects ?? [], 'private_draft' => $private, 'before' => $before]);
        Session::set('academy_notice', $audience === 'restricted' && $subjects === [] ? 'access_saved_nobody' : 'access_saved');
        return Response::redirect($back, 303);
    }

    /** Inscrire un membre à un cours publié (droit « inscrire ») ; le membre doit faire partie du public du cours. */
    private function assignAction(Request $request, AcademyRepository $repository, AcademyRights $rights, string $key, int $tenantId, int $userId): Response
    {
        if (!$rights->can('assign')) {
            return $this->error('forbidden', 403);
        }
        $member = (int) $request->inputString('member');
        $course = $repository->course($tenantId, $key);
        if ($course === null || $member < 1 || !Container::get(UserRepository::class)->isMemberOfTenant($member, $tenantId)) {
            Session::set('academy_notice', 'assign_invalid');
            return Response::redirect(url('academy/instructor'), 303);
        }
        $target = $this->rightsForMember($tenantId, $member);
        if (!$target->canSee($course)) {
            Session::set('academy_notice', 'assign_outside_audience');
            return Response::redirect(url('academy/instructor'), 303);
        }
        $repository->enroll($tenantId, $member, $course);
        $this->audit('academy.course.assigned', $tenantId, $key, ['user' => $member]);
        Session::set('academy_notice', 'assign_done');
        return Response::redirect(url('academy/instructor') . '#eleves', 303);
    }

    /** Droits d'un autre membre, calculés à partir des permissions de ses rôles (aperçu « voir comme », inscription). */
    private function rightsForMember(int $tenantId, int $memberId): AcademyRights
    {
        $access = $this->accessRepository();
        $memberships = $access->memberships($tenantId, $memberId);
        try {
            $permissions = (new \App\Services\Rbac\RbacService())->loadPermissionsForRoles($memberships['roles']);
        } catch (\Throwable) {
            $permissions = [];
        }
        return AcademyRights::forMember($tenantId, $memberId, $permissions, $access, $this->shareRepository());
    }

    /** Écran « Qui peut faire quoi dans l'Academy » : administrateurs et responsables de la formation. */
    private function rightsPage(Request $request, AcademyRights $rights): Response
    {
        if (!$rights->canManageRights()) {
            return $this->error('rights_forbidden', 403);
        }
        $tenantId = TenantContext::id();
        $access = $this->accessRepository();
        $options = $this->accessOptions($tenantId);
        $matrix = [];
        foreach (AcademyRights::CAPABILITIES as $capability) {
            $matrix[$capability] = ['configured' => $rights->configured($capability), 'subjects' => $rights->subjects($capability)];
        }
        $courses = [];
        try {
            $repository = new AcademyRepository();
            foreach (AcademyBuiltInCourses::all() as $builtIn) {
                $courses[] = $builtIn;
            }
            foreach ($repository->studioList($tenantId) as $item) {
                if (empty($item['deleted_at'])) {
                    $courses[] = $item;
                }
            }
        } catch (\PDOException) {
        }
        foreach ($courses as &$item) {
            $item['access'] = $this->courseAccessView($rights, $tenantId, (string) $item['key'], false);
        }
        unset($item);
        $preview = null;
        $previewId = (int) $request->queryString('apercu');
        if ($previewId > 0 && isset($options['user'][$previewId])) {
            $as = $this->rightsForMember($tenantId, $previewId);
            $preview = ['id' => $previewId, 'name' => $options['user'][$previewId], 'can' => $as->all(), 'courses' => array_map(static fn (array $course): array => [
                'key' => $course['key'], 'title' => $course['title'], 'see' => $as->canSee($course) && !empty($course['published']) && empty($course['archived']),
                'edit' => $as->canEdit($course), 'teach' => $as->canTeach((string) $course['key']), 'preview' => $as->canPreview($course),
            ], $courses)];
        }
        $notice = (string) Session::get('academy_notice', '');
        Session::set('academy_notice', '');
        return Response::view('academy.space', [
            'space' => 'droits', 'staff' => true, 'states' => [], 'courses' => [], 'selected' => null, 'state' => null, 'learners' => [], 'preview' => false, 'tenant' => Container::get(TenantRepository::class)->findById($tenantId),
            'account' => ['display_name' => (string) Session::get('display_name', ''), 'callsign' => (string) Session::get('callsign', '')],
            'notice' => $notice, 'rightsReady' => $access->ready(), 'matrix' => $matrix, 'options' => $options, 'rightsCourses' => $courses,
            'rightsPreview' => $preview, 'rightsAdmin' => $rights->isAdmin(), 'editing' => $request->queryString('modifier'), 'editingCourse' => $request->queryString('cours'),
        ])->header('Cache-Control', 'private, no-store');
    }

    /** Enregistrer ou rétablir un droit de la communauté. Chaque changement est inscrit au journal (avant / après). */
    private function rightsAction(Request $request, AcademyRights $rights, string $action, int $tenantId, int $userId): Response
    {
        if (!$rights->canManageRights()) {
            return $this->error('rights_forbidden', 403);
        }
        $capability = $request->inputString('capability');
        if (!in_array($capability, AcademyRights::CAPABILITIES, true)) {
            return $this->error('invalid_content', 422);
        }
        $back = url('academy/droits') . '#droit-' . $capability;
        $access = $this->accessRepository();
        if (!$access->ready()) {
            Session::set('academy_notice', 'rights_pending');
            return Response::redirect($back, 303);
        }
        $before = ['configured' => $rights->configured($capability), 'subjects' => $rights->subjects($capability)];
        if ($action === 'rights_reset') {
            $access->replaceRule($tenantId, '', $capability, null, $userId);
            $after = ['configured' => false, 'subjects' => [['type' => 'staff', 'id' => 0]]];
        } else {
            $subjects = $this->subjectsFromForm($request, $this->accessOptions($tenantId), true);
            $access->replaceRule($tenantId, '', $capability, array_merge([['type' => AcademyRights::MARKER, 'id' => 0]], $subjects), $userId);
            $after = ['configured' => true, 'subjects' => $subjects];
        }
        try {
            Container::get(AuditService::class)->logChange('academy.rights.updated', $tenantId, $userId ?: null, 'academy_rights', null,
                ['capability' => $capability] + $before, ['capability' => $capability] + $after);
        } catch (\Throwable $e) {
            error_log('[audit] academy.rights.updated : ' . $e->getMessage());
        }
        Session::set('academy_notice', $action === 'rights_reset' ? 'rights_reset_done' : 'rights_saved');
        return Response::redirect($back, 303);
    }

    /**
     * Espace instructeur : à traiter (débriefings à valider, examens à reprendre, demandes d'inscription), cours encadrés,
     * séances, élèves et leur progression. Chacun ne voit que les élèves des cours qu'il encadre, sauf droit « statistiques ».
     */
    private function instructorData(AcademyRepository $repository, AcademyRights $rights, int $tenantId, bool $storageReady): array
    {
        $userId = (int) Session::get('user_id');
        $learners = $managed = [];
        if ($storageReady) {
            try {
                $all = $rights->can('stats') || $rights->can('validate');
                $learners = array_values(array_filter($repository->learners($tenantId), static fn (array $row): bool => $all || $rights->canTeach((string) $row['course_key'])));
                foreach (AcademyBuiltInCourses::all() as $builtIn) {
                    if ($rights->can('validate') || $rights->can('stats') || $rights->can('create')) {
                        $managed[] = $builtIn;
                    }
                }
                foreach ($repository->studioList($tenantId) as $item) {
                    if (empty($item['deleted_at']) && ($rights->studioRole($item) !== null || $rights->canTeach((string) $item['key']))) {
                        $managed[] = $item;
                    }
                }
            } catch (\PDOException) {
                $learners = [];
            }
        }
        $settings = [];
        foreach ($managed as $course) {
            $settings[$course['key']] = $rights->settings((string) $course['key']);
        }
        $pendingRequests = 0;
        if ($rights->can('assign')) {
            try {
                $pendingRequests = count(Container::get(TrainingEnrollmentRepository::class)->listPendingApproval($tenantId));
            } catch (\Throwable) {
                $pendingRequests = 0;
            }
        }
        $assignable = [];
        if ($rights->can('assign')) {
            try {
                $assignable = array_values(array_filter($repository->courses($tenantId), static fn (array $course): bool => $rights->canSee($course)));
            } catch (\PDOException) {
                $assignable = [];
            }
        }
        return [
            'learners' => $learners,
            'instructor' => [
                'can' => $rights->all(), 'manageRights' => $rights->canManageRights(), 'entersStudio' => $rights->entersStudio(),
                'members' => $this->memberNames($tenantId), 'managed' => $managed, 'settings' => $settings,
                'teach' => array_fill_keys(array_values(array_filter(array_map(static fn (array $row): string => (string) $row['course_key'], $learners), static fn (string $key): bool => $rights->canTeach($key))), true),
                'sessions' => $this->accessRepository()->upcomingSessionsFor($tenantId, $userId),
                'pendingRequests' => $pendingRequests, 'assignable' => $assignable,
            ],
        ];
    }

    /**
     * Lien de partage : « communauté » ouvre l'éditeur ou l'aperçu au membre connecté de la communauté ;
     * « public » montre une lecture seule de la formation, sans compte ni inscription.
     */
    public function shared(Request $request, array $params = []): Response
    {
        $token = (string) ($params['token'] ?? '');
        try {
            $shares = $this->shareRepository();
            $link = $shares?->resolve($token);
            $course = $link !== null ? (new AcademyRepository())->editable($link['tenant_id'], $link['course_key']) : null;
            if ($link === null || $course === null || !empty($course['archived'])) {
                return $this->error('share_invalid', 404);
            }
            if ($link['access'] === 'public') {
                return Response::view('academy.shared', ['course' => $course, 'token' => $token, 'space' => 'shared'])
                    ->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow')->header('Referrer-Policy', 'no-referrer');
            }
            if ((int) Session::get('user_id') < 1) {
                return Response::redirect(url('login?redirect=' . rawurlencode('academy/partage/' . $token)), 303);
            }
            if (TenantContext::id() !== $link['tenant_id']) {
                return $this->error('share_other_community', 403);
            }
            $grants = Session::get('academy_link_grants', []);
            $grants = is_array($grants) ? $grants : [];
            $grants[$link['tenant_id'] . ':' . $link['course_key']] = $link['token_hash'];
            Session::set('academy_link_grants', $grants);
            $this->audit('academy.course.link_opened', $link['tenant_id'], $link['course_key'], ['role' => $link['role']]);
            return Response::redirect($link['role'] === 'editor' && empty($course['published'])
                ? $this->studioUrl($link['course_key'])
                : url('academy/classroom?course=' . rawurlencode($link['course_key']) . '&preview=1'), 303);
        } catch (\PDOException) {
            return $this->error('unavailable', 503);
        }
    }

    /** Diapositives d'une formation ouverte par un lien public. */
    public function sharedMedia(Request $request, array $params = []): Response
    {
        try {
            $link = $this->shareRepository()?->resolve((string) ($params['token'] ?? ''));
            $course = $link !== null && $link['access'] === 'public' ? (new AcademyRepository())->editable($link['tenant_id'], $link['course_key']) : null;
            $file = (string) ($params['file'] ?? '');
            if ($course === null || !empty($course['archived']) || !in_array($file, AcademyStudio::files($course), true)) {
                return (new Response())->setStatusCode(404);
            }
            return $this->pdf($this->importer()->path($link['tenant_id'], $file));
        } catch (\PDOException) {
            return (new Response())->setStatusCode(503);
        }
    }

    /**
     * Ajoute (ou met à jour) des diapositives Google Slides : nouveau chapitre, ou cours théorique d'un chapitre.
     * Une présentation déjà importée dans ce cours est remplacée à sa place, sans doublon.
     *
     * @return array{0:array,1:string,2:string}
     */
    private function importSlides(Request $request, array $draft, int $tenantId, string $step): array
    {
        try {
            // « Mettre à jour » sur une présentation déjà importée, sinon le lien collé (encart du chapitre ou panneau latéral).
            $here = $request->inputString('slides_here');
            $hereUrls = $request->input('slides_url_here');
            $url = $request->inputString('reimport_url') !== '' ? $request->inputString('reimport_url')
                : (ctype_digit($here) && is_array($hereUrls) && is_string($hereUrls[$here] ?? null) ? $hereUrls[$here] : $request->inputString('slides_url'));
            $slides = $this->importer()->import($tenantId, $url);
        } catch (\DomainException $e) {
            return [$draft, 'slides_' . $e->getMessage(), $step];
        }
        foreach ($draft['chapters'] as $chapterIndex => $chapter) {
            foreach ($chapter['resources'] as $resourceIndex => $resource) {
                if (($resource['deck'] ?? null) === $slides['deck']) {
                    // shortcut: l'ancien fichier reste sur le disque, des inscriptions peuvent encore le citer dans leur copie du cours.
                    $draft['chapters'][$chapterIndex]['resources'][$resourceIndex] = $slides;
                    return [$draft, 'slides_updated', $chapterIndex . '.0'];
                }
            }
        }
        $target = ctype_digit($here) ? $here : $request->inputString('slides_target', 'new');
        if ($target !== 'new' && ctype_digit($target) && isset($draft['chapters'][(int) $target])) {
            $index = (int) $target;
            if (count($draft['chapters'][$index]['resources']) >= 20) {
                return [$draft, 'slides_too_many', $step];
            }
            $draft['chapters'][$index]['resources'][] = $slides;
        } else {
            if (count($draft['chapters']) >= AcademyStudio::MAX_CHAPTERS) {
                return [$draft, 'slides_too_many', $step];
            }
            $chapter = AcademyStudio::blankChapter($slides['title']);
            $chapter['resources'][] = $slides;
            $draft['chapters'][] = $chapter;
            $index = count($draft['chapters']) - 1;
        }
        return [$draft, 'slides_imported', $index . '.0'];
    }

    /** Diapositives importées : fichiers privés de la communauté, lus par les membres ayant accès à l'Academy. */
    public function media(Request $request, array $params = []): Response
    {
        return $this->pdf($this->importer()->path(TenantContext::id(), (string) ($params['file'] ?? '')));
    }

    private function pdf(?string $path): Response
    {
        if ($path === null) {
            return (new Response())->setStatusCode(404);
        }
        return (new Response())
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="diapositives.pdf"')
            ->header('Content-Length', (string) filesize($path))
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Cache-Control', 'private, max-age=3600')
            ->setBodyStream(static function () use ($path): void {
                readfile($path);
            });
    }

    private function error(string $message, int $status): Response
    {
        return Response::view('academy.error', ['message' => $message])->setStatusCode($status)->header('Cache-Control', 'private, no-store');
    }
}
