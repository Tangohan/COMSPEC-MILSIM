/*
    Apps épinglées sur la carte du téléphone (4.16.0 ; trois à la fois et mini-apps en 4.17.0) : un bandeau par app, de la
    style du bandeau des coordonnées, posé sur sa grille (fn_mapBandGrid : rangées empilées au-dessus ou en dessous de lui, cases
    de même taille), qui la résume. Petit format : une ligne par app. Bandeau ORDRES : l'ordre qui attend et ses réponses,
    sans ouvrir l'app (téléphone porté : la touche « Interagir avec la miniature » est écrite sur le bandeau).
    Messages, SMS, Ordres : non lus et dernier message ou ordre, avec l'expéditeur et l'heure. Mini-apps : NAV (étape active
    de l'itinéraire, distance et cap), FEUX (mission de tir en cours), MEDEVAC (étape de la demande, LZ), ALERTES (dernière
    alerte du journal), HEURE H (compte à rebours du groupe, sinon chrono ou minuteur de la montre). Un toucher ouvre l'app.
    Facultatif : rien n'est posé par défaut.
    Choix du joueur : réglages CBA « comspec_atak_native_map_pinned_app » (celui de la 4.16.0, premier emplacement), « …_2 »
    et « …_3 » (par joueur, gardés entre les sessions), aussi dans Réglages > Carte et dans le panneau SITUATION de la carte.
    Appels :
      ["apps"]                       -> pages épinglées, dans l'ordre des emplacements, sans doublon ([] : aucune) ; une app
                                        interdite, retirée ou désinstallée n'y est pas
      ["height", [taille du texte, marge, compact]] -> hauteur du bandeau (fn_mapBandGrid : deux lignes, ou une en compact)
      ["build", [_ov, _mk, [x, y, w, h], [taille du texte, marge, marge des cases, largeur d'un caractère, ratio], interactif, page, opacité, compact]]
                                     -> crée les contrôles (fn_pageMap, case donnée par fn_mapBandGrid) et les remplit ;
                                        compact : une seule ligne (petit format, ou pile trop haute)
      ["act", [id de l'ordre, statut]] -> bouton du bandeau ORDRES : réponse à l'ordre par le chemin de l'app Ordres
                                        (fn_taskAction : Athena ou TOC, suivi fn_orderAck, journal), jamais deux fois le même
                                        statut ; ["act", ["", "NEXT"]] : ordre suivant
      ["panelRows", [dépliée, code du toucher]] -> volet SITUATION : ligne compacte « Apps épinglées · n/3 », le choix et
                                        l'explication seulement quand elle est dépliée
      ["tick"]                       -> une fois par seconde (fn_mapOverlayUpdate) : texte réécrit seulement s'il a changé,
                                        aucun redessin de la carte
      ["toggle", [page]]             -> épingle ou retire une app ("none" : retire tout ; quatrième app : remplace la troisième)
                                        et redessine la page ouverte
      ["refresh"]                    -> redessine la carte ou les Réglages s'ils sont ouverts (réglage changé dans le menu CBA)
      ["rows"]                       -> ligne de choix du panneau SITUATION et de Réglages > Carte (fn_formRender) : les apps
                                        que le joueur peut ouvrir, plus celles déjà épinglées (pour pouvoir les retirer)
    Aucune notification : le bandeau est dans le téléphone, rien n'apparaît dans l'interface d'Arma.
*/
params [["_act", "apps"], ["_a", []]];
disableSerialization;
// Emplacements : le réglage de la 4.16.0 reste le premier (le choix déjà enregistré chez les joueurs est gardé).
private _vars = ["comspec_atak_native_map_pinned_app", "comspec_atak_native_map_pinned_app_2", "comspec_atak_native_map_pinned_app_3"];
// Table unique des apps épinglables : [page ouverte au toucher, libellé du bandeau et du bouton de choix]. Mêmes clés, dans le
// même ordre, que les listes des trois réglages (XEH_preInit.sqf).
private _apps = [["CHAT", "MESSAGES"], ["SMS", "SMS"], ["TASK", "ORDRES"], ["WAYPOINTS", "NAV"], ["FIRES", "FEUX"], ["MEDICAL", "MEDEVAC"], ["ALERTS", "ALERTES"], ["GARMIN", "HEURE H"]];
private _keys = _apps apply { _x select 0 };
private _tr = { _this call comspec_atak_native_fnc_tr };
// Couleurs des bandeaux : constantes nommées, encres tirées de fn_theme (famille « map… », claires dans les trois thèmes).
// AtakMapPinsPanelAssetTest en calcule le contraste WCAG (4,5 au moins) sur le fond le plus défavorable : le fond à son
// opacité plancher posé sur une carte blanche, et le fond seul (carte noire).
private _PIN_BG = [0.02, 0.025, 0.022];        // fond sombre fixe, celui du bandeau des coordonnées
private _PIN_ALPHA_MIN = 0.86;                 // opacité plancher : un réglage « Fond : LÉGER » ne rend plus le texte illisible
private _PIN_INK_TEXT = "MapText";             // texte franc : expéditeur, contenu
private _PIN_INK_LABEL = "MapOk";              // libellé de l'app et bouton principal : encre d'accent des calques de carte
private _PIN_INK_EMPTY = "MapDim";             // état vide et heure : discret mais lisible
private _PIN_INK_BTN = [0.03, 0.05, 0.04, 1];  // texte du bouton principal, posé sur l'encre d'accent
private _PIN_BTN_BG = [0.16, 0.19, 0.17, 1];   // fond des autres boutons, texte en encre franche
private _PIN_EDGE = "mapCardEdge";             // liseré : le bandeau se détache aussi d'une carte sombre
private _PIN_ACT_FRAC = 0.55;                  // part de la case que les boutons du bandeau ORDRES peuvent prendre
// Ordres que le bandeau ORDRES propose de valider : ceux qui ne sont pas clos, triés comme dans l'app Ordres (fn_pageTasks :
// à traiter d'abord, puis priorité, puis le plus récent). -> [ids, rang de l'ordre montré, ordre montré (HashMap)].
// L'ordre montré est gardé par son identifiant (uiNamespace COMSPEC_ATAK_PinOrderId) : la liste peut changer dessous.
private _orderState = {
    private _all = [] call comspec_atak_native_fnc_tasksAll;
    private _sorted = [];
    {
        private _st = _y getOrDefault ["status", "NEW"];
        if !(_st isEqualType "") then { _st = str _st; };
        _st = toUpper _st;
        if (_st in ["FAILED", "DONE", "COMPLETED", "SUCCEEDED", "CANCELLED", "CANCELED"]) then { continue };
        private _stamp = _y getOrDefault ["stamp", 0];
        if !(_stamp isEqualType 0) then { _stamp = 0; };
        private _pr = _y getOrDefault ["priority", "NORMAL"];
        if !(_pr isEqualType "") then { _pr = str _pr; };
        private _pi = ["FLASH", "URGENT", "HIGH", "PRIORITY", "IMPORTANT"] find (toUpper _pr);
        _sorted pushBack [parseNumber (_st in ["ACK", "EXEC"]), [_pi min 2, 3] select (_pi < 0), -_stamp, str _x, _x];
    } forEach _all;
    _sorted sort true;
    private _ids = _sorted apply { _x select 4 };
    private _idx = (_ids find (uiNamespace getVariable ["COMSPEC_ATAK_PinOrderId", ""])) max 0;
    [_ids, _idx, if (_ids isEqualTo []) then { createHashMap } else { _all getOrDefault [_ids select _idx, createHashMap] }]
};
// Texte sans balise ni retour à la ligne, coupé à _n caractères.
private _clip = {
    params ["_t", "_n"];
    if !(_t isEqualType "") then { _t = str _t; };
    _t = ((_t splitString (toString [10, 13])) joinString " ") regexReplace ["[<>&]", ""];
    // forceUnicode 1 : la commande suivante compte en caractères, pas en octets (accents, chinois), sans rien changer ensuite.
    forceUnicode 1;
    private _len = count _t;
    if (_len > _n) then { forceUnicode 1; _t = (_t select [0, (_n - 1) max 1]) + "…"; };
    _t
};
// Écrit un texte structuré seulement s'il a changé.
private _put = {
    params ["_c", "_txt"];
    if (isNull _c || {(_c getVariable ["txt", ""]) isEqualTo _txt}) exitWith {};
    _c setVariable ["txt", _txt];
    if (missionNamespace getVariable ["COMSPEC_ATAK_FontAlt", false]) then { _txt = _txt call comspec_atak_native_fnc_fontText; };
    _c ctrlSetStructuredText parseText _txt; // i18n:brut (libellés déjà traduits)
};
// Résumé d'une app : [ligne 1, ligne 2] en texte structuré, _maxc = nombre de caractères de la ligne 2. Compact : tout sur
// la ligne 1 (libellé, état, puis ce qui tient), ligne 2 vide ; _key : touche écrite à droite du bandeau ORDRES (téléphone porté).
private _summary = {
    params ["_page", "_maxc", ["_compact", false], ["_key", ""]];
    // Bandeau ORDRES : « 1/3 » devant l'état quand plusieurs ordres attendent ; _todo : un ordre peut recevoir une réponse.
    private _pre = "";
    private _todo = false;
    private _th = COMSPEC_ATAK_Th;
    private _data = uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap];
    private _label = (_apps select ((_keys find _page) max 0)) select 1;
    private _n = 0;
    // Mini-apps : valeur en grand de la ligne 1 (vide : nombre de non lus), sa couleur, et le texte quand il n'y a rien.
    private _big = "";
    private _tone = "hexMapWarn";
    private _zero = "";
    private _none = "";
    private _who = "";
    private _when = "";
    private _what = "";
    // « 840 m · 317° NO » depuis moi ; arrondi à 10 m au-delà de 100 m (le texte n'est pas réécrit à chaque pas).
    private _to = {
        private _d = player distance2D _this;
        if (_d > 100) then { _d = (round (_d / 10)) * 10; };
        private _az = player getDir _this;
        format ["%1 %2", ["dist", _d, _az] call comspec_atak_native_fnc_mapFmt, [_az, 8] call comspec_atak_native_fnc_cardinal]
    };
    switch (_page) do {
        case "CHAT": {
            // Canaux seulement : les SMS ont leur propre bandeau (le badge de l'app Messages les compte aussi).
            _n = ((["CHAT"] call comspec_atak_native_fnc_appBadge) - (["SMS"] call comspec_atak_native_fnc_appBadge)) max 0;
            _none = "Aucun message";
            private _all = [""] call comspec_atak_native_fnc_messagesAll;
            if ((count _all) > 0) then {
                private _m = _all select -1;
                _who = [[("Moi") call _tr, _m getOrDefault ["author", ""]] select (!(_m getOrDefault ["mine", false])), 12] call _clip;
                _when = _m getOrDefault ["time", ""];
                _what = _m getOrDefault ["body", ""];
            };
        };
        case "SMS": {
            _n = ["SMS"] call comspec_atak_native_fnc_appBadge;
            _none = "Aucun SMS";
            private _p = _data getOrDefault ["p2p", []];
            if ((count _p) > 0) then {
                private _m = _p select -1;
                _who = [(["→ ", ""] select ((_m getOrDefault ["dir", ""]) isEqualTo "in")) + (_m getOrDefault ["peer", ""]), 12] call _clip;
                _when = _m getOrDefault ["time", ""];
                _what = _m getOrDefault ["body", ""];
            };
        };
        case "WAYPOINTS": {
            // Étape active de l'itinéraire (fn_wpAction), que la navigation soit lancée ou non.
            _none = "Aucun point de passage";
            _zero = "—";
            private _w = missionNamespace getVariable ["COMSPEC_ATAK_Waypoints", createHashMap];
            private _pts = _w getOrDefault ["pts", []];
            if ((count _pts) > 0) then {
                private _i = ((_w getOrDefault ["idx", 0]) max 0) min ((count _pts) - 1);
                (_pts select _i) params [["_pos", [0, 0, 0]], ["_name", ""]];
                _big = [_name, format ["WP %1", _i + 1]] select (_name isEqualTo "");
                _tone = "hexMapText";
                _who = _pos call _to;
                _what = format ["%1/%2", _i + 1, count _pts];
            };
        };
        case "FIRES": {
            // Mission de tir la plus récente encore en cours (appui feu typé, fn_fireSupport, publiée au camp).
            _none = "Aucune mission de tir";
            _zero = "—";
            private _best = createHashMap;
            {
                if (!((_x getOrDefault ["state", ""]) in ["EOM", "FAIL"]) && {(_x getOrDefault ["at", 0]) >= (_best getOrDefault ["at", -1])}) then { _best = _x; };
            } forEach (values (missionNamespace getVariable ["COMSPEC_ATAK_FsMsns", createHashMap]));
            if ((count _best) > 0) then {
                // Mêmes libellés et mêmes tons que les missions de tir de l'app Tâches (fn_fireSupport « rows »).
                private _st = _best getOrDefault ["state", "NEW"];
                _big = toUpper ((createHashMapFromArray [["NEW", "transmise"], ["ACK", "reçue"], ["EXEC", "en préparation"], ["SHOT", "coups partis"], ["SPLASH", "splash"], ["DONE", "tir fini"]] getOrDefault [_st, _st]) call _tr);
                _tone = switch (true) do {
                    case (_st in ["SHOT", "SPLASH"]): { "hexMapAlert" };
                    case (_st isEqualTo "DONE"): { "hexMapOk" };
                    case (_st isEqualTo "NEW"): { "hexMapText" };
                    default { "hexMapWarn" };
                };
                // Danger close : écrit en tête de la ligne 2, la pièce passe derrière.
                private _gun = (_best getOrDefault ["gunName", "Appui"]) call _tr;
                private _dc = _best getOrDefault ["dcClose", false];
                _who = [[_gun, "DANGER CLOSE"] select _dc, 12] call _clip;
                private _ammo = ["HE", "FUM", "ÉCL"] select ((["HE", "SMOKE", "ILLUM"] find (_best getOrDefault ["ammo", "HE"])) max 0);
                _what = (([[], [_gun]] select _dc) + [_best getOrDefault ["grid", ""], format ["%1 x%2", _ammo call _tr, _best getOrDefault ["rounds", 1]],
                    format [("de %1") call _tr, _best getOrDefault ["by", ""]]]) joinString " · ";
            } else {
                // Appui feu typé décoché : l'appel de feu générique de l'app CAS n'a que son état local (fn_firesState).
                private _f = uiNamespace getVariable ["COMSPEC_ATAK_Fires", createHashMap];
                if (!(missionNamespace getVariable ["comspec_atak_native_fs_enabled", true]) && {_f getOrDefault ["active", false]}) then {
                    _big = ("EN COURS") call _tr;
                    _who = ("CIBLE") call _tr;
                    _what = _f getOrDefault ["activeGrid", ""];
                };
            };
        };
        case "MEDICAL": {
            // Demande MEDEVAC encore ouverte (fn_medicalAction ; annulée ou terminée : status 4) : la mienne d'abord, sinon la
            // plus récente d'après son heure. L'identifiant, unique, empêche le tri d'aller comparer les demandes elles-mêmes.
            _none = "Aucune demande en cours";
            _zero = "—";
            private _uid = getPlayerUID player;
            private _open = ((values (missionNamespace getVariable ["COMSPEC_ATAK_MedevacReqs", createHashMap])) select { (_x getOrDefault ["status", 4]) < 4 }) apply {
                [parseNumber ((_x getOrDefault ["uid", ""]) isEqualTo _uid), _x getOrDefault ["time", ""], _x getOrDefault ["id", ""], _x]
            };
            if (_open isNotEqualTo []) then {
                _open sort false;
                private _r = (_open select 0) select 3;
                _big = (["DEMANDÉE", "ACCEPTÉE", "EN VOL", "SUR ZONE"] select ((_r get "status") max 0)) call _tr;
                _who = [_r getOrDefault ["from", ""], 12] call _clip;
                _what = format ["%1 · LZ %2 · %3 · %4", (_r getOrDefault ["pos", getPosASL player]) call _to, _r getOrDefault ["grid", ""], _r getOrDefault ["prio", ""], _r getOrDefault ["time", ""]];
            };
        };
        case "ALERTS": {
            // Dernière alerte du journal de l'app Alertes (fn_alertsLog : alertes du camp, les miennes, Athena, compromission).
            _none = "Aucune alerte";
            _zero = "—";
            private _log = missionNamespace getVariable ["COMSPEC_ATAK_AlertLog", []];
            if ((count _log) > 0) then {
                (_log select -1) params [["_type", ""], ["_from", ""], ["_pos", []], ["_grid", ""], ["_text", ""], ["_hour", ""]];
                // Mêmes libellés que le journal (fn_pageAlerts) ; rouge : détresse, vert : fin de contact, orange : le reste.
                (createHashMapFromArray [["PANIC", ["PANIQUE", "hexMapAlert"]], ["TIC", ["CONTACT", "hexMapWarn"]], ["TIC_CLEAR", ["FIN DE CONTACT", "hexMapOk"]],
                    ["EAGLE_DOWN", ["APPAREIL ABATTU", "hexMapAlert"]], ["COMPROMISE", ["COMPROMISSION", "hexMapWarn"]],
                    ["TOC_INFO", ["ALERTE DU TOC", "hexMapWarn"]], ["TOC_ALERT", ["ALERTE DU TOC", "hexMapWarn"]], ["TOC_URGENT", ["ALERTE DU TOC", "hexMapAlert"]], ["TOC_TRACE", ["SUIVI PAR LE TOC", "hexMapWarn"]]] getOrDefault [_type, [_type, "hexMapWarn"]]) params ["_t", "_c"];
                _big = _t call _tr;
                _tone = _c;
                _who = [[_from, ("Moi") call _tr] select (_from isEqualTo "Moi"), 12] call _clip;
                private _parts = [_grid, _hour, _text call _tr];
                if (_pos isEqualType [] && {(count _pos) >= 2}) then { _parts = [_pos call _to] + _parts; };
                _what = (_parts select { _x isEqualType "" && {_x isNotEqualTo ""} }) joinString " · ";
            };
        };
        case "GARMIN": {
            // Heure H du groupe (fn_watch « hhRecv » : enregistrée chez chaque membre, avec ou sans montre), même base de temps
            // et même écriture « H-mm:ss » que la montre. Sans heure H : le chrono ou le minuteur de la montre s'il tourne.
            _none = "Aucune heure H";
            _zero = "—";
            private _m = missionNamespace getVariable ["COMSPEC_ATAK_WatchM", createHashMap];
            private _hh = _m getOrDefault ["hh", []];
            private _left = if (_hh isEqualTo []) then { 0 } else { ceil ((_hh select 0) - ([time, serverTime] select isMultiplayer)) };
            private _mmss = { format ["%1:%2", [floor (_this / 60), 2] call CBA_fnc_formatNumber, [floor (_this mod 60), 2] call CBA_fnc_formatNumber] };
            if (_left > 0) then {
                _big = "H-" + (_left call _mmss);
                if (_left > 60) then { _tone = "hexMapText"; };
                _who = [format [("Envoyée par %1") call _tr, _hh select 1], 26] call _clip;
            } else {
                _tone = "hexMapText";
                if (_m getOrDefault ["run", false]) then {
                    _label = "CHRONO";
                    _big = ((_m getOrDefault ["acc", 0]) + diag_tickTime - (_m getOrDefault ["t0", diag_tickTime])) call _mmss;
                } else {
                    if ((_m getOrDefault ["tend", -1]) >= 0) then {
                        _label = "MINUTEUR";
                        _big = (((_m get "tend") - diag_tickTime) max 0) call _mmss;
                    };
                };
            };
        };
        default {
            _n = ["TASK"] call comspec_atak_native_fnc_appBadge;
            _none = "Aucun ordre";
            // Dernier ordre à traiter (le plus récent) ; sinon le dernier reçu.
            // Même liste que le nombre (fn_appBadge "TASK"), plus SUCCEEDED : le nombre et l'ordre montré parlent de la même chose.
            private _closed = ["ACK", "EXEC", "FAILED", "DONE", "COMPLETED", "SUCCEEDED", "CANCELLED", "CANCELED"];
            private _best = createHashMap;
            private _bestStamp = -1e12;
            private _bestOpen = false;
            {
                private _stamp = _y getOrDefault ["stamp", 0];
                if !(_stamp isEqualType 0) then { _stamp = 0; };
                private _open = !((toUpper (_y getOrDefault ["status", "NEW"])) in _closed);
                if ((_open && {!_bestOpen}) || {(_open isEqualTo _bestOpen) && {_stamp >= _bestStamp}}) then { _best = _y; _bestStamp = _stamp; _bestOpen = _open; };
            } forEach ([] call comspec_atak_native_fnc_tasksAll);
            // Ordre à traiter ou en cours : c'est lui que le bandeau montre et propose de valider, avec son rang.
            (call _orderState) params ["_ids", "_idx", "_cur"];
            if ((count _ids) > 0) then {
                _best = _cur;
                _todo = true;
                private _st = _cur getOrDefault ["status", "NEW"];
                if !(_st isEqualType "") then { _st = str _st; };
                _st = toUpper _st;
                if ((count _ids) > 1) then { _pre = format ["%1/%2 ", _idx + 1, count _ids]; };
                // Mêmes mots que les boutons de l'app Ordres.
                if (_st in ["ACK", "EXEC"]) then { _big = (["REÇU", "EN COURS"] select (_st isEqualTo "EXEC")) call _tr; };
            };
            if ((count _best) > 0) then {
                _when = _best getOrDefault ["time", ""];
                if !(_when isEqualType "") then { _when = ""; };
                _who = [_best getOrDefault ["issuer", "TOC"], 12] call _clip;
                private _t = _best getOrDefault ["typeLabel", ""];
                if (_t isEqualTo "") then { _t = _best getOrDefault ["type", ""]; };
                private _pl = _best getOrDefault ["payload", ""];
                if !(_pl isEqualType "") then { _pl = str _pl; };
                _what = ([_t, _pl] select { _x isNotEqualTo "" }) joinString " · ";
            };
        };
    };
    private _ink = _th get ("hex" + _PIN_INK_TEXT);
    private _ok = _th get ("hex" + _PIN_INK_LABEL);
    private _dim = _th get ("hex" + _PIN_INK_EMPTY);
    // Ligne 1 : libellé en encre d'accent puis le nombre de non lus (ou à traiter) en grand, orange s'il y en a ; étroit : le nombre seul.
    private _narrow = _maxc < 22;
    _label = _label call _tr;
    // Compact (petit format, ou pile trop haute) : une ligne, « ORDRES 1/2 2 à traiter TOC 14:32 · FRAGO … », coupée proprement.
    if (_compact) exitWith {
        private _state = _big;
        if (_state isEqualTo "" && {_n > 0}) then { _state = [str _n, format [("%1 à traiter") call _tr, _n]] select (_page isEqualTo "TASK" && {_maxc >= 30}); };
        _state = _pre + _state;
        private _has = _state isNotEqualTo "";
        if (!_has) then { _state = [_none call _tr, [_zero, "0"] select (_zero isEqualTo "")] select (_who isNotEqualTo "" || {_what isNotEqualTo ""}); };
        // Touche du téléphone porté (bandeau ORDRES, un ordre attend) : à droite, toujours entière.
        private _keyTip = ["", [format ["[%1]", _key], format [("%1 : répondre") call _tr, _key]] select (_maxc >= 34)] select (_key isNotEqualTo "" && {_todo});
        forceUnicode 1;
        private _left = _maxc - (count _label) - 1 - ([0, (count _keyTip) + 1] select (_keyTip isNotEqualTo ""));
        _state = [_state, _left max 3] call _clip;
        forceUnicode 1;
        _left = _left - (count _state) - 1;
        private _rest = ([_who, [_when, 5] call _clip, _what] select { _x isNotEqualTo "" }) joinString " · ";
        private _line = format ["<t size='0.78' color='%1'>%2</t> <t font='EtelkaMonospaceProBold' color='%3'>%4</t>", _ok, _label, [_dim, _th get _tone] select _has, _state];
        if (_rest isNotEqualTo "" && {_left >= 6}) then { _line = _line + format [" <t font='EtelkaMonospacePro' color='%1'>%2</t>", _ink, [_rest, _left] call _clip]; };
        if (_keyTip isNotEqualTo "") then { _line = _line + format ["<t align='right' font='EtelkaMonospaceProBold' color='%1'>%2</t>", _th get "hexMapWarn", _keyTip]; };
        [_line, ""]
    };
    // Valeur d'une mini-app : coupée à la place qui reste sur la ligne 1 (libellé en 0,78, valeur en 1,2).
    if (_big isNotEqualTo "") then { _big = [_big, (floor ((_maxc * 0.9 - (count _label) * 0.6 - 1) / 1.2)) max 4] call _clip; };
    if (_big isEqualTo "" && {_n > 0}) then {
        private _tpl = [("%1 non lu(s)") call _tr, ("%1 à traiter") call _tr] select (_page isEqualTo "TASK");
        _big = [format [_tpl, _n], str _n] select _narrow;
    };
    // Plusieurs ordres : le rang de celui qui est montré, devant l'état (étroit : le rang seul).
    if (_pre isNotEqualTo "") then { _big = [_pre + _big, trim _pre] select (_narrow || {_big isEqualTo ""}); };
    private _count = if (_big isNotEqualTo "") then {
        format ["<t font='EtelkaMonospaceProBold' size='1.2' color='%1'>%2</t>", _th get _tone, _big]
    } else {
        if (_zero isEqualTo "") then { _zero = [[("Aucun non lu") call _tr, ("Rien à traiter") call _tr] select (_page isEqualTo "TASK"), "0"] select _narrow; };
        format ["<t font='EtelkaMonospacePro' size='0.95' color='%1'>%2</t>", _dim, _zero]
    };
    private _l1 = format ["<t size='0.78' color='%1'>%2</t> %3", _ok, _label, _count];
    private _l2 = "";
    if (_who isEqualTo "" && {_what isEqualTo ""}) then {
        _l2 = format ["<t font='EtelkaMonospacePro' color='%1'>%2</t>", _dim, _none call _tr];
    } else {
        private _head = format ["<t font='EtelkaMonospaceProBold' color='%1'>%2</t>", _ink, _who];
        if (_when isNotEqualTo "") then { _head = _head + format [" <t font='EtelkaMonospacePro' color='%1'>%2</t>", _dim, [_when, 5] call _clip]; };
        private _used = (count _who) + ([0, 6] select (_when isNotEqualTo "")) + 3;
        _l2 = _head;
        // Contenu en encre franche (avant : gris, peu lisible sur une carte) ; seule l'heure reste discrète.
        if (_what isNotEqualTo "" && {_maxc - _used >= 4}) then {
            _l2 = _l2 + format ["<t font='EtelkaMonospacePro' color='%1'> · %2</t>", _ink, [_what, _maxc - _used] call _clip];
        };
    };
    [_l1, format ["<t size='0.9'>%1</t>", _l2]]
};
// Bandeau ORDRES : « VU » envoyé une fois (comme à l'affichage de l'ordre dans l'app, fn_orderAck « seen »), puis les boutons
// de réponse posés à droite de la case, sans redessiner la page : montrés, nommés et placés seulement quand l'ordre montré
// ou son statut change. Réponses proposées, mêmes mots et mêmes statuts que l'app Ordres : à traiter -> REÇU, IMPOSSIBLE ;
// reçu -> EN COURS, TERMINÉ ; en cours -> TERMINÉ, IMPOSSIBLE. La première toujours, puis « › » (ordre suivant, s'il y en a
// plusieurs), puis la seconde, tant que les boutons tiennent dans _PIN_ACT_FRAC de la case. -> caractères laissés au texte.
private _orderUi = {
    params ["_pin"];
    _pin params ["", "", "", "_maxc", ["_compact", false], ["_ex", createHashMap]];
    (call _orderState) params ["_ids", "_idx", "_cur"];
    private _any = (count _ids) > 0;
    private _id = if (_any) then { _ids select _idx } else { "" };
    if (_any) then { ["seen", [_id]] call comspec_atak_native_fnc_orderAck; };
    private _btns = _ex getOrDefault ["btns", []];
    // Téléphone porté : pas de bouton (pas de clic), la touche est écrite par _summary.
    if ((count _btns) < 3) exitWith { _maxc };
    private _st = _cur getOrDefault ["status", "NEW"];
    if !(_st isEqualType "") then { _st = str _st; };
    _st = toUpper _st;
    private _acts = switch (true) do {
        case (!_any): { [] };
        case (_st isEqualTo "ACK"): { [["EXEC", "EN COURS"], ["DONE", "TERMINÉ"]] };
        case (_st isEqualTo "EXEC"): { [["DONE", "TERMINÉ"], ["FAILED", "IMPOSSIBLE"]] };
        default { [["ACK", "REÇU"], ["FAILED", "IMPOSSIBLE"]] };
    };
    (_ex getOrDefault ["geo", [0, 0, 0.01, 0, 0.01]]) params ["_cx", "_cw", "_chW", "_gapB", "_charW"];
    private _budget = _cw * _PIN_ACT_FRAC;
    private _used = 0;
    // Emplacements, de gauche à droite : réponse principale, seconde réponse, ordre suivant.
    private _bw = [0, 0, 0];
    private _lab = ["", "", ""];
    private _do = [[], [], []];
    private _try = {
        params ["_i", "_label", "_action"];
        forceUnicode 1;
        private _need = ((count _label) + 2) * _chW * 0.9;
        if (_used + _need + _gapB <= _budget) then { _bw set [_i, _need]; _lab set [_i, _label]; _do set [_i, _action]; _used = _used + _need + _gapB; };
    };
    if ((count _acts) > 0) then {
        [0, ((_acts select 0) select 1) call _tr, [_id, (_acts select 0) select 0]] call _try;
        if ((_bw select 0) > 0) then {
            if ((count _ids) > 1) then { [2, "›", ["", "NEXT"]] call _try; };
            [1, ((_acts select 1) select 1) call _tr, [_id, (_acts select 1) select 0]] call _try;
        };
    };
    private _sig = [_lab, _do];
    if ((_ex getOrDefault ["sig", []]) isNotEqualTo _sig) then {
        _ex set ["sig", _sig];
        private _bx = _cx + _cw - _used + _gapB;
        {
            private _w1 = _bw select _forEachIndex;
            if (isNull _x) then { continue };
            if (_w1 > 0) then {
                // Abscisse et largeur seulement : l'ordonnée suit le bandeau des coordonnées (fn_mapOverlayUpdate « bandFollow »).
                _x ctrlSetPositionX _bx;
                _x ctrlSetPositionW _w1;
                _x ctrlSetText (_lab select _forEachIndex); // i18n:brut (libellé déjà traduit)
                _x setVariable ["act", _do select _forEachIndex];
                _x ctrlShow true;
                _x ctrlCommit 0;
                _bx = _bx + _w1 + _gapB;
            } else {
                _x setVariable ["act", []];
                _x ctrlShow false;
            };
        } forEach _btns;
        // Le texte s'arrête avant les boutons.
        {
            _x params ["_c", "_w0"];
            if (!isNull _c) then { _c ctrlSetPositionW ((_w0 - _used) max (_chW * 4)); _c ctrlCommit 0; };
        } forEach (_ex getOrDefault ["txt", []]);
    };
    floor (((_cw - _used) max 0) / (_charW max 0.0001))
};
// Remplit les deux lignes (ou ne fait rien si elles sont déjà à jour).
private _fill = {
    params ["_pin"];
    _pin params ["_c1", "_c2", "_page", "_maxc", ["_compact", false], ["_ex", createHashMap]];
    if (isNull _c1) exitWith {};
    if (_page isEqualTo "TASK") then { _maxc = [_pin] call _orderUi; };
    ([_page, _maxc, _compact, _ex getOrDefault ["key", ""]] call _summary) params ["_h1", "_h2"];
    [_c1, _h1] call _put;
    [_c2, _h2] call _put;
};
// Pages épinglées d'après les réglages du joueur, dans l'ordre des emplacements, sans doublon ni valeur inconnue.
private _sel = [];
{ private _v = missionNamespace getVariable [_x, "none"]; if (_v in _keys) then { _sel pushBackUnique _v; }; } forEach _vars;
// Règle de l'encadrement (fn_appInstall) : une app interdite, retirée ou désinstallée n'a pas de bandeau ; masquée par le mode
// Essentiel (4.18.0, fn_essentialMode), non plus : le choix est gardé, le bandeau revient avec l'app. [page, liste de fn_appList].
private _usable = {
    params ["_page", "_list"];
    private _app = (_list select { (_x get "page") isEqualTo _page }) param [0, createHashMap];
    (count _app) > 0 && {["installed", _app get "id"] call comspec_atak_native_fnc_appInstall} && {!(["hides", _app get "id"] call comspec_atak_native_fnc_essentialMode)}
};
// Explication du choix (Réglages > Carte, et volet SITUATION quand la ligne est dépliée).
private _pinHelp = "Trois au plus : un bandeau par app, aligné sur celui des coordonnées (non lus, étape de l'itinéraire, mission de tir, MEDEVAC, dernière alerte, heure H). Touchez-le pour ouvrir l'app. Ordres : répondez depuis le bandeau (REÇU, EN COURS, TERMINÉ, IMPOSSIBLE). En petit format : une ligne par app.";

