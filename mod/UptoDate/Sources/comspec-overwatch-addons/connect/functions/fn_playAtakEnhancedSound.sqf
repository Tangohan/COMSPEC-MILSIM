/*
    Son roleplay ATAK (coupure, gel, zone, réparation).
    Jamais les bips vanilla Arma (FD_CP / AddItemFailed / beep_target) :
    ils passent pour des erreurs script et spamment sur les coupures courtes.
*/
if (!hasInterface) exitWith {};

params [["_soundType", "", [""]]];
if (_soundType isEqualTo "") exitWith {};
_soundType = toLower _soundType;

private _kind = switch (_soundType) do {
    case "disconnect";
    case "crash";
    case "screen_broken";
    case "interference";
    case "glitch": { "down" };
    case "reconnect": { "up" };
    case "zone_alert";
    case "warning";
    case "degraded": { "warn" };
    default { "" };
};
if (_kind isEqualTo "") exitWith {};

private _now = diag_tickTime;
private _lastMap = missionNamespace getVariable ["COMSPEC_AtakMalfunctionSoundAt", createHashMap];
if (!(_lastMap isEqualType createHashMap)) then { _lastMap = createHashMap; };
private _prev = _lastMap getOrDefault [_kind, -1e9];
if ((_now - _prev) < 8) exitWith {};
_lastMap set [_kind, _now];
missionNamespace setVariable ["COMSPEC_AtakMalfunctionSoundAt", _lastMap, false];

private _pref = missionNamespace getVariable ["comspec_overwatch_notif_sound", "silent_vib"];
if (!(_pref isEqualType "")) then { _pref = "silent_vib"; };
_pref = toLower _pref;
if (_pref isEqualTo "mute") exitWith {};

private _vol = ["fx"] call comspec_overwatch_connect_fnc_getAtakSoundVolume;
if (_vol <= 0.01) exitWith {};

private _silentVib = _pref isEqualTo "silent_vib";
private _snd = switch (_kind) do {
    case "down": { if (_silentVib) then { "COMSPEC_ATAK_Vibrate" } else { "COMSPEC_ATAK_Disconnect" } };
    case "up": { if (_silentVib) then { "COMSPEC_ATAK_Vibrate" } else { "COMSPEC_ATAK_Start" } };
    default { "COMSPEC_ATAK_Vibrate" };
};

playSoundUI [_snd, _vol, 1];
