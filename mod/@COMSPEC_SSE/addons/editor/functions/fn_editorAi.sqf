/*
    Page « IA de la zone » de l'éditeur SSE (client du Zeus). Contrôles : dialogs/aizone.hpp ; serveur : fn_editorAiServer.
    Un seul chemin, de gauche à droite :
      1. la liste (gauche) : « Détecter autour » (rayon), « Sélection Zeus », recherche, « Filtres » (volet), tri par colonne ;
         un clic coche ou décoche une IA ; icônes : papiers (couleur = vrais, faux, ment, pas encore) et profil Athena ;
      2. les réglages des IA cochées (milieu), un onglet par sujet : Identité (source, authenticité, pays, volet « Importer
         depuis Athena »), Papiers (papiers portés, ceux qu'il a, voir, rendre vrai / faux, donner), Comportement
         (réaction au contrôle, « Questions et réponses… »), Camp ;
      3. « Appliquer » (IA cochées), « Appliquer à tout le groupe », « Mémoire de la carte… » (volet : garder, recharger,
         supprimer ; anciennement « Gardés pour les prochaines parties »).
    À droite, « Ce que verra le joueur » : le papier choisi rendu comme dans la visionneuse (fn_editorAiCard, recto / verso),
    et ce que l'IA dira au contrôle ; jamais une case vide.
    Rien n'est envoyé avant « Appliquer » (ligne d'état : « Modifié, pas encore appliqué »), sauf les boutons qui le disent
    (« Rendre vrai / faux », « Donner maintenant », « Questions et réponses… » : ils agissent tout de suite sur l'IA).
    Aucune alerte Arma : tout retour s'écrit dans la ligne d'état en bas de la fenêtre.
    Visite guidée en trois étapes (fn_editorCoach), une fois par profil (profileNamespace « comspec_sse_aiZoneCoach »),
    rouverte par « ? ».
    Liste (fn_editorAiServer « rows ») : [netId, nom donné, vrai nom, vérité, camp, rôle, réf. profil, id profil, nb papiers,
    comportement, groupe, nom d'unité Arma, papiers portés, nature, id gardé, pays des papiers].
    Ops : load · page [visible] · layout · tab nom · scan · scanZeus · scanUnits [unités] · rescan · filter · filters ·
    filtersClear · sort colonne · click · checkAll · checkNone · sheet · changed · fold · profFilter · refresh · docSel ·
    docOpen · docMark · docTrue · docFalse · docGive · flip · memory · keptSel · keptReload · keptForget · keepChecked ·
    applyChecked · applyGroup · answers · coach · help · preview · aiReply [genre, …] · athenaDone · keepItem · post · idcs
    [propriétaire] · voletClose · escape · audit · refreshLate
    Volets (« fold » : Importer depuis Athena, « filters », « memory ») : fermés au départ, un seul ouvert, par-dessus un
    voile qui les ferme d'un clic à côté ; Échap aussi.
*/
params [["_op", ""], ["_arg", []]];
if (!hasInterface) exitWith { false };
// Appel distant : réponses du serveur seulement (aiReply).
if (isRemoteExecuted && {!isServer} && {remoteExecutedOwner isNotEqualTo 2}) exitWith { false };
disableSerialization;
// Envoi à Athena depuis ce poste (SubmitIdCheck, contexte planifié) : renvoie la réponse brute (« OK|id|kept »).
if (_op isEqualTo "post") exitWith {
    if !(_arg isEqualType createHashMap) exitWith { "" };
    if !(["has"] call comspec_sse_fnc_idAthenaPack) exitWith { "ERR|no_link" };
    _arg set ["map", missionNamespace getVariable ["COMSPEC_MapId", 1]];
    _arg set ["op", missionNamespace getVariable ["comspec_sse_id_opKey", ""]];
    private _json = [_arg] call comspec_sse_fnc_toJsonApprox;
    private _parts = [];
    for "_i" from 0 to ((count _json) - 1) step 4000 do { _parts pushBack (_json select [_i, 4000]); };
    private _raw = ["SubmitIdCheck", _parts] call comspec_sse_fnc_extensionCall;
    if (_raw isEqualType []) then { _raw = _raw param [0, ""]; };
    if !(_raw isEqualType "") then { _raw = str _raw; };
    _raw
};

// --- Registre des contrôles (aizone.hpp) : chaque contrôle a UN propriétaire (cadre, liste, onglet, volet…). Seuls les
// contrôles des propriétaires visibles sont montrés (_showOnly, à chaque changement d'état et à la fin de la construction).
// 95508 est dans le groupe 95507 (il suit son groupe). Volets : un seul ouvert à la fois, par-dessus un voile (95850)
// qui les ferme d'un clic à côté ; Échap les ferme aussi. Vérifié sans le jeu : tests/Unit/SseAiZoneWindowAssetTest.php.
// Noms en SQF : la casse ne compte pas (_l et _loc sont la même variable) ; aucun nom local ne reprend un nom de ce niveau.
private _OWN = createHashMapFromArray [
    ["frame", [95000]],
    ["list", [95001, 95002, 95003, 95004, 95005, 95006, 95007, 95008, 95009, 95010, 95011, 95012, 95013, 95014, 95015, 95016, 95023, 95024, 95025, 95026]],
    ["listRows", [95017]],
    ["listEmpty", [95018, 95019, 95020]],
    ["mid", [95030, 95031, 95032, 95033, 95034, 95035, 95036, 95037]],
    ["tab_id", [95100, 95101, 95102, 95103, 95104, 95105, 95106, 95107, 95108, 95109]],
    ["tab_docs", [95200, 95201, 95202, 95203, 95204, 95206, 95207, 95208, 95209, 95210, 95211, 95212, 95213, 95214]],
    ["tab_beh", [95300, 95301, 95302, 95303, 95304]],
    ["tab_side", [95400, 95401, 95402]],
    ["pv", [95500, 95501, 95502, 95503, 95504, 95505, 95506, 95507]],
    ["foot", [95600, 95601, 95602, 95603, 95604]],
    ["scrim", [95850]],
    ["volet_ath", [95115, 95116, 95110, 95113, 95114, 95111, 95112]],
    ["volet_filt", [95700, 95701, 95702, 95703, 95704, 95705, 95706, 95707, 95708, 95709]],
    ["volet_mem", [95800, 95801, 95802, 95803, 95804, 95805, 95806, 95807, 95808, 95809]]
];
private _VOLETS = ["ath", "filt", "mem"];
private _TABBTN = createHashMapFromArray [["id", 95032], ["docs", 95033], ["beh", 95034], ["side", 95035]];
private _ownerOf = createHashMap;
{ private _owner = _x; { _ownerOf set [_x, _owner]; } forEach (_OWN get _owner); } forEach (keys _OWN);
private _ALL = keys _ownerOf;
_ALL sort true;
// « idcs » : tous les contrôles ; [« idcs », propriétaire] : ceux d'un propriétaire (autotest).
if (_op isEqualTo "idcs") exitWith { if (_arg isEqualType "") then { _OWN getOrDefault [_arg, []] } else { _ALL } };

private _d = uiNamespace getVariable ["comspec_sse_editorDisplay", displayNull];
private _st = uiNamespace getVariable ["comspec_sse_editorAi", createHashMap];
uiNamespace setVariable ["comspec_sse_editorAi", _st];
// Textes : nouvelles clés (AZ), sinon les anciennes (ED), gardées pour les autres appelants.
private _loc = { if (isLocalized ("STR_COMSPEC_SSE_AZ_" + _this)) then { localize ("STR_COMSPEC_SSE_AZ_" + _this) } else { localize ("STR_COMSPEC_SSE_ED_" + _this) } };
private _esc = { params ["_t"]; if !(_t isEqualType "") then { _t = str _t; }; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };
private _ctrl = { _d displayCtrl _this };
private _send = {
    params ["_sop", ["_x1", []]];
    private _call = [_sop, [player] + _x1];
    if (isServer) then { _call call comspec_sse_fnc_editorAiServer; } else { _call remoteExecCall ["comspec_sse_fnc_editorAiServer", 2]; };
};
// Voie de l'addon identity (papier en plein écran, rechargement du paquet).
private _sendId = { params ["_sub", ["_x1", []]]; ["send", ["aiCfg", [player, _sub, _x1]]] call comspec_sse_fnc_idClient; };

