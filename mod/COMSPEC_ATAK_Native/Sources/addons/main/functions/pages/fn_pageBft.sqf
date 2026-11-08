/*
    BFT (Blue Force Tracking) : tableau des amis suivis, comme un terminal JBC-P.
    En-tête : ma position (grille, cap, altitude) et l'état des pistes (direct / ancienne / perdue).
    Filtres : TOUS, MON ÉQUIPE (équipe de feu), MON GROUPE, VÉHICULES, PERDUS. Tri par distance.
    Équipes de feu : l'icône de chaque piste prend la couleur de son équipe, nom de l'équipe et rôle dans la fiche.
    Tableau à colonnes (RscListNBox) : niveau de l'arbre et symbole, indicatif, état (une forme, pas seulement une couleur),
    distance, azimut, âge ; batterie faible ou signal nul du téléphone après l'indicatif ; le trajet en infobulle et dans la fiche.
    Une piste hors ligne (téléphone éteint, cassé ou sans signal) reste figée à sa dernière position connue,
    et sans réseau toutes les pistes vieillissent.
    Piste choisie : fiche (trajet, rapprochement, itinéraire GPS) et actions (carte, GPS, SMS, faire vibrer, voir sa caméra).
    Vues (profil COMSPEC_ATAK_BftView) : LISTE (tableau trié par distance) ou ORBAT : arbre repliable de l'ORBAT du site
    (fn_bftOrbat : unité › postes et membres, en ligne ou non, postes vacants), puis section « Hors ORBAT du site » :
    les pistes et les joueurs de mon camp absents de cet ORBAT, en formation (ORBAT Athena abrégé, sinon préfixe de l'indicatif
    « Star 4 » → STAR) › groupe (chef, échelon, effectif, distance au chef) › équipe de feu › membres.
    Sans ORBAT du site : un message et cette seconde partie seule. Repli mémorisé dans uiNamespace COMSPEC_ATAK_BftOrbatOpen.
    État d'un membre de l'ORBAT : piste (distance, gisement), en jeu sans piste, hors ligne (absent du serveur), identité
    protégée, moi ; rapprochement par empreinte du Steam ID, jamais par le nom ; un joueur d'en face n'est jamais rapproché.
    Les compteurs du bandeau comptent les membres de l'ORBAT absents du serveur dans « hors ligne » (sauf filtre hors ligne).
    FILTRES : tiroir de réglages persistants (fn_bftFilter : IA, aériens, véhicules, pistes anciennes, hors ligne,
    mon groupe / mon unité, distance max), appliqués aussi à la carte.
*/
// Mode « update » (fn_statusUpdate) : même vue, même filtre, tableau affiché : lignes, en-tête et fiche réécrites sur
// place (aucun contrôle recréé, défilement du tableau gardé). Renvoie false si un rendu complet est nécessaire.
params [["_mode", ""]];
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
private _esc = { params ["_t"]; if !(_t isEqualType "") then { _t = str _t; }; [[[_t, "&", "&amp;"] call CBA_fnc_replace, "<", "&lt;"] call CBA_fnc_replace, ">", "&gt;"] call CBA_fnc_replace };
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
private _live = uiNamespace getVariable ["COMSPEC_ATAK_BftLive", createHashMap];
private _upd = _mode isEqualTo "update";
if (_upd && {
    (count _live) isEqualTo 0 || {_filtersOpen} || {(_s getOrDefault ["bftBuzz", ""]) isNotEqualTo ""}
    || {[_view, _filter, _land, _bw, _bh] isNotEqualTo (_live get "key")}
    || {((_live get "ctrls") findIf { isNull _x || {!ctrlShown _x} }) >= 0}
}) exitWith { false };
private _fresh = createHashMapFromArray [["LIVE", ["DIRECT", (COMSPEC_ATAK_Th get "hexOk")]], ["STALE", ["ANCIENNE", (COMSPEC_ATAK_Th get "hexWarn")]], ["LOST", ["PERDUE", (COMSPEC_ATAK_Th get "hexAlert")]], ["OFFLINE", ["HORS LIGNE", (COMSPEC_ATAK_Th get "hexTextMuted")]]];

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
// ORBAT du site : relu en tâche de fond (5 min au plus entre deux lectures), jamais d'attente ici.
["load"] call comspec_atak_native_fnc_bftOrbat;
private _orb = ["store"] call comspec_atak_native_fnc_bftOrbat;
private _oUnits = _orb get "units";
private _oPeople = _orb get "people";
private _hasOrb = (count _oUnits) > 0;
(["presence"] call comspec_atak_native_fnc_bftOrbat) params ["", "_onKey", "_strays"];
private _hideOff = profileNamespace getVariable ["COMSPEC_ATAK_BftHideOffline", false];
private _tidOf = { params ["_o"]; private _i = netId _o; if (_i isEqualTo "0:0") then { _i = str _o; }; _i };
private _trackById = createHashMapFromArray (_tracks apply { [_x select 1, _x] });
// Piste → personne de l'ORBAT, et joueurs de mon camp hors ORBAT sans piste (les autres sont dans les pistes).
private _trackKey = createHashMap;
{ _trackKey set [[_y] call _tidOf, _x]; } forEach _onKey;
private _strayKey = createHashMap;
{
    private _tid = [_x] call _tidOf;
    if !(_tid in _trackById) then { _strayKey set ["p:s" + _tid, [_x, [_x, true] call comspec_atak_native_fnc_unitCallsign]]; };
} forEach _strays;
// État d'une personne de l'ORBAT : [état, joueur, piste].
private _stOf = {
    params ["_key"];
    private _so = _strayKey getOrDefault [_key, []];
    if ((count _so) > 0) exitWith { ["NOTRACK", _so select 0, []] };
    private _kind = (_oPeople getOrDefault [_key, [0, 0, 0, 0, 0]]) select 4;
    if (_kind isEqualTo 2) exitWith { ["VACANT", objNull, []] };
    if (_kind isEqualTo 1) exitWith { ["ANON", objNull, []] };
    private _o = _onKey getOrDefault [_key, objNull];
    if (isNull _o) exitWith { ["OFFLINE", objNull, []] };
    if (_o isEqualTo player) exitWith { ["ME", _o, []] };
    private _t = _trackById getOrDefault [[_o] call _tidOf, []];
    [["NOTRACK", "TRACK"] select ((count _t) > 0), _o, _t]
};
private _myFtP = player getVariable ["COMSPEC_FT", ""];
// Filtres du BFT appliqués à une personne de l'ORBAT. Poste vacant et identité protégée : seulement dans TOUS.
private _passes = {
    params ["_st", "_o", "_t"];
    if (_hideOff && {_st isEqualTo "OFFLINE"}) exitWith { false };
    switch (_filter) do {
        case "GROUP": { !isNull _o && {group _o isEqualTo group player} };
        case "FT": { !isNull _o && {group _o isEqualTo group player} && {_myFtP isNotEqualTo ""} && {(_o getVariable ["COMSPEC_FT", ""]) isEqualTo _myFtP} };
        case "VEH": { !isNull _o && {(vehicle _o) isNotEqualTo _o} };
        case "LOST": { _st in ["OFFLINE", "NOTRACK"] || {(count _t) > 0 && {((_t select 2) getOrDefault ["freshness", "LIVE"]) in ["LOST", "OFFLINE"]}} };
        default { true };
    }
};
// Personne choisie qui retrouve une piste : la piste prend sa place (même ligne, actions complètes).
if ((_selId select [0, 2]) isEqualTo "p:") then {
    ([_selId] call _stOf) params ["_sst", "", "_stk"];
    if (_sst isEqualTo "TRACK") then { _selId = _stk select 1; _s set ["bftSel", _selId]; };
};
private _counts = [0, 0, 0, 0];
{
    private _k = ["LIVE", "STALE", "LOST", "OFFLINE"] find ((_x select 2) getOrDefault ["freshness", "LIVE"]);
    if (_k < 0) then { _k = 2; };
    _counts set [_k, (_counts select _k) + 1];
} forEach _tracks;
if (_hasOrb && {!_hideOff}) then { _counts set [3, (_counts select 3) + (((_orb get "total") - count _onKey) max 0)]; };
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
private _head = if (_upd) then { (_live get "ctrls") select 0 } else {
    private _c = ["COMSPEC_RscCard", [0, 0, _bw - _hbW - _pad / 2, _font * 1.35]] call comspec_atak_native_fnc_pageCtrl;
    _c ctrlSetBackgroundColor (COMSPEC_ATAK_Th get "surface");
    _c
};
// En une ligne : ma grille, mon cap, ma vitesse (en portrait étroit : la grille seule), puis quatre pastilles : deux lettres et
// le nombre, jamais la couleur seule (DI direct, AN ancienne, PE perdue, HL hors ligne) ; le détail est dans l'infobulle.
private _pill = { params ["_w", "_n", "_hex"]; format ["<t color='%1'>%2 %3</t>", _hex, (_w) call comspec_atak_native_fnc_tr, _n] };
private _hMute = COMSPEC_ATAK_Th get "hexTextMuted";
private _meTxt = if (_land || {!(_l get "mini")}) then { format ["<t color='%1'>·</t> %2° %3 <t color='%1'>·</t> %4 km/h", _hMute, round getDir player, [getDir player] call _card, round (_mySpd * 3.6)] } else { "" };
_head ctrlSetStructuredText parseText (format ["<t font='EtelkaMonospacePro' size='0.9'>%1</t> %2  %3 %4 %5 %6%7",
    [player, 8] call comspec_atak_native_fnc_gridRef, _meTxt,
    ["DI", _counts select 0, COMSPEC_ATAK_Th get "hexOk"] call _pill, ["AN", _counts select 1, COMSPEC_ATAK_Th get "hexWarn"] call _pill,
    ["PE", _counts select 2, COMSPEC_ATAK_Th get "hexAlert"] call _pill, ["HL", _counts select 3, _hMute] call _pill,
    ["  <t color='" + (COMSPEC_ATAK_Th get "hexAlert") + "'>" + (("PAS DE RÉSEAU") call comspec_atak_native_fnc_tr) + "</t>", ""] select _meOn]); // i18n:brut
