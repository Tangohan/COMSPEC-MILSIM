/* Position monde sous le curseur, pour le panneau curseur et la mesure en cours. */
params ["_map", "_mx", "_my"];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
_s set ["cursorPos", _map ctrlMapScreenToWorld [_mx, _my]];
[true] call comspec_atak_native_fnc_mapOverlayUpdate;
false
