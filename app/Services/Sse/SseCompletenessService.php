<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseAnalyticalRepository;
use App\Repositories\SseCaseRepository;
use App\Repositories\SseDocumentRepository;
use App\Repositories\SsePersonRepository;
use App\Repositories\SseSiteRepository;
use App\Repositories\SseSuggestionQueueRepository;

/**
 * Score de complétude du dossier SSE (0–100) + checklist enrichie.
 * Ne décide rien : il mesure et signale les trous.
 */
final class SseCompletenessService
{
    public function __construct(
        private ?SseCaseRepository $cases = null,
        private ?SsePersonRepository $persons = null,
        private ?SseSiteRepository $sites = null,
        private ?SseDocumentRepository $documents = null,
        private ?SseCorrelationService $correlation = null,
        private ?SseAnalyticalRepository $analytical = null,
        private ?SseSuggestionQueueRepository $queue = null,
    ) {
        $this->cases ??= new SseCaseRepository();
        $this->persons ??= new SsePersonRepository();
        $this->sites ??= new SseSiteRepository();
        $this->documents ??= new SseDocumentRepository();
        $this->correlation ??= new SseCorrelationService();
        $this->analytical ??= new SseAnalyticalRepository();
        $this->queue ??= new SseSuggestionQueueRepository();
    }

    /**
     * @param array<string,mixed> $case
     * @param list<array<string,mixed>>|null $people
     * @param list<array<string,mixed>>|null $sites
     * @param list<array<string,mixed>>|null $evidence
     * @return array{
     *   score:int,complete:bool,done:int,total:int,
     *   steps:list<array<string,mixed>>,
     *   gaps:list<array{kind:string,title:string,body:string,priority:string}>,
     *   digest:?array
     * }
     */
    public function evaluate(int $tenantId, array $case, ?array $people = null, ?array $sites = null, ?array $evidence = null): array
    {
        $caseId = (int) ($case['id'] ?? 0);
        $base = url('atak/sse/dossiers/' . $caseId);

        if ($people === null) {
            $people = [];
            foreach ($this->cases->listLinkedPersonIds($caseId, $tenantId) as $link) {
                $p = $this->persons->findById((int) $link['person_id'], $tenantId);
                if ($p) {
                    $people[] = $p;
                }
            }
        }
        $sites ??= $this->sites->listForCase($caseId, $tenantId);
        $evidence ??= $this->cases->listEvidence($caseId, $tenantId);

        try {
            $relations = $this->correlation->listStored($caseId, $tenantId);
        } catch (\Throwable) {
            $relations = [];
        }
        try {
            $documents = $this->documents->listForTenant($tenantId, ['case_id' => $caseId]);
        } catch (\Throwable) {
            $documents = [];
        }

        $assessments = $this->analytical->listAssessments($tenantId, $caseId);
        $activeAssessments = array_values(array_filter(
            $assessments,
            static fn (array $a): bool => ($a['status'] ?? '') === 'active'
        ));
        $gaps = $this->analytical->listGaps($tenantId, $caseId);
        $openGaps = array_values(array_filter(
            $gaps,
            static fn (array $g): bool => in_array(($g['status'] ?? ''), ['ouvert', 'en_cours'], true)
        ));
        $decisions = $this->analytical->listDecisions($tenantId, $caseId, 5);
        $caseLinks = $this->analytical->listCaseLinks($tenantId, $caseId);
        $pendingSuggestions = $this->queue->countPending($tenantId, $caseId);

        $bioCount = 0;
        $photoCount = 0;
        foreach ($people as $p) {
            if (!empty($p['has_biometrics']) || !empty($p['biometric_ref']) || !empty($p['fingerprint_hash'])) {
                $bioCount++;
            }
            if (!empty($p['photo_path']) || !empty($p['has_photo']) || !empty($p['primary_photo_id'])) {
                $photoCount++;
            }
        }

        $steps = [
            $this->step('identites', 'Identité', 'Au moins une personne rattachée', count($people), true, $base, 18),
            $this->step('biometrie', 'Biométrie', 'Empreinte ou prélèvement versé', $bioCount, false, $base, 8),
            $this->step('photo', 'Photographie', 'Portrait ou cliché de situation', $photoCount + $this->countEvidenceKind($evidence, 'photo'), false, $base, 8),
            $this->step('sites', 'Sites', 'Lieux exploités ou observés', count($sites), false, url('atak/sse/sites'), 12),
            $this->step('materiels', 'Matériels / pièces', 'Saisies et éléments matériels', count($evidence), false, $base, 10),
            $this->step('chronologie', 'Chronologie / notes', 'Repères temporels ou notes', count($this->cases->listNotes($caseId, $tenantId)), false, $base . '#notes', 8),
            $this->step('sources', 'Appréciation / sources', 'Au moins une appréciation structurée', count($activeAssessments), false, $base . '#analyse', 12),
            $this->step('relations', 'Relations', 'Liens entre objets ou dossiers', count($relations) + count($caseLinks), false, $base . '/correlations', 10),
            $this->step('redaction', 'Rédaction', 'Document de synthèse', count($documents), false, url('atak/sse/documents'), 8),
            $this->step('registre', 'Registre décisions', 'Décision analytique consignées', count($decisions), false, $base . '#decisions', 6),
        ];

        $score = 0;
        $done = 0;
        $complete = true;
        foreach ($steps as $i => $step) {
            $ok = (int) $step['count'] > 0;
            $steps[$i]['done'] = $ok;
            if ($ok) {
                $done++;
                $score += (int) $step['weight'];
            } elseif (!empty($step['required'])) {
                $complete = false;
            }
        }

        // Bonus : lacunes explicitement gérées (dossier mature)
        if ($openGaps !== [] || count($gaps) > 0) {
            $score = min(100, $score + 4);
        }
        // Pénalité douce : suggestions non traitées (info, pas bloquant)
        if ($pendingSuggestions > 3) {
            $score = max(0, $score - 2);
        }

        $autoGaps = $this->deriveGaps($people, $sites, $evidence, $activeAssessments, $openGaps, $bioCount, $photoCount);

        $prev = $this->queue->getCompleteness($tenantId, $caseId);
        $digest = $this->buildDigest($prev, [
            'people' => count($people),
            'sites' => count($sites),
            'evidence' => count($evidence),
            'relations' => count($relations) + count($caseLinks),
            'assessments' => count($activeAssessments),
            'gaps_open' => count($openGaps),
            'suggestions' => $pendingSuggestions,
        ]);

        $breakdown = [
            'score' => $score,
            'steps' => array_map(static fn (array $s): array => [
                'key' => $s['key'],
                'done' => !empty($s['done']),
                'count' => $s['count'],
                'weight' => $s['weight'],
            ], $steps),
            'pending_suggestions' => $pendingSuggestions,
            'open_gaps' => count($openGaps),
        ];

        $this->queue->saveCompleteness($tenantId, $caseId, $score, $breakdown, $digest);

        return [
            'score' => $score,
            'complete' => $complete,
            'done' => $done,
            'total' => count($steps),
            'steps' => $steps,
            'gaps' => $autoGaps,
            'digest' => $digest,
            'pending_suggestions' => $pendingSuggestions,
        ];
    }

