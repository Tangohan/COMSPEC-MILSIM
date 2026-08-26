/*
    Remonte une IA ennemie vers le poste ATAK (losange hostile).
    Uniquement si le chef de mission a demandé l’affichage.
*/
params [
    ["_unit", objNull, [objNull]]
];
if (!hasInterface) exitWith { false };
if (isNull _unit || {!alive _unit}) exitWith { false };
if (isPlayer _unit) exitWith { false };
if (
    !isNil "comspec_overwatch_connect_fnc_shouldSkipEnemyAiTransmit"
    && { [_unit] call comspec_overwatch_connect_fnc_shouldSkipEnemyAiTransmit }
) exitWith { false };
if (!(missionNamespace getVariable ["COMSPEC_AtakShowEnemyAi", false])) exitWith { false };
if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith { false };
if (missionNamespace getVariable ["COMSPEC_DisconnectSent", false]) exitWith { false };

private _pos = getPosWorld _unit;
if ((abs (_pos select 0) < 1) && { abs (_pos select 1) < 1 }) exitWith { false };

private _last = _unit getVariable ["COMSPEC_EnemyTrackLastAt", -1e9];
if ((diag_tickTime - _last) < 3) exitWith { false };
_unit setVariable ["COMSPEC_EnemyTrackLastAt", diag_tickTime, false];

private _fnc_num = { (_this select 0) toFixed (_this select 1) };
private _fnc_readable = {
    params ["_s"];
    if (!(_s isEqualType "")) then { _s = str _s };
    _s = trim _s;
    if (_s isEqualTo "") exitWith { false };
    if ((toLower _s) find "error:" == 0) exitWith { false };
    if ((toLower _s) in ["error", "grpnull", "unknown", "inconnu"]) exitWith { false };
    true
};

private _enyId = _unit getVariable ["COMSPEC_EnemyTrackId", ""];
if (!(_enyId isEqualType "")) then { _enyId = str _enyId };
_enyId = trim _enyId;
if (_enyId isEqualTo "" || {(toLower _enyId) find "eny-" != 0}) then {
    _enyId = format ["ENY-%1", ((netId _unit) splitString ":") joinString "-"];
    _unit setVariable ["COMSPEC_EnemyTrackId", _enyId, true];
};
_enyId = (_enyId splitString """" joinString "");

private _callSign = "";
private _custom = _unit getVariable ["COMSPEC_EnemyCallsign", ""];
if (!(_custom isEqualType "")) then { _custom = str _custom };
_custom = trim _custom;
if ([_custom] call _fnc_readable) then {
    _callSign = _custom;
} else {
    private _gid = trim (groupId (group _unit));
    private _n = name _unit;
    if (!(_n isEqualType "")) then { _n = str _n };
    _n = trim _n;
    if ([_gid] call _fnc_readable) then {
        _callSign = _gid;
    } else {
        if ([_n] call _fnc_readable) then { _callSign = _n; };
    };
};
if (_callSign isEqualTo "") then { _callSign = "Contact ennemi"; };
private _escCs = (_callSign splitString """" joinString "");

private _veh = vehicle _unit;
private _inVeh = _veh isNotEqualTo _unit;
private _heading = getDir (if (_inVeh) then { _veh } else { _unit });
private _aslZ = (getPosASL (if (_inVeh) then { _veh } else { _unit })) select 2;

private _groupName = trim (groupId (group _unit));
if (!(_groupName isEqualType "")) then { _groupName = ""; };
_groupName = (_groupName splitString """" joinString "");

private _role = "Contact ennemi";
private _health = "stable";
if (!isNil "comspec_overwatch_connect_fnc_getMedicalState") then {
    private _med = [_unit] call comspec_overwatch_connect_fnc_getMedicalState;
    if (_med isEqualType "" && {_med isNotEqualTo ""}) then {
        _health = (_med splitString "|") select 0;
    };
};
if (!alive _unit) then { _health = "dead" };
_health = (_health splitString """" joinString "");

private _platform = "INFANTRY";
if (!isNil "comspec_overwatch_connect_fnc_bftPlatform") then {
    _platform = [_unit] call comspec_overwatch_connect_fnc_bftPlatform;
};
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
    "{""enemy_ai"":true,""is_ai"":true,""source"":""enemy"",""enemy_id"":""%1"",""display_name"":""%2"",""side"":""EAST"",""affiliation"":""hostile"",""show_enemy_ai"":true,""in_vehicle"":%3,""platform"":""%4"",""vehicle"":""%5"",""vehicle_name"":""%6"",""military_id"":""""}",
    _enyId,
    _escCs,
    if (_inVeh) then { "true" } else { "false" },
    _platform,
    _vehType,
    _vehName
];

"COMSPECExtension" callExtension ["UpdatePosition", [
    [_pos select 0, 2] call _fnc_num,
    [_pos select 1, 2] call _fnc_num,
    [_heading, 2] call _fnc_num,
    _enyId,
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
