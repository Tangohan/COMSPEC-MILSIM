/*
    Bouton Athena de l'ACE Arsenal et panneau « Tenues Athena ».

    Fermé par défaut ; aucune synchronisation tant qu'il n'est pas ouvert. Une seule liste à la fois,
    en onglets (Communauté / Mes tenues), avec recherche et filtre par collection ; à droite la fiche
    de la tenue choisie (équipement en icônes, actions Enfiler / Importer / Partager / Supprimer) ;
    en bas les actions groupées et l'enregistrement de la tenue portée.
    Le panneau se place entre les deux colonnes de l'arsenal pour ne rien masquer.
    Actions : comspec_overwatch_connect_fnc_arsenalOverlayAction. Contenu : arsenalOverlayRefresh / arsenalOverlayFill.
*/
params [["_display", displayNull, [displayNull]]];
if (isNull _display) exitWith {};

{ private _c = _display getVariable [_x, controlNull]; if (!isNull _c) then { ctrlDelete _c; }; } forEach ["COMSPEC_ArsenalOverlay", "COMSPEC_ArsenalToggle"];
uiNamespace setVariable ["ace_arsenal_display", _display];

private _gridW = ((safeZoneW / safeZoneH) min 1.2) / 40;
private _sideW = 13 * _gridW;
private _gap = 0.010;
private _guiH = ((((safezoneW / safezoneH) min 1.2) / 1.2) / 25);
private _fs = _guiH * 0.78;
private _fsS = _guiH * 0.66;
private _pxW = pixelW; private _pxH = pixelH;

// Bouton d'ouverture : en haut, entre les deux colonnes de l'arsenal.
private _btnW = 0.16 * safezoneW;
private _btnH = _guiH * 1.15;
private _btnX = safeZoneX + (safeZoneW - _btnW) / 2;
private _btnY = safeZoneY + 0.008;
private _tog = _display ctrlCreate ["RscButton", 884400];
_tog ctrlSetPosition [_btnX, _btnY, _btnW, _btnH];
_tog ctrlSetText "ATHENA · TENUES";
_tog ctrlSetTooltip "Tenues de votre organisation : importer, partager, enfiler. Rien n'est synchronisé tant que le panneau est fermé.";
_tog ctrlSetFont "PuristaSemibold";
_tog ctrlSetFontHeight _fs;
_tog ctrlSetBackgroundColor [0.09, 0.30, 0.25, 0.96];
_tog ctrlSetTextColor [0.88, 1, 0.95, 1];
_tog ctrlAddEventHandler ["ButtonClick", { ["toggle"] call comspec_overwatch_connect_fnc_arsenalOverlayAction; }];
_tog ctrlCommit 0;
_display setVariable ["COMSPEC_ArsenalToggle", _tog];

// Panneau.
private _x = safeZoneX + _sideW + _gap;
private _w = (safeZoneW - 2 * (_sideW + _gap)) max (0.5 * safezoneW);
if ((_x + _w) > (safeZoneX + safeZoneW)) then { _x = safeZoneX + (safeZoneW - _w) / 2; };
private _y = _btnY + _btnH + 0.006;
private _h = (safeZoneY + safeZoneH) - _y - (_guiH * 2.6);
private _grp = _display ctrlCreate ["RscControlsGroupNoScrollbars", 884401];
_grp ctrlSetPosition [_x, _y, _w, _h];
_grp ctrlShow false;
_grp ctrlCommit 0;
_display setVariable ["COMSPEC_ArsenalOverlay", _grp];
_display setVariable ["COMSPEC_ArsenalOverlayOpen", false];

private _mk = {
    params ["_cls", "_pos", ["_idc", -1]];
    private _c = _display ctrlCreate [_cls, _idc, _grp];
    _c ctrlSetPosition _pos;
    _c ctrlCommit 0;
    _c
};
private _btn = {
    params ["_pos", "_text", "_act", "_rgba", ["_tip", ""], ["_font", "PuristaMedium"]];
    private _b = ["RscButton", _pos] call _mk;
    _b ctrlSetText _text;
    _b ctrlSetFont _font;
    _b ctrlSetFontHeight _fsS;
    _b ctrlSetBackgroundColor _rgba;
    _b ctrlSetTextColor [0.92, 0.97, 0.95, 1];
    if (_tip isNotEqualTo "") then { _b ctrlSetTooltip _tip; };
    _b setVariable ["act", _act];
    _b ctrlAddEventHandler ["ButtonClick", { params ["_c"]; [_c getVariable "act"] call comspec_overwatch_connect_fnc_arsenalOverlayAction; }];
    _b
};
private _pad = 0.012;
private _bg = ["RscText", [0, 0, _w, _h]] call _mk;
_bg ctrlSetBackgroundColor [0.035, 0.045, 0.05, 0.97];
private _acc = ["RscText", [0, 0, _w, 2 * _pxH]] call _mk;
_acc ctrlSetBackgroundColor [0.32, 0.78, 0.66, 1];

