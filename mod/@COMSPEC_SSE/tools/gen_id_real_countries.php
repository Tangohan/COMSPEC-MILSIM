<?php

declare(strict_types=1);

/**
 * Écrit addons/identity/functions/fn_idRealCountries.sqf depuis app/Support/SseIdRealCountryCatalog.php (site) :
 * mêmes pays réels stylisés, codes MRZ OACI, formats de numéro, autorités, durées et réservoirs de noms en jeu et
 * sur Athena (une graine donne la même identité des deux côtés).
 *
 *   php tools/gen_id_real_countries.php          # régénère le fichier (versionné)
 *   php tools/gen_id_real_countries.php --check  # échoue si le fichier versionné diffère
 */

$repo = dirname(__DIR__, 3);
require $repo . '/app/Support/SseIdRealCountryCatalog.php';

use App\Support\SseIdRealCountryCatalog as RC;

$out = dirname(__DIR__) . '/addons/identity/functions/fn_idRealCountries.sqf';
$q = static fn (string $s): string => '"' . str_replace('"', '""', $s) . '"';
$arr = static fn (array $l): string => '[' . implode(', ', array_map($q, $l)) . ']';

$rows = [];
foreach (RC::COUNTRIES as $code => $c) {
    $pool = RC::POOLS[$c['region']];
    $validity = '[' . implode(', ', array_map(static fn (string $t, int $y): string => '["' . $t . '", ' . $y . ']', array_keys($c['validity']), array_values($c['validity']))) . ']';
    $rows[] = '        [' . implode(', ', [
        $q($code), $q($c['mrz']), $q($c['name']), $q($c['name_en']), $q($c['name_native']), $q(RC::numberFormat($code)),
        $q($c['authority']), $q($c['zone']), $arr($c['languages']), $validity, $arr(array_map('strtoupper', $c['colors'])),
    ]) . ",\n            " . $arr($pool['m']) . ",\n            " . $arr($pool['f']) . ",\n            " . $arr($pool['last'])
        . ",\n            " . $arr($pool['places']) . ', ' . $q($pool['f_suffix'] ?? '') . ']';
}

$sqf = <<<'SQF'
/*
    GÉNÉRÉ par tools/gen_id_real_countries.php depuis app/Support/SseIdRealCountryCatalog.php (site) : ne pas modifier
    à la main.
    Pays réels STYLISÉS, réservés à un univers « réaliste — simulation » : réglage CBA comspec_sse_id_realSim, ou pays
    importé par la communauté sur Athena (paquet, real_sim). Gabarit générique par zone de style (europe : bordeaux,
    americas : bleu, other : vert ou noir), mention SIMULATION — DOCUMENT DE JEU, aucun vrai modèle ni emblème copié ;
    numéros au format générique du jeu ; code MRZ = code OACI (Allemagne « D », imprimé « D<< »).
    [] call comspec_sse_fnc_idRealCountries -> HashMap code -> HashMap pays (mêmes clés que fn_idCountries, plus
    realSim, zone, nameNative, validity HashMap type -> années, lastFSuffix : nom au féminin, ex. Ivanov -> Ivanova)
*/
private _m = missionNamespace getVariable "comspec_sse_idRealBase";
if (!isNil "_m") exitWith { _m };
_m = createHashMap;
{
    _x params ["_code", "_mrz", "_name", "_nameEn", "_nameNative", "_fmt", "_auth", "_zone", "_langs", "_validity", "_colors",
        "_first", "_firstF", "_last", "_cities", "_fSuffix"];
    _m set [_code, createHashMapFromArray [
        ["code", _code], ["name", _name], ["nameEn", _nameEn], ["nameNative", _nameNative], ["mrz", _mrz],
        ["number_format", _fmt], ["authority", _auth], ["validity", createHashMapFromArray _validity], ["cities", _cities],
        ["chip", true], ["fictional", false], ["realSim", true], ["zone", _zone], ["theme", "realistic_sim"],
        ["languages", _langs], ["docTypes", _validity apply { _x select 0 }], ["colors", _colors], ["active", true],
        ["first", _first], ["firstF", _firstF], ["last", _last], ["lastFSuffix", _fSuffix]
    ]];
} forEach [

SQF;
$sqf .= implode(",\n", $rows) . "\n];\nmissionNamespace setVariable [\"comspec_sse_idRealBase\", _m];\n_m\n";

if (in_array('--check', $argv, true)) {
    $cur = is_file($out) ? str_replace("\r\n", "\n", (string) file_get_contents($out)) : '';
    if ($cur !== $sqf) {
        fwrite(STDERR, "fn_idRealCountries.sqf n'est pas à jour : php tools/gen_id_real_countries.php\n");
        exit(1);
    }
    echo "fn_idRealCountries.sqf à jour (" . count($rows) . " pays).\n";
    exit(0);
}
file_put_contents($out, $sqf);
echo "fn_idRealCountries.sqf écrit (" . count($rows) . " pays).\n";
