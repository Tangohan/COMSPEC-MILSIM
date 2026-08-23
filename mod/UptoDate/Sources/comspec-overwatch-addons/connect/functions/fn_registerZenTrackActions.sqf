/*
    Menus Zeus : balise GPS véhicule, géolocalisation téléphone, IA alliée ATAK.
*/
if (!hasInterface) exitWith {};
if (missionNamespace getVariable ["COMSPEC_ZenTrackActionsRegistered", false]) exitWith {};

private _iconGps = "\A3\ui_f\data\igui\cfg\simpletasks\types\move_ca.paa";
private _iconPhone = "\A3\ui_f\data\igui\cfg\simpletasks\types\radio_ca.paa";
private _iconAlly = "\A3\ui_f\data\igui\cfg\simpletasks\types\meet_ca.paa";

private _collectAi = {
    params ["_obj", ["_pool", []]];
    private _out = [];
    private _pushUnit = {
        params ["_u"];
        if (isNull _u || {!(_u isKindOf "CAManBase")}) exitWith {};
        if (isPlayer _u || {!alive _u}) exitWith {};
        _out pushBackUnique _u;
    };
    if (!isNull _obj) then {
        if (_obj isKindOf "CAManBase") then {
            [_obj] call _pushUnit;
        } else {
            { [_x] call _pushUnit; } forEach (crew _obj);
        };
    };
    {
        if (isNull _x) then { continue };
        if (_x isKindOf "CAManBase") then {
            [_x] call _pushUnit;
        } else {
            private _veh = _x;
            { [_x] call _pushUnit; } forEach (crew _veh);
        };
    } forEach _pool;
    _out
};

private _toggleGps = {
    params ["_obj"];
    if (isNull _obj) exitWith {
        ["Posez ceci sur un véhicule.", "system", "warn"] call comspec_overwatch_connect_fnc_ambientHint;
    };
    if (_obj isKindOf "CAManBase") then { _obj = vehicle _obj };
    if (_obj isKindOf "CAManBase") exitWith {
        ["Sélectionnez un véhicule.", "system", "warn"] call comspec_overwatch_connect_fnc_ambientHint;
    };
    private _on = !([_obj, "COMSPEC_GpsBeacon"] call comspec_overwatch_connect_fnc_isObjectFlag);
    [_obj, _on] call comspec_overwatch_connect_fnc_setGpsBeacon;
    private _label = getText (configOf _obj >> "displayName");
    if (_label isEqualTo "") then { _label = "Véhicule" };
    if (_on) then {
        [format ["Balise GPS activée — %1 apparaît sur l’ATAK.", _label], "system", "info"] call comspec_overwatch_connect_fnc_ambientHint;
    } else {
        [format ["Balise GPS coupée — %1 n’est plus suivi.", _label], "system", "info"] call comspec_overwatch_connect_fnc_ambientHint;
    };
};

private _togglePhone = {
    params ["_obj"];
    if (isNull _obj || {!(_obj isKindOf "CAManBase")}) exitWith {
        ["Sélectionnez une personne (joueur ou IA).", "system", "warn"] call comspec_overwatch_connect_fnc_ambientHint;
    };
    private _on = !([_obj, "COMSPEC_PhoneTrack"] call comspec_overwatch_connect_fnc_isObjectFlag);
    [_obj, _on] call comspec_overwatch_connect_fnc_setPhoneTrack;
    private _who = name _obj;
    if (_on) then {
        [format ["Téléphone localisé — %1 apparaît sur l’ATAK.", _who], "system", "info"] call comspec_overwatch_connect_fnc_ambientHint;
    } else {
        [format ["Géolocalisation coupée pour %1.", _who], "system", "info"] call comspec_overwatch_connect_fnc_ambientHint;
    };
};