// En-tête : titre, état, actualiser, fermer.
private _rowH = _guiH * 1.1;
private _title = ["RscStructuredText", [_pad, _pad * 0.6, _w * 0.6, _guiH * 1.6]] call _mk;
_title ctrlSetStructuredText parseText "<t size='1.15' font='PuristaBold' color='#e2f7ef'>TENUES ATHENA</t><br/><t size='0.8' color='#7f9c94'>Collection de votre organisation</t>";
private _status = ["RscStructuredText", [_w * 0.45, _pad * 0.6, _w * 0.55 - _pad - 2 * (_rowH * _pxW / _pxH) - _pad * 2 - 0.09, _guiH * 1.6]] call _mk;
_grp setVariable ["COMSPEC_ArsenalStatus", _status];
private _sq = _rowH * _pxW / _pxH;
[[_w - _pad - _sq, _pad * 0.6, _sq, _rowH], "✕", "close", [0.25, 0.10, 0.10, 1], "Fermer"] call _btn;
[[_w - _pad * 2 - _sq - 0.09, _pad * 0.6, 0.09, _rowH], "ACTUALISER", "reload", [0.10, 0.17, 0.20, 1], "Recharger les tenues depuis Athena"] call _btn;

// Onglets, recherche, collection.
private _tabY = _pad * 0.6 + _guiH * 1.75;
private _tabW = 0.12 * safezoneW;
private _tabC = [[_pad, _tabY, _tabW, _rowH], "COMMUNAUTÉ", "tabCloud", [0.10, 0.17, 0.20, 1], "Tenues partagées par l'organisation", "PuristaSemibold"] call _btn;
private _tabL = [[_pad + _tabW + 0.004, _tabY, _tabW, _rowH], "MES TENUES", "tabLocal", [0.10, 0.17, 0.20, 1], "Tenues enregistrées dans votre arsenal ACE, sur cet ordinateur", "PuristaSemibold"] call _btn;
_grp setVariable ["COMSPEC_ArsenalTabs", [_tabC, _tabL]];
private _sx = _pad + 2 * _tabW + 0.016;
private _comboW = 0.13 * safezoneW;
private _searchW = _w - _sx - _comboW - _pad - 0.008;
private _sbg = ["RscText", [_sx, _tabY, _searchW, _rowH]] call _mk;
_sbg ctrlSetBackgroundColor [0.02, 0.026, 0.03, 1];
private _search = ["RscEdit", [_sx + 0.004, _tabY, _searchW - 0.008, _rowH], 884420] call _mk;
_search ctrlSetFontHeight _fsS;
_search ctrlSetTooltip "Rechercher une tenue (nom, collection, auteur)";
_search ctrlAddEventHandler ["KeyUp", { ["filter"] call comspec_overwatch_connect_fnc_arsenalOverlayAction; }];
_grp setVariable ["COMSPEC_ArsenalSearch", _search];
private _ph = ["RscText", [_sx + 0.006, _tabY, _searchW - 0.012, _rowH]] call _mk;
_ph ctrlSetText "Rechercher…";
_ph ctrlSetFontHeight _fsS;
_ph ctrlSetTextColor [0.5, 0.6, 0.57, 1];
_ph ctrlEnable false;
_grp setVariable ["COMSPEC_ArsenalSearchPh", _ph];
private _combo = ["RscCombo", [_w - _pad - _comboW, _tabY, _comboW, _rowH], 884421] call _mk;
_combo ctrlSetFontHeight _fsS;
_combo ctrlAddEventHandler ["LBSelChanged", { ["filter"] call comspec_overwatch_connect_fnc_arsenalOverlayAction; }];
_grp setVariable ["COMSPEC_ArsenalCombo", _combo];

// Corps : liste à gauche, fiche à droite.
private _bodyY = _tabY + _rowH + _pad * 0.6;
private _footH = _rowH * 2 + 0.006;
private _bodyH = _h - _bodyY - _footH - _pad;
private _listW = _w * 0.56;
private _list = ["RscListNBox", [_pad, _bodyY, _listW - _pad, _bodyH], 884404] call _mk;
_list ctrlSetBackgroundColor [0.022, 0.03, 0.034, 1];
_list ctrlSetFontHeight _fs;
_list lnbSetColumnsPos [0, 0.5, 0.78];
_list ctrlAddEventHandler ["LBSelChanged", { params ["_c", "_i"]; ["select", _i] call comspec_overwatch_connect_fnc_arsenalOverlayAction; }];
_list ctrlAddEventHandler ["LBDblClick", { params ["_c", "_i"]; ["apply", _i] call comspec_overwatch_connect_fnc_arsenalOverlayAction; }];
_grp setVariable ["COMSPEC_ArsenalList", _list];

