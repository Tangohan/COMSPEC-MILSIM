/*
    Papier de la visionneuse SSE (textures addons\ui\data\paper, dessinées par tools\gen_paper_textures.py).
      ["list"] call comspec_sse_fnc_paperSkin                     -> [[code, libellé], ...] (ordre de l'éditeur)
      ["texture", [code, nuit]] call …                            -> chemin de la texture
      ["resolve", [_fog, _entité, graine]] call …                 -> [code, manuscrit]  (code "" : ancien rendu uni)
      ["apply", [_display, code, nuit]] call …                    -> true
    Choix, du plus fort au plus faible :
      1. papier choisi dans l'éditeur (variable comspec_sse_doc1_paper de l'objet, ou clé « paper » du document) ;
         « legacy » (pièce posée avant les papiers) et un style de chrome choisi par le Zeus gardent l'ancien rendu ;
      2. le contexte déjà connu : pièce trouvée sur un corps (sale), objet détruit (brûlé), document « bruit » du
         générateur (froissé), document dit usé (jauni) ;
      3. la nature du document d'après son titre (note, rapport, ordre, ticket, formulaire), puis un papier tiré au
         hasard dans la famille, graine = identifiant du document : toujours le même papier pour la même pièce.
    Rien ne tourne hors de l'ouverture de la visionneuse.
*/
params [["_mode", "", [""]], ["_args", []]];

private _papers = [
    ["uni", "Uni propre"], ["quadrille", "Petits carreaux"], ["seyes", "Grands carreaux"], ["ligne", "Ligné"],
    ["spirale", "Carnet à spirale"], ["postit", "Post-it"], ["entete", "Papier à en-tête"], ["formulaire", "Formulaire militaire"],
    ["carbone", "Papier carbone"], ["ticket", "Ticket de caisse"], ["sale", "Papier sale"], ["jauni", "Papier jauni"],
    ["froisse", "Papier froissé"], ["dechire", "Déchiré et recollé"], ["brule", "Brûlé aux bords"]
];
if (_mode isEqualTo "list") exitWith { +_papers };

private _codes = _papers apply { _x select 0 };
if (_mode isEqualTo "texture") exitWith {
    _args params [["_code", "uni", [""]], ["_night", false, [false]]];
    if !(_code in _codes) then { _code = "uni"; };
    format ["\z\comspec_sse\addons\ui\data\paper\paper_%1%2_co.paa", _code, ["", "_n"] select _night]
};

