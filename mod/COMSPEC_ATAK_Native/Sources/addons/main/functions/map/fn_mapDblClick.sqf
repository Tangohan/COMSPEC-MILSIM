/* Double clic gauche : modifier le marqueur pointé, ou en créer un nouveau à cet endroit. */
params ["_map","_button","_mx","_my"];
if (_button isNotEqualTo 0) exitWith { false };
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State",createHashMap];
if ((_s getOrDefault ["mapMode","SELECT"]) in ["LINE","DRAW","MEASURE","LOS","HOUSES","FLAT","HEIGHT","ROUTE","WP"]) exitWith { false };
private _m = [_map,[_mx,_my]] call comspec_atak_native_fnc_markerAt;
private _w = _map ctrlMapScreenToWorld [_mx,_my];
if (_m isNotEqualTo "" && {(_m find "_USER_DEFINED") isEqualTo 0}) exitWith { [_m] call comspec_atak_native_fnc_markerEditOpen; true };
["", [_w select 0, _w select 1, 0]] call comspec_atak_native_fnc_markerEditOpen;
true
