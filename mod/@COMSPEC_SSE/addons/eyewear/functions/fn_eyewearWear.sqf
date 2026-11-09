/*
    Slot virtuel COMSPEC (expérimental) : porter les lunettes <id>.
    L'objet d'inventaire <classe>_Item quitte le sac (il y revient au retrait, fn_eyewearRemove) ; l'état est publié sur
    l'unité (comspec_sse_eyewear_worn, public, repris par les joueurs qui se connectent en cours de partie) et chaque
    machine dessine l'objet 3D (fn_eyewearRender). Les slots visage et JVN restent libres.
    À appeler là où l'unité est locale. Renvoie vrai si les lunettes sont portées.
*/
params [["_unit", objNull, [objNull]], ["_id", "", [""]]];
if (isNull _unit || {!alive _unit} || {!local _unit}) exitWith { false };
private _e = (([] call comspec_sse_fnc_eyewearCatalog) select 0) getOrDefault [_id, createHashMap];
if (count _e isEqualTo 0) exitWith { false };
private _item = _e get "item";
if !(_item in (items _unit)) exitWith { false };
if ((_unit getVariable ["comspec_sse_eyewear_worn", ""]) isNotEqualTo "") then {
    [_unit, true] call comspec_sse_fnc_eyewearRemove;
};
_unit removeItem _item;
_unit setVariable ["comspec_sse_eyewear_worn", _id, true];
if (_unit isEqualTo player) then { [] call comspec_sse_fnc_eyewearRender; };
true