if (_mode isEqualTo "resolve") exitWith {
    _args params [["_fog", createHashMap, [createHashMap]], ["_ent", objNull, [objNull]], ["_seedArg", "", [""]]];
    private _docs = (_fog getOrDefault ["docs", []]) select { _x isEqualType createHashMap };
    private _first = _docs param [0, createHashMap];
    private _set = toLower (_first getOrDefault ["paper", ""]);
    if (_set isEqualTo "" && {!isNull _ent}) then { _set = toLower (_ent getVariable ["comspec_sse_doc1_paper", ""]); };
    // Pièce posée par l'éditeur avant les papiers : elle garde sa feuille unie.
    if (_set isEqualTo "" && {!isNull _ent} && {(_ent getVariable ["comspec_sse_editorItem", ""]) isNotEqualTo ""}) then { _set = "legacy"; };
    if (_set isEqualTo "legacy") exitWith { ["", false] };
    // Style imposé par le Zeus ou la mission (module « Chrome documents ») : rendu d'avant, tel quel.
    private _chromeStyle = if (isNil "comspec_sse_fnc_getDocumentChrome") then { "clean" } else {
        toLower ((["get"] call comspec_sse_fnc_getDocumentChrome) getOrDefault ["paper_style", "clean"])
    };
    if (_set isEqualTo "" && {_chromeStyle isNotEqualTo "clean"}) exitWith { ["", false] };

    private _text = toLower ((_docs apply { (_x getOrDefault ["title", ""]) + " " + (_x getOrDefault ["summary", ""]) }) joinString " ");
    private _has = { params ["_words"]; (_words findIf { (_text find _x) >= 0 }) >= 0 };
    private _kind = switch (true) do {
        case ([["ticket", "reçu", "recu ", "bordereau", "facture", "receipt", "caisse"]] call _has): { "ticket" };
        case ([["formulaire", "fiche ", "bon de ", "laissez-passer", "registre", "form "]] call _has): { "form" };
        case ([["ordre", "order", "directive", "consigne", "télégramme", "message chiffré"]] call _has): { "order" };
        case ([["rapport", "report", "compte rendu", "bulletin", "synthèse", "note de service", "procès-verbal"]] call _has): { "report" };
        case ([["note", "carnet", "manuscrit", "annotation", "lettre", "griffonn", "brouillon", "rendez-vous", "liste de", "mot "]] call _has): { "note" };
        default { "other" };
    };
    private _code = if (_set in _codes) then { _set } else { call {
        private _ctx = switch (true) do {
            case (isNull _ent): { "" };
            case (_ent isKindOf "CAManBase" && {!alive _ent}): { "sale" };
            case (!(_ent isKindOf "CAManBase") && {(damage _ent) >= 0.9}): { "brule" };
            case ((_docs findIf { _x getOrDefault ["noise", false] }) >= 0 && {(_docs findIf { !(_x getOrDefault ["noise", false]) }) < 0}): { "froisse" };
            case ([["document usé", "illisible", "ancien", "archive"]] call _has): { "jauni" };
            default { "" };
        };
        if (_ctx isNotEqualTo "" && {_kind isNotEqualTo "ticket"}) exitWith { _ctx };
        private _pool = (createHashMapFromArray [
            ["note", ["seyes", "ligne", "spirale", "postit", "quadrille", "froisse", "dechire"]],
            ["report", ["entete", "uni", "quadrille", "carbone"]],
            ["order", ["entete", "carbone", "formulaire", "froisse"]],
            ["ticket", ["ticket"]],
            ["form", ["formulaire", "carbone"]],
            ["other", ["uni", "ligne", "quadrille", "seyes", "froisse", "jauni", "sale"]]
        ]) get _kind;
        // Pièce de l'éditeur : son numéro (toutes ses pièces ont le même identifiant de document). Aperçu : graine donnée.
        private _seed = _first getOrDefault ["uid", _fog getOrDefault ["uid", ""]];
        if (!isNull _ent && {(_ent getVariable ["comspec_sse_editorItem", ""]) isNotEqualTo ""}) then { _seed = _ent getVariable ["comspec_sse_editorItem", ""]; };
        if (_seedArg isNotEqualTo "") then { _seed = _seedArg; };
        if !(_seed isEqualType "") then { _seed = str _seed; };
        _pool select (([0, "paper:" + _seed] call comspec_sse_fnc_hash) mod (count _pool))
    } };
    // Écriture à la main : notes et carnets. Caveat est une police du jeu (plateforme Contact) : vérifiée, sinon police actuelle.
    [_code, (_kind isEqualTo "note" || {_code in ["postit", "spirale", "seyes"]}) && {isClass (configFile >> "CfgFontFamilies" >> "Caveat")}]
};

