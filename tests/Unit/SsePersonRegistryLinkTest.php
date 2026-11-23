<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\SsePersonRegistryController;
use App\Controllers\Web\SsePortalController;
use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Repositories\SseFileRecordRepository;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SsePersonRepository;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SsePersonRegistryLinkService;
use App\Services\Sse\SseWantedAccess;
use App\Services\Sse\SseWantedService;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseWantedSqlite;

/**
 * Fiche personne (/atak/sse/identites/{id}) : mène à la fiche du registre quand la personne a une identité (avis de
 * recherche, notice PDF, historique, détections, images du sujet), propose de la créer sinon, garde les identités
 * cachées pour l'habilitation Confidentiel et ne crée jamais deux identités pour une même personne.
 */
final class SsePersonRegistryLinkTest extends TestCase
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
        $this->pdo = SseWantedSqlite::open();
        Csrf::token();
        $_SESSION = [
            'user_id' => 7, 'tenant_id' => 1, 'callsign' => 'ANALYSTE', '_csrf_token' => 'jeton',
            'sse_tenant_id' => 1, 'sse_guest' => false, 'sse_clearance_until' => time() + 3600, 'sse_access_code_id' => 0,
            'sse_confidentiality_version' => SseAccessCodeService::CONFIDENTIALITY_VERSION, 'sse_confidentiality_tenant_id' => 1, 'sse_confidentiality_code_id' => 0,
        ];
        $_POST = [];
        $_GET = [];
        Gate::reset();
        Gate::getInstance()->setPermissions(['admin.access', 'atak.sse.case.manage', 'atak.sse.clearance.confidentiel', 'atak.sse.clearance.encadrement']);
        // Fiches personnes minimales (la migration d'origine est écrite pour MySQL).
        $this->pdo->exec('CREATE TABLE sse_persons (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, context_id INTEGER DEFAULT 1, status TEXT DEFAULT \'civil\', last_name TEXT DEFAULT \'\', first_name TEXT DEFAULT \'\', alias TEXT, sex_apparent TEXT, birth_date TEXT, birth_place TEXT, nationality TEXT, primary_photo_id INTEGER, bio_key TEXT, created_at TEXT, updated_at TEXT)');
        $this->pdo->exec('CREATE TABLE sse_person_photos (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, person_id INTEGER, image_path TEXT, angle TEXT, created_at TEXT)');
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

    private function person(int $tenant = 1, string $last = 'Karimzadeh', string $first = 'Élodie', string $nat = 'TAK'): int
    {
        $this->pdo->prepare('INSERT INTO sse_persons (tenant_id, last_name, first_name, sex_apparent, birth_date, nationality, created_at, updated_at) VALUES (?, ?, ?, \'M\', \'1984-03-12\', ?, \'2026-11-01 10:00:00\', \'2026-11-01 10:00:00\')')->execute([$tenant, $last, $first, $nat]);

        return (int) $this->pdo->lastInsertId();
    }

    private function identity(int $personId, string $kind = 'civil', int $tenant = 1): int
    {
        return (new SseIdentityRepository())->create($tenant, ['last_name' => 'Karimzadeh', 'first_name' => 'Élodie', 'kind' => $kind, 'person_id' => $personId]);
    }

    public function testTheCivilIdentityComesFirstAndSecretOnesNeedTheTruthClearance(): void
    {
        $person = $this->person();
        $cover = $this->identity($person, 'cover');
        $civil = $this->identity($person, 'civil');
        $links = new SsePersonRegistryLinkService();

        $withTruth = $links->identitiesOf(1, $person, true);
        self::assertSame([$civil, $cover], array_column($withTruth, 'id'));
        self::assertSame($civil, $links->target(1, $person, true)['id']);
        self::assertSame([$civil], array_column($links->identitiesOf(1, $person, false), 'id'));

        $onlyCover = $this->person(1, 'Dupont', 'Jean');
        $this->identity($onlyCover, 'forged');
        self::assertNull($links->target(1, $onlyCover, false), 'Une identité falsifiée ne se révèle pas sans l’habilitation');
        self::assertTrue($links->hasAny(1, $onlyCover));
        self::assertNull($links->target(2, $person, true), 'Autre communauté');
    }

    public function testRegistryIdentityIsCreatedOnceFromThePersonRecord(): void
    {
        $personId = $this->person(1, 'Karimzadeh', 'Élodie', 'Takistan');
        $person = (new SsePersonRepository())->findById($personId, 1);
        self::assertNotNull($person);
        $links = new SsePersonRegistryLinkService();
        $id = $links->createFor(1, $person, 7);
        self::assertNotNull($id);
        $identity = (new SseIdentityRepository())->find($id, 1);
        self::assertSame($personId, $identity['person_id']);
        self::assertSame('Karimzadeh', $identity['last_name']);
        self::assertSame('M', $identity['sex']);
        self::assertSame('1984-03-12', $identity['birth_date']);
        self::assertNull($identity['nationality'], 'Un nom de pays libre n’est pas un code à trois lettres');
        self::assertNull($links->createFor(1, $person, 7), 'Jamais deux identités pour une personne');
        self::assertNull($links->createFor(1, ['id' => 0, 'last_name' => 'X'], 7));
        $empty = $this->person(1, '', '');
        self::assertNull($links->createFor(1, (new SsePersonRepository())->findById($empty, 1) ?? [], 7));
    }

    public function testThePersonPageLeadsToTheRegistryRecordUnlessAskedForThePersonView(): void
    {
        $personId = $this->person();
        $identityId = $this->identity($personId);
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $controller = new SsePortalController();

        $response = $controller->identityShow(new Request(), ['id' => $personId]);
        self::assertSame(302, $response->statusCode());
        self::assertStringEndsWith('atak/sse/papiers/identites/' . $identityId, (string) $response->headerValue('Location'));

        // Une personne d'une autre communauté ne mène nulle part.
        $foreign = $this->person(2, 'Autre', 'Personne');
        $this->identity($foreign, 'civil', 2);
        self::assertSame(302, $controller->identityShow(new Request(), ['id' => $foreign])->statusCode());
        self::assertStringEndsWith('atak/sse/identites', (string) $controller->identityShow(new Request(), ['id' => $foreign])->headerValue('Location'));
    }

    /** @param array<string, mixed> $registry */
    private static function renderBlock(array $registry, array $person): string
    {
        ob_start();
        (static function () use ($registry, $person): void {
            require dirname(__DIR__, 2) . '/views/atak/sse/_person_registry.php';
        })();

        return (string) ob_get_clean();
    }

    public function testTheRegistryBlockRendersForEachState(): void
    {
        $personId = $this->person();
        $person = (new SsePersonRepository())->findById($personId, 1);
        self::assertNotNull($person);
        $access = new SseWantedAccess(false, true, true, true, 1, 7, 'ANALYSTE');
        $links = new SsePersonRegistryLinkService();

        $none = self::renderBlock($links->pageData(1, $person, $access, false), $person);
        self::assertStringContainsString('Créer l’identité du registre', $none);
        self::assertStringContainsString('identites/' . $personId . '/registre', $none);
        self::assertStringContainsString('name="_csrf_token"', $none);

        $identityId = $this->identity($personId);
        (new SseWantedService())->place(1, $identityId, ['notice_type' => 'hvt', 'threat_level' => 'high', 'armed' => 'yes', 'reason' => 'Motif <i>test</i>.'], $access);
        (new SseFileRecordRepository())->addPhoto(1, ['identity_id' => $identityId, 'path' => 'sse-seek/1/0123456789abcdef.jpg', 'mime' => 'image/jpeg', 'source' => 'seek', 'source_ref' => 'REC-1'], 7);
        $linked = self::renderBlock($links->pageData(1, $person, $access, false), $person);
        self::assertStringContainsString('papiers/identites/' . $identityId, $linked);
        self::assertStringContainsString('Sous avis de recherche', $linked);
        self::assertStringContainsString('/pdf', $linked);
        self::assertStringContainsString('Images du sujet (SEEK II)', $linked);
        self::assertStringContainsString('/atak/sse/fichiers/photos/', $linked);
        self::assertStringNotContainsString('Créer l’identité du registre', $linked);

        $this->identity($this->person(1, 'Dupont', 'Jean'), 'cover');
        $hiddenPerson = (new SsePersonRepository())->findById((int) $this->pdo->query('SELECT MAX(id) FROM sse_persons')->fetchColumn(), 1);
        $blind = new SseWantedAccess(false, true, true, false, 1, 7, 'ANALYSTE');
        self::assertSame([], $links->identitiesOf(1, (int) $hiddenPerson['id'], false));
    }

    public function testCreateActionNeedsTheTokenTheRightsAndTheCommunity(): void
    {
        $personId = $this->person();
        $other = $this->person(2, 'Autre', 'Personne');
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $controller = new SsePersonRegistryController();

        $_POST = [];
        $controller->create(new Request(), ['id' => $personId]);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_identities')->fetchColumn(), 'Sans jeton : rien');
        $_POST = ['_csrf_token' => 'jeton'];
        Gate::getInstance()->setPermissions(['atak.sse.view']);
        $controller->create(new Request(), ['id' => $personId]);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_identities')->fetchColumn(), 'Sans le droit d’écrire : rien');

        Gate::getInstance()->setPermissions(['admin.access']);
        $controller->create(new Request(), ['id' => $other]);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_identities')->fetchColumn(), 'Personne d’une autre communauté : rien');

        $ok = $controller->create(new Request(), ['id' => $personId]);
        self::assertSame(302, $ok->statusCode());
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_identities')->fetchColumn());
        self::assertStringContainsString('papiers/identites/', (string) $ok->headerValue('Location'));
        $controller->create(new Request(), ['id' => $personId]);
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_identities')->fetchColumn(), 'Rejouer ne crée pas une seconde identité');
    }

    public function testPageDataCarriesTheNoticeTheSeekImagesAndTheRights(): void
    {
        $personId = $this->person();
        $identityId = $this->identity($personId);
        $access = new SseWantedAccess(false, true, true, true, 1, 7, 'ANALYSTE');
        $notice = (new SseWantedService())->place(1, $identityId, ['notice_type' => 'hvt', 'threat_level' => 'high', 'armed' => 'yes', 'reason' => 'Motif de test.'], $access);
        self::assertTrue($notice['ok']);
        (new SseFileRecordRepository())->addPhoto(1, ['identity_id' => $identityId, 'path' => 'sse-seek/1/0123456789abcdef.jpg', 'mime' => 'image/jpeg', 'source' => 'seek', 'source_ref' => 'REC-1'], 7);

        $person = (new SsePersonRepository())->findById($personId, 1);
        self::assertNotNull($person);
        $data = (new SsePersonRegistryLinkService())->pageData(1, $person, $access, false);
        self::assertSame($identityId, $data['identity']['id']);
        self::assertSame($notice['ref'], $data['notice']['ref']);
        self::assertCount(1, $data['seekImages']);
        self::assertStringContainsString('/atak/sse/fichiers/photos/', (string) $data['seekImages'][0]['url']);
        self::assertTrue($data['canPdf']);
        self::assertTrue($data['canWanted']);
        self::assertFalse($data['canCreate'], 'Une identité existe déjà');

        $blind = new SseWantedAccess(false, false, false, false, 1, 7, 'LECTEUR', false);
        $limited = (new SsePersonRegistryLinkService())->pageData(1, $person, $blind, false);
        self::assertNull($limited['notice'], 'Sans lire la section fichiers, l’avis reste caché');
        self::assertFalse($limited['canPdf']);
    }

    private function flash(): string
    {
        return (string) \App\Core\Session::getFlash('error', '');
    }

    private function identityCount(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM sse_identities')->fetchColumn();
    }

    public function testEachRefusalOfTheCreateActionSaysItsOwnReason(): void
    {
        $personId = $this->person();
        $nameless = $this->person(1, '', '');
        $other = $this->person(2, 'Autre', 'Personne');
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $controller = new SsePersonRegistryController();

        // Jeton périmé : plus un « Action non autorisée. » unique, mais la page à recharger.
        $_POST = ['_csrf_token' => 'perime'];
        $controller->create(new Request(), ['id' => $personId]);
        self::assertSame(SsePersonRegistryController::TOKEN_EXPIRED, $this->flash());
        self::assertSame(0, $this->identityCount());

        // Droit manquant : la gestion des fichiers ouvre « Placer un avis » mais pas l'écriture dans « papiers » ; le message nomme le droit.
        $_POST = ['_csrf_token' => 'jeton'];
        Gate::getInstance()->setPermissions(['atak.sse.files.admin', 'atak.sse.clearance.confidentiel']);
        $controller->create(new Request(), ['id' => $personId]);
        self::assertSame(SsePersonRegistryLinkService::DENY_RIGHT, $this->flash());
        self::assertStringContainsString('Papiers et couvertures', SsePersonRegistryLinkService::DENY_RIGHT);
        self::assertSame(0, $this->identityCount());

        // Session invitée.
        Gate::getInstance()->setPermissions(['admin.access']);
        $_SESSION['sse_guest'] = true;
        $controller->create(new Request(), ['id' => $personId]);
        self::assertSame(SsePersonRegistryLinkService::DENY_GUEST, $this->flash());
        $_SESSION['sse_guest'] = false;

        // Personne d'une autre communauté, fiche sans nom, puis création et rejeu.
        $controller->create(new Request(), ['id' => $other]);
        self::assertStringContainsString('introuvable', $this->flash());
        $controller->create(new Request(), ['id' => $nameless]);
        self::assertStringContainsString('ni nom ni prénom', $this->flash());
        self::assertSame(0, $this->identityCount());

        // Jeton envoyé par l'en-tête (appel JS) : accepté comme un champ de formulaire.
        $_POST = [];
        $_SERVER['HTTP_X_CSRF_TOKEN'] = 'jeton';
        $created = $controller->create(new Request(), ['id' => $personId]);
        self::assertStringContainsString('papiers/identites/', (string) $created->headerValue('Location'));
        self::assertSame(1, $this->identityCount());
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);
        $_POST = ['_csrf_token' => 'jeton'];
        $controller->create(new Request(), ['id' => $personId]);
        self::assertStringContainsString('déjà rattachée', $this->flash());
        self::assertSame(1, $this->identityCount());
    }

    public function testTheCreateButtonAndTheCreateActionShareOneGuard(): void
    {
        $personId = $this->person();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['_csrf_token' => 'jeton'];
        $person = (new SsePersonRepository())->findById($personId, 1);
        self::assertNotNull($person);
        $access = new SseWantedAccess(false, true, true, true, 1, 7, 'ANALYSTE');
        $links = new SsePersonRegistryLinkService();

        foreach ([
            [['atak.sse.case.manage'], true],
            [['atak.sse.grant'], true],
            [['admin.access'], true],
            [['atak.sse.files.admin'], false],
            [['atak.sse.view'], false],
            [['atak.sse.access', 'atak.sse.clearance.confidentiel'], false],
        ] as [$permissions, $allowed]) {
            Gate::getInstance()->setPermissions($permissions);
            $page = $links->pageData(1, $person, $access, false);
            self::assertSame($allowed, $page['canCreate'], implode(',', $permissions) . ' : bouton');
            self::assertSame($allowed ? null : SsePersonRegistryLinkService::DENY_RIGHT, $page['createDenial']);
            $html = self::renderBlock($page, $person);
            self::assertSame($allowed, str_contains($html, 'Créer l’identité du registre</button>'));
            self::assertSame(!$allowed, str_contains($html, 'Droit manquant'), 'Sans le bouton, la fiche dit quel droit manque');

            (new SsePersonRegistryController())->create(new Request(), ['id' => $personId]);
            self::assertSame($allowed ? 1 : 0, $this->identityCount(), implode(',', $permissions) . ' : action = bouton');
            $this->pdo->exec('DELETE FROM sse_identities');
            \App\Core\Session::getFlash('error');
        }
    }

    public function testNewViewsImportTheirClassesAndCarryTheirTranslations(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root . '/views/atak/sse/_person_registry.php');
        self::assertStringContainsString('use App\Core\Csrf;', $view);
        self::assertStringContainsString('use App\Support\SseWantedCatalog as W;', $view);
        $en = (string) file_get_contents($root . '/lang/en/site_auto.php');
        foreach (['Dossier d’identité du registre', 'Créer l’identité du registre', 'Ouvrir le dossier d’identité', 'Images du sujet (SEEK II)'] as $text) {
            self::assertStringContainsString("'" . $text . "' =>", $en, $text);
        }
        // Les liens « Fiche personne » de la fiche du registre gardent la vue de la personne (pas de boucle).
        $identity = (string) file_get_contents($root . '/views/atak/sse/papers/identity_show.php');
        self::assertSame(3, substr_count($identity, "?fiche=personne"));
    }
}
