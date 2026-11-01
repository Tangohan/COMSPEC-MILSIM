<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Container;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\AcademyRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Services\Academy\AcademyAccess;
use App\Services\Academy\AcademyGameLesson;
use App\Services\Moderation\ModerationRestrictionResolver;
use App\Services\Moderation\ModerationRestrictionsCatalog;
use App\Services\Platform\FeatureGateService;
use App\Services\Rbac\RbacService;
use App\Support\ComspecApiKeyAuth;
use App\Support\TrainingLmsStaffAccess;

/**
 * Lecteur Academy en jeu (app Academy de l'ATAK natif, commande « Academy » de COMSPEC Link 2.0.113+).
 * Jeton : la session de jeu du compte (jeton court émis et rafraîchi par COMSPEC Link, jamais de mot de passe dans
 * le jeu). Mêmes droits que /academy : communauté abonnée, module Formation ouvert, pas de restriction de modération ;
 * l'élève ne lit que ses cours suivis (leçons atteintes), l'encadrement Formation tous les cours de la communauté.
 *
 *  GET  /api/atak/academy/courses?lang=fr|en
 *  GET  /api/atak/academy/lesson?course=&chapter=&lang=&from=
 *  POST /api/atak/academy/seen   {"course","chapter","stage"}  cours ou démonstration lus jusqu'au bout
 * Réponses en texte ligne (AcademyGameLesson), relayées telles quelles par COMSPEC Link.
 */
final class AcademyGameApiController
{
    public function courses(Request $request, array $params = []): Response
    {
        $ctx = $this->context();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $userId, $staff] = $ctx;
        $lang = $this->lang($request);
        try {
            $repo = new AcademyRepository();
            $body = AcademyGameLesson::coursesBody($repo->states($tenantId, $userId), $staff ? $repo->courses($tenantId, true) : [], $staff, $lang);
        } catch (\PDOException | \JsonException) {
            return $this->text('unavailable', 503);
        }

        return $this->text($body, 200, 'private, no-store');
    }

    public function lesson(Request $request, array $params = []): Response
    {
        $ctx = $this->context();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $userId, $staff] = $ctx;
        $key = $request->queryString('course');
        $chapter = (int) $request->queryString('chapter', '0');
        if (!preg_match('/\A[a-z0-9-]{1,64}\z/', $key) || $chapter < 0 || $chapter > 99) {
            return $this->text('invalid', 422);
        }
        try {
            $repo = new AcademyRepository();
            $state = $repo->states($tenantId, $userId)[$key] ?? null;
            // L'élève lit sa copie du cours (celle de son inscription) ; l'encadrement, le cours publié ou en brouillon.
            $course = is_array($state['snapshot'] ?? null) ? $state['snapshot'] : null;
            if ($staff && $course === null) {
                foreach ($repo->courses($tenantId, true) as $c) {
                    if (($c['key'] ?? '') === $key) {
                        $course = $c;
                        break;
                    }
                }
            }
        } catch (\PDOException | \JsonException) {
            return $this->text('unavailable', 503);
        }
        if ($course === null || !isset($course['chapters'][$chapter]) || !AcademyGameLesson::chapterAllowed($state, $chapter, $staff)) {
            return $this->text('not_found', 404);
        }
        $stage = $state !== null && (int) ($state['chapter'] ?? -1) === $chapter && empty($state['completed']) ? (int) ($state['stage'] ?? 0) : -1;

        return $this->text(AcademyGameLesson::lessonBody($course, $key, $chapter, $this->lang($request), (int) $request->queryString('from', '0'), $stage), 200, 'private, max-age=60');
    }

    public function seen(Request $request, array $params = []): Response
    {
        $ctx = $this->context();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $userId] = $ctx;
        $body = ComspecApiKeyAuth::peekJsonObject();
        $key = is_string($body['course'] ?? null) ? $body['course'] : '';
        $chapter = is_numeric($body['chapter'] ?? null) ? (int) $body['chapter'] : -1;
        $stage = is_numeric($body['stage'] ?? null) ? (int) $body['stage'] : -1;
        if (!preg_match('/\A[a-z0-9-]{1,64}\z/', $key) || $chapter < 0) {
            return $this->text('invalid', 422);
        }
        try {
            $repo = new AcademyRepository();
            $state = $repo->states($tenantId, $userId)[$key] ?? null;
            if ($state === null) {
                return $this->text('not_enrolled', 404);
            }
            // Lecture d'abord : rien n'est écrit quand l'étape est déjà franchie (page relue, deuxième téléphone).
            if (AcademyGameLesson::seen($state, $chapter, $stage, time()) !== null) {
                $repo->mutate($tenantId, $userId, $key, static fn (array $s): array => AcademyGameLesson::seen($s, $chapter, $stage, time()) ?? $s);
                $state = $repo->states($tenantId, $userId)[$key] ?? $state;
            }
        } catch (\PDOException | \JsonException) {
            return $this->text('unavailable', 503);
        } catch (\DomainException) {
            return $this->text('locked', 409);
        }

        return $this->text("OK\t" . (int) ($state['chapter'] ?? 0) . "\t" . (int) ($state['stage'] ?? 0) . "\t" . (int) !empty($state['completed']) . "\n");
    }

    /** @return array{0:int,1:int,2:bool}|Response */
    private function context(): array|Response
    {
        $userId = (int) (ComspecApiKeyAuth::matchedUserId() ?? 0);
        $tenantId = (int) (ComspecApiKeyAuth::matchedTenantId() ?? 0);
        // Clé de communauté seule (sans compte lié) : pas de cours personnels.
        if ($userId < 1) {
            return $this->text('session_required', 401);
        }
        if ($tenantId < 1) {
            return $this->text('tenant_context_required', 403);
        }
        try {
            $tenant = Container::get(TenantRepository::class)->findById($tenantId);
            if (!$tenant || !AcademyAccess::paid($tenant)) {
                return $this->text('academy_unpaid', 403);
            }
            if (!Container::get(FeatureGateService::class)->allows($tenantId, 'training')) {
                return $this->text('module_unavailable', 403);
            }
            if (!Container::get(ModerationRestrictionResolver::class)->isModuleAllowed($tenantId, $userId, ModerationRestrictionsCatalog::KEY_TRAINING)) {
                return $this->text('restricted', 403);
            }
            $staff = false;
            $users = Container::get(UserRepository::class);
            $user = $users->findById($userId);
            // Droits Formation calculés comme à la connexion au site, et seulement dans la communauté du compte.
            if (is_array($user) && (int) ($user['tenant_id'] ?? 0) === $tenantId) {
                (new RbacService())->setPermissionsForGateFromUserRow($user, $users);
                $staff = TrainingLmsStaffAccess::allows(Gate::getInstance());
            }
        } catch (\PDOException) {
            return $this->text('unavailable', 503);
        }

        return [$tenantId, $userId, $staff];
    }

    private function lang(Request $request): string
    {
        return $request->queryString('lang') === 'en' ? 'en' : 'fr';
    }

    private function text(string $body, int $status = 200, string $cache = 'no-store'): Response
    {
        return (new Response())->setStatusCode($status)->header('Content-Type', 'text/plain; charset=utf-8')
            ->header('Cache-Control', $cache)->setBody($body);
    }
}
