<?php

declare(strict_types=1);

namespace App\Services\Doctrine;

/**
 * Parcours de formation : étapes d’un cursus, puis qualifications à entretenir.
 *
 * Deux natures d’entrée :
 *  - `stage` : une étape du parcours, franchie une fois (formation initiale, école, brevet) ;
 *  - `qualification` : une aptitude à revalider périodiquement (secourisme, tir).
 *
 * Les durées de validité sont des **valeurs de départ pour le milsim**, pas des périodicités
 * réglementaires : chaque communauté fixe les siennes. `validity_months` à `null` signifie
 * « acquis sans échéance ».
 */
final class TrainingPipelineCatalog
{
    public const TYPE_STAGE = 'stage';
    public const TYPE_QUALIFICATION = 'qualification';

    /** @return array<string, string> */
    public static function typeLabels(): array
    {
        return [
            self::TYPE_STAGE => 'Étape de parcours',
            self::TYPE_QUALIFICATION => 'Qualification à entretenir',
        ];
    }

    /**
     * @return list<array{
     *   key: string, origin: string, type: string, step: int, code: string, label: string,
     *   purpose: string, prerequisite: ?string, validity_months: ?int, recycling: ?string
     * }>
     */
    public static function all(): array
    {
        return array_merge(self::americanPipeline(), self::frenchPipeline());
    }

    /** @return list<array<string, mixed>> */
    public static function forReferential(string $referential): array
    {
        $entries = DoctrineReferential::filter(self::all(), $referential);
        usort($entries, static function (array $a, array $b): int {
            return [$a['type'], $a['step'], $a['origin']] <=> [$b['type'], $b['step'], $b['origin']];
        });

        return $entries;
    }

    public static function find(string $key): ?array
    {
        foreach (self::all() as $entry) {
            if ($entry['key'] === $key) {
                return $entry;
            }
        }

        return null;
    }

    public static function validityLabel(array $entry): string
    {
        $months = $entry['validity_months'] ?? null;
        if ($months === null) {
            return 'Sans échéance';
        }
        $months = (int) $months;
        if ($months % 12 === 0) {
            $years = intdiv($months, 12);

            return $years . ' an' . ($years > 1 ? 's' : '');
        }

        return $months . ' mois';
    }

