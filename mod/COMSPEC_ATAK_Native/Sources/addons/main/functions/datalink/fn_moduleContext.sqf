/*
    Contexte commun des modules Zeus / Eden « ATAK · Données ».
    [_logic, _units, _activated] call comspec_atak_native_fnc_moduleContext → HashMap :
      run (ce poste exécute le module), zeus (posé par un Zeus), pos, targets (objets visés), target.
    Les modules sont isGlobal = 1 : seul le poste où la logique est locale exécute (le Zeus qui pose, le serveur en Eden).
    Objets visés : objet sous le module (Zeus), objets synchronisés (Eden). « near » : objet de mission le plus proche à moins de 3 m.
*/
params [["_logic", objNull, [objNull]], ["_units", [], [[]]], ["_activated", true, [true]]];
private _out = createHashMapFromArray [["run", false]];
if (isNull _logic || {!_activated} || {!local _logic}) exitWith { _out };
private _targets = [];
private _add = { params ["_o"]; if (_o isEqualType objNull && {!isNull _o} && {!(_o isKindOf "Logic")}) then { _targets pushBackUnique _o; }; };
[_logic getVariable ["bis_fnc_curatorAttachObject_object", objNull]] call _add;
[attachedTo _logic] call _add;
{ [_x] call _add; } forEach (synchronizedObjects _logic);
{ [_x] call _add; } forEach _units;
private _zeus = hasInterface && {!isNull curatorCamera || {!isNull (findDisplay 312)}};
_out set ["run", true];
_out set ["zeus", _zeus];
_out set ["logic", _logic];
_out set ["pos", getPosATL _logic];
_out set ["targets", _targets];
_out set ["target", _targets param [0, objNull]];
_out set ["near", ((nearestObjects [_logic, ["AllVehicles", "Thing", "Static"], 3]) select { !(_x isKindOf "Logic") && {!(_x isKindOf "CAManBase") || {!alive _x}} }) param [0, objNull]];
_out
