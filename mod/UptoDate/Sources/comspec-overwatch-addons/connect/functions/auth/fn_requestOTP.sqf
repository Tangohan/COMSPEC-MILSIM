private _d = uiNamespace getVariable ["COMSPEC_AthenaAuth_Display", displayNull];
if (isNull _d) exitWith {};
private _email = trim (ctrlText (_d displayCtrl 9401));
if ((count _email) < 5) exitWith {
    (_d displayCtrl 9410) ctrlSetStructuredText parseText "<t align='center' size='0.55' color='#e8b84a'>Indiquez d’abord votre adresse e-mail.</t>";
};
private _otpMode = missionNamespace getVariable ["comspec_overwatch_auth_otp_mode", false];
if (_otpMode) exitWith {
    missionNamespace setVariable ["comspec_overwatch_auth_otp_mode", false, false];
    (_d displayCtrl 9402) ctrlShow true;
    (_d displayCtrl 9420) ctrlShow true;
    (_d displayCtrl 9403) ctrlShow false;
    (_d displayCtrl 9424) ctrlShow false;
    (_d displayCtrl 9421) ctrlSetText "Code temporaire par e-mail";
    (_d displayCtrl 9410) ctrlSetStructuredText parseText "<t align='center' size='0.55' color='#7aa89a'>Saisissez votre mot de passe, ou demandez un nouveau code.</t>";
};
private _url = [] call comspec_overwatch_connect_fnc_portalUrl;
private _raw = ["COMSPECExtension" callExtension ["RequestOtp", [_url, _email]]] call comspec_overwatch_connect_fnc_extResult;
if (!(_raw isEqualType "")) then { _raw = str _raw; };
if ((toLower _raw) find "err|" == 0) exitWith {
    (_d displayCtrl 9410) ctrlSetStructuredText parseText "<t align='center' size='0.55' color='#e8b84a'>Impossible d’envoyer le code. Vérifiez l’adresse e-mail.</t>";
    [] call comspec_overwatch_connect_fnc_pollAuth;
};
missionNamespace setVariable ["comspec_overwatch_auth_otp_mode", true, false];
(_d displayCtrl 9402) ctrlShow false;
(_d displayCtrl 9420) ctrlShow false;
(_d displayCtrl 9403) ctrlShow true;
(_d displayCtrl 9424) ctrlShow true;
(_d displayCtrl 9421) ctrlSetText "Revenir au mot de passe";
(_d displayCtrl 9410) ctrlSetStructuredText parseText "<t align='center' size='0.55' color='#7aa89a'>Un code vient d’être envoyé. Saisissez-le ci-dessous, puis validez.</t>";
[] call comspec_overwatch_connect_fnc_pollAuth;
