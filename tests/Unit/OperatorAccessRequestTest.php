<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\OperatorHubController;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\OperatorHubRepository;
use App\Repositories\PermissionRepository;
use App\Repositories\SseCoverExportRepository;
use App\Repositories\UserRepository;
use App\Services\Effectifs\ElevationApprovalService;
use App\Services\Notifications\ActivityHubPresentationService;
use App\Services\Operator\OperatorAccessRequestService;
use App\Services\Operator\OperatorHubCatalog;
use App\Services\Operator\OperatorIdentityService;
use App\Services\Security\FileRateLimiter;
use App\Services\Sse\SseCoverExportService;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseIdentitySqlite;

/**
 * Demande d'accès à un outil opérateur grisé : création, doublon, portée (autre membre, autre communauté), annulation,
 * refus avec motif et délai, décision (journal, cloche, pré-cochage dans la page de gestion), personne ne s'accorde un
 * droit à soi-même, tuile redevenue normale une fois le droit accordé, table absente = fonction indisponible.
 */
final class OperatorAccessRequestTest extends TestCase
{
    private ?PDO $pdo = null;
    /** @var list<array{0: string, 1: string}> */
    private array $flashes = [];
    /** @var list<array{tenant: int, user: int, type: string, payload: array<string, mixed>}> */
    private array $notified = [];
    /** @var list<array{action: string, tenant: int, actor: int, request: int, details: array<string, mixed>}> */
    private array $audited = [];
    private int $now = 0;

    protected function tearDown(): void
    {
        if ($this->pdo !== null) {
            SseIdentitySqlite::close();
            $this->pdo = null;
        }
        $_POST = $_GET = [];
    }

    private function db(bool $withRequests = true): PDO
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = SseIdentitySqlite::open();
        $root = dirname(__DIR__, 2);
        (require $root . '/bootstrap/sse_cover_exports_migration.php')($this->pdo);
        (require $root . '/bootstrap/sse_files_records_migration.php')($this->pdo);
        (require $root . '/bootstrap/operator_hub_migration.php')($this->pdo);
        $this->pdo->exec("INSERT INTO users (id, tenant_id, steam_id, display_name) VALUES (10, 1, NULL, 'Jake'), (11, 1, NULL, 'Autre'), (20, 2, NULL, 'Bravo')");
        if ($withRequests) {
            $this->pdo->exec('CREATE TABLE permissions (id INTEGER PRIMARY KEY, tenant_id INTEGER, slug TEXT, name TEXT, module TEXT)');
            $this->pdo->exec("INSERT INTO permissions (id, tenant_id, slug, name, module) VALUES
                (101, 1, 'atak.sse.access', 'Accéder au portail de renseignement interpersonnel', 'atak'),
                (102, 1, 'atak.toc.use', 'Tenir le poste TOC web', 'atak'),
                (201, 2, 'atak.sse.access', 'Accéder au portail de renseignement interpersonnel', 'atak')");
            $this->pdo->exec("CREATE TABLE elevation_requests (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, target_user_id INTEGER, requested_by INTEGER,
                kind TEXT, note TEXT, proposed_grade_id INTEGER, proposed_role_id INTEGER, proposed_job_role_id INTEGER, proposed_unit_id INTEGER,
                proposed_permission_ids TEXT, status TEXT DEFAULT 'pending', resolution_note TEXT, resolved_by INTEGER, resolved_at TEXT, created_at TEXT, updated_at TEXT)");
        }
        $this->now = time();

        return $this->pdo;
    }

    private function service(): OperatorAccessRequestService
    {
        return new OperatorAccessRequestService(
            null,
            function (int $tenant, int $user, string $type, array $payload): void {
                $this->notified[] = ['tenant' => $tenant, 'user' => $user, 'type' => $type, 'payload' => $payload];
            },
            // Responsables de la communauté 1 : 11 et 12 ; de la communauté 2 : 21.
            static fn (int $tenant): array => $tenant === 1 ? [11, 12] : [21],
            function (string $action, int $tenant, int $actor, int $request, array $details): void {
                $this->audited[] = ['action' => $action, 'tenant' => $tenant, 'actor' => $actor, 'request' => $request, 'details' => $details];
            },
            fn (): int => $this->now,
        );
    }

