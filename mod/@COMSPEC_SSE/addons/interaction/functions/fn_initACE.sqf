/*
    Enregistre les menus ACE SSE (interaction + self).
*/
if (!hasInterface) exitWith {};

if (!isClass (configFile >> "CfgPatches" >> "ace_interact_menu")) exitWith {
    ["initACE: ace_interact_menu absent", "WARN"] call comspec_sse_fnc_log;
};
if (isNil "ace_interact_menu_fnc_createAction") exitWith {
    ["initACE: API ACE indisponible", "WARN"] call comspec_sse_fnc_log;
};

if (uiNamespace getVariable ["comspec_sse_aceReady", false]) exitWith {};
uiNamespace setVariable ["comspec_sse_aceReady", true];

private _noChildren = { [] };
private _aceParams = [false, false, false, false, true];
private _icon = "\a3\ui_f\data\igui\cfg\simpleTasks\types\intel_ca.paa";

// --- Parent sur personnes ---
private _rootPerson = [
    "COMSPEC_SSE",
    "SSE",
    _icon,
    {},
    { [_this select 0] call comspec_sse_fnc_canInspect },
    _noChildren,
    [],
    {[0,0,0]},
    4,
    _aceParams
] call ace_interact_menu_fnc_createAction;
["CAManBase", 0, ["ACE_MainActions"], _rootPerson, true] call ace_interact_menu_fnc_addActionToClass;

private _inspect = [
    "COMSPEC_SSE_Inspect",
    "Inspecter",
    _icon,
    { [_this select 0, _this select 1] call comspec_sse_fnc_doInspect },
    { [_this select 0] call comspec_sse_fnc_canInspect },
    _noChildren, [], {[0,0,0]}, 4, _aceParams
] call ace_interact_menu_fnc_createAction;
["CAManBase", 0, ["ACE_MainActions", "COMSPEC_SSE"], _inspect, true] call ace_interact_menu_fnc_addActionToClass;

private _photo = [
    "COMSPEC_SSE_Photo",
    "Photographier",
    "\a3\ui_f\data\igui\cfg\simpleTasks\types\search_ca.paa",
    { [_this select 0, _this select 1] call comspec_sse_fnc_doPhotograph },
    { [_this select 0] call comspec_sse_fnc_canInspect },
    _noChildren, [], {[0,0,0]}, 4, _aceParams
] call ace_interact_menu_fnc_createAction;
["CAManBase", 0, ["ACE_MainActions", "COMSPEC_SSE"], _photo, true] call ace_interact_menu_fnc_addActionToClass;

private _search = [
    "COMSPEC_SSE_Search",
    "Fouiller",
    "\a3\ui_f\data\igui\cfg\simpleTasks\types\search_ca.paa",
    { [_this select 0, _this select 1, false] call comspec_sse_fnc_doSearch },
    { [_this select 0] call comspec_sse_fnc_canInspect },
    _noChildren, [], {[0,0,0]}, 4, _aceParams
] call ace_interact_menu_fnc_createAction;
["CAManBase", 0, ["ACE_MainActions", "COMSPEC_SSE"], _search, true] call ace_interact_menu_fnc_addActionToClass;

private _mark = [
    "COMSPEC_SSE_Mark",
    "Marquer comme exploité",
    "\a3\ui_f\data\igui\cfg\simpleTasks\types\use_ca.paa",
    { [_this select 0, _this select 1] call comspec_sse_fnc_doMarkExploited },
    { [_this select 0] call comspec_sse_fnc_canInspect },
    _noChildren, [], {[0,0,0]}, 4, _aceParams
] call ace_interact_menu_fnc_createAction;
["CAManBase", 0, ["ACE_MainActions", "COMSPEC_SSE"], _mark, true] call ace_interact_menu_fnc_addActionToClass;

private _docsPerson = [
    "COMSPEC_SSE_DocsP",
    "Lire documents",
    "\a3\ui_f\data\igui\cfg\simpleTasks\types\documents_ca.paa",
    { [_this select 0, _this select 1] call comspec_sse_fnc_doReadDocuments },
    { [_this select 0] call comspec_sse_fnc_canInspect },
    _noChildren, [], {[0,0,0]}, 4, _aceParams
] call ace_interact_menu_fnc_createAction;
["CAManBase", 0, ["ACE_MainActions", "COMSPEC_SSE"], _docsPerson, true] call ace_interact_menu_fnc_addActionToClass;

// Biométrie / Digital : enregistrés par addons biometrics & digital (postInit dédiés)

// --- Parent sur objets / véhicules / armes ---
private _rootObj = [
    "COMSPEC_SSE_OBJ",
    "SSE",
    _icon,
    {},
    { [_this select 0] call comspec_sse_fnc_canInspect },
    _noChildren,
    [],
    {[0,0,0]},
    3,
    _aceParams
] call ace_interact_menu_fnc_createAction;

