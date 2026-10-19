<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\FileShareController;
use App\Support\FileShareAccess;
use App\Support\FileShareStorage;
use App\Support\FileShareWorkspace;
use PHPUnit\Framework\TestCase;

final class FileShareAccessTest extends TestCase
{
    private const NOW = 1_800_000_000;

    /** Arbre : 1 Opérations (à 10) > 2 Ordres > 3 Annexes ; 4 Logistique (à 10) ; 5 dossier rattaché à un parent inconnu. */
    private static function folders(): array
    {
        return [
            ['id' => 1, 'parent_id' => null, 'name' => 'Opérations', 'owner_user_id' => 10, 'deleted_at' => null],
            ['id' => 2, 'parent_id' => 1, 'name' => 'Ordres', 'owner_user_id' => 10, 'deleted_at' => null],
            ['id' => 3, 'parent_id' => 2, 'name' => 'Annexes', 'owner_user_id' => 10, 'deleted_at' => null],
            ['id' => 4, 'parent_id' => null, 'name' => 'Logistique', 'owner_user_id' => 10, 'deleted_at' => null],
            ['id' => 5, 'parent_id' => 999, 'name' => 'Autre communauté', 'owner_user_id' => 20, 'deleted_at' => null],
        ];
    }

    private static function who(int $userId, array $roles = [], array $units = []): array
    {
        return ['user_id' => $userId, 'role_ids' => $roles, 'unit_ids' => $units];
    }

    private static function ws(array $grants, array $who, array $folders = []): FileShareWorkspace
    {
        return new FileShareWorkspace($folders !== [] ? $folders : self::folders(), $grants, $who, self::NOW);
    }

    public function testNothingIsVisibleWithoutShare(): void
    {
        $ws = self::ws([], self::who(20));

        self::assertSame(FileShareAccess::NONE, $ws->folderLevel(1));
        self::assertSame(FileShareAccess::NONE, $ws->folderLevel(3));
        self::assertSame([], $ws->entryFolders());
        self::assertSame(FileShareAccess::NONE, $ws->fileLevel(['id' => 7, 'folder_id' => 2, 'deleted_at' => null]));
    }

    public function testOwnerManagesEverythingInside(): void
    {
        $ws = self::ws([], self::who(10));

        self::assertSame(FileShareAccess::MANAGE, $ws->folderLevel(3));
        self::assertSame(['Logistique', 'Opérations'], array_column($ws->entryFolders(), 'name'));
    }

    public function testFolderShareIsInheritedButNotUpwards(): void
    {
        $grants = [['folder_id' => 2, 'file_id' => null, 'target_type' => 'user', 'target_id' => 20, 'level' => 'view', 'expires_at' => null]];
        $ws = self::ws($grants, self::who(20));

        self::assertSame(FileShareAccess::NONE, $ws->folderLevel(1));
        self::assertSame(FileShareAccess::VIEW, $ws->folderLevel(2));
        self::assertSame(FileShareAccess::VIEW, $ws->folderLevel(3));
        self::assertSame(['Ordres'], array_column($ws->entryFolders(), 'name'));
        // Le fil d'Ariane ne révèle pas le dossier parent fermé.
        self::assertSame(['Ordres', 'Annexes'], array_column($ws->breadcrumb(3), 'name'));
        self::assertCount(1, $ws->inheritedGrants(3));
    }

    public function testHighestMatchingGrantWinsAndOthersDoNotLeak(): void
    {
        $grants = [
            ['folder_id' => 1, 'target_type' => 'role', 'target_id' => 5, 'level' => 'view', 'expires_at' => null],
            ['folder_id' => 2, 'target_type' => 'unit', 'target_id' => 30, 'level' => 'upload', 'expires_at' => null],
            ['folder_id' => 4, 'target_type' => 'user', 'target_id' => 21, 'level' => 'manage', 'expires_at' => null],
        ];
        $ws = self::ws($grants, self::who(20, [5], [30]));

        self::assertSame(FileShareAccess::VIEW, $ws->folderLevel(1));
        self::assertSame(FileShareAccess::UPLOAD, $ws->folderLevel(3));
        self::assertSame(FileShareAccess::NONE, $ws->folderLevel(4));
    }

