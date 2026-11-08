<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\ArsenalWardrobeController;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Repositories\ArsenalWardrobeRepository;
use App\Services\Platform\FeatureGateService;
use App\Support\ArsenalLoadoutItems;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Page Équipement : chaque membre supprime, renomme et règle la visibilité de SES tenues seulement, et « Copier pour
 * ACE » ne livre qu'une tenue visible par lui. Dépôt réel sur sqlite (information_schema, DATABASE() et NOW() simulés).
 */
final class ArsenalWardrobeManageTest extends TestCase
{
    private const TENANT = 4;

    private const OWNER = 10;

    private const OTHER = 11;

    private const LOADOUT = '[["arifle_MX_F","","","",["30Rnd_65x39_caseless_mag",30],[],""],[],[],["U_B_CombatUniform_mcam",[]],[],[],"H_HelmetB","",[],["ItemMap","","","","",""]]';

    private PDO $pdo;

    private ArsenalWardrobeRepository $repo;

    private ?PDO $savedPdo = null;

    private bool $savedSessionStarted = false;

    /** @var array{set: bool, value: mixed} */
    private array $savedSession = ['set' => false, 'value' => null];

    private mixed $savedPost = null;

    private bool $hadPost = false;

    private mixed $savedFiles = null;

    protected function setUp(): void
    {
        parent::setUp();
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->hadPost = isset($_POST);
        $this->savedPost = $_POST ?? null;
        $_POST = [];
        $this->savedFiles = $_FILES ?? null;
        $_FILES = [];

        $this->pdo = self::schema();
        $db = new \ReflectionProperty(Database::class, 'pdo');
        $saved = $db->getValue();
        $this->savedPdo = $saved instanceof PDO ? $saved : null;
        $db->setValue(null, $this->pdo);

        $started = new \ReflectionProperty(Session::class, 'started');
        $this->savedSessionStarted = (bool) $started->getValue();
        $started->setValue(null, true);
        $this->savedSession = ['set' => isset($_SESSION), 'value' => $_SESSION ?? null];
        $_SESSION = ['tenant_id' => self::TENANT, 'user_id' => self::OWNER];

        $this->repo = new ArsenalWardrobeRepository($this->pdo);
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(Database::class, 'pdo'))->setValue(null, $this->savedPdo);
        (new \ReflectionProperty(Session::class, 'started'))->setValue(null, $this->savedSessionStarted);
        if ($this->savedSession['set']) {
            $_SESSION = $this->savedSession['value'];
        } else {
            unset($_SESSION);
        }
        if ($this->hadPost) {
            $_POST = $this->savedPost;
        } else {
            unset($_POST);
        }
        $_FILES = is_array($this->savedFiles) ? $this->savedFiles : [];
        parent::tearDown();
    }

    public function testOwnerDeletesOwnTenue(): void
    {
        $id = $this->insert(self::OWNER, 'Assaut');
        $this->post(['_csrf_token' => Csrf::token()]);

        $this->controller()->destroyWardrobe(new Request(), ['id' => (string) $id]);

        self::assertSame(0, $this->stored($id));
        self::assertSame('Tenue supprimée.', Session::getFlash('success'));
    }

    public function testMemberCannotDeleteSomeoneElsesTenue(): void
    {
        $id = $this->insert(self::OTHER, 'Tenue du voisin');
        $this->post(['_csrf_token' => Csrf::token()]);

        $this->controller()->destroyWardrobe(new Request(), ['id' => (string) $id]);

        self::assertSame(1, $this->stored($id));
        self::assertSame('Vous ne pouvez supprimer que vos propres tenues.', Session::getFlash('error'));
    }

    public function testDeleteWithoutValidSessionTokenKeepsTheTenue(): void
    {
        $id = $this->insert(self::OWNER, 'Assaut');
        Csrf::token();
        $this->post(['_csrf_token' => 'faux']);

        $this->controller()->destroyWardrobe(new Request(), ['id' => (string) $id]);

        self::assertSame(1, $this->stored($id));
    }

    public function testOwnerRenamesNotesAndHidesTenue(): void
    {
        $id = $this->insert(self::OWNER, 'Assaut');
        $this->post([
            '_csrf_token' => Csrf::token(),
            'name' => '  Assaut   nuit ',
            'visibility' => 'personal',
            'notes' => 'Pour les opérations de nuit',
        ]);

        $this->controller()->updateWardrobe(new Request(), ['id' => (string) $id]);

        $row = $this->row($id);
        self::assertSame('Assaut nuit', $row['name']);
        self::assertSame('assaut-nuit', $row['slug']);
        self::assertSame('personal', $row['visibility']);
        self::assertSame('Pour les opérations de nuit', $row['notes']);
        // Le contenu de la tenue et son empreinte (comparée par le mod) ne bougent pas.
        self::assertSame(self::LOADOUT, $row['payload_text']);
        self::assertSame(hash('sha256', self::LOADOUT), $row['payload_sha256']);
    }

    public function testRenameRefusesANameAlreadyUsedByAnotherOfHisTenues(): void
    {
        $this->insert(self::OWNER, 'Recon');
        $id = $this->insert(self::OWNER, 'Assaut');
        $this->post(['_csrf_token' => Csrf::token(), 'name' => 'recon']);

        $this->controller()->updateWardrobe(new Request(), ['id' => (string) $id]);

        self::assertSame('Assaut', $this->row($id)['name']);
        self::assertStringContainsString('déjà une tenue portant ce nom', (string) Session::getFlash('error'));
    }

    public function testMemberCannotEditSomeoneElsesTenue(): void
    {
        $id = $this->insert(self::OTHER, 'Tenue du voisin');
        $this->post(['_csrf_token' => Csrf::token(), 'name' => 'Volée', 'visibility' => 'personal', 'notes' => 'x']);

        $this->controller()->updateWardrobe(new Request(), ['id' => (string) $id]);

        $row = $this->row($id);
        self::assertSame('Tenue du voisin', $row['name']);
        self::assertSame('tenant', $row['visibility']);
        self::assertNull($row['notes']);
        self::assertSame('not_found', $this->repo->renameWardrobe(self::TENANT, self::OWNER, $id, 'Volée'));
        self::assertFalse($this->repo->updateWardrobeVisibility(self::TENANT, self::OWNER, $id, 'personal'));
    }

    public function testPersonalTenueIsHiddenFromOtherMembers(): void
    {
        $hidden = $this->insert(self::OTHER, 'Secret', 'personal');
        $shared = $this->insert(self::OTHER, 'Partagée');

        $ids = array_column($this->repo->listAccessibleWardrobes(self::TENANT, self::OWNER), 'id');
        self::assertContains($shared, $ids);
        self::assertNotContains($hidden, $ids);
        self::assertContains($hidden, array_column($this->repo->listAccessibleWardrobes(self::TENANT, self::OTHER), 'id'));
        self::assertSame(['Partagée'], array_column($this->repo->exportPayloads(self::TENANT, null, self::OWNER), 'name'));

        $_GET['tenue'] = (string) $hidden;
        try {
            $res = $this->controller()->aceExport(new Request());
        } finally {
            unset($_GET['tenue']);
        }
        self::assertSame(302, $res->statusCode(), 'la tenue réservée de quelqu’un d’autre ne se copie pas');
    }

    public function testCopyForAceReturnsOneValidLoadout(): void
    {
        $stored = str_replace('"', '""', '[' . self::LOADOUT . ',[["ace_earplugs",true]]]');
        $id = $this->insert(self::OWNER, 'Assaut', 'tenant', $stored);
        $_GET['tenue'] = (string) $id;
        $_GET['inline'] = '1';
        try {
            $res = $this->controller()->aceExport(new Request());
        } finally {
            unset($_GET['tenue'], $_GET['inline']);
        }

        self::assertSame(200, $res->statusCode());
        $data = ArsenalLoadoutItems::decode($res->body());
        self::assertIsArray($data);
        self::assertCount(2, $data, '[tenue, infos étendues] : une seule tenue');
        self::assertCount(10, $data[0]);
        self::assertSame([['ace_earplugs', true]], $data[1]);
    }

    public function testEachTenueCardOffersCopyAndOwnerActions(): void
    {
        $root = dirname(__DIR__, 2);
        $hub = (string) file_get_contents($root . '/views/equipment/hub.php');
        $card = (string) file_get_contents($root . '/views/equipment/_wardrobe_card.php');
        $dialogs = (string) file_get_contents($root . '/views/equipment/_dialogs.php');
        $js = (string) file_get_contents($root . '/public/assets/js/equipment-catalog.js');

        self::assertStringContainsString('data-eq-ace-copy-card="<?= $wid ?>" aria-label="Copier pour ACE"', $card);
        self::assertStringContainsString('L’import de l’arsenal ACE ne prend qu’une tenue à la fois', $dialogs, 'l’aide ACE est dans la fenêtre d’aide de la page');
        self::assertStringContainsString('data-eq-ace-export', $hub);
        // Modifier / Supprimer : sur ses propres tenues seulement.
        $mineBlock = substr($card, (int) strpos($card, '<?php if ($isMine): ?>', (int) strpos($card, 'data-eq-ace-copy-card')), 900);
        self::assertStringContainsString('data-eq-edit-card=', $mineBlock);
        self::assertStringContainsString('data-eq-qv-delete', $dialogs, 'la suppression est dans la fenêtre de la tenue');
        self::assertStringContainsString('name="name" required maxlength="120" data-eq-qv-name', $dialogs);
        self::assertStringContainsString('<option value="personal">Moi seul</option>', $dialogs);
        self::assertStringContainsString("'?tenue=' + encodeURIComponent(id)", $js);
        self::assertStringContainsString('qvOpenInEdit', $js);
    }

    private function controller(): ArsenalWardrobeController
    {
        $gate = $this->createMock(FeatureGateService::class);
        $gate->method('allows')->willReturn(true);

        return new ArsenalWardrobeController($this->repo, $gate);
    }

    /** @param array<string, mixed> $post */
    private function post(array $post): void
    {
        $_POST = $post;
        $_SERVER['REQUEST_METHOD'] = 'POST';
    }

    private function insert(int $userId, string $name, string $visibility = 'tenant', string $payload = self::LOADOUT): int
    {
        $this->pdo->prepare(
            'INSERT INTO arsenal_wardrobes (tenant_id, user_id, name, slug, visibility, payload_text, payload_sha256, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        )->execute([self::TENANT, $userId, $name, ArsenalWardrobeRepository::slugify($name), $visibility, $payload, hash('sha256', $payload)]);

        return (int) $this->pdo->lastInsertId();
    }

    private function stored(int $id): int
    {
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM arsenal_wardrobes WHERE id = ?');
        $st->execute([$id]);

        return (int) $st->fetchColumn();
    }

    /** @return array<string, mixed> */
    private function row(int $id): array
    {
        $st = $this->pdo->prepare('SELECT * FROM arsenal_wardrobes WHERE id = ?');
        $st->execute([$id]);

        return $st->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    private static function schema(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->sqliteCreateFunction('DATABASE', static fn (): string => 'main', 0);
        $pdo->sqliteCreateFunction('NOW', static fn (): string => date('Y-m-d H:i:s'), 0);
        $pdo->sqliteCreateFunction('CHAR_LENGTH', static fn (?string $s): int => mb_strlen((string) $s), 1);
        $pdo->exec("ATTACH DATABASE ':memory:' AS information_schema");
        $pdo->exec('CREATE TABLE information_schema.TABLES (TABLE_SCHEMA TEXT, TABLE_NAME TEXT)');
        $pdo->exec('CREATE TABLE information_schema.COLUMNS (TABLE_SCHEMA TEXT, TABLE_NAME TEXT, COLUMN_NAME TEXT)');
        foreach (['arsenal_wardrobes', 'arsenal_equipment_collections', 'arsenal_collection_wardrobes', 'users'] as $table) {
            $pdo->prepare("INSERT INTO information_schema.TABLES VALUES ('main', ?)")->execute([$table]);
        }
        foreach (['cover_image_path', 'description', 'gallery_json', 'visibility'] as $column) {
            $pdo->prepare("INSERT INTO information_schema.COLUMNS VALUES ('main', 'arsenal_wardrobes', ?)")->execute([$column]);
        }
        $pdo->prepare("INSERT INTO information_schema.COLUMNS VALUES ('main', 'arsenal_equipment_collections', 'cover_image_path')")->execute();

        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, callsign TEXT, display_name TEXT)');
        $pdo->exec("INSERT INTO users (id, callsign, display_name) VALUES (10, 'ALPHA', 'Alpha'), (11, 'BRAVO', 'Bravo')");
        $pdo->exec('CREATE TABLE arsenal_equipment_collections (
            id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, owner_user_id INTEGER, name TEXT NOT NULL,
            slug TEXT NOT NULL, description TEXT, cover_image_path TEXT, visibility TEXT NOT NULL DEFAULT \'personal\',
            tags_json TEXT, sort_order INTEGER NOT NULL DEFAULT 0, created_at TEXT, updated_at TEXT)');
        $pdo->exec('CREATE TABLE arsenal_collection_wardrobes (collection_id INTEGER NOT NULL, wardrobe_id INTEGER NOT NULL, sort_order INTEGER NOT NULL DEFAULT 0)');
        $pdo->exec('CREATE TABLE arsenal_wardrobes (
            id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, user_id INTEGER NOT NULL, steam_uid TEXT,
            collection_id INTEGER, visibility TEXT NOT NULL DEFAULT \'tenant\', name TEXT NOT NULL, slug TEXT NOT NULL,
            source TEXT NOT NULL DEFAULT \'ace_arsenal\', payload_format TEXT NOT NULL DEFAULT \'arma_loadout_str\',
            payload_text TEXT NOT NULL, payload_sha256 TEXT NOT NULL, notes TEXT, description TEXT, cover_image_path TEXT,
            gallery_json TEXT, is_favorite INTEGER NOT NULL DEFAULT 0, last_synced_at TEXT, created_at TEXT, updated_at TEXT)');

        return $pdo;
    }
}
