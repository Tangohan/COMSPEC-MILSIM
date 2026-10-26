<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * ATAK natif, relais (MP2-01) : le serveur ne compile la condition d'activation en code d'un relais ou d'une passerelle
 * que depuis un registre que le réseau ne peut pas écrire.
 *
 * Défaut corrigé : fn_relayCond compilait sur le serveur, toutes les 2 s (boucle « srv » de XEH_postInitServer), le texte
 * de la variable d'objet COMSPEC_AtakRelayCondSrv. On la croyait « locale au serveur », parce que le module l'écrivait
 * sans diffusion ; or une écriture diffusée par un client remplace aussi la copie du serveur. Le cache du code compilé
 * (missionNamespace COMSPEC_ATAK_RelayCondCode) pouvait de même être remplacé par un publicVariable. Aucune variable que
 * le réseau peut écrire ne doit donc fournir du code au serveur.
 *
 * Désormais, le serveur lit seulement localNamespace, qui n'est jamais synchronisé. Le registre COMSPEC_ATAK_RelayCondSrc
 * est écrit par fn_relayCondSet (serveur seul), depuis le module Eden ou un script serveur, et le cache
 * COMSPEC_ATAK_RelayCondCode y est rangé aussi.
 */
final class AtakRelayCondServerRegistryTest extends TestCase
{
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main';
    private const CONNECT = 'mod/UptoDate/Sources/comspec-overwatch-addons/connect';
    private const REGISTRY = 'COMSPEC_ATAK_RelayCondSrc';
    private const CACHE = 'COMSPEC_ATAK_RelayCondCode';
    private const SETTER = 'comspec_atak_native_fnc_relayCondSet';

    private function path(string $rel): string
    {
        return dirname(__DIR__, 2) . '/' . $rel;
    }

    private function read(string $rel): string
    {
        return (string) file_get_contents($this->path($rel));
    }

