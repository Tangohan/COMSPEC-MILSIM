/*
    Déclenche toutes les charges ATAK du joueur, après confirmation.
    Params: [_unit, _confirmed]
*/
params [["_unit", objNull, [objNull]], ["_confirmed", false, [true]]];
if (isNull _unit) then { _unit = player; };
if (isNull _unit) exitWith { 0 };

if !([_unit] call comspec_overwatch_connect_fnc_hasTerminal) exitWith {
    ["Il faut une tablette ATAK pour déclencher ces charges.", "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;
    0
};

private _list = [_unit] call comspec_overwatch_connect_fnc_chargeOwnedAtak;
if (_list isEqualTo []) exitWith {
    ["Aucune charge ATAK à vous n’est armée.", "tactical", "info"] call comspec_overwatch_connect_fnc_announce;
    0
};

if (!_confirmed) exitWith {
    missionNamespace setVariable ["COMSPEC_ChargeConfirmAllUntil", diag_tickTime + 8, false];
    ["Confirmez : tout déclencher. Rouvrez le menu dans les 8 secondes.", "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;
    0
};

private _until = missionNamespace getVariable ["COMSPEC_ChargeConfirmAllUntil", -1e9];
missionNamespace setVariable ["COMSPEC_ChargeConfirmAllUntil", -1e9, false];
if (diag_tickTime > _until) exitWith {
    ["Confirmation expirée. Recommencez.", "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;
    0
};

private _n = 0;
{
    _x params ["_cid"];
    if ([_cid] call comspec_overwatch_connect_fnc_detonateChargeById) then {
        _n = _n + 1;
    };
} forEach _list;

if (_n > 0) then {
    [format ["Déclenchement groupé : %1 charge(s).", _n], "tactical", "info"] call comspec_overwatch_connect_fnc_announce;
} else {
    ["Aucune charge n’a pu être déclenchée.", "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;
};

_n
