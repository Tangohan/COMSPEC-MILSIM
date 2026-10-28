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

    public function testComspecLinkSendsPhotoMetaThroughSendJournal(): void
    {
        $cs = $this->read('mod/UptoDate/COMSPECExtension/Extension.cs');
        // NotifyNewPhoto avec métadonnées depuis 2.0.66 ; les versions suivantes le gardent.
        self::assertMatchesRegularExpression('/private const string ExtensionVersion = "(\\d+\\.\\d+\\.\\d+)";/', $cs);
        preg_match('/private const string ExtensionVersion = "(\\d+\\.\\d+\\.\\d+)";/', $cs, $m);
        self::assertTrue(version_compare($m[1], '2.0.66', '>='), 'COMSPEC Link >= 2.0.66 attendu, trouvé ' . $m[1]);
        foreach (['"title", args, 17', '"description", args, 18', '"is_sse", args, 19', '"sse_case_ref", args, 20',
            '"is_confidential", args, 21', '"photo_source", args, 22', '"drone_name", args, 23'] as $needle) {
            self::assertStringContainsString($needle, $cs);
        }
        self::assertStringContainsString('"Photo drone"', $cs);
        self::assertStringContainsString('PhotoJournal(job, "sending");', $cs);
        self::assertStringContainsString('string label = ""', $this->read('mod/UptoDate/COMSPECExtension/Extension_SendJournal.cs'));
        self::assertFileDoesNotExist(dirname(__DIR__, 2) . '/mod/COMSPEC_ATAK_Native/COMSPECATAKNativeExtension/NativePhoto.cs');
    }

    public function testConnectAndDronePassMetaWithVersionFallback(): void
    {
        $capture = $this->read('mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_captureReconImage.sqf');
        self::assertStringContainsString('["_photoMeta", [], [[]]]', $capture);
        self::assertStringContainsString('_notifyArgs append', $capture);
        self::assertStringContainsString('["_mPos", [], [[]]]', $capture);

        $drone = $this->read('mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/drone/fn_dronePhoto.sqf');
        self::assertStringContainsString('GetExtensionVersion', $drone);
        self::assertStringContainsString('_shortCaption', $drone);
        self::assertStringContainsString('true, false, true, _meta] call comspec_overwatch_connect_fnc_captureReconImage', $drone);
        self::assertStringNotContainsString('NativePhoto', $drone);
    }

    public function testSiteStoresAndShowsPhotoMeta(): void
    {
        $mig = $this->read('bootstrap/atak_recon_images_photo_meta_migration.php');
        foreach (['title', 'description', 'is_sse', 'sse_case_ref', 'is_confidential', 'photo_source', 'drone_name'] as $col) {
            self::assertStringContainsString("'{$col}' =>", $mig);
        }
        self::assertStringContainsString("'atak_recon_images_photo_meta_migration.php'", \Tests\Support\MigrationPipelineSource::read(dirname(__DIR__, 2)));
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
