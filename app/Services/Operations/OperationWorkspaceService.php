<?php

declare(strict_types=1);

namespace App\Services\Operations;

use App\Core\Gate;
use App\Repositories\OperationWorkspaceRepository;
use App\Support\OperationLabels;
use App\Support\TacticalGraphicsCatalog;
use App\Services\Integrations\DiscordEventRelayService;
use App\Support\DiscordWebhookCatalog;

final class OperationWorkspaceService
{
    public const DEFAULT_PHASES = [
        ['seq' => 0, 'code' => 'P0', 'name' => 'Préparer'],
        ['seq' => 1, 'code' => 'P1', 'name' => 'Infiltrer'],
        ['seq' => 2, 'code' => 'P2', 'name' => 'Isoler'],
        ['seq' => 3, 'code' => 'P3', 'name' => 'Assaut'],
        ['seq' => 4, 'code' => 'P4', 'name' => 'Consolider'],
        ['seq' => 5, 'code' => 'P5', 'name' => 'Extraire'],
    ];

    public const DEFAULT_OVERLAYS = [
        ['kind' => 'maneuver', 'name' => 'Manœuvre'],
        ['kind' => 'fire_support', 'name' => 'Appuis-feux'],
        ['kind' => 'intelligence', 'name' => 'Renseignement'],
        ['kind' => 'friendly', 'name' => 'Unités amies'],
        ['kind' => 'enemy', 'name' => 'Situation ennemie'],
    ];

    public const DEFAULT_ELEMENTS = [
        ['code' => 'HQ', 'name' => 'État-major', 'kind' => 'command'],
        ['code' => 'INDIA', 'name' => 'INDIA', 'kind' => 'maneuver'],
        ['code' => 'JULIET', 'name' => 'JULIET', 'kind' => 'maneuver'],
        ['code' => 'SUPPORT', 'name' => 'Soutien', 'kind' => 'support'],
    ];

    public function __construct(
        private OperationWorkspaceRepository $repo,
        private ?DiscordEventRelayService $discord = null,
    ) {
        $this->discord ??= new DiscordEventRelayService();
    }

    public function canView(?Gate $gate = null): bool
    {
        return (int) \App\Core\Session::get('tenant_id') > 0;
    }

    public function canPlan(?Gate $gate = null): bool
    {
        $gate ??= Gate::getInstance();

        return $gate->allows('operations.missions.manage')
            || $gate->allows('operations.planning.edit')
            || $gate->allows('admin.organization');
    }

    public function canIntel(?Gate $gate = null): bool
    {
        $gate ??= Gate::getInstance();

        return $this->canPlan($gate)
            || $gate->allows('operations.intel.product')
            || $gate->allows('atak.sse.case.manage')
            || $gate->allows('intel.poe.manage');
    }

    public function canOrders(?Gate $gate = null): bool
    {
        $gate ??= Gate::getInstance();

        return $this->canPlan($gate) || $gate->allows('operations.orders.edit');
    }

    public function canPublish(?Gate $gate = null): bool
    {
        $gate ??= Gate::getInstance();

        return $gate->allows('operations.missions.manage')
            || $gate->allows('operations.overlay.publish')
            || $gate->allows('admin.organization');
    }

    public function canChangePhase(?Gate $gate = null): bool
    {
        $gate ??= Gate::getInstance();

        return $gate->allows('operations.missions.manage')
            || $gate->allows('operations.phase.change')
            || $gate->allows('admin.organization');
    }