// --- Valeurs des listes. Papiers à porter : anciens alias (fn_idRegistry « kit »), puis chaque clé du registre (extensions
// actives comprises). Même valeur que l'attribut Eden COMSPEC_ID_Carry.
private _papers = ["auto", "none", "card", "passport", "license", "card_license", "card_passport", "military"];
private _extNames = createHashMap;
{ _extNames set [toLower (_x select 0), _x select 1]; } forEach (["extensions"] call comspec_sse_fnc_idDocTypes);
{
    private _ext = toLower ((["get", _x] call comspec_sse_fnc_idDocTypes) getOrDefault ["extension", ""]);
    private _label = ["label", _x] call comspec_sse_fnc_idDocTypes;
    _papers pushBack [_x, if (_ext isEqualTo "") then { _label } else { format ["%1 (%2)", _label, _extNames getOrDefault [_ext, _ext]] }];
} forEach (["list", [""]] call comspec_sse_fnc_idDocTypes);
private _kinds = ["civil", "cover", "forged"];
private _behav = ["auto", "coopere", "nerveux", "ment", "refuse", "fuit", "agressif", "rendu"];
private _sides = ["auto", "civ", "west", "east", "guer"];
private _roles = ["civilian", "military", "suspect", "hvt"];
private _states = ["athena", "generated", "pending", "kept"];
private _docTypes = (["list", [""]] call comspec_sse_fnc_idDocTypes) apply { [_x, ["label", _x] call comspec_sse_fnc_idDocTypes] };
private _docLabel = { params ["_k"]; private _n = ["label", _k] call comspec_sse_fnc_idDocTypes; if (_n isEqualType "" && {_n isNotEqualTo ""}) then { _n } else { ("Doc_" + _k) call _loc } };
private _docShort = { params ["_k"]; if (isLocalized ("STR_COMSPEC_SSE_ED_DocS_" + _k)) exitWith { localize ("STR_COMSPEC_SSE_ED_DocS_" + _k) }; [_k] call _docLabel };
private _papersLabel = { params ["_k"]; if (isLocalized ("STR_COMSPEC_SSE_ED_Papers_" + _k)) exitWith { ("Papers_" + _k) call _loc }; private _r = (_papers select { _x isEqualType [] && {(_x select 0) isEqualTo _k} }) param [0, [_k, _k]]; _r select 1 };
private _countries = {
    private _byCode = [] call comspec_sse_fnc_idCountries;
    private _codes = keys _byCode;
    _codes sort true;
    _codes apply { [_x, format ["%1 · %2", _x, (_byCode get _x) getOrDefault ["name", _x]]] }
};

// --- Couleurs (fond sombre de la page).
private _cText = [0.914, 0.941, 0.925, 1];
private _cMuted = [0.624, 0.690, 0.659, 1];
private _cGreen = [0.361, 0.780, 0.420, 1];
private _cRed = [0.95, 0.42, 0.35, 1];
private _cAmber = [0.95, 0.72, 0.28, 1];
private _truthColor = createHashMapFromArray [["true", _cGreen], ["lies", _cAmber], ["cover", _cAmber], ["forged", _cRed], ["pending", _cMuted], ["none", _cMuted]];
// Mêmes couleurs en texte structuré.
private _hMuted = "#9fb0a8";
private _hGreen = "#5cc76b";
private _hAmber = "#f2b847";
private _hRed = "#f26b59";
private _icoPaper = "\z\comspec_sse\addons\editor\data\type_paper_ca.paa";
private _icoProf = "\z\comspec_sse\addons\editor\data\type_identity_ca.paa";
private _picOff = "\a3\ui_f\data\GUI\RscCommon\RscCheckBox\CheckBox_unchecked_ca.paa";
private _picOn = "\a3\ui_f\data\GUI\RscCommon\RscCheckBox\CheckBox_checked_ca.paa";

// --- Grille et taille de texte minimale (même calcul que ui_macros.hpp / comspec_sse_fnc_uiGrid).
private _gh = (((safezoneW / safezoneH) min 1.2) / 1.2) / 25;
private _minH = _gh * 0.78;
// Texte d'un bouton ou d'un libellé : posé, police réduite par paliers sans descendre sous la taille minimale, puis
// raccourci avec « … » (libellé entier en info-bulle) s'il ne tient toujours pas.
private _setFit = {
    params ["_c", "_t", ["_tip", ""]];
    if (isNull _c) exitWith {};
    private _base = _c getVariable ["comspec_sse_fh0", -1];
    if (_base < 0) then { _base = ctrlFontHeight _c; _c setVariable ["comspec_sse_fh0", _base]; };
    _c ctrlSetFontHeight _base;
    _c ctrlSetText _t;
    private _w = ((ctrlPosition _c) select 2) * 0.94;
    private _fh = _base;
    while { (ctrlTextWidth _c) > _w && {(_fh * 0.95) >= _minH} } do { _fh = _fh * 0.95; _c ctrlSetFontHeight _fh; };
    if ((ctrlTextWidth _c) > _w) then {
        private _s = _t;
        while { (count _s) > 2 && {(ctrlTextWidth _c) > _w} } do { _s = _s select [0, (count _s) - 1]; _c ctrlSetText (_s + "…"); };
        _c ctrlSetTooltip ([_t, _t + " · " + _tip] select (_tip isNotEqualTo ""));
    } else {
        if (_tip isNotEqualTo "") then { _c ctrlSetTooltip _tip; };
    };
};
private _refit = { { private _c = _x call _ctrl; [_c, ctrlText _c, ""] call _setFit; } forEach _this; };
// Texte d'une cellule de liste : raccourci avec « … » à la largeur de la colonne (cases), mesuré sur un texte caché.
private _cell = {
    params ["_t", "_wCells", ["_size", 0.8]];
    private _m = _d getVariable ["comspec_sse_azMeasure", controlNull];
    if (isNull _m) then {
        _m = _d ctrlCreate ["RscText", -1];
        _m ctrlSetPosition [-30, -30, 30, 1];
        _m ctrlShow false;
        _m ctrlCommit 0;
        _d setVariable ["comspec_sse_azMeasure", _m];
    };
    _m ctrlSetFont "RobotoCondensed";
    _m ctrlSetFontHeight (_gh * _size);
    private _max = _wCells * (_gh * 0.75) * 0.94;
    _m ctrlSetText _t;
    if ((ctrlTextWidth _m) <= _max) exitWith { _t };
    private _s = _t;
    while { (count _s) > 2 && {(ctrlTextWidth _m) > _max} } do { _s = _s select [0, (count _s) - 1]; _m ctrlSetText (_s + "…"); };
    _s + "…"
};

// --- Affichage : un contrôle caché est masqué, désactivé et sorti de l'écran (aucun clic ne tombe sur un contrôle masqué).
private _home = _d getVariable ["comspec_sse_azHome", createHashMap];
if (!isNull _d) then { _d setVariable ["comspec_sse_azHome", _home]; };
private _vis = {
    params ["_idcs", "_on"];
    {
        private _c = _x call _ctrl;
        if (!isNull _c) then {
            if !(_x in _home) then { _home set [_x, ctrlPosition _c]; };
            private _p = _home get _x;
            _c ctrlShow _on;
            _c ctrlEnable _on;
            _c ctrlSetPosition ([[-30, -30, _p select 2, _p select 3], _p] select _on);
            _c ctrlCommit 0;
        };
    } forEach _idcs;
};
// Propriétaires visibles d'après l'état : rien hors de la page ; sinon cadre, liste (ou son état vide), en-tête du milieu,
// l'onglet actif, l'aperçu, la barre du bas, et au plus un volet (avec son voile).
private _visibleOwners = {
    if (isNull _d || {!(_st getOrDefault ["page", false])}) exitWith { [] };
    private _tabNow = _st getOrDefault ["tab", "id"];
    if !(_tabNow in ["id", "docs", "beh", "side"]) then { _tabNow = "id"; _st set ["tab", _tabNow]; };
    private _out = ["frame", "list", ["listRows", "listEmpty"] select (_st getOrDefault ["empty", true]), "mid", "tab_" + _tabNow, "pv", "foot"];
    private _volet = _st getOrDefault ["volet", ""];
    if (_volet in _VOLETS) then { _out append ["scrim", "volet_" + _volet]; };
    _out
};
// Montre les contrôles des propriétaires donnés, cache TOUS les autres (rien ne reste affiché par oubli).
private _showOnly = {
    params ["_owners"];
    _st set ["owners", _owners];
    { if !(_x in _owners) then { [_OWN get _x, false] call _vis; }; } forEach (keys _OWN);
    { [_OWN getOrDefault [_x, []], true] call _vis; } forEach _owners;
};
// Contrôle montré sous condition (invite, phrase d'attente) : jamais si son propriétaire est caché.
private _showIf = {
    params ["_idc", "_cond"];
    [[_idc], _cond && {(_ownerOf getOrDefault [_idc, ""]) in (_st getOrDefault ["owners", []])}] call _vis;
};
// Audit de l'état affiché : contrôles montrés d'un propriétaire caché, chevauchements entre contrôles d'une même couche
// (la page, ou le volet ouvert), textes coupés. Fonds, invites sous un champ, repère d'onglet et groupes posés dessous à dessein.
private _UNDER = [95000, 95007, 95008, 95013, 95015, 95018, 95036, 95037, 95113, 95114, 95115, 95116, 95209, 95502, 95503, 95504, 95507, 95600, 95700, 95701, 95800, 95801, 95850];
private _audit = {
    private _owners = _st getOrDefault ["owners", []];
    private _orphans = [];
    private _shown = [];
    {
        private _c = _x call _ctrl;
        if (!isNull _c && {ctrlShown _c}) then {
            if !((_ownerOf get _x) in _owners) then { _orphans pushBack _x; };
            _shown pushBack _x;
        };
    } forEach _ALL;
    private _layerOf = { params ["_idc"]; ["page", "volet"] select ((_ownerOf get _idc) in ["scrim", "volet_ath", "volet_filt", "volet_mem"]) };
    private _overlaps = [];
    private _flat = _shown select { !(_x in _UNDER) };
    private _eps = _gh * 0.02;
    for "_i" from 0 to ((count _flat) - 2) do {
        for "_j" from (_i + 1) to ((count _flat) - 1) do {
            private _ia = _flat select _i;
            private _ib = _flat select _j;
            if (([_ia] call _layerOf) isEqualTo ([_ib] call _layerOf)) then {
                (ctrlPosition (_ia call _ctrl)) params ["_x1", "_y1", "_w1", "_h1"];
                (ctrlPosition (_ib call _ctrl)) params ["_x2", "_y2", "_w2", "_h2"];
                if ((_x1 + _w1 - _eps) > _x2 && {(_x2 + _w2 - _eps) > _x1} && {(_y1 + _h1 - _eps) > _y2} && {(_y2 + _h2 - _eps) > _y1}) then { _overlaps pushBack [_ia, _ib]; };
            };
        };
    };
    // Texte coupé : libellé ou bouton plus large que sa case (après réduction), aide plus haute que sa case.
    private _clipped = [];
    {
        private _c = _x call _ctrl;
        (ctrlPosition _c) params ["", "", "_w", "_h"];
        private _type = ctrlType _c;
        if (_type in [0, 1] && {!((ctrlStyle _c) in [48, 2096])} && {(ctrlText _c) isNotEqualTo ""} && {(ctrlTextWidth _c) > _w * 1.01}) then { _clipped pushBack _x; };
        if (_type isEqualTo 13 && {(ctrlTextHeight _c) > _h * 1.05}) then { _clipped pushBack _x; };
    } forEach _shown;
    private _open = _VOLETS select { ("volet_" + _x) in _owners };
    createHashMapFromArray [
        ["ok", _orphans isEqualTo [] && {_overlaps isEqualTo []} && {_clipped isEqualTo []} && {(count _open) <= 1}],
        ["owners", +_owners], ["orphans", _orphans], ["overlaps", _overlaps], ["clipped", _clipped], ["volets", _open]
    ]
};