    /** @param list<string> $permissions */
    private function controller(int $user = 10, array $permissions = [], bool $csrf = true, int $tenant = 1, bool $throttled = false, ?\Closure $render = null): OperatorHubController
    {
        $exports = new SseCoverExportRepository();
        $limiter = $this->createMock(FileRateLimiter::class);
        $limiter->method('tooManyAttempts')->willReturnCallback(static fn (string $key): bool => $throttled && str_contains($key, ':access:'));
        $noop = static function (): void {
        };

        return new OperatorHubController(
            new OperatorHubRepository(),
            new OperatorIdentityService(null, null, null, null, null, null, $exports, new SseCoverExportService($exports, null, null, $noop, 'test-key'), $noop),
            static fn (): ?array => ['tenant' => $tenant, 'user' => $user, 'row' => ['id' => $user, 'callsign' => 'Viper 1-1', 'display_name' => 'Jake', 'steam_id' => null]],
            static fn (string $p): bool => in_array($p, $permissions, true),
            static fn (string $path): bool => true,
            $render ?? static fn (array $data): Response => (new Response())->setBody('rendu'),
            static fn (Request $r): bool => $csrf,
            function (string $type, string $message): void {
                $this->flashes[] = [$type, $message];
            },
            $limiter,
            static fn (int $t, int $u): array => [],
            $this->service(),
        );
    }

    /** @param array<string, mixed> $post */
    private function post(array $post = []): Request
    {
        $_POST = $post;
        $_SERVER['REQUEST_METHOD'] = 'POST';

        return new Request();
    }

    /** @return array<string, array<string, mixed>> outils de la page, par clé */
    private function tools(OperatorHubController $c, int $user = 10, int $tenant = 1): array
    {
        $data = $c->hubData(['tenant' => $tenant, 'user' => $user, 'row' => ['callsign' => 'Viper 1-1']]);
        $out = [];
        foreach ($data['hubLayout']['groups'] as $group) {
            foreach ($group['tools'] as $tool) {
                $out[$tool['key']] = $tool;
            }
        }

        return $out;
    }

    /** @param array<string, mixed> $data */
    private function hubHtml(array $data): string
    {
        extract($data + ['csrfToken' => 'jeton', 'success' => null, 'error' => null], EXTR_SKIP);
        // Comme en production : le moindre avertissement PHP de la vue est une erreur.
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
        $level = ob_get_level();
        try {
            ob_start();
            require dirname(__DIR__, 2) . '/views/operateur/hub.php';

            return (string) ob_get_clean();
        } finally {
            restore_error_handler();
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
        }
    }

    // ------------------------------------------------------------------ Demande

