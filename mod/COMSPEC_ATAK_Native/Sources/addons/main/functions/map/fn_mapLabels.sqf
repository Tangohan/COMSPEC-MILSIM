/*
    Cartouches des unités et des contacts sur la carte, façon ATAK : boîte sombre décalée du symbole, reliée
    par un trait fin.
      ligne 1 : indicatif en capitales, police condensée grasse (« STAR 4-1 », « ENI INFANTERIE 1 »), coupé au-delà
                de 16 à 18 caractères ; « +3 » quand d'autres unités sont regroupées sous le même cartouche ;
      ligne 2 : plus petite, chiffres à chasse fixe, en gris : distance et azimut depuis moi (« 48 m · 234° »,
                « 1,2 km · 034° ») ; une piste figée ou perdue s'estompe (alpha, fn_mapOnDraw), sans mention ; pour un contact ENI,
                heure de pose et auteur (« 15:01 · signalé TA1 », boîte bordée de rouge).
    Couleurs : table de la carte (fn_mapColors) ; fond à l'opacité choisie dans le volet CONFIGURATION.
    Tout est dessiné par la carte (drawRectangle, drawLine, drawIcon) : aucun contrôle créé ni déplacé à chaque image.
    Désencombrement :
      priorité   sélection > mon groupe et alliés à moins de 400 m > contacts ENI > autres alliés, du plus proche
                 au plus loin ; ces cartouches passent avant les noms des repères et des lieux (fn_mapLblGrid) ;
      regroupement  symboles à moins d'un cartouche l'un de l'autre à l'écran (même camp) : un seul cartouche « +N » ;
      place      4 positions essayées autour du symbole, jamais sur mon symbole, la sélection ni le bandeau, jamais
                 hors de la carte, puis abandon ; nombre plafonné ; ligne 2 seulement en zoom rapproché.
    Économie : distance, azimut et largeur de l'indicatif recalculés au plus deux fois par seconde par unité (cache
    COMSPEC_ATAK_LblCache) ; liste des contacts ENI refaite quand le magasin des marqueurs change.
    Params : [carte, candidats [[priorité, id, position, indicatif, hostile, alpha, fraîcheur]...], taille des textes,
              échelle de la carte, style ("FULL" complet | "CALLSIGN" indicatif seul), taille de mon symbole (px)]
*/
params ["_map", "_cands", "_ts", "_scale", ["_style", "FULL"], ["_symPx", 21]];
private _now = diag_tickTime;
private _two = _style isEqualTo "FULL" && {_scale < 0.1};
private _pal = [] call comspec_atak_native_fnc_mapColors;
private _cache = uiNamespace getVariable "COMSPEC_ATAK_LblCache";
if (isNil "_cache" || {(count _cache) > 2000}) then { _cache = createHashMap; uiNamespace setVariable ["COMSPEC_ATAK_LblCache", _cache]; };
private _fB = [COMSPEC_ATAK_FontSet select 1, "EtelkaMonospaceProBold"] select (_pal get "tac");

