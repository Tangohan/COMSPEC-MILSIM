/*
    App Comptes rendus : tous les C.R. au même endroit, chacun avec sa fiche (fn_reportForm).
      NOUVEAU    : ordres reçus du commandement à accuser, urgences en gros boutons (contact, opérateur à terre,
                   SALUTE), puis tous les types par famille avec leur icône ; REPRENDRE quand un brouillon existe ;
      BROUILLONS : fiches entamées, gardées jusqu'à l'envoi ;
      ENVOYÉS    : mes C.R. avec leur parcours (diffusé en jeu, enregistré sur Athena, lu, reçu) ; RENVOYER à Athena,
                   REPRENDRE (nouvelle fiche qui part de celle-ci) ;
      REÇUS      : les C.R. du camp ou de mon groupe, NOUVEAU tant qu'ils ne sont pas ouverts ; ACCUSER RÉCEPTION.
    Un C.R. déplié se lit comme une fiche imprimée : une ligne par repère.
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _ui = uiNamespace getVariable ["COMSPEC_ATAK_RepUi", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_RepUi", _ui];
private _tab = _ui getOrDefault ["tab", "NEW"];
private _types = [] call comspec_atak_native_fnc_reportTypes;
private _byCode = createHashMap;
{ _byCode set [_x select 0, _x]; } forEach _types;
private _list = values (missionNamespace getVariable ["COMSPEC_ATAK_Reports", createHashMap]);
private _drafts = missionNamespace getVariable ["COMSPEC_ATAK_RepDrafts", createHashMap];
private _uid = getPlayerUID player;
private _mine = _list select { (_x getOrDefault ["uid", ""]) isEqualTo _uid };
private _recv = _list select { (_x getOrDefault ["uid", ""]) isNotEqualTo _uid };
private _unread = { _x getOrDefault ["new", false] } count _recv;
private _esc = { params ["_t"]; if !(_t isEqualType "") then { _t = str _t; }; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };
private _act = { params ["_a", ["_k", ""], ["_v", ""]]; compile format ["[%1, %2, %3] call comspec_atak_native_fnc_reportAction;", str _a, str _k, str _v] };
private _pages = ([] call comspec_atak_native_fnc_appList) apply { _x get "page" };
private _prioHex = { params ["_p"]; switch (_p) do { case "FLASH": { "#e5483a" }; case "IMMÉDIAT": { "#f08a3c" }; case "PRIORITAIRE": { "#e8c547" }; default { "#5cc76b" } } };
// Couleur #rrggbb -> [r, g, b, 1] (teinte des icônes).
private _hexRgb = {
    params ["_h"];
    private _d = "0123456789abcdef";
    private _s = toLower (_h select [1]);
    private _v = [];
    for "_i" from 0 to 4 step 2 do { _v pushBack ((((_d find (_s select [_i, 1])) max 0) * 16 + ((_d find (_s select [_i + 1, 1])) max 0)) / 255); };
    _v + [1]
};
private _iconOf = { params ["_c"]; ((_byCode getOrDefault [_c, []]) param [6, createHashMap]) getOrDefault ["icon", "\z\comspec_atak_native\addons\main\data\app_reports.paa"] };

// Fiche ouverte : sa présentation propre.
private _code = _ui getOrDefault ["type", ""];
if (_tab isEqualTo "NEW" && {_code in _byCode} && {((_byCode get _code) select 3) isNotEqualTo ""}) exitWith {
    [[_byCode get _code, _ui] call comspec_atak_native_fnc_reportForm, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
    true
};

private _rows = [["segment", "", [
    ["NOUVEAU", ["tab", "NEW"] call _act, _tab isEqualTo "NEW"],
    [format ["BROUILLONS (%1)", count _drafts], ["tab", "DRAFTS"] call _act, _tab isEqualTo "DRAFTS"],
    [format ["ENVOYÉS (%1)", count _mine], ["tab", "SENT"] call _act, _tab isEqualTo "SENT"],
    [[format ["REÇUS (%1)", count _recv], format ["REÇUS · %1 NOUVEAU(X)", _unread]] select (_unread > 0), ["tab", "RECV"] call _act, _tab isEqualTo "RECV"]
]]];

// Lecture d'un C.R. comme une fiche imprimée, puis son parcours.
private _detail = {
    params ["_r"];
    {
        _x params ["_k", "_lbl", "_v", ["_tag", ""]];
        _rows pushBack ["line", _tag, _lbl, _v, "auto", "", "", "mono"];
    } forEach (_r getOrDefault ["lines", []]);
    private _extra = [];
    if ((_r getOrDefault ["jnet", ""]) isNotEqualTo "") then { _extra pushBack format ["Remonté sur Athena : %1", toLower (_r get "jnet")]; };
    if ((_r getOrDefault ["photo", ""]) isNotEqualTo "") then { _extra pushBack format ["Photo jointe : %1", _r get "photo"]; };
    private _rc = _r getOrDefault ["rcpt", createHashMap];
    if ((count _rc) > 0) then {
        _extra pushBack format ["Lu par %1", ((values _rc) apply { format ["%1%2 (%3)", _x select 0, ["", " ✓"] select ((_x select 1) isEqualTo "ACK"), _x select 2] }) joinString ", "];
    };
    if ((count _extra) > 0) then { _rows pushBack ["text", format ["<t size='0.8' color='#8a9a93'>%1</t>", (_extra apply { [_x] call _esc }) joinString "<br/>"]]; };
};

switch (_tab) do {
    case "NEW": {
        // Ordres du commandement (Athena) pas encore accusés : bouton ACCUSER RÉCEPTION ici, suivi complet dans Tâches.
        private _orders = [] call comspec_atak_native_fnc_tasksAll;
        private _todo = [];
        { if ((toUpper (_y getOrDefault ["status", "PENDING"])) in ["", "PENDING", "DELIVERED", "NEW"]) then { _todo pushBack [_x, _y]; }; } forEach _orders;
        if ((count _todo) > 0) then {
            _rows pushBack ["section", "Reçu du commandement", format ["%1 ordre(s) à accuser", count _todo]];
            {
                _x params ["_id", "_o"];
                private _lbl = _o getOrDefault ["typeLabel", ""];
                if (_lbl isEqualTo "") then { _lbl = _o getOrDefault ["type", "ORDRE"]; };
                private _pl = _o getOrDefault ["payload", ""];
                if !(_pl isEqualType "") then { _pl = str _pl; };
                private _hot = (toUpper (_o getOrDefault ["priority", ""])) in ["HIGH", "URGENT", "FLASH", "PRIORITY"];
                _rows pushBack ["person", "\z\comspec_atak_native\addons\main\data\app_tasks.paa", format ["<t font='COMSPEC_BarlowBold'>%1</t>%2<br/><t size='0.8' color='#8a9a93'>De %3</t>%4",
                    [_lbl] call _esc, ["", "  <t color='#e04038' size='0.85'>PRIORITAIRE</t>"] select _hot, [_o getOrDefault ["issuer", "TOC"]] call _esc,
                    ["", format ["<br/><t size='0.85'>%1</t>", [_pl select [0, 160]] call _esc]] select (_pl isNotEqualTo "")],
                    [["ACCUSER RÉCEPTION", ["orderAck", _id] call _act, true], ["VOIR", { ["TASK"] call comspec_atak_native_fnc_navigate; }]], [[0.88, 0.25, 0.22, 1], [0.95, 0.67, 0.2, 1]] select !_hot];
            } forEach (_todo select [0, 4]);
        };
        // Urgences : trois touches.
        _rows pushBack ["big", [["CONTACT", ["type", "CONTACT"] call _act, "red", true, "Troupes au contact : position, nature, besoin"], ["BLESSÉ", ["type", "EAGLE_DOWN"] call _act, "red", true, "Opérateur à terre, format MIST"], ["SALUTE", ["type", "SALUTE"] call _act, "amber", true, "Observation ennemie lettre par lettre"]]];
        private _fam = "";
        {
            _x params ["_c", "_label", "_family", "_web", "_help", "_fields", "_m"];
            // Raccourci vers une app masquée par la communauté : on ne le propose pas.
            if (_web isNotEqualTo "" || {(_fields select 0) in _pages}) then {
                if (_family isNotEqualTo _fam) then { _fam = _family; _rows pushBack ["section", _family, ""]; };
                private _d = _drafts getOrDefault [_c, []];
                private _btns = switch (true) do {
                    case (_web isEqualTo ""): { [["OUVRIR ›", ["type", _c] call _act]] };
                    case ((count _d) > 0): { [["REPRENDRE", ["type", _c] call _act, true, true, format ["Brouillon de %1", _d select 1]]] };
                    default { [["RÉDIGER", ["type", _c] call _act, true]] };
                };
                _rows pushBack ["person", _m get "icon", format ["<t font='COMSPEC_BarlowBold'>%1</t>  <t font='EtelkaMonospaceProBold' size='0.7' color='%2'>%3</t>%4<br/><t size='0.78' color='#8a9a93'>%5</t>",
                    [_label] call _esc, _m get "color", _m get "tag", ["", "  <t size='0.75' color='#e8c547'>BROUILLON</t>"] select ((count _d) > 0), [_help] call _esc], _btns,
                    [_m get "color"] call _hexRgb];
            };
        } forEach _types;
    };
    case "DRAFTS": {
        if ((count _drafts) isEqualTo 0) then { _rows pushBack ["empty", "\z\comspec_atak_native\addons\main\data\app_reports.paa", "Aucun brouillon", "Une fiche entamée est gardée ici jusqu'à son envoi."]; };
        {
            private _t = _byCode getOrDefault [_x, []];
            if ((count _t) > 0) then {
                _y params ["_v", "_at"];
                private _filled = { (_x select 1) in ["edit", "memo", "num", "dtg", "grid"] && {((_v getOrDefault [_x select 0, ""]) isNotEqualTo "")} } count (_t select 5);
                _rows pushBack ["person", (_t select 6) get "icon", format ["<t font='COMSPEC_BarlowBold'>%1</t><br/><t size='0.8' color='#8a9a93'>Modifié à %2 · %3 ligne(s) écrite(s)</t>", [_t select 1] call _esc, _at, _filled],
                    [["REPRENDRE", ["type", _x] call _act, true], ["SUPPRIMER", ["dropDraft", _x] call _act]]];
            };
        } forEach _drafts;
    };
    default {
        private _isSent = _tab isEqualTo "SENT";
        private _sel = [_recv, _mine] select _isSent;
        _sel = _sel apply { [_x getOrDefault ["time", ""], _x getOrDefault ["id", ""], _x] };
        _sel sort false;
        private _open = _ui getOrDefault ["open", ""];
        if ((count _sel) isEqualTo 0) then {
            _rows pushBack ["empty", "\z\comspec_atak_native\addons\main\data\app_reports.paa", ["Rien reçu pour l'instant", "Aucun compte rendu envoyé"] select _isSent,
                ["Les comptes rendus du camp et de votre groupe arrivent ici.", "Rédigez-en un depuis l'onglet NOUVEAU."] select _isSent];
        };
        {
            private _r = _x select 2;
            private _id = _r getOrDefault ["id", ""];
            private _prio = _r getOrDefault ["prio", "ROUTINE"];
            private _web = _r getOrDefault ["web", ""];
            private _rc = _r getOrDefault ["rcpt", createHashMap];
            private _acks = { (_x select 1) isEqualTo "ACK" } count (values _rc);
            // Parcours d'un C.R. envoyé : diffusé en jeu, Athena, lu, reçu.
            private _trail = if (_isSent) then {
                [
                    ["<t color='#8a9a93'>non diffusé en jeu</t>", "<t color='#5cc76b'>diffusé ✓</t>"] select ((_r getOrDefault ["dest", "CAMP"]) isNotEqualTo "AUCUNE"),
                    switch (_web) do { case "OK": { "<t color='#5cc76b'>Athena ✓</t>" }; case "ÉCHEC": { "<t color='#e5483a'>Athena : non reçu</t>" }; default { "<t color='#8a9a93'>Athena hors ligne</t>" } },
                    ["<t color='#8a9a93'>pas encore lu</t>", format ["<t color='#5cc76b'>lu par %1</t>", count _rc]] select ((count _rc) > 0),
                    ["", format ["<t color='#5cc76b'>reçu par %1 ✓</t>", _acks]] select (_acks > 0)
                ] select { _x isNotEqualTo "" }
            } else {
                [["", "<t color='#e8c547'>NOUVEAU</t>"] select (_r getOrDefault ["new", false]), ["", "<t color='#5cc76b'>réception accusée ✓</t>"] select (_r getOrDefault ["acked", false]),
                    ["", format ["<t color='#8a9a93'>%1</t>", toLower (_r getOrDefault ["dest", ""])]] select ((_r getOrDefault ["dest", "CAMP"]) isEqualTo "MON GROUPE")] select { _x isNotEqualTo "" }
            };
            _rows pushBack ["person", [_r getOrDefault ["code", ""]] call _iconOf, format ["<t font='COMSPEC_BarlowBold' color='%1'>%2</t>  <t font='COMSPEC_BarlowBold'>%3</t>  <t size='0.8' color='#8a9a93'>%4</t><br/><t size='0.8'>%5</t><br/><t size='0.85' color='#c9d4cf'>%6</t>",
                [_prio] call _prioHex, _prio, [_r getOrDefault ["label", "C.R."]] call _esc, [([_r getOrDefault ["by", "?"], _r getOrDefault ["time", ""], _r getOrDefault ["grid", ""]]) joinString " · "] call _esc,
                _trail joinString "  ·  ", [_r getOrDefault ["summary", ""]] call _esc], [], [[_prio] call _prioHex] call _hexRgb];
            if (_open isEqualTo _id) then { [_r] call _detail; };
            private _btns = [[["DÉTAILS", "REPLIER"] select (_open isEqualTo _id), ["open", _id] call _act, false], ["CARTE", ["map", _id] call _act]];
            if (_isSent) then {
                if (_web in ["ÉCHEC", "HORS LIGNE"]) then { _btns pushBack ["RENVOYER", ["web", _id] call _act, true, true, "Renvoie ce compte rendu à Athena"]; };
                _btns pushBack ["REPRENDRE", ["dup", _id] call _act, false, true, "Nouvelle fiche qui part de celle-ci"];
            } else {
                _btns pushBack [["ACCUSER RÉCEPTION", "REÇU ✓"] select (_r getOrDefault ["acked", false]), ["ack", _id] call _act, !(_r getOrDefault ["acked", false]), !(_r getOrDefault ["acked", false]), "Prévient l'auteur que vous l'avez reçu"];
            };
            _btns pushBack ["RETIRER", ["del", _id] call _act];
            _rows pushBack ["buttons", _btns];
        } forEach _sel;
    };
};
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