    public function testAMemberAsksForSeveralLockedRightsAtOnceAndManagersAreTold(): void
    {
        $this->db();
        $c = $this->controller();
        // « admin.organization » n'est le droit d'aucun outil grisé : ignoré. Aucun identifiant de membre n'est lu.
        $res = $c->accessRequest($this->post(['droits' => ['atak.sse.access', 'atak.toc.use', 'admin.organization'], 'motif' => "  Je tiens le poste\n renseignement samedi ", 'user_id' => '11', 'target_user_id' => '11']));
        self::assertSame(302, $res->statusCode());
        self::assertSame('success', $this->flashes[0][0], $this->flashes[0][1]);
        $rows = $this->pdo->query('SELECT * FROM elevation_requests')->fetchAll(PDO::FETCH_ASSOC);
        self::assertCount(1, $rows);
        self::assertSame([1, 10, 10, 'droits', 'pending'], [(int) $rows[0]['tenant_id'], (int) $rows[0]['target_user_id'], (int) $rows[0]['requested_by'], $rows[0]['kind'], $rows[0]['status']]);
        self::assertEqualsCanonicalizing([101, 102], json_decode((string) $rows[0]['proposed_permission_ids'], true));
        self::assertSame('Je tiens le poste renseignement samedi', $rows[0]['note']);
        // Cloche des responsables de la communauté, pas du demandeur ; entrée au journal d'audit.
        self::assertSame([11, 12], array_column($this->notified, 'user'));
        self::assertSame([1, 1], array_column($this->notified, 'tenant'));
        self::assertSame(OperatorAccessRequestService::NOTIFY_REQUEST, $this->notified[0]['type']);
        self::assertEqualsCanonicalizing(['Bureau SSE', 'Poste TOC'], $this->notified[0]['payload']['rights']);
        self::assertSame('operator.access_request.created', $this->audited[0]['action']);
        self::assertSame(10, $this->audited[0]['actor']);

        // La tuile grisée dit « Demande envoyée », avec la date, et propose d'annuler.
        $tools = $this->tools($c);
        self::assertTrue($tools['fichiers']['locked']);
        self::assertSame('pending', $tools['fichiers']['access']['state']);
        self::assertSame('pending', $tools['toc']['access']['state']);
        self::assertNull($tools['atak']['access']);
        $html = $this->hubHtml($c->hubData(['tenant' => 1, 'user' => 10, 'row' => []]));
        self::assertStringContainsString('Demande envoyée le ' . gmdate('d/m/Y'), $html);
        self::assertStringContainsString('operateur/acces/annuler', $html);
        self::assertStringNotContainsString('id="opx-acces"', $html, 'plus rien à demander : pas de fenêtre');
    }

    public function testADuplicateRequestForTheSameRightIsRefused(): void
    {
        $this->db();
        $c = $this->controller();
        $c->accessRequest($this->post(['droits' => ['atak.sse.access']]));
        $c->accessRequest($this->post(['droits' => ['atak.sse.access']]));
        self::assertSame('error', $this->flashes[1][0]);
        self::assertStringContainsString('déjà en attente', $this->flashes[1][1]);
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM elevation_requests')->fetchColumn());
        // Un autre droit reste demandable, dans une demande à part ; le droit déjà demandé n'y est pas repris.
        $c->accessRequest($this->post(['droits' => ['atak.sse.access', 'atak.toc.use']]));
        self::assertSame('success', $this->flashes[2][0]);
        self::assertSame('[102]', $this->pdo->query('SELECT proposed_permission_ids FROM elevation_requests ORDER BY id DESC LIMIT 1')->fetchColumn());
    }

    public function testRequestsNeedATokenARightToAskAndRespectTheRateLimit(): void
    {
        $this->db();
        $this->controller(10, [], false)->accessRequest($this->post(['droits' => ['atak.sse.access']]));
        self::assertStringContainsString('Session expirée', $this->flashes[0][1]);
        $this->controller()->accessRequest($this->post(['droits' => []]));
        self::assertSame('error', $this->flashes[1][0]);
        // Le membre a déjà le droit : la tuile n'est pas grisée, il n'y a rien à demander.
        $this->controller(10, ['atak.sse.access'])->accessRequest($this->post(['droits' => ['atak.sse.access']]));
        self::assertSame('error', $this->flashes[2][0]);
        $this->controller(10, [], true, 1, true)->accessRequest($this->post(['droits' => ['atak.sse.access']]));
        self::assertStringContainsString('Trop de demandes', $this->flashes[3][1]);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM elevation_requests')->fetchColumn());
    }

