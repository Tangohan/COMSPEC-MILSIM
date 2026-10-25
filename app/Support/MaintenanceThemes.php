<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Apparences de la page de maintenance publique (colonne maintenance_rules.ui_variant).
 * Les clés techniques restent celles déjà enregistrées en base ; seuls les noms affichés changent.
 */
final class MaintenanceThemes
{
    public const DEFAULT = 'military';

    /** @var array<string, array{label: string, hint: string}> */
    public const THEMES = [
        'military' => [
            'label' => 'Photo de terrain',
            'hint' => 'Photo d’équipe assombrie, message en blanc en bas de l’écran. Le style par défaut.',
        ],
        'minimal' => [
            'label' => 'Clair et sobre',
            'hint' => 'Fond clair sans photo, message centré dans une carte. Le plus lisible.',
        ],
        'neon' => [
            'label' => 'Nuit radar',
            'hint' => 'Fond noir, quadrillage et balayage radar vert. Ambiance poste de commandement.',
        ],
        'status' => [
            'label' => 'Tableau de situation',
            'hint' => 'Carte d’état : ce qui est fermé, ce qui est protégé, l’heure de réouverture.',
        ],
    ];

    public static function normalize(mixed $value): string
    {
        $key = strtolower(trim((string) $value));

        return array_key_exists($key, self::THEMES) ? $key : self::DEFAULT;
    }

    public static function label(mixed $value): string
    {
        return self::THEMES[self::normalize($value)]['label'];
    }
}
