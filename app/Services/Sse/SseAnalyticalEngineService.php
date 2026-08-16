<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Database;
use App\Repositories\SseAnalyticalRepository;
use App\Repositories\SseCaseRepository;
use App\Repositories\SsePersonRepository;
use App\Repositories\SseSiteRepository;
use App\Repositories\SseSuggestionQueueRepository;
use App\Repositories\SseWatchlistRepository;

/**
 * Pipeline analytique SSE.
 *
 * INGESTION → NORMALISATION → DÉDOUBLONNAGE → CORRÉLATION → CONTRADICTIONS
 * → SCORING → ALERTES → SYNTHÈSE
 *
 * Le moteur ne décide jamais qu’une hypothèse est vraie. Il produit
 * « rapprochement suggéré », « anomalie », « convergence », « hypothèse à examiner ».
 * Seule la validation humaine crée une relation analytique.
 */
final class SseAnalyticalEngineService
{
    public function __construct(
        private ?Database $db = null,
        private ?SseCaseRepository $cases = null,
        private ?SsePersonRepository $persons = null,
        private ?SseSiteRepository $sites = null,
        private ?SseWatchlistRepository $watchlist = null,
        private ?SseCrossMatchService $cross = null,
        private ?SseAnalyticalRepository $analytical = null,
        private ?SseSuggestionQueueRepository $queue = null,
        private ?SseCompletenessService $completeness = null,
        private ?SseCorrelationService $correlation = null,
    ) {
        $this->db ??= Database::getInstance();
        $this->cases ??= new SseCaseRepository();
        $this->persons ??= new SsePersonRepository();
        $this->sites ??= new SseSiteRepository();
        $this->watchlist ??= new SseWatchlistRepository();
        $this->cross ??= new SseCrossMatchService();
        $this->analytical ??= new SseAnalyticalRepository();
        $this->queue ??= new SseSuggestionQueueRepository();
        $this->completeness ??= new SseCompletenessService();
        $this->correlation ??= new SseCorrelationService();
    }

    /**
     * Exécute le pipeline pour un tenant.
     *
     * @return array{ok:bool,run_id:string,summary:string,details:array<string,int>}
     */
    public function runForTenant(int $tenantId, ?string $runId = null): array
    {
        $runId ??= 'sse-' . date('YmdHis') . '-' . $tenantId;
        $stats = [
            'cases' => 0,
            'suggestions' => 0,
            'signals' => 0,
            'gaps' => 0,
            'scored' => 0,
        ];

        $caseRows = $this->cases->listForTenant($tenantId, null, ['is_folder' => 0]);
        $stats['cases'] = count($caseRows);

        // Index personnes → dossiers pour multi-dossier
        $personCases = $this->indexPersonCases($tenantId, $caseRows);

        foreach ($caseRows as $case) {
            $caseId = (int) ($case['id'] ?? 0);
            if ($caseId < 1) {
                continue;
            }
            if (in_array((string) ($case['status'] ?? ''), ['archive'], true)) {
                $this->stageReopenHints($tenantId, $case, $personCases, $runId, $stats);
                continue;
            }

            $this->stageDedupAndCorrelation($tenantId, $case, $personCases, $runId, $stats);
            $this->stageContradictionsAndAging($tenantId, $case, $runId, $stats);
            $this->stageScoringAndGaps($tenantId, $case, $runId, $stats);
        }

        $this->stageCrossCaseSimilarity($tenantId, $caseRows, $runId, $stats);
        $this->stageWatchlistSweep($tenantId, $runId, $stats);
        $this->stagePreSseIntel($tenantId, $runId, $stats);

        return [
            'ok' => true,
            'run_id' => $runId,
            'summary' => sprintf(
                'Dossiers %d · suggestions %d · signaux %d · lacunes %d · scores %d',
                $stats['cases'],
                $stats['suggestions'],
                $stats['signals'],
                $stats['gaps'],
                $stats['scored']
            ),
            'details' => $stats,
        ];
    }

