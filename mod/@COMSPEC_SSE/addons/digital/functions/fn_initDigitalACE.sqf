/*
    Menus ACE Exploitation numérique (téléphone + ordinateur).
*/
if (!hasInterface) exitWith {};
if (!isClass (configFile >> "CfgPatches" >> "ace_interact_menu")) exitWith {};
if (isNil "ace_interact_menu_fnc_createAction") exitWith {};
if (uiNamespace getVariable ["comspec_sse_digitalAceReady", false]) exitWith {};
uiNamespace setVariable ["comspec_sse_digitalAceReady", true];

private _noChildren = { [] };
private _aceParams = [false, false, false, false, true];
private _icon = "\a3\ui_f\data\igui\cfg\simpleTasks\types\download_ca.paa";
private _cond = { [_this select 0] call comspec_sse_fnc_canInspect };

private _classes = ["Thing", "LandVehicle", "WeaponHolder", "ReammoBox_F", "CAManBase"];

{
    private _cls = _x;

    private _root = [
        "COMSPEC_SSE_DIGITAL",
        "Exploitation numérique",
        _icon,
        {},
        _cond,
        _noChildren, [], {[0,0,0]}, 3, _aceParams
    ] call ace_interact_menu_fnc_createAction;

    private _parentPath = if (_cls == "CAManBase") then {
        ["ACE_MainActions", "COMSPEC_SSE"]
    } else {
        ["ACE_MainActions", "COMSPEC_SSE_OBJ"]
    };

    // Sur personne : sous SSE ; sur objet : sous SSE_OBJ (créé par interaction)
    // Si parent absent, greffer sous ACE_MainActions
    [_cls, 0, _parentPath, _root, true] call ace_interact_menu_fnc_addActionToClass;

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

    {
        _x params ["_aid", "_label", "_code"];
        private _act = [_aid, _label, _icon, _code, _cond, _noChildren, [], {[0,0,0]}, 3, _aceParams] call ace_interact_menu_fnc_createAction;
        [_cls, 0, _parentPath + ["COMSPEC_SSE_DIGITAL"], _act, true] call ace_interact_menu_fnc_addActionToClass;
    } forEach _actions;
} forEach _classes;

["initDigitalACE OK"] call comspec_sse_fnc_log;
