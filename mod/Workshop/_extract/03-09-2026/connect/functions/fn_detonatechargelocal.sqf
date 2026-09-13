/*
    Détonation locale d’une charge ACE (machine où l’objet est local).
    Params: [_explosive, _chargeId]

    Ne prévient Athena qu’après disparition réelle de la charge.
    ACE scriptedExplosive passe par le serveur : si rien ne saute, repli local.
*/
params [["_explosive", objNull, [objNull]], ["_chargeId", "", [""]]];
if (isNull _explosive) exitWith { false };
if (_explosive getVariable ["COMSPEC_detonateFired", false]) exitWith { false };
_explosive setVariable ["COMSPEC_atakFireOk", true, true];
_explosive setVariable ["COMSPEC_detonateFired", true, true];

if (!isNil "ace_explosives_fnc_scriptedExplosive") then {
    [_explosive, 0, "#scripted"] call ace_explosives_fnc_scriptedExplosive;
} else {
    if (!isNil "ace_explosives_fnc_detonateExplosive") then {
        private _unit = if (isNull player) then { objNull } else { player };
        [_unit, -1, [_explosive, 0], "#scripted"] call ace_explosives_fnc_detonateExplosive;
    };
};

[{
    params ["_exp", "_cid"];
    if (_cid isEqualTo "") exitWith {};
    private _outcomes = missionNamespace getVariable ["COMSPEC_ExplosiveOutcomes", createHashMap];
    if (!(_outcomes isEqualType createHashMap)) then { _outcomes = createHashMap; };
    if ((_outcomes getOrDefault [_cid, ""]) isNotEqualTo "") exitWith {};

    private _mark = {
        params ["_id", "_map"];
        _map set [_id, "detonated"];
        missionNamespace setVariable ["COMSPEC_ExplosiveOutcomes", _map, false];
        [objNull, 0, player, "detonated", _id] call comspec_overwatch_connect_fnc_reportExplosiveTimer;
    };

    if (isNull _exp) then {
        [_cid, _outcomes] call _mark;
        ["INFO", "Explosives", "Charge ATAK sautée"] call comspec_overwatch_connect_fnc_log;
    } else {
        private _pos = getPosATL _exp;
        private _mag = _exp getVariable ["ace_explosives_magazineClass", ""];
        if (!(_mag isEqualType "") || {_mag isEqualTo ""}) then {
            _mag = _exp getVariable ["ace_explosives_class", ""];
        };
        if (!(_mag isEqualType "")) then { _mag = ""; };
        private _ammo = "";
        if (_mag isNotEqualTo "") then {
            _ammo = getText (configFile >> "CfgMagazines" >> _mag >> "ammo");
        };
        if (_ammo isEqualTo "") then { _ammo = typeOf _exp; };
        deleteVehicle _exp;
        if (_ammo isNotEqualTo "" && {isClass (configFile >> "CfgAmmo" >> _ammo)}) then {
            private _boom = _ammo createVehicle _pos;
            _boom setDamage 1;
        } else {
            "HelicopterExploSmall" createVehicle _pos;
        };
        [_cid, _outcomes] call _mark;
        ["WARN", "Explosives", "Charge ATAK : repli local (ACE n’a pas sauté)"] call comspec_overwatch_connect_fnc_log;
    };
}, [_explosive, _chargeId], 1.5] call CBA_fnc_waitAndExecute;

true