    /**
     * @return array{ok: bool, uuid?: string, error?: string}
     */
    public function createOperation(int $tenantId, int $userId, array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            return ['ok' => false, 'error' => 'Indiquez le nom de l’opération.'];
        }
        $code = strtoupper(preg_replace('/[^A-Z0-9\-]/i', '', (string) ($input['code'] ?? '')) ?? '');
        if ($code === '') {
            $code = strtoupper(substr(preg_replace('/[^A-Z0-9]/i', '', $name) ?? 'OPS', 0, 12));
        }
        if ($code === '') {
            $code = 'OPS';
        }
        $base = $code;
        $n = 2;
        while ($this->repo->codeExists($tenantId, $code)) {
            $code = $base . '-' . $n;
            $n++;
        }
        $uuid = OperationWorkspaceRepository::uuid();
        $id = $this->repo->create($tenantId, [
            'uuid' => $uuid,
            'code' => $code,
            'name' => $name,
            'classification' => $this->safeClass((string) ($input['classification'] ?? 'restricted')),
            'status' => $this->safeStatus((string) ($input['status'] ?? 'draft')),
            'commander_user_id' => $userId > 0 ? $userId : null,
            'workspace_key' => strtolower($code),
            'description' => trim((string) ($input['description'] ?? '')) ?: null,
            'created_by' => $userId > 0 ? $userId : null,
        ]);

        $firstPhaseId = null;
        foreach (self::DEFAULT_PHASES as $phase) {
            $pid = $this->repo->insertPhase($tenantId, $id, $phase['seq'], $phase['code'], $phase['name']);
            if ($firstPhaseId === null) {
                $firstPhaseId = $pid;
            }
        }
        if ($firstPhaseId !== null) {
            $this->repo->update($tenantId, $id, ['current_phase_id' => $firstPhaseId]);
        }

        foreach (self::DEFAULT_OVERLAYS as $i => $ov) {
            $overlayId = $this->repo->insertOverlay($tenantId, $id, $ov['name'], $ov['kind'], 'staff', $userId);
            $this->repo->insertLayer($tenantId, $id, $overlayId, $ov['name'], $ov['kind'], $i);
            $this->snapshotOverlay($tenantId, $overlayId, 'draft', 'Création', $userId);
        }

        foreach (self::DEFAULT_ELEMENTS as $i => $el) {
            $this->repo->insertElement($tenantId, $id, $el['code'], $el['name'], $el['kind'], $i);
        }

        if ($userId > 0) {
            $this->repo->insertMember($tenantId, $id, $userId, 'Commandement', 'HQ');
        }

        $this->repo->logActivity($tenantId, $id, $userId, 'created', $name);
        $this->discordNotify($tenantId, DiscordWebhookCatalog::KEY_OPERATION_STATUS, 'L’opération ' . $code . ' a été ouverte.');

        return ['ok' => true, 'uuid' => $uuid];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function workspacePayload(int $tenantId, string $uuid, bool $includeDrafts): ?array
    {
        $op = $this->repo->findByUuid($tenantId, $uuid);
        if ($op === null) {
            return null;
        }
        $opId = (int) $op['id'];
        $overlays = $this->repo->listOverlays($tenantId, $opId);
        $layers = $this->repo->listLayers($tenantId, $opId);
        $objects = $this->repo->listObjects($tenantId, $opId, null, !$includeDrafts);
        $versionsByOverlay = [];
        foreach ($overlays as $ov) {
            $versionsByOverlay[(int) $ov['id']] = $this->repo->listOverlayVersions($tenantId, (int) $ov['id']);
        }

        return [
            'operation' => $this->presentOperation($op),
            'phases' => $this->repo->listPhases($tenantId, $opId),
            'overlays' => array_map([$this, 'presentOverlay'], $overlays),
            'layers' => $layers,
            'objects' => array_map([$this, 'presentObject'], $objects),
            'versions' => $versionsByOverlay,
            'tasks' => $this->repo->listTasks($tenantId, $opId),
            'targets' => $this->repo->listTargets($tenantId, $opId),
            'orders' => array_map([$this, 'presentOrder'], $this->repo->listOrders($tenantId, $opId)),
            'elements' => $this->repo->listElements($tenantId, $opId),
            'members' => $this->repo->listMembers($tenantId, $opId),
            'activity' => $this->repo->listActivity($tenantId, $opId),
            'locks' => $this->repo->listLocks($tenantId, $opId),
            'graphics' => TacticalGraphicsCatalog::groups(),
        ];
    }

