/*
    Éditeur SSE (client) : formulaire, aperçu, bibliothèque, guidage. Aucun script par image : tout part d'un clic ou d'une
    frappe dans un champ (dialogs/editor.hpp). Placement : fn_editorPlace, puis fn_editorServer.
    [_op, _arg] call comspec_sse_fnc_editor
      open [pos, objet]   ouvre l'éditeur (module Zeus)        load display / unload
      type i · delivery i · model i · preview · save · publish
      toggleLibrary · filter i · libFill · libSelect · libEdit · libDuplicate · libPlace · libArchive · libLink
      guideNext · placed [id, résumé] · receive [titre, texte]
    Bibliothèque : profileNamespace « comspec_sse_editorLib » (liste de HashMap), sur ce poste seulement.
*/
params [["_op", "", [""]], ["_arg", 0]];
if (!hasInterface) exitWith { false };
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
private _models = createHashMapFromArray [
    ["intel", [["Rapport de source", "Rapport de source", "Source :\nFiabilité :\n\nCe qui a été observé :\n\nÀ vérifier :"], ["Carnet manuscrit", "Carnet retrouvé", "Pages déchirées, écriture rapide.\n\n"], ["Ordre intercepté", "Ordre intercepté", "De :\nÀ :\n\nOrdre :"]]],
    ["identity", [["Résident", "Prénom Nom", "Résident de la région, sans lien avec les forces en présence."], ["Couverture", "Prénom Nom", "Adresse :\nEmployeur :\nRaison du déplacement :"], ["Cible", "Prénom Nom", "Recherché pour :\nDernière position connue :"]]],
    ["paper", [["Papier civil", "Prénom Nom", ""], ["Papier de couverture", "Prénom Nom", ""], ["Faux grossier", "Prénom Nom", ""]]],
    ["case", [["Cible", "CIBLE-01", "Personne suivie :\nObjectif :"], ["Site", "SITE-01", "Site :\nCe qu'on y cherche :"], ["Réseau", "RESEAU-01", "Réseau suivi :\nPersonnes connues :"]]],
    ["kit", [["Infiltration", "Prénom Nom", "Identité de couverture et papiers."], ["Militaire", "Prénom Nom", "Carte militaire et carte d'identité."], ["Journaliste", "Prénom Nom", "Passeport et carte d'identité."]]]
];
private _deliveries = ["pickup", "search", "send"];
private _deliveryLabels = [["À ramasser", "Sur un objet choisi dans le monde", "Les joueurs le ramassent sur l'objet placé."], ["À trouver en fouille", "Sur une personne ou dans un véhicule", "Il apparaît lors d'une fouille."], ["Envoyé au téléphone", "À un groupe ou à un joueur", "Il arrive directement dans l'ATAK des destinataires."]];
private _markings = ["NON PROTÉGÉ", "DIFFUSION RESTREINTE", "CONFIDENTIEL"];
private _papers = [["id_card", "Carte d'identité"], ["passport", "Passeport"], ["driving_license", "Permis de conduire"], ["military_id", "Carte militaire"]];
private _natures = [["civil", "Vraie"], ["cover", "Couverture"], ["forged", "Faux"]];
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
            _lib pushBack createHashMapFromArray [["id", format ["M%1", _forEachIndex]], ["type", _type], ["title", _title], ["body", [_body] call _newlines], ["marking", 0], ["paper", 0], ["nature", 0], ["delivery", 0], ["state", "Modèle"], ["op", "Réutilisable"], ["when", ""], ["version", 1], ["history", []], ["link", ""]];
        } forEach [["kit", "Kit infiltration, papiers et légende", "Identité de couverture et papiers."], ["intel", "Rapport de source", "Source :\nFiabilité :\n\nCe qui a été observé :\n\nÀ vérifier :"], ["case", "Dossier cible", "Personne suivie :\nObjectif :"]];
        profileNamespace setVariable ["comspec_sse_editorLib", _lib];
    };
    _lib
};
private _stamp = {
    systemTime params ["_y", "_m", "_dd", "_h", "_mi"];
    format ["%1%2/%3%4/%5 %6%7:%8%9", ["", "0"] select (_dd < 10), _dd, ["", "0"] select (_m < 10), _m, _y, ["", "0"] select (_h < 10), _h, ["", "0"] select (_mi < 10), _mi]
};
private _blank = { createHashMapFromArray [["id", ""], ["type", "intel"], ["title", ""], ["body", ""], ["marking", 0], ["paper", 0], ["nature", 0], ["delivery", 0], ["state", "Brouillon"], ["op", missionName], ["when", ""], ["version", 0], ["history", []], ["link", ""]] };

