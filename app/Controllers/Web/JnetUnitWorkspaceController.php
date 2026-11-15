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
use App\Services\Jnet\JnetLinkAccess;
use App\Services\Jnet\JnetLinkService;
use App\Services\Jnet\JnetSpaceService;
use App\Services\Jnet\JnetUnitSpaceSettings;
use App\Services\Jnet\JnetUnitWorkspaceService;
use App\Services\Rbac\RbacService;
use App\Support\JnetSpaceTree;
use App\Support\PortalAccessChoice;

/**
 * Écritures des outils de l'espace d'une unité JNET : tâches, pages, réactions, commentaires, sondages, réponse de
 * présence au prochain rendez-vous, relais d'un ordre aux sous-unités, partage vers une autre unité, relance des sans-réponse,
 * ordre des pages. Toutes les routes sont en POST, avec jeton CSRF ; chaque contrôle d'accès est refait ici, côté serveur :
 * l'espace doit être visible du lecteur, et il doit pouvoir y écrire (JnetSpaceService::rights : règle de l'ORBAT plus
 * réglages de l'espace). Supprimer ce qu'un autre a écrit demande d'être chef de l'espace (même droit que « Personnaliser »)
 * ou « chef de module » désigné par lui. Les messages sont affichés en ligne dans la page (flash) ; une action envoyée sans
 * rechargement (en-tête Accept: application/json) reçoit un JSON {ok, message} que la page affiche en toast discret.
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
        private ?JnetLinkService $links = null,
    ) {
        $this->authService ??= \App\Core\Container::get(AuthService::class);
        $this->rbacService ??= \App\Core\Container::get(RbacService::class);
        $this->userRepository ??= \App\Core\Container::get(UserRepository::class);
        $this->spaces ??= new JnetSpaceService();
        $this->spaceSettings ??= new JnetUnitSpaceSettings();
        $this->workspace ??= new JnetUnitWorkspaceService();
        $this->links ??= new JnetLinkService();
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

        return $this->done($request, $g['back'] . '#taches', $error, null);
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
            return $this->done($request, $g['back'], 'Tâche introuvable.', null);
        }
        $act = (string) $request->input('act', '');
        if ($act === 'status') {
            $status = (string) $request->input('status', '');
            if (!isset(JnetUnitWorkspaceService::TASK_LABELS[$status])) {
                return $this->done($request, $g['back'], 'État inconnu.', null);
            }
            $repo->setTaskStatus($g['tenant'], $g['unit'], (int) $task['id'], $status);
        } elseif ($act === 'assign') {
            $to = (int) $request->input('assignee', 0);
            $repo->setTaskAssignee($g['tenant'], $g['unit'], (int) $task['id'], $to > 0 && in_array($to, $this->memberIds($g['tree'], $g['unit']), true) ? $to : null);
        } elseif ($act === 'delete') {
            if (!JnetUnitWorkspaceService::canEditTask($task, $g['user'], $g['chief'])) {
                return $this->done($request, $g['back'], 'Seuls l’auteur, le responsable ou le chef de l’espace retirent cette tâche.', null);
            }
            $repo->deleteTask($g['tenant'], $g['unit'], (int) $task['id']);
        }

        return $this->done($request, $g['back'] . '#taches', null, null);
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
            return $this->done($request, $g['base'] . '?vue=pages&direct=1', 'Page introuvable.', null);
        }
        $blocks = $this->withoutUnseenMentions($g, JnetUnitWorkspaceService::sanitizeBlocks($request->input('blocks', [])));
        $act = (string) $request->input('act', 'save');
        $blocks = JnetUnitWorkspaceService::applyBlockAction($blocks !== [] ? $blocks : [JnetUnitWorkspaceService::newBlock('p')], $act === 'save' ? '' : $act);
        $title = JnetUnitWorkspaceService::clean((string) $request->input('title', ''), JnetUnitWorkspaceService::TITLE_MAX, true);
        $saved = $repo->savePage($g['tenant'], $g['unit'], $pageId, $g['user'], $title, JnetUnitWorkspaceService::blocksToJson($blocks), (string) $request->input('version', ''));
        if (!$saved) {
            return $this->done($request, $edit, 'La page a été modifiée par quelqu’un d’autre depuis son ouverture : rechargez-la pour reprendre sa dernière version, puis refaites votre modification.', null, ['conflict' => true]);
        }
        if ($act !== 'save') {
            // Geste de l'éditeur : retour sur le formulaire, au même endroit.
            return Response::redirect($edit . '#blocks');
        }

        // Enregistrement automatique de l'éditeur (réponse JSON) : le nouvel horodatage devient la version à renvoyer.
        $fresh = $this->wantsJson($request) ? $repo->page($g['tenant'], $g['unit'], $pageId) : null;

        return $this->done($request, $g['base'] . '?vue=page&p=' . $pageId . '&direct=1', null, 'Page enregistrée.', ['version' => (string) ($fresh['updated_at'] ?? '')]);
    }

    /**
     * Citations (« [[type:id|libellé]] ») des blocs : celles que l'auteur ne peut pas voir redeviennent du texte simple. On ne
     * grave jamais dans une page un lien que son auteur n'aurait pas pu poser ; chaque lecteur est de toute façon contrôlé à
     * l'affichage.
     *
     * @param array{tenant:int,user:int} $g
     * @param list<array<string, mixed>> $blocks
     * @return list<array<string, mixed>>
     */
    private function withoutUnseenMentions(array $g, array $blocks): array
    {
        $viewer = null;
        foreach ($blocks as &$b) {
            if (isset($b['text']) && is_string($b['text']) && str_contains($b['text'], '[[')) {
                $viewer ??= JnetLinkAccess::forViewer($g['tenant'], $g['user'], $this->spaces);
                $b['text'] = $this->links->sanitizeMentions($viewer, $b['text']);
            }
        }
        unset($b);

        return $blocks;
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
            return $this->done($request, $list, 'Page introuvable.', null);
        }
        if (!$g['chief'] && (int) $page['created_by'] !== $g['user']) {
            return $this->done($request, $g['base'] . '?vue=page&p=' . $pageId . '&direct=1', 'Seuls l’auteur de la page et le chef de l’espace la suppriment.', null);
        }
        $this->workspace->repo()->deletePage($g['tenant'], $g['unit'], $pageId);

        return $this->done($request, $list, null, 'Page supprimée.');
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
            return $this->done($request, $g['base'] . '?vue=pages&direct=1', 'Page introuvable.', null);
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

        return $this->done($request, $back . '#b-' . rawurlencode($blockId), null, null);
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
            return $this->done($request, $g['base'] . '?vue=pages&direct=1', 'Page introuvable.', null);
        }
        $blockId = (string) $request->input('block', '');
        $option = (int) $request->input('option', -1);
        foreach (JnetUnitWorkspaceService::blocksFromJson((string) $page['blocks']) as $b) {
            if ($b['id'] === $blockId && $b['t'] === 'poll' && $option >= 0 && $option < count(array_filter($b['opts'], static fn (string $o): bool => $o !== ''))) {
                $this->workspace->repo()->castVote($pageId, $blockId, $g['user'], $option);
                break;
            }
        }

        return $this->done($request, $back . '#b-' . rawurlencode($blockId), null, null);
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

        return $this->done($request, $back, null, null);
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
            return $this->done($request, $g['base'] . '?vue=pages&direct=1', 'Page introuvable.', null);
        }
        if ($body === '') {
            return $this->done($request, $back, 'Écrivez un commentaire.', null);
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
            return $this->done($request, $back, 'Réponse inconnue.', null);
        }
        try {
            $gate = \App\Core\Container::get(\App\Services\Platform\FeatureGateService::class);
            if (!$gate->allowsLimitedFeatureModule($g['tenant'], 'events')) {
                return $this->done($request, $back, 'Le module Événements n’est pas disponible pour cette communauté.', null);
            }
            if ($eventId < 1 || !(new CommunityEventRepository())->belongsToTenant($eventId, $g['tenant'])) {
                return $this->done($request, $back, 'Événement introuvable.', null);
            }
            $result = \App\Core\Container::get(\App\Services\Attendance\CommunityEventAttendanceService::class)
                ->setRsvpWithNotifications($eventId, $g['user'], $g['tenant'], $status);
        } catch (\Throwable) {
            return $this->done($request, $back, 'La réponse n’a pas pu être enregistrée.', null);
        }
        if (!($result['ok'] ?? false)) {
            return $this->done($request, $back, (string) ($result['error'] ?? 'Impossible d’enregistrer.'), null);
        }

        return $this->done($request, $back, null, 'Votre réponse est enregistrée.');
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

        return $this->done($request, $g['base'] . '?vue=echanges&direct=1', $error, 'Ordre relayé aux sous-unités choisies.');
    }

    /** Ordre des pages après un glisser-déposer (le même effet que les boutons « monter » et « descendre »). */
    public function reorderPages(Request $request, array $params = []): Response
    {
        $g = $this->guard($request, $params, 'pages', true);
        if ($g instanceof Response) {
            return $g;
        }
        $this->workspace->repo()->setPageOrder($g['tenant'], $g['unit'], array_map('intval', (array) $request->input('ids', [])));

        return $this->done($request, $g['base'] . '?vue=pages&direct=1', null, 'Ordre des pages enregistré.');
    }

    // ——— Partager, relancer ———

    /**
     * « Partager » : un échange, une page ou un fichier de cet espace part vers une autre unité où le lecteur peut publier,
     * sous la forme d'une information (jamais d'un ordre) qui renvoie à l'élément. Mêmes contrôles que la publication
     * d'un échange (JnetSpaceService::post) ; l'élément doit déjà être visible du lecteur.
     */
    public function share(Request $request, array $params = []): Response
    {
        $g = $this->guard($request, $params, '', false);
        if ($g instanceof Response) {
            return $g;
        }
        $kind = (string) $request->input('kind', '');
        $itemId = (int) $request->input('item', 0);
        $target = (int) $request->input('target', 0);
        $back = [
            'exchange' => $g['base'] . '?vue=echanges&direct=1',
            'page' => $g['base'] . '?vue=page&p=' . $itemId . '&direct=1',
            'file' => $g['base'] . '?vue=documents&direct=1',
        ][$kind] ?? $g['back'];
        $offered = $this->spaces->shareTargets($g['tenant'], $g['user'], $g['unit']);
        $allowed = array_column($offered, 'label', 'id');
        // Unités proposées par une liaison active : l'envoi est une information au nom de CET espace, jamais un ordre.
        $viaLink = array_column($offered, 'via', 'id');
        if (!isset($allowed[$target])) {
            return $this->done($request, $back, 'Vous ne pouvez pas partager vers cette unité.', null);
        }
        $title = '';
        $body = '';
        $link = '';
        $type = 'info';
        if ($kind === 'exchange') {
            $row = $this->spaces->visibleExchange($g['tenant'], $g['user'], $itemId);
            if ($row === null) {
                return $this->done($request, $back, 'Échange introuvable.', null);
            }
            $title = (string) $row['title'];
            $body = trim((string) ($row['body'] ?? ''));
            $link = trim((string) ($row['link_url'] ?? ''));
            $link = $link !== '' ? '/' . ltrim($link, '/') : '';
        } elseif ($kind === 'page') {
            $page = in_array('pages', (array) ($this->spaceSettings->find($g['tenant'], $g['unit'])['hidden_modules'] ?? []), true)
                ? null
                : $this->workspace->repo()->page($g['tenant'], $g['unit'], $itemId);
            if ($page === null) {
                return $this->done($request, $back, 'Page introuvable.', null);
            }
            $title = (string) ($page['title'] !== '' ? $page['title'] : 'Page sans titre');
            $link = url('jnet/u/' . $g['unit'] . '?vue=page&p=' . $itemId . '&direct=1');
        } elseif ($kind === 'file') {
            $files = (new \App\Services\Jnet\JnetUnitFiles())->forUnit($g['tenant'], $g['user'], $g['unit']);
            $file = null;
            foreach (array_merge((array) $files['photos'], (array) $files['documents']) as $f) {
                if ((int) $f['id'] === $itemId) {
                    $file = $f;
                    break;
                }
            }
            if ($file === null) {
                return $this->done($request, $back, 'Fichier introuvable.', null);
            }
            $title = (string) $file['name'];
            $link = (string) $file['href'];
            $type = 'document';
        } else {
            return $this->done($request, $back, 'Rien à partager.', null);
        }
        $note = JnetUnitWorkspaceService::clean((string) $request->input('note', ''), 400, false);
        $origin = (string) ($g['tree']->node($g['unit'])['label'] ?? 'une unité');
        $intro = 'Partagé depuis ' . $origin . ($kind === 'file' ? ' : le fichier s’ouvre avec vos droits dans le partage de fichiers.' : '.');
        $text = $intro . ($note !== '' ? "\n\nMot de l’expéditeur : " . $note : '') . ($body !== '' ? "\n\n" . $body : '');
        $error = ($viaLink[$target] ?? '') === 'liaison'
            ? $this->spaces->shareViaLink($g['tenant'], $g['user'], $g['unit'], $target, $title, mb_substr($text, 0, 4000), $link)
            : $this->spaces->post($g['tenant'], $g['user'], $target, $type, $title, mb_substr($text, 0, 4000), $link, [$target]);

        return $this->done($request, $back, $error, 'Partagé avec ' . (string) $allowed[$target] . '.');
    }

    /**
     * « Relancer les sans-réponse » : un rappel part, par la messagerie JNET et ses notifications, aux seuls membres de
     * l'unité sans réponse au prochain rendez-vous. Réservé à ceux qui peuvent publier dans l'espace ; la liste des
     * destinataires est recalculée ici, jamais lue dans le formulaire.
     */
    public function remind(Request $request, array $params = []): Response
    {
        $g = $this->guard($request, $params, '', true);
        if ($g instanceof Response) {
            return $g;
        }
        $back = $g['base'] . '?vue=effectif&direct=1';
        if (empty($g['rights']['publish'])) {
            return $this->done($request, $back, 'Seuls ceux qui peuvent publier dans l’espace relancent les membres.', null);
        }
        $members = $this->memberIds($g['tree'], $g['unit']);
        $event = $this->workspace->nextEvent($g['tenant'], $g['user'], $members);
        $result = $this->workspace->remind(
            $g['tenant'],
            $g['user'],
            (string) ($g['tree']->node($g['unit'])['label'] ?? 'Unité'),
            $g['base'] . '?direct=1',
            $event,
            $members
        );
        $n = $result['count'];

        return $this->done($request, $back, $result['error'], 'Rappel envoyé à ' . $n . ' membre' . ($n > 1 ? 's' : '') . ' sans réponse.', ['count' => $n]);
    }

    // ——— Outils ———

    /**
     * Contrôles communs : connexion, jeton CSRF, espace visible du lecteur, module non masqué, droit d'écrire.
     *
     * 'chief' = peut modérer les outils (chef de l'espace, ou gestionnaire « chef de module » désigné par le chef).
     *
     * @return array{tenant:int,user:int,unit:int,tree:JnetSpaceTree,base:string,back:string,chief:bool,rights:array{read:bool,write:bool,lead:bool,publish:bool,chief:bool}}|Response
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
            return $this->deny($request, url('jnet'), 'Cet espace n’existe pas ou ne vous est pas accessible.');
        }
        $base = url('jnet/u/' . $unitId);
        $back = $base . '?direct=1';
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            return $this->deny($request, $back, 'Votre session a expiré, rien n’a été enregistré.');
        }
        $rights = $this->spaces->rights($tenantId, $userId, $unitId);
        if (!$rights['read']) {
            return $this->deny($request, url('jnet'), 'Cet espace est réservé aux unités que son chef a autorisées.');
        }
        if ($module !== '' && in_array($module, (array) ($this->spaceSettings->find($tenantId, $unitId)['hidden_modules'] ?? []), true)) {
            return $this->deny($request, $back, 'Cette rubrique est masquée dans cet espace.');
        }
        if ($needsWrite && !$rights['write']) {
            return $this->deny($request, $back, 'Vous consultez cet espace sans en avoir l’écriture : elle est réservée à l’unité, à son encadrement et aux unités que son chef a autorisées.');
        }

        return [
            'tenant' => $tenantId,
            'user' => $userId,
            'unit' => $unitId,
            'tree' => $tree,
            'base' => $base,
            'back' => $back,
            'chief' => $rights['chief'] || $rights['lead'],
            'rights' => $rights,
        ];
    }

    private function wantsJson(Request $request): bool
    {
        return str_contains(strtolower((string) $request->header('Accept')), 'application/json');
    }

    /** Refus : message en ligne après redirection, ou JSON pour une action envoyée sans rechargement. */
    private function deny(Request $request, string $to, string $message): Response
    {
        if ($this->wantsJson($request)) {
            return Response::json(['ok' => false, 'message' => $message], 403);
        }
        Session::flash('error', $message);

        return Response::redirect($to);
    }

    /** @return list<int> */
    private function memberIds(JnetSpaceTree $tree, int $unitId): array
    {
        return array_values(array_map('intval', (array) (($tree->node($unitId) ?? [])['members'] ?? [])));
    }

    /**
     * Fin d'une écriture : redirection avec message en ligne, ou JSON ({ok, message}) quand la page l'a envoyée sans
     * rechargement (glisser-déposer, partage, relance), pour que le script affiche une confirmation discrète.
     *
     * @param array<string, mixed> $extra
     */
    private function done(Request $request, string $to, ?string $error, ?string $ok, array $extra = []): Response
    {
        if ($this->wantsJson($request)) {
            return Response::json(['ok' => $error === null, 'message' => (string) ($error ?? $ok ?? '')] + $extra, $error === null ? 200 : 422);
        }
        if ($error !== null) {
            Session::flash('error', $error);
        } elseif ($ok !== null) {
            Session::flash('success', $ok);
        }

        return Response::redirect($to);
    }
}
