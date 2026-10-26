<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\AthenaInspectorMetricsRepository;
use PHPUnit\Framework\TestCase;

/**
 * Onglet Mods : un échec du catalogue de noms ne doit pas effacer les listes joueurs
 * ni les versions issues des connexions en jeu.
 */
final class AthenaDataInspectorModsTabTest extends TestCase
{
    public function testCatalogFailureKeepsPlayerListsAndSessionVersions(): void
    {
        $pdo = $this->pdo(true);
        $repo = new AthenaInspectorMetricsRepository($pdo);
        $mods = $repo->mods(7, 1, '');

        self::assertFalse($mods['catalog_available']);
        self::assertSame([], $mods['mods']);
        self::assertSame(0, $mods['mods_total']);
        self::assertSame(4, $mods['players_with_list']);
        self::assertSame(23, $mods['avg_mods_per_player']);
        self::assertSame(32, $mods['max_mods_per_player']);
        self::assertSame(3, $mods['versions']['players']);
        self::assertSame(2, $mods['versions']['mod_versions']['1.6.16']);
        self::assertSame(1, $mods['versions']['mod_versions']['1.5.13']);
        self::assertSame(1, $mods['versions']['link_versions']['2.0.93']);
        self::assertSame(['catalogue des mods'], $repo->missingSources());
        self::assertSame([], $pdo->errors);

        $catalogSql = implode("\n", array_filter(
            $pdo->queries,
            static fn (string $q): bool => str_contains($q, 'game_detected_mods')
        ));
        self::assertStringContainsString('ORDER BY MAX(m.last_seen) DESC', $catalogSql);
        self::assertStringNotContainsString('ORDER BY players DESC, last_seen', $catalogSql);
    }

    public function testZeroFlagIsNotTreatedAsOfficialContent(): void
    {
        $pdo = $this->pdo(false);
        $repo = new AthenaInspectorMetricsRepository($pdo);
        $mods = $repo->mods(7, 1, 'ace');

        self::assertTrue($mods['catalog_available']);
        self::assertSame([], $pdo->errors);
        self::assertSame([], $repo->missingSources());
        self::assertSame('@ace', $mods['mods'][0]['mod_dir']);
        self::assertFalse($mods['mods'][0]['is_dlc']);
        self::assertSame(3, $mods['mods'][0]['players']);
        self::assertSame(2, $mods['mods'][0]['players_recent']);
        self::assertTrue($mods['mods'][1]['is_dlc']);
        self::assertSame(2, $mods['mods_total']);

        $platform = $repo->mods(null, 24, '');
        self::assertSame(0, $platform['mods'][0]['communities']);
        self::assertStringContainsString('COUNT(DISTINCT m.tenant_id)', implode("\n", $pdo->queries));
    }

    public function testModsTabExplainsAMissingNameListWithoutErasingVersions(): void
    {
        $root = dirname(__DIR__, 2);
        $js = (string) file_get_contents($root . '/public/assets/js/athena-data-inspector.js');
        $view = (string) file_get_contents($root . '/views/atak/data_inspector.php');
        $css = (string) file_get_contents($root . '/public/assets/css/back-office-data-inspector.css');

        self::assertStringContainsString('Exporter le tableau', $view);
        self::assertStringNotContainsString('Exporter CSV', $view);
        self::assertStringContainsString('Le nom de chaque mod n’est pas disponible sur cette installation.', $js);
        self::assertStringContainsString('Déjà affiché :', $js);
        self::assertStringContainsString('Aucun mod déclaré par les joueurs.', $js);
        self::assertStringContainsString('catalog_available', $js);
        self::assertStringContainsString('is-info', $js);
        self::assertStringContainsString('.bo-di__notice.is-info', $css);
        self::assertStringContainsString('.bo-di-kpis.is-quad', $css);
    }

    private function pdo(bool $failCatalog): \PDO
    {
        return new class($failCatalog) extends \PDO {
            /** @var list<string> */
            public array $queries = [];

            /** @var list<string> */
            public array $errors = [];

            public function __construct(private bool $failCatalog)
            {
            }

            public function prepare(string $query, array $options = []): \PDOStatement|false
            {
                $this->queries[] = $query;
                if ($this->failCatalog && str_contains($query, 'game_detected_mods')) {
                    throw new \PDOException('catalogue indisponible');
                }
                $owner = $this;
                $st = new class() extends \PDOStatement {
                    public string $sql = '';

                    public ?object $owner = null;

                    public function execute(?array $params = null): bool
                    {
                        $params ??= [];
                        $n = substr_count($this->sql, '?');
                        if ($n !== count($params)) {
                            $this->owner->errors[] = $n . ' marqueurs / ' . count($params) . ' paramètres';
                        }

                        return true;
                    }

                    public function fetchAll(int $mode = \PDO::FETCH_DEFAULT, mixed ...$args): array
                    {
                        return $this->owner->rowsFor($this->sql);
                    }
                };
                $st->sql = $query;
                $st->owner = $owner;

                return $st;
            }

            public function exec(string $statement): int|false
            {
                return 0;
            }

            /** @return list<array<string, mixed>> */
            public function rowsFor(string $sql): array
            {
                if (str_contains($sql, 'game_detected_mods')) {
                    return [
                        ['mod_dir' => '@dlc', 'name' => 'Contenu officiel', 'workshop_id' => '', 'is_dlc' => '1', 'first_seen' => '2026-10-01 10:00:00', 'last_seen' => '2026-10-25 10:00:00'],
                        ['mod_dir' => '@ace', 'name' => 'ACE', 'workshop_id' => '123', 'is_dlc' => '0', 'first_seen' => '2026-10-01 10:00:00', 'last_seen' => '2026-10-25 12:00:00'],
                    ];
                }
                if (str_contains($sql, 'players_recent')) {
                    return [
                        ['mod_dir' => '@ace', 'players' => 3, 'players_recent' => 2],
                        ['mod_dir' => '@dlc', 'players' => 1, 'players_recent' => 1],
                    ];
                }
                if (str_contains($sql, 'AS lists')) {
                    return [['lists' => '4', 'avg_mods' => '23', 'max_mods' => '32']];
                }
                if (str_contains($sql, 'game_sessions') && str_contains($sql, 'mod_version')) {
                    return [
                        ['steam_id' => '1', 'mod_version' => '1.6.16', 'extension_version' => '2.0.85'],
                        ['steam_id' => '2', 'mod_version' => '1.6.16', 'extension_version' => '1.10.6'],
                        ['steam_id' => '3', 'mod_version' => '1.5.13', 'extension_version' => '2.0.93'],
                    ];
                }

                return [];
            }
        };
    }
}
