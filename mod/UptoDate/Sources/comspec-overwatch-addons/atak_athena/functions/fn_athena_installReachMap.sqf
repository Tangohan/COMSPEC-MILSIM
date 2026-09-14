/*
    Accroche le dessin et le clic « zone de déplacement » sur la carte ATAK IceMan / cTab.
*/
if (!hasInterface) exitWith {};
if (missionNamespace getVariable ["COMSPEC_ReachMapHooked", false]) exitWith {};
missionNamespace setVariable ["COMSPEC_ReachMapHooked", true, false];

private _attach = {
    private _names = ["cTab_Android_dlg", "cTab_Tablet_dlg", "cTab_microDAGR_dlg"];
    {
        private _disp = uiNamespace getVariable [_x, displayNull];
        if (isNull _disp) then { continue };
        if (_disp getVariable ["COMSPEC_ReachMapDraw", false]) then { continue };
        private _map = controlNull;
        {
            private _c = _disp displayCtrl _x;
            if (!isNull _c && {ctrlType _c == 101}) exitWith { _map = _c; };
        } forEach [1200, 1201, 1773, 10, 50, 51, 100, 26109, 26110];
        if (isNull _map) then {
            for "_i" from 1 to 4000 do {
                private _c = _disp displayCtrl _i;
                if (!isNull _c && {ctrlType _c == 101}) exitWith { _map = _c; };
            };
        };
        if (isNull _map) then { continue };
        _map ctrlAddEventHandler ["Draw", {
            _this call comspec_overwatch_atak_athena_fnc_athena_hookReachMap;
        }];
        _map ctrlAddEventHandler ["MouseButtonDown", {
            params ["_mapCtrl", "_button", "_xC", "_yC", ["_shift", false]];
            if (_button != 0) exitWith { false };
            private _world = _mapCtrl ctrlMapScreenToWorld [_xC, _yC];
            if (_shift) exitWith {
                [_world] call comspec_overwatch_connect_fnc_superPingSend
            };
            [_world, _mapCtrl, _xC, _yC] call comspec_overwatch_connect_fnc_reachOverlayClick
        }];
        _disp setVariable ["COMSPEC_ReachMapDraw", true];
    } forEach _names;
};

private _refreshCache = {
    private _rows = [] call comspec_overwatch_connect_fnc_getUnitsList;
    missionNamespace setVariable ["COMSPEC_ReachCache", _rows, false];
    [] call comspec_overwatch_connect_fnc_reachOverlayUpdateMarkers;
};

[] call _attach;
[] call _refreshCache;
{ [_attach, [], _x] call CBA_fnc_waitAndExecute; } forEach [0.2, 0.8, 1.5, 3];
missionNamespace setVariable ["COMSPEC_ReachMapAttach", _attach, false];
missionNamespace setVariable ["COMSPEC_ReachCacheRefresh", _refreshCache, false];
[{
    [] call (missionNamespace getVariable ["COMSPEC_ReachMapAttach", {}]);
    private _open = !(isNil "cTabIfOpen")
        || {!isNull (findDisplay 9973)}
        || {!isNull (findDisplay 9974)}
        || {!((missionNamespace getVariable ["COMSPEC_ReachSelectedCs", ""]) isEqualTo "")};
    if (!_open) exitWith {};
    [] call (missionNamespace getVariable ["COMSPEC_ReachCacheRefresh", {}]);
}, 2, []] call CBA_fnc_addPerFrameHandler;
