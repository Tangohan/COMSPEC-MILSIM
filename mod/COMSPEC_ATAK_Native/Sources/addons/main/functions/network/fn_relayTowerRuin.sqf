/*
    Pylône relais détruit (événement Killed posé par fn_relayTower, serveur) : la ruine reste au sol.
    Le moteur enfonce le pylône puis pose la ruine de sa classe (DestructionEffects, towers/config.cpp). Si au bout de
    8 s aucune ruine n'est là (le pylône disparaissait sans laisser de débris), on pose COMSPEC_ATAK_RelayTower_XX_ruins
    (pylône plié et couché, tronçon resté debout, antennes et cornières éparpillées) à l'emplacement et au cap du pylône.
      [pylône] call comspec_atak_native_fnc_relayTowerRuin
*/
params [["_o", objNull, [objNull]]];
if (!isServer || {isNull _o}) exitWith {};
private _cls = format ["%1_ruins", typeOf _o];
if !(isClass (configFile >> "CfgVehicles" >> _cls)) exitWith {};
private _p = getPosATL _o;
_p set [2, 0];
[{
    params ["_cls", "_p", "_dir"];
    private _model = toLower (((getText (configFile >> "CfgVehicles" >> _cls >> "model")) splitString "\") param [-1, ""]);
    if !(".p3d" in _model) then { _model = _model + ".p3d"; };
    private _there = (nearestObjects [_p, [], 30]) findIf { (toLower ((getModelInfo _x) select 0)) isEqualTo _model } >= 0;
    if (_there) exitWith {};
    private _r = createVehicle [_cls, [0, 0, 0], [], 0, "CAN_COLLIDE"];
    _r setDir _dir;
    _r setPosATL _p;
    _r setVectorUp [0, 0, 1];
    { [_x, [[_r], false]] remoteExec ["addCuratorEditableObjects", 2]; } forEach allCurators;
}, [_cls, _p, getDir _o], 8] call CBA_fnc_waitAndExecute;
