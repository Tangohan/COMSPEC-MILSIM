/*
    Polling périodique « état mission » vers Athena (serveur uniquement).
    - Config roleplay / zones / liaison via relais
    - Diffusion aux clients (évite N appels HTTP identiques)
*/
if (!isServer) exitWith { false };
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { false };
if (!(missionNamespace getVariable ["comspec_overwatch_server_hub", true])) exitWith { false };

// Diffusion seulement si la valeur change : ces setVariable publics partaient à chaque poll vers tous les clients (+ JIP).
// On compare à la dernière valeur diffusée (pas à la valeur locale du serveur, qui peut venir d'un réglage CBA).
private _fnc_setPub = {
    params ["_key", "_val"];
    private _sent = missionNamespace getVariable ["COMSPEC_ServerPubSent", createHashMap];
    missionNamespace setVariable ["COMSPEC_ServerPubSent", _sent, false];
    if (_key in _sent && {(_sent get _key) isEqualTo _val}) exitWith {};
    _sent set [_key, _val];
    missionNamespace setVariable [_key, _val, true];
};

private _raw = ["COMSPECExtension" callExtension ["GetRoleplayConfig", []]] call comspec_overwatch_connect_fnc_extResult;
if (!(_raw isEqualType "") || {_raw isEqualTo ""}) exitWith { false };
if ((_raw select [0, 3]) != "OK|") exitWith {
    ["COMSPEC_ServerHubOwnsMissionPoll", false] call _fnc_setPub;
    false
};

// Uplink serveur OK → les clients peuvent laisser le hub porter ce poll.
missionNamespace setVariable ["COMSPEC_ServerUplinkOk", true, false];
["COMSPEC_ServerHubOwnsMissionPoll", true] call _fnc_setPub;
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

["COMSPEC_IntelScramble", _intelScramble] call _fnc_setPub;
["COMSPEC_LinkViaRelays", _viaRelays] call _fnc_setPub;

if (_netEnabled) then {
    ["comspec_overwatch_roleplay_enabled", true] call _fnc_setPub;
    ["comspec_overwatch_roleplay_network_failures", true] call _fnc_setPub;
};

if (_zonesEnabled) then {
    ["comspec_overwatch_roleplay_enabled", true] call _fnc_setPub;
    ["comspec_overwatch_roleplay_visual_effects", true] call _fnc_setPub;
    ["comspec_overwatch_roleplay_network_failures", true] call _fnc_setPub;
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
