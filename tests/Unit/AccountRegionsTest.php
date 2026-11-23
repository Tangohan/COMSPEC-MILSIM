<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\UserRegionRepository;
use App\Services\Admin\PlatformAccountsOverviewService;
use App\Support\AccountRegions;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Région des comptes : catalogue fermé, seuil de confidentialité, dépôt, statistiques agrégées, export et purge.
 */
final class AccountRegionsTest extends TestCase
{
    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function testCatalogIsClosedAndUnique(): void
    {
        $all = AccountRegions::all();
        self::assertTrue(AccountRegions::isValid('FR-IDF'));
        self::assertTrue(AccountRegions::isValid(AccountRegions::OTHER));
        self::assertFalse(AccountRegions::isValid('Paris'));
        self::assertFalse(AccountRegions::isValid(''));
        $count = 0;
        foreach (AccountRegions::groups() as $items) {
            $count += count($items);
        }
        self::assertCount($count, $all, 'aucun code répété entre les groupes');
        self::assertCount(13, AccountRegions::groups()['France métropolitaine']);
    }

    public function testThresholdMasksSmallRegionsWithoutLeakingThem(): void
    {
        $r = AccountRegions::applyThreshold(['FR-IDF' => 12, 'FR-BRE' => 5, 'FR-COR' => 4], 30);
        self::assertSame(['FR-IDF', 'FR-BRE'], array_column($r['rows'], 'code'));
        self::assertSame(0, $r['others'], 'une seule petite région : jamais montrée seule');
        self::assertSame(30 - 21 + 4, $r['none'], 'la petite région est rangée avec les comptes sans région');

        $r = AccountRegions::applyThreshold(['FR-IDF' => 12, 'FR-COR' => 3, 'FR-GP' => 3], 20);
        self::assertSame(6, $r['others'], 'plusieurs petites régions regroupées, seulement au-dessus du seuil');
        self::assertSame(20 - 18, $r['none']);

        $r = AccountRegions::applyThreshold(['FR-INCONNU' => 50, 'FR-IDF' => 0], 10);
        self::assertSame([], $r['rows'], 'codes hors catalogue ignorés');
    }

    public function testRepositoryStoresOnlyACatalogCodeAndCanErase(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE user_regions (user_id INTEGER PRIMARY KEY, region_code TEXT, source TEXT, updated_at TEXT)');
        $repo = new UserRegionRepository($pdo);
        self::assertNull($repo->get(7));
        self::assertFalse($repo->save(7, 'Paris', '2026-11-01 10:00:00'));
        self::assertTrue($repo->save(7, 'FR-IDF', '2026-11-01 10:00:00'));
        self::assertTrue($repo->save(7, 'FR-BRE', '2026-11-02 10:00:00'));
        self::assertSame('FR-BRE', $repo->get(7)['region_code']);
        self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM user_regions')->fetchColumn());
        self::assertTrue($repo->clear(7));
        self::assertNull($repo->get(7));
    }

    public function testBreakdownIsAggregateOnlyAndCountsEachPersonOnce(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, tenant_id INTEGER, email TEXT, status TEXT, deleted_at TEXT)');
        $pdo->exec('CREATE TABLE user_regions (user_id INTEGER PRIMARY KEY, region_code TEXT, source TEXT, updated_at TEXT)');
        $id = 0;
        $add = static function (string $email, ?string $region) use ($pdo, &$id): void {
            ++$id;
            $pdo->prepare("INSERT INTO users (id, tenant_id, email, status) VALUES (?, 2, ?, 'active')")->execute([$id, $email]);
            if ($region !== null) {
                $pdo->prepare("INSERT INTO user_regions VALUES (?, ?, 'declared', '2026-11-01 00:00:00')")->execute([$id, $region]);
            }
        };
        for ($i = 0; $i < 6; ++$i) {
            $add("idf$i@x.fr", 'FR-IDF');
        }
        for ($i = 0; $i < 2; ++$i) {
            $add("cor$i@x.fr", 'FR-COR');
        }
        $add('none@x.fr', null);
        $add('Idf0@x.fr', 'FR-IDF'); // même personne sur une seconde communauté
        $add('fiche-ab@sans-compte.invalid', null); // fiche sans compte : exclue

        $res = (new PlatformAccountsOverviewService($pdo))->regionBreakdown();

        self::assertTrue($res['available']);
        self::assertSame(9, $res['total']);
        self::assertSame([['code' => 'FR-IDF', 'label' => 'Île-de-France', 'count' => 6]], $res['rows']);
        self::assertSame(0, $res['others']);
        self::assertSame(3, $res['none'], 'sans région (1) + région masquée (2)');
        self::assertStringNotContainsString('@', json_encode($res, JSON_UNESCAPED_UNICODE), 'aucune donnée nominative');
    }

    public function testStepRoutesExportAndPurgeAreWired(): void
    {
        $root = self::root();
        $registry = (string) file_get_contents($root . '/database/steps/registry.php');
        self::assertSame(1, substr_count($registry, "'5010_user_regions.php'"));
        preg_match_all("/'file' => '(\\d+)_/", $registry, $m);
        self::assertSame(count($m[1]), count(array_unique($m[1])), 'aucun numéro d’étape répété');
        self::assertStringContainsString("'user_regions'", (string) file_get_contents($root . '/app/Services/Account/AccountPurgeService.php'));
        $export = (string) file_get_contents($root . '/app/Services/Account/AccountDataExportService.php');
        self::assertStringContainsString("'region.csv'", $export);
        self::assertStringNotContainsString('REMOTE_ADDR', (string) file_get_contents($root . '/app/Controllers/Web/AccountPrivacyController.php'), 'l’adresse IP n’est jamais lue pour la région');
        $routes = (string) file_get_contents($root . '/routes/web.php');
        self::assertStringContainsString("'/account/donnees/region'", $routes);
    }
}
