<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * DEF-019 — touche ATAK sans téléphone (raison « item » de fn_canUse) : retour sonore seul. La notification
 * « Pas de terminal ATAK · Il faut porter : … » était rangée dans le centre d'un téléphone absent, comptée comme non
 * lue, puis lue plus tard, périmée, à l'ouverture d'un téléphone ramassé.
 *
 * Test statique (lecture de fn_deviceDenied et fn_canUse, commentaires retirés). Il échoue si « item » notifie de
 * nouveau (cas default ou cas « item » ajouté), si le son disparaît ou passe derrière la limite des 30 s, ou si les
 * autres raisons (Zeus, surchauffe, batterie, téléphone détruit) ne notifient plus.
 */
final class AtakDeviceDeniedNoPhoneAssetTest extends TestCase
{
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/';

    private function code(string $rel): string
    {
        $src = file_get_contents(dirname(__DIR__, 2) . '/' . self::MAIN . $rel);
        self::assertNotFalse($src, 'Source illisible : ' . $rel);

        return (string) preg_replace('~//[^\n]*~', '', (string) $src);
    }

    /** @return array<string, string> corps de chaque branche du switch (_why), « default » compris */
    private function branches(): array
    {
        $src = $this->code('functions/core/fn_deviceDenied.sqf');
        $at = strpos($src, 'switch (_why) do {');
        self::assertNotFalse($at, 'switch (_why) introuvable');
        $out = [];
        $re = '~(?:case\s+"([a-z]+)"|(default))\s*:?\s*\{((?:[^{}]|\{(?3)\})*)\}~';
        self::assertGreaterThan(0, preg_match_all($re, substr($src, $at + strlen('switch (_why) do {')), $m, PREG_SET_ORDER));
        foreach ($m as $b) {
            $out[$b[1] !== '' ? $b[1] : 'default'] = $b[3];
        }

        return $out;
    }

    public function testNoPhoneGivesNoNotification(): void
    {
        $canUse = $this->code('functions/core/fn_canUse.sqf');
        self::assertMatchesRegularExpression('~fnc_hasDevice\)\s*exitWith\s*\{\s*"item"\s*\}~', $canUse, 'fn_canUse ne renvoie plus « item » sans téléphone');

        $b = $this->branches();
        self::assertArrayHasKey('default', $b);
        self::assertStringNotContainsString('fnc_notify', $b['default'], 'Le cas default (« item ») notifie de nouveau');
        self::assertStringNotContainsString('fnc_notify', $b['item'] ?? '', 'Un cas « item » notifie de nouveau');
    }

    public function testSoundStaysImmediateAndUnthrottled(): void
    {
        $src = $this->code('functions/core/fn_deviceDenied.sqf');
        $sound = strpos($src, 'playSound "addItemFailed"');
        $limit = strpos($src, 'COMSPEC_ATAK_DeniedLast');
        $switch = strpos($src, 'switch (_why) do {');
        self::assertNotFalse($sound, 'Retour sonore disparu');
        self::assertNotFalse($limit);
        self::assertNotFalse($switch);
        self::assertLessThan($limit, $sound, 'Le son doit rester avant la limite d\'une notification par raison toutes les 30 s');
        self::assertLessThan($switch, $sound);
    }

    public function testOtherReasonsStillNotify(): void
    {
        $b = $this->branches();
        foreach (['zeus', 'heat', 'battery', 'broken'] as $why) {
            self::assertArrayHasKey($why, $b, 'Cas « ' . $why . ' » disparu');
            self::assertStringContainsString('call comspec_atak_native_fnc_notify', $b[$why], 'Le cas « ' . $why . ' » ne notifie plus');
        }
        self::assertStringNotContainsString('fnc_notify', $b['down'] ?? '', 'Mort ou inconscient : rangé sans message');
    }
}
