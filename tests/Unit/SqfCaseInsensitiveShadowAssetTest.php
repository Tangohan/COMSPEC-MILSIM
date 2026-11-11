<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * En SQF, les variables locales ignorent la casse : _L et _l, _T et _t sont la même variable. Un helper
 * « private _L = { localize … } » écrasé plus loin par « private _l = <liste> » ou « params ["_l"] » fait échouer
 * le « call _L » suivant : le script s'arrête et la fenêtre reste à moitié construite (Centre Zeus 4.3.0,
 * Extensions SSE 1.9.0, IA de la zone).
 *
 * Règle (version PHP, sans Python en CI, de tools/sqf_case_shadow_check.py) : dans un même fichier .sqf, un nom
 * qui reçoit un bloc de code ({ … } ou compile) n'existe sous aucune autre casse comme valeur (affectation,
 * params, private, for). Les cas vérifiés à portées séparées sont dans tools/sqf_case_shadow_allowlist.txt.
 * Analyse fine (portées, appels imbriqués, verdict BREAKS / RISK / OK) : python -I tools/sqf_case_shadow_check.py --all
 */
final class SqfCaseInsensitiveShadowAssetTest extends TestCase
{
    /** @var list<string> Sources des mods : ATAK natif, SSE, Overwatch connect, SOF, Flashbang. */
    private const ROOTS = [
        'mod/COMSPEC_ATAK_Native/Sources',
        'mod/@COMSPEC_SSE',
        'mod/UptoDate/Sources',
        'mod/COMSPEC_SOF_Base',
        'mod/COMSPEC_Flashbang',
    ];

    private const ALLOWLIST = 'tools/sqf_case_shadow_allowlist.txt';

    /** Variables magiques posées par le moteur (forEach, apply, gestionnaires…) : leur réaffectation est normale. */
    private const MAGIC = ['_x', '_y', '_foreachindex', '_this', '_thisargs', '_thisevent', '_thiseventhandler', '_exception'];

    public function testNoCodeHelperSharesItsNameWithAValueInAnotherCase(): void
    {
        $allowed = $this->allowList();
        $hits = [];
        $files = 0;
        foreach ($this->files() as [$rel, $src]) {
            $files++;
            foreach ($this->collisions($src) as [$helper, $other]) {
                if (!isset($allowed[$rel . ':' . $helper]) && !isset($allowed[$rel . ':' . $other])) {
                    $hits[] = $rel . ' : helper ' . $helper . ' et valeur ' . $other;
                }
            }
        }
        self::assertGreaterThan(1000, $files, 'Sources SQF des mods introuvables');
        self::assertSame([], $hits, "Helper SQF écrasable par la même variable écrite avec une autre casse "
            . "(renommer la valeur, cf. docs/SQF-VARIABLES-CASSE.md) :\n" . implode("\n", $hits));
    }

    public function testRuleCatchesTheKnownRegressions(): void
    {
        // Centre Zeus 4.3.0 : traduction _T masquée par « _x params ["_t", …] ».
        self::assertSame([['_T', '_t']], $this->collisions(
            "private _T = { _this call comspec_atak_native_fnc_tr };\n{ _x params [\"_t\", \"_w\"]; (_t call _T) } forEach _tabs;"
        ));
        // Extensions SSE 1.9.0 et IA de la zone : _L masqué par « private _l = <liste> ».
        self::assertSame([['_L', '_l']], $this->collisions(
            "private _L = { localize (\"STR_X_\" + _this) };\nprivate _fill = { private _l = 93910 call _ctl; lbClear _l; _l lbAdd (\"A\" call _L); };"
        ));
        self::assertSame([['_fit', '_FIT']], $this->collisions("private _fit = compile \"x\"; _FIT = 3;"));
    }

    public function testRuleIgnoresCommentsStringsMagicAndSameSpelling(): void
    {
        self::assertSame([], $this->collisions("private _L = { 1 };\n// private _l = [];\n/* _l = 2; */ hint \"_l = 3\"; \"a\" call _L;"));
        self::assertSame([], $this->collisions("private _L = { 1 }; _L = { 2 }; private _lst = [];"));
        self::assertSame([], $this->collisions("private _f = { _X }; { _x call _f } forEach [1];"));
        self::assertSame([], $this->collisions("private _ok = _a == _B; private _b = 1;"));
    }