    public function testRequestsStayWithTheirMemberAndTheirCommunity(): void
    {
        $this->db();
        $this->controller(10)->accessRequest($this->post(['droits' => ['atak.sse.access']]));
        // Autre membre de la même communauté : ne voit pas la demande, ne peut pas l'annuler.
        $other = $this->controller(11);
        self::assertSame('none', $this->tools($other, 11)['fichiers']['access']['state']);
        $other->accessCancel($this->post(['tool' => 'fichiers']));
        self::assertSame('error', end($this->flashes)[0]);
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM elevation_requests')->fetchColumn());
        // Autre communauté : même membre, autre table de droits ; rien ne passe.
        $svc = $this->service();
        self::assertSame('none', $svc->states(2, 10, ['atak.sse.access'])['atak.sse.access']['state']);
        self::assertSame('unavailable', $svc->states(2, 10, ['atak.toc.use'])['atak.toc.use']['state'], 'droit inconnu de la communauté 2');
        self::assertFalse($svc->cancel(2, 10, 'atak.sse.access'));
        $this->notified = [];
        $bravo = $this->controller(20, [], true, 2);
        $bravo->accessRequest($this->post(['droits' => ['atak.sse.access', 'atak.toc.use']]));
        self::assertSame('[201]', $this->pdo->query('SELECT proposed_permission_ids FROM elevation_requests WHERE tenant_id = 2')->fetchColumn(), 'identifiant du droit dans SA communauté');
        self::assertSame([21], array_column($this->notified, 'user'), 'seuls les responsables de la communauté 2 sont prévenus');
        // La tuile TOC de la communauté 2 reste grisée, sans bouton (droit inconnu).
        self::assertSame('unavailable', $this->tools($bravo, 20, 2)['toc']['access']['state']);
    }

    public function testAMemberCancelsTheirPendingRequest(): void
    {
        $this->db();
        $c = $this->controller();
        $c->accessRequest($this->post(['droits' => ['atak.sse.access', 'atak.toc.use']]));
        $c->accessCancel($this->post(['tool' => 'seek']));
        self::assertSame(['success', 'Demande annulée.'], end($this->flashes));
        self::assertSame('[102]', $this->pdo->query('SELECT proposed_permission_ids FROM elevation_requests')->fetchColumn(), 'le droit du Poste TOC reste demandé');
        $c->accessCancel($this->post(['tool' => 'toc']));
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM elevation_requests')->fetchColumn());
        self::assertSame('operator.access_request.cancelled', end($this->audited)['action']);
        self::assertSame('none', $this->tools($c)['fichiers']['access']['state']);
        // Une demande déjà décidée ne s'annule pas.
        $c->accessRequest($this->post(['droits' => ['atak.sse.access']]));
        $this->pdo->exec("UPDATE elevation_requests SET status = 'rejected', resolved_at = CURRENT_TIMESTAMP");
        $c->accessCancel($this->post(['tool' => 'fichiers']));
        self::assertSame('error', end($this->flashes)[0]);
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM elevation_requests')->fetchColumn());
    }

    public function testARefusalShowsItsReasonAndBlocksANewRequestForAWhile(): void
    {
        $this->db();
        $c = $this->controller();
        $c->accessRequest($this->post(['droits' => ['atak.sse.access']]));
        $this->pdo->exec("UPDATE elevation_requests SET status = 'rejected', resolution_note = 'Formation <b>SSE</b> à passer', resolved_at = '" . gmdate('Y-m-d H:i:s', $this->now) . "'");
        $tool = $this->tools($c)['fichiers'];
        self::assertSame('refused', $tool['access']['state']);
        self::assertFalse($tool['access']['can_retry']);
        $data = $c->hubData(['tenant' => 1, 'user' => 10, 'row' => []]);
        $html = $this->hubHtml($data);
        self::assertStringContainsString('Refusée', $html);
        self::assertStringContainsString('Motif : Formation &lt;b&gt;SSE&lt;/b&gt; à passer', $html, 'motif du responsable, échappé');
        self::assertStringContainsString('Nouvelle demande possible le', $html);
        self::assertSame(['atak.toc.use'], array_column($data['hubAccessRights'], 'slug'), 'le droit refusé ne se recoche pas avant le délai');
        $c->accessRequest($this->post(['droits' => ['atak.sse.access']]));
        self::assertSame('error', end($this->flashes)[0]);
        self::assertStringContainsString('redemander le', end($this->flashes)[1]);
        // Passé le délai : redemandable.
        $this->now += (OperatorAccessRequestService::RETRY_DAYS + 1) * 86400;
        self::assertTrue($this->tools($c)['fichiers']['access']['can_retry']);
        self::assertStringContainsString('Redemander', $this->hubHtml($c->hubData(['tenant' => 1, 'user' => 10, 'row' => []])));
        $c->accessRequest($this->post(['droits' => ['atak.sse.access']]));
        self::assertSame('success', end($this->flashes)[0]);
        self::assertSame('pending', $this->tools($c)['fichiers']['access']['state']);
    }

