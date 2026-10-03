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
// Le décalage entre le point visé et le centre visible est constant à l'écran : on le reprend du dernier recentrage
// pour viser juste du premier coup (sinon « Suivre ma position » saute puis se recale à chaque seconde).
(ctrlPosition _map) params ["_mx", "_my", "_mw", "_mh"];
private _vis = _map ctrlMapScreenToWorld [_mx + _mw / 2, _my + _mh / 2];
private _last = _map getVariable ["comspec_lastAim", []];
private _aim = _pos;
if ((count _last) isEqualTo 2) then {
    private _k = _z / ((ctrlMapScale _map) max 0.0001);
    _aim = [(_pos select 0) + ((_last select 0) - (_vis select 0)) * _k, (_pos select 1) + ((_last select 1) - (_vis select 1)) * _k];
};
_map setVariable ["comspec_lastAim", _aim];
// Un seul correctif à la fois : un recentrage plus récent remplace le précédent.
private _token = (_map getVariable ["comspec_centerToken", 0]) + 1;
_map setVariable ["comspec_centerToken", _token];
_map ctrlMapAnimAdd [0, _z, _aim];
ctrlMapAnimCommit _map;
// Correction : on attend la fin de l'animation, on mesure l'écart au centre visible et on recale (3 passes au plus).
[{
    params ["_args", "_pfh"];
    _args params ["_map", "_pos", "_z", "_tries", "", "", "_token"];
    if (isNull _map || {_tries > 40} || {(_map getVariable ["comspec_centerToken", 0]) isNotEqualTo _token}) exitWith { [_pfh] call CBA_fnc_removePerFrameHandler; };
    _args set [3, _tries + 1];
    if !(ctrlMapAnimDone _map) exitWith {};
    (ctrlPosition _map) params ["_x", "_y", "_w", "_h"];
    private _c = [_x + _w / 2, _y + _h / 2];
    private _sp = _map ctrlMapWorldToScreen _pos;
    if ((_sp distance2D _c) < 0.003 || {(_args param [4, 0]) >= 3}) exitWith { [_pfh] call CBA_fnc_removePerFrameHandler; };
    _args set [4, (_args param [4, 0]) + 1];
    // Le dernier point visé (_last) s'affiche en _st : on le décale du même écart que la cible.
    private _st = _map ctrlMapWorldToScreen (_args param [5, _pos]);
    private _fix = _map ctrlMapScreenToWorld [(_st select 0) + (_sp select 0) - (_c select 0), (_st select 1) + (_sp select 1) - (_c select 1)];
    _args set [5, _fix];
    _map setVariable ["comspec_lastAim", _fix];
    _map ctrlMapAnimAdd [0, _z, _fix];
    ctrlMapAnimCommit _map;
}, 0, [_map, _pos, _z, 0, 0, _aim, _token]] call CBA_fnc_addPerFrameHandler;
true
