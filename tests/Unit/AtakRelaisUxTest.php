<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * App Wave Relay refaite, relais exigé pour le réseau, chemin et qualité de la voix, graphe du camp.
 * Les fonctions « sans effet » du SQF ne peuvent pas être exécutées ici : leurs constantes sont lues dans le SQF et le
 * calcul est rejoué par un miroir PHP. Un chiffre changé dans le SQF sans le miroir fait échouer la lecture des constantes.
 */
final class AtakRelaisUxTest extends TestCase
{
    private const MAIN = __DIR__ . '/../../mod/COMSPEC_ATAK_Native/Sources/addons/main';

    private function read(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(self::MAIN . '/' . $path));
    }

    private function settingBlock(string $setting): string
    {
        $pre = $this->read('XEH_preInit.sqf');
        $i = strpos($pre, '["comspec_atak_native_' . $setting . '", "');
        self::assertNotFalse($i, $setting);

        return substr($pre, $i, strpos($pre, 'fnc_addSetting', $i) - $i);
    }

    public function testMaillageEtMpu5ActifsParDefaut(): void
    {
        // Chaque interrupteur qui allume le maillage, la MPU5 ou la voix relayée : coché par défaut, réglage serveur (1), donc toujours modifiable par un admin.
        foreach (['mpu_real', 'mpu_voice', 'relay_gate', 'mpu_voice_needRelay', 'mpu_wifi', 'mesh_server', 'mesh_gravity', 'mesh_p2p', 'mesh_groups', 'mesh_tools', 'radio_data'] as $on) {
            self::assertMatchesRegularExpression('~_[a-zA-Z]+, true, 1~', $this->settingBlock($on), $on);
        }
        // Restent décochés : ce qui retire le réseau à qui n'a pas préparé sa mission, et les outils de développement.
        foreach (['mpu_require', 'mesh_keys', 'mesh_dev'] as $off) {
            self::assertMatchesRegularExpression('~_[a-zA-Z]+, false, 1~', $this->settingBlock($off), $off);
        }
        self::assertStringContainsString('["Aucune", "Légère", "Réaliste"], 2], 1]', $this->settingBlock('mesh_voice_degrade'));
        self::assertStringContainsString('[0, 90, 0, 0], 1]', $this->settingBlock('mesh_voice_cut'));
        // Le défaut lu sans réglage enregistré (mission sans CBA chargé) suit : activé.
        $voice = $this->read('functions/network/fn_mpuVoice.sqf');
        self::assertStringNotContainsString('["comspec_atak_native_mpu_voice", false]', $voice);
        self::assertStringContainsString('coupée par un admin', $voice);
        // Catalogue Réalisme : les nouveaux réglages y sont, avec leur aide et un exemple.
        $cat = $this->read('functions/network/fn_realismCatalog.sqf');
        $help = $this->read('functions/network/fn_realismHelp.sqf');
        foreach (['relay_gate', 'mesh_voice_degrade', 'mesh_voice_cut', 'mesh_voice_policy'] as $k) {
            self::assertStringContainsString('_p + "' . $k . '"', $cat, $k);
            self::assertStringContainsString('["' . $k . '", ["', $help, $k);
        }
        self::assertStringContainsString('Coché par défaut depuis la 4.8', $help);
    }

    public function testRelaisExigeDecideEnUnSeulEndroit(): void
    {
        $net = $this->read('functions/network/fn_relayNet.sqf');
        foreach (['case "gateOn": {', 'case "gateEval": {', 'case "gate": {', 'case "deadZone": {'] as $c) {
            self::assertStringContainsString($c, $net, $c);
        }
        // « only » (liaison du téléphone, explosifs en réseau, page Réseau) suit la règle.
        $only = substr($net, (int) strpos($net, 'case "only": {'), 400);
        self::assertStringContainsString('|| {["gateOn"] call comspec_atak_native_fnc_relayNet}', $only);
        // Le profil Arcade garde un réseau libre.
        self::assertStringContainsString('(["profile"] call comspec_atak_native_fnc_netCond) isNotEqualTo 1', $net);
        // Graphe : plus de maillage d'équipe ni de maillage autonome sans relais.
        $graph = $this->read('functions/network/fn_meshGraph.sqf');
        $i = strpos($graph, 'if (_gws isEqualTo [] && {["gateOn"] call comspec_atak_native_fnc_relayNet}) exitWith {');
        self::assertNotFalse($i);
        self::assertLessThan(strpos($graph, '_gws = [_li]; _mode = "lead";'), $i);
        // Ancien maillage de l'app, voix relayée, carte d'état : même source.
        self::assertStringContainsString('if (_gw < 0 && {!_gateOn}) then {', $this->read('functions/pages/fn_waveRelayAction.sqf'));
        self::assertStringContainsString('{!(["gateOn"] call comspec_atak_native_fnc_relayNet)}) exitWith { true };', $this->read('functions/network/fn_mpuVoice.sqf'));
        self::assertStringContainsString('["gate"] call comspec_atak_native_fnc_relayNet', $this->read('functions/pages/fn_waveRelayStatus.sqf'));
        // La liaison internet vers Athena ne lit pas la règle.
        foreach (['fn_athenaLink.sqf', 'fn_athenaStatus.sqf', 'fn_athenaSignal.sqf'] as $f) {
            self::assertStringNotContainsString('gateOn', $this->read('functions/network/' . $f), $f);
        }
    }

    /**
     * Miroir de fn_relayNet "gateEval".
     *
     * @param list<array{string, bool, string, float, float}> $relays
     * @return array{bool, string}
     */
    private function gateEval(bool $on, array $relays, bool $attached): array
    {
        if (!$on) {
            return [true, 'free'];
        }
        if ($relays === []) {
            return [false, 'none'];
        }
        $up = array_values(array_filter($relays, static fn (array $r): bool => $r[1]));
        if ($up === []) {
            return [false, 'down'];
        }
        if ($attached) {
            return [true, 'ok'];
        }
        usort($up, static fn (array $a, array $b): int => $a[3] <=> $b[3]);

        return [false, $up[0][3] > $up[0][4] ? 'range' : 'chain'];
    }

    /** @return array<string, array{bool, list<array{string, bool, string, float, float}>, bool, bool, string}> */
    public static function casRelais(): array
    {
        $ok = ['Pylône 150179', true, '', 400.0, 1200.0];

        return [
            'aucun relais' => [true, [], false, false, 'none'],
            'relais détruit' => [true, [['Pylône 150179', false, 'détruit', 400.0, 1200.0]], false, false, 'down'],
            'relais éteint' => [true, [['Pylône 150179', false, 'éteint', 400.0, 1200.0]], false, false, 'down'],
            'relais brouillé' => [true, [['Pylône 150179', false, 'brouillé', 400.0, 1200.0]], false, false, 'down'],
            'hors de portée' => [true, [['Pylône 150179', true, '', 1800.0, 1200.0]], false, false, 'range'],
            'à portée et accroché' => [true, [$ok], true, true, 'ok'],
            'à portée, chaîne coupée' => [true, [$ok], false, false, 'chain'],
            'un détruit, un en service accroché' => [true, [['Mât', false, 'détruit', 100.0, 900.0], $ok], true, true, 'ok'],
            'réseau libre, sans relais (pair à pair)' => [false, [], false, true, 'free'],
            'règle active, deux téléphones sans relais (pair à pair)' => [true, [], false, false, 'none'],
        ];
    }

    /** @param list<array{string, bool, string, float, float}> $relays */
    #[DataProvider('casRelais')]
    public function testTableDuRelaisExige(bool $on, array $relays, bool $attached, bool $connected, string $code): void
    {
        self::assertSame([$connected, $code], $this->gateEval($on, $relays, $attached));
        // Les codes et les phrases du miroir sont ceux du SQF.
        $net = $this->read('functions/network/fn_relayNet.sqf');
        self::assertStringContainsString('"' . $code . '"', $net);
        foreach (['"Aucun relais sur le serveur"', '"Relais hors service : %1 %2"', '"Relais hors de portée : %1 à %2, portée %3"', '"Posez ou réparez un relais à portée, ou rapprochez-vous."'] as $t) {
            self::assertStringContainsString($t, $net, $t);
        }
        self::assertStringContainsString('if (_d > _r) exitWith { [false, "range"', $net);
    }

    /**
     * Constantes de fn_voicePath "quality", lues dans le SQF : [mode => [plancher, pente, niveau 1, niveau 2]].
     *
     * @return array<int, array{float, float, float, float}>
     */
    private function qualityConstants(): array
    {
        $v = $this->read('functions/network/fn_voicePath.sqf');
        self::assertSame(1, preg_match('~case 0: \{ ([0-9.]+) \};\s*case 1: \{ \[\(1 - ([0-9.]+) \* _hops\) max ([0-9.]+), ([0-9.]+), ([0-9.]+)\] select _l \};\s*default \{ \[\(1 - ([0-9.]+) \* _hops\) max ([0-9.]+), ([0-9.]+), ([0-9.]+)\] select _l \};~', $v, $m));
        self::assertStringContainsString('case (_pct >= 90): { 0 }; case (_pct >= 70): { 1 }; case (_pct >= 50): { 2 }; default { 3 };', $v);
        self::assertStringContainsString('(round (40 + _hopMs * _hops)) min 400', $v);

        return [0 => [(float) $m[1], 0.0, (float) $m[1], (float) $m[1]], 1 => [(float) $m[3], (float) $m[2], (float) $m[4], (float) $m[5]], 2 => [(float) $m[7], (float) $m[6], (float) $m[8], (float) $m[9]]];
    }

    /** @return array{int, int, int} palier, qualité %, latence ms */
    private function quality(int $hops, int $level, int $mode, int $hopMs, int $cut): array
    {
        if ($hops < 0 || $level >= 3) {
            return [4, 0, 0];
        }
        [$floor, $slope, $l1, $l2] = $this->qualityConstants()[$mode];
        $px = [max(1 - $slope * $hops, $floor), $l1, $l2][min(max($level, 0), 2)];
        $pct = (int) round($px * 100);
        if ($pct < $cut) {
            return [4, 0, 0];
        }

        return [$pct >= 90 ? 0 : ($pct >= 70 ? 1 : ($pct >= 50 ? 2 : 3)), $pct, min((int) round(40 + $hopMs * $hops), 400)];
    }

    public function testQualiteDeLaVoixRelayee(): void
    {
        // Réaliste (défaut) : 1 saut sur un bon lien = Excellent 96 %, puis Bon, puis le plancher ; lien dégradé = Bon 70 %, à la limite = Mauvais 45 %.
        self::assertSame([0, 96, 55], $this->quality(1, 0, 2, 15, 0));
        self::assertSame([1, 88, 85], $this->quality(3, 0, 2, 15, 0));
        self::assertSame([2, 60, 400], $this->quality(12, 0, 2, 30, 0));
        self::assertSame([1, 70, 70], $this->quality(2, 1, 2, 15, 0));
        self::assertSame([3, 45, 70], $this->quality(2, 2, 2, 15, 0));
        // Coupure nette : hors maillage, niveau 3, ou sous le seuil réglé.
        self::assertSame([4, 0, 0], $this->quality(-1, 0, 2, 15, 0));
        self::assertSame([4, 0, 0], $this->quality(2, 3, 2, 15, 0));
        self::assertSame([4, 0, 0], $this->quality(2, 2, 2, 15, 50));
        // Légère : jamais sous 65 %. Aucune : toujours 95 %.
        self::assertSame([2, 65, 70], $this->quality(2, 2, 1, 15, 0));
        self::assertSame([0, 95, 70], $this->quality(2, 2, 0, 15, 0));
        // Le signal rendu à ACRE2 vient de la même fonction, et le mode réaliste rend les valeurs d'avant (aucun changement audible par défaut).
        $voice = $this->read('functions/network/fn_mpuVoice.sqf');
        self::assertStringContainsString('["quality", [_hops, [0, _lvl] select _grav, _mode, 0, _cut]] call comspec_atak_native_fnc_voicePath', $voice);
        self::assertStringContainsString('[_px max (_q select 3), _dbm max (_q select 4)]', $voice);
        self::assertSame([0.6, 0.04, 0.7, 0.45], $this->qualityConstants()[2]);
        self::assertStringContainsString('default { [-60 - 3 * _hops, -80, -95] select _l };', $this->read('functions/network/fn_voicePath.sqf'));
        // Chemin voix d'ACRE2 : trois lectures de variable, aucun appel de plus.
        $sig = $this->read('functions/network/fn_mpuVoiceSig.sqf');
        self::assertSame(1, substr_count($sig, 'call comspec_atak_native_fnc_mpuVoice'));
        self::assertStringContainsString('missionNamespace getVariable ["COMSPEC_ATAK_VoicePolicy", 0]', $sig);
    }

    /**
     * Miroir de fn_voicePath "resolve".
     *
     * @return array{string, string}
     */
    private function resolve(bool $speaking, bool $onRadio, string $mod, int $meshHops, string $code, string $why, bool $noRelay): array
    {
        if (!$speaking) {
            return ['none', 'idle'];
        }
        if (!$onRadio) {
            return ['local', 'direct'];
        }
        if (!in_array($mod, ['acre', 'tfar'], true)) {
            return ['none', 'noradio'];
        }
        if ($meshHops >= 0 && $code === 'on') {
            return ['mesh', 'relayed'];
        }
        $reason = match ($code) {
            'on' => 'radioEnough', 'off' => 'meshOff', 'cut' => 'meshCut', 'noreal' => 'meshNoReal', 'nobridge' => 'notBridged',
            default => $noRelay ? 'noRelay' : (str_contains($why, 'brouill') ? 'jammed' : 'meshWait'),
        };

        return [$mod, $reason];
    }

    public function testCheminDeLaVoix(): void
    {
        $table = [
            [[false, false, 'acre', -1, 'on', '', false], ['none', 'idle']],
            [[true, false, 'acre', 2, 'on', '', false], ['local', 'direct']],
            [[true, true, '', -1, 'off', '', false], ['none', 'noradio']],
            [[true, true, 'acre', 2, 'on', '', false], ['mesh', 'relayed']],
            [[true, true, 'tfar', 1, 'on', '', false], ['mesh', 'relayed']],
            [[true, true, 'acre', -1, 'on', '', false], ['acre', 'radioEnough']],
            [[true, true, 'acre', -1, 'off', '', false], ['acre', 'meshOff']],
            [[true, true, 'tfar', -1, 'nobridge', '', false], ['tfar', 'notBridged']],
            [[true, true, 'acre', -1, 'wait', 'Aucun relais sur le serveur', true], ['acre', 'noRelay']],
            [[true, true, 'acre', -1, 'wait', 'canal brouillé', false], ['acre', 'jammed']],
            [[true, true, 'acre', -1, 'wait', 'hors du maillage', false], ['acre', 'meshWait']],
            [[true, true, 'acre', 2, 'wait', '', false], ['acre', 'meshWait']],
        ];
        $v = $this->read('functions/network/fn_voicePath.sqf');
        foreach ($table as [$in, $out]) {
            self::assertSame($out, $this->resolve(...$in));
            // Chaque chemin et chaque raison du miroir a sa phrase dans le SQF.
            self::assertStringContainsString('["' . $out[0] . '", "', $v, $out[0]);
            self::assertStringContainsString('["' . $out[1] . '", "', $v, $out[1]);
        }
        // Piloté par les événements d'émission, jamais par une boucle.
        foreach (['"acre_startedSpeaking"', '"acre_stoppedSpeaking"', '"OnTangent"', '["comspec_atak_native_voicePath", ["get"] call comspec_atak_native_fnc_voicePath] call CBA_fnc_localEvent'] as $t) {
            self::assertStringContainsString($t, $v, $t);
        }
        self::assertDoesNotMatchRegularExpression('~\b(while|waitUntil|addPerFrameHandler|addMissionEventHandler)\b(?![^\n]*_guard)~', $v);
        self::assertStringContainsString('["mesh", [_arg param [0, 0]]] call comspec_atak_native_fnc_voicePath;', $this->read('functions/network/fn_relayTxBadge.sqf'));
        self::assertStringContainsString('["init"] call comspec_atak_native_fnc_voicePath;', $this->read('XEH_postInitClient.sqf'));
        // Barre d'état : libellé et cadenas, grande et petite vue.
        self::assertStringContainsString('COMSPEC_ATAK_VoiceBar', $this->read('functions/ui/fn_statusUpdate.sqf'));
        foreach (['"VOIX ATAK"', '"VOIX ACRE"', '"VOIX TFAR"', '"VOIX DIR."'] as $lab) {
            self::assertStringContainsString($lab, $v);
            self::assertLessThanOrEqual(mb_strlen('COMPROMISE'), mb_strlen(trim($lab, '"')), $lab);
        }
    }

    /**
     * Miroir de fn_meshGraphView "layout" (rangée = nombre de sauts, une case par nœud).
     *
     * @param list<int> $hops
     * @param list<string> $names
     * @return list<array{float, float}>
     */
    private function layout(array $hops, array $names, int $cap = 80, int $perRow = 8): array
    {
        $order = [];
        foreach ($hops as $i => $h) {
            if ($h >= 0) {
                $order[] = [$h, $i];
            }
        }
        sort($order);
        $kept = array_slice($order, 0, $cap);
        $dropped = array_slice($order, $cap);
        $placed = [];
        $row = 0;
        $max = $kept === [] ? 0 : max(array_column($kept, 0));
        for ($h = 0; $h <= $max; $h++) {
            $in = [];
            foreach ($kept as [$hh, $i]) {
                if ($hh === $h) {
                    $in[] = [$names[$i], $i];
                }
            }
            if ($in === []) {
                continue;
            }
            sort($in);
            $extra = count(array_filter($dropped, static fn (array $d): bool => $d[0] === $h));
            $lines = (int) ceil((count($in) + ($extra > 0 ? 1 : 0)) / $perRow);
            for ($ln = 0; $ln < $lines; $ln++) {
                $chunk = array_slice($in, $ln * $perRow, $perRow);
                $cnt = count($chunk) + ($extra > 0 && $ln === $lines - 1 ? 1 : 0);
                foreach ($chunk as $k => [, $i]) {
                    $placed[$i] = [$row, ($k + 0.5) / max($cnt, 1)];
                }
                $row++;
            }
        }
        $out = array_fill(0, count($hops), [-1.0, -1.0]);
        foreach ($placed as $i => [$r, $x]) {
            $out[$i] = [$x, ($r + 0.5) / max($row, 1)];
        }

        return $out;
    }

    public function testMiseEnPageDuGrapheSansChevauchementEtDeterministe(): void
    {
        $hops = [];
        $names = [];
        for ($i = 0; $i < 120; $i++) {
            $hops[] = $i < 3 ? 0 : ($i % 11 === 0 ? -1 : 1 + ($i % 5));
            $names[] = 'N' . (($i * 37) % 120);
        }
        $a = $this->layout($hops, $names);
        self::assertSame($a, $this->layout($hops, $names), 'même entrée, même dessin');
        $seen = [];
        $placed = 0;
        foreach ($a as $i => [$x, $y]) {
            if ($hops[$i] < 0) {
                self::assertSame([-1.0, -1.0], [$x, $y], 'hors réseau : à part, pas dans le graphe');
                continue;
            }
            if ($x < 0) {
                continue;
            }
            $placed++;
            self::assertGreaterThan(0, $x);
            self::assertLessThan(1, $x);
            $key = sprintf('%.4f/%.4f', $x, $y);
            self::assertArrayNotHasKey($key, $seen, 'deux nœuds sur la même case');
            $seen[$key] = true;
        }
        self::assertSame(80, $placed, 'plafond de 80 nœuds, le reste regroupé');
        // Écart minimal dans une rangée : une case sur huit.
        $rows = [];
        foreach ($a as [$x, $y]) {
            if ($x >= 0) {
                $rows[sprintf('%.4f', $y)][] = $x;
            }
        }
        foreach ($rows as $xs) {
            sort($xs);
            for ($k = 1, $n = count($xs); $k < $n; $k++) {
                self::assertGreaterThanOrEqual(1 / 9 - 1e-9, $xs[$k] - $xs[$k - 1]);
            }
        }
        // Les relais (0 saut) sont sur la première rangée.
        self::assertSame($a[0][1], $a[1][1]);
        $below = array_values(array_filter(array_keys($a), static fn (int $i): bool => $hops[$i] > 0 && $a[$i][0] >= 0));
        self::assertNotEmpty($below);
        self::assertLessThan($a[$below[0]][1], $a[0][1]);
        $view = $this->read('functions/network/fn_meshGraphView.sqf');
        foreach (['["_cap", 80], ["_perRow", 8]', '_order sort true;', '_in sort true;', 'case "geo": {', 'for "_pass" from 1 to 8 do {', 'Aucun relais en service : réseau indisponible', 'createDisplay "RscDisplayEmpty"', 'comspec_atak_native_fnc_tocDisplayName'] as $t) {
            self::assertStringContainsString($t, $view, $t);
        }
        // Rien par image : ni Draw, ni boucle d'affichage.
        self::assertDoesNotMatchRegularExpression('~Draw3D|EachFrame|addPerFrameHandler|onEachFrame~', $view);
        self::assertStringContainsString('"Voir le réseau de ce camp (graphe)"', $this->read('functions/network/fn_meshTools.sqf'));
    }

    public function testAppWaveRelayEtatDAbordEtFiches(): void
    {
        $page = $this->read('functions/pages/fn_pageWaveRelay.sqf');
        foreach (['["ÉTAT", "NODE"] call _tabBtn', '["RELAIS", "COV"] call _tabBtn', '"COMSPEC_ATAK_WrStatusCtl"', '"COMSPEC_ATAK_WrVoiceCtl"', '["graph", ', '["meter", ', '"TESTER"', '"PASSERELLE"', '"SIGNALER UN PROBLÈME"',
            '"COMMENT L\'ACTIVER"', '["wrFoldRadio", "Radio de données"', '["wrFoldVoice", "Voix par le réseau"', '"Zone sans réseau la plus proche"', '"Gestion des relais (Zeus et admin)"', '"Priorité"', '"Dernières émissions"'] as $t) {
            self::assertStringContainsString($t, $page, $t);
        }
        // L'ancien mur de texte n'est plus au milieu de la page.
        self::assertStringNotContainsString('Lu dans vos radios ACRE2, sans jamais les régler', $page);
        self::assertStringNotContainsString('_rows pushBack ["text", ([_vHelp] call _esc) call _muted]', $page);
        // La carte d'état vient avant tout le reste de l'onglet ÉTAT.
        $node = substr($page, (int) strpos($page, "    default {\n        private _q ="));
        self::assertLessThan(strpos($node, '["graph", '), strpos($node, '"COMSPEC_ATAK_WrStatusCtl"'));
        self::assertLessThan(strpos($node, 'Voisins directs (%1)'), strpos($node, '"TESTER"'));
        $sheet = $this->read('functions/pages/fn_waveRelaySheet.sqf');
        foreach (['case "voice": {', 'case "gateway": {', 'case "diag": {', 'case "relay": {', '"1. Réglage du serveur"', '"6. Un équipier relié, même fréquence"', 'case "relayAct": {', 'diag_log text format'] as $t) {
            self::assertStringContainsString($t, $sheet, $t);
        }
        self::assertStringContainsString("\"Voir l'état du relais\"", $this->read('functions/shell/fn_aceMenu.sqf'));
        self::assertStringContainsString('["open", ["relay", _o]] call comspec_atak_native_fnc_waveRelaySheet', $this->read('functions/network/fn_relayNet.sqf'));
        self::assertStringContainsString('case "overview": {', $this->read('functions/network/fn_relayZeus.sqf'));
        foreach (['case "meter": {', 'case "graph": {'] as $c) {
            self::assertStringContainsString($c, $this->read('functions/ui/fn_formRender.sqf'), $c);
        }
        // Pas d'alerte d'Arma, pas de requête réseau, rien par image dans les fichiers ajoutés.
        foreach (['functions/pages/fn_waveRelaySheet.sqf', 'functions/network/fn_voicePath.sqf', 'functions/network/fn_meshGraphView.sqf'] as $f) {
            $src = (string) preg_replace('~/\*.*?\*/|//[^\n]*~s', '', $this->read($f));
            self::assertDoesNotMatchRegularExpression('~\b(hint|hintSilent|systemChat|titleText|cutText|BIS_fnc_showNotification|extensionCall|callExtension)\b~', $src, $f);
        }
        $cfg = $this->read('config.cpp');
        foreach (['class voicePath {};', 'class meshGraphView {};', 'class waveRelaySheet {};'] as $c) {
            self::assertStringContainsString($c, $cfg, $c);
        }
        foreach (['wr_node_relay.paa', 'wr_node_mpu.paa', 'wr_node_phone.paa', 'wr_node_ring.paa'] as $paa) {
            self::assertFileExists(self::MAIN . '/data/' . $paa);
        }
    }

    public function testTraductionsAnglaises(): void
    {
        $en = (string) file_get_contents(self::MAIN . '/i18n/en.txt');
        $xx = (string) file_get_contents(self::MAIN . '/../../../localisation/template/xx.txt');
        foreach (['Réseau relié', 'Aucun relais sur le serveur', 'Réseau ATAK (maillage MPU5)', 'Radio ACRE2 (radio classique)', 'Voix directe (proximité)', 'Excellent', 'Mauvais', 'Perdu',
            'SIGNALER UN PROBLÈME', "COMMENT L'ACTIVER", 'Réseau du camp', 'Aucun relais en service : réseau indisponible', 'Exiger un relais pour le réseau MPU/radio', 'Dégradation de la voix par le maillage', "Voir l'état du relais"] as $fr) {
            self::assertStringContainsString('["' . $fr . '", "', $en, $fr);
            self::assertStringContainsString('["' . $fr . '", "", "', $xx, $fr);
        }
    }
}
