<?php

declare(strict_types=1);

namespace App\Services\GameEquipment;

/**
 * Familles d’objets Arma remontées par le mod (champ `kind` de `item_catalog`).
 */
final class GameItemKinds
{
    /** @var array<string, array{label: string, plural: string}> */
    public const KINDS = [
        'primary' => ['label' => 'Arme principale', 'plural' => 'Armes principales'],
        'secondary' => ['label' => 'Lanceur', 'plural' => 'Lanceurs'],
        'handgun' => ['label' => 'Arme de poing', 'plural' => 'Armes de poing'],
        'optic' => ['label' => 'Optique', 'plural' => 'Optiques'],
        'muzzle' => ['label' => 'Bouche', 'plural' => 'Bouches et silencieux'],
        'pointer' => ['label' => 'Pointeur', 'plural' => 'Pointeurs et lampes'],
        'bipod' => ['label' => 'Bipied', 'plural' => 'Bipieds'],
        'magazine' => ['label' => 'Munition', 'plural' => 'Munitions et grenades'],
        'uniform' => ['label' => 'Tenue', 'plural' => 'Tenues'],
        'vest' => ['label' => 'Gilet', 'plural' => 'Gilets'],
        'backpack' => ['label' => 'Sac', 'plural' => 'Sacs'],
        'headgear' => ['label' => 'Casque', 'plural' => 'Casques et coiffes'],
        'goggles' => ['label' => 'Lunettes', 'plural' => 'Lunettes et masques'],
        'nvg' => ['label' => 'Vision nocturne', 'plural' => 'Vision nocturne'],
        'binocular' => ['label' => 'Jumelles', 'plural' => 'Jumelles et désignateurs'],
        'radio' => ['label' => 'Radio', 'plural' => 'Radios'],
        'medical' => ['label' => 'Médical', 'plural' => 'Matériel médical'],
        'item' => ['label' => 'Objet', 'plural' => 'Autres objets'],
    ];

    private const ALIASES = [
        'launcher' => 'secondary',
        'secondary_weapon' => 'secondary',
        'primary_weapon' => 'primary',
        'pistol' => 'handgun',
        'handgun_weapon' => 'handgun',
        'scope' => 'optic',
        'sight' => 'optic',
        'suppressor' => 'muzzle',
        'silencer' => 'muzzle',
        'laser' => 'pointer',
        'flashlight' => 'pointer',
        'underbarrel' => 'bipod',
        'mag' => 'magazine',
        'magazines' => 'magazine',
        'ammo' => 'magazine',
        'grenade' => 'magazine',
        'helmet' => 'headgear',
        'hat' => 'headgear',
        'facewear' => 'goggles',
        'glasses' => 'goggles',
        'nvgs' => 'nvg',
        'nightvision' => 'nvg',
        'binoculars' => 'binocular',
        'radios' => 'radio',
        'medical_items' => 'medical',
        'med' => 'medical',
        'items' => 'item',
        'assigned' => 'item',
        'assigned_item' => 'item',
    ];

    /** Précision d’une famille : un objet vu à la fois « item » et « radio » reste « radio ». */
    public static function specificity(string $kind): int
    {
        return $kind === 'item' ? 0 : 1;
    }

    public static function normalize(mixed $kind): string
    {
        if (!is_scalar($kind)) {
            return 'item';
        }
        $k = strtolower(trim((string) $kind));
        $k = self::ALIASES[$k] ?? $k;

        return isset(self::KINDS[$k]) ? $k : 'item';
    }

    public static function label(string $kind): string
    {
        return self::KINDS[$kind]['label'] ?? self::KINDS['item']['label'];
    }

    public static function plural(string $kind): string
    {
        return self::KINDS[$kind]['plural'] ?? self::KINDS['item']['plural'];
    }
}
