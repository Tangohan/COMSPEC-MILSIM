/*
    Remonte une erreur / un bug vers Athena (POST /api/atak/mod-report).
    Params: [_level, _channel, _message, _detail, _source]
      _level  — ERROR|WARN|INFO|BUG
      _source — auto|player|player-nolog|boot (player-nolog : la DLL ne joint pas le journal)
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
// Chiffres retirés de l'empreinte : un message avec horodatage / id / coordonnées en faisait une nouvelle à chaque fois.
private _msgFp = toString ((toArray toLower (_message select [0, 120])) select {_x < 48 || {_x > 57}});
private _fp = format ["%1|%2|%3", _severity, toLower _channel, _msgFp];
private _map = missionNamespace getVariable ["COMSPEC_DiagReportThrottle", createHashMap];
if (!(_map isEqualType createHashMap)) then { _map = createHashMap; };
// Table bornée (fuite mémoire sur une longue session sinon).
if (count _map > 200) then { _map = createHashMap; };
private _last = _map getOrDefault [_fp, -99999];
// Signalement joueur (« player », ou « player-nolog » quand le journal n'est pas joint) : jamais limité ici.
private _isPlayer = (_source select [0, 6]) isEqualTo "player";
if (!_isPlayer && {(diag_tickTime - _last) < 300}) exitWith { false };
// Garde globale : au plus un signalement auto / 60 s, toutes empreintes confondues.
if (!_isPlayer && {(diag_tickTime - (missionNamespace getVariable ["COMSPEC_DiagReportLastAuto", -99999])) < 60}) exitWith { false };
if (!_isPlayer) then { missionNamespace setVariable ["COMSPEC_DiagReportLastAuto", diag_tickTime, false]; };
_map set [_fp, diag_tickTime];
missionNamespace setVariable ["COMSPEC_DiagReportThrottle", _map, false];

private _url = [] call comspec_overwatch_connect_fnc_portalUrl;

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
// Version DLL : lue une seule fois par partie (évite un appel extension de plus par signalement).
private _extRaw = missionNamespace getVariable ["COMSPEC_DiagExtVersionRaw", ""];
if (_extRaw isEqualTo "") then {
    _extRaw = ["COMSPECExtension" callExtension ["GetExtensionVersion", []]] call comspec_overwatch_connect_fnc_extResult;
    missionNamespace setVariable ["COMSPEC_DiagExtVersionRaw", _extRaw, false];
};
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
if (_isPlayer) then {
    _fpServer = format ["player_%1", round diag_tickTime];
};
// Rapport auto : empreinte laissée vide, Athena la calcule sur le message complet. Les codes caractères tronqués
// à 40 chiffres ne couvraient que « error|tx|échec » : tous les échecs Tx d'un joueur se fondaient en un rapport.

// Envoi HTTP via extAwait : non bloquant depuis le spawn de fn_log (direct si appelé hors spawn, ex. bouton).
private _raw = [
    [
        "COMSPECExtension",
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
    ] call comspec_overwatch_connect_fnc_extAwait
] call comspec_overwatch_connect_fnc_extResult;

missionNamespace setVariable ["COMSPEC_LastDiagReport", _raw, false];

private _parts = _raw splitString "|";
private _prefix = if (count _parts >= 1) then { _parts select 0 } else { "" };
_prefix isEqualTo "OK"
