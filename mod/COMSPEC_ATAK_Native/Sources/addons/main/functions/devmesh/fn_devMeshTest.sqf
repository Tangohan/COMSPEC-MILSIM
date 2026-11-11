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
[_results, "nœud correct", ["node", ["C3", 6000, player], player] call _read, ["C3", 6000, player, 1, -2, 5]] call _expect;
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
// Goniométrie : deux relevés croisés.
private _crossed = ["cross", [[0, 0, 0], 45, [1000, 0, 0], 315]] call comspec_atak_native_fnc_meshEW;
[_results, "croisement de deux relevés", _crossed apply { round _x }, [500, 500]] call _expect;
[_results, "relevés parallèles : aucun point", ["cross", [[0, 0, 0], 45, [1000, 0, 0], 45]] call comspec_atak_native_fnc_meshEW, []] call _expect;
[_results, "écoute seule : ne relaie pas", (((["solve", [_chain, [3], _all, 6, 20000, [1, 1, 0.8, 0.64], [false, true, false, false]]] call comspec_atak_native_fnc_meshGraph) select 1) select 0), -1] call _expect;
// Ping : chemin et maillon faible (A <- B <- C, 3 est la source).
private _weakAdj = [[[1, 12, 1000]], [[0, 12, 1000], [2, 3, 1000]], [[1, 3, 1000], [3, 8, 1000]], [[2, 8, 1000]]];
private _weakVia = (["solve", [_weakAdj, [3], [true, true, true, true], 6, 20000]] call comspec_atak_native_fnc_meshGraph) select 2;
private _weakChain = ["chain", [_weakAdj, _weakVia, 0]] call comspec_atak_native_fnc_meshGraph;
[_results, "chemin : 3 liens", count _weakChain, 3] call _expect;
[_results, "maillon faible : lien 2-1, 3 dB", ["weakest", [_weakChain]] call comspec_atak_native_fnc_meshGraph, [2, 1, 3]] call _expect;
[_results, "chemin : boucle bornée", count (["chain", [[[[1, 5, 1]], [[0, 5, 1]]], [1, 0], 0]] call comspec_atak_native_fnc_meshGraph), 17] call _expect;
[_results, "brouillage : hors zone", ["jamAt", [[2000, 0, 0], [[[0, 0, 0], 500]], 1]] call comspec_atak_native_fnc_meshGraph, 1] call _expect;
[_results, "brouillage : autre canal", ["jamAt", [[0, 0, 0], [[[0, 0, 0], 500, 0, "", 1e9, false, 1, 2, ""]], 1]] call comspec_atak_native_fnc_meshGraph, 1] call _expect;
// Couverture des relais (fn_relayNet) : accélérateur déterministe, bandes fondues sans perte, lecture directe, reprise incrémentale.
private _covCells = [[[0, 0, 0], 5, 0, "A"], [[0, 100, 0], 5, 0, "A"], [[0, 200, 0], 1, 10, "A"], [[100, 0, 0], 0, 100, ""], [[100, 200, 0], 0, 100, ""]];
private _acc1 = ["covAccel", [_covCells, [100, 100], 100, 3]] call comspec_atak_native_fnc_relayNet;
private _acc2 = ["covAccel", [+_covCells, [100, 100], 100, 3]] call comspec_atak_native_fnc_relayNet;
[_results, "couverture : bandes fondues", _acc1 select 2, [[[0, 50, 0], 50, 100, 0], [[0, 200, 0], 50, 50, 2], [[100, 0, 0], 50, 50, 4], [[100, 200, 0], 50, 50, 4]]] call _expect;
[_results, "couverture : même grille, mêmes bandes", _acc1 select 2, _acc2 select 2] call _expect;
[_results, "couverture : même grille, même index", [0, 1, 2, 3, 4, 5] apply { (_acc1 select 1) getOrDefault [_x, -1] }, [0, 1, 2, 3, 4, 5] apply { (_acc2 select 1) getOrDefault [_x, -1] }] call _expect;
[_results, "couverture : index d'une case", (_acc1 select 1) getOrDefault [5, -1], 4] call _expect;
private _covArea = 0;
{ _covArea = _covArea + (_x select 2) / 50; } forEach (_acc1 select 2);
[_results, "couverture : les bandes couvrent toutes les cases", _covArea, count _covCells] call _expect;
[_results, "couverture : paliers", [5, 2, 0.6, 0.1, 0] apply { ["covBand", [_x]] call comspec_atak_native_fnc_relayNet }, [0, 1, 2, 3, 4]] call _expect;
private _covSigA = [[0, 0], 5000, 1, [0, 0, 0, false, 100], [], [[[10, 10, 30], 2000, 12, "R1"], [[5000, 0, 30], 2000, 12, "R2"]]];
private _covSigB = [[0, 0], 5000, 1, [0, 0, 0, false, 100], [], [[[10, 10, 30], 2000, 12, "R1"], [[5200, 0, 30], 2000, 12, "R2"]]];
private _covSigC = [[50, 0], 5000, 1, [0, 0, 0, false, 100], [], [[[10, 10, 30], 2000, 12, "R1"]]];
[_results, "couverture : rien n'a changé, rien à recalculer", ["covDirty", [_covSigA, +_covSigA]] call comspec_atak_native_fnc_relayNet, [true, []]] call _expect;
[_results, "couverture : relais déplacé, deux disques", ["covDirty", [_covSigA, _covSigB]] call comspec_atak_native_fnc_relayNet, [true, [[[5000, 0, 30], 2000], [[5200, 0, 30], 2000]]]] call _expect;
[_results, "couverture : centre déplacé, tout recalculer", ["covDirty", [_covSigA, _covSigC]] call comspec_atak_native_fnc_relayNet, [false, []]] call _expect;
[_results, "couverture : première fois, tout recalculer", ["covDirty", [[], _covSigA]] call comspec_atak_native_fnc_relayNet, [false, []]] call _expect;
// Lecture d'un point : même réponse avec l'index qu'en parcourant la grille (ancien format, sans accélérateur).
private _covSaved = missionNamespace getVariable "COMSPEC_ATAK_Coverage";
missionNamespace setVariable ["COMSPEC_ATAK_Coverage", [[100, 100], 100, _covCells, 150, 60, 12, 1, 3.6, _acc1, _covSigA, 0]];
private _atFast = [[10, 190, 0], [95, 20, 0], [400, 400, 0]] apply { ["at", _x] call comspec_atak_native_fnc_relayNet };
missionNamespace setVariable ["COMSPEC_ATAK_Coverage", [[100, 100], 100, _covCells, 150, 60, 12, 1, 3.6]];
private _atScan = [[10, 190, 0], [95, 20, 0], [400, 400, 0]] apply { ["at", _x] call comspec_atak_native_fnc_relayNet };
if (isNil "_covSaved") then { missionNamespace setVariable ["COMSPEC_ATAK_Coverage", nil]; } else { missionNamespace setVariable ["COMSPEC_ATAK_Coverage", _covSaved]; };
[_results, "couverture : lecture par l'index", _atFast, [[1, 10, "A"], [0, 100, ""], []]] call _expect;
[_results, "couverture : index et parcours d'accord", _atFast, _atScan] call _expect;
private _ok = { _x select 1 } count _results;
{ diag_log format ["[COMSPEC ATAK NATIVE][MESH] Test %1 : %2", _x select 0, ["échoué", "réussi"] select (_x select 1)]; } forEach _results;
diag_log format ["[COMSPEC ATAK NATIVE][MESH] Tests : %1 réussis sur %2", _ok, count _results];
[format ["Tests du maillage : %1 réussis sur %2 (détail dans le RPT)", _ok, count _results], 5] call comspec_atak_native_fnc_toast;
[_ok, count _results]
