/*
    Éditeur SSE (client) : formulaire, aperçu, bibliothèque, guidage. Aucun script par image : tout part d'un clic ou d'une
    frappe dans un champ (dialogs/editor.hpp). Placement : fn_editorPlace, puis fn_editorServer.
    [_op, _arg] call comspec_sse_fnc_editor
      open [pos, objet, page]   ouvre l'éditeur (module Zeus, menu Eden) ; page « ai » : IA de la zone        load display / unload / close
      aiPage              page « IA de la zone » (fn_editorAi)
      type i · delivery i · model i · preview · save · publish
      toggleLibrary · filter i · libFill · libSelect · libEdit · libDuplicate · libPlace · libArchive · libLink
      guideNext · placed [id, résumé] · receive [titre, texte] · found [id, joueur, quand] · status [texte, en ligne] · libRefresh
    Bibliothèque : profileNamespace « comspec_sse_editorLib » (liste de HashMap), suivie sur Athena par fn_editorLibrary.
*/
params [["_op", "", [""]], ["_arg", 0]];
if (!hasInterface) exitWith { false };
// Appel distant : du serveur seulement (receive, placed, found). Un autre poste n'écrit rien dans cette bibliothèque.
if (isRemoteExecuted && {!isServer} && {remoteExecutedOwner isNotEqualTo 2}) exitWith { false };
disableSerialization;

private _types = ["intel", "identity", "paper", "case", "kit"];
private _typeLabels = ["Renseignement", "Identité", "Papier", "Dossier", "Kit"];
private _typeHints = ["Note, rapport, carnet trouvé", "Une personne et sa légende", "Carte, passeport, permis", "Suivi d'une cible ou d'un site", "Lot prêt pour une mission"];
private _headings = [
    ["Nouveau renseignement", "Un document que les joueurs trouvent ou reçoivent."],
    ["Nouvelle identité", "Une personne, vraie ou de couverture."],
    ["Nouveau papier", "Un document rattaché à une identité."],
    ["Nouveau dossier", "Devient le dossier actif de la mission."],
    ["Nouveau kit", "Identité et papiers pour une personne."]
];
// Modèle : [libellé, titre, contenu, nature (0 vraie, 1 couverture, 2 faux), papier ou lot]. Avant, nature et papier ne suivaient pas.
private _models = createHashMapFromArray [
    ["intel", [["Rapport de source", "Rapport de source", "Source :\nFiabilité :\n\nCe qui a été observé :\n\nÀ vérifier :"], ["Carnet manuscrit", "Carnet retrouvé", "Pages déchirées, écriture rapide.\n\n"], ["Ordre intercepté", "Ordre intercepté", "De :\nÀ :\n\nOrdre :"]]],
    ["identity", [["Résident", "Prénom Nom", "Résident de la région, sans lien avec les forces en présence.", 0], ["Couverture", "Prénom Nom", "Adresse :\nEmployeur :\nRaison du déplacement :", 1], ["Cible", "Prénom Nom", "Recherché pour :\nDernière position connue :", 0]]],
    ["paper", [["Papier civil", "Prénom Nom", "", 0], ["Papier de couverture", "Prénom Nom", "", 1], ["Faux grossier", "Prénom Nom", "", 2]]],
    ["case", [["Cible", "CIBLE-01", "Personne suivie :\nObjectif :"], ["Site", "SITE-01", "Site :\nCe qu'on y cherche :"], ["Réseau", "RESEAU-01", "Réseau suivi :\nPersonnes connues :"]]],
    ["kit", [["Infiltration", "Prénom Nom", "Identité de couverture et papiers.", 1, 0], ["Militaire", "Prénom Nom", "Carte militaire et carte d'identité.", 0, 1], ["Journaliste", "Prénom Nom", "Passeport et carte d'identité.", 0, 0]]]
];
private _deliveries = ["pickup", "search", "send"];
private _deliveryLabels = [["À ramasser", "Sur un objet choisi dans le monde", "Les joueurs le ramassent sur l'objet placé."], ["En fouille", "Sur une personne ou dans un véhicule", "Il apparaît lors d'une fouille."], ["Au téléphone", "À un groupe ou à un joueur", "Il arrive directement dans l'ATAK des destinataires."]];
// Identité, papier, kit : mêmes modes, mais ce sont des papiers. Le téléphone n'en reçoit que l'annonce.
private _deliveryPapers = [["Au sol", "Posés à l'endroit choisi", "Les papiers sont posés au sol à l'endroit choisi, ou remis à la personne visée."], ["Sur quelqu'un", "Une personne ou un véhicule à cliquer", "Les papiers sont remis à la personne visée, ou dans le véhicule visé."], ["Annonce", "Message au téléphone, aucun papier", "Un message au téléphone annonce ces papiers : aucun objet n'est posé."]];
private _kitSets = ["Carte et passeport", "Carte militaire et carte", "Carte et permis"];
private _markings = ["NON PROTÉGÉ", "DIFFUSION RESTREINTE", "CONFIDENTIEL"];
private _papers = [["id_card", "Carte d'identité"], ["passport", "Passeport"], ["driving_license", "Permis de conduire"], ["military_id", "Carte militaire"]];
private _natures = [["civil", "Vraie"], ["cover", "Couverture"], ["forged", "Faux"]];
// Papier d'un renseignement : automatique (nature, contexte, graine : fn_paperSkin), un des papiers, ou la feuille unie d'avant
// (contenu enregistré avant les papiers, sans clé « skin »).
private _skins = [["auto", "Automatique (selon la pièce)"]] + (if (isNil "comspec_sse_fnc_paperSkin") then { [] } else { ["list"] call comspec_sse_fnc_paperSkin }) + [["legacy", "Feuille unie (ancien rendu)"]];
private _skinCodes = _skins apply { _x select 0 };
private _dir = "\z\comspec_sse\addons\editor\data\";
private _st = uiNamespace getVariable ["comspec_sse_editorState", createHashMap];
private _d = uiNamespace getVariable ["comspec_sse_editorDisplay", displayNull];
private _newlines = { params ["_t"]; [_t, "\n", toString [13, 10]] call CBA_fnc_replace };
private _esc = { params ["_t"]; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };
private _library = {
    private _lib = profileNamespace getVariable ["comspec_sse_editorLib", []];
    if (_lib isEqualTo []) then {
        // Premier lancement : modèles réutilisables.
        {
            _x params ["_type", "_title", "_body"];
            _lib pushBack createHashMapFromArray [["id", format ["M%1", _forEachIndex]], ["type", _type], ["title", _title], ["body", [_body] call _newlines], ["marking", 0], ["paper", 0], ["skin", "auto"], ["nature", 0], ["delivery", 0], ["state", "Modèle"], ["op", "Réutilisable"], ["when", ""], ["version", 1], ["history", []], ["link", ""], ["found", []], ["dirty", false]];
        } forEach [["kit", "Kit infiltration, papiers et légende", "Identité de couverture et papiers."], ["intel", "Rapport de source", "Source :\nFiabilité :\n\nCe qui a été observé :\n\nÀ vérifier :"], ["case", "Dossier cible", "Personne suivie :\nObjectif :"]];
        profileNamespace setVariable ["comspec_sse_editorLib", _lib];
    };
    _lib
};
private _stamp = {
    systemTime params ["_y", "_m", "_dd", "_h", "_mi"];
    format ["%1%2/%3%4/%5 %6%7:%8%9", ["", "0"] select (_dd < 10), _dd, ["", "0"] select (_m < 10), _m, _y, ["", "0"] select (_h < 10), _h, ["", "0"] select (_mi < 10), _mi]
};
private _blank = { createHashMapFromArray [["id", ""], ["type", "intel"], ["title", ""], ["body", ""], ["marking", 0], ["paper", 0], ["skin", "auto"], ["nature", 0], ["delivery", 0], ["state", "Brouillon"], ["op", missionName], ["when", ""], ["version", 0], ["history", []], ["link", ""], ["found", []], ["dirty", true]] };
// Eden : pas d'unité jouée.
private _who = { if (isNull player) then { profileName } else { name player } };

