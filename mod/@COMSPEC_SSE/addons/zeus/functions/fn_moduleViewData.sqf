/*
    Module « Consulter les données SSE » (vérité complète, réservé Zeus).
*/
params [
    ["_logic", objNull, [objNull]],
    ["_units", [], [[]]],
    ["_activated", true, [true]]
];

private _ctx = [_logic, _units, _activated] call comspec_sse_fnc_moduleContext;
if !(_ctx get "run") exitWith { true };
if (!hasInterface) exitWith { deleteVehicle _logic; true };

private _targets = _ctx get "targets";
if (_targets isEqualTo []) exitWith {
    ["Consulter : posez le module sur une entité SSE.", "error"] call comspec_sse_fnc_zeusNotify;
    deleteVehicle _logic;
    false
};

private _blocks = [];
{
    private _data = [_x] call comspec_sse_fnc_getData;
    private _label = if (_x isKindOf "CAManBase") then { name _x } else { getText (configOf _x >> "displayName") };
    if (isNil "_data" || {!(_data isEqualType [])}) then {
        _blocks pushBack format ["%1 — aucune donnée SSE", _label];
    } else {
        private _identity = [_x, "identity"] call comspec_sse_fnc_getSection;
        private _name = if (!isNil "_identity" && {_identity isEqualType createHashMap}) then { _identity getOrDefault ["name", "-"] } else { "-" };
        _blocks pushBack format [
            "%1\nUID %2 · %3 · %4\nÉtat : %5 · Nom : %6\nLiens : %7 · Graine : %8",
            _label,
            [_data, "uid", "?"] call comspec_sse_fnc_getPair,
            [_data, "type", "?"] call comspec_sse_fnc_getPair,
            [_data, "profile", "?"] call comspec_sse_fnc_getPair,
            [_data, "state", "?"] call comspec_sse_fnc_getPair,
            _name,
            count ([_x] call comspec_sse_fnc_getLinks),
            [_data, "seed", -1] call comspec_sse_fnc_getPair
        ];
    };
} forEach (_targets select [0, 4]);

if (count _targets > 4) then { _blocks pushBack format ["(+%1 autre(s))", count _targets - 4]; };
[_blocks joinString "\n\n"] call comspec_sse_fnc_zeusNotify;

deleteVehicle _logic;
true
