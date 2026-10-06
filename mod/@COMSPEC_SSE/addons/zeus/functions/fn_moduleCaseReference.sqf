/*
    Module « Référence de dossier SSE » (nouveau, V0.8).
    Fixe la référence de dossier utilisée pour les transmissions (Athena).
    Même rôle que « Dossier SSE actif » d'Overwatch, pour le pack autonome.
*/
params [
    ["_logic", objNull, [objNull]],
    ["_units", [], [[]]],
    ["_activated", true, [true]]
];

private _ctx = [_logic, _units, _activated] call comspec_sse_fnc_moduleContext;
if !(_ctx get "run") exitWith { true };

private _apply = {
    params ["_ref"];
    _ref = toUpper (trim _ref);
    // Diffusé à tous (et aux joueurs qui rejoignent ensuite).
    [_ref] remoteExecCall ["comspec_sse_fnc_setCaseReference", 0, "comspec_sse_caseReferenceJIP"];
    if (_ref isEqualTo "") then {
        ["Référence de dossier effacée : les fiches partent sans dossier.", "warn"] call comspec_sse_fnc_zeusNotify;
    } else {
        [format ["Dossier SSE actif : %1", _ref]] call comspec_sse_fnc_zeusNotify;
    };
};

private _ref = _logic getVariable ["Reference", ""];
if (_ref isEqualTo "" && {!isNil "comspec_sse_fnc_getCaseReference"}) then {
    _ref = [] call comspec_sse_fnc_getCaseReference;
};

if (_ctx get "zeus") then {
    [
        "Référence de dossier SSE",
        [["EDIT", "Référence", "Ex. SSE-2026-0007. Vide = efface la référence active.", _ref]],
        {
            params ["_values", "_args"];
            [_values select 0] call (_args select 0);
        },
        [_apply],
        "Toutes les fiches transmises ensuite sont classées dans ce dossier."
    ] call comspec_sse_fnc_uiForm;
} else {
    if ((trim _ref) isNotEqualTo "") then { [_ref] call _apply; };
};

deleteVehicle _logic;
true
