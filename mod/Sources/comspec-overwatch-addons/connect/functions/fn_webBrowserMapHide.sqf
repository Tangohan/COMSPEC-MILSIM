/*
    Masque la carte native et réaffiche le shell HTML.
    Params : [_view] — vue HTML à activer (bft, chat, alerts, apps…).
*/
params [["_view", "bft"]];

private _display = uiNamespace getVariable ["COMSPEC_WebBrowser_Display", displayNull];
if (isNull _display) then { _display = findDisplay 9974; };
if (isNull _display) exitWith {};

private _browser = _display displayCtrl 9401;
private _map = _display displayCtrl 9410;

{
    private _c = _display displayCtrl _x;
    if (!isNull _c) then { _c ctrlShow false; };
} forEach [9410, 9420, 9421, 9422, 9423, 9424, 9425, 9426, 9427, 9428];

if (!isNull _map) then {
    _map ctrlEnable false;
};

[] call comspec_overwatch_connect_fnc_mapContextMenuClose;

missionNamespace setVariable ["COMSPEC_WebBrowser_MapVisible", false];
missionNamespace setVariable ["COMSPEC_TabletPendingView", _view, false];

private _browserX = safezoneX + 0.09 * safezoneW;
private _browserY = safezoneY + 0.12 * safezoneH;
private _browserW = 0.82 * safezoneW;
private _browserH = 0.72 * safezoneH;
private _saved = missionNamespace getVariable ["COMSPEC_WebBrowser_BrowserPos", []];
if ((count _saved) >= 4) then {
    _browserX = _saved select 0;
    _browserY = _saved select 1;
    _browserW = _saved select 2;
    _browserH = _saved select 3;
};

if (!isNull _browser) then {
    _browser ctrlSetPosition [_browserX, _browserY, _browserW, _browserH];
    _browser ctrlCommit 0;
    _browser ctrlShow true;
    _browser ctrlEnable true;
    if (missionNamespace getVariable ["COMSPEC_WebBrowser_PageReady", false]) then {
        private _safeView = [_view] call comspec_overwatch_connect_fnc_webBrowserJsEscape;
        _browser ctrlWebBrowserAction ["ExecJS", format [
            "if(window.COMSPEC_setView){window.COMSPEC_setView('%1');} if(window.COMSPEC_setFooterMsg){window.COMSPEC_setFooterMsg('Menus tablette');}",
            _safeView
        ]];
    };
} else {
    private _help = _display displayCtrl 9430;
    if (!isNull _help) then { _help ctrlShow true; };
};

private _hint = _display displayCtrl 9403;
if (!isNull _hint) then {
    private _color = if (isNull _browser) then {"#ff8a7a"} else {"#7dffb3"};
    private _msg = if (isNull _browser) then {"Écran intégré indisponible"} else {"Écran tactique"};
    _hint ctrlSetStructuredText parseText format [
        "<t align='right' size='0.5' color='%1'>%2</t>",
        _color,
        _msg
    ];
};
