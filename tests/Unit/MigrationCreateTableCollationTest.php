<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Migrations\TableCollationAligner;
use PHPUnit\Framework\TestCase;
use Tests\Support\CreateTableCollationScanner;

/**
 * Toute nouvelle table d'une étape, d'un fichier SQL ou d'une migration bootstrap naît en utf8mb4_unicode_ci.
 *
 * Sans COLLATE, la table prend la collation par défaut du serveur (utf8mb4_uca1400_ai_ci sous MariaDB 11.4+) et ses
 * jointures texte avec les tables en utf8mb4_unicode_ci échouent en production (« Illegal mix of collations », 1267).
 * L'historique déjà livré est figé dans tests/fixtures/create_table_collation_allowlist.php.
 */
final class MigrationCreateTableCollationTest extends TestCase
{
    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function testNoNewCreateTableWithoutUnicodeCollation(): void
    {
        /** @var array<string, list<string>> $allow */
        $allow = require self::root() . '/tests/fixtures/create_table_collation_allowlist.php';
        $problems = [];
        foreach (CreateTableCollationScanner::scan(self::root()) as $file => $tables) {
            $left = $allow[$file] ?? [];
            foreach ($tables as $table) {
                $at = array_search($table, $left, true);
                if ($at === false) {
                    $problems[] = $file . ' : ' . $table;
                    continue;
                }
                unset($left[$at]);
            }
        }

        self::assertSame([], $problems, 'Ajoutez « DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci » à ces CREATE TABLE (voir docs/dev/COLLATIONS-MYSQL.md).');
    }

    public function testScannerFlagsOnlyTablesWithoutTheCollation(): void
    {
        $sql = "-- CREATE TABLE commentee (id INT)\n"
            . "CREATE TABLE IF NOT EXISTS `sans_collation` (\n  id INT, nom VARCHAR(20)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n"
            . "CREATE TABLE avec_collation (id INT, KEY k (id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n"
            . "CREATE TABLE autre_forme (id INT) ENGINE=InnoDB DEFAULT CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci;\n";
        self::assertSame(['sans_collation'], CreateTableCollationScanner::offenders($sql));

        $php = "<?php\n\$engine = \$sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';\n"
            . "\$pdo->exec('CREATE TABLE portable (id INT, code VARCHAR(8))' . \$engine);\n"
            . "\$pdo->exec(\"CREATE TABLE oubliee (code VARCHAR(8)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4\");\n";
        self::assertSame(['oubliee'], CreateTableCollationScanner::offenders($php));
    }

    public function testFixedTablesAreNoLongerAllowlistedAndAreAligned(): void
    {
        /** @var array<string, list<string>> $allow */
        $allow = require self::root() . '/tests/fixtures/create_table_collation_allowlist.php';
        self::assertArrayNotHasKey('bootstrap/atak_terminal_sync_migration.php', $allow);
        foreach (['atak_terminal_sync', 'player_sessions', 'member_availability_checks', 'sse_entity_links', 'user_community_membership_periods'] as $table) {
            self::assertContains($table, TableCollationAligner::RECENT_TABLES);
        }
        foreach (['users', 'tenants', 'personnel_profiles', 'user_profiles'] as $historic) {
            self::assertNotContains($historic, TableCollationAligner::RECENT_TABLES, 'Jamais de conversion des grosses tables historiques.');
        }
    }
}
