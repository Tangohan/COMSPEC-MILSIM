<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AtakMapRepository;
use App\Repositories\FireTeamRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;

/**
 * Gestion des équipes de feu (mission ATAK + organigramme RH).
 */
final class AdminFireTeamsController
{
    /** @var list<array{value: string, label: string}> */
    public const COLOR_CHOICES = [
        ['value' => '#2563EB', 'label' => 'Bleu'],
        ['value' => '#DC2626', 'label' => 'Rouge'],
        ['value' => '#16A34A', 'label' => 'Vert'],
        ['value' => '#EA580C', 'label' => 'Orange'],
        ['value' => '#7C3AED', 'label' => 'Violet'],
        ['value' => '#CA8A04', 'label' => 'Jaune'],
        ['value' => '#0891B2', 'label' => 'Cyan'],
        ['value' => '#64748B', 'label' => 'Gris'],
    ];

    public function __construct(
        private FireTeamRepository $fireTeams,
        private UnitRepository $unitRepository,
        private UserRepository $userRepository,
        private AtakMapRepository $atakMapRepository,
        private ?\App\Services\Tactical\FireTeamActivityLogger $ftActivity = null,
    ) {
        $this->ftActivity ??= new \App\Services\Tactical\FireTeamActivityLogger(
            new \App\Services\Tactical\AtakActivityLogService()
        );
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId <= 0) {
            return Response::redirect(url('login'));
        }

        $tab = (string) ($request->query('vue') ?? 'mission');
        if (!in_array($tab, ['mission', 'organigramme', 'toutes'], true)) {
            $tab = 'mission';
        }

        $filters = ['include_dissolved' => $this->truthy($request->query('inclure_dissoutes'))];
        if ($tab === 'mission') {
            $filters['kind'] = FireTeamRepository::KIND_EPHEMERAL;
        } elseif ($tab === 'organigramme') {
            $filters['kind'] = FireTeamRepository::KIND_PERMANENT;
        }

        $teams = $this->fireTeams->tablesReady()
            ? $this->fireTeams->listForTenant($tenantId, $filters)
            : [];

        $mapsById = [];
        foreach ($this->atakMapRepository->getAll() as $map) {
            $mapsById[(int) ($map['id'] ?? 0)] = (string) ($map['label'] ?? $map['slug'] ?? ('Carte #' . ($map['id'] ?? '')));
        }

