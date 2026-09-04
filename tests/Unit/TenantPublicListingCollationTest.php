<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class TenantPublicListingCollationTest extends TestCase
{
    public function testListForRegistryIncludesFirstTenantAndSkipsDefaultSlug(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE tenants (id INTEGER PRIMARY KEY, name TEXT, slug TEXT, community_code TEXT, logo_url TEXT, settings TEXT)');
        $pdo->exec("INSERT INTO tenants (id, name, slug, community_code, logo_url, settings) VALUES
            (1, 'Alpha', 'alpha', 'ALP', NULL, NULL),
            (2, 'Placeholder', 'default', NULL, NULL, NULL),
            (3, 'Bravo', 'bravo', 'BRV', '/logo.png', NULL)");

        $rows = (new TenantRepository($pdo))->listForRegistry();
        $slugs = array_map(static fn (array $row): string => (string) $row['slug'], $rows);

        self::assertContains('alpha', $slugs);
        self::assertContains('bravo', $slugs);
        self::assertNotContains('default', $slugs);
    }

    public function testFindBySlugUsesCollationSafeHelper(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Repositories/TenantRepository.php');
        self::assertStringContainsString('function findBySlug', $src);
        self::assertStringContainsString('SqlText::equals', $src);
        self::assertStringNotContainsString('SELECT * FROM tenants WHERE slug = ? LIMIT 1', $src);
        self::assertStringNotContainsString('WHERE id != 1', $src);
    }

    public function testPublicTenantMemberCountUsesCollationSafeHelper(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Repositories/UserRepository.php');
        self::assertStringContainsString('function countActiveMembers', $src);
        self::assertStringContainsString('SqlText::coalesceInLiterals', $src);
        self::assertStringNotContainsString("WHERE COALESCE(p.status, u.status) = 'active'", $src);
    }

    public function testHomeIndexListsRegistryWithoutLogoGate(): void
    {
        $home = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Web/HomeController.php');
        self::assertStringContainsString('listForRegistry()', $home);
        self::assertStringNotContainsString('count($featuredUnits) >= 10', $home);
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/home/index.php');
        self::assertStringContainsString('who-item__initials', $view);
    }

    public function testCountActiveMembersRunsOnSqlite(): void
    {
        $ref = new \ReflectionClass(UserRepository::class);
        if ($ref->hasProperty('hasMembershipTable')) {
            $prop = $ref->getProperty('hasMembershipTable');
            $prop->setAccessible(true);
            $prop->setValue(null, null);
        }
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, tenant_id INT, status TEXT)');
        $pdo->exec("INSERT INTO users (id, tenant_id, status) VALUES (1, 7, 'active'), (2, 7, 'pending_verification')");

        $count = (new UserRepository($pdo))->countActiveMembers(7);
        self::assertSame(1, $count);
    }

    public function testRuntimePhpHasNoRawSlugEqualsCompare(): void
    {
        $roots = [
            dirname(__DIR__, 2) . '/app/Repositories',
            dirname(__DIR__, 2) . '/app/Services',
            dirname(__DIR__, 2) . '/app/Controllers',
        ];
        $offenders = [];
        foreach ($roots as $root) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
            foreach ($it as $file) {
                if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
                    continue;
                }
                $src = (string) file_get_contents($file->getPathname());
                if (!preg_match_all('/slug = \\?/', $src, $matches, PREG_OFFSET_CAPTURE)) {
                    continue;
                }
                foreach ($matches[0] as [, $offset]) {
                    $before = substr($src, max(0, $offset - 280), 280);
                    if (!preg_match('/\bSET\b/i', $before, $setMatch, PREG_OFFSET_CAPTURE)) {
                        $lastSet = false;
                    } else {
                        // Dernière occurrence de SET (y compris « SET\n » sans espace).
                        $lastSet = false;
                        if (preg_match_all('/\bSET\b/i', $before, $allSets, PREG_OFFSET_CAPTURE)) {
                            $lastSet = (int) $allSets[0][count($allSets[0]) - 1][1];
                        }
                    }
                    $lastWhere = false;
                    if (preg_match_all('/\bWHERE\b/i', $before, $allWhere, PREG_OFFSET_CAPTURE)) {
                        $lastWhere = (int) $allWhere[0][count($allWhere[0]) - 1][1];
                    }
                    $isAssignment = $lastSet !== false && ($lastWhere === false || $lastSet > $lastWhere);
                    if (!$isAssignment) {
                        $offenders[] = str_replace('\\', '/', substr($file->getPathname(), strlen(dirname(__DIR__, 2)) + 1))
                            . ' offset ' . $offset;
                    }
                }
            }
        }
        self::assertSame([], $offenders, 'Comparaison slug = ? restante (hors SET).');
    }

    public function testRemainingHotspotRepositoriesUseSqlTextEquals(): void
    {
        $files = [
            'app/Repositories/BadgeRepository.php',
            'app/Repositories/ArsenalWardrobeRepository.php',
            'app/Repositories/OrbatChartTypeRepository.php',
            'app/Repositories/TrainingRepository.php',
            'app/Repositories/PersonnelJobRoleRepository.php',
            'app/Repositories/TenantMessageRepository.php',
            'app/Repositories/InterteamMissionRepository.php',
            'app/Repositories/Courrier/DocumentTemplateRepository.php',
            'app/Services/Community/TenantTypeSwitchService.php',
            'app/Services/Rbac/RoleDefinitionCatalog.php',
            'app/Services/Doctrine/DocumentAudienceResolver.php',
        ];
        $root = dirname(__DIR__, 2);
        foreach ($files as $rel) {
            $src = (string) file_get_contents($root . '/' . $rel);
            self::assertStringContainsString('SqlText::equals', $src, $rel);
        }
    }
}
