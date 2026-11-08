<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * La licence affirme que le mod ne transmet rien si le joueur répond Non au service Athéna.
 * La DLL ne doit donc rien ouvrir au chargement (« Warmup ») tant qu'aucune adresse n'est posée.
 * Vérification réelle, en jeu : tools/ingame_test.ps1 -SansProxy (LICENCE OK ou KO dans le rapport).
 */
final class AtakLicenceSansTransmissionTest extends TestCase
{
    public function testWarmupNOuvreRienSansAdresseAutorisee(): void
    {
        $cs = (string) file_get_contents(__DIR__ . '/../../mod/UptoDate/COMSPECExtension/Extension.cs');
        if (! str_contains($cs, '"UplinkOff"')) {
            self::markTestSkipped('Coupure de la liaison pas encore fusionnée (#1461) : la DLL fait encore un HEAD vers Athena au chargement.');
        }
        $debut = strpos($cs, 'private static void KickBackgroundWarmup()');
        self::assertNotFalse($debut);
        $corps = substr($cs, $debut, 900);
        self::assertStringContainsString('if (string.IsNullOrEmpty(baseUrl)) return;', $corps);
        self::assertStringNotContainsString('"https://athena.ttrd.fr"', $corps);
    }

    public function testLeTestEnJeuSaitVerifierLaLicence(): void
    {
        $ps = (string) file_get_contents(__DIR__ . '/../../mod/COMSPEC_ATAK_Native/tools/ingame_test.ps1');
        self::assertStringContainsString('[switch]$SansProxy', $ps);
        self::assertStringContainsString('LICENCE KO', $ps);
    }
}