// Contacts ENI : marqueurs hostiles (classe o_*) ou titrés « ENI … » (palette du téléphone), liste refaite quand le
// magasin des marqueurs change (au plus une fois par seconde, toutes les 15 s par sécurité) ; avant : chaque seconde,
// tous les marqueurs relus même sans aucun changement. Masqués dans le volet CONFIGURATION : liste ignorée.
private _eniOn = profileNamespace getVariable ["COMSPEC_ATAK_LblEni", true];
private _eni = missionNamespace getVariable ["COMSPEC_ATAK_EniList", [-1, [], -1, 0, ""]];
private _eRev = ((uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap]) getOrDefault ["revisions", createHashMap]) getOrDefault ["markers", -1];
private _eLang = missionNamespace getVariable ["COMSPEC_ATAK_Lang", "en"];
if (_eniOn && {_now > (_eni select 0)} && {(_eni param [2, -1]) isNotEqualTo _eRev || {_now > (_eni param [3, 0])} || {(_eni param [4, ""]) isNotEqualTo _eLang}}) then {
    private _meta = missionNamespace getVariable "COMSPEC_ATAK_MarkerMeta";
    if (isNil "_meta") then { _meta = createHashMap; missionNamespace setVariable ["COMSPEC_ATAK_MarkerMeta", _meta]; };
    private _list = [];
    {
        private _key = _x;
        private _m = _y;
        private _txt = [_m getOrDefault ["text", ""]] call comspec_atak_native_fnc_markerAutoName;
        if !(((toLower (_m getOrDefault ["type", ""])) select [0, 2]) isEqualTo "o_" || {(toUpper (_txt select [0, 4])) in ["ENI ", "ENY "]}) then { continue };
        if ((toUpper (_m getOrDefault ["shape", "ICON"])) isNotEqualTo "ICON") then { continue };
        private _pos = _m getOrDefault ["position", []];
        if ((count _pos) < 2) then { continue };
        // Heure et auteur : notés à la création (XEH_postInitClient, MarkerCreated) ; sinon (arrivé avant moi,
        // marqueur Athena) heure de première vue et auteur retrouvé une fois pour toutes.
        private _mt = _meta getOrDefault [_key, []];
        if ((count _mt) < 2) then {
            private _who = "";
            if ((_key select [0, 15]) isEqualTo "_USER_DEFINED #") then {
                private _oid = parseNumber ((((_key select [15]) splitString "/") param [0, ""]));
                private _u = allUsers findIf { ((getUserInfo _x) param [1, -1]) isEqualTo _oid };
                if (_u >= 0) then {
                    private _info = getUserInfo (allUsers select _u);
                    private _o = _info param [10, objNull];
                    _who = if (_o isEqualType objNull && {!isNull _o}) then { [_o] call comspec_atak_native_fnc_unitCallsign } else { _info param [3, ""] };
                };
            } else {
                private _src = _m getOrDefault ["source", ""];
                if (_src isNotEqualTo "") then { _who = [_src, "Athena"] select (_src isEqualTo "athena"); };
            };
            _mt = [(([dayTime, "HH:MM:SS"] call BIS_fnc_timeToString) splitString ":") joinString "", _who];
            _meta set [_key, _mt];
        };
        _mt params ["_hms", "_who"];
        // Heure affichée « 15:01 » (avant : « 150179 », heure, minutes et secondes collées, illisible).
        private _hm = [_hms, format ["%1:%2", _hms select [0, 2], _hms select [2, 2]]] select ((count _hms) >= 4);
        private _title = ["cut", toUpper ([_txt, ["ENY CONTACT", "ENI CONTACT"] select (_eLang isEqualTo "fr")] select (_txt isEqualTo "")), 18] call comspec_atak_native_fnc_mapFmt;
        // Âge du contact (heure du jeu) : « 15:01 · 4 min · signalé TA1 » ; la liste est refaite au plus toutes les 15 s.
        private _ageTxt = "";
        if ((count _hms) >= 6) then {
            _ageTxt = ["ageShort", ((dayTime * 3600 - ((parseNumber (_hms select [0, 2])) * 3600 + (parseNumber (_hms select [2, 2])) * 60 + parseNumber (_hms select [4, 2]))) + 86400) mod 86400] call comspec_atak_native_fnc_contactStyle;
        };
        private _hmAge = [_hm, _hm + " · " + _ageTxt] select (_ageTxt isNotEqualTo "");
        _list pushBack [_key, [_pos select 0, _pos select 1, 0], _title, [_hmAge, format [("%1 · signalé %2") call comspec_atak_native_fnc_tr, _hmAge, toUpper _who]] select (_who isNotEqualTo "")];
    } forEach ((uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap]) getOrDefault ["markers", createHashMap]);
    _eni = [_now + 1, _list, _eRev, _now + 15, _eLang];
    missionNamespace setVariable ["COMSPEC_ATAK_EniList", _eni];
};

// Géométrie de la carte : passage écran -> monde une fois par image.
(ctrlPosition _map) params ["_mx", "_my", "_mw", "_mh"];
private _ratio = (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]);
private _o0 = _map ctrlMapScreenToWorld [_mx, _my];
private _mpx = (((_map ctrlMapScreenToWorld [_mx + 0.1, _my]) select 0) - (_o0 select 0)) / 0.1;
private _mpy = ((_o0 select 1) - ((_map ctrlMapScreenToWorld [_mx, _my + 0.1]) select 1)) / 0.1;
if (_mpx <= 0 || {_mpy <= 0}) exitWith { uiNamespace setVariable ["COMSPEC_ATAK_LblRectsU", []]; };
private _fs1 = 0.025 * _ts;
private _fs2 = _fs1 * 0.82;
// Ligne 2 à chasse fixe : largeur = nombre de caractères. Ligne 1 condensée : mesurée (fn_mapTextW) à la taille de
// référence 0,025 puis mise à l'échelle (cache stable quand la taille suit le zoom).
private _cw2 = _fs2 * 0.58 / _ratio;
private _k1 = _fs1 / 0.025;
private _padX = _fs1 * 0.42 / _ratio;
private _padY = _fs1 * 0.2;
private _ox = _fs1 * 1.1 / _ratio;
private _oy = _fs1 * 0.9;

