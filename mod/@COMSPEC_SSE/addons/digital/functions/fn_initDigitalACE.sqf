/*
    Cache menus exploitation numérique — installation per-entité.
*/
if (!hasInterface) exitWith {};

[] call comspec_sse_fnc_ensureDebugApi;

["comspec_sse_fnc_initDigitalACE", _this] call comspec_debug_fnc_enter;
private _t0 = diag_tickTime;

if !(["digital"] call comspec_debug_fnc_isModuleEnabled) exitWith {
    ["comspec_sse_fnc_initDigitalACE"] call comspec_debug_fnc_exit;
};

if !(["COMSPEC_SSE_INIT_DIGITAL_ACE_DONE", "initDigitalACE"] call comspec_debug_fnc_guardOnce) exitWith {
    ["comspec_sse_fnc_initDigitalACE"] call comspec_debug_fnc_exit;
};

if (uiNamespace getVariable ["comspec_sse_digitalAceReady", false]) exitWith {
    ["comspec_sse_fnc_initDigitalACE"] call comspec_debug_fnc_exit;
};
uiNamespace setVariable ["comspec_sse_digitalAceReady", true];

if (isNil "ace_interact_menu_fnc_createAction") exitWith {
    ["comspec_sse_fnc_initDigitalACE"] call comspec_debug_fnc_exit;
};

private _noChildren = { [] };
private _aceParams = [false, false, false, false, true];
private _icon = "\a3\ui_f\data\igui\cfg\simpleTasks\types\download_ca.paa";

private _condDigital = {
    params ["_target"];
    if (isNull _target) exitWith { false };
    if !([_target] call comspec_sse_fnc_canInspect) exitWith { false };
    private _type = "";
    private _data = [_target] call comspec_sse_fnc_getData;
    if (!isNil "_data" && {_data isEqualType []}) then {
        _type = [_data, "type", ""] call BIS_fnc_getFromPairs;
    };
    if (_type isEqualTo "" && {!isNil "comspec_sse_fnc_resolveEntityType"}) then {
        _type = [_target] call comspec_sse_fnc_resolveEntityType;
    };
    _type in ["PHONE", "COMPUTER", "TABLET", "RADIO", "DIGITAL_MEDIA", "PERSON"]
};

private _actions = [
    ["COMSPEC_SSE_DIG_ID", "Identifier appareil", { [_this select 0, _this select 1, "identify"] call comspec_sse_fnc_exploitDevice }],
    ["COMSPEC_SSE_DIG_CT", "Contacts", { [_this select 0, _this select 1] call comspec_sse_fnc_extractContacts }],
    ["COMSPEC_SSE_DIG_MSG", "Messages", { [_this select 0, _this select 1] call comspec_sse_fnc_extractMessages }],
    ["COMSPEC_SSE_DIG_CALL", "Historique appels", { [_this select 0, _this select 1] call comspec_sse_fnc_extractCalls }],
    ["COMSPEC_SSE_DIG_PIC", "Photos", { [_this select 0, _this select 1] call comspec_sse_fnc_extractPhotos }],
    ["COMSPEC_SSE_DIG_LOC", "Coordonnées", { [_this select 0, _this select 1] call comspec_sse_fnc_extractLocations }],
    ["COMSPEC_SSE_DIG_FULL", "Extraction complète", { [_this select 0, _this select 1] call comspec_sse_fnc_extractFull }],
    ["COMSPEC_SSE_DIG_SYS", "Informations système", { [_this select 0, _this select 1, "system"] call comspec_sse_fnc_exploitComputer }],
    ["COMSPEC_SSE_DIG_USR", "Utilisateurs", { [_this select 0, _this select 1] call comspec_sse_fnc_extractUsers }],
    ["COMSPEC_SSE_DIG_FILES", "Documents / fichiers", { [_this select 0, _this select 1] call comspec_sse_fnc_extractFiles }],
    ["COMSPEC_SSE_DIG_BRW", "Historique navigateur", { [_this select 0, _this select 1] call comspec_sse_fnc_extractBrowser }],
    ["COMSPEC_SSE_DIG_MAIL", "Messagerie", { [_this select 0, _this select 1] call comspec_sse_fnc_extractMail }],
    ["COMSPEC_SSE_DIG_USB", "Supports connectés", { [_this select 0, _this select 1] call comspec_sse_fnc_extractUsbHistory }],
    ["COMSPEC_SSE_DIG_CRED", "Identifiants", { [_this select 0, _this select 1] call comspec_sse_fnc_extractCredentials }],
    ["COMSPEC_SSE_DIG_MEDIA", "Collecter support (USB/SD)", { [_this select 0, _this select 1] call comspec_sse_fnc_collectMedia }]
];

private _digRoot = ["COMSPEC_SSE_DIGITAL", "Exploitation numérique", _icon, {}, _condDigital, _noChildren, [], {[0,0,0]}, 3, _aceParams] call ace_interact_menu_fnc_createAction;
private _digChildren = _actions apply {
    _x params ["_aid", "_label", "_code"];
    [_aid, _label, _icon, _code, _condDigital, _noChildren, [], {[0,0,0]}, 3, _aceParams] call ace_interact_menu_fnc_createAction
};

private _cache = missionNamespace getVariable ["comspec_sse_aceMenuCache", createHashMap];
if (!(_cache isEqualType createHashMap)) then { _cache = createHashMap; };
_cache set ["digitalRoot", _digRoot];
_cache set ["digitalChildren", _digChildren];
missionNamespace setVariable ["comspec_sse_aceMenuCache", _cache];

if (!isNil "CBA_fnc_waitAndExecute") then {
    private _pending = (allUnits + vehicles + allDeadMen) select {
        !isNull _x
        && {_x getVariable ["comspec_sse_enabled", false]}
        && {!(_x getVariable ["comspec_sse_aceDigInstalled", false])}
        && {!(_x getVariable ["comspec_sse_aceDigQueued", false])}
    };
    _pending = _pending select [0, (count _pending) min 20];
    {
        [{
            params ["_e"];
            if (isNull _e) exitWith {};
            if (!isNil "comspec_sse_fnc_installEntityAceMenus") then {
                [_e] call comspec_sse_fnc_installEntityAceMenus;
            };
        }, [_x], 0.2 + (_forEachIndex * 0.06)] call CBA_fnc_waitAndExecute;
    } forEach _pending;
};

[_t0, "fn_initDigitalACE", 0.05] call comspec_debug_fnc_perfWarn;
["INFO", "DIGITAL", "INIT", "digital cache prêt (per-entité)"] call comspec_debug_fnc_log;
["comspec_sse_fnc_initDigitalACE"] call comspec_debug_fnc_exit;
