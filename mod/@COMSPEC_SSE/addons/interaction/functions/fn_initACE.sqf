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

if (missionNamespace getVariable ["comspec_sse_aceReady", false]) exitWith {
    ["comspec_sse_fnc_initACE"] call comspec_debug_fnc_exit;
};
missionNamespace setVariable ["comspec_sse_aceReady", true]; // par mission : ACE vide ses actions de classe à chaque mission

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

// Règle de conception : le minimum d'actions ACE. Ne restent que les gestes physiques au contact
// (fouiller, mettre sous scellé) et les actions des kits portés ; tout le reste (approfondir, documents,
// chronologie, terminal, photos, numérique, journal) passe par l'app « SSE terrain » du téléphone ATAK.
// Enfants : triplets ACE [action, [], cible] (jamais createAction brut).
private _insertWith = {
    params ["_target", "_key", "_kitKey"];
    private _cache = missionNamespace getVariable ["comspec_sse_aceMenuCache", createHashMap];
    if (!(_cache isEqualType createHashMap)) exitWith { [] };
    private _raw = +(_cache getOrDefault [_key, []]);
    _raw append (_cache getOrDefault [_kitKey, []]);
    private _wrapped = [_target, _raw] call comspec_sse_fnc_aceWrapMenuChildren;
    _wrapped select {
        (_x isEqualType [])
        && {(count _x) >= 2}
        && {(_x select 0) isEqualType []}
        && {(count (_x select 0)) >= 11}
    }
};
missionNamespace setVariable ["comspec_sse_aceInsertWith", _insertWith];
private _insertPerson = { [_this select 0, "personChildren", "kitPerson"] call (missionNamespace getVariable "comspec_sse_aceInsertWith") };
private _insertObject = { [_this select 0, "objectChildren", "kitObject"] call (missionNamespace getVariable "comspec_sse_aceInsertWith") };

private _rootPerson = [
    "COMSPEC_SSE", "SSE", _icon, {}, _cond, _insertPerson, [], {[0,0,0]}, 4, _aceParams, {}
] call comspec_sse_fnc_aceAction;
_rootPerson = [_rootPerson] call comspec_sse_fnc_acePadAction;

private _rootObj = [
    "COMSPEC_SSE_OBJ", "SSE", _icon, {}, _cond, _insertObject, [], {[0,0,0]}, 3, _aceParams, {}
] call comspec_sse_fnc_aceAction;
_rootObj = [_rootObj] call comspec_sse_fnc_acePadAction;

private _personChildren = [
    (["COMSPEC_SSE_Search", "Fouiller", "\a3\ui_f\data\igui\cfg\simpleTasks\types\search_ca.paa", { [_this select 0, _this select 1, false] call comspec_sse_fnc_doSearch }, _cond, _noChildren, [], {[0,0,0]}, 4, _aceParams] call comspec_sse_fnc_aceAction)
];

// Mettre sous scellé = collecte + scellé dans le même geste (sachet consommé).
private _objectChildren = [
    (["COMSPEC_SSE_SearchObj", "Fouiller", "\a3\ui_f\data\igui\cfg\simpleTasks\types\search_ca.paa", { [_this select 0, _this select 1, true] call comspec_sse_fnc_doSearch }, _cond, _noChildren, [], {[0,0,0]}, 3, _aceParams] call comspec_sse_fnc_aceAction),
    (["COMSPEC_SSE_Seal", "Mettre sous scellé", "\a3\ui_f\data\igui\cfg\simpleTasks\types\download_ca.paa", { [_this select 0, _this select 1, true] call comspec_sse_fnc_doCollect },
        { ([_this select 0] call comspec_sse_fnc_canInspect) && {"COMSPEC_SSE_EvidenceBag" in (items (_this select 1))} }, _noChildren, [], {[0,0,0]}, 3, _aceParams] call comspec_sse_fnc_aceAction)
];
([] call comspec_sse_fnc_kitActions) params ["_kitPerson", "_kitObject"];

private _cache = missionNamespace getVariable ["comspec_sse_aceMenuCache", createHashMap];
if (!(_cache isEqualType createHashMap)) then { _cache = createHashMap; };
_cache set ["personRoot", _rootPerson];
_cache set ["objectRoot", _rootObj];
_cache set ["personChildren", _personChildren];
_cache set ["objectChildren", _objectChildren];
_cache set ["kitPerson", _kitPerson];
_cache set ["kitObject", _kitObject];
missionNamespace setVariable ["comspec_sse_aceMenuCache", _cache];

// Personnes : une fois pour toute la classe (PNJ, joueurs, corps).
// Les véhicules restent posés par entité (installEntityAceMenus) pour ne pas
// polluer chaque voiture de la carte.
private _addClass = {
    params ["_cls", "_type", "_path", "_act"];
    if (_act isEqualTo []) exitWith { false };
    if (!isNil "comspec_debug_fnc_addACEActionToClass") exitWith {
        [_cls, _type, _path, _act, true, "sse_interaction"] call comspec_debug_fnc_addACEActionToClass
    };
    if (isNil "ace_interact_menu_fnc_addActionToClass") exitWith { false };
    [_cls, _type, _path, _act, true] call ace_interact_menu_fnc_addActionToClass;
    true
};
if (["CAManBase", 0, ["ACE_MainActions"], _rootPerson] call _addClass) then {
    ["Menu SSE personnes enregistré sur CAManBase"] call comspec_sse_fnc_log;
};

// Plus de menu personnel « COMSPEC SSE » : journal, terminal, scellés, campagne et guide sont dans l'app
// « SSE terrain » du téléphone ATAK ; le kit se prend dans une caisse de kit (Eden, Zeus).

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
