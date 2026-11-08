/*
    Tests des fonctions pures du réseau maillé (mode développeur, fn_devMesh ; bouton TESTS de l'onglet MAILLAGE et mission de
    banc d'essai). Écrit « réussi » ou « échoué » par cas dans le RPT et rend [réussis, total].
*/
if !(["allowed"] call comspec_atak_native_fnc_devMesh) exitWith { [0, 0] };
private _results = [];
private _expect = {
    params ["_list", "_name", "_got", "_want"];
    _list pushBack [_name, _got isEqualTo _want];
};
private _read = {
    params ["_kind", "_raw", "_who"];
    ["check", [_kind, _raw, _who]] call comspec_atak_native_fnc_meshRead
};
private _nan = sqrt -1;
// Lecture sûre : valeurs correctes rendues, valeurs absurdes refusées.
[_results, "nœud correct", ["node", ["C3", 6000, player], player] call _read, ["C3", 6000, player]] call _expect;
[_results, "nœud : canal 17", ["node", ["C17", 6000, player], player] call _read, []] call _expect;
[_results, "nœud : portée 1e9", ["node", ["C1", 1e9, player], player] call _read, []] call _expect;
[_results, "nœud : portée NaN", ["node", ["C1", _nan, player], player] call _read, []] call _expect;
[_results, "nœud : portée nulle", ["node", ["C1", 0, player], player] call _read, []] call _expect;
[_results, "nœud : antenne texte", ["node", ["C1", 500, "x"], player] call _read, []] call _expect;
[_results, "nœud : trois éléments manquants", ["node", ["C1"], player] call _read, []] call _expect;
[_results, "nœud : variable d'un autre type", ["node", "x", player] call _read, []] call _expect;
[_results, "MPU5 correcte", ["mpu5", [2.5, 1, 6000, "MPU5"], player] call _read, [2.5, 1, 6000, "MPU5"]] call _expect;
[_results, "MPU5 : débit 1e6", ["mpu5", [1e6, 1, 6000, "MPU5"], player] call _read, []] call _expect;
[_results, "MPU5 : nom tronqué", ((["mpu5", [1, 0, 500, "0123456789012345678901234567890"], player] call _read) select 3), "012345678901234567890123"] call _expect;
[_results, "voix correcte", ["voice", ["sw", "k", "87.5", 5000, "RWEST", 2, "PRC"], player] call _read, ["sw", "k", "87.5", 5000, "RWEST", 2, "PRC"]] call _expect;
[_results, "voix : sauts texte", ["voice", ["sw", "k", "87.5", 5000, "", "2", "PRC"], player] call _read, []] call _expect;
[_results, "voix : réseau inconnu", ["voice", ["sw", "k", "87.5", 5000, "Xfoo", 2, "PRC"], player] call _read, []] call _expect;
[_results, "voix : type inconnu", ["voice", ["zz", "k", "87.5", 5000, "", 2, "PRC"], player] call _read, []] call _expect;
[_results, "réseau voix correct", ["voiceNet", [["a", 43.5, "CH1"]], player] call _read, [["a", 43.5, "CH1"]]] call _expect;
[_results, "réseau voix : ligne fausse", ["voiceNet", [["a", 43.5, "CH1"], 3], player] call _read, []] call _expect;
[_results, "talkgroup correct", ["talkgroup", "ALPHA", player] call _read, "ALPHA"] call _expect;
[_results, "talkgroup : nombre", ["talkgroup", 3, player] call _read, ""] call _expect;
private _ok = { _x select 1 } count _results;
{ diag_log format ["[COMSPEC ATAK NATIVE][MESH] Test %1 : %2", _x select 0, ["échoué", "réussi"] select (_x select 1)]; } forEach _results;
diag_log format ["[COMSPEC ATAK NATIVE][MESH] Tests : %1 réussis sur %2", _ok, count _results];
[format ["Tests du maillage : %1 réussis sur %2 (détail dans le RPT)", _ok, count _results], 5] call comspec_atak_native_fnc_toast;
[_ok, count _results]
