/*
    Cache ACE SSE + menus self.
    Racines personne/objet avec insertChildren dynamiques (bio / digital / Athena)
    pour éviter toute duplication addActionToObject.
*/
if (!hasInterface) exitWith {};

[] call comspec_sse_fnc_ensureDebugApi;

["comspec_sse_fnc_initACE", _this] call comspec_debug_fnc_enter;
private _t0 = diag_tickTime;

if !(["ace"] call comspec_debug_fnc_isModuleEnabled) exitWith {
    ["WARN", "Boot", "ISOLATION", "SSE ACE disabled by debug isolation"] call comspec_debug_fnc_log;
    ["comspec_sse_fnc_initACE"] call comspec_debug_fnc_exit;
};

if !(["COMSPEC_SSE_INIT_ACE_DONE", "initACE"] call comspec_debug_fnc_guardOnce) exitWith {
    ["comspec_sse_fnc_initACE"] call comspec_debug_fnc_exit;
};

if (uiNamespace getVariable ["comspec_sse_aceReady", false]) exitWith {
    ["comspec_sse_fnc_initACE"] call comspec_debug_fnc_exit;
};
uiNamespace setVariable ["comspec_sse_aceReady", true];

if (!isClass (configFile >> "CfgPatches" >> "ace_interact_menu")) exitWith {
    ["comspec_sse_fnc_initACE"] call comspec_debug_fnc_exit;
};
if (isNil "ace_interact_menu_fnc_createAction") exitWith {
    ["comspec_sse_fnc_initACE"] call comspec_debug_fnc_exit;
};

private _noChildren = { [] };
private _aceParams = [false, false, false, false, true];
private _icon = "\a3\ui_f\data\igui\cfg\simpleTasks\types\intel_ca.paa";
private _cond = { [_this select 0] call comspec_sse_fnc_canInspect };

// Enfants personne : triplets ACE [action, [], cible] (jamais createAction brut).
private _insertPerson = {
    params ["_target"];
    private _cache = missionNamespace getVariable ["comspec_sse_aceMenuCache", createHashMap];
    if (!(_cache isEqualType createHashMap)) exitWith { [] };
    private _raw = +(_cache getOrDefault ["personChildren", []]);
    private _bio = _cache getOrDefault ["bioRoot", []];
    if (_bio isNotEqualTo []) then { _raw pushBack _bio; };
    private _dig = _cache getOrDefault ["digitalRoot", []];
    if (_dig isNotEqualTo []) then { _raw pushBack _dig; };
    private _ath = missionNamespace getVariable ["COMSPEC_OW_SSE_OpenAthenaAction", []];
    if (_ath isNotEqualTo []) then { _raw pushBack _ath; };
    private _wrapped = [_target, _raw] call comspec_sse_fnc_aceWrapMenuChildren;
    _wrapped select {
        (_x isEqualType [])
        && {(count _x) >= 2}
        && {(_x select 0) isEqualType []}
        && {(count (_x select 0)) >= 11}
    }
};

private _insertObject = {
    params ["_target"];
    private _cache = missionNamespace getVariable ["comspec_sse_aceMenuCache", createHashMap];
    if (!(_cache isEqualType createHashMap)) exitWith { [] };
    private _raw = +(_cache getOrDefault ["objectChildren", []]);
    private _dig = _cache getOrDefault ["digitalRoot", []];
    if (_dig isNotEqualTo []) then { _raw pushBack _dig; };
    private _wrapped = [_target, _raw] call comspec_sse_fnc_aceWrapMenuChildren;
    _wrapped select {
        (_x isEqualType [])
        && {(count _x) >= 2}
        && {(_x select 0) isEqualType []}
        && {(count (_x select 0)) >= 11}
    }
};

private _rootPerson = [
    "COMSPEC_SSE", "SSE", _icon, {}, _cond, _insertPerson, [], {[0,0,0]}, 4, _aceParams, {}
] call ace_interact_menu_fnc_createAction;
_rootPerson = [_rootPerson] call comspec_sse_fnc_acePadAction;