    /**
     * Vue terrain : calques explicitement publiés uniquement.
     *
     * @return array<string, mixed>|null
     */
    public function tacticalPayload(int $tenantId, string $uuid): ?array
    {
        $payload = $this->workspacePayload($tenantId, $uuid, false);
        if ($payload === null) {
            return null;
        }
        $publishedOverlays = array_values(array_filter(
            $payload['overlays'],
            static fn (array $ov): bool => ($ov['workflow'] ?? '') === 'published'
        ));
        $publishedIds = array_map(static fn (array $ov): int => (int) $ov['id'], $publishedOverlays);
        $objects = array_values(array_filter(
            $payload['objects'],
            static fn (array $obj): bool => in_array((int) ($obj['overlay_id'] ?? 0), $publishedIds, true)
        ));
        $currentPhaseId = (int) ($payload['operation']['current_phase_id'] ?? 0);
        $objects = array_values(array_filter(
            $objects,
            static function (array $obj) use ($currentPhaseId): bool {
                if (!empty($obj['all_phases'])) {
                    return true;
                }

                return (int) ($obj['phase_id'] ?? 0) === $currentPhaseId;
            }
        ));

        return [
            'operation' => $payload['operation'],
            'phases' => $payload['phases'],
            'overlays' => $publishedOverlays,
            'objects' => $objects,
            'tasks' => array_values(array_filter(
                $payload['tasks'],
                static fn (array $t): bool => in_array((string) ($t['status'] ?? ''), ['ready', 'active', 'upcoming'], true)
            )),
        ];
    }

    public function setStatus(int $tenantId, string $uuid, string $status, int $userId): bool
    {
        $op = $this->repo->findByUuid($tenantId, $uuid);
        if ($op === null) {
            return false;
        }
        $status = $this->safeStatus($status);
        $this->repo->update($tenantId, (int) $op['id'], ['status' => $status]);
        $this->repo->logActivity($tenantId, (int) $op['id'], $userId, 'status_changed', OperationLabels::status($status));
        $this->discordNotify(
            $tenantId,
            DiscordWebhookCatalog::KEY_OPERATION_STATUS,
            'L’opération ' . $op['code'] . ' est maintenant : ' . OperationLabels::status($status) . '.'
        );

        return true;
    }

    public function setPhase(int $tenantId, string $uuid, int $phaseId, int $userId): bool
    {
        $op = $this->repo->findByUuid($tenantId, $uuid);
        if ($op === null) {
            return false;
        }
        $found = null;
        foreach ($this->repo->listPhases($tenantId, (int) $op['id']) as $phase) {
            if ((int) $phase['id'] === $phaseId) {
                $found = $phase;
                break;
            }
        }
        if ($found === null) {
            return false;
        }
        $this->repo->update($tenantId, (int) $op['id'], ['current_phase_id' => $phaseId]);
        $this->repo->logActivity($tenantId, (int) $op['id'], $userId, 'phase_changed', (string) $found['name']);

        return true;
    }

