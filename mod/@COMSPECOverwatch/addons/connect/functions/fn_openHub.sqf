/*
    Ouvre le menu hub ATAK (choix des vues Overwatch).
*/
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
if (!isNull (findDisplay 9969)) exitWith {};

createDialog "COMSPEC_Hub_Dialog";

[] spawn {
    uiSleep 0.05;
    [] call comspec_overwatch_connect_fnc_refreshLinkStatus;
};
