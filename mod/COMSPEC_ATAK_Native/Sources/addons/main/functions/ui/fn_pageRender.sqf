params [["_page", "LAUNCHER"]];
disableSerialization;
private _d = ([] call comspec_atak_native_fnc_display);
if (isNull _d) exitWith { false };
// Garder la saisie en cours de l'app Athena quand la page se redessine.
private _form = uiNamespace getVariable ["COMSPEC_ATAK_Form", createHashMap];
if ("email" in _form || {"pair" in _form} || {"otp" in _form}) then {
    private _draft = uiNamespace getVariable ["COMSPEC_ATAK_AthenaDraft", createHashMap];
    { if (_x in _form) then { _draft set [_x, [_x] call comspec_atak_native_fnc_formValue]; }; } forEach ["email", "otp", "pair"];
    uiNamespace setVariable ["COMSPEC_ATAK_AthenaDraft", _draft];
};
// Défilement de la page affichée gardé (par page) : rendu de la même page (rafraîchissement) ou retour arrière
// (fn_back, fn_navigate sans empilement) le retrouvent au lieu de remonter en haut.
private _st = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _scrollMem = _st getOrDefault ["scrollMem", createHashMap];
private _drawn = _st getOrDefault ["pageDrawn", ""];
if (_drawn isNotEqualTo "") then { _scrollMem set [_drawn, ctrlScrollValues (_d displayCtrl 88531)]; };
_st set ["scrollMem", _scrollMem];
private _restore = (_drawn isEqualTo _page || {_st getOrDefault ["scrollRestore", false]}) && {!(_page in ["MAP", "CHAT"])};
_st set ["scrollRestore", false];
_st set ["pageDrawn", _page];
[] call comspec_atak_native_fnc_frsDraftSave;
[] call comspec_atak_native_fnc_recoDraftSave;
[] call comspec_atak_native_fnc_firesSave;
[] call comspec_atak_native_fnc_pageClear;
[] call comspec_atak_native_fnc_layoutApply;

private _title = "APPLICATIONS";
if (_page isNotEqualTo "LAUNCHER") then {
    private _app = ([] call comspec_atak_native_fnc_appList) select { (_x get "page") isEqualTo _page };
    _title = if ((count _app) > 0) then { toUpper ((_app select 0) get "name") } else { _page };
};
if (_page isEqualTo "RECENTS") then { _title = "APPS RÉCENTES"; };
if (_page isEqualTo "NOTIFS") then { _title = "NOTIFICATIONS"; };
(_d displayCtrl 88518) ctrlSetText _title;

