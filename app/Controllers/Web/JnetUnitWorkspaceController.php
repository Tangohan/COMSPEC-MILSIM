<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\CommunityEventRepository;
use App\Repositories\UserRepository;
use App\Services\Auth\AuthService;
use App\Services\Jnet\JnetSpaceService;
use App\Services\Jnet\JnetUnitSpaceSettings;
use App\Services\Jnet\JnetUnitWorkspaceService;
use App\Services\Rbac\RbacService;
use App\Support\JnetSpaceTree;
use App\Support\PortalAccessChoice;

/**
 * Écritures des outils de l'espace d'une unité JNET : tâches, pages, réactions, commentaires, sondages, réponse de
 * présence au prochain rendez-vous, relais d'un ordre aux sous-unités. Toutes les routes sont en POST, avec jeton CSRF ;
 * chaque contrôle d'accès est refait ici, côté serveur : l'espace doit être visible du lecteur, et il doit pouvoir y
 * publier (JnetSpaceTree::canPostIn, comme pour les échanges). Supprimer ce qu'un autre a écrit demande d'être chef de
 * l'espace (même droit que « Personnaliser »). Les messages sont affichés en ligne dans la page (flash), jamais en fenêtre.
 */
final class JnetUnitWorkspaceController
{
    public function __construct(
        private ?AuthService $authService = null,
        private ?RbacService $rbacService = null,
        private ?UserRepository $userRepository = null,
        private ?JnetSpaceService $spaces = null,
        private ?JnetUnitSpaceSettings $spaceSettings = null,
        private ?JnetUnitWorkspaceService $workspace = null,
    ) {
        $this->authService ??= \App\Core\Container::get(AuthService::class);
        $this->rbacService ??= \App\Core\Container::get(RbacService::class);
        $this->userRepository ??= \App\Core\Container::get(UserRepository::class);
        $this->spaces ??= new JnetSpaceService();
        $this->spaceSettings ??= new JnetUnitSpaceSettings();
        $this->workspace ??= new JnetUnitWorkspaceService();
    }

    // ——— Tâches ———

    public function addTask(Request $request, array $params = []): Response
    {
        $g = $this->guard($request, $params, 'taches', true);
        if ($g instanceof Response) {
            return $g;
        }
        $assignee = (int) $request->input('assignee', 0);
        $error = $this->workspace->addTask(
            $g['tenant'],
            $g['unit'],
            $g['user'],
            (string) $request->input('title', ''),
            (string) $request->input('status', 'todo'),
            $assignee > 0 ? $assignee : null,
            $this->memberIds($g['tree'], $g['unit'])
        );

        return $this->done($g['back'] . '#taches', $error, null);
    }

    /** Une action sur une tâche : changer d'état, de responsable, ou la retirer (champ « act »). */
    public function taskAction(Request $request, array $params = []): Response
    {
        $g = $this->guard($request, $params, 'taches', true);
        if ($g instanceof Response) {
            return $g;
        }
        $repo = $this->workspace->repo();
        $task = $repo->task($g['tenant'], $g['unit'], (int) ($params['tid'] ?? 0));
        if ($task === null) {
            return $this->done($g['back'], 'Tâche introuvable.', null);
        }
        $act = (string) $request->input('act', '');
        if ($act === 'status') {
            $status = (string) $request->input('status', '');
            if (!isset(JnetUnitWorkspaceService::TASK_LABELS[$status])) {
                return $this->done($g['back'], 'État inconnu.', null);
            }
            $repo->setTaskStatus($g['tenant'], $g['unit'], (int) $task['id'], $status);
        } elseif ($act === 'assign') {
            $to = (int) $request->input('assignee', 0);
            $repo->setTaskAssignee($g['tenant'], $g['unit'], (int) $task['id'], $to > 0 && in_array($to, $this->memberIds($g['tree'], $g['unit']), true) ? $to : null);
        } elseif ($act === 'delete') {
            if (!JnetUnitWorkspaceService::canEditTask($task, $g['user'], $g['chief'])) {
                return $this->done($g['back'], 'Seuls l’auteur, le responsable ou le chef de l’espace retirent cette tâche.', null);
            }
            $repo->deleteTask($g['tenant'], $g['unit'], (int) $task['id']);
        }

        return $this->done($g['back'] . '#taches', null, null);
    }

    // ——— Pages ———

