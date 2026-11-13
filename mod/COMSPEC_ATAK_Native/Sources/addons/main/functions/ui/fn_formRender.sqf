/*
    Formulaire défilant (pages Athena, Réseau, Réglages, éditeur de marqueur).
    Params : [lignes, rectangle, dans la zone de contenu (true) ou absolu sur le display (false), couleur du fond]
    Lignes :
      ["title", texte]                       ["text", texte structuré, couleur du fond, variable uiNamespace du contrôle]
      ["edit", clé, libellé, valeur]          ["memo", clé, libellé, valeur, nb lignes]
      ["combo", clé, libellé, [[texte, donnée, image, couleur]...], donnée choisie, [libellé du bouton, code]]
          bouton (facultatif) à droite de la liste, sur la même ligne : code appelé avec [donnée choisie]
      ["field", clé, libellé, valeur, libellé du bouton, code, principal]   champ de saisie avec son bouton à droite
      ["toggle", libellé, actif, code]        ["buttons", [[libellé, code, principal, actif, aide]...]]
      ["password", clé, libellé]              ["hero", image, texte structuré]
      ["gap"]
      ["person", image, texte structuré, [[libellé, code, principal, actif, aide]...], couleur image]  (ligne compacte : vignette, texte, boutons à droite ;
          principal = true, false ou "red" pour un bouton de confirmation)
      ["section", titre, sous-titre]        ["switch", libellé, actif, code, aide, imposé, id]
      ["qr", rangées « 0 »/« 1 », part de la largeur]   QR code dessiné en contrôles (fn_mobileQr), carré, marge comprise
      ["fold", titre, résumé, ouverte, code, id]   en-tête de section repliable : barre pleine largeur cliquable, chevron
          à droite ; le contenu qui suit n'est ajouté par la page que si la section est ouverte
      ["slider", libellé, valeur 0-100, code, aide, actif, icône, code de l'icône, aide de l'icône, [min, max], id]
          curseur 0-100 (ou [min, max], valeur affichée en %) au cran de 1 : code appelé avec [valeur] pendant le glissé (sans redessiner la page) ; icône
          (data\nom) cliquable à gauche, valeur à droite (0 = MUET) ; actif = liseré et icône en couleur d'accent
      ["segment", libellé, [[texte, code, actif, aide]...], aide]   ["info", libellé, valeur, id]   ["image", chemin]
          (id de « info », facultatif : valeur réécrite sur place par fn_formSet)
      ["line", numéro, libellé, valeur, genre, clé, aide]   ligne de formulaire type 9-LINE, hauteur fixe :
          numéro en pastille, libellé à gauche, valeur à droite. genre "auto" (valeur calculée, en couleur d'accent ;
          vide ou "-" : l'aide en gris), "warn" (valeur en rouge), "edit" (champ de saisie clé dans la colonne valeur,
          l'aide en infobulle et en gris sous le libellé), "autoedit" (même champ, prérempli d'une proposition
          automatique affichée en couleur d'accent)
      ["bar", [[titre de groupe, [[libellé, code, état, aide, actif]...]]...]]
          barre de boutons par groupes titrés (façon app Drone) ; état "" | "on" (vert, actif) | "red" (confirmation)
          | "main" (bouton principal) ; les groupes passent à la ligne quand les boutons deviendraient trop étroits.
      ["line", ..., "mono"]  8e paramètre « mono » : ligne de fiche dense (carte CAS), libellé en capitales grises,
          valeur en police à chasse fixe, hauteur réduite. 9e paramètre (vrai) : libellé en rouge (champ requis vide).
          Une pastille de plus de deux caractères (« CL V », « ALFA ») s'élargit.
      ["lineseg", numéro, libellé, [[texte, code, actif, couleur RGBA]...], aide, en rouge]   ligne de fiche dont la
          valeur est un choix : boutons dans la colonne valeur, ou sous le libellé sur toute la largeur quand ils sont
          plus de trois ou trop longs (5 par rangée). Couleur (facultative) : fond du bouton choisi, texte des autres.
      ["linecombo", numéro, libellé, clé, [[texte, donnée]...], donnée choisie, aide, en rouge]   ligne de fiche dont
          la valeur est une liste déroulante (lue par fnc_formValue comme une ligne "combo").
      ["strip", texte gauche, texte droit, couleur du fond]   bandeau de terminal pleine largeur (texte structuré).
      ["squad", cases (fn_squadBar « cells »), texte gauche, texte droit, code du clic sur une case (format, %1 : netId)]
          réglette d'effectif : une case par membre, l'état par la forme ; les cases passent à la ligne.
      ["steps", [[libellé, état]...]]   frise d'étapes en cases : état "done" | "now" | "hot" | "abort" | "todo".
      ["banner", texte structuré, couleur du fond]   bandeau d'état en gros caractères, centré.
      ["big", [[libellé, code, couleur, actif, aide]...]]   gros boutons tactiles ; couleur "red" | "amber" | "green" | "".
      ["text", texte, couleur du fond]   la couleur du fond (facultative) pose le texte sur une carte.
      ["notif", icône, couleur, titre, à droite, texte, code ouvrir, code fermer, aide]   carte de notification : liseré
          et pastille d'icône à la couleur de gravité, titre en gras, heure / compteur à droite, texte en dessous ;
          clic sur la carte = code ouvrir ({} : rien), croix à droite = code fermer ({} : pas de croix)
      ["cells", [[icône, couleur, titre, à droite, texte]...], colonnes (2)]   cartes d'état en grille (app Resynch) :
          liseré et pastille d'icône à la couleur de l'état, titre en gras, état à droite, texte en dessous ; les cartes
          d'une même rangée prennent la hauteur de la plus haute
      ["empty", icône, titre, sous-titre]   état vide centré : grande icône pâle, titre et explication
      ["status", image, couleur RGBA, titre, texte, action, code]   carte d'état (app Athena) : liseré et titre à la couleur de
          l'état, image carrée à gauche, explication dessous, action à faire en ambre (facultative) ; hauteur au contenu ;
          code (facultatif) : clic sur la carte
      ["check", niveau, libellé, valeur, aide]   étape d'une chaîne (liaison) : pastille d'icône (niveau "ok" | "now" |
          "warn" | "ko" | "todo" | "off"), libellé à gauche, valeur à droite, aide en petit dessous ; hauteur au contenu
      ["meter", titre, détail, pourcentage (-1 : sans jauge), [étiquettes], code, choisie]   ligne à jauge de qualité (app Wave Relay)
      ["graph", modèle (fn_meshGraphView "model"), options, hauteur en rangées]   graphe du réseau dessiné dans le formulaire
    Composants partagés 3.1.0 (système de design, docs/ui/atak-design-system.md) :
      ["header", titre, sous-titre, [[libellé, code, principal]...], code retour]   en-tête d'app : grand titre, actions à
          droite, bouton « retour » quand un sous-écran est ouvert
      ["tabs", [[libellé, code, choisi, pastille]...]]   barre d'onglets soulignés (l'onglet choisi en accent)
      ["row", icône, titre, sous-titre, texte à droite, code, couleur d'icône, lettre, liseré]   ligne de liste haute avec
          filet et chevron (navigation) ; icône "" + lettre : pastille de lettre
      ["shapes", libellé, [[texte, code, choisie, aide, couches [[texture ("" : trait), couleur, échelle (trait : épaisseur en px)]...]]...], aide]
          choix à aperçu : le dessin réel sur un fond de carte, le choix encadré
      ["chips", [[texte, couleur RGBA, code]...]]   pastilles d'état ou d'étiquette, passage à la ligne
      ["skeleton", n, texte]   squelette de chargement (n lignes grises) en attendant des données
      ["toggle", libellé, actif, code]   bascule compacte (carte + pastille OUI / NON), même dessin que « switch »
      ["empty", icône, titre, sous-titre, [libellé, code]]   état vide avec, en option, un bouton d'action
      ["buttons", [[libellé, code, "red", …]]]   « red » à la place de principal : bouton destructif rouge
      ["field", …]   Entrée dans le champ appuie sur son bouton
    Couleurs : toutes tirées de fn_theme (thème sombre, jour ou nuit JVN) ; aucune couleur en dur dans les composants.
    id (facultatif, switch / slider / fold) : la ligne se met à jour sur place par fn_formSet, sans redessiner la page.
    Défilement du formulaire gardé quand la même page se redessine (clic sur un réglage, rafraîchissement) : groupe
    nommé d'après la page et ses premières lignes, relevé par fn_pageRender (COMSPEC_ATAK_ScrollGroups).
    L'aide (facultative) s'affiche en infobulle. Hauteur occupée par le contenu : uiNamespace COMSPEC_ATAK_FormH ;
    fond du formulaire : (contrôle renvoyé) getVariable "bg".
    Les champs sont lisibles ensuite par [clé] call comspec_atak_native_fnc_formValue.
*/
params ["_rows", "_rect", ["_inContent", true], ["_bgColor", []]];
disableSerialization;
private _d = [] call comspec_atak_native_fnc_display;
private _l = [] call comspec_atak_native_fnc_layoutGet;
// Jetons du thème (fn_theme, 3.1.0) : couleurs des cartes, textes, états ; plus aucune couleur en dur ici.
private _th = [] call comspec_atak_native_fnc_theme;
private _hT = _th get "hexText";
private _hTD = _th get "hexTextDim";
private _hTM = _th get "hexTextMuted";
private _hAL = _th get "hexAlert";
private _hWA = _th get "hexWarn";
private _hOK = _th get "hexOk";
private _hOA = _th get "hexOnAccent";
private _acc = _th get "accent";
private _accInk = _th get "accentInk";
private _accHex = _th get "hexAccent";
private _accLine = (_acc select [0, 3]) + [0.45];
private _okDim = (((_th get "ok") select [0, 3]) apply { _x * 0.38 }) + [1];
// Pictogrammes blancs de l'addon (app_*, ui_*) : encre du thème sur le fond clair du thème jour, sinon blancs (avant : blancs
// sur blanc, invisibles). Logos et images hors addon (photo Athena) : couleurs d'origine.
private _picTint = {
    params ["_p"];
    if ((_th get "dark") || {(_p find "\addons\main\data\") < 0} || {(_p find "logo") >= 0}) exitWith { [1, 1, 1, 1] };
    _th get "text"
};
if ((count _bgColor) isNotEqualTo 4) then { _bgColor = _th get "bg1"; };
private _font = _l get "font";
private _fs = _l get "fontSmall";
private _fc = _l get "fontCaption";
private _pad = (_l get "pad") * 2;
private _ratioG = uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW];
_rect params ["_rx", "_ry", "_rw", "_rh"];
private _bg = ["COMSPEC_RscPanel", _rect, "", _inContent] call comspec_atak_native_fnc_pageCtrl;
_bg ctrlSetBackgroundColor _bgColor;
private _grp = ["COMSPEC_RscControlsGroup", _rect, "", _inContent] call comspec_atak_native_fnc_pageCtrl;
private _w = _rw - 0.014 - 2 * _pad;
private _rowH = _l get "rowH";
private _listH = _l get "listH";
private _y = _pad;
private _fields = uiNamespace getVariable ["COMSPEC_ATAK_Form", createHashMap];
// Lignes à mise à jour sur place (fn_formSet) : id -> contrôles.
private _ids = uiNamespace getVariable ["COMSPEC_ATAK_FormIds", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_FormIds", _ids];
// Clic différé d'une image : beaucoup d'actions redessinent la page (ou en changent), ce qui supprimait le bouton
// pendant son propre gestionnaire, une cause connue d'arrêt du moteur (« can't resize AutoArray to negative size »).
private _click = {
    params ["_b", "_code"];
    if (_code isEqualType "") then { _code = compile _code; };
    _b setVariable ["comspec_click", _code];
    // Le rappel ne s'exécute qu'une fois. CBA_fnc_onFrame (cba_common, init_perFrameHandler.sqf) rejoue tout le tampon
    // « image suivante » tant qu'il n'a pas été vidé : une erreur de script dans le code du clic interrompt le
    // gestionnaire avant le vidage, et le même clic serait rejoué à chaque image (et les rappels d'ACE derrière lui
    // n'iraient jamais au bout). Marqué avant l'appel, le rejeu ne fait plus rien et la file se vide à l'image suivante.
    _b ctrlAddEventHandler ["ButtonClick", {
        params ["_c"];
        [{
            params ["_c", "_code", "_done"];
            if (_done select 0) exitWith {};
            _done set [0, true];
            [_c] call _code;
        }, [_c, _c getVariable ["comspec_click", {}], [false]]] call comspec_atak_native_fnc_nextFrame;
    }];
};
// Contrôles créés par la ligne en cours de dessin : décalés ensuite vers leur colonne (mise en page en colonnes, plus bas).
private _flMade = [];
private _mk = {
    params ["_class", "_pos", ["_text", ""]];
    private _c = _d ctrlCreate [_class, -1, _grp];
    _flMade pushBack _c;
    [_c, _class] call comspec_atak_native_fnc_fontApply;
    _c ctrlSetPosition _pos;
    if (_text isNotEqualTo "") then { _c ctrlSetText ((_text) call comspec_atak_native_fnc_tr); };
    _c ctrlCommit 0;
    _c
};
// Bouton dont le fond change d'état (choisi, confirmation) : face (RscText) dessous, bouton plat dessus, voir COMSPEC_RscButtonFlat.
// Le bouton garde sa face en variable (« face ») pour fn_formSet.
private _flat = {
    params ["_pos", "_label"];
    private _f = ["COMSPEC_RscText", _pos] call _mk;
    private _b = ["COMSPEC_RscButtonFlat", _pos, _label] call _mk;
    _b setVariable ["face", _f];
    _b
};
private _face = { params ["_b", "_col"]; (_b getVariable ["face", controlNull]) ctrlSetBackgroundColor _col; };
// Libellé de bouton plus large que le bouton (anglais, police TRÈS GRAND) : la police rétrécit au lieu de couper le texte.
private _fit = {
    params ["_b", "_bw", "_fh"];
    private _tw = ctrlTextWidth _b;
    if (_tw > _bw * 0.9) then { _b ctrlSetFontHeight ((_fh * _bw * 0.9 / _tw) max COMSPEC_ATAK_FontMin); };
};
// Hauteur plancher d'un texte structuré, estimée d'après sa longueur et la largeur de son cadre : ctrlTextHeight rendait
// trop peu dans une colonne étroite (app Drone, colonne de gauche en paysage sur S7 : aides, sections et interrupteurs
// empilés les uns sur les autres et sur les boutons). Params : [texte (balises comprises), largeur, taille relative].
private _estH = {
    params ["_txt", "_wid", ["_rel", -1]];
    // Taille relative non donnée : celle de la première balise size='…' du texte (aides en 0.8, etc.), sinon 1.
    if (_rel < 0) then {
        private _i = _txt find "size='";
        _rel = [1, parseNumber (_txt select [_i + 6, 5])] select (_i >= 0);
        if (_rel <= 0) then { _rel = 1; };
    };
    private _nl = toString [10];
    private _plain = (_txt regexReplace ["<br ?/?>", _nl]) regexReplace ["<[^>]*>", ""];
    private _fh = ((missionNamespace getVariable ["COMSPEC_ATAK_FontBody", 0]) max _fs) * _rel;
    // Largeur moyenne d'un caractère (police condensée, capitales comprises) en unités d'écran horizontales.
    private _cw = _fh * 0.46 * pixelW / pixelH;
    private _n = 0;
    { _n = _n + ((ceil (((count _x) * _cw) / (_wid max 0.01))) max 1); } forEach (_plain splitString _nl);
    (_n max 1) * _fh * 1.08
};
// Libellés des boutons pleine ligne et des boutons de champ : retour de jeu, ENREGISTRER et QUITTER LE GROUPE
// paraissaient minuscules et empâtés à côté du texte de la page ; presque la taille du corps, rétrécis par _fit si trop longs.
private _btnFs = _font * 0.92;
private _lineIdx = 0; // lignes "line" : fond alterné
// Dessin d'une ligne à la place courante : de _pad à _pad + _w, à partir de _y (avancé de sa hauteur).
private _renderRow = {
    private _row = _this;
    switch (_row select 0) do {
        case "title": {
            private _t = ["COMSPEC_RscText", [_pad, _y, _w, _rowH], toUpper (_row select 1)] call _mk;
            _t ctrlSetFontHeight _fs;
            _t ctrlSetTextColor _accInk;
            _y = _y + _rowH;
        };
        case "text": {
            _row params ["", "_txt", ["_tbg", []], ["_keep", ""]];
            private _card = (count _tbg) isEqualTo 4;
            private _ix = [0, _pad / 2] select _card;
            private _cb = controlNull;
            if (_card) then { _cb = ["COMSPEC_RscText", [_pad, _y, _w, _rowH]] call _mk; _cb ctrlSetBackgroundColor _tbg; };
            private _t = ["COMSPEC_RscStructuredText", [_pad + _ix, _y + _ix / 2, _w - 2 * _ix, _rowH]] call _mk;
            private _trTxt = (_txt) call comspec_atak_native_fnc_tr;
            _t ctrlSetStructuredText parseText ((_trTxt) call comspec_atak_native_fnc_tr);
            private _h = (ctrlTextHeight _t) max (_fs * 1.2) max ([_trTxt, _w - 2 * _ix] call _estH);
            // Texte gardé pour être réécrit sans redessiner la page (décompte de la confirmation d'explosifs…) : il grandit
            // ensuite sans que sa case suive. Deux lignes réservées d'office (avant : « Cancelled in 10 s unless you » coupé).
            if (_keep isNotEqualTo "") then { _h = _h max (_fs * 2.5); };
            _t ctrlSetPosition [_pad + _ix, _y + _ix / 2, _w - 2 * _ix, _h];
            _t ctrlCommit 0;
            if (_card) then { _cb ctrlSetPosition [_pad, _y, _w, _h + _ix]; _cb ctrlCommit 0; _h = _h + _ix; };
            // Texte rafraîchi ensuite sans redessiner la page (panneau SITUATION de la carte) : contrôle gardé sous ce nom.
            if (_keep isNotEqualTo "") then { uiNamespace setVariable [_keep, _t]; };
            _y = _y + _h + ([_pad / 2, pixelH * 2] select _card);
        };
        case "strip": {
            // Bandeau de terminal : fond sombre pleine largeur, texte à gauche et à droite, chacun dans sa colonne
            // (un texte long passe à la ligne et le bandeau grandit, au lieu de chevaucher l'autre côté).
            _row params ["", "_left", ["_right", ""], ["_sbg", (_th get "bg0")]];
            private _sh = _fs * 1.45;
            private _hasR = _right isNotEqualTo "";
            private _lw = [_w - _pad * 0.8, _w * 0.52 - _pad * 0.4] select _hasR;
            private _sb = ["COMSPEC_RscText", [_pad, _y, _w, _sh]] call _mk;
            _sb ctrlSetBackgroundColor _sbg;
            private _lt = ["COMSPEC_RscStructuredText", [_pad * 1.4, _y, _lw, _sh]] call _mk;
            _lt ctrlSetStructuredText parseText ((_left) call comspec_atak_native_fnc_tr);
            private _parts = [_lt];
            if (_hasR) then {
                private _rt = ["COMSPEC_RscStructuredText", [_pad + _w * 0.52, _y, _w * 0.48 - _pad * 0.4, _sh]] call _mk;
                _rt ctrlSetStructuredText parseText ((format ["<t align='right'>%1</t>", _right]) call comspec_atak_native_fnc_tr);
                _parts pushBack _rt;
            };
            private _hh = _sh;
            { _hh = _hh max ((ctrlTextHeight _x) + pixelH * 4); } forEach _parts;
            { private _txH = (ctrlTextHeight _x) min _hh; _x ctrlSetPositionY (_y + (_hh - _txH) / 2); _x ctrlSetPositionH _txH; _x ctrlCommit 0; } forEach _parts;
            _sb ctrlSetPositionH _hh; _sb ctrlCommit 0;
            _y = _y + _hh + pixelH * 2;
        };
        case "squad": {
            _row params ["", "_cells", ["_left", ""], ["_right", ""], ["_onCell", ""]];
            private _sqH = _fs * 1.35;
            if (_left isNotEqualTo "" || {_right isNotEqualTo ""}) then {
                private _st1 = ["COMSPEC_RscStructuredText", [_pad, _y, _w, _fs * 1.25]] call _mk;
                _st1 ctrlSetStructuredText parseText ((format ["%1<t align='right'>%2</t>", _left, _right]) call comspec_atak_native_fnc_tr);
                _y = _y + _fs * 1.25;
            };
            (["prims", [_cells, _pad, _y, _w, _sqH, true, false, _onCell isNotEqualTo ""]] call comspec_atak_native_fnc_squadBar) params ["_sqUsed", "_sqPrims"];
            {
                _x params ["_class", "_pos", "_text", "_back", "_inkC", "_fh", "_nid"];
                private _c = [_class, _pos, _text] call _mk;
                if ((count _back) > 0) then { _c ctrlSetBackgroundColor _back; };
                if ((count _inkC) > 0) then { _c ctrlSetTextColor _inkC; };
                if (_fh > 0) then { _c ctrlSetFontHeight _fh; };
                if (_nid isNotEqualTo "") then { [_c, compile format [_onCell, str _nid]] call _click; };
            } forEach _sqPrims;
            _y = _y + _sqUsed + _pad / 2;
        };
        case "steps": {
            // Frise de la mission en cases accolées (deux rangées si les cases deviennent trop étroites).
            private _st = _row select 1;
            private _n = count _st;
            private _minW = _fs * 4.6 * (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]);
            private _per = ((floor (_w / _minW)) max 1) min _n;
            if (_per < _n) then { _per = ceil (_n / (ceil (_n / _per))); };
            private _cw = _w / (_per max 1);
            private _ch = _fs * 1.35;
            {
                _x params ["_lbl", ["_state", "todo"]];
                private _cx = _pad + (_forEachIndex mod _per) * _cw;
                private _cy = _y + (floor (_forEachIndex / _per)) * (_ch + pixelH * 2);
                private _c = ["COMSPEC_RscTextCenter", [_cx, _cy, _cw - pixelW * 2, _ch], _lbl] call _mk;
                _c ctrlSetFont (["EtelkaMonospacePro", "EtelkaMonospaceProBold"] select (_state in ["now", "hot", "abort"]));
                _c ctrlSetFontHeight (_fs * 0.72);
                switch (_state) do {
                    case "done": { _c ctrlSetBackgroundColor _okDim; _c ctrlSetTextColor (_th get "ok"); };
                    case "now": { _c ctrlSetBackgroundColor (_th get "warn"); _c ctrlSetTextColor (_th get "onState"); };
                    case "hot";
                    case "abort": { _c ctrlSetBackgroundColor (_th get "alert"); _c ctrlSetTextColor [1, 1, 1, 1]; };
                    default { _c ctrlSetBackgroundColor (_th get "surface"); _c ctrlSetTextColor (_th get "textMuted"); };
                };
            } forEach _st;
            _y = _y + (ceil (_n / (_per max 1))) * (_ch + pixelH * 2) + _pad / 3;
        };
        case "banner": {
            // Bandeau d'état : la situation de la mission en gros, centrée, sur la couleur de l'état.
            _row params ["", "_txt", ["_bbg", (_th get "surface")]];
            private _bb = ["COMSPEC_RscText", [_pad, _y, _w, _rowH]] call _mk;
            _bb ctrlSetBackgroundColor _bbg;
            private _t = ["COMSPEC_RscStructuredText", [_pad * 1.5, _y + _pad / 3, _w - _pad, _rowH]] call _mk;
            _t ctrlSetStructuredText parseText ((format ["<t align='center'>%1</t>", _txt]) call comspec_atak_native_fnc_tr);
            private _h = (ctrlTextHeight _t) max (_fs * 1.3);
            _t ctrlSetPosition [_pad * 1.5, _y + _pad / 3, _w - _pad, _h];
            _t ctrlCommit 0;
            _bb ctrlSetPosition [_pad, _y, _w, _h + _pad * 0.66];
            _bb ctrlCommit 0;
            _y = _y + _h + _pad * 0.66 + _pad / 2;
        };
        case "big": {
            // Gros boutons tactiles (gants) : décision de tir, appels principaux.
            private _btns = _row select 1;
            private _n = count _btns;
            private _bh2 = _rowH * 1.55;
            // Colonne étroite : les boutons passent à la rangée suivante plutôt que de couper leur libellé.
            private _bigF = (_fs * 1.05) max COMSPEC_ATAK_FontMin;
            private _bigLon = 0;
            { _bigLon = _bigLon max (count (((_x select 0) call comspec_atak_native_fnc_tr) splitString "")); } forEach _btns;
            private _bigNeed = _bigLon * _bigF * 0.62 * pixelW / pixelH;
            private _bigPer = _n max 1;
            while { _bigPer > 1 && {_bigNeed > ((_w - (_bigPer - 1) * _pad / 2) / _bigPer)} } do { _bigPer = _bigPer - 1; };
            private _bw2 = (_w - (_bigPer - 1) * _pad / 2) / _bigPer;
            {
                _x params ["_label", "_code", ["_col", ""], ["_enabled", true], ["_tip", ""]];
                private _b = ["COMSPEC_RscButton", [_pad + (_forEachIndex mod _bigPer) * (_bw2 + _pad / 2), _y + (floor (_forEachIndex / _bigPer)) * (_bh2 + _pad / 2), _bw2, _bh2], _label] call _mk;
                _b ctrlSetFont "EtelkaMonospaceProBold";
                _b ctrlSetFontHeight _bigF;
                private _bgc = switch (_col) do {
                    case "red": { (_th get "alert") };
                    case "amber": { (_th get "warn") };
                    case "green": { (_th get "ok") };
                    default { (_th get "surface2") };
                };
                if (!_enabled) then { _bgc = [(_bgc select 0) * 0.35 + 0.04, (_bgc select 1) * 0.35 + 0.05, (_bgc select 2) * 0.35 + 0.045, 1]; };
                _b ctrlSetBackgroundColor _bgc;
                _b ctrlSetTextColor ([(_th get "off"), [[1, 1, 1, 1], (_th get "onState")] select (_col isEqualTo "amber")] select _enabled);
                if (_tip isNotEqualTo "") then { _b ctrlSetTooltip ((_tip) call comspec_atak_native_fnc_tr); };
                _b ctrlEnable _enabled;
                [_b, _code] call _click;
            } forEach _btns;
            _y = _y + (ceil (_n / _bigPer)) * (_bh2 + _pad / 2);
        };
        case "edit";
        case "memo": {
            _row params ["_kind", "_key", "_label", ["_value", ""], ["_lines", 3]];
            private _lb = ["COMSPEC_RscLabel", [_pad, _y, _w, _fs * 1.2], _label] call _mk;
            _lb ctrlSetFontHeight _fs;
            _y = _y + _fs * 1.2;
            private _h = [_rowH, _font * 1.15 * _lines + _font * 0.3] select (_kind isEqualTo "memo");
            private _e = [["COMSPEC_RscEdit", "COMSPEC_RscEditMulti"] select (_kind isEqualTo "memo"), [_pad, _y, _w, _h], _value] call _mk;
            _e ctrlSetFontHeight _font;
            _fields set [_key, _e];
            _y = _y + _h + _pad / 2;
        };
        case "field": {
            // Champ et bouton sur la même ligne (renommer) : libellé au-dessus, bouton à droite à hauteur du champ.
            _row params ["", "_key", "_label", ["_value", ""], ["_btnLabel", "OK"], ["_code", {}], ["_primary", true]];
            private _lb = ["COMSPEC_RscLabel", [_pad, _y, _w, _fs * 1.2], _label] call _mk;
            _lb ctrlSetFontHeight _fs;
            _y = _y + _fs * 1.2;
            private _bwF = _w * 0.32;
            private _e = ["COMSPEC_RscEdit", [_pad, _y, _w - _bwF - _pad / 2, _rowH], _value] call _mk;
            _e ctrlSetFontHeight _font;
            _fields set [_key, _e];
            // Entrée dans le champ = appui sur son bouton (recherche d'un réglage, renommer…), clavier compris.
            if (_code isEqualType "") then { _code = compile _code; };
            _e setVariable ["comspec_enter", _code];
            _e ctrlAddEventHandler ["KeyDown", {
                params ["_c", "_key"];
                if !(_key in [28, 156]) exitWith { false };
                [{ params ["_c", "_code"]; [_c] call _code; }, [_c, _c getVariable ["comspec_enter", {}]]] call comspec_atak_native_fnc_nextFrame;
                true
            }];
            private _b = [["COMSPEC_RscButton", "COMSPEC_RscButtonPrimary"] select _primary, [_pad + _w - _bwF, _y, _bwF, _rowH], _btnLabel] call _mk;
            _b ctrlSetFontHeight _btnFs;
            [_b, _bwF, _btnFs] call _fit;
            if !(_primary) then { _b ctrlSetBackgroundColor (_th get "surface2"); _b ctrlSetTextColor (_th get "text"); };
            [_b, _code] call _click;
            _y = _y + _rowH + _pad / 2;
        };
        case "combo": {
            _row params ["", "_key", "_label", "_items", ["_selData", ""], ["_btn", []]];
            private _lb = ["COMSPEC_RscLabel", [_pad, _y, _w, _fs * 1.2], _label] call _mk;
            _lb ctrlSetFontHeight _fs;
            _y = _y + _fs * 1.2;
            // Bouton intégré (pas d'application au choix : la molette sur une liste fermée en change la ligne).
            private _bwC = [0, _w * 0.32] select ((count _btn) isEqualTo 2);
            private _c = ["COMSPEC_RscCombo", [_pad, _y, _w - ([0, _bwC + _pad / 2] select (_bwC > 0)), _rowH]] call _mk;
            _c ctrlSetFontHeight _font;
            private _sel = 0;
            {
                _x params ["_text", "_data", ["_pic", ""], ["_color", []]];
                private _i = _c lbAdd ((_text) call comspec_atak_native_fnc_tr);
                _c lbSetData [_i, _data];
                if (_pic isNotEqualTo "") then { _c lbSetPicture [_i, _pic]; };
                if ((count _color) isEqualTo 4) then { _c lbSetPictureColor [_i, _color]; _c lbSetPictureColorSelected [_i, _color]; };
                if (_data isEqualTo _selData) then { _sel = _i; };
            } forEach _items;
            _c lbSetCurSel _sel;
            if (_bwC > 0) then {
                _btn params ["_bl", "_bc"];
                private _b = ["COMSPEC_RscButtonPrimary", [_pad + _w - _bwC, _y, _bwC, _rowH], _bl] call _mk;
                _b ctrlSetFontHeight _btnFs;
                [_b, _bwC, _btnFs] call _fit;
                _b setVariable ["comspec_combo", [_c, _bc]];
                [_b, { params ["_b"]; (_b getVariable ["comspec_combo", [controlNull, {}]]) params ["_c", "_bc"]; if (!isNull _c) then { [_c lbData (lbCurSel _c)] call _bc; }; }] call _click;
            };
            _fields set [_key, _c];
            _y = _y + _rowH + _pad / 2;
        };
        case "toggle": {
            // Bascule compacte (3.1.0) : même dessin que « switch » (carte, libellé, pastille OUI / NON) sans l'aide,
            // à la place du bouton « ○ / ● » d'avant, qui ne ressemblait à aucun autre interrupteur du téléphone.
            _row params ["", "_label", "_on", "_code"];
            private _pillW = (_font * 3.6 * _ratioG) min (_w * 0.3);
            private _card = ["COMSPEC_RscText", [_pad, _y, _w, _rowH]] call _mk;
            _card ctrlSetBackgroundColor (_th get "surface");
            private _t = ["COMSPEC_RscStructuredText", [_pad * 1.5, _y, _w - _pillW - _pad * 2.5, _rowH]] call _mk;
            _t ctrlSetStructuredText parseText ((format ["<t size='0.95' color='" + _hT + "'>%1</t>", _label]) call comspec_atak_native_fnc_tr);
            private _th2 = (ctrlTextHeight _t) min _rowH;
            _t ctrlSetPosition [_pad * 1.5, _y + (_rowH - _th2) / 2, _w - _pillW - _pad * 2.5, _th2];
            _t ctrlCommit 0;
            private _ph = _fs * 1.25;
            private _pill = ["COMSPEC_RscTextCenter", [_pad + _w - _pillW - _pad / 2, _y + (_rowH - _ph) / 2, _pillW, _ph], ["NON", "OUI"] select _on] call _mk;
            _pill ctrlSetFontHeight ((_fs * 0.85) max COMSPEC_ATAK_FontMin);
            _pill ctrlSetBackgroundColor ([(_th get "off"), _acc] select _on);
            _pill ctrlSetTextColor ([(_th get "text"), (_th get "onAccent")] select _on);
            private _b = ["COMSPEC_RscButtonOverlay", [_pad, _y, _w, _rowH]] call _mk;
            [_b, _code] call _click;
            _y = _y + _rowH + _pad / 3;
        };
        case "tabs": {
            // Onglets (3.1.0) : une rangée d'onglets soulignés, l'onglet choisi en couleur d'accent avec un trait
            // dessous, les autres en texte atténué ; pastille de compteur facultative. Remplace la ligne « segment »
            // utilisée comme barre d'onglets (Debug, Groupe, Zones, BFT…) : on voit d'un coup d'œil où l'on est.
            // Params : [[libellé, code, choisi, pastille (nombre ou texte, facultatif)]...]
            private _tabs = _row select 1;
            private _n = (count _tabs) max 1;
            private _minF = COMSPEC_ATAK_FontMin max (_fs * 0.6);
            private _longest = 0;
            { _longest = _longest max (count ((((_x select 0) call comspec_atak_native_fnc_tr) splitString ""))); } forEach _tabs;
            private _needW = _longest * _minF * 0.55 * pixelW / pixelH;
            private _per = _n;
            while { _per > 1 && {_needW > (_w / _per) * 0.86} } do { _per = _per - 1; };
            private _tw = _w / _per;
            private _tabH = _rowH * 1.05;
            private _nRows = ceil (_n / _per);
            {
                _x params ["_text", "_code", ["_active", false], ["_badge", ""]];
                private _tx = _pad + (_forEachIndex mod _per) * _tw;
                private _ty = _y + (floor (_forEachIndex / _per)) * (_tabH + pixelH * 2);
                private _bgT = ["COMSPEC_RscText", [_tx, _ty, _tw - pixelW, _tabH]] call _mk;
                _bgT ctrlSetBackgroundColor ([(_th get "surfaceAlt"), (_th get "surface")] select _active);
                private _bt = _badge;
                if (_bt isEqualType 0) then { _bt = [str _bt, ""] select (_bt <= 0); };
                private _lbl = ["COMSPEC_RscStructuredText", [_tx, _ty, _tw - pixelW, _tabH]] call _mk;
                // 4.2.1 : libellé réduit jusqu'à la taille plancher quand il dépasse l'onglet. La répartition ci-dessus
                // compte à la taille plancher, le dessin se faisait à la taille normale : un libellé long passait sur
                // deux lignes, coupé par la hauteur de l'onglet.
                private _fh = ctrlFontHeight _lbl;
                if (_fh <= 0) then { _fh = _font; };
                private _chars = (count (((_text call comspec_atak_native_fnc_tr) splitString ""))) + ([0, 1 + 0.78 * (count (str _bt))] select (_bt isNotEqualTo ""));
                private _drawW = _chars * _fh * 0.55 * pixelW / pixelH;
                private _sz = 1;
                if (_drawW > _tw * 0.9) then { _sz = ((_tw * 0.9) / _drawW) max (_minF / _fh) min 1; };
                _lbl ctrlSetStructuredText parseText ((format ["<t align='center' font='COMSPEC_BarlowBold' size='%4' color='%1'>%2</t>%3",
                    [_hTD, _accHex] select _active, toUpper _text,
                    ["", format [" <t size='%3' color='%1'>%2</t>", [_hTM, _hWA] select _active, _bt, 0.78 * _sz]] select (_bt isNotEqualTo ""), _sz]) call comspec_atak_native_fnc_tr);
                private _lh = (ctrlTextHeight _lbl) min _tabH;
                _lbl ctrlSetPosition [_tx, _ty + (_tabH - _lh) / 2 - pixelH, _tw - pixelW, _lh];
                _lbl ctrlCommit 0;
                private _ul = ["COMSPEC_RscText", [_tx, _ty + _tabH - pixelH * 3, _tw - pixelW, pixelH * 3]] call _mk;
                _ul ctrlSetBackgroundColor ([(_th get "border"), _acc] select _active);
                private _b = ["COMSPEC_RscButtonOverlay", [_tx, _ty, _tw - pixelW, _tabH]] call _mk;
                [_b, _code] call _click;
            } forEach _tabs;
            _y = _y + _nRows * (_tabH + pixelH * 2) + _pad / 2;
        };
        case "row": {
            // Ligne de liste haute (3.1.0) : icône ou pastille à gauche, titre et sous-titre, valeur à droite, chevron
            // quand elle ouvre quelque chose ; filet dessous. Pour les listes de navigation (Réglages, apps récentes,
            // conversations) à la place des lignes « person » à bouton OUVRIR.
            // Params : [icône (chemin .paa, "" : pastille de lettre), titre, sous-titre, texte à droite, code (clic : {}
            //          pour une ligne inerte), couleur de l'icône (RGBA), lettre de la pastille, liseré d'état (RGBA ou [])]
            _row params ["", ["_icon", ""], ["_title", ""], ["_sub", ""], ["_right", ""], ["_code", {}], ["_icol", []], ["_ini", ""], ["_strip", []]];
            if ((count _icol) isNotEqualTo 4) then { _icol = _th get "textDim"; };
            private _e = { params ["_v"]; (_v regexReplace ["&", "&amp;"]) regexReplace ["<", "&lt;"] };
            private _clickable = _code isNotEqualTo {};
            private _ih = _font * 1.7;
            private _iw = _ih * _ratioG;
            private _chevW = [0, _font * 0.9 * _ratioG] select _clickable;
            private _rw2 = [0, _w * 0.26] select (_right isNotEqualTo "");
            private _tx = _pad * 1.4 + ([0, _iw + _pad * 0.8] select (_icon isNotEqualTo "" || {_ini isNotEqualTo ""}));
            private _tw = (_w - _tx + _pad - _rw2 - _chevW - _pad * 0.8) max (_w * 0.3);
            private _t = ["COMSPEC_RscStructuredText", [_tx, _y, _tw, _listH]] call _mk;
            _t ctrlSetStructuredText parseText ((format ["<t color='" + _hT + "'>%1</t>%2", [_title] call _e,
                ["", format ["<br/><t size='0.82' color='" + _hTD + "'>%1</t>", _sub]] select (_sub isNotEqualTo "")]) call comspec_atak_native_fnc_tr);
            private _h = ((ctrlTextHeight _t) + _pad * 0.9) max _listH;
            _t ctrlSetPosition [_tx, _y + (_h - (ctrlTextHeight _t)) / 2, _tw, ctrlTextHeight _t];
            _t ctrlCommit 0;
            if ((count _strip) isEqualTo 4) then {
                private _st = ["COMSPEC_RscText", [_pad, _y + _pad * 0.35, pixelW * 4, _h - _pad * 0.7]] call _mk;
                _st ctrlSetBackgroundColor _strip;
            };
            if (_icon isNotEqualTo "") then {
                private _p = ["COMSPEC_RscPicture", [_pad * 1.4, _y + (_h - _ih) / 2, _iw, _ih], _icon] call _mk;
                _p ctrlSetTextColor _icol;
            } else {
                if (_ini isNotEqualTo "") then {
                    private _pc = ["COMSPEC_RscTextCenter", [_pad * 1.4, _y + (_h - _ih) / 2, _iw, _ih]] call _mk;
                    _pc ctrlSetBackgroundColor [(_icol select 0) * 0.28, (_icol select 1) * 0.28, (_icol select 2) * 0.28, 1];
                    _pc ctrlSetTextColor _icol;
                    _pc ctrlSetFontHeight (_ih * 0.55);
                    _pc ctrlSetText _ini; // i18n:brut
                };
            };
            if (_right isNotEqualTo "") then {
                private _r = ["COMSPEC_RscStructuredText", [_tx + _tw, _y, _rw2, _h]] call _mk;
                _r ctrlSetStructuredText parseText ((format ["<t align='right' size='0.9' color='" + _hTD + "'>%1</t>", _right]) call comspec_atak_native_fnc_tr);
                private _rh = (ctrlTextHeight _r) min _h;
                _r ctrlSetPosition [_tx + _tw, _y + (_h - _rh) / 2, _rw2, _rh];
                _r ctrlCommit 0;
            };
            if (_clickable) then {
                private _cs = _font * 0.9;
                private _cv = ["COMSPEC_RscPicture", [_pad + _w - _chevW, _y + (_h - _cs) / 2, _cs * _ratioG, _cs], "\z\comspec_atak_native\addons\main\data\ui_chevron_right.paa"] call _mk;
                _cv ctrlSetTextColor (_th get "textMuted");
                private _b = ["COMSPEC_RscButtonOverlay", [_pad, _y, _w, _h]] call _mk;
                _b ctrlSetTooltip ((_title) call comspec_atak_native_fnc_tr);
                [_b, _code] call _click;
            };
            private _ln = ["COMSPEC_RscText", [_tx, _y + _h - pixelH, _w - _tx + _pad, pixelH]] call _mk;
            _ln ctrlSetBackgroundColor (_th get "border");
            _y = _y + _h;
        };
        case "chips": {
            // Pastilles d'état (3.1.0) : petites étiquettes colorées sur une ligne (états, étiquettes, filtres), passage
            // à la ligne quand la rangée est pleine. Params : [[texte, couleur RGBA (fond) ou "" (neutre), code (facultatif)]...]
            private _chips = _row select 1;
            private _ch = _fs * 1.35;
            private _gap = _pad * 0.4;
            private _cx = _pad;
            private _cy = _y;
            private _m = ["COMSPEC_RscTextCenter", [0, 0, _w, _ch]] call _mk;
            _m ctrlSetFontHeight ((_fs * 0.82) max COMSPEC_ATAK_FontMin);
            _m ctrlShow false;
            {
                _x params ["_text", ["_col", ""], ["_code", {}]];
                private _tr = (_text) call comspec_atak_native_fnc_tr;
                _m ctrlSetText _tr; // i18n:brut (déjà traduit)
                private _cw = (ctrlTextWidth _m) + _pad * 1.4;
                if (_cx + _cw > _pad + _w && {_cx > _pad}) then { _cx = _pad; _cy = _cy + _ch + _gap; };
                private _c = ["COMSPEC_RscTextCenter", [_cx, _cy, _cw, _ch]] call _mk;
                _c ctrlSetText _tr; // i18n:brut (déjà traduit)
                _c ctrlSetFont (COMSPEC_ATAK_FontSet select 1);
                _c ctrlSetFontHeight ((_fs * 0.82) max COMSPEC_ATAK_FontMin);
                private _has = (count _col) isEqualTo 4;
                _c ctrlSetBackgroundColor ([(_th get "surface2"), [(_col select 0), (_col select 1), (_col select 2), 0.22]] select _has);
                _c ctrlSetTextColor ([(_th get "text"), _col] select _has);
                if (_code isNotEqualTo {}) then { [["COMSPEC_RscButtonOverlay", [_cx, _cy, _cw, _ch]] call _mk, _code] call _click; };
                _cx = _cx + _cw + _gap;
            } forEach _chips;
            ctrlDelete _m;
            _y = _cy + _ch + _pad / 2;
        };
        case "skeleton": {
            // Squelette de chargement (3.1.0) : n lignes grises à la place d'un contenu pas encore reçu (liste Athena,
            // fiche en cours de synchro), pour ne jamais afficher une page vide sans explication. Params : [n, texte d'attente]
            _row params ["", ["_n", 3], ["_wait", "Chargement…"]];
            for "_i" from 1 to (_n max 1) do {
                private _k = ["COMSPEC_RscText", [_pad, _y, _w, _listH * 0.9]] call _mk;
                _k ctrlSetBackgroundColor (_th get "surface");
                private _b1 = ["COMSPEC_RscText", [_pad * 1.5, _y + _listH * 0.2, _w * ([0.55, 0.4, 0.65] select (_i mod 3)), _fs * 0.7]] call _mk;
                _b1 ctrlSetBackgroundColor (_th get "surface2");
                private _b2 = ["COMSPEC_RscText", [_pad * 1.5, _y + _listH * 0.55, _w * ([0.35, 0.5, 0.3] select (_i mod 3)), _fs * 0.5]] call _mk;
                _b2 ctrlSetBackgroundColor (_th get "surfaceAlt");
                _y = _y + _listH * 0.9 + _pad / 3;
            };
            if (_wait isNotEqualTo "") then {
                private _t = ["COMSPEC_RscStructuredText", [_pad, _y, _w, _fs * 1.3]] call _mk;
                _t ctrlSetStructuredText parseText ((format ["<t align='center' size='0.85' color='" + _hTM + "'>%1</t>", _wait]) call comspec_atak_native_fnc_tr);
                _y = _y + _fs * 1.3;
            };
            _y = _y + _pad / 2;
        };
        case "header": {
            // En-tête d'app (3.1.0) : titre en grand, sous-titre, et des actions à droite (icône ou texte) ; un bouton
            // « ‹ » quand l'app a un sous-écran à refermer. Même dessin dans toutes les apps à vues multiples.
            // Params : [titre, sous-titre, [[libellé, code, principal]...], code retour ({} : pas de retour)]
            _row params ["", ["_title", ""], ["_sub", ""], ["_acts", []], ["_back", {}]];
            private _hh = _font * 2.3;
            private _hasBack = _back isNotEqualTo {};
            private _bx = _pad;
            if (_hasBack) then {
                private _bs = _font * 1.1;
                private _bw0 = _font * 1.7 * _ratioG;
                private _bb = ["COMSPEC_RscText", [_pad, _y + (_hh - _font * 1.7) / 2, _bw0, _font * 1.7]] call _mk;
                _bb ctrlSetBackgroundColor (_th get "surface2");
                private _bi = ["COMSPEC_RscPicture", [_pad + (_bw0 - _bs * _ratioG) / 2, _y + (_hh - _bs) / 2, _bs * _ratioG, _bs], "\z\comspec_atak_native\addons\main\data\ui_back.paa"] call _mk;
                _bi ctrlSetTextColor (_th get "text");
                private _bo = ["COMSPEC_RscButtonOverlay", [_pad, _y + (_hh - _font * 1.7) / 2, _bw0, _font * 1.7]] call _mk;
                _bo ctrlSetTooltip (("Retour") call comspec_atak_native_fnc_tr);
                [_bo, _back] call _click;
                _bx = _pad + _bw0 + _pad * 0.6;
            };
            // Actions à droite : largeur au texte, de droite à gauche.
            private _m = ["COMSPEC_RscTextCenter", [0, 0, _w, _hh]] call _mk;
            _m ctrlSetFontHeight (_fs * 0.9);
            _m ctrlShow false;
            private _aws = _acts apply {
                _m ctrlSetText ((_x select 0) call comspec_atak_native_fnc_tr); // i18n:brut (déjà traduit)
                ((ctrlTextWidth _m) + _pad * 1.6) max (_font * 1.7 * _ratioG)
            };
            ctrlDelete _m;
            private _awAll = 0;
            { _awAll = _awAll + _x + _pad * 0.4; } forEach _aws;
            // 4.2.1 : colonne étroite (petit téléphone en portrait) : le titre garde toute la largeur et les actions passent
            // dessous, à droite ; avant, le titre se serrait entre le retour et les actions, un mot (ou une syllabe) par ligne.
            private _stack = _acts isNotEqualTo [] && {(_pad + _w - _bx - _awAll) < (_w * 0.55)};
            private _tw = [((_pad + _w - _awAll) - _bx) max (_w * 0.3), _pad + _w - _bx] select _stack;
            private _t = ["COMSPEC_RscStructuredText", [_bx, _y, _tw, _hh]] call _mk;
            // Colonne étroite : titre réduit jusqu'à ce que son mot le plus long tienne (« troubleshooting » coupé en deux).
            private _tsz = 1.25;
            if (_stack) then {
                // Taille de base des balises <t> : celle du contrôle, au moins celle de sa classe (0,024 × safeZoneH).
                private _fh0 = (ctrlFontHeight _t) max (0.024 * safeZoneH);
                private _mw = ["COMSPEC_RscText", [0, 0, _w, _hh]] call _mk;
                _mw ctrlSetFont "COMSPEC_BarlowBold";
                _mw ctrlSetFontHeight (_fh0 * 1.25);
                _mw ctrlShow false;
                private _lw = 0;
                { _mw ctrlSetText _x; _lw = _lw max (ctrlTextWidth _mw); } forEach (((_title) call comspec_atak_native_fnc_tr) splitString " "); // i18n:brut
                ctrlDelete _mw;
                // Marge large : le texte structuré coupe un mot avant la largeur mesurée par ctrlTextWidth.
                if (_lw > _tw * 0.8) then { _tsz = ((1.25 * _tw * 0.8 / _lw) max 0.7) min 1.25; };
            };
            _t ctrlSetStructuredText parseText ((format ["<t size='%3' font='COMSPEC_BarlowBold' color='" + _hT + "'>%1</t>%2", _title,
                ["", format ["<br/><t size='0.82' color='" + _hTD + "'>%1</t>", _sub]] select (_sub isNotEqualTo ""), _tsz]) call comspec_atak_native_fnc_tr);
            private _h = (ctrlTextHeight _t) max _hh;
            _t ctrlSetPosition [_bx, _y + ((_hh - (ctrlTextHeight _t)) / 2) max 0, _tw, ctrlTextHeight _t];
            _t ctrlCommit 0;
            private _ay = [_y + (_hh - _font * 1.7) / 2, _y + _h + _pad * 0.3] select _stack;
            if (_stack) then { _h = _h + _font * 1.7 + _pad * 0.3; };
            private _ax = _pad + _w;
            {
                _x params ["_label", "_code", ["_pri", false]];
                private _tr = (_label) call comspec_atak_native_fnc_tr;
                private _aw = _aws select _forEachIndex;
                _ax = _ax - _aw;
                private _ab = [["COMSPEC_RscButton", "COMSPEC_RscButtonPrimary"] select _pri, [_ax, _ay, _aw, _font * 1.7]] call _mk;
                _ab ctrlSetText _tr; // i18n:brut (déjà traduit)
                _ab ctrlSetFontHeight (_fs * 0.9);
                if (!_pri) then { _ab ctrlSetBackgroundColor (_th get "surface2"); _ab ctrlSetTextColor (_th get "text"); };
                [_ab, _code] call _click;
                _ax = _ax - _pad * 0.4;
            } forEach _acts;
            private _ln = ["COMSPEC_RscText", [_pad, _y + _h + _pad * 0.3, _w, pixelH]] call _mk;
            _ln ctrlSetBackgroundColor (_th get "border");
            _y = _y + _h + _pad * 0.8;
        };
        case "section": {
            // En-tête de section : barre verte, titre et sous-titre.
            _row params ["", "_title", ["_sub", ""]];
            _y = _y + _pad / 2;
            private _bar = ["COMSPEC_RscText", [_pad, _y + _fs * 0.15, _pad * 0.35, _fs * 1.1]] call _mk;
            _bar ctrlSetBackgroundColor _acc;
            private _t = ["COMSPEC_RscStructuredText", [_pad * 1.6, _y, _w - _pad, _rowH]] call _mk;
            // Titre en blanc (une couleur d'accent sombre, bleu ou rouge, se perdait sur le fond) : la barre porte l'accent.
            // Traduit avant la mise en capitales : « Cibles marquées : %1 » n'a pas de modèle en capitales.
            _t ctrlSetStructuredText parseText ((format ["<t font='COMSPEC_BarlowBold' color='" + _hT + "'>%1</t>%2", toUpper ((_title) call comspec_atak_native_fnc_tr), ["", format ["<br/><t size='0.8' color='" + _hTD + "'>%1</t>", _sub]] select (_sub isNotEqualTo "")]) call comspec_atak_native_fnc_tr);
            private _h = (ctrlTextHeight _t) max (_fs * 1.3)
                max (([(toUpper _title) call comspec_atak_native_fnc_tr, _w - _pad, 1] call _estH) + ([0, [(_sub) call comspec_atak_native_fnc_tr, _w - _pad, 0.8] call _estH] select (_sub isNotEqualTo "")));
            _t ctrlSetPosition [_pad * 1.6, _y, _w - _pad, _h];
            _t ctrlCommit 0;
            _y = _y + _h + _pad / 2;
        };
        case "switch": {
            // Ligne réglage : libellé + aide à gauche, interrupteur à droite ; « IMPOSÉ » si la communauté fixe la valeur.
            _row params ["", "_label", "_on", "_code", ["_help", ""], ["_locked", false], ["_id", ""]];
            // Pastille plafonnée au tiers de la ligne : dans le volet étroit de la carte, elle prenait toute la largeur
            // et le libellé disparaissait (on ne voyait que des « OUI »).
            private _pillW = (_font * 3.6 * (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW])) min (_w * 0.3);
            private _card = ["COMSPEC_RscText", [_pad, _y, _w, _rowH]] call _mk;
            _card ctrlSetBackgroundColor (_th get "surface");
            private _t = ["COMSPEC_RscStructuredText", [_pad * 1.5, _y + _pad / 4, _w - _pillW - _pad * 2.5, _rowH]] call _mk;
            // Libellé en blanc franc, aide en gris clair (retour de jeu : les lignes semblaient grisées).
            _t ctrlSetStructuredText parseText ((format ["<t size='0.95' color='" + _hT + "'>%1</t>%2", _label, ["", format ["<br/><t size='0.75' color='" + _hTD + "'>%1</t>", _help]] select (_help isNotEqualTo "")]) call comspec_atak_native_fnc_tr);
            private _tw = _w - _pillW - _pad * 2.5;
            private _h = (((ctrlTextHeight _t) max (([(_label) call comspec_atak_native_fnc_tr, _tw, 0.95] call _estH)
                + ([0, [(_help) call comspec_atak_native_fnc_tr, _tw, 0.75] call _estH] select (_help isNotEqualTo "")))) + _pad / 2) max _rowH;
            _t ctrlSetPosition [_pad * 1.5, _y + _pad / 4, _tw, _h - _pad / 2];
            _t ctrlCommit 0;
            _card ctrlSetPosition [_pad, _y, _w, _h];
            _card ctrlCommit 0;
            private _ph = _fs * 1.35;
            // Interrupteur libre : la pastille est le bouton. Un bouton transparent posé sur toute la ligne l'assombrissait en jeu, malgré ses couleurs à 0 :
            // les lignes libres paraissaient grisées et les lignes imposées, sans lui, claires (l'inverse du sens).
            private _pill = [["COMSPEC_RscButton", "COMSPEC_RscTextCenter"] select _locked, [_pad + _w - _pillW - _pad / 2, _y + (_h - _ph) / 2, _pillW, _ph], [["NON", "OUI"] select _on, format ["IMPOSÉ · %1", ["NON", "OUI"] select _on]] select _locked] call _mk;
            _pill ctrlSetFontHeight ((_fs * 0.85) max COMSPEC_ATAK_FontMin);
            [_pill, _pillW, (_fs * 0.85) max COMSPEC_ATAK_FontMin] call _fit;
            _pill ctrlSetBackgroundColor ([[(_th get "off"), _acc] select _on, (_th get "warnDim")] select _locked);
            _pill ctrlSetTextColor ([(_th get "text"), (_th get "onAccent")] select (_on && {!_locked}));
            if (_id isNotEqualTo "") then { _ids set [_id, createHashMapFromArray [["kind", "switch"], ["pill", _pill], ["locked", _locked], ["w", _pillW], ["fh", (_fs * 0.85) max COMSPEC_ATAK_FontMin]]]; };
            if !(_locked) then {
                [_pill, _code] call _click;
            } else {
                _card ctrlSetTooltip (("Valeur imposée : la ligne dit par qui. Non modifiable ici.") call comspec_atak_native_fnc_tr);
            };
            _y = _y + _h + _pad / 3;
        };
        case "fold": {
            // Section repliable (sous-menu) : grande cible de clic, titre à gauche, résumé et chevron à droite.
            // Retour de jeu : « certains sous-menus sont trop peu visibles ». Carte plus claire et bordée, titre toujours
            // en blanc, barre d'accent plus large, résumé en gris clair ; ouverte, le contenu est encadré d'un filet.
            // Titre et résumé dans un seul texte (titre à gauche, résumé aligné à droite) sur toute la largeur hors
            // chevron : deux colonnes fixes coupaient les mots (« SITUAT ION ») et posaient le compteur sur le titre.
            // Trop long, le résumé passe à la ligne et la carte s'agrandit, sans chevauchement.
            _row params ["", "_title", ["_sub", ""], ["_open", false], ["_code", {}], ["_id", ""]];
            private _ratio = uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW];
            private _h = _rowH * 1.25;
            _y = _y + _pad / 3;
            private _cs = _h * 0.5;
            private _cw = _cs / _ratio;
            private _tW = _w - _cw - _pad * 2.9;
            // Fond d'abord (l'ordre de création fait l'empilement), à la hauteur finale une fois le texte mesuré.
            private _card = ["COMSPEC_RscText", [_pad, _y, _w, _h]] call _mk;
            _card ctrlSetBackgroundColor ([(_th get "surface2"), (_th get "surfaceHi")] select _open);
            private _t = ["COMSPEC_RscStructuredText", [_pad * 1.9, _y, _tW, _h]] call _mk;
            _t ctrlSetStructuredText parseText ((format ["<t font='COMSPEC_BarlowBold' color='" + _hT + "'>%1</t>%2", toUpper _title,
                ["", format ["<t align='right' size='0.85' color='" + _hTD + "'>%1</t>", _sub]] select (_sub isNotEqualTo "")]) call comspec_atak_native_fnc_tr);
            _h = _h max ((ctrlTextHeight _t) + _pad / 2);
            _t ctrlSetPosition [_pad * 1.9, _y + (((_h - (ctrlTextHeight _t)) / 2) max 0), _tW, ctrlTextHeight _t];
            _t ctrlCommit 0;
            _card ctrlSetPositionH _h;
            _card ctrlCommit 0;
            if (_id isNotEqualTo "") then { _ids set [_id, createHashMapFromArray [["kind", "fold"], ["text", _t], ["title", _title]]]; };
            {
                private _ln = ["COMSPEC_RscText", [_pad, _y + _x, _w, pixelH]] call _mk;
                _ln ctrlSetBackgroundColor [1, 1, 1, [0.10, 0.16] select _open];
            } forEach [0, _h - pixelH];
            private _bar = ["COMSPEC_RscText", [_pad, _y, pixelW * 6, _h]] call _mk;
            _bar ctrlSetBackgroundColor ([(_th get "rail"), _acc] select _open);
            private _ch = ["COMSPEC_RscPicture", [_pad + _w - _cw - _pad * 0.75, _y + (_h - _cs) / 2, _cw, _cs], format ["\z\comspec_atak_native\addons\main\data\%1.paa", ["ui_chevron_down", "ui_chevron_up"] select _open]] call _mk;
            _ch ctrlSetTextColor ([(_th get "text"), _accInk] select _open);
            private _b = ["COMSPEC_RscButtonOverlay", [_pad, _y, _w, _h]] call _mk;
            _b ctrlSetTooltip ((["Déplier", "Replier"] select _open) call comspec_atak_native_fnc_tr);
            [_b, _code] call _click;
            _y = _y + _h + _pad / ([3, 2] select _open);
        };
        case "slider": {
            _row params ["", "_label", "_val", "_code", ["_help", ""], ["_active", false], ["_icon", ""], ["_iconCode", {}], ["_iconTip", ""], ["_range", [0, 100]], ["_id", ""]];
            _range params [["_min", 0], ["_max", 100]];
            private _ratio = uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW];
            private _h = _rowH * 1.3;
            private _card = ["COMSPEC_RscText", [_pad, _y, _w, _h]] call _mk;
            _card ctrlSetBackgroundColor (_th get "surface");
            // Liseré créé même inactif quand la ligne se met à jour sur place (fn_formSet le montre ou le masque).
            private _rim = controlNull;
            if (_active || {_id isNotEqualTo ""}) then {
                _rim = ["COMSPEC_RscText", [_pad, _y, pixelW * 3, _h]] call _mk;
                _rim ctrlSetBackgroundColor _acc;
                _rim ctrlShow _active;
            };
            private _ix = _pad * 1.6;
            private _ic = controlNull;
            if (_icon isNotEqualTo "") then {
                private _is = _rowH * 0.7;
                _ic = ["COMSPEC_RscPicture", [_ix, _y + (_h - _is) / 2, _is / _ratio, _is], format ["\z\comspec_atak_native\addons\main\data\%1.paa", _icon]] call _mk;
                _ic ctrlSetTextColor ([(_th get "textDim"), _accInk] select _active);
                private _ib = ["COMSPEC_RscButtonOverlay", [_ix, _y + (_h - _is) / 2, _is / _ratio, _is]] call _mk;
                if (_iconTip isNotEqualTo "") then { _ib ctrlSetTooltip ((_iconTip) call comspec_atak_native_fnc_tr); };
                [_ib, _iconCode] call _click;
                _ix = _ix + _is / _ratio + _pad;
            };
            private _lw = _w * 0.34;
            private _t = ["COMSPEC_RscStructuredText", [_ix, _y, _lw, _h]] call _mk;
            _t ctrlSetStructuredText parseText ((format ["<t size='0.95' color='" + _hT + "'>%1</t>%2", _label, ["", format ["<br/><t size='0.72' color='" + _hTD + "'>%1</t>", _help]] select (_help isNotEqualTo "")]) call comspec_atak_native_fnc_tr);
            private _txH = ctrlTextHeight _t;
            _t ctrlSetPosition [_ix, _y + (((_h - _txH) / 2) max 0), _lw, _txH min _h];
            _t ctrlCommit 0;
            private _vw = _font * 2.8 / _ratio;
            private _sx = _ix + _lw + _pad / 2;
            private _sw = (_pad + _w - _pad / 2 - _vw) - _sx - _pad / 2;
            private _vt = ["COMSPEC_RscTextRight", [_pad + _w - _pad / 2 - _vw, _y, _vw, _h], [(str (round _val)) + " %", "MUET"] select (_min <= 0 && {(round _val) <= 0})] call _mk;
            _vt ctrlSetFont (COMSPEC_ATAK_FontSet select 1);
            _vt ctrlSetFontHeight _fs;
            _vt ctrlSetTextColor ([(_th get "text"), _accInk] select _active);
            private _sl = ["COMSPEC_RscSlider", [_sx, _y + _h * 0.3, _sw, _h * 0.4]] call _mk;
            _sl sliderSetRange [_min, _max];
            _sl sliderSetSpeed [1, 10];
            _sl sliderSetPosition (_val max _min min _max);
            _sl setVariable ["comspec_slide", [_code, _vt, round _val, _min]];
            if (_id isNotEqualTo "") then { _ids set [_id, createHashMapFromArray [["kind", "slider"], ["slider", _sl], ["value", _vt], ["rim", _rim], ["icon", _ic], ["min", _min], ["max", _max]]]; };
            _sl ctrlAddEventHandler ["SliderPosChanged", {
                params ["_c", "_v"];
                (_c getVariable ["comspec_slide", [{}, controlNull, -1, 0]]) params ["_code", "_vt", "_last", ["_min", 0]];
                _v = round _v;
                if (_v isEqualTo _last) exitWith {};
                _c setVariable ["comspec_slide", [_code, _vt, _v, _min]];
                if (!isNull _vt) then { _vt ctrlSetText (([(str _v) + " %", "MUET"] select (_min <= 0 && {_v <= 0})) call comspec_atak_native_fnc_tr); };
                [_v] call _code;
            }];
            _y = _y + _h + _pad / 3;
        };
        case "segment": {
            // Choix exclusif en une ligne : libellé au-dessus, boutons accolés, celui choisi en vert.
            // id (facultatif) : choix changé sur place par fn_formSet ; 5e élément d'une option : sa valeur. Plusieurs lignes
            // peuvent partager un id (même réglage sur deux rangées).
            _row params ["", "_label", "_opts", ["_help", ""], ["_segId", ""]];
            private _segBtns = [];
            if (_label isNotEqualTo "") then {
                private _lb = ["COMSPEC_RscStructuredText", [_pad, _y, _w, _fs * 1.3]] call _mk;
                _lb ctrlSetStructuredText parseText ((format ["<t size='0.9'>%1</t>%2", _label, ["", format ["  <t size='0.75' color='" + _hTD + "'>%1</t>", _help]] select (_help isNotEqualTo "")]) call comspec_atak_native_fnc_tr);
                private _lh = (ctrlTextHeight _lb) max (_fs * 1.3)
                    max ([format ["%1  %2", (_label) call comspec_atak_native_fnc_tr, (_help) call comspec_atak_native_fnc_tr], _w, [0.9, 0.8] select (_help isNotEqualTo "")] call _estH);
                if (_lh > _fs * 1.3) then { _lb ctrlSetPosition [_pad, _y, _w, _lh]; _lb ctrlCommit 0; };
                _y = _y + _lh + _fs * 0.05;
            };
            private _n = count _opts;
            // Colonne étroite : autant de boutons par rangée que leurs libellés le permettent (taille plancher comprise),
            // la suite passe à la rangée suivante. Avant : 4 ou 5 boutons forcés sur une rangée, libellés débordant les
            // uns sur les autres (« REVENIR À MOI », altitudes, vitesses dans la colonne de gauche de l'app Drone).
            private _minF = COMSPEC_ATAK_FontMin max (_fs * 0.6);
            private _longest = 0;
            { _longest = _longest max (count ((((_x select 0) call comspec_atak_native_fnc_tr) splitString ""))); } forEach _opts;
            private _needW = _longest * _minF * 0.55 * pixelW / pixelH;
            private _per = _n max 1;
            while { _per > 1 && {_needW > ((_w / _per) - _pad / 6) * 0.9} } do { _per = _per - 1; };
            private _sw = _w / _per;
            {
                _x params ["_text", "_code", ["_active", false], ["_tip", ""], ["_segV", ""]];
                private _b = [[_pad + (_forEachIndex mod _per) * _sw, _y + (floor (_forEachIndex / _per)) * (_rowH * 0.9 + _pad / 4), _sw - _pad / 6, _rowH * 0.9], _text] call _flat;
                if (_tip isNotEqualTo "") then { _b ctrlSetTooltip ((_tip) call comspec_atak_native_fnc_tr); };
                _b ctrlSetFontHeight (_fs * 0.9);
                [_b, _sw - _pad / 6, _fs * 0.9] call _fit;
                // Option non choisie mais disponible : fond clair-sombre et texte blanc (ne doit pas sembler désactivée).
                if (_active) then { [_b, _acc] call _face; _b ctrlSetTextColor (_th get "onAccent"); } else { [_b, _th get "surface2"] call _face; _b ctrlSetTextColor (_th get "text"); };
                [_b, _code] call _click;
                _segBtns pushBack [_b, _segV];
            } forEach _opts;
            if (_segId isNotEqualTo "") then {
                private _segE = _ids getOrDefault [_segId, createHashMapFromArray [["kind", "segment"], ["btns", []]]];
                (_segE get "btns") append _segBtns;
                _ids set [_segId, _segE];
            };
            private _segRows = ceil (_n / _per) max 1;
            _y = _y + _segRows * _rowH * 0.9 + (_segRows - 1) * _pad / 4 + _pad / 2;
        };
        case "info": {
            // 4e élément (facultatif, 4.2.1) : id, pour réécrire la valeur sur place (fn_formSet « info » : distance, « il y a X s »).
            _row params ["", "_label", "_value", ["_iid", ""]];
            // Libellé à gauche (62 %, retour à la ligne), valeur à droite (38 %) : les deux colonnes ne se recouvrent plus.
            private _a = ["COMSPEC_RscStructuredText", [_pad, _y, _w * 0.62 - _pad * 0.5, _fs * 1.3]] call _mk;
            _a ctrlSetStructuredText parseText ((format ["<t size='0.85' color='" + _hTD + "'>%1</t>", _label]) call comspec_atak_native_fnc_tr);
            private _b = ["COMSPEC_RscStructuredText", [_pad + _w * 0.62, _y, _w * 0.38 - _pad * 0.5, _fs * 1.3]] call _mk;
            _b ctrlSetStructuredText parseText ((format ["<t size='0.85' align='right'>%1</t>", _value]) call comspec_atak_native_fnc_tr);
            if (_iid isEqualType "" && {_iid isNotEqualTo ""}) then { _ids set [_iid, createHashMapFromArray [["kind", "info"], ["value", _b]]]; };
            private _ih2 = ((ctrlTextHeight _a) max (ctrlTextHeight _b)) max (_fs * 1.45);
            if (_ih2 > _fs * 1.3) then {
                _a ctrlSetPosition [_pad, _y, _w * 0.62 - _pad * 0.5, _ih2]; _a ctrlCommit 0;
                _b ctrlSetPosition [_pad + _w * 0.62, _y, _w * 0.38 - _pad * 0.5, _ih2]; _b ctrlCommit 0;
            };
            // Filet discret sous chaque ligne : les fiches denses (Profil, Réseau, Debug) se lisent ligne par ligne.
            private _ln = ["COMSPEC_RscText", [_pad, _y + _ih2 - pixelH, _w, pixelH]] call _mk;
            _ln ctrlSetBackgroundColor (_th get "border");
            _y = _y + _ih2;
        };
        case "line": {
            _row params ["", "_num", "_label", ["_value", ""], ["_kind", "auto"], ["_key", ""], ["_help", ""], ["_style", ""], ["_warn", false]];
            private _mono = _style isEqualTo "mono";
            private _h = _rowH * ([1.25, 1.02] select _mono);
            private _odd = (_lineIdx mod 2) isEqualTo 1;
            _lineIdx = _lineIdx + 1;
            private _bgL = ["COMSPEC_RscText", [_pad, _y, _w, _h - pixelH * 2]] call _mk;
            _bgL ctrlSetBackgroundColor ([(_th get "surfaceAlt"), (_th get "surface")] select _odd);
            // Pastille du numéro, carrée à l'écran ; sans numéro, pas de pastille (une case vide sans légende n'a pas de sens) :
            // le libellé prend la place.
            private _noNum = _num isEqualTo "";
            private _nw = [(_h * 0.7) * (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]) * (((count _num) max 2) / 2), 0] select _noNum;
            private _nb = controlNull;
            private _nt = controlNull;
            if (!_noNum) then {
                _nb = ["COMSPEC_RscText", [_pad * 1.5, _y + _h * 0.15, _nw, _h * 0.7 - pixelH * 2]] call _mk;
                _nb ctrlSetBackgroundColor [_acc select 0, _acc select 1, _acc select 2, 0.9];
                _nt = ["COMSPEC_RscStructuredText", [_pad * 1.5, _y + (_h - _fs * 1.25) / 2, _nw, _fs * 1.25]] call _mk;
                _nt ctrlSetStructuredText parseText ((format ["<t align='center' font='%2' color='" + _hOA + "'>%1</t>", _num, [(COMSPEC_ATAK_FontSet select 1), "EtelkaMonospaceProBold"] select _mono]) call comspec_atak_native_fnc_tr);
            };
            private _lx = [_pad * 2.5 + _nw, _pad * 1.5] select _noNum;
            private _lw = (_w * 0.46) - _nw - _pad;
            private _vx = _pad + _w * 0.46;
            private _vw = _w * 0.54 - _pad;
            private _isEdit = _kind in ["edit", "autoedit"];
            // Libellé (et, pour un champ, l'aide en dessous) centré verticalement.
            private _lt = ["COMSPEC_RscStructuredText", [_lx, _y, _lw, _h]] call _mk;
            _lt ctrlSetStructuredText parseText ((format ["<t color='%3' size='%4'>%1</t>%2", [_label, toUpper _label] select _mono,
                ["", format ["<br/><t size='0.72' color='" + _hTM + "'>%1</t>", _help]] select (_isEdit && {_help isNotEqualTo ""} && {!_mono}), [[_hT, _hTD] select _mono, _hAL] select _warn, [1, 0.82] select _mono]) call comspec_atak_native_fnc_tr);
            // Libellé + aide plus hauts que la ligne (texte anglais plus long, grande police, téléphone en paysage) : la
            // ligne grandit au lieu de couper l'aide sous le libellé et de chevaucher la ligne suivante (retour joueur,
            // app Comptes rendus « Enemy position / estimated grid »). Champ de saisie gardé à sa hauteur, centré.
            private _h0 = _h;
            private _need = (ctrlTextHeight _lt) + _pad / 2;
            if (_need > _h) then {
                _h = _need;
                _bgL ctrlSetPosition [_pad, _y, _w, _h - pixelH * 2];
                _bgL ctrlCommit 0;
                if (!_noNum) then {
                    _nb ctrlSetPosition [_pad * 1.5, _y + (_h - _h0 * 0.7) / 2, _nw, _h0 * 0.7 - pixelH * 2];
                    _nb ctrlCommit 0;
                    _nt ctrlSetPosition [_pad * 1.5, _y + (_h - _fs * 1.25) / 2, _nw, _fs * 1.25];
                    _nt ctrlCommit 0;
                };
            };
            private _txH = (ctrlTextHeight _lt) min _h;
            _lt ctrlSetPosition [_lx, _y + (_h - _txH) / 2, _lw, _txH];
            _lt ctrlCommit 0;
            if (_isEdit) then {
                private _e = ["COMSPEC_RscEdit", [_vx, _y + (_h - _h0 * 0.76) / 2, _vw, _h0 * 0.76], _value] call _mk;
                _e ctrlSetFontHeight _font;
                if (_mono) then { _e ctrlSetFont "EtelkaMonospacePro"; _e ctrlSetFontHeight (_font * 0.85); };
                // Proposition automatique (modifiable) : en couleur d'accent, comme une valeur calculée.
                if (_kind isEqualTo "autoedit") then { _e ctrlSetTextColor _accInk; };
                if (_help isNotEqualTo "") then { _e ctrlSetTooltip ((_help) call comspec_atak_native_fnc_tr); };
                _fields set [_key, _e];
            } else {
                private _empty = _value in ["", "-"];
                private _col = switch (true) do { case _empty: { _hTM }; case (_kind isEqualTo "warn"): { _hAL }; default { _accHex } };
                private _vt = ["COMSPEC_RscStructuredText", [_vx, _y, _vw, _h]] call _mk;
                _vt ctrlSetStructuredText parseText ((format ["<t align='right' color='%1' font='%2' size='%4'>%3</t>", _col,
                    [[(COMSPEC_ATAK_FontSet select 1), (COMSPEC_ATAK_FontSet select 0)] select _empty, ["EtelkaMonospaceProBold", (COMSPEC_ATAK_FontSet select 0)] select _empty] select _mono,
                    [_value, _help] select _empty, [1, 0.9] select _mono]) call comspec_atak_native_fnc_tr);
                private _vh = (ctrlTextHeight _vt) min _h;
                _vt ctrlSetPosition [_vx, _y + (_h - _vh) / 2, _vw, _vh];
                _vt ctrlCommit 0;
            };
            _y = _y + _h;
        };
        case "lineseg";
        case "linecombo": {
            // Ligne de fiche (même tête que "line") dont la valeur est un choix ou une liste.
            private _isCombo = (_row select 0) isEqualTo "linecombo";
            private ["_num", "_label", "_opts", "_help", "_warn", "_key", "_items", "_selData"];
            if (_isCombo) then {
                _row params ["", "_n0", "_l0", "_k0", "_i0", ["_s0", ""], ["_h0", ""], ["_w0", false]];
                _num = _n0; _label = _l0; _key = _k0; _items = _i0; _selData = _s0; _help = _h0; _warn = _w0; _opts = [];
            } else {
                _row params ["", "_n0", "_l0", "_o0", ["_h0", ""], ["_w0", false]];
                _num = _n0; _label = _l0; _opts = _o0; _help = _h0; _warn = _w0;
            };
            private _ratio = uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW];
            private _h = _rowH * 1.25;
            private _chars = 0;
            { _chars = _chars + (count (_x select 0)); } forEach _opts;
            private _wide = !_isCombo && {(count _opts) > 3 || {_chars > 26}};
            private _per = 5;
            private _nRows = [1, ceil ((count _opts) / _per)] select _wide;
            private _bh = _rowH * 0.86;
            private _hHead = [_h, _h * 0.8] select _wide;
            private _tot = [_h, _hHead + _nRows * (_bh + pixelH * 3) + _pad / 3] select _wide;
            private _odd = (_lineIdx mod 2) isEqualTo 1;
            _lineIdx = _lineIdx + 1;
            private _bgL = ["COMSPEC_RscText", [_pad, _y, _w, _tot - pixelH * 2]] call _mk;
            _bgL ctrlSetBackgroundColor ([(_th get "surfaceAlt"), (_th get "surface")] select _odd);
            private _nw = (_hHead * 0.7) * _ratio * (((count _num) max 2) / 2);
            private _nb = ["COMSPEC_RscText", [_pad * 1.5, _y + _hHead * 0.15, _nw, _hHead * 0.7 - pixelH * 2]] call _mk;
            _nb ctrlSetBackgroundColor [_acc select 0, _acc select 1, _acc select 2, [0.9, 0.25] select (_num isEqualTo "")];
            private _nt = ["COMSPEC_RscStructuredText", [_pad * 1.5, _y + (_hHead - _fs * 1.25) / 2, _nw, _fs * 1.25]] call _mk;
            _nt ctrlSetStructuredText parseText ((format ["<t align='center' font='%2' color='" + _hOA + "'>%1</t>", _num, COMSPEC_ATAK_FontSet select 1]) call comspec_atak_native_fnc_tr);
            // Sans numéro : pas de pastille (elle restait en carré pâle sans légende, app Zones « État de la zone »).
            if (_num isEqualTo "") then { _nb ctrlShow false; _nt ctrlShow false; _nw = 0; };
            private _lx = [_pad * 2.5 + _nw, _pad * 1.5] select (_num isEqualTo "");
            private _lw = [(_w * 0.46) - _nw - _pad, _w - _nw - _pad * 2.5] select _wide;
            private _vx = _pad + _w * 0.46;
            private _vw = _w * 0.54 - _pad;
            private _lt = ["COMSPEC_RscStructuredText", [_lx, _y, _lw, _hHead]] call _mk;
            _lt ctrlSetStructuredText parseText ((format ["<t color='%3'>%1</t>%2", _label,
                ["", format [["<br/><t size='0.72' color='" + _hTM + "'>%1</t>", "  <t size='0.72' color='" + _hTM + "'>%1</t>"] select _wide, _help]] select (_help isNotEqualTo ""),
                [_hT, _hAL] select _warn]) call comspec_atak_native_fnc_tr);
            // Même chose que "line" : libellé + aide plus hauts que la tête, la ligne grandit (choix et liste restent à
            // leur hauteur, centrés ; en mode large, les boutons descendent sous la tête).
            private _off = 0;
            private _need = (ctrlTextHeight _lt) + _pad / 2;
            if (_need > _hHead) then {
                private _hHead0 = _hHead;
                private _grow = _need - _hHead;
                if (!_wide) then { _off = _grow / 2; };
                _hHead = _need;
                _tot = _tot + _grow;
                _bgL ctrlSetPosition [_pad, _y, _w, _tot - pixelH * 2];
                _bgL ctrlCommit 0;
                _nb ctrlSetPosition [_pad * 1.5, _y + (_hHead - _hHead0 * 0.7) / 2, _nw, _hHead0 * 0.7 - pixelH * 2];
                _nb ctrlCommit 0;
                _nt ctrlSetPosition [_pad * 1.5, _y + (_hHead - _fs * 1.25) / 2, _nw, _fs * 1.25];
                _nt ctrlCommit 0;
            };
            private _txH = (ctrlTextHeight _lt) min _hHead;
            _lt ctrlSetPosition [_lx, _y + (_hHead - _txH) / 2, _lw, _txH];
            _lt ctrlCommit 0;
            if (_isCombo) then {
                private _c = ["COMSPEC_RscCombo", [_vx, _y + _off + _h * 0.14, _vw, _h * 0.72]] call _mk;
                _c ctrlSetFontHeight _font;
                private _sel = 0;
                {
                    _x params ["_text", "_data"];
                    private _i = _c lbAdd ((_text) call comspec_atak_native_fnc_tr);
                    _c lbSetData [_i, _data];
                    if (_data isEqualTo _selData) then { _sel = _i; };
                } forEach _items;
                _c lbSetCurSel _sel;
                if (_help isNotEqualTo "") then { _c ctrlSetTooltip ((_help) call comspec_atak_native_fnc_tr); };
                _fields set [_key, _c];
            } else {
                private _n = count _opts;
                private _cols = [_n, _per min _n] select _wide;
                private _ax = [_vx, _lx] select _wide;
                private _aw = [_vw, _pad + _w - _lx - _pad * 0.5] select _wide;
                private _sw = _aw / (_cols max 1);
                {
                    _x params ["_text", "_code", ["_active", false], ["_col", []]];
                    private _r = floor (_forEachIndex / (_cols max 1));
                    private _bx = _ax + (_forEachIndex mod (_cols max 1)) * _sw;
                    private _by = [_y + _off + _h * 0.14, _y + _hHead + _r * (_bh + pixelH * 3)] select _wide;
                    private _b = [[_bx, _by, _sw - _pad / 6, [_h * 0.72, _bh] select _wide], _text] call _flat;
                    _b ctrlSetFontHeight ((_fs * 0.82) max COMSPEC_ATAK_FontMin);
                    private _has = (count _col) isEqualTo 4;
                    if (_active) then {
                        [_b, [_acc, _col] select _has] call _face;
                        _b ctrlSetTextColor ([(_th get "onAccent"), [1, 1, 1, 1]] select (_has && {((_col select 0) + (_col select 1) + (_col select 2)) < 0.9}));
                    } else {
                        [_b, _th get "surface2"] call _face;
                        _b ctrlSetTextColor ([(_th get "text"), [((_col select 0) * 0.6 + 0.4) min 1, ((_col select 1) * 0.6 + 0.4) min 1, ((_col select 2) * 0.6 + 0.4) min 1, 1]] select _has);
                    };
                    if (_help isNotEqualTo "") then { _b ctrlSetTooltip ((_help) call comspec_atak_native_fnc_tr); };
                    [_b, _code] call _click;
                } forEach _opts;
            };
            _y = _y + _tot;
        };
        case "notif": {
            _row params ["", "_icon", "_rgb", "_title", ["_meta", ""], ["_body", ""], ["_open", {}], ["_close", {}], ["_tip", ""], ["_fresh", false]];
            private _ratio = uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW];
            private _e = { params ["_v"]; (_v regexReplace ["&", "&amp;"]) regexReplace ["<", "&lt;"] };
            private _hasClose = _close isNotEqualTo {};
            private _ih = _font * 1.75;
            private _iw = _ih * _ratio;
            private _cbw = [0, _font * 1.6 * _ratio] select _hasClose;
            private _card = ["COMSPEC_RscText", [_pad, _y, _w, _rowH]] call _mk;
            // Non lue : carte un cran plus claire, le reste du texte ne change pas.
            _card ctrlSetBackgroundColor (_th get (["surface", "surface2"] select _fresh));
            private _strip = ["COMSPEC_RscText", [_pad, _y, pixelW * 4, _rowH]] call _mk;
            _strip ctrlSetBackgroundColor _rgb;
            private _tx = _pad * 2.2 + _iw;
            private _tw = (_w - _iw - _cbw - _pad * 2.6) max (_w * 0.3);
            private _t = ["COMSPEC_RscStructuredText", [_tx, _y + _pad / 3, _tw, _rowH]] call _mk;
            _t ctrlSetStructuredText parseText ((format ["<t font='COMSPEC_BarlowBold' color='" + _hT + "'>%1</t><t size='0.75' align='right' color='" + _hTD + "'>%2</t>%3",
                [_title] call _e, _meta, ["", format ["<br/><t size='0.9' color='" + _hT + "'>%1</t>", [_body] call _e]] select (_body isNotEqualTo "")]) call comspec_atak_native_fnc_tr);
            private _h = ((ctrlTextHeight _t) + _pad * 0.8) max (_ih + _pad * 0.8);
            _t ctrlSetPosition [_tx, _y + _pad * 0.4, _tw, _h - _pad * 0.8];
            _t ctrlCommit 0;
            { _x ctrlSetPosition [ctrlPosition _x select 0, _y, ctrlPosition _x select 2, _h]; _x ctrlCommit 0; } forEach [_card, _strip];
            // Pastille d'icône : carré teinté à la couleur de gravité, icône blanche.
            private _iy = _y + (_h - _ih) / 2;
            private _tile = ["COMSPEC_RscText", [_pad * 1.6, _iy, _iw, _ih]] call _mk;
            _tile ctrlSetBackgroundColor [_rgb select 0, _rgb select 1, _rgb select 2, 0.22];
            private _p = ["COMSPEC_RscPicture", [_pad * 1.6 + _iw * 0.17, _iy + _ih * 0.17, _iw * 0.66, _ih * 0.66], _icon] call _mk;
            _p ctrlSetTextColor (_th get "text");
            if (_open isNotEqualTo {}) then {
                private _b = ["COMSPEC_RscButtonOverlay", [_pad, _y, _w - _cbw, _h]] call _mk;
                if (_tip isNotEqualTo "") then { _b ctrlSetTooltip ((_tip) call comspec_atak_native_fnc_tr); };
                [_b, _open] call _click;
            };
            if (_hasClose) then {
                private _cx = _pad + _w - _cbw;
                private _cs = _font * 0.9;
                private _ci = ["COMSPEC_RscPicture", [_cx + (_cbw - _cs * _ratio) / 2, _y + (_h - _cs) / 2, _cs * _ratio, _cs], "\z\comspec_atak_native\addons\main\data\ui_close.paa"] call _mk;
                _ci ctrlSetTextColor (_th get "textDim");
                private _cb = ["COMSPEC_RscButtonOverlay", [_cx, _y, _cbw, _h]] call _mk;
                _cb ctrlSetTooltip (("Effacer cette notification") call comspec_atak_native_fnc_tr);
                [_cb, _close] call _click;
            };
            _y = _y + _h + _pad / 3;
        };
        case "cells": {
            _row params ["", ["_cells", []], ["_cols", 2]];
            private _ratio = uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW];
            private _e = { params ["_v"]; (_v regexReplace ["&", "&amp;"]) regexReplace ["<", "&lt;"] };
            private _gap = _pad / 2;
            private _cw = (_w - _gap * (_cols - 1)) / (_cols max 1);
            private _ih = _font * 1.6;
            private _iw = _ih * _ratio;
            for "_r" from 0 to ((count _cells) - 1) step _cols do {
                private _made = [];
                private _rh = _ih + _pad * 0.8;
                {
                    _x params ["_icon", "_rgb", "_title", ["_meta", ""], ["_body", ""]];
                    private _cx = _pad + _forEachIndex * (_cw + _gap);
                    private _card = ["COMSPEC_RscText", [_cx, _y, _cw, _rowH]] call _mk;
                    _card ctrlSetBackgroundColor (_th get "surface");
                    private _strip = ["COMSPEC_RscText", [_cx, _y, pixelW * 4, _rowH]] call _mk;
                    _strip ctrlSetBackgroundColor _rgb;
                    private _tile = ["COMSPEC_RscText", [_cx + _pad * 0.6, _y + _pad * 0.4, _iw, _ih]] call _mk;
                    _tile ctrlSetBackgroundColor [_rgb select 0, _rgb select 1, _rgb select 2, 0.22];
                    private _p = ["COMSPEC_RscPicture", [_cx + _pad * 0.6 + _iw * 0.17, _y + _pad * 0.4 + _ih * 0.17, _iw * 0.66, _ih * 0.66], _icon] call _mk;
                    _p ctrlSetTextColor (_th get "text");
                    private _tx = _cx + _pad * 1.2 + _iw;
                    private _tw = _cw - _iw - _pad * 1.6;
                    private _t = ["COMSPEC_RscStructuredText", [_tx, _y + _pad * 0.3, _tw, _rowH]] call _mk;
                    _t ctrlSetStructuredText parseText ((format ["<t font='COMSPEC_BarlowBold' color='" + _hT + "'>%1</t><t size='0.75' align='right'>%2</t>%3",
                        [_title] call _e, _meta, ["", format ["<br/><t size='0.82' color='" + _hTD + "'>%1</t>", [_body] call _e]] select (_body isNotEqualTo "")]) call comspec_atak_native_fnc_tr);
                    private _txH = ctrlTextHeight _t;
                    _rh = _rh max (_txH + _pad * 0.6);
                    _made pushBack [_card, _strip, _t, _txH];
                } forEach (_cells select [_r, _cols]);
                {
                    _x params ["_card", "_strip", "_t", "_txH"];
                    { _x ctrlSetPositionH _rh; _x ctrlCommit 0; } forEach [_card, _strip];
                    _t ctrlSetPositionH _txH; _t ctrlCommit 0;
                } forEach _made;
                _y = _y + _rh + _gap;
            };
        };
        case "empty": {
            // 5e paramètre (facultatif) : [libellé, code] d'un bouton d'action sous l'explication (un écran vide invite à agir).
            _row params ["", "_icon", "_title", ["_sub", ""], ["_action", []]];
            private _ratio = uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW];
            _y = _y + _pad * 2;
            private _is = _font * 3.2;
            private _p = ["COMSPEC_RscPicture", [_pad + (_w - _is * _ratio) / 2, _y, _is * _ratio, _is], _icon] call _mk;
            _p ctrlSetTextColor (_th get "off");
            _y = _y + _is + _pad;
            private _t = ["COMSPEC_RscStructuredText", [_pad, _y, _w, _rowH]] call _mk;
            _t ctrlSetStructuredText parseText ((format ["<t align='center' font='COMSPEC_BarlowBold' color='" + _hT + "'>%1</t>%2", _title,
                ["", format ["<br/><t align='center' size='0.85' color='" + _hTD + "'>%1</t>", _sub]] select (_sub isNotEqualTo "")]) call comspec_atak_native_fnc_tr);
            private _h = (ctrlTextHeight _t) max _rowH;
            _t ctrlSetPosition [_pad, _y, _w, _h];
            _t ctrlCommit 0;
            _y = _y + _h + _pad;
            if ((count _action) >= 2) then {
                private _aw = (_w * 0.6) min (_font * 12 * _ratioG);
                private _ab = ["COMSPEC_RscButtonPrimary", [_pad + (_w - _aw) / 2, _y, _aw, _rowH], _action select 0] call _mk;
                _ab ctrlSetFontHeight _btnFs;
                [_ab, _aw, _btnFs] call _fit;
                [_ab, _action select 1] call _click;
                _y = _y + _rowH + _pad;
            };
            _y = _y + _pad;
        };
        case "status": {
            // Carte d'état : la situation en toutes lettres, sans code ni jargon (app Athena).
            _row params ["", "_pic", ["_col", (_th get "ok")], ["_title", ""], ["_txt", ""], ["_act", ""], ["_open", {}]];
            private _ratio = uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW];
            private _ih = _font * 2.6;
            private _iw = _ih * _ratio;
            private _ip = _pad * 0.9;
            private _card = ["COMSPEC_RscText", [_pad, _y, _w, _ih + _ip * 2]] call _mk;
            _card ctrlSetBackgroundColor (_th get "surface");
            private _bar = ["COMSPEC_RscText", [_pad, _y, pixelW * 6, _ih + _ip * 2]] call _mk;
            _bar ctrlSetBackgroundColor _col;
            // Photo Athena téléchargée : logo dessous, comme la ligne "hero", si le fichier ne se charge pas.
            if (_pic isNotEqualTo "" && {(_pic select [0, 1]) isNotEqualTo "\"}) then {
                ["COMSPEC_RscPicture", [_pad + _ip, _y + _ip, _iw, _ih], "\z\comspec_atak_native\addons\main\data\logo_athena.paa"] call _mk;
            };
            if (_pic isNotEqualTo "") then { (["COMSPEC_RscPicture", [_pad + _ip, _y + _ip, _iw, _ih], _pic] call _mk) ctrlSetTextColor ([_pic] call _picTint); };
            // Titre de la couleur de l'état : couleurs vives faites pour un fond sombre, assombries sur le fond clair du thème jour
            // (gris 0,6 sur blanc : contraste 2,4, illisible).
            if !(_th get "dark") then { _col = [(_col select 0) * 0.55, (_col select 1) * 0.55, (_col select 2) * 0.55, 1]; };
            private _hex = (_col select [0, 3]) apply { private _v = round ((_x max 0 min 1) * 255); (["0123456789abcdef" select [floor (_v / 16), 1], "0123456789abcdef" select [_v mod 16, 1]]) joinString "" };
            private _tx = _pad + _ip * 2 + _iw;
            private _tw = _w - _iw - _ip * 3;
            private _t = ["COMSPEC_RscStructuredText", [_tx, _y + _ip, _tw, _ih]] call _mk;
            _t ctrlSetStructuredText parseText ((format ["<t size='1.12' font='COMSPEC_BarlowBold' color='#%1'>%2</t>%3%4", _hex joinString "", _title,
                ["", format ["<br/><t size='0.88' color='" + _hTD + "'>%1</t>", _txt]] select (_txt isNotEqualTo ""),
                ["", format ["<br/><t size='0.88' color='" + _hWA + "'>%1</t>", _act]] select (_act isNotEqualTo "")]) call comspec_atak_native_fnc_tr);
            private _txH = ctrlTextHeight _t;
            private _h = (_txH max _ih) + _ip * 2;
            _t ctrlSetPosition [_tx, _y + _ip + (((_ih - _txH) / 2) max 0), _tw, _txH];
            _t ctrlCommit 0;
            { _x ctrlSetPositionH _h; _x ctrlCommit 0; } forEach [_card, _bar];
            // Carte cliquable (8e paramètre, facultatif) : bouton invisible sur toute la carte.
            if (_open isNotEqualTo {}) then { [["COMSPEC_RscButtonInvisible", [_pad, _y, _w, _h]] call _mk, _open] call _click; };
            _y = _y + _h + _pad / 2;
        };
        case "check": {
            // Étape de la liaison : pastille (fait, en cours, à revoir, bloquant, pas encore, sans objet), libellé, valeur.
            _row params ["", ["_lvl", "todo"], ["_label", ""], ["_value", ""], ["_help", ""]];
            private _ratio = uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW];
            ([
                ["ok", "ui_check", (_th get "ok"), _hOK],
                ["now", "ui_repeat", (_th get "warn"), _hWA],
                ["warn", "ui_dot", (_th get "warn"), _hWA],
                ["ko", "ui_close", (_th get "alert"), _hAL],
                ["off", "ui_minus", (_th get "off"), _hTD]
            ] select { (_x select 0) isEqualTo _lvl }) params [["_def", ["todo", "ui_dot", (_th get "off"), _hTD]]];
            _def params ["", "_icon", "_icol", "_vhex"];
            private _is = _fs * 1.15;
            private _isw = _is * _ratio;
            private _lx = _pad * 1.6 + _isw;
            private _lw = _w * 0.42;
            private _vx = _lx + _lw;
            private _vw = _pad + _w - _vx - _pad * 0.5;
            private _a = ["COMSPEC_RscStructuredText", [_lx, _y, _lw, _fs * 1.3]] call _mk;
            _a ctrlSetStructuredText parseText ((format ["<t size='0.9' color='" + _hT + "'>%1</t>", _label]) call comspec_atak_native_fnc_tr);
            private _b = ["COMSPEC_RscStructuredText", [_vx, _y, _vw, _fs * 1.3]] call _mk;
            _b ctrlSetStructuredText parseText ((format ["<t size='0.9' align='right' color='%1'>%2</t>", _vhex, _value]) call comspec_atak_native_fnc_tr);
            private _rh = ((ctrlTextHeight _a) max (ctrlTextHeight _b)) max (_fs * 1.3);
            { _x ctrlSetPositionH _rh; _x ctrlCommit 0; } forEach [_a, _b];
            private _p = ["COMSPEC_RscPicture", [_pad * 0.9, _y + (((_fs * 1.3) - _is) / 2), _isw, _is], format ["\z\comspec_atak_native\addons\main\data\%1.paa", _icon]] call _mk;
            _p ctrlSetTextColor _icol;
            _y = _y + _rh;
            if (_help isNotEqualTo "") then {
                private _hc = ["COMSPEC_RscStructuredText", [_lx, _y, _w - _lx + _pad * 0.5, _fs]] call _mk;
                _hc ctrlSetStructuredText parseText ((format ["<t size='0.78' color='" + _hTD + "'>%1</t>", _help]) call comspec_atak_native_fnc_tr);
                private _hh = ctrlTextHeight _hc;
                _hc ctrlSetPositionH _hh; _hc ctrlCommit 0;
                _y = _y + _hh;
            };
            // Filet entre les étapes.
            private _ln = ["COMSPEC_RscText", [_lx, _y + pixelH * 2, _w - _lx + _pad * 0.5, pixelH]] call _mk;
            _ln ctrlSetBackgroundColor [1, 1, 1, 0.07];
            _y = _y + pixelH * 6;
        };
        case "meter": {
            // Ligne à jauge (voisins du maillage, relais) : nom en gras, pourcentage et barre à droite, détail dessous, étiquettes
            // (ce qui limite le lien) en ambre à droite du détail. Carte cliquable si un code est donné ; choisie : liseré d'accent.
            _row params ["", ["_mTitle", ""], ["_mSub", ""], ["_mPct", 0], ["_mTags", []], ["_mCode", {}], ["_mSel", false]];
            private _mCol = [(_th get "alert"), (_th get "warn"), (_th get "ok")] select (([0, 1] select (_mPct >= 30)) + ([0, 1] select (_mPct >= 60)));
            private _mHex = [_hAL, _hWA, _hOK] select (([0, 1] select (_mPct >= 30)) + ([0, 1] select (_mPct >= 60)));
            private _mH = _fs * 2.75;
            private _mCard = ["COMSPEC_RscText", [_pad, _y, _w, _mH]] call _mk;
            _mCard ctrlSetBackgroundColor ([(_th get "surface"), (_th get "surfaceHi")] select _mSel);
            if (_mSel) then { (["COMSPEC_RscText", [_pad, _y, pixelW * 6, _mH]] call _mk) ctrlSetBackgroundColor _acc; };
            private _mPw = _fs * 3.2 * pixelW / pixelH;
            private _mBw = (_w * 0.26) min (_fs * 9 * pixelW / pixelH);
            private _mT = ["COMSPEC_RscStructuredText", [_pad * 1.8, _y + _fs * 0.15, _w - _pad * 1.6, _fs * 1.3]] call _mk;
            _mT ctrlSetStructuredText parseText ((format ["<t font='COMSPEC_BarlowBold' color='" + _hT + "'>%1</t><t align='right' font='COMSPEC_BarlowBold' color='%2'>%3</t>", _mTitle, _mHex,
                [format ["%1 %%", _mPct], "-"] select (_mPct < 0)]) call comspec_atak_native_fnc_tr);
            if (_mPct >= 0) then {
                private _mBx = _pad + _w - _pad * 0.8 - _mPw - _mBw;
                (["COMSPEC_RscText", [_mBx, _y + _fs * 0.62, _mBw, pixelH * 5]] call _mk) ctrlSetBackgroundColor (_th get "surface2");
                (["COMSPEC_RscText", [_mBx, _y + _fs * 0.62, _mBw * ((_mPct max 2) min 100) / 100, pixelH * 5]] call _mk) ctrlSetBackgroundColor _mCol;
            };
            private _mS = ["COMSPEC_RscStructuredText", [_pad * 1.8, _y + _fs * 1.42, _w - _pad * 1.6, _fs * 1.2]] call _mk;
            _mS ctrlSetStructuredText parseText ((format ["<t size='0.82' color='" + _hTD + "'>%1</t>%2", _mSub,
                ["", format ["<t align='right' size='0.74' font='COMSPEC_BarlowBold' color='" + _hWA + "'>%1</t>", (_mTags apply { toUpper (_x call comspec_atak_native_fnc_tr) }) joinString " · "]] select (_mTags isNotEqualTo [])]) call comspec_atak_native_fnc_tr);
            if (_mCode isNotEqualTo {}) then { [["COMSPEC_RscButtonInvisible", [_pad, _y, _w, _mH]] call _mk, _mCode] call _click; };
            _y = _y + _mH + pixelH * 3;
        };
        case "graph": {
            // Graphe du réseau (fn_meshGraphView "draw") : même mise en page et même dessin que la fenêtre de Zeus et le TOC.
            _row params ["", ["_gModel", createHashMap], ["_gOpt", createHashMap], ["_gRows", 5]];
            private _gH = _rowH * _gRows;
            _gOpt set ["fs", _fs];
            ["draw", [_d, _grp, [_pad, _y, _w, _gH], _gModel, _gOpt]] call comspec_atak_native_fnc_meshGraphView;
            _y = _y + _gH + _pad / 2;
        };
        case "gap": { _y = _y + _pad; };
        case "person": {
            // 6e paramètre : lettre montrée dans une pastille teintée (_picColor) quand il n'y a pas d'image (_pic "") :
            // joueur sans photo lisible par Arma.
            // 7e paramètre : identifiant, pour changer texte et teinte de l'image sur place (fn_formSet, lignes de piste de l'app Musique).
            // 8e paramètre : couleur du liseré d'état à gauche (système de design §1 règle 2), [] : pas de liseré.
            _row params ["", "_pic", "_text", ["_btns", []], ["_picColor", [1, 1, 1, 1]], ["_ini", ""], ["_pid", ""], ["_edge", []]];
            private _picCtl = controlNull;
            private _ih = _font * 2.2;
            private _iw = _ih * (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]);
            private _bg = ["COMSPEC_RscText", [_pad, _y, _w, _ih + _pad / 2]] call _mk;
            _bg ctrlSetBackgroundColor (_th get "surface");
            if (_pic isEqualTo "" && {_ini isNotEqualTo ""}) then {
                private _pc = ["COMSPEC_RscTextCenter", [_pad * 1.3, _y + _pad / 4, _iw, _ih], ""] call _mk;
                _pc ctrlSetBackgroundColor [(_picColor select 0) * 0.28, (_picColor select 1) * 0.28, (_picColor select 2) * 0.28, 1];
                _pc ctrlSetTextColor _picColor;
                _pc ctrlSetFontHeight (_ih * 0.6);
                _pc ctrlSetText _ini; // i18n:brut
            } else {
                private _p = ["COMSPEC_RscPicture", [_pad * 1.3, _y + _pad / 4, _iw, _ih], _pic] call _mk;
                _p ctrlSetTextColor _picColor;
                _picCtl = _p;
            };
            private _nb = count _btns;
            // Trois boutons ou plus (DÉTAILS, ENVOYER, SUPPRIMER) : plus étroits pour laisser de la place au texte.
            private _bwid = _w * ([0.2, 0.14] select (_nb >= 3));
            // Jamais de largeur nulle ou négative (même famille d'arrêt moteur que plus haut).
            private _tw = (_w - _iw - _pad * 1.5 - _nb * (_bwid + _pad / 3)) max (_w * 0.2);
            private _t = ["COMSPEC_RscStructuredText", [_pad * 2 + _iw, _y + _pad / 4, _tw, _ih]] call _mk;
            _t ctrlSetStructuredText parseText ((_text) call comspec_atak_native_fnc_tr);
            // Texte long (fil OSINT, messages) : la ligne s'agrandit au lieu de couper le texte.
            private _rowIh = _ih max ((ctrlTextHeight _t) + _pad / 4);
            if (_rowIh > _ih) then {
                _t ctrlSetPosition [_pad * 2 + _iw, _y + _pad / 4, _tw, _rowIh]; _t ctrlCommit 0;
                _bg ctrlSetPosition [_pad, _y, _w, _rowIh + _pad / 2]; _bg ctrlCommit 0;
            };
            if ((count _edge) isEqualTo 4) then {
                private _rim = ["COMSPEC_RscText", [_pad, _y, pixelW * 4, _rowIh + _pad / 2]] call _mk;
                _rim ctrlSetBackgroundColor _edge;
            };
            if (_pid isNotEqualTo "") then { _ids set [_pid, createHashMapFromArray [["kind", "person"], ["text", _t], ["pic", _picCtl], ["btns", []], ["bg", _bg]]]; };
            {
                _x params ["_label", "_code", ["_primary", false], ["_enabled", true], ["_tip", ""]];
                // « red » à la place de principal : bouton de confirmation (second appui pour supprimer).
                private _red = _primary isEqualTo "red";
                if !(_primary isEqualType true) then { _primary = false; };
                private _bpos = [_pad + _w - (_nb - _forEachIndex) * (_bwid + _pad / 3), _y + _pad / 4 + _ih * 0.15, _bwid, _ih * 0.7];
                private _b = if (_primary) then { ["COMSPEC_RscButtonPrimary", _bpos, _label] call _mk } else { [_bpos, _label] call _flat };
                if (_tip isNotEqualTo "") then { _b ctrlSetTooltip ((_tip) call comspec_atak_native_fnc_tr); };
                _b ctrlSetFontHeight ((_fs * 0.85) max COMSPEC_ATAK_FontMin);
                // Bouton secondaire au même gris-vert que les barres et onglets (le bleu par défaut détonnait).
                if !(_primary) then { [_b, _th get "surface2"] call _face; _b ctrlSetTextColor ([(_th get "off"), (_th get "text")] select _enabled); };
                if (_red) then { [_b, _th get "alert"] call _face; _b ctrlSetTextColor [1, 1, 1, 1]; };
                _b ctrlEnable _enabled;
                if (_pid isNotEqualTo "") then { ((_ids get _pid) get "btns") pushBack _b; };
                [_b, _code] call _click;
            } forEach _btns;
            _y = _y + _rowIh + _pad * 0.75;
        };
        case "hero": {
            _row params ["", "_pic", "_text"];
            private _ih = _font * 3.2;
            private _iw = _ih * (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]); // carré à l'écran
            // Colonne de texte trop étroite à côté de l'image (téléphone en portrait, miniature) : image centrée au-dessus, texte sur
            // toute la largeur. Avant, une à deux syllabes par ligne et des mots coupés au milieu (« detonato r », « Jamm er »).
            if ((_w - _iw - _pad) < (_font * 11 * _ratioG)) exitWith {
                private _p = ["COMSPEC_RscPicture", [_pad + (_w - _iw) / 2, _y, _iw, _ih], _pic] call _mk;
                _p ctrlSetTextColor ([_pic] call _picTint);
                _y = _y + _ih + _pad / 2;
                private _t = ["COMSPEC_RscStructuredText", [_pad, _y, _w, _ih]] call _mk;
                _t ctrlSetStructuredText parseText ((_text) call comspec_atak_native_fnc_tr);
                private _h = ctrlTextHeight _t;
                _t ctrlSetPosition [_pad, _y, _w, _h];
                _t ctrlCommit 0;
                _y = _y + _h + _pad;
            };
            // Image hors addon (photo Athena téléchargée sur le disque) : le logo ATAK reste dessous. Si Arma ne peut pas
            // charger le fichier, le logo reste visible au lieu d'un cadre vide (le logo « disparaissait » après connexion).
            if (_pic isNotEqualTo "" && {(_pic select [0, 1]) isNotEqualTo "\"}) then {
                private _under = ["COMSPEC_RscPicture", [_pad, _y, _iw, _ih], "\z\comspec_atak_native\addons\main\data\logo_atak.paa"] call _mk;
                _under ctrlSetTextColor [1, 1, 1, 1];
            };
            private _p = ["COMSPEC_RscPicture", [_pad, _y, _iw, _ih], _pic] call _mk;
            _p ctrlSetTextColor ([_pic] call _picTint);
            private _t = ["COMSPEC_RscStructuredText", [_pad * 2 + _iw, _y, _w - _iw - _pad, _ih]] call _mk;
            _t ctrlSetStructuredText parseText ((_text) call comspec_atak_native_fnc_tr);
            private _h = (ctrlTextHeight _t) max _ih;
            _t ctrlSetPosition [_pad * 2 + _iw, _y, _w - _iw - _pad, _h];
            _t ctrlCommit 0;
            _y = _y + _h + _pad;
        };
        case "shapes": {
            _row params ["", "_label", "_opts", ["_help", ""]];
            if (_label isNotEqualTo "") then {
                private _lb = ["COMSPEC_RscStructuredText", [_pad, _y, _w, _fs * 1.3]] call _mk;
                _lb ctrlSetStructuredText parseText ((format ["<t size='0.9'>%1</t>%2", _label, ["", format ["  <t size='0.75' color='" + _hTD + "'>%1</t>", _help]] select (_help isNotEqualTo "")]) call comspec_atak_native_fnc_tr);
                _y = _y + _fs * 1.35;
            };
            private _per = (ceil ((_w / (_fs * 5.2 * pixelW / pixelH)))) max 3 min 6;
            private _cw = _w / _per;
            private _ch = _rowH * 1.25;
            private _lh = _fs * 1.05;
            private _ratioS = uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW];
            private _icoH = _ch * 0.8;
            {
                _x params ["_text", "_code", ["_active", false], ["_tip", ""], ["_layers", []]];
                private _cx = _pad + (_forEachIndex mod _per) * _cw;
                private _cy = _y + (floor (_forEachIndex / _per)) * (_ch + _lh + _pad / 3);
                private _cwi = _cw - _pad / 4;
                private _fr = ["COMSPEC_RscText", [_cx, _cy, _cwi, _ch + _lh]] call _mk;
                _fr ctrlSetBackgroundColor ([_th get "surface2", _acc] select _active);
                private _bgP = ["COMSPEC_RscPicture", [_cx + pixelW, _cy + pixelH, _cwi - 2 * pixelW, _ch - pixelH], "\z\comspec_atak_native\addons\main\data\map_preview_bg.paa"] call _mk;
                _bgP ctrlSetTextColor [1, 1, 1, 1];
                {
                    _x params ["_tx", "_tc", ["_sc", 1]];
                    if (_tx isEqualTo "") then {
                        // Trait : barre horizontale à l'épaisseur réelle (px).
                        private _bar = ["COMSPEC_RscText", [_cx + _cwi * 0.12, _cy + (_ch - _sc * pixelH) / 2, _cwi * 0.76, _sc * pixelH]] call _mk;
                        _bar ctrlSetBackgroundColor _tc;
                    } else {
                        private _ih = _icoH * _sc;
                        private _iw = _ih / _ratioS;
                        private _pic = ["COMSPEC_RscPicture", [_cx + (_cwi - _iw) / 2, _cy + (_ch - _ih) / 2, _iw, _ih], _tx] call _mk;
                        _pic ctrlSetTextColor _tc;
                    };
                } forEach _layers;
                private _tl = ["COMSPEC_RscText", [_cx, _cy + _ch, _cwi, _lh], _text] call _mk;
                _tl ctrlSetFontHeight ((_fs * 0.72) max COMSPEC_ATAK_FontMin);
                _tl ctrlSetBackgroundColor [0, 0, 0, 0];
                _tl ctrlSetTextColor (_th get "text");
                private _o = ["COMSPEC_RscButtonOverlay", [_cx, _cy, _cwi, _ch + _lh]] call _mk;
                if (_tip isNotEqualTo "") then { _o ctrlSetTooltip ((_tip) call comspec_atak_native_fnc_tr); };
                [_o, _code] call _click;
            } forEach _opts;
            private _rowsS = ceil ((count _opts) / _per) max 1;
            _y = _y + _rowsS * (_ch + _lh + _pad / 3) + _pad / 2;
        };
        case "image": {
            // Photo en pleine largeur (format 16/9 à l'écran, proportions conservées).
            _row params ["", "_pic"];
            private _ih = _w * 0.5625 * (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]);
            private _bgI = ["COMSPEC_RscText", [_pad, _y, _w - _pad, _ih]] call _mk;
            _bgI ctrlSetBackgroundColor [0, 0, 0, 0.6];
            ["COMSPEC_RscSlide", [_pad, _y, _w - _pad, _ih], _pic] call _mk;
            _y = _y + _ih + _pad;
        };
        case "qr": {
            // QR code dessiné module par module (« Sur mon téléphone », fn_mobileQr) : carré blanc avec sa marge de 4
            // modules, une bande noire par suite de modules foncés d'une rangée (pas d'image, rien à télécharger).
            // Params : [rangées de « 0 » / « 1 », part de la largeur (0,2 à 1)]. Carré à l'écran (COMSPEC_ATAK_Ratio).
            _row params ["", ["_qrRows", []], ["_frac", 0.8]];
            private _n = count _qrRows;
            if (_n >= 21) then {
                private _ratio = uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW];
                private _side = _w * ((_frac max 0.2) min 1);
                private _m = _side / (_n + 8);
                private _mh = _m * _ratio;
                private _x0 = _pad + (_w - _side) / 2;
                private _y0 = _y;
                private _q = ["COMSPEC_RscText", [_x0, _y0, _side, _side * _ratio]] call _mk;
                _q ctrlSetBackgroundColor [1, 1, 1, 1];
                {
                    private _r = toArray _x;
                    private _ry0 = _y0 + (_forEachIndex + 4) * _mh;
                    private _i = 0;
                    private _len = count _r;
                    while { _i < _len } do {
                        if ((_r select _i) isEqualTo 49) then {
                            private _j = _i;
                            while { _j < _len && {(_r select _j) isEqualTo 49} } do { _j = _j + 1; };
                            // Bande un poil plus large et plus haute : pas de fente claire entre deux modules voisins.
                            private _b = ["COMSPEC_RscText", [_x0 + (_i + 4) * _m, _ry0, (_j - _i) * _m + pixelW * 0.5, _mh + pixelH * 0.5]] call _mk;
                            _b ctrlSetBackgroundColor [0, 0, 0, 1];
                            _i = _j;
                        } else {
                            _i = _i + 1;
                        };
                    };
                } forEach _qrRows;
                _y = _y0 + _side * _ratio + _pad;
            };
        };
        case "password": {
            // Mot de passe masqué : on garde la vraie valeur à part et on n'affiche que des points.
            _row params ["", "_key", "_label"];
            private _lb = ["COMSPEC_RscLabel", [_pad, _y, _w, _fs * 1.2], _label] call _mk;
            _lb ctrlSetFontHeight _fs;
            _y = _y + _fs * 1.2;
            private _secretVar = format ["COMSPEC_ATAK_Secret_%1", _key];
            private _cur = uiNamespace getVariable [_secretVar, ""];
            private _e = ["COMSPEC_RscEdit", [_pad, _y, _w, _rowH], (_cur splitString "") apply { "•" } joinString ""] call _mk;
            _e ctrlSetFontHeight _font;
            _e setVariable ["secretVar", _secretVar];
            private _redraw = {
                params ["_c"];
                [{ params ["_c"]; if (!isNull _c) then { _c ctrlSetText (((((uiNamespace getVariable [_c getVariable "secretVar", ""]) splitString "") apply { "•" } joinString "")) call comspec_atak_native_fnc_tr); }; }, [_c]] call comspec_atak_native_fnc_nextFrame;
            };
            _e setVariable ["redraw", _redraw];
            _e ctrlAddEventHandler ["Char", {
                params ["_c", "_char"];
                private _v = _c getVariable "secretVar";
                uiNamespace setVariable [_v, (uiNamespace getVariable [_v, ""]) + toString [_char]];
                [_c] call (_c getVariable "redraw");
            }];
            _e ctrlAddEventHandler ["KeyDown", {
                params ["_c", "_key"];
                private _v = _c getVariable "secretVar";
                private _cur = uiNamespace getVariable [_v, ""];
                if (_key isEqualTo 14) then { uiNamespace setVariable [_v, _cur select [0, ((count _cur) - 1) max 0]]; [_c] call (_c getVariable "redraw"); };
                if (_key isEqualTo 211) then { uiNamespace setVariable [_v, ""]; [_c] call (_c getVariable "redraw"); };
                false
            }];
            _fields set [_key, _e];
            _y = _y + _rowH + _pad / 2;
        };
        case "buttons": {
            private _btns = _row select 1;
            private _n = count _btns;
            // 3e élément : identifiant, pour masquer ou montrer la rangée sur place (fn_formSet « btnrow »).
            private _rowBtns = [];
            private _rowId = _row param [2, ""];
            // 4e élément : hauteur minimale des boutons (jeton tap, fiche unique fn_unitCard).
            private _bRowH = _rowH max (_row param [3, 0]);
            if (_rowId isEqualType "" && {_rowId isNotEqualTo ""}) then { _ids set [_rowId, createHashMapFromArray [["kind", "btnrow"], ["btns", _rowBtns]]]; };
            // Comme « segment » : colonne étroite, les boutons dont le libellé ne tiendrait pas passent à la rangée suivante.
            private _minF = COMSPEC_ATAK_FontMin max (_fs * 0.6);
            private _longest = 0;
            { _longest = _longest max (count ((((_x select 0) call comspec_atak_native_fnc_tr) splitString ""))); } forEach _btns;
            private _needW = _longest * _minF * 0.55 * pixelW / pixelH;
            private _per = _n max 1;
            while { _per > 1 && {_needW > ((_w - (_per - 1) * _pad / 2) / _per) * 0.9} } do { _per = _per - 1; };
            private _bw = (_w - (_per - 1) * _pad / 2) / _per;
            {
                _x params ["_label", "_code", ["_primary", false], ["_enabled", true], ["_tip", ""]];
                // « red » à la place de principal : bouton destructif (supprimer, effacer), rouge, texte blanc.
                // « danger » : même rôle, plus discret dans une liste (texte rouge sur fond neutre ; Bloc-notes).
                private _red = _primary isEqualTo "red";
                private _danger = _primary isEqualTo "danger";
                if !(_primary isEqualType true) then { _primary = false; };
                private _bpos = [_pad + (_forEachIndex mod _per) * (_bw + _pad / 2), _y + (floor (_forEachIndex / _per)) * (_bRowH + _pad / 4), _bw, _bRowH];
                private _b = if (_primary) then { ["COMSPEC_RscButtonPrimary", _bpos, _label] call _mk } else { [_bpos, _label] call _flat };
                if (_tip isNotEqualTo "") then { _b ctrlSetTooltip ((_tip) call comspec_atak_native_fnc_tr); };
                _b ctrlSetFontHeight _btnFs;
                [_b, _bw, _btnFs] call _fit;
                if (!_primary) then { [_b, _th get "surface2"] call _face; _b ctrlSetTextColor ([(_th get "off"), (_th get "text")] select _enabled); };
                if (_red) then { [_b, _th get "alert"] call _face; _b ctrlSetTextColor [1, 1, 1, 1]; };
                if (_danger) then { _b ctrlSetTextColor (_th get "alert"); };
                _b ctrlEnable _enabled;
                _rowBtns pushBack _b;
                [_b, _code] call _click;
            } forEach _btns;
            private _bRows = ceil (_n / _per) max 1;
            _y = _y + _bRows * _bRowH + (_bRows - 1) * _pad / 4 + _pad / 2;
        };
        case "bar": {
            // Groupes titrés côte à côte (titre gris souligné de vert), passage à la ligne si les boutons deviennent
            // trop étroits pour leur libellé.
            private _groups = (_row select 1) select { (count (_x select 1)) > 0 };
            private _minW = _fs * 6.5 * (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]);
            private _perRow = (floor (_w / _minW)) max 1;
            private _lines = [];
            private _cur = [];
            private _n = 0;
            {
                private _k = count (_x select 1);
                if (_n > 0 && {_n + _k > _perRow}) then { _lines pushBack _cur; _cur = []; _n = 0; };
                _cur pushBack _x;
                _n = _n + _k;
            } forEach _groups;
            if ((count _cur) > 0) then { _lines pushBack _cur; };
            private _lh = _fs * 1.1; // titre de groupe + interligne, sans coupe
            private _bh = _rowH * 0.92;
            private _gw = _pad * 0.8;
            {
                private _line = _x;
                private _cnt = 0;
                { _cnt = _cnt + (count (_x select 1)); } forEach _line;
                private _bwid = (_w - ((count _line) - 1) * _gw) / (_cnt max 1);
                private _x0 = _pad;
                private _titled = (_line findIf { (_x select 0) isNotEqualTo "" }) >= 0;
                {
                    _x params ["_glabel", "_btns"];
                    private _span = _bwid * (count _btns);
                    if (_titled && {_glabel isNotEqualTo ""}) then {
                        private _t = ["COMSPEC_RscStructuredText", [_x0, _y, _span, _lh]] call _mk;
                        _t ctrlSetStructuredText parseText ((format ["<t size='0.72' font='COMSPEC_BarlowBold' color='" + _hTD + "'>%1</t>", toUpper _glabel]) call comspec_atak_native_fnc_tr);
                        _t ctrlEnable false;
                        private _ln = ["COMSPEC_RscText", [_x0, _y + _lh - pixelH * 3, _span - _pad / 3, pixelH * 2]] call _mk;
                        _ln ctrlSetBackgroundColor _accLine;
                    };
                    {
                        // 6e élément : identifiant, pour changer libellé et état sur place (fn_formSet, VISION de l'app Drone).
                        _x params ["_lbl", "_code", ["_state", ""], ["_tip", ""], ["_en", true], ["_bid", ""]];
                        private _bpos = [_x0 + _forEachIndex * _bwid, _y + ([0, _lh] select _titled), _bwid - _pad / 3, _bh];
                        private _b = if (_state isEqualTo "main") then { ["COMSPEC_RscButtonPrimary", _bpos, _lbl] call _mk } else { [_bpos, _lbl] call _flat };
                        _b ctrlSetFontHeight ((_fs * 0.8) max COMSPEC_ATAK_FontMin);
                        switch (_state) do {
                            case "on": { [_b, _th get "ok"] call _face; _b ctrlSetTextColor (_th get "onAccent"); };
                            case "red": { [_b, _th get "alert"] call _face; _b ctrlSetTextColor [1, 1, 1, 1]; };
                            case "main": {};
                            default { [_b, _th get "surface2"] call _face; _b ctrlSetTextColor ([(_th get "off"), (_th get "text")] select _en); };
                        };
                        if (_tip isNotEqualTo "") then { _b ctrlSetTooltip ((_tip) call comspec_atak_native_fnc_tr); };
                        _b ctrlEnable _en;
                        if (_bid isNotEqualTo "") then { _ids set [_bid, createHashMapFromArray [["kind", "btn"], ["btn", _b], ["en", _en]]]; };
                        [_b, _code] call _click;
                    } forEach _btns;
                    _x0 = _x0 + _span + _gw;
                } forEach _line;
                _y = _y + ([0, _lh] select _titled) + _bh + _pad / 2;
            } forEach _lines;
        };
    };
};
// 4.2.2 : mise en page « paysage d'abord ». Le téléphone est surtout tenu à l'horizontale ; avant, chaque app était
// une seule colonne pleine largeur (lignes de 90 caractères, grands vides, réglages et fiches les uns sous les autres).
// Quand la largeur tient deux colonnes lisibles (trois sur une tablette), d'après fn_layoutCols (le seul endroit des
// seuils, en caractères du texte courant), le formulaire se dispose de lui-même :
//   - les sections (« section » et ce qui la suit ; « fold » hors d'une section) se rangent en colonnes, chacune dans
//     la plus courte ;
//   - une suite d'au moins trois lignes de même genre (info, switch, toggle, row, person, notif, check, status,
//     slider) se range en grille, dans l'ordre de lecture (gauche à droite, puis rangée suivante) ;
//   - en-têtes, onglets, bandeaux, frises, états vides, barres de boutons groupés et gros boutons restent sur toute la
//     largeur et ferment la rangée de colonnes en cours.
// Portrait, petit téléphone, colonne étroite d'un volet : une colonne, comme avant (même dessin, mêmes hauteurs).
// Lignes de pilotage (pas dessinées) : ["flow", false] n'importe où : pas de colonnes dans ce formulaire (fil de
// messages, fiche à lire dans l'ordre) ; ["break"] : fin de la rangée de colonnes, la suite sur toute la largeur.
private _flW0 = _w;
// Carte : volets et menus déjà pensés pour le paysage (functions/map), laissés tels quels.
private _flOn = (_rows findIf { (_x param [0, ""]) isEqualTo "flow" && {!(_x param [1, true])} }) < 0
    && {((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isNotEqualTo "MAP"};
private _flBarrier = ["header", "tabs", "strip", "banner", "steps", "squad", "empty", "skeleton", "image", "qr", "bar", "big", "title", "break", "hero"];
private _flTiles = ["info", "switch", "toggle", "row", "person", "notif", "check", "status", "slider"];
// Une ligne dessinée en (_flX, _flY), largeur _flW ; renvoie le bas de la ligne.
private _flPut = {
    params ["_flR", "_flX", "_flW", "_flY"];
    _w = _flW;
    _y = _flY;
    _flMade = [];
    _flR call _renderRow;
    if (_flX isNotEqualTo 0) then {
        { _x ctrlSetPositionX ((ctrlPosition _x select 0) + _flX); _x ctrlCommit 0; } forEach _flMade;
    };
    _y
};
// Suite de lignes dans une colonne : grilles des suites de lignes de même genre quand la colonne est assez large.
private _flSeq = {
    params ["_flList", "_flX", "_flW", "_flY"];
    private _flI = 0;
    private _flN = count _flList;
    while { _flI < _flN } do {
        private _flR = _flList select _flI;
        private _flK = _flR select 0;
        private _flJ = _flI + 1;
        if (_flOn && {_flK in _flTiles}) then { while { _flJ < _flN && {((_flList select _flJ) select 0) isEqualTo _flK} } do { _flJ = _flJ + 1; }; };
        private _flRun = _flJ - _flI;
        private _flNc = if (_flRun >= 3) then { ([_flW, _flK] call comspec_atak_native_fnc_layoutCols) min _flRun } else { 1 };
        if (_flNc >= 2) then {
            private _flCw = (_flW - (_flNc - 1) * _pad) / _flNc;
            private _flRowY = _flY;
            private _flEnd = _flY;
            for "_flQ" from 0 to (_flRun - 1) do {
                if (_flQ > 0 && {(_flQ mod _flNc) isEqualTo 0}) then { _flRowY = _flEnd; };
                _flEnd = _flEnd max ([_flList select (_flI + _flQ), _flX + (_flQ mod _flNc) * (_flCw + _pad), _flCw, _flRowY] call _flPut);
            };
            _flY = _flEnd;
        } else {
            for "_flQ" from 0 to (_flRun - 1) do { _flY = [_flList select (_flI + _flQ), _flX, _flW, _flY] call _flPut; };
        };
        _flI = _flJ;
    };
    _flY
};
if (!_flOn) then {
    { if !((_x param [0, ""]) in ["flow", "break"]) then { _y = [_x, 0, _flW0, _y] call _flPut; }; } forEach _rows;
} else {
    // Rangées : lignes de tête (avant la première section), puis blocs (une section et ce qui la suit).
    private _flLead = [];
    private _flBlocks = [];
    private _flCur = [];
    private _flFlush = {
        if ((count _flCur) > 0) then { _flBlocks pushBack _flCur; _flCur = []; };
        private _flYY = [_flLead, 0, _flW0, _y] call _flSeq;
        _flLead = [];
        private _flNc = ([_flW0, "section"] call comspec_atak_native_fnc_layoutCols) min (count _flBlocks);
        if (_flNc >= 2) then {
            private _flCw = (_flW0 - (_flNc - 1) * _pad) / _flNc;
            private _flCols = [];
            for "_flQ" from 1 to _flNc do { _flCols pushBack _flYY; };
            {
                // Colonne la plus courte (la plus à gauche à égalité).
                private _flC = 0;
                { if (_x < (_flCols select _flC) - pixelH) then { _flC = _forEachIndex; }; } forEach _flCols;
                _flCols set [_flC, [_x, _flC * (_flCw + _pad), _flCw, _flCols select _flC] call _flSeq];
            } forEach _flBlocks;
            { _flYY = _flYY max _x; } forEach _flCols;
        } else {
            { _flYY = [_x, 0, _flW0, _flYY] call _flSeq; } forEach _flBlocks;
        };
        _flBlocks = [];
        _y = _flYY;
    };
    {
        private _flK = _x param [0, ""];
        switch (true) do {
            case (_flK isEqualTo "flow"): {};
            case (_flK in _flBarrier): {
                call _flFlush;
                if (_flK isNotEqualTo "break") then { _y = [_x, 0, _flW0, _y] call _flPut; };
            };
            // Section repliable : nouveau bloc seulement entre sections repliables (sous-menus des Réglages) ; dans une
            // section ou une carte (« Texte complet » d'un ordre de l'app Tâches), elle reste avec ce qui la précède.
            case (_flK isEqualTo "section" || {_flK isEqualTo "fold" && {(count _flCur) isEqualTo 0 || {((_flCur select 0) select 0) isEqualTo "fold"}}}): {
                if ((count _flCur) > 0) then { _flBlocks pushBack _flCur; };
                _flCur = [_x];
            };
            default {
                if ((count _flCur) > 0) then { _flCur pushBack _x; } else { _flLead pushBack _x; };
            };
        };
    } forEach _rows;
    call _flFlush;
};
_w = _flW0;
uiNamespace setVariable ["COMSPEC_ATAK_Form", _fields];
uiNamespace setVariable ["COMSPEC_ATAK_FormH", _y + _pad];
// Défilement gardé quand la même page se redessine (retour de jeu 2.9.27 : un clic sur un réglage ramenait la liste en
// haut). Nom : page + rang du formulaire dans la page + sous-écran déclaré + types des premières lignes (onglet choisi
// d'une ligne « segment » compris).
// Plus les titres : un titre qui change (compteur, état) faisait perdre le défilement à chaque rafraîchissement.
// Messagerie exclue (elle tient son propre défilement, fil en bas).
private _st = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _pg = _st getOrDefault ["pageDrawn", ""];
if (_pg isNotEqualTo "" && {!(_pg in ["CHAT", "SMS"])}) then {
    private _sg = uiNamespace getVariable ["COMSPEC_ATAK_ScrollGroups", []];
    private _nm = format ["form|%1|%2|%3|%4", _pg, count _sg, _st getOrDefault ["backSub", ""], str ((_rows select [0, 3]) apply { [_x param [0, ""], if ((_x param [0, ""]) isEqualTo "segment" && {(_x param [2, ""]) isEqualType []}) then { (_x select 2) apply { _x param [2, false] } } else { [] }] })];
    _sg pushBack [_nm, _grp];
    uiNamespace setVariable ["COMSPEC_ATAK_ScrollGroups", _sg];
    if ((_st getOrDefault ["pagePrev", ""]) isEqualTo _pg) then {
        private _v = ((_st getOrDefault ["scrollGroups", createHashMap]) getOrDefault [_nm, []]) param [0, -1];
        if (_v > 0) then {
            _grp ctrlSetScrollValues [_v, -1];
            // Encore à l'image suivante, quand la hauteur du contenu est connue.
            [{ params ["_g", "_v"]; if (!isNull _g) then { _g ctrlSetScrollValues [_v, -1]; }; }, [_grp, _v]] call comspec_atak_native_fnc_nextFrame;
        };
    };
};
_grp setVariable ["bg", _bg];
_grp
