/*
    App Zones (battleplan) : plans de zones nommées et numérotées, grands axes MSR / ASR et points de contrôle, situation
    par zone (état, signalements), partage par le réseau ATAK (fn_netSend : sans réseau, l'envoi attend en file ; à la
    réception, fn_offline retient ce qui arrive hors réseau), rejeu serveur aux arrivants, carte d'Arma et Athena
    (fn_markerNet / fn_markerWeb).
    Données :
      missionNamespace COMSPEC_ATAK_ZnMine : id -> plan (gardés dans le profil, par carte : COMSPEC_ATAK_Zn_<carte>) ;
      missionNamespace COMSPEC_ATAK_ZnRx   : id -> plan reçu (pour la partie ; rejoués par le serveur à l'arrivée) ;
      missionNamespace COMSPEC_ATAK_ZnRev  : révision de tous les plans (curseur de la carte, caches).
    Plan (HashMap) : id, name (« OBJ BRAVO »), owner, uid, rev, scope (0 moi, 1 équipe, 2 groupe, 3 camp), live, vis,
      arma, render (FILL surligné | LINE contour | POINT point centré), aff (type par défaut des zones), area (zone de
      travail [RECT [[x0, y0], [x1, y1]] | POLY [points] | CIRCLE [[x, y], rayon]]), gp (réglages du découpage, HashMap),
      zones, axes, cps, from (auteur d'un plan reçu), side / grp / ft (destinataires de la situation) ; en mémoire
      seulement : mk (marqueurs d'Arma), genTok / busy (calcul en cours), syncTok, geo (boîtes et surfaces).
    Zone : [id, nom, points [[x, y]...], point d'étiquette [x, y], type, rendu ("" : celui du plan), état, horodatage
      (serverTime), auteur de l'état, signalements, bâtiments [[x, y, demi-largeur, demi-longueur, cap, étages]...],
      rectangle [x, y, a, b] (case de grille) ou []].
    Signalement : [id, horodatage, « HH:MM », auteur, nature, effectif, texte, position [x, y]].
    Axe : [id, nom (« MSR TAMPA »), MSR | ASR, points, longueur (m), rendu]. Point de contrôle : [n, « CP3 », [x, y], axe].
    Params : [action, argument] — voir les « case ».
*/
params [["_act", ""], ["_arg", []]];
private _tr = { _this call comspec_atak_native_fnc_tr };
private _fr = (missionNamespace getVariable ["COMSPEC_ATAK_Lang", "en"]) isEqualTo "fr";
// États : clé, libellé, abrégé français, abrégé anglais (jamais repassés par la traduction), couleur, couleur d'Arma.
private _states = [
    ["UNREC", "Non reconnue", "NR", "UNR", [0.62, 0.66, 0.64, 1], "ColorGrey"],
    ["CLEAR", "Claire", "RAS", "CLR", [0.30, 0.82, 0.38, 1], "ColorGreen"],
    ["CONT", "Contact", "CTC", "TIC", [1, 0.60, 0.12, 1], "ColorOrange"],
    ["ENY", "Ennemi signalé", "ENI", "ENY", [0.95, 0.20, 0.15, 1], "ColorRed"],
    ["FRD", "Tenue par ami", "AMI", "FRD", [0.25, 0.55, 1, 1], "ColorBlue"],
    ["DENY", "Interdite", "INT", "DNY", [0.72, 0.32, 0.92, 1], "ColorCIV"]
];
// Types de zone (symbologie : ami bleu, ennemi rouge, neutre vert, inconnu jaune) : clé, libellé, couleur, couleur d'Arma.
private _types = [
    ["GEN", "Repère", [0.92, 0.93, 0.92, 1], "ColorWhite"],
    ["OBJ", "Objectif", [1, 0.55, 0.10, 1], "ColorOrange"],
    ["FRD", "Ami", [0.25, 0.55, 1, 1], "ColorBLUFOR"],
    ["HOS", "Ennemi", [0.95, 0.20, 0.15, 1], "ColorOPFOR"],
    ["NEU", "Neutre", [0.25, 0.80, 0.35, 1], "ColorGreen"],
    ["UNK", "Inconnu", [1, 0.85, 0.20, 1], "ColorYellow"],
    ["RES", "Restreinte", [0.75, 0.35, 0.95, 1], "ColorCIV"]
];
private _natures = [
    ["UNK", "Inconnue"], ["INF", "Infanterie"], ["MOT", "Motorisé"], ["MECA", "Mécanisé / blindé"], ["REC", "Reconnaissance"],
    ["ART", "Tirs indirects"], ["AA", "Antiaérien"], ["AIR", "Aérien / drone"], ["SNI", "Tireur embusqué"], ["IED", "EEI / piège"],
    ["HQ", "Poste de commandement"], ["CIV", "Civils"]
];
private _renders = [["FILL", "Surligné"], ["LINE", "Contour"], ["POINT", "Point centré"]];
// Noms de code (noms propres, jamais traduits).
private _themes = createHashMapFromArray [
    ["NATO", ["ALPHA", "BRAVO", "CHARLIE", "DELTA", "ECHO", "FOXTROT", "GOLF", "HOTEL", "INDIA", "JULIETT", "KILO", "LIMA", "MIKE", "NOVEMBER", "OSCAR", "PAPA", "QUEBEC", "ROMEO", "SIERRA", "TANGO", "UNIFORM", "VICTOR", "WHISKEY", "XRAY", "YANKEE", "ZULU"]],
    ["CITY", ["TAMPA", "DALLAS", "BOSTON", "DENVER", "ATLANTA", "PHOENIX", "DETROIT", "MEMPHIS", "RALEIGH", "TUCSON", "SEATTLE", "OMAHA", "RENO", "TULSA", "AUSTIN", "DOVER", "MIAMI", "ORLANDO", "SALEM", "TOPEKA"]],
    ["ANIMAL", ["COBRA", "VIPER", "FALCON", "BISON", "JAGUAR", "MAMBA", "PYTHON", "RAVEN", "WOLF", "BADGER", "HAWK", "LYNX", "PUMA", "RHINO", "TIGER", "WOLVERINE"]]
];
private _stateRow = { params ["_k"]; _states select ((_states findIf { (_x select 0) isEqualTo _k }) max 0) };
private _typeRow = { params ["_k"]; _types select ((_types findIf { (_x select 0) isEqualTo _k }) max 0) };
private _clean = { params ["_t", ["_n", 32]]; if !(_t isEqualType "") exitWith { "" }; ((trim _t) regexReplace ["[<>""\\]", ""]) select [0, _n] };
private _mine = missionNamespace getVariable "COMSPEC_ATAK_ZnMine";
private _rx = missionNamespace getVariable ["COMSPEC_ATAK_ZnRx", createHashMap];
if (isNil "_mine") then {
    // Premier appel : plans de cette carte relus du profil (tables posées avant : fn_zoneUnpack rappelle cette fonction).
    _mine = createHashMap;
    missionNamespace setVariable ["COMSPEC_ATAK_ZnMine", _mine];
    missionNamespace setVariable ["COMSPEC_ATAK_ZnRx", _rx];
    {
        private _b = [_x] call comspec_atak_native_fnc_zoneUnpack;
        if ((count _b) > 0) then { _b set ["arma", false]; _b set ["from", ""]; _mine set [_b get "id", _b]; };
    } forEach (profileNamespace getVariable [format ["COMSPEC_ATAK_Zn_%1", worldName], []]);
};
private _get = { params ["_id"]; _mine getOrDefault [_id, _rx getOrDefault [_id, createHashMap]] };
private _zoneOf = { params ["_b", "_zid"]; private _zs = _b getOrDefault ["zones", []]; private _i = _zs findIf { (_x select 0) isEqualTo _zid }; [[], _zs select _i] select (_i >= 0) };
private _bump = { missionNamespace setVariable ["COMSPEC_ATAK_ZnRev", (missionNamespace getVariable ["COMSPEC_ATAK_ZnRev", 0]) + 1]; };
private _refresh = {
    if ((missionNamespace getVariable ["COMSPEC_ATAK_ZnRefreshAt", -1]) isEqualTo diag_frameNo) exitWith {};
    missionNamespace setVariable ["COMSPEC_ATAK_ZnRefreshAt", diag_frameNo];
    [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "ZONES") then { ["#refresh"] call comspec_atak_native_fnc_pageZones; }; }] call CBA_fnc_execNextFrame;
};
private _save = {
    private _tok = (missionNamespace getVariable ["COMSPEC_ATAK_ZnSaveTok", 0]) + 1;
    missionNamespace setVariable ["COMSPEC_ATAK_ZnSaveTok", _tok];
    [{
        if (_this isNotEqualTo (missionNamespace getVariable ["COMSPEC_ATAK_ZnSaveTok", 0])) exitWith {};
        private _m = missionNamespace getVariable ["COMSPEC_ATAK_ZnMine", createHashMap];
        profileNamespace setVariable [format ["COMSPEC_ATAK_Zn_%1", worldName], (values _m) apply { ["pack", _x] call comspec_atak_native_fnc_zoneAction }];
        [] call comspec_atak_native_fnc_saveProfile;
    }, _tok, 2] call CBA_fnc_waitAndExecute;
};
// Plan modifié : révision (géométrie), profil, page, carte d'Arma et partage en direct, regroupés.
private _changed = {
    params ["_b", ["_geo", true], ["_resend", true]];
    if (_geo) then { _b set ["rev", (_b getOrDefault ["rev", 0]) + 1]; _b deleteAt "geo"; };
    call _bump;
    if ((_b get "id") in _mine) then {
        call _save;
        private _id = _b get "id";
        private _tok = (_b getOrDefault ["syncTok", 0]) + 1;
        _b set ["syncTok", _tok];
        [{
            params ["_id", "_tok", "_resend"];
            private _b = ["get", _id] call comspec_atak_native_fnc_zoneAction;
            if ((count _b) isEqualTo 0 || {_tok isNotEqualTo (_b getOrDefault ["syncTok", -1])}) exitWith {};
            if ((_b get "arma") || {(count (_b getOrDefault ["mk", []])) > 0}) then { ["armaNow", _id] call comspec_atak_native_fnc_zoneAction; };
            if (_resend && {_b getOrDefault ["live", false]} && {(_b get "scope") > 0}) then { ["share", [_id, _b get "scope", true]] call comspec_atak_native_fnc_zoneAction; };
        }, [_id, _tok, _resend], 1.5] call CBA_fnc_waitAndExecute;
    };
    call _refresh;
};
// Destinataires d'un plan partagé : camp, groupe ou équipe de son auteur (gardés au partage), et son auteur.
private _aud = {
    params ["_b"];
    private _sc = _b getOrDefault ["scope", 0];
    if (_sc <= 0) exitWith { [] };
    private _side = _b getOrDefault ["side", str side group player];
    private _grp = _b getOrDefault ["grp", netId group player];
    private _ft = _b getOrDefault ["ft", ""];
    private _ou = _b getOrDefault ["uid", ""];
    allPlayers select {
        _x isNotEqualTo player && {(str side group _x) isEqualTo _side} && {
            _sc >= 3 || {(getPlayerUID _x) isEqualTo _ou} || {(netId group _x) isEqualTo _grp && {_sc >= 2 || {_ft isEqualTo "" || {(_x getVariable ["COMSPEC_FT", ""]) isEqualTo _ft}}}}
        }
    }
};
private _usedNames = {
    private _u = [];
    { { _u pushBack ((_x select 1) select [4]); } forEach (_x getOrDefault ["axes", []]); _u pushBack (((_x get "name") splitString " ") param [1, ""]); } forEach ((values _mine) + (values _rx));
    _u
};
// Situation appliquée à une zone : état plus récent seulement ; signalements ajoutés sans doublon (12 au plus).
private _apply = {
    params ["_b", "_zid", "_st", "_stamp", "_by", "_reps"];
    private _z = [_b, _zid] call _zoneOf;
    if ((count _z) isEqualTo 0) exitWith { false };
    private _ch = false;
    if (_st isNotEqualTo "" && {(_states findIf { (_x select 0) isEqualTo _st }) >= 0} && {_stamp >= (_z select 7)}) then {
        if ((_z select 6) isNotEqualTo _st) then { _ch = true; };
        _z set [6, _st]; _z set [7, _stamp]; _z set [8, [_by, 24] call _clean];
    };
    private _have = (_z select 9) apply { _x select 0 };
    {
        if (_x isEqualType [] && {(count _x) >= 8} && {!((_x select 0) in _have)}) then { (_z select 9) pushBack _x; _ch = true; };
    } forEach _reps;
    if ((count (_z select 9)) > 12) then { private _r = (_z select 9) apply { [_x select 1, _x] }; _r sort true; _z set [9, (_r select [(count _r) - 12]) apply { _x select 1 }]; };
    _ch
};

