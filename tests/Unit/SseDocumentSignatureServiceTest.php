<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Media\SignatureImageService;
use App\Services\Sse\SseDocumentSignatureService;
use App\Services\Sse\SseIdImageService;
use App\Support\SseRichText;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\MysqlDdlSqlitePdo;

/**
 * Signature au pad (SQLite, exécuté par la CI) : liée à l'utilisateur connecté, horodatée, avec l'empreinte SHA-256 du document ;
 * l'image va dans le stockage privé ; une seule signature par utilisateur ; l'empreinte détecte toute modification ; une autre
 * communauté ne lit rien ; l'action est journalisée.
 */
final class SseDocumentSignatureServiceTest extends TestCase
{
    private PDO $pdo;
    private string $dir = '';
    /** @var list<array<string,mixed>> */
    private array $audit = [];

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ssesig_' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/public', 0777, true);
        $this->pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $scratch = new MysqlDdlSqlitePdo();
        (new \App\Support\Migrations\SchemaInspector($scratch))->runSqlFile(dirname(__DIR__, 2) . '/migrations/2026_11_26_100_sse_document_pro.sql');
        foreach ($scratch->query("SELECT sql FROM sqlite_master WHERE sql IS NOT NULL AND name NOT LIKE 'sqlite_%' ORDER BY type = 'index', name")->fetchAll(PDO::FETCH_COLUMN) as $sql) {
            $this->pdo->exec((string) $sql);
        }
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->dir);
    }

    private function rrmdir(string $d): void
    {
        if ($d === '' || !is_dir($d)) {
            return;
        }
        foreach (scandir($d) ?: [] as $f) {
            if ($f !== '.' && $f !== '..') {
                $p = $d . DIRECTORY_SEPARATOR . $f;
                is_dir($p) ? $this->rrmdir($p) : @unlink($p);
            }
        }
        @rmdir($d);
    }

    private function svc(): SseDocumentSignatureService
    {
        $images = new SignatureImageService(new SseIdImageService($this->dir . '/public', false, $this->dir . '/private'));

        return new SseDocumentSignatureService($this->pdo, $images, function (array $e): void {
            $this->audit[] = $e;
        }, $this->dir . '/private');
    }

    /** @return array<string,mixed> */
    private function doc(string $body = '<p>Texte</p>'): array
    {
        return ['id' => 7, 'tenant_id' => 3, 'title' => 'Note', 'classification' => 'confidentiel', 'reference_code' => 'SSE-NR-26-0001',
            'sheet_theme' => 'classique', 'body' => SseRichText::MARK . $body];
    }

    private function drawing(): string
    {
        return SignatureImageService::PREFIX . base64_encode(SseDocumentMediaTest::png(120, 40, 200));
    }

    public function testSignatureIsLinkedToTheUserTimestampedAndHashed(): void
    {
        $doc = $this->doc();
        $r = $this->svc()->sign($doc, 3, 55, 'Sgt Test', $this->drawing());
        self::assertTrue($r['ok'], (string) ($r['error'] ?? ''));
        $rows = $this->svc()->forDocument(3, 7);
        self::assertCount(1, $rows);
        self::assertSame(55, (int) $rows[0]['user_id']);
        self::assertSame('Sgt Test', $rows[0]['signer_label']);
        self::assertSame(SseDocumentSignatureService::documentHash($doc), $rows[0]['doc_hash']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string) $rows[0]['doc_hash']);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $rows[0]['signed_at']);
        self::assertMatchesRegularExpression('~^sse-private/sse-doc-signatures/3_[a-f0-9]{16}\.png$~', (string) $rows[0]['image_path']);
        self::assertNotNull($this->svc()->imageFile($rows[0], 3));
        self::assertNull($this->svc()->imageFile($rows[0], 4), 'autre communauté');
        self::assertTrue($this->svc()->isSigned(3, 7));
        self::assertFalse($this->svc()->isSigned(3, 8));
    }

    public function testSigningIsAuditedWithTheSigner(): void
    {
        $this->svc()->sign($this->doc(), 3, 55, 'Sgt Test', $this->drawing());
        self::assertCount(1, $this->audit);
        self::assertSame('sse.document.signed', $this->audit[0]['action']);
        self::assertSame(55, $this->audit[0]['actor_user_id']);
        self::assertSame(7, $this->audit[0]['object_id']);
    }

    public function testOnlyAnAuthenticatedUserAndOnceEach(): void
    {
        $svc = $this->svc();
        self::assertFalse($svc->sign($this->doc(), 3, 0, 'Invité', $this->drawing())['ok'], 'pas de signature sans compte');
        self::assertTrue($svc->sign($this->doc(), 3, 55, 'A', $this->drawing())['ok']);
        self::assertFalse($svc->sign($this->doc(), 3, 55, 'A', $this->drawing())['ok'], 'une seule signature par utilisateur');
        self::assertTrue($svc->sign($this->doc(), 3, 56, 'B', $this->drawing())['ok'], 'un autre utilisateur signe la sienne');
        self::assertCount(2, $svc->forDocument(3, 7));
    }

    public function testCannotSignADocumentOfAnotherCommunity(): void
    {
        self::assertFalse($this->svc()->sign($this->doc(), 4, 55, 'A', $this->drawing())['ok']);
        self::assertSame([], $this->svc()->forDocument(4, 7));
    }

    public function testBadDrawingsAreRefusedAndNothingIsRecorded(): void
    {
        $svc = $this->svc();
        foreach (['', 'data:image/svg+xml;base64,' . base64_encode('<svg/>'), SignatureImageService::PREFIX . base64_encode('x')] as $bad) {
            self::assertFalse($svc->sign($this->doc(), 3, 55, 'A', $bad)['ok']);
        }
        self::assertSame([], $svc->forDocument(3, 7));
    }

    public function testHashDetectsAnyChangeAfterSigning(): void
    {
        $doc = $this->doc();
        $this->svc()->sign($doc, 3, 55, 'A', $this->drawing());
        $sigs = $this->svc()->forDocument(3, 7);
        self::assertSame([(int) $sigs[0]['id'] => true], SseDocumentSignatureService::verify($doc, $sigs));
        foreach ([$this->doc('<p>Texte modifié</p>'), ['title' => 'Autre'] + $doc, ['classification' => 'interne'] + $doc, ['reference_code' => 'X'] + $doc] as $changed) {
            self::assertSame([(int) $sigs[0]['id'] => false], SseDocumentSignatureService::verify($changed, $sigs));
        }
    }
}
