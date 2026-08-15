#include "script_component.hpp"

if (!hasInterface) exitWith {};

if (isNil "Iceman_ATAK_BDA_reports") then {
    Iceman_ATAK_BDA_reports = [];
};

{
    if (isNil {uiNamespace getVariable _x}) then {
        uiNamespace setVariable [_x, missionNamespace getVariable [_x, {}]];
    };
} forEach [
    "Iceman_fnc_bda_clearForm",
    "Iceman_fnc_bda_clearReports",
    "Iceman_fnc_bda_onOpened",
    "Iceman_fnc_bda_send",
    "Iceman_fnc_bda_updatePanel"
];

["Iceman_ATAK_BDA", Iceman_fnc_bda_receive] call CBA_fnc_addEventHandler;

private _repairATAKApps = {
    if (isNil "BCE_fnc_ATAK_setAPPs_props") exitWith {};

    private _classes = "true" configClasses (configFile >> "ATAK_APPs");
    private _ordered = [_classes apply {[getNumber (_x >> "Menu_Property" >> "ORDER"), configName _x]}, [], {_x # 0}, "ASCEND"] call BIS_fnc_sortBy;
    private _expected = [];
    {
        private _name = _x # 1;
        if !(_name in ["BDA_Report"]) then {
            if !(_name in _expected) then {
                _expected pushBack _name;
            };
        };
    } forEach _ordered;

    private _cached = profileNamespace getVariable ["BCE_ATAK_APPs", []];
    private _cleaned = [];
    private _needsReset = (_cached isEqualTo []);

    {
        private _name = _x;
        if (_name in _expected) then {
            if (_name in _cleaned) then {
                _needsReset = true;
            } else {
                _cleaned pushBack _name;
            };
        } else {
            _needsReset = true;
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
};

{
    [_repairATAKApps, [], _x] call CBA_fnc_waitAndExecute;
} forEach [1, 3, 8];
