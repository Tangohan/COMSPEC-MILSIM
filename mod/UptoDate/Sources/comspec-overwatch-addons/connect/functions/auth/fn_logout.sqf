["COMSPECExtension" callExtension ["Logout", []]] call comspec_overwatch_connect_fnc_extResult;

missionNamespace setVariable ["comspec_overwatch_auth_state", "INITIALIZING", false];
missionNamespace setVariable ["comspec_overwatch_auth_error", "", false];
missionNamespace setVariable ["COMSPEC_AthenaReady", false, false];
missionNamespace setVariable ["COMSPEC_SteamLinked", false, false];
missionNamespace setVariable ["COMSPEC_LinkState", "offline", false];
missionNamespace setVariable ["COMSPEC_LinkDetail", "", false];
missionNamespace setVariable ["comspec_overwatch_api_key", "", false];
missionNamespace setVariable ["comspec_overwatch_tenant_id", "", false];
missionNamespace setVariable ["comspec_profile_name", "", false];
missionNamespace setVariable ["comspec_profile_callsign", "", false];
missionNamespace setVariable ["comspec_tenant_name", "", false];
missionNamespace setVariable ["comspec_profile_unit", "", false];
missionNamespace setVariable ["comspec_profile_grade", "", false];
missionNamespace setVariable ["comspec_profile_role", "", false];
missionNamespace setVariable ["comspec_profile_function", "", false];
missionNamespace setVariable ["comspec_profile_avatar", "", false];
missionNamespace setVariable ["comspec_profile_avatar_local", "", false];
missionNamespace setVariable ["comspec_profile_avatar_loading", false, false];
missionNamespace setVariable ["COMSPEC_LastPlaytimeSent", -1, false];

profileNamespace setVariable ["comspec_overwatch_saved_api_key", ""];
saveProfileNamespace;

if (!isNil "cba_settings_fnc_set") then {
    ["comspec_overwatch_api_key", "", 0, "client", true] call cba_settings_fnc_set;
};

[] call comspec_overwatch_connect_fnc_updateStatusBadges;
["COMSPEC_AthenaLinkChanged", ["logout"]] call CBA_fnc_localEvent;

if (!isNil "comspec_overwatch_atak_athena_fnc_athena_updatePanel") then {
    [] call comspec_overwatch_atak_athena_fnc_athena_updatePanel;
};
