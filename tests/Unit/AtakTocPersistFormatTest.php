<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Sauvegarde versionnée de la salle TOC (ATAK natif 4.10.0, feuille de route TOC n° 10) : format 3, rangs publiés du
 * format 2 inchangés, nouveaux champs à la fin, migration d'une sauvegarde plus ancienne rejouée ici sur un exemple
 * (même table _fields, même calcul que « migrate » de fn_tocPersist.sqf), copies de secours.
 * Lecture du code seulement : rien de tout cela n'a tourné en jeu.
 */
final class AtakTocPersistFormatTest extends TestCase
{
    private const TOC = __DIR__ . '/../../mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/toc/';
    private const FORMAT = 3;

    private static function src(string $f = 'fn_tocPersist.sqf'): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(self::TOC . $f));
    }

    /** Même calcul que _migrate (fn_tocPersist.sqf). */
    private static function migrate(array $fields, mixed $d): array
    {
        if (!is_array($d) || count($d) < 4) {
            return [];
        }
        $from = is_int($d[0]) || is_float($d[0]) ? $d[0] : 1;
        if ($from > self::FORMAT) {
            return $d;
        }
        $out = $d;
        foreach ($fields as [$i, , , $def]) {
            if (count($out) <= $i || $out[$i] === null) {
                $out[$i] = $def;
            }
        }
        $out[0] = self::FORMAT;

        return $out;
    }

    public function testPublishedRanksNeverMoveAndNewFieldsComeLast(): void
    {
        $fields = AtakTocAccessMatrixTest::table('fn_tocPersist.sqf', '_fields');
        self::assertSame(range(0, count($fields) - 1), array_column($fields, 0), 'rangs sans trou, dans l\'ordre');
        // Format 2 publié (4.4.0 à 4.9.x) : [2, date, écrans, flux, poste, salles, [marqueurs, plans]].
        self::assertSame(['format', 'date', 'screens', 'feed', 'room', 'halls', 'draw'], array_slice(array_column($fields, 1), 0, 7));
        self::assertSame(['journal', 'stations', 'handover', 'meta'], array_slice(array_column($fields, 1), 7));
        // Le format d'arrivée ne décroît jamais : un champ nouveau est toujours après les anciens.
        $since = array_column($fields, 2);
        $sorted = $since;
        sort($sorted);
        self::assertSame($sorted, $since);
        self::assertSame(self::FORMAT, max($since));
        $src = self::src();
        self::assertStringContainsString('private _format = 3;', $src);
        // L'écriture suit l'ordre de la table.
        self::assertSame(1, preg_match('/private _save = \[_format max _fmtPrev, systemTimeUTC, _scr, _ev, \["dump"\] call comspec_atak_native_fnc_tocRoom, \["dump"\] call comspec_atak_native_fnc_tocHall, \[_mk, _plans\],\s+\["dump"\] call comspec_atak_native_fnc_tocJournal, \["dump"\] call comspec_atak_native_fnc_tocStation, \["dump"\] call comspec_atak_native_fnc_tocHandover,\s+\[missionNamespace getVariable \["COMSPEC_ATAK_NativeVersion", ""\], worldName, missionName, _n\]\];/', $src));
        // La lecture garde les rangs publiés ; fn_tocHall lit toujours les salles au rang 5.
        self::assertStringContainsString('_data params ["", "", ["_scr", []], ["_ev", []], ["_room", []], ["_halls", []], ["_draw", []]];', $src);
        self::assertStringContainsString('(_d param [5, []]) findIf', self::src('fn_tocHall.sqf'));
        self::assertStringContainsString('["load", _data param [7, []]] call comspec_atak_native_fnc_tocJournal;', $src);
        self::assertStringContainsString('["load", _data param [8, []]] call comspec_atak_native_fnc_tocStation;', $src);
        self::assertStringContainsString('["load", _data param [9, []]] call comspec_atak_native_fnc_tocHandover;', $src);
    }

    public function testMigrationOfThePublishedFormatIsGolden(): void
    {
        $fields = AtakTocAccessMatrixTest::table('fn_tocPersist.sqf', '_fields');
        $screen = ['Land_TripodScreen_01_large_F', [1520.5, 4310.2, 12.1], [0, 1, 0], [0, 0, 1], ['MUR', 'WEST', 0, 'SPLIT', 0, [], true, true, 0, ''], [], [], [], ['S1', 'H1', false, [], [], []]];
        $room = [['WEST', [4, [], [], [['21:04:10', 'INFO', 'TOC · Dupont', 'Convoi parti']], [], [], [], [], []]]];
        $halls = [['H1', 'PC AVANT', 'WEST', [], []]];
        $v2 = [2, [2026, 11, 10, 21, 4, 10, 0], [$screen], [['WEST', []]], $room, $halls, [[], []]];
        $v3 = self::migrate($fields, $v2);
        // Exemple attendu, écrit en entier : les sept premiers rangs sont ceux du format 2, valeur pour valeur.
        self::assertSame([3, [2026, 11, 10, 21, 4, 10, 0], [$screen], [['WEST', []]], $room, $halls, [[], []], [], [], [], []], $v3);
        self::assertSame(array_slice($v2, 1), array_slice($v3, 1, 6));
        // Migrer deux fois ne change rien.
        self::assertSame($v3, self::migrate($fields, $v3));
        // Avant la 4.4.0 : seuls les écrans et le flux.
        self::assertSame([3, [2026, 10, 1, 0, 0, 0, 0], [$screen], [], [], [], [], [], [], [], []], self::migrate($fields, [1, [2026, 10, 1, 0, 0, 0, 0], [$screen], []]));
        // Sauvegarde d'un mod plus récent : rendue telle quelle, champs inconnus compris.
        $v4 = [4, [], [], [], [], [], [], [], [], [], [], ['champ futur']];
        self::assertSame($v4, self::migrate($fields, $v4));
        // Profil abîmé : rien n'est repris, rien ne plante.
        self::assertSame([], self::migrate($fields, 'abîmé'));
        self::assertSame([], self::migrate($fields, [2, []]));
        // Le code SQF fait ce calcul.
        $src = self::src();
        self::assertStringContainsString('if !(_d isEqualType [] && {(count _d) >= 4}) exitWith { [] };', $src);
        self::assertStringContainsString('if (_from > _format) exitWith { +_d };', $src);
        self::assertStringContainsString('if ((count _out) <= _i || {isNil { _out select _i }}) then { _out set [_i, +_def]; };', $src);
        self::assertStringContainsString('_out set [0, _format];', $src);
        self::assertStringContainsString('private _data = [_raw] call _migrate;', $src);
    }

    public function testBackupsExportAndNoDowngradeOfANewerSave(): void
    {
        $src = self::src();
        // Copie de secours au lancement, et une fois la dernière sauvegarde d'avant le format 3 (risque n° 5).
        self::assertStringContainsString('profileNamespace setVariable [_key + "_bak", +_raw];', $src);
        self::assertStringContainsString('profileNamespace setVariable [_key + "_v2", +_raw];', $src);
        self::assertLessThan(strpos($src, 'private _data = [_raw] call _migrate;'), strpos($src, 'profileNamespace setVariable [_key + "_bak", +_raw];'), 'la copie est faite avant toute lecture');
        // Champs inconnus d'un format plus récent recopiés, numéro de format jamais abaissé.
        self::assertStringContainsString('profileNamespace setVariable [_key, _save + (_prev select [count _fields])];', $src);
        self::assertStringContainsString('_format max _fmtPrev', $src);
        // Export et import en texte, retour à la copie de secours : serveur seulement, jamais par un appel distant.
        self::assertStringContainsString('case "export": { "COMSPEC-TOC-SAVE:3:" + ', $src);
        self::assertStringContainsString('private _d = [parseSimpleArray _raw] call _migrate;', $src);
        self::assertStringContainsString('if (_mode in ["clear", "import", "rollback", "export"] && {isRemoteExecuted} && {isMultiplayer} && {isNil "_tocTrusted"}) exitWith { false };', $src);
        self::assertStringNotContainsString('compile', $src, 'un texte importé n\'est jamais exécuté');
        // Sauvegarde importée : plus rien n'est écrit par-dessus avant le prochain lancement.
        self::assertStringContainsString('if (missionNamespace getVariable ["COMSPEC_ATAK_TocSaveFrozen", false]) exitWith {};', $src);
        // Plafond de taille : 200 lignes de journal par camp dans le profil.
        self::assertStringContainsString('_y select [0 max ((count _y) - 200)]', self::src('fn_tocJournal.sqf'));
        self::assertStringContainsString('if ((count _mine) > 50) then', self::src('fn_tocHandover.sqf'));
    }
}
