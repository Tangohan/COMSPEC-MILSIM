/*
    API debug no-op si comspec_sse_debug.pbo absent / non chargé.
    À appeler en tête de tout XEH critique.
*/
if (missionNamespace getVariable ["comspec_sse_debugApiReady", false]) exitWith { true };

if (isNil "comspec_debug_fnc_enter") then { comspec_debug_fnc_enter = {}; };
if (isNil "comspec_debug_fnc_exit") then { comspec_debug_fnc_exit = {}; };
if (isNil "comspec_debug_fnc_log") then { comspec_debug_fnc_log = {}; };
if (isNil "comspec_debug_fnc_breadcrumb") then { comspec_debug_fnc_breadcrumb = {}; };
if (isNil "comspec_debug_fnc_perfWarn") then { comspec_debug_fnc_perfWarn = {}; };
if (isNil "comspec_debug_fnc_exception") then { comspec_debug_fnc_exception = {}; };
if (isNil "comspec_debug_fnc_isSafeMode") then { comspec_debug_fnc_isSafeMode = { false }; };
if (isNil "comspec_debug_fnc_isModuleEnabled") then { comspec_debug_fnc_isModuleEnabled = { true }; };
if (isNil "comspec_debug_fnc_guardOnce") then {
    comspec_debug_fnc_guardOnce = {
        params ["_key"];
        if (missionNamespace getVariable [_key, false]) exitWith { false };
        missionNamespace setVariable [_key, true];
        true
    };
};
if (isNil "comspec_debug_fnc_addACEActionToClass") then {
    comspec_debug_fnc_addACEActionToClass = {
        params ["_cls", "_type", "_path", "_act", ["_inh", true], "_src"];
        if (isNil "ace_interact_menu_fnc_addActionToClass") exitWith { false };
        [_cls, _type, _path, _act, _inh] call ace_interact_menu_fnc_addActionToClass;
        true
    };
};
if (isNil "comspec_debug_fnc_registerEventHandler") then {
    comspec_debug_fnc_registerEventHandler = {
        params ["_id", "_kind", "_evt", "_code"];
        if (_kind == "CBA" && {!isNil "CBA_fnc_addEventHandler"}) then {
            [_evt, _code] call CBA_fnc_addEventHandler;
        };
        true
    };
};

missionNamespace setVariable ["comspec_sse_debugApiReady", true];
true
