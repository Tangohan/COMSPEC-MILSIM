/*
    onLoad du dialog WebBrowser : handlers PageLoaded / JSDialog, chargement HTML local,
    rafraîchissement périodique des effectifs (SQF → ExecJS).
*/
params [["_display", displayNull]];
if (isNull _display) exitWith {};

uiNamespace setVariable ["COMSPEC_WebBrowser_Display", _display];
missionNamespace setVariable ["COMSPEC_WebBrowser_PageReady", false];
missionNamespace setVariable ["COMSPEC_WebBrowser_Mode", "local"];

private _ctrl = _display displayCtrl 9401;
if (isNull _ctrl) exitWith {
    private _hint = _display displayCtrl 9403;
    if (!isNull _hint) then {
        _hint ctrlSetStructuredText parseText "<t align='right' size='0.55' color='#ff8a7a'>Navigateur indisponible</t>";
    };
    [_display] spawn {
        params ["_d"];
        uiSleep 0.4;
        if (!isNull _d) then { closeDialog 0; };
        uiSleep 0.05;
        if (isNull (findDisplay 9973)) then { createDialog "COMSPEC_Device_Dialog"; };
    };
};

_ctrl ctrlAddEventHandler ["PageLoaded", {
    _this call comspec_overwatch_connect_fnc_webBrowserPageLoaded;
}];

_ctrl ctrlAddEventHandler ["JSDialog", {
    _this call comspec_overwatch_connect_fnc_webBrowserJSDialog;
}];

private _hint = _display displayCtrl 9403;
if (!isNull _hint) then {
    _hint ctrlSetStructuredText parseText "<t align='right' size='0.55' color='#8aa0b4'>Ouverture de l’écran tactique…</t>";
};

// Token de boucle : invalidé à la fermeture (onUnload) pour éviter les doubles spawns
private _token = diag_tickTime;
missionNamespace setVariable ["COMSPEC_WebBrowser_RefreshToken", _token];
[_token] spawn {
    params ["_token"];
    while {
        !isNull (findDisplay 9974)
        && {(missionNamespace getVariable ["COMSPEC_WebBrowser_RefreshToken", -1]) isEqualTo _token}
    } do {
        uiSleep 4;
        if (isNull (findDisplay 9974)) exitWith {};
        if !((missionNamespace getVariable ["COMSPEC_WebBrowser_RefreshToken", -1]) isEqualTo _token) exitWith {};
        if ((missionNamespace getVariable ["COMSPEC_WebBrowser_Mode", "local"]) isEqualTo "athena") then { continue };
        private _d = findDisplay 9974;
        private _c = _d displayCtrl 9401;
        if (!isNull _c && {missionNamespace getVariable ["COMSPEC_WebBrowser_PageReady", false]}) then {
            [_c] call comspec_overwatch_connect_fnc_webBrowserPageLoaded;
        };
    };
};

// Charger le shell local (inspiré cTab / ctav-b2) — sandbox A3API, pas de clé Athena dans le HTML
_ctrl ctrlWebBrowserAction ["LoadFile", "z\comspec_overwatch\addons\connect\web\tablet.html"];
