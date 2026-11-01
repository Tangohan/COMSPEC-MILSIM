<?php

declare(strict_types=1);

namespace App\Services\Effectifs;

/**
 * Critères d’éligibilité à une élévation, réglés par chaque communauté
 * (tenants.settings.elevation_eligibility), et leur évaluation pour un membre.
 *
 * Critères simples, tous facultatifs (0 ou liste vide = critère non utilisé) :
 * - ancienneté minimale dans le grade actuel (mois révolus) ;
 * - nombre minimal de présences aux sessions sur 90 jours ;
 * - taux de présence minimal sur 90 jours (%) ;
 * - qualifications requises (valides au jour de l’évaluation).
 *
 * Résultat : « éligible » si tous les critères utilisés sont remplis, « non éligible » si l’un
 * échoue, « à vérifier » si aucun n’échoue mais qu’une donnée manque (date d’obtention du grade inconnue).
 * L’indicateur est informatif : il ne change aucun grade et ne bloque aucune demande.
 */
final class ElevationEligibility
{
    public const SETTINGS_KEY = 'elevation_eligibility';

    public const ELIGIBLE = 'eligible';
    public const NOT_ELIGIBLE = 'not_eligible';
    public const UNKNOWN = 'unknown';

    /**
     * @param array<string, mixed> $tenantSettings
     * @return array{min_months_in_grade:int,min_presences_90:int,min_presence_rate_90:int,required_qualification_ids:list<int>}
     */
    public static function settings(array $tenantSettings): array
    {
        $raw = is_array($tenantSettings[self::SETTINGS_KEY] ?? null) ? $tenantSettings[self::SETTINGS_KEY] : [];

        return self::sanitize($raw);
    }

    /**
     * @param array<string, mixed> $raw
     * @param list<int>|null $allowedQualificationIds qualifications de la communauté (null = pas de filtre)
     * @return array{min_months_in_grade:int,min_presences_90:int,min_presence_rate_90:int,required_qualification_ids:list<int>}
     */
    public static function sanitize(array $raw, ?array $allowedQualificationIds = null): array
    {
        $int = static fn (mixed $v, int $max): int => is_numeric($v) ? max(0, min($max, (int) $v)) : 0;
        $quals = [];
        foreach (is_array($raw['required_qualification_ids'] ?? null) ? $raw['required_qualification_ids'] : [] as $q) {
            $q = is_numeric($q) ? (int) $q : 0;
            if ($q > 0 && ($allowedQualificationIds === null || in_array($q, $allowedQualificationIds, true))) {
                $quals[$q] = $q;
            }
        }

        return [
            'min_months_in_grade' => $int($raw['min_months_in_grade'] ?? 0, 240),
            'min_presences_90' => $int($raw['min_presences_90'] ?? 0, 200),
            'min_presence_rate_90' => $int($raw['min_presence_rate_90'] ?? 0, 100),
            'required_qualification_ids' => array_values($quals),
        ];
    }

    /** @param array{min_months_in_grade:int,min_presences_90:int,min_presence_rate_90:int,required_qualification_ids:list<int>} $settings */
    public static function isConfigured(array $settings): bool
    {
        return $settings['min_months_in_grade'] > 0
            || $settings['min_presences_90'] > 0
            || $settings['min_presence_rate_90'] > 0
            || $settings['required_qualification_ids'] !== [];
    }

    /** Mois révolus entre deux dates (le jour anniversaire n’est pas compté avant terme). */
    public static function monthsBetween(string $from, \DateTimeImmutable $to): ?int
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $from)) {
            return null;
        }
        try {
            $start = new \DateTimeImmutable(substr($from, 0, 10));
        } catch (\Throwable) {
            return null;
        }
        if ($to < $start) {
            return 0;
        }
        $months = ((int) $to->format('Y') - (int) $start->format('Y')) * 12 + ((int) $to->format('n') - (int) $start->format('n'));
        if ((int) $to->format('j') < (int) $start->format('j')) {
            $months--;
        }

        return max(0, $months);
    }

    /**
     * @param array{min_months_in_grade:int,min_presences_90:int,min_presence_rate_90:int,required_qualification_ids:list<int>} $settings
     * @param array{months_in_grade:?int,presences_90:int,rate_90:?int,valid_qualification_ids:list<int>} $facts
     * @param array<int, string> $qualificationLabels
     * @return array{configured:bool,status:string,met:int,total:int,criteria:list<array{label:string,met:?bool,detail:string}>}
     */
    public static function evaluate(array $settings, array $facts, array $qualificationLabels = []): array
    {
        $criteria = [];
        if ($settings['min_months_in_grade'] > 0) {
            $months = $facts['months_in_grade'];
            $required = $settings['min_months_in_grade'];
            $criteria[] = [
                'label' => 'Ancienneté dans le grade : ' . $required . ' mois',
                'met' => $months === null ? null : $months >= $required,
                'detail' => $months === null ? 'Date d’obtention du grade actuel inconnue' : $months . ' mois dans le grade',
            ];
        }
        if ($settings['min_presences_90'] > 0) {
            $required = $settings['min_presences_90'];
            $criteria[] = [
                'label' => 'Présences sur 90 jours : ' . $required,
                'met' => $facts['presences_90'] >= $required,
                'detail' => $facts['presences_90'] . ' présence' . ($facts['presences_90'] > 1 ? 's' : ''),
            ];
        }
        if ($settings['min_presence_rate_90'] > 0) {
            $required = $settings['min_presence_rate_90'];
            $rate = $facts['rate_90'];
            $criteria[] = [
                'label' => 'Taux de présence sur 90 jours : ' . $required . ' %',
                'met' => $rate !== null && $rate >= $required,
                'detail' => $rate === null ? 'Aucune session sur la période' : $rate . ' %',
            ];
        }
        $valid = array_map('intval', $facts['valid_qualification_ids']);
        foreach ($settings['required_qualification_ids'] as $qid) {
            $name = $qualificationLabels[$qid] ?? 'Qualification requise';
            $ok = in_array($qid, $valid, true);
            $criteria[] = [
                'label' => 'Qualification : ' . $name,
                'met' => $ok,
                'detail' => $ok ? 'Valide' : 'Non détenue ou plus valide',
            ];
        }

        $met = count(array_filter($criteria, static fn (array $c): bool => $c['met'] === true));
        $failed = count(array_filter($criteria, static fn (array $c): bool => $c['met'] === false));
        $status = $failed > 0 ? self::NOT_ELIGIBLE : ($met === count($criteria) ? self::ELIGIBLE : self::UNKNOWN);

        return [
            'configured' => $criteria !== [],
            'status' => $criteria === [] ? self::UNKNOWN : $status,
            'met' => $met,
            'total' => count($criteria),
            'criteria' => $criteria,
        ];
    }
}
