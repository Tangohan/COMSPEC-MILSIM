/*
    Module « Compte rendu SSE (AAR) + export du graphe ».
*/
params [
    ["_logic", objNull, [objNull]],
    ["_units", [], [[]]],
    ["_activated", true, [true]]
];

private _ctx = [_logic, _units, _activated] call comspec_sse_fnc_moduleContext;
if !(_ctx get "run") exitWith { true };

private _apply = {
    params ["_pos", "_radius", "_export"];
    private _lines = [_pos, _radius] call comspec_sse_fnc_afterActionReport;
    private _msg = if (_lines isEqualType [] && {_lines isNotEqualTo []}) then { _lines joinString "\n" } else { "Compte rendu généré." };
    if (_export) then {
        private _graph = [] call comspec_sse_fnc_exportMissionGraph;
        _msg = _msg + format ["\nGraphe exporté : %1 entité(s).", count (_graph getOrDefault ["entities", []])];
    };
    [_msg] call comspec_sse_fnc_zeusNotify;
};

private _pos = _ctx get "pos";
private _radius = _logic getVariable ["Radius", 150];
private _export = _logic getVariable ["ExportGraph", true];

if (_ctx get "zeus") then {
    [
        "Compte rendu SSE (AAR)",
        [
            ["SLIDER", "Rayon (m)", "Zone prise en compte pour la complétude.", [25, 1000, _radius, 0]],
            ["CHECK", "Exporter le graphe", "Exporte le graphe de renseignement de la mission (journal / Athena).", _export]
        ],
        {
            params ["_values", "_args"];
            ([_args select 0] + _values) call (_args select 1);
        },
        [_pos, _apply]
    ] call comspec_sse_fnc_uiForm;
} else {
    [_pos, _radius, _export] call _apply;
};

deleteVehicle _logic;
true
