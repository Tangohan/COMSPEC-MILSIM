<?php

declare(strict_types=1);

/**
 * Icônes des outils opérateur (tracé façon Lucide, trait 1.8, 24 × 24). Définit $opxIcon(string $name, string $class).
 * Une clé inconnue rend un carré neutre : une carte n'est jamais sans icône.
 */
$opxIcon = static function (string $name, string $class = 'opx-ico'): string {
    static $paths = [
        'map' => '<path d="m9 4-6 2v14l6-2 6 2 6-2V4l-6 2z"/><path d="M9 4v14"/><path d="M15 6v14"/>',
        'radar' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4"/><path d="M12 12 18 6"/>',
        'link' => '<path d="M10 13a5 5 0 0 0 7.07 0l3-3a5 5 0 0 0-7.07-7.07l-1.5 1.5"/><path d="M14 11a5 5 0 0 0-7.07 0l-3 3a5 5 0 0 0 7.07 7.07l1.5-1.5"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'fingerprint' => '<path d="M12 10a2 2 0 0 0-2 2c0 1.5-.2 4-1 6"/><path d="M14 13.1c0 2.4-.3 5-1 7.4"/><path d="M17.3 18c.4-2 .7-4 .7-6a6 6 0 0 0-11.6-2"/><path d="M2 12a10 10 0 0 1 18-6"/><path d="M5 19.5C5.5 18 6 15 6 12"/><path d="M21.8 16c.2-2 .2-4.5-.6-7"/>',
        'phone' => '<rect x="6" y="2" width="12" height="20" rx="2"/><path d="M11 18h2"/>',
        'smartphone' => '<rect x="6" y="2" width="12" height="20" rx="2"/><path d="M10 6h4"/><path d="M11 18h2"/>',
        'camera' => '<path d="M4 8h3l2-3h6l2 3h3a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1z"/><circle cx="12" cy="13" r="3.5"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'id' => '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="11" r="2"/><path d="M6 16a3 3 0 0 1 6 0"/><path d="M15 10h3"/><path d="M15 14h3"/>',
        'mask' => '<path d="M3 7c3-1.5 6-1.5 9 0 3-1.5 6-1.5 9 0v4c0 4-3 7-6 7-1.500 0-2.500-1-3-2-.5 1-1.500 2-3 2-3 0-6-3-6-7z"/><path d="M7 11h3"/><path d="M14 11h3"/>',
        'box' => '<path d="m21 8-9-5-9 5v8l9 5 9-5z"/><path d="m3 8 9 5 9-5"/><path d="M12 13v8"/>',
        'shirt' => '<path d="m8 3-5 3 2 4 2-1v11h10V9l2 1 2-4-5-3a4 4 0 0 1-8 0z"/>',
        'route' => '<circle cx="6" cy="19" r="2"/><circle cx="18" cy="5" r="2"/><path d="M8 19h7a3 3 0 0 0 0-6H9a3 3 0 0 1 0-6h7"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18"/><path d="M8 3v4"/><path d="M16 3v4"/>',
        'book' => '<path d="M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2z"/><path d="M4 21V5"/><path d="M19 19v2H6"/>',
        'cap' => '<path d="m2 9 10-5 10 5-10 5z"/><path d="M6 11.500V16c0 1.500 2.700 3 6 3s6-1.500 6-3v-4.500"/><path d="M22 9v6"/>',
        'pen' => '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="m13.500 6.500 4 4"/>',
        'files' => '<path d="M8 3h8l4 4v10a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/><path d="M16 3v4h4"/><path d="M4 8v11a2 2 0 0 0 2 2h9"/>',
        'star' => '<path d="m12 3 2.700 5.600 6.100.800-4.500 4.300 1.100 6.100L12 16.900 6.600 19.800l1.100-6.100L3.200 9.400l6.100-.800z"/>',
        'arrow' => '<path d="M5 12h14"/><path d="m13 6 6 6-6 6"/>',
        'download' => '<path d="M12 4v11"/><path d="m7 11 5 5 5-5"/><path d="M5 20h14"/>',
        'check' => '<path d="m5 12.500 4.500 4.500L19 7.500"/>',
        'alert' => '<path d="M12 4 2.500 20h19z"/><path d="M12 10v4"/><path d="M12 17.500v.010"/>',
        'refresh' => '<path d="M20 11a8 8 0 0 0-14.500-4L4 8.500"/><path d="M4 4v4.500h4.500"/><path d="M4 13a8 8 0 0 0 14.500 4l1.500-1.500"/><path d="M20 20v-4.500h-4.500"/>',
        'plus' => '<path d="M12 5v14"/><path d="M5 12h14"/>',
        'lock' => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
        'close' => '<path d="M6 6l12 12"/><path d="M18 6 6 18"/>',
        'edit' => '<path d="M4 20h4L19 9l-4-4L4 16z"/>',
    ];

    return '<svg class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
        . ($paths[$name] ?? '<rect x="5" y="5" width="14" height="14" rx="2"/>') . '</svg>';
};
