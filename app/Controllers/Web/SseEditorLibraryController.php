<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Controllers\Admin\MemberIdentityPapersController;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\SseCaseRepository;
use App\Repositories\SseEditorItemRepository;
use App\Repositories\SseIdCountryRepository;
use App\Repositories\SseIdCoverLinkRepository;
use App\Repositories\UserRepository;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseClearanceService;
use App\Services\Sse\SseEditorAssignmentService;
use App\Services\Sse\SseEditorLibraryService;

/**
 * Bureau SSE › Bibliothèque de l'éditeur : les contenus enregistrés par le mod (ICD-SSE-EDITOR-001). Ils se créent dans
 * le jeu ; une identité, un papier ou un kit peut être attribué ici à un joueur (SseEditorAssignmentService).
 *
 * Mêmes règles que l'API pour une session du portail : compte identifié et habilitation Confidentiel. La communauté est
 * celle de la session, comme dans l'API : c'est dans celle-là que l'habilitation est calculée. Attribuer demande en plus
 * le droit de gérer les papiers des membres, jamais un accès invité.
 */
final class SseEditorLibraryController
{
    private const PER_PAGE = 50;

    public function __construct(
        private ?SseAccessCodeService $access = null,
        private ?SseClearanceService $clearance = null,
        private ?SseEditorItemRepository $items = null,
        private ?\Closure $canManage = null,
        private ?SseEditorAssignmentService $assignment = null,
    ) {
        $this->access ??= new SseAccessCodeService();
        $this->clearance ??= new SseClearanceService($this->access);
    }

    /** La session peut-elle lire la bibliothèque ? Aussi utilisé par le menu du Bureau SSE. */
    public static function allowed(?SseClearanceService $clearance = null): bool
    {
        if ((int) Session::get('user_id') < 1 || (int) Session::get('tenant_id') < 1) {
            return false;
        }
        try {
            return ($clearance ?? new SseClearanceService())->allows(SseCaseRepository::CLASS_CONFIDENTIAL);
        } catch (\Throwable) {
            return false;
        }
    }

    public function index(Request $request, array $params = []): Response
    {
        if (!self::allowed($this->clearance)) {
            Session::flash('error', 'Compte identifié et habilitation Confidentiel requis pour la bibliothèque de l’éditeur.');

            return Response::redirect(url('atak/sse/operations'));
        }
        $type = $request->queryString('type');
        $state = $request->queryString('state');
        $filters = [
            'type' => isset(SseEditorLibraryService::TYPE_LABELS[$type]) ? $type : '',
            'state' => in_array($state, SseEditorLibraryService::STATES, true) ? $state : '',
            'q' => mb_substr(trim($request->queryString('q')), 0, 120),
        ];
        $tenantId = (int) Session::get('tenant_id');
        $rows = [];
        $total = 0;
        $pages = 1;
        $page = 1;
        $unavailable = false;
        try {
            $this->items ??= new SseEditorItemRepository();
            $total = $this->items->searchCount($tenantId, $filters);
            $pages = max(1, (int) ceil($total / self::PER_PAGE));
            $page = max(1, min($pages, (int) $request->queryString('page', '1')));
            $rows = $this->items->search($tenantId, $filters, ($page - 1) * self::PER_PAGE, self::PER_PAGE);
        } catch (\PDOException) {
            // Étape de migration 4070 pas encore passée, ou base indisponible : même lecture que l'API (503).
            $unavailable = true;
        }

        return (new SseIdentityRegistryController($this->access, $this->clearance))->page('atak.sse.editor_library', [
            'title' => 'Bibliothèque de l’éditeur',
            'editorItems' => $this->withAssignments($tenantId, array_map(SseEditorLibraryService::forScreen(...), $rows)),
            'editorCanAssign' => $canAssign = $this->canAssign(),
            'editorMembers' => $canAssign ? self::members($tenantId) : [],
            'editorCountries' => $canAssign ? self::countries($tenantId) : [],
            'editorMaps' => $canAssign ? MemberIdentityPapersController::maps() : [],
            'editorFilters' => $filters,
            'editorTotal' => $total,
            'editorPage' => $page,
            'editorPages' => $pages,
            'editorUnavailable' => $unavailable,
        ]);
    }