if (_mode isEqualTo "apply") exitWith {
    _args params [["_display", displayNull, [displayNull]], ["_code", "", [""]], ["_night", false, [false]]];
    if (isNull _display) exitWith { false };
    private _tex = _display displayCtrl 93027;
    if (_code isEqualTo "" || {!(_code in _codes)}) exitWith {
        if (!isNull _tex) then { _tex ctrlShow false; };
        false
    };
    _tex ctrlSetText (["texture", [_code, _night]] call comspec_sse_fnc_paperSkin);
    _tex ctrlShow true;
    { (_display displayCtrl _x) ctrlShow false; } forEach [93024, 93025, 93026];
    // Bandeaux d'en-tête et de pied : couleur moyenne de la feuille à cet endroit (gen_paper_textures.py), presque
    // opaques : titre et pied restent lisibles sur les carreaux, les taches ou la brûlure.
    private _tints = createHashMapFromArray [
        ["uni", [[0.909, 0.901, 0.875], [0.905, 0.897, 0.871]]], ["uni_n", [[0.542, 0.547, 0.578], [0.539, 0.545, 0.575]]],
        ["quadrille", [[0.896, 0.897, 0.884], [0.887, 0.889, 0.876]]], ["quadrille_n", [[0.535, 0.545, 0.582], [0.53, 0.539, 0.577]]],
        ["seyes", [[0.895, 0.89, 0.889], [0.888, 0.884, 0.882]]], ["seyes_n", [[0.535, 0.541, 0.585], [0.531, 0.537, 0.58]]],
        ["ligne", [[0.922, 0.918, 0.896], [0.909, 0.906, 0.886]]], ["ligne_n", [[0.55, 0.557, 0.592], [0.542, 0.55, 0.585]]],
        ["sale", [[0.814, 0.785, 0.713], [0.757, 0.73, 0.663]]], ["sale_n", [[0.481, 0.475, 0.476], [0.448, 0.442, 0.443]]],
        ["jauni", [[0.775, 0.713, 0.54], [0.725, 0.667, 0.508]]], ["jauni_n", [[0.451, 0.428, 0.372], [0.422, 0.401, 0.35]]],
        ["froisse", [[0.886, 0.867, 0.818], [0.874, 0.855, 0.807]]], ["froisse_n", [[0.526, 0.526, 0.543], [0.519, 0.518, 0.536]]],
        ["dechire", [[0.904, 0.889, 0.851], [0.888, 0.874, 0.837]]], ["dechire_n", [[0.538, 0.539, 0.564], [0.528, 0.53, 0.554]]],
        ["brule", [[0.842, 0.806, 0.71], [0.808, 0.771, 0.679]]], ["brule_n", [[0.497, 0.487, 0.477], [0.476, 0.466, 0.456]]],
        ["entete", [[0.93, 0.926, 0.911], [0.908, 0.906, 0.894]]], ["entete_n", [[0.555, 0.563, 0.601], [0.542, 0.55, 0.589]]],
        ["formulaire", [[0.872, 0.88, 0.819], [0.867, 0.874, 0.814]]], ["formulaire_n", [[0.519, 0.532, 0.544], [0.516, 0.528, 0.54]]],
        ["carbone", [[0.837, 0.852, 0.882], [0.829, 0.844, 0.874]]], ["carbone_n", [[0.503, 0.518, 0.577], [0.498, 0.513, 0.572]]],
        ["spirale", [[0.923, 0.919, 0.9], [0.911, 0.908, 0.891]]], ["spirale_n", [[0.55, 0.558, 0.593], [0.543, 0.551, 0.587]]],
        ["ticket", [[0.95, 0.95, 0.942], [0.95, 0.95, 0.943]]], ["ticket_n", [[0.567, 0.577, 0.62], [0.568, 0.577, 0.62]]],
        ["postit", [[0.942, 0.874, 0.451], [0.929, 0.861, 0.442]]], ["postit_n", [[0.541, 0.517, 0.336], [0.533, 0.509, 0.33]]]
    ];
    (_tints getOrDefault [_code + (["", "_n"] select _night), [[0.9, 0.9, 0.88], [0.9, 0.9, 0.88]]]) params ["_head", "_foot"];
    (_display displayCtrl 93019) ctrlSetBackgroundColor (_head + [1]);
    (_display displayCtrl 93022) ctrlSetBackgroundColor (_head + [0.86]);
    (_display displayCtrl 93023) ctrlSetBackgroundColor (_foot + [0.86]);
    (_display displayCtrl 93011) ctrlSetTextColor ([[0.12, 0.1, 0.08, 1], [0.05, 0.04, 0.03, 1]] select _night);
    (_display displayCtrl 93017) ctrlSetTextColor ([[0.3, 0.26, 0.2, 1], [0.12, 0.1, 0.08, 1]] select _night);
    true
};

false
