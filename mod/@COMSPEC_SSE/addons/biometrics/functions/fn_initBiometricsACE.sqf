/*
    Menus ACE biométrie + ouverture SEEK II.
*/
if (!hasInterface) exitWith {};
if (!isClass (configFile >> "CfgPatches" >> "ace_interact_menu")) exitWith {};
if (isNil "ace_interact_menu_fnc_createAction") exitWith {};
if (uiNamespace getVariable ["comspec_sse_bioAceReady", false]) exitWith {};
uiNamespace setVariable ["comspec_sse_bioAceReady", true];

private _noChildren = { [] };
private _aceParams = [false, false, false, false, true];
private _icon = "\a3\ui_f\data\igui\cfg\simpleTasks\types\meet_ca.paa";
private _cond = { [_this select 0] call comspec_sse_fnc_canInspect };

private _bioRoot = [
    "COMSPEC_SSE_Bio",
    "Biométrie",
    _icon,
    {},
    _cond,
    _noChildren, [], {[0,0,0]}, 4, _aceParams
] call ace_interact_menu_fnc_createAction;
["CAManBase", 0, ["ACE_MainActions", "COMSPEC_SSE"], _bioRoot, true] call ace_interact_menu_fnc_addActionToClass;

private _items = [
    ["COMSPEC_SSE_SeekOpen", "Ouvrir SEEK II", { [_this select 0] call comspec_sse_fnc_openSeek }],
    ["COMSPEC_SSE_FP", "Empreintes", { [_this select 0, _this select 1] call comspec_sse_fnc_captureFingerprint }],
    ["COMSPEC_SSE_IR", "Iris", { [_this select 0, _this select 1] call comspec_sse_fnc_captureIris }],
    ["COMSPEC_SSE_Face", "Photo faciale", { [_this select 0, _this select 1] call comspec_sse_fnc_captureFace }],
    ["COMSPEC_SSE_DNA", "ADN", { [_this select 0, _this select 1] call comspec_sse_fnc_captureDNA }],
    ["COMSPEC_SSE_BioAll", "Capture complète", { [_this select 0, _this select 1] call comspec_sse_fnc_captureAll }],
    ["COMSPEC_SSE_Identify", "Identifier", { [_this select 0, _this select 1] call comspec_sse_fnc_identifySubject }]
];

{
    _x params ["_id", "_label", "_code"];
    private _act = [_id, _label, _icon, _code, _cond, _noChildren, [], {[0,0,0]}, 4, _aceParams] call ace_interact_menu_fnc_createAction;
    ["CAManBase", 0, ["ACE_MainActions", "COMSPEC_SSE", "COMSPEC_SSE_Bio"], _act, true] call ace_interact_menu_fnc_addActionToClass;
} forEach _items;

["initBiometricsACE OK"] call comspec_sse_fnc_log;
