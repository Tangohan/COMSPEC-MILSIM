/*
    Bouton Athena de l'ACE Arsenal et panneau « Tenues Athena ».

    Fermé par défaut ; aucune synchronisation tant qu'il n'est pas ouvert. Charte du téléphone ATAK natif (vert
    #5cc76b, fonds sombres de l'ATAK, polices COMSPEC_ATAK_FontSet). Le panneau occupe l'espace entre les deux
    colonnes de l'arsenal, lues sur les listes d'ACE (IDC 13 et 14) et sa barre du bas (IDC 10) : rien d'ACE n'est
    recouvert, quelle que soit la taille d'interface.
      en-tête    titre, sous-titre, état de la liste du site et de la copie locale, Actualiser, Fermer
      onglets    Mes tenues / Communauté / Favoris avec compteurs, pastilles « à envoyer » et « nouvelles » (cliquables)
      filtres    recherche (✕ l'efface), état de synchro, tri ; collections en pastilles (défilent avec ‹ ›)
      cartes     grille de cartes, page par page (molette ou ‹ ›) : silhouette de la tenue, nom, collection et auteur,
                 badge d'état, favori / mise en avant / obligatoire, arme, gilet, sac et casque en icônes.
                 Clic : fiche ; double-clic ou Entrée : enfiler ; clic droit : menu des actions ; flèches : parcourir. Les contrôles des cartes sont
                 créés ici une fois : la recherche ne fait que réécrire les cartes de la page (arsenalOverlayFill).
      sélection  Mes tenues : sélection multiple (cases à cocher), tout cocher (filtre appliqué), supprimer la
                 sélection en une fois, annuler la dernière suppression
      fiche      portrait, nom, collection, auteur, date, badge et phrase d'état, pastilles, pièces en icônes
                 légendées, contenu, actions Enfiler / Importer, Envoyer ou Mettre à jour / Supprimer
      pied       action groupée, tout basculer dans l'arsenal ACE (arsenalSyncAll), enregistrer la tenue portée,
                 ligne de retour (chargement, réussite, erreur) et rappel des gestes
    Champs de saisie : ACE les prend pour sa barre de recherche (ace_arsenal_leftSearchbarFocus) tant qu'ils ont le
    focus, sinon ses raccourcis partaient pendant la frappe (Retour arrière masquait l'interface au lieu d'effacer,
    Ctrl+C / Ctrl+V exportaient ou importaient). Entrée enregistre la tenue (champ du nom) ; dans la recherche, Entrée
    ou flèche bas passe aux cartes, qui se parcourent au clavier (gestionnaire KeyDown de l'écran, en fin de fichier).
    Icônes : imgrsenal du mod (aucun glyphe Unicode) ; tailles de texte avec un plancher en pixels.
    Actions : comspec_overwatch_connect_fnc_arsenalOverlayAction. Contenu : arsenalOverlayRefresh / arsenalOverlayFill.
*/
params [["_display", displayNull, [displayNull]]];
if (isNull _display) exitWith {};

{ private _c = _display getVariable [_x, controlNull]; if (!isNull _c) then { ctrlDelete _c; }; } forEach ["COMSPEC_ArsenalOverlay", "COMSPEC_ArsenalToggle"];
uiNamespace setVariable ["ace_arsenal_display", _display];

(missionNamespace getVariable ["COMSPEC_ATAK_FontSet", ["RobotoCondensed", "RobotoCondensedBold", "RobotoCondensedLight"]]) params [["_fR", "RobotoCondensed"], ["_fB", "RobotoCondensedBold"], ["_fL", "RobotoCondensedLight"]];
private _gridW = ((safeZoneW / safeZoneH) min 1.2) / 40;
private _sideW = 13 * _gridW;
private _gap = 0.008;
private _guiH = ((((safezoneW / safezoneH) min 1.2) / 1.2) / 25);
private _pxW = pixelW; private _pxH = pixelH;
// _u : unité de hauteur de l'interface ; _uw : la même longueur à l'écran, en largeur.
private _u = _guiH;
private _uw = _u * _pxW / _pxH;
// Tailles de texte : relatives à l'interface, avec un plancher en pixels (petite interface en 1080p : le plus petit
// texte reste lisible, environ 13 px de corps). Textes structurés : taille relative minimale (COMSPEC_ArsenalStMin,
// RscStructuredText vaut 0,8 unité).
private _fs = (_u * 0.8) max (20 * _pxH);
private _fsS = (_u * 0.7) max (18 * _pxH);
private _fsXS = (_u * 0.62) max (17 * _pxH);
// Icônes du panneau (img\arsenal du mod, teintées par ctrlSetTextColor ou <img color>) : pas de glyphes Unicode,
// absents de certaines polices du jeu.
private _ico = "\z\comspec_overwatch\addons\connect\img\arsenal\";
private _green = [0.36, 0.78, 0.42, 1];
private _muted = [0.58, 0.64, 0.60, 1];
private _bg2 = [0.070, 0.082, 0.072, 1];
private _line = [0.15, 0.19, 0.16, 1];

// Bouton d'ouverture : en haut, entre les deux colonnes de l'arsenal.
private _btnW = 0.16 * safezoneW;
private _btnH = _u * 1.15;
private _btnX = safeZoneX + (safeZoneW - _btnW) / 2;
private _btnY = safeZoneY + 0.008;
private _tog = _display ctrlCreate ["COMSPEC_RscButtonAtakPrimary", 884400];
_tog ctrlSetPosition [_btnX, _btnY, _btnW, _btnH];
_tog ctrlSetText (("ATHENA · TENUES") call comspec_overwatch_connect_fnc_tr);
_tog ctrlSetTooltip (("Tenues de votre organisation : importer, partager, enfiler. Rien n'est synchronisé tant que le panneau est fermé.") call comspec_overwatch_connect_fnc_tr);
_tog ctrlSetFont _fB;
_tog ctrlSetFontHeight (_u * 0.86);
_tog ctrlAddEventHandler ["ButtonClick", { ["toggle"] call comspec_overwatch_connect_fnc_arsenalOverlayAction; }];
_tog ctrlCommit 0;
_display setVariable ["COMSPEC_ArsenalToggle", _tog];

// Panneau : entre les colonnes d'ACE, lues sur ses listes (taille d'interface du joueur), sinon estimées.
private _px0 = safeZoneX + _sideW + _gap;
private _px1 = safeZoneX + safeZoneW - _sideW - _gap;
private _bottom = (safeZoneY + safeZoneH) - (_u * 2.6);
private _ll = _display displayCtrl 13;
private _rl = _display displayCtrl 14;
if (!isNull _ll && {!isNull _rl}) then {
    (ctrlPosition _ll) params ["_lx", "_ly", "_lw", "_lh"];
    (ctrlPosition _rl) params ["_rx"];
    if (_lw > 0 && {(_rx - (_lx + _lw)) > (0.3 * safeZoneW)}) then {
        _px0 = _lx + _lw + _gap;
        _px1 = _rx - _gap;
        _bottom = _ly + _lh;
        private _bar = _display displayCtrl 10;
        if (!isNull _bar) then { _bottom = _bottom min (((ctrlPosition _bar) select 1) - _gap); };
    };
};
private _w = _px1 - _px0;
private _y = _btnY + _btnH + 0.006;
private _h = _bottom - _y;
private _grp = _display ctrlCreate ["RscControlsGroupNoScrollbars", 884401];
_grp ctrlSetPosition [_px0, _y, _w, _h];
_grp ctrlShow false;
_grp ctrlCommit 0;
_display setVariable ["COMSPEC_ArsenalOverlay", _grp];
_display setVariable ["COMSPEC_ArsenalOverlayOpen", false];
_grp setVariable ["COMSPEC_ArsenalFonts", [_fR, _fB, _fL]];
_grp setVariable ["COMSPEC_ArsenalIco", _ico];
_grp setVariable ["COMSPEC_ArsenalStMin", (17 * _pxH) / (_u * 0.8)];

