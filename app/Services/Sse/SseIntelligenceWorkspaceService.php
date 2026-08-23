<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Database;
use App\Repositories\SseAuditLogRepository;
use App\Repositories\SseCaseRepository;
use App\Repositories\SseDigitalLabRepository;
use App\Repositories\SseEntityIndexRepository;
use App\Repositories\SseIntelEventRepository;
use App\Repositories\SseInterestCaseRepository;
use App\Repositories\SsePersonRepository;
use App\Repositories\SseSuggestionQueueRepository;
use App\Support\SseWorkspaceUi;

/**
 * Intelligence Workspace LOT 2 — inbox, chemise, timeline, graph, recherche.
 */
final class SseIntelligenceWorkspaceService
{
    /** @var list<string> */
    public const LIFECYCLE = [
        'BROUILLON', 'COLLECTE', 'A_EXPLOITER', 'EN_ANALYSE', 'A_VALIDER',
        'VALIDE', 'DIFFUSE', 'CLOS', 'ARCHIVE',
    ];

    public function __construct(
        private ?SseIntelFoundationService $foundation = null,
        private ?SseEntityIndexRepository $entities = null,
        private ?SseIntelEventRepository $events = null,
        private ?SseCaseRepository $cases = null,
        private ?SseInterestCaseRepository $interest = null,
        private ?SseSuggestionQueueRepository $suggestions = null,
        private ?SseDigitalLabRepository $digitalLab = null,
        private ?SseWorkspaceService $tower = null,
        private ?SsePersonRepository $persons = null,
        private ?SseAuditLogRepository $audit = null,
        private ?SseAnalyticalEngineService $engine = null,
        private ?SseIntelCycleService $cycle = null,
        private ?SseAnalysisService $analysis = null,
        private ?SseSyncService $sync = null,
        private ?Database $db = null,
    ) {
        $this->foundation ??= new SseIntelFoundationService();
        $this->entities ??= new SseEntityIndexRepository();
        $this->events ??= new SseIntelEventRepository();
        $this->cases ??= new SseCaseRepository();
        $this->interest ??= new SseInterestCaseRepository();
        $this->suggestions ??= new SseSuggestionQueueRepository();
        $this->digitalLab ??= new SseDigitalLabRepository();
        $this->tower ??= new SseWorkspaceService();
        $this->persons ??= new SsePersonRepository();
        $this->audit ??= new SseAuditLogRepository();
        $this->engine ??= new SseAnalyticalEngineService();
        $this->cycle ??= new SseIntelCycleService();
        $this->analysis ??= new SseAnalysisService();
        $this->sync ??= new SseSyncService();
        $this->db ??= Database::getInstance();
    }

