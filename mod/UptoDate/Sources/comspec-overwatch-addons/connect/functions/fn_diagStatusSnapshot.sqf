/*
    Instantané pour le bandeau de dépannage et le journal :
    débit, volume, poste, versions, compte, erreurs, derniers retours.
    Retour : HashMap (lines, hudHtml).
*/
private _out = createHashMap;

private _fnCount = {
    params ["_key", ["_fallback", "—"]];
    private _v = missionNamespace getVariable [format ["COMSPEC_UplinkLast_%1", _key], -1];
    if (!(_v isEqualType 0) || {_v < 0}) exitWith { _fallback };
    str (round _v)
};

private _tr = missionNamespace getVariable ["COMSPEC_LinkTraffic", createHashMap];
if (!(_tr isEqualType createHashMap)) then { _tr = createHashMap; };
private _win = _tr getOrDefault ["window_in", []];
if (!(_win isEqualType [])) then { _win = []; };
diag_log format ["[COMSPEC Overwatch][DEBUG][Diag] win count=%1 sample=%2", count _win, _win select [0, 3 min count _win]];
diag_log format ["[COMSPEC Overwatch][DEBUG][Diag] snapshot → window_in filter (n=%1)", count _win];
private _now = diag_tickTime;
_win = _win select { ((_x select 0) + 10) >= _now };
private _sumIn = 0;
{ _sumIn = _sumIn + (_x select 1); } forEach _win;
diag_log format ["[COMSPEC Overwatch][DEBUG][Diag] snapshot ← window_in filter ok, n=%1 sum=%2", count _win, _sumIn];
private _span = 1;
if ((count _win) > 1) then {
    _span = ((((_win select ((count _win) - 1)) select 0) - ((_win select 0) select 0)) max 1);
};
private _rate = _sumIn / _span;
private _rateKo = (round ((_rate / 1000) * 10)) / 10;
private _totalIn = _tr getOrDefault ["bytes_in_total", 0];
if (!(_totalIn isEqualType 0)) then { _totalIn = 0; };
private _totalInKo = round (_totalIn / 1000);

diag_log format ["[COMSPEC Overwatch][DEBUG][Diag] snapshot → getPacketLossStats (win=%1)", count _win];
private _pkt = createHashMap;
if (!isNil "comspec_overwatch_connect_fnc_getPacketLossStats") then {
    _pkt = [] call comspec_overwatch_connect_fnc_getPacketLossStats;
};
if (isNil "_pkt") then { _pkt = createHashMap; };
diag_log format ["[COMSPEC Overwatch][DEBUG][Diag] snapshot ← getPacketLossStats ok, keys=%1", if (_pkt isEqualType createHashMap) then {count (keys _pkt)} else {0}];
diag_log format ["[COMSPEC Overwatch][DEBUG][Diag] pkt raw=%1", _pkt];
if (!(_pkt isEqualType createHashMap)) then { _pkt = createHashMap; };
private _sent = _pkt getOrDefault ["packets_sent_total", 0];
private _err = _pkt getOrDefault ["packet_loss_percent", 0];
if (!(_sent isEqualType 0)) then { _sent = 0; };
if (!(_err isEqualType 0)) then { _err = 0; };
_err = (round _err) max 0 min 100;

private _url = missionNamespace getVariable ["comspec_overwatch_api_url", ""];
if (!(_url isEqualType "")) then { _url = str _url; };
private _host = _url;
if ((_host select [0, 8]) isEqualTo "https://") then { _host = _host select [8]; };
if ((_host select [0, 7]) isEqualTo "http://") then { _host = _host select [7]; };
private _slash = _host find "/";
if (_slash >= 0) then { _host = _host select [0, _slash]; };
if (_host isEqualTo "") then { _host = "inconnue"; };