    public function createPage(Request $request, array $params = []): Response
    {
        $g = $this->guard($request, $params, 'pages', true);
        if ($g instanceof Response) {
            return $g;
        }
        $id = $this->workspace->repo()->createPage(
            $g['tenant'],
            $g['unit'],
            $g['user'],
            JnetUnitWorkspaceService::clean((string) $request->input('title', ''), JnetUnitWorkspaceService::TITLE_MAX, true),
            JnetUnitWorkspaceService::blocksToJson([JnetUnitWorkspaceService::newBlock('p')])
        );

        return Response::redirect($g['base'] . '?vue=page&p=' . $id . '&edit=1&direct=1#page-title');
    }

    /** Enregistre la page (titre et blocs), ou applique un geste de l'éditeur (ajouter, monter, descendre, retirer un bloc). */
    public function savePage(Request $request, array $params = []): Response
    {
        $g = $this->guard($request, $params, 'pages', true);
        if ($g instanceof Response) {
            return $g;
        }
        $pageId = (int) ($params['pid'] ?? 0);
        $repo = $this->workspace->repo();
        $page = $repo->page($g['tenant'], $g['unit'], $pageId);
        $edit = $g['base'] . '?vue=page&p=' . $pageId . '&edit=1&direct=1';
        if ($page === null) {
            return $this->done($g['base'] . '?vue=pages&direct=1', 'Page introuvable.', null);
        }
        $blocks = JnetUnitWorkspaceService::sanitizeBlocks($request->input('blocks', []));
        $act = (string) $request->input('act', 'save');
        $blocks = JnetUnitWorkspaceService::applyBlockAction($blocks !== [] ? $blocks : [JnetUnitWorkspaceService::newBlock('p')], $act === 'save' ? '' : $act);
        $title = JnetUnitWorkspaceService::clean((string) $request->input('title', ''), JnetUnitWorkspaceService::TITLE_MAX, true);
        $saved = $repo->savePage($g['tenant'], $g['unit'], $pageId, $g['user'], $title, JnetUnitWorkspaceService::blocksToJson($blocks), (string) $request->input('version', ''));
        if (!$saved) {
            return $this->done($edit, 'La page a été modifiée par quelqu’un d’autre depuis son ouverture : rechargez-la pour reprendre sa dernière version, puis refaites votre modification.', null);
        }
        if ($act !== 'save') {
            // Geste de l'éditeur : retour sur le formulaire, au même endroit.
            return Response::redirect($edit . '#blocks');
        }

        return $this->done($g['base'] . '?vue=page&p=' . $pageId . '&direct=1', null, 'Page enregistrée.');
    }

    public function deletePage(Request $request, array $params = []): Response
    {
        $g = $this->guard($request, $params, 'pages', true);
        if ($g instanceof Response) {
            return $g;
        }
        $pageId = (int) ($params['pid'] ?? 0);
        $page = $this->workspace->repo()->page($g['tenant'], $g['unit'], $pageId);
        $list = $g['base'] . '?vue=pages&direct=1';
        if ($page === null) {
            return $this->done($list, 'Page introuvable.', null);
        }
        if (!$g['chief'] && (int) $page['created_by'] !== $g['user']) {
            return $this->done($g['base'] . '?vue=page&p=' . $pageId . '&direct=1', 'Seuls l’auteur de la page et le chef de l’espace la suppriment.', null);
        }
        $this->workspace->repo()->deletePage($g['tenant'], $g['unit'], $pageId);

        return $this->done($list, null, 'Page supprimée.');
    }

    public function movePage(Request $request, array $params = []): Response
    {
        $g = $this->guard($request, $params, 'pages', true);
        if ($g instanceof Response) {
            return $g;
        }
        $this->workspace->repo()->movePage($g['tenant'], $g['unit'], (int) ($params['pid'] ?? 0), (string) $request->input('dir', '') === 'up' ? -1 : 1);

        return Response::redirect($g['base'] . '?vue=pages&direct=1');
    }

