/*
    Force un poll ordres + alertes tactiques Athena puis rafraîchit le panneau.
*/
if (!hasInterface) exitWith {};

if (!isNil "comspec_overwatch_connect_fnc_pollOrders") then {
    [] call comspec_overwatch_connect_fnc_pollOrders;
};
if (!isNil "comspec_overwatch_connect_fnc_pollMissionPlan") then {
    [] call comspec_overwatch_connect_fnc_pollMissionPlan;
};
if (!isNil "comspec_overwatch_connect_fnc_pollTacticalAlerts") then {
    [] call comspec_overwatch_connect_fnc_pollTacticalAlerts;
};

[] call comspec_overwatch_atak_athena_fnc_athena_updatePanel;
["ATHENA", "Inbox actualisée.", 3] call comspec_overwatch_connect_fnc_addScreenToast;
