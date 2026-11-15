<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\SseEntityLinkRepository;
use App\Repositories\SseIdCheckRepository;
use App\Repositories\SseIdDocumentRepository;
use App\Repositories\SsePersonRepository;
use App\Services\Sse\Links\SseLinkGraph;
use App\Services\Sse\SseIdCheckService;
use App\Services\Sse\SseIdSubjectMatchService;
use App\Services\Sse\SseProvenance;
use App\Services\Sse\SseWantedDetectionService;
use App\Support\SseCerbere;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\ArraySseLinkViewer;
use Tests\Support\SseLinksFixture;
use Tests\Support\SseLinksSqlite;

/**
 * Provenance enregistrée : ce que le système relie ou crée tout seul laisse son POURQUOI (relation, motif, règle Cerbère) dans
 * sse_entity_links, sans jamais bloquer le traitement ni modifier autre chose.
 */
final class SseProvenanceTest extends TestCase
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
        $this->pdo->exec('DELETE FROM sse_id_checks');
    }

    protected function tearDown(): void
    {
        SseLinksSqlite::close();
    }

    /** @return list<array<string, mixed>> */
    private function rows(string $where = '1 = 1'): array
    {
        return $this->pdo->query('SELECT * FROM sse_entity_links WHERE ' . $where . ' ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function testACerbereLinkKeepsItsRuleItsReasonAndAnAutomaticAuthor(): void
    {
        $id = SseProvenance::cerbere(1, ['check', 5], ['identity', $this->ids['identity']], 'identified_as', 'cerbere.link.check_biokey', 'Clé FP-1.');
        self::assertGreaterThan(0, $id);
        $row = $this->rows()[0];
        self::assertSame('check', $row['source_type']);
        self::assertSame('5', $row['source_id']);
        self::assertSame('cerbere.link.check_biokey', $row['rule_code']);
        self::assertSame('system', $row['created_by']);
        self::assertSame('Contrôle rattaché par la clé biométrique déjà connue. Clé FP-1.', $row['reason']);
    }

    public function testRecordingTwiceOrInTheOtherDirectionNeverDoublesTheLink(): void
    {
        self::assertGreaterThan(0, SseProvenance::cerbere(1, ['check', 5], ['identity', 1], 'identified_as', 'cerbere.link.check_document'));
        self::assertSame(0, SseProvenance::cerbere(1, ['check', 5], ['identity', 1], 'identified_as', 'cerbere.link.check_biokey'));
        self::assertSame(0, SseProvenance::cerbere(1, ['identity', 1], ['check', 5], 'identified_as', 'cerbere.link.check_biokey'));
        self::assertCount(1, $this->rows());
        self::assertSame('cerbere.link.check_document', $this->rows()[0]['rule_code'], 'la première explication est gardée');
    }

    public function testTheUniqueKeyIsInTheTableItselfForSqliteAndMysql(): void
    {
        $sql = (string) file_get_contents(SseLinksSqlite::sqlFile());
        self::assertStringContainsString('UNIQUE KEY uq_sse_entity_link (tenant_id, source_type, source_id, target_type, target_id)', $sql);
        self::assertStringNotContainsString('ALTER TABLE', $sql, 'unicité dans le CREATE TABLE, jamais ALTER … ADD UNIQUE KEY');
        $this->pdo->exec("INSERT INTO sse_entity_links (tenant_id, source_type, source_id, target_type, target_id, relation, created_by, created_at) VALUES (1, 'a', '1', 'b', '2', 'related', 'user', '2026-01-01 00:00:00')");
        $this->expectDuplicateInsertToFail();
    }

    private function expectDuplicateInsertToFail(): void
    {
        $failed = false;
        try {
            $this->pdo->exec("INSERT INTO sse_entity_links (tenant_id, source_type, source_id, target_type, target_id, relation, created_by, created_at) VALUES (1, 'a', '1', 'b', '2', 'related', 'system', '2026-01-02 00:00:00')");
        } catch (\Throwable) {
            $failed = true;
        }
        self::assertTrue($failed);
    }

    public function testWithoutTheTableNothingIsWrittenAndNothingBreaks(): void
    {
        $this->pdo->exec('DROP TABLE sse_entity_links');
        SseEntityLinkRepository::forgetReady();
        self::assertFalse(SseEntityLinkRepository::ready());
        self::assertSame(0, SseProvenance::cerbere(1, ['check', 5], ['identity', 1], 'identified_as', 'cerbere.link.check_biokey'));
        self::assertSame(0, SseProvenance::manual(1, ['check', 5], ['identity', 1], 'related', 'Motif écrit.', 7));
    }

    public function testInvalidLinksAreRefusedWithoutAnError(): void
    {
        self::assertSame(0, SseProvenance::cerbere(0, ['check', 5], ['identity', 1], 'identified_as', 'cerbere.link.check_biokey'));
        self::assertSame(0, SseProvenance::cerbere(1, ['check', 5], ['check', 5], 'related', 'cerbere.link.check_biokey'), 'un élément n’est pas lié à lui-même');
        self::assertSame(0, SseProvenance::cerbere(1, ['inconnu', 5], ['identity', 1], 'related', 'cerbere.link.check_biokey'));
        self::assertSame([], $this->rows());
    }

    public function testAControlStoredFromTheFieldExplainsEveryLinkItGot(): void
    {
        $paper = $this->ids['paper1'];
        $this->pdo->exec("UPDATE sse_id_documents SET workflow = 'issued' WHERE id = " . $paper);
        $service = new SseIdCheckService(new SseIdCheckRepository(), new SseIdDocumentRepository(), new SsePersonRepository());
        $stored = $service->store(1, 1, [
            'idempotency_key' => 'trace-check-0001',
            'document' => ['id' => $paper, 'type' => 'passport', 'country' => 'TAK', 'number' => 'TK1234567'],
            'presented_name' => ['last' => 'Karimzadeh', 'first' => 'Élodie'],
            'subject' => ['person_id' => $this->ids['person'], 'bio_key' => 'FP-77'],
            'controller' => ['callsign' => 'ALPHA-2'],
            'verdict' => 'ras',
        ]);
        self::assertFalse($stored['duplicate']);
        $checkId = (int) $stored['id'];
        $byTarget = [];
        foreach ($this->rows("source_type = 'check' AND source_id = '" . $checkId . "'") as $r) {
            $byTarget[$r['target_type']] = $r;
        }
        self::assertSame('cerbere.link.check_document', $byTarget['paper']['rule_code'], 'papier présenté');
        self::assertSame('cerbere.link.check_document', $byTarget['identity']['rule_code'], 'identité du papier présenté');
        self::assertSame('cerbere.link.check_designated', $byTarget['person']['rule_code'], 'fiche désignée par le terminal');
        foreach ($byTarget as $r) {
            self::assertSame('system', $r['created_by']);
            self::assertNotSame('', $r['reason']);
        }
        // Rejouer le même envoi ne crée aucun lien de plus.
        $service->store(1, 1, ['idempotency_key' => 'trace-check-0001', 'document' => ['id' => $paper]]);
        self::assertCount(3, $this->rows("source_type = 'check'"));
    }

    public function testAControlLinkedByTheBiometricKeyAloneSaysSo(): void
    {
        $this->pdo->exec("UPDATE sse_identities SET bio_key = 'FP-9' WHERE id = " . $this->ids['identity']);
        $service = new SseIdCheckService(new SseIdCheckRepository(), new SseIdDocumentRepository(), new SsePersonRepository());
        $stored = $service->store(1, 1, [
            'idempotency_key' => 'trace-check-0002',
            'document' => ['type' => 'passport', 'country' => 'TAK', 'number' => 'INCONNU99'],
            'presented_name' => ['last' => 'Karimzadeh'],
            'subject' => ['bio_key' => 'FP-9'],
            'controller' => ['callsign' => 'ALPHA-2'],
        ]);
        $rows = $this->rows("source_type = 'check' AND source_id = '" . (int) $stored['id'] . "'");
        $byTarget = array_column($rows, null, 'target_type');
        self::assertSame('cerbere.link.check_biokey', $byTarget['identity']['rule_code']);
        self::assertArrayNotHasKey('paper', $byTarget, 'aucun papier du registre : aucun lien inventé');
    }

    public function testEarlierChecksAttachedLaterByTheSameKeyAreTracedAsRetroactive(): void
    {
        $this->pdo->exec("UPDATE sse_persons SET bio_key = NULL WHERE id = " . $this->ids['person']);
        foreach ([1, 2] as $n) {
            $this->pdo->prepare("INSERT INTO sse_id_checks (tenant_id, context_id, idempotency_key, subject_bio_key, created_at) VALUES (1, 1, ?, 'FP-RETRO', '2026-11-0{$n} 10:00:00')")->execute(['retro-' . $n]);
        }
        (new SseIdSubjectMatchService())->remember(1, 1, 'FP-RETRO', $this->ids['person'], null, null);
        $rows = $this->rows("rule_code = 'cerbere.link.check_retro'");
        self::assertCount(2, $rows);
        foreach ($rows as $r) {
            self::assertSame('person', $r['target_type']);
            self::assertSame((string) $this->ids['person'], $r['target_id']);
        }
        $attached = (int) $this->pdo->query("SELECT COUNT(*) FROM sse_id_checks WHERE subject_bio_key = 'FP-RETRO' AND person_id = " . $this->ids['person'])->fetchColumn();
        self::assertSame(2, $attached, 'le rattachement lui-même est inchangé : seule la trace est ajoutée');
    }

    public function testAWantedDetectionLinksTheNoticeToTheCheckThatRaisedIt(): void
    {
        $this->pdo->exec("INSERT INTO sse_id_checks (id, tenant_id, context_id, idempotency_key, identity_id, created_at) VALUES (42, 1, 1, 'det-1', " . $this->ids['identity'] . ", '2026-11-10 10:00:00')");
        $service = new SseWantedDetectionService(null, null, null, static function (): void {
        }, static fn (): array => []);
        $r = $service->report(1, $this->ids['identity'], 'id_check', ['source' => 'game', 'source_ref' => 'CTRL-42', 'operator_label' => 'ALPHA-2']);
        self::assertTrue($r['created']);
        $rows = $this->rows("rule_code = 'cerbere.detect.wanted'");
        self::assertCount(1, $rows);
        self::assertSame('notice', $rows[0]['source_type']);
        self::assertSame((string) $this->ids['notice'], $rows[0]['source_id']);
        self::assertSame('check', $rows[0]['target_type']);
        self::assertSame('42', $rows[0]['target_id']);
        self::assertSame('detected_on', $rows[0]['relation']);
        self::assertStringContainsString('Identité sous avis de recherche actif détectée.', $rows[0]['reason']);
    }

    public function testTheTraceAppearsInThePathWithTheCerbereNameAndTheRule(): void
    {
        $this->pdo->exec("INSERT INTO sse_id_checks (id, tenant_id, context_id, idempotency_key, created_at) VALUES (60, 1, 1, 'path-1', '2026-11-10 10:00:00')");
        SseProvenance::cerbere(1, ['check', 60], ['identity', $this->ids['identity']], 'identified_as', 'cerbere.link.check_document');
        $g = (new SseLinkGraph(ArraySseLinkViewer::full()))->resolve('check', '60');
        $e = $g['edges']['check:60|identity:' . $this->ids['identity']];
        self::assertSame('Cerbère-Liaison (règle cerbere.link.check_document)', $e['actor']);
        self::assertSame('Cerbère-Liaison', $e['treatment']);
        self::assertSame('cerbere', $e['origin']);
    }

    public function testEveryHookedMechanismNamesItsRuleInItsSource(): void
    {
        $root = dirname(__DIR__, 2) . '/app/';
        $wiring = [
            'Controllers/Api/SseApiController.php' => ['cerbere.create.case_from_code', 'cerbere.link.case_terminal'],
            'Services/Sse/SseAutomationService.php' => ['cerbere.link.case_single_open', 'cerbere.create.case_daily', 'cerbere.link.case_daily', 'cerbere.correlate.duplicate_biometric', 'cerbere.correlate.co_presence'],
            'Services/Sse/SseIdCheckService.php' => ['cerbere.link.check_document', 'cerbere.link.check_biokey', 'cerbere.link.check_designated', 'cerbere.link.check_netid'],
            'Services/Sse/SseIdSubjectMatchService.php' => ['cerbere.link.check_retro'],
            'Services/Sse/SseWantedDetectionService.php' => ['cerbere.detect.wanted'],
            'Services/Sse/SseAnalyticalEngineService.php' => ['cerbere.validate.engine_suggestion'],
        ];
        foreach ($wiring as $file => $rules) {
            $src = (string) file_get_contents($root . $file);
            self::assertStringContainsString('SseProvenance::cerbere', $src, $file);
            foreach ($rules as $rule) {
                self::assertStringContainsString("'" . $rule . "'", $src, $file . ' ' . $rule);
                self::assertArrayHasKey($rule, SseCerbere::RULES, 'règle déclarée dans le catalogue : ' . $rule);
            }
        }
    }
}
