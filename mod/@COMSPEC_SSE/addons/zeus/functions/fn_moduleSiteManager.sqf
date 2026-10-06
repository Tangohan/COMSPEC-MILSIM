/*
    Module « Gestionnaire de site SSE » : complétude et triage autour du module
    (sans révéler de position aux joueurs).
*/
params [
    ["_logic", objNull, [objNull]],
    ["_units", [], [[]]],
    ["_activated", true, [true]]
];

private _ctx = [_logic, _units, _activated] call comspec_sse_fnc_moduleContext;
if !(_ctx get "run") exitWith { true };
if (!hasInterface) exitWith { deleteVehicle _logic; true };

private _apply = {
    params ["_pos", "_radius"];
    private _list = [_pos, _radius] call comspec_sse_fnc_listSiteEntities;
    if (_list isEqualTo []) exitWith {
        [format ["Aucun élément SSE dans un rayon de %1 m.", _radius], "warn"] call comspec_sse_fnc_zeusNotify;
    };
    private _pct = [_pos, _radius] call comspec_sse_fnc_siteCompleteness;
    private _tri = [_pos, _radius] call comspec_sse_fnc_triageSite;
    private _lines = [format ["Site (%1 m) — complétude %2%3 — %4 élément(s)", _radius, _pct, "%", count _list]];
    {
        _lines pushBack format [
            "• %1 | %2 | %3 | valeur %4",
            _x getOrDefault ["triage", "?"],
            _x getOrDefault ["type", "?"],
            _x getOrDefault ["level", "?"],
            _x getOrDefault ["INTEL_VALUE", 0]
        ];
    } forEach (_tri select [0, (count _tri) min 12]);
    [_lines joinString "\n"] call comspec_sse_fnc_zeusNotify;
};

private _pos = _ctx get "pos";
private _radius = _logic getVariable ["Radius", 60];

if (_ctx get "zeus") then {
    [
        "Gestionnaire de site SSE",
        [["SLIDER", "Rayon (m)", "Zone analysée autour du module.", [10, 300, _radius, 0]]],
        {
            params ["_values", "_args"];
            [_args select 0, _values select 0] call (_args select 1);
        },
        [_pos, _apply]
    ] call comspec_sse_fnc_uiForm;
} else {
    [_pos, _radius] call _apply;
};

deleteVehicle _logic;
true