    /**
     * Validation humaine d’une suggestion → relation éventuelle + décision registre.
     *
     * @return array{ok:bool,error?:string}
     */
    public function acceptSuggestion(int $tenantId, int $suggestionId, string $authorLabel, ?int $userId = null): array
    {
        $s = $this->queue->findSuggestion($tenantId, $suggestionId);
        if ($s === null || ($s['status'] ?? '') !== 'pending') {
            return ['ok' => false, 'error' => 'Proposition introuvable ou déjà traitée.'];
        }

        if (!$this->queue->decide($tenantId, $suggestionId, 'accepted', $authorLabel, $userId)) {
            return ['ok' => false, 'error' => 'Enregistrement de la décision impossible.'];
        }

        $caseId = (int) ($s['case_id'] ?? 0);
        $kind = (string) ($s['kind'] ?? '');

        // Pose une relation analytique seulement si un dossier porte la suggestion
        // et que les deux côtés sont des objets SSE connus — jamais une fusion.
        if ($caseId > 0 && in_array($kind, ['name_near', 'co_presence', 'duplicate_bio', 'same_site', 'cross_case_person', 'watchlist'], true)) {
            $reliability = match ((string) ($s['confidence'] ?? '')) {
                'confirme_candidat', 'probable' => 'corroborated',
                default => 'unverified',
            };
            try {
                $this->correlation->addRelation($tenantId, $caseId, [
                    'from_type' => (string) $s['left_type'],
                    'from_id' => (int) $s['left_id'],
                    'to_type' => (string) $s['right_type'],
                    'to_id' => (int) $s['right_id'],
                    'relation' => $this->relationLabelForKind($kind),
                    'reliability' => $reliability,
                    'source' => 'regle',
                    'note' => 'Validé depuis proposition moteur : ' . (string) ($s['reason'] ?? ''),
                    'author_label' => $authorLabel,
                ]);
            } catch (\Throwable) {
                // La décision reste consignée même si la relation échoue.
            }
        }

        if ($caseId > 0) {
            $this->analytical->recordDecision($tenantId, $caseId, [
                'decision_domain' => $kind === 'merge_suggest' ? 'fusion' : 'rattachement',
                'subject_label' => (string) ($s['title'] ?? 'Rapprochement'),
                'value_before' => SseSuggestionQueueRepository::CONFIDENCE[(string) ($s['confidence'] ?? '')] ?? 'Proposition',
                'value_after' => 'Validé par analyste',
                'reason' => (string) ($s['reason'] ?? 'Validation de proposition moteur'),
                'author_label' => $authorLabel,
                'decided_by' => $userId,
            ]);

            if ($kind === 'merge_suggest' && !empty($s['related_case_id'])) {
                $this->analytical->createCaseLink($tenantId, $caseId, [
                    'related_case_id' => (int) $s['related_case_id'],
                    'relation_type' => 'doublon_potentiel',
                    'note' => 'Fusion suggérée validée comme doublon potentiel — fusion effective réservée à une action distincte.',
                    'author_label' => $authorLabel,
                    'created_by' => $userId,
                ]);
            }
        }

        return ['ok' => true];
    }

    public function rejectSuggestion(int $tenantId, int $suggestionId, string $authorLabel, ?int $userId = null): array
    {
        $s = $this->queue->findSuggestion($tenantId, $suggestionId);
        if ($s === null || ($s['status'] ?? '') !== 'pending') {
            return ['ok' => false, 'error' => 'Proposition introuvable ou déjà traitée.'];
        }
        if (!$this->queue->decide($tenantId, $suggestionId, 'rejected', $authorLabel, $userId)) {
            return ['ok' => false, 'error' => 'Rejet impossible.'];
        }
        $caseId = (int) ($s['case_id'] ?? 0);
        if ($caseId > 0) {
            $this->analytical->recordDecision($tenantId, $caseId, [
                'decision_domain' => 'rattachement',
                'subject_label' => (string) ($s['title'] ?? 'Rapprochement'),
                'value_before' => 'Proposition',
                'value_after' => 'Rejeté',
                'reason' => (string) ($s['reason'] ?? 'Rejet opérateur'),
                'author_label' => $authorLabel,
                'decided_by' => $userId,
            ]);
        }

        return ['ok' => true];
    }

