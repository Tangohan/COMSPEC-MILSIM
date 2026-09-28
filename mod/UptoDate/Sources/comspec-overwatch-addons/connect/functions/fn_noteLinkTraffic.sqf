/*
    Compte le volume reçu depuis la liaison (caractères du retour)
    et les appels extension (métriques de charge session).
*/
params [["_text", "", [""]]];
if (!(_text isEqualType "")) then { _text = str _text; };
private _n = count _text;
private _now = diag_tickTime;
private _tr = missionNamespace getVariable ["COMSPEC_LinkTraffic", createHashMap];
if (!(_tr isEqualType createHashMap)) then { _tr = createHashMap; };
_tr set ["bytes_in_total", (_tr getOrDefault ["bytes_in_total", 0]) + _n];
_tr set ["calls_total", (_tr getOrDefault ["calls_total", 0]) + 1];
private _ok = (_text find "OK|") == 0;
if (_ok) then {
    _tr set ["calls_ok", (_tr getOrDefault ["calls_ok", 0]) + 1];
} else {
    if (_n > 0) then {
        _tr set ["calls_err", (_tr getOrDefault ["calls_err", 0]) + 1];
    };
};
private _win = _tr getOrDefault ["window_in", []];
if (!(_win isEqualType [])) then { _win = []; };
_win pushBack [_now, _n];
_win = _win select { ((_x select 0) + 10) >= _now };
if ((count _win) > 80) then {
    _win = _win select [((count _win) - 80) max 0, 80];
};
_tr set ["window_in", _win];
private _winCalls = _tr getOrDefault ["window_calls", []];
if (!(_winCalls isEqualType [])) then { _winCalls = []; };
_winCalls pushBack _now;
_winCalls = _winCalls select { (_x + 60) >= _now };
if ((count _winCalls) > 200) then {
    _winCalls = _winCalls select [((count _winCalls) - 200) max 0, 200];
};
_tr set ["window_calls", _winCalls];
_tr set ["calls_per_min", count _winCalls];
_tr set ["updated_at", _now];
missionNamespace setVariable ["COMSPEC_LinkTraffic", _tr, false];
_n