private _editorIdcs = [94063, 94010, 94011, 94020, 94021, 94022, 94030, 94031, 94032, 94040, 94041, 94042, 94043, 94046, 94047, 94048, 94050, 94051, 94052, 94053, 94054, 94055, 94060, 94061, 94062];
{ private _b = _x; for "_i" from 0 to 4 do { _editorIdcs pushBack (_b + _i); }; } forEach [94100, 94110, 94120, 94130, 94140, 94200, 94210, 94220];
private _libIdcs = [94300, 94320, 94321, 94322, 94323, 94330, 94331, 94332, 94333, 94334, 94335, 94336, 94337, 94338, 94339, 94340, 94310, 94311, 94312, 94313, 94314, 94315, 94316, 94317];

// Valeurs du formulaire -> brouillon en cours.
private _read = {
    private _it = _st get "item";
    _it set ["title", ctrlText (_d displayCtrl 94041)];
    _it set ["body", ctrlText (_d displayCtrl 94047)];
    private _main = (lbCurSel (_d displayCtrl 94043)) max 0;
    if ((_it get "type") isEqualTo "paper") then { _it set ["paper", _main]; } else { _it set ["marking", _main]; };
    _it set ["nature", (lbCurSel (_d displayCtrl 94045)) max 0];
};
private _preview = {
    private _it = _st get "item";
    private _text = [[[_it get "body"] call _esc, toString [13], ""] call CBA_fnc_replace, toString [10], "<br/>"] call CBA_fnc_replace;
    private _type = _it get "type";
    private _stampText = if (_type isEqualTo "paper") then { toUpper ((_papers select (_it get "paper")) select 1) } else { _markings select (_it get "marking") };
    private _ref = _it get "id";
    (_d displayCtrl 94054) ctrlSetStructuredText parseText format [
        "<t font='EtelkaMonospacePro' size='0.72' color='#4a4c45'>RÉF. SSE-%1</t><t align='right' size='0.95' font='PuristaBold' color='#a32a1e'>%2</t><br/><br/><t font='PuristaBold' size='1.5' color='#1b1c19'>%3</t><br/><br/><t color='#1b1c19'>%4</t>",
        [_ref, "NOUVEAU"] select (_ref isEqualTo ""), [_stampText] call _esc, [_it get "title"] call _esc, _text
    ];
    private _del = _it get "delivery";
    (_d displayCtrl 94055) ctrlSetText (if (_type isEqualTo "case") then { "Devient le dossier actif : les pièces et fiches suivantes y sont rattachées." } else { (_deliveryLabels select _del) select 2 });
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
private _form = {
    private _it = _st get "item";
    private _type = _it get "type";
    // Remplir les listes déclenche onLBSelChanged : champs posés d'abord, relecture coupée le temps du remplissage.
    _st set ["busy", true];
    (_d displayCtrl 94041) ctrlSetText (_it get "title");
    (_d displayCtrl 94047) ctrlSetText (_it get "body");
    private _i = _types find _type;
    (_d displayCtrl 94020) ctrlSetText ((_headings select _i) select 0);
    (_d displayCtrl 94021) ctrlSetText ((_headings select _i) select 1);
    { (_d displayCtrl (94030 + _forEachIndex)) ctrlSetText (_x select 0); } forEach (_models get _type);
    (_d displayCtrl 94040) ctrlSetText (if (_type in ["identity", "paper", "kit"]) then { "Prénom et nom" } else { ["Titre", "Référence du dossier"] select (_type isEqualTo "case") });
    private _main = _d displayCtrl 94043;
    lbClear _main;
    if (_type isEqualTo "paper") then {
        (_d displayCtrl 94042) ctrlSetText "Type de papier";
        { _main lbAdd (_x select 1); } forEach _papers;
        _main lbSetCurSel (_it get "paper");
    } else {
        (_d displayCtrl 94042) ctrlSetText "Classification";
        { _main lbAdd _x; } forEach _markings;
        _main lbSetCurSel (_it get "marking");
    };
    private _second = _d displayCtrl 94045;
    lbClear _second;
    { _second lbAdd (_x select 1); } forEach _natures;
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
};
private _page = {
    params ["_show"];
    _st set ["page", ["editor", "library"] select _show];
    { (_d displayCtrl _x) ctrlShow !_show; } forEach _editorIdcs;
    (_d displayCtrl 94044) ctrlShow false;
    (_d displayCtrl 94045) ctrlShow false;
    { (_d displayCtrl _x) ctrlShow _show; } forEach _libIdcs;
    (_d displayCtrl 94002) ctrlSetText (["SSE / ÉDITEUR", "SSE / BIBLIOTHÈQUE"] select _show);
    (_d displayCtrl 94004) ctrlSetText (["BIBLIOTHÈQUE", "NOUVEAU"] select _show);
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
    if ((_it get "id") isEqualTo "") then { _it set ["id", format ["%1%2", floor (diag_tickTime % 10000), floor random 1000]]; };
    _it set ["version", (_it get "version") + 1];
    _it set ["when", call _stamp];
    (_it get "history") pushBack [_label, name player, call _stamp];
    private _i = _lib findIf { (_x get "id") isEqualTo (_it get "id") };
    if (_i < 0) then { _lib pushBack _it; } else { _lib set [_i, _it]; };
    saveProfileNamespace;
};

switch (_op) do {
    case "open": {
        _arg params [["_pos", []], ["_obj", objNull]];
        uiNamespace setVariable ["comspec_sse_editorState", createHashMapFromArray [["item", call _blank], ["page", "editor"], ["filter", 0], ["ctx", [_pos, _obj]], ["guide", 0]]];
        createDialog "COMSPEC_SSE_Editor";
    };
    case "load": {
        uiNamespace setVariable ["comspec_sse_editorDisplay", _arg];
        _d = _arg;
        {
            private _b = 94100 + _forEachIndex * 10;
            (_d displayCtrl (_b + 1)) ctrlSetText format ["%1type_%2_ca.paa", _dir, _x];
            (_d displayCtrl (_b + 2)) ctrlSetText (_typeLabels select _forEachIndex);
            (_d displayCtrl (_b + 3)) ctrlSetText (_typeHints select _forEachIndex);
        } forEach _types;
        {
            private _b = 94200 + _forEachIndex * 10;
            (_d displayCtrl (_b + 1)) ctrlSetText format ["%1delivery_%2_ca.paa", _dir, _x];
            (_d displayCtrl (_b + 2)) ctrlSetText ((_deliveryLabels select _forEachIndex) select 0);
            (_d displayCtrl (_b + 3)) ctrlSetText ((_deliveryLabels select _forEachIndex) select 1);
        } forEach _deliveries;
        (_d displayCtrl 94322) lnbSetColumnsPos [0, 0.05, 0.5, 0.66, 0.84];
        [false] call _page;
        // Guidage au premier lancement seulement.
        private _seen = profileNamespace getVariable ["comspec_sse_editorGuided", false];
        { (_d displayCtrl _x) ctrlShow !_seen; } forEach [94400, 94401, 94402, 94403];
        if (!_seen) then { _st set ["guide", -1]; ["guideNext"] call comspec_sse_fnc_editor; };
    };
    case "unload": { uiNamespace setVariable ["comspec_sse_editorDisplay", displayNull]; };
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
    case "type": {
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
        ((_models get (_it get "type")) select _arg) params ["", "_title", "_body"];
        _it set ["title", _title];
        _it set ["body", [_body] call _newlines];
        call _form;
    };
    case "preview": { if !(_st getOrDefault ["busy", false]) then { call _read; call _preview; }; };
    case "save": {
        call _read;
        private _it = _st get "item";
        if ((trim (_it get "title")) isEqualTo "") exitWith { (_d displayCtrl 94003) ctrlSetText "Donnez d'abord un titre."; };
        [_it, format ["Version %1 enregistrée", (_it get "version") + 1]] call _store;
        (_d displayCtrl 94003) ctrlSetText format ["Brouillon enregistré à %1", [dayTime, "HH:MM"] call BIS_fnc_timeToString];
        call _preview;
    };
    case "publish": {
        ["save"] call comspec_sse_fnc_editor;
        private _it = _st get "item";
        if ((_it get "id") isEqualTo "") exitWith {};
        closeDialog 2;
        [_it, _st get "ctx"] call comspec_sse_fnc_editorPlace;
    };
    case "toggleLibrary": {
        if ((_st get "page") isEqualTo "library") then {
            _st set ["item", call _blank];
            [false] call _page;
        } else {
            call _read;
            [true] call _page;
        };
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
                private _row = _list lnbAddRow ["", _it get "title", _typeLabels select (_types find (_it get "type")), _it get "op", _state];
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
        private _rows = if (_has) then { _hist apply { _x params ["_what", "_who", "_when"]; format ["%1<br/><t color='#a9b8b1' size='0.85'>%2 · %3</t>", [_what] call _esc, [_who] call _esc, _when] } } else { [] };
        (_d displayCtrl 94338) ctrlSetStructuredText parseText (_rows joinString "<br/><br/>");
        (_d displayCtrl 94340) ctrlSetText (if (_has) then { _it get "link" } else { "" });
    };
    case "libLink": {
        private _it = call _selected;
        if ((count _it) isEqualTo 0) exitWith {};
        _it set ["link", ctrlText (_d displayCtrl 94340)];
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
        _copy set ["op", missionName];
        [_copy, format ["Copie de %1", _it get "title"]] call _store;
        _st set ["item", _copy];
        [false] call _page;
    };
    case "libArchive": {
        private _it = call _selected;
        if ((count _it) isEqualTo 0) exitWith {};
        _it set ["state", "Archivé"];
        (_it get "history") pushBack ["Archivé", name player, call _stamp];
        saveProfileNamespace;
        ["libFill"] call comspec_sse_fnc_editor;
    };
    case "libPlace": {
        private _it = call _selected;
        if ((count _it) isEqualTo 0) exitWith {};
        closeDialog 2;
        [_it, [[], objNull]] call comspec_sse_fnc_editorPlace;
    };
    case "placed": {
        _arg params [["_id", ""], ["_summary", ""]];
        private _i = (call _library) findIf { (_x get "id") isEqualTo _id };
        if (_i >= 0) then {
            private _it = (call _library) select _i;
            _it set ["state", "Placé en mission"];
            _it set ["op", missionName];
            (_it get "history") pushBack ["Placé en mission", name player, call _stamp];
            saveProfileNamespace;
        };
        [_summary, "SUCCESS"] call comspec_sse_fnc_notify;
    };
    case "receive": {
        _arg params [["_title", ""], ["_body", ""]];
        // Dans le téléphone : centre de notifications de l'ATAK, sinon notification SSE.
        if (isNil "comspec_atak_native_fnc_notify") exitWith { [format ["%1 : %2", _title, _body], "INFO"] call comspec_sse_fnc_notify; };
        ["MESSAGE", format ["%1 : %2", _title, _body], 8, 10] call comspec_atak_native_fnc_notify;
    };
};
true
