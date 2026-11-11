<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * IA hostiles du SSE : colère, insultes, bousculade, coups (addon ia_rp du pack @COMSPEC_SSE).
 * Les règles sont des tables (data/iarp_aggro.sqf) lues par fn_iarpAggro : le test relit ces tables et rejoue les
 * mêmes règles (palier atteint, dégâts plafonnés), vérifie les répliques (data/iarp_hostile.sqf : 3 au moins par palier
 * et par caractère, français et anglais) et le câblage (réglages coupés par défaut, Eden, Zeus, TSP en option, aucune
 * fenêtre d'alerte du jeu).
 */
final class SseIaAgressiviteAssetTest extends TestCase
{
    private function addon(): string
    {
        return dirname(__DIR__, 2) . '/mod/@COMSPEC_SSE/addons/ia_rp';
    }

    private function read(string $rel): string
    {
        $path = $this->addon() . '/' . $rel;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }

    /** @return array<string, mixed> */
    private function data(string $file): array
    {
        $src = (string) preg_replace('#/\*.*?\*/#s', '', $this->read('data/' . $file));
        $rows = json_decode(trim($src), true);
        self::assertIsArray($rows, "data/$file : tableau SQF illisible (" . json_last_error_msg() . ')');
        $out = [];
        foreach ($rows as [$k, $v]) {
            $out[(string) $k] = $v;
        }

        return $out;
    }

    /** @return array<string, array{en: string, fr: string}> */
    private function strings(): array
    {
        $xml = simplexml_load_string($this->read('stringtable.xml'));
        self::assertNotFalse($xml);
        $out = [];
        foreach ($xml->xpath('//Key') ?: [] as $key) {
            $out[(string) $key['ID']] = ['en' => trim((string) $key->English), 'fr' => trim((string) $key->French)];
        }

        return $out;
    }

    /**
     * Même règle que fn_iarpAggro « stage ».
     *
     * @param array<string, mixed> $c
     */
    private function stage(array $c, float $anger, string $mode, string $trait): string
    {
        if (!in_array($mode, ['verbal', 'physical'], true)) {
            return 'calm';
        }
        $bluff = false;
        foreach ($c['traits'] as $row) {
            if ($row[0] === $trait) {
                $bluff = $row[3];
            }
        }
        $phys = $mode === 'physical' && !$bluff;
        $out = 'calm';
        foreach ($c['stages'] as [$id, $min, $needPhys]) {
            if ($anger >= $min && (!$needPhys || $phys)) {
                $out = $id;
            }
        }

        return $out;
    }

    /**
     * Même règle que fn_iarpAggro « damage ».
     *
     * @param array<string, mixed> $c
     */
    private function damage(array $c, string $kind, float $intensity, bool $lethal, float $taken, float $victim): float
    {
        $d = array_column($c['damage'], 1, 0);
        $k = max(0, min(100, $intensity)) / 50;
        $dmg = min($d[$kind] * $k, max(0, $d['perMinute'] * $k - $taken));
        if (!$lethal) {
            $dmg = min($dmg, max(0, $d['nonLethalCeil'] - $victim));
        }

        return (float) max(0, $dmg);
    }

    public function testEscalationLadder(): void
    {
        $c = $this->data('iarp_aggro.sqf');
        self::assertSame(['off', 'verbal', 'physical'], $c['modes']);
        self::assertSame(['calm', 'irritated', 'hostile', 'shove', 'punch'], array_column($c['stages'], 0));
        $prev = -1;
        foreach ($c['stages'] as [$id, $min, $needPhys]) {
            self::assertGreaterThan($prev, $min, "palier $id : seuils croissants");
            self::assertSame(in_array($id, ['shove', 'punch'], true), $needPhys, "palier $id : geste physique");
            $prev = $min;
        }

        self::assertSame('calm', $this->stage($c, 100, 'off', 'hostile'), 'réglage coupé : jamais rien');
        self::assertSame('calm', $this->stage($c, 10, 'physical', 'none'));
        self::assertSame('irritated', $this->stage($c, 25, 'verbal', 'none'));
        self::assertSame('hostile', $this->stage($c, 45, 'verbal', 'none'));
        self::assertSame('hostile', $this->stage($c, 100, 'verbal', 'hostile'), 'verbal : jamais de coups');
        self::assertSame('shove', $this->stage($c, 65, 'physical', 'none'));
        self::assertSame('punch', $this->stage($c, 85, 'physical', 'none'));
        self::assertSame('hostile', $this->stage($c, 100, 'physical', 'bluff'), 'le lâche qui bluffe ne frappe jamais');

        $traits = array_column($c['traits'], 0);
        foreach (['none', 'hostile', 'irritable', 'drunk', 'fanatic', 'resentful', 'proud', 'bluff'] as $t) {
            self::assertContains($t, $traits);
        }
        $trig = array_column($c['triggers'], 1, 0);
        foreach (['insulted', 'threatened', 'searched', 'papers', 'pushed', 'weapon', 'ordered', 'provoked', 'side', 'zeus'] as $t) {
            self::assertArrayHasKey($t, $trig, "déclencheur $t absent");
            self::assertGreaterThan(0, $trig[$t]);
        }
        // Un seul contrôle de papiers n'énerve personne ; une insulte agace ; une bousculade rend hostile ; l'ordre du Zeus passe aux coups.
        self::assertSame('calm', $this->stage($c, $trig['papers'], 'physical', 'none'));
        self::assertSame('irritated', $this->stage($c, $trig['insulted'], 'physical', 'none'));
        self::assertSame('punch', $this->stage($c, $trig['zeus'], 'physical', 'none'));
        self::assertLessThan($c['stages'][3][1], $trig['pushed'], 'une seule bousculade ne déclenche pas une bagarre');
    }

    public function testDamageIsCappedAndNeverLethalByDefault(): void
    {
        $c = $this->data('iarp_aggro.sqf');
        $d = array_column($c['damage'], 1, 0);
        $m = array_column($c['melee'], 1, 0);
        self::assertLessThanOrEqual(0.1, $d['punch'], 'coup de poing : petits dégâts');
        self::assertLessThan($d['punch'], $d['shove']);
        self::assertLessThan(1, $d['nonLethalCeil']);
        self::assertLessThanOrEqual(8, $m['maxSwings']);
        self::assertGreaterThanOrEqual(30, $m['rest'], 'repos entre deux bagarres : pas de boucle sans fin');
        self::assertGreaterThanOrEqual(1, $m['perVictim']);
        self::assertLessThanOrEqual(1.6, $m['reach']);

        self::assertEqualsWithDelta(0.08, $this->damage($c, 'punch', 50, false, 0, 0), 1e-9);
        self::assertSame(0.0, $this->damage($c, 'punch', 0, false, 0, 0), 'intensité 0 : aucun dégât');
        self::assertSame(0.0, $this->damage($c, 'shove', 50, false, $d['perMinute'], 0), 'plafond par minute atteint');

        // Une bagarre entière, à toutes les intensités : jamais plus que le plafond par minute, jamais au-delà du seuil non mortel.
        foreach ([25, 50, 75, 100] as $int) {
            foreach ([0.0, 0.3, 0.55] as $start) {
                $taken = 0.0;
                $victim = $start;
                for ($i = 0; $i < 3 * $m['maxSwings']; $i++) {
                    $hit = $this->damage($c, 'punch', $int, false, $taken, $victim);
                    $taken += $hit;
                    $victim += $hit;
                }
                self::assertLessThanOrEqual($d['perMinute'] * $int / 50 + 1e-9, $taken, "intensité $int : plafond par minute");
                self::assertLessThanOrEqual(max($start, $d['nonLethalCeil']) + 1e-9, $victim, "intensité $int : jamais mortel par défaut");
                self::assertLessThan(1, $victim);
            }
        }
        // « Mortel » coché : le seuil saute, le plafond par minute reste.
        self::assertGreaterThan(0, $this->damage($c, 'punch', 100, true, 0, 0.9));
        self::assertSame(0.0, $this->damage($c, 'punch', 100, true, $d['perMinute'] * 2, 0.9));
    }

    public function testHostileLinesCoverEveryLevelAndPersonaInFrenchAndEnglish(): void
    {
        $h = $this->data('iarp_hostile.sqf');
        self::assertSame(['irritated', 'hostile', 'brink', 'fight', 'after', 'flee'], $h['levels']);
        self::assertSame(['*', 'irritable', 'drunk', 'fanatic', 'resentful', 'proud', 'bluff'], $h['personas']);

        $cells = [];
        $allFr = [];
        $check = function (array $lines, string $where) use (&$allFr): void {
            self::assertGreaterThanOrEqual(3, count($lines), "$where : 3 répliques au moins");
            $seen = [];
            foreach ($lines as $pair) {
                self::assertCount(2, $pair, "$where : [français, anglais]");
                [$fr, $en] = $pair;
                self::assertNotSame('', trim($fr), "$where : français vide");
                self::assertNotSame('', trim($en), "$where : anglais vide");
                self::assertStringNotContainsString('"', $fr . $en, "$where : guillemet droit");
                self::assertNotContains($fr, $seen, "$where : réplique en double");
                $seen[] = $fr;
                $allFr[] = $fr;
                // Jeu de rôle : langage cru, jamais d'injure visant une origine, une religion, un sexe ou une orientation, rien de sexuel.
                self::assertDoesNotMatchRegularExpression('/(?<![\pL])(putes?|salopes?|pédés?|tapettes?|bites?|viols?|violer|nègres?|bougnoules?|youpins?|faggots?|whores?|bitch(es)?|raped?|nigg\w*|retards?)(?![\pL])/iu',
                    $fr . ' ' . $en, "$where : mot interdit");
            }
        };
        foreach ($h['lines'] as [$level, $persona, $lines]) {
            self::assertContains($level, $h['levels']);
            self::assertContains($persona, $h['personas']);
            $cells["$level|$persona"] = ($cells["$level|$persona"] ?? 0) + count($lines);
            $check($lines, "$level / $persona");
        }
        foreach ($h['levels'] as $level) {
            foreach ($h['personas'] as $persona) {
                self::assertGreaterThanOrEqual(3, $cells["$level|$persona"] ?? 0, "palier $level, caractère $persona : 3 répliques au moins");
            }
        }
        foreach (['triggers' => ['insulted', 'searched', 'papers', 'pushed', 'weapon', 'ordered'], 'registers' => ['street', 'rural', 'educated', 'foreign'],
            'countries' => ['FRA', 'DEU', 'USA', 'AUT', 'BEL']] as $section => $ids) {
            $have = array_column($h[$section], 1, 0);
            foreach ($ids as $id) {
                self::assertArrayHasKey($id, $have, "$section : $id absent");
                $check($have[$id], "$section / $id");
            }
        }
        // Les répliques demandées par TTRD y sont.
        foreach (['Je vais te casser ta gueule !', 'Touche-moi encore et je te défonce.', 'Dégage.', "T'as pas honte ?", 'Casse-toi de chez moi !',
            'Je vais te péter la mâchoire, tu m\'entends ?'] as $line) {
            self::assertContains($line, $allFr, "réplique « $line » absente");
        }
        self::assertGreaterThanOrEqual(200, count($allFr));

        // Tirage sans redite (8 dernières) et pondéré : palier × 3, déclencheur × 2, registre et pays × 1.
        $fn = $this->read('functions/fn_iarpAggro.sqf');
        self::assertStringContainsString('if ((count _rec) > 8) then { _rec deleteAt 0; };', $fn);
        self::assertStringContainsString('private _fresh = _pool select { !((_x select 0) in _recent) };', $fn);
        self::assertStringContainsString('[_lines, 3] call _add;', $fn);
        self::assertStringContainsString('[_x select 1, 2] call _add;', $fn);
    }

    public function testSettingsAreOffByDefaultAndExplainedWithAnExample(): void
    {
        $set = $this->read('functions/fn_iarpSettings.sqf');
        $strings = $this->strings();
        self::assertMatchesRegularExpression('/\["comspec_sse_iarp_aggro", "LIST", "STR_COMSPEC_IARP_S_Aggro", \[\["off", "verbal", "physical"\],.*?, 0\]\] call _add;/s', $set, 'agressivité : « Jamais » par défaut');
        self::assertStringContainsString('["comspec_sse_iarp_aggroLethal", "CHECKBOX", "STR_COMSPEC_IARP_S_AggroLethal", false] call _add;', $set);
        self::assertStringContainsString('["comspec_sse_iarp_aggroKo", "CHECKBOX", "STR_COMSPEC_IARP_S_AggroKo", false] call _add;', $set);
        self::assertStringContainsString('["comspec_sse_iarp_aggroWeapon", "CHECKBOX", "STR_COMSPEC_IARP_S_AggroWeapon", false] call _add;', $set);
        self::assertStringContainsString('["comspec_sse_iarp_aggroDamage", "SLIDER", "STR_COMSPEC_IARP_S_AggroDamage", [0, 100, 50, 0]] call _add;', $set);
        self::assertMatchesRegularExpression('/\["comspec_sse_iarp_aggroMelee", "LIST", "STR_COMSPEC_IARP_S_AggroMelee", \[\["auto", "tsp", "sse"\]/', $set);
        self::assertStringContainsString('["comspec_sse_iarp_aggroTspAnim", "CHECKBOX", "STR_COMSPEC_IARP_S_AggroTspAnim", true] call _add;', $set);
        foreach (['Aggro', 'AggroDamage', 'AggroLethal', 'AggroKo', 'AggroWeapon', 'AggroMelee', 'AggroTspAnim'] as $k) {
            foreach (['_T', '_D'] as $suffix) {
                $id = 'STR_COMSPEC_IARP_S_' . $k . $suffix;
                self::assertArrayHasKey($id, $strings, "$id absente");
                self::assertNotSame('', $strings[$id]['fr']);
                self::assertNotSame('', $strings[$id]['en']);
            }
            self::assertStringContainsString('Exemple', $strings['STR_COMSPEC_IARP_S_' . $k . '_D']['fr'], "$k : réglage sans exemple");
            self::assertStringContainsString('Example', $strings['STR_COMSPEC_IARP_S_' . $k . '_D']['en'], "$k : setting without an example");
        }
        self::assertSame('Automatique (TSP Melee si présent)', $strings['STR_COMSPEC_IARP_S_AggroMelee_auto']['fr']);
        self::assertSame('Intégré SSE', $strings['STR_COMSPEC_IARP_S_AggroMelee_sse']['fr']);
    }

    public function testEdenAttributesAndZeusMenu(): void
    {
        $cfg = $this->read('config.cpp');
        $strings = $this->strings();
        foreach (['Mode' => 'comspec_sse_aggroMode', 'Trait' => 'comspec_sse_aggroTrait', 'Dmg' => 'comspec_sse_aggroDmg', 'Lethal' => 'comspec_sse_aggroLethal'] as $k => $var) {
            self::assertStringContainsString('class comspec_sse_iarpAggro' . $k . ' {', $cfg);
            self::assertStringContainsString("_this setVariable ['$var', _value, true];", $cfg);
            foreach (['STR_COMSPEC_IARP_Z_Aggro' . $k, 'STR_COMSPEC_IARP_E_Aggro' . $k . '_D', 'STR_COMSPEC_IARP_Z_Aggro' . $k . '_D'] as $id) {
                self::assertArrayHasKey($id, $strings, "$id absente");
                self::assertNotSame('', $strings[$id]['fr']);
                self::assertNotSame('', $strings[$id]['en']);
            }
            self::assertStringContainsString('Exemple', $strings['STR_COMSPEC_IARP_E_Aggro' . $k . '_D']['fr']);
        }
        preg_match_all('/name = "\$(STR_COMSPEC_IARP_[ZS]_Aggro\w*)"/', $cfg, $m);
        self::assertNotEmpty($m[1]);
        foreach (array_unique($m[1]) as $id) {
            self::assertArrayHasKey($id, $strings, "$id absente");
        }
        foreach (['class iarpAggro {};', 'class iarpMelee {};'] as $fn) {
            self::assertStringContainsString($fn, $cfg);
        }
        // TSP : dépendances souples seulement.
        self::assertDoesNotMatchRegularExpression('/requiredAddons\[\][^;]*tsp_/', $cfg);

        $zen = $this->read('functions/fn_iarpZen.sqf');
        self::assertStringContainsString('["_aggro", "STR_COMSPEC_IARP_Z_Aggro"', $zen);
        self::assertStringContainsString('["aggroSet", [_mode, _trait,', $zen);
        preg_match_all('/STR_COMSPEC_IARP_Z_Aggro\w*/', $zen, $z);
        foreach (array_unique($z[0]) as $id) {
            if (str_ends_with($id, '_')) {
                continue;
            }
            self::assertArrayHasKey($id, $strings, "$id absente");
        }
        foreach (['auto', 'none', 'hostile', 'irritable', 'drunk', 'fanatic', 'resentful', 'proud', 'bluff'] as $t) {
            self::assertArrayHasKey('STR_COMSPEC_IARP_Z_AggroTrait_' . $t, $strings);
        }
        self::assertStringContainsString('["_aggro", "Aggro"]', $this->read('functions/fn_iarpZenHub.sqf'));
        self::assertArrayHasKey('STR_COMSPEC_IARP_H_Aggro', $strings);
        self::assertArrayHasKey('STR_COMSPEC_IARP_H_Aggro_D', $strings);

        $srv = $this->read('functions/fn_iarpServer.sqf');
        self::assertMatchesRegularExpression('/case "aggroSet": \{\s*if !\(\[_me\] call comspec_sse_fnc_isAuthority\) exitWith \{\};/', $srv, 'réglage réservé au Zeus ou à l\'administrateur');
        self::assertStringContainsString('private _agg = ["say", [_t, _me, _list, _cmd, _norm]] call comspec_sse_fnc_iarpAggro;', $srv);
        self::assertStringContainsString('_outcome = "hostile : refuse l\'ordre";', $srv);
        self::assertStringContainsString('["reset", [_t]] call comspec_sse_fnc_iarpAggro;', $srv, 'le « calme » du Zeus éteint aussi la colère');
    }

    public function testGuardRailsAndSoftDependencies(): void
    {
        $aggro = $this->read('functions/fn_iarpAggro.sqf');
        $melee = $this->read('functions/fn_iarpMelee.sqf');
        $xeh = $this->read('XEH_postInit.sqf');

        // Jamais contre une IA ; rien quand le réglage est coupé, sous le « calme » du Zeus ou en zone sans agressivité.
        self::assertStringContainsString('{!isPlayer _by}', $aggro);
        self::assertStringContainsString('if ((_c get "mode") isEqualTo "off") exitWith { _none };', $aggro);
        self::assertStringContainsString('missionNamespace getVariable ["comspec_sse_iarp_aggro", "off"]', $aggro);
        self::assertStringContainsString('"comspec_sse_aggroCalm"', $aggro);
        self::assertStringContainsString('"comspec_sse_aggroSafe"', $aggro);
        self::assertStringContainsString('"colere" in', $aggro);
        // Une bagarre à la fois, repos ensuite, nombre d'IA par joueur borné, arme sortie seulement si « mortel ».
        self::assertStringContainsString('{time < (_s get "rest")}', $aggro);
        self::assertStringContainsString('_melee getOrDefault ["perVictim", 2]', $aggro);
        self::assertStringContainsString('{_c get "weapon"} && {_c get "lethal"}', $aggro);
        // Plafonds revérifiés avant chaque coup, dégâts calculés par la règle testée, auteur vide pour ACE.
        self::assertStringContainsString('exitWith { ["cap"] call _stop; };', $melee);
        self::assertStringContainsString('["damage", [_swing, _int, _lethal, _sum, damage _v]] call comspec_sse_fnc_iarpAggro', $melee);
        self::assertStringContainsString('"punch", objNull] call ace_medical_fnc_addDamageToUnit;', $melee);
        self::assertStringContainsString('if (!isDamageAllowed _v) exitWith {};', $melee);

        // TSP Melee et TSP Animate : seulement s'ils sont chargés, jamais requis ; TSP Melee applique ses propres dégâts.
        self::assertStringContainsString('isClass (configFile >> "CfgPatches" >> "tsp_melee")', $aggro);
        self::assertStringContainsString('{!isNil "tsp_fnc_melee_action"}', $melee);
        self::assertStringContainsString('spawn tsp_fnc_melee_action;', $melee);
        self::assertStringContainsString('"tsp_melee_damageMan"', $melee);
        self::assertStringContainsString('isClass (configFile >> "CfgPatches" >> "tsp_animate")', $melee);
        self::assertStringContainsString('isClass (configFile >> "CfgGesturesMale" >> "States" >> _x)', $melee);
        self::assertStringNotContainsString('tsp_fnc_melee_ai', $melee . $aggro, 'la boucle IA de TSP Melee ne s\'arrête jamais : jamais appelée');
        // Sons du jeu (fichiers d'Arma 3).
        self::assertStringContainsString('a3\sounds_f\characters\human-sfx\P01\Low_Hit_0%1.wss', $melee);
        self::assertStringContainsString('a3\sounds_f\characters\movements\bodyfalls\bodyfall_concrete_%1.wss', $melee);

        foreach (['"comspec_sse_iarpMelee"', '"comspec_sse_iarpMeleeHit"', '"comspec_sse_iarpAggroEv"', '"comspec_sse_idAsked"'] as $ev) {
            self::assertStringContainsString($ev, $xeh);
        }
        // Aucune fenêtre d'alerte du jeu.
        foreach ([$aggro, $melee] as $src) {
            foreach (['hint ', 'hintSilent', 'systemChat', 'titleText', 'cutText', 'BIS_fnc_showNotification', 'zen_common_fnc_showMessage', 'comspec_sse_fnc_toast'] as $cmd) {
                self::assertStringNotContainsString($cmd, $src, "$cmd interdit");
            }
        }
        // Auto-test en jeu.
        $self = $this->read('functions/fn_iarpSelfTest.sqf');
        self::assertStringContainsString('["stage", [_anger, _mode, _trait]] call comspec_sse_fnc_iarpAggro', $self);
        self::assertStringContainsString('["damage", _args] call comspec_sse_fnc_iarpAggro', $self);
    }
}
