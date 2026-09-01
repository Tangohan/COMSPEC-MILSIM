private _d = uiNamespace getVariable ["COMSPEC_AthenaAuth_Display", displayNull];
if (isNull _d) exitWith {};
private _email = trim (ctrlText (_d displayCtrl 9401));
private _pass = ctrlText (_d displayCtrl 9402);
if ((count _email) < 5 || {(count _pass) < 1}) exitWith {
    (_d displayCtrl 9410) ctrlSetStructuredText parseText "<t align='center' size='0.55' color='#e8b84a'>Indiquez votre adresse e-mail et votre mot de passe.</t>";
};
private _url = [] call comspec_overwatch_connect_fnc_portalUrl;
(_d displayCtrl 9410) ctrlSetStructuredText parseText "<t align='center' size='0.55' color='#7aa89a'>Authentification en cours…</t>";
["COMSPECExtension" callExtension ["AuthPassword", [_url, _email, _pass, "1.5.0"]]] call comspec_overwatch_connect_fnc_extResult;
[] call comspec_overwatch_connect_fnc_pollAuth;
