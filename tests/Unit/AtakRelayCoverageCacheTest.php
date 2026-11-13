<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * ATAK natif, relais : couverture incrémentale et caches (audit docs/audit/2026-11-relais.md).
 * Le calcul lui-même tourne en jeu (fn_devMeshTest, bouton TESTS) ; ici, ce qui doit rester vrai dans les sources.
 */
final class AtakRelayCoverageCacheTest extends TestCase
{
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main';

    private function read(string $rel): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $rel);
    }

    public function testCoverageIsIncrementalAndSigned(): void
    {
        $net = $this->read(self::MAIN . '/functions/network/fn_relayNet.sqf');
        foreach (['"covBand"', '"covAccel"', '"covSig"', '"covDirty"'] as $act) {
            self::assertStringContainsString('case ' . $act . ':', $net, $act);
        }
        // Même signature : rien n'est recalculé. Relais modifiés seulement : cases hors de leur portée reprises.
        self::assertStringContainsString('if (_sig isEqualTo (_old param [9, []])) exitWith', $net);
        self::assertStringContainsString('["covDirty", [_old param [9, []], _sig]] call comspec_atak_native_fnc_relayNet', $net);
        self::assertStringContainsString('_kept = _kept + 1;', $net);
        // La signature porte tout ce dont dépend une case : réalisme, météo, brouilleurs, relais.
        self::assertStringContainsString('["realism"] call comspec_atak_native_fnc_netCond', $net);
        self::assertStringContainsString('round (10 * rain)', $net);
        // Le format historique (8 premiers éléments) ne change pas : les lecteurs existants testent « count >= 8 ».
        self::assertMatchesRegularExpression(
            '/setVariable \["COMSPEC_ATAK_Coverage", \[_center, _cell, _cells, _radius, _pct, dayTime, count _relays, [^\n]+\n\s+\["covAccel", \[_cells, _center, _cell, _n\]\] call comspec_atak_native_fnc_relayNet, _sig, _kept\]\];/',
            str_replace("\r\n", "\n", $net)
        );
    }

    public function testCoverageJammersMatchTheRelayList(): void
    {
        $net = str_replace("\r\n", "\n", $this->read(self::MAIN . '/functions/network/fn_relayNet.sqf'));
        // Brouillage GPS seul ou d'un canal du maillage : sans effet sur les relais, donc sans zone blanche sur la couverture.
        $filter = '{(_x param [7, 0]) isEqualTo 0} && {(_x param [8, ""]) isNotEqualTo "gps"}';
        self::assertGreaterThanOrEqual(3, substr_count($net, $filter), 'liste, signature et calcul de couverture');
    }

    public function testPointLookupUsesTheIndexAndNeverNegativeParam(): void
    {
        $net = $this->read(self::MAIN . '/functions/network/fn_relayNet.sqf');
        self::assertStringContainsString('private _ci = _idx getOrDefault [_i * _n + _j, -1];', $net);
        self::assertStringContainsString('if (_ci < 0) then { continue };', $net);
        // « param [-1] » rend le dernier élément : un index absent ne doit jamais y arriver.
        self::assertStringNotContainsString('param [_idx getOrDefault', $net);
        self::assertStringNotContainsString('param [_oldIdx getOrDefault', $net);
    }

    public function testMapDrawsMergedBandsWithFallback(): void
    {
        $draw = $this->read(self::MAIN . '/functions/map/fn_meshDraw.sqf');
        self::assertStringContainsString('private _runs = (_cv param [8, []]) param [2, []];', $draw);
        self::assertStringContainsString('_map drawRectangle [_p, _ra, _rb, 0, _cols select _band, _fill];', $draw);
        self::assertStringContainsString('["covBand", [_mb]] call comspec_atak_native_fnc_relayNet', $draw);
    }

    public function testRelayHopAndMeshPairCaches(): void
    {
        $net = $this->read(self::MAIN . '/functions/network/fn_relayNet.sqf');
        self::assertStringContainsString('COMSPEC_ATAK_RelayHopCache', $net);
        self::assertStringContainsString('diag_tickTime - (_hit select 0) < 15', $net);
        $graph = $this->read(self::MAIN . '/functions/network/fn_meshGraph.sqf');
        // Clé de position calculée une fois par nœud et par passe, plus par paire.
        self::assertStringContainsString('private _pk = _nodes apply { [_x select 6] call _posKey };', $graph);
        self::assertStringContainsString('_pk select _i, _pk select _j] call _marginOf', $graph);
        self::assertStringNotContainsString('private _k = str ([[_kb, _ka', $graph);
    }

    public function testTowersPublishTheRelayListOnce(): void
    {
        $tower = $this->read(self::MAIN . '/functions/network/fn_relayTower.sqf');
        self::assertStringContainsString('COMSPEC_ATAK_TowerListPending', $tower);
        self::assertSame(1, substr_count($tower, 'setVariable ["COMSPEC_AtakRelays", (missionNamespace getVariable ["COMSPEC_AtakRelays", []]) select { !isNull _x }, true]'));
    }

    public function testInGameSelfChecksCoverTheCoverageCache(): void
    {
        $test = $this->read(self::MAIN . '/functions/devmesh/fn_devMeshTest.sqf');
        foreach ([
            'couverture : même grille, mêmes bandes',
            'couverture : les bandes couvrent toutes les cases',
            'couverture : relais déplacé, deux disques',
            'couverture : index et parcours d\'accord',
        ] as $name) {
            self::assertStringContainsString($name, $test, $name);
        }
    }
}
