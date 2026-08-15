#include "..\script_component.hpp"

params ["_mode"];

private _state = call Iceman_fnc_jump_getState;
_state set ["selectMode", _mode];
if (_mode == "waypoint") then {
    _state set ["tab", "waypoints"];
};

call Iceman_fnc_jump_updatePanel;
private _target = switch (_mode) do {
    case "jumpPoint": {"jump point"};
    case "dropZone": {"drop zone"};
    case "waypoint": {"via point"};
    default {_mode};
};
["JUMP", format ["Tap the ATAK map to set %1.", _target], 4] call cTab_fnc_addNotification;