    /**
     * @param list<int>|null $caseScope
     * @return array<string, mixed>
     */
    public function workspacePayload(int $tenantId, ?array $caseScope = null, ?int $caseId = null, ?int $userId = null): array
    {
        if ($tenantId > 0) {
            $this->foundation->backfillTenant($tenantId, 60);
        }

        $lastSeen = $userId !== null && $userId > 0
            ? $this->getAnalystCursor($tenantId, $userId)
            : null;

        $inbox = SseWorkspaceUi::collapseInbox($this->buildInbox($tenantId, $lastSeen));
        $timeline = $this->events->listForTenant($tenantId, [
            'limit' => 50,
            'case_id' => $caseId,
        ]);
        if ($timeline === []) {
            $timeline = $this->fallbackTimeline($tenantId, $caseScope);
        }
        $timeline = SseWorkspaceUi::collapseTimeline($timeline);
        $relations = $this->foundation->listRelations($tenantId, [
            'limit' => 40,
            'case_id' => $caseId,
        ]);
        $entities = $this->entities->search($tenantId, ['limit' => 30]);
        $cases = $this->listCaseSummaries($tenantId, $caseScope);
        $graph = $this->buildGraph($tenantId, null, 2, $caseId);
        $folder = null;
        $context = $entities[0] ?? ($cases[0] ?? null);

        if ($caseId !== null && $caseId > 0) {
            $folder = $this->caseFolder($tenantId, $caseId);
            if ($folder !== null) {
                $context = $folder['header'] ?? $context;
                $timeline = SseWorkspaceUi::collapseTimeline($folder['timeline'] ?? $timeline);
                $relations = $folder['relations'] ?? $relations;
            }
        }

        if ($userId !== null && $userId > 0) {
            $this->touchAnalystCursor($tenantId, $userId);
        }

        $cycle = [];
        try {
            $cycle = $this->cycle->cycleBoard($tenantId, $caseId);
        } catch (\Throwable) {
            $cycle = ['counts' => [], 'requirements' => [], 'taskings' => [], 'products' => [], 'catalog' => []];
        }

        $analysis = [];
        try {
            $analysis = $this->analysis->analysisBoard($tenantId, $caseId);
        } catch (\Throwable) {
            $analysis = [
                'pattern_of_life' => ['summary' => 'Analyse indisponible.', 'events_count' => 0],
                'heatmap' => ['cells' => [], 'summary' => ''],
                'contradictions' => [],
                'rapprochements' => [],
                'anomalies' => [],
                'findings' => [],
                'counts' => [],
                'catalog' => [],
            ];
        }

        $liaison = [];
        try {
            $liaison = $this->sync->monitorSnapshot($tenantId);
        } catch (\Throwable) {
            $liaison = [
                'status' => 'indisponible',
                'status_label' => 'Service indisponible',
                'liaison_label' => 'Liaison indisponible',
                'file_attente' => 0,
                'echecs' => 0,
                'conflits' => 0,
            ];
        }

        return [
            'inbox' => $inbox,
            'timeline' => $timeline,
            'relations' => $relations,
            'entities' => $entities,
            'cases' => $cases,
            'graph' => $graph,
            'folder' => $folder,
            'context' => $context,
            'cycle' => $cycle,
            'analysis' => $analysis,
            'liaison' => $liaison,
            'contradictions' => $analysis['contradictions'] ?? [],
            'selected_case_id' => $caseId,
            'counts' => [
                'inbox' => count($inbox),
                'timeline' => count($timeline),
                'relations' => count($relations),
                'entities' => count($entities),
                'cases' => count($cases),
                'graph_nodes' => count($graph['nodes'] ?? []),
                'requirements_open' => (int) ($cycle['counts']['requirements'] ?? 0),
                'taskings_open' => (int) ($cycle['counts']['taskings_open'] ?? 0),
                'products_pending' => (int) ($cycle['counts']['products_pending'] ?? 0),
                'contradictions' => (int) ($analysis['counts']['contradictions'] ?? 0),
                'anomalies' => (int) ($analysis['counts']['anomalies'] ?? 0),
                'rapprochements' => (int) ($analysis['counts']['rapprochements'] ?? 0),
                'file_attente' => (int) ($liaison['file_attente'] ?? 0),
                'conflits_sync' => (int) ($liaison['conflits'] ?? 0),
            ],
            'lifecycle_options' => $this->lifecycleOptions(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function apiSummary(int $tenantId): array
    {
        $payload = $this->workspacePayload($tenantId, null);

        return [
            'counts' => $payload['counts'],
            'inbox' => array_slice($payload['inbox'], 0, 15),
            'timeline' => array_slice($payload['timeline'], 0, 15),
            'relations_proposed' => array_values(array_filter(
                $payload['relations'],
                static fn (array $r): bool => ($r['status'] ?? '') === 'proposed'
            )),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function inbox(int $tenantId, ?int $userId = null): array
    {
        $lastSeen = $userId !== null && $userId > 0
            ? $this->getAnalystCursor($tenantId, $userId)
            : null;

        return SseWorkspaceUi::collapseInbox($this->buildInbox($tenantId, $lastSeen));
    }

    /**
     * @return array{ok:bool,message?:string}
     */
    public function decideInboxItem(int $tenantId, string $kind, int $id, string $decision, string $authorLabel, ?int $userId = null): array
    {
        if ($kind === 'suggestion') {
            if ($decision === 'accept') {
                $r = $this->engine->acceptSuggestion($tenantId, $id, $authorLabel, $userId);

                return ['ok' => (bool) ($r['ok'] ?? false), 'message' => $r['error'] ?? 'Rapprochement validé.'];
            }
            if ($decision === 'reject') {
                $r = $this->engine->rejectSuggestion($tenantId, $id, $authorLabel, $userId);

                return ['ok' => (bool) ($r['ok'] ?? false), 'message' => $r['error'] ?? 'Proposition rejetée.'];
            }
        }

        if ($kind === 'relation' && $decision === 'reject') {
            $ok = $this->softDeleteRelation($tenantId, $id, $authorLabel, 'Rejet depuis l’inbox');

            return ['ok' => $ok, 'message' => $ok ? 'Relation écartée.' : 'Impossible d’écarter la relation.'];
        }

        if ($decision === 'dismiss') {
            $this->foundation->audit([
                'tenant_id' => $tenantId,
                'actor_user_id' => $userId,
                'actor_label' => $authorLabel,
                'action' => 'inbox.dismiss',
                'object_type' => $kind,
                'object_id' => $id,
                'reason' => 'Ignoré depuis l’inbox',
            ]);

            return ['ok' => true, 'message' => 'Élément ignoré (journalisé).'];
        }

        return ['ok' => false, 'message' => 'Décision non prise en charge.'];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function caseFolder(int $tenantId, int $caseId): ?array
    {
        $case = $this->cases->findById($caseId, $tenantId);
        if ($case === null || !empty($case['is_folder'])) {
            return null;
        }

        $linked = $this->cases->listLinkedPersonIds($caseId, $tenantId);
        $people = [];
        foreach ($linked as $link) {
            $pid = (int) ($link['person_id'] ?? 0);
            if ($pid < 1) {
                continue;
            }
            $p = $this->persons->findById($pid, $tenantId);
            if ($p === null) {
                continue;
            }
            $idx = $this->entities->findBySource($tenantId, 'sse_persons', $pid);
            $people[] = [
                'id' => $pid,
                'display_name' => (string) ($p['display_name'] ?? ''),
                'uuid' => $idx['uuid'] ?? null,
                'identity_tier_label' => $idx['identity_tier_label'] ?? null,
                'confidence_code' => $idx['confidence_code'] ?? null,
                'href' => url('atak/sse/identites/' . $pid),
                'note' => $link['note'] ?? null,
            ];
        }

        $notes = $this->cases->listNotes($caseId, $tenantId);
        $evidence = $this->cases->listEvidence($caseId, $tenantId);
        $timeline = $this->events->listForTenant($tenantId, ['case_id' => $caseId, 'limit' => 60]);
        $relations = $this->foundation->listRelations($tenantId, ['case_id' => $caseId, 'limit' => 50]);
        $audit = $this->audit->listForTenant($tenantId, [
            'object_type' => 'case',
            'object_id' => $caseId,
            'limit' => 30,
        ]);

        $lifecycle = (string) ($case['lifecycle_status'] ?? '');

        return [
            'header' => [
                'id' => $caseId,
                'reference_code' => (string) ($case['reference_code'] ?? ''),
                'title' => (string) ($case['title'] ?? ''),
                'summary' => (string) ($case['summary'] ?? ''),
                'lifecycle_status' => $lifecycle,
                'lifecycle_label' => $this->lifecycleLabel($lifecycle),
                'classification' => (string) ($case['classification'] ?? ''),
                'priority' => (string) ($case['priority'] ?? 'normale'),
                'producing_unit' => $case['producing_unit'] ?? null,
                'confidence_note' => $case['confidence_note'] ?? null,
                'last_activity_at' => $case['last_activity_at'] ?? $case['updated_at'] ?? null,
                'href' => url('atak/sse/dossiers/' . $caseId),
                'entity_type' => 'case',
            ],
            'entities' => $people,
            'notes' => $notes,
            'evidence' => $evidence,
            'timeline' => $timeline,
            'relations' => $relations,
            'audit' => $audit,
            'cycle' => $this->cycle->cycleBoard($tenantId, $caseId),
            'graph' => $this->buildGraph($tenantId, null, 2, $caseId),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{ok:bool,message?:string}
     */
    public function updateCaseMeta(int $tenantId, int $caseId, array $data, string $authorLabel, ?int $userId = null): array
    {
        $before = $this->cases->findById($caseId, $tenantId);
        if ($before === null) {
            return ['ok' => false, 'message' => 'Dossier introuvable.'];
        }

        $patch = [];
        if (isset($data['lifecycle_status'])) {
            $ls = strtoupper(trim((string) $data['lifecycle_status']));
            if (!in_array($ls, self::LIFECYCLE, true)) {
                return ['ok' => false, 'message' => 'État de cycle non reconnu.'];
            }
            $patch['lifecycle_status'] = $ls;
        }
        if (isset($data['priority'])) {
            $patch['priority'] = trim((string) $data['priority']) ?: 'normale';
        }
        if (array_key_exists('producing_unit', $data)) {
            $patch['producing_unit'] = $data['producing_unit'];
        }
        if (array_key_exists('confidence_note', $data)) {
            $patch['confidence_note'] = $data['confidence_note'];
        }
        if (array_key_exists('analyst_user_id', $data)) {
            $patch['analyst_user_id'] = $data['analyst_user_id'];
        }
        $patch['last_activity_at'] = gmdate('Y-m-d H:i:s');

        try {
            $ok = $this->cases->update($caseId, $tenantId, $patch);
        } catch (\Throwable) {
            return ['ok' => false, 'message' => 'Mise à jour impossible (schéma ou droits).'];
        }
        if (!$ok) {
            return ['ok' => false, 'message' => 'Aucune modification appliquée.'];
        }

        $this->foundation->audit([
            'tenant_id' => $tenantId,
            'actor_user_id' => $userId,
            'actor_label' => $authorLabel,
            'action' => 'case.meta_update',
            'object_type' => 'case',
            'object_id' => $caseId,
            'reason' => 'Mise à jour chemise Workspace',
            'before' => [
                'lifecycle_status' => $before['lifecycle_status'] ?? null,
                'priority' => $before['priority'] ?? null,
            ],
            'after' => $patch,
        ]);
        $this->foundation->syncCase($tenantId, array_merge($before, $patch));

        return ['ok' => true];
    }

    /**
     * @return array{nodes:list<array<string,mixed>>,edges:list<array<string,mixed>>}
     */
    public function buildGraph(int $tenantId, ?string $rootUuid = null, int $depth = 2, ?int $caseId = null): array
    {
        $depth = max(1, min(3, $depth));
        $relations = $this->foundation->listRelations($tenantId, [
            'case_id' => $caseId,
            'limit' => 120,
        ]);

        $needed = [];
        foreach ($relations as $rel) {
            $ft = (string) ($rel['from_type'] ?? 'person');
            $tt = (string) ($rel['to_type'] ?? 'person');
            $needed[$ft . ':' . (int) ($rel['from_id'] ?? 0)] = true;
            $needed[$tt . ':' . (int) ($rel['to_id'] ?? 0)] = true;
        }

        $nodeMap = [];
        foreach (array_keys($needed) as $key) {
            [$type, $idStr] = explode(':', $key, 2);
            $sourceTable = match ($type) {
                'person' => 'sse_persons',
                'site' => 'sse_sites',
                'case' => 'sse_cases',
                'interest_case' => 'sse_interest_cases',
                default => '',
            };
            if ($sourceTable === '') {
                continue;
            }
            $ent = $this->entities->findBySource($tenantId, $sourceTable, (int) $idStr);
            if ($ent === null) {
                // Nœud minimal sans index
                $uuid = 'local-' . $type . '-' . $idStr;
                $nodeMap[$uuid] = [
                    'id' => $uuid,
                    'uuid' => $uuid,
                    'label' => ucfirst($type) . ' #' . $idStr,
                    'entity_type' => $type,
                    'source_id' => (int) $idStr,
                    'confidence_code' => 'F6',
                    'status' => '',
                ];
                continue;
            }
            $nodeMap[$ent['uuid']] = [
                'id' => $ent['uuid'],
                'uuid' => $ent['uuid'],
                'label' => (string) ($ent['display_label'] ?? ''),
                'entity_type' => (string) ($ent['entity_type'] ?? $type),
                'source_id' => (int) ($ent['source_id'] ?? 0),
                'source_table' => (string) ($ent['source_table'] ?? ''),
                'confidence_code' => (string) ($ent['confidence_code'] ?? 'F6'),
                'identity_tier' => $ent['identity_tier'] ?? null,
                'status' => (string) ($ent['status'] ?? ''),
            ];
        }

        // Si peu de relations, injecter des entités index récentes
        if (count($nodeMap) < 3) {
            foreach ($this->entities->search($tenantId, ['limit' => 12]) as $ent) {
                $nodeMap[$ent['uuid']] = [
                    'id' => $ent['uuid'],
                    'uuid' => $ent['uuid'],
                    'label' => (string) ($ent['display_label'] ?? ''),
                    'entity_type' => (string) ($ent['entity_type'] ?? ''),
                    'source_id' => (int) ($ent['source_id'] ?? 0),
                    'source_table' => (string) ($ent['source_table'] ?? ''),
                    'confidence_code' => (string) ($ent['confidence_code'] ?? 'F6'),
                    'identity_tier' => $ent['identity_tier'] ?? null,
                    'status' => (string) ($ent['status'] ?? ''),
                ];
            }
        }

        $resolveUuid = function (string $type, int $id) use ($tenantId, $nodeMap): ?string {
            $sourceTable = match ($type) {
                'person' => 'sse_persons',
                'site' => 'sse_sites',
                'case' => 'sse_cases',
                'interest_case' => 'sse_interest_cases',
                default => '',
            };
            if ($sourceTable !== '') {
                $ent = $this->entities->findBySource($tenantId, $sourceTable, $id);
                if ($ent !== null) {
                    return (string) $ent['uuid'];
                }
            }
            $local = 'local-' . $type . '-' . $id;

            return isset($nodeMap[$local]) ? $local : $local;
        };

        $edges = [];
        foreach ($relations as $rel) {
            $from = $resolveUuid((string) ($rel['from_type'] ?? 'person'), (int) ($rel['from_id'] ?? 0));
            $to = $resolveUuid((string) ($rel['to_type'] ?? 'person'), (int) ($rel['to_id'] ?? 0));
            if ($from === null || $to === null) {
                continue;
            }
            $edges[] = [
                'id' => (int) ($rel['id'] ?? 0),
                'from' => $from,
                'to' => $to,
                'relation' => (string) ($rel['relation'] ?? ''),
                'status' => (string) ($rel['status'] ?? 'confirmed'),
                'proposed' => (($rel['status'] ?? '') === 'proposed'),
                'reliability' => (string) ($rel['reliability'] ?? 'unverified'),
            ];
        }

        // Expansion profondeur depuis root
        if ($rootUuid !== null && $rootUuid !== '' && isset($nodeMap[$rootUuid])) {
            $keep = [$rootUuid => true];
            $frontier = [$rootUuid];
            for ($d = 0; $d < $depth; $d++) {
                $next = [];
                foreach ($edges as $e) {
                    $a = (string) $e['from'];
                    $b = (string) $e['to'];
                    if (isset($keep[$a]) && !isset($keep[$b])) {
                        $keep[$b] = true;
                        $next[] = $b;
                    } elseif (isset($keep[$b]) && !isset($keep[$a])) {
                        $keep[$a] = true;
                        $next[] = $a;
                    }
                }
                $frontier = $next;
                if ($frontier === []) {
                    break;
                }
            }
            $nodeMap = array_filter($nodeMap, static fn (array $n): bool => isset($keep[(string) $n['uuid']]));
            $edges = array_values(array_filter(
                $edges,
                static fn (array $e): bool => isset($keep[(string) $e['from']]) && isset($keep[(string) $e['to']])
            ));
        }

        return [
            'nodes' => array_values($nodeMap),
            'edges' => $edges,
            'depth' => $depth,
            'root' => $rootUuid,
        ];
    }

    /**
     * @return array{groups:array<string,list<array<string,mixed>>>,count:int}
     */
    public function universalSearch(int $tenantId, string $q, ?array $caseScope = null): array
    {
        $q = trim($q);
        $groups = [
            'personnes' => [],
            'sites' => [],
            'dossiers' => [],
            'pistes' => [],
            'evenements' => [],
            'autres' => [],
        ];
        if ($q === '' || mb_strlen($q) < 2) {
            return ['groups' => $groups, 'count' => 0];
        }

        $norm = $this->normalizeSearch($q);
        foreach ($this->entities->search($tenantId, ['q' => $q, 'limit' => 40]) as $ent) {
            $item = [
                'uuid' => $ent['uuid'],
                'label' => (string) ($ent['display_label'] ?? ''),
                'ref' => (string) ($ent['reference_code'] ?? ''),
                'type' => (string) ($ent['entity_type'] ?? ''),
                'confidence_code' => (string) ($ent['confidence_code'] ?? ''),
                'href' => $this->entityHref($ent),
                'hint' => (string) ($ent['identity_tier_label'] ?? ''),
            ];
            $bucket = match ((string) ($ent['entity_type'] ?? '')) {
                'person' => 'personnes',
                'site' => 'sites',
                'case' => 'dossiers',
                'interest_case' => 'pistes',
                default => 'autres',
            };
            $groups[$bucket][] = $item;
        }

        // Complément dossiers / DI si peu de hits index
        if ($groups['dossiers'] === []) {
            foreach ($this->listCaseSummaries($tenantId, $caseScope) as $c) {
                $hay = $this->normalizeSearch(($c['reference_code'] ?? '') . ' ' . ($c['title'] ?? ''));
                if ($hay !== '' && (str_contains($hay, $norm) || similar_text($hay, $norm) > 4)) {
                    $groups['dossiers'][] = [
                        'label' => trim(($c['reference_code'] ?? '') . ' ' . ($c['title'] ?? '')),
                        'ref' => (string) ($c['reference_code'] ?? ''),
                        'type' => 'dossier',
                        'href' => (string) ($c['href'] ?? '#'),
                        'hint' => (string) ($c['lifecycle_label'] ?? ''),
                    ];
                }
            }
        }

        foreach ($this->events->listForTenant($tenantId, ['limit' => 80]) as $ev) {
            $hay = $this->normalizeSearch(($ev['summary'] ?? '') . ' ' . ($ev['event_uuid'] ?? ''));
            if ($hay !== '' && str_contains($hay, $norm)) {
                $groups['evenements'][] = [
                    'label' => (string) ($ev['summary'] ?? $ev['event_type_label'] ?? 'Événement'),
                    'ref' => (string) ($ev['confidence_code'] ?? ''),
                    'type' => (string) ($ev['event_type_label'] ?? ''),
                    'href' => url('atak/sse/workspace') . '#timeline',
                    'hint' => (string) ($ev['source_system_label'] ?? ''),
                ];
            }
            if (count($groups['evenements']) >= 10) {
                break;
            }
        }

        $count = 0;
        foreach ($groups as $list) {
            $count += count($list);
        }

        return ['groups' => $groups, 'count' => $count, 'q' => $q];
    }

    public function softDeleteRelation(int $tenantId, int $id, string $authorLabel, string $reason): bool
    {
        try {
            $n = $this->db->execute(
                'UPDATE sse_relations
                 SET status = \'deleted\', deleted_at = UTC_TIMESTAMP(), justification = :j
                 WHERE id = :id AND tenant_id = :t AND (deleted_at IS NULL OR deleted_at = \'0000-00-00 00:00:00\')',
                ['id' => $id, 't' => $tenantId, 'j' => mb_substr($reason, 0, 500)]
            );
            if ($n > 0) {
                $this->foundation->audit([
                    'tenant_id' => $tenantId,
                    'actor_label' => $authorLabel,
                    'action' => 'relation.delete',
                    'object_type' => 'relation',
                    'object_id' => $id,
                    'reason' => $reason,
                ]);
            }

            return $n > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param list<int>|null $caseScope
     * @return list<array<string, mixed>>
     */
    private function listCaseSummaries(int $tenantId, ?array $caseScope): array
    {
        $cases = [];
        foreach ($this->cases->listForTenant($tenantId, $caseScope) as $c) {
            if (!empty($c['is_folder'])) {
                continue;
            }
            $cases[] = [
                'id' => (int) ($c['id'] ?? 0),
                'entity_type' => 'CASE',
                'entity_type_label' => 'Dossier',
                'icon' => 'folder',
                'reference_code' => (string) ($c['reference_code'] ?? ''),
                'title' => (string) ($c['title'] ?? ''),
                'lifecycle_status' => (string) ($c['lifecycle_status'] ?? ''),
                'lifecycle_label' => $this->lifecycleLabel((string) ($c['lifecycle_status'] ?? '')),
                'status' => (string) ($c['status'] ?? ''),
                'classification' => (string) ($c['classification'] ?? ''),
                'priority' => (string) ($c['priority'] ?? 'normale'),
                'last_activity_at' => $c['last_activity_at'] ?? $c['updated_at'] ?? null,
                'href' => url('atak/sse/workspace') . '?case=' . (int) ($c['id'] ?? 0),
                'full_href' => url('atak/sse/dossiers/' . (int) ($c['id'] ?? 0)),
            ];
            if (count($cases) >= 30) {
                break;
            }
        }

        return $cases;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildInbox(int $tenantId, ?string $lastSeen): array
    {
        $items = [];

        foreach ($this->interest->listForTenant($tenantId, []) as $row) {
            $st = (string) ($row['status'] ?? '');
            if (!in_array($st, ['ouvert', 'en_analyse', 'en_collecte', 'en_correlation', ''], true)
                && !str_contains($st, 'attente')
                && !str_contains($st, 'correl')) {
                continue;
            }
            $items[] = [
                'kind' => 'interest_case',
                'kind_label' => 'Dossier d’intérêt',
                'icon' => 'folder',
                'id' => (int) ($row['id'] ?? 0),
                'title' => trim((string) ($row['reference_code'] ?? '') . ' ' . (string) ($row['temporary_designation'] ?? ''))
                    ?: 'Piste sans désignation',
                'detail' => (string) ($row['suspected_alias'] ?? $row['status_label'] ?? ''),
                'updated_at' => $row['updated_at'] ?? $row['created_at'] ?? null,
                'href' => url('atak/sse/interet/' . (int) ($row['id'] ?? 0)),
                'tone' => 'warn',
                'actions' => ['open'],
            ];
            if (count($items) >= 12) {
                break;
            }
        }

        try {
            foreach ($this->suggestions->listSuggestions($tenantId, ['status' => 'pending', 'limit' => 12]) as $s) {
                $items[] = [
                    'kind' => 'suggestion',
                    'kind_label' => 'Rapprochement proposé',
                    'icon' => 'link',
                    'id' => (int) ($s['id'] ?? 0),
                    'title' => (string) ($s['title'] ?? 'Suggestion'),
                    'detail' => (string) ($s['reason'] ?? ''),
                    'updated_at' => $s['updated_at'] ?? $s['created_at'] ?? null,
                    'href' => url('atak/sse/rapprochements'),
                    'tone' => 'danger',
                    'actions' => ['accept', 'reject', 'open'],
                ];
            }
        } catch (\Throwable) {
        }

        foreach ($this->foundation->listRelations($tenantId, ['status' => 'proposed', 'limit' => 10]) as $rel) {
            $items[] = [
                'kind' => 'relation',
                'kind_label' => 'Relation proposée',
                'icon' => 'graph',
                'id' => (int) ($rel['id'] ?? 0),
                'title' => sprintf(
                    '%s → %s → %s',
                    SseWorkspaceUi::entityTypeLabel((string) ($rel['from_type'] ?? '')),
                    SseWorkspaceUi::relationLabel((string) ($rel['relation'] ?? '')),
                    SseWorkspaceUi::entityTypeLabel((string) ($rel['to_type'] ?? ''))
                ),
                'detail' => (string) ($rel['justification'] ?? $rel['note'] ?? 'À confirmer par un analyste'),
                'updated_at' => $rel['created_at'] ?? null,
                'href' => url('atak/sse/workspace') . '#graph',
                'tone' => 'warn',
                'actions' => ['reject', 'open'],
            ];
        }

        try {
            foreach ($this->digitalLab->listAcquisitions($tenantId, ['limit' => 8]) as $a) {
                $st = strtolower((string) ($a['status'] ?? ''));
                if (in_array($st, ['closed', 'archived', 'clos', 'archive'], true)) {
                    continue;
                }
                $items[] = [
                    'kind' => 'acquisition',
                    'kind_label' => 'Acquisition numérique',
                    'icon' => 'device',
                    'id' => (int) ($a['id'] ?? 0),
                    'title' => trim((string) ($a['device_reference'] ?? '') . ' — ' . (string) ($a['method_label'] ?? 'Acquisition'))
                        ?: ('Acquisition #' . (int) ($a['id'] ?? 0)),
                    'detail' => (string) ($a['status_label'] ?? $a['status'] ?? ''),
                    'updated_at' => $a['updated_at'] ?? $a['created_at'] ?? null,
                    'href' => url('atak/sse/exploitation-numerique'),
                    'tone' => 'ok',
                    'actions' => ['open'],
                ];
            }
        } catch (\Throwable) {
        }

        try {
            foreach ($this->analysis->openContradictionsForInbox($tenantId) as $cx) {
                $items[] = [
                    'kind' => 'contradiction',
                    'kind_label' => 'Contradiction',
                    'icon' => 'alert',
                    'id' => (int) ($cx['id'] ?? 0),
                    'title' => (string) ($cx['title'] ?? 'Contradiction'),
                    'detail' => (string) ($cx['explanation'] ?? ''),
                    'updated_at' => $cx['updated_at'] ?? $cx['created_at'] ?? null,
                    'href' => url('atak/sse/workspace') . '#analyse',
                    'tone' => match ((string) ($cx['severity'] ?? '')) {
                        'critique', 'haute' => 'danger',
                        'normale' => 'warn',
                        default => '',
                    },
                    'actions' => ['open'],
                ];
            }
        } catch (\Throwable) {
        }

        if ($lastSeen !== null) {
            foreach ($this->events->listForTenant($tenantId, ['since' => $lastSeen, 'limit' => 8]) as $ev) {
                $items[] = [
                    'kind' => 'event',
                    'kind_label' => 'Nouveau depuis votre dernière visite',
                    'icon' => SseWorkspaceUi::iconForInboxKind(
                        'event',
                        (string) ($ev['summary'] ?? ''),
                        (string) ($ev['event_type'] ?? '')
                    ),
                    'id' => (int) ($ev['id'] ?? 0),
                    'title' => (string) ($ev['summary'] ?? $ev['event_type_label'] ?? 'Événement'),
                    'detail' => (string) ($ev['source_system_label'] ?? ''),
                    'updated_at' => $ev['event_time'] ?? null,
                    'href' => url('atak/sse/workspace') . '#timeline',
                    'tone' => 'ok',
                    'actions' => ['open'],
                ];
            }
        }

        usort($items, static function (array $a, array $b): int {
            if (!empty($a['placeholder'])) {
                return 1;
            }
            if (!empty($b['placeholder'])) {
                return -1;
            }

            return strcmp((string) ($b['updated_at'] ?? ''), (string) ($a['updated_at'] ?? ''));
        });

        return array_slice($items, 0, 30);
    }

    private function getAnalystCursor(int $tenantId, int $userId): ?string
    {
        try {
            $row = $this->db->fetchOne(
                'SELECT last_seen_at FROM sse_analyst_cursors WHERE tenant_id = :t AND user_id = :u LIMIT 1',
                ['t' => $tenantId, 'u' => $userId]
            );

            return $row['last_seen_at'] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function touchAnalystCursor(int $tenantId, int $userId): void
    {
        try {
            $this->db->execute(
                'INSERT INTO sse_analyst_cursors (tenant_id, user_id, last_seen_at)
                 VALUES (:t, :u, UTC_TIMESTAMP())
                 ON DUPLICATE KEY UPDATE last_seen_at = UTC_TIMESTAMP()',
                ['t' => $tenantId, 'u' => $userId]
            );
        } catch (\Throwable) {
        }
    }

    /**
     * @param array<string, mixed> $ent
     */
    private function entityHref(array $ent): string
    {
        $table = (string) ($ent['source_table'] ?? '');
        $id = (int) ($ent['source_id'] ?? 0);

        return match ($table) {
            'sse_persons' => url('atak/sse/identites/' . $id),
            'sse_sites' => url('atak/sse/sites/' . $id),
            'sse_cases' => url('atak/sse/workspace') . '?case=' . $id,
            'sse_interest_cases' => url('atak/sse/interet/' . $id),
            default => url('atak/sse/workspace'),
        };
    }

    private function normalizeSearch(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $map = ['à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c'];

        return strtr($s, $map);
    }

    /**
     * @return array<string, string>
     */
    public function lifecycleOptions(): array
    {
        $out = [];
        foreach (self::LIFECYCLE as $k) {
            $out[$k] = $this->lifecycleLabel($k);
        }

        return $out;
    }

    /**
     * @param list<int>|null $caseScope
     * @return list<array<string, mixed>>
     */
    private function fallbackTimeline(int $tenantId, ?array $caseScope): array
    {
        try {
            $tower = $this->tower->controlTower($tenantId, $caseScope);
            $activity = is_array($tower['activity'] ?? null) ? $tower['activity'] : [];
            $out = [];
            foreach ($activity as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $out[] = [
                    'event_uuid' => '',
                    'event_type' => 'OBSERVED',
                    'event_type_label' => (string) ($row['label'] ?? $row['type'] ?? 'Activité'),
                    'icon' => 'event',
                    'source_system' => 'MANUAL',
                    'source_system_label' => 'Portail',
                    'summary' => (string) ($row['detail'] ?? $row['text'] ?? $row['title'] ?? ''),
                    'event_time' => (string) ($row['at'] ?? $row['time'] ?? $row['created_at'] ?? ''),
                    'event_time_label' => \App\Support\SseWorkspaceUi::formatEventTime((string) ($row['at'] ?? $row['time'] ?? $row['created_at'] ?? '')),
                    'confidence_code' => 'F6',
                    'author_label' => $row['author'] ?? null,
                ];
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    public function lifecycleLabel(string $status): string
    {
        return match (strtoupper($status)) {
            'BROUILLON' => 'Brouillon',
            'COLLECTE' => 'Collecte',
            'A_EXPLOITER' => 'À exploiter',
            'EN_ANALYSE' => 'En analyse',
            'A_VALIDER' => 'À valider',
            'VALIDE' => 'Validé',
            'DIFFUSE' => 'Diffusé',
            'CLOS' => 'Clos',
            'ARCHIVE' => 'Archivé',
            default => \App\Support\SseWorkspaceUi::humanizeCode($status, 'Non défini'),
        };
    }
}
