<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\MaintenanceGuard;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * NET-006 : la maintenance annonçait toujours Retry-After 900. La DLL COMSPEC Link (plafond 600 s) et l'Overwatch
 * web suspendent leurs envois pendant ce délai : une maintenance de 2 min figeait les positions près de 10 min.
 */
final class MaintenanceRetryAfterTest extends TestCase
{
    /** Fichiers de bootstrap/ chargés par public/index.php avant la maintenance. */
    private const BOOTSTRAP_FILES = ['env.php', 'error_hint.php', 'app.php', 'autoload.php', 'load_phpmailer.php'];

    private const LINKED_DIRS = ['app', 'views', 'vendor'];

    public function testRetryAfterFollowsTheAnnouncedEnd(): void
    {
        $now = (int) strtotime('2026-10-26 14:00:00');

        self::assertSame(120, MaintenanceGuard::retryAfterSeconds(['ends_at' => '2026-10-26 14:02:00'], $now));
        self::assertSame(900, MaintenanceGuard::retryAfterSeconds(['ends_at' => '2026-10-26 16:00:00'], $now), 'fin lointaine : délai inchangé');
        self::assertSame(15, MaintenanceGuard::retryAfterSeconds(['ends_at' => '2026-10-26 14:00:05'], $now), 'fin imminente : plancher');
        self::assertSame(15, MaintenanceGuard::retryAfterSeconds(['ends_at' => '2026-10-26 13:59:00'], $now), 'fin déjà passée : plancher');
    }

    public function testWithoutAnnouncedEndTheDelayIsUnchanged(): void
    {
        foreach ([[], ['ends_at' => null], ['ends_at' => ''], ['ends_at' => 'pas une date']] as $maintenance) {
            self::assertSame(900, MaintenanceGuard::retryAfterSeconds($maintenance, 1_000_000));
        }
    }

    public function testReturnsAreSpreadAfterAnAnnouncedEnd(): void
    {
        $now = (int) strtotime('2026-10-26 14:00:00');

        self::assertSame(127, MaintenanceGuard::retryAfterSeconds(['ends_at' => '2026-10-26 14:02:00'], $now, 7));
        self::assertSame(30, MaintenanceGuard::retryAfterSeconds(['ends_at' => '2026-10-26 14:00:05'], $now, 15), 'plancher puis étalement');
        self::assertSame(900, MaintenanceGuard::retryAfterSeconds(['ends_at' => '2026-10-26 16:00:00'], $now, 15), 'plafond conservé');
        self::assertSame(900, MaintenanceGuard::retryAfterSeconds([], $now, 15), 'sans fin annoncée : 900, sans étalement');
    }

    public function testJsonPayloadAnnouncesTheSameDelay(): void
    {
        $payload = MaintenanceGuard::jsonPayload(['title' => 'Mise à jour', 'ends_at' => '2026-10-26 14:02:00'], 'Retour à 14 h 02.', 127);

        self::assertSame(127, $payload['retry_after']);
        self::assertSame('maintenance', $payload['error']);
        self::assertSame(900, MaintenanceGuard::jsonPayload(['title' => 'Mise à jour'], 'Retour bientôt.')['retry_after']);
    }

    /**
     * L4-R1 : le comportement d'enforce(), pas sa source (un test qui cherchait des lignes dans le fichier laissait
     * passer un délai fixe de 900 s). Maintenance des API annoncée pour finir dans 2 min : à chaque requête,
     * l'en-tête Retry-After et le champ retry_after portent la même valeur, tirée de l'heure de fin
     * (120 s plus l'étalement de 0 à 15 s).
     */
    #[Group('php-builtin-server')]
    public function testEnforceSendsTheDelayOfTheAnnouncedEndInTheHeaderAndTheBody(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }

        $responses = self::serve(<<<'PHP'
            $pdo = new PDO('sqlite::memory:');
            $pdo->exec('CREATE TABLE app_maintenance (id INTEGER PRIMARY KEY, scope TEXT, is_enabled INTEGER, title TEXT,
                message TEXT, maintenance_code TEXT, starts_at TEXT, ends_at TEXT, allow_admin_bypass INTEGER,
                allowed_ips TEXT, allowed_roles TEXT, allowed_user_ids TEXT, redirect_url TEXT, http_status INTEGER,
                priority INTEGER, ui_variant TEXT, ui_animation INTEGER)');
            $pdo->prepare("INSERT INTO app_maintenance (scope, is_enabled, title, message, ends_at, allow_admin_bypass,
                http_status, priority) VALUES ('route:/api/', 1, 'Mise à jour', 'Retour dans deux minutes.', ?, 0, 503, 100)")
                ->execute([date('Y-m-d H:i:s', time() + 120)]);
            (new App\Support\MaintenanceGuard(new App\Support\MaintenanceService($pdo)))->enforce('/api/units');
            echo 'enforce() a laissé passer la requête';
            PHP, 5);