switch (_act) do {
    case "states": { _states };
    case "state": { [_arg] call _stateRow };
    case "stateShort": { ([_arg] call _stateRow) select ([3, 2] select _fr) };
    case "types": { _types };
    case "type": { [_arg] call _typeRow };
    case "natures": { _natures };
    case "natureName": { ((_natures select ((_natures findIf { (_x select 0) isEqualTo _arg }) max 0)) select 1) call _tr };
    case "renders": { _renders };
    case "themes": { _themes };
    case "scopeName": { (["Moi seul", "Équipe", "Groupe", "Camp"] param [_arg, "Groupe"]) call _tr };
    case "mine": { _mine };
    case "rx": { _rx };
    case "get": { [_arg] call _get };
    case "isMine": { _arg in _mine };
    case "zone": { _arg params ["_id", "_zid"]; [[_id] call _get, _zid] call _zoneOf };
    // Plan -> paires (profil, réseau) ; cases de grille envoyées sans leurs coins (refaits d'après le rectangle).
    case "pack": {
        private _b = _arg;
        ((keys _b) select { !(_x in ["mk", "genTok", "busy", "syncTok", "geo"]) }) apply {
            private _v = _b get _x;
            switch (_x) do {
                case "gp": { _v = (keys _v) apply { [_x, _v get _x] }; };
                case "zones": { _v = _v apply { if ((count (_x param [11, []])) isEqualTo 4) then { private _z = +_x; _z set [2, []]; _z } else { _x } }; };
            };
            [_x, _v]
        }
    };
    case "new": {
        _arg params [["_name", ""]];
        if ((count _mine) >= 30) exitWith { ["WARNING", "30 plans de zones au plus : supprimez-en un", 3, 20] call comspec_atak_native_fnc_notify; "" };
        private _id = format ["Z%1_%2_%3", (getPlayerUID player) select [((count getPlayerUID player) - 6) max 0], round (diag_tickTime * 10) mod 1000000, floor random 1000];
        private _used = call _usedNames;
        private _code = ((_themes get "NATO") select { !(_x in _used) }) param [0, "ALPHA"];
        if (_name isEqualTo "") then { _name = "OBJ " + _code; };
        private _gp = createHashMapFromArray [
            ["cut", "GRID"], ["size", 200], ["fmt", "GRG"], ["snap", true], ["sec", 8], ["rings", 1], ["secFmt", "CARD"], ["minA", 2500],
            ["tracks", false], ["prefix", _code select [0, 1]], ["theme", "CITY"], ["asr", 3], ["axMin", 300], ["setPre", "OBJ"], ["setTheme", "NATO"], ["radius", 300]
        ];
        private _b = createHashMapFromArray [
            ["id", _id], ["name", [_name, 24] call _clean], ["owner", [player] call comspec_atak_native_fnc_unitCallsign], ["uid", getPlayerUID player],
            ["rev", 1], ["scope", 0], ["live", false], ["vis", true], ["arma", false], ["render", "FILL"], ["aff", "GEN"], ["area", []], ["gp", _gp],
            ["zones", []], ["axes", []], ["cps", []], ["from", ""], ["side", str side group player], ["grp", netId group player], ["ft", ""], ["mk", []]
        ];
        _mine set [_id, _b];
        call _save; call _bump; call _refresh;
        _id
    };
    // Nom de code automatique : préfixe (OBJ, AO…) et prochain nom libre du thème choisi ; préfixe des zones = initiale.
    case "autoName": {
        private _b = _mine getOrDefault [_arg, createHashMap];
        if ((count _b) isEqualTo 0) exitWith { "" };
        private _gp = _b get "gp";
        private _used = call _usedNames;
        private _list = _themes getOrDefault [_gp getOrDefault ["setTheme", "NATO"], _themes get "NATO"];
        private _cur = ((_b get "name") splitString " ") param [1, ""];
        private _i = ((_list find _cur) + 1) max 0;
        private _code = "";
        for "_k" from 0 to ((count _list) - 1) do {
            private _c = _list select ((_i + _k) mod (count _list));
            if !(_c in _used) exitWith { _code = _c; };
        };
        if (_code isEqualTo "") then { _code = _list select (_i mod (count _list)); };
        _b set ["name", format ["%1 %2", _gp getOrDefault ["setPre", "OBJ"], _code]];
        _gp set ["prefix", _code select [0, 1]];
        [_b, false] call _changed;
        _b get "name"
    };
    case "axisNames": {
        _arg params ["_id", "_n"];
        private _b = [_id] call _get;
        private _list = _themes getOrDefault [(_b getOrDefault ["gp", createHashMap]) getOrDefault ["theme", "CITY"], _themes get "CITY"];
        private _used = call _usedNames;
        // Axes déjà nommés de ce plan : leurs noms resservent (nouveau calcul).
        { _used = _used - [(_x select 1) select [4]]; } forEach (_b getOrDefault ["axes", []]);
        private _out = _list select { !(_x in _used) };
        while { (count _out) < _n } do { _out pushBack format ["%1 %2", _list select ((count _out) mod (count _list)), (count _out) + 1]; };
        _out select [0, _n]
    };
    case "del": {
        private _b = [_arg] call _get;
        if ((count _b) isEqualTo 0) exitWith { false };
        { deleteMarker _x; [_x, true] call comspec_atak_native_fnc_markerWeb; } forEach (_b getOrDefault ["mk", []]);
        _b set ["mk", []];
        if (_arg in _mine) then {
            if ((_b get "scope") > 0) then { ["unshare", _arg] call comspec_atak_native_fnc_zoneAction; };
            _mine deleteAt _arg;
            call _save;
        } else { _rx deleteAt _arg; };
        call _bump; call _refresh;
        true
    };
    case "set": {
        _arg params ["_id", "_key", "_v"];
        private _b = _mine getOrDefault [_id, createHashMap];
        // Plan reçu : seul l'affichage se règle ici.
        if ((count _b) isEqualTo 0) exitWith {
            private _o = _rx getOrDefault [_id, createHashMap];
            if ((count _o) > 0 && {_key in ["vis", "render"]}) then { _o set [_key, _v]; call _bump; call _refresh; };
            false
        };
        if (_key isEqualTo "name") then { _v = [_v, 24] call _clean; if (_v isEqualTo "") then { _v = _b get "name"; }; };
        if ((_b getOrDefault [_key, ""]) isEqualTo _v) exitWith { false };
        _b set [_key, _v];
        switch (_key) do {
            case "vis": { call _save; call _bump; call _refresh; };
            case "live": { call _save; };
            // Type par défaut : appliqué à toutes les zones.
            case "aff": { { _x set [4, _v]; } forEach (_b get "zones"); [_b, false] call _changed; };
            case "arma": {
                if (_v && {!(missionNamespace getVariable ["comspec_atak_native_zones_arma", false])}) exitWith {
                    _b set ["arma", false];
                    ["WARNING", "Zones sur la carte d'Arma désactivées par le serveur", 3, 20] call comspec_atak_native_fnc_notify;
                };
                ["armaNow", _id] call comspec_atak_native_fnc_zoneAction;
                call _save; call _refresh;
            };
            default { [_b, false] call _changed; };
        };
        true
    };
    // Réglages du découpage (gardés dans le plan, sans envoi).
    case "gp": {
        _arg params ["_id", "_key", "_v"];
        private _b = _mine getOrDefault [_id, createHashMap];
        if ((count _b) isEqualTo 0) exitWith { false };
        if (_key isEqualTo "prefix") then { _v = toUpper ([_v, 6] call _clean); };
        (_b get "gp") set [_key, _v];
        call _save; call _refresh;
        true
    };
    // Zone de travail : [id, RECT | POLY | CIRCLE, données].
    case "areaSet": {
        _arg params ["_id", "_kind", "_data"];
        private _b = _mine getOrDefault [_id, createHashMap];
        if ((count _b) isEqualTo 0) exitWith { false };
        _b set ["area", [_kind, _data]];
        [_b, true, false] call _changed;
        true
    };
    // Zone de travail en polygone : [points, genre, centre, rayon].
    case "areaPoly": {
        private _b = if (_arg isEqualType "") then { [_arg] call _get } else { _arg };
        (_b getOrDefault ["area", []]) params [["_k", ""], ["_d", []]];
        switch (_k) do {
            case "RECT": {
                _d params [["_p", [0, 0]], ["_q", [0, 0]]];
                private _x0 = (_p select 0) min (_q select 0); private _x1 = (_p select 0) max (_q select 0);
                private _y0 = (_p select 1) min (_q select 1); private _y1 = (_p select 1) max (_q select 1);
                [[[_x0, _y0], [_x1, _y0], [_x1, _y1], [_x0, _y1]], "RECT", [(_x0 + _x1) / 2, (_y0 + _y1) / 2], ((_x1 - _x0) max (_y1 - _y0)) / 2]
            };
            case "CIRCLE": {
                _d params [["_c", [0, 0]], ["_r", 100]];
                [["circle", [_c, _r, 48]] call comspec_atak_native_fnc_zoneGeom, "CIRCLE", _c, _r]
            };
            case "POLY": { [_d, "POLY", ["centroid", [_d]] call comspec_atak_native_fnc_zoneGeom, 0] };
            default { [[], "", [0, 0], 0] };
        }
    };
    // Calculs en arrière-plan (fn_zoneGen) : découpage, axes, bâtiments.
    case "gen";
    case "axes";
    case "bld": {
        _arg params ["_id", ["_zid", ""]];
        private _b = _mine getOrDefault [_id, createHashMap];
        if ((count _b) isEqualTo 0) exitWith { false };
        if ((count (_b getOrDefault ["area", []])) isEqualTo 0 && {_act isNotEqualTo "bld"}) exitWith { ["INFO", "Tracez d'abord la zone de travail sur la carte de l'app", 3, 10] call comspec_atak_native_fnc_notify; false };
        private _tok = (_b getOrDefault ["genTok", 0]) + 1;
        _b set ["genTok", _tok];
        _b set ["busy", toUpper _act];
        call _refresh;
        [["ZONES", "AXES", "BLD"] select (["gen", "axes", "bld"] find _act), _id, _tok, _zid] spawn comspec_atak_native_fnc_zoneGen;
        true
    };
    case "genDone": {
        _arg params ["_id", "_tok", "_zones", ["_msg", ""]];
        private _b = _mine getOrDefault [_id, createHashMap];
        if ((count _b) isEqualTo 0 || {(_b getOrDefault ["genTok", -1]) isNotEqualTo _tok}) exitWith {};
        _b set ["busy", ""];
        if ((count _zones) > 0) then {
            _b set ["zones", _zones];
            [_b] call _changed;
            ["SUCCESS", format ["%1 : %2 zone(s) nommée(s) et numérotée(s)", _b get "name", count _zones], 3, 20] call comspec_atak_native_fnc_notify;
        } else { call _refresh; };
        if (_msg isNotEqualTo "") then { ["WARNING", _msg, 5, 20] call comspec_atak_native_fnc_notify; };
    };
    case "axesDone": {
        _arg params ["_id", "_tok", "_axes", ["_cps", []], ["_msg", ""]];
        private _b = _mine getOrDefault [_id, createHashMap];
        if ((count _b) isEqualTo 0 || {(_b getOrDefault ["genTok", -1]) isNotEqualTo _tok}) exitWith {};
        _b set ["busy", ""];
        if ((count _axes) > 0) then {
            _b set ["axes", _axes];
            _b set ["cps", _cps];
            [_b] call _changed;
            ["SUCCESS", format ["%1 : %2 (%3 point(s) de contrôle)", _b get "name", (_axes apply { _x select 1 }) joinString ", ", count _cps], 5, 20] call comspec_atak_native_fnc_notify;
        } else { call _refresh; };
        if (_msg isNotEqualTo "") then { ["WARNING", _msg, 5, 20] call comspec_atak_native_fnc_notify; };
    };
    case "bldDone": {
        _arg params ["_id", "_tok", "_zid", "_list"];
        private _b = _mine getOrDefault [_id, createHashMap];
        if ((count _b) isEqualTo 0 || {(_b getOrDefault ["genTok", -1]) isNotEqualTo _tok}) exitWith {};
        _b set ["busy", ""];
        private _z = [_b, _zid] call _zoneOf;
        if ((count _z) isEqualTo 0) exitWith { call _refresh; };
        _z set [10, _list];
        [_b, false] call _changed;
        ["SUCCESS", format ["Zone %1 : %2 bâtiment(s) numéroté(s)", _z select 1, count _list], 3, 20] call comspec_atak_native_fnc_notify;
    };
    // Zone modifiée par l'auteur du plan : [id, id de zone, label | type | rm | del | bldClear, valeur].
    case "zoneSet": {
        _arg params ["_id", "_zid", "_key", ["_v", ""]];
        private _b = _mine getOrDefault [_id, createHashMap];
        if ((count _b) isEqualTo 0) exitWith { false };
        private _z = [_b, _zid] call _zoneOf;
        if ((count _z) isEqualTo 0) exitWith { false };
        switch (_key) do {
            case "label": { _v = [_v, 16] call _clean; if (_v isNotEqualTo "") then { _z set [1, _v]; }; };
            case "type": { if ((_types findIf { (_x select 0) isEqualTo _v }) >= 0) then { _z set [4, _v]; }; };
            case "rm": { _z set [5, [_v, ""] select !(_v in ["FILL", "LINE", "POINT"])]; };
            case "bldClear": { _z set [10, []]; };
            case "del": { (_b get "zones") deleteAt ((_b get "zones") findIf { (_x select 0) isEqualTo _zid }); };
        };
        [_b, _key isEqualTo "del"] call _changed;
        true
    };
    case "axisSet": {
        _arg params ["_id", "_aid", "_key", ["_v", ""]];
        private _b = _mine getOrDefault [_id, createHashMap];
        if ((count _b) isEqualTo 0) exitWith { false };
        private _ax = _b get "axes";
        private _i = _ax findIf { (_x select 0) isEqualTo _aid };
        if (_i < 0) exitWith { false };
        switch (_key) do {
            case "name": { _v = toUpper ([_v, 24] call _clean); if (_v isNotEqualTo "") then { private _old = (_ax select _i) select 1; (_ax select _i) set [1, _v]; { if ((_x select 3) isEqualTo _old) then { _x set [3, _v]; }; } forEach (_b get "cps"); }; };
            case "rm": { (_ax select _i) set [5, [_v, ""] select !(_v in ["FILL", "LINE", "POINT"])]; };
            case "del": { private _n = (_ax select _i) select 1; _ax deleteAt _i; _b set ["cps", (_b get "cps") select { (_x select 3) isNotEqualTo _n }]; };
        };
        [_b, _key isEqualTo "del"] call _changed;
        true
    };
    // Axe repris en route nommée de l'app Routes (véhicule, par les routes) : départ, ses points de contrôle, arrivée.
    case "axisRoute": {
        _arg params ["_id", "_aid"];
        private _b = [_id] call _get;
        private _a = (_b getOrDefault ["axes", []]) param [(_b getOrDefault ["axes", []]) findIf { (_x select 0) isEqualTo _aid }, []];
        if ((count _a) < 4 || {(count (_a select 3)) < 2}) exitWith { "" };
        _a params ["", "_name", "_cls", "_pts"];
        private _rid = ["new", ["VEH", _name]] call comspec_atak_native_fnc_routeAction;
        if (_rid isEqualTo "") exitWith { "" };
        ["set", [_rid, "color", ["ColorGreen", "ColorBlue"] select (_cls isEqualTo "MSR")]] call comspec_atak_native_fnc_routeAction;
        // Points de contrôle dans l'ordre de l'axe (sommet le plus proche).
        private _cps = ((_b getOrDefault ["cps", []]) select { (_x select 3) isEqualTo _name }) apply {
            private _p = _x select 2;
            private _bi = 0; private _bd = 1e9;
            { private _d = _p distance2D _x; if (_d < _bd) then { _bd = _d; _bi = _forEachIndex; }; } forEach _pts;
            [_bi, _x]
        };
        _cps sort true;
        ["stepAdd", [_rid, (_pts select 0) + [0], -1, "", "SP"]] call comspec_atak_native_fnc_routeAction;
        { ["stepAdd", [_rid, ((_x select 1) select 2) + [0], -1, (_x select 1) select 1, "CP"]] call comspec_atak_native_fnc_routeAction; } forEach _cps;
        ["stepAdd", [_rid, (_pts select -1) + [0], -1, "", "END"]] call comspec_atak_native_fnc_routeAction;
        ["SUCCESS", format ["Route %1 créée dans l'app Routes (%2 point(s) de contrôle)", _name, count _cps], 4, 20] call comspec_atak_native_fnc_notify;
        _rid
    };
    // Situation donnée ici : [id, id de zone, état ("" : inchangé), signalement [nature, effectif, texte, position] ou []].
    case "sit": {
        _arg params ["_id", "_zid", ["_st", ""], ["_rep", []]];
        private _b = [_id] call _get;
        if ((count _b) isEqualTo 0) exitWith { false };
        private _z = [_b, _zid] call _zoneOf;
        if ((count _z) isEqualTo 0) exitWith { false };
        private _who = [player] call comspec_atak_native_fnc_unitCallsign;
        private _stamp = serverTime;
        private _reps = [];
        if ((count _rep) >= 3) then {
            _rep params [["_nat", "UNK"], ["_size", ""], ["_txt", ""], ["_pos", []]];
            if !(_pos isEqualType [] && {(count _pos) >= 2}) then { _pos = _z select 3; };
            _reps pushBack [format ["S%1_%2", (getPlayerUID player) select [((count getPlayerUID player) - 4) max 0], floor (diag_tickTime * 100) mod 10000000], _stamp,
                [dayTime, "HH:MM"] call BIS_fnc_timeToString, _who, _nat, [_size, 12] call _clean, [_txt, 120] call _clean, [round (_pos select 0), round (_pos select 1)]];
        };
        if (_st isEqualTo "" && {(count _reps) isEqualTo 0}) exitWith { false };
        [_b, _zid, _st, _stamp, _who, _reps] call _apply;
        [_b, false, false] call _changed;
        private _shared = (_b getOrDefault ["scope", 0]) > 0;
        if (_shared && {missionNamespace getVariable ["comspec_atak_native_zones_share", true]}) then {
            private _to = [_b] call _aud;
            private _args = ["sit", _id, _zid, _st, _stamp, _who, _reps];
            private _res = [{
                params ["_args", "_to"];
                if ((count _to) > 0) then { ["comspec_atak_native_zone", _args, _to] call CBA_fnc_targetEvent; };
                ["comspec_atak_native_zoneSrv", _args] call CBA_fnc_serverEvent;
            }, [_args, _to], "Rapport de zone", 1] call comspec_atak_native_fnc_netSend;
            private _what = [((([_st] call _stateRow) select 1) call _tr), ("signalement") call _tr] select (_st isEqualTo "");
            if (_res isEqualTo "QUEUED") then {
                ["WARNING", format ["Zone %1 (%2) : %3 · pas de réseau, envoi au retour du signal", _z select 1, _b get "name", _what], 4, 20] call comspec_atak_native_fnc_notify;
            } else {
                ["SUCCESS", format ["Zone %1 (%2) : %3 · envoyé à %4 joueur(s)", _z select 1, _b get "name", _what, count _to], 3, 20] call comspec_atak_native_fnc_notify;
            };
        } else {
            [format ["Zone %1 : %2", _z select 1, ((([[_st] call _stateRow, ["", "Signalement noté"]] select (_st isEqualTo "")) select 1) call _tr)], 2] call comspec_atak_native_fnc_toast;
        };
        true
    };
    // Situation remise à zéro par l'auteur du plan (états « non reconnue », signalements effacés), partout.
    case "reset": {
        private _b = _mine getOrDefault [_arg, createHashMap];
        if ((count _b) isEqualTo 0) exitWith { false };
        { _x set [6, "UNREC"]; _x set [7, serverTime]; _x set [8, ""]; _x set [9, []]; } forEach (_b get "zones");
        [_b, false, false] call _changed;
        if ((_b get "scope") > 0) then {
            private _to = [_b] call _aud;
            [{
                params ["_id", "_to", "_t"];
                if ((count _to) > 0) then { ["comspec_atak_native_zone", ["reset", _id, _t], _to] call CBA_fnc_targetEvent; };
                ["comspec_atak_native_zoneSrv", ["reset", _id, _t]] call CBA_fnc_serverEvent;
            }, [_arg, _to, serverTime], "Rapport de zone", 1] call comspec_atak_native_fnc_netSend;
        };
        ["INFO", "Situation remise à zéro", 2, 10] call comspec_atak_native_fnc_notify;
        true
    };
    // Partage : [id, portée 1 équipe | 2 groupe | 3 camp, silencieux].
    case "share": {
        _arg params ["_id", ["_sc", 2], ["_quiet", false]];
        private _b = _mine getOrDefault [_id, createHashMap];
        if ((count _b) isEqualTo 0) exitWith { false };
        if !(missionNamespace getVariable ["comspec_atak_native_zones_share", true]) exitWith { ["WARNING", "Partage des zones désactivé par le serveur", 3, 20] call comspec_atak_native_fnc_notify; false };
        if (((count (_b get "zones")) + (count (_b get "axes"))) isEqualTo 0) exitWith { ["INFO", "Plan sans zone ni axe : rien à envoyer", 3, 10] call comspec_atak_native_fnc_notify; false };
        if ((_b get "scope") > _sc) then { ["unshare", _id] call comspec_atak_native_fnc_zoneAction; };
        _b set ["scope", _sc];
        _b set ["live", true];
        _b set ["side", str side group player];
        _b set ["grp", netId group player];
        _b set ["ft", [player getVariable ["COMSPEC_FT", ""], ""] select (_sc >= 2)];
        call _save;
        private _to = [_b] call _aud;
        private _pk = ["pack", _b] call comspec_atak_native_fnc_zoneAction;
        private _who = [player] call comspec_atak_native_fnc_unitCallsign;
        private _nPts = 0;
        { _nPts = _nPts + count (_x select 2); } forEach (_b get "zones");
        private _res = [{
            params ["_pk", "_to", "_who", "_sc", "_id", "_b"];
            if ((count _to) > 0) then { ["comspec_atak_native_zone", ["set", _pk, _who, _sc], _to] call CBA_fnc_targetEvent; };
            ["comspec_atak_native_zoneSrv", ["set", _id, _pk, _sc, _b get "side", _b get "grp", _b get "ft", _who]] call CBA_fnc_serverEvent;
        }, [_pk, _to, _who, _sc, _id, _b], "Plan de zones", 2 + (count (_b get "zones")) * 0.05 + _nPts * 0.02, 1, "zn:" + _id] call comspec_atak_native_fnc_netSend;
        if !(_quiet) then {
            switch (true) do {
                case (_res isEqualTo "QUEUED"): { ["WARNING", "Pas de réseau : le plan partira au retour du signal", 4, 20] call comspec_atak_native_fnc_notify; };
                case ((count _to) isEqualTo 0): { ["INFO", format ["Plan partagé (%1) : personne d'autre pour l'instant, il sera remis aux arrivants", ["scopeName", _sc] call comspec_atak_native_fnc_zoneAction], 4, 20] call comspec_atak_native_fnc_notify; };
                default { ["SUCCESS", format ["Plan envoyé à %1 joueur(s) (%2)", count _to, ["scopeName", _sc] call comspec_atak_native_fnc_zoneAction], 3, 20] call comspec_atak_native_fnc_notify; };
            };
        };
        call _refresh;
        true
    };
    case "unshare": {
        private _b = _mine getOrDefault [_arg, createHashMap];
        if ((count _b) isEqualTo 0) exitWith { false };
        private _to = [_b] call _aud;
        [{
            params ["_id", "_to"];
            if ((count _to) > 0) then { ["comspec_atak_native_zone", ["del", _id, [player] call comspec_atak_native_fnc_unitCallsign, 0], _to] call CBA_fnc_targetEvent; };
            ["comspec_atak_native_zoneSrv", ["del", _id]] call CBA_fnc_serverEvent;
        }, [_arg, _to], "Plan de zones", 1, 1, "zn:" + _arg] call comspec_atak_native_fnc_netSend;
        _b set ["scope", 0];
        _b set ["live", false];
        call _save; call _refresh;
        true
    };
    // Réception (événement comspec_atak_native_zone) :
    //   ["set", plan (paires), auteur, portée] | ["del", id, auteur] | ["sit", id, zone, état, horodatage, auteur, signalements]
    //   | ["sitAll", même chose (rejeu serveur)] | ["reset", id, horodatage]
    case "recv": {
        private _op = _arg param [0, ""];
        private _quiet = missionNamespace getVariable ["COMSPEC_ATAK_Replaying", false];
        switch (_op) do {
            case "del": {
                _arg params ["", ["_id", ""], ["_who", ""]];
                if !(_id isEqualType "" && {_id in _rx}) exitWith {};
                private _n = (_rx get _id) get "name";
                _rx deleteAt _id;
                if !(_quiet) then { ["INFO", format ["Plan de zones retiré par %1 : %2", _who, _n], 3, 20] call comspec_atak_native_fnc_notify; };
                call _bump; call _refresh;
            };
            case "set": {
                _arg params ["", ["_pk", []], ["_who", ""], ["_sc", 2]];
                private _b = [_pk] call comspec_atak_native_fnc_zoneUnpack;
                if ((count _b) isEqualTo 0) exitWith {};
                if ((_b get "uid") isEqualTo getPlayerUID player || {(_b get "id") in _mine}) exitWith {};
                private _id = _b get "id";
                private _old = _rx getOrDefault [_id, createHashMap];
                _b set ["from", [_who, _b get "owner"] select (_who isEqualTo "")];
                _b set ["scope", (_sc max 1) min 3];
                _b set ["arma", false];
                if ((count _old) > 0) then {
                    _b set ["vis", _old get "vis"]; _b set ["render", _old get "render"];
                    // Situation reçue entre-temps (plus récente que celle du plan) : gardée.
                    { [_b, _x select 0, _x select 6, _x select 7, _x select 8, _x select 9] call _apply; } forEach (_old get "zones");
                };
                _rx set [_id, _b];
                if !(_quiet) then {
                    if ((count _old) isEqualTo 0) then {
                        ["MESSAGE", format ["Plan de zones reçu de %1 (%2) : %3, %4 zone(s), %5 axe(s)", _b get "from", ["scopeName", _b get "scope"] call comspec_atak_native_fnc_zoneAction, _b get "name", count (_b get "zones"), count (_b get "axes")], 6, 50] call comspec_atak_native_fnc_notify;
                        [] call comspec_atak_native_fnc_vibrate;
                    } else {
                        private _msg = format ["Plan de zones mis à jour par %1 : %2", _b get "from", _b get "name"];
                        if !(([_msg, 2] call comspec_atak_native_fnc_toast)) then { ["INFO", _msg, 2, 10] call comspec_atak_native_fnc_notify; };
                    };
                };
                call _bump; call _refresh;
            };
            case "ask": {
                _arg params ["", ["_id", ""], ["_zid", ""], ["_st", ""], ["_rep", []], ["_by", ""]];
                if (_quiet || {!(_id isEqualType "" && {_id in _mine} && {_zid isEqualType ""} && {_st isEqualType ""} && {_rep isEqualType []} && {_by isEqualType ""})}) exitWith {};
                _rep = (_rep select [0, 4]) apply { [_x, ""] select !(_x isEqualType "" || {_x isEqualType []}) };
                ["ask", [_id, _zid, _st, _rep, [_by, 24] call _clean]] call comspec_atak_native_fnc_zoneAction;
            };
            case "sit";
            case "sitAll": {
                _arg params ["", ["_id", ""], ["_zid", ""], ["_st", ""], ["_stamp", 0], ["_by", ""], ["_reps", []]];
                if !(_id isEqualType "" && {_zid isEqualType ""} && {_st isEqualType ""} && {_stamp isEqualType 0} && {_reps isEqualType []}) exitWith {};
                private _b = [_id] call _get;
                if ((count _b) isEqualTo 0) exitWith {};
                private _reps2 = (_reps select [0, 12]) select { _x isEqualType [] && {(count _x) >= 8} && {(_x select 0) isEqualType ""} && {(_x select 1) isEqualType 0} };
                _reps2 = _reps2 apply { [(_x select 0) select [0, 24], _x select 1, [_x select 2, 5] call _clean, [_x select 3, 24] call _clean, [_x select 4, 6] call _clean, [_x select 5, 12] call _clean, [_x select 6, 120] call _clean, [[0, 0], _x select 7] select ((_x select 7) isEqualType [] && {(count (_x select 7)) >= 2})] };
                private _z = [_b, _zid] call _zoneOf;
                if ((count _z) isEqualTo 0) exitWith {};
                // Question en attente sur cette zone : un autre a déjà tranché.
                if (_st isNotEqualTo "") then { (missionNamespace getVariable ["COMSPEC_ATAK_ZnAsk", createHashMap]) deleteAt (_id + "|" + _zid); };
                private _was = _z select 6;
                if !([_b, _zid, _st, _stamp, _by, _reps2] call _apply) exitWith {};
                [_b, false, false] call _changed;
                if (!_quiet && {_op isEqualTo "sit"}) then {
                    private _now = _z select 6;
                    private _r = _reps2 param [0, []];
                    private _det = if ((count _r) >= 8) then { ([["natureName", _r select 4] call comspec_atak_native_fnc_zoneAction, _r select 5, _r select 6] select { _x isNotEqualTo "" }) joinString " · " } else { "" };
                    private _msg = format ["Zone %1 (%2) : %3%4 · %5", _z select 1, _b get "name", (([_now] call _stateRow) select 1) call _tr, ["", " · " + _det] select (_det isNotEqualTo ""), _by];
                    if (_now in ["ENY", "CONT"] && {_now isNotEqualTo _was || {(count _r) > 0}}) then {
                        ["WARNING", _msg, 6, 40] call comspec_atak_native_fnc_notify;
                        [] call comspec_atak_native_fnc_vibrate;
                    } else {
                        if !(([_msg, 3] call comspec_atak_native_fnc_toast)) then { ["INFO", _msg, 3, 10] call comspec_atak_native_fnc_notify; };
                    };
                };
            };
            case "reset": {
                _arg params ["", ["_id", ""], ["_t", 0]];
                private _b = [_id] call _get;
                if ((count _b) isEqualTo 0) exitWith {};
                { if ((_x select 7) <= _t) then { _x set [6, "UNREC"]; _x set [7, _t]; _x set [8, ""]; }; _x set [9, []]; } forEach (_b get "zones");
                [_b, false, false] call _changed;
                if !(_quiet) then { ["INFO", format ["Situation remise à zéro : %1", _b get "name"], 3, 10] call comspec_atak_native_fnc_notify; };
            };
        };
    };
    // Boîtes et surfaces des zones, gardées par révision : [révision, boîte du plan, boîtes des zones, surfaces, points 3D].
    case "geo": {
        private _b = _arg;
        private _g = _b getOrDefault ["geo", []];
        if ((_g param [0, -1]) isEqualTo (_b get "rev")) exitWith { _g };
        private _zb = []; private _za = []; private _p3 = [];
        private _X0 = 1e9; private _Y0 = 1e9; private _X1 = -1e9; private _Y1 = -1e9;
        {
            private _pts = _x select 2;
            private _bb = ["bbox", [_pts]] call comspec_atak_native_fnc_zoneGeom;
            _zb pushBack _bb;
            _za pushBack (abs (["area", [_pts]] call comspec_atak_native_fnc_zoneGeom));
            _p3 pushBack (_pts apply { [_x select 0, _x select 1, 0] });
            _X0 = _X0 min (_bb select 0); _Y0 = _Y0 min (_bb select 1); _X1 = _X1 max (_bb select 2); _Y1 = _Y1 max (_bb select 3);
        } forEach (_b get "zones");
        {
            { _X0 = _X0 min (_x select 0); _Y0 = _Y0 min (_x select 1); _X1 = _X1 max (_x select 0); _Y1 = _Y1 max (_x select 1); } forEach (_x select 3);
        } forEach (_b get "axes");
        private _ap = (["areaPoly", _b] call comspec_atak_native_fnc_zoneAction) select 0;
        { _X0 = _X0 min (_x select 0); _Y0 = _Y0 min (_x select 1); _X1 = _X1 max (_x select 0); _Y1 = _Y1 max (_x select 1); } forEach _ap;
        _g = [_b get "rev", [_X0, _Y0, _X1, _Y1], _zb, _za, _p3, _ap apply { [_x select 0, _x select 1, 0] }];
        _b set ["geo", _g];
        _g
    };
    // Triangles de remplissage des zones qui ne sont pas des cases (calcul en arrière-plan, gardé par révision) :
    // HashMap id de zone -> triangles (vide tant que le calcul n'est pas fini).
    case "tri": {
        private _b = _arg;
        private _cache = uiNamespace getVariable "COMSPEC_ATAK_ZnTri";
        if (isNil "_cache") then { _cache = createHashMap; uiNamespace setVariable ["COMSPEC_ATAK_ZnTri", _cache]; };
        private _key = format ["%1|%2", _b get "id", _b get "rev"];
        private _e = _cache getOrDefault [_b get "id", []];
        if ((_e param [0, ""]) isEqualTo _key) exitWith { _e select 1 };
        _cache set [_b get "id", [_key, createHashMap]];
        [_b get "id", _key, ((_b get "zones") select { (count (_x select 11)) isNotEqualTo 4 }) apply { [_x select 0, _x select 2] }] spawn {
            params ["_id", "_key", "_list"];
            private _h = createHashMap;
            { _h set [_x select 0, ["tri", [_x select 1]] call comspec_atak_native_fnc_zoneGeom]; if ((_forEachIndex mod 4) isEqualTo 3) then { sleep 0.01; }; } forEach _list;
            private _cache = uiNamespace getVariable ["COMSPEC_ATAK_ZnTri", createHashMap];
            if (((_cache getOrDefault [_id, []]) param [0, ""]) isEqualTo _key) then { _cache set [_id, [_key, _h]]; };
        };
        createHashMap
    };
    // Zone d'un plan qui contient la position (la plus petite) : [plan, position] -> zone ou [].
    case "zoneIn": {
        _arg params ["_b", "_p"];
        private _g = ["geo", _b] call comspec_atak_native_fnc_zoneAction;
        (_g select 1) params ["_X0", "_Y0", "_X1", "_Y1"];
        _p params ["_px", "_py"];
        if (_px < _X0 || {_px > _X1} || {_py < _Y0} || {_py > _Y1}) exitWith { [] };
        private _best = []; private _ba = 1e12;
        private _p3 = [_px, _py, 0];
        {
            (_g select 2 select _forEachIndex) params ["_x0", "_y0", "_x1", "_y1"];
            if (_px >= _x0 && {_px <= _x1} && {_py >= _y0} && {_py <= _y1}) then {
                private _a = (_g select 3) select _forEachIndex;
                if (_a < _ba && {(count (_x select 11)) isEqualTo 4 || {_p3 inPolygon ((_g select 4) select _forEachIndex)}}) then { _ba = _a; _best = _x; };
            };
        } forEach (_b get "zones");
        _best
    };
    // Zone sous une position parmi les plans affichés : [position] -> [plan, zone] ou [].
    case "at": {
        _arg params [["_p", []]];
        if ((count _p) < 2) exitWith { [] };
        private _best = []; private _ba = 1e12;
        {
            if (_x get "vis") then {
                private _z = ["zoneIn", [_x, _p]] call comspec_atak_native_fnc_zoneAction;
                if ((count _z) > 0) then {
                    private _g = ["geo", _x] call comspec_atak_native_fnc_zoneAction;
                    private _a = (_g select 3) select ((_x get "zones") find _z);
                    if (_a < _ba) then { _ba = _a; _best = [_x, _z]; };
                };
            };
        } forEach ((values _mine) + (values _rx));
        _best
    };
    // Bandeau du curseur de la carte : texte de la zone sous le point (gardé par position et révision).
    case "atText": {
        _arg params [["_p", []]];
        if ((count _p) < 2 || {!(missionNamespace getVariable ["comspec_atak_native_zones_cursor", true])}) exitWith { "" };
        if (((count _mine) + (count _rx)) isEqualTo 0) exitWith { "" };
        private _key = [round ((_p select 0) / 3), round ((_p select 1) / 3), missionNamespace getVariable ["COMSPEC_ATAK_ZnRev", 0], _fr];
        private _c = uiNamespace getVariable ["COMSPEC_ATAK_ZnCursor", []];
        if ((_c param [0, []]) isEqualTo _key) exitWith { _c select 1 };
        private _hit = ["at", [_p]] call comspec_atak_native_fnc_zoneAction;
        private _t = "";
        if ((count _hit) > 0) then {
            _hit params ["_b", "_z"];
            private _sr = [_z select 6] call _stateRow;
            private _hex = ((_sr select 4) select [0, 3]) call BIS_fnc_colorRGBtoHTML;
            _t = format ["<t font='COMSPEC_BarlowBold' color='%1'>%2</t> <t size='0.8' color='#b7c1bc'>%3</t>",
                _hex, ((_z select 1) regexReplace ["[<>&]", ""]), (format ["%1 · %2", _b get "name", _sr select ([3, 2] select _fr)]) regexReplace ["[<>&]", ""]];
        };
        uiNamespace setVariable ["COMSPEC_ATAK_ZnCursor", [_key, _t]];
        _t
    };
    // Citation d'une zone dans un message ou un compte rendu : [plan, zone] -> « B-04 (OBJ BRAVO) 33T… · Ennemi signalé ».
    case "cite": {
        _arg params ["_b", "_z"];
        private _p = _z select 3;
        format ["%1 (%2) %3 · %4", _z select 1, _b get "name", [[_p select 0, _p select 1, 0], 8] call comspec_atak_native_fnc_gridRef, (([_z select 6] call _stateRow) select 1) call _tr]
    };
    // Raccourci @zone des messages : zone sous ma position, sinon "".
    case "citeAt": {
        private _hit = ["at", [_arg]] call comspec_atak_native_fnc_zoneAction;
        if ((count _hit) isEqualTo 0) exitWith { "" };
        ["cite", _hit] call comspec_atak_native_fnc_zoneAction
    };
    // Contact ennemi posé ou signalé par ce téléphone (marqueur hostile, TIC, SALUTE, C.R. CONTACT / SPOTREP) :
    // [position, nature, effectif, texte]. Réglage serveur « Contact dans une zone » (zones_contact_mode) :
    //   MANUAL (défaut) rien ; ASK question OUI / NON à ce joueur et à l'auteur du plan (sans réponse : rien) ;
    //   AUTO la zone qui le contient passe « ennemi signalé » (« contact » si tenue par un ami ; interdite ou déjà
    //   ennemie : inchangée) avec un signalement, envoyé comme un rapport de zone.
    case "contact": {
        _arg params [["_p", []], ["_nat", "UNK"], ["_size", ""], ["_txt", ""]];
        private _mode = missionNamespace getVariable ["comspec_atak_native_zones_contact_mode", "MANUAL"];
        if ((count _p) < 2 || {!(_mode in ["ASK", "AUTO"])}) exitWith { 0 };
        private _n = 0;
        {
            private _b = _x;
            private _z = ["zoneIn", [_b, _p]] call comspec_atak_native_fnc_zoneAction;
            if ((count _z) > 0) then {
                private _st = _z select 6;
                private _new = switch (true) do { case (_st in ["UNREC", "CLEAR", "CONT"]): { "ENY" }; case (_st isEqualTo "FRD"): { "CONT" }; default { "" }; };
                private _rep = [_nat, _size, _txt, [_p select 0, _p select 1]];
                if (_mode isEqualTo "AUTO") then {
                    ["sit", [_b get "id", _z select 0, _new, _rep]] call comspec_atak_native_fnc_zoneAction;
                    _n = _n + 1;
                } else {
                    if (_new isEqualTo "") exitWith {};
                    private _q = [_b get "id", _z select 0, _new, _rep, [player] call comspec_atak_native_fnc_unitCallsign];
                    ["ask", _q] call comspec_atak_native_fnc_zoneAction;
                    // Plan reçu : la même question part à son auteur (s'il est là).
                    if !((_b get "id") in _mine) then {
                        private _ou = _b getOrDefault ["uid", ""];
                        private _to = allPlayers select { (getPlayerUID _x) isEqualTo _ou && {_x isNotEqualTo player} };
                        if ((count _to) > 0) then {
                            [{
                                params ["_q", "_to"];
                                ["comspec_atak_native_zone", ["ask"] + _q, _to] call CBA_fnc_targetEvent;
                            }, [_q, _to], "Rapport de zone", 1] call comspec_atak_native_fnc_netSend;
                        };
                    };
                    _n = _n + 1;
                };
            };
        } forEach ((values _mine) + (values _rx));
        _n
    };
    // Question en attente (mode Proposer) : [id, zone, état proposé, signalement, auteur du contact].
    case "ask": {
        _arg params [["_id", ""], ["_zid", ""], ["_st", ""], ["_rep", []], ["_by", ""]];
        private _b = [_id] call _get;
        if ((count _b) isEqualTo 0) exitWith { false };
        private _z = [_b, _zid] call _zoneOf;
        if ((count _z) isEqualTo 0 || {(_states findIf { (_x select 0) isEqualTo _st }) < 0}) exitWith { false };
        private _asks = missionNamespace getVariable ["COMSPEC_ATAK_ZnAsk", createHashMap];
        missionNamespace setVariable ["COMSPEC_ATAK_ZnAsk", _asks];
        _asks set [_id + "|" + _zid, [_id, _zid, _st, _rep, _by, serverTime]];
        ["WARNING", format [("Contact en %1 (%2) : passer la zone en « %3 » ? OUI / NON dans l'app Zones") call _tr, _z select 1, _b get "name", (([_st] call _stateRow) select 1) call _tr], 8, 40, "ZONES"] call comspec_atak_native_fnc_notify;
        [] call comspec_atak_native_fnc_vibrate;
        call _bump; call _refresh;
        true
    };
    case "asks": { values (missionNamespace getVariable ["COMSPEC_ATAK_ZnAsk", createHashMap]) };
    // Réponse : [clé id|zone, oui]. Oui : état et signalement appliqués et envoyés (« sit ») ; non : oubliée.
    case "askAnswer": {
        _arg params [["_k", ""], ["_yes", false]];
        private _asks = missionNamespace getVariable ["COMSPEC_ATAK_ZnAsk", createHashMap];
        private _q = _asks getOrDefault [_k, []];
        _asks deleteAt _k;
        if ((count _q) > 0 && {_yes}) then { ["sit", [_q select 0, _q select 1, _q select 2, _q select 3]] call comspec_atak_native_fnc_zoneAction; };
        call _bump; call _refresh;
        true
    };
    // Marqueur posé par ce joueur (EH MarkerCreated, fn_markerDrop) : contact si c'est un marqueur ennemi, une fois.
    case "markerContact": {
        private _m = _arg;
        if ((markerShape _m) isNotEqualTo "ICON") exitWith { false };
        private _done = missionNamespace getVariable ["COMSPEC_ATAK_ZnMkDone", createHashMap];
        if (_m in _done) exitWith { false };
        private _type = markerType _m;
        private _txt = markerText _m;
        if !(((toLower _type) select [0, 2]) isEqualTo "o_" || {(toUpper (_txt select [0, 4])) in ["ENI ", "ENY "]}) exitWith { false };
        _done set [_m, true];
        missionNamespace setVariable ["COMSPEC_ATAK_ZnMkDone", _done];
        ["contact", [markerPos _m, ["natureOf", _type] call comspec_atak_native_fnc_zoneAction, "", _txt]] call comspec_atak_native_fnc_zoneAction;
        true
    };
    // Nature d'un contact d'après le type de marqueur hostile (o_inf, o_armor…).
    case "natureOf": {
        private _t = toLower _arg;
        switch (true) do {
            case (_t in ["o_inf"]): { "INF" };
            case (_t in ["o_motor_inf"]): { "MOT" };
            case (_t in ["o_mech_inf", "o_armor"]): { "MECA" };
            case (_t in ["o_recon"]): { "REC" };
            case (_t in ["o_art", "o_mortar"]): { "ART" };
            case (_t in ["o_antiair"]): { "AA" };
            case (_t in ["o_air", "o_plane", "o_uav"]): { "AIR" };
            case (_t in ["o_hq"]): { "HQ" };
            default { "UNK" };
        }
    };
    // Carte d'Arma et Athena : cases en rectangles (pleins ou bordés), autres zones en polyligne fermée, étiquette au
    // centre (nom et état), axes en polyligne, points de contrôle. Refait en entier à chaque modification (regroupée).
    // Athena n'a pas de polygone plein dans les marqueurs : seules les cases de grille y sont remplies.
    case "armaNow": {
        private _b = _mine getOrDefault [_arg, createHashMap];
        if ((count _b) isEqualTo 0) exitWith {};
        { deleteMarker _x; [_x, true] call comspec_atak_native_fnc_markerWeb; } forEach (_b getOrDefault ["mk", []]);
        _b set ["mk", []];
        if (!(_b get "arma") || {!(missionNamespace getVariable ["comspec_atak_native_zones_arma", false])} || {((count (_b get "zones")) + (count (_b get "axes"))) isEqualTo 0}) exitWith {};
        private _ch = [3, 1] select ((_b get "scope") >= 3);
        private _mk = [];
        private _mkName = {
            private _index = (missionNamespace getVariable ["COMSPEC_ATAK_MarkerIndex", 0]) + 1;
            missionNamespace setVariable ["COMSPEC_ATAK_MarkerIndex", _index];
            format ["_USER_DEFINED #%1/%2/%3", clientOwner, 9000 + _index, _ch]
        };
        private _cap = (missionNamespace getVariable ["comspec_atak_native_zones_armamax", 60]) max 10 min 200;
        private _ptsOnly = (count (_b get "zones")) > _cap;
        missionNamespace setVariable ["COMSPEC_MarkerEhMuted", (missionNamespace getVariable ["COMSPEC_MarkerEhMuted", 0]) + 1];
        {
            _x params ["", "_name", "_pts", "_lp", "_ty", "_rm", "_st", "", "", "_reps", "", ["_rect", []]];
            if (_rm isEqualTo "") then { _rm = _b get "render"; };
            private _col = [([_ty] call _typeRow) select 3, ([_st] call _stateRow) select 5] select (_st isNotEqualTo "UNREC");
            if (!_ptsOnly && {_rm isNotEqualTo "POINT"}) then {
                if ((count _rect) isEqualTo 4) then {
                    private _m = ["create", [call _mkName, [_rect select 0, _rect select 1, 0], _ch]] call comspec_atak_native_fnc_markerNet;
                    if (_m isNotEqualTo "") then {
                        _m setMarkerShapeLocal "RECTANGLE";
                        _m setMarkerSizeLocal [_rect select 2, _rect select 3];
                        _m setMarkerBrushLocal (["Border", "Solid"] select (_rm isEqualTo "FILL"));
                        _m setMarkerAlphaLocal ([0.9, 0.45] select (_rm isEqualTo "FILL"));
                        _m setMarkerColor _col;
                        _mk pushBack _m;
                    };
                } else {
                    if ((count _pts) >= 3) then {
                        private _flat = [];
                        { _flat append [_x select 0, _x select 1]; } forEach ((_pts select [0, 199]) + [_pts select 0]);
                        private _m = ["create", [call _mkName, [(_pts select 0) select 0, (_pts select 0) select 1, 0], _ch]] call comspec_atak_native_fnc_markerNet;
                        if (_m isNotEqualTo "") then {
                            _m setMarkerShapeLocal "POLYLINE";
                            _m setMarkerPolylineLocal _flat;
                            _m setMarkerColor _col;
                            _mk pushBack _m;
                        };
                    };
                };
            };
            private _m = ["create", [call _mkName, [_lp select 0, _lp select 1, 0], _ch]] call comspec_atak_native_fnc_markerNet;
            if (_m isNotEqualTo "") then {
                _m setMarkerTypeLocal "mil_dot";
                _m setMarkerSizeLocal [0.6, 0.6];
                _m setMarkerTextLocal format ["%1%2%3", _name, ["", " · " + ((([_st] call _stateRow) select 1) call _tr)] select (_st isNotEqualTo "UNREC"), ["", format [" (%1)", count _reps]] select ((count _reps) > 0)];
                _m setMarkerColor _col;
                _mk pushBack _m;
            };
        } forEach (_b get "zones");
        {
            _x params ["", "_name", "_cls", "_pts"];
            if ((count _pts) >= 2) then {
                private _flat = [];
                { _flat append [_x select 0, _x select 1]; } forEach (_pts select [0, 200]);
                private _m = ["create", [call _mkName, [(_pts select 0) select 0, (_pts select 0) select 1, 0], _ch]] call comspec_atak_native_fnc_markerNet;
                if (_m isNotEqualTo "") then {
                    _m setMarkerShapeLocal "POLYLINE";
                    _m setMarkerPolylineLocal _flat;
                    _m setMarkerColor (["ColorGreen", "ColorBlue"] select (_cls isEqualTo "MSR"));
                    _mk pushBack _m;
                };
                private _mid = _pts select (floor ((count _pts) / 2));
                private _l = ["create", [call _mkName, [_mid select 0, _mid select 1, 0], _ch]] call comspec_atak_native_fnc_markerNet;
                if (_l isNotEqualTo "") then {
                    _l setMarkerTypeLocal "mil_dot";
                    _l setMarkerSizeLocal [0.5, 0.5];
                    _l setMarkerTextLocal _name;
                    _l setMarkerColor (["ColorGreen", "ColorBlue"] select (_cls isEqualTo "MSR"));
                    _mk pushBack _l;
                };
            };
        } forEach (_b get "axes");
        {
            _x params ["", "_lbl", "_p"];
            private _m = ["create", [call _mkName, [_p select 0, _p select 1, 0], _ch]] call comspec_atak_native_fnc_markerNet;
            if (_m isNotEqualTo "") then {
                _m setMarkerTypeLocal "mil_triangle";
                _m setMarkerSizeLocal [0.6, 0.6];
                _m setMarkerTextLocal _lbl;
                _m setMarkerColor "ColorYellow";
                _mk pushBack _m;
            };
        } forEach (_b get "cps");
        missionNamespace setVariable ["COMSPEC_MarkerEhMuted", ((missionNamespace getVariable ["COMSPEC_MarkerEhMuted", 1]) - 1) max 0];
        if ((count _mk) isEqualTo 0) exitWith { ["WARNING", "Marqueurs refusés sur ce canal", 3, 20] call comspec_atak_native_fnc_notify; };
        if (_ptsOnly) then { ["INFO", format ["Plus de %1 zones : seules leurs étiquettes vont sur la carte d'Arma et Athena", _cap], 4, 20] call comspec_atak_native_fnc_notify; };
        _b set ["mk", _mk];
        // Site Athena : seulement si le plan est partagé (sinon ses marqueurs ne vont qu'au canal, sans le site).
        if ((_b get "scope") > 0) then {
            { [_x] call comspec_atak_native_fnc_markerWeb; } forEach _mk;
        } else {
            [{ { ["touch", [_x, false]] call comspec_atak_native_fnc_markerNet; } forEach _this; }, _mk, 0.5] call CBA_fnc_waitAndExecute;
        };
    };
    default { nil };
};
