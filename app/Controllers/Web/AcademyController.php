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
use App\Services\Academy\AcademyStudio;
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
        if (in_array($space, ['instructor', 'studio'], true) && !$staff) {
            return $this->error('forbidden', 403);
        }
        if ($space === 'studio') {
            return $this->studio($request);
        }
        // Aperçu d'un brouillon depuis le Studio : réservé à l'encadrement, sans inscription possible.
        $preview = $staff && $space === 'classroom' && $request->queryString('preview') === '1';
        try {
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
                if (!TrainingLmsStaffAccess::allows(Gate::getInstance())) {
                    return $this->error('forbidden', 403);
                }
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
                $repository->mutate($tenantId, $userId, $key, static fn (array $state): array => AcademyCurriculum::transition($state, $state['snapshot'], $action, $input, time()));
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

    private function studioUrl(string $key, string $step = ''): string
    {
        return url('academy/studio' . ($key !== '' ? '?course=' . rawurlencode($key) . (preg_match('/\A\d{1,2}\.\d\z/', $step) ? '&step=' . $step : '') : ''));
    }

    private function importer(): GoogleSlidesImporter
    {
        return Container::get(GoogleSlidesImporter::class);
    }

    /** Espace de création : liste des cours de la communauté, ou édition d'un cours. */
    private function studio(Request $request): Response
    {
        $tenantId = TenantContext::id();
        try {
            if (!Container::get(\App\Services\Platform\FeatureGateService::class)->allows($tenantId, 'training')) {
                return $this->error('module_unavailable', 403);
            }
            $repository = new AcademyRepository();
            $key = $request->queryString('course');
            $course = null;
            if ($key !== '') {
                $course = $repository->editable($tenantId, $key) ?? AcademyBuiltInCourses::find($key);
                if ($course === null) {
                    return $this->error('not_found', 404);
                }
            }
            $tenant = Container::get(TenantRepository::class)->findById($tenantId);
            $account = ['display_name' => (string) Session::get('display_name', ''), 'callsign' => (string) Session::get('callsign', '')];
            $notice = (string) Session::get('academy_notice', '');
            Session::set('academy_notice', '');
            return Response::view('academy.studio', [
                'space' => 'studio', 'staff' => true, 'tenant' => $tenant, 'account' => $account, 'notice' => $notice,
                'course' => $course, 'studioCourses' => $course === null ? $repository->courses($tenantId, true) : [],
                'issues' => $course !== null ? AcademyStudio::issues($course) : [],
                'viewStep' => $request->queryString('step'),
            ])->header('Cache-Control', 'private, no-store');
        } catch (\PDOException) {
            // Tables Academy absentes : la création attend l'étape de migration.
            return $this->error('unavailable', 503);
        }
    }

    private function studioAction(Request $request, AcademyRepository $repository, string $action, string $key, int $tenantId, int $userId): Response
    {
        $step = $request->inputString('step');
        if ($action === 'studio_new') {
            return Response::redirect($this->studioUrl($repository->createDraft($tenantId, $userId, AcademyStudio::blankCourse())), 303);
        }
        $course = $repository->editable($tenantId, $key);
        if ($action === 'studio_revise') {
            $source = $course ?? AcademyBuiltInCourses::find($key);
            if ($source === null) {
                return $this->error('not_found', 404);
            }
            // Nouvelle version d'un cours publié (ou copie d'un module intégré) : l'original reste en ligne jusqu'à la publication.
            $copy = $source;
            unset($copy['built_in'], $copy['demo']);
            if ($course !== null) {
                $copy['replaces'] = $key;
            }
            Session::set('academy_notice', 'studio_revision');
            return Response::redirect($this->studioUrl($repository->createDraft($tenantId, $userId, $copy), $step), 303);
        }
        if ($course === null || $course['published']) {
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
                return Response::redirect($this->studioUrl($key, $step), 303);
            }
            $repository->publishDraft($tenantId, $key);
            $notice = 'studio_published';
        }
        Session::set('academy_notice', $notice);
        return Response::redirect($this->studioUrl($key, $step), 303);
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
            // « Mettre à jour » sur une présentation déjà importée, sinon le lien collé.
            $url = $request->inputString('reimport_url') !== '' ? $request->inputString('reimport_url') : $request->inputString('slides_url');
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
        $target = $request->inputString('slides_target', 'new');
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
        $path = $this->importer()->path(TenantContext::id(), (string) ($params['file'] ?? ''));
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
