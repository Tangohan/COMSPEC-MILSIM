<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * ATAK natif, fenêtre « Gérer les écrans » du TOC : règles sans effet de fn_tocManagerUiData et de fn_tocManagerUiPower,
 * rejouées ici sur leurs propres tables de cas (les mêmes que « selfTest » en jeu) :
 *  - liste des sources rangées par famille (« sourceList ») ;
 *  - changements en attente -> opérations dans l'ordre (« plan ») ;
 *  - boîte de confirmation dans la fenêtre, bouton par défaut ANNULER (« confirm ») ;
 *  - veille et veille automatique d'une dalle (« rule ») ; curseur de largeur (« w2p », « p2w ») ; grille d'un mur.
 */
final class AtakTocManagerUiPlanAssetTest extends TestCase
{
    private const TOC = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/toc/';

    private static function read(string $file): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/' . self::TOC . $file));
    }

    private static function table(string $src, string $name): array
    {
        self::assertSame(1, preg_match('/private\s+' . preg_quote($name, '/') . '\s*=\s*(\[.*?\]);\s*\n\s*\/\/ ---- FIN/s', $src, $m), 'table ' . $name . ' absente');
        $data = json_decode($m[1], true);
        self::assertIsArray($data, 'table ' . $name . ' : JSON invalide');

        return $data;
    }

    private static function family(string $key): string
    {
        $h = explode('|', $key)[0];

        return match (true) {
            in_array($h, ['SPLIT', 'MAP', 'TOC'], true) => 'MAP',
            in_array($h, ['CAM', 'MOS', 'MOSAIC', 'COLC'], true) => 'CAM',
            in_array($h, ['SAT', 'SAT1', 'SAT2'], true) => 'SAT',
            in_array($h, ['CLOCK', 'STRIP', 'COLA'], true) => 'TIME',
            in_array($h, ['BARS', 'BC', 'BROADCAST'], true) => 'BC',
            $h === 'PLAN' => 'PLAN',
            $h === 'TEXT' => 'TEXT',
            in_array($h, ['OFF', 'PLATE'], true) => 'MISC',
            default => 'DATA',
        };
    }

    private static function viewOf(string $key, array $ok): string
    {
        $h = explode('|', $key)[0];

        return match (true) {
            $key === 'OFF' => '',
            in_array($h, ['SAT1', 'SAT2'], true) => 'SAT',
            $h === 'SQ' => (!in_array('SQUAD', $ok, true) && in_array('PLATE', $ok, true)) ? 'PLATE' : 'SQUAD',
            $h === 'MOS' => 'MOSAIC',
            in_array($h, ['BARS', 'BC'], true) => 'BROADCAST',
            $h === 'PLAN' => 'MAP',
            default => $h,
        };
    }

    private static function kindOf(string $key): string
    {
        $h = explode('|', $key)[0];

        return match (true) {
            in_array($h, ['SPLIT', 'MAP', 'TOC', 'SQ', 'SQUAD', 'PLAN'], true) => 'map',
            in_array($h, ['SAT', 'SAT1', 'SAT2'], true) => 'sat',
            in_array($h, ['MOS', 'MOSAIC'], true) => 'mos',
            $h === 'CAM' => 'cam',
            $h === 'TEXT' => 'text',
            default => 'other',
        };
    }

    /** « plan » de fn_tocManagerUiData, rejoué avec sa table _order. */
    private static function plan(array $order, array $changes, array $targets): array
    {
        $ch = [];
        foreach ($changes as [$field, $value]) {
            $ch[$field] = $value;
        }
        $newSrc = $ch['source'] ?? '';
        $ops = [];
        foreach ($order as [$field, $scope, $need]) {
            if (!array_key_exists($field, $ch)) {
                continue;
            }
            $val = $ch[$field];
            if ($scope === 'one' && count($targets) !== 1) {
                $ops[] = ['skip', $field, 'multi'];
                continue;
            }
            $done = [];
            foreach ($targets as [$key, $host, $ok, $src]) {
                $id = $scope === 'host' ? $host : $key;
                if (in_array($id, $done, true)) {
                    continue;
                }
                if ($field === 'source') {
                    $view = self::viewOf($val, $ok);
                    $ops[] = [($view === '' || in_array($view, $ok, true)) ? 'source' : 'refuse', $id, $val];
                } elseif ($field === 'text') {
                    $ops[] = in_array('TEXT', $ok, true) ? ['text', $id, $val] : ['refuse', $id, 'TEXT'];
                } else {
                    $eff = $src;
                    if ($newSrc !== '') {
                        $nv = self::viewOf($newSrc, $ok);
                        if ($nv !== '' && in_array($nv, $ok, true)) {
                            $eff = $newSrc;
                        }
                    }
                    if ($need === '' || in_array(self::kindOf($eff), explode(',', $need), true)) {
                        $ops[] = [$field, $id, $val];
                    }
                }
                $done[] = $id;
            }
        }

        return $ops;
    }

    /** « confirm » : état [genre, cible, garder les écrans], événement -> [état, action]. */
    private static function confirm(array $state, string $ev): array
    {
        [$kind, $id, $keep] = $state;
        $p = explode(':', $ev);
        if ($p[0] === 'ask') {
            return [[$p[1] ?? '', implode(':', array_slice($p, 2)), true], ''];
        }
        if ($kind === '') {
            return [['', '', true], ''];
        }
        if ($ev === 'toggle') {
            return [[$kind, $id, !$keep], ''];
        }
        if ($ev === 'ok') {
            $act = match ($kind) {
                'room' => ($keep ? 'roomDissolve:' : 'roomRemove:') . $id,
                'resetAll' => 'resetAll:',
                'screen' => 'screenDelete:' . $id,
                'scene' => 'sceneSave:' . $id,
                default => '',
            };

            return [['', '', true], $act];
        }

        return [['', '', true], ''];
    }

    public function testChangementsEnAttenteDeviennentUnPlanOrdonne(): void
    {
        $src = self::read('fn_tocManagerUiData.sqf');
        $order = self::table($src, '_order');
        $fields = array_column($order, 0);
        self::assertSame(count($fields), count(array_unique($fields)));
        // L'alimentation avant la source, la source avant sa vue, le nom et le titre réservés à une seule dalle.
        self::assertLessThan(array_search('source', $fields, true), array_search('power', $fields, true));
        self::assertLessThan(array_search('width', $fields, true), array_search('source', $fields, true));
        foreach ($order as [$field, $scope, $need]) {
            self::assertContains($scope, ['pane', 'host', 'one'], $field);
            self::assertSame(in_array($field, ['name', 'title'], true), $scope === 'one', $field);
            self::assertSame(in_array($field, ['layout', 'bright', 'dist', 'autoOff', 'hall'], true), $scope === 'host', $field);
        }
        $cases = self::table($src, '_planCases');
        self::assertGreaterThanOrEqual(10, count($cases));
        foreach ($cases as [$name, $changes, $targets, $want]) {
            self::assertSame($want, self::plan($order, $changes, $targets), $name);
        }
        // Chaque opération du plan est exécutée par fn_tocManagerUiApply « run » ; chaque champ de la fenêtre y mène.
        $run = self::read('fn_tocManagerUiApply.sqf');
        foreach ($fields as $op) {
            self::assertMatchesRegularExpression('/case "' . $op . '"[;:]/', $run, $op . ' : opération sans exécution');
        }
        $ui = self::read('fn_tocManagerUi.sqf');
        foreach ($fields as $op) {
            self::assertMatchesRegularExpression('/(_ch|\(_st get "changes"\)) set \["' . $op . '",/', $ui, $op . ' : aucun champ ne pose ce changement');
        }
        self::assertStringContainsString('["plan", [(keys _ch) apply { [_x, _ch get _x] }, _targets]] call _dat', $ui);
        self::assertStringContainsString('if (_op isEqualTo "refuse") then { _nRef = _nRef + 1; continue };', $run);
        // Les règles rejouées sont bien celles du fichier.
        foreach (['if (_scope isEqualTo "one" && {(count _targets) isNotEqualTo 1}) then { _ops pushBack ["skip", _field, "multi"]; continue };',
            'if (_view isEqualTo "" || {_view in _ok}) then { _ops pushBack ["source", _id, _val]; } else { _ops pushBack ["refuse", _id, _val]; };',
            'case (_h isEqualTo "SQ"): { ["SQUAD", "PLATE"] select (!("SQUAD" in _ok) && {"PLATE" in _ok}) };', 'case (_h in ["SPLIT", "MAP", "TOC", "SQ", "SQUAD", "PLAN"]): { "map" };'] as $needle) {
            self::assertStringContainsString($needle, $src);
        }
    }

    public function testConfirmationDansLaFenetreBoutonParDefautAnnuler(): void
    {
        $src = self::read('fn_tocManagerUiData.sqf');
        $cases = self::table($src, '_confirmCases');
        self::assertGreaterThanOrEqual(10, count($cases));
        foreach ($cases as [$name, $events, $want]) {
            $state = ['', '', true];
            $act = '';
            foreach ($events as $ev) {
                [$state, $act] = self::confirm($state, $ev);
            }
            self::assertSame($want, $act, $name);
            self::assertSame(['', '', true], $state, $name . ' : la boîte se referme');
        }
        // Supprimer une salle : « garder les écrans » par défaut ; Entrée et Échap annulent.
        self::assertSame('roomDissolve:H7', self::confirm(self::confirm(['', '', true], 'ask:room:H7')[0], 'ok')[1]);
        self::assertSame('', self::confirm(self::confirm(['', '', true], 'ask:room:H7')[0], 'enter')[1]);
        self::assertStringContainsString('case "room": { (["roomRemove:", "roomDissolve:"] select _keep) + _id };', $src);
        $ui = self::read('fn_tocManagerUi.sqf');
        $run = self::read('fn_tocManagerUiApply.sqf');
        // Entrée dans la boîte : seulement si le bouton de confirmation a le focus, sinon ANNULER.
        self::assertStringContainsString('if (_foc isEqualTo (_c get "cfOk")) then { ["confirm", "ok"] call _fn; } else { ["confirm", "enter"] call _fn; };', $ui);
        self::assertStringContainsString('["do", [["roomRemove", "dissolve"] select (_what isEqualTo "roomDissolve"), _id]] call _th;', $run);
        self::assertStringContainsString('if !(["canEdit"] call _mgr) exitWith', $run);
        // Les actions qui détruisent passent toutes par la boîte.
        foreach (['["#ask", "room", _hid,', '["#ask", "screen", _cur select 0,', '["#ask", "resetAll", "",', '["#ask", "scene", _hid,'] as $needle) {
            self::assertStringContainsString($needle, $run);
        }
    }

    public function testSourcesRangeesParFamille(): void
    {
        $src = self::read('fn_tocManagerUiData.sqf');
        $families = array_column(self::table($src, '_families'), 1);
        $cases = self::table($src, '_listCases');
        self::assertGreaterThanOrEqual(4, count($cases));
        foreach ($cases as [$name, $items, $used, $find, $want]) {
            $find = mb_strtolower(trim($find));
            $counts = [];
            foreach ($families as $fam) {
                $n = 0;
                foreach ($items as [$label, $key]) {
                    if (self::family($key) === $fam && ($find === '' || str_contains(mb_strtolower($label), $find))) {
                        ++$n;
                    }
                }
                $counts[] = $n;
            }
            self::assertSame($want, $counts, $name);
        }
        // Mêmes familles que le tableau de la 4.8.0, plus PLANS, TEXTE et la plaque rangée dans SPÉCIAL.
        foreach (['SPLIT' => 'MAP', 'CAM|2:14' => 'CAM', 'MOS|3|AUTO' => 'CAM', 'SAT2' => 'SAT', 'SQ|Alpha 1-1' => 'DATA', 'RADIO' => 'DATA', 'CLOCK' => 'TIME', 'PLAN|ALL' => 'PLAN', 'PLAN|3' => 'PLAN',
            'BC|S12-3' => 'BC', 'BARS' => 'BC', 'TEXT' => 'TEXT', 'OFF' => 'MISC', 'PLATE' => 'MISC'] as $key => $want) {
            self::assertSame($want, self::family($key), $key);
            self::assertContains(self::family($key), $families);
        }
        foreach (['case (_h isEqualTo "PLAN"): { "PLAN" };', 'case (_h isEqualTo "TEXT"): { "TEXT" };', 'case (_h in ["OFF", "PLATE"]): { "MISC" };', '((_usedMap getOrDefault [_key, []]) select [0, 4]) joinString ", "'] as $needle) {
            self::assertStringContainsString($needle, $src);
        }
        // Caméras : état lu, hors ligne gardées dans la liste ; source refusée par le format : listée en gris.
        $run = self::read('fn_tocManagerUiApply.sqf');
        foreach (['["no", ["off", "ok"] select _live] select _camOk', '["sources", objNull] call _mgr', '"PLAN|ALL"', 'case "refusal": {'] as $needle) {
            self::assertStringContainsString($needle, $run);
        }
    }

    public function testVeilleEtVeilleAutomatiqueDUneDalle(): void
    {
        $src = self::read('fn_tocManagerUiPower.sqf');
        $rule = static function (bool $off, bool $standby, bool $woke, float $auto, float $near, float $idle): string {
            return match (true) {
                $off && $standby && $near >= 0 && $near <= 10 => 'wake',
                $off => '',
                $near >= 0 && $near <= 25 => '',
                $woke && $idle >= 120 => 'sleep',
                $auto > 0 && $idle >= $auto * 60 => 'sleep',
                default => '',
            };
        };
        $cases = self::table($src, '_cases');
        self::assertGreaterThanOrEqual(10, count($cases));
        foreach ($cases as [$name, $off, $standby, $woke, $auto, $near, $idle, $want]) {
            self::assertSame($want, $rule($off, $standby, $woke, (float) $auto, (float) $near, (float) $idle), $name);
        }
        // Une dalle éteinte à la main ne se rallume jamais seule ; personne à moins de 25 m : aucun réveil.
        self::assertSame('', $rule(true, false, false, 5, 1, 0));
        self::assertSame('', $rule(true, true, false, 0, 11, 0));
        foreach (['case (_off && {_standby} && {_near >= 0} && {_near <= 10}): { "wake" };', 'case (_woke && {_idle >= 120}): { "sleep" };', 'case (_auto > 0 && {_idle >= _auto * 60}): { "sleep" };',
            'if (!_standby && {_auto <= 0} && {!_woke}) then { continue };', '}, 5] call CBA_fnc_addPerFrameHandler;', 'if (isServer) then { ["initServer"] call _fn; };'] as $needle) {
            self::assertStringContainsString($needle, $src);
        }
        // Serveur seul, par le chemin d'un opérateur ; la fenêtre demande, elle ne pose pas l'état des autres.
        self::assertSame(2, substr_count($src, '["do", ["power", [_o,'));
        $run = self::read('fn_tocManagerUiApply.sqf');
        self::assertStringContainsString('["set", [_o, "standby", _pm isEqualTo 1]] call comspec_atak_native_fnc_tocManagerUiPower;', $run);
        self::assertStringContainsString('["set", [_h, "autoOff", _val]] call comspec_atak_native_fnc_tocManagerUiPower;', $run);
    }

    public function testCurseurDeLargeurEtGrilleDUnMur(): void
    {
        $src = self::read('fn_tocManagerUiData.sqf');
        self::assertStringContainsString('case "w2p": { ((100 * (ln (((_a max 60) min 20000) / 60)) / (ln (20000 / 60))) max 0) min 100 };', $src);
        self::assertStringContainsString('case "p2w": { round (60 * ((20000 / 60) ^ (((_a max 0) min 100) / 100))) };', $src);
        $w2p = static fn (float $w): float => min(100.0, max(0.0, 100 * log(min(20000.0, max(60.0, $w)) / 60) / log(20000 / 60)));
        $p2w = static fn (float $p): float => round(60 * (20000 / 60) ** (min(100.0, max(0.0, $p)) / 100));
        self::assertSame(60.0, $p2w(0));
        self::assertSame(20000.0, $p2w(100));
        foreach ([60, 500, 1000, 3000, 8000, 20000] as $w) {
            self::assertEqualsWithDelta($w, $p2w($w2p((float) $w)), $w * 0.01, 'aller-retour ' . $w . ' m');
        }
        // Un pas de curseur change la largeur de moins de 8 % : précis sur les petites largeurs comme sur les grandes.
        for ($p = 0; $p < 100; ++$p) {
            self::assertLessThan(1.08, $p2w($p + 1) / $p2w($p));
        }
        // Grille d'un mur lue dans le nom de sa classe, sinon une rangée.
        $grid = static function (string $class, int $n): array {
            $n = max(1, $n);
            if (preg_match('/([0-9])x([0-9])/', $class, $m) !== 1) {
                return [$n, 1];
            }
            $cols = (int) $m[1];
            $rows = (int) $m[2];

            return ($cols * $rows < $n || $cols < 1) ? [$n, 1] : [$cols, $rows];
        };
        self::assertSame([4, 3], $grid('COMSPEC_ATAK_Screen_Wall4x3_Panels', 12));
        self::assertSame([6, 4], $grid('COMSPEC_ATAK_Screen_Wall6x4', 24));
        self::assertSame([3, 1], $grid('COMSPEC_ATAK_Screen_Curved', 3));
        self::assertSame([8, 1], $grid('COMSPEC_ATAK_Screen_Wall2x2', 8));
        self::assertStringContainsString('if (_cols * _rws < _n || {_cols < 1}) exitWith { [_n, 1] };', $src);
        // 24 cases au plus dans le schéma (mur 6 x 4) ; vignettes jusqu'à 12 dalles.
        $ui = self::read('fn_tocManagerUi.sqf');
        self::assertStringContainsString('for "_i" from 0 to 23 do {', $ui);
        self::assertStringContainsString('["grid", [typeOf _host, count _sib]] call _dat', $ui);
    }
}
