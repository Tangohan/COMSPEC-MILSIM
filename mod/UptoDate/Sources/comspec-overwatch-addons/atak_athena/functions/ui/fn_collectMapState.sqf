/*
    DATA → STATE. Remplit COMSPEC_MapState (pas d’UI).
*/
if (!hasInterface) exitWith {};

private _units = [];
private _now = time;
{
    if (!alive _x) then { continue };
    private _pliAt = _x getVariable ["COMSPEC_PliAt", -1];
    if (_pliAt < 0 && {isPlayer _x}) then { _pliAt = _now };
    private _age = if (_pliAt < 0) then { 999 } else { _now - _pliAt };
    private _med = [_x] call comspec_overwatch_atak_athena_fnc_formatUnitStatus;
    private _radio = ["none", "", "", false, false, "", false];
    if (!isNil "comspec_overwatch_connect_fnc_getRadioTxState") then {
        _radio = [_x] call comspec_overwatch_connect_fnc_getRadioTxState;
    };
    private _cs = "";
    if (!isNil "comspec_overwatch_atak_athena_fnc_athena_bftUnitLabel") then {
        _cs = [_x] call comspec_overwatch_atak_athena_fnc_athena_bftUnitLabel;
    };
    if (_cs isEqualTo "") then { _cs = name _x; };
    private _role = "";
    if (!isNil "comspec_overwatch_connect_fnc_getUnitRole") then {
        _role = [_x] call comspec_overwatch_connect_fnc_getUnitRole;
    };
    private _grp = "";
    if (!isNil "comspec_overwatch_connect_fnc_inGameGroupLabel") then {
        _grp = [_x] call comspec_overwatch_connect_fnc_inGameGroupLabel;
    };
    _units pushBack createHashMapFromArray [
        ["unit", _x],
        ["callsign", _cs],
        ["role", _role],
        ["group", _grp],
        ["pos", getPosASLVisual _x],
        ["heading", round (direction _x)],
        ["speed", round (abs (speed _x))],
        ["pli_age", _age],
        ["stale", _age >= 30],
        ["medical", _med],
        ["radio", _radio]
    ];
} forEach (allPlayers + (units group player));

private _layers = missionNamespace getVariable ["COMSPEC_MapLayers", createHashMap];
if (!(_layers isEqualType createHashMap) || {count _layers == 0}) then {
    _layers = createHashMapFromArray [
        ["units", true], ["vehicles", true], ["objectives", true],
        ["player_markers", true], ["athena", true], ["intel", true],
        ["photos", true], ["jtac", true], ["cas", true], ["sigint", true],
        ["logistics", true]
    ];
    missionNamespace setVariable ["COMSPEC_MapLayers", _layers, false];
};

private _filter = missionNamespace getVariable ["COMSPEC_MapFilter", "ALL"];
private _air = [];
{
    if (!alive _x) then { continue };
    if (!(_x isKindOf "Air")) then { continue };
    private _cs = vehicleVarName _x;
    if (_cs isEqualTo "") then { _cs = typeOf _x };
    private _st = "ON STATION";
    if (fuel _x < 0.08) then { _st = "WINCHESTER" };
    if ((getPosATL _x select 2) < 5) then { _st = "RTB" };
    _air pushBack [_cs, getPosATL _x, round ((getPosATL _x) select 2), _st];
} forEach vehicles;
private _laser = player getVariable ["ace_laser_code", ""];
if (_laser isEqualTo "" && {!isNil "ace_laser_code"}) then { _laser = ace_laser_code };
missionNamespace setVariable ["COMSPEC_MapAir", _air, false];
missionNamespace setVariable ["COMSPEC_MapLaser", _laser, false];

private _state = createHashMapFromArray [
    ["units", _units],
    ["layers", _layers],
    ["filter", _filter],
    ["air", _air],
    ["laser", _laser],
    ["workspace", missionNamespace getVariable ["COMSPEC_MapWorkspace", "MISSION"]],
    ["tool", missionNamespace getVariable ["COMSPEC_MapActiveTool", ""]],
    ["selected", missionNamespace getVariable ["COMSPEC_MapSelected", objNull]],
    ["updated_at", _now]
];
missionNamespace setVariable ["COMSPEC_MapState", _state, false];
_state
