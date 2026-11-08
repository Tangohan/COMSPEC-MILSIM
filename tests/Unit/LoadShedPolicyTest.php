<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Watch\LoadShedPolicy;
use PHPUnit\Framework\TestCase;

/**
 * Priorité sous charge : quelles routes ne sont JAMAIS ralenties, quelles formules sont visées à chaque palier.
 */
final class LoadShedPolicyTest extends TestCase
{
    private const METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

    /** Chemins qui ne doivent jamais être ralentis, par catégorie demandée. */
    private const CRITICAL = [
        'connexion et session' => [
            '/login', '/login/otp', '/login/select-community', '/logout', '/register', '/forgot-password', '/reset-password',
            '/verify-email', '/account/steam/connect', '/community/switch', '/api/game/v1/auth/password', '/api/game/v1/session/restore',
            '/api/game/v1/bootstrap', '/api/atak/client-init', '/api/atak/game-link/redeem', '/api/atak/pair/start',
            '/api/atak/recovery/redeem', '/api/atak/session-restore', '/api/me/preferences',
        ],
        'paiements et webhooks' => [
            '/api/stripe/webhook', '/api/paypal/webhook', '/platform/upgrade', '/platform/upgrade/checkout',
            '/paiement/ATH-1234', '/soutenir-atak/checkout', '/communities/create/pay',
        ],
        'alertes de commandement' => [
            '/api/atak/orders', '/api/atak/orders/12/status', '/api/atak/overwatch/alerts', '/api/atak/overwatch/alerts/ack',
            '/api/atak/tactical-alerts', '/api/atak/device-alerts', '/api/medical-alerts', '/api/nine-line', '/api/cas',
            '/api/atak/salute', '/api/chat',
        ],
        'administration plateforme' => [
            '/admin', '/admin/system/surcharge', '/admin/system/alertes-mod/12/traiter', '/admin/settings', '/api/admin/users',
            '/cron/run',
        ],
        'signalement d’erreurs du mod et sondes' => [
            '/api/atak/mod-report', '/api/atak/startup-check', '/api/atak/device-logs', '/api/atak/ping', '/api/atak/version',
            '/api/health', '/api/system/version',
        ],
        'interface opérationnelle' => ['/atak', '/atak/map', '/m/carte', '/connect/abc123/carte', '/assets/js/app.js'],
    ];

    public function testCriticalRoutesAreNeverSlowedWhateverTheMethodOrTheClient(): void
    {
        foreach (self::CRITICAL as $category => $paths) {
            foreach ($paths as $path) {
                self::assertTrue(LoadShedPolicy::isNeverSlowed($path), $category . ' : ' . $path);
                foreach (self::METHODS as $method) {
                    foreach ([true, false] as $gameClient) {
                        self::assertFalse(
                            LoadShedPolicy::isSheddable($method, $path, $gameClient),
                            $category . ' : ' . $method . ' ' . $path . ($gameClient ? ' (mod)' : ' (navigateur)')
                        );
                    }
                }
            }
        }
    }

    public function testEveryDeclaredRouteOfACriticalFamilyIsNeverSlowed(): void
    {
        $routes = (string) file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');
        preg_match_all("#\\\$router->(get|post|put|patch|delete)\\('(/[^']*)'#", $routes, $m, PREG_SET_ORDER);
        self::assertGreaterThan(500, count($m), 'routes/web.php non lu');
        $families = '#^/(admin|api/admin|login|logout|register|forgot-password|reset-password|verify-email|api/stripe|api/paypal|api/game/v1|'
            . 'api/atak/(mod-report|startup-check|device-logs|client-init|orders|ping|version|game-link|pair|recovery)|cron)(/|$)#';
        $checked = 0;
        foreach ($m as [, $method, $path]) {
            if (preg_match($families, $path) !== 1) {
                continue;
            }
            $checked++;
            // Les segments {id} d'une route sont remplacés par une valeur quelconque.
            $concrete = preg_replace('#\{[a-zA-Z_]+\}#', '42', $path);
            foreach ([true, false] as $gameClient) {
                self::assertFalse(LoadShedPolicy::isSheddable(strtoupper($method), (string) $concrete, $gameClient), strtoupper($method) . ' ' . $path);
            }
        }
        self::assertGreaterThan(100, $checked);
    }

