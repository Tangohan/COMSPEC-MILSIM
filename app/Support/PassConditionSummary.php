<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\Personnel\Pass\PassConditionEngine as E;

/**
 * Phrase lisible d’une condition de PASS (« A validé la formation « JTAC » »), avec le nom réel des éléments
 * choisis, et ce qu’il manque pour qu’elle soit complète. Même logique que l’aperçu en direct du formulaire
 * (public/assets/js/pass-form.js) : le rendu serveur et la saisie disent la même chose.
 */
final class PassConditionSummary
{
    /**
     * @param array<string, mixed> $c condition (condition_type, *_id, threshold_value, window_days, …)
     * @param array{courses?: array<int, string>, qualifications?: array<int, string>, grades?: array<int, string>} $lookups
     * @return array{text: string, missing: string}
     */
    public static function describe(array $c, array $lookups = []): array
    {
        $type = (string) ($c['condition_type'] ?? '');
        $n = (float) ($c['threshold_value'] ?? 0);
        $name = static function (string $list, string $key) use ($c, $lookups): string {
            $id = (int) ($c[$key] ?? 0);

            return $id > 0 ? (string) ($lookups[$list][$id] ?? ('#' . $id)) : '';
        };

        switch ($type) {
            case E::TYPE_LMS:
                $x = $name('courses', 'training_module_id');

                return $x === ''
                    ? ['text' => 'A validé une formation', 'missing' => 'Choisissez la formation.']
                    : ['text' => 'A validé la formation « ' . $x . ' »', 'missing' => ''];
            case E::TYPE_QUALIFICATION:
                $x = $name('qualifications', 'qualification_id');
                $valid = !empty($c['require_validity']) ? ', encore valable' : '';

                return $x === ''
                    ? ['text' => 'Détient une qualification' . $valid, 'missing' => 'Choisissez la qualification.']
                    : ['text' => 'Détient la qualification « ' . $x . ' »' . $valid, 'missing' => ''];
            case E::TYPE_RAW_HOURS:
                return self::hours($n, 'Au moins %s h de jeu (temps brut)');
            case E::TYPE_VALIDATED_HOURS:
                return self::hours($n, 'Au moins %s h de jeu validées');
            case E::TYPE_CATEGORY_HOURS:
                $cat = trim((string) ($c['hour_category'] ?? ''));
                $r = self::hours($n, 'Au moins %s h validées' . ($cat !== '' ? ' en « ' . $cat . ' »' : ''));
                if ($cat === '' && $r['missing'] === '') {
                    $r['missing'] = 'Choisissez le type d’heure.';
                }

                return $r;
            case E::TYPE_MIN_GRADE:
                $x = $name('grades', 'grade_id');

                return $x === ''
                    ? ['text' => 'A au moins un grade donné', 'missing' => 'Choisissez le grade minimum.']
                    : ['text' => 'A au moins le grade ' . $x, 'missing' => ''];
            case E::TYPE_HIERARCHICAL_AVIS:
                $kind = trim((string) ($c['avis_kind'] ?? ''));

                return $kind === ''
                    ? ['text' => 'A reçu un avis hiérarchique favorable', 'missing' => 'Choisissez qui donne l’avis.']
                    : ['text' => 'A reçu un avis favorable « ' . E::avisLabel($kind) . ' »', 'missing' => ''];
            case E::TYPE_BILAN_RATING:
                $kind = trim((string) ($c['bilan_kind'] ?? ''));
                $kindLabel = '';
                foreach (E::bilanKinds() as $bk) {
                    if ($bk['value'] === $kind) {
                        $kindLabel = $bk['label'];
                    }
                }
                $window = (int) ($c['window_days'] ?? 0);
                $text = 'Moyenne des bilans' . ($kindLabel !== '' ? ' « ' . $kindLabel . ' »' : '') . ' d’au moins ' . self::fmt($n)
                    . ($window > 0 ? ' sur les ' . $window . ' derniers jours' : '');

                return ['text' => $text, 'missing' => $n <= 0 ? 'Indiquez la note minimale.' : ''];
            default:
                return ['text' => 'Condition à choisir', 'missing' => 'Choisissez le type de condition.'];
        }
    }

    /** @return array{text: string, missing: string} */
    private static function hours(float $n, string $pattern): array
    {
        return ['text' => sprintf($pattern, self::fmt($n)), 'missing' => $n <= 0 ? 'Indiquez le nombre d’heures.' : ''];
    }

    private static function fmt(float $n): string
    {
        if (abs($n - round($n)) < 0.05) {
            return (string) (int) round($n);
        }

        return str_replace('.', ',', (string) round($n, 1));
    }
}
