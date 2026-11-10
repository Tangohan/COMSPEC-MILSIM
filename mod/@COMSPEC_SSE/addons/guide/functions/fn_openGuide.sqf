/*
    Ouvre un chapitre du MANUEL SSE (CfgHints › COMSPEC_SSE) dans la fenêtre maison : sommaire à gauche (liste des
    chapitres, champ de recherche au-dessus qui filtre sur le titre ET le contenu), texte à droite, bouton Fermer,
    Échap ferme, renvoi vers le manuel de terrain d'Arma en bas de page.

    La fenêtre se pose par-dessus l'écran parent :
      - Eden : findDisplay 313 (BIS_fnc_openFieldManual lancé depuis la barre de menus d'Eden échouait : pas d'écran parent) ;
      - Zeus : findDisplay 312 (le manuel de terrain s'ouvrait derrière l'interface Zeus) ;
      - en jeu : l'écran passé en paramètre (un dialogue SSE ouvert, voir comspec_sse_fnc_guideButton), sinon findDisplay 46.
    Sans écran parent (menu principal), le manuel de terrain d'Arma s'ouvre à la place.

    [_topic, _parentDisplay] call comspec_sse_fnc_openGuide
      _topic         : classe de CfgHints › COMSPEC_SSE (« Start », « Editor », « IdCheck »...) ; inconnu : premier chapitre.
      _parentDisplay : facultatif, écran sur lequel poser la fenêtre.

    Codes réservés de CfgHints dans description : %1 saut de ligne, %2 puce, %3 / %4 surlignage.
*/
params [["_topic", "Start", [""]], ["_parent", displayNull, [displayNull]]];
if (!hasInterface) exitWith {};
disableSerialization;

// L'action d'un menu d'Eden s'exécute pendant que la liste déroulante est encore ouverte : une fenêtre créée à ce
// moment resterait invisible et garderait la souris. On la crée juste après, une fois le menu refermé.
if (is3DEN && {!canSuspend}) exitWith { _this spawn { uiSleep 0.05; _this call comspec_sse_fnc_openGuide; }; };

if (isNull _parent) then {
    _parent = switch (true) do {
        case (is3DEN): { findDisplay 313 };
        case (!isNull findDisplay 312): { findDisplay 312 };
        default { findDisplay 46 };
    };
};
if (isNull _parent) exitWith { ["COMSPEC_SSE", _topic] call BIS_fnc_openFieldManual; };

private _root = configFile >> "CfgHints" >> "COMSPEC_SSE";
private _ids = ("true" configClasses _root) apply { configName _x };
if (_ids isEqualTo []) exitWith {};
if !(_topic in _ids) then { _topic = _ids select 0; };

// Une seule fenêtre à la fois.
private _old = uiNamespace getVariable ["comspec_sse_guide_display", displayNull];
if (!isNull _old) then { _old closeDisplay 2; };

private _d = _parent createDisplay "RscDisplayEmpty";
if (isNull _d) exitWith { ["COMSPEC_SSE", _topic] call BIS_fnc_openFieldManual; };
uiNamespace setVariable ["comspec_sse_guide_display", _d];

private _w = safeZoneW * 0.64;
private _h = safeZoneH * 0.74;
private _x0 = safeZoneX + (safeZoneW - _w) / 2;
private _y0 = safeZoneY + (safeZoneH - _h) / 2 + safeZoneH * 0.015;
private _u = safeZoneH * 0.006;
private _rowH = 5 * _u;

private _dim = _d ctrlCreate ["ctrlStatic", -1];
_dim ctrlSetPosition [safeZoneX, safeZoneY, safeZoneW, safeZoneH];
_dim ctrlSetBackgroundColor [0, 0, 0, 0.45];
_dim ctrlCommit 0;
private _bg = _d ctrlCreate ["ctrlStatic", -1];
_bg ctrlSetPosition [_x0, _y0, _w, _h];
_bg ctrlSetBackgroundColor [0.13, 0.14, 0.13, 0.97];
_bg ctrlCommit 0;
private _title = _d ctrlCreate ["ctrlStatic", -1];
_title ctrlSetPosition [_x0, _y0 - _rowH, _w, _rowH];
_title ctrlSetBackgroundColor [0.10, 0.30, 0.16, 1];
_title ctrlSetTextColor [1, 1, 1, 1];
_title ctrlSetText "  MANUEL SSE";
_title ctrlCommit 0;

