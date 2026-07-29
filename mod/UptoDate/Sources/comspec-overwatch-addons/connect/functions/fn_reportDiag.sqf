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

// Contexte : dernières lignes du tampon diag (sans secrets)
private _buf = missionNamespace getVariable ["COMSPEC_DiagLog", []];
private _tail = [];
if (_buf isEqualType [] && {(count _buf) > 0}) then {
    private _start = (count _buf) - 12;
    if (_start < 0) then { _start = 0; };
    _tail = _buf select [_start, 12];
};
private _ctxParts = [];
private _dq = toString [34];
{
    private _line = _x;
    if (_line find "api_key" < 0 && {_line find "token" < 0} && {_line find "Bearer" < 0}) then {
        private _safe = (_line splitString _dq) joinString "'";
        _ctxParts pushBack (_dq + _safe + _dq);
    };
} forEach _tail;
private _contextJson = "{" + _dq + "recent_log" + _dq + ":[" + (_ctxParts joinString ",") + "]";
if (_detail isNotEqualTo "") then {
    private _logEsc = (_detail splitString _dq) joinString "'";
    _logEsc = (_logEsc splitString toString [10]) joinString "\\n";
    if (count _logEsc > 12000) then { _logEsc = _logEsc select [0, 12000]; };
    _contextJson = _contextJson + "," + _dq + "session_log" + _dq + ":" + _dq + _logEsc + _dq;
};
_contextJson = _contextJson + "}";

private _detailApi = _detail;
if (count _detailApi > 7500) then {
    _detailApi = (_detailApi select [0, 7500]) + "...[voir journal complet]";
};

private _fpHash = _fp;
// Empreinte courte stable pour le serveur
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
            _extVersion,
            _contextJson
        ]
    ]
] call comspec_overwatch_connect_fnc_extResult;

private _parts = _raw splitString "|";
private _prefix = if (count _parts >= 1) then { _parts select 0 } else { "" };
_prefix isEqualTo "OK"