private _objClasses = ["Thing", "LandVehicle", "Air", "Ship", "WeaponHolder", "WeaponHolderSimulated", "ReammoBox_F"];
{
    [_x, 0, ["ACE_MainActions"], _rootObj, true] call ace_interact_menu_fnc_addActionToClass;
} forEach _objClasses;

private _exam = [
    "COMSPEC_SSE_Examine",
    "Examiner",
    _icon,
    { [_this select 0, _this select 1] call comspec_sse_fnc_doInspect },
    { [_this select 0] call comspec_sse_fnc_canInspect },
    _noChildren, [], {[0,0,0]}, 3, _aceParams
] call ace_interact_menu_fnc_createAction;

private _searchObj = [
    "COMSPEC_SSE_SearchObj",
    "Fouiller",
    "\a3\ui_f\data\igui\cfg\simpleTasks\types\search_ca.paa",
    { [_this select 0, _this select 1, true] call comspec_sse_fnc_doSearch },
    { [_this select 0] call comspec_sse_fnc_canInspect },
    _noChildren, [], {[0,0,0]}, 3, _aceParams
] call ace_interact_menu_fnc_createAction;

private _collect = [
    "COMSPEC_SSE_Collect",
    "Collecter",
    "\a3\ui_f\data\igui\cfg\simpleTasks\types\download_ca.paa",
    { [_this select 0, _this select 1] call comspec_sse_fnc_doCollect },
    { [_this select 0] call comspec_sse_fnc_canInspect },
    _noChildren, [], {[0,0,0]}, 3, _aceParams
] call ace_interact_menu_fnc_createAction;

private _docsObj = [
    "COMSPEC_SSE_Docs",
    "Lire documents",
    "\a3\ui_f\data\igui\cfg\simpleTasks\types\documents_ca.paa",
    { [_this select 0, _this select 1] call comspec_sse_fnc_doReadDocuments },
    { [_this select 0] call comspec_sse_fnc_canInspect },
    _noChildren, [], {[0,0,0]}, 3, _aceParams
] call ace_interact_menu_fnc_createAction;

private _radioObj = [
    "COMSPEC_SSE_Radio",
    "Exploiter radio",
    "\a3\ui_f\data\igui\cfg\simpleTasks\types\radio_ca.paa",
    { [_this select 0, _this select 1] call comspec_sse_fnc_doExploitRadio },
    {
        private _t = _this select 0;
        ([_t] call comspec_sse_fnc_canInspect)
        && {
            private _type = if (isNil {[_t] call comspec_sse_fnc_getData}) then {
                [_t] call comspec_sse_fnc_resolveEntityType
            } else {
                [[_t] call comspec_sse_fnc_getData, "type", ""] call BIS_fnc_getFromPairs
            };
            _type == "RADIO"
        }
    },
    _noChildren, [], {[0,0,0]}, 3, _aceParams
] call ace_interact_menu_fnc_createAction;

{
    private _cls = _x;
    {
        [_cls, 0, ["ACE_MainActions", "COMSPEC_SSE_OBJ"], _x, true] call ace_interact_menu_fnc_addActionToClass;
    } forEach [_exam, _searchObj, _collect, _docsObj, _radioObj];
} forEach _objClasses;

// --- Self interaction : Journal + kit ---
private _selfRoot = [
    "COMSPEC_SSE_SELF",
    "COMSPEC SSE",
    _icon,
    {},
    { true },
    _noChildren, [], {[0,0,0]}, 1, []
] call ace_interact_menu_fnc_createAction;
[player, 1, ["ACE_SelfActions"], _selfRoot] call ace_interact_menu_fnc_addActionToObject;

private _journal = [
    "COMSPEC_SSE_Journal",
    "Journal SSE",
    _icon,
    { [] call comspec_sse_fnc_openJournal },
    { true },
    _noChildren, [], {[0,0,0]}, 1, []
] call ace_interact_menu_fnc_createAction;
[player, 1, ["ACE_SelfActions", "COMSPEC_SSE_SELF"], _journal] call ace_interact_menu_fnc_addActionToObject;

private _techLog = [
    "COMSPEC_SSE_TechLog",
    "Journal technique (erreurs)",
    "\a3\ui_f\data\igui\cfg\simpleTasks\types\documents_ca.paa",
    { [] call comspec_sse_fnc_showLog },
    { true },
    _noChildren, [], {[0,0,0]}, 1, []
] call ace_interact_menu_fnc_createAction;
[player, 1, ["ACE_SelfActions", "COMSPEC_SSE_SELF"], _techLog] call ace_interact_menu_fnc_addActionToObject;