    /**
     * @return array{ok: bool, uuid?: string, error?: string}
     */
    public function addObject(int $tenantId, string $opUuid, int $userId, array $input): array
    {
        $op = $this->repo->findByUuid($tenantId, $opUuid);
        if ($op === null) {
            return ['ok' => false, 'error' => 'Opération introuvable.'];
        }
        $graphic = TacticalGraphicsCatalog::find((string) ($input['graphic_type'] ?? ''));
        if ($graphic === null) {
            return ['ok' => false, 'error' => 'Type de graphique inconnu.'];
        }
        $overlayId = (int) ($input['overlay_id'] ?? 0);
        $overlay = $this->repo->findOverlay($tenantId, $overlayId);
        if ($overlay === null || (int) $overlay['operation_id'] !== (int) $op['id']) {
            return ['ok' => false, 'error' => 'Calque introuvable.'];
        }
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            $name = $graphic['label'];
        }
        $uuid = OperationWorkspaceRepository::uuid();
        $geometry = $input['geometry'] ?? $this->defaultGeometry($graphic['geometry'], $input);
        $this->repo->insertObject($tenantId, [
            'operation_id' => (int) $op['id'],
            'overlay_id' => $overlayId,
            'layer_id' => ((int) ($input['layer_id'] ?? 0)) ?: null,
            'uuid' => $uuid,
            'graphic_type' => $graphic['id'],
            'name' => $name,
            'affiliation' => $this->safeAffiliation((string) ($input['affiliation'] ?? 'friendly')),
            'status' => (string) ($input['status'] ?? 'planned'),
            'phase_id' => ((int) ($input['phase_id'] ?? 0)) ?: null,
            'all_phases' => !empty($input['all_phases']),
            'element_code' => trim((string) ($input['element_code'] ?? '')) ?: null,
            'description' => trim((string) ($input['description'] ?? '')) ?: null,
            'classification' => $this->safeClass((string) ($input['classification'] ?? $op['classification'])),
            'geometry_json' => json_encode($geometry, JSON_UNESCAPED_UNICODE),
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
        $this->repo->logActivity($tenantId, (int) $op['id'], $userId, 'object_created', $name);

        return ['ok' => true, 'uuid' => $uuid];
    }

    public function moveObject(int $tenantId, string $opUuid, string $objectUuid, int $userId, array $geometry): array
    {
        $op = $this->repo->findByUuid($tenantId, $opUuid);
        if ($op === null) {
            return ['ok' => false, 'error' => 'Opération introuvable.'];
        }
        $obj = $this->repo->findObjectByUuid($tenantId, $objectUuid);
        if ($obj === null || (int) $obj['operation_id'] !== (int) $op['id']) {
            return ['ok' => false, 'error' => 'Objet introuvable.'];
        }
        if (!$this->repo->claimLock($tenantId, (int) $op['id'], $objectUuid, $userId)) {
            return ['ok' => false, 'error' => 'Cet objet est déjà en cours d’édition.'];
        }
        $this->repo->updateObject($tenantId, $objectUuid, [
            'geometry_json' => json_encode($geometry, JSON_UNESCAPED_UNICODE),
            'updated_by' => $userId,
        ]);
        $this->repo->logActivity($tenantId, (int) $op['id'], $userId, 'object_moved', (string) $obj['name']);

        return ['ok' => true];
    }

    public function updateObjectMeta(int $tenantId, string $opUuid, string $objectUuid, int $userId, array $input): array
    {
        $op = $this->repo->findByUuid($tenantId, $opUuid);
        if ($op === null) {
            return ['ok' => false, 'error' => 'Opération introuvable.'];
        }
        $obj = $this->repo->findObjectByUuid($tenantId, $objectUuid);
        if ($obj === null || (int) $obj['operation_id'] !== (int) $op['id']) {
            return ['ok' => false, 'error' => 'Objet introuvable.'];
        }
        if (!$this->repo->claimLock($tenantId, (int) $op['id'], $objectUuid, $userId)) {
            return ['ok' => false, 'error' => 'Cet objet est déjà en cours d’édition.'];
        }
        $patch = ['updated_by' => $userId];
        foreach (['name', 'affiliation', 'status', 'element_code', 'description', 'classification'] as $col) {
            if (array_key_exists($col, $input)) {
                $patch[$col] = $input[$col];
            }
        }
        if (array_key_exists('all_phases', $input)) {
            $patch['all_phases'] = !empty($input['all_phases']) ? 1 : 0;
        }
        if (array_key_exists('phase_id', $input)) {
            $patch['phase_id'] = ((int) $input['phase_id']) ?: null;
        }
        $this->repo->updateObject($tenantId, $objectUuid, $patch);
        $this->repo->logActivity($tenantId, (int) $op['id'], $userId, 'object_updated', (string) ($patch['name'] ?? $obj['name']));

        return ['ok' => true];
    }

    public function deleteObject(int $tenantId, string $opUuid, string $objectUuid, int $userId): array
    {
        $op = $this->repo->findByUuid($tenantId, $opUuid);
        if ($op === null) {
            return ['ok' => false, 'error' => 'Opération introuvable.'];
        }
        $obj = $this->repo->findObjectByUuid($tenantId, $objectUuid);
        if ($obj === null || (int) $obj['operation_id'] !== (int) $op['id']) {
            return ['ok' => false, 'error' => 'Objet introuvable.'];
        }
        if (!$this->repo->claimLock($tenantId, (int) $op['id'], $objectUuid, $userId)) {
            return ['ok' => false, 'error' => 'Cet objet est déjà en cours d’édition.'];
        }
        $this->repo->deleteObject($tenantId, $objectUuid);
        $this->repo->releaseLock($tenantId, (int) $op['id'], $objectUuid, $userId);
        $this->repo->logActivity($tenantId, (int) $op['id'], $userId, 'object_deleted', (string) $obj['name']);

        return ['ok' => true];
    }

    public function advanceOverlayWorkflow(int $tenantId, int $overlayId, string $workflow, int $userId, ?string $note = null): array
    {
        $overlay = $this->repo->findOverlay($tenantId, $overlayId);
        if ($overlay === null) {
            return ['ok' => false, 'error' => 'Calque introuvable.'];
        }
        $workflow = $this->safeWorkflow($workflow);
        $version = (int) $overlay['current_version'] + 1;
        $visibility = $workflow === 'published' ? 'published' : (string) $overlay['visibility'];
        $this->repo->updateOverlay($tenantId, $overlayId, [
            'workflow' => $workflow,
            'current_version' => $version,
            'published_version' => $workflow === 'published' ? $version : $overlay['published_version'],
            'visibility' => $visibility,
        ]);
        $this->snapshotOverlay($tenantId, $overlayId, $workflow, $note, $userId);
        $this->repo->logActivity(
            $tenantId,
            (int) $overlay['operation_id'],
            $userId,
            'overlay_workflow',
            (string) $overlay['name'] . ' — ' . OperationLabels::workflow($workflow)
        );
        if ($workflow === 'published') {
            $stOp = $this->repo->findById($tenantId, (int) $overlay['operation_id']);
            $code = (string) ($stOp['code'] ?? '');
            $this->discordNotify(
                $tenantId,
                DiscordWebhookCatalog::KEY_OVERLAY_PUBLISHED,
                'Un calque de l’opération ' . $code . ' a été publié sur la vue terrain : ' . $overlay['name'] . '.'
            );
        }

        return ['ok' => true];
    }

    public function restoreOverlayVersion(int $tenantId, int $overlayId, int $version, int $userId): array
    {
        $overlay = $this->repo->findOverlay($tenantId, $overlayId);
        if ($overlay === null) {
            return ['ok' => false, 'error' => 'Calque introuvable.'];
        }
        $found = null;
        foreach ($this->repo->listOverlayVersions($tenantId, $overlayId) as $row) {
            if ((int) $row['version'] === $version) {
                $found = $row;
                break;
            }
        }
        if ($found === null) {
            return ['ok' => false, 'error' => 'Version introuvable.'];
        }
        $snapshot = json_decode((string) $found['snapshot_json'], true);
        if (!is_array($snapshot)) {
            return ['ok' => false, 'error' => 'Version illisible.'];
        }
        $existing = $this->repo->listObjects($tenantId, (int) $overlay['operation_id'], $overlayId);
        foreach ($existing as $obj) {
            $this->repo->deleteObject($tenantId, (string) $obj['uuid']);
        }
        foreach ($snapshot['objects'] ?? [] as $obj) {
            if (!is_array($obj)) {
                continue;
            }
            $this->repo->insertObject($tenantId, [
                'operation_id' => (int) $overlay['operation_id'],
                'overlay_id' => $overlayId,
                'layer_id' => $obj['layer_id'] ?? null,
                'uuid' => OperationWorkspaceRepository::uuid(),
                'graphic_type' => $obj['graphic_type'] ?? 'point',
                'name' => $obj['name'] ?? 'Objet',
                'affiliation' => $obj['affiliation'] ?? 'friendly',
                'status' => $obj['status'] ?? 'planned',
                'phase_id' => $obj['phase_id'] ?? null,
                'all_phases' => !empty($obj['all_phases']),
                'element_code' => $obj['element_code'] ?? null,
                'description' => $obj['description'] ?? null,
                'classification' => $obj['classification'] ?? 'restricted',
                'geometry_json' => is_string($obj['geometry_json'] ?? null)
                    ? $obj['geometry_json']
                    : json_encode($obj['geometry'] ?? [], JSON_UNESCAPED_UNICODE),
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
        }
        $this->repo->logActivity($tenantId, (int) $overlay['operation_id'], $userId, 'overlay_restored', $overlay['name'] . ' v' . $version);

        return ['ok' => true];
    }

    public function addTask(int $tenantId, string $uuid, int $userId, array $input): array
    {
        $op = $this->repo->findByUuid($tenantId, $uuid);
        if ($op === null) {
            return ['ok' => false, 'error' => 'Opération introuvable.'];
        }
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            return ['ok' => false, 'error' => 'Indiquez le libellé de la tâche.'];
        }
        $seq = count($this->repo->listTasks($tenantId, (int) $op['id'])) + 1;
        $code = strtoupper((string) ($op['code'] ?? 'OPS')) . '-' . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
        $this->repo->insertTask($tenantId, (int) $op['id'], [
            'code' => $code,
            'title' => $title,
            'assigned_element' => trim((string) ($input['assigned_element'] ?? '')) ?: null,
            'supporting_element' => trim((string) ($input['supporting_element'] ?? '')) ?: null,
            'h_offset' => trim((string) ($input['h_offset'] ?? '')) ?: null,
            'status' => (string) ($input['status'] ?? 'upcoming'),
            'description' => trim((string) ($input['description'] ?? '')) ?: null,
            'created_by' => $userId,
        ]);
        $this->repo->logActivity($tenantId, (int) $op['id'], $userId, 'task_created', $title);

        return ['ok' => true];
    }

    public function addTarget(int $tenantId, string $uuid, int $userId, array $input): array
    {
        $op = $this->repo->findByUuid($tenantId, $uuid);
        if ($op === null) {
            return ['ok' => false, 'error' => 'Opération introuvable.'];
        }
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            return ['ok' => false, 'error' => 'Indiquez le nom de l’objectif.'];
        }
        $seq = count($this->repo->listTargets($tenantId, (int) $op['id'])) + 1;
        $code = 'TGT-' . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
        $this->repo->insertTarget($tenantId, (int) $op['id'], [
            'target_code' => $code,
            'name' => $name,
            'classification' => $this->safeClass((string) ($input['classification'] ?? $op['classification'])),
            'target_type' => trim((string) ($input['target_type'] ?? '')) ?: null,
            'category' => trim((string) ($input['category'] ?? '')) ?: null,
            'mgrs' => trim((string) ($input['mgrs'] ?? '')) ?: null,
            'confidence' => trim((string) ($input['confidence'] ?? '')) ?: null,
            'sse_person_id' => ((int) ($input['sse_person_id'] ?? 0)) ?: null,
            'sse_case_id' => ((int) ($input['sse_case_id'] ?? 0)) ?: null,
            'notes' => trim((string) ($input['notes'] ?? '')) ?: null,
            'created_by' => $userId,
        ]);
        $this->repo->logActivity($tenantId, (int) $op['id'], $userId, 'target_created', $name);

        return ['ok' => true];
    }

    public function saveOrder(int $tenantId, string $uuid, int $userId, array $input): array
    {
        $op = $this->repo->findByUuid($tenantId, $uuid);
        if ($op === null) {
            return ['ok' => false, 'error' => 'Opération introuvable.'];
        }
        $kind = (string) ($input['kind'] ?? 'opord');
        if (!in_array($kind, ['opord', 'warnord', 'frago'], true)) {
            $kind = 'opord';
        }
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            $title = OperationLabels::orderKind($kind) . ' ' . $op['code'];
        }
        $seq = count($this->repo->listOrders($tenantId, (int) $op['id'])) + 1;
        $code = strtoupper((string) $op['code']) . '-' . strtoupper(substr($kind, 0, 3)) . '-' . str_pad((string) $seq, 2, '0', STR_PAD_LEFT);
        $sections = [
            'situation' => trim((string) ($input['situation'] ?? '')),
            'mission' => trim((string) ($input['mission'] ?? '')),
            'execution' => trim((string) ($input['execution'] ?? '')),
            'sustainment' => trim((string) ($input['sustainment'] ?? '')),
            'command' => trim((string) ($input['command'] ?? '')),
        ];
        $overlayRefs = array_values(array_filter(array_map('intval', (array) ($input['overlay_ids'] ?? []))));
        $this->repo->insertOrder($tenantId, (int) $op['id'], [
            'kind' => $kind,
            'code' => $code,
            'title' => $title,
            'workflow' => 'draft',
            'overlay_refs_json' => json_encode($overlayRefs, JSON_UNESCAPED_UNICODE),
            'sections_json' => json_encode($sections, JSON_UNESCAPED_UNICODE),
            'created_by' => $userId,
        ]);
        $this->repo->logActivity($tenantId, (int) $op['id'], $userId, 'order_created', $title);

        return ['ok' => true];
    }

