<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Audit\AuditAction;
use App\Support\GameClientVersionGate as Gate;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Plancher de version des DLL du jeu (SEC-001) : règle, réponse de refus, cache du plancher, point d'entrée.
 */
final class GameClientVersionGateTest extends TestCase
{
    private const FLOOR = ['link' => '2.0.96', 'atak' => '2.4.1'];

    private const OLD_LINK = 'COMSPECExtension/2.0.95';

    /** @return iterable<string, array{string, string, bool, string}> User-Agent, plancher, refusé, motif */
    public static function decisions(): iterable
    {
        yield 'Link 2.0.95 (ATAK 2.4.0, en production)' => ['COMSPECExtension/2.0.95', '2.0.96', true, 'outdated'];
        yield 'Link 2.0.96 (correctif, égal au plancher)' => ['COMSPECExtension/2.0.96', '2.0.96', false, 'up_to_date'];
        yield 'Link 2.0.97 (plus récent)' => ['COMSPECExtension/2.0.97', '2.0.96', false, 'up_to_date'];
        yield 'Link 2.0.100 (numérique, pas alphabétique)' => ['COMSPECExtension/2.0.100', '2.0.96', false, 'up_to_date'];
        yield 'Link 2.0.9 (plus ancien)' => ['COMSPECExtension/2.0.9', '2.0.96', true, 'outdated'];
        yield 'Link 2.1 (deux composantes)' => ['COMSPECExtension/2.1', '2.0.96', false, 'up_to_date'];
        yield 'Link 2.0.96.0 (composante nulle)' => ['COMSPECExtension/2.0.96.0', '2.0.96', false, 'up_to_date'];
        yield 'Link 1.99.999 (majeure plus petite)' => ['COMSPECExtension/1.99.999', '2.0.96', true, 'outdated'];
        yield 'suffixe +sha ignoré' => ['COMSPECExtension/2.0.96+5e22a261', '2.0.96', false, 'up_to_date'];
        yield 'suffixe +sha sur version ancienne' => ['COMSPECExtension/2.0.95+5e22a261', '2.0.96', true, 'outdated'];
        yield 'DLL native publiée (1.0.0-native)' => ['COMSPECATAKNativeExtension/1.0.0-native', '2.0.96', true, 'outdated'];
        yield 'DLL native corrigée (2.0.96-native)' => ['COMSPECATAKNativeExtension/2.0.96-native', '2.0.96', false, 'up_to_date'];
        yield 'DLL native corrigée (2.0.96)' => ['COMSPECATAKNativeExtension/2.0.96', '2.0.96', false, 'up_to_date'];
        yield 'casse du produit indifférente' => ['comspecextension/2.0.95', '2.0.96', true, 'outdated'];
        yield 'version illisible : refusée' => ['COMSPECExtension/abc', '2.0.96', true, 'unreadable_version'];
        yield 'version absente : refusée' => ['COMSPECExtension', '2.0.96', true, 'unreadable_version'];
        yield 'version vide : refusée' => ['COMSPECExtension/', '2.0.96', true, 'unreadable_version'];
        yield 'plancher vide : rien n\'est bloqué' => ['COMSPECExtension/1.0.0', '', false, 'no_floor'];
        yield 'plancher illisible : rien n\'est bloqué' => ['COMSPECExtension/1.0.0', 'abc', false, 'invalid_floor'];
        yield 'navigateur' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36', '2.0.96', false, 'not_game_client'];
        yield 'sans User-Agent' => ['', '2.0.96', false, 'not_game_client'];
        yield 'outil (curl)' => ['curl/8.5.0', '2.0.96', false, 'not_game_client'];
        yield 'robot' => ['Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', '2.0.96', false, 'not_game_client'];
        yield 'intégration' => ['DiscordBot (https://discord.com, 10)', '2.0.96', false, 'not_game_client'];
        yield 'nom voisin non reconnu' => ['COMSPECExtensionTool/1.0', '2.0.96', false, 'not_game_client'];
    }

    #[DataProvider('decisions')]
    public function testDecision(string $userAgent, string $floor, bool $blocked, string $reason): void
    {
        $decision = Gate::decide($userAgent, '', $floor);

        self::assertSame($blocked, $decision['blocked']);
        self::assertSame($reason, $decision['reason']);
    }

