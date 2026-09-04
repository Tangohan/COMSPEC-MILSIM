<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Backup\CompleteDataSnapshotService;
use PHPUnit\Framework\TestCase;

final class CompleteDataSnapshotServiceTest extends TestCase
{
    private string $root = '';

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'comspec-snap-' . bin2hex(random_bytes(4));
        mkdir($this->root . '/storage/uploads/avatars', 0777, true);
        mkdir($this->root . '/public/uploads/sse', 0777, true);
        mkdir($this->root . '/storage/snapshots', 0777, true);
        file_put_contents($this->root . '/storage/uploads/avatars/pic.jpg', 'photo');
        file_put_contents($this->root . '/public/uploads/sse/ev.png', 'png');
    }

    protected function tearDown(): void
    {
        if ($this->root !== '' && is_dir($this->root)) {
            $this->rmTree($this->root);
        }
    }

    public function testSanitizeLabelKeepsSafeTokens(): void
    {
        self::assertSame('avant-fusion', CompleteDataSnapshotService::sanitizeLabel('Avant fusion !'));
        self::assertSame('ok_1.2', CompleteDataSnapshotService::sanitizeLabel('OK_1.2'));
        self::assertSame('', CompleteDataSnapshotService::sanitizeLabel('***'));
    }

    public function testSnapshotIdRejectsPathTraversal(): void
    {
        $svc = $this->service();
        $this->expectException(\RuntimeException::class);
        $svc->assertSnapshotId('../etc/passwd');
    }

    public function testPathTraversalEntriesAreRejected(): void
    {
        $svc = $this->service();
        self::assertFalse($svc->isAllowedStoragePath('../.env'));
        self::assertFalse($svc->isAllowedStoragePath('storage/uploads/../../.env'));
        self::assertFalse($svc->isAllowedStoragePath('app/Config/database.php'));
        self::assertTrue($svc->isAllowedStoragePath('storage/uploads/avatars/pic.jpg'));
        self::assertTrue($svc->isAllowedStoragePath('public/uploads/sse/ev.png'));
    }

    public function testCreateStorageOnlyThenRestoreOverlay(): void
    {
        $svc = $this->service();
        $manifest = $svc->create('essai', false, true, 10);
        self::assertNotEmpty($manifest['id']);
        self::assertFalse($manifest['database']['included']);
        self::assertTrue($manifest['storage']['included']);
        self::assertGreaterThanOrEqual(2, (int) $manifest['storage']['file_count']);

        $check = $svc->verify((string) $manifest['id']);
        self::assertTrue($check['ok'], implode(' ', $check['errors']));

        file_put_contents($this->root . '/storage/uploads/avatars/pic.jpg', 'changed');
        file_put_contents($this->root . '/storage/uploads/avatars/new.jpg', 'nouveau');

        $svc->restore((string) $manifest['id'], false, true, false, false);
        self::assertSame('photo', (string) file_get_contents($this->root . '/storage/uploads/avatars/pic.jpg'));
        self::assertFileExists($this->root . '/storage/uploads/avatars/new.jpg');
    }

    public function testPruneStorageRemovesFilesAbsentFromSnapshot(): void
    {
        $svc = $this->service();
        $manifest = $svc->create('prune', false, true, 10);
        file_put_contents($this->root . '/storage/uploads/avatars/new.jpg', 'nouveau');
        $svc->restore((string) $manifest['id'], false, true, true, false);
        self::assertFileDoesNotExist($this->root . '/storage/uploads/avatars/new.jpg');
        self::assertSame('photo', (string) file_get_contents($this->root . '/storage/uploads/avatars/pic.jpg'));
    }

    public function testRestoreDryRunDoesNotWriteFiles(): void
    {
        $svc = $this->service();
        $manifest = $svc->create('dry', false, true, 10);
        file_put_contents($this->root . '/storage/uploads/avatars/pic.jpg', 'changed');
        $svc->restore((string) $manifest['id'], false, true, false, true);
        self::assertSame('changed', (string) file_get_contents($this->root . '/storage/uploads/avatars/pic.jpg'));
    }

    public function testDisallowedIndexPathsAreNotRestored(): void
    {
        $svc = $this->service();
        $manifest = $svc->create('safe', false, true, 10);
        $dir = $svc->snapshotDir((string) $manifest['id']);
        $indexPath = $dir . DIRECTORY_SEPARATOR . 'files-index.json';
        $index = json_decode((string) file_get_contents($indexPath), true);
        self::assertIsArray($index);
        $index['../secret.txt'] = ['bytes' => 3, 'sha256' => hash('sha256', 'pwn')];
        $index['app/hack.php'] = ['bytes' => 3, 'sha256' => hash('sha256', 'pwn')];
        file_put_contents($indexPath, json_encode($index));
        mkdir($dir . '/files/app', 0777, true);
        file_put_contents($dir . '/files/app/hack.php', 'pwn');
        file_put_contents($dir . '/secret.txt', 'pwn');

        $hash = hash_file('sha256', $indexPath);
        $manifestPath = $dir . DIRECTORY_SEPARATOR . 'manifest.json';
        $meta = json_decode((string) file_get_contents($manifestPath), true);
        self::assertIsArray($meta);
        $meta['storage']['sha256'] = $hash;
        file_put_contents($manifestPath, json_encode($meta, JSON_PRETTY_PRINT));
        file_put_contents($dir . DIRECTORY_SEPARATOR . 'SHA256SUMS', $hash . "  files-index.json\n");

        $svc->restore((string) $manifest['id'], false, true, false, false);
        self::assertFileDoesNotExist($this->root . '/secret.txt');
        self::assertFileDoesNotExist($this->root . '/app/hack.php');
        self::assertFileExists($this->root . '/storage/uploads/avatars/pic.jpg');
    }

    public function testVerifyDetectsTamperedIndex(): void
    {
        $svc = $this->service();
        $manifest = $svc->create('tamper', false, true, 10);
        $indexPath = $svc->snapshotDir((string) $manifest['id']) . DIRECTORY_SEPARATOR . 'files-index.json';
        file_put_contents($indexPath, (string) file_get_contents($indexPath) . ' ');
        $check = $svc->verify((string) $manifest['id']);
        self::assertFalse($check['ok']);
    }

    public function testPruneKeepsNewestSnapshots(): void
    {
        $svc = $this->service();
        $a = $svc->create('a', false, true, 0);
        $b = $svc->create('b', false, true, 0);
        self::assertCount(2, $svc->list());
        $removed = $svc->prune(1);
        self::assertSame(1, $removed);
        $left = $svc->list();
        self::assertCount(1, $left);
        self::assertSame($b['id'], $left[0]['id']);
        unset($a);
    }

    public function testSnapshotDirMustNotSitUnderPublic(): void
    {
        $bad = $this->root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'oops';
        $svc = new CompleteDataSnapshotService($this->root, null, null, null, $bad);
        $this->expectException(\RuntimeException::class);
        $svc->snapshotRoot();
    }

    private function service(): CompleteDataSnapshotService
    {
        $snaps = $this->root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'snapshots';

        return new CompleteDataSnapshotService($this->root, null, null, null, $snaps);
    }

    private function rmTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        /** @var \SplFileInfo $file */
        foreach ($it as $file) {
            $real = $file->getRealPath();
            if ($real === false) {
                continue;
            }
            $file->isDir() ? @rmdir($real) : @unlink($real);
        }
        @rmdir($dir);
    }
}
