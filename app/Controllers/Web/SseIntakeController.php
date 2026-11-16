<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Controllers\Admin\AdminIntelIntakeController;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\SseCaseRepository;
use App\Services\Intel\IntelIntakeDesk;
use App\Services\Intel\IntelIntakeService;
use App\Services\Intel\IntelRedaction;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseAccessPolicy;
use App\Services\Sse\SseClearanceService;
use App\Support\SseAccessCatalog;

/**
 * Remontées vues du bureau SSE : la même remontée que dans le back-office, traitée par le même service
 * (IntelIntakeDesk), avec l'habillage du portail.
 *   GET  /atak/sse/remontees                               file des remontées (comptes rendus et fiches)
 *   GET  /atak/sse/remontees/{source}/{id}                 un compte rendu et son suivi ; une fiche renvoie à sa page
 *   GET  /atak/sse/remontees/{source}/{id}/suggestions     listes des fenêtres (JSON)
 *   POST /atak/sse/remontees/{source}/{id}/{action}        actions de traitement
 *
 * Droits du portail (SseAccessPolicy) :
 *   - file et suivi : gérer les fiches de renseignement (section « terrain », niveau gérer), ou l'accès au back-office ;
 *   - traiter (prendre en compte, commenter, état, transmettre, doublon, attribuer, type, thèmes, Discord, retour sur une
 *     proposition de Cerbère : ignorer, mauvaise détection) : le même droit ;
 *   - convertir : en plus, écrire dans les dossiers ; relier : écrire dans les fiches (vérifié par le service de liens) ;
 *   - modifier les données, caviarder, flouter, supprimer, lire l'identifiant Steam : jamais d'ici, seulement depuis le
 *     back-office (lien proposé à qui y a accès).
 */
final class SseIntakeController
{
    /** Actions offertes depuis le portail. */
    private const ACTIONS = ['take', 'comment', 'state', 'assign', 'type', 'themes', 'cerbere', 'convert', 'link', 'discord'];

    public function __construct(
        private ?IntelIntakeDesk $desk = null,
        private ?SseAccessCodeService $access = null,
        private ?SseClearanceService $clearance = null,
    ) {
        $this->access ??= new SseAccessCodeService();
    }

    private function desk(): IntelIntakeDesk
    {
        return $this->desk ??= new IntelIntakeDesk();
    }

    private function tenantId(): int
    {
        $tenantId = $this->access->tenantId();

        return $tenantId > 0 ? $tenantId : (int) Session::get('tenant_id');
    }

    /**
     * Droits de la session sur les remontées, depuis le portail.
     *
     * @return array<string, bool>
     */
    public static function sessionRights(bool $guest, int $tenantId): array
    {
        if ($guest || (int) Session::get('user_id') < 1) {
            return IntelIntakeDesk::rights([]);
        }
        $ctx = ['guest' => false, 'tenant_id' => $tenantId];
        $treat = SseAccessPolicy::sessionAllows('terrain', SseAccessCatalog::LEVEL_MANAGE, $ctx);

        return IntelIntakeDesk::rights([
            'treat' => $treat,
            'discord' => $treat,
            'convert' => $treat && SseAccessPolicy::sessionAllows('dossiers', SseAccessCatalog::LEVEL_WRITE, $ctx),
            'link' => $treat && SseAccessPolicy::sessionAllows('terrain', SseAccessCatalog::LEVEL_WRITE, $ctx),
        ]);
    }

    /** @return array<string, bool> */
    private function rights(): array
    {
        return self::sessionRights($this->access->isGuest(), $this->tenantId());
    }

    private function viewerLevel(): string
    {
        try {
            $this->clearance ??= new SseClearanceService();

            return $this->clearance->maxLevel();
        } catch (\Throwable) {
            return SseCaseRepository::CLASS_INTERNAL;
        }
    }

    /** @return array{id: int, label: string} */
    private function actor(): array
    {
        $label = '';
        foreach (['callsign', 'display_name', 'username'] as $k) {
            $label = trim((string) (Session::get($k) ?? ''));
            if ($label !== '') {
                break;
            }
        }

        return ['id' => (int) Session::get('user_id'), 'label' => $label !== '' ? $label : 'Bureau SSE'];
    }

    /** @param array<string, mixed> $data */
    private function view(string $view, array $data): Response
    {
        $data['isGuest'] = $this->access->isGuest();
        $data['clearanceUntil'] = (int) Session::get(SseAccessCodeService::SESSION_UNTIL, 0);
        $data['guestLabel'] = (string) Session::get('sse_guest_label', '');
        $data['sseTheme'] = function_exists('sse_ui_theme') ? sse_ui_theme() : 'dark';
        $data['canGrant'] = !$this->access->isGuest() && function_exists('can') && (can('atak.sse.grant') || can('admin.access'));

        return Response::view($view, $data);
    }

    private function denied(): Response
    {
        Session::flash('error', 'La file des remontées est réservée à qui gère les fiches de renseignement.');

        return Response::redirect(url('atak/sse/fiches'));
    }

