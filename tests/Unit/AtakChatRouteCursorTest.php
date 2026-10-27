<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Api\AtakApiController;
use App\Core\Container;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AtakDataRepository;
use App\Repositories\AtakOrderRepository;
use App\Services\Platform\FeatureGateService;
use App\Services\Tactical\AtakActivityLogService;
use App\Support\AtakArmaWriteGuard;
use App\Support\ComspecApiKeyAuth;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Tchat par les VRAIES routes : chatIndex (GET /api/chat) et chatStore (POST /api/chat), dépôt réel sur sqlite.
 *
 * Le jeu (COMSPEC Link limit=25, ATAK natif limit=40) relève avec « after » et avance son curseur au plus grand
 * id REÇU. Un bloc de lignes que la lecture masque (protocole « ORDER|… », réglages « REGLAGES / AFFICHAGE|… »)
 * figeait la relève de tous les clients : 50 lignes « ORDER| » suffisaient pour Link, 80 pour le natif, parce
 * que le dépôt les retirait APRÈS le LIMIT et que la relève s'arrêtait sur une fenêtre vide ou « pas pleine ».
 * Désormais la relève lit la fenêtre brute, et POST /api/chat refuse ce que la lecture masquerait ensuite.
 */
final class AtakChatRouteCursorTest extends TestCase
{
    private const MAP_ID = 1;

    private int $tenantId;

    private PDO $pdo;

    private AtakApiController $controller;

    private ?PDO $savedPdo = null;

    /** @var array<string, object> */
    private array $savedContainer = [];

    private bool $savedSessionStarted = false;

    /** @var array{set: bool, value: mixed} */
    private array $savedSession = ['set' => false, 'value' => null];

    /** @var array<string, mixed> */
    private array $savedGet = [];

    /** @var array<string, mixed> */
    private array $savedServer = [];

    private bool $hadPost = false;

