<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Sse\SseSignalDeduper;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseTriageSqlite;

/**
 * Rattrapage des doublons existants (tools/sse-dedupe-signals.php) : simulation par défaut, fusion dans le plus ancien, rien de supprimé,
 * idempotent, reprenable, un groupe en échec est annulé en entier. Même forme de données que la production observée : la même lacune
 * du même dossier recréée chaque nuit.
 */
final class SseSignalDeduperTest extends TestCase
{
    private const PHOTO = 'Lacune / besoin proposé : Photographie manquante';
    private const BIO = 'Lacune / besoin proposé : Biométrie manquante';

    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = SseTriageSqlite::open(true);
        // Dossiers 5 et 6 : 12 nuits de photo et de biométrie manquantes ; dossier 5 : 12 complétudes faibles ; communauté 2 : 3 nuits.
        for ($n = 1; $n <= 12; $n++) {
            $day = sprintf('2026-10-%02d 02:00:00', $n);
            foreach ([5, 6] as $case) {
                $this->signal(1, $case, 'gap_auto', 'medium', self::PHOTO, 'auto_gap', $day);
                $this->signal(1, $case, 'gap_auto', 'medium', self::BIO, 'auto_gap', $day);
            }
            $this->signal(1, 5, 'completeness', 'medium', 'Complétude faible (' . (20 + $n) . '/100)', 'low_completeness', $day);
        }
        for ($n = 1; $n <= 3; $n++) {
            $this->signal(2, 5, 'gap_auto', 'medium', self::PHOTO, 'auto_gap', sprintf('2026-10-%02d 02:00:00', $n));
        }
    }

    protected function tearDown(): void
    {
        SseTriageSqlite::close();
    }

    private function signal(int $tenant, int $case, string $type, string $sev, string $title, string $rule, string $at): void
    {
        $this->pdo->prepare("INSERT INTO sse_engine_signals (tenant_id, case_id, signal_type, severity, title, detail, status, rule_key, run_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'détail', 'open', ?, 'r', ?, ?)")
            ->execute([$tenant, $case, $type, $sev, $title, $rule, $at, $at]);
    }

    private function count(string $where): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM sse_engine_signals WHERE ' . $where)->fetchColumn();
    }

    private function deduper(): SseSignalDeduper
    {
        return new SseSignalDeduper($this->pdo, fn (): string => '2026-11-15 10:00:00');
    }

    public function testDryRunReportsTheNoiseAndWritesNothing(): void
    {
        $before = $this->pdo->query('SELECT id, status, updated_at FROM sse_engine_signals ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $r = $this->deduper()->run(false);

        self::assertFalse($r['applied']);
        self::assertSame(12 * 5 + 3, $r['signals']);
        self::assertSame(6, $r['groups'], 'photo et biométrie des dossiers 5 et 6, complétude du 5, photo de la communauté 2');
        self::assertSame(11 * 5 + 2, $r['duplicates']);
        self::assertSame(2, $r['tenants']);
        self::assertSame(0, $r['merged']);
        self::assertSame($before, $this->pdo->query('SELECT id, status, updated_at FROM sse_engine_signals ORDER BY id')->fetchAll(PDO::FETCH_ASSOC), 'simulation : aucune écriture');
        self::assertArrayHasKey('gap_auto:Photographie manquante', $r['by_type']);
        self::assertSame(11 * 2 + 2, $r['by_type']['gap_auto:Photographie manquante']);
    }

    public function testApplyKeepsTheOldestAsSourceAndMarksTheOthersMerged(): void
    {
        $r = $this->deduper()->run(true);

        self::assertSame(11 * 5 + 2, $r['merged']);
        self::assertSame(0, $r['failed']);
        self::assertSame(6, $this->count("status = 'open'"), 'une ligne ouverte par identité');
        self::assertSame(12 * 5 + 3, $this->count('1=1'), 'rien n’est supprimé');
        $source = $this->pdo->query("SELECT * FROM sse_engine_signals WHERE case_id = 5 AND tenant_id = 1 AND title LIKE '%Photographie%' AND status = 'open'")->fetch(PDO::FETCH_ASSOC);
        self::assertSame('2026-10-01 02:00:00', $source['created_at'], 'la source est la plus ancienne');
        self::assertSame(12, (int) $source['seen_count'], 'les observations s’additionnent');
        self::assertSame('2026-10-12 02:00:00', $source['last_seen_at']);
        $merged = $this->pdo->query("SELECT * FROM sse_engine_signals WHERE status = 'merged' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        self::assertSame('cerbere-dedoublonnage', $merged['resolved_by']);
        self::assertSame('2026-11-15 10:00:00', $merged['resolved_at']);
        self::assertSame((int) $source['id'], (int) $this->pdo->query("SELECT merged_into FROM sse_engine_signals WHERE tenant_id = 1 AND case_id = 5 AND title LIKE '%Photographie%' AND status = 'merged' LIMIT 1")->fetchColumn());
    }

    public function testTheSourceReceivesTheLatestContent(): void
    {
        $this->deduper()->run(true);
        $title = (string) $this->pdo->query("SELECT title FROM sse_engine_signals WHERE signal_type = 'completeness' AND status = 'open'")->fetchColumn();

        self::assertSame('Complétude faible (32/100)', $title, 'le score de la dernière nuit');
    }

    public function testASecondRunDoesNothing(): void
    {
        $this->deduper()->run(true);
        $dump = $this->pdo->query('SELECT * FROM sse_engine_signals ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $again = $this->deduper()->run(true);

        self::assertSame(0, $again['groups']);
        self::assertSame(0, $again['merged']);
        self::assertSame($dump, $this->pdo->query('SELECT * FROM sse_engine_signals ORDER BY id')->fetchAll(PDO::FETCH_ASSOC), 'idempotent, à l’octet près');
    }

    public function testLimitProcessesAFewGroupsAndALaterRunResumes(): void
    {
        $first = $this->deduper()->run(true, null, 2);
        self::assertSame(2, $first['groups'] - $first['remaining']);
        self::assertSame(4, $first['remaining']);

        $second = $this->deduper()->run(true, null, 2);
        self::assertSame(2, $second['remaining']);
        $third = $this->deduper()->run(true);
        self::assertSame(0, $third['remaining']);
        self::assertSame(6, $this->count("status = 'open'"), 'même résultat qu’en une fois');
    }

    public function testTenantOptionTouchesOnlyThatCommunity(): void
    {
        $r = $this->deduper()->run(true, 2);

        self::assertSame(2, $r['merged']);
        self::assertSame(12 * 5, $this->count("tenant_id = 1 AND status = 'open'"), 'la communauté 1 n’est pas touchée');
    }

    public function testAGroupThatFailsIsRolledBackWholeAndTheOthersContinue(): void
    {
        // Un déclencheur refuse la fusion du dossier 6 : la transaction de ce groupe est annulée, rien n'est perdu.
        $this->pdo->exec("CREATE TRIGGER block_case6 BEFORE UPDATE ON sse_engine_signals WHEN NEW.case_id = 6 AND NEW.status = 'merged' BEGIN SELECT RAISE(ABORT, 'refusé'); END");
        $r = $this->deduper()->run(true);

        self::assertSame(2, $r['failed'], 'photo et biométrie du dossier 6');
        self::assertCount(2, $r['errors']);
        self::assertSame(12 * 2, $this->count("case_id = 6 AND status = 'open'"), 'groupe annulé en entier : ni source modifiée ni doublon fusionné');
        self::assertSame(0, $this->count("case_id = 6 AND status = 'merged'"));
        self::assertSame(12 * 5 + 3, $this->count('1=1'), 'aucune ligne perdue');
        self::assertSame(1, $this->count("tenant_id = 1 AND case_id = 5 AND title LIKE '%Photographie%' AND status = 'open'"), 'les autres groupes sont fusionnés');
        self::assertFalse($this->pdo->inTransaction());
    }

    public function testWorksWithoutTheTrackingColumnsOfStep4690(): void
    {
        SseTriageSqlite::close();
        $this->pdo = SseTriageSqlite::open(false);
        foreach ([1, 2, 3] as $n) {
            $this->signal(1, 5, 'gap_auto', 'medium', self::PHOTO, 'auto_gap', '2026-10-0' . $n . ' 02:00:00');
        }
        $r = $this->deduper()->run(true);

        self::assertFalse($r['columns']['merged_into']);
        self::assertSame(2, $r['merged']);
        self::assertSame(1, $this->count("status = 'open'"));
        self::assertSame(2, $this->count("status = 'merged'"));
    }

    public function testTheCommandLineScriptIsDryRunByDefaultAndDocumented(): void
    {
        $script = (string) file_get_contents(dirname(__DIR__, 2) . '/tools/sse-dedupe-signals.php');

        self::assertStringContainsString("getopt('', ['apply', 'limit:', 'tenant:', 'help'])", $script);
        self::assertStringContainsString('$apply = isset($opts[\'apply\']);', $script);
        self::assertStringContainsString('SIMULATION (aucune écriture)', $script);
        self::assertStringContainsString('->run($apply, $tenant, $limit)', $script);
        self::assertStringContainsString("PHP_SAPI !== 'cli'", $script);
        self::assertStringNotContainsString('DELETE', strtoupper($script), 'rien n’est supprimé');
        self::assertStringContainsString('exit($report[\'failed\'] > 0 ? 1 : 0)', $script);
    }
}
