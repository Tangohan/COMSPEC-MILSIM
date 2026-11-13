<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Droits de la salle TOC (ATAK natif 4.12.0, feuille de route TOC n° 11, 14, 15) : table des rôles et des droits de
 * fn_tocAccess.sqf rejouée ici (mêmes tables JSON, même calcul), règle par défaut validée par TTRD (seuls le chef de
 * poste et l'administrateur Zeus changent les écrans et les salles), verrou, contrôle côté serveur.
 * Lecture du code seulement : rien de tout cela n'a tourné en jeu.
 */
final class AtakTocAccessMatrixTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';
    private const MAIN = self::ROOT . '/mod/COMSPEC_ATAK_Native/Sources/addons/main/';
    private const TOC = self::MAIN . 'functions/toc/';
    private const NEW_FILES = ['fn_tocAccess.sqf', 'fn_tocJournal.sqf', 'fn_tocStation.sqf', 'fn_tocHandover.sqf', 'fn_tocDiag.sqf', 'fn_tocApi.sqf', 'fn_tocPresets.sqf'];

    private static function read(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents($path));
    }

    /** Table JSON du code SQF : « // ---- TABLE _nom » … « private _nom = [ … ]; » … « // ---- FIN ». */
    public static function table(string $file, string $name): array
    {
        $src = self::read(self::TOC . $file);
        self::assertSame(1, preg_match('/\/\/ ---- TABLE ' . preg_quote($name, '/') . ' .*?private ' . preg_quote($name, '/') . ' = (\[.*?\]);\n\s*\/\/ ---- FIN/s', $src, $m), $name);
        $data = json_decode($m[1], true);
        self::assertIsArray($data, $name . ' : JSON strict attendu (' . json_last_error_msg() . ')');

        return $data;
    }

    /** Même calcul que _need (fn_tocAccess.sqf). */
    private static function need(array $matrix, string $perm, string $mode): int
    {
        foreach ($matrix as $row) {
            if ($row[0] === $perm) {
                $i = array_search($mode, ['roles', 'open', 'admin'], true);

                return $row[max(2 + ($i === false ? -1 : $i), 2)];
            }
        }

        return 99;
    }

    /** Même calcul que _allowed (fn_tocAccess.sqf). */
    private static function allowed(array $matrix, int $level, string $perm, string $mode, bool $locked = false, bool $holder = false): bool
    {
        foreach ($matrix as $row) {
            if ($row[0] === $perm) {
                if ($level < self::need($matrix, $perm, $mode)) {
                    return false;
                }

                return !($locked && $row[5] && !$holder && $level < 3);
            }
        }

        return false;
    }

    public function testRolesAreOperatorChiefAndZeusAdministrator(): void
    {
        $roles = self::table('fn_tocAccess.sqf', '_roles');
        self::assertSame(['none', 'operator', 'chief', 'admin'], array_column($roles, 0));
        self::assertSame([0, 1, 2, 3], array_column($roles, 1), 'le niveau est le rang dans la table (le code lit _roles select niveau)');
        self::assertSame(['Sans accès', 'Opérateur', 'Chef de poste', 'Administrateur Zeus'], array_column($roles, 2));
    }

    /** @return array<string, array{string, string, int, bool, bool, bool}> */
    public static function cases(): array
    {
        // [droit, mode, niveau, verrouillée, auteur du verrou, attendu]
        return [
            'défaut : un opérateur lit' => ['view', 'roles', 1, false, false, true],
            'défaut : un opérateur tient un poste' => ['station', 'roles', 1, false, false, true],
            'défaut : un opérateur écrit dans les registres' => ['entry', 'roles', 1, false, false, true],
            'défaut : un opérateur ne change pas un écran' => ['screens', 'roles', 1, false, false, false],
            'défaut : un opérateur ne règle pas la salle' => ['rooms', 'roles', 1, false, false, false],
            'défaut : le chef de poste change un écran' => ['screens', 'roles', 2, false, false, true],
            'défaut : le chef de poste règle la salle' => ['rooms', 'roles', 2, false, false, true],
            'défaut : le Zeus change un écran' => ['screens', 'roles', 3, false, false, true],
            'défaut : le chef de poste verrouille' => ['lock', 'roles', 2, false, false, true],
            'défaut : un opérateur ne verrouille pas' => ['lock', 'roles', 1, false, false, false],
            'défaut : un opérateur ne lit pas le journal' => ['journal', 'roles', 1, false, false, false],
            'défaut : le chef de poste lit le journal' => ['journal', 'roles', 2, false, false, true],
            'défaut : seul le Zeus pose une salle complète' => ['build', 'roles', 2, false, false, false],
            'défaut : seul le Zeus donne un rôle' => ['grant', 'roles', 2, false, false, false],
            'autre camp : rien' => ['view', 'roles', 0, false, false, false],
            'ouvert : un opérateur change un écran, comme avant' => ['screens', 'open', 1, false, false, true],
            'ouvert : un opérateur ne verrouille toujours pas' => ['lock', 'open', 1, false, false, false],
            'administrateurs : le chef de poste ne change plus un écran' => ['screens', 'admin', 2, false, false, false],
            'administrateurs : le Zeus oui' => ['screens', 'admin', 3, false, false, true],
            'verrou : un autre chef de poste est gelé' => ['screens', 'roles', 2, true, false, false],
            'verrou : son auteur garde la main' => ['screens', 'roles', 2, true, true, true],
            'verrou : le Zeus passe toujours' => ['rooms', 'roles', 3, true, false, true],
            'verrou : lire reste permis' => ['view', 'roles', 1, true, false, true],
            'verrou : un chef de poste peut le lever' => ['lock', 'roles', 2, true, false, true],
            'verrou : ouvert, un opérateur est gelé aussi' => ['screens', 'open', 1, true, false, false],
            'mode inconnu : comme le défaut' => ['screens', 'nimporte', 1, false, false, false],
            'droit inconnu : refusé' => ['nimporte', 'roles', 3, false, false, false],
        ];
    }

    #[DataProvider('cases')]
    public function testPermissionMatrix(string $perm, string $mode, int $level, bool $locked, bool $holder, bool $expected): void
    {
        self::assertSame($expected, self::allowed(self::table('fn_tocAccess.sqf', '_matrix'), $level, $perm, $mode, $locked, $holder));
    }

    public function testMatrixIsCompleteAndTheZeusIsNeverLockedOut(): void
    {
        $matrix = self::table('fn_tocAccess.sqf', '_matrix');
        self::assertSame(['view', 'station', 'entry', 'handover', 'screens', 'rooms', 'presets', 'lock', 'journal', 'diag', 'build', 'grant'], array_column($matrix, 0));
        foreach ($matrix as [$perm, $label, $roles, $open, $admin, $frozen]) {
            self::assertNotSame('', $label, $perm);
            foreach ([$roles, $open, $admin] as $lvl) {
                self::assertContains($lvl, [1, 2, 3], $perm);
            }
            self::assertIsBool($frozen, $perm);
            // Risque n° 4 de la feuille de route : le Zeus et l'administrateur passent toujours, verrou compris.
            foreach (['roles', 'open', 'admin'] as $mode) {
                self::assertTrue(self::allowed($matrix, 3, $perm, $mode, true, false), "{$perm} / {$mode}");
            }
            // Le mode « ouvert » n'est jamais plus strict que « rôles », lui-même jamais plus strict que « administrateurs ».
            self::assertLessThanOrEqual($roles, $open, $perm);
            self::assertLessThanOrEqual($admin, $roles, $perm);
        }
        // Le code SQF fait bien ce calcul (mêmes opérandes).
        $src = self::read(self::TOC . 'fn_tocAccess.sqf');
        self::assertStringContainsString('(_matrix select _i) select (2 + (["roles", "open", "admin"] find _m) max 2)', $src);
        self::assertStringContainsString('if (_lvl < ([_perm, _m] call _need)) exitWith { false };', $src);
        self::assertStringContainsString('!(_locked && {(_matrix select _i) select 5} && {!_holder} && {_lvl < 3})', $src);
        self::assertStringContainsString('getVariable ["comspec_atak_native_toc_access_mode", "roles"]', $src, 'défaut validé par TTRD : rôles');
    }

    public function testEveryHallAndScreenActionIsGatedWithAKnownRight(): void
    {
        $perms = array_column(self::table('fn_tocAccess.sqf', '_matrix'), 0);
        $hallActs = self::table('fn_tocAccess.sqf', '_hallActs');
        $hall = self::read(self::TOC . 'fn_tocHall.sqf');
        $i = strpos($hall, '    case "do": {');
        self::assertNotFalse($i);
        preg_match_all('/^            case "(\w+)": /m', substr($hall, $i), $m);
        self::assertEqualsCanonicalizing($m[1], array_column($hallActs, 0), 'chaque action de fn_tocHall « do » a son droit');
        foreach ($hallActs as [$act, $perm, $how]) {
            self::assertContains($perm, $perms, $act);
            self::assertContains($how, ['none', 'id', 'id0', 'obj', 'obj0'], $act);
        }
        $by = array_column($hallActs, 1, 0);
        foreach (['create', 'rename', 'link', 'unlink', 'linkNear', 'sceneSave', 'sceneDel'] as $act) {
            self::assertSame('rooms', $by[$act], $act);
        }
        foreach (['power', 'powerAll', 'sceneApply'] as $act) {
            self::assertSame('screens', $by[$act], $act);
        }
        self::assertSame([['view', 'screens'], ['remove', 'rooms']], self::table('fn_tocAccess.sqf', '_screenActs'));
    }

    public function testServerNeverTrustsWhatTheClientAnnounces(): void
    {
        $src = self::read(self::TOC . 'fn_tocAccess.sqf');
        // L'émetteur vient du moteur, son camp et son identifiant sont lus sur le serveur.
        self::assertStringContainsString('private _o = remoteExecutedOwner;', $src);
        self::assertStringContainsString('allPlayers findIf { (owner _x) isEqualTo _o }', $src);
        self::assertStringContainsString('private _uid = getPlayerUID _u;', $src);
        self::assertStringContainsString('private _sd = str side group _u;', $src);
        self::assertStringContainsString('[_u] call comspec_atak_native_fnc_adminAllowed', $src);
        // L'état qui fait foi n'est pas dans une variable qu'un client peut publier.
        self::assertStringContainsString('localNamespace getVariable ["COMSPEC_ATAK_TocAccessSrv"', $src);
        self::assertStringContainsString('localNamespace setVariable ["COMSPEC_ATAK_TocAccessSrv", _s];', $src);
        foreach (['fn_tocJournal.sqf' => 'COMSPEC_ATAK_TocJournalSrv', 'fn_tocStation.sqf' => 'COMSPEC_ATAK_TocStationsSrv', 'fn_tocHandover.sqf' => 'COMSPEC_ATAK_TocHandoverSrv'] as $f => $var) {
            self::assertStringContainsString('localNamespace setVariable ["' . $var . '"', self::read(self::TOC . $f), $f);
        }
        // Demandes des clients : par remoteExecCall (l'émetteur est connu), jamais par un événement CBA.
        foreach (self::NEW_FILES as $f) {
            $s = self::read(self::TOC . $f);
            if ($f !== 'fn_tocAccess.sqf') {
                self::assertStringNotContainsString('CBA_fnc_serverEvent', $s, $f);
            }
            // Aucun mode ne lit le camp ou l'UID dans les arguments d'une demande.
            self::assertDoesNotMatchRegularExpression('/_a params \[[^\]]*"_(uid|side|sd)"[^\]]*\];\s*\n\s*if \(!isServer\) exitWith \{ \[[^\n]*remoteExecCall/', $s, $f);
        }
        // Modes internes : fermés à un appel distant direct.
        foreach (['fn_tocJournal.sqf', 'fn_tocStation.sqf', 'fn_tocHandover.sqf', 'fn_tocPresets.sqf', 'fn_tocAccess.sqf'] as $f) {
            self::assertStringContainsString('private _inside = !isRemoteExecuted || {!isNil "_tocTrusted"};', self::read(self::TOC . $f), $f);
        }
        self::assertStringContainsString('if (!isServer || {!_inside}) exitWith { false };', self::read(self::TOC . 'fn_tocStation.sqf'));
        // Réponses du serveur : un autre joueur ne peut pas les imiter.
        foreach (['fn_tocJournal.sqf', 'fn_tocHandover.sqf', 'fn_tocDiag.sqf', 'fn_tocPresets.sqf'] as $f) {
            self::assertStringContainsString('{isMultiplayer && {isRemoteExecuted} && {!(remoteExecutedOwner in [0, 2])}}', self::read(self::TOC . $f), $f);
        }
    }

    public function testHooksInTheExistingHandlersAreOneLineEach(): void
    {
        $hall = self::read(self::TOC . 'fn_tocHall.sqf');
        self::assertStringContainsString('if (!isServer) exitWith { ["send", ["hall", _a]] call comspec_atak_native_fnc_tocAccess; true };', $hall);
        self::assertStringContainsString('if (["legacy", ["hall", _this]] call comspec_atak_native_fnc_tocAccess) then { ["do", _this] call comspec_atak_native_fnc_tocHall; };', $hall);
        $scr = self::read(self::TOC . 'fn_tocScreen.sqf');
        self::assertStringContainsString('exitWith { ["send", ["screen", ["view", _a]]] call comspec_atak_native_fnc_tocAccess; true };', $scr);
        self::assertStringContainsString('exitWith { ["send", ["screen", ["remove", _a]]] call comspec_atak_native_fnc_tocAccess; true };', $scr);
        self::assertStringContainsString('if (["legacy", ["screen", _this]] call comspec_atak_native_fnc_tocAccess) then { _this call comspec_atak_native_fnc_tocScreen; };', $scr);
        // Serveur plus ancien (sans le contrôle) : le client garde l'ancien événement.
        $acc = self::read(self::TOC . 'fn_tocAccess.sqf');
        self::assertStringContainsString('missionNamespace setVariable ["COMSPEC_ATAK_TocAccessOn", true, true];', $acc);
        self::assertStringContainsString('if (missionNamespace getVariable ["COMSPEC_ATAK_TocAccessOn", false]) exitWith { ["gate", [_kind, _load]] remoteExecCall ["comspec_atak_native_fnc_tocAccess", 2]; true };', $acc);
        // La porte ne laisse passer que les modes de la table.
        self::assertStringContainsString('if (_perm isEqualTo "" || {isNull _o}) exitWith { false };', $acc);
        self::assertStringContainsString('["init"] call comspec_atak_native_fnc_tocAccess;', self::read(self::MAIN . 'XEH_postInitServer.sqf'));
        self::assertStringContainsString('["initClient"] call comspec_atak_native_fnc_tocAccess;', self::read(self::MAIN . 'XEH_postInitClient.sqf'));
        $cfg = self::read(self::MAIN . 'config.cpp');
        foreach (['tocAccess', 'tocJournal', 'tocStation', 'tocHandover', 'tocDiag', 'tocApi', 'tocPresets'] as $fn) {
            self::assertStringContainsString("class {$fn} {};", $cfg);
        }
    }

    public function testNoGamePopupNoRadioGuardNoAthenaInTheNewFiles(): void
    {
        foreach (array_merge(self::NEW_FILES, ['fn_tocPersist.sqf', 'fn_tocTheme.sqf']) as $f) {
            $s = self::read(self::TOC . $f);
            self::assertDoesNotMatchRegularExpression('/\b(hint|hintSilent|hintC|systemChat|titleText|cutText|BIS_fnc_showNotification|zen_common_fnc_showMessage|comspec_atak_native_fnc_notify|comspec_atak_native_fnc_toast)\b/', $s, $f);
            self::assertStringNotContainsString('radioGuard', $s, $f);
            // Aucune requête vers Athena ni vers le site : tout est interne au serveur du jeu.
            self::assertDoesNotMatchRegularExpression('/comspec_overwatch_connect_fnc_(?!log)\w+|fnc_apiCall|fnc_http|athena-comspec|https?:\/\//i', $s, $f);
        }
        // Seul appel à la DLL : le presse-papiers local (copyToClipboard est sans effet en multijoueur).
        preg_match_all('/\["(\w+)"(?:, \[[^\n]*?\])?\] call comspec_atak_native_fnc_extensionCall/', self::read(self::TOC . 'fn_tocDiag.sqf'), $m);
        self::assertEqualsCanonicalizing(['ClipBegin', 'ClipAppend', 'ClipCommit'], array_unique($m[1]));
        foreach (array_diff(self::NEW_FILES, ['fn_tocDiag.sqf']) as $f) {
            self::assertStringNotContainsString('extensionCall', self::read(self::TOC . $f), $f);
        }
    }

    public function testEverySettingIsExplainedWithAnExampleInBothLanguages(): void
    {
        $pre = self::read(self::MAIN . 'XEH_preInit.sqf');
        $en = self::read(self::MAIN . 'i18n/en.txt');
        $xx = self::read(self::ROOT . '/mod/COMSPEC_ATAK_Native/localisation/template/xx.txt');
        $settings = ['toc_access_mode', 'toc_chief_rank', 'toc_chief_uids', 'toc_journal_max', 'toc_journal_log', 'toc_theme'];
        foreach ($settings as $s) {
            self::assertSame(1, preg_match('/\["comspec_atak_native_' . $s . '", "\w+",\n\s+\["([^"]+)", "((?:[^"]|"")+)"\],\n\s+_tocHeld, /', $pre, $m), $s);
            [, $title, $help] = $m;
            self::assertMatchesRegularExpression('/Exemple : .{15,}/u', $help, "{$s} : explication avec un exemple");
            self::assertDoesNotMatchRegularExpression('/comspec_atak|fnc_|CBA_|remoteExec/i', $title . $help, "{$s} : pas de nom de variable ni de fonction");
            foreach ([$title, $help] as $t) {
                self::assertStringContainsString('[' . '"' . $t . '", "', $en, "EN manquant : {$t}");
                self::assertStringContainsString('[' . '"' . $t . '", "", "', $xx, "modèle xx manquant : {$t}");
            }
        }
        // Les réglages du serveur sont imposés à tous (1) ; le thème est propre à chaque joueur (0).
        self::assertSame(5, preg_match_all('/_tocHeld, .*, 1\] call comspec_atak_native_fnc_addSetting;/', $pre));
        self::assertSame(1, preg_match_all('/_tocHeld, .*, 0\] call comspec_atak_native_fnc_addSetting;/', $pre));
        $doc = self::read(self::ROOT . '/docs/TOC-ACCESS.md');
        foreach (array_column(self::table('fn_tocAccess.sqf', '_matrix'), 0) as $perm) {
            self::assertStringContainsString('| `' . $perm . '` |', $doc, "droit {$perm} absent de docs/TOC-ACCESS.md");
        }
        foreach (['Droits de la salle TOC', 'Grade minimal du chef de quart', 'Chefs de poste désignés', 'Lignes gardées au journal du TOC', 'Journal du TOC dans le journal COMSPEC', 'Thème des écrans TOC'] as $title) {
            self::assertStringContainsString($title, $doc);
            self::assertStringContainsString('["' . $title . '", "', $pre);
        }
    }

    public function testEveryDisplayedTextOfTheLotHasEnglish(): void
    {
        $en = self::read(self::MAIN . 'i18n/en.txt');
        $texts = array_merge(
            array_column(self::table('fn_tocAccess.sqf', '_roles'), 2),
            array_column(self::table('fn_tocAccess.sqf', '_matrix'), 1),
            array_column(self::table('fn_tocJournal.sqf', '_acts'), 1),
            array_column(self::table('fn_tocStation.sqf', '_kinds'), 1),
        );
        foreach (self::NEW_FILES as $f) {
            $s = self::read(self::TOC . $f);
            // Retours au joueur, textes passés à la traduction, noms d'actions ACE et Zeus.
            preg_match_all('/\["feedback", \[_u, (?:true|false), "([^"]+)"/', $s, $m);
            $texts = array_merge($texts, $m[1]);
            preg_match_all('/"((?:Refusé|Poste|Relève|Préréglage|Salle)[^"]*)"/u', $s, $m);
            $texts = array_merge($texts, $m[1]);
            preg_match_all('/\("([^"]+)" call (?:_T|comspec_atak_native_fnc_tr)\)/', $s, $m);
            $texts = array_merge($texts, $m[1]);
            if (in_array($f, ['fn_tocDiag.sqf', 'fn_tocStation.sqf'], true)) {
                preg_match_all('/\["(?:COMSPEC_ATAK_TocDesk|comspec_atak_toc\w*|Leave|Offer|Relief|Journal|Diag)", "([^"]+)"/', $s, $m);
                $texts = array_merge($texts, $m[1]);
            }
        }
        $texts = array_values(array_unique($texts));
        self::assertGreaterThan(90, count($texts));
        foreach ($texts as $t) {
            self::assertStringContainsString('["' . $t . '", "', $en, "« {$t} » sans anglais dans i18n/en.txt");
        }
    }
}
