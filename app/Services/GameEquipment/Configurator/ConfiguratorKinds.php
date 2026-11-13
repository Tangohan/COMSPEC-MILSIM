<?php

declare(strict_types=1);

namespace App\Services\GameEquipment\Configurator;

use App\Services\GameEquipment\GameItemKinds;

/**
 * Familles et catégories d'objets du configurateur, déduites de la configuration importée du jeu (type d'Arma
 * renvoyé par BIS_fnc_itemType, type d'emplacement des armes), sinon de la famille vue en jeu (GameItemKinds).
 *
 * Famille (kind) = emplacement où l'objet se pose : primary, secondary, handgun, muzzle, optic, pointer, bipod,
 * magazine, uniform, vest, backpack, headgear, goggles, nvg, binocular, radio, map, gps, compass, watch, medical, item.
 * Catégorie (cat) = filtre de la palette (fusil, pistolet, mitrailleuse…), jamais inventée : vide quand le jeu ne
 * l'a pas dit.
 */
final class ConfiguratorKinds
{
    /** Codes d'emplacement des conteneurs d'Arma (allowedSlots[]). */
    public const SLOT_CODES = ['uniform' => 801, 'vest' => 701, 'backpack' => 901];

    /** Familles du configurateur et libellé (singulier). */
    public const LABELS = [
        'primary' => 'Arme principale',
        'secondary' => 'Lanceur',
        'handgun' => 'Arme de poing',
        'muzzle' => 'Bouche',
        'optic' => 'Optique',
        'pointer' => 'Rail latéral',
        'bipod' => 'Bipied ou poignée',
        'magazine' => 'Chargeur ou munition',
        'uniform' => 'Uniforme',
        'vest' => 'Gilet',
        'backpack' => 'Sac à dos',
        'headgear' => 'Casque ou coiffe',
        'goggles' => 'Lunettes',
        'nvg' => 'Vision nocturne',
        'binocular' => 'Jumelles',
        'radio' => 'Radio',
        'map' => 'Carte',
        'gps' => 'GPS ou terminal',
        'compass' => 'Boussole',
        'watch' => 'Montre',
        'medical' => 'Médical',
        'item' => 'Objet',
    ];

    /** Catégories d'armes et de munitions (filtres de la palette). */
    public const CATEGORIES = [
        'fusil' => 'Fusil',
        'pm' => 'Pistolet-mitrailleur',
        'mitrailleuse' => 'Mitrailleuse',
        'precision' => 'Précision',
        'pompe' => 'Fusil à pompe',
        'lance-grenades' => 'Lance-grenades',
        'pistolet' => 'Pistolet',
        'lanceur' => 'Lanceur',
        'balles' => 'Balles',
        'grenades' => 'Grenades et fumigènes',
        'roquettes' => 'Roquettes et missiles',
        'explosifs' => 'Mines et explosifs',
    ];

    private const WEAPON_CATS = [
        'assaultrifle' => 'fusil',
        'rifle' => 'fusil',
        'submachinegun' => 'pm',
        'machinegun' => 'mitrailleuse',
        'sniperrifle' => 'precision',
        'shotgun' => 'pompe',
        'grenadelauncher' => 'lance-grenades',
        'handgun' => 'pistolet',
        'launcher' => 'lanceur',
        'missilelauncher' => 'lanceur',
        'rocketlauncher' => 'lanceur',
        'mortar' => 'lanceur',
    ];

    private const ITEM_KINDS = [
        'accessorymuzzle' => 'muzzle',
        'accessorysights' => 'optic',
        'accessorypointer' => 'pointer',
        'accessorybipod' => 'bipod',
        'binocular' => 'binocular',
        'laserdesignator' => 'binocular',
        'nvgoggles' => 'nvg',
        'radio' => 'radio',
        'map' => 'map',
        'gps' => 'gps',
        'uavterminal' => 'gps',
        'compass' => 'compass',
        'watch' => 'watch',
        'firstaidkit' => 'medical',
        'medikit' => 'medical',
    ];

