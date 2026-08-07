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
    _x setVariable ["comspec_sse_data", nil, true];
    _x setVariable ["comspec_sse_enabled", false, true];
    _x setVariable ["comspec_sse_searchable", false, true];
    _x setVariable ["comspec_sse_clusterId", nil, true];
} forEach _targets;

if (hasInterface) then {
    hint format ["Données SSE effacées (%1)", count _targets];
};

deleteVehicle _logic;
true
