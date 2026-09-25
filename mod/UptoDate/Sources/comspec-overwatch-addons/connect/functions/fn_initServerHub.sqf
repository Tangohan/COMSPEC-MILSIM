/*
    Hub serveur Overwatch — charge mission unique :
    - file anti-spam / debounce
    - relais synchronisés une fois
    - zones / realism portail une fois
    - polling état mission Athena
    - ordres IA + marqueurs Zeus agrégés
*/
if (!isServer) exitWith { false };
if (!(missionNamespace getVariable ["comspec_overwatch_server_hub", true])) exitWith { false };
if (missionNamespace getVariable ["COMSPEC_ServerHubStarted", false]) exitWith { true };
missionNamespace setVariable ["COMSPEC_ServerHubStarted", true, false];
missionNamespace setVariable ["COMSPEC_ServerHubActive", true, true];
missionNamespace setVariable ["COMSPEC_ServerQueue", createHashMap, false];

// Même filtre de marqueurs « déjà nôtres » que le client (évite boucles).
if (isNil "COMSPEC_fnc_isOwnedMapMarker") then {
    missionNamespace setVariable ["COMSPEC_fnc_isOwnedMapMarker", {
        params ["_n"];
        if (!(_n isEqualType "") || {_n isEqualTo ""}) exitWith { false };
        private _ul = toLower _n;
        (
            (_ul find "comspec_webmk_") == 0
            || {(_ul find "comspec_shape_") == 0}
            || {(_ul find "comspec_tabletmk_") == 0}
            || {(_ul find "comspec_relay_") == 0}
            || {(_ul find "_comspec_po_ring_") == 0}
            || {(_ul find "_comspec_det_ring_") == 0}
            || {(_ul find "comspec_gps_") == 0}
        )
    }, false];
};

"COMSPECExtension" callExtension "Warmup";

["INFO", "ServerHub", "Démarrage hub serveur (relais / zones / file / poll mission)"] call comspec_overwatch_connect_fnc_log;

// --- Marqueurs Zeus / globaux : debounce unique ---
if (isNil "COMSPEC_ServerMarkerEHs") then {
    private _enqueueMarker = {
        params ["_marker", ["_deleted", false]];
        if (_marker isEqualTo "") exitWith {};
        if ((missionNamespace getVariable ["COMSPEC_MarkerEhMuted", 0]) > 0) exitWith {};
        if ([_marker] call (missionNamespace getVariable ["COMSPEC_fnc_isOwnedMapMarker", { false }])) exitWith {};
        if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
        [
            format ["mk_%1_%2", _marker, if (_deleted) then {"d"} else {"u"}],
            {
                params ["_m", "_del"];
                if (!_del && {!(_m in allMapMarkers)}) exitWith {};
                private _force = if (!isNil "comspec_overwatch_connect_fnc_isSyncableMapMarker") then {
                    [_m] call comspec_overwatch_connect_fnc_isSyncableMapMarker
                } else {
                    true
                };
                [_m, _del, _force] call comspec_overwatch_connect_fnc_syncMapMarker;
            },
            0.75,
            [_marker, _deleted]
        ] call comspec_overwatch_connect_fnc_serverEnqueue;
    };
    missionNamespace setVariable ["COMSPEC_ServerEnqueueMarker", _enqueueMarker, false];

    COMSPEC_ServerMarkerEHs = [
        addMissionEventHandler ["MarkerCreated", {
            private _marker = _this call comspec_overwatch_connect_fnc_resolveMarkerEhName;
            if (_marker isEqualTo "") exitWith {};
            [_marker, false] call (missionNamespace getVariable ["COMSPEC_ServerEnqueueMarker", {}]);
        }],
        addMissionEventHandler ["MarkerUpdated", {
            private _marker = _this call comspec_overwatch_connect_fnc_resolveMarkerEhName;
            if (_marker isEqualTo "") exitWith {};
            [_marker, false] call (missionNamespace getVariable ["COMSPEC_ServerEnqueueMarker", {}]);
        }],
        addMissionEventHandler ["MarkerDeleted", {
            private _marker = _this call comspec_overwatch_connect_fnc_resolveMarkerEhName;
            if (_marker isEqualTo "") exitWith {};
            [_marker, true] call (missionNamespace getVariable ["COMSPEC_ServerEnqueueMarker", {}]);
        }]
    ];
};

// --- Flush file (~0.4 s) ---
if (isNil "COMSPEC_ServerFlushPfh") then {
    COMSPEC_ServerFlushPfh = [{
        if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
        [] call comspec_overwatch_connect_fnc_serverFlushQueue;
    }, 0.4, []] call CBA_fnc_addPerFrameHandler;
};

// --- Relais : une seule boucle serveur ---
if (isNil "COMSPEC_ServerRelaysPfh") then {
    COMSPEC_ServerRelaysPfh = [{
        if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
        if (!(["relays"] call comspec_overwatch_connect_fnc_diagIsolateAllows)) exitWith {};
        [
            "sync_relays",
            { [] call comspec_overwatch_connect_fnc_syncAtakRelays; },
            0.2,
            []
        ] call comspec_overwatch_connect_fnc_serverEnqueue;
    }, 12, []] call CBA_fnc_addPerFrameHandler;
};

// --- État mission Athena (~90 s) ---
if (isNil "COMSPEC_ServerMissionPfh") then {
    // Premier passage rapide après boot
    [{
        [] call comspec_overwatch_connect_fnc_serverPollMissionState;
    }, [], 8] call CBA_fnc_waitAndExecute;

    COMSPEC_ServerMissionPfh = [{
        if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
        [
            "poll_mission_state",
            { [] call comspec_overwatch_connect_fnc_serverPollMissionState; },
            0.5,
            []
        ] call comspec_overwatch_connect_fnc_serverEnqueue;
    }, 90, []] call CBA_fnc_addPerFrameHandler;
};

// --- Ordres IA : un seul poll serveur ---
if (isNil "COMSPEC_ServerAiOrdersPfh") then {
    COMSPEC_ServerAiOrdersPfh = [{
        if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
        if (!(missionNamespace getVariable ["COMSPEC_ServerUplinkOk", false])
            && {!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])}) exitWith {};
        [
            "poll_ai_orders",
            { [] call comspec_overwatch_connect_fnc_pollAiOrders; },
            0.3,
            []
        ] call comspec_overwatch_connect_fnc_serverEnqueue;
    }, 5, []] call CBA_fnc_addPerFrameHandler;
};

true
