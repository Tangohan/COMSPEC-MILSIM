/*
    Publie le roster des caméras casque / drones vers Athena (panneau Cams).
    Pas de flux RTMP : présence + liaison aux aperçus photo.
*/
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {};
if (!(["video_feeds"] call comspec_overwatch_connect_fnc_isModModuleEnabled)) exitWith {};

private _cs = "";
if (!isNil "comspec_overwatch_connect_fnc_getCallsign") then {
    _cs = [] call comspec_overwatch_connect_fnc_getCallsign;
};
if (_cs isEqualTo "") then { _cs = name player; };
_cs = (_cs splitString """" joinString "");

private _feeds = [];

// --- Caméras casque (liste cTab ou détection équipement) ---
private _helmetList = missionNamespace getVariable ["cTabHcamlist", []];
if (!(_helmetList isEqualType [])) then { _helmetList = []; };
if (_helmetList isEqualTo []) then {
    _helmetList = allPlayers select {
        alive _x && {
            ("ItemcTabHCam" in (items _x + assignedItems _x))
            || {((headgear _x) in (missionNamespace getVariable ["cTab_helmetClass_has_HCam", []]))}
        }
    };
};

{
    if (isNull _x || {!alive _x}) then { continue };
    private _uid = getPlayerUID _x;
    if (_uid isEqualTo "") then { _uid = str _x; };
    private _name = name _x;
    private _feedCs = _name;
    if (_x isEqualTo player && {_cs isNotEqualTo ""}) then { _feedCs = _cs; };
    private _pos = getPosASL _x;
    private _id = format ["helmet:%1", _uid];
    private _label = format ["Caméra casque — %1", _feedCs];
    _feeds pushBack [
        _id,
        "helmet",
        _label,
        _feedCs,
        _uid,
        _pos select 0,
        _pos select 1,
        _pos select 2,
        mapGridPosition _x
    ];
} forEach _helmetList;

// --- Drones / UAV (Drone Ops connecté + véhicules UAV) ---
private _droneState = missionNamespace getVariable ["Iceman_ATAK_DroneOps_state", createHashMap];
private _connectedDrone = objNull;
if (_droneState isEqualType createHashMap) then {
    private _d = _droneState getOrDefault ["drone", objNull];
    if (!isNull _d && {alive _d}) then { _connectedDrone = _d; };
};

private _uavs = vehicles select {
    alive _x && {(_x isKindOf "UAV") || {_x isKindOf "UAV_01_base_F"} || {_x isKindOf "UAV_02_base_F"} || {_x isKindOf "UAV_05_Base_F"} || {_x isKindOf "UAV_06_base_F"}}
};

if (!isNull _connectedDrone && {!(_connectedDrone in _uavs)}) then {
    _uavs = _uavs + [_connectedDrone];
};

{
    if (isNull _x || {!alive _x}) then { continue };
    private _netId = netId _x;
    if (_netId isEqualTo "") then { _netId = str _x; };
    private _cfg = configOf _x;
    private _disp = getText (_cfg >> "displayName");
    if (_disp isEqualTo "") then { _disp = typeOf _x; };
    private _pos = getPosASL _x;
    private _ownerName = _cs;
    private _owner = (UAVControl _x) select 0;
    if (!isNull _owner) then { _ownerName = name _owner; };
    private _id = format ["drone:%1", _netId];
    private _kind = if (_x isEqualTo _connectedDrone) then { "drone" } else { "uav" };
    private _label = format ["Caméra drone — %1", _disp];
    _feeds pushBack [
        _id,
        _kind,
        _label,
        _ownerName,
        getPlayerUID player,
        _pos select 0,
        _pos select 1,
        _pos select 2,
        mapGridPosition _x
    ];
} forEach _uavs;

// Construire JSON (échappement minimal — pas de guillemets dans les libellés)
private _escape = {
    params ["_s"];
    if (!(_s isEqualType "")) then { _s = format ["%1", _s]; };
    _s = _s splitString """" joinString "";
    _s = _s splitString "\" joinString "/";
    _s = _s splitString toString [10] joinString " ";
    _s = _s splitString toString [13] joinString " ";
    _s
};

private _parts = [];
{
    _x params ["_id", "_kind", "_label", "_callsign", "_steam", "_px", "_py", "_pz", "_grid"];
    _parts pushBack format [
        "{""id"":""%1"",""kind"":""%2"",""label"":""%3"",""callsign"":""%4"",""steam_uid"":""%5"",""pos_x"":%6,""pos_y"":%7,""pos_z"":%8,""grid"":""%9""}",
        [_id] call _escape,
        [_kind] call _escape,
        [_label] call _escape,
        [_callsign] call _escape,
        [_steam] call _escape,
        _px,
        _py,
        _pz,
        [_grid] call _escape
    ];
} forEach _feeds;

private _mapId = missionNamespace getVariable ["comspec_overwatch_map_id", 1];
if (_mapId isEqualType "") then { _mapId = parseNumber _mapId; };
if (_mapId isEqualTo 0) then { _mapId = 1; };

private _json = format [
    "{""mapId"":%1,""callsign"":""%2"",""feeds"":[%3]}",
    _mapId,
    [_cs] call _escape,
    _parts joinString ","
];

private _sig = format ["%1|%2", count _feeds, _parts joinString ";"];
private _last = missionNamespace getVariable ["COMSPEC_Athena_LastVideoFeedsSig", ""];
// Toujours republier régulièrement pour le TTL « en ligne », même si la liste est identique
private _lastAt = missionNamespace getVariable ["COMSPEC_Athena_LastVideoFeedsAt", 0];
if (_sig isEqualTo _last && {(diag_tickTime - _lastAt) < 25}) exitWith {};
missionNamespace setVariable ["COMSPEC_Athena_LastVideoFeedsSig", _sig, false];
missionNamespace setVariable ["COMSPEC_Athena_LastVideoFeedsAt", diag_tickTime, false];

"COMSPECExtension" callExtension ["SendVideoFeeds", [_json]];
if ((count _feeds) > 0) then {
    [format ["Cams actives · %1 flux", count _feeds]] call comspec_overwatch_connect_fnc_appendModuleLog;
};
