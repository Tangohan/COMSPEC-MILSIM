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
        $staff = false;
        try {
            $userId = (int) Session::get('user_id');
            if ($userId > 0) {
                $account = Container::get(UserRepository::class)->findById($userId);
                $tenant = Container::get(TenantRepository::class)->findById(TenantContext::id());
                $accessState = !$tenant || ($tenant['slug'] ?? '') === 'default' ? 'no_tenant' : 'unpaid';
                if ($account && $tenant && $accessState !== 'no_tenant' && AcademyAccess::paid($tenant)) {
                    if (!Container::get(\App\Services\Platform\FeatureGateService::class)->allows((int) $tenant['id'], 'training')) {
                        return Response::view('academy.home', compact('account','tenant','legacyCourses','legacyEnrollments','staff') + ['accessState'=>'unavailable'])->header('Cache-Control','private, no-store');
                    }
                    $data = $this->lms()->load((int) $tenant['id'], $userId);
                    $legacyCourses = $data['legacyCourses'];
                    $legacyEnrollments = $data['legacyEnrollments'];
                    $accessState = 'active';
                    $staff = TrainingLmsStaffAccess::allows(Gate::getInstance());
                }
            }
        } catch (\PDOException | \RuntimeException) {
            $accessState = 'unavailable';
        }
        return Response::view('academy.home', compact('account', 'tenant', 'accessState', 'legacyCourses', 'legacyEnrollments', 'staff'))
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
        $spaces = ['dashboard', 'catalogue', 'courses', 'classroom', 'laboratory', 'simulators', 'exams', 'library', 'notebook', 'record', 'instructor'];
        if (!in_array($space, $spaces, true)) {
            return (new Response())->setStatusCode(404);
        }
        $staff = TrainingLmsStaffAccess::allows(Gate::getInstance());
        if ($space === 'instructor' && !$staff) {
            return $this->error('forbidden', 403);
        }
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
                $courses = $repository->courses($tenantId, $space === 'instructor');
            } catch (\PDOException) {
                // Existing LMS remains usable before the optional Academy tables are migrated.
                $states = $courses = [];
                $academyStorageReady = false;
            }
            $firstEnrollment = array_key_first(array_filter($states, static fn ($key): bool => $key !== '__notes', ARRAY_FILTER_USE_KEY));
            $key = (string) $request->query('course', $firstEnrollment ?? ($courses[0]['key'] ?? ''));
            $selected = null;
            foreach ($courses as $course) {
                if ($course['key'] === $key) {
                    $selected = $course;
                    break;
                }
            }
            $state = $states[$key] ?? null;
            if ($state !== null) {
                $selected = $state['snapshot'] ?? $selected;
            }
            return Response::view('academy.space', compact('space', 'staff', 'states', 'courses', 'selected', 'state', 'account', 'tenant') + $lmsData + [
                'academyStorageReady' => $academyStorageReady,
                'learners' => $space === 'instructor' && $academyStorageReady ? $repository->learners($tenantId) : [],
                'notice' => Session::get('academy_notice', ''),
            ]);
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
            } elseif (in_array($action, ['create', 'publish', 'validate'], true)) {
                if (!TrainingLmsStaffAccess::allows(Gate::getInstance())) {
                    return $this->error('forbidden', 403);
                }
                if ($action === 'create') {
                    $raw = $request->input('content', '');
                    if (!is_string($raw) || strlen($raw) > 500000) {
                        return $this->error('invalid_content', 422);
                    }
                    $content = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
                    if (!is_array($content)) {
                        return $this->error('invalid_content', 422);
                    }
                    $repository->saveCourse($tenantId, $userId, $content, $request->input('publish') === '1');
                } elseif ($action === 'publish') {
                    $repository->publish($tenantId, $key);
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
                $input = ['answer' => $request->input('answer'), 'token' => $request->input('token'), 'reflection' => $request->input('reflection'), 'expected_stage' => $request->input('expected_stage'), 'expected_chapter' => $request->input('expected_chapter')];
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

    private function error(string $message, int $status): Response
    {
        return Response::view('academy.error', ['message' => $message])->setStatusCode($status)->header('Cache-Control', 'private, no-store');
    }
}
