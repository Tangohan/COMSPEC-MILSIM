params ["_ctrlScreen", ["_mode", 0]];

if (_mode != 0) exitWith {false};
if (isNil "cTab_player" || {isNull cTab_player}) exitWith {false};

private _veh = vehicle cTab_player;
private _pos = getPosASL _veh;
private _heading = direction _veh;
private _color = missionNamespace getVariable ["cTabMicroDAGRfontColour", [1,0.78,0,1]];
private _size = missionNamespace getVariable ["cTabTADownIconBaseSize", 26];
private _textSize = missionNamespace getVariable ["cTabTxtSize", 0.04];

_ctrlScreen drawIcon [
    "\A3\ui_f\data\map\VehicleIcons\iconmanvirtual_ca.paa",
    _color,
    _pos,
    _size,
    _size,
    _heading,
    "",
    1,
    _textSize,
    "TahomaB",
    "right"
];

true
