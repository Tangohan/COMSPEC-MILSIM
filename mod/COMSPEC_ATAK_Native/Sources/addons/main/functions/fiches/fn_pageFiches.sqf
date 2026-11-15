/*
    App Fiches : les fiches du portail SSE lues sur le téléphone (ICD-SSE-FICHES-001). Trois écrans, sans aucun toast ni hint :
    tous les retours passent par la ligne d'état en haut de l'écran.
      Liste   : bandeau de classification de l'habilitation du porteur, ligne d'état (synchro, hors ligne, il y a N min), filtres
                (genre, menace, type, zone), une ligne par fiche (nom, alias, barre de menace, genre, référence, marquage), REÇUES.
      Détail  : fiche façon renseignement américain (BOLO) : bandeau de classification en haut et en bas, en-tête du service, nom,
                menace en barre, ARMÉ ET DANGEREUX, rubriques Motif, Mesures à prendre, Description, Dernier lieu connu, Zone, véhicules,
                adresses, personnes liées, validité. PARTAGER envoie la référence, ACTUALISER relit le site.
      Partage : appareils Bluetooth appairés à portée et joueurs (SMS) ; seule la référence part, le destinataire lit la fiche selon son
                habilitation (le site filtre).
    Réglages de lecture : taille du texte (A- / A+) et mode compact, gardés pour la partie. Données et actions : fn_fichesAction.
    État : COMSPEC_ATAK_State fichesOpen (référence), fichesShare, fichesView (ALL | avis | sheets | inbox), fichesF_type | threat | zone,
    fichesScale (0.85 | 1 | 1.2), fichesCompact, fichesStatus [texte, tick].
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _d = "\z\comspec_atak_native\addons\main\data\";
if !([] call comspec_atak_native_fnc_bridge) exitWith {
    [[["empty", _d + "app_wanted.paa", "Fiches indisponibles sur ce serveur",
        "Les fiches viennent d'Athena par COMSPEC Overwatch, qui n'est pas chargé ici. Demandez à l'administrateur du serveur de l'ajouter."]], [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
    true
};
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _store = uiNamespace getVariable ["COMSPEC_ATAK_Fiches", createHashMap];
// Lecture au démarrage de l'app : relit le site seulement si la liste est absente ou périmée (tâche de fond).
["ensure"] call comspec_atak_native_fnc_fichesAction;
_store = uiNamespace getVariable ["COMSPEC_ATAK_Fiches", createHashMap];

private _muted = COMSPEC_ATAK_Th get "hexTextMuted";
private _dim = COMSPEC_ATAK_Th get "hexTextDim";
private _warn = COMSPEC_ATAK_Th get "hexWarn";
private _alert = COMSPEC_ATAK_Th get "hexAlert";
private _en = ("Oui" call comspec_atak_native_fnc_tr) isNotEqualTo "Oui";
private _z = _s getOrDefault ["fichesScale", 1];
if !(_z isEqualType 0) then { _z = 1; };
private _compact = _s getOrDefault ["fichesCompact", false];
private _act = { params ["_a", ["_v", ""]]; compile format ["[%1, %2] call comspec_atak_native_fnc_fichesAction;", str _a, str _v] };
private _esc = {
    params ["_t"];
    if !(_t isEqualType "") then { _t = str _t; };
    { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]];
    _t
};
private _escBr = { params ["_t"]; ([_t] call _esc) regexReplace [toString [10], "<br/>"] };
// Marquage de classification : couleurs des bandeaux américains (vert, bleu, rouge, orange). [fond RGBA, texte, accent].
private _markCol = {
    params ["_m"];
    private _u = toUpper _m;
    switch (true) do {
        case ((_u select [0, 3]) isEqualTo "TOP"): { [[1, 0.404, 0.122, 1], "#000000", "#ff671f"] };
        case ((_u select [0, 6]) isEqualTo "SECRET"): { [[0.784, 0.063, 0.18, 1], "#ffffff", "#ff5a5a"] };
        case ((_u select [0, 12]) isEqualTo "CONFIDENTIAL"): { [[0, 0.2, 0.627, 1], "#ffffff", "#6f9bff"] };
        case ((_u select [0, 12]) isEqualTo "UNCLASSIFIED"): { [[0, 0.478, 0.2, 1], "#ffffff", "#3ccf6a"] };
        case ((_u select [0, 3]) isEqualTo "CUI"): { [[0.314, 0.157, 0.471, 1], "#ffffff", "#b78cff"] };
        default { [[0.28, 0.3, 0.31, 1], "#ffffff", "#b4c1bb"] };
    }
};
private _banner = {
    params ["_m"];
    (_m call _markCol) params ["_bg", "_tx"];
    ["banner", format ["<t font='COMSPEC_BarlowBold' size='1.05' color='%1'>%2</t>", _tx, [_m] call _esc], _bg]
};
// Menace : libellé, rang (1 à 4), couleur.
private _threat = createHashMapFromArray [
    ["low", ["Faible", 1, "#5cc76b"]], ["medium", ["Moyen", 2, "#f2ab33"]],
    ["high", ["Élevé", 3, "#ff8a3d"]], ["critical", ["Critique", 4, "#e5483a"]]
];
private _bar = {
    params ["_t"];
    (_threat getOrDefault [_t, ["", 0, _dim]]) params ["_n", "_k", "_c"];
    private _out = "";
    for "_i" from 1 to 4 do {
        _out = _out + format ["<t color='%1'>%2</t> ", [_dim, _c] select (_i <= _k), "||"];
    };
    _out
};
// Clé de filtre « type » d'une fiche : « avis:hvt » pour un avis (son type), le genre sinon (« vigilance »).
private _typeKey = {
    params ["_f"];
    if ((_f get "kind") isEqualTo "avis") then { "avis:" + (_f get "type") } else { _f get "kind" }
};
private _threatName = { params ["_t"]; ((_threat getOrDefault [_t, ["", 0, ""]]) select 0) };
private _threatRgb = {
    params ["_t"];
    switch (_t) do {
        case "low": { [0.36, 0.78, 0.42, 1] };
        case "medium": { [0.95, 0.67, 0.2, 1] };
        case "high": { [1, 0.54, 0.24, 1] };
        case "critical": { [0.9, 0.28, 0.23, 1] };
        default { [0.8, 0.85, 0.82, 1] };
    }
};
private _types = _store getOrDefault ["types", createHashMap];
private _typeLabel = {
    params ["_f"];
    private _t = _f get "type";
    if ((_f get "kind") isNotEqualTo "avis") exitWith { ["kindLabel", _f get "kind"] call comspec_atak_native_fnc_fichesAction };
    private _lab = _types getOrDefault [_t, []];
    if ((count _lab) isEqualTo 2) then { _lab select ([0, 1] select _en) } else { "Avis de recherche" }
};
// Il y a N min : depuis la dernière lecture réussie.
private _agoText = {
    params ["_m"];
    if (_m < 0) exitWith { "" };
    if (_m < 1) exitWith { "à l'instant" };
    if (_m < 60) exitWith { format ["il y a %1 min", floor _m] };
    format ["il y a %1 h", floor (_m / 60)]
};
private _all = ["list"] call comspec_atak_native_fnc_fichesAction;
private _meta = _store getOrDefault ["meta", []];
private _viewerMark = _meta param [3, "CUI"];
if !(_viewerMark isEqualType "") then { _viewerMark = "CUI"; };
private _authority = _meta param [[0, 1] select _en, ""];
if (!(_authority isEqualType "") || {_authority isEqualTo ""}) then { _authority = ["Bureau international de recherche", "International Search Bureau"] select _en; };
private _age = ["age"] call comspec_atak_native_fnc_fichesAction;
private _offline = (_store getOrDefault ["src", ""]) isEqualTo "poste" || {(_store getOrDefault ["err", ""]) isNotEqualTo "" && {(count _all) > 0}};
private _busy = _store getOrDefault ["busy", false];

// Ligne d'état : message du moment (30 s) sinon l'état de la synchronisation.
private _statusRow = {
    params [["_right", ""]];
    private _msg = _s getOrDefault ["fichesStatus", ["", 0]];
    private _left = "";
    private _bg = [0.071, 0.09, 0.114, 1];
    if ((_msg select 0) isNotEqualTo "" && {(diag_tickTime - (_msg select 1)) < 30 || {_busy}}) then {
        _left = [_msg select 0] call _esc;
    } else {
        private _clock = _store getOrDefault ["clock", ""];
        switch (true) do {
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
    ["strip", format ["<t font='EtelkaMonospacePro' size='0.85'>%1</t>", _left], format ["<t font='EtelkaMonospacePro' size='0.85'>%1</t>", _right], _bg]
};
private _footer = {
    ["text", format ["<t size='0.72' color='%1'>%2</t>", _muted, "Les fiches viennent du portail Athena et sont filtrées selon votre habilitation."]]
};

// ---------------------------------------------------------------------------------------------------------------- Détail
private _open = _s getOrDefault ["fichesOpen", ""];
private _sharing = _s getOrDefault ["fichesShare", false];
if (_open isNotEqualTo "") exitWith {
    _s set ["backSub", ["FICHES", { ["back"] call comspec_atak_native_fnc_fichesAction; }]];
    private _f = ["get", _open] call comspec_atak_native_fnc_fichesAction;
    private _rows = [];
    if ((count _f) isEqualTo 0) then {
        // Fiche reçue ou ouverte par sa référence : elle se lit dans MA liste, donc selon MON habilitation.
        _rows pushBack ([_viewerMark] call _banner);
        _rows pushBack ([""] call _statusRow);
        _rows pushBack ["empty", _d + "app_wanted.paa", format ["Fiche %1 introuvable", [_open] call _esc],
            "Elle n'est pas dans votre liste : actualisez, ou elle est au-dessus de votre habilitation, retirée ou expirée."];
        _rows pushBack ["buttons", [["‹ RETOUR", { ["back"] call comspec_atak_native_fnc_fichesAction; }], ["ACTUALISER", { ["load", true] call comspec_atak_native_fnc_fichesAction; }, true]]];
        [_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
        true
    } else {
        private _mark = _f get "mark";
        // ---------- Partage
        if (_sharing) then {
            _s set ["backSub", ["FICHES", { ["shareClose"] call comspec_atak_native_fnc_fichesAction; }]];
            _rows pushBack ([_mark] call _banner);
            _rows pushBack ([[_f get "ref"] call _esc] call _statusRow);
            private _title = ["title", _open] call comspec_atak_native_fnc_fichesAction;
            _rows pushBack ["text", format ["<t font='COMSPEC_BarlowBold' size='1.1'>%1</t><br/><t size='0.85' color='%2'>%3 · %4</t><br/><t size='0.75' color='%2'>Seule la référence part. Le destinataire lit la fiche selon SON habilitation : le site refuse ce qui la dépasse.</t>",
                [[_title, ["kindLabel", _f get "kind"] call comspec_atak_native_fnc_fichesAction] select (_title isEqualTo "")] call _esc, _muted, [_f get "ref"] call _esc, [_f get "mark"] call _esc]];
            private _btOn = ["on"] call comspec_atak_native_fnc_btAction;
            private _bt = ["state"] call comspec_atak_native_fnc_btAction;
            private _near = ["near"] call comspec_atak_native_fnc_btAction;
            _rows pushBack ["section", "Bluetooth", "Téléphones appairés à portée"];
            if (!_btOn) then {
                _rows pushBack ["text", format ["<t color='%1'>Bluetooth éteint : allumez-le dans l'app Bluetooth pour envoyer la fiche à un appareil appairé.</t>", _muted]];
            } else {
                private _paired = (_near select { _x select 4 });
                if (_paired isEqualTo []) then {
                    _rows pushBack ["text", format ["<t color='%1'>Aucun appareil appairé à portée. Appairez-le dans l'app Bluetooth (code).</t>", _muted]];
                };
                {
                    _x params ["_uid", "_name", "_dist"];
                    _rows pushBack ["person", _d + "app_profile.paa",
                        format ["<t font='COMSPEC_BarlowBold'>ATAK de %1</t><br/><t size='0.8' color='%2'>appairé · %3 m</t>", [_name] call _esc, _muted, _dist],
                        [["ENVOYER", compile format ["['shareBt', [%1, %2]] call comspec_atak_native_fnc_fichesAction;", str _open, str _uid], true]]];
                } forEach _paired;
            };
            _rows pushBack ["section", "SMS", "Joueurs de la partie"];
            private _ppl = (allPlayers select { _x isNotEqualTo player && {alive _x} }) apply { name _x };
            _ppl sort true;
            if (_ppl isEqualTo []) then {
                _rows pushBack ["text", format ["<t color='%1'>Aucun autre joueur connecté.</t>", _muted]];
            };
            {
                _rows pushBack ["person", _d + "app_chat.paa", format ["<t font='COMSPEC_BarlowBold'>%1</t>", [_x] call _esc],
                    [["ENVOYER", compile format ["['shareSms', [%1, %2]] call comspec_atak_native_fnc_fichesAction;", str _open, str _x]]]];
            } forEach (_ppl select [0, 24]);
            _rows pushBack ["buttons", [["‹ RETOUR", { ["shareClose"] call comspec_atak_native_fnc_fichesAction; }]]];
            _rows pushBack ([_mark] call _banner);
            [_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
            true
        } else {
            // ---------- Fiche
            private _kind = _f get "kind";
            private _rgb = [_f get "threat"] call _threatRgb;
            private _sz = {
                params ["_v"];
                format ["<t size='%1'>%2</t>", _z, _v]
            };
            _rows pushBack ([_mark] call _banner);
            _rows pushBack ["strip", format ["<t font='COMSPEC_BarlowBold' size='0.85'>%1</t>", toUpper ([[_f get "auth", _authority] select ((_f get "auth") isEqualTo "")] call _esc)],
                format ["<t font='EtelkaMonospacePro' size='0.85'>%1</t>", [_f get "ref"] call _esc], [0.071, 0.09, 0.114, 1]];
            _rows pushBack ([""] call _statusRow);
            private _alias = _f get "alias";
            private _who = format ["<t font='COMSPEC_BarlowBold' size='%1'>%2</t>%3<br/><t size='0.85' color='%4'>%5</t>",
                1.35 * _z, [[_f get "name", "Identité inconnue"] select ((_f get "name") isEqualTo "")] call _esc,
                ["", format ["<br/><t color='%1' size='0.9'>dit « %2 »</t>", _dim, [_alias] call _esc]] select (_alias isNotEqualTo ""),
                _muted, [[_f] call _typeLabel] call _esc];
            _rows pushBack ["person", _d + "app_wanted.paa", _who, [], _rgb];
            private _tName = [_f get "threat"] call _threatName;
            if (_tName isNotEqualTo "") then {
                _rows pushBack ["text", format ["<t font='EtelkaMonospaceProBold' size='0.95'>%1</t> <t font='COMSPEC_BarlowBold' color='%2'>%3</t>",
                    [_f get "threat"] call _bar, (_threat get (_f get "threat")) select 2, "MENACE : " + toUpper _tName]];
            };
            if ((_f get "armed") isEqualTo "yes") then {
                _rows pushBack ["banner", "<t font='COMSPEC_BarlowBold' size='1.05' color='#ffffff'>ARMÉ ET DANGEREUX</t>", [0.55, 0.05, 0.05, 1]];
            };
            private _sec = {
                params ["_title", "_text", ["_hot", false]];
                if (_text isEqualTo "") exitWith {};
                _rows pushBack ["section", _title, ""];
                _rows pushBack ["text", [format ["<t color='%1'>%2</t>", ["#e3ebe7", "#ffd2cc"] select _hot, [_text] call _escBr]] call _sz,
                    [[0.085, 0.102, 0.093, 1], [0.2, 0.07, 0.06, 1]] select _hot];
            };
            ["Motif", _f get "reason"] call _sec;
            ["Mesures à prendre", _f get "measures", true] call _sec;
            [["Signalement", "Synthèse"] select (_kind in ["profile", "control"]), _f get "desc"] call _sec;
            ["Observations", _f get "remarks"] call _sec;
            private _fact = {
                params ["_label", "_v"];
                if (_v isEqualTo "") exitWith {};
                _rows pushBack ["info", _label, [_v] call _esc];
            };
            if (!_compact || {(_f get "place") isNotEqualTo ""}) then {
                _rows pushBack ["section", "Dernier lieu connu", ""];
                ["Lieu", _f get "place"] call _fact;
                ["Vu le", _f get "seen"] call _fact;
                ["Zone", _f get "zone"] call _fact;
                if ((_f get "place") isEqualTo "" && {(_f get "zone") isEqualTo ""} && {(_f get "seen") isEqualTo ""}) then {
                    _rows pushBack ["text", format ["<t color='%1'>Aucun lieu connu.</t>", _muted]];
                };
            };
            private _links = [["Véhicules", _f get "vehicles"], ["Adresses", _f get "addresses"], ["Personnes liées", _f get "people"]] select { (count (_x select 1)) > 0 };
            if (_links isNotEqualTo []) then {
                _rows pushBack ["section", "Liens", ""];
                { _x params ["_label", "_items"]; _rows pushBack ["info", _label, (_items apply { [_x] call _esc }) joinString "<br/>"]; } forEach _links;
            };
            _rows pushBack ["section", "Validité", ""];
            ["Émise le", _f get "issued"] call _fact;
            ["Valable jusqu'au", [_f get "until", "Sans échéance"] select ((_f get "until") isEqualTo "")] call _fact;
            ["Mise à jour", _f get "updated"] call _fact;
            if ((_f get "note") isNotEqualTo "") then {
                _rows pushBack ["text", format ["<t size='0.85' color='#f2ab33'>%1</t>", [_f get "note"] call _escBr], [0.18, 0.12, 0.03, 1]];
            };
            if ((_f get "cut") isEqualTo true) then {
                _rows pushBack ["text", format ["<t size='0.8' color='%1'>Texte raccourci : lecture de la fiche entière…</t>", _muted]];
            };
            _rows pushBack ["buttons", [
                ["‹ RETOUR", { ["back"] call comspec_atak_native_fnc_fichesAction; }],
                ["PARTAGER", compile format ["['share', %1] call comspec_atak_native_fnc_fichesAction;", str _open], true],
                ["ACTUALISER", { ["load", true] call comspec_atak_native_fnc_fichesAction; }]
            ]];
            _rows pushBack ([_mark] call _banner);
            _rows pushBack ([] call _footer);
            [_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
            true
        };
    }
};

// ---------------------------------------------------------------------------------------------------------------- Liste
private _view = _s getOrDefault ["fichesView", "ALL"];
private _fType = _s getOrDefault ["fichesF_type", ""];
private _fThreat = _s getOrDefault ["fichesF_threat", ""];
private _fZone = _s getOrDefault ["fichesF_zone", ""];
private _rows = [];
_rows pushBack ([_viewerMark] call _banner);
_rows pushBack ([format ["%1 fiche(s)", count _all]] call _statusRow);
private _inbox = ["inbox"] call comspec_atak_native_fnc_fichesAction;
private _nAvis = { (_x get "kind") isEqualTo "avis" } count _all;
_rows pushBack ["segment", "", [
    [format ["TOUTES (%1)", count _all], { ["viewSet", "ALL"] call comspec_atak_native_fnc_fichesAction; }, _view isEqualTo "ALL"],
    [format ["AVIS (%1)", _nAvis], { ["viewSet", "avis"] call comspec_atak_native_fnc_fichesAction; }, _view isEqualTo "avis"],
    [format ["FICHES (%1)", (count _all) - _nAvis], { ["viewSet", "sheets"] call comspec_atak_native_fnc_fichesAction; }, _view isEqualTo "sheets"],
    [format ["REÇUES (%1)", count _inbox], { ["viewSet", "inbox"] call comspec_atak_native_fnc_fichesAction; }, _view isEqualTo "inbox"]
]];

if (_view isEqualTo "inbox") exitWith {
    _rows pushBack ["section", "Fiches reçues", "Référence seulement : la fiche s'ouvre dans votre liste, selon votre habilitation"];
    if (_inbox isEqualTo []) then {
        _rows pushBack ["empty", _d + "app_chat.paa", "Aucune fiche reçue", "Une fiche partagée par un équipier (Bluetooth ou SMS) apparaît ici."];
    };
    {
        _x params ["_from", "_ref", "_title", "_kind", "_level", "_via", "_at"];
        private _have = (count (["get", _ref] call comspec_atak_native_fnc_fichesAction)) > 0;
        _rows pushBack ["person", _d + "app_wanted.paa",
            format ["<t font='COMSPEC_BarlowBold'>%1</t>%2<br/><t size='0.8' color='%3'>%4 · de %5 · %6 · %7</t>",
                [_ref] call _esc, ["", " " + ([_title] call _esc)] select (_title isNotEqualTo ""), _muted,
                ["kindLabel", _kind] call comspec_atak_native_fnc_fichesAction, [_from] call _esc, ["SMS", "Bluetooth"] select (_via isEqualTo "bt"), _at],
            [[["OUVRIR", "ABSENTE"] select !_have, compile format ["['open', %1] call comspec_atak_native_fnc_fichesAction;", str _ref], _have, true,
                "Absente de votre liste : actualisez, ou elle dépasse votre habilitation."]]];
    } forEach _inbox;
    _rows pushBack ["buttons", [["ACTUALISER", { ["load", true] call comspec_atak_native_fnc_fichesAction; }, true]]];
    _rows pushBack ([] call _footer);
    [_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
    true
};

// Menace, type et zone : ne sont proposés que si la liste en contient plusieurs.
private _threatKeys = ["low", "medium", "high", "critical"] select { private _k = _x; (_all findIf { (_x get "threat") isEqualTo _k }) >= 0 };
if ((count _threatKeys) > 1) then {
    _rows pushBack ["segment", "", [["MENACE : TOUTES", { ["filter", ["threat", ""]] call comspec_atak_native_fnc_fichesAction; }, _fThreat isEqualTo ""]] + (_threatKeys apply {
        [toUpper ([_x] call _threatName), compile format ["['filter', ['threat', %1]] call comspec_atak_native_fnc_fichesAction;", str _x], _fThreat isEqualTo _x]
    })];
};
private _typeKeys = [];
{ _typeKeys pushBackUnique ([_x] call _typeKey); } forEach _all;
if ((count _typeKeys) > 1) then {
    private _items = [["Tous les types", ""]];
    {
        private _key = _x;
        private _f = (_all select { ([_x] call _typeKey) isEqualTo _key }) param [0, createHashMap];
        if ((count _f) > 0) then { _items pushBack [[_f] call _typeLabel, _key]; };
    } forEach _typeKeys;
    _rows pushBack ["combo", "fichesType", "Type", _items, _fType, ["OK", { params ["_v"]; ["filter", ["type", _v]] call comspec_atak_native_fnc_fichesAction; }]];
};
private _zones = [];
{ if ((_x get "zone") isNotEqualTo "") then { _zones pushBackUnique (_x get "zone"); }; } forEach _all;
_zones sort true;
if ((count _zones) > 1) then {
    _rows pushBack ["combo", "fichesZone", "Zone", [["Toutes les zones", ""]] + (_zones apply { [_x, _x] }), _fZone,
        ["OK", { params ["_v"]; ["filter", ["zone", _v]] call comspec_atak_native_fnc_fichesAction; }]];
};
private _tools = [
    ["A-", { ["scale", -1] call comspec_atak_native_fnc_fichesAction; }, false, _z > 0.9],
    ["A+", { ["scale", 1] call comspec_atak_native_fnc_fichesAction; }, false, _z < 1.15],
    [["COMPACT", "CONFORT"] select _compact, { ["compact"] call comspec_atak_native_fnc_fichesAction; }]
];
if (_fType isNotEqualTo "" || {_fThreat isNotEqualTo ""} || {_fZone isNotEqualTo ""}) then {
    _tools pushBack ["EFFACER LES FILTRES", { ["clearFilters"] call comspec_atak_native_fnc_fichesAction; }];
};
_rows pushBack ["buttons", _tools];

private _shown = _all select {
    private _f = _x;
    private _key = [_f] call _typeKey;
    (_view isEqualTo "ALL" || {_view isEqualTo "avis" && {(_f get "kind") isEqualTo "avis"}} || {_view isEqualTo "sheets" && {(_f get "kind") isNotEqualTo "avis"}})
    && {_fType isEqualTo "" || {_key isEqualTo _fType}}
    && {_fThreat isEqualTo "" || {(_f get "threat") isEqualTo _fThreat}}
    && {_fZone isEqualTo "" || {(_f get "zone") isEqualTo _fZone}}
};
if ((count _all) isEqualTo 0 && {_age < 0} && {(_store getOrDefault ["err", ""]) isEqualTo ""}) then { _rows pushBack ["skeleton", 3, "Lecture des fiches…"]; };
if ((_store getOrDefault ["err", ""]) isNotEqualTo "" && {(count _all) isEqualTo 0}) then {
    _rows pushBack ["empty", _d + "app_wanted.paa", [_store get "err"] call _esc, "ACTUALISER, en bas, relit les fiches d'Athena."];
};
if ((count _shown) isEqualTo 0 && {(count _all) > 0}) then {
    _rows pushBack ["empty", _d + "ui_search.paa", "Aucune fiche pour ces filtres", "Retirez un filtre pour revoir les autres fiches.", ["EFFACER LES FILTRES", { ["clearFilters"] call comspec_atak_native_fnc_fichesAction; }]];
};
if ((count _all) isEqualTo 0 && {_age >= 0} && {(_store getOrDefault ["err", ""]) isEqualTo ""}) then {
    _rows pushBack ["empty", _d + "app_wanted.paa", "Aucune fiche pour l'instant", "Rien n'est diffusé à votre niveau d'habilitation. ACTUALISER, en bas, relit le portail."];
};
{
    private _f = _x;
    private _rgb = [_f get "threat"] call _threatRgb;
    private _alias = _f get "alias";
    private _tName = [_f get "threat"] call _threatName;
    private _line2 = format ["<t size='0.8' color='%1'>%2%3 · %4</t>", _muted,
        ["", format ["<t color='%1'>« %2 »</t> · ", _dim, [_alias] call _esc]] select (_alias isNotEqualTo ""), [[_f] call _typeLabel] call _esc, [_f get "ref"] call _esc];
    private _accent = ([_f get "mark"] call _markCol) select 2;
    private _tCol = (_threat getOrDefault [_f get "threat", ["", 0, _dim]]) select 2;
    _rows pushBack ["person", _d + "app_wanted.paa",
        format ["<t font='COMSPEC_BarlowBold' size='%1'>%2</t><br/><t font='EtelkaMonospaceProBold' size='0.75'>%3</t> <t font='COMSPEC_BarlowBold' size='0.85' color='%4'>%5</t>%6%7<br/><t size='0.72' color='%8'>%9</t>",
            1.05 * _z, [[_f get "name", "Identité inconnue"] select ((_f get "name") isEqualTo "")] call _esc,
            [_f get "threat"] call _bar, _tCol, toUpper _tName,
            ["", format [" <t font='COMSPEC_BarlowBold' size='0.8' color='%1'>· ARMÉ</t>", _alert]] select ((_f get "armed") isEqualTo "yes"),
            ["", "<br/>" + _line2] select !_compact,
            _accent, [_f get "mark"] call _esc],
        [["OUVRIR", compile format ["['open', %1] call comspec_atak_native_fnc_fichesAction;", str (_f get "ref")], true]], _rgb];
} forEach _shown;
_rows pushBack ["buttons", [[format ["ACTUALISER%1", ["", format [" (%1)", _store getOrDefault ["clock", ""]]] select ((_store getOrDefault ["clock", ""]) isNotEqualTo "")], { ["load", true] call comspec_atak_native_fnc_fichesAction; }, true]]];
_rows pushBack ([_viewerMark] call _banner);
_rows pushBack ([] call _footer);
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
