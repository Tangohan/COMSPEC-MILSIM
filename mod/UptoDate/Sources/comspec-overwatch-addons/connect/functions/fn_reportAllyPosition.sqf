/*
    Remonte une IA comme unité alliée ATAK (pas comme un téléphone).
    Ne doit pas réutiliser l’identité Steam du client relais.
*/
params [
    ["_unit", objNull, [objNull]]
];
if (!hasInterface) exitWith { false };
if (isNull _unit || {!alive _unit}) exitWith { false };
if (isPlayer _unit) exitWith { false };
if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith { false };
if (missionNamespace getVariable ["COMSPEC_DisconnectSent", false]) exitWith { false };

private _pos = getPosWorld _unit;
if ((abs (_pos select 0) < 1) && { abs (_pos select 1) < 1 }) exitWith { false };

private _last = _unit getVariable ["COMSPEC_AllyTrackLastAt", -1e9];
if ((diag_tickTime - _last) < 6) exitWith { false };
_unit setVariable ["COMSPEC_AllyTrackLastAt", diag_tickTime, false];

private _fnc_num = { (_this select 0) toFixed (_this select 1) };
private _callSign = [_unit] call comspec_overwatch_connect_fnc_allyTrackCallsign;
private _veh = vehicle _unit;
private _inVeh = _veh isNotEqualTo _unit;
private _heading = getDir (if (_inVeh) then { _veh } else { _unit });
private _aslZ = (getPosASL (if (_inVeh) then { _veh } else { _unit })) select 2;
private _side = side group _unit;
private _sideStr = switch (_side) do {
    case east: { "EAST" };
    case resistance: { "GUER" };
    case civilian: { "CIV" };
    default { "WEST" };
};
private _affiliation = switch (_side) do {
    case east: { "hostile" };
    case resistance: { "unknown" };
    case civilian: { "neutral" };
    default { "friend" };
};
private _escCs = (_callSign splitString """" joinString "");
private _groupName = trim (groupId (group _unit));
if (!(_groupName isEqualType "")) then { _groupName = ""; };
_groupName = (_groupName splitString """" joinString "");

private _role = [_unit] call comspec_overwatch_connect_fnc_getUnitRole;
if (!(_role isEqualType "")) then { _role = str _role };
_role = trim _role;
if (_role isEqualTo "" || {(toLower _role) in ["operator", "unknown", "inconnu"]}) then {
    _role = "Unité alliée";
};
_role = (_role splitString """" joinString "");

private _health = "stable";
if (!isNil "comspec_overwatch_connect_fnc_getMedicalState") then {
    private _med = [_unit] call comspec_overwatch_connect_fnc_getMedicalState;
    if (_med isEqualType "" && {_med isNotEqualTo ""}) then {
        _health = (_med splitString "|") select 0;
    };
};
if (!alive _unit) then { _health = "dead" };
_health = (_health splitString """" joinString "");

private _allyId = _unit getVariable ["COMSPEC_AllyTrackId", ""];
if (!(_allyId isEqualType "")) then { _allyId = str _allyId };
if (_allyId isEqualTo "") then {
    _allyId = format ["ALLY-%1", ((netId _unit) splitString ":") joinString "-"];
    _unit setVariable ["COMSPEC_AllyTrackId", _allyId, true];
};
_allyId = (_allyId splitString """" joinString "");

private _platform = [_unit] call comspec_overwatch_connect_fnc_bftPlatform;
private _vehType = "";
private _vehName = "";
if (_inVeh) then {
    _vehType = typeOf _veh;
    _vehName = getText (configFile >> "CfgVehicles" >> _vehType >> "displayName");
    if (_vehName isEqualTo "") then { _vehName = getText (configOf _veh >> "displayName"); };
    _vehType = (_vehType splitString """" joinString "");
    _vehName = (_vehName splitString """" joinString "");
};

private _extra = format [
    "{""ally_ai"":true,""is_ai"":true,""source"":""ally"",""ally_id"":""%1"",""display_name"":""%2"",""side"":""%3"",""affiliation"":""%4"",""in_vehicle"":%5,""platform"":""%6"",""vehicle"":""%7"",""vehicle_name"":""%8"",""military_id"":""""}",
    _allyId,
    _escCs,
    _sideStr,
    _affiliation,
    if (_inVeh) then { "true" } else { "false" },
    _platform,
    _vehType,
    _vehName
];

"COMSPECExtension" callExtension ["UpdatePosition", [
    [_pos select 0, 2] call _fnc_num,
    [_pos select 1, 2] call _fnc_num,
    [_heading, 2] call _fnc_num,
    _allyId,
    _role,
    _health,
    "",
    "",
    "",
    _extra,
    "",
    _groupName,
    [_aslZ, 3] call _fnc_num
]];
true
