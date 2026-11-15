<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\SseIdentityRegistryController;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Session;
use App\Repositories\SseIdCheckRepository;
use App\Repositories\SseIdDocumentRepository;
use App\Repositories\SseIdentityRepository;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseCheckPaperSelectionService;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseLinksSqlite;

/**
 * « Consigner un papier » depuis les remontées des contrôles ATAK : sélection multiple, champs préremplis et modifiables, doublons,
 * droits, cloisonnement (communauté, autre identité) et provenance (contrôle rattaché au papier, lien visible dans « Liaisons »).
 * Vraies tables sqlite, vrais dépôts, vrai contrôleur.
 */
final class SseCheckPaperSelectionTest extends TestCase
{
    private PDO $pdo;

    /** @var array<string, mixed> */
    private array $saved = [];

    private int $subject = 0;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = SseLinksSqlite::open();
        $this->saved = ['session' => $_SESSION ?? null, 'post' => $_POST, 'get' => $_GET, 'server' => $_SERVER];
        $_SESSION = [
            'user_id' => 7, 'tenant_id' => 1, '_csrf_token' => 'jeton',
            'sse_tenant_id' => 1, 'sse_guest' => false, 'sse_clearance_until' => time() + 3600, 'sse_access_code_id' => 0,
            'sse_confidentiality_version' => SseAccessCodeService::CONFIDENTIALITY_VERSION, 'sse_confidentiality_tenant_id' => 1, 'sse_confidentiality_code_id' => 0,
        ];
        Gate::reset();
        Gate::getInstance()->setPermissions(['admin.access', 'atak.sse.case.manage']);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['_csrf_token' => 'jeton'];
        $this->subject = (new SseIdentityRepository())->create(1, ['last_name' => 'Karimzadeh', 'first_name' => 'Élodie', 'kind' => 'civil', 'person_id' => 5]);
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
        Gate::reset();
    }

    /** @param array<string, mixed> $over */
    private function check(string $key, array $over = [], int $tenant = 1): int
    {
        $row = $over + [
            'context_id' => 1, 'idempotency_key' => $key, 'doc_type' => 'passport', 'country_code' => 'USA', 'doc_number' => 'US47036925',
            'presented_last' => 'Karimzadeh', 'presented_first' => 'Élodie', 'controller_callsign' => 'Alpha 2-1', 'grid' => '045112',
            'levels' => 'N0', 'results_json' => json_encode(['N0' => ['source' => 'releve', 'visual' => 'faux', 'expires' => '2031-05-02']]), 'verdict' => 'signale',
        ];

        return (new SseIdCheckRepository())->store($tenant, $row)['id'];
    }

    private function identity(): array
    {
        return (new SseIdentityRepository())->find($this->subject, 1);
    }

    private function docCount(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM sse_id_documents')->fetchColumn();
    }

    private function service(): SseCheckPaperSelectionService
    {
        return new SseCheckPaperSelectionService();
    }

    public function testTheCheckFieldsPrefillThePaperAndAreEditableBeforeValidation(): void
    {
        $id = $this->check('k1');
        $rows = $this->service()->candidates(1, $this->identity());
        self::assertCount(1, $rows);
        self::assertSame('new', $rows[0]['state']);
        self::assertSame('US47036925', $rows[0]['fields']['doc_number']);
        self::assertSame('2031-05-02', $rows[0]['fields']['expires_on'], 'Expiration relevée par l’ATAK');
        self::assertSame('fake', $rows[0]['fields']['authenticity'], 'Appréciation visuelle « faux »');
        self::assertStringContainsString('045112', $rows[0]['fields']['place']);

        $res = $this->service()->consign(1, $this->identity(), [$id => ['expires_on' => '2032-01-01', 'authenticity' => 'doubtful', 'authority' => 'US Dept of State', 'remark' => 'Page 2 grattée']], 7);
        self::assertCount(1, $res['created']);
        $doc = (new SseIdDocumentRepository())->find($res['created'][0]['doc_id'], 1);
        self::assertSame('observed', $doc['workflow']);
        self::assertSame($this->subject, (int) $doc['identity_id']);
        self::assertSame('2032-01-01', $doc['expires_on'], 'La saisie de l’opérateur remplace le préremplissage');
        self::assertSame('US Dept of State', $doc['authority']);
        self::assertSame('doubtful', \App\Services\Sse\SseIdentityHolderService::authenticityOf($doc));
        self::assertStringContainsString('CTRL-' . $id, (string) $doc['notes']);
        self::assertStringContainsString('Page 2 grattée', (string) $doc['notes']);
    }

    public function testSeveralChecksAreRecordedAtOnceAndEachCheckPointsToItsPaper(): void
    {
        $a = $this->check('a');
        $b = $this->check('b', ['doc_type' => 'id_card', 'country_code' => 'FRA', 'doc_number' => 'FR998877', 'results_json' => '{}']);
        $res = $this->service()->consign(1, $this->identity(), [$a => [], $b => []], 7);

        self::assertCount(2, $res['created']);
        self::assertSame([], $res['skipped']);
        self::assertSame(2, $this->docCount());
        $checks = new SseIdCheckRepository();
        foreach ($res['created'] as $c) {
            self::assertSame([$c['doc_id']], [(int) $checks->find($c['check_ids'][0], 1)['document_id']]);
        }
        // Provenance : papier « créé depuis » le contrôle, posé par l'opérateur, visible dans les liaisons.
        $link = $this->pdo->query("SELECT * FROM sse_entity_links WHERE relation = 'created_from'")->fetchAll(PDO::FETCH_ASSOC);
        self::assertCount(2, $link);
        self::assertSame('7', (string) $link[0]['created_by_user_id']);
        $audit = $this->pdo->query("SELECT action, actor_user_id FROM sse_audit_log WHERE action = 'id_document.observe_from_check'")->fetchAll(PDO::FETCH_ASSOC);
        self::assertCount(2, $audit, 'Chaque papier consigné est audité');
        self::assertSame('7', (string) $audit[0]['actor_user_id']);
    }

    public function testNothingIsRecordedTwice(): void
    {
        $a = $this->check('a');
        $first = $this->service()->consign(1, $this->identity(), [$a => []], 7);
        self::assertCount(1, $first['created']);

        // Même contrôle une seconde fois : déjà consigné.
        $again = $this->service()->consign(1, $this->identity(), [$a => []], 7);
        self::assertSame([], $again['created']);
        self::assertSame('consigne', $again['skipped'][0]['reason']);

        // Autre contrôle du même papier : déjà au registre (jamais un second papier).
        $b = $this->check('b');
        $dup = $this->service()->consign(1, $this->identity(), [$b => []], 7);
        self::assertSame('registre', $dup['skipped'][0]['reason']);
        self::assertSame(1, $this->docCount());

        // La liste le dit : consigné d'un côté, déjà au registre de l'autre, sélection impossible.
        $states = array_column($this->service()->candidates(1, $this->identity()), null, 'id');
        self::assertSame('consigned', $states[$a]['state']);
        self::assertFalse($states[$a]['selectable']);
        self::assertSame('exists', $states[$b]['state']);
        self::assertSame($states[$a]['doc_ref'], $states[$b]['doc_ref']);
        self::assertFalse($states[$b]['selectable']);
    }

    public function testTwoChecksOfTheSamePaperGiveOnePaperLinkedToBoth(): void
    {
        $a = $this->check('a');
        $b = $this->check('b');
        $res = $this->service()->consign(1, $this->identity(), [$a => [], $b => []], 7);

        self::assertCount(1, $res['created']);
        self::assertSame([$a, $b], $res['created'][0]['check_ids']);
        self::assertSame(1, $this->docCount());
        $checks = new SseIdCheckRepository();
        self::assertSame($res['created'][0]['doc_id'], (int) $checks->find($b, 1)['document_id']);
    }

    public function testAnotherCommunityOrAnotherIdentityIsNeverReachable(): void
    {
        $foreign = $this->check('t2', ['doc_number' => 'ZZ1'], 2);
        $other = (new SseIdentityRepository())->create(1, ['last_name' => 'Autre', 'first_name' => 'Sujet', 'kind' => 'civil', 'person_id' => 6]);
        $linked = $this->check('lk', ['identity_id' => $other, 'doc_number' => 'ZZ2']);
        $free = $this->check('free', ['doc_number' => 'ZZ3']);

        $ids = array_column($this->service()->candidates(1, $this->identity()), 'id');
        self::assertContains($free, $ids, 'Contrôle sans rattachement : proposé');
        self::assertNotContains($foreign, $ids, 'Autre communauté : absent');
        self::assertNotContains($linked, $ids, 'Rattaché à une autre identité : absent');

        $res = $this->service()->consign(1, $this->identity(), [$foreign => [], $linked => [], 99999 => []], 7);
        self::assertSame([], $res['created']);
        self::assertSame(['introuvable', 'introuvable', 'introuvable'], array_column($res['skipped'], 'reason'));
        self::assertSame(0, $this->docCount());

        // Un contrôle libre est rattaché à l'identité et à la personne du sujet à la consignation.
        $ok = $this->service()->consign(1, $this->identity(), [$free => []], 7);
        self::assertCount(1, $ok['created']);
        $row = (new SseIdCheckRepository())->find($free, 1);
        self::assertSame($this->subject, (int) $row['identity_id']);
        self::assertSame(5, (int) $row['person_id']);
    }

    public function testControllerRequiresTokenRightAndASubject(): void
    {
        $a = $this->check('a');
        $controller = new SseIdentityRegistryController();
        $post = static fn (array $over = []): array => array_replace(['_csrf_token' => 'jeton', 'pick' => [$a => '1'], 'row' => [$a => ['authority' => 'Préfecture']]], $over);

        $_POST = $post(['_csrf_token' => 'perime']);
        $controller->documentObserveFromChecks(new Request(), ['id' => $this->subject]);
        self::assertStringContainsString('restée ouverte', (string) Session::getFlash('error', ''));

        Gate::getInstance()->setPermissions(['atak.sse.view']);
        $_POST = $post();
        $controller->documentObserveFromChecks(new Request(), ['id' => $this->subject]);
        self::assertStringContainsString('Droit manquant', (string) Session::getFlash('error', ''), 'Un refus dit quel droit manque, jamais « Action non autorisée »');
        self::assertSame(0, $this->docCount());

        Gate::getInstance()->setPermissions(['admin.access', 'atak.sse.case.manage']);
        $cover = (new SseIdentityRepository())->create(1, ['last_name' => 'Couv', 'first_name' => 'Erture', 'kind' => 'cover']);
        $controller->documentObserveFromChecks(new Request(), ['id' => $cover]);
        self::assertStringContainsString('sujet contrôlé', (string) Session::getFlash('error', ''));
        self::assertSame(0, $this->docCount());

        $_POST = ['_csrf_token' => 'jeton'];
        $controller->documentObserveFromChecks(new Request(), ['id' => $this->subject]);
        self::assertStringContainsString('Cochez au moins un papier', (string) Session::getFlash('error', ''));

        $_POST = $post();
        $controller->documentObserveFromChecks(new Request(), ['id' => $this->subject]);
        self::assertStringContainsString('1 papier(s) consigné(s)', (string) Session::getFlash('success', ''));
        self::assertSame(1, $this->docCount());
        self::assertSame('Préfecture', (new SseIdDocumentRepository())->list(1)[0]['authority']);

        // Rejouer l'envoi : rien de plus, le refus est dit.
        $controller->documentObserveFromChecks(new Request(), ['id' => $this->subject]);
        self::assertStringContainsString('déjà consigné', (string) Session::getFlash('error', ''));
        self::assertSame(1, $this->docCount());

        // Identité d'une autre communauté : introuvable.
        $_POST = $post();
        $foreign = (new SseIdentityRepository())->create(2, ['last_name' => 'X', 'first_name' => 'Y', 'kind' => 'civil', 'person_id' => 9]);
        $controller->documentObserveFromChecks(new Request(), ['id' => $foreign]);
        self::assertStringContainsString('introuvable', (string) Session::getFlash('error', ''));
    }

    public function testTheCheckListShowsWhichChecksAreRecorded(): void
    {
        $a = $this->check('a');
        $b = $this->check('b', ['doc_number' => 'FR1', 'country_code' => 'FRA']);
        $c = $this->check('c', ['doc_number' => null, 'country_code' => null]);
        $this->service()->consign(1, $this->identity(), [$a => []], 7);
        $states = $this->service()->registryStates(1, (new SseIdCheckRepository())->list(1));

        self::assertSame('consigned', $states[$a]['state']);
        self::assertTrue($states[$a]['observed']);
        self::assertNotSame('', $states[$a]['ref']);
        self::assertSame('new', $states[$b]['state']);
        self::assertSame('incomplete', $states[$c]['state']);
    }

    public function testTheViewsExposeTheSelectionWithoutTheRejectedDesignSystem(): void
    {
        $root = dirname(__DIR__, 2);
        $dialog = (string) file_get_contents($root . '/views/atak/sse/papers/_remontees_dialog.php');
        $assets = $dialog . (string) file_get_contents($root . '/public/assets/css/sse-papers-remontees.css') . (string) file_get_contents($root . '/public/assets/js/sse-papers-remontees.js');
        self::assertStringContainsString('name="pick[', $dialog);
        self::assertStringContainsString('data-rem-state', $dialog);
        self::assertStringContainsString('Cerbère', $dialog);
        self::assertStringNotContainsString('sse-ds', $assets);
        self::assertStringNotContainsString('Archivo', $assets);
        self::assertSame(0, preg_match('/class="[^"]*\bsse-/', $dialog));
        self::assertStringContainsString('Registre', (string) file_get_contents($root . '/views/atak/sse/papers/checks.php'));
    }
}
