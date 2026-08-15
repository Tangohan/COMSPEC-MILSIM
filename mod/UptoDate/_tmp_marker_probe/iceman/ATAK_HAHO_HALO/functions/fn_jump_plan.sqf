#include "..\script_component.hpp"

private _state = call Iceman_fnc_jump_getState;
private _jumpPoint = _state getOrDefault ["jumpPoint", []];
private _dropZone = _state getOrDefault ["dropZone", []];
private _waypoints = _state getOrDefault ["waypoints", []];
private _mode = _state getOrDefault ["mode", "HAHO"];

if (_jumpPoint isEqualTo []) then {
    _jumpPoint = getPosATL vehicle player;
    _state set ["jumpPoint", _jumpPoint];
};

if (_dropZone isEqualTo []) exitWith {
    ["JUMP", "Set a drop zone first.", 4] call cTab_fnc_addNotification;
    call Iceman_fnc_jump_updatePanel;
};

private _path = [_jumpPoint] + _waypoints + [_dropZone];
private _result = [_path, _mode] call Iceman_fnc_jump_calculatePlan;
_result params [
    "_segments",
    "_ticks",
    "_distance",
    "_canopyTime",
    "_requiredExitAGL",
    "_requiredPullAGL",
    "_avgGroundSpeedKph",
    "_warnings"
];

if (_segments isEqualTo []) exitWith {
    ["JUMP", "Jump point and drop zone are too close or invalid.", 4] call cTab_fnc_addNotification;
};

_state set ["path", _path];
_state set ["segments", _segments];
_state set ["ticks", _ticks];
_state set ["distance", _distance];
_state set ["canopyTime", _canopyTime];
_state set ["requiredExitAGL", _requiredExitAGL];
_state set ["requiredPullAGL", _requiredPullAGL];
_state set ["avgGroundSpeedKph", _avgGroundSpeedKph];
_state set ["warnings", _warnings];
_state set ["planned", true];
_state set ["selectMode", ""];

call Iceman_fnc_jump_updatePanel;

private _eta = [_canopyTime] call Iceman_fnc_jump_formatTime;
private _altText = if (_mode == "HAHO") then {
    format ["exit %1ft AGL", round (_requiredExitAGL / 0.3048)]
} else {
    format ["pull need %1m AGL", round _requiredPullAGL]
};

["JUMP", format ["%1 plan ready: %2 km, %3, canopy ETA %4.", _mode, (_distance / 1000) toFixed 1, _altText, _eta], 5] call cTab_fnc_addNotification;