        foreach ($responses as $i => $response) {
            self::assertSame(503, $response['status'], "requête {$i} : {$response['raw']}");
            self::assertSame('1', $response['headers']['x-athena-maintenance'] ?? null);
            self::assertSame('maintenance', $response['body']['error'] ?? null);
            $header = (int) ($response['headers']['retry-after'] ?? 0);
            self::assertSame($header, $response['body']['retry_after'] ?? null, "requête {$i} : en-tête et corps annoncent un seul délai");
            self::assertGreaterThanOrEqual(110, $header, "requête {$i} : délai tiré de la fin annoncée (2 min)");
            self::assertLessThanOrEqual(135, $header, "requête {$i} : délai tiré de la fin annoncée (2 min), étalement compris");
        }
    }

    /**
     * L4-R2 : l'ancienne maintenance par fichier (mises à jour lancées depuis l'administration) n'a pas d'heure de
     * fin. Les API reviennent après 30 s plus un étalement borné à 30 s, au lieu de 300 s fixes.
     */
    public function testLegacyFileMaintenanceDelayIsShortAndSpread(): void
    {
        self::assertSame(30, MaintenanceGuard::legacyFileRetryAfter());
        self::assertSame(47, MaintenanceGuard::legacyFileRetryAfter(17));
        self::assertSame(60, MaintenanceGuard::legacyFileRetryAfter(30));
        self::assertSame(60, MaintenanceGuard::legacyFileRetryAfter(500), 'étalement borné');
        self::assertSame(30, MaintenanceGuard::legacyFileRetryAfter(-5), 'étalement jamais négatif');
    }

    /**
     * L4-R2 : réponse d'une API pendant l'ancienne maintenance par fichier. Avant, la page HTML « 503 » avec
     * Retry-After 300 : la DLL et le téléphone n'y lisaient pas la maintenance, et la DLL suspendait ses envois
     * 5 min dès le troisième échec. Maintenant le JSON de la maintenance planifiée, même délai dans l'en-tête et le
     * corps, tiré au hasard entre 30 et 60 s.
     */
    #[Group('php-builtin-server')]
    public function testLegacyFileMaintenanceAnswersApisInJson(): void
    {
        $responses = self::serve(<<<'PHP'
            App\Support\MaintenanceGuard::sendLegacyFileApiResponse('Mise à jour en cours.');
            PHP, 10);

        $delays = [];
        foreach ($responses as $i => $response) {
            self::assertSame(503, $response['status'], "requête {$i} : {$response['raw']}");
            self::assertStringStartsWith('application/json', $response['headers']['content-type'] ?? '');
            self::assertSame('no-store', $response['headers']['cache-control'] ?? null);
            self::assertSame('1', $response['headers']['x-athena-maintenance'] ?? null);
            self::assertSame('maintenance', $response['body']['error'] ?? null);
            self::assertSame('Mise à jour en cours.', $response['body']['message'] ?? null);
            $header = (int) ($response['headers']['retry-after'] ?? 0);
            self::assertSame($header, $response['body']['retry_after'] ?? null, "requête {$i} : en-tête et corps annoncent un seul délai");
            self::assertGreaterThanOrEqual(30, $header);
            self::assertLessThanOrEqual(60, $header);
            $delays[] = $header;
        }
        self::assertGreaterThan(1, count(array_unique($delays)), 'retours étalés : 10 délais tous égaux');
    }

    /**
     * TEST-013 (L4-R2, branchement) : le vrai public/index.php, servi par `php -S` pendant l'ancienne maintenance par
     * fichier. Avant, ce branchement n'était vérifié qu'en cherchant des lignes dans le fichier. Le contrôleur frontal
     * et ses fichiers de démarrage sont copiés dans un dossier temporaire, où storage/maintenance.json est posé : le
     * dépôt n'est jamais mis en maintenance. Les API (avec ou sans en-tête Accept, en lecture comme en écriture : la
     * DLL envoie ses positions et son tchat en POST) reçoivent le JSON « maintenance » avec un délai de 30 à 60 s, le
     * même dans l'en-tête et le corps ; les pages reçoivent la page HTML avec Retry-After 300, comme avant.
     */
    #[Group('php-builtin-server')]
    public function testLegacyFileMaintenanceThroughTheFrontControllerAnswersApisInJsonAndPagesInHtml(): void
    {
        $copy = self::frontControllerCopy(['enabled' => true, 'message' => 'Mise à jour en cours.', 'allowed_ips' => []]);
        try {
            $responses = self::request($copy . '/public/index.php', $copy . '/public', [
                ['/api/units', 'application/json'],
                ['/api/atak/position', ''],
                ['/api/chat?mapId=1&limit=25&after=10', ''],
                ['/api/atak/client-init', 'application/json'],
                ['/api/atak/position', '', 'POST'],
                ['/api/chat', 'application/json', 'POST'],
                ['/', 'text/html,application/xhtml+xml'],
                ['/login', 'text/html,application/xhtml+xml'],
            ]);
        } finally {
            self::removeFrontControllerCopy($copy);
        }

        foreach (array_slice($responses, 0, 6) as $i => $response) {
            self::assertSame(503, $response['status'], "API {$i} : {$response['raw']}");
            self::assertStringStartsWith('application/json', $response['headers']['content-type'] ?? '', "API {$i} : {$response['raw']}");
            self::assertSame('no-store', $response['headers']['cache-control'] ?? null, "API {$i}");
            self::assertSame('1', $response['headers']['x-athena-maintenance'] ?? null, "API {$i}");
            self::assertSame('maintenance', $response['body']['error'] ?? null, "API {$i}");
            self::assertSame('Mise à jour en cours.', $response['body']['message'] ?? null, "API {$i}");
            $header = (int) ($response['headers']['retry-after'] ?? 0);
            self::assertSame($header, $response['body']['retry_after'] ?? null, "API {$i} : en-tête et corps annoncent un seul délai");
            self::assertGreaterThanOrEqual(30, $header, "API {$i}");
            self::assertLessThanOrEqual(60, $header, "API {$i}");
        }
        foreach (array_slice($responses, 6) as $i => $response) {
            self::assertSame(503, $response['status'], "page {$i} : {$response['raw']}");
            self::assertStringStartsWith('text/html', strtolower($response['headers']['content-type'] ?? ''), "page {$i}");
            self::assertSame('300', $response['headers']['retry-after'] ?? null, "page {$i} : délai des pages inchangé");
            self::assertNull($response['body'], "page {$i} : pas de JSON");
            self::assertStringContainsString('<html', $response['raw'], "page {$i}");
        }
    }

    /**
     * Copie temporaire pour servir le vrai public/index.php. Le contrôleur frontal et les fichiers de démarrage qu'il
     * charge sont de vraies copies : PHP résout les liens symboliques, et un __DIR__ lu à travers un lien désignerait
     * le dépôt, donc son storage/. app/, views/ et vendor/ sont des liens vers le dépôt.
     *
     * @param array<string, mixed> $maintenance contenu de storage/maintenance.json
     */
    private static function frontControllerCopy(array $maintenance): string
    {
        $root = dirname(__DIR__, 2);
        $copy = sys_get_temp_dir() . '/athena-front-' . bin2hex(random_bytes(6));
        foreach (['/public', '/bootstrap', '/storage'] as $sub) {
            mkdir($copy . $sub, 0777, true);
        }
        copy($root . '/public/index.php', $copy . '/public/index.php');
        foreach (self::BOOTSTRAP_FILES as $file) {
            copy($root . '/bootstrap/' . $file, $copy . '/bootstrap/' . $file);
        }
        foreach (self::LINKED_DIRS as $name) {
            if (file_exists($root . '/' . $name) && !@symlink($root . '/' . $name, $copy . '/' . $name)) {
                self::removeFrontControllerCopy($copy);
                self::markTestSkipped('liens symboliques indisponibles');
            }
        }
        file_put_contents($copy . '/storage/maintenance.json', json_encode($maintenance, JSON_THROW_ON_ERROR));

        return $copy;
    }

    /** Retire la copie : les liens eux-mêmes (jamais leur cible), puis les fichiers et dossiers posés par le test. */
    private static function removeFrontControllerCopy(string $copy): void
    {
        foreach (self::LINKED_DIRS as $name) {
            if (is_link($copy . '/' . $name)) {
                unlink($copy . '/' . $name);
            }
        }
        foreach (self::BOOTSTRAP_FILES as $file) {
            @unlink($copy . '/bootstrap/' . $file);
        }
        @unlink($copy . '/public/index.php');
        @unlink($copy . '/storage/maintenance.json');
        foreach (['/public', '/bootstrap', '/storage', ''] as $sub) {
            @rmdir($copy . $sub);
        }
    }

    /**
     * Sert $script (code PHP sans balise d'ouverture, classes App\ chargées depuis le dépôt) par `php -S` sur
     * 127.0.0.1 et lui envoie $requests requêtes GET /api/units. header() et exit ne s'observent qu'avec un vrai
     * serveur.
     *
     * @return list<array{status: int, headers: array<string, string>, body: array<string, mixed>|null, raw: string}>
     */
    private static function serve(string $script, int $requests): array
    {
        $dir = sys_get_temp_dir() . '/athena-maintenance-' . bin2hex(random_bytes(6));
        mkdir($dir);
        $router = $dir . '/router.php';
        $prelude = <<<'PHP'
            <?php

            declare(strict_types=1);

            spl_autoload_register(static function (string $class): void {
                $file = __ROOT__ . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
                if (str_starts_with($class, 'App\\') && is_file($file)) {
                    require $file;
                }
            });

            PHP;
        file_put_contents($router, strtr($prelude, ['__ROOT__' => var_export(dirname(__DIR__, 2), true)]) . $script . "\n");

        try {
            return self::request($router, $dir, array_fill(0, $requests, ['/api/units', 'application/json']));
        } finally {
            @unlink($router);
            @rmdir($dir);
        }
    }

    /**
     * Lance `php -S` sur 127.0.0.1, port libre, avec le script de routage $router, et lui envoie les requêtes
     * $requests ([chemin, en-tête Accept ; vide : pas d'en-tête, méthode : GET si absente]). Un POST porte un petit
     * corps JSON, comme un envoi de la DLL. Le serveur est arrêté dans tous les cas.
     *
     * @param list<array{0: string, 1: string, 2?: string}> $requests
     * @return list<array{status: int, headers: array<string, string>, body: array<string, mixed>|null, raw: string}>
     */
    private static function request(string $router, string $docroot, array $requests): array
    {
        if (!function_exists('proc_open')) {
            self::markTestSkipped('proc_open indisponible');
        }

        $log = sys_get_temp_dir() . '/athena-php-server-' . bin2hex(random_bytes(6)) . '.log';
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        self::assertNotFalse($socket, "port local : {$errstr}");
        $name = (string) stream_socket_get_name($socket, false);
        $port = (int) substr($name, (int) strrpos($name, ':') + 1);
        fclose($socket);

        $server = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $docroot, $router],
            [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']],
            $pipes,
            $docroot
        );
        self::assertIsResource($server);
        fclose($pipes[0]);

        $responses = [];
        try {
            foreach ($requests as $request) {
                [$path, $accept] = $request;
                $method = $request[2] ?? 'GET';
                $connection = false;
                for ($try = 0; $connection === false && $try < 100; $try++) {
                    $connection = @stream_socket_client('tcp://127.0.0.1:' . $port, $errno, $errstr, 1);
                    if ($connection === false) {
                        usleep(50_000);
                    }
                }
                if ($connection === false) {
                    self::fail("php -S injoignable sur le port {$port} : " . (string) @file_get_contents($log));
                }
                stream_set_timeout($connection, 10);
                $acceptLine = $accept !== '' ? "Accept: {$accept}\r\n" : '';
                $payload = $method === 'GET' ? '' : '{"mapId":1}';
                $bodyLines = $payload === '' ? '' : "Content-Type: application/json\r\nContent-Length: " . strlen($payload) . "\r\n";
                fwrite($connection, "{$method} {$path} HTTP/1.0\r\nHost: 127.0.0.1\r\n{$acceptLine}{$bodyLines}Connection: close\r\n\r\n{$payload}");
                $raw = (string) stream_get_contents($connection);
                fclose($connection);

                [$head, $body] = array_pad(explode("\r\n\r\n", $raw, 2), 2, '');
                $lines = explode("\r\n", $head);
                $status = preg_match('#^HTTP/\S+ (\d{3})#', (string) array_shift($lines), $m) === 1 ? (int) $m[1] : 0;
                $headers = [];
                foreach ($lines as $line) {
                    [$key, $value] = array_pad(explode(':', $line, 2), 2, '');
                    $headers[strtolower(trim($key))] = trim($value);
                }
                $json = json_decode($body, true);
                $responses[] = ['status' => $status, 'headers' => $headers, 'body' => is_array($json) ? $json : null, 'raw' => $raw];
            }
        } finally {
            proc_terminate($server);
            proc_close($server);
            @unlink($log);
        }

        return $responses;
    }
}
