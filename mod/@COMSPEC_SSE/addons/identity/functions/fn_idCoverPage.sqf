/*
    App « Ma couverture » du téléphone ATAK (page IDCOVER, déclarée dans COMSPEC_ATAK_Apps du SSE, main.pbo intact).
      MA FICHE   fiche RP du seul joueur concerné, du plus urgent au détail : question d'un contrôleur (réponses au choix),
                 état de l'infiltration, cohérence (note et actions pour la corriger), ce que disent ses papiers, légende à
                 retenir (ce qu'un contrôleur demande), liaison Athena (compte, couverture reçue ou locale, date, quoi
                 faire), puis sections repliables : contacts, documents complémentaires, histoire, phrases types, sujets
                 sensibles, secrets, consignes
      ZEUS       (curateur seulement) les infiltrés et leur cohérence ; un clic ouvre la fiche de l'un d'eux
    Rendu : [page, rectangle] (fn_pageRender de l'ATAK). Actions : ["act", action, argument] call comspec_sse_fnc_idCoverPage
      refresh · tab · answer [qid, index] · open netId (Zeus) · back · fold clé · pull (paquet Athena)
      received [ancienne fiche, nouvelle] · synced   réponses du serveur et d'Athena ; même fiche : page laissée telle quelle,
                 seule la note change sur place s'il n'y a que ça de nouveau
    État local (jamais diffusé) : comspec_sse_idCoverSheets (netId -> fiche reçue du serveur), comspec_sse_idQuestion,
    comspec_sse_idCoverSync (fn_idCoverSync). Couleurs : jetons de fn_theme, lisibles dans les trois thèmes. Texte structuré :
    0,8 au moins (fn_theme, docs/ui/atak-design-system.md : jamais sous 14 px).
*/
params [["_page", "IDCOVER"], ["_rect", []], ["_arg", ""]];
private _st = uiNamespace getVariable ["comspec_sse_idCoverApp", createHashMap];
uiNamespace setVariable ["comspec_sse_idCoverApp", _st];
private _isOpen = { ((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "IDCOVER" };
private _render = {
    [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "IDCOVER") then { ["IDCOVER"] call comspec_atak_native_fnc_pageRender; }; }] call comspec_atak_native_fnc_nextFrame;
};
private _sheets = missionNamespace getVariable ["comspec_sse_idCoverSheets", createHashMap];
missionNamespace setVariable ["comspec_sse_idCoverSheets", _sheets];
private _zeus = !isNull (getAssignedCuratorLogic player);
private _tr = { _this call comspec_sse_fnc_tr };
// Note de cohérence (texte structuré) : aussi posée sur place, sans redessiner la page.
private _scoreText = {
    params ["_score", "_th"];
    (switch (true) do {
        case (_score >= 75): { [_th get "hexOk", "Tient la route"] };
        case (_score >= 50): { [_th get "hexWarn", "Fragile"] };
        default { [_th get "hexAlert", "Ne tiendra pas un contrôle"] };
    }) params ["_col", "_word"];
    format ["<t color='%3'>%4</t>  <t size='1.3' font='COMSPEC_BarlowBold' color='%1'>%2 / 100</t>  <t font='COMSPEC_BarlowBold' color='%1'>%5</t>",
        _col, _score, _th get "hexTextDim", "Cohérence" call _tr, _word call _tr]
};

