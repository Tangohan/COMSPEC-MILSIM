/*
    Bouton Athena de l'ACE Arsenal et panneau « Tenues Athena ».

    Fermé par défaut ; aucune synchronisation tant qu'il n'est pas ouvert. Charte du téléphone ATAK natif (vert
    #5cc76b, polices COMSPEC_ATAK_FontSet) :
      en-tête    titre, sous-titre, heure de chargement, Actualiser, Fermer
      onglets    Mes tenues / Communauté avec compteurs, pastilles « à envoyer » et « nouvelles » (cliquables)
      filtres    recherche, état de synchro, tri ; collections en pastilles (défilent avec ‹ ›)
      corps      liste (pastille d'état, nom, collection, état) et fiche de la tenue (état expliqué, pièces en
                 icônes légendées, contenu, actions Enfiler / Envoyer ou Importer / Supprimer)
      pied       action groupée (envoyer les tenues pas encore sur le site / importer), enregistrer la tenue portée
    Le panneau se place entre les deux colonnes de l'arsenal pour ne rien masquer.
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
private _fs = _guiH * 0.86;
private _fsS = _guiH * 0.74;
private _pxW = pixelW; private _pxH = pixelH;
private _green = [0.36, 0.78, 0.42, 1];
private _muted = [0.56, 0.65, 0.59, 1];

// Bouton d'ouverture : en haut, entre les deux colonnes de l'arsenal.
private _btnW = 0.16 * safezoneW;
private _btnH = _guiH * 1.15;
private _btnX = safeZoneX + (safeZoneW - _btnW) / 2;
private _btnY = safeZoneY + 0.008;
private _tog = _display ctrlCreate ["COMSPEC_RscButtonAtakPrimary", 884400];
_tog ctrlSetPosition [_btnX, _btnY, _btnW, _btnH];
_tog ctrlSetText (("ATHENA · TENUES") call comspec_overwatch_connect_fnc_tr);
_tog ctrlSetTooltip (("Tenues de votre organisation : importer, partager, enfiler. Rien n'est synchronisé tant que le panneau est fermé.") call comspec_overwatch_connect_fnc_tr);
_tog ctrlSetFont _fB;
_tog ctrlSetFontHeight _fs;
_tog ctrlAddEventHandler ["ButtonClick", { ["toggle"] call comspec_overwatch_connect_fnc_arsenalOverlayAction; }];
_tog ctrlCommit 0;
_display setVariable ["COMSPEC_ArsenalToggle", _tog];

// Panneau.
private _px0 = safeZoneX + _sideW + _gap;
private _w = ((safeZoneW - 2 * (_sideW + _gap)) max (0.5 * safezoneW)) min (safeZoneW - 2 * _gap);
if ((_px0 + _w) > (safeZoneX + safeZoneW)) then { _px0 = safeZoneX + (safeZoneW - _w) / 2; };
private _y = _btnY + _btnH + 0.006;
private _h = (safeZoneY + safeZoneH) - _y - (_guiH * 2.6);
private _grp = _display ctrlCreate ["RscControlsGroupNoScrollbars", 884401];
_grp ctrlSetPosition [_px0, _y, _w, _h];
_grp ctrlShow false;
_grp ctrlCommit 0;
_display setVariable ["COMSPEC_ArsenalOverlay", _grp];
_display setVariable ["COMSPEC_ArsenalOverlayOpen", false];
_grp setVariable ["COMSPEC_ArsenalFonts", [_fR, _fB, _fL]];

private _mk = {
    params ["_cls", "_pos", ["_idc", -1]];
    private _c = _display ctrlCreate [_cls, _idc, _grp];
    _c ctrlSetPosition _pos;
    _c ctrlCommit 0;
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
// Bouton : classe de la charte ATAK (neutre, Primary vert, Danger rouge), texte traduit, action par variable.
private _btn = {
    params ["_pos", "_text", "_act", ["_cls", "COMSPEC_RscButtonAtak"], ["_tip", ""], ["_font", _fR]];
    private _b = [_cls, _pos] call _mk;
    if (_text isNotEqualTo "") then { _b ctrlSetText ((_text) call comspec_overwatch_connect_fnc_tr); };
    _b ctrlSetFont _font;
    _b ctrlSetFontHeight _fsS;
    if (_tip isNotEqualTo "") then { _b ctrlSetTooltip ((_tip) call comspec_overwatch_connect_fnc_tr); };
    _b setVariable ["act", _act];
    _b ctrlAddEventHandler ["ButtonClick", { params ["_c"]; [_c getVariable "act", _c getVariable ["arg", ""]] call comspec_overwatch_connect_fnc_arsenalOverlayAction; }];
    _b
};
private _pad = 0.012;
private _rowH = _guiH * 1.15;
(["RscText", [0, 0, _w, _h]] call _mk) ctrlSetBackgroundColor [0.030, 0.037, 0.033, 0.97];
(["RscText", [0, 0, _w, 2 * _pxH]] call _mk) ctrlSetBackgroundColor _green;

// En-tête : titre et sous-titre sur deux lignes séparées (le sous-titre n'est plus rogné), état, actualiser, fermer.
private _hy = _pad * 0.8;
[[_pad, _hy, _w * 0.5, _guiH * 1.3], "TENUES ATHENA", _fB, _guiH * 1.15, [0.93, 0.97, 0.94, 1]] call _txt;
[[_pad, _hy + _guiH * 1.3, _w * 0.55, _guiH * 0.95], "Votre arsenal et les tenues partagées par votre organisation", _fR, _guiH * 0.74, _muted] call _txt;
private _sq = _rowH * _pxW / _pxH;
private _refW = 0.085 * safezoneW;
[[_w - _pad - _sq, _hy, _sq, _rowH], "✕", "close", "COMSPEC_RscButtonAtak", "Fermer"] call _btn;
[[_w - _pad - _sq - _gap - _refW, _hy, _refW, _rowH], "ACTUALISER", "reload", "COMSPEC_RscButtonAtak", "Recharger les tenues depuis Athena"] call _btn;
private _status = ["RscStructuredText", [_w * 0.5, _hy + _rowH + 0.002, _w * 0.5 - _pad, _guiH * 1.0]] call _mk;
_grp setVariable ["COMSPEC_ArsenalStatus", _status];
private _hb = _hy + _guiH * 2.35;
(["RscText", [_pad, _hb, _w - 2 * _pad, _pxH]] call _mk) ctrlSetBackgroundColor [0.14, 0.19, 0.16, 1];

// Onglets (soulignés en vert quand actifs) et pastilles de synchro.
private _tabY = _hb + _pad * 0.7;
private _tabW = 0.11 * safezoneW;
private _tabL = [[_pad, _tabY, _tabW, _rowH], "MES TENUES", "tabLocal", "COMSPEC_RscButtonAtak", "Tenues enregistrées dans votre arsenal ACE, sur cet ordinateur", _fB] call _btn;
private _tabC = [[_pad + _tabW + _gap, _tabY, _tabW, _rowH], "COMMUNAUTÉ", "tabCloud", "COMSPEC_RscButtonAtak", "Tenues partagées par l'organisation sur le site", _fB] call _btn;
private _ulL = ["RscText", [_pad, _tabY + _rowH - 2 * _pxH, _tabW, 2 * _pxH]] call _mk;
private _ulC = ["RscText", [_pad + _tabW + _gap, _tabY + _rowH - 2 * _pxH, _tabW, 2 * _pxH]] call _mk;
{ _x ctrlSetBackgroundColor _green; } forEach [_ulL, _ulC];
_grp setVariable ["COMSPEC_ArsenalTabs", [_tabC, _tabL, _ulC, _ulL]];
private _pillPend = [[_w - _pad - 0.2, _tabY + _rowH * 0.1, 0.2, _rowH * 0.8], "", "pillPending", "COMSPEC_RscButtonAtak", "Afficher les tenues de votre arsenal qui ne sont pas à jour sur le site"] call _btn;
private _pillNew = [[_w - _pad - 0.4, _tabY + _rowH * 0.1, 0.18, _rowH * 0.8], "", "pillNew", "COMSPEC_RscButtonAtak", "Afficher les tenues partagées depuis votre dernière visite"] call _btn;
_grp setVariable ["COMSPEC_ArsenalPills", [_pillPend, _pillNew]];

// Filtres : recherche, état, tri.
private _fy = _tabY + _rowH + _pad * 0.7;
private _stateW = 0.12 * safezoneW;
private _sortW = 0.095 * safezoneW;
private _searchW = _w - 2 * _pad - _stateW - _sortW - 2 * _gap;
(["RscText", [_pad, _fy, _searchW, _rowH]] call _mk) ctrlSetBackgroundColor [0.065, 0.078, 0.07, 1];
(["RscText", [_pad, _fy + _rowH - _pxH, _searchW, _pxH]] call _mk) ctrlSetBackgroundColor [0.20, 0.28, 0.23, 1];
private _search = ["RscEdit", [_pad + 0.004, _fy, _searchW - 0.008, _rowH], 884420] call _mk;
_search ctrlSetFont _fR;
_search ctrlSetFontHeight _fsS;
_search ctrlSetTooltip (("Rechercher une tenue (nom, collection, auteur, état)") call comspec_overwatch_connect_fnc_tr);
_search ctrlAddEventHandler ["KeyUp", { ["filter"] call comspec_overwatch_connect_fnc_arsenalOverlayAction; }];
_grp setVariable ["COMSPEC_ArsenalSearch", _search];
private _ph = [[_pad + 0.006, _fy, _searchW - 0.012, _rowH], "Rechercher une tenue, une collection, un auteur…", _fR, _fsS, [0.45, 0.53, 0.48, 1]] call _txt;
_ph ctrlEnable false;
_grp setVariable ["COMSPEC_ArsenalSearchPh", _ph];
private _stateCombo = ["RscCombo", [_pad + _searchW + _gap, _fy, _stateW, _rowH], 884421] call _mk;
_stateCombo ctrlSetFont _fR;
_stateCombo ctrlSetFontHeight _fsS;
_stateCombo ctrlSetTooltip (("Filtrer par état de synchronisation avec le site") call comspec_overwatch_connect_fnc_tr);
_stateCombo ctrlAddEventHandler ["LBSelChanged", { ["stateFilter"] call comspec_overwatch_connect_fnc_arsenalOverlayAction; }];
_grp setVariable ["COMSPEC_ArsenalStateCombo", _stateCombo];
private _sortCombo = ["RscCombo", [_w - _pad - _sortW, _fy, _sortW, _rowH], 884423] call _mk;
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

// Collections en pastilles : posées par arsenalOverlayFill dans cette bande, flèches si elles débordent.
private _cy = _fy + _rowH + _pad * 0.6;
private _chipH = _rowH * 0.9;
_grp setVariable ["COMSPEC_ArsenalChipBand", [_pad, _cy, _w - 2 * _pad, _chipH]];
private _arrW = _chipH * _pxW / _pxH;
_grp setVariable ["COMSPEC_ArsenalChipArrows", [
    [[_pad, _cy, _arrW, _chipH], "‹", "chipPrev", "COMSPEC_RscButtonAtak", "Collections précédentes"] call _btn,
    [[_w - _pad - _arrW, _cy, _arrW, _chipH], "›", "chipNext", "COMSPEC_RscButtonAtak", "Collections suivantes"] call _btn
]];
_grp setVariable ["COMSPEC_ArsenalChips", []];

// Corps : liste à gauche (en-têtes de colonnes), fiche à droite.
private _bodyY = _cy + _chipH + _pad * 0.7;
private _footH = _rowH * 2 + _gap + _guiH * 0.2;
private _bodyH = _h - _bodyY - _footH - _pad;
private _listW = (_w - 2 * _pad - _gap) * 0.55;
(["RscText", [_pad, _bodyY, _listW, _bodyH]] call _mk) ctrlSetBackgroundColor [0.045, 0.055, 0.05, 1];
private _colPos = [0, 0.045, 0.58, 0.79];
_grp setVariable ["COMSPEC_ArsenalColPos", _colPos];
private _lhH = _guiH * 0.95;
private _colHead = [];
{
    private _cx = _pad + (_colPos select (_forEachIndex + 1)) * _listW + 0.004;
    private _nx = if (_forEachIndex < 2) then { _pad + (_colPos select (_forEachIndex + 2)) * _listW } else { _pad + _listW };
    _colHead pushBack ([[_cx, _bodyY + 0.002, _nx - _cx - 0.004, _lhH], _x, _fB, _guiH * 0.66, [0.50, 0.60, 0.54, 1]] call _txt);
} forEach ["TENUE", "COLLECTION", "ÉTAT"];
_grp setVariable ["COMSPEC_ArsenalColHead", _colHead];
(["RscText", [_pad, _bodyY + _lhH + 0.002, _listW, _pxH]] call _mk) ctrlSetBackgroundColor [0.14, 0.19, 0.16, 1];
private _list = ["COMSPEC_RscArsenalOutfitList", [_pad, _bodyY + _lhH + 0.004, _listW, _bodyH - _lhH - 0.006], 884404] call _mk;
_list ctrlSetFont _fR;
_list ctrlSetFontHeight _fs;
_list lnbSetColumnsPos _colPos;
_list ctrlAddEventHandler ["LBSelChanged", { params ["_c", "_i"]; ["select", _i] call comspec_overwatch_connect_fnc_arsenalOverlayAction; }];
_list ctrlAddEventHandler ["LBDblClick", { params ["_c", "_i"]; ["apply", _i] call comspec_overwatch_connect_fnc_arsenalOverlayAction; }];
_grp setVariable ["COMSPEC_ArsenalList", _list];

private _dx = _pad + _listW + _gap;
private _dw = _w - _dx - _pad;
private _ip = 0.010;
(["RscText", [_dx, _bodyY, _dw, _bodyH]] call _mk) ctrlSetBackgroundColor [0.055, 0.067, 0.06, 1];
private _iw = _dw - 2 * _ip;
private _ix = _dx + _ip;
private _dHead = ["RscStructuredText", [_ix, _bodyY + _ip * 0.6, _iw, _guiH * 2.5]] call _mk;
_grp setVariable ["COMSPEC_ArsenalDetailHead", _dHead];
// Badge d'état et phrase qui l'explique.
private _by = _bodyY + _ip * 0.6 + _guiH * 2.55;
private _badge = [[_ix, _by, 0.1, _guiH * 1.0], "", _fB, _guiH * 0.7, [0.03, 0.04, 0.035, 1], _green] call _txt;
_badge ctrlSetText "";
_grp setVariable ["COMSPEC_ArsenalBadge", _badge];
private _note = ["RscStructuredText", [_ix, _by + _guiH * 1.1, _iw, _guiH * 1.7]] call _mk;
_grp setVariable ["COMSPEC_ArsenalBadgeNote", _note];
// Pièces de la tenue : 2 rangées de 4 cases, icône et légende (Arme, Tenue…).
private _cg = 0.006;
private _cellW = (_iw - 3 * _cg) / 4;
private _picH = (_cellW * _pxH / _pxW * 0.62) min (_guiH * 2.6);
private _capH = _guiH * 0.8;
private _cellH = _picH + _capH;
private _gy = _by + _guiH * 2.9;
private _pics = [];
for "_i" from 0 to 7 do {
    private _cx = _ix + (_i mod 4) * (_cellW + _cg);
    private _cy2 = _gy + (floor (_i / 4)) * (_cellH + _cg);
    private _cb = ["RscText", [_cx, _cy2, _cellW, _cellH]] call _mk;
    _cb ctrlSetBackgroundColor [0.075, 0.09, 0.08, 1];
    private _p = ["RscPictureKeepAspect", [_cx + _cellW * 0.08, _cy2 + _picH * 0.06, _cellW * 0.84, _picH * 0.88], 884408 + _i] call _mk;
    private _cap = [[_cx, _cy2 + _picH, _cellW, _capH], "", _fR, _guiH * 0.62, [0.62, 0.70, 0.65, 1]] call _txt;
    _cap ctrlSetText "";
    { _x ctrlShow false; } forEach [_cb, _p, _cap];
    _pics pushBack [_cb, _p, _cap];
};
_grp setVariable ["COMSPEC_ArsenalPreviewPics", _pics];
// Actions de la fiche : Enfiler (pleine largeur), puis Envoyer / Importer et Supprimer côte à côte.
private _actY = _bodyY + _bodyH - _ip - (_rowH * 2 + _gap);
private _ny = _gy + 2 * (_cellH + _cg) + 0.004;
private _namesGrp = ["RscControlsGroup", [_ix, _ny, _iw, (_actY - _ny - _gap) max _guiH]] call _mk;
private _names = _display ctrlCreate ["RscStructuredText", -1, _namesGrp];
_names ctrlSetPosition [0, 0, _iw - 0.012, _guiH];
_names ctrlCommit 0;
_grp setVariable ["COMSPEC_ArsenalPreviewNames", _names];
private _half = (_iw - _gap) / 2;
_grp setVariable ["COMSPEC_ArsenalDetailBtns", [
    [[_ix, _actY, _iw, _rowH], "ENFILER", "apply", "COMSPEC_RscButtonAtakPrimary", "Mettre cette tenue sur le mannequin (double-clic dans la liste)", _fB] call _btn,
    [[_ix, _actY + _rowH + _gap, _half, _rowH], "", "keep", "COMSPEC_RscButtonAtak", "", _fB] call _btn,
    [[_ix + _half + _gap, _actY + _rowH + _gap, _half, _rowH], "", "remove", "COMSPEC_RscButtonAtakDanger"] call _btn
]];

// Pied : action groupée, enregistrement de la tenue portée, aide.
private _fty = _h - _pad * 0.6 - _footH;
private _fw = (_w - 2 * _pad - _gap) / 2;
_grp setVariable ["COMSPEC_ArsenalBulk", [[_pad, _fty, _fw, _rowH], "", "bulk", "COMSPEC_RscButtonAtak", "", _fB] call _btn];
private _sx = _pad + _fw + _gap;
private _saveW = 0.12 * safezoneW;
private _editW = _fw - _saveW - _gap;
(["RscText", [_sx, _fty, _editW, _rowH]] call _mk) ctrlSetBackgroundColor [0.065, 0.078, 0.07, 1];
(["RscText", [_sx, _fty + _rowH - _pxH, _editW, _pxH]] call _mk) ctrlSetBackgroundColor [0.20, 0.28, 0.23, 1];
private _nameEdit = ["RscEdit", [_sx + 0.004, _fty, _editW - 0.008, _rowH], 884422] call _mk;
_nameEdit ctrlSetFont _fR;
_nameEdit ctrlSetFontHeight _fsS;
_nameEdit ctrlSetText (profileNamespace getVariable ["COMSPEC_ArsenalLastSaveName", ""]); // i18n:brut
_nameEdit ctrlSetTooltip (("Nom de la tenue à enregistrer (ex. SOAR - Breacher). Le préfixe avant « - » sert de collection.") call comspec_overwatch_connect_fnc_tr);
_grp setVariable ["COMSPEC_ArsenalSaveName", _nameEdit];
private _nph = [[_sx + 0.006, _fty, _editW - 0.012, _rowH], "Nom de la tenue portée (ex. SOAR - Breacher)…", _fR, _fsS, [0.45, 0.53, 0.48, 1]] call _txt;
_nph ctrlEnable false;
_nph ctrlShow ((ctrlText _nameEdit) isEqualTo "");
_nameEdit setVariable ["ph", _nph];
_nameEdit ctrlAddEventHandler ["KeyUp", { params ["_c"]; (_c getVariable ["ph", controlNull]) ctrlShow ((ctrlText _c) isEqualTo ""); }];
[[_w - _pad - _saveW, _fty, _saveW, _rowH], "ENREGISTRER ET ENVOYER", "saveCurrent", "COMSPEC_RscButtonAtakPrimary", "Enregistre la tenue portée par le mannequin dans votre arsenal et l'envoie au site pour l'organisation", _fB] call _btn;
private _help = ["RscStructuredText", [_pad, _fty + _rowH + _gap, _w - 2 * _pad, _rowH]] call _mk;
_grp setVariable ["COMSPEC_ArsenalHint", _help];
_grp setVariable ["COMSPEC_ArsenalSetHint", {
    params ["_g", "_text"];
    private _c = _g getVariable ["COMSPEC_ArsenalHint", controlNull];
    if (!isNull _c) then { _c ctrlSetStructuredText parseText (format ["<t size='0.8' color='#8fa596'>%1</t>", (_text) call comspec_overwatch_connect_fnc_tr]); };
}];
_grp setVariable ["COMSPEC_ArsenalTab", profileNamespace getVariable ["COMSPEC_ArsenalTab", "local"]];
_grp setVariable ["COMSPEC_ArsenalColl", ""];
_grp setVariable ["COMSPEC_ArsenalStateKey", "all"];
_grp setVariable ["COMSPEC_ArsenalChipOffset", 0];
[_display, [], ""] call comspec_overwatch_connect_fnc_arsenalOverlayPreview;
