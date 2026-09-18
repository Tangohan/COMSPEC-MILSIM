/*
    Titre Messagerie : depuis un fil, revient aux canaux ; sinon ouvre le tiroir.
*/
if (!hasInterface) exitWith {};

if ((missionNamespace getVariable ["COMSPEC_Comms_View", "list"]) isEqualTo "thread") then {
    [] call comspec_overwatch_atak_athena_fnc_athena_commsBack;
} else {
    if (missionNamespace getVariable ["COMSPEC_MessageHubOrigin", false]) then {
        ["message"] call comspec_overwatch_atak_athena_fnc_athena_openAtakApp;
    } else {
        call BCE_fnc_ATAK_toggleSubListMenu;
    };
};
