<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\SseAnalyticalRepository;
use App\Repositories\SseCaseRepository;
use App\Repositories\SsePersonRepository;
use App\Repositories\SseSiteRepository;
use App\Repositories\SseSuggestionQueueRepository;
use App\Repositories\SseWatchlistRepository;
use App\Services\Sse\SseAnalyticalEngineService;
use App\Services\Sse\SseCompletenessService;
use App\Services\Sse\SseCorrelationService;
use App\Services\Sse\SseCrossMatchService;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseTriageSqlite;

/**
 * Le passage nocturne du moteur sur 30 nuits : mêmes lacunes, mêmes dossiers. Vrai moteur, vrai dépôt de signaux (sqlite) ; les
 * lectures des dossiers, personnes, sites et de la complétude sont des doublures simples (classes étendues, aucun createMock).
 */
final class SseAnalyticalEngineSignalLifecycleTest extends TestCase
{
    private PDO $pdo;
    private string $now = '2026-10-01 02:00:00';

    /** @var array<int, array<string, mixed>> */
    private array $evals = [];

    /** @var list<array<string, mixed>> */
    private array $cases = [];

    protected function setUp(): void
    {
        $this->pdo = SseTriageSqlite::open(true);
        $this->cases = [
            ['id' => 5, 'status' => 'ouvert', 'title' => 'Réseau du port', 'summary' => '', 'reference_code' => 'DOS-0005'],
            ['id' => 6, 'status' => 'ouvert', 'title' => 'Convoi nord', 'summary' => '', 'reference_code' => 'DOS-0006'],
        ];
        $this->evals = [
            5 => $this->eval(22, ['Photographie manquante', 'Biométrie manquante', 'Appréciation analytique absente', 'Identité sans site rattaché']),
            6 => $this->eval(55, ['Photographie manquante']),
        ];
    }

    protected function tearDown(): void
    {
        SseTriageSqlite::close();
    }

    /**
     * @param list<string> $gapTitles
     * @return array<string, mixed>
     */
    private function eval(int $score, array $gapTitles): array
    {
        return [
            'score' => $score, 'complete' => false, 'done' => 0, 'total' => 0, 'steps' => [], 'digest' => ['changes' => []],
            'gaps' => array_map(static fn (string $t): array => ['kind' => 'besoin', 'title' => $t, 'body' => 'BESOIN — ' . $t, 'priority' => 'normale'], $gapTitles),
        ];
    }

    private function engine(): SseAnalyticalEngineService
    {
        $test = $this;
        $cases = new class ($test) extends SseCaseRepository {
            public function __construct(private SseAnalyticalEngineSignalLifecycleTest $t)
            {
            }

            public function listForTenant(int $tenantId, ?array $scopeIds = null, array $filters = []): array
            {
                return $this->t->casesForRun();
            }

            public function listLinkedPersonIds(int $caseId, int $tenantId): array
            {
                return [];
            }
        };
        $persons = new class extends SsePersonRepository {
            public function __construct()
            {
            }
        };
        $sites = new class extends SseSiteRepository {
            public function __construct()
            {
            }

            public function listForCase(int $caseId, int $tenantId): array
            {
                return [];
            }
        };
        $watch = new class extends SseWatchlistRepository {
            public function __construct()
            {
            }
        };
        $cross = new class extends SseCrossMatchService {
            public function __construct()
            {
            }

            public function matchPersonsAgainstWatchlist(int $tenantId, int $contextId = 1): array
            {
                return [];
            }
        };
        $analytical = new class extends SseAnalyticalRepository {
            public function __construct()
            {
            }

            public function listAssessments(int $tenantId, int $caseId): array
            {
                return [];
            }

            public function createGap(int $tenantId, int $caseId, array $data): array
            {
                return ['ok' => true];
            }
        };
        $completeness = new class ($test) extends SseCompletenessService {
            public function __construct(private SseAnalyticalEngineSignalLifecycleTest $t)
            {
            }

            public function evaluate(int $tenantId, array $case, ?array $people = null, ?array $sites = null, ?array $evidence = null): array
            {
                return $this->t->evalFor((int) $case['id']);
            }
        };
        $correlation = new class extends SseCorrelationService {
            public function __construct()
            {
            }
        };

        return new SseAnalyticalEngineService(null, $cases, $persons, $sites, $watch, $cross, $analytical, new SseSuggestionQueueRepository(null, fn (): string => $this->now), $completeness, $correlation);
    }

