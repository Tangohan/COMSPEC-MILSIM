/*
    Action Athena SEEK — exposée via insertChildren de la racine SSE terrain.
    Sans addon SSE : racine locale « Renseignement SSE » per-entité (une fois).
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

["INFO", "SSE", "Installation du menu SSE Athena (insertChildren / fallback)"] call comspec_overwatch_connect_fnc_log;

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

if (!isNil "comspec_overwatch_connect_fnc_acePadAction") then {
    _open = [_open] call comspec_overwatch_connect_fnc_acePadAction;
};
missionNamespace setVariable ["COMSPEC_OW_SSE_OpenAthenaAction", _open];

private _hasSseTerrain = isClass (configFile >> "CfgPatches" >> "comspec_sse_interaction");

// Avec SSE terrain : l’action est déjà injectée par insertChildren de COMSPEC_SSE.
// Sans SSE : greffe class-wide (événement ACE = nom de classe) + repli per-entité.
if (!_hasSseTerrain) then {
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
        [false, false, false, false, true],
        {}
    ] call ace_interact_menu_fnc_createAction;
    if (!isNil "comspec_overwatch_connect_fnc_acePadAction") then {
        _root = [_root] call comspec_overwatch_connect_fnc_acePadAction;
    };
    missionNamespace setVariable ["COMSPEC_OW_SSE_AthenaRootAction", _root];

    private _graft = {
        params ["_entity"];

        private _act = missionNamespace getVariable ["COMSPEC_OW_SSE_OpenAthenaAction", []];
        private _rootAct = missionNamespace getVariable ["COMSPEC_OW_SSE_AthenaRootAction", []];
        if (_act isEqualTo [] || {_rootAct isEqualTo []}) exitWith {};

        // ACE newControllableObject envoie un nom de classe (STRING), pas un objet.
        if (_entity isEqualType "") exitWith {
            if (missionNamespace getVariable ["COMSPEC_OW_SSE_AthenaClassInstalled", false]) exitWith {};
            if (!isClass (configFile >> "CfgVehicles" >> _entity)) exitWith {};
            if !(_entity isKindOf "CAManBase") exitWith {};
            if (isNil "ace_interact_menu_fnc_addActionToClass") exitWith {};
            ["CAManBase", 0, ["ACE_MainActions"], _rootAct, true] call ace_interact_menu_fnc_addActionToClass;
            ["CAManBase", 0, ["ACE_MainActions", "COMSPEC_SSE_ATHENA"], _act, true] call ace_interact_menu_fnc_addActionToClass;
            missionNamespace setVariable ["COMSPEC_OW_SSE_AthenaClassInstalled", true];
        };

        if (!(_entity isEqualType objNull) || {isNull _entity}) exitWith {};
        if !(_entity isKindOf "CAManBase") exitWith {};
        if (_entity getVariable ["comspec_ow_sse_athenaInstalled", false]) exitWith {};

        if !(_entity getVariable ["comspec_ow_sse_rootInstalled", false]) then {
            [_entity, 0, ["ACE_MainActions"], _rootAct] call ace_interact_menu_fnc_addActionToObject;
            _entity setVariable ["comspec_ow_sse_rootInstalled", true];
        };
        [_entity, 0, ["ACE_MainActions", "COMSPEC_SSE_ATHENA"], _act] call ace_interact_menu_fnc_addActionToObject;
        _entity setVariable ["comspec_ow_sse_athenaInstalled", true];
    };

    missionNamespace setVariable ["COMSPEC_OW_SSE_GraftAthenaOnEntity", _graft];

    if (!isNil "CBA_fnc_addEventHandler") then {
        ["ace_interact_menu_newControllableObject", {
            params ["_entity"];
            private _fn = missionNamespace getVariable ["COMSPEC_OW_SSE_GraftAthenaOnEntity", {}];
            [_entity] call _fn;
        }] call CBA_fnc_addEventHandler;
    };
};

["INFO", "SSE", "Menu SSE Athena prêt"] call comspec_overwatch_connect_fnc_log;
call _exitTrace;
