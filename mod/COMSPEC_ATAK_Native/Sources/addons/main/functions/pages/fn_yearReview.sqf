/*
    Bilan de l'année (app « Bilan de l'année », fn_pageYearReview) : les écrans de la rétrospective du site (/retrospective).
    Source : GetYearReview de la DLL (GET /api/atak/year-review), en tâche de fond par fn_extAwait, une lecture à la fois.
    Circuit de l'ORBAT (fn_bftOrbat) : rien ne part sans liaison Athena autorisée (COMSPEC_AthenaReady, licence acceptée) ni
    sans COMSPEC Link ; la DLL refuse de toute façon avant authentification. Le bilan reçu reste dans uiNamespace jusqu'à la
    fin du jeu (rien sur le disque). Textes des écrans : en français, comme sur le site.
    Params : [action, argument]
      "load" forcer   relit l'API si l'échéance est passée (forcer : au plus toutes les 10 s) ; ne bloque jamais
      "receive" texte (tâche de fond) traite la réponse de la DLL
      "store"         table : state (IDLE | LOADING | OK | EMPTY | NOLINK | FORBID | DOWN | DLL | OFF), year, tenant, partial, slides, okAt
                        slides : liste de tables (key, tone, kicker, name, title, lead, big, big_label, persona, stats [[libellé, valeur]],
                        bars [[lettre, pourcentage]], boards [[titre, [[rang, nom, valeur]]]])
*/
params [["_act", ""], ["_arg", ""]];
private _store = uiNamespace getVariable "COMSPEC_ATAK_YearReview";
if (isNil "_store") then {
    _store = createHashMapFromArray [["state", "IDLE"], ["year", 0], ["tenant", ""], ["partial", false], ["slides", []], ["okAt", -1e9]];
    uiNamespace setVariable ["COMSPEC_ATAK_YearReview", _store];
};
// "store" rend la table elle-même : la fonction finit par true, qui masquerait la valeur du switch.
if (_act isEqualTo "store") exitWith { _store };
switch (_act) do {
    case "load": {
        if (!hasInterface) exitWith {};
        // Autre communauté : le bilan de la précédente n'est jamais montré.
        private _tenant = missionNamespace getVariable ["comspec_tenant_name", ""];
        if !(_tenant isEqualType "") then { _tenant = ""; };
        if (_tenant isNotEqualTo "" && {(_store get "tenant") isNotEqualTo ""} && {(_store get "tenant") isNotEqualTo _tenant}) then {
            _store set ["slides", []]; _store set ["okAt", -1e9]; _store set ["state", "IDLE"];
        };
        if !([] call comspec_atak_native_fnc_bridge) exitWith { _store set ["state", "OFF"]; };
        // Licence : aucune requête vers Athena avant l'autorisation.
        if !(missionNamespace getVariable ["COMSPEC_AthenaReady", false]) exitWith { _store set ["state", "NOLINK"]; };
        if (diag_tickTime - (missionNamespace getVariable ["COMSPEC_ATAK_YearBusyAt", -1e9]) < 60) exitWith {};
        private _force = _arg isEqualTo true && {diag_tickTime - (missionNamespace getVariable ["COMSPEC_ATAK_YearAskedAt", -1e9]) > 10};
        if (!_force && {diag_tickTime < (missionNamespace getVariable ["COMSPEC_ATAK_YearNext", -1e9])}) exitWith {};
        missionNamespace setVariable ["COMSPEC_ATAK_YearBusyAt", diag_tickTime];
        missionNamespace setVariable ["COMSPEC_ATAK_YearAskedAt", diag_tickTime];
        if ((_store get "state") isNotEqualTo "OK") then { _store set ["state", "LOADING"]; };
        [] spawn {
            private _r = ["COMSPECExtension", "GetYearReview", []] call comspec_atak_native_fnc_extAwait;
            if (_r isEqualType []) then { _r = _r param [0, ""]; };
            if !(_r isEqualType "") then { _r = ""; };
            ["receive", _r] call comspec_atak_native_fnc_yearReview;
            missionNamespace setVariable ["COMSPEC_ATAK_YearBusyAt", -1e9];
        };
    };
    case "receive": {
        private _next = { missionNamespace setVariable ["COMSPEC_ATAK_YearNext", diag_tickTime + _this]; };
        if ((_arg select [0, 3]) isNotEqualTo "OK|") exitWith {
            private _code = if ((_arg select [0, 4]) isEqualTo "ERR|") then { _arg select [4] } else { "" };
            switch (true) do {
                case (_code isEqualTo "busy"): { 30 call _next; };
                case (_code in ["not_linked", "unauthorized", "no_tenant"]): { _store set ["state", "NOLINK"]; 120 call _next; };
                case (_code isEqualTo "forbidden"): { _store set ["state", "FORBID"]; 300 call _next; };
                case (_code isEqualTo "uplink_hold"): { _store set ["state", "OFF"]; 60 call _next; };
                case (_code isEqualTo "" || {(_code select [0, 7]) isEqualTo "unknown"}): { _store set ["state", "DLL"]; 300 call _next; };
                default { _store set ["state", "DOWN"]; 60 call _next; };
            };
        };
        (_arg splitString "|") params ["", ["_n", "0"]];
        private _txt = "";
        private _ok = true;
        for "_i" from 0 to (((parseNumber _n) min 40) - 1) do {
            private _c = ["COMSPECExtension", "GetYearReviewChunk", [str _i]] call comspec_overwatch_connect_fnc_extDirect;
            if (_c isEqualType []) then { _c = _c param [0, ""]; };
            if !(_c isEqualType "" && {(_c select [0, 3]) isEqualTo "OK|"}) exitWith { _ok = false; };
            _txt = _txt + (_c select [3]);
        };
        private _root = if (_ok && {_txt isNotEqualTo ""}) then { parseSimpleArray _txt } else { [] };
        if (isNil "_root" || {!(_root isEqualType [])} || {(count _root) isEqualTo 0}) exitWith { _store set ["state", "DOWN"]; 60 call _next; };
        private _h = {
            params ["_a"];
            if !(_a isEqualType []) exitWith { createHashMap };
            createHashMapFromArray (_a select { _x isEqualType [] && {(count _x) isEqualTo 2} && {(_x select 0) isEqualType ""} })
        };
        private _m = [_root] call _h;
        private _str = { params ["_o", "_k", ["_max", 120]]; private _v = _o getOrDefault [_k, ""]; if (_v isEqualType "") then { _v select [0, _max] } else { "" } };
        private _arr = { params ["_o", "_k"]; private _v = _o getOrDefault [_k, []]; [[], _v] select (_v isEqualType []) };
        private _slides = (([_m, "slides"] call _arr) select [0, 24]) apply {
            private _s = [_x] call _h;
            private _stats = (([_s, "stats"] call _arr) select [0, 8]) select { _x isEqualType [] && {(count _x) >= 2} } apply { [str (_x select 0), str (_x select 1)] };
            private _bars = (([_s, "bars"] call _arr) select [0, 12]) select { _x isEqualType [] && {(count _x) >= 2} } apply { [str (_x select 0), 0 max (parseNumber str (_x select 1)) min 100] };
            private _boards = (([_s, "boards"] call _arr) select [0, 4]) select { _x isEqualType [] && {(count _x) >= 2} && {(_x select 0) isEqualType ""} && {(_x select 1) isEqualType []} } apply {
                [_x select 0, ((_x select 1) select [0, 6]) select { _x isEqualType [] && {(count _x) >= 3} } apply { [parseNumber str (_x select 0), str (_x select 1), str (_x select 2)] }]
            };
            createHashMapFromArray [
                ["key", [_s, "key", 24] call _str], ["tone", [_s, "tone", 12] call _str], ["kicker", [_s, "kicker"] call _str],
                ["name", [_s, "name", 60] call _str], ["title", [_s, "title"] call _str], ["lead", [_s, "lead", 300] call _str],
                ["big", [_s, "big", 24] call _str], ["big_label", [_s, "big_label"] call _str], ["persona", [_s, "persona", 60] call _str],
                ["stats", _stats], ["bars", _bars], ["boards", _boards]
            ]
        };
        private _year = _m getOrDefault ["year", 0];
        if !(_year isEqualType 0) then { _year = 0; };
        _store set ["year", _year];
        _store set ["tenant", [_m, "tenant", 60] call _str];
        _store set ["partial", (_m getOrDefault ["partial", false]) isEqualTo true];
        _store set ["slides", _slides];
        _store set ["okAt", diag_tickTime];
        // État NOT_PUBLISHED du site : rien à montrer pour l'instant.
        _store set ["state", ["OK", "EMPTY"] select ((count _slides) isEqualTo 0)];
        600 call _next;
    };
    default {};
};
true
