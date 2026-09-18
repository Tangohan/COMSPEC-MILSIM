/*
    Compte le volume reçu depuis la liaison (caractères du retour).
    Sert au débit affiché pendant le dépannage.
*/
params [["_text", "", [""]]];
if (!(_text isEqualType "")) then { _text = str _text; };
private _n = count _text;
private _now = diag_tickTime;
private _tr = missionNamespace getVariable ["COMSPEC_LinkTraffic", createHashMap];
if (!(_tr isEqualType createHashMap)) then { _tr = createHashMap; };
_tr set ["bytes_in_total", (_tr getOrDefault ["bytes_in_total", 0]) + _n];
private _win = _tr getOrDefault ["window_in", []];
if (!(_win isEqualType [])) then { _win = []; };
_win pushBack [_now, _n];
_win = _win select { ((_x select 0) + 10) >= _now };
if ((count _win) > 80) then {
    _win = _win select [((count _win) - 80) max 0, 80];
};
_tr set ["window_in", _win];
missionNamespace setVariable ["COMSPEC_LinkTraffic", _tr, false];
_n
