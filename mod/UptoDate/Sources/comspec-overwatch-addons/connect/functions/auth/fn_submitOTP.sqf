private _d = uiNamespace getVariable ["COMSPEC_AthenaAuth_Display", displayNull];
if (isNull _d) exitWith {};
private _email = trim (ctrlText (_d displayCtrl 9401));
private _code = trim (ctrlText (_d displayCtrl 9403));
if ((count _code) < 4) exitWith {
    (_d displayCtrl 9410) ctrlSetStructuredText parseText "<t align='center' size='0.55' color='#e8b84a'>Saisissez le code reçu par e-mail.</t>";
};
private _url = [] call comspec_overwatch_connect_fnc_portalUrl;
["COMSPECExtension" callExtension ["VerifyOtp", [_url, _email, _code, "1.5.0"]]] call comspec_overwatch_connect_fnc_extResult;
[] call comspec_overwatch_connect_fnc_pollAuth;
