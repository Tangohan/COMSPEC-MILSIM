/*
    Ouvre une vue joueur.
    Par défaut (ATAK Enhanced only) : couche Athena / cTAB — pas la tablette Chromium.
    Params: [_view, _fromAtak]
      _view — bft|chat|orders|alerts|radio|status|apps|help|callsign|cas|briefing|phone|account|medical|tactical|manifest|modules
      _fromAtak — true si ouvert depuis ATAK Enhanced (autorise la tablette legacy)
*/
params [
    ["_view", "bft", [""]],
    ["_fromAtak", false, [true]]
];

if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

private _v = toLower (trim _view);
if (_v isEqualTo "") then { _v = "bft"; };
if (_v isEqualTo "hub") then { _v = "apps"; };
if (_v isEqualTo "tablet" || {_v isEqualTo "webbrowser"}) then { _v = "bft"; };

// Formulaires dédiés (toujours hors Chromium, même si ATAK-only est décoché)
if (_v isEqualTo "briefing") exitWith {
    [] call comspec_overwatch_connect_fnc_openBriefingBoard;
};
if (_v isEqualTo "cas") exitWith {
    [] call comspec_overwatch_connect_fnc_casRequestShow;
};
if (_v isEqualTo "manifest") exitWith {
    [] call comspec_overwatch_connect_fnc_flightManifestShow;
};

// Mode ATAK-only (défaut) : bascule vers l’app Athena, jamais un toast mort.
if !([_fromAtak] call comspec_overwatch_connect_fnc_canOpenOverwatchUi) exitWith {
    private _tab = switch (_v) do {
        case "orders": { "order" };
        case "medical";
        case "tactical";
        case "alerts";
        case "alert": { "urgences" };
        case "chat";
        case "msg": { "messages" };
        case "phone";
        case "callsign";
        case "account";
        case "link": { "liaison" };
        case "photo";
        case "photos": { "photo" };
        case "modules": { "modules" };
        default { "all" };
    };
    [_tab] call comspec_overwatch_connect_fnc_openAthenaFeature;
};

missionNamespace setVariable ["COMSPEC_TabletPendingView", _v, false];

if (isNull (findDisplay 9974)) then {
    [_fromAtak] call comspec_overwatch_connect_fnc_webBrowserShow;
};

[_v, _fromAtak] spawn {
    params ["_view", "_fromAtak"];
    private _t = diag_tickTime + 4;
    waitUntil {
        !isNull (findDisplay 9974)
        || {diag_tickTime > _t}
    };
    if (isNull (findDisplay 9974)) exitWith {};

    private _t2 = diag_tickTime + 3;
    waitUntil {
        missionNamespace getVariable ["COMSPEC_WebBrowser_PageReady", false]
        || {diag_tickTime > _t2}
    };

    uiSleep 0.08;
    private _disp = findDisplay 9974;
    if (isNull _disp) exitWith {};

    private _ctrl = _disp displayCtrl 9401;
    if (!isNull _ctrl && {missionNamespace getVariable ["COMSPEC_WebBrowser_PageReady", false]}) then {
        private _safe = [_view] call comspec_overwatch_connect_fnc_webBrowserJsEscape;
        _ctrl ctrlWebBrowserAction [
            "ExecJS",
            format [
                "if(window.COMSPEC_setView){window.COMSPEC_setView('%1');} if(window.COMSPEC_setFooterMsg){window.COMSPEC_setFooterMsg('Vue %1');}",
                _safe
            ]
        ];
    };

    if (_view isEqualTo "bft") then {
        [] call comspec_overwatch_connect_fnc_webBrowserMapShow;
    } else {
        [_view] call comspec_overwatch_connect_fnc_webBrowserMapHide;
    };
};