    // ------------------------------------------------------------------ Tuiles

    public function testALockedTileOffersTheRequestAndAGrantedRightMakesItNormalAgain(): void
    {
        $this->db();
        $c = $this->controller();
        $data = $c->hubData(['tenant' => 1, 'user' => 10, 'row' => []]);
        $rights = array_column($data['hubAccessRights'], null, 'slug');
        self::assertEqualsCanonicalizing(['atak.sse.access', 'atak.toc.use'], array_keys($rights));
        self::assertContains('Fiches SEEK II', $rights['atak.sse.access']['tools']);
        self::assertSame(['Poste TOC', 'Plans d’opération'], $rights['atak.toc.use']['tools']);
        self::assertNotSame('', $rights['atak.sse.access']['help']);
        $html = $this->hubHtml($data);
        self::assertStringContainsString('data-opx-open="opx-acces" data-opx-check="atak.sse.access"', $html);
        self::assertStringContainsString('<dialog class="opx-dialog" id="opx-acces"', $html);
        self::assertStringContainsString('name="droits[]" value="atak.toc.use"', $html);
        self::assertStringContainsString('name="_csrf_token" value="jeton"', $html);
        self::assertStringNotContainsString('user_id', $html, 'aucun identifiant de membre dans le formulaire');
        self::assertStringNotContainsString('kicker', $html);
        self::assertStringNotContainsString('eyebrow', $html);

        // Demande acceptée et droit accordé : la tuile n'est plus grisée et n'a plus rien d'une demande.
        $c->accessRequest($this->post(['droits' => ['atak.sse.access']]));
        $this->pdo->exec("UPDATE elevation_requests SET status = 'approved', resolved_at = CURRENT_TIMESTAMP");
        $granted = $this->controller(10, ['atak.sse.access']);
        $tool = $this->tools($granted)['fichiers'];
        self::assertFalse($tool['locked']);
        self::assertNull($tool['access']);
        $data = $granted->hubData(['tenant' => 1, 'user' => 10, 'row' => []]);
        self::assertSame(['atak.toc.use'], array_column($data['hubAccessRights'], 'slug'));
        $html = $this->hubHtml($data);
        self::assertStringContainsString('href="' . url('atak/sse/fichiers') . '"', $html);
        self::assertStringNotContainsString('data-opx-check="atak.sse.access"', $html);
        // Droit retiré plus tard : il se redemande (la demande acceptée ne bloque rien).
        self::assertSame('none', $this->tools($c)['fichiers']['access']['state']);
    }

    public function testWithoutTheRequestsTableTilesStayGreyedWithNoButtonAndNoError(): void
    {
        $this->db(false);
        $c = $this->controller();
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
        try {
            $data = $c->hubData(['tenant' => 1, 'user' => 10, 'row' => []]);
            $html = $this->hubHtml($data);
        } finally {
            restore_error_handler();
        }
        self::assertSame([], $data['hubAccessRights']);
        self::assertStringContainsString('Accès réservé : Bureau SSE', $html);
        self::assertStringNotContainsString('Demander l’accès', $html);
        self::assertStringNotContainsString('opx-acces', $html);
        $c->accessRequest($this->post(['droits' => ['atak.sse.access']]));
        self::assertSame('error', end($this->flashes)[0]);
        $c->accessCancel($this->post(['tool' => 'fichiers']));
        self::assertSame('error', end($this->flashes)[0]);
    }

    // ------------------------------------------------------------------ Décision (page de gestion)

