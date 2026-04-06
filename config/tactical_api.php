<?php

declare(strict_types=1);

/**
 * Chemins d’API soumis à ComspecApiKeyAuth en production (voir app/Support/ComspecApiKeyAuth.php).
 */
return [
    'atak_exempt_paths' => [
        '/api/atak/ping',
        '/api/atak/whoami',
    ],
    'protected_prefixes' => [
        '/api/markers',
        '/api/units',
        '/api/chat',
        '/api/pings',
        '/api/nine-line',
        '/api/cas',
        '/api/recon/',
        '/api/map-shapes',
        '/api/flight-manifest',
        '/api/fire-support',
        '/api/danger-zones',
        '/api/logistics',
        '/api/intel/',
        '/api/replay/',
        '/api/iff/',
    ],
    'exempt_paths' => [],
];