private _dx = _listW + 0.004;
private _dw = _w - _dx - _pad;
private _dbg = ["RscText", [_dx, _bodyY, _dw, _bodyH]] call _mk;
_dbg ctrlSetBackgroundColor [0.05, 0.065, 0.07, 1];
private _dHead = ["RscStructuredText", [_dx + _pad * 0.6, _bodyY + _pad * 0.5, _dw - _pad * 1.2, _guiH * 2.4]] call _mk;
_grp setVariable ["COMSPEC_ArsenalDetailHead", _dHead];
// Icônes d'équipement : 2 rangées de 4 cases.
private _cell = ((_dw - _pad * 1.2 - 3 * 0.006) / 4) min (_guiH * 3.2 * _pxW / _pxH);
private _cellH = _cell * _pxH / _pxW;
private _iy = _bodyY + _pad * 0.5 + _guiH * 2.5;
private _pics = [];
for "_i" from 0 to 7 do {
    private _cx = _dx + _pad * 0.6 + (_i mod 4) * (_cell + 0.006);
    private _cy = _iy + (floor (_i / 4)) * (_cellH + 0.006);
    private _cb = ["RscText", [_cx, _cy, _cell, _cellH]] call _mk;
    _cb ctrlSetBackgroundColor [0.08, 0.1, 0.11, 1];
    _cb ctrlShow false;
    private _p = ["RscPictureKeepAspect", [_cx + _cell * 0.08, _cy + _cellH * 0.08, _cell * 0.84, _cellH * 0.84], 884408 + _i] call _mk;
    _p ctrlShow false;
    _pics pushBack [_cb, _p];
};
_grp setVariable ["COMSPEC_ArsenalPreviewPics", _pics];
private _actY = _bodyY + _bodyH - (_rowH * 3 + 0.012) - _pad * 0.5;
private _ny = _iy + 2 * (_cellH + 0.006) + 0.004;
private _names = ["RscStructuredText", [_dx + _pad * 0.6, _ny, _dw - _pad * 1.2, (_actY - _ny - 0.004) max _guiH]] call _mk;
_grp setVariable ["COMSPEC_ArsenalPreviewNames", _names];
private _aw = _dw - _pad * 1.2;
private _ax = _dx + _pad * 0.6;
_grp setVariable ["COMSPEC_ArsenalDetailBtns", [
    [[_ax, _actY, _aw, _rowH], "ENFILER", "apply", [0.12, 0.42, 0.30, 1], "Mettre cette tenue sur le mannequin (double-clic dans la liste)", "PuristaSemibold"] call _btn,
    [[_ax, _actY + _rowH + 0.006, _aw, _rowH], "", "keep", [0.12, 0.22, 0.38, 1]] call _btn,
    [[_ax, _actY + 2 * (_rowH + 0.006), _aw, _rowH], "", "remove", [0.32, 0.12, 0.12, 1]] call _btn
]];

// Pied : actions groupées et enregistrement de la tenue portée.
private _fy = _h - _footH - _pad * 0.5;
private _fw = (_w - 2 * _pad - 0.008) / 2;
_grp setVariable ["COMSPEC_ArsenalBulk", [[_pad, _fy, _fw, _rowH], "", "bulk", [0.10, 0.17, 0.20, 1]] call _btn];
private _nbg = ["RscText", [_pad + _fw + 0.008, _fy, _fw * 0.6, _rowH]] call _mk;
_nbg ctrlSetBackgroundColor [0.02, 0.026, 0.03, 1];
private _nameEdit = ["RscEdit", [_pad + _fw + 0.012, _fy, _fw * 0.6 - 0.008, _rowH], 884422] call _mk;
_nameEdit ctrlSetFontHeight _fsS;
_nameEdit ctrlSetText (profileNamespace getVariable ["COMSPEC_ArsenalLastSaveName", ""]);
_nameEdit ctrlSetTooltip "Nom de la tenue à enregistrer (ex. SOAR - Breacher). Le préfixe avant « - » sert de collection.";
_grp setVariable ["COMSPEC_ArsenalSaveName", _nameEdit];
[[_pad + _fw * 1.6 + 0.012, _fy, _fw * 0.4 - 0.004, _rowH], "ENREGISTRER ET PARTAGER", "saveCurrent", [0.12, 0.42, 0.30, 1], "Enregistre la tenue portée par le mannequin dans votre arsenal et la partage à l'organisation"] call _btn;
private _help = ["RscStructuredText", [_pad, _fy + _rowH + 0.004, _w - 2 * _pad, _rowH]] call _mk;
_grp setVariable ["COMSPEC_ArsenalHint", _help];
_grp setVariable ["COMSPEC_ArsenalSetHint", {
    params ["_g", "_text"];
    private _c = _g getVariable ["COMSPEC_ArsenalHint", controlNull];
    if (!isNull _c) then { _c ctrlSetStructuredText parseText format ["<t size='0.78' color='#8aa8a0'>%1</t>", _text]; };
}];
_grp setVariable ["COMSPEC_ArsenalTab", profileNamespace getVariable ["COMSPEC_ArsenalTab", "cloud"]];
[_display, [], ""] call comspec_overwatch_connect_fnc_arsenalOverlayPreview;
