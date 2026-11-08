<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\SseEditorLibraryController;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\SseEditorItemRepository;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseClearanceService;
use App\Support\HtmlTranslator;
use App\Support\SsePortalNav;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseIdentitySqlite;

/** Bureau SSE › Bibliothèque de l'éditeur : droits, cloison des communautés, filtres, échappement, table absente. */
final class SseEditorLibraryScreenTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $saved = [];

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        SseIdentitySqlite::open();
        $this->saved = ['get' => $_GET ?? [], 'session' => $_SESSION ?? null];
        $_GET = [];
        $_SESSION = ['user_id' => 5, 'tenant_id' => 1];
        $repo = new SseEditorItemRepository();
        \App\Core\Database::getPdo()->exec("INSERT INTO users (id, tenant_id, display_name) VALUES (5, 1, 'Cne <Viper>')");
        $repo->save(1, 'E1', self::values(['title' => 'Note <script>alert(1)</script>', 'body' => "Ligne 1\nQuai <b>4</b>", 'author_user_id' => 5, 'found_by' => 'Sgt Durand', 'found_when' => '08/11/2026 22:10']));
        $repo->save(1, 'E2', self::values(['item_type' => 'paper', 'title' => 'Carte Roy', 'body' => '', 'paper' => 1, 'marking' => 2, 'state' => 'Archivé', 'history_json' => '[["Créé","A","1"],["Archivé","B","2"]]']));
        $repo->save(1, 'E3', self::values(['title' => 'Remise à 100% au port']));
        $repo->save(2, 'E9', self::values(['title' => 'Secret de Bravo']));
    }

    protected function tearDown(): void
    {
        SseIdentitySqlite::close();
        $_GET = $this->saved['get'];
        if ($this->saved['session'] === null) {
            unset($_SESSION);
        } else {
            $_SESSION = $this->saved['session'];
        }
    }

    /** @param array<string, mixed> $values */
    private static function values(array $values): array
    {
        return $values + ['item_type' => 'intel', 'title' => 'T', 'body' => 'B', 'marking' => 1, 'paper' => 0, 'nature' => 0, 'delivery' => 0, 'state' => 'Brouillon', 'op_label' => 'OP-KAVALA', 'link_label' => '', 'version' => 3, 'history_json' => '[]'];
    }

    private function open(bool $cleared = true, array $query = []): Response
    {
        $_GET = $query;
        $clearance = $this->createMock(SseClearanceService::class);
        $clearance->method('allows')->willReturn($cleared);
        $access = $this->createMock(SseAccessCodeService::class);
        $access->method('isGuest')->willReturn(false);
        // Code d'une autre communauté resté en session : la session décide, pas lui.
        $access->method('tenantId')->willReturn(2);

        return (new SseEditorLibraryController($access, $clearance))->index(new Request());
    }

    public function testListStaysInTheSessionCommunity(): void
    {
        $html = $this->open()->body();
        self::assertStringContainsString('Carte Roy', $html);
        self::assertStringNotContainsString('Secret de Bravo', $html);
        self::assertStringContainsString('3 contenu(s)', $html);
    }

    public function testRefusedWithoutClearanceOrWithoutAccount(): void
    {
        $denied = $this->open(false);
        self::assertSame(302, $denied->statusCode());
        self::assertStringNotContainsString('Carte Roy', $denied->body());

        // Invité par code : pas de compte, même si le code porte l'habilitation.
        $_SESSION = ['tenant_id' => 1];
        self::assertSame(302, $this->open(true)->statusCode());
        self::assertFalse(SseEditorLibraryController::allowed());
    }

    public function testContentIsEscapedAndNotTranslated(): void
    {
        $html = $this->open()->body();
        self::assertStringNotContainsString('<script>alert(1)', $html);
        self::assertStringNotContainsString('<b>4</b>', $html);
        self::assertStringContainsString('<span translate="no">Note &lt;script&gt;alert(1)&lt;/script&gt;</span>', $html);
        self::assertStringContainsString('<div class="el-body" translate="no">Ligne 1' . "\n" . 'Quai &lt;b&gt;4&lt;/b&gt;</div>', $html);
        self::assertStringContainsString('<td translate="no">Cne &lt;Viper&gt;</td>', $html);
        self::assertStringContainsString('<span translate="no">Sgt Durand</span>', $html);
    }

    public function testFiltersAndSearch(): void
    {
        $paper = $this->open(true, ['type' => 'paper'])->body();
        self::assertStringContainsString('Carte Roy', $paper);
        self::assertStringContainsString('1 contenu(s)', $paper);

        self::assertStringContainsString('1 contenu(s)', $this->open(true, ['state' => 'Archivé'])->body());
        // Valeur inconnue ignorée.
        self::assertStringContainsString('3 contenu(s)', $this->open(true, ['type' => "x' OR 1=1"])->body());

        // « % » cherché tel quel, recherche dans le texte, le joueur et l'opération.
        self::assertStringContainsString('1 contenu(s)', $this->open(true, ['q' => '100%'])->body());
        self::assertStringContainsString('0 contenu(s)', $this->open(true, ['q' => '_uai'])->body());
        self::assertStringContainsString('1 contenu(s)', $this->open(true, ['q' => 'Quai'])->body());
        self::assertStringContainsString('1 contenu(s)', $this->open(true, ['q' => 'Durand'])->body());
        self::assertStringContainsString('3 contenu(s)', $this->open(true, ['q' => 'KAVALA'])->body());
        self::assertStringContainsString('Aucun contenu ne correspond', $this->open(true, ['q' => 'Bravo'])->body());
    }

    public function testPaperShowsItsPaperTypeAndHistoryIsNewestFirst(): void
    {
        $html = $this->open(true, ['type' => 'paper'])->body();
        self::assertStringContainsString('<td>Passeport</td>', $html);
        self::assertStringContainsString('<dt>Type de papier</dt>', $html);
        self::assertStringNotContainsString('<td>CONFIDENTIEL</td>', $html);
        self::assertLessThan(strpos($html, '>Créé<'), strpos($html, '>Archivé<small'));
    }

    public function testPagesAreBoundedAndKeepFilters(): void
    {
        $repo = new SseEditorItemRepository();
        for ($i = 0; $i < 60; $i++) {
            $repo->save(1, 'P' . $i, self::values(['title' => 'Page ' . $i, 'state' => 'Modèle']));
        }
        $first = $this->open(true, ['state' => 'Modèle'])->body();
        self::assertSame(50, substr_count($first, 'data-sse-modal-open='));
        self::assertStringContainsString('Page 1 sur 2', $first);
        self::assertStringContainsString('state=Mod%C3%A8le&amp;page=2', $first);
        // Au-delà de la dernière page : ramené sur la dernière.
        self::assertSame(10, substr_count($this->open(true, ['state' => 'Modèle', 'page' => '99'])->body(), 'data-sse-modal-open='));
    }

    public function testMissingTableGivesAClearMessage(): void
    {
        \App\Core\Database::getPdo()->exec('DROP TABLE sse_editor_items');
        $r = $this->open();
        self::assertSame(200, $r->statusCode());
        self::assertStringContainsString('étape de migration 4070', $r->body());
    }

    public function testEnglishPageLeavesMembersTextAlone(): void
    {
        $html = HtmlTranslator::translate($this->open()->body());
        self::assertStringContainsString('Found by a player', $html);
        self::assertStringContainsString('Sgt Durand', $html);
        self::assertStringNotContainsString('Trouvé par un joueur', $html);
        self::assertStringContainsString('Ligne 1', $html);
    }

    public function testRouteAndMenuAreGuarded(): void
    {
        $routes = (string) file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');
        self::assertStringContainsString("get('/atak/sse/editeur', [\\App\\Controllers\\Web\\SseEditorLibraryController::class, 'index'], \$mwSsePortal)", $routes);
        // Le contenu ne s'écrit pas ici : la seule écriture est l'attribution à un joueur (SseIdCoverLinkTest).
        self::assertSame(1, substr_count($routes, "post('/atak/sse/editeur"));

        $paths = static function (array $sections): array {
            return array_merge(...array_map(static fn (array $s): array => array_column($s['items'], 'path'), $sections));
        };
        self::assertNotContains('atak/sse/editeur', $paths(SsePortalNav::sections(true, true)));
        self::assertContains('atak/sse/editeur', $paths(SsePortalNav::sections(false, false, false, false, true)));
    }
}
