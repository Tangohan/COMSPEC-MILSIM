<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Admin\PlayerTracePurgeService;
use PHPUnit\Framework\TestCase;

/**
 * Plan de purge d'un joueur : chaque étape reçoit autant de paramètres que de marqueurs,
 * la portée communauté filtre bien sur tenant_id, et l'historique opérationnel est conservé.
 */
final class PlayerTracePurgeServiceTest extends TestCase
{
    private function fakePdo(): \PDO
    {
        return new class () extends \PDO {
            public function __construct()
            {
            }

            public function prepare(string $query, array $options = []): \PDOStatement|false
            {
                $st = new class () extends \PDOStatement {
                    public string $q = '';

                    public function execute(?array $params = null): bool
                    {
                        return true;
                    }

                    public function fetchColumn(int $column = 0): mixed
                    {
                        return str_contains($this->q, 'information_schema.TABLES') ? 1 : false;
                    }

                    public function fetchAll(int $mode = \PDO::FETCH_DEFAULT, mixed ...$args): array
                    {
                        return $mode === \PDO::FETCH_COLUMN
                            ? ['id', 'tenant_id', 'user_id', 'steam_id', 'steam_uid', 'call_sign', 'submitter_user_id', 'author_user_id', 'extra']
                            : [];
                    }
                };
                $st->q = $query;

                return $st;
            }

            public function query(string $query, ?int $fetchMode = null, mixed ...$args): \PDOStatement|false
            {
                return $this->prepare($query);
            }

            public function exec(string $statement): int|false
            {
                return 0;
            }
        };
    }

    public function testPlanBindsParametersAndKeepsHistory(): void
    {
        $svc = new PlayerTracePurgeService($this->fakePdo());
        $id = ['user_ids' => [5], 'steams' => ['76561198000000001'], 'callsigns' => ['Viper'], 'tenants' => [1, 2]];

        foreach ([null, 3] as $scope) {
            foreach ($svc->plan($id, $scope) as $step) {
                $marks = substr_count((string) preg_replace("/'[^']*'/", "''", $step['where']), '?');
                self::assertSame($marks, count($step['params']), $step['table'] . ' : ' . $step['where']);
                if ($scope !== null && !in_array($step['table'], ['game_device_pairings', 'game_atak_pair_challenges', 'atak_mod_reports'], true)) {
                    self::assertStringContainsString('tenant_id', $step['where'], $step['table']);
                }
            }
        }

        $plan = $svc->plan($id, null);
        $tables = array_column($plan, 'table');
        self::assertContains('atak_units', $tables);
        self::assertContains('operator_game_profiles', $tables);
        self::assertContains('game_player_mods', $tables);
        $kept = array_column(array_filter($plan, static fn ($s) => $s['mode'] === 'keep'), 'table');
        self::assertContains('atak_tactical_reports', $kept);
        self::assertNotContains('athena_accounts', array_column($svc->plan($id, 3), 'table'));
    }

    public function testCleanHelpers(): void
    {
        self::assertSame(['76561198000000001'], PlayerTracePurgeService::cleanSteams(['76561198000000001', '', 'x', '76561198000000001']));
        self::assertSame(['Viper', 'Hunter'], PlayerTracePurgeService::cleanStrings([' Viper ', 'Hunter', '', 'Viper']));
    }
}
