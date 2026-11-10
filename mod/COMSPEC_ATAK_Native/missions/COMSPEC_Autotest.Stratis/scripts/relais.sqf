// Réseau relais plus réaliste (4.2.1, fn_meshGraph) : cause de coupure, liens manqués, bâtiments, reconvergence, dépannage MPU.
// Fonctions sans effet et faits imposés seulement : aucun relais posé, aucune radio ACRE2 ni TFAR touchée, aucun réglage du serveur gardé.
if !("relais" call AT_fnc_groupe) exitWith {};
["relais_causes", {
    if (["comspec_atak_native_fnc_meshGraph"] call AT_fnc_nil) exitWith {};
    // Plus forte perte au-delà de 3 dB : relief, bâtiments, météo ; rien de notable : 0 (la distance seule).
    private _cas = [
        [[["Relief entre les antennes", 14], ["Pluie sur le trajet (1.2 km)", 2]], 2],
        [[["Zone de Fresnel partiellement masquée", 5]], 2],
        [[["Relief entre les antennes", 4], ["Bâtiments sur le trajet", 9]], 3],
        [[["Orage", 6]], 4],
        [[["Pluie sur le trajet (0.4 km)", 1.5]], 0],
        [[], 0]
    ];
    {
        _x params ["_l", "_want"];
        private _c = ["lossCode", _l] call comspec_atak_native_fnc_meshGraph;
        [_c isEqualTo _want, format ["lossCode %1 : %2 au lieu de %3", _l, _c, _want]] call AT_fnc_check;
    } forEach _cas;
    for "_c" from 1 to 8 do {
        private _t = ["causeTxt", [_c]] call comspec_atak_native_fnc_meshGraph;
        private _f = ["causeFix", [_c, 0]] call comspec_atak_native_fnc_meshGraph;
        [_t isEqualType "" && {_t isNotEqualTo ""} && {_f isEqualType ""} && {(count _f) > 15}, format ["cause %1 : « %2 » / « %3 »", _c, _t, _f]] call AT_fnc_check;
    };
    // Distance : la distance à gagner est chiffrée.
    private _fd = ["causeFix", [1, 420]] call comspec_atak_native_fnc_meshGraph;
    [(_fd find "400 m") >= 0, format ["conseil distance : %1", _fd]] call AT_fnc_check;
    call AT_fnc_done;
}, 30] call AT_fnc_run;

["relais_graphe_explique", {
    if (["comspec_atak_native_fnc_meshGraph"] call AT_fnc_nil) exitWith {};
    // Graphe local imposé (réglage « calculé sur le serveur » décoché le temps de l'étape) : 3 nœuds, un lien faible limité par les bâtiments,
    // un lien manqué à cause du relief. Format diffusé : nœuds par 5, liens par 3, causes par 2, liens manqués par 3.
    private _oldSrv = missionNamespace getVariable ["comspec_atak_native_mesh_server", true];
    private _oldLoc = missionNamespace getVariable ["COMSPEC_ATAK_MeshLocal", []];
    missionNamespace setVariable ["comspec_atak_native_mesh_server", false];
    private _side = str side group player;
    private _nf = [player, player, 1, 600, 200, player, player, 1, 600, 200, player, player, 1, 600, 200];
    private _lf = [0 + 64 * 1, 4, 20];
    private _cf = [0 + 64 * 1, 3];
    private _mf = [1 + 64 * 2, 2, -8, 2 + 64 * 1, 2, -8];
    missionNamespace setVariable ["COMSPEC_ATAK_MeshLocal", [987654, [[_side, _nf, _lf, 3, _cf, _mf]]]];
    uiNamespace setVariable ["COMSPEC_ATAK_MeshDec", []];
    private _g = ["graph", [_side]] call comspec_atak_native_fnc_meshGraph;
    _g params [["_nodes", []], "", ["_links", []], "", ["_causes", createHashMap], ["_miss", []]];
    [(count _nodes) isEqualTo 3 && {(count _links) isEqualTo 1}, format ["graphe lu : %1 nœuds, %2 liens", count _nodes, count _links]] call AT_fnc_check;
    [((_links param [0, []]) param [4, -1]) isEqualTo 3, format ["cause du lien faible : %1", _links]] call AT_fnc_check;
    [((_miss param [1, []]) param [0, []]) isEqualTo [-8, 2, 2], format ["lien manqué du nœud 1 : %1", _miss]] call AT_fnc_check;
    // Ancien serveur : ni causes ni liens manqués, le graphe se lit quand même.
    missionNamespace setVariable ["COMSPEC_ATAK_MeshLocal", [987655, [[_side, _nf, _lf, 3]]]];
    private _g2 = ["graph", [_side]] call comspec_atak_native_fnc_meshGraph;
    [(count (_g2 param [2, []])) isEqualTo 1 && {(count (_g2 param [4, createHashMap])) isEqualTo 0}, "graphe sans ajouts 4.2.1 illisible"] call AT_fnc_check;
    // Valeur fausse dans les liens manqués : ignorée, le reste du graphe tient.
    missionNamespace setVariable ["COMSPEC_ATAK_MeshLocal", [987656, [[_side, _nf, _lf, 3, _cf, ["x", 1, 2]]]]];
    private _g3 = ["graph", [_side]] call comspec_atak_native_fnc_meshGraph;
    [(count (_g3 param [2, []])) isEqualTo 1 && {((_g3 param [5, []]) findIf { _x isNotEqualTo [] }) < 0}, "lien manqué invalide non ignoré"] call AT_fnc_check;
    missionNamespace setVariable ["COMSPEC_ATAK_MeshLocal", _oldLoc];
    missionNamespace setVariable ["comspec_atak_native_mesh_server", _oldSrv];
    uiNamespace setVariable ["COMSPEC_ATAK_MeshDec", []];
    call AT_fnc_done;
}, 30] call AT_fnc_run;

