/*
    Retire une tenue de la communauté (uniquement celles que vous avez envoyées).
*/
params [["_id", "", [""]]];

if (!hasInterface) exitWith { false };
if (_id isEqualTo "") exitWith { false };

if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {
    ["Liaison Athena requise pour retirer une tenue de la communauté.", "arsenal", "warn", true] call comspec_overwatch_connect_fnc_announce;
    false
};

private _parsed = ["DeleteWardrobe", [_id], "DeleteWardrobe", false, false, "arsenal", false]
    call comspec_overwatch_connect_fnc_callExtLogged;
_parsed params [["_success", false], ["_status", ""], ["_detail", ""]];

if (_success) exitWith { true };

private _why = toLower (format ["%1 %2", _status, _detail]);
if ((_why find "unauthorized") >= 0 || {(_why find "forbidden") >= 0}) then {
    ["Vous ne pouvez retirer que les tenues que vous avez envoyées.", "arsenal", "warn", true] call comspec_overwatch_connect_fnc_announce;
} else {
    ["Impossible de retirer cette tenue de la communauté.", "arsenal", "warn", true] call comspec_overwatch_connect_fnc_announce;
};
false