private _editorIdcs = [94063, 94010, 94011, 94020, 94021, 94022, 94030, 94031, 94032, 94040, 94041, 94042, 94043, 94046, 94047, 94048, 94050, 94051, 94052, 94053, 94054, 94055, 94060, 94061, 94062];
{ private _b = _x; for "_i" from 0 to 4 do { _editorIdcs pushBack (_b + _i); }; } forEach [94100, 94110, 94120, 94130, 94140, 94200, 94210, 94220];
private _aiIdcs = [];
_editorIdcs append [94056, 94057, 94058];
for "_i" from 94500 to 94548 do { _aiIdcs pushBack _i; };
private _libIdcs = [94300, 94320, 94321, 94322, 94323, 94330, 94331, 94332, 94333, 94334, 94335, 94336, 94337, 94338, 94339, 94340, 94310, 94311, 94312, 94313, 94314, 94315, 94316, 94317];

// Valeurs du formulaire -> brouillon en cours.
private _read = {
    private _it = _st get "item";
    _it set ["title", ctrlText (_d displayCtrl 94041)];
    _it set ["body", ctrlText (_d displayCtrl 94047)];
    private _main = (lbCurSel (_d displayCtrl 94043)) max 0;
    if ((_it get "type") in ["paper", "kit"]) then { _it set ["paper", _main]; } else { _it set ["marking", _main]; };
    _it set ["nature", (lbCurSel (_d displayCtrl 94045)) max 0];
    if ((_it get "type") isEqualTo "intel") then { _it set ["skin", _skinCodes param [(lbCurSel (_d displayCtrl 94057)) max 0, "auto"]]; };
};
private _preview = {
    private _it = _st get "item";
    private _text = [[[_it get "body"] call _esc, toString [13], ""] call CBA_fnc_replace, toString [10], "<br/>"] call CBA_fnc_replace;
    private _type = _it get "type";
    private _stampText = switch (_type) do {
        case "paper": { toUpper ((_papers select (_it get "paper")) select 1) };
        case "kit": { toUpper (_kitSets param [_it get "paper", _kitSets select 0]) };
        case "intel": { _markings select (_it get "marking") };
        default { "" };
    };
    private _titleShown = _it get "title";
    // Aperçu vide : un exemple utile, en gris, plutôt qu'une page blanche.
    private _example = "";
    if ((trim _titleShown) isEqualTo "" && {(trim (_it get "body")) isEqualTo ""}) then {
        _example = (createHashMapFromArray [["intel", "Exemple : Rapport de source. Choisissez un modèle à gauche de « Partir de »."], ["identity", "Exemple : Alexandros Nikolaidis, résident de Charkia."], ["paper", "Exemple : carte d'identité d'Alexandros Nikolaidis."], ["case", "Exemple : CIBLE-01, dossier actif de la mission."], ["kit", "Exemple : Alexandros Nikolaidis, carte et passeport."]]) get _type;
    };
    private _ref = _it get "id";
    // Feuille de l'aperçu : le papier que verra le joueur (automatique : même calcul que la visionneuse, graine = numéro).
    private _skinPic = _d displayCtrl 94058;
    private _skin = _it getOrDefault ["skin", "legacy"];
    if (_type isEqualTo "intel" && {_skin isNotEqualTo "legacy"} && {!isNil "comspec_sse_fnc_paperSkin"}) then {
        if (_skin isEqualTo "auto") then {
            private _doc = createHashMapFromArray [["title", _it get "title"], ["summary", _it get "body"], ["paper", ""]];
            _skin = (["resolve", [createHashMapFromArray [["docs", [_doc]]], objNull, [_ref, "NOUVEAU"] select (_ref isEqualTo "")]] call comspec_sse_fnc_paperSkin) select 0;
        };
        _skinPic ctrlSetText (["texture", [_skin, false]] call comspec_sse_fnc_paperSkin);
        _skinPic ctrlShow (_skin isNotEqualTo "");
    } else {
        _skinPic ctrlShow false;
    };
    (_d displayCtrl 94054) ctrlSetStructuredText parseText format [
        "<t font='EtelkaMonospacePro' size='0.72' color='#4a4c45'>RÉF. SSE-%1</t><t align='right' size='0.95' font='PuristaBold' color='#a32a1e'>%2</t><br/><br/><t font='PuristaBold' size='1.5' color='#1b1c19'>%3</t><br/><br/><t color='#1b1c19'>%4</t>",
        [_ref, "NOUVEAU"] select (_ref isEqualTo ""), [_stampText] call _esc, [_titleShown] call _esc, [_text, format ["<t color='#7a7c75'>%1</t>", [_example call comspec_sse_fnc_tr] call _esc]] select (_example isNotEqualTo "")
    ];
    private _del = _it get "delivery";
    (_d displayCtrl 94055) ctrlSetText (if (_type isEqualTo "case") then { "Devient le dossier actif : les pièces et fiches suivantes y sont rattachées." } else { (([_deliveryLabels, _deliveryPapers] select (_type isNotEqualTo "intel")) select _del) select 2 });
    // État clair : brouillon local, enregistré sur Athena, publié dans cette partie.
    private _stateTxt = if ((_it get "id") isEqualTo "") then { "Pas encore enregistré" } else {
        if ((_it get "state") isEqualTo "Placé en mission") exitWith { "Publié dans cette partie" };
        ["Enregistré sur Athena", "Brouillon sur ce poste"] select (_it getOrDefault ["dirty", true])
    };
    if !(_st getOrDefault ["statusHold", false]) then { (_d displayCtrl 94003) ctrlSetText (_stateTxt call comspec_sse_fnc_tr); [_d displayCtrl 94003] call _fit; };
    (_d displayCtrl 94062) ctrlSetText (((["PLACER ET PUBLIER", "DEVENIR LE DOSSIER ACTIF"] select (_type isEqualTo "case")) call comspec_sse_fnc_tr));
};
private _highlight = {
    private _it = _st get "item";
    {
        private _on = _x isEqualTo (_it get "type");
        private _b = 94100 + _forEachIndex * 10;
        (_d displayCtrl _b) ctrlSetBackgroundColor ([[0.047, 0.071, 0.063, 1], [0.09, 0.157, 0.122, 1]] select _on);
        (_d displayCtrl (_b + 1)) ctrlSetTextColor ([[0.62, 0.69, 0.66, 1], [0.36, 0.78, 0.42, 1]] select _on);
    } forEach _types;
    {
        private _on = _forEachIndex isEqualTo (_it get "delivery");
        private _b = 94200 + _forEachIndex * 10;
        (_d displayCtrl _b) ctrlSetBackgroundColor ([[0.067, 0.094, 0.082, 1], [0.09, 0.157, 0.122, 1]] select _on);
        (_d displayCtrl (_b + 1)) ctrlSetTextColor ([[0.62, 0.69, 0.66, 1], [0.36, 0.78, 0.42, 1]] select _on);
    } forEach _deliveries;
};
// Formulaire selon le type : seulement les réglages utiles.
// Libellé plus long que sa case (traduction, modèle au nom long) : police réduite par paliers jusqu'à ce qu'il tienne,
// 60 % au plus bas, au lieu d'être coupé (« Sur quelqu… », « Papier de couvertur… »).
private _fit = {
    params ["_c"];
    if (isNull _c || {!ctrlShown _c}) exitWith {};
    private _base = _c getVariable ["comspec_sse_fh0", -1];
    if (_base < 0) then { _base = ctrlFontHeight _c; _c setVariable ["comspec_sse_fh0", _base]; };
    _c ctrlSetFontHeight _base;
    private _w = ((ctrlPosition _c) select 2) * 0.94;
    private _n = 0;
    while { (ctrlTextWidth _c) > _w && {_n < 5} } do {
        _c ctrlSetFontHeight ((ctrlFontHeight _c) * 0.9);
        _n = _n + 1;
    };
};
private _fitAll = {
    { [_d displayCtrl _x] call _fit; } forEach [94030, 94031, 94032, 94040, 94042, 94044, 94046, 94102, 94112, 94122, 94132, 94142, 94202, 94212, 94222, 94003];
};
private _form = {
    private _it = _st get "item";
    private _type = _it get "type";
    // Remplir les listes déclenche onLBSelChanged : champs posés d'abord, relecture coupée le temps du remplissage.
    _st set ["busy", true];
    (_d displayCtrl 94041) ctrlSetText (_it get "title");
    (_d displayCtrl 94047) ctrlSetText (_it get "body");
    private _i = _types find _type;
    (_d displayCtrl 94020) ctrlSetText (((_headings select _i) select 0) call comspec_sse_fnc_tr);
    (_d displayCtrl 94021) ctrlSetText (((_headings select _i) select 1) call comspec_sse_fnc_tr);
    { (_d displayCtrl (94030 + _forEachIndex)) ctrlSetText ((_x select 0) call comspec_sse_fnc_tr); } forEach (_models get _type);
    (_d displayCtrl 94040) ctrlSetText (if (_type in ["identity", "paper", "kit"]) then { "Prénom et nom" } else { ["Titre", "Référence du dossier"] select (_type isEqualTo "case") });
    private _main = _d displayCtrl 94043;
    lbClear _main;
    switch (_type) do {
        case "paper": {
            (_d displayCtrl 94042) ctrlSetText ("Type de papier" call comspec_sse_fnc_tr);
            { _main lbAdd ((_x select 1) call comspec_sse_fnc_tr); } forEach _papers;
            _main lbSetCurSel (_it get "paper");
        };
        case "kit": {
            (_d displayCtrl 94042) ctrlSetText ("Lot de papiers" call comspec_sse_fnc_tr);
            { _main lbAdd (_x call comspec_sse_fnc_tr); } forEach _kitSets;
            _main lbSetCurSel ((_it get "paper") min 2);
        };
        default {
            (_d displayCtrl 94042) ctrlSetText ("Classification" call comspec_sse_fnc_tr);
            { _main lbAdd (_x call comspec_sse_fnc_tr); } forEach _markings;
            _main lbSetCurSel (_it get "marking");
        };
    };
    // Classification : n'a d'effet que sur un renseignement. Identité et dossier : pas de liste.
    private _showMain = _type in ["intel", "paper", "kit"];
    (_d displayCtrl 94042) ctrlShow _showMain;
    _main ctrlShow _showMain;
    // Colonnes selon ce qui est affiché. Mêmes valeurs que la grille de ui_macros.hpp (40 x 25 cases).
    private _gw = ((safezoneW / safezoneH) min 1.2) / 40;
    private _gx = safezoneX + (safezoneW - _gw * 40) / 2;
    private _setX = { params ["_c", "_cx", "_cw"]; private _p = ctrlPosition _c; _c ctrlSetPosition [_gx + _cx * _gw, _p select 1, _cw * _gw, _p select 3]; _c ctrlCommit 0; };
    private _nat = _type in ["identity", "paper", "kit"];
    private _tw = if (_nat) then { [11.9, 6.0] select _showMain } else { [16.2, 8] select _showMain };
    [_d displayCtrl 94040, 8.7, _tw] call _setX;
    [_d displayCtrl 94041, 8.7, _tw] call _setX;
    private _mx = 8.7 + _tw + 0.2;
    private _mw = if (_nat) then { 5.8 } else { [8, 3.9] select (_type isEqualTo "intel") };
    [_d displayCtrl 94042, _mx, _mw] call _setX;
    [_main, _mx, _mw] call _setX;
    [_d displayCtrl 94044, 20.8, 4.1] call _setX;
    [_d displayCtrl 94045, 20.8, 4.1] call _setX;
    (_d displayCtrl 94046) ctrlSetText (([["Contenu", "Légende (imprimée sur la fiche de la personne)"] select (_type in ["identity", "kit"]), "Notes (bibliothèque seulement, non envoyées)"] select (_type in ["case", "paper"])) call comspec_sse_fnc_tr);
    // Libellés des modes de remise selon le type (renseignement ou papiers).
    { private _b = 94200 + _forEachIndex * 10; (_d displayCtrl (_b + 2)) ctrlSetText ((_x select 0) call comspec_sse_fnc_tr); (_d displayCtrl (_b + 3)) ctrlSetText ((_x select 1) call comspec_sse_fnc_tr); } forEach ([_deliveryLabels, _deliveryPapers] select (_type isNotEqualTo "intel"));
    // Papier : renseignement seulement, liste avec la vignette de chaque feuille.
    private _skinList = _d displayCtrl 94057;
    lbClear _skinList;
    {
        private _row = _skinList lbAdd ((_x select 1) call comspec_sse_fnc_tr);
        if !((_x select 0) in ["auto", "legacy"]) then { _skinList lbSetPicture [_row, ["texture", [_x select 0, false]] call comspec_sse_fnc_paperSkin]; };
    } forEach _skins;
    _skinList lbSetCurSel ((_skinCodes find (_it getOrDefault ["skin", "legacy"])) max 0);
    (_d displayCtrl 94056) ctrlShow (_type isEqualTo "intel");
    _skinList ctrlShow (_type isEqualTo "intel");
    private _second = _d displayCtrl 94045;
    lbClear _second;
    { _second lbAdd ((_x select 1) call comspec_sse_fnc_tr); } forEach _natures;
    _second lbSetCurSel (_it get "nature");
    private _needNature = _type in ["identity", "paper", "kit"];
    (_d displayCtrl 94044) ctrlShow _needNature;
    _second ctrlShow _needNature;
    // Un dossier n'est pas remis aux joueurs : pas de mode de remise.
    private _showDelivery = _type isNotEqualTo "case";
    (_d displayCtrl 94048) ctrlShow _showDelivery;
    { private _b = _x; for "_n" from 0 to 4 do { (_d displayCtrl (_b + _n)) ctrlShow _showDelivery; }; } forEach [94200, 94210, 94220];
    _st set ["busy", false];
    call _highlight;
    call _preview;
    call _fitAll;
};
// Contrôle d'une page cachée : masqué, désactivé et sorti de l'écran (plusieurs pages se superposent : aucun clic ne doit
// pouvoir atterrir sur un contrôle masqué).
private _vis = {
    params ["_idc", "_on"];
    private _c = _d displayCtrl _idc;
    private _home = _st getOrDefault ["home", createHashMap];
    _st set ["home", _home];
    if !(_idc in _home) then { _home set [_idc, ctrlPosition _c]; };
    private _p = _home get _idc;
    _c ctrlShow _on;
    _c ctrlEnable _on;
    _c ctrlSetPosition ([[-9, -9, _p select 2, _p select 3], _p] select _on);
    _c ctrlCommit 0;
};
private _page = {
    params ["_show", ["_ai", false]];
    _st set ["page", if (_ai) then { "ai" } else { ["editor", "library"] select _show }];
    { (_d displayCtrl _x) ctrlShow (!_show && {!_ai}); (_d displayCtrl _x) ctrlEnable (!_show && {!_ai}); } forEach _editorIdcs;
    { (_d displayCtrl _x) ctrlShow false; } forEach [94044, 94045, 94056, 94057, 94058];
    { [_x, _show && {!_ai}] call _vis; } forEach _libIdcs;
    { [_x, _ai] call _vis; } forEach _aiIdcs;
    (_d displayCtrl 94002) ctrlSetText ((if (_ai) then { "SSE / IA DE LA ZONE" } else { ["SSE / ÉDITEUR", "SSE / BIBLIOTHÈQUE"] select _show }) call comspec_sse_fnc_tr);
    (_d displayCtrl 94004) ctrlSetText ((["BIBLIOTHÈQUE", "NOUVEAU"] select (_show || {_ai})) call comspec_sse_fnc_tr);
    (_d displayCtrl 94006) ctrlShow !is3DEN;
    if (_ai) exitWith { ["load"] call comspec_sse_fnc_editorAi; };
    if (_show) then { ["libFill"] call comspec_sse_fnc_editor; } else { call _form; };
};
private _selected = {
    private _list = _d displayCtrl 94322;
    private _row = lnbCurSelRow _list;
    if (_row < 0) exitWith { createHashMap };
    private _id = _list lnbData [_row, 0];
    private _i = (call _library) findIf { (_x get "id") isEqualTo _id };
    if (_i < 0) then { createHashMap } else { (call _library) select _i }
};
private _store = {
    params ["_it", "_label"];
    private _lib = call _library;
    if ((_it get "id") isEqualTo "") then { _it set ["id", ["newId"] call comspec_sse_fnc_editorLibrary]; };
    _it set ["version", (_it get "version") + 1];
    _it set ["when", call _stamp];
    (_it get "history") pushBack [_label, call _who, call _stamp];
    _it set ["dirty", true];
    private _i = _lib findIf { (_x get "id") isEqualTo (_it get "id") };
    if (_i < 0) then { _lib pushBack _it; } else { _lib set [_i, _it]; };
    saveProfileNamespace;
    ["push", [_it]] call comspec_sse_fnc_editorLibrary;
};