private _terminalSelf = [
    "COMSPEC_SSE_TerminalSelf",
    "Ouvrir terminal SSE",
    "\a3\ui_f\data\igui\cfg\simpleTasks\types\download_ca.paa",
    { [objNull] call comspec_sse_fnc_uiOpenTerminal },
    { true },
    _noChildren, [], {[0,0,0]}, 1, []
] call ace_interact_menu_fnc_createAction;
[player, 1, ["ACE_SelfActions", "COMSPEC_SSE_SELF"], _terminalSelf] call ace_interact_menu_fnc_addActionToObject;

private _kit = [
    "COMSPEC_SSE_EquipKit",
    "Équiper le kit SSE",
    "\a3\ui_f\data\igui\cfg\simpleTasks\types\box_ca.paa",
    { [] call comspec_sse_fnc_equipSseKit },
    { true },
    _noChildren, [], {[0,0,0]}, 1, []
] call ace_interact_menu_fnc_createAction;
[player, 1, ["ACE_SelfActions", "COMSPEC_SSE_SELF"], _kit] call ace_interact_menu_fnc_addActionToObject;

// Terminal sur cible (personne / objet)
private _terminalTarget = [
    "COMSPEC_SSE_TerminalTarget",
    "Ouvrir terminal SSE",
    "\a3\ui_f\data\igui\cfg\simpleTasks\types\download_ca.paa",
    { [_this select 0] call comspec_sse_fnc_uiOpenTerminal },
    { [_this select 0] call comspec_sse_fnc_canInspect },
    _noChildren, [], {[0,0,0]}, 4, _aceParams
] call ace_interact_menu_fnc_createAction;
["CAManBase", 0, ["ACE_MainActions", "COMSPEC_SSE"], _terminalTarget, true] call ace_interact_menu_fnc_addActionToClass;
{
    [_x, 0, ["ACE_MainActions", "COMSPEC_SSE_OBJ"], _terminalTarget, true] call ace_interact_menu_fnc_addActionToClass;
} forEach _objClasses;

if (!isNil "comspec_sse_fnc_advanceExploitation") then {
    private _adv = [
        "COMSPEC_SSE_Advance",
        "Approfondir l'exploitation",
        "\a3\ui_f\data\igui\cfg\simpleTasks\types\intel_ca.paa",
        {
            private _r = [_this select 0, _this select 1] call comspec_sse_fnc_advanceExploitation;
            hint format ["Niveau %1\n%2", _r getOrDefault ["level", "?"], (_r getOrDefault ["lines", []]) joinString endl];
        },
        { [_this select 0] call comspec_sse_fnc_canInspect },
        _noChildren, [], {[0,0,0]}, 4, _aceParams
    ] call ace_interact_menu_fnc_createAction;
    ["CAManBase", 0, ["ACE_MainActions", "COMSPEC_SSE"], _adv, true] call ace_interact_menu_fnc_addActionToClass;
    {
        [_x, 0, ["ACE_MainActions", "COMSPEC_SSE_OBJ"], _adv, true] call ace_interact_menu_fnc_addActionToClass;
    } forEach _objClasses;

    private _bag = [
        "COMSPEC_SSE_Bag",
        "Mettre sous scellé",
        "\a3\ui_f\data\igui\cfg\simpleTasks\types\download_ca.paa",
        { [_this select 0, _this select 1] call comspec_sse_fnc_bagEvidence },
        { [_this select 0] call comspec_sse_fnc_canInspect },
        _noChildren, [], {[0,0,0]}, 3, _aceParams
    ] call ace_interact_menu_fnc_createAction;
    {
        [_x, 0, ["ACE_MainActions", "COMSPEC_SSE_OBJ"], _bag, true] call ace_interact_menu_fnc_addActionToClass;
    } forEach _objClasses;

    private _tl = [
        "COMSPEC_SSE_Timeline",
        "Chronologie SSE",
        "\a3\ui_f\data\igui\cfg\simpleTasks\types\wait_ca.paa",
        {
            private _ev = [_this select 0] call comspec_sse_fnc_buildTimeline;
            private _lines = _ev apply { format ["%1 — %2", _x getOrDefault ["when", "?"], _x getOrDefault ["text", ""]] };
            hint (("Chronologie" + endl) + (_lines select [0, (count _lines) min 8] joinString endl));
        },
        { [_this select 0] call comspec_sse_fnc_canInspect },
        _noChildren, [], {[0,0,0]}, 4, _aceParams
    ] call ace_interact_menu_fnc_createAction;
    ["CAManBase", 0, ["ACE_MainActions", "COMSPEC_SSE"], _tl, true] call ace_interact_menu_fnc_addActionToClass;
};

["initACE: menus SSE installés"] call comspec_sse_fnc_log;
