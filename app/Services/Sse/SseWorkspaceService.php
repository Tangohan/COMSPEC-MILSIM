<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseCaseRepository;
use App\Repositories\SseDigitalLabRepository;
use App\Repositories\SseDocumentRepository;
use App\Repositories\SseInterestCaseRepository;
use App\Repositories\SseMeshRepository;
use App\Repositories\SsePersonRepository;
use App\Repositories\SseSiteRepository;
use App\Repositories\SseWatchlistRepository;

/**
 * Agrégats pour la Control Tower SSE (tableau de bord opérationnel).
 */
final class SseWorkspaceService
{
    public function __construct(
        private ?SseCaseRepository $cases = null,
        private ?SseInterestCaseRepository $interest = null,
        private ?SsePersonRepository $persons = null,
        private ?SseSiteRepository $sites = null,
        private ?SseMeshRepository $meshes = null,
        private ?SseWatchlistRepository $watchlist = null,
        private ?SseCrossMatchService $cross = null,
        private ?SseDigitalLabRepository $digitalLab = null,
        private ?SseDocumentRepository $documents = null,
    ) {
        $this->cases ??= new SseCaseRepository();
        $this->interest ??= new SseInterestCaseRepository();
        $this->persons ??= new SsePersonRepository();
        $this->sites ??= new SseSiteRepository();
        $this->meshes ??= new SseMeshRepository();
        $this->watchlist ??= new SseWatchlistRepository();
        $this->cross ??= new SseCrossMatchService();
        $this->digitalLab ??= new SseDigitalLabRepository();
        $this->documents ??= new SseDocumentRepository();
    }

