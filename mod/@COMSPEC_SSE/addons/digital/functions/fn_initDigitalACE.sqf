/*
    Menus ACE Exploitation numérique — instrumenté.
*/
if (!hasInterface) exitWith {};

["comspec_sse_fnc_initDigitalACE", _this] call comspec_debug_fnc_enter;
private _t0 = diag_tickTime;

if !(["digital"] call comspec_debug_fnc_isModuleEnabled) exitWith {
    ["WARN", "Boot", "ISOLATION", "SSE Digital disabled by debug isolation"] call comspec_debug_fnc_log;
    ["comspec_sse_fnc_initDigitalACE"] call comspec_debug_fnc_exit;
};

if !(["COMSPEC_SSE_INIT_DIGITAL_ACE_DONE", "initDigitalACE"] call comspec_debug_fnc_guardOnce) exitWith {
    ["comspec_sse_fnc_initDigitalACE"] call comspec_debug_fnc_exit;
};

if (uiNamespace getVariable ["comspec_sse_digitalAceReady", false]) exitWith {
    ["WARN", "GUARD", "DUPLICATE", "initDigitalACE already ready"] call comspec_debug_fnc_log;
    ["comspec_sse_fnc_initDigitalACE"] call comspec_debug_fnc_exit;
};
uiNamespace setVariable ["comspec_sse_digitalAceReady", true];

if (!isClass (configFile >> "CfgPatches" >> "ace_interact_menu")) exitWith {
    ["comspec_sse_fnc_initDigitalACE"] call comspec_debug_fnc_exit;
};
if (isNil "ace_interact_menu_fnc_createAction") exitWith {
    ["comspec_sse_fnc_initDigitalACE"] call comspec_debug_fnc_exit;
};

private _noChildren = { [] };
private _aceParams = [false, false, false, false, true];
private _icon = "\a3\ui_f\data\igui\cfg\simpleTasks\types\download_ca.paa";
private _inherit = missionNamespace getVariable ["COMSPEC_DEBUG_ACE_INHERIT", true];
private _src = "fn_initDigitalACE";

["DIGITAL.001", format ["init begin inherit=%1", _inherit]] call comspec_debug_fnc_breadcrumb;

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
    _type in ["PHONE", "COMPUTER", "TABLET", "RADIO", "DIGITAL_MEDIA"]
};

private _classes = ["CAManBase", "LandVehicle", "ReammoBox_F"];
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
private _totalActs = count _actions;
private _classIdx = 0;

{
    private _cls = _x;
    private _ci = _classIdx;
    _classIdx = _classIdx + 1;
    ["DEBUG", "DIGITAL", "CLASS", format ["%1 begin", _cls]] call comspec_debug_fnc_log;
    ["DIGITAL.CLASS", format ["%1 begin", _cls]] call comspec_debug_fnc_breadcrumb;

    private _root = ["COMSPEC_SSE_DIGITAL", "Exploitation numérique", _icon, {}, _condDigital, _noChildren, [], {[0,0,0]}, 3, _aceParams] call ace_interact_menu_fnc_createAction;
    private _parentPath = if (_cls == "CAManBase") then { ["ACE_MainActions", "COMSPEC_SSE"] } else { ["ACE_MainActions", "COMSPEC_SSE_OBJ"] };

    // Racine immédiate ; enfants étalés (évite pic ACE interact_menu au postInit).
    ["DEBUG", "ACE", "BEGIN", format ["class=%1", _cls]] call comspec_debug_fnc_log;
    [_cls, 0, _parentPath, _root, _inherit, _src] call comspec_debug_fnc_addACEActionToClass;
    ["DEBUG", "ACE", "END", format ["class=%1", _cls]] call comspec_debug_fnc_log;

    {
        private _i = _forEachIndex;
        _x params ["_aid", "_label", "_code"];
        private _delay = 0.03 + (_ci * 0.35) + (_i * 0.02);
        [{
            params ["_cls", "_parentPath", "_aid", "_label", "_code", "_icon", "_condDigital", "_noChildren", "_aceParams", "_inherit", "_src", "_i", "_totalActs"];
            ["DEBUG", "DIGITAL", "ACTION", format ["[%1/%2] %3", _i + 1, _totalActs, _aid]] call comspec_debug_fnc_log;
            private _act = [_aid, _label, _icon, _code, _condDigital, _noChildren, [], {[0,0,0]}, 3, _aceParams] call ace_interact_menu_fnc_createAction;
            ["DEBUG", "ACE", "BEGIN", format ["class=%1 action=%2", _cls, _aid]] call comspec_debug_fnc_log;
            [_cls, 0, _parentPath + ["COMSPEC_SSE_DIGITAL"], _act, _inherit, _src] call comspec_debug_fnc_addACEActionToClass;
            ["DEBUG", "ACE", "END", format ["class=%1 action=%2", _cls, _aid]] call comspec_debug_fnc_log;
        }, [_cls, _parentPath, _aid, _label, _code, _icon, _condDigital, _noChildren, _aceParams, _inherit, _src, _i, _totalActs], _delay] call CBA_fnc_waitAndExecute;
    } forEach _actions;

    ["DEBUG", "DIGITAL", "CLASS", format ["%1 root done (children queued)", _cls]] call comspec_debug_fnc_log;
} forEach _classes;

["DIGITAL.999", "initDigitalACE complete"] call comspec_debug_fnc_breadcrumb;
[_t0, "fn_initDigitalACE", 0.05] call comspec_debug_fnc_perfWarn;
["INFO", "DIGITAL", "INIT", "initDigitalACE OK"] call comspec_debug_fnc_log;
["comspec_sse_fnc_initDigitalACE"] call comspec_debug_fnc_exit;
