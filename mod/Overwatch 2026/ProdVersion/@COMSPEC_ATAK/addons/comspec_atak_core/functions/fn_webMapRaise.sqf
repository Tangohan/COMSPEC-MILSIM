private _display = uiNamespace getVariable ["COMSPEC_ATAK_Display", displayNull];
if (isNull _display) exitWith {false};

private _tex = ["mapTexture", "SAT"] call COMSPEC_fnc_getState;
private _idc = if (_tex isEqualTo "TOPO") then {2202} else {2201};

private _rect = missionNamespace getVariable ["COMSPEC_ATAK_LiveMapViewport", []];
if ((count _rect) < 4) then
{
    private _glass = missionNamespace getVariable ["COMSPEC_ATAK_HolePos", []];
    if ((count _glass) >= 4) then
    {
        _glass params ["_gx", "_gy", "_gw", "_gh"];
        _rect = [
            _gx + (_gw * 0.072),
            _gy + (_gh * 0.105),
            _gw * 0.643,
            _gh * 0.770
        ];
        missionNamespace setVariable ["COMSPEC_ATAK_LiveMapViewport", _rect, false];
    };
};

private _scale = 0.05;
private _center = if (isNull player) then {[worldSize / 2, worldSize / 2]} else {getPosATL player};
private _oldActive = _display displayCtrl _idc;
if (!isNull _oldActive) then
{
    _scale = (ctrlMapScale _oldActive) max 0.001 min 1;
};

{
    private _old = _display displayCtrl _x;
    if (!isNull _old) then { ctrlDelete _old; };
} forEach [2201, 2202];

private _sat = _display ctrlCreate ["RscMapControl", 2201];
private _topo = _display ctrlCreate ["RscMapControl", 2202];

{
    if (!isNull _x) then
    {
        if ((count _rect) >= 4) then
        {
            _x ctrlSetPosition _rect;
            _x ctrlCommit 0;
        };
        _x ctrlAddEventHandler ["Draw", { _this call COMSPEC_fnc_mapDrawOperational; }];
        _x ctrlAddEventHandler ["MouseButtonDown", { _this call COMSPEC_fnc_mapOnMouseButtonDown; }];
        _x ctrlShow false;
        _x ctrlEnable false;
    };
} forEach [_sat, _topo];

private _active = _display displayCtrl _idc;
if (isNull _active) exitWith {false};

_active ctrlShow true;
_active ctrlEnable true;
_active ctrlMapAnimAdd [0, _scale, _center];
ctrlMapAnimCommit _active;

true
