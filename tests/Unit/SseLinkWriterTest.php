<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\SseLinksController;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Services\Sse\Links\SseLinkGraph;
use App\Services\Sse\Links\SseLinkWriter;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\ArraySseLinkViewer;
use Tests\Support\SseLinksFixture;
use Tests\Support\SseLinksSqlite;

/**
 * Liens manuels du widget : motif obligatoire, droits d'écriture de la section de l'élément, lecture des deux bouts (un élément
 * invisible répond comme un élément inexistant : pas d'IDOR), jamais d'élément d'une autre communauté, retrait réservé aux liens
 * manuels, journal d'audit, jeton CSRF et adresse de retour restreinte au portail.
 */
final class SseLinkWriterTest extends TestCase
{
    private PDO $pdo;

    /** @var array<string, int> */
    private array $ids = [];

    /** @var array<string, mixed> */
    private array $saved = [];

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->saved = ['post' => $_POST ?? [], 'get' => $_GET ?? [], 'server' => $_SERVER, 'session' => $_SESSION ?? null];
        $this->pdo = SseLinksSqlite::open();
        $this->ids = SseLinksFixture::seed($this->pdo);
        $_SESSION = ['user_id' => 7, 'tenant_id' => 1];
        $_POST = [];
        $_GET = [];
        Csrf::token();
    }

    protected function tearDown(): void
    {
        SseLinksSqlite::close();
        $_POST = $this->saved['post'];
        $_GET = $this->saved['get'];
        $_SERVER = $this->saved['server'];
        if ($this->saved['session'] === null) {
            unset($_SESSION);
        } else {
            $_SESSION = $this->saved['session'];
        }
    }

    private function writer(?ArraySseLinkViewer $v = null): SseLinkWriter
    {
        $v ??= ArraySseLinkViewer::full();

        return new SseLinkWriter($v, new SseLinkGraph($v));
    }

    private function add(?ArraySseLinkViewer $v = null, string $reason = 'Même réseau que celui du dossier.', string $toType = 'site', ?int $toId = null): array
    {
        return $this->writer($v)->add('person', (string) $this->ids['person'], $toType, (string) ($toId ?? $this->ids['site']), 'related', $reason);
    }

    public function testAManualLinkNeedsAReasonAndKeepsItsAuthorAndIsAudited(): void
    {
        $short = $this->add(null, 'ok');
        self::assertFalse($short['ok']);
        self::assertStringContainsString('motif est obligatoire', (string) $short['error']);
        self::assertSame(0, (int) $this->pdo->query("SELECT COUNT(*) FROM sse_entity_links WHERE rule_code = 'manual.link' AND target_type = 'site'")->fetchColumn());

        $ok = $this->add(null, '  Vu <b>ensemble</b>   au port.  ');
        self::assertTrue($ok['ok']);
        $row = $this->pdo->query('SELECT * FROM sse_entity_links WHERE id = ' . $ok['id'])->fetch(PDO::FETCH_ASSOC);
        self::assertSame('Vu ensemble au port.', $row['reason'], 'balises retirées, espaces nettoyés');
        self::assertSame('user', $row['created_by']);
        self::assertSame('7', (string) $row['created_by_user_id']);
        self::assertSame('manual.link', $row['rule_code']);
        $audit = $this->pdo->query("SELECT action, actor_user_id FROM sse_audit_log WHERE action = 'entity_link.add'")->fetch(PDO::FETCH_ASSOC);
        self::assertSame('entity_link.add', $audit['action']);
        self::assertSame('7', (string) $audit['actor_user_id']);
    }

    public function testTheSectionWriteRightIsRequired(): void
    {
        $res = $this->add(ArraySseLinkViewer::full()->with(['write' => []]));
        self::assertFalse($res['ok']);
        self::assertSame(SseLinkWriter::DENY_RIGHT, $res['error']);
        $guest = $this->add(ArraySseLinkViewer::full()->with(['guest' => true]));
        self::assertSame(SseLinkWriter::DENY_GUEST, $guest['error']);
    }

    public function testAnElementTheReaderCannotSeeAnswersLikeAnElementThatDoesNotExist(): void
    {
        $hidden = $this->add(ArraySseLinkViewer::full()->with(['clearance' => 'encadrement']), 'Motif valable ici.', 'case', $this->ids['case_secret']);
        $missing = $this->add(null, 'Motif valable ici.', 'case', 999999);
        self::assertSame(SseLinkWriter::NOT_FOUND, $hidden['error']);
        self::assertSame($hidden['error'], $missing['error']);
        $foreign = $this->writer(ArraySseLinkViewer::full(2))->add('person', (string) $this->ids['person_t2'], 'person', (string) $this->ids['person'], 'related', 'Motif valable ici.');
        self::assertSame(SseLinkWriter::NOT_FOUND, $foreign['error'], 'élément d’une autre communauté');
        self::assertSame(0, (int) $this->pdo->query("SELECT COUNT(*) FROM sse_entity_links WHERE tenant_id = 2 AND reason = 'Motif valable ici.'")->fetchColumn());
    }

    public function testAlreadyLinkedSelfAndUnknownTypesAreRefused(): void
    {
        $first = $this->add();
        self::assertTrue($first['ok']);
        self::assertSame('Ces deux éléments sont déjà liés.', $this->add()['error']);
        self::assertSame('Un élément ne peut pas être lié à lui-même.', $this->writer()->add('person', '5', 'person', '5', 'related', 'Motif valable ici.')['error']);
        self::assertSame(SseLinkWriter::NOT_FOUND, $this->writer()->add('person', (string) $this->ids['person'], 'nimporte', '5', 'related', 'Motif valable ici.')['error']);
        self::assertSame(SseLinkWriter::NOT_FOUND, $this->writer()->add('person', (string) $this->ids['person'], 'jnet', '5', 'related', 'Motif valable ici.')['error']);
        self::assertSame(SseLinkWriter::NOT_FOUND, $this->writer()->add('person', (string) $this->ids['person'], 'site', '5; DROP TABLE x', 'related', 'Motif valable ici.')['error']);
    }

    public function testOnlyAManualLinkIsRemovedNeverACerbereNorADerivedOne(): void
    {
        $manual = $this->ids['link_manual'];
        $cerbere = $this->ids['link_case'];
        $res = $this->writer()->remove($cerbere);
        self::assertFalse($res['ok']);
        self::assertStringContainsString('Cerbère', (string) $res['error']);
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_entity_links WHERE id = ' . $cerbere)->fetchColumn());
        // Un lien déduit d'une colonne n'a pas de ligne : son identifiant n'existe pas.
        self::assertFalse($this->writer()->remove(9999)['ok']);
        $ok = $this->writer()->remove($manual);
        self::assertTrue($ok['ok']);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_entity_links WHERE id = ' . $manual)->fetchColumn());
        self::assertSame('entity_link.remove', (string) $this->pdo->query("SELECT action FROM sse_audit_log WHERE action = 'entity_link.remove'")->fetchColumn());
    }

    public function testARemovalCannotReachAnotherCommunityOrAnInvisibleLink(): void
    {
        $foreign = $this->writer()->remove($this->ids['link_t2']);
        self::assertFalse($foreign['ok']);
        self::assertSame(SseLinkWriter::NOT_FOUND, $foreign['error']);
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_entity_links WHERE id = ' . $this->ids['link_t2'])->fetchColumn());
        // Le lien manuel relie une fiche de renseignement : sans la section « terrain », il est invisible et intouchable.
        $blind = $this->writer(ArraySseLinkViewer::full()->with(['read' => ['fichiers', 'papiers']]))->remove($this->ids['link_manual']);
        self::assertSame(SseLinkWriter::NOT_FOUND, $blind['error']);
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_entity_links WHERE id = ' . $this->ids['link_manual'])->fetchColumn());
    }

    public function testWithoutTheTableManualLinksSayWhy(): void
    {
        SseLinksSqlite::close();
        $this->pdo = SseLinksSqlite::open(false);
        self::assertSame(SseLinkWriter::NOT_READY, $this->add()['error']);
        self::assertSame(SseLinkWriter::NOT_READY, $this->writer()->remove(1)['error']);
    }

    // ——— Routes : jeton CSRF et adresse de retour ———

    private function post(array $fields): Request
    {
        $_POST = $fields;
        $_SERVER['REQUEST_METHOD'] = 'POST';

        return new Request();
    }

    public function testAWriteWithoutAValidTokenChangesNothing(): void
    {
        $controller = new SseLinksController(ArraySseLinkViewer::full());
        $before = (int) $this->pdo->query('SELECT COUNT(*) FROM sse_entity_links')->fetchColumn();
        foreach ([[], ['_csrf_token' => ''], ['_csrf_token' => 'faux']] as $extra) {
            $res = $controller->store($this->post($extra + ['from' => 'person:' . $this->ids['person'], 'to' => 'site:' . $this->ids['site'], 'relation' => 'related', 'reason' => 'Motif valable ici.', 'back' => 'atak/sse/identites/1']), []);
            self::assertSame(302, $res->statusCode());
            self::assertStringContainsString('session a changé', (string) Session::getFlash('error'));
        }
        $res = $controller->remove($this->post(['_csrf_token' => 'faux']), ['id' => $this->ids['link_manual']]);
        self::assertSame(302, $res->statusCode());
        self::assertSame($before, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_entity_links')->fetchColumn());
    }

    public function testAValidTokenRecordsTheLinkAndReturnsToThePage(): void
    {
        $controller = new SseLinksController(ArraySseLinkViewer::full());
        $res = $controller->store($this->post(['_csrf_token' => Csrf::token(), 'from' => 'person:' . $this->ids['person'], 'to' => 'site:' . $this->ids['site'], 'relation' => 'related', 'reason' => 'Vu ensemble au port.', 'back' => 'atak/sse/identites/' . $this->ids['person']]), []);
        self::assertSame(302, $res->statusCode());
        self::assertStringEndsWith('atak/sse/identites/' . $this->ids['person'], (string) $res->headerValue('Location'));
        self::assertSame('Lien enregistré.', Session::getFlash('success'));
        self::assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM sse_entity_links WHERE reason = 'Vu ensemble au port.'")->fetchColumn());
    }

    public function testTheTenantOfTheRequestIsIgnoredAndTheReturnAddressStaysInThePortal(): void
    {
        $controller = new SseLinksController(ArraySseLinkViewer::full());
        $res = $controller->store($this->post(['_csrf_token' => Csrf::token(), 'tenant_id' => '2', 'from' => 'person:' . $this->ids['person_t2'], 'to' => 'site:' . $this->ids['site'], 'reason' => 'Motif valable ici.', 'back' => 'https://exemple.test/hameçon']), []);
        self::assertStringEndsWith('atak/sse', (string) $res->headerValue('Location'));
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_entity_links WHERE tenant_id = 2 AND reason = \'Motif valable ici.\'')->fetchColumn());
        foreach (['//exemple.test', 'atak/sse/../../admin', 'admin/system', 'javascript:alert(1)', "atak/sse\r\nLocation: x"] as $bad) {
            self::assertStringEndsWith('atak/sse', SseLinksController::safeBack($bad), $bad);
        }
        self::assertStringEndsWith('atak/sse/identites/12', SseLinksController::safeBack('/atak/sse/identites/12'));
        self::assertStringEndsWith('atak/sse/identites/12#liaisons', SseLinksController::safeBack('atak/sse/identites/12#liaisons'));
    }

    public function testTheSearchRouteAnswersWithReadableElementsOnly(): void
    {
        $_GET = ['q' => 'SSE-2026', 'types' => 'case'];
        $controller = new SseLinksController(ArraySseLinkViewer::full()->with(['clearance' => 'encadrement']));
        $body = json_decode($controller->search(new Request(), [])->body(), true);
        self::assertCount(1, $body['items']);
        self::assertSame('SSE-2026-0001', $body['items'][0]['ref']);
        $_GET = ['q' => 'a'];
        self::assertSame([], json_decode($controller->search(new Request(), [])->body(), true)['items']);
    }
}
