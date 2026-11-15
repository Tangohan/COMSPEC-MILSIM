/*
    App « Avis et vigilance » : assistant d'émission (vue « new » de la page SSEALERT), en sept étapes courtes, une décision par écran :
      TYPE      fiche de vigilance, avis de recherche ou notice de couleur : une tuile par type, au liseré de sa couleur
      SUJET     une personne que le téléphone connaît déjà (Recherchés, contrôles d'identité, SEEK II, fiches) ou un inconnu décrit
                (sexe, taille, corpulence, tenue, signes)
      NIVEAU    niveau de vigilance en tuiles de couleur, « armé et dangereux » ; le bloc d'alerte se voit tel qu'il sera diffusé
      MOTIFS    motifs (M-…) et mesures (A-…) par codes du catalogue : recherche, récents en tête ; consigne en clair
      ZONE      ma position, un point touché sur la carte ou une grille saisie ; lieu et zone
      VALIDITÉ  durée, service à informer, délai de compte rendu
      ENVOI     récapitulatif façon document, puis ÉMETTRE (ou PROPOSER AU BUREAU sans le droit d'émettre)
    Frise d'étapes en tête, PRÉCÉDENT / SUIVANT en pied, brouillon gardé à chaque geste (fn_alertesDraft). Ce qui manque s'écrit sous
    la frise. Aucun toast, aucun hint, aucun systemChat. Les champs de saisie portent le préfixe « av_ ».
    Params (fn_pageAlertes) : [page, rectangle].
*/
params [["_page", "SSEALERT"], ["_rect", []]];
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _d = "\z\comspec_atak_native\addons\main\data\";
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
_s set ["backSub", ["SSEALERT", { ["prev"] call comspec_atak_native_fnc_alertesDraft; }]];
private _muted = COMSPEC_ATAK_Th get "hexTextMuted";
private _dim = COMSPEC_ATAK_Th get "hexTextDim";
private _warn = COMSPEC_ATAK_Th get "hexWarn";
private _okHex = COMSPEC_ATAK_Th get "hexOk";
private _en = (["lang"] call comspec_atak_native_fnc_alertesAction) isEqualTo "en";
private _dact = { params ["_a", ["_v", ""]]; compile format ["[%1, %2] call comspec_atak_native_fnc_alertesDraft;", str _a, str _v] };
private _esc = {
    params ["_t"];
    if !(_t isEqualType "") then { _t = str _t; };
    { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]];
    _t
};
private _cat = { params ["_section"]; ["cat", _section] call comspec_atak_native_fnc_alertesAction };
private _lab = { params ["_row"]; private _t = _row param [[1, 2] select _en, ""]; if (_t isEqualType "") then { _t } else { "" } };
private _glyph = { params ["_shape"]; createHashMapFromArray [["triangle", "▲"], ["diamond", "■"], ["octagon", "●"]] getOrDefault [_shape, "▲"] };
private _dr = ["get"] call comspec_atak_native_fnc_alertesDraft;
private _step = _dr get "step";
private _names = ["steps"] call comspec_atak_native_fnc_alertesDraft;
private _gate = ["gate"] call comspec_atak_native_fnc_alertesAction;
private _proposal = ["proposal"] call comspec_atak_native_fnc_alertesDraft;
private _picked = format ["<t color='%1'>CHOISI ✓</t>", _okHex];

private _rows = [];
_rows pushBack ["header", "Émettre une alerte", format ["Étape %1 sur %2 · %3", _step + 1, count _names, _names select _step],
    [["EFFACER", { ["clear"] call comspec_atak_native_fnc_alertesDraft; }, false]], { ["prev"] call comspec_atak_native_fnc_alertesDraft; }];
