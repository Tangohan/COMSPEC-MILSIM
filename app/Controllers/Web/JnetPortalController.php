<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TenantMessageRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Services\Auth\AuthService;
use App\Services\Jnet\JnetDashboardService;
use App\Services\Rbac\RbacService;
use App\Support\PortalAccessChoice;

final class JnetPortalController
{
    public function __construct(
        private ?AuthService $authService = null,
        private ?RbacService $rbacService = null,
        private ?UserRepository $userRepository = null,
        private ?TenantRepository $tenantRepository = null,
        private ?TenantMessageRepository $messageRepository = null,
        private ?JnetDashboardService $jnet = null,
    ) {
        $this->authService ??= \App\Core\Container::get(AuthService::class);
        $this->rbacService ??= \App\Core\Container::get(RbacService::class);
        $this->userRepository ??= \App\Core\Container::get(UserRepository::class);
        $this->tenantRepository ??= \App\Core\Container::get(TenantRepository::class);
        $this->messageRepository ??= new TenantMessageRepository();
        $this->jnet ??= new JnetDashboardService();
    }

    public function home(Request $request, array $params = []): Response
    {
        $ctx = $this->ensureAuth();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $dash = $this->jnet->buildHome($ctx['tenant_id'], $ctx['user_id']);

        return $this->render('home', 'Accueil', $dash, 'home');
    }

    public function unit(Request $request, array $params = []): Response
    {
        $ctx = $this->ensureAuth();
        if ($ctx instanceof Response) {
            return $ctx;
        }

        return $this->render('unit', 'Unité', $this->jnet->buildUnitPage($ctx['tenant_id'], $ctx['user_id']), 'unit');
    }

    public function personnel(Request $request, array $params = []): Response
    {
        $ctx = $this->ensureAuth();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $filter = strtolower(trim((string) $request->query('filtre', 'all')));
        $all = $this->jnet->loadPersonnelCards($ctx['tenant_id']);
        $filtered = array_values(array_filter($all, static function (array $p) use ($filter): bool {
            if ($filter === '' || $filter === 'all') {
                return true;
            }
            if ($filter === 'deployed') {
                return ($p['duty'] ?? '') === 'deployed';
            }
            if ($filter === 'off') {
                return ($p['duty'] ?? '') === 'off';
            }
            $hay = strtoupper((string) ($p['unit'] ?? '') . ' ' . ($p['function'] ?? '') . ' ' . ($p['role'] ?? ''));

            return str_contains($hay, strtoupper($filter));
        }));

        return $this->render('personnel', 'Personnel', [
            'personnel' => $filtered,
            'personnelFilter' => $filter,
            'personnelTotal' => count($all),
        ], 'personnel');
    }

    public function personnelShow(Request $request, array $params = []): Response
    {
        $ctx = $this->ensureAuth();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $id = (int) ($params['id'] ?? 0);
        $card = $this->jnet->findPersonnelCard($ctx['tenant_id'], $id);
        if ($card === null) {
            Session::flash('error', 'Fiche personnel introuvable.');

            return Response::redirect(url('jnet/personnel'));
        }

        return $this->render('personnel_show', (string) ($card['name'] ?? 'Personnel'), [
            'person' => $card,
        ], 'personnel');
    }

    public function operations(Request $request, array $params = []): Response
    {
        $ctx = $this->ensureAuth();
        if ($ctx instanceof Response) {
            return $ctx;
        }

        return $this->render('operations', 'Opérations', [
            'operations' => $this->jnet->loadOperations($ctx['tenant_id']),
        ], 'operations');
    }

    public function operationShow(Request $request, array $params = []): Response
    {
        $ctx = $this->ensureAuth();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $op = $this->jnet->findOperation($ctx['tenant_id'], (int) ($params['id'] ?? 0));
        if ($op === null) {
            Session::flash('error', 'Opération introuvable.');

            return Response::redirect(url('jnet/operations'));
        }

        return $this->render('operation_show', (string) ($op['title'] ?? 'Opération'), [
            'operation' => $op,
        ], 'operations');
    }

