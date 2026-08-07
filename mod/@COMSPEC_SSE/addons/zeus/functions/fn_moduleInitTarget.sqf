params [
    ["_logic", objNull, [objNull]],
    ["_units", [], [[]]],
    ["_activated", true, [true]]
];

if (!_activated) exitWith { true };

private _targets = synchronizedObjects _logic;
if (count _targets == 0) then {
    private _attached = _logic getVariable ["bis_fnc_curatorAttachObject_object", objNull];
    if (!isNull _attached) then { _targets = [_attached]; };
};

{
    [_x, "OBJECT"] call comspec_sse_fnc_makeSearchable;
} forEach _targets;

if (hasInterface) then {
    hint format ["SSE initialisé sur %1 cible(s)", count _targets];
};

deleteVehicle _logic;
true
