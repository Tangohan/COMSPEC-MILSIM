/*
    Indications dans le tube : full | compass | off.
    Params: [_mode, _persist]
*/
params [["_mode", "full", [""]], ["_persist", true, [true]]];

if (!(_mode isEqualType "") || {_mode isEqualTo ""}) then { _mode = "full"; };
_mode = toLower _mode;
if !(_mode in ["full", "compass", "off"]) then { _mode = "full"; };

private _gps = _mode isNotEqualTo "off";
private _only = _mode isEqualTo "compass";

missionNamespace setVariable ["comspec_overwatch_ecoti_gps_hud", _gps, false];
missionNamespace setVariable ["comspec_overwatch_ecoti_compass_only", _only, false];

if (_persist) then {
    profileNamespace setVariable ["COMSPEC_EcotiTubeInfo", _mode];
    saveProfileNamespace;
};

if (!isNil "cba_settings_fnc_set") then {
    ["comspec_overwatch_ecoti_gps_hud", _gps, 2, "client"] call cba_settings_fnc_set;
    ["comspec_overwatch_ecoti_compass_only", _only, 2, "client"] call cba_settings_fnc_set;
};

if (!_gps && {!_only}) then {
    [] call comspec_overwatch_connect_fnc_ecotiChromeHide;
};

_mode
