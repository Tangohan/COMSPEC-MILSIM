<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\FireTeamRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UnitRepository;
use App\Support\ComspecApiKeyAuth;

/**
 * CRUD équipes de feu — auth clé X-COMSPEC-KEY / session (middleware tactique).
 */
final class FireTeamApiController
{
    private const DEFAULT_MAP_ID = 1;

    /** @var array<string, mixed>|null */
    private ?array $jsonBodyCache = null;

    public function __construct(
        private FireTeamRepository $fireTeams,
        private TenantRepository $tenantRepository,
        private UnitRepository $unitRepository,
        private ?\App\Services\Tactical\FireTeamActivityLogger $ftActivity = null,
    ) {
        $this->ftActivity ??= new \App\Services\Tactical\FireTeamActivityLogger(
            new \App\Services\Tactical\AtakActivityLogService()
        );
    }

    public function index(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        if (!$this->fireTeams->tablesReady()) {
            return Response::json(['error' => 'not_migrated', 'message' => 'Les équipes de feu ne sont pas encore disponibles.'], 503);
        }

        $filters = [
            'kind' => (string) ($request->query('kind') ?? ''),
            'include_dissolved' => $this->truthy($request->query('include_dissolved')),
        ];
        $mapQ = $request->query('mapId') ?? $request->query('map_id');
        if ($mapQ !== null && $mapQ !== '') {
            $filters['map_id'] = (int) $mapQ;
        }
        $unitQ = $request->query('unit_id') ?? $request->query('unitId');
        if ($unitQ !== null && $unitQ !== '') {
            $filters['unit_id'] = (int) $unitQ;
        }
        $missionKey = trim((string) ($request->query('mission_key') ?? $request->query('missionKey') ?? ''));
        if ($missionKey !== '') {
            $filters['mission_key'] = $missionKey;
        }

        $rows = $this->fireTeams->listForTenant($r, $filters);

        return Response::json([
            'ok' => true,
            'fire_teams' => array_map([$this, 'serializeTeam'], $rows),
        ]);
    }

    public function show(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $id = (int) ($params['id'] ?? 0);
        $team = $id > 0 ? $this->fireTeams->findByIdForTenant($id, $r) : null;
        if (!$team) {
            return Response::json(['error' => 'not_found'], 404);
        }

        return Response::json(['ok' => true, 'fire_team' => $this->serializeTeam($team)]);
    }

    public function store(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        if (!$this->fireTeams->tablesReady()) {
            return Response::json(['error' => 'not_migrated'], 503);
        }

        $body = $this->jsonBody($request);
        $kind = (($body['kind'] ?? $request->input('kind') ?? '') === FireTeamRepository::KIND_PERMANENT)
            ? FireTeamRepository::KIND_PERMANENT
            : FireTeamRepository::KIND_EPHEMERAL;

        $label = trim((string) ($body['label'] ?? $request->input('label') ?? ''));
        if ($label === '') {
            return Response::json(['error' => 'label_required', 'message' => 'Indiquez un nom d’équipe.'], 400);
        }

        $unitId = isset($body['unit_id']) ? (int) $body['unit_id'] : (int) ($request->input('unit_id') ?? 0);
        if ($kind === FireTeamRepository::KIND_PERMANENT && $unitId > 0) {
            $unit = $this->unitRepository->findById($unitId, $r);
            if (!$unit) {
                return Response::json(['error' => 'unit_not_found', 'message' => 'Unité introuvable dans l’organigramme.'], 400);
            }
        }

        $mapId = $this->mapId($request, true);
        $createdBy = (int) (Session::get('user_id') ?? 0);

        $team = $this->fireTeams->create($r, [
            'kind' => $kind,
            'label' => $label,
            'color' => (string) ($body['color'] ?? $request->input('color') ?? '#2563eb'),
            'map_id' => $kind === FireTeamRepository::KIND_EPHEMERAL ? $mapId : null,
            'mission_key' => (string) ($body['mission_key'] ?? $body['missionKey'] ?? $request->input('mission_key') ?? ''),
            'unit_id' => $kind === FireTeamRepository::KIND_PERMANENT ? $unitId : null,
            'notes' => (string) ($body['notes'] ?? $request->input('notes') ?? ''),
            'created_by_user_id' => $createdBy > 0 ? $createdBy : null,
        ]);

        if (!$team) {
            return Response::json(['error' => 'create_failed'], 500);
        }

        $members = $body['members'] ?? null;
        if (is_array($members)) {
            $normalized = [];
            foreach ($members as $m) {
                if (!is_array($m)) {
                    continue;
                }
                $uid = (int) ($m['user_id'] ?? $m['userId'] ?? 0);
                if ($uid < 1) {
                    continue;
                }
                $normalized[] = [
                    'user_id' => $uid,
                    'role' => (string) ($m['role'] ?? FireTeamRepository::ROLE_MEMBER),
                ];
            }
            if ($normalized !== []) {
                $this->fireTeams->replaceMembers((int) $team['id'], $r, $normalized);
                $team = $this->fireTeams->findByIdForTenant((int) $team['id'], $r) ?? $team;
            }
        }

        $this->ftActivity?->record(
            $r,
            $team,
            'created',
            'Équipe de feu créée — ' . (string) ($team['label'] ?? ''),
            $this->ftActivity->actorFromSession(),
            ['member_count' => (int) ($team['member_count'] ?? count($team['members'] ?? []))]
        );

        return Response::json(['ok' => true, 'fire_team' => $this->serializeTeam($team)], 201);
    }

