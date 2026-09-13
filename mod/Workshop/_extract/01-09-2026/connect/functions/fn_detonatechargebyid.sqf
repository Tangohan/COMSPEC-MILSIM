/*
    Trouve une charge par identifiant et la fait exploser sur son propriétaire.
*/
params [["_chargeId", "", [""]]];
if (_chargeId isEqualTo "") exitWith { false };

private _fired = missionNamespace getVariable ["COMSPEC_ExplosiveDetonateOrdered", createHashMap];
if (!(_fired isEqualType createHashMap)) then { _fired = createHashMap; };
if ((_fired getOrDefault [_chargeId, false]) isEqualTo true) exitWith { false };
_fired set [_chargeId, true];
missionNamespace setVariable ["COMSPEC_ExplosiveDetonateOrdered", _fired, false];

private _exp = [_chargeId] call comspec_overwatch_connect_fnc_findChargeObject;
if (isNull _exp) exitWith {
    private _tries = missionNamespace getVariable ["COMSPEC_ExplosiveDetonateTries", createHashMap];
    if (!(_tries isEqualType createHashMap)) then { _tries = createHashMap; };
    private _n = (_tries getOrDefault [_chargeId, 0]) + 1;
    _tries set [_chargeId, _n];
    missionNamespace setVariable ["COMSPEC_ExplosiveDetonateTries", _tries, false];
    _fired set [_chargeId, false];
    missionNamespace setVariable ["COMSPEC_ExplosiveDetonateOrdered", _fired, false];
    if (_n >= 8) then {
        [objNull, 0, player, "detonated", _chargeId] call comspec_overwatch_connect_fnc_reportExplosiveTimer;
    };
    false
};

[_exp, _chargeId] remoteExecCall ["comspec_overwatch_connect_fnc_detonateChargeLocal", _exp];

private _outcomes = missionNamespace getVariable ["COMSPEC_ExplosiveOutcomes", createHashMap];
if (!(_outcomes isEqualType createHashMap)) then { _outcomes = createHashMap; };
_outcomes set [_chargeId, "detonated"];
missionNamespace setVariable ["COMSPEC_ExplosiveOutcomes", _outcomes, false];
[objNull, 0, player, "detonated", _chargeId] call comspec_overwatch_connect_fnc_reportExplosiveTimer;
true
