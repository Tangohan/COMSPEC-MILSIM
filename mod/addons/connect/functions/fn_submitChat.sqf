private _display = uiNamespace getVariable ["COMSPEC_Chat_Display", displayNull];
if (isNull _display) exitWith {};

private _ctrl = _display displayCtrl 1400;
private _msg = ctrlText _ctrl;
if (_msg == "") exitWith { closeDialog 0; };

private _console = _display displayCtrl 1401;
private _consoleText = ctrlText _console;
_consoleText = _consoleText + "[COMMAND] " + _msg + "\n";
_console ctrlSetText _consoleText;

private _log = missionNamespace getVariable ["COMSPEC_Log", ""];
_log = _log + "[COMMAND] " + _msg + "\n";
missionNamespace setVariable ["COMSPEC_Log", _log, true];

private _logCtrl = _display displayCtrl 1402;
if (!isNull _logCtrl) then { _logCtrl ctrlSetText _log; };

[player, "CHAT", _msg] call comspec_overwatch_connect_fnc_sendIntel;
closeDialog 0;
