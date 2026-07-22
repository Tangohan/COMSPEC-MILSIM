/*
    Affiche la carte Arma native et masque le Chromium (toujours au-dessus sinon).
    Params optionnels : [_radarZoom]
*/
params [["_radarZoom", -1]];

private _display = uiNamespace getVariable ["COMSPEC_WebBrowser_Display", displayNull];
if (isNull _display) then { _display = findDisplay 9974; };
if (isNull _display) exitWith {};

private _browser = _display displayCtrl 9401;
private _map = _display displayCtrl 9410;
if (isNull _map) exitWith {};

if (_radarZoom > 0) then {
    missionNamespace setVariable ["COMSPEC_WebBrowser_MapZoom", (_radarZoom max 0.25) min 8];
};

if (!isNull _browser) then { _browser ctrlShow false; };

{
    private _c = _display displayCtrl _x;
    if (!isNull _c) then { _c ctrlShow true; };
} forEach [9410, 9420, 9421, 9422, 9423, 9424, 9425, 9426, 9427, 9428];

missionNamespace setVariable ["COMSPEC_WebBrowser_MapVisible", true];

private _bp = if (!isNull _browser) then { ctrlPosition _browser } else {
    [
        safezoneX + 0.07 * safezoneW,
        safezoneY + 0.105 * safezoneH,
        0.86 * safezoneW,
        0.76 * safezoneH
    ]
};
_map ctrlSetPosition _bp;
_map ctrlCommit 0;

[] call comspec_overwatch_connect_fnc_webBrowserMapCenter;

private _hint = _display displayCtrl 9403;
if (!isNull _hint) then {
    _hint ctrlSetStructuredText parseText "<t align='right' size='0.55' color='#7dffb3'>Carte terrain Arma</t>";
};
