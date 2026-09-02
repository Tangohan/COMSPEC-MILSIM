/*
    Journal de session Overwatch pour la tuile Athena (même contenu que le fichier
    de diagnostic : liaison, erreurs, envois). Plus récent en tête.

    Retour : [[kind, title, detail, sortKey, meta], ...]
*/
if (!hasInterface) exitWith { [] };

private _filter = missionNamespace getVariable ["COMSPEC_Athena_LogFilter", "all"];
if !(_filter in ["all", "error", "tx", "medical", "photo"]) then { _filter = "all"; };

private _fnc_esc = {
    params ["_s"];
    if (!(_s isEqualType "")) then { _s = str _s; };
    _s = (_s splitString "&") joinString "&amp;";
    _s = (_s splitString "<") joinString "&lt;";
    _s = (_s splitString ">") joinString "&gt;";
    _s
};

private _fnc_humanize = {
    params ["_s"];
    if (!(_s isEqualType "") || {_s isEqualTo ""}) exitWith { _s };
    {
        _x params ["_from", "_to"];
        private _guard = 0;
        while { (_s find _from) >= 0 && {_guard < 8} } do {
            private _p = _s find _from;
            _s = (_s select [0, _p]) + _to + (_s select [_p + count _from, count _s]);
            _guard = _guard + 1;
        };
    } forEach [
        ["/public/api/atak/flight-manifest", "manifeste de vol"],
        ["/api/atak/flight-manifest", "manifeste de vol"],
        ["/public/api/atak/photos", "photo"],
        ["/api/atak/photos", "photo"],
        ["/public/api/atak/device-logs", "journal terminal"],
        ["/api/atak/device-logs", "journal terminal"],
        ["/public/api/atak/", "poste · "],
        ["/api/atak/", "poste · "],
        ["HTTP POST — code -1", "envoi vers le poste impossible"]
    ];
    _s
};

private _fnc_keep = {
    params ["_level", "_channel", "_filter"];
    if (_filter isEqualTo "all") exitWith { true };
    private _lv = toUpper _level;
    private _ch = toLower _channel;
    switch (_filter) do {
        case "error": { _lv in ["ERROR", "WARN", "WARNING"] };
        case "tx": { _ch in ["tx", "etat", "boot", "auth", "esc", "liaison", "core"] };
        case "medical": { _ch in ["medical", "respawn", "panic"] };
        case "photo": { _ch in ["photo", "recon", "capture", "iceman"] };
        default { true };
    }
};

private _fnc_parse = {
    params ["_raw"];
    if (!(_raw isEqualType "") || {_raw isEqualTo ""}) exitWith { [] };
    private _line = [_raw] call _fnc_humanize;
    private _time = "";
    if ((_line select [0, 1]) isEqualTo "[" && {(_line select [1, 2]) isEqualTo "20"}) then {
        private _close = _line find "]";
        if (_close > 8) then {
            _time = _line select [1, _close - 1];
            _line = trim (_line select [_close + 1, count _line]);
        };
    };
    private _tickPrefix = _line find "[COMSPEC";
    if (_tickPrefix > 0) then {
        if (_time isEqualTo "") then {
            _time = trim (_line select [0, _tickPrefix]);
        };
        _line = trim (_line select [_tickPrefix, count _line]);
    };
    private _guard = 0;
    while { (_line find "[COMSPEC") == 0 && {_guard < 3} } do {
        private _br = _line find "]";
        if (_br < 0) then {
            _guard = 99;
        } else {
            _line = trim (_line select [_br + 1, count _line]);
            _guard = _guard + 1;
        };
    };
    private _level = "INFO";
    private _channel = "Core";
    if ((_line select [0, 1]) isEqualTo "[") then {
        private _a = _line find "]";
        if (_a > 0) then {
            _level = _line select [1, _a - 1];
            _line = trim (_line select [_a + 1, count _line]);
        };
    };
    if ((_line select [0, 1]) isEqualTo "[") then {
        private _b = _line find "]";
        if (_b > 0) then {
            _channel = _line select [1, _b - 1];
            _line = trim (_line select [_b + 1, count _line]);
        };
    };
    if ((_line select [0, 2]) isEqualTo "| ") then {
        _line = trim (_line select [2, count _line]);
    };
    private _detailExtra = "";
    private _bar = _line find " | ";
    if (_bar >= 0) then {
        _detailExtra = trim (_line select [_bar + 3, count _line]);
        _line = trim (_line select [0, _bar]);
    };
    [_level, _channel, _line, _time, _detailExtra]
};

