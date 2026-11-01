<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Response;
use App\Services\Platform\PublicHistoryStatsService;
use PDO;
use PHPUnit\Framework\TestCase;

/** Page publique « Notre histoire » : chiffres réels, kilomètres, versions, base vide sans erreur, portée communauté isolée. */
final class PublicHistoryPageTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        $_SESSION = ['_csrf_token' => str_repeat('a', 64)];
        $_SERVER['REQUEST_URI'] = '/notre-histoire';
        $GLOBALS['__app_locale'] = 'fr';
        // Le traducteur est mémorisé globalement : repartir du français quel que soit le test précédent.
        unset($GLOBALS['__app_translator']);
        $this->cacheDir = sys_get_temp_dir() . '/comspec-history-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__app_locale'], $GLOBALS['__app_translator']);
        foreach (glob($this->cacheDir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->cacheDir);
    }

    public function testEmptyDatabaseRendersPageWithPendingCounters(): void
    {
        $service = $this->service(new PDO('sqlite::memory:'));
        $stats = $service->stats();

        self::assertSame(0, $stats['members']);
        self::assertNull($stats['founded_at']);
        self::assertSame([], $stats['years']);
        self::assertSame(0, $stats['distance_km']);
        self::assertSame(0, $stats['play_hours']);
        self::assertSame(0, $stats['communities']);

        $body = $this->render($stats, null, null, []);
        self::assertStringContainsString('Ce que les communautés ont accompli', $body);
        self::assertStringContainsString('À venir', $body);
        self::assertStringContainsString('La frise se remplira', $body);
        self::assertStringContainsString('Les premiers kilomètres apparaîtront', $body);
        self::assertStringContainsString('L’historique des versions sera publié', $body);
        self::assertStringContainsString('La première communauté peut ouvrir son espace', $body);
        self::assertStringNotContainsString('L’histoire de', $body);
        self::assertStringNotContainsString('Comment ces kilomètres ont été parcourus', $body);
    }

    public function testRealDataFeedsPlatformAndCommunityScopes(): void
    {
        $pdo = $this->seededPdo();
        $service = $this->service($pdo);

        $platform = $service->stats();
        self::assertSame('2024-03-15', $platform['founded_at']);
        self::assertSame(3, $platform['members']);
        self::assertSame(3, $platform['operations']);
        self::assertSame(5, $platform['flight_hours']);
        self::assertSame(2, $platform['academy_validated']);
        self::assertSame(3, $platform['orbat_units']);
        self::assertSame([], $platform['highlights']);
        self::assertSame(['2024', '2025'], array_column($platform['years'], 'year'));
        // Communautés : hors espace technique par défaut.
        self::assertSame(2, $platform['communities']);
        // Kilomètres : somme des modes de déplacement de toutes les communautés (mètres → km entiers).
        self::assertSame(65, $platform['distance_km']);
        self::assertSame(['foot' => 13, 'land' => 20, 'air' => 30, 'sea' => 0, 'halo' => 1], $platform['distance_modes']);
        self::assertSame(3, $platform['distance_sessions']);
        self::assertSame([0, 65], array_column($platform['years'], 'distance_km'));
        // Heures de jeu : seulement le temps de jeu total, pas le temps par rôle.
        self::assertSame(3, $platform['play_hours']);

        $community = $service->stats(2);
        self::assertSame('community', $community['scope']);
        self::assertSame('Task Force Lynx', $community['community_name']);
        self::assertSame(2, $community['members']);
        self::assertSame(2, $community['operations']);
        self::assertSame(3, $community['flight_hours']);
        self::assertSame(2, $community['orbat_units']);
        self::assertSame(37, $community['distance_km']);
        self::assertSame(2, $community['distance_sessions']);
        self::assertSame(2, $community['play_hours']);
        $titles = array_column($community['highlights'], 'title');
        self::assertContains('Opération Aube Rouge', $titles);
        self::assertContains('Retour sur la campagne', $titles);
        // Rien d’une autre communauté, ni d’événement annulé ou à venir, ni d’article brouillon.
        self::assertNotContains('Opération voisine', $titles);
        self::assertNotContains('Opération annulée', $titles);
        self::assertNotContains('Opération future', $titles);
        self::assertNotContains('Brouillon', $titles);

        // Cache court : une nouvelle donnée n’apparaît qu’après expiration (ou recalcul forcé).
        $pdo->exec("INSERT INTO units (tenant_id, name) VALUES (2, 'Section appui')");
        self::assertSame(2, $service->stats(2)['orbat_units']);
        self::assertSame(3, $service->stats(2, true)['orbat_units']);

        $body = $this->render($platform, $community, $this->releases());
        self::assertStringContainsString('2024', $body);
        self::assertStringContainsString('data-nh-count="65"', $body);
        self::assertStringContainsString('Comment ces kilomètres ont été parcourus', $body);
        self::assertStringContainsString('En chute libre', $body);
        self::assertStringContainsString('L’histoire de Task Force Lynx', $body);
        self::assertStringContainsString('data-nh-count="37"', $body);
        self::assertStringContainsString('Opération Aube Rouge', $body);
        self::assertStringContainsString('2 participants', $body);
        self::assertStringContainsString('Premier message ATAK', $body);
        self::assertStringNotContainsString('pilot@example.test', $body);
    }

    public function testVersionsTimelineShowsReleasesWithFiltersAndBulletins(): void
    {
        $stats = $this->service(new PDO('sqlite::memory:'))->stats();
        $body = $this->render($stats, null, $this->releases());

        self::assertStringContainsString('Toutes les versions', $body);
        self::assertStringContainsString('En service aujourd’hui', $body);
        self::assertStringContainsString('Liaison plus fluide sous charge', $body);
        self::assertStringContainsString('ATAK natif 2.9.19', $body);
        self::assertStringContainsString('COMSPEC Link 2.0.110', $body);
        self::assertStringContainsString('Portail Athena 1.7.0', $body);
        self::assertStringContainsString('data-products="atak link"', $body);
        self::assertStringContainsString('data-nh-filter="portal"', $body);
        self::assertStringNotContainsString('data-nh-filter="sse"', $body);
        self::assertStringContainsString('nouveautes/update/01203', $body);
        self::assertStringContainsString('Date connue au mois près', $body);
        // Plus jamais l’ancienne carte unique « 1.0.0 x.0.0 ».
        self::assertStringNotContainsString('x.0.0', $body);
    }

    public function testControllerUsesSessionTenantOnlyForSignedInMembers(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Web/SitePagesController.php');
        self::assertStringContainsString('function publicHistoryTenantId', $src);
        self::assertStringContainsString("Session::get('user_id')", $src);
        self::assertStringContainsString("'platformStats' => \$platformStats", $src);
        self::assertStringContainsString("'releaseHistory' => \$releaseHistory", $src);
        self::assertStringContainsString('PublicReleaseHistoryService', $src);
    }

    private function service(PDO $pdo): PublicHistoryStatsService
    {
        return new PublicHistoryStatsService($pdo, $this->cacheDir, new \DateTimeImmutable('2025-06-01 12:00:00'));
    }

    /** @return array<string, mixed> */
    private function releases(): array
    {
        return [
            'releases' => [
                [
                    'id' => 'atak-2-9-19', 'date' => '2025-05-20', 'date_precision' => 'day', 'product' => 'atak',
                    'filters' => ['atak', 'link'],
                    'tags' => [
                        ['product' => 'atak', 'label' => 'ATAK natif', 'version' => '2.9.19'],
                        ['product' => 'link', 'label' => 'COMSPEC Link', 'version' => '2.0.110'],
                    ],
                    'title' => 'Liaison plus fluide sous charge', 'title_en' => '', 'summary' => '',
                    'points' => ['Les ordres ne figent plus l’image'], 'points_en' => [],
                    'type' => 'performance', 'codename' => 'Denver', 'bulletin' => 1203,
                ],
                [
                    'id' => 'portal-1-7-0', 'date' => '2025-04-18', 'date_precision' => 'month', 'product' => 'portal',
                    'filters' => ['portal'],
                    'tags' => [['product' => 'portal', 'label' => 'Portail', 'version' => '1.7.0']],
                    'title' => 'Chaque version du portail porte un nom de ville', 'title_en' => '', 'summary' => '',
                    'points' => [], 'points_en' => [], 'type' => null, 'codename' => 'Denver', 'bulletin' => 0,
                ],
            ],
            'current' => [
                ['product' => 'atak', 'label' => 'ATAK natif', 'version' => '2.9.19', 'date' => '2025-05-20'],
                ['product' => 'portal', 'label' => 'Portail Athena', 'version' => '1.7.0', 'date' => '2025-04-18'],
            ],
            'counts' => ['atak' => 1, 'link' => 1, 'overwatch' => 0, 'sse' => 0, 'portal' => 1],
            'bulletins' => 1,
            'first_date' => '2025-04-18',
        ];
    }

    /**
     * @param array<string, mixed> $platform
     * @param array<string, mixed>|null $community
     * @param array<string, mixed>|null $releases
     * @param list<array<string, string>>|null $milestones
     */
    private function render(array $platform, ?array $community, ?array $releases, ?array $milestones = null): string
    {
        $response = Response::view('layout.marketing', [
            'content' => 'site.history',
            'title' => 'ATHENA — Notre histoire',
            'marketingActive' => 'history',
            'platformStats' => $platform,
            'communityStats' => $community,
            'releaseHistory' => $releases,
            'milestones' => $milestones ?? [['date' => '2024-12-01', 'title' => 'Premier message ATAK', 'text' => 'Premier message ATAK transmis.']],
        ]);
        self::assertSame(200, $response->statusCode());

        return $response->body();
    }

    private function seededPdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE tenants (id INTEGER PRIMARY KEY, name TEXT, slug TEXT, created_at TEXT)');
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, tenant_id INT, email TEXT, status TEXT, is_service_account INT DEFAULT 0, created_at TEXT)');
        $pdo->exec('CREATE TABLE community_events (id INTEGER PRIMARY KEY, tenant_id INT, title TEXT, starts_at TEXT, cancelled_at TEXT)');
        $pdo->exec('CREATE TABLE community_event_rsvps (id INTEGER PRIMARY KEY, event_id INT, user_id INT, status TEXT)');
        $pdo->exec('CREATE TABLE pilot_logbook_entries (id INTEGER PRIMARY KEY, tenant_id INT, user_id INT, flown_on TEXT, seconds INT, status TEXT)');
        $pdo->exec('CREATE TABLE academy_learners (tenant_id INT, user_id INT, course_key TEXT, state_json TEXT)');
        $pdo->exec('CREATE TABLE training_enrollments (id INTEGER PRIMARY KEY, tenant_id INT, user_id INT, status TEXT)');
        $pdo->exec('CREATE TABLE units (id INTEGER PRIMARY KEY, tenant_id INT, name TEXT)');
        $pdo->exec('CREATE TABLE tenant_mini_articles (id INTEGER PRIMARY KEY, tenant_id INT, title TEXT, status TEXT, published_at TEXT)');
        $pdo->exec('CREATE TABLE atak_distance_stats (id INTEGER PRIMARY KEY, tenant_id INT, user_id INT, day TEXT, foot_m INT, land_m INT, sea_m INT, air_m INT, halo_m INT)');
        $pdo->exec('CREATE TABLE atak_screen_time (tenant_id INT, user_id INT, day TEXT, kind TEXT, item_key TEXT, seconds INT)');

        $pdo->exec("INSERT INTO tenants (id, name, slug, created_at) VALUES
            (1, 'Plateforme', 'default', '2024-03-15 10:00:00'), (2, 'Task Force Lynx', 'tf-lynx', '2024-05-02 09:00:00'),
            (3, 'Voisins', 'voisins', '2024-09-01 09:00:00')");
        $pdo->exec("INSERT INTO users (id, tenant_id, email, status, is_service_account, created_at) VALUES
            (1, 2, 'pilot@example.test', 'active', 0, '2024-05-03 10:00:00'),
            (2, 2, 'b@example.test', 'active', 0, '2025-01-10 10:00:00'),
            (3, 2, 'bot@example.test', 'active', 1, '2025-01-10 10:00:00'),
            (4, 2, 'old@example.test', 'inactive', 0, '2024-06-10 10:00:00'),
            (5, 3, 'c@example.test', 'active', 0, '2024-09-02 10:00:00')");
        $pdo->exec("INSERT INTO community_events (id, tenant_id, title, starts_at, cancelled_at) VALUES
            (1, 2, 'Opération Aube Rouge', '2024-11-20 20:00:00', NULL),
            (2, 2, 'Manœuvre de printemps', '2025-04-12 20:00:00', NULL),
            (3, 2, 'Opération annulée', '2025-02-01 20:00:00', '2025-01-30 10:00:00'),
            (4, 2, 'Opération future', '2025-09-01 20:00:00', NULL),
            (5, 3, 'Opération voisine', '2025-03-01 20:00:00', NULL)");
        $pdo->exec("INSERT INTO community_event_rsvps (event_id, user_id, status) VALUES (1, 1, 'yes'), (1, 2, 'yes'), (2, 1, 'no')");
        $pdo->exec("INSERT INTO pilot_logbook_entries (tenant_id, user_id, flown_on, seconds, status) VALUES
            (2, 1, '2024-11-20', 7200, 'verified'), (2, 1, '2025-04-12', 3600, 'declared'),
            (2, 1, '2025-04-13', 36000, 'rejected'), (3, 5, '2025-03-01', 7200, 'verified')");
        $pdo->exec("INSERT INTO academy_learners (tenant_id, user_id, course_key, state_json) VALUES
            (2, 1, 'fondamentaux', '{\"chapter\":3,\"completed\":true}'),
            (2, 2, 'fondamentaux', '{\"chapter\":1}'),
            (2, 1, '__notes', '{\"completed\":true}')");
        $pdo->exec("INSERT INTO training_enrollments (tenant_id, user_id, status) VALUES (3, 5, 'completed'), (2, 2, 'in_progress')");
        $pdo->exec("INSERT INTO units (tenant_id, name) VALUES (2, 'Groupement'), (2, 'Escadrille'), (3, 'Section')");
        $pdo->exec("INSERT INTO tenant_mini_articles (tenant_id, title, status, published_at) VALUES
            (2, 'Retour sur la campagne', 'published', '2025-05-01 10:00:00'),
            (2, 'Brouillon', 'draft', NULL)");
        $pdo->exec("INSERT INTO atak_distance_stats (tenant_id, user_id, day, foot_m, land_m, sea_m, air_m, halo_m) VALUES
            (2, 1, '2025-04-12', 5400, 10000, 0, 20000, 1300),
            (2, 2, '2025-04-12', 600, 0, 0, 0, 0),
            (3, 5, '2025-03-01', 7000, 10500, 0, 10200, 0)");
        $pdo->exec("INSERT INTO atak_screen_time (tenant_id, user_id, day, kind, item_key, seconds) VALUES
            (2, 1, '2025-04-12', 'play', 'total', 7200), (2, 2, '2025-04-12', 'play', 'total', 1800),
            (2, 1, '2025-04-12', 'role', 'PIL', 99999), (3, 5, '2025-03-01', 'play', 'total', 3600)");

        return $pdo;
    }
}
