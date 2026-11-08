/*
    App Routes : routes nommées (les miennes, celles reçues de l'équipe, du groupe ou du camp), type de tracé, style,
    étapes typées, calculs (distance, cap, durée, heure d'arrivée), guidage « Suivre la route », partage par le réseau
    ATAK (fn_netSend : sans réseau, l'envoi attend en file ; à la réception, fn_offline retient ce qui arrive hors
    réseau), carte d'Arma et Athena (marqueurs polyligne et repères d'étapes, fn_markerNet / fn_markerWeb), vue 3D
    (fn_route3d), actions ACE « Infos tactiques JVN » d'Overwatch (point d'itinéraire regardé : route « Itinéraire JVN »).
    Données :
      missionNamespace COMSPEC_ATAK_RteMine : id -> route (gardées dans le profil, par carte : COMSPEC_ATAK_Rte_<carte>) ;
      missionNamespace COMSPEC_ATAK_RteRx   : id -> route reçue (pour la partie ; rejouées par le serveur à l'arrivée) ;
      missionNamespace COMSPEC_ATAK_RteFollow : route suivie (HashMap id, idx, t0), vide sinon.
    Route (HashMap) : id, name, owner, uid, kind (FOOT | VEH | AIR | SEA), roads (véhicule : par les routes), speed
      (km/h), corr (couloir aérien, m), color (classe CfgMarkerColors), width (1-3), style (SOLID | DASH | ARROW), vis
      (carte ATAK), arma (carte d'Arma et Athena), d3 (vue 3D), hh (heure H « HH:MM » ou ""), steps, legs (tracé par
      les routes : points de chaque tronçon, [] = ligne droite), rev, from (auteur d'une route reçue), scope (dernier
      partage : 0 moi, 1 équipe, 2 groupe, 3 camp), live (renvoyée à chaque modification) ; en mémoire seulement :
      mk (marqueurs d'Arma posés), busy (calcul par les routes en cours).
    Étape : [position [x, y, 0], type, nom, consigne, H+ (minutes, -1 : aucune), action attendue].
    Params : [action, argument] — voir les « case ».
*/
params [["_act", ""], ["_arg", []]];
// Lectures faites à chaque image (fn_routeDraw : chaque route) ou chaque seconde (suivi, vue 3D) servies avant la
// préparation des tables ci-dessous : tracé gardé par révision et route par son id, une fois les routes relues du profil.
if (_act isEqualTo "geo" && {
    private _g = (uiNamespace getVariable ["COMSPEC_ATAK_RteGeo", createHashMap]) getOrDefault [_arg get "id", []];
    (_g param [0, ""]) isEqualTo format ["%1|%2", _arg get "id", _arg get "rev"]
}) exitWith { (uiNamespace getVariable "COMSPEC_ATAK_RteGeo") get (_arg get "id") };
if (_act isEqualTo "get" && {!isNil {missionNamespace getVariable "COMSPEC_ATAK_RteMine"}}) exitWith {
    (missionNamespace getVariable "COMSPEC_ATAK_RteMine") getOrDefault [_arg, (missionNamespace getVariable ["COMSPEC_ATAK_RteRx", createHashMap]) getOrDefault [_arg, createHashMap]]
};
private _tr = { _this call comspec_atak_native_fnc_tr };
private _maxSteps = (missionNamespace getVariable ["comspec_atak_native_route_max", 40]) max 5 min 80;
// Types de tracé : clé, libellé, icône, vitesse par défaut (km/h), facteur du rayon d'arrivée, couleur, style.
private _kinds = [
    ["FOOT", "À pied", "\a3\ui_f\data\igui\cfg\simpletasks\types\walk_ca.paa", 4, 1, "ColorGreen", "SOLID"],
    ["VEH", "Véhicule", "\a3\ui_f\data\igui\cfg\simpletasks\types\car_ca.paa", 40, 2.4, "ColorOrange", "SOLID"],
    ["AIR", "Aérien", "\a3\ui_f\data\igui\cfg\simpletasks\types\heli_ca.paa", 180, 8, "ColorBlue", "DASH"],
    ["SEA", "Nautique", "\a3\ui_f\data\igui\cfg\simpletasks\types\boat_ca.paa", 25, 3, "ColorYellow", "DASH"]
];
// Types d'étape : clé, libellé, classe CfgMarkers, couleur, préfixe du nom automatique.
private _types = [
    ["SP", "Départ", "mil_start", [0.36, 0.85, 0.42, 1], "SP"],
    ["WP", "Point de passage", "mil_dot", [0.95, 0.75, 0.18, 1], "WP"],
    ["CP", "Checkpoint", "mil_triangle", [0.30, 0.80, 0.95, 1], "CP"],
    ["PL", "Ligne de phase", "mil_box", [1, 0.62, 0.20, 1], "PL"],
    ["RP", "Point de ralliement", "mil_join", [1, 0.55, 0.15, 1], "RP"],
    ["ORP", "Ralliement objectif", "mil_join", [0.95, 0.42, 0.20, 1], "ORP"],
    ["OBJ", "Objectif", "mil_objective", [0.95, 0.30, 0.25, 1], "OBJ"],
    ["LZ", "LZ (poser)", "mil_pickup", [0.75, 0.55, 1, 1], "LZ"],
    ["PZ", "PZ (embarquer)", "mil_pickup", [0.60, 0.70, 1, 1], "PZ"],
    ["RLP", "Point de dislocation", "mil_circle", [0.85, 0.85, 0.55, 1], "RLP"],
    ["END", "Arrivée", "mil_end", [0.92, 0.94, 0.92, 1], "FIN"]
];
// Actions attendues à l'étape.
private _acts = [
    ["", "Aucune"], ["HALT", "Halte"], ["REPORT", "Compte rendu radio"], ["REGROUP", "Regroupement"], ["FORM", "Changer de formation"],
    ["WAIT", "Attendre l'ordre"], ["CROSS", "Franchissement"], ["OBS", "Observation"], ["EMB", "Embarquement"], ["DEB", "Débarquement"],
    ["ASSAULT", "Assaut"], ["SILENT", "Silence radio"]
];
private _colors = [
    ["ColorRed", [0.9, 0.1, 0.1, 1], "Rouge"], ["ColorOrange", [0.95, 0.5, 0.1, 1], "Orange"], ["ColorYellow", [0.95, 0.85, 0.1, 1], "Jaune"],
    ["ColorGreen", [0.15, 0.75, 0.25, 1], "Vert"], ["ColorBlue", [0.2, 0.45, 0.95, 1], "Bleu"], ["ColorPink", [0.9, 0.4, 0.7, 1], "Rose"],
    ["ColorBlack", [0.08, 0.08, 0.08, 1], "Noir"], ["ColorWhite", [1, 1, 1, 1], "Blanc"]
];
private _kindRow = { params ["_k"]; _kinds select ((_kinds findIf { (_x select 0) isEqualTo _k }) max 0) };
private _typeRow = { params ["_k"]; _types select ((_types findIf { (_x select 0) isEqualTo _k }) max 1) };
private _clean = { params ["_t", ["_n", 32]]; if !(_t isEqualType "") exitWith { "" }; ((trim _t) regexReplace ["[<>""\\]", ""]) select [0, _n] };
private _mine = missionNamespace getVariable "COMSPEC_ATAK_RteMine";
private _rx = missionNamespace getVariable ["COMSPEC_ATAK_RteRx", createHashMap];
if (isNil "_mine") then {
    // Premier appel : routes de cette carte relues du profil (marqueurs d'Arma d'une ancienne partie : plus posés).
    // Tables posées avant la lecture : fn_routeUnpack rappelle cette fonction (types, actions).
    _mine = createHashMap;
    missionNamespace setVariable ["COMSPEC_ATAK_RteMine", _mine];
    missionNamespace setVariable ["COMSPEC_ATAK_RteRx", _rx];
    {
        private _r = [_x] call comspec_atak_native_fnc_routeUnpack;
        if ((count _r) > 0) then { _r set ["arma", false]; _r set ["from", ""]; _mine set [_r get "id", _r]; };
    } forEach (profileNamespace getVariable [format ["COMSPEC_ATAK_Rte_%1", worldName], []]);
};
private _get = { params ["_id"]; _mine getOrDefault [_id, _rx getOrDefault [_id, createHashMap]] };
// Édition en cours (une de mes routes à la fois, vue EDIT de l'app) : missionNamespace COMSPEC_ATAK_RteEdit, HashMap
// id, orig (route empaquetée à l'ouverture), undo / redo (piles d'états empaquetés, 30 au plus), isNew, dirty.
// Pendant l'édition, rien ne part au réseau ni sur la carte d'Arma : un seul envoi à la validation (editCommit).
private _ed = missionNamespace getVariable ["COMSPEC_ATAK_RteEdit", createHashMap];
private _editing = { params ["_id"]; (_ed getOrDefault ["id", ""]) isEqualTo _id };
// État de la route gardé avant une modification (annuler) ; la pile « rétablir » repart de zéro.
private _snap = {
    params ["_r"];
    if !([_r get "id"] call _editing) exitWith {};
    private _u = _ed get "undo";
    _u pushBack +(["pack", _r] call comspec_atak_native_fnc_routeAction);
    if ((count _u) > 30) then { _u deleteAt 0; };
    _ed set ["redo", []];
    _ed set ["dirty", true];
};
// Route remise dans un état empaqueté (annuler, rétablir, annuler les modifications).
private _restore = {
    params ["_r", "_pk"];
    private _rev = _r getOrDefault ["rev", 0];
    // Affichage et partage (carte, 3D, Arma, portée) : réglés hors de l'édition, jamais repris d'un état gardé.
    { if !((_x select 0) in ["mk", "id", "vis", "d3", "arma", "live", "scope", "from"]) then { _r set [_x select 0, +(_x select 1)]; }; } forEach _pk;
    _r set ["rev", ((_r getOrDefault ["rev", 0]) max _rev) + 1];
    _r set ["roadGen", (_r getOrDefault ["roadGen", 0]) + 1];
    _r set ["busy", false];
    _r deleteAt "legKeep"; _r deleteAt "legFrom";
    if ((_r get "kind") isEqualTo "VEH" && {_r get "roads"} && {(count (_r get "steps")) >= 2} && {(count (_r get "legs")) isNotEqualTo ((count (_r get "steps")) - 1)}) then {
        ["roads", _r get "id"] call comspec_atak_native_fnc_routeAction;
    };
};
// Page redessinée (une fois par image au plus) : sur place pour la vue d'édition (carte gardée), sinon rendu complet.
private _refresh = {
    if ((missionNamespace getVariable ["COMSPEC_ATAK_RteRefreshAt", -1]) isEqualTo diag_frameNo) exitWith {};
    missionNamespace setVariable ["COMSPEC_ATAK_RteRefreshAt", diag_frameNo];
    [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "ROUTE") then { ["#refresh"] call comspec_atak_native_fnc_pageRoute; }; }] call comspec_atak_native_fnc_nextFrame;
};
private _save = {
    private _tok = (missionNamespace getVariable ["COMSPEC_ATAK_RteSaveTok", 0]) + 1;
    missionNamespace setVariable ["COMSPEC_ATAK_RteSaveTok", _tok];
    [{
        if (_this isNotEqualTo (missionNamespace getVariable ["COMSPEC_ATAK_RteSaveTok", 0])) exitWith {};
        private _m = missionNamespace getVariable ["COMSPEC_ATAK_RteMine", createHashMap];
        profileNamespace setVariable [format ["COMSPEC_ATAK_Rte_%1", worldName], (values _m) apply { ["pack", _x] call comspec_atak_native_fnc_routeAction }];
        [] call comspec_atak_native_fnc_saveProfile;
    }, _tok, 2] call comspec_atak_native_fnc_waitAndExec;
};
// Route modifiée : révision (tracé à refaire), profil, page, marqueurs d'Arma et partage en direct, regroupés.
private _changed = {
    params ["_r", ["_geo", true]];
    if (_geo) then { _r set ["rev", (_r getOrDefault ["rev", 0]) + 1]; };
    if ((_r get "id") in _mine) then {
        call _save;
        // Route en cours d'édition : envoi et marqueurs d'Arma à la validation seulement.
        if ([_r get "id"] call _editing) exitWith { _ed set ["dirty", true]; };
        private _id = _r get "id";
        private _tok = (_r getOrDefault ["syncTok", 0]) + 1;
        _r set ["syncTok", _tok];
        [{
            params ["_id", "_tok"];
            if (_tok isNotEqualTo ((["get", _id] call comspec_atak_native_fnc_routeAction) getOrDefault ["syncTok", -1])) exitWith {};
            private _r = ["get", _id] call comspec_atak_native_fnc_routeAction;
            if ((count _r) isEqualTo 0 || {_r getOrDefault ["busy", false]}) exitWith {};
            if ((_r get "arma") || {(count (_r getOrDefault ["mk", []])) > 0}) then { ["armaNow", _id] call comspec_atak_native_fnc_routeAction; };
            if ((_r getOrDefault ["live", false]) && {(_r get "scope") > 0}) then { ["share", [_id, _r get "scope", true]] call comspec_atak_native_fnc_routeAction; };
        }, [_id, _tok], 1.5] call comspec_atak_native_fnc_waitAndExec;
    };
    call _refresh;
};
// Nom automatique d'une étape (CP 3, RP 1, SP…) : le plus petit numéro que les autres étapes n'utilisent pas encore
// (SP et FIN : sans numéro tant qu'ils sont libres). Avant : nombre d'étapes du même type + 1, d'où des doublons après
// une suppression et un numéro décalé quand l'étape changeait de type (elle se comptait elle-même). _skip : index de
// l'étape renommée (-1 : étape pas encore posée).
private _autoName = {
    params ["_r", "_k", ["_skip", -1]];
    private _pre = ([_k] call _typeRow) select 4;
    if (_pre isEqualTo "FIN" && {(missionNamespace getVariable ["COMSPEC_ATAK_Lang", "en"]) isNotEqualTo "fr"}) then { _pre = "END"; };
    private _used = [];
    { if (_forEachIndex isNotEqualTo _skip) then { _used pushBack (toUpper (_x select 2)); }; } forEach (_r get "steps");
    if (_k in ["SP", "END"] && {!(_pre in _used)}) exitWith { _pre };
    private _n = 1;
    while { (format ["%1 %2", _pre, _n]) in _used } do { _n = _n + 1; };
    format ["%1 %2", _pre, _n]
};
// Calcul par les routes (véhicule) relancé si besoin, sinon tracé droit.
private _legsFix = {
    params ["_r"];
    // Pendant le calcul, lignes droites (les anciens tronçons ne correspondent plus aux étapes) ; ils restent en
    // réserve (legKeep) pour reprendre les tronçons inchangés.
    if ((count (_r get "legs")) > 0) then { _r set ["legKeep", _r get "legs"]; };
    _r set ["legs", []];
    if ((_r get "kind") isEqualTo "VEH" && {_r get "roads"} && {(count (_r get "steps")) >= 2}) then {
        ["roads", _r get "id"] call comspec_atak_native_fnc_routeAction;
    } else {
        _r set ["roadGen", (_r getOrDefault ["roadGen", 0]) + 1];
        _r set ["busy", false];
    };
};
private _targets = {
    params ["_sc"];
    switch (_sc) do {
        case 1: {
            private _ft = player getVariable ["COMSPEC_FT", ""];
            (units group player) select { isPlayer _x && {_x isNotEqualTo player} && {_ft isEqualTo "" || {(_x getVariable ["COMSPEC_FT", ""]) isEqualTo _ft}} }
        };
        case 2: { (units group player) select { isPlayer _x && {_x isNotEqualTo player} } };
        case 3: { allPlayers select { _x isNotEqualTo player && {side group _x isEqualTo side group player} } };
        default { [] };
    };
};

