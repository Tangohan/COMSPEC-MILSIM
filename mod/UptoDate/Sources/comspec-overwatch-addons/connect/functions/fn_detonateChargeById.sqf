/*
    Trouve une charge par identifiant et la fait exploser sur son propriétaire.
    Athena n’est prévenue qu’après la détonation réelle (voir detonateChargeLocal).
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
    if (_n >= 12) then {
        ["WARN", "Explosives", "Charge ATAK introuvable en jeu — le poste reste en attente"] call comspec_overwatch_connect_fnc_log;
        ["La charge n’a pas été trouvée en jeu. Le poste ne doit pas indiquer qu’elle a explosé.", "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;
    } else {
        _fired set [_chargeId, false];
        missionNamespace setVariable ["COMSPEC_ExplosiveDetonateOrdered", _fired, false];
    };
    false
};

if (local _exp) then {
    [_exp, _chargeId] call comspec_overwatch_connect_fnc_detonateChargeLocal;
} else {
    [_exp, _chargeId] remoteExecCall ["comspec_overwatch_connect_fnc_detonateChargeLocal", _exp];
};

true
