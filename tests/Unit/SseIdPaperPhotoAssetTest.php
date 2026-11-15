<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tests\Support\ModVersion;

/**
 * Photo du papier au contrôle d'identité (COMSPEC SSE 1.15.0, COMSPEC ATAK 4.26.0, COMSPEC Link 2.0.127) : câblage du mod
 * (bouton de la visionneuse, app Contrôle ID, envoi après le contrôle), garde-fous de la DLL et versions.
 * Les sources sont lues sans tenir compte des fins de ligne du checkout.
 */
final class SseIdPaperPhotoAssetTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';
    private const IDENTITY = 'mod/@COMSPEC_SSE/addons/identity/';
    private const LINK = 'mod/UptoDate/COMSPECExtension/';

    private static function read(string $rel): string
    {
        $src = file_get_contents(self::ROOT . '/' . $rel);
        self::assertIsString($src, $rel);

        return str_replace(["\r\n", "\r"], "\n", $src);
    }

    /** Code SQF sans commentaires ni chaînes : pour compter les délimiteurs et chercher des commandes. */
    private static function sqfCode(string $src): string
    {
        $src = (string) preg_replace('#/\*.*?\*/#s', '', $src);
        $src = (string) preg_replace('#"(?:[^"]|"")*"#', '""', $src);
        $src = (string) preg_replace('#//[^\n]*#', '', $src);

        return (string) preg_replace("#'(?:[^']|'')*'#", "''", $src);
    }

    public function testSqfDelimitersAreBalanced(): void
    {
        foreach (['fn_idPaperPhoto', 'fn_idViewerOnLoad', 'fn_idPage', 'fn_idAction', 'fn_idAthenaSubmit', 'fn_idPhoto'] as $fn) {
            $code = self::sqfCode(self::read(self::IDENTITY . 'functions/' . $fn . '.sqf'));
            foreach ([['{', '}'], ['[', ']'], ['(', ')']] as [$open, $close]) {
                self::assertSame(substr_count($code, $open), substr_count($code, $close), $fn . ' : ' . $open . $close);
            }
        }
    }

    public function testNothingIsShownByTheArmaInterface(): void
    {
        $code = self::sqfCode(self::read(self::IDENTITY . 'functions/fn_idPaperPhoto.sqf'));
        foreach (['hint', 'hintSilent', 'hintC', 'systemChat', 'cutText', 'titleText', 'cutRsc', 'titleRsc', 'globalChat', 'sideChat', 'BIS_fnc_showNotification', 'comspec_sse_fnc_notify', 'comspec_atak_native_fnc_notify'] as $banned) {
            self::assertDoesNotMatchRegularExpression('/\b' . preg_quote($banned, '/') . '\b/', $code, $banned);
        }
        // L'état passe par la ligne de la visionneuse et par l'app Contrôle ID.
        $src = self::read(self::IDENTITY . 'functions/fn_idPaperPhoto.sqf');
        self::assertStringContainsString('["status", _text, _color] call comspec_sse_fnc_idViewerOnLoad', $src);
        self::assertStringContainsString('["IDCHECK"] call comspec_atak_native_fnc_pageRender', $src);
    }

    public function testThePhotoIsAlwaysAPlayerGesture(): void
    {
        self::assertStringContainsString('class idPaperPhoto {};', self::read(self::IDENTITY . 'config.cpp'));
        $hpp = self::read(self::IDENTITY . 'dialogs/idViewer.hpp');
        self::assertMatchesRegularExpression('/class BtnPaper: RscButton \{\s*idc = 93716;\s*text = "PHOTO DU PAPIER";/', $hpp);
        self::assertStringContainsString("action = \"['shoot'] call comspec_sse_fnc_idPaperPhoto\";", $hpp);

        // « shoot » n'est appelé que par ce bouton : ni à l'ouverture de la visionneuse, ni à l'envoi du contrôle.
        $calls = 0;
        foreach (glob(self::ROOT . '/mod/@COMSPEC_SSE/addons/*/functions/*.sqf') ?: [] as $file) {
            $calls += preg_match_all('/\[\s*"shoot"\s*\]\s*call\s+comspec_sse_fnc_idPaperPhoto/', (string) file_get_contents($file));
        }
        self::assertSame(0, $calls);
        $src = self::read(self::IDENTITY . 'functions/fn_idPaperPhoto.sqf');
        self::assertSame(1, substr_count(self::sqfCode($src), 'screenshot _file'));

        $viewer = self::read(self::IDENTITY . 'functions/fn_idViewerOnLoad.sqf');
        self::assertStringContainsString('[93716, !_own && {!_zeus}]', $viewer);
        self::assertStringContainsString('(["can"] call comspec_sse_fnc_idPaperPhoto) params ["_okP", "_whyP", "_shortP"];', $viewer);

        $action = self::read(self::IDENTITY . 'functions/fn_idAction.sqf');
        self::assertStringContainsString('case "paper": { [_rec get "doc", false, false] call comspec_sse_fnc_idViewerOpen; };', $action);
        self::assertStringContainsString('case "paperResend": { ["send", _rec] call comspec_sse_fnc_idPaperPhoto; call _render; };', $action);
    }

    public function testSendingStateAndResendAreWired(): void
    {
        $src = self::read(self::IDENTITY . 'functions/fn_idPaperPhoto.sqf');
        foreach (['"taken"', '"sending"', '"sent"', '"failed"'] as $state) {
            self::assertStringContainsString('case ' . $state . ':', $src, $state);
        }
        self::assertStringContainsString('["UploadIdCheckPhoto", [_key, _rec getOrDefault ["paperShot", ""]] + _crop] call comspec_sse_fnc_extensionCall', $src);
        self::assertStringContainsString('["IdCheckPhotoStatus", [_key]] call comspec_sse_fnc_extensionCall', $src);
        self::assertStringContainsString('(_caps find "UploadIdCheckPhoto") >= 0', $src);
        // Le fichier a la forme stricte que la DLL attend, le cadre est borné à l'écran.
        self::assertStringContainsString('format ["COMSPEC_SSE_Paper_%1_%2.png"', $src);
        self::assertStringContainsString('apply { round (((_x max 0) min 1) * 10000) }', $src);

        $page = self::read(self::IDENTITY . 'functions/fn_idPage.sqf');
        self::assertStringContainsString('["Renvoyer la photo" call _tr, ["paperResend"] call _act', $page);
        self::assertStringContainsString('(["label", _cur] call comspec_sse_fnc_idPaperPhoto) param [0, ""]', $page);

        $submit = self::read(self::IDENTITY . 'functions/fn_idAthenaSubmit.sqf');
        self::assertStringContainsString('if ((_rec getOrDefault ["paperState", ""]) in ["taken", "failed"]) then { ["send", _rec] call comspec_sse_fnc_idPaperPhoto; };', $submit);
        self::assertLessThan(strpos($submit, '["send", _rec] call comspec_sse_fnc_idPaperPhoto'), strpos($submit, '_ok = true;'), 'la photo part après la réponse du site');
    }

    public function testTheDllKeepsStrictLimits(): void
    {
        $cs = self::read(self::LINK . 'Extension_IdCheckPhoto.cs');
        self::assertStringContainsString('internal const int IdPaperMaxJpegBytes = 1_500_000;', $cs);
        self::assertStringContainsString('internal const long IdPaperMaxSourceBytes = 96L * 1024 * 1024;', $cs);
        self::assertStringContainsString('@"^COMSPEC_SSE_Paper_[A-Za-z0-9_\-]{1,64}\.png$"', $cs, 'nom de fichier strict, sans dossier');
        self::assertStringContainsString('@"^[A-Za-z0-9_.:\-]{4,80}$"', $cs);
        // Lecture restreinte aux dossiers de captures : chemin résolu comparé au dossier, liens refusés.
        self::assertStringContainsString('path = IdPaperResolve(leaf, ScreenshotDirs());', $cs);
        self::assertStringContainsString('if (!string.Equals(Path.GetDirectoryName(full), root, StringComparison.OrdinalIgnoreCase)) continue;', $cs);
        self::assertStringContainsString('FileAttributes.ReparsePoint', $cs);
        self::assertStringNotContainsString('EnumeratePhotoLookupDirs', $cs);
        self::assertStringNotContainsString('Directory.GetFiles', $cs);
        // Type vérifié, débit borné.
        self::assertStringContainsString('if (!IdPaperIsPng(path)) return "failed|invalid_type";', $cs);
        self::assertStringContainsString('if (now - _idPaperLastMs < IdPaperMinIntervalMs) return "ERR|rate_limited";', $cs);
        self::assertStringContainsString('if (IdPaperRecent.Count >= IdPaperWindowMax) return "ERR|rate_limited";', $cs);
        self::assertStringContainsString('>= IdPaperMaxPending) return "ERR|queue_full";', $cs);
        self::assertStringContainsString('private const string IdPaperApiPath = "/api/sse/identity/checks/image";', $cs);
        self::assertStringContainsString('AttachApiKeyHeader(req);', $cs);
        // Rien au journal : ni jeton, ni clé, ni chemin.
        foreach (['Log(', 'LogWrite', 'Console.', 'Debug.', 'File.AppendAllText', 'InvokeCallback('] as $sink) {
            self::assertStringNotContainsString($sink, $cs, $sink);
        }

        $ext = self::read(self::LINK . 'Extension.cs');
        self::assertStringContainsString("if (function == \"UploadIdCheckPhoto\")\n            return BeginUploadIdCheckPhoto(args);", $ext);
        self::assertStringContainsString("if (function == \"IdCheckPhotoStatus\")\n            return IdCheckPhotoStatus(args);", $ext);
        self::assertStringContainsString('SubmitIdCheck,UploadIdCheckPhoto"', $ext, 'commande annoncée par GetAtakFeatures');
        // La capture du papier ne part jamais vers la photothèque (canal reconnaissance).
        self::assertStringContainsString('n.StartsWith("COMSPEC_SSE_Face", StringComparison.OrdinalIgnoreCase) || IsIdPaperCaptureName(n);', $ext);
        self::assertStringContainsString('Extension_IdCheckPhoto.cs', self::read('mod/COMSPEC_ATAK_Native/COMSPECATAKNativeExtension/COMSPECATAKNativeExtension.csproj'));
    }

    public function testVersionsAreAligned(): void
    {
        $hpp = self::read('mod/COMSPEC_ATAK_Native/Sources/addons/main/script_component.hpp');
        self::assertSame(1, preg_match('/#define LINK_VERSION_STR "([0-9.]+)"/', $hpp, $link));
        ModVersion::assertAtLeast('2.0.127', $link[1]);
        self::assertStringContainsString('private const string ExtensionVersion = "' . $link[1] . '";', self::read(self::LINK . 'Extension.cs'));
        $csproj = self::read(self::LINK . 'COMSPECExtension.csproj');
        self::assertStringContainsString('<Version>' . $link[1] . '</Version>', $csproj);
        self::assertStringContainsString('<InformationalVersion>' . $link[1] . '</InformationalVersion>', $csproj);
        $native = self::read('mod/COMSPEC_ATAK_Native/COMSPECATAKNativeExtension/COMSPECATAKNativeExtension.csproj');
        self::assertStringContainsString('<Version>' . $link[1] . '</Version>', $native);
        self::assertStringContainsString('<InformationalVersion>' . $link[1] . '-native</InformationalVersion>', $native);

        self::assertSame(1, preg_match('/#define VERSION_STR "([0-9.]+)"/', $hpp, $atak));
        ModVersion::assertAtLeast('4.26.0', $atak[1]);
        self::assertStringContainsString('(ATAK natif 4.26.0.AG - Washington, COMSPEC Link 2.0.127)', self::read('CHANGELOG-ATAK.md'));
        self::assertStringContainsString('[h1]COMSPEC ATAK 4.26.0 Washington', self::read('mod/COMSPEC_ATAK_Native/publisher/STEAM_CHANGELOG.txt'));

        foreach (glob(self::ROOT . '/mod/@COMSPEC_SSE/addons/*/script_mod.hpp') ?: [] as $file) {
            self::assertSame(1, preg_match('/versionStr = "([0-9.]+)"/', (string) file_get_contents($file), $sse), $file);
            ModVersion::assertAtLeast('1.15.0', $sse[1]);
        }
        self::assertStringContainsString('## 1.15.0', self::read('mod/@COMSPEC_SSE/docs/CHANGELOG.md'));
        self::assertStringContainsString('[h1]COMSPEC SSE 1.15.0', self::read('mod/@COMSPEC_SSE/STEAM_CHANGELOG.txt'));
    }

    public function testEnglishDictionaryHasThePaperPhotoTextsAndThePhysicalDescription(): void
    {
        $en = self::read('mod/COMSPEC_ATAK_Native/Sources/addons/main/i18n/en.txt');
        foreach ([
            '["PHOTO DU PAPIER", "PAPER PHOTO"]', '["Photo du papier non envoyée : %1.", "Paper photo not sent: %1."]',
            '["Renvoyer la photo", "Resend photo"]', '["Signalement physique", "Physical description"]',
        ] as $entry) {
            self::assertStringContainsString($entry, $en, $entry);
        }
        // Format strict : une entrée par ligne, virgule entre les entrées, aucune après la dernière, "" pour un guillemet.
        $lines = explode("\n", trim($en));
        self::assertSame('[', $lines[0]);
        self::assertSame(']', $lines[count($lines) - 1]);
        $last = count($lines) - 2;
        for ($i = max(1, $last - 40); $i <= $last; $i++) {
            self::assertMatchesRegularExpression('/^\["(?:[^"]|"")*", "(?:[^"]|"")*"\]' . ($i < $last ? ',' : '') . '$/', $lines[$i], 'ligne ' . ($i + 1));
        }
        self::assertStringContainsString('[["Signalement physique", "Synthèse"] select', self::read('mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/fiches/fn_pageFiches.sqf'));
    }
}