    public function update(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $id = (int) ($params['id'] ?? 0);
        $body = $this->jsonBody($request);
        $data = [];
        foreach (['label', 'color', 'notes', 'mission_key', 'missionKey'] as $key) {
            if (array_key_exists($key, $body)) {
                $data[$key === 'missionKey' ? 'mission_key' : $key] = $body[$key];
            }
        }
        if (array_key_exists('mapId', $body) || array_key_exists('map_id', $body)) {
            $data['map_id'] = (int) ($body['mapId'] ?? $body['map_id'] ?? 0);
        }
        if (array_key_exists('unit_id', $body) || array_key_exists('unitId', $body)) {
            $data['unit_id'] = (int) ($body['unit_id'] ?? $body['unitId'] ?? 0);
        }

        $team = $this->fireTeams->update($id, $r, $data);
        if (!$team) {
            return Response::json(['error' => 'not_found_or_dissolved'], 404);
        }

        $action = array_key_exists('color', $data) ? 'color_changed' : 'updated';
        $label = array_key_exists('color', $data)
            ? ('Couleur d’équipe mise à jour — ' . (string) ($team['label'] ?? ''))
            : ('Équipe de feu mise à jour — ' . (string) ($team['label'] ?? ''));
        $this->ftActivity?->record($r, $team, $action, $label, $this->ftActivity->actorFromSession());

        return Response::json(['ok' => true, 'fire_team' => $this->serializeTeam($team)]);
    }

    public function dissolve(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $id = (int) ($params['id'] ?? 0);
        $before = $id > 0 ? $this->fireTeams->findByIdForTenant($id, $r) : null;
        if (!$this->fireTeams->dissolve($id, $r)) {
            return Response::json(['error' => 'not_found'], 404);
        }
        $team = $this->fireTeams->findByIdForTenant($id, $r) ?? $before;
        if (is_array($team)) {
            $this->ftActivity?->record(
                $r,
                $team,
                'dissolved',
                'Équipe de feu dissoute — ' . (string) ($team['label'] ?? ''),
                $this->ftActivity->actorFromSession()
            );
        }

        return Response::json(['ok' => true, 'fire_team' => $team ? $this->serializeTeam($team) : null]);
    }

    public function destroy(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $id = (int) ($params['id'] ?? 0);
        if (!$this->fireTeams->softDelete($id, $r)) {
            return Response::json(['error' => 'not_found'], 404);
        }

        return Response::json(['ok' => true]);
    }