    public function testExtensionVersionIsOnlyAFallbackForAnIdentifiedDll(): void
    {
        // User-Agent de DLL sans numéro : extension_version décide.
        self::assertFalse(Gate::decide('COMSPECExtension', '2.0.96', '2.0.96')['blocked']);
        self::assertTrue(Gate::decide('COMSPECExtension', '2.0.95', '2.0.96')['blocked']);
        // Le User-Agent prime : la DLL native publiée envoie extension_version « 2.0.95 » (GameAuth) mais annonce 1.0.0-native.
        $native = Gate::decide('COMSPECATAKNativeExtension/1.0.0-native', '2.0.96', '2.0.96');
        self::assertTrue($native['blocked']);
        self::assertSame('1.0.0', $native['version']);
        self::assertSame(Gate::PRODUCT_NATIVE, $native['product']);
        // Sans User-Agent de DLL, extension_version seul n'identifie pas une DLL.
        self::assertFalse(Gate::decide('', '2.0.90', '2.0.96')['blocked']);
    }

    public function testCheckReadsTheFallbackFromTheQueryString(): void
    {
        $server = ['HTTP_USER_AGENT' => 'COMSPECExtension', 'REQUEST_METHOD' => 'GET'];

        self::assertNull(Gate::check('/api/atak/units', $server + ['QUERY_STRING' => 'mapId=1&extension_version=2.0.97'], self::FLOOR));
        $refusal = Gate::check('/api/atak/units', $server + ['QUERY_STRING' => 'extension_version=2.0.95'], self::FLOOR);
        self::assertNotNull($refusal);
        self::assertSame(403, $refusal->statusCode());
    }

    public function testNormalizeVersion(): void
    {
        self::assertSame('1.0.0', Gate::normalizeVersion('1.0.0-native'));
        self::assertSame('2.0.96', Gate::normalizeVersion('2.0.96+5e22a261'));
        self::assertSame('2.0.96', Gate::normalizeVersion(' v2.0.96 '));
        self::assertSame('2.0.96', Gate::normalizeVersion('02.00.096'));
        self::assertSame('2.0.96', Gate::normalizeVersion('2.0.96-rc1'));
        self::assertSame('2', Gate::normalizeVersion('2'));
        self::assertNull(Gate::normalizeVersion(''));
        self::assertNull(Gate::normalizeVersion('abc'));
        self::assertNull(Gate::normalizeVersion('native-2.0.96'));
        self::assertNull(Gate::normalizeVersion('1.2.3.4.5'));
        self::assertNull(Gate::normalizeVersion('1234567890.0.0'));
    }

    public function testNormalizeFloor(): void
    {
        self::assertSame('', Gate::normalizeFloor('  '));
        self::assertSame('2.0.96', Gate::normalizeFloor('2.0.96'));
        self::assertSame('2.0.96', Gate::normalizeFloor('v2.0.96'));
        self::assertSame('2.4.1', Gate::normalizeFloor(' 2.4.1 '));
        self::assertNull(Gate::normalizeFloor('2.0.96-native'));
        self::assertNull(Gate::normalizeFloor('2..0'));
        self::assertNull(Gate::normalizeFloor('abc'));
        self::assertNull(Gate::normalizeFloor('<script>'));
    }

    public function testCompareVersions(): void
    {
        self::assertSame(0, Gate::compareVersions('2.0.96', '2.0.96.0'));
        self::assertSame(-1, Gate::compareVersions('2.0.95', '2.0.96'));
        self::assertSame(1, Gate::compareVersions('2.0.100', '2.0.96'));
        self::assertSame(1, Gate::compareVersions('2.1', '2.0.96'));
        self::assertSame(-1, Gate::compareVersions('1.0.0', '2.0.96'));
    }

    public function testEmptyFloorBlocksNothing(): void
    {
        self::assertNull(Gate::check('/api/atak/units', ['HTTP_USER_AGENT' => 'COMSPECExtension/1.0.0'], ['link' => '', 'atak' => '']));
        self::assertNull(Gate::check('/api/game/v1/session/restore', ['HTTP_USER_AGENT' => 'COMSPECATAKNativeExtension/1.0.0-native'], ['link' => '', 'atak' => '2.4.1']));
    }

