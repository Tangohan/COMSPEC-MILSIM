#include "script_component.hpp"

if (!hasInterface) exitWith {};

Iceman_ATAK_Jump_state = createHashMapFromArray [
    ["jumpPoint", []],
    ["dropZone", []],
    ["waypoints", []],
    ["path", []],
    ["segments", []],
    ["ticks", []],
    ["distance", 0],
    ["canopyTime", 0],
    ["mode", "HAHO"],
    ["selectMode", ""],
    ["tab", "plan"],
    ["planned", false],
    ["requiredExitAGL", 0],
    ["requiredPullAGL", 0],
    ["avgGroundSpeedKph", 30],
    ["warnings", []]
];

{
    if (isNil {uiNamespace getVariable _x}) then {
        uiNamespace setVariable [_x, missionNamespace getVariable [_x, {}]];
    };
} forEach [
    "Iceman_fnc_jump_clear",
    "Iceman_fnc_jump_onOpened",
    "Iceman_fnc_jump_plan",
    "Iceman_fnc_jump_selectMode",
    "Iceman_fnc_jump_selectTab",
    "Iceman_fnc_jump_setMode",
    "Iceman_fnc_jump_updatePanel"
];

private _repairATAKApps = {
    if (isNil "BCE_fnc_ATAK_setAPPs_props") exitWith {};

    private _classes = "true" configClasses (configFile >> "ATAK_APPs");
    private _ordered = [_classes apply {[getNumber (_x >> "Menu_Property" >> "ORDER"), configName _x]}, [], {_x # 0}, "ASCEND"] call BIS_fnc_sortBy;
    private _expected = [];
    {
        private _name = _x # 1;
        if !(_name in _expected) then {
            _expected pushBack _name;
        };
    } forEach _ordered;

    private _cached = profileNamespace getVariable ["BCE_ATAK_APPs", []];
    private _cleaned = [];
    private _needsReset = (_cached isEqualTo []);

    {
        private _name = _x;
        if (_name == "HAHO_HALO") then {
            _needsReset = true;
        } else {
            if (_name in _expected) then {
                if (_name in _cleaned) then {
                    _needsReset = true;
                } else {
                    _cleaned pushBack _name;
                };
            } else {
                _needsReset = true;
            };
        };
    } forEach _cached;

    {
        if !(_x in _cleaned) then {
            _cleaned pushBack _x;
            _needsReset = true;
        };
    } forEach _expected;

    if (count _cleaned != count _expected) then {
        _cleaned = _expected;
        _needsReset = true;
    };

    if (_needsReset) then {
        profileNamespace setVariable ["BCE_ATAK_APPs", _cleaned];
        saveProfileNamespace;
    };

    [_cleaned] call BCE_fnc_ATAK_setAPPs_props;

    if (!(isNil "cTab_fnc_getSettings") && {!(isNil "cTab_fnc_setSettings")}) then {
        private _settings = ["cTab_Android_dlg", "showMenu"] call cTab_fnc_getSettings;
        if (_settings isEqualType []) then {
            if ((_settings param [0, ""]) == "HAHO_HALO") then {
                _settings set [0, "settings"];
                ["cTab_Android_dlg", [["showMenu", _settings]], false] call cTab_fnc_setSettings;
            };
        } else {
            if (_settings isEqualType "" && {_settings == "HAHO_HALO"}) then {
                ["cTab_Android_dlg", [["showMenu", ["settings"]]], false] call cTab_fnc_setSettings;
            };
        };
    };
};

{
    [_repairATAKApps, [], _x] call CBA_fnc_waitAndExecute;
} forEach [1, 3, 8];