// Candidats visibles, triés par priorité puis du plus proche au plus loin.
private _me = [] call comspec_atak_native_fnc_gpsPos;
private _vis = [];
{
    _x params ["_prio", "_id", "_pos", "_cs", ["_hostile", false], ["_a", 1], ["_fresh", "LIVE"]];
    private _sp = _map ctrlMapWorldToScreen _pos;
    if ((_sp select 0) < _mx || {(_sp select 0) > (_mx + _mw)} || {(_sp select 1) < _my} || {(_sp select 1) > (_my + _mh)}) then { continue };
    private _c = _cache getOrDefault [_id, []];
    if ((count _c) < 5 || {_now > (_c select 0)} || {(_c select 3) isNotEqualTo _cs}) then {
        private _d = _me distance2D _pos;
        _c = [_now + 0.5, _d, ["dist", _d, _me getDir _pos] call comspec_atak_native_fnc_mapFmt, _cs, [_cs, _fB, 0.025] call comspec_atak_native_fnc_mapTextW];
        _cache set [_id, _c];
    };
    // Allié proche (moins de 400 m) : rangé avec mon groupe, devant les contacts ENI.
    if (_prio isEqualTo 3 && {(_c select 1) < 400}) then { _prio = 1; };
    private _l2 = _c select 2;
    _vis pushBack [_prio * 1e6 + (_c select 1), _id, _pos, _sp, _cs, _l2, _hostile, _a, _c select 4];
} forEach _cands;
if (_eniOn) then {
    {
        _x params ["_id", "_pos", "_title", "_line2"];
        private _sp = _map ctrlMapWorldToScreen _pos;
        if ((_sp select 0) < _mx || {(_sp select 0) > (_mx + _mw)} || {(_sp select 1) < _my} || {(_sp select 1) > (_my + _mh)}) then { continue };
        private _c = _cache getOrDefault [_id, []];
        if ((count _c) < 3 || {_now > (_c select 0)}) then { _c = [_now + 0.5, (_me distance2D _pos) min 999999, [_title, _fB, 0.025] call comspec_atak_native_fnc_mapTextW]; _cache set [_id, _c]; };
        _vis pushBack [2e6 + (_c select 1), _id, _pos, _sp, _title, _line2, true, 1, _c select 2];
    } forEach (_eni select 1);
};
_vis sort true;

// Regroupement : case d'environ un cartouche à l'écran, par camp ; le premier (le plus prioritaire) garde le cartouche.
private _cluX = _fs1 * 3 / _ratio;
private _cluY = _fs1 * 1.4;
private _clu = createHashMap;
private _keep = [];
{
    private _sp = _x select 3;
    private _ck = [floor ((_sp select 0) / _cluX), floor ((_sp select 1) / _cluY), _x select 6];
    private _i = _clu getOrDefault [_ck, -1];
    // Unités regroupées : nombre et noms gardés (deux joueurs côte à côte n'affichaient qu'un nom, « +1 » sans dire qui).
    if (_i < 0) then { _clu set [_ck, count _keep]; _keep pushBack [_x, 0, []]; } else { private _kp = _keep select _i; _kp set [1, (_kp select 1) + 1]; (_kp select 2) pushBack [_x select 4, _x select 8]; };
} forEach _vis;

