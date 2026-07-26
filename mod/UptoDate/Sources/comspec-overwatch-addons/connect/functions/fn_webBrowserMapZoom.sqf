/*
    Zoom carte native. Params : [_factor] (>1 rapproche, <1 éloigne)
*/
params [["_factor", 1.35]];

private _zoom = missionNamespace getVariable ["COMSPEC_WebBrowser_MapZoom", 1];
_zoom = ((_zoom * _factor) max 0.25) min 8;
missionNamespace setVariable ["COMSPEC_WebBrowser_MapZoom", _zoom];
[] call comspec_overwatch_connect_fnc_webBrowserMapCenter;
