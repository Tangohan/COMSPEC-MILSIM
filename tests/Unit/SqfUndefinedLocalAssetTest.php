<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * En SQF, lire une variable locale jamais définie donne « rien » : l'expression qui s'en sert sort en erreur à chaque
 * passage. PC du poste TOC, 4.7.1 : « _sideRgb » avait perdu sa définition dans la refonte du thème, la carte du PC
 * sortait « 0 éléments fournis, 4 attendus » à chaque image.
 *
 * Règle (version PHP, sans Python en CI, de tools/sqf_undefined_local_check.py) : dans un fichier .sqf, tout nom
 * « _nom » lu dans le code est défini quelque part dans le même fichier (private, params, for, affectation), ou c'est
 * une variable posée par le moteur ou le cadre appelant (MAGIC). La casse est ignorée, comme en SQF. Les portées ne
 * sont pas suivies : seuls les noms définis nulle part sortent, sans fausse alerte de portée.
 */
final class SqfUndefinedLocalAssetTest extends TestCase
{
    /** @var list<string> Sources des mods : ATAK natif, SSE, Overwatch connect, SOF, Flashbang. */
    private const ROOTS = [
        'mod/COMSPEC_ATAK_Native/Sources',
        'mod/@COMSPEC_SSE',
        'mod/UptoDate/Sources',
        'mod/COMSPEC_SOF_Base',
        'mod/COMSPEC_Flashbang',
    ];

    private const ALLOWLIST = 'tools/sqf_undefined_local_allowlist.txt';

    private const MAGIC = [
        '_x', '_y', '_this', '_foreachindex', '_exception', '_thisargs', '_thisevent', '_thiseventhandler', '_thisscript',
        '_thisfsm', '_thisscriptedeventhandler', '_thisid', '_thistype', '_thisfnc', '_target', '_player', '_params',
        '_actionparams', '_caller', '_actionid', '_arguments', '_originaltarget', '_fnc_scriptname', '_fnc_scriptnameparent',
        '_input0', '_input1', '_input2', '_input3', '_input4', '_input5', '_input6', '_input7', '_input8', '_input9',
        '_thisstate', '_thistransition', '_thisorigin', '_thistarget',
    ];

    public function testEveryLocalReadIsDefinedInItsFile(): void
    {
        $allowed = $this->allowList();
        $hits = [];
        $files = 0;
        foreach ($this->files() as [$rel, $src]) {
            $files++;
            foreach ($this->undefined($src) as [$name, $line]) {
                if (!isset($allowed[strtolower($rel . ':' . $name)])) {
                    $hits[] = $rel . ':' . $line . ' : ' . $name;
                }
            }
        }
        self::assertGreaterThan(1000, $files, 'Sources SQF des mods introuvables');
        self::assertSame([], $hits, "Variable locale SQF lue sans définition dans son fichier (la définir, ou la passer en paramètre) :\n" . implode("\n", $hits));
    }

    public function testRuleCatchesTheKnownRegression(): void
    {
        // PC du poste TOC 4.7.1 : _sideRgb lue dans la carte, définie nulle part dans le fichier.
        $src = "private _map = _a;\n_map drawIcon [\"x.paa\", _sideRgb + [1], _p, 30, 30, 0, \"\", 0];\nprivate _p = [0, 0, 0];\nprivate _a = 1;\n";
        self::assertSame([['_sideRgb', 2]], $this->undefined($src));
        // Définie : plus rien.
        self::assertSame([], $this->undefined("private _a = 0; private _p = 0; private _sideRgb = [1, 1, 1];\n" . $src));
    }

    public function testRuleIgnoresCommentsStringsMagicParamsAndCase(): void
    {
        $src = <<<'SQF'
            // _jamais dans un commentaire
            /* _nonPlus */
            params ["_logic", ["_units", []]];
            _logic setVariable ["texte _libre", "_pasUneVariable ici"];
            { private _n = _x; _N = _forEachIndex; } forEach _UNITS;
            for "_i" from 0 to 3 do { _n = _i; };
            private ["_a", "_b"];
            _a = _b;
            _this spawn { _ok = _this; };
            _btn ctrlAddEventHandler ["ButtonClick", "_btn2 = _this select 0; _btn2 ctrlShow false;"];
            SQF;
        self::assertSame([['_btn', 10]], $this->undefined($src));
    }

