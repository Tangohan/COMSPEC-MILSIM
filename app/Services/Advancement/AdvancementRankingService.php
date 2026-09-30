<?php

declare(strict_types=1);

namespace App\Services\Advancement;

/**
 * Classement des candidatures et ordre des grades : propositions + détections.
 */
final class AdvancementRankingService
{
    /**
     * @param list<array<string, mixed>> $candidacies
     * @param list<array<string, mixed>> $members
     * @return array{ranks: array<int, int>, detections: list<array{code:string, level:string, message:string}>}
     */
    public function proposeCandidacyOrder(array $candidacies, array $members = [], ?int $quota = null): array
    {
        $sortable = $candidacies;
        usort($sortable, [$this, 'compareCandidacies']);
        $ranks = [];
        $n = 0;
        foreach ($sortable as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $n++;
            $ranks[$id] = $n;
        }

        return [
            'ranks' => $ranks,
            'detections' => $this->detectCandidacies($candidacies, $members, $quota, $ranks),
        ];
    }

    /**
     * @param list<array<string, mixed>> $candidacies
     * @param list<array<string, mixed>> $members
     * @param array<int, int>|null $proposedRanks
     * @return list<array{code:string, level:string, message:string}>
     */
    public function detectCandidacies(array $candidacies, array $members = [], ?int $quota = null, ?array $proposedRanks = null): array
    {
        $out = [];
        $byRank = [];
        $listed = 0;
        $memberIds = [];
        foreach ($members as $member) {
            $pid = (int) ($member['personnel_id'] ?? 0);
            if ($pid > 0) {
                $memberIds[$pid] = true;
            }
        }
        foreach ($candidacies as $row) {
            $name = $this->personName($row);
            $rank = $row['preference_rank'] ?? null;
            $rank = ($rank === null || $rank === '') ? null : (int) $rank;
            $eligible = !empty($row['is_eligible']) || !empty($row['live_eligible']);
            $forced = $this->isForced($row);
            $decision = (string) ($row['decision'] ?? '');
            $opinion = (string) ($row['commission_opinion'] ?? '');
            $pid = (int) ($row['personnel_id'] ?? 0);

            if ($rank !== null) {
                $byRank[$rank][] = $name;
            }
            if ($decision === AdvancementWorkflowService::DECISION_LISTED) {
                $listed++;
                if (!$eligible && !$forced) {
                    $out[] = $this->hit('ineligible_listed', 'danger', $name . ' est inscrit alors qu’il n’est pas éligible. Cochez le passage exceptionnel et justifiez, ou retirez l’inscription.');
                }
                if ($forced && trim((string) ($row['exceptional_reason'] ?? '')) === '') {
                    $out[] = $this->hit('exception_sans_motif', 'danger', $name . ' : un passage exceptionnel exige un motif.');
                }
                if ($opinion === '') {
                    $out[] = $this->hit('inscrit_sans_avis', 'warn', $name . ' est inscrit sans avis de commission.');
                }
            }
            if ($forced && $decision !== AdvancementWorkflowService::DECISION_LISTED && $decision !== AdvancementWorkflowService::DECISION_NOT) {
                $out[] = $this->hit('exception_sans_decision', 'warn', $name . ' a un passage exceptionnel sans décision d’inscription.');
            }
            if (isset($memberIds[$pid])) {
                $out[] = $this->hit('membre_candidat', 'warn', $name . ' siège à la commission et est aussi candidat. Un suppléant devrait le remplacer pour cet avis.');
            }
            if ($proposedRanks !== null) {
                $id = (int) ($row['id'] ?? 0);
                $suggested = $proposedRanks[$id] ?? null;
                if ($suggested !== null && $rank !== null && $rank !== $suggested && $eligible) {
                    $out[] = $this->hit('ecart_anciennete', 'info', $name . ' est classé ' . $rank . ' ; l’ancienneté suggère ' . $suggested . '.');
                }
            }
        }
        foreach ($byRank as $rank => $names) {
            if (count($names) > 1) {
                $out[] = $this->hit('rang_double', 'danger', 'Le rang ' . $rank . ' est attribué à ' . implode(', ', $names) . '.');
            }
        }
        $used = array_keys($byRank);
        sort($used);
        if ($used !== []) {
            $expected = range(1, max($used));
            $missing = array_values(array_diff($expected, $used));
            if ($missing !== []) {
                $out[] = $this->hit('rang_trou', 'warn', 'Trous dans le classement : rangs ' . implode(', ', $missing) . ' absents.');
            }
        }
        if ($quota !== null && $quota >= 0 && $listed > $quota) {
            $out[] = $this->hit('quota', 'danger', 'Inscriptions (' . $listed . ') au-delà du quota (' . $quota . ').');
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $grades
     * @return array{order: list<int>, detections: list<array{code:string, level:string, message:string}>}
     */
    public function proposeGradeOrder(array $grades): array
    {
        $groups = [];
        foreach ($grades as $row) {
            if (!empty($row['archived_at'])) {
                continue;
            }
            $key = (string) ((int) ($row['filiere_id'] ?? 0));
            $groups[$key][] = $row;
        }
        $order = [];
        $detections = [];
        foreach ($groups as $rows) {
            $current = [];
            foreach ($rows as $row) {
                $fid = (int) ($row['id'] ?? 0);
                if ($fid > 0) {
                    $current[(int) ($row['rank_order'] ?? 0)][] = (string) ($row['code'] ?? $row['label'] ?? $fid);
                }
            }
            foreach ($current as $rank => $codes) {
                if ($rank > 0 && count($codes) > 1) {
                    $detections[] = $this->hit('grade_rang_double', 'danger', 'Ordre ' . $rank . ' en double : ' . implode(', ', $codes) . '.');
                }
            }
            usort($rows, fn (array $a, array $b): int => $this->gradeScore($a) <=> $this->gradeScore($b));
            $i = 0;
            foreach ($rows as $row) {
                $id = (int) ($row['id'] ?? 0);
                if ($id < 1) {
                    continue;
                }
                $i++;
                $order[] = $id;
                $have = (int) ($row['rank_order'] ?? 0);
                if ($have !== $i) {
                    $detections[] = $this->hit(
                        'grade_ordre_detecte',
                        'info',
                        (string) ($row['code'] ?? $row['label'] ?? $id) . ' passerait de l’ordre ' . $have . ' à ' . $i . ' (détection de hiérarchie).'
                    );
                }
            }
        }

        return ['order' => $order, 'detections' => $detections];
    }

    /**
     * @param array<string, mixed> $row
     */
    public function isForced(array $row): bool
    {
        return !empty($row['exceptional_override']) && trim((string) ($row['exceptional_reason'] ?? '')) !== '';
    }

    /**
     * @param array<string, mixed> $grade
     */
    public function gradeScore(array $grade): int
    {
        $code = strtoupper(trim((string) ($grade['code'] ?? '')));
        $known = $this->codeScores();
        if ($code !== '' && isset($known[$code])) {
            return $known[$code];
        }
        $blob = strtoupper(trim(
            (string) ($grade['code'] ?? '') . ' ' . (string) ($grade['label'] ?? '') . ' ' . (string) ($grade['short_label'] ?? '') . ' ' . (string) ($grade['label_otan'] ?? '')
        ));
        if (preg_match('/(?:OR|OF|E|O)-(\d+)/', $blob, $m)) {
            $n = (int) $m[1];
            if (str_contains($blob, 'OF-') || preg_match('/\bO-\d/', $blob)) {
                return 200 + $n;
            }

            return 50 + $n;
        }
        foreach ($known as $hint => $score) {
            if ($hint !== '' && str_contains($blob, $hint)) {
                return $score;
            }
        }

        return 1000 + (int) ($grade['rank_order'] ?? 0);
    }

    /**
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     */
    private function compareCandidacies(array $a, array $b): int
    {
        $scoreA = $this->candidacyScore($a);
        $scoreB = $this->candidacyScore($b);
        if ($scoreA !== $scoreB) {
            return $scoreB <=> $scoreA;
        }
        $volA = (string) ($a['volunteered_at'] ?? '');
        $volB = (string) ($b['volunteered_at'] ?? '');
        if ($volA !== $volB) {
            return $volA <=> $volB;
        }

        return $this->personName($a) <=> $this->personName($b);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function candidacyScore(array $row): int
    {
        $eligible = !empty($row['is_eligible']) || !empty($row['live_eligible']);
        $forced = $this->isForced($row);
        $months = (int) ($row['months_in_grade'] ?? 0);
        $base = 0;
        if ($eligible) {
            $base = 10000;
        } elseif ($forced) {
            $base = 8000;
        }

        return $base + $months;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function personName(array $row): string
    {
        $name = trim((string) ($row['display_name'] ?? ''));
        if ($name === '') {
            $name = trim((string) ($row['callsign'] ?? ''));
        }
        if ($name === '') {
            $name = trim((string) ($row['email'] ?? 'Personnel'));
        }

        return $name;
    }

    /**
     * @return array{code:string, level:string, message:string}
     */
    private function hit(string $code, string $level, string $message): array
    {
        return ['code' => $code, 'level' => $level, 'message' => $message];
    }

    /**
     * @return array<string, int>
     */
    private function codeScores(): array
    {
        return [
            'REC' => 10, 'GAV' => 12, 'SD2' => 14, 'PVT' => 14, 'PV1' => 14,
            'OPE' => 18, 'SD1' => 20, 'PV2' => 20, 'GND' => 24, 'PFC' => 26,
            'CPL' => 30, 'SPC' => 32, 'CCH' => 36, 'MDL' => 40, 'MDC' => 44,
            'SGT' => 50, 'SCH' => 56, 'SSG' => 58, 'SFC' => 62,
            'ADJ' => 70, 'ADC' => 76, 'MSG' => 78, '1SG' => 80,
            'MAJ' => 86, 'SGM' => 88, 'CSM' => 90,
            'WO1' => 110, 'CW2' => 114, 'CW3' => 118, 'CW4' => 122, 'CW5' => 126,
            'ASP' => 140, 'SL' => 144, 'SLT' => 144, '2LT' => 144,
            'LT' => 150, 'LTN' => 150, '1LT' => 152,
            'CNE' => 160, 'CPT' => 160, 'CEN' => 166,
            'CDT' => 170, 'LCL' => 180, 'LTC' => 180, 'COL' => 190,
            'GBR' => 200, 'BG' => 200, 'GDV' => 210, 'MG' => 210,
            'GCA' => 220, 'LTG' => 220, 'GAR' => 230, 'GEN' => 230,
            'CIV' => 900, 'HG' => 910,
        ];
    }
}
