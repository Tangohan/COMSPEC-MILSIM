<?php
declare(strict_types=1);

/**
 * Pictogrammes de la page Configuration ATAK (SVG en trait, couleur courante).
 *
 * @return callable(string, int=):string
 */
return static function (string $name, int $size = 20): string {
    $p = [
        'start' => '<path d="M5 19 19 5"/><path d="M9 5h10v10"/>',
        'key' => '<circle cx="8" cy="15" r="4"/><path d="m11 12 9-9M16 7l3 3M14 9l2 2"/>',
        'game' => '<rect x="2.5" y="7" width="19" height="11"/><path d="M7 10.5v4M5 12.5h4"/><path d="M15.5 11.5h.01M18 13.5h.01"/>',
        'mission' => '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3.5"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/>',
        'modules' => '<rect x="3" y="3" width="7.5" height="7.5"/><rect x="13.5" y="3" width="7.5" height="7.5"/><rect x="3" y="13.5" width="7.5" height="7.5"/><path d="M17.25 13.5v7.5M13.5 17.25H21"/>',
        'map' => '<path d="M9 4 3 6.5v13.5L9 17.5l6 2.5 6-2.5V4l-6 2.5L9 4Z"/><path d="M9 4v13.5M15 6.5V20"/>',
        'photo' => '<rect x="3" y="6" width="18" height="13"/><path d="M8.5 6 10 3.5h4L15.5 6"/><circle cx="12" cy="12.5" r="3.5"/>',
        'server' => '<rect x="3" y="4" width="18" height="7"/><rect x="3" y="13" width="18" height="7"/><path d="M7 7.5h.01M7 16.5h.01M11 7.5h6M11 16.5h6"/>',
        'link' => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
        'data' => '<ellipse cx="12" cy="6" rx="8" ry="3"/><path d="M4 6v12c0 1.7 3.6 3 8 3s8-1.3 8-3V6"/><path d="M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/>',
        'shield' => '<path d="M12 3 4.5 6v5.5c0 4.5 3.2 8.3 7.5 9.5 4.3-1.2 7.5-5 7.5-9.5V6L12 3Z"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14"/><path d="m3 6 9 7 9-7"/>',
        'steam' => '<circle cx="12" cy="12" r="9"/><circle cx="15.5" cy="9" r="2.5"/><circle cx="9" cy="15" r="2"/><path d="m10.7 14 3.3-3.4"/>',
        'code' => '<rect x="4" y="9" width="16" height="11"/><path d="M8 9V6.5a4 4 0 0 1 8 0V9"/><path d="M12 13.5v3"/>',
        'refresh' => '<path d="M20 11a8 8 0 0 0-14.5-4.5L4 8"/><path d="M4 4v4h4"/><path d="M4 13a8 8 0 0 0 14.5 4.5L20 16"/><path d="M20 20v-4h-4"/>',
        'chat' => '<path d="M4 5h16v11H9l-5 4V5Z"/><path d="M8 9.5h8M8 12.5h5"/>',
        'fire' => '<circle cx="12" cy="12" r="8"/><path d="M12 4v4M12 16v4M4 12h4M16 12h4"/><circle cx="12" cy="12" r="1.5"/>',
        'intel' => '<path d="M2.5 12S6 5 12 5s9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7Z"/><circle cx="12" cy="12" r="3"/>',
        'marker' => '<path d="M12 21s-6.5-6-6.5-11a6.5 6.5 0 0 1 13 0c0 5-6.5 11-6.5 11Z"/><circle cx="12" cy="10" r="2.5"/>',
        'clock' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
        'wrench' => '<path d="M14.5 6.5a4 4 0 0 0 5 5L21 13l-8 8-3-3 8-8-1.5-1.5a4 4 0 0 0-5-5l3 3-2 2-3-3Z"/>',
        'warn' => '<path d="M12 3 2 20h20L12 3Z"/><path d="M12 10v4.5M12 17v.5"/>',
        'tip' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 11v5.5M12 7.5v.5"/>',
        'check' => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
        'eye' => '<path d="M2.5 12S6 5 12 5s9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7Z"/><circle cx="12" cy="12" r="3"/>',
        'weather' => '<path d="M7 18h10a4 4 0 0 0 .5-8 5.5 5.5 0 0 0-10.6 1.5A3.3 3.3 0 0 0 7 18Z"/>',
        'drone' => '<circle cx="6" cy="6" r="2.5"/><circle cx="18" cy="6" r="2.5"/><circle cx="6" cy="18" r="2.5"/><circle cx="18" cy="18" r="2.5"/><path d="M8 8l2.5 2.5M16 8l-2.5 2.5M8 16l2.5-2.5M16 16l-2.5-2.5"/><rect x="10" y="10" width="4" height="4"/>',
        'camera' => '<rect x="3" y="7" width="13" height="10"/><path d="m16 11 5-3v8l-5-3"/>',
        'route' => '<circle cx="6" cy="18" r="2.5"/><circle cx="18" cy="6" r="2.5"/><path d="M8.5 18H15a3 3 0 0 0 0-6H9a3 3 0 0 1 0-6h6.5"/>',
        'radio' => '<path d="M12 12v9"/><circle cx="12" cy="10" r="2"/><path d="M7.5 5.5a6.5 6.5 0 0 0 0 9M16.5 5.5a6.5 6.5 0 0 1 0 9M4.5 3a10 10 0 0 0 0 14M19.5 3a10 10 0 0 1 0 14"/>',
        'jump' => '<path d="M4 10a8 8 0 0 1 16 0"/><path d="M4 10l8 6 8-6M12 16v5"/>',
        'download' => '<path d="M12 4v11M7 10l5 5 5-5"/><path d="M4 20h16"/>',
        'trash' => '<path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/>',
        'external' => '<path d="M14 4h6v6M20 4l-9 9"/><path d="M18 14v6H4V6h6"/>',
    ];

    return '<svg class="bo-ac-ico" viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="square" stroke-linejoin="miter" aria-hidden="true">'
        . ($p[$name] ?? $p['tip']) . '</svg>';
};
