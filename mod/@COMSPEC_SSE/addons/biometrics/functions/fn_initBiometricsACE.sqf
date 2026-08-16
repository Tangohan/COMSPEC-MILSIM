/*
    Menus ACE biométrie + SEEK II — instrumenté.
*/
if (!hasInterface) exitWith {};

["comspec_sse_fnc_initBiometricsACE", _this] call comspec_debug_fnc_enter;
private _t0 = diag_tickTime;

if !(["biometrics"] call comspec_debug_fnc_isModuleEnabled) exitWith {
    ["WARN", "Boot", "ISOLATION", "SSE Biometrics disabled by debug isolation"] call comspec_debug_fnc_log;
    ["comspec_sse_fnc_initBiometricsACE"] call comspec_debug_fnc_exit;
};

if !(["COMSPEC_SSE_INIT_BIO_ACE_DONE", "initBiometricsACE"] call comspec_debug_fnc_guardOnce) exitWith {
    ["comspec_sse_fnc_initBiometricsACE"] call comspec_debug_fnc_exit;
};

if (uiNamespace getVariable ["comspec_sse_bioAceReady", false]) exitWith {
    ["WARN", "GUARD", "DUPLICATE", "initBiometricsACE already ready"] call comspec_debug_fnc_log;
    ["comspec_sse_fnc_initBiometricsACE"] call comspec_debug_fnc_exit;
};
uiNamespace setVariable ["comspec_sse_bioAceReady", true];

if (!isClass (configFile >> "CfgPatches" >> "ace_interact_menu")) exitWith {
    ["comspec_sse_fnc_initBiometricsACE"] call comspec_debug_fnc_exit;
};
if (isNil "ace_interact_menu_fnc_createAction") exitWith {
    ["comspec_sse_fnc_initBiometricsACE"] call comspec_debug_fnc_exit;
};

private _noChildren = { [] };
private _aceParams = [false, false, false, false, true];
private _icon = "\a3\ui_f\data\igui\cfg\simpleTasks\types\meet_ca.paa";
private _cond = { [_this select 0] call comspec_sse_fnc_canInspect };
private _inherit = missionNamespace getVariable ["COMSPEC_DEBUG_ACE_INHERIT", true];
private _src = "fn_initBiometricsACE";
private _parentPath = ["ACE_MainActions", "COMSPEC_SSE"];
private _bioPath = ["ACE_MainActions", "COMSPEC_SSE", "COMSPEC_SSE_Bio"];

["BIO.001", format ["init begin inherit=%1", _inherit]] call comspec_debug_fnc_breadcrumb;

private _bioRoot = ["COMSPEC_SSE_Bio", "Biométrie", _icon, {}, _cond, _noChildren, [], {[0,0,0]}, 4, _aceParams] call ace_interact_menu_fnc_createAction;
["BIO.002", "root created"] call comspec_debug_fnc_breadcrumb;

["DEBUG", "ACE", "BEGIN", "class=CAManBase action=COMSPEC_SSE_Bio"] call comspec_debug_fnc_log;
["CAManBase", 0, _parentPath, _bioRoot, _inherit, _src] call comspec_debug_fnc_addACEActionToClass;
["DEBUG", "ACE", "END", "class=CAManBase action=COMSPEC_SSE_Bio"] call comspec_debug_fnc_log;
["BIO.003", "CAManBase bio root registered"] call comspec_debug_fnc_breadcrumb;

private _items = [
    ["COMSPEC_SSE_SeekOpen", "Ouvrir SEEK II", { [_this select 0] call comspec_sse_fnc_openSeek }],
    ["COMSPEC_SSE_FP", "Empreintes", { [_this select 0, _this select 1] call comspec_sse_fnc_captureFingerprint }],
    ["COMSPEC_SSE_IR", "Iris", { [_this select 0, _this select 1] call comspec_sse_fnc_captureIris }],
    ["COMSPEC_SSE_Face", "Photo faciale", { [_this select 0, _this select 1] call comspec_sse_fnc_captureFace }],
    ["COMSPEC_SSE_DNA", "ADN", { [_this select 0, _this select 1] call comspec_sse_fnc_captureDNA }],
    ["COMSPEC_SSE_BioAll", "Capture complète", { [_this select 0, _this select 1] call comspec_sse_fnc_captureAll }],
    ["COMSPEC_SSE_Identify", "Identifier", { [_this select 0, _this select 1] call comspec_sse_fnc_identifySubject }]
];
private _n = count _items;

{
    private _i = _forEachIndex + 1;
    _x params ["_id", "_label", "_code"];
    ["DEBUG", "BIO", "ACTION", format ["[%1/%2] %3", _i, _n, _id]] call comspec_debug_fnc_log;
    ["BIO.ACT", format ["[%1/%2] %3", _i, _n, _id]] call comspec_debug_fnc_breadcrumb;
    private _act = [_id, _label, _icon, _code, _cond, _noChildren, [], {[0,0,0]}, 4, _aceParams] call ace_interact_menu_fnc_createAction;
    ["DEBUG", "ACE", "BEGIN", format ["class=CAManBase action=%1", _id]] call comspec_debug_fnc_log;
    ["CAManBase", 0, _bioPath, _act, _inherit, _src] call comspec_debug_fnc_addACEActionToClass;
    ["DEBUG", "ACE", "END", format ["class=CAManBase action=%1", _id]] call comspec_debug_fnc_log;
} forEach _items;

["BIO.999", "initBiometricsACE complete"] call comspec_debug_fnc_breadcrumb;
[_t0, "fn_initBiometricsACE", 0.05] call comspec_debug_fnc_perfWarn;
["INFO", "BIO", "INIT", "initBiometricsACE OK"] call comspec_debug_fnc_log;
["comspec_sse_fnc_initBiometricsACE"] call comspec_debug_fnc_exit;
