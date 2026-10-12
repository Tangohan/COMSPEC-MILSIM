/* Barre d'état (batterie, météo, heure, réseau), pastilles, suivi carte en HUD et pages vivantes. */
disableSerialization;
private _d = [] call comspec_atak_native_fnc_display;
if (isNull _d) exitWith {};
private _state = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _data = uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap];
private _dir = "\z\comspec_atak_native\addons\main\data\";
// Coque jour / nuit selon l'heure de la mission.
private _shell = ([] call comspec_atak_native_fnc_layoutGet) get "phoneTexture";
if ((ctrlText (_d displayCtrl 88509)) isNotEqualTo _shell) then { (_d displayCtrl 88509) ctrlSetText ((_shell) call comspec_atak_native_fnc_tr); };

private _legacy = missionNamespace getVariable ["COMSPEC_LinkState", ""];
private _net = switch (toLower _legacy) do {
    case "linked": { "CONNECTED" };
    case "connecting";
    case "degraded": { "DEGRADED" };
    default { _state getOrDefault ["networkState", "OFFLINE"] };
};
// Anti-battement : un nouvel état doit tenir 3 s (perte) ou 5 s (retour) avant d'être affiché, sinon la barre
// d'état clignote connecté / hors ligne quand COMSPEC Link hésite entre deux états.
(uiNamespace getVariable ["COMSPEC_ATAK_NetShown", [_net, _net, diag_tickTime]]) params ["_netCur", "_netCand", "_netSince"];
if (_net isEqualTo _netCur) then { _netCand = _net; _netSince = diag_tickTime; } else {
    if (_net isNotEqualTo _netCand) then { _netCand = _net; _netSince = diag_tickTime; };
    if (diag_tickTime - _netSince >= ([5, 3] select (_net isEqualTo "OFFLINE"))) then { _netCur = _net; };
};
uiNamespace setVariable ["COMSPEC_ATAK_NetShown", [_netCur, _netCand, _netSince]];
_net = _netCur;
_state set ["networkState", _net];
private _sig = _d displayCtrl 88527;
// Barres : débit simulé (fn_linkQuality), qui tient compte de la liaison Athena quand Overwatch est là.
private _lq = [] call comspec_atak_native_fnc_linkQuality;
private _bars = if (_lq get "sim") then { _lq get "bars" } else { switch (_net) do { case "CONNECTED": { 4 }; case "DEGRADED": { 2 }; default { 0 }; } };
_sig ctrlSetText (((_dir + format ["sig_%1.paa", _bars])) call comspec_atak_native_fnc_tr);
_sig ctrlSetTextColor (switch (true) do { case (_bars >= 3): { [0.36, 0.78, 0.42, 1] }; case (_bars >= 1): { [0.95, 0.67, 0.20, 1] }; default { [0.88, 0.25, 0.22, 1] }; });
_sig ctrlSetTooltip ((format ["%1 · %2 · %3 kbit/s · %4 ms · perte %5 %%", switch (_net) do { case "CONNECTED": { "Athena connecté" }; case "DEGRADED": { "Athena dégradé" }; default { "Athena hors ligne" }; }, _lq get "label", _lq get "kbps", _lq get "latency", _lq get "loss"]) call comspec_atak_native_fnc_tr);
// Mode avion : icône d'avion à la place des barres.
if (_lq getOrDefault ["airplane", false]) then {
    _sig ctrlSetText (("\a3\ui_f\data\map\vehicleicons\iconplane_ca.paa") call comspec_atak_native_fnc_tr);
    _sig ctrlSetTextColor [0.95, 0.67, 0.20, 1];
    _sig ctrlSetTooltip (("Mode avion : réseau coupé (Bluetooth disponible)") call comspec_atak_native_fnc_tr);
};
[] call comspec_atak_native_fnc_deviceOverlay;
// Écran de verrouillage (heure, batterie, verrouillage au rallumage) et volet rapide (batterie, réseau).
["tick"] call comspec_atak_native_fnc_lockScreen;
["tick"] call comspec_atak_native_fnc_quickSettings;