// Place occupée : bandeau d'information, mon symbole et celui de la sélection (aucun cartouche dessus).
private _grid = createHashMap;
{ [_grid, _x, 2] call comspec_atak_native_fnc_mapLblGrid; } forEach ([_map] call comspec_atak_native_fnc_mapHudRects);
private _sel = ((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["selectedEntity", createHashMap]) getOrDefault ["id", ""];
private _hx = _symPx * 0.6 * pixelW;
private _hy = _symPx * 0.6 * pixelH;
private _placed = [];
private _occ = [_me];
private _si = _vis findIf { (_x select 1) isEqualTo _sel };
if (_sel isNotEqualTo "" && {_si >= 0}) then { _occ pushBack ((_vis select _si) select 2); };
{
    private _p = _map ctrlMapWorldToScreen _x;
    private _r = [(_p select 0) - _hx, (_p select 1) - _hy, (_p select 0) + _hx, (_p select 1) + _hy];
    [_grid, _r, 2] call comspec_atak_native_fnc_mapLblGrid;
    _placed pushBack _r;
} forEach _occ;
private _max = [20, 28] select (_style isEqualTo "CALLSIGN");
private _n = 0;
private _fill = "#(rgb,8,8,3)color(1,1,1,1)";
private _none = "#(argb,8,8,3)color(0,0,0,0)";
private _toW = { params ["_sx", "_sy"]; private _w = _map ctrlMapScreenToWorld [_sx, _sy]; [_w select 0, _w select 1, 0] };
private _bg = _pal get "lblBg";
private _line = _pal get "lblLine";
private _edge = _pal get "lblEdge";
{
    if (_n >= _max) then { break };
    _x params ["_e", "_more", ["_mNames", []]];
    _e params ["", "_id", "_pos", "_sp", "_l1", "_l2", "_hostile", "_a", "_w1"];
    _sp params ["_sx", "_sy"];
    _w1 = _w1 * _k1;
    if (_more > 0) then {
        // Deux ou trois unités groupées (noms courts) : les noms sont écrits, « +N » seulement au-delà.
        private _chars = count _l1;
        { _chars = _chars + 3 + (count (_x select 0)); } forEach _mNames;
        if (_more <= 2 && {_chars <= 44}) then {
            { _l1 = _l1 + " · " + (_x select 0); _w1 = _w1 + (_x select 1) * _k1 + 3 * _fs1 * 0.5 / _ratio; } forEach _mNames;
        } else {
            _l1 = format ["%1 +%2", _l1, _more]; _w1 = _w1 + (2 + count str _more) * _fs1 * 0.5 / _ratio;
        };
    };
    private _w = (_w1 max ([0, (count _l2) * _cw2] select _two)) + _padX * 2;
    private _h = _fs1 * 1.05 + ([0, _fs2 * 1.1] select _two) + _padY * 2;
    // Haut droite, haut gauche, bas droite, bas gauche : première place libre dans la carte.
    private _rect = [];
    {
        _x params ["_dx", "_dy"];
        private _x0 = [_sx - _ox - _w, _sx + _ox] select (_dx > 0);
        private _y0 = [_sy - _oy - _h, _sy + _oy] select (_dy > 0);
        private _r = [_x0, _y0, _x0 + _w, _y0 + _h];
        if (_x0 < _mx || {(_x0 + _w) > (_mx + _mw)} || {_y0 < _my} || {(_y0 + _h) > (_my + _mh)}) then { continue };
        if ([_grid, _r, 1] call comspec_atak_native_fnc_mapLblGrid) then { continue };
        _rect = _r;
        break;
    } forEach [[1, -1], [-1, -1], [1, 1], [-1, 1]];
    if (_rect isEqualTo []) then { continue };
    _placed pushBack _rect;
    _n = _n + 1;
    _rect params ["_x0", "_y0", "_x1", "_y1"];
    private _ctr = [(_x0 + _x1) / 2, (_y0 + _y1) / 2] call _toW;
    private _hw = (_w / 2) * _mpx;
    private _hh = (_h / 2) * _mpy;
    // Trait du symbole au coin le plus proche du cartouche.
    private _cx = [_x1, _x0] select (_x0 > _sx);
    private _cy = [_y1, _y0] select (_y0 > _sy);
    _map drawLine [[_pos select 0, _pos select 1, 0], [_cx, _cy] call _toW, [[_line select 0, _line select 1, _line select 2, (_line select 3) * _a], [_edge select 0, _edge select 1, _edge select 2, 0.8]] select _hostile];
    _map drawRectangle [_ctr, _hw, _hh, 0, [_bg select 0, _bg select 1, _bg select 2, (_bg select 3) * (_a max 0.6)], _fill];
    if (_hostile) then { _map drawRectangle [_ctr, _hw, _hh, 0, _edge, ""]; };
    if (_id isEqualTo _sel) then { _map drawRectangle [_ctr, _hw, _hh, 0, _pal get "sel", ""]; };
    private _ty = _y0 + _padY + _fs1 * 0.52;
    private _tc = [_pal get "lblTxt", _pal get "lblHostile"] select _hostile;
    _map drawIcon [_none, [_tc select 0, _tc select 1, _tc select 2, [_a, 1] select _hostile], [_x0 + _padX, _ty] call _toW, 0, 0, 0, _l1, 0, _fs1, _fB, "right"];
    if (_two) then {
        private _dc = _pal get "lblDim";
        _map drawIcon [_none, [_dc select 0, _dc select 1, _dc select 2, _a max 0.8], [_x0 + _padX, _ty + _fs1 * 0.55 + _fs2 * 0.6] call _toW, 0, 0, 0, _l2, 0, _fs2, "EtelkaMonospacePro", "right"];
    };
} forEach _keep;
// Rectangles posés (mon symbole et la sélection compris) : les noms des repères et des lieux les évitent (image suivante).
uiNamespace setVariable ["COMSPEC_ATAK_LblRectsU", _placed];