private _frise = [];
{ _frise pushBack [_x, switch (true) do { case (_forEachIndex < _step): { "done" }; case (_forEachIndex isEqualTo _step): { "now" }; default { "todo" }; }]; } forEach _names;
_rows pushBack ["steps", _frise];
if (_gate isNotEqualTo "") then {
    _rows pushBack ["strip", format ["<t size='0.85' color='%1'>%2</t>", _warn, [["gateText", _gate] call comspec_atak_native_fnc_alertesAction] call _esc], "", [0.18, 0.12, 0.03, 1]];
};
if ((_dr get "hint") isNotEqualTo "") then {
    _rows pushBack ["text", format ["<t color='%1'>%2</t>", _warn, [_dr get "hint"] call _esc], [0.18, 0.12, 0.03, 1]];
};
// Bloc d'alerte tel qu'il sera diffusé : niveau déduit de la menace et de l'armement (règle du site).
private _levelBanner = {
    (["rank"] call comspec_atak_native_fnc_alertesDraft) params ["_key", "_rank"];
    (["level", _key] call comspec_atak_native_fnc_alertesAction) params ["_title", "", "_hex", "_rgb", "_shape"];
    private _ink = ["#14110a", "#ffffff"] select (_rank >= 3);
    ["banner", format ["<t font='COMSPEC_BarlowBold' size='1.3' color='%1'>%2 %3</t><br/><t font='EtelkaMonospaceProBold' size='0.85' color='%1'>NIVEAU %4/4</t>", _ink, [_shape] call _glyph, [_title] call _esc, _rank], _rgb]
};

