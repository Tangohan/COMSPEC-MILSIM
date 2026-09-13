/*
    Lignes de messagerie pour la tablette TOC (UI Overwatch).
    Priorité : messages de groupe (web → jeu / terrain), puis journal radio.
    Retour : [[from, text, time, dir, kind, grid], ...]
      dir  : in | out | toc
      kind : group | radio | hq
*/
if (!hasInterface) exitWith { [] };

private _myCs = "";
if (!isNil "comspec_overwatch_connect_fnc_getCallsign") then {
    _myCs = [] call comspec_overwatch_connect_fnc_getCallsign;
};
if (_myCs isEqualTo "") then { _myCs = name player; };
private _myCsU = toUpper _myCs;

private _dirFor = {
    params ["_from"];
    private _u = toUpper (trim _from);
    if ((_u find "(TOC)") >= 0 || {_u isEqualTo "TOC"} || {(_u find "POSTE DE COMMANDEMENT") >= 0}) exitWith { "toc" };
    if (_u isEqualTo _myCsU) exitWith { "out" };
    "in"
};

private _rows = [];

private _groupMsgs = missionNamespace getVariable ["Iceman_ATAK_Group_messages", []];
if (_groupMsgs isEqualType []) then {
    {
        if (!(_x isEqualType []) || {(count _x) < 5}) then { continue };
        private _gTime = _x select 0;
        private _gSender = _x select 1;
        private _gId = _x select 2;
        private _gGrid = _x select 3;
        private _gText = trim (_x select 4);
        if (_gText isEqualTo "") then { continue };
        private _from = if (_gSender isEqualTo "") then { "Terrain" } else { _gSender };
        private _dir = [_from] call _dirFor;
        _rows pushBack [_from, _gText, _gTime, _dir, "group", _gGrid];
    } forEach _groupMsgs;
};

if ((count _rows) < 1) then {
    private _inbox = missionNamespace getVariable ["COMSPEC_Athena_AlertInbox", []];
    if (_inbox isEqualType []) then {
        {
            if (!(_x isEqualType []) || {(count _x) < 3}) then { continue };
            private _kind = toUpper (_x select 0);
            if (!(_kind in ["GROUP", "NOTIFY", "HQ"])) then { continue };
            private _label = if ((count _x) > 1) then { _x select 1 } else { "Message" };
            private _text = if ((count _x) > 2) then { trim (_x select 2) } else { "" };
            private _grid = if ((count _x) > 3) then { _x select 3 } else { "" };
            private _time = if ((count _x) > 4) then { _x select 4 } else { "" };
            private _from = if ((count _x) > 5) then { _x select 5 } else { _label };
            if (_text isEqualTo "") then { continue };
            private _k = if (_kind isEqualTo "HQ") then { "hq" } else {
                if (_kind isEqualTo "GROUP") then { "group" } else { "radio" }
            };
            _rows pushBack [_from, _text, _time, [_from] call _dirFor, _k, _grid];
        } forEach _inbox;
    };
};

if ((count _rows) > 24) then {
    _rows = _rows select [(count _rows) - 24, 24];
};

_rows
