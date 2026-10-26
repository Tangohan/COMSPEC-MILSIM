/*
    Connexion Athena depuis l'éditeur Eden (menu COMSPEC SSE, bouton du tableau de bord SSE) et temps passé dans Eden.

    Même liaison que le jeu, par la DLL COMSPEC Link : session Athena mémorisée (RestoreSession), sinon un code court
    à valider sur athena-comspec.eu/lier, connecté à son compte (PairStart / PairStatus, comme l'app Athena du téléphone
    ATAK). Aucune clé ni aucun jeton ne passe par le SQF.

    Temps Eden : compté tant que l'éditeur est ouvert, au premier plan et utilisé (souris ou clavier dans les
    5 dernières minutes), aperçu de mission exclu. Gardé pour la session de jeu tant que le compte n'est pas relié,
    envoyé toutes les 5 minutes une fois relié, au lancement d'un aperçu et à la fermeture de l'éditeur
    (ReportPlaytime, contexte « editor ») : le site l'ajoute aux heures Eden de la fiche personnel et du tableau des heures.

    [mode, args] call comspec_sse_fnc_athenaEden
      "open"     [parent]  fenêtre Connexion Athena (état du compte, code, ouverture de la page /lier)
      "track"              démarre le compteur (événements Eden de Cfg3DEN, ouverture du menu)
      "flush"              envoie le temps Eden gardé si le compte est relié (sans appel réseau sinon)
      "check" / "pair" / "openPage" / "render" / "loop" : étapes internes
    État de la fenêtre : uiNamespace « comspec_sse_athenaEden » (HashMap) ; temps : « comspec_sse_edenPending »
    (à envoyer), « comspec_sse_edenSession » (compté depuis le lancement du jeu), « comspec_sse_edenSent » (envoyé).
*/
params [["_mode", "open", [""]], ["_args", [], [[]]]];
if (!hasInterface) exitWith {};
disableSerialization;

