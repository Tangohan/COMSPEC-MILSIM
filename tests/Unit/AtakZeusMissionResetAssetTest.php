<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * DEF-028 — état Zeus de la partie précédente (annonces, alarme, écran qui bugue et numéro de sa boucle) remis à zéro au
 * preInit de chaque mission (fn_stateInit), et non plus au premier appel de fn_zeusClient (jusqu'à 2 s après
 * l'arrivée). Pendant cette fenêtre, fn_zeusOverlay pouvait reprendre l'écran qui bugue de l'ancienne mission, ou
 * lancer une seconde boucle si la remise tardive effaçait la poignée d'une boucle vivante.
 *
 * Test statique (lecture des .sqf). Il échoue si une de ces remises quitte fn_stateInit, si fn_zeusClient réécrit
 * la poignée de boucle (remise tardive revenue), ou si la lecture de la poignée ne suit plus « < 0 » avec -1 par défaut.
 */
final class AtakZeusMissionResetAssetTest extends TestCase
{
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/';

    private function code(string $rel): string
    {
        $src = file_get_contents(dirname(__DIR__, 2) . '/' . self::MAIN . $rel);
        self::assertNotFalse($src, 'Source illisible : ' . $rel);

        // Commentaires retirés : un nom cité dans un commentaire ne compte pas.
        return (string) preg_replace('~//[^\n]*~', '', (string) $src);
    }

    public function testStateInitResetsZeusStateAtEachMission(): void
    {
        $init = $this->code('functions/core/fn_stateInit.sqf');
        self::assertMatchesRegularExpression('~uiNamespace\s+setVariable\s*\[\s*"COMSPEC_ATAK_ZeusGlitchPfh"\s*,\s*-1\s*\]~', $init);
        self::assertSame(1, preg_match('~\{\s*uiNamespace\s+setVariable\s*\[\s*_x\s*,\s*nil\s*\]\s*;?\s*\}\s*forEach\s*\[([^\]]*"COMSPEC_ATAK_ZeusSeen"[^\]]*)\]~', $init, $m), 'Liste nil des variables Zeus introuvable dans fn_stateInit');
        foreach (['ZeusSeen', 'ZeusSig', 'ZeusNoise', 'ZeusNoiseAt', 'ZeusGlitch'] as $v) {
            self::assertStringContainsString('"COMSPEC_ATAK_' . $v . '"', $m[1], 'COMSPEC_ATAK_' . $v . ' n\'est plus remise à nil par fn_stateInit');
        }
    }

    public function testZeusClientNoLongerResetsTheLoopHandleLate(): void
    {
        $client = $this->code('functions/zeus/fn_zeusClient.sqf');
        self::assertDoesNotMatchRegularExpression('~setVariable\s*\[\s*"COMSPEC_ATAK_ZeusGlitchPfh"~', $client, 'fn_zeusClient réécrit la poignée de la boucle de l\'écran qui bugue');
        self::assertStringNotContainsString('COMSPEC_ATAK_ZeusClientInit', $client);
    }

    public function testOverlayStartsTheLoopOnlyWithoutLiveHandle(): void
    {
        // -1 n'est la bonne valeur que si la boucle n'est lancée que pour une poignée négative.
        self::assertStringContainsString(
            '(uiNamespace getVariable ["COMSPEC_ATAK_ZeusGlitchPfh", -1]) < 0',
            $this->code('functions/zeus/fn_zeusOverlay.sqf')
        );
    }
}