private _rootObj = [
    "COMSPEC_SSE_OBJ", "SSE", _icon, {}, _cond, _insertObject, [], {[0,0,0]}, 3, _aceParams, {}
] call ace_interact_menu_fnc_createAction;
_rootObj = [_rootObj] call comspec_sse_fnc_acePadAction;

private _personChildren = [
    (["COMSPEC_SSE_Inspect", "Inspecter", _icon, { [_this select 0, _this select 1] call comspec_sse_fnc_doInspect }, _cond, _noChildren, [], {[0,0,0]}, 4, _aceParams] call ace_interact_menu_fnc_createAction),
    (["COMSPEC_SSE_Photo", "Photographier", "\a3\ui_f\data\igui\cfg\simpleTasks\types\search_ca.paa", { [_this select 0, _this select 1] call comspec_sse_fnc_doPhotograph }, _cond, _noChildren, [], {[0,0,0]}, 4, _aceParams] call ace_interact_menu_fnc_createAction),
    (["COMSPEC_SSE_Search", "Fouiller", "\a3\ui_f\data\igui\cfg\simpleTasks\types\search_ca.paa", { [_this select 0, _this select 1, false] call comspec_sse_fnc_doSearch }, _cond, _noChildren, [], {[0,0,0]}, 4, _aceParams] call ace_interact_menu_fnc_createAction),
    (["COMSPEC_SSE_Mark", "Marquer comme exploité", "\a3\ui_f\data\igui\cfg\simpleTasks\types\use_ca.paa", { [_this select 0, _this select 1] call comspec_sse_fnc_doMarkExploited }, _cond, _noChildren, [], {[0,0,0]}, 4, _aceParams] call ace_interact_menu_fnc_createAction),
    (["COMSPEC_SSE_DocsP", "Lire documents", "\a3\ui_f\data\igui\cfg\simpleTasks\types\documents_ca.paa", { [_this select 0, _this select 1] call comspec_sse_fnc_doReadDocuments }, _cond, _noChildren, [], {[0,0,0]}, 4, _aceParams] call ace_interact_menu_fnc_createAction),
    (["COMSPEC_SSE_TerminalTarget", "Ouvrir terminal SSE", "\a3\ui_f\data\igui\cfg\simpleTasks\types\download_ca.paa", { [_this select 0] call comspec_sse_fnc_uiOpenTerminal }, _cond, _noChildren, [], {[0,0,0]}, 4, _aceParams] call ace_interact_menu_fnc_createAction)
];

if (!isNil "comspec_sse_fnc_aceDogtagIsPresent" && {[] call comspec_sse_fnc_aceDogtagIsPresent}) then {
    _personChildren pushBack ([
        "COMSPEC_SSE_Dogtag", "Lire la plaque (SSE)", "\a3\ui_f\data\igui\cfg\simpleTasks\types\meet_ca.paa",
        {
            params ["_target", "_player"];
            if (!isNil "comspec_sse_fnc_ensureGenerated") then { [_target] call comspec_sse_fnc_ensureGenerated; };
            if (!isNil "comspec_sse_fnc_aceDogtagSync") then { [_target] call comspec_sse_fnc_aceDogtagSync; };
            if (!isNil "comspec_sse_fnc_aceDogtagOnCheck") then { [_player, _target] call comspec_sse_fnc_aceDogtagOnCheck; };
        },
        _cond, _noChildren, [], {[0,0,0]}, 4, _aceParams
    ] call ace_interact_menu_fnc_createAction);
};

