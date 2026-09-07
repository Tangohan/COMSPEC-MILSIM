<?php

declare(strict_types=1);

namespace App\Services\Personnel\PhaseRules;

final class PhaseRuleEngine
{
    public const TYPE_QUALIFICATION = 'qualification_held';
    public const TYPE_LMS = 'lms_completed';
    public const TYPE_RAW_HOURS = 'playtime_raw_hours';
    public const TYPE_VALIDATED_HOURS = 'playtime_validated_hours';
    public const TYPE_CATEGORY_HOURS = 'playtime_category_hours';
    public const TYPE_SESSIONS = 'sessions_validated_count';
    public const TYPE_SESSIONS_KIND = 'sessions_kind_count';
    public const TYPE_CHECKED_IN = 'attendance_checked_in_count';
    public const TYPE_ATTENDANCE_RATE = 'attendance_rate';
    public const TYPE_SENIORITY = 'seniority_days';
    public const TYPE_COMPLETENESS = 'dossier_completeness';
    public const TYPE_TUTOR = 'tutor_assigned';
    public const TYPE_NO_SANCTION = 'no_active_sanction';

    /**
     * @return list<array{type: string, label: string, preview: string}>
     */
    public static function catalog(): array
    {
        return [
            ['type' => self::TYPE_QUALIFICATION, 'label' => 'Qualification détenue', 'preview' => 'Le membre détient la qualification choisie.'],
            ['type' => self::TYPE_LMS, 'label' => 'Module de formation validé', 'preview' => 'Le membre a validé le module choisi.'],
            ['type' => self::TYPE_RAW_HOURS, 'label' => 'Temps Arma brut', 'preview' => 'Le temps de jeu brut atteint le seuil.'],
            ['type' => self::TYPE_VALIDATED_HOURS, 'label' => 'Temps Arma validé', 'preview' => 'Le temps validé pour le suivi atteint le seuil.'],
            ['type' => self::TYPE_CATEGORY_HOURS, 'label' => 'Temps dans une catégorie', 'preview' => 'Le temps validé dans cette nature de session atteint le seuil.'],
            ['type' => self::TYPE_SESSIONS, 'label' => 'Nombre de sessions validées', 'preview' => 'Le nombre de sessions validées atteint le seuil sur la période.'],
            ['type' => self::TYPE_SESSIONS_KIND, 'label' => 'Sessions d’un type', 'preview' => 'Le nombre de sessions de ce type atteint le seuil sur la période.'],
            ['type' => self::TYPE_CHECKED_IN, 'label' => 'Présences pointées', 'preview' => 'Le nombre de présences réellement pointées atteint le seuil. Une simple inscription ne compte pas.'],
            ['type' => self::TYPE_ATTENDANCE_RATE, 'label' => 'Taux de présence', 'preview' => 'Le taux de présence pointée atteint le pourcentage sur la période.'],
            ['type' => self::TYPE_SENIORITY, 'label' => 'Ancienneté', 'preview' => 'L’ancienneté dans la communauté atteint le nombre de jours.'],
            ['type' => self::TYPE_COMPLETENESS, 'label' => 'Complétude du dossier', 'preview' => 'La complétude de la fiche atteint le pourcentage.'],
            ['type' => self::TYPE_TUTOR, 'label' => 'Tuteur désigné', 'preview' => 'Un tuteur est renseigné sur le dossier.'],
            ['type' => self::TYPE_NO_SANCTION, 'label' => 'Aucune sanction en cours', 'preview' => 'Aucune restriction active n’est en cours.'],
        ];
    }

    public static function labelFor(string $type): string
    {
        foreach (self::catalog() as $row) {
            if ($row['type'] === $type) {
                return $row['label'];
            }
        }

        return 'Condition';
    }