    public function intelligence(Request $request, array $params = []): Response
    {
        $ctx = $this->ensureAuth();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $home = $this->jnet->buildHome($ctx['tenant_id'], $ctx['user_id']);

        return $this->render('intelligence', 'Renseignement', [
            'intelFeed' => $home['intelFeed'],
            'priorityTargets' => $home['priorityTargets'],
            'viewerLens' => $home['viewerLens'],
        ], 'intelligence');
    }

    public function targets(Request $request, array $params = []): Response
    {
        $ctx = $this->ensureAuth();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $targets = $this->jnet->loadTargets($ctx['tenant_id']);

        return $this->render('targets', 'Cibles prioritaires', [
            'targets' => $targets,
            'targetsTotal' => count($targets),
        ], 'targets');
    }

    public function targetShow(Request $request, array $params = []): Response
    {
        $ctx = $this->ensureAuth();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $id = rawurldecode((string) ($params['id'] ?? ''));
        $target = $this->jnet->findTarget($ctx['tenant_id'], $id);
        if ($target === null) {
            Session::flash('error', 'Dossier cible introuvable.');

            return Response::redirect(url('jnet/cibles'));
        }
        $tab = strtolower(trim((string) $request->query('onglet', 'profil')));

        return $this->render('target_show', (string) ($target['name'] ?? 'Cible'), [
            'target' => $target,
            'targetTab' => $tab,
        ], 'targets');
    }

    public function exploitation(Request $request, array $params = []): Response
    {
        return $this->render('exploitation', 'Exploitation', [
            'links' => [
                ['label' => 'Bureau SSE', 'desc' => 'Dossiers, identités, sites et preuves terrain', 'href' => url('atak/sse')],
                ['label' => 'Laboratoire numérique', 'desc' => 'Terminaux, acquisitions et artéfacts', 'href' => url('atak/sse/numerique')],
                ['label' => 'Croisements', 'desc' => 'Corrélations et listes de surveillance', 'href' => url('atak/sse/croisements')],
                ['label' => 'Transmission', 'desc' => 'Journaux de mission et comptes rendus', 'href' => url('transmission')],
            ],
        ], 'exploitation');
    }

    public function library(Request $request, array $params = []): Response
    {
        return $this->render('library', 'Bibliothèque', [
            'sections' => [
                ['label' => 'Doctrine & guides', 'items' => ['Procédures d’unité', 'Guide SSE', 'Normes de rédaction']],
                ['label' => 'Briefings', 'items' => ['Briefing courant', 'Intentions de commandement', 'Situations hebdomadaires']],
                ['label' => 'Archives', 'items' => ['Comptes rendus classés', 'Dossiers clos', 'Exports de mission']],
            ],
            'athenaDocs' => url('documents'),
            'sseGuide' => url('atak/sse/guide'),
        ], 'library');
    }

    public function mail(Request $request, array $params = []): Response
    {
        $ctx = $this->sessionContext();
        $threads = [];
        $selected = null;
        $messages = [];
        if ($ctx !== null) {
            $auth = $this->ensureAuth();
            if ($auth instanceof Response) {
                return $auth;
            }
            $threads = $this->messageRepository->listThreadsForUser($ctx['tenant_id'], $ctx['user_id']);
            $threadId = (int) $request->query('fil', 0);
            if ($threadId <= 0 && $threads !== []) {
                $threadId = (int) ($threads[0]['id'] ?? 0);
            }
            if ($threadId > 0) {
                foreach ($threads as $t) {
                    if ((int) ($t['id'] ?? 0) === $threadId) {
                        $selected = $t;
                        break;
                    }
                }
                if ($selected !== null && $this->messageRepository->userInThread($threadId, $ctx['user_id'])) {
                    $this->messageRepository->markThreadRead($threadId, $ctx['user_id']);
                    $messages = $this->messageRepository->listMessages($threadId);
                } else {
                    $selected = null;
                }
            }
        }

        return $this->render('mail', 'Messagerie', [
            'mailThreads' => $threads,
            'mailSelected' => $selected,
            'mailMessages' => $messages,
            'mailCurrentUserId' => $ctx['user_id'] ?? 0,
            'mailFullUrl' => url('messages'),
        ], 'inbox');
    }

    public function system(Request $request, array $params = []): Response
    {
        return $this->render('system', 'Système', [
            'canTba' => PortalAccessChoice::canAccessTba(),
            'preferredPortal' => PortalAccessChoice::remembered(),
        ], 'system');
    }

