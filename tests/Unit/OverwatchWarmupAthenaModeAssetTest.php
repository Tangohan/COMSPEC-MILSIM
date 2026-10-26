<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * TEST-003 — le « Warmup » de COMSPEC Link (file d'envoi relue, connexion TLS ouverte à l'avance vers Athena par une
 * requête HEAD) n'est lancé à l'arrivée en mission (XEH_postInit du connect) qu'avec le service Athéna (« on »).
 * En « off » (ATAK local) ou en mode full local imposé par le serveur, fn_athenaMode promet « aucune connexion ».
 *
 * Test statique (lecture des .sqf, commentaires retirés). Il échoue si le Warmup redevient inconditionnel, si la garde
 * change de condition, si le Warmup quitte le postInit pour le bloc CBA_settingsInitialized (cas normal retardé), ou
 * si fn_initAuth ne préchauffe plus après le « Oui » d'un premier lancement.
 */
final class OverwatchWarmupAthenaModeAssetTest extends TestCase
{
    private const CONNECT = 'mod/UptoDate/Sources/comspec-overwatch-addons/connect/';

    private const GUARD = '~if\s*\(\s*\(\s*\[\]\s*call\s+comspec_overwatch_connect_fnc_athenaMode\s*\)\s*isEqualTo\s*"on"\s*\)\s*then\s*\{([^{}]*)\}~';

    private function code(string $rel): string
    {
        $src = file_get_contents(dirname(__DIR__, 2) . '/' . self::CONNECT . $rel);
        self::assertNotFalse($src, 'Source illisible : ' . $rel);

        // Commentaires retirés : un appel cité dans un commentaire ne compte pas.
        return (string) preg_replace('~//[^\n]*~', '', (string) $src);
    }

    public function testClientPostInitWarmsUpOnlyWithAthenaServiceOn(): void
    {
        $post = $this->code('XEH_postInit.sqf');
        self::assertSame(1, substr_count($post, 'callExtension "Warmup"'), 'Un seul Warmup attendu dans XEH_postInit (gardé par le mode)');
        self::assertSame(1, preg_match(self::GUARD, $post, $m, PREG_OFFSET_CAPTURE), 'Garde « athenaMode == on » introuvable dans XEH_postInit');
        self::assertStringContainsString('callExtension "Warmup"', $m[1][0], 'Le Warmup de XEH_postInit n\'est plus sous la garde du mode Athéna');
    }

    public function testWarmupIsNotDelayedToTheCbaSettingsBlock(): void
    {
        // Cas normal (« on ») : même moment qu'avant, à l'arrivée, avant le journal de session et bien avant le bloc
        // CBA_settingsInitialized (poignée de main Athena).
        $post = $this->code('XEH_postInit.sqf');
        $warm = strpos($post, 'callExtension "Warmup"');
        self::assertNotFalse($warm);
        $client = strpos($post, 'if (!hasInterface) exitWith {};');
        $log = strpos($post, 'comspec_overwatch_connect_fnc_startLogSession');
        $boot = strpos($post, 'COMSPEC_CbaSettingsBootCode = {');
        self::assertNotFalse($client);
        self::assertNotFalse($log);
        self::assertNotFalse($boot);
        self::assertGreaterThan($client, $warm, 'Le Warmup doit rester dans la partie joueur du postInit');
        self::assertLessThan($log, $warm, 'Le Warmup doit rester avant le journal de session');
        self::assertLessThan($boot, $warm, 'Le Warmup ne doit pas attendre CBA_settingsInitialized');
    }

    public function testFirstLaunchStillWarmsUpAfterYes(): void
    {
        // Premier lancement (mode "") : pas de Warmup au postInit ; après « Oui », la poignée de main appelle
        // fn_waitAthenaReady puis fn_initAuth, qui préchauffe lui-même avant Init, une fois le mode vérifié.
        $auth = $this->code('functions/auth/fn_initAuth.sqf');
        $check = strpos($auth, '([] call comspec_overwatch_connect_fnc_athenaMode) isNotEqualTo "on") exitWith');
        $warm = strpos($auth, 'callExtension "Warmup"');
        $init = strpos($auth, '"COMSPECExtension", "Init"');
        self::assertNotFalse($check, 'fn_initAuth ne vérifie plus le mode Athéna');
        self::assertNotFalse($warm, 'fn_initAuth ne préchauffe plus la DLL');
        self::assertNotFalse($init);
        self::assertLessThan($warm, $check);
        self::assertLessThan($init, $warm);
        self::assertStringContainsString('comspec_overwatch_connect_fnc_initAuth', $this->code('functions/fn_waitAthenaReady.sqf'));
    }
}
