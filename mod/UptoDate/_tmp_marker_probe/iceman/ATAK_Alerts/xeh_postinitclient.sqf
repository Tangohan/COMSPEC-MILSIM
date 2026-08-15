#include "script_component.hpp"

if (!hasInterface) exitWith {};

if (isNil "Iceman_ATAK_Reports_reports") then {
    Iceman_ATAK_Reports_reports = [];
};
Iceman_ATAK_Alerts_reports = Iceman_ATAK_Reports_reports;

if (isNil "Iceman_ATAK_Reports_tab") then {
    Iceman_ATAK_Reports_tab = "inbox";
};
if (isNil "Iceman_ATAK_Reports_selected") then {
    Iceman_ATAK_Reports_selected = -1;
};
if (isNil "Iceman_ATAK_Reports_form") then {
    Iceman_ATAK_Reports_form = "TIC";
};
if (isNil "Iceman_ATAK_Reports_trnCounter") then {
    Iceman_ATAK_Reports_trnCounter = 1;
};
if (isNil "Iceman_ATAK_Panic_reports") then {
    Iceman_ATAK_Panic_reports = [];
};
if (isNil "Iceman_ATAK_Panic_selected") then {
    Iceman_ATAK_Panic_selected = -1;
};
if (isNil "Iceman_ATAK_Group_messages") then {
    Iceman_ATAK_Group_messages = [];
};
if (isNil "Iceman_ATAK_Group_selected") then {
    Iceman_ATAK_Group_selected = -1;
};

{
    if (isNil {uiNamespace getVariable _x}) then {
        uiNamespace setVariable [_x, missionNamespace getVariable [_x, {}]];
    };
} forEach [
    "Iceman_fnc_alerts_clearReports",
    "Iceman_fnc_alerts_clearForm",
    "Iceman_fnc_alerts_locateSelected",
    "Iceman_fnc_alerts_onOpened",
    "Iceman_fnc_alerts_panicOpened",
    "Iceman_fnc_alerts_reportTypeChanged",
    "Iceman_fnc_alerts_sendFrago",
    "Iceman_fnc_alerts_sendPanic",
    "Iceman_fnc_alerts_sendQuick",
    "Iceman_fnc_alerts_selectReport",
    "Iceman_fnc_alerts_selectTab",
    "Iceman_fnc_alerts_submitReport",
    "Iceman_fnc_alerts_updatePanel",
    "Iceman_fnc_group_onOpened",
    "Iceman_fnc_group_selectMessage",
    "Iceman_fnc_group_sendMessage",
    "Iceman_fnc_group_updatePanel",
    "Iceman_fnc_panic_locateSelected",
    "Iceman_fnc_panic_selectReport",
    "Iceman_fnc_panic_updatePanel"
];

["Iceman_ATAK_Alerts", Iceman_fnc_alerts_receive] call CBA_fnc_addEventHandler;
["Iceman_ATAK_GroupMessage", Iceman_fnc_group_receive] call CBA_fnc_addEventHandler;

if (isNil "Iceman_ATAK_Alerts_originalTicAlert") then {
    Iceman_ATAK_Alerts_originalTicAlert = cTab_fnc_ticAlert;
};
cTab_fnc_ticAlert = Iceman_fnc_alerts_ticAlert;

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
} forEach [1, 3, 8, 12];
