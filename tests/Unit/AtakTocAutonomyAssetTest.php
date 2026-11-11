<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * ATAK natif 4.10.0, « le TOC fonctionne sans téléphone » (lot 1 de docs/audit/2026-11-toc-feuille-de-route.md).
 * Les règles pures du SQF portent leurs tables de cas entre « ---- … (JSON) » et « ---- FIN … » : elles sont rejouées
 * ici par les mêmes règles écrites en PHP (état d'une piste, fusion téléphone et jeu, plafond, intervalle du relevé,
 * validation des saisies du poste, doublons). Le reste est lu dans les sources : contrôle de l'émetteur par le
 * serveur, pertes de toute unité, aucune alerte du jeu, aucune boucle nouvelle, textes anglais. Rien n'est lancé.
 */
final class AtakTocAutonomyAssetTest extends TestCase
{
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/';
    private const TOC = self::MAIN . 'functions/toc/';
    private const NEW_FILES = ['fn_tocTracks.sqf', 'fn_tocNodeStatus.sqf', 'fn_tocEntry.sqf', 'fn_tocInbox.sqf', 'fn_tocPanels.sqf'];

    private static function read(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path));
    }

    /** Table JSON d'un fichier SQF, entre « // ---- NOM » et « // ---- FIN NOM » (plusieurs « private _x = […]; » possibles). */
    private static function tables(string $src, string $name): array
    {
        $ok = preg_match('~// ---- ' . preg_quote($name, '~') . '\b[^\n]*\n(.*?)// ---- FIN ' . preg_quote($name, '~') . '~s', $src, $m);
        self::assertSame(1, $ok, 'table « ' . $name . ' » absente');
        $body = (string) preg_replace('~^\s*//[^\n]*$~m', '', $m[1]);
        $out = [];
        preg_match_all('~private (_\w+) = (\[.*?\]);\s*(?=private |\z)~s', trim($body) . "\n", $hits, PREG_SET_ORDER);
        foreach ($hits as $h) {
            $val = json_decode($h[2], true);
            self::assertIsArray($val, 'table ' . $h[1] . ' de « ' . $name . ' » : JSON illisible (' . json_last_error_msg() . ')');
            $out[$h[1]] = $val;
        }
        self::assertNotEmpty($out, 'table « ' . $name . ' » vide');

        return $out;
    }

    /** Sources SQF sans commentaires (// et bloc), pour chercher du code et non des phrases. */
    private static function code(string $src): string
    {
        $src = (string) preg_replace('~/\*.*?\*/~s', '', $src);

        return (string) preg_replace('~^\s*//[^\n]*$~m', '', $src);
    }

    // ---------------------------------------------------------------- fonctions et réglages

    public function testNewFunctionsAreDeclaredAndDocumented(): void
    {
        $cfg = self::read(self::MAIN . 'config.cpp');
        $doc = self::read('docs/TOC-DATA-API.md');
        foreach (self::NEW_FILES as $file) {
            $fn = substr($file, 3, -4);
            self::assertFileExists(dirname(__DIR__, 2) . '/' . self::TOC . $file);
            self::assertStringContainsString('class ' . $fn . ' {};', $cfg, $fn . ' absente de CfgFunctions');
            self::assertStringContainsString('`' . $file . '`', $doc, $file . ' absent de docs/TOC-DATA-API.md');
            self::assertStringStartsWith('/*', ltrim(self::read(self::TOC . $file)), $file . ' : en-tête de mode d’emploi absent');
        }
    }

    public function testServerSettingsExistWithTheDefaultsValidatedByTtrd(): void
    {
        $pre = self::read(self::MAIN . 'XEH_preInit.sqf');
        // Source des positions : Mixte par défaut (premier choix, rang 0).
        self::assertMatchesRegularExpression('~"comspec_atak_native_toc_source", "LIST",.*?\[\["mix", "game", "atak"\], \[[^\]]+\], 0\], 1\]~s', $pre);
        // Caméra de casque sans téléphone : permise par défaut.
        self::assertMatchesRegularExpression('~"comspec_atak_native_toc_headcam", "CHECKBOX",.*?_loc, true, 1\]~s', $pre);
        self::assertMatchesRegularExpression('~"comspec_atak_native_toc_tracks_max", "SLIDER",.*?\[20, 400, 150, 0\], 1\]~s', $pre);
        self::assertStringContainsString('"comspec_atak_native_toc_tracks_ai", "LIST"', $pre);
        // Le réglage est expliqué avec un exemple par choix.
        self::assertSame(1, preg_match('~"Source des positions du TOC", "([^"]+)"~', $pre, $m));
        self::assertGreaterThanOrEqual(3, substr_count($m[1], 'exemple'));
        $tracks = self::read(self::TOC . 'fn_tocTracks.sqf');
        self::assertStringContainsString('getVariable ["comspec_atak_native_toc_source", "mix"]', $tracks);
        self::assertStringContainsString('[["game", "GAME"], ["atak", "ATAK"], ["mix", "MIX"]]) getOrDefault [toLower _v, "MIX"]', $tracks);
    }

    // ---------------------------------------------------------------- état d'une piste

    /** fn_tocNodeStatus « node », mêmes règles. @param array<int, mixed> $a */
    private static function nodeStatus(array $a, array $game, array $atak): int
    {
        [$src, $local, $age, $rate, $beacon, $has] = $a;
        if ($rate <= 0) {
            $rate = 3;
        }
        if ($src === 'J' || $src === 'M') {
            if ($local) {
                return 0;
            }
            if ($age < 0) {
                return 4;
            }
            if ($age < $rate * $game[0] + $game[1]) {
                return 0;
            }

            return $age < $rate * $game[2] * $game[3] + $game[4] ? 1 : 2;
        }
        if ($src !== 'A') {
            return 4;
        }
        if (!$beacon) {
            return 3;
        }
        if ($local) {
            return 0;
        }
        if (!$has || $age < 0) {
            return 4;
        }
        if ($age < $rate * $atak[0] + $atak[1]) {
            return 0;
        }

        return $age < $rate * $atak[2] * $atak[3] + $atak[4] ? 1 : 2;
    }

    public function testNodeStatusTableGivesTheBestLiveSource(): void
    {
        $src = self::read(self::TOC . 'fn_tocNodeStatus.sqf');
        $rules = self::tables($src, 'TABLES');
        // Téléphone seul : la règle de fn_tocStatus (4.8.0) est appelée, pas recopiée ; sa table est lue chez elle.
        self::assertStringContainsString('["node", [_age, _rate, _beacon, _local, _has]] call comspec_atak_native_fnc_tocStatus', $src);
        self::assertStringNotContainsString('_ruleAtak', $src);
        self::assertSame(1, preg_match('~private _rule = (\[[^\]]+\]);~', self::read(self::TOC . 'fn_tocStatus.sqf'), $ra));
        $rules['_ruleAtak'] = json_decode($ra[1], true);
        $cases = self::tables($src, 'CAS')['_cases'];
        self::assertGreaterThanOrEqual(16, count($cases));
        foreach ($cases as [$name, $args, $want]) {
            self::assertSame($want, self::nodeStatus($args, $rules['_ruleGame'], $rules['_ruleAtak']), $name);
        }
        // Cause du « perdu » de la 4.7 : une unité vivante vue par le jeu n'est jamais perdue ni hors ligne faute de téléphone.
        foreach ([[true, 0], [false, 0], [false, 5]] as [$local, $age]) {
            foreach ([true, false] as $beacon) {
                self::assertSame(0, self::nodeStatus(['J', $local, $age, 3, $beacon, false], $rules['_ruleGame'], $rules['_ruleAtak']));
                self::assertSame(0, self::nodeStatus(['M', $local, $age, 3, $beacon, false], $rules['_ruleGame'], $rules['_ruleAtak']));
            }
        }
        self::assertCount(5, $rules['_ruleAtak']);
        // Source inconnue : état neutre, jamais en rouge.
        self::assertStringContainsString('["source inconnue", "text2"]', $src);
        self::assertStringContainsString('if ((["node", _args] call _fn) isEqualTo _want)', $src, 'selfTest absent');
        // Le serveur donne son état à chaque piste du jeu par cette fonction.
        self::assertStringContainsString('["node", ["J", _x select 3, _age, _iv, true, false]] call comspec_atak_native_fnc_tocNodeStatus', self::read(self::TOC . 'fn_tocTracks.sqf'));
        // Un tué garde sa piste 5 minutes, en « perdu » (2) et santé « tué » (3) : jamais montré en direct.
        self::assertStringContainsString('2] select ((_n select 10) isEqualTo 3)];', self::read(self::TOC . 'fn_tocTracks.sqf'));
    }

    // ---------------------------------------------------------------- pistes : intervalle, plafond, fusion

    /** fn_tocTracks « cap ». @param list<array{0:int,1:string,2:list<string>}> $tracks */
    private static function cap(array $tracks, int $max): array
    {
        if (count($tracks) <= $max) {
            return $tracks;
        }
        $kept = [];
        for ($p = 0; $p <= 5; $p++) {
            foreach ($tracks as $t) {
                if ($t[0] === $p && count($kept) < $max) {
                    $kept[] = $t;
                }
            }
        }

        return $kept;
    }

    /** fn_tocTracks « merge », sur la forme courte des cas. @return array{0:list<string>,1:int,2:int} */
    private static function merge(array $phone, array $tracks, string $mode, int $max): array
    {
        if ($mode === 'ATAK') {
            $out = array_map(static fn (array $p): string => $p[0] . ':A:' . $p[1], array_slice($phone, 0, $max));

            return [$out, count($out), count($phone)];
        }
        $kept = self::cap($tracks, $max);
        $nodes = array_map(static fn (array $t): array => ['key' => $t[1], 'src' => 'J', 'ph' => -1], $kept);
        if ($mode === 'GAME') {
            return [array_map(static fn (array $n): string => $n['key'] . ':J:-1', $nodes), count($nodes), count($tracks)];
        }
        $owner = [];
        foreach ($kept as $i => $t) {
            foreach ($t[2] as $k) {
                $owner[$k] = $i;
            }
        }
        $known = [];
        foreach ($tracks as $t) {
            foreach ($t[2] as $k) {
                $known[$k] = true;
            }
        }
        $free = [];
        foreach ($phone as [$k, $fr]) {
            if (isset($owner[$k])) {
                $i = $owner[$k];
                $old = $nodes[$i]['ph'] < 0 ? 4 : $nodes[$i]['ph'];
                $nodes[$i]['src'] = 'M';
                $nodes[$i]['ph'] = $fr < $old ? $fr : $old;
            } elseif (!isset($known[$k])) {
                $free[] = [$k, $fr];
            }
        }
        $out = array_map(static fn (array $n): string => $n['key'] . ':' . $n['src'] . ':' . $n['ph'], $nodes);
        foreach ($free as [$k, $fr]) {
            if (count($out) < $max) {
                $out[] = $k . ':A:' . $fr;
            }
        }

        return [$out, count($out), count($tracks) + count($free)];
    }

    public function testCollectionIntervalRelaxesWithTheNumberOfTracks(): void
    {
        $src = self::read(self::TOC . 'fn_tocTracks.sqf');
        $steps = self::tables($src, 'PALIERS')['_steps'];
        $interval = static function (int $n) use ($steps): int {
            foreach ($steps as [$max, $sec]) {
                if ($n <= $max) {
                    return $sec;
                }
            }

            return $steps[count($steps) - 1][1];
        };
        foreach (self::tables($src, 'CAS INTERVALLE')['_casesInterval'] as [$n, $want]) {
            self::assertSame($want, $interval($n), $n . ' pistes');
        }
        // Jamais plus court que le tour du flux (3 s), toujours croissant.
        $prev = 0;
        foreach ($steps as [, $sec]) {
            self::assertGreaterThanOrEqual(3, $sec);
            self::assertGreaterThanOrEqual($prev, $sec);
            $prev = $sec;
        }
    }

    public function testPhoneAndGameIdentityMergeIntoOneNodeUnderTheCap(): void
    {
        $src = self::read(self::TOC . 'fn_tocTracks.sqf');
        $cases = self::tables($src, 'CAS FUSION')['_casesMerge'];
        self::assertGreaterThanOrEqual(10, count($cases));
        foreach ($cases as [$name, $phone, $tracks, $mode, $max, $want, $shown, $total]) {
            self::assertSame([$want, $shown, $total], self::merge($phone, $tracks, $mode, $max), $name);
        }
        // Aucun doublon de clé, quel que soit le réglage, et jamais plus de nœuds que le plafond.
        $phone = [['p1', 0], ['p2', 1], ['x1', 2], ['a2', 0]];
        $tracks = [[0, 'p1', ['p1', 'p2']], [4, 'a1', ['a1']], [5, 'a2', ['a2']], [1, 'a3', ['a3']]];
        foreach (['MIX', 'GAME', 'ATAK'] as $mode) {
            foreach ([1, 2, 3, 150] as $max) {
                [$nodes, $shown] = self::merge($phone, $tracks, $mode, $max);
                $keys = array_map(static fn (string $n): string => explode(':', $n)[0], $nodes);
                self::assertSame($keys, array_values(array_unique($keys)), "$mode $max : clé en double");
                self::assertLessThanOrEqual($max, $shown, "$mode $max");
            }
        }
        // Le SQF applique bien ces règles : plafond par priorité, copie des nœuds, identité par les membres.
        self::assertStringContainsString('forEach (_tracks select { (_x select 0) isEqualTo _p })', $src);
        self::assertStringContainsString('{ _owner set [_x, _i]; } forEach (_x select 2);', $src);
        self::assertStringContainsString('if !(_k in _known) then { _free pushBack _x; };', $src);
        self::assertStringContainsString('private _out = _kept apply { +(_x select 1) };', $src);
    }

    public function testGameTracksCostNothingWithoutAReaderAndAddNoLoop(): void
    {
        $tracks = self::code(self::read(self::TOC . 'fn_tocTracks.sqf'));
        foreach (['addPerFrameHandler', 'COMSPEC_ATAK_PfhSpread', 'while {', 'spawn', 'allUnits', 'publicVariable '] as $bad) {
            self::assertStringNotContainsString($bad, $tracks, 'fn_tocTracks : ' . $bad);
        }
        // Relevé gardé tant qu'il est frais, munitions bornées, pertes regroupées, envoi ciblé sur changement.
        self::assertStringContainsString('if ((diag_tickTime - (_c select 2)) < ((_c select 3) - 0.5)) exitWith { _c };', $tracks);
        self::assertStringContainsString('private _ammoBudget = 25;', $tracks);
        self::assertStringContainsString('}, [_side], 5] call CBA_fnc_waitAndExecute;', $tracks);
        self::assertStringContainsString('private _all = _h isNotEqualTo (_st select 0);', $tracks);
        self::assertStringContainsString('{ _x publicVariableClient _var; } forEach _to;', $tracks);
        self::assertStringContainsString('if !(["active", _side] call _fn) exitWith { false };', $tracks);
        // Le flux garde sa sortie sans lecteur, et n'appelle les pistes qu'après elle, par camp lu.
        $feed = self::code(self::read(self::TOC . 'fn_tocFeed.sqf'));
        $exit = strpos($feed, 'if ((count _screens) isEqualTo 0 && {(count _ops) isEqualTo 0}) exitWith {};');
        $skip = strpos($feed, 'if (_viewers isEqualTo []) then { continue };');
        $call = strpos($feed, '(["nodes", [_side, _nodes, _nP, _nAi]] call comspec_atak_native_fnc_tocTracks)');
        self::assertNotFalse($exit);
        self::assertNotFalse($skip);
        self::assertNotFalse($call);
        self::assertLessThan($skip, $exit);
        self::assertLessThan($call, $skip);
        // Réglage « jeu » : les téléphones ne sont pas lus. Effectif réel du camp dans l'état du flux.
        self::assertStringContainsString('if (_srcMode isEqualTo "GAME" || {!alive _u}', $feed);
        self::assertStringContainsString('[_jam, _relays, _strength select 0, _strength select 1, _ext]', $feed);
        self::assertStringContainsString('["publish", [_side, _owners]] call comspec_atak_native_fnc_tocTracks;', $feed);
    }

    // ---------------------------------------------------------------- saisies du poste

    /** fn_tocEntry « validate ». @return array{0:bool,1:list<string>} valide, clés en erreur */
    private static function validate(array $forms, string $kind, array $pairs): array
    {
        $def = null;
        foreach ($forms as $f) {
            if ($f[0] === $kind) {
                $def = $f[2];
            }
        }
        if ($def === null) {
            return [false, ['']];
        }
        $in = [];
        foreach ($pairs as [$k, $v]) {
            $in[$k] = $v;
        }
        $errs = [];
        $clean = [];
        foreach ($def as [$key, , $type, $req, $max, $opts]) {
            $v = $in[$key] ?? '';
            if ($type === 'int') {
                $v = (int) round((float) $v);
                if ($v < 0 || $v > $max) {
                    $errs[] = $key;
                    $v = max(0, min($max, $v));
                }
            } elseif ($type === 'list') {
                $v = mb_strtoupper(trim((string) $v));
                if ($v === '') {
                    $v = $opts[0];
                }
                if (!in_array($v, $opts, true)) {
                    $errs[] = $key;
                    $v = $opts[0];
                }
            } elseif ($type === 'grid') {
                $v = str_replace([' ', '-'], '', (string) $v);
                if ($v === '') {
                    if ($req) {
                        $errs[] = $key;
                    }
                } elseif (!ctype_digit($v) || !in_array(strlen($v), [4, 6, 8, 10], true)) {
                    $errs[] = $key;
                    $v = '';
                }
            } else {
                $v = trim((string) preg_replace('~[\t\n\r]+~', ' ', (string) $v));
                if ($req && $v === '') {
                    $errs[] = $key;
                }
            }
            $clean[$key] = $v;
        }
        if ($kind === 'MEDEVAC' && ($clean['a'] + $clean['b'] + $clean['c']) <= 0) {
            $errs[] = 'a';
        }
        if ($kind === 'CASUALTY' && ($clean['wia'] + $clean['kia'] + $clean['ekia'] + $clean['ewia']) <= 0) {
            $errs[] = 'wia';
        }

        return [$errs === [], $errs];
    }

    public function testPostEntriesAreValidatedOnTheStationAndAgainOnTheServer(): void
    {
        $src = self::read(self::TOC . 'fn_tocEntry.sqf');
        $forms = self::tables($src, 'FORMULAIRES')['_forms'];
        $kinds = array_column($forms, 0);
        foreach (['MEDEVAC', 'FIRE', 'RESUPPLY', 'CONTACT', 'SITREP', 'SALUTE', 'CASUALTY', 'ORDER', 'MESSAGE', 'ACK'] as $k) {
            self::assertContains($k, $kinds, 'formulaire ' . $k);
        }
        $types = ['text', 'grid', 'int', 'list'];
        foreach ($forms as [$kind, $title, $fields]) {
            self::assertNotSame('', $title);
            $keys = array_column($fields, 0);
            self::assertSame($keys, array_values(array_unique($keys)), $kind . ' : clé en double');
            foreach ($fields as $f) {
                self::assertCount(6, $f, $kind . ' / ' . $f[0]);
                self::assertContains($f[2], $types, $kind . ' / ' . $f[0]);
                self::assertIsBool($f[3]);
                if ($f[2] === 'list') {
                    self::assertNotEmpty($f[5], $kind . ' / ' . $f[0] . ' : liste sans choix');
                }
            }
        }
        // MEDEVAC : les neuf lignes sont là.
        $med = implode("\n", array_column($forms[array_search('MEDEVAC', $kinds, true)][2], 1));
        for ($i = 1; $i <= 9; $i++) {
            self::assertStringContainsString($i . ' · ', $med, 'MEDEVAC : ligne ' . $i);
        }
        $cases = self::tables($src, 'CAS VALIDATION')['_casesValid'];
        self::assertGreaterThanOrEqual(12, count($cases));
        foreach ($cases as [$name, $kind, $pairs, $want, $keys]) {
            self::assertSame([$want, $keys], self::validate($forms, $kind, $pairs), $name);
        }
        // Le serveur ne croit pas la validation du poste : il la refait, émetteur déjà vérifié.
        $apply = substr($src, (int) strpos($src, 'case "apply": {'), 1200);
        self::assertStringContainsString('if (!isServer) exitWith { false };', $apply);
        self::assertStringContainsString('isNil "_tocTrusted"', $apply);
        self::assertStringContainsString('(["validate", [_kind, _pairs]] call _fn) params ["_ok", "", "_clean"];', $apply);
        self::assertStringContainsString('["send", ["entry", [_kind, _clean, _pos]]] call comspec_atak_native_fnc_tocRoom;', $src);
    }

    public function testARequestAlreadyReceivedFromAPhoneIsNotCreatedTwice(): void
    {
        $src = self::read(self::TOC . 'fn_tocEntry.sqf');
        $min = static function (string $t): int {
            $p = explode(':', $t);

            return count($p) < 2 ? -1 : (int) $p[0] * 60 + (int) $p[1];
        };
        $dup = static function (array $rows, string $kind, string $el, string $hhmm, int $win) use ($min): int {
            $now = $min($hhmm);
            foreach ($rows as $i => $r) {
                $t = $min($r[1]);
                $d = abs($now - $t);
                if ($d > 720) {
                    $d = 1440 - $d;
                }
                if ($t >= 0 && $r[2] === $kind && mb_strtolower($r[3]) === mb_strtolower($el) && in_array($r[7], ['RECUE', 'PRISE'], true) && $d <= $win) {
                    return $i;
                }
            }

            return -1;
        };
        foreach (self::tables($src, 'CAS DOUBLON')['_casesDup'] as [$name, $rows, $kind, $el, $hhmm, $want]) {
            self::assertSame($want, $dup($rows, $kind, $el, $hhmm, 3), $name);
        }
        self::assertStringContainsString('["do", [_side, "reqExtra", [(_rows select _dup) select 0, _extra], _who, _uid]] call _room;', $src);
        self::assertStringContainsString('case "reqExtra": {', self::read(self::TOC . 'fn_tocRoom.sqf'));
    }

    // ---------------------------------------------------------------- serveur : émetteur vérifié

    public function testServerFindsSideAndUidItselfInsteadOfTrustingTheClient(): void
    {
        $room = self::code(self::read(self::TOC . 'fn_tocRoom.sqf'));
        $req = substr($room, (int) strpos($room, 'case "req": {'), 1600);
        self::assertStringContainsString('private _owner = remoteExecutedOwner;', $req);
        self::assertStringContainsString('_p = (allPlayers select { (owner _x) isEqualTo _owner }) param [0, objNull];', $req);
        self::assertStringContainsString('private _side = str side group _p;', $req);
        self::assertStringContainsString('private _uid = getPlayerUID _p;', $req);
        self::assertStringContainsString('name _p', $req);
        // La demande du joueur ne porte plus ni camp ni UID.
        self::assertStringContainsString('["req", [_act, _args, ["callsign"] call comspec_atak_native_fnc_tocPost]] remoteExecCall ["comspec_atak_native_fnc_tocRoom", 2];', $room);
        // L'événement qui croyait le client n'est plus écouté par le serveur.
        self::assertStringNotContainsString('["comspec_atak_native_tocRoom", {', $room);
        self::assertStringContainsString('missionNamespace setVariable ["COMSPEC_ATAK_TocSrvAuth", true, true];', $room);
        // Appel direct d'un client aux modes du serveur : refusé partout où l'on écrit.
        self::assertMatchesRegularExpression('~if \(isServer && \{isRemoteExecuted\} && \{!\(remoteExecutedOwner in \[0, 2\]\)\} && \{isNil "_tocTrusted"\} && \{!\(_mode in \["req", "state", "zulu", "timerText"\]\)\}\) exitWith \{ false \};~', $room);
        self::assertStringContainsString('private _tocTrusted = true;', $req);
        foreach (['fn_tocEntry.sqf', 'fn_tocInbox.sqf', 'fn_tocFeed.sqf', 'fn_tocTracks.sqf'] as $f) {
            self::assertMatchesRegularExpression('~isRemoteExecuted\}? && \{!\(remoteExecutedOwner in \[0, 2\]\)\} && \{isNil "_tocTrusted"\}~', self::code(self::read(self::TOC . $f)), $f);
        }
        // « dropUid » (départ d'un joueur) reste une action du serveur seul.
        self::assertStringContainsString('_act in ["", "dropUid"]', $req);
    }

    public function testDeathsAreCountedForAnyUnitOfTheSide(): void
    {
        $room = self::code(self::read(self::TOC . 'fn_tocRoom.sqf'));
        self::assertSame(1, preg_match('~addMissionEventHandler \["EntityKilled", \{(.*?)\}\];~s', $room, $m));
        self::assertStringNotContainsString('hasDevice', $m[1], 'un mort sans téléphone doit être compté');
        self::assertStringContainsString('["killed", _u] call comspec_atak_native_fnc_tocTracks;', $m[1]);
        $tracks = self::code(self::read(self::TOC . 'fn_tocTracks.sqf'));
        $killed = substr($tracks, (int) strpos($tracks, 'case "killed": {'), 4200);
        self::assertStringNotContainsString('hasDevice', $killed);
        self::assertStringContainsString('{ ["do", [_side, "kill", [_x, "kia", _y], "Jeu", ""]] call comspec_atak_native_fnc_tocRoom; } forEach _q;', $killed);
    }

    // ---------------------------------------------------------------- sans téléphone : boîte, retours, caméra

    public function testNothingOfTheLotUsesAGameAlertOrThePhoneOrAthena(): void
    {
        $alerts = ['hint ', 'hintSilent', 'hintC', 'systemChat', 'titleText', 'cutText', 'BIS_fnc_showNotification', 'BIS_fnc_dynamicText', 'zen_common_fnc_showMessage',
            'ace_common_fnc_displayText', 'globalChat', 'sideChat'];
        $phone = ['COMSPEC_ATAK_State', 'fnc_bftOrbat', 'fnc_notify', 'fnc_pageRender', 'fnc_navigate'];
        $athena = ['callExtension', 'comspec_overwatch_', 'fnc_bridge', 'fnc_athenaMode', 'fnc_api', 'fnc_radioGuard'];
        foreach (self::NEW_FILES as $file) {
            $code = self::code(self::read(self::TOC . $file));
            foreach (array_merge($alerts, $phone, $athena) as $bad) {
                self::assertStringNotContainsString($bad, $code, $file . ' : ' . $bad);
            }
        }
        // fn_tocInbox lit les ordres là où le serveur du jeu les a posés, sans passer par l'app Tâches.
        $inbox = self::code(self::read(self::TOC . 'fn_tocInbox.sqf'));
        self::assertStringNotContainsString('fnc_tasksAll', $inbox);
        self::assertStringContainsString('["send", [_id, toUpper _status]] call comspec_atak_native_fnc_orderAck;', $inbox);
        // Un message du poste n'est plus jeté faute de téléphone : la boîte ne teste pas fn_hasDevice pour le garder.
        $msg = substr($inbox, (int) strpos($inbox, '["comspec_atak_native_tocMsg", {'), 700);
        self::assertStringNotContainsString('hasDevice', $msg);
        self::assertStringContainsString('["push", ["MSG",', $msg);
    }

    public function testFeedbackGoesWhereThePlayerLooksWhenThePhoneIsAway(): void
    {
        $notify = self::read(self::MAIN . 'functions/ui/fn_notify.sqf');
        self::assertStringContainsString('if (hasInterface && {!_discreet} && {isNull ([] call comspec_atak_native_fnc_display)}) then { ["feedback", [_type, _message, _app]] call comspec_atak_native_fnc_tocInbox; };', $notify);
        $inbox = self::code(self::read(self::TOC . 'fn_tocInbox.sqf'));
        self::assertStringContainsString('if (_pcOpen) then { ["status", _text] call comspec_atak_native_fnc_tocLaptop; };', $inbox);
        self::assertStringContainsString('if (_zeus) then { ["zeusLine", [_text, toUpper _type]] call _fn; };', $inbox);
        self::assertStringContainsString('findDisplay 312', $inbox);
        // Fenêtre ouverte par ACE sur soi et par une touche ; non-lus dans le nom de l'action.
        self::assertStringContainsString('["CAManBase", 1, ["ACE_SelfActions"], _act, true] call ace_interact_menu_fnc_addActionToClass;', $inbox);
        self::assertStringContainsString('call CBA_fnc_addKeybind;', $inbox);
        self::assertStringContainsString('["initClient"] call comspec_atak_native_fnc_tocInbox;', self::read(self::TOC . 'fn_tocRoom.sqf'));
        self::assertStringContainsString('["initServer"] call comspec_atak_native_fnc_tocInbox;', self::read(self::TOC . 'fn_tocRoom.sqf'));
    }

    public function testHelmetCameraOfAPlayerWithoutAPhoneFollowsTheSetting(): void
    {
        $cam = self::code(self::read(self::TOC . 'fn_tocCam.sqf'));
        self::assertStringContainsString('if ((missionNamespace getVariable ["comspec_atak_native_toc_headcam", true]) isEqualTo true) then {', $cam);
        self::assertStringContainsString('format ["Caméra de casque · %1"', $cam);
        self::assertStringContainsString('COMSPEC_ATAK_TocHeadCamOff', $cam);
        self::assertStringContainsString('if (_src isKindOf "Man" && {_src getVariable ["COMSPEC_ATAK_TocHeadCam", false]}) exitWith { 256 };', $cam);
        self::assertStringContainsString('_cam attachTo [_src, [0, 0.14, 0.12], "head", true];', $cam);
        // La caméra poitrine d'un porteur d'ATAK reste soumise à son partage.
        self::assertStringContainsString('{[_x] call comspec_atak_native_fnc_hasDevice} && {["can", _x] call comspec_atak_native_fnc_camShare}', $cam);
    }

    // ---------------------------------------------------------------- registre des panneaux, textes, liste d'essai

    public function testPanelRegistryServesEveryBoardWithItsEmptyState(): void
    {
        $src = self::read(self::TOC . 'fn_tocPanels.sqf');
        preg_match_all('~^            \["([A-Z]+)", "([^"]*)", \{~m', $src, $m);
        foreach (['TRACKS', 'STRENGTH', 'ORBAT', 'FEED', 'ORDERS', 'INBOX', 'MYORDERS', 'MYREQ', 'LOG', 'REQ', 'KILLS', 'EXCHECK', 'TIMERS', 'POSTS', 'CAMS', 'FEEDBACK'] as $id) {
            self::assertContains($id, $m[1], 'panneau ' . $id);
        }
        self::assertSame($m[1], array_values(array_unique($m[1])), 'panneau déclaré deux fois');
        foreach (['"OK"', '"EMPTY"', '"WAIT"', '"LATE"'] as $state) {
            self::assertStringContainsString($state, $src);
        }
        self::assertStringContainsString('format ["%1 pistes affichées sur %2", _shown, _total]', $src);
        self::assertStringContainsString('case "register": {', $src);
        // ORBAT lu dans le jeu, sans le site.
        self::assertStringContainsString('[["orbat", _a] call comspec_atak_native_fnc_tocTracks, []]', $src);
    }

    public function testEveryNewTextHasItsEnglish(): void
    {
        $en = self::read(self::MAIN . 'i18n/en.txt');
        preg_match_all('~^\["((?:[^"]|"")*)", "((?:[^"]|"")*)"\],?$~m', $en, $m);
        $dict = array_combine($m[1], $m[2]);
        self::assertSame(count($m[1]), count($dict), 'clé en double dans i18n/en.txt');
        $texts = ['Source des positions du TOC', 'Mixte (jeu et réseau ATAK)', 'Jeu (toute unité du camp)', 'Réseau ATAK (porteurs seulement)', 'IA suivies par le TOC',
            'Pistes du TOC par camp (plafond)', 'Caméra de casque sans téléphone', 'Caméra de casque · %1', 'source inconnue', 'opérationnel', 'inconscient',
            'Messages et ordres du TOC', 'TOC · messages, ordres et demandes', 'Tout marquer lu', 'Véhicule détruit : %1', 'Tué : %1', '%1 pistes affichées sur %2',
            'En attente des données du serveur', 'Données du serveur en retard', '%1 : à renseigner', '%1 : 4, 6, 8 ou 10 chiffres', '%1 : envoyé'];
        foreach (self::tables(self::read(self::TOC . 'fn_tocEntry.sqf'), 'FORMULAIRES')['_forms'] as [, $title, $fields]) {
            $texts[] = $title;
            foreach ($fields as $f) {
                $texts[] = $f[1];
            }
        }
        preg_match_all('~^            \["[A-Z]+", "([^"]+)", \{.*?\}, "([^"]*)"\],?$~ms', self::read(self::TOC . 'fn_tocPanels.sqf'), $p);
        $texts = array_merge($texts, $p[1], array_filter($p[2]));
        self::assertGreaterThan(90, count($texts));
        foreach (array_unique($texts) as $t) {
            self::assertArrayHasKey($t, $dict, "« {$t} » sans anglais dans i18n/en.txt");
            self::assertNotSame('', $dict[$t]);
        }
        // Le réglage et ses exemples sont traduits en entier.
        self::assertSame(1, preg_match('~"Source des positions du TOC", "([^"]+)"~', self::read(self::MAIN . 'XEH_preInit.sqf'), $tip));
        self::assertArrayHasKey($tip[1], $dict);
        self::assertGreaterThanOrEqual(3, substr_count($dict[$tip[1]], 'example'));
    }

    public function testZeroPhoneChecklistAndChangelogAreWritten(): void
    {
        $doc = self::read('docs/TOC-DATA-API.md');
        self::assertSame(14, preg_match_all('~^- \[ \] \d+\. ~m', $doc), 'liste « mission à zéro téléphone » : 14 cas');
        foreach (['fn_radioGuard', 'D16', 'remoteExecutedOwner', 'comspec_atak_native_toc_source'] as $word) {
            self::assertStringContainsString($word, $doc);
        }
        $log = self::read('CHANGELOG-ATAK.md');
        self::assertStringContainsString('## Pack 4.10.0 Washington : le TOC fonctionne sans téléphone (ATAK natif 4.10.0.AG - Washington', $log);
        self::assertStringContainsString('FR : ATAK 4.10.0 : le TOC fonctionne sans téléphone', $log);
        self::assertStringContainsString('EN : ATAK 4.10.0: the TOC works without a phone', $log);
        self::assertStringContainsString('[h1]COMSPEC ATAK 4.10.0 Washington', self::read('mod/COMSPEC_ATAK_Native/publisher/STEAM_CHANGELOG.txt'));
        self::assertStringContainsString('#define VERSION_STR "4.10.0"', self::read(self::MAIN . 'script_component.hpp'));
    }
}