private _toggleAlly = {
    params ["_obj", ["_pool", []]];
    private _targets = [_obj, _pool] call (missionNamespace getVariable ["COMSPEC_ZeusCollectAllyAi", { [] }]);
    if (_targets isEqualTo [] && {!isNull _obj} && {_obj isKindOf "CAManBase"} && {!isPlayer _obj}) then {
        { if (!isPlayer _x && {alive _x}) then { _targets pushBackUnique _x }; } forEach (units group _obj);
    };
    if (_targets isEqualTo []) exitWith {
        ["Sélectionnez une IA (ou un véhicule avec un équipage IA).", "system", "warn"] call comspec_overwatch_connect_fnc_ambientHint;
    };
    if ((count _targets) == 1) then {
        private _u0 = _targets select 0;
        { if (!isPlayer _x && {alive _x}) then { _targets pushBackUnique _x }; } forEach (units group _u0);
    };
    private _anyOff = false;
    {
        if !([_x, "COMSPEC_AllyTrack"] call comspec_overwatch_connect_fnc_isObjectFlag) then { _anyOff = true };
    } forEach _targets;
    private _on = _anyOff;
    { [_x, _on] call comspec_overwatch_connect_fnc_setAllyTrack; } forEach _targets;
    if (_on) then {
        [format ["%1 unité(s) alliée(s) visible(s) sur l’ATAK.", count _targets], "system", "info"] call comspec_overwatch_connect_fnc_ambientHint;
    } else {
        [format ["Suivi ATAK coupé pour %1 unité(s).", count _targets], "system", "info"] call comspec_overwatch_connect_fnc_ambientHint;
    };
};

missionNamespace setVariable ["COMSPEC_ZeusToggleGpsBeacon", _toggleGps];
missionNamespace setVariable ["COMSPEC_ZeusTogglePhoneTrack", _togglePhone];
missionNamespace setVariable ["COMSPEC_ZeusToggleAllyTrack", _toggleAlly];
missionNamespace setVariable ["COMSPEC_ZeusCollectAllyAi", _collectAi];

if (!isNil "zen_custom_modules_fnc_register") then {
    [
        "COMSPEC Roleplay",
        "Balise GPS véhicule",
        {
            params ["_pos", "_obj"];
            if (isNull _obj) then {
                { if (!(_x isKindOf "CAManBase")) exitWith { _obj = _x }; } forEach (nearestObjects [_pos, ["LandVehicle", "Air", "Ship"], 8]);
            };
            [_obj] call (missionNamespace getVariable ["COMSPEC_ZeusToggleGpsBeacon", {}]);
        },
        _iconGps
    ] call zen_custom_modules_fnc_register;

    [
        "COMSPEC Roleplay",
        "Géolocalisation téléphone",
        {
            params ["_pos", "_obj"];
            if (isNull _obj || {!(_obj isKindOf "CAManBase")}) then {
                { if (_x isKindOf "CAManBase") exitWith { _obj = _x }; } forEach (nearestObjects [_pos, ["CAManBase"], 5]);
            };
            [_obj] call (missionNamespace getVariable ["COMSPEC_ZeusTogglePhoneTrack", {}]);
        },
        _iconPhone
    ] call zen_custom_modules_fnc_register;

    [
        "COMSPEC Roleplay",
        "IA alliée sur l’ATAK",
        {
            params ["_pos", "_obj"];
            private _pool = curatorSelected select 0;
            if (isNull _obj) then {
                { if (_x isKindOf "CAManBase" && {!isPlayer _x}) exitWith { _obj = _x }; } forEach (nearestObjects [_pos, ["CAManBase"], 8]);
            };
            [_obj, _pool] call (missionNamespace getVariable ["COMSPEC_ZeusToggleAllyTrack", {}]);
        },
        _iconAlly
    ] call zen_custom_modules_fnc_register;
};

