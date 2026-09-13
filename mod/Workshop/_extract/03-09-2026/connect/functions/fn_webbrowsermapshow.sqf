/*
    Affiche la carte Arma native et masque le Chromium (toujours au-dessus sinon).
    Params optionnels : [_radarZoom]
*/
params [["_radarZoom", -1]];

private _display = uiNamespace getVariable ["COMSPEC_WebBrowser_Display", displayNull];
if (isNull _display) then { _display = findDisplay 9974; };
if (isNull _display) exitWith {
    ["COMSPEC_Warning", ["Ouvrez d’abord la tablette Athena pour afficher la carte terrain."]] call comspec_overwatch_connect_fnc_showNotification;
};

private _browserX = safezoneX + 0.09 * safezoneW;
private _browserY = safezoneY + 0.12 * safezoneH;
private _browserW = 0.82 * safezoneW;
private _browserH = 0.72 * safezoneH;

private _browser = _display displayCtrl 9401;
private _map = _display displayCtrl 9410;

// Création à chaud si le contrôle carte n’a pas été instancié
if (isNull _map) then {
    if (isClass (configFile >> "COMSPEC_TabletMap")) then {
        _map = _display ctrlCreate ["COMSPEC_TabletMap", 9410];
    };
    if (isNull _map && {isClass (configFile >> "RscMapControl")}) then {
        _map = _display ctrlCreate ["RscMapControl", 9410];
        if (!isNull _map) then {
            _map ctrlAddEventHandler ["Draw", {
                [(_this select 0)] call comspec_overwatch_connect_fnc_webBrowserMapOnDraw;
            }];
        };
    };
};

if (isNull _map) exitWith {
    private _hint = _display displayCtrl 9403;
    if (!isNull _hint) then {
        _hint ctrlSetStructuredText parseText "<t align='right' size='0.5' color='#ff8a7a'>Carte terrain indisponible</t>";
    };
    ["COMSPEC_Warning", ["Carte terrain indisponible. Utilisez « Ouvrir sur le PC » pour le portail Athena."]] call comspec_overwatch_connect_fnc_showNotification;
};

if (_radarZoom > 0) then {
    missionNamespace setVariable ["COMSPEC_WebBrowser_MapZoom", (_radarZoom max 0.25) min 8];
};

// Chromium reste souvent dessiné au-dessus même avec ctrlShow false → hors écran + désactivé
if (!isNull _browser) then {
    if ((missionNamespace getVariable ["COMSPEC_WebBrowser_BrowserPos", []]) isEqualTo []) then {
        missionNamespace setVariable ["COMSPEC_WebBrowser_BrowserPos", ctrlPosition _browser];
    };
    _browser ctrlEnable false;
    _browser ctrlShow false;
    _browser ctrlSetPosition [safezoneX - 10, safezoneY - 10, 0.01, 0.01];
    _browser ctrlCommit 0;
};

private _help = _display displayCtrl 9430;
if (!isNull _help) then { _help ctrlShow false; };

{
    private _c = _display displayCtrl _x;
    if (!isNull _c) then { _c ctrlShow true; };
} forEach [9410, 9420, 9421, 9422, 9423, 9424, 9425, 9426, 9427, 9428];

missionNamespace setVariable ["COMSPEC_WebBrowser_MapVisible", true];
missionNamespace setVariable ["COMSPEC_TabletPendingView", "bft", false];

_map ctrlEnable true;
_map ctrlShow true;
_map ctrlSetPosition [_browserX, _browserY, _browserW, _browserH];
_map ctrlCommit 0;
ctrlSetFocus _map;

[] call comspec_overwatch_connect_fnc_webBrowserMapCenter;

private _hint = _display displayCtrl 9403;
if (!isNull _hint) then {
    _hint ctrlSetStructuredText parseText "<t align='right' size='0.5' color='#7dffb3'>Carte terrain</t>";
};
