if (!hasInterface) exitWith {};
if (!isNull (uiNamespace getVariable ["COMSPEC_AthenaAuth_Display", displayNull])) exitWith {};
createDialog "COMSPEC_AthenaAuth_Dialog";
private _d = uiNamespace getVariable ["COMSPEC_AthenaAuth_Display", displayNull];
if (isNull _d) exitWith {};
private _ver = format [
    "<t align='center' size='0.5' color='#5a7080'>Extension 1.18.0 • Mod 1.5.0</t>"
];
(_d displayCtrl 9430) ctrlSetStructuredText parseText _ver;
