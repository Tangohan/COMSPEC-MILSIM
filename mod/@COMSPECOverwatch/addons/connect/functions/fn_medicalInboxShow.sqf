/*
    Ouvre la boîte de triage des alertes médicales reçues.
*/
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

if !([] call comspec_overwatch_connect_fnc_canTriageMedical) exitWith {
    ["COMSPEC_Warning", ["Seul un médecin ou un chef d’équipe peut faire le triage des alertes."]] call comspec_overwatch_connect_fnc_showNotification;
};

if (isNull (findDisplay 9976)) then {
    createDialog "COMSPEC_MedicalInbox_Dialog";
};