    public function testOnlyModTrafficOnApiAndBrowserPageReadsCanBeSlowed(): void
    {
        // Envois et lectures du mod (clé ou jeton) sous /api/ : visés.
        self::assertTrue(LoadShedPolicy::isSheddable('POST', '/api/atak/position', true));
        self::assertTrue(LoadShedPolicy::isSheddable('POST', '/api/atak/telemetry/batch', true));
        self::assertTrue(LoadShedPolicy::isSheddable('GET', '/api/atak/units', true));
        // Pages du portail ouvertes en lecture par un navigateur : visées.
        self::assertTrue(LoadShedPolicy::isSheddable('GET', '/c/une-communaute', false));
        self::assertTrue(LoadShedPolicy::isSheddable('GET', '/communities', false));
        // Écritures et appels /api/ d'un navigateur : le client web mettrait toutes ses écritures en pause.
        self::assertFalse(LoadShedPolicy::isSheddable('POST', '/c/une-communaute/contact', false));
        self::assertFalse(LoadShedPolicy::isSheddable('GET', '/api/atak/units', false));
        self::assertFalse(LoadShedPolicy::isSheddable('POST', '/api/atak/position', false));
        // Le mod hors /api/ n'existe pas : non visé.
        self::assertFalse(LoadShedPolicy::isSheddable('GET', '/communities', true));
    }

    public function testTiersAreTargetedFromFreeUpwardAndProPlusIsNeverTargeted(): void
    {
        // [rang de formule, palier, visée]
        $cases = [
            [0, 0, false], [0, 1, true], [1, 1, false], [2, 1, false], [3, 1, false],
            [0, 2, true], [1, 2, true], [2, 2, false], [3, 2, false],
            [0, 3, true], [1, 3, true], [2, 3, true], [3, 3, false],
        ];
        foreach ($cases as [$rank, $level, $expected]) {
            self::assertSame($expected, LoadShedPolicy::tierTargeted($rank, $level), 'rang ' . $rank . ' au palier ' . $level);
        }
        // Rang inconnu ou négatif : jamais ralenti.
        self::assertFalse(LoadShedPolicy::tierTargeted(-1, 3));
    }

    public function testShedRatioSoftensTheSlowdown(): void
    {
        self::assertTrue(LoadShedPolicy::shouldShed(0, 1, 60, 0.0));
        self::assertTrue(LoadShedPolicy::shouldShed(0, 1, 60, 0.59));
        self::assertFalse(LoadShedPolicy::shouldShed(0, 1, 60, 0.6));
        self::assertFalse(LoadShedPolicy::shouldShed(0, 1, 60, 0.99));
        self::assertTrue(LoadShedPolicy::shouldShed(0, 1, 100, 0.999));
        // Une formule non visée n'est jamais ralentie, quel que soit le tirage.
        self::assertFalse(LoadShedPolicy::shouldShed(3, 3, 100, 0.0));
        self::assertFalse(LoadShedPolicy::shouldShed(1, 1, 100, 0.0));
    }

    public function testRetryAfterGrowsWithTheLevelAndStaysBounded(): void
    {
        self::assertSame(30, LoadShedPolicy::retryAfterSeconds(1, 0));
        self::assertSame(45, LoadShedPolicy::retryAfterSeconds(2, 0));
        self::assertSame(60, LoadShedPolicy::retryAfterSeconds(3, 0));
        self::assertSame(75, LoadShedPolicy::retryAfterSeconds(3, 99));
        self::assertGreaterThan(29, LoadShedPolicy::retryAfterSeconds(0, -5));
    }
}
