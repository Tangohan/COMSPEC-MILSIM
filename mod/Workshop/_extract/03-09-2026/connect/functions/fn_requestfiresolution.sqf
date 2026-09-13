// Request fire solution from C2. Args: [missionId, gunX, gunY, gunZ, targetX, targetY, targetZ, ammoType, fireUnitId (optional)]
// Returns: "OK|{json}" or "ERR|..."
params [
    ["_missionId", "mission_1_map_1", [""]],
    ["_gunX", 0, [0]],
    ["_gunY", 0, [0]],
    ["_gunZ", 0, [0]],
    ["_targetX", 0, [0]],
    ["_targetY", 0, [0]],
    ["_targetZ", 0, [0]],
    ["_ammoType", "HE", [""]],
    ["_fireUnitId", 0, [0]]
];

private _args = [_missionId, str _gunX, str _gunY, str _gunZ, str _targetX, str _targetY, str _targetZ, _ammoType];
if (_fireUnitId > 0) then {
    _args pushBack (str _fireUnitId);
};
private _raw = ["COMSPECExtension" callExtension ["FireSupport.Request", _args]] call comspec_overwatch_connect_fnc_extResult;
if (_raw isEqualTo "") exitWith { [] };
private _parts = _raw splitString "|";
if (count _parts < 2) exitWith { [] };
if ((_parts select 0) != "OK") exitWith { [] };

private _jsonStr = _parts select 1;
_jsonStr = (_jsonStr splitString "_") joinString "|";
_jsonStr = (_jsonStr splitString " ") joinString "\n";
[_jsonStr] call comspec_overwatch_connect_fnc_receiveFireSolution