    /** @return list<array<string, mixed>> */
    public function casesForRun(): array
    {
        return $this->cases;
    }

    /** @return array<string, mixed> */
    public function evalFor(int $caseId): array
    {
        return $this->evals[$caseId];
    }

    private function night(int $n): array
    {
        $this->now = gmdate('Y-m-d H:i:s', strtotime('2026-10-01 02:00:00 UTC') + ($n - 1) * 86400);

        return $this->engine()->runForTenant(1, 'run-' . $n);
    }

    private function open(string $where = '1=1'): int
    {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM sse_engine_signals WHERE status = 'open' AND " . $where)->fetchColumn();
    }

    public function testThirtyNightsCreateOneSignalPerConditionNotOnePerNight(): void
    {
        for ($n = 1; $n <= 30; $n++) {
            $this->night($n);
        }

        self::assertSame(5 + 1 + 0, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_engine_signals')->fetchColumn(), 'dossier 5 : 4 lacunes + complétude ; dossier 6 : 1 lacune (avant : 30 fois plus)');
        self::assertSame(6, $this->open());
        self::assertSame(30, (int) $this->pdo->query("SELECT seen_count FROM sse_engine_signals WHERE title LIKE '%Photographie%' AND case_id = 5")->fetchColumn());
    }

    public function testAConditionThatDisappearsIsClosedAutomaticallyByCerbereDetection(): void
    {
        for ($n = 1; $n <= 5; $n++) {
            $this->night($n);
        }
        // Photo versée au dossier 5, biométrie relevée, complétude remontée au-dessus du seuil.
        $this->evals[5] = $this->eval(62, ['Appréciation analytique absente', 'Identité sans site rattaché']);
        $run = $this->night(6);

        self::assertSame(3, $run['details']['resolved'], 'photo, biométrie et complétude faible closes');
        self::assertSame(0, $this->open("title LIKE '%Photographie%' AND case_id = 5"));
        self::assertSame(1, $this->open("title LIKE '%Photographie%' AND case_id = 6"), 'l’autre dossier n’est pas touché');
        $row = $this->pdo->query("SELECT * FROM sse_engine_signals WHERE title LIKE '%Biométrie%'")->fetch(PDO::FETCH_ASSOC);
        self::assertSame('resolved', $row['status']);
        self::assertSame('cerbere-detection', $row['resolved_by']);
        self::assertSame('2026-10-06 02:00:00', $row['resolved_at']);
        self::assertSame(2, $this->open('case_id = 5'), 'les deux lacunes qui durent restent ouvertes, une ligne chacune');
    }

    public function testALowCompletenessUnchangedForThirtyDaysGoesToSleepInsteadOfStayingInTheList(): void
    {
        $last = [];
        for ($n = 1; $n <= 36; $n++) {
            $last = $this->night($n);
        }

        self::assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM sse_engine_signals WHERE status = 'dormant' AND signal_type = 'completeness'")->fetchColumn());
        self::assertSame(0, $this->open("signal_type = 'completeness'"));
        self::assertSame(5, $this->open(), 'les lacunes ne s’endorment pas : elles disparaissent quand la donnée existe');
        self::assertSame(0, $last['details']['dormant'], 'déjà en veille la veille : rien de nouveau');
    }

    public function testAnArchivedCaseClosesItsSignals(): void
    {
        $this->night(1);
        $this->night(2);
        $this->cases[1]['status'] = 'archive';
        $run = $this->night(3);

        self::assertSame(1, $run['details']['resolved']);
        self::assertSame(0, $this->open('case_id = 6'));
    }

    public function testTurningOffAutomaticResolutionKeepsTheSignalsOpen(): void
    {
        $this->night(1);
        (new \App\Repositories\SseTriageRuleRepository(null, fn (): string => $this->now))->save(1, null, ['name' => 'x', 'preset_key' => 'auto_resolve', 'enabled' => 0, 'action' => 'acknowledge'], 1);
        $this->evals[5] = $this->eval(62, []);
        $run = $this->night(2);

        self::assertSame(0, $run['details']['resolved']);
        self::assertSame(6, $this->open(), 'la communauté a coupé la règle « Résoudre automatiquement »');
    }

    public function testASummaryReportsResolvedAndDormant(): void
    {
        $run = $this->night(1);
        self::assertStringContainsString('résolus 0', $run['summary']);
        self::assertStringContainsString('en veille 0', $run['summary']);
    }
}
