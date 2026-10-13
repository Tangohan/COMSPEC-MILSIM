<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\MusicLibraryRepository;
use App\Support\MusicTrackStorage;
use PHPUnit\Framework\TestCase;

final class MusicLibraryTest extends TestCase
{
    public function testSignedStreamUrlVerifiesAndRejectsTamperingOrExpiry(): void
    {
        $now = 1_800_000_000;
        $path = MusicTrackStorage::signedPath(42, 'mp3', $now);
        self::assertMatchesRegularExpression('#^/api/music/stream/42\.mp3\?exp=(\d+)&sig=([0-9a-f]{32})$#', $path);
        preg_match('#exp=(\d+)&sig=([0-9a-f]+)#', $path, $m);
        $exp = (int) $m[1];
        self::assertTrue(MusicTrackStorage::verify(42, $exp, $m[2], $now));
        self::assertFalse(MusicTrackStorage::verify(43, $exp, $m[2], $now), 'autre piste');
        self::assertFalse(MusicTrackStorage::verify(42, $exp + 1, $m[2], $now), 'expiration modifiée');
        self::assertFalse(MusicTrackStorage::verify(42, $exp, $m[2], $exp + 1), 'lien expiré');
        // Même heure : même URL pour tous les auditeurs du haut-parleur (cache commun côté DLL).
        self::assertSame($path, MusicTrackStorage::signedPath(42, 'mp3', $now + 60));
    }

    public function testAudioSignatureDetection(): void
    {
        self::assertSame('mp3', MusicTrackStorage::extensionFromSignature("ID3\x04\x00\x00\x00\x00\x00\x00\x00\x00"));
        self::assertSame('mp3', MusicTrackStorage::extensionFromSignature("\xFF\xFB\x90\x00"));
        self::assertSame('aac', MusicTrackStorage::extensionFromSignature("\xFF\xF1\x50\x80"));
        self::assertSame('wav', MusicTrackStorage::extensionFromSignature('RIFF' . "\x00\x00\x00\x00" . 'WAVE'));
        self::assertSame('m4a', MusicTrackStorage::extensionFromSignature("\x00\x00\x00\x20ftypM4A "));
        self::assertNull(MusicTrackStorage::extensionFromSignature('<html><body>'));
    }

    public function testVisibilityRules(): void
    {
        self::assertTrue(MusicLibraryRepository::trackVisibleTo(['status' => 'active', 'scope' => 'community', 'tenant_id' => 9], 1));
        self::assertTrue(MusicLibraryRepository::trackVisibleTo(['status' => 'active', 'scope' => 'org', 'tenant_id' => 1], 1));
        self::assertFalse(MusicLibraryRepository::trackVisibleTo(['status' => 'active', 'scope' => 'org', 'tenant_id' => 9], 1));
        self::assertFalse(MusicLibraryRepository::trackVisibleTo(['status' => 'hidden', 'scope' => 'community', 'tenant_id' => 1], 1));

        self::assertTrue(MusicLibraryRepository::playlistVisibleTo(['visibility' => 'global'], 5, 0));
        self::assertTrue(MusicLibraryRepository::playlistVisibleTo(['visibility' => 'public', 'tenant_id' => 9], 5, 0));
        self::assertTrue(MusicLibraryRepository::playlistVisibleTo(['visibility' => 'org', 'tenant_id' => 5], 5, 0));
        self::assertFalse(MusicLibraryRepository::playlistVisibleTo(['visibility' => 'org', 'tenant_id' => 9], 5, 3));
        self::assertTrue(MusicLibraryRepository::playlistVisibleTo(['visibility' => 'private', 'owner_user_id' => 3], 5, 3));
        self::assertFalse(MusicLibraryRepository::playlistVisibleTo(['visibility' => 'private', 'owner_user_id' => 3], 5, 4));
        self::assertFalse(MusicLibraryRepository::playlistVisibleTo(['visibility' => 'private', 'owner_user_id' => 0], 5, 0));
    }

