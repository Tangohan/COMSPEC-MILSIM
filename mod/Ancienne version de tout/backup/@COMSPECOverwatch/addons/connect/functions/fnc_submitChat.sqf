private _display = uiNamespace getVariable ["COMSPEC_Chat_Display", displayNull];
if (isNull _display) exitWith {};

private _ctrl = _display displayCtrl 1400;
private _msg = ctrlText _ctrl;
if (_msg == "") exitWith { closeDialog 0; };

[player, "CHAT", _msg] call comspec_overwatch_connect_fnc_sendIntel;
closeDialog 0;