private _mk = {
    params ["_cls", "_pos", ["_idc", -1], ["_parent", _grp]];
    private _c = _display ctrlCreate [_cls, _idc, _parent];
    _c ctrlSetPosition _pos;
    _c ctrlCommit 0;
    _c
};
private _box = {
    params ["_pos", "_rgba", ["_parent", _grp]];
    private _c = ["RscText", _pos, -1, _parent] call _mk;
    _c ctrlSetBackgroundColor _rgba;
    _c
};
private _txt = {
    params ["_pos", "_text", ["_font", _fR], ["_size", _fsS], ["_rgba", [0.90, 0.94, 0.91, 1]], ["_bg", [0, 0, 0, 0]]];
    private _c = ["RscText", _pos] call _mk;
    _c ctrlSetFont _font;
    _c ctrlSetFontHeight _size;
    _c ctrlSetTextColor _rgba;
    _c ctrlSetBackgroundColor _bg;
    if (_text isNotEqualTo "") then { _c ctrlSetText ((_text) call comspec_overwatch_connect_fnc_tr); };
    _c
};
private _pic = { params ["_pos"]; ["RscPictureKeepAspect", _pos] call _mk };
// Bouton : classe de la charte ATAK (neutre, Primary vert, Danger rouge), texte traduit, action par variable.
private _btn = {
    params ["_pos", "_text", "_act", ["_cls", "COMSPEC_RscButtonAtak"], ["_tip", ""], ["_font", _fR], ["_size", _fsS]];
    private _b = [_cls, _pos] call _mk;
    if (_text isNotEqualTo "") then { _b ctrlSetText ((_text) call comspec_overwatch_connect_fnc_tr); };
    _b ctrlSetFont _font;
    _b ctrlSetFontHeight _size;
    if (_tip isNotEqualTo "") then { _b ctrlSetTooltip ((_tip) call comspec_overwatch_connect_fnc_tr); };
    _b setVariable ["act", _act];
    _b ctrlAddEventHandler ["ButtonClick", { params ["_c"]; [_c getVariable "act", _c getVariable ["arg", ""]] call comspec_overwatch_connect_fnc_arsenalOverlayAction; }];
    _b
};
// Bouton à icône : bouton sans texte, icône du mod posée dessus (inactive : le clic passe au bouton). L'icône est
// gardée sur le bouton (variable « ico ») pour être teintée ou masquée avec lui.
private _icoBtn = {
    params ["_pos", "_icon", "_act", ["_cls", "COMSPEC_RscButtonAtak"], ["_tip", ""], ["_tint", [0.90, 0.94, 0.91, 1]], ["_inset", 0.26]];
    private _b = [_pos, "", _act, _cls, _tip] call _btn;
    _pos params ["_bx", "_by", "_bw", "_bh"];
    private _s = _bh * (1 - 2 * _inset);
    private _sw = _s * _pxW / _pxH;
    private _p = [[_bx + (_bw - _sw) / 2, _by + (_bh - _s) / 2, _sw, _s]] call _pic;
    _p ctrlSetText (_ico + _icon); // i18n:brut
    _p ctrlSetTextColor _tint;
    _p ctrlEnable false;
    _b setVariable ["ico", _p];
    _b
};
// Focus dans un de nos champs : ACE garde ses raccourcis pour lui (voir l'en-tête).
private _typing = {
    params ["_e"];
    _e ctrlAddEventHandler ["SetFocus", { ace_arsenal_leftSearchbarFocus = true; }];
    _e ctrlAddEventHandler ["KillFocus", { ace_arsenal_leftSearchbarFocus = false; }];
};
// Champ de saisie sur fond ATAK, souligné, avec son texte d'invite (variable « ph » du champ).
private _field = {
    params ["_pos", "_idc", "_hint", "_tip", ["_lead", ""]];
    _pos params ["_fx", "_fy", "_fw", "_fh"];
    [_pos, _bg2] call _box;
    [[_fx, _fy + _fh - _pxH, _fw, _pxH], [0.20, 0.28, 0.23, 1]] call _box;
    // Icône en tête du champ (loupe de la recherche) : le texte commence après elle.
    if (_lead isNotEqualTo "") then {
        private _s = _fh * 0.5;
        private _sw = _s * _pxW / _pxH;
        private _p = [[_fx + 0.006, _fy + (_fh - _s) / 2, _sw, _s]] call _pic;
        _p ctrlSetText (_ico + _lead); // i18n:brut
        _p ctrlSetTextColor [0.45, 0.53, 0.48, 1];
        _p ctrlEnable false;
        _fx = _fx + _sw + 0.006;
        _fw = _fw - _sw - 0.006;
    };
    private _e = ["RscEdit", [_fx + 0.004, _fy, _fw - 0.008, _fh], _idc] call _mk;
    _e ctrlSetFont _fR;
    _e ctrlSetFontHeight _fsS;
    _e ctrlSetTooltip ((_tip) call comspec_overwatch_connect_fnc_tr);
    [_e] call _typing;
    private _ph = [[_fx + 0.006, _fy, _fw - 0.012, _fh], _hint, _fR, _fsS, [0.45, 0.53, 0.48, 1]] call _txt;
    _ph ctrlEnable false;
    _e setVariable ["ph", _ph];
    _e
};

private _padX = 0.5 * _uw;
private _padY = 0.4 * _u;
private _rowH = _u * 1.1;
private _iw = _w - 2 * _padX;
[[0, 0, _w, _h], [0.025, 0.030, 0.027, 0.97]] call _box;
[[0, 0, _w, 2 * _pxH], _green] call _box;

