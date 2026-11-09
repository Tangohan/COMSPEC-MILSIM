/*
    Fiche du marqueur sélectionné sur la carte (clic gauche sur un repère, un contact ou une alerte), au-dessus du panneau
    curseur. Créée au rendu de la page carte (fn_pageMap), jamais par image. Couleurs : jetons de fn_theme (mapCard,
    mapCardEdge, mapText, mapDim, mapWarn, mapAlert et les affiliations mapHostile… lisibles sur mapCard, AtakThemeContrastTest).
      pastille   forme de l'affiliation (losange ennemi, carré neutre, cercle ami, carré à bras inconnu, triangle civil) avec le
                 symbole du repère dedans : l'affiliation ne repose jamais sur la couleur seule ;
      ligne 1    nom, puis l'affiliation en toutes lettres et la nature (MISSION, JOUEUR, ALERTE) ;
      ligne 2    grille · distance · azimut depuis moi (chasse fixe) ;
      ligne 3    âge et auteur (« il y a 4 min · TA1 »), « POSITION ANCIENNE » au-delà de 10 min ; note s'il y en a une ;
      actions    Y ALLER, PARTAGER (ping vers mon camp), CENTRER ou MODIFIER, SUPPRIMER ou EFFACER (marqueurs que je peux effacer).
    Params : [fabrique de contrôles (code de fn_pageMap), x, y, largeur, hauteur de la carte, hauteur du bandeau, marge, taille
              de texte réduite, taille de texte, rapport d'écran]
*/
params ["_mk", "_bx", "_by", "_mw", "_mh", "_panH", "_pad", "_fs", "_font", "_ratio"];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _sel = _s getOrDefault ["selectedMarker", ""];
if (_sel isEqualTo "" || {(markerShape _sel) isEqualTo ""}) exitWith {};
private _th = COMSPEC_ATAK_Th;
private _note = ((missionNamespace getVariable ["COMSPEC_ATAK_MarkerNotes", createHashMap]) getOrDefault [_sel, ["", ""]]) select 0;
private _kind = [_sel] call comspec_atak_native_fnc_markerDeletable;
private _type = markerType _sel;
private _mp = getMarkerPos _sel;

// Affiliation : classe o_ n_ u_ b_ c_ (ennemi, ami, neutre, inconnu, civil) ; "" pour un repère de plan (objectif, danger...),
// qui garde sa couleur et son symbole seuls (comme sur la carte, fn_mapGameMarkers).
private _aff = ["markerAff", _type] call comspec_atak_native_fnc_contactStyle;

// Heure de pose (heure du jeu), âge et auteur (COMSPEC_ATAK_MarkerMeta : EH MarkerCreated, ou heure transmise en mode réseau
// ATAK, fn_markerNet). Inconnu pour un marqueur posé avant mon arrivée.
private _mt = (missionNamespace getVariable ["COMSPEC_ATAK_MarkerMeta", createHashMap]) getOrDefault [_sel, []];
private _ageS = -1;
private _stamp = "";
if ((count _mt) > 0 && {(_mt select 0) isEqualType ""} && {(count (_mt select 0)) >= 6}) then {
    private _h = _mt select 0;
    _ageS = ((dayTime * 3600 - ((parseNumber (_h select [0, 2])) * 3600 + (parseNumber (_h select [2, 2])) * 60 + parseNumber (_h select [4, 2]))) + 86400) mod 86400;
    _stamp = format [("Posé à %1:%2") call comspec_atak_native_fnc_tr, _h select [0, 2], _h select [2, 2]] + " · " + (["age", _ageS] call comspec_atak_native_fnc_contactStyle);
    if ((_mt param [1, ""]) isEqualType "" && {(_mt param [1, ""]) isNotEqualTo ""}) then { _stamp = format ["%1 · %2", _stamp, toUpper (_mt select 1)]; };
};
private _old = _ageS > 600;

private _pw = ((_mw * 0.5) max (_font * 14 / _ratio)) min (_mw - 2 * _pad);
private _px = _bx + (_mw - _pw) / 2;
private _lineH = _fs * 1.05;
private _btnH = _fs * 1.5;
private _in = _pad * 0.8;
private _ph = _in + _fs * 1.3 + _lineH + ([0, _lineH] select (_stamp isNotEqualTo "")) + ([0, _lineH] select (_note isNotEqualTo "")) + _in * 0.6 + _btnH + _in;
private _py = _by + _mh - _panH - _pad * 2 - _ph;

