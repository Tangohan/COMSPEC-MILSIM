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
}
