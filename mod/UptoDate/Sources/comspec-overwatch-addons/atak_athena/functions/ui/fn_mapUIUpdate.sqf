/*
    Boucle UI carte : lit COMSPEC_MapState, dessine chrome COMSPEC (IDC 88500+).
    Params: [_disp, _mapCtrl, [_visX,_visY,_visW,_visH]]
*/
params [
    ["_disp", displayNull],
    ["_mapCtrl", controlNull],
    ["_vis", []]
];
if (isNull _disp || {isNull _mapCtrl}) exitWith {};
if (!(_vis isEqualType []) || {(count _vis) < 4}) exitWith {};

[] call comspec_overwatch_atak_athena_fnc_collectMapState;
[] call comspec_overwatch_atak_athena_fnc_applyMapLayers;
[_disp, _mapCtrl, _vis] call comspec_overwatch_atak_athena_fnc_createTopBar;
[_disp, _mapCtrl, _vis] call comspec_overwatch_atak_athena_fnc_createToolRail;
[_disp, _mapCtrl, _vis] call comspec_overwatch_atak_athena_fnc_createOperatorCard;
[_disp, _mapCtrl, _vis] call comspec_overwatch_atak_athena_fnc_createLayerPanel;
[_disp, _mapCtrl, _vis] call comspec_overwatch_atak_athena_fnc_createInspector;
[_disp, _mapCtrl, _vis] call comspec_overwatch_atak_athena_fnc_createTimeline;
[_disp, _mapCtrl, _vis] call comspec_overwatch_atak_athena_fnc_mapDebugOverlay;

if (isNil {uiNamespace getVariable "COMSPEC_MapUI_MouseWired"}) then {
    uiNamespace setVariable ["COMSPEC_MapUI_MouseWired", true];
    _mapCtrl ctrlAddEventHandler ["MouseButtonDown", {
        params ["_ctrl", "_btn", "_x", "_y", "_shift", "_ctrlKey", "_alt"];
        private _world = _ctrl ctrlMapScreenToWorld [_x, _y];
        if (_btn == 1) exitWith {
            [_ctrl, _world] call comspec_overwatch_atak_athena_fnc_mapContextMenu;
            true
        };
        if (_btn == 0 && {_shift}) exitWith {
            [_world] call comspec_overwatch_atak_athena_fnc_mapQuickPing;
            true
        };
        private _tool = missionNamespace getVariable ["COMSPEC_MapActiveTool", ""];
        switch (_tool) do {
            case "measure": { [_world] call comspec_overwatch_atak_athena_fnc_mapMeasure; true };
            case "coord": { [_world] call comspec_overwatch_atak_athena_fnc_mapCoordTool; true };
            case "route": { [_world] call comspec_overwatch_atak_athena_fnc_mapRoutePlanner; true };
            case "zone": { [_world] call comspec_overwatch_atak_athena_fnc_mapZones; true };
            default { false };
        }
    }];
    _mapCtrl ctrlAddEventHandler ["Draw", {
        params ["_map"];
        [_map] call comspec_overwatch_atak_athena_fnc_mapDrawOverlay;
    }];
};

private _track = missionNamespace getVariable ["COMSPEC_MapTrackUnit", objNull];
if (!isNull _track && {alive _track}) then {
    private _last = missionNamespace getVariable ["COMSPEC_MapTrackAt", -1e9];
    if ((time - _last) > 4) then {
        missionNamespace setVariable ["COMSPEC_MapTrackAt", time, false];
        _mapCtrl ctrlMapAnimAdd [0.35, ctrlMapScale _mapCtrl, getPos _track];
        ctrlMapAnimCommit _mapCtrl;
    };
};

private _replay = missionNamespace getVariable ["COMSPEC_MapReplay", []];
if (!(_replay isEqualType [])) then { _replay = []; };
private _state = missionNamespace getVariable ["COMSPEC_MapState", createHashMap];
private _snap = [];
{
    _snap pushBack [_x getOrDefault ["callsign", ""], _x getOrDefault ["pos", []]];
} forEach (_state getOrDefault ["units", []]);
_replay pushBack [time, _snap];
if ((count _replay) > 180) then { _replay deleteAt 0; };
missionNamespace setVariable ["COMSPEC_MapReplay", _replay, false];

[] call comspec_overwatch_atak_athena_fnc_athena_relabelBft;
