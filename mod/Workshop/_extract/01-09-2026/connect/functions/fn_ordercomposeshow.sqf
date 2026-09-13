/*
    Ouvre la mini-fenêtre d’émission d’ordre / FRAGO.
    Params optionnels : ["_prefKind"] — "FRAGO" | "MOVE" | "HOLD" | …
*/
params [["_prefKind", "", [""]]];

if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

if (!(missionNamespace getVariable ["comspec_overwatch_order_compose_enabled", true])) exitWith {
    ["L’émission d’ordres in-game est désactivée dans les options du mod.", "order", "warn"] call comspec_overwatch_connect_fnc_announce;
};

if !([] call comspec_overwatch_connect_fnc_canIssueOrder) exitWith {
    ["Seul le chef d’unité peut émettre un ordre depuis cette fenêtre.", "order", "warn"] call comspec_overwatch_connect_fnc_announce;
};

if (
    !isNil "comspec_overwatch_connect_fnc_hasTerminal"
    && { !([player] call comspec_overwatch_connect_fnc_hasTerminal) }
) exitWith {
    ["Équipement de liaison requis pour émettre un ordre.", "order", "warn"] call comspec_overwatch_connect_fnc_announce;
};

missionNamespace setVariable ["COMSPEC_OrderCompose_PrefKind", toUpper (trim _prefKind), false];
createDialog "COMSPEC_OrderCompose_Dialog";
