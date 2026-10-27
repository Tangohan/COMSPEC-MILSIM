<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Lignes d'historique RH enregistrées en français (« Affectation : Non renseigné → DAG-1 · Grade : SGT »).
 * Sur une page anglaise, chaque morceau est traduit séparément : la traduction automatique ne
 * reconnaît pas la phrase entière, qui contient des valeurs.
 */
final class HrHistoryLabel
{
    public static function forPage(string $label): string
    {
        if ($label === '' || !function_exists('locale') || locale() !== 'en') {
            return $label;
        }
        $pieces = [];
        foreach (explode(' · ', $label) as $piece) {
            $pieces[] = self::translatePiece($piece);
        }

        return implode(' · ', $pieces);
    }

    private static function translatePiece(string $piece): string
    {
        $whole = HtmlTranslator::translateText($piece);
        if ($whole !== $piece) {
            return $whole;
        }
        // « Libellé : valeur » ou « Libellé : avant → après » : seuls le libellé et « Non renseigné » sont traduits.
        if (!preg_match('/^(.+?)\s?:\s(.*)$/u', $piece, $m)) {
            return $piece;
        }
        $values = array_map(
            static fn (string $v): string => trim($v) === 'Non renseigné' ? 'Not set' : $v,
            explode(' → ', $m[2])
        );

        return HtmlTranslator::translateText(trim($m[1])) . ': ' . implode(' → ', $values);
    }
}
