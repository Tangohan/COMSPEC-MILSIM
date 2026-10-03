/* Actions de l'app Médical. Params : [action, valeur, id d'alerte] */
params ["_action", ["_v", ""], ["_id", ""]];
private _save = {
    private _m = uiNamespace getVariable ["COMSPEC_ATAK_Medevac", createHashMap];
    private _form = uiNamespace getVariable ["COMSPEC_ATAK_Form", createHashMap];
    { if (_x in _form) then { _m set [_x, [_x] call comspec_atak_native_fnc_formValue]; }; } forEach ["mLz", "mRem"];
    uiNamespace setVariable ["COMSPEC_ATAK_Medevac", _m];
    _m
};
private _render = { [{ ["MEDICAL"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; };
switch (_action) do {
    case "set": { (call _save) set [_v, _id]; call _render; };
    case "lzHere": { (call _save) set ["mLz", [getPosASL player, 8] call comspec_atak_native_fnc_gridRef]; call _render; };
    case "medevac": {
        private _m = call _save;
        if (isNil "comspec_overwatch_connect_fnc_requestMEDEVAC") exitWith { ["WARNING", "MEDEVAC : COMSPEC Overwatch requis", 4, 30] call comspec_atak_native_fnc_notify; };
        private _n = { params ["_k", "_d"]; (parseNumber (_m getOrDefault [_k, _d])) max 0 };
        private _grid = (_m getOrDefault ["mLz", ""]) splitString " " joinString "";
        private _pos = getPos player;
        if (_grid isNotEqualTo "") then { private _p = ([_grid] call BIS_fnc_gridToPos) param [0, []]; if ((count _p) >= 2) then { _pos = [_p select 0, _p select 1, 0]; }; };
        private _args = [_m getOrDefault ["prio", "URGENT"], ["mT1", "1"] call _n, ["mT2", "0"] call _n, ["mT3", "0"] call _n,
            _m getOrDefault ["sec", "NO_ENEMY"], _m getOrDefault ["mark", "SMOKE"], _m getOrDefault ["col", "GREEN"], _pos, _m getOrDefault ["mRem", ""], _grid];
        _args spawn {
            private _ok = _this call comspec_overwatch_connect_fnc_requestMEDEVAC;
            if (_ok isEqualType true && {_ok}) then { ["SUCCESS", "MEDEVAC demandé · LZ marquée", 5, 50] call comspec_atak_native_fnc_notify; };
        };
    };
    case "locate": {
        if (_v isEqualTo "") exitWith { ["WARNING", "Pas de grille pour cette alerte", 3, 20] call comspec_atak_native_fnc_notify; };
        private _p = [_v] call BIS_fnc_gridToPos;
        private _pos = _p param [0, []];
        if ((count _pos) < 2) exitWith {};
        ["MAP"] call comspec_atak_native_fnc_navigate;
        [{ [_this, 0.03] call comspec_atak_native_fnc_mapCenter; }, [_pos select 0, _pos select 1]] call CBA_fnc_execNextFrame;
    };
    case "triage": {
        [_v, _id] spawn {
            params ["_v", "_id"];
            [_v, _id] call comspec_overwatch_connect_fnc_medicalTriage;
            [] call comspec_overwatch_connect_fnc_pollMedicalAlerts;
            [{ ["MEDICAL"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
        };
    };
};
true
