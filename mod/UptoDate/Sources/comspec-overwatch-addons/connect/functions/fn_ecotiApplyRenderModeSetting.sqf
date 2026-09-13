/*
    Applique le mode de rendu des pastilles situation : world3d | screen2d.
    Params: [_mode, _persist]
*/
params [["_mode", "world3d", [""]], ["_persist", true, [true]]];

if (!(_mode isEqualType "") || {_mode isEqualTo ""}) then { _mode = "world3d"; };
_mode = toLower _mode;
if (_mode in ["screen", "hud2d", "2d"]) then { _mode = "screen2d"; };
if !(_mode in ["world3d", "screen2d"]) then { _mode = "world3d"; };

missionNamespace setVariable ["comspec_overwatch_ecoti_render_mode", _mode, false];

if (_persist) then {
    profileNamespace setVariable ["COMSPEC_EcotiRenderMode", _mode];
    saveProfileNamespace;
};

if (!isNil "cba_settings_fnc_set") then {
    ["comspec_overwatch_ecoti_render_mode", _mode, 2, "client"] call cba_settings_fnc_set;
};

if (_mode isEqualTo "world3d") then {
    [] call comspec_overwatch_connect_fnc_ecotiHudHide;
};

_mode