_head ctrlSetTooltip (format [("Altitude %1 m · %2 direct · %3 ancienne(s) · %4 perdue(s) · %5 hors ligne%6") call comspec_atak_native_fnc_tr, round (_me select 2), _counts select 0, _counts select 1, _counts select 2, _counts select 3, ["", (" · PAS DE RÉSEAU : pistes figées") call comspec_atak_native_fnc_tr] select !_meOn]);
// À droite de l'en-tête : vue LISTE / ORBAT et tiroir des filtres (nombre de filtres actifs).
private _nFilt = ({ profileNamespace getVariable [_x, false] } count ["COMSPEC_ATAK_HideAllyAi", "COMSPEC_ATAK_BftHideAir", "COMSPEC_ATAK_BftHideVeh", "COMSPEC_ATAK_BftHideLost", "COMSPEC_ATAK_BftHideOffline"])
    + ([0, 1] select ((profileNamespace getVariable ["COMSPEC_ATAK_AllyFilter", "ALL"]) isNotEqualTo "ALL"))
    + ([0, 1] select ((profileNamespace getVariable ["COMSPEC_ATAK_BftMaxDist", 0]) isNotEqualTo 0));
if (!_upd) then {
// Menu de la vue : LISTE (tableau des pistes) ou ORBAT (arbre formation, groupe, équipe), la vue choisie en couleur.
{
    _x params ["_vLbl", "_vKey", "_vX", "_vTip"];
    private _vf = ["COMSPEC_RscText", [_bw - _hbW + _vX, 0, _hbW / 2 - pixelW, _font * 1.25]] call comspec_atak_native_fnc_pageCtrl;
    private _vb = ["COMSPEC_RscButtonFlat", [_bw - _hbW + _vX, 0, _hbW / 2 - pixelW, _font * 1.25], _vLbl] call comspec_atak_native_fnc_pageCtrl;
    _vb ctrlSetFontHeight ((_fs * 0.9) max COMSPEC_ATAK_FontMin);
    _vb ctrlSetTooltip ((_vTip) call comspec_atak_native_fnc_tr);
    _vf ctrlSetBackgroundColor ([COMSPEC_ATAK_Th get "surface2", COMSPEC_ATAK_Th get "accent"] select (_view isEqualTo _vKey));
    _vb ctrlSetTextColor ([COMSPEC_ATAK_Th get "text", COMSPEC_ATAK_Th get "onAccent"] select (_view isEqualTo _vKey));
    _vb setVariable ["comspec_view", _vKey];
    _vb ctrlAddEventHandler ["ButtonClick", {
        params ["_c"];
        profileNamespace setVariable ["COMSPEC_ATAK_BftView", _c getVariable ["comspec_view", "LIST"]];
        [] call comspec_atak_native_fnc_saveProfile;
        (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["bftFiltersOpen", false];
        [{ ['BFT'] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
    }];
} forEach [["LISTE", "LIST", 0, "Tableau des pistes, triées par distance"], ["ORBAT", "ORBAT", _hbW / 2, "Arbre ORBAT : formation, groupe, équipe, membres"]];
// Tiroir des filtres : l'entonnoir au bout de la rangée des puces, avec le nombre de filtres actifs.
private _fbH = _font * 1.4;
private _fbW = _fbH / (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]);
private _fbX = _bw - _fbW - _pad;
private _fbY = _font * 1.35 + _pad / 2;
if (_filtersOpen || {_nFilt > 0}) then {
    private _fbg = ["COMSPEC_RscText", [_fbX, _fbY, _fbW, _fbH]] call comspec_atak_native_fnc_pageCtrl;
    _fbg ctrlSetBackgroundColor ([COMSPEC_ATAK_Th get "surfaceHi", COMSPEC_ATAK_Th get "accent"] select _filtersOpen);
};
if (_nFilt > 0) then {
    private _fbn = ["COMSPEC_RscTextRight", [_fbX, _fbY + _fbH * 0.5, _fbW, _fbH * 0.5], str _nFilt] call comspec_atak_native_fnc_pageCtrl;
    _fbn ctrlSetFontHeight COMSPEC_ATAK_FontMin;
    _fbn ctrlSetTextColor ([COMSPEC_ATAK_Th get "text", COMSPEC_ATAK_Th get "onAccent"] select _filtersOpen);
};
private _fb = ["COMSPEC_RscIconButton", [_fbX, _fbY, _fbW, _fbH], "\z\comspec_atak_native\addons\main\data\ui_filter.paa"] call comspec_atak_native_fnc_pageCtrl;
_fb ctrlSetTextColor ([COMSPEC_ATAK_Th get "text", COMSPEC_ATAK_Th get "onAccent"] select _filtersOpen);
_fb ctrlSetTooltip ((["Masquer IA, aériens, véhicules, pistes anciennes ou hors ligne ; distance max (aussi sur la carte)", format ["FILTRES (%1)", _nFilt]] select (_nFilt > 0)) call comspec_atak_native_fnc_tr);
_fb ctrlAddEventHandler ["ButtonClick", {
    private _st = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
    _st set ["bftFiltersOpen", !(_st getOrDefault ["bftFiltersOpen", false])];
    _st set ["bftFiltersAnim", true];
    [{ ['BFT'] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
}];
};
_y = _font * 1.35 + _pad / 2;

// Filtres
if (!_upd) then {
private _opts = [["TOUS", "ALL"], ["ÉQUIPE", "FT"], ["GROUPE", "GROUP"], ["VÉHICULES", "VEH"], ["PERDUS", "LOST"]];
private _fw = (_bw - _pad * 2 - _font * 1.4 / (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW])) / 5;
{
    _x params ["_t", "_k"];
    private _b = ["COMSPEC_RscButton", [_pad + _forEachIndex * _fw, _y, _fw - _pad / 4, _font * 1.4], _t] call comspec_atak_native_fnc_pageCtrl;
    _b ctrlSetFontHeight ((_fs * 0.9) max COMSPEC_ATAK_FontMin);
    if (_k isEqualTo _filter) then { _b ctrlSetBackgroundColor (COMSPEC_ATAK_Th get "accent"); _b ctrlSetTextColor (COMSPEC_ATAK_Th get "onAccent"); };
    _b ctrlAddEventHandler ["ButtonClick", compile format ["(uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) set ['bftFilter', '%1']; [{ ['BFT'] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;", _k]];
} forEach _opts;
};
_y = _y + _font * 1.4 + _pad / 2;

// Message important de l'ORBAT du site (compte non lié, Athena injoignable…) : sur plusieurs lignes au-dessus du tableau, où
// une cellule le couperait. Touché, il relit l'ORBAT quand c'est possible. Un autre message : rendu complet.
(if (_view isEqualTo "ORBAT") then { ["notice"] call comspec_atak_native_fnc_bftOrbat } else { ["", "muted", false] }) params ["_nTxt", "_nTone", "_nRetry"];
private _noteOn = _view isEqualTo "ORBAT" && {!_filtersOpen} && {_nTone in ["warn", "alert"]};
private _noteTxt = ["", _nTxt] select _noteOn;
if (_upd && {_noteTxt isNotEqualTo (_live getOrDefault ["note", ""])}) exitWith { false };
if (_noteOn) then {
    private _noteH = _fs * 2.5;
    if (!_upd) then {
        private _nc = ["COMSPEC_RscStructuredText", [_pad, _y, ([_bw, _bw * 0.58] select _land) - _pad, _noteH]] call comspec_atak_native_fnc_pageCtrl;
        _nc ctrlSetStructuredText parseText (format ["<t size='0.9' color='%1'>%2</t>", COMSPEC_ATAK_Th get (["hexWarn", "hexAlert"] select (_nTone isEqualTo "alert")), [(_nTxt) call comspec_atak_native_fnc_tr] call _esc]); // i18n:brut
        if (_nRetry) then {
            private _nb = ["COMSPEC_RscButtonOverlay", [_pad, _y, ([_bw, _bw * 0.58] select _land) - _pad, _noteH]] call comspec_atak_native_fnc_pageCtrl;
            _nb ctrlSetTooltip (("Clic : relire l'ORBAT du site") call comspec_atak_native_fnc_tr);
            _nb ctrlAddEventHandler ["ButtonClick", { ["load", true] call comspec_atak_native_fnc_bftOrbat; }];
        };
    };
    _y = _y + _noteH;
};

// Tableau des pistes (à gauche en paysage, en haut en portrait) et fiche de la piste choisie.
private _detailH = [_bh * 0.36, 0] select _land;
private _listW = [_bw, _bw * 0.58] select _land;
private _listH = _bh - _y - _detailH - _pad / 2;
// Colonnes du tableau, en fraction de sa largeur : portrait et paysage ; trois colonnes de niveau dans l'arbre, une seule dans la liste.
private _colPos = if (_view isEqualTo "ORBAT") then { [[0, 0.05, 0.10, 0.15, 0.60, 0.68, 0.88, 0.995], [0, 0.035, 0.07, 0.105, 0.56, 0.63, 0.79, 0.89]] select _land } else { [[0, 0.075, 0.076, 0.077, 0.56, 0.65, 0.85, 0.995], [0, 0.05, 0.051, 0.052, 0.52, 0.59, 0.76, 0.87]] select _land };
if (!_upd) then {
// En-têtes alignés sur les colonnes du tableau (une seule ligne de filtres quand le tiroir est ouvert).
{
    _x params ["_ht", "_hx", "_hw"];
    private _cols = ["COMSPEC_RscText", [_hx, _y, _hw, _fs * 1.2], _ht] call comspec_atak_native_fnc_pageCtrl;
    _cols ctrlSetFontHeight ((_fs * 0.8) max COMSPEC_ATAK_FontMin);
    _cols ctrlSetTextColor (COMSPEC_ATAK_Th get "textMuted");
} forEach (if (_filtersOpen) then { [["FILTRES DU BFT (aussi appliqués à la carte)", _pad, _listW - _pad]] } else {
    [[["INDICATIF", "UNITÉ › POSTE › MEMBRE"] select (_view isEqualTo "ORBAT"), _listW * (_colPos select 3), _listW * ((_colPos select 4) - (_colPos select 3))],
     ["ÉTAT", _listW * (_colPos select 4), _listW * ((_colPos select 5) - (_colPos select 4))],
     ["DIST", _listW * (_colPos select 5), _listW * ((_colPos select 6) - (_colPos select 5))],
     ["AZ", _listW * (_colPos select 6), _listW * ((_colPos select 7) - (_colPos select 6))],
     ["ÂGE", _listW * (_colPos select 7), _listW * (1 - (_colPos select 7))]]
});
};
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
        [] call comspec_atak_native_fnc_saveProfile;
        [] call comspec_atak_native_fnc_localDataRefresh;
        [{ ['BFT'] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
    }];
    private _d = [] call comspec_atak_native_fnc_display;
    private _g = _d ctrlCreate ["COMSPEC_RscControlsGroup", -1, _d displayCtrl 88531];
    private _made = [_g];
    private _mkF = {
        params ["_class", "_pos", ["_text", ""]];
        private _c = _d ctrlCreate [_class, -1, _g];
        _c ctrlSetPosition _pos;
        if (_text isNotEqualTo "") then { _c ctrlSetText ((_text) call comspec_atak_native_fnc_tr); };
        _c ctrlCommit 0;
        _made pushBack _c;
        _c
    };
    private _w = _listW - _pad;
    private _rh = _font * 1.45;
    private _fy = _pad / 2;
    private _bg = ["COMSPEC_RscText", [0, 0, _w, 10]] call _mkF;
    _bg ctrlSetBackgroundColor (COMSPEC_ATAK_Th get "surface");
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
    _c ctrlAddEventHandler ["ButtonClick", { (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["bftFiltersOpen", false]; [{ ['BFT'] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame; }];
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
if (_upd) then { _list = (_live get "ctrls") select 1; } else {
_list = ["COMSPEC_RscListNBox", [0, _y, _listW, _listH]] call comspec_atak_native_fnc_pageCtrl;
// Plafonnée à la hauteur de ligne de la classe (0,040 × safeZoneH), jamais sous le plancher de 14 px.
_list ctrlSetFontHeight ((_font min (safeZoneH * 0.038)) max COMSPEC_ATAK_FontMin);
_list ctrlSetBackgroundColor (COMSPEC_ATAK_Th get "bg1");
_list lnbSetColumnsPos _colPos;
};
// Ligne suivante du tableau : réécrite sur place en mise à jour (sans vider le tableau, le défilement reste), ajoutée sinon.
// Colonnes : 0 à 2 niveau de l'arbre (chevron ou symbole, dans la colonne de sa profondeur), 3 texte, 4 état, 5 distance, 6 azimut, 7 âge.
// Ligne identique au passage précédent (membre hors ligne, poste vacant, en-tête) : aucune commande d'affichage.
private _rowN = 0;
private _sigs = if (_upd) then { uiNamespace getVariable ["COMSPEC_ATAK_BftRowSigs", []] } else { [] };
private _sigOut = [];
private _cRow = COMSPEC_ATAK_Th get "text";
private _cMute = COMSPEC_ATAK_Th get "textMuted";
private _cDim = COMSPEC_ATAK_Th get "textDim";
private _put = {
    params ["_t", ["_right", ""], ["_rgba", _cRow], ["_pic", ""], ["_picRgba", _cRow], ["_tip", ""], ["_depth", 0], ["_stPic", ""], ["_stRgba", _cRow], ["_az", ""], ["_ageT", ""]];
    private _i = _rowN;
    _rowN = _rowN + 1;
    private _sig = [_t, _right, _rgba, _pic, _picRgba, _tip, _depth, _stPic, _stRgba, _az, _ageT];
    _sigOut pushBack _sig;
    private _nRow = (lnbSize _list) select 0;
    if (_i < (count _sigs) && {(_sigs select _i) isEqualTo _sig} && {_i < _nRow}) exitWith { _i };
    if (_i < _nRow) then { _list lnbSetText [[_i, 3], (_t) call comspec_atak_native_fnc_tr]; } else { _i = _list lnbAddRow ["", "", "", (_t) call comspec_atak_native_fnc_tr, "", "", "", ""]; };
    { _list lnbSetPicture [[_i, _x], ""]; } forEach [0, 1, 2];
    if (_pic isNotEqualTo "") then {
        private _pc = _depth min 2;
        _list lnbSetPicture [[_i, _pc], _pic];
        _list lnbSetPictureColor [[_i, _pc], _picRgba];
        _list lnbSetPictureColorSelected [[_i, _pc], _cRow];
    };
    _list lnbSetPicture [[_i, 4], _stPic];
    if (_stPic isNotEqualTo "") then { _list lnbSetPictureColor [[_i, 4], _stRgba]; _list lnbSetPictureColorSelected [[_i, 4], _stRgba]; };
    _list lnbSetColor [[_i, 3], _rgba];
    _list lnbSetTextRight [[_i, 5], (_right) call comspec_atak_native_fnc_tr];
    _list lnbSetText [[_i, 6], _az];
    _list lnbSetText [[_i, 7], _ageT];
    { _list lnbSetColor [[_i, _x], _cDim]; } forEach [5, 6, 7];
    _list lnbSetTooltip [[_i, 3], (_tip) call comspec_atak_native_fnc_tr];
    _i
};
// État d'une ligne : l'image dit l'état par sa forme (coche apte, triangle blessé, croix hors de combat, point hors ligne,
// horloge pour une position ancienne), la teinte le redit.
private _dirI = "\z\comspec_atak_native\addons\main\data\";
// Une ligne de piste (tableau et ORBAT). Params : [piste, profondeur, mention après l'indicatif, libellé de l'ORBAT du site]
private _addMember = {
    params ["_t", ["_depth", 0], ["_extra", ""], ["_orbLabel", ""]];
    _t params ["_dist", "_id", "_e", "_obj", "_pos"];
    private _f = _e getOrDefault ["freshness", "LIVE"];
    private _age = round (diag_tickTime - (_e getOrDefault ["updated", diag_tickTime]));
    ([_e] call comspec_atak_native_fnc_symbology) params ["_icon", "_color"];
    if ((profileNamespace getVariable ["COMSPEC_ATAK_UnitSymbols", "NATO"]) in ["NATO", "MILSTD"]) then { _icon = ([_e] call comspec_atak_native_fnc_natoSymbol) select 0; };
    // État médical masqué (Réglages > Réalisme, fn_medShow) : seul le décès compte.
    (["state", _e] call comspec_atak_native_fnc_unitCard) params ["_sc", "_sLbl", "", "_sRgba"];
    private _dead = _sc >= 2;
    private _old = _sc in [0, -1] && {_f in ["STALE", "LOST"]};
    private _stPic = _dirI + (switch (true) do { case _dead: { "ui_close" }; case (_sc isEqualTo 1): { "ui_warning" }; case (_sc isEqualTo -2): { "ui_dot" }; case _old: { "ui_clock" }; case (_sc isEqualTo 0): { "ui_check" }; default { "ui_dot" }; }) + ".paa";
    private _stRgba = [_sRgba, COMSPEC_ATAK_Th get (["textMuted", "warn"] select (_f isEqualTo "STALE"))] select _old;
    // Batterie faible ou signal nul du téléphone de l'équipier (publié par son téléphone) : après l'indicatif.
    private _phone = "";
    if (!isNull _obj) then {
        (_obj getVariable ["COMSPEC_ATAK_Pub", []]) params [["_bat", -1], ["_bars", -1]];
        if (_bat >= 0 && {_bat <= 15}) then { _phone = format ["  %1 %%", _bat]; };
        if (_bars isEqualTo 0) then { _phone = _phone + "  " + (("sans signal") call comspec_atak_native_fnc_tr); };
    };
    // Équipe de feu (camp ami) : couleur de l'icône et nom de l'équipe après l'indicatif.
    private _ft = if (isNull _obj) then { createHashMap } else { [_obj] call comspec_atak_native_fnc_ftInfo };
    private _ftName = _ft getOrDefault ["name", ""];
    if (_ftName isNotEqualTo "") then { _color = _ft get "rgba"; };
    private _ageTxt = [format ["%1 s", _age], format ["%1 min", floor (_age / 60)]] select (_age >= 60);
    private _i = [
        format ["%1%2%3%4", [_orbLabel, [_id, _e] call _labelOf] select (_orbLabel isEqualTo ""), ["", format ["  · %1%2", _ftName, ["", " " + (_ft get "roleShort")] select ((_ft getOrDefault ["roleShort", ""]) isNotEqualTo "")]] select (_ftName isNotEqualTo ""), _extra, _phone],
        [_dist] call _distTxt,
        switch (true) do { case _dead: { COMSPEC_ATAK_Th get "alert" }; case (_f isEqualTo "LIVE"): { _cRow }; case (_f isEqualTo "STALE"): { COMSPEC_ATAK_Th get "warn" }; default { _cMute }; },
        _icon,
        [_color, _cMute] select (_f in ["LOST", "OFFLINE"]),
        format ["%5 · %1 · %2 · %6 · trajet %3 %4", (_fresh getOrDefault [_f, [_f]]) select 0, mapGridPosition _pos, [_dist, _mySpd max _walk] call _etaTxt, ["à pied", "à ma vitesse"] select (_mySpd > _walk), (_sLbl) call comspec_atak_native_fnc_tr, [player getDir _pos] call _card],
        _depth, _stPic, _stRgba,
        format ["%1°", [(round (player getDir _pos)) mod 360, 3] call CBA_fnc_formatNumber],
        _ageTxt
    ] call _put;
    _ids pushBack _id;
    _rows pushBack ["m", _id];
    if (_firstRow < 0) then { _firstRow = _i; };
    if (_id isEqualTo _selId) then { _selIdx = _i; };
};
// Ligne d'en-tête de l'ORBAT : un chevron (ouvert ou fermé) dans la colonne de sa profondeur, clic : replier / déplier.
// Réglette en texte (fn_squadBar) et effectif à droite, le détail en infobulle.
private _addHead = {
    params ["_key", "_text", "_right", "_rgba", ["_depth", 0], ["_more", ""]];
    private _on = (uiNamespace getVariable ["COMSPEC_ATAK_BftOrbatOpen", createHashMap]) getOrDefault [_key, true];
    [_text, _right, _rgba, _dirI + (["ui_chevron_right", "ui_chevron_down"] select _on) + ".paa", _rgba,
        [(("Clic : replier / déplier") call comspec_atak_native_fnc_tr), format ["%1 · %2", (_more) call comspec_atak_native_fnc_tr, (("Clic : replier / déplier") call comspec_atak_native_fnc_tr)]] select (_more isNotEqualTo ""), _depth] call _put;
    _rows pushBack ["h", _key];
    _ids pushBack "";
};
if (_view isEqualTo "ORBAT") then {
    private _open = uiNamespace getVariable ["COMSPEC_ATAK_BftOrbatOpen", createHashMap];
    uiNamespace setVariable ["COMSPEC_ATAK_BftOrbatOpen", _open];
    // Message de l'ORBAT du site, sans gravité : une ligne ; les autres sont au-dessus du tableau.
    if (!_noteOn) then {
        [_nTxt, "", _cMute, "", _cMute, _nTxt] call _put;
        _rows pushBack ["", ""];
        _ids pushBack "";
    };
    // Réglette d'une liste d'unités, en texte, pour une ligne d'en-tête.
    private _ruler = { params ["_us"]; ["text", ["cells", _us] call comspec_atak_native_fnc_squadBar] call comspec_atak_native_fnc_squadBar };
    private _fold = (_orb get "total") > 60;
    // Une personne : ligne de piste si elle en a une, sinon son état.
    private _addPerson = {
        params ["_key", "_depth", "_prefix"];
        ([_key] call _stOf) params ["_st", "_o", "_t"];
        if !([_st, _o, _t] call _passes) exitWith {};
        private _pe = _oPeople getOrDefault [_key, []];
        private _label = if ((count _pe) > 0) then { _pe select 0 } else { (_strayKey getOrDefault [_key, ["", ""]]) select 1 };
        if (_st isEqualTo "TRACK") exitWith { [_t, _depth, "", _prefix + _label] call _addMember; };
        private _lk = switch (_st) do {
            case "ME": { ["moi", COMSPEC_ATAK_Th get "accentInk", "ui_gps"] };
            case "NOTRACK": { ["en jeu, sans piste", COMSPEC_ATAK_Th get "warn", "ui_warning"] };
            case "ANON": { ["état non communiqué", _cMute, "ui_help"] };
            case "VACANT": { ["poste vacant", _cMute, ""] };
            default { ["hors ligne", _cMute, "ui_dot"] };
        };
        private _word = (_lk select 0) call comspec_atak_native_fnc_tr;
        private _i = [format ["%1%2 · %3", _prefix, _label, _word], "", _lk select 1, "", _cRow, _word, _depth, [_dirI + (_lk select 2) + ".paa", ""] select ((_lk select 2) isEqualTo ""), _lk select 1] call _put;
        _ids pushBack _key;
        _rows pushBack ["p", _key];
        if (_firstRow < 0 && {_st in ["OFFLINE", "NOTRACK"]}) then { _firstRow = _i; };
        if (_selIdx < 0 && {_key isEqualTo _selId}) then { _selIdx = _i; };
    };
    if (_hasOrb) then {
        // Filtre actif : une unité n'apparaît que si elle (ou une sous-unité) a une ligne retenue.
        private _vis = _oUnits apply {
            _filter isEqualTo "ALL" || {((_x select 4) findIf { ([_x select 1] call _stOf) call _passes }) >= 0}
        };
        if (_filter isNotEqualTo "ALL") then {
            private _pend = [false, false, false, false, false, false, false, false, false, false, false, false, false, false, false, false];
            for "_i" from ((count _oUnits) - 1) to 0 step -1 do {
                private _d = (_oUnits select _i) select 1;
                private _own = _vis select _i;
                for "_k" from (_d + 1) to 15 do { if (_pend select _k) then { _own = true; _pend set [_k, false]; }; };
                if (_own) then { _vis set [_i, true]; _pend set [_d, true]; };
            };
        };
        private _skip = -1;
        {
            _x params ["_uId", "_d", "_uName", "_uCode", "_uRows", "_uRestricted"];
            if (_skip >= 0 && {_d > _skip}) then { continue };
            _skip = -1;
            if !(_vis select _forEachIndex) then { _skip = _d; continue };
            private _uk = format ["u:%1", _uId];
            // Grosse communauté : seul le premier niveau est ouvert au départ.
            // La valeur de départ est notée : le premier clic sur l'en-tête inverse bien ce qui est affiché.
            if !(_uk in _open) then { _open set [_uk, !_fold || {_d < 1}]; };
            private _isOpen = _open get _uk;
            private _on = 0;
            private _tot = 0;
            private _vac = 0;
            {
                if ((_x select 0) isEqualTo "v") then { _vac = _vac + (_x select 3); } else {
                    if (((_oPeople getOrDefault [_x select 1, [0, 0, 0, 0, 0]]) select 4) isEqualTo 0) then { _tot = _tot + 1; if ((_x select 1) in _onKey) then { _on = _on + 1; }; };
                };
            } forEach _uRows;
            private _inGame = (_uRows select { (_x select 0) isNotEqualTo "v" }) apply { _onKey getOrDefault [_x select 1, objNull] };
            _inGame = _inGame select { !isNull _x };
            [_uk, format ["%1%2%3%4", _uName, ["", format ["  (%1)", _uCode]] select (_uCode isNotEqualTo ""), ["", "  · restreinte"] select _uRestricted, ["", "  " + ([_inGame] call _ruler)] select ((count _inGame) > 0)],
                format ["%1/%2", _on, _tot], COMSPEC_ATAK_Th get (["text", "info"] select (_d isEqualTo 0)), _d min 2,
                format ["%1 / %2 en jeu%3", _on, _tot, ["", format [" · %1 vacant(s)", _vac]] select (_vac > 0)]] call _addHead;
            if (!_isOpen) then { _skip = _d; continue };
            {
                [_x select 1, _d + 1, ["", (_x select 2) + " : "] select ((_x select 2) isNotEqualTo "" && {(_x select 0) isEqualTo "p"})] call _addPerson;
            } forEach _uRows;
        } forEach _oUnits;
    };
    // Tout ce qui n'est pas dans l'ORBAT du site : pistes (IA comprises) et joueurs de mon camp sans piste.
    private _others = if (_hasOrb) then { _tracks select { !((_x select 1) in _trackKey) } } else { _tracks };
    private _strayShown = (keys _strayKey) select { ([_x] call _stOf) call _passes };
    private _outOpen = true;
    if (_hasOrb && {(count _others) > 0 || {(count _strayShown) > 0}}) then {
        _outOpen = _open getOrDefault ["o:hors", true];
        ["o:hors", "HORS ORBAT DU SITE", str ((count _others) + (count _strayShown)), COMSPEC_ATAK_Th get "info", 0, format ["%1 joueur(s) ou piste(s)", (count _others) + (count _strayShown)]] call _addHead;
    };
    if (!_outOpen) then { _others = []; _strayShown = []; };
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
    } forEach _others;
    private _formNames = keys _forms;
    _formNames sort true;
    {
        private _form = _x;
        private _groups = _forms get _form;
        private _fk = "f:" + _form;
        private _nf = 0;
        { _nf = _nf + count ((_groups get _x) select 2); } forEach (keys _groups);
        [_fk, _form, str _nf, COMSPEC_ATAK_Th get "info", 0, format ["%1 piste(s)", _nf]] call _addHead;
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
                // Effectif : aptes sur total, la réglette du groupe en texte après son nom ; le chef en infobulle.
                private _gCells = if (isNull _grp) then { [] } else { ["cells", _grp] call comspec_atak_native_fnc_squadBar };
                private _gCnt = if (isNull _grp) then { [_n, _n] } else { ["count", _gCells] call comspec_atak_native_fnc_squadBar };
                [_gKey2, format ["%1  ·  %2%3%4", _gName, _ech, ["", "  (mon groupe)"] select (_grp isEqualTo group player), ["", "  " + (["text", _gCells] call comspec_atak_native_fnc_squadBar)] select ((count _gCells) > 0)],
                    format ["%1/%2", _gCnt select 0, _gCnt select 1], _cRow, 1,
                    format ["%1 membre(s)%2", _n, ["", "  ·  " + _ldrTxt] select (_ldrTxt isNotEqualTo "")]] call _addHead;
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
                        [_tk, _tName, format ["%1", count _tMem], _tRgba, 2] call _addHead;
                        if (_open getOrDefault [_tk, true]) then { { [_x, 2, [_x] call _tag] call _addMember; } forEach _tMem; };
                    } forEach _tIds;
                    { [_x, 2, [_x] call _tag] call _addMember; } forEach _free;
                };
            } forEach _gKeys;
        };
    } forEach _formNames;
    { [_x, 1, ""] call _addPerson; } forEach _strayShown;
} else {
    { [_x] call _addMember; } forEach _tracks;
};
// Rien à montrer : pas la ligne vide de la capture, un message. Dans TOUS, un ORBAT sans membre garde ses unités.
if (_firstRow < 0 && {!(_hasOrb && {_filter isEqualTo "ALL"} && {_view isEqualTo "ORBAT"})}) then {
    [(["Aucune piste amie suivie.", "Aucune piste pour ce filtre."] select (_filter isNotEqualTo "ALL" || {_nFilt > 0})), "", _cMute] call _put;
    _rows pushBack ["", ""];
    _ids pushBack "";
};
while { ((lnbSize _list) select 0) > _rowN } do { _list lnbDeleteRow (((lnbSize _list) select 0) - 1); };
uiNamespace setVariable ["COMSPEC_ATAK_BftRowSigs", _sigOut];
uiNamespace setVariable ["COMSPEC_ATAK_BftIds", _ids];
uiNamespace setVariable ["COMSPEC_ATAK_BftRows", _rows];
// Piste choisie absente (filtrée) : la première de la liste ; repliée dans l'ORBAT : elle reste choisie, comme une personne de l'arbre.
private _selKept = _view isEqualTo "ORBAT" && {_selId in _oPeople || {_selId in _strayKey}} && {([_selId] call _stOf) call _passes};
if (_selIdx < 0 && {_firstRow >= 0} && {!_selKept} && {(_tracks findIf { (_x select 1) isEqualTo _selId }) < 0}) then { _selIdx = _firstRow; _selId = _ids select _firstRow; _s set ["bftSel", _selId]; };
// Replier un en-tête ne prend pas la sélection : elle revient à la piste choisie.
if (_selIdx >= 0 && {(lnbCurSelRow _list) isNotEqualTo _selIdx}) then { _list lnbSetCurSelRow _selIdx; };
if (!_upd) then {
// Défilement du tableau retrouvé après un rendu complet de la même page (clic, sélection) : avant, retour en haut.
// Ancien tableau (masqué par fn_pageClear, supprimé à l'image suivante) : sa position est relue.
private _oldList = (_live getOrDefault ["ctrls", []]) param [1, controlNull];
if (!isNull _oldList && {(_live getOrDefault ["drawnSeq", -1]) isEqualTo ((uiNamespace getVariable ["COMSPEC_ATAK_RenderSeq", 0]) - 1)}) then {
    private _lsv = ctrlScrollValues _oldList;
    if ((_lsv select 0) > 0) then { _list ctrlSetScrollValues [_lsv select 0, -1]; };
};
_list ctrlAddEventHandler ["LBSelChanged", {
    params ["", "_i"];
    ((uiNamespace getVariable ["COMSPEC_ATAK_BftRows", []]) param [_i, []]) params [["_k", ""], ["_v", ""]];
    private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
    // En-tête ORBAT : replier / déplier (mémorisé).
    if (_k isEqualTo "h") exitWith {
        private _open = uiNamespace getVariable ["COMSPEC_ATAK_BftOrbatOpen", createHashMap];
        _open set [_v, !(_open getOrDefault [_v, true])];
        uiNamespace setVariable ["COMSPEC_ATAK_BftOrbatOpen", _open];
        [{ ['BFT'] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
    };
    if !(_k in ["m", "p"]) exitWith {};
    if (_v isEqualTo (_s getOrDefault ["bftSel", ""])) exitWith {};
    _s set ["bftSel", _v];
    [{ ['BFT'] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
}];
_list ctrlAddEventHandler ["LBDblClick", {
    params ["", "_i"];
    if ((((uiNamespace getVariable ["COMSPEC_ATAK_BftRows", []]) param [_i, []]) param [0, ""]) isNotEqualTo "m") exitWith {};
    [{ ["center"] call comspec_atak_native_fnc_bftAction; }] call comspec_atak_native_fnc_nextFrame;
}];
};
};

// Fiche de la piste choisie.
private _dRect = if (_land) then { [_listW + _pad / 2, _y - _fs * 1.2, _bw - _listW - _pad / 2, _bh - _y + _fs * 1.2] } else { [0, _y + _listH + _pad / 2, _bw, _detailH] };
_dRect params ["_dx", "_dy", "_dw", "_dh"];
private _sel = (_tracks select { (_x select 1) isEqualTo _selId }) param [0, []];
// Personne de l'ORBAT sans piste (hors ligne, en jeu sans piste, moi, poste vacant, identité protégée).
private _pSt = "";
private _pObj = objNull;
if ((count _sel) isEqualTo 0 && {_view isEqualTo "ORBAT"} && {_selId in _oPeople || {_selId in _strayKey}}) then {
    ([_selId] call _stOf) params ["_ps", "_po"];
    _pSt = _ps;
    _pObj = _po;
};
private _buzzOn = (_selId isNotEqualTo "") && {(_s getOrDefault ["bftBuzz", ""]) isEqualTo _selId} && {(count _sel) > 0 || {_pSt isEqualTo "NOTRACK"}};
// Vibreur ouvert sur une personne partie ou une autre ligne choisie : refermé (sinon plus de mise à jour sur place).
if (!_buzzOn && {(_s getOrDefault ["bftBuzz", ""]) isNotEqualTo ""}) then { _s set ["bftBuzz", ""]; };
// Rangée d'actions de la fiche unique (fn_unitCard) : boutons à la hauteur tactile, le surplus derrière « … ».
private _barH = (_l get "tap") + _pad / 2;
private _det = if (_upd) then { (_live get "ctrls") select 2 } else {
    private _c = ["COMSPEC_RscCard", [_dx, _dy, _dw, _dh - _barH], ""] call comspec_atak_native_fnc_pageCtrl;
    _c ctrlSetBackgroundColor (COMSPEC_ATAK_Th get "surface");
    _c
};
private _hxMute = COMSPEC_ATAK_Th get "hexTextMuted";
if ((count _sel) isEqualTo 0) then {
    if (_pSt isEqualTo "") then {
        _det ctrlSetStructuredText parseText (("<t color='" + _hxMute + "'>" + (["Choisissez une piste dans le tableau.", "Choisissez un membre dans l'arbre."] select (_view isEqualTo "ORBAT")) + "</t>") call comspec_atak_native_fnc_tr);
    } else {
        private _pe = _oPeople getOrDefault [_selId, []];
        private _label = if ((count _pe) > 0) then { _pe select 0 } else { (_strayKey getOrDefault [_selId, ["", ""]]) select 1 };
        (switch (_pSt) do {
            case "ME": { ["MOI", COMSPEC_ATAK_Th get "hexAccent", "Votre position est dans le bandeau."] };
            case "NOTRACK": { ["EN JEU, SANS PISTE", COMSPEC_ATAK_Th get "hexWarn", "Présent sur le serveur, sans téléphone ATAK ou piste masquée par un filtre : position inconnue."] };
            case "ANON": { ["IDENTITÉ PROTÉGÉE", _hxMute, "Fiche confidentielle : sa présence en jeu n'est pas communiquée."] };
            case "VACANT": { ["POSTE VACANT", _hxMute, "Aucun titulaire sur ce poste."] };
            default { ["HORS LIGNE", _hxMute, "Absent du serveur : aucune position, ni message ni vibreur possibles."] };
        }) params ["_stTxt", "_stHex", "_stHelp"];
        private _lines = (_pe param [5, []]) apply {
            format ["<t color='" + _hxMute + "'>Unité</t> %1%2", [_x select 0] call _esc, ["", format ["  ·  <t color='" + _hxMute + "'>Poste</t> %1", [_x select 1] call _esc]] select ((_x select 1) isNotEqualTo "" && {_pSt isNotEqualTo "VACANT"})]
        };
        _det ctrlSetStructuredText parseText ((format [
            "<t size='1.15' font='COMSPEC_BarlowBold'>%1</t>  <t color='%2'>● %3</t><br/>%4<t color='" + _hxMute + "'>%5</t>",
            [_label] call _esc, _stHex, _stTxt, (_lines select [0, 6]) apply { _x + "<br/>" } joinString "", _stHelp]) call comspec_atak_native_fnc_tr);
    };
} else {
    _sel params ["_dist", "_id", "_e", "_obj", "_pos"];
    private _f = _e getOrDefault ["freshness", "LIVE"];
    (_fresh getOrDefault [_f, [_f, (COMSPEC_ATAK_Th get "hexTextMuted")]]) params ["_ft", "_fc"];
    private _type = switch (_e getOrDefault ["type", "infantry"]) do { case "air": { "Aérien" }; case "armor": { "Blindé" }; case "vehicle": { "Véhicule" }; default { "Infanterie" }; };
    private _isLive = _f isEqualTo "LIVE";
    private _speed = if (isNull _obj || {!_isLive}) then { "—" } else { format ["%1 km/h", round (vectorMagnitude velocity vehicle _obj * 3.6)] };
    private _age = round (diag_tickTime - (_e getOrDefault ["updated", diag_tickTime]));
    private _ageTxt = [format ["%1 s", _age], format ["%1 min", floor (_age / 60)]] select (_age >= 60);
    // Trajet : à pied, à ma vitesse, et rapprochement mutuel (vitesse relative le long de l'axe).
    private _eta = format ["<t color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>Trajet</t> %1 à pied", [_dist, _walk] call _etaTxt];
    if (_mySpd > _walk) then { _eta = _eta + format [" · %1 à ma vitesse", [_dist, _mySpd] call _etaTxt]; };
    private _close = "";
    if (_isLive && {!isNull _obj} && {_dist > 5}) then {
        private _ax = vectorNormalized ((getPosASL _obj) vectorDiff (getPosASL player));
        private _vc = ((velocity vehicle player) vectorDiff (velocity vehicle _obj)) vectorDotProduct _ax;
        _close = switch (true) do {
            case (_vc > 0.4): { format ["<br/><t color='" + (COMSPEC_ATAK_Th get "hexOk") + "'>Se rapproche</t> à %1 km/h · jonction dans %2", round (_vc * 3.6), [_dist, _vc] call _etaTxt] };
            case (_vc < -0.4): { format ["<br/><t color='" + (COMSPEC_ATAK_Th get "hexWarn") + "'>S'éloigne</t> à %1 km/h", round (abs _vc * 3.6)] };
            default { "<br/><t color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>Distance stable</t>" };
        };
    };
    // Itinéraire GPS en cours vers cette piste.
    private _rt = missionNamespace getVariable ["COMSPEC_ATAK_Route", createHashMap];
    if ((count _rt) > 0 && {(_rt getOrDefault ["label", ""]) isEqualTo (_e getOrDefault ["callsign", _id])}) then {
        // Reste calculé sur le dernier point suivi par le guidage (sans relancer le guidage lui-même).
        private _ri = _rt getOrDefault ["idx", 0];
        private _left = ((_rt get "total") - (((_rt get "cum") select _ri) + (((_rt get "pts") select _ri) distance2D (_rt getOrDefault ["proj", (_rt get "pts") select _ri])))) max 0;
        _close = _close + format ["<br/><t color='" + (COMSPEC_ATAK_Th get "hexInfo") + "'>GPS</t> %1 par la route · arrivée dans %2", [_left] call _distTxt, [_left, _mySpd max _walk] call _etaTxt];
    };
    private _offTxt = switch (true) do {
        case (_f isEqualTo "OFFLINE"): { format ["<br/><t color='" + (COMSPEC_ATAK_Th get "hexAlert") + "'>Hors ligne</t> <t color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>(téléphone éteint, cassé ou sans signal) · dernière position il y a %1</t>", _ageTxt] };
        case (!_isLive): { format ["<br/><t color='" + (COMSPEC_ATAK_Th get "hexWarn") + "'>Dernière position il y a %1</t>", _ageTxt] };
        default { "" };
    };
    private _hs = ["state", _e] call comspec_atak_native_fnc_unitCard;
    private _health = format ["<t color='%1'>%2</t>", _hs select 2, _hs select 1];
    private _grp = if (isNull _obj) then { "Athena" } else { groupId group _obj };
    private _ftI = if (isNull _obj) then { createHashMap } else { [_obj] call comspec_atak_native_fnc_ftInfo };
    private _ftTxt = switch (true) do {
        case ((_ftI getOrDefault ["name", ""]) isNotEqualTo ""): { format ["<br/><t color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>Équipe</t> <t color='%1' font='COMSPEC_BarlowBold'>● %2</t>%3", _ftI get "hex", [_ftI get "name"] call _esc, ["", format [" · %1", _ftI get "roleLabel"]] select ((_ftI get "roleLabel") isNotEqualTo "")] };
        case ((_ftI getOrDefault ["roleLabel", ""]) isNotEqualTo ""): { format ["<br/><t color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>Rôle</t> %1", _ftI get "roleLabel"] };
        default { "" };
    };
    // Fiche de l'ORBAT du site du joueur de cette piste.
    private _trkKey = _trackKey getOrDefault [_id, ""];
    if (_trkKey isNotEqualTo "") then {
        private _pe = _oPeople getOrDefault [_trkKey, []];
        _ftTxt = format ["<br/><t color='" + _hxMute + "'>ORBAT</t> %1", [_pe param [0, ""]] call _esc] + (((_pe param [5, []]) select [0, 3]) apply {
            format ["<br/><t color='" + _hxMute + "'>Unité</t> %1%2", [_x select 0] call _esc, ["", format ["  ·  <t color='" + _hxMute + "'>Poste</t> %1", [_x select 1] call _esc]] select ((_x select 1) isNotEqualTo "")]
        } joinString "") + _ftTxt;
    };
    private _crew = if (!isNull _obj && {vehicle _obj isNotEqualTo _obj}) then { format ["<br/><t color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>Dans</t> %1", getText (configOf vehicle _obj >> "displayName")] } else { "" };
    _det ctrlSetStructuredText parseText ((format [
        "<t size='1.15' font='COMSPEC_BarlowBold'>%1</t>  <t size='0.8' color='%2'>● %3</t><br/><t color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>%4 · %5</t>%6%18<br/><t font='EtelkaMonospacePro'>%7</t><br/>%8 · %9° %10<br/>%15%16<br/><t color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>Vitesse</t> %11  <t color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>Cap</t> %12°  <t color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>Alt</t> %13 m<br/><t color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>État</t> %14%17",
        [[_id, _e] call _labelOf] call _esc, _fc, _ft, _type, [_grp] call _esc, _crew,
        [_pos, 8] call comspec_atak_native_fnc_gridRef,
        [format ["%1 m", round _dist], format ["%1 km", (_dist / 1000) toFixed 2]] select (_dist >= 1000), round (player getDir _pos), [player getDir _pos] call _card,
        _speed, round (_e getOrDefault ["heading", 0]), round (_pos select 2), _health, _eta, _close, _offTxt, _ftTxt]) call comspec_atak_native_fnc_tr);
};
// Vibreur : composition du message (alerte à l'écran de son téléphone, avec mon texte) à la place de la fiche.
if (_buzzOn) then {
    private _who = if ((count _sel) > 0) then { [[(_sel select 1), (_sel select 2)] call _labelOf] call _esc } else { [((_oPeople getOrDefault [_selId, []]) param [0, (_strayKey getOrDefault [_selId, ["", ""]]) select 1])] call _esc };
    _det ctrlSetStructuredText parseText ((format ["<t size='1.1' font='COMSPEC_BarlowBold'>VIBREUR · %1</t><br/><t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>Son téléphone vibre et affiche une alerte avec votre message, même rangé. Message rapide, ou texte libre puis ENVOYER.</t>", _who]) call comspec_atak_native_fnc_tr);
    private _presets = ["RALLIEMENT", "CONTACT", "STOP", "BESOIN D'AIDE", "APPELEZ-MOI", "RAS"];
    private _pw = (_dw - _pad * 4) / 3;
    private _py = _dy + _font * 2.6;
    {
        private _b = ["COMSPEC_RscButton", [_dx + (_forEachIndex mod 3) * (_pw + _pad * 2), _py + (floor (_forEachIndex / 3)) * (_font * 1.5), _pw, _font * 1.35], _x] call comspec_atak_native_fnc_pageCtrl;
        _b ctrlSetFontHeight _fs;
        _b ctrlAddEventHandler ["ButtonClick", compile format ["[{ ['buzzSend', %1] call comspec_atak_native_fnc_bftAction; }] call comspec_atak_native_fnc_nextFrame;", str _x]];
    } forEach _presets;
    private _ed = ["COMSPEC_RscEdit", [_dx, _py + _font * 3.2, _dw, _font * 1.4], uiNamespace getVariable ["COMSPEC_ATAK_BuzzDraft", ""]] call comspec_atak_native_fnc_pageCtrl;
    _ed ctrlSetFontHeight _fs;
    _ed ctrlSetTooltip (("Texte libre (80 caractères)") call comspec_atak_native_fnc_tr);
    uiNamespace setVariable ["COMSPEC_ATAK_BuzzEdit", _ed];
};
// Cible de la fiche : la piste choisie. Personne de l'ORBAT en jeu sans piste : SMS et vibreur seulement (position inconnue) ;
// hors ligne : HORS LIGNE à la place de VIBRER. Sinon les actions restent à leur place, grisées.
private _unit = [createHashMap, _sel param [2, createHashMap]] select ((count _sel) > 0);
private _uCtx = "bft";
if (_pSt isEqualTo "NOTRACK") then { _unit = ["resolve", _pObj] call comspec_atak_native_fnc_unitCard; _uCtx = "bftp"; };
if (_pSt isEqualTo "OFFLINE") then { _unit = createHashMapFromArray [["freshness", "OFFLINE"]]; };
// Mise à jour sur place : ensemble d'actions changé (commandement passé) : rendu complet.
if (_upd && {!(["barUpdate", [_live get "bar", _unit, _uCtx]] call comspec_atak_native_fnc_unitCard)}) exitWith { false };
private _bar = createHashMap;
if (!_upd) then {
    private _barY = _dy + _dh - _barH + _pad / 2;
    if (_buzzOn) then {
        // Composition du vibreur : ANNULER et ENVOYER à la place de la rangée d'actions.
        private _bw3 = (_dw - _pad) / 2;
        {
            _x params ["_t", "_act", "_on"];
            private _b = [["COMSPEC_RscButton", "COMSPEC_RscButtonPrimary"] select (_forEachIndex isEqualTo 1), [_dx + _forEachIndex * (_bw3 + _pad), _barY, _bw3, _l get "tap"], _t] call comspec_atak_native_fnc_pageCtrl;
            _b ctrlSetFontHeight _fs;
            _b ctrlEnable _on;
            _b ctrlAddEventHandler ["ButtonClick", compile format ["[{ ['%1'] call comspec_atak_native_fnc_bftAction; }] call comspec_atak_native_fnc_nextFrame;", _act]];
        } forEach [["ANNULER", "buzzCancel", true], ["ENVOYER", "buzzSend", _meOn]];
    } else {
        _bar = ["bar", [_unit, _uCtx, _dx, _barY, _dw, true]] call comspec_atak_native_fnc_unitCard;
    };
    // Contrôles gardés pour la mise à jour sur place (tableau seulement, hors tiroir des filtres et vibreur).
    uiNamespace setVariable ["COMSPEC_ATAK_BftLive", [createHashMap, createHashMapFromArray [
        ["key", [_view, _filter, _land, _bw, _bh]], ["ctrls", [_head, _list, _det]], ["bar", _bar], ["note", _noteTxt], ["drawnSeq", uiNamespace getVariable ["COMSPEC_ATAK_RenderSeq", 0]]
    ]] select (!_filtersOpen && {!_buzzOn} && {!isNull _list})];
};
true
