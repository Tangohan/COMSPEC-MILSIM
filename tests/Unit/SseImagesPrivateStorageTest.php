<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\PhotoLibrary\PhotoSseTransfer;
use App\Services\Sse\SseIdImageService;
use App\Services\Sse\SseImageMigration;
use App\Support\SseImageStorage;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Images SSE sensibles hors du dossier public : nouveaux envois en espace privé, lecture des deux emplacements pendant la
 * migration, traversée de chemin refusée, et script de déplacement des fichiers existants (SQLite + dossiers temporaires,
 * fichiers factices : jamais de vrais visages).
 */
final class SseImagesPrivateStorageTest extends TestCase
{
    private string $root = '';
    private string $pub = '';
    private string $priv = '';

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sseprv_' . bin2hex(random_bytes(4));
        $this->pub = $this->root . '/public';
        $this->priv = $this->root . '/storage/private/sse-images';
        mkdir($this->pub . '/uploads', 0777, true);
        mkdir($this->priv, 0777, true);
    }

    protected function tearDown(): void
    {
        if ($this->root === '' || !is_dir($this->root)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($this->root);
    }

    /** PNG 1×1 factice valide (aucune photo réelle). */
    private static function png(string $marker = ''): string
    {
        $raw = "\x89PNG\r\n\x1a\n" . pack('N', 13) . 'IHDR' . pack('NNCCCCC', 1, 1, 8, 2, 0, 0, 0) . pack('N', 0);
        $idat = "\x78\x9c\x63\x60\x60\x60\x00\x00\x00\x04\x00\x01";

        return $raw . pack('N', strlen($idat)) . 'IDAT' . $idat . pack('N', 0) . pack('N', strlen($marker)) . 'tEXt' . $marker . pack('N', 0) . pack('N', 0) . 'IEND' . pack('N', 0);
    }

    private function src(string $bytes): string
    {
        $p = $this->root . '/' . bin2hex(random_bytes(4)) . '.bin';
        file_put_contents($p, $bytes);

        return $p;
    }

    private function service(): SseIdImageService
    {
        return new SseIdImageService($this->pub, false, $this->priv);
    }

    /** @return list<string> tous les fichiers sous un dossier */
    private function filesUnder(string $dir): array
    {
        $out = [];
        if (!is_dir($dir)) {
            return $out;
        }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $f) {
            $out[] = str_replace('\\', '/', $f->getPathname());
        }

        return $out;
    }

    // ------------------------------------------------------------------ nouveaux envois

    public function testNewUploadsLandInThePrivateSpaceAndNeverUnderPublic(): void
    {
        $svc = $this->service();
        foreach (['photo' => 'sse-id-photos', 'signature' => 'sse-id-signatures', 'background' => 'sse-id-backgrounds'] as $kind => $folder) {
            $res = $svc->storeFile($this->src(self::png()), $kind, 7);
            self::assertTrue($res['ok'], (string) ($res['error'] ?? ''));
            self::assertMatchesRegularExpression('#^sse-private/' . $folder . '/7_[a-f0-9]{16}\.png$#', (string) $res['path']);
            self::assertTrue(SseImageStorage::isPrivatePath((string) $res['path']));
            self::assertFileExists($this->priv . '/' . $folder . '/' . basename((string) $res['path']));
            self::assertNotNull(SseImageStorage::absolute((string) $res['path'], $this->pub, $this->priv));
        }
        self::assertSame([], $this->filesUnder($this->pub), 'aucune image sensible sous public/');
    }

    public function testCountryFlagsStayPublicBecauseTheyAreNotSensitive(): void
    {
        $res = $this->service()->storeFile($this->src(self::png()), 'flag', 7);
        self::assertTrue($res['ok']);
        self::assertStringStartsWith('uploads/sse-flags/7_', (string) $res['path']);
        self::assertCount(1, $this->filesUnder($this->pub . '/uploads/sse-flags'));
        self::assertSame([], $this->filesUnder($this->priv));
    }

    public function testNoCallerWritesSensibleImagesUnderPublicAnymore(): void
    {
        $root = dirname(__DIR__, 2);
        foreach (['app/Services/Sse/SseIdImageService.php', 'app/Services/PhotoLibrary/PhotoSseTransfer.php'] as $file) {
            $code = (string) file_get_contents($root . '/' . $file);
            self::assertStringNotContainsString("'/uploads/sse-id-photos'", $code, $file);
            self::assertDoesNotMatchRegularExpression("#return 'uploads/sse-id-(photos|signatures|backgrounds)/#", $code, $file);
        }
        $svc = (string) file_get_contents($root . '/app/Services/Sse/SseIdImageService.php');
        self::assertMatchesRegularExpression("#'photo' => \\['sse-id-photos', [^\\]]*true\\]#", $svc);
        self::assertMatchesRegularExpression("#'signature' => \\['sse-id-signatures', [^\\]]*true\\]#", $svc);
        self::assertMatchesRegularExpression("#'background' => \\['sse-id-backgrounds', [^\\]]*true\\]#", $svc);
    }

    public function testPhotoLibraryCopyIsStoredPrivately(): void
    {
        $t = new PhotoSseTransfer(null, null, null, null, $this->pub, $this->priv);
        $m = new \ReflectionMethod($t, 'storeCopy');
        $path = $m->invoke($t, 4, self::png(), 'image/png');
        self::assertMatchesRegularExpression('#^sse-private/sse-id-photos/4_[a-f0-9]{16}\.png$#', (string) $path);
        self::assertSame([], $this->filesUnder($this->pub), 'rien sous public/');
        self::assertNotNull(SseImageStorage::absolute((string) $path, $this->pub, $this->priv));
    }

    // ------------------------------------------------------------------ lecture : deux emplacements, aucune traversée

    public function testReadersAcceptThePrivateAndTheLegacyLocations(): void
    {
        mkdir($this->pub . '/uploads/sse-id-photos', 0777, true);
        file_put_contents($this->pub . '/uploads/sse-id-photos/3_0123456789abcdef.png', self::png());
        $new = $this->service()->storeFile($this->src(self::png()), 'photo', 3);

        $legacy = SseImageStorage::absolute('uploads/sse-id-photos/3_0123456789abcdef.png', $this->pub, $this->priv);
        $private = SseImageStorage::absolute((string) $new['path'], $this->pub, $this->priv);
        self::assertNotNull($legacy);
        self::assertNotNull($private);
        self::assertStringContainsString('/public/uploads/', str_replace('\\', '/', (string) $legacy));
        self::assertStringContainsString('/storage/private/sse-images/', str_replace('\\', '/', (string) $private));
        self::assertNotNull(SseImageStorage::readable('uploads/sse-id-photos/3_0123456789abcdef.png', $this->pub, $this->priv));
        self::assertNotNull(SseImageStorage::readable((string) $new['path'], $this->pub, $this->priv));
        self::assertStringStartsWith('data:image/png;base64,', (string) SseImageStorage::dataUri((string) $new['path'], $this->pub, $this->priv));
    }

    public function testPathTraversalAndForeignPathsAreRefused(): void
    {
        file_put_contents($this->root . '/secret.png', self::png());
        mkdir($this->pub . '/uploads/sse-id-photos', 0777, true);
        mkdir($this->priv . '/sse-id-photos', 0777, true);
        file_put_contents($this->pub . '/uploads/other.png', self::png());
        foreach ([
            'sse-private/sse-id-photos/../../../../secret.png',
            'sse-private/sse-id-photos/..%2F..%2Fsecret.png',
            'uploads/sse-id-photos/../../../secret.png',
            'uploads/sse-id-photos/3_0123456789abcdef.png/../../other.png',
            'sse-private/sse-id-photos/3_0123456789abcdef.png' . "\0.php",
            '/etc/passwd',
            'C:\\Windows\\win.ini',
            'sse-private/other-folder/3_0123456789abcdef.png',
            'sse-private/sse-id-photos/x.php',
            'sse-private/sse-id-photos/3_0123456789ABCDEF.png',
            '',
        ] as $bad) {
            self::assertNull(SseImageStorage::absolute($bad, $this->pub, $this->priv), $bad);
            self::assertFalse(SseImageStorage::isStoredPath($bad), $bad);
        }
        self::assertNull(SseImageStorage::readable('uploads/../../secret.png', $this->pub, $this->priv));
        self::assertNull(SseImageStorage::dataUri('sse-private/sse-id-photos/../../../../secret.png', $this->pub, $this->priv));
    }

    public function testVerifiedMimeReadsTheContentNeverTheExtension(): void
    {
        $fake = $this->root . '/fake.png';
        file_put_contents($fake, "<?php echo 'x';");
        self::assertNull(SseImageStorage::verifiedMime($fake));
        $real = $this->root . '/real.jpg';
        file_put_contents($real, self::png());
        self::assertSame('image/png', SseImageStorage::verifiedMime($real), 'le contenu prime sur l’extension');
        self::assertNull(SseImageStorage::dataUri('sse-private/sse-id-photos/3_0123456789abcdef.png', $this->pub, $this->priv));
    }

    public function testExternalPackNeverReceivesTheInternalPathOfASensibleImage(): void
    {
        self::assertNull(SseImageStorage::publicOnly('sse-private/sse-id-photos/3_0123456789abcdef.png'));
        self::assertNull(SseImageStorage::publicOnly('uploads/sse-id-photos/3_0123456789abcdef.png'));
        self::assertSame('uploads/sse-flags/3_0123456789abcdef.png', SseImageStorage::publicOnly('uploads/sse-flags/3_0123456789abcdef.png'));
        self::assertNull(SseImageStorage::publicOnly(null));
    }

    // ------------------------------------------------------------------ script de migration

    private function db(): PDO
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE sse_identities (id INTEGER PRIMARY KEY, tenant_id INT, photo_path TEXT, signature_path TEXT)');
        $pdo->exec('CREATE TABLE sse_id_documents (id INTEGER PRIMARY KEY, tenant_id INT, background_path TEXT)');
        $pdo->exec('CREATE TABLE sse_file_photos (id INTEGER PRIMARY KEY, tenant_id INT, path TEXT)');

        return $pdo;
    }

    /** Fichier hérité factice + ligne en base ; retourne son chemin relatif. */
    private function legacy(PDO $pdo, string $table, string $col, string $folder, int $tenant, string $hex, string $marker): string
    {
        $dir = $this->pub . '/uploads/' . $folder;
        @mkdir($dir, 0777, true);
        $rel = 'uploads/' . $folder . '/' . $tenant . '_' . $hex . '.png';
        file_put_contents($this->pub . '/' . $rel, self::png($marker));
        $pdo->prepare('INSERT INTO ' . $table . ' (tenant_id, ' . $col . ') VALUES (?, ?)')->execute([$tenant, $rel]);

        return $rel;
    }

    private function migration(PDO $pdo, ?string $log = null): SseImageMigration
    {
        return new SseImageMigration($pdo, $this->pub, $this->priv, $log);
    }

    public function testDryRunListsEverythingAndWritesNothing(): void
    {
        $pdo = $this->db();
        $a = $this->legacy($pdo, 'sse_identities', 'photo_path', 'sse-id-photos', 1, '0000000000000001', 'A');
        $this->legacy($pdo, 'sse_identities', 'signature_path', 'sse-id-signatures', 1, '0000000000000002', 'B');
        $this->legacy($pdo, 'sse_id_documents', 'background_path', 'sse-id-backgrounds', 2, '0000000000000003', 'C');
        $pdo->prepare('INSERT INTO sse_file_photos (tenant_id, path) VALUES (1, ?)')->execute(['sse-seek/1/aaaaaaaaaaaaaaaa.jpg']);
        $pdo->prepare('INSERT INTO sse_file_photos (tenant_id, path) VALUES (1, ?)')->execute(['uploads/sse/evidence/ev_1_x.png']);
        $log = $this->root . '/journal.jsonl';

        $r = $this->migration($pdo, $log)->run(false);

        self::assertFalse($r['apply']);
        self::assertSame(3, $r['files']);
        self::assertGreaterThan(0, $r['bytes']);
        self::assertSame(0, $r['moved']);
        self::assertSame(0, $r['errors']);
        self::assertFileExists($this->pub . '/' . $a, 'rien déplacé');
        self::assertSame([], $this->filesUnder($this->priv), 'rien copié');
        self::assertFileDoesNotExist($log, 'pas de journal en simulation');
        self::assertSame($a, $pdo->query('SELECT photo_path FROM sse_identities WHERE id = 1')->fetchColumn(), 'base inchangée');
        self::assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM sse_file_photos WHERE path LIKE 'uploads/sse/%'")->fetchColumn());
    }

    public function testApplyMovesVerifiesUpdatesTheDatabaseThenDeletesThePublicCopy(): void
    {
        $pdo = $this->db();
        $a = $this->legacy($pdo, 'sse_identities', 'photo_path', 'sse-id-photos', 1, '0000000000000001', 'A');
        $b = $this->legacy($pdo, 'sse_id_documents', 'background_path', 'sse-id-backgrounds', 2, '0000000000000003', 'C');
        $before = (string) hash_file('sha256', $this->pub . '/' . $a);
        $log = $this->root . '/logs/journal.jsonl';

        $r = $this->migration($pdo, $log)->run(true);

        self::assertSame(2, $r['moved']);
        self::assertSame(0, $r['errors']);
        $newA = 'sse-private/sse-id-photos/1_0000000000000001.png';
        self::assertSame($newA, $pdo->query('SELECT photo_path FROM sse_identities WHERE id = 1')->fetchColumn());
        self::assertSame('sse-private/sse-id-backgrounds/2_0000000000000003.png', $pdo->query('SELECT background_path FROM sse_id_documents WHERE id = 1')->fetchColumn());
        self::assertSame($before, hash_file('sha256', $this->priv . '/sse-id-photos/1_0000000000000001.png'), 'octets identiques');
        self::assertFileDoesNotExist($this->pub . '/' . $a, 'copie publique supprimée après vérification');
        self::assertFileDoesNotExist($this->pub . '/' . $b);
        self::assertSame([], glob($this->priv . '/*/*.tmp-*') ?: [], 'aucun fichier temporaire');
        $lines = file($log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        self::assertCount(2, $lines, 'une ligne de journal par fichier');
        self::assertSame('moved', json_decode((string) $lines[0], true)['status']);
    }

    public function testApplyIsIdempotentAndResumable(): void
    {
        $pdo = $this->db();
        $this->legacy($pdo, 'sse_identities', 'photo_path', 'sse-id-photos', 1, '0000000000000001', 'A');
        $this->legacy($pdo, 'sse_identities', 'photo_path', 'sse-id-photos', 1, '0000000000000009', 'Z');
        $m = $this->migration($pdo);
        self::assertSame(1, $m->run(true, null, 1)['moved'], '--limit 1');
        self::assertSame(1, $m->run(true)['moved'], 'la reprise ne traite que le reste');
        $again = $m->run(true);
        self::assertSame(0, $again['files']);
        self::assertSame(0, $again['moved']);
        self::assertCount(2, glob($this->priv . '/sse-id-photos/*.png') ?: []);
        // Reprise après interruption : copie privée déjà faite, base encore sur l'ancien chemin, fichier public encore là.
        $rel = $this->legacy($pdo, 'sse_identities', 'photo_path', 'sse-id-photos', 1, '0000000000000005', 'R');
        copy($this->pub . '/' . $rel, $this->priv . '/sse-id-photos/1_0000000000000005.png');
        $r = $m->run(true);
        self::assertSame(1, $r['reused']);
        self::assertSame('sse-private/sse-id-photos/1_0000000000000005.png', $pdo->query('SELECT photo_path FROM sse_identities WHERE id = 3')->fetchColumn());
        self::assertFileDoesNotExist($this->pub . '/' . $rel);
    }

    public function testTenantFilterOnlyTouchesThatCommunity(): void
    {
        $pdo = $this->db();
        $one = $this->legacy($pdo, 'sse_identities', 'photo_path', 'sse-id-photos', 1, '0000000000000001', 'A');
        $two = $this->legacy($pdo, 'sse_identities', 'photo_path', 'sse-id-photos', 2, '0000000000000002', 'B');
        $r = $this->migration($pdo)->run(true, 2);
        self::assertSame(1, $r['moved']);
        self::assertFileExists($this->pub . '/' . $one, 'autre communauté intacte');
        self::assertFileDoesNotExist($this->pub . '/' . $two);
        self::assertSame($one, $pdo->query('SELECT photo_path FROM sse_identities WHERE tenant_id = 1')->fetchColumn());
    }

    public function testDifferentPrivateCopyIsACollisionAndNothingIsOverwrittenOrDeleted(): void
    {
        $pdo = $this->db();
        $rel = $this->legacy($pdo, 'sse_identities', 'photo_path', 'sse-id-photos', 1, '0000000000000001', 'A');
        mkdir($this->priv . '/sse-id-photos', 0777, true);
        file_put_contents($this->priv . '/sse-id-photos/1_0000000000000001.png', self::png('AUTRE'));
        $before = (string) file_get_contents($this->priv . '/sse-id-photos/1_0000000000000001.png');

        $dry = $this->migration($pdo)->run(false);
        self::assertSame(1, $dry['collisions']);
        $r = $this->migration($pdo)->run(true);

        self::assertSame(1, $r['collisions']);
        self::assertSame(0, $r['moved']);
        self::assertSame($before, file_get_contents($this->priv . '/sse-id-photos/1_0000000000000001.png'));
        self::assertFileExists($this->pub . '/' . $rel);
        self::assertSame($rel, $pdo->query('SELECT photo_path FROM sse_identities WHERE id = 1')->fetchColumn());
    }

    public function testSimulatedFailureLosesNothing(): void
    {
        $pdo = $this->db();
        $rel = $this->legacy($pdo, 'sse_identities', 'photo_path', 'sse-id-photos', 1, '0000000000000001', 'A');
        // Échec de la base : la mise à jour est refusée (déclencheur SQLite) après une copie réussie.
        $pdo->exec("CREATE TRIGGER no_update BEFORE UPDATE ON sse_identities BEGIN SELECT RAISE(ABORT, 'écriture refusée'); END");
        $r = $this->migration($pdo)->run(true);
        self::assertSame(1, $r['errors']);
        self::assertSame(0, $r['moved']);
        self::assertFileExists($this->pub . '/' . $rel, 'le fichier public reste');
        self::assertSame($rel, $pdo->query('SELECT photo_path FROM sse_identities WHERE id = 1')->fetchColumn(), 'chemin inchangé');
        self::assertFalse($pdo->inTransaction());

        // Échec de la copie : le dossier privé est un fichier, rien ne peut y être écrit.
        $pdo->exec('DROP TRIGGER no_update');
        $this->tearDownPrivate();
        file_put_contents($this->priv, 'je suis un fichier');
        $r2 = $this->migration($pdo)->run(true);
        self::assertSame(1, $r2['errors']);
        self::assertFileExists($this->pub . '/' . $rel);
        self::assertSame($rel, $pdo->query('SELECT photo_path FROM sse_identities WHERE id = 1')->fetchColumn());
        unlink($this->priv);
        mkdir($this->priv, 0777, true);

        // Une fois la cause levée, la reprise va au bout.
        $r3 = $this->migration($pdo)->run(true);
        self::assertSame(1, $r3['moved'] + $r3['reused']);
        self::assertFileDoesNotExist($this->pub . '/' . $rel);
    }

    private function tearDownPrivate(): void
    {
        foreach ($this->filesUnder($this->priv) as $f) {
            @unlink($f);
        }
        foreach (glob($this->priv . '/*', GLOB_ONLYDIR) ?: [] as $d) {
            @rmdir($d);
        }
        @rmdir($this->priv);
    }

    public function testASharedFileIsOnlyDeletedOnceNothingReferencesItAnymore(): void
    {
        $pdo = $this->db();
        $rel = $this->legacy($pdo, 'sse_identities', 'photo_path', 'sse-id-photos', 1, '0000000000000001', 'A');
        $pdo->prepare('INSERT INTO sse_file_photos (tenant_id, path) VALUES (1, ?)')->execute([$rel]);
        $r = $this->migration($pdo)->run(true);
        self::assertSame(1, $r['moved'], 'un seul fichier, deux lignes');
        self::assertSame('sse-private/sse-id-photos/1_0000000000000001.png', $pdo->query('SELECT path FROM sse_file_photos WHERE id = 1')->fetchColumn());
        self::assertFileDoesNotExist($this->pub . '/' . $rel);

        // Avec --tenant, une ligne d'une autre communauté qui référence le même fichier le garde en place.
        $pdo2 = $this->db();
        $rel2 = $this->legacy($pdo2, 'sse_identities', 'photo_path', 'sse-id-photos', 1, '0000000000000002', 'B');
        $pdo2->prepare('INSERT INTO sse_identities (tenant_id, photo_path) VALUES (2, ?)')->execute([$rel2]);
        $this->migration($pdo2)->run(true, 1);
        self::assertFileExists($this->pub . '/' . $rel2, 'encore référencé : copie publique conservée');
    }

    public function testRollbackRestoresThePublicCopyAndTheOldPath(): void
    {
        $pdo = $this->db();
        $rel = $this->legacy($pdo, 'sse_identities', 'photo_path', 'sse-id-photos', 1, '0000000000000001', 'A');
        $log = $this->root . '/j.jsonl';
        $m = $this->migration($pdo, $log);
        $m->run(true);
        self::assertFileDoesNotExist($this->pub . '/' . $rel);

        $back = $m->rollback($log);
        self::assertSame(1, $back['restored']);
        self::assertSame($rel, $pdo->query('SELECT photo_path FROM sse_identities WHERE id = 1')->fetchColumn());
        self::assertFileExists($this->pub . '/' . $rel);
        self::assertFileExists($this->priv . '/sse-id-photos/1_0000000000000001.png', 'l’espace privé n’est pas vidé');

        // Un journal altéré ne permet jamais d'écrire hors des dossiers attendus.
        file_put_contents($log, json_encode(['status' => 'moved', 'from' => 'uploads/sse-id-photos/../../x.png', 'to' => 'sse-private/sse-id-photos/../../x.png', 'rows' => []]) . "\n");
        self::assertSame(0, $m->rollback($log)['restored']);
    }

    public function testMissingSourceIsReportedAndNothingIsChanged(): void
    {
        $pdo = $this->db();
        $pdo->exec("INSERT INTO sse_identities (tenant_id, photo_path) VALUES (1, 'uploads/sse-id-photos/1_ffffffffffffffff.png')");
        $r = $this->migration($pdo)->run(true);
        self::assertSame(1, $r['missing']);
        self::assertSame('uploads/sse-id-photos/1_ffffffffffffffff.png', $pdo->query('SELECT photo_path FROM sse_identities WHERE id = 1')->fetchColumn());
    }

    // ------------------------------------------------------------------ règles du serveur web et documentation

    public function testNginxAndApacheRulesDenyTheDirectAccessAndAreDocumented(): void
    {
        $root = dirname(__DIR__, 2);
        $nginx = (string) file_get_contents($root . '/docs/nginx.example.conf');
        self::assertMatchesRegularExpression('#location ~\* \^/uploads/sse-id-\(photos\|signatures\|backgrounds\)\(/\|\$\)#', $nginx);
        self::assertStringContainsString('return 404;', $nginx);
        $htaccess = (string) file_get_contents($root . '/public/.htaccess');
        self::assertMatchesRegularExpression('#RewriteRule \^uploads/sse-id-\(photos\|signatures\|backgrounds\)/ - \[R=404,L\]#', $htaccess);
        $doc = (string) file_get_contents($root . '/docs/sse-images-hors-public.md');
        foreach (['sauvegarde', 'tools/sse-move-images-private.php', '--apply', 'retour arrière', 'nginx -t'] as $needle) {
            self::assertStringContainsStringIgnoringCase($needle, $doc);
        }
    }

    public function testPlatformCatalogListsThePrivateAndLegacyFolders(): void
    {
        $catalog = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Support/PlatformStorageCatalog.php');
        foreach (['storage/private/sse-images', 'public/uploads/sse-id-photos', 'public/uploads/sse-id-signatures', 'public/uploads/sse-id-backgrounds'] as $dir) {
            self::assertStringContainsString("'" . $dir . "'", $catalog);
        }
    }
}
