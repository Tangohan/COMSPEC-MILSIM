/*
    Reprise post-déconnexion / CTD — pattern HandleDisconnect JSOC.
    Serveur : mémorise état ATAK par UID ; client JIP : restaure via API.
*/
if (!isServer) exitWith {};

if (missionNamespace getVariable ["COMSPEC_CrashRecoveryInited", false]) exitWith {};
missionNamespace setVariable ["COMSPEC_CrashRecoveryInited", true, true];

COMSPEC_DisconnectedAtakState = createHashMap;

addMissionEventHandler ["HandleDisconnect", {
    params ["_unit", "_id", "_uid", "_name"];
    if (_uid isEqualTo "" || {isNull _unit}) exitWith { false };

    private _callsign = "";
    if (local _unit) then {
        _callsign = trim (missionNamespace getVariable ["COMSPEC_Callsign", ""]);
    };
    if (_callsign isEqualTo "") then {
        _callsign = trim (groupId (group _unit));
    };

    private _state = createHashMap;
    _state set ["callsign", _callsign];
    _state set ["atak_state", _unit getVariable ["COMSPEC_AtakState", createHashMap]];
    _state set ["link_state", _unit getVariable ["COMSPEC_LinkState", "linked"]];
    _state set ["position", getPosATL _unit];
    _state set ["direction", getDir _unit];
    _state set ["time", time];

    COMSPEC_DisconnectedAtakState set [_uid, _state];
    publicVariable "COMSPEC_DisconnectedAtakState";
    false
}];

addMissionEventHandler ["PlayerConnected", {
    params ["_id", "_uid", "_name", "_jip", "_owner"];
    if (!_jip) exitWith {};
    if (_uid isEqualTo "") exitWith {};

    private _saved = COMSPEC_DisconnectedAtakState getOrDefault [_uid, createHashMap];
    if (_saved isEqualTo createHashMap) exitWith {};

    [_uid, _saved, _owner] remoteExecCall ["comspec_overwatch_connect_fnc_restoreAtakSession", _owner];
}];

true