    public function addMember(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $teamId = (int) ($params['id'] ?? 0);
        $body = $this->jsonBody($request);
        $member = $this->fireTeams->addMember($teamId, $r, [
            'user_id' => (int) ($body['user_id'] ?? $body['userId'] ?? 0),
            'callsign' => (string) ($body['callsign'] ?? ''),
            'role' => (string) ($body['role'] ?? FireTeamRepository::ROLE_MEMBER),
            'display_order' => (int) ($body['display_order'] ?? $body['displayOrder'] ?? 0),
        ]);
        if (!$member) {
            return Response::json(['error' => 'member_add_failed', 'message' => 'Impossible d’ajouter ce membre.'], 400);
        }
        $team = $this->fireTeams->findByIdForTenant($teamId, $r);
        if (is_array($team)) {
            $who = trim((string) ($member['effective_callsign'] ?? $member['callsign'] ?? $member['display_name'] ?? ''));
            $this->ftActivity?->record(
                $r,
                $team,
                'member_added',
                'Attribution fire team — ' . ($who !== '' ? $who : 'membre') . ' → ' . (string) ($team['label'] ?? ''),
                $this->ftActivity->actorFromSession(),
                [
                    'member_callsign' => (string) ($member['effective_callsign'] ?? $member['callsign'] ?? ''),
                    'member_user_id' => (int) ($member['user_id'] ?? 0),
                    'member_role' => (string) ($member['role'] ?? ''),
                ]
            );
        }

        return Response::json(['ok' => true, 'member' => $this->serializeMember($member)], 201);
    }

    public function updateMember(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $teamId = (int) ($params['id'] ?? 0);
        $memberId = (int) ($params['memberId'] ?? $params['member_id'] ?? 0);
        $body = $this->jsonBody($request);
        $patch = [];
        if (array_key_exists('callsign', $body)) {
            $patch['callsign'] = $body['callsign'];
        }
        if (array_key_exists('role', $body)) {
            $patch['role'] = $body['role'];
        }
        if (array_key_exists('display_order', $body) || array_key_exists('displayOrder', $body)) {
            $patch['display_order'] = (int) ($body['display_order'] ?? $body['displayOrder'] ?? 0);
        }
        $member = $this->fireTeams->updateMember($teamId, $memberId, $r, $patch);
        if (!$member) {
            return Response::json(['error' => 'not_found'], 404);
        }
        $team = $this->fireTeams->findByIdForTenant($teamId, $r);
        if (is_array($team) && (isset($patch['role']) || isset($patch['callsign']))) {
            $who = trim((string) ($member['effective_callsign'] ?? $member['callsign'] ?? $member['display_name'] ?? ''));
            $this->ftActivity?->record(
                $r,
                $team,
                'member_updated',
                'Changement fire team — ' . ($who !== '' ? $who : 'membre') . ' (' . (string) ($team['label'] ?? '') . ')',
                $this->ftActivity->actorFromSession(),
                [
                    'member_callsign' => (string) ($member['effective_callsign'] ?? $member['callsign'] ?? ''),
                    'member_role' => (string) ($member['role'] ?? ''),
                ]
            );
        }

        return Response::json(['ok' => true, 'member' => $this->serializeMember($member)]);
    }

    public function removeMember(Request $request, array $params = []): Response
    {
        $r = $this->requireTenant($request);
        if ($r instanceof Response) {
            return $r;
        }
        $teamId = (int) ($params['id'] ?? 0);
        $memberId = (int) ($params['memberId'] ?? $params['member_id'] ?? 0);
        $team = $this->fireTeams->findByIdForTenant($teamId, $r);
        $removedLabel = '';
        if (is_array($team)) {
            foreach (($team['members'] ?? []) as $m) {
                if (is_array($m) && (int) ($m['id'] ?? 0) === $memberId) {
                    $removedLabel = trim((string) ($m['effective_callsign'] ?? $m['callsign'] ?? $m['display_name'] ?? ''));
                    break;
                }
            }
        }
        if (!$this->fireTeams->removeMember($teamId, $memberId, $r)) {
            return Response::json(['error' => 'not_found'], 404);
        }
        if (is_array($team)) {
            $this->ftActivity?->record(
                $r,
                $team,
                'member_removed',
                'Retrait fire team — ' . ($removedLabel !== '' ? $removedLabel : 'membre') . ' ← ' . (string) ($team['label'] ?? ''),
                $this->ftActivity->actorFromSession(),
                ['member_callsign' => $removedLabel]
            );
        }

        return Response::json(['ok' => true]);
    }

