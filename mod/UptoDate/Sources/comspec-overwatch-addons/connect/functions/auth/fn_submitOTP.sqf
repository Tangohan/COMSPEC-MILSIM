private _d = uiNamespace getVariable ["COMSPEC_AthenaAuth_Display", displayNull];
if (isNull _d) exitWith {};
private _email = trim (ctrlText (_d displayCtrl 9401));
private _code = trim (ctrlText (_d displayCtrl 9403));
if ((count _code) < 4) exitWith {
    (_d displayCtrl 9410) ctrlSetStructuredText parseText "<t align='center' size='0.55' color='#e8b84a'>Saisissez le code reçu par e-mail.</t>";
};
private _url = [] call comspec_overwatch_connect_fnc_portalUrl;
private _pack = [] call comspec_overwatch_connect_fnc_packVersion;
private _steam = if (!isNull player) then { getPlayerUID player } else { "" };
if ((count _steam) >= 8) then {
    ["COMSPECExtension" callExtension ["SetSteamId", [_steam]]] call comspec_overwatch_connect_fnc_extResult;
};
["COMSPECExtension" callExtension ["VerifyOtp", [_url, _email, _code, _pack, _steam]]] call comspec_overwatch_connect_fnc_extResult;
[] call comspec_overwatch_connect_fnc_pollAuth;
if ((missionNamespace getVariable ["comspec_overwatch_auth_state", ""]) isEqualTo "READY") then {
    missionNamespace setVariable ["comspec_overwatch_auth_otp_mode", false, false];
};
