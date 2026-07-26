/*
    Applique les réglages d’expérience imposés par la communauté (tenant).
*/
if (!hasInterface) exitWith {};

private _map = missionNamespace getVariable ["COMSPEC_TenantExperience", createHashMap];
if (!(_map isEqualType createHashMap)) exitWith {};

private _realism = (_map getOrDefault ["realism", "0"]) isEqualTo "1";
private _troll = (_map getOrDefault ["troll", "0"]) isEqualTo "1";
missionNamespace setVariable ["COMSPEC_TenantRealism", _realism, false];
missionNamespace setVariable ["COMSPEC_TenantTrollMode", _troll, false];

if (_realism) then {
    missionNamespace setVariable ["comspec_overwatch_milsim_ui", true, false];
    if (!isNil "CBA_fnc_setSetting") then {
        ["comspec_overwatch_milsim_ui", true, 2, "mission", true] call CBA_fnc_setSetting;
    };
};

private _applyTri = {
    params ["_key", "_cbaKey"];
    private _map = missionNamespace getVariable ["COMSPEC_TenantExperience", createHashMap];
    private _val = _map getOrDefault [_key, "player"];
    if (_val isEqualTo "player") exitWith {};
    private _bool = _val isEqualTo "on";
    missionNamespace setVariable [_cbaKey, _bool, false];
    if (!isNil "CBA_fnc_setSetting") then {
        [_cbaKey, _bool, 2, "mission", true] call CBA_fnc_setSetting;
    };
};

["screen_notifications", "comspec_overwatch_screen_notifications"] call _applyTri;
["vehicle_detail", "comspec_overwatch_vehicle_mode"] call _applyTri;
["require_equipment", "comspec_overwatch_require_item"] call _applyTri;
["show_opfor", "comspec_overwatch_show_opfor"] call _applyTri;

true
