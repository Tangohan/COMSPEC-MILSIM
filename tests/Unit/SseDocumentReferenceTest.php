<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Sse\SseDocumentReference;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\MysqlDdlSqlitePdo;

/**
 * Référence automatique SSE-NR-26-0042 : format par type de document, séquence par communauté, préfixe et année, aucun doublon,
 * table créée par le VRAI fichier SQL de l'étape 5070 (SQLite, exécuté par la CI).
 */
final class SseDocumentReferenceTest extends TestCase
{
    private PDO $pdo;

    private function db(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $scratch = new MysqlDdlSqlitePdo();
        (new \App\Support\Migrations\SchemaInspector($scratch))->runSqlFile(dirname(__DIR__, 2) . '/migrations/2026_11_26_100_sse_document_pro.sql');
        foreach ($scratch->query("SELECT sql FROM sqlite_master WHERE sql IS NOT NULL AND name NOT LIKE 'sqlite_%' ORDER BY type = 'index', name")->fetchAll(PDO::FETCH_COLUMN) as $sql) {
            $this->pdo->exec((string) $sql);
        }
    }

    public function testFormatIsPrefixYearSequence(): void
    {
        self::assertSame('SSE-NR-26-0042', SseDocumentReference::render('note_analyse', 2026, 42));
        self::assertSame('SSE-FL-27-0001', SseDocumentReference::render('flash', 2027, 1));
        self::assertSame('SSE-NR-26-12345', SseDocumentReference::render('inconnu', 2026, 12345), 'type inconnu : préfixe des notes, séquence jamais tronquée');
    }

    public function testSequenceIsPerCommunityPrefixAndYear(): void
    {
        $this->db();
        $ref = new SseDocumentReference($this->pdo);
        self::assertSame('SSE-NR-26-0001', $ref->next(1, 'note_analyse', 2026));
        self::assertSame('SSE-NR-26-0002', $ref->next(1, 'note_analyse', 2026));
        self::assertSame('SSE-NR-26-0001', $ref->next(2, 'note_analyse', 2026), 'autre communauté');
        self::assertSame('SSE-FL-26-0001', $ref->next(1, 'flash', 2026), 'autre type');
        self::assertSame('SSE-NR-27-0001', $ref->next(1, 'note_analyse', 2027), 'autre année');
        self::assertSame('SSE-NR-26-0003', $ref->next(1, 'note_analyse', 2026));
    }

    public function testNoDuplicateAcrossManyAttributionsAndTwoInstances(): void
    {
        $this->db();
        $a = new SseDocumentReference($this->pdo);
        $b = new SseDocumentReference($this->pdo);
        $seen = [];
        for ($i = 0; $i < 60; $i++) {
            $r = ($i % 2 === 0 ? $a : $b)->next(7, 'synthese', 2026);
            self::assertArrayNotHasKey($r, $seen, $r);
            $seen[$r] = true;
        }
        self::assertSame(60, count($seen));
        self::assertSame(60, (int) $this->pdo->query('SELECT last_seq FROM sse_document_counters WHERE tenant_id = 7')->fetchColumn());
    }

    public function testFailedAttributionLeavesNoGap(): void
    {
        $this->db();
        $ref = new SseDocumentReference($this->pdo);
        $ref->next(1, 'diffusion', 2026);
        $this->pdo->beginTransaction();
        $inner = new SseDocumentReference($this->pdo);
        $inner->next(1, 'diffusion', 2026);
        $this->pdo->rollBack();
        self::assertSame('SSE-DF-26-0002', $ref->next(1, 'diffusion', 2026), 'une transaction annulée ne consomme pas de numéro');
    }
}
