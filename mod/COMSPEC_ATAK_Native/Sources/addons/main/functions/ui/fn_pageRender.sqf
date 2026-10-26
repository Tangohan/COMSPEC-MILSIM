params [["_page", "LAUNCHER"]];
private _perfT = diag_tickTime;
disableSerialization;
private _d = ([] call comspec_atak_native_fnc_display);
if (isNull _d) exitWith { false };
// Rendus comptés : fn_layoutApply (mise en page seule) ne redessine pas la page si un rendu a suivi.
uiNamespace setVariable ["COMSPEC_ATAK_RenderSeq", (uiNamespace getVariable ["COMSPEC_ATAK_RenderSeq", 0]) + 1];
// Garder la saisie en cours de l'app Athena quand la page se redessine.
private _form = uiNamespace getVariable ["COMSPEC_ATAK_Form", createHashMap];
if ("email" in _form || {"pair" in _form} || {"otp" in _form}) then {
    private _draft = uiNamespace getVariable ["COMSPEC_ATAK_AthenaDraft", createHashMap];
    { if (_x in _form) then { _draft set [_x, [_x] call comspec_atak_native_fnc_formValue]; }; } forEach ["email", "otp", "pair"];
    uiNamespace setVariable ["COMSPEC_ATAK_AthenaDraft", _draft];
};
// App JTAC : saisie du brief gardée (un appel radio reçu redessine la page).
if ("j5" in _form || {"jBda" in _form}) then { ["save"] call comspec_atak_native_fnc_jtacAction; };
// Défilement de la page affichée gardé (par page) : rendu de la même page (rafraîchissement) ou retour arrière
// (fn_back, fn_navigate sans empilement) le retrouvent au lieu de remonter en haut.
private _st = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _scrollMem = _st getOrDefault ["scrollMem", createHashMap];
private _drawn = _st getOrDefault ["pageDrawn", ""];
if (_drawn isNotEqualTo "") then { _scrollMem set [_drawn, ctrlScrollValues (_d displayCtrl 88531)]; };
_st set ["scrollMem", _scrollMem];
// Groupes défilants internes d'une page (liste et fil de la messagerie) : position relevée par nom avant l'effacement.
private _grpMem = _st getOrDefault ["scrollGroups", createHashMap];
{ _x params ["_n", "_c"]; if (!isNull _c) then { _grpMem set [_n, ctrlScrollValues _c]; }; } forEach (uiNamespace getVariable ["COMSPEC_ATAK_ScrollGroups", []]);
uiNamespace setVariable ["COMSPEC_ATAK_ScrollGroups", []];
_st set ["scrollGroups", _grpMem];
// Accueil et Réglages : toujours rouverts à la position quittée (retour d'une app du bas de la liste, réglage qui
// recrée l'écran).
private _restore = (_drawn isEqualTo _page || {_st getOrDefault ["scrollRestore", false]} || {_page in ["LAUNCHER", "SETTINGS"]}) && {!(_page in ["MAP", "CHAT"])};
_st set ["scrollRestore", false];
_st set ["pageDrawn", _page];
_st set ["pagePrev", _drawn]; // page dessinée juste avant (centre de notifications : « nouveau » à l'entrée seulement)
// Sous-écran déclaré pour la touche retour (fn_back) : la page le redéclare pendant son dessin si elle en affiche un.
_st deleteAt "backSub";
[] call comspec_atak_native_fnc_frsDraftSave;
[] call comspec_atak_native_fnc_recoDraftSave;
[] call comspec_atak_native_fnc_firesSave;
[] call comspec_atak_native_fnc_pageClear;
// Calques hors zone de contenu : recréés plus bas, après la page.
[createHashMap, false] call comspec_atak_native_fnc_layoutApply;

private _title = "APPLICATIONS";
if (_page isNotEqualTo "LAUNCHER") then {
    private _app = ([] call comspec_atak_native_fnc_appList) select { (_x get "page") isEqualTo _page };
    _title = if ((count _app) > 0) then { toUpper ((_app select 0) get "name") } else { _page };
};
if (_page isEqualTo "RECENTS") then { _title = "APPS RÉCENTES"; };
if (_page isEqualTo "NOTIFS") then { _title = "NOTIFICATIONS"; };
if (_page isEqualTo "PATCHES") then { _title = "PATCH AU DOS"; };
(_d displayCtrl 88518) ctrlSetText ((_title) call comspec_atak_native_fnc_tr);

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
    case "PATCHES": { ["page"] call comspec_atak_native_fnc_pagePatches; };
    case "PHOTOS": { [] call comspec_atak_native_fnc_pagePhotos; };
    case "FRS": { [] call comspec_atak_native_fnc_pageFrs; };
    case "RECO": { [] call comspec_atak_native_fnc_pageReco; };
    case "FIRES": { [] call comspec_atak_native_fnc_pageFires; };
    case "ALERTS": { [] call comspec_atak_native_fnc_pageAlerts; };
    case "SALUTE": { [] call comspec_atak_native_fnc_pageSalute; };
    case "MEDICAL": { [] call comspec_atak_native_fnc_pageMedical; };
    case "PROFILE": { [] call comspec_atak_native_fnc_pageProfile; };
    case "LIVECAM": { [] call comspec_atak_native_fnc_pageLivecam; };
    case "VIEW3D": { [] call comspec_atak_native_fnc_pageView3d; };
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
// Dégâts de l'écran par-dessus la page. Surface du verre, effets d'écran et calque Zeus aussi recréés : fn_pageClear
// vient de masquer et désactiver leurs contrôles (supprimés à l'image suivante) ; gardés tels quels (même signature),
// saleté, brouillage et verrouillage Zeus disparaissaient jusqu'au prochain fn_statusUpdate.
private _killOv = {
    {
        if (_x isEqualType controlNull) then { ctrlDelete _x; } else {
            if (_x isEqualType []) then { _x call _killOv; };
        };
    } forEach _this;
};
(uiNamespace getVariable ["COMSPEC_ATAK_DevOverlay", []]) call _killOv;
(((uiNamespace getVariable ["COMSPEC_ATAK_SurfOverlay", ["", []]]) param [1, []]) apply { _x param [1, controlNull] }) call _killOv;
(((uiNamespace getVariable ["COMSPEC_ATAK_ScreenFx", ["", []]]) param [1, []]) apply { _x param [1, controlNull] }) call _killOv;
(uiNamespace getVariable ["COMSPEC_ATAK_ZeusOv", []]) call _killOv;
uiNamespace setVariable ["COMSPEC_ATAK_DevOverlay", []];
uiNamespace setVariable ["COMSPEC_ATAK_SurfOverlay", ["", []]];
uiNamespace setVariable ["COMSPEC_ATAK_ScreenFx", ["", []]];
uiNamespace setVariable ["COMSPEC_ATAK_ZeusOv", []];
[] call comspec_atak_native_fnc_deviceOverlay;
// Effets recréés après la page : déjà au-dessus de tout ce qu'elle vient de créer (fn_overlayRaise n'a rien à faire).
uiNamespace setVariable ["COMSPEC_ATAK_OvSeq", uiNamespace getVariable ["COMSPEC_ATAK_CtrlSeq", 0]];
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
// Écran de verrouillage, volet rapide et toast Android remis devant la page redessinée (fn_fx).
["raise"] call comspec_atak_native_fnc_fx;
[format ["page %1 (dessin)", _page], _perfT] call comspec_atak_native_fnc_perfAcc;
true