    public function testNonDllRequestsAreNeverBlocked(): void
    {
        $browser = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';
        self::assertNull(Gate::check('/api/atak/units', ['HTTP_USER_AGENT' => $browser], self::FLOOR));
        self::assertNull(Gate::check('/api/atak/units', [], self::FLOOR));
        self::assertNull(Gate::check('/admin/settings', ['HTTP_USER_AGENT' => 'curl/8.5.0'], self::FLOOR));
    }

    public function testUpdateInformationStaysReachable(): void
    {
        self::assertNull(Gate::check('/api/atak/version', ['HTTP_USER_AGENT' => self::OLD_LINK], self::FLOOR));
        self::assertNull(Gate::check('/api/atak/version', ['HTTP_USER_AGENT' => 'COMSPECATAKNativeExtension/1.0.0-native'], self::FLOOR));
    }

    public function testUpToDateDllPasses(): void
    {
        self::assertNull(Gate::check('/api/atak/units', ['HTTP_USER_AGENT' => 'COMSPECExtension/2.0.96'], self::FLOOR));
        self::assertNull(Gate::check('/api/game/v1/session/restore', ['HTTP_USER_AGENT' => 'COMSPECATAKNativeExtension/2.0.96-native'], self::FLOOR));
    }

    /** @return iterable<string, array{string}> */
    public static function operationalPaths(): iterable
    {
        yield 'client-init' => ['/api/atak/client-init'];
        yield 'sondage des ordres' => ['/api/atak/orders'];
        yield 'tchat' => ['/api/chat'];
        yield 'lot de télémétrie' => ['/api/atak/telemetry/batch'];
        yield 'check de démarrage' => ['/api/atak/startup-check'];
        yield 'whoami ATAK' => ['/api/atak/whoami'];
    }

    #[DataProvider('operationalPaths')]
    public function testOperationalPathsGet403UnauthorizedSoThe2095DllLatches(string $path): void
    {
        $response = Gate::check($path, ['HTTP_USER_AGENT' => self::OLD_LINK], self::FLOOR);

        self::assertNotNull($response);
        self::assertSame(403, $response->statusCode());
        self::assertSame((string) Gate::RETRY_AFTER_SEC, $response->headerValue('Retry-After'));
        self::assertSame('no-store', $response->headerValue('Cache-Control'));
        // La DLL 2.0.95 lit le premier champ « error » : il doit valoir « unauthorized » (refus définitif, verrou).
        self::assertStringStartsWith('{"error":"unauthorized"', $response->body());
        $body = json_decode($response->body(), true);
        self::assertSame('MOD_OUTDATED', $body['code']);
        self::assertTrue($body['update_required']);
        // Lus et affichés par COMSPEC Link (« Pack actuel : 2.0.95 — version exigée : 2.0.96 ») : numéros de DLL des deux côtés.
        self::assertSame('2.0.96', $body['min_mod_version']);
        self::assertSame('2.0.95', $body['detected_mod_version']);
        self::assertSame('2.0.96', $body['min_link_version']);
        self::assertSame('2.0.95', $body['detected_link_version']);
        self::assertSame('2.4.1', $body['min_atak_version']);
        self::assertSame(Gate::PRODUCT_LINK, $body['client_product']);
        self::assertSame('COMSPEC ATAK is out of date: update it from the Steam Workshop (2.4.1 or later).', $body['message']);
        self::assertSame('COMSPEC ATAK n\'est plus à jour : mettez-le à jour depuis le Workshop Steam (2.4.1 ou plus récent).', $body['message_fr']);
    }

    /** @return iterable<string, array{string}> */
    public static function sessionPaths(): iterable
    {
        yield 'reprise Game Auth' => ['/api/game/v1/session/restore'];
        yield 'connexion Steam' => ['/api/game/v1/auth/steam/exchange'];
        yield 'profil Game Auth' => ['/api/game/v1/profile'];
        yield 'liaison par code' => ['/api/atak/pair/start'];
        yield 'suivi du code' => ['/api/atak/pair/status'];
        yield 'récupération' => ['/api/atak/recovery/redeem'];
        yield 'code court' => ['/api/atak/game-link/redeem'];
        yield 'reprise de session ATAK' => ['/api/atak/session-restore'];
        yield 'sous-dossier /public' => ['/public/api/atak/pair/start'];
    }