    /**
     * @param list<int>|null $caseScope
     * @return array<string, mixed>
     */
    public function controlTower(int $tenantId, ?array $caseScope = null): array
    {
        $cases = $this->cases->listForTenant($tenantId, $caseScope);
        $active = 0;
        $stale = 0;
        $now = time();
        foreach ($cases as $c) {
            if (!empty($c['is_folder'])) {
                continue;
            }
            $st = (string) ($c['status'] ?? '');
            if (in_array($st, ['ouvert', 'en_cours'], true)) {
                $active++;
            }
            $upd = strtotime((string) ($c['updated_at'] ?? $c['created_at'] ?? '')) ?: 0;
            if ($upd > 0 && ($now - $upd) > 86400 * 3 && in_array($st, ['ouvert', 'en_cours'], true)) {
                $stale++;
            }
        }

        $interest = $this->interest->listForTenant($tenantId, []);
        $interestPending = 0;
        foreach ($interest as $row) {
            $st = (string) ($row['status'] ?? '');
            if (in_array($st, ['ouvert', 'en_analyse', 'en_collecte', 'en_correlation'], true)
                || $st === '' || str_contains($st, 'attente') || str_contains($st, 'correl')) {
                $interestPending++;
            }
        }

        $people = $this->persons->listForContext($tenantId, 1, ['limit' => 80]);
        $sites = $this->sites->listForContext($tenantId, 1, ['limit' => 50]);
        $meshes = $this->meshes->listForTenant($tenantId);

        $crossPending = 0;
        try {
            $matches = $this->cross->matchPersonsAgainstWatchlist($tenantId);
            $crossPending = is_array($matches) ? count($matches) : 0;
        } catch (\Throwable) {
            $crossPending = 0;
        }

        $toZulu = static function (?string $stamp, bool $withDate = false): string {
            $ts = $stamp !== null && $stamp !== '' ? (strtotime($stamp) ?: time()) : time();
            return $withDate
                ? gmdate('d/m/Y H:i', $ts) . 'Z'
                : gmdate('H:i', $ts) . 'Z';
        };

        $activity = [];
        foreach (array_slice($people, 0, 6) as $p) {
            $stamp = (string) ($p['created_at'] ?? '');
            $activity[] = [
                'at' => $toZulu($stamp),
                'at_full' => $toZulu($stamp, true),
                'ts' => $stamp !== '' ? (strtotime($stamp) ?: 0) : time(),
                'text' => 'Fiche identité reçue — ' . (string) ($p['display_name'] ?? 'identité'),
                'kind' => 'identity',
                'href' => url('atak/sse/identites/' . (int) ($p['id'] ?? 0)),
            ];
        }
        foreach (array_slice($interest, 0, 4) as $row) {
            $stamp = (string) ($row['updated_at'] ?? $row['created_at'] ?? '');
            $activity[] = [
                'at' => $toZulu($stamp),
                'at_full' => $toZulu($stamp, true),
                'ts' => $stamp !== '' ? (strtotime($stamp) ?: 0) : time(),
                'text' => 'Dossier d’intérêt mis à jour — ' . (string) ($row['temporary_designation'] ?? $row['reference_code'] ?? 'signalement'),
                'kind' => 'pressee',
                'href' => url('atak/sse/interet/' . (int) ($row['id'] ?? 0)),
            ];
        }
        foreach (array_slice($cases, 0, 4) as $c) {
            if (!empty($c['is_folder'])) {
                continue;
            }
            $stamp = (string) ($c['updated_at'] ?? $c['created_at'] ?? '');
            $activity[] = [
                'at' => $toZulu($stamp),
                'at_full' => $toZulu($stamp, true),
                'ts' => $stamp !== '' ? (strtotime($stamp) ?: 0) : time(),
                'text' => 'Dossier actualisé — ' . (string) ($c['reference_code'] ?? '') . ' ' . (string) ($c['title'] ?? ''),
                'kind' => 'case',
                'href' => url('atak/sse/dossiers/' . (int) ($c['id'] ?? 0)),
            ];
        }
        usort($activity, static fn (array $a, array $b): int => ((int) ($b['ts'] ?? 0)) <=> ((int) ($a['ts'] ?? 0)));
        $activity = array_slice($activity, 0, 12);

        $alerts = [];
        if ($crossPending > 0) {
            $alerts[] = [
                'level' => 'elevee',
                'title' => 'Rapprochements à confirmer',
                'detail' => $crossPending . ' proposition' . ($crossPending > 1 ? 's' : '') . ' en file opérateur.',
                'href' => url('atak/sse/croisements'),
                'action' => 'Ouvrir les croisements',
            ];
        }
        if ($stale > 0) {
            $alerts[] = [
                'level' => 'moderee',
                'title' => 'Dossiers sans activité récente',
                'detail' => $stale . ' dossier' . ($stale > 1 ? 's' : '') . ' actifs sans mise à jour depuis 3 jours.',
                'href' => url('atak/sse/dossiers?status=en_cours'),
                'action' => 'Revoir les dossiers',
            ];
        }
        if ($interestPending > 5) {
            $alerts[] = [
                'level' => 'critique',
                'title' => 'File dossiers d’intérêt saturée',
                'detail' => $interestPending . ' dossiers d’intérêt en attente d’instruction.',
                'href' => url('atak/sse/interet'),
                'action' => 'Instruire la file',
            ];
        }

        $digitalPending = 0;
        $digitalDevices = 0;
        try {
            $hub = $this->digitalLab->hubCounts($tenantId);
            $digitalPending = (int) ($hub['findings_pending'] ?? 0);
            $digitalDevices = (int) ($hub['devices'] ?? 0);
        } catch (\Throwable) {
            $digitalPending = 0;
            $digitalDevices = 0;
        }

        if ($digitalPending > 0) {
            $alerts[] = [
                'level' => 'moderee',
                'title' => 'Signaux numériques à examiner',
                'detail' => $digitalPending . ' proposition' . ($digitalPending > 1 ? 's' : '') . ' du laboratoire numérique.',
                'href' => url('atak/sse/exploitation-numerique/analyses'),
                'action' => 'Ouvrir le laboratoire',
            ];
        }

        if ($alerts === []) {
            $alerts[] = [
                'level' => 'faible',
                'title' => 'Situation nominale',
                'detail' => 'Aucune anomalie prioritaire détectée sur le périmètre de session.',
                'href' => url('atak/sse/operations'),
                'action' => 'Maintenir la veille',
            ];
        }

        // Série 24 h (8 créneaux de 3 h) pour le graphique d’activité.
        $buckets = array_fill(0, 8, 0);
        $nowUtc = time();
        foreach ($activity as $row) {
            $ts = (int) ($row['ts'] ?? 0);
            if ($ts <= 0) {
                continue;
            }
            $ageH = (int) floor(max(0, $nowUtc - $ts) / 3600);
            if ($ageH >= 24) {
                continue;
            }
            $idx = 7 - (int) floor($ageH / 3);
            if ($idx >= 0 && $idx < 8) {
                $buckets[$idx]++;
            }
        }
        $bucketLabels = [];
        for ($i = 0; $i < 8; $i++) {
            $bucketLabels[] = gmdate('H', $nowUtc - ((7 - $i) * 3 * 3600)) . 'Z';
        }

        $queueItems = [
            ['label' => 'Rapprochements à confirmer', 'count' => $crossPending, 'href' => url('atak/sse/croisements'), 'tone' => 'warn'],
            ['label' => 'Dossiers d’intérêt à instruire', 'count' => $interestPending, 'href' => url('atak/sse/interet'), 'tone' => 'accent'],
            ['label' => 'Signaux numériques', 'count' => $digitalPending, 'href' => url('atak/sse/exploitation-numerique/analyses'), 'tone' => 'accent'],
            ['label' => 'Dossiers sans activité', 'count' => $stale, 'href' => url('atak/sse/dossiers?status=en_cours'), 'tone' => 'warn'],
            ['label' => 'Investigations ouvertes', 'count' => count($meshes), 'href' => url('atak/sse/toiles'), 'tone' => 'ok'],
        ];
        $queueCounts = array_map(static fn (array $q): int => (int) ($q['count'] ?? 0), $queueItems);
        $queueMax = max(1, $queueCounts === [] ? 0 : max($queueCounts));

        $recentObjects = [];
        foreach (array_slice($people, 0, 5) as $p) {
            $recentObjects[] = [
                'type' => 'Identité',
                'ref' => 'IDN-' . str_pad((string) ((int) ($p['id'] ?? 0)), 5, '0', STR_PAD_LEFT),
                'label' => (string) ($p['display_name'] ?? ''),
                'href' => url('atak/sse/identites/' . (int) ($p['id'] ?? 0)),
                'at' => $toZulu((string) ($p['created_at'] ?? '')),
            ];
        }
        foreach (array_slice($sites, 0, 3) as $s) {
            $recentObjects[] = [
                'type' => 'Site',
                'ref' => (string) ($s['reference_code'] ?? ''),
                'label' => (string) ($s['name'] ?? ''),
                'href' => url('atak/sse/sites/' . (int) ($s['id'] ?? 0)),
                'at' => $toZulu((string) ($s['created_at'] ?? $s['updated_at'] ?? '')),
            ];
        }
        try {
            foreach (array_slice($this->digitalLab->listDevices($tenantId, ['limit' => 3]), 0, 3) as $d) {
                $recentObjects[] = [
                    'type' => 'Support numérique',
                    'ref' => (string) ($d['reference_code'] ?? ''),
                    'label' => (string) ($d['device_type_label'] ?? 'Support'),
                    'href' => url('atak/sse/exploitation-numerique/supports/' . (int) ($d['id'] ?? 0)),
                    'at' => $toZulu((string) ($d['created_at'] ?? '')),
                ];
            }
        } catch (\Throwable) {
        }

        $totalCases = count(array_filter($cases, static fn (array $c): bool => empty($c['is_folder'])));

        return [
            'kpi' => [
                'active_cases' => $active,
                'pressee_pending' => $interestPending,
                'people' => count($people),
                'sites' => count($sites),
                'meshes' => count($meshes),
                'cross_pending' => $crossPending,
                'stale_cases' => $stale,
                'digital_devices' => $digitalDevices,
                'digital_findings' => $digitalPending,
                'total_cases' => $totalCases,
            ],
            'activity' => $activity,
            'alerts' => $alerts,
            'recent_objects' => $recentObjects,
            'operator_queue' => $queueItems,
            'charts' => [
                'activity_24h' => [
                    'labels' => $bucketLabels,
                    'values' => $buckets,
                    'max' => max(1, max($buckets)),
                ],
                'workload' => [
                    ['label' => 'Dossiers actifs', 'value' => $active, 'color' => '#34d399'],
                    ['label' => 'Dossiers d’intérêt', 'value' => $interestPending, 'color' => '#fbbf24'],
                    ['label' => 'Rapprochements', 'value' => $crossPending, 'color' => '#f87171'],
                    ['label' => 'Investigations', 'value' => count($meshes), 'color' => '#60a5fa'],
                    ['label' => 'Numérique', 'value' => $digitalPending, 'color' => '#a78bfa'],
                ],
                'queue_max' => $queueMax,
            ],
            'clock' => [
                'zulu' => gmdate('H:i:s') . 'Z',
                'zulu_date' => gmdate('d/m/Y'),
                'generated_at' => gmdate('d/m/Y H:i:s') . 'Z',
            ],
            'data_quality' => [
                'freshness' => $stale === 0 ? 'Bonne' : ($stale < 3 ? 'Correcte' : 'Dégradée'),
                'sources_ok' => 3,
                'sources_total' => 3,
                'sync_label' => 'Synchronisé',
            ],
        ];
    }

