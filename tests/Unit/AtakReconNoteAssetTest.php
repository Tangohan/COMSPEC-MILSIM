<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ReconNoteCatalog;
use PHPUnit\Framework\TestCase;

final class AtakReconNoteAssetTest extends TestCase
{
    public function testCatalogTagsConfidenceAndFade(): void
    {
        self::assertSame('vehicle', ReconNoteCatalog::normalizeTag('Véhicule'));
        self::assertSame('armed_group', ReconNoteCatalog::normalizeTag('groupe_arme'));
        self::assertSame('Véhicule', ReconNoteCatalog::tagLabel('vehicle'));
        self::assertSame('vu_direct', ReconNoteCatalog::normalizeConfidence('vu direct'));
        self::assertSame('rapporte', ReconNoteCatalog::normalizeConfidence('rapporté'));
        self::assertSame('Vu direct', ReconNoteCatalog::confidenceLabel('vu_direct'));
        self::assertSame('fresh', ReconNoteCatalog::freshness(60));
        self::assertSame('aging', ReconNoteCatalog::freshness(25 * 60));
        self::assertSame('stale', ReconNoteCatalog::freshness(50 * 60));
        self::assertSame(1.0, ReconNoteCatalog::opacity(10));
        self::assertLessThan(0.4, ReconNoteCatalog::opacity(50 * 60));
    }

    public function testSqfDllApiAndTacmapChain(): void
    {
        $root = dirname(__DIR__, 2);
        $cfgC = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp');
        $cfgA = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp');
        $ace = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_initACE.sqf');
        $push = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_reconPushNote.sqf');
        $show = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_reconNoteShow.sqf');
        $look = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_reconLookPos.sqf');
        $dlg = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/display_recon_note.hpp');
        $sync = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_syncMapMarker.sqf');
        $ext = (string) file_get_contents($root . '/mod/UptoDate/COMSPECExtension/Extension.cs');
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $api = (string) file_get_contents($root . '/app/Controllers/Api/AtakApiController.php');
        $mig = (string) file_get_contents($root . '/bootstrap/atak_recon_notes_migration.php');
        $run = (string) file_get_contents($root . '/run-migrations.php');
        $mapJs = (string) file_get_contents($root . '/public/assets/js/comspec-operational-map.js');
        $notesJs = (string) file_get_contents($root . '/public/assets/js/tacmap-recon-notes.js');
        $tacmap = (string) file_get_contents($root . '/views/tacmap.php');
        $layers = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/ui/fn_createLayerPanel.sqf');
        $apply = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/ui/fn_applyMapLayers.sqf');
        $catalog = (string) file_get_contents($root . '/app/Support/DevDispatchCatalog.php');

        self::assertStringContainsString('versionStr = "1.6.9"', $cfgC);
        self::assertStringContainsString('versionStr = "1.0.165"', $cfgA);
        self::assertStringContainsString('class reconPushNote {}', $cfgC);
        self::assertStringContainsString('class reconNoteShow {}', $cfgC);
        self::assertStringContainsString('display_recon_note.hpp', $cfgC);
        self::assertStringContainsString('Reco : Noter', $ace);
        self::assertStringContainsString('athena_openRecon', $ace);
        self::assertStringContainsString('_menuVer = 9', $ace);
        self::assertStringContainsString('class AtakRecon', $cfgA);
        self::assertStringContainsString('COMSPEC_ATAK_Recon', $cfgA);
        self::assertStringContainsString('class athena_openRecon {}', $cfgA);
        self::assertStringContainsString('ui\\recon_page.hpp', $cfgA);
        $openRecon = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_openRecon.sqf');
        $filter = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_filterDrawerApps.sqf');
        $hide = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_hideForeignPages.sqf');
        $page = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/ui/recon_page.hpp');
        self::assertStringContainsString('AtakRecon', $openRecon);
        self::assertStringContainsString('AtakRecon', $filter);
        self::assertStringContainsString('comspec_atak_recon', $hide);
        self::assertStringContainsString('COMSPEC_ATAK_Recon', $page);
        self::assertStringContainsString('Véhicule', $page);
        self::assertStringContainsString('createMarker', $push);
        self::assertStringContainsString('RECON.Note', $push);
        self::assertStringContainsString('serverTime', $push);
        self::assertStringContainsString('comspec_recon_', $push);
        self::assertStringContainsString('lineIntersectsSurfaces', $look);
        self::assertStringContainsString('idd = 9966', $dlg);
        self::assertStringContainsString('Véhicule', $dlg);
        self::assertStringContainsString('8', $show);
        self::assertStringContainsString('"comspec_recon_"', $sync);
        self::assertStringContainsString('2.0.51', $ext);
        self::assertStringContainsString('RECON.Note', $ext);
        self::assertStringContainsString('/api/recon/notes', $ext);
        self::assertStringContainsString('ReconNote', $ext);
        self::assertStringContainsString("reconNotesIndex", $routes);
        self::assertStringContainsString("reconNotesStore", $routes);
        self::assertStringContainsString('function reconNotesStore', $api);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS recon_notes', $mig);
        self::assertStringContainsString('atak_recon_notes_migration.php', $run);
        self::assertStringContainsString('Notes reco', $tacmap);
        self::assertStringContainsString('tacmap-recon-notes.js', $tacmap);
        self::assertStringContainsString('renderReconNoteMarkers', $mapJs);
        self::assertStringContainsString('reconNotes', $mapJs);
        self::assertStringContainsString('TacmapReconNotes', $notesJs);
        self::assertStringContainsString('Notes reco', $layers);
        self::assertStringContainsString('comspec_recon_', $apply);
        self::assertStringContainsString('notes de reconnaissance', $catalog);
        self::assertStringContainsString("707", $catalog);
    }
}
