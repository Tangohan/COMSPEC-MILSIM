/*
    App Academy (demande de TTRD, 01/11) : les cours COMSPEC Academy du site lus en jeu sur le téléphone, et diffusés
    par l'instructeur sur les écrans posables (fn_screenCast, source ACADEMY). Tout passe par COMSPEC Link
    (commande Academy, 2.0.113+) avec la session de jeu du compte : jamais de mot de passe dans le jeu, jamais de
    requête qui fige le jeu (fn_extAwait). Les leçons reçues restent en mémoire 10 min.
    État : uiNamespace COMSPEC_ATAK_Academy (HashMap)
      view  "LIST" | "PICK" | "LESSON"   courses [[clé, titre, leçon, nb leçons, étape, terminé, titre leçon]...]
      all   [[clé, titre, nb leçons, publié]...] (encadrement Formation seulement)   staff, lang, err, busy, at
      lesson [repère, clé, leçon, cours, titre leçon, nb leçons, pages [[genre, étape, titre, texte]...], étape, diapositives [[titre, lien]...]]
      idx   page affichée     pick [clé, titre, nb leçons]     cache HashMap repère -> [leçon, heure]
    Modes :
      ["ensure"] | ["load"]          liste de mes cours (ensure : si jamais chargée ou plus de 60 s)
      ["open", [clé, leçon]]         ouvre une leçon (pages demandées par morceaux, la DLL rend 8 Ko au plus)
      ["step", ±1] | ["goto", i]     page précédente / suivante : écrans que je diffuse suivis, étape vue renvoyée au site
      ["follow", [repère, i]]        l'écran a été avancé au menu ACE : le téléphone suit
      ["pick", [clé, titre, n]]      choisir une leçon d'un cours (instructeur, cours terminé)
      ["cast", objet] | ["stop", objet]   diffuser / arrêter la leçon sur un écran (instructeur)
      ["screens"]                    écrans à portée (écrans COMSPEC et écrans TOC)
      ["slides", lien]               diapositives Google Slides du cours, ouvertes dans l'app Briefing
      ["list"]                       retour à la liste
*/
params [["_mode", "ensure"], ["_a", []]];
private _st = uiNamespace getVariable ["COMSPEC_ATAK_Academy", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_Academy", _st];
private _render = { [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "ACADEMY") then { ["ACADEMY"] call comspec_atak_native_fnc_pageRender; }; }] call comspec_atak_native_fnc_nextFrame; };
private _lang = ["en", "fr"] select ((missionNamespace getVariable ["COMSPEC_ATAK_Lang", "fr"]) isEqualTo "fr");
// Erreur de la DLL ou du site, en phrase pour le joueur.
private _why = {
    params ["_raw"];
    switch (true) do {
        case (_raw isEqualTo "" || {(_raw find "unknown") >= 0} || {(_raw find "bad_args") >= 0}): { "COMSPEC Link ne connaît pas encore Academy : mettez-le à jour (2.0.113 ou plus)." };
        case ((_raw find "unauthorized") >= 0 || {(_raw find "not_connected") >= 0} || {(_raw find "session_required") >= 0}): { "Connexion Athena requise : liez le téléphone à votre compte (app Athena)." };
        case ((_raw find "academy_unpaid") >= 0): { "Academy n'est pas comprise dans l'abonnement de votre communauté." };
        case ((_raw find "module_unavailable") >= 0): { "Le module Formation est fermé dans votre communauté." };
        case ((_raw find "restricted") >= 0): { "L'accès aux formations vous est restreint pour le moment." };
        case ((_raw find "tenant_context") >= 0): { "Choisissez d'abord votre communauté dans l'app Athena." };
        case ((_raw find "not_found") >= 0): { "Cette leçon n'est pas disponible : elle n'est pas encore atteinte ou le cours a été retiré." };
        case ((_raw find "timeout") >= 0): { "Athena n'a pas répondu à temps. Réessayez dans un instant." };
        default { "Athena est indisponible pour le moment. Réessayez dans un instant." };
    };
};
private _split = { params ["_s"]; if (!isNil "comspec_overwatch_connect_fnc_splitKeepEmpty") then { [_s, toString [9]] call comspec_overwatch_connect_fnc_splitKeepEmpty } else { _s splitString toString [9] } };
private _res = { params ["_r"]; private _v = if (!isNil "comspec_overwatch_connect_fnc_extResult") then { [_r] call comspec_overwatch_connect_fnc_extResult } else { _r param [0, ""] }; if !(_v isEqualType "") then { _v = str _v; }; _v };