    /**
     * @param list<array<string,mixed>> $people
     * @param list<array<string,mixed>> $sites
     * @param list<array<string,mixed>> $evidence
     * @param list<array<string,mixed>> $assessments
     * @param list<array<string,mixed>> $openGaps
     * @return list<array{kind:string,title:string,body:string,priority:string}>
     */
    private function deriveGaps(array $people, array $sites, array $evidence, array $assessments, array $openGaps, int $bioCount, int $photoCount): array
    {
        $existingTitles = [];
        foreach ($openGaps as $g) {
            $existingTitles[mb_strtolower((string) ($g['title'] ?? ''))] = true;
        }

        $candidates = [];
        if ($people !== [] && $sites === []) {
            $candidates[] = [
                'kind' => 'lacune',
                'title' => 'Identité sans site rattaché',
                'body' => 'LACUNE IDENTIFIÉE — Des personnes sont rattachées au dossier, mais aucun site n’est encore porté à la chemise.',
                'priority' => 'haute',
            ];
        }
        if ($sites !== [] && $people === []) {
            $candidates[] = [
                'kind' => 'lacune',
                'title' => 'Site sans identité rattachée',
                'body' => 'LACUNE IDENTIFIÉE — Un ou plusieurs sites sont ouverts sans personne nominativement rattachée.',
                'priority' => 'critique',
            ];
        }
        foreach ($sites as $s) {
            $fn = trim((string) ($s['function'] ?? $s['role'] ?? $s['site_function'] ?? ''));
            if ($fn === '') {
                $candidates[] = [
                    'kind' => 'lacune',
                    'title' => 'Site identifié — fonction inconnue',
                    'body' => 'LACUNE IDENTIFIÉE — Le site « ' . trim((string) ($s['name'] ?? 'sans nom')) . ' » est rattaché ; sa fonction exacte demeure indéterminée.',
                    'priority' => 'normale',
                ];
                break;
            }
        }
        if ($people !== [] && $bioCount === 0) {
            $candidates[] = [
                'kind' => 'besoin',
                'title' => 'Biométrie manquante',
                'body' => 'BESOIN — Obtenir un élément biométrique discriminatoire pour consolider l’identité.',
                'priority' => 'normale',
            ];
        }
        if ($people !== [] && $photoCount === 0) {
            $candidates[] = [
                'kind' => 'besoin',
                'title' => 'Photographie manquante',
                'body' => 'BESOIN — Disposer d’une photographie exploitable de la cible ou du site.',
                'priority' => 'faible',
            ];
        }
        if ($assessments === [] && ($people !== [] || $sites !== [])) {
            $candidates[] = [
                'kind' => 'besoin',
                'title' => 'Appréciation analytique absente',
                'body' => 'BESOIN PRIORITAIRE — Porter une appréciation structurée (fait → source → confiance → hypothèse).',
                'priority' => 'haute',
            ];
        }
        foreach ($assessments as $a) {
            $corr = trim((string) ($a['corroboration_text'] ?? ''));
            if ($corr === '' || preg_match('/source\s+unique/iu', $corr)) {
                $candidates[] = [
                    'kind' => 'critere',
                    'title' => 'Recoupement indépendant requis',
                    'body' => 'CRITÈRE DE CONFIRMATION — L’hypothèse sera consolidée après un second élément indépendant.',
                    'priority' => 'haute',
                ];
                break;
            }
        }

        $out = [];
        foreach ($candidates as $c) {
            $key = mb_strtolower($c['title']);
            if (isset($existingTitles[$key])) {
                continue;
            }
            $out[] = $c;
        }

        return $out;
    }

