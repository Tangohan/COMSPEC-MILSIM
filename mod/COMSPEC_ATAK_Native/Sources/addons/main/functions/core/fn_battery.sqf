/*
    Batterie simulée du téléphone (profil COMSPEC_ATAK_BatterySim et réglage serveur comspec_atak_native_battery_sim).
    Consommation par minute selon l'usage : veille, écran porté ou en main, mode nuit (écran sombre),
    appareil photo, live cam, guidage GPS, envois de données de la dernière minute, recherche de réseau
    quand le signal est faible, brouilleur actif ; multiplicateur serveur comspec_atak_native_battery_drain.
    Recharge à bord d'un véhicule moteur allumé. Alertes à 20 % et 5 % ; à 0 % le téléphone s'éteint.
    Appelée chaque seconde écran ouvert (barre d'état) et toutes les 10 s sinon. Retourne le niveau 0–100.
    Détail de la dernière mesure : missionNamespace COMSPEC_ATAK_BatteryInfo = [% consommé par minute (négatif = recharge), [[facteur, % / min]...]].
*/
private _level = missionNamespace getVariable ["COMSPEC_ATAK_Battery", 100];
if !((profileNamespace getVariable ["COMSPEC_ATAK_BatterySim", true]) && {missionNamespace getVariable ["comspec_atak_native_battery_sim", true]}) exitWith { 100 };
if !([player] call comspec_atak_native_fnc_hasDevice) exitWith { _level };
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _now = diag_tickTime;
private _dt = (_now - (missionNamespace getVariable ["COMSPEC_ATAK_BatteryTick", _now])) min 15;
missionNamespace setVariable ["COMSPEC_ATAK_BatteryTick", _now];

private _f = [];
private _open = !isNull ([] call comspec_atak_native_fnc_display);
private _night = profileNamespace getVariable ["COMSPEC_ATAK_NightMode", "OFF"];
if (_open) then {
    private _scr = [0.35, 0.55] select (_s getOrDefault ["interactive", false]);
    if (_night isNotEqualTo "OFF") then { _scr = _scr * 0.6; };
    _f pushBack [["Écran porté", "Écran en main"] select (_s getOrDefault ["interactive", false]), _scr];
} else {
    _f pushBack ["Veille", 0.12];
};
if (uiNamespace getVariable ["COMSPEC_ATAK_PhotoMode", false]) then { _f pushBack ["Appareil photo", 0.7]; };
if (!isNull (missionNamespace getVariable ["COMSPEC_ATAK_LivecamCam", objNull])) then { _f pushBack ["Live cam", 1.0]; };
// Radio data : chaque envoi de la dernière minute (fn_netSend) coûte un peu, plafonné.
private _tx = (missionNamespace getVariable ["COMSPEC_ATAK_NetTxAt", []]) select { _now - _x < 60 };
missionNamespace setVariable ["COMSPEC_ATAK_NetTxAt", _tx];
if ((count _tx) > 0) then { _f pushBack [format ["Envois de données (%1 / min)", count _tx], (0.03 * count _tx) min 0.4]; };
// Un téléphone qui cherche le réseau pousse son émetteur : signal faible ou absent, batterie qui fond.
private _lq = [] call comspec_atak_native_fnc_linkQuality;
if ((_lq getOrDefault ["sim", true]) && {(_lq getOrDefault ["bars", 4]) <= 1}) then { _f pushBack ["Recherche de réseau", 0.15]; };
if ((count (missionNamespace getVariable ["COMSPEC_ATAK_Route", createHashMap])) > 0) then { _f pushBack ["Guidage GPS", 0.25]; };
private _uid = getPlayerUID player;
private _t = [time, serverTime] select isMultiplayer;
if (((missionNamespace getVariable ["COMSPEC_ATAK_Jammers", []]) findIf { _x isEqualType [] && {(_x param [2, ""]) isEqualTo _uid} && {(_x param [4, 1e9]) > _t} }) >= 0) then { _f pushBack ["Brouilleur", 0.8]; };
private _rate = 0;
{ _rate = _rate + (_x select 1); } forEach _f;
_rate = _rate * (missionNamespace getVariable ["comspec_atak_native_battery_drain", 1]);
private _veh = vehicle player;
if (_veh isNotEqualTo player && {isEngineOn _veh}) then { _f pushBack ["Recharge véhicule", -3]; _rate = _rate - 3; };
missionNamespace setVariable ["COMSPEC_ATAK_BatteryInfo", [_rate, _f]];

private _old = _level;
_level = 0 max ((_level - _rate * _dt / 60) min 100);
missionNamespace setVariable ["COMSPEC_ATAK_Battery", _level];
{
    if (_old > _x && {_level <= _x}) then {
        ["WARNING", format ["Batterie faible : %1 %%", _x], 6, 50] call comspec_atak_native_fnc_notify;
        [] call comspec_atak_native_fnc_vibrate;
    };
} forEach [20, 5];
if (_old > 0 && {_level <= 0}) then {
    ["WARNING", "Batterie vide : le téléphone s'éteint", 6, 70] call comspec_atak_native_fnc_notify;
    [{ [] call comspec_atak_native_fnc_close; }, [], 2] call CBA_fnc_waitAndExecute;
};
_level