["relais_depannage", {
    if (["comspec_atak_native_fnc_mpuDiag"] call AT_fnc_nil) exitWith {};
    private _base = ["sample"] call comspec_atak_native_fnc_mpuDiag;
    _base set ["relayNeed", false];
    private _stepOf = { params ["_f", "_id"]; ((["eval", [_f]] call comspec_atak_native_fnc_mpuDiag) select { (_x select 0) isEqualTo _id }) param [0, []] };
    // Hors maillage avec une cause : l'explication la donne, le conseil remplace l'exemple général.
    private _f = +_base;
    _f set ["meshOk", false];
    _f set ["meshWhy", "hors du maillage : lien Ma radio › Relais Nord (1.2 km) coupé par le relief : il manque 8 dB"];
    _f set ["meshCause", "lien Ma radio › Relais Nord (1.2 km) coupé par le relief : il manque 8 dB"];
    _f set ["meshFix", ["causeFix", [2, 0]] call comspec_atak_native_fnc_meshGraph];
    private _s = [_f, "mesh"] call _stepOf;
    [(_s param [1, ""]) isEqualTo "KO" && {((_s param [3, ""]) find "relief") >= 0} && {((_s param [4, ""]) find "crête") >= 0}, format ["hors maillage expliqué : %1", _s]] call AT_fnc_check;
    // Reconvergence : attention, pas panne.
    private _r = +_base;
    _r set ["meshOk", false];
    _r set ["meshReconv", true];
    _r set ["meshWhy", "reconvergence du maillage : Relais Nord perdu, nouvelle route dans 2 s"];
    private _rs = [_r, "mesh"] call _stepOf;
    [(_rs param [1, ""]) isEqualTo "ATTENTION" && {(_rs param [5, ""]) isEqualTo "reconvergence"}, format ["reconvergence : %1", _rs]] call AT_fnc_check;
    // Aucun relais en service mais un relais proche en panne : la panne est nommée.
    private _d = +_base;
    _d set ["relayNeed", true];
    _d set ["relays", 0];
    _d set ["relayDown", "Relais Nord à 800 m : batterie vide"];
    private _ds = [_d, "relay"] call _stepOf;
    [(_ds param [1, ""]) isEqualTo "KO" && {((_ds param [3, ""]) find "batterie vide") >= 0}, format ["relais en panne : %1", _ds]] call AT_fnc_check;
    // Chemin fragile : la limite apparaît dans le détail.
    private _w = +_base;
    _w set ["meshLevel", 2];
    _w set ["meshCause", "maillon faible Ma radio › Alpha 2 : marge 2 dB, limitée par les bâtiments"];
    _w set ["meshFix", ["causeFix", [3, 0]] call comspec_atak_native_fnc_meshGraph];
    private _ws = [_w, "mesh"] call _stepOf;
    [((_ws param [3, ""]) find "bâtiments") >= 0 && {((_ws param [4, ""]) find "toit") >= 0}, format ["maillon faible : %1", _ws]] call AT_fnc_check;
    call AT_fnc_done;
}, 30] call AT_fnc_run;

["relais_vue_reelle", {
    if (["comspec_atak_native_fnc_meshGraph", "comspec_atak_native_fnc_mpuLink"] call AT_fnc_nil) exitWith {};
    // Lecture réelle (liaison réaliste) : les nouvelles clés existent toujours, même vides, et la carte a de quoi dessiner le lien manquant.
    private _m = uiNamespace getVariable ["COMSPEC_ATAK_MpuMesh", createHashMap];
    if ((count _m) isEqualTo 0) exitWith { "maillage jamais lu (liaison réaliste ou MPU5 absente)" call AT_fnc_skip; };
    if !("cause" in _m) exitWith { "lecture du maillage antérieure à la 4.2.1" call AT_fnc_skip; };
    { [_x in _m, format ["clé %1 absente de la vue du maillage", _x]] call AT_fnc_check; } forEach ["cause", "fix", "miss", "reconv"];
    (format ["vue réelle : ok %1, cause « %2 », lien manqué %3", _m getOrDefault ["ok", false], _m getOrDefault ["cause", ""], _m getOrDefault ["miss", []]]) call AT_fnc_info;
    call AT_fnc_done;
}, 30] call AT_fnc_run;
