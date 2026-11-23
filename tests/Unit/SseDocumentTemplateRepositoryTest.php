<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use App\Repositories\SseDocumentTemplateRepository;
use App\Support\SseDocumentTemplates;
use App\Support\SseRichText;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\MysqlDdlSqlitePdo;

/**
 * Modèles personnels : table créée par le VRAI fichier SQL de l'étape 5060, cloisonnement par communauté, visibilité privée ou
 * partagée, suppression réservée à l'auteur, corps assaini, limite par rédacteur, mode dégradé sans la table.
 */
final class SseDocumentTemplateRepositoryTest extends TestCase
{
    private PDO $pdo;
    private mixed $saved = null;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $scratch = new MysqlDdlSqlitePdo();
        (new \App\Support\Migrations\SchemaInspector($scratch))->runSqlFile(dirname(__DIR__, 2) . '/migrations/2026_11_25_100_sse_document_templates.sql');
        foreach ($scratch->query("SELECT sql FROM sqlite_master WHERE sql IS NOT NULL AND tbl_name = 'sse_document_templates' ORDER BY type = 'index', name")->fetchAll(PDO::FETCH_COLUMN) as $sql) {
            $this->pdo->exec((string) $sql);
        }
        $prop = new \ReflectionProperty(Database::class, 'pdo');
        $this->saved = $prop->getValue();
        $prop->setValue(null, $this->pdo);
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(Database::class, 'pdo'))->setValue(null, $this->saved);
    }

    private function body(): string
    {
        return SseRichText::MARK . SseDocumentTemplates::body('note_renseignement');
    }

    public function testStepCreatesTheTableWithItsCommunityKey(): void
    {
        $cols = array_column($this->pdo->query("PRAGMA table_info('sse_document_templates')")->fetchAll(), 'name');
        foreach (['tenant_id', 'user_id', 'guest_key', 'title', 'body', 'shared', 'document_type', 'classification'] as $col) {
            self::assertContains($col, $cols, $col);
        }
    }

    public function testPrivateTemplateIsVisibleOnlyToItsAuthor(): void
    {
        $repo = new SseDocumentTemplateRepository();
        $id = $repo->create(1, 7, '', ['title' => 'Mon modèle', 'body' => $this->body(), 'document_type' => 'note_analyse', 'classification' => 'confidentiel']);
        self::assertGreaterThan(0, (int) $id);
        self::assertCount(1, $repo->listVisible(1, 7, ''));
        self::assertCount(0, $repo->listVisible(1, 8, ''), 'un autre rédacteur ne le voit pas');
        self::assertNull($repo->findVisible((int) $id, 1, 8, ''));
        self::assertNotNull($repo->findVisible((int) $id, 1, 7, ''));
        self::assertTrue($repo->listVisible(1, 7, '')[0]['mine']);
    }

    public function testSharedTemplateStaysInsideItsCommunity(): void
    {
        $repo = new SseDocumentTemplateRepository();
        $id = (int) $repo->create(1, 7, '', ['title' => 'Partagé', 'body' => $this->body(), 'shared' => true]);
        self::assertCount(1, $repo->listVisible(1, 8, ''), 'visible par la communauté');
        self::assertFalse($repo->listVisible(1, 8, '')[0]['mine']);
        self::assertCount(0, $repo->listVisible(2, 7, ''), 'jamais dans une autre communauté');
        self::assertNull($repo->findVisible($id, 2, 7, ''));
        self::assertFalse($repo->delete($id, 2, 7, ''), 'ni supprimable depuis une autre communauté');
        self::assertFalse($repo->delete($id, 1, 8, ''), 'seul l’auteur supprime');
        self::assertTrue($repo->delete($id, 1, 7, ''));
        self::assertCount(0, $repo->listVisible(1, 8, ''));
    }

    public function testGuestKeyKeepsTemplatesApartWhenThereIsNoAccount(): void
    {
        $repo = new SseDocumentTemplateRepository();
        $repo->create(1, 0, 'cle-a', ['title' => 'Invité A', 'body' => $this->body()]);
        self::assertCount(1, $repo->listVisible(1, 0, 'cle-a'));
        self::assertCount(0, $repo->listVisible(1, 0, 'cle-b'));
        self::assertCount(0, $repo->listVisible(1, 0, ''));
    }

    public function testBodyIsSanitisedAndEmptyOrNamelessTemplatesAreRefused(): void
    {
        $repo = new SseDocumentTemplateRepository();
        $id = (int) $repo->create(1, 7, '', ['title' => 'Piégé', 'body' => SseRichText::MARK . '<p onclick="x()">Texte</p><script>alert(1)</script>']);
        $row = $repo->findVisible($id, 1, 7, '');
        self::assertNotNull($row);
        self::assertStringNotContainsString('script', (string) $row['body']);
        self::assertStringNotContainsString('onclick', (string) $row['body']);
        self::assertStringContainsString('Texte', (string) $row['body']);
        self::assertNull($repo->create(1, 7, '', ['title' => '', 'body' => $this->body()]));
        self::assertNull($repo->create(1, 7, '', ['title' => 'Vide', 'body' => SseRichText::MARK]));
        self::assertNull($repo->create(1, 7, '', ['title' => 'Trop gros', 'body' => str_repeat('a', SseRichText::MAX_BYTES * 2)]));
    }

    public function testUnknownTypeAndClassificationAreNormalised(): void
    {
        $repo = new SseDocumentTemplateRepository();
        $id = (int) $repo->create(1, 7, '', ['title' => 'T', 'body' => $this->body(), 'document_type' => 'pirate', 'classification' => 'ultra']);
        $row = $repo->findVisible($id, 1, 7, '');
        self::assertSame('note_analyse', $row['document_type']);
        self::assertContains($row['classification'], ['interne', 'encadrement', 'confidentiel', 'tres_restreint']);
    }

    public function testLimitPerAuthor(): void
    {
        $repo = new SseDocumentTemplateRepository();
        for ($i = 0; $i < SseDocumentTemplateRepository::MAX_PER_USER; $i++) {
            self::assertNotNull($repo->create(1, 7, '', ['title' => 'M' . $i, 'body' => $this->body()]));
        }
        self::assertNull($repo->create(1, 7, '', ['title' => 'Une de trop', 'body' => $this->body()]));
        self::assertNotNull($repo->create(1, 8, '', ['title' => 'Autre auteur', 'body' => $this->body()]));
    }

    public function testWithoutTheTableEverythingFailsQuietly(): void
    {
        $this->pdo->exec('DROP TABLE sse_document_templates');
        $repo = new SseDocumentTemplateRepository();
        self::assertSame([], $repo->listVisible(1, 7, ''));
        self::assertNull($repo->create(1, 7, '', ['title' => 'x', 'body' => $this->body()]));
        self::assertFalse($repo->delete(1, 1, 7, ''));
    }
}