    /** POST /atak/sse/editeur/{uid}/attribuer : identité, papier ou kit remis à un membre de la communauté. */
    public function assign(Request $request, array $params = []): Response
    {
        $back = url('atak/sse/editeur');
        if (!$this->canAssign()) {
            Session::flash('error', 'Attribuer un contenu demande l’habilitation Confidentiel et le droit de gérer les papiers des membres.');

            return Response::redirect($back);
        }
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($back);
        }
        $tenantId = (int) Session::get('tenant_id');
        // « Nom (#12) » : la liste propose les membres, seul le numéro compte, et il doit être de cette communauté.
        $picked = preg_match('/#(\d+)\)?\s*$/', $request->inputString('member'), $m) === 1 ? (int) $m[1] : 0;
        $member = $picked > 0 ? (new UserRepository())->findById($picked, $tenantId) : null;
        if ($member === null) {
            Session::flash('error', 'Choisissez un membre de la communauté dans la liste.');

            return Response::redirect($back);
        }
        try {
            $res = ($this->assignment ??= new SseEditorAssignmentService())->assign(
                $tenantId,
                (string) ($params['id'] ?? ''),
                (int) $member['id'],
                $request->inputString('country'),
                (int) $request->input('context_id', 0) ?: null,
                trim($request->inputString('op_key')) ?: null,
                (int) Session::get('user_id')
            );
        } catch (\PDOException $e) {
            error_log('Bibliothèque de l’éditeur : attribution impossible : ' . $e->getMessage());
            Session::flash('error', 'Attribution indisponible pour le moment : l’étape de migration 4110 n’a peut-être pas encore été passée.');

            return Response::redirect($back);
        }
        if (!$res['ok']) {
            Session::flash('error', (string) ($res['error'] ?? 'Attribution impossible.'));

            return Response::redirect($back);
        }
        $steam = preg_replace('/\D/', '', (string) ($member['steam_id'] ?? '')) !== '';
        Session::flash('success', ($res['again'] ?? false ? 'Attribution mise à jour.' : 'Contenu attribué : ' . (int) ($res['documents'] ?? 0) . ' papier(s) émis.')
            . ($steam
                ? ' Le joueur le reçoit au chargement du paquet : début de mission, ou Ma couverture › Demander la mise à jour.'
                : ' Son compte n’est pas lié à Steam : le serveur de jeu ne pourra rien lui remettre tant qu’il ne l’a pas lié.'));

        return Response::redirect($back);
    }

    private function canAssign(): bool
    {
        if (!self::allowed($this->clearance) || $this->access->isGuest()) {
            return false;
        }
        try {
            return $this->canManage !== null ? ($this->canManage)() === true : MemberIdentityPapersController::currentRights($this->clearance)['manage'];
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function withAssignments(int $tenantId, array $items): array
    {
        $byItem = [];
        try {
            $byItem = (new SseIdCoverLinkRepository())->assignmentsForItems($tenantId, array_map(static fn (array $i): int => (int) $i['id'], $items));
        } catch (\PDOException) {
            // Étape de migration 4110 pas encore passée : la bibliothèque reste lisible.
        }
        foreach ($items as $k => $it) {
            $items[$k]['assignments'] = [];
            foreach ($byItem[(int) $it['id']] ?? [] as $a) {
                $items[$k]['assignments'][] = [
                    'name' => (string) ($a['user_name'] ?? ('#' . (int) $a['user_id'])),
                    'steam_linked' => preg_replace('/\D/', '', (string) ($a['steam_id'] ?? '')) !== '',
                    'op_key' => ($a['op_key'] ?? null) !== null ? (string) $a['op_key'] : '',
                    'updated' => substr((string) $a['updated_at'], 0, 16),
                ];
            }
        }

        return $items;
    }

    /** @return list<array{id: int, name: string}> */
    private static function members(int $tenantId): array
    {
        try {
            $out = [];
            foreach ((new UserRepository())->listPersonnelDirectoryRich($tenantId, '', 300) as $u) {
                $out[] = ['id' => (int) $u['id'], 'name' => (string) ($u['display_name'] ?? '')];
            }

            return $out;
        } catch (\Throwable $e) {
            error_log('Bibliothèque de l’éditeur : membres indisponibles : ' . $e->getMessage());

            return [];
        }
    }

    /** @return list<array{code: string, name: string}> */
    private static function countries(int $tenantId): array
    {
        try {
            return array_map(
                static fn (array $c): array => ['code' => (string) $c['code'], 'name' => (string) $c['name']],
                (new SseIdCountryRepository())->listForTenant($tenantId)
            );
        } catch (\Throwable) {
            return [];
        }
    }
}
