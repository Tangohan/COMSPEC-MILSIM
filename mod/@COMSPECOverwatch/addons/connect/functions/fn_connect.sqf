if (!hasInterface) exitWith {};

private _url = missionNamespace getVariable ["comspec_overwatch_api_url", ""];
if (_url == "") exitWith {};

private _result = ["COMSPECExtension" callExtension ["Connect", [_url]]] call comspec_overwatch_connect_fnc_extResult;
private _parts = _result splitString "|";
private _prefix = if (count _parts >= 1) then { _parts select 0 } else { "" };
private _payload = if (count _parts >= 2) then { _parts select 1 } else { _result };

private _log = missionNamespace getVariable ["COMSPEC_Log", ""];
if (_prefix == "OK") then {
    _log = _log + "[SERVER] " + _payload + "\n";
    private _ipResult = ["COMSPECExtension" callExtension ["GetClientIp", []]] call comspec_overwatch_connect_fnc_extResult;
    private _ipParts = _ipResult splitString "|";
    private _ipPrefix = if (count _ipParts >= 1) then { _ipParts select 0 } else { "" };
    private _userIp = if (count _ipParts >= 2) then { _ipParts select 1 } else { "—" };
    if (_ipPrefix == "OK") then {
        missionNamespace setVariable ["COMSPEC_userIp", _userIp, true];
    } else {
        missionNamespace setVariable ["COMSPEC_userIp", "—", true];
    };
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
    private _ipCtrl = _display displayCtrl 1398;
    private _ip = missionNamespace getVariable ["COMSPEC_userIp", "—"];
    if (!isNull _ipCtrl) then { _ipCtrl ctrlSetText ("Votre IP: " + _ip); };
};
