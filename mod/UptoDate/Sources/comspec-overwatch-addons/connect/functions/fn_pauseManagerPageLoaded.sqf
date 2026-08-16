/*
    PageLoaded du panneau "Gestion du mod" : injecte statut liaison, réglages rapides
    et diagnostic via ExecJS (window.COMSPEC_PM_onBoot).
*/

params ["_ctrl"];

if (isNull _ctrl) exitWith {};

missionNamespace setVariable ["COMSPEC_PauseManager_PageReady", true];

private _display = ctrlParent _ctrl;
if (!isNull _display) then {
    private _hint = _display displayCtrl 9602;
    if (!isNull _hint) then {
        _hint ctrlSetStructuredText parseText "<t align='right' size='0.55' color='#7dffb3'>Prêt</t>";
    };
};

private _bool = {
    params ["_v"];
    if (_v) then { "true" } else { "false" }
};

// --- Statut liaison Athena ---
private _linkState = missionNamespace getVariable ["COMSPEC_LinkState", "offline"];
private _linkDetail = missionNamespace getVariable ["COMSPEC_LinkDetail", ""];
private _lastPosSyncAt = missionNamespace getVariable ["COMSPEC_LastPositionSync", -1];
private _lastPosSync = -1;
if (_lastPosSyncAt isEqualType 0 && {_lastPosSyncAt >= 0}) then {
    _lastPosSync = diag_tickTime - _lastPosSyncAt;
};
private _lastLatency = missionNamespace getVariable ["COMSPEC_LastLatencyMs", -1];
if (!(_lastLatency isEqualType 0)) then { _lastLatency = -1; };
if (_linkDetail isEqualTo "" && {_linkState isEqualTo "offline"}) then {
    _linkDetail = "Pas de session Athena — reconnectez ou liez le compte.";
};

// --- Réglages rapides ---
private _overwatchEnabled = missionNamespace getVariable ["comspec_overwatch_enabled", true];
private _aceMenus = missionNamespace getVariable ["comspec_overwatch_ace_menus", false];
private _quiet = missionNamespace getVariable ["comspec_overwatch_quiet_mode", false];
private _milsim = missionNamespace getVariable ["comspec_overwatch_milsim_ui", false];

// --- Diagnostic ---
private _modVersion = [] call comspec_overwatch_connect_fnc_getModVersion;
private _extStatus = [] call comspec_overwatch_connect_fnc_extensionStatus;
_extStatus params [["_extOk", false], ["_extCode", "not_loaded"]];
private _aceLoaded = isClass (configFile >> "CfgPatches" >> "ace_interact_menu");
private _cbaLoaded = !(isNil "CBA_fnc_addSetting");

private _js = format [
    "window.COMSPEC_PM_BOOT={link:{state:'%1',detail:'%2',lastPosSync:%3,latencyMs:%4},settings:{overwatchEnabled:%5,aceMenus:%6,quiet:%7,milsim:%8},diag:{version:'%9',extOk:%10,extCode:'%11',ace:%12,cba:%13}}; if(window.COMSPEC_PM_onBoot){window.COMSPEC_PM_onBoot(window.COMSPEC_PM_BOOT);}",
    [_linkState] call comspec_overwatch_connect_fnc_webBrowserJsEscape,
    [_linkDetail] call comspec_overwatch_connect_fnc_webBrowserJsEscape,
    _lastPosSync,
    _lastLatency,
    [_overwatchEnabled] call _bool,
    [_aceMenus] call _bool,
    [_quiet] call _bool,
    [_milsim] call _bool,
    [_modVersion] call comspec_overwatch_connect_fnc_webBrowserJsEscape,
    [_extOk] call _bool,
    [_extCode] call comspec_overwatch_connect_fnc_webBrowserJsEscape,
    [_aceLoaded] call _bool,
    [_cbaLoaded] call _bool
];

_ctrl ctrlWebBrowserAction ["ExecJS", _js];