// Sommaire : recherche puis liste.
private _lw = _w * 0.3;
private _lx = _x0 + 2 * _u;
private _ly = _y0 + 2 * _u;
private _search = _d ctrlCreate ["ctrlEdit", -1];
_search ctrlSetPosition [_lx, _ly, _lw, _rowH];
_search ctrlSetBackgroundColor [0.08, 0.09, 0.08, 1];
_search ctrlSetTooltip (("Filtre les chapitres sur leur titre et leur contenu.") call comspec_sse_fnc_tr);
_search ctrlCommit 0;
private _hint = _d ctrlCreate ["ctrlStatic", -1];
_hint ctrlSetPosition [_lx + _u, _ly, _lw - 2 * _u, _rowH];
_hint ctrlSetTextColor [0.55, 0.6, 0.65, 1];
_hint ctrlSetText (("Rechercher dans le manuel...") call comspec_sse_fnc_tr);
_hint ctrlEnable false;
_hint ctrlCommit 0;
private _list = _d ctrlCreate ["ctrlListbox", -1];
_list ctrlSetPosition [_lx, _ly + _rowH + _u, _lw, _h - 10 * _u - _rowH - _u];
_list ctrlCommit 0;

// Texte du chapitre.
private _grp = _d ctrlCreate ["ctrlControlsGroup", -1];
private _gx = _x0 + _lw + 4 * _u;
private _gw = _w - _lw - 6 * _u;
_grp ctrlSetPosition [_gx, _ly, _gw, _h - 10 * _u];
_grp ctrlCommit 0;
private _txt = _d ctrlCreate ["ctrlStructuredText", -1, _grp];
_txt ctrlCommit 0;

// Chapitres : [id, titre, texte structuré, texte brut en minuscules pour la recherche].
private _chapters = _ids apply {
    private _cfg = _root >> _x;
    private _name = (getText (_cfg >> "displayName")) call comspec_sse_fnc_tr;
    private _body = format [(getText (_cfg >> "description")) call comspec_sse_fnc_tr, "<br/>", "• ", "<t color='#7fd08a'>", "</t>"];
    private _tip = (getText (_cfg >> "tip")) call comspec_sse_fnc_tr;
    if (_tip isNotEqualTo "") then { _body = _body + format ["<br/><br/><t color='#c9d3dc' size='0.9'>%1</t>", _tip]; };
    private _plain = toLower ((_name + " " + _body) regexReplace ["<[^>]*>", " "]);
    [_x, _name, _body, _plain]
};
_d setVariable ["comspec_sse_guide", [_list, _txt, _gw - 3 * _u, _u, _chapters, _hint]];

// Remplit la liste avec les chapitres qui contiennent le texte cherché ; garde le chapitre ouvert s'il y figure encore.
private _fill = {
    params ["_d", ["_q", ""]];
    (_d getVariable ["comspec_sse_guide", []]) params ["_list", "_txt", "_tw", "_u", "_chapters"];
    private _cur = _list lbData (lbCurSel _list);
    _q = toLower _q;
    lbClear _list;
    {
        _x params ["_id", "_name", "", "_plain"];
        if (_q isEqualTo "" || {_q in _plain}) then {
            private _i = _list lbAdd _name;
            _list lbSetData [_i, _id];
        };
    } forEach _chapters;
    private _sel = 0;
    for "_i" from 0 to (lbSize _list) - 1 do { if ((_list lbData _i) isEqualTo _cur) exitWith { _sel = _i; }; };
    if (lbSize _list > 0) then { _list lbSetCurSel _sel; } else {
        _txt ctrlSetStructuredText parseText format ["<t color='#8a96a3'>%1</t>", ("Aucun chapitre ne contient ce texte.") call comspec_sse_fnc_tr];
        _txt ctrlSetPosition [0, 0, _tw, 3 * _u];
        _txt ctrlCommit 0;
    };
};
_d setVariable ["comspec_sse_guide_fill", _fill];

