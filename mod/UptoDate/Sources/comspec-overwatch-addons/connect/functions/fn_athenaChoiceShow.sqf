/*
    Ouvre la question « Votre unité utilise-t-elle le service Athéna ? » (menu principal ou mission).
    Params : [_force] — false : seulement si le joueur n'a pas encore choisi. true : rouvrir pour changer d'avis.
    Retour : true si l'écran est ouvert.
*/
params [["_force", false]];
if (!hasInterface) exitWith { false };
if (!_force && {([] call comspec_overwatch_connect_fnc_athenaMode) isNotEqualTo ""}) exitWith { false };
if (!isNull (uiNamespace getVariable ["COMSPEC_AthenaChoice_Display", displayNull])) exitWith { true };

private _parent = displayNull;
{
    if (!isNull findDisplay _x) exitWith { _parent = findDisplay _x; };
} forEach [49, 12, 46, 0];
// Menu principal ou menu Échap : écran enfant. Sinon (mission sans menu ouvert) : dialogue.
if (!isNull _parent && {!(_parent isEqualTo findDisplay 46)}) then {
    _parent createDisplay "COMSPEC_AthenaChoice_Dialog";
} else {
    createDialog "COMSPEC_AthenaChoice_Dialog";
};
!isNull (uiNamespace getVariable ["COMSPEC_AthenaChoice_Display", displayNull])
