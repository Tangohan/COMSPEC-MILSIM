<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * API du TOC pour les créateurs de mission (ATAK natif 4.12.0, feuille de route TOC Z4) et bibliothèque de
 * préréglages (Z3) : contrôle des arguments rejoué ici sur la table _sig de fn_tocApi.sqf, verbes et événements tous
 * documentés avec un exemple (docs/TOC-API.md), texte d'échange d'un préréglage relu et réécrit à l'identique.
 * Lecture du code seulement : rien de tout cela n'a tourné en jeu.
 */
final class AtakTocApiContractTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';
    private const TOC = self::ROOT . '/mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/toc/';
    private const HEAD = 'COMSPEC-TOC-PRESET:1:';

    private static function read(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents($path));
    }

    /** Même calcul que _check (fn_tocApi.sqf). Les valeurs PHP tiennent lieu de valeurs SQF ; un objet est un \stdClass. */
    private static function check(array $sig, string $verb, mixed $args): string
    {
        $row = null;
        foreach ($sig as $r) {
            if ($r[0] === $verb) {
                $row = $r;
            }
        }
        if ($row === null) {
            return "verbe inconnu : {$verb}";
        }
        if (!is_array($args)) {
            return 'les arguments doivent être un tableau';
        }
        foreach ($row[2] as $i => [$name, $type, $required]) {
            $val = $args[$i] ?? null;
            if ($val === null) {
                if ($required) {
                    return "argument manquant : {$name}";
                }
                continue;
            }
            $ok = match ($type) {
                'S' => is_string($val),
                'N' => is_int($val) || is_float($val),
                'B' => is_bool($val),
                'O' => $val instanceof \stdClass,
                'A' => is_array($val),
                'C' => $val instanceof \Closure,
                default => true,
            };
            if (!$ok) {
                return "argument {$name} : type {$type} attendu";
            }
        }

        return '';
    }

    /** @return array<string, array{string, mixed, string}> */
    public static function calls(): array
    {
        $obj = new \stdClass();

        return [
            'salle créée' => ['roomCreate', ['PC AVANT', 'WEST'], ''],
            'salle créée avec son PC' => ['roomCreate', ['PC AVANT', 'WEST', $obj, 'H1'], ''],
            'salle sans camp' => ['roomCreate', ['PC AVANT'], 'argument manquant : side'],
            'nom qui n\'est pas un texte' => ['roomCreate', [12, 'WEST'], 'argument name : type S attendu'],
            'PC qui n\'est pas un objet' => ['roomCreate', ['PC', 'WEST', 'pc_1'], 'argument pc : type O attendu'],
            'écran posé' => ['screenSpawn', [[10, 20, 0], 90, '', 'MUR', 'WEST', 'SPLIT', 'H1'], ''],
            'écran posé, position seule' => ['screenSpawn', [[10, 20, 0], 90], ''],
            'position qui n\'est pas un tableau' => ['screenSpawn', ['ici', 90], 'argument posATL : type A attendu'],
            'affichage changé' => ['screenView', [$obj, 'LOG'], ''],
            'affichage sans écran' => ['screenView', ['MUR', 'LOG'], 'argument screen : type O attendu'],
            'source : toute valeur' => ['screenSource', [$obj, 'cam', $obj], ''],
            'source sans valeur (auto)' => ['screenSource', [$obj, 'auto'], ''],
            'texte : texte ou réglage' => ['screenText', [$obj, [['A', 'G', 10, 0]]], ''],
            'verrou' => ['lock', ['H1', true, 'briefing'], ''],
            'verrou : booléen attendu' => ['lock', ['H1', 1], 'argument on : type B attendu'],
            'verrou sans état' => ['lock', ['H1'], 'argument manquant : on'],
            'état du camp' => ['state', ['WEST'], ''],
            'rôle donné par UID' => ['grant', ['76561198000000001', 'chief'], ''],
            'chrono : valeur numérique' => ['timerAdd', ['WEST', 'H', 'AT', '22:00'], 'argument value : type N attendu'],
            'abonnement' => ['on', ['comspec_atak_toc_locked', static fn () => null], ''],
            'abonnement sans code' => ['on', ['comspec_atak_toc_locked', 'code'], 'argument code : type C attendu'],
            'diagnostic sans argument' => ['diag', [], ''],
            'arguments en trop : ignorés (un ajout futur ne casse rien)' => ['roomRemove', ['H1', 'en trop'], ''],
            'arguments qui ne sont pas un tableau' => ['rooms', 'WEST', 'les arguments doivent être un tableau'],
            'verbe inconnu' => ['roomDestroy', [], 'verbe inconnu : roomDestroy'],
        ];
    }

    #[DataProvider('calls')]
    public function testArgumentValidation(string $verb, mixed $args, string $expected): void
    {
        self::assertSame($expected, self::check(AtakTocAccessMatrixTest::table('fn_tocApi.sqf', '_sig'), $verb, $args));
    }

    public function testSignatureTableIsWellFormedAndImplemented(): void
    {
        $sig = AtakTocAccessMatrixTest::table('fn_tocApi.sqf', '_sig');
        $src = self::read(self::TOC . 'fn_tocApi.sqf');
        $verbs = array_column($sig, 0);
        self::assertSame($verbs, array_unique($verbs));
        self::assertGreaterThanOrEqual(30, count($verbs));
        foreach ($sig as [$verb, $where, $args]) {
            self::assertContains($where, ['server', 'any'], $verb);
            self::assertStringContainsString('    case "' . $verb . '": ', $src, "verbe {$verb} sans code");
            $optional = false;
            foreach ($args as [$name, $type, $required]) {
                self::assertContains($type, ['S', 'N', 'B', 'O', 'A', 'C', 'X'], "{$verb}.{$name}");
                self::assertIsBool($required);
                // Contrat : les arguments facultatifs sont toujours après les obligatoires.
                self::assertFalse($optional && $required, "{$verb}.{$name} : obligatoire après un facultatif");
                $optional = $optional || !$required;
            }
        }
        // Demandes de TTRD : créer salle et écrans par script, régler affichage, source et texte, verrouiller, lire l'état, s'abonner.
        foreach (['roomCreate', 'screenAdd', 'screenSpawn', 'screenView', 'screenSource', 'screenText', 'lock', 'state', 'on', 'off'] as $v) {
            self::assertContains($v, $verbs);
        }
        // Le contrôle du code SQF : mêmes messages que le calcul rejoué ici.
        foreach (['format ["verbe inconnu : %1", _v]', '"les arguments doivent être un tableau"', 'format ["argument manquant : %1", _name]', 'format ["argument %1 : type %2 attendu", _name, _ty]'] as $needle) {
            self::assertStringContainsString($needle, $src);
        }
        // Jamais d'erreur de script ni de fenêtre : valeur d'échec, raison gardée et écrite au journal.
        self::assertStringContainsString('if (_err isNotEqualTo "") exitWith { _err call _refuse; _fail };', $src);
        self::assertStringContainsString('missionNamespace setVariable ["COMSPEC_ATAK_TocApiError", _this];', $src);
        // Un joueur ne pilote pas le TOC en appelant l'API sur le serveur.
        self::assertStringContainsString('if (isRemoteExecuted && {isMultiplayer} && {!(remoteExecutedOwner in [0, 2])}) exitWith { "appel distant refusé" call _refuse; _fail };', $src);
        self::assertStringContainsString('if (_where isEqualTo "server" && {!isServer}) exitWith { "à appeler sur le serveur" call _refuse; _fail };', $src);
        self::assertStringContainsString('private _version = "1.0";', $src);
    }

    public function testEveryVerbAndEventIsDocumentedWithAnExample(): void
    {
        $doc = self::read(self::ROOT . '/docs/TOC-API.md');
        self::assertStringContainsString('Version de l\'API : **1.0**', $doc);
        foreach (AtakTocAccessMatrixTest::table('fn_tocApi.sqf', '_sig') as [$verb, $where]) {
            $i = strpos($doc, "### `{$verb}`\n");
            self::assertNotFalse($i, "verbe {$verb} absent de docs/TOC-API.md");
            $next = strpos($doc, "\n##", $i + 5);
            $section = substr($doc, $i, ($next === false ? strlen($doc) : $next) - $i);
            self::assertStringContainsString('```sqf', $section, "{$verb} : exemple manquant");
            self::assertStringContainsString('["' . $verb . '", ', $section, "{$verb} : l'exemple n'appelle pas le verbe");
            self::assertStringContainsString($where === 'server' ? 'Serveur.' : 'Partout', $section, "{$verb} : où l'appeler");
        }
        $events = AtakTocAccessMatrixTest::table('fn_tocApi.sqf', '_events');
        $names = array_column($events, 0);
        foreach ($events as [$name, $where]) {
            self::assertStringStartsWith('comspec_atak_toc_', $name);
            self::assertContains($where, ['server', 'all', 'client'], $name);
            self::assertStringContainsString('| `' . $name . '` |', $doc, "événement {$name} absent de docs/TOC-API.md");
        }
        // Tout événement levé par le code est dans la table, et tout événement de la table est levé quelque part.
        $raised = [];
        foreach (glob(self::TOC . 'fn_toc*.sqf') ?: [] as $f) {
            preg_match_all('/\["(comspec_atak_toc_\w+)", [^\n]*?call CBA_fnc_(?:localEvent|globalEvent|targetEvent)/', self::read($f), $m);
            $raised = array_merge($raised, $m[1]);
        }
        self::assertEqualsCanonicalizing($names, array_values(array_unique($raised)));
    }

    /** Même nettoyage que _sanitize (fn_tocPresets.sqf), sur la table _schema. */
    private static function sanitize(array $schema, mixed $p): array
    {
        if (!is_array($p) || count($p) < 5 || $p[0] !== 1 || !is_array($p[4])) {
            return [];
        }
        $clean = static fn (mixed $s, int $n): string => mb_substr((string) preg_replace('/["\r\n\\\\]/', '', is_string($s) ? $s : ''), 0, $n);
        $r3 = static function (int|float $v): int|float {
            $r = round($v * 1000) / 1000;

            return $r == (int) $r ? (int) $r : $r;
        };
        $name = $clean($p[1], 32);
        if ($name === '') {
            return [];
        }
        $items = [];
        foreach (array_slice($p[4], 0, 40) as $it) {
            if (!is_array($it) || count($it) < 5) {
                continue;
            }
            $row = [];
            $bad = false;
            foreach ($schema as [$i, , $type, $n]) {
                $v = $it[$i] ?? null;
                switch ($type) {
                    case 'S':
                        $row[] = $clean($v, $n);
                        break;
                    case 'N':
                        $row[] = is_int($v) || is_float($v) ? $r3($v) : 0;
                        break;
                    case 'B':
                        $row[] = $v === true;
                        break;
                    case 'V':
                        if (is_array($v) && count($v) === 3 && array_filter($v, static fn ($x): bool => is_int($x) || is_float($x)) === $v) {
                            $row[] = array_map($r3, $v);
                        } else {
                            $bad = true;
                            $row[] = [0, 0, 0];
                        }
                        break;
                    default:
                        $row[] = is_array($v) && strlen((string) json_encode($v)) <= $n ? $v : [];
                }
            }
            if (!$bad && $row[0] !== '') {
                $items[] = $row;
            }
        }
        if ($items === []) {
            return [];
        }
        $scenes = $p[5] ?? [];
        if (!is_array($scenes) || strlen((string) json_encode($scenes)) > 6000) {
            $scenes = [];
        }

        return [1, $name, $clean($p[2], 10), strtoupper($clean($p[3], 4)), $items, $scenes];
    }

    public function testPresetTextRoundTripsOnTheDocumentedExample(): void
    {
        $schema = AtakTocAccessMatrixTest::table('fn_tocPresets.sqf', '_schema');
        self::assertSame(range(0, 10), array_column($schema, 0));
        self::assertSame(['class', 'offset', 'dir', 'name', 'view', 'sat', 'squad', 'off', 'text', 'opt', 'mosaic'], array_column($schema, 1));
        $doc = self::read(self::ROOT . '/docs/TOC-API.md');
        self::assertSame(1, preg_match('/^(' . preg_quote(self::HEAD, '/') . '\[.*\])$/m', $doc, $m), 'exemple de texte d\'échange dans docs/TOC-API.md');
        $text = $m[1];
        // Import : en-tête, tableau, nettoyage.
        $raw = json_decode(substr($text, strlen(self::HEAD)), true);
        self::assertIsArray($raw, 'un préréglage sans texte d\'affichage se lit comme du JSON');
        $preset = self::sanitize($schema, $raw);
        self::assertSame('SALLE OPS', $preset[1]);
        self::assertSame('WEST', $preset[3]);
        self::assertCount(2, $preset[4]);
        self::assertSame(['Land_TripodScreen_01_large_F', [-1.5, 0, 0], 0, 'MUR GAUCHE', 'SPLIT', 0, '', false, [], [], []], $preset[4][0]);
        // Export du préréglage importé : même texte, au caractère près ; et un second aller-retour ne change rien.
        $again = self::HEAD . json_encode($preset, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        self::assertSame($text, $again);
        self::assertSame($preset, self::sanitize($schema, json_decode(substr($again, strlen(self::HEAD)), true)));
        // Texte abîmé, autre format, écran sans classe ou sans position : rien n'est repris.
        self::assertSame([], self::sanitize($schema, 'abîmé'));
        self::assertSame([], self::sanitize($schema, [2, 'X', '', 'WEST', [['C', [0, 0, 0], 0, 'N', 'MAP']]]));
        self::assertSame([], self::sanitize($schema, [1, 'X', '', 'WEST', [['', [0, 0, 0], 0, 'N', 'MAP'], ['C', 'ici', 0, 'N', 'MAP']]]));
        // Guillemets et retours à la ligne retirés des noms, longueurs bornées, arrondi au millimètre.
        $dirty = self::sanitize($schema, [1, "SALLE \"A\"\nB" . str_repeat('x', 40), '2026-11-11T00:00', 'west', [['C', [1.23456, 0, 0], 90.00049, 'N', 'MAP']]]);
        self::assertSame('SALLE AB' . str_repeat('x', 24), $dirty[1]);
        self::assertSame('2026-11-11', $dirty[2]);
        self::assertSame('WEST', $dirty[3]);
        self::assertSame(['C', [1.235, 0, 0], 90, 'N', 'MAP', 0, '', false, [], [], []], $dirty[4][0]);
        // Le code SQF : même en-tête, même nettoyage, jamais de code exécuté.
        $src = self::read(self::TOC . 'fn_tocPresets.sqf');
        self::assertStringContainsString('private _head = "' . self::HEAD . '";', $src);
        self::assertStringContainsString('case "export": { private _p = [_a] call _sanitize; if (_p isEqualTo []) then { "" } else { _head + (str _p) } };', $src);
        self::assertStringContainsString('[parseSimpleArray _raw] call _sanitize', $src);
        self::assertStringContainsString('if !(_a isEqualType "" && {(_a find _head) isEqualTo 0} && {(count _a) <= 60000}) exitWith { [] };', $src);
        self::assertStringContainsString('(_p select 0) isEqualTo 1', $src);
        self::assertStringContainsString('_s regexReplace ["[""\r\n\\\\\\\\]", ""]', $src);
        self::assertStringContainsString('(round (_this * 1000)) / 1000', $src);
        self::assertDoesNotMatchRegularExpression('/\b(compile|call compile|execVM)\b/', $src);
        // Deux bibliothèques : profil du serveur (toutes les missions) et mémoire du serveur pour la partie.
        self::assertStringContainsString('profileNamespace getVariable ["COMSPEC_ATAK_TocPresets", []]', $src);
        self::assertStringContainsString('localNamespace getVariable ["COMSPEC_ATAK_TocPresetsMission", []]', $src);
        self::assertStringContainsString('_l select [0 max ((count _l) - 30)]', $src);
        // Modèle d'écran absent d'une autre mission : remplacé et compté.
        self::assertStringContainsString('if !(isClass (configFile >> "CfgVehicles" >> (_it select 0))) then { _repl = _repl + 1; };', $src);
    }

    public function testDiagnosticCollectsEverythingWithoutPlayerIdentity(): void
    {
        $src = self::read(self::TOC . 'fn_tocDiag.sqf');
        $i = strpos($src, '    case "collect": {');
        $j = strpos($src, '    case "local": {');
        self::assertNotFalse($i);
        self::assertNotFalse($j);
        $collect = substr($src, $i, $j - $i);
        foreach (['Version du serveur', 'RÉGLAGES', 'SALLES', 'écrans %1', 'POSTE %1', 'flux', 'postes tenus', 'SAUVEGARDE', 'PRÉRÉGLAGES', 'images par seconde du serveur'] as $part) {
            self::assertStringContainsString($part, $collect, "le relevé contient « {$part} »");
        }
        // Aucun nom ni identifiant de joueur (feuille de route, Z2).
        self::assertDoesNotMatchRegularExpression('/\bname\b|getPlayerUID|profileName|_x select 5|_x select 6\) (?!isNotEqualTo "")/', $collect);
        // Écrit au journal COMSPEC une fois, copié, montré dans une fenêtre ; trois accès.
        self::assertStringContainsString('{ ["INFO", "TOC", _x] call comspec_atak_native_fnc_log; } forEach _l;', $src);
        self::assertStringContainsString('copyToClipboard _a;', $src);
        self::assertStringContainsString('["COMSPEC ATAK", "TocDiag", ["Exporter le diagnostic du TOC", ', $src);
        self::assertStringContainsString('["comspec_atak_toc_diag", "Exporter le diagnostic du TOC", "", { ["run"] call comspec_atak_native_fnc_tocDiag; }', $src);
        self::assertStringContainsString('["Diag", "Exporter le diagnostic du TOC", { ["run"] call comspec_atak_native_fnc_tocDiag; }] call _add;', self::read(self::TOC . 'fn_tocStation.sqf'));
        self::assertStringContainsString('if !(["check", [_u, "diag", "", "diag.export", ""]] call comspec_atak_native_fnc_tocAccess) exitWith { false };', $src);
    }
}