    public function testTheDecisionIsLoggedAndTheMemberIsTold(): void
    {
        $this->db();
        $svc = $this->service();
        $request = ['id' => 7, 'target_user_id' => 10, 'requested_by' => 10];
        $svc->afterDecision(1, $request, 'approved', [101, 102], [101], 'TOC : après la formation', 11);
        self::assertSame('operator.access_request.decided', $this->audited[0]['action']);
        self::assertSame(11, $this->audited[0]['actor']);
        self::assertSame(['outcome' => 'partial', 'member_user_id' => 10, 'granted' => ['Bureau SSE'], 'refused' => ['Poste TOC'], 'note' => 'TOC : après la formation'], $this->audited[0]['details']);
        self::assertCount(1, $this->notified);
        self::assertSame([10, OperatorAccessRequestService::NOTIFY_DECISION], [$this->notified[0]['user'], $this->notified[0]['type']]);
        $text = OperatorAccessRequestService::notificationText(OperatorAccessRequestService::NOTIFY_DECISION, $this->notified[0]['payload']);
        self::assertSame('Votre demande d’accès est acceptée en partie', $text['title']);
        self::assertSame('Accordé : Bureau SSE. Non accordé : Poste TOC. Motif : TOC : après la formation', $text['detail']);
        self::assertSame('operateur', $text['path']);

        $svc->afterDecision(1, $request, 'rejected', [101], [101], 'Pas cette fois', 11);
        self::assertSame('refused', $this->notified[1]['payload']['outcome']);
        self::assertSame([], $this->notified[1]['payload']['granted']);
        $svc->afterDecision(1, $request, 'approved', [101], [101], '', 11);
        self::assertSame('granted', $this->notified[2]['payload']['outcome']);
        // « Marquer en cours » n'est pas une décision : ni journal, ni cloche.
        $svc->afterDecision(1, $request, 'in_review', [101], [], '', 11);
        self::assertCount(3, $this->notified);

        // Cloche du site : les deux notifications ont un titre, un détail et un lien.
        $hub = (new \ReflectionClass(ActivityHubPresentationService::class))->newInstanceWithoutConstructor();
        $row = $hub->formatNotificationRow(['type' => OperatorAccessRequestService::NOTIFY_DECISION, 'payload_json' => json_encode($this->notified[0]['payload']), 'created_at' => '2026-11-11 10:00:00']);
        self::assertMatchesRegularExpression('/acceptée en partie|partly accepted/', $row['title']);
        self::assertStringEndsWith('/operateur', $row['href']);
        $row = $hub->formatNotificationRow(['type' => OperatorAccessRequestService::NOTIFY_REQUEST, 'payload_json' => json_encode(['member' => 'Viper 1-1', 'rights' => ['Bureau SSE'], 'note' => 'poste rens']), 'created_at' => '2026-11-11 10:00:00']);
        self::assertMatchesRegularExpression('/^Viper 1-1 (demande : Bureau SSE\. Motif : |asks for: Bureau SSE\. Reason: )poste rens$/', $row['detail']);
        self::assertSame('Access request to decide', OperatorAccessRequestService::notificationText(OperatorAccessRequestService::NOTIFY_REQUEST, [], false)['title']);
        self::assertStringEndsWith('/back-office/ressources/effectifs/elevations', $row['href']);
    }