if (_page isEqualTo "act") exitWith {
    switch (_rect) do {
        case "refresh": {
            _st set ["force", true];
            _st set ["asked", time];
            ["send", ["coverSheet", [player, [player, objectFromNetId (_st getOrDefault ["open", ""])] select ((_st getOrDefault ["open", ""]) isNotEqualTo "")]]] call comspec_sse_fnc_idClient;
            if (_zeus) then { ["send", ["coverList", [player]]] call comspec_sse_fnc_idClient; };
        };
        case "tab": { _st set ["tab", _arg]; _st set ["open", ""]; if (_arg isEqualTo "zeus") then { ["send", ["coverList", [player]]] call comspec_sse_fnc_idClient; }; call _render; };
        case "open": { _st set ["open", _arg]; ["send", ["coverSheet", [player, objectFromNetId _arg]]] call comspec_sse_fnc_idClient; call _render; };
        case "back": { _st set ["open", ""]; call _render; };
        case "fold": {
            private _f = _st getOrDefault ["folds", createHashMap];
            _st set ["folds", _f];
            _f set [_arg select 0, !(_f getOrDefault [_arg select 0, _arg select 1])];
            call _render;
        };
        case "import": { ["run"] call comspec_sse_fnc_idCoverImport; };
        case "forget": { ["forget"] call comspec_sse_fnc_idCoverImport; };
        case "pull": {
            ["send", ["coverPull", [player]]] call comspec_sse_fnc_idClient;
            _st set ["pulled", time];
            call _render;
        };
        case "answer": {
            _arg params [["_qid", ""], ["_i", -1]];
            private _q = missionNamespace getVariable ["comspec_sse_idQuestion", []];
            if (_q isEqualTo []) exitWith {};
            (_q select 3) set [_qid, _i];
            ["send", ["answer", [player, _qid, _i]]] call comspec_sse_fnc_idClient;
            call _render;
        };
        case "received": {
            if !(call _isOpen) exitWith {};
            _arg params [["_old", createHashMap], ["_new", createHashMap]];
            private _force = _st getOrDefault ["force", false];
            _st set ["force", false];
            private _same = {
                params ["_a", "_b"];
                private _x1 = +_a; private _x2 = +_b;
                _x1 deleteAt "score"; _x2 deleteAt "score";
                (str _x1) isEqualTo (str _x2)
            };
            if (_force || {!([_old, _new] call _same)}) exitWith { call _render; };
            private _c = uiNamespace getVariable ["comspec_sse_idCoverScoreCtrl", controlNull];
            if (!isNull _c && {(_old getOrDefault ["score", -1]) isNotEqualTo (_new getOrDefault ["score", -1])}) then {
                _c ctrlSetStructuredText parseText ([_new getOrDefault ["score", 0], [] call comspec_atak_native_fnc_theme] call _scoreText);
            };
        };
        case "synced": { if (call _isOpen) then { call _render; }; };
    };
    true
};

disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
if ((count _rect) isEqualTo 4) then { _bw = _rect select 2; _bh = _rect select 3; };
private _th = [] call comspec_atak_native_fnc_theme;
private _hDim = _th get "hexTextDim";
private _hOk = _th get "hexOk";
private _hWarn = _th get "hexWarn";
private _hAlert = _th get "hexAlert";
private _surface = _th get "surface";
private _esc = { params ["_t"]; if !(_t isEqualType "") then { _t = str _t; }; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };
// La classe du texte structuré garde une encre claire : en thème JOUR, chaque texte porte sa couleur du thème.
private _hText = _th get "hexText";
private _ink = { format ["<t color='%1'>%2</t>", _hText, _this] };
private _bold = { format ["<t font='COMSPEC_BarlowBold' color='%1'>%2</t>", _hText, [_this] call _esc] };
private _act = { params ["_a", ["_x2", ""]]; compile format ["['act', '%1', %2] call comspec_sse_fnc_idCoverPage;", _a, str _x2] };
private _when = { params ["_t"]; if !(_t isEqualType "") exitWith { "" }; ((_t splitString "T") joinString " ") select [0, 16] };
private _icon = "\z\comspec_sse\addons\identity\data\app_id.paa";
private _tab = _st getOrDefault ["tab", "me"];
if (!_zeus) then { _tab = "me"; };
private _rows = [];

