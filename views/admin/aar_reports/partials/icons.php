<?php
declare(strict_types=1);

/**
 * Pictogrammes du rédacteur de compte rendu (SVG en trait, couleur courante).
 *
 * @return callable(string):string
 */
return static function (string $name): string {
    $paths = [
        'mission' => '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3.5"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/>',
        'format' => '<path d="M12 3 3 7.5l9 4.5 9-4.5L12 3Z"/><path d="m3 12 9 4.5 9-4.5"/><path d="m3 16.5 9 4.5 9-4.5"/>',
        'summary' => '<path d="M6 3h9l4 4v14H6z"/><path d="M15 3v4h4"/><path d="M9 11h7M9 14.5h7M9 18h4"/>',
        'points' => '<path d="M4 7h7M7.5 3.5v7"/><path d="M13 17h7"/><path d="M4 20 20 4"/>',
        'lessons' => '<path d="M9 18h6M10 21h4"/><path d="M12 3a6 6 0 0 0-3.5 10.9c.6.5 1 1.2 1 2.1h5c0-.9.4-1.6 1-2.1A6 6 0 0 0 12 3Z"/>',
        'actions' => '<path d="M4 6.5 5.5 8 8 5"/><path d="M4 12.5 5.5 14 8 11"/><path d="M4 18.5 5.5 20 8 17"/><path d="M11 6.5h9M11 12.5h9M11 18.5h6"/>',
        'standard' => '<rect x="4" y="3" width="16" height="18"/><path d="M8 8h8M8 12h8M8 16h5"/>',
        'quiz' => '<rect x="4" y="3" width="16" height="18"/><path d="M8 8.5l1.5 1.5L12 7.5M8 15l1.5 1.5L12 14"/><path d="M14 9h2.5M14 15.5h2.5"/>',
        'free' => '<path d="M4 20h4L19 9l-4-4L4 16v4Z"/><path d="m13.5 6.5 4 4"/>',
        'clock' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
        'check' => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
        'tip' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 11v5.5M12 7.5v.5"/>',
    ];

    return '<svg class="bo-aar-ico" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="square" stroke-linejoin="miter" aria-hidden="true">'
        . ($paths[$name] ?? $paths['tip']) . '</svg>';
};