private _url = "https://athena-comspec.eu/public";
private _call = {
    params ["_cmd", ["_a", []]];
    private _r = "COMSPECExtension" callExtension [_cmd, _a];
    if (_r isEqualType []) then { _r = _r param [0, ""]; };
    if !(_r isEqualType "") then { _r = str _r; };
    _r
};
// Steam du joueur : relevé en mission (XEH_postInit), sinon celui d'Overwatch ; vide dans Eden sinon (la DLL
// reprend alors le Steam associé au compte Athena).
private _steam = {
    private _s = "";
    {
        private _v = profileNamespace getVariable [_x, ""];
        if (_v isEqualType "") then { _v = ((trim _v) splitString """'") joinString ""; };
        if (_s isEqualTo "" && {_v isEqualType ""} && {_v regexMatch "^[0-9]{17}$"}) then { _s = _v; };
    } forEach ["comspec_sse_steam_uid", "comspec_overwatch_saved_steam_uid"];
    _s
};
// [relié, nom, indicatif, communauté, DLL présente] ; avec « true », reprend la session mémorisée (appel réseau).
private _status = {
    params [["_restore", false, [true]]];
    private _read = { [["GetAuthState"] call _call, toString [9]] call CBA_fnc_split };
    private _raw = ["GetAuthState"] call _call;
    if (_raw isEqualTo "") exitWith { [false, "", "", "", false] };
    private _cells = [_raw, toString [9]] call CBA_fnc_split;
    if (_restore && {(_cells param [1, ""]) isNotEqualTo "READY"}) then {
        ["RestoreSession", [_url, "", call _steam]] call _call;
        _cells = call _read;
    };
    private _cell = { private _v = trim (_cells param [_this, ""]); ["", _v] select (_v isNotEqualTo "-") };
    private _ok = (_cells param [1, ""]) isEqualTo "READY" || {uiNamespace getVariable ["comspec_sse_athenaPaired", false]};
    [_ok, 5 call _cell, 6 call _cell, 7 call _cell, true]
};
private _esc = { params ["_s"]; ((_s regexReplace ["&", "&amp;"]) regexReplace ["<", "&lt;"]) regexReplace [">", "&gt;"] };
private _st = uiNamespace getVariable ["comspec_sse_athenaEden", createHashMap];
uiNamespace setVariable ["comspec_sse_athenaEden", _st];
private _dlg = uiNamespace getVariable ["comspec_sse_athenaEdenDisplay", displayNull];

switch (_mode) do {
    case "open": {
        if (!is3DEN && {isNull findDisplay 313}) exitWith { hintSilent (("Connexion Athena : disponible dans l'éditeur Eden (menu COMSPEC SSE).") call comspec_sse_fnc_tr); };
        // Action d'un menu d'Eden : la liste déroulante est encore ouverte, la fenêtre est créée juste après.
        if (!canSuspend) exitWith { [_mode, _args] spawn { uiSleep 0.05; _this call comspec_sse_fnc_athenaEden; }; };
        ["track"] call comspec_sse_fnc_athenaEden;
        if (!isNull _dlg) then { _dlg closeDisplay 2; };
        private _parent = _args param [0, displayNull];
        if (isNull _parent) then { _parent = findDisplay 313; };
        if (isNull _parent) exitWith {};
        private _d = _parent createDisplay "RscDisplayEmpty";
        if (isNull _d) exitWith {};
        uiNamespace setVariable ["comspec_sse_athenaEdenDisplay", _d];
        private _w = safeZoneW * 0.42;
        private _h = safeZoneH * 0.40;
        private _u = safeZoneH * 0.006;
        private _x0 = safeZoneX + (safeZoneW - _w) / 2;
        private _y0 = safeZoneY + (safeZoneH - _h) / 2;
        private _mk = {
            params ["_cls", "_pos"];
            private _c = _d ctrlCreate [_cls, -1];
            _c ctrlSetPosition _pos;
            _c ctrlCommit 0;
            _c
        };
        private _dim = ["ctrlStatic", [safeZoneX, safeZoneY, safeZoneW, safeZoneH]] call _mk;
        _dim ctrlSetBackgroundColor [0, 0, 0, 0.4];
        private _bg = ["ctrlStatic", [_x0, _y0, _w, _h]] call _mk;
        _bg ctrlSetBackgroundColor [0.12, 0.13, 0.12, 0.97];
        private _title = ["ctrlStatic", [_x0, _y0 - 5 * _u, _w, 5 * _u]] call _mk;
        _title ctrlSetBackgroundColor [0.10, 0.30, 0.16, 1];
        _title ctrlSetTextColor [1, 1, 1, 1];
        _title ctrlSetText ("  " + (("Connexion Athena") call comspec_sse_fnc_tr));
        private _body = ["ctrlStructuredText", [_x0 + 2 * _u, _y0 + 2 * _u, _w - 4 * _u, _h - 11 * _u]] call _mk;
        _d setVariable ["comspec_sse_body", _body];
        private _bw = (_w - 5 * _u) / 3;
        private _by = _y0 + _h - 7 * _u;
        private _bCode = ["ctrlButton", [_x0 + _u, _by, _bw, 5 * _u]] call _mk;
        _bCode ctrlSetText (("Obtenir un code") call comspec_sse_fnc_tr);
        _bCode ctrlSetTooltip (("Demande un code de connexion à saisir sur la page /lier d'Athena, connecté à votre compte.") call comspec_sse_fnc_tr);
        _bCode ctrlAddEventHandler ["ButtonClick", { ["pair"] spawn comspec_sse_fnc_athenaEden; }];
        _d setVariable ["comspec_sse_bCode", _bCode];
        private _bOpen = ["ctrlButton", [_x0 + 2 * _u + _bw, _by, _bw, 5 * _u]] call _mk;
        _bOpen ctrlSetText (("Ouvrir la page") call comspec_sse_fnc_tr);
        _bOpen ctrlSetTooltip (("Ouvre la page de connexion d'Athena dans le navigateur, avec le code déjà rempli.") call comspec_sse_fnc_tr);
        _bOpen ctrlAddEventHandler ["ButtonClick", { ["openPage"] call comspec_sse_fnc_athenaEden; }];
        _d setVariable ["comspec_sse_bOpen", _bOpen];
        private _bClose = ["ctrlButton", [_x0 + 3 * _u + 2 * _bw, _by, _bw, 5 * _u]] call _mk;
        _bClose ctrlSetText (("Fermer") call comspec_sse_fnc_tr);
        _bClose ctrlAddEventHandler ["ButtonClick", { (ctrlParent (_this select 0)) closeDisplay 2; }];
        _d displayAddEventHandler ["Unload", {
            // Fenêtre fermée : l'attente du code s'arrête (le code reste valable sur le site jusqu'à son échéance).
            uiNamespace setVariable ["comspec_sse_athenaEdenToken", -1];
        }];
        ["check"] spawn comspec_sse_fnc_athenaEden;
    };

    case "check": {
        _st set ["phase", "check"];
        ["render"] call comspec_sse_fnc_athenaEden;
        uiSleep 0.05;
        private _s = [true] call _status;
        _s params ["_ok", "_name", "_callsign", "_tenant", "_dll"];
        _st set ["name", _name];
        _st set ["callsign", _callsign];
        _st set ["tenant", _tenant];
        if (!_dll) exitWith { _st set ["phase", "nodll"]; ["render"] call comspec_sse_fnc_athenaEden; };
        if (_ok) exitWith {
            _st set ["phase", "ok"];
            ["render"] call comspec_sse_fnc_athenaEden;
            ["flush"] call comspec_sse_fnc_athenaEden;
        };
        // Pas de session : un code tout de suite, comme l'app Athena du téléphone.
        ["pair"] call comspec_sse_fnc_athenaEden;
    };

    case "pair": {
        if (!canSuspend) exitWith { ["pair"] spawn comspec_sse_fnc_athenaEden; };
        private _token = diag_tickTime;
        uiNamespace setVariable ["comspec_sse_athenaEdenToken", _token];
        private _mine = { (uiNamespace getVariable ["comspec_sse_athenaEdenToken", -1]) isEqualTo _token };
        _st set ["phase", "asking"];
        ["render"] call comspec_sse_fnc_athenaEden;
        uiSleep 0.05;
        // OK|code|durée s|adresse|intervalle s|adresse avec le code (6e champ : COMSPEC Link 2.0.72+)
        private _r = ["PairStart", [_url, call _steam, "sse-eden"]] call _call;
        if !(call _mine) exitWith {};
        if (_r isEqualTo "") exitWith { _st set ["phase", "nodll"]; ["render"] call comspec_sse_fnc_athenaEden; };
        private _cells = _r splitString "|";
        if ((_cells param [0, ""]) isNotEqualTo "OK") exitWith {
            _st set ["phase", "error"];
            _st set ["detail", _cells param [1, "sans réponse"]];
            ["render"] call comspec_sse_fnc_athenaEden;
        };
        private _code = _cells param [1, ""];
        private _ttl = (parseNumber (_cells param [2, "600"])) max 30;
        private _uri = _cells param [3, ""];
        if (((toLower _uri) find "http") isNotEqualTo 0) then { _uri = _url + "/lier"; };
        private _full = _cells param [5, ""];
        if (((toLower _full) find "http") isNotEqualTo 0) then { _full = format ["%1?code=%2", _uri, _code]; };
        private _every = ((parseNumber (_cells param [4, "2"])) max 2) min 15;
        private _until = diag_tickTime + _ttl;
        _st set ["phase", "code"];
        _st set ["code", _code];
        _st set ["uri", _uri];
        _st set ["full", _full];
        _st set ["until", _until];
        ["render"] call comspec_sse_fnc_athenaEden;
        private _result = "";
        while { (call _mine) && {_result isEqualTo ""} && {diag_tickTime < _until} } do {
            uiSleep _every;
            if !(call _mine) exitWith {};
            private _p = ["PairStatus"] call _call;
            switch (true) do {
                case (_p isEqualTo "OK|approved"): { _result = "approved"; };
                case ((_p find "ERR|expired") isEqualTo 0 || {(_p find "ERR|no_pairing") isEqualTo 0}): { _result = "expired"; };
                case ((_p find "ERR|http_4") isEqualTo 0): { _result = "error"; _st set ["detail", _p select [4]]; };
                default { ["render"] call comspec_sse_fnc_athenaEden; };
            };
        };
        if !(call _mine) exitWith {};
        if (_result isEqualTo "") then { _result = "expired"; };
        if (_result isNotEqualTo "approved") exitWith { _st set ["phase", _result]; ["render"] call comspec_sse_fnc_athenaEden; };
        uiNamespace setVariable ["comspec_sse_athenaPaired", true];
        ([false] call _status) params ["", "_name", "_callsign", "_tenant"];
        _st set ["name", _name];
        _st set ["callsign", _callsign];
        _st set ["tenant", _tenant];
        _st set ["phase", "ok"];
        ["render"] call comspec_sse_fnc_athenaEden;
        ["flush"] call comspec_sse_fnc_athenaEden;
        // Tableau de bord SSE ouvert : il relit le site avec la nouvelle session.
        private _dash = uiNamespace getVariable ["comspec_sse_dashDisplay", displayNull];
        if (!isNull _dash) then { ["refresh", [], _dash] spawn comspec_sse_fnc_dashSite; };
    };

    case "openPage": {
        if ((_st getOrDefault ["phase", ""]) isNotEqualTo "code") exitWith { ["pair"] spawn comspec_sse_fnc_athenaEden; };
        private _r = ["OpenAthenaLink", [_st getOrDefault ["full", ""]]] call _call;
        // DLL plus ancienne (commande inconnue) : le joueur tape l'adresse lui-même, déjà affichée.
        if ((_r select [0, 2]) isNotEqualTo "OK") then { _st set ["noBrowser", true]; ["render"] call comspec_sse_fnc_athenaEden; };
    };

    case "render": {
        if (isNull _dlg) exitWith {};
        private _phase = _st getOrDefault ["phase", "check"];
        private _tr = { (_this) call comspec_sse_fnc_tr };
        private _lines = [];
        switch (_phase) do {
            case "check": { _lines pushBack format ["<t color='#8a96a3'>%1</t>", "Vérification de la liaison avec Athena…" call _tr]; };
            case "asking": { _lines pushBack format ["<t color='#8a96a3'>%1</t>", "Demande d'un code de connexion…" call _tr]; };
            case "nodll": {
                _lines pushBack format ["<t color='#e06060'>%1</t>", "La DLL COMSPEC Link ne répond pas : chargez le mod COMSPEC Link (autorisé par BattlEye), puis relancez Arma." call _tr];
            };
            case "ok": {
                _lines pushBack format ["<t size='1.2' color='#7fd28f'>%1</t>", "Compte Athena relié" call _tr];
                private _who = [_st getOrDefault ["name", ""], _st getOrDefault ["callsign", ""]] select { _x isNotEqualTo "" };
                if (_who isNotEqualTo []) then { _lines pushBack format ["%1 : %2", "Compte" call _tr, [_who joinString " · "] call _esc]; };
                if ((_st getOrDefault ["tenant", ""]) isNotEqualTo "") then { _lines pushBack format ["%1 : %2", "Communauté" call _tr, [_st get "tenant"] call _esc]; };
                _lines pushBack ("Le tableau de bord SSE lit maintenant les campagnes et les avis de recherche de votre communauté." call _tr);
            };
            case "code": {
                private _short = ((_st getOrDefault ["uri", ""]) regexReplace ["^https?://", ""]) regexReplace ["/public/", "/"];
                private _left = ceil (((_st getOrDefault ["until", 0]) - diag_tickTime) / 60) max 1;
                _lines pushBack (format ["Sur %1, connecté à votre compte Athena, saisissez ce code :" call _tr, format ["<t color='#9fd3ff'>%1</t>", [_short] call _esc]]);
                _lines pushBack format ["<t size='2.4' align='center' font='EtelkaMonospacePro'>%1</t>", [_st getOrDefault ["code", ""]] call _esc];
                _lines pushBack format ["<t color='#8a96a3'>%1</t>", format ["« Ouvrir la page » l'ouvre dans le navigateur avec le code déjà rempli. Code valable %1 min ; la fenêtre se met à jour dès qu'il est validé." call _tr, _left]];
                if (_st getOrDefault ["noBrowser", false]) then { _lines pushBack format ["<t color='#e0b050'>%1</t>", "Votre version de COMSPEC Link ne sait pas ouvrir le navigateur : tapez l'adresse vous-même." call _tr]; };
            };
            case "expired": { _lines pushBack format ["<t color='#e0b050'>%1</t>", "Code expiré : cliquez sur « Obtenir un code »." call _tr]; };
            case "error": { _lines pushBack format ["<t color='#e06060'>%1</t>", format ["Code de connexion impossible : %1" call _tr, [_st getOrDefault ["detail", ""]] call _esc]]; };
        };
        // Temps passé dans Eden.
        private _min = { str (floor ((_this max 0) / 60)) };
        _lines pushBack "";
        _lines pushBack format ["<t color='#c8d2cc'>%1</t>", format ["Temps dans Eden depuis le lancement du jeu : %1 min, dont %2 min envoyées à Athena." call _tr,
            (uiNamespace getVariable ["comspec_sse_edenSession", 0]) call _min, (uiNamespace getVariable ["comspec_sse_edenSent", 0]) call _min]];
        if (_phase isNotEqualTo "ok") then {
            _lines pushBack format ["<t color='#8a96a3'>%1</t>", "Le temps passé dans Eden est compté et part sur votre fiche Athena dès que le compte est relié." call _tr];
        };
        (_dlg getVariable ["comspec_sse_body", controlNull]) ctrlSetStructuredText parseText (_lines joinString "<br/>");
        (_dlg getVariable ["comspec_sse_bCode", controlNull]) ctrlSetText ((["Obtenir un code", "Nouveau code"] select (_phase in ["code", "expired"])) call _tr);
        (_dlg getVariable ["comspec_sse_bOpen", controlNull]) ctrlEnable (_phase isEqualTo "code");
    };

    case "track": {
        private _d3 = findDisplay 313;
        if (isNull _d3) exitWith {};
        // Activité dans l'éditeur : souris, clavier, molette.
        if !(_d3 getVariable ["comspec_sse_edenInput", false]) then {
            _d3 setVariable ["comspec_sse_edenInput", true];
            {
                _d3 displayAddEventHandler [_x, { uiNamespace setVariable ["comspec_sse_edenLastInput", diag_tickTime]; false }];
            } forEach ["KeyDown", "MouseButtonDown", "MouseZChanged", "MouseMoving"];
        };
        uiNamespace setVariable ["comspec_sse_edenLastInput", diag_tickTime];
        // Une seule boucle : son battement date de moins de 20 s tant qu'elle tourne (un aperçu de mission peut l'arrêter).
        if (diag_tickTime - (uiNamespace getVariable ["comspec_sse_edenBeat", -1000]) < 20) exitWith {};
        uiNamespace setVariable ["comspec_sse_edenBeat", diag_tickTime];
        ["loop"] spawn comspec_sse_fnc_athenaEden;
    };

    case "loop": {
        private _last = diag_tickTime;
        private _lastSend = diag_tickTime;
        while { !isNull findDisplay 313 } do {
            uiNamespace setVariable ["comspec_sse_edenBeat", diag_tickTime];
            uiSleep 5;
            private _now = diag_tickTime;
            private _dt = (_now - _last) min 30;
            _last = _now;
            if (is3DEN && {!is3DENPreview} && {isGameFocused} && {_now - (uiNamespace getVariable ["comspec_sse_edenLastInput", 0]) < 300}) then {
                uiNamespace setVariable ["comspec_sse_edenPending", ((uiNamespace getVariable ["comspec_sse_edenPending", 0]) + _dt) min 43200];
                uiNamespace setVariable ["comspec_sse_edenSession", (uiNamespace getVariable ["comspec_sse_edenSession", 0]) + _dt];
            };
            if (_now - _lastSend >= 300) then {
                _lastSend = _now;
                ["flush"] call comspec_sse_fnc_athenaEden;
                ["render"] call comspec_sse_fnc_athenaEden;
            };
        };
        uiNamespace setVariable ["comspec_sse_edenBeat", -1000];
        ["flush"] call comspec_sse_fnc_athenaEden;
    };

    case "flush": {
        private _secs = floor (uiNamespace getVariable ["comspec_sse_edenPending", 0]);
        if (_secs < 60) exitWith {};
        // Session pas encore reprise depuis le lancement du jeu : une seule tentative (appel réseau, Eden attend).
        private _s = [false] call _status;
        if (!(_s select 0) && {_s select 4} && {!(uiNamespace getVariable ["comspec_sse_edenRestoreTried", false])}) then {
            uiNamespace setVariable ["comspec_sse_edenRestoreTried", true];
            _s = [true] call _status;
        };
        if !(_s select 0) exitWith {};
        // Le site plafonne un envoi à 2 h.
        private _sent = 0;
        while { _secs - _sent >= 1 } do {
            private _n = (_secs - _sent) min 7200;
            ["ReportPlaytime", [call _steam, str _n, profileName, "", "editor"]] call _call;
            _sent = _sent + _n;
        };
        uiNamespace setVariable ["comspec_sse_edenPending", ((uiNamespace getVariable ["comspec_sse_edenPending", 0]) - _sent) max 0];
        uiNamespace setVariable ["comspec_sse_edenSent", (uiNamespace getVariable ["comspec_sse_edenSent", 0]) + _sent];
        diag_log format ["[COMSPEC SSE][INFO][EDEN] Temps Eden envoyé à Athena : %1 s", _sent];
    };
};