    /** Code SQF sans ses commentaires (// et bloc) ; les chaînes "..." et '...' sont gardées telles quelles. */
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
                $j = $i + 1;
                while ($j < $n) {
                    if ($src[$j] === $c) {
                        if ($j + 1 < $n && $src[$j + 1] === $c) {
                            $j += 2;
                            continue;
                        }
                        break;
                    }
                    $j++;
                }
                $out .= substr($src, $i, $j - $i + 1);
                $i = $j;
                continue;
            }
            $out .= $c;
        }

        return $out;
    }

    /**
     * Fichiers SQF de l'addon natif (fonctions et XEH) et de l'addon connect, chemins relatifs à la racine.
     *
     * @return list<string>
     */
    private function sqfFiles(): array
    {
        $root = dirname(__DIR__, 2) . '/';
        $files = [];
        foreach ([self::MAIN, self::CONNECT] as $dir) {
            if (!is_dir($root . $dir)) {
                continue;
            }
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . $dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if ($f instanceof \SplFileInfo && $f->isFile() && strtolower($f->getExtension()) === 'sqf') {
                    $files[] = substr($f->getPathname(), strlen($root));
                }
            }
        }
        sort($files);

        return $files;
    }

    public function testServerCompilesConditionCodeOnlyFromItsLocalRegistry(): void
    {
        $cond = $this->code($this->read(self::MAIN . '/functions/network/fn_relayCond.sqf'));
        self::assertStringNotContainsString('COMSPEC_AtakRelayCondSrv', $cond, 'le serveur lit encore la variable d\'objet');
        // Un seul « compile » dans le fichier, sur le texte _src, dans la branche serveur de la condition en code.
        self::assertSame(1, preg_match_all('/\bcompile\b/', $cond));
        self::assertSame(1, substr_count($cond, 'compile _src;'));
        $at = strrpos($cond, 'default {');
        self::assertNotFalse($at);
        $def = substr($cond, (int) $at);
        $server = strpos($def, 'if (isServer) then {');
        $client = strpos($def, '(_o getVariable ["COMSPEC_AtakRelayCondOk", []]) params [["_okSrv", false], ["_defSrv", ""]];');
        $compile = strpos($def, 'compile _src;');
        self::assertNotFalse($server);
        self::assertNotFalse($client);
        self::assertNotFalse($compile);
        self::assertGreaterThan((int) $server, (int) $compile, 'compile hors de la branche serveur');
        self::assertLessThan((int) $client, (int) $compile, 'compile dans la branche client');
        // Le texte compilé vient du seul registre localNamespace : ni l'objet, ni missionNamespace, ni une variable publique.
        preg_match_all('/(?:private\s+)?\b_src\s*=\s*([^;]+);/', $def, $src);
        self::assertSame(
            ['(((localNamespace getVariable ["' . self::REGISTRY . '", []]) select { (_x select 0) isEqualTo _o }) param [0, [objNull, ""]]) select 1'],
            array_map('trim', $src[1]),
            'origine du code compilé'
        );
        // Le code compilé vient du cache localNamespace ou de ce texte, et rien d'autre n'est appelé sur l'objet.
        preg_match_all('/(?:private\s+)?\b_code\s*=\s*([^;]+);/', $def, $code);
        self::assertSame(['_codes getOrDefault [_src, {}]', 'compile _src'], array_map('trim', $code[1]));
        preg_match_all('/(?:private\s+)?\b_codes\s*=\s*([^;]+);/', $def, $codes);
        self::assertSame(['localNamespace getVariable "' . self::CACHE . '"', 'createHashMap'], array_map('trim', $codes[1]));
        self::assertStringContainsString('localNamespace setVariable ["' . self::CACHE . '", _codes];', $def);
        self::assertSame(1, substr_count($def, '_o call _code;'));
        self::assertDoesNotMatchRegularExpression('/\bmissionNamespace\b|\bpublicVariable/', $def, 'la branche du code ne touche pas missionNamespace');
    }

    public function testRegistryIsServerOnlyAndNeverSynchronised(): void
    {
        $rel = self::MAIN . '/functions/network/fn_relayCondSet.sqf';
        self::assertFileExists($this->path($rel));
        $set = $this->code($this->read($rel));
        self::assertStringContainsString('params [["_o", objNull, [objNull]], ["_src", "", [""]]];', $set);
        // Serveur seul, et jamais pour un remoteExec venu d'un client, avant toute écriture.
        $guard = 'if (!isServer || {isRemoteExecuted && {!(remoteExecutedOwner in [2, clientOwner])}}) exitWith {';
        $write = 'localNamespace setVariable ["' . self::REGISTRY . '", _reg];';
        self::assertStringContainsString($guard, $set);
        self::assertStringContainsString($write, $set);
        self::assertLessThan((int) strpos($set, $write), (int) strpos($set, $guard), 'garde serveur après l\'écriture');
        self::assertStringContainsString('(localNamespace getVariable ["' . self::REGISTRY . '", []]) select { !isNull (_x select 0) && {(_x select 0) isNotEqualTo _o} }', $set);

        // Registre et cache : chaque mention passe par localNamespace (jamais missionNamespace, un objet ou publicVariable),
        // une seule fonction écrit le registre, et seule fn_relayCond le lit en plus d'elle.
        $mentions = [];
        $writers = [];
        $callers = [];
        foreach ($this->sqfFiles() as $file) {
            $code = $this->code($this->read($file));
            $name = basename($file);
            foreach ([self::REGISTRY, self::CACHE] as $var) {
                $all = substr_count($code, $var);
                if ($all === 0) {
                    continue;
                }
                $mentions[] = $name;
                $local = preg_match_all('/\blocalNamespace\s+(?:getVariable|setVariable)\s*\[?\s*"' . $var . '"/', $code);
                self::assertSame($all, $local, $name . ' : ' . $var . ' hors de localNamespace');
            }
            if (preg_match('/\blocalNamespace\s+setVariable\s*\[\s*"' . self::REGISTRY . '"/', $code) === 1) {
                $writers[] = $name;
            }
            if (str_contains($code, 'call ' . self::SETTER)) {
                $callers[] = $name;
            }
            self::assertDoesNotMatchRegularExpression('/remoteExec(?:Call)?\s*\[\s*"' . self::SETTER . '"/', $code, $file);
        }
        self::assertSame(['fn_relayCond.sqf', 'fn_relayCondSet.sqf'], array_values(array_unique($mentions)));
        self::assertSame(['fn_relayCondSet.sqf'], $writers);
        // Appelants : le module (sur le serveur) ; tout nouvel appelant est une entrée du registre, à revoir avant d'ajouter.
        self::assertSame(['fn_moduleRelay.sqf'], $callers);

        // Déclarée comme les autres fonctions du réseau, jamais en liste blanche CfgRemoteExec.
        $cfg = $this->read(self::MAIN . '/config.cpp');
        self::assertSame(1, preg_match('/class network \{ file="[^"]*\\\\functions\\\\network";(.*?)\n\s*\};/s', $cfg, $net));
        self::assertStringContainsString('class relayCond {}; class relayCondSet {};', $net[1]);
        self::assertStringNotContainsString(self::SETTER, $cfg);
    }

    public function testEdenModuleFeedsTheRegistryAndTheObjectVariableIsGone(): void
    {
        $mod = $this->code($this->read(self::MAIN . '/functions/network/fn_moduleRelay.sqf'));
        self::assertStringContainsString('_x setVariable ["COMSPEC_AtakRelayCond", trim _cond, true];', $mod);
        self::assertStringContainsString(
            'if (isServer && {!((toLower trim _cond) in ["", "stopped", "engineoff", "mast"])}) then { [_x, trim _cond] call ' . self::SETTER . '; };',
            $mod
        );
        // Plus aucun code de l'addon natif ni de connect ne lit ou n'écrit la variable d'objet.
        $files = $this->sqfFiles();
        self::assertGreaterThan(400, count($files));
        foreach ($files as $file) {
            self::assertStringNotContainsString('COMSPEC_AtakRelayCondSrv', $this->code($this->read($file)), $file);
        }
        // Documentation des scripts de mission : l'ancien exemple a disparu, la fonction serveur le remplace.
        $head = $this->read(self::MAIN . '/functions/network/fn_relayCond.sqf');
        $doc = $this->read('mod/COMSPEC_ATAK_Native/MODULES.md');
        foreach ([$head, $doc] as $text) {
            self::assertStringNotContainsString('setVariable ["COMSPEC_AtakRelayCondSrv"', $text);
            self::assertStringContainsString('[_veh, "_this animationSourcePhase ""mast_src"" > 0.9"] call ' . self::SETTER . ';', $text);
        }
    }

    public function testPresetsPublishedResultAndClientsAreUnchanged(): void
    {
        $cond = $this->code($this->read(self::MAIN . '/functions/network/fn_relayCond.sqf'));
        foreach ([
            'if (_c isEqualType "" && {!((toLower trim _c) in ["", "stopped", "engineoff", "mast"])}) then { [_x] call comspec_atak_native_fnc_relayCond; };',
            'if (!(_c isEqualType "") || {(trim _c) isEqualTo ""}) exitWith { [true, ""] };',
            'case "stopped": { _def = "en mouvement"; (abs speed _o) < 2 };',
            'case "engineoff": { _def = "moteur allumé"; !(isEngineOn _o) };',
            '(selectMax (_src apply { _o animationSourcePhase _x })) >= 0.9',
            'if ((_o getVariable ["COMSPEC_AtakRelayCondOk", []]) isNotEqualTo [_r, _def]) then { _o setVariable ["COMSPEC_AtakRelayCondOk", [_r, _def], true]; };',
            '(_o getVariable ["COMSPEC_AtakRelayCondOk", []]) params [["_okSrv", false], ["_defSrv", ""]];',
            '[_ok, ["", [_def, _why] select (_why isNotEqualTo "")] select !_ok]',
        ] as $line) {
            self::assertStringContainsString($line, $cond, $line);
        }
        self::assertStringContainsString(
            '[{ ["srv"] call comspec_atak_native_fnc_relayCond; }, 2] call COMSPEC_ATAK_PfhSpread;',
            $this->read(self::MAIN . '/XEH_postInitServer.sqf')
        );
        self::assertSame(2, substr_count($this->read(self::MAIN . '/functions/network/fn_relayNet.sqf'), 'call comspec_atak_native_fnc_relayCond;'));
    }
}
