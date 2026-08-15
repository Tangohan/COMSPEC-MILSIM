#include "..\script_component.hpp"

if (missionNamespace getVariable ["Iceman_ATAK_Reports_appFilterInstalled", false]) exitWith {};
if (isNil "BCE_fnc_ATAK_setAPPs_props") exitWith {};

Iceman_ATAK_Reports_appFilterInstalled = true;

BCE_fnc_ATAK_getAPPs = {
    params [["_Reset_Value", false], ["_onInit", false]];

    private _hidden = ["BDA_Report"];
    private _rawApps = profileNamespace getVariable ["BCE_ATAK_APPs", []];
    private _ATAK_APPs = _rawApps - _hidden;

    private _classes = "true" configClasses (configFile >> "ATAK_APPs");
    private _orderedClasses = [_classes apply {[getNumber (_x >> "Menu_Property" >> "ORDER"), configName _x]}, [], {_x # 0}, "ASCEND"] call BIS_fnc_sortBy;
    private _defaultOrder = [];
    {
        private _name = _x # 1;
        if !(_name in _hidden) then {
            if !(_name in _defaultOrder) then {
                _defaultOrder pushBack _name;
            };
        };
    } forEach _orderedClasses;

    private _cacheInvalid = _Reset_Value || {!(_rawApps isEqualTo _ATAK_APPs)} || {_ATAK_APPs findIf {true} < 0} || {count _ATAK_APPs != count _defaultOrder};
    if (!_cacheInvalid) then {
        _cacheInvalid = (_defaultOrder findIf {!(_x in _ATAK_APPs)}) > -1 || {(_ATAK_APPs findIf {!(_x in _defaultOrder)}) > -1};
    };

    if (_cacheInvalid) exitWith {
        _ATAK_APPs = _defaultOrder;
        profileNamespace setVariable ["BCE_ATAK_APPs", _ATAK_APPs];
        saveProfileNamespace;
        [_ATAK_APPs] call BCE_fnc_ATAK_setAPPs_props;
        _ATAK_APPs
    };

    if (_onInit && _ATAK_APPs findIf {true} > -1) exitWith {
        [_ATAK_APPs] call BCE_fnc_ATAK_setAPPs_props;
        _ATAK_APPs
    };

    if (_Reset_Value || _ATAK_APPs findIf {true} < 0) then {
        _ATAK_APPs = _defaultOrder;
        [_ATAK_APPs] call BCE_fnc_ATAK_setAPPs_props;
        profileNamespace setVariable ["BCE_ATAK_APPs", _ATAK_APPs];
        saveProfileNamespace;
    };

    private _appsMap = localNamespace getVariable ["BCE_ATAK_APPs_HashMap", createHashMap];
    if (count _appsMap == 0) then {
        [_ATAK_APPs] call BCE_fnc_ATAK_setAPPs_props;
    };

    _ATAK_APPs
};