    public function testAllowListEntriesAreJustifiedAndPointToRealFiles(): void
    {
        $root = dirname(__DIR__, 2);
        $lines = file($root . '/' . self::ALLOWLIST, FILE_IGNORE_NEW_LINES) ?: [];
        $entries = 0;
        foreach ($lines as $line) {
            if (trim($line) === '' || str_starts_with(ltrim($line), '#')) {
                continue;
            }
            $entries++;
            self::assertMatchesRegularExpression('~^[^:#\s]+\.sqf:_\w+\s+#\s*\S~', $line, 'Entrée sans raison : ' . $line);
            $rel = explode(':', $line, 2)[0];
            self::assertFileExists($root . '/' . $rel, 'Exception vers un fichier disparu : ' . $line);
        }
        self::assertLessThan(20, $entries, 'Trop d\'exceptions : renommer les variables plutôt que les tolérer');
    }

    public function testScannerAndDeveloperNoteArePresent(): void
    {
        $root = dirname(__DIR__, 2);
        $py = (string) file_get_contents($root . '/tools/sqf_case_shadow_check.py');
        self::assertStringContainsString('BREAKS', $py);
        self::assertStringContainsString('sqf_case_shadow_allowlist.txt', $py);
        $doc = (string) file_get_contents($root . '/docs/SQF-VARIABLES-CASSE.md');
        self::assertStringContainsString('python -I tools/sqf_case_shadow_check.py', $doc);
    }

    /**
     * Paires [helper, autre orthographe] du fichier.
     *
     * @return list<array{0: string, 1: string}>
     */
    private function collisions(string $src): array
    {
        $code = $this->strip($src);
        preg_match_all('/(?<![\w])(_\w+)\s*=(?!=)\s*(?:\{|compile(?:Final|Script)?\b)/i', $code, $h);
        $helpers = array_values(array_unique($h[1]));
        if ($helpers === []) {
            return [];
        }
        preg_match_all('/(?<![\w])(_\w+)\s*=(?!=)\s*+(?!\{|compile)/i', $code, $v);
        preg_match_all('/"(_\w+)"/', $code, $q);
        $values = array_values(array_unique(array_merge($v[1], $q[1])));
        $out = [];
        foreach ($helpers as $helper) {
            if (in_array(strtolower($helper), self::MAGIC, true)) {
                continue;
            }
            foreach ($values as $value) {
                if ($value !== $helper && strtolower($value) === strtolower($helper)) {
                    $out[] = [$helper, $value];
                }
            }
        }

        return $out;
    }

    /** @return array<string, true> */
    private function allowList(): array
    {
        $out = [];
        foreach (file(dirname(__DIR__, 2) . '/' . self::ALLOWLIST, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $entry = trim(explode('#', $line, 2)[0]);
            if ($entry !== '') {
                $out[$entry] = true;
            }
        }

        return $out;
    }

    /** @return list<array{0: string, 1: string}> */
    private function files(): array
    {
        $root = dirname(__DIR__, 2);
        $out = [];
        foreach (self::ROOTS as $base) {
            $dir = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $base);
            if (!is_dir($dir)) {
                continue;
            }
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));
            /** @var SplFileInfo $file */
            foreach ($it as $file) {
                if (!$file->isFile() || strtolower($file->getExtension()) !== 'sqf') {
                    continue;
                }
                $path = $file->getPathname();
                $out[] = [str_replace('\\', '/', substr($path, strlen($root) + 1)), (string) file_get_contents($path)];
            }
        }

        return $out;
    }

    /** Commentaires et lignes de préprocesseur retirés ; chaînes vidées sauf un nom de variable seul ("_l"). */
    private function strip(string $src): string
    {
        $src = preg_replace('/^[ \t]*#.*$/m', '', str_replace("\r", '', $src)) ?? $src;
        $out = '';
        $len = strlen($src);
        $i = 0;
        while ($i < $len) {
            $c = $src[$i];
            $next = $i + 1 < $len ? $src[$i + 1] : '';
            if ($c === '/' && $next === '/') {
                while ($i < $len && $src[$i] !== "\n") {
                    $i++;
                }
                continue;
            }
            if ($c === '/' && $next === '*') {
                $end = strpos($src, '*/', $i + 2);
                $i = $end === false ? $len : $end + 2;
                $out .= ' ';
                continue;
            }
            if ($c === '"' || $c === "'") {
                $j = $i + 1;
                while ($j < $len) {
                    if ($src[$j] === $c) {
                        if ($j + 1 < $len && $src[$j + 1] === $c) {
                            $j += 2;
                            continue;
                        }
                        break;
                    }
                    $j++;
                }
                $body = substr($src, $i + 1, $j - $i - 1);
                $out .= preg_match('/^_\w+$/', $body) === 1 ? '"' . $body . '"' : '""';
                $i = $j + 1;
                continue;
            }
            $out .= $c;
            $i++;
        }

        return $out;
    }
}
