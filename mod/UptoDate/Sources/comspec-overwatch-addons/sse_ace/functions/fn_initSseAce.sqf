/*
    Installe le nœud SSE sur les personnes (CAManBase) — instrumenté.
*/
if (!hasInterface) exitWith {};

if (!isNil "comspec_debug_fnc_enter") then {
    ["comspec_overwatch_sse_ace_fnc_initSseAce", _this] call comspec_debug_fnc_enter;
};

private _exitTrace = {
    if (!isNil "comspec_debug_fnc_exit") then {
        ["comspec_overwatch_sse_ace_fnc_initSseAce"] call comspec_debug_fnc_exit;
    };
};

private _disabled = false;
if (!isNil "comspec_debug_fnc_isModuleEnabled") then {
    _disabled = !(["overwatch_sse_ace"] call comspec_debug_fnc_isModuleEnabled);
} else {
    _disabled = missionNamespace getVariable ["COMSPEC_DEBUG_DISABLE_OVERWATCH_SSE_ACE", false];
};
if (_disabled) exitWith {
    if (!isNil "comspec_debug_fnc_log") then {
        ["WARN", "Boot", "ISOLATION", "Overwatch SSE ACE disabled by debug isolation"] call comspec_debug_fnc_log;
    };
    call _exitTrace;
};

private _guardOk = true;
if (!isNil "comspec_debug_fnc_guardOnce") then {
    _guardOk = ["COMSPEC_SSE_INIT_OW_SSE_ACE_DONE", "initSseAce"] call comspec_debug_fnc_guardOnce;
};
if (!_guardOk) exitWith {
    call _exitTrace;
};

if (!isClass (configFile >> "CfgPatches" >> "ace_interact_menu")) exitWith {
    ["initSseAce", "ace_interact_menu absent — couche SSE non installée", nil, "SSE", "INFO"] call comspec_overwatch_connect_fnc_logFnError;
    call _exitTrace;
};
if (isNil "ace_interact_menu_fnc_createAction" || {isNil "ace_interact_menu_fnc_addActionToClass"}) exitWith {
    ["initSseAce", "API ace_interact_menu indisponible — couche SSE non installée", nil, "SSE", "WARN"] call comspec_overwatch_connect_fnc_logFnError;
    call _exitTrace;
};
if (isNil "comspec_overwatch_connect_fnc_ssePersonDialogShow") exitWith {
    ["initSseAce", "connect absent — terminal SSE introuvable", nil, "SSE", "ERROR"] call comspec_overwatch_connect_fnc_logFnError;
    call _exitTrace;
};

if (uiNamespace getVariable ["COMSPEC_SseAceMenuReady", false]) exitWith {
    call _exitTrace;
};
uiNamespace setVariable ["COMSPEC_SseAceMenuReady", true];

["INFO", "SSE", "Installation du menu SSE (interaction ACE)"] call comspec_overwatch_connect_fnc_log;
if (!isNil "comspec_debug_fnc_breadcrumb") then {
    ["OW.SSE.001", "initSseAce begin"] call comspec_debug_fnc_breadcrumb;
};

private _noChildren = { [] };
private _cond = {
    params ["_target"];
    [_target] call comspec_overwatch_sse_ace_fnc_sseCanExploit
};
private _aceParams = [false, false, false, false, true];
private _inherit = missionNamespace getVariable ["COMSPEC_DEBUG_ACE_INHERIT", true];
private _src = "fn_initSseAce";

private _reg = {
    params ["_cls", "_type", "_path", "_act", "_inh", "_src"];
    if (!isNil "comspec_debug_fnc_addACEActionToClass") then {
        [_cls, _type, _path, _act, _inh, _src] call comspec_debug_fnc_addACEActionToClass;
    } else {
        [_cls, _type, _path, _act, _inh] call ace_interact_menu_fnc_addActionToClass;
    };
};

private _hasSseTerrain = isClass (configFile >> "CfgPatches" >> "comspec_sse_interaction");

private _open = [
    "COMSPEC_SSE_OpenAthena",
    "Ouvrir la fiche Athena",
    "\a3\ui_f\data\igui\cfg\simpleTasks\types\intel_ca.paa",
    {
        params ["_target"];
        [_target] call comspec_overwatch_connect_fnc_sseOpenTerminal;
    },
    _cond,
    _noChildren,
    [],
    {[0, 0, 0]},
    4,
    _aceParams,
    {
        params ["_target", "", "", "_actionData"];
        if (isNil "_actionData" || {!(_actionData isEqualType [])} || {(count _actionData) < 2}) exitWith {};
        _actionData set [1, ([_target] call comspec_overwatch_sse_ace_fnc_sseExploitTargetLabel)];
    }
] call ace_interact_menu_fnc_createAction;

if (_hasSseTerrain) then {
    private _graft = {
        params ["_open", "_inherit", "_left", "_src"];
        if (uiNamespace getVariable ["comspec_sse_aceReady", false]) exitWith {
            if (!isNil "comspec_debug_fnc_breadcrumb") then {
                ["OW.SSE.002", "graft OpenAthena under COMSPEC_SSE"] call comspec_debug_fnc_breadcrumb;
            };
            if (!isNil "comspec_debug_fnc_addACEActionToClass") then {
                ["CAManBase", 0, ["ACE_MainActions", "COMSPEC_SSE"], _open, _inherit, _src] call comspec_debug_fnc_addACEActionToClass;
            } else {
                ["CAManBase", 0, ["ACE_MainActions", "COMSPEC_SSE"], _open, _inherit] call ace_interact_menu_fnc_addActionToClass;
            };
            ["INFO", "SSE", "Fiche Athena greffée sous le menu SSE terrain"] call comspec_overwatch_connect_fnc_log;
        };
        if (_left <= 0) exitWith {
            ["WARN", "SSE", "Menu terrain SSE non prêt — greffe Athena annulée (pas de second parent)"] call comspec_overwatch_connect_fnc_log;
        };
        private _fn = missionNamespace getVariable ["COMSPEC_OW_SSE_GraftAthena", {}];
        [_fn, [_open, _inherit, _left - 1, _src], 1] call CBA_fnc_waitAndExecute;
    };
    missionNamespace setVariable ["COMSPEC_OW_SSE_GraftAthena", _graft];
    [_graft, [_open, _inherit, 8, _src], 2] call CBA_fnc_waitAndExecute;
} else {
    private _root = [
        "COMSPEC_SSE_ATHENA",
        "Renseignement SSE",
        "\a3\ui_f\data\igui\cfg\simpleTasks\types\meet_ca.paa",
        {},
        _cond,
        _noChildren,
        [],
        {[0, 0, 0]},
        4,
        _aceParams
    ] call ace_interact_menu_fnc_createAction;
    ["CAManBase", 0, ["ACE_MainActions"], _root, _inherit, _src] call _reg;
    ["CAManBase", 0, ["ACE_MainActions", "COMSPEC_SSE_ATHENA"], _open, _inherit, _src] call _reg;
    ["INFO", "SSE", "Menu Athena autonome (mod @COMSPEC_SSE absent)"] call comspec_overwatch_connect_fnc_log;
};

if (!isNil "comspec_debug_fnc_breadcrumb") then {
    ["OW.SSE.999", "initSseAce complete"] call comspec_debug_fnc_breadcrumb;
};
["INFO", "SSE", "Menu SSE installé"] call comspec_overwatch_connect_fnc_log;
call _exitTrace;
