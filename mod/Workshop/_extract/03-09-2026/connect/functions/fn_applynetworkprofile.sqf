/*
    Résout le profil réseau Overwatch → variables de télémétrie.
    Ne pilote pas seulement un sleep : seuils, heartbeat, batch DLL, politique.
*/
if (!hasInterface) exitWith {};

private _authority = missionNamespace getVariable ["comspec_overwatch_network_authority", 0];
if (!(_authority isEqualType 0)) then { _authority = 0; };
_authority = (round _authority) max 0 min 2;

private _serverProfile = missionNamespace getVariable ["comspec_overwatch_network_profile_server", 1];
if (!(_serverProfile isEqualType 0)) then { _serverProfile = 1; };
_serverProfile = (round _serverProfile) max 0 min 4;

private _clientProfile = missionNamespace getVariable ["comspec_overwatch_network_profile", 1];
if (!(_clientProfile isEqualType 0)) then { _clientProfile = 1; };
_clientProfile = (round _clientProfile) max 0 min 4;

private _profile = _serverProfile;
switch (_authority) do {
    case 1: { _profile = _clientProfile; };
    case 2: { _profile = (_clientProfile min _serverProfile); };
    default { _profile = _serverProfile; };
};

private _policy = missionNamespace getVariable ["comspec_overwatch_network_policy", 2];
if (!(_policy isEqualType 0)) then { _policy = 2; };
_policy = (round _policy) max 0 min 2;

private _posMin = 5;
private _heartbeat = 30;
private _moveInf = 3;
private _moveVeh = 10;
private _moveAir = 15;
private _heading = 15;
private _batch = 1;
private _hist = 15;
private _load = "normale";

switch (_profile) do {
    case 0: {
        _posMin = 10; _heartbeat = 60; _moveInf = 5; _moveVeh = 15; _moveAir = 20;
        _heading = 25; _batch = 2; _hist = 30; _load = "faible";
    };
    case 1: {
        _posMin = 5; _heartbeat = 30; _moveInf = 3; _moveVeh = 10; _moveAir = 15;
        _heading = 15; _batch = 1; _hist = 15; _load = "normale";
    };
    case 2: {
        _posMin = 2; _heartbeat = 20; _moveInf = 2; _moveVeh = 8; _moveAir = 12;
        _heading = 10; _batch = 0.5; _hist = 10; _load = "élevée";
    };
    case 3: {
        _posMin = 1; _heartbeat = 10; _moveInf = 1; _moveVeh = 5; _moveAir = 8;
        _heading = 8; _batch = 0.35; _hist = 8; _load = "très élevée";
    };
    default {
        private _pi = missionNamespace getVariable ["comspec_overwatch_position_interval", 5];
        if (!(_pi isEqualType 0)) then { _pi = 5; };
        _posMin = (_pi max 1) min 30;
        private _th = missionNamespace getVariable ["comspec_overwatch_position_threshold", 5];
        if (!(_th isEqualType 0)) then { _th = 5; };
        _moveInf = (_th max 1) min 50;
        _moveVeh = (_moveInf * 2.5) min 40;
        _moveAir = (_moveInf * 4) min 50;
        private _bi = missionNamespace getVariable ["comspec_overwatch_batch_interval", 1];
        if (!(_bi isEqualType 0)) then { _bi = 1; };
        _batch = (_bi max 0.25) min 5;
        private _hb = missionNamespace getVariable ["comspec_overwatch_heartbeat_interval", 30];
        if (!(_hb isEqualType 0)) then { _hb = 30; };
        _heartbeat = (_hb max 8) min 120;
        _heading = 15;
        _hist = 15;
        _load = "personnalisée";
    };
};

missionNamespace setVariable ["COMSPEC_NetworkProfile", _profile, false];
missionNamespace setVariable ["COMSPEC_NetworkPolicy", _policy, false];
missionNamespace setVariable ["COMSPEC_PositionMinInterval", _posMin, false];
missionNamespace setVariable ["COMSPEC_HeartbeatInterval", _heartbeat, false];
missionNamespace setVariable ["COMSPEC_MoveThresholdInf", _moveInf, false];
missionNamespace setVariable ["COMSPEC_MoveThresholdVeh", _moveVeh, false];
missionNamespace setVariable ["COMSPEC_MoveThresholdAir", _moveAir, false];
missionNamespace setVariable ["COMSPEC_HeadingThreshold", _heading, false];
missionNamespace setVariable ["COMSPEC_DllBatchSec", _batch, false];
missionNamespace setVariable ["COMSPEC_HistorySampleMin", _hist, false];
missionNamespace setVariable ["COMSPEC_NetworkLoadHint", _load, false];

if (missionNamespace getVariable ["COMSPEC_AthenaReady", false]) then {
    private _ms = str round (((_batch max 0.25) min 2) * 1000);
    ["COMSPECExtension" callExtension ["SetTelemetryBatch", [_ms]]] call comspec_overwatch_connect_fnc_extResult;
};

true