    public function switchPortal(Request $request, array $params = []): Response
    {
        if (!$request->isPost()) {
            return Response::redirect(url('jnet/systeme'));
        }
        if (!\App\Core\Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('jnet/systeme'));
        }
        $action = (string) $request->input('action', '');
        if ($action === 'clear_preference') {
            PortalAccessChoice::clearRemembered();
            Session::flash('success', 'Le choix d’espace mémorisé a été effacé.');

            return Response::redirect(url('login/choisir-espace'));
        }
        if ($action === 'tba') {
            if (!PortalAccessChoice::canAccessTba()) {
                Session::flash('error', 'Accès administratif indisponible pour votre compte.');

                return Response::redirect(url('jnet/systeme'));
            }
            PortalAccessChoice::remember(PortalAccessChoice::PORTAL_TBA, false);

            return Response::redirect(url('back-office'));
        }
        if ($action === 'chooser') {
            return Response::redirect(url('login/choisir-espace'));
        }

        return Response::redirect(url('jnet/systeme'));
    }

    /** @deprecated keep redirects for old bookmarks */
    public function theatre(Request $request, array $params = []): Response
    {
        return Response::redirect(url('jnet/operations'));
    }

    public function sources(Request $request, array $params = []): Response
    {
        return Response::redirect(url('jnet/renseignement'));
    }

    public function intents(Request $request, array $params = []): Response
    {
        return Response::redirect(url('jnet/renseignement'));
    }

    public function roster(Request $request, array $params = []): Response
    {
        return Response::redirect(url('jnet/personnel'));
    }

    public function apps(Request $request, array $params = []): Response
    {
        return Response::redirect(url('jnet/bibliotheque'));
    }

    public function sync(Request $request, array $params = []): Response
    {
        return Response::redirect(url('jnet'));
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function render(string $view, string $title, array $extra = [], ?string $activeNav = null): Response
    {
        $ctx = $this->ensureAuth();
        if ($ctx instanceof Response) {
            return $ctx;
        }

        $tenant = $this->tenantRepository->findById($ctx['tenant_id']);
        $tenantName = is_array($tenant) ? community_display_name($tenant) : 'Communauté';
        $nodeId = $this->nodeIdForTenant($ctx['tenant_id'], is_array($tenant) ? (string) ($tenant['slug'] ?? '') : '');

        $payload = array_merge([
            'title' => $title,
            'activeNav' => $activeNav ?? $view,
            'jnetTenantName' => $tenantName,
            'jnetDisplayName' => (string) Session::get('display_name', ''),
            'jnetCallsign' => (string) Session::get('callsign', ''),
            'jnetNodeId' => $nodeId,
            'jnetCanTba' => PortalAccessChoice::canAccessTba(),
            'jnetUnreadMail' => $this->unreadMailCount($ctx['tenant_id'], $ctx['user_id']),
            'jnetDtg' => strtoupper(gmdate('dHi') . 'Z' . gmdate('M y')),
            'jnetContentView' => 'jnet.' . $view,
        ], $extra);

        return Response::view('jnet._layout', $payload);
    }

    private function ensureAuth(): array|Response
    {
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        $ctx = $this->sessionContext();
        if ($ctx === null) {
            return Response::redirect(url('login'));
        }
        $user = $this->authService->user();
        if ($user) {
            $this->rbacService->setPermissionsForGateFromUserRow($user, $this->userRepository);
        }

        return $ctx;
    }

    /** @return array{tenant_id:int,user_id:int}|null */
    private function sessionContext(): ?array
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId <= 0 || $userId <= 0) {
            return null;
        }

        return ['tenant_id' => $tenantId, 'user_id' => $userId];
    }

    private function unreadMailCount(int $tenantId, int $userId): int
    {
        try {
            return $this->messageRepository->unreadCountForUser($tenantId, $userId);
        } catch (\Throwable) {
            return 0;
        }
    }

    private function nodeIdForTenant(int $tenantId, string $slug): string
    {
        $base = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $slug) ?? '');
        if ($base === '') {
            $base = 'NODE';
        }
        $base = substr($base, 0, 4);
        $suffix = strtoupper(substr(dechex(($tenantId * 7919) % 65535), -3));

        return $base . $suffix;
    }
}