// Couleur du repère (CfgMarkerColors), claire par défaut (sans couleur, ou trop sombre pour la fiche).
private _rgba = (getArray (configFile >> "CfgMarkerColors" >> (markerColor _sel) >> "color")) apply { if (_x isEqualType "") then { call compile _x } else { _x } };
if ((count _rgba) isNotEqualTo 4 || {((_rgba select 0) * 0.3 + (_rgba select 1) * 0.59 + (_rgba select 2) * 0.11) < 0.25}) then { _rgba = _th get "mapText"; };
_rgba = [_rgba select 0, _rgba select 1, _rgba select 2, 1];
private _ink = [_rgba, ["ink", _aff] call comspec_atak_native_fnc_contactStyle] select (_aff isNotEqualTo "");

{ private _o = ["COMSPEC_RscText", [_px + pixelW * _x, _py + pixelH * _x, _pw, _ph]] call _mk; _o ctrlSetBackgroundColor [0, 0, 0, 0.22]; } forEach [2, 4];
private _brd = ["COMSPEC_RscText", [_px - pixelW, _py - pixelH, _pw + pixelW * 2, _ph + pixelH * 2]] call _mk;
_brd ctrlSetBackgroundColor (_th get "mapCardEdge");
private _cbg = ["COMSPEC_RscText", [_px, _py, _pw, _ph]] call _mk;
_cbg ctrlSetBackgroundColor (_th get "mapCard");
private _edge = ["COMSPEC_RscText", [_px, _py, pixelW * 4, _ph]] call _mk;
_edge ctrlSetBackgroundColor _ink;

// Pastille : forme de l'affiliation (liseré à la couleur, fond sombre) et symbole du repère dedans.
private _chipH = _fs * 1.3 + _lineH;
private _chipW = _chipH / _ratio;
private _tx0 = _px + pixelW * 4 + _in / _ratio * 0.6;
if (_aff isEqualTo "") then {
    private _chip = ["COMSPEC_RscText", [_tx0, _py + _in, _chipW, _chipH]] call _mk;
    _chip ctrlSetBackgroundColor [_rgba select 0, _rgba select 1, _rgba select 2, 0.16];
} else {
    {
        _x params ["_tex", "_role", "_sc"];
        private _k = 0.5 * _sc / 2.0;
        private _ic0 = ["COMSPEC_RscIcon", [_tx0 + _chipW * (0.5 - _k), _py + _in + _chipH * (0.5 - _k), _chipW * 2 * _k, _chipH * 2 * _k], _tex] call _mk;
        _ic0 ctrlSetTextColor ([_ink, _th get "mapCard"] select (_role isEqualTo "DARK"));
    } forEach (["layers", _aff] call comspec_atak_native_fnc_contactStyle);
};
private _ic = ["COMSPEC_RscIcon", [_tx0 + _chipW * 0.25, _py + _in + _chipH * 0.25, _chipW * 0.5, _chipH * 0.5], getText (configFile >> "CfgMarkers" >> _type >> "icon")] call _mk;
_ic ctrlSetTextColor ([_rgba, _ink] select (_aff isNotEqualTo ""));

