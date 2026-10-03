/*
    Zoom de la carte du téléphone. Params : [facteur (0.5 = avant, 2 = arrière)]
    En main : autour du centre visible. Porté (mini) : autour du joueur, la carte continue à le suivre.
    Le zoom est mémorisé pour la prochaine ouverture de la carte.
*/
params [["_factor", 0.5]];
disableSerialization;
private _m = ([] call comspec_atak_native_fnc_display) displayCtrl 88530;
if (isNull _m) exitWith { false };
private _z = (((ctrlMapScale _m) * _factor) max 0.001) min 1;
uiNamespace setVariable ["COMSPEC_ATAK_MapScale", _z];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
if (!(_s getOrDefault ["interactive", false]) || {_s getOrDefault ["mapFollow", false]}) exitWith {
    [player, _z] call comspec_atak_native_fnc_mapCenter
};
(ctrlPosition _m) params ["_x", "_y", "_w", "_h"];
[_m ctrlMapScreenToWorld [_x + _w / 2, _y + _h / 2], _z] call comspec_atak_native_fnc_mapCenter