    /** @return list<array<string, mixed>> */
    private static function americanPipeline(): array
    {
        return [
            [
                'key' => 'us_bct',
                'origin' => DoctrineReferential::US,
                'type' => self::TYPE_STAGE,
                'step' => 1,
                'code' => 'BCT',
                'label' => 'Formation initiale du combattant',
                'purpose' => 'Socle commun : ordre serré, tir, hygiène de campagne, condition physique, règles d’engagement.',
                'prerequisite' => null,
                'validity_months' => null,
                'recycling' => null,
            ],
            [
                'key' => 'us_ait',
                'origin' => DoctrineReferential::US,
                'type' => self::TYPE_STAGE,
                'step' => 2,
                'code' => 'AIT',
                'label' => 'Formation de spécialité',
                'purpose' => 'Qualifie sur un emploi précis à l’issue de la formation initiale.',
                'prerequisite' => 'Formation initiale du combattant',
                'validity_months' => null,
                'recycling' => null,
            ],
            [
                'key' => 'us_school',
                'origin' => DoctrineReferential::US,
                'type' => self::TYPE_STAGE,
                'step' => 3,
                'code' => 'École',
                'label' => 'École de spécialisation',
                'purpose' => 'Stage qualifiant complémentaire : aéroporté, aéromobile, chef d’équipe, tireur d’élite.',
                'prerequisite' => 'Formation de spécialité',
                'validity_months' => null,
                'recycling' => null,
            ],
            [
                'key' => 'us_badge',
                'origin' => DoctrineReferential::US,
                'type' => self::TYPE_STAGE,
                'step' => 4,
                'code' => 'Badge',
                'label' => 'Badge de qualification',
                'purpose' => 'Distinction sanctionnant une épreuve tenue : insigne porté sur la tenue.',
                'prerequisite' => 'École de spécialisation',
                'validity_months' => null,
                'recycling' => null,
            ],
            [
                'key' => 'us_cls',
                'origin' => DoctrineReferential::US,
                'type' => self::TYPE_QUALIFICATION,
                'step' => 1,
                'code' => 'CLS',
                'label' => 'Secourisme au combat',
                'purpose' => 'Gestes de sauvetage au contact : garrot, voies aériennes, conditionnement, transmission de la demande d’évacuation.',
                'prerequisite' => 'Formation initiale du combattant',
                'validity_months' => 12,
                'recycling' => 'Remise à niveau annuelle, avec mise en situation.',
            ],
            [
                'key' => 'us_weapons_qual',
                'origin' => DoctrineReferential::US,
                'type' => self::TYPE_QUALIFICATION,
                'step' => 2,
                'code' => 'Qualification tir',
                'label' => 'Contrôle de tir',
                'purpose' => 'Vérifie l’aptitude au tir sur l’arme de dotation.',
                'prerequisite' => null,
                'validity_months' => 6,
                'recycling' => 'Séance de contrôle semestrielle.',
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function frenchPipeline(): array
    {
        return [
            [
                'key' => 'fr_fgi',
                'origin' => DoctrineReferential::FR,
                'type' => self::TYPE_STAGE,
                'step' => 1,
                'code' => 'FGI',
                'label' => 'Formation générale initiale',
                'purpose' => 'Socle commun du militaire : comportement, ordre serré, tir, secourisme de base, condition physique.',
                'prerequisite' => null,
                'validity_months' => null,
                'recycling' => null,
            ],
            [
                'key' => 'fr_fsi',
                'origin' => DoctrineReferential::FR,
                'type' => self::TYPE_STAGE,
                'step' => 2,
                'code' => 'FSI',
                'label' => 'Formation de spécialité initiale',
                'purpose' => 'Qualifie sur l’emploi tenu au sein de l’unité.',
                'prerequisite' => 'Formation générale initiale',
                'validity_months' => null,
                'recycling' => null,
            ],
            [
                'key' => 'fr_cat1',
                'origin' => DoctrineReferential::FR,
                'type' => self::TYPE_STAGE,
                'step' => 3,
                'code' => 'CATi 1 / CT1',
                'label' => 'Certificat d’aptitude technique du premier niveau',
                'purpose' => 'Atteste la maîtrise technique de l’emploi et ouvre l’accès aux responsabilités d’équipe.',
                'prerequisite' => 'Formation de spécialité initiale',
                'validity_months' => null,
                'recycling' => null,
            ],
            [
                'key' => 'fr_brevet',
                'origin' => DoctrineReferential::FR,
                'type' => self::TYPE_STAGE,
                'step' => 4,
                'code' => 'Brevet',
                'label' => 'Brevet de spécialité',
                'purpose' => 'Sanctionne un cursus de spécialité et conditionne l’accès aux fonctions d’encadrement.',
                'prerequisite' => 'Certificat d’aptitude technique du premier niveau',
                'validity_months' => null,
                'recycling' => null,
            ],
            [
                'key' => 'fr_sc1',
                'origin' => DoctrineReferential::FR,
                'type' => self::TYPE_QUALIFICATION,
                'step' => 1,
                'code' => 'SC 1',
                'label' => 'Sauvetage au combat, premier niveau',
                'purpose' => 'Gestes de sauvetage au contact : garrot, position d’attente, alerte et demande d’évacuation.',
                'prerequisite' => 'Formation générale initiale',
                'validity_months' => 12,
                'recycling' => 'Recyclage annuel, avec mise en situation.',
            ],
            [
                'key' => 'fr_controle_tir',
                'origin' => DoctrineReferential::FR,
                'type' => self::TYPE_QUALIFICATION,
                'step' => 2,
                'code' => 'Contrôle de tir',
                'label' => 'Contrôle des aptitudes au tir',
                'purpose' => 'Vérifie l’aptitude au tir sur l’arme individuelle de dotation.',
                'prerequisite' => null,
                'validity_months' => 12,
                'recycling' => 'Séance de contrôle annuelle.',
            ],
        ];
    }
}
