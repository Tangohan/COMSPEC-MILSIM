/*
    Module « Tableau des écrans TOC » (Zeus et Eden) : ouvre le tableau des écrans (fn_tocManager), une ligne par dalle,
    pour régler la source de chaque écran, poser, dupliquer, lier, générer une salle et ses flux.
    Zeus : lâché n'importe où, il ouvre le tableau (sur un écran : cet écran est la ligne choisie) ; il ne pose rien et
    se réutilise autant de fois qu'on veut. Eden : le tableau s'ouvre par le menu COMSPEC ATAK > Tableau des écrans TOC ;
    un module laissé dans la mission ne fait rien au lancement.
*/
params [["_logic", objNull, [objNull]], ["_units", [], [[]]], ["_activated", true, [true]]];
if (is3DEN) exitWith { true };
private _ctx = [_logic, _units, _activated] call comspec_atak_native_fnc_moduleContext;
if !(_ctx get "run") exitWith { true };
private _zeus = (_ctx get "zeus") || {hasInterface && {time > 1} && {!isNull (getAssignedCuratorLogic player)}};
private _target = (_ctx get "targets") param [0, objNull];
deleteVehicle _logic;
if (!_zeus || {!hasInterface}) exitWith { true };
[{ ["open", [_this]] call comspec_atak_native_fnc_tocManager; }, _target, 0.2] call comspec_atak_native_fnc_waitAndExec;
true
