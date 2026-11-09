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
// Barre d'état : chaque contrôle n'est réécrit que si sa valeur change (avant : une douzaine d'écritures par seconde).
private _put = {
    params ["_c", "_k", "_v"];
    if ((_c getVariable [_k, 0]) isEqualTo _v) exitWith {};
    _c setVariable [_k, _v];
    switch (_k) do { case "cs_t": { _c ctrlSetText ((_v) call comspec_atak_native_fnc_tr); }; case "cs_tt": { _c ctrlSetTooltip ((_v) call comspec_atak_native_fnc_tr); }; default { _c ctrlSetTextColor _v; }; };
};
private _sigT = ((_dir + format ["sig_%1.paa", _bars])) call comspec_atak_native_fnc_tr;
private _sigC = switch (true) do { case (_bars >= 3): { [0.36, 0.78, 0.42, 1] }; case (_bars >= 1): { [0.95, 0.67, 0.20, 1] }; default { [0.88, 0.25, 0.22, 1] }; };
private _sigTt = (format ["%1 · %2 · %3 kbit/s · %4 ms · perte %5 %%", switch (_net) do { case "CONNECTED": { "Athena connecté" }; case "DEGRADED": { "Athena dégradé" }; default { "Athena hors ligne" }; }, _lq get "label", _lq get "kbps", _lq get "latency", _lq get "loss"]) call comspec_atak_native_fnc_tr;
// Mode avion : icône d'avion à la place des barres.
if (_lq getOrDefault ["airplane", false]) then {
    _sigT = ("\a3\ui_f\data\map\vehicleicons\iconplane_ca.paa") call comspec_atak_native_fnc_tr;
    _sigC = [0.95, 0.67, 0.20, 1];
    _sigTt = ("Mode avion : réseau coupé (Bluetooth disponible)") call comspec_atak_native_fnc_tr;
};
[_sig, "cs_t", _sigT] call _put;
[_sig, "cs_c", _sigC] call _put;
[_sig, "cs_tt", _sigTt] call _put;
[] call comspec_atak_native_fnc_deviceOverlay;
// Écran de verrouillage (heure, batterie, verrouillage au rallumage) et volet rapide (batterie, réseau).
["tick"] call comspec_atak_native_fnc_lockScreen;
if (uiNamespace getVariable ["COMSPEC_ATAK_PadUp", false]) then { ["tick"] call comspec_atak_native_fnc_phonePad; };
if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "BLUETOOTH") then { ["scan"] call comspec_atak_native_fnc_btAction; };
["tick"] call comspec_atak_native_fnc_quickSettings;

private _bat = [] call comspec_atak_native_fnc_battery;
private _batCtrl = _d displayCtrl 88512;
[_batCtrl, "cs_t", ((_dir + format ["bat_%1.paa", switch (true) do { case (_bat > 80): { 100 }; case (_bat > 55): { 75 }; case (_bat > 30): { 50 }; case (_bat > 12): { 25 }; default { 10 }; }])) call comspec_atak_native_fnc_tr] call _put;
[_batCtrl, "cs_c", [[0.90, 0.94, 0.91, 1], [0.88, 0.25, 0.22, 1]] select (_bat <= 12)] call _put;
[_d displayCtrl 88513, "cs_t", (((str floor _bat) + "%")) call comspec_atak_native_fnc_tr] call _put;
// Mémoire vive simulée (fn_ramUsage) : pastille de la barre d'état, détail en info-bulle.
private _ram = [] call comspec_atak_native_fnc_ramUsage;
private _ramCtrl = uiNamespace getVariable ["COMSPEC_ATAK_RamCtrl", controlNull];
if (!isNull _ramCtrl) then {
    private _pct = _ram get "pct";
    [_ramCtrl, "cs_t", (("RAM " + (str _pct) + "%")) call comspec_atak_native_fnc_tr] call _put;
    [_ramCtrl, "cs_c", switch (true) do { case (_pct >= 90): { [0.88, 0.25, 0.22, 1] }; case (_pct >= 75): { [0.95, 0.67, 0.20, 1] }; default { [0.62, 0.70, 0.66, 1] }; }] call _put;
    [_ramCtrl, "cs_tt", (format ["Mémoire vive : %1 / %2 Mo · %3 app(s) en mémoire · estimation du téléphone, pas la mémoire du PC", _ram get "used", _ram get "total", _ram get "apps"]) call comspec_atak_native_fnc_tr] call _put;
};

