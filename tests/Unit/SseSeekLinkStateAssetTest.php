<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * SEEK II « HORS LIAISON » permanent : l'état COMSPEC_LinkState est une chaîne, et
 * `str` l'entourait de guillemets ("\"linked\"" ≠ "linked"). Le SEEK et les requêtes ABIS
 * se croyaient donc toujours hors liaison.
 */
final class SseSeekLinkStateAssetTest extends TestCase
{
    private const FILES = [
        'mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_sseLinkState.sqf',
        'mod/@COMSPEC_SSE/addons/biometrics/functions/fn_bioQuery.sqf',
        'mod/@COMSPEC_SSE/addons/biometrics/functions/fn_bioCaptureLatentAction.sqf',
    ];

    public function testLinkStateIsReadAsAStringAndFallsBackOnNativeAtak(): void
    {
        $root = dirname(__DIR__, 2);
        foreach (self::FILES as $rel) {
            $src = (string) file_get_contents($root . '/' . $rel);
            self::assertStringNotContainsString('str (missionNamespace getVariable ["COMSPEC_LinkState"', $src, $rel);
            self::assertStringContainsString('missionNamespace getVariable ["COMSPEC_LinkState", ""]', $src, $rel);
            self::assertStringContainsString('"networkState"', $src, $rel);
        }
    }

    /** Le SEEK suit le même verdict que la barre d'état du téléphone (un seul calcul partagé). */
    public function testSeekUsesTheSharedLinkVerdict(): void
    {
        $root = dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/';
        foreach (['fn_ssePersonRefreshPanels.sqf', 'fn_sseIdentityQuery.sqf'] as $f) {
            self::assertStringContainsString('comspec_overwatch_connect_fnc_sseLinkState', (string) file_get_contents($root . $f), $f);
        }
        $state = (string) file_get_contents($root . 'fn_sseLinkState.sqf');
        self::assertStringContainsString('if (_net isEqualTo "CONNECTED") exitWith { "linked" };', $state);
    }

    public function testStudioAimsAtTheAnimatedEyePoint(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_sseFaceStudio.sqf');
        self::assertStringContainsString('["pilot", "Memory"]', $src);
        self::assertStringNotContainsString('["head", "FireGeometry"]', $src);
    }
}
