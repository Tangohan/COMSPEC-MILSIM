/*
    Palette du mode MARQUEUR (au-dessus de la carte) :
    - camp : ENNEMI, AMI, NEUTRE, INCONNU ou TACTIQUE (repères d'ordre : objectif, danger, LZ…) ;
    - grille d'icônes du type (infanterie, blindé, artillerie, PC, médical…) ;
    - couleur (AUTO = couleur du camp) et taille ;
    - AUTRES : tous les marqueurs Arma et des mods, en liste ;
    - canal (Général par défaut), « à la suite » (la palette reste après VALIDER), « titre et note » après la pose ;
    - en-tête : VALIDER pose au point choisi d'un clic droit (fn_markerPlace), replier, fermer.
    Les icônes sont montrées sur des tuiles claires, dans la couleur qu'elles auront sur la carte.
    Params : [[x, y, w, h] zone disponible]
*/
params ["_rect"];
disableSerialization;
_rect params ["_x0", "_y0", "_w", "_hMax"];
private _l = [] call comspec_atak_native_fnc_layoutGet;
private _fs = _l get "fontSmall";
private _pad = _l get "pad";
private _ratio = (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]);
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _mk = { params ["_class", "_pos", ["_text", ""]]; [_class, _pos, _text, false] call comspec_atak_native_fnc_pageCtrl };
private _redraw = { [{ ["MAP"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; };
uiNamespace setVariable ["COMSPEC_ATAK_MarkerPick", {
    params ["_key", "_val"];
    private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
    _s set [_key, _val];
    if (_key isEqualTo "markerAff") then { _s set ["markerType", ""]; };
    if (_key isEqualTo "markerLibCat") then { _s set ["markerType", ""]; };
    [{ ["MAP"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
}];
([] call comspec_atak_native_fnc_markerPaletteData) params ["_affs", "_typesByAff"];
private _aff = _s getOrDefault ["markerAff", "o"];
private _types = _typesByAff getOrDefault [_aff, []];
private _type = _s getOrDefault ["markerType", ""];
if ((_types findIf { (_x select 0) isEqualTo _type }) < 0 && {!isClass (configFile >> "CfgMarkers" >> _type)}) then { _type = (_types param [0, ["mil_dot"]]) select 0; _s set ["markerType", _type]; };
private _affColor = (_affs select ((_affs findIf { (_x select 0) isEqualTo _aff }) max 0)) select 3;
private _rh = _fs * 1.55;
private _y = _y0;
private _bg = ["COMSPEC_RscMapPanel", [_x0 - _pad, _y0 - _pad, _w + 2 * _pad, _hMax]] call _mk;
private _green = [0.36, 0.78, 0.42, 1];
private _colorKey = _s getOrDefault ["markerColor", "AUTO"];
private _swatches = [["Auto (camp)", "AUTO"], ["Rouge", "ColorRed"], ["Bleu", "ColorBlue"], ["Vert", "ColorGreen"], ["Jaune", "ColorYellow"], ["Orange", "ColorOrange"], ["Noir", "ColorBlack"], ["Blanc", "ColorWhite"]];
private _cfgRgba = { params ["_cls"]; (getArray (configFile >> "CfgMarkerColors" >> _cls >> "color")) apply { if (_x isEqualType "") then { call compile _x } else { _x } } };
private _iconRgba = if (_colorKey isEqualTo "AUTO") then { _affColor } else { private _c = [_colorKey] call _cfgRgba; [_affColor, _c] select ((count _c) isEqualTo 4) };
// Tuile claire pour toutes les couleurs, sombre seulement pour le blanc.
private _tile = { params ["_c", ["_sel", false]]; if (((_c select 0) + (_c select 1) + (_c select 2)) > 2.4) then { [[0.10, 0.12, 0.11, 0.95], [0.16, 0.30, 0.19, 1]] select _sel } else { [[0.66, 0.69, 0.67, 0.95], [0.80, 0.90, 0.81, 1]] select _sel } };
private _short = createHashMapFromArray [["Poste de commandement", "PC"], ["Reconnaissance", "Reco"], ["Anti-aérien", "Sol-air"], ["Hélicoptère", "Hélico"], ["Point de ralliement", "Ralliement"], ["Champ de mines", "Mines"], ["Triangle plat", "Triangle"]];

// 1. En-tête : aperçu tel qu'il sera posé, résumé (texte structuré), VALIDER au point choisi, replier, fermer.
private _size = _s getOrDefault ["markerSize", 1];
private _sizes = [["S", 0.7], ["M", 1], ["L", 1.4], ["XL", 2]];
private _fold = profileNamespace getVariable ["COMSPEC_ATAK_MarkerFold", false];
private _anchor = _s getOrDefault ["markerAnchor", []];
private _hasAnchor = (count _anchor) >= 2;
private _chan = ["channel"] call comspec_atak_native_fnc_markerPlace;
private _ph = _rh * 1.7;
private _pw = _ph * _ratio;
private _typeLabel = ((_types select ((_types findIf { (_x select 0) isEqualTo _type }) max 0)) param [1, getText (configFile >> "CfgMarkers" >> _type >> "name")]);
if !((_types findIf { (_x select 0) isEqualTo _type }) >= 0) then { _typeLabel = getText (configFile >> "CfgMarkers" >> _type >> "name"); };
private _pt = ["COMSPEC_RscText", [_x0, _y, _pw, _ph]] call _mk;
_pt ctrlSetBackgroundColor ([_iconRgba] call _tile);
private _pi = (0.45 + 0.25 * _size) min 0.95;
private _pic = ["COMSPEC_RscIcon", [_x0 + _pw * (1 - _pi) / 2, _y + _ph * (1 - _pi) / 2, _pw * _pi, _ph * _pi], getText (configFile >> "CfgMarkers" >> _type >> "icon")] call _mk;
_pic ctrlSetTextColor _iconRgba;
private _affName = (_affs select ((_affs findIf { (_x select 0) isEqualTo _aff }) max 0)) select 1;
private _colName = (_swatches select ((_swatches findIf { (_x select 1) isEqualTo _colorKey }) max 0)) select 0;
// Boutons carrés à droite (replier / fermer), VALIDER à leur gauche.
private _ib = _rh;
private _ibW = _ib / _ratio;
private _iconBtn = {
    params ["_xx", "_icon", "_tip", "_code", ["_col", [0.86, 0.91, 0.88, 1]]];
    private _f = ["COMSPEC_RscText", [_xx, _y + (_ph - _ib) / 2, _ibW, _ib]] call _mk;
    _f ctrlSetBackgroundColor [0.10, 0.12, 0.11, 0.95];
    private _i = ["COMSPEC_RscIcon", [_xx + _ibW * 0.2, _y + (_ph - _ib) / 2 + _ib * 0.2, _ibW * 0.6, _ib * 0.6], "\z\comspec_atak_native\addons\main\data\" + _icon] call _mk;
    _i ctrlSetTextColor _col;
    private _b = ["COMSPEC_RscButtonOverlay", [_xx, _y + (_ph - _ib) / 2, _ibW, _ib]] call _mk;
    _b ctrlSetTooltip ((_tip) call comspec_atak_native_fnc_tr);
    _b ctrlAddEventHandler ["ButtonClick", _code];
};
private _closeX = _x0 + _w - _ibW;
private _foldX = _closeX - _ibW - _pad / 2;
[_closeX, "ui_close.paa", "Fermer la palette (retour à la sélection)", { ["close"] call comspec_atak_native_fnc_markerPlace; }, [0.95, 0.45, 0.40, 1]] call _iconBtn;
[_foldX, ["ui_chevron_up.paa", "ui_chevron_down.paa"] select _fold, ["Replier la palette", "Déplier la palette"] select _fold, { ["fold"] call comspec_atak_native_fnc_markerPlace; }] call _iconBtn;
private _vw = (_w * 0.2) max (_fs * 6 / _ratio * 0.6);
private _vx = _foldX - _pad / 2 - _vw;
private _vb = ["COMSPEC_RscButtonPrimary", [_vx, _y + (_ph - _rh * 1.15) / 2, _vw, _rh * 1.15], "VALIDER"] call _mk;
_vb ctrlSetFont (COMSPEC_ATAK_FontSet select 1);
_vb ctrlSetFontHeight ((_fs * 0.95) max COMSPEC_ATAK_FontMin);
_vb ctrlSetTooltip (([
    "Clic droit sur la carte pour choisir le point, puis VALIDER",
    "Poser le marqueur au point choisi (clic droit)"
] select _hasAnchor) call comspec_atak_native_fnc_tr);
if !(_hasAnchor) then { _vb ctrlSetBackgroundColor [0.16, 0.22, 0.18, 0.95]; _vb ctrlSetTextColor [0.55, 0.62, 0.58, 1]; };
_vb ctrlAddEventHandler ["ButtonClick", { ["validate"] call comspec_atak_native_fnc_markerPlace; }];
// Résumé : type, camp · couleur · taille · canal, puis le point choisi ou la consigne.
private _where = if (_hasAnchor) then {
    private _dd = player distance2D _anchor;
    format ["<t color='#5cc76b'>POINT</t> <t font='EtelkaMonospacePro'>%1</t> · %2 · %3°", [_anchor, 8] call comspec_atak_native_fnc_gridRef,
        [format ["%1 m", round _dd], format ["%1 km", (_dd / 1000) toFixed 1]] select (_dd >= 1000), round (player getDir _anchor)]
} else {
    format ["<t color='#d9a441'>%1</t>", ("Clic droit : choisir le point · clic gauche : poser tout de suite") call comspec_atak_native_fnc_tr]
};
private _sum = ["COMSPEC_RscStructuredText", [_x0 + _pw + _pad, _y, (_vx - _pad) - (_x0 + _pw + _pad), _ph]] call _mk;
_sum ctrlSetStructuredText parseText ((format ["<t size='1.05' font='COMSPEC_BarlowBold'>%1</t><br/><t color='#8a9a93' size='0.85'>%2 · %3 · taille %4 · canal %5</t><br/><t size='0.8'>%6</t>",
    _typeLabel, ([toLower _affName, "repère"] select (_aff in ["mil", "lib"])), toLower _colName,
    (_sizes select ((_sizes findIf { (_x select 1) isEqualTo _size }) max 0)) select 0,
    ["channelName", _chan] call comspec_atak_native_fnc_markerPlace, _where]) call comspec_atak_native_fnc_tr);
_sum ctrlSetFontHeight (_fs * 0.9);
_y = _y + _ph + _pad;
// Repliée : l'en-tête seul (on pose toujours au clic, VALIDER reste là).
if (_fold) exitWith {
    _bg ctrlSetPosition [_x0 - _pad, _y0 - _pad, _w + 2 * _pad, (_y - _y0) + _pad];
    _bg ctrlCommit 0;
    _y
};

// 2. Camp : onglets soulignés de la couleur du camp.
private _n = count _affs;
private _cw = _w / _n;
{
    _x params ["_key", "_label", "_cls", "_rgba"];
    private _on = _key isEqualTo _aff;
    private _b = ["COMSPEC_RscButton", [_x0 + _forEachIndex * _cw, _y, _cw - _pad / 3, _rh], _label] call _mk;
    _b ctrlSetFontHeight ((_fs * 0.85) max COMSPEC_ATAK_FontMin);
    _b ctrlSetBackgroundColor ([[0.06, 0.075, 0.065, 0.95], [(_rgba select 0) * 0.55 + 0.05, (_rgba select 1) * 0.55 + 0.05, (_rgba select 2) * 0.55 + 0.05, 1]] select _on);
    _b ctrlSetTextColor ([[0.58, 0.64, 0.60, 1], [1, 1, 1, 1]] select _on);
    private _line = (if (_key in ["mil", "lib"]) then { [0.75, 0.78, 0.76, 1] } else { _rgba }) apply { _x };
    _line set [3, [0.55, 1] select _on];
    private _u = ["COMSPEC_RscText", [_x0 + _forEachIndex * _cw, _y + _rh - _rh * 0.1, _cw - _pad / 3, _rh * 0.1]] call _mk;
    _u ctrlSetBackgroundColor _line;
    _b ctrlAddEventHandler ["ButtonClick", compile format ["['markerAff', '%1'] call (uiNamespace getVariable 'COMSPEC_ATAK_MarkerPick');", _key]];
} forEach _affs;
_y = _y + _rh + _pad;

// 3. Types : tuiles avec l'icône dans sa couleur et son nom. Onglet TOUS : catégorie et pages.
private _gap = _pad / 2;
private _iconH = _fs * 2.0;
private _labH = _fs * 0.95;
private _minW = (_iconH / _ratio) max (_fs * 3.4 / _ratio * 0.62);
private _cols = (floor ((_w + _gap) / (_minW + _gap))) max 1;
private _tileW = (_w + _gap) / _cols - _gap;
private _tileH = _iconH + _labH;
if (_aff isEqualTo "lib") then {
    private _cats = [];
    { _cats pushBackUnique (_x select 2); } forEach _types;
    private _cat = _s getOrDefault ["markerLibCat", _cats param [0, ""]];
    if !(_cat in _cats) then { _cat = _cats param [0, ""]; };
    private _all = +_types;
    _types = _types select { (_x select 2) isEqualTo _cat };
    private _cc = ["COMSPEC_RscCombo", [_x0, _y, _w * 0.62, _rh]] call _mk;
    _cc ctrlSetFontHeight ((_fs * 0.85) max COMSPEC_ATAK_FontMin);
    {
        private _dn = getText (configFile >> "CfgMarkerClasses" >> _x >> "displayName");
        private _c = _x;
        private _i = _cc lbAdd ((format ["%1 (%2)", [_dn, _x] select (_dn isEqualTo ""), { (_x select 2) isEqualTo _c } count _all]) call comspec_atak_native_fnc_tr);
        _cc lbSetData [_i, _x];
        if (_x isEqualTo _cat) then { _cc lbSetCurSel _i; };
    } forEach _cats;
    _cc ctrlAddEventHandler ["LBSelChanged", { params ["_c", "_i"]; private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]; _s set ["markerLibPage", 0]; ['markerLibCat', _c lbData _i] call (uiNamespace getVariable 'COMSPEC_ATAK_MarkerPick'); }];
    // Pages : trois lignes de tuiles.
    private _per = _cols * 3;
    private _pages = ceil ((count _types) / _per) max 1;
    private _page = (_s getOrDefault ["markerLibPage", 0]) min (_pages - 1);
    _types = _types select [_page * _per, _per];
    private _bw = (_w * 0.38 - _pad) / 3;
    private _px = _x0 + _w * 0.62 + _pad;
    private _prev = ["COMSPEC_RscButton", [_px, _y, _bw, _rh], "‹"] call _mk;
    _prev ctrlAddEventHandler ["ButtonClick", compile format ["['markerLibPage', %1] call (uiNamespace getVariable 'COMSPEC_ATAK_MarkerPick');", (_page - 1) max 0]];
    private _pg = ["COMSPEC_RscTextCenter", [_px + _bw, _y, _bw, _rh], format ["%1/%2", _page + 1, _pages]] call _mk;
    _pg ctrlSetFontHeight ((_fs * 0.85) max COMSPEC_ATAK_FontMin);
    private _next = ["COMSPEC_RscButton", [_px + 2 * _bw, _y, _bw, _rh], "›"] call _mk;
    _next ctrlAddEventHandler ["ButtonClick", compile format ["['markerLibPage', %1] call (uiNamespace getVariable 'COMSPEC_ATAK_MarkerPick');", (_page + 1) min (_pages - 1)]];
    _y = _y + _rh + _pad / 2;
};
{
    _x params ["_cls", "_label"];
    private _cx = _x0 + (_forEachIndex mod _cols) * (_tileW + _gap);
    private _cy = _y + (floor (_forEachIndex / _cols)) * (_tileH + _gap);
    private _sel = _cls isEqualTo _type;
    if (_sel) then {
        private _fr = ["COMSPEC_RscText", [_cx - _gap / 2, _cy - _gap / 2, _tileW + _gap, _tileH + _gap]] call _mk;
        _fr ctrlSetBackgroundColor _green;
    };
    private _cb = ["COMSPEC_RscText", [_cx, _cy, _tileW, _tileH]] call _mk;
    _cb ctrlSetBackgroundColor ([_iconRgba, _sel] call _tile);
    private _iw = _iconH * 0.78 / _ratio;
    private _ic = ["COMSPEC_RscIcon", [_cx + (_tileW - _iw) / 2, _cy + _iconH * 0.12, _iw, _iconH * 0.78], getText (configFile >> "CfgMarkers" >> _cls >> "icon")] call _mk;
    _ic ctrlSetTextColor _iconRgba;
    private _lt = ["COMSPEC_RscTextCenter", [_cx, _cy + _iconH, _tileW, _labH], _short getOrDefault [_label, _label]] call _mk;
    _lt ctrlSetFontHeight ((_fs * 0.68) max COMSPEC_ATAK_FontMin);
    _lt ctrlSetTextColor ([[0.05, 0.06, 0.05, 1], [0.92, 0.94, 0.92, 1]] select ((((_iconRgba select 0) + (_iconRgba select 1) + (_iconRgba select 2)) > 2.4)));
    private _b = ["COMSPEC_RscButtonOverlay", [_cx, _cy, _tileW, _tileH]] call _mk;
    _b ctrlSetTooltip ((_label) call comspec_atak_native_fnc_tr);
    _b ctrlAddEventHandler ["ButtonClick", compile format ["['markerType', '%1'] call (uiNamespace getVariable 'COMSPEC_ATAK_MarkerPick');", _cls]];
} forEach _types;
_y = _y + (ceil ((count _types) / _cols)) * (_tileH + _gap) + _pad / 2;

// 4. Couleur (pastilles) et taille (segment), sur une ligne.
private _lab = { params ["_t", "_xx", "_ww"]; private _c = ["COMSPEC_RscLabel", [_xx, _y, _ww, _rh], _t] call _mk; _c ctrlSetFontHeight ((_fs * 0.75) max COMSPEC_ATAK_FontMin); };
["COULEUR", _x0, _w * 0.12] call _lab;
private _sq = _rh * 0.8;
private _sqW = _sq * _ratio;
private _sx = _x0 + _w * 0.12;
{
    _x params ["_t", "_cls"];
    private _rgba = if (_cls isEqualTo "AUTO") then { _affColor } else { [_cls] call _cfgRgba };
    private _xx = _sx + _forEachIndex * (_sqW + _pad);
    private _yy = _y + (_rh - _sq) / 2;
    if (_cls isEqualTo _colorKey) then {
        private _fr = ["COMSPEC_RscText", [_xx - _pad / 3, _yy - _pad / 3, _sqW + _pad * 2 / 3, _sq + _pad * 2 / 3]] call _mk;
        _fr ctrlSetBackgroundColor _green;
    };
    private _b = ["COMSPEC_RscButton", [_xx, _yy, _sqW, _sq], ["", "A"] select (_cls isEqualTo "AUTO")] call _mk;
    if ((count _rgba) isEqualTo 4) then { _b ctrlSetBackgroundColor _rgba; };
    _b ctrlSetFontHeight ((_fs * 0.75) max COMSPEC_ATAK_FontMin);
    _b ctrlSetTextColor [1, 1, 1, 1];
    _b ctrlSetTooltip ((_t) call comspec_atak_native_fnc_tr);
    _b ctrlAddEventHandler ["ButtonClick", compile format ["['markerColor', '%1'] call (uiNamespace getVariable 'COMSPEC_ATAK_MarkerPick');", _cls]];
} forEach _swatches;
private _zx = _sx + (count _swatches) * (_sqW + _pad) + _pad * 2;
["TAILLE", _zx, _w * 0.1] call _lab;
_zx = _zx + _w * 0.1;
private _ow = ((_x0 + _w - _zx) / 4) min (_w * 0.07);
{
    _x params ["_t", "_v"];
    private _b = ["COMSPEC_RscButton", [_zx + _forEachIndex * _ow, _y, _ow - _pad / 3, _rh], _t] call _mk;
    _b ctrlSetFontHeight ((_fs * 0.85) max COMSPEC_ATAK_FontMin);
    if (_v isEqualTo _size) then { _b ctrlSetBackgroundColor _green; _b ctrlSetTextColor [0.03, 0.05, 0.04, 1]; };
    _b ctrlAddEventHandler ["ButtonClick", compile format ["['markerSize', %1] call (uiNamespace getVariable 'COMSPEC_ATAK_MarkerPick');", _v]];
} forEach _sizes;
_y = _y + _rh + _pad / 2;

// 5. Canal (Général par défaut : vu de tous) et options de pose : à la suite, titre et note après la pose.
["CANAL", _x0, _w * 0.12] call _lab;
private _chans = [0, 1, 2, 3, 4, 5];
private _chW = (_w * 0.58) / (count _chans);
{
    private _on = _x isEqualTo _chan;
    private _b = ["COMSPEC_RscButton", [_x0 + _w * 0.12 + _forEachIndex * _chW, _y, _chW - _pad / 3, _rh], ["channelName", _x] call comspec_atak_native_fnc_markerPlace] call _mk;
    _b ctrlSetFontHeight ((_fs * 0.72) max COMSPEC_ATAK_FontMin);
    if (_on) then { _b ctrlSetBackgroundColor _green; _b ctrlSetTextColor [0.03, 0.05, 0.04, 1]; };
    _b ctrlSetTooltip (([
        "Général : tous les joueurs voient le marqueur", "Camp : tout mon camp", "Commandement : chefs de groupe",
        "Groupe : mon groupe seulement", "Véhicule : l'équipage de mon véhicule", "Direct : les joueurs proches"
    ] select _forEachIndex) call comspec_atak_native_fnc_tr);
    _b ctrlAddEventHandler ["ButtonClick", compile format ["['setChannel', %1] call comspec_atak_native_fnc_markerPlace;", _x]];
} forEach _chans;
private _optX = _x0 + _w * 0.71;
private _optW = (_x0 + _w - _optX - _pad / 2) / 2;
private _series = profileNamespace getVariable ["COMSPEC_ATAK_MarkerSeries", false];
private _sb = ["COMSPEC_RscButton", [_optX, _y, _optW, _rh], ["☐  À la suite", "☑  À la suite"] select _series] call _mk;
_sb ctrlSetFontHeight ((_fs * 0.78) max COMSPEC_ATAK_FontMin);
if (_series) then { _sb ctrlSetTextColor _green; };
_sb ctrlSetTooltip (("Après VALIDER, la palette reste ouverte pour poser le marqueur suivant") call comspec_atak_native_fnc_tr);
_sb ctrlAddEventHandler ["ButtonClick", { ["series"] call comspec_atak_native_fnc_markerPlace; }];
private _edit = _s getOrDefault ["markerEditAfter", false];
private _eb = ["COMSPEC_RscButton", [_optX + _optW + _pad / 2, _y, _optW, _rh], ["☐  Titre et note", "☑  Titre et note"] select _edit] call _mk;
_eb ctrlSetFontHeight ((_fs * 0.78) max COMSPEC_ATAK_FontMin);
if (_edit) then { _eb ctrlSetTextColor _green; };
_eb ctrlSetTooltip (("Ouvrir l'éditeur (titre, description) juste après la pose") call comspec_atak_native_fnc_tr);
_eb ctrlAddEventHandler ["ButtonClick", { private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]; ['markerEditAfter', !(_s getOrDefault ["markerEditAfter", false])] call (uiNamespace getVariable 'COMSPEC_ATAK_MarkerPick'); }];
_y = _y + _rh + _pad / 2;

// 6. Tous les marqueurs chargés (Arma et mods) en liste.
["AUTRES", _x0, _w * 0.12] call _lab;
private _combo = ["COMSPEC_RscCombo", [_x0 + _w * 0.12, _y, _w * 0.88, _rh]] call _mk;
_combo ctrlSetFontHeight ((_fs * 0.85) max COMSPEC_ATAK_FontMin);
_combo lbAdd (("Tous les marqueurs Arma et des mods…") call comspec_atak_native_fnc_tr);
_combo lbSetData [0, ""];
private _sel = 0;
{
    _x params ["_name", "_cls", "_icon"];
    private _i = _combo lbAdd ((_name) call comspec_atak_native_fnc_tr);
    _combo lbSetData [_i, _cls];
    _combo lbSetPicture [_i, _icon];
    if (_cls isEqualTo _type && {(_types findIf { (_x select 0) isEqualTo _cls }) < 0}) then { _sel = _i; };
} forEach (([] call comspec_atak_native_fnc_markerCatalog) select 0);
_combo lbSetCurSel _sel;
_combo ctrlAddEventHandler ["LBSelChanged", { params ["_c", "_i"]; private _d = _c lbData _i; if (_d isNotEqualTo "") then { ['markerType', _d] call (uiNamespace getVariable 'COMSPEC_ATAK_MarkerPick'); }; }];
_y = _y + _rh + _pad;
_bg ctrlSetPosition [_x0 - _pad, _y0 - _pad, _w + 2 * _pad, (_y - _y0) + _pad];
_bg ctrlCommit 0;
_y