    public function testNobodyGrantsThemselvesARight(): void
    {
        self::assertTrue(OperatorAccessRequestService::grantsToSelf(10, 10, ['permission_ids' => [101]]));
        self::assertTrue(OperatorAccessRequestService::grantsToSelf(10, 10, ['role_id' => 3]));
        self::assertFalse(OperatorAccessRequestService::grantsToSelf(10, 11, ['permission_ids' => [101]]));
        self::assertFalse(OperatorAccessRequestService::grantsToSelf(10, null, ['permission_ids' => [101]]));
        self::assertFalse(OperatorAccessRequestService::grantsToSelf(10, 10, ['grade_id' => 4]), 'un grade n’est pas un droit');

        // Le service qui applique une demande acceptée refuse avant toute écriture, quel que soit l'appelant.
        $users = $this->createMock(UserRepository::class);
        $users->expects(self::never())->method('findById');
        $permissions = $this->createMock(PermissionRepository::class);
        $permissions->expects(self::never())->method('grantToUser');
        $approval = new ElevationApprovalService(
            $users,
            $this->createMock(\App\Repositories\RoleRepository::class),
            $this->createMock(\App\Repositories\GradeRepository::class),
            $this->createMock(\App\Repositories\PersonnelJobRoleRepository::class),
            $this->createMock(\App\Repositories\PersonnelAssignmentRepository::class),
            $this->createMock(\App\Repositories\UnitRepository::class),
            $this->createMock(\App\Services\Rbac\RbacService::class),
            $this->createMock(\App\Repositories\PersonnelProfileRepository::class),
            $permissions,
            null,
            $this->createMock(\App\Services\Personnel\PersonnelServiceHistoryWriter::class),
        );
        $res = $approval->applyApprovedChanges(1, 10, ['permission_ids' => [101]], 10);
        self::assertFalse($res['ok']);
        self::assertStringContainsString('vous-même', $res['message']);
        self::assertSame([], $res['applied']);
        // Et la page de gestion passe bien par ce service pour accorder.
        $controller = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Admin/EffectifsWorkspaceController.php');
        self::assertStringContainsString('elevationApprovalService->applyApprovedChanges(', $controller);
        self::assertStringContainsString('->afterDecision(', $controller);
    }

    public function testAnotherManagerGrantsThroughTheSiteOwnMechanism(): void
    {
        $users = $this->createMock(UserRepository::class);
        $users->method('findById')->willReturn(['id' => 10, 'tenant_id' => 1]);
        $permissions = $this->createMock(PermissionRepository::class);
        // Surcharge individuelle (user_permission_overrides) : le mécanisme du site, pas un autre.
        $permissions->expects(self::once())->method('grantToUser')->with(1, 10, [101], 11);
        $approval = new ElevationApprovalService(
            $users,
            $this->createMock(\App\Repositories\RoleRepository::class),
            $this->createMock(\App\Repositories\GradeRepository::class),
            $this->createMock(\App\Repositories\PersonnelJobRoleRepository::class),
            $this->createMock(\App\Repositories\PersonnelAssignmentRepository::class),
            $this->createMock(\App\Repositories\UnitRepository::class),
            $this->createMock(\App\Services\Rbac\RbacService::class),
            $this->createMock(\App\Repositories\PersonnelProfileRepository::class),
            $permissions,
            null,
            $this->createMock(\App\Services\Personnel\PersonnelServiceHistoryWriter::class),
        );
        $res = $approval->applyApprovedChanges(1, 10, ['permission_ids' => [101]], 11);
        self::assertTrue($res['ok'], $res['message']);
        self::assertSame(['permissions'], $res['applied']);
    }

