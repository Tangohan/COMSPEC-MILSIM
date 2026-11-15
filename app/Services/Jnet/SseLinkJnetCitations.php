<?php

declare(strict_types=1);

namespace App\Services\Jnet;

use App\Repositories\JnetLinksRepository;
use App\Services\Sse\Links\SseLinkJnetBridge;

/**
 * « Cité dans : compte rendu X » : les contenus JNET (échanges, pages, tâches, opérations...) qui citent un élément SSE, vus
 * avec les droits JNET DU LECTEUR (JnetLinkViewer::see). Un contenu que le lecteur ne voit pas n'apparaît pas, sans trace.
 *
 * Côté JNET un élément SSE est le type « sse » et l'identifiant « type-numéro » (« person-12 ») : rien d'autre n'y est stocké.
 */
final class SseLinkJnetCitations implements SseLinkJnetBridge
{
    private ?JnetLinkViewer $viewer;

    public function __construct(private readonly int $tenantId, private readonly int $userId, ?JnetLinkViewer $viewer = null, private ?JnetLinksRepository $repo = null)
    {
        $this->viewer = $viewer;
    }

    private function viewer(): JnetLinkViewer
    {
        return $this->viewer ??= JnetLinkAccess::forViewer($this->tenantId, $this->userId);
    }

    public function citations(int $tenantId, array $refs): array
    {
        if ($tenantId < 1 || $tenantId !== $this->tenantId || $this->userId < 1 || $refs === []) {
            return [];
        }
        $repo = $this->repo ??= new JnetLinksRepository();
        try {
            if (!$repo->linksReady()) {
                return [];
            }
            $byId = [];
            foreach ($refs as $ref) {
                $id = JnetLinkRules::sseIdFromRef((string) $ref);
                if ($id !== null) {
                    $byId[$id] = (string) $ref;
                }
            }
            if ($byId === []) {
                return [];
            }
            $out = [];
            $rows = $repo->linksOfMany($tenantId, 'sse', array_keys($byId));
            $seen = [];
            $viewer = $this->viewer();
            $authors = $viewer->authors(array_map(static fn (array $r): int => (int) $r['created_by'], $rows));
            foreach ($rows as $row) {
                $sseIsSrc = (string) $row['src_type'] === 'sse';
                $sseId = (string) ($sseIsSrc ? $row['src_id'] : $row['dst_id']);
                $oType = (string) ($sseIsSrc ? $row['dst_type'] : $row['src_type']);
                $oId = (string) ($sseIsSrc ? $row['dst_id'] : $row['src_id']);
                if ($oType === 'sse' || !isset($byId[$sseId]) || isset($seen[$byId[$sseId] . '|' . $oType . ':' . $oId])) {
                    continue;
                }
                $item = $viewer->see($oType, $oId);
                if ($item === null) {
                    continue;
                }
                $seen[$byId[$sseId] . '|' . $oType . ':' . $oId] = true;
                $out[] = [
                    'ref' => $byId[$sseId], 'id' => $oType . ':' . $oId, 'title' => (string) $item['title'], 'summary' => trim(JnetLinkRules::TYPES[$oType] ?? '') . ((string) ($item['sub'] ?? '') !== '' ? ' · ' . $item['sub'] : ''),
                    'href' => (string) $item['href'], 'date' => (string) ($row['created_at'] ?? ''), 'by' => (string) ($authors[(int) $row['created_by']] ?? ''),
                ];
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }
}
