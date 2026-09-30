<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Repositories\GradeDefinitionRepository;
use App\Repositories\PersonnelGradeHistoryRepository;
use App\Repositories\QualificationAwardRepository;
use DateTimeImmutable;

/**
 * Éligibilité à un grade visé : grade précédent, temps de grade, qualification requise.
 * Réutilise QualificationTemporalStatusService — pas de duplication de la validité.
 */
final class AdvancementEligibilityService
{
    public function __construct(
        private PersonnelGradeHistoryRepository $history,
        private GradeDefinitionRepository $grades,
        private QualificationAwardRepository $awards,
        private QualificationTemporalStatusService $temporal,
    ) {
    }

    /**
     * @return array{
     *     is_eligible: bool,
     *     eligibility_reason: ?string,
     *     current_grade: ?array<string, mixed>,
     *     target_grade: ?array<string, mixed>,
     *     months_in_grade: ?int,
     *     required_months: ?int,
     *     qualification_ok: ?bool
     * }
     */
    public function evaluate(
        int $tenantId,
        int $personnelId,
        int $targetGradeId,
        ?DateTimeImmutable $now = null
    ): array {
        $now = $now ?? new DateTimeImmutable('today');
        $empty = [
            'is_eligible' => false,
            'eligibility_reason' => null,
            'current_grade' => null,
            'target_grade' => null,
            'months_in_grade' => null,
            'required_months' => null,
            'qualification_ok' => null,
        ];

        $target = $this->grades->find($tenantId, $targetGradeId);
        if ($target === null || !empty($target['archived_at'])) {
            $empty['eligibility_reason'] = 'Grade visé introuvable ou archivé.';

            return $empty;
        }
        $empty['target_grade'] = $target;

        $current = $this->history->currentForPersonnel($tenantId, $personnelId);
        $empty['current_grade'] = $current;
        if ($current === null) {
            $empty['eligibility_reason'] = 'Aucun grade actuel enregistré.';

            return $empty;
        }

        $predecessor = $this->grades->findImmediatePredecessor($tenantId, $target);
        $currentRank = (int) ($current['rank_order'] ?? 0);
        $targetRank = (int) ($target['rank_order'] ?? 0);
        $isImmediate = $predecessor !== null && (int) ($predecessor['id'] ?? 0) === (int) ($current['grade_id'] ?? 0);
        $isConsecutive = $currentRank === $targetRank - 1;
        if (!$isImmediate && !$isConsecutive) {
            $currentLabel = trim((string) ($current['grade_label'] ?? $current['grade_code'] ?? 'grade actuel'));
            $targetLabel = trim((string) ($target['label'] ?? $target['code'] ?? 'grade visé'));
            $empty['eligibility_reason'] = 'Le grade actuel (' . $currentLabel . ') n’est pas le grade immédiatement inférieur à ' . $targetLabel . '.';

            return $empty;
        }

        $requiredMonths = isset($target['min_time_in_previous_grade_months'])
            && $target['min_time_in_previous_grade_months'] !== null
            && $target['min_time_in_previous_grade_months'] !== ''
            ? (int) $target['min_time_in_previous_grade_months']
            : 0;
        $empty['required_months'] = $requiredMonths > 0 ? $requiredMonths : null;

        $obtainedRaw = substr((string) ($current['obtained_at'] ?? ''), 0, 10);
        $monthsInGrade = 0;
        if ($obtainedRaw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $obtainedRaw)) {
            try {
                $obtained = new DateTimeImmutable($obtainedRaw);
                $diff = $obtained->diff($now);
                $monthsInGrade = ($diff->invert === 1) ? 0 : ($diff->y * 12 + $diff->m);
                $empty['months_in_grade'] = $monthsInGrade;
                if ($requiredMonths > 0) {
                    $due = $obtained->modify('+' . $requiredMonths . ' months');
                    if ($now->format('Y-m-d') < $due->format('Y-m-d')) {
                        $empty['eligibility_reason'] = 'Temps de grade insuffisant : ' . $monthsInGrade
                            . ' mois sur ' . $requiredMonths . ' requis';

                        return $empty;
                    }
                }
            } catch (\Throwable) {
                $empty['eligibility_reason'] = 'Date d’obtention du grade actuel illisible.';

                return $empty;
            }
        } elseif ($requiredMonths > 0) {
            $empty['eligibility_reason'] = 'Temps de grade insuffisant : date d’obtention manquante.';

            return $empty;
        }

        $requiredQualId = (int) ($target['required_qualification_id'] ?? 0);
        if ($requiredQualId > 0) {
            $ok = $this->hasValidQualification(
                $tenantId,
                $personnelId,
                $requiredQualId,
                isset($target['required_qualification_level_id']) ? (int) $target['required_qualification_level_id'] : 0,
                $now
            );
            $empty['qualification_ok'] = $ok;
            if (!$ok) {
                $qualName = trim((string) ($target['required_qualification_name'] ?? $target['required_qualification_code'] ?? ''));
                $empty['eligibility_reason'] = $qualName !== ''
                    ? 'Qualification ' . $qualName . ' manquante'
                    : 'Qualification requise manquante';

                return $empty;
            }
        } else {
            $empty['qualification_ok'] = null;
        }

        $empty['is_eligible'] = true;
        $empty['eligibility_reason'] = null;

        return $empty;
    }

    private function hasValidQualification(
        int $tenantId,
        int $personnelId,
        int $definitionId,
        int $levelId,
        DateTimeImmutable $now
    ): bool {
        try {
            $rows = $this->awards->listForUser($personnelId, $tenantId);
        } catch (\Throwable) {
            return false;
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            if ((int) ($row['definition_id'] ?? 0) !== $definitionId) {
                continue;
            }
            if ($levelId > 0 && (int) ($row['qualification_level_id'] ?? 0) !== $levelId) {
                continue;
            }
            if ($this->temporal->isEffectivelyActive($row, $now)) {
                return true;
            }
        }

        return false;
    }
}