switch (_mode) do {
    case "ensure";
    case "load": {
        if (_mode isEqualTo "ensure" && {diag_tickTime - (_st getOrDefault ["at", -1e9]) < ([60, 20] select ((_st getOrDefault ["err", ""]) isNotEqualTo ""))}) exitWith {};
        // Chargement perdu (fin de mission, script arrêté) : libéré après 30 s.
        if ((_st getOrDefault ["busy", false]) && {diag_tickTime - (_st getOrDefault ["busyAt", 0]) < 30}) exitWith {};
        if !([] call comspec_atak_native_fnc_bridge) exitWith { _st set ["err", "COMSPEC Link est nécessaire pour lire vos cours Academy."]; _st set ["at", diag_tickTime]; call _render; };
        _st set ["busy", true];
        _st set ["busyAt", diag_tickTime];
        call _render;
        [_st, _render, _lang, _why, _split, _res] spawn {
            params ["_st", "_render", "_lang", "_why", "_split", "_res"];
            private _raw = [["COMSPECExtension", "Academy", ["courses", "", "", _lang], 25] call comspec_atak_native_fnc_extAwait] call _res;
            _st set ["busy", false];
            _st set ["at", diag_tickTime];
            if ((_raw select [0, 3]) isNotEqualTo "OK|") exitWith { _st set ["err", [_raw] call _why]; call _render; };
            private _mine = [];
            private _all = [];
            {
                private _c = [_x] call _split;
                switch (_c param [0, ""]) do {
                    case "H": { _st set ["staff", (_c param [1, "0"]) isEqualTo "1"]; };
                    case "C": { if ((count _c) >= 8) then { _mine pushBack [_c select 1, _c select 2, parseNumber (_c select 3), parseNumber (_c select 4), parseNumber (_c select 5), (_c select 6) isEqualTo "1", _c select 7]; }; };
                    case "S": { if ((count _c) >= 5) then { _all pushBack [_c select 1, _c select 2, parseNumber (_c select 3), (_c select 4) isEqualTo "1"]; }; };
                };
            } forEach ((_raw select [3]) splitString toString [10]);
            _st set ["courses", _mine];
            _st set ["all", _all];
            _st set ["err", ""];
            call _render;
        };
    };
    case "open": {
        _a params [["_key", ""], ["_ch", 0]];
        private _ref = format ["%1#%2#%3", _key, _ch, _lang];
        private _hit = (_st getOrDefault ["cache", createHashMap]) getOrDefault [_ref, []];
        if ((count _hit) >= 2 && {diag_tickTime - (_hit select 1) < 600}) exitWith {
            _st set ["lesson", _hit select 0];
            _st set ["idx", 0];
            _st set ["view", "LESSON"];
            _st set ["err", ""];
            call _render;
        };
        if ((_st getOrDefault ["busy", false]) && {diag_tickTime - (_st getOrDefault ["busyAt", 0]) < 30}) exitWith {};
        _st set ["busy", true];
        _st set ["busyAt", diag_tickTime];
        _st set ["err", ""];
        call _render;
        [_st, _render, _lang, _why, _split, _res, _key, _ch, _ref] spawn {
            params ["_st", "_render", "_lang", "_why", "_split", "_res", "_key", "_ch", "_ref"];
            private _from = 0;
            private _head = [];
            private _pages = [];
            private _slides = [];
            private _err = "";
            // Morceaux de 7 Ko : 12 au plus (une leçon reste bien en dessous).
            for "_n" from 1 to 12 do {
                private _raw = [["COMSPECExtension", "Academy", ["lesson", _key, str _ch, _lang, str _from], 25] call comspec_atak_native_fnc_extAwait] call _res;
                if ((_raw select [0, 3]) isNotEqualTo "OK|") exitWith { _err = [_raw] call _why; };
                private _next = 0;
                {
                    private _c = [_x] call _split;
                    switch (_c param [0, ""]) do {
                        case "L": { if ((count _c) >= 10) then { _head = _c; _next = parseNumber (_c select 9); }; };
                        case "R": { if ((count _c) >= 3) then { _slides pushBack [_c select 1, _c select 2]; }; };
                        case "P": { if ((count _c) >= 6) then { _pages pushBack [_c select 2, parseNumber (_c select 3), _c select 4, _c select 5]; }; };
                    };
                } forEach ((_raw select [3]) splitString toString [10]);
                if (_next <= _from) exitWith {};
                _from = _next;
            };
            _st set ["busy", false];
            if (_err isNotEqualTo "" || {(count _head) < 10} || {(count _pages) isEqualTo 0}) exitWith {
                _st set ["err", [_err, "Leçon vide ou incomplète : réessayez."] select (_err isEqualTo "")];
                call _render;
            };
            private _lesson = [_ref, _key, _ch, _head select 3, _head select 4, parseNumber (_head select 5), _pages, parseNumber (_head select 7), _slides];
            private _cache = _st getOrDefault ["cache", createHashMap];
            if ((count _cache) > 20) then { _cache = createHashMap; };
            _cache set [_ref, [_lesson, diag_tickTime]];
            _st set ["cache", _cache];
            _st set ["lesson", _lesson];
            _st set ["idx", 0];
            _st set ["view", "LESSON"];
            call _render;
        };
    };
    case "step";
    case "goto": {
        private _l = _st getOrDefault ["lesson", []];
        if ((count _l) < 9) exitWith {};
        private _n = count (_l select 6);
        private _i = if (_mode isEqualTo "step") then { (_st getOrDefault ["idx", 0]) + _a } else { _a };
        _i = (_i max 0) min ((_n - 1) max 0);
        _st set ["idx", _i];
        // Écrans où je diffuse cette leçon : ils suivent (seul l'index circule).
        {
            private _c = _x getVariable ["COMSPEC_ATAK_ScreenCast", []];
            if ((count _c) >= 6 && {(_c select 0) isEqualTo player} && {(_c select 5) isEqualTo (_l select 0)} && {(_c select 4) isNotEqualTo _i}) then {
                private _c2 = +_c;
                _c2 set [4, _i];
                _x setVariable ["COMSPEC_ATAK_ScreenCast", _c2, true];
            };
        } forEach ((missionNamespace getVariable ["COMSPEC_ATAK_Screens", []]) select { !isNull _x });
        ["seenCheck"] call comspec_atak_native_fnc_academy;
        call _render;
    };
    case "seenCheck": {
        // Élève arrivé à la dernière page du cours (ou de la démonstration) de sa leçon en cours : étape vue sur le site.
        private _l = _st getOrDefault ["lesson", []];
        if ((count _l) < 9) exitWith {};
        private _stage = _l select 7;
        if !(_stage in [0, 1]) exitWith {};
        private _last = -1;
        { if ((_x select 1) isEqualTo _stage) then { _last = _forEachIndex; }; } forEach (_l select 6);
        if (_last < 0 || {(_st getOrDefault ["idx", 0]) < _last}) exitWith {};
        private _sent = _st getOrDefault ["seen", createHashMap];
        private _k = format ["%1#%2", _l select 0, _stage];
        if (diag_tickTime - (_sent getOrDefault [_k, -1e9]) < 30) exitWith {};
        _sent set [_k, diag_tickTime];
        _st set ["seen", _sent];
        [_st, _render, _l, _stage, _res, _split] spawn {
            params ["_st", "_render", "_l", "_stage", "_res", "_split"];
            private _raw = [["COMSPECExtension", "Academy", ["seen", _l select 1, str (_l select 2), str _stage], 25] call comspec_atak_native_fnc_extAwait] call _res;
            if ((_raw select [0, 3]) isNotEqualTo "OK|") exitWith {
                ["WARNING", "ACADEMY", format ["Étape %1 de %2 non enregistrée : %3", _stage, _l select 0, _raw select [0, 80]]] call comspec_atak_native_fnc_log;
            };
            private _c = [((_raw select [3]) splitString toString [10]) param [0, ""]] call _split;
            private _cur = _st getOrDefault ["lesson", []];
            if ((count _cur) < 9 || {(_cur select 0) isNotEqualTo (_l select 0)}) exitWith {};
            private _now = [-1, parseNumber (_c param [2, "-1"])] select ((parseNumber (_c param [1, "-1"])) isEqualTo (_l select 2) && {(_c param [3, "0"]) isEqualTo "0"});
            _cur set [7, _now];
            ["INFO", ["Cours lu : étape enregistrée sur Academy", "Démonstration vue : étape enregistrée sur Academy"] select (_stage isEqualTo 1), 3, 20] call comspec_atak_native_fnc_notify;
            // Liste à jour au prochain affichage, et la démonstration peut suivre si elle est déjà lue.
            _st set ["at", -1e9];
            ["seenCheck"] call comspec_atak_native_fnc_academy;
            call _render;
        };
    };
    case "follow": {
        _a params [["_ref", ""], ["_i", 0]];
        private _l = _st getOrDefault ["lesson", []];
        if ((count _l) >= 9 && {(_l select 0) isEqualTo _ref}) then { _st set ["idx", _i]; ["seenCheck"] call comspec_atak_native_fnc_academy; call _render; };
    };
    case "pick": {
        _st set ["pick", _a];
        _st set ["view", "PICK"];
        call _render;
    };
    case "list": {
        _st set ["view", "LIST"];
        ["ensure"] call comspec_atak_native_fnc_academy;
        call _render;
    };
    case "screens": {
        private _l = ["list"] call comspec_atak_native_fnc_screenCast;
        {
            if (alive _x && {(player distance _x) < 30} && {!(_x in _l)}) then { _l pushBack _x; };
        } forEach ((missionNamespace getVariable ["COMSPEC_ATAK_TocScreens", []]) select { !isNull _x && {[_x] call { params ["_o"]; private _s = (_o getVariable ["COMSPEC_ATAK_TocScr", []]) param [1, ""]; _s isEqualTo "" || {_s isEqualTo str side group player} }} });
        _l
    };
    case "cast": {
        private _l = _st getOrDefault ["lesson", []];
        if ((count _l) < 9 || {isNull _a} || {!(_st getOrDefault ["staff", false])}) exitWith { false };
        private _ok = ["castAcademy", [_a, [_l select 0, _l select 3, format [("Leçon %1 · %2") call comspec_atak_native_fnc_tr, (_l select 2) + 1, _l select 4], (_l select 6) apply { [_x select 0, _x select 2, _x select 3] }], _st getOrDefault ["idx", 0]]] call comspec_atak_native_fnc_screenCast;
        [{ call _this; }, _render, 0.3] call comspec_atak_native_fnc_waitAndExec;
        _ok
    };
    case "stop": {
        if (isNull _a) exitWith { false };
        ["stop", _a] call comspec_atak_native_fnc_screenCast;
        [{ call _this; }, _render, 0.3] call comspec_atak_native_fnc_waitAndExec;
        true
    };
    case "slides": {
        if (_a isEqualTo "") exitWith {};
        ["load", [_a, false]] call comspec_atak_native_fnc_briefingCfg;
        ["BRIEFING"] call comspec_atak_native_fnc_navigate;
    };
};
