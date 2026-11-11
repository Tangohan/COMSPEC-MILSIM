/*
    comspec_fb_fnc_playerReset : fin (ou interruption) des effets sur le joueur local. Arrête la boucle, détruit les
    post-traitements (ppEffectDestroy), retire le calque, coupe l'acouphène, rend la visée, les volumes et la radio.
    Appelée à la fin normale, à la mort, au changement d'unité, à l'ouverture de Zeus ou du mode spectateur.
*/
private _st = comspec_fb_state;
if (count _st == 0) exitWith {};
private _pfh = _st getOrDefault ["pfh", -1];
if (_pfh >= 0) then { [_pfh] call CBA_fnc_removePerFrameHandler };
{ ppEffectDestroy _x } forEach (_st getOrDefault ["pp", []]);
("comspec_fb_layer" call BIS_fnc_rscLayer) cutFadeOut 0;
(_st getOrDefault ["tinn", [-1]]) params ["_id"];
if (_id >= 0) then { stopSound _id };
if ("orig" in _st) then {
    [1, true] call comspec_fb_fnc_hearing;
    private _unit = _st getOrDefault ["unit", objNull];
    if (!isNull _unit) then {
        _unit setCustomAimCoef ((_st get "orig") getOrDefault ["aim", 1]);
        _unit setAnimSpeedCoef ((_st get "orig") getOrDefault ["anim", 1]);
    };
};
{ _st deleteAt _x } forEach (keys _st);
["joueur", "effets terminés, tout est rétabli"] call comspec_fb_fnc_log;
