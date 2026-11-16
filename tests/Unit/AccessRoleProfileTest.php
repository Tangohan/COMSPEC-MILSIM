<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Admin\AccessRoleProfileController;
use App\Core\Database;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Rbac\AccessRoleLogoStore;
use App\Services\Rbac\AccessRoleProfileForm;
use App\Services\Rbac\AccessRoleProfileService as Svc;
use App\Services\Rbac\AccessRoleProfileView;
use App\Support\RosterEffectiveRole;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\MysqlDdlSqlitePdo;

/**
 * Effectifs > Accès : fiche des niveaux d'accès. Vraie étape 4870 sur SQLite (DDL MySQL traduit), vrais services, vrai
 * contrôleur (session, droits, jeton), vraie vue. Aucun double de test.
 */
final class AccessRoleProfileTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';
    private const TENANT = 1;
    private const OTHER = 2;
    private const STEP = 'database/steps/4870_access_role_profiles.php';
    private const CSRF = 'jeton-de-test-0123456789abcdef';

    private MysqlDdlSqlitePdo $pdo;
    private string $logoDir;
    /** @var array<string, mixed> */
    private array $saved = [];

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        Svc::resetCache();
        $this->pdo = self::database(true);
        $this->logoDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'arl-' . bin2hex(random_bytes(4));
        $started = new \ReflectionProperty(Session::class, 'started');
        $dbPdo = new \ReflectionProperty(Database::class, 'pdo');
        $this->saved = ['started' => $started->getValue(), 'pdo' => $dbPdo->getValue(), 'session' => $_SESSION ?? [], 'post' => $_POST, 'get' => $_GET, 'server' => $_SERVER, 'files' => $_FILES];
        $started->setValue(null, true);
        $dbPdo->setValue(null, $this->pdo);
        $_SERVER['HTTP_HOST'] = 'athena-comspec.eu';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_GET = [];
        $_FILES = [];
        $this->actAs(['admin.roles.manage']);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->logoDir . DIRECTORY_SEPARATOR . '*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->logoDir);
        if ($this->saved === []) {
            return;
        }
        (new \ReflectionProperty(Session::class, 'started'))->setValue(null, $this->saved['started']);
        (new \ReflectionProperty(Database::class, 'pdo'))->setValue(null, $this->saved['pdo']);
        $_SESSION = $this->saved['session'];
        $_POST = $this->saved['post'];
        $_GET = $this->saved['get'];
        $_SERVER = $this->saved['server'];
        $_FILES = $this->saved['files'];
        Gate::reset();
        Svc::resetCache();
    }

    // —— Données ——

    private static function database(bool $migrated): MysqlDdlSqlitePdo
    {
        $pdo = new MysqlDdlSqlitePdo();
        $pdo->sqliteCreateFunction('NOW', static fn (): string => date('Y-m-d H:i:s'), 0);
        foreach ([
            'CREATE TABLE tenants (id INTEGER PRIMARY KEY, name TEXT)',
            'CREATE TABLE audit_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, user_id INTEGER, action TEXT, entity_type TEXT, entity_id INTEGER, old_value TEXT, new_value TEXT, ip TEXT, user_agent TEXT, created_at TEXT)',
            'CREATE TABLE roles (id INTEGER PRIMARY KEY, tenant_id INTEGER, name TEXT, slug TEXT, description TEXT, is_system INTEGER DEFAULT 0, is_locked INTEGER DEFAULT 0, role_layer TEXT DEFAULT \'community\', badge_style TEXT, is_active INTEGER, created_at TEXT)',
            'CREATE TABLE personnel_job_role_tags (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, name TEXT, color TEXT, created_at TEXT)',
            'CREATE TABLE personnel_job_role_tag_links (tenant_id INTEGER, job_role_id INTEGER, tag_id INTEGER)',
            'CREATE TABLE personnel_qualification_definitions (id INTEGER PRIMARY KEY, tenant_id INTEGER, name TEXT, archived_at TEXT, is_active INTEGER)',
            'CREATE TABLE personnel_job_roles (id INTEGER PRIMARY KEY, tenant_id INTEGER, name TEXT, slug TEXT, description TEXT, is_system INTEGER DEFAULT 0)',
        ] as $sql) {
            $pdo->exec($sql);
        }
        $pdo->exec("INSERT INTO tenants (id, name) VALUES (1, 'Une'), (2, 'Autre')");
        $role = $pdo->prepare('INSERT INTO roles (id, tenant_id, name, slug, description, is_system) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ([
            [1, self::TENANT, 'Membre', 'member', 'Le socle.', 1],
            [2, self::TENANT, 'Instructeur', 'access-custom-instructeur', 'Ouvre les sessions.', 0],
            [3, self::TENANT, 'Recrutement', 'access-custom-recrutement', '', 0],
            [9, self::OTHER, 'Étranger', 'access-custom-etranger', '', 0],
        ] as $r) {
            $role->execute($r);
        }
        $pdo->exec("INSERT INTO personnel_qualification_definitions (id, tenant_id, name, archived_at, is_active) VALUES (1, 1, 'Moniteur de tir', NULL, 1), (2, 1, 'Pédagogie', NULL, 1), (3, 2, 'Qualif d’ailleurs', NULL, 1)");
        $pdo->exec("INSERT INTO personnel_job_roles (id, tenant_id, name) VALUES (21, 1, 'Instructeur tir'), (22, 1, 'Infirmier'), (29, 2, 'Emploi d’ailleurs')");
        $pdo->exec("INSERT INTO personnel_job_role_tags (tenant_id, name, color) VALUES (1, 'Formation', 'green')");
        if ($migrated) {
            self::runStep($pdo);
        }

        return $pdo;
    }

    private static function runStep(PDO $pdo): string
    {
        $root = self::ROOT;
        ob_start();
        try {
            require self::ROOT . '/' . self::STEP;
        } finally {
            $out = (string) ob_get_clean();
        }
        unset($root);

        return $out;
    }

    /** @param list<string> $permissions */
    private function actAs(array $permissions, int $tenantId = self::TENANT): void
    {
        $_SESSION = ['_csrf_token' => self::CSRF, 'user_id' => 7, 'tenant_id' => $tenantId];
        Gate::reset();
        Gate::getInstance()->setPermissions($permissions);
    }

    private function svc(): Svc
    {
        return new Svc($this->pdo);
    }

    /** @return array<string, mixed> */
    private function input(array $over = []): array
    {
        return $over + [
            'abbreviation' => 'instr', 'details' => "Prépare les **sessions**.\n\n- Ouvre", 'icon' => 'graduation', 'tags' => 'Formation, Encadrement',
            'qualifications' => [1 => 'required', 2 => 'granted'], 'jobs' => ['21'], 'grade_min' => '4', 'grade_max' => '12', 'sort_order' => '30',
        ];
    }

    private function controller(?callable $uploaded = null): AccessRoleProfileController
    {
        $logos = new AccessRoleLogoStore($this->logoDir);

        return new AccessRoleProfileController(null, new AccessRoleProfileForm(new Svc($this->pdo), $logos, $uploaded), $logos);
    }

    private static function post(array $fields): Request
    {
        $_POST = $fields;

        return new Request();
    }

    private static function location(Response $r): string
    {
        return (string) $r->headerValue('Location');
    }

    private static function png(int $w = 80, int $h = 60): string
    {
        $im = imagecreatetruecolor($w, $h);
        imagefill($im, 0, 0, (int) imagecolorallocate($im, 30, 90, 200));
        ob_start();
        imagepng($im);
        imagedestroy($im);

        return (string) ob_get_clean();
    }

    /** JPEG avec un segment EXIF (APP1) inséré après le marqueur de début. */
    private static function jpegWithExif(): string
    {
        $im = imagecreatetruecolor(64, 64);
        imagefill($im, 0, 0, (int) imagecolorallocate($im, 200, 120, 40));
        ob_start();
        imagejpeg($im, null, 90);
        imagedestroy($im);
        $jpeg = (string) ob_get_clean();
        $payload = "Exif\x00\x00" . 'MM' . "\x00\x2A\x00\x00\x00\x08" . str_repeat('GPS-SECRET-', 4);
        $app1 = "\xFF\xE1" . pack('n', strlen($payload) + 2) . $payload;

        return substr($jpeg, 0, 2) . $app1 . substr($jpeg, 2);
    }

    private function tmp(string $bytes): string
    {
        $f = tempnam(sys_get_temp_dir(), 'arl');
        file_put_contents((string) $f, $bytes);

        return (string) $f;
    }

    // —— Étape de base de données ——

    public function testMigrationStepIsIdempotentSqliteFriendlyAndHasARollback(): void
    {
        $pdo = self::database(false);
        Svc::resetCache();
        self::assertFalse((new Svc($pdo))->ready());
        $first = self::runStep($pdo);
        self::assertStringContainsString('Tables créées', $first);
        self::assertTrue((new Svc($pdo))->ready());
        $changes = $pdo->changes;
        $second = self::runStep($pdo);
        self::assertStringContainsString('déjà présentes', $second);
        self::assertSame($changes, $pdo->changes, 'relancer l’étape ne modifie plus le schéma');

        $sql = str_replace("\r\n", "\n", (string) file_get_contents(self::ROOT . '/migrations/2026_11_23_100_access_role_profiles.sql'));
        self::assertStringContainsString('UNIQUE KEY uk_arp_tenant_abbreviation (tenant_id, abbreviation)', $sql, 'unicité dans le CREATE TABLE');
        self::assertSame(0, preg_match('/ALTER\s+TABLE/i', $sql));
        $down = (string) file_get_contents(self::ROOT . '/migrations/rollback/access_role_profiles_down.sql');
        foreach (['access_role_profiles', 'access_role_tag_links', 'access_role_qualifications', 'access_role_jobs'] as $t) {
            self::assertStringContainsString('DROP TABLE IF EXISTS ' . $t, $down);
        }
        $registry = require self::ROOT . '/database/steps/registry.php';
        $entry = array_values(array_filter($registry, static fn (array $s): bool => ($s['id'] ?? '') === 'access_role_profiles'));
        self::assertCount(1, $entry);
        self::assertSame('4870_access_role_profiles.php', $entry[0]['file']);
    }

    public function testWithoutTheStepNothingIsReadOrWrittenAndThePageSaysSo(): void
    {
        $pdo = self::database(false);
        Svc::resetCache();
        $svc = new Svc($pdo);
        self::assertSame([], $svc->profiles(self::TENANT));
        self::assertFalse($svc->save(self::TENANT, 2, $svc->validate(self::TENANT, 2, $this->input(['qualifications' => [], 'jobs' => []])), 7));
        $form = new AccessRoleProfileForm($svc, new AccessRoleLogoStore($this->logoDir));
        self::assertSame(['data' => null, 'error' => null], $form->check(self::TENANT, 2, ['profile_form' => '1', 'abbreviation' => '!!']));

        $html = $this->render(['ready' => false]);
        self::assertStringContainsString(Svc::NOT_READY, $html);
        self::assertStringNotContainsString('name="abbreviation"', $html, 'champs de la fiche absents sans l’étape');
        self::assertStringContainsString('name="role_color"', $html, 'nom, description et couleur restent modifiables');
        self::assertStringContainsString('Niveaux d’accès', $html);
    }

    // —— Champs enregistrés et validés ——

    public function testProfileFieldsAreValidatedSavedAndReadBack(): void
    {
        $svc = $this->svc();
        $data = $svc->validate(self::TENANT, 2, $this->input());
        self::assertSame('INSTR', $data['abbreviation']);
        self::assertTrue($svc->save(self::TENANT, 2, $data, 7));
        $p = $svc->profiles(self::TENANT)[2];
        self::assertSame('INSTR', $p['abbreviation']);
        self::assertSame('graduation', $p['icon']);
        self::assertSame([4, 12], [$p['grade_min'], $p['grade_max']]);
        self::assertSame(30, $p['sort_order']);
        self::assertSame([1], $p['qualifications']['required']);
        self::assertSame([2], $p['qualifications']['granted']);
        self::assertSame([21], $p['jobs']);
        self::assertSame(['Encadrement', 'Formation'], array_column($p['tags'], 'name'));
        self::assertStringContainsString('<strong>sessions</strong>', Svc::detailsHtml($p['details']));
        self::assertStringNotContainsString('<script', Svc::detailsHtml('<script>alert(1)</script>'));
        self::assertSame(['done' => 6, 'total' => 6, 'missing' => []], Svc::completeness(['description' => 'Ouvre les sessions.'], $p));
        self::assertSame(1, Svc::completeness(['description' => 'x'], Svc::emptyProfile())['done']);
        self::assertSame('4 / 6', Svc::completeness(['description' => ''], ['details' => 'x', 'abbreviation' => 'A', 'icon' => 'star', 'tags' => [['name' => 't']]] + Svc::emptyProfile())['done'] . ' / 6');
    }

    public function testInvalidValuesAreRefused(): void
    {
        $svc = $this->svc();
        $svc->save(self::TENANT, 2, $svc->validate(self::TENANT, 2, $this->input()), 7);
        $cases = [
            'abrégé déjà pris' => [3, ['abbreviation' => 'INSTR']],
            'abrégé trop court' => [3, ['abbreviation' => 'A']],
            'icône inconnue' => [3, ['icon' => 'licorne']],
            'qualification d’une autre communauté' => [3, ['qualifications' => [3 => 'required']]],
            'lien de qualification inconnu' => [3, ['qualifications' => [1 => 'obligatoire']]],
            'emploi d’une autre communauté' => [3, ['jobs' => ['29']]],
            'grades inversés' => [3, ['grade_min' => '12', 'grade_max' => '4']],
            'grade hors échelle' => [3, ['grade_min' => '25']],
            'ordre non entier' => [3, ['sort_order' => 'premier']],
            'description trop longue' => [3, ['details' => str_repeat('a', Svc::DETAILS_MAX + 1)]],
            'trop d’étiquettes' => [3, ['tags' => 'a,b,c,d,e,f,g,h,i']],
        ];
        foreach ($cases as $label => [$roleId, $over]) {
            try {
                $svc->validate(self::TENANT, $roleId, $this->input($over + ['abbreviation' => 'RECR']));
                self::fail('accepté à tort : ' . $label);
            } catch (InvalidArgumentException $e) {
                self::assertNotSame('', $e->getMessage(), $label);
            }
        }
        self::assertSame('INSTR', $svc->validate(self::TENANT, 2, $this->input())['abbreviation'], 'son propre abrégé reste valide');
        self::assertFalse($svc->save(self::OTHER, 2, $svc->validate(self::TENANT, 2, $this->input()), 7), 'niveau d’une autre communauté');
    }

    public function testTagsJoinTheSharedCatalogWithoutDuplicates(): void
    {
        $svc = $this->svc();
        $svc->save(self::TENANT, 2, $svc->validate(self::TENANT, 2, $this->input(['tags' => 'formation, Nouvelle étiquette'])), 7);
        $svc->save(self::TENANT, 3, $svc->validate(self::TENANT, 3, $this->input(['abbreviation' => 'RECR', 'tags' => 'Nouvelle étiquette'])), 7);
        $names = $this->pdo->query('SELECT name FROM personnel_job_role_tags WHERE tenant_id = 1 ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
        self::assertSame(['Formation', 'Nouvelle étiquette'], $names, 'réutilise « Formation » des fonctions, crée la nouvelle une seule fois');
        $catalog = array_column($svc->tagCatalog(self::TENANT), 'roles', 'name');
        self::assertSame(['Formation' => 1, 'Nouvelle étiquette' => 2], $catalog);
    }

    public function testArchiveCopyAndForget(): void
    {
        $svc = $this->svc();
        $svc->save(self::TENANT, 2, $svc->validate(self::TENANT, 2, $this->input()), 7);
        self::assertTrue($svc->setArchived(self::TENANT, 2, true, 7));
        self::assertTrue($svc->profiles(self::TENANT)[2]['archived']);
        self::assertFalse($svc->setArchived(self::OTHER, 2, true, 7));
        self::assertTrue($svc->copy(self::TENANT, 2, 3, 7));
        $copy = $svc->profiles(self::TENANT)[3];
        self::assertNull($copy['abbreviation'], 'l’abrégé, unique, ne suit pas');
        self::assertFalse($copy['archived']);
        self::assertSame([1], $copy['qualifications']['required']);
        self::assertNull($svc->forget(self::TENANT, 3));
        self::assertArrayNotHasKey(3, $svc->profiles(self::TENANT));
    }

    // —— Logo téléversé ——

    public function testLogoUploadChecksTypeSizeAndStripsExif(): void
    {
        $store = new AccessRoleLogoStore($this->logoDir);
        if (!function_exists('imagecreatefromstring')) {
            self::markTestSkipped('GD requis');
        }
        $png = $store->store(self::TENANT, $this->tmp(self::png()));
        self::assertTrue($png['ok']);
        self::assertSame('image/png', $png['mime']);
        self::assertNotNull($store->resolve(self::TENANT, $png['file']));
        self::assertNull($store->resolve(self::OTHER, $png['file']), 'jamais servi à une autre communauté');
        self::assertNull($store->resolve(self::TENANT, '../' . $png['file']));

        $exif = self::jpegWithExif();
        self::assertStringContainsString('GPS-SECRET', $exif);
        $jpg = $store->store(self::TENANT, $this->tmp($exif));
        self::assertTrue($jpg['ok']);
        $bytes = (string) file_get_contents($this->logoDir . DIRECTORY_SEPARATOR . $jpg['file']);
        self::assertStringNotContainsString('GPS-SECRET', $bytes, 'EXIF retiré');
        self::assertStringNotContainsString('Exif', $bytes);

        self::assertFalse($store->store(self::TENANT, $this->tmp(str_repeat('a', AccessRoleLogoStore::MAX_BYTES + 1)))['ok'], 'plus d’1 Mo');
        $fake = $store->store(self::TENANT, $this->tmp("GIF89a<?php echo 1; ?>"));
        self::assertFalse($fake['ok'], 'type lu dans le contenu');
        self::assertStringContainsString('PNG, JPEG, WebP ou SVG', $fake['error']);
    }

    public function testSvgIsAcceptedOnlyWhenSafeAndIsRewritten(): void
    {
        $store = new AccessRoleLogoStore($this->logoDir);
        $clean = '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" xmlns:inkscape="http://www.inkscape.org/namespaces/inkscape" viewBox="0 0 10 10" inkscape:version="1"><metadata>éditeur</metadata><circle cx="5" cy="5" r="4" fill="#123456"/></svg>';
        $ok = $store->store(self::TENANT, $this->tmp($clean));
        self::assertTrue($ok['ok']);
        self::assertSame('image/svg+xml', $ok['mime']);
        $saved = (string) file_get_contents($this->logoDir . DIRECTORY_SEPARATOR . $ok['file']);
        self::assertStringContainsString('<circle', $saved);
        self::assertStringNotContainsString('metadata', $saved);
        self::assertStringNotContainsString('inkscape', $saved);
        foreach ([
            'script' => '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
            'gestionnaire' => '<svg xmlns="http://www.w3.org/2000/svg"><rect width="1" height="1" onclick="alert(1)"/></svg>',
            'image externe' => '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><image xlink:href="https://exemple.test/x.png"/></svg>',
            'url externe' => '<svg xmlns="http://www.w3.org/2000/svg"><rect width="1" height="1" fill="url(https://exemple.test/#a)"/></svg>',
            'entité' => '<!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]><svg xmlns="http://www.w3.org/2000/svg"><text>&x;</text></svg>',
            'objet étranger' => '<svg xmlns="http://www.w3.org/2000/svg"><foreignObject><div>x</div></foreignObject></svg>',
        ] as $label => $svg) {
            self::assertNull(AccessRoleLogoStore::sanitizeSvg($svg), $label);
        }
    }

    public function testFormAppliesLogoOnlyWithConfirmationAndReplacesTheOldFile(): void
    {
        if (!function_exists('imagecreatefromstring')) {
            self::markTestSkipped('GD requis');
        }
        $svc = $this->svc();
        $logos = new AccessRoleLogoStore($this->logoDir);
        $form = new AccessRoleProfileForm($svc, $logos, static fn (string $tmp): bool => is_file($tmp));
        $file = ['tmp_name' => $this->tmp(self::png()), 'error' => UPLOAD_ERR_OK];
        $refused = $form->apply(self::TENANT, 2, null, ['profile_form' => '1', 'visual' => 'logo'], $file, 7);
        self::assertFalse($refused['ok']);
        self::assertNull($svc->logoOf(self::TENANT, 2));
        $first = $form->apply(self::TENANT, 2, null, ['profile_form' => '1', 'visual' => 'logo', 'logo_confirm' => '1'], $file, 7);
        self::assertTrue($first['ok']);
        $one = $svc->logoOf(self::TENANT, 2);
        self::assertNotNull($one);
        $form->apply(self::TENANT, 2, null, ['profile_form' => '1', 'visual' => 'logo', 'logo_confirm' => '1'], ['tmp_name' => $this->tmp(self::png(40, 40)), 'error' => UPLOAD_ERR_OK], 7);
        $two = $svc->logoOf(self::TENANT, 2);
        self::assertNotSame($one['file'], $two['file']);
        self::assertFileDoesNotExist($this->logoDir . DIRECTORY_SEPARATOR . $one['file'], 'l’ancien fichier est effacé');
        $notUploaded = new AccessRoleProfileForm($svc, $logos, static fn (string $tmp): bool => false);
        self::assertFalse($notUploaded->apply(self::TENANT, 2, null, ['profile_form' => '1', 'logo_confirm' => '1'], $file, 7)['ok'], 'fichier qui n’est pas un envoi');
        $form->apply(self::TENANT, 2, null, ['profile_form' => '1', 'visual' => 'icon'], null, 7);
        self::assertNull($svc->logoOf(self::TENANT, 2), 'choisir l’icône retire le logo');
        self::assertFileDoesNotExist($this->logoDir . DIRECTORY_SEPARATOR . $two['file']);
    }

    // —— Contrôleur : droits, jeton, communauté ——

    public function testSaveNeedsTheRightAndAValidToken(): void
    {
        $fields = ['_csrf_token' => self::CSRF, 'role_id' => '2', 'name' => 'Instructeur', 'description' => 'Ouvre les sessions.', 'role_color' => 'blue', 'profile_form' => '1'] + $this->input();

        $this->actAs(['organization.effectifs.hub.view']);
        $this->controller()->save(self::post($fields));
        self::assertArrayNotHasKey(2, $this->svc()->profiles(self::TENANT), 'sans droit de gérer les accès');

        $this->actAs(['admin.roles.manage']);
        $this->controller()->save(self::post(['_csrf_token' => 'faux'] + $fields));
        self::assertArrayNotHasKey(2, $this->svc()->profiles(self::TENANT), 'jeton refusé');

        $r = $this->controller()->save(self::post($fields));
        self::assertStringContainsString('?role=2', self::location($r));
        self::assertSame('INSTR', $this->svc()->profiles(self::TENANT)[2]['abbreviation']);
        $role = $this->pdo->query('SELECT badge_style FROM roles WHERE id = 2')->fetchColumn();
        self::assertSame('blue', json_decode((string) $role, true)['color']);
        $log = $this->pdo->query("SELECT action, entity_type, entity_id, new_value FROM audit_logs ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        self::assertSame(['access_role.profile_updated', 'role', 2], [$log['action'], $log['entity_type'], (int) $log['entity_id']], 'écriture journalisée');
        self::assertContains('abbreviation', json_decode((string) $log['new_value'], true)['fields']);
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn(), 'les refus ne journalisent rien');
    }

    public function testSaveRefusesAnotherCommunityAndKeepsTheInputOnError(): void
    {
        $fields = ['_csrf_token' => self::CSRF, 'role_color' => '', 'profile_form' => '1'] + $this->input();
        $this->controller()->save(self::post(['role_id' => '9', 'name' => 'Pris'] + $fields));
        $rows = $this->pdo->query('SELECT COUNT(*) FROM access_role_profiles')->fetchColumn();
        self::assertSame(0, (int) $rows, 'niveau d’une autre communauté');

        $r = $this->controller()->save(self::post(['role_id' => '3', 'name' => 'Recrutement'] + ['grade_min' => '12', 'grade_max' => '2'] + $fields));
        self::assertStringContainsString('fiche=1', self::location($r), 'la fenêtre se rouvre');
        self::assertSame('12', Session::getFlash('rol_old')['grade_min'], 'la saisie est reprise');
        self::assertArrayNotHasKey(3, $this->svc()->profiles(self::TENANT));

        $this->controller()->save(self::post(['role_id' => '3', 'name' => 'instructeur'] + $fields));
        self::assertSame('Recrutement', $this->pdo->query('SELECT name FROM roles WHERE id = 3')->fetchColumn(), 'nom déjà pris');
    }

    public function testArchiveKeepsRightsAndHoldersAndRefusesMember(): void
    {
        $this->controller()->archive(self::post(['_csrf_token' => self::CSRF, 'role_id' => '1', 'archive' => '1']));
        self::assertArrayNotHasKey(1, $this->svc()->profiles(self::TENANT), 'Membre ne s’archive pas');
        $this->controller()->archive(self::post(['_csrf_token' => 'faux', 'role_id' => '2', 'archive' => '1']));
        self::assertArrayNotHasKey(2, $this->svc()->profiles(self::TENANT));
        $this->controller()->archive(self::post(['_csrf_token' => self::CSRF, 'role_id' => '2', 'archive' => '1']));
        self::assertTrue($this->svc()->profiles(self::TENANT)[2]['archived']);
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM roles WHERE id = 2')->fetchColumn(), 'le niveau reste');
        $this->controller()->archive(self::post(['_csrf_token' => self::CSRF, 'role_id' => '2', 'archive' => '0']));
        self::assertFalse($this->svc()->profiles(self::TENANT)[2]['archived']);
    }

    public function testLogoRouteServesOnlyTheSessionCommunity(): void
    {
        if (!function_exists('imagecreatefromstring')) {
            self::markTestSkipped('GD requis');
        }
        $logos = new AccessRoleLogoStore($this->logoDir);
        $stored = $logos->store(self::TENANT, $this->tmp(self::png()));
        $this->svc()->setLogo(self::TENANT, 2, $stored['file'], $stored['mime'], 7);
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $ok = $this->controller()->logo(new Request(), ['id' => '2']);
        self::assertSame(200, $ok->statusCode());
        self::assertSame('image/png', $ok->headerValue('Content-Type'));
        self::assertSame('nosniff', $ok->headerValue('X-Content-Type-Options'));
        $this->actAs(['admin.roles.manage'], self::OTHER);
        self::assertSame(404, $this->controller()->logo(new Request(), ['id' => '2'])->statusCode());
        $this->actAs([]);
        self::assertSame(404, $this->controller()->logo(new Request(), ['id' => '2'])->statusCode(), 'sans accès aux effectifs');
    }

    public function testDeleteIsRefusedWhileTheLevelHasHoldersAndRoutesKeepTheGuards(): void
    {
        $ctrl = str_replace("\r\n", "\n", (string) file_get_contents(self::ROOT . '/app/Controllers/Admin/EffectifsWorkspaceController.php'));
        $start = (int) strpos($ctrl, 'public function deleteAccessRole(');
        $body = substr($ctrl, $start, (int) strpos($ctrl, "\n    public function ", $start + 10) - $start);
        self::assertMatchesRegularExpression('/if \(\$isCustom && \$holderCount > 0\) \{.*?return Response::redirect/s', $body);
        self::assertLessThan(strpos($body, 'syncOrganizationRoles'), strpos($body, '$holderCount > 0'), 'refus avant tout déplacement de membre');
        self::assertStringContainsString('->forget($tenantId, $roleId)', $body);
        $routes = (string) file_get_contents(self::ROOT . '/routes/web.php');
        foreach (["post('/back-office/ressources/effectifs/roles/fiche'", "post('/back-office/ressources/effectifs/roles/archiver'", "get('/back-office/ressources/effectifs/roles/{id}/logo'"] as $route) {
            self::assertMatchesRegularExpression('#' . preg_quote($route, '#') . '[^;]*OrganizationAdminMiddleware::class\]\);#', $routes, $route);
        }
    }

    // —— Rôle hérité de l'emploi : inchangé ——

    public function testRosterRoleStillComesFromTheJobNotFromTheAccessLevel(): void
    {
        $role = RosterEffectiveRole::resolve('Membre', ['B SQN'], [['label' => 'Instructeur tir', 'primary' => true]]);
        self::assertSame(RosterEffectiveRole::SOURCE_JOB, $role['source']);
        self::assertSame('Instructeur tir', $role['label'], 'un emploi associé à un niveau d’accès ne remplace pas le rôle opérationnel');
    }

    // —— Rendu de la page ——

    /** @param array<string, mixed> $ctx */
    private function render(array $ctx = [], array $query = []): string
    {
        $_GET = $query;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $ready = $ctx['ready'] ?? true;
        $svc = $this->svc();
        if ($ready) {
            $svc->save(self::TENANT, 2, $svc->validate(self::TENANT, 2, $this->input()), 7);
            $svc->setArchived(self::TENANT, 3, true, 7);
        }
        $roles = $this->pdo->query('SELECT * FROM roles WHERE tenant_id = 1 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $data = AccessRoleProfileView::data(new Request(), $roles, $ready ? $svc->profiles(self::TENANT) : [], [1 => [10, 11, 12], 2 => [10, 11], 3 => []], [
            'ready' => $ready, 'tags_ready' => $ready, 'qualifications' => $ready ? $svc->qualificationCatalog(self::TENANT) : [],
            'jobs' => $ready ? $svc->jobCatalog(self::TENANT) : [], 'tags' => $ready ? $svc->tagCatalog(self::TENANT) : [], 'grade_labels' => [4 => ['Caporal-chef']], 'old' => null,
        ]);

        return Response::view('admin.effectifs_workspace.roles', $data + [
            'orgRoles' => $roles, 'selectedAccessRole' => $roles[1], 'selectedAccessKind' => 'custom', 'accessPermissionGroups' => [], 'checkedPermissionIds' => [],
            'lockedPermissionIds' => [], 'accessRoleCards' => [2 => ['kind' => 'custom', 'color' => null, 'hidden' => false, 'members' => 2, 'rights' => 1, 'scope' => 'read', 'critical' => 0]], 'accessHolders' => [], 'accessHoldersTotal' => 2, 'accessSelectedColor' => '',
            'accessColorPresets' => \App\Services\Rbac\AccessRoleColor::presets(), 'accessTemplates' => [], 'canManageRoles' => true, 'csrfToken' => self::CSRF,
        ])->body();
    }

    public function testPageRendersBandCardsFiltersAndStepDialog(): void
    {
        $html = $this->render();
        self::assertStringContainsString('bo-band bo-band--hero', $html);
        self::assertStringContainsString('membres couverts', $html);
        self::assertMatchesRegularExpression('/<strong>3<\/strong><span>membres couverts/', $html);
        self::assertSame(3, substr_count($html, 'data-rol-card '), 'une carte par niveau');
        self::assertStringContainsString('6/6 champs renseignés', $html);
        self::assertStringContainsString('1/6 champs renseignés', $html);
        self::assertStringContainsString('Moniteur de tir', $html);
        self::assertStringContainsString('name="etiquette"', $html);
        self::assertStringContainsString('name="qualif"', $html);
        self::assertStringContainsString('name="archives"', $html);
        self::assertStringContainsString('Afficher les archivés (1)', $html);
        self::assertMatchesRegularExpression('/data-archived="1"[^>]*hidden/', $html, 'archivé masqué par défaut');
        foreach (['Identité', 'Apparence', 'Étiquettes', 'Qualifications', 'Emplois et grades'] as $step) {
            self::assertStringContainsString('<span>' . $step . '</span>', $html);
        }
        self::assertStringContainsString('id="rol-new"', $html);
        self::assertStringContainsString('id="rol-edit"', $html);
        self::assertStringContainsString('enctype="multipart/form-data"', $html);
        self::assertStringContainsString('name="qualifications[1]"', $html);
        self::assertStringContainsString('data-rol-help', $html);
        self::assertStringContainsString('Suppression possible seulement sans titulaire', $html);
        self::assertStringNotContainsString('eyebrow', $html);
        self::assertStringNotContainsString('sse-ds', $html);

        $filtered = $this->render([], ['archives' => '1', 'q' => 'recru']);
        self::assertMatchesRegularExpression('/data-archived="1"(?![^>]*hidden)[^>]*>/', $filtered, 'archivés affichés sur demande');
        self::assertStringContainsString('1 niveau affiché', $filtered);
    }

    public function testViewFiltersMatchTagsQualificationsAndCompleteness(): void
    {
        $card = ['name' => 'Instructeur', 'description' => '', 'qualification_count' => 0, 'completeness' => ['done' => 2, 'total' => 6],
            'profile' => ['archived' => false, 'abbreviation' => 'INSTR', 'tags' => [['id' => 4, 'name' => 'Formation']]]];
        $f = ['q' => '', 'tag' => 0, 'qualif' => '', 'archives' => false, 'incomplete' => false];
        self::assertTrue(AccessRoleProfileView::matches($card, $f));
        self::assertTrue(AccessRoleProfileView::matches($card, ['q' => 'formation'] + $f), 'recherche dans les étiquettes');
        self::assertTrue(AccessRoleProfileView::matches($card, ['tag' => 4] + $f));
        self::assertFalse(AccessRoleProfileView::matches($card, ['tag' => 5] + $f));
        self::assertFalse(AccessRoleProfileView::matches($card, ['qualif' => 'avec'] + $f));
        self::assertTrue(AccessRoleProfileView::matches($card, ['incomplete' => true] + $f));
        $card['profile']['archived'] = true;
        self::assertFalse(AccessRoleProfileView::matches($card, $f));
        self::assertTrue(AccessRoleProfileView::matches($card, ['archives' => true] + $f));
    }
}