    /** @param array<string, mixed> $team */
    private function serializeTeam(array $team): array
    {
        $kind = (string) ($team['kind'] ?? '');
        $members = [];
        foreach (($team['members'] ?? []) as $m) {
            if (is_array($m)) {
                $members[] = $this->serializeMember($m);
            }
        }

        return [
            'id' => (int) ($team['id'] ?? 0),
            'kind' => $kind,
            'kind_label' => $kind === FireTeamRepository::KIND_PERMANENT ? 'Organigramme' : 'Mission',
            'label' => (string) ($team['label'] ?? ''),
            'color' => (string) ($team['color'] ?? '#2563EB'),
            'map_id' => isset($team['map_id']) && $team['map_id'] !== null ? (int) $team['map_id'] : null,
            'mission_key' => $team['mission_key'] ?? null,
            'unit_id' => isset($team['unit_id']) && $team['unit_id'] !== null ? (int) $team['unit_id'] : null,
            'unit_name' => $team['unit_name'] ?? null,
            'notes' => $team['notes'] ?? null,
            'is_active' => !empty($team['is_active']),
            'dissolved_at' => $team['dissolved_at'] ?? null,
            'created_at' => $team['created_at'] ?? null,
            'updated_at' => $team['updated_at'] ?? null,
            'member_count' => (int) ($team['member_count'] ?? count($members)),
            'members' => $members,
        ];
    }

    /** @param array<string, mixed> $m */
    private function serializeMember(array $m): array
    {
        $role = (string) ($m['role'] ?? FireTeamRepository::ROLE_MEMBER);

        return [
            'id' => (int) ($m['id'] ?? 0),
            'user_id' => isset($m['user_id']) && $m['user_id'] !== null ? (int) $m['user_id'] : null,
            'display_name' => (string) ($m['display_name'] ?? ''),
            'callsign' => (string) ($m['effective_callsign'] ?? $m['callsign'] ?? ''),
            'role' => $role,
            'role_label' => $role === FireTeamRepository::ROLE_LEADER ? 'Chef d’équipe' : 'Membre',
            'display_order' => (int) ($m['display_order'] ?? 0),
            'avatar_url' => (string) ($m['avatar_url'] ?? ''),
        ];
    }

    private function requireTenant(Request $request): int|Response
    {
        $id = $this->resolveTenantId($request);
        if ($id === null) {
            return Response::json([
                'error' => 'tenant_context_required',
                'message' => 'Indiquez tenant_id ou tenant_slug, une session avec communauté, ou ATAK_DEFAULT_TENANT_ID.',
            ], 403);
        }

        return $id;
    }

    private function resolveTenantId(Request $request): ?int
    {
        $matched = ComspecApiKeyAuth::matchedTenantId();
        if ($matched !== null && $matched > 0) {
            return $matched;
        }
        $sid = Session::get('tenant_id');
        if ($sid !== null && $sid !== '') {
            $n = (int) $sid;

            return $n > 0 ? $n : null;
        }
        $q = $request->query('tenant_id');
        if ($q !== null && $q !== '') {
            $n = (int) $q;

            return $n > 0 ? $n : null;
        }
        $body = $this->jsonBody($request);
        if (!empty($body['tenant_id'])) {
            $n = (int) $body['tenant_id'];

            return $n > 0 ? $n : null;
        }
        $slug = $request->query('tenant_slug');
        if (is_string($slug) && trim($slug) !== '') {
            $t = $this->tenantRepository->findBySlug(trim($slug));

            return $t ? (int) $t['id'] : null;
        }
        $env = getenv('ATAK_DEFAULT_TENANT_ID') ?: getenv('APP_ATAK_DEFAULT_TENANT_ID');
        if ($env !== false && $env !== null && $env !== '') {
            return (int) $env;
        }

        return null;
    }

    private function mapId(Request $request, bool $fromBody = false): int
    {
        if ($fromBody) {
            $body = $this->jsonBody($request);
            $map = $body['mapId'] ?? $body['map_id'] ?? $request->query('mapId') ?? $request->input('map_id');
        } else {
            $map = $request->query('mapId') ?? $request->query('map_id');
        }

        return $map !== null && $map !== '' ? (int) $map : self::DEFAULT_MAP_ID;
    }

    /** @return array<string, mixed> */
    private function jsonBody(Request $request): array
    {
        if ($this->jsonBodyCache !== null) {
            return $this->jsonBodyCache;
        }
        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            $this->jsonBodyCache = [];

            return $this->jsonBodyCache;
        }
        $decoded = json_decode($raw, true);
        $this->jsonBodyCache = is_array($decoded) ? $decoded : [];

        return $this->jsonBodyCache;
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
