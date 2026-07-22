<?php

declare(strict_types=1);

/**
 * Chemins d’API soumis à ComspecApiKeyAuth en production (voir app/Support/ComspecApiKeyAuth.php).
 */
return [
    'atak_exempt_paths' => [
        '/api/atak/ping',
        '/api/atak/whoami',
        // Code court à usage unique : le secret est le code lui-même (TTL court).
        '/api/atak/game-link/redeem',
        // Steam déjà lié au compte : l’UID Steam du client Arma fait office de preuve (pas de clé ATAK).
        '/api/atak/game-link/by-steam',
        // QR téléphone : token dans l’URL (TTL court) — scannable / téléchargeable sans clé (voir aussi regex dans ComspecApiKeyAuth).
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
