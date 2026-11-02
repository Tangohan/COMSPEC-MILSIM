<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Bibliothèque d’icônes des fonctions (emplois) : SVG en ligne au trait, même style que les icônes du site
 * (views/partials/nav_icons.php : 24×24, stroke="currentColor"). Aucune image externe, aucune saisie libre :
 * la base ne garde que la clé, le dessin vient toujours d’ici.
 */
final class JobRoleIconLibrary
{
    /** Clé => [libellé, groupe, tracé SVG (contenu du <svg>)]. */
    private const ICONS = [
        'star' => ['Commandement', 'Commandement', '<path d="M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z"/>'],
        'chevrons' => ['Galons', 'Commandement', '<path d="M5 10l7-5 7 5M5 15l7-5 7 5M5 20l7-5 7 5"/>'],
        'flag' => ['Fanion', 'Commandement', '<path d="M5 21V4M5 4h11l-2 4 2 4H5"/>'],
        'clipboard' => ['État-major', 'Commandement', '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4V3h6v1M9 10h6M9 14h6M9 18h4"/>'],
        'map' => ['Carte et plans', 'Commandement', '<path d="M9 4L3 6v14l6-2 6 2 6-2V4l-6 2-6-2zM9 4v14M15 6v14"/>'],
        'compass' => ['Navigation', 'Commandement', '<circle cx="12" cy="12" r="9"/><path d="M15.5 8.5l-2 5-5 2 2-5z"/>'],
        'crosshair' => ['Tireur', 'Combat', '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="2"/><path d="M12 2v4M12 18v4M2 12h4M18 12h4"/>'],
        'target' => ['Cible', 'Combat', '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>'],
        'shield' => ['Protection', 'Combat', '<path d="M12 3l8 4v6c0 5-3.5 9-8 10-4.5-1-8-5-8-10V7l8-4z"/>'],
        'sword' => ['Assaut', 'Combat', '<path d="M14 4h6v6L9 21l-3-3L17 7M4 20l3-3M5 14l5 5"/>'],
        'bomb' => ['Explosifs', 'Combat', '<circle cx="11" cy="14" r="7"/><path d="M15 8l2-2M17 6l2 1M17 6l1-2"/>'],
        'flame' => ['Feux et appuis', 'Combat', '<path d="M12 22c4 0 7-3 7-7 0-5-5-7-5-12-3 2-4 5-4 7-1-1-2-2-2-4-2 2-3 5-3 9 0 4 3 7 7 7z"/>'],
        'binoculars' => ['Reconnaissance', 'Renseignement', '<circle cx="7" cy="16" r="4"/><circle cx="17" cy="16" r="4"/><path d="M7 12V6h3v6M14 12V6h3v6M11 16h2"/>'],
        'eye' => ['Observation', 'Renseignement', '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>'],
        'search' => ['Analyse', 'Renseignement', '<circle cx="11" cy="11" r="7"/><path d="M21 21l-5-5"/>'],
        'camera' => ['Imagerie', 'Renseignement', '<path d="M4 8h3l2-3h6l2 3h3v11H4z"/><circle cx="12" cy="13" r="3.5"/>'],
        'radio' => ['Transmissions', 'Transmissions', '<rect x="6" y="8" width="12" height="13" rx="2"/><path d="M9 8L8 2M10 13h4M10 17h4"/>'],
        'antenna' => ['Antenne', 'Transmissions', '<path d="M12 10v12M8 22h8M5 4a10 10 0 000 12M19 4a10 10 0 010 12M8 7a5 5 0 000 6M16 7a5 5 0 010 6"/><circle cx="12" cy="10" r="1"/>'],
        'satellite' => ['Satellite', 'Transmissions', '<path d="M13 7l4 4-6 6-4-4zM7 13l-4 4 4 4 4-4M17 11l4-4-4-4-4 4M15 21a6 6 0 006-6"/>'],
        'laptop' => ['Informatique', 'Transmissions', '<rect x="4" y="5" width="16" height="11" rx="1"/><path d="M2 19h20"/>'],
        'megaphone' => ['Communication', 'Transmissions', '<path d="M3 10v4h4l9 5V5L7 10H3zM19 9a4 4 0 010 6"/>'],
        'medical' => ['Santé', 'Soutien', '<rect x="3" y="3" width="18" height="18" rx="3"/><path d="M12 7v10M7 12h10"/>'],
        'heart' => ['Secours', 'Soutien', '<path d="M12 21s-8-5-8-11a4.5 4.5 0 018-3 4.5 4.5 0 018 3c0 6-8 11-8 11z"/>'],
        'wrench' => ['Maintenance', 'Soutien', '<path d="M14 6a4 4 0 005 5l-9 9a2 2 0 01-3-3l9-9a4 4 0 01-2-2z"/>'],
        'truck' => ['Transport', 'Soutien', '<path d="M2 6h12v10H2zM14 9h4l4 4v3h-8"/><circle cx="6" cy="18" r="2"/><circle cx="18" cy="18" r="2"/>'],
        'box' => ['Logistique', 'Soutien', '<path d="M3 7l9-4 9 4v10l-9 4-9-4zM3 7l9 4 9-4M12 11v10"/>'],
        'fuel' => ['Carburant', 'Soutien', '<path d="M4 21V4h9v17M3 21h11M4 10h9M13 8l4 3v7a2 2 0 004 0V9l-3-3"/>'],
        'utensils' => ['Restauration', 'Soutien', '<path d="M6 3v8a2 2 0 004 0V3M8 11v10M16 3c-2 1-3 4-3 7h3v11"/>'],
        'plane' => ['Aviation', 'Air et mer', '<path d="M21 15l-8-3V5a1 1 0 00-2 0v7l-8 3v2l8-2v4l-2 2h6l-2-2v-4l8 2z"/>'],
        'helicopter' => ['Hélicoptère', 'Air et mer', '<path d="M3 5h16M11 5v4M5 13a6 4 0 0012 0 6 4 0 00-12 0zM17 13h5M22 11v4M8 19h8M10 17v2M14 17v2"/>'],
        'parachute' => ['Parachutiste', 'Air et mer', '<path d="M3 11a9 7 0 0118 0zM3 11l9 9 9-9M8 11l4 9 4-9"/>'],
        'drone' => ['Drone', 'Air et mer', '<circle cx="5" cy="5" r="2"/><circle cx="19" cy="5" r="2"/><circle cx="5" cy="19" r="2"/><circle cx="19" cy="19" r="2"/><rect x="9" y="9" width="6" height="6" rx="1"/><path d="M7 7l2 2M17 7l-2 2M7 17l2-2M17 17l-2-2"/>'],
        'anchor' => ['Marine', 'Air et mer', '<circle cx="12" cy="5" r="2"/><path d="M12 7v14M8 11h8M4 14a8 7 0 0016 0"/>'],
        'tank' => ['Blindé', 'Air et mer', '<rect x="3" y="13" width="18" height="6" rx="3"/><path d="M6 13v-3h9v3M15 11h7"/>'],
        'users' => ['Équipe', 'Organisation', '<circle cx="9" cy="8" r="3.5"/><path d="M2 21v-1a6 6 0 0114 0v1M16 4.5a3.5 3.5 0 010 7M18 14a5 5 0 014 5v2"/>'],
        'user' => ['Personnel', 'Organisation', '<circle cx="12" cy="8" r="4"/><path d="M4 21v-1a8 8 0 0116 0v1"/>'],
        'book' => ['Instruction', 'Organisation', '<path d="M4 4h6a2 2 0 012 2v15a2 2 0 00-2-2H4zM20 4h-6a2 2 0 00-2 2v15a2 2 0 012-2h6z"/>'],
        'graduation' => ['Formation', 'Organisation', '<path d="M2 9l10-5 10 5-10 5zM6 11v5c0 1.5 3 3 6 3s6-1.5 6-3v-5M22 9v6"/>'],
        'scale' => ['Juridique', 'Organisation', '<path d="M12 3v18M7 21h10M4 7h16M7 7l-3 7h6zM17 7l-3 7h6z"/>'],
        'key' => ['Sécurité', 'Organisation', '<circle cx="8" cy="15" r="4"/><path d="M11 12l9-9M17 6l3 3M15 8l2 2"/>'],
        'lock' => ['Confidentialité', 'Organisation', '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 018 0v3"/>'],
        'music' => ['Musique', 'Organisation', '<path d="M9 18V5l11-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="17" cy="16" r="3"/>'],
        'dumbbell' => ['Sport', 'Organisation', '<path d="M6 7v10M3 9v6M18 7v10M21 9v6M6 12h12"/>'],
        'tent' => ['Bivouac', 'Organisation', '<path d="M12 4L2 20h20zM12 4l-4 16M12 4l4 16"/>'],
        'hammer' => ['Génie', 'Organisation', '<path d="M14 6l4 4M4 20l9-9M11 4l6 6 3-3-6-6z"/>'],
        'biohazard' => ['NRBC', 'Organisation', '<circle cx="12" cy="12" r="2"/><path d="M12 10V3M12 14l-6 4M12 14l6 4M8 6a6 6 0 008 0M5 15a6 6 0 004-7M19 15a6 6 0 01-4-7"/>'],
    ];

    /** @return array<string, array{label: string, group: string}> */
    public static function all(): array
    {
        $out = [];
        foreach (self::ICONS as $key => [$label, $group]) {
            $out[$key] = ['label' => $label, 'group' => $group];
        }

        return $out;
    }

    public static function has(?string $key): bool
    {
        return $key !== null && isset(self::ICONS[$key]);
    }

    public static function label(string $key): string
    {
        return self::ICONS[$key][0] ?? '';
    }

    /** SVG décoratif (aria-hidden) ; chaîne vide pour une clé inconnue. */
    public static function svg(?string $key, string $class = 'job-icon'): string
    {
        if (!self::has($key)) {
            return '';
        }

        return '<svg class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
            . ' stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
            . self::ICONS[$key][2] . '</svg>';
    }
}
