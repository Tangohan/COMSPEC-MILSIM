/*
    Ouvre la tablette Athena (9974) et bascule sur une vue HTML.
    Params: [_view] — bft|chat|orders|alerts|radio|status|apps|help|callsign|cas|briefing|phone|account|medical|tactical|manifest|modules
*/
params [["_view", "bft", [""]]];

if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

private _v = toLower (trim _view);
if (_v isEqualTo "") then { _v = "bft"; };
if (_v isEqualTo "hub") then { _v = "apps"; };
if (_v isEqualTo "tablet" || {_v isEqualTo "webbrowser"}) then { _v = "bft"; };

missionNamespace setVariable ["COMSPEC_TabletPendingView", _v, false];

if (isNull (findDisplay 9974)) then {
    [] call comspec_overwatch_connect_fnc_webBrowserShow;
};

[_v] spawn {
    params ["_view"];
    private _t = diag_tickTime + 4;
    waitUntil {
        !isNull (findDisplay 9974)
        || {diag_tickTime > _t}
    };
    if (isNull (findDisplay 9974)) exitWith {};
    // Attendre que la page soit prête
    private _t2 = diag_tickTime + 3;
    waitUntil {
        missionNamespace getVariable ["COMSPEC_WebBrowser_PageReady", false]
        || {diag_tickTime > _t2}
    };
    uiSleep 0.08;
    private _disp = findDisplay 9974;
    if (isNull _disp) exitWith {};
    private _ctrl = _disp displayCtrl 9401;
    if (isNull _ctrl) exitWith {};
    private _safe = [_view] call comspec_overwatch_connect_fnc_webBrowserJsEscape;
    _ctrl ctrlWebBrowserAction [
        "ExecJS",
        format [
            "if(window.COMSPEC_setView){window.COMSPEC_setView('%1');} if(window.COMSPEC_setFooterMsg){window.COMSPEC_setFooterMsg('Vue %1');}",
            _safe
        ]
    ];
    // Masquer la carte native si on n’est pas en BFT
    if !(_view isEqualTo "bft") then {
        [_view] call comspec_overwatch_connect_fnc_webBrowserMapHide;
    };
};
