<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * DEF-024 — l'alerte « Carte Athena non incluse » (refus 403 feature_unavailable d'un envoi de photo) attend jusqu'à
 * 30 min que le téléphone soit ouvert, au lieu d'être abandonnée au bout de 2 min (COMSPEC_ATAK_PlanModalShown étant
 * posé dès la mise en file, elle n'était jamais reproposée). fn_fullAlert prend un 6e paramètre facultatif (attente,
 * 120 s par défaut : les autres alertes ne changent pas), rangé en 9e position de l'alerte en cours. Une alerte jamais
 * vue n'est pas reprise par la mission suivante (remise à nil dans fn_stateInit).
 *
 * Test statique (lecture des .sqf, commentaires retirés). Il échoue si l'attente redevient fixe, n'est plus rangée
 * ou n'a plus 120 s par défaut, si l'alerte du refus 403 ne passe plus 1800, ou si la remise à zéro par mission
 * disparaît.
 */
final class AtakFullAlertWaitAssetTest extends TestCase
{
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/';

    private function code(string $rel): string
    {
        $src = file_get_contents(dirname(__DIR__, 2) . '/' . self::MAIN . $rel);
        self::assertNotFalse($src, 'Source illisible : ' . $rel);

        return (string) preg_replace('~//[^\n]*~', '', (string) $src);
    }

    public function testWaitIsAnOptionalSixthParameterStoredNinth(): void
    {
        $fa = $this->code('functions/ui/fn_fullAlert.sqf');
        self::assertMatchesRegularExpression('~^params \[\["_title", "ALERTE"\], \["_msg", ""\], \["_from", ""\], \["_rgba", [^\]]*\]\], \["_dur", 10\], \["_wait", 120\]\];~m', $fa, '6e paramètre « _wait » (120 par défaut) absent');
        self::assertMatchesRegularExpression('~setVariable \["COMSPEC_ATAK_FullAlert", \[_title, _msg, _from, _rgba, _dur max 1, diag_tickTime, \[dayTime, "HH:MM"\] call BIS_fnc_timeToString, -1, _wait\]\]~', $fa, 'Attente non rangée en 9e position');
    }

    public function testPendingAlertExpiresAfterItsOwnWait(): void
    {
        $fa = $this->code('functions/ui/fn_fullAlert.sqf');
        self::assertStringContainsString('_cur params ["_title", "_msg", "_from", "_rgba", "_dur", "_at", "_hh", "_shown", ["_wait", 120]];', $fa);
        self::assertStringContainsString('(_shown < 0 && {(diag_tickTime - _at) > _wait})', $fa);
        self::assertDoesNotMatchRegularExpression('~diag_tickTime - _at\)\s*>\s*120~', $fa, 'Attente fixe de 120 s revenue');
    }

    public function testPlanRefusalAlertWaitsHalfAnHour(): void
    {
        self::assertMatchesRegularExpression(
            '~\["Carte Athena non incluse" call comspec_atak_native_fnc_tr, _msg, "Athena", \[[^\]]*\], 30, 1800\] call comspec_atak_native_fnc_fullAlert;~',
            $this->code('XEH_postInitClient.sqf')
        );
    }

    public function testPendingAlertDoesNotSurviveTheMission(): void
    {
        self::assertMatchesRegularExpression('~uiNamespace setVariable \["COMSPEC_ATAK_FullAlert", nil\];~', $this->code('functions/core/fn_stateInit.sqf'));
        // nil n'est sûr que si chaque lecture a une valeur par défaut.
        $bare = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/' . self::MAIN . 'functions', \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->getExtension() === 'sqf' && preg_match('~getVariable\s+"COMSPEC_ATAK_FullAlert"~', (string) file_get_contents($f->getPathname()))) {
                $bare[] = $f->getFilename();
            }
        }
        self::assertSame([], $bare, 'Lecture de COMSPEC_ATAK_FullAlert sans valeur par défaut');
    }
}
