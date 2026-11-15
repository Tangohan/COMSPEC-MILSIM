<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Database;
use App\Repositories\SseEntityLinkRepository;
use App\Repositories\SseWantedNoticeRepository;
use App\Support\SseCerbere;

/**
 * Page « Cerbères » : ce que le système fait tout seul dans le bureau SSE, en une phrase par traitement, s'il est actif, et
 * ses derniers événements. LECTURE SEULE, avec les journaux qui existent (liens de provenance, détections d'avis de
 * recherche, file des propositions du moteur) : aucune écriture, aucune règle réglée d'ici. Chaque source absente est ignorée.
 */
final class SseCerbereReport
{
    public function __construct(private ?Database $db = null)
    {
    }

    private function db(): Database
    {
        return $this->db ?? Database::getInstance();
    }

    /**
     * @return array{treatments: list<array<string, mixed>>, recent: list<array<string, mixed>>, provenance: bool, queue: array<string, int>, detections: list<array<string, mixed>>}
     */
    public function build(int $tenantId): array
    {
        $provenance = SseEntityLinkRepository::ready();
        $counts = [];
        $recent = [];
        if ($provenance && $tenantId > 0) {
            try {
                $repo = new SseEntityLinkRepository($this->db);
                $counts = $repo->countsByRule($tenantId);
                foreach ($repo->recentCerbere($tenantId, 12) as $row) {
                    $recent[] = [
                        'at' => (string) $row['created_at'],
                        'treatment' => SseCerbere::treatmentLabel((string) $row['rule_code']),
                        'rule' => (string) $row['rule_code'],
                        'rule_label' => SseCerbere::RULES[(string) $row['rule_code']]['label'] ?? '',
                        'text' => (string) $row['reason'],
                        'from' => $row['source_type'] . ':' . $row['source_id'],
                        'to' => $row['target_type'] . ':' . $row['target_id'],
                    ];
                }
            } catch (\Throwable) {
            }
        }
        $queue = $this->queue($tenantId);
        $detections = $this->detections($tenantId);
        $wantedReady = SseWantedNoticeRepository::ready();
        $treatments = [];
        foreach (SseCerbere::TREATMENTS as $key => $def) {
            $rules = [];
            $total = 0;
            $last = null;
            foreach (SseCerbere::rulesOf($key) as $code) {
                $c = $counts[$code] ?? ['n' => 0, 'last' => null];
                $rules[] = ['code' => $code, 'label' => SseCerbere::RULES[$code]['label'], 'n' => $c['n'], 'last' => $c['last']];
                $total += $c['n'];
                if ($c['last'] !== null && ($last === null || $c['last'] > $last)) {
                    $last = $c['last'];
                }
            }
            [$active, $why] = match ($key) {
                'validation' => $queue['ready'] > 0 ? [true, ''] : [false, 'La file des propositions n’est pas encore créée (étape de base de données à passer).'],
                'detection' => $wantedReady ? [true, ''] : [false, 'Les avis de recherche ne sont pas encore activés (étape de base de données à passer).'],
                default => [true, ''],
            };
            $treatments[] = [
                'key' => $key, 'label' => $def['label'], 'does' => $def['does'], 'active' => $active, 'why' => $why,
                'rules' => $rules, 'total' => $total, 'last' => $last,
                'extra' => $key === 'validation' ? $queue : ($key === 'detection' ? ['detections' => count($detections)] : []),
            ];
        }

        return ['treatments' => $treatments, 'recent' => $recent, 'provenance' => $provenance, 'queue' => $queue, 'detections' => $detections];
    }

    /** @return array<string, int> */
    private function queue(int $tenantId): array
    {
        $out = ['ready' => 0, 'pending' => 0, 'accepted' => 0, 'rejected' => 0];
        if ($tenantId < 1) {
            return $out;
        }
        try {
            foreach ($this->db()->fetchAll('SELECT status, COUNT(*) AS n FROM sse_suggestion_queue WHERE tenant_id = :t GROUP BY status', ['t' => $tenantId]) as $r) {
                $out['ready'] = 1;
                if (isset($out[(string) $r['status']])) {
                    $out[(string) $r['status']] = (int) $r['n'];
                }
            }
            if ($out['ready'] === 0) {
                $this->db()->fetchOne('SELECT 1 FROM sse_suggestion_queue WHERE tenant_id = :t LIMIT 1', ['t' => $tenantId]);
                $out['ready'] = 1;
            }
        } catch (\Throwable) {
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function detections(int $tenantId): array
    {
        if ($tenantId < 1 || !SseWantedNoticeRepository::ready()) {
            return [];
        }
        try {
            return array_map(static fn (array $r): array => [
                'at' => (string) $r['created_at'], 'type' => (string) $r['detection_type'], 'status' => (string) $r['status'], 'repeat' => (int) ($r['repeat_count'] ?? 1),
            ], $this->db()->fetchAll('SELECT created_at, detection_type, status, repeat_count FROM sse_detections WHERE tenant_id = :t ORDER BY id DESC LIMIT 6', ['t' => $tenantId]));
        } catch (\Throwable) {
            return [];
        }
    }
}
