<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Atak\AtakUnitRowDedupe;
use PHPUnit\Framework\TestCase;
use Tests\Support\ModVersion;

/**
 * Carte du téléphone : un allié n'est plus affiché des dizaines de fois (pistes périmées empilées) ; dédoublonnage
 * défensif côté site (une ligne par indicatif) et côté téléphone (pistes Athena périmées).
 */
final class AtakUnitDuplicateMarkersTest extends TestCase
{
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/';
    private const REFRESH = 'functions/map/fn_localDataRefresh.sqf';

    private function read(string $rel): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/' . $rel));
    }

    private function strip(string $sqf): string
    {
        $sqf = (string) preg_replace('/"(?:[^"]|"")*"/s', '""', $sqf);
        $sqf = (string) preg_replace('#/\*.*?\*/#s', '', $sqf);

        return (string) preg_replace('#//[^\n]*#', '', $sqf);
    }

    public function testSqfDelimitersAreBalanced(): void
    {
        $code = $this->strip($this->read(self::MAIN . self::REFRESH));
        self::assertSame(substr_count($code, '{'), substr_count($code, '}'));
        self::assertSame(substr_count($code, '['), substr_count($code, ']'));
        self::assertSame(substr_count($code, '('), substr_count($code, ')'));
    }

    public function testNoOnScreenNotificationInTouchedFile(): void
    {
        $code = $this->strip($this->read(self::MAIN . self::REFRESH));
        foreach (['systemChat', 'hintSilent', 'hint ', 'cutText', 'titleText'] as $banned) {
            self::assertStringNotContainsString($banned, $code, $banned);
        }
    }

    public function testStaleGhostDedupeIsWiredAndLogIsOffByDefault(): void
    {
        $sqf = $this->read(self::MAIN . self::REFRESH);
        self::assertStringContainsString('_gGhosts', $sqf);
        self::assertStringContainsString('> 1800', $sqf);
        self::assertStringContainsString('missionNamespace getVariable ["COMSPEC_ATAK_DiagDup", false]', $sqf);
        // Le dédoublonnage passe avant la publication de la table des unités.
        self::assertLessThan(strpos($sqf, '["units",_units'), strpos($sqf, '_gGhosts sort false'));
        // Jamais une piste en direct, une unité du jeu ou un téléphone tracé.
        self::assertStringContainsString('isNull (_ge getOrDefault ["object", objNull])', $sqf);
        self::assertStringContainsString('_ge getOrDefault ["phone", false]', $sqf);
    }

    public function testRowsWithSameCallSignCollapseToBestLink(): void
    {
        $rows = [
            ['id' => 1, 'call_sign' => 'Y 31', 'status' => 'offline', 'age_seconds' => 5],
            ['id' => 2, 'call_sign' => 'Alpha', 'status' => 'linked', 'age_seconds' => 1],
            ['id' => 3, 'call_sign' => ' y 31 ', 'status' => 'linked', 'age_seconds' => 9],
            ['id' => 4, 'call_sign' => 'Y 31', 'status' => 'linked', 'age_seconds' => 2],
        ];
        $out = AtakUnitRowDedupe::byCallSign($rows);
        self::assertCount(2, $out);
        self::assertSame(4, $out[0]['id']);
        self::assertSame(2, $out[1]['id']);
    }

    public function testRowsWithoutCallSignAreAllKeptAndOrderIsStable(): void
    {
        $rows = [
            ['id' => 1, 'call_sign' => ''],
            ['id' => 2],
            'x',
            ['id' => 3, 'call_sign' => 'B', 'status' => 'linked'],
            ['id' => 4, 'call_sign' => 'B', 'status' => 'linked'],
        ];
        $out = AtakUnitRowDedupe::byCallSign($rows);
        self::assertCount(4, $out);
        self::assertSame(1, $out[0]['id']);
        self::assertSame(3, $out[3]['id']);
    }

    public function testControllerAppliesDedupe(): void
    {
        $php = $this->read('app/Controllers/Api/AtakApiController.php');
        self::assertStringContainsString('AtakUnitRowDedupe::byCallSign($this->unitsForTransmission', $php);
    }

    public function testVersionsAndChangelogs(): void
    {
        $hpp = $this->read(self::MAIN . 'script_component.hpp');
        $v = ModVersion::match('/#define VERSION_STR "([0-9.]+)"/', $hpp);
        ModVersion::assertAtLeast('4.24.3', $v);
        self::assertStringContainsString('#define LINK_VERSION_STR "2.0.127"', $hpp);
        $log = $this->read('CHANGELOG-ATAK.md');
        self::assertMatchesRegularExpression('/\n## Carte du téléphone : plus de symboles d\'alliés empilés[^\n]+\(ATAK natif 4\.24\.3\.AG - Washington, COMSPEC Link 2\.0\.126\)\n\n### Résumé joueur\nType : correctif\nFR : [^\n]+\n(- [^\n]+\n)+EN : [^\n]+\n(- [^\n]+\n)+/', $log);
        self::assertStringContainsString('[h1]COMSPEC ATAK 4.24.3 Washington', $this->read('mod/COMSPEC_ATAK_Native/publisher/STEAM_CHANGELOG.txt'));
    }
}
