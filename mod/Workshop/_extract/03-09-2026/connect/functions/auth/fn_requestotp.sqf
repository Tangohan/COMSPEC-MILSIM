private _d = uiNamespace getVariable ["COMSPEC_AthenaAuth_Display", displayNull];
if (isNull _d) exitWith {};
private _email = trim (ctrlText (_d displayCtrl 9401));
if ((count _email) < 5) exitWith {
    (_d displayCtrl 9410) ctrlSetStructuredText parseText "<t align='center' size='0.55' color='#e8b84a'>Indiquez d’abord votre adresse e-mail.</t>";
};
private _url = [] call comspec_overwatch_connect_fnc_portalUrl;
["COMSPECExtension" callExtension ["RequestOtp", [_url, _email]]] call comspec_overwatch_connect_fnc_extResult;
(_d displayCtrl 9402) ctrlShow false;
(_d displayCtrl 9420) ctrlShow false;
(_d displayCtrl 9403) ctrlShow true;
(_d displayCtrl 9424) ctrlShow true;
(_d displayCtrl 9410) ctrlSetStructuredText parseText "<t align='center' size='0.55' color='#7aa89a'>Un code vient d’être envoyé. Saisissez-le ci-dessous.</t>";
