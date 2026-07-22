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

missionNamespace setVariable ["COMSPEC_WebBrowser_MapVisible", false];

if (!isNull _browser) then {
    _browser ctrlShow true;
    if (missionNamespace getVariable ["COMSPEC_WebBrowser_PageReady", false]) then {
        private _safeView = [_view] call comspec_overwatch_connect_fnc_webBrowserJsEscape;
        _browser ctrlWebBrowserAction ["ExecJS", format [
            "if(window.COMSPEC_setView){window.COMSPEC_setView('%1');} if(window.COMSPEC_setFooterMsg){window.COMSPEC_setFooterMsg('Menus tablette');}",
            _safeView
        ]];
    };
};

private _hint = _display displayCtrl 9403;
if (!isNull _hint) then {
    _hint ctrlSetStructuredText parseText "<t align='right' size='0.55' color='#7dffb3'>Écran tactique</t>";
};
