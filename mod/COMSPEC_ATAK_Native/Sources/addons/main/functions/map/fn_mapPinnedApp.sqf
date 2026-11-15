/*
    Apps épinglées sur la carte du téléphone (4.16.0 ; trois à la fois et mini-apps en 4.17.0) : un bandeau par app, de la
    taille et du style du bandeau des coordonnées, posé à côté (ou au-dessus, ou en dessous si la place manque), qui la résume.
    Messages, SMS, Ordres : non lus et dernier message ou ordre, avec l'expéditeur et l'heure. Mini-apps : NAV (étape active
    de l'itinéraire, distance et cap), FEUX (mission de tir en cours), MEDEVAC (étape de la demande, LZ), ALERTES (dernière
    alerte du journal), HEURE H (compte à rebours du groupe, sinon chrono ou minuteur de la montre). Un toucher ouvre l'app.
    Facultatif : rien n'est posé par défaut.
    Choix du joueur : réglages CBA « comspec_atak_native_map_pinned_app » (celui de la 4.16.0, premier emplacement), « …_2 »
    et « …_3 » (par joueur, gardés entre les sessions), aussi dans Réglages > Carte et dans le panneau SITUATION de la carte.
    Appels :
      ["apps"]                       -> pages épinglées, dans l'ordre des emplacements, sans doublon ([] : aucune) ; une app
                                        interdite, retirée ou désinstallée n'y est pas
      ["height", [taille du texte, marge]] -> hauteur du bandeau (deux lignes, comme le bandeau des coordonnées)
      ["build", [_ov, _mk, [x, y, w, h], [taille du texte, marge, marge des cases, largeur d'un caractère, ratio], interactif, page, opacité]]
                                     -> crée les contrôles (fn_pageMap) et les remplit
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
// Résumé d'une app : [ligne 1, ligne 2] en texte structuré, _maxc = nombre de caractères de la ligne 2.
private _summary = {
    params ["_page", "_maxc"];
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
            if ((count _best) > 0) then {
                _who = [_best getOrDefault ["issuer", "TOC"], 12] call _clip;
                private _t = _best getOrDefault ["typeLabel", ""];
                if (_t isEqualTo "") then { _t = _best getOrDefault ["type", ""]; };
                private _pl = _best getOrDefault ["payload", ""];
                if !(_pl isEqualType "") then { _pl = str _pl; };
                _what = ([_t, _pl] select { _x isNotEqualTo "" }) joinString " · ";
            };
        };
    };
    private _ok = _th get "hexMapOk";
    private _dim = _th get "hexMapDim";
    // Ligne 1 : libellé discret puis le nombre de non lus (ou à traiter) en grand, orange s'il y en a ; étroit : le nombre seul.
    private _narrow = _maxc < 22;
    _label = _label call _tr;
    // Valeur d'une mini-app : coupée à la place qui reste sur la ligne 1 (libellé en 0,78, valeur en 1,2).
    if (_big isNotEqualTo "") then { _big = [_big, (floor ((_maxc * 0.9 - (count _label) * 0.6 - 1) / 1.2)) max 4] call _clip; };
    if (_big isEqualTo "" && {_n > 0}) then {
        private _tpl = [("%1 non lu(s)") call _tr, ("%1 à traiter") call _tr] select (_page isEqualTo "TASK");
        _big = [format [_tpl, _n], str _n] select _narrow;
    };
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
        private _head = format ["<t font='EtelkaMonospaceProBold' color='%1'>%2</t>", _th get "hexMapText", _who];
        if (_when isNotEqualTo "") then { _head = _head + format [" <t font='EtelkaMonospacePro' color='%1'>%2</t>", _dim, [_when, 5] call _clip]; };
        private _used = (count _who) + ([0, 6] select (_when isNotEqualTo "")) + 3;
        _l2 = _head;
        if (_what isNotEqualTo "" && {_maxc - _used >= 4}) then {
            _l2 = _l2 + format ["<t font='EtelkaMonospacePro' color='%1'> · %2</t>", _dim, [_what, _maxc - _used] call _clip];
        };
    };
    [_l1, format ["<t size='0.9'>%1</t>", _l2]]
};
// Remplit les deux lignes (ou ne fait rien si elles sont déjà à jour).
private _fill = {
    params ["_pin"];
    _pin params ["_c1", "_c2", "_page", "_maxc"];
    if (isNull _c1) exitWith {};
    ([_page, _maxc] call _summary) params ["_h1", "_h2"];
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

switch (_act) do {
    case "apps": {
        if (_sel isEqualTo []) exitWith { [] };
        private _list = [] call comspec_atak_native_fnc_appList;
        _sel select { [_x, _list] call _usable }
    };
    case "height": {
        _a params ["_fs", "_in"];
        _fs * 1.45 + _fs * 1.12 + _in * 1.5
    };
    case "build": {
        _a params ["_ov", "_mk", "_rect", "_m", "_inter", "_pg", "_alpha"];
        _rect params ["_x0", "_y0", "_w", "_h"];
        _m params ["_fs", "_in", "_cellIn", "_chW", "_ratio"];
        private _bg = ["COMSPEC_RscText", [_x0, _y0, _w, _h]] call _mk;
        _bg ctrlSetBackgroundColor [0.02, 0.025, 0.022, _alpha];
        private _edge = ["COMSPEC_RscText", [_x0, _y0, pixelW * 3, _h]] call _mk;
        _edge ctrlSetBackgroundColor (((COMSPEC_ATAK_Th get "accent") select [0, 3]) + [0.9]);
        private _l1H = _fs * 1.45;
        private _l2H = _fs * 1.12;
        private _cx = _x0 + pixelW * 3 + _cellIn;
        private _cw = _w - pixelW * 3 - _cellIn * 1.6;
        private _txt = {
            params ["_y", "_hh", "_fh"];
            private _t = ["COMSPEC_RscStructuredText", [_cx, _y, _cw, _hh]] call _mk;
            _t ctrlSetFontHeight (_fh max COMSPEC_ATAK_FontMin);
            _t ctrlEnable false;
            _t
        };
        private _c1 = [_y0 + _in * 0.75, _l1H, _fs] call _txt;
        private _c2 = [_y0 + _in * 0.75 + _l1H, _l2H, _fs * 0.9] call _txt;
        // Caractères de la ligne 2 (chasse fixe, taille 0,9) ; la ligne 1 passe au nombre seul sous 22.
        private _maxc = floor (_cw / (_chW * 0.9));
        private _pin = [_c1, _c2, _pg, _maxc];
        _ov set ["pins", (_ov getOrDefault ["pins", []]) + [_pin]];
        if (_inter) then {
            private _b = ["COMSPEC_RscButtonOverlay", [_x0, _y0, _w, _h]] call _mk;
            _b setVariable ["page", _pg];
            _b ctrlSetTooltip (("Ouvrir l'app épinglée") call _tr);
            _b ctrlAddEventHandler ["ButtonClick", { params ["_c"]; [_c getVariable ["page", "CHAT"]] call comspec_atak_native_fnc_navigate; }];
        };
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
        }, "Trois au plus : un bandeau par app, de la taille de celui des coordonnées (non lus, étape de l'itinéraire, mission de tir, MEDEVAC, dernière alerte, heure H) ; touchez-le pour ouvrir l'app ; masquées en petit format ou si la carte est trop petite"]]
    };
    default { false };
};
