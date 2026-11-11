/*
    « Afficher un texte » (retour de TTRD : « AFFICHER UN TEXTE : on a aucun module pour l'écrire ») : écrire le message des
    écrans en affichage TEXTE (bandeaux LED, écrans 16:9, colonnes) depuis un module, le menu d'un écran ou le gestionnaire
    d'écrans, pour un écran, toute sa salle ou tous les écrans proches. Le dessin, l'écriture « message ; couleur ; durée ;
    clignotant | … » et les variables restent ceux de fn_tocText ; le réglage part au serveur par fn_tocScreen « view »
    (clé « text », variable publique COMSPEC_ATAK_TocText, gardée par fn_tocPersist). Rien d'autre n'est retenu : l'affichage
    d'avant n'est pas mémorisé.
    Modes :
      ["presets"]      -> [[libellé, texte dans l'écriture des messages]...] (table _presets)
      ["variables"]    -> [[variable, aide, exemple]...] (table _vars : seulement celles que fn_tocText remplace)
      ["targets", [portée, repère, rayon]]  -> écrans désignés capables de l'affichage TEXTE (dalles de mur comprises),
                         du plus proche au plus loin. Portée : "object" (le repère lui-même), "nearest" (le plus proche),
                         "near" (dans le rayon, 50 m sans indication), "hall" (même salle que le repère ; repère donné par
                         une position : salle de l'écran lié le plus proche), "all". Repère : objet ou position. Rayon 0 :
                         sans limite.
      ["send", [écrans, texte, couleur, vitesse, défilement]]  -> nombre d'écrans servis. Messages séparés par | ou par des
                         retours à la ligne ; l'écran qui n'est pas en affichage TEXTE y passe.
      ["clear", écrans] -> nombre d'écrans dont le message est effacé (liste vide : texte par défaut de l'affichage)
      ["spec", réglage] -> texte d'un réglage COMSPEC_ATAK_TocText dans l'écriture des messages (un message par ligne)
      ["dialog", [écran ou écrans, titre, ligne de résultat, repère]]  fenêtre de saisie (Zeus, joueur, gestionnaire) ; le
                         résultat de chaque envoi s'écrit dans la fenêtre, qui reste ouverte.
      ["dialogEvent", clé]  événements de la fenêtre
      ["moduleApply", …]    serveur : texte du module Eden (fn_moduleTocText), après la reprise de la partie précédente
*/
params [["_mode", ""], ["_a", []]];
private _fn = comspec_atak_native_fnc_tocTextTool;
private _ts = comspec_atak_native_fnc_tocScreen;
private _tt = comspec_atak_native_fnc_tocText;
// ---- TABLE _presets (JSON) : [libellé, texte]. Texte : « message ; couleur ; durée ; clignotant », messages séparés par |.
private _presets = [
["Bienvenue", "BIENVENUE AU {poste} ; G ; 10"],
["Alerte", "ALERTE ; R ; 5 ; clignotant"],
["Exercice en cours", "EXERCICE EN COURS ; A ; 10"],
["Heure H", "HEURE H {h} ; A ; 10"],
["Situation", "SITUATION : RAS ; G ; 10 | {dtg} ; W ; 5"],
["Briefing en cours", "BRIEFING EN COURS · SILENCE ; A ; 10"],
["Silence radio", "SILENCE RADIO ; R ; 8 ; clignotant"],
["Heure et date", "{zulus} Z · {date} ; G ; 10"],
["Accès réservé", "ACCÈS RÉSERVÉ {camp} ; A ; 10"],
["Fin d'exercice", "FIN D'EXERCICE ; G ; 10"]
];
// ---- FIN
// ---- TABLE _vars (JSON) : [variable, aide, exemple]. Seulement les variables remplacées par fn_tocText (« _expand »).
private _vars = [
["{zulu}", "Heure Zulu, heures et minutes", "14:32"],
["{zulus}", "Heure Zulu avec les secondes", "14:32:07"],
["{local}", "Heure de la mission", "06:15"],
["{date}", "Date de la mission", "11 NOV 2035"],
["{dtg}", "Groupe date-heure Zulu", "111432Z NOV 26"],
["{h}", "Chrono « heure H » du poste (sinon le premier chrono)", "H-00:12:30"],
["{camp}", "Camp de l'écran", "BLUFOR"],
["{poste}", "Nom de l'écran", "TOC BRAVO"],
["{groupe}", "Escouade liée à l'écran", "Alpha 1-1"]
];
// ---- FIN
private _desig = { params ["_o"]; (count (_o getVariable ["COMSPEC_ATAK_TocScr", []])) >= 8 };
// Format de la dalle (fn_tocScreen « format », index 4 : affichages permis) : une plaque de nom ne montre pas de texte.
private _can = { params ["_o"]; !isNull _o && {alive _o} && {"TEXT" in ((["format", _o] call _ts) param [4, []])} };
// Écrans désignés du registre, et dalles 2 et suivantes des murs (relais), capables de l'affichage TEXTE.
private _pool = {
    private _out = [];
    {
        if (_x isEqualType objNull && {!isNull _x}) then {
            _out pushBackUnique _x;
            { if (_x isEqualType objNull && {!isNull _x}) then { _out pushBackUnique _x; }; } forEach (_x getVariable ["COMSPEC_ATAK_TocPanels", []]);
        };
    } forEach (missionNamespace getVariable ["COMSPEC_ATAK_TocScreens", []]);
    _out select { [_x] call _desig && {[_x] call _can} }
};
private _asList = {
    params ["_v"];
    if (_v isEqualType objNull) then { _v = [_v]; };
    if !(_v isEqualType []) then { _v = []; };
    _v select { _x isEqualType objNull && {!isNull _x} }
};
private _cfgOf = { params ["_o"]; private _c = _o getVariable ["COMSPEC_ATAK_TocText", []]; [[], _c] select (_c isEqualType []) };

