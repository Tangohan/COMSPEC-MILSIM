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

    // Petit modèle classique désactivé temporairement — on laisse l’écran ouvert avec l’erreur.

};



_ctrl ctrlAddEventHandler ["PageLoaded", {

    _this call comspec_overwatch_connect_fnc_webBrowserPageLoaded;

}];



_ctrl ctrlAddEventHandler ["JSDialog", {

    _this call comspec_overwatch_connect_fnc_webBrowserJSDialog;

}];



// Carte native : masquée au départ ; double-clic = marqueur
missionNamespace setVariable ["COMSPEC_WebBrowser_MapVisible", false];
missionNamespace setVariable ["COMSPEC_WebBrowser_MapZoom", 1];
missionNamespace setVariable ["COMSPEC_WebBrowser_MapShowNames", true];
missionNamespace setVariable ["COMSPEC_WebBrowser_MapUnits", []];

private _map = _display displayCtrl 9410;
if (!isNull _map) then {
    _map ctrlShow false;
    _map ctrlAddEventHandler ["MouseButtonDblClick", {
        params ["_mapCtrl", "_button", "_xC", "_yC"];
        if (_button != 0) exitWith {};
        if !(missionNamespace getVariable ["COMSPEC_WebBrowser_MapVisible", false]) exitWith {};
        private _world = _mapCtrl ctrlMapScreenToWorld [_xC, _yC];
        [_world select 0, _world select 1, "mil_dot", "ColorRed"] call comspec_overwatch_connect_fnc_placeMarkerFromTablet;
    }];
};
{
    private _c = _display displayCtrl _x;
    if (!isNull _c) then { _c ctrlShow false; };
} forEach [9420, 9421, 9422, 9423, 9424, 9425, 9426, 9427, 9428];

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



// Charger le shell local (sandbox A3API).
// LoadFile == OpenDataAsURL(loadFile) SANS charset : Chromium peut lire le HTML
// en Windows-1252 (accents FR / symboles casses). On force UTF-8 via data URI base64.

private _htmlPath = "z\comspec_overwatch\addons\connect\web\tablet.html";
private _html = loadFile _htmlPath;
if (_html isEqualTo "") then {
    _ctrl ctrlWebBrowserAction ["LoadFile", _htmlPath];
} else {
    private _b64 = controlNull ctrlWebBrowserAction ["ToBase64", _html];
    if (_b64 isEqualType "" && {!(_b64 isEqualTo "")}) then {
        _ctrl ctrlWebBrowserAction ["OpenDataAsURL", format ["data:text/html;charset=utf-8;base64,%1", _b64]];
    } else {
        // Repli : data URI texte + charset (moins robuste si # / % dans le HTML)
        _ctrl ctrlWebBrowserAction ["OpenDataAsURL", format ["data:text/html;charset=utf-8,%1", _html]];
    };
};

