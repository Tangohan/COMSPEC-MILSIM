/*
    Polling périodique « état mission » vers Athena (serveur uniquement).
    - Config roleplay / zones / liaison via relais
    - Diffusion aux clients (évite N appels HTTP identiques)
*/
if (!isServer) exitWith { false };
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { false };
if (!(missionNamespace getVariable ["comspec_overwatch_server_hub", true])) exitWith { false };

private _raw = ["COMSPECExtension" callExtension ["GetRoleplayConfig", []]] call comspec_overwatch_connect_fnc_extResult;
if (!(_raw isEqualType "") || {_raw isEqualTo ""}) exitWith { false };
if ((_raw select [0, 3]) != "OK|") exitWith {
    missionNamespace setVariable ["COMSPEC_ServerHubOwnsMissionPoll", false, true];
    false
};

// Uplink serveur OK → les clients peuvent laisser le hub porter ce poll.
missionNamespace setVariable ["COMSPEC_ServerUplinkOk", true, false];
missionNamespace setVariable ["COMSPEC_ServerHubOwnsMissionPoll", true, true];
// Les gates AthenaReady côté serveur (sync marqueurs / relais) suivent l’uplink hub.
if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) then {
    missionNamespace setVariable ["COMSPEC_AthenaReady", true, false];
};

private _payload = _raw select [3, count _raw - 3];
private _last = missionNamespace getVariable ["COMSPEC_RoleplayConfigRaw", ""];
if (_payload isEqualTo _last) exitWith { true };
missionNamespace setVariable ["COMSPEC_RoleplayConfigRaw", _payload, false];

private _map = createHashMap;
private _zoneLines = [];

{
    private _line = trim _x;
    if (_line isEqualTo "") then { continue };
    private _parts = _line splitString toString [9];
    if ((count _parts) < 2) then { continue };
    private _key = _parts select 0;
    private _val = _parts select 1;

    if (_key in ["zones_lines_count"]) then { continue };

    if ((count _parts) >= 5 && {!(_key in ["network_enabled", "zones_enabled", "network_mode", "packet_loss_percent", "zones_json", "session_ttl_sec", "intel_scramble_enabled", "link_via_relays"])}) then {
        _zoneLines pushBack _line;
    } else {
        _map set [_key, _val];
    };
} forEach (_payload splitString toString [10]);

if ((count _zoneLines) > 0) then {
    _map set ["zones_lines", _zoneLines joinString toString [10]];
};

missionNamespace setVariable ["COMSPEC_PortalRoleplayConfig", _map, true];

private _netEnabled = (_map getOrDefault ["network_enabled", "0"]) isEqualTo "1";
private _zonesEnabled = (_map getOrDefault ["zones_enabled", "0"]) isEqualTo "1";
private _intelScramble = (_map getOrDefault ["intel_scramble_enabled", "0"]) isEqualTo "1";
private _viaRelays = (_map getOrDefault ["link_via_relays", "0"]) isEqualTo "1";

missionNamespace setVariable ["COMSPEC_IntelScramble", _intelScramble, true];
missionNamespace setVariable ["COMSPEC_LinkViaRelays", _viaRelays, true];

if (_netEnabled) then {
    missionNamespace setVariable ["comspec_overwatch_roleplay_enabled", true, true];
    missionNamespace setVariable ["comspec_overwatch_roleplay_network_failures", true, true];
};

if (_zonesEnabled) then {
    missionNamespace setVariable ["comspec_overwatch_roleplay_enabled", true, true];
    missionNamespace setVariable ["comspec_overwatch_roleplay_visual_effects", true, true];
    missionNamespace setVariable ["comspec_overwatch_roleplay_network_failures", true, true];
    [] call comspec_overwatch_connect_fnc_syncRoleplayZonesFromPortal;
};

["COMSPEC_serverMissionState", [
    _viaRelays,
    _zonesEnabled,
    _netEnabled,
    _intelScramble
]] call CBA_fnc_globalEvent;

["INFO", "ServerHub", "État mission Athena synchronisé (zones / réseau)"] call comspec_overwatch_connect_fnc_log;
true
