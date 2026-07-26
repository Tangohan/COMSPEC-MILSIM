/*
    onLoad du dialog WebBrowser : handlers PageLoaded / JSDialog, chargement HTML local,
    rafraîchissement périodique des effectifs (SQF → ExecJS).
    Si le contrôle navigateur est absent : tentative ctrlCreate, sinon écran de repli.
*/

params [["_display", displayNull]];

if (isNull _display) exitWith {};

uiNamespace setVariable ["COMSPEC_WebBrowser_Display", _display];
missionNamespace setVariable ["COMSPEC_WebBrowser_PageReady", false];
missionNamespace setVariable ["COMSPEC_WebBrowser_Mode", "local"];

private _browserX = safezoneX + 0.09 * safezoneW;
private _browserY = safezoneY + 0.12 * safezoneH;
private _browserW = 0.82 * safezoneW;
private _browserH = 0.72 * safezoneH;

private _ctrl = _display displayCtrl 9401;

// Certains clients n’instancient pas CT_WEBBROWSER via createDialog : on retente à chaud.
if (isNull _ctrl) then {
    if (isClass (configFile >> "COMSPEC_RscWebBrowser")) then {
        _ctrl = _display ctrlCreate ["COMSPEC_RscWebBrowser", 9401];
    };
    if (isNull _ctrl && {isClass (configFile >> "RscWebBrowser")}) then {
        _ctrl = _display ctrlCreate ["RscWebBrowser", 9401];
    };
    if (!isNull _ctrl) then {
        _ctrl ctrlSetPosition [_browserX, _browserY, _browserW, _browserH];
        _ctrl ctrlCommit 0;
    };
};

private _fncFallback = {
    params ["_disp"];
    private _hint = _disp displayCtrl 9403;
    if (!isNull _hint) then {
        _hint ctrlSetStructuredText parseText "<t align='right' size='0.5' color='#ff8a7a'>Écran intégré indisponible</t>";
    };
    private _help = _disp displayCtrl 9430;
    if (!isNull _help) then {
        _help ctrlShow true;
        _help ctrlSetStructuredText parseText (
            "<t align='center' size='0.85' color='#e8f4f0' font='RobotoCondensedBold'>Tablette Athena</t><br/><br/>" +
            "<t align='center' size='0.62' color='#c5d4de'>" +
            "L’écran intégré ne peut pas démarrer sur cette session Arma.<br/><br/>" +
            "Utilisez <t color='#7dffb3'>Ouvrir sur le PC</t> pour le portail Athena,<br/>" +
            "ou <t color='#7dffb3'>Carte terrain</t> pour la carte en jeu.<br/><br/>" +
            "Vérifiez aussi qu’Arma est à jour (2.14 ou plus)." +
            "</t>"
        );
    };
    [] spawn {
        uiSleep 0.35;
        if (!isNull (findDisplay 9974)) then {
            [] call comspec_overwatch_connect_fnc_webBrowserOpenSystem;
        };
    };
};

if (isNull _ctrl) exitWith {
    [_display] call _fncFallback;
};

_ctrl ctrlAddEventHandler ["PageLoaded", {
    _this call comspec_overwatch_connect_fnc_webBrowserPageLoaded;
}];

_ctrl ctrlAddEventHandler ["JSDialog", {
    _this call comspec_overwatch_connect_fnc_webBrowserJSDialog;
}];

// Carte native : masquée au départ ; double-clic = marqueur
missionNamespace setVariable ["COMSPEC_WebBrowser_MapVisible", false];
missionNamespace setVariable ["COMSPEC_WebBrowser_MapAutoOpened", false];
missionNamespace setVariable ["COMSPEC_WebBrowser_BrowserPos", []];
missionNamespace setVariable ["COMSPEC_WebBrowser_MapZoom", 1];
missionNamespace setVariable ["COMSPEC_WebBrowser_MapShowNames", true];
missionNamespace setVariable ["COMSPEC_WebBrowser_MapUnits", []];
if ((missionNamespace getVariable ["COMSPEC_TabletPendingView", ""]) isEqualTo "") then {
    missionNamespace setVariable ["COMSPEC_TabletPendingView", "bft", false];
};

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
} forEach [9420, 9421, 9422, 9423, 9424, 9425, 9426, 9427, 9428, 9430];

private _hint = _display displayCtrl 9403;
if (!isNull _hint) then {
    _hint ctrlSetStructuredText parseText "<t align='right' size='0.5' color='#8aa0b4'>Ouverture de l’écran tactique…</t>";
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

// Shell local : LoadFile d’abord (fiable pour les gros HTML).
// Repli UTF-8 base64 si le fichier est lisible mais LoadFile ne déclenche pas PageLoaded.
private _htmlPath = "z\comspec_overwatch\addons\connect\web\tablet.html";
_ctrl ctrlWebBrowserAction ["LoadFile", _htmlPath];

[_display, _ctrl, _htmlPath, _token, _fncFallback] spawn {
    params ["_disp", "_browser", "_path", "_tok", "_fncFallback"];
    uiSleep 2.2;
    if (isNull _disp || {isNull _browser}) exitWith {};
    if !((missionNamespace getVariable ["COMSPEC_WebBrowser_RefreshToken", -1]) isEqualTo _tok) exitWith {};
    if (missionNamespace getVariable ["COMSPEC_WebBrowser_PageReady", false]) exitWith {};

    private _html = loadFile _path;
    if (_html isEqualTo "") exitWith {
        [_disp] call _fncFallback;
        private _hint2 = _disp displayCtrl 9403;
        if (!isNull _hint2) then {
            _hint2 ctrlSetStructuredText parseText "<t align='right' size='0.5' color='#ff8a7a'>Contenu tablette introuvable</t>";
        };
    };

    private _hint = _disp displayCtrl 9403;
    if (!isNull _hint) then {
        _hint ctrlSetStructuredText parseText "<t align='right' size='0.5' color='#ffd27a'>Nouvelle tentative de chargement…</t>";
    };

    private _b64 = controlNull ctrlWebBrowserAction ["ToBase64", _html];
    if (_b64 isEqualType "" && {!(_b64 isEqualTo "")}) then {
        _browser ctrlWebBrowserAction ["OpenDataAsURL", format ["data:text/html;charset=utf-8;base64,%1", _b64]];
    } else {
        _browser ctrlWebBrowserAction ["OpenDataAsURL", format ["data:text/html;charset=utf-8,%1", _html]];
    };

    uiSleep 4;
    if (isNull _disp) exitWith {};
    if !((missionNamespace getVariable ["COMSPEC_WebBrowser_RefreshToken", -1]) isEqualTo _tok) exitWith {};
    if (missionNamespace getVariable ["COMSPEC_WebBrowser_PageReady", false]) exitWith {};
    if (!isNull _hint) then {
        _hint ctrlSetStructuredText parseText "<t align='right' size='0.5' color='#ffd27a'>Chargement long — essayez « Ouvrir sur le PC »</t>";
    };
};
