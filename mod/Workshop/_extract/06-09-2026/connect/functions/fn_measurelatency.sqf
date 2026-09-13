/*
    Mesure la latence aller-retour du module Athena (Ping).
    Stocke COMSPEC_LastLatencyMs (−1 si indisponible) et retourne la valeur en ms.
*/
if (!hasInterface) exitWith { -1 };

private _t0 = diag_tickTime;
private _ping = ["COMSPECExtension" callExtension ["Ping", []]] call comspec_overwatch_connect_fnc_extResult;
private _ms = round ((diag_tickTime - _t0) * 1000);
if (_ms < 0) then { _ms = 0; };

if (_ping isEqualTo "" || {(_ping select [0, 3]) != "OK|"}) exitWith {
    missionNamespace setVariable ["COMSPEC_LastLatencyMs", -1, false];
    missionNamespace setVariable ["COMSPEC_LastLatencyAt", diag_tickTime, false];
    -1
};

missionNamespace setVariable ["COMSPEC_LastLatencyMs", _ms, false];
missionNamespace setVariable ["COMSPEC_LastLatencyAt", diag_tickTime, false];
_ms
