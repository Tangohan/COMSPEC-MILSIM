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
use App\Services\Academy\AcademyRichText;
use App\Services\Academy\AcademyOffer;
use App\Services\Academy\AcademyTracker;
use App\Services\Academy\AcademyVersionUpgrade;
use App\Services\Academy\AcademyStudio;
use App\Services\Academy\AcademyStudioCredentials;
use App\Services\Training\TrainingCourseMediaUploadService;
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
        $spaces = ['dashboard', 'catalogue', 'courses', 'classroom', 'laboratory', 'simulators', 'exams', 'library', 'notebook', 'record', 'instructor', 'studio', 'droits', 'suivi'];
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
            if ($space === 'suivi') {
                return $this->tracker($request, $repository, $rights, $tenantId, $userId, $staff);
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
                ? AcademyOffer::collect($tenantId, (int) Session::get('user_id'), $tenant, $courses, $states, $lmsData['legacyCourses'] ?? [], $lmsData['legacyEnrollments'] ?? [], $staff, $space === 'catalogue' && AcademyOffer::needsReference($request->queryParams()))
                : null;
            $offerFilters = AcademyOffer::filters($request->queryParams());
            $offerDetail = $space === 'catalogue' ? $request->queryString('fiche') : '';
            // Importer une qualification du catalogue de référence : mêmes droits que la page Catalogue du référentiel.
            $gate = Gate::getInstance();
            $offerCanImport = $space === 'catalogue' && ($gate->allows('admin.organization') || $gate->allows('admin.access') || $gate->allows('site.support'));
            $offerQuota = $offerCanImport ? $this->qualificationQuota($tenantId)->status(\App\Services\Billing\QualificationCatalogQuota::IMPORTS) : null;
            // File « à traiter » de l'encadrement : complète sur le tableau de bord et l'espace instructeur, simple compteur ailleurs (menu).
            $instructorView = $space === 'instructor' ? $this->instructorData($repository, $rights, $tenantId, $academyStorageReady) : ['learners' => []];
            $queue = null;
            $instructorBadge = 0;
            if ($staff && $academyStorageReady) {
                if (in_array($space, ['dashboard', 'instructor'], true)) {
                    $queue = $this->instructorQueue($repository, $rights, $tenantId, $space === 'instructor' ? $instructorView['learners'] : null);
                    $instructorBadge = $queue['total'];
                } else {
                    $instructorBadge = $this->instructorBadge($repository, $rights, $tenantId);
                }
            }
            // Parcours sur une ancienne version du cours : badge et passage à la version courante.
            $currentVersions = $academyStorageReady ? $this->currentVersions($repository, $tenantId) : [];
            $versionInfo = [];
            foreach ($states as $stateKey => $item) {
                if ($stateKey !== '__notes' && ($info = AcademyVersionUpgrade::outdated($item, (string) $stateKey, $currentVersions[$stateKey] ?? null)) !== null) {
                    $versionInfo[$stateKey] = $info;
                }
            }
            foreach ($instructorView['learners'] as &$followed) {
                $followed['version'] = AcademyVersionUpgrade::outdated($followed['state'], (string) $followed['course_key'], $currentVersions[$followed['course_key']] ?? null);
            }
            unset($followed);
            return Response::view('academy.space', compact('versionInfo', 'space', 'staff', 'states', 'courses', 'selected', 'state', 'account', 'tenant', 'preview', 'offer', 'offerFilters', 'offerDetail', 'offerCanImport', 'offerQuota', 'queue', 'instructorBadge') + $lmsData + [
                'academyStorageReady' => $academyStorageReady && !$preview,
                'viewStep' => $request->queryString('step'),
                'notice' => Session::get('academy_notice', ''),
                'queueMembers' => $queue !== null ? ($instructorView['instructor']['members'] ?? $this->memberNames($tenantId)) : [],
                // File de l'espace instructeur : filtre ouvert depuis un compteur du tableau de bord, bilan d'une action groupée.
                'queueType' => $request->queryString('type'),
                'queueBulk' => $request->queryString('bulk') === '1' ? ['done' => max(0, (int) $request->queryString('ok')), 'failed' => max(0, (int) $request->queryString('ko'))] : null,
            ] + $instructorView)->header('Cache-Control', 'private, no-store');
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
            if ($action === 'version_upgrade') {
                return $this->versionUpgradeAction($request, $repository, $rights, $key, $tenantId, $userId);
            }
            if ($action === 'assign') {
                return $this->assignAction($request, $repository, $rights, $key, $tenantId, $userId);
            }
            if (in_array($action, ['tracker_enroll', 'tracker_remind', 'tracker_exempt', 'tracker_unexempt'], true)) {
                return $this->trackerAction($request, $repository, $rights, $action, $key, $tenantId, $userId);
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
                        if (!$certify && AcademyCurriculum::nextUnvalidated($state['validations'] ?? [], count($state['snapshot']['chapters'] ?? []), (int) $state['chapter'] + 1) === null) {
                            throw new \DomainException('certify_required');
                        }
                        return AcademyCurriculum::validateChapter($state, $state['snapshot'], $userId, $comment, time());
                    });
                    $this->awardCourseQualifications($repository, $tenantId, $learnerId, $key, $userId);
                    $this->audit('academy.learner.validated', $tenantId, $key, ['learner' => $learnerId, 'chapter' => is_scalar($expectedChapter) ? (int) $expectedChapter : null]);
                    if ($request->inputString('return') === 'dashboard') {
                        Session::set('academy_notice', 'ins_validated');
                        return Response::redirect(url('academy/dashboard') . '#a-traiter', 303);
                    }
                    if ($request->inputString('return') === 'tracker') {
                        Session::set('academy_notice', 'ins_validated');
                        return Response::redirect($this->trackerUrl($request->inputString('back')), 303);
                    }
                    if ($request->inputString('return') === 'queue') {
                        Session::set('academy_notice', 'ins_validated');
                        return Response::redirect(url('academy/instructor') . '#file', 303);
                    }
                }
                $returnSpace = 'instructor';
            } else {
                $input = ['answer' => $request->input('answer'), 'answers' => $request->input('answers'), 'token' => $request->input('token'), 'reflection' => $request->input('reflection'), 'nothing_to_improve' => $request->input('nothing_to_improve'), 'expected_stage' => $request->input('expected_stage'), 'expected_chapter' => $request->input('expected_chapter')];
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
            if ($e->getMessage() === 'certify_required') {
                return $this->error('certify_required', 403);
            }
            return $this->error(in_array($e->getMessage(), ['locked', 'reflection_required', 'not_enrolled', 'already_enrolled'], true) ? $e->getMessage() : 'invalid_content', 422);
        } catch (\PDOException) {
            return $this->error('unavailable', 503);
        }
    }

    /** @return array<string, array<string, mixed>> clé suivie => version courante du cours (intégré ou Studio) */
    private function currentVersions(AcademyRepository $repository, int $tenantId): array
    {
        try {
            return AcademyVersionUpgrade::currentMap($repository->courses($tenantId), \App\Services\Academy\AcademyBuiltInQualifications::forPdo(\App\Core\Database::getPdo())->lineage($tenantId));
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * « Passer à la nouvelle version » : le stagiaire pour lui-même, ou l’encadrement (validation ou inscription) pour
     * un stagiaire. Progression et validations gardées ; un parcours terminé garde sa licence (jamais révoquée).
     */
    private function versionUpgradeAction(Request $request, AcademyRepository $repository, AcademyRights $rights, string $key, int $tenantId, int $userId): Response
    {
        $learnerId = (int) $request->input('learner', $userId);
        if ($learnerId < 1 || ($learnerId !== $userId && !$rights->canTeach($key) && !$rights->can('assign'))) {
            return $this->error('forbidden', 403);
        }
        $current = $this->currentVersions($repository, $tenantId)[$key] ?? null;
        if ($current === null || $request->inputString('confirm') !== '1') {
            return $this->error('invalid_content', 422);
        }
        $newKey = (string) $current['key'];
        $from = null;
        $repository->moveEnrollment($tenantId, $learnerId, $key, $newKey, static function (array $state) use ($key, $current, $userId, &$from): array {
            $info = AcademyVersionUpgrade::outdated($state, $key, $current);
            if ($info === null) {
                throw new \DomainException('locked');
            }
            $from = $info;
            return AcademyVersionUpgrade::upgrade($state, $current, $userId, time());
        });
        $this->audit('academy.learner.version_upgraded', $tenantId, $newKey, ['learner' => $learnerId, 'from_course' => $key, 'from_version' => $from['from'] ?? null, 'to_version' => $from['to'] ?? null, 'completed' => $from['completed'] ?? false]);
        Session::set('academy_notice', 'version_upgraded');
        if ($learnerId !== $userId) {
            return Response::redirect(url('academy/instructor') . '#eleve-' . $learnerId . '-' . rawurlencode($newKey), 303);
        }
        return Response::redirect($this->classroomUrl($newKey), 303);
    }

    /**
     * Dernier chapitre d’un cours validé. Cours intégré : sa qualification Athena Academy est créée si besoin dans le
     * référentiel (mode « Formation Academy ») puis attribuée par la règle automatique. Tout cours : les qualifications
     * et brevets qui le demandent (Studio) sont évalués aussitôt. Ne bloque jamais la validation.
     */
    private function awardCourseQualifications(AcademyRepository $repository, int $tenantId, int $learnerId, string $key, int $instructorId): void
    {
        try {
            $state = $repository->states($tenantId, $learnerId)[$key] ?? null;
            if (!is_array($state) || ($state['completed'] ?? false) !== true) {
                return;
            }
            $builtIn = \App\Services\Academy\AcademyBuiltInQualifications::forPdo(\App\Core\Database::getPdo());
            $definitionId = 0;
            if (isset(AcademyBuiltInCourses::qualifications()[$key])) {
                $definitionId = $builtIn->ensureDefinition($tenantId, $key, function_exists('locale') ? (string) locale() : 'fr', $instructorId);
                if ($definitionId > 0) {
                    Container::get(\App\Services\Personnel\QualificationAwardRuleService::class)->runAuto($tenantId, $definitionId);
                }
            }
            // Qualifications et brevets du Studio qui demandent ce cours (ou une version qu'il remplace).
            $this->credentials()->awardForCourse($tenantId, $key, $builtIn->lineage($tenantId)[$key] ?? [], $definitionId);
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

    /** @return array<int,string> membres actifs de la communauté : identifiant => « Prénom N. » (indicatif ensuite, s’il existe) */
    private function memberNames(int $tenantId, ?int $limit = 500): array
    {
        $rows = Container::get(UserRepository::class)->listForTenant($tenantId, null, 'active', null, $limit);
        $ids = array_map(static fn (array $m): int => (int) $m['id'], $rows);
        try {
            $identities = (new \App\Services\Personnel\MemberNameService())->identitiesFor($tenantId, $ids);
        } catch (\Throwable) {
            $identities = [];
        }
        $members = [];
        foreach ($rows as $member) {
            $id = (int) $member['id'];
            $row = \App\Support\MemberName::withIdentity($member, $identities[$id] ?? null);
            $name = member_name($row, 'list', $tenantId);
            $callsign = \App\Support\MemberName::callsign($row);
            $members[$id] = $name === \App\Support\MemberName::FALLBACK ? '#' . $id
                : ($callsign !== '' && $callsign !== $name ? $name . ' (' . $callsign . ')' : $name);
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
            $credentialPage = $this->credentialsPage($request, $rights, $repository, $tenantId);
            if ($credentialPage !== null) {
                return $credentialPage;
            }
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
                'mediaImages' => $course !== null && $role !== 'viewer' ? self::academyImages($tenantId) : [],
                'editLang' => $request->queryString('edit') === 'en' ? 'en' : 'fr',
                'openShare' => $request->queryString('share') === '1',
                'studioView' => $request->queryString('vue'),
                'courseCredentials' => $course !== null ? $this->courseCredentials($rights, $tenantId, $key) : null,
                'credentialCounts' => $course === null ? $this->credentialCounts($tenantId) : [],
                'openNewCourse' => $course === null && $request->queryString('nouveau-cours') === '1' && $rights->can('create'),
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
        if (str_starts_with($action, 'studio_cred_')) {
            return $this->credentialAction($request, $repository, $rights, $action, $key, $tenantId, $userId);
        }
        $access = $this->accessRepository();
        if (in_array($action, ['studio_preview', 'studio_image'], true)) {
            // Aperçu en direct et images de l'éditeur : qui peut rédiger ce cours (auteur, droit « modifier », éditeur
            // invité) ; sans cours ouvert, qui peut créer ou modifier des cours.
            $editing = $key !== '' ? $repository->editable($tenantId, $key) : null;
            if (!($editing !== null ? $rights->canEdit($editing) : ($rights->can('create') || $rights->can('edit')))) {
                return $this->error('forbidden', 403);
            }
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
    /**
     * Academy › Tableau de suivi : matrice stagiaires × formations (vue « cursus ») ou × chapitres (vue « formation »).
     * Encadrement : tous les membres avec le droit « statistiques » ou « valider », sinon les cours que l'on encadre ;
     * stagiaire : sa seule ligne. Export CSV : ?export=csv (mêmes filtres, toutes les pages).
     */
    private function tracker(Request $request, AcademyRepository $repository, AcademyRights $rights, int $tenantId, int $userId, bool $staff): Response
    {
        $data = $this->trackerData($request, $repository, $rights, $tenantId, $userId, $staff, $request->queryString('export') === 'csv');
        if ($request->queryString('export') === 'csv') {
            return (new Response())->setStatusCode(200)
                ->header('Content-Type', 'text/csv; charset=utf-8')
                ->header('Content-Disposition', 'attachment; filename="academy-suivi-' . date('Ymd-Hi') . '.csv"')
                ->header('Cache-Control', 'private, no-store')
                ->setBody($this->trackerCsv($data));
        }
        $notice = (string) Session::get('academy_notice', '');
        $noticeCount = (int) Session::get('academy_notice_n', 0);
        Session::set('academy_notice_n', 0);
        return Response::view('academy.space', [
            'space' => 'suivi', 'staff' => $staff, 'states' => [], 'courses' => [], 'selected' => null, 'state' => null, 'learners' => [], 'preview' => false,
            'tenant' => Container::get(TenantRepository::class)->findById($tenantId),
            'account' => ['display_name' => (string) Session::get('display_name', ''), 'callsign' => (string) Session::get('callsign', '')],
            'notice' => $notice, 'noticeCount' => $noticeCount, 'tracker' => $data,
            'instructorBadge' => $staff ? $this->instructorBadge($repository, $rights, $tenantId) : 0,
        ])->header('Cache-Control', 'private, no-store');
    }

    /** @return array<string, mixed> */
    private function trackerData(Request $request, AcademyRepository $repository, AcademyRights $rights, int $tenantId, int $userId, bool $staff, bool $everything): array
    {
        $now = time();
        $all = $rights->can('stats') || $rights->can('validate');
        $courses = [];
        foreach ($repository->courses($tenantId) as $course) {
            $key = (string) $course['key'];
            if ($staff ? ($all || $rights->canTeach($key)) : true) {
                $courses[$key] = $course;
            }
        }
        $counts = $repository->enrollmentCounts($tenantId);
        if (!$staff) {
            // Stagiaire : ses seules formations.
            $mine = array_keys(array_filter($repository->states($tenantId, $userId), static fn ($key): bool => $key !== '__notes', ARRAY_FILTER_USE_KEY));
            $courses = array_intersect_key($courses, array_flip($mine));
        }
        uksort($courses, static fn ($a, $b): int => [($counts[$b] ?? 0), (string) $a] <=> [($counts[$a] ?? 0), (string) $b]);
        $view = $request->queryString('vue') === 'formation' ? 'course' : 'path';
        $focus = $request->queryString('course');
        if (!isset($courses[$focus])) {
            $focus = (string) (array_key_first($courses) ?? '');
        }
        if ($view === 'course') {
            $keys = $focus !== '' ? [$focus] : [];
        } else {
            $picked = array_values(array_unique(array_filter(explode(',', $request->queryString('cols')), static fn (string $key): bool => isset($courses[$key]))));
            $keys = $picked !== [] ? array_slice($picked, 0, 30) : array_slice(array_map('strval', array_keys(array_filter($courses, static fn (array $c): bool => ($counts[(string) $c['key']] ?? 0) > 0))), 0, AcademyTracker::MAX_COLUMNS);
            if ($keys === []) {
                $keys = array_slice(array_map('strval', array_keys($courses)), 0, 6);
            }
        }
        $columns = array_map(static fn ($key): array => ['key' => (string) $key, 'chapters' => max(1, count($courses[$key]['chapters'] ?? [])), 'course' => $courses[$key]], $keys);

        // Une lecture pour toutes les cellules, puis les attributions des qualifications délivrées par ces cours.
        $rows = [];
        foreach ($repository->trackerRows($tenantId, $keys, $staff ? null : $userId) as $raw) {
            $row = AcademyTracker::normalizeRow($raw);
            if ($staff || $row['user_id'] === $userId) {
                $rows[$row['user_id']][$row['course_key']] = $row;
            }
        }
        $awards = [];
        try {
            $credentials = array_values(array_filter($this->credentials()->list($tenantId), static fn (array $c): bool => empty($c['archived']) && array_intersect($keys, $c['courses']) !== []));
            if ($credentials !== []) {
                $awards = AcademyTracker::awardsByCourse($credentials, Container::get(\App\Repositories\QualificationAwardRepository::class)->forDefinitions($tenantId, array_column($credentials, 'id')));
            }
        } catch (\Throwable) {
            $awards = [];
        }

        // Membres : toute la communauté pour l'encadrement (unité, grade, fonction, cohorte), soi-même sinon.
        $access = $this->accessRepository();
        $names = $staff ? $this->memberNames($tenantId, null) : [$userId => trim((string) Session::get('display_name', '')) ?: sprintf('#%d', $userId)];
        $contexts = Container::get(UserRepository::class)->memberNumberContextsForTenant($tenantId, array_keys($names));
        $facets = $staff ? $access->trackerFacets($tenantId) : ['units' => [], 'jobs' => [], 'groups' => [], 'groupOptions' => []];
        $members = [];
        foreach ($names as $id => $name) {
            $members[] = ['id' => (int) $id, 'name' => $name, 'grade' => $contexts[$id]['grade'] ?? '', 'unit' => $contexts[$id]['unit'] ?? '',
                'units' => $facets['units'][$id] ?? [], 'jobs' => $facets['jobs'][$id] ?? [], 'groups' => $facets['groups'][$id] ?? []];
        }
        $filters = ['unit' => (int) $request->queryString('unite'), 'job' => (int) $request->queryString('fonction'), 'grade' => $request->queryString('grade'),
            'group' => (int) $request->queryString('cohorte'), 'q' => $request->queryString('q')];
        $state = $request->queryString('etat');
        $state = in_array($state, array_merge(AcademyTracker::STATES, ['late']), true) ? $state : '';
        // Vue « formation » : par défaut, seulement les inscrits.
        $onlyEnrolled = $view === 'course' && $request->queryString('tous') !== '1';
        $filtered = AcademyTracker::filterMembers($members, $filters);
        if ($onlyEnrolled) {
            $filtered = array_values(array_filter($filtered, static fn (array $m): bool => isset($rows[$m['id']][$focus]) || isset($awards[$m['id']][$focus])));
        }
        $matrix = AcademyTracker::matrix($filtered, $columns, $rows, $awards, $now, max(1, (int) $request->queryString('page', '1')), $state, $everything ? PHP_INT_MAX : AcademyTracker::PER_PAGE);
        $grades = array_values(array_unique(array_filter(array_column($members, 'grade'), static fn (string $g): bool => $g !== '')));
        sort($grades, SORT_NATURAL);
        $teach = [];
        foreach ($keys as $key) {
            $teach[$key] = $rights->canTeach($key);
        }

        return [
            'view' => $view, 'focus' => $focus, 'columns' => $columns, 'courses' => $courses, 'counts' => $counts, 'matrix' => $matrix,
            'rows' => $rows, 'filters' => $filters, 'state' => $state, 'onlyEnrolled' => $onlyEnrolled, 'now' => $now,
            'options' => $staff ? ['unit' => $access->unitOptions($tenantId), 'job' => $access->jobOptions($tenantId), 'group' => $facets['groupOptions'], 'grade' => $grades] : [],
            'can' => ['assign' => $staff && $rights->can('assign'), 'certify' => $rights->can('certify'), 'me' => $userId, 'teach' => $teach, 'detail' => $staff],
            'query' => array_filter(['vue' => $view === 'course' ? 'formation' : '', 'course' => $view === 'course' ? $focus : '', 'cols' => $request->queryString('cols'),
                'unite' => $filters['unit'] ?: '', 'fonction' => $filters['job'] ?: '', 'grade' => $filters['grade'], 'cohorte' => $filters['group'] ?: '',
                'q' => $filters['q'], 'etat' => $state, 'tous' => $request->queryString('tous') === '1' ? '1' : ''], static fn ($v): bool => $v !== '' && $v !== 0),
        ];
    }

    /** @param array<string, mixed> $data */
    private function trackerCsv(array $data): string
    {
        $copy = $this->copy();
        $t = static fn (string $key): string => $copy[$key] ?? $key;
        $lang = locale() === 'en' ? 'en' : 'fr';
        $title = static fn (array $value): string => trim((string) ($value[$lang] ?? '')) !== '' ? (string) $value[$lang] : (string) ($value['fr'] ?? '');
        $headers = [$t('trk_learner'), $t('trk_grade'), $t('trk_unit')];
        $course = $data['view'] === 'course' ? ($data['columns'][0] ?? null) : null;
        if ($course !== null) {
            foreach ($course['course']['chapters'] ?? [] as $i => $chapter) {
                $headers[] = ($i + 1) . '. ' . $title($chapter['title'] ?? []);
            }
            $headers[] = $t('trk_progress');
            $headers[] = $t('trk_status');
        } else {
            foreach ($data['columns'] as $column) {
                $headers[] = $title($column['course']['title'] ?? []);
            }
        }
        $lines = [];
        foreach ($data['matrix']['rows'] as $row) {
            $line = [(string) $row['member']['name'], (string) $row['member']['grade'], (string) $row['member']['unit']];
            if ($course !== null) {
                $cell = $row['cells'][$course['key']];
                foreach (AcademyTracker::steps($cell, (int) $course['chapters']) as $step) {
                    $line[] = $t('trk_step_' . $step);
                }
                $line[] = $cell['pct'] . ' %';
                $line[] = $t('trk_state_' . $cell['state']) . ($cell['late'] ? ' · ' . $t('trk_late') : '');
            } else {
                foreach ($row['cells'] as $cell) {
                    $line[] = $t('trk_state_' . $cell['state']) . (in_array($cell['state'], ['progress', 'review', 'failed'], true) ? ' (' . $cell['pct'] . ' %)' : '') . ($cell['late'] ? ' · ' . $t('trk_late') : '');
                }
            }
            $lines[] = $line;
        }
        return AcademyTracker::csv($headers, $lines);
    }

    /**
     * Actions du tableau de suivi. Chaque droit est revérifié ici : inscrire (droit « inscrire », public du cours),
     * relancer (encadrer le cours ou inscrire), dispenser ou lever la dispense (encadrer le cours et « délivrer »).
     */
    private function trackerAction(Request $request, AcademyRepository $repository, AcademyRights $rights, string $action, string $key, int $tenantId, int $userId): Response
    {
        $back = $this->trackerUrl($request->inputString('back'));
        $posted = $request->input('members', []);
        // Bouton d'une cellule de la matrice : « membre|cours » (un seul formulaire partagé par toute la page).
        if (preg_match('/\A(\d{1,10})\|([a-z0-9-]{1,64})\z/', $request->inputString('cell'), $m) === 1) {
            $posted = [(int) $m[1]];
            $key = $m[2];
        }
        $course = $repository->course($tenantId, $key);
        if ($course === null) {
            Session::set('academy_notice', 'assign_invalid');
            return Response::redirect($back, 303);
        }
        $known = $this->memberNames($tenantId, null);
        $targets = array_values(array_unique(array_filter(array_map('intval', is_array($posted) ? $posted : [$posted]), static fn (int $id): bool => isset($known[$id]))));
        $done = 0;
        if ($action === 'tracker_enroll') {
            if (!$rights->can('assign')) {
                return $this->error('forbidden', 403);
            }
            // Inscrire toute une unité (sous-unités comprises par les affectations).
            $unit = (int) $request->inputString('unit');
            if ($unit > 0) {
                foreach ($this->accessRepository()->trackerFacets($tenantId)['units'] as $member => $units) {
                    if (isset($known[$member]) && in_array($unit, $units, true)) {
                        $targets[] = (int) $member;
                    }
                }
                $targets = array_values(array_unique($targets));
            }
            $restricted = $rights->settings($key)['audience'] === 'restricted';
            foreach ($targets as $member) {
                if ($restricted && !$this->rightsForMember($tenantId, $member)->canSee($course)) {
                    continue;
                }
                $repository->enroll($tenantId, $member, $course);
                $done++;
            }
            $this->audit('academy.tracker.enrolled', $tenantId, $key, ['users' => $targets, 'unit' => $unit ?: null, 'done' => $done]);
            return $this->trackerBack($back, 'trk_enrolled', $done);
        }
        if ($action === 'tracker_remind') {
            if (!$rights->canTeach($key) && !$rights->can('assign')) {
                return $this->error('forbidden', 403);
            }
            $now = time();
            $late = $request->inputString('scope') === 'late';
            $chapters = count($course['chapters'] ?? []);
            $wanted = array_flip($targets);
            $messages = new \App\Repositories\TenantMessageRepository();
            $copy = $this->copy();
            $lang = locale() === 'en' ? 'en' : 'fr';
            $title = trim((string) ($course['title'][$lang] ?? '')) ?: (string) ($course['title']['fr'] ?? $key);
            foreach ($repository->trackerRows($tenantId, [$key]) as $raw) {
                $row = AcademyTracker::normalizeRow($raw);
                $cell = AcademyTracker::cell($row, $chapters, null, $now);
                $member = $row['user_id'];
                if ($member === $userId || !isset($known[$member]) || ($late ? !$cell['late'] : !isset($wanted[$member])) || !AcademyTracker::canRemindNow($cell, $now)) {
                    continue;
                }
                try {
                    $thread = $messages->createThread($tenantId, $userId, sprintf($copy['trk_remind_subject'] ?? '%s', $title), [$member]);
                    $messages->addMessage($thread, $userId, sprintf($copy['trk_remind_body'] ?? '%s %d %s', $title, $cell['chapter'] + 1, $this->classroomUrl($key)));
                } catch (\Throwable) {
                    continue;
                }
                $repository->markReminded($tenantId, $member, $key, $now);
                $done++;
            }
            $this->audit('academy.tracker.reminded', $tenantId, $key, ['scope' => $late ? 'late' : 'selection', 'done' => $done]);
            return $this->trackerBack($back, 'trk_reminded', $done);
        }
        // Dispense : un seul stagiaire, jamais soi-même.
        if (!$rights->canTeach($key) || !$rights->can('certify')) {
            return $this->error('forbidden', 403);
        }
        $member = $targets[0] ?? 0;
        if ($member < 1 || $member === $userId) {
            return $this->error($member === $userId ? 'self_validation' : 'invalid_content', $member === $userId ? 403 : 422);
        }
        $comment = trim($request->inputString('comment'));
        if (mb_strlen($comment) > 1000) {
            return $this->error('invalid_content', 422);
        }
        if ($action === 'tracker_exempt') {
            $repository->enroll($tenantId, $member, $course);
        }
        try {
            $repository->mutate($tenantId, $member, $key, static function (array $state) use ($action, $userId, $comment): array {
                if ($action === 'tracker_exempt') {
                    if (!empty($state['completed'])) {
                        throw new \DomainException('locked');
                    }
                    $state['exempt'] = ['by' => $userId, 'at' => time(), 'comment' => $comment];
                } else {
                    unset($state['exempt']);
                }
                return $state;
            });
        } catch (\DomainException) {
            Session::set('academy_notice', 'trk_exempt_locked');
            return Response::redirect($back, 303);
        }
        $this->audit($action === 'tracker_exempt' ? 'academy.tracker.exempted' : 'academy.tracker.exemption_lifted', $tenantId, $key, ['user' => $member, 'comment' => $comment]);
        return $this->trackerBack($back, $action === 'tracker_exempt' ? 'trk_exempted' : 'trk_unexempted', 1);
    }

    /** Retour au tableau de suivi avec ses filtres : seulement une chaîne de requête simple. */
    private function trackerUrl(string $back): string
    {
        return url('academy/suivi') . (preg_match('/\A\?[A-Za-z0-9_=&%,.+\-]{0,600}\z/', $back) === 1 ? $back : '');
    }

    private function trackerBack(string $url, string $notice, int $count): Response
    {
        Session::set('academy_notice', $notice);
        Session::set('academy_notice_n', $count);
        return Response::redirect($url, 303);
    }

    /** Dictionnaire de l'Academy (export, messages envoyés aux stagiaires). @return array<string, string> */
    private function copy(): array
    {
        $lang = locale() === 'en' ? 'en' : 'fr';
        return (require base_path('lang/' . $lang . '/academy.php')) + (require base_path('lang/' . $lang . '/academy_access.php'));
    }

    private function instructorData(AcademyRepository $repository, AcademyRights $rights, int $tenantId, bool $storageReady): array
    {
        $userId = (int) Session::get('user_id');
        $learners = $managed = [];
        if ($storageReady) {
            try {
                $learners = $this->followedLearners($repository, $rights, $tenantId);
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
        $pendingRequests = count($this->pendingEnrollments($rights, $tenantId));
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

    /** Stagiaires suivis : tous avec le droit « statistiques » ou « valider », sinon ceux des cours que l'on encadre. */
    private function followedLearners(AcademyRepository $repository, AcademyRights $rights, int $tenantId): array
    {
        $all = $rights->can('stats') || $rights->can('validate');
        return array_values(array_filter($repository->learners($tenantId), static fn (array $row): bool => $all || $rights->canTeach((string) $row['course_key'])));
    }

    /** Demandes d'inscription aux formations de la communauté : pour qui a le droit « inscrire ». */
    private function pendingEnrollments(AcademyRights $rights, int $tenantId): array
    {
        if (!$rights->can('assign')) {
            return [];
        }
        try {
            return Container::get(TrainingEnrollmentRepository::class)->listPendingApproval($tenantId);
        } catch (\Throwable) {
            return [];
        }
    }

    /** Qualifications et brevets proposés à un responsable : mêmes droits que la fiche du référentiel qui les décide. */
    private function pendingProposals(int $tenantId): array
    {
        $gate = Gate::getInstance();
        if (!$gate->allows('admin.organization') && !$gate->allows('admin.access') && !$gate->allows('site.support')) {
            return [];
        }
        try {
            return Container::get(\App\Repositories\QualificationAwardRepository::class)->pendingProposals($tenantId);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * File « à traiter » (tableau de bord et espace instructeur) et séances que l'on encadre.
     *
     * @param list<array<string, mixed>>|null $learners stagiaires déjà lus par l'espace instructeur
     * @return array{items: list<array<string, mixed>>, counts: array<string, int>, total: int, sessions: list<array<string, mixed>>, certify: bool, can: array<string, bool>}
     */
    private function instructorQueue(AcademyRepository $repository, AcademyRights $rights, int $tenantId, ?array $learners = null): array
    {
        $userId = (int) Session::get('user_id');
        try {
            $learners ??= $this->followedLearners($repository, $rights, $tenantId);
        } catch (\PDOException) {
            $learners = [];
        }
        $queue = \App\Services\Academy\AcademyInstructorQueue::build($learners, $rights->canTeach(...), $rights->can('certify'), $this->pendingEnrollments($rights, $tenantId), $this->pendingProposals($tenantId), $userId, time());
        $gate = Gate::getInstance();
        return $queue + ['sessions' => $this->accessRepository()->upcomingSessionsFor($tenantId, $userId), 'certify' => $rights->can('certify'),
            'can' => ['enrollment' => $rights->can('assign'), 'proposal' => $gate->allows('admin.organization') || $gate->allows('admin.access') || $gate->allows('site.support')]];
    }

    /** Compteur du menu « Espace instructeur (n) » : lecture légère, sans le contenu des cours. */
    private function instructorBadge(AcademyRepository $repository, AcademyRights $rights, int $tenantId): int
    {
        $userId = (int) Session::get('user_id');
        $notMine = static fn (array $row): bool => (int) ($row['user_id'] ?? 0) !== $userId;
        try {
            $rows = $repository->reviewRows($tenantId);
        } catch (\PDOException) {
            $rows = [];
        }
        return \App\Services\Academy\AcademyInstructorQueue::badge($rows, $rights->canTeach(...), count(array_filter($this->pendingEnrollments($rights, $tenantId), $notMine)), count(array_filter($this->pendingProposals($tenantId), $notMine)), $userId);
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

    private function credentials(): AcademyStudioCredentials
    {
        return Container::get(AcademyStudioCredentials::class);
    }

    /** Ce que la formule permet d'ajouter au référentiel des qualifications ; administrateur plateforme : sans limite. */
    private function qualificationQuota(int $tenantId): \App\Services\Billing\QualificationCatalogQuota
    {
        try {
            return \App\Services\Billing\QualificationCatalogQuota::forTenant($tenantId, \App\Core\Database::getPdo(), Container::get(\App\Services\Platform\FeatureGateService::class), Gate::getInstance()->isPlatformAdmin());
        } catch (\Throwable) {
            return \App\Services\Billing\QualificationCatalogQuota::unlimited();
        }
    }

    /** Créer et modifier les qualifications et brevets du Studio : mêmes droits que créer ou modifier un cours. */
    private function canManageCredentials(AcademyRights $rights): bool
    {
        return $rights->can('create') || $rights->can('edit');
    }

    /** @return list<string> versions précédentes d'un cours du Studio */
    private function olderVersions(int $tenantId, string $key): array
    {
        try {
            return \App\Services\Academy\AcademyBuiltInQualifications::forPdo(\App\Core\Database::getPdo())->lineage($tenantId)[$key] ?? [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Cours qui peuvent mener à une qualification : cours du Studio (hors corbeille) et cours intégrés.
     *
     * @return array<string, array{title: string, source: string, status: string}>
     */
    private function credentialCourses(AcademyRepository $repository, AcademyRights $rights, int $tenantId): array
    {
        $lang = locale() === 'en' ? 'en' : 'fr';
        $titleOf = static fn (array $c): string => trim((string) ($c['title'][$lang] ?? '')) ?: (trim((string) ($c['title']['fr'] ?? '')) ?: (string) $c['key']);
        $out = [];
        try {
            foreach ($repository->studioList($tenantId) as $c) {
                if (!empty($c['deleted_at']) || (empty($c['published']) && $rights->studioRole($c) === null)) {
                    continue;
                }
                $status = !empty($c['archived']) ? 'archived' : (!empty($c['published']) ? 'pub' : (isset($c['replaces']) ? 'version' : 'draft'));
                $out[(string) $c['key']] = ['title' => $titleOf($c), 'source' => 'studio', 'status' => $status];
            }
        } catch (\PDOException | \JsonException) {
        }
        foreach (AcademyBuiltInCourses::all() as $c) {
            $out[(string) $c['key']] = ['title' => $titleOf($c), 'source' => 'builtin', 'status' => 'builtin'];
        }
        return $out;
    }

    /** Compteurs des onglets du Studio : qualifications et brevets actifs. @return array<string,int> */
    private function credentialCounts(int $tenantId): array
    {
        $counts = ['qualification' => 0, 'brevet' => 0];
        try {
            foreach ($this->credentials()->list($tenantId) as $c) {
                if (empty($c['archived'])) {
                    $counts[$c['kind']]++;
                }
            }
        } catch (\Throwable) {
        }
        return $counts;
    }

    /** Réglages du cours : ce qu'il délivre à la réussite, et ce qu'on peut y rattacher. */
    private function courseCredentials(AcademyRights $rights, int $tenantId, string $key): ?array
    {
        try {
            $credentials = $this->credentials();
            if (!$credentials->ready()) {
                return null;
            }
            $linked = $credentials->forCourse($tenantId, $key, $this->olderVersions($tenantId, $key));
            $linkedIds = array_column($linked, 'id');
            $available = array_values(array_filter($credentials->list($tenantId), static fn (array $c): bool => empty($c['archived']) && !in_array($c['id'], $linkedIds, true)));
            return ['linked' => $linked, 'available' => $available, 'canManage' => $this->canManageCredentials($rights), 'rulesReady' => $credentials->rulesReady()];
        } catch (\Throwable $e) {
            error_log('[academy] qualifications du cours ' . $key . ' : ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Studio, qualifications et brevets : listes (?vue=qualifications|brevets), création (?nouveau=qualification|brevet,
     * &cours=clé pour la rattacher d'emblée à un cours) et fiche (?fiche=id). Null : autre page du Studio.
     */
    private function credentialsPage(Request $request, AcademyRights $rights, AcademyRepository $repository, int $tenantId): ?Response
    {
        $view = $request->queryString('vue');
        $new = $request->queryString('nouveau');
        $fiche = $request->queryString('fiche');
        $isList = in_array($view, ['qualifications', 'brevets'], true);
        if (!$isList && !in_array($new, AcademyStudioCredentials::KINDS, true) && !ctype_digit($fiche)) {
            return null;
        }
        if (!$rights->entersStudio()) {
            return $this->error('forbidden', 403);
        }
        $credentials = $this->credentials();
        if (!$credentials->ready()) {
            return $this->error('unavailable', 503);
        }
        $canManage = $this->canManageCredentials($rights);
        $editing = null;
        $kind = $isList ? ($view === 'brevets' ? 'brevet' : 'qualification') : $new;
        if (!$isList && ctype_digit($fiche)) {
            $editing = $credentials->find($tenantId, (int) $fiche);
            if ($editing === null) {
                return $this->error('not_found', 404);
            }
            $kind = $editing['kind'];
        } elseif (!$isList && !$canManage) {
            return $this->error('forbidden', 403);
        }
        $origin = in_array($request->queryString('origine'), ['communaute', 'base', 'archives'], true) ? $request->queryString('origine') : 'tout';
        $courses = $this->credentialCourses($repository, $rights, $tenantId);
        $notice = (string) Session::get('academy_notice', '');
        Session::set('academy_notice', '');
        $form = Session::get('academy_form', []);
        Session::set('academy_form', []);
        $data = [
            'space' => 'studio', 'tenant' => Container::get(TenantRepository::class)->findById($tenantId), 'notice' => $notice,
            'canManage' => $canManage, 'canArchive' => $rights->can('publish'), 'credKind' => $kind, 'credList' => $isList,
            'credOrigin' => $origin, 'credentials' => $credentials->list($tenantId), 'editing' => $editing, 'studioCourses' => $courses,
            'counts' => $this->credentialCounts($tenantId), 'rulesReady' => $credentials->rulesReady(), 'oldForm' => is_array($form) ? $form : [],
            'credQuota' => $this->qualificationQuota($tenantId)->status(\App\Services\Billing\QualificationCatalogQuota::CUSTOM),
        ];
        if (!$isList) {
            $referentiel = Container::get(\App\Repositories\QualificationReferentielRepository::class);
            $lang = locale() === 'en' ? 'en' : 'fr';
            $prefill = $request->queryString('cours');
            $data += [
                'categories' => $referentiel->listCategories($tenantId),
                'catalogCategories' => array_values(array_map(static fn (array $label): string => (string) $label[$lang], \App\Support\QualificationCatalog\QualificationCatalog::CATEGORIES)),
                'issuers' => array_values(array_filter((new \App\Repositories\QualificationIssuerRepository())->listForTenant($tenantId), static fn (array $i): bool => empty($i['archived_at']))),
                'templates' => $referentiel->listCertificateTemplates($tenantId),
                'metricOptions' => array_map(static fn (array $m): string => $m['label'], \App\Support\QualificationCriteriaCatalog::METRICS),
                'aircraftOptions' => $credentials->rulesReady() ? Container::get(\App\Services\Personnel\QualificationAwardRuleService::class)->aircraftOptions($tenantId) : [],
                'prefillCourse' => isset($courses[$prefill]) ? $prefill : '',
                'backOfficeUrl' => $editing !== null && Gate::getInstance()->allows('admin.organization') ? url('back-office/referentiels/qualifications/' . (int) $editing['id'] . '/edit') : '',
            ];
            // Prérequis lus dans le texte de la fiche : proposés dans l'encart « Prérequis détectés ».
            if ($editing !== null && $canManage && $credentials->rulesReady()) {
                try {
                    $data['detected'] = Container::get(\App\Services\Personnel\QualificationPrerequisiteSuggester::class)->forDefinition($tenantId, (int) $editing['id'], $lang);
                    $data['criterionNames'] = Container::get(\App\Services\Personnel\QualificationAwardRuleService::class)->names($tenantId);
                } catch (\Throwable $e) {
                    error_log('Prérequis détectés (Studio) : ' . $e->getMessage());
                }
            }
        }
        return Response::view('academy.studio_credentials', $data)->header('Cache-Control', 'private, no-store');
    }

    private function credentialUrl(string $query): string
    {
        return url('academy/studio?' . $query);
    }

    /** Qualifications et brevets du Studio : enregistrer (avec l'insigne), archiver, rattacher ou détacher un cours. */
    private function credentialAction(Request $request, AcademyRepository $repository, AcademyRights $rights, string $action, string $key, int $tenantId, int $userId): Response
    {
        $credentials = $this->credentials();
        $id = (int) $request->inputString('cred_id');
        $kind = in_array($request->inputString('cred_kind'), AcademyStudioCredentials::KINDS, true) ? $request->inputString('cred_kind') : 'qualification';
        if (!($action === 'studio_cred_archive' ? $rights->can('publish') : $this->canManageCredentials($rights))) {
            return $this->error('forbidden', 403);
        }
        // Rattacher depuis les réglages d'un cours : retour au cours ; sinon retour à la fiche.
        $fromCourse = $request->inputString('return') === 'course' && $key !== 'academy';
        $back = fn (int $credId, string $notice): Response => $this->credentialBack($fromCourse ? $this->studioUrl($key)
            : $this->credentialUrl($credId > 0 ? 'fiche=' . $credId : 'nouveau=' . $kind), $notice);
        try {
            if ($action === 'studio_cred_save') {
                $form = [];
                foreach (\App\Support\QualificationDefinitionPayload::FIELDS as $field) {
                    if ($request->input($field) !== null) {
                        $form[$field] = $request->input($field);
                    }
                }
                foreach (['is_permanent', 'requires_exam', 'renewal_required', 'uses_levels', 'auto_enabled'] as $box) {
                    $form[$box] = $request->inputString($box) === '1';
                }
                $form += ['issuer_id' => $request->inputString('issuer_id'), 'category_new' => $request->inputString('category_new'), 'auto_mode' => $request->inputString('auto_mode')];
                $conditions = $request->input('conditions');
                if (is_array($conditions)) {
                    $form['conditions'] = $conditions;
                }
                // Création : dans la limite de la formule (les fiches existantes restent modifiables).
                if ($id <= 0 && !$this->qualificationQuota($tenantId)->canAdd(\App\Services\Billing\QualificationCatalogQuota::CUSTOM)) {
                    Session::set('academy_form', $form);
                    return $back(0, 'cred_quota_reached');
                }
                $saved = $credentials->save($tenantId, $id > 0 ? $id : null, $kind, $form, $userId);
                $notice = $id > 0 ? 'cred_saved' : 'cred_created';
                $file = $_FILES['badge'] ?? null;
                if (is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    try {
                        $credentials->saveBadge($tenantId, $saved, $file);
                    } catch (\Throwable) {
                        $notice = 'cred_badge_failed';
                    }
                }
                $this->audit($id > 0 ? 'academy.credential.updated' : 'academy.credential.created', $tenantId, $key, ['definition' => $saved, 'kind' => $kind]);
                return $this->credentialBack($this->credentialUrl('fiche=' . $saved), $notice);
            }
            if ($action === 'studio_cred_prereqs') {
                // Encart « Prérequis détectés » : suggestions cochées, valeur éventuellement corrigée.
                $values = $request->input('value');
                $selected = [];
                $picks = $request->input('pick');
                foreach (is_array($picks) ? $picks : [] as $sid) {
                    if (is_string($sid) && preg_match('/\A[a-z]{1,2}(?::[A-Za-z0-9_.\-]*){0,5}\z/', $sid) === 1) {
                        $v = is_array($values) ? ($values[$sid] ?? null) : null;
                        $selected[$sid] = is_scalar($v) ? (string) $v : null;
                    }
                }
                $r = Container::get(\App\Services\Personnel\QualificationPrerequisiteSuggester::class)->apply($tenantId, $id, $selected, $userId, locale() === 'en' ? 'en' : 'fr');
                $this->audit('academy.credential.prerequisites_added', $tenantId, $key, ['definition' => $id, 'added' => $r['added']]);
                return $back($id, $r['added'] > 0 ? 'cred_prereqs_added' : 'cred_prereqs_none');
            }
            if ($action === 'studio_cred_archive') {
                $credentials->archive($tenantId, $id, $userId);
                $this->audit('academy.credential.archived', $tenantId, $key, ['definition' => $id]);
                return $this->credentialBack($this->credentialUrl('vue=' . ($kind === 'brevet' ? 'brevets' : 'qualifications')), 'cred_archived');
            }
            if (in_array($action, ['studio_cred_link', 'studio_cred_unlink'], true)) {
                $courseKey = $fromCourse ? $key : $request->inputString('link_course');
                $id = $id > 0 ? $id : (int) $request->inputString('link_cred');
                if (!isset($this->credentialCourses($repository, $rights, $tenantId)[$courseKey])) {
                    return $back($id, 'not_found');
                }
                if ($action === 'studio_cred_link') {
                    $credentials->linkCourse($tenantId, $id, $courseKey, $userId);
                } else {
                    $credentials->unlinkCourse($tenantId, $id, $courseKey, $this->olderVersions($tenantId, $courseKey), $userId);
                }
                $this->audit($action === 'studio_cred_link' ? 'academy.credential.course_linked' : 'academy.credential.course_unlinked', $tenantId, $courseKey, ['definition' => $id]);
                return $back($id, $action === 'studio_cred_link' ? 'cred_linked' : 'cred_unlinked');
            }
        } catch (\RuntimeException $e) {
            if ($action === 'studio_cred_save') {
                // Formulaire refusé : les valeurs saisies sont reproposées.
                Session::set('academy_form', array_intersect_key($_POST, array_flip(array_merge(\App\Support\QualificationDefinitionPayload::FIELDS, ['issuer_id', 'category_new']))));
            }
            $known = ['not_found', 'cred_unavailable', 'cred_name_required', 'cred_code_invalid', 'cred_code_taken', 'cred_rules_pending'];
            return $back($id, in_array($e->getMessage(), $known, true) ? $e->getMessage() : 'cred_rule_error');
        }
        return $this->error('invalid_content', 422);
    }

    private function credentialBack(string $url, string $notice): Response
    {
        Session::set('academy_notice', $notice);
        return Response::redirect($url, 303);
    }

    private function error(string $message, int $status): Response
    {
        return Response::view('academy.error', ['message' => $message])->setStatusCode($status)->header('Cache-Control', 'private, no-store');
    }
}
