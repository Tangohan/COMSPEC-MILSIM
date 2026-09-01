if (!hasInterface) exitWith {};
if (!isNull (uiNamespace getVariable ["COMSPEC_AthenaAuth_Display", displayNull])) exitWith {};
createDialog "COMSPEC_AthenaAuth_Dialog";
private _d = uiNamespace getVariable ["COMSPEC_AthenaAuth_Display", displayNull];
if (isNull _d) exitWith {};
[] call comspec_overwatch_connect_fnc_pollAuth;
