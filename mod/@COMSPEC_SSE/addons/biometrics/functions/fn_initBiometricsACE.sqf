/*
    Cache menus biométrie — installation per-entité via installEntityAceMenus.
*/
if (!hasInterface) exitWith {};

[] call comspec_sse_fnc_ensureDebugApi;

["comspec_sse_fnc_initBiometricsACE", _this] call comspec_debug_fnc_enter;
private _t0 = diag_tickTime;

if !(["biometrics"] call comspec_debug_fnc_isModuleEnabled) exitWith {
    ["comspec_sse_fnc_initBiometricsACE"] call comspec_debug_fnc_exit;
};

if !(["COMSPEC_SSE_INIT_BIO_ACE_DONE", "initBiometricsACE"] call comspec_debug_fnc_guardOnce) exitWith {
    ["comspec_sse_fnc_initBiometricsACE"] call comspec_debug_fnc_exit;
};

if (uiNamespace getVariable ["comspec_sse_bioAceReady", false]) exitWith {
    ["comspec_sse_fnc_initBiometricsACE"] call comspec_debug_fnc_exit;
};
uiNamespace setVariable ["comspec_sse_bioAceReady", true];

if (isNil "ace_interact_menu_fnc_createAction") exitWith {
    ["comspec_sse_fnc_initBiometricsACE"] call comspec_debug_fnc_exit;
};

private _noChildren = { [] };
private _aceParams = [false, false, false, false, true];
private _icon = "\a3\ui_f\data\igui\cfg\simpleTasks\types\meet_ca.paa";
private _cond = { [_this select 0] call comspec_sse_fnc_canInspect };

private _bioRoot = ["COMSPEC_SSE_Bio", "Biométrie", _icon, {}, _cond, _noChildren, [], {[0,0,0]}, 4, _aceParams] call ace_interact_menu_fnc_createAction;
private _items = [
    ["COMSPEC_SSE_SeekOpen", "Ouvrir SEEK II", { [_this select 0] call comspec_sse_fnc_openSeek }],
    ["COMSPEC_SSE_FP", "Empreintes", { [_this select 0, _this select 1] call comspec_sse_fnc_captureFingerprint }],
    ["COMSPEC_SSE_IR", "Iris", { [_this select 0, _this select 1] call comspec_sse_fnc_captureIris }],
    ["COMSPEC_SSE_Face", "Photo faciale", { [_this select 0, _this select 1] call comspec_sse_fnc_captureFace }],
    ["COMSPEC_SSE_DNA", "ADN", { [_this select 0, _this select 1] call comspec_sse_fnc_captureDNA }],
    ["COMSPEC_SSE_BioAll", "Capture complète", { [_this select 0, _this select 1] call comspec_sse_fnc_captureAll }],
    ["COMSPEC_SSE_Identify", "Identifier", { [_this select 0, _this select 1] call comspec_sse_fnc_identifySubject }]
];
private _bioChildren = _items apply {
    _x params ["_id", "_label", "_code"];
    [_id, _label, _icon, _code, _cond, _noChildren, [], {[0,0,0]}, 4, _aceParams] call ace_interact_menu_fnc_createAction
};

private _cache = missionNamespace getVariable ["comspec_sse_aceMenuCache", createHashMap];
if (!(_cache isEqualType createHashMap)) then { _cache = createHashMap; };
_cache set ["bioRoot", _bioRoot];
_cache set ["bioChildren", _bioChildren];
missionNamespace setVariable ["comspec_sse_aceMenuCache", _cache];

// Compléter les entités déjà activées (étalé, max 20) — install direct, pas un nouvel event flood.
if (!isNil "CBA_fnc_waitAndExecute") then {
    private _pending = (allUnits + allDeadMen) select {
        !isNull _x
        && {_x getVariable ["comspec_sse_enabled", false]}
        && {!(_x getVariable ["comspec_sse_aceBioInstalled", false])}
        && {!(_x getVariable ["comspec_sse_aceBioQueued", false])}
    };
    _pending = _pending select [0, (count _pending) min 20];
    {
        [{
            params ["_e"];
            if (isNull _e) exitWith {};
            if (!isNil "comspec_sse_fnc_installEntityAceMenus") then {
                [_e] call comspec_sse_fnc_installEntityAceMenus;
            };
        }, [_x], 0.15 + (_forEachIndex * 0.06)] call CBA_fnc_waitAndExecute;
    } forEach _pending;
};

[_t0, "fn_initBiometricsACE", 0.05] call comspec_debug_fnc_perfWarn;
["INFO", "BIO", "INIT", "biometrics cache prêt (per-entité)"] call comspec_debug_fnc_log;
["comspec_sse_fnc_initBiometricsACE"] call comspec_debug_fnc_exit;
