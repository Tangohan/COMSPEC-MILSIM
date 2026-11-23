<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Sse\SseIdentifierGenerator;
use App\Services\Sse\SseIdentifierService;
use PDO;
use PHPUnit\Framework\TestCase;

final class SseIdentifierServiceTest extends TestCase
{
    /** @var list<array<string, mixed>> */
    private array $journal = [];

    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        foreach (SseIdentifierService::schema('sqlite') as $sql) {
            $pdo->exec($sql);
        }
        $pdo->exec('CREATE TABLE sse_persons (id INTEGER PRIMARY KEY, tenant_id INTEGER)');
        $pdo->exec('CREATE TABLE sse_field_notes (id INTEGER PRIMARY KEY, tenant_id INTEGER, title TEXT, case_id INTEGER)');
        $pdo->exec('CREATE TABLE sse_ai_profiles (id INTEGER PRIMARY KEY, tenant_id INTEGER, label TEXT, side TEXT, category TEXT, identity_id INTEGER, archived_at TEXT)');

        return $pdo;
    }

    private function service(PDO $pdo): SseIdentifierService
    {
        $this->journal = [];

        return new SseIdentifierService($pdo, function (array $e): void {
            $this->journal[] = $e;
        });
    }

    public function testAssignGivesAllIdentifiersAndIsIdempotent(): void
    {
        $s = $this->service($this->pdo());
        $a = $s->assign(7, 'person', 10);
        self::assertMatchesRegularExpression('/^ID-\d{4}-0001$/', $a['ident_no']);
        self::assertSame('Fiche personne ' . $a['ident_no'], $a['sheet_name']);
        self::assertSame(1, (int) $a['sheet_name_auto']);
        self::assertTrue(SseIdentifierGenerator::isValidDse($a['dse']));
        self::assertMatchesRegularExpression('/^[A-Z]+-\d{2,}$/', $a['codename']);
        self::assertSame($a, $s->assign(7, 'person', 10), 'Une donnée déjà pourvue ne change pas.');
        self::assertSame('ID-' . gmdate('Y') . '-0002', $s->assign(7, 'person', 11)['ident_no']);
    }

    public function testAuthorTitleIsKeptAsSheetName(): void
    {
        $s = $this->service($this->pdo());
        $row = $s->assign(7, 'field_note', 3, 'Observation du pont');
        self::assertSame('Observation du pont', $row['sheet_name']);
        self::assertSame(0, (int) $row['sheet_name_auto']);
    }

    public function testNoCollisionAndSequencesAreIndependentPerCommunity(): void
    {
        $s = $this->service($this->pdo());
        $codes = $dses = $nos = [];
        for ($i = 1; $i <= 400; $i++) {
            $r = $s->assign(7, 'person', $i);
            $codes[$r['codename']] = $dses[$r['dse']] = $nos[$r['ident_no']] = true;
        }
        self::assertCount(400, $codes);
        self::assertCount(400, $dses);
        self::assertCount(400, $nos);
        // Une autre communauté repart de 1 et peut réutiliser les mêmes formes sans conflit.
        self::assertMatchesRegularExpression('/-0001$/', $s->assign(8, 'person', 1)['ident_no']);
    }

    public function testSequenceNeverGoesBackAfterTheDataIsDeleted(): void
    {
        $pdo = $this->pdo();
        $s = $this->service($pdo);
        $first = $s->assign(7, 'person', 1);
        $pdo->exec('DELETE FROM sse_persons');
        $second = $s->assign(7, 'person', 2);
        self::assertNotSame($first['ident_no'], $second['ident_no']);
        self::assertNotSame($first['dse'], $second['dse']);
        self::assertNotNull($s->forEntity(7, 'person', 1), 'Les identifiants d\'une donnée supprimée restent réservés.');
    }

    public function testFindByDseIsScopedToTheCommunity(): void
    {
        $s = $this->service($this->pdo());
        $a = $s->assign(7, 'person', 1);
        self::assertSame((int) $a['id'], (int) $s->findByDse(7, strtolower($a['dse']))['id']);
        self::assertNull($s->findByDse(8, $a['dse']));
        self::assertNull($s->findByDse(7, 'DSE-00000'));
    }

    public function testSearchFindsByEveryIdentifier(): void
    {
        $s = $this->service($this->pdo());
        $a = $s->assign(7, 'field_note', 1, 'Convoi nord');
        foreach ([$a['ident_no'], $a['codename'], $a['dse'], substr($a['dse'], 4), 'convoi'] as $q) {
            $ids = array_column($s->search(7, $q), 'id');
            self::assertContains($a['id'], $ids, $q);
        }
        self::assertSame([], $s->search(8, $a['dse']));
        self::assertSame([], $s->search(7, '%'));
    }

    public function testRenameIsJournaledAndKeepsDseAndNumber(): void
    {
        $s = $this->service($this->pdo());
        $a = $s->assign(7, 'person', 1);
        $b = $s->assign(7, 'person', 2);
        $r = $s->rename(7, 'person', 1, 'corbeau-9', 'Fiche du pont', 5, 'Responsable');
        self::assertTrue($r['ok']);
        self::assertSame('CORBEAU-9', $r['row']['codename']);
        self::assertSame('Fiche du pont', $r['row']['sheet_name']);
        self::assertSame($a['dse'], $r['row']['dse']);
        self::assertSame($a['ident_no'], $r['row']['ident_no']);
        self::assertCount(2, $this->journal);
        // Un nom pris ou abandonné ne peut pas être repris.
        self::assertSame('codename_taken', $s->rename(7, 'person', 2, 'CORBEAU-9', null, 5, 'R')['error']);
        $s->rename(7, 'person', 1, 'PIE-3', null, 5, 'R');
        self::assertSame('codename_taken', $s->rename(7, 'person', 2, 'CORBEAU-9', null, 5, 'R')['error']);
        self::assertSame('invalid_codename', $s->rename(7, 'person', 2, 'NAZI-1', null, 5, 'R')['error']);
        self::assertSame($b['codename'], $s->forEntity(7, 'person', 2)['codename']);
    }

    public function testAiProfileLinkIsScopedToTheCommunity(): void
    {
        $pdo = $this->pdo();
        $pdo->exec("INSERT INTO sse_ai_profiles VALUES (1, 7, 'Garde', 'civ', 'civilian', NULL, NULL), (2, 8, 'Autre', 'civ', 'civilian', NULL, NULL), (3, 7, 'Archive', 'civ', 'civilian', NULL, '2026-01-01')");
        $s = $this->service($pdo);
        $s->assign(7, 'person', 1);
        self::assertTrue($s->setAiProfile(7, 'person', 1, 1, 5, 'R')['ok']);
        self::assertSame('ai_profile_not_found', $s->setAiProfile(7, 'person', 1, 2, 5, 'R')['error']);
        self::assertSame('ai_profile_not_found', $s->setAiProfile(7, 'person', 1, 3, 5, 'R')['error']);
        self::assertSame(1, (int) $s->forEntity(7, 'person', 1)['ai_profile_id']);
        self::assertNull($s->setAiProfile(7, 'person', 1, null, 5, 'R')['row']['ai_profile_id']);
    }

    public function testBackfillIsRerunnableAndNeverOverwrites(): void
    {
        $pdo = $this->pdo();
        $pdo->exec("INSERT INTO sse_persons VALUES (1, 7), (2, 7), (3, 8)");
        $pdo->exec("INSERT INTO sse_field_notes VALUES (1, 7, 'Titre existant', NULL), (2, 7, '', NULL)");
        $s = $this->service($pdo);
        $kept = $s->assign(7, 'person', 1);
        $s->rename(7, 'person', 1, 'HERON-77', null, 5, 'R');
        $kept = $s->forEntity(7, 'person', 1);

        $done = $s->backfill();
        self::assertSame(2, $done['person']);
        self::assertSame(2, $done['field_note']);
        self::assertSame(0, $done['report'], 'Table absente : ignorée.');
        self::assertSame($kept, $s->forEntity(7, 'person', 1));
        self::assertSame('Titre existant', $s->forEntity(7, 'field_note', 1)['sheet_name']);
        self::assertSame(1, (int) $s->forEntity(7, 'field_note', 2)['sheet_name_auto']);

        $before = $pdo->query('SELECT COUNT(*) FROM sse_data_identifiers')->fetchColumn();
        self::assertSame(['person' => 0, 'identity' => 0, 'field_note' => 0, 'report' => 0, 'document' => 0], $s->backfill());
        self::assertSame($before, $pdo->query('SELECT COUNT(*) FROM sse_data_identifiers')->fetchColumn());
    }
}
