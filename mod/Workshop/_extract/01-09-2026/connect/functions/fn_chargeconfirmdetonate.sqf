/*
    Déclenche une charge ATAK du joueur, après confirmation ACE.
    Params: [_chargeId, _confirmed]
*/
params [["_chargeId", "", [""]], ["_confirmed", false, [true]]];
if (_chargeId isEqualTo "") exitWith { false };

if !([player] call comspec_overwatch_connect_fnc_hasTerminal) exitWith {
    ["Il faut une tablette ATAK pour déclencher cette charge.", "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;
    false
};

private _owned = [player] call comspec_overwatch_connect_fnc_chargeOwnedAtak;
private _mine = false;
{
    if ((_x select 0) isEqualTo _chargeId) then { _mine = true; };
} forEach _owned;
if (!_mine) exitWith {
    ["Cette charge n’est pas à vous.", "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;
    false
};

if (!_confirmed) exitWith {
    missionNamespace setVariable ["COMSPEC_ChargeConfirmId", _chargeId, false];
    missionNamespace setVariable ["COMSPEC_ChargeConfirmUntil", diag_tickTime + 8, false];
    ["Confirmez le déclenchement dans le menu, dans les 8 secondes.", "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;
    false
};

private _pending = missionNamespace getVariable ["COMSPEC_ChargeConfirmId", ""];
private _until = missionNamespace getVariable ["COMSPEC_ChargeConfirmUntil", -1e9];
missionNamespace setVariable ["COMSPEC_ChargeConfirmId", "", false];
missionNamespace setVariable ["COMSPEC_ChargeConfirmUntil", -1e9, false];
if (_pending isNotEqualTo _chargeId || {diag_tickTime > _until}) exitWith {
    ["Confirmation expirée. Recommencez.", "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;
    false
};

[_chargeId] call comspec_overwatch_connect_fnc_detonateChargeById
