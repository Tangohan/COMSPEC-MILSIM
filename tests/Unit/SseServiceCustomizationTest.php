<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\SseWantedNoticeController;
use App\Controllers\Web\SseWantedServiceController;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SseServiceEmblemRepository;
use App\Repositories\SseWantedMetaRepository;
use App\Repositories\SseWantedNoticeRepository;
use App\Repositories\SseWantedSettingsRepository;
use App\Services\Security\FileRateLimiter;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseMemberImageGate;
use App\Services\Sse\SseServiceEmblems;
use App\Services\Sse\SseWantedAccess;
use App\Services\Sse\SseWantedService;
use App\Support\SseAlertReportTo;
use App\Support\SseImageStorage;
use App\Support\SseWantedServiceAdmin;
use App\Support\SseWantedServices;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\MysqlDdlSqlitePdo;
use Tests\Support\SseWantedSqlite;

/**
 * Personnalisation complète des services demandeurs : surcharge d'un service livré et fiche d'un service créé (description,
 * devise, adresse, cases « Afficher sur les PDF »), refus des noms réels sur chaque champ, emblème téléversé (type lu dans le
 * contenu, taille, métadonnées retirées, confirmation obligatoire, route contrôlée, autre communauté introuvable, débit limité),
 * repli générique sans la table, instantané des avis déjà émis, droits, audit, et règles d'interface des vues.
 */
final class SseServiceCustomizationTest extends TestCase
{
    private PDO $pdo;

    private string $private;

    /** @var array<string, mixed> */
    private array $saved = [];

    /** @var list<string> */
    private array $tmp = [];

