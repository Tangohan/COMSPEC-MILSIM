// Pré-init bridge Athena ↔ ATAK Enhanced (cTab).
if (isClass (configFile >> "CfgPatches" >> "comspec_overwatch_atak_native")) exitWith {
    missionNamespace setVariable ["COMSPEC_ATAK_LegacyBootstrapSuppressed", true, false];
    diag_log "[COMSPEC ATAK NATIVE][WARN][BOOT] Legacy ATAK bootstrap suppressed";
};
if (!isServer && !hasInterface) exitWith {};

missionNamespace setVariable ["COMSPEC_AthenaBridge_SuppressMirror", false, false];
missionNamespace setVariable ["COMSPEC_Athena_PanelTab", "all", false];
missionNamespace setVariable ["COMSPEC_Athena_HomeSection", "fil", false];
if (isNil { missionNamespace getVariable "COMSPEC_Athena_AlertInbox" }) then {
    missionNamespace setVariable ["COMSPEC_Athena_AlertInbox", [], false];
};
if (isNil { missionNamespace getVariable "COMSPEC_Athena_Notifications" }) then {
    missionNamespace setVariable ["COMSPEC_Athena_Notifications", [], false];
};
