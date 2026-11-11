<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Journal des actions du TOC, postes nommés et relève (ATAK natif 4.10.0, feuille de route TOC n° 13, 16, 17) :
 * tampon en anneau rejoué ici, table des actions, résumé de relève rejoué sur un état de salle d'exemple.
 * Lecture du code seulement : rien de tout cela n'a tourné en jeu.
 */
final class AtakTocJournalTest extends TestCase
{
    private const TOC = __DIR__ . '/../../mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/toc/';

    private static function src(string $f): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(self::TOC . $f));
    }

    /** Même calcul que _push (fn_tocJournal.sqf). */
    private static function push(array $buf, array $line, int $size): array
    {
        $buf[] = $line;
        if (count($buf) > $size) {
            array_splice($buf, 0, count($buf) - $size);
        }

        return $buf;
    }

    public function testRingBufferKeepsTheMostRecentLines(): void
    {
        $buf = [];
        for ($n = 1; $n <= 620; $n++) {
            $buf = self::push($buf, [$n, '21:00:00', 0, 'WEST', 'H1', 'Dupont', 'chief', 'lock.on', '', true], 500);
            self::assertLessThanOrEqual(500, count($buf));
        }
        self::assertCount(500, $buf);
        self::assertSame(121, $buf[0][0], 'les 120 plus anciennes sont sorties');
        self::assertSame(620, $buf[499][0], 'la plus récente est à la fin');
        self::assertSame(range(121, 620), array_column($buf, 0), 'ordre gardé');
        // Taille réduite en cours de partie (réglage) : l'anneau se resserre au prochain ajout.
        $buf = self::push($buf, [621], 50);
        self::assertCount(50, $buf);
        self::assertSame(572, $buf[0][0]);
        $src = self::src('fn_tocJournal.sqf');
        self::assertStringContainsString("_buf pushBack _line;\n    if ((count _buf) > _size) then { _buf deleteRange [0, (count _buf) - _size]; };", $src);
        self::assertStringContainsString('(round (missionNamespace getVariable ["comspec_atak_native_toc_journal_max", 500])) max 50 min 2000', $src);
    }

    public function testJournalIsServerSideOnDemandAndWrittenOnceToTheComspecLog(): void
    {
        $src = self::src('fn_tocJournal.sqf');
        // Ligne : [n°, heure ZULU, serverTime, camp, salle, auteur, rôle, action, détail, accordée].
        self::assertStringContainsString('private _l = [_n, ["zulu"] call comspec_atak_native_fnc_tocRoom, serverTime, [_sd, ""] select (_sd isEqualTo "ALL"), _hall select [0, 40], _who select [0, 48], _role, _act select [0, 32], _det select [0, 160], _ok];', $src);
        // Aucune variable publique : le journal ne part qu'à la demande, à qui a le droit, pour son camp.
        self::assertStringNotContainsString(', true];', $src, 'aucun setVariable public');
        self::assertStringNotContainsString('publicVariable', $src);
        self::assertStringContainsString('if !(["check", [_u, "journal", "", "", ""]] call comspec_atak_native_fnc_tocAccess) exitWith { false };', $src);
        self::assertStringContainsString('if (_lvl < 3 || {!(_sd in (_sides + [""]))}) then { _sd = str side group _u; };', $src);
        self::assertStringContainsString('["recv", [_sd, _l]] remoteExecCall ["comspec_atak_native_fnc_tocJournal", owner _u];', $src);
        // Auteur d'un appel distant : l'émetteur réel, jamais celui annoncé.
        self::assertStringContainsString('if (!_inside) then { _who = (["actor", objNull] call comspec_atak_native_fnc_tocAccess) select 0; _sd = ""; };', $src);
        // Une ligne, une fois, dans le journal COMSPEC (module TOC, recopié dans le .log de session).
        self::assertSame(1, substr_count($src, 'call comspec_atak_native_fnc_log;'));
        self::assertStringContainsString('if (missionNamespace getVariable ["comspec_atak_native_toc_journal_log", true]) then {', $src);
        self::assertStringContainsString('"TOC"', $src);
        self::assertMatchesRegularExpression('/in \[[^\]]*"TOC"[^\]]*\]/', (string) file_get_contents(self::TOC . '../core/fn_log.sqf'), 'le module TOC est recopié dans le journal COMSPEC');
        // Lecture par le Zeus et le chef de poste : action Zeus, action ACE sur soi, fenêtre à copier.
        $diag = self::src('fn_tocDiag.sqf');
        self::assertStringContainsString('"comspec_atak_toc_journal", "Journal des actions du TOC"', $diag);
        self::assertStringContainsString('["Journal", "Journal des actions du TOC", { ["journal"] call comspec_atak_native_fnc_tocDiag; }] call _add;', self::src('fn_tocStation.sqf'));
        self::assertStringContainsString('["COMSPEC_RscEditMulti"', $diag);
    }

    public function testEveryJournaledActionHasALabel(): void
    {
        $acts = array_column(AtakTocAccessMatrixTest::table('fn_tocJournal.sqf', '_acts'), 0);
        self::assertSame($acts, array_unique($acts), 'aucune action en double');
        foreach (array_column(AtakTocAccessMatrixTest::table('fn_tocAccess.sqf', '_hallActs'), 0) as $a) {
            self::assertContains('hall.' . $a, $acts);
        }
        foreach (array_column(AtakTocAccessMatrixTest::table('fn_tocAccess.sqf', '_screenActs'), 0) as $a) {
            self::assertContains('screen.' . $a, $acts);
        }
        foreach (['fn_tocAccess.sqf', 'fn_tocStation.sqf', 'fn_tocHandover.sqf', 'fn_tocPresets.sqf', 'fn_tocDiag.sqf', 'fn_tocPersist.sqf', 'fn_tocApi.sqf'] as $f) {
            preg_match_all('/"((?:station|handover|preset|lock|access|diag|save|journal)\.[a-zA-Z]+)"/', self::src($f), $m);
            foreach (array_unique($m[1]) as $a) {
                self::assertContains($a, $acts, "{$f} : action {$a} sans libellé");
            }
        }
        foreach (['api', 'legacy'] as $a) {
            self::assertContains($a, $acts);
        }
    }

    public function testStationsKindsRanksAndOneChiefPerRoom(): void
    {
        $kinds = AtakTocAccessMatrixTest::table('fn_tocStation.sqf', '_kinds');
        self::assertSame(['CDQ', 'OPS', 'RENS', 'FEUX', 'LOG', 'TRANS'], array_column($kinds, 0));
        self::assertSame([true, false, false, false, false, false], array_column($kinds, 2), 'un seul rôle de poste donne le rôle « chef de poste »');
        self::assertSame(['PRIVATE', 'CORPORAL', 'SERGEANT', 'LIEUTENANT', 'CAPTAIN', 'MAJOR', 'COLONEL'], AtakTocAccessMatrixTest::table('fn_tocStation.sqf', '_ranks'));
        $src = self::src('fn_tocStation.sqf');
        // Refus dits au joueur et écrits au journal ; poste gardé 5 minutes après une déconnexion ; verrous levés.
        foreach (['approchez-vous du PC du poste', 'ce poste est tenu, demandez la relève', 'cette salle a déjà un chef de quart, demandez la relève', 'ce poste est gardé à son opérateur pendant 5 minutes'] as $t) {
            self::assertStringContainsString('"Refusé : ' . $t . '"', $src);
        }
        self::assertStringContainsString('["add", [_rec select 2, _hall, _u, "station.take", _kind, false]] call comspec_atak_native_fnc_tocJournal;', $src);
        self::assertStringContainsString('_rec set [10, [0, serverTime + 300] select (_keepFor isNotEqualTo "")];', $src);
        self::assertStringContainsString('addMissionEventHandler ["PlayerDisconnected"', $src);
        self::assertStringContainsString('["unlockBy", [_uid, "déconnexion"]] call _acc;', $src);
        // Le tenant est celui que le serveur a retrouvé, jamais un nom ou un UID envoyé par le client.
        self::assertStringContainsString('(["actor", _claimed] call _acc) params ["_u", "_tr"];', $src);
        self::assertStringContainsString('_rec set [5, (["nameOf", _u] call comspec_atak_native_fnc_tocAccess)]; _rec set [6, _uid];', $src);
        // Demande de TTRD : jamais le pseudo Steam dans le TOC. Journal, postes, relèves et verrou écrivent l'indicatif
        // (résolveur commun s'il est chargé), sinon le groupe et le rang du jeu.
        foreach (['fn_tocAccess.sqf', 'fn_tocJournal.sqf', 'fn_tocStation.sqf', 'fn_tocHandover.sqf', 'fn_tocDiag.sqf', 'fn_tocPresets.sqf', 'fn_tocApi.sqf'] as $f) {
            self::assertDoesNotMatchRegularExpression('/\bname _\w+|\bprofileName\b/', self::src($f), $f);
        }
        $acc = self::src('fn_tocAccess.sqf');
        self::assertStringContainsString('if (!isNil "comspec_atak_native_fnc_tocDisplayName") then { _n = [_a] call comspec_atak_native_fnc_tocDisplayName; };', $acc);
        self::assertStringContainsString('_n = format ["%1 · %2", groupId group _a, ((units group _a) find _a) + 1];', $acc);
        // Tout s'emploie sans console : actions ACE sur soi.
        self::assertStringContainsString('["CAManBase", 1, ["ACE_SelfActions"], _root, true] call ace_interact_menu_fnc_addActionToClass;', $src);
    }

    public function testHandoverSummaryIsGoldenAndExportsAsText(): void
    {
        // État de salle d'exemple (fn_tocRoom) : révision, chronos, satellites, main courante, exécution, demandes, FLASH, postes, pertes.
        $state = [
            7,
            [['T1', 'H-HOUR', 'AT', 1320, true, 0, 0], ['T2', 'PAUSE', 'UP', 0, false, 12, 0]],
            [[], []],
            [['21:00:00', 'INFO', 'TOC', 'x']],
            [['E1', 1, 'Départ', 'ROUGE', 'Alpha', '22:10', '22:11:05'], ['E2', 2, 'Objectif saisi', 'VERT', 'Bravo', '22:40', ''], ['E3', 3, 'Repli', 'BLEU', 'Alpha', '23:30', '']],
            [['R1', '21:10', 'MEDEVAC', 'Alpha 1-1', 'uid', '123456', 'un blessé', 'PRISE', '', []], ['R2', '21:20', 'RAVITAILLEMENT', 'Bravo', 'uid', '', '', 'TERMINEE', '', []], ['R3', '21:30', 'APPUI FEU', 'Bravo', 'uid', '', '', 'RECUE', '', []]],
            ['CONTACT AU NORD', 'TOC', '21:31:00', 0],
            [],
            [['Alpha', 2, 1, 5, 0], ['Bravo', 1, 0, 0, 0]],
        ];
        $open = array_values(array_filter($state[5], static fn (array $r): bool => in_array($r[7], ['RECUE', 'PRISE'], true)));
        $todo = array_values(array_filter($state[4], static fn (array $r): bool => $r[6] === ''));
        $out = [['Demandes ouvertes : %1', [count($open)]]];
        foreach (array_slice($open, max(0, count($open) - 3)) as $r) {
            $out[] = ['  %1 · %2 · %3 · %4', [$r[1], $r[2], $r[3], $r[7]]];
        }
        $out[] = ['Pertes amies : %1 blessé(s), %2 tué(s)', [array_sum(array_column($state[8], 1)), array_sum(array_column($state[8], 2))]];
        $out[] = ["Liste d'exécution : %1 ligne(s) à venir sur %2", [count($todo), count($state[4])]];
        foreach (array_slice($todo, 0, 3) as $r) {
            $out[] = ['  %1 · %2 · %3 · %4', [$r[1], $r[3], $r[2], $r[5]]];
        }
        $out[] = ['Chronos en marche : %1', [count(array_filter($state[1], static fn (array $t): bool => $t[4]))]];
        $out[] = ['FLASH en cours : %1', [$state[6][0]]];
        self::assertSame([
            ['Demandes ouvertes : %1', [2]],
            ['  %1 · %2 · %3 · %4', ['21:10', 'MEDEVAC', 'Alpha 1-1', 'PRISE']],
            ['  %1 · %2 · %3 · %4', ['21:30', 'APPUI FEU', 'Bravo', 'RECUE']],
            ['Pertes amies : %1 blessé(s), %2 tué(s)', [3, 1]],
            ["Liste d'exécution : %1 ligne(s) à venir sur %2", [2, 3]],
            ['  %1 · %2 · %3 · %4', [2, 'VERT', 'Objectif saisi', '22:40']],
            ['  %1 · %2 · %3 · %4', [3, 'BLEU', 'Repli', '23:30']],
            ['Chronos en marche : %1', [1]],
            ['FLASH en cours : %1', ['CONTACT AU NORD']],
        ], $out);
        self::assertLessThanOrEqual(12, count($out), 'une page');
        $src = self::src('fn_tocHandover.sqf');
        // Le code SQF compose les mêmes lignes, dans le même ordre.
        $at = 0;
        foreach (['(_x param [7, ""]) in ["RECUE", "PRISE"]', '_out pushBack ["Demandes ouvertes : %1", [count _open]];', '_out pushBack ["Pertes amies : %1 blessé(s), %2 tué(s)", [_wia, _kia]];', '(_x param [6, ""]) isEqualTo ""', '_out pushBack ["Liste d\'exécution : %1 ligne(s) à venir sur %2", [count _todo, count (_st select 4)]];', '_out pushBack ["Chronos en marche : %1", [{ _x param [4, false] } count (_st select 1)]];', '_out pushBack ["FLASH en cours : %1", [(_st select 6) select 0]];', '_out select [0, 12]'] as $needle) {
            $p = strpos($src, $needle, $at);
            self::assertNotFalse($p, $needle);
            $at = $p;
        }
        // Relève : proposée par le tenant, signée par un autre, poste transmis, verrous du sortant levés, export en texte.
        self::assertStringContainsString('"Refusé : le sortant ne signe pas sa propre relève"', $src);
        self::assertStringContainsString('["assign", [_pc, _u]] call comspec_atak_native_fnc_tocStation;', $src);
        self::assertStringContainsString('["unlockBy", [_h select 13, "relève"]] call _acc;', $src);
        self::assertStringContainsString('case "export": {', $src);
        self::assertStringContainsString('joinString endl', $src);
        // L'UID du sortant ne part jamais chez un client.
        self::assertStringContainsString('apply { _x select [0, 12] }', $src);
    }
}