// Fermer (en haut à droite).
private _cw = _fs * 1.2;
private _cx = _px + _pw - _cw / _ratio - _in / _ratio * 0.6;
private _cl = ["COMSPEC_RscIcon", [_cx, _py + _in, _cw / _ratio, _cw], "\z\comspec_atak_native\addons\main\data\ui_close.paa"] call _mk;
_cl ctrlSetTextColor (_th get "mapDim");
private _clb = ["COMSPEC_RscButtonOverlay", [_cx, _py + _in, _cw / _ratio, _cw]] call _mk;
_clb ctrlSetTooltip (("Fermer la fiche") call comspec_atak_native_fnc_tr);
_clb ctrlAddEventHandler ["ButtonClick", { (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["selectedMarker", ""]; [{ ["MAP"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame; }];

// Titre et affiliation, position, âge et auteur, note.
private _dd = player distance2D _mp;
private _kindTxt = (["MISSION", "JOUEUR", "ALERTE"] select _kind) call comspec_atak_native_fnc_tr;
private _kindCol = [_th get "hexMapDim", _th get "hexMapOk", _th get "hexMapWarn"] select _kind;
private _affTxt = ["", format ["<t size='0.72' color='%1'>%2</t>  ", ["hex", _aff] call comspec_atak_native_fnc_contactStyle, ["label", _aff] call comspec_atak_native_fnc_contactStyle]] select (_aff isNotEqualTo "");
private _txX = _tx0 + _chipW + _in / _ratio * 0.6;
private _txt = ["COMSPEC_RscStructuredText", [_txX, _py + _in - _fs * 0.1, _cx - _txX - _in / _ratio * 0.4, _chipH + ([0, _lineH] select (_stamp isNotEqualTo "")) + ([0, _lineH] select (_note isNotEqualTo "")) + _fs * 0.2]] call _mk;
private _esc = { _this regexReplace ["[<>&]", ""] };
private _name = [([markerText _sel] call comspec_atak_native_fnc_markerAutoName), ("(sans titre)") call comspec_atak_native_fnc_tr] select ((markerText _sel) isEqualTo "");
private _l3 = "";
if (_stamp isNotEqualTo "") then {
    _l3 = format ["<br/><t size='0.78' color='%1'>%2</t>", _th get "hexMapDim", _stamp call _esc];
    if (_old) then { _l3 = _l3 + format ["  <t size='0.72' color='%1'>%2</t>", _th get "hexMapWarn", ("POSITION ANCIENNE") call comspec_atak_native_fnc_tr]; };
};
_txt ctrlSetStructuredText parseText (format ["<t font='COMSPEC_BarlowBold' size='1.15' color='%1'>%2</t>  %3<t size='0.72' color='%4'>%5</t><br/><t font='EtelkaMonospacePro' size='0.82' color='%6'>%7 · %8</t>%9",
    _th get "hexMapText", _name call _esc, _affTxt, _kindCol, _kindTxt, _th get "hexMapDim",
    [_mp, 8] call comspec_atak_native_fnc_gridRef, ["dist", _dd, player getDir _mp] call comspec_atak_native_fnc_mapFmt,
    _l3 + (["", format ["<br/><t size='0.85' color='%1'>%2</t>", _th get "hexMapText", (_note call _esc) select [0, 90]]] select (_note isNotEqualTo ""))]); // i18n:brut
_txt ctrlSetFontHeight _fs;

// Actions : une rangée en bas de la fiche. PARTAGER = ping vers mon camp à cette position.
private _acts = [
    ["Y ALLER", "COMSPEC_RscButtonPrimary", { private _m = (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["selectedMarker", ""]; private _p = getMarkerPos _m; [[_p select 0, _p select 1, 0], [([markerText _m] call comspec_atak_native_fnc_markerAutoName), "Marqueur"] select ((markerText _m) isEqualTo "")] spawn comspec_atak_native_fnc_routeCompute; }],
    ["PARTAGER", "COMSPEC_RscButton", { ["send", getMarkerPos ((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["selectedMarker", ""])] call comspec_atak_native_fnc_mapPing; }]
];
if (_kind isEqualTo 1) then {
    _acts pushBack ["MODIFIER", "COMSPEC_RscButton", { [(uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["selectedMarker", ""]] call comspec_atak_native_fnc_markerEditOpen; }];
} else {
    _acts pushBack ["CENTRER", "COMSPEC_RscButton", { private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]; _s set ["mapFollow", false]; [getMarkerPos (_s getOrDefault ["selectedMarker", ""])] call comspec_atak_native_fnc_mapCenter; }];
};
if (_kind > 0) then {
    _acts pushBack [["", "SUPPRIMER", "EFFACER"] select _kind, "COMSPEC_RscButton", { [(uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["selectedMarker", ""]] call comspec_atak_native_fnc_markerDelete; }];
};
private _ay = _py + _ph - _in - _btnH;
private _ax0 = _px + pixelW * 4 + _in / _ratio * 0.6;
private _aw = ((_px + _pw - _in / _ratio * 0.6) - _ax0 - _pad * 0.5 * ((count _acts) - 1)) / (count _acts);
{
    _x params ["_lbl", "_cls", "_code"];
    private _b = [_cls, [_ax0 + _forEachIndex * (_aw + _pad * 0.5), _ay, _aw, _btnH], _lbl] call _mk;
    _b ctrlSetFont (COMSPEC_ATAK_FontSet select 1);
    _b ctrlSetFontHeight ((_fs * 0.8) max COMSPEC_ATAK_FontMin);
    if (_lbl in ["SUPPRIMER", "EFFACER"]) then { _b ctrlSetTextColor (_th get "mapAlert"); };
    _b ctrlAddEventHandler ["ButtonClick", _code];
} forEach _acts;
if (_kind > 0) then {
    _txt ctrlSetTooltip ((["", "Double clic : modifier · Suppr : effacer", "Suppr : effacer cette alerte de la carte"] select _kind) call comspec_atak_native_fnc_tr);
};
