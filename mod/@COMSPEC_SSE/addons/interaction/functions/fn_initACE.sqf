/*
    Enregistre les menus ACE SSE (interaction + self) — version instrumentée.
*/
if (!hasInterface) exitWith {};

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
    ["WARN", "GUARD", "DUPLICATE", "initACE uiNamespace already ready"] call comspec_debug_fnc_log;
    ["comspec_sse_fnc_initACE"] call comspec_debug_fnc_exit;
};
uiNamespace setVariable ["comspec_sse_aceReady", true];

if (!isClass (configFile >> "CfgPatches" >> "ace_interact_menu")) exitWith {
    ["WARN", "SSE", "INIT", "initACE: ace_interact_menu absent"] call comspec_debug_fnc_log;
    ["comspec_sse_fnc_initACE"] call comspec_debug_fnc_exit;
};
if (isNil "ace_interact_menu_fnc_createAction") exitWith {
    ["WARN", "SSE", "INIT", "initACE: API ACE indisponible"] call comspec_debug_fnc_log;
    ["comspec_sse_fnc_initACE"] call comspec_debug_fnc_exit;
};

private _noChildren = { [] };
private _aceParams = [false, false, false, false, true];
private _icon = "\a3\ui_f\data\igui\cfg\simpleTasks\types\intel_ca.paa";
private _inherit = missionNamespace getVariable ["COMSPEC_DEBUG_ACE_INHERIT", true];
private _src = "fn_initACE";

private _reg = {
    params ["_cls", "_type", "_path", "_act", "_inh", "_src"];
    private _aid = if ((count _act) > 0) then { _act select 0 } else { "?" };
    ["DEBUG", "ACE", "BEGIN", format ["class=%1 action=%2", _cls, _aid]] call comspec_debug_fnc_log;
    ["SSE.ACE.CLASS", format ["registering %1 / %2", _cls, _aid]] call comspec_debug_fnc_breadcrumb;
    [_cls, _type, _path, _act, _inh, _src] call comspec_debug_fnc_addACEActionToClass;
    ["DEBUG", "ACE", "END", format ["class=%1 action=%2", _cls, _aid]] call comspec_debug_fnc_log;
};

["SSE.ACE.001", "begin root person"] call comspec_debug_fnc_breadcrumb;

private _rootPerson = [
    "COMSPEC_SSE", "SSE", _icon, {},
    { [_this select 0] call comspec_sse_fnc_canInspect },
    _noChildren, [], {[0,0,0]}, 4, _aceParams
] call ace_interact_menu_fnc_createAction;
["SSE.ACE.002", "root person created"] call comspec_debug_fnc_breadcrumb;
["SSE.ACE.003", "registering CAManBase"] call comspec_debug_fnc_breadcrumb;
["CAManBase", 0, ["ACE_MainActions"], _rootPerson, _inherit, _src] call _reg;
["SSE.ACE.004", "CAManBase registered"] call comspec_debug_fnc_breadcrumb;

private _inspect = ["COMSPEC_SSE_Inspect", "Inspecter", _icon, { [_this select 0, _this select 1] call comspec_sse_fnc_doInspect }, { [_this select 0] call comspec_sse_fnc_canInspect }, _noChildren, [], {[0,0,0]}, 4, _aceParams] call ace_interact_menu_fnc_createAction;
["CAManBase", 0, ["ACE_MainActions", "COMSPEC_SSE"], _inspect, _inherit, _src] call _reg;

private _photo = ["COMSPEC_SSE_Photo", "Photographier", "\a3\ui_f\data\igui\cfg\simpleTasks\types\search_ca.paa", { [_this select 0, _this select 1] call comspec_sse_fnc_doPhotograph }, { [_this select 0] call comspec_sse_fnc_canInspect }, _noChildren, [], {[0,0,0]}, 4, _aceParams] call ace_interact_menu_fnc_createAction;
["CAManBase", 0, ["ACE_MainActions", "COMSPEC_SSE"], _photo, _inherit, _src] call _reg;