    #[DataProvider('sessionPaths')]
    public function testSessionPathsGet426ModOutdated(string $path): void
    {
        $response = Gate::check($path, ['HTTP_USER_AGENT' => self::OLD_LINK], self::FLOOR);

        self::assertNotNull($response);
        self::assertSame(426, $response->statusCode());
        self::assertNull($response->headerValue('Retry-After'));
        $body = json_decode($response->body(), true);
        self::assertSame('MOD_OUTDATED', $body['error']);
        self::assertSame('MOD_OUTDATED', $body['code']);
        self::assertSame('2.0.96', $body['min_mod_version']);
        self::assertSame('2.0.95', $body['detected_mod_version']);
        self::assertSame('2.4.1', $body['min_atak_version']);
    }

    public function testWithoutAtakVersionTheDllNumbersArePaired(): void
    {
        $response = Gate::check('/api/game/v1/session/restore', ['HTTP_USER_AGENT' => self::OLD_LINK], ['link' => '2.0.96', 'atak' => '']);

        self::assertNotNull($response);
        $body = json_decode($response->body(), true);
        self::assertSame('2.0.96', $body['min_mod_version']);
        self::assertSame('2.0.95', $body['detected_mod_version']);
        self::assertArrayNotHasKey('min_atak_version', $body);
        self::assertSame('COMSPEC ATAK is out of date: update it from the Steam Workshop (COMSPEC Link 2.0.96 or later).', $body['message']);
    }

    public function testRefusalBodiesAvoidKeywordsThatOldDllsMapToOtherRefusals(): void
    {
        $words = [
            'tenant', 'steam_not_linked', 'steam_required', 'account_disabled', 'mod_steam_blocked', 'mod_ip_blocked',
            'atak_blocked', 'feature_unavailable', 'maintenance', 'invalid_credentials', 'otp_expired', 'no_tenant',
            'session_expired',
        ];
        foreach (['/api/atak/client-init', '/api/game/v1/session/restore'] as $path) {
            foreach ([self::FLOOR, ['link' => '2.0.96', 'atak' => '']] as $floor) {
                $response = Gate::check($path, ['HTTP_USER_AGENT' => self::OLD_LINK], $floor);
                self::assertNotNull($response);
                $raw = strtolower($response->body());
                foreach ($words as $word) {
                    self::assertStringNotContainsString($word, $raw, $path . ' : ' . $word);
                }
            }
        }
    }

    public function testUnreadableVersionIsRefusedWithAnEmptyDetectedVersion(): void
    {
        $response = Gate::check('/api/atak/units', ['HTTP_USER_AGENT' => 'COMSPECExtension/abc', 'REQUEST_METHOD' => 'GET'], self::FLOOR);

        self::assertNotNull($response);
        self::assertSame(403, $response->statusCode());
        $body = json_decode($response->body(), true);
        self::assertSame('', $body['detected_link_version']);
        // Sans numéro lisible, le mod garde le sien plutôt qu'un numéro vide.
        self::assertArrayNotHasKey('detected_mod_version', $body);
    }

    public function testLoadFloorReadsTheDatabaseAtMostOncePerTtl(): void
    {
        $dir = sys_get_temp_dir() . '/gate-floor-' . bin2hex(random_bytes(4));
        $file = $dir . '/client-version-floor.json';
        $calls = 0;
        $database = static function () use (&$calls): array {
            $calls++;

            return ['link' => '2.0.96', 'atak' => '2.4.1'];
        };
        $failing = static function (): array {
            throw new \RuntimeException('Database connection failed: refused');
        };
        $now = time();

        try {
            // Pas de cache : une lecture en base, cache écrit (dossier créé au besoin).
            self::assertSame(self::FLOOR, Gate::loadFloor($file, $database, $now));
            self::assertSame(1, $calls);
            self::assertFileExists($file);
            // Cache frais : aucune lecture en base.
            self::assertSame(self::FLOOR, Gate::loadFloor($file, $database, $now + 5));
            self::assertSame(1, $calls);
            // Cache périmé : relu en base.
            touch($file, $now - Gate::CACHE_TTL_SEC - 1);
            self::assertSame(self::FLOOR, Gate::loadFloor($file, $database, $now));
            self::assertSame(2, $calls);
            // Base injoignable : le dernier plancher connu reste en vigueur.
            touch($file, $now - Gate::CACHE_TTL_SEC - 1);
            self::assertSame(self::FLOOR, Gate::loadFloor($file, $failing, $now));
            // Base injoignable et aucun cache : aucun blocage.
            unlink($file);
            self::assertSame(['link' => '', 'atak' => ''], Gate::loadFloor($file, $failing, $now));
            // Cache corrompu : relu en base.
            file_put_contents($file, '{oops');
            self::assertSame(self::FLOOR, Gate::loadFloor($file, $database, $now));
            self::assertSame(3, $calls);
        } finally {
            @unlink($file);
            @rmdir($dir);
        }
    }

