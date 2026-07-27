<?php

declare(strict_types=1);

namespace App\Services\Doctrine;

/**
 * Échelons de commandement, avec effectif de référence et fonctions attendues.
 *
 * L’effectif est une **référence d’articulation**, pas une contrainte : une unité milsim
 * arme rarement un échelon au complet. Il sert à situer un groupe dans une hiérarchie et à
 * savoir quelles fonctions doivent être tenues en priorité.
 *
 * Les fourchettes d’effectif reprennent l’articulation d’infanterie couramment enseignée.
 * Elles varient selon l’arme, le type d’unité et l’époque : chaque communauté peut les
 * ajuster.
 */
final class EchelonCatalog
{
    /**
     * @return list<array{
     *   key: string, origin: string, level: int, label: string, commanded_by: string,
     *   strength_min: int, strength_max: int, composition: string, functions: list<string>
     * }>
     */
    public static function all(): array
    {
        return array_merge(self::americanEchelons(), self::frenchEchelons());
    }

    /** @return list<array<string, mixed>> */
    public static function forReferential(string $referential): array
    {
        $entries = DoctrineReferential::filter(self::all(), $referential);
        // Du plus petit au plus grand échelon, puis par origine pour un affichage stable
        // lorsque les deux référentiels sont présentés côte à côte.
        usort($entries, static function (array $a, array $b): int {
            return [$a['level'], $a['origin']] <=> [$b['level'], $b['origin']];
        });

        return $entries;
    }

    public static function find(string $key): ?array
    {
        foreach (self::all() as $echelon) {
            if ($echelon['key'] === $key) {
                return $echelon;
            }
        }

        return null;
    }

    public static function strengthLabel(array $echelon): string
    {
        $min = (int) ($echelon['strength_min'] ?? 0);
        $max = (int) ($echelon['strength_max'] ?? 0);
        if ($min <= 0 && $max <= 0) {
            return '—';
        }
        if ($min === $max) {
            return (string) $min;
        }

        return $min . ' à ' . $max;
    }

    /** @return list<array<string, mixed>> */
    private static function americanEchelons(): array
    {
        return [
            [
                'key' => 'us_fire_team',
                'origin' => DoctrineReferential::US,
                'level' => 1,
                'label' => 'Fire team',
                'commanded_by' => 'Team leader',
                'strength_min' => 4,
                'strength_max' => 4,
                'composition' => 'Plus petite cellule de combat, deux par squad.',
                'functions' => [
                    'Team leader — conduit la cellule',
                    'Automatic rifleman — appui feu',
                    'Grenadier — tir lance-grenades',
                    'Rifleman — voltigeur',
                ],
            ],
            [
                'key' => 'us_squad',
                'origin' => DoctrineReferential::US,
                'level' => 2,
                'label' => 'Squad',
                'commanded_by' => 'Squad leader',
                'strength_min' => 8,
                'strength_max' => 9,
                'composition' => 'Un chef et deux fire teams.',
                'functions' => [
                    'Squad leader — commande le squad',
                    'Deux team leaders',
                    'Appui feu réparti entre les deux cellules',
                ],
            ],
            [
                'key' => 'us_platoon',
                'origin' => DoctrineReferential::US,
                'level' => 3,
                'label' => 'Platoon',
                'commanded_by' => 'Platoon leader',
                'strength_min' => 30,
                'strength_max' => 42,
                'composition' => 'Un élément de commandement, trois squads de combat, un squad d’armes.',
                'functions' => [
                    'Platoon leader — commande le platoon',
                    'Platoon sergeant — adjoint, conduit le soutien',
                    'Opérateur radio',
                    'Auxiliaire sanitaire',
                    'Chefs de squad',
                ],
            ],
            [
                'key' => 'us_company',
                'origin' => DoctrineReferential::US,
                'level' => 4,
                'label' => 'Company',
                'commanded_by' => 'Company commander',
                'strength_min' => 100,
                'strength_max' => 150,
                'composition' => 'Un commandement de compagnie, trois platoons de combat, un platoon d’armes.',
                'functions' => [
                    'Company commander — commande la compagnie',
                    'Executive officer — adjoint',
                    'First sergeant — encadrement et soutien',
                    'Cellule transmissions',
                    'Cellule logistique',
                ],
            ],
            [
                'key' => 'us_battalion',
                'origin' => DoctrineReferential::US,
                'level' => 5,
                'label' => 'Battalion',
                'commanded_by' => 'Battalion commander',
                'strength_min' => 500,
                'strength_max' => 800,
                'composition' => 'Un état-major, plusieurs companies de combat et de soutien.',
                'functions' => [
                    'Battalion commander',
                    'Executive officer',
                    'État-major : personnel, renseignement, opérations, logistique',
                    'Command sergeant major',
                ],
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function frenchEchelons(): array
    {
        return [
            [
                'key' => 'fr_equipe',
                'origin' => DoctrineReferential::FR,
                'level' => 1,
                'label' => 'Équipe',
                'commanded_by' => 'Chef d’équipe',
                'strength_min' => 3,
                'strength_max' => 4,
                'composition' => 'Plus petite cellule, deux par groupe de combat.',
                'functions' => [
                    'Chef d’équipe — conduit la cellule',
                    'Tireur d’appui',
                    'Grenadier voltigeur',
                    'Voltigeur',
                ],
            ],
            [
                'key' => 'fr_groupe',
                'origin' => DoctrineReferential::FR,
                'level' => 2,
                'label' => 'Groupe de combat',
                'commanded_by' => 'Chef de groupe',
                'strength_min' => 8,
                'strength_max' => 10,
                'composition' => 'Un chef et deux équipes : une de choc, une d’appui.',
                'functions' => [
                    'Chef de groupe — commande le groupe',
                    'Adjoint, chef de la seconde équipe',
                    'Équipe de choc',
                    'Équipe d’appui',
                ],
            ],
            [
                'key' => 'fr_section',
                'origin' => DoctrineReferential::FR,
                'level' => 3,
                'label' => 'Section',
                'commanded_by' => 'Chef de section',
                'strength_min' => 30,
                'strength_max' => 40,
                'composition' => 'Un commandement de section et trois à quatre groupes de combat.',
                'functions' => [
                    'Chef de section — commande la section',
                    'Adjoint au chef de section',
                    'Opérateur radio',
                    'Auxiliaire sanitaire',
                    'Chefs de groupe',
                ],
            ],
            [
                'key' => 'fr_compagnie',
                'origin' => DoctrineReferential::FR,
                'level' => 4,
                'label' => 'Compagnie',
                'commanded_by' => 'Commandant d’unité',
                'strength_min' => 100,
                'strength_max' => 150,
                'composition' => 'Une section de commandement et trois à quatre sections de combat.',
                'functions' => [
                    'Commandant d’unité — commande la compagnie',
                    'Capitaine adjoint',
                    'Adjudant d’unité — encadrement et soutien',
                    'Cellule transmissions',
                    'Cellule logistique',
                ],
            ],
            [
                'key' => 'fr_regiment',
                'origin' => DoctrineReferential::FR,
                'level' => 5,
                'label' => 'Régiment',
                'commanded_by' => 'Chef de corps',
                'strength_min' => 600,
                'strength_max' => 1200,
                'composition' => 'Un état-major et plusieurs compagnies de combat et de soutien.',
                'functions' => [
                    'Chef de corps',
                    'Chef de corps adjoint',
                    'État-major : personnel, renseignement, opérations, logistique',
                    'Major du régiment',
                ],
            ],
        ];
    }
}