    /** Case d'un bloc « tâche » cochée ou décochée, sans passer par l'éditeur. */
    public function togglePageTodo(Request $request, array $params = []): Response
    {
        $g = $this->guard($request, $params, 'pages', true);
        if ($g instanceof Response) {
            return $g;
        }
        $pageId = (int) ($params['pid'] ?? 0);
        $page = $this->workspace->repo()->page($g['tenant'], $g['unit'], $pageId);
        $back = $g['base'] . '?vue=page&p=' . $pageId . '&direct=1';
        if ($page === null) {
            return $this->done($g['base'] . '?vue=pages&direct=1', 'Page introuvable.', null);
        }
        $blocks = JnetUnitWorkspaceService::blocksFromJson((string) $page['blocks']);
        $blockId = (string) $request->input('block', '');
        foreach ($blocks as &$b) {
            if ($b['id'] === $blockId && $b['t'] === 'todo') {
                $b['done'] = empty($b['done']);
            }
        }
        unset($b);
        $this->workspace->repo()->touchBlocks($g['tenant'], $g['unit'], $pageId, $g['user'], JnetUnitWorkspaceService::blocksToJson($blocks));

        return Response::redirect($back . '#b-' . rawurlencode($blockId));
    }

    public function vote(Request $request, array $params = []): Response
    {
        $g = $this->guard($request, $params, 'pages', true);
        if ($g instanceof Response) {
            return $g;
        }
        $pageId = (int) ($params['pid'] ?? 0);
        $page = $this->workspace->repo()->page($g['tenant'], $g['unit'], $pageId);
        $back = $g['base'] . '?vue=page&p=' . $pageId . '&direct=1';
        if ($page === null) {
            return $this->done($g['base'] . '?vue=pages&direct=1', 'Page introuvable.', null);
        }
        $blockId = (string) $request->input('block', '');
        $option = (int) $request->input('option', -1);
        foreach (JnetUnitWorkspaceService::blocksFromJson((string) $page['blocks']) as $b) {
            if ($b['id'] === $blockId && $b['t'] === 'poll' && $option >= 0 && $option < count(array_filter($b['opts'], static fn (string $o): bool => $o !== ''))) {
                $this->workspace->repo()->castVote($pageId, $blockId, $g['user'], $option);
                break;
            }
        }

        return Response::redirect($back . '#b-' . rawurlencode($blockId));
    }

    public function react(Request $request, array $params = []): Response
    {
        $g = $this->guard($request, $params, 'pages', true);
        if ($g instanceof Response) {
            return $g;
        }
        $pageId = (int) ($params['pid'] ?? 0);
        $kind = (string) $request->input('kind', '');
        $back = $g['base'] . '?vue=page&p=' . $pageId . '&direct=1#reactions';
        if (isset(JnetUnitWorkspaceService::REACTIONS[$kind]) && $this->workspace->repo()->page($g['tenant'], $g['unit'], $pageId) !== null) {
            $this->workspace->repo()->toggleReaction($pageId, $g['user'], $kind);
        }

        return Response::redirect($back);
    }

    public function comment(Request $request, array $params = []): Response
    {
        $g = $this->guard($request, $params, 'pages', true);
        if ($g instanceof Response) {
            return $g;
        }
        $pageId = (int) ($params['pid'] ?? 0);
        $back = $g['base'] . '?vue=page&p=' . $pageId . '&direct=1#comments';
        $body = JnetUnitWorkspaceService::clean((string) $request->input('body', ''), JnetUnitWorkspaceService::COMMENT_MAX, false);
        if ($this->workspace->repo()->page($g['tenant'], $g['unit'], $pageId) === null) {
            return $this->done($g['base'] . '?vue=pages&direct=1', 'Page introuvable.', null);
        }
        if ($body === '') {
            return $this->done($back, 'Écrivez un commentaire.', null);
        }
        $this->workspace->repo()->addComment($pageId, $g['user'], $body);

        return Response::redirect($back);
    }

    // ——— Présence au prochain rendez-vous, relais ———

