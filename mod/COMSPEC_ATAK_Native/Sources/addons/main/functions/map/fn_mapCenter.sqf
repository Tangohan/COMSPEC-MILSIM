/* Centre la carte du terminal sur un objet ou une position, avec un zoom optionnel. */
params [["_target", player], ["_scale", -1]];
disableSerialization;
private _map = ([] call comspec_atak_native_fnc_display) displayCtrl 88530;
if (isNull _map) exitWith { false };
private _pos = if (_target isEqualType objNull) then { getPosASL _target } else { _target };
if ((count _pos) < 2) exitWith { false };
private _z = if (_scale > 0) then { _scale } else { ctrlMapScale _map };
_map ctrlMapAnimAdd [0.3, _z, _pos];
ctrlMapAnimCommit _map;
true
