/*
    Cache menus biométrie (insertChildren sous la racine SSE).
    Ne réinstalle plus d’actions sur les entités — la racine lit le cache à l’ouverture.
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
    [_id, _label, _icon, _code, _cond, _noChildren, [], {[0,0,0]}, 4, _aceParams] call comspec_sse_fnc_aceAction
};

private _insertBio = {
    params ["_target"];
    private _cache = missionNamespace getVariable ["comspec_sse_aceMenuCache", createHashMap];
    if (!(_cache isEqualType createHashMap)) exitWith { [] };
    [_target, _cache getOrDefault ["bioChildren", []]] call comspec_sse_fnc_aceWrapMenuChildren
};

private _bioRoot = [
    "COMSPEC_SSE_Bio", "Biométrie", _icon, {}, _cond, _insertBio, [], {[0,0,0]}, 4, _aceParams, {}
] call comspec_sse_fnc_aceAction;
if (!isNil "comspec_sse_fnc_acePadAction") then {
    _bioRoot = [_bioRoot] call comspec_sse_fnc_acePadAction;
};

private _cache = missionNamespace getVariable ["comspec_sse_aceMenuCache", createHashMap];
if (!(_cache isEqualType createHashMap)) then { _cache = createHashMap; };
_cache set ["bioRoot", _bioRoot];
_cache set ["bioChildren", _bioChildren];
// Objets : empreintes latentes (armes au sol, objets saisis).
_cache set ["bioObjectChildren", [
    ["COMSPEC_SSE_Latent", "Relever des empreintes latentes", "\a3\ui_f\data\igui\cfg\simpleTasks\types\search_ca.paa",
        { [_this select 0, _this select 1] call comspec_sse_fnc_bioCaptureLatentAction },
        { (missionNamespace getVariable ["comspec_sse_bio_latents", true]) && {[_this select 0] call comspec_sse_fnc_canInspect} },
        _noChildren, [], {[0,0,0]}, 3, _aceParams] call comspec_sse_fnc_aceAction
]];
missionNamespace setVariable ["comspec_sse_aceMenuCache", _cache];

// Armes et équipements au sol : pas de menu SSE, mais des empreintes à relever.
if (!isNil "ace_interact_menu_fnc_addActionToClass") then {
    private _latentHolder = [
        "COMSPEC_SSE_LatentHolder", "Relever des empreintes latentes", "\a3\ui_f\data\igui\cfg\simpleTasks\types\search_ca.paa",
        { [_this select 0, _this select 1] call comspec_sse_fnc_bioCaptureLatentAction },
        { (missionNamespace getVariable ["comspec_sse_bio_latents", true]) && {(missionNamespace getVariable ["comspec_sse_bio_enabled", true])} },
        _noChildren, [], {[0,0,0]}, 3, _aceParams
    ] call comspec_sse_fnc_aceAction;
    {
        [_x, 0, [], _latentHolder, true] call ace_interact_menu_fnc_addActionToClass;
    } forEach ["WeaponHolderSimulated", "GroundWeaponHolder"];
};

// Sac de tête : sur toute personne maîtrisée, indépendamment du menu SSE.
if (!isNil "ace_interact_menu_fnc_addActionToClass") then {
    private _bagOn = [
        "COMSPEC_SSE_HeadBagOn", "Mettre un sac sur la tête", "\a3\ui_f\data\igui\cfg\simpleTasks\types\danger_ca.paa",
        {
            params ["_t", "_p"];
            [4, [_t, _p], { (_this select 0) params ["_t", "_p"]; ["on", _t, _p] call comspec_sse_fnc_headBag; }, {}, "Sac de tête…",
                { (_this select 0) params ["_t", "_p"]; ["canOn", _t, _p] call comspec_sse_fnc_headBag }] call ace_common_fnc_progressBar;
            _p playActionNow "Medic";
        },
        { ["canOn", _this select 0, _this select 1] call comspec_sse_fnc_headBag },
        _noChildren, [], {[0,0,0]}, 2.5, _aceParams
    ] call comspec_sse_fnc_aceAction;
    private _bagOff = [
        "COMSPEC_SSE_HeadBagOff", "Retirer le sac de tête", "\a3\ui_f\data\igui\cfg\simpleTasks\types\meet_ca.paa",
        {
            params ["_t", "_p"];
            [2, [_t, _p], { (_this select 0) params ["_t", "_p"]; ["off", _t, _p] call comspec_sse_fnc_headBag; }, {}, "Retrait du sac…",
                { (_this select 0) params ["_t", "_p"]; ["canOff", _t, _p] call comspec_sse_fnc_headBag }] call ace_common_fnc_progressBar;
            _p playActionNow "Medic";
        },
        { ["canOff", _this select 0, _this select 1] call comspec_sse_fnc_headBag },
        _noChildren, [], {[0,0,0]}, 2.5, _aceParams
    ] call comspec_sse_fnc_aceAction;
    {
        ["CAManBase", 0, ["ACE_MainActions"], _x, true] call ace_interact_menu_fnc_addActionToClass;
    } forEach [_bagOn, _bagOff];
};

[_t0, "fn_initBiometricsACE", 0.05] call comspec_debug_fnc_perfWarn;
["INFO", "BIO", "INIT", "biometrics cache prêt (insertChildren)"] call comspec_debug_fnc_log;
["comspec_sse_fnc_initBiometricsACE"] call comspec_debug_fnc_exit;
