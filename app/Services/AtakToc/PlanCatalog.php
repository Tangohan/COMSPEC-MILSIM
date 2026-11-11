<?php

declare(strict_types=1);

namespace App\Services\AtakToc;

/**
 * Catalogue des éléments d'un plan d'opération (éditeur web /atak/toc/plans) et leur équivalent dans l'app Plan de
 * l'ATAK natif (fn_planCatalog : 32 types, 9 couleurs). Une seule table, servie telle quelle à l'éditeur JS
 * (window.TOC_PLAN_CATALOG) : outils, géométries permises, type envoyé en jeu.
 *
 * Géométries : point (1 point), line (2 points ou plus), area (polygone, 3 points ou plus), circle (centre + rayon),
 * rect (2 coins), ellipse (centre + rayons rx, ry, rotation). Coordonnées : mètres Arma [x, y].
 */
final class PlanCatalog
{
    public const GEOMS = ['point', 'line', 'area', 'circle', 'rect', 'ellipse'];

    /** Couleurs de l'app Plan du jeu (fn_planCatalog), clé => hex affiché par le site (atak-briefing-plan.js). */
    public const GAME_COLORS = [
        'RED' => '#f24d47', 'CYAN' => '#4dccf2', 'GREEN' => '#73d973', 'YELLOW' => '#fae04d', 'BLUE' => '#5999ff',
        'WHITE' => '#f2f2f2', 'ORANGE' => '#ff9433', 'PURPLE' => '#c780f2', 'BLACK' => '#141414',
    ];

    /** Affiliations OTAN (APP-6) : ami, hostile, neutre, inconnu. */
    public const AFFILIATIONS = ['F', 'H', 'N', 'U'];

    /** Échelons (APP-6, code amplificateur du SIDC 2525C position 11-12). */
    public const ECHELONS = ['', 'team', 'squad', 'section', 'platoon', 'company', 'battalion', 'regiment', 'brigade'];

    /** Fonctions d'unité proposées (code de fonction SIDC 2525C, 6 caractères à partir de la position 5). */
    public const UNIT_TYPES = [
        'infantry' => ['Infanterie', 'UCI---'],
        'mech' => ['Infanterie mécanisée', 'UCIZ--'],
        'motorized' => ['Infanterie motorisée', 'UCIM--'],
        'recon' => ['Reconnaissance', 'UCR---'],
        'armor' => ['Blindés', 'UCA---'],
        'artillery' => ['Artillerie', 'UCF---'],
        'mortar' => ['Mortiers', 'UCFM--'],
        'engineer' => ['Génie', 'UCE---'],
        'medical' => ['Santé', 'USM---'],
        'supply' => ['Logistique', 'USS---'],
        'aviation' => ['Aviation (voilure tournante)', 'UCVR--'],
        'sof' => ['Forces spéciales', 'UCIS--'],
        'hq' => ['Poste de commandement', 'UH----'],
        'signal' => ['Transmissions', 'UUS---'],
        'antitank' => ['Antichar', 'UCAA--'],
        'airdefense' => ['Défense sol-air', 'UCD---'],
        'airborne' => ['Infanterie aéroportée', 'UCIA--'],
        'airassault' => ['Infanterie héliportée', 'UCIS--'],
        'mountain' => ['Infanterie de montagne', 'UCIO--'],
        'light' => ['Infanterie légère', 'UCIL--'],
        'uav' => ['Drones', 'UCVU--'],
        'fixedwing' => ['Aviation (voilure fixe)', 'UCVF--'],
        'unknown' => ['Sans fonction', '------'],
    ];

    /** Icônes simples (repère libre). */
    public const ICONS = ['flag', 'star', 'cross', 'target', 'triangle', 'dot', 'square', 'diamond'];