private _search = ["COMSPEC_SSE_Search", "Fouiller", "\a3\ui_f\data\igui\cfg\simpleTasks\types\search_ca.paa", { [_this select 0, _this select 1, false] call comspec_sse_fnc_doSearch }, { [_this select 0] call comspec_sse_fnc_canInspect }, _noChildren, [], {[0,0,0]}, 4, _aceParams] call ace_interact_menu_fnc_createAction;
["CAManBase", 0, ["ACE_MainActions", "COMSPEC_SSE"], _search, _inherit, _src] call _reg;

private _mark = ["COMSPEC_SSE_Mark", "Marquer comme exploité", "\a3\ui_f\data\igui\cfg\simpleTasks\types\use_ca.paa", { [_this select 0, _this select 1] call comspec_sse_fnc_doMarkExploited }, { [_this select 0] call comspec_sse_fnc_canInspect }, _noChildren, [], {[0,0,0]}, 4, _aceParams] call ace_interact_menu_fnc_createAction;
["CAManBase", 0, ["ACE_MainActions", "COMSPEC_SSE"], _mark, _inherit, _src] call _reg;

private _docsPerson = ["COMSPEC_SSE_DocsP", "Lire documents", "\a3\ui_f\data\igui\cfg\simpleTasks\types\documents_ca.paa", { [_this select 0, _this select 1] call comspec_sse_fnc_doReadDocuments }, { [_this select 0] call comspec_sse_fnc_canInspect }, _noChildren, [], {[0,0,0]}, 4, _aceParams] call ace_interact_menu_fnc_createAction;
["CAManBase", 0, ["ACE_MainActions", "COMSPEC_SSE"], _docsPerson, _inherit, _src] call _reg;

// Plaque : sync one-way (pas de wrap ACE) + exploitation SSE
if (!isNil "comspec_sse_fnc_aceDogtagIsPresent" && {[] call comspec_sse_fnc_aceDogtagIsPresent}) then {
    private _dog = [
        "COMSPEC_SSE_Dogtag",
        "Lire la plaque (SSE)",
        "\a3\ui_f\data\igui\cfg\simpleTasks\types\meet_ca.paa",
        {
            params ["_target", "_player"];
            if (!isNil "comspec_sse_fnc_ensureGenerated") then {
                [_target] call comspec_sse_fnc_ensureGenerated;
            };
            if (!isNil "comspec_sse_fnc_aceDogtagSync") then {
                [_target] call comspec_sse_fnc_aceDogtagSync;
            };
            if (!isNil "comspec_sse_fnc_aceDogtagOnCheck") then {
                [_player, _target] call comspec_sse_fnc_aceDogtagOnCheck;
            };
        },
        { [_this select 0] call comspec_sse_fnc_canInspect },
        _noChildren, [], {[0,0,0]}, 4, _aceParams
    ] call ace_interact_menu_fnc_createAction;
    ["CAManBase", 0, ["ACE_MainActions", "COMSPEC_SSE"], _dog, _inherit, _src] call _reg;
};

["SSE.ACE.010", "begin root object"] call comspec_debug_fnc_breadcrumb;
private _rootObj = ["COMSPEC_SSE_OBJ", "SSE", _icon, {}, { [_this select 0] call comspec_sse_fnc_canInspect }, _noChildren, [], {[0,0,0]}, 3, _aceParams] call ace_interact_menu_fnc_createAction;
private _objClasses = ["LandVehicle", "Air", "Ship", "ReammoBox_F"];
{ [_x, 0, ["ACE_MainActions"], _rootObj, _inherit, _src] call _reg; } forEach _objClasses;

