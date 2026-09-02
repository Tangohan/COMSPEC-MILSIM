/*
    Enfile une tenue sur le mannequin de l’arsenal (ou le joueur).
*/
params [["_loadout", [], [[]]], ["_name", "", [""]]];

if (_loadout isEqualTo []) exitWith { false };

private _unit = missionNamespace getVariable ["ace_arsenal_center", objNull];
if (isNull _unit) then { _unit = player; };
if (isNull _unit || {!alive _unit}) exitWith { false };

_unit setUnitLoadout [_loadout, true];

private _msg = if (_name isEqualTo "") then {
    "Tenue enfilée."
} else {
    format ["Tenue « %1 » enfilée.", _name]
};
[_msg, "arsenal", "ok", true] call comspec_overwatch_connect_fnc_announce;

if (!isNil "comspec_overwatch_connect_fnc_operatorProfileTick") then {
    [{
        ["loadout_changed"] call comspec_overwatch_connect_fnc_operatorProfileTick;
    }, [], 1] call CBA_fnc_waitAndExecute;
};

true
