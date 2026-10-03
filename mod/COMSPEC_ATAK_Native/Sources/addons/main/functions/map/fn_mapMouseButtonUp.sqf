params ["_map","_button","_mx","_my"];
if (_button isNotEqualTo 0) exitWith { false };
private _w = _map ctrlMapScreenToWorld [_mx,_my];
["UP", [_w select 0, _w select 1, 0]] call comspec_atak_native_fnc_markerStroke