// En-tête : titre et sous-titre à gauche ; Actualiser, Fermer et l'état de la liste à droite.
private _hy = _padY;
[[_padX, _hy, _iw * 0.4, _u * 1.1], "TENUES ATHENA", _fB, _u * 1.05, [0.93, 0.97, 0.94, 1]] call _txt;
[[_padX, _hy + _u * 1.1, _iw * 0.4, _u * 0.75], "Votre arsenal et les tenues partagées", _fR, _fsXS, _muted] call _txt;
private _sq = _rowH * _pxW / _pxH;
private _refW = 4.6 * _uw;
[[_w - _padX - _sq, _hy, _sq, _rowH], "close_ca.paa", "close", "COMSPEC_RscButtonAtak", "Fermer le panneau des tenues"] call _icoBtn;
private _bReload = [[_w - _padX - _sq - _gap - _refW, _hy, _refW, _rowH], "ACTUALISER", "reload", "COMSPEC_RscButtonAtak", "Recharger la liste des tenues depuis Athena (sinon, celle gardée sur ce PC)", _fB] call _btn;
// État de la liste : entre le titre et les boutons, sur toute la hauteur de l'en-tête (coupé quand il tenait sous les boutons).
private _stX = _padX + _iw * 0.4 + _gap;
private _status = ["RscStructuredText", [_stX, _hy - _u * 0.1, (_w - _padX - _sq - _refW - 2 * _gap) - _stX - _gap, _u * 2.1]] call _mk;
_grp setVariable ["COMSPEC_ArsenalStatus", _status];
private _hb = _hy + _u * 2.0;
[[_padX, _hb, _iw, _pxH], _line] call _box;

// Onglets (soulignés en vert quand actifs) et pastilles de synchro.
private _tabY = _hb + _u * 0.3;
private _tabW = 5.6 * _uw;
private _tabs = [];
{
    _x params ["_label", "_act", "_tip"];
    private _tx = _padX + _forEachIndex * (_tabW + _gap);
    private _b = [[_tx, _tabY, _tabW, _rowH], _label, _act, "COMSPEC_RscButtonAtak", _tip, _fB] call _btn;
    private _ul = [[_tx, _tabY + _rowH - 2 * _pxH, _tabW, 2 * _pxH], _green] call _box;
    _tabs pushBack [_b, _ul];
} forEach [
    ["MES TENUES", "tabLocal", "Tenues enregistrées dans votre arsenal ACE, sur cet ordinateur"],
    ["COMMUNAUTÉ", "tabCloud", "Tenues partagées par l'organisation sur le site"],
    ["FAVORIS", "tabFav", "Tenues de l'organisation marquées en favori sur le site"]
];
_grp setVariable ["COMSPEC_ArsenalTabs", _tabs];
private _pillPend = [[_w - _padX - 0.2, _tabY + _rowH * 0.12, 0.2, _rowH * 0.76], "", "pillPending", "COMSPEC_RscButtonAtak", "Afficher les tenues de votre arsenal qui ne sont pas à jour sur le site", _fB, _fsXS] call _btn;
private _pillNew = [[_w - _padX - 0.4, _tabY + _rowH * 0.12, 0.18, _rowH * 0.76], "", "pillNew", "COMSPEC_RscButtonAtak", "Afficher les tenues partagées depuis votre dernière visite", _fB, _fsXS] call _btn;
_grp setVariable ["COMSPEC_ArsenalPills", [_pillPend, _pillNew]];

// Filtres : recherche (✕ l'efface), état, tri.
private _fy = _tabY + _rowH + _u * 0.35;
private _stateW = 6.4 * _uw;
private _sortW = 5.2 * _uw;
private _searchW = _iw - _stateW - _sortW - 2 * _gap;
private _search = [[_padX, _fy, _searchW, _rowH], 884420, "Rechercher une tenue, une collection, un auteur…", "Rechercher une tenue (nom, collection, auteur, état, pièce). Plusieurs mots : tous doivent correspondre. Bas : aller aux cartes.", "search_ca.paa"] call _field;
_search ctrlAddEventHandler ["KeyUp", { ["filter"] call comspec_overwatch_connect_fnc_arsenalOverlayAction; }];
_grp setVariable ["COMSPEC_ArsenalSearch", _search];
_grp setVariable ["COMSPEC_ArsenalSearchPh", _search getVariable ["ph", controlNull]];
private _clr = [[_padX + _searchW - _sq, _fy, _sq, _rowH], "close_ca.paa", "clearSearch", "COMSPEC_RscInvisibleButton", "Effacer la recherche", _muted, 0.32] call _icoBtn;
_clr ctrlShow false;
(_clr getVariable ["ico", controlNull]) ctrlShow false;
_grp setVariable ["COMSPEC_ArsenalSearchClear", _clr];
private _stateCombo = ["RscCombo", [_padX + _searchW + _gap, _fy, _stateW, _rowH], 884421] call _mk;
_stateCombo ctrlSetFont _fR;
_stateCombo ctrlSetFontHeight _fsS;
_stateCombo ctrlSetTooltip (("Filtrer par état de synchronisation avec le site") call comspec_overwatch_connect_fnc_tr);
_stateCombo ctrlAddEventHandler ["LBSelChanged", { ["stateFilter"] call comspec_overwatch_connect_fnc_arsenalOverlayAction; }];
_grp setVariable ["COMSPEC_ArsenalStateCombo", _stateCombo];
private _sortCombo = ["RscCombo", [_w - _padX - _sortW, _fy, _sortW, _rowH], 884423] call _mk;
_sortCombo ctrlSetFont _fR;
_sortCombo ctrlSetFontHeight _fsS;
{
    _x params ["_k", "_label"];
    private _i = _sortCombo lbAdd ((_label) call comspec_overwatch_connect_fnc_tr);
    _sortCombo lbSetData [_i, _k];
} forEach [["coll", "Tri : collection"], ["name", "Tri : nom"], ["state", "Tri : état"], ["recent", "Tri : plus récentes"]];
private _sortKey = profileNamespace getVariable ["COMSPEC_ArsenalSort", "coll"];
_sortCombo lbSetCurSel ((["coll", "name", "state", "recent"] find _sortKey) max 0);
_sortCombo ctrlAddEventHandler ["LBSelChanged", { params ["_c", "_i"]; ["sort", _c lbData _i] call comspec_overwatch_connect_fnc_arsenalOverlayAction; }];
_grp setVariable ["COMSPEC_ArsenalSort", _sortKey];

// Collections en pastilles : réserve de boutons réécrits par arsenalOverlayFill, flèches si elles débordent.
private _cy = _fy + _rowH + _u * 0.3;
private _chipH = _rowH * 0.82;
_grp setVariable ["COMSPEC_ArsenalChipBand", [_padX, _cy, _iw, _chipH]];
private _arrW = _chipH * _pxW / _pxH;
_grp setVariable ["COMSPEC_ArsenalChipArrows", [
    [[_padX, _cy, _arrW, _chipH], "left_ca.paa", "chipPrev", "COMSPEC_RscButtonAtak", "Collections précédentes"] call _icoBtn,
    [[_w - _padX - _arrW, _cy, _arrW, _chipH], "right_ca.paa", "chipNext", "COMSPEC_RscButtonAtak", "Collections suivantes"] call _icoBtn
]];
private _chips = [];
for "_i" from 1 to 16 do {
    private _c = [[_padX, _cy, 0.05, _chipH], "", "chip", "COMSPEC_RscButtonAtak", "", _fR, _fsXS] call _btn;
    _c ctrlShow false;
    _chips pushBack _c;
};
_grp setVariable ["COMSPEC_ArsenalChips", _chips];

