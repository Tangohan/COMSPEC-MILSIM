/*
 * Relit la session émise par la liaison et met à jour le téléphone.
 * _homeApp : "desktop" après une reconnexion automatique, "account" après e-mail / mot de passe.
 */
params [["_homeApp", "desktop", [""]]];

private _authRaw = ["GetAuthState", []] call COMSPEC_fnc_extensionCall;
private _auth = [_authRaw] call COMSPEC_fnc_parseAuthState;

private _callsign = _auth getOrDefault ["callsign", "-"];
if (_callsign in ["", "-"]) then { _callsign = profileName; };
private _tenant = _auth getOrDefault ["tenant", "-"];

["callsign", _callsign] call COMSPEC_fnc_setState;
["athenaTenant", _tenant] call COMSPEC_fnc_setState;
["athenaName", _auth getOrDefault ["name", ""]] call COMSPEC_fnc_setState;
["athenaFirstName", _auth getOrDefault ["firstName", ""]] call COMSPEC_fnc_setState;
["athenaLastName", _auth getOrDefault ["lastName", ""]] call COMSPEC_fnc_setState;
["athenaCallsign", _auth getOrDefault ["callsign", ""]] call COMSPEC_fnc_setState;
["athenaGrade", _auth getOrDefault ["grade", ""]] call COMSPEC_fnc_setState;
["athenaUnit", _auth getOrDefault ["unit", ""]] call COMSPEC_fnc_setState;
["athenaRole", _auth getOrDefault ["role", ""]] call COMSPEC_fnc_setState;
["athenaFunction", _auth getOrDefault ["function", ""]] call COMSPEC_fnc_setState;
["athenaAvatar", _auth getOrDefault ["avatar", ""]] call COMSPEC_fnc_setState;
["athenaAccountId", _auth getOrDefault ["accountId", ""]] call COMSPEC_fnc_setState;
["athenaEmail", _auth getOrDefault ["email", ""]] call COMSPEC_fnc_setState;
["athenaDeviceId", _auth getOrDefault ["deviceId", ""]] call COMSPEC_fnc_setState;
["athenaSessionExpiresAt", _auth getOrDefault ["sessionExpiresAt", ""]] call COMSPEC_fnc_setState;
["athenaTenantSlug", _auth getOrDefault ["tenantSlug", ""]] call COMSPEC_fnc_setState;
["athenaExtensionVersion", _auth getOrDefault ["extensionVersion", ""]] call COMSPEC_fnc_setState;
["athenaDetectedModVersion", _auth getOrDefault ["detectedModVersion", ""]] call COMSPEC_fnc_setState;
["athenaMinModVersion", _auth getOrDefault ["minModVersion", ""]] call COMSPEC_fnc_setState;

private _reportedSteamLinked = _auth getOrDefault ["steamLinked", false];
private _steamNow = [] call COMSPEC_fnc_networkSteamUid;
private _effectiveSteamLinked = _reportedSteamLinked || {!(_steamNow isEqualTo "")};
["athenaSteamLinked", _effectiveSteamLinked] call COMSPEC_fnc_setState;
["athenaSteamDetected", !(_steamNow isEqualTo "")] call COMSPEC_fnc_setState;
["athenaSteamNotice", _auth getOrDefault ["steamNotice", ""]] call COMSPEC_fnc_setState;
["athenaAuthState", _auth getOrDefault ["state", ""]] call COMSPEC_fnc_setState;

missionNamespace setVariable ["COMSPEC_LinkState", "linked", false];

["ATHENA", format ["Connecté : %1", _callsign], "OK"] call COMSPEC_fnc_networkUpdateConnectionUI;
["ATHENA", _tenant] call COMSPEC_fnc_networkApplyMode;
[false] call COMSPEC_fnc_networkShowConnection;

private _appJs = if (_homeApp isEqualTo "account") then {"account"} else {"desktop"};
private _js = format [
    "if(window.COMSPEC_ATAK_setGate){window.COMSPEC_ATAK_setGate(false);}"
    + "if(window.COMSPEC_ATAK_forcePhoneApp){window.COMSPEC_ATAK_forcePhoneApp('%1');}",
    _appJs
];
[_js] call COMSPEC_fnc_webExecJS;
[] call COMSPEC_fnc_webPushState;

true
