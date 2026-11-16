<?php

declare(strict_types=1);

namespace App\Services\Sse\Detection;

use App\Repositories\SseDetectionRepository;
use App\Services\Intel\IntelIntakeService;
use PDO;

/**
 * Re-détection prudente (tools/sse-redetect.php) : pour chaque remontée encore ouverte, compare ce que l'ancienne lecture
 * proposait (SseLegacyKeywordDetector, par sous-chaîne) à ce que Cerbère-Lecture propose maintenant.
 *
 *   - Une ancienne proposition que le nouveau moteur ne justifie plus est RETIRÉE avec une trace : ligne « withdrawn » dans
 *     sse_detection_decisions (motif : les mots de l'ancienne lecture) et entrée « cerbere.lecture.withdrawn » au journal SSE.
 *   - Une proposition déjà appliquée, ignorée ou rejetée n'est jamais modifiée ; les données des remontées ne sont jamais touchées.
 *   - Idempotent : une proposition déjà retirée n'est pas retirée deux fois ; relancer ne change rien.
 * Simulation par défaut : rien n'est écrit sans $apply.
 */
final class SseRedetection
{
    public const ACTOR = ['id' => 0, 'label' => 'Cerbère-Lecture (re-détection)'];

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @return array{tenants: int, items: int, legacy: int, kept: int, withdrawn: int, skipped: int, ready: bool, changes: list<array{tenant: int, source: string, id: int, ref: string, target: string, value: string, words: list<string>, done: bool}>}
     */
    public function run(bool $apply, ?int $tenantId = null, int $limit = 2000): array
    {
        $intake = new IntelIntakeService($this->pdo);
        $repo = new SseDetectionRepository($this->pdo);
        $service = new SseDetectionService($this->pdo, $repo);
        $report = ['tenants' => 0, 'items' => 0, 'legacy' => 0, 'kept' => 0, 'withdrawn' => 0, 'skipped' => 0, 'ready' => $repo->ready(), 'changes' => []];
        if (!$intake->schemaReady()) {
            return $report;
        }
        $tenants = $tenantId !== null ? [$tenantId] : $this->tenants();
        foreach ($tenants as $t) {
            $report['tenants']++;
            $decided = $repo->decidedKeys($t);
            foreach ($intake->listItems($t, ['state' => 'open'], 0, $limit) as $row) {
                $item = $intake->find($t, (string) $row['source'], (int) $row['id']);
                if ($item === null || !empty($item['deleted'])) {
                    continue;
                }
                $report['items']++;
                $now = [];
                foreach ($service->proposals($t, $item) as $p) {
                    $now[$p['target'] . '|' . $p['value']] = true;
                }
                foreach (SseLegacyKeywordDetector::proposals($item) as $old) {
                    $report['legacy']++;
                    $key = $old['target'] . '|' . $old['value'];
                    if (isset($now[$key])) {
                        $report['kept']++;
                        continue;
                    }
                    if (isset($decided[$item['source'] . '|' . $item['id'] . '|' . $key])) {
                        $report['skipped']++;
                        continue;
                    }
                    $done = false;
                    if ($apply && $report['ready']) {
                        $done = $repo->recordDecision($t, (string) $item['source'], (int) $item['id'], $old['target'], $old['value'], [], 0, 'withdrawn',
                            'Retirée par la re-détection : l’ancienne lecture avait trouvé ' . implode(', ', array_map(static fn (string $w): string => '« ' . $w . ' »', $old['words'])) . ', que le lexique ' . $service->lexicon($t)->version() . ' ne retient plus.',
                            self::ACTOR, $service->lexicon($t)->version());
                        if ($done) {
                            $decided[$item['source'] . '|' . $item['id'] . '|' . $key] = true;
                        }
                    }
                    $report['withdrawn'] += $done || !$apply ? 1 : 0;
                    $report['changes'][] = ['tenant' => $t, 'source' => (string) $item['source'], 'id' => (int) $item['id'], 'ref' => (string) ($item['ref'] ?? ''), 'target' => $old['target'], 'value' => $old['value'], 'words' => $old['words'], 'done' => $done];
                }
            }
        }

        return $report;
    }

    /** @return list<int> */
    private function tenants(): array
    {
        try {
            $ids = $this->pdo->query('SELECT DISTINCT tenant_id FROM intel_intake_state UNION SELECT DISTINCT tenant_id FROM sse_field_notes')->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (\Throwable) {
            $ids = [];
        }

        return array_values(array_filter(array_map('intval', $ids), static fn (int $i): bool => $i > 0));
    }
}
