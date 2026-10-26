<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * ATAK natif, classe MP2-01 : du code que le SERVEUR exécute ne doit jamais être tenu dans une variable que le réseau
 * peut remplacer (variable globale ou missionNamespace : un client les réécrit par publicVariable), et le serveur ne
 * doit jamais compiler une valeur lue dans une variable d'objet ou publique. Le code serveur va dans localNamespace
 * (jamais synchronisé, patron de fn_dataServer) ou dans une fonction CfgFunctions (finale : le moteur refuse de la
 * réécrire). Ce test fige l'état corrigé et interdit la réintroduction du motif.
 *
 * Rouge sur origin/main (COMSPEC_ATAK_FtCheck et COMSPEC_ATAK_PfhSpread en globale, COMSPEC_ATAK_SrvKeep en
 * missionNamespace, fn_relayCond qui compile COMSPEC_AtakRelayCondSrv de l'objet), vert après le correctif.
 */
final class AtakServerCodeNamespaceGuardTest extends TestCase
{
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main';
    private const CONNECT = 'mod/UptoDate/Sources/comspec-overwatch-addons/connect';

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    private function read(string $rel): string
    {
        return (string) file_get_contents($this->root() . '/' . $rel);
    }

    /** Code SQF sans commentaires (// et bloc), chaînes "..." et '...' remplacées par "" pour ne pas gêner les regex. */
    private function code(string $src): string
    {
        $out = '';
        $n = strlen($src);
        for ($i = 0; $i < $n; $i++) {
            $c = $src[$i];
            $next = $i + 1 < $n ? $src[$i + 1] : '';
            if ($c === '/' && $next === '/') {
                while ($i < $n && $src[$i] !== "\n") {
                    $i++;
                }
                $out .= "\n";
                continue;
            }
            if ($c === '/' && $next === '*') {
                $end = strpos($src, '*/', $i + 2);
                $i = $end === false ? $n : $end + 1;
                $out .= ' ';
                continue;
            }
            if ($c === '"' || $c === "'") {
                $q = $c;
                $j = $i + 1;
                while ($j < $n) {
                    if ($src[$j] === $q) {
                        if ($j + 1 < $n && $src[$j + 1] === $q) {
                            $j += 2;
                            continue;
                        }
                        break;
                    }
                    $j++;
                }
                // On garde le contenu de la chaîne (les noms de variables en dépendent) ; seuls les commentaires partent.
                $out .= substr($src, $i, $j - $i + 1);
                $i = $j;
                continue;
            }
            $out .= $c;
        }

        return $out;
    }

    /** @return list<string> chemins relatifs des .sqf de l'addon natif et de connect. */
    private function sqfFiles(): array
    {
        $files = [];
        foreach ([self::MAIN, self::CONNECT] as $dir) {
            $abs = $this->root() . '/' . $dir;
            if (!is_dir($abs)) {
                continue;
            }
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($abs, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if ($f instanceof \SplFileInfo && $f->isFile() && strtolower($f->getExtension()) === 'sqf') {
                    $files[] = substr($f->getPathname(), strlen($this->root()) + 1);
                }
            }
        }
        sort($files);

        return $files;
    }

    /** Un fichier est « serveur » si son code commence par une garde !isServer, ou si c'est XEH_postInitServer. */
    private function isServerFile(string $rel, string $code): bool
    {
        if (basename($rel) === 'XEH_postInitServer.sqf') {
            return true;
        }

        return preg_match('/!\s*isServer\s*\)\s*exitWith/', substr($code, 0, 800)) === 1;
    }

    public function testFixedServerHoldersAreMachineLocalOrFinal(): void
    {
        $srv = $this->code($this->read(self::MAIN . '/XEH_postInitServer.sqf'));
        foreach (['COMSPEC_ATAK_FtCheck', 'COMSPEC_ATAK_SrvKeep'] as $v) {
            self::assertMatchesRegularExpression('/localNamespace\s+setVariable\s*\[\s*"' . $v . '"/', $srv, $v);
            self::assertDoesNotMatchRegularExpression('/missionNamespace\s+setVariable\s*\[\s*"' . $v . '"/', $srv, $v);
            self::assertDoesNotMatchRegularExpression('/(?m)^\s*' . $v . '\s*=\s*\{/', $srv, $v);
        }

        // PfhSpread : fonction finale (CfgFunctions), plus de variable globale ni d'appel par ce nom.
        self::assertStringContainsString('class pfhSpread {};', $this->read(self::MAIN . '/config.cpp'));
        self::assertFileExists($this->root() . '/' . self::MAIN . '/functions/core/fn_pfhSpread.sqf');
        self::assertDoesNotMatchRegularExpression('/(?m)^\s*COMSPEC_ATAK_PfhSpread\s*=\s*\{/', $this->code($this->read(self::MAIN . '/XEH_preInit.sqf')));
        foreach ($this->sqfFiles() as $rel) {
            self::assertStringNotContainsString('call COMSPEC_ATAK_PfhSpread', $this->code($this->read($rel)), $rel);
        }

        // connect : prédicat exécuté par les boucles de synchro du serveur.
        foreach ([self::CONNECT . '/XEH_postInit.sqf', self::CONNECT . '/functions/fn_initServerHub.sqf', self::CONNECT . '/functions/fn_startSyncLoops.sqf'] as $rel) {
            $c = $this->code($this->read($rel));
            self::assertDoesNotMatchRegularExpression('/missionNamespace\s+setVariable\s*\[\s*"COMSPEC_fnc_isOwnedMapMarker"/', $c, $rel);
            self::assertStringNotContainsString('missionNamespace getVariable ["COMSPEC_fnc_isOwnedMapMarker"', $c, $rel);
        }
    }

    public function testNoServerCodeHeldInSynchronisedNamespace(): void
    {
        $offenders = [];
        foreach ($this->sqfFiles() as $rel) {
            $c = $this->code($this->read($rel));
            if (!$this->isServerFile($rel, $c)) {
                continue;
            }
            // Code assigné à une variable globale (donc missionNamespace) : réécrivable par publicVariable.
            if (preg_match_all('/(?m)^\s*([A-Z][A-Za-z0-9_]*)\s*=\s*\{/', $c, $m)) {
                foreach ($m[1] as $name) {
                    $offenders[] = "$rel : global $name = { ... }";
                }
            }
            // Code écrit directement dans missionNamespace.
            if (preg_match_all('/missionNamespace\s+setVariable\s*\[\s*"([A-Za-z0-9_]+)"\s*,\s*\{/', $c, $m)) {
                foreach ($m[1] as $name) {
                    $offenders[] = "$rel : missionNamespace setVariable [\"$name\", { ... }]";
                }
            }
            // Code rangé via une variable locale (private _x = { ... }; missionNamespace setVariable ["N", _x];).
            if (preg_match_all('/(?:private\s+)?(_[A-Za-z0-9_]+)\s*=\s*\{/', $c, $lm)) {
                $codeLocals = array_flip($lm[1]);
                if (preg_match_all('/missionNamespace\s+setVariable\s*\[\s*"([A-Za-z0-9_]+)"\s*,\s*(_[A-Za-z0-9_]+)\s*\]/', $c, $sm, PREG_SET_ORDER)) {
                    foreach ($sm as $hit) {
                        if (isset($codeLocals[$hit[2]])) {
                            $offenders[] = "$rel : missionNamespace setVariable [\"$hit[1]\", $hit[2]] (code)";
                        }
                    }
                }
            }
        }
        self::assertSame([], $offenders, "Code serveur dans une variable synchronisable :\n" . implode("\n", $offenders));
    }

    public function testServerNetworkCodeNeverCompilesAnObjectOrPublicVariable(): void
    {
        $offenders = [];
        foreach ($this->sqfFiles() as $rel) {
            if (!str_contains($rel, self::MAIN . '/functions/network/') && !str_contains($rel, self::MAIN . '/functions/datalink/')) {
                continue;
            }
            $c = $this->code($this->read($rel));
            if (!preg_match_all('/\bcompile\s+\(?\s*(_[A-Za-z0-9_]+)\b/', $c, $m, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            foreach ($m[1] as $hit) {
                $var = $hit[0];
                $at = (int) $hit[1];
                // Dernière affectation de cette locale avant le compile.
                if (!preg_match_all('/(?:private\s+)?' . preg_quote($var, '/') . '\s*=\s*([^;]+);/', substr($c, 0, $at), $am)) {
                    continue;
                }
                $rhs = trim(end($am[1]));
                if (str_contains($rhs, 'getVariable') && !str_contains($rhs, 'localNamespace')) {
                    $offenders[] = "$rel : compile $var ← $rhs";
                }
            }
        }
        self::assertSame([], $offenders, "compile d'une valeur lue dans une variable d'objet ou publique :\n" . implode("\n", $offenders));
    }
}
