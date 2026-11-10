<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\GameEquipment\Configurator\ConfiguratorKinds;
use App\Services\GameEquipment\Configurator\PrekitDocument;

/**
 * Espace Matériel de la page Équipement : catalogue des objets de la communauté (jeu de base d'Arma 3, configuration
 * importée du jeu, objets vus en jeu par COMSPEC Link), fiches matériel et dotation.
 *
 * Le catalogue est celui du configurateur (EquipmentConfiguratorController::catalogue, mis en cache par le
 * navigateur) ; favoris, tendances et pré-kits viennent de EquipmentConfiguratorController::memberData. Ici : les
 * familles du filtre, les tris et la configuration envoyée au script (public/assets/js/equipment-materiel.js).
 * Pur : ne touche ni la base ni la session.
 */
final class EquipmentMateriel
{
    /** Onglets de l'espace (?tab=…, le premier est celui où l'on arrive). */
    public const TABS = [
        'catalogue' => 'Catalogue des objets',
        'fiches' => 'Fiches matériel',
        'dotation' => 'Dotation',
    ];

    /**
     * Familles du filtre => [libellé, familles du configurateur]. Chaque famille du configurateur
     * (ConfiguratorKinds::LABELS) appartient à une seule famille du filtre.
     */
    public const FAMILIES = [
        'armes' => ['Armes', ['primary', 'handgun', 'secondary']],
        'accessoires' => ['Accessoires', ['optic', 'muzzle', 'pointer', 'bipod']],
        'munitions' => ['Munitions', ['magazine']],
        'tenues' => ['Tenues', ['uniform']],
        'gilets' => ['Gilets', ['vest']],
        'sacs' => ['Sacs', ['backpack']],
        'casques' => ['Casques et lunettes', ['headgear', 'goggles', 'nvg']],
        'medical' => ['Médical', ['medical']],
        'radio' => ['Radios', ['radio']],
        'divers' => ['Divers', ['binocular', 'map', 'gps', 'compass', 'watch', 'item']],
    ];

    /** Tris proposés => libellé. Le premier est le tri par défaut. */
    public const SORTS = [
        'pertinence' => 'Pertinence',
        'nom' => 'Nom (A à Z)',
        'poids-asc' => 'Plus léger d’abord',
        'poids-desc' => 'Plus lourd d’abord',
        'capacite' => 'Plus grande capacité',
        'porte' => 'Plus porté en jeu',
    ];

    /** Cartes ajoutées à chaque fois (défilement ou « Afficher plus ») : le catalogue compte des milliers d'objets. */
    public const BATCH = 48;

    /** Masse d'Arma (dixièmes de livre, convention d'ACE) vers kilogrammes : même conversion que le configurateur. */
    public const MASS_TO_KG = 0.0453592;

    /** Famille du filtre d'une famille du configurateur ('divers' si inconnue). */
    public static function familyOf(string $kind): string
    {
        foreach (self::FAMILIES as $key => [, $kinds]) {
            if (in_array($kind, $kinds, true)) {
                return $key;
            }
        }

        return 'divers';
    }

    /**
     * Configuration du script de l'espace Matériel. Les adresses et droits viennent du contrôleur ; rien de personnel.
     *
     * @param array<string, string> $urls catalogue, social, favorite, prekits, icons, configurator, base
     * @param array{sharePrekits?: bool, catalog?: bool} $can
     * @param array<string, mixed>|null $base état du catalogue de base (VanillaCatalogLoader::status)
     * @return array<string, mixed>
     */
    public static function boot(array $urls, string $csrf, array $can, ?array $base): array
    {
        $families = [];
        foreach (self::FAMILIES as $key => [$label, $kinds]) {
            $families[] = ['id' => $key, 'label' => $label, 'kinds' => $kinds];
        }

        return [
            'urls' => array_map('strval', $urls),
            'csrf' => $csrf,
            'can' => ['sharePrekits' => !empty($can['sharePrekits']), 'catalog' => !empty($can['catalog'])],
            'families' => $families,
            'kinds' => ConfiguratorKinds::LABELS,
            'cats' => ConfiguratorKinds::CATEGORIES,
            'sorts' => self::SORTS,
            'batch' => self::BATCH,
            'kg' => self::MASS_TO_KG,
            'prekitRefused' => PrekitDocument::REFUSED_KINDS,
            'prekitMax' => ['lines' => PrekitDocument::MAX_LINES, 'count' => PrekitDocument::MAX_COUNT, 'name' => PrekitDocument::NAME_MAX],
            'slots' => array_flip(ConfiguratorKinds::SLOT_CODES),
            'base' => [
                'available' => !empty($base['available']),
                'needed' => !empty($base['available']) && !empty($base['needed']),
            ],
        ];
    }
}