private _bat = [] call comspec_atak_native_fnc_battery;
private _batCtrl = _d displayCtrl 88512;
_batCtrl ctrlSetText (((_dir + format ["bat_%1.paa", switch (true) do { case (_bat > 80): { 100 }; case (_bat > 55): { 75 }; case (_bat > 30): { 50 }; case (_bat > 12): { 25 }; default { 10 }; }])) call comspec_atak_native_fnc_tr);
_batCtrl ctrlSetTextColor ([[0.90, 0.94, 0.91, 1], [0.88, 0.25, 0.22, 1]] select (_bat <= 12));
(_d displayCtrl 88513) ctrlSetText ((((str floor _bat) + "%")) call comspec_atak_native_fnc_tr);
// Mémoire vive simulée (fn_ramUsage) : pastille de la barre d'état, détail en info-bulle.
private _ram = [] call comspec_atak_native_fnc_ramUsage;
private _ramCtrl = uiNamespace getVariable ["COMSPEC_ATAK_RamCtrl", controlNull];
if (!isNull _ramCtrl) then {
    private _pct = _ram get "pct";
    _ramCtrl ctrlSetText ((("RAM " + (str _pct) + "%")) call comspec_atak_native_fnc_tr);
    _ramCtrl ctrlSetTextColor (switch (true) do { case (_pct >= 90): { [0.88, 0.25, 0.22, 1] }; case (_pct >= 75): { [0.95, 0.67, 0.20, 1] }; default { [0.62, 0.70, 0.66, 1] }; });
    _ramCtrl ctrlSetTooltip ((format ["Mémoire vive : %1 / %2 Mo · %3 app(s) en mémoire", _ram get "used", _ram get "total", _ram get "apps"]) call comspec_atak_native_fnc_tr);
};

([] call comspec_atak_native_fnc_weather) params ["_temp", "_speed", "_card"];
(_d displayCtrl 88524) ctrlSetText ((format ["%1°C   %2 %3", _temp, _card, round _speed]) call comspec_atak_native_fnc_tr);
(_d displayCtrl 88524) ctrlSetTooltip ((format ["Vent du %1, %2 m/s", _card, round _speed]) call comspec_atak_native_fnc_tr);
(_d displayCtrl 88525) ctrlSetText ((([dayTime, "HH:MM"] call BIS_fnc_timeToString)) call comspec_atak_native_fnc_tr);
// Cloche : orange tant qu'il reste des notifications non lues. Logo COMSPEC Link : affiché quand la liaison est établie.
private _unread = _state getOrDefault ["notifUnread", 0];
private _bell = _d displayCtrl 88548;
_bell ctrlSetTextColor ([[0.90, 0.94, 0.91, 1], [0.95, 0.67, 0.20, 1]] select (_unread > 0));
_bell ctrlSetTooltip (((["Centre de notifications", format ["Centre de notifications : %1 non lue(s)", _unread]] select (_unread > 0))) call comspec_atak_native_fnc_tr);
private _linkOk = if ([] call comspec_atak_native_fnc_bridge) then {
    _net isEqualTo "CONNECTED" && {missionNamespace getVariable ["COMSPEC_AthenaReady", false]}
} else {
    _net isEqualTo "CONNECTED"
};
(_d displayCtrl 88549) ctrlShow _linkOk;

