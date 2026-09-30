<?php

declare(strict_types=1);

namespace App\Services\Advancement;

use App\Services\Personnel\QualificationTemporalStatusService;
use DateTimeImmutable;

/**
 * Éligibilité à un grade visé. Le temps de grade et la qualification requise
 * sont calculés à la volée — la validité de qualification passe par
 * QualificationTemporalStatusService, sans dupliquer ses règles.
 */
final class AdvancementEligibilityService
{
    public const PATH_CHOICE = 'choix';
    public const PATH_SENIORITY = 'anciennete';

    public function __construct(
        private ?QualificationTemporalStatusService $temporal = null,
    ) {
        $this->temporal ??= new QualificationTemporalStatusService();
    }

    /**
     * Mois civils révolus. Le jour anniversaire du mois n'est pas compté avant terme.
     */
    public function monthsCompleted(DateTimeImmutable $from, DateTimeImmutable $to): int
    {
        if ($to < $from) {
            return 0;
        }
        $total = ((int) $to->format('Y') - (int) $from->format('Y')) * 12
            + ((int) $to->format('n') - (int) $from->format('n'));
        if ((int) $to->format('j') < (int) $from->format('j')) {
            $total--;
        }

        return max(0, $total);
    }

    /**
     * Date d'échéance calendaire (31 janvier + 1 mois = dernier jour de février).
     */
    public function dueOn(DateTimeImmutable $obtained, int $months): DateTimeImmutable
    {
        if ($months <= 0) {
            return $obtained;
        }
        $monthIndex = ((int) $obtained->format('n') - 1) + $months;
        $year = (int) $obtained->format('Y') + intdiv($monthIndex, 12);
        $month = ($monthIndex % 12) + 1;
        $probe = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $day = min((int) $obtained->format('j'), (int) $probe->format('t'));

        return new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day));
    }

    /**
     * @param array<string, mixed> $input
     *   current: ?array{rank_order:int, filiere_id:?int, obtained_at:?string, label?:string}
     *   target: array{rank_order:int, filiere_id:?int, label?:string, min_time_in_previous_grade_months:?int,
     *     required_qualification_id:?int, required_qualification_label?:string, required_qualification_level_id:?int,
     *     advancement_choice_enabled?:bool, advancement_seniority_enabled?:bool}
     *   qualification_award: ?array<string, mixed>
     *   qualification_level_met?: bool
     * @return array{is_eligible:bool, eligibility_reason:?string, months_in_grade:int, months_required:?int, due_on:?string}
     */
    public function evaluate(array $input, ?DateTimeImmutable $today = null, string $pathway = self::PATH_CHOICE): array
    {
        $today = ($today ?? new DateTimeImmutable('today'))->setTime(0, 0);
        $target = is_array($input['target'] ?? null) ? $input['target'] : [];
        $current = is_array($input['current'] ?? null) ? $input['current'] : null;
        $targetLabel = trim((string) ($target['label'] ?? 'grade visé'));
        $requiredMonths = isset($target['min_time_in_previous_grade_months']) && $target['min_time_in_previous_grade_months'] !== ''
            ? (int) $target['min_time_in_previous_grade_months']
            : null;
        if ($requiredMonths !== null && $requiredMonths < 0) {
            $requiredMonths = 0;
        }

        $empty = [
            'is_eligible' => false,
            'eligibility_reason' => null,
            'months_in_grade' => 0,
            'months_required' => $requiredMonths,
            'due_on' => null,
        ];

        if ($pathway === self::PATH_CHOICE && array_key_exists('advancement_choice_enabled', $target) && empty($target['advancement_choice_enabled'])) {
            $empty['eligibility_reason'] = 'Ce grade n’est pas ouvert à la voie choix.';

            return $empty;
        }
        if ($pathway === self::PATH_SENIORITY && array_key_exists('advancement_seniority_enabled', $target) && empty($target['advancement_seniority_enabled'])) {
            $empty['eligibility_reason'] = 'Ce grade n’est pas ouvert à l’ancienneté.';

            return $empty;
        }
        if ($current === null) {
            $empty['eligibility_reason'] = 'Aucun grade actuel enregistré.';

            return $empty;
        }

        $currentFiliere = $current['filiere_id'] ?? null;
        $targetFiliere = $target['filiere_id'] ?? null;
        $currentFiliere = $currentFiliere === null || $currentFiliere === '' ? null : (int) $currentFiliere;
        $targetFiliere = $targetFiliere === null || $targetFiliere === '' ? null : (int) $targetFiliere;
        if ($currentFiliere !== $targetFiliere) {
            $empty['eligibility_reason'] = 'La filière du grade actuel ne correspond pas à celle du grade visé.';

            return $empty;
        }

        $expectedOrder = (int) ($target['rank_order'] ?? 0) - 1;
        if ((int) ($current['rank_order'] ?? 0) !== $expectedOrder) {
            $empty['eligibility_reason'] = 'Le grade actuel ne précède pas ' . $targetLabel . '.';

            return $empty;
        }

        $obtainedRaw = trim((string) ($current['obtained_at'] ?? ''));
        $monthsInGrade = 0;
        $dueOn = null;
        if ($obtainedRaw !== '') {
            try {
                $obtained = new DateTimeImmutable(substr($obtainedRaw, 0, 10));
            } catch (\Throwable) {
                $obtained = null;
            }
            if ($obtained instanceof DateTimeImmutable) {
                $monthsInGrade = $this->monthsCompleted($obtained, $today);
                if ($requiredMonths !== null && $requiredMonths > 0) {
                    $dueOn = $this->dueOn($obtained, $requiredMonths)->format('Y-m-d');
                }
            }
        }
        $empty['months_in_grade'] = $monthsInGrade;
        $empty['due_on'] = $dueOn;

        if ($requiredMonths !== null && $requiredMonths > 0) {
            if ($obtainedRaw === '' || $dueOn === null) {
                $empty['eligibility_reason'] = 'Date d’obtention du grade actuel inconnue.';

                return $empty;
            }
            $due = new DateTimeImmutable($dueOn);
            if ($today < $due) {
                $empty['eligibility_reason'] = 'Temps de grade insuffisant : ' . $monthsInGrade . ' mois sur ' . $requiredMonths . ' requis';

                return $empty;
            }
        }

        $requiredQualificationId = isset($target['required_qualification_id']) && $target['required_qualification_id'] !== ''
            ? (int) $target['required_qualification_id']
            : 0;
        if ($requiredQualificationId > 0) {
            $qualLabel = trim((string) ($target['required_qualification_label'] ?? 'requise'));
            if ($qualLabel === '') {
                $qualLabel = 'requise';
            }
            $award = is_array($input['qualification_award'] ?? null) ? $input['qualification_award'] : null;
            if ($award === null || !$this->temporal->isEffectivelyActive($award, $today)) {
                $empty['eligibility_reason'] = 'Qualification ' . $qualLabel . ' manquante';

                return $empty;
            }
            $requiredLevel = isset($target['required_qualification_level_id']) && $target['required_qualification_level_id'] !== ''
                ? (int) $target['required_qualification_level_id']
                : 0;
            if ($requiredLevel > 0 && empty($input['qualification_level_met'])) {
                $empty['eligibility_reason'] = 'Niveau de qualification requis non atteint pour ' . $qualLabel . '.';

                return $empty;
            }
        }

        return [
            'is_eligible' => true,
            'eligibility_reason' => null,
            'months_in_grade' => $monthsInGrade,
            'months_required' => $requiredMonths,
            'due_on' => $dueOn,
        ];
    }
}