    public function testTheManagementPagePreTicksAndHighlightsWhatWasAsked(): void
    {
        $this->db();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['REQUEST_URI'] = '/back-office/ressources/effectifs/elevations';
        $data = [
            'elevationRequests' => [[
                'id' => 7, 'status' => 'pending', 'kind' => 'droits', 'target_user_id' => 10, 'requested_by' => 10,
                'target_display_name' => 'Jake', 'target_email' => null, 'requester_display_name' => 'Jake', 'requester_email' => null,
                'note' => 'Poste <rens> samedi', 'created_at' => '2026-11-10 20:15:00', 'resolution_note' => null,
                '_permission_ids' => [102], '_current_role_ids' => [], '_proposal_labels' => [],
            ]],
            'elevationKindLabels' => ['droits' => 'Droits d’accès'],
            'elevationCatalog' => ['grades' => [], 'roles' => [], 'job_roles' => [], 'units' => [], 'permissions' => [
                ['id' => 101, 'slug' => 'atak.sse.access', 'name' => 'Accéder au portail de renseignement interpersonnel', 'module' => 'atak'],
                ['id' => 102, 'slug' => 'atak.toc.use', 'name' => 'Tenir le poste TOC web', 'module' => 'atak'],
                ['id' => 103, 'slug' => 'documents.view', 'name' => 'Lire les documents', 'module' => 'documents'],
            ]],
            'csrfToken' => 'jeton',
        ];
        $level = ob_get_level();
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
        try {
            extract($data, EXTR_SKIP);
            ob_start();
            require dirname(__DIR__, 2) . '/views/admin/effectifs_workspace/elevation_requests.php';
            $html = (string) ob_get_clean();
        } finally {
            restore_error_handler();
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
        }
        // Le droit demandé est coché d'avance, en tête, signalé avec la date et le motif (échappé).
        self::assertSame(1, preg_match('#<label class="eff-elev-mode__option is-requested">\s*<input type="checkbox" name="proposed_permission_ids\[\]" value="102" checked>#', $html));
        self::assertStringContainsString('Demandé le 10/11/2026 20:15 : Poste &lt;rens&gt; samedi', $html);
        self::assertLessThan(strpos($html, 'value="101"'), strpos($html, 'value="102"'), 'les droits demandés viennent en premier');
        self::assertSame(1, preg_match('#name="proposed_permission_ids\[\]" value="101" >#', $html), 'un droit non demandé reste à cocher à la main');
        // Libellé en clair et explication avec exemple pour les droits des outils opérateur.
        self::assertStringContainsString('<strong>Poste TOC</strong>', $html);
        self::assertStringContainsString(htmlspecialchars(OperatorHubCatalog::PERMISSION_HELP['atak.toc.use'], ENT_QUOTES, 'UTF-8'), $html);
        self::assertStringContainsString('<strong>Lire les documents</strong>', $html);
        // Un clic pour décider : accepter (tout ou partie, selon les cases) ou refuser avec motif.
        self::assertStringContainsString('name="status" value="rejected"', $html);
        self::assertStringContainsString('name="resolution_note"', $html);
    }

    // ------------------------------------------------------------------ Liste des droits, traductions

    public function testEveryOperatorRightHasAClearLabelAnExplanationAndAnEnglishVersion(): void
    {
        $catalog = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Authorization/TenantPermissionCatalog.php');
        $en = require dirname(__DIR__, 2) . '/lang/en/site_auto.php';
        $rights = ['atak.sse.access', 'atak.sse.grant', 'atak.sse.case.manage', 'atak.sse.export', 'atak.sse.files.admin', 'atak.sse.lore.manage', 'atak.toc.use', 'atak.toc.manage'];
        foreach (OperatorHubCatalog::TOOLS as $tool) {
            foreach ($tool['any_permissions'] ?? [] as $p) {
                if (!str_starts_with($p, 'admin.')) {
                    self::assertContains($p, $rights, $tool['key']);
                }
            }
        }
        foreach ($rights as $slug) {
            self::assertStringContainsString("'slug' => '" . $slug . "'", $catalog, $slug . ' : déclaré dans la liste des droits du site');
            $label = OperatorHubCatalog::PERMISSION_LABELS[$slug] ?? '';
            $help = OperatorHubCatalog::PERMISSION_HELP[$slug] ?? '';
            self::assertNotSame('', $label, $slug);
            self::assertStringContainsString('Exemple : ', $help, $slug);
            foreach ([$label, $help] as $fr) {
                self::assertArrayHasKey($fr, $en, $fr);
                self::assertNotSame($fr, $en[$fr], $fr);
            }
        }
        foreach (['Demander l’accès', 'Envoyer la demande', 'Demande envoyée', 'Refusée', 'Redemander', 'Motif (facultatif)', 'Demande annulée.',
            'Demande envoyée : un responsable la traite, vous serez prévenu de sa décision.', 'Bureau SSE', 'Appareils ATAK'] as $fr) {
            self::assertArrayHasKey($fr, $en, $fr);
        }
    }
}