switch (_step) do {
    // ------------------------------------------------------------------------------------------------ TYPE
    case 0: {
        _rows pushBack ["notif", _d + "ui_eye.paa", [0.95, 0.76, 0, 1], "FICHE DE VIGILANCE", [_picked, ""] select ((_dr get "doc") isNotEqualTo "vigilance"),
            "Faire surveiller une personne ou une situation, sans la rechercher", ["set", ["doc", "vigilance"]] call _dact, {}, "", (_dr get "doc") isEqualTo "vigilance"];
        private _types = ["type"] call _cat;
        if (_types isEqualTo []) then {
            _rows pushBack ["text", format ["<t color='%1'>Types d'avis : catalogue du portail pas encore lu. ACTUALISER dans la liste, puis revenez ; la fiche de vigilance s'émet déjà.</t>", _muted]];
        };
        {
            private _key = _x param [0, ""];
            private _c = ["color", _x param [3, ""]] call comspec_atak_native_fnc_alertesAction;
            private _title = _x param [[4, 5] select _en, ""];
            if (!(_title isEqualType "") || {_title isEqualTo ""}) then { _title = [_x] call _lab; };
            private _sub = _x param [[6, 7] select _en, ""];
            if !(_sub isEqualType "") then { _sub = ""; };
            private _on = (_dr get "doc") isEqualTo "avis" && {(_dr get "type") isEqualTo _key};
            _rows pushBack ["notif", _d + "app_wanted.paa", [_c select 2, [0.7, 0.76, 0.73, 1]] select ((count (_c select 2)) isNotEqualTo 4), toUpper _title,
                [_picked, ""] select !_on, _sub, ["set", ["type", _key]] call _dact, {}, "", _on];
        } forEach _types;
    };
    // ------------------------------------------------------------------------------------------------ SUJET
    case 1: {
        private _unknown = (_dr get "subjMode") isEqualTo "unknown";
        _rows pushBack ["segment", "", [
            ["PERSONNE CONNUE", ["set", ["subjMode", "known"]] call _dact, !_unknown],
            ["INCONNU À DÉCRIRE", ["unknown"] call _dact, _unknown]
        ]];
        if (!_unknown) then {
            if ((_dr get "subjectName") isNotEqualTo "") then {
                _rows pushBack ["status", _d + "app_profile.paa", [0.36, 0.78, 0.42, 1], [_dr get "subjectName"] call _esc,
                    format ["%1 · %2", [_dr get "subjectFrom"] call _esc, [[_dr get "subject", "sans référence au registre"] select ((_dr get "subject") isEqualTo "")] call _esc],
                    ["", "Sans référence au registre : l'alerte part en proposition, à valider par le bureau."] select ((_dr get "subject") isEqualTo "")];
            };
            _rows pushBack ["field", "av_q", "Chercher dans ce que le téléphone connaît (nom ou référence)", _dr get "q", "CHERCHER", { ["step", ((["get"] call comspec_atak_native_fnc_alertesDraft) get "step")] call comspec_atak_native_fnc_alertesDraft; }, true];
            private _found = ["subjects", _dr get "q"] call comspec_atak_native_fnc_alertesDraft;
            if (_found isEqualTo []) then {
                _rows pushBack ["text", format ["<t color='%1'>Personne de ce nom dans les Recherchés, les contrôles d'identité, le SEEK II ni les fiches du poste. Saisissez sa référence, ou décrivez un inconnu.</t>", _muted]];
            };
            {
                _x params ["_nm", "_ref", "_from"];
                private _on = (_dr get "subjectName") isEqualTo _nm && {(_dr get "subject") isEqualTo _ref};
                _rows pushBack ["row", "", [_nm] call _esc, format ["%1 · %2", _from, [[_ref, "sans référence au registre"] select (_ref isEqualTo "")] call _esc], ["", "✓"] select _on,
                    ["pick", [_ref, _nm, _from]] call _dact, [], toUpper (_nm select [0, 1]), [[], [0.36, 0.78, 0.42, 1]] select _on];
            } forEach _found;
            _rows pushBack ["edit", "av_subject", "Référence d'identité ou de personne, si vous la connaissez", _dr get "subject"];
        } else {
            private _seg = {
                params ["_label", "_key", "_opts"];
                ["segment", _label, _opts apply { [toUpper _x, ["set", [_key, [_x, ""] select ((_dr get _key) isEqualTo _x)]] call _dact, (_dr get _key) isEqualTo _x] }]
            };
            _rows pushBack (["Sexe", "sex", ["Homme", "Femme", "Non déterminé"]] call _seg);
            _rows pushBack (["Corpulence", "build", ["Mince", "Moyenne", "Forte", "Athlétique"]] call _seg);
            _rows pushBack ["edit", "av_height", "Taille (ex. 1,80 m)", _dr get "height"];
            _rows pushBack ["edit", "av_clothes", "Tenue (couleurs, coiffe, sac)", _dr get "clothes"];
            _rows pushBack ["edit", "av_marks", "Signes particuliers (barbe, cicatrice, tatouage, démarche)", _dr get "marks"];
            _rows pushBack ["text", format ["<t size='0.8' color='%1'>Un inconnu part en proposition : le bureau le rattache au registre avant de diffuser.</t>", _muted]];
        };
    };
    // ------------------------------------------------------------------------------------------------ NIVEAU
    case 2: {
        _rows pushBack (call _levelBanner);
        {
            _x params ["_key", "_threat", "_rule"];
            (["level", _key] call comspec_atak_native_fnc_alertesAction) params ["_title", "_rank", "", "_rgb", "_shape"];
            private _on = (_dr get "threat") isEqualTo _threat || {_key isEqualTo "caution" && {(_dr get "threat") isEqualTo "critical"}};
            _rows pushBack ["notif", _d + "ui_warning.paa", _rgb, format ["%1 %2", [_shape] call _glyph, _title], [_picked, ""] select !_on, _rule,
                ["set", ["threat", _threat]] call _dact, {}, "", _on];
        } forEach [["watch", "low", "Menace faible ou non précisée"], ["reinforced", "medium", "Menace moyenne"], ["caution", "high", "Menace élevée ou critique"]];
        if ((_dr get "threat") in ["high", "critical"]) then {
            _rows pushBack ["segment", "Menace", [
                ["ÉLEVÉE", ["set", ["threat", "high"]] call _dact, (_dr get "threat") isEqualTo "high"],
                ["CRITIQUE", ["set", ["threat", "critical"]] call _dact, (_dr get "threat") isEqualTo "critical"]
            ]];
        };
        _rows pushBack ["segment", "Armement", [
            ["ARMÉ ET DANGEREUX", ["set", ["armed", "yes"]] call _dact, (_dr get "armed") isEqualTo "yes"],
            ["NON ARMÉ", ["set", ["armed", "no"]] call _dact, (_dr get "armed") isEqualTo "no"],
            ["INCONNU", ["set", ["armed", "unknown"]] call _dact, (_dr get "armed") isEqualTo "unknown"]
        ], "armé : niveau 4, quelle que soit la menace"];
    };
    // ------------------------------------------------------------------------------------------------ MOTIFS
    case 3: {
        private _tab = _dr getOrDefault ["codesTab", "reasons"];
        _rows pushBack ["tabs", [
            ["MOTIFS", ["set", ["codesTab", "reasons"]] call _dact, _tab isEqualTo "reasons", count (_dr get "reasons")],
            ["MESURES", ["set", ["codesTab", "measures"]] call _dact, _tab isEqualTo "measures", count (_dr get "measures")],
            ["CONSIGNE", ["set", ["codesTab", "text"]] call _dact, _tab isEqualTo "text", ["", "1"] select ((_dr get "text") isNotEqualTo "")]
        ]];
        if (_tab isEqualTo "text") then {
            _rows pushBack ["memo", "av_text", "Conduite à tenir, en clair (400 caractères)", _dr get "text", 4];
            _rows pushBack ["text", format ["<t size='0.8' color='%1'>Une phrase par consigne. Les mesures codifiées s'affichent d'abord, numérotées, puis ce texte.</t>", _muted]];
        } else {
            private _section = ["reason", "measure"] select (_tab isEqualTo "measures");
            private _qKey = ["qr", "qm"] select (_tab isEqualTo "measures");
            private _chosen = _dr get _tab;
            private _all = [_section] call _cat;
            // Codes choisis : pastilles (un toucher retire le code).
            if (_chosen isNotEqualTo []) then {
                _rows pushBack ["chips", _chosen apply { [format ["%1 ✕", _x], [0.36, 0.78, 0.42, 1], ["toggle", [_tab, _x]] call _dact] }];
            };
            _rows pushBack ["field", "av_" + _qKey, ["Chercher un motif (code M-… ou mot)", "Chercher une mesure (code A-… ou mot)"] select (_tab isEqualTo "measures"), _dr get _qKey, "CHERCHER",
                { ["step", ((["get"] call comspec_atak_native_fnc_alertesDraft) get "step")] call comspec_atak_native_fnc_alertesDraft; }, true];
            private _q = toLower (_dr get _qKey);
            private _recent = profileNamespace getVariable ["COMSPEC_ATAK_AlertesRecents", []];
            if !(_recent isEqualType []) then { _recent = []; };
            // Récents en tête, puis l'ordre du catalogue ; 14 lignes au plus : la recherche resserre.
            private _match = _all select { _q isEqualTo "" || {((toLower (_x param [0, ""])) find _q) >= 0} || {((toLower ([_x] call _lab)) find _q) >= 0} };
            private _first = _match select { (_x param [0, ""]) in _recent };
            private _sorted = _first + (_match - _first);
            if (_all isEqualTo []) then {
                _rows pushBack ["text", format ["<t color='%1'>Catalogue du portail pas encore lu : ACTUALISER dans la liste, ou écrivez la consigne en clair (onglet CONSIGNE).</t>", _muted]];
            };
            if (_all isNotEqualTo [] && {_sorted isEqualTo []}) then {
                _rows pushBack ["text", format ["<t color='%1'>Aucun code pour cette recherche.</t>", _muted]];
            };
            {
                private _code = _x param [0, ""];
                private _on = _code in _chosen;
                _rows pushBack ["row", "", _code, [[_x] call _lab] call _esc, [["", "récent"] select (_code in _recent), "✓"] select _on,
                    ["toggle", [_tab, _code]] call _dact, [], ["M", "A"] select (_tab isEqualTo "measures"), [[], [0.36, 0.78, 0.42, 1]] select _on];
            } forEach (_sorted select [0, 14]);
            if ((count _sorted) > 14) then {
                _rows pushBack ["text", format ["<t size='0.8' color='%1'>%2 autres codes : précisez la recherche.</t>", _muted, (count _sorted) - 14]];
            };
        };
    };
    // ------------------------------------------------------------------------------------------------ ZONE
    case 4: {
        private _pt = ["mapPoint"] call comspec_atak_native_fnc_alertesDraft;
        if (_pt isNotEqualTo []) then {
            _rows pushBack ["big", [[format ["UTILISER LE POINT TOUCHÉ · %1", [_pt, 8] call comspec_atak_native_fnc_gridRef], { ["mapUse"] call comspec_atak_native_fnc_alertesDraft; }, "green", true, "Le dernier point touché sur la carte devient la grille"]]];
        };
        _rows pushBack ["big", [
            ["MA POSITION", { ["here"] call comspec_atak_native_fnc_alertesDraft; }, "", true, "Grille et lieu-dit de ma position"],
            ["POINTER SUR LA CARTE", { ["mapArm"] call comspec_atak_native_fnc_alertesDraft; }, "", true, "Ouvre la carte : touchez le point, puis revenez"]
        ]];
        _rows pushBack ["edit", "av_grid", "Grille", _dr get "grid"];
        _rows pushBack ["edit", "av_place", "Dernier lieu connu", _dr get "place"];
        _rows pushBack ["edit", "av_zone", "Zone (secteur, quartier, axe)", _dr get "zone"];
    };
    // ------------------------------------------------------------------------------------------------ VALIDITÉ
    case 5: {
        _rows pushBack ["segment", "Validité", [7, 14, 30, 60, 90, 180] apply { [format ["%1 J", _x], ["set", ["days", _x]] call _dact, (_dr get "days") isEqualTo _x] },
            "l'alerte reste active jusqu'à cette échéance, ou jusqu'à sa levée"];
        _rows pushBack ["segment", "Compte rendu attendu", (["delay"] call _cat) apply { [toUpper ([_x] call _lab), ["set", ["delay", _x param [0, ""]]] call _dact, (_dr get "delay") isEqualTo (_x param [0, ""])] }];
        _rows pushBack ["section", "Service à informer", "Celui qui reçoit les signalements"];
        private _svcs = ["service"] call _cat;
        if (_svcs isEqualTo []) then {
            _rows pushBack ["text", format ["<t color='%1'>Service par défaut de la communauté (catalogue pas encore lu).</t>", _muted]];
        };
        {
            private _code = _x param [0, ""];
            // Par défaut : celui que la communauté a désigné, tant que rien n'est choisi.
            private _on = (_dr get "service") isEqualTo _code || {(_dr get "service") isEqualTo "" && {(_x param [7, false]) isEqualTo true}};
            private _sigle = _x param [3, ""];
            private _chan = _x param [8, ""];
            if (!(_sigle isEqualType "") || {_sigle isEqualTo ""}) then { _sigle = "S"; };
            if !(_chan isEqualType "") then { _chan = ""; };
            _rows pushBack ["row", "", [[_x] call _lab] call _esc, [_chan] call _esc, ["", "✓"] select _on,
                ["set", ["service", _code]] call _dact, [], _sigle select [0, 4], [[], [0.36, 0.78, 0.42, 1]] select _on];
        } forEach _svcs;
    };
    // ------------------------------------------------------------------------------------------------ ENVOI
    default {
        private _missing = ["missing"] call comspec_atak_native_fnc_alertesDraft;
        private _docName = if ((_dr get "doc") isEqualTo "avis") then {
            private _row = ((["type"] call _cat) select { (_x param [0, ""]) isEqualTo (_dr get "type") }) param [0, []];
            private _t = _row param [[4, 5] select _en, ""];
            [toUpper _t, "AVIS DE RECHERCHE"] select (!(_t isEqualType "") || {_t isEqualTo ""})
        } else { "FICHE DE VIGILANCE" };
        _rows pushBack ["strip", format ["<t font='COMSPEC_BarlowBold' size='0.9'>%1</t>", [_docName] call _esc],
            format ["<t font='EtelkaMonospacePro' size='0.85' color='%1'>%2</t>", _warn, ["À ÉMETTRE", "À PROPOSER AU BUREAU"] select _proposal], [0.071, 0.09, 0.114, 1]];
        _rows pushBack (call _levelBanner);
        private _desc = ["describe"] call comspec_atak_native_fnc_alertesDraft;
        private _who = [_dr get "subjectName", "Sujet non identifié"] select ((_dr get "subjectName") isEqualTo "");
        _rows pushBack ["person", _d + "app_wanted.paa", format ["<t font='COMSPEC_BarlowBold' size='1.25'>%1</t><br/><t size='0.85' color='%2'>%3</t>", [_who] call _esc, _muted,
            [[_dr get "subject", _desc] select ((_dr get "subject") isEqualTo "")] call _esc], []];
        private _codes = {
            params ["_section", "_list"];
            (_list apply {
                private _code = _x;
                private _row = (([_section] call _cat) select { (_x param [0, ""]) isEqualTo _code }) param [0, []];
                format ["<t font='EtelkaMonospaceProBold' color='%1'>%2</t> %3", _dim, _code, [[_row] call _lab] call _esc]
            }) joinString "<br/>"
        };
        private _fact = { params ["_label", "_v"]; if (_v isNotEqualTo "") then { _rows pushBack ["info", _label, _v]; }; };
        ["Motifs", ["reason", _dr get "reasons"] call _codes] call _fact;
        ["Mesures", ["measure", _dr get "measures"] call _codes] call _fact;
        ["Conduite à tenir", [_dr get "text"] call _esc] call _fact;
        ["Lieu", [_dr get "place"] call _esc] call _fact;
        ["Zone", [_dr get "zone"] call _esc] call _fact;
        ["Grille", [_dr get "grid"] call _esc] call _fact;
        ["Validité", format ["%1 jours", _dr get "days"]] call _fact;
        private _svc = ["label", ["service", _dr get "service"]] call comspec_atak_native_fnc_alertesAction;
        ["Service à informer", [[_svc, "Service par défaut de la communauté"] select ((_dr get "service") isEqualTo "")] call _esc] call _fact;
        ["Compte rendu", [["label", ["delay", _dr get "delay"]] call comspec_atak_native_fnc_alertesAction] call _esc] call _fact;
        if (_missing isNotEqualTo []) then {
            _rows pushBack ["text", format ["<t font='COMSPEC_BarlowBold' color='%1'>Il manque</t><br/><t color='%1'>%2</t>", _warn, (_missing apply { "· " + _x }) joinString "<br/>"], [0.18, 0.12, 0.03, 1]];
        };
        if (_proposal) then {
            _rows pushBack ["text", format ["<t size='0.85' color='%1'>%2</t>", _dim,
                ["Sujet sans référence au registre : l'envoi part en PROPOSITION, à valider par le bureau avant diffusion.",
                 "Votre compte n'a pas le droit d'émettre ce document : l'envoi part en PROPOSITION, à valider par le bureau avant diffusion."] select ((_dr get "subject") isNotEqualTo "")],
                [0.05, 0.08, 0.11, 1]];
        };
        _rows pushBack ["big", [[["ÉMETTRE L'ALERTE", "PROPOSER AU BUREAU"] select _proposal, { ["submit"] call comspec_atak_native_fnc_alertesDraft; }, ["green", "amber"] select _proposal, _missing isEqualTo [],
            ["Diffuse l'alerte à tous les téléphones habilités", "Envoie la proposition à la file « À valider » du bureau"] select _proposal]]];
    };
};
private _nav = [[["‹ PRÉCÉDENT", "‹ LISTE"] select (_step isEqualTo 0), { ["prev"] call comspec_atak_native_fnc_alertesDraft; }]];
if (_step < ((count _names) - 1)) then { _nav pushBack ["SUIVANT ›", { ["next"] call comspec_atak_native_fnc_alertesDraft; }, true]; };
_rows pushBack ["buttons", _nav];
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