private _ow = getText (configFile >> "CfgPatches" >> "comspec_overwatch_connect" >> "versionStr");
if (_ow isEqualTo "") then { _ow = "—"; };
private _ath = getText (configFile >> "CfgPatches" >> "comspec_overwatch_atak_athena" >> "versionStr");
if (_ath isEqualTo "") then { _ath = "—"; };
private _extVer = missionNamespace getVariable ["COMSPEC_ExtVersionCached", ""];
if (_extVer isEqualTo "") then {
    diag_log "[COMSPEC Overwatch][DEBUG][Diag] snapshot → extensionStatus";
    private _ext = [false, "", ""];
    if (!isNil "comspec_overwatch_connect_fnc_extensionStatus") then {
        _ext = [] call comspec_overwatch_connect_fnc_extensionStatus;
    };
    if (isNil "_ext") then { _ext = [false, "", ""]; };
    diag_log format ["[COMSPEC Overwatch][DEBUG][Diag] snapshot ← extensionStatus ok, raw=%1", _ext];
    _ext params ["", "", ["_ping", ""]];
    if ((_ping isEqualType "") && {(_ping select [0, 3]) isEqualTo "OK|"}) then {
        _extVer = trim (_ping select [3]);
    } else {
        if (_ping isEqualType "") then { _extVer = _ping; };
    };
    if (_extVer isEqualTo "") then { _extVer = "—"; };
    missionNamespace setVariable ["COMSPEC_ExtVersionCached", _extVer, false];
} else {
    diag_log format ["[COMSPEC Overwatch][DEBUG][Diag] snapshot skip extensionStatus (cached=%1)", _extVer];
};

diag_log "[COMSPEC Overwatch][DEBUG][Diag] snapshot → getCallsign";
private _cs = "";
if (!isNil "comspec_overwatch_connect_fnc_getCallsign") then {
    _cs = [] call comspec_overwatch_connect_fnc_getCallsign;
};
if (isNil "_cs") then { _cs = ""; };
diag_log format ["[COMSPEC Overwatch][DEBUG][Diag] snapshot ← getCallsign ok, raw=%1", _cs];
if (!(_cs isEqualType "")) then { _cs = ""; };
private _linked = missionNamespace getVariable ["COMSPEC_OperatorLinked", false];
private _pid = missionNamespace getVariable ["COMSPEC_OperatorProfileId", 0];
if (!(_pid isEqualType 0)) then { _pid = 0; };
private _acct = "non identifié";
if (_linked) then {
    private _who = _cs;
    if (_who isEqualTo "" && {hasInterface} && {!isNull player}) then { _who = name player; };
    if (_who isEqualTo "") then { _who = "opérateur"; };
    _acct = format ["%1 · fiche %2", _who, _pid];
} else {
    if (_cs isNotEqualTo "") then { _acct = format ["%1 (non lié)", _cs]; };
};

private _debit = format ["%1 ko/s", _rateKo];
private _total = format ["%1 envois · %2 ko reçus", round _sent, _totalInKo];
private _mods = format ["Overwatch %1 · Athena %2 · liaison %3", _ow, _ath, _extVer];
private _returns = format [
    "messages %1 · marqueurs %2 · ordres %3 · formes %4",
    ["messages"] call _fnCount,
    ["marqueurs"] call _fnCount,
    ["ordres"] call _fnCount,
    ["formes"] call _fnCount
];

private _lines = [
    format ["Débit : %1", _debit],
    format ["Débit total transmis : %1", _total],
    format ["IP : %1", _host],
    format ["Version des mods : %1", _mods],
    format ["Compte identifié : %1", _acct],
    format ["Taux d’erreur : %1 %%", _err],
    format ["Retours : %1", _returns]
];
_out set ["lines", _lines];
_out set ["hudHtml", format [
    "<t align='left' size='0.72' color='#c5d4de'>Débit : %1<br/>Débit total transmis : %2<br/>IP : %3<br/>Version des mods : %4<br/>Compte identifié : %5<br/>Taux d’erreur : %6 %%<br/>Retours : %7</t>",
    _debit,
    _total,
    _host,
    _mods,
    _acct,
    _err,
    _returns
]];
_out
