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
[] call comspec_overwatch_connect_fnc_pollModModules;
[] call comspec_overwatch_connect_fnc_pollExperience;

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

// Relayer les marqueurs déjà présents (Marker Dropper / carte / file d’attente) après liaison Athena.
[] spawn {
    uiSleep 1.5;
    [] call comspec_overwatch_connect_fnc_queueMapMarker; // flush pending
    [] call comspec_overwatch_connect_fnc_resyncAllMapMarkers;
    if (!isNil "comspec_overwatch_atak_athena_fnc_athena_bridgeCtabMarkers") then {
        [] call comspec_overwatch_atak_athena_fnc_athena_bridgeCtabMarkers;
    };
};
[{
    if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {};
    [] call comspec_overwatch_connect_fnc_queueMapMarker;
    [] call comspec_overwatch_connect_fnc_resyncAllMapMarkers;
}, 25, []] call CBA_fnc_addPerFrameHandler;

private _casPollInterval = 10;
[{
    params ["_args", "_pfhId"];
    [{
        if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
        if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {};
        // Sans indicatif : ne pas interroger avec un fallback « Pilot » (faux positifs / 9-line vide)
        private _callsign = [] call comspec_overwatch_connect_fnc_getCallsign;
        if (_callsign isEqualTo "") exitWith {};
        private _mapId = str (missionNamespace getVariable ["comspec_overwatch_map_id", 1]);
        if (_mapId isEqualTo "" || {_mapId isEqualTo "0"}) then { _mapId = "1"; };
        private _raw = ["COMSPECExtension" callExtension ["GetCASForCallsign", [_callsign, _mapId]]] call comspec_overwatch_connect_fnc_extResult;
        if (_raw isEqualTo "" || {(_raw select [0, 3]) != "OK|"}) exitWith {};
        private _payload = _raw select [3, count _raw - 3];
        private _trimmed = trim _payload;
        // Liste vide / null : mémoriser pour ne pas retrigger, sans ouvrir le 9-line
        if (
            _trimmed isEqualTo ""
            || {_trimmed isEqualTo "[]"}
            || {_trimmed isEqualTo "null"}
            || {_trimmed isEqualTo "{}"}
        ) exitWith {
            private _lastEmpty = missionNamespace getVariable ["COMSPEC_LastCASPayload", ""];
            if (_payload != _lastEmpty) then {
                missionNamespace setVariable ["COMSPEC_LastCASPayload", _payload];
                missionNamespace setVariable ["COMSPEC_CAS_Raw", ""];
                // Remise à zéro locale — receiveCASRequest ignore les payloads vides
                missionNamespace setVariable ["COMSPEC_CurrentCASId", ""];
                missionNamespace setVariable ["COMSPEC_LastCASOpenedId", ""];
            };
        };
        private _lastPayload = missionNamespace getVariable ["COMSPEC_LastCASPayload", ""];
        if (_payload != "" && {_payload != _lastPayload}) then {
            missionNamespace setVariable ["COMSPEC_LastCASPayload", _payload];
            missionNamespace setVariable ["COMSPEC_CAS_Raw", _payload];
            private _prevId = missionNamespace getVariable ["COMSPEC_LastCASOpenedId", ""];
            [] call comspec_overwatch_connect_fnc_receiveCASRequest;
            private _newId = missionNamespace getVariable ["COMSPEC_CurrentCASId", ""];
            // Notifier seulement quand une vraie nouvelle demande (nouvel id) a été acceptée
            if (_newId != "" && {_newId != _prevId}) then {
                ["COMSPEC_Info", ["Nouvelle demande d’appui aérien reçue"]] call comspec_overwatch_connect_fnc_showNotification;
                ["[CAS] Nouvelle demande d’appui aérien reçue.", "cas"] call comspec_overwatch_connect_fnc_appendLinkLog;
            };
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
        [] call comspec_overwatch_connect_fnc_pollTacticalAlerts;
    }, [], "pollTacticalAlerts"] call comspec_overwatch_connect_fnc_profileWrap;
}, 10, []] call CBA_fnc_addPerFrameHandler;

[{
    [{
        if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
        if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {};
        [] call comspec_overwatch_connect_fnc_pollMapShapes;
    }, [], "pollMapShapes"] call comspec_overwatch_connect_fnc_profileWrap;
}, 10, []] call CBA_fnc_addPerFrameHandler;

[{
    [{
        if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
        if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {};
        [] call comspec_overwatch_connect_fnc_pollModModules;
    }, [], "pollModModules"] call comspec_overwatch_connect_fnc_profileWrap;
}, 45, []] call CBA_fnc_addPerFrameHandler;

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

[{
    [{
        if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
        if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {};
        [] call comspec_overwatch_connect_fnc_pollExperience;
    }, [], "pollExperience"] call comspec_overwatch_connect_fnc_profileWrap;
}, 60, []] call CBA_fnc_addPerFrameHandler;

["OnTrackingAnomaly", {
    params ["_alert"];
    private _realism = missionNamespace getVariable ["COMSPEC_TenantRealism", false];
    private _milsimUi = missionNamespace getVariable ["comspec_overwatch_milsim_ui", false];
    if (_realism || _milsimUi) exitWith {};
    private _kind = toUpper (_alert getOrDefault ["kind", "ANOMALY"]);
    private _unit = _alert getOrDefault ["unit", ""];
    private _msg = switch (_kind) do {
        case "IMMOBILE": {
            private _dur = _alert getOrDefault ["duration", 0];
            private _mins = round (_dur / 60);
            if (_unit isEqualTo "") then {
                format ["Suivi — opérateur immobile depuis environ %1 min (détection automatique, à vérifier).", _mins max 1]
            } else {
                format ["Suivi — %1 semble immobile depuis environ %2 min (détection automatique, à vérifier).", _unit, _mins max 1]
            };
        };
        case "INCOHERENT_MOVE": {
            private _dist = round (_alert getOrDefault ["distance", 0]);
            if (_unit isEqualTo "") then {
                format ["Suivi — déplacement brusque détecté (~%1 m). Peut être un faux positif (téléportation, véhicule).", _dist]
            } else {
                format ["Suivi — %1 : déplacement brusque (~%2 m). Peut être un faux positif (téléportation, véhicule).", _unit, _dist]
            };
        };
        default {
            if (_unit isEqualTo "") then {
                "Suivi — anomalie de position détectée (détection automatique)."
            } else {
                format ["Suivi — %1 : anomalie de position (détection automatique).", _unit]
            };
        };
    };
    // Journal tablette toujours ; chat / bandeau seulement si notifs écran activées.
    [_msg, "system", "warn"] call comspec_overwatch_connect_fnc_announce;
}] call comspec_overwatch_connect_fnc_registerEventHandler;

[] spawn comspec_overwatch_connect_fnc_playtimeTracker;

["[Athena] Boucles de synchronisation démarrées."] call comspec_overwatch_connect_fnc_appendLinkLog;
