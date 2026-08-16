/*
    Installe le nœud SSE Athena sur les personnes — greffe per-entité.
    Plus d'addActionToClass CAManBase (anti STACK_OVERFLOW).
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
    call _exitTrace;
};
if (isNil "ace_interact_menu_fnc_createAction" || {isNil "ace_interact_menu_fnc_addActionToObject"}) exitWith {
    call _exitTrace;
};
if (isNil "comspec_overwatch_connect_fnc_ssePersonDialogShow") exitWith {
    call _exitTrace;
};

if (uiNamespace getVariable ["COMSPEC_SseAceMenuReady", false]) exitWith {
    call _exitTrace;
};
uiNamespace setVariable ["COMSPEC_SseAceMenuReady", true];

["INFO", "SSE", "Installation du menu SSE Athena (per-entité)"] call comspec_overwatch_connect_fnc_log;

private _open = [
    "COMSPEC_SSE_OpenAthena",
    "Ouvrir la fiche Athena",
    "\a3\ui_f\data\igui\cfg\simpleTasks\types\intel_ca.paa",
    {
        params ["_target"];
        [_target] call comspec_overwatch_connect_fnc_sseOpenTerminal;
    },
    {
        params ["_target"];
        [_target] call comspec_overwatch_sse_ace_fnc_sseCanExploit
    },
    { [] },
    [],
    {[0, 0, 0]},
    4,
    [false, false, false, false, true],
    {
        params ["_target", "", "", "_actionData"];
        if (isNil "_actionData" || {!(_actionData isEqualType [])} || {(count _actionData) < 2}) exitWith {};
        _actionData set [1, ([_target] call comspec_overwatch_sse_ace_fnc_sseExploitTargetLabel)];
    }
] call ace_interact_menu_fnc_createAction;

missionNamespace setVariable ["COMSPEC_OW_SSE_OpenAthenaAction", _open];

private _graft = {
    params ["_entity"];
    if (isNull _entity) exitWith {};
    if !(_entity isKindOf "CAManBase") exitWith {};
    if (_entity getVariable ["comspec_ow_sse_athenaInstalled", false]) exitWith {};
    private _act = missionNamespace getVariable ["COMSPEC_OW_SSE_OpenAthenaAction", []];
    if (_act isEqualTo []) exitWith {};

    private _hasSseTerrain = isClass (configFile >> "CfgPatches" >> "comspec_sse_interaction");
    if (_hasSseTerrain && {_entity getVariable ["comspec_sse_aceMenusInstalled", false]}) then {
        [_entity, 0, ["ACE_MainActions", "COMSPEC_SSE"], _act] call ace_interact_menu_fnc_addActionToObject;
    } else {
        if !(_entity getVariable ["comspec_ow_sse_rootInstalled", false]) then {
            private _root = [
                "COMSPEC_SSE_ATHENA",
                "Renseignement SSE",
                "\a3\ui_f\data\igui\cfg\simpleTasks\types\meet_ca.paa",
                {},
                {
                    params ["_target"];
                    [_target] call comspec_overwatch_sse_ace_fnc_sseCanExploit
                },
                { [] },
                [],
                {[0, 0, 0]},
                4,
                [false, false, false, false, true]
            ] call ace_interact_menu_fnc_createAction;
            [_entity, 0, ["ACE_MainActions"], _root] call ace_interact_menu_fnc_addActionToObject;
            _entity setVariable ["comspec_ow_sse_rootInstalled", true];
        };
        [_entity, 0, ["ACE_MainActions", "COMSPEC_SSE_ATHENA"], _act] call ace_interact_menu_fnc_addActionToObject;
    };
    _entity setVariable ["comspec_ow_sse_athenaInstalled", true];
};

missionNamespace setVariable ["COMSPEC_OW_SSE_GraftAthenaOnEntity", _graft];

if (!isNil "CBA_fnc_addEventHandler") then {
    ["comspec_sse_entityAceReady", {
        params ["_ent"];
        private _fn = missionNamespace getVariable ["COMSPEC_OW_SSE_GraftAthenaOnEntity", {}];
        [_ent] call _fn;
    }] call CBA_fnc_addEventHandler;
};

["INFO", "SSE", "Menu SSE Athena prêt (écoute entityAceReady)"] call comspec_overwatch_connect_fnc_log;
call _exitTrace;