// Cadenas et libellé du chiffrement : état réel de ma liaison (fn_meshKeys "link"), ouvert tant qu'elle n'est pas chiffrée.
(["link"] call comspec_atak_native_fnc_meshKeys) params ["_lockCode", "_lockShort", "_lockLong", "_lockWhy", "_lockImg", "_lockCol"];
private _lockCtrl = uiNamespace getVariable ["COMSPEC_ATAK_LockCtrl", controlNull];
if (!isNull _lockCtrl) then {
    private _lockTt = (format ["%1 : %2", (_lockLong) call comspec_atak_native_fnc_tr, (_lockWhy) call comspec_atak_native_fnc_tr]);
    [_lockCtrl, "cs_t", _lockImg] call _put;
    [_lockCtrl, "cs_c", _lockCol] call _put;
    [_lockCtrl, "cs_tt", _lockTt] call _put;
    private _lockTxt = uiNamespace getVariable ["COMSPEC_ATAK_LockTxt", controlNull];
    [_lockTxt, "cs_t", _lockShort] call _put;
    [_lockTxt, "cs_c", _lockCol] call _put;
    [_lockTxt, "cs_tt", _lockTt] call _put;
};

// Icône de la radio MPU5 (fn_mpuLink "badge") : non reliée, câble, Wi-Fi, transmet, coupée. Lit le statut en cache ; texture, couleur et infobulle
// ne sont réécrites que si elles changent. Ne se montre que si elle tient dans la barre (fn_layoutApply).
private _mpuCtrl = uiNamespace getVariable ["COMSPEC_ATAK_MpuCtrl", controlNull];
if (!isNull _mpuCtrl) then {
    (["badge"] call comspec_atak_native_fnc_mpuLink) params ["_mpuCode", "_mpuImg", "_mpuCol", "_mpuTip"];
    private _mpuShow = _mpuCode isNotEqualTo "off" && {_mpuCtrl getVariable ["fit", false]};
    if ((ctrlShown _mpuCtrl) isNotEqualTo _mpuShow) then { _mpuCtrl ctrlShow _mpuShow; };
    if (_mpuShow) then {
        [_mpuCtrl, "cs_t", _mpuImg] call _put;
        [_mpuCtrl, "cs_c", _mpuCol] call _put;
        private _mpuWhy = ["", format [" %1", ((["status"] call comspec_atak_native_fnc_mpuLink) getOrDefault ["why", ""]) call comspec_atak_native_fnc_tr]] select (_mpuCode isEqualTo "cut");
        [_mpuCtrl, "cs_tt", (_mpuTip call comspec_atak_native_fnc_tr) + _mpuWhy] call _put;
    };
};

([] call comspec_atak_native_fnc_weather) params ["_temp", "_speed", "_card"];
[_d displayCtrl 88524, "cs_t", (format ["%1°C   %2 %3", _temp, _card, round _speed]) call comspec_atak_native_fnc_tr] call _put;
[_d displayCtrl 88524, "cs_tt", (format ["Vent du %1, %2 m/s", _card, round _speed]) call comspec_atak_native_fnc_tr] call _put;
[_d displayCtrl 88525, "cs_t", (([dayTime, "HH:MM"] call BIS_fnc_timeToString)) call comspec_atak_native_fnc_tr] call _put;
// Cloche : orange tant qu'il reste des notifications non lues. Logo COMSPEC Link : affiché quand la liaison est établie.
private _unread = _state getOrDefault ["notifUnread", 0];
private _bell = _d displayCtrl 88548;
[_bell, "cs_c", [[0.90, 0.94, 0.91, 1], [0.95, 0.67, 0.20, 1]] select (_unread > 0)] call _put;
[_bell, "cs_tt", ((["Centre de notifications", format ["Centre de notifications : %1 non lue(s)", _unread]] select (_unread > 0))) call comspec_atak_native_fnc_tr] call _put;
private _linkOk = if ([] call comspec_atak_native_fnc_bridge) then {
    _net isEqualTo "CONNECTED" && {missionNamespace getVariable ["COMSPEC_AthenaReady", false]}
} else {
    _net isEqualTo "CONNECTED"
};
// Terminal compromis (fn_compromiseView) : logo rouge clignotant, même relié (certificats révoqués, liaison refusée).
["refresh"] call comspec_atak_native_fnc_compromiseView;
// Nouvel appareil après une compromission validée : enregistré dès qu'un téléphone est sur le joueur.
["deviceTick"] call comspec_atak_native_fnc_compromise;
private _comp = ["get"] call comspec_atak_native_fnc_compromiseView;
private _logo = _d displayCtrl 88549;
if ((count _comp) > 0) then {
    if !(ctrlShown _logo) then { _logo ctrlShow true; };
    [_logo, "cs_c", [0.92, 0.22, 0.18, [0.35, 1] select (((floor diag_tickTime) mod 2) isEqualTo 0)]] call _put;
    [_logo, "cs_tt", (format ["COMSPEC Link : terminal %1, liaison de données coupée (%2)", ["compromis", "capturé"] select ((_comp getOrDefault ["state", ""]) isEqualTo "captured"), ["origine manuelle", "origine automatique"] select ((_comp getOrDefault ["origin", ""]) isEqualTo "auto")]) call comspec_atak_native_fnc_tr] call _put;
} else {
    if ((ctrlShown _logo) isNotEqualTo _linkOk) then { _logo ctrlShow _linkOk; };
    [_logo, "cs_c", [0.36, 0.78, 0.42, 1]] call _put;
    [_logo, "cs_tt", ("COMSPEC Link connecté") call comspec_atak_native_fnc_tr] call _put;
};