    public function testExpiredGrantGivesNothing(): void
    {
        $grants = [
            ['folder_id' => 1, 'target_type' => 'all', 'target_id' => 0, 'level' => 'view', 'expires_at' => date('Y-m-d H:i:s', self::NOW - 1)],
            ['folder_id' => 4, 'target_type' => 'all', 'target_id' => 0, 'level' => 'view', 'expires_at' => date('Y-m-d H:i:s', self::NOW + 3600)],
        ];
        $ws = self::ws($grants, self::who(20));

        self::assertSame(FileShareAccess::NONE, $ws->folderLevel(1));
        self::assertSame(FileShareAccess::VIEW, $ws->folderLevel(4));
    }

    public function testTrashedAncestorCutsAccessEvenForOwner(): void
    {
        $folders = self::folders();
        $folders[1]['deleted_at'] = '2026-10-19 08:00:00';
        $grants = [['folder_id' => 3, 'target_type' => 'user', 'target_id' => 20, 'level' => 'manage', 'expires_at' => null]];

        self::assertSame(FileShareAccess::NONE, self::ws($grants, self::who(20), $folders)->folderLevel(3));
        self::assertSame(FileShareAccess::NONE, self::ws([], self::who(10), $folders)->folderLevel(3));
        self::assertTrue(self::ws([], self::who(10), $folders)->canRestoreFolder(2));
        self::assertFalse(self::ws([], self::who(20), $folders)->canRestoreFolder(2));
    }

    public function testFolderWithUnknownParentIsNeverReachable(): void
    {
        $ws = self::ws([['folder_id' => 999, 'target_type' => 'all', 'target_id' => 0, 'level' => 'manage', 'expires_at' => null]], self::who(20));

        self::assertSame(FileShareAccess::NONE, $ws->folderLevel(5));
        self::assertSame([], $ws->chain(5));
    }

    public function testSingleFileShareStaysOnThatFile(): void
    {
        $grants = [['folder_id' => null, 'file_id' => 7, 'target_type' => 'user', 'target_id' => 20, 'level' => 'upload', 'expires_at' => null]];
        $ws = self::ws($grants, self::who(20));
        $shared = ['id' => 7, 'folder_id' => 2, 'uploaded_by' => 10, 'deleted_at' => null];
        $other = ['id' => 8, 'folder_id' => 2, 'uploaded_by' => 10, 'deleted_at' => null];

        // « Déposer » n'a pas de sens sur un fichier : ramené à Consulter.
        self::assertSame(FileShareAccess::VIEW, $ws->fileLevel($shared));
        self::assertSame(FileShareAccess::NONE, $ws->fileLevel($other));
        self::assertSame(FileShareAccess::NONE, $ws->folderLevel(2));
        self::assertSame([7], $ws->directFileIds());
        self::assertSame(FileShareAccess::NONE, $ws->fileLevel(['deleted_at' => '2026-10-19 08:00:00'] + $shared));
    }

    public function testUploaderCanRemoveOwnFileOnly(): void
    {
        $grants = [['folder_id' => 4, 'target_type' => 'user', 'target_id' => 20, 'level' => 'upload', 'expires_at' => null]];
        $ws = self::ws($grants, self::who(20));

        self::assertTrue($ws->canDeleteFile(['id' => 1, 'folder_id' => 4, 'uploaded_by' => 20, 'deleted_at' => null]));
        self::assertFalse($ws->canDeleteFile(['id' => 2, 'folder_id' => 4, 'uploaded_by' => 10, 'deleted_at' => null]));
    }

