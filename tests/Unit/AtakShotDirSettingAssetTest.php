<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tests\Support\ModVersion;

/**
 * Dossier des captures d'écran (ATAK 2.9.30, COMSPEC Link 2.0.116) : recherche bornée, réglage du joueur, message clair.
 */
final class AtakShotDirSettingAssetTest extends TestCase
{
    public function testDllLooksOnlyInBoundedScreenshotFolders(): void
    {
        $root = dirname(__DIR__, 2);
        $dll = (string) file_get_contents($root . '/mod/UptoDate/COMSPECExtension/Extension.cs');

        ModVersion::assertAtLeast('2.0.116', ModVersion::match('/ExtensionVersion\s*=\s*"([0-9.]+)"/', $dll));
        self::assertStringContainsString('private static string[] ScreenshotDirs()', $dll);
        self::assertStringContainsString('ComputeShotDirs', $dll);
        self::assertStringContainsString('"Shots.Config"', $dll);
        self::assertStringContainsString('"Shots.Check"', $dll);
        self::assertStringContainsString('function.StartsWith("Shots.", StringComparison.Ordinal)) return HandleShotDirs', $dll);
        // Plus de parcours Workshop / bibliothèques Steam / sous-dossiers.
        foreach (['EnumerateIcemanPhotoLibraryDirs', 'FindBceScreenshotDir', 'EnumerateSteamLibraryRoots', 'EnumerateFilesShallow', 'EnumerateLooseScreenshotDirs', 'IsLocalArma3Root'] as $gone) {
            self::assertStringNotContainsString($gone, $dll);
        }
        // Validation du chemin saisi : réseau, relatif, « .. », racine.
        foreach (['"network"', '"not_absolute"', '"parent"', '"drive_root"', 'DriveType.Network'] as $rule) {
            self::assertStringContainsString($rule, $dll);
        }
        // Le dossier en service est cité au joueur quand une capture est introuvable.
        self::assertStringContainsString('|in={active', $dll);
        // Aucune attente bloquante dans la résolution d'un chemin.
        $start = strpos($dll, 'private static string? ResolveLocalImagePath(');
        self::assertNotFalse($start);
        $body = substr($dll, $start, (int) strpos($dll, 'private static string DescribeImageLookupFailure', $start) - $start);
        self::assertStringNotContainsString('Thread.Sleep', $body);
        // Watchers démarrés hors du fil du jeu.
        self::assertStringContainsString('_ = Task.Run(StartScreenshotWatchersCore);', $dll);
    }

    public function testCbaSettingAndPhoneSettingsShowTheFolder(): void
    {
        $root = dirname(__DIR__, 2) . '/mod/COMSPEC_ATAK_Native/Sources/addons/main';
        $pre = (string) file_get_contents($root . '/XEH_preInit.sqf');
        $post = (string) file_get_contents($root . '/XEH_postInitClient.sqf');
        $cfg = (string) file_get_contents($root . '/config.cpp');
        $fn = (string) file_get_contents($root . '/functions/camera/fn_shotDir.sqf');
        $settings = (string) file_get_contents($root . '/functions/pages/fn_pageSettings.sqf');
        $hpp = (string) file_get_contents($root . '/script_component.hpp');
        $en = (string) file_get_contents($root . '/i18n/en.txt');

        self::assertStringContainsString('"comspec_atak_native_shot_dir", "EDITBOX"', $pre);
        self::assertStringContainsString('"comspec_atak_native_shot_check", "CHECKBOX"', $pre);
        self::assertStringContainsString('["COMSPEC", "ATAK · Photo"], "", 0,', $pre);
        self::assertStringContainsString('["apply"] call comspec_atak_native_fnc_shotDir;', $post);
        self::assertStringContainsString('class shotDir {};', $cfg);
        self::assertStringContainsString('"Shots.Config"', $fn);
        self::assertStringContainsString('"COMSPECExtension" callExtension ["Shots.Config"', $fn);
        self::assertStringContainsString('"Shots.Check"', $fn);
        self::assertStringContainsString('profileName', $fn);
        self::assertStringContainsString('VÉRIFIER LE DOSSIER', $settings);
        self::assertStringContainsString('#define LINK_VERSION_STR "2.0.116"', $hpp);
        self::assertStringContainsString('"Dossier des captures d\'écran", "Screenshot folder"', $en);
    }

    public function testMissingCaptureNamesTheFolder(): void
    {
        $root = dirname(__DIR__, 2);
        $cb = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_extensionCallback.sqf');
        $cfg = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp');
        $note = (string) file_get_contents($root . '/docs/bugs/2026-11-02-photos-recherche-captures-partout.md');

        self::assertStringContainsString('_fnc_shotMissing', $cb);
        self::assertStringContainsString('Capture introuvable dans %1 : vérifiez le réglage Dossier des captures', $cb);
        self::assertStringNotContainsString('Photo introuvable sur le disque — capturez à nouveau.', $cb);
        ModVersion::assertAtLeast('1.6.28', ModVersion::fromConfigCpp($cfg));
        self::assertStringContainsString('Workshop', $note);
        self::assertStringContainsString('Statut', $note);
    }
}
