<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * ATAK natif, autorisations multijoueur : ce que le serveur et les clients ne croient plus d'un autre poste, et les
 * chemins légitimes du serveur qu'un contrôle d'émetteur trop large casserait.
 */
final class AtakMultiplayerAuthTest extends TestCase
{
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main';

    private function read(string $rel): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $rel);
    }

    /** Texte de $src qui va du repère $from (inclus) au repère $to suivant (exclu) ; vide si l'un manque. */
    private function between(string $src, string $from, string $to): string
    {
        $i = strpos($src, $from);
        $j = $i === false ? false : strpos($src, $to, $i + strlen($from));
        return $j === false ? '' : substr($src, (int) $i, $j - (int) $i);
    }

    public function testAdminRecoverStripsAServerLocalAiItself(): void
    {
        // « Reprendre le téléphone » (app Admin) tourne dans la pile du remoteExec de l'admin. Par l'événement
        // capLocal, CBA appelle le gestionnaire d'une IA locale au serveur dans cette même pile, et fn_captureServer y
        // refuse l'action « local » : l'IA gardait le téléphone alors que l'app annonçait « Téléphone repris ».
        $recover = $this->between($this->read(self::MAIN . '/functions/admin/fn_adminServer.sqf'), 'case "recover": {', '"Téléphone repris à l\'IA');
        self::assertNotEmpty($recover);
        self::assertMatchesRegularExpression('/if \(local _ai\) then \{\s*if \(_x in \(assignedItems _ai\)\) then \{ _ai unlinkItem _x; \} else \{ _ai removeItem _x; \};\s*\} else \{\s*\["comspec_atak_native_capLocal", \["strip", _ai, _x\], _ai\] call CBA_fnc_targetEvent;/', $recover);
        // La correction ne passe pas par le filtre : « local » reste refusée à tout appel distant (sinon un client
        // ferait retirer, donner ou déplacer quoi que ce soit à une IA du serveur).
        self::assertStringContainsString('if (_remote && {!(_act in ["dropped", "validate", "dismiss", "athena"])}) exitWith {', $this->read(self::MAIN . '/functions/shell/fn_captureServer.sqf'));
    }

    public function testAthenaResultIsTakenOnlyFromTheSolicitedPlayerWhilePending(): void
    {
        // Résultat de la révocation chez Athena : n'importe quel client posait « ok » (même avant toute validation) ou
        // faisait passer la révocation au candidat suivant. Le serveur retient l'UID du joueur à qui il demande la
        // révocation, à la validation comme à chaque relance, et n'accepte la réponse que de lui, en attente.
        $srv = $this->read(self::MAIN . '/functions/shell/fn_captureServer.sqf');
        self::assertStringContainsString('_c set ["asked", getPlayerUID _by];', $this->between($srv, '_c set ["state", "validated"];', '"revoke"'));
        $athena = $this->between($srv, 'case "athena": {', "\n};\ntrue");
        self::assertNotEmpty($athena);
        self::assertMatchesRegularExpression('/^case "athena": \{\s*_arg params \[\["_id", ""\], \["_ok", false\], \["_from", objNull\]\];.*?'
            . 'if \(\(_c getOrDefault \["athena", ""\]\) isNotEqualTo "pending"\) exitWith \{\};.*?'
            . 'private _asked = _c getOrDefault \["asked", ""\];\s*if \(_asked isEqualTo "" \|\| \{\(getPlayerUID _from\) isNotEqualTo _asked\}\) exitWith \{.*?'
            . 'if \(_ok\) exitWith \{ _c set \["athena", "ok"\]; call _publish; \};/s', $athena);
        self::assertSame(1, substr_count($athena, 'if (_ok) exitWith'));
        self::assertMatchesRegularExpression('/_c set \["asked", getPlayerUID _next\];\s*\S[^\n]*"revoke"[^\n]*_next\]/', $athena);
    }

    public function testFireTeamsHaveNoCbaEventLeft(): void
    {
        // Équipes de feu : les demandes arrivent par remoteExecCall vers 2. Le gestionnaire CBA comspec_atak_native_ft
        // n'avait plus d'émetteur, et en partie hébergée un client qui levait l'événement agissait au nom de l'hôte.
        self::assertStringNotContainsString('"comspec_atak_native_ft"', $this->read(self::MAIN . '/XEH_postInitServer.sqf'));
        foreach (['functions/teams/fn_ftAction.sqf', 'functions/teams/fn_ftRoleSync.sqf', 'functions/pages/fn_groupAction.sqf'] as $f) {
            $src = $this->read(self::MAIN . '/' . $f);
            self::assertStringNotContainsString('"comspec_atak_native_ft"', $src, $f);
            self::assertStringContainsString('remoteExecCall ["comspec_atak_native_fnc_ftServer", 2]', $src, $f);
        }
        self::assertStringContainsString('class comspec_atak_native_fnc_ftServer { allowedTargets = 2; };', $this->read(self::MAIN . '/config.cpp'));
    }
}
