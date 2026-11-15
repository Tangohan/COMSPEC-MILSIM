<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\SseWantedNoticeController;
use App\Core\Gate;
use App\Core\Request;
use App\Repositories\SseFileRecordRepository;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SseWantedExtrasRepository;
use App\Repositories\SseWantedNoticeRepository;
use App\Repositories\SseWantedSettingsRepository;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseWantedAccess;
use App\Services\Sse\SseWantedNoticeData;
use App\Services\Sse\SseWantedService;
use App\Support\SseWantedCodes;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseWantedSqlite;

/**
 * Codes, signalement et texte d'un avis : stockage avec l'avis (table fille), rétrocompatibilité d'un avis sans codes, mode dégradé
 * sans l'étape de base de données, filtre et recherche de la liste, formes exposées au PDF et au jeu, écrans qui s'affichent
 * réellement avec des données.
 */
final class SseWantedCodesStorageTest extends TestCase
{
    private PDO $pdo;

    /** @var array<string, mixed> */
    private array $saved = [];

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->saved = ['session' => $_SESSION ?? null, 'post' => $_POST ?? [], 'get' => $_GET ?? [], 'server' => $_SERVER];
        \App\Core\Csrf::token();
        $this->pdo = SseWantedSqlite::open();
        $this->pdo->exec("INSERT INTO users (id, tenant_id, steam_id, display_name) VALUES (7, 1, '76561198000000007', 'Vrai Membre')");
        $_SESSION = [
            'user_id' => 7, 'tenant_id' => 1, 'callsign' => 'ANALYSTE', '_csrf_token' => 'jeton',
            'sse_tenant_id' => 1, 'sse_guest' => false, 'sse_clearance_until' => time() + 3600, 'sse_access_code_id' => 0,
            'sse_confidentiality_version' => SseAccessCodeService::CONFIDENTIALITY_VERSION, 'sse_confidentiality_tenant_id' => 1, 'sse_confidentiality_code_id' => 0,
        ];
        $_POST = [];
        $_GET = [];
        Gate::reset();
    }

    protected function tearDown(): void
    {
        SseWantedSqlite::close();
        $_POST = $this->saved['post'];
        $_GET = $this->saved['get'];
        $_SERVER = $this->saved['server'];
        if ($this->saved['session'] === null) {
            unset($_SESSION);
        } else {
            $_SESSION = $this->saved['session'];
        }
        Gate::reset();
    }

    private static function manager(): SseWantedAccess
    {
        return new SseWantedAccess(false, true, true, true, 1, 7, 'ANALYSTE');
    }

    private function identity(string $last = 'Karimzadeh'): int
    {
        return (new SseIdentityRepository())->create(1, ['last_name' => $last, 'first_name' => 'Élodie', 'sex' => 'M', 'birth_date' => '1984-03-12', 'nationality' => 'TAK']);
    }

    /** @return array<string, mixed> */
    private static function input(array $extra = []): array
    {
        return $extra + ['notice_type' => 'hvt', 'threat_level' => 'high', 'armed' => 'yes', 'reason' => 'Participation présumée à une attaque coordonnée.'];
    }

    // ------------------------------------------------------------------ stockage

    public function testCodesAndPhysicalAreStoredWithTheNoticeBesideTheFinalText(): void
    {
        $id = $this->identity();
        $res = (new SseWantedService())->place(1, $id, self::input([
            'codes_reasons' => ['M-TER-01', 'M-INS-01', 'M-ZZZ-99'], 'codes_measures' => ['A-DNG', 'A-NOI'], 'codes_template' => 'cell_leader',
            'physical' => ['height_cm' => '181', 'build' => 'athletic', 'hair' => 'brown', 'tattoos' => 'Aigle sur l’épaule droite', 'equipment' => ['rifle', 'vest']],
        ]), self::manager());
        self::assertTrue($res['ok']);
        self::assertSame([], $res['warnings']);

        $notice = (new SseWantedNoticeRepository())->find(1, $res['id']);
        self::assertSame('Participation présumée à une attaque coordonnée.', $notice['reason'], 'le texte final reste dans l’avis');
        $extras = (new SseWantedExtrasRepository())->forNotice(1, $res['id']);
        self::assertSame(['M-TER-01', 'M-INS-01'], array_column($extras['codes']['reasons'], 'code'), 'code inconnu écarté');
        self::assertSame(['A-DNG', 'A-NOI'], array_column($extras['codes']['measures'], 'code'));
        self::assertSame('cell_leader', $extras['codes']['template']);
        self::assertSame('Planification d’attaque', $extras['codes']['reasons'][0]['fr']);
        self::assertSame('Attack planning', $extras['codes']['reasons'][0]['en']);
        self::assertSame(181, $extras['physical']['height_cm']);
        self::assertSame(['rifle', 'vest'], $extras['physical']['equipment']);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $extras['physical']['snapshot_at'], 'instantané daté');

        // La chronologie note les codes posés.
        $details = (string) $this->pdo->query("SELECT details_json FROM sse_identity_events WHERE event_type = 'notice.placed'")->fetchColumn();
        self::assertStringContainsString('"reasons":["M-TER-01","M-INS-01"]', $details);
    }

    public function testEmptyTextIsComposedFromTheChosenCodesAndTheDefaultInstructionStillApplies(): void
    {
        $id = $this->identity();
        $res = (new SseWantedService())->place(1, $id, ['notice_type' => 'poi', 'threat_level' => 'low', 'armed' => 'no', 'reason' => '', 'instructions' => '', 'codes_reasons' => ['M-INT-01'], 'codes_measures' => ['A-OBS', 'A-REP']], self::manager());
        self::assertTrue($res['ok'], json_encode($res));
        $n = (new SseWantedNoticeRepository())->find(1, $res['id']);
        self::assertSame(SseWantedCodes::compose(['M-INT-01'], SseWantedCodes::REASONS, 'fr', []), $n['reason']);
        self::assertSame(SseWantedCodes::compose(['A-OBS', 'A-REP'], SseWantedCodes::MEASURES, 'fr', []), $n['instructions']);

        // Sans code ni texte : toujours refusé ; sans mesures : la consigne par défaut de la communauté.
        $none = (new SseWantedService())->place(1, $this->identity('Autre'), ['notice_type' => 'poi', 'threat_level' => 'low', 'armed' => 'no', 'reason' => ''], self::manager());
        self::assertFalse($none['ok']);
        $def = (new SseWantedService())->place(1, $this->identity('Troisième'), self::input(['codes_reasons' => ['M-TRF-02']]), self::manager());
        self::assertSame(\App\Support\SseWantedCatalog::DEFAULTS['default_instructions_fr'], (new SseWantedNoticeRepository())->find(1, $def['id'])['instructions']);
        // En anglais, le texte composé est en anglais.
        $en = SseWantedService::validate(['notice_type' => 'poi', 'threat_level' => 'low', 'armed' => 'no', 'reason' => '', 'codes_reasons' => ['M-TRF-02']], SseWantedSettingsRepository::normalize([]), null, 'en');
        self::assertSame('Organizes clandestine border crossings for fighters or goods.', $en['data']['reason']);
    }

    public function testACommunityCodeDisabledAfterwardsKeepsItsLabelOnTheOldNotice(): void
    {
        $id = $this->identity();
        $settings = new SseWantedSettingsRepository();
        $settings->save(1, SseWantedSettingsRepository::normalize(['codes' => ['custom' => ['M-ZZZ-01' => ['fr' => 'Réseau de couriers', 'en' => 'Courier network']]]]), 7);
        $res = (new SseWantedService())->place(1, $id, self::input(['codes_reasons' => ['M-ZZZ-01']]), self::manager());
        self::assertTrue($res['ok']);
        // La communauté retire le code : l'avis ne change pas.
        $settings->save(1, SseWantedSettingsRepository::normalize(['codes' => ['disabled' => ['M-ZZZ-01']]]), 7);
        $x = (new SseWantedExtrasRepository())->forNotice(1, $res['id']);
        self::assertSame('Réseau de couriers', $x['codes']['reasons'][0]['fr']);
        self::assertSame('Courier network', $x['codes']['reasons'][0]['en']);
    }

    // ------------------------------------------------------------------ rétrocompatibilité et mode dégradé

    public function testANoticeWithoutCodesStillWorksEverywhere(): void
    {
        $id = $this->identity();
        $res = (new SseWantedService())->place(1, $id, self::input(), self::manager());
        self::assertTrue($res['ok']);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_wanted_notice_extras')->fetchColumn(), 'aucune ligne inutile');
        $x = (new SseWantedExtrasRepository())->forNotice(1, $res['id']);
        self::assertSame([], $x['codes']['reasons']);
        self::assertSame([], $x['physical']);
        // Un avis posé avant l'étape (ligne absente) s'affiche, se liste et se filtre.
        $notices = (new SseWantedNoticeRepository())->list(1, ['status' => 'active', 'codes' => ['M-TER-01']]);
        self::assertSame([], $notices);
        self::assertCount(1, (new SseWantedNoticeRepository())->list(1, ['status' => 'active']));
        $game = SseWantedNoticeData::forGame(1);
        self::assertSame([], $game[0]['reasons']);
        self::assertSame([], $game[0]['physical']);
        self::assertSame([], $game[0]['images']);
        $pdf = SseWantedNoticeData::forPdf((new SseWantedNoticeRepository())->find(1, $res['id']), 'fr');
        self::assertSame([], $pdf['reasons']);
        self::assertSame([], $pdf['attachments']);
        self::assertNull($pdf['main_image']);
    }

    public function testWithoutTheDatabaseStepTheNoticeIsPlacedWithAClearMessage(): void
    {
        Gate::getInstance()->setPermissions(['admin.access', 'atak.sse.case.manage', 'atak.sse.clearance.confidentiel', 'atak.sse.clearance.encadrement']);
        $this->pdo->exec('DROP TABLE sse_wanted_attachments');
        $this->pdo->exec('DROP TABLE sse_wanted_notice_extras');
        SseWantedExtrasRepository::forgetReady();
        self::assertFalse(SseWantedExtrasRepository::ready());
        $id = $this->identity();
        $res = (new SseWantedService())->place(1, $id, self::input(['codes_reasons' => ['M-TER-01']]), self::manager());
        self::assertTrue($res['ok'], 'l’avis est placé');
        self::assertCount(1, $res['warnings']);
        self::assertStringContainsString('étape de base de données', $res['warnings'][0]);
        self::assertSame('Participation présumée à une attaque coordonnée.', (new SseWantedNoticeRepository())->find(1, $res['id'])['reason']);
        self::assertSame([], (new SseWantedExtrasRepository())->forNotice(1, $res['id'])['codes']['reasons']);
        // Liste filtrée par code : sans la table, le filtre est ignoré au lieu de casser la page.
        self::assertCount(1, (new SseWantedNoticeRepository())->list(1, ['status' => 'active', 'codes' => ['M-TER-01'], 'q' => 'Karimzadeh', 'q_codes' => ['M-TER-01']]));
        $controller = new SseWantedNoticeController(null, null, null, null, null, null, null, self::manager());
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $list = $controller->index(new Request());
        self::assertSame(200, $list->statusCode());
        self::assertStringNotContainsString('Warning', $list->body());
        $form = $controller->placeForm(new Request(), ['id' => (string) $this->identity('Quatrième')]);
        self::assertSame(200, $form->statusCode());
        self::assertStringContainsString('étape de base de données « codification des avis »', $form->body());
    }

    // ------------------------------------------------------------------ liste : badges, filtre, recherche

    public function testListFiltersAndSearchesByCodeAndByLabel(): void
    {
        $a = (new SseWantedService())->place(1, $this->identity('Alpha'), self::input(['codes_reasons' => ['M-TRF-02'], 'codes_measures' => ['A-EXF']]), self::manager());
        $b = (new SseWantedService())->place(1, $this->identity('Bravo'), self::input(['codes_reasons' => ['M-TER-01'], 'codes_measures' => ['A-OBS']]), self::manager());
        $c = (new SseWantedService())->place(1, $this->identity('Charlie'), self::input(), self::manager());
        $repo = new SseWantedNoticeRepository();
        $ids = static fn (array $rows): array => array_map(static fn (array $n): int => $n['id'], $rows);

        self::assertSame([$a['id']], $ids($repo->list(1, ['status' => 'active', 'codes' => ['M-TRF-02']])));
        self::assertSame([$b['id']], $ids($repo->list(1, ['status' => 'active', 'codes' => ['A-OBS']])));
        $both = $ids($repo->list(1, ['status' => 'active', 'codes' => ['A-OBS', 'A-EXF']]));
        sort($both);
        self::assertSame([$a['id'], $b['id']], $both);
        self::assertSame([], $repo->list(1, ['status' => 'active', 'codes' => ['M-VIO-05']]));
        self::assertSame([], $repo->list(1, ['status' => 'active', 'codes' => ['pas un code; DROP TABLE']]), 'code mal formé : aucun résultat');
        // La recherche libre trouve un libellé de code (« passeur »), un nom et une référence.
        $q = 'passeur';
        self::assertSame([$a['id']], $ids($repo->list(1, ['status' => 'active', 'q' => $q, 'q_codes' => SseWantedCodes::matching($q, []) ])));
        self::assertSame([$c['id']], $ids($repo->list(1, ['status' => 'active', 'q' => 'Charlie', 'q_codes' => SseWantedCodes::matching('Charlie', [])])));
        // Un autre locataire ne voit rien.
        self::assertSame([], $repo->list(2, ['status' => 'active', 'codes' => ['M-TRF-02']]));
    }

    public function testViewsRenderCodesPhysicalAndFilterWithRealData(): void
    {
        Gate::getInstance()->setPermissions(['admin.access', 'atak.sse.case.manage', 'atak.sse.clearance.confidentiel', 'atak.sse.clearance.encadrement']);
        $id = $this->identity('Karimzadeh');
        $res = (new SseWantedService())->place(1, $id, self::input([
            'codes_reasons' => ['M-TER-01', 'M-FIN-01'], 'codes_measures' => ['A-DNG', 'A-OBS'],
            'physical' => ['sex' => 'male', 'height_cm' => '181', 'eyes' => 'green', 'scars' => 'Cicatrice à la joue gauche', 'equipment' => ['rifle']],
        ]), self::manager());
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];
        $fiche = (new \App\Controllers\Web\SseIdentityRegistryController())->identityShow(new Request(), ['id' => $id]);
        $body = $fiche->body();
        self::assertSame(200, $fiche->statusCode());
        foreach (['M-TER-01', 'Planification d’attaque', 'M-FIN-01', 'A-DNG', 'Signalement physique', 'Cicatrice à la joue gauche', 'Arme d’épaule', 'sse-wanted-codes.css'] as $needle) {
            self::assertStringContainsString($needle, $body, $needle);
        }
        self::assertStringContainsString('code=M-TER-01', $body, 'le badge mène à la liste filtrée');
        self::assertStringNotContainsString('Warning', $body);

        $controller = new SseWantedNoticeController(null, null, null, null, null, null, null, self::manager());
        $_GET = ['statut' => 'active', 'code' => 'M-TER-01'];
        $list = $controller->index(new Request());
        self::assertStringContainsString($res['ref'], $list->body());
        self::assertStringContainsString('M-TER-01', $list->body());
        $_GET = ['statut' => 'active', 'code' => 'A-EXF'];
        self::assertStringNotContainsString($res['ref'], $controller->index(new Request())->body(), 'filtre par code');
        $_GET = ['statut' => 'active', 'q' => 'financeur'];
        self::assertStringContainsString($res['ref'], $controller->index(new Request())->body(), 'recherche par libellé de code');
    }

    public function testPlaceFormRendersPrefilledFromTheRecordWithTheCodePicker(): void
    {
        Gate::getInstance()->setPermissions(['admin.access', 'atak.sse.case.manage', 'atak.sse.clearance.confidentiel', 'atak.sse.clearance.encadrement']);
        $id = $this->identity();
        (new SseFileRecordRepository())->saveProfile(1, $id, ['height_cm' => 177, 'weight_kg' => 82, 'build' => 'muscular', 'hair' => 'black', 'eyes' => 'brown', 'marks' => 'Tatouage sur le cou'], 7);
        $controller = new SseWantedNoticeController(null, null, null, null, null, null, null, self::manager());
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $resp = $controller->placeForm(new Request(), ['id' => (string) $id]);
        $body = $resp->body();
        self::assertSame(200, $resp->statusCode());
        foreach (['data-wc-form', 'enctype="multipart/form-data"', 'id="wc-data"', 'name="phys_height_cm" value="177"', 'Tatouage sur le cou', 'Modèles d’avis', 'Placer l’avis', 'sse-wanted-form.js', 'name="att_file[]"'] as $needle) {
            self::assertStringContainsString($needle, $body, $needle);
        }
        self::assertStringContainsString('M-TER-01', $body, 'catalogue transmis au sélecteur');
        self::assertStringContainsString('"cell_leader"', $body);
        // Pas d'étiquette au-dessus des titres : le formulaire n'en a pas.
        self::assertDoesNotMatchRegularExpression('/class="[^"]*eyebrow/i', $body);
    }

    public function testSettingsScreenSavesTheCodificationTabAndKeepsItWhenTheTabIsAbsent(): void
    {
        Gate::getInstance()->setPermissions(['admin.access', 'atak.sse.case.manage', 'atak.sse.clearance.confidentiel', 'atak.sse.clearance.encadrement']);
        $controller = new SseWantedNoticeController(null, null, null, null, null, null, null, self::manager());
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $page = $controller->settings(new Request());
        self::assertSame(200, $page->statusCode());
        foreach (['Codification', 'name="codes_present"', 'M-TER-01', 'name="codes_default_reasons[poi]"', 'Modèles d’avis'] as $needle) {
            self::assertStringContainsString($needle, $page->body(), $needle);
        }

        $base = ['authority_fr' => 'Bureau fictif', 'authority_en' => 'Fictional bureau', 'ref_format' => 'A-{N}', '_csrf_token' => 'jeton'];
        $_POST = $base + [
            'codes_present' => '1', 'codes_row' => ['M-TER-01', 'M-TER-02', 'A-OBS'], 'codes_enabled' => ['M-TER-01', 'A-OBS'],
            'codes_fr' => ['M-TER-01' => 'Projet d’attentat'], 'codes_custom' => 'M-ABC-01|Mon motif|My reason|Mon texte.|My text.|medium',
            'codes_default_reasons' => ['poi' => 'M-ABC-01'], 'codes_default_measures' => ['poi' => 'A-OBS'],
        ];
        self::assertSame(302, $controller->settingsSave(new Request())->statusCode());
        $config = (new SseWantedSettingsRepository())->get(1);
        self::assertSame(['M-TER-02'], $config['codes']['disabled']);
        self::assertSame('Projet d’attentat', SseWantedCodes::find('M-TER-01', $config)['fr']);
        self::assertSame(['M-ABC-01'], SseWantedCodes::defaultsForType('poi', $config)['reasons']);

        // Un envoi de l'ancien formulaire (sans l'onglet) ne perd rien.
        $_POST = array_merge($base, ['authority_fr' => 'Bureau fictif 2']);
        $controller->settingsSave(new Request());
        $after = (new SseWantedSettingsRepository())->get(1);
        self::assertSame('Bureau fictif 2', $after['authority_fr']);
        self::assertSame($config['codes'], $after['codes']);
    }

    // ------------------------------------------------------------------ exposition au jeu et au PDF

    public function testGameAndPdfShapesAreStableAndCarryLabelsInBothLanguages(): void
    {
        $id = $this->identity();
        $res = (new SseWantedService())->place(1, $id, self::input([
            'codes_reasons' => ['M-TRF-02'], 'codes_measures' => ['A-EXF', 'A-REP'], 'zone' => 'Frontière nord',
            'physical' => ['height_cm' => '175', 'hair' => 'gray', 'marks' => str_repeat('x', 200)],
        ]), self::manager());
        $game = SseWantedNoticeData::forGame(1);
        self::assertCount(1, $game);
        $n = $game[0];
        self::assertSame(['id', 'ref', 'name', 'type', 'threat', 'armed', 'zone', 'reasons', 'measures', 'physical', 'images'], array_keys($n));
        self::assertSame([['code' => 'M-TRF-02', 'fr' => 'Passeur', 'en' => 'Smuggler']], $n['reasons']);
        self::assertSame(['A-EXF', 'A-REP'], array_column($n['measures'], 'code'));
        self::assertSame('Signaler tout mouvement transfrontalier', $n['measures'][0]['fr']);
        self::assertSame(80, mb_strlen($n['physical']['marks']));
        self::assertSame('175 cm · cheveux gris', $n['physical']['summary_fr']);
        self::assertSame([], $n['images']);

        $pdf = SseWantedNoticeData::forPdf((new SseWantedNoticeRepository())->find(1, $res['id']), 'en');
        self::assertSame([['code' => 'M-TRF-02', 'label' => 'Smuggler']], $pdf['reasons']);
        self::assertSame('Report any cross-border movement', $pdf['measures'][0]['label']);
        self::assertSame('175 cm · hair gray', $pdf['physical_line']);
        self::assertSame('Height', $pdf['physical_rows'][0]['label']);
    }

    // ------------------------------------------------------------------ migration

    public function testMigrationStepIsRegisteredRerunnableAndReversible(): void
    {
        $root = dirname(__DIR__, 2);
        $registry = (array) require $root . '/database/steps/registry.php';
        $entry = array_values(array_filter($registry, static fn (array $e): bool => $e['id'] === 'sse_wanted_codes'));
        self::assertCount(1, $entry);
        self::assertSame('schema', $entry[0]['group']);
        self::assertTrue($entry[0]['rerun']);
        self::assertSame('4690_sse_wanted_codes.php', $entry[0]['file']);
        self::assertFileExists($root . '/database/steps/' . $entry[0]['file']);
        $numbers = array_map(static fn (array $e): int => (int) substr((string) $e['file'], 0, 4), $registry);
        self::assertSame(count($numbers), count(array_unique($numbers)), 'numéros d’étape uniques');
        $down = (string) file_get_contents($root . '/migrations/rollback/sse_wanted_codes_down.sql');
        $sql = (string) file_get_contents($root . '/migrations/2026_11_16_130_sse_wanted_codes.sql');
        foreach (SseWantedSqlite::CODE_TABLES as $table) {
            self::assertStringContainsString('DROP TABLE IF EXISTS ' . $table . ';', $down);
            self::assertStringContainsString('CREATE TABLE IF NOT EXISTS ' . $table . ' ', $sql);
        }
        // Aucune colonne ajoutée à une table existante, aucune donnée touchée, rien de propre à MySQL en dehors du DDL traduit.
        self::assertStringNotContainsString('ALTER TABLE', $sql);
        self::assertStringNotContainsString('INSERT INTO', $sql);
        self::assertStringNotContainsString('ADD UNIQUE', $sql);

        // L'étape elle-même : crée les tables manquantes, puis ne change plus rien.
        $scratch = new \Tests\Support\MysqlDdlSqlitePdo();
        $scratch->exec('CREATE TABLE tenants (id INTEGER PRIMARY KEY, name TEXT, slug TEXT)');
        $inspector = new \App\Support\Migrations\SchemaInspector($scratch);
        $inspector->runSqlFile(SseWantedSqlite::sqlFile());
        $pdo = $scratch;
        $run = static function () use ($pdo, $root): string {
            ob_start();
            (static function () use ($pdo, $root): void {
                require $root . '/database/steps/4690_sse_wanted_codes.php';
            })();

            return (string) ob_get_clean();
        };
        self::assertStringContainsString('Tables créées', $run());
        foreach (SseWantedSqlite::CODE_TABLES as $table) {
            self::assertTrue($inspector->tableExists($table), $table);
        }
        self::assertStringContainsString('déjà présentes', $run());
    }
}