// Question en attente (checkpoint, contrôle IA) : réponses au choix.
(missionNamespace getVariable ["comspec_sse_idQuestion", []]) params [["_qWho", ""], ["_qList", []], ["_qEnd", 0], ["_qDone", createHashMap]];
private _open = _qList select { !((_x select 0) in _qDone) };
if (_open isNotEqualTo [] && {_qEnd > time}) then {
    (_open select 0) params ["_qid", "_text", "_opts"];
    _rows pushBack ["banner", format ["<t color='%1'>%2</t>", _hWarn, format [("%1 vous interroge (%2 s)" call _tr), [_qWho] call _esc, round (_qEnd - time)]], _surface];
    _rows pushBack ["section", (_text call _tr), ""];
    private _b = [];
    { _b pushBack [[_x call _tr] call _esc, ["answer", [_qid, _forEachIndex]] call _act, false, true]; } forEach _opts;
    _rows pushBack ["buttons", _b];
    _rows pushBack ["text", format ["<t size='0.8' color='%1'>%2</t>", _hDim, ("Aussi : menu ACE personnel › Mes papiers › Répondre." call _tr) call _esc]];
};
if (_zeus) then {
    // Onglets du système de design : l'onglet choisi reste lisible au survol et au focus, quel que soit l'accent.
    _rows pushBack ["tabs", [
        ["MA FICHE" call _tr, ["tab", "me"] call _act, _tab isEqualTo "me"],
        ["ZEUS" call _tr, ["tab", "zeus"] call _act, _tab isEqualTo "zeus", count (missionNamespace getVariable ["comspec_sse_idCoverList", []])]
    ]];
};

// Ce qu'il faut corriger : code de fn_idCover « score » -> [action, comment].
private _fixes = createHashMapFromArray [
    ["sans papiers", ["Trouver des papiers", "Vous n'avez aucun papier. Demandez-en au Zeus ou à vos responsables sur Athena (kit de mission), ou récupérez une pièce."]],
    ["papiers à plusieurs noms", ["Ne garder qu'un seul nom", "Laissez ailleurs les papiers qui portent un autre nom que votre couverture."]],
    ["papiers douteux", ["Trouver de meilleurs papiers", "Ces papiers résistent mal à un contrôle poussé. Évitez les contrôles ou demandez une pièce mieux faite."]],
    ["arme à la main", ["Ranger l'arme", "Une arme visible fait sauter la couverture dès qu'un ennemi vous voit."]],
    ["arme longue dans le dos", ["Cacher l'arme longue", "Mettez-la dans un sac ou un véhicule."]],
    ["tenue militaire", ["Changer de tenue", "Portez des vêtements civils."]],
    ["gilet pare-balles", ["Retirer le gilet", "Un civil ne porte pas de gilet pare-balles."]],
    ["casque", ["Retirer le casque", "Portez un couvre-chef civil, ou rien."]],
    ["jumelles de vision nocturne", ["Retirer les jumelles de vision nocturne", "Rangez-les dans votre sac."]],
    ["en zone interdite", ["Quitter la zone interdite", "Votre légende ne justifie pas votre présence ici."]],
    ["réponses fausses", ["Relire votre légende", "Vos réponses ne collent pas à vos papiers : apprenez ce qu'il y a dans « À retenir »."]],
    ["soupçons", ["Se faire discret", "Les soupçons retombent si rien ne vous trahit pendant un moment."]]
];

