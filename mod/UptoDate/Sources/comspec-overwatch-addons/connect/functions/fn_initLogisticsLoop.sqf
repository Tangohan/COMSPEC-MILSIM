/*
    Boucle LOGSTAT Phase C : remonte périodiquement l’état logistique du véhicule / opérateur.
    Intervalle 10–15 s (delta) ; immédiat à l’embarquement via GetIn (initVehicleTracking).
*/
if (!hasInterface) exitWith { false };
if (missionNamespace getVariable ["COMSPEC_LogisticsLoopStarted", false]) exitWith { true };
missionNamespace setVariable ["COMSPEC_LogisticsLoopStarted", true, false];

[{
    if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
    if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {};
    if (missionNamespace getVariable ["COMSPEC_DisconnectSent", false]) exitWith {};
    if (missionNamespace getVariable ["COMSPEC_HandshakeQuiet", false]) exitWith {};
    if (diag_tickTime < (missionNamespace getVariable ["COMSPEC_RespawnGraceUntil", -1e9])) exitWith {};
    if (isNull player || {!alive player}) exitWith {};
    if !([player] call comspec_overwatch_connect_fnc_hasTerminal) exitWith {};

    private _last = missionNamespace getVariable ["COMSPEC_LogisticsLastAt", -1e9];
    private _gap = 12;
    private _sendBack = missionNamespace getVariable ["COMSPEC_SendBackoffSec", 0];
    if ((_sendBack isEqualType 0) && {_sendBack > 0}) then {
        _gap = _gap * (if (_sendBack >= 150) then { 4 } else { 2 });
    };
    if ((diag_tickTime - _last) < _gap) exitWith {};

    private _cs = [] call comspec_overwatch_connect_fnc_getCallsign;
    if (_cs isEqualTo "") then { _cs = name player; };
    private _veh = vehicle player;
    private _target = if (_veh isEqualTo player) then { player } else { _veh };
    private _tid = missionNamespace getVariable ["COMSPEC_TenantId", ""];
    private _mapId = missionNamespace getVariable ["COMSPEC_MapId", 1];
    if (!(_mapId isEqualType 0)) then { _mapId = 1; };
    private _missionId = if (_tid isEqualType "" && {_tid isNotEqualTo ""}) then {
        format ["mission_%1_map_%2", _tid, _mapId]
    } else {
        format ["mission_1_map_%1", _mapId]
    };

    [_missionId, _cs, _cs, _target] call comspec_overwatch_connect_fnc_sendLogisticsStatus;
    missionNamespace setVariable ["COMSPEC_LogisticsLastAt", diag_tickTime, false];
}, 3] call CBA_fnc_addPerFrameHandler;

true
