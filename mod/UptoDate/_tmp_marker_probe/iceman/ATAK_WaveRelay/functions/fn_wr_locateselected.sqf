private _state = call Iceman_fnc_wr_getState;
private _tab = _state getOrDefault ["tab", "home"];
private _selection = _state getOrDefault ["selection", 0];
private _target = [];
private _label = "selection";

if (_tab == "home") then {
    private _rows = _state getOrDefault ["lastHealthRows", []];
    if (_selection >= 0 && {_selection < count _rows}) then {
        private _row = _rows # _selection;
        _label = _row param [1, "health row"];
        private _targetRef = _row param [3, objNull];
        if (_targetRef isEqualType objNull) then {
            if (!isNull _targetRef) then {_target = getPosATL _targetRef};
        } else {
            if (_targetRef isEqualType [] && {(count _targetRef) >= 2}) then {
                _target = +_targetRef;
            };
        };
    };
} else {
if (_tab == "feeds") then {
    private _feeds = _state getOrDefault ["lastFeeds", []];
    if (_selection >= 0 && {_selection < count _feeds}) then {
        private _feed = _feeds # _selection;
        _label = _feed # 0;
        if ((_feed # 1) == "vehicle") then {
            private _obj = call compile (_feed # 2);
            if (!isNull _obj) then {_target = getPosATL _obj};
        };
        if ((_feed # 1) == "helmet") then {
            private _obj = call compile (_feed # 2);
            if (!isNull _obj) then {_target = getPosATL _obj};
        };
    };
} else {
    private _nodes = _state getOrDefault ["lastNodes", []];
    if (_selection >= 0 && {_selection < count _nodes}) then {
        private _node = _nodes # _selection;
        _target = ASLToATL (_node get "pos");
        _label = _node get "name";
    };
};
};

if (_target isEqualTo []) exitWith {
    ["WAVE RELAY", "No locatable item selected.", 3] call cTab_fnc_addNotification;
    false
};

private _display = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
if (isNull _display) exitWith {
    ["WAVE RELAY", "ATAK display is not open.", 3] call cTab_fnc_addNotification;
    false
};

private _map = controlNull;
{
    private _candidate = _display displayCtrl _x;
    if (!isNull _candidate) exitWith {_map = _candidate};
} forEach [1201, 1202];

if (isNull _map) exitWith {
    ["WAVE RELAY", "ATAK map is not available.", 3] call cTab_fnc_addNotification;
    false
};

_target set [2, 0];
_map ctrlMapAnimAdd [0.5, ctrlMapScale _map, _target];
ctrlMapAnimCommit _map;

["WAVE RELAY", format ["Located %1.", _label], 3] call cTab_fnc_addNotification;
true
