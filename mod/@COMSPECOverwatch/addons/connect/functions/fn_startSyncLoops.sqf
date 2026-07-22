/*
    Author: COMSPEC
    Description:
        Démarre les boucles de synchronisation après le handshake Athena.
        Évite de spammer le portail / le scheduler tant que la liaison n’est pas prête.
*/
if (!hasInterface) exitWith {};
if (missionNamespace getVariable ["COMSPEC_SyncLoopsStarted", false]) exitWith {};
missionNamespace setVariable ["COMSPEC_SyncLoopsStarted", true, false];

[] call comspec_overwatch_connect_fnc_sendFactionSettings;

private _interval = missionNamespace getVariable ["comspec_overwatch_position_interval", 3];
if (!(_interval isEqualType 0)) then { _interval = 2; };
_interval = (_interval max 1) min 15;
[{
    if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {};
    [{ [player] call comspec_overwatch_connect_fnc_updatePosition }, [], "updatePosition"] call comspec_overwatch_connect_fnc_profileWrap;
}, _interval] call CBA_fnc_addPerFrameHandler;

[] call comspec_overwatch_connect_fnc_initRadioMonitor;

if (isNil "COMSPEC_MapMarkerEHs") then {
    COMSPEC_MapMarkerEHs = [
        addMissionEventHandler ["MarkerCreated", {
            params ["_marker"];
            [_marker, false] call comspec_overwatch_connect_fnc_syncMapMarker;
        }],
        addMissionEventHandler ["MarkerUpdated", {
            params ["_marker"];
            [_marker, false] call comspec_overwatch_connect_fnc_syncMapMarker;
        }],
        addMissionEventHandler ["MarkerDeleted", {
            params ["_marker"];
            [_marker, true] call comspec_overwatch_connect_fnc_syncMapMarker;
        }]
    ];
};

private _casPollInterval = 10;
[{
    params ["_args", "_pfhId"];
    [{
        if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
        if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {};
        private _callsign = [] call comspec_overwatch_connect_fnc_getCallsign;
        if (_callsign isEqualTo "") then { _callsign = "Pilot"; };
        private _raw = ["COMSPECExtension" callExtension ["GetCASForCallsign", [_callsign, "1"]]] call comspec_overwatch_connect_fnc_extResult;
        if (_raw isEqualTo "" || {(_raw select [0, 3]) != "OK|"}) exitWith {};
        private _payload = _raw select [3, count _raw - 3];
        private _lastPayload = missionNamespace getVariable ["COMSPEC_LastCASPayload", ""];
        if (_payload != "" && {_payload != _lastPayload}) then {
            missionNamespace setVariable ["COMSPEC_LastCASPayload", _payload];
            missionNamespace setVariable ["COMSPEC_CAS_Raw", _payload];
            [] call comspec_overwatch_connect_fnc_receiveCASRequest;
            ["COMSPEC_Info", ["Nouvelle demande CAS reçue"]] call comspec_overwatch_connect_fnc_showNotification;
            ["[CAS] Nouvelle demande d’appui aérien reçue.", "cas"] call comspec_overwatch_connect_fnc_appendLinkLog;
        };
    }, [], "casPoll"] call comspec_overwatch_connect_fnc_profileWrap;
}, _casPollInterval, []] call CBA_fnc_addPerFrameHandler;

[{
    [{
        if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
        if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {};
        [] call comspec_overwatch_connect_fnc_pollMedicalAlerts;
    }, [], "pollMedicalAlerts"] call comspec_overwatch_connect_fnc_profileWrap;
}, 8, []] call CBA_fnc_addPerFrameHandler;

[{
    [{
        if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
        if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {};
        [] call comspec_overwatch_connect_fnc_pollOrders;
    }, [], "pollOrders"] call comspec_overwatch_connect_fnc_profileWrap;
}, 8, []] call CBA_fnc_addPerFrameHandler;

[{
    [{
        if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
        if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {};
        [] call comspec_overwatch_connect_fnc_pollMapShapes;
    }, [], "pollMapShapes"] call comspec_overwatch_connect_fnc_profileWrap;
}, 10, []] call CBA_fnc_addPerFrameHandler;

["OnOrderIssued", {
    params ["_order"];
    private _target = _order getOrDefault ["target", ""];
    if (_target isEqualTo "") exitWith {};

    private _chainLog = missionNamespace getVariable ["COMSPEC_OrderPropagationLog", []];
    _chainLog pushBack [
        serverTime,
        _order getOrDefault ["id", ""],
        "COMMANDER",
        "SQUAD_LEADER",
        _target
    ];
    _chainLog pushBack [
        serverTime,
        _order getOrDefault ["id", ""],
        "SQUAD_LEADER",
        "FIRETEAM",
        _target
    ];
    missionNamespace setVariable ["COMSPEC_OrderPropagationLog", _chainLog, true];

    [_order] call comspec_overwatch_connect_fnc_receiveOrder;
}] call comspec_overwatch_connect_fnc_registerEventHandler;

[{
    if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
    private _orders = missionNamespace getVariable ["COMSPEC_Orders", []];
    private _seen = missionNamespace getVariable ["COMSPEC_OrdersSeen", []];
    {
        if (!(_x isEqualType createHashMap)) then { continue };
        private _id = _x getOrDefault ["id", ""];
        if (_id isEqualTo "" || {_id in _seen}) then { continue };
        [_x] call comspec_overwatch_connect_fnc_receiveOrder;
    } forEach _orders;
}, 5, []] call CBA_fnc_addPerFrameHandler;

["OnTrackingAnomaly", {
    params ["_alert"];
    private _kind = _alert getOrDefault ["kind", "ANOMALY"];
    [format ["Anomalie détectée : %1", _kind], "system", "warn"] call comspec_overwatch_connect_fnc_announce;
}] call comspec_overwatch_connect_fnc_registerEventHandler;

[] spawn comspec_overwatch_connect_fnc_playtimeTracker;

["[Athena] Boucles de synchronisation démarrées."] call comspec_overwatch_connect_fnc_appendLinkLog;
