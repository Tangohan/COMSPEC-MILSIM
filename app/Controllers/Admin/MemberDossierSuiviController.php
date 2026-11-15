<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\UserRepository;
use App\Services\Effectifs\Dossier\DossierCatalog;
use App\Services\Effectifs\Dossier\DossierHistoryPresenter;
use App\Services\Effectifs\Dossier\LiveDossierPorts;
use App\Services\Effectifs\Dossier\MemberDossierStepActions;
use App\Services\Effectifs\Dossier\MemberDossierTeamBoard;
use App\Services\Effectifs\Dossier\MemberDossierTimeline;
use App\Services\Personnel\MemberNameService;
use App\Support\EffectifsLmsAccess;
use Throwable;

/**
 * Suivi du dossier des membres (Effectifs) : gestes d'un responsable sur une étape, exports CSV, relance groupée et
 * données de l'onglet « Suivi du dossier » de la fiche. Chaque entrée vérifie la session, la communauté, le droit
 * (effectifs.suivi.view pour lire, effectifs.suivi.manage pour modifier), le jeton CSRF et l'appartenance du membre à
 * la communauté ; les étapes sont retrouvées dans le fil de CE membre (MemberDossierStepActions), jamais par un
 * identifiant brut.
 */
final class MemberDossierSuiviController
{
    public const BULK_MAX = 25;

    /** @var list<string> */
    private const ACTIONS = ['complete', 'reopen', 'postpone', 'reassign', 'note', 'skip', 'unskip', 'remind', 'remove', 'add'];

    /** Modifier une étape. */
    public function act(Request $request, array $params = []): Response
    {
        $denied = $this->deny(true);
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $actorId = (int) Session::get('user_id');
        $memberId = (int) ($params['id'] ?? 0);
        $action = (string) ($params['action'] ?? '');
        $back = $memberId > 0 ? effectifs_member_url($memberId, 'suivi') : effectifs_workspace_url();
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($back);
        }
        if (!in_array($action, self::ACTIONS, true) || !$this->memberInTenant($tenantId, $memberId)) {
            Session::flash('error', 'Membre ou geste introuvable.');

            return Response::redirect(effectifs_workspace_url());
        }
        $actions = $this->actions();
        $key = mb_substr((string) $request->input('key', ''), 0, 80);
        $reason = (string) $request->input('reason', '');
        $r = match ($action) {
            'complete' => $actions->complete($tenantId, $actorId, $memberId, $key, $reason),
            'reopen' => $actions->reopen($tenantId, $actorId, $memberId, $key, $reason),
            'postpone' => $actions->postpone($tenantId, $actorId, $memberId, $key, (string) $request->input('due_on', ''), $reason),
            'reassign' => $actions->reassign($tenantId, $actorId, $memberId, $key, (int) $request->input('owner_user_id', 0), $reason),
            'note' => $actions->note($tenantId, $actorId, $memberId, $key, (string) $request->input('note', '')),
            'skip' => $actions->skip($tenantId, $actorId, $memberId, $key, $reason),
            'unskip' => $actions->unskip($tenantId, $actorId, $memberId, $key, $reason),
            'remind' => $actions->remind($tenantId, $actorId, $memberId, $key),
            'remove' => $actions->remove($tenantId, $actorId, $memberId, $key, $reason),
            default => $actions->addCustom($tenantId, $actorId, $memberId, (string) $request->input('track', ''), (string) $request->input('title', ''), (string) $request->input('due_on', ''), (int) $request->input('owner_user_id', 0)),
        };
        Session::flash($r['ok'] ? 'success' : 'error', $r['message']);
        $anchor = $key !== '' && $r['ok'] && $action !== 'remove' ? 'etape-' . trim((string) preg_replace('/[^a-z0-9]+/i', '-', $key), '-') : '';

