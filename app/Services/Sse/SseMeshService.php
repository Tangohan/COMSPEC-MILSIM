<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseMeshRepository;

/**
 * Assemble une toile depuis le graphe de corrélations d’un dossier.
 */
final class SseMeshService
{
    public function __construct(
        private ?SseMeshRepository $meshes = null,
        private ?SseCorrelationService $correlation = null,
    ) {
        $this->meshes ??= new SseMeshRepository();
        $this->correlation ??= new SseCorrelationService();
    }

    /**
     * Remplit une toile vide avec les nœuds / liens déduits du dossier.
     *
     * @return array{nodes:int, edges:int}
     */
    public function seedFromCase(int $meshId, int $caseId, int $tenantId): array
    {
        $graph = $this->correlation->graphForCase($caseId, $tenantId);
        $map = []; // "type:id" => node_id
        $i = 0;
        $cx = 420.0;
        $cy = 280.0;
        $nCount = max(1, count($graph['nodes']));
        foreach ($graph['nodes'] as $key => $node) {
            $angle = (2 * M_PI * $i) / $nCount;
            $radius = 40 + min(220, 28 * $nCount);
            $kind = match ((string) ($node['type'] ?? '')) {
                'person' => 'person',
                'site' => 'site',
                'seizure' => 'seizure',
                'evidence' => 'seizure',
                'document' => 'document',
                'room' => 'site',
                default => 'custom',
            };
            $meta = [];
            if ($kind === 'person' && (int) ($node['id'] ?? 0) > 0) {
                $photoPath = $this->primaryPersonPhotoPath((int) $node['id'], $tenantId);
                if ($photoPath !== null) {
                    $meta['image_path'] = $photoPath;
                }
            }
            $nid = $this->meshes->addNode($meshId, $tenantId, [
                'kind' => $kind,
                'label' => (string) ($node['label'] ?? 'Élément'),
                'detail' => trim(((string) ($node['ref'] ?? '')) . ' ' . ((string) ($node['detail'] ?? ''))),
                'meta_json' => $meta !== [] ? SseMeshRepository::encodeMetaJson($meta) : null,
                'ref_type' => (string) ($node['type'] ?? ''),
                'ref_id' => (int) ($node['id'] ?? 0),
                'pos_x' => $cx + cos($angle) * $radius,
                'pos_y' => $cy + sin($angle) * $radius,
            ]);
            $map[$key] = $nid;
            $i++;
        }

        $edgeCount = 0;
        foreach ($graph['edges'] as $edge) {
            $fromKey = $edge['from_type'] . ':' . $edge['from_id'];
            $toKey = $edge['to_type'] . ':' . $edge['to_id'];
            if (!isset($map[$fromKey], $map[$toKey])) {
                continue;
            }
            $rel = (string) ($edge['relation'] ?? 'associe');
            if (!isset(SseMeshRepository::RELATION_LABELS[$rel])) {
                $rel = 'associe';
            }
            $this->meshes->addEdge($meshId, $tenantId, [
                'from_node_id' => $map[$fromKey],
                'to_node_id' => $map[$toKey],
                'relation' => $rel,
                'note' => (string) ($edge['note'] ?? ''),
                'reliability' => (string) ($edge['reliability'] ?? 'unverified'),
                'author_label' => 'Import dossier',
            ]);
            $edgeCount++;
        }

        return ['nodes' => count($map), 'edges' => $edgeCount];
    }

