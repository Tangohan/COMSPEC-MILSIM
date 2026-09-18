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
["show_independent", "comspec_overwatch_show_independent"] call _applyTri;
["show_civilian", "comspec_overwatch_show_civilian"] call _applyTri;
["sync_map_markers", "comspec_overwatch_sync_map_markers"] call _applyTri;
["radio_proximity", "comspec_overwatch_radio_proximity_enabled"] call _applyTri;
["ace_menus", "comspec_overwatch_ace_menus"] call _applyTri;
["order_compose", "comspec_overwatch_order_compose_enabled"] call _applyTri;
["sse_require_item", "comspec_sse_require_item"] call _applyTri;
["playtime", "comspec_overwatch_playtime_enabled"] call _applyTri;
["athena_feed", "comspec_overwatch_athena_feed_snapshot"] call _applyTri;

private _dmg = _map getOrDefault ["atak_realism", "player"];
if (_dmg isNotEqualTo "player") then {
    private _lvl = if (_dmg isEqualTo "off") then { 0 } else { parseNumber _dmg };
    if (_lvl >= 0 && {_lvl <= 3}) then {
        missionNamespace setVariable ["comspec_overwatch_atak_realism", _lvl, false];
        if (!isNil "CBA_fnc_setSetting") then {
            ["comspec_overwatch_atak_realism", _lvl, 2, "mission", true] call CBA_fnc_setSetting;
        };
    };
};

true
