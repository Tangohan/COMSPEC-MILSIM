/* Bouton cloche de la barre d'état : ouvre le centre de notifications, ou le referme s'il est déjà affiché. */
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
if ((_s getOrDefault ["activePage", ""]) isEqualTo "NOTIFS") exitWith { [] call comspec_atak_native_fnc_back; };
["NOTIFS"] call comspec_atak_native_fnc_navigate;
