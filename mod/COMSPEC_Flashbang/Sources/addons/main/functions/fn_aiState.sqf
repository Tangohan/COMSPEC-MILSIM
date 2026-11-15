/*
    comspec_fb_fnc_aiState : état mémorisé sur une IA par fn_aiHit, sous forme de tableau envoyable à une autre machine.
    [_unit] call comspec_fb_fnc_aiState
      -> [noms des compétences, valeurs d'origine, valeurs posées (relues), fonctions coupées (disableAI), posture d'origine, posture posée]
    Tableau vide : aucun effet en cours sur cette unité.
*/
params [["_unit", objNull, [objNull]]];
private _st = _unit getVariable ["comspec_fb_aiSkills", []];
if (isNull _unit || {_st isEqualTo []}) exitWith { [] };
[
    _st select 0, _st select 1, _st select 2,
    ((_unit getVariable ["comspec_fb_aiFeat", []]) select { ((_x select 0) select [0, 1]) != "@" }) apply { _x select 0 },
    _unit getVariable ["comspec_fb_aiPos", ""], _unit getVariable ["comspec_fb_aiPosSet", ""]
]