switch (_act) do {
    case "kinds": { _kinds };
    case "kind": { [_arg] call _kindRow };
    case "types": { _types };
    case "type": { [_arg] call _typeRow };
    case "acts": { _acts };
    case "actName": { ((_acts select ((_acts findIf { (_x select 0) isEqualTo _arg }) max 0)) select 1) };
    case "colors": { _colors };
    case "rgba": { +((_colors select ((_colors findIf { (_x select 0) isEqualTo _arg }) max 0)) select 1) };
    case "scopeName": { (["Moi seul", "Équipe", "Groupe", "Camp"] param [_arg, "Groupe"]) call _tr };
    case "mine": { _mine };
    case "rx": { _rx };
    case "get": { [_arg] call _get };
    case "isMine": { _arg in _mine };
    case "icon": {
        private _cache = uiNamespace getVariable ["COMSPEC_ATAK_RteIcons", createHashMap];
        private _i = _cache getOrDefault [_arg, ""];
        if (_i isEqualTo "") then {
            _i = getText (configFile >> "CfgMarkers" >> (([_arg] call _typeRow) select 2) >> "icon");
            _cache set [_arg, _i];
            uiNamespace setVariable ["COMSPEC_ATAK_RteIcons", _cache];
        };
        _i
    };
    // Route -> tableau de paires (profil, réseau) ; les clés en mémoire seulement sont laissées.
    case "pack": {
        private _r = _arg;
        ((keys _r) select { !(_x in ["mk", "busy", "legFrom", "legKeep", "roadGen", "syncTok"]) }) apply { [_x, _r get _x] }
    };
    case "new": {
        _arg params [["_kind", "FOOT"], ["_name", ""], ["_id", ""]];
        if ((count _mine) >= 60) exitWith { ["WARNING", "60 routes au plus : supprimez-en une", 3, 20] call comspec_atak_native_fnc_notify; "" };
        private _k = [_kind] call _kindRow;
        if (_id isEqualTo "") then { _id = format ["R%1_%2_%3", (getPlayerUID player) select [((count getPlayerUID player) - 6) max 0], round (diag_tickTime * 10) mod 1000000, floor random 1000]; };
        private _n = 1;
        while { ((values _mine) findIf { (_x get "name") isEqualTo format [("Route %1") call _tr, _n] }) >= 0 } do { _n = _n + 1; };
        if (_name isEqualTo "") then { _name = format [("Route %1") call _tr, _n]; };
        // 2.9.38 : partagée d'office (réglage serveur, le camp par défaut) et sur la carte d'Arma ; envoyée à la validation.
        private _dsc = ((missionNamespace getVariable ["comspec_atak_native_plan_scope", 3]) max 0) min 3;
        if !(missionNamespace getVariable ["comspec_atak_native_route_share", true]) then { _dsc = 0; };
        private _r = createHashMapFromArray [
            ["id", _id], ["name", [_name] call _clean], ["owner", [player] call comspec_atak_native_fnc_unitCallsign], ["uid", getPlayerUID player],
            ["kind", _k select 0], ["roads", (_k select 0) isEqualTo "VEH"], ["speed", _k select 3], ["corr", 500], ["color", _k select 5], ["width", 2],
            ["style", _k select 6], ["vis", true], ["arma", (missionNamespace getVariable ["comspec_atak_native_route_arma", true]) && {_dsc > 0}], ["d3", false], ["hh", ""], ["steps", []], ["legs", []], ["rev", 1], ["from", ""],
            ["scope", _dsc], ["live", _dsc > 0], ["mk", []]
        ];
        _mine set [_id, _r];
        call _save;
        call _refresh;
        _id
    };
    case "dup": {
        private _src = [_arg] call _get;
        if ((count _src) isEqualTo 0) exitWith { "" };
        private _id = ["new", [_src get "kind", format [("%1 (copie)") call _tr, _src get "name"]]] call comspec_atak_native_fnc_routeAction;
        if (_id isEqualTo "") exitWith { "" };
        private _r = _mine get _id;
        { _r set [_x, +(_src get _x)]; } forEach ["roads", "speed", "corr", "color", "width", "style", "hh", "steps", "legs"];
        [_r] call _changed;
        ["SUCCESS", format ["Copie créée : %1", _r get "name"], 2, 10] call comspec_atak_native_fnc_notify;
        _id
    };
    case "del": {
        private _r = [_arg] call _get;
        if ((count _r) isEqualTo 0) exitWith { false };
        // Marqueurs d'Arma posés : retirés partout (et sur Athena).
        { [_x, true] call comspec_atak_native_fnc_markerWeb; deleteMarker _x; } forEach (_r getOrDefault ["mk", []]);
        _r set ["mk", []];
        if (_arg in _mine) then {
            // Route partagée : retirée chez ceux qui l'ont reçue.
            if ((_r get "scope") > 0) then { ["unshare", _arg] call comspec_atak_native_fnc_routeAction; };
            _mine deleteAt _arg;
            call _save;
        } else {
            // Route reçue retirée ici : plus jamais reprise (rejeu du serveur, mise à jour de l'auteur), pour la partie.
            (missionNamespace getVariable ["COMSPEC_ATAK_RteGone", createHashMap]) set [_arg, 1e9];
            missionNamespace setVariable ["COMSPEC_ATAK_RteGone", missionNamespace getVariable ["COMSPEC_ATAK_RteGone", createHashMap]];
            _rx deleteAt _arg;
            ["INFO", "ROUTE", format ["Route reçue %1 retirée par le joueur : ses mises à jour seront ignorées", _arg]] call comspec_atak_native_fnc_log;
        };
        if (((missionNamespace getVariable ["COMSPEC_ATAK_RteFollow", createHashMap]) getOrDefault ["id", ""]) isEqualTo _arg) then { ["unfollow", true] call comspec_atak_native_fnc_routeAction; };
        call _refresh;
        true
    };
    case "set": {
        _arg params ["_id", "_key", "_v"];
        private _r = _mine getOrDefault [_id, createHashMap];
        // Route reçue : seuls l'affichage (carte, 3D) se règlent ici, le reste appartient à son auteur.
        if ((count _r) isEqualTo 0) exitWith {
            private _o = _rx getOrDefault [_id, createHashMap];
            if ((count _o) > 0 && {_key in ["vis", "d3"]}) then { _o set [_key, _v]; call _refresh; };
            false
        };
        switch (_key) do {
            case "name": { _v = [_v] call _clean; if (_v isEqualTo "") exitWith { _v = _r get "name"; }; };
            case "speed": { if (_v isEqualType "") then { _v = parseNumber _v; }; _v = (round _v) max 1 min 900; };
            case "corr": { if (_v isEqualType "") then { _v = parseNumber _v; }; _v = (round _v) max 50 min 5000; };
            case "width": { _v = (_v max 1) min 3; };
            case "hh": {
                _v = trim _v;
                if (_v isNotEqualTo "") then {
                    private _p = (_v splitString ":hH. ") apply { parseNumber _x };
                    _v = if ((count _p) >= 1 && {(_p select 0) >= 0} && {(_p select 0) < 24}) then {
                        format ["%1:%2", [str (_p select 0), "0" + str (_p select 0)] select ((_p select 0) < 10), [str (_p param [1, 0]), "0" + str (_p param [1, 0])] select ((_p param [1, 0]) < 10)]
                    } else { "" };
                };
            };
        };
        private _old = _r getOrDefault [_key, ""];
        if (_old isEqualTo _v) exitWith { false };
        if !(_key in ["vis", "d3", "arma", "live"]) then { [_r] call _snap; };
        _r set [_key, _v];
        switch (_key) do {
            case "kind": {
                private _k = [_v] call _kindRow;
                _r set ["speed", _k select 3];
                _r set ["roads", _v isEqualTo "VEH"];
                [_r] call _legsFix;
                [_r] call _changed;
            };
            case "roads": { [_r] call _legsFix; [_r] call _changed; };
            case "vis";
            case "d3": { call _save; call _refresh; };
            case "arma": {
                if (_v && {!(missionNamespace getVariable ["comspec_atak_native_route_arma", true])}) exitWith {
                    _r set ["arma", false];
                    ["WARNING", "Routes sur la carte d'Arma désactivées par le serveur", 3, 20] call comspec_atak_native_fnc_notify;
                };
                ["armaNow", _id] call comspec_atak_native_fnc_routeAction;
                call _save; call _refresh;
            };
            case "live": { call _save; };
            default { [_r, _key in ["color", "width", "style", "corr"]] call _changed; };
        };
        true
    };
    case "stepAdd": {
        _arg params ["_id", "_pos", ["_at", -1], ["_name", ""], ["_type", ""]];
        private _r = _mine getOrDefault [_id, createHashMap];
        if ((count _r) isEqualTo 0 || {(count _pos) < 2}) exitWith { -1 };
        private _st = _r get "steps";
        if ((count _st) >= _maxSteps) exitWith { ["WARNING", format ["%1 étapes au plus", _maxSteps], 3, 20] call comspec_atak_native_fnc_notify; -1 };
        // Type par défaut : départ pour la première étape, puis point de passage.
        if (_type isEqualTo "") then { _type = ["WP", "SP"] select ((count _st) isEqualTo 0); };
        [_r] call _snap;
        _name = [_name, 24] call _clean;
        if (_name isEqualTo "") then { _name = [_r, _type] call _autoName; };
        private _step = [[_pos select 0, _pos select 1, 0], _type, _name, "", -1, ""];
        if (_at < 0 || {_at >= count _st}) then { _st pushBack _step; _at = (count _st) - 1; } else { _st insert [_at, [_step]]; };
        // Suivi en cours sur cette route : l'étape visée reste la même.
        private _f = missionNamespace getVariable ["COMSPEC_ATAK_RteFollow", createHashMap];
        if ((_f getOrDefault ["id", ""]) isEqualTo _id && {_at <= (_f get "idx")}) then { _f set ["idx", (_f get "idx") + 1]; };
        [_r] call _legsFix;
        [_r] call _changed;
        _at
    };
    case "stepSet": {
        _arg params ["_id", "_i", "_vals"];
        private _r = _mine getOrDefault [_id, createHashMap];
        if ((count _r) isEqualTo 0) exitWith { false };
        private _st = _r get "steps";
        if (_i < 0 || {_i >= count _st}) exitWith { false };
        _vals params [["_name", ""], ["_type", ""], ["_note", ""], ["_hp", -1], ["_act", ""]];
        [_r] call _snap;
        private _s = _st select _i;
        if (_type isNotEqualTo "" && {(_types findIf { (_x select 0) isEqualTo _type }) >= 0}) then {
            // Nom automatique de l'ancien type (« WP 2 ») : remplacé par celui du nouveau ; un nom saisi est gardé.
            private _oldPre = ([_s select 1] call _typeRow) select 4;
            if ((_s select 1) isNotEqualTo _type && {_name isEqualTo (_s select 2)} && {((_name find _oldPre) isEqualTo 0) || {_name in ["FIN", "END"]}} && {(count _name) <= (count _oldPre) + 3}) then {
                _s set [1, _type];
                _name = [_r, _type, _i] call _autoName;
            };
            _s set [1, _type];
        };
        _name = [_name, 24] call _clean;
        if (_name isNotEqualTo "") then { _s set [2, _name]; };
        _s set [3, [_note, 120] call _clean];
        _s set [4, [-1, round _hp] select (_hp isEqualType 0 && {_hp >= 0})];
        _s set [5, [_act, ""] select ((_acts findIf { (_x select 0) isEqualTo _act }) < 0)];
        [_r, false] call _changed;
        true
    };
    case "stepMove": {
        _arg params ["_id", "_i", "_pos"];
        private _r = _mine getOrDefault [_id, createHashMap];
        if ((count _r) isEqualTo 0 || {(count _pos) < 2}) exitWith { false };
        private _st = _r get "steps";
        if (_i < 0 || {_i >= count _st}) exitWith { false };
        [_r] call _snap;
        (_st select _i) set [0, [_pos select 0, _pos select 1, 0]];
        [_r] call _legsFix;
        [_r] call _changed;
        true
    };
    case "stepUp";
    case "stepDown": {
        _arg params ["_id", "_i"];
        private _r = _mine getOrDefault [_id, createHashMap];
        if ((count _r) isEqualTo 0) exitWith { -1 };
        private _st = _r get "steps";
        private _j = _i + ([-1, 1] select (_act isEqualTo "stepDown"));
        if (_i < 0 || {_i >= count _st} || {_j < 0} || {_j >= count _st}) exitWith { -1 };
        [_r] call _snap;
        private _a = _st select _i;
        _st set [_i, _st select _j];
        _st set [_j, _a];
        [_r] call _legsFix;
        [_r] call _changed;
        _j
    };
    case "stepDel": {
        _arg params ["_id", "_i"];
        private _r = _mine getOrDefault [_id, createHashMap];
        if ((count _r) isEqualTo 0) exitWith { false };
        private _st = _r get "steps";
        if (_i < 0 || {_i >= count _st}) exitWith { false };
        [_r] call _snap;
        _st deleteAt _i;
        private _f = missionNamespace getVariable ["COMSPEC_ATAK_RteFollow", createHashMap];
        if ((_f getOrDefault ["id", ""]) isEqualTo _id) then {
            if ((count _st) isEqualTo 0) then { ["unfollow", true] call comspec_atak_native_fnc_routeAction; } else {
                if (_i < (_f get "idx")) then { _f set ["idx", (_f get "idx") - 1]; };
                _f set ["idx", ((_f get "idx") min ((count _st) - 1)) max 0];
            };
        };
        [_r] call _legsFix;
        [_r] call _changed;
        true
    };
    case "reverse": {
        private _r = _mine getOrDefault [_arg, createHashMap];
        if ((count _r) isEqualTo 0 || {(count (_r get "steps")) < 2}) exitWith { false };
        [_r] call _snap;
        reverse (_r get "steps");
        private _legs = _r get "legs";
        if ((count _legs) > 0) then { reverse _legs; { reverse _x; } forEach _legs; };
        // Départ et arrivée échangés : leurs types suivent.
        private _st = _r get "steps";
        if (((_st select 0) select 1) isEqualTo "END" && {((_st select -1) select 1) isEqualTo "SP"}) then {
            (_st select 0) set [1, "SP"]; (_st select -1) set [1, "END"];
            private _n0 = (_st select 0) select 2; (_st select 0) set [2, (_st select -1) select 2]; (_st select -1) set [2, _n0];
        };
        ["INFO", "Route inversée", 2, 10] call comspec_atak_native_fnc_notify;
        [_r] call _changed;
        true
    };
    // Itinéraire de l'app Itinéraire (points de passage) repris en nouvelle route.
    case "import": {
        private _pts = (missionNamespace getVariable ["COMSPEC_ATAK_Waypoints", createHashMap]) getOrDefault ["pts", []];
        if ((count _pts) isEqualTo 0) exitWith { ["INFO", "L'app Itinéraire n'a aucune étape", 3, 10] call comspec_atak_native_fnc_notify; "" };
        private _id = ["new", ["FOOT", ("Itinéraire importé") call _tr]] call comspec_atak_native_fnc_routeAction;
        if (_id isEqualTo "") exitWith { "" };
        private _r = _mine get _id;
        private _map = createHashMapFromArray [["WP", "WP"], ["SP", "SP"], ["CP", "CP"], ["RP", "RP"], ["OBJ", "OBJ"], ["LZ", "LZ"], ["END", "END"]];
        { _x params ["_p", "_n", ["_k", "WP"]]; (_r get "steps") pushBack [[_p select 0, _p select 1, 0], _map getOrDefault [_k, "WP"], [_n, 24] call _clean, "", -1, ""]; } forEach (_pts select [0, _maxSteps]);
        [_r] call _changed;
        ["SUCCESS", format ["%1 étape(s) reprises de l'app Itinéraire", count (_r get "steps")], 3, 10] call comspec_atak_native_fnc_notify;
        _id
    };
    // Distances (m) et caps par tronçon, distance cumulée, totale, durée estimée (s), vitesse (m/s).
    case "stats": {
        private _r = if (_arg isEqualType "") then { [_arg] call _get } else { _arg };
        if ((count _r) isEqualTo 0) exitWith { createHashMap };
        private _st = _r get "steps";
        private _legs = _r get "legs";
        private _ld = []; private _lb = []; private _cum = [0];
        for "_i" from 1 to ((count _st) - 1) do {
            private _a = (_st select (_i - 1)) select 0; private _b = (_st select _i) select 0;
            private _path = _legs param [_i - 1, []];
            private _d = 0;
            if ((count _path) >= 2) then { for "_k" from 1 to ((count _path) - 1) do { _d = _d + ((_path select (_k - 1)) distance2D (_path select _k)); }; } else { _d = _a distance2D _b; };
            _ld pushBack _d; _lb pushBack (_a getDir _b);
            _cum pushBack ((_cum select (_i - 1)) + _d);
        };
        private _v = ((_r get "speed") / 3.6) max 0.3;
        private _tot = _cum select ((count _cum) - 1);
        createHashMapFromArray [["dist", _ld], ["brg", _lb], ["cum", _cum], ["total", _tot], ["dur", _tot / _v], ["v", _v]]
    };
    // Tracé de la route pour le dessin : [révision, points, boîte [x0, y0, x1, y1], distances et caps des tronçons], gardé
    // jusqu'à la révision suivante.
    case "geo": {
        private _r = _arg;
        private _cache = uiNamespace getVariable ["COMSPEC_ATAK_RteGeo", createHashMap];
        uiNamespace setVariable ["COMSPEC_ATAK_RteGeo", _cache];
        private _key = format ["%1|%2", _r get "id", _r get "rev"];
        private _g = _cache getOrDefault [_r get "id", []];
        if ((_g param [0, ""]) isEqualTo _key) exitWith { _g };
        private _st = _r get "steps";
        private _legs = _r get "legs";
        private _pts = [];
        {
            private _p = _x select 0;
            if (_forEachIndex > 0) then {
                private _path = _legs param [_forEachIndex - 1, []];
                if ((count _path) >= 2) then { { _pts pushBack [_x select 0, _x select 1, 0]; } forEach (_path select [1, (count _path) - 2]); };
            };
            _pts pushBack [_p select 0, _p select 1, 0];
        } forEach _st;
        private _x0 = 1e9; private _y0 = 1e9; private _x1 = -1e9; private _y1 = -1e9;
        { _x0 = _x0 min (_x select 0); _y0 = _y0 min (_x select 1); _x1 = _x1 max (_x select 0); _y1 = _y1 max (_x select 1); } forEach _pts;
        // Distances et caps des tronçons, pour les étiquettes de la carte.
        private _s = ["stats", _r] call comspec_atak_native_fnc_routeAction;
        _g = [_key, _pts, [_x0, _y0, _x1, _y1], _s getOrDefault ["dist", []], _s getOrDefault ["brg", []]];
        _cache set [_r get "id", _g];
        _g
    };
    // Véhicule « suivre les routes » : chaque tronçon calculé sur le réseau routier (fn_roadPath, en arrière-plan).
    case "roads": {
        private _r = _mine getOrDefault [_arg, createHashMap];
        if ((count _r) isEqualTo 0) exitWith {};
        private _gen = (_r getOrDefault ["roadGen", 0]) + 1;
        _r set ["roadGen", _gen];
        _r set ["busy", true];
        [_arg, _gen, +(_r get "steps"), +(_r getOrDefault ["legKeep", []]), +(_r getOrDefault ["legFrom", []])] spawn {
            params ["_id", "_gen", "_steps", "_old", "_oldFrom"];
            // Petite attente : plusieurs étapes posées d'affilée ne lancent qu'un calcul.
            sleep 0.6;
            private _r = ["get", _id] call comspec_atak_native_fnc_routeAction;
            if ((count _r) isEqualTo 0 || {(_r getOrDefault ["roadGen", 0]) isNotEqualTo _gen}) exitWith {};
            private _legs = [];
            private _from = [];
            private _fail = 0;
            for "_i" from 1 to ((count _steps) - 1) do {
                private _a = (_steps select (_i - 1)) select 0; private _b = (_steps select _i) select 0;
                // Tronçon inchangé (mêmes bouts) : repris du calcul précédent.
                private _k = _oldFrom find [_a, _b];
                private _path = if (_k >= 0) then { _old param [_k, []] } else { [] };
                if ((count _path) < 2) then {
                    if ((_a distance2D _b) < 120) then { _path = [_a, _b]; } else {
                        ([[_a, _b], createHashMapFromArray [["mode", "FAST"], ["snap", 200], ["snapFar", 700], ["keepEnds", true], ["maxNodes", 30000]]] call comspec_atak_native_fnc_roadPath) params [["_p", []], ["_s", "DIRECT"]];
                        _path = _p apply { [_x select 0, _x select 1, 0] };
                        if (_s isEqualTo "DIRECT") then { _fail = _fail + 1; };
                    };
                };
                if ((count _path) < 2) then { _path = [_a, _b]; };
                _legs pushBack _path;
                _from pushBack [_a, _b];
                if (((["get", _id] call comspec_atak_native_fnc_routeAction) getOrDefault ["roadGen", 0]) isNotEqualTo _gen) exitWith {};
            };
            _r = ["get", _id] call comspec_atak_native_fnc_routeAction;
            if ((count _r) isEqualTo 0 || {(_r getOrDefault ["roadGen", 0]) isNotEqualTo _gen}) exitWith {};
            _r set ["legs", _legs];
            _r set ["legKeep", _legs];
            _r set ["legFrom", _from];
            _r set ["busy", false];
            if (_fail > 0) then { ["INFO", format ["%1 tronçon(s) sans route : ligne droite", _fail], 3, 20] call comspec_atak_native_fnc_notify; };
            ["touch", _id] call comspec_atak_native_fnc_routeAction;
        };
    };
    case "touch": {
        private _r = [_arg] call _get;
        if ((count _r) > 0) then { [_r] call _changed; };
    };
    // Partage : [id, portée 1 équipe | 2 groupe | 3 camp, silencieux]. Part par le réseau du téléphone ; le serveur la
    // garde pour les joueurs qui arrivent ensuite.
    case "share": {
        _arg params ["_id", ["_sc", 2], ["_quiet", false]];
        private _r = _mine getOrDefault [_id, createHashMap];
        if ((count _r) isEqualTo 0) exitWith { false };
        if !(missionNamespace getVariable ["comspec_atak_native_route_share", true]) exitWith { ["WARNING", "Partage des routes désactivé par le serveur", 3, 20] call comspec_atak_native_fnc_notify; false };
        if ((count (_r get "steps")) < 1) exitWith { if !(_quiet) then { ["INFO", "Route sans étape : rien à envoyer", 3, 10] call comspec_atak_native_fnc_notify; }; false };
        // Route repartagée après un retrait : nouvelle révision, sinon la pierre tombale des destinataires l'ignorerait.
        if ((_r get "scope") isEqualTo 0) then { _r set ["rev", (_r getOrDefault ["rev", 0]) + 1]; };
        // Portée réduite : retirée d'abord chez tous ceux de l'ancienne portée.
        if ((_r get "scope") > _sc) then { ["unshare", _id] call comspec_atak_native_fnc_routeAction; };
        _r set ["scope", _sc];
        _r set ["live", true];
        call _save;
        private _to = [_sc] call _targets;
        private _pk = ["pack", _r] call comspec_atak_native_fnc_routeAction;
        private _who = [player] call comspec_atak_native_fnc_unitCallsign;
        private _nPts = 0; { _nPts = _nPts + count _x; } forEach (_r get "legs");
        private _st = [{
            params ["_pk", "_to", "_who", "_sc", "_id"];
            if ((count _to) > 0) then { ["comspec_atak_native_route", ["set", _pk, _who, _sc], _to] call CBA_fnc_targetEvent; };
            ["comspec_atak_native_routeSrv", ["set", _id, _pk, _sc, str side group player, netId group player, player getVariable ["COMSPEC_FT", ""], _who]] call CBA_fnc_serverEvent;
        }, [_pk, _to, _who, _sc, _id], "Route", 1 + (count (_r get "steps")) * 0.15 + _nPts * 0.02, 1, "rte:" + _id] call comspec_atak_native_fnc_netSend;
        if !(_quiet) then {
            switch (true) do {
                case (_st isEqualTo "QUEUED"): { ["WARNING", "Pas de réseau : la route partira au retour du signal", 4, 20] call comspec_atak_native_fnc_notify; };
                case ((count _to) isEqualTo 0): { ["INFO", format ["Route partagée (%1) : personne d'autre pour l'instant, elle sera remise aux arrivants", ["scopeName", _sc] call comspec_atak_native_fnc_routeAction], 4, 20] call comspec_atak_native_fnc_notify; };
                default { ["SUCCESS", format ["Route envoyée à %1 joueur(s) (%2)", count _to, ["scopeName", _sc] call comspec_atak_native_fnc_routeAction], 3, 20] call comspec_atak_native_fnc_notify; };
            };
        };
        call _refresh;
        true
    };
    case "unshare": {
        private _r = _mine getOrDefault [_arg, createHashMap];
        if ((count _r) isEqualTo 0) exitWith { false };
        private _to = [_r get "scope"] call _targets;
        // La révision part avec le retrait : pierre tombale posée même chez qui n'a pas encore reçu la route (file hors réseau).
        [{
            params ["_id", "_to", "_rev"];
            if ((count _to) > 0) then { ["comspec_atak_native_route", ["del", _id, [player] call comspec_atak_native_fnc_unitCallsign, _rev], _to] call CBA_fnc_targetEvent; };
            ["comspec_atak_native_routeSrv", ["del", _id]] call CBA_fnc_serverEvent;
        }, [_arg, _to, _r getOrDefault ["rev", 0]], "Route", 1, 1, "rte:" + _arg] call comspec_atak_native_fnc_netSend;
        _r set ["scope", 0];
        _r set ["live", false];
        call _save;
        call _refresh;
        true
    };
    // Réception (événement comspec_atak_native_route) : [op "set" | "del", route (paires) | id, auteur, portée].
    case "recv": {
        _arg params [["_op", ""], ["_pk", []], ["_who", ""], ["_sc", 2]];
        private _quiet = missionNamespace getVariable ["COMSPEC_ATAK_Replaying", false];
        if (_op isEqualTo "del") exitWith {
            if !(_pk isEqualType "") exitWith {};
            // Pierre tombale : une version plus ancienne (rejeu, file sans réseau) ne la fait pas revenir, même arrivée après.
            private _gone = missionNamespace getVariable ["COMSPEC_ATAK_RteGone", createHashMap];
            missionNamespace setVariable ["COMSPEC_ATAK_RteGone", _gone];
            _gone set [_pk, ((_rx getOrDefault [_pk, createHashMap]) getOrDefault ["rev", 0]) max ([0, _sc] select (_sc isEqualType 0)) max (_gone getOrDefault [_pk, -1])];
            if !(_pk in _rx) exitWith {};
            private _n = (_rx get _pk) get "name";
            _rx deleteAt _pk;
            if (((missionNamespace getVariable ["COMSPEC_ATAK_RteFollow", createHashMap]) getOrDefault ["id", ""]) isEqualTo _pk) then { ["unfollow", true] call comspec_atak_native_fnc_routeAction; };
            if !(_quiet) then { ["INFO", format ["Route retirée par %1 : %2", _who, _n], 3, 20] call comspec_atak_native_fnc_notify; };
            call _refresh;
        };
        private _r = [_pk] call comspec_atak_native_fnc_routeUnpack;
        if ((count _r) isEqualTo 0) exitWith {};
        // Ma propre route rejouée par le serveur : déjà dans mon profil.
        if ((_r get "uid") isEqualTo getPlayerUID player || {(_r get "id") in _mine}) exitWith {};
        private _id = _r get "id";
        private _old = _rx getOrDefault [_id, createHashMap];
        // Version plus ancienne que celle déjà reçue, ou route retirée depuis (pierre tombale) : ignorée.
        private _goneRev = (missionNamespace getVariable ["COMSPEC_ATAK_RteGone", createHashMap]) getOrDefault [_id, -1];
        if ((_r get "rev") <= _goneRev) exitWith { ["INFO", "ROUTE", format ["Route %1 (révision %2) ignorée : retirée depuis", _id, _r get "rev"]] call comspec_atak_native_fnc_log; };
        if ((count _old) > 0 && {(_r get "rev") < (_old getOrDefault ["rev", 0])}) exitWith { ["INFO", "ROUTE", format ["Route %1 : révision %2 plus ancienne que %3, ignorée", _id, _r get "rev", _old get "rev"]] call comspec_atak_native_fnc_log; };
        (missionNamespace getVariable ["COMSPEC_ATAK_RteGone", createHashMap]) deleteAt _id;
        _r set ["from", [_who, _r get "owner"] select (_who isEqualTo "")];
        _r set ["scope", (_sc max 1) min 3];
        _r set ["arma", false];
        // Réglages d'affichage de celui qui reçoit gardés d'une version à l'autre.
        if ((count _old) > 0) then { _r set ["vis", _old get "vis"]; _r set ["d3", _old get "d3"]; } else { _r set ["vis", true]; _r set ["d3", false]; };
        _rx set [_id, _r];
        if !(_quiet) then {
            if ((count _old) isEqualTo 0) then {
                ["MESSAGE", format ["Route reçue de %1 (%2) : %3, %4 étape(s)", _r get "from", ["scopeName", _r get "scope"] call comspec_atak_native_fnc_routeAction, _r get "name", count (_r get "steps")], 6, 50] call comspec_atak_native_fnc_notify;
                [] call comspec_atak_native_fnc_vibrate;
            } else {
                private _msg = format ["Route mise à jour par %1 : %2", _r get "from", _r get "name"];
                if !(([_msg, 2] call comspec_atak_native_fnc_toast)) then { ["INFO", _msg, 2, 10] call comspec_atak_native_fnc_notify; };
            };
        };
        call _refresh;
    };
    // Carte d'Arma et Athena : polyligne de la route et repère de chaque étape, sur le canal du groupe (du camp si la
    // route est partagée au camp). Refait en entier à chaque modification (regroupée, fn_routeAction _changed).
    case "armaNow": {
        private _r = _mine getOrDefault [_arg, createHashMap];
        if ((count _r) isEqualTo 0) exitWith {};
        { [_x, true] call comspec_atak_native_fnc_markerWeb; deleteMarker _x; } forEach (_r getOrDefault ["mk", []]);
        _r set ["mk", []];
        if (!(_r get "arma") || {!(missionNamespace getVariable ["comspec_atak_native_route_arma", true])} || {(count (_r get "steps")) isEqualTo 0}) exitWith {};
        private _ch = [3, 1] select ((_r get "scope") >= 3);
        private _mk = [];
        private _col = _r get "color";
        private _mkName = {
            private _index = (missionNamespace getVariable ["COMSPEC_ATAK_MarkerIndex", 0]) + 1;
            missionNamespace setVariable ["COMSPEC_ATAK_MarkerIndex", _index];
            format ["_USER_DEFINED #%1/%2/%3", clientOwner, 9000 + _index, _ch]
        };
        // Overwatch (EH MarkerCreated) enverrait un point vide avant la polyligne : muet le temps de poser les marqueurs.
        missionNamespace setVariable ["COMSPEC_MarkerEhMuted", (missionNamespace getVariable ["COMSPEC_MarkerEhMuted", 0]) + 1];
        private _pts = (["geo", _r] call comspec_atak_native_fnc_routeAction) select 1;
        if ((count _pts) >= 2) then {
            // 200 points au plus (marqueur polyligne d'Arma et envoi au site) : tracé allégé régulièrement.
            private _stp = ceil ((count _pts) / 200);
            private _flat = [];
            for "_i" from 0 to ((count _pts) - 1) step _stp do { _flat append [(_pts select _i) select 0, (_pts select _i) select 1]; };
            if ((((count _pts) - 1) mod _stp) isNotEqualTo 0) then { _flat append [(_pts select -1) select 0, (_pts select -1) select 1]; };
            private _m = ["create", [call _mkName, _pts select 0, _ch]] call comspec_atak_native_fnc_markerNet;
            if (_m isNotEqualTo "") then {
                _m setMarkerShapeLocal "POLYLINE";
                _m setMarkerPolylineLocal _flat;
                _m setMarkerTextLocal (_r get "name");
                _m setMarkerColor _col;
                _mk pushBack _m;
            };
        };
        {
            _x params ["_p", "_k", "_n"];
            private _m = ["create", [call _mkName, _p, _ch]] call comspec_atak_native_fnc_markerNet;
            if (_m isNotEqualTo "") then {
                _m setMarkerTypeLocal (([_k] call _typeRow) select 2);
                _m setMarkerSizeLocal [0.75, 0.75];
                _m setMarkerTextLocal format ["%1 · %2", _n, _r get "name"];
                _m setMarkerColor _col;
                _mk pushBack _m;
            };
        } forEach (_r get "steps");
        missionNamespace setVariable ["COMSPEC_MarkerEhMuted", ((missionNamespace getVariable ["COMSPEC_MarkerEhMuted", 1]) - 1) max 0];
        if ((count _mk) isEqualTo 0) exitWith { ["WARNING", "Marqueurs refusés sur ce canal", 3, 20] call comspec_atak_native_fnc_notify; };
        _r set ["mk", _mk];
        { [_x] call comspec_atak_native_fnc_markerWeb; } forEach _mk;
    };
    // Édition (vue EDIT de l'app) : [id, nouvelle route]. Une autre route en cours d'édition est d'abord validée.
    case "editBegin": {
        _arg params [["_id", ""], ["_isNew", false]];
        if !(_id in _mine) exitWith { false };
        if ([_id] call _editing) exitWith { if (_isNew) then { _ed set ["isNew", true]; }; true };
        if ((count _ed) > 0) then { ["editCommit"] call comspec_atak_native_fnc_routeAction; };
        _ed = createHashMapFromArray [["id", _id], ["orig", +(["pack", _mine get _id] call comspec_atak_native_fnc_routeAction)], ["undo", []], ["redo", []], ["isNew", _isNew], ["dirty", false]];
        missionNamespace setVariable ["COMSPEC_ATAK_RteEdit", _ed];
        true
    };
    case "editInfo": { _ed };
    // Validation : fin de l'édition, puis un seul envoi (partage en direct, carte d'Arma et Athena) si la route a changé.
    case "editCommit": {
        if ((count _ed) isEqualTo 0) exitWith { false };
        private _id = _ed get "id";
        private _dirty = _ed get "dirty";
        _ed = createHashMap;
        missionNamespace setVariable ["COMSPEC_ATAK_RteEdit", _ed];
        // Validation suivie d'un partage (argument true) : le partage envoie la route, seuls les marqueurs d'Arma suivent.
        if (_arg isEqualTo true) exitWith {
            if (_dirty && {_id in _mine} && {((_mine get _id) get "arma") || {(count ((_mine get _id) getOrDefault ["mk", []])) > 0}}) then { ["armaNow", _id] call comspec_atak_native_fnc_routeAction; };
            _dirty
        };
        if (_dirty && {_id in _mine}) then { [_mine get _id, false] call _changed; } else { call _refresh; };
        _dirty
    };
    // Annuler les modifications : route remise comme à l'ouverture (nouvelle route : brouillon abandonné). Rien n'était
    // parti au réseau : rien à renvoyer.
    case "editRevert": {
        if ((count _ed) isEqualTo 0) exitWith { false };
        private _id = _ed get "id";
        private _orig = _ed get "orig";
        private _isNew = _ed get "isNew";
        _ed = createHashMap;
        missionNamespace setVariable ["COMSPEC_ATAK_RteEdit", _ed];
        private _r = _mine getOrDefault [_id, createHashMap];
        if ((count _r) isEqualTo 0) exitWith { false };
        if (_isNew && {(_r get "scope") isEqualTo 0}) exitWith {
            { [_x, true] call comspec_atak_native_fnc_markerWeb; deleteMarker _x; } forEach (_r getOrDefault ["mk", []]);
            _mine deleteAt _id;
            if (((missionNamespace getVariable ["COMSPEC_ATAK_RteFollow", createHashMap]) getOrDefault ["id", ""]) isEqualTo _id) then { ["unfollow", true] call comspec_atak_native_fnc_routeAction; };
            call _save; call _refresh;
            true
        };
        [_r, _orig] call _restore;
        // Marqueurs d'Arma reposés pendant l'édition (interrupteur) : refaits sur l'état d'origine.
        if ((_r get "arma") || {(count (_r getOrDefault ["mk", []])) > 0}) then { ["armaNow", _id] call comspec_atak_native_fnc_routeAction; };
        call _save; call _refresh;
        true
    };
    // Annuler / rétablir pendant l'édition (Ctrl+Z / Ctrl+Y) : true si quelque chose a changé.
    case "undo";
    case "redo": {
        if ((count _ed) isEqualTo 0) exitWith { false };
        private _r = _mine getOrDefault [_ed get "id", createHashMap];
        if ((count _r) isEqualTo 0) exitWith { false };
        private _from = _ed get (["redo", "undo"] select (_act isEqualTo "undo"));
        private _to = _ed get (["undo", "redo"] select (_act isEqualTo "undo"));
        if ((count _from) isEqualTo 0) exitWith { false };
        _to pushBack +(["pack", _r] call comspec_atak_native_fnc_routeAction);
        [_r, _from deleteAt ((count _from) - 1)] call _restore;
        _ed set ["dirty", true];
        call _save; call _refresh;
        true
    };
    // Route supprimée, gardée 8 s (bandeau « Supprimée · ANNULER ») avant d'être retirée partout.
    case "trash": {
        private _r = _mine getOrDefault [_arg, createHashMap];
        if ((count _r) isEqualTo 0) exitWith { ["del", _arg] call comspec_atak_native_fnc_routeAction };
        if ([_arg] call _editing) then { _ed = createHashMap; missionNamespace setVariable ["COMSPEC_ATAK_RteEdit", _ed]; };
        private _bin = missionNamespace getVariable ["COMSPEC_ATAK_RteTrash", createHashMap];
        missionNamespace setVariable ["COMSPEC_ATAK_RteTrash", _bin];
        private _tok = diag_tickTime;
        // Retirée tout de suite partout (destinataires, serveur, carte d'Arma, Athena) : quitter le jeu pendant le délai
        // d'annulation ne la laisse pas revivre ailleurs. Annuler la repartage à la même portée.
        private _sc0 = _r get "scope";
        { [_x, true] call comspec_atak_native_fnc_markerWeb; deleteMarker _x; } forEach (_r getOrDefault ["mk", []]);
        _r set ["mk", []];
        if (_sc0 > 0) then { ["unshare", _arg] call comspec_atak_native_fnc_routeAction; };
        _bin set [_arg, [_r, _tok, _sc0]];
        _mine deleteAt _arg;
        call _save; call _refresh;
        [{
            params ["_id", "_tok"];
            private _bin = missionNamespace getVariable ["COMSPEC_ATAK_RteTrash", createHashMap];
            private _e = _bin getOrDefault [_id, []];
            if ((_e param [1, -1]) isNotEqualTo _tok) exitWith {};
            _bin deleteAt _id;
            (missionNamespace getVariable ["COMSPEC_ATAK_RteMine", createHashMap]) set [_id, _e select 0];
            ["del", _id] call comspec_atak_native_fnc_routeAction;
        }, [_arg, _tok], 8] call comspec_atak_native_fnc_waitAndExec;
        true
    };
    case "untrash": {
        private _bin = missionNamespace getVariable ["COMSPEC_ATAK_RteTrash", createHashMap];
        private _e = _bin getOrDefault [_arg, []];
        if ((count _e) isEqualTo 0) exitWith { false };
        _bin deleteAt _arg;
        _mine set [_arg, _e select 0];
        if ((_e param [2, 0]) > 0) then { ["share", [_arg, _e select 2, true]] call comspec_atak_native_fnc_routeAction; };
        if ((_e select 0) get "arma") then { ["armaNow", _arg] call comspec_atak_native_fnc_routeAction; };
        call _save; call _refresh;
        true
    };
    // Route affichée sous un point de l'écran d'une carte (clic droit de l'app Carte) : [carte, point écran] -> id ou "".
    case "at": {
        _arg params ["_map", "_sp"];
        private _best = ""; private _bd = pixelW * 10;
        {
            if (_x get "vis") then {
                private _r = _x;
                private _pts = (["geo", _r] call comspec_atak_native_fnc_routeAction) select 1;
                private _scr = _pts apply { _map ctrlMapWorldToScreen [_x select 0, _x select 1] };
                for "_i" from 1 to ((count _scr) - 1) do {
                    private _a = _scr select (_i - 1); private _c = _scr select _i;
                    private _ab = [(_c select 0) - (_a select 0), (_c select 1) - (_a select 1)];
                    private _l2 = ((_ab select 0) ^ 2) + ((_ab select 1) ^ 2);
                    private _t = if (_l2 > 0) then { ((((_sp select 0) - (_a select 0)) * (_ab select 0) + ((_sp select 1) - (_a select 1)) * (_ab select 1)) / _l2) max 0 min 1 } else { 0 };
                    private _d = [(_a select 0) + (_ab select 0) * _t, (_a select 1) + (_ab select 1) * _t] distance2D _sp;
                    if (_d < _bd) then { _bd = _d; _best = _r get "id"; };
                };
                { if ((_x distance2D _sp) < _bd) then { _bd = _x distance2D _sp; _best = _r get "id"; }; } forEach _scr;
            };
        } forEach ((values _mine) + (values _rx));
        _best
    };
    // Itinéraires hors de l'app Routes, montrés dans son onglet ITINÉRAIRES : [[genre WP | GPS, nom, détail]...].
    //   WP  : étapes de l'app Itinéraire (partagées au groupe ou au camp selon son réglage) ;
    //   GPS : itinéraire du guidage GPS « Y aller » (ligne jaune de la carte).
    case "itins": {
        private _o = [];
        private _wp = (missionNamespace getVariable ["COMSPEC_ATAK_Waypoints", createHashMap]) getOrDefault ["pts", []];
        if ((count _wp) > 0) then { _o pushBack ["WP", ("Itinéraire (app Itinéraire)") call _tr, format [("%1 étape(s)") call _tr, count _wp]]; };
        private _g = missionNamespace getVariable ["COMSPEC_ATAK_Route", createHashMap];
        if ((count _g) > 0) then {
            private _d = _g getOrDefault ["total", 0];
            _o pushBack ["GPS", format [("Guidage GPS vers %1") call _tr, _g getOrDefault ["label", ""]], [format ["%1 m", round _d], format ["%1 km", (_d / 1000) toFixed 1]] select (_d >= 1000)];
        };
        _o
    };
    // Itinéraire effacé [genre], gardé 8 s pour ANNULER (« itinUndo ») : l'app Itinéraire partagée renvoie son état.
    case "itinDel": {
        private _bin = missionNamespace getVariable ["COMSPEC_ATAK_ItinBin", createHashMap];
        missionNamespace setVariable ["COMSPEC_ATAK_ItinBin", _bin];
        switch (_arg) do {
            case "WP": {
                private _w = missionNamespace getVariable ["COMSPEC_ATAK_Waypoints", createHashMap];
                if ((count (_w getOrDefault ["pts", []])) isEqualTo 0) exitWith {};
                _bin set ["WP", [+(_w get "pts"), _w getOrDefault ["idx", 0], diag_tickTime]];
                ["clear"] call comspec_atak_native_fnc_wpAction;
            };
            case "GPS": {
                private _g = missionNamespace getVariable ["COMSPEC_ATAK_Route", createHashMap];
                if ((count _g) isEqualTo 0) exitWith {};
                _bin set ["GPS", [_g, 0, diag_tickTime]];
                missionNamespace setVariable ["COMSPEC_ATAK_RoutePending", nil];
                missionNamespace setVariable ["COMSPEC_ATAK_RouteGen", (missionNamespace getVariable ["COMSPEC_ATAK_RouteGen", 0]) + 1];
                missionNamespace setVariable ["COMSPEC_ATAK_Route", createHashMap];
            };
        };
        call _refresh;
        true
    };
    case "itinUndo": {
        private _bin = missionNamespace getVariable ["COMSPEC_ATAK_ItinBin", createHashMap];
        private _e = _bin getOrDefault [_arg, []];
        if ((count _e) isEqualTo 0 || {(diag_tickTime - (_e select 2)) > 30}) exitWith { false };
        _bin deleteAt _arg;
        if (_arg isEqualTo "WP") then {
            private _w = missionNamespace getVariable ["COMSPEC_ATAK_Waypoints", createHashMap];
            _w set ["pts", _e select 0]; _w set ["idx", _e select 1];
            missionNamespace setVariable ["COMSPEC_ATAK_Waypoints", _w];
            if ((profileNamespace getVariable ["COMSPEC_ATAK_WpShare", 1]) > 0) then { ["sync"] call comspec_atak_native_fnc_wpAction; };
        } else {
            missionNamespace setVariable ["COMSPEC_ATAK_Route", _e select 0];
        };
        call _refresh;
        true
    };
    // Guidage « Suivre la route » : [id, étape de départ (facultative)].
    case "follow": {
        _arg params [["_id", ""], ["_from", -1]];
        private _r = [_id] call _get;
        if ((count _r) isEqualTo 0 || {(count (_r get "steps")) isEqualTo 0}) exitWith { false };
        private _st = _r get "steps";
        // Départ : l'étape demandée, sinon la plus proche parmi les premières (on ne repart pas du début en cours de route).
        private _idx = _from;
        if (_idx < 0) then {
            _idx = 0;
            private _best = 1e9;
            { private _d = player distance2D (_x select 0); if (_d < _best) then { _best = _d; _idx = _forEachIndex; }; } forEach _st;
            // L'étape la plus proche déjà dépassée (je suis plus près de la suivante que de celle-ci depuis la précédente) : la suivante.
            if (_idx < ((count _st) - 1) && {_idx > 0} && {(player distance2D ((_st select (_idx + 1)) select 0)) < (((_st select _idx) select 0) distance2D ((_st select (_idx + 1)) select 0))}) then { _idx = _idx + 1; };
            if ((player distance2D ((_st select 0) select 0)) < 400) then { _idx = 0; };
        };
        _idx = (_idx max 0) min ((count _st) - 1);
        missionNamespace setVariable ["COMSPEC_ATAK_RteFollow", createHashMapFromArray [["id", _id], ["idx", _idx], ["t0", time], ["d0", 0]]];
        (_st select _idx) params ["_p", "", "_n"];
        ["SUCCESS", format ["Suivi de %1 : cap sur %2 (%3 / %4)", _r get "name", _n, _idx + 1, count _st], 3, 20] call comspec_atak_native_fnc_notify;
        // Montre Garmin : boussole vers l'étape visée.
        ["send", [_p, _n]] call comspec_atak_native_fnc_watch;
        ["tick"] call comspec_atak_native_fnc_routeAction;
        call _refresh;
        true
    };
    case "unfollow": {
        private _f = missionNamespace getVariable ["COMSPEC_ATAK_RteFollow", createHashMap];
        if ((count _f) isEqualTo 0) exitWith {};
        missionNamespace setVariable ["COMSPEC_ATAK_RteFollow", createHashMap];
        [] call comspec_atak_native_fnc_route3d;
        private _wm = missionNamespace getVariable ["COMSPEC_ATAK_WatchM", createHashMap];
        if ((count _wm) > 0) then { _wm set ["wpt", []]; };
        if !(_arg isEqualTo true) then { ["INFO", "Suivi de route arrêté", 2, 10] call comspec_atak_native_fnc_notify; };
        call _refresh;
    };
    case "next";
    case "prev": {
        private _f = missionNamespace getVariable ["COMSPEC_ATAK_RteFollow", createHashMap];
        if ((count _f) isEqualTo 0) exitWith {};
        private _r = [_f get "id"] call _get;
        if ((count _r) isEqualTo 0) exitWith {};
        private _st = _r get "steps";
        _f set ["idx", (((_f get "idx") + ([-1, 1] select (_act isEqualTo "next"))) max 0) min ((count _st) - 1)];
        (_st select (_f get "idx")) params ["_p", "", "_n"];
        private _wm = missionNamespace getVariable ["COMSPEC_ATAK_WatchM", createHashMap];
        if ((count _wm) > 0) then { _wm set ["wpt", [_p, _n]]; };
        call _refresh;
    };
    // État du guidage pour l'affichage : route, étape visée, distance, cap, reste, durées, progression.
    case "followInfo": {
        private _f = missionNamespace getVariable ["COMSPEC_ATAK_RteFollow", createHashMap];
        if ((count _f) isEqualTo 0) exitWith { createHashMap };
        private _r = [_f get "id"] call _get;
        if ((count _r) isEqualTo 0) exitWith { createHashMap };
        private _st = _r get "steps";
        private _i = ((_f get "idx") min ((count _st) - 1)) max 0;
        private _s = ["stats", _r] call comspec_atak_native_fnc_routeAction;
        private _me = getPosATL vehicle player;
        private _p = (_st select _i) select 0;
        private _dn = _me distance2D _p;
        private _rest = _dn;
        { _rest = _rest + _x; } forEach ((_s get "dist") select [_i]);
        private _tot = (_s get "total") max 1;
        // Allure : réelle si l'on avance assez vite, sinon celle de la route.
        private _v = vectorMagnitude velocity vehicle player;
        private _vr = _s get "v";
        private _vu = [_vr, (_v min (_vr * 3))] select (_v > _vr * 0.4);
        createHashMapFromArray [
            ["route", _r], ["idx", _i], ["step", _st select _i], ["dist", _dn], ["brg", _me getDir _p], ["rest", _rest],
            ["etaNext", _dn / (_vu max 0.3)], ["etaEnd", _rest / (_vu max 0.3)], ["prog", ((1 - _rest / _tot) max 0) min 1], ["n", count _st], ["stats", _s]
        ]
    };
    // Une fois par seconde (XEH_postInitClient) : arrivée aux étapes, fin de route, vue 3D.
    case "tick": {
        [] call comspec_atak_native_fnc_route3d;
        // Édition laissée ouverte (autre app, téléphone reposé) : validée, pour que la route parte quand même.
        if ((count _ed) > 0) then {
            private _sx = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
            if ((_sx getOrDefault ["activePage", ""]) isNotEqualTo "ROUTE" || {!(_sx getOrDefault ["interactive", false])}) then { ["editCommit"] call comspec_atak_native_fnc_routeAction; };
        };
        // Même règle pour le plan de l'app Zones en cours d'édition.
        if ((count (missionNamespace getVariable ["COMSPEC_ATAK_ZnEdit", createHashMap])) > 0) then { ["editTick"] call comspec_atak_native_fnc_zoneAction; };
        private _f = missionNamespace getVariable ["COMSPEC_ATAK_RteFollow", createHashMap];
        if ((count _f) isEqualTo 0) exitWith {};
        private _r = [_f get "id"] call _get;
        if ((count _r) isEqualTo 0) exitWith { ["unfollow"] call comspec_atak_native_fnc_routeAction; };
        private _st = _r get "steps";
        if ((count _st) isEqualTo 0) exitWith { ["unfollow"] call comspec_atak_native_fnc_routeAction; };
        private _i = ((_f get "idx") min ((count _st) - 1)) max 0;
        (_st select _i) params ["_p", "_k", "_n", ["_note", ""], ["_hp", -1], ["_ac", ""]];
        private _rad = ((missionNamespace getVariable ["comspec_atak_native_route_arrive", 25]) max 5) * (([_r get "kind"] call _kindRow) select 4);
        // Page Routes ouverte en vue de suivi : chiffres mis à jour sur place (pas de rendu).
        private _live = uiNamespace getVariable ["COMSPEC_ATAK_RteLive", controlNull];
        if (!isNull _live) then { ["#live"] call comspec_atak_native_fnc_pageRoute; };
        if ((player distance2D _p) > _rad) exitWith {};
        // Étape atteinte : consigne et action rappelées, vibreur, étape suivante.
        private _what = ([["actName", _ac] call comspec_atak_native_fnc_routeAction, ""] select (_ac isEqualTo "")) call _tr;
        private _det = ([_what, _note] select { _x isNotEqualTo "" }) joinString " · ";
        [] call comspec_atak_native_fnc_vibrate;
        if (_i >= ((count _st) - 1)) exitWith {
            ["SUCCESS", format ["Route %1 terminée : %2 atteint%3", _r get "name", _n, ["", " · " + _det] select (_det isNotEqualTo "")], 6, 50] call comspec_atak_native_fnc_notify;
            ["unfollow", true] call comspec_atak_native_fnc_routeAction;
        };
        _f set ["idx", _i + 1];
        [] call comspec_atak_native_fnc_route3d;
        (_st select (_i + 1)) params ["_p2", "", "_n2"];
        ["WARNING", format ["%1 atteint%2 · cap sur %3", _n, ["", " · " + _det] select (_det isNotEqualTo ""), _n2], 6, 40] call comspec_atak_native_fnc_notify;
        private _wm = missionNamespace getVariable ["COMSPEC_ATAK_WatchM", createHashMap];
        if ((count _wm) > 0) then { _wm set ["wpt", [_p2, _n2]]; };
        call _refresh;
    };
    // Actions ACE « Infos tactiques JVN » d'Overwatch : le point regardé va dans la route « Itinéraire JVN » (vue 3D).
    case "jvnAdd": {
        private _id = "JVN_" + getPlayerUID player;
        if !(_id in _mine) then {
            ["new", ["FOOT", ("Itinéraire JVN") call _tr, _id]] call comspec_atak_native_fnc_routeAction;
            // Itinéraire personnel (actions ACE) : pas de partage d'office.
            { (_mine get _id) set _x; } forEach [["d3", true], ["scope", 0], ["live", false], ["arma", false]];
        };
        if !(_id in _mine) exitWith { false };
        private _i = ["stepAdd", [_id, _arg]] call comspec_atak_native_fnc_routeAction;
        if (_i < 0) exitWith { false };
        ["INFO", format ["Point d'itinéraire ajouté (%1) : app Routes, « Itinéraire JVN »", _i + 1], 3, 20] call comspec_atak_native_fnc_notify;
        true
    };
    case "jvnClear": {
        private _id = "JVN_" + getPlayerUID player;
        if !(_id in _mine) exitWith { false };
        ["del", _id] call comspec_atak_native_fnc_routeAction;
        ["INFO", "Itinéraire JVN effacé", 2, 10] call comspec_atak_native_fnc_notify;
        true
    };
    case "jvnCount": { count ((_mine getOrDefault ["JVN_" + getPlayerUID player, createHashMap]) getOrDefault ["steps", []]) };
    default { nil };
};
