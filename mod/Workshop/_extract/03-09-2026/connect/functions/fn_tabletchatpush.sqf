/*
    Pousse le fil TOC vers la tablette HTML si elle est ouverte.
*/
if (!hasInterface) exitWith { false };

private _display = uiNamespace getVariable ["COMSPEC_WebBrowser_Display", displayNull];
if (isNull _display) exitWith { false };
if !(missionNamespace getVariable ["COMSPEC_WebBrowser_PageReady", false]) exitWith { false };
if ((missionNamespace getVariable ["COMSPEC_WebBrowser_Mode", "local"]) isEqualTo "athena") exitWith { false };

private _ctrl = _display displayCtrl 9401;
if (isNull _ctrl) exitWith { false };

private _rows = [] call comspec_overwatch_connect_fnc_tabletChatLines;
private _jsParts = [];
{
    _x params ["_from", "_text", "_time", "_dir", "_kind", ["_grid", ""]];
    _jsParts pushBack format [
        "{from:'%1',text:'%2',time:'%3',dir:'%4',kind:'%5',grid:'%6'}",
        [_from] call comspec_overwatch_connect_fnc_webBrowserJsEscape,
        [_text] call comspec_overwatch_connect_fnc_webBrowserJsEscape,
        [_time] call comspec_overwatch_connect_fnc_webBrowserJsEscape,
        [_dir] call comspec_overwatch_connect_fnc_webBrowserJsEscape,
        [_kind] call comspec_overwatch_connect_fnc_webBrowserJsEscape,
        [_grid] call comspec_overwatch_connect_fnc_webBrowserJsEscape
    ];
} forEach _rows;

private _js = format [
    "if(window.COMSPEC_setChat){window.COMSPEC_setChat([%1]);}",
    _jsParts joinString ","
];
_ctrl ctrlWebBrowserAction ["ExecJS", _js];
true
