/* Zoom autour du centre visible de la carte. Params : [facteur (0.5 = avant, 2 = arrière)] */
params [["_factor", 0.5]];
disableSerialization;
private _m = ([] call comspec_atak_native_fnc_display) displayCtrl 88530;
if (isNull _m) exitWith { false };
(ctrlPosition _m) params ["_x", "_y", "_w", "_h"];
[_m ctrlMapScreenToWorld [_x + _w / 2, _y + _h / 2], (((ctrlMapScale _m) * _factor) max 0.001) min 1] call comspec_atak_native_fnc_mapCenter
