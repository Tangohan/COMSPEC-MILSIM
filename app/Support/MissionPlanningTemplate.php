<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Gabarit d’organisation de combat (task organization) pour un nouveau plan.
 *
 * @phpstan-type ElementTpl array{code:string,label:string,kind:string,auth:int,slots:list<array{callsign:string,function:string,role:string}>}
 */
final class MissionPlanningTemplate
{
    /**
     * @return list<ElementTpl>
     */
    public static function defaultTaskForce(): array
    {
        return [
            [
                'code' => 'HQ',
                'label' => 'HQ',
                'kind' => 'hq',
                'auth' => 3,
                'slots' => [
                    ['callsign' => 'DAGGER 6', 'function' => 'Commandant de force', 'role' => 'cdr'],
                    ['callsign' => 'DAGGER 5', 'function' => 'Adjoint / XO', 'role' => 'xo'],
                    ['callsign' => 'DAGGER RTO', 'function' => 'Radio', 'role' => 'rto'],
                ],
            ],
            [
                'code' => 'ALPHA',
                'label' => 'ALPHA',
                'kind' => 'maneuver',
                'auth' => 8,
                'slots' => [
                    ['callsign' => 'ALPHA 1-1', 'function' => 'Chef d’équipe', 'role' => 'tl'],
                    ['callsign' => 'ALPHA 1-2', 'function' => 'Fusilier automatique', 'role' => 'ar'],
                    ['callsign' => 'ALPHA 1-3', 'function' => 'Grenadier', 'role' => 'grn'],
                    ['callsign' => 'ALPHA 1-4', 'function' => 'Infirmier', 'role' => 'medic'],
                    ['callsign' => 'ALPHA 2-1', 'function' => 'Chef d’équipe', 'role' => 'tl'],
                    ['callsign' => 'ALPHA 2-2', 'function' => 'Fusilier automatique', 'role' => 'ar'],
                    ['callsign' => 'ALPHA 2-3', 'function' => 'Grenadier', 'role' => 'grn'],
                    ['callsign' => 'ALPHA 2-4', 'function' => 'Fusilier', 'role' => 'rifle'],
                ],
            ],
            [
                'code' => 'BRAVO',
                'label' => 'BRAVO',
                'kind' => 'maneuver',
                'auth' => 8,
                'slots' => [
                    ['callsign' => 'BRAVO 1-1', 'function' => 'Chef d’équipe', 'role' => 'tl'],
                    ['callsign' => 'BRAVO 1-2', 'function' => 'Fusilier automatique', 'role' => 'ar'],
                    ['callsign' => 'BRAVO 1-3', 'function' => 'Grenadier', 'role' => 'grn'],
                    ['callsign' => 'BRAVO 1-4', 'function' => 'Infirmier', 'role' => 'medic'],
                    ['callsign' => 'BRAVO 2-1', 'function' => 'Chef d’équipe', 'role' => 'tl'],
                    ['callsign' => 'BRAVO 2-2', 'function' => 'Fusilier automatique', 'role' => 'ar'],
                    ['callsign' => 'BRAVO 2-3', 'function' => 'Grenadier', 'role' => 'grn'],
                    ['callsign' => 'BRAVO 2-4', 'function' => 'Fusilier', 'role' => 'rifle'],
                ],
            ],
            [
                'code' => 'AIR',
                'label' => 'AIR',
                'kind' => 'air',
                'auth' => 4,
                'slots' => [
                    ['callsign' => 'VIPER 1-1', 'function' => 'Appui aérien', 'role' => 'cas'],
                    ['callsign' => 'RAVEN 2-1', 'function' => 'Reconnaissance aérienne', 'role' => 'recce'],
                ],
            ],
            [
                'code' => 'SUPPORT',
                'label' => 'SOUTIEN',
                'kind' => 'support',
                'auth' => 4,
                'slots' => [
                    ['callsign' => 'MEDEVAC 1', 'function' => 'Évacuation sanitaire', 'role' => 'medevac'],
                    ['callsign' => 'LOGPAC 1', 'function' => 'Ravitaillement', 'role' => 'log'],
                    ['callsign' => 'JTAC', 'function' => 'Guidage aérien', 'role' => 'jtac'],
                    ['callsign' => 'EOD', 'function' => 'Neutralisation', 'role' => 'eod'],
                ],
            ],
        ];
    }

    /**
     * Mesures de contrôle à poser sur la carte (sans coordonnées tant qu’elles ne sont pas placées).
     *
     * @return list<array{code:string,label:string,kind:string,geom:string,element:string,order:int}>
     */
    public static function defaultControlMeasures(): array
    {
        return [
            ['code' => 'LD', 'label' => 'Ligne de départ', 'kind' => 'ld', 'geom' => 'line', 'element' => '', 'order' => 10],
            ['code' => 'PL RED', 'label' => 'Ligne de phase rouge', 'kind' => 'pl', 'geom' => 'line', 'element' => '', 'order' => 20],
            ['code' => 'ORP FOX', 'label' => 'Point de rassemblement FOX', 'kind' => 'orp', 'geom' => 'point', 'element' => '', 'order' => 30],
            ['code' => 'OBJ EAGLE', 'label' => 'Objectif EAGLE', 'kind' => 'obj', 'geom' => 'point', 'element' => '', 'order' => 40],
            ['code' => 'LZ HAWK', 'label' => 'Zone de poser HAWK', 'kind' => 'lz', 'geom' => 'point', 'element' => 'AIR', 'order' => 50],
            ['code' => 'AXIS RED', 'label' => 'Axe rouge', 'kind' => 'axis', 'geom' => 'line', 'element' => 'ALPHA', 'order' => 60],
            ['code' => 'AXIS BLUE', 'label' => 'Axe bleu', 'kind' => 'axis', 'geom' => 'line', 'element' => 'BRAVO', 'order' => 70],
            ['code' => 'CP 1', 'label' => 'Point de contrôle 1', 'kind' => 'cp', 'geom' => 'point', 'element' => '', 'order' => 80],
            ['code' => 'CP 2', 'label' => 'Point de contrôle 2', 'kind' => 'cp', 'geom' => 'point', 'element' => '', 'order' => 90],
        ];
    }

    /**
     * Jalons prévus, décalés par rapport à l’heure H (négatif = avant H).
     *
     * @return list<array{code:string,label:string,offset:int}>
     */
    public static function defaultTimeline(): array
    {
        return [
            ['code' => 'BRIEF', 'label' => 'Briefing terminé', 'offset' => -900],
            ['code' => 'SP', 'label' => 'Heure de départ', 'offset' => 0],
            ['code' => 'LD', 'label' => 'Ligne de départ franchie', 'offset' => 900],
            ['code' => 'ORP', 'label' => 'Point de rassemblement', 'offset' => 3300],
            ['code' => 'OBJ', 'label' => 'Objectif — pas plus tard que', 'offset' => 4500],
        ];
    }
}
