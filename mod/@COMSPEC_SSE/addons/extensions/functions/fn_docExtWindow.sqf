/*
    Fenêtre « SSE ROLEPLAY · Extensions » (dialogs/extensions.hpp) : extensions de documents détectées
    (["extensions"] call comspec_sse_fnc_idDocTypes), fiche, modèles, aperçu, bouton Activer / Désactiver, et tuto
    intégré « Comment ça marche » (étapes STR_COMSPEC_SSE_EXT_T<n>_T / _B, exemple de config.cpp à copier).
      ["open"]            ouvre (ou relit si déjà ouverte) ; ["open", "tuto"] ouvre directement le tuto
      ["load", display]   onLoad de la fenêtre                    ["unload"]          onUnload
      ["select"]          ligne choisie -> volet de droite         ["toggle"]          demande « set » au serveur
      ["refresh"]         relit la liste (liste coupée reçue du serveur)
      ["help"]            bascule extensions <-> tuto ; ["help", "tuto"] ouvre le tuto
      ["step"]            étape du tuto choisie                     ["copy"]            exemple de config.cpp copié
      ["view"]            -> vue affichée : "list", "empty", "tuto" ("" fenêtre fermée ; autotest)
      ["skeleton"]        -> exemple de config.cpp (texte brut, langue du jeu)
    Vues : « list » (extensions présentes), « empty » (aucune : explication et « Ouvrir le tuto » à la place des
    volets), « tuto ». Activer / Désactiver : Zeus, administrateur ou hôte seulement (grisé sinon) ; Eden : consultation.
    Aucune alerte Arma : tout se dit dans la fenêtre (ligne d'état du bas, deux lignes au plus, police réduite au besoin).
    Textes : stringtable de l'addon (FR / EN, langue du jeu).
*/
params [["_op", "open", [""]], ["_a", displayNull, [displayNull, ""]]];
disableSerialization;
private _L = { localize ("STR_COMSPEC_SSE_EXT_" + _this) };
private _fr = ("Lang" call _L) isEqualTo "fr";
private _nl = toString [10];
private _steps = 10;

