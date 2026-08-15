private _controls = call Iceman_fnc_drone_findControls;
private _state = call Iceman_fnc_drone_getState;

private _gridCtrl = _controls getOrDefault ["grid", controlNull];
private _altCtrl = _controls getOrDefault ["altitude", controlNull];
private _functionCtrl = _controls getOrDefault ["function", controlNull];
private _radiusCtrl = _controls getOrDefault ["radius", controlNull];

private _gridText = if (!isNull _gridCtrl) then {ctrlText _gridCtrl} else {""};
private _pos = [_gridText] call Iceman_fnc_drone_gridToPos;

private _altitude = if (!isNull _altCtrl) then {parseNumber ctrlText _altCtrl} else {_state getOrDefault ["altitude", 60]};
private _radius = if (!isNull _radiusCtrl) then {parseNumber ctrlText _radiusCtrl} else {_state getOrDefault ["radius", 150]};
_altitude = (_altitude max 15) min 500;
_radius = (_radius max 25) min 1000;

private _function = _state getOrDefault ["function", "move"];
if (!isNull _functionCtrl && {lbCurSel _functionCtrl >= 0}) then {
    private _data = _functionCtrl lbData (lbCurSel _functionCtrl);
    if (_data != "") then {_function = _data};
};

[_pos, _altitude, _function, _radius]
