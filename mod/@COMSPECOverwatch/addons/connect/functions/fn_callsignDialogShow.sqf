/*
    Ouvre le dialog de saisie d’indicatif.
*/
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

if (isNull (findDisplay 9974)) then {
    createDialog "COMSPEC_Callsign_Dialog";
};
