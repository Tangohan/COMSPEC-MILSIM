params ["_ctrlScreen", ["_mode", 0]];

if (_mode != 0) exitWith {false};
if (isNil "cTab_player" || {isNull cTab_player}) exitWith {false};
if !(missionNamespace getVariable ["cTabDrawMapTools", false]) exitWith {false};
if (isNil "cTabMapCursorPos" || {cTabMapCursorPos isEqualTo []}) exitWith {false};

private _display = ctrlParent _ctrlScreen;
if (isNull _display) exitWith {false};

private _veh = vehicle cTab_player;
private _playerPos = getPosASL _veh;
private _cursorPos = +cTabMapCursorPos;
private _color = missionNamespace getVariable ["cTabMicroDAGRhighlightColour", [1,1,0,1]];

[_display, _ctrlScreen, _playerPos, _cursorPos, 0, false] call cTab_fnc_drawHook;
_ctrlScreen drawIcon [
    "\A3\ui_f\data\map\Markers\Military\dot_CA.paa",
    _color,
    _cursorPos,
    18,
    18,
    0,
    "",
    1,
    missionNamespace getVariable ["cTabTxtSize", 0.04],
    "TahomaB",
    "center"
];

true
