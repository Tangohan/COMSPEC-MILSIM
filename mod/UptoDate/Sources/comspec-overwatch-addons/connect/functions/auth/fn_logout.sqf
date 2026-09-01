["COMSPECExtension" callExtension ["Logout", []]] call comspec_overwatch_connect_fnc_extResult;
missionNamespace setVariable ["comspec_overwatch_auth_state", "INITIALIZING", false];
missionNamespace setVariable ["COMSPEC_AthenaReady", false, false];
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

