/*
    Change la batterie du téléphone avec une batterie de rechange (fn_batteryItem : Batterie ATAK, réglage serveur
    « Batteries de rechange », piles et batteries d'autres mods). Une batterie est consommée ; le téléphone,
    privé de courant pendant le changement, redémarre (écran de démarrage, fn_deviceOverlay).
*/
private _item = [player] call comspec_atak_native_fnc_batteryItem;
if (_item isEqualTo "") exitWith { ["WARNING", "Pas de batterie de rechange sur vous", 4, 30] call comspec_atak_native_fnc_notify; false };
player removeItem _item;
missionNamespace setVariable ["COMSPEC_ATAK_Battery", 100];
missionNamespace setVariable ["COMSPEC_ATAK_BatteryTick", diag_tickTime];
private _n = missionNamespace getVariable ["COMSPEC_ATAK_Device", createHashMap];
if ((_n getOrDefault ["damage", 0]) < 1) then {
    _n set ["offUntil", time + 6]; _n set ["offFrom", time]; _n set ["offReason", "Démarrage"];
    missionNamespace setVariable ["COMSPEC_ATAK_Device", _n];
};
private _name = getText (configFile >> "CfgWeapons" >> _item >> "displayName");
["SUCCESS", format ["Batterie changée (%1) · 100 %%", [_name, _item] select (_name isEqualTo "")], 4, 30] call comspec_atak_native_fnc_notify;
playSound "ClickSoft";
[] call comspec_atak_native_fnc_deviceOverlay;
true