// --- Listes déroulantes.
private _fill = {
    // [idc, codes (ou [code, libellé]), préfixe de clé, [[donnée, texte]…] en tête]
    params ["_idc", "_codes", "_prefix", ["_head", []]];
    private _c = _idc call _ctrl;
    lbClear _c;
    { private _i = _c lbAdd (_x select 1); _c lbSetData [_i, _x select 0]; } forEach _head;
    { if (_x isEqualType []) then { private _i = _c lbAdd (_x select 1); _c lbSetData [_i, _x select 0]; } else { private _i = _c lbAdd ((_prefix + _x) call _loc); _c lbSetData [_i, _x]; }; } forEach _codes;
    _c lbSetCurSel 0;
};
private _data = { private _c = _this call _ctrl; private _i = lbCurSel _c; if (_i < 0) exitWith { "" }; _c lbData _i };
private _pick = { params ["_idc", "_code"]; private _c = _idc call _ctrl; for "_i" from 0 to ((lbSize _c) - 1) do { if ((_c lbData _i) isEqualTo _code) exitWith { _c lbSetCurSel _i; }; }; };
private _spec = { createHashMapFromArray [["profile", 95101 call _data], ["papers", 95201 call _data], ["kind", 95104 call _data], ["behaviour", 95301 call _data], ["side", 95401 call _data], ["country", 95107 call _data]] };

// --- Ligne d'état (en bas à gauche) : le dernier retour (« 3 IA mises à jour. », « Modifié, pas encore appliqué »).
// Jamais d'alerte Arma. Réglages modifiés : aussi un point devant « Appliquer ».
private _statusDraw = {
    (_st getOrDefault ["status", ["", "info"]]) params ["_t", "_kind"];
    private _col = [_hMuted, _hGreen, _hAmber] select ((["info", "ok", "warn"] find _kind) max 0);
    private _c = 95601 call _ctrl;
    _c ctrlSetStructuredText parseText (["", format ["<t color='%1'>%2</t>", _col, [_t] call _esc]] select (_t isNotEqualTo ""));
    _c ctrlSetTooltip ([("Status_D" call _loc), _t] select (_t isNotEqualTo ""));
};
private _note = { params ["_t", ["_kind", "info"]]; _st set ["status", [_t, _kind]]; call _statusDraw; };

private _rowOf = { params ["_net"]; private _rows = _st getOrDefault ["rows", []]; private _i = _rows findIf { (_x select 0) isEqualTo _net }; if (_i < 0) then { [] } else { _rows select _i } };
private _checkedUnits = { ((_st getOrDefault ["checked", []]) apply { objectFromNetId _x }) select { !isNull _x && {alive _x} } };
private _ctxPos = {
    private _ctx = (uiNamespace getVariable ["comspec_sse_editorState", createHashMap]) getOrDefault ["ctx", [[], objNull]];
    private _p = _ctx param [0, []];
    if (_p isNotEqualTo []) exitWith { _p };
    if (!isNull curatorCamera) exitWith { screenToWorld [0.5, 0.5] };
    getPosATL player
};
private _setRowMark = { params ["_lb", "_r", "_on"]; _lb lnbSetPicture [[_r, 0], [_picOff, _picOn] select _on]; _lb lnbSetColor [[_r, 1], [_cText, _cGreen] select _on]; };
private _counts = {
    private _n = count (_st getOrDefault ["checked", []]);
    [95025 call _ctrl, format ["Checked" call _loc, _n]] call _setFit;
    private _lab = [("Apply" call _loc), format ["%1 (%2)", "Apply" call _loc, _n]] select (_n > 0);
    [95604 call _ctrl, [_lab, "● " + _lab] select (_st getOrDefault ["dirty", false])] call _setFit;
    { (_x call _ctrl) ctrlEnable (_n > 0); } forEach [95603, 95604, 95804];
};

