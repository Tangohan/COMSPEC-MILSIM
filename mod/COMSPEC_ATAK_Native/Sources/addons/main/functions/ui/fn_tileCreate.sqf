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
    [_c, _class] call comspec_atak_native_fnc_fontApply;
    _c ctrlSetPosition _p;
    _c ctrlCommit 0;
    _made pushBack _c;
    _c
};

["COMSPEC_RscTile", [_x0, _y0, _w, _h]] call _mk;
// Libellé : une ligne à la taille du lanceur, réduite jusqu'à 70 % si elle dépasse (« Guerre électronique »), sans
// descendre sous le plancher des légendes (fn_layoutGet). Encore trop long à ce plancher : deux lignes, coupées à
// l'espace (« Marquer / tracking »), au lieu d'un texte de 9 px illisible.
private _labelTxt = (_label) call comspec_atak_native_fnc_tr;
private _maxW = _w * 0.94;
private _fh = _font;
private _two = false;
if (_label isNotEqualTo "") then {
    // Mesure sur un contrôle temporaire (même police que le libellé), supprimé aussitôt.
    private _m = if (isNull _group) then { _display ctrlCreate ["COMSPEC_RscTextCenter", -1] } else { _display ctrlCreate ["COMSPEC_RscTextCenter", -1, _group] };
    [_m, "COMSPEC_RscTextCenter"] call comspec_atak_native_fnc_fontApply;
    _m ctrlSetPosition [_x0, _y0, _w * 4, _font * 1.35];
    _m ctrlCommit 0;
    _m ctrlSetText _labelTxt; // i18n:brut (déjà traduit)
    _m ctrlSetFontHeight _font;
    private _tw = ctrlTextWidth _m;
    if (_tw > _maxW) then {
        _fh = ((_font * _maxW / _tw) max (_font * 0.7)) max COMSPEC_ATAK_FontMin;
        _m ctrlSetFontHeight _fh;
        if ((ctrlTextWidth _m) > _maxW && {(_labelTxt find " ") > 0}) then {
            _two = true;
            _fh = (_font * 0.85) max COMSPEC_ATAK_FontMin;
        };
    };
    ctrlDelete _m;
};
private _labelH = [[_font * 1.35, _fh * 2.15] select _two, 0] select (_label isEqualTo "");
private _iconH = ((_h - _labelH) * 0.58) min (_w * (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]) * 0.7);
private _iconW = _iconH * pixelW / pixelH;
private _pic = ["COMSPEC_RscPicture", [_x0 + (_w - _iconW) / 2, _y0 + (_h - _labelH - _iconH) / 2, _iconW, _iconH]] call _mk;
_pic ctrlSetText ((_icon) call comspec_atak_native_fnc_tr);
_pic ctrlSetTextColor [0.93, 0.97, 0.94, 1];
if (_labelH > 0) then {
    private _t = [["COMSPEC_RscTextCenter", "COMSPEC_RscTextCenterMulti"] select _two, [_x0 + _w * 0.03, _y0 + _h - _labelH - _h * 0.06, _w * 0.94, _labelH]] call _mk;
    _t ctrlSetFontHeight _fh;
    _t ctrlSetText _labelTxt; // i18n:brut (déjà traduit)
};
if (_badge > 0) then {
    private _bh = _font * 1.1;
    private _bw = _bh * pixelW / pixelH * 1.4;
    private _b = ["COMSPEC_RscBadge", [_x0 + _w - _bw - _w * 0.04, _y0 + _h * 0.05, _bw, _bh]] call _mk;
    _b ctrlSetFontHeight (_font * 0.9);
    _b ctrlSetText ((([str _badge, "99+"] select (_badge > 99))) call comspec_atak_native_fnc_tr);
};
private _btn = ["COMSPEC_RscButtonOverlay", [_x0, _y0, _w, _h]] call _mk;
_btn setVariable ["page", _page];
_btn ctrlSetTooltip ((_label) call comspec_atak_native_fnc_tr);
// La navigation supprime cette tuile : on la diffère d'une image pour ne pas détruire le bouton dans son propre handler.
_btn ctrlAddEventHandler ["ButtonClick", {
    params ["_c"];
    [{ [_this] call comspec_atak_native_fnc_navigate; }, _c getVariable ["page", "LAUNCHER"]] call CBA_fnc_execNextFrame;
}];
_made
