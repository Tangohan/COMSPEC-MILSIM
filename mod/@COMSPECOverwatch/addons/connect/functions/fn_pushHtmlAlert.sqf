/*
    File d'alertes pour la tablette HTML (toast + journal Alertes).
    Params: [_type, _title, _body, _priority]
      type     : link | medical | order | ping | system
      priority : info | warn | critical
*/
params [
    ["_type", "system", [""]],
    ["_title", "", [""]],
    ["_body", "", [""]],
    ["_priority", "info", [""]]
];

if (!hasInterface) exitWith {};

_type = toLower (trim _type);
if (!(_type in ["link", "medical", "order", "ping", "system"])) then { _type = "system"; };
_priority = toLower (trim _priority);
if (!(_priority in ["info", "warn", "critical"])) then { _priority = "info"; };
_title = trim _title;
_body = trim _body;
if (_title isEqualTo "" && {_body isEqualTo ""}) exitWith {};
if (_title isEqualTo "") then { _title = "Alerte"; };

private _ts = round time;
private _id = format ["a%1_%2", _ts, floor (random 9999)];
private _entry = [_id, _type, _title, _body, _priority, _ts];

private _queue = missionNamespace getVariable ["COMSPEC_HtmlAlerts", []];
if (!(_queue isEqualType [])) then { _queue = []; };
_queue pushBack _entry;
if ((count _queue) > 40) then {
    _queue = _queue select [(count _queue) - 40, 40];
};
missionNamespace setVariable ["COMSPEC_HtmlAlerts", _queue, false];

// Push immédiat si tablette Chromium ouverte
private _display = uiNamespace getVariable ["COMSPEC_WebBrowser_Display", displayNull];
if (isNull _display) exitWith {};
if !(missionNamespace getVariable ["COMSPEC_WebBrowser_PageReady", false]) exitWith {};
if ((missionNamespace getVariable ["COMSPEC_WebBrowser_Mode", "local"]) isEqualTo "athena") exitWith {};

private _ctrl = _display displayCtrl 9401;
if (isNull _ctrl) exitWith {};

private _safeTitle = [_title] call comspec_overwatch_connect_fnc_webBrowserJsEscape;
private _safeBody = [_body] call comspec_overwatch_connect_fnc_webBrowserJsEscape;
private _safeType = [_type] call comspec_overwatch_connect_fnc_webBrowserJsEscape;
private _safePrio = [_priority] call comspec_overwatch_connect_fnc_webBrowserJsEscape;

private _js = format [
    "if(window.COMSPEC_pushAlert){window.COMSPEC_pushAlert({id:'%1',type:'%2',title:'%3',body:'%4',priority:'%5',ts:%6});}",
    _id,
    _safeType,
    _safeTitle,
    _safeBody,
    _safePrio,
    _ts
];
_ctrl ctrlWebBrowserAction ["ExecJS", _js];