// Corps : grille de cartes à gauche, fiche à droite.
private _footH = _rowH + _u * 1.0;
private _bodyY = _cy + _chipH + _u * 0.4;
private _bodyH = _h - _bodyY - _footH - _padY - _u * 0.55;
private _colGap = 0.5 * _uw;
private _listW = (_iw - _colGap) * 0.58;
private _barH = _rowH * 0.88;
private _areaY = _bodyY;
private _areaH = _bodyH - _barH - _u * 0.25;
private _empty = ["RscStructuredText", [_padX + _listW * 0.06, _areaY + _areaH * 0.3, _listW * 0.88, _u * 3]] call _mk;
_grp setVariable ["COMSPEC_ArsenalEmpty", _empty];

// Cartes : taille fixe, autant de colonnes et de rangées que la place en donne ; une page = la grille pleine.
private _cardH = _u * 2.6;
private _gx = 0.35 * _uw;
private _gy = _u * 0.22;
private _cols = (1 max (floor ((_listW + _gx) / (10.5 * _uw + _gx)))) min 3;
private _rows = 1 max (floor ((_areaH + _gy) / (_cardH + _gy)));
private _cardW = (_listW - (_cols - 1) * _gx) / _cols;
private _ps = _cardH - _u * 0.4;
private _psW = _ps * _pxW / _pxH;
private _icH = (_cardH - _u * 0.52) / 2;
private _icW = _icH * 1.35 * _pxW / _pxH;
private _icX = _cardW - 0.3 * _uw - 2 * _icW - 0.1 * _uw;
private _tX = 0.35 * _uw + _psW + 0.35 * _uw;
private _tW = _icX - 0.3 * _uw - _tX;
private _cards = [];
for "_i" from 0 to (_cols * _rows - 1) do {
    private _x0 = _padX + (_i mod _cols) * (_cardW + _gx);
    private _y0 = _areaY + (floor (_i / _cols)) * (_cardH + _gy);
    private _outer = [[_x0, _y0, _cardW, _cardH], _line] call _box;
    private _inner = [[_x0 + _pxW, _y0 + _pxH, _cardW - 2 * _pxW, _cardH - 2 * _pxH], [0.052, 0.063, 0.056, 1]] call _box;
    private _stripe = [[_x0 + _pxW, _y0 + _pxH, 3 * _pxW, _cardH - 2 * _pxH], _muted] call _box;
    private _pbg = [[_x0 + 0.35 * _uw, _y0 + _u * 0.2, _psW, _ps], _bg2] call _box;
    private _ppic = [[_x0 + 0.35 * _uw + 2 * _pxW, _y0 + _u * 0.2 + 2 * _pxH, _psW - 4 * _pxW, _ps - 4 * _pxH]] call _pic;
    private _check = [[_x0 + 0.35 * _uw + _pxW, _y0 + _u * 0.2 + _pxH, _u * 0.75 * _pxW / _pxH, _u * 0.75]] call _pic;
    private _icons = [];
    for "_k" from 0 to 3 do {
        _icons pushBack ([[_x0 + _icX + (_k mod 2) * (_icW + 0.1 * _uw), _y0 + _u * 0.2 + (floor (_k / 2)) * (_icH + _u * 0.12), _icW, _icH]] call _pic);
    };
    private _name = [[_x0 + _tX, _y0 + _u * 0.14, _tW, _u * 0.85], "", _fB, _fs * 0.95] call _txt;
    private _flags = ["RscStructuredText", [_x0 + _tX, _y0 + _u * 0.2, _tW, _u * 0.8]] call _mk;
    private _meta = [[_x0 + _tX, _y0 + _u * 0.96, _tW, _u * 0.7], "", _fR, _fsXS, _muted] call _txt;
    private _badge = [[_x0 + _tX, _y0 + _u * 1.74, _tW, _u * 0.64], "", _fB, (_u * 0.56) max (16 * _pxH)] call _txt;
    // Place d'origine du nom et de la collection (le squelette du chargement les raccourcit).
    { _x setVariable ["rect", ctrlPosition _x]; } forEach [_name, _meta];
    private _hit = ["COMSPEC_RscArsenalCardHit", [_x0, _y0, _cardW, _cardH]] call _mk;
    _hit setVariable ["idx", -1];
    _hit ctrlAddEventHandler ["ButtonClick", { params ["_c"]; ["select", _c getVariable ["idx", -1]] call comspec_overwatch_connect_fnc_arsenalOverlayAction; }];
    _hit ctrlAddEventHandler ["MouseButtonDblClick", { params ["_c", "_b"]; if (_b isEqualTo 0) then { ["apply", _c getVariable ["idx", -1]] call comspec_overwatch_connect_fnc_arsenalOverlayAction; }; }];
    _hit ctrlAddEventHandler ["MouseButtonDown", { params ["_c", "_b"]; if (_b isEqualTo 1) then { ["menu", _c getVariable ["idx", -1]] call comspec_overwatch_connect_fnc_arsenalOverlayAction; }; }];
    _hit ctrlAddEventHandler ["MouseZChanged", { params ["", "_z"]; ["page", [1, -1] select (_z > 0)] call comspec_overwatch_connect_fnc_arsenalOverlayAction; }];
    // Clavier sur une carte (elle a le focus après un clic) : touches lues par le gestionnaire de l'écran (plus bas).
    // ACE ignore ses raccourcis tant qu'une carte a le focus (sinon Retour arrière masquait l'interface, Ctrl+C
    // exportait la tenue portée).
    [_hit] call _typing;
    _hit setVariable ["COMSPEC_CardHit", true];
    private _all = [_outer, _inner, _stripe, _pbg, _ppic, _check, _name, _flags, _meta, _badge, _hit] + _icons;
    { _x ctrlShow false; } forEach _all;
    // [zone cliquable, cadre, fond, liseré d'état, fond silhouette, silhouette, case, nom, pastilles, collection,
    //  badge, icônes, tous les contrôles, largeur du texte]
    _cards pushBack [_hit, _outer, _inner, _stripe, _pbg, _ppic, _check, _name, _flags, _meta, _badge, _icons, _all, _tW];
};
_grp setVariable ["COMSPEC_ArsenalCards", _cards];
_grp setVariable ["COMSPEC_ArsenalCols", _cols];
_grp setVariable ["COMSPEC_ArsenalPage", 0];
// Carte choisie (cadre vert) et cases cochées de la sélection multiple, sans réécrire les cartes.
_grp setVariable ["COMSPEC_ArsenalPaintCards", {
    params ["_g"];
    private _s = _g getVariable ["COMSPEC_ArsenalSelected", -1];
    private _pick = ((_g getVariable ["COMSPEC_ArsenalTab", "local"]) isEqualTo "local") && {_g getVariable ["COMSPEC_ArsenalPickMode", false]};
    private _marked = _g getVariable ["COMSPEC_ArsenalMarked", []];
    private _shown = _g getVariable ["COMSPEC_ArsenalShown", []];
    {
        _x params ["_hit", "_outer", "_inner", "", "", "", "_check"];
        private _i = _hit getVariable ["idx", -1];
        if (_i < 0) then { continue };
        private _on = _i isEqualTo _s;
        private _m = _pick && {(toLower ((_shown param [_i, [""]]) select 0)) in _marked};
        _outer ctrlSetBackgroundColor ([[0.15, 0.19, 0.16, 1], [0.36, 0.78, 0.42, 1]] select (_on || _m));
        _inner ctrlSetBackgroundColor ([[0.052, 0.063, 0.056, 1], [0.07, 0.11, 0.08, 1]] select (_on || _m));
        _check ctrlShow _pick;
        if (_pick) then {
            _check ctrlSetText (((["\a3\ui_f\data\GUI\RscCommon\RscCheckBox\CheckBox_unchecked_ca.paa", "\a3\ui_f\data\GUI\RscCommon\RscCheckBox\CheckBox_checked_ca.paa"] select _m)) call comspec_overwatch_connect_fnc_tr);
            _check ctrlSetTextColor ([[0.58, 0.64, 0.60, 1], [0.36, 0.78, 0.42, 1]] select _m);
        };
    } forEach (_g getVariable ["COMSPEC_ArsenalCards", []]);
}];