// --- Liste des IA : recherche, filtres, tri.
private _stateOf = { params ["_row"]; if ((_row select 7) isNotEqualTo "") then { "athena" } else { ["generated", "pending"] select ((_row select 3) isEqualTo "pending") } };
private _truthRank = createHashMapFromArray [["forged", 0], ["lies", 1], ["cover", 2], ["true", 3], ["pending", 4], ["none", 5]];
private _render = {
    private _lb = 95017 call _ctrl;
    _st set ["busy", true];
    lnbClear _lb;
    private _q = toLower (trim (ctrlText (95009 call _ctrl)));
    [95008, _q isEqualTo ""] call _showIf;
    private _fs = 95703 call _data;
    private _fr = 95705 call _data;
    private _fe = 95707 call _data;
    private _nf = { _x isNotEqualTo "" } count [_fs, _fr, _fe];
    [95010 call _ctrl, [("Filters" call _loc), format ["FiltersN" call _loc, _nf]] select (_nf > 0)] call _setFit;
    private _checked = _st getOrDefault ["checked", []];
    private _rows = (_st getOrDefault ["rows", []]) select {
        private _x0 = _x;
        _x0 params ["", "_given", "_true", "", "_side", "_role", "_pref", "", "", "", "_grp", "_uname", "", "", "_keptId"];
        private _state = [_x0] call _stateOf;
        (_fs isEqualTo "" || {_side isEqualTo _fs}) && {_fr isEqualTo "" || {_role isEqualTo _fr}}
            && {_fe isEqualTo "" || {if (_fe isEqualTo "kept") then { _keptId isNotEqualTo "" } else { _state isEqualTo _fe }}}
            && {_q isEqualTo "" || {((toLower format ["%1 %2 %3 %4 %5", _given, _true, _uname, _grp, _pref]) find _q) >= 0}}
    };
    // Tri : colonne et sens (un second clic inverse).
    (_st getOrDefault ["sort", ["name", true]]) params ["_col", "_asc"];
    private _key = switch (_col) do {
        case "role": { { _roles find (_x select 5) } };
        case "docs": { { _truthRank getOrDefault [_x select 3, 9] } };
        case "prof": { { [1, 0] select ((_x select 7) isNotEqualTo "") } };
        default { { toLower ([_x select 1, _x select 2] select ((_x select 1) isEqualTo "")) } };
    };
    private _n = -1;
    private _pairs = _rows apply { _n = _n + 1; [call _key, _n] };
    _pairs sort _asc;
    _rows = _pairs apply { _rows select (_x select 1) };
    {
        _x params ["_net", "_given", "_true", "_truth", "", "_role", "_pref", "_pid", "_nd", "", "_grp"];
        private _shown = [_given, format ["(%1)", "Truth_pending" call _loc]] select (_given isEqualTo "");
        private _r = _lb lnbAddRow ["", [_shown, 6.6] call _cell, [("Role_" + _role) call _loc, 3.6] call _cell, [str _nd, "-"] select (_nd isEqualTo 0), ""];
        _lb lnbSetData [[_r, 0], _net];
        [_lb, _r, _net in _checked] call _setRowMark;
        _lb lnbSetPicture [[_r, 3], _icoPaper];
        _lb lnbSetPictureColor [[_r, 3], _truthColor getOrDefault [_truth, _cMuted]];
        _lb lnbSetColor [[_r, 3], _truthColor getOrDefault [_truth, _cMuted]];
        if (_pid isNotEqualTo "") then { _lb lnbSetPicture [[_r, 4], _icoProf]; _lb lnbSetPictureColor [[_r, 4], _cGreen]; };
        // Vrai nom : pour le Zeus seulement, au survol du nom.
        _lb lnbSetTooltip [[_r, 1], format ["%1 · %2 · %3", ("Truth_" + _truth) call _loc, format ["TrueName" call _loc, [_true, "?"] select (_true isEqualTo "")], _grp]];
    } forEach _rows;
    _lb lnbSetCurSelRow -1;
    _st set ["busy", false];
    // En-têtes : flèche sur la colonne triée.
    {
        _x params ["_idc", "_c", "_k"];
        if (_k isNotEqualTo "") then { [_idc call _ctrl, (_k call _loc) + (["", [" ▼", " ▲"] select _asc] select (_col isEqualTo _c))] call _setFit; };
    } forEach [[95011, "name", "HName"], [95012, "role", "HRole"]];
    private _rowsAll = _st getOrDefault ["rows", []];
    [95002 call _ctrl, format ["Count" call _loc, count _rowsAll]] call _setFit;
    // État vide : avant la réponse du serveur, liste vide, ou filtres trop serrés (le texte renvoie aux boutons du haut).
    private _isEmpty = _rows isEqualTo [];
    _st set ["empty", _isEmpty];
    if (_isEmpty) then {
        private _scanned = _st getOrDefault ["scanned", false];
        private _filtered = _rowsAll isNotEqualTo [];
        (95019 call _ctrl) ctrlSetText ([[("EmptyTitle" call _loc), ("EmptyFilterTitle" call _loc)] select _filtered, "Waiting" call _loc] select !_scanned);
        (95020 call _ctrl) ctrlSetStructuredText parseText ([[("EmptyText" call _loc), ("EmptyFilterText" call _loc)] select _filtered, ""] select !_scanned);
    };
    call _counts;
    call _layout;
};
// Ce qui est affiché : d'abord le registre (_showOnly : tout ce qui n'appartient pas à un propriétaire visible est caché),
// puis les détails de l'état (onglet actif, boutons actifs). Appelé à chaque changement d'état et en fin de construction.
private _layout = {
    if (isNull _d) exitWith {};
    [call _visibleOwners] call _showOnly;
    if !(_st getOrDefault ["page", false]) exitWith {};
    private _tabNow = _st getOrDefault ["tab", "id"];
    private _volet = _st getOrDefault ["volet", ""];
    // Volet ouvert : la liste dessous ne prend pas de clic (le voile les arrête déjà).
    (95017 call _ctrl) ctrlEnable (_volet isEqualTo "");
    // Onglet actif : texte clair, repère vert dessous.
    {
        private _b = (_TABBTN get _x) call _ctrl;
        private _on = _x isEqualTo _tabNow;
        _b ctrlSetTextColor ([_cMuted, _cText] select _on);
        _b ctrlSetBackgroundColor ([[0.047, 0.071, 0.063, 1], [0.09, 0.157, 0.122, 1]] select _on);
        if (_on) then {
            private _p = ctrlPosition _b;
            private _m = 95037 call _ctrl;
            _m ctrlSetPosition [_p select 0, (_p select 1) + (_p select 3) - _gh * 0.12, _p select 2, _gh * 0.15];
            _m ctrlCommit 0;
        };
    } forEach ["id", "docs", "beh", "side"];
    [95109 call _ctrl, format ["%1 %2", ["▸", "▾"] select (_volet isEqualTo "ath"), format ["Fold" call _loc, count (_st getOrDefault ["profiles", []])]]] call _setFit;
    // Contrôles sous condition, jamais hors de leur propriétaire (invites des champs vides, phrases d'attente).
    [95008, (trim (ctrlText (95009 call _ctrl))) isEqualTo ""] call _showIf;
    [95114, (ctrlText (95111 call _ctrl)) isEqualTo ""] call _showIf;
    [95209, _st getOrDefault ["docsNone", false]] call _showIf;
    [95504, _st getOrDefault ["pvEmpty", true]] call _showIf;
    // États des boutons après affichage (_vis réactive tout ce qu'il montre).
    private _n = count (_st getOrDefault ["checked", []]);
    { (_x call _ctrl) ctrlEnable (_n > 0); } forEach [95603, 95604, 95804];
    { (_x call _ctrl) ctrlEnable ((_st getOrDefault ["docSel", ""]) isNotEqualTo ""); } forEach [95210, 95211];
    (95213 call _ctrl) ctrlEnable (([_st getOrDefault ["cur", ""]] call _rowOf) isNotEqualTo []);
    { (_x call _ctrl) ctrlEnable ((95806 call _data) isNotEqualTo ""); } forEach [95807, 95808];
    (95505 call _ctrl) ctrlEnable (_st getOrDefault ["canFlip", false]);
};
// Un seul volet à la fois (« ath », « filt », « mem », ou "" : aucun). La visite guidée s'efface devant un volet.
private _setVolet = {
    params ["_which"];
    if !(_which in _VOLETS) then { _which = ""; };
    if (_which isNotEqualTo "" && {!isNil "comspec_sse_fnc_editorCoach"}) then { ["stop"] call comspec_sse_fnc_editorCoach; };
    _st set ["volet", _which];
    call _layout;
};
private _keptFill = {
    params ["_list"];
    private _c = 95806 call _ctrl;
    lbClear _c;
    {
        _x params ["_id", "_label", "_src", "_when"];
        private _i = _c lbAdd format ["%1 · %2%3", _label, ("KeptSrc_" + _src) call _loc, ["", " · " + _when] select (_when isNotEqualTo "")];
        _c lbSetData [_i, _id];
        _c lbSetTooltip [_i, _c lbText _i];
    } forEach _list;
    if (_list isEqualTo []) then { private _i = _c lbAdd ("MemNone" call _loc); _c lbSetData [_i, ""]; };
    _c lbSetCurSel 0;
    ["keptSel"] call comspec_sse_fnc_editorAi;
};
private _renderDocs = {
    private _c = 95208 call _ctrl;
    _st set ["busyDocs", true];
    lnbClear _c;
    private _keep = _st getOrDefault ["docSel", ""];
    private _sel = -1;
    {
        _x params ["_id", "_type", "_num", "", "", "_kind", ["_country", ""]];
        private _r = _c lnbAddRow [[format ["%1%2", [_type] call _docShort, ["", " · " + _country] select (_country isNotEqualTo "")], 5.9, 0.78] call _cell, [_num, 4.0, 0.78] call _cell, ("Badge_" + _kind) call _loc];
        _c lnbSetData [[_r, 0], _id];
        _c lnbSetTooltip [[_r, 0], format ["%1 · %2 · %3", [_type] call _docLabel, _country, _num]];
        // Badge Vrai / Faux : vert en règle, rouge faux, orange couverture ou alias.
        _c lnbSetColor [[_r, 2], if (_kind isEqualTo "forged") then { _cRed } else { [_cAmber, _cGreen] select (_kind in ["civil", "generated"]) }];
        if (_id isEqualTo _keep) then { _sel = _r; };
    } forEach (_st getOrDefault ["docs", []]);
    // Un papier est toujours choisi s'il y en a : l'aperçu montre un vrai papier, jamais une case vide.
    if (_sel < 0 && {(_st getOrDefault ["docs", []]) isNotEqualTo []}) then { _sel = 0; };
    _st set ["busyDocs", false];
    _c lnbSetCurSelRow _sel;
    private _none = (_st getOrDefault ["docs", []]) isEqualTo [];
    _st set ["docsNone", _none && {_st getOrDefault ["docsKnown", false]}];
    (95209 call _ctrl) ctrlSetStructuredText parseText (("DocsNone" call _loc) call _esc);
    [95209, _st getOrDefault ["docsNone", false]] call _showIf;
    if (_sel < 0) then { ["docSel"] call comspec_sse_fnc_editorAi; };
};
// Réglages posés d'après l'IA (une seule cochée) : profil, papiers, nature, comportement, pays ; camp inchangé.
private _loadRow = {
    params ["_net"];
    private _row = [_net] call _rowOf;
    if (_row isEqualTo []) exitWith {};
    _st set ["busy", true];
    if ((_row select 7) isNotEqualTo "") then { (95111 call _ctrl) ctrlSetText ""; ["profFilter"] call comspec_sse_fnc_editorAi; };
    [95101, _row select 7] call _pick;
    [95201, _row select 12] call _pick;
    [95104, _row select 13] call _pick;
    [95301, _row select 9] call _pick;
    [95401, "auto"] call _pick;
    [95107, _row param [15, "auto"]] call _pick;
    _st set ["busy", false];
    if (_st getOrDefault ["dirty", false]) then { _st set ["dirty", false]; if (((_st getOrDefault ["status", [""]]) select 0) isEqualTo ("Dirty" call _loc)) then { [""] call _note; }; };
    call _counts;
};
private _selDoc = { private _c = 95208 call _ctrl; private _r = lnbCurSelRow _c; if (_r < 0) exitWith { "" }; _c lnbData [_r, 0] };
private _docRow = { params ["_id"]; private _docs = _st getOrDefault ["docs", []]; private _i = _docs findIf { (_x select 0) isEqualTo _id }; if (_i < 0) then { [] } else { _docs select _i } };
// Aides qui suivent le choix (une phrase claire par valeur).
private _helps = {
    private _pid = 95101 call _data;
    private _p = (_st getOrDefault ["profiles", []]) select { (_x select 0) isEqualTo _pid };
    (95102 call _ctrl) ctrlSetStructuredText parseText ([("SrcM_random" call _loc), format ["SrcM_profile" call _loc, [((_p param [0, []]) param [1, _pid])] call _esc]] select (_pid isNotEqualTo ""));
    (95105 call _ctrl) ctrlSetStructuredText parseText ((("KindM_" + (95104 call _data)) call _loc) call _esc);
    (95302 call _ctrl) ctrlSetStructuredText parseText ((("BehM_" + (95301 call _data)) call _loc) call _esc);
    private _sd = 95401 call _data;
    private _row = [_st getOrDefault ["cur", ""]] call _rowOf;
    private _now = if (_row isEqualTo []) then { "-" } else { ("Side_" + (_row select 4)) call _loc };
    (95402 call _ctrl) ctrlSetStructuredText parseText ((if (_sd isEqualTo "auto") then { format ["SideM_auto" call _loc, _now] } else { ("SideM_" + _sd) call _loc }) call _esc);
};
private _apply = {
    params ["_group", "_keep"];
    private _units = call _checkedUnits;
    if (_units isEqualTo []) exitWith { ["NeedCheck" call _loc, "warn"] call _note; };
    if (_group) then {
        private _members = [];
        { { _members pushBackUnique _x; } forEach (units group _x); } forEach _units;
        _units = _members select { !isPlayer _x && {alive _x} };
    };
    [format ["Applying" call _loc, count _units]] call _note;
    ["apply", [_units, call _spec, _keep]] call _send;
};

