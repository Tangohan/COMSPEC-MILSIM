private _mode = ["networkMode", "NONE"] call COMSPEC_fnc_getState;

if (_mode isEqualTo "P2P") then
{
    [] call COMSPEC_fnc_p2pStop;
};

["Logout", []] call COMSPEC_fnc_extensionCall;

{
    [_x, ""] call COMSPEC_fnc_setState;
} forEach [
    "athenaName","athenaFirstName","athenaLastName","athenaCallsign","athenaGrade",
    "athenaUnit","athenaRole","athenaFunction","athenaAvatar","athenaAccountId",
    "athenaEmail","athenaDeviceId","athenaSessionExpiresAt","athenaTenant",
    "athenaTenantSlug","athenaAuthState","athenaSteamNotice"
];
["athenaSteamLinked", false] call COMSPEC_fnc_setState;
["athenaSteamDetected", false] call COMSPEC_fnc_setState;
missionNamespace setVariable ["COMSPEC_LinkState", "", false];

["NONE", ""] call COMSPEC_fnc_networkApplyMode;
[true] call COMSPEC_fnc_networkShowConnection;
[] call COMSPEC_fnc_webPushState;
[] call COMSPEC_fnc_refreshUI;

true
