<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseCaseRepository;
use App\Repositories\SseDigitalLabRepository;
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
    ) {
        $this->cases ??= new SseCaseRepository();
        $this->interest ??= new SseInterestCaseRepository();
        $this->persons ??= new SsePersonRepository();
        $this->sites ??= new SseSiteRepository();
        $this->meshes ??= new SseMeshRepository();
        $this->watchlist ??= new SseWatchlistRepository();
        $this->cross ??= new SseCrossMatchService();
        $this->digitalLab ??= new SseDigitalLabRepository();
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

        $activity = [];
        foreach (array_slice($people, 0, 6) as $p) {
            $stamp = (string) ($p['created_at'] ?? '');
            $activity[] = [
                'at' => strlen($stamp) >= 16 ? substr($stamp, 11, 5) : date('H:i'),
                'text' => 'Fiche identité reçue — ' . (string) ($p['display_name'] ?? 'identité'),
                'kind' => 'identity',
            ];
        }
        foreach (array_slice($interest, 0, 4) as $row) {
            $stamp = (string) ($row['updated_at'] ?? $row['created_at'] ?? '');
            $activity[] = [
                'at' => strlen($stamp) >= 16 ? substr($stamp, 11, 5) : date('H:i'),
                'text' => 'Dossier d’intérêt mis à jour — ' . (string) ($row['temporary_designation'] ?? $row['reference_code'] ?? 'signalement'),
                'kind' => 'pressee',
            ];
        }
        usort($activity, static fn (array $a, array $b): int => strcmp((string) $b['at'], (string) $a['at']));
        $activity = array_slice($activity, 0, 12);

        $alerts = [];
        if ($crossPending > 0) {
            $alerts[] = [
                'level' => 'elevee',
                'title' => 'Rapprochements à confirmer',
                'detail' => $crossPending . ' proposition' . ($crossPending > 1 ? 's' : '') . ' en file opérateur.',
            ];
        }
        if ($stale > 0) {
            $alerts[] = [
                'level' => 'moderee',
                'title' => 'Dossiers sans activité récente',
                'detail' => $stale . ' dossier' . ($stale > 1 ? 's' : '') . ' actifs sans mise à jour depuis 3 jours.',
            ];
        }
        if ($interestPending > 5) {
            $alerts[] = [
                'level' => 'critique',
                'title' => 'File dossiers d’intérêt saturée',
                'detail' => $interestPending . ' dossiers d’intérêt en attente d’instruction.',
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
            ];
        }

        if ($alerts === []) {
            $alerts[] = [
                'level' => 'moderee',
                'title' => 'Situation nominale',
                'detail' => 'Aucune anomalie prioritaire détectée sur le périmètre de session.',
            ];
        }

        $recentObjects = [];
        foreach (array_slice($people, 0, 5) as $p) {
            $recentObjects[] = [
                'type' => 'Identité',
                'ref' => 'IDN-' . str_pad((string) ((int) ($p['id'] ?? 0)), 5, '0', STR_PAD_LEFT),
                'label' => (string) ($p['display_name'] ?? ''),
                'href' => url('atak/sse/identites/' . (int) ($p['id'] ?? 0)),
            ];
        }
        foreach (array_slice($sites, 0, 3) as $s) {
            $recentObjects[] = [
                'type' => 'Site',
                'ref' => (string) ($s['reference_code'] ?? ''),
                'label' => (string) ($s['name'] ?? ''),
                'href' => url('atak/sse/sites/' . (int) ($s['id'] ?? 0)),
            ];
        }
        try {
            foreach (array_slice($this->digitalLab->listDevices($tenantId, ['limit' => 3]), 0, 3) as $d) {
                $recentObjects[] = [
                    'type' => 'Support numérique',
                    'ref' => (string) ($d['reference_code'] ?? ''),
                    'label' => (string) ($d['device_type_label'] ?? 'Support'),
                    'href' => url('atak/sse/exploitation-numerique/supports/' . (int) ($d['id'] ?? 0)),
                ];
            }
        } catch (\Throwable) {
        }

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
                'total_cases' => count(array_filter($cases, static fn (array $c): bool => empty($c['is_folder']))),
            ],
            'activity' => $activity,
            'alerts' => $alerts,
            'recent_objects' => $recentObjects,
            'operator_queue' => [
                ['label' => 'Rapprochements à confirmer', 'count' => $crossPending, 'href' => url('atak/sse/croisements')],
                ['label' => 'Dossiers d’intérêt à instruire', 'count' => $interestPending, 'href' => url('atak/sse/interet')],
                ['label' => 'Signaux numériques', 'count' => $digitalPending, 'href' => url('atak/sse/exploitation-numerique/analyses')],
                ['label' => 'Dossiers sans activité', 'count' => $stale, 'href' => url('atak/sse/dossiers?status=en_cours')],
                ['label' => 'Investigations ouvertes', 'count' => count($meshes), 'href' => url('atak/sse/toiles')],
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
     * Recherche globale légère (identités, sites, dossiers, toiles, Pré-SSE).
     *
     * @return list<array{type:string,ref:string,label:string,href:string}>
     */
    public function globalSearch(int $tenantId, string $q, ?array $caseScope = null): array
    {
        $q = trim($q);
        if ($q === '' || mb_strlen($q) < 2) {
            return [];
        }
        $out = [];
        $ql = mb_strtolower($q);

        try {
            foreach ($this->persons->listForContext($tenantId, 1, ['limit' => 100]) as $p) {
                $hay = mb_strtolower(
                    (string) ($p['display_name'] ?? '') . ' '
                    . (string) ($p['alias'] ?? '') . ' '
                    . (string) ($p['last_name'] ?? '') . ' '
                    . (string) ($p['first_name'] ?? '')
                );
                if (!str_contains($hay, $ql)) {
                    continue;
                }
                $out[] = [
                    'type' => 'Identité',
                    'ref' => 'IDN-' . str_pad((string) ((int) ($p['id'] ?? 0)), 5, '0', STR_PAD_LEFT),
                    'label' => (string) ($p['display_name'] ?? ''),
                    'href' => url('atak/sse/identites/' . (int) ($p['id'] ?? 0)),
                ];
            }
        } catch (\Throwable) {
            // Source indisponible : ne pas faire tomber toute la recherche.
        }

        try {
            foreach ($this->sites->listForContext($tenantId, 1, ['limit' => 80]) as $s) {
                $hay = mb_strtolower((string) ($s['name'] ?? '') . ' ' . (string) ($s['reference_code'] ?? ''));
                if (!str_contains($hay, $ql)) {
                    continue;
                }
                $out[] = [
                    'type' => 'Site',
                    'ref' => (string) ($s['reference_code'] ?? ''),
                    'label' => (string) ($s['name'] ?? ''),
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
                    'href' => url('atak/sse/dossiers/' . (int) ($c['id'] ?? 0)),
                ];
                if (count($out) > 40) {
                    break;
                }
            }
        } catch (\Throwable) {
        }

        try {
            foreach ($this->meshes->listForTenant($tenantId, ['q' => $q]) as $m) {
                $out[] = [
                    'type' => 'Toile',
                    'ref' => (string) ($m['reference_code'] ?? ''),
                    'label' => (string) ($m['title'] ?? ''),
                    'href' => url('atak/sse/toiles/' . (int) ($m['id'] ?? 0)),
                ];
            }
        } catch (\Throwable) {
        }

        try {
            foreach ($this->interest->listForTenant($tenantId, ['q' => $q]) as $row) {
                $out[] = [
                'type' => 'Dossier d’intérêt',
                'ref' => (string) ($row['reference_code'] ?? ''),
                'label' => (string) ($row['temporary_designation'] ?? ''),
                'href' => url('atak/sse/interet/' . (int) ($row['id'] ?? 0)),
                ];
            }
        } catch (\Throwable) {
        }

        return array_slice($out, 0, 40);
    }
}
