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
            $nid = $this->meshes->addNode($meshId, $tenantId, [
                'kind' => $kind,
                'label' => (string) ($node['label'] ?? 'Élément'),
                'detail' => trim(((string) ($node['ref'] ?? '')) . ' ' . ((string) ($node['detail'] ?? ''))),
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
}