_list ctrlAddEventHandler ["LBSelChanged", {
    params ["_ctrl", "_i"];
    if (_i < 0) exitWith {};
    private _d = ctrlParent _ctrl;
    (_d getVariable ["comspec_sse_guide", []]) params ["_list", "_txt", "_tw", "_u", "_chapters"];
    if (isNil "_txt") exitWith {};
    private _id = _ctrl lbData _i;
    private _ch = _chapters select ((_chapters findIf { (_x select 0) isEqualTo _id }) max 0);
    _txt ctrlSetStructuredText parseText format ["<t size='1.15' font='RobotoCondensedBold'>%1</t><br/><br/>%2", _ch select 1, _ch select 2];
    _txt ctrlSetPosition [0, 0, _tw, 1];
    _txt ctrlCommit 0;
    _txt ctrlSetPosition [0, 0, _tw, (ctrlTextHeight _txt) + 2 * _u];
    _txt ctrlCommit 0;
    (ctrlParentControlsGroup _txt) ctrlSetScrollValues [0, -1];
}];
_search ctrlAddEventHandler ["KeyUp", {
    params ["_ctrl"];
    private _d = ctrlParent _ctrl;
    private _q = ctrlText _ctrl;
    (_d getVariable ["comspec_sse_guide", []]) params ["", "", "", "", "", "_hint"];
    if (!isNil "_hint") then { _hint ctrlShow (_q isEqualTo ""); };
    [_d, _q] call (_d getVariable ["comspec_sse_guide_fill", {}]);
    false
}];
[_d, ""] call _fill;
{ if ((_list lbData _forEachIndex) isEqualTo _topic) exitWith { _list lbSetCurSel _forEachIndex; }; } forEach _ids;

// Bas de page : renvoi vers le manuel de terrain d'Arma (hors Eden et Zeus, où il ne s'ouvre pas au bon endroit), Fermer.
private _bw = _w * 0.18;
private _by = _y0 + _h - 6 * _u;
if (!is3DEN && {isNull findDisplay 312}) then {
    private _fm = _d ctrlCreate ["ctrlButton", -1];
    _fm ctrlSetPosition [_lx, _by, _lw, _rowH];
    _fm ctrlSetText (("Manuel de terrain d'Arma") call comspec_sse_fnc_tr);
    _fm ctrlSetTooltip (("Le même manuel dans le manuel de terrain du jeu (Échap › Manuel de terrain › COMSPEC SSE).") call comspec_sse_fnc_tr);
    _fm ctrlAddEventHandler ["ButtonClick", {
        params ["_b"];
        private _d = ctrlParent _b;
        (_d getVariable ["comspec_sse_guide", []]) params ["_list"];
        private _topic = if (isNil "_list" || {lbCurSel _list < 0}) then { "Start" } else { _list lbData (lbCurSel _list) };
        // Le bouton ne se supprime pas dans son propre gestionnaire : fermeture et ouverture différées.
        [_d, _topic] spawn { params ["_d", "_topic"]; _d closeDisplay 2; uiSleep 0.05; ["COMSPEC_SSE", _topic] call BIS_fnc_openFieldManual; };
        false
    }];
    _fm ctrlCommit 0;
} else {
    private _note = _d ctrlCreate ["ctrlStructuredText", -1];
    _note ctrlSetPosition [_lx, _by, _lw * 1.6, _rowH];
    _note ctrlSetStructuredText parseText format ["<t size='0.85' color='#8a96a3'>%1</t>", ("Aussi dans Échap › Manuel de terrain › COMSPEC SSE.") call comspec_sse_fnc_tr];
    _note ctrlCommit 0;
};
private _b = _d ctrlCreate ["ctrlButton", -1];
_b ctrlSetPosition [_x0 + _w - _bw - _u, _by, _bw, _rowH];
_b ctrlSetText (("Fermer") call comspec_sse_fnc_tr);
_b ctrlAddEventHandler ["ButtonClick", { [ctrlParent (_this select 0)] spawn { (_this select 0) closeDisplay 2; }; false }];
_b ctrlCommit 0;

_d displayAddEventHandler ["KeyDown", {
    params ["_d", "_key"];
    if (_key == 1) exitWith { [_d] spawn { (_this select 0) closeDisplay 2; }; true };
    false
}];
_d displayAddEventHandler ["Unload", { uiNamespace setVariable ["comspec_sse_guide_display", displayNull]; }];
ctrlSetFocus _search;
_d