    public function index(Request $request, array $params = []): Response
    {
        $rights = $this->rights();
        if (!$rights['treat']) {
            return $this->denied();
        }
        $tenantId = $this->tenantId();
        $intake = $this->desk()->intake();
        $ready = $intake->schemaReady();
        $tab = $request->queryString('etat', 'open');
        $tab = in_array($tab, ['open', 'closed', 'mine', 'all'], true) || isset(IntelIntakeService::STATES[$tab]) ? $tab : 'open';
        $badge = $request->queryString('pastille');
        $badge = isset(IntelIntakeDesk::BADGE_FILTERS[$badge]) ? $badge : '';
        $sort = $request->queryString('tri', 'recu');
        $sort = isset(IntelIntakeDesk::SORTS[$sort]) ? $sort : 'recu';
        $dir = $request->queryString('ordre', 'desc') === 'asc' ? 'asc' : 'desc';
        $q = mb_substr(trim($request->queryString('q')), 0, 80);
        $me = (int) Session::get('user_id');
        $filters = ['q' => $q, 'state' => $tab === 'mine' ? 'open' : $tab] + ($tab === 'mine' ? ['assignee' => 'me'] : []);
        $items = $ready ? $intake->listItems($tenantId, $filters, $me, 200) : [];
        // Les extraits respectent les caviardages, comme dans le back-office.
        $level = $this->viewerLevel();
        foreach (['cr', 'fiche'] as $src) {
            $red = $items !== [] ? $intake->redactions($tenantId, $src, array_column(array_filter($items, static fn (array $i): bool => $i['source'] === $src), 'id')) : [];
            foreach ($items as &$it) {
                if ($it['source'] === $src && isset($red[$it['id']])) {
                    $it['title'] = IntelRedaction::apply((string) $it['title'], $red[$it['id']], $level, $me);
                }
            }
            unset($it);
        }
        $refined = IntelIntakeDesk::refine($items, $badge, $sort, $dir);

        return $this->view('atak.sse.intake_index', [
            'title' => 'Remontées',
            'activeNav' => 'fiches',
            'intakeReady' => $ready,
            'intakeItems' => $refined['items'],
            'intakeBadgeCounts' => $refined['counts'],
            'intakeCounts' => $ready ? $intake->counts($tenantId, ['q' => $q], $me) : ['open' => 0, 'closed' => 0, 'mine' => 0, 'deleted' => 0, 'states' => []],
            'intakeTab' => $tab,
            'intakeBadge' => $badge,
            'intakeSort' => $sort,
            'intakeDir' => $dir,
            'intakeQuery' => $q,
            'intakeBackOffice' => AdminIntelIntakeController::allowed(),
            'canManage' => true,
            'canWrite' => true,
        ]);
    }

    public function show(Request $request, array $params = []): Response
    {
        $rights = $this->rights();
        if (!$rights['treat']) {
            return $this->denied();
        }
        $source = (string) ($params['source'] ?? '');
        $id = (int) ($params['id'] ?? 0);
        if ($source === 'fiche' && $id > 0) {
            // Une fiche a déjà sa page au bureau : son suivi en est un onglet.
            return Response::redirect(url('atak/sse/fiches/' . $id) . '#suivi');
        }
        $tenantId = $this->tenantId();
        $me = (int) Session::get('user_id');
        $desk = $this->desk()->intake()->schemaReady() ? $this->desk()->view($tenantId, $source, $id, ['id' => $me, 'level' => $this->viewerLevel()], $rights) : null;
        if ($desk === null || !empty($desk['item']['deleted'])) {
            Session::flash('error', 'Remontée introuvable.');

            return Response::redirect(url('atak/sse/remontees'));
        }
        $this->desk()->intake()->recordRead($tenantId, $source, $id, $me, 'web');

        return $this->view('atak.sse.intake_show', [
            'title' => (string) $desk['item']['ref'],
            'activeNav' => 'fiches',
            'intakeDesk' => $desk,
            'intakeMembers' => $this->desk()->intake()->members($tenantId),
            'intakeTypes' => IntelIntakeService::typeOptions($source),
            'intakeBackOffice' => AdminIntelIntakeController::allowed(),
            'canManage' => true,
            'canWrite' => true,
        ]);
    }

    public function suggestions(Request $request, array $params = []): Response
    {
        $rights = $this->rights();
        if (!$rights['treat']) {
            return Response::json(['items' => []], 403);
        }

        return Response::json(['items' => AdminIntelIntakeController::suggest(
            $this->desk(), $this->tenantId(), (string) ($params['source'] ?? ''), (int) ($params['id'] ?? 0),
            $request->queryString('genre'), $request->queryString('q'), $rights['link']
        )]);
    }

    public function action(Request $request, array $params = []): Response
    {
        $source = (string) ($params['source'] ?? '');
        $id = (int) ($params['id'] ?? 0);
        $action = (string) ($params['action'] ?? '');
        $back = $source === 'fiche' ? url('atak/sse/fiches/' . $id) . '#suivi' : url('atak/sse/remontees/' . rawurlencode($source) . '/' . $id);
        $token = $request->input('_csrf_token');
        if (!is_string($token) || $token === '' || !Csrf::validate($token)) {
            Session::flash('error', 'Votre page est restée ouverte trop longtemps ou la session a changé : rechargez la page puis recommencez.');

            return Response::redirect(isset(IntelIntakeService::SOURCES[$source]) && $id > 0 ? $back : url('atak/sse/remontees'));
        }
        if (!in_array($action, self::ACTIONS, true)) {
            Session::flash('error', IntelIntakeDesk::denied('manage'));

            return Response::redirect(isset(IntelIntakeService::SOURCES[$source]) && $id > 0 ? $back : url('atak/sse/remontees'));
        }
        $res = $this->desk()->perform($this->tenantId(), $source, $id, $action, $request->all(), $this->actor(), $this->rights(), $this->viewerLevel());
        Session::flash($res['ok'] ? 'success' : 'error', $res['message']);

        return Response::redirect(isset(IntelIntakeService::SOURCES[$source]) && $id > 0 ? $back : url('atak/sse/remontees'));
    }
}
