// Pré-init bridge Athena ↔ ATAK Enhanced (cTab).
if (!isServer && !hasInterface) exitWith {};

missionNamespace setVariable ["COMSPEC_AthenaBridge_SuppressMirror", false, false];
missionNamespace setVariable ["COMSPEC_Athena_PanelTab", "all", false];
if (isNil { missionNamespace getVariable "COMSPEC_Athena_AlertInbox" }) then {
    missionNamespace setVariable ["COMSPEC_Athena_AlertInbox", [], false];
};
if (isNil { missionNamespace getVariable "COMSPEC_Athena_Notifications" }) then {
    missionNamespace setVariable ["COMSPEC_Athena_Notifications", [], false];
};
