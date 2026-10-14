<?php

declare(strict_types=1);

namespace App\Services\Community;

/**
 * Structures de départ proposées à l’étape « Organisation » de l’assistant de création.
 *
 * Chaque modèle produit la liste attendue par le constructeur ORBAT (champ wizard_units_json) :
 * types limités à group, section, team et squad, adresses courtes uniques dans le modèle.
 */
final class WizardOrbatPresets
{
    public const DEFAULT_ID = 'minimal';

    /**
     * @return list<array{id: string, label: string, audience: string, description: string, units: list<array<string, mixed>>}>
     */
    public static function all(): array
    {
        return [
            [
                'id' => 'minimal',
                'label' => 'Structure minimale',
                'audience' => 'Pour démarrer',
                'description' => 'Un état-major, une section et une équipe. Idéal pour poser le cadre puis compléter au fil des arrivées.',
                'units' => self::build([
                    ['em', '', 'État-major', 'group'],
                    ['s1', 'em', '1re section', 'section'],
                    ['t1', 's1', '1re équipe', 'team'],
                ]),
            ],
            [
                'id' => 'squad',
                'label' => 'Escouade',
                'audience' => 'Petit groupe (6 à 12 joueurs)',
                'description' => 'Une escouade avec son chef et deux équipes de feu. Le format des petites communautés et des sessions à effectif réduit.',
                'units' => self::build([
                    ['sq', '', 'Escouade', 'squad'],
                    ['cmd', 'sq', 'Commandement d’escouade', 'team'],
                    ['ft1', 'sq', 'Équipe de feu 1', 'team'],
                    ['ft2', 'sq', 'Équipe de feu 2', 'team'],
                ]),
            ],
            [
                'id' => 'section_fr',
                'label' => 'Section d’infanterie (FR)',
                'audience' => 'Environ 30 joueurs',
                'description' => 'Une section française : groupe de commandement et trois groupes de combat, chacun avec une équipe feu et une équipe choc.',
                'units' => self::build(array_merge(
                    [
                        ['sec', '', 'Section', 'section'],
                        ['gc', 'sec', 'Groupe de commandement', 'team'],
                    ],
                    self::frenchSquads('sec', 3)
                )),
            ],
            [
                'id' => 'platoon_us',
                'label' => 'Peloton US (platoon)',
                'audience' => 'Environ 40 joueurs',
                'description' => 'Un peloton américain : commandement, trois groupes de combat à deux équipes Alpha et Bravo, et un groupe d’armes.',
                'units' => self::build(array_merge(
                    [
                        ['plt', '', 'Peloton', 'section'],
                        ['hq', 'plt', 'Commandement de peloton', 'team'],
                    ],
                    self::usSquads('plt', 3),
                    [['wpn', 'plt', 'Groupe d’armes', 'squad']]
                )),
            ],
            [
                'id' => 'company_fr',
                'label' => 'Compagnie (3 sections)',
                'audience' => 'Grande communauté (80 joueurs et plus)',
                'description' => 'Une compagnie avec section de commandement, trois sections de combat à trois groupes et une section d’appui.',
                'units' => self::build(array_merge(
                    [
                        ['cie', '', 'Compagnie', 'group'],
                        ['scl', 'cie', 'Section de commandement', 'section'],
                    ],
                    self::companySections(),
                    [['sap', 'cie', 'Section d’appui', 'section']]
                )),
            ],
            [
                'id' => 'sof_detachment',
                'label' => 'Détachement forces spéciales',
                'audience' => '12 à 16 joueurs',
                'description' => 'Un détachement de type ODA : commandement, deux équipes d’assaut, une équipe reconnaissance et tireurs d’élite.',
                'units' => self::build([
                    ['det', '', 'Détachement', 'group'],
                    ['cmd', 'det', 'Commandement du détachement', 'team'],
                    ['ta', 'det', 'Équipe Alpha', 'team'],
                    ['tb', 'det', 'Équipe Bravo', 'team'],
                    ['recce', 'det', 'Équipe reconnaissance', 'team'],
                ]),
            ],
            [
                'id' => 'combined_arms',
                'label' => 'Groupement interarmes',
                'audience' => 'Communauté multi-rôles',
                'description' => 'Un poste de commandement, une section d’infanterie, un peloton blindé, une escadrille d’hélicoptères et une cellule soutien (santé, logistique).',
                'units' => self::build(array_merge(
                    [
                        ['gtia', '', 'Groupement', 'group'],
                        ['pc', 'gtia', 'Poste de commandement', 'section'],
                        ['inf', 'gtia', 'Section d’infanterie', 'section'],
                    ],
                    self::frenchSquads('inf', 2, 'inf-'),
                    [
                        ['blind', 'gtia', 'Peloton blindé', 'section'],
                        ['blind1', 'blind', 'Patrouille de chars 1', 'team'],
                        ['blind2', 'blind', 'Patrouille de chars 2', 'team'],
                        ['aero', 'gtia', 'Escadrille d’hélicoptères', 'section'],
                        ['aero1', 'aero', 'Équipage 1', 'team'],
                        ['aero2', 'aero', 'Équipage 2', 'team'],
                        ['sout', 'gtia', 'Cellule soutien', 'section'],
                        ['sante', 'sout', 'Équipe santé', 'team'],
                        ['log', 'sout', 'Équipe logistique', 'team'],
                    ]
                )),
            ],
            [
                'id' => 'gaming',
                'label' => 'Communauté de joueurs',
                'audience' => 'Associations et communautés multi-jeux',
                'description' => 'Une organisation non militaire : bureau, animation, accueil, technique et création de missions.',
                'units' => self::build([
                    ['com', '', 'Communauté', 'group'],
                    ['bureau', 'com', 'Bureau', 'section'],
                    ['anim', 'com', 'Animation', 'section'],
                    ['accueil', 'com', 'Accueil et recrutement', 'section'],
                    ['tech', 'com', 'Technique et serveurs', 'section'],
                    ['missions', 'com', 'Création de missions', 'section'],
                ]),
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function unitsFor(string $id): array
    {
        foreach (self::all() as $preset) {
            if ($preset['id'] === $id) {
                return $preset['units'];
            }
        }

        return self::unitsFor(self::DEFAULT_ID);
    }

    /**
     * Données allégées pour le front (sans recalcul côté navigateur).
     *
     * @return list<array{id: string, label: string, audience: string, description: string, count: int, units: list<array<string, mixed>>}>
     */
    public static function forWizard(): array
    {
        $out = [];
        foreach (self::all() as $preset) {
            $preset['count'] = count($preset['units']);
            $out[] = $preset;
        }

        return $out;
    }

    /**
     * @param list<array{0: string, 1: string, 2: string, 3: string}> $rows [clé, clé parente, nom, type]
     * @return list<array<string, mixed>>
     */
    private static function build(array $rows): array
    {
        $orderByParent = [];
        $usedSlugs = [];
        $units = [];
        foreach ($rows as [$key, $parent, $name, $type]) {
            $order = $orderByParent[$parent] ?? 0;
            $orderByParent[$parent] = $order + 1;
            $slug = self::slugify($name);
            if ($slug === '' || isset($usedSlugs[$slug])) {
                $slug = self::slugify(($slug === '' ? '' : $slug . ' ') . $key);
            }
            $usedSlugs[$slug] = true;
            $units[] = [
                'key' => $key,
                'parent_key' => $parent,
                'name' => $name,
                'slug' => $slug,
                'type' => $type,
                'display_order' => $order,
            ];
        }

        return $units;
    }

    /**
     * @return list<array{0: string, 1: string, 2: string, 3: string}>
     */
    private static function frenchSquads(string $parent, int $count, string $prefix = ''): array
    {
        $labels = [1 => '1er groupe', 2 => '2e groupe', 3 => '3e groupe', 4 => '4e groupe'];
        $rows = [];
        for ($i = 1; $i <= $count; $i++) {
            $g = $prefix . 'g' . $i;
            $rows[] = [$g, $parent, $labels[$i] ?? ($i . 'e groupe'), 'squad'];
            $rows[] = [$g . 'feu', $g, 'Équipe feu', 'team'];
            $rows[] = [$g . 'choc', $g, 'Équipe choc', 'team'];
        }

        return $rows;
    }

    /**
     * @return list<array{0: string, 1: string, 2: string, 3: string}>
     */
    private static function usSquads(string $parent, int $count): array
    {
        $labels = [1 => '1er groupe', 2 => '2e groupe', 3 => '3e groupe'];
        $rows = [];
        for ($i = 1; $i <= $count; $i++) {
            $sq = 'sq' . $i;
            $rows[] = [$sq, $parent, $labels[$i] ?? ($i . 'e groupe'), 'squad'];
            $rows[] = [$sq . 'a', $sq, 'Équipe Alpha', 'team'];
            $rows[] = [$sq . 'b', $sq, 'Équipe Bravo', 'team'];
        }

        return $rows;
    }

    /**
     * @return list<array{0: string, 1: string, 2: string, 3: string}>
     */
    private static function companySections(): array
    {
        $labels = [1 => '1re section', 2 => '2e section', 3 => '3e section'];
        $rows = [];
        foreach ($labels as $i => $label) {
            $s = 's' . $i;
            $rows[] = [$s, 'cie', $label, 'section'];
            for ($g = 1; $g <= 3; $g++) {
                $rows[] = [$s . 'g' . $g, $s, $g === 1 ? '1er groupe' : $g . 'e groupe', 'squad'];
            }
        }

        return $rows;
    }

    /**
     * Adresse courte lisible (minuscules, chiffres, tirets), 50 caractères au plus.
     */
    private static function slugify(string $name): string
    {
        $s = mb_strtolower(trim($name));
        if (class_exists(\Normalizer::class)) {
            $nfd = \Normalizer::normalize($s, \Normalizer::FORM_D);
            if (is_string($nfd)) {
                $s = preg_replace('/\p{Mn}+/u', '', $nfd) ?? $s;
            }
        }
        $s = strtr($s, ['’' => '-', "'" => '-', 'œ' => 'oe', 'æ' => 'ae']);
        $s = trim((string) preg_replace('/[^a-z0-9]+/', '-', $s), '-');

        return rtrim(substr($s, 0, 50), '-');
    }
}
