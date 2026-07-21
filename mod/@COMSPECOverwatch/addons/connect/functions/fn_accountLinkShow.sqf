/*
    Ouvre le dialog de connexion compte Athena (code de liaison).
*/
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

if (isNull (findDisplay 9972)) then {
    createDialog "COMSPEC_AccountLink_Dialog";
};
