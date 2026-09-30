<?php

declare(strict_types=1);

namespace App\Services\Personnel\Pass;

/**
 * Catalogue et évaluation des conditions d’un PASS (poste, avancement, notation).
 */
final class PassConditionEngine
{
    public const TYPE_QUALIFICATION = 'qualification_held';
    public const TYPE_LMS = 'lms_completed';
    public const TYPE_RAW_HOURS = 'playtime_raw_hours';
    public const TYPE_VALIDATED_HOURS = 'playtime_validated_hours';
    public const TYPE_CATEGORY_HOURS = 'playtime_category_hours';
    public const TYPE_MIN_GRADE = 'min_grade';
    public const TYPE_HIERARCHICAL_AVIS = 'hierarchical_avis';
    public const TYPE_BILAN_RATING = 'bilan_rating_min';

    public const AVIS_CHEF = 'chef';
    public const AVIS_MIXTE = 'mixte';
    public const AVIS_COMMANDEMENT = 'commandement';
    public const AVIS_CHEF_N1 = 'chef_n1';
    public const AVIS_CHEF_N2 = 'chef_n2';

    /**
     * @return list<array{type: string, label: string, preview: string}>
     */
    public static function catalog(): array
    {
        return [
            ['type' => self::TYPE_LMS, 'label' => 'Formation suivie', 'preview' => 'Le membre a validé le module choisi.'],
            ['type' => self::TYPE_QUALIFICATION, 'label' => 'Qualification détenue', 'preview' => 'Le membre détient la qualification choisie.'],
            ['type' => self::TYPE_RAW_HOURS, 'label' => 'Heure de jeu (brut)', 'preview' => 'Le temps de jeu brut atteint le seuil.'],
            ['type' => self::TYPE_VALIDATED_HOURS, 'label' => 'Heure de jeu (validé)', 'preview' => 'Le temps validé pour le suivi atteint le seuil.'],
            ['type' => self::TYPE_CATEGORY_HOURS, 'label' => 'Type d’heure de jeu', 'preview' => 'Le temps validé dans cette nature de session atteint le seuil.'],
            ['type' => self::TYPE_MIN_GRADE, 'label' => 'Grade minimum', 'preview' => 'Le grade actuel atteint au moins le grade choisi.'],
            ['type' => self::TYPE_HIERARCHICAL_AVIS, 'label' => 'Avis hiérarchique', 'preview' => 'Un avis favorable du niveau choisi est enregistré.'],
            ['type' => self::TYPE_BILAN_RATING, 'label' => 'Notation minimale', 'preview' => 'La moyenne des bilans du type choisi atteint le seuil.'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function avisKinds(): array
    {
        return [
            ['value' => self::AVIS_CHEF, 'label' => 'Chef'],
            ['value' => self::AVIS_MIXTE, 'label' => 'Mixte'],
            ['value' => self::AVIS_COMMANDEMENT, 'label' => 'Commandement'],
            ['value' => self::AVIS_CHEF_N1, 'label' => 'Chef N+1'],
            ['value' => self::AVIS_CHEF_N2, 'label' => 'Chef N+2'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function bilanKinds(): array
    {
        return [
            ['value' => 'recrutement', 'label' => 'Recrutement'],
            ['value' => 'rh', 'label' => 'Ressources humaines'],
            ['value' => 'commandement', 'label' => 'Commandement'],
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

    public static function avisLabel(string $kind): string
    {
        foreach (self::avisKinds() as $row) {
            if ($row['value'] === $kind) {
                return $row['label'];
            }
        }

        return 'Avis hiérarchique';
    }

    /**
     * @param list<array<string, mixed>> $conditions
     * @return array{eligible: bool, logic: string, items: list<array{passed: bool, label: string, current: string, target: string, reason: string, type: string}>}
     */
    public function evaluate(array $conditions, string $logic, PassEvaluationContext $ctx): array
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
            return ['eligible' => true, 'logic' => $logic, 'items' => []];
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
    public function evaluateOne(array $condition, PassEvaluationContext $ctx): array
    {
        $type = (string) ($condition['condition_type'] ?? '');
        $threshold = (float) ($condition['threshold_value'] ?? 0);
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
            self::TYPE_RAW_HOURS => $this->numericItem($type, $label, $ctx->rawHours, $threshold, ' h', 'Temps brut'),
            self::TYPE_VALIDATED_HOURS => $this->numericItem($type, $label, $ctx->validatedHours, $threshold, ' h', 'Temps validé'),
            self::TYPE_CATEGORY_HOURS => $this->numericItem($type, $label, $ctx->categoryHours, $threshold, ' h', 'Temps « ' . $ctx->categoryLabel . ' »'),
            self::TYPE_MIN_GRADE => $this->boolItem(
                $type,
                $label,
                $ctx->gradeMet,
                $ctx->gradeLabel,
                'atteint',
                $ctx->gradeMet ? 'atteint' : 'non atteint'
            ),
            self::TYPE_HIERARCHICAL_AVIS => $this->avisItem($condition, $ctx),
            self::TYPE_BILAN_RATING => $this->numericItem(
                $type,
                $label . ' (' . $ctx->bilanLabel . ')',
                $ctx->bilanAverage,
                $threshold,
                '',
                $ctx->bilanCount > 0 ? 'Moyenne sur ' . $ctx->bilanCount . ' bilan(s)' : 'Aucun bilan'
            ),
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

        return match ($type) {
            self::TYPE_QUALIFICATION => 'Le membre doit détenir la qualification choisie'
                . (!empty($condition['require_validity']) ? ', encore valable.' : '.'),
            self::TYPE_LMS => 'Le membre doit avoir validé le module de formation choisi.',
            self::TYPE_RAW_HOURS => 'Le temps de jeu brut doit atteindre au moins ' . self::fmt($n) . ' h.',
            self::TYPE_VALIDATED_HOURS => 'Le temps validé pour le suivi doit atteindre au moins ' . self::fmt($n) . ' h.',
            self::TYPE_CATEGORY_HOURS => 'Le temps validé dans cette nature de session doit atteindre au moins ' . self::fmt($n) . ' h.',
            self::TYPE_MIN_GRADE => 'Le grade actuel doit atteindre au moins le grade choisi.',
            self::TYPE_HIERARCHICAL_AVIS => 'Un avis favorable « ' . self::avisLabel((string) ($condition['avis_kind'] ?? '')) . ' » doit être enregistré.',
            self::TYPE_BILAN_RATING => 'La moyenne des bilans doit atteindre au moins ' . self::fmt($n) . '.',
            default => 'Condition non reconnue.',
        };
    }

    /**
     * @param array<string, mixed> $condition
     * @return array{passed: bool, label: string, current: string, target: string, reason: string, type: string}
     */
    private function avisItem(array $condition, PassEvaluationContext $ctx): array
    {
        $kind = trim((string) ($condition['avis_kind'] ?? self::AVIS_CHEF_N1));
        $label = 'Avis « ' . self::avisLabel($kind) . ' »';
        $passed = !empty($ctx->favorableAvisByKind[$kind]);

        return $this->boolItem(
            self::TYPE_HIERARCHICAL_AVIS,
            $label,
            $passed,
            $label,
            'favorable',
            $passed ? 'favorable' : 'en attente'
        );
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
