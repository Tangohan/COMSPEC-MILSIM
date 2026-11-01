<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\GameCatalog;
use App\Support\Migrations\MigrationRegistry;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\MysqlDdlSqlitePdo;

require_once dirname(__DIR__, 2) . '/bootstrap/migration_runner.php';

/** Jeux déclarés par les communautés : une seule entrée par jeu dans l'annuaire. */
final class GameCatalogTest extends TestCase
{
    public function testSpellingsOfTheSameGameShareOneName(): void
    {
        foreach (['Arma3', 'Arma 3', 'ARMA 3', 'ArmA III', 'A3', ' arma  3 ', 'Arma-3'] as $raw) {
            self::assertSame('Arma 3', GameCatalog::canonical($raw), $raw);
        }
        self::assertSame('Arma Reforger', GameCatalog::canonical('reforger'));
        self::assertSame('Arma Reforger', GameCatalog::canonical('ARMA REFORGR'));
        self::assertSame('DayZ', GameCatalog::canonical('Day-Z'));
        self::assertSame('Hell Let Loose', GameCatalog::canonical('HLL'));
        self::assertSame('Ready or Not', GameCatalog::canonical('ready or not'));
        self::assertSame('Ground Branch', GameCatalog::canonical('GroundBranch'));
        self::assertSame('Insurgency: Sandstorm', GameCatalog::canonical('Insurgency Sandstorm'));
        self::assertNull(GameCatalog::canonical('Mon jeu maison'));
    }

    public function testNumberedGamesAreNeverMerged(): void
    {
        self::assertSame('Arma 2', GameCatalog::canonical('ArmA II'));
        self::assertSame('Arma 4', GameCatalog::canonical('Arma4'));
        self::assertNotSame(GameCatalog::canonical('Arma 2'), GameCatalog::canonical('Arma 3'));
        self::assertSame('Squad 44', GameCatalog::canonical('squad44'));
        self::assertSame('Squad', GameCatalog::canonical('SQUAD'));
    }

    public function testKeyIgnoresCaseAccentsSpacesAndPunctuation(): void
    {
        self::assertSame('arma3', GameCatalog::key(' ARMA 3 '));
        self::assertSame('eteoperation', GameCatalog::key('Été – Opération'));
        self::assertSame('', GameCatalog::key('  - '));
    }

    public function testNormalizeIsIdempotentAndKeepsUnknownGames(): void
    {
        self::assertSame('Arma 3 / Arma Reforger', GameCatalog::normalize('arma3, Reforger'));
        self::assertSame('Arma 3', GameCatalog::normalize('Arma 3 / ARMA3'));
        self::assertSame('Mon jeu maison', GameCatalog::normalize('  Mon   jeu maison '));
        self::assertSame('', GameCatalog::normalize(''));
        foreach (['arma3, Reforger', 'ArmA III', 'Insurgency: Sandstorm', 'Mon jeu maison', 'HLL + Squad'] as $raw) {
            $once = GameCatalog::normalize($raw);
            self::assertSame($once, GameCatalog::normalize($once), $raw);
        }
    }

    public function testGroupGivesOneChoicePerGameWithCounts(): void
    {
        $grouping = GameCatalog::group(['Arma3', 'Arma 3', 'ARMA 3', 'ArmA III', '', 'Arma Reforger', 'Arma 3 / Reforger', 'Starfall Tactics', 'starfall tactic', 'STARFALL TACTICS']);
        $groups = $grouping['groups'];
        self::assertSame(['label' => 'Arma 3', 'count' => 5], $groups['arma3']);
        self::assertSame(['label' => 'Arma Reforger', 'count' => 2], $groups['armareforger']);
        // Jeu inconnu : variantes rapprochées, graphie la plus soignée affichée.
        self::assertSame(['label' => 'Starfall Tactics', 'count' => 3], $groups['starfalltactics']);
        self::assertCount(3, $groups);
        self::assertSame(['Arma 3', 'Arma Reforger', 'Starfall Tactics'], array_column(array_values($groups), 'label'));
        self::assertSame([], $grouping['keys'][4]);
        self::assertSame(['arma3', 'armareforger'], $grouping['keys'][6]);
        self::assertSame(['starfalltactics'], $grouping['keys'][8]);
    }

    public function testShortUnknownNamesAreNotFuzzyMerged(): void
    {
        $groups = GameCatalog::group(['Xyz1', 'Xyz2', 'Abcd', 'Abce'])['groups'];
        self::assertCount(4, $groups);
    }

    public function testMigrationHarmonizesLabelsKeepsOriginalAndIsIdempotent(): void
    {
        $pdo = new MysqlDdlSqlitePdo();
        $pdo->exec('CREATE TABLE tenants (id INTEGER PRIMARY KEY, name TEXT, slug TEXT, settings TEXT)');
        $insert = $pdo->prepare('INSERT INTO tenants (id, name, slug, settings) VALUES (?, ?, ?, ?)');
        $insert->execute([1, 'A', 'a', json_encode(['community' => ['game_label' => 'ARMA3', 'registry_listed' => false], 'empty' => new \stdClass()])]);
        $insert->execute([2, 'B', 'b', json_encode(['community' => ['game_label' => 'Arma 3']])]);
        $insert->execute([3, 'C', 'c', json_encode(['community' => ['game_label' => 'Mon jeu maison']])]);
        $insert->execute([4, 'D', 'd', null]);
        $insert->execute([5, 'E', 'e', json_encode(['timezone' => 'Europe/Paris'])]);
        $pdo->changes = 0;

        $out = self::runStep($pdo);
        self::assertStringContainsString('1 libellé(s) harmonisé(s)', $out);
        $one = json_decode((string) $pdo->query('SELECT settings FROM tenants WHERE id = 1')->fetchColumn(), true);
        self::assertSame('Arma 3', $one['community']['game_label']);
        self::assertSame('ARMA3', $one['community']['game_label_original']);
        self::assertFalse($one['community']['registry_listed']);
        self::assertStringContainsString('"empty":{}', (string) $pdo->query('SELECT settings FROM tenants WHERE id = 1')->fetchColumn());
        $three = json_decode((string) $pdo->query('SELECT settings FROM tenants WHERE id = 3')->fetchColumn(), true);
        self::assertArrayNotHasKey('game_label_original', $three['community']);

        $pdo->changes = 0;
        self::assertStringContainsString('0 libellé(s)', self::runStep($pdo));
        self::assertSame(0, $pdo->changes);
    }

    private static function runStep(PDO $pdo): string
    {
        $root = dirname(__DIR__, 2);
        $migrationFlush = static function (): void {
        };
        $migrationEnsurePdo = static function (): void {
        };
        require $root . '/database/steps/_context.php';
        ob_start();
        try {
            require MigrationRegistry::find($root, 'community_game_labels')['path'];
        } finally {
            $output = (string) ob_get_clean();
        }

        return $output;
    }
}