    /** @var list<string> */
    private array $rateKeys = [];

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite') || !extension_loaded('gd')) {
            self::markTestSkipped('pdo_sqlite et gd requis');
        }
        $this->saved = ['session' => $_SESSION ?? null, 'post' => $_POST ?? [], 'get' => $_GET ?? [], 'server' => $_SERVER, 'files' => $_FILES ?? []];
        \App\Core\Csrf::token();
        $this->open(true);
        $_SESSION = [
            'user_id' => 7, 'tenant_id' => 1, 'callsign' => 'ANALYSTE', '_csrf_token' => 'jeton',
            'sse_tenant_id' => 1, 'sse_guest' => false, 'sse_clearance_until' => time() + 3600, 'sse_access_code_id' => 0,
            'sse_confidentiality_version' => SseAccessCodeService::CONFIDENTIALITY_VERSION, 'sse_confidentiality_tenant_id' => 1, 'sse_confidentiality_code_id' => 0,
        ];
        $_POST = [];
        $_GET = [];
        $_FILES = [];
        Gate::reset();
        SseMemberImageGate::useForSession(null);
        $this->private = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'svc-emblems-' . bin2hex(random_bytes(6));
        mkdir($this->private, 0775, true);
    }

    protected function tearDown(): void
    {
        foreach ($this->tmp as $f) {
            @unlink($f);
        }
        $limiter = new FileRateLimiter();
        foreach ($this->rateKeys as $key) {
            $limiter->clear($key);
        }
        SseWantedSqlite::close();
        SseServiceEmblemRepository::forgetReady();
        SseMemberImageGate::useForSession(null);
        self::rmTree($this->private);
        $_POST = $this->saved['post'];
        $_GET = $this->saved['get'];
        $_FILES = $this->saved['files'];
        $_SERVER = $this->saved['server'];
        if ($this->saved['session'] === null) {
            unset($_SESSION);
        } else {
            $_SESSION = $this->saved['session'];
        }
        Gate::reset();
    }

    // ------------------------------------------------------------------ outils

    /** Base du test ; la table des emblèmes vient du vrai fichier SQL de la migration (étape 4830). */
    private function open(bool $withEmblemTable): void
    {
        $this->pdo = SseWantedSqlite::open();
        $this->pdo->exec("INSERT INTO users (id, tenant_id, steam_id, display_name) VALUES (7, 1, '76561198000000007', 'Vrai Membre')");
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS sse_audit_log (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, actor_user_id INTEGER, actor_label TEXT, action TEXT, object_type TEXT, object_id INTEGER, object_uuid TEXT, reason TEXT, before_json TEXT, after_json TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        if ($withEmblemTable) {
            $scratch = new MysqlDdlSqlitePdo();
            $scratch->exec('CREATE TABLE tenants (id INTEGER PRIMARY KEY, name TEXT, slug TEXT)');
            (new \App\Support\Migrations\SchemaInspector($scratch))->runSqlFile(dirname(__DIR__, 2) . '/migrations/2026_11_20_100_sse_alerts.sql');
            foreach ($scratch->query("SELECT sql FROM sqlite_master WHERE sql IS NOT NULL AND tbl_name = 'sse_service_emblems' ORDER BY type = 'index', name")->fetchAll(PDO::FETCH_COLUMN) as $sql) {
                $this->pdo->exec((string) $sql);
            }
        }
        SseServiceEmblemRepository::forgetReady();
    }

    private static function rmTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }

    /** Gestionnaire de la section (niveau « Gérer ») de la communauté 1. */
    private function admin(int $tenantId = 1, ?int $userId = null): SseWantedAccess
    {
        $userId ??= random_int(100000, 900000);
        $this->rateKeys[] = SseServiceEmblems::rateKey($tenantId, $userId);

        return new SseWantedAccess(false, true, true, true, $tenantId, $userId, 'ANALYSTE', true, true);
    }

    /** Rédacteur : écrit des avis, mais n'a pas le niveau « Gérer ». */
    private static function writer(): SseWantedAccess
    {
        return new SseWantedAccess(false, true, true, true, 1, 8, 'REDACTEUR', true, false);
    }

    private function emblems(?FileRateLimiter $limiter = null): SseServiceEmblems
    {
        return new SseServiceEmblems(null, $this->private, new SseWantedSettingsRepository(), $limiter);
    }

    private function file(string $bytes): string
    {
        $f = (string) tempnam(sys_get_temp_dir(), 'svc');
        file_put_contents($f, $bytes);
        $this->tmp[] = $f;

        return $f;
    }

    /** Image dessinée par code : disque sur fond transparent (PNG, WebP) ou blanc (JPEG). */
    private static function image(string $format, int $side = 300): string
    {
        $im = imagecreatetruecolor($side, $side);
        imagealphablending($im, false);
        imagesavealpha($im, true);
        imagefill($im, 0, 0, (int) imagecolorallocatealpha($im, 255, 255, 255, $format === 'jpeg' ? 0 : 127));
        imagefilledellipse($im, intdiv($side, 2), intdiv($side, 2), (int) ($side * 0.8), (int) ($side * 0.8), (int) imagecolorallocate($im, 22, 78, 84));
        ob_start();
        match ($format) {
            'jpeg' => imagejpeg($im, null, 90),
            'webp' => imagewebp($im, null, 90),
            'gif' => imagegif($im),
            default => imagepng($im),
        };
        $b = (string) ob_get_clean();
        imagedestroy($im);

        return $b;
    }

    /** JPEG portant un bloc EXIF (position et auteur) : il ne doit rien en rester après le téléversement. */
    private static function jpegWithExif(): string
    {
        $jpeg = self::image('jpeg');
        $tiff = "II*\x00\x08\x00\x00\x00\x00\x00\x00\x00\x00\x00" . 'GPS 48.8566N 2.3522E AUTEUR-SECRET';
        $app1 = "Exif\x00\x00" . $tiff;

        return substr($jpeg, 0, 2) . "\xFF\xE1" . pack('n', strlen($app1) + 2) . $app1 . substr($jpeg, 2);
    }

    /** @return array<string, mixed> */
    private static function config(): array
    {
        return SseWantedSettingsRepository::normalize([]);
    }

    /** @return array<string, mixed> */
    private static function form(array $extra = []): array
    {
        return $extra + [
            'fr' => 'Cellule de veille du Détroit', 'en' => 'Strait Watch Cell', 'ac' => 'CVD', 'family' => 'theatre', 'roles' => ['request', 'alert'],
            'auth_fr' => 'État-major de la Task Force Aurore', 'auth_en' => 'Task Force Aurora Headquarters',
            'desc_fr' => "Veille les mouvements du Détroit.\nCentralise les signalements des patrouilles.", 'desc_en' => 'Watches movements in the Strait.',
            'motto' => 'Voir avant, prévenir à temps', 'radio' => 'STRAIT WATCH · canal TAC 41', 'tel' => '+1 555 0142', 'mail' => 'veille@cvd.invalid',
            'address' => "Bâtiment 4, camp Ardent\nRoute du Littoral", 'city' => 'Port-Hélène', 'country' => 'Théâtre de la Corne de l’Est', 'hours' => 'Permanence 24 h/24',
            'web' => 'https://signalement.cvd.invalid', 'print' => ['radio', 'tel', 'address', 'city', 'country', 'hours'],
            'max' => 'confidentiel', 'class' => 'encadrement', 'thr' => 'high', 'exp' => 21, 'threats' => ['medium', 'high'], 'types' => ['poi', 'hvt'],
            'color' => 'teal', 'emblem' => 'ring', 'lang' => ['fr', 'en'], 'enabled' => true,
        ];
    }

    /** Réglages de la communauté 1 enregistrés avec le service « CSV-CVD ». @return array<string, mixed> */
    private function savedConfig(): array
    {
        $r = SseWantedServiceAdmin::create(self::config(), self::form());
        self::assertTrue($r['ok'], implode(' ', $r['errors']));
        (new SseWantedSettingsRepository())->save(1, $r['config'], 7);

        return $r['config'];
    }

    /** @return list<array<string, mixed>> */
    private function audit(): array
    {
        return $this->pdo->query('SELECT * FROM sse_audit_log ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function bytes(Response $resp): string
    {
        $prop = new \ReflectionProperty($resp, 'bodyStream');
        $stream = $prop->getValue($resp);
        if (!is_callable($stream)) {
            return $resp->body();
        }
        ob_start();
        $stream();

        return (string) ob_get_clean();
    }

    private function controller(SseWantedAccess $rights, ?SseServiceEmblems $emblems = null): SseWantedServiceController
    {
        return new SseWantedServiceController(null, null, new SseWantedSettingsRepository(), $rights, $emblems ?? $this->emblems(), null, null, null, static fn (string $tmp): bool => is_file($tmp));
    }

    /** @param array<string, mixed> $post */
    private static function jsonPost(array $post): Request
    {
        $_POST = $post;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        return new Request();
    }

    /** @return array<string, mixed> */
    private static function decoded(Response $r): array
    {
        $data = json_decode($r->body(), true);

        return is_array($data) ? $data : [];
    }

    // ------------------------------------------------------------------ Service créé : tous les champs

    public function testACreatedServiceCarriesDescriptionMottoAddressAndPrintChoices(): void
    {
        $r = SseWantedServiceAdmin::create(self::config(), self::form());
        self::assertTrue($r['ok'], implode(' ', $r['errors']));
        $s = SseWantedServices::find('CSV-CVD', $r['config']);
        self::assertNotNull($s);
        self::assertSame("Veille les mouvements du Détroit.\nCentralise les signalements des patrouilles.", $s['desc_fr']);
        self::assertSame('Watches movements in the Strait.', $s['desc_en']);
        self::assertSame('Voir avant, prévenir à temps', $s['motto']);
        self::assertSame("Bâtiment 4, camp Ardent\nRoute du Littoral", $s['address']);
        self::assertSame('Port-Hélène', $s['city']);
        self::assertSame('Théâtre de la Corne de l’Est', $s['country']);
        self::assertSame('Permanence 24 h/24', $s['hours']);
        self::assertSame('https://signalement.cvd.invalid', $s['web']);
        self::assertSame(['radio', 'tel', 'address', 'city', 'country', 'hours'], $s['print']);
        // Adresse déjà composée : lignes de l'adresse, « localité, pays », horaires.
        self::assertSame("Bâtiment 4, camp Ardent\nRoute du Littoral\nPort-Hélène, Théâtre de la Corne de l’Est\nPermanence 24 h/24", $s['print_address']);
        // Le courriel n'est pas coché : il ne sort ni dans la ligne de coordonnées ni dans le préremplissage.
        self::assertSame('STRAIT WATCH · canal TAC 41 · +1 555 0142', SseWantedServices::contactLine($s));
        self::assertSame('STRAIT WATCH · canal TAC 41 · +1 555 0142', SseWantedServices::prefill($s)['contact_detail']);
        self::assertSame('', $s['emblem_ref']);
        self::assertFalse($s['overridden']);
        // Enregistrés puis relus (JSON de sse_wanted_settings) : rien ne se perd.
        $reloaded = SseWantedSettingsRepository::normalize(json_decode((string) json_encode($r['config']), true));
        self::assertSame($s, SseWantedServices::find('CSV-CVD', $reloaded));
        // Le « service à informer » d'un document reprend description et adresse imprimable.
        $report = SseAlertReportTo::resolve($r['config'], ['service' => 'CSV-CVD'])['report'];
        self::assertSame($s['desc_fr'], $report['desc_fr']);
        self::assertSame($s['print_address'], $report['address']);
        self::assertSame('STRAIT WATCH · canal TAC 41 · +1 555 0142', $report['channel']);
    }

    public function testPrintBoxesDecideWhatIsShownAndDefaultToEverything(): void
    {
        $all = SseWantedServices::normalize(['code' => 'CSV-A', 'fr' => 'Service complet', 'radio' => 'NET 1', 'tel' => '+1 555 0101', 'mail' => 'a@b.invalid', 'address' => 'Camp 1', 'city' => 'Ville', 'hours' => 'Jour']);
        self::assertSame(SseWantedServices::PRINT_KEYS, $all['print'], 'Sans choix : tout est affiché');
        self::assertSame('NET 1 · +1 555 0101 · a@b.invalid', SseWantedServices::contactLine($all));
        self::assertSame("Camp 1\nVille\nJour", $all['print_address']);
        $none = SseWantedServices::normalize(['code' => 'CSV-A', 'fr' => 'Service discret', 'radio' => 'NET 1', 'address' => 'Camp 1', 'print' => []]);
        self::assertSame([], $none['print']);
        self::assertSame('', SseWantedServices::contactLine($none));
        self::assertSame('', $none['print_address']);
        $some = SseWantedServices::normalize(['code' => 'CSV-A', 'fr' => 'Service', 'address' => 'Camp 1', 'city' => 'Ville', 'country' => 'Pays', 'print' => ['country', 'inconnu', 'address']]);
        self::assertSame(['address', 'country'], $some['print'], 'Clés inconnues écartées, ordre fixe');
        self::assertSame("Camp 1\nPays", $some['print_address']);
        self::assertLessThanOrEqual(300, mb_strlen(SseWantedServices::normalize(['code' => 'CSV-A', 'fr' => 'Service', 'address' => str_repeat('a', 400), 'city' => str_repeat('b', 90), 'country' => str_repeat('c', 90), 'hours' => str_repeat('d', 90)])['print_address']));
    }

    // ------------------------------------------------------------------ Service livré : surcharge par communauté

    public function testADeliveredServiceIsFullyCustomizedThenRestored(): void
    {
        $config = self::config();
        $delivered = SseWantedServices::find('SVC-JIFC', $config);
        $edited = SseWantedServiceAdmin::update($config, 'SVC-JIFC', self::form(['fr' => 'Cellule de fusion Aurore', 'en' => 'Aurora Fusion Cell', 'ac' => 'CFA', 'family' => 'command', 'color' => 'plum', 'emblem' => 'cross']));
        self::assertTrue($edited['ok'], implode(' ', $edited['errors']));
        self::assertSame('SVC-JIFC', $edited['code']);
        $s = SseWantedServices::find('SVC-JIFC', $edited['config']);
        self::assertSame('catalogue', $s['source'], 'Un service livré surchargé reste un service livré');
        self::assertTrue($s['overridden']);
        foreach (['fr' => 'Cellule de fusion Aurore', 'en' => 'Aurora Fusion Cell', 'ac' => 'CFA', 'family' => 'command', 'color' => 'plum', 'emblem' => 'cross', 'motto' => 'Voir avant, prévenir à temps',
            'city' => 'Port-Hélène', 'max' => 'confidentiel', 'tel' => '+1 555 0142', 'exp' => 21] as $k => $v) {
            self::assertSame($v, $s[$k], $k);
        }
        // Les réglages ne gardent que la surcharge, jamais une copie du catalogue ; les autres services ne bougent pas.
        self::assertSame([], $edited['config']['services']['custom']);
        self::assertSame(['SVC-JIFC'], array_keys($edited['config']['services']['overrides']));
        self::assertSame(SseWantedServices::find('SVC-CTC', $config), SseWantedServices::find('SVC-CTC', $edited['config']));
        // Une autre communauté (réglages vierges) garde le service livré.
        self::assertSame($delivered['fr'], SseWantedServices::find('SVC-JIFC', self::config())['fr']);
        // Relu depuis le JSON : même service.
        $reloaded = SseWantedSettingsRepository::normalize(json_decode((string) json_encode($edited['config']), true));
        self::assertSame($s, SseWantedServices::find('SVC-JIFC', $reloaded));
        // Le sélecteur du formulaire propose le service personnalisé.
        $item = array_values(array_filter(SseWantedServices::clientData($edited['config'])['items'], static fn (array $i): bool => $i['code'] === 'SVC-JIFC'))[0];
        self::assertSame('Cellule de fusion Aurore', $item['name']);

        // Seul ce qui diffère est gardé : remettre la valeur livrée d'un champ l'efface de la surcharge.
        $back = SseWantedServiceAdmin::update($edited['config'], 'SVC-JIFC', ['fr' => $delivered['fr']] + $s);
        self::assertArrayNotHasKey('fr', $back['config']['services']['overrides']['SVC-JIFC']);

        $reset = SseWantedServiceAdmin::reset($edited['config'], 'SVC-JIFC');
        self::assertTrue($reset['ok']);
        $restored = SseWantedServices::find('SVC-JIFC', $reset['config']);
        self::assertFalse($restored['overridden']);
        self::assertSame($delivered, array_intersect_key($restored, $delivered));
        self::assertSame($delivered['fr'], $restored['fr']);
        self::assertSame([], $reset['config']['services']['overrides']);
        self::assertFalse(SseWantedServiceAdmin::reset($reset['config'], 'SVC-JIFC')['ok'], 'Rien à rétablir');
        self::assertFalse(SseWantedServiceAdmin::reset($config, 'CSV-ABSENT')['ok']);
    }

    public function testVisibilityOrderAndDefaultApplyToDeliveredAndCreatedServices(): void
    {
        $config = SseWantedServiceAdmin::create(self::config(), self::form())['config'];
        $hidden = SseWantedServiceAdmin::update($config, 'SVC-JIFC', ['enabled' => false] + SseWantedServices::find('SVC-JIFC', $config));
        self::assertTrue($hidden['ok'], implode(' ', $hidden['errors']));
        self::assertFalse(SseWantedServices::find('SVC-JIFC', $hidden['config'])['enabled']);
        self::assertSame([], $hidden['config']['services']['overrides'], 'Masquer n’est pas une surcharge');
        $placed = SseWantedServiceAdmin::setPosition($config, 'CSV-CVD', 2);
        self::assertSame('CSV-CVD', array_keys(SseWantedServices::all($placed['config']))[1]);
        $first = SseWantedServiceAdmin::setPosition($config, 'CSV-CVD', 1);
        self::assertSame('CSV-CVD', array_keys(SseWantedServices::all($first['config']))[0]);
        $last = SseWantedServiceAdmin::setPosition($config, 'SVC-JIFC', 999);
        self::assertSame('SVC-JIFC', array_key_last(SseWantedServices::all($last['config'])));
        self::assertFalse(SseWantedServiceAdmin::setPosition($config, 'SVC-INCONNU', 1)['ok']);
        $default = SseWantedServiceAdmin::setDefault($config, 'CSV-CVD');
        self::assertSame('CSV-CVD', SseWantedServices::defaultCode($default['config']));
    }

    // ------------------------------------------------------------------ Garde-fous

    public function testRealOrganisationNamesAreRefusedOnEveryField(): void
    {
        $config = self::config();
        $bad = [
            'fr' => 'Bureau Interpol du Détroit', 'en' => 'Europol Strait Office', 'ac' => 'FBI', 'auth_fr' => 'Sous l’autorité de la DGSE', 'auth_en' => 'Under the Department of Defense',
            'desc_fr' => 'Travaille avec la Gendarmerie nationale.', 'desc_en' => 'Works with the Secret Service.', 'motto' => 'Avec le soutien de l’OTAN',
            'address' => "Quartier général du Pentagon\nAile nord", 'city' => 'Camp Europol', 'country' => 'Zone ONU', 'hours' => 'Permanence NATO', 'mail' => 'contact@interpol.invalid',
            'web' => 'https://europol.invalid', 'radio' => 'CIA ACTUAL',
        ];
        foreach ($bad as $field => $value) {
            $created = SseWantedServiceAdmin::create($config, self::form([$field => $value]));
            self::assertFalse($created['ok'], 'création : ' . $field);
            self::assertStringContainsString('organisation réelle', implode(' ', $created['errors']), 'création : ' . $field);
            self::assertSame($config, $created['config']);
            // La surcharge d'un service livré passe le même contrôle.
            $over = SseWantedServiceAdmin::update($config, 'SVC-JIFC', [$field => $value] + SseWantedServices::find('SVC-JIFC', $config));
            self::assertFalse($over['ok'], 'surcharge : ' . $field);
            self::assertStringContainsString('organisation réelle', implode(' ', $over['errors']), 'surcharge : ' . $field);
            self::assertSame([], $over['config']['services']['overrides']);
        }
    }

    public function testContactsStayOnReservedNumbersAndDomainsAndTextsAreBounded(): void
    {
        $config = self::config();
        $refused = [
            ['tel' => '+33 1 42 00 00 00'], ['tel' => '0612345678'], ['mail' => 'chef@service.gouv.fr'], ['mail' => 'chef@exemple.com'],
            ['web' => 'https://www.exemple.com/signaler'], ['web' => 'javascript:alert(1)'], ['web' => 'signalement'],
            ['address' => 'Camp <b>Ardent</b>'], ['desc_fr' => 'Texte {{ modèle }}'], ['motto' => '<script>'],
            ['address' => "1\n2\n3\n4\n5"], ['desc_fr' => "a\nb\nc\nd"], ['desc_fr' => str_repeat('x', 301)], ['motto' => str_repeat('x', 121)],
            ['city' => str_repeat('x', 51)], ['hours' => str_repeat('x', 61)], ['address' => str_repeat('x', 131)],
        ];
        foreach ($refused as $bad) {
            $r = SseWantedServiceAdmin::create($config, self::form($bad));
            self::assertFalse($r['ok'], (string) json_encode($bad, JSON_UNESCAPED_UNICODE));
            self::assertNotSame([], $r['errors']);
        }
        foreach ([['web' => ''], ['web' => '/atak/sse/signalement/abc'], ['web' => 'https://signalement.cvd.test/depot'], ['tel' => '555-0199'], ['mail' => 'veille@cvd.example'], ['address' => ''], ['desc_fr' => '']] as $good) {
            $r = SseWantedServiceAdmin::create($config, self::form($good));
            self::assertTrue($r['ok'], json_encode($good) . ' : ' . implode(' ', $r['errors']));
        }
    }

    // ------------------------------------------------------------------ Emblème téléversé

    public function testAnEmblemIsReencodedResizedStoredPrivatelyAndConfirmedByItsAuthor(): void
    {
        $this->savedConfig();
        $admin = $this->admin(1, 7);
        $svc = $this->emblems();
        $res = $svc->store(1, 'CSV-CVD', $this->file(self::image('png', 900)), true, $admin);
        self::assertTrue($res['ok'], $res['error'] ?? '');
        self::assertSame(1, $res['version']);
        self::assertSame('svc:CSV-CVD:1', $res['ref']);
        self::assertSame(512, $res['width']);

        $row = $this->pdo->query("SELECT * FROM sse_service_emblems WHERE tenant_id = 1 AND service_code = 'CSV-CVD'")->fetch(PDO::FETCH_ASSOC);
        self::assertMatchesRegularExpression('#^sse-private/sse-service-emblems/1_[a-f0-9]{16}\.png$#', (string) $row['path']);
        self::assertMatchesRegularExpression('#^sse-private/sse-service-emblems/1_[a-f0-9]{16}\.png$#', (string) $row['thumb_path']);
        self::assertSame('image/png', $row['mime']);
        self::assertSame(512, (int) $row['width']);
        self::assertSame(7, (int) $row['confirmed_by_user_id']);
        self::assertSame('ANALYSTE', $row['confirmed_by_label']);
        self::assertNotNull($row['confirmed_at']);
        self::assertTrue(SseImageStorage::isPrivatePath((string) $row['path']));
        self::assertFileExists($this->private . '/sse-service-emblems/' . basename((string) $row['path']));

        // Octets prêts pour le PDF : PNG réencodé, 512 px au plus, transparence gardée ; miniature de 96 px.
        $bytes = $svc->bytesFor(1, 'CSV-CVD');
        self::assertIsString($bytes);
        self::assertStringStartsWith("\x89PNG\r\n\x1a\n", $bytes);
        $im = imagecreatefromstring($bytes);
        self::assertSame(512, imagesx($im));
        self::assertSame(127, (imagecolorat($im, 2, 2) >> 24) & 0x7F, 'Le coin reste transparent');
        self::assertSame(0, (imagecolorat($im, 256, 256) >> 24) & 0x7F, 'Le centre reste opaque');
        $thumb = $svc->resolve(1, 'CSV-CVD', true);
        self::assertNotNull($thumb);
        self::assertSame(96, getimagesize($thumb['file'])[0]);
        self::assertSame('svc:CSV-CVD:1', $svc->refFor(1, 'CSV-CVD'));
        // Les services de la communauté portent l'étiquette, sans requête : les documents la lisent.
        $config = (new SseWantedSettingsRepository())->get(1);
        self::assertSame('svc:CSV-CVD:1', SseWantedServices::find('CSV-CVD', $config)['emblem_ref']);
        self::assertSame('svc:CSV-CVD:1', SseAlertReportTo::resolve($config, ['service' => 'CSV-CVD'])['report']['emblem_ref']);
        self::assertSame('', SseWantedServices::find('SVC-JIFC', $config)['emblem_ref']);

        // Journal d'audit : auteur, confirmation, règle.
        $log = $this->audit();
        $last = $log[count($log) - 1];
        self::assertSame('wanted.services.emblem_set', $last['action']);
        self::assertSame('ANALYSTE', $last['actor_label']);
        self::assertSame(7, (int) $last['actor_user_id']);
        $after = json_decode((string) $last['after_json'], true);
        self::assertTrue($after['confirmed']);
        self::assertSame('ANALYSTE', $after['confirmed_by']);
        self::assertSame(SseServiceEmblems::RULE, $after['rule']);
        self::assertNull($last['before_json']);

        // Remplacement : la version monte, les anciens fichiers partent.
        $old = $this->private . '/sse-service-emblems/' . basename((string) $row['path']);
        $again = $svc->store(1, 'CSV-CVD', $this->file(self::image('jpeg')), true, $admin);
        self::assertTrue($again['ok'], $again['error'] ?? '');
        self::assertSame(2, $again['version']);
        self::assertFalse(is_file($old));
        self::assertStringStartsWith("\xFF\xD8\xFF", (string) $svc->bytesFor(1, 'CSV-CVD'));
        self::assertSame('svc:CSV-CVD:2', SseWantedServices::find('CSV-CVD', (new SseWantedSettingsRepository())->get(1))['emblem_ref']);
        $log = $this->audit();
        self::assertSame(1, json_decode((string) $log[count($log) - 1]['before_json'], true)['version'], 'L’ancien emblème est au journal');
        // Un service livré reçoit son emblème de la même façon.
        self::assertTrue($svc->store(1, 'SVC-JIFC', $this->file(self::image('png')), true, $admin)['ok']);
        self::assertSame('svc:SVC-JIFC:1', $svc->refFor(1, 'SVC-JIFC'));
    }

    public function testTheTypeIsReadFromTheContentAndMetadataAreRemoved(): void
    {
        $this->savedConfig();
        $admin = $this->admin();
        $svc = $this->emblems();
        $svg = '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" width="64" height="64"><script>alert(1)</script><circle cx="32" cy="32" r="30"/></svg>';
        foreach (['svg' => $svg, 'texte' => 'pas une image', 'gif' => self::image('gif'), 'php' => "<?php echo 1; ?>\x89PNG", 'faux png' => "\x89PNG\r\n\x1a\n" . str_repeat('x', 400), 'vide' => ''] as $name => $bytes) {
            $r = $svc->store(1, 'CSV-CVD', $this->file($bytes), true, $admin);
            self::assertFalse($r['ok'], $name);
            self::assertStringContainsString('Image refusée', $r['error'], $name);
        }
        self::assertNull($svc->bytesFor(1, 'CSV-CVD'));
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_service_emblems')->fetchColumn());
        self::assertSame([], glob($this->private . '/sse-service-emblems/*') ?: []);

        // Taille : 1 Mo au plus ; côté : 32 px au moins.
        $big = self::image('png') . str_repeat("\0", SseServiceEmblems::MAX_BYTES);
        self::assertStringContainsString('1 Mo', $svc->store(1, 'CSV-CVD', $this->file($big), true, $admin)['error']);
        self::assertStringContainsString('32 px', $svc->store(1, 'CSV-CVD', $this->file(self::image('png', 20)), true, $admin)['error']);

        // EXIF : présent à l'entrée, absent à la sortie (réencodage).
        $source = self::jpegWithExif();
        self::assertStringContainsString('Exif', $source);
        self::assertStringContainsString('AUTEUR-SECRET', $source);
        $r = $svc->store(1, 'CSV-CVD', $this->file($source), true, $admin);
        self::assertTrue($r['ok'], $r['error'] ?? '');
        $stored = (string) $svc->bytesFor(1, 'CSV-CVD');
        self::assertStringStartsWith("\xFF\xD8\xFF", $stored);
        self::assertStringNotContainsString('Exif', $stored);
        self::assertStringNotContainsString('AUTEUR-SECRET', $stored);
        self::assertStringNotContainsString('GPS', $stored);
        $thumb = $svc->resolve(1, 'CSV-CVD', true);
        self::assertStringNotContainsString('AUTEUR-SECRET', (string) file_get_contents($thumb['file']));

        // WebP accepté, rangé en PNG (transparence gardée).
        if (function_exists('imagewebp')) {
            $r = $svc->store(1, 'SVC-CTC', $this->file(self::image('webp')), true, $admin);
            self::assertTrue($r['ok'], $r['error'] ?? '');
            self::assertStringStartsWith("\x89PNG\r\n\x1a\n", (string) $svc->bytesFor(1, 'SVC-CTC'));
        }
    }

    public function testTheConfirmationBoxIsMandatoryAndOnlyManagersUpload(): void
    {
        $this->savedConfig();
        $svc = $this->emblems();
        $png = self::image('png');
        $r = $svc->store(1, 'CSV-CVD', $this->file($png), false, $this->admin());
        self::assertFalse($r['ok']);
        self::assertStringContainsString('Cochez la confirmation', $r['error']);
        self::assertFalse($svc->store(1, 'CSV-CVD', $this->file($png), true, self::writer())['ok'], 'Le niveau « Gérer » est requis');
        self::assertFalse($svc->store(1, 'CSV-CVD', $this->file($png), true, new SseWantedAccess(true, false, false, false, 1, null, '', true, false))['ok'], 'Invité');
        self::assertFalse($svc->store(1, 'CSV-CVD', $this->file($png), true, $this->admin(2))['ok'], 'Gestionnaire d’une autre communauté');
        self::assertFalse($svc->store(2, 'CSV-CVD', $this->file($png), true, $this->admin(2))['ok'], 'Le service n’existe pas dans l’autre communauté');
        self::assertFalse($svc->store(1, 'CSV-INCONNU', $this->file($png), true, $this->admin())['ok']);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_service_emblems')->fetchColumn());
        self::assertSame([], $this->audit(), 'Rien au journal sans emblème posé');

        // Un gestionnaire retire un emblème : fichiers, ligne et étiquette partent, le retrait est journalisé.
        $admin = $this->admin();
        self::assertTrue($svc->store(1, 'CSV-CVD', $this->file($png), true, $admin)['ok']);
        self::assertFalse($svc->remove(1, 'CSV-CVD', self::writer())['ok']);
        self::assertFalse($svc->remove(1, 'CSV-CVD', $this->admin(2))['ok']);
        self::assertNotNull($svc->bytesFor(1, 'CSV-CVD'));
        self::assertTrue($svc->remove(1, 'CSV-CVD', $admin)['ok']);
        self::assertNull($svc->bytesFor(1, 'CSV-CVD'));
        self::assertSame('', $svc->refFor(1, 'CSV-CVD'));
        self::assertSame([], glob($this->private . '/sse-service-emblems/*') ?: []);
        self::assertSame('', SseWantedServices::find('CSV-CVD', (new SseWantedSettingsRepository())->get(1))['emblem_ref']);
        $log = $this->audit();
        self::assertSame('wanted.services.emblem_removed', $log[count($log) - 1]['action']);
        self::assertFalse($svc->remove(1, 'CSV-CVD', $admin)['ok'], 'Plus rien à retirer');
    }

    public function testUploadsAreRateLimitedPerManager(): void
    {
        $this->savedConfig();
        $admin = $this->admin();
        $svc = $this->emblems(new FileRateLimiter());
        $png = self::image('png', 64);
        for ($i = 0; $i < SseServiceEmblems::RATE_MAX; $i++) {
            $r = $svc->store(1, 'CSV-CVD', $this->file($png), true, $admin);
            self::assertTrue($r['ok'], 'envoi ' . $i . ' : ' . ($r['error'] ?? ''));
        }
        $blocked = $svc->store(1, 'CSV-CVD', $this->file($png), true, $admin);
        self::assertFalse($blocked['ok']);
        self::assertStringContainsString('Trop de téléversements', $blocked['error']);
        self::assertSame(SseServiceEmblems::RATE_MAX, (int) $this->pdo->query("SELECT version FROM sse_service_emblems WHERE service_code = 'CSV-CVD'")->fetchColumn());
        // Un autre gestionnaire n'est pas freiné par le premier.
        self::assertTrue($svc->store(1, 'CSV-CVD', $this->file($png), true, $this->admin())['ok']);
    }

    public function testTheControlledRouteServesTheEmblemToItsCommunityOnly(): void
    {
        $this->savedConfig();
        $svc = $this->emblems();
        self::assertTrue($svc->store(1, 'CSV-CVD', $this->file(self::image('png')), true, $this->admin())['ok']);
        $reader = new SseWantedAccess(false, false, false, false, 1, 9, 'LECTEUR', true, false);
        $full = $this->controller($reader, $svc)->emblem(new Request(), ['code' => 'CSV-CVD']);
        self::assertSame(200, $full->statusCode());
        self::assertSame('image/png', $full->headerValue('Content-Type'));
        self::assertSame('nosniff', $full->headerValue('X-Content-Type-Options'));
        self::assertStringContainsString('private', (string) $full->headerValue('Cache-Control'));
        self::assertStringStartsWith("\x89PNG\r\n\x1a\n", self::bytes($full));
        $_GET = ['v' => 'mini'];
        $mini = $this->controller($reader, $svc)->emblem(new Request(), ['code' => 'csv-cvd']);
        self::assertSame(200, $mini->statusCode());
        self::assertLessThan(strlen(self::bytes($full)), strlen(self::bytes($mini)));
        $_GET = [];
        // Adresse contrôlée, jamais un fichier public ni un nom devinable.
        self::assertStringEndsWith('atak/sse/avis/services/CSV-CVD/embleme?v=mini&e=1', SseServiceEmblems::url('CSV-CVD', true, 1));

        // Autre communauté, lecteur sans droit, code inconnu ou tordu : 404 identique.
        $foreign = new SseWantedAccess(false, true, true, true, 2, 7, 'AUTRE', true, true);
        foreach ([[$foreign, 'CSV-CVD'], [new SseWantedAccess(false, false, false, false, 1, 7, '', false), 'CSV-CVD'], [$reader, 'SVC-JIFC'], [$reader, 'CSV-ABSENT'], [$reader, '../../etc/passwd'], [$reader, '']] as [$who, $code]) {
            $r = $this->controller($who, $svc)->emblem(new Request(), ['code' => $code]);
            self::assertSame(404, $r->statusCode(), $code);
            self::assertSame('Not found', $r->body());
        }
        self::assertNull($svc->bytesFor(2, 'CSV-CVD'));
        self::assertSame('', $svc->refFor(2, 'CSV-CVD'));
        // Une ligne qui pointerait le fichier d'une autre communauté n'est jamais servie.
        $path = (string) $this->pdo->query("SELECT path FROM sse_service_emblems WHERE service_code = 'CSV-CVD'")->fetchColumn();
        $this->pdo->prepare("INSERT INTO sse_service_emblems (tenant_id, service_code, path, thumb_path, mime, width, height, bytes, version, created_at, updated_at) VALUES (2, 'SVC-JIFC', :p, :p2, 'image/png', 1, 1, 1, 1, '2026-11-15 10:00:00', '2026-11-15 10:00:00')")->execute(['p' => $path, 'p2' => $path]);
        self::assertNull($svc->bytesFor(2, 'SVC-JIFC'));
        self::assertSame(404, $this->controller($foreign, $svc)->emblem(new Request(), ['code' => 'SVC-JIFC'])->statusCode());
    }

    public function testWithoutTheTableUploadsAreClosedAndEverythingFallsBackToTheGenericEmblem(): void
    {
        SseWantedSqlite::close();
        $this->open(false);
        $this->savedConfig();
        self::assertFalse(SseServiceEmblems::ready());
        $svc = $this->emblems();
        $r = $svc->store(1, 'CSV-CVD', $this->file(self::image('png')), true, $this->admin());
        self::assertFalse($r['ok']);
        self::assertSame(SseServiceEmblems::NOT_READY, $r['error']);
        self::assertNull($svc->bytesFor(1, 'CSV-CVD'));
        self::assertSame('', $svc->refFor(1, 'CSV-CVD'));
        self::assertSame([], $svc->listFor(1));
        self::assertNull($svc->metaFor(1, 'CSV-CVD'));
        self::assertFalse($svc->remove(1, 'CSV-CVD', $this->admin())['ok']);
        $svc->purge(1, 'CSV-CVD');
        self::assertSame(404, $this->controller($this->admin(), $svc)->emblem(new Request(), ['code' => 'CSV-CVD'])->statusCode());
        self::assertSame('', SseWantedServices::find('CSV-CVD', (new SseWantedSettingsRepository())->get(1))['emblem_ref']);
        // L'écran s'affiche : message clair, emblème générique vectoriel, aucun champ de téléversement.
        Gate::getInstance()->setPermissions(['admin.access', 'atak.sse.case.manage', 'atak.sse.clearance.confidentiel', 'atak.sse.clearance.encadrement']);
        $html = (new SseWantedNoticeController(null, null, null, null, null, null, null, $this->admin()))->settings(new Request())->body();
        self::assertStringContainsString('data-svc-noemblem', $html);
        self::assertStringContainsString('class="svc-emblem"', $html);
        self::assertStringNotContainsString('data-svc-file', $html);
    }

    // ------------------------------------------------------------------ Contrôleur : droits, jeton, audit

    public function testWritesNeedTheTokenAndTheManageLevelAndAreAudited(): void
    {
        $this->savedConfig();
        $form = ['fr' => 'Cellule de veille Aurore', 'position' => '1', 'is_default' => '1', 'default_set' => '1', 'print_set' => '1', 'enabled' => '1'] + self::form();
        // Sans jeton, ou jeton faux : refus, rien n'est écrit.
        foreach ([[], ['_csrf_token' => ''], ['_csrf_token' => 'faux']] as $token) {
            $r = $this->controller($this->admin())->update(self::jsonPost($token + $form), ['code' => 'CSV-CVD']);
            self::assertSame(419, $r->statusCode());
            self::assertFalse(self::decoded($r)['ok']);
        }
        // Rédacteur sans le niveau « Gérer » : refus.
        $r = $this->controller(self::writer())->update(self::jsonPost(['_csrf_token' => 'jeton'] + $form), ['code' => 'CSV-CVD']);
        self::assertSame(403, $r->statusCode());
        self::assertSame([SseWantedNoticeController::NEED_ADMIN], self::decoded($r)['errors']);
        foreach (['create', 'reset', 'emblemUpload', 'emblemRemove'] as $method) {
            self::assertSame(403, $this->controller(self::writer())->{$method}(self::jsonPost(['_csrf_token' => 'jeton'] + $form), ['code' => 'CSV-CVD'])->statusCode(), $method);
            self::assertSame(419, $this->controller($this->admin())->{$method}(self::jsonPost($form), ['code' => 'CSV-CVD'])->statusCode(), $method);
        }
        self::assertSame('Cellule de veille du Détroit', SseWantedServices::find('CSV-CVD', (new SseWantedSettingsRepository())->get(1))['fr']);
        self::assertSame([], $this->audit());

        // Gestionnaire : enregistré dans SA communauté (celle de la session), ancien et nouveau au journal.
        $r = $this->controller($this->admin())->update(self::jsonPost(['_csrf_token' => 'jeton', 'tenant_id' => '2'] + $form), ['code' => 'CSV-CVD']);
        self::assertSame(200, $r->statusCode(), $r->body());
        self::assertTrue(self::decoded($r)['ok']);
        $config = (new SseWantedSettingsRepository())->get(1);
        self::assertSame('Cellule de veille Aurore', SseWantedServices::find('CSV-CVD', $config)['fr']);
        self::assertSame('CSV-CVD', array_keys(SseWantedServices::all($config))[0], 'Rang demandé');
        self::assertSame('CSV-CVD', SseWantedServices::defaultCode($config));
        self::assertNull(SseWantedServices::find('CSV-CVD', (new SseWantedSettingsRepository())->get(2)), 'Rien dans la communauté citée par la requête');
        $log = $this->audit();
        $last = $log[count($log) - 1];
        self::assertSame('wanted.services.update', $last['action']);
        self::assertSame('ANALYSTE', $last['actor_label']);
        self::assertSame('Cellule de veille du Détroit', json_decode((string) $last['before_json'], true)['fr']);
        self::assertSame('Cellule de veille Aurore', json_decode((string) $last['after_json'], true)['fr']);

        // Erreur de saisie : message dans la fenêtre (JSON), rien n'est écrit.
        $r = $this->controller($this->admin())->update(self::jsonPost(['_csrf_token' => 'jeton', 'ac' => 'FBI'] + $form), ['code' => 'CSV-CVD']);
        self::assertSame(422, $r->statusCode());
        self::assertStringContainsString('organisation réelle', implode(' ', self::decoded($r)['errors']));
        self::assertSame('CVD', SseWantedServices::find('CSV-CVD', (new SseWantedSettingsRepository())->get(1))['ac']);

        // Service livré : surcharge puis retour aux valeurs livrées, par les routes.
        $jifc = ['fr' => 'Cellule de fusion Aurore', 'enabled' => '1', 'print_set' => '1'] + SseWantedServices::find('SVC-JIFC', $config);
        $r = $this->controller($this->admin())->update(self::jsonPost(['_csrf_token' => 'jeton'] + $jifc), ['code' => 'SVC-JIFC']);
        self::assertSame(200, $r->statusCode(), $r->body());
        self::assertSame('Cellule de fusion Aurore', SseWantedServices::find('SVC-JIFC', (new SseWantedSettingsRepository())->get(1))['fr']);
        $r = $this->controller($this->admin())->reset(self::jsonPost(['_csrf_token' => 'jeton']), ['code' => 'SVC-JIFC']);
        self::assertSame(200, $r->statusCode(), $r->body());
        self::assertSame('Cellule interarmées de fusion du renseignement', SseWantedServices::find('SVC-JIFC', (new SseWantedSettingsRepository())->get(1))['fr']);
        $log = $this->audit();
        self::assertSame('wanted.services.reset', $log[count($log) - 1]['action']);
        self::assertSame('Cellule de fusion Aurore', json_decode((string) $log[count($log) - 1]['before_json'], true)['fr']);

        // Création par la fenêtre.
        $r = $this->controller($this->admin())->create(self::jsonPost(['_csrf_token' => 'jeton', 'fr' => 'Bureau des liaisons du Littoral', 'en' => 'Littoral Liaison Office', 'ac' => 'BLL'] + $form), []);
        self::assertSame(200, $r->statusCode(), $r->body());
        self::assertSame('CSV-BLL', self::decoded($r)['code']);
        // Sans script : retour à la page avec le message.
        $_POST = ['_csrf_token' => 'jeton'] + $form;
        unset($_SERVER['HTTP_ACCEPT']);
        $plain = $this->controller(self::writer())->update(new Request(), ['code' => 'CSV-CVD']);
        self::assertSame(302, $plain->statusCode());
        self::assertSame(SseWantedNoticeController::NEED_ADMIN, Session::getFlash('error'));
    }

    public function testTheUploadRouteChecksTheFileAndTheConfirmation(): void
    {
        $this->savedConfig();
        $svc = $this->emblems();
        $admin = $this->admin();
        $file = $this->file(self::image('png'));
        $_FILES = ['emblem_file' => ['name' => 'insigne.svg', 'type' => 'image/svg+xml', 'tmp_name' => $file, 'error' => UPLOAD_ERR_OK, 'size' => filesize($file)]];
        // Sans la case : refus.
        $r = $this->controller($admin, $svc)->emblemUpload(self::jsonPost(['_csrf_token' => 'jeton']), ['code' => 'CSV-CVD']);
        self::assertSame(422, $r->statusCode());
        self::assertStringContainsString('Cochez la confirmation', implode(' ', self::decoded($r)['errors']));
        // Avec la case : accepté (le nom et le type annoncés ne comptent pas, le contenu est un PNG).
        $r = $this->controller($admin, $svc)->emblemUpload(self::jsonPost(['_csrf_token' => 'jeton', 'emblem_confirm' => '1']), ['code' => 'CSV-CVD']);
        self::assertSame(200, $r->statusCode(), $r->body());
        $data = self::decoded($r);
        self::assertSame(1, $data['version']);
        self::assertStringContainsString('atak/sse/avis/services/CSV-CVD/embleme', $data['url']);
        // Contenu SVG sous un nom PNG : refus.
        $bad = $this->file('<svg xmlns="http://www.w3.org/2000/svg"/>');
        $_FILES = ['emblem_file' => ['name' => 'embleme.png', 'type' => 'image/png', 'tmp_name' => $bad, 'error' => UPLOAD_ERR_OK, 'size' => filesize($bad)]];
        $r = $this->controller($admin, $svc)->emblemUpload(self::jsonPost(['_csrf_token' => 'jeton', 'emblem_confirm' => '1']), ['code' => 'CSV-CVD']);
        self::assertSame(422, $r->statusCode());
        // Aucun fichier, fichier trop gros côté PHP.
        $_FILES = [];
        self::assertSame(422, $this->controller($admin, $svc)->emblemUpload(self::jsonPost(['_csrf_token' => 'jeton', 'emblem_confirm' => '1']), ['code' => 'CSV-CVD'])->statusCode());
        $_FILES = ['emblem_file' => ['name' => 'a.png', 'type' => 'image/png', 'tmp_name' => '', 'error' => UPLOAD_ERR_INI_SIZE, 'size' => 0]];
        $r = $this->controller($admin, $svc)->emblemUpload(self::jsonPost(['_csrf_token' => 'jeton', 'emblem_confirm' => '1']), ['code' => 'CSV-CVD']);
        self::assertStringContainsString('1 Mo', implode(' ', self::decoded($r)['errors']));
        // Retrait par la route.
        $r = $this->controller($admin, $svc)->emblemRemove(self::jsonPost(['_csrf_token' => 'jeton']), ['code' => 'CSV-CVD']);
        self::assertSame(200, $r->statusCode(), $r->body());
        self::assertNull($svc->bytesFor(1, 'CSV-CVD'));
    }

    // ------------------------------------------------------------------ Avis déjà émis

    public function testIssuedNoticesKeepTheirSnapshotUntilAnExplicitUpdate(): void
    {
        $config = $this->savedConfig();
        Gate::getInstance()->setPermissions(['admin.access', 'atak.sse.case.manage', 'atak.sse.clearance.confidentiel', 'atak.sse.clearance.encadrement']);
        $admin = $this->admin(1, 7);
        $iid = (new SseIdentityRepository())->create(1, ['last_name' => 'Karimzadeh', 'first_name' => 'Élodie', 'sex' => 'F', 'birth_date' => '1984-03-12', 'nationality' => 'TAK']);
        $placed = (new SseWantedService())->place(1, $iid, ['notice_type' => 'poi', 'threat_level' => 'medium', 'armed' => 'no', 'reason' => 'Contact connu d’une cible recherchée.', 'service_code' => 'CSV-CVD'], $admin);
        self::assertTrue($placed['ok'] ?? false, json_encode($placed, JSON_UNESCAPED_UNICODE));
        $id = (int) $placed['id'];
        $meta = new SseWantedMetaRepository();
        $notices = new SseWantedNoticeRepository();
        $before = $meta->forNotice(1, $id)['service'];
        self::assertSame('Cellule de veille du Détroit', $before['fr']);
        $labelBefore = (string) $notices->find(1, $id)['contact_label'];

        // Le service change (nom, sigle, couleur, canal) : l'avis émis ne bouge pas.
        $r = $this->controller($admin)->update(self::jsonPost(['_csrf_token' => 'jeton', 'fr' => 'Cellule de veille Aurore', 'ac' => 'CVA', 'color' => 'plum', 'radio' => 'AURORA WATCH · canal TAC 9', 'print_set' => '1', 'enabled' => '1'] + self::form()), ['code' => 'CSV-CVD']);
        self::assertSame(200, $r->statusCode(), $r->body());
        self::assertSame($before, $meta->forNotice(1, $id)['service'], 'Instantané inchangé');
        self::assertSame($labelBefore, (string) $notices->find(1, $id)['contact_label']);

        // Action explicite : jeton et niveau « Gérer » requis, avis de la communauté de la session seulement.
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        unset($_SERVER['HTTP_ACCEPT']);
        self::assertSame(302, $this->controller($admin)->noticeRefresh(new Request(), ['id' => (string) $id])->statusCode());
        self::assertSame(SseWantedNoticeController::TOKEN_EXPIRED, Session::getFlash('error'));
        $_POST = ['_csrf_token' => 'jeton'];
        $this->controller(self::writer())->noticeRefresh(new Request(), ['id' => (string) $id]);
        self::assertSame(SseWantedNoticeController::NEED_ADMIN, Session::getFlash('error'));
        $this->controller($this->admin(2))->noticeRefresh(new Request(), ['id' => (string) $id]);
        self::assertSame('Avis introuvable.', Session::getFlash('error'));
        $this->controller($admin)->noticeRefresh(new Request(), ['id' => '99999']);
        self::assertSame('Avis introuvable.', Session::getFlash('error'));
        self::assertSame($before, $meta->forNotice(1, $id)['service']);

        $ok = $this->controller($admin)->noticeRefresh(new Request(), ['id' => (string) $id]);
        self::assertSame(302, $ok->statusCode());
        self::assertStringContainsString('Service mis à jour', (string) Session::getFlash('success'));
        $after = $meta->forNotice(1, $id)['service'];
        self::assertSame('Cellule de veille Aurore', $after['fr']);
        self::assertSame('CVA', $after['ac']);
        self::assertSame(SseWantedServices::palette()['plum'], $after['color']);
        $notice = $notices->find(1, $id);
        self::assertStringContainsString('Cellule de veille Aurore (CVA)', (string) $notice['contact_label']);
        self::assertStringContainsString('AURORA WATCH', (string) $notice['contact_detail']);
        $log = $this->audit();
        $last = $log[count($log) - 1];
        self::assertSame('wanted.services.notice_refresh', $last['action']);
        self::assertSame($id, (int) $last['object_id']);
        self::assertSame('Cellule de veille du Détroit', json_decode((string) $last['before_json'], true)['service']['fr']);
        self::assertSame('Cellule de veille Aurore', json_decode((string) $last['after_json'], true)['service']['fr']);
        unset($config);
    }

    // ------------------------------------------------------------------ Écran

    public function testTheSettingsScreenListsServicesWithTheirEmblemAndStaysReadOnlyWithoutTheManageLevel(): void
    {
        $this->savedConfig();
        Gate::getInstance()->setPermissions(['admin.access', 'atak.sse.case.manage', 'atak.sse.clearance.confidentiel', 'atak.sse.clearance.encadrement']);
        self::assertTrue($this->emblems()->store(1, 'CSV-CVD', $this->file(self::image('png')), true, $this->admin())['ok']);
        $html = (new SseWantedNoticeController(null, null, null, null, null, null, null, $this->admin()))->settings(new Request())->body();
        self::assertSame(count(SseWantedServices::catalogue()) + 1, substr_count($html, 'data-svc-row'));
        self::assertStringContainsString('atak/sse/avis/services/CSV-CVD/embleme?v=mini', html_entity_decode($html));
        self::assertStringContainsString(htmlspecialchars(SseServiceEmblems::RULE, ENT_QUOTES, 'UTF-8'), $html);
        self::assertStringContainsString('data-svc-confirm', $html);
        foreach (['Identité', 'Emblème', 'Coordonnées', 'Documents', 'Avancé'] as $tab) {
            self::assertMatchesRegularExpression('~<button class="svc-tab"[^>]*>' . preg_quote($tab, '~') . '</button>~u', $html);
        }
        self::assertStringContainsString('data-svc-new', $html);
        // Lecture seule : aucune action d'écriture, champs figés.
        $read = (new SseWantedNoticeController(null, null, null, null, null, null, null, self::writer()))->settings(new Request())->body();
        self::assertStringContainsString('data-svc-row', $read);
        self::assertStringNotContainsString('data-svc-new', $read);
        self::assertStringNotContainsString('data-svc-save', $read);
        self::assertStringNotContainsString('data-svc-file', $read);
        self::assertStringNotContainsString('name="op" value="toggle"', $read);
        self::assertMatchesRegularExpression('~<fieldset class="svc-fields" data-svc-fields\s+disabled>~', $read);
    }

    public function testViewsFollowTheInterfaceRules(): void
    {
        $root = dirname(__DIR__, 2);
        $views = ['views/atak/sse/papers/_wanted_services.php', 'views/atak/sse/papers/_service_modal.php', 'views/atak/sse/papers/_service_emblem.php'];
        $assets = ['public/assets/css/sse-services.css', 'public/assets/js/sse-services.js'];
        foreach (array_merge($views, $assets) as $file) {
            $src = (string) file_get_contents($root . '/' . $file);
            // Jamais le système de design « SSE DS », sa police ni ses classes.
            self::assertDoesNotMatchRegularExpression('/sse-ds|Archivo|class="[^"]*\bsse-(?!notify)[a-z]/i', $src, $file);
            // Aucun petit libellé au-dessus d'un titre.
            self::assertDoesNotMatchRegularExpression('/eyebrow|kicker|overline|surtitre/i', $src, $file);
            // Aucune fenêtre du navigateur, aucun toast.
            self::assertDoesNotMatchRegularExpression('/\b(?:alert|confirm|prompt)\s*\(|toast/i', $src, $file);
        }
        foreach ($views as $file) {
            $src = (string) file_get_contents($root . '/' . $file);
            // Vocabulaire : rien ne dit que le service est inventé.
            self::assertDoesNotMatchRegularExpression('/ficti|fiction|jeu de rôle|\bRP\b|simulation|spécimen/iu', $src, $file);
            // Un titre n'est jamais précédé d'un libellé dans le même bloc.
            self::assertDoesNotMatchRegularExpression('~<(?:span|small|p|div)[^>]*>[^<]{1,40}</(?:span|small|p|div)>\s*<h[1-6]~u', $src, $file);
        }
        $modal = (string) file_get_contents($root . '/views/atak/sse/papers/_service_modal.php');
        // Cinq onglets, et sous chaque titre de section une ligne de description.
        self::assertSame(5, preg_match_all('~<div class="svc-pane__head"><h3>[^<]+</h3><p>[^<]+</p></div>~u', $modal));
        self::assertSame(5, preg_match_all('~<section class="svc-pane"~', $modal));
        $css = (string) file_get_contents($root . '/public/assets/css/sse-services.css');
        // Texte : jamais sous 13 px (0,8125rem).
        preg_match_all('/font-size:\s*([0-9.]+)(rem|px|em)/', $css, $m, PREG_SET_ORDER);
        self::assertNotSame([], $m);
        foreach ($m as [$all, $size, $unit]) {
            self::assertGreaterThanOrEqual(13.0, (float) $size * ($unit === 'px' ? 1 : 16), $all);
        }
        // Cibles : aucune hauteur minimale de commande sous 44 px.
        preg_match_all('/\.svc-(?:tab|sort|pick|seg button|print|switch|confirm|shape)[^{]*\{[^}]*min-height:\s*([0-9]+)px/', $css, $t, PREG_SET_ORDER);
        self::assertNotSame([], $t);
        foreach ($t as [$all, $px]) {
            self::assertGreaterThanOrEqual(44, (int) $px, $all);
        }
    }
}
