// Filet Mavic : s'execute APRES Mavic_Core (requiredAddons).
// Garantit les settings lus par fn_handleConnect meme si le XEH Mavic a echoue.

if (isNil "mavic_setting_enableConnectionDistance") then {
    missionNamespace setVariable ["mavic_setting_enableConnectionDistance", false];
};
if (isNil "mavic_setting_maxConnectionDistance") then {
    missionNamespace setVariable ["mavic_setting_maxConnectionDistance", 6000];
};
if (isNil "mavic_setting_showInterface") then {
    missionNamespace setVariable ["mavic_setting_showInterface", true];
};
if (isNil "mavic_setting_vanillaInterface") then {
    missionNamespace setVariable ["mavic_setting_vanillaInterface", false];
};

diag_log format [
    "[COMSPEC Overwatch][mavik_compat] PreInit OK — enableConn=%1 maxDist=%2",
    missionNamespace getVariable ["mavic_setting_enableConnectionDistance", "NIL"],
    missionNamespace getVariable ["mavic_setting_maxConnectionDistance", "NIL"]
];
