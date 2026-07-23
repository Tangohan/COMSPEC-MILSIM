/*
    Force un poll ordres Athena puis rafraîchit le panneau.
*/
if (!hasInterface) exitWith {};

if (!isNil "comspec_overwatch_connect_fnc_pollOrders") then {
    [] call comspec_overwatch_connect_fnc_pollOrders;
};

[] call comspec_overwatch_atak_athena_fnc_athena_updatePanel;
["ATHENA", "Inbox actualisée.", 3] call cTab_fnc_addNotification;