// Barre sous les cartes : sélection multiple (Mes tenues) à gauche, pages à droite.
private _tby = _areaY + _areaH + _u * 0.25;
private _pgS = _barH * _pxW / _pxH;
private _pgL = 2.8 * _uw;
private _pgX = _padX + _listW - 2 * _pgS - _pgL - 0.008;
private _pgLabel = ["RscStructuredText", [_pgX + _pgS + 0.004, _tby + _barH * 0.12, _pgL, _barH]] call _mk;
_grp setVariable ["COMSPEC_ArsenalPager", [
    [[_pgX, _tby, _pgS, _barH], "left_ca.paa", "pagePrev", "COMSPEC_RscButtonAtak", "Page précédente (molette ou Page préc. sur les cartes)"] call _icoBtn,
    _pgLabel,
    [[_pgX + _pgS + _pgL + 0.008, _tby, _pgS, _barH], "right_ca.paa", "pageNext", "COMSPEC_RscButtonAtak", "Page suivante (molette ou Page suiv. sur les cartes)"] call _icoBtn
]];
private _pickBtns = [];
{
    _x params ["_act", "_cls", "_tip"];
    _pickBtns pushBack ([[_padX, _tby, 0.05, _barH], "", _act, _cls, _tip, _fB, _fsXS] call _btn);
} forEach [
    ["pick", "COMSPEC_RscButtonAtak", "Cocher plusieurs tenues pour les supprimer en une fois"],
    ["markAll", "COMSPEC_RscButtonAtak", "Coche toutes les tenues affichées (recherche, état et collection appliqués)"],
    ["deleteMarked", "COMSPEC_RscButtonAtakDanger", "Supprime de votre arsenal, sur cet ordinateur, les tenues cochées. Celles déjà sur le site y restent."],
    ["undoDelete", "COMSPEC_RscButtonAtak", "Remet dans votre arsenal les tenues de la dernière suppression"]
];
_grp setVariable ["COMSPEC_ArsenalPickBtns", _pickBtns];
_grp setVariable ["COMSPEC_ArsenalPickMode", false];
_grp setVariable ["COMSPEC_ArsenalMarked", []];
// Barre de sélection : seulement les boutons utiles (Sélectionner ; Tout cocher et Supprimer une fois en mode coché ;
// Annuler tant qu'une suppression peut être reprise), à la largeur de leur texte.
_grp setVariable ["COMSPEC_ArsenalPickRefresh", {
    params ["_g"];
    (_g getVariable ["COMSPEC_ArsenalPickBtns", []]) params [["_bPick", controlNull], ["_bAll", controlNull], ["_bDel", controlNull], ["_bUndo", controlNull]];
    private _local = (_g getVariable ["COMSPEC_ArsenalTab", "local"]) isEqualTo "local";
    private _mode = _local && {_g getVariable ["COMSPEC_ArsenalPickMode", false]};
    private _marked = _g getVariable ["COMSPEC_ArsenalMarked", []];
    private _shown = (_g getVariable ["COMSPEC_ArsenalShown", []]) apply { toLower (_x select 0) };
    private _allOn = _shown isNotEqualTo [] && {(_shown findIf { !(_x in _marked) }) < 0};
    private _trash = profileNamespace getVariable ["COMSPEC_ArsenalTrash", []];
    if !(_trash isEqualType []) then { _trash = []; };
    _bPick ctrlSetText ((["SÉLECTIONNER", "TERMINER"] select _mode) call comspec_overwatch_connect_fnc_tr);
    _bAll ctrlSetText ((["TOUT COCHER", "TOUT DÉCOCHER"] select _allOn) call comspec_overwatch_connect_fnc_tr);
    _bAll ctrlEnable (_shown isNotEqualTo []);
    _bDel ctrlSetText ((format [("SUPPRIMER (%1)") call comspec_overwatch_connect_fnc_tr, count _marked]) call comspec_overwatch_connect_fnc_tr);
    _bDel ctrlEnable (_marked isNotEqualTo []);
    _bUndo ctrlSetText ((format [("ANNULER (%1)") call comspec_overwatch_connect_fnc_tr, count _trash]) call comspec_overwatch_connect_fnc_tr);
    private _bx = (_g getVariable ["COMSPEC_ArsenalPickX", 0]);
    {
        _x params ["_b", "_on"];
        _b ctrlShow _on;
        if (_on) then {
            (ctrlPosition _b) params ["", "_by", "", "_bh"];
            private _bw = (ctrlTextWidth _b) + 0.022;
            _b ctrlSetPosition [_bx, _by, _bw, _bh];
            _b ctrlCommit 0;
            _bx = _bx + _bw + 0.004;
        };
    } forEach [[_bPick, _local], [_bAll, _mode], [_bDel, _mode], [_bUndo, _local && {_trash isNotEqualTo []}]];
}];
_grp setVariable ["COMSPEC_ArsenalPickX", _padX];

