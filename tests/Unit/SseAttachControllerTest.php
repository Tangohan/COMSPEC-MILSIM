<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\SseAttachController;
use App\Core\Request;
use App\Services\Sse\Attach\SseAttachService;
use App\Support\SseAttachCatalog as A;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\ArraySseLinkViewer;
use Tests\Support\SseAttachFixture;
use Tests\Support\SseAttachSqlite;

/**
 * Points d'entrée de la fenêtre « Rattacher » : jeton CSRF sur toute écriture (aperçu compris), même réponse pour une pièce
 * ou une cible inexistante, d'une autre communauté ou au-dessus de l'habilitation (anti-IDOR), communauté de la session et
 * jamais de la requête, réponses JSON pour la fenêtre et retour interne pour un formulaire sans script.
 */
final class SseAttachControllerTest extends TestCase
{
    private PDO $pdo;

    /** @var array<string, int> */
    private array $ids;

    /** @var array<string, mixed> */
    private array $saved = [];

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->saved = ['session' => $_SESSION ?? null, 'post' => $_POST ?? [], 'get' => $_GET ?? [], 'server' => $_SERVER];
        $this->pdo = SseAttachSqlite::open();
        $this->ids = SseAttachFixture::seed($this->pdo);
        $_SESSION = ['user_id' => 22, 'tenant_id' => 1, '_csrf_token' => 'jeton', 'sse_tenant_id' => 1];
        $_POST = [];
        $_GET = [];
    }

    protected function tearDown(): void
    {
        SseAttachSqlite::close();
        $_POST = $this->saved['post'];
        $_GET = $this->saved['get'];
        $_SERVER = $this->saved['server'];
        if ($this->saved['session'] === null) {
            unset($_SESSION);
        } else {
            $_SESSION = $this->saved['session'];
        }
    }

    private function controller(?ArraySseLinkViewer $viewer = null): SseAttachController
    {
        return new SseAttachController($viewer ?? SseAttachFixture::operator());
    }

    /** @param array<string, mixed> $post */
    private function post(array $post, bool $csrf = true, bool $json = true): Request
    {
        $_POST = $post + ($csrf ? ['_csrf_token' => 'jeton'] : []);
        $_GET = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_ACCEPT'] = $json ? 'application/json' : 'text/html';

        return new Request();
    }

    /** @param array<string, mixed> $query */
    private function get(array $query): Request
    {
        $_GET = $query;
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        return new Request();
    }

    /** @return array<string, mixed> */
    private static function json(\App\Core\Response $res): array
    {
        return (array) json_decode($res->body(), true);
    }

    private function note(): string
    {
        return 'note:' . $this->ids['note_free'];
    }

    public function testContextTargetsAndTargetAnswerJson(): void
    {
        $c = $this->controller();
        $ctx = self::json($c->context($this->get(['pieces' => $this->note(), 'recents' => 'case:' . $this->ids['case'] . ',case:' . $this->ids['case_secret']])));
        self::assertTrue($ctx['ok']);
        self::assertSame('FR-2026-0012', $ctx['sources'][0]['ref']);
        self::assertSame(['SSE-2026-0001'], array_column($ctx['recents'], 'ref'), 'un récent devenu invisible ne revient pas');
        self::assertNotSame([], $ctx['suggestions']);
        self::assertSame(A::UNDO_SECONDS, $ctx['undo_seconds']);

        $list = self::json($c->targets($this->get(['pieces' => $this->note(), 'scope' => 'lead', 'q' => 'fourgon', 'sort' => 'alpha'])));
        self::assertTrue($list['ok']);
        self::assertSame('lead', $list['scope']);
        self::assertSame(['PST-2026-0001'], array_column($list['items'], 'ref'));
        // Aucune identité de membre, aucun champ interne dans la réponse.
        $body = $c->targets($this->get(['pieces' => $this->note(), 'scope' => 'identity']))->body();
        self::assertStringNotContainsString('Dupuis', $body);
        self::assertStringNotContainsString('Faux-Nom', $body);
        self::assertStringNotContainsString('"hay"', $body);
        self::assertStringNotContainsString('owner_id', $body);

        $one = $c->target($this->get(['pieces' => $this->note(), 'cible' => 'case:' . $this->ids['case']]));
        self::assertSame(200, $one->statusCode());
        self::assertSame('Réseau des passeurs', self::json($one)['item']['title']);
        self::assertContains(['ref' => '', 'label' => 'Élément restreint', 'type_label' => ''], self::json($one)['item']['attached']);
    }

    public function testUnknownForeignAndClassifiedTargetsAnswerExactlyTheSame(): void
    {
        $c = $this->controller();
        $bodies = [];
        foreach (['case:999999', 'case:' . $this->ids['case_t2'], 'case:' . $this->ids['case_secret'], 'jnet:1', 'n’importe quoi'] as $target) {
            $res = $c->target($this->get(['pieces' => $this->note(), 'cible' => $target]));
            self::assertSame(404, $res->statusCode(), $target);
            $bodies[] = $res->body();
        }
        self::assertCount(1, array_unique($bodies), 'aucun indice ne distingue inexistant, autre communauté et au-dessus de l’habilitation');
        // Même règle pour la pièce.
        $piece = [];
        foreach (['note:999999', 'note:' . $this->ids['note_secret'], 'person:' . $this->ids['person_t2']] as $ref) {
            $res = $c->context($this->get(['pieces' => $ref]));
            self::assertSame(404, $res->statusCode(), $ref);
            $piece[] = $res->body();
            self::assertSame(404, $c->targets($this->get(['pieces' => $ref]))->statusCode());
        }
        self::assertCount(1, array_unique($piece));
        // La communauté vient du lecteur : un paramètre de requête ne la change pas.
        $res = $c->target($this->get(['pieces' => $this->note(), 'cible' => 'case:' . $this->ids['case_t2'], 'tenant_id' => 2, 'tenant' => 2]));
        self::assertSame(404, $res->statusCode());
        $other = $this->controller(SseAttachFixture::manager()->with(['tenant' => 2]));
        self::assertSame(404, $other->context($this->get(['pieces' => $this->note()]))->statusCode());
    }

    public function testEveryWriteRequiresTheCsrfToken(): void
    {
        $c = $this->controller();
        $data = ['pieces' => $this->note(), 'cibles' => 'case:' . $this->ids['case3'], 'reason' => 'Motif suffisant'];
        foreach ([
            fn (Request $r) => $c->preview($r), fn (Request $r) => $c->store($r), fn (Request $r) => $c->undo($r), fn (Request $r) => $c->create($r),
            fn (Request $r) => $c->align($r), fn (Request $r) => $c->setting($r), fn (Request $r) => $c->update($r, ['id' => $this->ids['att_doc']]),
            fn (Request $r) => $c->detach($r, ['id' => $this->ids['att_doc']]), fn (Request $r) => $c->move($r, ['id' => $this->ids['att_doc']]),
            fn (Request $r) => $c->leadStore($r), fn (Request $r) => $c->leadUpdate($r, ['id' => $this->ids['lead1']]),
        ] as $n => $call) {
            $res = $call($this->post($data + ['title' => 'Sans jeton', 'type' => 'lead', 'id' => (string) $this->ids['att_note'], 'always_align' => '1'], false));
            self::assertSame(419, $res->statusCode(), 'écriture ' . $n);
            self::assertFalse(self::json($res)['ok']);
        }
        self::assertSame(5, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_link_attachments')->fetchColumn());
        self::assertSame(10, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_leads')->fetchColumn());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_portal_settings')->fetchColumn());
        // Mauvais jeton : même refus.
        $_POST = $data + ['_csrf_token' => 'autre'];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        self::assertSame(419, $c->store(new Request())->statusCode());
    }

    public function testPreviewThenAttachThenUndoThroughTheRoutes(): void
    {
        $c = $this->controller();
        $data = ['pieces' => $this->note(), 'cibles' => 'case:' . $this->ids['case4'] . ',lead:' . $this->ids['lead2'], 'reason' => 'Relevé au même endroit', 'align' => '1',
            'tags' => json_encode(['#' . $this->ids['tag1'], 'Pont nord|green']), 'label' => 'Mouvement', 'per' => json_encode(['lead:' . $this->ids['lead2'] => ['nature' => 'verify_with']])];
        $preview = self::json($c->preview($this->post($data)));
        self::assertTrue($preview['ok']);
        self::assertSame(['Classé dans', 'À vérifier avec'], array_column($preview['rows'], 'nature'));
        self::assertSame('raise', $preview['rows'][0]['effect']);
        self::assertSame(5, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_link_attachments')->fetchColumn());

        $res = $c->store($this->post($data));
        self::assertSame(200, $res->statusCode());
        $out = self::json($res);
        self::assertSame(2, $out['count']);
        self::assertSame('2 rattachements enregistrés.', $out['message']);
        self::assertSame('verify_with', $this->pdo->query('SELECT nature FROM sse_link_attachments WHERE id = ' . (int) $out['created'][1])->fetchColumn());
        self::assertSame('encadrement', $this->pdo->query('SELECT classification FROM sse_field_notes WHERE id = ' . $this->ids['note_free'])->fetchColumn());

        $undo = self::json($c->undo($this->post(['ids' => implode(',', $out['created'])])));
        self::assertTrue($undo['ok']);
        self::assertSame(2, $undo['count']);
        self::assertSame('interne', $this->pdo->query('SELECT classification FROM sse_field_notes WHERE id = ' . $this->ids['note_free'])->fetchColumn());

        // Échec lisible : motif trop court, cible invisible.
        $bad = $c->store($this->post(['pieces' => $this->note(), 'cibles' => 'case:' . $this->ids['case3'], 'reason' => 'x']));
        self::assertSame(422, $bad->statusCode());
        self::assertSame(SseAttachService::REASON_REQUIRED, self::json($bad)['error']);
        $hidden = self::json($c->store($this->post(['pieces' => $this->note(), 'cibles' => 'case:' . $this->ids['case_secret'], 'reason' => 'Motif suffisant'])));
        self::assertSame(SseAttachService::NOT_FOUND, $hidden['error']);
    }

    public function testEditDetachMoveAndCreateThroughTheRoutes(): void
    {
        $c = $this->controller();
        $id = $this->ids['att_doc'];
        self::assertTrue(self::json($c->update($this->post(['label' => 'CR signé', 'reason' => 'Version signée reçue', 'tags' => 'Vérifié, Signé']), ['id' => $id]))['ok']);
        self::assertSame('CR signé', $this->pdo->query('SELECT label FROM sse_link_attachments WHERE id = ' . $id)->fetchColumn());
        // Un rattachement d'une pièce invisible, ou d'un autre numéro : introuvable, sans distinction.
        $a = $c->detach($this->post(['reason' => 'Motif suffisant']), ['id' => $this->ids['att_secret']])->body();
        $b = $c->detach($this->post(['reason' => 'Motif suffisant']), ['id' => 999999])->body();
        self::assertSame($a, $b);
        self::assertSame(SseAttachService::NOT_FOUND, self::json($c->move($this->post(['cible' => 'case:' . $this->ids['case3'], 'reason' => 'Motif suffisant']), ['id' => 999999]))['error']);

        $moved = self::json($c->move($this->post(['cible' => 'case:' . $this->ids['case3'], 'reason' => 'Relève de l’autre affaire']), ['id' => $id]));
        self::assertTrue($moved['ok']);
        self::assertSame('removed', $this->pdo->query('SELECT status FROM sse_link_attachments WHERE id = ' . $id)->fetchColumn());
        self::assertTrue(self::json($c->detach($this->post(['reason' => 'Finalement inutile']), ['id' => $moved['id']]))['ok']);

        $made = self::json($c->create($this->post(['type' => 'lead', 'title' => 'Guetteur du pont nord', 'classification' => 'encadrement'])));
        self::assertTrue($made['ok']);
        self::assertSame(1, preg_match('/^lead:\d+$/', (string) $made['key']));
        self::assertFalse(self::json($this->controller(SseAttachFixture::reader())->create($this->post(['type' => 'lead', 'title' => 'Sans droit'])))['ok']);

        // Formulaire sans script : message et retour à une adresse du portail seulement.
        $res = $c->detach($this->post(['reason' => 'x', 'back' => 'https://exemple.test/piege'], true, false), ['id' => $this->ids['att_check']]);
        self::assertSame(302, $res->statusCode());
        self::assertTrue(str_ends_with((string) $res->headerValue('Location'), 'atak/sse'));
        $res = $c->detach($this->post(['reason' => 'Mauvais dossier', 'back' => 'atak/sse/dossiers/1'], true, false), ['id' => $this->ids['att_check']]);
        self::assertTrue(str_ends_with((string) $res->headerValue('Location'), 'atak/sse/dossiers/1'));
        self::assertSame('removed', $this->pdo->query('SELECT status FROM sse_link_attachments WHERE id = ' . $this->ids['att_check'])->fetchColumn());
    }

    public function testCommunitySettingNeedsTheManageRight(): void
    {
        $denied = self::json($this->controller()->setting($this->post(['always_align' => '1'])));
        self::assertFalse($denied['ok']);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_portal_settings')->fetchColumn());
        $ok = self::json($this->controller(SseAttachFixture::manager())->setting($this->post(['always_align' => '1'])));
        self::assertTrue($ok['ok']);
        self::assertSame('1', $this->pdo->query("SELECT setting_value FROM sse_portal_settings WHERE tenant_id = 1 AND setting_key = '" . A::SETTING_ALWAYS_ALIGN . "'")->fetchColumn());
        self::assertTrue((new SseAttachService(SseAttachFixture::operator()))->alwaysAlign());
    }

    public function testAlignProposalIsAppliedOnlyOnRequest(): void
    {
        $this->pdo->exec("UPDATE sse_cases SET classification = 'encadrement' WHERE id = " . $this->ids['case']);
        $c = $this->controller();
        self::assertSame('interne', $this->pdo->query('SELECT classification FROM sse_field_notes WHERE id = ' . $this->ids['note'])->fetchColumn());
        self::assertTrue(self::json($c->align($this->post(['id' => (string) $this->ids['att_note']])))['ok']);
        self::assertSame('encadrement', $this->pdo->query('SELECT classification FROM sse_field_notes WHERE id = ' . $this->ids['note'])->fetchColumn());
        self::assertFalse(self::json($c->align($this->post(['id' => (string) $this->ids['att_note']])))['ok'], 'déjà au niveau de la cible');
    }
}
