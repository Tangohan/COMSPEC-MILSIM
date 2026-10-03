/* Actions de l'app Médical. Params : [action, valeur, id d'alerte] */
params ["_action", ["_v", ""], ["_id", ""]];
switch (_action) do {
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
