<?php

declare(strict_types=1);

namespace App\Services\Advancement;

use App\Repositories\AdvancementRepository;
use App\Repositories\QualificationAwardRepository;
use App\Services\Personnel\QualificationTemporalStatusService;
use DateTimeImmutable;

final class AdvancementEligibilityService
{
    public function __construct(
        private AdvancementRepository $advancement,
        private QualificationAwardRepository $awards,
        private QualificationTemporalStatusService $qualificationStatus,
    ) {}

    /**
     * @return array{is_eligible: bool, eligibility_reason: ?string, details: array<string, mixed>}
     */
    public function evaluate(
        int $tenantId,
        int $personnelId,
        int $targetGradeId,
        ?DateTimeImmutable $asOf = null
    ): array {
        $asOf ??= new DateTimeImmutable('today');
        $target = $this->advancement->findGrade($tenantId, $targetGradeId);
        if ($target === null || $target['archived_at'] !== null) {
            return $this->refused('Grade visé introuvable ou archivé.');
        }
        $current = $this->advancement->currentGrade($tenantId, $personnelId);
        if ($current === null) {
            return $this->refused('Aucun grade actuel n’est enregistré dans l’historique.');
        }
        $previous = $this->advancement->previousGrade($tenantId, $target);
        if ($previous === null || (int) $current['grade_id'] !== (int) $previous['id']) {
            return $this->refused(
                'Le grade actuel doit être ' . ($previous['label'] ?? 'le grade immédiatement inférieur') . '.',
                ['current_grade' => $current, 'target_grade' => $target]
            );
        }

        $minimumMonths = (int) ($target['min_time_in_previous_grade_months'] ?? 0);
        $obtainedAt = new DateTimeImmutable(substr((string) $current['obtained_at'], 0, 10));
        $eligibleAt = $obtainedAt->modify('+' . $minimumMonths . ' months');
        if ($asOf < $eligibleAt) {
            $monthsCompleted = $this->completedMonths($obtainedAt, $asOf);

            return $this->refused(
                sprintf('Temps de grade insuffisant : %d mois sur %d requis.', $monthsCompleted, $minimumMonths),
                ['eligible_at' => $eligibleAt->format('Y-m-d'), 'months_completed' => $monthsCompleted]
            );
        }

        $requiredQualificationId = (int) ($target['required_qualification_id'] ?? 0);
        if ($requiredQualificationId > 0) {
            $valid = false;
            $requiredLevelId = (int) ($target['required_qualification_level_id'] ?? 0);
            foreach ($this->awards->listForUser($personnelId, $tenantId) as $award) {
                if ((int) ($award['definition_id'] ?? 0) !== $requiredQualificationId) {
                    continue;
                }
                if ($requiredLevelId > 0 && (int) ($award['qualification_level_id'] ?? 0) !== $requiredLevelId) {
                    continue;
                }
                if ($this->qualificationStatus->isEffectivelyActive($award, $asOf)) {
                    $valid = true;
                    break;
                }
            }
            if (!$valid) {
                $label = trim((string) ($target['required_qualification_label'] ?? ''));

                return $this->refused(
                    'Qualification ' . ($label !== '' ? $label : '#' . $requiredQualificationId) . ' manquante ou expirée.'
                );
            }
        }

        return [
            'is_eligible' => true,
            'eligibility_reason' => null,
            'details' => [
                'current_grade' => $current,
                'target_grade' => $target,
                'eligible_at' => $eligibleAt->format('Y-m-d'),
                'months_completed' => $this->completedMonths($obtainedAt, $asOf),
            ],
        ];
    }

    /**
     * Moteur pur utilisé par les tests et par les imports avant persistance.
     *
     * @param array<string, mixed> $current
     * @param array<string, mixed> $target
     * @return array{is_eligible: bool, eligibility_reason: ?string}
     */
    public static function evaluateFacts(
        array $current,
        array $target,
        bool $hasValidQualification,
        DateTimeImmutable $asOf
    ): array {
        if ((int) ($current['rank_order'] ?? 0) >= (int) ($target['rank_order'] ?? 0)
            || (int) ($target['rank_order'] ?? 0) - (int) ($current['rank_order'] ?? 0) !== 1
        ) {
            return ['is_eligible' => false, 'eligibility_reason' => 'Le grade actuel n’est pas le grade immédiatement inférieur.'];
        }
        $months = (int) ($target['min_time_in_previous_grade_months'] ?? 0);
        $obtained = new DateTimeImmutable((string) $current['obtained_at']);
        $eligibleAt = $obtained->modify('+' . $months . ' months');
        if ($asOf < $eligibleAt) {
            $done = self::monthsBetween($obtained, $asOf);

            return [
                'is_eligible' => false,
                'eligibility_reason' => sprintf('Temps de grade insuffisant : %d mois sur %d requis.', $done, $months),
            ];
        }
        if (!empty($target['required_qualification_id']) && !$hasValidQualification) {
            return ['is_eligible' => false, 'eligibility_reason' => 'Qualification requise manquante ou expirée.'];
        }

        return ['is_eligible' => true, 'eligibility_reason' => null];
    }

    private function completedMonths(DateTimeImmutable $from, DateTimeImmutable $to): int
    {
        return self::monthsBetween($from, $to);
    }

    private static function monthsBetween(DateTimeImmutable $from, DateTimeImmutable $to): int
    {
        if ($to < $from) {
            return 0;
        }
        $diff = $from->diff($to);

        return ($diff->y * 12) + $diff->m;
    }

    /** @return array{is_eligible: false, eligibility_reason: string, details: array<string, mixed>} */
    private function refused(string $reason, array $details = []): array
    {
        return ['is_eligible' => false, 'eligibility_reason' => $reason, 'details' => $details];
    }
}
