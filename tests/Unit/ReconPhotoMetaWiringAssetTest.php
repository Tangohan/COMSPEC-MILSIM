<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Fiche des clichés du drone (titre, description, SSE, confidentiel) : de fn_dronePhoto jusqu'au poste.
 */
final class ReconPhotoMetaWiringAssetTest extends TestCase
{
    private function read(string $rel): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $rel);
    }

    public function testNativeDllSendsPhotoMetaFields(): void
    {
        $cs = $this->read('mod/COMSPEC_ATAK_Native/COMSPECATAKNativeExtension/NativePhoto.cs');
        foreach (['"title", a, 21', '"description", a, 22', '"is_sse", a, 23', '"sse_case_ref", a, 24',
            '"is_confidential", a, 25', '"photo_source", a, 26', '"drone_name", a, 27', '"/api/recon/images"'] as $needle) {
            self::assertStringContainsString($needle, $cs);
        }
        // Branché seulement dans le bloc de la DLL native : l'ancien chemin NotifyNewPhoto reste intact.
        $ext = $this->read('mod/UptoDate/COMSPECExtension/Extension.cs');
        $start = strpos($ext, '#if COMSPEC_ATAK_NATIVE', strpos($ext, 'TryGetSyncResponse'));
        $hook = strpos($ext, 'return NativePhotoCommand(args);');
        $end = strpos($ext, '#endif', (int) $start);
        self::assertNotFalse($hook);
        self::assertTrue($start < $hook && $hook < $end);
    }

    public function testDronePhotoCallsNativeDllWithOverwatchFallback(): void
    {
        $drone = $this->read('mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/drone/fn_dronePhoto.sqf');
        self::assertStringContainsString('["NativePhoto", _native] call comspec_atak_native_fnc_extensionCall', $drone);
        self::assertStringContainsString('["NativePhoto", ["status", _file]]', $drone);
        self::assertStringContainsString('_shortCaption', $drone);
        self::assertStringContainsString('[_file, _caption, "UAV", _feed, true, false, true] call comspec_overwatch_connect_fnc_captureReconImage', $drone);
    }

    public function testSiteStoresAndShowsPhotoMeta(): void
    {
        $mig = $this->read('bootstrap/atak_recon_images_photo_meta_migration.php');
        foreach (['title', 'description', 'is_sse', 'sse_case_ref', 'is_confidential', 'photo_source', 'drone_name'] as $col) {
            self::assertStringContainsString("'{$col}' =>", $mig);
        }
        self::assertStringContainsString("'atak_recon_images_photo_meta_migration.php'", $this->read('run-migrations.php'));
        self::assertStringContainsString('atak_recon_images_photo_meta_migration.php', $this->read('app/Repositories/ReconImageRepository.php'));

        $ctrl = $this->read('app/Controllers/Api/AtakApiController.php');
        self::assertStringContainsString('self::reconPhotoMetaFromPost($_POST)', $ctrl);
        self::assertStringContainsString('presentReconPhotoMeta', $ctrl);
        self::assertStringContainsString('SseCaseRepository::CLASS_CONFIDENTIAL', $ctrl);

        foreach (['public/assets/js/atak-overwatch-beta.js', 'public/assets/js/atak-cams.js', 'public/assets/js/tacmap-recon.js'] as $js) {
            $src = $this->read($js);
            self::assertStringContainsString('CONFIDENTIEL', $src, $js);
            self::assertStringContainsString('sse_case_url', $src, $js);
            self::assertStringContainsString('row.title', str_replace('p.title', 'row.title', $src), $js);
        }
    }
}
