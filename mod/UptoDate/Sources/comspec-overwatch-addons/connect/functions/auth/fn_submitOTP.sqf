private _d = uiNamespace getVariable ["COMSPEC_AthenaAuth_Display", displayNull];
if (isNull _d) exitWith {};
private _email = trim (ctrlText (_d displayCtrl 9401));
private _code = trim (ctrlText (_d displayCtrl 9403));
if ((count _code) < 4) exitWith {
    [(_d displayCtrl 9410), "<t align='center' size='0.55' color='#e8b84a'>Saisissez le code reçu par e-mail.</t>"] call comspec_overwatch_connect_fnc_setPlainText;
};
private _url = [] call comspec_overwatch_connect_fnc_portalUrl;
private _pack = [] call comspec_overwatch_connect_fnc_packVersion;
private _steam = if (!isNull player) then { getPlayerUID player } else { "" };
[(_d displayCtrl 9410), "<t align='center' size='0.55' color='#7aa89a'>Vérification du code…</t>"] call comspec_overwatch_connect_fnc_setPlainText;
// OPT-08 : HTTP hors fil jeu.
[_url, _email, _code, _pack, _steam] spawn {
    params ["_url", "_email", "_code", "_pack", "_steam"];
    if ((count _steam) >= 8) then {
        [["COMSPECExtension", "SetSteamId", [_steam]] call comspec_overwatch_connect_fnc_extAwait] call comspec_overwatch_connect_fnc_extResult;
    };
    [["COMSPECExtension", "VerifyOtp", [_url, _email, _code, _pack, _steam], 40] call comspec_overwatch_connect_fnc_extAwait] call comspec_overwatch_connect_fnc_extResult;
    [] call comspec_overwatch_connect_fnc_pollAuth;
    if ((missionNamespace getVariable ["comspec_overwatch_auth_state", ""]) isEqualTo "READY") then {
        missionNamespace setVariable ["comspec_overwatch_auth_otp_mode", false, false];
    };
};
