if (!hasInterface) exitWith {};

private _url = missionNamespace getVariable ["comspec_overwatch_api_url", ""];
if (_url == "") exitWith {};

private _result = "COMSPECExtension" callExtension ["Connect", [_url]];
private _parts = _result splitString "|";
private _prefix = if (count _parts >= 1) then { _parts select 0 } else { "" };
private _payload = if (count _parts >= 2) then { _parts select 1 } else { _result };

private _log = missionNamespace getVariable ["COMSPEC_Log", ""];
if (_prefix == "OK") then {
    _log = _log + "[SERVER] " + _payload + "\n";
} else {
    if (_prefix == "ERR") then {
        _log = _log + "[ERROR] " + _payload + "\n";
    };
};
missionNamespace setVariable ["COMSPEC_Log", _log, true];

private _display = uiNamespace getVariable ["COMSPEC_Chat_Display", displayNull];
if (!isNull _display) then {
    private _ctrl = _display displayCtrl 1402;
    if (!isNull _ctrl) then { _ctrl ctrlSetText _log; };
};
