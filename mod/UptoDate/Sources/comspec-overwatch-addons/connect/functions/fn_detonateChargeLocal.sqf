/*
    Détonation locale d’une charge ACE (machine où l’objet est local).
    Params: [_explosive, _chargeId]
*/
params [["_explosive", objNull, [objNull]], ["_chargeId", "", [""]]];
if (isNull _explosive) exitWith { false };
if (_explosive getVariable ["COMSPEC_detonateFired", false]) exitWith { false };
_explosive setVariable ["COMSPEC_detonateFired", true, true];

if (!isNil "ace_explosives_fnc_scriptedExplosive") then {
    [_explosive, 0.05] call ace_explosives_fnc_scriptedExplosive;
} else {
    if (!isNil "ace_explosives_fnc_detonateExplosive") then {
        private _unit = if (!isNull player) then { player } else { objNull };
        [_unit, -1, [_explosive, 0.1], "ACE_Clacker"] call ace_explosives_fnc_detonateExplosive;
    } else {
        _explosive setDamage 1;
    };
};

true
