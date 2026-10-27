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
use App\Support\ComspecApiKeyAuth;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * NET-028 : POST /api/atak/salute recopie le compte rendu dans le tchat (« ALERTE TACTIQUE|SALUTE|indicatif|grille|x|y|
 * S=…|A=…|L=…|U=…|T=…|E=… »). L'indicatif, la grille, la position et les champs S à T précèdent un « | » : un indicatif
 * « ORDER », ou un champ qui finit par « order », donnait une ligne que la lecture masque (protocole des ordres C2), et
 * 650 lignes de suite figeaient la relève avec curseur de COMSPEC Link (limit=25 : 50 + 3 × 200 lignes lues).
 * POST /api/chat refuse déjà ces lignes depuis NET-016 ; la route SALUTE applique désormais la même règle.
 * Vraies routes saluteStore et chatIndex, dépôt réel sur sqlite, session web d'un membre.
 */
final class AtakSaluteHiddenChatTest extends TestCase
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
        // Un test précédent peut avoir retiré $_POST (unset) : Request le copie dans une propriété typée array.
        $this->hadPost = isset($_POST);
        $this->savedPost = $_POST ?? null;
        $_POST = [];
        $this->tenantId = 1200000 + random_int(1, 99999);

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

        // Session web d'un membre de la communauté (pas de clé jeu : les pages Tacmap et Overwatch).
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

    /** Chaque morceau du compte rendu qui précède un « | » peut cacher la ligne : tous sont refusés, rien n'est écrit. */
    public function testSaluteThatTheChatWouldHideIsRefusedAndNothingIsStoredOrLogged(): void
    {
        $cases = [
            'indicatif « ORDER »' => [['call_sign' => 'ORDER', 'size' => '3 BMP'], 'order_incomplete'],
            'indicatif « X-Order »' => [['call_sign' => 'X-Order', 'activity' => 'patrouille'], 'order_incomplete'],
            'champ qui finit par « order »' => [['call_sign' => 'ALPHA', 'unit' => 'platoon in order', 'equipment' => 'fusils'], 'order_incomplete'],
            'grille « ORDER »' => [['call_sign' => 'ALPHA', 'grid' => 'ORDER', 'size' => '2'], 'order_incomplete'],
            'position « order »' => [['call_sign' => 'ALPHA', 'pos_x' => 'order', 'size' => '2'], 'order_incomplete'],
            'indicatif des réglages' => [['call_sign' => 'REGLAGES AFFICHAGE', 'size' => '2'], 'reserved_settings'],
            'champ des réglages' => [['call_sign' => 'ALPHA', 'size' => 'reglages affichage', 'activity' => 'arrêt'], 'reserved_settings'],
        ];
        foreach ($cases as $label => [$payload, $error]) {
            $response = $this->salute($payload);

            self::assertSame(422, $response->statusCode(), $label);
            $json = json_decode($response->body(), true);
            self::assertIsArray($json, $label);
            self::assertSame($error, $json['error'] ?? null, $label);
            self::assertStringStartsWith('Compte rendu refusé', (string) ($json['message'] ?? ''), $label);
        }

        self::assertSame(0, $this->storedRows(), 'rien n’est stocké dans le tchat');
        self::assertSame([], $this->saluteLogLabels(), 'rien n’est inscrit au journal d’activité');
    }

    /**
     * Ce que la lecture montre passe comme avant : « Border » (« order » termine un mot), « ordre » en français, et
     * « order » dans le dernier champ (E), qu'aucun « | » ne suit. Tous arrivent à la relève avec curseur du jeu.
     */
    public function testOrdinarySalutesAreStoredLoggedAndReachTheCursorPoll(): void
    {
        $this->seed('TOC', 'déjà lu', 5);
        $payloads = [
            ['call_sign' => 'ALPHA', 'size' => '3 BMP', 'activity' => 'ordre de marche vers le nord', 'location' => '031047',
                'unit' => 'inconnue', 'time' => '14:20', 'equipment' => 'canons 30 mm', 'grid' => '031047', 'pos_x' => 3100, 'pos_y' => 4700],
            ['call_sign' => 'Border', 'size' => '2 pax', 'activity' => 'observation'],
            ['call_sign' => 'BRAVO', 'size' => '1 véhicule', 'equipment' => 'matériel in order'],
        ];
        foreach ($payloads as $payload) {
            $response = $this->salute($payload);
            self::assertSame(201, $response->statusCode(), (string) $payload['call_sign']);
        }

        $bodies = array_column($this->drain(5, 25), 'body');
        self::assertCount(3, $bodies, 'les trois comptes rendus arrivent au jeu');
        foreach (['ALPHA', 'Border', 'BRAVO'] as $k => $callSign) {
            self::assertStringStartsWith('ALERTE TACTIQUE|SALUTE|' . $callSign . '|', (string) $bodies[$k]);
        }
        self::assertCount(3, $this->saluteLogLabels(), 'journal d’activité comme avant');
    }

    /**
     * Mesure de NET-028 : 650 SALUTE d'un indicatif « ORDER », puis 5 vrais messages. Sur main, les 650 étaient stockés
     * (201) et masqués : la relève de COMSPEC Link (limit=25) lisait 650 lignes cachées, rendait une page vide et ne
     * recevait jamais les 5 messages suivants. Désormais les 650 sont refusés et les 5 messages arrivent.
     */
    public function testSixHundredFiftySalutesFromAnOrderCallsignNoLongerFreezeTheGamePoll(): void
    {
        $this->seed('TOC', 'déjà lu', 5);
        $statuses = [];
        for ($i = 0; $i < 650; $i++) {
            $code = $this->salute(['call_sign' => 'ORDER', 'size' => (string) $i])->statusCode();
            $statuses[$code] = ($statuses[$code] ?? 0) + 1;
        }
        $this->seed('TOC', 'vrai message', 5);

        $received = array_filter(
            array_column($this->drain(5, 25), 'body'),
            static fn ($body): bool => $body === 'vrai message'
        );
        ksort($statuses);
        $seen = implode(', ', array_map(static fn (int $code, int $n): string => $n . ' × ' . $code, array_keys($statuses), $statuses));
        self::assertCount(5, $received, 'vrais messages reçus par COMSPEC Link : ' . count($received) . '/5 ; réponses SALUTE : ' . $seen);
        self::assertSame([422 => 650], $statuses);
    }

    /** POST /api/atak/salute d'un membre connecté au site (pages Tacmap et Overwatch). */
    private function salute(array $payload): Response
    {
        $_GET = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/atak/salute';
        $this->setControllerProperty($this->controller, 'jsonBodyCache', array_merge(['mapId' => self::MAP_ID], $payload));

        return $this->controller->saluteStore(new Request());
    }

    /**
     * Relèves successives du jeu (GET /api/chat?mapId=1&limit=…&after=…, sans indicatif), le curseur avançant au plus
     * grand id reçu.
     *
     * @return list<array<string, mixed>>
     */
    private function drain(int $after, int $limit): array
    {
        $seen = [];
        for ($poll = 0; $poll < 20; $poll++) {
            $_GET = ['mapId' => (string) self::MAP_ID, 'limit' => (string) $limit, 'after' => (string) $after];
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SERVER['REQUEST_URI'] = '/api/chat?' . http_build_query($_GET);
            $response = $this->controller->chatIndex(new Request());
            self::assertSame(200, $response->statusCode());
            $rows = json_decode($response->body(), true);
            self::assertIsArray($rows);
            if ($rows === []) {
                break;
            }
            array_push($seen, ...$rows);
            $after = max(array_map(static fn (array $row): int => (int) $row['id'], $rows));
        }

        return $seen;
    }

    /** @return list<string> */
    private function saluteLogLabels(): array
    {
        $labels = array_column((new AtakActivityLogService())->listRecent($this->tenantId, self::MAP_ID), 'label');

        return array_values(array_filter($labels, static fn ($label): bool => str_starts_with((string) $label, 'SALUTE')));
    }

    private function seed(string $author, string $body, int $count): void
    {
        $insert = $this->pdo->prepare(
            "INSERT INTO atak_chat_messages (tenant_id, map_id, author, body, source, channel_key)
             VALUES (?, ?, ?, ?, 'game', 'general')"
        );
        for ($i = 0; $i < $count; $i++) {
            $insert->execute([$this->tenantId, self::MAP_ID, $author, $body]);
        }
    }

    private function storedRows(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM atak_chat_messages')->fetchColumn();
    }

    private function setControllerProperty(AtakApiController $controller, string $name, mixed $value): void
    {
        (new \ReflectionProperty(AtakApiController::class, $name))->setValue($controller, $value);
    }
}
