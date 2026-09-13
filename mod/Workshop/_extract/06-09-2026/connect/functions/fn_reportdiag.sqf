/*
    Remonte une erreur / un bug vers Athena (POST /api/atak/mod-report).
    Params: [_level, _channel, _message, _detail, _source]
      _level  — ERROR|WARN|INFO|BUG
      _source — auto|player|boot
*/
params [
    ["_level", "ERROR", [""]],
    ["_channel", "Core", [""]],
    ["_message", "", [""]],
    ["_detail", "", [""]],
    ["_source", "auto", [""]]
];

if (!hasInterface) exitWith { false };
if (_message isEqualTo "") exitWith { false };

private _levelKey = toUpper (trim _level);
private _severity = switch (_levelKey) do {
    case "ERROR";
    case "ERR";
    case "FATAL": { "error" };
    case "WARN";
    case "WARNING": { "warn" };
    case "BUG";
    case "PLAYER";
    case "USER": { "bug" };
    case "INFO": { "info" };
    default { "error" };
};

// Anti-spam local : même empreinte ≤ 1 envoi / 5 min
private _fp = format ["%1|%2|%3", _severity, toLower _channel, toLower (_message select [0, 120])];
private _map = missionNamespace getVariable ["COMSPEC_DiagReportThrottle", createHashMap];
if (!(_map isEqualType createHashMap)) then { _map = createHashMap; };
private _last = _map getOrDefault [_fp, -99999];
if (_source isNotEqualTo "player" && {(diag_tickTime - _last) < 300}) exitWith { false };
_map set [_fp, diag_tickTime];
missionNamespace setVariable ["COMSPEC_DiagReportThrottle", _map, false];

private _url = missionNamespace getVariable ["comspec_overwatch_api_url", ""];
if (!(_url isEqualType "")) then { _url = ""; };
_url = trim _url;
if (_url isEqualTo "") then {
    _url = trim (profileNamespace getVariable ["comspec_overwatch_saved_api_url", ""]);
};
if (_url isEqualTo "") then {
    _url = "https://athena.ttrd.fr/public";
};

private _steamUid = "";
if (!isNull player) then { _steamUid = getPlayerUID player; };
if (!(_steamUid isEqualType "")) then { _steamUid = ""; };
_steamUid = trim _steamUid;
if ((count _steamUid) < 15) then {
    _steamUid = trim (profileNamespace getVariable ["comspec_overwatch_saved_steam_uid", ""]);
};

private _playerName = profileName;
if (!isNull player) then {
    private _n = name player;
    if (_n isEqualType "" && {!(trim _n isEqualTo "")}) then { _playerName = _n; };
};

private _callsign = "";
if (!isNil "comspec_overwatch_connect_fnc_getCallsign") then {
    _callsign = [] call comspec_overwatch_connect_fnc_getCallsign;
};

private _modVersion = [] call comspec_overwatch_connect_fnc_getModVersion;

private _armaBuild = "";
private _pv = productVersion;
if (_pv isEqualType [] && {count _pv >= 4}) then {
    _armaBuild = str (_pv select 3);
};

private _extVersion = "";
private _extRaw = ["COMSPECExtension" callExtension ["GetExtensionVersion", []]] call comspec_overwatch_connect_fnc_extResult;
private _extParts = _extRaw splitString "|";
if ((count _extParts) >= 2 && {(_extParts select 0) isEqualTo "OK"}) then {
    private _label = _extParts select 1;
    private _bits = _label splitString " ";
    if ((count _bits) >= 2) then { _extVersion = _bits select 1; };
};

private _detailApi = _detail;
if (count _detailApi > 1800) then {
    _detailApi = (_detailApi select [0, 1800]) + "...";
};

private _fpServer = "";
if (_source isEqualTo "player") then {
    _fpServer = format ["player_%1", round diag_tickTime];
} else {
    {
        _fpServer = _fpServer + str _x;
    } forEach (toArray _fp);
    if (count _fpServer > 40) then { _fpServer = _fpServer select [0, 40]; };
};

private _raw = [
    "COMSPECExtension" callExtension [
        "ReportDiag",
        [
            _url,
            _severity,
            _channel,
            _message,
            _detailApi,
            _fpServer,
            _source,
            _steamUid,
            _steamUid,
            _playerName,
            _callsign,
            _modVersion,
            _armaBuild,
            _extVersion
        ]
    ]
] call comspec_overwatch_connect_fnc_extResult;

missionNamespace setVariable ["COMSPEC_LastDiagReport", _raw, false];

private _parts = _raw splitString "|";
private _prefix = if (count _parts >= 1) then { _parts select 0 } else { "" };
_prefix isEqualTo "OK"