    /**
     * Fusionne des investigations sources dans une investigation cible.
     * Les entités déjà présentes via la même référence métier sont réutilisées ;
     * les sources sont ensuite archivées.
     *
     * @param list<int> $sourceIds
     * @return array{target_id:int, nodes_added:int, edges_added:int, archived:int, reused:int}
     */
    public function mergeInto(int $targetId, array $sourceIds, int $tenantId, ?int $userId = null): array
    {
        $target = $this->meshes->findById($targetId, $tenantId);
        if ($target === null) {
            throw new \InvalidArgumentException('Investigation cible introuvable.');
        }

        $sources = [];
        foreach (array_values(array_unique(array_map('intval', $sourceIds))) as $sid) {
            if ($sid < 1 || $sid === $targetId) {
                continue;
            }
            $mesh = $this->meshes->findById($sid, $tenantId);
            if ($mesh === null) {
                continue;
            }
            if (in_array((string) ($mesh['status'] ?? ''), ['archive'], true)) {
                // On peut encore regrouper une investigation déjà archivée.
            }
            $sources[] = $mesh;
        }
        if ($sources === []) {
            throw new \InvalidArgumentException('Sélectionnez au moins une autre investigation à regrouper.');
        }

        if (in_array((string) ($target['status'] ?? ''), ['archive', 'clos'], true)) {
            $this->meshes->update($targetId, $tenantId, ['status' => 'en_cours']);
        }

        $targetNodes = $this->meshes->listNodes($targetId, $tenantId);
        $targetEdges = $this->meshes->listEdges($targetId, $tenantId);

        /** @var array<string, int> $refMap */
        $refMap = [];
        $maxX = 80.0;
        foreach ($targetNodes as $node) {
            $refKey = $this->nodeRefKey($node);
            if ($refKey !== null) {
                $refMap[$refKey] = (int) $node['id'];
            }
            $maxX = max($maxX, (float) ($node['pos_x'] ?? 0));
        }

        /** @var array<string, true> $edgeKeys */
        $edgeKeys = [];
        foreach ($targetEdges as $edge) {
            $edgeKeys[$this->edgeKey(
                (int) $edge['from_node_id'],
                (int) $edge['to_node_id'],
                (string) $edge['relation']
            )] = true;
        }

        $nodesAdded = 0;
        $edgesAdded = 0;
        $reused = 0;
        $clusterIndex = 0;
        $mergedLabels = [];

        foreach ($sources as $source) {
            $sourceId = (int) $source['id'];
            $sourceNodes = $this->meshes->listNodes($sourceId, $tenantId);
            $sourceEdges = $this->meshes->listEdges($sourceId, $tenantId);
            $mergedLabels[] = trim((string) ($source['reference_code'] ?? '')) !== ''
                ? (string) $source['reference_code']
                : ('#' . $sourceId);

            $minX = null;
            $minY = null;
            foreach ($sourceNodes as $node) {
                $x = (float) ($node['pos_x'] ?? 0);
                $y = (float) ($node['pos_y'] ?? 0);
                $minX = $minX === null ? $x : min($minX, $x);
                $minY = $minY === null ? $y : min($minY, $y);
            }
            $minX ??= 0.0;
            $minY ??= 0.0;
            $offsetX = $maxX + 200.0 + ($clusterIndex * 480.0);
            $offsetY = 60.0;

            /** @var array<int, int> $idMap old node id => target node id */
            $idMap = [];
            foreach ($sourceNodes as $node) {
                $oldId = (int) ($node['id'] ?? 0);
                if ($oldId < 1) {
                    continue;
                }
                $refKey = $this->nodeRefKey($node);
                if ($refKey !== null && isset($refMap[$refKey])) {
                    $idMap[$oldId] = $refMap[$refKey];
                    $reused++;
                    continue;
                }

                $meta = is_array($node['meta'] ?? null) ? $node['meta'] : [];
                $newId = $this->meshes->addNode($targetId, $tenantId, [
                    'kind' => (string) ($node['kind'] ?? 'custom'),
                    'label' => (string) ($node['label'] ?? 'Élément'),
                    'detail' => (string) ($node['detail'] ?? ''),
                    'meta_json' => $meta !== [] ? SseMeshRepository::encodeMetaJson($meta) : null,
                    'ref_type' => (string) ($node['ref_type'] ?? ''),
                    'ref_id' => (int) ($node['ref_id'] ?? 0),
                    'pos_x' => ((float) ($node['pos_x'] ?? 0) - $minX) + $offsetX,
                    'pos_y' => ((float) ($node['pos_y'] ?? 0) - $minY) + $offsetY,
                ]);
                $idMap[$oldId] = $newId;
                if ($refKey !== null) {
                    $refMap[$refKey] = $newId;
                }
                $nodesAdded++;
                $maxX = max($maxX, ((float) ($node['pos_x'] ?? 0) - $minX) + $offsetX);
            }

            foreach ($sourceEdges as $edge) {
                $fromOld = (int) ($edge['from_node_id'] ?? 0);
                $toOld = (int) ($edge['to_node_id'] ?? 0);
                if (!isset($idMap[$fromOld], $idMap[$toOld])) {
                    continue;
                }
                $fromNew = $idMap[$fromOld];
                $toNew = $idMap[$toOld];
                if ($fromNew === $toNew) {
                    continue;
                }
                $rel = (string) ($edge['relation'] ?? 'associe');
                $key = $this->edgeKey($fromNew, $toNew, $rel);
                if (isset($edgeKeys[$key])) {
                    continue;
                }
                $note = trim((string) ($edge['note'] ?? ''));
                $this->meshes->addEdge($targetId, $tenantId, [
                    'from_node_id' => $fromNew,
                    'to_node_id' => $toNew,
                    'relation' => $rel,
                    'note' => $note,
                    'reliability' => (string) ($edge['reliability'] ?? 'unverified'),
                    'created_by' => $userId,
                    'author_label' => 'Regroupement',
                ]);
                $edgeKeys[$key] = true;
                $edgesAdded++;
            }

            $this->meshes->update($sourceId, $tenantId, ['status' => 'archive']);
            $clusterIndex++;
        }

        $summaryBits = [];
        $existingSummary = trim((string) ($target['summary'] ?? ''));
        if ($existingSummary !== '') {
            $summaryBits[] = $existingSummary;
        }
        foreach ($sources as $source) {
            $srcSummary = trim((string) ($source['summary'] ?? ''));
            if ($srcSummary === '') {
                continue;
            }
            $ref = (string) ($source['reference_code'] ?? ('#' . (int) $source['id']));
            $summaryBits[] = '[' . $ref . '] ' . $srcSummary;
        }
        $summaryBits[] = 'Regroupement du ' . date('d/m/Y H:i')
            . ' — investigations intégrées : ' . implode(', ', $mergedLabels) . '.';
        $mergedSummary = mb_substr(implode("\n\n", $summaryBits), 0, 5000);
        $this->meshes->update($targetId, $tenantId, ['summary' => $mergedSummary]);

        return [
            'target_id' => $targetId,
            'nodes_added' => $nodesAdded,
            'edges_added' => $edgesAdded,
            'archived' => count($sources),
            'reused' => $reused,
        ];
    }

    /**
     * @param array<string, mixed> $node
     */
    private function nodeRefKey(array $node): ?string
    {
        $type = trim((string) ($node['ref_type'] ?? ''));
        $id = (int) ($node['ref_id'] ?? 0);
        if ($type === '' || $id < 1) {
            return null;
        }

        return strtolower($type) . ':' . $id;
    }

    private function edgeKey(int $from, int $to, string $relation): string
    {
        return $from . '>' . $to . ':' . SseMeshRepository::normalizeRelation($relation);
    }

    private function primaryPersonPhotoPath(int $personId, int $tenantId): ?string
    {
        if ($personId < 1 || $tenantId < 1) {
            return null;
        }
        try {
            $persons = new \App\Repositories\SsePersonRepository();
            $person = $persons->findById($personId, $tenantId);
            if ($person === null) {
                return null;
            }
            $photo = is_array($person['primary_photo'] ?? null) ? $person['primary_photo'] : null;
            $path = trim((string) ($photo['image_path'] ?? ''));

            return $path !== '' ? $path : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