// Fiche de la tenue choisie.
private _dx = _padX + _listW + _colGap;
private _dw = _w - _dx - _padX;
private _ip = 0.45 * _uw;
private _ipY = _u * 0.35;
[[_dx, _bodyY, _dw, _bodyH], [0.055, 0.067, 0.06, 1]] call _box;
[[_dx, _bodyY, _dw, _pxH], _line] call _box;
private _dIw = _dw - 2 * _ip;
private _ix = _dx + _ip;
private _top = _bodyY + _ipY;
// Portrait : celui de l'auteur (téléchargé comme sur ATAK) par-dessus la tenue en silhouette.
private _avS = (_u * 3.3) min (_dIw * 0.3 * _pxH / _pxW);
private _avW = _avS * _pxW / _pxH;
private _avBg = [[_ix, _top, _avW, _avS], _bg2] call _box;
private _avSil = [[_ix + 3 * _pxW, _top + 3 * _pxH, _avW - 6 * _pxW, _avS - 6 * _pxH]] call _pic;
private _avPic = ["RscPictureKeepAspect", [_ix + _pxW, _top + _pxH, _avW - 2 * _pxW, _avS - 2 * _pxH], 884430] call _mk;
_avPic ctrlShow false;
_grp setVariable ["COMSPEC_ArsenalAvatar", [_avBg, _avPic, _avSil]];
private _hx = _ix + _avW + 0.4 * _uw;
private _hw = _ix + _dIw - _hx;
private _dHead = ["RscStructuredText", [_hx, _top - _u * 0.05, _hw, _u * 2.3]] call _mk;
_grp setVariable ["COMSPEC_ArsenalDetailHead", _dHead];
// Badge d'état sous le nom ; pastilles (obligatoire, mise en avant, favori, auteur) et phrase qui dit quoi faire.
private _badge = [[_hx, _top + _avS - _u * 0.8, 0.1, _u * 0.8], "", _fB, (_u * 0.62) max (17 * _pxH), [0.03, 0.04, 0.035, 1], _green] call _txt;
_grp setVariable ["COMSPEC_ArsenalBadge", _badge];
private _by = _top + _avS + _u * 0.25;
private _flags = ["RscStructuredText", [_ix, _by, _dIw, _u * 0.9]] call _mk;
_grp setVariable ["COMSPEC_ArsenalDetailFlags", _flags];
private _note = ["RscStructuredText", [_ix, _by + _u * 0.85, _dIw, _u * 1.5]] call _mk;
_grp setVariable ["COMSPEC_ArsenalBadgeNote", _note];
// Pièces de la tenue : 2 rangées de 4 cases, icône et légende (Arme, Tenue…).
private _cg = 0.006;
private _cellW = (_dIw - 3 * _cg) / 4;
private _picH = (_cellW * _pxH / _pxW * 0.55) min (_u * 2.1);
private _capH = _u * 0.7;
private _cellH = _picH + _capH;
private _gy2 = _by + _u * 2.45;
private _pics = [];
for "_i" from 0 to 7 do {
    private _cx = _ix + (_i mod 4) * (_cellW + _cg);
    private _cy2 = _gy2 + (floor (_i / 4)) * (_cellH + _cg);
    private _cb = [[_cx, _cy2, _cellW, _cellH], [0.075, 0.09, 0.08, 1]] call _box;
    private _p = ["RscPictureKeepAspect", [_cx + _cellW * 0.08, _cy2 + _picH * 0.06, _cellW * 0.84, _picH * 0.88], 884408 + _i] call _mk;
    private _cap = [[_cx, _cy2 + _picH, _cellW, _capH], "", _fR, _fsXS, [0.66, 0.74, 0.69, 1]] call _txt;
    { _x ctrlShow false; } forEach [_cb, _p, _cap];
    _pics pushBack [_cb, _p, _cap];
};
_grp setVariable ["COMSPEC_ArsenalPreviewPics", _pics];
// Actions de la fiche : Enfiler (pleine largeur), puis Importer / Envoyer / Mettre à jour, Supprimer et « … » (toutes
// les actions, comme le clic droit sur la carte).
private _actY = _bodyY + _bodyH - _ipY - (_rowH * 2 + _gap);
private _ny = _gy2 + 2 * (_cellH + _cg) + 0.004;
private _namesGrp = ["RscControlsGroup", [_ix, _ny, _dIw, (_actY - _ny - _gap) max _u]] call _mk;
private _names = _display ctrlCreate ["RscStructuredText", -1, _namesGrp];
_names ctrlSetPosition [0, 0, _dIw - 0.012, _u];
_names ctrlCommit 0;
_grp setVariable ["COMSPEC_ArsenalPreviewNames", _names];
private _half = (_dIw - 2 * _gap - _sq) / 2;
_grp setVariable ["COMSPEC_ArsenalDetailBtns", [
    [[_ix, _actY, _dIw, _rowH], "ENFILER", "apply", "COMSPEC_RscButtonAtakPrimary", "Mettre cette tenue sur le mannequin et fermer le panneau", _fB, _fs] call _btn,
    [[_ix, _actY + _rowH + _gap, _half, _rowH], "", "keep", "COMSPEC_RscButtonAtak", "", _fB] call _btn,
    [[_ix + _half + _gap, _actY + _rowH + _gap, _half, _rowH], "", "remove", "COMSPEC_RscButtonAtakDanger", "", _fB] call _btn,
    [[_ix + _dIw - _sq, _actY + _rowH + _gap, _sq, _rowH], "more_ca.paa", "moreMenu", "COMSPEC_RscButtonAtak", "Toutes les actions sur cette tenue (aussi au clic droit sur sa carte)"] call _icoBtn
]];