// Échap (gestionnaire posé par « load ») : ferme le volet ouvert et garde la fenêtre ; vrai si un volet a été fermé.
if (_op isEqualTo "escape") exitWith {
    if (isNull _d || {(_st getOrDefault ["volet", ""]) isEqualTo ""}) exitWith { false };
    [""] call _setVolet;
    true
};
// Audit de l'état affiché (autotest, visite guidée) : [« ok », « owners », « orphans », « overlaps », « clipped », « volets »].
if (_op isEqualTo "audit") exitWith { if (isNull _d) then { createHashMapFromArray [["ok", false]] } else { call _audit } };

switch (_op) do {
    case "page": {
        // Page affichée ou quittée (fn_editor « _page ») : tout masquer en partant ; le bouton « ? » global suit la page.
        if (isNull _d) exitWith {};
        _st set ["page", _arg isEqualTo true];
        {
            if ((_x getVariable ["comspec_sse_guide_topic", ""]) isNotEqualTo "") then { _x setVariable ["comspec_sse_guide_topic", ["Editor", "AiZone"] select (_arg isEqualTo true)]; };
        } forEach (allControls _d);
        // Volet fermé en changeant de page ; en entrant, tout reste caché jusqu'à « load » (qui finit par _layout).
        _st set ["volet", ""];
        [[]] call _showOnly;
        if (_arg isEqualTo true) exitWith {};
        ["clear", [95503 call _ctrl]] call comspec_sse_fnc_editorAiCard;
    };
    case "load": {
        { _st set [_x, []]; } forEach ["checked", "rows", "docs", "profiles", "scanArgs"];
        { _st set [_x, false]; } forEach ["scanned", "docsKnown", "busy", "busyDocs", "dirty", "refreshing"];
        _st set ["page", true];
        _st set ["empty", true];
        _st set ["pvEmpty", true];
        _st set ["docsNone", false];
        _st set ["cur", ""];
        _st set ["sheetCur", "-"];
        _st set ["docSel", ""];
        _st set ["docCache", createHashMap];
        _st set ["face", "recto"];
        _st set ["tab", "id"];
        _st set ["sort", ["name", true]];
        _st set ["status", ["", "info"]];
        // État initial : aucun volet ouvert, puis le registre décide de ce qui est affiché (cadre, liste en attente, onglet
        // Identité, aperçu, barre du bas). Même si la suite échouait, rien d'autre ne resterait affiché par-dessus.
        _st set ["volet", ""];
        call _layout;
        // Échap ferme le volet ouvert (sans fermer la fenêtre) ; posé une fois par fenêtre.
        if (isNil { _d getVariable "comspec_sse_azEsc" }) then {
            _d setVariable ["comspec_sse_azEsc", _d displayAddEventHandler ["KeyDown", { params ["", "_key"]; (_key isEqualTo 1) && {["escape"] call comspec_sse_fnc_editorAi} }]];
        };
        _st set ["busy", true];
        [95005, [], "", [["25", "25 m"], ["50", "50 m"], ["100", "100 m"], ["250", "250 m"], ["500", "500 m"]]] call _fill;
        (95005 call _ctrl) lbSetCurSel 1;
        [95703, ["civ", "west", "east", "guer"], "Side_", [["", "FSide_All" call _loc]]] call _fill;
        [95705, _roles, "Role_", [["", "FRole_All" call _loc]]] call _fill;
        [95707, _states, "State_", [["", "FState_All" call _loc]]] call _fill;
        [95201, _papers, "Papers_"] call _fill;
        [95104, _kinds, "Kind_"] call _fill;
        [95301, _behav, "Beh_"] call _fill;
        [95401, _sides, "Side_"] call _fill;
        [95212, _docTypes, "Doc_"] call _fill;
        [95107, call _countries, "", [["auto", "CtryAuto" call _loc]]] call _fill;
        [95101, [], "", [["", "SrcRandom" call _loc]]] call _fill;
        // Info-bulle par valeur : la phrase complète (les libellés courts restent lisibles dans la liste).
        { _x params ["_idc", "_pre"]; private _c = _idc call _ctrl; for "_i" from 0 to ((lbSize _c) - 1) do { private _k = _pre + (_c lbData _i); if (isLocalized ("STR_COMSPEC_SSE_AZ_" + _k)) then { _c lbSetTooltip [_i, _k call _loc]; }; }; } forEach [[95104, "KindM_"], [95301, "BehM_"]];
        _st set ["busy", false];
        (95017 call _ctrl) lnbSetColumnsPos [0, 0.0667, 0.5067, 0.7467, 0.88];
        (95208 call _ctrl) lnbSetColumnsPos [0.005, 0.4338, 0.7279];
        { (_x call _ctrl) ctrlSetText ""; } forEach [95009, 95111, 94003];
        (95110 call _ctrl) ctrlSetStructuredText parseText (("ProfNone" call _loc) call _esc);
        [[]] call _keptFill;
        { (_x call _ctrl) ctrlEnable false; } forEach [95210, 95211, 95213];
        // Libellés posés et ajustés (traduction comprise) une fois pour toutes.
        [95001, 95004, 95006, 95023, 95024, 95030, 95032, 95033, 95034, 95035, 95100, 95103, 95106, 95200, 95203, 95204, 95206, 95207,
            95210, 95213, 95300, 95303, 95400, 95500, 95505, 95506, 95602, 95603, 95702, 95704, 95706, 95708, 95709, 95802, 95804, 95805, 95807, 95808, 95809] call _refit;
        // Légende des icônes de la liste (mêmes couleurs que les lignes).
        private _img = { params ["_pic", "_col"]; format ["<img image='%1' color='%2'/>", _pic, _col] };
        (95026 call _ctrl) ctrlSetStructuredText parseText format ["Legend" call _loc, [_icoPaper, _hGreen] call _img, [_icoPaper, _hRed] call _img, [_icoPaper, _hAmber] call _img, [_icoPaper, _hMuted] call _img, [_icoProf, _hGreen] call _img];
        call _helps;
        call _render;
        ["sheet"] call comspec_sse_fnc_editorAi;
        private _ctx = (uiNamespace getVariable ["comspec_sse_editorState", createHashMap]) getOrDefault ["ctx", [[], objNull]];
        private _o = _ctx param [1, objNull];
        // Module posé sur une IA : tout son groupe est listé. Sinon, sélection du Zeus, puis les IA autour.
        private _sel = (curatorSelected param [0, []]) select { _x isKindOf "CAManBase" && {!isPlayer _x} };
        if (!isNull _o && {_o isKindOf "CAManBase"}) then { _sel = units group _o; };
        // Liste donnée par l'appelant (fn_aiConfigOpen) : elle prime, une seule fois.
        private _given = uiNamespace getVariable ["comspec_sse_editorAiUnits", []];
        uiNamespace setVariable ["comspec_sse_editorAiUnits", []];
        if (_given isNotEqualTo []) then { _sel = _given; };
        if (_sel isNotEqualTo []) then { ["scanUnits", _sel] call comspec_sse_fnc_editorAi; } else { ["scan"] call comspec_sse_fnc_editorAi; };
        // Fin de la construction : le registre repasse (aucun contrôle hors de l'état affiché).
        call _layout;
        // Visite guidée : une fois par profil, seulement sur une fenêtre saine (voir « coach »).
        ["coach", false] call comspec_sse_fnc_editorAi;
    };
    case "layout": { call _layout; };
    case "tab": {
        if !(_arg in ["id", "docs", "beh", "side"]) exitWith {};
        _st set ["tab", _arg];
        call _layout;
    };
    case "scan": {
        private _args = [call _ctxPos, parseNumber (95005 call _data), []];
        _st set ["scanArgs", _args];
        ["scan", _args] call _send;
    };
    case "scanZeus": {
        private _sel = (curatorSelected param [0, []]) select { _x isKindOf "CAManBase" && {!isPlayer _x} };
        if (_sel isEqualTo []) exitWith { ["NoZeusSel" call _loc, "warn"] call _note; };
        ["scanUnits", _sel] call comspec_sse_fnc_editorAi;
    };
    case "scanUnits": {
        private _args = [[], 0, _arg select { !isNull _x }];
        _st set ["scanArgs", _args];
        ["scan", _args] call _send;
    };
    case "rescan": {
        private _args = _st getOrDefault ["scanArgs", []];
        if (_args isEqualTo []) exitWith { ["scan"] call comspec_sse_fnc_editorAi; };
        if ((_args select 2) isNotEqualTo []) then { _args set [2, (_args select 2) select { !isNull _x && {alive _x} }]; };
        ["scan", _args] call _send;
    };
    case "filter": { if !(_st getOrDefault ["busy", false]) then { call _render; }; };
    case "filters": { [["filt", ""] select ((_st getOrDefault ["volet", ""]) isEqualTo "filt")] call _setVolet; };
    case "voletClose": { [""] call _setVolet; };
    case "filtersClear": {
        _st set ["busy", true];
        { (_x call _ctrl) lbSetCurSel 0; } forEach [95703, 95705, 95707];
        (95009 call _ctrl) ctrlSetText "";
        _st set ["busy", false];
        _st set ["volet", ""];
        call _render;
    };
    case "sort": {
        (_st getOrDefault ["sort", ["name", true]]) params ["_col", "_asc"];
        _st set ["sort", [_arg, [true, !_asc] select (_arg isEqualTo _col)]];
        call _render;
    };
    case "click": {
        if (_st getOrDefault ["busy", false]) exitWith {};
        private _lb = 95017 call _ctrl;
        private _r = lnbCurSelRow _lb;
        if (_r < 0) exitWith {};
        private _net = _lb lnbData [_r, 0];
        private _checked = _st getOrDefault ["checked", []];
        private _on = !(_net in _checked);
        if (_on) then { _checked pushBack _net; } else { _checked = _checked - [_net]; };
        _st set ["checked", _checked];
        _st set ["cur", _net];
        // Ligne relâchée tout de suite : un second clic sur la même ligne doit aussi déclencher l'évènement.
        _st set ["busy", true];
        [_lb, _r, _on] call _setRowMark;
        _lb lnbSetCurSelRow -1;
        _st set ["busy", false];
        call _counts;
        if ((count _checked) <= 1) then { [_net] call _loadRow; };
        ["sheet"] call comspec_sse_fnc_editorAi;
    };
    case "checkAll": {
        private _lb = 95017 call _ctrl;
        private _nets = [];
        for "_i" from 0 to (((lnbSize _lb) select 0) - 1) do { _nets pushBack (_lb lnbData [_i, 0]); };
        _st set ["checked", _nets];
        if !((_st getOrDefault ["cur", ""]) in _nets) then { _st set ["cur", _nets param [0, ""]]; };
        call _render;
        if ((count _nets) isEqualTo 1) then { [_nets select 0] call _loadRow; };
        ["sheet"] call comspec_sse_fnc_editorAi;
    };
    case "checkNone": {
        _st set ["checked", []];
        call _render;
        ["sheet"] call comspec_sse_fnc_editorAi;
    };
    case "sheet": {
        // En-tête du milieu (qui on règle) et papiers de l'IA courante.
        if (isNull _d) exitWith {};
        private _cur = _st getOrDefault ["cur", ""];
        private _checked = _st getOrDefault ["checked", []];
        private _row = [_cur] call _rowOf;
        private _has = _row isNotEqualTo [];
        (95213 call _ctrl) ctrlEnable _has;
        // Même IA (liste relue après Appliquer ou un papier changé) : ses papiers et le papier choisi restent affichés.
        if (_cur isNotEqualTo (_st getOrDefault ["sheetCur", ""])) then {
            _st set ["docs", []];
            _st set ["docsKnown", false];
            _st set ["docSel", ""];
        };
        _st set ["sheetCur", _cur];
        private _name = 95030 call _ctrl;
        private _sub = 95031 call _ctrl;
        if ((count _checked) > 1) then {
            [_name, format ["SheetMulti" call _loc, count _checked]] call _setFit;
            [_sub, format ["SheetMultiSub" call _loc, count _checked]] call _setFit;
        } else {
            if (!_has) then {
                [_name, "SheetNone" call _loc] call _setFit;
                [_sub, "SheetNoneSub" call _loc] call _setFit;
            } else {
                _row params ["", "_given", "_true", "_truth", "_side", "_role", "", "", "", "", "_grp"];
                [_name, [_given, format ["(%1)", "Truth_pending" call _loc]] select (_given isEqualTo "")] call _setFit;
                [_sub, format ["SheetSub" call _loc, [_true, "?"] select (_true isEqualTo ""), ("Truth_" + _truth) call _loc, _grp]] call _setFit;
            };
        };
        call _renderDocs;
        call _helps;
        if (_has) then { private _u = objectFromNetId _cur; if (!isNull _u) then { ["docs", [_u]] call _send; }; };
        ["preview"] call comspec_sse_fnc_editorAi;
    };
    case "changed": {
        // Choix fait par le Zeus dans un onglet : rien n'est envoyé, l'état le dit.
        if (_st getOrDefault ["busy", false]) exitWith {};
        _st set ["dirty", true];
        call _helps;
        ["Dirty" call _loc, "warn"] call _note;
        call _counts;
        ["preview"] call comspec_sse_fnc_editorAi;
    };
    case "fold": { [["ath", ""] select ((_st getOrDefault ["volet", ""]) isEqualTo "ath")] call _setVolet; };
    case "profFilter": {
        private _c = 95101 call _ctrl;
        private _keep = 95101 call _data;
        private _q = toLower (trim (ctrlText (95111 call _ctrl)));
        private _was = _st getOrDefault ["busy", false];
        _st set ["busy", true];
        lbClear _c;
        private _i0 = _c lbAdd ("SrcRandom" call _loc);
        _c lbSetData [_i0, ""];
        {
            _x params ["_id", "_ref", "_label", "_name"];
            private _t = format ["%1 · %2%3", _ref, _label, ["", format [" (%1)", _name]] select (_name isNotEqualTo "")];
            if (_q isEqualTo "" || {((toLower _t) find _q) >= 0} || {_id isEqualTo _keep}) then { private _i = _c lbAdd (format ["SrcProfile" call _loc, _t]); _c lbSetData [_i, _id]; _c lbSetTooltip [_i, _t]; };
        } forEach (_st getOrDefault ["profiles", []]);
        _c lbSetCurSel 0;
        [95101, _keep] call _pick;
        _st set ["busy", _was];
        [95114, _q isEqualTo ""] call _showIf;
    };
    case "refresh": {
        // Le serveur recharge le paquet d'identité (profils et mémoire d'Athena), puis la liste est relue.
        // La ligne d'état ne reste pas sur « Actualisation demandée… » : la liste relue après la seconde relecture (12 s) la
        // remplace par « Liste relue », et sans réponse au bout de 25 s, « Athena n'a pas répondu ».
        ["reload"] call _sendId;
        ["Refreshing" call _loc] call _note;
        _st set ["refreshing", true];
        _st set ["refreshUntil", diag_tickTime + 11];
        { [{ if (!isNull (uiNamespace getVariable ["comspec_sse_editorDisplay", displayNull])) then { ["rescan"] call comspec_sse_fnc_editorAi; }; }, [], _x] call CBA_fnc_waitAndExecute; } forEach [4, 12];
        [{ if (!isNull (uiNamespace getVariable ["comspec_sse_editorDisplay", displayNull])) then { ["refreshLate"] call comspec_sse_fnc_editorAi; }; }, [], 25] call CBA_fnc_waitAndExecute;
    };
    case "refreshLate": {
        if !(_st getOrDefault ["refreshing", false]) exitWith {};
        _st set ["refreshing", false];
        ["RefreshLate" call _loc, "warn"] call _note;
    };
    case "docSel": {
        if (_st getOrDefault ["busyDocs", false]) exitWith {};
        private _id = call _selDoc;
        if (_id isNotEqualTo (_st getOrDefault ["docSel", ""])) then { _st set ["face", "recto"]; };
        _st set ["docSel", _id];
        private _on = _id isNotEqualTo "";
        { (_x call _ctrl) ctrlEnable _on; } forEach [95210, 95211];
        private _kind = ([_id] call _docRow) param [5, ""];
        [95211 call _ctrl, [("MakeFalse" call _loc), ("MakeTrue" call _loc)] select (_kind isEqualTo "forged")] call _setFit;
        (95211 call _ctrl) ctrlSetTooltip ([("DocFalse_D" call _loc), ("DocTrue_D" call _loc)] select (_kind isEqualTo "forged"));
        // Papier imprimé demandé au serveur une fois (jamais la vérité), gardé pour cette fenêtre.
        if (_on && {!(_id in (_st getOrDefault ["docCache", createHashMap]))}) then {
            private _u = objectFromNetId (_st getOrDefault ["cur", ""]);
            if (!isNull _u) then { ["docView", [_u, _id]] call _send; };
        };
        ["preview"] call comspec_sse_fnc_editorAi;
    };
    case "docOpen": {
        private _id = call _selDoc;
        if (_id isEqualTo "") exitWith { ["PickDoc" call _loc, "warn"] call _note; };
        // Fenêtre « Papiers de l'IA » (identity) : ce papier tel qu'un joueur le voit, l'analyse du Zeus à côté.
        private _u = objectFromNetId (_st getOrDefault ["cur", ""]);
        if (!isNull _u && {!isNil "comspec_sse_fnc_idZeusPapers"}) exitWith { ["open", [_u, _id]] call comspec_sse_fnc_idZeusPapers; };
        // Sans elle : plein écran, la visionneuse du joueur (cette fenêtre se ferme, comme avant).
        ["docOpen", [_id]] call _sendId;
    };
    case "docMark";
    case "docTrue";
    case "docFalse": {
        private _u = objectFromNetId (_st getOrDefault ["cur", ""]);
        private _id = call _selDoc;
        if (isNull _u || {_id isEqualTo ""}) exitWith { ["PickDoc" call _loc, "warn"] call _note; };
        private _kind = switch (_op) do {
            case "docTrue": { "civil" };
            case "docFalse": { "forged" };
            default { ["forged", "civil"] select ((([_id] call _docRow) param [5, ""]) isEqualTo "forged") };
        };
        (_st getOrDefault ["docCache", createHashMap]) deleteAt _id;
        ["docMark", [_u, _id, _kind]] call _send;
        [(["DocMadeFalse", "DocMadeTrue"] select (_kind isEqualTo "civil")) call _loc, "ok"] call _note;
        ["rescan"] call comspec_sse_fnc_editorAi;
    };
    case "docGive": {
        private _u = objectFromNetId (_st getOrDefault ["cur", ""]);
        if (isNull _u) exitWith { ["NeedCheck" call _loc, "warn"] call _note; };
        ["docAdd", [_u, 95212 call _data]] call _send;
        [format ["DocGiven" call _loc, (95212 call _ctrl) lbText (lbCurSel (95212 call _ctrl))], "ok"] call _note;
        ["rescan"] call comspec_sse_fnc_editorAi;
    };
    case "flip": {
        _st set ["face", ["recto", "verso"] select ((_st getOrDefault ["face", "recto"]) isEqualTo "recto")];
        ["preview"] call comspec_sse_fnc_editorAi;
    };
    case "memory": { [["mem", ""] select ((_st getOrDefault ["volet", ""]) isEqualTo "mem")] call _setVolet; };
    case "keptSel": {
        private _on = (95806 call _data) isNotEqualTo "";
        { (_x call _ctrl) ctrlEnable _on; } forEach [95807, 95808];
    };
    case "keptReload": {
        private _id = 95806 call _data;
        if (_id isEqualTo "") exitWith {};
        ["restoreOne", [_id]] call _send;
    };
    case "keptForget": {
        private _id = 95806 call _data;
        if (_id isEqualTo "") exitWith {};
        ["unkeep", [_id]] call _send;
    };
    case "preview": {
        // « Ce que verra le joueur » : le papier choisi (vrai rendu), sinon une phrase ; puis ce qu'il dira au contrôle.
        if (isNull _d) exitWith {};
        private _grp = 95503 call _ctrl;
        private _row = [_st getOrDefault ["cur", ""]] call _rowOf;
        private _id = _st getOrDefault ["docSel", ""];
        private _doc = (_st getOrDefault ["docCache", createHashMap]) getOrDefault [_id, createHashMap];
        private _face = _st getOrDefault ["face", "recto"];
        private _drawn = false;
        if ((count _doc) > 0) then { _drawn = (["draw", [_grp, _doc, _face]] call comspec_sse_fnc_editorAiCard) isEqualTo true; } else { ["clear", [_grp]] call comspec_sse_fnc_editorAiCard; };
        private _faces = if (_drawn) then { ["sides", [_doc]] call comspec_sse_fnc_editorAiCard } else { 1 };
        _st set ["canFlip", _drawn && {_faces > 1}];
        (95505 call _ctrl) ctrlEnable (_st get "canFlip");
        private _ph = switch (true) do {
            case (_drawn): { "" };
            case (_row isEqualTo []): { "PvNoAi" call _loc };
            case (_id isNotEqualTo ""): { "PvLoading" call _loc };
            case (_st getOrDefault ["docsKnown", false]): { "PvNoDoc" call _loc };
            default { "PvLoading" call _loc };
        };
        (95504 call _ctrl) ctrlSetStructuredText parseText ([_ph] call _esc);
        _st set ["pvEmpty", _ph isNotEqualTo ""];
        [95504, _ph isNotEqualTo ""] call _showIf;
        private _cap = if (_drawn) then {
            format ["%1 · %2 · %3", [_doc getOrDefault ["type", ""]] call _docLabel, _doc getOrDefault ["countryName", _doc getOrDefault ["country", ""]], ("Face_" + _face) call _loc]
        } else { "PvCaptionNone" call _loc };
        [95501 call _ctrl, _cap] call _setFit;
        // Ce qu'il dira au contrôle (et, pour le Zeus seul, la vérité).
        private _t = 95508 call _ctrl;
        if (_row isEqualTo []) exitWith { _t ctrlSetStructuredText parseText format ["<t color='%1'>%2</t>", _hMuted, ["SumNone" call _loc] call _esc]; };
        _row params ["", "_given", "_true", "_truth", "", "", "", "_rowPid", "", "_rowBeh", "", "", "_rowCarry", "_rowKind"];
        private _pid = 95101 call _data;
        private _p = (_st getOrDefault ["profiles", []]) select { (_x select 0) isEqualTo _pid };
        private _who = _given;
        if (_who isEqualTo "" && {_p isNotEqualTo []}) then { _who = (_p select 0) select 3; };
        private _lines = [];
        _lines pushBack (if (_who isEqualTo "") then { format ["SumPending" call _loc, [_true] call _esc] } else { format ["SumName" call _loc, format ["<t font='RobotoCondensedBold'>%1</t>", [_who] call _esc]] });
        private _docs = (_st getOrDefault ["docs", []]) apply { _x params ["", "_type", "_num"]; format ["%1 %2", [_type] call _docLabel, _num] };
        _lines pushBack format ["SumPapers" call _loc, [([_docs joinString ", ", "SumPapersNone" call _loc] select (_docs isEqualTo []))] call _esc];
        _lines pushBack format ["SumBeh" call _loc, [("Beh_" + (95301 call _data)) call _loc] call _esc];
        private _zeus = if (_truth isEqualTo "true") then { "PrevSame" call _loc } else { format ["PrevTrue" call _loc, [_true, "?"] select (_true isEqualTo ""), ("Truth_" + _truth) call _loc] };
        _lines pushBack format ["<t color='%1'>%2</t>", _hAmber, [format ["SumZeus" call _loc, _zeus]] call _esc];
        // Réglages différents de ce que l'IA a : ce qui changera à « Appliquer ».
        private _carry = 95201 call _data;
        private _kind = 95104 call _data;
        if (_pid isNotEqualTo _rowPid || {_carry isNotEqualTo _rowCarry} || {_kind isNotEqualTo _rowKind} || {(95301 call _data) isNotEqualTo _rowBeh} || {(95401 call _data) isNotEqualTo "auto"}) then {
            private _what = if (_pid isNotEqualTo "") then { "PrevPapersProfile" call _loc } else { format ["%1 · %2", [_carry] call _papersLabel, ("Kind_" + _kind) call _loc] };
            _lines pushBack format ["<t color='%1'>%2</t>", _hGreen, [format ["SumAfter" call _loc, _what]] call _esc];
        };
        _t ctrlSetStructuredText parseText (_lines joinString "<br/>");
        // Hauteur réelle : le groupe défile s'il dépasse (aucun texte coupé).
        private _w = (ctrlPosition _t) select 2;
        private _grpH = (ctrlPosition (95507 call _ctrl)) select 3;
        _t ctrlSetPosition [0, 0, _w, _grpH];
        _t ctrlCommit 0;
        _t ctrlSetPosition [0, 0, _w, (ctrlTextHeight _t) max _grpH];
        _t ctrlCommit 0;
    };
    case "applyChecked": { [false, false] call _apply; };
    case "applyGroup": { [true, false] call _apply; };
    case "keepChecked": { [false, true] call _apply; };
    case "answers": {
        private _units = call _checkedUnits;
        if (_units isEqualTo []) exitWith { ["NeedCheck" call _loc, "warn"] call _note; };
        if (isNil "comspec_sse_fnc_iarpAnswersUi") exitWith {};
        ["open", ["units", _units, _d]] call comspec_sse_fnc_iarpAnswersUi;
    };
    case "coach": {
        // Visite guidée, ancrée sur la liste, les onglets, puis les boutons du bas. _arg : true = rouverte par « ? ».
        if (isNull _d) exitWith {};
        _st set ["volet", ""];
        call _layout;
        // Jamais par-dessus une fenêtre cassée : si l'audit trouve un contrôle hors de son propriétaire, un chevauchement
        // ou plus d'un volet, pas de visite (journal seulement ; « ? » la rouvrira une fois la fenêtre saine). Un texte
        // raccourci (« … ») n'est pas une fenêtre cassée.
        private _au = call _audit;
        if ((_au get "orphans") isNotEqualTo [] || {(_au get "overlaps") isNotEqualTo []} || {(count (_au get "volets")) > 1}) exitWith {
            ["stop"] call comspec_sse_fnc_editorCoach;
            diag_log format ["[COMSPEC SSE][IA DE LA ZONE] visite guidée non montrée, fenêtre à vérifier : %1", [_au get "orphans", _au get "overlaps", _au get "clipped", _au get "volets"]];
        };
        ["start", [[
            ["Co1T" call _loc, "Co1B" call _loc, [0.3, 3.25, 14.6, 17.7]],
            ["Co2T" call _loc, "Co2B" call _loc, [15.2, 2.05, 14.0, 20.85]],
            ["Co3T" call _loc, "Co3B" call _loc, [14.8, 23.45, 24.9, 1.3]]
        ], "comspec_sse_aiZoneCoach", _arg isNotEqualTo false]] call comspec_sse_fnc_editorCoach;
    };
    case "help": {
        // Chapitre du manuel (CfgHints COMSPEC_SSE › AiZone), posé sur cette fenêtre.
        private _cfg = configFile >> "CfgHints" >> "COMSPEC_SSE" >> "AiZone";
        if (!isClass _cfg || {isNull _d}) exitWith { if (!isNil "comspec_sse_fnc_openGuide") then { ["AiZone"] call comspec_sse_fnc_openGuide; }; };
        if (!isNil "comspec_sse_fnc_openGuide") then { ["AiZone", _d] call comspec_sse_fnc_openGuide; };
    };
    // --- Réponses du serveur (fn_editorAiServer ; « docs » peut aussi venir de l'addon identity, sans le pays).
    case "aiReply": {
        if (isNull _d) exitWith {};
        _arg params [["_kind", ""], ["_p1", []], ["_p2", []], ["_p3", []], ["_p4", []]];
        switch (_kind) do {
            case "rows": {
                private _rows = _p1 select { _x isEqualType [] && {(count _x) >= 15} };
                _st set ["rows", _rows];
                _st set ["scanned", true];
                private _nets = _rows apply { _x select 0 };
                private _checked = (_st getOrDefault ["checked", []]) select { _x in _nets };
                // Une seule IA (ouverte depuis le centre Zeus sur elle) : cochée d'office.
                if (_checked isEqualTo [] && {(count _nets) isEqualTo 1}) then { _checked = +_nets; };
                _st set ["checked", _checked];
                if !((_st getOrDefault ["cur", ""]) in _nets) then { _st set ["cur", _checked param [0, _nets param [0, ""]]]; };
                _st set ["profiles", _p2];
                ["profFilter"] call comspec_sse_fnc_editorAi;
                _p3 params [["_n", 0], ["_at", ""]];
                (95110 call _ctrl) ctrlSetStructuredText parseText ((if (_n > 0) then { if (_at isEqualTo "") then { format ["ProfStatusNoDate" call _loc, _n] } else { format ["ProfStatus" call _loc, _n, _at] } } else { "ProfNone" call _loc }) call _esc);
                [_p4] call _keptFill;
                call _render;
                // Actualisation demandée : la relecture qui suit le rechargement du paquet la clôt dans la ligne d'état.
                if ((_st getOrDefault ["refreshing", false]) && {diag_tickTime >= (_st getOrDefault ["refreshUntil", 0])}) then {
                    _st set ["refreshing", false];
                    [format ["RefreshDone" call _loc, count _rows], "ok"] call _note;
                };
                // Réglages en cours gardés si le Zeus les a modifiés (rien n'est perdu au rafraîchissement de la liste).
                if ((count _checked) <= 1 && {!(_st getOrDefault ["dirty", false])}) then { [_st get "cur"] call _loadRow; };
                ["sheet"] call comspec_sse_fnc_editorAi;
            };
            case "status": {
                _p1 params [["_n", 0], ["_at", ""]];
                (95110 call _ctrl) ctrlSetStructuredText parseText ((if (_n > 0) then { if (_at isEqualTo "") then { format ["ProfStatusNoDate" call _loc, _n] } else { format ["ProfStatus" call _loc, _n, _at] } } else { "ProfNone" call _loc }) call _esc);
            };
            case "docs": {
                if (_p1 isEqualType "" && {_p1 isEqualTo (_st getOrDefault ["cur", ""])}) then {
                    _st set ["docs", _p2];
                    _st set ["docsKnown", true];
                    call _renderDocs;
                    ["preview"] call comspec_sse_fnc_editorAi;
                };
            };
            case "docView": {
                if !(_p1 isEqualType "" && {_p2 isEqualType createHashMap}) exitWith {};
                (_st getOrDefault ["docCache", createHashMap]) set [_p1, _p2];
                if (_p1 isEqualTo (_st getOrDefault ["docSel", ""])) then { ["preview"] call comspec_sse_fnc_editorAi; };
            };
            case "applied": {
                private _t = format ["Applied" call _loc, _p1];
                if (_p2 isEqualType 0 && {_p2 > 0}) then { _t = _t + " " + format ["KeptOk" call _loc, _p2]; };
                _st set ["dirty", false];
                call _counts;
                // Papiers refaits : l'aperçu les redemande.
                _st set ["docCache", createHashMap];
                [_t, "ok"] call _note;
                ["rescan"] call comspec_sse_fnc_editorAi;
            };
            case "note": {
                if !(_p1 isEqualType "") exitWith {};
                if !(_p2 isEqualType []) then { _p2 = []; };
                private _k = toUpper ([_p3, "INFO"] select !(_p3 isEqualType ""));
                [format ([_p1 call _loc] + _p2), ["info", "ok", "warn"] select ((["INFO", "SUCCESS", "WARNING"] find _k) max 0)] call _note;
                if (_p1 in ["KeptAthenaOk", "KeptAthenaKo", "Forgot"]) then { ["rescan"] call comspec_sse_fnc_editorAi; };
            };
            case "kept": { [_p1] call _keptFill; };
            case "athenaPost": {
                // Le serveur n'a pas la liaison : la copie sur Athena part de ce poste (DLL du Zeus), contexte planifié.
                _p1 spawn {
                    private _ok = 0;
                    {
                        _x params ["_payload", "_lid"];
                        private _raw = ["post", _payload] call comspec_sse_fnc_editorAi;
                        if ((_raw select [0, 3]) isEqualTo "OK|") then {
                            _ok = _ok + 1;
                            if (_lid isNotEqualTo "") then { ["athenaDone", [_lid, (_raw splitString "|") param [1, ""]]] call comspec_sse_fnc_editorAi; };
                        };
                    } forEach _this;
                    ["aiReply", ["note", ["KeptAthenaKo", "KeptAthenaOk"] select (_ok isEqualTo (count _this)), [], ["WARNING", "SUCCESS"] select (_ok isEqualTo (count _this))]] call comspec_sse_fnc_editorAi;
                };
            };
        };
    };
    case "athenaDone": { ["athenaDone", _arg] call _send; };
    // --- Athena depuis ce poste (contexte planifié) : élément gardé de l'éditeur (fn_editor « placed »), copie d'une IA gardée.
    case "keepItem": {
        if (!canSuspend) exitWith { _this spawn comspec_sse_fnc_editorAi; };
        _arg params [["_it", createHashMap], ["_where", []]];
        private _pairs = [];
        { if (_x in _it) then { _pairs pushBack [_x, _it get _x]; }; } forEach ["id", "type", "title", "body", "marking", "paper", "nature", "delivery"];
        ["post", createHashMapFromArray [["kind", "ai_keep"], ["place", createHashMapFromArray [
            ["kind", "item"], ["x", _where select 0], ["y", _where select 1], ["label", _it get "title"], ["config", createHashMapFromArray [["item", _pairs]]]
        ]]]] call comspec_sse_fnc_editorAi;
    };
};
true