if (!isNil "zen_context_menu_fnc_createAction" && {!isNil "zen_context_menu_fnc_addAction"}) then {
    private _gpsAction = [
        "comspec_gps_beacon",
        "Balise GPS ATAK",
        _iconGps,
        {
            private _pool = if (!isNil "zen_context_menu_selectedObjects") then {
                zen_context_menu_selectedObjects
            } else {
                curatorSelected select 0
            };
            private _obj = objNull;
            { if (!isNull _x && {!(_x isKindOf "CAManBase")}) exitWith { _obj = _x }; } forEach _pool;
            [_obj] call (missionNamespace getVariable ["COMSPEC_ZeusToggleGpsBeacon", {}]);
        },
        {
            private _pool = if (!isNil "zen_context_menu_selectedObjects") then {
                zen_context_menu_selectedObjects
            } else {
                curatorSelected select 0
            };
            ({ !isNull _x && {!(_x isKindOf "CAManBase")} && {_x isKindOf "AllVehicles"} } count _pool) > 0
        }
    ] call zen_context_menu_fnc_createAction;
    [_gpsAction, [], 7] call zen_context_menu_fnc_addAction;

    private _phoneAction = [
        "comspec_phone_track",
        "Géolocalisation téléphone",
        _iconPhone,
        {
            private _pool = if (!isNil "zen_context_menu_selectedObjects") then {
                zen_context_menu_selectedObjects
            } else {
                curatorSelected select 0
            };
            private _obj = objNull;
            { if (!isNull _x && {_x isKindOf "CAManBase"}) exitWith { _obj = _x }; } forEach _pool;
            [_obj] call (missionNamespace getVariable ["COMSPEC_ZeusTogglePhoneTrack", {}]);
        },
        {
            private _pool = if (!isNil "zen_context_menu_selectedObjects") then {
                zen_context_menu_selectedObjects
            } else {
                curatorSelected select 0
            };
            ({ !isNull _x && {_x isKindOf "CAManBase"} } count _pool) > 0
        }
    ] call zen_context_menu_fnc_createAction;
    [_phoneAction, [], 7] call zen_context_menu_fnc_addAction;

    private _allyAction = [
        "comspec_ally_track",
        "IA alliée sur l’ATAK",
        _iconAlly,
        {
            private _pool = if (!isNil "zen_context_menu_selectedObjects") then {
                zen_context_menu_selectedObjects
            } else {
                curatorSelected select 0
            };
            private _obj = objNull;
            { if (!isNull _x && {_x isKindOf "CAManBase"} && {!isPlayer _x}) exitWith { _obj = _x }; } forEach _pool;
            if (isNull _obj) then {
                { if (!isNull _x && {!(_x isKindOf "CAManBase")}) exitWith { _obj = _x }; } forEach _pool;
            };
            [_obj, _pool] call (missionNamespace getVariable ["COMSPEC_ZeusToggleAllyTrack", {}]);
        },
        {
            private _pool = if (!isNil "zen_context_menu_selectedObjects") then {
                zen_context_menu_selectedObjects
            } else {
                curatorSelected select 0
            };
            ({
                !isNull _x && {
                    (_x isKindOf "CAManBase" && {!isPlayer _x})
                    || {({ !isPlayer _y && {alive _y} } count (crew _x)) > 0}
                }
            } count _pool) > 0
        }
    ] call zen_context_menu_fnc_createAction;
    [_allyAction, [], 7] call zen_context_menu_fnc_addAction;
};

if (!isNil "ace_zeus_fnc_addModule") then {
    ["COMSPEC ATAK", "Balise GPS véhicule", {
        params ["", ["_obj", objNull]];
        if (isNull _obj) then {
            { if (!(_x isKindOf "CAManBase")) exitWith { _obj = _x }; } forEach (curatorSelected select 0);
        };
        [_obj] call (missionNamespace getVariable ["COMSPEC_ZeusToggleGpsBeacon", {}]);
    }, _iconGps] call ace_zeus_fnc_addModule;

    ["COMSPEC ATAK", "Géolocalisation téléphone", {
        params ["", ["_obj", objNull]];
        if (isNull _obj || {!(_obj isKindOf "CAManBase")}) then {
            { if (_x isKindOf "CAManBase") exitWith { _obj = _x }; } forEach (curatorSelected select 0);
        };
        [_obj] call (missionNamespace getVariable ["COMSPEC_ZeusTogglePhoneTrack", {}]);
    }, _iconPhone] call ace_zeus_fnc_addModule;

    ["COMSPEC ATAK", "IA alliée sur l’ATAK", {
        params ["", ["_obj", objNull]];
        private _pool = curatorSelected select 0;
        if (isNull _obj) then {
            { if (_x isKindOf "CAManBase" && {!isPlayer _x}) exitWith { _obj = _x }; } forEach _pool;
        };
        [_obj, _pool] call (missionNamespace getVariable ["COMSPEC_ZeusToggleAllyTrack", {}]);
    }, _iconAlly] call ace_zeus_fnc_addModule;
};

missionNamespace setVariable ["COMSPEC_ZenTrackActionsRegistered", true];
["INFO", "Tracking", "Menus Zeus balise GPS / téléphone / IA alliée enregistrés"] call comspec_overwatch_connect_fnc_log;
