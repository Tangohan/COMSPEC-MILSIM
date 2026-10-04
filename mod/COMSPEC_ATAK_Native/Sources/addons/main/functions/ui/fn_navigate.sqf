params [["_page", "LAUNCHER"], ["_push", true]];
_page = toUpper _page;
private _allowed = ["LAUNCHER", "RECENTS", "NOTIFS"] + (([] call comspec_atak_native_fnc_appList) apply { _x get "page" });
if !(_page in _allowed) then { _page = "LAUNCHER"; };

private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _history = _s getOrDefault ["history", []];
private _last = if ((count _history) > 0) then { _history select ((count _history) - 1) } else { "" };
if ((_push || {(count _history) isEqualTo 0}) && {_last isNotEqualTo _page}) then {
    _history pushBack _page;
    while {(count _history) > 20} do { _history deleteAt 0; };
};
_s set ["history", _history];
_s set ["activePage", _page];

[_page] call comspec_atak_native_fnc_pageRender;
["INFO", "UI", format ["Navigate %1", _page]] call comspec_atak_native_fnc_log;
true
