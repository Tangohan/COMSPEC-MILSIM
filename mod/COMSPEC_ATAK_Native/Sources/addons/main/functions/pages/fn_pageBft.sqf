/*
    BFT (Blue Force Tracking) : tableau des amis suivis, comme un terminal JBC-P.
    En-tête : ma position (grille, cap, altitude) et l'état des pistes (direct / ancienne / perdue).
    Filtres : TOUS, MON ÉQUIPE (équipe de feu), MON GROUPE, VÉHICULES, PERDUS. Tri par distance.
    Équipes de feu : l'icône de chaque piste prend la couleur de son équipe, nom de l'équipe et rôle dans la fiche.
    Chaque piste : symbole, indicatif, distance et gisement, temps de trajet, âge de la dernière position.
    Une piste hors ligne (téléphone éteint, cassé ou sans signal) reste figée à sa dernière position connue,
    et sans réseau toutes les pistes vieillissent.
    Piste choisie : fiche (trajet, rapprochement, itinéraire GPS) et actions (carte, GPS, SMS, faire vibrer).
    Vues (profil COMSPEC_ATAK_BftView) : LISTE (tableau trié par distance) ou ORBAT (arbre repliable :
    formation (ORBAT Athena abrégé, sinon préfixe de l'indicatif « Star 4 » → STAR) › groupe (chef, échelon, effectif,
    distance au chef) › équipe de feu › membres). Repli mémorisé dans uiNamespace COMSPEC_ATAK_BftOrbatOpen.
    FILTRES : tiroir de réglages persistants (fn_bftFilter : IA, aériens, véhicules, pistes anciennes, hors ligne,
    mon groupe / mon unité, distance max), appliqués aussi à la carte.
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _pad = (_l get "pad") * 2;
private _font = _l get "font";
private _fs = _l get "fontSmall";
private _land = _l get "landscape";
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _data = uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap];
private _filter = _s getOrDefault ["bftFilter", "ALL"];
private _selId = _s getOrDefault ["bftSel", ""];
private _view = profileNamespace getVariable ["COMSPEC_ATAK_BftView", "LIST"];
private _filtersOpen = _s getOrDefault ["bftFiltersOpen", false];
private _esc = { params ["_t"]; if !(_t isEqualType "") then { _t = str _t; }; [[_t, "<", "&lt;"] call CBA_fnc_replace, ">", "&gt;"] call CBA_fnc_replace };
// Temps de trajet : ma vitesse si je bouge, sinon la marche (5 km/h).
private _mySpd = vectorMagnitude velocity vehicle player;
private _walk = 5 / 3.6;
private _etaTxt = {
    params ["_m", "_v"];
    private _t = round (_m / (_v max 0.5) / 60);
    switch (true) do { case (_t < 1): { "< 1 min" }; case (_t < 60): { format ["%1 min", _t] }; default { format ["%1 h %2", floor (_t / 60), [str (_t mod 60), "0" + str (_t mod 60)] select ((_t mod 60) < 10)] }; }
};
private _distTxt = { params ["_d"]; [format ["%1 m", round _d], format ["%1 km", (_d / 1000) toFixed 1]] select (_d >= 1000) };
private _card = { params ["_deg"]; ["N", "NE", "E", "SE", "S", "SO", "O", "NO"] select ((round (_deg / 45)) mod 8) };
private _fresh = createHashMapFromArray [["LIVE", ["DIRECT", "#5cc76b"]], ["STALE", ["ANCIENNE", "#f2ab33"]], ["LOST", ["PERDUE", "#e5483a"]], ["OFFLINE", ["HORS LIGNE", "#6c7671"]]];

// Pistes amies (hors moi), avec distance et gisement.
private _me = getPosASL player;
private _tracks = [];
{
    if ((_y getOrDefault ["affiliation", ""]) isEqualTo "friend" && {!(_y getOrDefault ["self", false])}) then {
        private _obj = _y getOrDefault ["object", objNull];
        private _live = (_y getOrDefault ["freshness", "LIVE"]) isEqualTo "LIVE";
        private _pos = if (isNull _obj || {!_live}) then { _y getOrDefault ["position", [0, 0, 0]] } else { getPosASL _obj };
        _tracks pushBack [player distance2D _pos, _x, _y, _obj, _pos];
    };
} forEach (_data getOrDefault ["units", createHashMap]);
// Doublons : une piste Athena (sans objet) au même indicatif qu'une unité en jeu est la même personne (fusionnée
// aussi par fn_localDataRefresh) ; des IA d'un même groupe sans indicatif propre portent toutes le nom du groupe
// (« Star 4-1 » deux fois) : elles sont distinguées par leur rôle.
private _byCs = createHashMap;
{
    private _k = toLower ((_x select 2) getOrDefault ["callsign", _x select 1]);
    _byCs set [_k, (_byCs getOrDefault [_k, []]) + [_forEachIndex]];
} forEach _tracks;
private _drop = [];
private _labels = createHashMap;
{
    if ((count _y) > 1) then {
        private _withObj = _y select { !isNull ((_tracks select _x) select 3) };
        if ((count _withObj) > 0) then { _drop append (_y - _withObj); };
        if ((count _withObj) > 1) then {
            {
                private _t = _tracks select _x;
                private _o = _t select 3;
                private _role = getText (configOf _o >> "displayName");
                _labels set [_t select 1, format ["%1 · %2", (_t select 2) getOrDefault ["callsign", _t select 1], [[_role, name _o] select (_role isEqualTo ""), name _o] select (isPlayer _o)]];
            } forEach _withObj;
        };
    };
} forEach _byCs;
if ((count _drop) > 0) then {
    private _kept = [];
    { if !(_forEachIndex in _drop) then { _kept pushBack _x; }; } forEach _tracks;
    _tracks = _kept;
};
private _labelOf = { params ["_id", "_e"]; _labels getOrDefault [_id, _e getOrDefault ["callsign", _id]] };
private _counts = [0, 0, 0, 0];
{
    private _k = ["LIVE", "STALE", "LOST", "OFFLINE"] find ((_x select 2) getOrDefault ["freshness", "LIVE"]);
    if (_k < 0) then { _k = 2; };
    _counts set [_k, (_counts select _k) + 1];
} forEach _tracks;
private _myFt = player getVariable ["COMSPEC_FT", ""];
_tracks = _tracks select {
    private _e = _x select 2;
    switch (_filter) do {
        case "GROUP": { !isNull (_x select 3) && {group (_x select 3) isEqualTo group player} };
        case "FT": { !isNull (_x select 3) && {group (_x select 3) isEqualTo group player} && {_myFt isNotEqualTo ""} && {((_x select 3) getVariable ["COMSPEC_FT", ""]) isEqualTo _myFt} };
        case "VEH": { (_e getOrDefault ["type", "infantry"]) in ["vehicle", "armor", "air"] || {!isNull (_x select 3) && {(vehicle (_x select 3)) isNotEqualTo (_x select 3)}} };
        case "LOST": { (_e getOrDefault ["freshness", "LIVE"]) in ["LOST", "OFFLINE"] };
        default { true };
    }
};
_tracks sort true;

// En-tête : ma position et le bilan des pistes.
private _y = 0;
private _meOn = (([] call comspec_atak_native_fnc_linkQuality) getOrDefault ["bars", 1]) > 0;
private _hbW = _bw * ([0.24, 0.16] select _land);
private _head = ["COMSPEC_RscCard", [0, 0, _bw - _hbW - _pad / 2, _font * 2.6]] call comspec_atak_native_fnc_pageCtrl;
_head ctrlSetBackgroundColor [0.035, 0.05, 0.07, 1];
_head ctrlSetStructuredText parseText format [
    "<t size='0.75' color='#7fb6e6'> MOI </t><t font='EtelkaMonospacePro' size='0.9'>%1</t>  <t size='0.8' color='#8a9a93'>cap</t> %2° %3  <t size='0.8' color='#8a9a93'>alt</t> %4 m  <t size='0.8' color='#8a9a93'>vit</t> %9 km/h%10<br/><t size='0.75' color='#7fb6e6'> PISTES </t><t color='#5cc76b'>● %5 direct</t>   <t color='#f2ab33'>● %6 ancienne(s)</t>   <t color='#e5483a'>● %7 perdue(s)</t>   <t color='#8a948f'>○ %8 hors ligne</t>",
    [player, 8] call comspec_atak_native_fnc_gridRef, round getDir player, [getDir player] call _card, round (_me select 2), _counts select 0, _counts select 1, _counts select 2, _counts select 3,
    round (_mySpd * 3.6), ["  <t color='#e5483a'>● PAS DE RÉSEAU : pistes figées</t>", ""] select _meOn];
// À droite de l'en-tête : vue LISTE / ORBAT et tiroir des filtres (nombre de filtres actifs).
private _nFilt = ({ profileNamespace getVariable [_x, false] } count ["COMSPEC_ATAK_HideAllyAi", "COMSPEC_ATAK_BftHideAir", "COMSPEC_ATAK_BftHideVeh", "COMSPEC_ATAK_BftHideLost", "COMSPEC_ATAK_BftHideOffline"])
    + ([0, 1] select ((profileNamespace getVariable ["COMSPEC_ATAK_AllyFilter", "ALL"]) isNotEqualTo "ALL"))
    + ([0, 1] select ((profileNamespace getVariable ["COMSPEC_ATAK_BftMaxDist", 0]) isNotEqualTo 0));
private _vb = ["COMSPEC_RscButton", [_bw - _hbW, 0, _hbW, _font * 1.25], ["VUE : LISTE", "VUE : ORBAT"] select (_view isEqualTo "ORBAT")] call comspec_atak_native_fnc_pageCtrl;
_vb ctrlSetFontHeight (_fs * 0.9);
_vb ctrlSetTooltip "Basculer entre le tableau des pistes et l'arbre ORBAT (formation, groupe, équipe)";
_vb ctrlAddEventHandler ["ButtonClick", {
    profileNamespace setVariable ["COMSPEC_ATAK_BftView", ["ORBAT", "LIST"] select ((profileNamespace getVariable ["COMSPEC_ATAK_BftView", "LIST"]) isEqualTo "ORBAT")];
    saveProfileNamespace;
    (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["bftFiltersOpen", false];
    [{ ['BFT'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
}];
private _fb = ["COMSPEC_RscButton", [_bw - _hbW, _font * 1.35, _hbW, _font * 1.25], ["FILTRES", format ["FILTRES (%1)", _nFilt]] select (_nFilt > 0)] call comspec_atak_native_fnc_pageCtrl;
_fb ctrlSetFontHeight (_fs * 0.9);
if (_filtersOpen || {_nFilt > 0}) then { _fb ctrlSetBackgroundColor ([[0.18, 0.32, 0.50, 1], [0.28, 0.55, 0.85, 1]] select _filtersOpen); _fb ctrlSetTextColor [1, 1, 1, 1]; };
_fb ctrlSetTooltip "Masquer IA, aériens, véhicules, pistes anciennes ou hors ligne ; distance max (aussi sur la carte)";
_fb ctrlAddEventHandler ["ButtonClick", {
    private _st = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
    _st set ["bftFiltersOpen", !(_st getOrDefault ["bftFiltersOpen", false])];
    _st set ["bftFiltersAnim", true];
    [{ ['BFT'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
}];
_y = _font * 2.6 + _pad / 2;

// Filtres
private _opts = [["TOUS", "ALL"], ["MON ÉQUIPE", "FT"], ["MON GROUPE", "GROUP"], ["VÉHICULES", "VEH"], ["PERDUS", "LOST"]];
private _fw = (_bw - _pad * 2) / 5;
{
    _x params ["_t", "_k"];
    private _b = ["COMSPEC_RscButton", [_pad + _forEachIndex * _fw, _y, _fw - _pad / 4, _font * 1.4], _t] call comspec_atak_native_fnc_pageCtrl;
    _b ctrlSetFontHeight (_fs * 0.9);
    if (_k isEqualTo _filter) then { _b ctrlSetBackgroundColor [0.28, 0.55, 0.85, 1]; _b ctrlSetTextColor [1, 1, 1, 1]; };
    _b ctrlAddEventHandler ["ButtonClick", compile format ["(uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) set ['bftFilter', '%1']; [{ ['BFT'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;", _k]];
} forEach _opts;
_y = _y + _font * 1.4 + _pad / 2;

// Tableau des pistes (à gauche en paysage, en haut en portrait) et fiche de la piste choisie.
private _detailH = [_bh * 0.36, 0] select _land;
private _listW = [_bw, _bw * 0.58] select _land;
private _listH = _bh - _y - _detailH - _pad / 2;
private _cols = ["COMSPEC_RscLabel", [_pad, _y, _listW - _pad, _fs * 1.2], ""] call comspec_atak_native_fnc_pageCtrl;
_cols ctrlSetText (switch (true) do {
    case _filtersOpen: { "  FILTRES DU BFT (aussi appliqués à la carte)" };
    case (_view isEqualTo "ORBAT"): { "  FORMATION › GROUPE › ÉQUIPE › MEMBRE        DISTANCE · GISEMENT · TRAJET · ÂGE" };
    default { "  INDICATIF                 DISTANCE · GISEMENT · TRAJET · ÂGE" };
});
_cols ctrlSetFontHeight (_fs * 0.8);
_y = _y + _fs * 1.2;
_listH = _listH - _fs * 1.2;
private _ids = [];
private _rows = [];
private _selIdx = -1;
private _firstRow = -1;
private _list = controlNull;
if (_filtersOpen) then {
    // Tiroir des filtres : à la place du tableau, il descend sous l'en-tête (groupe qui s'agrandit).
    uiNamespace setVariable ["COMSPEC_ATAK_BftFilterSet", {
        params ["_var", "_val"];
        profileNamespace setVariable [_var, _val];
        saveProfileNamespace;
        [] call comspec_atak_native_fnc_localDataRefresh;
        [{ ['BFT'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
    }];
    private _d = [] call comspec_atak_native_fnc_display;
    private _g = _d ctrlCreate ["COMSPEC_RscControlsGroup", -1, _d displayCtrl 88531];
    private _made = [_g];
    private _mkF = {
        params ["_class", "_pos", ["_text", ""]];
        private _c = _d ctrlCreate [_class, -1, _g];
        _c ctrlSetPosition _pos;
        if (_text isNotEqualTo "") then { _c ctrlSetText _text; };
        _c ctrlCommit 0;
        _made pushBack _c;
        _c
    };
    private _w = _listW - _pad;
    private _rh = _font * 1.45;
    private _fy = _pad / 2;
    private _bg = ["COMSPEC_RscText", [0, 0, _w, 10]] call _mkF;
    _bg ctrlSetBackgroundColor [0.035, 0.05, 0.07, 0.98];
    private _acc = ([] call comspec_atak_native_fnc_accent) select 0;
    {
        _x params ["_var", "_label"];
        private _on = profileNamespace getVariable [_var, false];
        private _lb = ["COMSPEC_RscText", [_pad, _fy, _w * 0.72 - _pad, _rh], _label] call _mkF;
        _lb ctrlSetFontHeight _fs;
        private _b = ["COMSPEC_RscButton", [_w * 0.72, _fy + _rh * 0.08, _w * 0.28 - _pad, _rh * 0.84], ["NON", "OUI"] select _on] call _mkF;
        _b ctrlSetFontHeight (_fs * 0.9);
        if (_on) then { _b ctrlSetBackgroundColor _acc; _b ctrlSetTextColor [0.03, 0.04, 0.035, 1]; };
        _b ctrlAddEventHandler ["ButtonClick", compile format ["['%1', %2] call (uiNamespace getVariable 'COMSPEC_ATAK_BftFilterSet');", _var, !_on]];
        _fy = _fy + _rh;
    } forEach [
        ["COMSPEC_ATAK_HideAllyAi", "Masquer les IA"],
        ["COMSPEC_ATAK_BftHideAir", "Masquer les aériens"],
        ["COMSPEC_ATAK_BftHideVeh", "Masquer les véhicules (l'équipage reste dans son groupe)"],
        ["COMSPEC_ATAK_BftHideLost", "Masquer les pistes anciennes et perdues"],
        ["COMSPEC_ATAK_BftHideOffline", "Masquer les pistes hors ligne"]
    ];
    {
        _x params ["_var", "_def", "_label", "_opts"];
        private _cur = profileNamespace getVariable [_var, _def];
        private _lb = ["COMSPEC_RscText", [_pad, _fy, _w - _pad * 2, _rh], _label] call _mkF;
        _lb ctrlSetFontHeight _fs;
        _fy = _fy + _rh;
        private _ow = (_w - _pad * 2) / (count _opts);
        {
            _x params ["_t", "_v"];
            private _b = ["COMSPEC_RscButton", [_pad + _forEachIndex * _ow, _fy, _ow - _pad / 4, _rh * 0.9], _t] call _mkF;
            _b ctrlSetFontHeight (_fs * 0.9);
            if (_v isEqualTo _cur) then { _b ctrlSetBackgroundColor _acc; _b ctrlSetTextColor [0.03, 0.04, 0.035, 1]; };
            _b ctrlAddEventHandler ["ButtonClick", compile format ["['%1', %2] call (uiNamespace getVariable 'COMSPEC_ATAK_BftFilterSet');", _var, str _v]];
        } forEach _opts;
        _fy = _fy + _rh + _pad / 2;
    } forEach [
        ["COMSPEC_ATAK_AllyFilter", "ALL", "Alliés suivis", [["TOUS", "ALL"], ["MON GROUPE", "GROUP"], ["MON UNITÉ", "ORBAT"]]],
        ["COMSPEC_ATAK_BftMaxDist", 0, "Distance max", [["TOUT", 0], ["1 KM", 1000], ["3 KM", 3000], ["10 KM", 10000]]]
    ];
    private _hw = (_w - _pad * 3) / 2;
    private _r = ["COMSPEC_RscButton", [_pad, _fy, _hw, _rh], "RÉINITIALISER"] call _mkF;
    _r ctrlSetFontHeight _fs;
    _r ctrlAddEventHandler ["ButtonClick", {
        { profileNamespace setVariable [_x, nil]; } forEach ["COMSPEC_ATAK_HideAllyAi", "COMSPEC_ATAK_BftHideAir", "COMSPEC_ATAK_BftHideVeh", "COMSPEC_ATAK_BftHideLost", "COMSPEC_ATAK_BftHideOffline", "COMSPEC_ATAK_AllyFilter", "COMSPEC_ATAK_BftMaxDist"];
        ["COMSPEC_ATAK_BftMaxDist", 0] call (uiNamespace getVariable "COMSPEC_ATAK_BftFilterSet");
    }];
    private _c = ["COMSPEC_RscButtonPrimary", [_pad * 2 + _hw, _fy, _hw, _rh], "FERMER"] call _mkF;
    _c ctrlSetFontHeight _fs;
    _c ctrlAddEventHandler ["ButtonClick", { (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["bftFiltersOpen", false]; [{ ['BFT'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }];
    _fy = _fy + _rh + _pad;
    _bg ctrlSetPosition [0, 0, _w, _fy max _listH];
    _bg ctrlCommit 0;
    if ((_s getOrDefault ["bftFiltersAnim", false]) && {["anim"] call comspec_atak_native_fnc_fx}) then {
        _g ctrlSetPosition [_pad / 2, _y, _w, 0];
        _g ctrlCommit 0;
        _g ctrlSetPosition [_pad / 2, _y, _w, _listH];
        _g ctrlCommit 0.18;
    } else {
        _g ctrlSetPosition [_pad / 2, _y, _w, _listH];
        _g ctrlCommit 0;
    };
    _s set ["bftFiltersAnim", false];
    private _pc = uiNamespace getVariable ["COMSPEC_ATAK_PageControls", []];
    _pc append _made;
    uiNamespace setVariable ["COMSPEC_ATAK_PageControls", _pc];
} else {
_list = ["COMSPEC_RscListBox", [0, _y, _listW, _listH]] call comspec_atak_native_fnc_pageCtrl;
_list ctrlSetFontHeight _font;
_list ctrlSetBackgroundColor [0.03, 0.04, 0.05, 0.9];
// Une ligne de piste (tableau et ORBAT). Params : [piste, retrait, mention après l'indicatif]
private _addMember = {
    params ["_t", ["_indent", ""], ["_extra", ""]];
    _t params ["_dist", "_id", "_e", "_obj", "_pos"];
    private _f = _e getOrDefault ["freshness", "LIVE"];
    private _age = round (diag_tickTime - (_e getOrDefault ["updated", diag_tickTime]));
    ([_e] call comspec_atak_native_fnc_symbology) params ["_icon", "_color"];
    if ((profileNamespace getVariable ["COMSPEC_ATAK_UnitSymbols", "DOTS"]) isEqualTo "NATO") then { _icon = ([_e] call comspec_atak_native_fnc_natoSymbol) select 0; };
    // État médical masqué (Réglages > Réalisme, fn_medShow) : seul le décès compte.
    private _dead = !isNull _obj && {!alive _obj || {lifeState _obj isEqualTo "INCAPACITATED" && {["state", "allies"] call comspec_atak_native_fnc_medShow}}};
    // Équipe de feu (camp ami) : couleur de l'icône et nom de l'équipe après l'indicatif.
    private _ft = if (isNull _obj) then { createHashMap } else { [_obj] call comspec_atak_native_fnc_ftInfo };
    private _ftName = _ft getOrDefault ["name", ""];
    if (_ftName isNotEqualTo "") then { _color = _ft get "rgba"; };
    private _i = _list lbAdd format ["%5%1%2%3%4%6", [_id, _e] call _labelOf, ["", format ["  · %1%2", _ftName, ["", " " + (_ft get "roleShort")] select ((_ft getOrDefault ["roleShort", ""]) isNotEqualTo "")]] select (_ftName isNotEqualTo ""), ["", "  (blessé)"] select _dead, ["", "  (hors ligne)"] select (_f isEqualTo "OFFLINE"), _indent, _extra];
    _list lbSetPicture [_i, _icon];
    _list lbSetPictureColor [_i, [_color, [0.45, 0.50, 0.55, 1]] select (_f in ["LOST", "OFFLINE"])];
    _list lbSetPictureColorSelected [_i, [1, 1, 1, 1]];
    _list lbSetTextRight [_i, format ["%1 · %2° %3 · %4 · %5", [_dist] call _distTxt, round (player getDir _pos), [player getDir _pos] call _card, [_dist, _mySpd max _walk] call _etaTxt, [format ["%1 s", _age], format ["%1 min", floor (_age / 60)]] select (_age >= 60)]];
    _list lbSetColor [_i, switch (true) do { case _dead: { [0.88, 0.25, 0.22, 1] }; case (_f isEqualTo "LIVE"): { [0.90, 0.94, 0.91, 1] }; case (_f isEqualTo "STALE"): { [0.95, 0.67, 0.20, 1] }; default { [0.55, 0.60, 0.58, 1] }; }];
    _list lbSetColorRight [_i, [0.62, 0.70, 0.66, 1]];
    _list lbSetTooltip [_i, format ["%1 · %2 · trajet %3 %4", (_fresh getOrDefault [_f, [_f]]) select 0, mapGridPosition _pos, [_dist, _mySpd max _walk] call _etaTxt, ["à pied", "à ma vitesse"] select (_mySpd > _walk)]];
    _ids pushBack _id;
    _rows pushBack ["m", _id];
    if (_firstRow < 0) then { _firstRow = _i; };
    if (_id isEqualTo _selId) then { _selIdx = _i; };
};
// Ligne d'en-tête de l'ORBAT (clic : replier / déplier).
private _addHead = {
    params ["_key", "_text", "_right", "_rgba"];
    private _i = _list lbAdd _text;
    _list lbSetColor [_i, _rgba];
    _list lbSetTextRight [_i, _right];
    _list lbSetColorRight [_i, [0.62, 0.70, 0.66, 1]];
    _list lbSetTooltip [_i, "Clic : replier / déplier"];
    _rows pushBack ["h", _key];
    _ids pushBack "";
};
if (_view isEqualTo "ORBAT") then {
    private _open = uiNamespace getVariable ["COMSPEC_ATAK_BftOrbatOpen", createHashMap];
    uiNamespace setVariable ["COMSPEC_ATAK_BftOrbatOpen", _open];
    private _sign = { params ["_k"]; ["+ ", "- "] select (_open getOrDefault [_k, true]) };
    // Préfixe d'un indicatif de groupe : « Star 4 » → « Star », « Shadow 2-1 » → « Shadow ».
    private _prefix = { params ["_n"]; private _p = trim (_n regexReplace ["[ -]*[0-9][0-9 ./-]*$", ""]); [_p, _n] select (_p isEqualTo "") };
    // Formation › groupe › pistes.
    private _forms = createHashMap;
    {
        _x params ["_dist", "_id", "_e", "_obj", "_pos"];
        private _gName = if (isNull _obj) then { ((_e getOrDefault ["callsign", _id]) regexReplace ["-[0-9]+$", ""]) } else { groupId group _obj };
        private _gKey = if (isNull _obj) then { "a:" + toLower _gName } else { str group _obj };
        private _orb = _e getOrDefault ["orbat", ""];
        if (!isNull _obj && {_orb isEqualTo ""}) then { _orb = _obj getVariable ["COMSPEC_ATAK_Orbat", ""]; };
        private _form = if (_orb isEqualType "" && {_orb isNotEqualTo ""}) then { [_orb] call comspec_atak_native_fnc_abbrev } else { toUpper ([_gName] call _prefix) };
        private _groups = _forms getOrDefault [_form, createHashMap];
        private _gEntry = _groups getOrDefault [_gKey, [_gName, [if (isNull _obj) then { grpNull } else { group _obj }], []]];
        (_gEntry select 2) pushBack _x;
        _groups set [_gKey, _gEntry];
        _forms set [_form, _groups];
    } forEach _tracks;
    private _formNames = keys _forms;
    _formNames sort true;
    {
        private _form = _x;
        private _groups = _forms get _form;
        private _fk = "f:" + _form;
        private _nf = 0;
        { _nf = _nf + count ((_groups get _x) select 2); } forEach (keys _groups);
        [_fk, format ["%1%2", [_fk] call _sign, _form], format ["%1 piste(s)", _nf], [0.50, 0.72, 0.92, 1]] call _addHead;
        if (_open getOrDefault [_fk, true]) then {
            private _gKeys = (keys _groups) apply { [toLower ((_groups get _x) select 0), _x] };
            _gKeys sort true;
            _gKeys = _gKeys apply { _x select 1 };
            {
                private _gk = _x;
                (_groups get _gk) params ["_gName", "_gArr", "_mem"];
                private _grp = _gArr select 0;
                private _ldr = if (isNull _grp) then { objNull } else { leader _grp };
                private _n = if (isNull _grp) then { count _mem } else { count units _grp };
                private _ech = switch (true) do { case (_n <= 4): { "équipe" }; case (_n <= 13): { "groupe" }; default { "section" }; };
                private _ldrTxt = switch (true) do {
                    case (isNull _ldr): { "" };
                    case (_ldr isEqualTo player): { "chef : moi" };
                    default { format ["chef à %1", [player distance2D _ldr] call _distTxt] };
                };
                private _gKey2 = "g:" + _gk;
                [_gKey2, format ["   %1%2  ·  %3%4", [_gKey2] call _sign, _gName, _ech, ["", "  (mon groupe)"] select (_grp isEqualTo group player)],
                    format ["%1 membre(s)%2", _n, ["", "  ·  " + _ldrTxt] select (_ldrTxt isNotEqualTo "")], [0.86, 0.90, 0.88, 1]] call _addHead;
                if (_open getOrDefault [_gKey2, true]) then {
                    // Chef d'abord, puis par distance.
                    _mem = _mem apply { [[1, 0] select (!isNull _ldr && {(_x select 3) isEqualTo _ldr}), _x select 0, _x] };
                    _mem sort true;
                    _mem = _mem apply { _x select 2 };
                    private _teams = createHashMap;
                    private _free = [];
                    {
                        private _o = _x select 3;
                        private _fi = if (isNull _o) then { createHashMap } else { [_o] call comspec_atak_native_fnc_ftInfo };
                        if ((_fi getOrDefault ["id", ""]) isEqualTo "") then { _free pushBack _x; } else {
                            private _te = _teams getOrDefault [_fi get "id", [_fi get "name", _fi get "rgba", []]];
                            (_te select 2) pushBack _x;
                            _teams set [_fi get "id", _te];
                        };
                    } forEach _mem;
                    private _tag = {
                        params ["_t"];
                        private _o = _t select 3;
                        private _tx = "";
                        if (!isNull _o && {_o isEqualTo _ldr}) then { _tx = "  · chef"; };
                        if (!isNull _o && {(vehicle _o) isNotEqualTo _o}) then { _tx = _tx + format ["  [%1]", getText (configOf vehicle _o >> "displayName")]; };
                        _tx
                    };
                    private _tIds = keys _teams;
                    _tIds sort true;
                    {
                        (_teams get _x) params ["_tName", "_tRgba", "_tMem"];
                        private _tk = "t:" + _gk + "|" + _x;
                        [_tk, format ["      %1%2", [_tk] call _sign, _tName], format ["%1", count _tMem], _tRgba] call _addHead;
                        if (_open getOrDefault [_tk, true]) then { { [_x, "         ", [_x] call _tag] call _addMember; } forEach _tMem; };
                    } forEach _tIds;
                    { [_x, "      ", [_x] call _tag] call _addMember; } forEach _free;
                };
            } forEach _gKeys;
        };
    } forEach _formNames;
} else {
    { [_x] call _addMember; } forEach _tracks;
};
if ((count _tracks) isEqualTo 0) then {
    private _i = _list lbAdd (["Aucune piste amie suivie.", "Aucune piste pour ce filtre."] select (_filter isNotEqualTo "ALL" || {_nFilt > 0}));
    _list lbSetColor [_i, [0.55, 0.60, 0.58, 1]];
    _rows pushBack ["", ""];
};
uiNamespace setVariable ["COMSPEC_ATAK_BftIds", _ids];
uiNamespace setVariable ["COMSPEC_ATAK_BftRows", _rows];
// Piste choisie absente (filtrée) : la première de la liste ; repliée dans l'ORBAT : elle reste choisie.
if (_selIdx < 0 && {_firstRow >= 0} && {(_tracks findIf { (_x select 1) isEqualTo _selId }) < 0}) then { _selIdx = _firstRow; _selId = _ids select _firstRow; _s set ["bftSel", _selId]; };
if (_selIdx >= 0) then { _list lbSetCurSel _selIdx; };
_list ctrlAddEventHandler ["LBSelChanged", {
    params ["", "_i"];
    ((uiNamespace getVariable ["COMSPEC_ATAK_BftRows", []]) param [_i, []]) params [["_k", ""], ["_v", ""]];
    private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
    // En-tête ORBAT : replier / déplier (mémorisé).
    if (_k isEqualTo "h") exitWith {
        private _open = uiNamespace getVariable ["COMSPEC_ATAK_BftOrbatOpen", createHashMap];
        _open set [_v, !(_open getOrDefault [_v, true])];
        uiNamespace setVariable ["COMSPEC_ATAK_BftOrbatOpen", _open];
        [{ ['BFT'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
    };
    if (_k isNotEqualTo "m" || {_v isEqualTo (_s getOrDefault ["bftSel", ""])}) exitWith {};
    _s set ["bftSel", _v];
    [{ ['BFT'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
}];
_list ctrlAddEventHandler ["LBDblClick", {
    params ["", "_i"];
    if ((((uiNamespace getVariable ["COMSPEC_ATAK_BftRows", []]) param [_i, []]) param [0, ""]) isNotEqualTo "m") exitWith {};
    [{ ["center"] call comspec_atak_native_fnc_bftAction; }] call CBA_fnc_execNextFrame;
}];
};

// Fiche de la piste choisie.
private _dRect = if (_land) then { [_listW + _pad / 2, _y - _fs * 1.2, _bw - _listW - _pad / 2, _bh - _y + _fs * 1.2] } else { [0, _y + _listH + _pad / 2, _bw, _detailH] };
_dRect params ["_dx", "_dy", "_dw", "_dh"];
private _sel = (_tracks select { (_x select 1) isEqualTo _selId }) param [0, []];
private _det = ["COMSPEC_RscCard", [_dx, _dy, _dw, _dh - _font * 1.6], ""] call comspec_atak_native_fnc_pageCtrl;
_det ctrlSetBackgroundColor [0.035, 0.05, 0.07, 1];
if ((count _sel) isEqualTo 0) then {
    _det ctrlSetStructuredText parseText "<t color='#8a9a93'>Choisissez une piste dans le tableau.</t>";
} else {
    _sel params ["_dist", "_id", "_e", "_obj", "_pos"];
    private _f = _e getOrDefault ["freshness", "LIVE"];
    (_fresh getOrDefault [_f, [_f, "#8a9a93"]]) params ["_ft", "_fc"];
    private _type = switch (_e getOrDefault ["type", "infantry"]) do { case "air": { "Aérien" }; case "armor": { "Blindé" }; case "vehicle": { "Véhicule" }; default { "Infanterie" }; };
    private _isLive = _f isEqualTo "LIVE";
    private _speed = if (isNull _obj || {!_isLive}) then { "—" } else { format ["%1 km/h", round (vectorMagnitude velocity vehicle _obj * 3.6)] };
    private _age = round (diag_tickTime - (_e getOrDefault ["updated", diag_tickTime]));
    private _ageTxt = [format ["%1 s", _age], format ["%1 min", floor (_age / 60)]] select (_age >= 60);
    // Trajet : à pied, à ma vitesse, et rapprochement mutuel (vitesse relative le long de l'axe).
    private _eta = format ["<t color='#8a9a93'>Trajet</t> %1 à pied", [_dist, _walk] call _etaTxt];
    if (_mySpd > _walk) then { _eta = _eta + format [" · %1 à ma vitesse", [_dist, _mySpd] call _etaTxt]; };
    private _close = "";
    if (_isLive && {!isNull _obj} && {_dist > 5}) then {
        private _ax = vectorNormalized ((getPosASL _obj) vectorDiff (getPosASL player));
        private _vc = ((velocity vehicle player) vectorDiff (velocity vehicle _obj)) vectorDotProduct _ax;
        _close = switch (true) do {
            case (_vc > 0.4): { format ["<br/><t color='#5cc76b'>Se rapproche</t> à %1 km/h · jonction dans %2", round (_vc * 3.6), [_dist, _vc] call _etaTxt] };
            case (_vc < -0.4): { format ["<br/><t color='#f2ab33'>S'éloigne</t> à %1 km/h", round (abs _vc * 3.6)] };
            default { "<br/><t color='#8a9a93'>Distance stable</t>" };
        };
    };
    // Itinéraire GPS en cours vers cette piste.
    private _rt = missionNamespace getVariable ["COMSPEC_ATAK_Route", createHashMap];
    if ((count _rt) > 0 && {(_rt getOrDefault ["label", ""]) isEqualTo (_e getOrDefault ["callsign", _id])}) then {
        // Reste calculé sur le dernier point suivi par le guidage (sans relancer le guidage lui-même).
        private _ri = _rt getOrDefault ["idx", 0];
        private _left = ((_rt get "total") - (((_rt get "cum") select _ri) + (((_rt get "pts") select _ri) distance2D (_rt getOrDefault ["proj", (_rt get "pts") select _ri])))) max 0;
        _close = _close + format ["<br/><t color='#7fb6e6'>GPS</t> %1 par la route · arrivée dans %2", [_left] call _distTxt, [_left, _mySpd max _walk] call _etaTxt];
    };
    private _offTxt = switch (true) do {
        case (_f isEqualTo "OFFLINE"): { format ["<br/><t color='#e5483a'>Hors ligne</t> <t color='#8a9a93'>(téléphone éteint, cassé ou sans signal) · dernière position il y a %1</t>", _ageTxt] };
        case (!_isLive): { format ["<br/><t color='#f2ab33'>Dernière position il y a %1</t>", _ageTxt] };
        default { "" };
    };
    private _health = if (isNull _obj) then { "—" } else { if (_f isEqualTo "OFFLINE") then { "<t color='#8a9a93'>inconnu</t>" } else { switch (true) do { case (!alive _obj): { "<t color='#e5483a'>Mort</t>" }; case !(["state", "allies"] call comspec_atak_native_fnc_medShow): { "<t color='#8a9a93'>—</t>" }; case (lifeState _obj isEqualTo "INCAPACITATED"): { "<t color='#e5483a'>Inconscient</t>" }; case ((damage _obj) > 0.25): { "<t color='#f2ab33'>Blessé</t>" }; default { "<t color='#5cc76b'>Apte</t>" }; } } };
    private _grp = if (isNull _obj) then { "Athena" } else { groupId group _obj };
    private _ftI = if (isNull _obj) then { createHashMap } else { [_obj] call comspec_atak_native_fnc_ftInfo };
    private _ftTxt = switch (true) do {
        case ((_ftI getOrDefault ["name", ""]) isNotEqualTo ""): { format ["<br/><t color='#8a9a93'>Équipe</t> <t color='%1' font='RobotoCondensedBold'>● %2</t>%3", _ftI get "hex", [_ftI get "name"] call _esc, ["", format [" · %1", _ftI get "roleLabel"]] select ((_ftI get "roleLabel") isNotEqualTo "")] };
        case ((_ftI getOrDefault ["roleLabel", ""]) isNotEqualTo ""): { format ["<br/><t color='#8a9a93'>Rôle</t> %1", _ftI get "roleLabel"] };
        default { "" };
    };
    private _crew = if (!isNull _obj && {vehicle _obj isNotEqualTo _obj}) then { format ["<br/><t color='#8a9a93'>Dans</t> %1", getText (configOf vehicle _obj >> "displayName")] } else { "" };
    _det ctrlSetStructuredText parseText format [
        "<t size='1.15' font='RobotoCondensedBold'>%1</t>  <t size='0.8' color='%2'>● %3</t><br/><t color='#8a9a93'>%4 · %5</t>%6%18<br/><t font='EtelkaMonospacePro'>%7</t><br/>%8 · %9° %10<br/>%15%16<br/><t color='#8a9a93'>Vitesse</t> %11  <t color='#8a9a93'>Cap</t> %12°  <t color='#8a9a93'>Alt</t> %13 m<br/><t color='#8a9a93'>État</t> %14%17",
        [[_id, _e] call _labelOf] call _esc, _fc, _ft, _type, [_grp] call _esc, _crew,
        [_pos, 8] call comspec_atak_native_fnc_gridRef,
        [format ["%1 m", round _dist], format ["%1 km", (_dist / 1000) toFixed 2]] select (_dist >= 1000), round (player getDir _pos), [player getDir _pos] call _card,
        _speed, round (_e getOrDefault ["heading", 0]), round (_pos select 2), _health, _eta, _close, _offTxt, _ftTxt];
};
private _bw3 = (_dw - _pad * 3) / 4;
{
    _x params ["_t", "_act", "_on"];
    private _b = [["COMSPEC_RscButton", "COMSPEC_RscButtonPrimary"] select (_forEachIndex isEqualTo 1), [_dx + _forEachIndex * (_bw3 + _pad), _dy + _dh - _font * 1.5, _bw3, _font * 1.4], _t] call comspec_atak_native_fnc_pageCtrl;
    _b ctrlSetFontHeight _fs;
    _b ctrlEnable _on;
    _b ctrlAddEventHandler ["ButtonClick", compile format ["[{ ['%1'] call comspec_atak_native_fnc_bftAction; }] call CBA_fnc_execNextFrame;", _act]];
} forEach [
    ["CARTE", "center", (count _sel) > 0],
    ["Y ALLER", "route", (count _sel) > 0],
    ["SMS", "sms", (count _sel) > 0 && {!isNull (_sel select 3)} && {isPlayer (_sel select 3)}],
    [["VIBRER", "HORS LIGNE"] select ((count _sel) > 0 && {((_sel select 2) getOrDefault ["freshness", "LIVE"]) isEqualTo "OFFLINE"}), "buzz",
        _meOn && {(count _sel) > 0} && {!isNull (_sel select 3)} && {isPlayer (_sel select 3)} && {((_sel select 2) getOrDefault ["freshness", "LIVE"]) isNotEqualTo "OFFLINE"}]
];
true