    /** Réponse de présence au prochain événement de la communauté : le service et les contrôles de la page Événements. */
    public function presence(Request $request, array $params = []): Response
    {
        $g = $this->guard($request, $params, '', true);
        if ($g instanceof Response) {
            return $g;
        }
        $status = (string) $request->input('status', '');
        $eventId = (int) $request->input('event_id', 0);
        $back = $g['base'] . '?direct=1#rendez-vous';
        if (!in_array($status, ['yes', 'maybe', 'no'], true)) {
            return $this->done($back, 'Réponse inconnue.', null);
        }
        try {
            $gate = \App\Core\Container::get(\App\Services\Platform\FeatureGateService::class);
            if (!$gate->allowsLimitedFeatureModule($g['tenant'], 'events')) {
                return $this->done($back, 'Le module Événements n’est pas disponible pour cette communauté.', null);
            }
            if ($eventId < 1 || !(new CommunityEventRepository())->belongsToTenant($eventId, $g['tenant'])) {
                return $this->done($back, 'Événement introuvable.', null);
            }
            $result = \App\Core\Container::get(\App\Services\Attendance\CommunityEventAttendanceService::class)
                ->setRsvpWithNotifications($eventId, $g['user'], $g['tenant'], $status);
        } catch (\Throwable) {
            return $this->done($back, 'La réponse n’a pas pu être enregistrée.', null);
        }
        if (!($result['ok'] ?? false)) {
            return $this->done($back, (string) ($result['error'] ?? 'Impossible d’enregistrer.'), null);
        }

        return $this->done($back, null, 'Votre réponse est enregistrée.');
    }

    public function relay(Request $request, array $params = []): Response
    {
        $g = $this->guard($request, $params, '', true);
        if ($g instanceof Response) {
            return $g;
        }
        $error = $this->spaces->relay(
            $g['tenant'],
            $g['user'],
            $g['unit'],
            (int) ($params['eid'] ?? 0),
            array_map('intval', (array) $request->input('subunits', [])),
            JnetUnitWorkspaceService::clean((string) $request->input('note', ''), 400, false)
        );

        return $this->done($g['base'] . '?vue=echanges&direct=1', $error, 'Ordre relayé aux sous-unités choisies.');
    }

    // ——— Outils ———

    /**
     * Contrôles communs : connexion, jeton CSRF, espace visible du lecteur, module non masqué, droit d'écrire.
     *
     * @return array{tenant:int,user:int,unit:int,tree:JnetSpaceTree,base:string,back:string,chief:bool}|Response
     */
    private function guard(Request $request, array $params, string $module, bool $needsWrite): array|Response
    {
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId <= 0 || $userId <= 0) {
            return Response::redirect(url('login'));
        }
        $user = $this->authService->user();
        if ($user) {
            $this->rbacService->setPermissionsForGateFromUserRow($user, $this->userRepository);
        }
        if (PortalAccessChoice::isNoOrganizationContext()) {
            return Response::redirect(url('dashboard'));
        }
        $unitId = (int) ($params['id'] ?? 0);
        $tree = $this->spaces->tree($tenantId, $userId);
        if ($unitId <= 0 || !$tree->has($unitId)) {
            Session::flash('error', 'Cet espace n’existe pas ou ne vous est pas accessible.');

            return Response::redirect(url('jnet'));
        }
        $base = url('jnet/u/' . $unitId);
        $back = $base . '?direct=1';
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Votre session a expiré, rien n’a été enregistré.');

            return Response::redirect($back);
        }
        if ($module !== '' && in_array($module, (array) ($this->spaceSettings->find($tenantId, $unitId)['hidden_modules'] ?? []), true)) {
            Session::flash('error', 'Cette rubrique est masquée dans cet espace.');

            return Response::redirect($back);
        }
        if ($needsWrite && !$this->spaces->canPost($tenantId, $userId, $unitId)) {
            Session::flash('error', 'Vous consultez cet espace sans en être membre : l’écriture est réservée à l’unité et à son encadrement.');

            return Response::redirect($back);
        }

        return [
            'tenant' => $tenantId,
            'user' => $userId,
            'unit' => $unitId,
            'tree' => $tree,
            'base' => $base,
            'back' => $back,
            'chief' => $this->isChief($tenantId, $userId, $tree->chain($unitId)),
        ];
    }

    /** @param list<int> $chain */
    private function isChief(int $tenantId, int $userId, array $chain): bool
    {
        if ($this->spaces->isOrgWideWriter() || \App\Core\Gate::getInstance()->allows('organization.orbat.manage')) {
            return true;
        }

        return $this->spaceSettings->commandsWithin($tenantId, $userId, $chain);
    }

    /** @return list<int> */
    private function memberIds(JnetSpaceTree $tree, int $unitId): array
    {
        return array_values(array_map('intval', (array) (($tree->node($unitId) ?? [])['members'] ?? [])));
    }

    private function done(string $to, ?string $error, ?string $ok): Response
    {
        if ($error !== null) {
            Session::flash('error', $error);
        } elseif ($ok !== null) {
            Session::flash('success', $ok);
        }

        return Response::redirect($to);
    }
}