    /**
     * @param list<array<string, mixed>> $conditions
     * @return array{eligible: bool, logic: string, items: list<array{passed: bool, label: string, current: string, target: string, reason: string, type: string}>}
     */
    public function evaluate(array $conditions, string $logic, PhaseEvaluationContext $ctx): array
    {
        $logic = $logic === 'any' ? 'any' : 'all';
        $items = [];
        foreach ($conditions as $condition) {
            if (!is_array($condition)) {
                continue;
            }
            $items[] = $this->evaluateOne($condition, $ctx);
        }
        if ($items === []) {
            return ['eligible' => false, 'logic' => $logic, 'items' => []];
        }
        $eligible = $logic === 'any'
            ? (bool) array_filter($items, static fn (array $i): bool => !empty($i['passed']))
            : !array_filter($items, static fn (array $i): bool => empty($i['passed']));

        return ['eligible' => $eligible, 'logic' => $logic, 'items' => $items];
    }

    /**
     * @param array<string, mixed> $condition
     * @return array{passed: bool, label: string, current: string, target: string, reason: string, type: string}
     */
    public function evaluateOne(array $condition, PhaseEvaluationContext $ctx): array
    {
        $type = (string) ($condition['condition_type'] ?? '');
        $threshold = (float) ($condition['threshold_value'] ?? 0);
        $window = (int) ($condition['window_days'] ?? 0);
        $label = self::labelFor($type);

        return match ($type) {
            self::TYPE_QUALIFICATION => $this->boolItem(
                $type,
                $label,
                $ctx->qualificationValid || (!$this->bool($condition['require_validity'] ?? false) && $ctx->qualificationHeldId !== null && $ctx->qualificationHeldId > 0),
                $ctx->qualificationLabel,
                'détenue' . (!empty($condition['require_validity']) ? ' et encore valable' : ''),
                $ctx->qualificationValid ? 'détenue et valable' : 'non remplie'
            ),
            self::TYPE_LMS => $this->boolItem(
                $type,
                $label,
                $ctx->lmsCompleted,
                $ctx->lmsLabel,
                'validé',
                $ctx->lmsCompleted ? 'validé' : 'non validé'
            ),
            self::TYPE_RAW_HOURS => $this->numericItem($type, $label, $ctx->rawHours, $threshold, 'h', 'Temps brut'),
            self::TYPE_VALIDATED_HOURS => $this->numericItem($type, $label, $ctx->validatedHours, $threshold, 'h', 'Temps validé'),
            self::TYPE_CATEGORY_HOURS => $this->numericItem($type, $label, $ctx->categoryHours, $threshold, 'h', 'Temps « ' . $ctx->categoryLabel . ' »'),
            self::TYPE_SESSIONS => $this->numericItem($type, $label, (float) $ctx->validatedSessions, $threshold, 'session(s)', $window > 0 ? 'Sur ' . $window . ' jours' : 'Au total'),
            self::TYPE_SESSIONS_KIND => $this->numericItem($type, $label, (float) $ctx->kindSessions, $threshold, 'session(s)', $ctx->kindLabel),
            self::TYPE_CHECKED_IN => $this->numericItem($type, $label, (float) $ctx->checkedInCount, $threshold, 'présence(s) pointée(s)', $window > 0 ? 'Sur ' . $window . ' jours' : 'Au total'),
            self::TYPE_ATTENDANCE_RATE => $this->numericItem($type, $label, $ctx->attendanceRate, $threshold, ' %', $window > 0 ? 'Sur ' . $window . ' jours' : 'Sur 90 jours'),
            self::TYPE_SENIORITY => $this->numericItem($type, $label, (float) $ctx->seniorityDays, $threshold, ' j', 'Ancienneté'),
            self::TYPE_COMPLETENESS => $this->numericItem($type, $label, (float) $ctx->completenessPercent, $threshold, ' %', 'Complétude de la fiche'),
            self::TYPE_TUTOR => $this->boolItem($type, $label, $ctx->tutorAssigned, 'Tuteur', 'désigné', $ctx->tutorAssigned ? 'désigné' : 'aucun tuteur'),
            self::TYPE_NO_SANCTION => $this->boolItem($type, $label, !$ctx->hasActiveSanction, 'Sanction', 'aucune en cours', $ctx->hasActiveSanction ? 'une sanction est en cours' : 'aucune en cours'),
            default => [
                'passed' => false,
                'label' => $label,
                'current' => '—',
                'target' => '—',
                'reason' => 'Cette condition n’est pas reconnue.',
                'type' => $type,
            ],
        };
    }

