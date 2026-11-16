/*
    App « Avis et vigilance » (page SSEALERT, dossier SSE du lanceur) : les alertes du portail SSE sur le téléphone
    (ICD-SSE-ALERTES-001). Aucun toast, aucun hint, aucun systemChat : les retours passent par la ligne d'état de l'écran.
      Liste     : bandeau de classification du porteur, ligne d'état (synchro, hors ligne, liaison), onglets TOUT / VIGILANCE / AVIS /
                  NOTICES, pastilles de couleur des notices, zone, « armé et dangereux » ; une carte par alerte au liseré de sa couleur
                  (rouge pour une notice rouge) avec niveau, sujet, âge, lu ou non lu.
      Alerte    : document d'alerte : bandeaux de classification, bloc d'alerte dominant à la couleur du niveau, conduite à tenir en
                  consignes numérotées, motifs et mesures codifiés, signalement, dernier lieu connu (VOIR SUR LA CARTE), SERVICE À
                  INFORMER (nom, canal, délai), adresse de signalement et son QR code ; JE L'AI VU, ACCUSER LECTURE, PARTAGER,
                  RENOUVELER et LEVER selon les droits du compte.
      Signaler  : position et heure prises d'office, certitude, direction, véhicule, texte court ; trois gestes depuis l'alerte.
      Mes envois: parcours de chaque envoi (en cours, émis, proposé au bureau, non reçu avec sa raison) et RENVOYER sans doublon.
      Émettre   : assistant en sept étapes (fn_pageAlertesNew).
    Données et actions : fn_alertesAction, fn_alertesSend, fn_alertesDraft.
    État : COMSPEC_ATAK_State alertesView ("" liste | detail | share | report | renew | lift | sent | new), alertesOpen (référence),
    alertesTab (ALL | vigilance | avis | notices), alertesF_type | zone | armed, alertesDays, alertesStatus [texte, tick], alertesLast.
    Params (fn_pageRender) : [page, rectangle].
*/
params [["_page", "SSEALERT"], ["_rect", []]];
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _d = "\z\comspec_atak_native\addons\main\data\";
if !([] call comspec_atak_native_fnc_bridge) exitWith {
    [[["empty", _d + "ui_warning.paa", "Avis et vigilance : indisponible sur ce serveur",
        "Les alertes viennent d'Athena par COMSPEC Overwatch, qui n'est pas chargé ici. Demandez à l'administrateur du serveur de l'ajouter."]], [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
    true
};
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
// Lecture à l'ouverture de l'app : relit le site seulement si la liste est absente ou périmée (tâche de fond).
["ensure"] call comspec_atak_native_fnc_alertesAction;
private _store = uiNamespace getVariable ["COMSPEC_ATAK_Alertes", createHashMap];
private _view = _s getOrDefault ["alertesView", ""];
// Assistant d'émission : son propre fichier.
if (_view isEqualTo "new") exitWith { [_page, _rect] call comspec_atak_native_fnc_pageAlertesNew };

private _muted = COMSPEC_ATAK_Th get "hexTextMuted";
private _dim = COMSPEC_ATAK_Th get "hexTextDim";
private _warn = COMSPEC_ATAK_Th get "hexWarn";
private _alert = COMSPEC_ATAK_Th get "hexAlert";
private _okHex = COMSPEC_ATAK_Th get "hexOk";
private _land = _bw > _bh;
private _act = { params ["_a", ["_v", ""]]; compile format ["[%1, %2] call comspec_atak_native_fnc_alertesAction;", str _a, str _v] };
private _dact = { params ["_a", ["_v", ""]]; compile format ["[%1, %2] call comspec_atak_native_fnc_alertesDraft;", str _a, str _v] };
private _sact = { params ["_a", ["_v", ""]]; compile format ["[%1, %2] call comspec_atak_native_fnc_alertesSend;", str _a, str _v] };
private _esc = {
    params ["_t"];
    if !(_t isEqualType "") then { _t = str _t; };
    { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]];
    _t
};
private _escBr = { params ["_t"]; ([_t] call _esc) regexReplace [toString [10], "<br/>"] };
// Texte du portail : passages caviardés en « [CAVIARDÉ] », sans guillemets ni parenthèses vides (fn_sseHub « text »).
private _clean = { params ["_t"]; ["text", _t] call comspec_atak_native_fnc_sseHub };
// Marquage de classification : couleurs des bandeaux de l'échelle américaine du portail. [fond RGBA, texte].
private _markCol = {
    params ["_m"];
    private _u = toUpper _m;
    switch (true) do {
        case ((_u select [0, 3]) isEqualTo "TOP"): { [[1, 0.404, 0.122, 1], "#000000"] };
        case ((_u select [0, 6]) isEqualTo "SECRET"): { [[0.784, 0.063, 0.18, 1], "#ffffff"] };
        case ((_u select [0, 12]) isEqualTo "CONFIDENTIAL"): { [[0, 0.2, 0.627, 1], "#ffffff"] };
        case ((_u select [0, 12]) isEqualTo "UNCLASSIFIED"): { [[0, 0.478, 0.2, 1], "#ffffff"] };
        case ((_u select [0, 3]) isEqualTo "CUI"): { [[0.314, 0.157, 0.471, 1], "#ffffff"] };
        default { [[0.28, 0.3, 0.31, 1], "#ffffff"] };
    }
};
private _banner = {
    params ["_m"];
    (_m call _markCol) params ["_bg", "_tx"];
    ["banner", format ["<t font='COMSPEC_BarlowBold' size='1.05' color='%1'>%2</t>", _tx, [_m] call _esc], _bg]
};
// Niveau : [titre, rang, #hex, RGBA, forme]. Texte sombre sur le jaune et l'ambre, blanc sur l'orange foncé et le rouge.
private _level = { params ["_k"]; ["level", _k] call comspec_atak_native_fnc_alertesAction };
private _ink = { params ["_rank"]; ["#14110a", "#ffffff"] select (_rank >= 3) };
private _glyph = { params ["_shape"]; createHashMapFromArray [["triangle", "▲"], ["diamond", "■"], ["octagon", "●"]] getOrDefault [_shape, "▲"] };
private _bars = {
    params ["_rank", "_hex"];
    private _o = "";
    for "_i" from 1 to 4 do { _o = _o + format ["<t color='%1'>||</t> ", [_dim, _hex] select (_i <= _rank)]; };
    _o
};
// Liseré d'une alerte : la couleur de sa notice, sinon celle de son niveau.
private _edge = {
    params ["_f"];
    private _c = ["color", _f get "color"] call comspec_atak_native_fnc_alertesAction;
    if ((count (_c select 2)) isEqualTo 4) exitWith { [_c select 2, _c select 1] };
    private _lv = [_f get "level"] call _level;
    [_lv select 3, _lv select 2]
};
// Titre du document : « NOTICE ROUGE » (titre du type au catalogue), « AVIS DE RECHERCHE », « FICHE DE VIGILANCE ».
private _docTitle = {
    params ["_f"];
    if ((_f get "doc") isNotEqualTo "avis") exitWith { "FICHE DE VIGILANCE" };
    private _row = ((["cat", "type"] call comspec_atak_native_fnc_alertesAction) select { (_x param [0, ""]) isEqualTo (_f get "type") }) param [0, []];
    private _en = (["lang"] call comspec_atak_native_fnc_alertesAction) isEqualTo "en";
    private _t = _row param [[4, 5] select _en, ""];
    if (_t isEqualType "" && {_t isNotEqualTo ""}) exitWith { toUpper _t };
    private _c = ["color", _f get "color"] call comspec_atak_native_fnc_alertesAction;
    if ((_c select 0) isNotEqualTo "") exitWith { format ["NOTICE %1", toUpper (_c select 0)] };
    "AVIS DE RECHERCHE"
};
private _typeLabel = {
    params ["_f"];
    if ((_f get "doc") isNotEqualTo "avis") exitWith { "Fiche de vigilance" };
    private _t = ["label", ["type", _f get "type"]] call comspec_atak_native_fnc_alertesAction;
    [_t, "Avis de recherche"] select (_t isEqualTo "" || {_t isEqualTo (_f get "type")})
};
private _agoText = {
    params ["_m"];
    if (_m < 0) exitWith { "" };
    if (_m < 1) exitWith { "à l'instant" };
    if (_m < 60) exitWith { format ["il y a %1 min", floor _m] };
    if (_m < 1440) exitWith { format ["il y a %1 h", floor (_m / 60)] };
    format ["il y a %1 j", floor (_m / 1440)]
};
private _all = ["list"] call comspec_atak_native_fnc_alertesAction;
private _meta = _store getOrDefault ["meta", []];
private _viewerMark = _meta param [1, "CUI"];
if (!(_viewerMark isEqualType "") || {_viewerMark isEqualTo ""}) then { _viewerMark = "CUI"; };
private _age = ["age"] call comspec_atak_native_fnc_alertesAction;
private _gate = ["gate"] call comspec_atak_native_fnc_alertesAction;
private _offline = _gate isNotEqualTo "" || {(_store getOrDefault ["src", ""]) isEqualTo "poste"} || {(_store getOrDefault ["err", ""]) isNotEqualTo "" && {(count _all) > 0}};
private _busy = _store getOrDefault ["busy", false];
private _unavailable = (_store getOrDefault ["avail", ""]) isEqualTo "no";
private _rights = ["rights"] call comspec_atak_native_fnc_alertesAction;
private _outbox = ["list"] call comspec_atak_native_fnc_alertesSend;
private _pendingN = { (_x getOrDefault ["web", ""]) in ["EN ATTENTE", "ÉCHEC", "HORS LIGNE"] } count _outbox;

// Ligne d'état : message du moment (30 s) sinon l'état de la synchronisation ; à droite la liaison (satellite ou relais).
private _statusRow = {
    params [["_right", ""]];
    private _msg = _s getOrDefault ["alertesStatus", ["", 0]];
    private _left = "";
    private _bg = [0.071, 0.09, 0.114, 1];
    if ((_msg select 0) isNotEqualTo "" && {(diag_tickTime - (_msg select 1)) < 30 || {_busy}}) then {
        // Retour d'une action (« Lecture accusée. ») : en couleur d'état, pour ne pas se lire comme un nom de service.
        _left = format ["<t color='%1'>%2</t>", _okHex, [_msg select 0] call _esc];
    } else {
        private _clock = _store getOrDefault ["clock", ""];
        switch (true) do {
            case (_gate isNotEqualTo ""): {
                _bg = [0.18, 0.12, 0.03, 1];
                _left = format ["<t color='%1'>%2</t>", _warn, [["gateText", _gate] call comspec_atak_native_fnc_alertesAction] call _esc];
            };
            case (_unavailable): { _left = "Indisponible sur ce serveur"; };
            case (_age < 0 && {(count _all) isEqualTo 0}): { _left = ["Pas encore synchronisé", "Lecture en cours…"] select _busy; };
            case (_offline): {
                _bg = [0.18, 0.12, 0.03, 1];
                _left = format ["<t color='%1'>Hors ligne</t> · dernière synchro %2 (%3)", _warn, _clock, [_age] call _agoText];
            };
            default {
                _left = format ["Synchro %1 · %2%3", _clock, [_age] call _agoText, ["", format [" · <t color='%1'>à actualiser</t>", _warn]] select (_age > 30)];
            };
        };
    };
    // Liaison à droite, libellée et atténuée : avant, « Lecture accusée. … LIAISON SATELLITE » se lisait comme une seule
    // phrase (statut et nom de service mêlés). Libellé et valeur sont deux morceaux traduits à part (fn_tr).
    if (_right isEqualTo "") then { _right = format ["<t color='%1'>Liaison</t> %2", _muted, ["satellite", "relais"] select ((["get"] call comspec_atak_native_fnc_linkMode) isEqualTo "relay")]; };
    ["strip", format ["<t font='EtelkaMonospacePro' size='0.85'>%1</t>", _left], format ["<t font='EtelkaMonospacePro' size='0.85'>%1</t>", _right], _bg]
};
private _footer = {
    ["text", format ["<t size='0.72' color='%1'>%2</t>", _muted, "Les alertes viennent du portail Athena et sont filtrées selon votre habilitation."]]
};
private _backBtn = ["‹ RETOUR", { ["back"] call comspec_atak_native_fnc_alertesAction; }];
private _open = _s getOrDefault ["alertesOpen", ""];

// ---------------------------------------------------------------------------------------------------------------- Mes envois
if (_view isEqualTo "sent") exitWith {
    _s set ["backSub", ["SSEALERT", { ["back"] call comspec_atak_native_fnc_alertesAction; }]];
    private _rows = [];
    _rows pushBack ["header", "Mes envois", format ["%1 envoi(s) · %2 à suivre", count _outbox, _pendingN], [], { ["back"] call comspec_atak_native_fnc_alertesAction; }];
    _rows pushBack ([""] call _statusRow);
    private _last = _s getOrDefault ["alertesLast", ""];
    private _sorted = +_outbox;
    reverse _sorted;
    // Le dernier envoi d'abord, en carte d'état : c'est celui que le joueur vient de faire.
    private _iLast = _sorted findIf { (_x get "id") isEqualTo _last };
    if (_iLast > 0) then { private _r = _sorted deleteAt _iLast; _sorted = [_r] + _sorted; };
    if (_sorted isEqualTo []) then {
        _rows pushBack ["empty", _d + "ui_send.paa", "Aucun envoi", "Une alerte émise, un signalement, un renouvellement ou une levée apparaît ici avec son parcours."];
    };
    {
        private _rec = _x;
        private _web = _rec getOrDefault ["web", ""];
        (["state", _rec get "id"] call comspec_atak_native_fnc_alertesSend) params ["_trail", "_retry"];
        private _proposal = (_rec getOrDefault ["resState", ""]) isEqualTo "proposal";
        private _head = switch (true) do {
            case (_web isEqualTo "EN ATTENTE"): { ["ENVOI EN COURS…", [0.55, 0.6, 0.65, 1], "ui_clock.paa"] };
            case (_web isEqualTo "OK" && {_proposal}): { ["PROPOSÉ AU BUREAU", [0.95, 0.67, 0.2, 1], "ui_send.paa"] };
            case (_web isEqualTo "OK"): { [["REÇU", "ÉMIS"] select ((_rec get "op") isEqualTo "create"), [0.36, 0.78, 0.42, 1], "ui_check.paa"] };
            case (_web isEqualTo "HORS LIGNE"): { ["EN FILE", [0.95, 0.67, 0.2, 1], "ui_clock.paa"] };
            default { ["NON REÇU", [0.9, 0.28, 0.23, 1], "ui_error.paa"] };
        };
        _head params ["_title", "_rgb", "_pic"];
        private _ref = _rec getOrDefault ["resRef", ""];
        private _target = [_rec getOrDefault ["ref", ""], _ref] select ((_rec get "op") in ["create", "renew"] && {!_proposal} && {_ref isNotEqualTo ""});
        if (_proposal && {(_rec getOrDefault ["answered", ""]) isNotEqualTo ""}) then { _target = _rec get "answered"; };
        _rows pushBack ["status", _d + _pic, _rgb, format ["%1 · %2", _title, _rec getOrDefault ["at", ""]],
            format ["%1<br/>%2", [_rec getOrDefault ["label", ""]] call _esc, _trail],
            ["", "RENVOYER reprend le même envoi : le portail n'en fait jamais un doublon."] select _retry];
        private _btns = [];
        if (_retry) then { _btns pushBack ["RENVOYER", ["retry", _rec get "id"] call _sact, true, true, "Renvoie cet envoi à Athena, sans doublon"]; };
        if (_web isEqualTo "OK" && {_target isNotEqualTo ""} && {(_target select [0, 5]) isNotEqualTo "PROP-"}) then { _btns pushBack ["OUVRIR L'ALERTE", ["openRef", _target] call _act, !_retry]; };
        if (_web isNotEqualTo "EN ATTENTE") then { _btns pushBack ["RETIRER", ["del", _rec get "id"] call _sact]; };
        if (_btns isNotEqualTo []) then { _rows pushBack ["buttons", _btns]; };
    } forEach _sorted;
    _rows pushBack ["buttons", [_backBtn, ["ÉMETTRE", ["view", "new"] call _act]]];
    [_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
    true
};

// ---------------------------------------------------------------------------------------------------------------- Une alerte
if (_open isNotEqualTo "" && {_view in ["detail", "share", "report", "renew", "lift"]}) exitWith {
    _s set ["backSub", ["SSEALERT", { ["back"] call comspec_atak_native_fnc_alertesAction; }]];
    private _f = ["get", _open] call comspec_atak_native_fnc_alertesAction;
    private _rows = [];
    if ((count _f) isEqualTo 0) exitWith {
        // Alerte reçue ou ouverte par sa référence : elle se lit dans MA liste, donc selon MON habilitation.
        _rows pushBack ([_viewerMark] call _banner);
        _rows pushBack ([""] call _statusRow);
        _rows pushBack ["empty", _d + "ui_warning.paa", format ["Alerte %1 introuvable", [_open] call _esc],
            "Elle n'est pas dans votre liste : actualisez, ou elle est au-dessus de votre habilitation, levée depuis plus de trente jours ou expirée."];
        _rows pushBack ["buttons", [_backBtn, ["ACTUALISER", { ["load", true] call comspec_atak_native_fnc_alertesAction; }, true]]];
        [_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
        true
    };
    private _mark = _f get "mark";
    if (_mark isEqualTo "") then { _mark = _viewerMark; };
    private _lv = [_f get "level"] call _level;
    _lv params ["_lvTitle", "_lvRank", "_lvHex", "_lvRgb", "_lvShape"];
    if (_lvRank isEqualTo 0) then { _lvRank = _f get "rank"; };
    // Nom caviardé : « Identité protégée » ; vide : « Sujet non identifié » (fn_sseHub « name »).
    private _name = ["name", [_f get "name", "Sujet non identifié"]] call comspec_atak_native_fnc_sseHub;
    private _closed = (_f get "state") in ["lifted", "expired"];
    private _more = ["more", _open] call comspec_atak_native_fnc_alertesAction;

    // ---------- Partage
    if (_view isEqualTo "share") exitWith {
        _rows pushBack ([_mark] call _banner);
        _rows pushBack ["header", "Partager l'alerte", format ["%1 · %2", _open, [[_f] call _docTitle] call _esc], [], { ["back"] call comspec_atak_native_fnc_alertesAction; }];
        _rows pushBack ([""] call _statusRow);
        _rows pushBack ["text", format ["<t size='0.8' color='%1'>Seule la référence part. Le destinataire lit l'alerte selon SON habilitation : le site refuse ce qui la dépasse.</t>", _muted]];
        private _btOn = ["on"] call comspec_atak_native_fnc_btAction;
        private _near = ["near"] call comspec_atak_native_fnc_btAction;
        _rows pushBack ["section", "Bluetooth", "Téléphones appairés à portée"];
        if (!_btOn) then {
            _rows pushBack ["text", format ["<t color='%1'>Bluetooth éteint : allumez-le dans l'app Bluetooth pour envoyer l'alerte à un appareil appairé.</t>", _muted]];
        } else {
            private _paired = (_near select { _x select 4 });
            if (_paired isEqualTo []) then {
                _rows pushBack ["text", format ["<t color='%1'>Aucun appareil appairé à portée. Appairez-le dans l'app Bluetooth (code).</t>", _muted]];
            };
            {
                _x params ["_uid", "_pname", "_dist"];
                _rows pushBack ["person", _d + "app_profile.paa",
                    format ["<t font='COMSPEC_BarlowBold'>ATAK de %1</t><br/><t size='0.8' color='%2'>appairé · %3 m</t>", [_pname] call _esc, _muted, _dist],
                    [["ENVOYER", ["shareBt", [_open, _uid]] call _act, true]]];
            } forEach _paired;
        };
        _rows pushBack ["section", "SMS", "Joueurs de la partie"];
        private _ppl = (allPlayers select { _x isNotEqualTo player && {alive _x} }) apply { name _x };
        _ppl sort true;
        if (_ppl isEqualTo []) then { _rows pushBack ["text", format ["<t color='%1'>Aucun autre joueur connecté.</t>", _muted]]; };
        {
            _rows pushBack ["person", _d + "app_chat.paa", format ["<t font='COMSPEC_BarlowBold'>%1</t>", [_x] call _esc], [["ENVOYER", ["shareSms", [_open, _x]] call _act]]];
        } forEach (_ppl select [0, 24]);
        _rows pushBack ["buttons", [_backBtn]];
        _rows pushBack ([_mark] call _banner);
        [_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
        true
    };

    // ---------- Signaler une observation : position et heure déjà prises, il reste à confirmer.
    if (_view isEqualTo "report") exitWith {
        private _r = ["reportGet"] call comspec_atak_native_fnc_alertesDraft;
        _rows pushBack ([_mark] call _banner);
        _rows pushBack ["header", "Signaler une observation", format ["%1 · %2", _open, [_name] call _esc], [], { ["back"] call comspec_atak_native_fnc_alertesAction; }];
        _rows pushBack ([""] call _statusRow);
        _rows pushBack ["cells", [
            [_d + "ui_pin.paa", [0.36, 0.78, 0.42, 1], "Position", [_r getOrDefault ["grid", ""]] call _esc, [[_r getOrDefault ["place", ""], "prise d'office"] select ((_r getOrDefault ["place", ""]) isEqualTo "")] call _esc],
            [_d + "ui_clock.paa", [0.36, 0.78, 0.42, 1], "Heure", [_r getOrDefault ["clock", ""]] call _esc, "prise d'office"]
        ], 2];
        _rows pushBack ["segment", "Ce que je rapporte", [
            ["JE L'AI VU", ["report", ["what", "seen"]] call _dact, (_r getOrDefault ["what", "seen"]) isEqualTo "seen"],
            ["J'AI UNE INFORMATION", ["report", ["what", "info"]] call _dact, (_r getOrDefault ["what", "seen"]) isEqualTo "info"]
        ]];
        _rows pushBack ["segment", "Certitude", (["cat", "sure"] call comspec_atak_native_fnc_alertesAction) apply {
            [toUpper (["label", ["sure", _x select 0]] call comspec_atak_native_fnc_alertesAction), ["report", ["sure", _x select 0]] call _dact, (_r getOrDefault ["sure", "medium"]) isEqualTo (_x select 0)]
        }];
        _rows pushBack ["segment", "Direction prise", [["—", ""], ["N", "N"], ["NE", "NE"], ["E", "E"], ["SE", "SE"], ["S", "S"], ["SO", "SO"], ["O", "O"], ["NO", "NO"]] apply {
            [_x select 0, ["report", ["dir", _x select 1]] call _dact, (_r getOrDefault ["dir", ""]) isEqualTo (_x select 1)]
        }];
        _rows pushBack ["edit", "av_rvehicle", "Véhicule (facultatif : type, couleur, plaque)", _r getOrDefault ["vehicle", ""]];
        _rows pushBack ["memo", "av_rtext", "Ce que j'ai vu (facultatif, 600 caractères)", _r getOrDefault ["text", ""], 3];
        if (_gate isNotEqualTo "") then {
            _rows pushBack ["text", format ["<t size='0.85' color='%1'>Hors réseau : le signalement est gardé en file et part seul au retour de la liaison.</t>", _warn], [0.18, 0.12, 0.03, 1]];
        };
        if (_closed) then {
            _rows pushBack ["text", format ["<t color='%1'>Alerte levée ou expirée : le portail ne prend plus de signalement.</t>", _warn], [0.18, 0.12, 0.03, 1]];
        } else {
            _rows pushBack ["big", [["ENVOYER LE SIGNALEMENT", { ["reportSend"] call comspec_atak_native_fnc_alertesDraft; }, "red", true, "Envoie le signalement au service à informer"]]];
        };
        _rows pushBack ["buttons", [_backBtn]];
        _rows pushBack ([_mark] call _banner);
        [_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
        true
    };

    // ---------- Renouveler
    if (_view isEqualTo "renew") exitWith {
        private _days = _s getOrDefault ["alertesDays", 30];
        _rows pushBack ([_mark] call _banner);
        _rows pushBack ["header", "Renouveler l'alerte", format ["%1 · %2", _open, [_name] call _esc], [], { ["back"] call comspec_atak_native_fnc_alertesAction; }];
        _rows pushBack ([""] call _statusRow);
        _rows pushBack ["segment", "Nouvelle durée de validité", [7, 14, 30, 60, 90, 180] apply { [format ["%1 J", _x], ["days", _x] call _act, _days isEqualTo _x] }];
        _rows pushBack ["edit", "av_reason", "Motif du renouvellement (facultatif)", ""];
        _rows pushBack ["text", format ["<t size='0.8' color='%1'>La précédente est close ; son adresse de signalement reste lisible et renvoie vers la nouvelle.</t>", _muted]];
        _rows pushBack ["big", [["RENOUVELER", { ["renewSend"] call comspec_atak_native_fnc_alertesAction; }, "green", true, "Émet une nouvelle référence"]]];
        _rows pushBack ["buttons", [_backBtn]];
        _rows pushBack ([_mark] call _banner);
        [_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
        true
    };

    // ---------- Lever (motif obligatoire)
    if (_view isEqualTo "lift") exitWith {
        _rows pushBack ([_mark] call _banner);
        _rows pushBack ["header", "Lever l'alerte", format ["%1 · %2", _open, [_name] call _esc], [], { ["back"] call comspec_atak_native_fnc_alertesAction; }];
        _rows pushBack ([""] call _statusRow);
        _rows pushBack ["memo", "av_reason", "Motif de la levée (obligatoire)", "", 3];
        _rows pushBack ["text", format ["<t size='0.8' color='%1'>L'alerte quitte la liste de tous les téléphones. Elle reste lisible trente jours par sa référence, marquée « levée ».</t>", _muted]];
        _rows pushBack ["big", [["LEVER L'ALERTE", { ["liftSend"] call comspec_atak_native_fnc_alertesAction; }, "red", true, "Clôt l'alerte pour tout le monde"]]];
        _rows pushBack ["buttons", [_backBtn]];
        _rows pushBack ([_mark] call _banner);
        [_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
        true
    };

    // ---------- Document d'alerte
    ([_f] call _edge) params ["_edgeRgb", "_edgeHex"];
    private _notice = ["color", _f get "color"] call comspec_atak_native_fnc_alertesAction;
    _rows pushBack ([_mark] call _banner);
    _rows pushBack ["strip", format ["<t font='COMSPEC_BarlowBold' size='0.9' color='%1'>%2</t>", _edgeHex, [[_f] call _docTitle] call _esc],
        format ["<t font='EtelkaMonospacePro' size='0.85'>%1</t>", [_f get "ref"] call _esc], [0.071, 0.09, 0.114, 1]];
    _rows pushBack ([""] call _statusRow);
    // Notice de couleur : filet de la couleur de la notice sous l'en-tête (fn_formRender dessine un bandeau sans texte en
    // trait fin). Plus de filet en pied : collé au bandeau de classification du bas, il se lisait comme un second bandeau vide.
    if ((count (_notice select 2)) isEqualTo 4) then { _rows pushBack ["banner", "", _notice select 2]; };
    if (_closed) then {
        private _when = _more getOrDefault ["liftedAt", ""];
        _rows pushBack ["banner", format ["<t font='COMSPEC_BarlowBold' color='#ffffff'>%1%2</t>%3", ["ALERTE LEVÉE", "ALERTE EXPIRÉE"] select ((_f get "state") isEqualTo "expired"),
            ["", format [" LE %1", [_when] call _esc]] select (_when isNotEqualTo ""),
            ["", format ["<br/><t size='0.85' color='#d7dde3'>%1</t>", [_more getOrDefault ["liftReason", ""]] call _escBr]] select ((_more getOrDefault ["liftReason", ""]) isNotEqualTo "")], [0.28, 0.3, 0.31, 1]];
    };
    // Bloc d'alerte dominant : bande pleine à la couleur du niveau, forme, titre, rang n/4, consigne dominante.
    private _orderText = createHashMapFromArray [["hold", "NE PAS APPRÉHENDER — SIGNALER"], ["order", "INTERVENTION SUR ORDRE"]] getOrDefault [_f get "order", ""];
    private _inkHex = [_lvRank] call _ink;
    _rows pushBack ["banner", format ["<t font='COMSPEC_BarlowBold' size='1.45' color='%1'>%2 %3</t><br/><t font='EtelkaMonospaceProBold' size='0.85' color='%1'>NIVEAU %4/4</t>%5",
        _inkHex, [_lvShape] call _glyph, [[_lvTitle, "VIGILANCE"] select (_lvTitle isEqualTo "")] call _esc, _lvRank,
        ["", format ["<br/><t font='COMSPEC_BarlowBold' size='1.1' color='%1'>%2</t>", _inkHex, _orderText]] select (_orderText isNotEqualTo "")], _lvRgb];
    if ((_f get "armed") isEqualTo "yes" && {(_f get "level") isNotEqualTo "danger"}) then {
        _rows pushBack ["banner", "<t font='COMSPEC_BarlowBold' size='1.05' color='#ffffff'>ARMÉ ET DANGEREUX</t>", [0.55, 0.05, 0.05, 1]];
    };
    // Alias vides ou caviardés retirés : plus de « dit « » » orphelin.
    private _alias = ["alias", _f get "alias"] call comspec_atak_native_fnc_sseHub;
    _rows pushBack ["person", _d + "app_wanted.paa", format ["<t font='COMSPEC_BarlowBold' size='1.35'>%1</t>%2<br/><t size='0.85' color='%3'>%4%5</t>",
        [_name] call _esc, ["", format ["<br/><t color='%1' size='0.9'>dit « %2 »</t>", _dim, [_alias] call _esc]] select (_alias isNotEqualTo ""),
        _muted, [[_f] call _typeLabel] call _esc,
        ["", format [" · <t color='%1'>PÉRISSABLE : échéance dans 72 h</t>", _warn]] select ((_f get "soon") isEqualTo true)], [], _lvRgb];
    // Les deux gestes du terrain, tout de suite sous le bloc d'alerte.
    if (!_closed) then {
        private _acked = (_f get "acked") isEqualTo true;
        _rows pushBack ["big", [
            ["JE L'AI VU", ["reportOpen", [_open, [], ""]] call _dact, "red", (_rights param [3, true]) isEqualTo true || {(_store getOrDefault ["catAt", -1]) < 0}, "Signaler une observation : position et heure prises d'office"],
            [["ACCUSER LECTURE", "LECTURE ACCUSÉE"] select _acked, ["ack", _open] call _act, "green", !_acked, "Dit au bureau que vous avez lu cette alerte"]
        ]];
    };
    // Conduite à tenir : consignes numérotées (mesures codifiées puis lignes libres).
    private _conduct = _f get "conduct";
    _rows pushBack ["section", "Conduite à tenir", ""];
    if (_conduct isEqualTo []) then { _rows pushBack ["text", format ["<t color='%1'>Aucune consigne particulière.</t>", _muted]]; };
    {
        // Puce ou numéro déjà écrit par le portail retiré : un seul numéro par consigne (avant « 1 • Ne pas… »).
        _rows pushBack ["text", format ["<t font='EtelkaMonospaceProBold' color='%1'>%2</t>  <t color='#ffe9e2'>%3</t>", _lvHex, _forEachIndex + 1, [["item", _x] call comspec_atak_native_fnc_sseHub] call _escBr], [0.2, 0.07, 0.06, 1]];
    } forEach _conduct;
    private _donts = _more getOrDefault ["donts", []];
    if (_donts isNotEqualTo []) then {
        _rows pushBack ["text", format ["<t font='COMSPEC_BarlowBold' color='%1'>À NE PAS FAIRE</t><br/><t color='#ffd2cc'>%2</t>", _alert, (_donts apply { "· " + ([["item", _x] call comspec_atak_native_fnc_sseHub] call _esc) }) joinString "<br/>"], [0.2, 0.07, 0.06, 1]];
    };
    // Motifs codifiés (détail), sinon le motif court de la liste.
    private _reasons = _more getOrDefault ["reasons", []];
    if (_reasons isNotEqualTo [] || {(_f get "reason") isNotEqualTo ""}) then {
        _rows pushBack ["section", "Motifs", ""];
        if (_reasons isNotEqualTo []) then {
            _rows pushBack ["text", (_reasons apply {
                private _sp = _x find " ";
                if (_sp > 0) then { format ["<t font='EtelkaMonospaceProBold' color='%1'>%2</t> %3", _dim, [_x select [0, _sp]] call _esc, [[_x select [_sp + 1]] call _clean] call _esc] } else { [[_x] call _clean] call _esc }
            }) joinString "<br/>", [0.085, 0.102, 0.093, 1]];
        };
        if ((_f get "reason") isNotEqualTo "") then { _rows pushBack ["text", format ["<t color='#e3ebe7'>%1</t>", [[_f get "reason"] call _clean] call _escBr], [0.085, 0.102, 0.093, 1]]; };
    };
    if ((_more getOrDefault ["desc", ""]) isNotEqualTo "") then {
        _rows pushBack ["section", "Signalement", ""];
        _rows pushBack ["text", format ["<t color='#e3ebe7'>%1</t>", [[_more get "desc"] call _clean] call _escBr], [0.085, 0.102, 0.093, 1]];
    };
    private _fact = {
        params ["_label", "_v"];
        _v = [_v] call _clean;
        if (_v isEqualTo "") exitWith {};
        _rows pushBack ["info", _label, [_v] call _esc];
    };
    _rows pushBack ["section", "Dernier lieu connu", ""];
    ["Lieu", _f get "place"] call _fact;
    ["Zone", _f get "zone"] call _fact;
    if ((_f get "place") isEqualTo "" && {(_f get "zone") isEqualTo ""}) then {
        _rows pushBack ["text", format ["<t color='%1'>Aucun lieu connu.</t>", _muted]];
    } else {
        _rows pushBack ["buttons", [["VOIR SUR LA CARTE", ["map", _open] call _act, false, true, "Centre la carte sur le lieu, quand il s'y retrouve"]]];
    };
    private _links = [["Véhicules", _more getOrDefault ["vehicles", []]], ["Personnes liées", _more getOrDefault ["people", []]]] select { (count (_x select 1)) > 0 };
    if (_links isNotEqualTo []) then {
        _rows pushBack ["section", "Liens", ""];
        { _x params ["_label", "_items"]; _rows pushBack ["info", _label, (_items apply { [[_x] call _clean] call _esc }) joinString "<br/>"]; } forEach _links;
    };
    // Service à informer : nom, canal, délai ; adresse de signalement et son QR code à scanner avec un vrai téléphone.
    _rows pushBack ["section", "Service à informer", ""];
    private _delay = ["label", ["delay", _f get "delay"]] call comspec_atak_native_fnc_alertesAction;
    if ((_f get "service") isEqualTo "" && {(_f get "channel") isEqualTo ""}) then {
        _rows pushBack ["text", format ["<t color='%1'>Aucun service désigné : signalez au bureau par JE L'AI VU.</t>", _muted]];
    } else {
        _rows pushBack ["text", format ["<t font='COMSPEC_BarlowBold' size='1.15'>%1</t>%2%3",
            [["name", [_f get "service", "Service non précisé"]] call comspec_atak_native_fnc_sseHub] call _esc,
            ["", format ["<br/><t font='EtelkaMonospaceProBold' color='%1'>%2</t>", _warn, [_f get "channel"] call _esc]] select ((_f get "channel") isNotEqualTo ""),
            ["", format ["<br/><t size='0.85' color='%1'>Compte rendu : %2</t>", _dim, [_delay] call _esc]] select ((_f get "delay") isNotEqualTo "")], [0.05, 0.08, 0.11, 1]];
    };
    private _url = _f get "url";
    if (_url isNotEqualTo "") then {
        _rows pushBack ["text", format ["<t size='0.8' color='%1'>Adresse de signalement</t><br/><t font='EtelkaMonospacePro' size='0.82'>%2</t>", _muted, [_url] call _esc], [0.05, 0.08, 0.11, 1]];
        private _qr = (_store getOrDefault ["qr", createHashMap]) getOrDefault [_url, []];
        if ((count _qr) >= 21) then {
            // Part de la largeur de sa colonne (le QR reste dans la section Service à informer) ; fn_formRender le réduit
            // pour qu'il tienne entier dans la zone visible.
            _rows pushBack ["qr", _qr, 0.62];
            _rows pushBack ["text", format ["<t align='center' size='0.8' color='%1'>Scannez ce code avec votre téléphone pour signaler depuis le portail.</t>", _muted]];
        };
    } else {
        _rows pushBack ["text", format ["<t size='0.8' color='%1'>Adresse de signalement : pas encore donnée par ce serveur.</t>", _muted]];
    };
    _rows pushBack ["section", "Validité", ""];
    ["Émise le", _f get "issued"] call _fact;
    ["Valable jusqu'au", [_f get "until", "Sans échéance"] select ((_f get "until") isEqualTo "")] call _fact;
    ["Mise à jour", _f get "updated"] call _fact;
    if ((count _more) > 0) then { ["Signalements reçus", str (_more getOrDefault ["reports", 0])] call _fact; };
    if ((_f get "cut") isEqualTo true) then {
        _rows pushBack ["text", format ["<t size='0.8' color='%1'>Texte raccourci : lecture de l'alerte entière…</t>", _muted]];
    };
    // Actions secondaires : partage, gestion selon les droits du compte, lien vers la fiche complète de l'app Fiches.
    private _btns = [_backBtn, ["PARTAGER", ["share", _open] call _act, true]];
    private _canIssue = (_rights param [[0, 1] select ((_f get "doc") isEqualTo "avis"), false]) isEqualTo true;
    if (!_closed && {_canIssue}) then { _btns pushBack ["RENOUVELER", ["view", "renew"] call _act]; };
    if (!_closed && {(_rights param [2, false]) isEqualTo true}) then { _btns pushBack ["LEVER", ["view", "lift"] call _act, "red"]; };
    _rows pushBack ["buttons", _btns];
    private _cross = [];
    if ((count (["get", _open] call comspec_atak_native_fnc_fichesAction)) > 0) then {
        _cross pushBack ["FICHE COMPLÈTE", compile format ["['openRef', %1] call comspec_atak_native_fnc_fichesAction;", str _open], false, true, "Ouvre la fiche dans l'app Fiches"];
    };
    _cross pushBack ["ACTUALISER", { ["load", true] call comspec_atak_native_fnc_alertesAction; }];
    _rows pushBack ["buttons", _cross];
    _rows pushBack ([_mark] call _banner);
    _rows pushBack ([] call _footer);
    [_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
    true
};

// ---------------------------------------------------------------------------------------------------------------- Liste
private _tab = _s getOrDefault ["alertesTab", "ALL"];
private _fType = _s getOrDefault ["alertesF_type", ""];
private _fZone = _s getOrDefault ["alertesF_zone", ""];
private _fArmed = _s getOrDefault ["alertesF_armed", ""];
private _rows = [];
_rows pushBack ([_viewerMark] call _banner);
private _draft = ["get"] call comspec_atak_native_fnc_alertesDraft;
private _resume = (_draft getOrDefault ["step", 0]) > 0 || {(_draft getOrDefault ["subjectName", ""]) isNotEqualTo ""};
_rows pushBack ["header", "Avis et vigilance", "", [
    [["ÉMETTRE", "REPRENDRE LE BROUILLON"] select _resume, ["view", "new"] call _act, true],
    [format ["MES ENVOIS%1", ["", format [" (%1)", _pendingN]] select (_pendingN > 0)], ["view", "sent"] call _act, false]
], {}];
_rows pushBack ([format ["%1 alerte(s)", count _all]] call _statusRow);

if (_unavailable && {(count _all) isEqualTo 0}) exitWith {
    _rows pushBack ["empty", _d + "ui_warning.paa", "Avis et vigilance : indisponible sur ce serveur",
        "Le portail de ce serveur ne diffuse pas encore les alertes au téléphone. Rien n'est en panne : l'app s'allumera seule après sa mise à jour.",
        ["ACTUALISER", { ["load", true] call comspec_atak_native_fnc_alertesAction; }]];
    _rows pushBack ["buttons", [["FICHES", { ["FICHES"] call comspec_atak_native_fnc_navigate; }], ["RECHERCHÉS", { ["WANTED"] call comspec_atak_native_fnc_navigate; }]]];
    [_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
    true
};

private _isNotice = { params ["_f"]; (_f get "doc") isEqualTo "avis" && {(_f get "color") isNotEqualTo ""} };
private _nVig = { (_x get "doc") isEqualTo "vigilance" } count _all;
private _nNot = { [_x] call _isNotice } count _all;
private _tabAct = { params ["_t"]; compile format ["(uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) set ['alertesTab', %1]; ['filter', ['type', '']] call comspec_atak_native_fnc_alertesAction;", str _t] };
_rows pushBack ["tabs", [
    ["TOUT", ["ALL"] call _tabAct, _tab isEqualTo "ALL", count _all],
    ["VIGILANCE", ["vigilance"] call _tabAct, _tab isEqualTo "vigilance", _nVig],
    ["AVIS", ["avis"] call _tabAct, _tab isEqualTo "avis", (count _all) - _nVig - _nNot],
    ["NOTICES", ["notices"] call _tabAct, _tab isEqualTo "notices", _nNot]
]];
// Notices : une pastille par couleur présente, à la couleur de la notice.
if (_tab isEqualTo "notices") then {
    private _colors = [];
    { if ([_x] call _isNotice) then { _colors pushBackUnique (_x get "color"); }; } forEach _all;
    if ((count _colors) > 1) then {
        _rows pushBack ["chips", [["TOUTES", "", ["filter", ["type", ""]] call _act]] + (_colors apply {
            private _c = ["color", _x] call comspec_atak_native_fnc_alertesAction;
            [format ["%1%2", ["", "● "] select (_fType isEqualTo _x), toUpper (_c select 0)], _c select 2, ["filter", ["type", _x]] call _act]
        })];
    };
};
private _zones = [];
{ if ((_x get "zone") isNotEqualTo "") then { _zones pushBackUnique (_x get "zone"); }; } forEach _all;
_zones sort true;
if ((count _zones) > 1) then {
    _rows pushBack ["combo", "alertesZone", "Zone", [["Toutes les zones", ""]] + (_zones apply { [_x, _x] }), _fZone,
        ["OK", { params ["_v"]; ["filter", ["zone", _v]] call comspec_atak_native_fnc_alertesAction; }]];
};
if ((_all findIf { (_x get "armed") isEqualTo "yes" }) >= 0) then {
    _rows pushBack ["toggle", "Armé et dangereux seulement", _fArmed isEqualTo "yes", ["filter", ["armed", ["yes", ""] select (_fArmed isEqualTo "yes")]] call _act];
};

private _shown = _all select {
    private _f = _x;
    (_tab isEqualTo "ALL" || {_tab isEqualTo "vigilance" && {(_f get "doc") isEqualTo "vigilance"}}
        || {_tab isEqualTo "avis" && {(_f get "doc") isEqualTo "avis"} && {!([_f] call _isNotice)}} || {_tab isEqualTo "notices" && {[_f] call _isNotice}})
    && {_fType isEqualTo "" || {(_f get "color") isEqualTo _fType}}
    && {_fZone isEqualTo "" || {(_f get "zone") isEqualTo _fZone}}
    && {_fArmed isEqualTo "" || {(_f get "armed") isEqualTo "yes"}}
};
private _err = _store getOrDefault ["err", ""];
if ((count _all) isEqualTo 0 && {_age < 0} && {_err isEqualTo ""}) then { _rows pushBack ["skeleton", 3, "Lecture des alertes…"]; };
if (_err isNotEqualTo "" && {(count _all) isEqualTo 0}) then {
    _rows pushBack ["empty", _d + "ui_warning.paa", [_err] call _esc, "ACTUALISER, en bas, relit les alertes d'Athena."];
};
if ((count _shown) isEqualTo 0 && {(count _all) > 0}) then {
    _rows pushBack ["empty", _d + "ui_search.paa", "Aucune alerte pour ces filtres", "Retirez un filtre pour revoir les autres alertes.", ["EFFACER LES FILTRES", { ["clearFilters"] call comspec_atak_native_fnc_alertesAction; }]];
};
if ((count _all) isEqualTo 0 && {_age >= 0} && {_err isEqualTo ""}) then {
    _rows pushBack ["empty", _d + "ui_check.paa", "Aucune alerte active", "Rien n'est diffusé à votre niveau d'habilitation. ÉMETTRE, en haut, en crée une ; ACTUALISER relit le portail."];
};
private _inbox = (["inbox"] call comspec_atak_native_fnc_fichesAction) apply { [_x param [1, ""], _x param [0, ""]] };
{
    private _f = _x;
    ([_f] call _edge) params ["_rgb", "_hex"];
    private _lv = [_f get "level"] call _level;
    private _read = ["isRead", _f] call comspec_atak_native_fnc_alertesAction;
    private _ago = [["ageOf", _f get "updated"] call comspec_atak_native_fnc_alertesAction] call _agoText;
    private _from = (_inbox select { (_x select 0) isEqualTo (_f get "ref") }) param [0, []];
    // À droite : niveau (forme, rang) à sa couleur, ARMÉ, âge, non lu.
    private _metaTxt = format ["<t font='COMSPEC_BarlowBold' color='%1'>%2 %3/4</t>%4%5%6", _lv select 2, [_lv select 4] call _glyph, _f get "rank",
        ["", format [" <t font='COMSPEC_BarlowBold' color='%1'>· ARMÉ</t>", _alert]] select ((_f get "armed") isEqualTo "yes"),
        ["", " · " + _ago] select (_ago isNotEqualTo ""),
        [format [" · <t font='COMSPEC_BarlowBold' color='%1'>NON LU</t>", _warn], format [" · <t color='%1'>lu</t>", _muted]] select _read];
    private _body = format ["%1 · %2%3%4%5", [_f] call _docTitle, _f get "ref",
        ["", " · " + (_f get "zone")] select ((_f get "zone") isNotEqualTo ""),
        ["", " · périssable"] select ((_f get "soon") isEqualTo true),
        ["", format [" · reçue de %1", _from param [1, ""]]] select (_from isNotEqualTo [])];
    _rows pushBack ["notif", _d + (["ui_eye.paa", "app_wanted.paa"] select ((_f get "doc") isEqualTo "avis")), _rgb,
        ["name", [_f get "name", "Sujet non identifié"]] call comspec_atak_native_fnc_sseHub, _metaTxt, _body, ["open", _f get "ref"] call _act, {}, "Ouvrir l'alerte", !_read];
} forEach _shown;
_rows pushBack ["buttons", [
    [format ["ACTUALISER%1", ["", format [" (%1)", _store getOrDefault ["clock", ""]]] select ((_store getOrDefault ["clock", ""]) isNotEqualTo "")], { ["load", true] call comspec_atak_native_fnc_alertesAction; }, true],
    ["FICHES", { ["FICHES"] call comspec_atak_native_fnc_navigate; }, false, true, "Toutes les fiches du portail"],
    ["RECHERCHÉS", { ["WANTED"] call comspec_atak_native_fnc_navigate; }, false, true, "Personnes recherchées, avec leur photo"]
]];
_rows pushBack ([_viewerMark] call _banner);
_rows pushBack ([] call _footer);
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
