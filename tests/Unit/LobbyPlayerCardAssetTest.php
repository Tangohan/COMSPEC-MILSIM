<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tests\Support\ModVersion;

/**
 * Dossier du joueur à l'écran des rôles (COMSPEC Lobby 0.3.1, ATAK natif 4.26.1, COMSPEC Link 2.0.128) :
 *  - la fonction est celle du membre sur Athena, jamais le texte du poste ; grade affiché ; ligne vide masquée ;
 *  - la photo : le cache d'images de la DLL revient dans le dossier du jeu (lu par Arma), plus dans Documents.
 * Lecture des fichiers sans dépendre des fins de ligne du checkout.
 */
final class LobbyPlayerCardAssetTest extends TestCase
{
    private const LOBBY = 'mod/COMSPEC_Lobby/Sources/addons/main/functions/fn_lobbyOpen.sqf';
    private const AVATAR = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/network/fn_avatarPath.sqf';
    private const TEX = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/ui/fn_texUsable.sqf';

    private static function read(string $p): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/' . $p));
    }

    /** Code SQF sans commentaires ni contenu des chaînes (« "" » compris). */
    private static function bare(string $sqf): string
    {
        $out = '';
        $n = strlen($sqf);
        for ($i = 0; $i < $n; $i++) {
            $c = $sqf[$i];
            $d = $sqf[$i + 1] ?? '';
            if ($c === '/' && $d === '/') {
                while ($i < $n && $sqf[$i] !== "\n") {
                    $i++;
                }
                $out .= "\n";
                continue;
            }
            if ($c === '/' && $d === '*') {
                $end = strpos($sqf, '*/', $i + 2);
                $i = $end === false ? $n : $end + 1;
                continue;
            }
            if ($c === '"' || $c === "'") {
                for ($i++; $i < $n; $i++) {
                    if ($sqf[$i] === $c) {
                        if (($sqf[$i + 1] ?? '') === $c) {
                            $i++;
                            continue;
                        }
                        break;
                    }
                }
                $out .= '""';
                continue;
            }
            $out .= $c;
        }

        return $out;
    }

    public function testTouchedSqfFilesHaveBalancedDelimiters(): void
    {
        $pairs = [')' => '(', ']' => '[', '}' => '{'];
        foreach ([self::LOBBY, self::AVATAR, self::TEX] as $file) {
            $stack = [];
            foreach (str_split(self::bare(self::read($file))) as $ch) {
                if (in_array($ch, $pairs, true)) {
                    $stack[] = $ch;
                } elseif (isset($pairs[$ch])) {
                    self::assertSame($pairs[$ch], array_pop($stack), "{$file} : « {$ch} » sans ouverture correspondante");
                }
            }
            self::assertSame([], $stack, "{$file} : délimiteur resté ouvert");
        }
    }

    public function testNoOnScreenAlertOfTheGameInTouchedFiles(): void
    {
        foreach ([self::LOBBY, self::AVATAR, self::TEX] as $file) {
            self::assertSame(
                0,
                preg_match('/\b(systemChat|hint|hintSilent|hintC|cutText|titleText|cutRsc|BIS_fnc_showNotification|BIS_fnc_guiMessage)\b/i', self::bare(self::read($file)), $m),
                $file . ' : ' . ($m[0] ?? '')
            );
        }
    }

    public function testFunctionLineIsTheMembersFunctionNeverTheSlotText(): void
    {
        $s = self::read(self::LOBBY);
        // Le texte du poste n'entre plus dans la carte du joueur.
        self::assertStringNotContainsString('_h set ["role", _name]', $s);
        self::assertSame(0, preg_match('/set \["role",\s*_(name|text|tag)\b/', $s));
        // Sans carte Athena, le texte du rôle ne sert que de nom (comportement d'origine), pas de fonction.
        self::assertStringContainsString('_h set ["surname", [_name, "-"] select (_name isEqualTo "")];', $s);
        // La fonction et le grade viennent de la liaison Athena gardée par la DLL (cellules 17 et 9 de GetAuthState).
        self::assertStringContainsString('private _auth = (["GetAuthState", []] call _ext) splitString _tab;', $s);
        self::assertStringContainsString('if ((_auth param [1, ""]) isEqualTo "READY") then {', $s);
        self::assertStringContainsString('_rank = 9 call _cell;', $s);
        self::assertStringContainsString('_role = 17 call _cell;', $s);
        self::assertStringContainsString('["rank", _rank], ["unit", 3 call _col], ["role", _role], ["quals", _qn]', $s);
        // Ordre des cellules de la DLL : grade en 9, fonction en 17 (OK = 0).
        $dll = self::read('mod/UptoDate/COMSPECExtension/GameAuth.cs');
        self::assertSame(1, preg_match('/return string\.Join\("\\\\t",(.*?)\);/s', substr($dll, (int) strpos($dll, 'private static string FormatGameAuthState()')), $m));
        $cells = array_values(array_filter(array_map('trim', explode("\n", $m[1])), static fn (string $l): bool => $l !== ''));
        self::assertSame('IdentityCell(_gameProfileGrade),', $cells[9]);
        self::assertSame('IdentityCell(_gameProfileFunction),', $cells[17]);
        self::assertSame('TabCell(_gameAuthState),', $cells[1]);
    }

    public function testAtakKeepsRankAndFunctionInTheCardOfTheRoleScreen(): void
    {
        $a = self::read(self::AVATAR);
        self::assertStringContainsString('["rank", "comspec_profile_grade" call _prof], ["unit", 3 call _col], ["role", "comspec_profile_function" call _prof],', $a);
        $connect = self::read('mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/auth/fn_applyBootstrap.sqf');
        self::assertStringContainsString('missionNamespace setVariable ["comspec_profile_grade", _grade, false];', $connect);
        self::assertStringContainsString('missionNamespace setVariable ["comspec_profile_function", _function, false];', $connect);
    }

    public function testEmptyLinesAreHiddenAndLongQualificationsAreCut(): void
    {
        $s = self::read(self::LOBBY);
        self::assertStringContainsString('private _rows = [[9110, 9114], [9111, 9115], [9112, 9116], [9113, 9117]];', $s);
        self::assertStringContainsString('{ (_x call _c) ctrlShow _show; } forEach [_li, _vi];', $s);
        self::assertStringContainsString('[_card getOrDefault ["quals", ""], 9117 call _c] call _fitQuals', $s);
        self::assertStringContainsString('getTextWidth ["RobotoCondensed", _size]', $s);
        // La police mesurée est celle du contrôle des qualifications.
        self::assertSame(1, preg_match('/DTEXT\(ValueQuals, 9117,[^\n]*"RobotoCondensed"/', self::read('mod/COMSPEC_Lobby/Sources/addons/main/RscLobby.hpp')));
        // Plus aucune écriture directe d'une valeur vide dans une ligne.
        self::assertStringNotContainsString('(9116 call _c) ctrlSetText (_card getOrDefault ["role", ""])', $s);
    }

    public function testPortraitFrameSaysWhyItIsEmptyOnTheCardItself(): void
    {
        $s = self::read(self::LOBBY);
        self::assertStringContainsString('private _shown = [_pic] call _portrait;', $s);
        self::assertStringContainsString('case (_isSelf): { localize "STR_COMSPEC_LOBBY_PortraitNone" };', $s);
        self::assertStringContainsString('localize "STR_COMSPEC_LOBBY_PortraitHintNone", localize "STR_COMSPEC_LOBBY_PortraitHintRetry"', $s);
        // Copie JPEG du site quand la texture .paa n'est pas lisible ici (le navigateur intégré ne lit pas le .paa).
        self::assertStringContainsString('private _web = [(_url select [0, (count _url) - 4]) + ".jpg"] call _get;', $s);
        $dll = self::read('mod/UptoDate/COMSPECExtension/Extension.cs');
        self::assertStringContainsString('if (!localPng && !localJpeg) return "ERR|invalid_image";', $dll);
        // Le site range bien cette copie à côté du .paa, sous le même nom.
        self::assertSame('uploads/portraits/atak/41_1700000000.jpg', \App\Support\AtakGameImage::webRel(\App\Support\AtakGameImage::paaRel('uploads/portraits/41_1700000000.png')));
    }

    public function testImageCacheIsBackInTheGameFolderWhichArmaCanRead(): void
    {
        $dll = self::read('mod/UptoDate/COMSPECExtension/Extension.cs');
        $a = strpos($dll, 'private static string[] ImageCacheDirs()');
        self::assertNotFalse($a);
        $body = substr($dll, (int) $a, 700);
        $game = strpos($body, 'Path.Combine(AppContext.BaseDirectory, "comspec_cache", "briefing")');
        $docs = strpos($body, 'Path.Combine(persistent, "briefing")');
        $temp = strpos($body, 'Path.Combine(Path.GetTempPath(), "comspec_cache", "briefing")');
        self::assertNotFalse($game);
        self::assertNotFalse($docs);
        self::assertNotFalse($temp);
        self::assertTrue($game < $docs && $docs < $temp, 'Dossier du jeu, puis Documents, puis TEMP.');
        self::assertStringContainsString('foreach (var dir in ImageCacheDirs())', $dll);
        // Portrait déjà téléchargé ailleurs : copié, pas retéléchargé.
        self::assertStringContainsString('File.Copy(avatarElsewhere, cached, true);', $dll);

        // Le téléphone ne demande plus à Arma une image qu'il ne lit pas (fenêtre « Picture … not found »).
        $tex = self::read(self::TEX);
        self::assertStringContainsString('if (fileExists _x) then {', $tex);
        self::assertStringNotContainsString('private _cands = [_p];', $tex);
        self::assertTrue(strpos($tex, 'if (fileExists _x) then {') < strpos($tex, 'getTextureInfo _x;'));
    }

    public function testVersionsAreAligned(): void
    {
        $hpp = self::read('mod/COMSPEC_Lobby/Sources/addons/main/script_component.hpp');
        $lobby = ModVersion::match('/#define VERSION_STR ([0-9.]+)/', $hpp);
        ModVersion::assertAtLeast('0.3.1', $lobby);
        [$maj, $min, $pat] = array_map('intval', explode('.', $lobby));
        self::assertStringContainsString("#define VERSION_AR {$maj},{$min},{$pat}", $hpp);
        $toml = self::read('mod/COMSPEC_Lobby/Sources/.hemtt/project.toml');
        self::assertSame(1, preg_match('/major = (\d+)\nminor = (\d+)\npatch = (\d+)/', $toml, $t));
        self::assertSame([$maj, $min, $pat], [(int) $t[1], (int) $t[2], (int) $t[3]]);
        self::assertStringStartsWith("[h1]COMSPEC Lobby {$lobby} ", self::read('mod/COMSPEC_Lobby/workshop/STEAM_CHANGELOG.txt'));
        self::assertStringContainsString("Version {$lobby} ", self::read('mod/COMSPEC_Lobby/README.md'));

        $atakHpp = self::read('mod/COMSPEC_ATAK_Native/Sources/addons/main/script_component.hpp');
        $link = ModVersion::match('/#define LINK_VERSION_STR "([0-9.]+)"/', $atakHpp);
        ModVersion::assertAtLeast('2.0.128', $link);
        self::assertStringContainsString('private const string ExtensionVersion = "' . $link . '";', self::read('mod/UptoDate/COMSPECExtension/Extension.cs'));
        self::assertStringContainsString("<Version>{$link}</Version>", self::read('mod/UptoDate/COMSPECExtension/COMSPECExtension.csproj'));
        self::assertStringContainsString("<InformationalVersion>{$link}-native</InformationalVersion>", self::read('mod/COMSPEC_ATAK_Native/COMSPECATAKNativeExtension/COMSPECATAKNativeExtension.csproj'));
        ModVersion::assertAtLeast('4.26.1', ModVersion::match('/#define VERSION_STR "([0-9.]+)"/', $atakHpp));

        $log = self::read('CHANGELOG-ATAK.md');
        self::assertStringContainsString('(ATAK natif 4.26.1.AG - Washington, COMSPEC Link 2.0.128, COMSPEC Lobby 0.3.1)', $log);
        $entry = substr($log, (int) strpos($log, 'COMSPEC Lobby 0.3.1)'), 2500);
        self::assertStringContainsString("### Résumé joueur\nType : correctif\nFR : ", $entry);
        self::assertStringContainsString("\nEN : ", $entry);
        self::assertStringContainsString('[h1]COMSPEC ATAK 4.26.1 Washington', self::read('mod/COMSPEC_ATAK_Native/publisher/STEAM_CHANGELOG.txt'));
    }

    public function testNewLabelsExistInFrenchAndEnglish(): void
    {
        $xml = simplexml_load_string(self::read('mod/COMSPEC_Lobby/Sources/addons/main/stringtable.xml'));
        self::assertNotFalse($xml);
        foreach (['PortraitNone', 'PortraitHintNone', 'PortraitHintRetry'] as $k) {
            $key = $xml->xpath('//Key[@ID="STR_COMSPEC_LOBBY_' . $k . '"]');
            self::assertCount(1, $key, $k);
            self::assertNotSame('', trim((string) $key[0]->French), $k);
            self::assertNotSame('', trim((string) $key[0]->English), $k);
            self::assertNotSame(trim((string) $key[0]->French), trim((string) $key[0]->English), $k);
        }
        // Le fichier est celui que produit l'outil du mod.
        $tool = self::read('mod/COMSPEC_Lobby/tools/make_stringtable.py');
        foreach (['"PortraitNone"', '"PortraitHintNone"', '"PortraitHintRetry"'] as $k) {
            self::assertStringContainsString($k, $tool);
        }
    }
}