private _rawLines = [];
private _cached = missionNamespace getVariable ["COMSPEC_Athena_SessionLogCache", []];
private _cachedAt = missionNamespace getVariable ["COMSPEC_Athena_SessionLogAt", -1e9];
if ((diag_tickTime - _cachedAt) < 1.2 && {_cached isEqualType []} && {(count _cached) > 0}) then {
    _rawLines = _cached;
} else {
    private _raw = ["COMSPECExtension" callExtension ["GetLogTail", ["14000"]]] call comspec_overwatch_connect_fnc_extResult;
    if (_raw isEqualType "" && {(_raw select [0, 3]) isEqualTo "OK|"}) then {
        private _payload = _raw select [3, (count _raw) - 3];
        {
            if (_x isNotEqualTo "") then { _rawLines pushBack _x; };
        } forEach (_payload splitString toString [9]);
    };
    if ((count _rawLines) == 0) then {
        private _buf = missionNamespace getVariable ["COMSPEC_DiagLog", []];
        if (_buf isEqualType []) then { _rawLines = +_buf; };
    };
    missionNamespace setVariable ["COMSPEC_Athena_SessionLogCache", _rawLines, false];
    missionNamespace setVariable ["COMSPEC_Athena_SessionLogAt", diag_tickTime, false];
};

private _entries = [];
{
    private _parsed = [_x] call _fnc_parse;
    if ((count _parsed) < 3) then { continue };
    _parsed params ["_level", "_channel", "_msg", ["_time", ""], ["_extra", ""]];
    if (_msg isEqualTo "" && {_extra isEqualTo ""}) then { continue };
    if !([_level, _channel, _filter] call _fnc_keep) then { continue };

    private _lvU = toUpper _level;
    private _kind = switch (_lvU) do {
        case "ERROR";
        case "ERR";
        case "FATAL": { "error" };
        case "WARN";
        case "WARNING": { "warn" };
        case "DEBUG";
        case "TRACE": { "debug" };
        default { "info" };
    };
    private _clock = _time;
    if ((count _clock) >= 19) then {
        // 2026-09-01 20:15:46.814 → 20:15:46
        _clock = _time select [11, 8];
    };
    if (_clock isEqualTo "") then { _clock = "--:--:--"; };
    private _title = format ["%1 %2 %3  %4", _clock, _lvU, _channel, _msg];
    if ((count _title) > 72) then {
        _title = (_title select [0, 69]) + "…";
    };
    private _color = switch (_kind) do {
        case "error": { "#ff8a7a" };
        case "warn": { "#ffd27a" };
        case "debug": { "#8aa0b4" };
        default { "#e8f4f0" };
    };
    private _detail = format [
        "<t color='%1' size='1.05'>%2 · %3</t><br/><t color='#8aa0b4'>Heure</t>  %4<br/><br/><t color='#e8f4f0'>%5</t>",
        _color,
        _lvU,
        [_channel] call _fnc_esc,
        if (_time isEqualTo "") then { "—" } else { [_time] call _fnc_esc },
        [_msg] call _fnc_esc
    ];
    if (_extra isNotEqualTo "") then {
        _detail = _detail + format ["<br/><br/><t color='#8aa0b4'>Détail</t><br/><t color='#c8d0d6'>%1</t>", [_extra] call _fnc_esc];
    };
    _entries pushBack [_kind, _title, _detail, _time, []];
} forEach _rawLines;

reverse _entries;
if ((count _entries) > 80) then {
    _entries = _entries select [0, 80];
};
_entries
