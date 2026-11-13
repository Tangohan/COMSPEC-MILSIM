/*
    Traçage d'un numéro, côté téléphone : app GE, onglet TRAÇAGE (fn_pageEw), cercles sur la carte (fn_ewDraw) et
    boutons « TRACER » des autres apps. Le téléphone ne calcule jamais une position : il envoie la requête au serveur
    (fn_traceServer) et n'affiche que la réponse. Le traçage donne une position, jamais une identité : l'étiquette d'une
    ligne est ce que le joueur sait déjà (ce qu'il a saisi, ou la ligne du fichier où il a lu le numéro).
    Params : [action, argument]
      "init"        client (XEH_postInitClient) : réponses du serveur, menu Zeus
      "may" [unité] droit de tracer (serveur et client). Réglage « Réservé aux opérateurs GE » : opérateur GE
                    (COMSPEC_ATAK_EwOperator), renseignement (COMSPEC_ATAK_IntelOperator) ou rôle de mission (guerre
                    électronique, brouilleur, SIGINT, renseignement, S2, G2) ; réglage « Mandat du Zeus » : mandat
                    accordé (COMSPEC_ATAK_TraceWarrant). Un Zeus passe toujours.
      "needLink"    liaison avec l'opérateur exigée ? Réglage « Liaison ATAK exigée » : selon le profil (Milsim, Standard
                    et Personnalisé oui, Arcade non), toujours, jamais
      "allowed"     [autorisé, raison] pour l'interface
      "submit"      lit les champs de l'onglet (numéro, qui) et lance "request"
      "request" [numéro, étiquette, "manual" | "track"]   "track" i (suivi on/off)   "del" i   "clear" (journal)
      "result" [réponse de fn_traceServer]   "tick" (toutes les 5 s, depuis fn_ewAction) : suivis, requêtes sans réponse
      "map" i   "share" i (marqueur prérempli : canal au choix, groupe ou TOC)   "toSse" i (fiche SSE FRO préremplie)
      "open" [numéro, étiquette] : app GE ouverte sur TRAÇAGE, numéro prérempli (depuis n'importe quelle app)
      "rows"        lignes de l'onglet TRAÇAGE
      "numRows" [texte ou textes, étiquette, ligne d'abord] -> boutons « TRACER … » des numéros du texte ; étiquette :
                    la ligne où le numéro est écrit (ligne d'abord, défaut), sinon celle de l'appelant
      "zen"         menu clic droit du Zeus (ZEN) : téléphone d'une IA, traçage de la mission, mandat, réseau civil
    Numéros tracés : missionNamespace COMSPEC_ATAK_GeoTracks (lu par la carte et la recherche, effacé avec le
    téléphone), HashMap q, qn, kind, label, pos, rad, t (time du relevé), at (horloge de mission), hour, status,
    why, track, hist [[pos, rad, at]...], token, sent.
    Journal de traçage : missionNamespace COMSPEC_ATAK_TraceLog [[heure, numéro, étiquette, état, grille]...], 30 au plus.
*/
params [["_act", ""], ["_arg", []]];
private _calc = comspec_atak_native_fnc_traceCalc;
private _now = [time, serverTime] select isMultiplayer;
private _list = missionNamespace getVariable ["COMSPEC_ATAK_GeoTracks", []];
private _rerender = { [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "EW") then { ["EW"] call comspec_atak_native_fnc_pageRender; }; }] call comspec_atak_native_fnc_nextFrame; };
private _say = { params ["_type", "_msg"]; [_type, _msg, 5, 40, "EW"] call comspec_atak_native_fnc_notify; };
private _statusTxt = {
    params ["_st", ["_wait", 0]];
    switch (_st) do {
        case "PENDING": { "Requête opérateur…" };
        case "OK": { "en ligne" };
        case "SOS": { "batterie vide, carte SIM en secours" };
        case "OFF": { "éteint ou hors réseau" };
        case "JAM": { "pas de signal : zone brouillée" };
        case "NOCOV": { "hors couverture" };
        case "UNKNOWN": { "Numéro inconnu du réseau" };
        case "OWN": { "numéro de votre camp : traçage non autorisé" };
        case "DISABLED": { "traçage suspendu sur cette mission" };
        case "DENIED": { "traçage réservé aux opérateurs habilités" };
        case "NOLINK": { "Pas de liaison avec l'opérateur" };
        case "WAIT": { format ["opérateur occupé : réessayez dans %1 s", _wait] };
        case "CAP": { "trop de numéros suivis en même temps" };
        case "NOREPLY": { "pas de réponse de l'opérateur" };
        default { "" };
    }
};
private _entry = { params ["_i"]; if (_i isEqualType 0 && {_i >= 0} && {_i < count _list}) then { _list select _i } else { createHashMap } };
private _ret = true;

