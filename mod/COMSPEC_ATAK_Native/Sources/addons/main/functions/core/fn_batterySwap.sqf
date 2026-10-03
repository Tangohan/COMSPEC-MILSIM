/* Change la batterie du téléphone avec une batterie de rechange (réglage serveur « Batteries de rechange »). */
private _list = ((missionNamespace getVariable ["comspec_atak_native_battery_items", "ACE_UAVBattery"]) splitString ", ") apply { toLower _x };
private _item = (items player) select { (toLower _x) in _list } param [0, ""];
if (_item isEqualTo "") exitWith { ["WARNING", "Pas de batterie de rechange sur vous", 4, 30] call comspec_atak_native_fnc_notify; false };
player removeItem _item;
missionNamespace setVariable ["COMSPEC_ATAK_Battery", 100];
["SUCCESS", "Batterie changée · 100 %", 4, 30] call comspec_atak_native_fnc_notify;
true
