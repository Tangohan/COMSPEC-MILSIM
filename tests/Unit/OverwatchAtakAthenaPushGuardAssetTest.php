<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * DEAD-014 — connect n'appelle comspec_overwatch_atak_athena_fnc_athena_pushNotification (addon gelé atak_athena,
 * absent de @COMSPEC_ATAK_Native) que sous la garde isNil, comme fn_pollMedicalAlerts. fn_pollTacticalAlerts
 * l'appelait sans garde à chaque alerte tactique distante (erreur « variable non définie » attendue dans le RPT).
 *
 * Test statique (lecture des .sqf de connect, commentaires retirés). Il échoue si un appel de la fonction sort d'une
 * garde `if (!isNil "…") then { … }` qui la nomme, ou si l'alerte tactique n'arrive plus à l'inbox connect et au
 * toast du téléphone en dehors de cette garde.
 */
final class OverwatchAtakAthenaPushGuardAssetTest extends TestCase
{
    private const CONNECT = 'mod/UptoDate/Sources/comspec-overwatch-addons/connect/';

    private const FN = 'comspec_overwatch_atak_athena_fnc_athena_pushNotification';

    private function code(string $path): string
    {
        $src = file_get_contents($path);
        self::assertNotFalse($src, 'Source illisible : ' . $path);

        return (string) preg_replace('~//[^\n]*~', '', (string) $src);
    }

    /** @return list<array{0: int, 1: int}> début et fin des corps `if (!isNil "FN") then { … }` */
    private function guards(string $src): array
    {
        $out = [];
        preg_match_all('~if\s*\(\s*!\s*isNil\s*"' . self::FN . '"\s*\)\s*then\s*\{~', $src, $m, PREG_OFFSET_CAPTURE);
        foreach ($m[0] as [$txt, $at]) {
            $start = $at + strlen($txt);
            $depth = 1;
            for ($i = $start, $n = strlen($src); $i < $n; $i++) {
                if ($src[$i] === '{') {
                    $depth++;
                } elseif ($src[$i] === '}' && --$depth === 0) {
                    $out[] = [$start, $i];
                    break;
                }
            }
        }

        return $out;
    }

    public function testEveryCallIsGuarded(): void
    {
        $files = 0;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/' . self::CONNECT, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->getExtension() !== 'sqf') {
                continue;
            }
            $src = $this->code($f->getPathname());
            if (!preg_match_all('~call\s+' . self::FN . '\b~', $src, $calls, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            $files++;
            $guards = $this->guards($src);
            foreach ($calls[0] as [, $at]) {
                $inside = array_filter($guards, static fn (array $g): bool => $at > $g[0] && $at < $g[1]);
                self::assertNotEmpty($inside, $f->getFilename() . ' : appel de ' . self::FN . ' sans garde isNil');
            }
        }
        // fn_pollMedicalAlerts et fn_pollTacticalAlerts.
        self::assertGreaterThanOrEqual(2, $files);
    }

    public function testTacticalAlertStillReachesInboxAndToastOutsideTheGuard(): void
    {
        $src = $this->code(dirname(__DIR__, 2) . '/' . self::CONNECT . 'functions/fn_pollTacticalAlerts.sqf');
        $guards = $this->guards($src);
        self::assertCount(1, $guards);
        foreach (['_inbox pushBack [_kindKey', '_notifyTitles pushBack', '_playUnconscious = true'] as $needle) {
            $at = strpos($src, $needle);
            self::assertNotFalse($at, $needle . ' introuvable');
            self::assertFalse($at > $guards[0][0] && $at < $guards[0][1], $needle . ' ne doit pas dépendre de atak_athena');
        }
    }
}