private _exam = ["COMSPEC_SSE_Examine", "Examiner", _icon, { [_this select 0, _this select 1] call comspec_sse_fnc_doInspect }, { [_this select 0] call comspec_sse_fnc_canInspect }, _noChildren, [], {[0,0,0]}, 3, _aceParams] call ace_interact_menu_fnc_createAction;
private _searchObj = ["COMSPEC_SSE_SearchObj", "Fouiller", "\a3\ui_f\data\igui\cfg\simpleTasks\types\search_ca.paa", { [_this select 0, _this select 1, true] call comspec_sse_fnc_doSearch }, { [_this select 0] call comspec_sse_fnc_canInspect }, _noChildren, [], {[0,0,0]}, 3, _aceParams] call ace_interact_menu_fnc_createAction;
private _collect = ["COMSPEC_SSE_Collect", "Collecter", "\a3\ui_f\data\igui\cfg\simpleTasks\types\download_ca.paa", { [_this select 0, _this select 1] call comspec_sse_fnc_doCollect }, { [_this select 0] call comspec_sse_fnc_canInspect }, _noChildren, [], {[0,0,0]}, 3, _aceParams] call ace_interact_menu_fnc_createAction;
private _docsObj = ["COMSPEC_SSE_Docs", "Lire documents", "\a3\ui_f\data\igui\cfg\simpleTasks\types\documents_ca.paa", { [_this select 0, _this select 1] call comspec_sse_fnc_doReadDocuments }, { [_this select 0] call comspec_sse_fnc_canInspect }, _noChildren, [], {[0,0,0]}, 3, _aceParams] call ace_interact_menu_fnc_createAction;
private _radioObj = ["COMSPEC_SSE_Radio", "Exploiter radio", "\a3\ui_f\data\igui\cfg\simpleTasks\types\radio_ca.paa", { [_this select 0, _this select 1] call comspec_sse_fnc_doExploitRadio }, { private _t = _this select 0; ([_t] call comspec_sse_fnc_canInspect) && { private _type = if (isNil {[_t] call comspec_sse_fnc_getData}) then { [_t] call comspec_sse_fnc_resolveEntityType } else { [[_t] call comspec_sse_fnc_getData, "type", ""] call BIS_fnc_getFromPairs }; _type == "RADIO" } }, _noChildren, [], {[0,0,0]}, 3, _aceParams] call ace_interact_menu_fnc_createAction;

{
    private _cls = _x;
    { [_cls, 0, ["ACE_MainActions", "COMSPEC_SSE_OBJ"], _x, _inherit, _src] call _reg; } forEach [_exam, _searchObj, _collect, _docsObj, _radioObj];
} forEach _objClasses;

["SSE.ACE.020", "self interaction begin"] call comspec_debug_fnc_breadcrumb;
private _selfRoot = ["COMSPEC_SSE_SELF", "COMSPEC SSE", _icon, {}, { true }, _noChildren, [], {[0,0,0]}, 1, []] call ace_interact_menu_fnc_createAction;
[player, 1, ["ACE_SelfActions"], _selfRoot] call ace_interact_menu_fnc_addActionToObject;
[player, 1, ["ACE_SelfActions", "COMSPEC_SSE_SELF"], (["COMSPEC_SSE_Journal", "Journal SSE", _icon, { [] call comspec_sse_fnc_openJournal }, { true }, _noChildren, [], {[0,0,0]}, 1, []] call ace_interact_menu_fnc_createAction)] call ace_interact_menu_fnc_addActionToObject;
[player, 1, ["ACE_SelfActions", "COMSPEC_SSE_SELF"], (["COMSPEC_SSE_TechLog", "Journal technique (erreurs)", "\a3\ui_f\data\igui\cfg\simpleTasks\types\documents_ca.paa", { [] call comspec_sse_fnc_showLog }, { true }, _noChildren, [], {[0,0,0]}, 1, []] call ace_interact_menu_fnc_createAction)] call ace_interact_menu_fnc_addActionToObject;
[player, 1, ["ACE_SelfActions", "COMSPEC_SSE_SELF"], (["COMSPEC_SSE_TerminalSelf", "Ouvrir terminal SSE", "\a3\ui_f\data\igui\cfg\simpleTasks\types\download_ca.paa", { [objNull] call comspec_sse_fnc_uiOpenTerminal }, { true }, _noChildren, [], {[0,0,0]}, 1, []] call ace_interact_menu_fnc_createAction)] call ace_interact_menu_fnc_addActionToObject;
[player, 1, ["ACE_SelfActions", "COMSPEC_SSE_SELF"], (["COMSPEC_SSE_EquipKit", "Équiper le kit SSE", "\a3\ui_f\data\igui\cfg\simpleTasks\types\box_ca.paa", { [] call comspec_sse_fnc_equipSseKit }, { true }, _noChildren, [], {[0,0,0]}, 1, []] call ace_interact_menu_fnc_createAction)] call ace_interact_menu_fnc_addActionToObject;