switch (_act) do {
    case "init": {
        if (!hasInterface) exitWith {};
        ["comspec_atak_native_traceRes", { ["result", _this] call comspec_atak_native_fnc_traceAction; }] call CBA_fnc_addEventHandler;
        ["zen"] call comspec_atak_native_fnc_traceAction;
    };
    case "may": {
        private _u = if (_arg isEqualType objNull) then { _arg } else { objNull };
        if (isNull _u) exitWith { _ret = false; };
        if (!isNull (getAssignedCuratorLogic _u)) exitWith { _ret = true; };
        if ((missionNamespace getVariable ["comspec_atak_native_trace_warrant", false]) isEqualTo true
            && {!((missionNamespace getVariable ["COMSPEC_ATAK_TraceWarrant", false]) isEqualTo true)}) exitWith { _ret = false; };
        if !((missionNamespace getVariable ["comspec_atak_native_geoloc_ew_only", false]) isEqualTo true) exitWith { _ret = true; };
        if ((_u getVariable ["COMSPEC_ATAK_EwOperator", false]) isEqualTo true || {(_u getVariable ["COMSPEC_ATAK_IntelOperator", false]) isEqualTo true}) exitWith { _ret = true; };
        private _r = " " + (toLower (roleDescription _u)) + " ";
        _ret = (["guerre", "brouill", "sigint", "elint", "electronic warfare", "renseign", "intel", " s2", " g2", " ew ", " ge "] findIf { (_r find _x) >= 0 }) >= 0;
    };
    case "needLink": {
        _ret = switch (missionNamespace getVariable ["comspec_atak_native_trace_link", 0]) do {
            case 1: { true };
            case 2: { false };
            default { (["profile"] call comspec_atak_native_fnc_netCond) isNotEqualTo 1 };
        };
    };
    case "allowed": {
        _ret = call {
            if !(missionNamespace getVariable ["comspec_atak_native_geoloc_enabled", true]) exitWith { [false, "Traçage des numéros désactivé sur ce serveur."] };
            if ((missionNamespace getVariable ["COMSPEC_ATAK_TraceOff", false]) isEqualTo true) exitWith { [false, "Traçage suspendu sur cette mission."] };
            if !(["may", player] call comspec_atak_native_fnc_traceAction) exitWith {
                if ((missionNamespace getVariable ["comspec_atak_native_trace_warrant", false]) isEqualTo true && {!((missionNamespace getVariable ["COMSPEC_ATAK_TraceWarrant", false]) isEqualTo true)}) exitWith {
                    [false, "Pas de mandat de traçage : l'opérateur refuse la requête."]
                };
                [false, "Réservé aux opérateurs de guerre électronique et du renseignement sur cette mission."]
            };
            [true, ""]
        };
    };
    case "submit": {
        private _f = uiNamespace getVariable ["COMSPEC_ATAK_Ew", createHashMap];
        uiNamespace setVariable ["COMSPEC_ATAK_Ew", _f];
        private _q = ["geoq", _f getOrDefault ["geoq", ""]] call comspec_atak_native_fnc_formValue;
        private _lbl = ["geolbl", _f getOrDefault ["geolbl", ""]] call comspec_atak_native_fnc_formValue;
        _f set ["geoq", _q];
        _f set ["geolbl", _lbl];
        _ret = ["request", [_q, _lbl, "manual"]] call comspec_atak_native_fnc_traceAction;
    };
    case "request": {
        _arg params [["_q", ""], ["_label", ""], ["_mode", "manual"]];
        _ret = false;
        (["allowed"] call comspec_atak_native_fnc_traceAction) params ["_ok", "_why"];
        if !(_ok) exitWith { ["WARNING", _why] call _say; };
        _q = trim _q;
        private _qn = ["norm", [_q]] call _calc;
        if ((count _qn) < 8) exitWith { ["WARNING", "Saisissez un numéro complet (06 12 34 56 78, +964 750 123 4567), un IMEI ou une adresse MAC"] call _say; };
        if ((["needLink"] call comspec_atak_native_fnc_traceAction) && {(([] call comspec_atak_native_fnc_linkQuality) getOrDefault ["bars", 1]) <= 0}) exitWith {
            ["WARNING", "Pas de liaison avec l'opérateur : aucun réseau sur votre téléphone"] call _say;
        };
        private _i = _list findIf { ["same", [_x getOrDefault ["qn", ""], _qn]] call _calc };
        private _e = if (_i >= 0) then { _list select _i } else { createHashMapFromArray [["q", _q], ["qn", _qn], ["kind", "NUMÉRO"], ["label", ""], ["pos", []], ["rad", 0],
            ["t", -1], ["at", -1], ["hour", ""], ["status", ""], ["why", ""], ["track", false], ["hist", []], ["token", -1], ["sent", -1e9]] };
        if ((_e getOrDefault ["status", ""]) isEqualTo "PENDING" && {(time - (_e getOrDefault ["sent", -1e9])) < 60}) exitWith {};
        if (_mode isEqualTo "manual") then {
            private _next = uiNamespace getVariable ["COMSPEC_ATAK_GeoNext", 0];
            if (diag_tickTime < _next) exitWith { _mode = ""; ["WARNING", format ["Opérateur occupé : réessayez dans %1 s", ceil (_next - diag_tickTime)]] call _say; };
            uiNamespace setVariable ["COMSPEC_ATAK_GeoNext", diag_tickTime + ((missionNamespace getVariable ["comspec_atak_native_trace_cooldown", 20]) max 2)];
        };
        if (_mode isEqualTo "") exitWith {};
        if (_label isNotEqualTo "") then { _e set ["label", _label select [0, 60]]; };
        private _token = round (diag_tickTime * 1000) + floor random 1000;
        _e set ["token", _token];
        _e set ["sent", time];
        _e set ["prev", _e getOrDefault ["status", ""]];
        _e set ["status", "PENDING"];
        _e set ["why", ""];
        if (_i < 0) then {
            _list pushBack _e;
            // 8 lignes au plus : la plus ancienne non suivie part d'abord.
            while { (count _list) > 8 } do {
                private _k = _list findIf { !(_x getOrDefault ["track", false]) };
                _list deleteAt ([0, _k] select (_k >= 0));
            };
        };
        missionNamespace setVariable ["COMSPEC_ATAK_GeoTracks", _list];
        ["req", [player, _q, _mode, _token]] remoteExecCall ["comspec_atak_native_fnc_traceServer", 2];
        call _rerender;
        _ret = true;
    };
    case "result": {
        _arg params [["_token", -1], ["_q", ""], ["_qn", ""], ["_code", ""], ["_pos", []], ["_rad", 0], ["_at", -1], ["_kind", ""], ["_wait", 0]];
        private _i = _list findIf { (_x getOrDefault ["token", -2]) isEqualTo _token };
        if (_i < 0) exitWith {};
        private _e = _list select _i;
        private _prev = _e getOrDefault ["prev", ""];
        private _hour = [dayTime, "HH:MM"] call BIS_fnc_timeToString;
        _e set ["why", [_code, _wait] call _statusTxt];
        if (_kind isNotEqualTo "") then { _e set ["kind", _kind]; };
        // Refus : la ligne garde son dernier état connu ; refus définitif : le suivi s'arrête.
        if (_code in ["WAIT", "NOLINK", "CAP", "DENIED", "DISABLED"]) exitWith {
            _e set ["status", [_prev, _code] select (_prev in ["", "PENDING"])];
            if (_code in ["CAP", "DENIED", "DISABLED"]) then { _e set ["track", false]; };
            if ((_e getOrDefault ["sent", 0]) > 0 && {!(_code isEqualTo "WAIT" && {_e getOrDefault ["track", false]})}) then {
                ["WARNING", format ["Tracé %1 : %2", _e get "q", _e get "why"]] call _say;
            };
            call _rerender;
        };
        _e set ["status", _code];
        if ((count _pos) >= 2) then {
            _e set ["pos", [_pos select 0, _pos select 1, 0]];
            _e set ["rad", round _rad];
            _e set ["at", _at];
            _e set ["t", time - ((_now - _at) max 0)];
            _e set ["hour", [(dayTime - (((_now - _at) max 0) / 3600) + 24) mod 24, "HH:MM"] call BIS_fnc_timeToString];
            private _h = _e getOrDefault ["hist", []];
            if ((_h findIf { (_x select 2) isEqualTo _at }) < 0) then { _h pushBack [[_pos select 0, _pos select 1, 0], round _rad, _at]; };
            while { (count _h) > 8 } do { _h deleteAt 0; };
            _e set ["hist", _h];
        };
        if (_code in ["UNKNOWN", "OWN"]) then { _e set ["track", false]; };
        private _grid = if ((count _pos) >= 2) then { [_pos, 8] call comspec_atak_native_fnc_gridRef } else { "" };
        private _changed = _code isNotEqualTo _prev;
        switch (true) do {
            case (_code in ["OK", "SOS"]): {
                if (_changed || {!(_e getOrDefault ["track", false])}) then {
                    ["SUCCESS", format ["Tracé %1 : position à %2 m près, %3", _e get "q", round _rad, _grid]] call _say;
                    [] call comspec_atak_native_fnc_vibrate;
                };
                // Le résultat (jamais la vraie position) part au poste : cercle de probabilité sur la carte Overwatch.
                if ([] call comspec_atak_native_fnc_bridge && {!isNil "comspec_overwatch_connect_fnc_emitTelemetryEvent"}) then {
                    ["geoloc", createHashMapFromArray [["x", _pos select 0], ["y", _pos select 1], ["radius", round _rad], ["q", _e get "qn"],
                        ["label", format ["Tracé : %1", _e get "q"]], ["grid", _grid]], 1] call comspec_overwatch_connect_fnc_emitTelemetryEvent;
                };
            };
            case (_changed || {!(_e getOrDefault ["track", false])}): {
                ["WARNING", format [["Tracé %1 : %2", "Tracé %1 : %2, dernière position connue conservée"] select ((count (_e getOrDefault ["pos", []])) >= 2), _e get "q", _e get "why"]] call _say;
            };
            default {};
        };
        private _log = missionNamespace getVariable ["COMSPEC_ATAK_TraceLog", []];
        _log pushBack [_hour, _e get "q", _e getOrDefault ["label", ""], _e get "why", _grid];
        while { (count _log) > 30 } do { _log deleteAt 0; };
        missionNamespace setVariable ["COMSPEC_ATAK_TraceLog", _log];
        call _rerender;
    };
    case "tick": {
        if (_list isEqualTo []) exitWith {};
        private _every = (missionNamespace getVariable ["comspec_atak_native_geoloc_refresh", 30]) max 10;
        private _wait = ((missionNamespace getVariable ["comspec_atak_native_trace_delay", 8]) max 1) * 1.2 + 25;
        // Suivi continu tant que le téléphone est ouvert (en main ou porté) : rien ne part quand il est rangé.
        private _open = !isNull ([] call comspec_atak_native_fnc_display);
        private _changed = false;
        {
            private _e = _x;
            if ((_e getOrDefault ["status", ""]) isEqualTo "PENDING") then {
                if ((time - (_e getOrDefault ["sent", 0])) > _wait) then {
                    _e set ["status", [_e getOrDefault ["prev", ""], "NOREPLY"] select ((_e getOrDefault ["prev", ""]) in ["", "PENDING"])];
                    _e set ["why", ["NOREPLY"] call _statusTxt];
                    _changed = true;
                };
                continue;
            };
            if (_open && {_e getOrDefault ["track", false]} && {(time - (_e getOrDefault ["sent", -1e9])) >= _every}) then {
                ["request", [_e get "q", "", "track"]] call comspec_atak_native_fnc_traceAction;
            };
        } forEach +_list;
        if (_changed) then { call _rerender; };
    };
    case "track": {
        private _e = [_arg] call _entry;
        if ((count _e) isEqualTo 0) exitWith {};
        if (_e getOrDefault ["track", false]) exitWith { _e set ["track", false]; call _rerender; };
        private _max = (missionNamespace getVariable ["comspec_atak_native_trace_max", 3]) max 1;
        if (({ _x getOrDefault ["track", false] } count _list) >= _max) exitWith {
            ["WARNING", format ["Suivi limité à %1 numéro(s) en même temps : arrêtez un autre suivi", _max]] call _say;
        };
        _e set ["track", true];
        _e set ["sent", -1e9];
        ["request", [_e get "q", "", "track"]] call comspec_atak_native_fnc_traceAction;
        call _rerender;
    };
    case "del": {
        if (_arg isEqualType 0 && {_arg >= 0} && {_arg < count _list}) then { _list deleteAt _arg; };
        call _rerender;
    };
    case "clear": {
        missionNamespace setVariable ["COMSPEC_ATAK_TraceLog", []];
        call _rerender;
    };
    case "map": {
        private _p = ([_arg] call _entry) getOrDefault ["pos", []];
        if ((count _p) < 2) exitWith {};
        ["MAP"] call comspec_atak_native_fnc_navigate;
        [{ [_this, 0.06] call comspec_atak_native_fnc_mapCenter; }, [_p select 0, _p select 1]] call comspec_atak_native_fnc_nextFrame;
    };
    case "share": {
        private _e = [_arg] call _entry;
        private _p = _e getOrDefault ["pos", []];
        if ((count _p) < 2) exitWith {};
        // Éditeur de marqueur prérempli : le joueur choisit le canal (groupe, camp, TOC) comme pour tout marqueur.
        ["MAP"] call comspec_atak_native_fnc_navigate;
        [[_p select 0, _p select 1, 0]] call comspec_atak_native_fnc_mapCenter;
        ["", [_p select 0, _p select 1, 0]] call comspec_atak_native_fnc_markerEditOpen;
        private _ed = (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["markerEdit", createHashMap];
        _ed set ["text", (format ["Tracé : %1", _e get "q"]) select [0, 40]];
        _ed set ["desc", format ["Position approchée à %1 m près, relevée à %2 en %3%4", _e get "rad", _e get "hour", [_p, 8] call comspec_atak_native_fnc_gridRef,
            ["", format [" (%1)", _e get "label"]] select ((_e getOrDefault ["label", ""]) isNotEqualTo "")]];
        _ed set ["color", "ColorOrange"];
    };
    case "toSse": {
        private _e = [_arg] call _entry;
        private _p = _e getOrDefault ["pos", []];
        if ((count _p) < 2) exitWith {};
        ["frs", "FRO"] call comspec_atak_native_fnc_sseAction;
        private _draft = uiNamespace getVariable ["COMSPEC_ATAK_FrsDraft", createHashMap];
        if ((count _draft) isEqualTo 0) exitWith {};
        private _nl = toString [10];
        private _line = format ["Position tracée du numéro %1%2 : %3, à %4 m près, relevée à %5 (%6).", _e get "q",
            ["", format [" (%1)", _e get "label"]] select ((_e getOrDefault ["label", ""]) isNotEqualTo ""),
            [_p, 8] call comspec_atak_native_fnc_gridRef, _e get "rad", _e get "hour", [_e get "status"] call _statusTxt];
        private _body = _draft getOrDefault ["body", ""];
        _draft set ["body", [_body + _nl + _line, _line + _nl] select ((trim _body) isEqualTo "")];
    };
    case "open": {
        _arg params [["_q", ""], ["_label", ""]];
        _ret = ["EW", createHashMapFromArray [["q", _q], ["label", _label]], {
            params ["_p"];
            (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["ewTab", "GEO"];
            private _f = uiNamespace getVariable ["COMSPEC_ATAK_Ew", createHashMap];
            uiNamespace setVariable ["COMSPEC_ATAK_Ew", _f];
            _f set ["geoq", _p getOrDefault ["q", ""]];
            _f set ["geolbl", _p getOrDefault ["label", ""]];
        }] call comspec_atak_native_fnc_openApp;
    };
    case "numRows": {
        _arg params [["_texts", ""], ["_label", ""], ["_preferLine", true]];
        if (_texts isEqualType "") then { _texts = [_texts]; };
        if !(missionNamespace getVariable ["comspec_atak_native_geoloc_enabled", true]) exitWith { _ret = []; };
        private _found = [];
        {
            private _t = _x;
            if (_t isEqualType "") then {
                {
                    private _n = _x;
                    if ((_found findIf { (_x select 0) isEqualTo _n }) < 0) then {
                        // Étiquette : la ligne du texte où le joueur a lu le numéro (ce qu'il sait déjà), sinon celle de l'appelant.
                        private _line = ((_t splitString (toString [10])) select { (_x find _n) >= 0 }) param [0, ""];
                        _line = trim ((_line splitString (toString [13])) joinString "");
                        _found pushBack [_n, [_label, _line select [0, 60]] select (_line isNotEqualTo "" && {_line isNotEqualTo _n} && {_preferLine || {_label isEqualTo ""}})];
                    };
                } forEach (["extract", [_t]] call _calc);
            };
        } forEach _texts;
        _ret = [];
        private _btns = (_found select [0, 4]) apply {
            _x params ["_n", "_l"];
            [format ["TRACER %1", _n], compile format ["['open', [%1, %2]] call comspec_atak_native_fnc_traceAction;", str _n, str _l], false, true, "Ouvre l'app GE sur l'onglet TRAÇAGE avec ce numéro"]
        };
        for "_k" from 0 to ((count _btns) - 1) step 2 do { _ret pushBack ["buttons", _btns select [_k, 2]]; };
    };
    case "rows": {
        private _muted = COMSPEC_ATAK_Th get "hexTextMuted";
        private _f = uiNamespace getVariable ["COMSPEC_ATAK_Ew", createHashMap];
        private _rows = [["section", "Traçage de numéro", "Numéro de téléphone, IMEI ou adresse MAC"]];
        (["allowed"] call comspec_atak_native_fnc_traceAction) params ["_ok", "_why"];
        if !(_ok) exitWith { _rows pushBack ["text", format ["<t color='%1'>%2</t>", COMSPEC_ATAK_Th get "hexWarn", _why]]; _ret = _rows; };
        private _next = (uiNamespace getVariable ["COMSPEC_ATAK_GeoNext", 0]) - diag_tickTime;
        private _every = round ((missionNamespace getVariable ["comspec_atak_native_geoloc_refresh", 30]) max 10);
        _rows append [
            ["text", format ["<t size='0.8' color='%1'>%2</t>", _muted, "Requête à l'opérateur : position approchée par les antennes qui captent le téléphone, avec un cercle d'incertitude. Plusieurs antennes : quelques dizaines de mètres ; une seule : plusieurs centaines. Éteint, brouillé ou hors couverture : seulement la dernière position connue, de moins en moins précise."]],
            ["edit", "geoq", "Numéro (06 12 34 56 78, +964 750 123 4567), IMEI ou MAC", _f getOrDefault ["geoq", ""]],
            ["edit", "geolbl", "Qui (facultatif : ce que vous savez déjà)", _f getOrDefault ["geolbl", ""]],
            ["buttons", [[[format ["OPÉRATEUR OCCUPÉ (%1 s)", ceil _next], "TRACER LE NUMÉRO"] select (_next <= 0), { ['submit'] call comspec_atak_native_fnc_traceAction; }, true, true, "Envoie la requête à l'opérateur : la réponse arrive après quelques secondes"]]]
        ];
        if ((count _list) > 0) then {
            _rows pushBack ["section", "Numéros tracés", format ["Suivi : nouvelle position toutes les %1 s, %2 numéro(s) au plus, téléphone ouvert", _every, (missionNamespace getVariable ["comspec_atak_native_trace_max", 3]) max 1]];
        };
        private _grow = (missionNamespace getVariable ["comspec_atak_native_trace_grow", 1.5]) max 0;
        for "_i" from ((count _list) - 1) to 0 step -1 do {
            private _e = _list select _i;
            private _p = _e getOrDefault ["pos", []];
            private _st = _e getOrDefault ["status", ""];
            private _col = switch (_st) do {
                case "OK": { COMSPEC_ATAK_Th get "hexOk" };
                case "PENDING": { _muted };
                case "UNKNOWN"; case "OWN": { COMSPEC_ATAK_Th get "hexAlert" };
                default { COMSPEC_ATAK_Th get "hexWarn" };
            };
            private _where = if ((count _p) < 2) then { format ["<t color='%1'>aucune position</t>", _muted] } else {
                private _age = (_now - (_e getOrDefault ["at", _now])) max 0;
                private _r = round (if (_st in ["OK", "SOS", "PENDING"]) then { _e get "rad" } else { ["aged", [_e get "rad", _age, _grow, 5000]] call _calc });
                private _d = player distance2D _p;
                format ["%1 · à %2 m près · %3 · <t color='%4'>%5 à %6, il y a %7 min</t>", [_p, 8] call comspec_atak_native_fnc_gridRef, _r,
                    [format ["%1 m", round _d], format ["%1 km", (_d / 1000) toFixed 1]] select (_d >= 1000), _muted,
                    ["dernière position connue", "relevé"] select (_st in ["OK", "SOS", "PENDING"]), _e getOrDefault ["hour", ""], floor (_age / 60)]
            };
            private _lbl = _e getOrDefault ["label", ""];
            _rows pushBack ["text", format ["<t font='COMSPEC_BarlowBold'>Tracé : %1</t>  <t color='%2'>%3</t>%4%5<br/><t size='0.85'>%6</t>",
                _e get "q", _col, [[_st] call _statusTxt, _e getOrDefault ["why", ""]] select ((_e getOrDefault ["why", ""]) isNotEqualTo "" && {_st isNotEqualTo "PENDING"}),
                ["", format ["  <t color='%1'>● SUIVI</t>", COMSPEC_ATAK_Th get "hexOk"]] select (_e getOrDefault ["track", false]),
                ["", format ["<br/><t size='0.8' color='%1'>%2</t>", _muted, _lbl]] select (_lbl isNotEqualTo ""), _where]];
            private _h = (_e getOrDefault ["hist", []]) select { (_x select 2) isNotEqualTo (_e getOrDefault ["at", -1]) };
            if (_h isNotEqualTo []) then {
                _rows pushBack ["text", format ["<t size='0.8' color='%1'>Positions précédentes : %2</t>", _muted,
                    ((_h select [((count _h) - 3) max 0, 3]) apply { format ["%1 (il y a %2 min)", [_x select 0, 6] call comspec_atak_native_fnc_gridRef, floor (((_now - (_x select 2)) max 0) / 60)] }) joinString " · "]];
            };
            private _has = (count _p) >= 2;
            _rows pushBack ["buttons", [
                ["CARTE", compile format ["['map', %1] call comspec_atak_native_fnc_traceAction;", _i], false, _has],
                [["SUIVRE", "ARRÊTER LE SUIVI"] select (_e getOrDefault ["track", false]), compile format ["['track', %1] call comspec_atak_native_fnc_traceAction;", _i], false, !(_st in ["UNKNOWN", "OWN"])],
                ["PARTAGER", compile format ["['share', %1] call comspec_atak_native_fnc_traceAction;", _i], false, _has, "Marqueur prérempli sur la carte : choisissez le canal (groupe, TOC…)"],
                ["EN FICHE SSE", compile format ["['toSse', %1] call comspec_atak_native_fnc_traceAction;", _i], false, _has, "Fiche SSE d'observation (FRO) préremplie avec la position tracée"],
                ["EFFACER", compile format ["['del', %1] call comspec_atak_native_fnc_traceAction;", _i]]
            ]];
        };
        private _log = missionNamespace getVariable ["COMSPEC_ATAK_TraceLog", []];
        _rows pushBack ["section", "Journal de traçage", format ["%1 requête(s)", count _log]];
        if (_log isEqualTo []) then { _rows pushBack ["text", format ["<t color='%1'>Aucune requête.</t>", _muted]]; };
        for "_i" from ((count _log) - 1) to (((count _log) - 10) max 0) step -1 do {
            (_log select _i) params ["_hr", "_q", "_l", "_s", "_g"];
            _rows pushBack ["text", format ["<t size='0.85'>%1 · %2%3 · %4%5</t>", _hr, _q, ["", format [" (%1)", _l]] select (_l isNotEqualTo ""), _s, ["", format [" · %1", _g]] select (_g isNotEqualTo "")]];
        };
        if (_log isNotEqualTo []) then { _rows pushBack ["buttons", [["EFFACER LE JOURNAL", { ['clear'] call comspec_atak_native_fnc_traceAction; }]]]; };
        _ret = _rows;
    };
    case "zen": {
        if (!hasInterface || {isNil "zen_context_menu_fnc_addAction"} || {missionNamespace getVariable ["COMSPEC_ATAK_TraceZenOn", false]}) exitWith {};
        missionNamespace setVariable ["COMSPEC_ATAK_TraceZenOn", true];
        private _icon = "\z\comspec_atak_native\addons\main\data\app_ew.paa";
        // IA visées : sélection Zeus, sinon l'unité sous la souris (jamais un joueur : son téléphone est l'ATAK).
        missionNamespace setVariable ["COMSPEC_ATAK_TraceZenAis", {
            params ["", ["_objs", []], "", "", "", ["_hover", objNull]];
            private _u = _objs select { _x isEqualType objNull && {_x isKindOf "CAManBase"} && {!isPlayer _x} };
            if (_u isEqualTo [] && {_hover isEqualType objNull} && {_hover isKindOf "CAManBase"} && {!isPlayer _hover}) then { _u = [_hover]; };
            _u
        }];
        private _set = {
            params ["_v", "_txt"];
            compile format ["
                private _u = _this call (missionNamespace getVariable 'COMSPEC_ATAK_TraceZenAis');
                { _x setVariable ['COMSPEC_ATAK_PhoneTrace', '%1', true]; } forEach _u;
                ['feedback', [format ['%2', count _u]]] call comspec_atak_native_fnc_zeusMenu;
            ", _v, _txt]
        };
        private _cond = { (_this call (missionNamespace getVariable "COMSPEC_ATAK_TraceZenAis")) isNotEqualTo [] };
        [["comspec_atak_trace_ai", "ATAK · Téléphone de l'IA (traçage)", _icon, {}, _cond] call comspec_atak_native_fnc_zenAction, [], 6] call zen_context_menu_fnc_addAction;
        {
            _x params ["_id", "_name", "_v", "_txt"];
            [[_id, _name, _icon, [_v, _txt] call _set, _cond] call comspec_atak_native_fnc_zenAction, ["comspec_atak_trace_ai"], 0] call zen_context_menu_fnc_addAction;
        } forEach [
            ["comspec_atak_trace_ai_on", "Allumé : traçable", "on", "Téléphone allumé et traçable : %1 IA."],
            ["comspec_atak_trace_ai_off", "Éteint : dernière position connue seulement", "off", "Téléphone éteint : %1 IA."],
            ["comspec_atak_trace_ai_burner", "Jetable : jamais traçable", "burner", "Téléphone jetable, non traçable : %1 IA."]
        ];
        [["comspec_atak_trace_ai_num", "Voir et copier son numéro", _icon, {
            private _u = (_this call (missionNamespace getVariable "COMSPEC_ATAK_TraceZenAis")) param [0, objNull];
            if (isNull _u) exitWith {};
            private _n = "";
            { if (_n isEqualTo "" && {_x isEqualType ""} && {(trim _x) isNotEqualTo ""}) then { _n = trim _x; }; } forEach [
                _u getVariable ["COMSPEC_ATAK_PhoneNumber", ""], _u getVariable ["comspec_sse_personPhone", ""], _u getVariable ["comspec_sse_phoneNumber", ""]];
            if (_n isEqualTo "" && {!isNil "comspec_sse_fnc_getSection"} && {!isNil { _u getVariable "comspec_sse_data" }}) then {
                private _id = [_u, "identity"] call comspec_sse_fnc_getSection;
                if (!isNil "_id" && {_id isEqualType createHashMap}) then { _n = _id getOrDefault ["phone", ""]; };
            };
            // Sans numéro connu : numéro calculé (toujours le même pour cette unité), le téléphone devient traçable.
            if (_n isEqualTo "") then {
                _n = ([_u] call comspec_atak_native_fnc_phoneIdent) select 0;
                if ((_u getVariable ["COMSPEC_ATAK_PhoneTrace", ""]) isEqualTo "") then { _u setVariable ["COMSPEC_ATAK_PhoneTrace", "on", true]; };
            };
            copyToClipboard _n;
            ['feedback', [format ["Numéro de %1 : %2 (copié)", name _u, _n]]] call comspec_atak_native_fnc_zeusMenu;
        }, _cond] call comspec_atak_native_fnc_zenAction, ["comspec_atak_trace_ai"], 0] call zen_context_menu_fnc_addAction;
        // Mission : traçage suspendu ou rétabli, mandat, réseau mobile civil.
        [["comspec_atak_trace_mission", "ATAK · Traçage des numéros", _icon, {}, { true }] call comspec_atak_native_fnc_zenAction, [], 5] call zen_context_menu_fnc_addAction;
        {
            _x params ["_id", "_name", "_var", "_val", "_txt", "_cond2"];
            [[_id, _name, _icon, compile format ["missionNamespace setVariable ['%1', %2, true]; ['feedback', ['%3']] call comspec_atak_native_fnc_zeusMenu;", _var, _val, _txt], _cond2]
                call comspec_atak_native_fnc_zenAction, ["comspec_atak_trace_mission"], 0] call zen_context_menu_fnc_addAction;
        } forEach [
            ["comspec_atak_trace_off", "Suspendre le traçage", "COMSPEC_ATAK_TraceOff", "true", "Traçage des numéros suspendu pour la mission.", { !((missionNamespace getVariable ["COMSPEC_ATAK_TraceOff", false]) isEqualTo true) }],
            ["comspec_atak_trace_on", "Rétablir le traçage", "COMSPEC_ATAK_TraceOff", "false", "Traçage des numéros rétabli.", { (missionNamespace getVariable ["COMSPEC_ATAK_TraceOff", false]) isEqualTo true }],
            ["comspec_atak_trace_warrant_on", "Accorder le mandat de traçage", "COMSPEC_ATAK_TraceWarrant", "true", "Mandat de traçage accordé.", { (missionNamespace getVariable ["comspec_atak_native_trace_warrant", false]) isEqualTo true && {!((missionNamespace getVariable ["COMSPEC_ATAK_TraceWarrant", false]) isEqualTo true)} }],
            ["comspec_atak_trace_warrant_off", "Retirer le mandat de traçage", "COMSPEC_ATAK_TraceWarrant", "false", "Mandat de traçage retiré.", { (missionNamespace getVariable ["COMSPEC_ATAK_TraceWarrant", false]) isEqualTo true }],
            ["comspec_atak_trace_civ_off", "Couper le réseau mobile civil", "COMSPEC_ATAK_TraceCivNet", "false", "Réseau mobile civil coupé : seuls les relais captent les téléphones.", { !((missionNamespace getVariable ["COMSPEC_ATAK_TraceCivNet", missionNamespace getVariable ["comspec_atak_native_trace_civnet", true]]) isEqualTo false) }],
            ["comspec_atak_trace_civ_on", "Rétablir le réseau mobile civil", "COMSPEC_ATAK_TraceCivNet", "true", "Réseau mobile civil rétabli.", { (missionNamespace getVariable ["COMSPEC_ATAK_TraceCivNet", missionNamespace getVariable ["comspec_atak_native_trace_civnet", true]]) isEqualTo false }]
        ];
    };
    default { _ret = false; };
};
_ret
