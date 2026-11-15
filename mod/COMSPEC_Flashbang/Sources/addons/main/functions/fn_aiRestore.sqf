/*
    comspec_fb_fnc_aiRestore : rend à une IA ses compétences, ses fonctions d'IA (disableAI) et sa posture d'avant le flash,
    et efface TOUTES les variables de l'effet (aucune fuite). Appelée sur la machine qui possède l'unité : en fin d'effet
    (fn_aiTick), ou par l'événement CBA « comspec_fb_aiRestore » quand l'IA a changé de machine pendant l'effet.
    - Une compétence qui n'a plus la valeur posée par le mod (un autre mod ou la mission l'a modifiée) n'est pas touchée.
    - Seules les fonctions coupées par ce mod sont rétablies (liste _fonctions) ; celles déjà coupées avant ne le sont jamais.
    - Posture : rendue seulement si elle est toujours celle que le mod a posée.
    - Unité morte : rendu sans effet visible ; unité supprimée : rien à rendre.
    [_unit, _état (fn_aiState ; [] = lire sur l'unité)] call comspec_fb_fnc_aiRestore
*/
params [["_unit", objNull, [objNull]], ["_st", [], [[]]]];
if (isNull _unit) exitWith {};
if (_st isEqualTo []) then { _st = [_unit] call comspec_fb_fnc_aiState };
_st params [["_names", [], [[]]], ["_orig", [], [[]]], ["_applied", [], [[]]], ["_feat", [], [[]]], ["_pos", "", [""]], ["_posSet", "", [""]]];
if (local _unit) then {
    {
        private _was = _applied param [_forEachIndex, -1];
        if (_was < 0 || {abs ((_unit skill _x) - _was) < 0.02}) then {
            _unit setSkill [_x, _orig param [_forEachIndex, _unit skill _x]];
        };
    } forEach _names;
    { _unit enableAI _x } forEach _feat;
    if (_pos isNotEqualTo "" && {_posSet isEqualTo "" || {toLower (unitPos _unit) == toLower _posSet}}) then {
        _unit setUnitPos (toUpper _pos);
    };
    // Geste réflexe encore joué : retour à la position de base (l'IA reprend ensuite son animation de combat).
    if (alive _unit && {((toLower (animationState _unit)) find "acts_civilshocked") == 0}) then {
        _unit playMoveNow (["AmovPercMstpSnonWnonDnon", "AmovPercMstpSrasWrflDnon"] select (primaryWeapon _unit != ""));
    };
};
{
    _unit setVariable [_x, nil];
} forEach ["comspec_fb_aiSkills", "comspec_fb_aiPeak", "comspec_fb_aiEnd", "comspec_fb_aiFeat", "comspec_fb_aiPos", "comspec_fb_aiPosSet", "comspec_fb_aiTickAt", "comspec_fb_aiRetry", "comspec_fb_aiWakes"];
["ia", format ["%1 : compétences et fonctions rétablies", _unit]] call comspec_fb_fnc_log;