private _sheetRows = {
    params ["_s", ["_mine", true]];
    private _state = _s getOrDefault ["state", "off"];
    switch (_state) do {
        case "on": { _rows pushBack ["banner", format ["<t font='COMSPEC_BarlowBold' color='%1'>%2</t>", _hOk, "INFILTRÉ · COUVERTURE INTACTE" call _tr], _surface]; };
        case "burnt": {
            _rows pushBack ["banner", format ["<t font='COMSPEC_BarlowBold' color='%1'>%2</t>", _hAlert, "COUVERTURE BRÛLÉE" call _tr], _surface];
            if ((_s getOrDefault ["burnWhy", ""]) isNotEqualTo "") then { _rows pushBack ["info", "Motif" call _tr, ([(_s get "burnWhy") call _tr] call _esc) call _ink]; };
        };
        default {};
    };
    if ((_s getOrDefault ["trueName", ""]) isNotEqualTo "") then { _rows pushBack ["info", "Vrai nom (Zeus)" call _tr, ([_s get "trueName"] call _esc) call _ink]; };

    // Cohérence : la note, puis quoi faire. Gain : ce que la note reprend une fois corrigé.
    private _score = _s getOrDefault ["score", -1];
    if (_score >= 0) then {
        _rows pushBack ["text", [_score, _th] call _scoreText, _surface, "comspec_sse_idCoverScoreCtrl"];
        private _todo = _s getOrDefault ["todo", []];
        {
            _x params ["_code", ["_gain", 0]];
            (_fixes getOrDefault [_code, ["Se faire discret", ""]]) params ["_what", "_how"];
            _rows pushBack ["check", ["warn", "ko"] select (_gain >= 15), _what call _tr, ["", format ["+%1", _gain]] select (_gain > 0), _how call _tr];
        } forEach _todo;
    };

    // Ce que disent les papiers : vrais documents portés, ou l'identité prévue s'il n'y en a aucun.
    private _docs = _s getOrDefault ["docs", []];
    private _carried = _docs select { _x param [4, false] };
    _rows pushBack ["section", ["Identité prévue, sans papier" call _tr, "Ce que disent mes papiers" call _tr] select (_carried isNotEqualTo []), ""];
    if (_carried isEqualTo []) then {
        _rows pushBack ["text", format ["<t color='%1'>%2</t>", _hWarn, ([["Aucun papier sur vous : rien ne prouve encore l'identité ci-dessous à un contrôle.", "Vos papiers ne sont pas sur vous : rien ne prouve l'identité ci-dessous à un contrôle."] select (_docs isNotEqualTo []) call _tr] call _esc)], _surface];
    };
    _rows pushBack ["person", "\z\comspec_sse\addons\identity\data\silhouette_co.paa",
        format ["<t size='1.1' font='COMSPEC_BarlowBold' color='%5'>%1</t><br/><t size='0.85' color='%4'>%2 · %3</t>", [_s getOrDefault ["name", "?"]] call _esc,
            [_s getOrDefault ["dob", ""]] call _esc, [_s getOrDefault ["pob", ""]] call _esc, _hDim, _hText], [], [1, 1, 1, 1]];
    {
        _x params ["_type", "_country", "_number", "_expires", ["_on", false]];
        _rows pushBack ["info", format ["%1 · %2", _type call _tr, [_country] call _esc],
            format ["%1%2", (format ["%1%2", [_number] call _esc, ["", format [" · %1 %2", "valable jusqu'au" call _tr, [_expires] call _esc]] select (_expires isNotEqualTo "")]) call _ink,
                ["", format [" · <t color='%1'>%2</t>", _hWarn, "pas sur vous" call _tr]] select !_on]];
    } forEach _docs;

    // À retenir : ce qu'un contrôleur demande (mêmes questions que fn_idLegend), réponses en gras.
    _rows pushBack ["section", "À retenir" call _tr, "Ce qu'on vous demandera à un contrôle" call _tr];
    private _ph = _s getOrDefault ["phone", createHashMap];
    private _firstContact = ((_s getOrDefault ["contacts", []]) param [0, []]) param [0, ""];
    {
        _x params ["_q", "_a"];
        if (_a isNotEqualTo "") then { _rows pushBack ["info", _q call _tr, (_a call _tr) call _bold]; };
    } forEach [
        ["D'où venez-vous ?", _s getOrDefault ["city", ""]],
        ["Pour qui travaillez-vous ?", _s getOrDefault ["employer", ""]],
        ["Raison du déplacement ?", _s getOrDefault ["travel", ""]],
        ["Quel est votre métier ?", _s getOrDefault ["profession", ""]],
        ["Où êtes-vous né ?", _s getOrDefault ["pob", ""]],
        ["Qui peut confirmer ce que vous dites ?", _firstContact],
        ["Adresse", _s getOrDefault ["address", ""]],
        ["Téléphone", trim format ["%1%2", _ph getOrDefault ["number", ""], ["", format [" · %1", _ph getOrDefault ["model", ""]]] select ((_ph getOrDefault ["model", ""]) isNotEqualTo "")]]
    ];
    if ((_s getOrDefault ["title", ""]) isNotEqualTo "") then { _rows pushBack ["info", "Brief" call _tr, ([_s get "title"] call _esc) call _ink]; };

    private _fold = {
        params ["_key", "_title", "_items", ["_def", false], ["_summary", ""]];
        if (_items isEqualTo []) exitWith {};
        private _isOpen = (_st getOrDefault ["folds", createHashMap]) getOrDefault [_key, _def];
        _rows pushBack ["fold", _title call _tr, [_summary, str (count _items)] select (_summary isEqualTo ""), _isOpen, ["fold", [_key, _def]] call _act];
        if (_isOpen) then { { _rows pushBack ["text", format ["<t size='0.9' color='%2'>%1</t>", _x, _hText]]; } forEach _items; };
    };

    // Liaison Athena : seulement sur sa propre fiche (le Zeus n'a pas à voir le compte d'un autre).
    if (_mine) then {
        private _status = if (isNil "comspec_atak_native_fnc_athenaStatus") then { createHashMap } else { [] call comspec_atak_native_fnc_athenaStatus };
        private _logged = _status getOrDefault ["logged", false];
        private _steam = missionNamespace getVariable ["COMSPEC_SteamLinked", false];
        private _sync = missionNamespace getVariable ["comspec_sse_idCoverSync", createHashMap];
        private _source = _s getOrDefault ["source", "local"];
        private _prepared = _s getOrDefault ["prepared", false];
        private _lines = [];
        _lines pushBack [["ko", "ok"] select _logged, "Compte Athena" call _tr, (["Non lié", "Lié"] select _logged) call _tr,
            ["Ouvrez l'app Athena du téléphone pour relier votre compte." call _tr, ""] select _logged];
        if (!_steam) then { _lines pushBack ["warn", "Compte Steam" call _tr, "Non lié" call _tr, "Sur Athena, liez votre compte Steam : sans lui, le serveur ne peut pas vous remettre une couverture préparée." call _tr]; };
        private _summary = (["Compte non lié", "Compte lié"] select _logged) call _tr;
        private _needsPull = false;
        switch (true) do {
            case (_source isEqualTo "athena"): {
                private _at = [_s getOrDefault ["athenaAt", ""]] call _when;
                _lines pushBack ["ok", "Couverture" call _tr, "Reçue d'Athena" call _tr, ["", format ["%1 %2 UTC", "Mise à jour le" call _tr, _at]] select (_at isNotEqualTo "")];
                _summary = format ["%1 · %2", _summary, "Reçue d'Athena" call _tr];
            };
            case (_source isEqualTo "import"): {
                private _at = [_s getOrDefault ["importedAt", ""]] call _when;
                _lines pushBack ["ok", "Couverture" call _tr, "Importée depuis un fichier" call _tr, ["", format ["%1 %2 UTC", "Générée le" call _tr, _at]] select (_at isNotEqualTo "")];
                _summary = format ["%1 · %2", _summary, "Importée" call _tr];
            };
            case (_prepared): {
                private _syncState = _sync getOrDefault ["state", ""];
                private _at = [_sync getOrDefault ["at", ""]] call _when;
                switch (_syncState) do {
                    case "sent": { _lines pushBack ["ok", "Couverture" call _tr, "Créée en jeu, envoyée à Athena" call _tr, format ["%1 %2 UTC. %3", "Mise à jour le" call _tr, _at, "Retrouvez-la sur Athena, dans Mes papiers." call _tr]]; };
                    case "sending": { _lines pushBack ["now", "Couverture" call _tr, "Envoi à Athena…" call _tr, ""]; };
                    case "unlinked": { _lines pushBack ["warn", "Couverture" call _tr, "Locale" call _tr, "Reliez votre compte Athena : elle partira toute seule ensuite." call _tr]; };
                    case "error": { _lines pushBack ["warn", "Couverture" call _tr, "Locale" call _tr, "Athena n'a pas répondu. Nouvel essai quand vous actualisez." call _tr]; };
                    default { _lines pushBack ["warn", "Couverture" call _tr, "Locale" call _tr, "Sans liaison COMSPEC Link, elle reste sur ce poste. Vérifiez l'app Athena." call _tr]; };
                };
                _summary = format ["%1 · %2", _summary, (["Locale", "Envoyée"] select (_syncState isEqualTo "sent")) call _tr];
            };
            default {
                _needsPull = true;
                _lines pushBack ["warn", "Couverture" call _tr, "Aucune préparée" call _tr, "Attribuez-la sur Athena (Bureau SSE) ou demandez-la au Zeus. Déjà attribuée ? Demandez la mise à jour." call _tr];
                _summary = format ["%1 · %2", _summary, "Aucune couverture" call _tr];
            };
        };
        private _def = !_logged || {!_steam} || {_needsPull} || {(_lines findIf { (_x select 0) in ["warn", "ko"] }) >= 0};
        private _isOpen = (_st getOrDefault ["folds", createHashMap]) getOrDefault ["athena", _def];
        _rows pushBack ["fold", "Liaison Athena" call _tr, _summary, _isOpen, ["fold", ["athena", _def]] call _act];
        if (_isOpen) then {
            { _rows pushBack (["check"] + _x); } forEach _lines;
            if (_source isNotEqualTo "athena") then {
                private _wait = (time - (_st getOrDefault ["pulled", -1000])) < 120;
                _rows pushBack ["buttons", [["Demander la mise à jour" call _tr, ["pull"] call _act, false, !_wait, "Recharge le paquet d'identité du serveur (au plus une fois toutes les 2 minutes)." call _tr]]];
            };
        };

        // Importer ma couverture : fichier généré sur Athena, lu sur ce poste. Aucune connexion à Athena, aucune requête réseau.
        private _imp = missionNamespace getVariable ["comspec_sse_idCoverImportSt", createHashMap];
        private _isImported = _s getOrDefault ["imported", false];
        private _impOpen = (_st getOrDefault ["folds", createHashMap]) getOrDefault ["import", !_isImported];
        _rows pushBack ["fold", "Importer ma couverture" call _tr, (["Aucun fichier importé", "Couverture importée"] select _isImported) call _tr, _impOpen, ["fold", ["import", !_isImported]] call _act];
        if (_impOpen) then {
            private _steps = [
                "1. Sur Athena > Ma couverture, cliquez Générer puis Télécharger.",
                "2. Déposez le fichier dans Documents\Arma 3 - COMSPEC.",
                "3. Ici, cliquez Importer."
            ] apply { [_x call _tr] call _esc };
            _rows pushBack ["text", format ["<t size='0.9' color='%2'>%1</t>", _steps joinString "<br/>", _hText]];
            if ((_imp getOrDefault ["text", ""]) isNotEqualTo "") then {
                _rows pushBack ["text", format ["<t color='%1'>%2</t>", [_hAlert, _hOk] select ((_imp getOrDefault ["kind", ""]) isEqualTo "ok"), [(_imp get "text") call _tr] call _esc]];
            };
            private _b = [["IMPORTER MA COUVERTURE" call _tr, ["import"] call _act, false, true, "Lit le fichier déposé dans Documents\Arma 3 - COMSPEC. Rien n'est envoyé à Athena." call _tr]];
            if (_isImported) then { _b pushBack ["Oublier l'import" call _tr, ["forget"] call _act, false, true, "Revient à la légende d'avant, sans toucher à vos papiers." call _tr]; };
            _rows pushBack ["buttons", _b];
        };
    };

    ["contacts", "Contacts", (_s getOrDefault ["contacts", []]) apply { _x params [["_n", ""], ["_r", ""], ["_p", ""]]; [trim format ["%1%2%3", _n, ["", format [" (%1)", _r call _tr]] select (_r isNotEqualTo ""), ["", format [" · %1", _p]] select (_p isNotEqualTo "")]] call _esc }] call _fold;
    ["extraDocs", "Documents complémentaires", (_s getOrDefault ["extraDocs", []]) apply { [_x call _tr] call _esc }] call _fold;
    private _story = [];
    { if (_x isNotEqualTo "") then { _story pushBack ([_x call _tr] call _esc); }; } forEach ([_s getOrDefault ["biography", ""], _s getOrDefault ["family", ""]] + (_s getOrDefault ["history", []]) + (_s getOrDefault ["languages", []]));
    ["story", "Mon histoire", _story] call _fold;
    ["phrases", "Phrases types", (_s getOrDefault ["phrases", []]) apply { format ["« %1 »", [_x call _tr] call _esc] }] call _fold;
    ["sensitive", "Sujets sensibles", (_s getOrDefault ["sensitive", []]) apply { [_x call _tr] call _esc }] call _fold;
    ["secrets", "Secrets", (_s getOrDefault ["secrets", []]) apply { [_x call _tr] call _esc }] call _fold;
    ["orders", "Consignes", ([_s getOrDefault ["instructions", ""]] select { _x isNotEqualTo "" }) apply { [_x] call _esc }, true] call _fold;
};

