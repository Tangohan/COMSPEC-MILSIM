<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\ModVersion;

/**
 * Le mod SSE lit les champs d'atelier des papiers du pack d'identités (ref, issued_at, layout, print, images) :
 * câblage relu dans les sources SQF, tolérance des champs absents, bornes de taille, i18n et version du pack.
 */
final class SseIdentityPackPaperFieldsAssetTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../';
    private const ID = 'mod/@COMSPEC_SSE/addons/identity/functions/';

    private static function read(string $p): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(self::ROOT . $p));
    }

    public function testTheParserReadsEveryNewFieldFromTheSamePackFields(): void
    {
        $s = self::read(self::ID . 'fn_idAthenaPack.sqf');
        self::assertSame(1, preg_match('/private _docFields = \[[^\]]*"forgery_quality", "ref", "issued_at", "layout", "print", "images"\];/', $s));
        // Une seule liste de champs : plus de copie littérale dans les boucles « apply » et « applySelf ».
        self::assertSame(1, substr_count($s, '"forgery_quality"'));
        self::assertSame(2, substr_count($s, '[_x, _docFields] call _rec;'));
        self::assertSame(2, substr_count($s, '_d call _normDoc;'));
        // Contrat du site : les mêmes clés que SseIdentityPackService.
        $svc = self::read('app/Services/Sse/SseIdentityPackService.php');
        foreach (['ref', 'issued_at', 'layout', 'print', 'images', 'photo', 'signature', 'background'] as $key) {
            self::assertStringContainsString("'" . $key . "'", $svc, $key);
        }
    }

    public function testAbsentOrNullFieldsFallBackToEmptyValues(): void
    {
        $s = self::read(self::ID . 'fn_idAthenaPack.sqf');
        self::assertStringContainsString('{ _d set [_x, (_d getOrDefault [_x, ""]) call _s]; } forEach ["ref", "issued_at", "layout"];', $s);
        self::assertStringContainsString('private _s = { if (_this isEqualType "") then { _this select [0, 200] } else { "" } };', $s);
        self::assertStringContainsString('(_d getOrDefault ["print", []]) call _pairs', $s);
        self::assertStringContainsString('(_d getOrDefault ["images", []]) call _pairs', $s);
        self::assertStringContainsString('if (_this isEqualType []) then {', $s);
        self::assertStringContainsString('forEach ["photo", "signature", "background"];', $s);
        // images null : trois clés présentes, vides ; jamais de lecture sans valeur par défaut.
        self::assertStringContainsString('private _v = _im getOrDefault [_x, ""];', $s);
        self::assertStringNotContainsString('_d get "images"', $s);
        self::assertStringNotContainsString('_d get "print"', $s);
    }

    public function testSizesAndImagePathsAreBounded(): void
    {
        $s = self::read(self::ID . 'fn_idAthenaPack.sqf');
        self::assertStringContainsString('(count _out) < 16', $s);
        self::assertStringContainsString('_this select [0, 200]', $s);
        self::assertStringContainsString('for "_i" from 0 to ((_n min 400) - 1) do {', $s);
        // Chemins : ni « .. », ni schéma (« : »), ni antislash ; rien n'est chargé (Arma ne lit pas d'image par HTTP).
        self::assertStringContainsString('(_v find "..") >= 0 || {(_v find ":") >= 0} || {(_v find (toString [92])) >= 0}', $s);
        self::assertStringNotContainsString('ctrlSetText', $s);
        self::assertStringNotContainsString('setObjectTexture', $s);
        // Les morceaux du DLL restent sous la limite de retour d'extension (parseSimpleArray ≤ 7 400 octets).
        $cs = self::read('mod/UptoDate/COMSPECExtension/Extension_Identity.cs');
        self::assertSame(1, preg_match('/IdentityPackChunkBytes = (\d+);/', $cs, $m));
        self::assertLessThanOrEqual(7400, (int) $m[1]);
        self::assertLessThanOrEqual(12 * 7400, 12 * (int) $m[1]);
    }

    public function testTheControlShowsTheReferenceAndRejectsAPaperNotYetIssued(): void
    {
        $s = self::read(self::ID . 'fn_idEvaluate.sqf');
        self::assertStringContainsString('(_doc getOrDefault ["athenaId", ""]) isNotEqualTo ""', $s);
        self::assertStringContainsString('(count (_issued splitString "-")) isEqualTo 3', $s);
        self::assertSame(3, substr_count($s, 'if (_notYet) then {'));
        self::assertSame(3, substr_count($s, '_codes pushBack "not_issued"; ["fail", "faux"] call _worse;'));
        self::assertStringContainsString('private _paperRef = _ath getOrDefault ["ref", ""];', $s);
        self::assertStringContainsString('_codes pushBack "paper_ref";', $s);
        // Aucun toast, systemChat ni popup : seulement les constats du contrôle.
        self::assertStringNotContainsString('systemChat', $s);
        self::assertStringNotContainsString('hint ', $s);

        $p = self::read(self::ID . 'fn_idPaperOf.sqf');
        foreach (['paperRef' => 'ref', 'issuedAt' => 'issued_at', 'layout' => 'layout'] as $out => $in) {
            self::assertStringContainsString('_out set ["' . $out . '", _ath getOrDefault ["' . $in . '", ""]];', $p);
        }
        self::assertStringContainsString('_out set ["print", +(_ath getOrDefault ["print", createHashMap])];', $p);
        self::assertStringContainsString('_out set ["images", +(_ath getOrDefault ["images", createHashMap])];', $p);
    }

    public function testTheNewControlLinesAreInTheEnglishDictionaries(): void
    {
        $fr = ["Date d'émission postérieure à la date du jour (%1) : papier pas encore émis.", 'Référence du papier : %1.'];
        $en = self::read('mod/COMSPEC_ATAK_Native/Sources/addons/main/i18n/en.txt');
        $xx = self::read('mod/COMSPEC_ATAK_Native/localisation/template/xx.txt');
        foreach ($fr as $text) {
            self::assertSame(1, substr_count($en, '["' . $text . '", "'), $text);
            self::assertSame(1, substr_count($xx, '["' . $text . '", "", "'), $text);
        }
        foreach ([$en, $xx] as $dict) {
            $dict = rtrim($dict);
            self::assertStringStartsWith("[\n[", $dict);
            self::assertStringEndsWith("\"]\n]", $dict);
            self::assertSame(0, preg_match('/\]\n\n?\[/', $dict) ? 1 : 0, 'chaque entrée se termine par une virgule');
        }
    }

    public function testThePackVersionIsBumpedWithItsNotes(): void
    {
        $header = self::read('mod/@COMSPEC_SSE/addons/main/script_mod.hpp');
        $v = ModVersion::match('/versionStr = "([0-9.]+)"/', $header);
        ModVersion::assertAtLeast('1.12.0', $v);
        foreach (glob(self::ROOT . 'mod/@COMSPEC_SSE/addons/*/script_mod.hpp') ?: [] as $file) {
            self::assertStringContainsString('versionStr = "' . $v . '"', (string) file_get_contents($file), basename(dirname($file)));
        }
        self::assertStringContainsString('## 1.12.0', self::read('mod/@COMSPEC_SSE/docs/CHANGELOG.md'));
        self::assertStringContainsString('### Résumé joueur', self::read('mod/@COMSPEC_SSE/docs/CHANGELOG.md'));
        self::assertStringContainsString('[h1]COMSPEC SSE 1.12.0', self::read('mod/@COMSPEC_SSE/STEAM_CHANGELOG.txt'));
    }

    /** @return iterable<string, array{string}> */
    public static function sqfFiles(): iterable
    {
        foreach (['fn_idAthenaPack', 'fn_idEvaluate', 'fn_idPaperOf'] as $f) {
            yield $f => [self::ID . $f . '.sqf'];
        }
    }

    #[DataProvider('sqfFiles')]
    public function testSqfBracketsAreBalanced(string $path): void
    {
        $s = self::read($path);
        $s = (string) preg_replace('/"(?:[^"]|"")*"/', '', $s);
        $s = (string) preg_replace('#//[^\n]*#', '', $s);
        $s = (string) preg_replace('#/\*.*?\*/#s', '', $s);
        foreach (['{}', '[]', '()'] as $pair) {
            self::assertSame(substr_count($s, $pair[0]), substr_count($s, $pair[1]), basename($path) . ' ' . $pair);
        }
    }
}