    public function testUnitShareCoversSubUnits(): void
    {
        // 30 compagnie > 31 section > 32 groupe ; le membre est dans le groupe 32.
        $units = FileShareAccess::withAncestorUnits([32], [30 => 0, 31 => 30, 32 => 31, 40 => 0]);
        sort($units);

        self::assertSame([30, 31, 32], $units);
        self::assertTrue(FileShareAccess::grantMatches(['target_type' => 'unit', 'target_id' => 30], self::who(20, [], $units), self::NOW));
        self::assertFalse(FileShareAccess::grantMatches(['target_type' => 'unit', 'target_id' => 40], self::who(20, [], $units), self::NOW));
        self::assertFalse(FileShareAccess::grantMatches(['target_type' => 'user', 'target_id' => 0], self::who(0), self::NOW));
    }

    public function testCleanNameStripsSeparatorsAndControls(): void
    {
        self::assertSame('Ordre initial.pdf', FileShareAccess::cleanName("  ../Ordre\tinitial.pdf ", 255));
        self::assertSame('a b', FileShareAccess::cleanName("a/\\b", 255));
        self::assertSame('', FileShareAccess::cleanName(' .. ', 255));
        self::assertSame('1,5 Mo', FileShareAccess::humanSize(1572864));
    }

    public function testStoragePathsAreBoundToTheirCommunity(): void
    {
        $path = 'partage/12/2026-10/' . str_repeat('ab', 20);

        self::assertTrue(FileShareStorage::isStoredPath($path));
        self::assertFalse(FileShareStorage::isStoredPath('partage/12/2026-10/../../.env'));
        self::assertNull(FileShareStorage::absolutePath($path, 13));
        self::assertTrue(FileShareStorage::accepts('carte.KMZ'));
        self::assertFalse(FileShareStorage::accepts('page.html'));
        self::assertFalse(FileShareStorage::accepts('script.php'));
        self::assertFalse(FileShareStorage::isInline('image/svg+xml'));
    }

    public function testStoreKeepsFileOutsideWebRootAndServesItAsDownload(): void
    {
        $tenantId = 900000 + random_int(1, 99999);
        $tmp = (string) tempnam(sys_get_temp_dir(), 'fs');
        file_put_contents($tmp, 'contenu');
        $stored = FileShareStorage::store($tenantId, $tmp, 'note.txt', false);

        try {
            self::assertNull($stored['error']);
            self::assertNotNull($stored['path']);
            self::assertStringStartsWith('partage/' . $tenantId . '/', (string) $stored['path']);
            self::assertSame(hash('sha256', 'contenu'), $stored['sha256']);
            self::assertNotNull(FileShareStorage::absolutePath((string) $stored['path'], $tenantId));
            $resp = FileShareStorage::response(['stored_path' => $stored['path'], 'name' => 'note "x".html.txt'], $tenantId, false);
            self::assertSame(200, $resp->statusCode());
            self::assertStringStartsWith('attachment;', (string) $resp->headerValue('Content-Disposition'));
            self::assertSame('nosniff', $resp->headerValue('X-Content-Type-Options'));
            self::assertSame(404, FileShareStorage::response(['stored_path' => $stored['path'], 'name' => 'note.txt'], $tenantId + 1, false)->statusCode());
        } finally {
            FileShareStorage::remove((string) $stored['path'], $tenantId);
            @rmdir(dirname(FileShareStorage::root() . '/' . substr((string) $stored['path'], strlen('partage/'))));
            @rmdir(FileShareStorage::root() . '/' . $tenantId);
        }
    }

    public function testNormalizeFilesSkipsEmptySlots(): void
    {
        $raw = [
            'name' => ['a.pdf', '', 'b.png'],
            'tmp_name' => ['/tmp/a', '', '/tmp/b'],
            'size' => [10, 0, 20],
            'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE, UPLOAD_ERR_INI_SIZE],
        ];
        $files = FileShareController::normalizeFiles($raw);

        self::assertCount(2, $files);
        self::assertSame('b.png', $files[1]['name']);
        self::assertSame(UPLOAD_ERR_INI_SIZE, $files[1]['error']);
        self::assertSame([], FileShareController::normalizeFiles(null));
    }
}
