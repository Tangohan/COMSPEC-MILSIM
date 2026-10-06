<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Aides du formulaire de qualification (back-office) : exemples MILSIM prêts à adapter
 * et aperçu du numéro de brevet. Les exemples sont injectés en JSON dans la page et
 * appliqués côté navigateur ; rien n’est enregistré tant que l’administrateur ne valide pas.
 */
final class QualificationExamples
{
    public const DEFAULT_NUMBER_FORMAT = 'QUAL-{code}-{year}-{seq}';

    /**
     * `category` / `type` : mots recherchés dans les libellés des catégories / types de la communauté
     * (premier libellé qui contient l’un des mots, sans tenir compte de la casse ni des accents).
     *
     * @return list<array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            [
                'key' => 'sc1', 'label' => 'SC1 — Sauvetage au combat niveau 1',
                'code' => 'SC1', 'name' => 'Sauvetage au combat niveau 1', 'short_name' => 'SC1',
                'description' => 'Gestes réflexes de tout combattant : garrot, pansement compressif, mise en position latérale, compte rendu MARCHE.',
                'scope' => 'global', 'category' => ['santé', 'sante', 'médical', 'medical', 'secour'], 'type' => ['technique', 'aptitude'],
                'validity' => 24, 'alert' => 30, 'grace' => 15, 'currency' => '',
                'uses_levels' => false, 'is_permanent' => false, 'enforce_level_progression' => false,
                'requires_panel' => false, 'requires_exam' => true, 'renewal_required' => true,
                'number_format' => 'SC1-{year}-{seq}',
            ],
            [
                'key' => 'sc2', 'label' => 'SC2 — Sauvetage au combat niveau 2',
                'code' => 'SC2', 'name' => 'Sauvetage au combat niveau 2', 'short_name' => 'SC2',
                'description' => 'Auxiliaire sanitaire de groupe : bilan, perfusion, évacuation. Prérequis conseillé : SC1 valide.',
                'scope' => 'global', 'category' => ['santé', 'sante', 'médical', 'medical', 'secour'], 'type' => ['technique', 'aptitude'],
                'validity' => 24, 'alert' => 45, 'grace' => 15, 'currency' => '',
                'uses_levels' => false, 'is_permanent' => false, 'enforce_level_progression' => false,
                'requires_panel' => true, 'requires_exam' => true, 'renewal_required' => true,
                'number_format' => 'SC2-{year}-{seq}',
            ],
            [
                'key' => 'te', 'label' => 'Tireur d’élite',
                'code' => 'TE', 'name' => 'Tireur d’élite', 'short_name' => 'TE',
                'description' => 'Tir de précision à longue distance, observation, calcul de correction. Niveaux conseillés : Tireur de précision puis Tireur d’élite.',
                'scope' => 'global', 'category' => ['combat', 'tir', 'infanterie'], 'type' => ['spécialité', 'specialite', 'technique'],
                'validity' => 12, 'alert' => 30, 'grace' => 30, 'currency' => 60,
                'uses_levels' => true, 'is_permanent' => false, 'enforce_level_progression' => true,
                'requires_panel' => false, 'requires_exam' => true, 'renewal_required' => true,
                'number_format' => 'TE-{year}-{seq}',
            ],
            [
                'key' => 'jtac', 'label' => 'JTAC — Contrôleur air avancé',
                'code' => 'JTAC', 'name' => 'Contrôleur aérien avancé (JTAC)', 'short_name' => 'JTAC',
                'description' => 'Guidage des appuis aériens (CAS) : 9-line, contrôle type 1/2/3, coordination avec les pilotes.',
                'scope' => 'global', 'category' => ['appui', 'air', 'feux'], 'type' => ['spécialité', 'specialite', 'technique'],
                'validity' => 24, 'alert' => 30, 'grace' => 15, 'currency' => 90,
                'uses_levels' => false, 'is_permanent' => false, 'enforce_level_progression' => false,
                'requires_panel' => true, 'requires_exam' => true, 'renewal_required' => true,
                'number_format' => 'JTAC-{year}-{seq}',
            ],
            [
                'key' => 'pilote', 'label' => 'Pilote hélicoptère',
                'code' => 'PIL-H', 'name' => 'Pilote hélicoptère', 'short_name' => 'PIL-H',
                'description' => 'Pilotage, navigation tactique, insertion et extraction. Niveaux conseillés : Élève pilote, Pilote, Chef de bord.',
                'scope' => 'global', 'category' => ['aviation', 'air', 'aéro', 'aero'], 'type' => ['spécialité', 'specialite', 'technique'],
                'validity' => 12, 'alert' => 30, 'grace' => 15, 'currency' => 60,
                'uses_levels' => true, 'is_permanent' => false, 'enforce_level_progression' => true,
                'requires_panel' => true, 'requires_exam' => true, 'renewal_required' => true,
                'number_format' => 'PIL-{year}-{seq}',
            ],
            [
                'key' => 'cdg', 'label' => 'Chef de groupe',
                'code' => 'CDG', 'name' => 'Chef de groupe', 'short_name' => 'CDG',
                'description' => 'Commandement d’un groupe de combat : ordres initiaux, conduite, comptes rendus. Acquise une fois pour toutes.',
                'scope' => 'unit', 'category' => ['commandement', 'encadrement'], 'type' => ['commandement', 'brevet'],
                'validity' => '', 'alert' => '', 'grace' => '', 'currency' => '',
                'uses_levels' => false, 'is_permanent' => true, 'enforce_level_progression' => false,
                'requires_panel' => true, 'requires_exam' => false, 'renewal_required' => false,
                'number_format' => 'CDG-{year}-{seq}',
            ],
            [
                'key' => 'eod', 'label' => 'EOD — Neutralisation d’explosifs',
                'code' => 'EOD', 'name' => 'Neutralisation et destruction d’explosifs', 'short_name' => 'EOD',
                'description' => 'Reconnaissance, identification et neutralisation des engins explosifs (IED, UXO).',
                'scope' => 'global', 'category' => ['génie', 'genie', 'combat'], 'type' => ['spécialité', 'specialite', 'technique'],
                'validity' => 12, 'alert' => 30, 'grace' => 0, 'currency' => '',
                'uses_levels' => false, 'is_permanent' => false, 'enforce_level_progression' => false,
                'requires_panel' => false, 'requires_exam' => true, 'renewal_required' => true,
                'number_format' => 'EOD-{year}-{seq}',
            ],
            [
                'key' => 'radio', 'label' => 'Opérateur radio',
                'code' => 'RADIO', 'name' => 'Opérateur transmissions', 'short_name' => 'RADIO',
                'description' => 'Procédures radio, indicatifs, rédaction des messages, mise en œuvre des postes.',
                'scope' => 'global', 'category' => ['transmission', 'communication'], 'type' => ['technique', 'aptitude'],
                'validity' => '', 'alert' => '', 'grace' => '', 'currency' => '',
                'uses_levels' => false, 'is_permanent' => true, 'enforce_level_progression' => false,
                'requires_panel' => false, 'requires_exam' => true, 'renewal_required' => false,
                'number_format' => '',
            ],
            [
                'key' => 'para', 'label' => 'Brevet parachutiste',
                'code' => 'PARA', 'name' => 'Brevet parachutiste', 'short_name' => 'PARA',
                'description' => 'Six sauts validés, dont un de nuit. Brevet acquis à vie ; le maintien des sauts se suit à part.',
                'scope' => 'global', 'category' => ['aéroporté', 'aeroporte', 'air', 'combat'], 'type' => ['brevet'],
                'validity' => '', 'alert' => '', 'grace' => '', 'currency' => 180,
                'uses_levels' => false, 'is_permanent' => true, 'enforce_level_progression' => false,
                'requires_panel' => false, 'requires_exam' => true, 'renewal_required' => false,
                'number_format' => 'BP-{year}-{seq}',
            ],
        ];
    }

    /**
     * Aperçu du numéro de brevet, même règle que QualificationCertificatePdfService::allocateNumber().
     */
    public static function numberPreview(string $format, string $code, int $year, int $seq): string
    {
        $format = trim($format) === '' ? self::DEFAULT_NUMBER_FORMAT : trim($format);
        $code = preg_replace('/[^A-Z0-9_\-]/i', '', $code) ?: 'QUAL';
        $seqStr = str_pad((string) $seq, 4, '0', STR_PAD_LEFT);

        return str_replace(
            ['{code}', '{year}', '{seq}', '{année}', '{sequence}'],
            [strtoupper($code), (string) $year, $seqStr, (string) $year, $seqStr],
            $format
        );
    }
}