        return Response::view('layout.main', [
            'content' => 'admin.fire_teams.index',
            'title' => 'Équipes de feu',
            'fireTeams' => $teams,
            'fireTeamsTab' => $tab,
            'fireTeamsMaps' => $mapsById,
            'fireTeamsReady' => $this->fireTeams->tablesReady(),
            'fireTeamsIncludeDissolved' => !empty($filters['include_dissolved']),
            'fireTeamsStats' => [
                'total' => count($teams),
                'active' => count(array_filter($teams, static fn (array $t): bool => !empty($t['is_active']))),
            ],
        ]);
    }

    public function create(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId <= 0) {
            return Response::redirect(url('login'));
        }

        $kind = (string) ($request->query('type') ?? 'mission');
        $isPermanent = $kind === 'organigramme' || $kind === FireTeamRepository::KIND_PERMANENT;

        return Response::view('layout.main', [
            'content' => 'admin.fire_teams.form',
            'title' => 'Nouvelle équipe de feu',
            'fireTeam' => null,
            'fireTeamKind' => $isPermanent ? FireTeamRepository::KIND_PERMANENT : FireTeamRepository::KIND_EPHEMERAL,
            'fireTeamUnits' => $this->unitRepository->getTeams($tenantId),
            'fireTeamMaps' => $this->atakMapRepository->getAll(),
            'fireTeamUsers' => $this->communityUsers($tenantId),
            'fireTeamColors' => self::COLOR_CHOICES,
            'fireTeamMemberIds' => [],
            'fireTeamLeaderId' => null,
            'fireTeamsReady' => $this->fireTeams->tablesReady(),
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId <= 0) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('back-office/atak/fire-teams/create'));
        }
        if (!$this->fireTeams->tablesReady()) {
            Session::flash('error', 'Les équipes de feu ne sont pas encore disponibles. Exécutez les migrations.');

            return Response::redirect(url('back-office/atak/fire-teams'));
        }

        $kind = $request->input('kind') === FireTeamRepository::KIND_PERMANENT
            ? FireTeamRepository::KIND_PERMANENT
            : FireTeamRepository::KIND_EPHEMERAL;
        $label = trim((string) $request->input('label', ''));
        if ($label === '') {
            Session::flash('error', 'Indiquez un nom pour l’équipe.');

            return Response::redirect(url('back-office/atak/fire-teams/create?type=' . ($kind === FireTeamRepository::KIND_PERMANENT ? 'organigramme' : 'mission')));
        }

        $unitId = (int) ($request->input('unit_id') ?? 0);
        if ($kind === FireTeamRepository::KIND_PERMANENT && $unitId > 0) {
            $unit = $this->unitRepository->findById($unitId, $tenantId);
            if (!$unit) {
                Session::flash('error', 'L’unité choisie est introuvable dans l’organigramme.');

                return Response::redirect(url('back-office/atak/fire-teams/create?type=organigramme'));
            }
        }

        $createdBy = (int) (Session::get('user_id') ?? 0);
        $team = $this->fireTeams->create($tenantId, [
            'kind' => $kind,
            'label' => $label,
            'color' => (string) $request->input('color', '#2563EB'),
            'map_id' => (int) ($request->input('map_id') ?? 1),
            'mission_key' => (string) $request->input('mission_key', ''),
            'unit_id' => $unitId,
            'notes' => (string) $request->input('notes', ''),
            'created_by_user_id' => $createdBy > 0 ? $createdBy : null,
        ]);

        if (!$team) {
            Session::flash('error', 'Impossible de créer l’équipe.');

            return Response::redirect(url('back-office/atak/fire-teams/create'));
        }

        $this->applyMembersFromRequest($request, (int) $team['id'], $tenantId);
        $team = $this->fireTeams->findByIdForTenant((int) $team['id'], $tenantId) ?? $team;
        $this->ftActivity?->record(
            $tenantId,
            $team,
            'created',
            'Équipe de feu créée — ' . (string) ($team['label'] ?? $label),
            $this->ftActivity->actorFromSession(),
            ['member_count' => (int) ($team['member_count'] ?? count($team['members'] ?? []))]
        );
        Session::flash('success', 'Équipe de feu créée.');

        return Response::redirect(url('back-office/atak/fire-teams/' . (int) $team['id']));
    }

    public function show(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        if ($tenantId <= 0 || $id < 1) {
            return Response::redirect(url('back-office/atak/fire-teams'));
        }

        $team = $this->fireTeams->findByIdForTenant($id, $tenantId);
        if (!$team) {
            Session::flash('error', 'Équipe introuvable.');

            return Response::redirect(url('back-office/atak/fire-teams'));
        }

        $memberIds = [];
        $leaderId = null;
        foreach (($team['members'] ?? []) as $m) {
            $uid = (int) ($m['user_id'] ?? 0);
            if ($uid > 0) {
                $memberIds[] = $uid;
                if (($m['role'] ?? '') === FireTeamRepository::ROLE_LEADER) {
                    $leaderId = $uid;
                }
            }
        }

        $mapsById = [];
        foreach ($this->atakMapRepository->getAll() as $map) {
            $mapsById[(int) ($map['id'] ?? 0)] = (string) ($map['label'] ?? $map['slug'] ?? '');
        }

        return Response::view('layout.main', [
            'content' => 'admin.fire_teams.form',
            'title' => (string) ($team['label'] ?? 'Équipe de feu'),
            'fireTeam' => $team,
            'fireTeamKind' => (string) ($team['kind'] ?? FireTeamRepository::KIND_EPHEMERAL),
            'fireTeamUnits' => $this->unitRepository->getTeams($tenantId),
            'fireTeamMaps' => $this->atakMapRepository->getAll(),
            'fireTeamMapsById' => $mapsById,
            'fireTeamUsers' => $this->communityUsers($tenantId),
            'fireTeamColors' => self::COLOR_CHOICES,
            'fireTeamMemberIds' => $memberIds,
            'fireTeamLeaderId' => $leaderId,
            'fireTeamsReady' => true,
        ]);
    }

    public function update(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        if ($tenantId <= 0 || $id < 1) {
            return Response::redirect(url('back-office/atak/fire-teams'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('back-office/atak/fire-teams/' . $id));
        }

        $label = trim((string) $request->input('label', ''));
        if ($label === '') {
            Session::flash('error', 'Indiquez un nom pour l’équipe.');

            return Response::redirect(url('back-office/atak/fire-teams/' . $id));
        }

        $before = $this->fireTeams->findByIdForTenant($id, $tenantId);
        $beforeMembers = [];
        if (is_array($before)) {
            foreach (($before['members'] ?? []) as $m) {
                if (is_array($m)) {
                    $uid = (int) ($m['user_id'] ?? 0);
                    if ($uid > 0) {
                        $beforeMembers[$uid] = true;
                    }
                }
            }
        }

        $team = $this->fireTeams->update($id, $tenantId, [
            'label' => $label,
            'color' => (string) $request->input('color', '#2563EB'),
            'map_id' => (int) ($request->input('map_id') ?? 0),
            'mission_key' => (string) $request->input('mission_key', ''),
            'unit_id' => (int) ($request->input('unit_id') ?? 0),
            'notes' => (string) $request->input('notes', ''),
        ]);

        if (!$team) {
            Session::flash('error', 'Impossible de modifier cette équipe (introuvable ou déjà dissoute).');

            return Response::redirect(url('back-office/atak/fire-teams'));
        }

        $this->applyMembersFromRequest($request, $id, $tenantId);
        $team = $this->fireTeams->findByIdForTenant($id, $tenantId) ?? $team;

        $afterMembers = [];
        foreach (($team['members'] ?? []) as $m) {
            if (is_array($m)) {
                $uid = (int) ($m['user_id'] ?? 0);
                if ($uid > 0) {
                    $afterMembers[$uid] = trim((string) ($m['effective_callsign'] ?? $m['display_name'] ?? ''));
                }
            }
        }
        $added = array_diff_key($afterMembers, $beforeMembers);
        $removed = array_diff_key($beforeMembers, $afterMembers);
        $colorBefore = is_array($before) ? strtoupper((string) ($before['color'] ?? '')) : '';
        $colorAfter = strtoupper((string) ($team['color'] ?? ''));

        if ($added !== [] || $removed !== []) {
            $bits = [];
            foreach ($added as $name) {
                if ($name !== '') {
                    $bits[] = 'rejoint : ' . $name;
                }
            }
            foreach ($removed as $name) {
                $bits[] = 'quitté : ' . ($name !== '' ? $name : 'membre');
            }
            $this->ftActivity?->record(
                $tenantId,
                $team,
                'roster_changed',
                'Composition fire team — ' . (string) ($team['label'] ?? '') . ($bits !== [] ? ' (' . implode(', ', $bits) . ')' : ''),
                $this->ftActivity->actorFromSession(),
                ['added' => count($added), 'removed' => count($removed)]
            );
        } elseif ($colorBefore !== '' && $colorAfter !== '' && $colorBefore !== $colorAfter) {
            $this->ftActivity?->record(
                $tenantId,
                $team,
                'color_changed',
                'Couleur d’équipe mise à jour — ' . (string) ($team['label'] ?? ''),
                $this->ftActivity->actorFromSession()
            );
        } else {
            $this->ftActivity?->record(
                $tenantId,
                $team,
                'updated',
                'Équipe de feu mise à jour — ' . (string) ($team['label'] ?? ''),
                $this->ftActivity->actorFromSession()
            );
        }

        Session::flash('success', 'Équipe mise à jour.');

        return Response::redirect(url('back-office/atak/fire-teams/' . $id));
    }

    public function dissolve(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        if ($tenantId <= 0 || $id < 1) {
            return Response::redirect(url('back-office/atak/fire-teams'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('back-office/atak/fire-teams/' . $id));
        }

        $before = $this->fireTeams->findByIdForTenant($id, $tenantId);
        if ($this->fireTeams->dissolve($id, $tenantId)) {
            if (is_array($before)) {
                $this->ftActivity?->record(
                    $tenantId,
                    $before,
                    'dissolved',
                    'Équipe de feu dissoute — ' . (string) ($before['label'] ?? ''),
                    $this->ftActivity->actorFromSession()
                );
            }
            Session::flash('success', 'Équipe dissoute. Elle n’apparaît plus comme active pour la mission.');
        } else {
            Session::flash('error', 'Impossible de dissoudre cette équipe.');
        }

        return Response::redirect(url('back-office/atak/fire-teams'));
    }

    public function delete(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        if ($tenantId <= 0 || $id < 1) {
            return Response::redirect(url('back-office/atak/fire-teams'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('back-office/atak/fire-teams'));
        }

        if ($this->fireTeams->softDelete($id, $tenantId)) {
            Session::flash('success', 'Équipe retirée.');
        } else {
            Session::flash('error', 'Impossible de retirer cette équipe.');
        }

        return Response::redirect(url('back-office/atak/fire-teams'));
    }

    private function applyMembersFromRequest(Request $request, int $teamId, int $tenantId): void
    {
        $rawIds = $request->input('member_user_ids', []);
        if (!is_array($rawIds)) {
            $rawIds = [];
        }
        $leaderId = (int) ($request->input('leader_user_id') ?? 0);
        $members = [];
        foreach ($rawIds as $raw) {
            $uid = (int) $raw;
            if ($uid < 1) {
                continue;
            }
            $members[] = [
                'user_id' => $uid,
                'role' => $uid === $leaderId
                    ? FireTeamRepository::ROLE_LEADER
                    : FireTeamRepository::ROLE_MEMBER,
            ];
        }
        // Si un chef est choisi mais pas coché dans la liste, l’ajouter.
        if ($leaderId > 0) {
            $found = false;
            foreach ($members as $m) {
                if ((int) $m['user_id'] === $leaderId) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                array_unshift($members, [
                    'user_id' => $leaderId,
                    'role' => FireTeamRepository::ROLE_LEADER,
                ]);
            }
        }
        $this->fireTeams->replaceMembers($teamId, $tenantId, $members);
    }

    /** @return list<array<string, mixed>> */
    private function communityUsers(int $tenantId): array
    {
        $users = $this->userRepository->allForTenant($tenantId);
        $out = [];
        foreach ($users as $u) {
            $status = (string) ($u['status'] ?? '');
            if ($status !== '' && $status !== 'active') {
                continue;
            }
            $out[] = $u;
        }
        usort($out, static function (array $a, array $b): int {
            $ca = trim((string) ($a['callsign'] ?? ''));
            $cb = trim((string) ($b['callsign'] ?? ''));
            $da = trim((string) ($a['display_name'] ?? ''));
            $db = trim((string) ($b['display_name'] ?? ''));
            $la = $ca !== '' ? $ca : $da;
            $lb = $cb !== '' ? $cb : $db;

            return strcasecmp($la, $lb);
        });

        return $out;
    }

    private function truthy(mixed $v): bool
    {
        if ($v === true || $v === 1 || $v === '1') {
            return true;
        }
        if (is_string($v)) {
            return in_array(strtolower($v), ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }
}