// Exemple de config.cpp (type « Permis de port d'arme ») : même texte pour l'affichage et le presse-papiers.
private _skeleton = {
    private _c = { params ["_frT", "_enT"]; "// " + ([_enT, _frT] select _fr) };
    [
        ["config.cpp de l'extension « Mes documents » (addon séparé)", "config.cpp of the ""My documents"" extension (separate addon)"] call _c,
        ["$PBOPREFIX$ du dossier : z\mes\addons\documents", "Folder $PBOPREFIX$: z\mes\addons\documents"] call _c,
        "class CfgPatches {",
        "  class mes_documents {",
        "    name = ""Mes documents"";",
        "    units[] = {};",
        "    weapons[] = {""MES_DOC_Permis""};",
        "    requiredVersion = 2.14;",
        "    " + (["Obligatoire : le SSE est chargé avant cet addon", "Required: the SSE loads before this addon"] call _c),
        "    requiredAddons[] = {""comspec_sse_identity"", ""cba_main""};",
        "    author = ""Moi"";",
        "  };",
        "};",
        "",
        "class COMSPEC_ID_Base;",
        "class CfgWeapons {",
        "  " + (["Objet vierge (Arsenal du Zeus)", "Blank item (Zeus Arsenal)"] call _c),
        "  class MES_DOC_Permis: COMSPEC_ID_Base {",
        "    scope = 2;",
        "    displayName = ""Permis de port d'arme"";",
        "    picture = ""\z\mes\addons\documents\data\icon_permis_ca.paa"";",
        "  };",
        "  " + (["Exemplaires remis en jeu (un par document délivré)", "Copies handed out in game (one per issued document)"] call _c),
        "  class MES_DOC_Permis_001: MES_DOC_Permis { scope = 1; };",
        "  class MES_DOC_Permis_002: MES_DOC_Permis { scope = 1; };",
        "};",
        "",
        "class CfgComspecDocuments {",
        "  " + (["Nom de la classe = clé du type (minuscules)", "Class name = type key (lower case)"] call _c),
        "  class mes_permis {",
        "    displayName = ""Permis de port d'arme"";",
        "    displayNameEn = ""Firearms permit"";",
        "    " + (["identity, travel, permit, professional, pass ou other", "identity, travel, permit, professional, pass or other"] call _c),
        "    category = ""permit"";",
        "    item = ""MES_DOC_Permis"";",
        "    icon = ""\z\mes\addons\documents\data\icon_permis_ca.paa"";",
        "    " + (["Largeur / hauteur : carte 1.585, passeport 1.42, A6 1.414", "Width / height: card 1.585, passport 1.42, A6 1.414"] call _c),
        "    ratio = 1.585;",
        "    sides = 1;",
        "    " + (["Pays émetteurs ; vide : tous", "Issuing countries; empty: all"] call _c),
        "    countries[] = {};",
        "    " + (["Bande MRZ : vide, td1 (carte) ou td3 (passeport)", "MRZ band: empty, td1 (card) or td3 (passport)"] call _c),
        "    mrz = """";",
        "    " + (["Nom de la classe dans CfgComspecDocumentExtensions", "Class name in CfgComspecDocumentExtensions"] call _c),
        "    extension = ""mes_documents"";",
        "    class recto {",
        "      texture = ""\z\mes\addons\documents\data\permis_recto_co.paa"";",
        "      " + (["Positions de 0 à 1 (fraction de la face)", "Positions from 0 to 1 (fraction of the side)"] call _c),
        "      class photo { x = 0.05; y = 0.2; w = 0.26; h = 0.56; };",
        "      class holo { x = 0.23; y = 0.63; w = 0.13; };",
        "      class fields {",
        "        class last { key = ""last""; x = 0.36; y = 0.25; w = 0.5; h = 0.062; transform = ""upper""; font = ""name""; };",
        "        class first { key = ""first""; x = 0.36; y = 0.37; w = 0.5; h = 0.062; };",
        "        class number { key = ""number""; x = 0.36; y = 0.49; w = 0.3; h = 0.062; font = ""mono""; };",
        "        class expires { key = ""expires""; x = 0.36; y = 0.61; w = 0.3; h = 0.062; transform = ""dmy""; };",
        "        " + (["Champ saisi par le Zeus (input = 1)", "Field typed by the Zeus (input = 1)"] call _c),
        "        class weapon { key = ""weapon""; x = 0.36; y = 0.73; w = 0.5; h = 0.062; input = 1; label = ""Arme""; default = ""Pistolet""; };",
        "      };",
        "    };",
        "  };",
        "};",
        "",
        "class CfgComspecDocumentExtensions {",
        "  class mes_documents {",
        "    name = ""Mes documents"";",
        "    author = ""Moi"";",
        "    version = ""1.0"";",
        "    description = ""Permis de port d'arme pour ma mission."";",
        "    documents[] = {""mes_permis""};",
        "    preview = ""\z\mes\addons\documents\data\permis_recto_co.paa"";",
        "  };",
        "};"
    ]
};
if (_op isEqualTo "skeleton") exitWith { (call _skeleton) joinString _nl };
if (!hasInterface) exitWith { false };

private _d = uiNamespace getVariable ["comspec_sse_docExtDisplay", displayNull];
private _esc = { params ["_t"]; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };
// comspec_sse_docExtTestEmpty (uiNamespace, autotest seulement) : vue « aucune extension » forcée.
private _rows = { if (isNil "comspec_sse_fnc_idDocTypes" || {uiNamespace getVariable ["comspec_sse_docExtTestEmpty", false]}) then { [] } else { ["extensions"] call comspec_sse_fnc_idDocTypes } };
private _canEdit = {
    if (is3DEN) exitWith { false };
    isServer || {!isNull (getAssignedCuratorLogic player)} || {[player] call comspec_sse_fnc_isAuthority} || {serverCommandAvailable "#kick"}
};
private _ctl = { _d displayCtrl _this };

// Libellé d'une ligne : police réduite par paliers (60 % au plus bas), puis « … » s'il déborde encore (texte entier
// en bulle d'aide).
private _fitLine = {
    params ["_c", "_t"];
    if (isNull _c) exitWith {};
    private _base = _c getVariable ["comspec_sse_fh0", -1];
    if (_base < 0) then { _base = ctrlFontHeight _c; _c setVariable ["comspec_sse_fh0", _base]; };
    _c ctrlSetFontHeight _base;
    _c ctrlSetText _t;
    private _w = ((ctrlPosition _c) select 2) * 0.94;
    private _n = 0;
    while { (ctrlTextWidth _c) > _w && {_n < 5} } do { _c ctrlSetFontHeight ((ctrlFontHeight _c) * 0.9); _n = _n + 1; };
    if ((ctrlTextWidth _c) > _w) then {
        private _k = count _t;
        while { _k > 4 && {(ctrlTextWidth _c) > _w} } do { _k = _k - 2; _c ctrlSetText ((_t select [0, _k]) + "…"); };
        _c ctrlSetTooltip _t;
    };
};
// Texte structuré de hauteur fixe (ligne d'état, vue vide) : taille réduite par paliers jusqu'à tenir.
private _fitRich = {
    params ["_c", "_rich"];
    if (isNull _c) exitWith {};
    private _h = (ctrlPosition _c) select 3;
    {
        _c ctrlSetStructuredText parseText format ["<t size='%1'>%2</t>", _x, _rich];
        if ((ctrlTextHeight _c) <= _h) exitWith {};
    } forEach [1, 0.92, 0.84, 0.76, 0.68];
};
// Texte structuré dans un groupe défilant : hauteur du texte = hauteur réelle, la barre de défilement fait le reste.
private _fillScroll = {
    params ["_g", "_i", "_rich"];
    private _grp = _g call _ctl;
    private _c = _grp controlsGroupCtrl _i;
    if (isNull _c) exitWith {};
    _c ctrlSetStructuredText parseText _rich;
    (ctrlPosition _c) params ["_x", "", "_w"];
    _c ctrlSetPosition [_x, 0, _w, (ctrlTextHeight _c) max ((ctrlPosition _grp) select 3)];
    _c ctrlCommit 0;
    _grp ctrlSetScrollValues [0, -1];
};
private _setState = { params ["_rich"]; if (!isNull _d) then { [93924 call _ctl, _rich] call _fitRich; }; };
// Libellé de la liste coupé avec « … » pour laisser la place à l'état à droite (« Active », « Désactivée »).
private _rowText = {
    params ["_t", "_right"];
    private _pr = 93999 call _ctl;
    private _lb = 93910 call _ctl;
    private _gw = ([] call comspec_sse_fnc_uiGrid) select 2;
    _pr ctrlSetText _right;
    private _avail = ((ctrlPosition _lb) select 2) - (ctrlTextWidth _pr) - 1.6 * _gw;
    _pr ctrlSetText _t;
    if ((ctrlTextWidth _pr) <= _avail) exitWith { _t };
    private _k = count _t;
    while { _k > 4 && {(ctrlTextWidth _pr) > _avail} } do { _k = _k - 2; _pr ctrlSetText ((_t select [0, _k]) + "…"); };
    ctrlText _pr
};
private _rowOf = {
    params ["_cls"];
    ((call _rows) select { (toLower (_x select 0)) isEqualTo (toLower _cls) }) param [0, []]
};
private _current = {
    private _l = 93910 call _ctl;
    private _i = lbCurSel _l;
    if (_i < 0) exitWith { [] };
    [_l lbData _i] call _rowOf
};
private _mode = { uiNamespace getVariable ["comspec_sse_docExtMode", "list"] };
private _view = {
    if ((call _mode) isEqualTo "tuto") exitWith { "tuto" };
    ["list", "empty"] select ((call _rows) isEqualTo [])
};

// Contrôles visibles selon la vue ; bouton d'en-tête « Aide » ou « Extensions ».
private _layout = {
    private _v = call _view;
    { (_x call _ctl) ctrlShow (_v isEqualTo "list"); } forEach [93910, 93926, 93920, 93927, 93923, 93930];
    { (_x call _ctl) ctrlShow (_v isEqualTo "empty"); } forEach [93951, 93952];
    { (_x call _ctl) ctrlShow (_v isEqualTo "tuto"); } forEach [93960, 93964, 93961, 93963];
    (93903 call _ctl) ctrlSetTooltip ((["Help_D", "Back_D"] select (_v isEqualTo "tuto")) call _L);
    [93903 call _ctl, (["Help", "Back"] select (_v isEqualTo "tuto")) call _L] call _fitLine;
    _v
};

// Volet de droite pour la ligne choisie.
private _select = {
    if (isNull _d || {(call _view) isNotEqualTo "list"}) exitWith {};
    private _row = call _current;
    private _toggle = 93930 call _ctl;
    private _enabledSetting = missionNamespace getVariable ["comspec_sse_docExt_enabled", true];
    if (_row isEqualTo []) exitWith {
        [93920 call _ctl, ""] call _fitLine;
        [93927, 93921, ""] call _fillScroll;
        (93923 call _ctl) ctrlSetText "";
        _toggle ctrlEnable false;
    };
    _row params ["_cls", "_name", "_author", "_version", "_desc", "_docs", "_preview", "_active", "_builtin", ["_defaultOff", false]];
    if (_name isEqualTo "") then { _name = _cls; };
    [93920 call _ctl, _name] call _fitLine;
    private _meta = [_author] select { _x isNotEqualTo "" };
    if (_version isNotEqualTo "") then { _meta pushBack ("v" + _version); };
    if (_builtin) then { _meta pushBack ("Builtin" call _L); };
    if (_defaultOff) then { _meta pushBack ("DefaultOff" call _L); };
    private _lines = _docs apply { format ["<t color='#73cc80'>•</t> %1 <t color='#9eafa4'>(%2)</t>", [["label", _x] call comspec_sse_fnc_idDocTypes] call _esc, [_x] call _esc] };
    if (_lines isEqualTo []) then { _lines = [["NoModels" call _L] call _esc]; };
    [93927, 93921, format ["<t color='#9eafa4'>%1</t><br/>%2<br/><br/><t color='#73cc80' font='RobotoCondensedBold'>%3</t><br/>%4",
        [_meta joinString " · "] call _esc, [_desc] call _esc, ["Models" call _L] call _esc, _lines joinString "<br/>"]] call _fillScroll;
    // Aperçu : image « preview » de l'extension, sinon le recto du premier modèle (repli alt).
    private _pic = _preview;
    if (_pic isEqualTo "" || {!fileExists _pic}) then {
        _pic = if (_docs isEqualTo []) then { "" } else { ["texture", [_docs select 0, "recto", "alt", ""]] call comspec_sse_fnc_idDocTypes };
    };
    (93923 call _ctl) ctrlSetText _pic;
    [_toggle, (["Enable", "Disable"] select _active) call _L] call _fitLine;
    private _can = call _canEdit;
    _toggle ctrlEnable (_can && _enabledSetting);
    private _word = (["WordOff", "WordOn"] select _active) call _L;
    private _stateTxt = switch (true) do {
        case (!_enabledSetting): { "StOffSetting" call _L };
        case (is3DEN): { "StEden" call _L };
        case (!_can): { format ["StReadOnly" call _L, _name, _word] };
        case (_active): { format ["StActive" call _L, _name] };
        default { format ["StInactive" call _L, _name] };
    };
    [[_stateTxt] call _esc] call _setState;
};

// Tuto : étape choisie (titre, texte ; exemple de config.cpp à l'étape 5, avec le bouton « Copier l'exemple »).
private _step = {
    if (isNull _d) exitWith {};
    private _n = ((lbCurSel (93960 call _ctl)) max 0) + 1;
    private _body = format ["<t size='1.25' font='PuristaMedium' color='#73cc80'>%1</t><br/><br/>%2", [format ["T%1_T", _n] call _L] call _esc, format ["T%1_B", _n] call _L];
    private _code = _n isEqualTo 5;
    if (_code) then {
        // Retraits gardés (espaces insécables), police à chasse fixe, un peu plus petite ; commentaires en gris.
        private _nb = toString [160];
        private _lines = (call _skeleton) apply {
            private _t = [_x] call _esc;
            private _i = 0;
            while { (_t select [_i, 1]) isEqualTo " " } do { _i = _i + 1; };
            private _lead = "";
            for "_k" from 1 to _i do { _lead = _lead + _nb + _nb; };
            private _col = ["#e6ede6", "#9eafa4"] select ((_t select [_i, 2]) isEqualTo "//");
            format ["<t color='%1'>%2%3</t>", _col, _lead, _t select [_i]]
        };
        _body = _body + format ["<br/><br/><t font='EtelkaMonospacePro' size='0.72'>%1</t>", _lines joinString "<br/>"];
    };
    (93963 call _ctl) ctrlShow _code;
    // Lecture plus haute quand le bouton « Copier » n'est pas affiché (il est posé sous la lecture).
    private _grp = 93961 call _ctl;
    (ctrlPosition _grp) params ["_gx", "_gy", "_gw"];
    private _gh = ((ctrlPosition (93964 call _ctl)) select 3) - (([] call comspec_sse_fnc_uiGrid) select 3) * ([0.6, 2.5] select _code);
    _grp ctrlSetPosition [_gx, _gy, _gw, _gh];
    _grp ctrlCommit 0;
    [93961, 93962, _body] call _fillScroll;
    [[(["StTuto", "StTutoCode"] select _code) call _L] call _esc] call _setState;
};
private _fillTuto = {
    private _l = 93960 call _ctl;
    private _keep = (lbCurSel _l) max 0;
    lbClear _l;
    for "_n" from 1 to _steps do { _l lbAdd format ["%1. %2", _n, format ["T%1_T", _n] call _L]; };
    _l lbSetCurSel _keep;
    call _step;
};

// Liste de gauche (sélection gardée), puis volet de droite ; ou vue vide ; ou tuto.
private _fill = {
    if (isNull _d) exitWith {};
    private _all = call _rows;
    private _v = call _layout;
    [93902 call _ctl, if (_v isEqualTo "tuto") then { format ["SummaryTuto" call _L, _steps] } else { format ["Summary" call _L, count _all, count (_all select { _x select 7 })] }] call _fitLine;
    if (_v isEqualTo "tuto") exitWith { call _fillTuto; };
    if (_v isEqualTo "empty") exitWith {
        [93951 call _ctl, format ["<t size='1.25' font='PuristaMedium' color='#73cc80'>%1</t><br/><br/>%2", ["EmptyTitle" call _L] call _esc, "EmptyBody" call _L]] call _fitRich;
        [93952 call _ctl, "OpenTuto" call _L] call _fitLine;
        [[("StEmpty" call _L)] call _esc] call _setState;
    };
    private _l = 93910 call _ctl;
    private _keep = if ((lbCurSel _l) >= 0) then { _l lbData (lbCurSel _l) } else { "" };
    lbClear _l;
    private _sel = -1;
    {
        _x params ["_cls", "_name", "", "", "", "", "", "_active"];
        private _full = [_name, _cls] select (_name isEqualTo "");
        private _right = (["WordOffCap", "WordOnCap"] select _active) call _L;
        private _i = _l lbAdd ([_full, _right] call _rowText);
        _l lbSetData [_i, _cls];
        _l lbSetTooltip [_i, _full];
        _l lbSetTextRight [_i, _right];
        _l lbSetColor [_i, [[0.62, 0.68, 0.64, 1], [0.9, 0.93, 0.9, 1]] select _active];
        _l lbSetColorRight [_i, [[0.62, 0.68, 0.64, 1], [0.45, 0.8, 0.5, 1]] select _active];
        if (_cls isEqualTo _keep) then { _sel = _i; };
    } forEach _all;
    _l lbSetCurSel (_sel max 0);
    call _select;
};

switch (_op) do {
    case "open": {
        uiNamespace setVariable ["comspec_sse_docExtMode", ["list", "tuto"] select (_a isEqualTo "tuto")];
        if (!isNull _d) exitWith { call _fill; true };
        createDialog "COMSPEC_SSE_DocExtensions"
    };
    case "load": {
        _d = _a;
        uiNamespace setVariable ["comspec_sse_docExtDisplay", _d];
        // Titre et boutons fixes : même ajustement que les libellés posés en SQF.
        { [_x call _ctl, ctrlText (_x call _ctl)] call _fitLine; } forEach [93901, 93931, 93963];
        call _fill;
        true
    };
    case "unload": { uiNamespace setVariable ["comspec_sse_docExtDisplay", displayNull]; true };
    case "select": { call _select; true };
    case "step": { call _step; true };
    case "view": { if (isNull _d) then { "" } else { call _view } };
    case "refresh": { if (isNull _d) exitWith { false }; call _fill; true };
    case "help": {
        if (isNull _d) exitWith { false };
        private _to = if (_a isEqualTo "tuto") then { "tuto" } else { ["tuto", "list"] select ((call _mode) isEqualTo "tuto") };
        uiNamespace setVariable ["comspec_sse_docExtMode", _to];
        call _fill;
        true
    };
    case "copy": {
        if (isNull _d) exitWith { false };
        copyToClipboard ((call _skeleton) joinString _nl);
        // En ligne, Arma peut refuser le presse-papiers à un client : le texte reste affiché dans le tuto.
        [[(["Copied", "CopiedMp"] select (isMultiplayer && {!isServer})) call _L] call _esc] call _setState;
        true
    };
    case "toggle": {
        if (isNull _d) exitWith { false };
        private _row = call _current;
        if (_row isEqualTo []) exitWith { false };
        _row params ["_cls", "_name", "", "", "", "", "", "_active"];
        if (_name isEqualTo "") then { _name = _cls; };
        if !(call _canEdit) exitWith { call _select; false };
        // Ligne d'état avant l'envoi : sur un serveur hébergé, le serveur relit la fenêtre dans la foulée.
        [[format ["StSent" call _L, _name, (["WordOn", "WordOff"] select _active) call _L]] call _esc] call _setState;
        (93930 call _ctl) ctrlEnable false;
        ["set", [_cls, !_active]] remoteExecCall ["comspec_sse_fnc_docExtServer", 2];
        true
    };
    default { false };
};
