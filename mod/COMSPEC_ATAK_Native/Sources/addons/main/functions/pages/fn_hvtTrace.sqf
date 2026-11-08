/*
    Traces civiles d'une HVT : une activité vue dans une app civile (Tinder, UberEats), datée, sur une zone approximative.
    Choix : un relevé du passé, jamais la position du moment. Le serveur note la maille de 1 km où était la cible, puis
    ne publie cette trace qu'après un délai. Le joueur reçoit donc l'heure, la maille et le lieu-dit le plus proche :
    jamais de position exacte ni de suivi en direct. Rien de faux ici, contrairement aux tuyaux de fn_hvtIntel :
    la trace dit où la cible était, à vous de deviner où elle va.
    Désactivé par défaut. Actif par le réglage comspec_atak_native_hvt_trace, par la mission
    (COMSPEC_ATAK_HvtTraceOn en variable publique) ou par le Zeus (app Admin, onglet DEBUG IA). Une cible s'exclut avec
    COMSPEC_ATAK_HvtTrace = false. Réservé au renseignement si comspec_atak_native_hvt_trace_role est coché :
    COMSPEC_ATAK_IntelOperator sur l'unité, ou rôle de mission contenant renseign, intel, s2, g2 ; le Zeus et l'admin aussi.
    Serveur : un contrôle par minute, rien par image. Variable publique COMSPEC_ATAK_HvtTraces :
      [[clé, app, instant (CBA_missionTime), [x, y] centre de la maille, grille, lieu-dit]...], 24 au plus.
    Params : [action, argument]
      "initSrv" (serveur)  "init" (client)  "srvTick"  "on" actif ?  "allowed" ce joueur peut-il les voir ?
      "traces" traces valides    "rows" lignes pour Tinder › DOSSIERS    "mark" [clé, instant]    "new" clé (événement)
*/
params [["_act", ""], ["_arg", []]];
private _on = { (missionNamespace getVariable ["comspec_atak_native_hvt_trace", false]) isEqualTo true || {(missionNamespace getVariable ["COMSPEC_ATAK_HvtTraceOn", false]) isEqualTo true} };
private _cell = 1000;
switch (_act) do {
    case "initSrv": {
        if (isServer) then { [{ ["srvTick"] call comspec_atak_native_fnc_hvtTrace; }, [], 60] call comspec_atak_native_fnc_waitAndExec; };
    };
    case "init": {
        ["comspec_atak_native_hvtTraceNew", { params [["_key", ""]]; ["new", _key] call comspec_atak_native_fnc_hvtTrace; }] call CBA_fnc_addEventHandler;
    };
    case "srvTick": {
        [{ ["srvTick"] call comspec_atak_native_fnc_hvtTrace; }, [], 60] call comspec_atak_native_fnc_waitAndExec;
        private _queue = missionNamespace getVariable ["COMSPEC_ATAK_HvtTraceQ", []];
        missionNamespace setVariable ["COMSPEC_ATAK_HvtTraceQ", _queue];
        private _now = CBA_missionTime;
        if (call _on) then {
            private _next = missionNamespace getVariable ["COMSPEC_ATAK_HvtTraceNext", createHashMap];
            missionNamespace setVariable ["COMSPEC_ATAK_HvtTraceNext", _next];
            private _every = (missionNamespace getVariable ["comspec_atak_native_hvt_trace_every", 5]) max 1;
            private _hvts = allUnits select { !isPlayer _x && {alive _x} && {_x isKindOf "CAManBase"} && {(["kind", _x] call comspec_atak_native_fnc_datingSpecials) isEqualTo "HVT"} && {(_x getVariable ["COMSPEC_ATAK_HvtTrace", true]) isEqualTo true} };
            {
                private _key = ["key", _x] call comspec_atak_native_fnc_datingSpecials;
                if (_now >= (_next getOrDefault [_key, 0])) then {
                    _next set [_key, _now + 60 * _every * (0.7 + random 0.6)];
                    // Le téléphone de la cible n'est pas toujours actif : tous les relevés ne donnent pas une trace.
                    if (random 1 < 0.7) then {
                        private _p = getPosATL _x;
                        private _c = [((floor ((_p select 0) / _cell)) + 0.5) * _cell, ((floor ((_p select 1) / _cell)) + 0.5) * _cell];
                        private _near = nearestLocations [_c, ["NameCityCapital", "NameCity", "NameVillage", "NameLocal"], 4000];
                        private _place = if (_near isEqualTo []) then { "" } else { text (_near select 0) };
                        _queue pushBack [_key, selectRandom ["Tinder", "UberEats"], round _now, _c, mapGridPosition _c, _place];
                    };
                };
            } forEach _hvts;
        };
        // Publication différée : jamais moins de 2 min, même si une mission règle moins.
        private _delay = 60 * ((missionNamespace getVariable ["comspec_atak_native_hvt_trace_delay", 10]) max 2);
        private _ready = _queue select { (_now - (_x select 2)) >= _delay };
        if (_ready isEqualTo []) exitWith {};
        missionNamespace setVariable ["COMSPEC_ATAK_HvtTraceQ", _queue select { (_now - (_x select 2)) < _delay }];
        private _pub = (["traces"] call comspec_atak_native_fnc_hvtTrace) + _ready;
        missionNamespace setVariable ["COMSPEC_ATAK_HvtTraces", _pub select [((count _pub) - 24) max 0], true];
        { ["comspec_atak_native_hvtTraceNew", [_x select 0]] call CBA_fnc_globalEvent; } forEach _ready;
    };
    case "on": { call _on };
    case "allowed": {
        if !(call _on) exitWith { false };
        if !((missionNamespace getVariable ["comspec_atak_native_hvt_trace_role", false]) isEqualTo true) exitWith { true };
        if ((player getVariable ["COMSPEC_ATAK_IntelOperator", false]) isEqualTo true || {[player] call comspec_atak_native_fnc_adminAllowed}) exitWith { true };
        private _r = " " + (toLower (roleDescription player)) + " ";
        (["renseign", "intel", " s2", " g2"] findIf { (_r find _x) >= 0 }) >= 0
    };
    case "traces": {
        private _v = missionNamespace getVariable ["COMSPEC_ATAK_HvtTraces", []];
        if !(_v isEqualType []) exitWith { [] };
        (_v select [0, 24]) select { _x isEqualTypeArray ["", "", 0, [], "", ""] && {(_x select 3) isEqualTypeArray [0, 0]} }
    };
    // Une nouvelle trace : alerte discrète, et un avis local dans Recherchés (fn_wantedAction).
    case "new": {
        if !(_arg isEqualType "" && {["allowed"] call comspec_atak_native_fnc_hvtTrace} && {[player] call comspec_atak_native_fnc_hasDevice}) exitWith {};
        private _name = ["name", _arg] call comspec_atak_native_fnc_hvtIntel;
        ["MESSAGE", format ["Trace civile : %1 repéré dans une app. Voir Tinder › DOSSIERS.", _name], 5, 25, "DATING"] call comspec_atak_native_fnc_notify;
        private _bolo = missionNamespace getVariable ["COMSPEC_ATAK_WantedLocal", []];
        if ((_bolo findIf { (_x param [1, ""]) isEqualTo _arg }) < 0) then {
            _bolo pushBack ["hvt", _arg, "", _name, "", "HVT", "Repéré dans une app civile. Heure et zone : Tinder › DOSSIERS, traces civiles.", ""];
            missionNamespace setVariable ["COMSPEC_ATAK_WantedLocal", _bolo];
        };
        if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "DATING") then {
            [{ ["DATING"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
        };
    };
    case "rows": {
        if !(["allowed"] call comspec_atak_native_fnc_hvtTrace) exitWith { [] };
        private _all = ["traces"] call comspec_atak_native_fnc_hvtTrace;
        if (_all isEqualTo []) exitWith { [] };
        private _esc = { params ["_t"]; [[_t, "<", "&lt;"] call CBA_fnc_replace, ">", "&gt;"] call CBA_fnc_replace };
        private _rows = [["section", "Traces civiles", "Activité vue dans une app, avec du retard. Maille de 1 km, jamais la position du moment."]];
        private _keys = [];
        { _keys pushBackUnique (_x select 0); } forEach _all;
        reverse _keys;
        {
            private _key = _x;
            private _mine = _all select { (_x select 0) isEqualTo _key };
            reverse _mine;
            private _lines = (_mine select [0, 3]) apply {
                _x params ["", "_app", "_at", "", "_grid", "_place"];
                format ["%1 · il y a %2 min · secteur %3%4", _app, round ((CBA_missionTime - _at) / 60), _grid, if (_place isEqualTo "") then { "" } else { format [" · près de %1", [_place] call _esc] }]
            };
            (_mine select 0) params ["", "", "_lastAt"];
            _rows pushBack ["person", "", format ["<t font='COMSPEC_BarlowBold'>%1</t><br/><t size='0.8'>%2</t>", [["name", _key] call comspec_atak_native_fnc_hvtIntel] call _esc, _lines joinString "<br/>"],
                [["ZONE", compile format ["['mark', [%1, %2]] call comspec_atak_native_fnc_hvtTrace;", str _key, _lastAt], false, true, "Marque la maille de la dernière trace sur la carte"]]];
        } forEach _keys;
        _rows
    };
    case "mark": {
        _arg params [["_key", ""], ["_at", 0]];
        private _t = (["traces"] call comspec_atak_native_fnc_hvtTrace) select { (_x select 0) isEqualTo _key && {(_x select 2) isEqualTo _at} };
        if (_t isEqualTo []) exitWith {};
        (_t select 0) params ["", "", "", "_c"];
        private _m = format ["comspec_hvttrace_%1", _key];
        deleteMarkerLocal _m;
        private _z = createMarkerLocal [_m, _c];
        _z setMarkerShapeLocal "RECTANGLE";
        _z setMarkerSizeLocal [_cell / 2, _cell / 2];
        _z setMarkerBrushLocal "Border";
        _z setMarkerColorLocal "ColorOrange";
        ["INFO", "Zone marquée sur la carte", 3, 20] call comspec_atak_native_fnc_notify;
    };
    default { false };
};