if (!isNil "comspec_sse_fnc_advanceExploitation") then {
    _personChildren pushBack (["COMSPEC_SSE_Advance", "Approfondir l'exploitation", "\a3\ui_f\data\igui\cfg\simpleTasks\types\intel_ca.paa", { private _r = [_this select 0, _this select 1] call comspec_sse_fnc_advanceExploitation; hint format ["Niveau %1\n%2", _r getOrDefault ["level", "?"], (_r getOrDefault ["lines", []]) joinString endl]; }, _cond, _noChildren, [], {[0,0,0]}, 4, _aceParams] call ace_interact_menu_fnc_createAction);
    _personChildren pushBack (["COMSPEC_SSE_Timeline", "Chronologie SSE", "\a3\ui_f\data\igui\cfg\simpleTasks\types\wait_ca.paa", { private _ev = [_this select 0] call comspec_sse_fnc_buildTimeline; private _lines = _ev apply { format ["%1 — %2", _x getOrDefault ["when", "?"], _x getOrDefault ["text", ""]] }; hint (("Chronologie" + endl) + (_lines select [0, (count _lines) min 8] joinString endl)); }, _cond, _noChildren, [], {[0,0,0]}, 4, _aceParams] call ace_interact_menu_fnc_createAction);
};

private _objectChildren = [
    (["COMSPEC_SSE_Examine", "Examiner", _icon, { [_this select 0, _this select 1] call comspec_sse_fnc_doInspect }, _cond, _noChildren, [], {[0,0,0]}, 3, _aceParams] call ace_interact_menu_fnc_createAction),
    (["COMSPEC_SSE_SearchObj", "Fouiller", "\a3\ui_f\data\igui\cfg\simpleTasks\types\search_ca.paa", { [_this select 0, _this select 1, true] call comspec_sse_fnc_doSearch }, _cond, _noChildren, [], {[0,0,0]}, 3, _aceParams] call ace_interact_menu_fnc_createAction),
    (["COMSPEC_SSE_Collect", "Collecter", "\a3\ui_f\data\igui\cfg\simpleTasks\types\download_ca.paa", { [_this select 0, _this select 1] call comspec_sse_fnc_doCollect }, _cond, _noChildren, [], {[0,0,0]}, 3, _aceParams] call ace_interact_menu_fnc_createAction),
    (["COMSPEC_SSE_Docs", "Lire documents", "\a3\ui_f\data\igui\cfg\simpleTasks\types\documents_ca.paa", { [_this select 0, _this select 1] call comspec_sse_fnc_doReadDocuments }, _cond, _noChildren, [], {[0,0,0]}, 3, _aceParams] call ace_interact_menu_fnc_createAction),
    (["COMSPEC_SSE_Radio", "Exploiter radio", "\a3\ui_f\data\igui\cfg\simpleTasks\types\radio_ca.paa", { [_this select 0, _this select 1] call comspec_sse_fnc_doExploitRadio }, { private _t = _this select 0; ([_t] call comspec_sse_fnc_canInspect) && { private _type = if (isNil {[_t] call comspec_sse_fnc_getData}) then { [_t] call comspec_sse_fnc_resolveEntityType } else { [[_t] call comspec_sse_fnc_getData, "type", ""] call BIS_fnc_getFromPairs }; _type == "RADIO" } }, _noChildren, [], {[0,0,0]}, 3, _aceParams] call ace_interact_menu_fnc_createAction),
    (["COMSPEC_SSE_TerminalTarget", "Ouvrir terminal SSE", "\a3\ui_f\data\igui\cfg\simpleTasks\types\download_ca.paa", { [_this select 0] call comspec_sse_fnc_uiOpenTerminal }, _cond, _noChildren, [], {[0,0,0]}, 3, _aceParams] call ace_interact_menu_fnc_createAction)
];

if (!isNil "comspec_sse_fnc_advanceExploitation") then {
    _objectChildren pushBack (["COMSPEC_SSE_Advance", "Approfondir l'exploitation", "\a3\ui_f\data\igui\cfg\simpleTasks\types\intel_ca.paa", { private _r = [_this select 0, _this select 1] call comspec_sse_fnc_advanceExploitation; hint format ["Niveau %1\n%2", _r getOrDefault ["level", "?"], (_r getOrDefault ["lines", []]) joinString endl]; }, _cond, _noChildren, [], {[0,0,0]}, 3, _aceParams] call ace_interact_menu_fnc_createAction);
    _objectChildren pushBack (["COMSPEC_SSE_Bag", "Mettre sous scellé", "\a3\ui_f\data\igui\cfg\simpleTasks\types\download_ca.paa", { [_this select 0, _this select 1] call comspec_sse_fnc_bagEvidence }, _cond, _noChildren, [], {[0,0,0]}, 3, _aceParams] call ace_interact_menu_fnc_createAction);
};