switch (_op) do {
    case "open": {
        _arg params [["_pos", []], ["_obj", objNull], ["_startPage", ""]];
        // Module posé sur une personne vivante, IA : on sait quoi configurer.
        if (_startPage isEqualTo "" && {!isNull _obj} && {_obj isKindOf "CAManBase"} && {!isPlayer _obj} && {alive _obj}) then { _startPage = "ai"; };
        uiNamespace setVariable ["comspec_sse_editorState", createHashMapFromArray [["item", call _blank], ["page", "editor"], ["filter", 0], ["ctx", [_pos, _obj]], ["guide", 0], ["startPage", _startPage]]];
        if (is3DEN) then { (findDisplay 313) createDisplay "COMSPEC_SSE_Editor"; } else { createDialog "COMSPEC_SSE_Editor"; };
    };
    case "load": {
        uiNamespace setVariable ["comspec_sse_editorDisplay", _arg];
        _d = _arg;
        {
            private _b = 94100 + _forEachIndex * 10;
            (_d displayCtrl (_b + 1)) ctrlSetText format ["%1type_%2_ca.paa", _dir, _x];
            (_d displayCtrl (_b + 2)) ctrlSetText ((_typeLabels select _forEachIndex) call comspec_sse_fnc_tr);
            (_d displayCtrl (_b + 3)) ctrlSetText ((_typeHints select _forEachIndex) call comspec_sse_fnc_tr);
        } forEach _types;
        {
            private _b = 94200 + _forEachIndex * 10;
            (_d displayCtrl (_b + 1)) ctrlSetText format ["%1delivery_%2_ca.paa", _dir, _x];
            (_d displayCtrl (_b + 2)) ctrlSetText (((_deliveryLabels select _forEachIndex) select 0) call comspec_sse_fnc_tr);
            (_d displayCtrl (_b + 3)) ctrlSetText (((_deliveryLabels select _forEachIndex) select 1) call comspec_sse_fnc_tr);
        } forEach _deliveries;
        (_d displayCtrl 94322) lnbSetColumnsPos [0, 0.05, 0.5, 0.66, 0.84];
        if ((_st getOrDefault ["startPage", ""]) isEqualTo "ai") then { [false, true] call _page; } else { [false] call _page; };
        // Guidage au premier lancement seulement.
        private _seen = profileNamespace getVariable ["comspec_sse_editorGuided", false];
        { (_d displayCtrl _x) ctrlShow !_seen; } forEach [94400, 94401, 94402, 94403];
        if (!_seen) then { _st set ["guide", -1]; ["guideNext"] call comspec_sse_fnc_editor; };
    };
    case "unload": {
        uiNamespace setVariable ["comspec_sse_editorDisplay", displayNull];
        ["flush"] call comspec_sse_fnc_editorLibrary;
    };
    case "close": { _d closeDisplay 2; };
    case "guideNext": {
        private _steps = [
            ["Bienvenue dans l'éditeur SSE", "Choisissez à gauche ce que vous créez. Un modèle remplit le formulaire en un clic."],
            ["Voyez ce que verront les joueurs", "L'aperçu à droite suit votre saisie. Rien n'est envoyé avant « Placer et publier »."],
            ["Placez dans le monde", "Choisissez comment les joueurs l'obtiennent, puis cliquez dans le monde. La bibliothèque garde tout : recherche, duplication, historique."]
        ];
        private _n = (_st getOrDefault ["guide", -1]) + 1;
        if (_n >= (count _steps)) exitWith {
            profileNamespace setVariable ["comspec_sse_editorGuided", true];
            saveProfileNamespace;
            { (_d displayCtrl _x) ctrlShow false; } forEach [94400, 94401, 94402, 94403];
        };
        _st set ["guide", _n];
        (_steps select _n) params ["_t", "_b"];
        (_d displayCtrl 94402) ctrlSetStructuredText parseText format ["<t font='PuristaBold' size='1.3'>%1</t><br/><br/>%2", [_t] call _esc, [_b] call _esc];
        (_d displayCtrl 94403) ctrlSetText (["SUIVANT", "COMMENCER"] select (_n isEqualTo ((count _steps) - 1)));
    };
    case "aiPage": {
        if ((_st getOrDefault ["page", ""]) isEqualTo "editor") then { call _read; };
        [false, true] call _page;
    };
    case "type": {
        if (missionNamespace getVariable ["comspec_sse_id_editorDebug", false]) then { diag_log text format ["[COMSPEC SSE] Éditeur : type %1.", _arg]; };
        call _read;
        (_st get "item") set ["type", _types select _arg];
        call _form;
    };
    case "delivery": {
        call _read;
        (_st get "item") set ["delivery", _arg];
        call _highlight;
        call _preview;
    };
    case "model": {
        private _it = _st get "item";
        ((_models get (_it get "type")) select _arg) params ["", "_title", "_body", ["_nature", -1], ["_set", -1]];
        _it set ["title", _title];
        _it set ["body", [_body] call _newlines];
        if (_nature >= 0) then { _it set ["nature", _nature]; };
        if ((_it get "type") isEqualTo "kit") then { _it set ["paper", [_arg, _set] select (_set >= 0)]; };
        call _form;
    };
    case "preview": { if !(_st getOrDefault ["busy", false]) then { call _read; call _preview; }; };
    case "save": {
        call _read;
        private _it = _st get "item";
        if ((trim (_it get "title")) isEqualTo "") exitWith {
            private _need = (["Donnez d'abord un titre.", "Donnez d'abord un prénom et un nom."] select ((_it get "type") in ["identity", "paper", "kit"])) call comspec_sse_fnc_tr;
            (_d displayCtrl 94003) ctrlSetText _need;
            [_need, "WARNING"] call comspec_sse_fnc_toast;
        };
        [_it, format ["Version %1 enregistrée", (_it get "version") + 1]] call _store;
        (_d displayCtrl 94003) ctrlSetText format ["Brouillon enregistré à %1", [dayTime, "HH:MM"] call BIS_fnc_timeToString];
        call _preview;
    };
    case "publish": {
        ["save"] call comspec_sse_fnc_editor;
        private _it = _st get "item";
        if ((_it get "id") isEqualTo "") exitWith {};
        _d closeDisplay 2;
        [_it, _st get "ctx"] call comspec_sse_fnc_editorPlace;
    };
    case "toggleLibrary": {
        if ((_st get "page") isEqualTo "ai") exitWith { _st set ["item", call _blank]; [false] call _page; };
        if ((_st get "page") isEqualTo "library") then {
            _st set ["item", call _blank];
            [false] call _page;
        } else {
            call _read;
            [true] call _page;
            ["pull"] call comspec_sse_fnc_editorLibrary;
        };
    };
    case "libRefresh": { if ((_st getOrDefault ["page", ""]) isEqualTo "library") then { ["libFill"] call comspec_sse_fnc_editor; }; };
    case "status": {
        _arg params [["_text", ""], ["_online", false]];
        (_d displayCtrl 94003) ctrlSetText _text;
        if (_online && {(_st getOrDefault ["item", createHashMap]) isNotEqualTo createHashMap}) then { (_st get "item") set ["dirty", false]; };
        (_d displayCtrl 94060) ctrlSetText ((["Rien n'est envoyé aux joueurs avant « Placer et publier ». Le contenu reste enregistré sur ce poste.", "Rien n'est envoyé aux joueurs avant « Placer et publier ». Le contenu est aussi enregistré sur Athena."] select _online) call comspec_sse_fnc_tr);
    };
    case "filter": {
        _st set ["filter", _arg];
        ["libFill"] call comspec_sse_fnc_editor;
    };
    case "libFill": {
        private _list = _d displayCtrl 94322;
        lnbClear _list;
        private _filter = _st getOrDefault ["filter", 0];
        private _query = toLower (trim (ctrlText (_d displayCtrl 94321)));
        for "_i" from 0 to 7 do { (_d displayCtrl (94310 + _i)) ctrlSetBackgroundColor ([[0.047, 0.071, 0.063, 1], [0.09, 0.157, 0.122, 1]] select (_i isEqualTo _filter)); };
        private _shown = 0;
        {
            private _it = _x;
            private _state = _it get "state";
            private _keep = switch (_filter) do {
                case 0: { _state isNotEqualTo "Archivé" };
                case 6: { _state isEqualTo "Modèle" };
                case 7: { _state isEqualTo "Archivé" };
                default { _state isNotEqualTo "Archivé" && {(_it get "type") isEqualTo (_types select (_filter - 1))} };
            };
            if (_keep && {_query isEqualTo "" || {(toLower format ["%1 %2 %3 %4", _it get "title", _it get "op", _it get "body", _it get "link"]) find _query >= 0}}) then {
                private _row = _list lnbAddRow ["", _it get "title", (_typeLabels select (_types find (_it get "type"))) call comspec_sse_fnc_tr, _it get "op", _state];
                _list lnbSetData [[_row, 0], _it get "id"];
                private _icon = switch (_state) do { case "Placé en mission": { "placed" }; case "Modèle": { "template" }; case "Archivé": { "archived" }; default { "draft" }; };
                _list lnbSetPicture [[_row, 0], format ["%1state_%2_ca.paa", _dir, _icon]];
                _shown = _shown + 1;
            };
        } forEach (call _library);
        (_d displayCtrl 94323) ctrlSetText (["", "Rien ici pour le moment."] select (_shown isEqualTo 0));
        if (_shown > 0) then { _list lnbSetCurSelRow 0; };
        ["libSelect"] call comspec_sse_fnc_editor;
    };
    case "libSelect": {
        private _it = call _selected;
        private _has = (count _it) > 0;
        { (_d displayCtrl _x) ctrlEnable _has; } forEach [94333, 94334, 94335, 94336, 94340];
        (_d displayCtrl 94332) ctrlSetStructuredText parseText (if (_has) then { format ["<t font='PuristaBold'>%1</t>", [_it get "title"] call _esc] } else { "" });
        private _hist = if (_has) then { +(_it get "history") } else { [] };
        reverse _hist;
        private _row = { params ["_what", "_author", "_when"]; format ["%1<br/><t color='#a9b8b1' size='0.85'>%2 · %3</t>", [_what] call _esc, [_author] call _esc, [_when] call _esc] };
        private _rows = _hist apply { _x call _row };
        // Découverte : en tête de l'historique (maquette).
        private _found = if (_has) then { _it getOrDefault ["found", []] } else { [] };
        if (_found isNotEqualTo []) then { _rows insert [0, [["Trouvé par un joueur" call comspec_sse_fnc_tr, _found param [0, ""], _found param [1, ""]] call _row]]; };
        (_d displayCtrl 94338) ctrlSetStructuredText parseText (_rows joinString "<br/><br/>");
        (_d displayCtrl 94340) ctrlSetText (if (_has) then { _it get "link" } else { "" });
    };
    case "libLink": {
        private _it = call _selected;
        if ((count _it) isEqualTo 0) exitWith {};
        _it set ["link", ctrlText (_d displayCtrl 94340)];
        _it set ["dirty", true];
        saveProfileNamespace;
    };
    case "libEdit": {
        private _it = call _selected;
        if ((count _it) isEqualTo 0) exitWith {};
        // Un modèle se recopie : l'original reste.
        if ((_it get "state") isEqualTo "Modèle") exitWith { ["libDuplicate"] call comspec_sse_fnc_editor; };
        _st set ["item", _it];
        [false] call _page;
    };
    case "libDuplicate": {
        private _it = call _selected;
        if ((count _it) isEqualTo 0) exitWith {};
        private _copy = +_it;
        _copy set ["id", ""];
        _copy set ["state", "Brouillon"];
        _copy set ["version", 0];
        _copy set ["history", []];
        _copy set ["found", []];
        _copy set ["op", missionName];
        [_copy, format ["Copie de %1", _it get "title"]] call _store;
        _st set ["item", _copy];
        [false] call _page;
    };
    case "libArchive": {
        private _it = call _selected;
        if ((count _it) isEqualTo 0) exitWith {};
        _it set ["state", "Archivé"];
        (_it get "history") pushBack ["Archivé", call _who, call _stamp];
        _it set ["dirty", true];
        saveProfileNamespace;
        ["push", [_it]] call comspec_sse_fnc_editorLibrary;
        ["libFill"] call comspec_sse_fnc_editor;
    };
    case "libPlace": {
        private _it = call _selected;
        if ((count _it) isEqualTo 0) exitWith {};
        _d closeDisplay 2;
        [_it, [[], objNull]] call comspec_sse_fnc_editorPlace;
    };
    case "placed": {
        _arg params [["_id", ""], ["_summary", ""], ["_where", []]];
        private _i = (call _library) findIf { (_x get "id") isEqualTo _id };
        if (_i >= 0) then {
            private _it = (call _library) select _i;
            _it set ["state", "Placé en mission"];
            _it set ["op", missionName];
            (_it get "history") pushBack ["Placé en mission", call _who, call _stamp];
            _it set ["dirty", true];
            saveProfileNamespace;
            ["push", [_it]] call comspec_sse_fnc_editorLibrary;
            // « Garder pour les prochaines parties » : enregistré sur Athena, remis en place par le serveur au démarrage.
            if ((_it getOrDefault ["keep", false]) && {_where isNotEqualTo []} && {!is3DEN}) then { ["keepItem", [_it, _where]] call comspec_sse_fnc_editorAi; };
        };
        if (is3DEN) then { [_summary call comspec_sse_fnc_tr, 0] call BIS_fnc_3DENNotification; } else { [_summary, "SUCCESS"] call comspec_sse_fnc_toast; };
    };
    case "found": {
        _arg params [["_id", ""], ["_player", ""], ["_when", ""]];
        private _i = (call _library) findIf { (_x get "id") isEqualTo _id };
        // Seul le poste qui connaît ce contenu l'inscrit et prévient (le site le reçoit du serveur).
        if (_i < 0) exitWith {};
        private _it = (call _library) select _i;
        if ((_it getOrDefault ["found", []]) isEqualTo []) then {
            _it set ["found", [_player, _when]];
            _it set ["dirty", true];
            saveProfileNamespace;
        };
        [format ["%1 : %2 (%3).", _it get "title", "trouvé par un joueur" call comspec_sse_fnc_tr, _player], "INFO"] call comspec_sse_fnc_notify;
        if ((_st getOrDefault ["page", ""]) isEqualTo "library" && {!isNull _d}) then { ["libSelect"] call comspec_sse_fnc_editor; };
    };
    case "receive": {
        _arg params [["_title", ""], ["_body", ""]];
        // Dans le téléphone : centre de notifications de l'ATAK, sinon notification SSE.
        if (isNil "comspec_atak_native_fnc_notify") exitWith { [format ["%1 : %2", _title, _body], "INFO"] call comspec_sse_fnc_notify; };
        ["MESSAGE", format ["%1 : %2", _title, _body], 8, 10] call comspec_atak_native_fnc_notify;
    };
};
true