if (_tab isEqualTo "zeus") then {
    private _openId = _st getOrDefault ["open", ""];
    private _s = _sheets getOrDefault [_openId, createHashMap];
    if (_openId isNotEqualTo "" && {(count _s) > 0}) exitWith {
        [_s, false] call _sheetRows;
        _rows pushBack ["buttons", [["RETOUR" call _tr, ["back"] call _act]]];
    };
    private _lst = missionNamespace getVariable ["comspec_sse_idCoverList", []];
    if (_lst isEqualTo []) exitWith { _rows pushBack ["empty", _icon, "Aucun infiltré" call _tr, "Module Zeus « Couverture » sur un joueur ou une IA pour l'infiltrer." call _tr]; };
    {
        _x params ["_net", "_n", "_cn", "_state", "_score"];
        _rows pushBack ["notif", _icon, [_th get "off", _th get "ok", _th get "alert"] select ((["off", "on", "burnt"] find _state) max 0),
            format ["%1 · %2", _n, _cn], format ["%1/100", _score],
            ((["Levée", "Couverture intacte", "Brûlée"] select ((["off", "on", "burnt"] find _state) max 0)) call _tr),
            ["open", _net] call _act, {}];
    } forEach _lst;
} else {
    private _s = _sheets getOrDefault [netId player, createHashMap];
    // Fiche relue en arrivant sur la page, au plus toutes les 15 s : même contenu, rien n'est redessiné (« received »).
    if ((time - (_st getOrDefault ["asked", -100])) > ([15, 10] select ((count _s) isEqualTo 0))) then {
        _st set ["asked", time];
        ["send", ["coverSheet", [player, player]]] call comspec_sse_fnc_idClient;
    };
    if ((count _s) isEqualTo 0) exitWith {
        _rows pushBack ["empty", _icon, "Fiche en chargement" call _tr, "Votre fiche est demandée au serveur." call _tr];
    };
    [_s] call _sheetRows;
};
_rows pushBack ["buttons", [["Actualiser" call _tr, ["refresh"] call _act]]];
_rows pushBack ["text", format ["<t size='0.8' color='%1'>%2</t>", _hDim, ("Fiche personnelle : visible de vous seul (et du Zeus)." call _tr) call _esc]];
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
