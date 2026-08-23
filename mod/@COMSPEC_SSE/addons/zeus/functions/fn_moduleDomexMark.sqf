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

private _overrides = createHashMapFromArray [
    ["enabled", true],
    ["node_id", _logic getVariable ["NodeId", ""]],
    ["device_type", _logic getVariable ["DeviceType", "ordinateur"]],
    ["owner", _logic getVariable ["Owner", ""]],
    ["organization", _logic getVariable ["Organization", ""]],
    ["network", _logic getVariable ["Network", ""]],
    ["security", _logic getVariable ["Security", "moyenne"]],
    ["profile", _logic getVariable ["Profile", "generique"]],
    ["duration", str (_logic getVariable ["Duration", 180])],
    ["access_remote", _logic getVariable ["AccessRemote", false]],
    ["packet_type", _logic getVariable ["PacketType", ""]],
    ["packet_text", _logic getVariable ["PacketText", ""]],
    ["packet_quality", _logic getVariable ["PacketQuality", "complet"]],
    ["packet_entities", _logic getVariable ["PacketEntities", ""]]
];

private _n = 0;
{
    if !(_x isKindOf "CAManBase") then {
        [_x, _overrides] call comspec_sse_fnc_domexApplyObject;
        _n = _n + 1;
    };
} forEach _targets;

if (hasInterface) then {
    hint format ["Intelligence numérique posée sur %1 objet(s)", _n];
};

deleteVehicle _logic;
true
