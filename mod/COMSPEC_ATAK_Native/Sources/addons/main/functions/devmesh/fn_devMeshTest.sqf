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
[_results, "nœud correct", ["node", ["C3", 6000, player], player] call _read, ["C3", 6000, player, 1]] call _expect;
[_results, "nœud : largeur 5", ["node", ["C3", 6000, player, 5], player] call _read, []] call _expect;
[_results, "courbe milsim : moitié puis tiers", round (100 * ((["solve", [[[[1, 10, 1000]], [[0, 10, 1000], [2, 10, 1000]], [[1, 10, 1000]]], [2], [true, true, true], 6, 20000, [1, 1, 0.5, 0.33]]] call comspec_atak_native_fnc_meshGraph) select 0 select 0)), 50000] call _expect;
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
[_results, "groupes corrects", ["talkgroup", [1, 3], player] call _read, [1, 3]] call _expect;
[_results, "groupes : numéro 17", ["talkgroup", [17], player] call _read, []] call _expect;
[_results, "groupes : texte", ["talkgroup", "ALPHA", player] call _read, []] call _expect;
[_results, "fréquence : même groupe", ["ofFreq", [43.5]] call comspec_atak_native_fnc_meshGroups, ["ofFreq", ["43.5"]] call comspec_atak_native_fnc_meshGroups] call _expect;
[_results, "fréquence nulle : aucun groupe", ["ofFreq", [0]] call comspec_atak_native_fnc_meshGroups, 0] call _expect;
// Graphe : chaîne A-B-C-D, passerelle D. Débit = plus petit lien x 0,8 par saut relayé.
private _chain = [[[1, 10, 10000]], [[0, 10, 10000], [2, 10, 10000]], [[1, 10, 10000], [3, 10, 10000]], [[2, 10, 10000]]];
private _all = [true, true, true, true];
private _solve = {
    params ["_adj", "_src", "_allow", "_hopMax"];
    ["solve", [_adj, _src, _allow, _hopMax, 20000]] call comspec_atak_native_fnc_meshGraph
};
private _s6 = [_chain, [3], _all, 6] call _solve;
[_results, "chaîne : 3 sauts", (_s6 select 1) select 0, 3] call _expect;
[_results, "chaîne : débit réduit par saut", round ((_s6 select 0) select 0), 6400] call _expect;
[_results, "chaîne : premier maillon", (_s6 select 2) select 0, 1] call _expect;
[_results, "chaîne : passerelle retrouvée", (_s6 select 4) select 0, 3] call _expect;
[_results, "plafond de 2 sauts : A hors maillage", (([_chain, [3], _all, 2] call _solve) select 1) select 0, -1] call _expect;
[_results, "nœud interdit (autre canal) : A coupé", (([_chain, [3], [true, false, true, true], 6] call _solve) select 1) select 0, -1] call _expect;
private _parted = [[[1, 10, 10000]], [[0, 10, 10000]], [[3, 10, 10000]], [[2, 10, 10000]]];
[_results, "partition : A coupé de D", (([_parted, [3], _all, 6] call _solve) select 1) select 0, -1] call _expect;
// A-D direct à 1 000 kbit/s, ou A-B-D à 5 000 : le plus large gagne, sauf si le plafond l'interdit.
private _wide = [[[3, 10, 1000], [1, 10, 5000]], [[0, 10, 5000], [3, 10, 5000]], [], [[0, 10, 1000], [1, 10, 5000]]];
private _w6 = [_wide, [3], _all, 6] call _solve;
[_results, "plus large : détour de 2 sauts", (_w6 select 1) select 0, 2] call _expect;
[_results, "plus large : débit du détour", round ((_w6 select 0) select 0), 4000] call _expect;
[_results, "plafond de 1 saut : lien direct", (([_wide, [3], _all, 1] call _solve) select 1) select 0, 1] call _expect;
// Gravité (section 3 du plan).
{
    _x params ["_label", "_in", "_want"];
    [_results, _label, ["gravity", _in] call comspec_atak_native_fnc_meshGraph, _want] call _expect;
} forEach [
    ["gravité nominale", [12, 2, 6], 0], ["gravité dégradée, marge", [6, 2, 6], 1], ["gravité dégradée, sauts", [12, 5, 6], 1],
    ["gravité limite", [2, 2, 6], 2], ["gravité coupée, hors maillage", [12, -1, 6], 3], ["gravité coupée, trop de sauts", [12, 7, 6], 3]
];
// Hystérésis : un lien ne tombe qu'au deuxième constat et revient tout de suite.
private _h1 = ["hyst", [true, 8, 4000, []]] call comspec_atak_native_fnc_meshGraph;
private _h2 = ["hyst", [false, -3, 0, _h1 select 1]] call comspec_atak_native_fnc_meshGraph;
private _h3 = ["hyst", [false, -3, 0, _h2 select 1]] call comspec_atak_native_fnc_meshGraph;
private _h4 = ["hyst", [true, 8, 4000, _h2 select 1]] call comspec_atak_native_fnc_meshGraph;
[_results, "hystérésis : lien bon gardé", _h1 select 0, true] call _expect;
[_results, "hystérésis : premier constat, gardé", _h2 select 0, true] call _expect;
[_results, "hystérésis : premier constat, marge -1", _h2 select 2, -1] call _expect;
[_results, "hystérésis : deuxième constat, tombé", _h3 select 0, false] call _expect;
[_results, "hystérésis : retour immédiat", _h4 select 1, [0, 8, 4000]] call _expect;
// Signal ACRE2 : jamais pire qu'ACRE2, aucune rallonge au niveau 3 ou sans lien.
private _sig = { params ["_in"]; ["sigMap", _in] call comspec_atak_native_fnc_mpuVoice };
[_results, "signal : niveau 3, origine", [[0.1, -110, 2, 3, true]] call _sig, [0.1, -110]] call _expect;
[_results, "signal : sans lien, origine", [[0.1, -110, -1, 0, true]] call _sig, [0.1, -110]] call _expect;
[_results, "signal : niveau 2", [[0.1, -110, 2, 2, true]] call _sig, [0.45, -95]] call _expect;
[_results, "signal : direct meilleur gardé", [[0.9, -50, 2, 2, true]] call _sig, [0.9, -50]] call _expect;
private _ok = { _x select 1 } count _results;
{ diag_log format ["[COMSPEC ATAK NATIVE][MESH] Test %1 : %2", _x select 0, ["échoué", "réussi"] select (_x select 1)]; } forEach _results;
diag_log format ["[COMSPEC ATAK NATIVE][MESH] Tests : %1 réussis sur %2", _ok, count _results];
[format ["Tests du maillage : %1 réussis sur %2 (détail dans le RPT)", _ok, count _results], 5] call comspec_atak_native_fnc_toast;
[_ok, count _results]