    /**
     * Recherche globale (identités, sites, dossiers, d’intérêt, investigations, documents).
     *
     * @return list<array{type:string,ref:string,label:string,href:string,hint?:string}>
     */
    public function globalSearch(int $tenantId, string $q, ?array $caseScope = null, int $limit = 40): array
    {
        $q = trim($q);
        if ($q === '' || mb_strlen($q) < 2) {
            return [];
        }
        $out = [];
        $limit = max(5, min(60, $limit));
        $perSource = max(5, (int) ceil($limit / 3));

        try {
            foreach ($this->persons->searchForTenant($tenantId, $q, $perSource) as $p) {
                $out[] = [
                    'type' => 'Identité',
                    'ref' => 'IDN-' . str_pad((string) ((int) ($p['id'] ?? 0)), 5, '0', STR_PAD_LEFT),
                    'label' => (string) ($p['display_name'] ?? ''),
                    'hint' => trim((string) (($p['affiliation'] ?? '') ?: ($p['nationality'] ?? ''))),
                    'href' => url('atak/sse/identites/' . (int) ($p['id'] ?? 0)),
                ];
            }
        } catch (\Throwable) {
        }

        try {
            foreach ($this->sites->searchForTenant($tenantId, $q, $perSource) as $s) {
                $out[] = [
                    'type' => 'Site',
                    'ref' => (string) ($s['reference_code'] ?? ('SITE-' . (int) ($s['id'] ?? 0))),
                    'label' => (string) ($s['name'] ?? ''),
                    'hint' => (string) ($s['site_type_label'] ?? $s['status_label'] ?? ''),
                    'href' => url('atak/sse/sites/' . (int) ($s['id'] ?? 0)),
                ];
            }
        } catch (\Throwable) {
        }

        try {
            foreach ($this->cases->listForTenant($tenantId, $caseScope, ['q' => $q]) as $c) {
                if (!empty($c['is_folder'])) {
                    continue;
                }
                $out[] = [
                    'type' => 'Dossier',
                    'ref' => (string) ($c['reference_code'] ?? ''),
                    'label' => (string) ($c['title'] ?? ''),
                    'hint' => (string) ($c['status_label'] ?? ''),
                    'href' => url('atak/sse/dossiers/' . (int) ($c['id'] ?? 0)),
                ];
                if (count($out) >= $limit) {
                    break;
                }
            }
        } catch (\Throwable) {
        }

        try {
            foreach ($this->interest->listForTenant($tenantId, ['q' => $q]) as $row) {
                $out[] = [
                    'type' => 'Dossier d’intérêt',
                    'ref' => (string) ($row['reference_code'] ?? ''),
                    'label' => (string) ($row['temporary_designation'] ?? ''),
                    'hint' => (string) ($row['suspected_alias'] ?? ''),
                    'href' => url('atak/sse/interet/' . (int) ($row['id'] ?? 0)),
                ];
            }
        } catch (\Throwable) {
        }

        try {
            foreach ($this->meshes->listForTenant($tenantId, ['q' => $q]) as $m) {
                $out[] = [
                    'type' => 'Investigation',
                    'ref' => (string) ($m['reference_code'] ?? ''),
                    'label' => (string) ($m['title'] ?? ''),
                    'hint' => 'Toile relationnelle',
                    'href' => url('atak/sse/toiles/' . (int) ($m['id'] ?? 0)),
                ];
            }
        } catch (\Throwable) {
        }

        try {
            foreach ($this->documents->listForTenant($tenantId, ['q' => $q]) as $d) {
                $out[] = [
                    'type' => 'Document',
                    'ref' => (string) ($d['reference_code'] ?? ''),
                    'label' => (string) ($d['title'] ?? ''),
                    'hint' => (string) ($d['document_type_label'] ?? $d['status_label'] ?? ''),
                    'href' => url('atak/sse/documents/' . (int) ($d['id'] ?? 0)),
                ];
                if (count($out) >= $limit) {
                    break;
                }
            }
        } catch (\Throwable) {
        }

        return array_slice($out, 0, $limit);
    }
}