    public function testReadFloorFromDatabaseNormalizesAndIgnoresUnreadableValues(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE platform_settings (setting_key VARCHAR(100) PRIMARY KEY, value TEXT, updated_at TEXT)');

        self::assertSame(['link' => '', 'atak' => ''], Gate::readFloorFromDatabase($pdo));

        $insert = $pdo->prepare('INSERT INTO platform_settings (setting_key, value) VALUES (?, ?)');
        $insert->execute([Gate::SETTING_MIN_LINK_VERSION, ' v2.0.96 ']);
        $insert->execute([Gate::SETTING_MIN_ATAK_VERSION, '2.4.1']);
        $insert->execute(['autre_reglage', '9.9.9']);
        self::assertSame(self::FLOOR, Gate::readFloorFromDatabase($pdo));

        $pdo->exec("UPDATE platform_settings SET value = 'abc' WHERE setting_key = '" . Gate::SETTING_MIN_LINK_VERSION . "'");
        self::assertSame(['link' => '', 'atak' => '2.4.1'], Gate::readFloorFromDatabase($pdo));
    }

    public function testFrontControllerAppliesTheGateBeforeMaintenanceSessionAndDatabase(): void
    {
        $index = (string) file_get_contents(dirname(__DIR__, 2) . '/public/index.php');
        $gate = strpos($index, 'GameClientVersionGate::check($requestPath, $_SERVER)');

        self::assertNotFalse($gate);
        self::assertGreaterThan((int) strpos($index, '$isPublicAsset'), $gate);
        self::assertLessThan((int) strpos($index, 'PortalHost::legacyNoticeTarget($_SERVER)'), $gate);
        self::assertLessThan((int) strpos($index, 'MaintenanceGuard::isInfrastructurePath'), $gate);
        self::assertLessThan((int) strpos($index, '\App\Core\Database::getPdo()'), $gate);
        self::assertLessThan((int) strpos($index, 'new \App\Core\Application()'), $gate);
    }

    public function testPlatformAdminFieldAndCliTool(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $controller = (string) file_get_contents($root . '/app/Controllers/Admin/System/SystemSettingsController.php');
        $view = (string) file_get_contents($root . '/views/admin/system/settings.php');
        $cli = (string) file_get_contents($root . '/tools/game-client-gate.php');

        self::assertMatchesRegularExpression(
            "/post\\('\\/admin\\/settings\\/version-dll', \\[SystemSettingsController::class, 'saveGameClientGate'\\], \\[AuthMiddleware::class, SystemAdminMiddleware::class\\]\\)/",
            $routes
        );
        self::assertStringContainsString('Csrf::validate', $controller);
        self::assertStringContainsString('AuditAction::PLATFORM_GAME_CLIENT_FLOOR_UPDATED', $controller);
        self::assertStringContainsString('GameClientVersionGate::forgetCache()', $controller);
        self::assertSame('platform.game_client_floor_updated', AuditAction::PLATFORM_GAME_CLIENT_FLOOR_UPDATED);
        self::assertStringContainsString('name="_csrf_token"', $view);
        self::assertStringContainsString('name="clear" value="1"', $view);
        self::assertStringContainsString("url('admin/settings/version-dll')", $view);
        foreach (["case 'status'", "case 'set'", "case 'clear'", "case 'check'", 'Gate::forgetCache()', 'AuditAction::PLATFORM_GAME_CLIENT_FLOOR_UPDATED', "PHP_SAPI !== 'cli'"] as $needle) {
            self::assertStringContainsString($needle, $cli, $needle);
        }
    }
}
