<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Modèles de départ pour les grades sur mesure (référentiel des grades → onglet « Sur mesure »).
 *
 * Chaque modèle : un nom de système, puis ses catégories du plus haut au plus bas, chacune avec ses grades
 * du plus haut au plus bas ([abrégé, nom complet]). Tout reste modifiable après création. Les noms sont ceux
 * des univers (en anglais, comme dans les jeux et les wikis).
 */
final class CustomGradeTemplates
{
    /** Code « pays » des systèmes sur mesure (doctrine des fiches membres). */
    public const COUNTRY = 'ZZ';

    /**
     * @return array<string, array{label: string, system: string, categories: list<array{label: string, grades: list<array{0: string, 1: string}>}>}>
     */
    public static function all(): array
    {
        return [
            'vide' => [
                'label' => 'Vide',
                'system' => 'Grades de la communauté',
                'categories' => [
                    ['label' => 'Grades', 'grades' => []],
                ],
            ],
            'scp' => [
                'label' => 'SCP Foundation',
                'system' => 'SCP Foundation',
                'categories' => [
                    ['label' => 'Overseer Council', 'grades' => [['O5', 'O5 Council Member']]],
                    ['label' => 'Site Command', 'grades' => [['SD', 'Site Director'], ['ASD', 'Assistant Site Director']]],
                    ['label' => 'Mobile Task Forces', 'grades' => [
                        ['CMDR', 'MTF Commander'], ['CPT', 'MTF Captain'], ['SGT', 'MTF Sergeant'], ['OPR', 'MTF Operative'],
                    ]],
                    ['label' => 'Security Department', 'grades' => [
                        ['SC', 'Security Chief'], ['SCPT', 'Security Captain'], ['SLT', 'Security Lieutenant'],
                        ['SSGT', 'Security Sergeant'], ['SO', 'Security Officer'], ['CDT', 'Security Cadet'],
                    ]],
                    ['label' => 'Research Department', 'grades' => [
                        ['RD', 'Research Director'], ['SR', 'Senior Researcher'], ['RES', 'Researcher'],
                        ['JR', 'Junior Researcher'], ['RA', 'Research Assistant'],
                    ]],
                    ['label' => 'Personnel', 'grades' => [['D', 'Class-D Personnel']]],
                ],
            ],
            'sw_empire' => [
                'label' => 'Star Wars : Empire galactique',
                'system' => 'Galactic Empire',
                'categories' => [
                    ['label' => 'High Command', 'grades' => [
                        ['GMOFF', 'Grand Moff'], ['MOFF', 'Moff'], ['GADM', 'Grand Admiral'], ['ADM', 'Admiral'],
                    ]],
                    ['label' => 'Imperial Officers', 'grades' => [
                        ['COL', 'Colonel'], ['MAJ', 'Major'], ['CPT', 'Captain'], ['LT', 'Lieutenant'], ['ENS', 'Ensign'],
                    ]],
                    ['label' => 'Stormtrooper Corps', 'grades' => [
                        ['TKCMD', 'Stormtrooper Commander'], ['SGT', 'Stormtrooper Sergeant'], ['CPL', 'Stormtrooper Corporal'],
                        ['TK', 'Stormtrooper'], ['CDT', 'Stormtrooper Cadet'],
                    ]],
                ],
            ],
            'sw_republic' => [
                'label' => 'Star Wars : Grande Armée de la République',
                'system' => 'Grand Army of the Republic',
                'categories' => [
                    ['label' => 'Jedi Command', 'grades' => [['GEN', 'Jedi General'], ['JCMD', 'Jedi Commander']]],
                    ['label' => 'Clone Officers', 'grades' => [
                        ['MCMD', 'Marshal Commander'], ['CMDR', 'Clone Commander'], ['CPT', 'Clone Captain'], ['LT', 'Clone Lieutenant'],
                    ]],
                    ['label' => 'Clone NCOs', 'grades' => [
                        ['SGM', 'Clone Sergeant Major'], ['SGT', 'Clone Sergeant'], ['CPL', 'Clone Corporal'],
                    ]],
                    ['label' => 'Clone Troopers', 'grades' => [
                        ['VET', 'Veteran Clone Trooper'], ['CT', 'Clone Trooper'], ['CC', 'Clone Cadet'],
                    ]],
                ],
            ],
            'wh40k' => [
                'label' => 'Warhammer 40,000 : Astra Militarum',
                'system' => 'Astra Militarum',
                'categories' => [
                    ['label' => 'High Command', 'grades' => [['LG', 'Lord General'], ['GEN', 'General']]],
                    ['label' => 'Commissariat', 'grades' => [
                        ['LCOM', 'Lord Commissar'], ['COM', 'Commissar'], ['CCOM', 'Cadet Commissar'],
                    ]],
                    ['label' => 'Regimental Officers', 'grades' => [
                        ['COL', 'Colonel'], ['MAJ', 'Major'], ['CPT', 'Captain'], ['LT', 'Lieutenant'],
                    ]],
                    ['label' => 'Guardsmen', 'grades' => [
                        ['SGT', 'Sergeant'], ['VET', 'Veteran'], ['GDM', 'Guardsman'], ['CON', 'Conscript'],
                    ]],
                ],
            ],
        ];
    }

    /** @return array{label: string, system: string, categories: list<array{label: string, grades: list<array{0: string, 1: string}>}>}|null */
    public static function find(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }
}