private _page = _state getOrDefault ["activePage", "LAUNCHER"];
// App Athena ouverte : se redessine quand la session, la liaison ou le message changent
// (avec Overwatch : ses variables ; sans : l'état renvoyé par la DLL native).
if (_page isEqualTo "ATHENA") then {
    private _str = { params ["_k", "_d"]; private _r = missionNamespace getVariable [_k, _d]; if (_r isEqualType "") then { _r } else { str _r } };
    private _sig = if ([] call comspec_atak_native_fnc_bridge) then {
        [["comspec_overwatch_auth_state", "—"] call _str, toLower (["COMSPEC_LinkState", "offline"] call _str), missionNamespace getVariable ["COMSPEC_AthenaReady", false]]
    } else {
        private _cells = (["GetAuthState", []] call comspec_atak_native_fnc_extensionCall) splitString (toString [9]);
        private _st = _cells param [1, "—"];
        [_st, ["offline", "linked"] select (_st isEqualTo "READY"), _st isEqualTo "READY"]
    };
    private _old = uiNamespace getVariable ["COMSPEC_ATAK_AthenaSig", []];
    private _hint = uiNamespace getVariable ["COMSPEC_ATAK_AthenaHint", ["", false, 0]];
    private _hintExpired = ((_old param [3, ""]) isNotEqualTo "") && {diag_tickTime - (_hint param [2, 0]) > 30};
    if (_hintExpired || {(_old select [0, 3]) isNotEqualTo _sig}) then {
        [{ ["ATHENA"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
    };
};
// Service Athena choisi mais pas connecté au compte : bandeau qui ouvre l'app Athena (touché), après 25 s puis au plus
// toutes les 10 min. Vérifié toutes les 15 s (sans Overwatch, l'état vient de la DLL).
if (_page isNotEqualTo "ATHENA" && {diag_tickTime >= (missionNamespace getVariable ["COMSPEC_ATAK_LoginCheckAt", 0])}) then {
    missionNamespace setVariable ["COMSPEC_ATAK_LoginCheckAt", diag_tickTime + 15];
    if (([] call comspec_atak_native_fnc_athenaMode) isNotEqualTo "on") exitWith {};
    private _auth = if ([] call comspec_atak_native_fnc_bridge) then {
        private _a = missionNamespace getVariable ["comspec_overwatch_auth_state", ""];
        if (_a isEqualType "") then { _a } else { str _a }
    } else {
        ((["GetAuthState", []] call comspec_atak_native_fnc_extensionCall) splitString (toString [9])) param [1, ""]
    };
    if (_auth isEqualTo "READY") exitWith { missionNamespace setVariable ["COMSPEC_ATAK_LoginSince", -1]; };
    private _since = missionNamespace getVariable ["COMSPEC_ATAK_LoginSince", -1];
    if (_since < 0) exitWith { missionNamespace setVariable ["COMSPEC_ATAK_LoginSince", diag_tickTime]; };
    if (diag_tickTime - _since > 25 && {diag_tickTime >= (missionNamespace getVariable ["COMSPEC_ATAK_LoginNagAt", 0])}) then {
        missionNamespace setVariable ["COMSPEC_ATAK_LoginNagAt", diag_tickTime + 600];
        ["WARNING", "Non connecté à votre compte Athena : touchez ici pour vous connecter", 8, 40, "ATHENA"] call comspec_atak_native_fnc_notify;
    };
};
if (_page isEqualTo "MAP") then {
    ["tick"] call comspec_atak_native_fnc_pageQuickMap;
    [] call comspec_atak_native_fnc_mapOverlayUpdate;
    [] call comspec_atak_native_fnc_routeBanner;
    if (missionNamespace getVariable ["COMSPEC_ATAK_InspOpen", false]) then { [] call comspec_atak_native_fnc_inspectorUpdate; };
    ["tick"] call comspec_atak_native_fnc_unitCard;
    ["mapTick"] call comspec_atak_native_fnc_squadBar;
    // Porté (HUD) ou suivi activé : la carte reste centrée sur le joueur.
    if (!(_state getOrDefault ["interactive", false]) || {_state getOrDefault ["mapFollow", false]}) then {
        // Le suivi image par image (fn_mapOnDraw) prend le relais dès le premier centrage : ici, seulement au besoin
        // (premier affichage, zoom changé) ou en secours s'il a décroché (centre visible à plus de 30 m de mon point).
        // Avant : recentrage complet tous les 30 m parcourus, même quand le suivi tenait ; chaque recentrage coupe le
        // suivi 0,6 s (fn_mapCenter) : en marchant ou en véhicule, la carte s'arrêtait puis sautait toutes les quelques secondes.
        private _fMap = ([] call comspec_atak_native_fnc_mapCtrl);
        (ctrlPosition _fMap) params ["_fcx", "_fcy", "_fcw", "_fch"];
        private _fVis = _fMap ctrlMapScreenToWorld [_fcx + _fcw / 2, _fcy + _fch / 2];
        private _fk = [0, 0, ctrlMapScale _fMap];
        private _fl = uiNamespace getVariable ["COMSPEC_ATAK_FollowLast", [0, 0, 0]];
        private _fFar = ((count (_fMap getVariable ["comspec_lastAim", []])) isNotEqualTo 2)
            || {ctrlMapAnimDone _fMap && {diag_tickTime - (_fMap getVariable ["comspec_centerAt", -1]) > 2} && {(([] call comspec_atak_native_fnc_gpsPos) distance2D _fVis) > 30}};
        if (_fFar || {(_fk select 2) isNotEqualTo (_fl select 2)}) then {
            uiNamespace setVariable ["COMSPEC_ATAK_FollowLast", _fk];
            [player] call comspec_atak_native_fnc_mapCenter;
        };
    };
};

// Vibration à l'arrivée d'un message (Athena, alerte TOC ou message direct).
private _inCount = ({(_x getOrDefault ["dir", ""]) isEqualTo "in"} count (_data getOrDefault ["p2p", []])) + (count (_data getOrDefault ["inbox", []]));
_inCount = _inCount + (if ([] call comspec_atak_native_fnc_bridge) then {
    {!(_x param [5, false])} count (["var", ["COMSPEC_Comms_Messages", []]] call comspec_atak_native_fnc_offline)
} else {
    {!(_x get "mine")} count ([] call comspec_atak_native_fnc_messagesAll)
});
private _prevIn = uiNamespace getVariable ["COMSPEC_ATAK_InCount", -1];
if (_prevIn >= 0 && {_inCount > _prevIn}) then { [] call comspec_atak_native_fnc_vibrate; };
uiNamespace setVariable ["COMSPEC_ATAK_InCount", _inCount];

// Pastilles : on ne reconstruit le dock (et le lanceur) que si un compteur change.
private _badges = [["CHAT"] call comspec_atak_native_fnc_appBadge, ["SMS"] call comspec_atak_native_fnc_appBadge, ["TASK"] call comspec_atak_native_fnc_appBadge];
if (_badges isNotEqualTo (uiNamespace getVariable ["COMSPEC_ATAK_BadgeSig", []])) then {
    uiNamespace setVariable ["COMSPEC_ATAK_BadgeSig", _badges];
    if (_page isEqualTo "LAUNCHER") then { [_page] call comspec_atak_native_fnc_pageRender; } else { [] call comspec_atak_native_fnc_dockRender; };
};

// Pages vivantes : re-rendu quand leurs données changent (le brouillon de message est conservé).
private _pageSig = switch (_page) do {
    // Dernier message compris : la liste est plafonnée, son nombre seul ne bouge plus une fois pleine.
    case "CHAT"; case "SMS": {
        private _all = [] call comspec_atak_native_fnc_messagesAll;
        private _last = _all param [(count _all) - 1, createHashMap];
        [count _all, _last getOrDefault ["id", ""], _last getOrDefault ["time", ""], _last getOrDefault ["body", ""], count (_data getOrDefault ["p2p", []])]
    };
    case "TASK": { private _ta = [] call comspec_atak_native_fnc_tasksAll; [count _ta, (values _ta) apply { _x getOrDefault ["status", ""] }, (simpleTasks player) apply { taskState _x }, (values (missionNamespace getVariable ["COMSPEC_ATAK_OrderAcks", createHashMap])) apply { (values _x) apply { _x select 1 } }] };
    // Débit simulé laissé de côté : il varie sans cesse et faisait redessiner toute la page chaque seconde
    // (la page se rafraîchit d'elle-même toutes les 5 s).
    case "NETWORK": { [_lq get "bars", ([] call comspec_atak_native_fnc_deviceHealth) get "state", count (missionNamespace getVariable ["COMSPEC_ATAK_NetQueue", []])] };
    case "DEBUG": { [count (missionNamespace getVariable ["COMSPEC_ATAK_Log", []]), count (missionNamespace getVariable ["COMSPEC_DiagLog", []]), count (missionNamespace getVariable ["COMSPEC_ATAK_NetLog", []])] };
    case "BFT": { (((values (_data getOrDefault ["units", createHashMap])) select { (_x getOrDefault ["affiliation", ""]) isEqualTo "friend" }) apply { [_x getOrDefault ["freshness", ""], round (((_x getOrDefault ["position", [0,0,0]]) distance2D player) / 50), alive (_x getOrDefault ["object", objNull])] }) + [["sig"] call comspec_atak_native_fnc_bftOrbat] };
    case "GPS": {
        // Le texte saisi est gardé avant un éventuel re-rendu.
        (uiNamespace getVariable ["COMSPEC_ATAK_Gps", createHashMap]) set ["grid", ["gpsGrid", ""] call comspec_atak_native_fnc_formValue];
        private _rt = missionNamespace getVariable ["COMSPEC_ATAK_Route", createHashMap];
        [count _rt, _rt getOrDefault ["idx", 0], round (((getPosASL player) select 0) / 25), round (((getPosASL player) select 1) / 25), missionNamespace getVariable ["COMSPEC_ATAK_RouteBusy", false]]
    };
    case "WAVERELAY": { [_lockCode] };
    case "GROUP": { (units group player) apply { [name _x, alive _x, lifeState _x, round ((damage _x) * 4)] } };
    default { [] };
};
// BFT et GPS changent presque chaque seconde dans une grosse mission : au plus un rendu complet toutes les 3 s
// (la signature n'est retenue qu'une fois la page redessinée, le changement n'est donc pas perdu).
private _sigWait = _page in ["BFT", "GPS"] && {diag_tickTime - (_state getOrDefault ["sigRenderAt", 0]) < 3};
// Saisie en cours (champ texte actif) : pas de re-rendu sous les doigts du joueur ; la signature n'est pas retenue,
// la page se redessine dès qu'il quitte le champ.
private _fc = focusedCtrl _d;
if (!isNull _fc && {(ctrlType _fc) isEqualTo 2}) then { _sigWait = true; };
if ((_state getOrDefault ["pageSigPage", ""]) isEqualTo _page && {_pageSig isNotEqualTo (_state getOrDefault ["pageSig", []])}) then {
    if (_sigWait) exitWith { _pageSig = _state getOrDefault ["pageSig", []]; };
    _state set ["sigRenderAt", diag_tickTime];
    // BFT : lignes, en-tête et fiche réécrites sur place quand c'est possible (aucun contrôle recréé, défilement gardé).
    private _pt = diag_tickTime;
    if (_page isEqualTo "BFT" && {["update"] call comspec_atak_native_fnc_pageBft}) exitWith { ["page BFT (mise à jour)", _pt] call comspec_atak_native_fnc_perfAcc; };
    [_page] call comspec_atak_native_fnc_pageRender;
};
_state set ["pageSig", _pageSig];
_state set ["pageSigPage", _page];