    // ── Stages ─────────────────────────────────────────────────────────────

    /**
     * @param list<array<string,mixed>> $caseRows
     * @param array<int,list<int>> $personCases
     * @param array<string,int> $stats
     */
    private function stageDedupAndCorrelation(int $tenantId, array $case, array $personCases, string $runId, array &$stats): void
    {
        $caseId = (int) $case['id'];
        $links = $this->cases->listLinkedPersonIds($caseId, $tenantId);
        $people = [];
        foreach ($links as $link) {
            $p = $this->persons->findById((int) $link['person_id'], $tenantId);
            if ($p) {
                $people[] = $p;
            }
        }

        // Multi-dossier : même personne dans N dossiers
        foreach ($people as $p) {
            $pid = (int) ($p['id'] ?? 0);
            $casesForPerson = $personCases[$pid] ?? [];
            if (count($casesForPerson) >= 2) {
                foreach ($casesForPerson as $otherCaseId) {
                    if ($otherCaseId === $caseId) {
                        continue;
                    }
                    $id = $this->queue->upsertSuggestion($tenantId, [
                        'case_id' => $caseId,
                        'related_case_id' => $otherCaseId,
                        'left_type' => 'person',
                        'left_id' => $pid,
                        'right_type' => 'case',
                        'right_id' => $otherCaseId,
                        'kind' => 'cross_case_person',
                        'score' => min(95, 50 + (count($casesForPerson) * 10)),
                        'confidence' => count($casesForPerson) >= 3 ? 'probable' : 'possible',
                        'title' => sprintf('Cette personne apparaît dans %d dossiers', count($casesForPerson)),
                        'reason' => 'Rapprochement inter-dossiers — à valider, sans fusion automatique.',
                        'rule_key' => 'cross_case_person',
                        'run_id' => $runId,
                        'evidence' => ['case_ids' => $casesForPerson],
                    ]);
                    if ($id) {
                        $stats['suggestions']++;
                    }
                }
            }
        }

        // Noms proches intra-dossier
        $n = count($people);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $a = $people[$i];
                $b = $people[$j];
                $score = $this->nameSimilarity(
                    (string) ($a['display_name'] ?? $a['full_name'] ?? ''),
                    (string) ($b['display_name'] ?? $b['full_name'] ?? '')
                );
                if ($score >= 70) {
                    $id = $this->queue->upsertSuggestion($tenantId, [
                        'case_id' => $caseId,
                        'left_type' => 'person',
                        'left_id' => (int) $a['id'],
                        'right_type' => 'person',
                        'right_id' => (int) $b['id'],
                        'kind' => 'name_near',
                        'score' => $score,
                        'confidence' => $score >= 85 ? 'probable' : 'possible',
                        'title' => 'Identités potentiellement proches',
                        'reason' => 'Similarité nominale détectée — doublon possible, pas un fait.',
                        'rule_key' => 'name_near',
                        'run_id' => $runId,
                    ]);
                    if ($id) {
                        $stats['suggestions']++;
                    }
                }
            }
        }

        // Sites partagés → signal cluster léger
        $sites = $this->sites->listForCase($caseId, $tenantId);
        if (count($people) >= 2 && count($sites) >= 1) {
            $this->queue->addSignal($tenantId, [
                'case_id' => $caseId,
                'signal_type' => 'cluster',
                'severity' => 'info',
                'title' => 'Cluster potentiel personnes ↔ sites',
                'detail' => sprintf('%d personnes et %d sites co-présents dans le dossier — hypothèse de réseau à examiner.', count($people), count($sites)),
                'rule_key' => 'cluster_case',
                'run_id' => $runId,
            ]);
            $stats['signals']++;
        }
    }

    /** @param array<string,int> $stats */
    private function stageContradictionsAndAging(int $tenantId, array $case, string $runId, array &$stats): void
    {
        $caseId = (int) $case['id'];
        $assessments = $this->analytical->listAssessments($tenantId, $caseId);
        $active = array_values(array_filter($assessments, static fn (array $a): bool => ($a['status'] ?? '') === 'active'));

        $singleSource = 0;
        $byHyp = [];
        foreach ($active as $a) {
            $corr = trim((string) ($a['corroboration_text'] ?? ''));
            if ($corr === '' || preg_match('/source\s+unique|aucun\s+recoup/iu', $corr)) {
                $singleSource++;
            }
            if (!empty($a['divergence_code'])) {
                // Traité plus bas en agrégat — pas de suggestion left=right.
            }
            $hyp = (string) ($a['hypothesis_code'] ?? 'H1');
            $byHyp[$hyp][] = $a;

            // Vieillissement
            $temp = (string) ($a['temporality'] ?? '');
            $created = strtotime((string) ($a['created_at'] ?? '')) ?: 0;
            $ageDays = $created > 0 ? (int) floor((time() - $created) / 86400) : 0;
            if ($temp === 'ancien' || $ageDays >= 30) {
                $this->queue->addSignal($tenantId, [
                    'case_id' => $caseId,
                    'signal_type' => 'aging',
                    'severity' => $ageDays >= 60 ? 'medium' : 'info',
                    'title' => 'Renseignement vieilli — valeur historique conservée',
                    'detail' => sprintf('Appréciation #%d âgée de %d jours. Caractère « actuel » à réévaluer.', (int) $a['id'], $ageDays),
                    'rule_key' => 'intel_aging',
                    'run_id' => $runId,
                ]);
                $stats['signals']++;
            }
        }

        // Fix contradiction suggestions with distinct ids when multiple divergent
        $divergent = array_values(array_filter($active, static fn (array $a): bool => !empty($a['divergence_code'])));
        if (count($divergent) >= 2) {
            $id = $this->queue->upsertSuggestion($tenantId, [
                'case_id' => $caseId,
                'left_type' => 'assessment',
                'left_id' => (int) $divergent[0]['id'],
                'right_type' => 'assessment',
                'right_id' => (int) $divergent[1]['id'],
                'kind' => 'contradiction',
                'score' => 85,
                'confidence' => 'probable',
                'title' => 'Deux informations ne peuvent simultanément être vraies',
                'reason' => 'Divergences portées sur des appréciations actives — arbitrage requis.',
                'rule_key' => 'dual_divergence',
                'run_id' => $runId,
            ]);
            if ($id) {
                $stats['suggestions']++;
            }
        } elseif (count($divergent) === 1) {
            $this->queue->addSignal($tenantId, [
                'case_id' => $caseId,
                'signal_type' => 'anomaly',
                'severity' => 'high',
                'title' => 'Contradiction non résolue',
                'detail' => (string) ($divergent[0]['divergence_label'] ?? ''),
                'rule_key' => 'single_divergence',
                'run_id' => $runId,
            ]);
            $stats['signals']++;
        }

        if ($singleSource > 0) {
            $this->queue->addSignal($tenantId, [
                'case_id' => $caseId,
                'signal_type' => 'single_source',
                'severity' => 'high',
                'title' => 'Alerte source unique',
                'detail' => sprintf('%d appréciation(s) importante(s) sans recoupement indépendant.', $singleSource),
                'rule_key' => 'single_source_alert',
                'run_id' => $runId,
            ]);
            $stats['signals']++;
        }

        // Convergence : plusieurs H1 avec confiance élevée
        $h1High = array_values(array_filter(
            $byHyp['H1'] ?? [],
            static fn (array $a): bool => ($a['confidence'] ?? '') === 'eleve'
        ));
        if (count($h1High) >= 2) {
            $id = $this->queue->upsertSuggestion($tenantId, [
                'case_id' => $caseId,
                'left_type' => 'assessment',
                'left_id' => (int) $h1High[0]['id'],
                'right_type' => 'assessment',
                'right_id' => (int) $h1High[1]['id'],
                'kind' => 'convergence',
                'score' => 75,
                'confidence' => 'probable',
                'title' => 'Convergence — plusieurs éléments indépendants décrivent le même fait',
                'reason' => 'À examiner : ne transforme pas automatiquement la confiance.',
                'rule_key' => 'convergence_h1',
                'run_id' => $runId,
            ]);
            if ($id) {
                $stats['suggestions']++;
            }
        }
    }

    /** @param array<string,int> $stats */
    private function stageScoringAndGaps(int $tenantId, array $case, string $runId, array &$stats): void
    {
        $caseId = (int) $case['id'];
        $eval = $this->completeness->evaluate($tenantId, $case);
        $stats['scored']++;

        if (($eval['digest']['changes'] ?? []) !== []) {
            $this->queue->addSignal($tenantId, [
                'case_id' => $caseId,
                'signal_type' => 'digest',
                'severity' => 'info',
                'title' => 'Évolutions depuis le dernier calcul',
                'detail' => (string) ($eval['digest']['summary'] ?? ''),
                'rule_key' => 'case_digest',
                'run_id' => $runId,
                'payload' => $eval['digest'],
            ]);
            $stats['signals']++;
        }

        foreach (array_slice($eval['gaps'] ?? [], 0, 5) as $gap) {
            $priorityMap = [
                'critique' => 'critique',
                'haute' => 'prioritaire',
                'normale' => 'normale',
                'faible' => 'basse',
            ];
            $prio = $priorityMap[(string) ($gap['priority'] ?? 'normale')] ?? 'normale';
            $this->queue->addSignal($tenantId, [
                'case_id' => $caseId,
                'signal_type' => 'gap_auto',
                'severity' => in_array($prio, ['critique', 'prioritaire'], true) ? 'high' : 'medium',
                'title' => 'Lacune / besoin proposé : ' . (string) ($gap['title'] ?? ''),
                'detail' => (string) ($gap['body'] ?? ''),
                'rule_key' => 'auto_gap',
                'run_id' => $runId,
            ]);
            $stats['signals']++;

            // Seules les lacunes critiques / hautes sont portées au registre (le reste reste signal).
            if (!in_array($prio, ['critique', 'prioritaire'], true)) {
                continue;
            }
            $result = $this->analytical->createGap($tenantId, $caseId, [
                'kind' => $gap['kind'] ?? 'lacune',
                'title' => $gap['title'] ?? 'Lacune',
                'body' => $gap['body'] ?? '',
                'priority' => $prio,
                'status' => 'ouvert',
                'author_label' => 'Moteur SSE',
            ]);
            if (!empty($result['ok'])) {
                $stats['gaps']++;
            }
        }

        if (($eval['score'] ?? 0) < 40) {
            $this->queue->addSignal($tenantId, [
                'case_id' => $caseId,
                'signal_type' => 'completeness',
                'severity' => 'medium',
                'title' => sprintf('Complétude faible (%d/100)', (int) $eval['score']),
                'detail' => 'Le dossier présente des trous majeurs (identité, sites, sources ou pièces).',
                'rule_key' => 'low_completeness',
                'run_id' => $runId,
            ]);
            $stats['signals']++;
        }
    }

    /**
     * @param list<array<string,mixed>> $caseRows
     * @param array<string,int> $stats
     */
    private function stageCrossCaseSimilarity(int $tenantId, array $caseRows, string $runId, array &$stats): void
    {
        $n = count($caseRows);
        for ($i = 0; $i < min($n, 40); $i++) {
            for ($j = $i + 1; $j < min($n, 40); $j++) {
                $a = $caseRows[$i];
                $b = $caseRows[$j];
                if (in_array((string) ($a['status'] ?? ''), ['archive'], true)
                    && in_array((string) ($b['status'] ?? ''), ['archive'], true)) {
                    continue;
                }
                $score = $this->caseTitleSimilarity(
                    (string) ($a['title'] ?? ''),
                    (string) ($b['title'] ?? ''),
                    (string) ($a['summary'] ?? ''),
                    (string) ($b['summary'] ?? '')
                );
                if ($score < 72) {
                    continue;
                }
                $id = $this->queue->upsertSuggestion($tenantId, [
                    'case_id' => (int) $a['id'],
                    'related_case_id' => (int) $b['id'],
                    'left_type' => 'case',
                    'left_id' => (int) $a['id'],
                    'right_type' => 'case',
                    'right_id' => (int) $b['id'],
                    'kind' => 'merge_suggest',
                    'score' => $score,
                    'confidence' => $score >= 85 ? 'probable' : 'possible',
                    'title' => 'Fusion de dossiers suggérée (jamais automatique)',
                    'reason' => sprintf(
                        'Similarité entre %s et %s — validation opérateur obligatoire.',
                        (string) ($a['reference_code'] ?? ''),
                        (string) ($b['reference_code'] ?? '')
                    ),
                    'rule_key' => 'case_similarity',
                    'run_id' => $runId,
                ]);
                if ($id) {
                    $stats['suggestions']++;
                }
            }
        }
    }

    /** @param array<string,int> $stats */
    private function stageWatchlistSweep(int $tenantId, string $runId, array &$stats): void
    {
        try {
            $hits = $this->cross->matchPersonsAgainstWatchlist($tenantId, 1);
        } catch (\Throwable) {
            return;
        }
        foreach ($hits as $row) {
            $person = $row['person'] ?? [];
            $pid = (int) ($person['id'] ?? 0);
            foreach ($row['matches'] ?? [] as $m) {
                $entry = $m['entry'] ?? [];
                $eid = (int) ($entry['id'] ?? 0);
                $score = (int) ($m['score'] ?? 0);
                if ($pid < 1 || $eid < 1 || $score < 60) {
                    continue;
                }
                $id = $this->queue->upsertSuggestion($tenantId, [
                    'left_type' => 'person',
                    'left_id' => $pid,
                    'right_type' => 'watchlist',
                    'right_id' => $eid,
                    'kind' => 'watchlist',
                    'score' => $score,
                    'confidence' => $score >= 85 ? 'confirme_candidat' : ($score >= 70 ? 'probable' : 'possible'),
                    'title' => 'Correspondance liste de surveillance',
                    'reason' => (string) ($m['reason'] ?? 'Croisement nominatif'),
                    'rule_key' => 'watchlist_match',
                    'run_id' => $runId,
                ]);
                if ($id) {
                    $stats['suggestions']++;
                }
            }
        }
    }

    /**
     * Pont léger intel terrain → signaux pré-SSE (pas de création de dossier auto).
     *
     * @param array<string,int> $stats
     */
    private function stagePreSseIntel(int $tenantId, string $runId, array &$stats): void
    {
        try {
            $rows = $this->db->fetchAll(
                'SELECT id, target_type, grid, note, created_at FROM intel_reports
                  WHERE tenant_id = :t AND created_at >= (NOW() - INTERVAL 2 DAY)
                  ORDER BY id DESC LIMIT 80',
                ['t' => $tenantId]
            );
        } catch (\Throwable) {
            return;
        }
        if ($rows === []) {
            return;
        }

        $byType = [];
        foreach ($rows as $r) {
            $t = strtoupper((string) ($r['target_type'] ?? 'UNKNOWN'));
            $byType[$t] = ($byType[$t] ?? 0) + 1;
        }
        $parts = [];
        foreach ($byType as $t => $c) {
            $parts[] = $c . '× ' . $t;
        }
        $this->queue->addSignal($tenantId, [
            'signal_type' => 'digest',
            'severity' => 'info',
            'title' => 'Intel terrain récent (pré-SSE)',
            'detail' => 'Rapports fusionnés C2 des 48 h : ' . implode(', ', $parts)
                . '. Aucune fiche SSE créée automatiquement — à verser / rapprocher par un analyste.',
            'rule_key' => 'pre_sse_intel',
            'run_id' => $runId,
            'payload' => ['counts' => $byType, 'sample_ids' => array_map(static fn ($r) => (int) $r['id'], array_slice($rows, 0, 10))],
        ]);
        $stats['signals']++;

        foreach (array_slice($rows, 0, 15) as $r) {
            $type = strtoupper((string) ($r['target_type'] ?? ''));
            if (!in_array($type, ['INFANTRY', 'VEHICLE', 'ARMOR', 'AIR_DEFENSE'], true)) {
                continue;
            }
            $rid = (int) $r['id'];
            $bucket = abs(crc32($type . ':' . (string) ($r['grid'] ?? ''))) % 100000 + 1;
            $id = $this->queue->upsertSuggestion($tenantId, [
                'left_type' => 'intel_report',
                'left_id' => $rid,
                'right_type' => 'intel_bucket',
                'right_id' => $bucket,
                'kind' => 'intel_pre_sse',
                'score' => 55,
                'confidence' => 'possible',
                'title' => 'Intel ' . $type . ' à verser au SSE',
                'reason' => 'Rapport terrain brut — proposition d’exploitation, sans création automatique de dossier.',
                'rule_key' => 'intel_pre_sse',
                'run_id' => $runId,
                'evidence' => ['grid' => $r['grid'] ?? null],
            ]);
            if ($id) {
                $stats['suggestions']++;
            }
        }
    }

    /**
     * @param array<int,list<int>> $personCases
     * @param array<string,int> $stats
     */
    private function stageReopenHints(int $tenantId, array $case, array $personCases, string $runId, array &$stats): void
    {
        $caseId = (int) $case['id'];
        $links = $this->cases->listLinkedPersonIds($caseId, $tenantId);
        foreach ($links as $link) {
            $pid = (int) ($link['person_id'] ?? 0);
            $others = array_filter($personCases[$pid] ?? [], static fn (int $cid): bool => $cid !== $caseId);
            if ($others === []) {
                continue;
            }
            $otherId = (int) array_values($others)[0];
            $id = $this->queue->upsertSuggestion($tenantId, [
                'case_id' => $caseId,
                'related_case_id' => $otherId,
                'left_type' => 'case',
                'left_id' => $caseId,
                'right_type' => 'case',
                'right_id' => $otherId,
                'kind' => 'reopen_suggest',
                'score' => 70,
                'confidence' => 'possible',
                'title' => 'Réouverture suggérée d’un dossier archivé',
                'reason' => 'Élément fortement corrélé apparu ailleurs — suggestion uniquement.',
                'rule_key' => 'reopen_archived',
                'run_id' => $runId,
            ]);
            if ($id) {
                $stats['suggestions']++;
            }
            break;
        }
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * @param list<array<string,mixed>> $caseRows
     * @return array<int,list<int>>
     */
    private function indexPersonCases(int $tenantId, array $caseRows): array
    {
        $map = [];
        foreach ($caseRows as $case) {
            $caseId = (int) ($case['id'] ?? 0);
            foreach ($this->cases->listLinkedPersonIds($caseId, $tenantId) as $link) {
                $pid = (int) ($link['person_id'] ?? 0);
                if ($pid < 1) {
                    continue;
                }
                $map[$pid] ??= [];
                if (!in_array($caseId, $map[$pid], true)) {
                    $map[$pid][] = $caseId;
                }
            }
        }

        return $map;
    }

    private function nameSimilarity(string $a, string $b): int
    {
        $a = $this->norm($a);
        $b = $this->norm($b);
        if ($a === '' || $b === '' || mb_strlen($a) < 3 || mb_strlen($b) < 3) {
            return 0;
        }
        if ($a === $b) {
            return 100;
        }
        similar_text($a, $b, $pct);

        return (int) round($pct);
    }

    private function caseTitleSimilarity(string $t1, string $t2, string $s1, string $s2): int
    {
        $a = $this->norm($t1 . ' ' . mb_substr($s1, 0, 120));
        $b = $this->norm($t2 . ' ' . mb_substr($s2, 0, 120));
        if ($a === '' || $b === '') {
            return 0;
        }
        similar_text($a, $b, $pct);

        return (int) round($pct);
    }

    private function norm(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;

        return $s;
    }

    private function relationLabelForKind(string $kind): string
    {
        return match ($kind) {
            'name_near', 'duplicate_bio' => 'meme_individu',
            'co_presence' => 'co_presence',
            default => 'associe',
        };
    }
}
