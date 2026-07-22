/*
    Ouvre le dialog de saisie d’indicatif (idd 9977).
*/
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

if (isNull (findDisplay 9977)) then {
    createDialog "COMSPEC_Callsign_Dialog";
};
