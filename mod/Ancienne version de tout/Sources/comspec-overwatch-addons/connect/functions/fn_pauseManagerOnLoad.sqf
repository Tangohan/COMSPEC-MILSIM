/*
    onLoad du dialog "Gestion du mod" : câble PageLoaded / JSDialog, charge le HTML local.
    Même schéma que fn_webBrowserOnLoad.sqf (tablette), en plus simple (pas de carte native).
*/

params [["_display", displayNull]];

if (isNull _display) exitWith {};

uiNamespace setVariable ["COMSPEC_PauseManager_Display", _display];
missionNamespace setVariable ["COMSPEC_PauseManager_PageReady", false, false];

private _ctrl = _display displayCtrl 9601;

private _fncFallback = {
    params ["_disp"];
    private _hint = _disp displayCtrl 9602;
    if (!isNull _hint) then {
        _hint ctrlSetStructuredText parseText "<t align='right' size='0.5' color='#ff8a7a'>Écran intégré indisponible</t>";
    };
    private _help = _disp displayCtrl 9603;
    if (!isNull _help) then {
        _help ctrlShow true;
        _help ctrlSetStructuredText parseText (
            "<t align='center' size='0.8' color='#e8f4f0' font='RobotoCondensedBold'>Gestion du mod</t><br/><br/>" +
            "<t align='center' size='0.6' color='#c5d4de'>L’écran intégré ne peut pas démarrer sur cette session Arma.<br/>" +
            "Vérifiez qu’Arma est à jour (2.14 ou plus).</t>"
        );
    };
};

if (isNull _ctrl) exitWith {
    [_display] call _fncFallback;
};

_ctrl ctrlAddEventHandler ["PageLoaded", {
    _this call comspec_overwatch_connect_fnc_pauseManagerPageLoaded;
}];

_ctrl ctrlAddEventHandler ["JSDialog", {
    _this call comspec_overwatch_connect_fnc_pauseManagerJSDialog;
}];

private _hint = _display displayCtrl 9602;
if (!isNull _hint) then {
    _hint ctrlSetStructuredText parseText "<t align='right' size='0.5' color='#8aa0b4'>Ouverture…</t>";
};

// Token de boucle : invalidé à la fermeture (onUnload) pour éviter les tentatives fantômes.
private _token = diag_tickTime;
missionNamespace setVariable ["COMSPEC_PauseManager_RefreshToken", _token, false];

private _htmlPath = "z\comspec_overwatch\addons\connect\web\pause_manager.html";
_ctrl ctrlWebBrowserAction ["LoadFile", _htmlPath];

[_display, _ctrl, _htmlPath, _token, _fncFallback] spawn {
    params ["_disp", "_browser", "_path", "_tok", "_fncFallback"];
    uiSleep 2;
    if (isNull _disp || {isNull _browser}) exitWith {};
    if !((missionNamespace getVariable ["COMSPEC_PauseManager_RefreshToken", -1]) isEqualTo _tok) exitWith {};
    if (missionNamespace getVariable ["COMSPEC_PauseManager_PageReady", false]) exitWith {};

    private _html = loadFile _path;
    if (_html isEqualTo "") exitWith {
        [_disp] call _fncFallback;
    };

    private _b64 = controlNull ctrlWebBrowserAction ["ToBase64", _html];
    if (_b64 isEqualType "" && {!(_b64 isEqualTo "")}) then {
        _browser ctrlWebBrowserAction ["OpenDataAsURL", format ["data:text/html;charset=utf-8;base64,%1", _b64]];
    } else {
        _browser ctrlWebBrowserAction ["OpenDataAsURL", format ["data:text/html;charset=utf-8,%1", _html]];
    };

    uiSleep 4;
    if (isNull _disp) exitWith {};
    if !((missionNamespace getVariable ["COMSPEC_PauseManager_RefreshToken", -1]) isEqualTo _tok) exitWith {};
    if (missionNamespace getVariable ["COMSPEC_PauseManager_PageReady", false]) exitWith {};
    [_disp] call _fncFallback;
};