    public static function previewSentence(array $condition): string
    {
        $type = (string) ($condition['condition_type'] ?? '');
        $n = (float) ($condition['threshold_value'] ?? 0);
        $window = (int) ($condition['window_days'] ?? 0);
        $windowTxt = $window > 0 ? ' sur ' . $window . ' jours' : '';

        return match ($type) {
            self::TYPE_QUALIFICATION => 'Cette règle signifie : le membre doit détenir la qualification choisie'
                . (!empty($condition['require_validity']) ? ', encore valable.' : '.'),
            self::TYPE_LMS => 'Cette règle signifie : le membre doit avoir validé le module de formation choisi.',
            self::TYPE_RAW_HOURS => 'Cette règle signifie : le temps de jeu brut doit atteindre au moins ' . self::fmt($n) . ' h.',
            self::TYPE_VALIDATED_HOURS => 'Cette règle signifie : le temps validé pour le suivi doit atteindre au moins ' . self::fmt($n) . ' h.',
            self::TYPE_CATEGORY_HOURS => 'Cette règle signifie : le temps validé dans cette nature de session doit atteindre au moins ' . self::fmt($n) . ' h.',
            self::TYPE_SESSIONS => 'Cette règle signifie : au moins ' . self::fmt($n) . ' session(s) validée(s)' . $windowTxt . '.',
            self::TYPE_SESSIONS_KIND => 'Cette règle signifie : au moins ' . self::fmt($n) . ' session(s) de ce type' . $windowTxt . '.',
            self::TYPE_CHECKED_IN => 'Cette règle signifie : au moins ' . self::fmt($n) . ' présence(s) réellement pointée(s)' . $windowTxt . '. Une simple inscription ne suffit pas.',
            self::TYPE_ATTENDANCE_RATE => 'Cette règle signifie : le taux de présence pointée doit atteindre ' . self::fmt($n) . ' %' . ($window > 0 ? $windowTxt : ' sur 90 jours') . '.',
            self::TYPE_SENIORITY => 'Cette règle signifie : l’ancienneté doit atteindre au moins ' . self::fmt($n) . ' jours.',
            self::TYPE_COMPLETENESS => 'Cette règle signifie : la fiche doit être complète à au moins ' . self::fmt($n) . ' %.',
            self::TYPE_TUTOR => 'Cette règle signifie : un tuteur doit être désigné sur le dossier.',
            self::TYPE_NO_SANCTION => 'Cette règle signifie : aucune sanction en cours.',
            default => 'Cette règle n’est pas reconnue.',
        };
    }

    /**
     * @return array{passed: bool, label: string, current: string, target: string, reason: string, type: string}
     */
    private function numericItem(string $type, string $label, float $current, float $target, string $unit, string $scope): array
    {
        $passed = $current + 0.0001 >= $target;
        $curTxt = self::fmt($current) . $unit;
        $tgtTxt = self::fmt($target) . $unit;

        return [
            'passed' => $passed,
            'label' => $label,
            'current' => $curTxt,
            'target' => $tgtTxt,
            'reason' => $scope . ' : ' . $curTxt . ' / ' . $tgtTxt . ($passed ? '.' : ' — pas encore atteint.'),
            'type' => $type,
        ];
    }

    /**
     * @return array{passed: bool, label: string, current: string, target: string, reason: string, type: string}
     */
    private function boolItem(string $type, string $label, bool $passed, string $subject, string $target, string $current): array
    {
        return [
            'passed' => $passed,
            'label' => $label,
            'current' => $current,
            'target' => $target,
            'reason' => $subject . ' : ' . $current . '.',
            'type' => $type,
        ];
    }

    private function bool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }

    private static function fmt(float $n): string
    {
        if (abs($n - round($n)) < 0.05) {
            return (string) (int) round($n);
        }

        return rtrim(rtrim(number_format($n, 1, ',', ''), '0'), ',');
    }
}
