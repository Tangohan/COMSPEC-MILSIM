<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\AthenaInspectorMetricsRepository;
use PHPUnit\Framework\TestCase;

/**
 * Toutes les vues du Data Inspector, en portée communauté et plateforme :
 * chaque requête doit recevoir exactement autant de paramètres que de marqueurs « ? »
 * et une base vide doit produire des sections vides, sans source signalée indisponible.
 */
final class AthenaInspectorMetricsRepositoryTest extends TestCase
{
    public function testEveryViewBindsMatchingParameters(): void
    {
        $pdo = new class () extends \PDO {
            /** @var list<string> */
            public array $errors = [];

            public function __construct()
            {
            }

            public function prepare(string $query, array $options = []): \PDOStatement|false
            {
                $pdo = $this;
                $st = new class () extends \PDOStatement {
                    public ?\Closure $check = null;

                    public function execute(?array $params = null): bool
                    {
                        ($this->check)($params ?? []);

                        return true;
                    }

                    public function fetchAll(int $mode = \PDO::FETCH_DEFAULT, mixed ...$args): array
                    {
                        return [];
                    }
                };
                $st->check = static function (array $params) use ($query, $pdo): void {
                    $n = substr_count($query, '?');
                    if ($n !== count($params)) {
                        $pdo->errors[] = $n . ' marqueurs / ' . count($params) . ' paramètres : ' . substr(preg_replace('/\s+/', ' ', $query) ?? '', 0, 120);
                    }
                };

                return $st;
            }

            public function exec(string $statement): int|false
            {
                return 0;
            }
        };

        $repo = new AthenaInspectorMetricsRepository($pdo);
        foreach ([7, null] as $tenant) {
            $repo->overview($tenant, 24);
            $repo->pipeline($tenant, 168, 'x');
            $repo->players($tenant, 24, '');
            $repo->mods($tenant, 24, 'ace');
            $repo->modPlayers($tenant, '@ace');
            $repo->terminals($tenant, 'atak');
            $repo->traffic($tenant, 720);
            $repo->errors($tenant, 24);
        }
        $communities = $repo->communities(24);

        self::assertSame([], $pdo->errors);
        self::assertSame([], $repo->missingSources());
        self::assertSame([], $communities);
    }
}