    /**
     * @param array<string,mixed>|null $prev
     * @param array<string,int> $now
     * @return array<string,mixed>
     */
    private function buildDigest(?array $prev, array $now): array
    {
        $prevCounts = is_array($prev['digest']['counts'] ?? null) ? $prev['digest']['counts'] : [];
        $changes = [];
        foreach ($now as $k => $v) {
            $before = (int) ($prevCounts[$k] ?? 0);
            $delta = $v - $before;
            if ($delta !== 0) {
                $changes[$k] = $delta;
            }
        }
        $parts = [];
        $labels = [
            'people' => 'personne(s)',
            'sites' => 'site(s)',
            'evidence' => 'pièce(s)',
            'relations' => 'lien(s)',
            'assessments' => 'appréciation(s)',
            'gaps_open' => 'lacune(s) ouverte(s)',
            'suggestions' => 'rapprochement(s) en attente',
        ];
        foreach ($changes as $k => $delta) {
            $sign = $delta > 0 ? '+' : '';
            $parts[] = $sign . $delta . ' ' . ($labels[$k] ?? $k);
        }

        return [
            'counts' => $now,
            'changes' => $changes,
            'summary' => $parts === [] ? 'Aucun changement comptable depuis le dernier calcul.' : implode(', ', $parts),
            'at' => date('c'),
        ];
    }

    /** @param list<array<string,mixed>> $evidence */
    private function countEvidenceKind(array $evidence, string $needle): int
    {
        $n = 0;
        foreach ($evidence as $e) {
            $blob = strtolower((string) ($e['label'] ?? '') . ' ' . (string) ($e['caption'] ?? '') . ' ' . (string) ($e['kind'] ?? ''));
            if (str_contains($blob, $needle) || str_contains($blob, 'photo') || str_contains($blob, 'image')) {
                $n++;
            }
        }

        return $n;
    }

    /** @return array<string,mixed> */
    private function step(string $key, string $label, string $hint, int $count, bool $required, string $href, int $weight): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'hint' => $hint,
            'count' => $count,
            'unit' => '',
            'required' => $required,
            'href' => $href,
            'action' => $required && $count < 1 ? 'Compléter' : 'Ouvrir',
            'weight' => $weight,
        ];
    }
}
