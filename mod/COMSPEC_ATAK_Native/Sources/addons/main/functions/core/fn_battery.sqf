/*
    Batterie simulée du téléphone (profil COMSPEC_ATAK_BatterySim et réglage serveur comspec_atak_native_battery_sim).
    Consommation par minute selon l'usage : veille, écran porté ou en main, mode nuit (écran sombre),
    appareil photo, live cam, guidage GPS, envois de données de la dernière minute, recherche de réseau
    quand le signal est faible, brouilleur actif. Réglages serveur : autonomie écran en main comspec_atak_native_battery_hours
    (3 h par défaut, tous les usages suivent la même proportion), charge de départ comspec_atak_native_battery_start,
    recharge en véhicule comspec_atak_native_battery_vehicle.
    Recharge à bord d'un véhicule moteur allumé ou par la batterie externe (fn_powerBank) . Alertes à 20 % et 5 % ; à 0 % le téléphone s'éteint (animation fn_powerFx)
    et reste inutilisable jusqu'à la recharge ou au changement de batterie (fn_batterySwap).
    Appelée chaque seconde écran ouvert (barre d'état) et toutes les 10 s sinon. Retourne le niveau 0–100.
    Détail de la dernière mesure : missionNamespace COMSPEC_ATAK_BatteryInfo = [% consommé par minute (négatif = recharge), [[facteur, % / min]...]].
*/
if (isNil { missionNamespace getVariable "COMSPEC_ATAK_Battery" }) then {
    missionNamespace setVariable ["COMSPEC_ATAK_Battery", 5 max (missionNamespace getVariable ["comspec_atak_native_battery_start", 100]) min 100];
};
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
    // Luminosité baissée (volet rapide) : l'écran consomme moins.
    _scr = _scr * (0.6 + 0.4 * ((profileNamespace getVariable ["COMSPEC_ATAK_Brightness", 1]) max 0.2 min 1));
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
// Les taux ci-dessus donnent environ 3 h écran en main (0,55 % / min) ; l'admin règle cette autonomie.
_rate = _rate * 3 / ((missionNamespace getVariable ["comspec_atak_native_battery_hours", 3]) max 0.5);
// Cellule abîmée (choc, balle, eau : fn_deviceDamage) : elle se vide jusqu'à trois fois plus vite.
private _wear = ((missionNamespace getVariable ["COMSPEC_ATAK_Device", createHashMap]) getOrDefault ["battery", 0]) * ([0, 1] select (missionNamespace getVariable ["comspec_atak_native_damage_sim", true]));
if (_wear > 0 && {_rate > 0}) then { _f pushBack ["Batterie endommagée", round (_rate * 2 * _wear * 100) / 100]; _rate = _rate * (1 + 2 * _wear); };
// Batterie externe fixée sur la plaque (fn_powerBank).
private _pb = ["rate", [_dt, _level]] call comspec_atak_native_fnc_powerBank;
if (_pb < 0) then { _f pushBack [["label"] call comspec_atak_native_fnc_powerBank, _pb]; _rate = _rate + _pb; };
private _veh = vehicle player;
private _vr = missionNamespace getVariable ["comspec_atak_native_battery_vehicle", 3];
if (_vr > 0 && {_veh isNotEqualTo player} && {isEngineOn _veh}) then { _f pushBack ["Recharge véhicule", -_vr]; _rate = _rate - _vr; };
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
    ["WARNING", ["Batterie vide : le téléphone s'éteint", "Batterie vide : le téléphone s'éteint. Mode secours SIM : les relais peuvent encore le localiser par son numéro ou son IMEI"] select (missionNamespace getVariable ["comspec_atak_native_sim_sos", true]), 6, 70] call comspec_atak_native_fnc_notify;
    ["publish"] call comspec_atak_native_fnc_simSos;
    // Écran qui baisse, icône « batterie vide », puis noir et téléphone rangé (fn_powerFx).
    ["empty"] call comspec_atak_native_fnc_powerFx;
};
_level
