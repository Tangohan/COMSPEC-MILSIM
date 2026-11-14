/*
    App épinglée sur la carte du téléphone (4.16.0) : un bandeau de la taille et du style du bandeau des coordonnées, posé à
    côté (ou au-dessus, ou en dessous si la place manque), qui résume une app : Messages, SMS ou Ordres (non lus et dernier
    message ou ordre, avec l'expéditeur et l'heure). Un toucher ouvre l'app. Facultatif : rien n'est posé par défaut.
    Choix du joueur : réglage CBA « comspec_atak_native_map_pinned_app » (par joueur, gardé entre les sessions), aussi dans
    Réglages > Carte et dans le panneau SITUATION de la carte.
    Appels :
      ["app"]                        -> "" (aucune, ou app interdite, retirée ou désinstallée) ou la page épinglée : "CHAT", "SMS", "TASK"
      ["height", [taille du texte, marge]] -> hauteur du bandeau (deux lignes, comme le bandeau des coordonnées)
      ["build", [_ov, _mk, [x, y, w, h], [taille du texte, marge, marge des cases, largeur d'un caractère, ratio], interactif, page, opacité]]
                                     -> crée les contrôles (fn_pageMap) et les remplit
      ["tick"]                       -> une fois par seconde (fn_mapOverlayUpdate) : texte réécrit seulement s'il a changé,
                                        aucun redessin de la carte
      ["set", [page]]                -> choisit l'app épinglée ("none" pour aucune) et redessine la carte si elle est ouverte
      ["refresh"]                    -> redessine la carte si elle est ouverte (réglage changé dans le menu CBA)
      ["rows"]                       -> ligne de choix du panneau SITUATION (fn_formRender)
    Aucune notification : le bandeau est dans le téléphone, rien n'apparaît dans l'interface d'Arma.
*/
params [["_act", "app"], ["_a", []]];
disableSerialization;
private _var = "comspec_atak_native_map_pinned_app";
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
    private _label = "";
    private _n = 0;
    private _none = "";
    private _who = "";
    private _when = "";
    private _what = "";
    switch (_page) do {
        case "CHAT": {
            _label = "MESSAGES";
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
            _label = "SMS";
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
        default {
            _label = "ORDRES";
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
    private _count = "";
    if (_n > 0) then {
        private _tpl = [("%1 non lu(s)") call _tr, ("%1 à traiter") call _tr] select (_page isEqualTo "TASK");
        _count = format ["<t font='EtelkaMonospaceProBold' size='1.2' color='%1'>%2</t>", _th get "hexMapWarn", [format [_tpl, _n], str _n] select _narrow];
    } else {
        private _zero = [("Aucun non lu") call _tr, ("Rien à traiter") call _tr] select (_page isEqualTo "TASK");
        _count = format ["<t font='EtelkaMonospacePro' size='0.95' color='%1'>%2</t>", _dim, [_zero, "0"] select _narrow];
    };
    private _l1 = format ["<t size='0.78' color='%1'>%2</t> %3", _ok, _label call _tr, _count];
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
// Page épinglée d'après le réglage du joueur (aucune si valeur inconnue).
private _page = missionNamespace getVariable [_var, "none"];
if !(_page isEqualType "" && {_page in ["CHAT", "SMS", "TASK"]}) then { _page = ""; };

switch (_act) do {
    case "app": {
        // Règle de l'encadrement (fn_appInstall) : une app interdite, retirée ou désinstallée n'a pas de bandeau non plus.
        if (_page isEqualTo "") exitWith { "" };
        private _app = (([] call comspec_atak_native_fnc_appList) select { (_x get "page") isEqualTo _page }) param [0, createHashMap];
        ["", _page] select ((count _app) > 0 && {["installed", _app get "id"] call comspec_atak_native_fnc_appInstall})
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
        _ov set ["pin", _pin];
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
        private _ov = uiNamespace getVariable ["COMSPEC_ATAK_MapOverlay", createHashMap];
        private _pin = _ov getOrDefault ["pin", []];
        if ((count _pin) < 4) exitWith { false };
        private _t0 = diag_tickTime;
        [_pin] call _fill;
        ["carte : app épinglée", _t0] call comspec_atak_native_fnc_perfAcc;
        true
    };
    case "set": {
        private _v = _a param [0, "none"];
        if !(_v in ["none", "CHAT", "SMS", "TASK"]) then { _v = "none"; };
        // 5e paramètre : enregistré dans le profil CBA (sans lui, le choix ne valait que pour la partie en cours).
        [_var, _v, false, "client", true] call CBA_settings_fnc_set;
        ["refresh"] call comspec_atak_native_fnc_mapPinnedApp;
        true
    };
    case "refresh": {
        // Un seul redessin même si le réglage et le menu CBA préviennent tous les deux.
        if (diag_tickTime - (uiNamespace getVariable ["COMSPEC_ATAK_PinRefreshAt", -10]) < 0.3) exitWith { false };
        uiNamespace setVariable ["COMSPEC_ATAK_PinRefreshAt", diag_tickTime];
        if (isNull ([] call comspec_atak_native_fnc_display)) exitWith { false };
        if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isNotEqualTo "MAP") exitWith { false };
        [{ ["MAP"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
        true
    };
    case "rows": {
        private _cur = [_page, "none"] select (_page isEqualTo "");
        [["segment", "App épinglée sous le bandeau", [["AUCUNE", "none"], ["MESSAGES", "CHAT"], ["SMS", "SMS"], ["ORDRES", "TASK"]] apply {
            _x params ["_t", "_v"];
            [_t, format ["['set', [%1]] call comspec_atak_native_fnc_mapPinnedApp;", str _v], _v isEqualTo _cur]
        }, "Résumé de l'app (non lus, dernier message ou ordre) dans un bandeau de la taille de celui des coordonnées ; touchez-le pour ouvrir l'app"]]
    };
    default { false };
};