        return Response::redirect(effectifs_member_url($memberId, 'suivi', $anchor));
    }

    /** Export CSV du fil d'un membre. */
    public function exportMember(Request $request, array $params = []): Response
    {
        $denied = $this->deny(false);
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $memberId = (int) ($params['id'] ?? 0);
        if (!$this->memberInTenant($tenantId, $memberId)) {
            Session::flash('error', 'Membre introuvable.');

            return Response::redirect(effectifs_workspace_url());
        }
        $data = MemberDossierTimeline::make()->forUser($tenantId, $memberId);
        $steps = MemberDossierTimeline::filter($data['steps'], self::track((string) $request->query('parcours', '')), self::status((string) $request->query('statut', '')), mb_substr((string) $request->query('q', ''), 0, 80));

        return $this->csv(MemberDossierTimeline::csv($steps), 'suivi-dossier-' . $memberId . '-' . date('Y-m-d') . '.csv');
    }

    /** Export CSV de la vue équipe, avec les filtres affichés. */
    public function exportTeam(Request $request, array $params = []): Response
    {
        $denied = $this->deny(false);
        if ($denied !== null) {
            return $denied;
        }
        $board = $this->teamBoard((int) Session::get('tenant_id'), $request);

        return $this->csv(MemberDossierTeamBoard::csv($board['rows']), 'suivi-des-dossiers-' . date('Y-m-d') . '.csv');
    }

    /** Relance groupée : la prochaine étape à faire par le membre, pour une sélection plafonnée, sans doublon. */
    public function bulkRemind(Request $request, array $params = []): Response
    {
        $denied = $this->deny(true);
        if ($denied !== null) {
            return $denied;
        }
        $back = effectifs_workspace_url('suivi');
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($back);
        }
        $tenantId = (int) Session::get('tenant_id');
        $raw = $request->input('members', []);
        $ids = array_map('intval', is_array($raw) ? $raw : []);
        if ($ids === []) {
            Session::flash('error', 'Sélectionnez au moins un membre.');

            return Response::redirect($back);
        }
        // Seuls les membres de cette communauté : un identifiant étranger est écarté.
        $ids = array_values(array_filter(array_slice(array_unique($ids), 0, 200), fn (int $id): bool => $this->memberInTenant($tenantId, $id)));
        $r = $this->actions()->remindMany($tenantId, (int) Session::get('user_id'), $ids, self::BULK_MAX);
        $msg = $r['sent'] . ' membre(s) relancé(s)' . ($r['skipped'] > 0 ? ', ' . $r['skipped'] . ' ignoré(s) (rien à relancer ou relancé il y a moins de ' . MemberDossierStepActions::REMIND_COOLDOWN_HOURS . ' h)' : '')
            . ($r['capped'] ? '. Limite de ' . self::BULK_MAX . ' relances par envoi atteinte : relancez le reste dans un second temps.' : '.');
        Session::flash($r['sent'] > 0 ? 'success' : 'error', $msg);

        return Response::redirect($back);
    }

    /**
     * Données de l'onglet de la fiche. Null si le lecteur ne peut pas consulter le suivi.
     *
     * @param list<array{id: int, label: string}> $ownerCandidates responsables proposés (hors lecteur et membre, ajoutés ici)
     * @return array<string, mixed>|null
     */
    public static function tabData(int $tenantId, int $memberId, int $viewerId, string $memberName, Request $request, array $ownerCandidates = []): ?array
    {
        $gate = Gate::getInstance();
        if (!EffectifsLmsAccess::canViewSuivi($gate)) {
            return null;
        }
        $filter = [
            'track' => self::track((string) $request->queryString('parcours', '')),
            'status' => self::status((string) $request->queryString('statut', '')),
            'q' => mb_substr(trim((string) $request->queryString('q', '')), 0, 80),
        ];

        return self::buildTab(MemberDossierTimeline::make(), $tenantId, $memberId, $viewerId, $memberName, $filter, EffectifsLmsAccess::canManageSuivi($gate), $ownerCandidates);
    }

    /**
     * Construit les données de l'onglet à partir d'un fil déjà branché (testable sans session ni droits).
     *
     * @param array{track: string, status: string, q: string} $filter
     * @param list<array{id: int, label: string}> $ownerCandidates
     * @return array<string, mixed>
     */
    public static function buildTab(MemberDossierTimeline $timeline, int $tenantId, int $memberId, int $viewerId, string $memberName, array $filter, bool $canManage, array $ownerCandidates = [], ?string $today = null, ?\Closure $names = null): array
    {
        $data = $timeline->forUser($tenantId, $memberId, $today);
        $tracks = $data['tracks'];
        if ($filter['track'] !== '' || $filter['status'] !== '' || $filter['q'] !== '') {
            foreach ($tracks as $k => $t) {
                $kept = MemberDossierTimeline::filter($t['steps'], $filter['track'], $filter['status'], $filter['q']);
                if ($kept === []) {
                    unset($tracks[$k]);
                } else {
                    $tracks[$k]['steps'] = $kept;
                }
            }
        }
        $store = $timeline->store();
        $events = DossierHistoryPresenter::present($tenantId, $store->events($tenantId, $memberId, 200), $names);
        $owners = [];
        foreach (array_merge([['id' => $viewerId, 'label' => 'Moi'], ['id' => $memberId, 'label' => 'Le membre (' . $memberName . ')']], $ownerCandidates) as $o) {
            if ((int) $o['id'] > 0) {
                $owners[(int) $o['id']] = ['id' => (int) $o['id'], 'label' => (string) $o['label']];
            }
        }

        return [
            'summary' => $data['summary'],
            'steps' => $data['steps'],
            'tracks' => $tracks,
            'degraded' => $data['degraded'],
            'filter' => $filter,
            'events' => $events,
            'eventTotal' => $store->eventCount($tenantId, $memberId),
            'canManage' => $canManage,
            'isSelf' => $viewerId === $memberId,
            'ownerOptions' => array_values($owners),
            'today' => $today ?? date('Y-m-d'),
        ];
    }

    /**
     * Tableau de la vue équipe : membres actifs, filtrés et triés.
     *
     * @return array{rows: list<array<string, mixed>>, all: list<array<string, mixed>>, totals: array<string, int>, units: array<int, string>, filters: array<string, mixed>}
     */
    public function teamBoard(int $tenantId, Request $request): array
    {
        $viewerId = (int) Session::get('user_id');
        $filters = [
            'q' => mb_substr(trim((string) $request->queryString('q', '')), 0, 80),
            'track' => self::track((string) $request->queryString('parcours', '')),
            'owner' => self::owner((string) $request->queryString('responsable', ''), $viewerId),
            'etat' => in_array((string) $request->queryString('etat', ''), ['ouvert', 'retard', 'bloque'], true) ? (string) $request->queryString('etat', '') : '',
            'unit' => max(0, (int) $request->queryString('unite', 0)),
            'sort' => in_array((string) $request->queryString('tri', ''), MemberDossierTeamBoard::SORTS, true) ? (string) $request->queryString('tri', '') : 'retard',
            'desc' => $request->queryString('ordre', '') === 'desc',
        ];
        $timeline = MemberDossierTimeline::make();
        $board = new MemberDossierTeamBoard($timeline, $timeline->store());
        $all = $board->rows($tenantId, $this->teamMembers($tenantId));
        $units = [];
        foreach ($all as $r) {
            if ($r['unit_id'] > 0) {
                $units[$r['unit_id']] = $r['unit_name'];
            }
        }
        asort($units);
        $rows = MemberDossierTeamBoard::sort(MemberDossierTeamBoard::filter($all, $filters), $filters['sort'], $filters['desc']);

        return ['rows' => $rows, 'all' => $all, 'totals' => MemberDossierTeamBoard::totals($all), 'units' => $units, 'filters' => $filters];
    }

    /**
     * Membres actifs de la communauté, avec unité et grade.
     *
     * @return list<array{id: int, name: string, unit_id: int, unit_name: string, grade: string}>
     */
    private function teamMembers(int $tenantId): array
    {
        $users = Container::get(UserRepository::class);
        $ids = [];
        for ($offset = 0; $offset < 2000; $offset += 200) {
            $page = $users->listForTenant($tenantId, null, 'active', null, 200, $offset, true, null, null);
            foreach ($page as $u) {
                $ids[] = (int) $u['id'];
            }
            if (count($page) < 200) {
                break;
            }
        }
        $meta = [];
        foreach ($users->listEffectifsRosterByIds($tenantId, $ids) as $r) {
            $meta[(int) $r['id']] = $r;
        }
        $grades = [];
        foreach ($meta as $id => $r) {
            $grades[$id] = (string) ($r['grade_short'] ?? '');
        }
        try {
            $labels = (new MemberNameService())->labelsFor($tenantId, $ids, 'list', $grades);
        } catch (Throwable) {
            $labels = [];
        }
        $out = [];
        foreach ($ids as $id) {
            $out[] = [
                'id' => $id,
                'name' => (string) ($labels[$id] ?? ('Membre n° ' . $id)),
                'unit_id' => (int) ($meta[$id]['unit_id'] ?? 0),
                'unit_name' => (string) ($meta[$id]['unit_name'] ?? ''),
                'grade' => $grades[$id] ?? '',
            ];
        }

        return $out;
    }

    private function actions(): MemberDossierStepActions
    {
        $timeline = MemberDossierTimeline::make();

        return new MemberDossierStepActions($timeline, $timeline->store(), new LiveDossierPorts());
    }

    private function memberInTenant(int $tenantId, int $memberId): bool
    {
        return $memberId > 0 && Container::get(UserRepository::class)->findById($memberId, $tenantId) !== null;
    }

    private function deny(bool $manage): ?Response
    {
        if (!(int) Session::get('user_id') || !(int) Session::get('tenant_id')) {
            return Response::redirect(url('login'));
        }
        $gate = Gate::getInstance();
        $ok = EffectifsLmsAccess::allows($gate) && ($manage ? EffectifsLmsAccess::canManageSuivi($gate) : EffectifsLmsAccess::canViewSuivi($gate));
        if (!$ok) {
            Session::flash('error', $manage ? 'Vous n’êtes pas habilité à modifier le suivi des dossiers.' : 'Vous n’êtes pas habilité à consulter le suivi des dossiers.');

            return Response::redirect(effectifs_workspace_url());
        }

        return null;
    }

    private function csv(string $body, string $filename): Response
    {
        return (new Response())
            ->setStatusCode(200)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($body);
    }

    private static function track(string $v): string
    {
        return in_array($v, DossierCatalog::tracks(), true) ? $v : '';
    }

    private static function status(string $v): string
    {
        return $v === 'open' || array_key_exists($v, DossierCatalog::statusLabels()) ? $v : '';
    }

    /** Un type de responsable, « moi » (identifiant du lecteur) ou rien. */
    private static function owner(string $v, int $viewerId): string
    {
        if ($v === 'moi') {
            return (string) $viewerId;
        }

        return array_key_exists($v, DossierCatalog::ownerLabels()) && $v !== DossierCatalog::OWNER_USER ? $v : '';
    }
}
