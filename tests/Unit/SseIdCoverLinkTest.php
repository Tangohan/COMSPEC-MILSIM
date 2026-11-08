<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Api\SseIdentityApiController;
use App\Controllers\Web\SseEditorLibraryController;
use App\Controllers\Web\SseIdWorkshopController;
use App\Core\Database;
use App\Core\Request;
use App\Repositories\SseEditorItemRepository;
use App\Repositories\SseIdCountryRepository;
use App\Repositories\SseIdDocumentRepository;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SseIdKitRepository;
use App\Repositories\SseIdOperationRepository;
use App\Repositories\SsePersonRepository;
use App\Repositories\SseWatchlistRepository;
use App\Repositories\TenantAtakConfigRepository;
use App\Repositories\TenantRepository;
use App\Services\Sse\MemberIdentityPapersService;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseClearanceService;
use App\Services\Sse\SseEditorAssignmentService;
use App\Services\Sse\SseIdCheckService;
use App\Services\Sse\SseIdDocumentLookupService;
use App\Services\Sse\SseIdentityPackService;
use App\Services\Sse\SseIdGameCoverService;
use App\Services\Sse\SseIdMassGeneratorService;
use App\Support\ComspecApiKeyAuth;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseIdentitySqlite;

/**
 * Lien Athena des couvertures : couverture née en jeu (compte du jeton, communauté, confidentialité), attribution d'un
 * contenu de la bibliothèque à un joueur, ce que reçoit le serveur de jeu, ce que voient les autres.
 */
