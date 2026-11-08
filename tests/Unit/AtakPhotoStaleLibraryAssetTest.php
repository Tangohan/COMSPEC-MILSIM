<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Ticket Athena #938 : une ancienne photo Photo Library absente du disque (file_not_found) ne doit plus bloquer les
 * photos du téléphone (pause feed_backoff de 5 min), ni occuper le fil des photos de COMSPEC Link pendant 20 s + 150 s.
 */
final class AtakPhotoStaleLibraryAssetTest extends TestCase
{
    private static function read(string $rel): string
    {
        $raw = file_get_contents(dirname(__DIR__, 2) . '/' . $rel);
        self::assertIsString($raw, $rel);

        return str_replace("\r\n", "\n", $raw);
    }

    public function testFeedBackoffOnlyAppliesToAutomaticFeedSnapshots(): void
    {
        $cap = self::read('mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_captureReconImage.sqf');
        self::assertStringContainsString('private _autoFeed = _feedId isNotEqualTo "" && {_photoMeta isEqualTo []};', $cap);
        // Calculé avant que la caméra active ne remplisse le flux (casque, drone).
        self::assertLessThan(strpos($cap, '_feedId = format ["helmet:%1"'), strpos($cap, 'private _autoFeed'));
        self::assertStringContainsString('if (_autoFeed && {_failUntil isEqualType 0} && {diag_tickTime < _failUntil}) exitWith {', $cap);
        self::assertStringNotContainsString('if (_failUntil isEqualType 0 && {diag_tickTime < _failUntil}) exitWith {', $cap);

        // Le téléphone appelle bien avec un flux vide et une fiche : il n'est pas un aperçu automatique.
        foreach (['fn_photoMode.sqf', 'fn_photoCam.sqf'] as $f) {
            $src = self::read('mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/pages/' . $f);
            self::assertStringContainsString('["", _caption, "CTAB", "", false, false, true, _meta] call comspec_overwatch_connect_fnc_captureReconImage', $src, $f);
        }
        // L'aperçu automatique garde sa pause (flux donné, pas de fiche).
        $feed = self::read('mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_snapshotVideoFeed.sqf');
        self::assertStringContainsString('["", _caption, _device, _feedId, false, false] call comspec_overwatch_connect_fnc_captureReconImage', $feed);
    }

    public function testStaleLibraryPhotoIsAWarningNotAPhoneError(): void
    {
        $cb = self::read('mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_extensionCallback.sqf');
        $start = strpos($cb, 'case "PhotoUpload": {');
        $end = strpos($cb, 'case "PhotoDiskSync": {');
        self::assertNotFalse($start);
        self::assertNotFalse($end);
        $block = substr($cb, (int) $start, (int) $end - (int) $start);

        self::assertStringContainsString('{(_hintLow find "comspec_") != 0}', $block);
        // « empty_path » (photo sans nom) reste un vrai échec.
        self::assertStringContainsString('{!(_hintLow in ["", "empty_path"])}', $block);
        // Défini avant la liste des photos mortes et la pause des flux qui s'en servent.
        self::assertLessThan(strpos($block, 'COMSPEC_FeedSnapFailUntil'), strpos($block, 'private _staleLib'));
        self::assertStringContainsString("_staleLib\n                    || {_planRefused}", $block);
        self::assertStringContainsString('{!_staleLib} && {!isNil "comspec_atak_native_fnc_notify"}', $block);
        self::assertStringContainsString('if (!_staleLib) then {' . "\n" . '                    missionNamespace setVariable ["COMSPEC_LastReconUploadResult"', $block);
        self::assertStringContainsString('while { (count _dead) > 600 } do { _dead deleteAt 0; };', $block);
        self::assertStringNotContainsString('(count _dead) > 200', $block);
        self::assertStringContainsString('elle ne sera plus renvoyée', $block);
    }

    public function testFullScreenshotFolderIsNamedToThePlayer(): void
    {
        $cb = self::read('mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_extensionCallback.sqf');
        self::assertStringContainsString('(_x select [0, 5]) isEqualTo "size="', $cb);
        self::assertStringContainsString('if (_mb >= 240) exitWith {', $cb);
        self::assertStringContainsString('Arma s\'arrête à 250 Mo', $cb);

        $ext = self::read('mod/UptoDate/COMSPECExtension/Extension.cs');
        self::assertStringContainsString('var sizeMb = DirectorySizeMb(active);', $ext);
        self::assertStringContainsString('|in={active.Replace(\'|\', \'/\')}{sizePart}', $ext);
    }

    public function testLinkDoesNotWaitOnOldTimestampedPhotos(): void
    {
        $ext = self::read('mod/UptoDate/COMSPECExtension/Extension.cs');
        self::assertStringContainsString('internal static bool IsStaleTimestampedShot(string? rawPath, DateTime nowLocal)', $ext);
        self::assertStringContainsString('@"^(\d{4})_(\d{2})_(\d{2})_(\d{2})_(\d{2})_(\d{2})"', $ext);
        self::assertStringContainsString('return nowLocal - taken > TimeSpan.FromHours(1);', $ext);
        self::assertStringContainsString('var staleShot = specificName && IsStaleTimestampedShot(job.RawPath, DateTime.Now);', $ext);
        self::assertStringContainsString('if (staleShot) maxAttempts = 1;', $ext);
        self::assertStringContainsString('if (resolved == null && specificName && !staleShot && job.LateRetries < PhotoLateRetryMax', $ext);
        self::assertStringContainsString('if (attempt + 1 >= maxAttempts) break;', $ext);

        // Même règle que la DLL, rejouée ici : le nom du ticket #938 est ancien, une photo du mod ne l'est jamais.
        $now = new \DateTimeImmutable('2026-11-08 22:54:00');
        self::assertTrue(self::isStale('2025_10_27_11_59_52.png', $now));
        self::assertTrue(self::isStale('2026_11_08_21_00_00.jpg', $now));
        self::assertFalse(self::isStale('2026_11_08_22_40_00.jpg', $now));
        self::assertFalse(self::isStale('COMSPEC_644_63259.png', $now));
        self::assertFalse(self::isStale('2027_01_01_00_00_00.png', $now));
    }

