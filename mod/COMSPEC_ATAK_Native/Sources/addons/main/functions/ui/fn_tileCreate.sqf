/*
    Tuile d'application : fond, icône, libellé, pastille, et un bouton transparent par-dessus.
    _group = controlNull pour créer sur le display (dock, rail), sinon dans le controls group.
    Retourne la liste des contrôles créés (à suivre par l'appelant pour la suppression).
*/
params ["_display", "_group", "_pos", "_icon", ["_label", ""], ["_badge", 0], ["_page", "LAUNCHER"], ["_font", 0.02]];
disableSerialization;
_pos params ["_x0", "_y0", "_w", "_h"];
private _made = [];
private _mk = {
    params ["_class", "_p"];
    private _c = if (isNull _group) then { _display ctrlCreate [_class, -1] } else { _display ctrlCreate [_class, -1, _group] };
    _c ctrlSetPosition _p;
    _c ctrlCommit 0;
    _made pushBack _c;
    _c
};

["COMSPEC_RscTile", [_x0, _y0, _w, _h]] call _mk;
private _labelH = [_font * 1.35, 0] select (_label isEqualTo "");
private _iconH = ((_h - _labelH) * 0.58) min (_w * pixelH / pixelW * 0.7);
private _iconW = _iconH * pixelW / pixelH;
private _pic = ["COMSPEC_RscPicture", [_x0 + (_w - _iconW) / 2, _y0 + (_h - _labelH - _iconH) / 2, _iconW, _iconH]] call _mk;
_pic ctrlSetText _icon;
_pic ctrlSetTextColor [0.93, 0.97, 0.94, 1];
if (_labelH > 0) then {
    private _t = ["COMSPEC_RscTextCenter", [_x0, _y0 + _h - _labelH - _h * 0.06, _w, _labelH]] call _mk;
    _t ctrlSetFontHeight _font;
    _t ctrlSetText _label;
    // Libellé trop long pour la tuile (« Guerre électronique ») : police réduite jusqu'à 70 %.
    private _fh = _font;
    while { (ctrlTextWidth _t) > _w * 0.94 && {_fh > _font * 0.7} } do { _fh = _fh * 0.92; _t ctrlSetFontHeight _fh; };
};
if (_badge > 0) then {
    private _bh = _font * 1.1;
    private _bw = _bh * pixelW / pixelH * 1.4;
    private _b = ["COMSPEC_RscBadge", [_x0 + _w - _bw - _w * 0.04, _y0 + _h * 0.05, _bw, _bh]] call _mk;
    _b ctrlSetFontHeight (_font * 0.9);
    _b ctrlSetText ([str _badge, "99+"] select (_badge > 99));
};
private _btn = ["COMSPEC_RscButtonOverlay", [_x0, _y0, _w, _h]] call _mk;
_btn setVariable ["page", _page];
_btn ctrlSetTooltip _label;
// La navigation supprime cette tuile : on la diffère d'une image pour ne pas détruire le bouton dans son propre handler.
_btn ctrlAddEventHandler ["ButtonClick", {
    params ["_c"];
    [{ [_this] call comspec_atak_native_fnc_navigate; }, _c getVariable ["page", "LAUNCHER"]] call CBA_fnc_execNextFrame;
}];
_made
