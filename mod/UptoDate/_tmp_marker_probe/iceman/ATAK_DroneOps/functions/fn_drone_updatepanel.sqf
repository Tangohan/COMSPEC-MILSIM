private _controls = call Iceman_fnc_drone_findControls;
private _state = call Iceman_fnc_drone_getState;

private _droneCtrl = _controls getOrDefault ["drone", controlNull];
private _gridCtrl = _controls getOrDefault ["grid", controlNull];
private _altCtrl = _controls getOrDefault ["altitude", controlNull];
private _functionCtrl = _controls getOrDefault ["function", controlNull];
private _radiusCtrl = _controls getOrDefault ["radius", controlNull];
private _statusCtrl = _controls getOrDefault ["status", controlNull];
private _infoCtrl = _controls getOrDefault ["info", controlNull];

private _drone = _state getOrDefault ["drone", objNull];
private _target = _state getOrDefault ["target", []];
private _altitude = _state getOrDefault ["altitude", 60];
private _radius = _state getOrDefault ["radius", 150];
private _function = _state getOrDefault ["function", "move"];
private _selectMode = _state getOrDefault ["selectMode", ""];

if (!isNull _droneCtrl) then {
    private _text = "No drone connected";
    if (!isNull _drone && {alive _drone}) then {
        _text = format ["%1 | %2", getText (configOf _drone >> "displayName"), [_drone] call Iceman_fnc_drone_posToGrid];
    };
    _droneCtrl ctrlSetStructuredText parseText format ["<t align='center'>%1</t>", _text];
};

if (!isNull _gridCtrl) then {
    private _gridText = "";
    if (_function == "protect") then {
        _gridText = [getPosASL player] call Iceman_fnc_drone_posToGrid;
    } else {
        _gridText = (["", [_target] call Iceman_fnc_drone_posToGrid] select !(_target isEqualTo []));
    };
    _gridCtrl ctrlSetText _gridText;
};

if (!isNull _altCtrl && {ctrlText _altCtrl == ""}) then {
    _altCtrl ctrlSetText str _altitude;
};

if (!isNull _radiusCtrl && {ctrlText _radiusCtrl == ""}) then {
    _radiusCtrl ctrlSetText str _radius;
};

if (!isNull _functionCtrl && {lbSize _functionCtrl == 0}) then {
    {
        _x params ["_label", "_value"];
        private _idx = _functionCtrl lbAdd _label;
        _functionCtrl lbSetData [_idx, _value];
        if (_value == _function) then {
            _functionCtrl lbSetCurSel _idx;
        };
    } forEach [
        ["Move", "move"],
        ["Loiter", "loiter"],
        ["Scan", "scan"],
        ["Protect", "protect"]
    ];
};

if (!isNull _functionCtrl && {lbCurSel _functionCtrl < 0}) then {
    _functionCtrl lbSetCurSel 0;
};

if (!isNull _statusCtrl) then {
    private _status = if (_selectMode == "target") then {
        "Tap map point"
    } else {
        if (isNull _drone || {!alive _drone}) then {"Connect a supported drone"} else {format ["Ready: %1", toUpper _function]}
    };
    _statusCtrl ctrlSetStructuredText parseText format ["<t align='center'>%1</t>", _status];
};

if (!isNull _infoCtrl) then {
    private _owner = if (!isNull _drone) then {_drone getVariable ["Iceman_DroneOps_ownerName", ""]} else {""};
    private _targetText = if (_function == "protect") then {
        format ["Protecting: %1", [getPosASL player] call Iceman_fnc_drone_posToGrid]
    } else {
        if (_target isEqualTo []) then {"Point: not set"} else {format ["Point: %1", [_target] call Iceman_fnc_drone_posToGrid]}
    };
    private _lines = [
        "ACE or scroll-wheel a deployed drone to connect.",
        "Camera remains available in Video Feeds.",
        _targetText,
        format ["Altitude: %1m AGL | Radius: %2m", _altitude, _radius]
    ];
    if (_owner != "") then {_lines pushBack format ["Owner: %1", _owner]};
    _infoCtrl ctrlSetStructuredText parseText (_lines joinString "<br/>");
};