    public function testCapturesFolderIsWhereSentPhotosLive(): void
    {
        $ext = self::read('mod/UptoDate/COMSPECExtension/Extension.cs');
        // Envoi depuis la copie dans Captures ; une fois reçue, l'original du mod quitte Screenshots (limite 250 Mo).
        self::assertStringContainsString('originalShot = resolved;', $ext);
        self::assertStringContainsString("if (!FinishUploadedShot(originalShot, resolved))\n                    TryDeleteUploadedPhoto(resolved);", $ext);
        self::assertStringContainsString('internal static bool FinishUploadedShot(string? original, string? sent)', $ext);
        self::assertStringContainsString('if (!name.StartsWith("COMSPEC_", StringComparison.OrdinalIgnoreCase)) return true;', $ext);
        self::assertStringContainsString('if (IsUserShotDir(Path.GetDirectoryName(original) ?? "")) return true;', $ext);
        self::assertStringContainsString('if (src.Exists && copy.Exists && copy.Length == src.Length) src.Delete();', $ext);
        // Captures : 200 fichiers et 1 Go au plus.
        self::assertStringContainsString('private const long CapturesMaxBytes = 1024L * 1024 * 1024;', $ext);
        self::assertStringContainsString('if (i < 200 && kept <= CapturesMaxBytes) continue;', $ext);
        // Ménage de Screenshots toutes les 5 min (avant : une fois par partie) et tout de suite après un file_not_found.
        self::assertStringNotContainsString('_screenshotQuotaEnsured', $ext);
        self::assertStringContainsString('=> lastTicks == 0 || nowTicks - lastTicks >= TimeSpan.FromMinutes(5).Ticks;', $ext);
        self::assertStringContainsString('const long softLimit = 150L * 1024 * 1024;', $ext);
        self::assertStringContainsString('EnsureScreenshotQuota(force: true);', $ext);
        // Vérification du dossier : taille et dossier Captures ; ouverture dans l'explorateur.
        self::assertStringContainsString('+ "|" + Clean(ComspecCaptureDir() ?? "-");', $ext);
        self::assertStringContainsString('case "Shots.OpenCaptures":', $ext);
        self::assertStringContainsString('return "ERR|captures_unavailable";', $ext);

        $native = self::read('mod/COMSPEC_ATAK_Native/COMSPECATAKNativeExtension/COMSPECATAKNativeExtension.csproj');
        self::assertStringContainsString('<Compile Include="..\..\UptoDate\COMSPECExtension\Extension.cs"', $native);
    }

    public function testPhoneShowsFolderSizeAndOpensCaptures(): void
    {
        $base = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/';
        $shot = self::read($base . 'functions/camera/fn_shotDir.sqf');
        self::assertStringContainsString('["_mb", "-1"], ["_cap", "?"]', $shot);
        self::assertStringContainsString('case "open": {', $shot);
        self::assertStringContainsString('["Shots.OpenCaptures"] call comspec_atak_native_fnc_extensionCall', $shot);
        self::assertStringContainsString('COMSPEC_ATAK_ShotDirFullWarned', $shot);
        self::assertStringContainsString('if (_page in ["SETTINGS", "GALLERY"]) then {', $shot);

        $gallery = self::read($base . 'functions/pages/fn_pageGallery.sqf');
        self::assertStringContainsString('["OUVRIR LE DOSSIER CAPTURES", { ["open"] call comspec_atak_native_fnc_shotDir; }', $gallery);
        self::assertStringContainsString('[["text"] call comspec_atak_native_fnc_shotDir] call _esc', $gallery);
        $settings = self::read($base . 'functions/pages/fn_pageSettings.sqf');
        self::assertStringContainsString('["OUVRIR LE DOSSIER CAPTURES", { ["open"] call comspec_atak_native_fnc_shotDir; }]', $settings);
        $post = self::read($base . 'XEH_postInitClient.sqf');
        self::assertStringContainsString('[{ ["check", false] spawn comspec_atak_native_fnc_shotDir; }, [], 20] call comspec_atak_native_fnc_waitAndExec;', $post);

        $en = self::read($base . 'i18n/en.txt');
        foreach ([
            '["OUVRIR LE DOSSIER CAPTURES", "OPEN CAPTURES FOLDER"],',
            '["%1 Mo sur 250", "%1 MB of 250"],',
            '["Photos envoyées gardées dans %1", "Sent photos kept in %1"],',
        ] as $line) {
            self::assertStringContainsString($line, $en);
        }
    }

    private static function isStale(string $name, \DateTimeImmutable $now): bool
    {
        if (preg_match('/^(\d{4})_(\d{2})_(\d{2})_(\d{2})_(\d{2})_(\d{2})/', $name, $m) !== 1) {
            return false;
        }
        $taken = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', sprintf('%s-%s-%s %s:%s:%s', $m[1], $m[2], $m[3], $m[4], $m[5], $m[6]));
        if ($taken === false) {
            return false;
        }

        return $now->getTimestamp() - $taken->getTimestamp() > 3600;
    }
}