private _cache = missionNamespace getVariable ["comspec_sse_aceMenuCache", createHashMap];
if (!(_cache isEqualType createHashMap)) then { _cache = createHashMap; };
_cache set ["personRoot", _rootPerson];
_cache set ["objectRoot", _rootObj];
_cache set ["personChildren", _personChildren];
_cache set ["objectChildren", _objectChildren];
missionNamespace setVariable ["comspec_sse_aceMenuCache", _cache];

// Self interaction (joueur uniquement)
private _selfRoot = ["COMSPEC_SSE_SELF", "COMSPEC SSE", _icon, {}, { true }, _noChildren, [], {[0,0,0]}, 1, _aceParams, {}] call ace_interact_menu_fnc_createAction;
_selfRoot = [_selfRoot] call comspec_sse_fnc_acePadAction;
if !(player getVariable ["comspec_sse_aceSelfInstalled", false]) then {
    player setVariable ["comspec_sse_aceSelfInstalled", true];
    [player, 1, ["ACE_SelfActions"], _selfRoot] call ace_interact_menu_fnc_addActionToObject;
    {
        private _sa = [_x] call comspec_sse_fnc_acePadAction;
        if (_sa isNotEqualTo []) then {
            [player, 1, ["ACE_SelfActions", "COMSPEC_SSE_SELF"], _sa] call ace_interact_menu_fnc_addActionToObject;
        };
    } forEach [
        (["COMSPEC_SSE_Journal", "Journal SSE", _icon, { [] call comspec_sse_fnc_openJournal }, { true }, _noChildren, [], {[0,0,0]}, 1, _aceParams] call ace_interact_menu_fnc_createAction),
        (["COMSPEC_SSE_TechLog", "Journal technique (erreurs)", "\a3\ui_f\data\igui\cfg\simpleTasks\types\documents_ca.paa", { [] call comspec_sse_fnc_showLog }, { true }, _noChildren, [], {[0,0,0]}, 1, _aceParams] call ace_interact_menu_fnc_createAction),
        (["COMSPEC_SSE_TerminalSelf", "Ouvrir terminal SSE", "\a3\ui_f\data\igui\cfg\simpleTasks\types\download_ca.paa", { [objNull] call comspec_sse_fnc_uiOpenTerminal }, { true }, _noChildren, [], {[0,0,0]}, 1, _aceParams] call ace_interact_menu_fnc_createAction),
        (["COMSPEC_SSE_EquipKit", "Équiper le kit SSE", "\a3\ui_f\data\igui\cfg\simpleTasks\types\box_ca.paa", { [] call comspec_sse_fnc_equipSseKit }, { true }, _noChildren, [], {[0,0,0]}, 1, _aceParams] call ace_interact_menu_fnc_createAction)
    ];
};

if (!isNil "CBA_fnc_addEventHandler") then {
    ["comspec_sse_entityEnabled", {
        params ["_ent"];
        if (isNull _ent) exitWith {};
        [{
            params ["_e"];
            if (isNull _e) exitWith {};
            [_e] call comspec_sse_fnc_installEntityAceMenus;
        }, [_ent], 0.05] call CBA_fnc_waitAndExecute;
    }] call CBA_fnc_addEventHandler;
};

[_t0, "fn_initACE", 0.05] call comspec_debug_fnc_perfWarn;
["INFO", "SSE", "INIT", "initACE cache prêt (insertChildren, anti-doublon)"] call comspec_debug_fnc_log;
["comspec_sse_fnc_initACE"] call comspec_debug_fnc_exit;
