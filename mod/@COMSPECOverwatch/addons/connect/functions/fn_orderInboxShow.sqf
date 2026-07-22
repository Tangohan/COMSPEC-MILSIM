/*
    Ouvre la boîte de réception des ordres.
*/
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

if (isNull (findDisplay 9975)) then {
    createDialog "COMSPEC_OrderInbox_Dialog";
};
