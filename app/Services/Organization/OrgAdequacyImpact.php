<?php

declare(strict_types=1);

namespace App\Services\Organization;

/**
 * Lecture de l’évaluation pour la page « Adéquation de l’organisation » : écarts regroupés par type (vue d’ensemble)
 * et nombre d’unités et d’écarts par échelon (liste des échelons, « Ce que ça change » de la fenêtre d’un échelon).
 *
 * Pur : ne lit que le résultat de OrgAdequacyEngine::evaluate(), sans accès base.
 */
final class OrgAdequacyImpact
{
    /**
     * Types d’écarts de la vue d’ensemble, du plus urgent au moins urgent : codes du moteur, règles qui les
     * produisent (OrgAdequacySettings::RULES) et gravité (warn : à traiter, info : à examiner, muted : à compléter).
     */
    public const GROUPS = [
        'under' => ['codes' => ['leader_grade_below', 'leader_qualification_missing', 'leader_function_missing', 'deputy_grade_below'], 'rules' => ['grade_below', 'qualifications', 'functions'], 'tone' => 'warn'],
        'vacant' => ['codes' => ['leader_vacant'], 'rules' => ['vacancy'], 'tone' => 'warn'],
        'deputy' => ['codes' => ['deputy_missing'], 'rules' => ['deputy'], 'tone' => 'warn'],
        'placement' => ['codes' => ['without_place', 'without_chief'], 'rules' => ['placement'], 'tone' => 'warn'],
        'over' => ['codes' => ['leader_overqualified', 'deputy_overqualified', 'member_overqualified', 'qualified_above'], 'rules' => ['grade_above'], 'tone' => 'info'],
        'strength' => ['codes' => ['under_strength', 'over_strength'], 'rules' => ['strength'], 'tone' => 'info'],
        'subunits' => ['codes' => ['subunits_missing'], 'rules' => ['subunits'], 'tone' => 'info'],
        'info' => ['codes' => ['leader_grade_unknown', 'grade_not_comparable', 'echelon_unknown'], 'rules' => ['echelon_unknown', 'grade_below'], 'tone' => 'muted'],
    ];

    /** Libellés courts des types (résumé de la chaîne de commandement) ; la page Adéquation a les libellés longs. */
    public const LABELS = [
        'under' => 'Sous-capacité',
        'vacant' => 'Chef à désigner',
        'deputy' => 'Adjoint attendu',
        'placement' => 'Sans place',
        'over' => 'Surqualification',
        'strength' => 'Effectif hors plage',
        'subunits' => 'Sous-unités attendues',
        'info' => 'À compléter',
    ];

    /** Écarts propres à une personne (sans place, sans chef) : ils ne sont pas comptés pour un échelon. */
    private const PERSON_CODES = ['without_place', 'without_chief'];

    /**
     * @param array<string, mixed> $result OrgAdequacyEngine::evaluate()
     * @return array<string, list<array<string, mixed>>> type => écarts, dans l’ordre du moteur
     */
    public static function grouped(array $result): array
    {
        $groupOf = [];
        foreach (self::GROUPS as $g => $def) {
            foreach ($def['codes'] as $code) {
                $groupOf[$code] = $g;
            }
        }
        $out = array_fill_keys(array_keys(self::GROUPS), []);
        foreach (is_array($result['issues'] ?? null) ? $result['issues'] : [] as $issue) {
            $g = $groupOf[(string) ($issue['code'] ?? '')] ?? null;
            if ($g !== null) {
                $out[$g][] = $issue;
            }
        }

        return $out;
    }

    /**
     * Règles d’un type désactivées toutes ensemble (le type n’est alors plus évalué).
     *
     * @param array<string, mixed> $settings OrgAdequacySettings::sanitize()
     */
    public static function groupDisabled(string $group, array $settings): bool
    {
        $rules = is_array($settings['rules'] ?? null) ? $settings['rules'] : [];
        foreach (self::GROUPS[$group]['rules'] ?? [] as $rule) {
            if (!empty($rules[$rule])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Unités de chaque échelon du référentiel et écarts qui les concernent.
     *
     * @param array<string, mixed> $result OrgAdequacyEngine::evaluate()
     * @return array<string, array{units: int, issues: int}>
     */
    public static function byEchelon(array $result): array
    {
        $out = [];
        foreach (EchelonStandards::keys() as $key) {
            $out[$key] = ['units' => 0, 'issues' => 0];
        }
        $keyOfUnit = [];
        foreach (is_array($result['units'] ?? null) ? $result['units'] : [] as $id => $u) {
            $key = EchelonStandards::standardKey((string) ($u['echelon'] ?? ''));
            if ($key !== null && isset($out[$key])) {
                $keyOfUnit[(int) $id] = $key;
                $out[$key]['units']++;
            }
        }
        foreach (is_array($result['issues'] ?? null) ? $result['issues'] : [] as $issue) {
            $unitId = (int) ($issue['unit_id'] ?? 0);
            if (isset($keyOfUnit[$unitId]) && !in_array((string) ($issue['code'] ?? ''), self::PERSON_CODES, true)) {
                $out[$keyOfUnit[$unitId]]['issues']++;
            }
        }

        return $out;
    }

    /**
     * L’échelon s’écarte-t-il du référentiel ? (activation mise à part : elle a son propre interrupteur.)
     *
     * @param array<string, mixed> $settings OrgAdequacySettings::sanitize()
     */
    public static function customized(array $settings, string $key): bool
    {
        $o = is_array($settings['echelons'][$key] ?? null) ? $settings['echelons'][$key] : [];
        unset($o['enabled']);

        return $o !== [];
    }
}
