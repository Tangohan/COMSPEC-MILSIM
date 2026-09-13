/*
    Synchronise la config roleplay portail (zones, réseau) vers le client.
    Appelle l'extension GetRoleplayConfig → /api/atak/roleplay-stats
*/
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {};

private _raw = ["COMSPECExtension" callExtension ["GetRoleplayConfig", []]] call comspec_overwatch_connect_fnc_extResult;
if (_raw isEqualTo "" || {(_raw select [0, 3]) != "OK|"}) exitWith {};

private _payload = _raw select [3, count _raw - 3];
private _last = missionNamespace getVariable ["COMSPEC_RoleplayConfigRaw", ""];
if (_payload isEqualTo _last) exitWith {};
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

    // Lignes zone : name\ttype\tx\ty\tradius\tintensity (6 colonnes, pas une clef connue)
    if ((count _parts) >= 5 && {!(_key in ["network_enabled", "zones_enabled", "network_mode", "packet_loss_percent", "zones_json", "session_ttl_sec", "intel_scramble_enabled"])}) then {
        _zoneLines pushBack _line;
    } else {
        _map set [_key, _val];
    };
} forEach (_payload splitString toString [10]);

if ((count _zoneLines) > 0) then {
    _map set ["zones_lines", _zoneLines joinString toString [10]];
};

missionNamespace setVariable ["COMSPEC_PortalRoleplayConfig", _map, false];

private _netEnabled = (_map getOrDefault ["network_enabled", "0"]) isEqualTo "1";
private _zonesEnabled = (_map getOrDefault ["zones_enabled", "0"]) isEqualTo "1";
private _intelScramble = (_map getOrDefault ["intel_scramble_enabled", "0"]) isEqualTo "1";
missionNamespace setVariable ["COMSPEC_IntelScramble", _intelScramble, false];

if (_netEnabled) then {
    missionNamespace setVariable ["comspec_overwatch_roleplay_enabled", true, false];
    missionNamespace setVariable ["comspec_overwatch_roleplay_network_failures", true, false];
};

if (_zonesEnabled) then {
    missionNamespace setVariable ["comspec_overwatch_roleplay_enabled", true, false];
    missionNamespace setVariable ["comspec_overwatch_roleplay_visual_effects", true, false];
    missionNamespace setVariable ["comspec_overwatch_roleplay_network_failures", true, false];
    [] call comspec_overwatch_connect_fnc_syncRoleplayZonesFromPortal;
};

true