switch (_page) do {
    case "LAUNCHER": { [] call comspec_atak_native_fnc_launcherRender; };
    case "MAP": { [] call comspec_atak_native_fnc_pageMap; };
    case "CHAT": { [] call comspec_atak_native_fnc_pageChat; };
    case "GROUP": { [] call comspec_atak_native_fnc_pageGroup; };
    case "BFT": { [] call comspec_atak_native_fnc_pageBft; };
    case "RECENTS": { [] call comspec_atak_native_fnc_pageRecents; };
    case "NOTIFS": { [] call comspec_atak_native_fnc_pageNotifs; };
    case "FOOD": { [] call comspec_atak_native_fnc_pageFood; };
    case "DATING": { [] call comspec_atak_native_fnc_pageDating; };
    case "OSINT": { [] call comspec_atak_native_fnc_pageOsint; };
    case "CREDITS": { [] call comspec_atak_native_fnc_pageCredits; };
    case "BDA": { [] call comspec_atak_native_fnc_pageBda; };
    case "REPORTS": { [] call comspec_atak_native_fnc_pageReports; };
    case "SSE": { [] call comspec_atak_native_fnc_pageSse; };
    case "WANTED": { [] call comspec_atak_native_fnc_pageWanted; };
    case "DRONEDETECT": { [] call comspec_atak_native_fnc_pageDroneDetect; };
    case "DRONE": { [] call comspec_atak_native_fnc_pageDrone; };
    case "AAR": { [] call comspec_atak_native_fnc_pageAar; };
    case "C2": { [] call comspec_atak_native_fnc_pageC2; };
    case "EXPLO": { [] call comspec_atak_native_fnc_pageExplo; };
    case "BREACH": { [] call comspec_atak_native_fnc_pageBreach; };
    case "SNIPER": { [] call comspec_atak_native_fnc_pageSniper; };
    case "JTAC": { [] call comspec_atak_native_fnc_pageJtac; };
    case "MUSIC": { [] call comspec_atak_native_fnc_pageMusic; };
    case "GPS": { [] call comspec_atak_native_fnc_pageGps; };
    case "WAYPOINTS": { [] call comspec_atak_native_fnc_pageWaypoints; };
    case "DEBUG": { [] call comspec_atak_native_fnc_pageDebug; };
    case "WEATHER": { [] call comspec_atak_native_fnc_pageWeather; };
    case "RESYNCH": { [] call comspec_atak_native_fnc_pageResynch; };
    case "STATUS": { [] call comspec_atak_native_fnc_pageStatus; };
    case "RELIEF": { [] call comspec_atak_native_fnc_pageRelief; };
    case "WAVERELAY": { [] call comspec_atak_native_fnc_pageWaveRelay; };
    case "LOGI": { [] call comspec_atak_native_fnc_pageLogistics; };
    case "EW": { [] call comspec_atak_native_fnc_pageEw; };
    case "TASK": { [] call comspec_atak_native_fnc_pageTasks; };
    case "ATHENA": { [] call comspec_atak_native_fnc_pageAthena; };
    case "NETWORK": { [] call comspec_atak_native_fnc_pageNetwork; };
    case "SETTINGS": { [] call comspec_atak_native_fnc_pageSettings; };
    case "PHOTOS": { [] call comspec_atak_native_fnc_pagePhotos; };
    case "FRS": { [] call comspec_atak_native_fnc_pageFrs; };
    case "RECO": { [] call comspec_atak_native_fnc_pageReco; };
    case "FIRES": { [] call comspec_atak_native_fnc_pageFires; };
    case "ALERTS": { [] call comspec_atak_native_fnc_pageAlerts; };
    case "MEDICAL": { [] call comspec_atak_native_fnc_pageMedical; };
    case "PROFILE": { [] call comspec_atak_native_fnc_pageProfile; };
    case "LIVECAM": { [] call comspec_atak_native_fnc_pageLivecam; };
    case "BRIEFING": { [] call comspec_atak_native_fnc_pageBriefing; };
    default {
        // Modules externes (COMSPEC Modules) : une app déclarée par un autre addon avec function="tag_fnc_page"
        // dans COMSPEC_ATAK_Apps est dessinée par cette fonction. Params : [page, [0, 0, largeur, hauteur]].
        private _cfg = ("true" configClasses (configFile >> "COMSPEC_ATAK_Apps")) select { (toUpper getText (_x >> "page")) isEqualTo _page };
        private _fn = if ((count _cfg) > 0) then { getText ((_cfg select 0) >> "function") } else { "" };
        if (_fn isNotEqualTo "" && {!isNil _fn}) then {
            ((([] call comspec_atak_native_fnc_layoutGet) get "body")) params ["", "", "_bw", "_bh"];
            [_page, [0, 0, _bw, _bh]] call (missionNamespace getVariable _fn);
        } else {
            [_page] call comspec_atak_native_fnc_pageText;
        };
    };
};
// Événement pour les modules : une app vient de s'afficher.
["comspec_atak_native_pageOpened", [_page]] call CBA_fnc_localEvent;
// Dégâts de l'écran par-dessus la page.
uiNamespace setVariable ["COMSPEC_ATAK_DevOverlay", []];
[] call comspec_atak_native_fnc_deviceOverlay;
// Défilement retrouvé (et encore à l'image suivante, quand la hauteur du contenu est connue).
private _sv = _scrollMem getOrDefault [_page, []];
if (_restore && {_sv isEqualType []} && {(count _sv) isEqualTo 2} && {(_sv select 0) > 0}) then {
    (_d displayCtrl 88531) ctrlSetScrollValues [_sv select 0, -1];
    [{
        params ["_page", "_v"];
        private _d2 = [] call comspec_atak_native_fnc_display;
        if (isNull _d2 || {((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["pageDrawn", ""]) isNotEqualTo _page}) exitWith {};
        (_d2 displayCtrl 88531) ctrlSetScrollValues [_v, -1];
    }, [_page, _sv select 0]] call CBA_fnc_execNextFrame;
};
// Toasts recréés après la page pour rester au premier plan.
[] call comspec_atak_native_fnc_notificationsRender;
true
