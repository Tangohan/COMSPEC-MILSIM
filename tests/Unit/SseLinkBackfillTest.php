<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Sse\Links\SseLinkBackfill;
use App\Services\Sse\Links\SseLinkGraph;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\ArraySseLinkViewer;
use Tests\Support\SseLinksFixture;
use Tests\Support\SseLinksSqlite;

/**
 * Rétro-remplissage des liens de provenance (tools/sse-backfill-links.php) : simulation par défaut, écriture par lots, relance
 * sans effet, reprise par curseur, une seule table touchée, communautés séparées.
 */
final class SseLinkBackfillTest extends TestCase
{
    private PDO $pdo;

    /** @var array<string, int> */
    private array $ids = [];

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = SseLinksSqlite::open();
        $this->ids = SseLinksFixture::seed($this->pdo);
        $this->pdo->exec('DELETE FROM sse_entity_links');
    }

    protected function tearDown(): void
    {
        SseLinksSqlite::close();
    }

    private function count(string $where = '1 = 1'): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM sse_entity_links WHERE ' . $where)->fetchColumn();
    }

    /** @return array<string, int> nombre de lignes de chaque autre table, pour prouver qu'elles ne bougent pas */
    private function snapshot(): array
    {
        $out = [];
        foreach (['sse_persons', 'sse_identities', 'sse_id_documents', 'sse_id_checks', 'sse_seek_records', 'sse_wanted_notices', 'sse_doc_issues', 'sse_file_photos', 'sse_file_links', 'sse_case_persons', 'sse_cases', 'sse_field_notes', 'sse_documents'] as $t) {
            $out[$t] = (int) $this->pdo->query('SELECT COUNT(*) FROM ' . $t)->fetchColumn() . ':' . md5(json_encode($this->pdo->query('SELECT * FROM ' . $t . ' ORDER BY id')->fetchAll(PDO::FETCH_ASSOC)));
        }

        return $out;
    }

    public function testTheDefaultIsASimulationAndWritesNothing(): void
    {
        $before = $this->snapshot();
        $r = (new SseLinkBackfill($this->pdo))->run(false);
        self::assertTrue($r['ready']);
        self::assertFalse($r['apply']);
        self::assertTrue($r['done']);
        self::assertGreaterThan(30, $r['missing'], 'les liens déductibles des colonnes seraient créés');
        self::assertSame(0, $r['created']);
        self::assertSame(0, $this->count());
        self::assertSame($before, $this->snapshot());
    }

    public function testApplyRebuildsTheLinksWithTheirDateReasonAndRule(): void
    {
        $before = $this->snapshot();
        $r = (new SseLinkBackfill($this->pdo))->run(true);
        self::assertSame($r['missing'], $r['created']);
        self::assertSame(0, $r['errors']);
        self::assertSame($r['created'], $this->count());
        self::assertSame($this->count(), $this->count("rule_code = 'cerbere.backfill.column' AND created_by = 'system'"));
        // Une ligne précise : l'identité du registre rattachée à la personne, à la date de création de l'identité.
        $row = $this->pdo->query("SELECT * FROM sse_entity_links WHERE source_type = 'identity' AND source_id = '" . $this->ids['identity'] . "' AND target_type = 'person'")->fetch(PDO::FETCH_ASSOC);
        self::assertSame('attached_to', $row['relation']);
        self::assertSame('2026-11-01 10:05:00', $row['created_at']);
        self::assertStringContainsString('Lien reconstruit depuis les données existantes.', $row['reason']);
        self::assertSame('7', (string) $row['created_by_user_id']);
        // Le motif noté à l'époque (note de classement) est gardé.
        $filed = $this->pdo->query("SELECT reason FROM sse_entity_links WHERE source_type = 'person' AND target_type = 'case' AND target_id = '" . $this->ids['case'] . "'")->fetchColumn();
        self::assertStringContainsString('Classement automatique — dossier ouvert unique', (string) $filed);
        // Aucune autre donnée n'est modifiée.
        self::assertSame($before, $this->snapshot());
    }

    public function testRunningItAgainCreatesNothing(): void
    {
        $job = new SseLinkBackfill($this->pdo);
        $first = $job->run(true);
        $n = $this->count();
        $second = $job->run(true);
        self::assertSame(0, $second['created']);
        self::assertSame(0, $second['missing']);
        self::assertSame($n, $this->count());
        self::assertSame($first['created'], $second['existing']);
    }

    public function testAnExistingExplanationIsNeverReplacedNorDoubledInEitherDirection(): void
    {
        // Un contrôle déjà expliqué par Cerbère dans un sens, un autre dans l'autre sens.
        $this->pdo->exec("INSERT INTO sse_entity_links (tenant_id, source_type, source_id, target_type, target_id, relation, reason, rule_code, created_by, created_at) VALUES (1, 'check', '{$this->ids['check1']}', 'identity', '{$this->ids['identity']}', 'identified_as', 'Papier présenté.', 'cerbere.link.check_document', 'system', '2026-11-02 14:00:01')");
        $this->pdo->exec("INSERT INTO sse_entity_links (tenant_id, source_type, source_id, target_type, target_id, relation, reason, rule_code, created_by, created_at) VALUES (1, 'person', '{$this->ids['person']}', 'check', '{$this->ids['check2']}', 'related', 'Posé à la main.', 'manual.link', 'user', '2026-11-05 10:00:00')");
        (new SseLinkBackfill($this->pdo))->run(true);
        self::assertSame(1, $this->count("source_id = '{$this->ids['check1']}' AND source_type = 'check' AND target_type = 'identity'"));
        self::assertSame('cerbere.link.check_document', $this->pdo->query("SELECT rule_code FROM sse_entity_links WHERE source_type = 'check' AND source_id = '{$this->ids['check1']}' AND target_type = 'identity'")->fetchColumn());
        self::assertSame(0, $this->count("source_type = 'check' AND source_id = '{$this->ids['check2']}' AND target_type = 'person'"), 'le lien manuel inverse suffit');
        self::assertSame('manual.link', $this->pdo->query("SELECT rule_code FROM sse_entity_links WHERE source_type = 'person' AND target_type = 'check' AND target_id = '{$this->ids['check2']}'")->fetchColumn());
    }

    public function testBatchesAreBoundedAndTheRunCanBeResumedFromItsCursor(): void
    {
        $job = new SseLinkBackfill($this->pdo);
        $all = (new SseLinkBackfill($this->pdo))->run(false)['missing'];
        $p = $job->run(true, 1, 2, null, 3);
        self::assertFalse($p['done']);
        self::assertMatchesRegularExpression('/^\d+:\d+:\d+$/', (string) $p['cursor']);
        self::assertLessThanOrEqual(6, $p['created'], 'trois lots de deux lignes au plus');
        $guard = 0;
        $cursor = $p['cursor'];
        while ($cursor !== null && $guard++ < 200) {
            $step = $job->run(true, 1, 2, $cursor, 3);
            $cursor = $step['done'] ? null : $step['cursor'];
        }
        self::assertSame($all, $this->count());
        self::assertSame(0, $job->run(false)['missing']);
    }

    public function testAnotherCommunityOnlyGetsItsOwnLinks(): void
    {
        (new SseLinkBackfill($this->pdo))->run(true, 2);
        self::assertSame(0, $this->count('tenant_id = 1'));
        // La communauté 2 n'a que sa personne : aucune colonne à relier, rien d'inventé.
        self::assertSame(0, $this->count('tenant_id = 2'));
        (new SseLinkBackfill($this->pdo))->run(true, 1);
        self::assertGreaterThan(30, $this->count('tenant_id = 1'));
        self::assertSame(0, $this->count('tenant_id = 2'));
    }

    public function testTheBackfilledLinksAppearInThePathAsRecordedByCerbere(): void
    {
        (new SseLinkBackfill($this->pdo))->run(true);
        $g = (new SseLinkGraph(ArraySseLinkViewer::full()))->resolve('person', (string) $this->ids['person']);
        $e = $g['edges']['identity:' . $this->ids['identity'] . '|person:' . $this->ids['person']];
        self::assertSame('cerbere', $e['origin']);
        self::assertSame('cerbere.backfill.column', $e['rule']);
        self::assertSame('Cerbère-Liaison (règle cerbere.backfill.column)', $e['actor']);
        self::assertSame('sse_entity_links', $e['source']);
    }

    public function testWithoutTheTableItExplainsAndDoesNothing(): void
    {
        $this->pdo->exec('DROP TABLE sse_entity_links');
        $r = (new SseLinkBackfill($this->pdo))->run(true);
        self::assertFalse($r['ready']);
        self::assertSame(0, $r['created']);
        self::assertStringContainsString('étape de migration', $r['messages'][0]);
    }

    public function testTheCommandIsDryRunByDefaultAndRefusesBadOptions(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/tools/sse-backfill-links.php');
        self::assertStringContainsString("\$apply = isset(\$opts['apply']);", $src);
        self::assertStringContainsString('SIMULATION (aucune écriture)', $src);
        self::assertStringContainsString("PHP_SAPI !== 'cli'", $src);
        self::assertStringContainsString('--resume=', $src);
        self::assertStringNotContainsString('.env', $src, 'aucune lecture de fichier de secrets');
        self::assertStringNotContainsString('DELETE', $src);
        self::assertStringNotContainsString('UPDATE ', (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/Sse/Links/SseLinkBackfill.php'), 'aucune modification des autres tables');
        self::assertStringNotContainsString('DELETE ', (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/Sse/Links/SseLinkBackfill.php'));
        $php = PHP_BINARY;
        foreach (['--batch=0', '--tenant=abc', '--resume=oups'] as $bad) {
            $out = [];
            $code = 0;
            exec(escapeshellarg($php) . ' ' . escapeshellarg(dirname(__DIR__, 2) . '/tools/sse-backfill-links.php') . ' ' . $bad . ' 2>&1', $out, $code);
            self::assertSame(2, $code, $bad . ' : ' . implode(' ', $out));
        }
    }
}