private _terminalTarget = ["COMSPEC_SSE_TerminalTarget", "Ouvrir terminal SSE", "\a3\ui_f\data\igui\cfg\simpleTasks\types\download_ca.paa", { [_this select 0] call comspec_sse_fnc_uiOpenTerminal }, { [_this select 0] call comspec_sse_fnc_canInspect }, _noChildren, [], {[0,0,0]}, 4, _aceParams] call ace_interact_menu_fnc_createAction;
["CAManBase", 0, ["ACE_MainActions", "COMSPEC_SSE"], _terminalTarget, _inherit, _src] call _reg;
{ [_x, 0, ["ACE_MainActions", "COMSPEC_SSE_OBJ"], _terminalTarget, _inherit, _src] call _reg; } forEach _objClasses;

if (!isNil "comspec_sse_fnc_advanceExploitation") then {
    private _adv = ["COMSPEC_SSE_Advance", "Approfondir l'exploitation", "\a3\ui_f\data\igui\cfg\simpleTasks\types\intel_ca.paa", { private _r = [_this select 0, _this select 1] call comspec_sse_fnc_advanceExploitation; hint format ["Niveau %1\n%2", _r getOrDefault ["level", "?"], (_r getOrDefault ["lines", []]) joinString endl]; }, { [_this select 0] call comspec_sse_fnc_canInspect }, _noChildren, [], {[0,0,0]}, 4, _aceParams] call ace_interact_menu_fnc_createAction;
    ["CAManBase", 0, ["ACE_MainActions", "COMSPEC_SSE"], _adv, _inherit, _src] call _reg;
    { [_x, 0, ["ACE_MainActions", "COMSPEC_SSE_OBJ"], _adv, _inherit, _src] call _reg; } forEach _objClasses;
    private _bag = ["COMSPEC_SSE_Bag", "Mettre sous scellé", "\a3\ui_f\data\igui\cfg\simpleTasks\types\download_ca.paa", { [_this select 0, _this select 1] call comspec_sse_fnc_bagEvidence }, { [_this select 0] call comspec_sse_fnc_canInspect }, _noChildren, [], {[0,0,0]}, 3, _aceParams] call ace_interact_menu_fnc_createAction;
    { [_x, 0, ["ACE_MainActions", "COMSPEC_SSE_OBJ"], _bag, _inherit, _src] call _reg; } forEach _objClasses;
    private _tl = ["COMSPEC_SSE_Timeline", "Chronologie SSE", "\a3\ui_f\data\igui\cfg\simpleTasks\types\wait_ca.paa", { private _ev = [_this select 0] call comspec_sse_fnc_buildTimeline; private _lines = _ev apply { format ["%1 — %2", _x getOrDefault ["when", "?"], _x getOrDefault ["text", ""]] }; hint (("Chronologie" + endl) + (_lines select [0, (count _lines) min 8] joinString endl)); }, { [_this select 0] call comspec_sse_fnc_canInspect }, _noChildren, [], {[0,0,0]}, 4, _aceParams] call ace_interact_menu_fnc_createAction;
    ["CAManBase", 0, ["ACE_MainActions", "COMSPEC_SSE"], _tl, _inherit, _src] call _reg;
};

["SSE.ACE.999", "initACE complete"] call comspec_debug_fnc_breadcrumb;
[_t0, "fn_initACE", 0.05] call comspec_debug_fnc_perfWarn;
["INFO", "SSE", "INIT", "initACE menus installés"] call comspec_debug_fnc_log;
["comspec_sse_fnc_initACE"] call comspec_debug_fnc_exit;
