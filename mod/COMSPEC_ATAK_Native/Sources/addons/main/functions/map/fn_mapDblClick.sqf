/* Double clic gauche : modifier le marqueur pointé, ou en poser un nouveau à cet endroit. */
params ["_map","_button","_mx","_my"];
if (_button isNotEqualTo 0) exitWith { false };
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State",createHashMap];
// Mode MARQUEUR : chaque clic pose déjà un marqueur, deux clics rapprochés ne doivent pas ouvrir l'éditeur.
if ((_s getOrDefault ["mapMode","SELECT"]) in ["MARKER","LINE","DRAW","MEASURE","LOS","HOUSES","FLAT","HEIGHT","ROUTE","WP"]) exitWith { false };
private _m = [_map,[_mx,_my]] call comspec_atak_native_fnc_markerAt;
private _w = _map ctrlMapScreenToWorld [_mx,_my];
if (_m isNotEqualTo "" && {(_m find "_USER_DEFINED") isEqualTo 0}) exitWith { [_m] call comspec_atak_native_fnc_markerEditOpen; true };
// Ailleurs : pose d'un marqueur à cet endroit (palette ou éditeur complet, réglage d'addon « Menu de pose des marqueurs »).
["open", [_w select 0, _w select 1, 0]] call comspec_atak_native_fnc_markerPlace;
true