    public function publishOrder(int $tenantId, int $orderId, int $userId): array
    {
        // tenant-scoped via overlay lookup through list
        $this->repo->updateOrder($tenantId, $orderId, [
            'workflow' => 'published',
            'published_version' => 1,
        ]);
        $this->discordNotify(
            $tenantId,
            DiscordWebhookCatalog::KEY_ORDER_PUBLISHED,
            'Un ordre d’opération a été publié.'
        );

        return ['ok' => true];
    }

    /**
     * @param array<string, mixed> $op
     * @return array<string, mixed>
     */
    public function presentOperation(array $op): array
    {
        $op['status_label'] = OperationLabels::status((string) ($op['status'] ?? 'draft'));
        $op['classification_label'] = OperationLabels::classification((string) ($op['classification'] ?? 'restricted'));
        $op['phase_label'] = trim((string) ($op['phase_name'] ?? '')) !== ''
            ? ((string) ($op['phase_code'] ?? '') . ' — ' . (string) $op['phase_name'])
            : 'Sans phase';

        return $op;
    }

    /**
     * @param array<string, mixed> $ov
     * @return array<string, mixed>
     */
    private function presentOverlay(array $ov): array
    {
        $ov['workflow_label'] = OperationLabels::workflow((string) ($ov['workflow'] ?? 'draft'));
        $ov['visibility_label'] = OperationLabels::visibility((string) ($ov['visibility'] ?? 'staff'));
        $ov['kind_label'] = OperationLabels::overlayKind((string) ($ov['kind'] ?? ''));

        return $ov;
    }