switch (_mode) do {
    case "presets": { +_presets };
    case "variables": { +_vars };
    case "targets": {
        _a params [["_scope", "near"], ["_ref", objNull], ["_rad", 50]];
        if !(_rad isEqualType 0) then { _rad = 50; };
        if (_ref isEqualType [] && {(count _ref) < 2}) then { _ref = objNull; };
        if !(_ref isEqualTypeAny [objNull, []]) then { _ref = objNull; };
        private _noRef = _ref isEqualType objNull && {isNull _ref};
        if (_scope isEqualTo "object") exitWith {
            // L'objet lui-même : écran désigné, ou écran pas encore désigné (« send » le désigne).
            if (_noRef || {_ref isEqualType []}) exitWith { [] };
            [[], [_ref]] select ([_ref] call _can && {[_ref] call _desig || {["isScreen", _ref] call comspec_atak_native_fnc_tocConfig}})
        };
        private _all = call _pool;
        if (_scope isEqualTo "all") exitWith { _all };
        if (_noRef) exitWith { [] };
        private _rows = [];
        { _rows pushBack [_x distance _ref, _forEachIndex, _x]; } forEach _all;
        _rows sort true;
        if (_rad > 0) then { _rows = _rows select { (_x select 0) <= _rad }; };
        _all = _rows apply { _x select 2 };
        switch (_scope) do {
            case "nearest": { _all select [0, 1] };
            case "hall": {
                private _hid = "";
                if (_ref isEqualType objNull) then {
                    _hid = ["of", _ref] call comspec_atak_native_fnc_tocHall;
                } else {
                    { _hid = ["of", _x] call comspec_atak_native_fnc_tocHall; if (_hid isNotEqualTo "") exitWith {}; } forEach _all;
                };
                if (_hid isEqualTo "") exitWith { [] };
                // Toute la salle, même au-delà du rayon (le rayon ne sert qu'à trouver la salle depuis une position).
                (call _pool) select { (["of", _x] call comspec_atak_native_fnc_tocHall) isEqualTo _hid }
            };
            default { _all };
        }
    };
    case "send": {
        _a params [["_tg", []], ["_s", ""], ["_col", "G"], ["_speed", 1], ["_scroll", 0]];
        if !(_s isEqualType "") exitWith { 0 };
        // Un message par ligne ou séparés par | : même écriture ensuite (fn_tocText « parse »).
        _s = _s regexReplace ["[\r\n]+", "|"];
        private _n = 0;
        {
            private _o = _x;
            private _old = [_o] call _cfgOf;
            private _cfg = ["parse", [_s, _col, _speed, _scroll, _old param [3, 0], _old param [4, 1]]] call _tt;
            if ((count (_cfg select 0)) isEqualTo 0) then { continue };
            private _c = _o getVariable ["COMSPEC_ATAK_TocScr", []];
            if ((count _c) < 8) then {
                // Écran pas encore désigné : il le devient en affichage TEXTE (comme fn_tocScreen « ensure », sans notification).
                private _nm = getText (configOf _o >> "displayName");
                if (_nm isEqualTo "") then { _nm = "TOC"; };
                ["add", [_o, _nm select [0, 32], "", 0, "TEXT", true, false]] call _ts;
            };
            ["view", [_o, "text", _cfg]] call _ts;
            if ((count _c) >= 8 && {(_c param [3, ""]) isNotEqualTo "TEXT"}) then { ["view", [_o, "mode", "TEXT"]] call _ts; };
            _o setVariable ["COMSPEC_ATAK_TocAt", -10];
            _n = _n + 1;
        } forEach ([_tg] call _asList);
        _n
    };
    case "clear": {
        private _n = 0;
        {
            if !([_x] call _desig) then { continue };
            private _old = [_x] call _cfgOf;
            ["view", [_x, "text", [[], _old param [1, 1], _old param [2, 0], _old param [3, 0], _old param [4, 1]]]] call _ts;
            _x setVariable ["COMSPEC_ATAK_TocAt", -10];
            _n = _n + 1;
        } forEach ([_a] call _asList);
        _n
    };
    case "spec": {
        // Réglage -> texte : la couleur du premier message est la couleur commune, écrite seulement quand elle change.
        if !(_a isEqualType []) exitWith { "" };
        private _msgs = (_a param [0, []]);
        if !(_msgs isEqualType []) exitWith { "" };
        _msgs = _msgs select { _x isEqualType [] && {(_x param [0, ""]) isEqualType ""} };
        private _base = (_msgs param [0, []]) param [1, "G"];
        (_msgs apply {
            _x params [["_m", ""], ["_c", "G"], ["_secs", 10], ["_blk", 0]];
            switch (true) do {
                case (_blk isEqualTo 1): { format ["%1 ; %2 ; %3 ; clignotant", _m, _c, _secs] };
                case (_secs isNotEqualTo 10): { format ["%1 ; %2 ; %3", _m, _c, _secs] };
                case (_c isNotEqualTo _base): { format ["%1 ; %2", _m, _c] };
                default { _m };
            }
        }) joinString (toString [10])
    };
    case "dialog": {
        // Fenêtre de saisie, ouverte par-dessus ce qui est ouvert (Zeus, PC du poste, gestionnaire, jeu) : fn_zeusForm ne
        // s'ouvre que sur le Zeus ou sur le jeu, pas sur une autre fenêtre. Mêmes contrôles que lui.
        _a params [["_tg", objNull], ["_title", ""], ["_note", ""], ["_ref", objNull]];
        if (!hasInterface) exitWith { false };
        disableSerialization;
        private _list = [];
        { _list append (["targets", ["object", _x]] call _fn); } forEach ([_tg] call _asList);
        private _org = if (_list isEqualTo []) then { _ref } else { _list select 0 };
        if (_org isEqualType objNull && {isNull _org}) then { _org = player; };
        // Aucun écran choisi : le repère n'est qu'un endroit (salle et écrans cherchés autour de lui).
        if (_list isEqualTo [] && {_org isEqualType objNull}) then { _org = getPosATL _org; };
        if !(_title isEqualType "" && {_title isNotEqualTo ""}) then { _title = "Afficher un texte"; };
        if !(_note isEqualType "") then { _note = ""; };
        private _tops = allDisplays select { !isNull _x && {(displayUniqueName _x) isEqualTo ""} };
        private _parent = _tops param [(count _tops) - 1, findDisplay 46];
        if (isNull _parent) exitWith { false };
        private _d = _parent createDisplay "RscDisplayEmpty";
        if (isNull _d) exitWith { false };
        private _ui = createHashMap;
        uiNamespace setVariable ["COMSPEC_ATAK_TxtTool", createHashMapFromArray [["d", _d], ["ui", _ui], ["list", _list], ["org", _org]]];
        // Réglage actuel du premier écran : son texte revient dans la fenêtre, prêt à être corrigé.
        private _cur = if (_list isEqualTo []) then { [] } else { [_list select 0] call _cfgOf };
        private _txt = ["spec", _cur] call _fn;
        private _schema = ["schema"] call _tt;
        private _opts = { params ["_key"]; (_schema select (_schema findIf { (_x select 0) isEqualTo _key })) select 4 };
        private _w = safeZoneW * 0.44;
        private _x0 = safeZoneX + (safeZoneW - _w) / 2;
        private _rh = safeZoneH * 0.034;
        private _gp = safeZoneH * 0.008;
        private _lw = _w * 0.3;
        private _iw = _w - _lw - 3 * _gp;
        private _h = _rh * 1.3 + 9 * (_rh + _gp) + _rh * 4 + _rh * 2.5 + _rh * 1.6 + 4 * _gp;
        private _y0 = safeZoneY + (safeZoneH - _h) / 2;
        private _mk = {
            params ["_cls", "_pos", ["_key", ""]];
            private _c = _d ctrlCreate [_cls, -1];
            _c ctrlSetPosition _pos;
            _c ctrlCommit 0;
            if (_key isNotEqualTo "") then { _ui set [_key, _c]; };
            _c
        };
        (["COMSPEC_RscPanel", [_x0, _y0, _w, _h]] call _mk) ctrlSetBackgroundColor [0.035, 0.045, 0.04, 0.97];
        private _head = ["COMSPEC_RscText", [_x0, _y0, _w, _rh * 1.3]] call _mk;
        _head ctrlSetBackgroundColor [0.10, 0.20, 0.12, 1];
        _head ctrlSetText (("  " + _title) call comspec_atak_native_fnc_tr);
        _head ctrlSetFont (COMSPEC_ATAK_FontSet select 1);
        private _cy = _y0 + _rh * 1.3 + _gp;
        private _ix = _x0 + _lw + _gp;
        // Écran visé : son nom et sa salle, ou ce qui manque.
        private _hallName = {
            params ["_o"];
            private _hl = ["get", ["of", _o] call comspec_atak_native_fnc_tocHall] call comspec_atak_native_fnc_tocHall;
            _hl param [1, ""]
        };
        private _who = switch (true) do {
            case (_list isEqualTo []): { ("Aucun écran choisi : le texte part aux écrans texte proches") call comspec_atak_native_fnc_tr };
            case ((count _list) > 1): { format [("%1 écrans choisis") call comspec_atak_native_fnc_tr, count _list] };
            default {
                private _o = _list select 0;
                private _nm = (_o getVariable ["COMSPEC_ATAK_TocScr", []]) param [0, getText (configOf _o >> "displayName")];
                private _hn = [_o] call _hallName;
                format ["%1 · %2", _nm, [format [("salle %1") call comspec_atak_native_fnc_tr, _hn], ("sans salle") call comspec_atak_native_fnc_tr] select (_hn isEqualTo "")]
            };
        };
        (["COMSPEC_RscText", [_x0 + _gp, _cy, _w - 2 * _gp, _rh]] call _mk) ctrlSetText _who; // i18n:brut (nom de l'écran, morceaux déjà traduits)
        _cy = _cy + _rh + _gp;
        private _row = {
            params ["_label", "_cls", "_key", ["_rows", 1]];
            (["COMSPEC_RscText", [_x0 + _gp, _cy, _lw, _rh]] call _mk) ctrlSetText (_label call comspec_atak_native_fnc_tr);
            private _c = [_cls, [_ix, _cy, _iw, _rh * _rows], _key] call _mk;
            _cy = _cy + _rh * _rows + _gp;
            _c
        };
        private _combo = {
            params ["_label", "_key", "_items", "_sel"];
            private _c = [_label, "COMSPEC_RscCombo", _key] call _row;
            { _c lbAdd ((_x select 0) call comspec_atak_native_fnc_tr); } forEach _items;
            _c setVariable ["values", _items apply { _x select 1 }];
            _c lbSetCurSel ((_items findIf { (_x select 1) isEqualTo _sel }) max 0);
            _c
        };
        private _pre = ["Modèle de message", "preset", [["Aucun modèle", ""]] + _presets, ""] call _combo;
        _pre ctrlAddEventHandler ["LBSelChanged", { ["dialogEvent", "preset"] call comspec_atak_native_fnc_tocTextTool; }];
        private _ed = ["Message", "COMSPEC_RscEditMulti", "text", 4] call _row;
        _ed ctrlSetText _txt; // i18n:brut (texte saisi par l'opérateur)
        private _help = ["COMSPEC_RscStructuredText", [_x0 + _gp, _cy, _w - 2 * _gp, _rh * 2.5]] call _mk;
        _help ctrlSetStructuredText parseText (format ["<t size='0.8' color='#8a9a93'>%1<br/>%2 %3</t>",
            ("Un message par ligne, ou séparés par |. Écriture : message ; couleur ; durée ; clignotant. Exemple : ALERTE ; R ; 5 ; clignotant") call comspec_atak_native_fnc_tr,
            ("Variables :") call comspec_atak_native_fnc_tr, (_vars apply { _x select 0 }) joinString " "]);
        _cy = _cy + _rh * 2.5 + _gp;
        private _base = ((_cur param [0, []]) param [0, []]) param [1, "G"];
        ["Couleur", "color", ["color"] call _opts, _base] call _combo;
        ["Vitesse", "speed", ["speed"] call _opts, _cur param [1, 1]] call _combo;
        ["Défilement", "scroll", ["scroll"] call _opts, _cur param [2, 0]] call _combo;
        private _scopes = [["Tous les écrans texte de la salle", "hall"], ["Tous les écrans texte à moins de 50 m", "near"]];
        if (_list isNotEqualTo []) then { _scopes = [[["Cet écran", "Les écrans choisis"] select ((count _list) > 1), "list"]] + _scopes; };
        ["Écrans visés", "scope", _scopes, (_scopes select 0) select 1] call _combo;
        ["Action", "act", [["Afficher", "show"], ["Effacer le message", "clear"]], "show"] call _combo;
        // Résultat de la dernière action, écrit dans la fenêtre.
        private _res = ["COMSPEC_RscText", [_x0 + _gp, _cy, _w - 2 * _gp, _rh], "result"] call _mk;
        _res ctrlSetTextColor (["rgba", ["accent", 1]] call comspec_atak_native_fnc_tocTheme);
        _res ctrlSetText (_note call comspec_atak_native_fnc_tr);
        private _by = _y0 + _h - _rh * 1.3;
        private _ok = ["COMSPEC_RscButtonPrimary", [_x0 + _w - _gp - _w * 0.22, _by, _w * 0.22, _rh]] call _mk;
        _ok ctrlSetText (("VALIDER") call comspec_atak_native_fnc_tr);
        _ok ctrlAddEventHandler ["ButtonClick", { ["dialogEvent", "ok"] call comspec_atak_native_fnc_tocTextTool; }];
        private _no = ["COMSPEC_RscButton", [_x0 + _w - 2 * _gp - _w * 0.44, _by, _w * 0.22, _rh]] call _mk;
        _no ctrlSetText (("FERMER") call comspec_atak_native_fnc_tr);
        _no ctrlAddEventHandler ["ButtonClick", { (ctrlParent (_this select 0)) closeDisplay 2; }];
        ctrlSetFocus _ed;
        true
    };
    case "dialogEvent": {
        disableSerialization;
        private _st = uiNamespace getVariable ["COMSPEC_ATAK_TxtTool", createHashMap];
        private _d = _st getOrDefault ["d", displayNull];
        if (isNull _d) exitWith { false };
        private _ui = _st get "ui";
        private _val = { params ["_key"]; private _c = _ui get _key; (_c getVariable ["values", []]) param [lbCurSel _c, ""] };
        if (_a isEqualTo "preset") exitWith {
            // Modèle choisi : son texte remplace le message, prêt à être corrigé puis validé.
            private _p = ["preset"] call _val;
            if (_p isNotEqualTo "") then { (_ui get "text") ctrlSetText (_p call comspec_atak_native_fnc_tr); };
            true
        };
        if (_a isNotEqualTo "ok") exitWith { false };
        private _org = _st get "org";
        private _tg = switch (["scope"] call _val) do {
            case "list": { _st get "list" };
            case "hall": { ["targets", ["hall", _org, 50]] call _fn };
            default { ["targets", ["near", _org, 50]] call _fn };
        };
        private _s = ctrlText (_ui get "text");
        private _clear = (["act"] call _val) isEqualTo "clear";
        private _n = -1;
        if (_clear) then {
            _n = ["clear", _tg] call _fn;
        } else {
            if ((["fromFields", [_s regexReplace ["[\r\n]+", "|"]]] call _tt) isNotEqualTo []) then {
                _n = ["send", [_tg, _s, ["color"] call _val, ["speed"] call _val, ["scroll"] call _val]] call _fn;
            };
        };
        private _msg = switch (true) do {
            case (_n < 0): { ("Écrivez d'abord un message") call comspec_atak_native_fnc_tr };
            case (_n isEqualTo 0): { ("Aucun écran texte dans cette portée (écran sans salle, ou trop loin)") call comspec_atak_native_fnc_tr };
            case (_clear): { format [("Message effacé sur %1 écran(s)") call comspec_atak_native_fnc_tr, _n] };
            default { format [("Texte affiché sur %1 écran(s)") call comspec_atak_native_fnc_tr, _n] };
        };
        private _res = _ui get "result";
        _res ctrlSetTextColor (["rgba", [["accent", "amber"] select (_n <= 0), 1]] call comspec_atak_native_fnc_tocTheme);
        _res ctrlSetText _msg; // i18n:brut (déjà traduit)
        _n > 0
    };
    case "moduleApply": {
        // Module Eden : plusieurs passes (les écrans d'une salle complète sont posés un à un après la reprise) ; un écran
        // déjà servi n'est jamais réécrit, ce que l'opérateur a changé entre-temps reste.
        _a params [["_pos", [0, 0, 0]], ["_sync", []], ["_s", ""], ["_col", "G"], ["_speed", 1], ["_scroll", 0], ["_scope", "sync"], ["_rad", 50], ["_done", []], ["_pass", 0]];
        if (!isServer || {!(_s isEqualType "")} || {(trim _s) isEqualTo ""}) exitWith { 0 };
        private _tg = [];
        if (_scope isEqualTo "sync") then {
            { _tg append (["targets", ["object", _x]] call _fn); } forEach ([_sync] call _asList);
            // Rien de synchronisé : l'écran texte le plus proche du module.
            if (_tg isEqualTo [] && {([_sync] call _asList) isEqualTo []}) then { _scope = "nearest"; };
        };
        if (_scope isNotEqualTo "sync") then { _tg = ["targets", [_scope, _pos, [0, _rad] select (_scope isEqualTo "near")]] call _fn; };
        if (_scope isEqualTo "nearest" && {_done isNotEqualTo []}) then { _tg = []; };
        _tg = _tg - _done;
        private _n = ["send", [_tg, _s, _col, _speed, _scroll]] call _fn;
        _done append _tg;
        if (_n > 0) then { ["INFO", "TOC", format ["Module Afficher un texte : texte posé sur %1 écran(s) (%2)", _n, _scope]] call comspec_atak_native_fnc_log; };
        if (_pass < 3) then {
            [{ ["moduleApply", _this] call comspec_atak_native_fnc_tocTextTool; }, [_pos, _sync, _s, _col, _speed, _scroll, _scope, _rad, _done, _pass + 1], 8] call comspec_atak_native_fnc_waitAndExec;
        } else {
            if (_done isEqualTo []) then { ["WARNING", "TOC", format ["Module Afficher un texte : aucun écran capable d'afficher du texte trouvé (%1)", _scope]] call comspec_atak_native_fnc_log; };
        };
        _n
    };
    default { false };
};
