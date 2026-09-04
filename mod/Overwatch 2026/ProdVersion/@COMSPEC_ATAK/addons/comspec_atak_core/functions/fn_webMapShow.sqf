private _display = uiNamespace getVariable ["COMSPEC_ATAK_Display",displayNull];
if (isNull _display) exitWith {false};

[_display] call COMSPEC_fnc_webLayout;

private _browser = _display displayCtrl 1100;

// HYBRID LIVEMAP:
// Chromium owns the ATAK chrome. The native map must sit ON TOP of the
// browser in the central hole — Arma's WebBrowser does not punch a
// transparent hole through an opaque page.
if (!isNull _browser) then
{
    _browser ctrlShow true;
    _browser ctrlEnable true;
};

missionNamespace setVariable ["COMSPEC_ATAK_MapVisible",true,false];
["activeApp","overwatch"] call COMSPEC_fnc_setState;

// All fallback native chrome stays hidden: HTML/CSS/JS is the actual ATAK UI.
{
    private _ctrl = _display displayCtrl _x;
    if (!isNull _ctrl) then
    {
        _ctrl ctrlShow false;
        _ctrl ctrlEnable false;
    };
} forEach [
    2209,2210,2211,2212,2213,2214,2215,2216,
    2220,2221,
    1090,9430,
    1150,1151,1152,1153
];

if (!isNull _browser) then
{
    _browser ctrlWebBrowserAction [
        "ExecJS",
        "if(window.COMSPEC_ATAK_setNativeMap){window.COMSPEC_ATAK_setNativeMap(true);}if(window.COMSPEC_ATAK_reportMapViewport){window.COMSPEC_ATAK_reportMapViewport();}"
    ];
};

// Recreate the map AFTER any browser show/JS so Chromium cannot cover it.
[] call COMSPEC_fnc_webMapRaise;
[] call COMSPEC_fnc_mapApplyTexture;
[] call COMSPEC_fnc_mapCenterOnPlayer;
true
