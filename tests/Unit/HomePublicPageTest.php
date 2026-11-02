<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Response;
use App\Services\Platform\PublicHistoryStatsService;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Accueil public : chiffres réels (même service et même cache que « Notre histoire »), cartes de
 * communauté, référencement conservé et page allégée (vidéos non préchargées, images WebP).
 */
final class HomePublicPageTest extends TestCase
{
    private string $cacheDir;

    private ?string $appUrl = null;

    protected function setUp(): void
    {
        $_SESSION = ['_csrf_token' => str_repeat('a', 64)];
        $_SERVER['REQUEST_URI'] = '/';
        // Adresse publique connue : sans elle, ni canonique ni hreflang ne sont produits.
        $this->appUrl = $_ENV['APP_URL'] ?? null;
        $_ENV['APP_URL'] = 'https://athena-comspec.eu';
        $GLOBALS['__app_locale'] = 'fr';
        unset($GLOBALS['__app_translator']);
        $this->cacheDir = sys_get_temp_dir() . '/comspec-home-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__app_locale'], $GLOBALS['__app_translator']);
        if ($this->appUrl === null) {
            unset($_ENV['APP_URL']);
        } else {
            $_ENV['APP_URL'] = $this->appUrl;
        }
        foreach (glob($this->cacheDir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->cacheDir);
    }

    public function testEmptyDatabaseRendersWithoutFiguresAndKeepsSeo(): void
    {
        $stats = $this->service(new PDO('sqlite::memory:'))->stats();
        $body = $this->render($stats, []);

        // Aucun chiffre inventé : ni bloc de chiffres ni compteur quand tout vaut zéro.
        self::assertStringNotContainsString('data-hp-figures', $body);
        self::assertStringNotContainsString('data-hp-count', $body);
        self::assertStringContainsString('Ils opèrent déjà <span>sur Athena.</span>', $body);
        self::assertStringContainsString('hu-empty', $body);
        // Aucune carte inventée : seule la carte « Votre unité ici » reste.
        self::assertStringNotContainsString('class="hu-card"', $body);
        $this->assertSeo($body);
    }

    public function testRealFiguresAndCommunityCards(): void
    {
        $stats = $this->service($this->seededPdo())->stats();
        $body = $this->render($stats, [[
            'name' => 'Task Force Lynx', 'slug' => 'tf-lynx', 'logo_url' => '', 'banner_url' => '', 'accent' => '#d97706',
            'href' => '/c/tf-lynx', 'tagline' => 'Opérations du jeudi', 'game' => 'Arma 3', 'locale_label' => 'FR',
            'affiliation' => '', 'featured' => true, 'recruitment_open' => true, 'members' => 12, 'units' => 3, 'ops_per_month' => 4.0,
        ]]);

        self::assertStringContainsString('Ce que les communautés ont accompli', $body);
        // Kilomètres (mètres cumulés → km), membres actifs hors comptes de service, heures de jeu.
        self::assertStringContainsString('data-hp-count="37"', $body);
        self::assertStringContainsString('<span data-hp-count="2">2</span>', $body);
        self::assertStringContainsString('Membres actifs', $body);
        self::assertStringContainsString('En chute libre', $body);
        self::assertStringContainsString('notre-histoire', $body);
        // Valeur à zéro : absente (aucune opération, aucune heure de vol dans le jeu d’essai).
        self::assertStringNotContainsString('Heures de vol', $body);
        self::assertStringNotContainsString('pilot@example.test', $body);

        self::assertStringContainsString('class="hu-fcard"', $body);
        self::assertStringContainsString('--u-accent:#d97706', $body);
        self::assertStringContainsString('Recrute', $body);
        self::assertStringContainsString('<span class="hu-tag" translate="no">Arma 3</span><span class="hu-tag">FR</span>', $body);
        $this->assertSeo($body);
    }

    public function testEnglishLabels(): void
    {
        $GLOBALS['__app_locale'] = 'en';
        $stats = $this->service($this->seededPdo())->stats();
        $body = $this->render($stats, []);
        self::assertStringContainsString('What the communities have achieved', $body);

        $fr = require dirname(__DIR__, 2) . '/lang/fr/home.php';
        $en = require dirname(__DIR__, 2) . '/lang/en/home.php';
        $keys = static fn (array $a): array => array_values(array_filter(array_keys($a), static fn (string $k): bool => str_starts_with($k, 'figures_')));
        self::assertSame($keys($fr), $keys($en));
    }

    public function testPageStaysLight(): void
    {
        $body = $this->render([], []);
        // Vidéos : aucun octet avant que le script décide d’en lire une.
        self::assertStringNotContainsString('preload="auto"', $body);
        // Fonds du hero : WebP responsive, seul le premier part au chargement.
        self::assertMatchesRegularExpression('/<img id="hero-poster" src="[^"]+hero-1-1920\.webp" srcset="[^"]+960w/', $body);
        self::assertSame(3, substr_count($body, 'data-hero-src='));
        self::assertStringContainsString('imagesrcset=', $body);
        // Plus de cascade @import de design-system.css ni de flou sur la barre fixe.
        self::assertStringNotContainsString('design-system.css', $body);
        self::assertStringContainsString('display=swap', $body);

        $root = dirname(__DIR__, 2);
        $css = (string) file_get_contents($root . '/public/assets/css/home-impact.css');
        self::assertStringNotContainsString('backdrop-filter', $css);
        self::assertMatchesRegularExpression('/prefers-reduced-motion: no-preference\)\s*\{\s*\.home-impact \.hp--figures\.is-armed \.hp-route/', $css);

        $js = (string) file_get_contents($root . '/public/assets/js/home-impact.js');
        self::assertStringContainsString('function homeFigures()', $js);
        self::assertStringContainsString("matchMedia('(prefers-reduced-motion: reduce)').matches) return;", $js);
        self::assertStringContainsString('function autoVideoAllowed()', $js);
        self::assertStringNotContainsString("addEventListener('scroll'", $js);

        // Bandeau de don commun au site public, comme dans layout.marketing.
        self::assertStringContainsString("views/partials/donation_banner.php", (string) file_get_contents($root . '/views/home/index.php'));

        $ctrl = (string) file_get_contents($root . '/app/Controllers/Web/HomeController.php');
        self::assertStringContainsString('PublicHistoryStatsService', $ctrl);
        self::assertStringNotContainsString('getPlatformOperationalKpis', $ctrl);
    }

    private function assertSeo(string $body): void
    {
        self::assertStringContainsString('<link rel="canonical"', $body);
        self::assertStringContainsString('hreflang="en"', $body);
        self::assertStringContainsString('property="og:image"', $body);
        self::assertStringContainsString('"@type":"FAQPage"', $body);
        self::assertStringContainsString('"@type":"Organization"', $body);
        self::assertSame(1, substr_count($body, '<h1 '));
    }

    private function service(PDO $pdo): PublicHistoryStatsService
    {
        return new PublicHistoryStatsService($pdo, $this->cacheDir, new \DateTimeImmutable('2025-06-01 12:00:00'));
    }

    /**
     * @param array<string, mixed> $stats
     * @param list<array<string, mixed>> $units
     */
    private function render(array $stats, array $units): string
    {
        $response = Response::view('home.index', [
            'title' => 'Athena Comspec — Portail MILSIM',
            'platformStats' => $stats,
            'featuredUnits' => $units,
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
        $pdo->exec('CREATE TABLE atak_distance_stats (id INTEGER PRIMARY KEY, tenant_id INT, user_id INT, day TEXT, foot_m INT, land_m INT, sea_m INT, air_m INT, halo_m INT)');
        $pdo->exec('CREATE TABLE atak_screen_time (tenant_id INT, user_id INT, day TEXT, kind TEXT, item_key TEXT, seconds INT)');
        $pdo->exec("INSERT INTO tenants (id, name, slug, created_at) VALUES (1, 'Plateforme', 'default', '2024-03-15 10:00:00'), (2, 'Task Force Lynx', 'tf-lynx', '2024-05-02 09:00:00')");
        $pdo->exec("INSERT INTO users (id, tenant_id, email, status, is_service_account, created_at) VALUES
            (1, 2, 'pilot@example.test', 'active', 0, '2024-05-03 10:00:00'),
            (2, 2, 'b@example.test', 'active', 0, '2025-01-10 10:00:00'),
            (3, 2, 'bot@example.test', 'active', 1, '2025-01-10 10:00:00')");
        $pdo->exec("INSERT INTO atak_distance_stats (tenant_id, user_id, day, foot_m, land_m, sea_m, air_m, halo_m) VALUES
            (2, 1, '2025-04-12', 5400, 10000, 0, 20000, 1300), (2, 2, '2025-04-12', 600, 0, 0, 0, 0)");
        $pdo->exec("INSERT INTO atak_screen_time (tenant_id, user_id, day, kind, item_key, seconds) VALUES (2, 1, '2025-04-12', 'play', 'total', 7200)");

        return $pdo;
    }
}
