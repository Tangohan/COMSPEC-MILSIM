private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _history = _s getOrDefault ["history", []];
if ((count _history) > 1) then { _history deleteAt ((count _history) - 1); };
private _page = if ((count _history) > 0) then { _history select ((count _history) - 1) } else { "LAUNCHER" };
private _prev = _s getOrDefault ["activePage", ""];
private _r = [_page, false] call comspec_atak_native_fnc_navigate;
// La page quittée glisse vers la droite (fn_fx).
if (_page isNotEqualTo _prev) then { ["back"] call comspec_atak_native_fnc_fx; };
_r