switch (_act) do {
    case "apps": {
        if (_sel isEqualTo []) exitWith { [] };
        private _list = [] call comspec_atak_native_fnc_appList;
        _sel select { [_x, _list] call _usable }
    };
    case "height": {
        ["height", _a] call comspec_atak_native_fnc_mapBandGrid
    };
    case "build": {
        _a params ["_ov", "_mk0", "_rect", "_m", "_inter", "_pg", "_alpha", ["_compact", false]];
        _rect params ["_x0", "_y0", "_w", "_h"];
        _m params ["_fs", "_in", "_cellIn", "_chW", "_ratio"];
        private _th = COMSPEC_ATAK_Th;
        private _K = ["const"] call comspec_atak_native_fnc_mapBandGrid;
        // Tous les contrôles du bandeau sont gardés : ils suivent le bandeau des coordonnées quand sa hauteur change.
        private _made = [];
        private _mk = { private _c = _this call _mk0; _made pushBack _c; _c };
        private _bg = ["COMSPEC_RscText", [_x0, _y0, _w, _h]] call _mk;
        _bg ctrlSetBackgroundColor (_PIN_BG + [_alpha max _PIN_ALPHA_MIN]);
        // Liseré : filet en haut, en bas et à droite ; à gauche, le trait d'accent du bandeau des coordonnées.
        {
            private _ln = ["COMSPEC_RscText", _x] call _mk;
            _ln ctrlSetBackgroundColor (_th get _PIN_EDGE);
        } forEach [[_x0, _y0, _w, pixelH], [_x0, _y0 + _h - pixelH, _w, pixelH], [_x0 + _w - pixelW, _y0, pixelW, _h]];
        private _edge = ["COMSPEC_RscText", [_x0, _y0, pixelW * 3, _h]] call _mk;
        _edge ctrlSetBackgroundColor (((_th get "accent") select [0, 3]) + [0.9]);
        // Mêmes lignes que le bandeau des coordonnées (fn_mapBandGrid) : ligne 1 puis ligne 2, ou une seule en compact.
        private _l1H = _fs * (_K get "line1");
        private _l2H = _fs * (_K get "line2");
        private _cx = _x0 + pixelW * 3 + _cellIn;
        private _cw = _w - pixelW * 3 - _cellIn * 1.6;
        private _txt = {
            params ["_y", "_hh", "_fh"];
            private _t = ["COMSPEC_RscStructuredText", [_cx, _y, _cw, _hh]] call _mk;
            _t ctrlSetFontHeight (_fh max COMSPEC_ATAK_FontMin);
            _t ctrlEnable false;
            _t
        };
        private _c1 = controlNull;
        private _c2 = controlNull;
        if (_compact) then {
            _c1 = [_y0 + _in * (_K get "padVCompact") / 2, _fs * (_K get "lineCompact"), _fs] call _txt;
        } else {
            _c1 = [_y0 + _in * (_K get "padV") / 2, _l1H, _fs] call _txt;
            _c2 = [_y0 + _in * (_K get "padV") / 2 + _l1H, _l2H, _fs * 0.9] call _txt;
        };
        // Caractères de la ligne 2 (chasse fixe, taille 0,9), ou de la ligne unique ; la ligne 1 passe au nombre seul sous 22.
        private _charW = [_chW * 0.9, _chW] select _compact;
        private _maxc = floor (_cw / _charW);
        private _ex = createHashMapFromArray [["txt", [[_c1, _cw], [_c2, _cw]]]];
        private _pin = [_c1, _c2, _pg, _maxc, _compact, _ex];
        _ov set ["pins", (_ov getOrDefault ["pins", []]) + [_pin]];
        if (_inter) then {
            private _b = ["COMSPEC_RscButtonOverlay", [_x0, _y0, _w, _h]] call _mk;
            _b setVariable ["page", _pg];
            _b ctrlSetTooltip (("Ouvrir l'app épinglée") call _tr);
            _b ctrlAddEventHandler ["ButtonClick", { params ["_c"]; [_c getVariable ["page", "CHAT"]] call comspec_atak_native_fnc_navigate; }];
        };
        if (_pg isEqualTo "TASK") then {
            if (_inter) then {
                // Boutons de réponse, créés après le bouton d'ouverture (donc au-dessus de lui) et cachés : _orderUi les montre.
                // Hors des boutons, un toucher ouvre l'app comme avant. Hauteur : presque toute la case (cible de toucher).
                private _bm = [_in * 0.4, pixelH * 2] select _compact;
                private _btns = [0, 1, 2] apply {
                    private _b = ["COMSPEC_RscButton", [_cx, _y0 + _bm, _chW * 4, _h - _bm * 2]] call _mk;
                    _b ctrlSetFontHeight ((_fs * 0.9) max COMSPEC_ATAK_FontMin);
                    _b ctrlSetBackgroundColor ([_th get ("map" + _PIN_INK_LABEL), _PIN_BTN_BG] select (_x > 0));
                    _b ctrlSetTextColor ([_PIN_INK_BTN, _th get ("map" + _PIN_INK_TEXT)] select (_x > 0));
                    _b ctrlSetTooltip ((["Répondre à cet ordre sans ouvrir l'app", "Ordre suivant"] select (_x isEqualTo 2)) call _tr);
                    _b ctrlShow false;
                    // Réponse à l'image suivante : le clic ne modifie pas son propre bouton pendant son gestionnaire.
                    _b ctrlAddEventHandler ["ButtonClick", {
                        params ["_c"];
                        private _do = _c getVariable ["act", []];
                        if (_do isEqualTo []) exitWith {};
                        [{ ["act", _this] call comspec_atak_native_fnc_mapPinnedApp; }, _do] call comspec_atak_native_fnc_nextFrame;
                    }];
                    _b
                };
                _ex set ["btns", _btns];
                _ex set ["geo", [_cx, _cw, _chW, _chW * 0.5, _charW]];
            } else {
                // Téléphone porté : pas de clic. La touche existante « Interagir avec la miniature » (PhoneMiniUse) rend la
                // miniature cliquable, boutons compris ; son nom est écrit à droite du bandeau quand un ordre attend.
                private _kb = (["get", "PhoneMiniUse"] call comspec_atak_native_fnc_keybinds) param [0, []];
                if (_kb isNotEqualTo []) then { _ex set ["key", ["name", _kb] call comspec_atak_native_fnc_keybinds]; };
            };
        };
        _ov set ["bandFollow", (_ov getOrDefault ["bandFollow", []]) + _made];
        [_pin] call _fill;
        true
    };
    case "tick": {
        private _pins = (uiNamespace getVariable ["COMSPEC_ATAK_MapOverlay", createHashMap]) getOrDefault ["pins", []];
        if (_pins isEqualTo []) exitWith { false };
        private _t0 = diag_tickTime;
        { [_x] call _fill; } forEach _pins;
        ["carte : app épinglée", _t0] call comspec_atak_native_fnc_perfAcc;
        true
    };
    case "toggle": {
        // Déjà épinglée : retirée. Sinon ajoutée à la suite ; trois déjà posées : elle remplace la troisième. "none" : plus rien.
        private _page = _a param [0, "none"];
        private _new = if (_page in _sel) then { _sel - [_page] } else { [[], (_sel select [0, (count _vars) - 1]) + [_page]] select (_page in _keys) };
        {
            private _var = _x;
            private _v = _new param [_forEachIndex, "none"];
            // fn_setSetting : gardé dans le profil CBA (sinon le choix ne valait que pour la partie en cours), une seule
            // écriture du profil, regroupée, pour les trois emplacements.
            if ((missionNamespace getVariable [_var, "none"]) isNotEqualTo _v) then { [_var, _v] call comspec_atak_native_fnc_setSetting; };
        } forEach _vars;
        ["refresh"] call comspec_atak_native_fnc_mapPinnedApp;
        true
    };
    case "refresh": {
        // Un seul redessin par image : les trois réglages changés par un même toucher (ou validés dans le menu CBA) et la
        // fin de « toggle » préviennent dans la même image. Pas de délai : deux touchers rapprochés redessinent tous les deux.
        if ((uiNamespace getVariable ["COMSPEC_ATAK_PinRefreshAt", -1]) isEqualTo diag_frameNo) exitWith { false };
        uiNamespace setVariable ["COMSPEC_ATAK_PinRefreshAt", diag_frameNo];
        if (isNull ([] call comspec_atak_native_fnc_display)) exitWith { false };
        // Carte, ou Réglages > Carte (même ligne de choix) : la page ouverte est redessinée.
        private _open = (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""];
        if !(_open in ["MAP", "SETTINGS"]) exitWith { false };
        [{ [_this] call comspec_atak_native_fnc_pageRender; }, _open] call comspec_atak_native_fnc_nextFrame;
        true
    };
    case "rows": {
        // Boutons à bascule : chaque app épinglée est allumée, AUCUNE quand rien ne l'est. Une app interdite, retirée ou
        // désinstallée n'est pas proposée (elle n'aurait pas de bandeau) ; déjà épinglée, elle reste là pour être retirée,
        // avec l'explication en info-bulle.
        private _list = [] call comspec_atak_native_fnc_appList;
        private _ok = _keys select { [_x, _list] call _usable };
        [["segment", "Apps épinglées sur la carte", (([["none", "AUCUNE"]] + _apps) select { (_x select 0) isEqualTo "none" || {(_x select 0) in _ok} || {(_x select 0) in _sel} }) apply {
            _x params ["_v", "_t"];
            [_t, format ["['toggle', [%1]] call comspec_atak_native_fnc_mapPinnedApp;", str _v], [_v in _sel, _sel isEqualTo []] select (_v isEqualTo "none"),
                ["", ["App interdite ou désinstallée : pas de bandeau", "Masquée par le mode Essentiel : pas de bandeau"] select (["on"] call comspec_atak_native_fnc_essentialMode)] select (_v in _sel && {!(_v in _ok)})]
        }, _pinHelp]]
    };
    case "panelRows": {
        // Volet SITUATION de la carte : une ligne compacte « Apps épinglées · n/3 » ; dépliée, les boutons de choix puis
        // l'explication sur toute la largeur du volet (plus de long texte en colonne étroite).
        _a params [["_open", false], ["_code", {}]];
        private _rows = [["fold", "Apps épinglées", format ["%1/%2", count _sel, count _vars], _open, _code]];
        if (_open) then {
            private _seg = +((["rows"] call comspec_atak_native_fnc_mapPinnedApp) select 0);
            _seg set [1, ""];
            _seg set [3, ""];
            _rows pushBack _seg;
            _rows pushBack ["text", format ["<t size='0.8' color='%1'>%2</t>", COMSPEC_ATAK_Th get "hexTextDim", _pinHelp call _tr]];
        };
        _rows
    };
    case "act": {
        // Bouton du bandeau ORDRES. Réponse : exactement le chemin des boutons de l'app Ordres (fn_pageTasks) : l'ordre est
        // choisi puis fn_taskAction envoie le statut à Athena ou au TOC, le diffuse au camp (fn_orderAck) et l'écrit au journal.
        _a params [["_id", ""], ["_status", ""]];
        if (_status isEqualTo "NEXT") exitWith {
            (call _orderState) params ["_ids", "_idx"];
            if ((count _ids) > 1) then { uiNamespace setVariable ["COMSPEC_ATAK_PinOrderId", _ids select ((_idx + 1) mod (count _ids))]; };
            ["tick"] call comspec_atak_native_fnc_mapPinnedApp;
            true
        };
        private _task = ([] call comspec_atak_native_fnc_tasksAll) getOrDefault [_id, createHashMap];
        private _now = _task getOrDefault ["status", ""];
        if !(_now isEqualType "") then { _now = str _now; };
        // Jamais deux fois le même accusé : ordre disparu, statut inconnu ou déjà donné, rien n'est envoyé.
        if (!(_status in ["ACK", "EXEC", "DONE", "FAILED"]) || {(count _task) isEqualTo 0} || {(toUpper _now) isEqualTo _status}) exitWith { false };
        (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["selectedTask", _id];
        [_status] call comspec_atak_native_fnc_taskAction;
        // Reçu : le bandeau passe au prochain ordre à traiter s'il en reste ; sinon il garde celui-ci (EN COURS, TERMINÉ).
        if (_status isEqualTo "ACK" && {(["TASK"] call comspec_atak_native_fnc_appBadge) > 0}) then { uiNamespace setVariable ["COMSPEC_ATAK_PinOrderId", ""]; };
        ["tick"] call comspec_atak_native_fnc_mapPinnedApp;
        true
    };
    default { false };
};
