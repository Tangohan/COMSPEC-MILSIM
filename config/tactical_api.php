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
        // Accès anticipé Overwatch : 1er lancement menu principal, sans clé ATAK
        // (extension RegisterBeta — voir AtakApiController::betaRegister, rate-limité).
        '/api/atak/beta-register',
        // Rapports d’erreurs / bugs Overwatch : avant ou sans liaison (ReportDiag / signalement),
        // rate-limité — voir AtakApiController::modReport.
        '/api/atak/mod-report',
        // Terminal / certificat réalisme : NON exemptés — le mod utilise la clé communauté
        // (RegisterTerminal / RegisterCertificate / GetTerminalRealism → AtakRealismApiController).
        // QR téléphone : token dans l’URL (TTL court) — scannable / téléchargeable sans clé
        // (exemption dynamique dans ComspecApiKeyAuth::pathRequiresProtection pour …/qr.png).
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
        // Contrats unifiés /api/operations/* (timeline mission, SITREP, AAR/RETEX,
        // readiness, médical, logistique, comms, doctrine). Mêmes données tactiques
        // que /api/replay/ et /api/intel/, donc même niveau d’exigence.
        //
        // Note : /api/operations/doctrine/documents (DoctrineApiController) porte déjà
        // AuthMiddleware. Une session membre valide satisfait la garde tactique
        // (ComspecApiKeyAuth::authenticatedBrowserSessionMayAccessTactical), sauf si le
        // déploiement a explicitement posé TACTICAL_API_ALLOW_SESSION=false — auquel cas
        // une clé d’accès est requise, ce qui est le comportement voulu dans ce mode.
        '/api/operations/',
    ],
    'exempt_paths' => [],
];
