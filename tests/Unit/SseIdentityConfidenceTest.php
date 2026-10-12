<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\SsePortalController;
use PHPUnit\Framework\TestCase;

/**
 * Fiche identité : fiabilité, crédibilité et corroboration tirées de ce que le terrain a transmis
 * (elles restaient « — » pour toute fiche). Le SEEK transmet aussi le sexe apparent.
 */
final class SseIdentityConfidenceTest extends TestCase
{
    public function testSignedFieldCaptureWithPrintsAndIrisIsRated(): void
    {
        $c = SsePortalController::identityConfidence(
            ['signature' => ['callsign' => 'TA1'], 'from_arma' => true, 'identity_tier' => 'DECLARED'],
            [['kind' => 'empreintes', 'quality' => 70], ['kind' => 'iris', 'quality' => 58]],
            64
        );
        self::assertSame('B — Terrain, terminal signé', $c['source']);
        self::assertSame('3 — Possible', $c['credibility']);
        self::assertSame(33, $c['corroboration']);
        self::assertSame(64, $c['technical']);
    }

    public function testConfirmedAbisMatchIsProbable(): void
    {
        $c = SsePortalController::identityConfidence(
            ['from_arma' => true, 'identity_query' => ['result' => 'confirmed'], 'primary_photo_id' => 4],
            [['kind' => 'empreintes', 'quality' => 80]],
            80
        );
        self::assertSame('C — Terrain, non signé', $c['source']);
        self::assertSame('2 — Probable', $c['credibility']);
        self::assertSame(50, $c['corroboration']);
    }

    public function testManualFileWithoutEvidenceStaysEmpty(): void
    {
        $c = SsePortalController::identityConfidence([], [], null);
        self::assertSame('—', $c['source']);
        self::assertSame('—', $c['credibility']);
        self::assertNull($c['corroboration']);
    }

    public function testSeekSendsSexAgeAndKnownAffiliation(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_ssePersonDialogSubmit.sqf');
        self::assertStringContainsString('"sex_apparent"', $src);
        self::assertStringContainsString('comspec_sse_fnc_bioProfile', $src);
        self::assertStringContainsString('["orgOf", [_target]]', $src);
    }
}
