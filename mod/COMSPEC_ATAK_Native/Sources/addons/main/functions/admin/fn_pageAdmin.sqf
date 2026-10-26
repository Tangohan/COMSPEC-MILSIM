/*
    App ADMIN : interface de gestion dans le téléphone, pour la gestion de la communauté et les Zeus (rien sur l'écran de jeu).
    Onglets (clé admTab de l'état) :
      EN JEU     ATAK de chaque joueur de la partie : porté ou non, batterie, état, liaison Athena, versions, numéro / IMEI,
                 effets Zeus en cours ; actions Zeus par joueur (verrouiller, muet, brouiller, éteindre, tout rétablir),
                 et sa fiche Athena (compte, compromission, blocage) quand le parc est chargé
      ATHENA     parc ATAK de la communauté sur le site (comme Gestion > Parc ATAK) : bloquer, lever un blocage, déconnecter,
                 message imposé, déclarer ou lever une compromission ; recherche et pages de 40
      APPS       App Store : pour la partie, chaque app au choix des joueurs, obligatoire (installée chez tous, pas de
                 désinstallation) ou interdite (retirée des téléphones, ne s'ouvre plus) ; fn_appInstall, fn_adminServer « apps »
      DEBUG IA   téléphones ramassés par l'IA ennemie : porteur, déchiffrage, exploitation du BFT, groupes en traque et joueurs
                 repérés, IA en route pour ramasser ; valider / rejeter, arrêter la traque, reprendre le téléphone ; traque
                 coupée pour la partie
    Droits : voir fn_adminClient (en jeu : Zeus, hôte, admin connecté, gestionnaire désigné ; Athena : rôle sur le site).
    Relevé de la partie toutes les 10 s tant que l'app est à l'écran (rien sinon).
    Présentation (refonte 2.4.x, retour de TTRD) : tableau de bord en cartes (joueurs, alertes, effets Zeus, IA), une
    ligne par joueur / ATAK / téléphone avec pastille (initiale, couleur du camp ou de l'état) et boutons GÉRER / CARTE,
    fiche dépliée en lignes d'info, effets Zeus en interrupteurs OUI / NON et actions regroupées par thème (2 ou 3 par
    rangée au lieu de 4). Filtre TOUS / ALERTES / ZEUS sur l'onglet EN JEU.
    Params (app déclarée avec function=) : [page, [x, y, largeur, hauteur]]
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _esc = { params ["_t"]; if !(_t isEqualType "") then { _t = str _t; }; [[_t, "<", "&lt;"] call CBA_fnc_replace, ">", "&gt;"] call CBA_fnc_replace };
private _re = "[{ ['ADMIN'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;";
private _setS = { params ["_k", "_v"]; compile format ["(uiNamespace getVariable 'COMSPEC_ATAK_State') set [%1, %2]; %3", str _k, str _v, _re] };
private _inGame = [player] call comspec_atak_native_fnc_adminAllowed;
private _role = uiNamespace getVariable ["COMSPEC_ATAK_AdminRole", ""];
private _site = _role in ["manager", "platform"];
private _tab = _s getOrDefault ["admTab", ["ATHENA", "GAME"] select _inGame];
private _open = _s getOrDefault ["admOpen", ""];
private _confirm = _s getOrDefault ["admConfirm", ""];
private _ago = {
    params ["_sec"];
    if (_sec < 0) exitWith { "jamais" };
    if (_sec < 90) exitWith { format ["il y a %1 s", round _sec] };
    if (_sec < 5400) exitWith { format ["il y a %1 min", round (_sec / 60)] };
    if (_sec < 172800) exitWith { format ["il y a %1 h", round (_sec / 3600)] };
    format ["il y a %1 j", round (_sec / 86400)]
};
private _confirmBtn = {
    // Bouton à deux temps : le premier clic arme, le second fait.
    params ["_label", "_key", "_code", ["_help", ""], ["_enabled", true]];
    if (_confirm isEqualTo _key) exitWith {
        ["CONFIRMER : " + _label, compile format ["(uiNamespace getVariable 'COMSPEC_ATAK_State') set ['admConfirm', '']; %1 %2", _code, _re], "red", _enabled, _help]
    };
    [_label, compile format ["(uiNamespace getVariable 'COMSPEC_ATAK_State') set ['admConfirm', %1]; %2", str _key, _re], false, _enabled, _help]
};

private _ini = { params ["_n"]; toUpper ((((_n splitString " -_.:[]()|") select { _x isNotEqualTo "" }) param [0, "#"]) select [0, 1]) };
private _sideRgb = createHashMapFromArray [["WEST", [0.30, 0.56, 0.88, 1]], ["EAST", [0.90, 0.28, 0.23, 1]], ["GUER", [0.36, 0.78, 0.42, 1]], ["CIV", [0.71, 0.54, 0.88, 1]]];
// Bouton GÉRER / FERMER d'une ligne : déplie la fiche (une seule à la fois).
private _manage = { params ["_key"]; private _o = _open isEqualTo _key; [["GÉRER", "FERMER"] select _o, ["admOpen", ["", _key] select !_o] call _setS, _o, true, ["Afficher la fiche et les actions", "Replier la fiche"] select _o] };
private _mapBtn = { params ["_pos", ["_lbl", "CARTE"], ["_help", "Centre la carte sur ce point"]];
    [_lbl, compile format ["['MAP'] call comspec_atak_native_fnc_navigate; [{ [_this, 0.02] call comspec_atak_native_fnc_mapCenter; }, [%1, %2, 0], 0.25] call CBA_fnc_waitAndExecute;", _pos param [0, 0], _pos param [1, 0]], false, _pos isEqualType [] && {(count _pos) >= 2}, _help] };

// Relevé périodique tant que l'app est à l'écran.
if (_inGame && {isNil { uiNamespace getVariable "COMSPEC_ATAK_AdminPfh" }}) then {
    ["scan"] call comspec_atak_native_fnc_adminClient;
    uiNamespace setVariable ["COMSPEC_ATAK_AdminPfh", [{
        private _st = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        if (isNull ([] call comspec_atak_native_fnc_display) || {(_st getOrDefault ["activePage", ""]) isNotEqualTo "ADMIN"}) exitWith {
            [_this select 1] call CBA_fnc_removePerFrameHandler;
            uiNamespace setVariable ["COMSPEC_ATAK_AdminPfh", nil];
        };
        // Message en cours de saisie : pas de relevé (le redessin effacerait le texte).
        if ((_st getOrDefault ["admMsgFor", 0]) isNotEqualTo 0) exitWith {};
        ["scan"] call comspec_atak_native_fnc_adminClient;
    }, 10] call CBA_fnc_addPerFrameHandler];
};
// Parc Athena : chargé à la première ouverture, puis à la demande.
private _fleet = uiNamespace getVariable ["COMSPEC_ATAK_AdminFleet", []];
if (_fleet isEqualTo [] && {!(uiNamespace getVariable ["COMSPEC_ATAK_AdminFleetBusy", false])}) then { ["fleet", ["", 0]] call comspec_atak_native_fnc_adminClient; };
_fleet params [["_fAt", -1], ["_fRole", ""], ["_fTotal", 0], ["_fRows", []], ["_fErr", ""], ["_fQ", ""], ["_fOff", 0]];
// Fiches Athena par Steam, pour l'onglet EN JEU.
private _bySteam = createHashMap;
{ if ((_x param [2, ""]) isNotEqualTo "") then { _bySteam set [_x select 2, _x]; }; } forEach _fRows;

private _players = (values (uiNamespace getVariable ["COMSPEC_ATAK_AdminPlayers", createHashMap])) apply { _x select 0 };
_players = _players apply { [_x param [3, ""], toLower (_x param [2, ""]), _x] };
_players sort true;
_players = _players apply { _x select 2 };
(uiNamespace getVariable ["COMSPEC_ATAK_AdminDebug", [[], -1]]) params [["_dbg", []], ["_dbgAt", -1]];
_dbg params [["_cfg", []], ["_caps", []], ["_going", []], ["_srvNow", 0]];

// Alertes d'un joueur en jeu (servent au tableau de bord, au filtre ALERTES et aux pastilles de la liste).
private _alertsOf = {
    params ["_p"];
    _p params ["_uid", "", "", "", "", "_has", "_bat", "_dev", "", "", "", "", "_fx", "", "", "", "_sos", "", "", "_comp"];
    private _ath = _bySteam getOrDefault [_uid, []];
    private _f = [];
    if (_sos) then { _f pushBack ["SOS", "#f2ab33"]; };
    if (_comp isNotEqualTo "" || {(_ath param [5, "none"]) in ["captured", "compromised"]}) then { _f pushBack ["COMPROMIS", "#e5483a"]; };
    if ((parseNumber (_ath param [6, "0"])) > 0) then { _f pushBack ["BLOQUÉ", "#e5483a"]; };
    if (_dev in ["OFF", "BROKEN"]) then { _f pushBack [["ÉTEINT", "CASSÉ"] select (_dev isEqualTo "BROKEN"), "#e5483a"]; };
    if (_has && {_bat isEqualType 0} && {_bat <= 15}) then { _f pushBack ["BATTERIE", "#f2ab33"]; };
    if (!_has) then { _f pushBack ["SANS ATAK", "#8a9a93"]; };
    _f
};
private _nAlert = { private _a = [_x] call _alertsOf; (_a findIf { (_x select 0) isNotEqualTo "SANS ATAK" }) >= 0 } count _players;
private _nFx = { ((_x param [12, []]) isNotEqualTo []) } count _players;
private _nAtak = { _x param [5, false] } count _players;
private _rows = [];
// Tableau de bord : quatre cartes, toujours visibles en tête de l'app.
private _roleTxt = (createHashMapFromArray [["platform", "admin plateforme"], ["manager", "gestion"], ["none", "aucun droit site"]]) getOrDefault [_role, "droits site inconnus"];
_rows pushBack ["cells", [
    ["\z\comspec_atak_native\addons\main\data\app_group.paa", [0.30, 0.56, 0.88, 1], "JOUEURS", format ["%1", count _players], format ["%1 avec ATAK", _nAtak]],
    ["\z\comspec_atak_native\addons\main\data\app_alerts.paa", [[0.36, 0.78, 0.42, 1], [0.90, 0.28, 0.23, 1]] select (_nAlert > 0), "ALERTES", format ["%1", _nAlert], ["tout va bien", "SOS, compromis, éteints…"] select (_nAlert > 0)],
    ["\z\comspec_atak_native\addons\main\data\app_admin.paa", [[0.45, 0.5, 0.47, 1], [0.95, 0.67, 0.2, 1]] select (_nFx > 0), "EFFETS ZEUS", format ["%1", _nFx], ["aucun en cours", "joueur(s) concerné(s)"] select (_nFx > 0)],
    ["\z\comspec_atak_native\addons\main\data\app_sse.paa", [[0.45, 0.5, 0.47, 1], [0.90, 0.28, 0.23, 1]] select ((count _caps) > 0), "IA ENNEMIE", format ["%1", count _caps], ["aucun téléphone ramassé", "téléphone(s) aux mains de l'IA"] select ((count _caps) > 0)]
], 2];
_rows pushBack ["text", format ["<t size='0.8' color='#8a9a93'>%1 · en jeu : %2 · Athena : %3</t>", [[player] call comspec_atak_native_fnc_unitCallsign] call _esc, ["aucun droit", "Zeus / gestion"] select _inGame, _roleTxt]];
private _tabBtn = { params ["_t", "_k"]; [_t, ["admTab", _k] call _setS, _tab isEqualTo _k] };
_rows pushBack ["segment", "", [
    [format ["EN JEU (%1)", count _players], "GAME"] call _tabBtn,
    [format ["ATHENA (%1)", _fTotal], "ATHENA"] call _tabBtn,
    [format ["IA ENNEMIE (%1)", count _caps], "DEBUG"] call _tabBtn
] + ([[], [[format ["APPS (%1)", count ((["policy"] call comspec_atak_native_fnc_appInstall) select 0) + count ((["policy"] call comspec_atak_native_fnc_appInstall) select 1)], "APPS"] call _tabBtn]] select _inGame)];

switch (_tab) do {
    case "APPS": {
        if (!_inGame) exitWith {
            _rows pushBack ["text", "<t color='#8a9a93'>Réservé au Zeus, à l'admin du serveur (#login) et aux gestionnaires désignés par le serveur. La communauté impose ses apps depuis Athena (Contrôle serveur > Apps du téléphone).</t>"];
        };
        private _mis = missionNamespace getVariable ["COMSPEC_ATAK_AppPolicy", [[], []]];
        if !(_mis isEqualType []) then { _mis = [[], []]; };
        _mis params [["_mReq", []], ["_mForb", []]];
        _rows pushBack ["section", "App Store : règles de la partie", "OBLIGATOIRE : installée chez tous, impossible à désinstaller · INTERDITE : retirée des téléphones, ne s'ouvre plus · LIBRE : au choix de chaque joueur"];
        _rows pushBack ["buttons", [["TOUT LIBÉRER", "apps:reset", "['server', ['apps', ['', 'reset']]] call comspec_atak_native_fnc_adminClient;", "Lève les règles de la partie (celles du serveur et d'Athena restent)", (count _mReq) + (count _mForb) > 0] call _confirmBtn]];
        {
            private _id = _x get "id";
            private _lid = toLower _id;
            private _rule = switch (true) do { case (_lid in _mForb): { "forbidden" }; case (_lid in _mReq): { "required" }; default { "free" }; };
            private _src = ((["source", _id] call comspec_atak_native_fnc_appInstall) splitString " +") - ["mission"];
            private _st = ["state", _id] call comspec_atak_native_fnc_appInstall;
            private _set = { params ["_r"]; compile format ["['server', ['apps', [%1, %2]]] call comspec_atak_native_fnc_adminClient;", str _id, str _r] };
            _rows pushBack ["person", _x get "icon", format ["<t font='COMSPEC_BarlowBold'>%1</t>  <t size='0.75' color='#8a9a93'>%2</t>%3", _x get "name", _x get "section",
                ["", format ["<br/><t size='0.75' color='#e8b84a'>aussi %1 par : %2</t>", ["obligatoire", "interdite"] select (_st isEqualTo "forbidden"), _src joinString ", "]] select (_src isNotEqualTo [])],
                [["LIBRE", ["free"] call _set, _rule isEqualTo "free", true, "Au choix de chaque joueur"],
                 ["OBLIGATOIRE", ["required"] call _set, _rule isEqualTo "required", true, "Installée chez tous, impossible à désinstaller"],
                 ["INTERDITE", ["forbidden"] call _set, [false, "red"] select (_rule isEqualTo "forbidden"), true, "Retirée de tous les téléphones"]],
                [[1, 1, 1, 1], [0.45, 0.48, 0.46, 1]] select (_st isEqualTo "forbidden")];
        } forEach (([] call comspec_atak_native_fnc_appList) select { !(_x get "system") && {!((_x get "id") in ["Admin", "Toc"])} });
    };
    case "GAME": {
        if (!_inGame) exitWith {
            _rows pushBack ["text", "<t color='#8a9a93'>Réservé au Zeus, à l'admin du serveur (#login) et aux gestionnaires désignés par le serveur (réglage « Gestionnaires en jeu »). Le parc Athena reste disponible selon vos droits sur le site.</t>"];
        };
        private _filter = _s getOrDefault ["admFilter", "ALL"];
        private _fBtn = { params ["_t", "_k"]; [_t, ["admFilter", _k] call _setS, _filter isEqualTo _k] };
        _rows pushBack ["segment", "Afficher", [["TOUS", "ALL"] call _fBtn, [format ["ALERTES (%1)", _nAlert], "ALERT"] call _fBtn, [format ["ZEUS (%1)", _nFx], "FX"] call _fBtn]];
        _rows pushBack ["buttons", [
            ["ACTUALISER", { ["scan"] call comspec_atak_native_fnc_adminClient; }, true, true, "Redemande l'état de chaque ATAK (relevé auto toutes les 10 s)"],
            ["TOUT RÉTABLIR", "resetAll", format ["{ ['zeus', [getPlayerUID _x, 'reset']] call comspec_atak_native_fnc_adminClient; } forEach (allPlayers select { (_x getVariable ['COMSPEC_ATAK_ZeusFx', []]) isNotEqualTo [] });"], "Retire les effets Zeus de tous les joueurs", _nFx > 0] call _confirmBtn
        ]];
        private _shown = switch (_filter) do {
            case "ALERT": { _players select { private _a = [_x] call _alertsOf; (_a findIf { (_x select 0) isNotEqualTo "SANS ATAK" }) >= 0 } };
            case "FX": { _players select { (_x param [12, []]) isNotEqualTo [] } };
            default { _players };
        };
        if (_players isEqualTo []) then { _rows pushBack ["empty", "\z\comspec_atak_native\addons\main\data\app_admin.paa", "En attente des joueurs", "Chaque ATAK répond au relevé en quelques secondes."]; };
        if (_players isNotEqualTo [] && {_shown isEqualTo []}) then { _rows pushBack ["text", "<t color='#8a9a93'>Aucun joueur dans ce filtre.</t>"]; };
        {
            _x params ["_uid", "_name", "_cs", "_side", "_grp", "_has", "_bat", "_dev", "_aKey", "_aTitle", "_mod", "_link", "_fx", "_num", "_imei", "_tuid", "_sos", "_beacon", "_pos", "_comp", ["_net", -1]];
            private _fxKeys = _fx apply { _x select 0 };
            private _ath = _bySteam getOrDefault [_uid, []];
            private _al = [_x] call _alertsOf;
            private _key = "P" + _uid;
            private _isOpen = _open isEqualTo _key;
            // Ligne 2 : batterie colorée, liaison Athena, puis alertes et effets Zeus en couleur.
            private _batTxt = if (!_has) then { "" } else {
                private _bn = [-1, _bat] select (_bat isEqualType 0);
                format ["<t color='%1'>%2 %%</t>", switch (true) do { case (_bn < 0): { "#c9d4cf" }; case (_bn <= 15): { "#e5483a" }; case (_bn <= 30): { "#f2ab33" }; default { "#c9d4cf" }; }, _bat]
            };
            private _bits = [_batTxt, [_aTitle] call _esc] select { _x isNotEqualTo "" };
            { _bits pushBack format ["<t color='%1'>%2</t>", _x select 1, _x select 0]; } forEach _al;
            if (_fxKeys isNotEqualTo []) then { _bits pushBack format ["<t color='#f2ab33'>ZEUS : %1</t>", toUpper (_fxKeys joinString ", ")]; };
            _rows pushBack ["person", "", format ["<t font='COMSPEC_BarlowBold'>%1</t>  <t size='0.8' color='#8a9a93'>%2</t><br/><t size='0.8'>%3</t>", [_cs] call _esc, [_name] call _esc, _bits joinString "<t color='#8a9a93'> · </t>"],
                [[_key] call _manage, [_pos] call _mapBtn], [_sideRgb getOrDefault [_side, [0.79, 0.83, 0.81, 1]], [1, 0.3, 0.25, 1]] select ((count _al) > 0 && {((_al select 0) select 1) isEqualTo "#e5483a"}), [_cs] call _ini];
            if (!_isOpen) then { continue };
            _rows pushBack ["info", "Groupe", format ["%1 · %2", _grp, _side]];
            _rows pushBack ["info", "ATAK", [format ["porté · batterie %1 %% · appareil %2 · balise BFT %3", _bat, _dev, ["coupée", "active"] select _beacon], "aucun téléphone porté"] select !_has];
            _rows pushBack ["info", "Athena", format ["%1 (%2)", _aTitle, _aKey]];
            _rows pushBack ["info", "Versions", format ["mod %1 · Link %2", [_mod, "?"] select (_mod isEqualTo ""), [_link, "absent"] select (_link isEqualTo "")]];
            _rows pushBack ["info", "Identité", format ["n° %1 · IMEI %2", [_num, "—"] select (_num isEqualTo ""), [_imei, "—"] select (_imei isEqualTo "")]];
            if (_pos isEqualType [] && {(count _pos) >= 2}) then { _rows pushBack ["info", "Position", format ["%1 · à %2 m", [_pos, 8] call comspec_atak_native_fnc_gridRef, round (player distance2D _pos)]]; };
            if (_ath isNotEqualTo []) then {
                _rows pushBack ["info", "Fiche Athena", format ["%1 · compte %2 · vu %3", _ath select 1, _ath param [4, "?"], [parseNumber (_ath param [10, "-1"])] call _ago]];
            } else {
                _rows pushBack ["info", "Fiche Athena", [["aucune fiche pour ce Steam dans la page chargée", "parc Athena non chargé"] select (_fAt < 0), "réservé à la gestion (site)"] select !_site];
            };
            // Effets Zeus : un interrupteur par effet (état lisible d'un coup d'œil, un appui pour basculer).
            _rows pushBack ["section", "Contrôle du téléphone", "Effets Zeus sur l'ATAK de ce joueur"];
            {
                _x params ["_lbl", "_k", "_on", "_off", "_help"];
                private _act = _k in _fxKeys;
                _rows pushBack ["switch", _lbl, _act, compile format ["['zeus', [%1, %2, %3]] call comspec_atak_native_fnc_adminClient;", str _uid, str _k, [_on, _off] select _act], _help];
            } forEach [
                ["Verrouillé", "lock", str "Téléphone verrouillé par l'encadrement", str "", "Écran verrouillé avec un message de l'encadrement"],
                ["Muet", "mute", "true", "false", "Haut-parleurs coupés"],
                ["Brouillé", "jam", "70", "0", "Brouillage fort du réseau"],
                ["Éteint", "off", "true", "false", "ATAK désactivé"]
            ];
            _rows pushBack ["buttons", [
                ["RÉPARER", compile format ["['zeus', [%1, 'once', ['repair', []]]] call comspec_atak_native_fnc_adminClient;", str _uid], false, _has, "Répare son téléphone (écran, composants)"],
                ["TOUT RÉTABLIR", compile format ["['zeus', [%1, 'reset']] call comspec_atak_native_fnc_adminClient;", str _uid], false, _fxKeys isNotEqualTo [], "Retire tous les effets Zeus et répare"],
                ["FICHE ATHENA", compile format ["(uiNamespace getVariable 'COMSPEC_ATAK_State') set ['admTab', 'ATHENA']; (uiNamespace getVariable 'COMSPEC_ATAK_State') set ['admOpen', %1]; ['fleet', [%2, 0]] call comspec_atak_native_fnc_adminClient; %3", str ("A" + _uid), str _uid, _re], false, _site, "Ouvre sa fiche dans le parc Athena (recherche par Steam)"]
            ]];
            _rows pushBack ["gap"];
        } forEach _shown;
    };
    case "ATHENA": {
        if (([] call comspec_atak_native_fnc_athenaMode) isEqualTo "off") exitWith {
            _rows pushBack ["text", "<t color='#8a9a93'>Service Athena coupé sur ce téléphone : le parc de la communauté n'est pas disponible.</t>"];
        };
        if (!_site) exitWith {
            _rows pushBack ["text", format ["<t color='#8a9a93'>%1</t>", [
                "Réservé à la gestion de la communauté et à l'administration du site. Vos droits sont lus sur Athena avec votre compte relié en jeu.",
                "Lecture des droits sur Athena…"] select (uiNamespace getVariable ["COMSPEC_ATAK_AdminFleetBusy", false])]];
            if (_fErr isNotEqualTo "") then { _rows pushBack ["text", format ["<t color='#f2ab33'>Athena : %1</t>", [_fErr] call _esc]]; };
            _rows pushBack ["buttons", [["RÉESSAYER", { ["fleet", ["", 0]] call comspec_atak_native_fnc_adminClient; }, true, true]]];
        };
        _rows pushBack ["section", format ["Parc ATAK de la communauté : %1", _fTotal], format ["Mêmes règles que Gestion > Parc ATAK sur le site · %1 · chargé %2",
            ["gestion de la communauté", "administration de la plateforme"] select (_role isEqualTo "platform"), [diag_tickTime - _fAt] call _ago]];
        _rows pushBack ["field", "admQ", "Rechercher", _fQ, "CHERCHER", { ["fleet", [trim (["admQ"] call comspec_atak_native_fnc_formValue), 0]] call comspec_atak_native_fnc_adminClient; }, true];
        if (_fErr isNotEqualTo "") then { _rows pushBack ["text", format ["<t color='#f2ab33'>Athena : %1</t>", [_fErr] call _esc]]; };
        if (uiNamespace getVariable ["COMSPEC_ATAK_AdminFleetBusy", false]) then { _rows pushBack ["text", "<t color='#8a9a93'>Chargement…</t>"]; };
        if (_fRows isEqualTo [] && {_fErr isEqualTo ""}) then { _rows pushBack ["text", "<t color='#8a9a93'>Aucun ATAK.</t>"]; };
        private _online = createHashMapFromArray (_players apply { [_x select 0, _x select 2] });
        {
            _x params ["_id", "_label", ["_steam", ""], ["_term", ""], ["_acc", ""], ["_comp", "none"], ["_bid", "0"], ["_bscope", ""], ["_breason", ""], ["_pend", "0"], ["_seen", "-1"], ["_play", "0"], ["_mod", ""], ["_lnk", ""]];
            private _blocked = (parseNumber _bid) > 0;
            private _compd = _comp in ["captured", "compromised"];
            private _flags = [];
            if (_steam in _online) then { _flags pushBack "<t color='#5cc76b'>EN JEU</t>"; };
            if (_compd) then { _flags pushBack format ["<t color='#e5483a'>%1</t>", ["CAPTURÉ", "COMPROMIS"] select (_comp isEqualTo "compromised")]; };
            if (_blocked) then { _flags pushBack format ["<t color='#e5483a'>BLOQUÉ%1</t>", ["", " (PLATEFORME)"] select (_bscope isEqualTo "all")]; };
            if (_acc isNotEqualTo "ok") then { _flags pushBack format ["<t color='#f2ab33'>%1</t>", ["COMPTE DÉSACTIVÉ", "SANS COMPTE"] select (_acc isEqualTo "none")]; };
            if ((parseNumber _pend) > 0) then { _flags pushBack format ["%1 message(s) en attente", _pend]; };
            private _key = "T" + _id;
            private _isOpen = _open in [_key, "A" + _steam];
            // Pastille : rouge si bloqué ou compromis, vert si en jeu, gris sinon.
            private _tint = switch (true) do { case (_blocked || _compd): { [0.90, 0.28, 0.23, 1] }; case (_steam in _online): { [0.36, 0.78, 0.42, 1] }; default { [0.55, 0.61, 0.58, 1] }; };
            private _mb = [_key] call _manage;
            if (_isOpen) then { _mb = ["FERMER", ["admOpen", ""] call _setS, false, true, "Replier la fiche"]; };
            _rows pushBack ["person", "", format ["<t font='COMSPEC_BarlowBold'>%1</t>  <t size='0.8' color='#8a9a93'>vu %2</t><br/><t size='0.8'>%3</t>", [_label] call _esc, [parseNumber _seen] call _ago,
                [_flags joinString "<t color='#8a9a93'> · </t>", "<t color='#8a9a93'>aucun signalement</t>"] select (_flags isEqualTo [])], [_mb], _tint, [_label] call _ini];
            if (!_isOpen) then { continue };
            _rows pushBack ["info", "Steam", [_steam, "—"] select (_steam isEqualTo "")];
            _rows pushBack ["info", "Terminal", [_term, "—"] select (_term isEqualTo "")];
            _rows pushBack ["info", "Versions", format ["mod %1 · Link %2", [_mod, "?"] select (_mod isEqualTo ""), [_lnk, "?"] select (_lnk isEqualTo "")]];
            _rows pushBack ["info", "Temps de jeu", format ["%1 h", _play]];
            if (_blocked) then { _rows pushBack ["info", "Blocage", [_breason, "sans motif"] select (_breason isEqualTo "")]; };
            private _act = { params ["_a", ["_extra", "[]"]]; format ["['site', [%1, createHashMapFromArray ([['terminal_id', %2]] + %3)]] call comspec_atak_native_fnc_adminClient;", str _a, _id, _extra] };
            _rows pushBack ["section", "Accès", "Blocage, sessions et message imposé"];
            _rows pushBack ["buttons", [
                if (_blocked) then {
                    ["LEVER LE BLOCAGE", format ["unblock:%1", _bid], format ["['site', ['unblock', createHashMapFromArray [['block_id', %1]]]] call comspec_atak_native_fnc_adminClient;", _bid],
                        "Le joueur peut se reconnecter", _bscope isNotEqualTo "all" || {_role isEqualTo "platform"}] call _confirmBtn
                } else {
                    ["BLOQUER", "block:" + _id, ["block", "[['reason', 'Bloqué en jeu par la gestion']]"] call _act, "Refuse le jeu à cet ATAK dans la communauté (sessions fermées)"] call _confirmBtn
                },
                ["DÉCONNECTER", "disc:" + _id, ["disconnect"] call _act, "Ferme ses sessions de jeu sans le bloquer"] call _confirmBtn,
                ["MESSAGE", compile format ["(uiNamespace getVariable 'COMSPEC_ATAK_State') set ['admMsgFor', %1]; %2", _id, _re], false, true, "Message imposé : s'affiche dans son ATAK jusqu'à COMPRIS"]
            ]];
            _rows pushBack ["section", "Compromission", "Téléphone aux mains de l'ennemi ou plus digne de confiance"];
            _rows pushBack ["buttons", [
                ["DÉCLARER CAPTURÉ", "cap:" + _id, ["compromise", "[['state', 'captured']]"] call _act, "L'ATAK est aux mains de l'ennemi (certificats gardés)", !_compd] call _confirmBtn,
                ["DÉCLARER COMPROMIS", "comp:" + _id, ["compromise", "[['state', 'compromised']]"] call _act, "Révoque ses certificats : il n'est plus reconnu sur le réseau", _comp isNotEqualTo "compromised"] call _confirmBtn,
                ["LEVER LA COMPROMISSION", "undo:" + _id, ["undo_compromise"] call _act, "L'ATAK repasse sous contrôle, certificats rétablis", _compd] call _confirmBtn
            ]];
            _rows pushBack ["gap"];
            if ((_s getOrDefault ["admMsgFor", 0]) isEqualTo (parseNumber _id)) then {
                _rows pushBack ["edit", "admMsgTitle", "Titre", ""];
                _rows pushBack ["memo", "admMsgBody", "Texte", "", 3];
                _rows pushBack ["buttons", [
                    ["ENVOYER", compile format ["['site', ['message', createHashMapFromArray [['terminal_id', %1], ['title', trim (['admMsgTitle'] call comspec_atak_native_fnc_formValue)], ['body', trim (['admMsgBody'] call comspec_atak_native_fnc_formValue)], ['level', 'warning']]]] call comspec_atak_native_fnc_adminClient; (uiNamespace getVariable 'COMSPEC_ATAK_State') set ['admMsgFor', 0]; %2", _id, _re], true, true],
                    ["ANNULER", ["admMsgFor", 0] call _setS, false, true]
                ]];
            };
        } forEach _fRows;
        if (_fTotal > (count _fRows)) then {
            _rows pushBack ["buttons", [
                ["PRÉCÉDENTS", compile format ["['fleet', [%1, %2]] call comspec_atak_native_fnc_adminClient;", str _fQ, (_fOff - 40) max 0], false, _fOff > 0],
                [format ["%1-%2 / %3", _fOff + 1, _fOff + count _fRows, _fTotal], {}, false, false],
                ["SUIVANTS", compile format ["['fleet', [%1, %2]] call comspec_atak_native_fnc_adminClient;", str _fQ, _fOff + 40], false, (_fOff + 40) < _fTotal]
            ]];
        };
        _rows pushBack ["buttons", [["RECHARGER", compile format ["['fleet', [%1, %2]] call comspec_atak_native_fnc_adminClient;", str _fQ, _fOff], false, true]]];
    };
    case "DEBUG": {
        if (!_inGame) exitWith {
            _rows pushBack ["text", "<t color='#8a9a93'>Réservé au Zeus, à l'admin du serveur et aux gestionnaires désignés par le serveur.</t>"];
        };
        _cfg params [["_capOn", true], ["_radius", 100], ["_hunt", true], ["_huntR", 3000], ["_crack", 120]];
        _rows pushBack ["section", "Réglages de la partie", format ["Ramassage %1 (rayon %2 m) · traque %3 (rayon %4 m) · déchiffrage %5 s · relevé %6",
            ["coupé", "actif"] select _capOn, _radius, ["coupée", "active"] select _hunt, _huntR, _crack, [diag_tickTime - _dbgAt, "en attente"] select (_dbgAt < 0)]];
        _rows pushBack ["buttons", [
            ["ACTUALISER", { ["scan"] call comspec_atak_native_fnc_adminClient; }, true, true],
            [["RÉTABLIR LA TRAQUE", "COUPER LA TRAQUE"] select _hunt, compile format ["['server', ['hunt', [%1]]] call comspec_atak_native_fnc_adminClient;", !_hunt], false, true,
                "Traque des joueurs par l'IA qui a un téléphone, pour toute la partie"]
        ]];
        _rows pushBack ["section", format ["Aux mains de l'IA : %1", count _caps], "Téléphone ramassé, porteur, exploitation du BFT et groupes lancés à la poursuite des joueurs"];
        if (_caps isEqualTo []) then { _rows pushBack ["text", "<t color='#8a9a93'>Aucun téléphone ramassé par l'IA dans cette partie.</t>"]; };
        {
            _x params ["_id", "_cs", "_imei", "_num", "_side", "_state", "_by", "_athena", "_grid", "_hour", "_status", "_carrier", "_lastGrid", "_lastAgo", "_hunters", "_targets", "_capPos", ["_ouid", ""]];
            _carrier params [["_cType", ""], ["_cName", ""], ["_cSide", ""], ["_cGrp", ""], ["_cPos", []]];
            private _stTxt = switch (true) do {
                case (_status isEqualTo "cut"): { "<t color='#5cc76b'>coupé du réseau (validé)</t>" };
                case (_status isEqualTo "wiped"): { "<t color='#8a9a93'>effacé : rien d'exploitable</t>" };
                case (_status isEqualTo "recovered"): { "<t color='#8a9a93'>repris à l'IA</t>" };
                case (_status isEqualTo "dead"): { "<t color='#8a9a93'>porteur mort (téléphone sur le corps)</t>" };
                case ((_status select [0, 6]) isEqualTo "crack:"): { format ["<t color='#f2ab33'>déchiffrage : %1 s</t>", _status select [6]] };
                default { "<t color='#e5483a'>EXPLOITÉ : l'IA lit le BFT</t>" };
            };
            private _key = "C" + _id;
            private _tint = switch (true) do { case (_status in ["cut", "wiped", "recovered", "dead"]): { [0.55, 0.61, 0.58, 1] }; case ((_status select [0, 6]) isEqualTo "crack:"): { [0.95, 0.67, 0.2, 1] }; default { [0.90, 0.28, 0.23, 1] }; };
            _rows pushBack ["person", "", format ["<t font='COMSPEC_BarlowBold'>ATAK de %1</t>  <t size='0.8' color='#8a9a93'>%2</t><br/><t size='0.8'>%3</t><t size='0.8' color='#8a9a93'> · %4 · %5 repéré(s) · %6 groupe(s) en traque</t>",
                [_cs] call _esc, _id, _stTxt, (createHashMapFromArray [["suspected", "suspectée"], ["validated", "validée"], ["dismissed", "rejetée"]]) getOrDefault [_state, _state], _targets, count _hunters],
                [[_key] call _manage, [_cPos, "PORTEUR", "Centre la carte sur l'IA qui a le téléphone"] call _mapBtn], _tint, [_cs] call _ini];
            private _isOpen = _open isEqualTo _key;
            if (!_isOpen) then { continue };
            _rows pushBack ["info", "Téléphone", format ["%1 · n° %2 · IMEI %3", _side, _num, _imei]];
            _rows pushBack ["info", "Ramassé", format ["%1 vers %2", _hour, _grid]];
            _rows pushBack ["info", "Porteur", [format ["%1 (%2) · %3 · groupe %4 · %5", [_cName] call _esc, _cType, _cSide, [_cGrp] call _esc,
                [[_cPos, 8] call comspec_atak_native_fnc_gridRef, "—"] select (_cPos isEqualTo [])], "aucun"] select (_cType isEqualTo "")];
            _rows pushBack ["info", "Dernière connexion", [format ["%1 · %2", _lastGrid, [_lastAgo] call _ago], "jamais"] select (_lastGrid isEqualTo "")];
            _rows pushBack ["info", "Compromission", format ["%1%2 · Athena %3", (createHashMapFromArray [["suspected", "suspectée"], ["validated", "validée"], ["dismissed", "rejetée"]]) getOrDefault [_state, _state],
                ["", " par " + _by] select (_by isNotEqualTo ""), [_athena, "—"] select (_athena isEqualTo "")]];
            {
                _x params ["_gid", "_n", "_hp", "_gs"];
                _rows pushBack ["info", "Traque", format ["%1 (%2, %3 IA) » %4", [_gid] call _esc, _gs, _n, [[_hp, 6] call comspec_atak_native_fnc_gridRef, "en attente"] select (_hp isEqualTo [])]];
            } forEach _hunters;
            private _srv = { params ["_op"]; format ["['server', ['capture', [%1, %2]]] call comspec_atak_native_fnc_adminClient;", str _id, str _op] };
            _rows pushBack ["buttons", [
                [_capPos, "LIEU", "Centre la carte sur le lieu du ramassage"] call _mapBtn,
                ["VALIDER", "cv:" + _id, ["validate"] call _srv, "Coupe le téléphone du réseau et révoque son certificat sur Athena", _state isEqualTo "suspected"] call _confirmBtn,
                ["REJETER", "cd:" + _id, ["dismiss"] call _srv, "Fausse alerte : la compromission est rejetée", _state isEqualTo "suspected"] call _confirmBtn
            ]];
            _rows pushBack ["buttons", [
                ["ARRÊTER LA TRAQUE", "ch:" + _id, ["stopHunt"] call _srv, "Les groupes de cette capture arrêtent de chercher les joueurs", _hunters isNotEqualTo [] || {_status isEqualTo "exploit"}] call _confirmBtn,
                ["REPRENDRE LE TÉLÉPHONE", "cr:" + _id, ["recover"] call _srv, "Retire le téléphone à l'IA : plus d'exploitation", _cType isNotEqualTo ""] call _confirmBtn
            ]];
            _rows pushBack ["gap"];
        } forEach _caps;
        _rows pushBack ["section", format ["IA en route pour ramasser : %1", count _going], "Une IA ennemie proche d'un téléphone de joueur (corps, joueur à terre, téléphone posé) va le chercher"];
        if (_going isEqualTo []) then { _rows pushBack ["text", "<t color='#8a9a93'>Aucune.</t>"]; };
        {
            _x params ["_name", "_gSide", "_dist", "_aiPos", "_owner", "_phPos", "_since"];
            _rows pushBack ["person", "", format ["<t font='COMSPEC_BarlowBold'>%1</t>  <t size='0.8' color='#8a9a93'>%2</t><br/><t size='0.8'>vers l'ATAK de %3 · à %4 m · depuis %5 s · %6</t>",
                [_name] call _esc, _gSide, [_owner] call _esc, _dist, _since, [_phPos, 8] call comspec_atak_native_fnc_gridRef], [[_aiPos] call _mapBtn], [0.95, 0.67, 0.2, 1], [_name] call _ini];
        } forEach _going;
    };
};

[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
