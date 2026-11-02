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
use App\Services\Audit\AuditService;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Repositories\TrainingEnrollmentRepository;
use App\Repositories\TrainingRepository;
use App\Services\Training\TrainingService;
use App\Services\Tenant\TenantContext;
use App\Services\Academy\AcademyAccess;
use App\Services\Academy\AcademyLmsBridge;
use App\Services\Academy\AcademyCurriculum;
use App\Services\Academy\AcademyBuiltInCourses;
use App\Services\Academy\AcademyRichText;
use App\Services\Academy\AcademyStudio;
use App\Services\Training\TrainingCourseMediaUploadService;
use App\Services\Academy\GoogleSlidesImporter;
use App\Support\TrainingLmsStaffAccess;

final class AcademyController
{
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
                        $academyCourses = $repository->courses((int) $tenant['id']);
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
        $spaces = ['dashboard', 'catalogue', 'courses', 'classroom', 'laboratory', 'simulators', 'exams', 'library', 'notebook', 'record', 'instructor', 'studio'];
        if (!in_array($space, $spaces, true)) {
            return (new Response())->setStatusCode(404);
        }
        $staff = TrainingLmsStaffAccess::allows(Gate::getInstance());
        if ($space === 'instructor' && !$staff) {
            return $this->error('forbidden', 403);
        }
        if ($space === 'studio') {
            return $this->studio($request, $staff);
        }
        try {
            // Aperçu d'une formation (brouillon compris), sans inscription possible : encadrement, ou lecteur / éditeur invité.
            $preview = $space === 'classroom' && $request->queryString('preview') === '1'
                && $this->courseRole(TenantContext::id(), $request->queryString('course'), $staff) !== null;
            $repository = new AcademyRepository();
            $tenantId = TenantContext::id();
            if (!Container::get(\App\Services\Platform\FeatureGateService::class)->allows($tenantId, 'training')) {
                return $this->error('module_unavailable', 403);
            }
            $lmsData = $this->lms()->load($tenantId, (int) Session::get('user_id'));
            $tenant = Container::get(TenantRepository::class)->findById($tenantId);
            $account = ['display_name'=>(string) Session::get('display_name',''), 'callsign'=>(string) Session::get('callsign','')];
            $academyStorageReady = true;
            try {
                $states = $repository->states($tenantId, (int) Session::get('user_id'));
                $courses = $repository->courses($tenantId, $space === 'instructor' || $preview);
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
            return Response::view('academy.space', compact('space', 'staff', 'states', 'courses', 'selected', 'state', 'account', 'tenant', 'preview') + $lmsData + [
                'academyStorageReady' => $academyStorageReady && !$preview,
                'viewStep' => $request->queryString('step'),
                'learners' => $space === 'instructor' && $academyStorageReady ? $repository->learners($tenantId) : [],
                'notice' => Session::get('academy_notice', ''),
            ])->header('Cache-Control', 'private, no-store');
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
            $returnSpace = 'classroom';
            if ($action === 'enroll') {
                $course = $repository->course($tenantId, $key);
                if (!$course) {
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
                if (!TrainingLmsStaffAccess::allows(Gate::getInstance())) {
                    return $this->error('forbidden', 403);
                }
                if ($action === 'publish') {
                    $repository->publishDraft($tenantId, $key);
                } else {
                    $learnerId = (int) $request->input('learner', 0);
                    if ($learnerId === $userId) {
                        return $this->error('self_validation', 403);
                    }
                    $comment = $request->input('comment', '');
                    if (!is_string($comment)) {
                        return $this->error('invalid_content', 422);
                    }
                    $expectedChapter = $request->input('expected_chapter');
                    $repository->mutate($tenantId, $learnerId, $key, static function (array $state) use ($expectedChapter, $userId, $comment): array {
                        if (!is_scalar($expectedChapter) || (string) $expectedChapter !== (string) $state['chapter']) {
                            throw new \DomainException('locked');
                        }
                        return AcademyCurriculum::validateChapter($state, $state['snapshot'], $userId, $comment, time());
                    });
                }
                $returnSpace = 'instructor';
            } else {
                $input = ['answer' => $request->input('answer'), 'answers' => $request->input('answers'), 'token' => $request->input('token'), 'reflection' => $request->input('reflection'), 'expected_stage' => $request->input('expected_stage'), 'expected_chapter' => $request->input('expected_chapter')];
                $resultStep = '';
                $repository->mutate($tenantId, $userId, $key, static function (array $state) use ($action, $input, &$resultStep): array {
                    $next = AcademyCurriculum::transition($state, $state['snapshot'], $action, $input, time());
                    // Bonne réponse ou évaluation remise : le retour s'affiche sur l'étape validée, pas en tête de la suivante.
                    if (($action === 'practice' && ($next['feedback'] ?? '') === 'correct') || $action === 'exam_submit') {
                        $resultStep = (int) $state['chapter'] . '.' . (int) $state['stage'];
                    }
                    return $next;
                });
                if (in_array($action, ['practice', 'exam_submit'], true)) {
                    return Response::redirect($this->classroomUrl($key, $resultStep) . '#acr-result', 303);
                }
            }
            Session::set('academy_notice', 'saved');
            return Response::redirect(url('academy/' . $returnSpace . '?course=' . rawurlencode($key)), 303);
        } catch (\DomainException | \InvalidArgumentException | \JsonException $e) {
            return $this->error(in_array($e->getMessage(), ['locked', 'reflection_required', 'not_enrolled'], true) ? $e->getMessage() : 'invalid_content', 422);
        } catch (\PDOException) {
            return $this->error('unavailable', 503);
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

    /**
     * Droit sur une formation : « owner » pour l'encadrement (droits LMS inchangés), sinon le partage reçu
     * (personne invitée, ou lien de communauté ouvert dans cette session et toujours valide).
     */
    private function courseRole(int $tenantId, string $key, bool $staff): ?string
    {
        if ($staff) {
            return 'owner';
        }
        $shares = $this->shareRepository();
        if ($shares === null || $tenantId < 1) {
            return null;
        }
        $role = $shares->roleFor($tenantId, $key, (int) Session::get('user_id'));
        $grants = Session::get('academy_link_grants', []);
        $grant = is_array($grants) ? ($grants[$tenantId . ':' . $key] ?? null) : null;
        if (is_string($grant)) {
            $link = $shares->link($tenantId, $key);
            if ($link !== null && hash_equals($link['token_hash'], $grant) && $link['access'] === 'community'
                && ($link['expires_at'] === null || strtotime($link['expires_at']) > time()) && ($role === null || $link['role'] === 'editor')) {
                $role = $link['role'];
            }
        }
        return $role;
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

    /** Espace de création : accueil (formations de la communauté), ou édition d'une formation. */
    private function studio(Request $request, bool $staff): Response
    {
        $tenantId = TenantContext::id();
        $userId = (int) Session::get('user_id');
        try {
            if (!Container::get(\App\Services\Platform\FeatureGateService::class)->allows($tenantId, 'training')) {
                return $this->error('module_unavailable', 403);
            }
            $repository = new AcademyRepository();
            $shares = $this->shareRepository();
            $key = $request->queryString('course');
            $course = null;
            $role = null;
            if ($key !== '') {
                $course = $repository->editable($tenantId, $key) ?? AcademyBuiltInCourses::find($key);
                $role = $course !== null ? $this->courseRole($tenantId, $key, $staff) : null;
                if ($course === null || $role === null) {
                    return $this->error($course === null ? 'not_found' : 'forbidden', $course === null ? 404 : 403);
                }
                if ($role === 'viewer') {
                    // Lecteur : il voit l'aperçu stagiaire, pas l'éditeur.
                    return Response::redirect(url('academy/classroom?course=' . rawurlencode($key) . '&preview=1'), 303);
                }
            }
            $sharedWithMe = !$staff && $shares !== null ? $shares->coursesFor($tenantId, $userId) : [];
            if (!$staff && $course === null && $sharedWithMe === []) {
                return $this->error('forbidden', 403);
            }
            $list = [];
            if ($course === null) {
                if ($staff && $repository->lifecycleReady()) {
                    foreach ($repository->purgeTrash($tenantId) as $purged) {
                        $shares?->forget($tenantId, $purged);
                        $this->audit('academy.course.purged', $tenantId, $purged);
                    }
                }
                $list = array_values(array_filter($repository->studioList($tenantId), static fn (array $item): bool => $staff || isset($sharedWithMe[$item['key']])));
            }
            $sharing = null;
            if ($staff && $course !== null && empty($course['built_in']) && $shares !== null) {
                $members = [];
                foreach (Container::get(UserRepository::class)->listForTenant($tenantId, null, 'active', null, 200) as $member) {
                    $members[(int) $member['id']] = trim((string) ($member['callsign'] ?? '')) !== '' ? (string) $member['callsign']
                        : (trim(((string) ($member['first_name'] ?? '')) . ' ' . ((string) ($member['last_name'] ?? ''))) ?: '#' . (int) $member['id']);
                }
                $sharing = ['shares' => $shares->shares($tenantId, $key), 'link' => $shares->link($tenantId, $key), 'members' => $members];
            }
            $tenant = Container::get(TenantRepository::class)->findById($tenantId);
            $account = ['display_name' => (string) Session::get('display_name', ''), 'callsign' => (string) Session::get('callsign', '')];
            $notice = (string) Session::get('academy_notice', '');
            Session::set('academy_notice', '');
            return Response::view('academy.studio', [
                'space' => 'studio', 'staff' => $staff, 'role' => $role, 'tenant' => $tenant, 'account' => $account, 'notice' => $notice,
                'course' => $course, 'studioCourses' => $list, 'sharedWithMe' => $sharedWithMe,
                'builtInCourses' => $course === null && $staff ? array_values(AcademyBuiltInCourses::all()) : [],
                'issues' => $course !== null ? AcademyStudio::issues($course) : [],
                'learnerCount' => $course !== null && empty($course['built_in']) ? $repository->learnerCount($tenantId, $key) : 0,
                'lifecycleReady' => $repository->lifecycleReady(), 'sharing' => $sharing, 'sharingReady' => $shares !== null,
                'viewStep' => $request->queryString('step'),
                'mediaImages' => $course !== null && $role !== 'viewer' ? self::academyImages($tenantId) : [],
                'editLang' => $request->queryString('edit') === 'en' ? 'en' : 'fr',
                'openShare' => $request->queryString('share') === '1',
                'studioView' => $request->queryString('vue'),
            ])->header('Cache-Control', 'private, no-store');
        } catch (\PDOException) {
            // Tables Academy absentes : la création attend l'étape de migration.
            return $this->error('unavailable', 503);
        }
    }

    /**
     * Médiathèque du Studio : images déjà envoyées par la communauté pour ses cours (les plus récentes d'abord).
     *
     * @return list<array{path:string,url:string}>
     */
    private static function academyImages(int $tenantId): array
    {
        $dir = base_path('public/uploads/training-course-media/' . $tenantId);
        $files = is_dir($dir) ? (glob($dir . '/academy-*') ?: []) : [];
        usort($files, static fn (string $a, string $b): int => (int) @filemtime($b) <=> (int) @filemtime($a));
        $images = [];
        foreach (array_slice($files, 0, 60) as $file) {
            $path = 'uploads/training-course-media/' . $tenantId . '/' . basename($file);
            if (AcademyRichText::imageAllowed($path)) {
                $images[] = ['path' => $path, 'url' => url($path)];
            }
        }
        return $images;
    }

    private function studioAction(Request $request, AcademyRepository $repository, string $action, string $key, int $tenantId, int $userId): Response
    {
        $staff = TrainingLmsStaffAccess::allows(Gate::getInstance());
        // Un éditeur invité modifie le brouillon ; publier, versionner, archiver, supprimer et partager restent à l'encadrement.
        if (!in_array($action, ['studio_save', 'studio_import', 'studio_preview', 'studio_image'], true) && !$staff) {
            return $this->error('forbidden', 403);
        }
        if (!$staff && $this->courseRole($tenantId, $key, false) !== 'editor') {
            return $this->error('forbidden', 403);
        }
        $step = $request->inputString('step');
        if ($action === 'studio_preview') {
            // Aperçu en direct de l'éditeur : même rendu que la salle de cours, rien n'est enregistré.
            $text = $request->input('text', '');
            if (!is_string($text) || strlen($text) > 20000) {
                return Response::json(['error' => 'invalid_content'], 422)->header('Cache-Control', 'private, no-store');
            }
            $copy = require base_path('lang/' . (locale() === 'en' ? 'en' : 'fr') . '/academy.php');
            return Response::json(['html' => AcademyRichText::render($text, $copy)])->header('Cache-Control', 'private, no-store');
        }
        if ($action === 'studio_image') {
            // Image d'un texte de cours : stockage public de la communauté (contrôle d'extension, de type réel et de taille).
            try {
                $path = Container::get(TrainingCourseMediaUploadService::class)->storeUpload($tenantId, is_array($_FILES['image'] ?? null) ? $_FILES['image'] : null, 'academy');
            } catch (\InvalidArgumentException | \RuntimeException) {
                $path = null;
            }
            if ($path === null || !AcademyRichText::imageAllowed($path)) {
                return Response::json(['error' => 'rte_upload_failed'], 422)->header('Cache-Control', 'private, no-store');
            }
            $this->audit('academy.course.image_uploaded', $tenantId, $key, ['path' => $path]);
            return Response::json(['path' => $path, 'url' => url($path)])->header('Cache-Control', 'private, no-store');
        }
        if ($action === 'studio_new') {
            // Fenêtre « Nouveau cours » : titre, présentation et couverture en français, le reste se rédige dans l'éditeur.
            $blank = AcademyStudio::blankCourse();
            $start = AcademyStudio::fromForm(['title' => ['fr' => $request->inputString('new_title')], 'summary' => ['fr' => $request->inputString('new_summary')],
                'cover' => $request->inputString('new_cover'), 'chapters' => $blank['chapters']], []);
            $created = $repository->createDraft($tenantId, $userId, $start);
            $this->audit('academy.course.created', $tenantId, $created);
            return Response::redirect($this->studioUrl($created), 303);
        }
        if ($action === 'studio_trash_restore') {
            $restored = $repository->lifecycleReady() && $repository->restoreFromTrash($tenantId, $key);
            if ($restored) {
                $this->audit('academy.course.restored_from_trash', $tenantId, $key);
            }
            Session::set('academy_notice', $restored ? 'studio_trash_restored' : 'locked');
            return Response::redirect($this->studioUrl(''), 303);
        }
        $course = $repository->editable($tenantId, $key);
        if (in_array($action, ['studio_archive', 'studio_unarchive', 'studio_delete'], true)) {
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
            return $this->shareAction($request, $action, $course, $key, $tenantId, $userId);
        }
        if (in_array($action, ['studio_revise', 'studio_duplicate'], true)) {
            $source = $course ?? AcademyBuiltInCourses::find($key);
            if ($source === null) {
                return $this->error('not_found', 404);
            }
            // Nouvelle version d'un cours publié : l'original reste en ligne jusqu'à la publication. Copie : cours indépendant.
            $copy = $source;
            unset($copy['built_in'], $copy['demo'], $copy['replaces'], $copy['updated_at'], $copy['archived']);
            $revision = $action === 'studio_revise' && $course !== null && $course['published'];
            if ($revision) {
                $copy['replaces'] = $key;
            } else {
                foreach (['fr' => ' (copie)', 'en' => ' (copy)'] as $language => $suffix) {
                    $copy['title'][$language] = trim((string) ($copy['title'][$language] ?? '')) . $suffix;
                }
            }
            $created = $repository->createDraft($tenantId, $userId, $copy);
            $this->audit($revision ? 'academy.course.revised' : 'academy.course.duplicated', $tenantId, $created, ['from' => $key]);
            Session::set('academy_notice', $revision ? 'studio_revision' : 'studio_copied');
            return Response::redirect($this->studioUrl($created, $step), 303);
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

    /** Fenêtre « Partager » : personnes (lecteur / éditeur) et lien d'accès. Réservé à l'encadrement. */
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
            $shares->grant($tenantId, $key, $member, $role, $userId);
            $this->audit('academy.course.shared', $tenantId, $key, ['user' => $member, 'role' => $role]);
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
