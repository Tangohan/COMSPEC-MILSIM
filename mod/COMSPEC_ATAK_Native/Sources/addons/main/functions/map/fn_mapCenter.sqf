/*
    Centre la carte du terminal sur un objet ou une position, avec un zoom optionnel.
    Une carte déplacée par ctrlSetPosition garde son ancien centre interne : ctrlMapAnimAdd place alors
    la cible ailleurs qu'au milieu. On anime, puis on corrige à l'image suivante d'après la position
    réellement affichée.
*/
params [["_target", player], ["_scale", -1]];
disableSerialization;
private _map = ([] call comspec_atak_native_fnc_display) displayCtrl 88530;
if (isNull _map) exitWith { false };
private _pos = if (_target isEqualType objNull) then { getPosASL _target } else { _target };
if ((count _pos) < 2) exitWith { false };
_pos = [_pos select 0, _pos select 1];
private _z = if (_scale > 0) then { _scale } else { ctrlMapScale _map };
_map ctrlMapAnimAdd [0, _z, _pos];
ctrlMapAnimCommit _map;
[{
    params ["_map", "_pos", "_z"];
    if (isNull _map) exitWith {};
    (ctrlPosition _map) params ["_x", "_y", "_w", "_h"];
    private _c = [_x + _w / 2, _y + _h / 2];
    private _sp = _map ctrlMapWorldToScreen _pos;
    if ((_sp distance2D _c) < 0.002) exitWith {};
    private _fix = _map ctrlMapScreenToWorld [2 * (_sp select 0) - (_c select 0), 2 * (_sp select 1) - (_c select 1)];
    _map ctrlMapAnimAdd [0, _z, _fix];
    ctrlMapAnimCommit _map;
}, [_map, _pos, _z]] call CBA_fnc_execNextFrame;
true