    /**
     * @param array<string, mixed> $obj
     * @return array<string, mixed>
     */
    private function presentObject(array $obj): array
    {
        $geo = json_decode((string) ($obj['geometry_json'] ?? ''), true);
        $obj['geometry'] = is_array($geo) ? $geo : [];
        $graphic = TacticalGraphicsCatalog::find((string) ($obj['graphic_type'] ?? ''));
        $obj['graphic_label'] = $graphic['label'] ?? (string) ($obj['graphic_type'] ?? '');
        $obj['affiliation_label'] = OperationLabels::affiliation((string) ($obj['affiliation'] ?? 'friendly'));
        $obj['status_label'] = OperationLabels::objectStatus((string) ($obj['status'] ?? 'planned'));
        $obj['classification_label'] = OperationLabels::classification((string) ($obj['classification'] ?? 'restricted'));

        return $obj;
    }

    /**
     * @param array<string, mixed> $order
     * @return array<string, mixed>
     */
    private function presentOrder(array $order): array
    {
        $order['kind_label'] = OperationLabels::orderKind((string) ($order['kind'] ?? 'opord'));
        $order['workflow_label'] = OperationLabels::workflow((string) ($order['workflow'] ?? 'draft'));
        $sections = json_decode((string) ($order['sections_json'] ?? ''), true);
        $order['sections'] = is_array($sections) ? $sections : [];
        $refs = json_decode((string) ($order['overlay_refs_json'] ?? ''), true);
        $order['overlay_ids'] = is_array($refs) ? $refs : [];

        return $order;
    }

