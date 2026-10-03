/* Position monde sous le curseur : panneau curseur, trait jaune, mesure et dessin en cours. */
params ["_map", "_mx", "_my"];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _w = _map ctrlMapScreenToWorld [_mx, _my];
_s set ["cursorPos", _w];
["MOVE", [_w select 0, _w select 1, 0]] call comspec_atak_native_fnc_markerStroke;
[true] call comspec_atak_native_fnc_mapOverlayUpdate;
false