// Pied : action groupée, enregistrement de la tenue portée, puis retour des actions et rappel des gestes.
private _fty = _h - _padY - _footH;
[[_padX, _fty - _u * 0.3, _iw, _pxH], _line] call _box;
private _fw = (_iw - _gap) / 2;
private _syncW = _fw * 0.46;
_grp setVariable ["COMSPEC_ArsenalBulk", [[_padX, _fty, _fw - _syncW - _gap, _rowH], "", "bulk", "COMSPEC_RscButtonAtak", "", _fB] call _btn];
// Tout basculer : toutes les tenues de l'organisation dans l'arsenal ACE, les miennes vers le site, sans rien écraser.
private _bSync = [[_padX + _fw - _syncW, _fty, _syncW, _rowH], "TOUT BASCULER DANS L'ARSENAL ACE", "syncAll", "COMSPEC_RscButtonAtakPrimary", "Récupère toutes les tenues de l'organisation dans « Mes équipements » d'ACE et envoie au site celles qui n'y sont pas. Rien n'est supprimé ni remplacé.", _fB] call _btn;
private _sx = _padX + _fw + _gap;
private _saveW = 6.4 * _uw;
private _editW = _fw - _saveW - _gap;
private _nameEdit = [[_sx, _fty, _editW, _rowH], 884422, "Nom de la tenue portée (ex. SOAR - Breacher)…", "Nom de la tenue à enregistrer (ex. SOAR - Breacher). Le préfixe avant « - » sert de collection."] call _field;
_nameEdit ctrlSetText (profileNamespace getVariable ["COMSPEC_ArsenalLastSaveName", ""]); // i18n:brut
_grp setVariable ["COMSPEC_ArsenalSaveName", _nameEdit];
// Entrée : enregistre la tenue portée sous ce nom (même effet que le bouton ; gestionnaire de l'écran, plus bas).
(_nameEdit getVariable ["ph", controlNull]) ctrlShow ((ctrlText _nameEdit) isEqualTo "");
_nameEdit ctrlAddEventHandler ["KeyUp", { params ["_c"]; (_c getVariable ["ph", controlNull]) ctrlShow ((ctrlText _c) isEqualTo ""); }];
private _bSave = [[_w - _padX - _saveW, _fty, _saveW, _rowH], "ENREGISTRER ET ENVOYER", "saveCurrent", "COMSPEC_RscButtonAtakPrimary", "Enregistre la tenue portée par le mannequin dans votre arsenal et l'envoie au site pour l'organisation", _fB] call _btn;
_grp setVariable ["COMSPEC_ArsenalNetBtns", [_bReload, _bSync, _bSave]];
private _lineY = _fty + _rowH + _u * 0.18;
private _feedback = ["RscStructuredText", [_padX, _lineY, _iw * 0.56, _u * 0.8]] call _mk;
private _help = ["RscStructuredText", [_padX + _iw * 0.56, _lineY, _iw * 0.44, _u * 0.8]] call _mk;
_grp setVariable ["COMSPEC_ArsenalHint", _feedback];
_grp setVariable ["COMSPEC_ArsenalHelp", _help];
// Retour d'une action, de la couleur de son issue : info (gris), busy (en cours), ok (vert), warn (orange), err (rouge).
_grp setVariable ["COMSPEC_ArsenalSetHint", {
    params ["_g", "_text", ["_lvl", "info"]];
    private _c = _g getVariable ["COMSPEC_ArsenalHint", controlNull];
    if (isNull _c) exitWith {};
    private _dot = switch (_lvl) do { case "ok"; case "busy": { "#5cc76b" }; case "warn": { "#f2ab33" }; case "err": { "#e04038" }; default { "#8fa596" }; };
    private _fg = ["#b5c4ba", "#e4efe8"] select (_lvl in ["ok", "warn", "err"]);
    private _ico = _g getVariable ["COMSPEC_ArsenalIco", ""];
    private _img = switch (_lvl) do { case "warn"; case "err": { "warn_ca.paa" }; default { "dot_ca.paa" }; };
    _c ctrlSetStructuredText parseText (((format ["<t size='%4'><img image='%5%6' color='%1'/>  <t color='%2'>%3</t></t>", _dot, _fg, (_text) call comspec_overwatch_connect_fnc_tr, 0.8 max (_g getVariable ["COMSPEC_ArsenalStMin", 0]), _ico, _img])) call comspec_overwatch_connect_fnc_tr);
    _c ctrlSetTooltip ((_text) call comspec_overwatch_connect_fnc_tr);
    // Opération réseau en cours (chargement, envoi, import, synchro) : les boutons qui en lancent une autre attendent
    // la fin (ou 60 s au plus, si l'opération ne rend jamais la main).
    private _busy = _lvl isEqualTo "busy";
    _g setVariable ["COMSPEC_ArsenalBusy", _busy];
    { if (!isNull _x) then { _x ctrlEnable !_busy; }; } forEach (_g getVariable ["COMSPEC_ArsenalNetBtns", []]);
    private _bulk = _g getVariable ["COMSPEC_ArsenalBulk", controlNull];
    if (!isNull _bulk) then { _bulk ctrlEnable (!_busy && {(_g getVariable ["COMSPEC_ArsenalBulkRows", []]) isNotEqualTo []} && {!(_g getVariable ["COMSPEC_ArsenalOffline", false])}); };
    if (_busy) then {
        private _t = diag_tickTime;
        _g setVariable ["COMSPEC_ArsenalBusyAt", _t];
        [{
            params ["_g", "_t"];
            if (isNull _g || {(_g getVariable ["COMSPEC_ArsenalBusyAt", -1]) isNotEqualTo _t} || {!(_g getVariable ["COMSPEC_ArsenalBusy", false])}) exitWith {};
            _g setVariable ["COMSPEC_ArsenalBusy", false];
            { if (!isNull _x) then { _x ctrlEnable true; }; } forEach (_g getVariable ["COMSPEC_ArsenalNetBtns", []]);
        }, [_g, _t], 60] call CBA_fnc_waitAndExecute;
    };
}];

// Menu clic droit : fond qui le ferme au clic, carte de lignes et de séparateurs placés à l'ouverture (créés en
// dernier : par-dessus les cartes). Même fond pour la petite fenêtre de saisie (Renommer, Déplacer vers une collection).
private _scrim = ["COMSPEC_RscArsenalScrim", [0, 0, _w, _h]] call _mk;
_scrim ctrlAddEventHandler ["ButtonClick", { ["menuClose"] call comspec_overwatch_connect_fnc_arsenalOverlayAction; }];
_scrim ctrlShow false;
// Fermés, le fond, le menu et la saisie n'ont aucune surface : même réaffichés avec leur groupe, ils ne se voient pas.
_grp setVariable ["COMSPEC_ArsenalScrimRect", [0, 0, _w, _h]];
_scrim ctrlSetPosition [0, 0, 0, 0];
_scrim ctrlCommit 0;
private _menuW = 12.5 * _uw;
private _mRowH = _rowH * 0.88;
private _menu = ["RscControlsGroupNoScrollbars", [0, 0, _menuW, 16 * _mRowH]] call _mk;
private _mBg = [[0, 0, _menuW, 16 * _mRowH], _line, _menu] call _box;
[[0, 0, _menuW, 2 * _pxH], _green, _menu] call _box;
private _mRows = [];
for "_i" from 0 to 15 do {
    private _r = ["COMSPEC_RscArsenalMenuRow", [_pxW, 0, _menuW - 2 * _pxW, _mRowH - _pxH], -1, _menu] call _mk;
    _r ctrlSetFont _fR;
    _r ctrlSetFontHeight _fsS;
    _r ctrlAddEventHandler ["ButtonClick", { params ["_c"]; ["menuDo", _c getVariable ["act", ""]] call comspec_overwatch_connect_fnc_arsenalOverlayAction; }];
    _r ctrlShow false;
    _mRows pushBack _r;
};
private _mSeps = [];
for "_i" from 0 to 5 do {
    private _l = [[0.4 * _uw, 0, _menuW - 0.8 * _uw, _pxH], [0.22, 0.28, 0.24, 1], _menu] call _box;
    _l ctrlShow false;
    _mSeps pushBack _l;
};
_menu ctrlShow false;
_menu ctrlSetPosition [0, 0, 0, 0];
_menu ctrlCommit 0;
_grp setVariable ["COMSPEC_ArsenalMenu", [_scrim, _menu, _mRows, _mRowH, _menuW, _mBg, _mSeps]];