    private const EQUIPMENT_KINDS = [
        'uniform' => 'uniform',
        'vest' => 'vest',
        'backpack' => 'backpack',
        'headgear' => 'headgear',
        'glasses' => 'goggles',
    ];

    private const MAGAZINE_CATS = [
        'bullet' => 'balles',
        'grenade' => 'grenades',
        'smokeshell' => 'grenades',
        'flare' => 'grenades',
        'shell' => 'grenades',
        'rocket' => 'roquettes',
        'missile' => 'roquettes',
        'mine' => 'explosifs',
        'minebounding' => 'explosifs',
        'minedirectional' => 'explosifs',
    ];

    /**
     * Famille et catégorie d'une ligne de configuration importée.
     *
     * @return array{kind: string, cat: string}
     */
    public static function fromSpec(string $typeMain, string $typeSub, ?int $slotType): array
    {
        $main = strtolower(trim($typeMain));
        $sub = strtolower(trim($typeSub));
        if ($main === 'weapon') {
            $cat = self::WEAPON_CATS[$sub] ?? '';
            $kind = match ($slotType) {
                1 => 'primary',
                2 => 'handgun',
                4 => 'secondary',
                default => match ($cat) {
                    'pistolet' => 'handgun',
                    'lanceur' => 'secondary',
                    '' => 'item',
                    default => 'primary',
                },
            };
            if ($kind === 'handgun' && $cat === '') {
                $cat = 'pistolet';
            }
            if ($kind === 'secondary' && $cat === '') {
                $cat = 'lanceur';
            }

            return ['kind' => $kind, 'cat' => $cat];
        }
        if ($main === 'item') {
            return ['kind' => self::ITEM_KINDS[$sub] ?? 'item', 'cat' => ''];
        }
        if ($main === 'equipment') {
            return ['kind' => self::EQUIPMENT_KINDS[$sub] ?? 'item', 'cat' => ''];
        }
        if ($main === 'magazine' || $main === 'mine') {
            return ['kind' => 'magazine', 'cat' => self::MAGAZINE_CATS[$sub] ?? ''];
        }

        return ['kind' => 'item', 'cat' => ''];
    }

    /**
     * Famille d'un objet seulement vu en jeu (sans configuration importée) : celle remontée par le mod.
     *
     * @return array{kind: string, cat: string}
     */
    public static function fromObserved(mixed $kind): array
    {
        $k = GameItemKinds::normalize($kind);
        $cat = match ($k) {
            'handgun' => 'pistolet',
            'secondary' => 'lanceur',
            default => '',
        };

        return ['kind' => $k, 'cat' => $cat];
    }

    public static function label(string $kind): string
    {
        return self::LABELS[$kind] ?? self::LABELS['item'];
    }

    /** Camps lisibles dans la classe d'un uniforme du jeu de base (U_B_…, U_O_…, U_I_…, U_C_…). */
    public const SIDES = ['blufor' => 'BLUFOR', 'opfor' => 'OPFOR', 'indep' => 'Indépendants', 'civil' => 'Civils'];

    /**
     * Camp d'après la convention de nommage des uniformes d'Arma 3 ; '' si la classe ne la suit pas (mods) : rien
     * n'est deviné au-delà de cette convention.
     */
    public static function sideFromUniform(string $class): string
    {
        if (preg_match('/^U_([BOIC])_/i', $class, $m) !== 1) {
            return '';
        }

        return ['B' => 'blufor', 'O' => 'opfor', 'I' => 'indep', 'C' => 'civil'][strtoupper($m[1])];
    }

    /**
     * Familles acceptées par un emplacement de la tenue. Un objet seulement vu en jeu sous « item » (carte, GPS,
     * montre…) est accepté dans les emplacements d'objets assignés : le jeu ne les distingue pas.
     *
     * @return list<string>
     */
    public static function acceptedKinds(string $slot): array
    {
        return match ($slot) {
            'map', 'gps', 'compass', 'watch' => [$slot, 'item'],
            'radio' => ['radio', 'item'],
            default => [$slot],
        };
    }
}