    private mixed $savedPost = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hadPost = isset($_POST);
        $this->savedPost = $_POST ?? null;
        $_POST = [];
        $this->tenantId = 1100000 + random_int(1, 99999);

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(
            'CREATE TABLE atak_chat_messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tenant_id INTEGER NOT NULL,
                map_id INTEGER NOT NULL,
                author TEXT,
                body TEXT,
                source TEXT,
                channel_key TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )'
        );
        $db = new \ReflectionProperty(Database::class, 'pdo');
        $saved = $db->getValue();
        $this->savedPdo = $saved instanceof PDO ? $saved : null;
        $db->setValue(null, $this->pdo);

        // Formule de la communauté : accès ATAK accordé (le contrôle de formule n'est pas l'objet de ce test).
        $gate = $this->createMock(FeatureGateService::class);
        $gate->method('allows')->willReturn(true);
        $instances = new \ReflectionProperty(Container::class, 'instances');
        /** @var array<string, object> $current */
        $current = $instances->getValue();
        $this->savedContainer = $current;
        $instances->setValue(null, array_merge($current, [FeatureGateService::class => $gate]));

        // Session web d'un membre de la communauté (pas de clé jeu : flux « web »).
        $started = new \ReflectionProperty(Session::class, 'started');
        $this->savedSessionStarted = (bool) $started->getValue();
        $started->setValue(null, true);
        $this->savedSession = ['set' => isset($_SESSION), 'value' => $_SESSION ?? null];
        $_SESSION = ['tenant_id' => $this->tenantId];
        $this->savedGet = $_GET;
        foreach (['REQUEST_METHOD', 'REQUEST_URI', 'REMOTE_ADDR', 'HTTP_X_COMSPEC_KEY', 'HTTP_X_ATAK_TOKEN', 'HTTP_AUTHORIZATION'] as $key) {
            if (array_key_exists($key, $_SERVER)) {
                $this->savedServer[$key] = $_SERVER[$key];
            }
        }
        unset($_SERVER['HTTP_X_COMSPEC_KEY'], $_SERVER['HTTP_X_ATAK_TOKEN'], $_SERVER['HTTP_AUTHORIZATION']);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        ComspecApiKeyAuth::resetForTests();

        $controller = (new \ReflectionClass(AtakApiController::class))->newInstanceWithoutConstructor();
        $this->setControllerProperty($controller, 'atak', new AtakDataRepository($this->pdo));
        $this->setControllerProperty($controller, 'orderRepository', new AtakOrderRepository($this->pdo));
        $this->setControllerProperty($controller, 'activityLog', new AtakActivityLogService());
        $this->controller = $controller;
    }

    protected function tearDown(): void
    {
        (new AtakActivityLogService())->purgeAllForTenant($this->tenantId);
        @unlink(base_path('storage/cache/mission-settings/t' . $this->tenantId . '_m' . self::MAP_ID . '.json'));
        ComspecApiKeyAuth::resetForTests();
        (new \ReflectionProperty(Database::class, 'pdo'))->setValue(null, $this->savedPdo);
        (new \ReflectionProperty(Container::class, 'instances'))->setValue(null, $this->savedContainer);
        (new \ReflectionProperty(Session::class, 'started'))->setValue(null, $this->savedSessionStarted);
        if ($this->savedSession['set']) {
            $_SESSION = $this->savedSession['value'];
        } else {
            unset($_SESSION);
        }
        $_GET = $this->savedGet;
        foreach (['REQUEST_METHOD', 'REQUEST_URI', 'REMOTE_ADDR', 'HTTP_X_COMSPEC_KEY', 'HTTP_X_ATAK_TOKEN', 'HTTP_AUTHORIZATION'] as $key) {
            if (array_key_exists($key, $this->savedServer)) {
                $_SERVER[$key] = $this->savedServer[$key];
            } else {
                unset($_SERVER[$key]);
            }
        }
        if ($this->hadPost) {
            $_POST = $this->savedPost;
        } else {
            unset($_POST);
        }
        parent::tearDown();
    }

    public function testCursorPollThroughTheRouteDrainsABurstOldestFirstWithoutGaps(): void
    {
        // 60 messages arrivés d'un coup après le curseur : chaque relève rend les 25 plus ANCIENS non reçus.
        $this->seed('TOC', 'déjà lu', 5);
        $this->seed('TOC', 'rafale', 60);

        self::assertSame(range(6, 30), $this->ids($this->poll(5, 25)));
        self::assertSame(range(6, 65), $this->drain(5, 25), 'les 60 messages arrivent tous, dans l’ordre');
    }

    public function testBlockOfProtocolOrderLinesNoLongerFreezesTheCursorPoll(): void
    {
        // Lignes « ORDER|… » déjà en base (stockées avant ce correctif) entre le curseur et 5 vrais messages.
        $this->seed('TOC', 'déjà lu', 5);
        $this->seed('MEMBRE', 'ORDER|', 80);
        $this->seed('TOC', 'vrai message', 5);

        self::assertSame(range(86, 90), $this->ids($this->poll(5, 25)), 'COMSPEC Link (limit=25)');
        self::assertSame(range(86, 90), $this->ids($this->poll(5, 40)), 'ATAK natif (limit=40)');
    }

    public function testLongBlockOfHiddenSettingsIsCrossedWithinOnePoll(): void
    {
        // 600 lignes de réglages : plus que les 4 fenêtres de 2 × limit d'avant (200 lignes pour Link).
        $this->seed('TOC', 'déjà lu', 5);
        $this->seed('REGLAGES', 'AFFICHAGE|carte', 600);
        $this->seed('TOC', 'vrai message', 5);

        self::assertSame(range(606, 610), $this->ids($this->poll(5, 25)));
    }

    public function testChatStoreRefusesWhatTheChatWouldHideAndStoresNothing(): void
    {
        $cases = [
            ['MEMBRE', 'ORDER|', 'order_incomplete'],
            ['MEMBRE', 'Reçu, ORDER| annulé', 'order_incomplete'],
            ['MEMBRE', 'order|ORD-1', 'order_incomplete'],
            ['REGLAGES', 'AFFICHAGE|carte', 'reserved_author'],
            [' reglages ', 'bonjour', 'reserved_author'],
        ];
        foreach ($cases as [$author, $body, $error]) {
            $response = $this->post($author, $body);
            self::assertSame(422, $response->statusCode(), $author . ' / ' . $body);
            $json = json_decode($response->body(), true);
            self::assertIsArray($json);
            self::assertSame($error, $json['error'] ?? null, $body);
            self::assertStringStartsWith('Message refusé', (string) ($json['message'] ?? ''));
        }

        self::assertSame(0, $this->storedRows(), 'rien n’est stocké');
    }

    /**
     * Flux du jeu (clé d'accès, garde Arma) : même refus que pour un membre connecté au site. Sans ce cas, un refus
     * limité au flux web passait inaperçu.
     */
    public function testChatStoreFromTheGameRefusesAnIncompleteOrderToo(): void
    {
        $_SERVER['HTTP_X_COMSPEC_KEY'] = 'cle-de-test';
        (new \ReflectionProperty(ComspecApiKeyAuth::class, 'matchedTenantId'))->setValue(null, $this->tenantId);
        $judged = 0;
        $guard = $this->createMock(AtakArmaWriteGuard::class);
        $guard->method('assertActor')->willReturnCallback(static function () use (&$judged): array {
            ++$judged;

            return ['steam_uid' => '76561190000000007', 'session_ok' => true];
        });
        $this->setControllerProperty($this->controller, 'armaGuard', $guard);

        foreach (['ORDER|', 'Reçu, ORDER| annulé'] as $body) {
            $response = $this->post('TOC', $body);
            self::assertSame(422, $response->statusCode(), $body);
            $json = json_decode($response->body(), true);
            self::assertIsArray($json);
            self::assertSame('order_incomplete', $json['error'] ?? null, $body);
        }

        self::assertSame(2, $judged, 'flux du jeu : la garde Arma a jugé chaque envoi');
        self::assertSame(0, $this->storedRows(), 'rien n’est stocké');
    }

    public function testChatStoreStillInterceptsCompleteOrdersAndRecognizedSettings(): void
    {
        $order = $this->post('TOC', 'ORDER|ORD-1-2|MOVE|alpha|IMPORTANT|TOC|aller au point');
        $settings = $this->post('ALPHA', 'REGLAGES AFFICHAGE|adversaire=0|independants=1|civils=1');
        $legacySettings = $this->post('REGLAGES', 'AFFICHAGE|adversaire=1|independants=0|civils=1');

        foreach ([$order, $settings, $legacySettings] as $response) {
            self::assertSame(201, $response->statusCode());
            $json = json_decode($response->body(), true);
            self::assertIsArray($json);
            self::assertTrue($json['hidden_from_chat'] ?? null);
        }
        self::assertSame(0, $this->storedRows(), 'interceptés avant stockage, comme avant');
    }

    public function testChatStoreStillStoresAnOrdinaryMessage(): void
    {
        $response = $this->post('ALPHA', 'Contact au nord, ordre de repli reçu');

        self::assertSame(201, $response->statusCode());
        self::assertSame(1, $this->storedRows());
        self::assertSame([1], $this->ids($this->poll(0, 25)));
    }

    /**
     * « order| » qui termine un indicatif (« Border ») n'est pas le protocole d'ordres. L'ancienne règle (« ORDER| »
     * n'importe où) masquait ces messages ; refusés à l'écriture, ils auraient été perdus avec leurs effets
     * (journal d'activité, Discord, position de l'unité).
     */
    public function testCallsignEndingWithOrderIsAnOrdinaryMessage(): void
    {
        $response = $this->post('Border', 'MP|Border|TOC|reçu');

        self::assertSame(201, $response->statusCode());
        self::assertSame(1, $this->storedRows());
        self::assertSame([1], $this->ids($this->poll(0, 25)), 'montré à la lecture');
        $labels = array_column((new AtakActivityLogService())->listRecent($this->tenantId, self::MAP_ID), 'label');
        self::assertContains('Message privé — Border → TOC', $labels);

        foreach (['ALERTE TACTIQUE|TIC|Border|031047|||contact est', 'MP|RECORDER|TOC|ok', 'Éorder|x'] as $body) {
            self::assertFalse(AtakDataRepository::isProtocolOrderChatBody($body), $body);
        }
        foreach (['ORDER|', 'order|ORD-1', 'Reçu, ORDER| annulé', 'MP|TOC|ALPHA|ORDER|ORD-1|MOVE', '[TOC]ORDER|x', '2ORDER|x'] as $body) {
            self::assertTrue(AtakDataRepository::isProtocolOrderChatBody($body), $body);
        }
        self::assertTrue(AtakDataRepository::isProtocolOrderChatBody("Border|\xff"), 'corps illisible : règle large');
    }

    public function testCursorPollStopsBeforeTheGameDllCutsItsOutputAndTheRestFollows(): void
    {
        // Cas mesuré par le relecteur avec la vraie DLL : 40 messages de 200 caractères pour l'ATAK natif (limit=40),
        // sortie coupée à 7 996 octets en gardant les plus récents, 9 messages perdus pour de bon.
        $this->seed('TOC', 'déjà lu', 5);
        for ($k = 0; $k < 40; $k++) {
            $this->seed('TOC-ALPHA', self::frenchText($k, 200), 1);
        }

        $first = $this->poll(5, 40);
        self::assertLessThan(40, count($first), 'page réduite à ce que la DLL rend sans couper');
        self::assertLessThanOrEqual(7500, strlen(self::dllOutput($first)));
        self::assertSame(range(6, 5 + count($first)), $this->ids($first), 'les plus anciens d’abord');
        self::assertSame(range(6, 45), $this->drain(5, 40), 'les 40 arrivent, sans trou ni doublon');
    }

    public function testNewlinesAreCountedAsTheGameDllWritesThem(): void
    {
        // La DLL remplace chaque saut de ligne par « — » entre espaces (5 octets) : 10 corps de 200 sauts de ligne
        // donnent plus de 10 000 octets de sortie pour 2 000 octets de corps.
        $this->seed('TOC', 'déjà lu', 5);
        $this->seed('TOC', str_repeat("\n", 200), 10);

        $first = $this->poll(5, 25);
        self::assertNotEmpty($first);
        self::assertLessThanOrEqual(7500, strlen(self::dllOutput($first)));
    }

    public function testPageIsFilledUpToTheBudgetWithBodiesCutAt280Characters(): void
    {
        // La DLL n'écrit que les 280 premiers caractères du corps : compter 600 caractères arrêterait la page
        // à la moitié de ce qui passe.
        $this->seed('TOC', 'déjà lu', 5);
        for ($k = 0; $k < 30; $k++) {
            $this->seed('TOC-ALPHA', self::frenchText($k, 600), 1);
        }

        $first = $this->poll(5, 25);
        $next = $this->poll(max($this->ids($first)), 1);
        self::assertCount(1, $next);
        self::assertLessThanOrEqual(7500, strlen(self::dllOutput($first)));
        self::assertGreaterThan(7500, strlen(self::dllOutput($first)) + strlen(self::dllOutput($next)), 'page pleine');
    }

    public function testOneMessageLongerThanTheBudgetStillMovesTheCursor(): void
    {
        $this->seed('TOC', 'déjà lu', 5);
        $this->seed(str_repeat('A', 9000), 'indicatif démesuré', 1);
        $this->seed('TOC', 'suivant', 1);

        self::assertSame([6], $this->ids($this->poll(5, 25)));
        self::assertSame([7], $this->ids($this->poll(6, 25)));
    }

    public function testFirstSnapshotWithoutCursorIsNotReduced(): void
    {
        for ($k = 0; $k < 40; $k++) {
            $this->seed('TOC-ALPHA', self::frenchText($k, 200), 1);
        }

        self::assertCount(40, $this->poll(0, 40));
    }

    /** Texte français accentué (2 octets par lettre accentuée), $chars caractères, numéroté. */
    private static function frenchText(int $k, int $chars): string
    {
        $base = "Ordre TOC : progressez vers le point d'appui, sécurisez l'axe, rendez compte à l'arrivée. ";

        return mb_substr(sprintf('#%02d ', $k) . str_repeat($base, 10), 0, $chars, 'UTF-8');
    }

    /**
     * Sortie de la DLL du jeu pour ces messages avant sa coupe finale (Extension.cs, SimplifyChatMessagesJson) :
     * « id\tauteur\tcorps (280 car.)\tcreated_at\tcanal\n », tabulation → espace, saut de ligne → « — », \r retiré.
     *
     * @param list<array<string, mixed>> $rows
     */
    private static function dllOutput(array $rows): string
    {
        $clean = static fn (string $s): string => str_replace(["\t", "\n", "\r"], [' ', ' — ', ''], $s);
        $out = '';
        foreach ($rows as $row) {
            $id = (string) ($row['id'] ?? '');
            if ($id === '' || $id === '0') {
                continue;
            }
            $channel = trim((string) ($row['channel_key'] ?? '')) !== '' ? (string) $row['channel_key'] : 'general';
            $out .= $clean($id) . "\t" . $clean((string) ($row['author'] ?? '')) . "\t"
                . $clean(mb_substr((string) ($row['body'] ?? ''), 0, 280, 'UTF-8')) . "\t"
                . $clean((string) ($row['created_at'] ?? '')) . "\t" . $clean($channel) . "\n";
        }

        return $out;
    }

    private function seed(string $author, string $body, int $count): void
    {
        $insert = $this->pdo->prepare(
            "INSERT INTO atak_chat_messages (tenant_id, map_id, author, body, source, channel_key)
             VALUES (?, ?, ?, ?, 'game', 'general')"
        );
        $this->pdo->beginTransaction();
        for ($i = 0; $i < $count; $i++) {
            $insert->execute([$this->tenantId, self::MAP_ID, $author, $body]);
        }
        $this->pdo->commit();
    }

    private function storedRows(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM atak_chat_messages')->fetchColumn();
    }

    /** GET /api/chat?mapId=1&limit=…&after=… comme le jeu (sans indicatif ni canal). */
    private function poll(int $after, int $limit): array
    {
        $_GET = ['mapId' => (string) self::MAP_ID, 'limit' => (string) $limit];
        if ($after > 0) {
            $_GET['after'] = (string) $after;
        }
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/chat?' . http_build_query($_GET);
        $response = $this->controller->chatIndex(new Request());
        self::assertSame(200, $response->statusCode());
        $rows = json_decode($response->body(), true);
        self::assertIsArray($rows);

        return $rows;
    }

    /**
     * Relèves successives, le curseur avançant au plus grand id reçu, comme le client du jeu.
     *
     * @return list<int>
     */
    private function drain(int $after, int $limit): array
    {
        $seen = [];
        for ($poll = 0; $poll < 20; $poll++) {
            $ids = $this->ids($this->poll($after, $limit));
            if ($ids === []) {
                break;
            }
            array_push($seen, ...$ids);
            $after = max($ids);
        }

        return $seen;
    }

    /** POST /api/chat d'un membre connecté au site. */
    private function post(string $author, string $body): Response
    {
        $_GET = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/chat';
        $this->setControllerProperty($this->controller, 'jsonBodyCache', [
            'mapId' => self::MAP_ID,
            'author' => $author,
            'body' => $body,
        ]);

        return $this->controller->chatStore(new Request());
    }

    /** @return list<int> */
    private function ids(array $rows): array
    {
        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }

    private function setControllerProperty(AtakApiController $controller, string $name, mixed $value): void
    {
        (new \ReflectionProperty(AtakApiController::class, $name))->setValue($controller, $value);
    }
}
