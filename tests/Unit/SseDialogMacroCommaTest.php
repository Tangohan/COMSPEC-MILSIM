<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Une virgule dans une chaîne passée en argument de macro de config disparaît au préprocesseur d'Arma : ['type', 1] devient
 * ['type' 1], erreur de script à chaque clic (RPT du 8 novembre, éditeur SSE). hemtt check ne le voit pas.
 */
final class SseDialogMacroCommaTest extends TestCase
{
    public function testNoCommaInStringPassedToAConfigMacro(): void
    {
        $root = dirname(__DIR__, 2) . '/mod';
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        $bad = [];
        foreach ($files as $f) {
            $name = str_replace('\\', '/', (string) $f);
            if (!preg_match('/\.(hpp|cpp)$/', $name) || !preg_match('#/(addons|Sources)/#', $name)) {
                continue;
            }
            foreach (file($name) ?: [] as $n => $line) {
                $t = ltrim($line);
                if (str_starts_with($t, '#') || str_starts_with($t, '//')) {
                    continue;
                }
                // MACRO_EN_MAJUSCULES(…"…,…"…)
                if (preg_match('/\b[A-Z][A-Z0-9_]{2,}\([^"\n)]*"[^"\n]*,[^"\n]*"/', $line)) {
                    $bad[] = $name . ':' . ($n + 1);
                }
            }
        }
        self::assertSame([], $bad, "Virgule dans une chaîne passée à une macro :\n" . implode("\n", $bad));
    }

    public function testEveryEditorCardUsesTheCommaFreeForm(): void
    {
        $hpp = (string) file_get_contents(dirname(__DIR__, 2) . '/mod/@COMSPEC_SSE/addons/editor/dialogs/editor.hpp');
        self::assertSame(8, preg_match_all('/ED_CARD\(\w+,\d+,[\d.]+,[\d.]+,[\d.]+,[\d.]+,"\(\[\'\w+\'\] \+ \[\d\]\) call comspec_sse_fnc_editor"\)/', $hpp));
    }
}