private _page = _state getOrDefault ["activePage", "LAUNCHER"];
// App Athena ouverte : se redessine quand la session, la liaison ou le message changent
// (avec Overwatch : ses variables ; sans : l'état renvoyé par la DLL native).
if (_page isEqualTo "ATHENA") then {
    private _str = { params ["_k", "_d"]; private _r = missionNamespace getVariable [_k, _d]; if (_r isEqualType "") then { _r } else { str _r } };
    private _sig = if ([] call comspec_atak_native_fnc_bridge) then {
        [["comspec_overwatch_auth_state", "—"] call _str, toLower (["COMSPEC_LinkState", "offline"] call _str), missionNamespace getVariable ["COMSPEC_AthenaReady", false]]
    } else {
        private _cells = (["GetAuthState", []] call comspec_atak_native_fnc_extensionCall) splitString "|";
        private _st = _cells param [1, "—"];
        [_st, ["offline", "linked"] select (_st isEqualTo "READY"), _st isEqualTo "READY"]
    };
    private _old = uiNamespace getVariable ["COMSPEC_ATAK_AthenaSig", []];
    private _hint = uiNamespace getVariable ["COMSPEC_ATAK_AthenaHint", ["", false, 0]];
    private _hintExpired = ((_old param [3, ""]) isNotEqualTo "") && {diag_tickTime - (_hint param [2, 0]) > 30};
    if (_hintExpired || {(_old select [0, 3]) isNotEqualTo _sig}) then {
        [{ ["ATHENA"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
    };
};
if (_page isEqualTo "MAP") then {
    [] call comspec_atak_native_fnc_mapOverlayUpdate;
    [] call comspec_atak_native_fnc_routeBanner;
    if (missionNamespace getVariable ["COMSPEC_ATAK_InspOpen", false]) then { [] call comspec_atak_native_fnc_inspectorUpdate; };
    // Porté (HUD) ou suivi activé : la carte reste centrée sur le joueur.
    if (!(_state getOrDefault ["interactive", false]) || {_state getOrDefault ["mapFollow", false]}) then {
        // Recentrage seulement si j'ai bougé (ou zoomé) : sinon la carte tremble à chaque seconde.
        private _fk = [round ((getPosASL player) select 0), round ((getPosASL player) select 1), ctrlMapScale ((([] call comspec_atak_native_fnc_display)) displayCtrl 88530)];
        private _fl = uiNamespace getVariable ["COMSPEC_ATAK_FollowLast", [0, 0, 0]];
        // Le suivi image par image (fn_mapOnDraw) prend le relais dès le premier centrage : ici, seulement au besoin
        // (premier affichage, zoom changé) ou en secours s'il a décroché (plus de 30 m).
        private _fMap = (([] call comspec_atak_native_fnc_display)) displayCtrl 88530;
        private _fFar = ((count (_fMap getVariable ["comspec_lastAim", []])) isNotEqualTo 2) || {(([_fk select 0, _fk select 1] distance2D [_fl select 0, _fl select 1]) > 30)};
        if (_fFar || {(_fk select 2) isNotEqualTo (_fl select 2)}) then {
            uiNamespace setVariable ["COMSPEC_ATAK_FollowLast", _fk];
            [player] call comspec_atak_native_fnc_mapCenter;
        };
    };
};

// Vibration à l'arrivée d'un message (Athena, alerte TOC ou message direct).
private _inCount = ({(_x getOrDefault ["dir", ""]) isEqualTo "in"} count (_data getOrDefault ["p2p", []])) + (count (_data getOrDefault ["inbox", []]));
_inCount = _inCount + (if ([] call comspec_atak_native_fnc_bridge) then {
    {!(_x param [5, false])} count (missionNamespace getVariable ["COMSPEC_Comms_Messages", []])
} else {
    {!(_x get "mine")} count ([] call comspec_atak_native_fnc_messagesAll)
});
private _prevIn = uiNamespace getVariable ["COMSPEC_ATAK_InCount", -1];
if (_prevIn >= 0 && {_inCount > _prevIn}) then { [] call comspec_atak_native_fnc_vibrate; };
uiNamespace setVariable ["COMSPEC_ATAK_InCount", _inCount];

// Pastilles : on ne reconstruit le dock (et le lanceur) que si un compteur change.
private _badges = [["CHAT"] call comspec_atak_native_fnc_appBadge, ["TASK"] call comspec_atak_native_fnc_appBadge];
if (_badges isNotEqualTo (uiNamespace getVariable ["COMSPEC_ATAK_BadgeSig", []])) then {
    uiNamespace setVariable ["COMSPEC_ATAK_BadgeSig", _badges];
    if (_page isEqualTo "LAUNCHER") then { [_page] call comspec_atak_native_fnc_pageRender; } else { [] call comspec_atak_native_fnc_dockRender; };
};

// Pages vivantes : re-rendu quand leurs données changent (le brouillon de message est conservé).
private _pageSig = switch (_page) do {
    // Dernier message compris : la liste est plafonnée, son nombre seul ne bouge plus une fois pleine.
    case "CHAT": {
        private _all = [] call comspec_atak_native_fnc_messagesAll;
        private _last = _all param [(count _all) - 1, createHashMap];
        [count _all, _last getOrDefault ["id", ""], _last getOrDefault ["time", ""], _last getOrDefault ["body", ""], count (_data getOrDefault ["p2p", []])]
    };
    case "TASK": { [count ([] call comspec_atak_native_fnc_tasksAll), (values ([] call comspec_atak_native_fnc_tasksAll)) apply { _x getOrDefault ["status", ""] }, (simpleTasks player) apply { taskState _x }, (values (missionNamespace getVariable ["COMSPEC_ATAK_OrderAcks", createHashMap])) apply { (values _x) apply { _x select 1 } }] };
    // Débit simulé laissé de côté : il varie sans cesse et faisait redessiner toute la page chaque seconde
    // (la page se rafraîchit d'elle-même toutes les 5 s).
    case "NETWORK": { [_lq get "bars", ([] call comspec_atak_native_fnc_deviceHealth) get "state", count (missionNamespace getVariable ["COMSPEC_ATAK_NetQueue", []])] };
    case "DEBUG": { [count (missionNamespace getVariable ["COMSPEC_ATAK_Log", []]), count (missionNamespace getVariable ["COMSPEC_DiagLog", []]), count (missionNamespace getVariable ["COMSPEC_ATAK_NetLog", []])] };
    case "BFT": { ((values (_data getOrDefault ["units", createHashMap])) select { (_x getOrDefault ["affiliation", ""]) isEqualTo "friend" }) apply { [_x getOrDefault ["freshness", ""], round (((_x getOrDefault ["position", [0,0,0]]) distance2D player) / 50), alive (_x getOrDefault ["object", objNull])] } };
    case "GPS": {
        // Le texte saisi est gardé avant un éventuel re-rendu.
        (uiNamespace getVariable ["COMSPEC_ATAK_Gps", createHashMap]) set ["grid", ["gpsGrid", ""] call comspec_atak_native_fnc_formValue];
        private _rt = missionNamespace getVariable ["COMSPEC_ATAK_Route", createHashMap];
        [count _rt, _rt getOrDefault ["idx", 0], round ((getPosASL player) select 0) / 25, round ((getPosASL player) select 1) / 25, missionNamespace getVariable ["COMSPEC_ATAK_RouteBusy", false]]
    };
    case "GROUP": { (units group player) apply { [name _x, alive _x, lifeState _x, round ((damage _x) * 4)] } };
    default { [] };
};
// BFT et GPS changent presque chaque seconde dans une grosse mission : au plus un rendu complet toutes les 3 s
// (la signature n'est retenue qu'une fois la page redessinée, le changement n'est donc pas perdu).
private _sigWait = _page in ["BFT", "GPS"] && {diag_tickTime - (_state getOrDefault ["sigRenderAt", 0]) < 3};
if ((_state getOrDefault ["pageSigPage", ""]) isEqualTo _page && {_pageSig isNotEqualTo (_state getOrDefault ["pageSig", []])}) then {
    if (_sigWait) exitWith { _pageSig = _state getOrDefault ["pageSig", []]; };
    _state set ["sigRenderAt", diag_tickTime];
    [_page] call comspec_atak_native_fnc_pageRender;
};
_state set ["pageSig", _pageSig];
_state set ["pageSigPage", _page];