    private function snapshotOverlay(int $tenantId, int $overlayId, string $workflow, ?string $note, ?int $userId): void
    {
        $overlay = $this->repo->findOverlay($tenantId, $overlayId);
        if ($overlay === null) {
            return;
        }
        $objects = $this->repo->listObjects($tenantId, (int) $overlay['operation_id'], $overlayId);
        $snapshot = json_encode(['objects' => $objects], JSON_UNESCAPED_UNICODE);
        $this->repo->insertOverlayVersion(
            $tenantId,
            $overlayId,
            (int) $overlay['current_version'],
            $workflow,
            $snapshot ?: '{"objects":[]}',
            $note,
            $userId
        );
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function defaultGeometry(string $kind, array $input): array
    {
        $x = (float) ($input['x'] ?? 500);
        $y = (float) ($input['y'] ?? 500);

        return match ($kind) {
            'point' => ['type' => 'point', 'x' => $x, 'y' => $y],
            'line' => ['type' => 'line', 'points' => [[$x, $y], [$x + 80, $y]]],
            'polyline', 'arrow', 'route' => ['type' => 'polyline', 'points' => [[$x, $y], [$x + 60, $y - 40], [$x + 120, $y]]],
            'polygon', 'area' => ['type' => 'polygon', 'points' => [[$x, $y], [$x + 80, $y], [$x + 80, $y + 60], [$x, $y + 60]]],
            'rectangle' => ['type' => 'rectangle', 'x' => $x, 'y' => $y, 'w' => 80, 'h' => 50],
            'circle' => ['type' => 'circle', 'x' => $x, 'y' => $y, 'r' => 40],
            'ellipse' => ['type' => 'ellipse', 'x' => $x, 'y' => $y, 'rx' => 50, 'ry' => 30],
            default => ['type' => $kind, 'x' => $x, 'y' => $y],
        };
    }

    private function safeStatus(string $v): string
    {
        return in_array($v, ['draft', 'planned', 'active', 'paused', 'closed'], true) ? $v : 'draft';
    }

    private function safeClass(string $v): string
    {
        return in_array($v, ['unclassified', 'restricted', 'confidential', 'secret'], true) ? $v : 'restricted';
    }

    private function safeAffiliation(string $v): string
    {
        return in_array($v, ['friendly', 'hostile', 'neutral', 'unknown'], true) ? $v : 'friendly';
    }

    private function safeWorkflow(string $v): string
    {
        return in_array($v, ['draft', 'review', 'approved', 'published'], true) ? $v : 'draft';
    }

    private function discordNotify(int $tenantId, string $key, string $message): void
    {
        try {
            $this->discord?->notify($tenantId, $key, $message);
        } catch (\Throwable) {
        }
    }
}