// Fenêtre de saisie : titre, champ (Entrée valide), Valider / Annuler.
private _askW = (16 * _uw) min (_w * 0.8);
private _askH = _u * 3.9;
private _ask = ["RscControlsGroupNoScrollbars", [(_w - _askW) / 2, (_h - _askH) / 2, _askW, _askH]] call _mk;
[[0, 0, _askW, _askH], _line, _ask] call _box;
[[_pxW, _pxH, _askW - 2 * _pxW, _askH - 2 * _pxH], [0.035, 0.042, 0.038, 1], _ask] call _box;
[[0, 0, _askW, 2 * _pxH], _green, _ask] call _box;
private _aIp = 0.45 * _uw;
private _askTitle = ["RscStructuredText", [_aIp, _u * 0.3, _askW - 2 * _aIp, _u * 0.9], -1, _ask] call _mk;
[[_aIp, _u * 1.25, _askW - 2 * _aIp, _rowH], _bg2, _ask] call _box;
[[_aIp, _u * 1.25 + _rowH - _pxH, _askW - 2 * _aIp, _pxH], _green, _ask] call _box;
private _askEdit = ["RscEdit", [_aIp + 0.004, _u * 1.25, _askW - 2 * _aIp - 0.008, _rowH], 884424, _ask] call _mk;
_askEdit ctrlSetFont _fR;
_askEdit ctrlSetFontHeight _fsS;
[_askEdit] call _typing;
private _aBw = (_askW - 2 * _aIp - _gap) / 2;
{
    _x params ["_bx", "_label", "_act", "_cls"];
    private _b = [_cls, [_bx, _u * 2.6, _aBw, _rowH], -1, _ask] call _mk;
    _b ctrlSetText ((_label) call comspec_overwatch_connect_fnc_tr);
    _b ctrlSetFont _fB;
    _b ctrlSetFontHeight _fsS;
    _b setVariable ["act", _act];
    _b ctrlAddEventHandler ["ButtonClick", { params ["_c"]; [_c getVariable "act"] call comspec_overwatch_connect_fnc_arsenalOverlayAction; }];
} forEach [[_aIp, "FERMER", "menuClose", "COMSPEC_RscButtonAtak"], [_aIp + _aBw + _gap, "VALIDER", "askOk", "COMSPEC_RscButtonAtakPrimary"]];
_ask ctrlShow false;
_grp setVariable ["COMSPEC_ArsenalAskRect", [(_w - _askW) / 2, (_h - _askH) / 2, _askW, _askH]];
_ask ctrlSetPosition [0, 0, 0, 0];
_ask ctrlCommit 0;
_grp setVariable ["COMSPEC_ArsenalAsk", [_ask, _askTitle, _askEdit]];

// Clavier : un seul gestionnaire sur l'écran de l'arsenal, à côté de celui d'ACE (qui garde la touche pour lui :
// un gestionnaire de contrôle ne la recevrait pas toujours). Il n'agit que si le panneau est ouvert et selon le
// contrôle qui a le focus :
//   carte        flèches, Page préc. / suiv., Début / Fin (parcourir), Entrée (enfiler), Suppr (supprimer, avec
//                confirmation), Ctrl+C (copier la tenue)
//   recherche    flèche bas ou Entrée : aller aux cartes
//   nom en bas   Entrée : enregistrer la tenue portée
//   saisie       Entrée : valider (Renommer, Déplacer vers une collection)
private _oldEh = _display getVariable ["COMSPEC_ArsenalKeyEh", -1];
if (_oldEh >= 0) then { _display displayRemoveEventHandler ["KeyDown", _oldEh]; };
_display setVariable ["COMSPEC_ArsenalKeyEh", _display displayAddEventHandler ["KeyDown", {
    params ["_d", "_key", "", "_ctrl"];
    if !(_d getVariable ["COMSPEC_ArsenalOverlayOpen", false]) exitWith { false };
    private _g = _d getVariable ["COMSPEC_ArsenalOverlay", controlNull];
    private _f = focusedCtrl _d;
    if (isNull _g || {isNull _f} || {!ctrlShown _g}) exitWith { false };
    private _enter = _key in [28, 156];
    private _do = switch (true) do {
        case (_f getVariable ["COMSPEC_CardHit", false]): {
            switch (_key) do {
                case 203: { ["nav", "left"] };
                case 205: { ["nav", "right"] };
                case 200: { ["nav", "up"] };
                case 208: { ["nav", "down"] };
                case 201: { ["nav", "pgUp"] };
                case 209: { ["nav", "pgDn"] };
                case 199: { ["nav", "home"] };
                case 207: { ["nav", "end"] };
                case 28;
                case 156: { ["navEnter"] };
                case 211: { ["navDelete"] };
                case 46: { [["", "navCopy"] select _ctrl] };
                default { [""] };
            }
        };
        case (_f isEqualTo (_g getVariable ["COMSPEC_ArsenalSearch", controlNull])): { [[""], ["navFocus"]] select (_enter || {_key isEqualTo 208}) };
        case (_f isEqualTo (_g getVariable ["COMSPEC_ArsenalSaveName", controlNull])): { [[""], ["saveCurrent"]] select _enter };
        case (_f isEqualTo ((_g getVariable ["COMSPEC_ArsenalAsk", []]) param [2, controlNull])): { [[""], ["askOk"]] select _enter };
        default { [""] };
    };
    if ((_do select 0) isEqualTo "") exitWith { false };
    [{ _this call comspec_overwatch_connect_fnc_arsenalOverlayAction; }, _do] call CBA_fnc_execNextFrame;
    true
}]];

// Panneau rendu visible : afficher le groupe réaffiche ses contrôles masqués (panneau du menu, saisie, cartes vides,
// boutons de sélection empilés, invite sur le nom : capture du propriétaire). Chacun reprend ici son état, sans attendre
// la liste. Appelé juste après _g ctrlShow true (arsenalOverlayAction « toggle », « loadoutsClosed »).
_grp setVariable ["COMSPEC_ArsenalSettle", {
    params ["_g"];
    ["menuClose"] call comspec_overwatch_connect_fnc_arsenalOverlayAction;
    { { _x ctrlShow false; } forEach (_x select 12); } forEach (_g getVariable ["COMSPEC_ArsenalCards", []]);
    {
        if (!isNull _x) then {
            _x ctrlShow false;
            private _p = _x getVariable ["ico", controlNull];
            if (!isNull _p) then { _p ctrlShow false; };
        };
    } forEach ((_g getVariable ["COMSPEC_ArsenalChips", []]) + (_g getVariable ["COMSPEC_ArsenalChipArrows", []]) + (_g getVariable ["COMSPEC_ArsenalPager", []]) + (_g getVariable ["COMSPEC_ArsenalPills", []]) + [_g getVariable ["COMSPEC_ArsenalEmpty", controlNull]]);
    private _search = _g getVariable ["COMSPEC_ArsenalSearch", controlNull];
    private _hasText = (ctrlText _search) isNotEqualTo "";
    (_g getVariable ["COMSPEC_ArsenalSearchPh", controlNull]) ctrlShow !_hasText;
    private _clr = _g getVariable ["COMSPEC_ArsenalSearchClear", controlNull];
    _clr ctrlShow _hasText;
    (_clr getVariable ["ico", controlNull]) ctrlShow _hasText;
    private _save = _g getVariable ["COMSPEC_ArsenalSaveName", controlNull];
    (_save getVariable ["ph", controlNull]) ctrlShow ((ctrlText _save) isEqualTo "");
    [_g] call (_g getVariable ["COMSPEC_ArsenalPickRefresh", {}]);
    [ctrlParent _g, [], ""] call comspec_overwatch_connect_fnc_arsenalOverlayPreview;
}];

_grp setVariable ["COMSPEC_ArsenalTab", profileNamespace getVariable ["COMSPEC_ArsenalTab", "local"]];
_grp setVariable ["COMSPEC_ArsenalColl", ""];
_grp setVariable ["COMSPEC_ArsenalStateKey", "all"];
_grp setVariable ["COMSPEC_ArsenalChipOffset", 0];
[_display, [], ""] call comspec_overwatch_connect_fnc_arsenalOverlayPreview;