    public function testScannerFixAndAllowListArePresent(): void
    {
        $root = dirname(__DIR__, 2);
        self::assertFileExists($root . '/tools/sqf_undefined_local_check.py');
        self::assertFileExists($root . '/' . self::ALLOWLIST);
        foreach (array_keys($this->allowList()) as $entry) {
            [$rel] = explode(':', $entry, 2);
            self::assertFileExists($root . '/' . $rel, 'exception sur un fichier disparu : ' . $entry);
        }
        // Même liste de variables du moteur dans le script et dans ce test.
        $py = (string) file_get_contents($root . '/tools/sqf_undefined_local_check.py');
        foreach (self::MAGIC as $name) {
            self::assertStringContainsString('"' . $name . '"', $py, $name . ' absent de MAGIC dans le script');
        }
        // Le correctif : couleur du camp définie dans la carte du PC, et « mk » rend bien le contrôle créé.
        $lap = str_replace("\r\n", "\n", (string) file_get_contents($root . '/mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/toc/fn_tocLaptop.sqf'));
        self::assertStringContainsString('private _sideRgb = (["rgba", ["friend"]] call comspec_atak_native_fnc_tocTheme) select [0, 3];', $lap);
        self::assertStringContainsString('private _ret = switch (_mode) do {', $lap);
        self::assertStringEndsWith("};\nif (_mode in [\"mk\", \"audit\"] && {!isNil \"_ret\"}) exitWith { _ret };\ntrue\n", $lap);
    }

    /**
     * @return list<array{0: string, 1: int}> variables lues sans définition : [nom tel qu'écrit, ligne]
     */
    private function undefined(string $src): array
    {
        $src = str_replace("\r\n", "\n", $src);
        // Commentaires retirés sans toucher aux chaînes ; chaînes vidées (longueur et lignes gardées).
        $strings = [];
        $code = (string) preg_replace_callback(
            '~"(?:[^"]|"")*"|\'(?:[^\']|\'\')*\'|//[^\n]*|/\*.*?\*/~s',
            static function (array $m) use (&$strings): string {
                $t = $m[0];
                if ($t[0] === '/') {
                    return str_repeat("\n", substr_count($t, "\n"));
                }
                $strings[] = substr($t, 1, -1);

                return '"' . preg_replace('/[^\n]/', ' ', substr($t, 1, -1)) . '"';
            },
            $src
        );
        $declared = [];
        foreach ($strings as $body) {
            if (preg_match('/^_[A-Za-z0-9_]+$/', $body) === 1) {
                $declared[strtolower($body)] = true;
                continue;
            }
            // Code écrit dans une chaîne (gestionnaire, condition d'action) : ses définitions comptent aussi.
            preg_match_all('/(?<![A-Za-z0-9_#])(_[A-Za-z0-9_]+)\s*=(?!=)|\bprivate\s+(_[A-Za-z0-9_]+)|""(_[A-Za-z0-9_]+)""/', $body, $m);
            foreach (array_merge($m[1], $m[2], $m[3]) as $n) {
                if ($n !== '') {
                    $declared[strtolower($n)] = true;
                }
            }
        }
        preg_match_all('/(?<![A-Za-z0-9_#])(_[A-Za-z0-9_]+)\s*=(?!=)|\bprivate\s+(_[A-Za-z0-9_]+)/', $code, $m);
        foreach (array_merge($m[1], $m[2]) as $n) {
            if ($n !== '') {
                $declared[strtolower($n)] = true;
            }
        }
        $magic = array_flip(self::MAGIC);
        $hits = [];
        preg_match_all('/(?<![A-Za-z0-9_#])_[A-Za-z0-9_]+/', $code, $m, PREG_OFFSET_CAPTURE);
        foreach ($m[0] as [$name, $pos]) {
            $low = strtolower($name);
            if (isset($declared[$low]) || isset($magic[$low]) || isset($hits[$low])) {
                continue;
            }
            $hits[$low] = [$name, substr_count($code, "\n", 0, $pos) + 1];
        }
        $out = array_values($hits);
        usort($out, static fn (array $a, array $b): int => $a[1] <=> $b[1]);

        return $out;
    }

    /** @return array<string, true> */
    private function allowList(): array
    {
        $out = [];
        $path = dirname(__DIR__, 2) . '/' . self::ALLOWLIST;
        foreach (is_file($path) ? (file($path, FILE_IGNORE_NEW_LINES) ?: []) : [] as $line) {
            $line = trim((string) preg_replace('/#.*$/', '', $line));
            if ($line !== '') {
                $out[strtolower($line)] = true;
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
                if (str_contains($path, '.hemttout')) {
                    continue;
                }
                $out[] = [str_replace('\\', '/', substr($path, strlen($root) + 1)), (string) file_get_contents($path)];
            }
        }

        return $out;
    }
}
