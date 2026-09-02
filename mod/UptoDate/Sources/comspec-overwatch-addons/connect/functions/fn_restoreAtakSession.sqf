/*
    Restaure l'état ATAK après reconnexion JIP (client).
    Params: [_uid, _savedState]
*/
if (!hasInterface) exitWith {};

params ["_uid", "_savedState", ["_owner", clientOwner]];

if (_savedState isEqualTo createHashMap) exitWith {};

waitUntil { !isNull player };

private _atakState = _savedState getOrDefault ["atak_state", createHashMap];
if (_atakState isEqualType createHashMap && {!(_atakState isEqualTo createHashMap)}) then {
    if !(_atakState getOrDefault ["device_destroyed", false]) then {
        missionNamespace setVariable ["COMSPEC_AtakState", _atakState, false];
    };
};

private _cs = _savedState getOrDefault ["callsign", ""];
if (_cs isNotEqualTo "") then {
    [_cs, true, "session_restore"] call comspec_overwatch_connect_fnc_setCallsign;
};

private _link = _savedState getOrDefault ["link_state", "linked"];
missionNamespace setVariable ["COMSPEC_LinkState", _link, false];

// Tentative restauration depuis le portail (TTL post-CTD)
private _steamUid = getPlayerUID player;
if (_steamUid != "") then {
    private _raw = ["COMSPECExtension" callExtension ["GetSessionRestore", [_steamUid]]] call comspec_overwatch_connect_fnc_extResult;
    if ((_raw select [0, 3]) isEqualTo "OK|") then {
        private _payload = _raw select [3];
        private _parts = _payload splitString toString [9];
        if ((count _parts) >= 2) then {
            private _portalCs = trim (_parts select 0);
            if (_portalCs isNotEqualTo "" && {_cs isEqualTo ""}) then {
                [_portalCs, true, "portal_restore"] call comspec_overwatch_connect_fnc_setCallsign;
            };
        };
    };
};

["Session ATAK restaurée après reconnexion.", "link", "info"] call comspec_overwatch_connect_fnc_ambientHint;

// Resync marqueurs / position
[] spawn {
    uiSleep 3;
    if (missionNamespace getVariable ["COMSPEC_AthenaReady", false]) then {
        [] call comspec_overwatch_connect_fnc_resyncAllMapMarkers;
        [player, true] call comspec_overwatch_connect_fnc_updatePosition;
        if (!isNil "comspec_overwatch_connect_fnc_syncOperatorProfile") then {
            ["sync", "session_restore", true] call comspec_overwatch_connect_fnc_syncOperatorProfile;
        };
    };
};

// Nettoyer côté serveur
[_uid] remoteExecCall ["comspec_overwatch_connect_fnc_clearDisconnectedAtakState", 2];

true