    /**
     * Types : clé => [libellé, géométries permises, type du jeu, couleur par défaut, famille, options].
     * Famille : unit | point | line | area | free | text | fire. Type du jeu « * » : décidé à l'envoi (unité selon affiliation).
     *
     * @return array<string, array{label:string, geoms:list<string>, game:string, color:string, family:string, dash?:string, arrow?:string, fill?:float, text?:string}>
     */
    public static function kinds(): array
    {
        return [
            'unit' => ['label' => 'Unité (symbole OTAN)', 'geoms' => ['point'], 'game' => '*', 'color' => '#6cb8ff', 'family' => 'unit'],
            'text' => ['label' => 'Texte', 'geoms' => ['point'], 'game' => 'NOTE', 'color' => '#f2f2f2', 'family' => 'text'],
            'icon' => ['label' => 'Repère', 'geoms' => ['point'], 'game' => 'NOTE', 'color' => '#fae04d', 'family' => 'point'],
            'checkpoint' => ['label' => 'Point de contrôle (CP)', 'geoms' => ['point'], 'game' => 'CP', 'color' => '#f2f2f2', 'family' => 'point', 'text' => 'CP'],
            'rally' => ['label' => 'Point de ralliement (RP)', 'geoms' => ['point'], 'game' => 'RP', 'color' => '#73d973', 'family' => 'point', 'text' => 'RP'],
            'trp' => ['label' => 'Point de référence de tir (TRP)', 'geoms' => ['point'], 'game' => 'TRP', 'color' => '#f24d47', 'family' => 'point', 'text' => 'TRP'],
            'objpt' => ['label' => 'Objectif (point)', 'geoms' => ['point'], 'game' => 'OBJPT', 'color' => '#f24d47', 'family' => 'point', 'text' => 'OBJ'],
            'cleared' => ['label' => 'Bâtiment fouillé', 'geoms' => ['point'], 'game' => 'CLEARED', 'color' => '#73d973', 'family' => 'point'],
            'uncleared' => ['label' => 'Bâtiment non fouillé', 'geoms' => ['point'], 'game' => 'UNCLEARED', 'color' => '#ff9433', 'family' => 'point'],
            'sbf' => ['label' => 'Appui par le feu (SBF)', 'geoms' => ['line'], 'game' => 'SBF', 'color' => '#5999ff', 'family' => 'line', 'arrow' => 'end'],
            'abf' => ['label' => 'Attaque par le feu (ABF)', 'geoms' => ['line'], 'game' => 'ABF', 'color' => '#f24d47', 'family' => 'line', 'arrow' => 'end'],
            'route' => ['label' => 'Itinéraire', 'geoms' => ['line'], 'game' => 'ROUTE', 'color' => '#fae04d', 'family' => 'line', 'arrow' => 'end'],
            'axis' => ['label' => 'Axe de progression', 'geoms' => ['line'], 'game' => 'AXIS', 'color' => '#5999ff', 'family' => 'line', 'arrow' => 'end'],
            'attack' => ['label' => 'Direction d’attaque', 'geoms' => ['line'], 'game' => 'ATK', 'color' => '#f24d47', 'family' => 'line', 'arrow' => 'end'],
            'support' => ['label' => 'Direction d’appui', 'geoms' => ['line'], 'game' => 'SPT', 'color' => '#5999ff', 'family' => 'line', 'arrow' => 'end', 'dash' => 'dash'],
            'move' => ['label' => 'Mouvement', 'geoms' => ['line'], 'game' => 'MOVE', 'color' => '#f2f2f2', 'family' => 'line', 'arrow' => 'end', 'dash' => 'dash'],
            'phase_line' => ['label' => 'Ligne de coordination (PL)', 'geoms' => ['line'], 'game' => 'PL', 'color' => '#f2f2f2', 'family' => 'line', 'text' => 'PL'],
            'ld' => ['label' => 'Ligne de débouché (LD)', 'geoms' => ['line'], 'game' => 'LD', 'color' => '#73d973', 'family' => 'line', 'text' => 'LD'],
            'loa' => ['label' => 'Limite d’avance (LOA)', 'geoms' => ['line'], 'game' => 'LOA', 'color' => '#ff9433', 'family' => 'line', 'text' => 'LOA'],
            'boundary' => ['label' => 'Limite (BDY)', 'geoms' => ['line'], 'game' => 'BDY', 'color' => '#f2f2f2', 'family' => 'line', 'dash' => 'dash'],
            'objective' => ['label' => 'Objectif (zone)', 'geoms' => ['area', 'circle'], 'game' => 'OBJ', 'color' => '#f24d47', 'family' => 'area', 'fill' => 0.12, 'text' => 'OBJ'],
            'ao' => ['label' => 'Zone d’opération (AO)', 'geoms' => ['area', 'rect'], 'game' => 'AO', 'color' => '#f2f2f2', 'family' => 'area', 'fill' => 0.0, 'text' => 'AO'],
            'nai' => ['label' => 'Zone d’intérêt (NAI)', 'geoms' => ['area', 'circle'], 'game' => 'NAI', 'color' => '#fae04d', 'family' => 'area', 'fill' => 0.08, 'text' => 'NAI'],
            'ea' => ['label' => 'Zone d’engagement (EA)', 'geoms' => ['area', 'circle'], 'game' => 'EA', 'color' => '#f24d47', 'family' => 'area', 'fill' => 0.1, 'text' => 'EA'],
            'aa' => ['label' => 'Zone de regroupement (AA)', 'geoms' => ['area', 'circle'], 'game' => 'AA', 'color' => '#5999ff', 'family' => 'area', 'fill' => 0.1, 'text' => 'AA'],
            'enemy' => ['label' => 'Zone ennemie', 'geoms' => ['area', 'circle'], 'game' => 'ENY', 'color' => '#f24d47', 'family' => 'area', 'fill' => 0.18],
            'lz' => ['label' => 'Zone de poser (HLZ)', 'geoms' => ['circle', 'area'], 'game' => 'HLZ', 'color' => '#73d973', 'family' => 'area', 'fill' => 0.1, 'text' => 'HLZ'],
            'infil' => ['label' => 'Point d’infiltration', 'geoms' => ['area', 'circle'], 'game' => 'INFIL', 'color' => '#73d973', 'family' => 'area', 'fill' => 0.1, 'text' => 'INFIL'],
            'exfil' => ['label' => 'Point d’exfiltration', 'geoms' => ['area', 'circle'], 'game' => 'EXFIL', 'color' => '#4dccf2', 'family' => 'area', 'fill' => 0.1, 'text' => 'EXFIL'],
            'nfa' => ['label' => 'Zone d’interdiction de tir (NFA)', 'geoms' => ['circle', 'area'], 'game' => 'NFA', 'color' => '#f24d47', 'family' => 'area', 'fill' => 0.15, 'text' => 'NFA'],
            'ffa' => ['label' => 'Zone de tir libre (FFA)', 'geoms' => ['circle', 'area'], 'game' => 'ZONE', 'color' => '#73d973', 'family' => 'area', 'fill' => 0.08, 'text' => 'FFA'],
            'rfa' => ['label' => 'Zone de tir restreint (RFA)', 'geoms' => ['circle', 'area'], 'game' => 'ZONE', 'color' => '#ff9433', 'family' => 'area', 'fill' => 0.08, 'text' => 'RFA'],
            'minefield' => ['label' => 'Champ de mines', 'geoms' => ['area', 'rect'], 'game' => 'ZONE', 'color' => '#ff9433', 'family' => 'area', 'fill' => 0.12, 'dash' => 'dash', 'text' => 'MINES'],
            'line' => ['label' => 'Trait', 'geoms' => ['line'], 'game' => 'FREE', 'color' => '#f2f2f2', 'family' => 'free'],
            'arrow' => ['label' => 'Flèche', 'geoms' => ['line'], 'game' => 'FREE', 'color' => '#f2f2f2', 'family' => 'free', 'arrow' => 'end'],
            'polygon' => ['label' => 'Polygone', 'geoms' => ['area'], 'game' => 'ZONE', 'color' => '#f2f2f2', 'family' => 'free', 'fill' => 0.1],
            'rect' => ['label' => 'Rectangle', 'geoms' => ['rect'], 'game' => 'ZONE', 'color' => '#f2f2f2', 'family' => 'free', 'fill' => 0.1],
            'ellipse' => ['label' => 'Ellipse', 'geoms' => ['ellipse'], 'game' => 'ZONE', 'color' => '#f2f2f2', 'family' => 'free', 'fill' => 0.1],
            'circle' => ['label' => 'Cercle', 'geoms' => ['circle'], 'game' => 'ZONE', 'color' => '#f2f2f2', 'family' => 'free', 'fill' => 0.1],
            // Éditeur v2 : points de soutien, feux, lignes de coordination, zones et texte avec renvoi.
            'medevac' => ['label' => 'Point MEDEVAC', 'geoms' => ['point'], 'game' => 'NOTE', 'color' => '#f24d47', 'family' => 'point', 'text' => 'MEDEVAC'],
            'ccp' => ['label' => 'Nid de blessés (CCP)', 'geoms' => ['point'], 'game' => 'NOTE', 'color' => '#f24d47', 'family' => 'point', 'text' => 'CCP'],
            'supply' => ['label' => 'Point de ravitaillement', 'geoms' => ['point'], 'game' => 'NOTE', 'color' => '#fae04d', 'family' => 'point', 'text' => 'RAV'],
            'op' => ['label' => 'Poste d’observation (OP)', 'geoms' => ['point'], 'game' => 'NOTE', 'color' => '#5999ff', 'family' => 'point', 'text' => 'OP'],
            'target' => ['label' => 'Cible', 'geoms' => ['point'], 'game' => 'TRP', 'color' => '#f24d47', 'family' => 'point', 'text' => 'TGT'],
            'decon' => ['label' => 'Point de décontamination', 'geoms' => ['point'], 'game' => 'NOTE', 'color' => '#fae04d', 'family' => 'point', 'text' => 'DECON'],
            'fan' => ['label' => 'Éventail de portée', 'geoms' => ['point'], 'game' => 'NOTE', 'color' => '#ff9433', 'family' => 'fire'],
            'fscl' => ['label' => 'Ligne de coordination des feux (FSCL)', 'geoms' => ['line'], 'game' => 'PL', 'color' => '#f2f2f2', 'family' => 'line', 'text' => 'FSCL'],
            'cfl' => ['label' => 'Ligne de coordination des tirs (CFL)', 'geoms' => ['line'], 'game' => 'PL', 'color' => '#f2f2f2', 'family' => 'line', 'dash' => 'dash', 'text' => 'CFL'],
            'rfl' => ['label' => 'Ligne de tir restreint (RFL)', 'geoms' => ['line'], 'game' => 'PL', 'color' => '#f24d47', 'family' => 'line', 'text' => 'RFL'],
            'flot' => ['label' => 'Ligne avant des troupes amies (FLOT)', 'geoms' => ['line'], 'game' => 'PL', 'color' => '#5999ff', 'family' => 'line', 'text' => 'FLOT'],
            'obstacle' => ['label' => 'Ligne d’obstacles', 'geoms' => ['line'], 'game' => 'FREE', 'color' => '#73d973', 'family' => 'line'],
            'textbox' => ['label' => 'Texte avec renvoi', 'geoms' => ['line'], 'game' => 'NOTE', 'color' => '#f2f2f2', 'family' => 'text'],
            'bp' => ['label' => 'Position de combat (BP)', 'geoms' => ['area', 'circle'], 'game' => 'ZONE', 'color' => '#5999ff', 'family' => 'area', 'fill' => 0.08, 'text' => 'BP'],
            'tai' => ['label' => 'Zone d’intérêt ciblée (TAI)', 'geoms' => ['area', 'circle'], 'game' => 'NAI', 'color' => '#ff9433', 'family' => 'area', 'fill' => 0.08, 'text' => 'TAI'],
            'pz' => ['label' => 'Zone d’enlèvement (PZ)', 'geoms' => ['circle', 'area'], 'game' => 'HLZ', 'color' => '#73d973', 'family' => 'area', 'fill' => 0.1, 'text' => 'PZ'],
            'dz' => ['label' => 'Zone de largage (DZ)', 'geoms' => ['circle', 'area'], 'game' => 'ZONE', 'color' => '#4dccf2', 'family' => 'area', 'fill' => 0.1, 'text' => 'DZ'],
            'cbrn' => ['label' => 'Zone contaminée (NRBC)', 'geoms' => ['area', 'circle'], 'game' => 'ZONE', 'color' => '#fae04d', 'family' => 'area', 'fill' => 0.15, 'dash' => 'dash', 'text' => 'NRBC'],
            'obstacle_belt' => ['label' => 'Ceinture d’obstacles', 'geoms' => ['area', 'rect'], 'game' => 'ZONE', 'color' => '#73d973', 'family' => 'area', 'fill' => 0.08, 'dash' => 'dash', 'text' => 'OBST'],
            'ring' => ['label' => 'Cercle de portée', 'geoms' => ['circle'], 'game' => 'ZONE', 'color' => '#ff9433', 'family' => 'fire', 'fill' => 0.0],
        ];
    }

