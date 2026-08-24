/*
    Snapshot météo mission → Athena (bandeau web).
    Inspiré ATAK Enhanced Weather — lecture moteur Arma uniquement.
    On n’envoie pas pendant le handshake : un 401/timeout n’est pas une panne opérateur.
*/
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {};
private _readyAt = missionNamespace getVariable ["COMSPEC_AthenaReadyAt", 0];
if (!(_readyAt isEqualType 0)) then { _readyAt = 0; };
if (_readyAt > 0 && {(diag_tickTime - _readyAt) < 20}) exitWith {};
private _backUntil = missionNamespace getVariable ["COMSPEC_ApiBackoffUntil", 0];
if ((_backUntil isEqualType 0) && {diag_tickTime < _backUntil}) exitWith {};
if (!(["weather"] call comspec_overwatch_connect_fnc_isModModuleEnabled)) exitWith {};
if (isNil "comspec_overwatch_connect_fnc_getCallsign") exitWith {};

private _cloud = (overcast max 0) min 1;
private _rain = (rain max 0) min 1;
private _fog = (fog max 0) min 1;
private _humidity = (humidity max 0) min 1;
private _temp = round ((ambientTemperature select 0));
private _windKph = round ((vectorMagnitude wind) * 3.6);
private _windDir = round windDir;

private _condition = call {
    if (_rain > 0.45) exitWith { "Pluie" };
    if (_fog > 0.45) exitWith { "Brouillard" };
    if (_cloud > 0.7) exitWith { "Couvert" };
    if (_cloud > 0.35) exitWith { "Nuageux" };
    "Dégagé"
};

private _cs = [] call comspec_overwatch_connect_fnc_getCallsign;
if (_cs isEqualTo "") then { _cs = name player; };
_cs = (_cs splitString """" joinString "");

private _sig = format ["%1|%2|%3|%4|%5|%6", _condition, _temp, _windKph, round (_cloud * 100), round (_fog * 100), round (_rain * 100)];
private _last = missionNamespace getVariable ["COMSPEC_Athena_LastWeatherSig", ""];
if (_sig isEqualTo _last) exitWith {};
missionNamespace setVariable ["COMSPEC_Athena_PendingWeatherSig", _sig, false];

private _json = format [
    "{""condition"":""%1"",""temperature_c"":%2,""wind_kph"":%3,""wind_dir"":%4,""cloud_pct"":%5,""fog_pct"":%6,""rain_pct"":%7,""humidity_pct"":%8,""call_sign"":""%9"",""mapId"":1}",
    _condition,
    _temp,
    _windKph,
    _windDir,
    round (_cloud * 100),
    round (_fog * 100),
    round (_rain * 100),
    round (_humidity * 100),
    _cs
];

"COMSPECExtension" callExtension ["SendWeather", [_json]];
[format ["Météo · %1 · %2 °C", _condition, _temp]] call comspec_overwatch_connect_fnc_appendModuleLog;
