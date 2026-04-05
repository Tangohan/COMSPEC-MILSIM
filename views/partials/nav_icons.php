<?php

declare(strict_types=1);

/**
 * Icônes méga-menu / triggers (SVG inline, 16–20px).
 */
function nav_icon_svg(string $name, string $class = 'h-4 w-4'): string
{
    $c = htmlspecialchars($class, ENT_QUOTES, 'UTF-8');
    return match ($name) {
        'crosshair' => '<svg class="' . $c . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><circle cx="12" cy="12" r="3" stroke-width="2"/><path stroke-width="2" stroke-linecap="round" d="M12 3v3M12 18v3M3 12h3M18 12h3"/></svg>',
        'folder' => '<svg class="' . $c . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-width="2" stroke-linejoin="round" d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/></svg>',
        'users' => '<svg class="' . $c . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-width="2" stroke-linecap="round" d="M17 21v-2a4 4 0 00-4-4H7a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zM23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>',
        'academic' => '<svg class="' . $c . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-width="2" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5z"/><path stroke-width="2" stroke-linejoin="round" d="M12 14l6.16 3.422a12 12 0 01-.84 1.539L12 21l-5.32-3.04a12 12 0 01-.84-1.539L12 14z"/></svg>',
        'shield' => '<svg class="' . $c . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-width="2" stroke-linejoin="round" d="M12 3l8 4v6c0 5-3.5 9-8 10-4.5-1-8-5-8-10V7l8-4z"/></svg>',
        default => '<svg class="' . $c . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke-width="2"/></svg>',
    };
}