final class SseIdCoverLinkTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $saved = [];

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $pdo = SseIdentitySqlite::open();
        $pdo->exec('CREATE TABLE game_sessions (id INTEGER PRIMARY KEY, tenant_id INTEGER, user_id INTEGER, steam_id TEXT, access_token_hash TEXT, expires_at TEXT, revoked_at TEXT)');
        $pdo->exec("INSERT INTO users (id, tenant_id, steam_id, display_name) VALUES (10, 1, '76561198000000010', 'Joueur'), (11, 1, NULL, 'Sans Steam'), (20, 2, '76561198000000020', 'Autre')");
        $this->saved = ['get' => $_GET ?? [], 'post' => $_POST ?? [], 'session' => $_SESSION ?? null, 'server' => $_SERVER];
        $_GET = [];
        $_POST = [];
        $_SESSION = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['HTTP_X_COMSPEC_KEY'], $_SERVER['HTTP_X_ATAK_TOKEN']);
        ComspecApiKeyAuth::resetForTests();
        SseIdMassGeneratorService::useAuditSink(static function (array $entry): void {
        });
    }

    protected function tearDown(): void
    {
        SseIdMassGeneratorService::useAuditSink(null);
        SseIdentitySqlite::close();
        $_GET = $this->saved['get'];
        $_POST = $this->saved['post'];
        $_SERVER = $this->saved['server'];
        if ($this->saved['session'] === null) {
            unset($_SESSION);
        } else {
            $_SESSION = $this->saved['session'];
        }
        ComspecApiKeyAuth::resetForTests();
    }

    private function api(string $body): SseIdentityApiController
    {
        $clearance = $this->createMock(SseClearanceService::class);
        $clearance->method('allows')->willReturn(false);
        $config = $this->createMock(TenantAtakConfigRepository::class);
        $config->method('isMaintenanceEnabled')->willReturn(false);

        return new SseIdentityApiController(
            $this->createMock(SseIdentityPackService::class),
            $this->createMock(SseIdDocumentLookupService::class),
            $this->createMock(SseIdCheckService::class),
            $clearance,
            $config,
            $this->createMock(TenantRepository::class),
            static fn (): string => $body,
        );
    }

    private function gameSession(int $tenant, int $user): void
    {
        $token = str_repeat((string) $user, 64 / strlen((string) $user));
        $st = Database::getPdo()->prepare('INSERT INTO game_sessions (tenant_id, user_id, access_token_hash, expires_at) VALUES (?, ?, ?, ?)');
        $st->execute([$tenant, $user, hash('sha256', $token), '2099-01-01 00:00:00']);
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        ComspecApiKeyAuth::resetForTests();
    }

    /** @param array<string, mixed> $extra */
    private static function report(array $extra = []): string
    {
        return (string) json_encode($extra + ['kind' => 'cover_report', 'map' => '3', 'op' => 'OP-1', 'cover' => [
            'name' => 'MARKOS Petros', 'dob' => '1990-01-01', 'pob' => 'Kavala', 'address' => '12 RUE DU PORT, Kavala', 'employer' => 'Garage KARRAS',
            'contacts' => [['Eleni STAVROU', 'Voisin', '+031 1']], 'docs' => [['Carte d\'identité', 'Altis', 'A1']], 'trueName' => 'Ne doit pas rester',
        ]]);
    }

    private static function gameCoverRows(): int
    {
        return (int) Database::getPdo()->query('SELECT COUNT(*) FROM sse_id_game_covers')->fetchColumn();
    }

    public function testCoverFromTheGameBelongsToTheTokenAccountNotToTheBody(): void
    {
        $this->gameSession(1, 10);
        $response = $this->api(self::report(['uid' => '76561198000000020', 'user_id' => 20]))->checksStore(new Request());
        self::assertSame(200, $response->statusCode());
        $json = json_decode($response->body(), true);
        self::assertGreaterThan(0, $json['id']);
        self::assertStringStartsWith('saved;', $json['ref']);

        $service = new SseIdGameCoverService();
        self::assertSame([], $service->forUser(1, 20));
        self::assertSame([], $service->forUser(2, 10), 'Autre communauté : rien');
        $covers = $service->forUser(1, 10);
        self::assertCount(1, $covers);
        self::assertSame('MARKOS Petros', $covers[0]['name']);
        self::assertSame(3, $covers[0]['context_id']);
        self::assertSame('OP-1', $covers[0]['op_key']);
        self::assertArrayNotHasKey('trueName', $covers[0], 'Seuls les champs prévus sont gardés');
    }

    public function testSameContentIsNotRewrittenAndChangesUpdateTheSameRow(): void
    {
        $service = new SseIdGameCoverService();
        $body = json_decode(self::report(), true);
        $first = $service->store(1, 10, $body);
        $second = $service->store(1, 10, $body);
        self::assertSame($first['id'], $second['id']);
        self::assertStringStartsWith('unchanged;', $second['ref']);
        $body['cover']['employer'] = 'Autre';
        $third = $service->store(1, 10, $body);
        self::assertSame($first['id'], $third['id']);
        self::assertStringStartsWith('saved;', $third['ref']);
        self::assertSame(1, self::gameCoverRows());
    }

    public function testCommunityKeyAloneWritesNothingAndSaysUnlinked(): void
    {
        $service = new SseIdGameCoverService();
        self::assertSame(['id' => 0, 'ref' => 'unlinked'], $service->store(1, null, json_decode(self::report(), true)));
        self::assertSame(0, self::gameCoverRows());
        self::assertSame(['error' => 'invalid_cover'], $service->store(1, 10, ['cover' => ['dob' => '1990']]));
        self::assertSame(0, self::gameCoverRows());
    }

    public function testMissingMigrationIsAnUnavailableAnswerNotACrash(): void
    {
        $this->gameSession(1, 10);
        Database::getPdo()->exec('DROP TABLE sse_id_game_covers');
        self::assertSame(503, $this->api(self::report())->checksStore(new Request())->statusCode());
    }

    public function testStatusTellsThePlayerWhatToDo(): void
    {
        $none = SseIdGameCoverService::status(false, [], [], []);
        self::assertSame('none', $none['source']);
        self::assertStringContainsString('Steam', $none['todo']);

        $brief = ['updated_at' => '2026-11-08 18:00:00', 'created_at' => '2026-11-01 10:00:00'];
        $athena = SseIdGameCoverService::status(true, [$brief], [], [['updated_at' => '2026-11-09 10:00:00']]);
        self::assertSame('athena', $athena['source']);
        self::assertSame('2026-11-08 18:00:00', $athena['updated_at']);
        self::assertStringContainsString('Rien à faire', $athena['todo']);

        $game = SseIdGameCoverService::status(true, [], [], [['updated_at' => '2026-11-09 10:00:00']]);
        self::assertSame('game', $game['source']);
        self::assertSame('2026-11-09 10:00:00', $game['updated_at']);
    }

    /** @param array<string, mixed> $values */
    private function libraryItem(int $tenant, string $uid, array $values = []): int
    {
        return (new SseEditorItemRepository())->save($tenant, $uid, $values + [
            'item_type' => 'identity', 'title' => 'Petros Markos', 'body' => "Adresse : 12 rue du Port\nEmployeur : Garage KARRAS\nRaison du déplacement : Une livraison\nProfession :\nVit seul.",
            'marking' => 0, 'paper' => 0, 'nature' => 1, 'delivery' => 0, 'state' => 'Modèle', 'op_label' => '', 'link_label' => '', 'version' => 2, 'history_json' => '[]',
        ]);
    }

    private function pack(int $tenant): array
    {
        $watch = $this->createMock(SseWatchlistRepository::class);
        $watch->method('listActive')->willReturn([]);
        $persons = $this->createMock(SsePersonRepository::class);
        $persons->method('listForContext')->willReturn([]);

        return (new SseIdentityPackService(new SseIdDocumentRepository(), new SseIdentityRepository(), new SseIdCountryRepository(), $watch, $persons, new SseIdKitRepository(), new SseIdOperationRepository()))
            ->build($tenant, 1);
    }

    public function testLibraryCoverArrivesInThePackWithItsPapersAndStaysHidden(): void
    {
        $this->libraryItem(1, 'E1');
        $res = (new SseEditorAssignmentService())->assign(1, 'E1', 10, 'ALT', null, null, 5);
        self::assertTrue($res['ok']);
        self::assertFalse($res['again']);
        self::assertSame(1, $res['documents']);

        $pack = $this->pack(1);
        self::assertSame('76561198000000010', $pack['kits'][0]['uid']);
        self::assertCount(1, $pack['kits'][0]['documents']);
        $doc = $pack['documents'][0];
        self::assertSame((int) $doc['id'], (int) $pack['kits'][0]['documents'][0]);
        self::assertSame('cover', $doc['origin']);
        self::assertSame('id_card', $doc['type']);
        self::assertSame('Markos', $pack['identities'][0]['last_name']);
        self::assertSame('Petros', $pack['identities'][0]['first_name']);
        $brief = $pack['briefs'][0];
        self::assertSame('76561198000000010', $brief['uid']);
        self::assertSame('MARKOS Petros', $brief['cover_name']);
        self::assertSame('Garage KARRAS', $brief['employer']);
        self::assertSame('Une livraison', $brief['travel']);
        self::assertSame('12 rue du Port', $brief['address']);
        self::assertSame('Vit seul.', $brief['legend_free']);
        self::assertNotSame('', $brief['updated_at']);
        // Un ancien mod lit toujours le texte entier.
        self::assertStringContainsString('Employeur', $brief['legend']);

        // Consulter la fiche sans habilitation : ni l'identité, ni le papier, ni le kit, ni le brief.
        $papers = new MemberIdentityPapersService();
        $hidden = $papers->section(1, 10, '76561198000000010', false);
        self::assertSame([], $hidden['identities']);
        self::assertSame([], $hidden['documents']);
        self::assertSame([], $hidden['kits']);
        self::assertSame([], SseIdWorkshopController::visibleBriefs((new SseIdOperationRepository())->listBriefs(1), false));
        self::assertCount(1, SseIdWorkshopController::visibleBriefs((new SseIdOperationRepository())->listBriefs(1), true));

        // Le joueur voit son papier, jamais sa nature.
        $mine = $papers->memberView(1, 10, '76561198000000010');
        self::assertCount(1, $mine['documents']);
        self::assertArrayNotHasKey('st_origin', $mine['documents'][0]);
    }

    public function testAssigningTwiceKeepsThePapersAndFollowsTheText(): void
    {
        $this->libraryItem(1, 'E1');
        $service = new SseEditorAssignmentService();
        self::assertTrue($service->assign(1, 'E1', 10, 'ALT', null, null, 5)['ok']);
        $this->libraryItem(1, 'E1', ['body' => 'Employeur : Mairie', 'version' => 3]);
        $again = $service->assign(1, 'E1', 10, '', null, null, 5);
        self::assertTrue($again['ok']);
        self::assertTrue($again['again']);
        $pack = $this->pack(1);
        self::assertCount(1, $pack['documents']);
        self::assertSame('Mairie', $pack['briefs'][0]['employer']);
    }

    public function testNonCoverNatureDoesNotOpenAnInfiltrationAndStaysMasked(): void
    {
        $this->libraryItem(1, 'E2', ['nature' => 0, 'item_type' => 'paper', 'paper' => 1]);
        self::assertTrue((new SseEditorAssignmentService())->assign(1, 'E2', 10, 'ALT', null, null, 5)['ok']);
        $pack = $this->pack(1);
        self::assertSame('passport', $pack['documents'][0]['type']);
        self::assertSame('official', $pack['documents'][0]['origin']);
        self::assertNull($pack['briefs'][0]['identity_id']);
        self::assertSame('', $pack['briefs'][0]['cover_name']);
        self::assertSame([], (new MemberIdentityPapersService())->section(1, 10, '76561198000000010', false)['identities']);
    }

    public function testAssignmentRefusesWrongContentAndStaysInTheCommunity(): void
    {
        $service = new SseEditorAssignmentService();
        $this->libraryItem(1, 'E3', ['item_type' => 'intel']);
        $this->libraryItem(1, 'E4', ['title' => 'Prénom']);
        $this->libraryItem(2, 'E5');
        self::assertFalse($service->assign(1, 'E3', 10, 'ALT', null, null, 5)['ok']);
        self::assertFalse($service->assign(1, 'E4', 10, 'ALT', null, null, 5)['ok']);
        self::assertFalse($service->assign(1, 'E5', 10, 'ALT', null, null, 5)['ok'], 'Contenu d’une autre communauté');
        self::assertFalse($service->assign(1, 'NOPE', 10, 'ALT', null, null, 5)['ok']);
        $this->libraryItem(1, 'E6');
        self::assertFalse($service->assign(1, 'E6', 10, 'XXX', null, null, 5)['ok'], 'Pays inconnu');
        self::assertSame([], $this->pack(1)['documents']);
    }

    private function library(bool $cleared, bool $manage, bool $guest = false): SseEditorLibraryController
    {
        $clearance = $this->createMock(SseClearanceService::class);
        $clearance->method('allows')->willReturn($cleared);
        $access = $this->createMock(SseAccessCodeService::class);
        $access->method('isGuest')->willReturn($guest);

        return new SseEditorLibraryController($access, $clearance, null, static fn (): bool => $manage);
    }

    public function testScreenOffersAssignmentOnlyToThoseWhoMayManage(): void
    {
        $_SESSION = ['user_id' => 5, 'tenant_id' => 1];
        $this->libraryItem(1, 'E1');
        $this->libraryItem(2, 'E9', ['title' => 'Secret Bravo']);
        $with = $this->library(true, true)->index(new Request())->body();
        self::assertStringContainsString('/atak/sse/editeur/E1/attribuer', $with);
        self::assertStringNotContainsString('Secret Bravo', $with);
        $without = $this->library(true, false)->index(new Request())->body();
        self::assertStringNotContainsString('/attribuer', $without);
        $guest = $this->library(true, true, true)->index(new Request())->body();
        self::assertStringNotContainsString('/attribuer', $guest);
    }

    public function testAssignActionIsRefusedWithoutRights(): void
    {
        $_SESSION = ['user_id' => 5, 'tenant_id' => 1];
        $this->libraryItem(1, 'E1');
        foreach ([[true, false, false], [false, true, false], [true, true, true]] as [$cleared, $manage, $guest]) {
            $response = $this->library($cleared, $manage, $guest)->assign(new Request(), ['id' => 'E1']);
            self::assertSame(302, $response->statusCode());
        }
        self::assertSame([], (new SseIdOperationRepository())->listBriefs(1));
    }

    public function testMyPapersShowsTheStateAndEscapesTheCover(): void
    {
        $render = static function (array $coverStatus, array $gameCovers): string {
            $papers = ['identities' => [], 'documents' => [], 'kits' => [], 'checks' => [], 'steam_linked' => $coverStatus['steam_linked']];
            $maps = [];
            $briefs = [];
            ob_start();
            require dirname(__DIR__, 2) . '/views/admin/member_situation/mes_papiers.php';

            return (string) ob_get_clean();
        };
        $unlinked = $render(SseIdGameCoverService::status(false, [], [], []), []);
        self::assertStringContainsString('Ma couverture', $unlinked);
        self::assertStringContainsString('Non lié', $unlinked);
        self::assertStringContainsString('Lier mon compte Steam', $unlinked);

        $cover = ['name' => 'MARKOS <b>P</b>', 'address' => '12 RUE', 'context_id' => null, 'op_key' => null, 'updated_at' => '2026-11-08 18:00:00', 'contacts' => [['Eleni', 'Voisin', '']], 'docs' => [['Carte', 'Altis', 'A1']]];
        $game = $render(SseIdGameCoverService::status(true, [], [], [$cover]), [$cover]);
        self::assertStringContainsString('Créée en jeu', $game);
        self::assertStringContainsString('2026-11-08 18:00', $game);
        self::assertStringContainsString('MARKOS &lt;b&gt;P&lt;/b&gt;', $game);
        self::assertStringNotContainsString('Lier mon compte Steam', $game);
    }

    public function testRouteIsGuarded(): void
    {
        $routes = (string) file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');
        self::assertStringContainsString("post('/atak/sse/editeur/{id}/attribuer', [\\App\\Controllers\\Web\\SseEditorLibraryController::class, 'assign'], \$mwSsePortal)", $routes);
        self::assertStringContainsString("'file' => '4110_atak_sse_id_cover_link.php', 'rerun' => true", (string) file_get_contents(dirname(__DIR__, 2) . '/database/steps/registry.php'));
    }
}