    public function testWiring(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $migrations = (string) file_get_contents($root . '/run-migrations.php');
        $nav = (string) file_get_contents($root . '/views/partials/ath_sidebar_nav.php');
        $dll = (string) file_get_contents($root . '/mod/UptoDate/COMSPECExtension/Extension.cs');

        self::assertStringContainsString("'/back-office/ma-situation/musique'", $routes);
        self::assertStringContainsString("'/api/atak/music'", $routes);
        self::assertStringContainsString("'/api/music/stream/{file}'", $routes);
        self::assertStringContainsString('music_library_migration.php', $migrations);
        self::assertStringContainsString('back-office/ma-situation/musique', $nav);
        self::assertStringContainsString('"/api/atak/music?view="', $dll);
        // Le flux signé ne doit pas passer sous /api/atak (protégé par jeton : la DLL du lecteur n'en envoie pas).
        self::assertStringNotContainsString("'/api/atak/music/stream", $routes);
        self::assertFileExists($root . '/views/admin/member_situation/musique.php');
    }

    public function testAdminPagesArePlatformOrManagerOnly(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        foreach (['/admin/musique', '/admin/musique/actions', '/admin/musique/envoyer', '/admin/musique/playlists/{id}/supprimer'] as $path) {
            self::assertMatchesRegularExpression("#router->(get|post)\\('" . preg_quote($path, '#') . "', \\[MusicAdminController::class, '[a-zA-Z]+'\\], \\[AuthMiddleware::class, SystemAdminMiddleware::class\\]#", $routes, $path);
        }
        foreach (['/back-office/ressources/musique', '/back-office/ressources/musique/actions', '/back-office/ressources/musique/envoyer', '/back-office/ressources/musique/playlists/{id}/supprimer'] as $path) {
            self::assertMatchesRegularExpression("#router->(get|post)\\('" . preg_quote($path, '#') . "', \\[MusicAdminController::class, '[a-zA-Z]+'\\], \\[AuthMiddleware::class, TenantResourceAdminMiddleware::class\\]#", $routes, $path);
        }
        self::assertStringContainsString("'admin/musique'", (string) file_get_contents($root . '/views/partials/platform_admin_sidebar.php'));
        self::assertStringContainsString("url('back-office/ressources/musique')", (string) file_get_contents($root . '/views/partials/ath_sidebar_nav.php'));
        self::assertFileExists($root . '/views/admin/music/index.php');
    }

    public function testUploadedFilesNormalisesSingleAndMultipleFields(): void
    {
        self::assertSame([], \App\Controllers\Admin\MusicAdminController::uploadedFiles(null));
        self::assertSame([], \App\Controllers\Admin\MusicAdminController::uploadedFiles(['name' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0]));
        $one = ['name' => 'a.mp3', 'type' => 'audio/mpeg', 'tmp_name' => '/tmp/a', 'error' => UPLOAD_ERR_OK, 'size' => 9];
        self::assertSame([$one], \App\Controllers\Admin\MusicAdminController::uploadedFiles($one));
        $multi = \App\Controllers\Admin\MusicAdminController::uploadedFiles([
            'name' => ['a.mp3', '', 'b.wav'], 'type' => ['audio/mpeg', '', 'audio/wav'], 'tmp_name' => ['/tmp/a', '', '/tmp/b'],
            'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE, UPLOAD_ERR_OK], 'size' => [9, 0, 7],
        ]);
        self::assertSame(['a.mp3', 'b.wav'], array_column($multi, 'name'));
    }

    public function testAtakRefreshesAthenaViewsWithoutRestart(): void
    {
        $sqf = (string) file_get_contents(dirname(__DIR__, 2) . '/mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/music/fn_pageMusic.sqf');
        self::assertStringContainsString('["ensure", _view] call comspec_atak_native_fnc_musicAthena', $sqf);
        // Un chargement perdu (fin de mission) ne doit pas bloquer l'onglet : état « busy » borné et garde-fou.
        self::assertStringContainsString('["busy", _view] call comspec_atak_native_fnc_musicAthena', $sqf);
        $athena = (string) file_get_contents(dirname(__DIR__, 2) . '/mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/music/fn_musicAthena.sqf');
        self::assertStringContainsString('CBA_fnc_waitAndExecute', $athena);
        self::assertStringContainsString('#define BUSY_MAX', $athena);
    }
}
