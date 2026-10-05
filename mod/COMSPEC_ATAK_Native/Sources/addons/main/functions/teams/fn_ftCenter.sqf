/* Centre d'une équipe de feu (moyenne des membres vivants, ASL). Params : [groupe, id d'équipe ("" : tout le groupe)]. [] si personne. */
params [["_g", grpNull], ["_tid", ""]];
if (isNull _g) exitWith { [] };
private _m = (units _g) select { alive _x && {_tid isEqualTo "" || {(_x getVariable ["COMSPEC_FT", ""]) isEqualTo _tid}} };
if ((count _m) isEqualTo 0) exitWith { [] };
private _sum = [0, 0, 0];
{ _sum = _sum vectorAdd (getPosASL _x); } forEach _m;
_sum vectorMultiply (1 / count _m)