    /** Type du jeu => type web et géométrie (import « depuis le jeu »). */
    public const FROM_GAME = [
        'ENY' => ['enemy', 'area'], 'INFIL' => ['infil', 'area'], 'EXFIL' => ['exfil', 'area'], 'OBJ' => ['objective', 'area'],
        'AO' => ['ao', 'area'], 'NAI' => ['nai', 'area'], 'EA' => ['ea', 'area'], 'AA' => ['aa', 'area'], 'HLZ' => ['lz', 'area'],
        'NFA' => ['nfa', 'area'], 'ZONE' => ['polygon', 'area'],
        'PL' => ['phase_line', 'line'], 'LD' => ['ld', 'line'], 'LOA' => ['loa', 'line'], 'BDY' => ['boundary', 'line'],
        'ROUTE' => ['route', 'line'], 'MOVE' => ['move', 'line'], 'AXIS' => ['axis', 'line'], 'ATK' => ['attack', 'line'],
        'SPT' => ['support', 'line'], 'FREE' => ['line', 'line'],
        'CLEARED' => ['cleared', 'point'], 'UNCLEARED' => ['uncleared', 'point'], 'UNITF' => ['unit', 'point'], 'UNITH' => ['unit', 'point'],
        'NOTE' => ['text', 'point'], 'CP' => ['checkpoint', 'point'], 'RP' => ['rally', 'point'], 'TRP' => ['trp', 'point'],
        'OBJPT' => ['objpt', 'point'], 'SBF' => ['sbf', 'line'], 'ABF' => ['abf', 'line'],
    ];

    /** Catalogue servi à l'éditeur (JSON). @return array<string, mixed> */
    public static function forClient(): array
    {
        $types = [];
        foreach (self::UNIT_TYPES as $k => [$label, $fn]) {
            $types[] = ['id' => $k, 'label' => $label, 'fn' => $fn];
        }

        return [
            'kinds' => self::kinds(),
            'gameColors' => self::GAME_COLORS,
            'affiliations' => self::AFFILIATIONS,
            'echelons' => self::ECHELONS,
            'unitTypes' => $types,
            'icons' => self::ICONS,
            'limits' => ['elements' => PlanValidator::MAX_ELEMENTS, 'points' => PlanValidator::MAX_POINTS, 'phases' => PlanValidator::MAX_PHASES],
        ];
    }
}
