<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Champs d’une fiche du référentiel des qualifications envoyés par un formulaire : celui du back-office
 * (référentiel des qualifications) et celui du Studio de l’Academy (qualifications et brevets). Un seul endroit
 * pour la liste des champs ; le Studio n’en affiche qu’une partie, les autres gardent leur valeur (merge()).
 */
final class QualificationDefinitionPayload
{
    public const FIELDS = [
        'code', 'name', 'short_name', 'description', 'category_id', 'type_id', 'uses_levels', 'is_permanent',
        'default_validity_months', 'alert_before_expiry_days', 'grace_period_days', 'enforce_level_progression',
        'requires_panel', 'qualification_scope', 'certificate_template_id', 'certificate_number_format',
        'currency_days', 'requires_exam', 'renewal_required',
    ];

    /**
     * @param callable(string): mixed $input valeur envoyée d’un champ (null s’il est absent)
     * @return array<string, mixed>
     */
    public static function fromInput(callable $input): array
    {
        $out = [];
        foreach (self::FIELDS as $field) {
            $out[$field] = $input($field);
        }
        $out['qualification_scope'] ??= 'global';

        return $out;
    }

    /**
     * Fiche existante complétée par les champs envoyés : un champ absent du formulaire garde sa valeur.
     *
     * @param array<string, mixed>|null $existing
     * @param array<string, mixed> $sent
     * @return array<string, mixed>
     */
    public static function merge(?array $existing, array $sent): array
    {
        $out = [];
        foreach (self::FIELDS as $field) {
            $out[$field] = array_key_exists($field, $sent) ? $sent[$field] : ($existing[$field] ?? null);
        }
        $out['qualification_scope'] = ($out['qualification_scope'] ?? 'global') === 'unit' ? 'unit' : 'global';

        return $out;
    }
}
