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
                 au plus loin ; ces cartouches passent avant les noms des repères et des lieux (fn_mapLbl) ;
      regroupement  symboles à moins de 80 x 26 px l'un de l'autre (même camp) : un seul cartouche « +N » ;
      place      fn_mapLbl : les 4 coins puis les côtés du symbole, jamais sur un symbole, une étiquette ou le bandeau,
                 jamais hors de la carte, puis abandon ; nombre plafonné ; ligne 2 seulement en vue rapprochée.
    Économie : distance, azimut et largeur de l'indicatif recalculés au plus deux fois par seconde par unité (cache
    COMSPEC_ATAK_LblCache) ; liste des contacts ENI refaite quand le magasin des marqueurs change.
    Params : [carte, candidats [[priorité, id, position, indicatif, hostile, alpha, fraîcheur]...], taille des textes,
              échelle de la carte, style ("FULL" complet | "CALLSIGN" indicatif seul), taille de mon symbole (px)]
*/
params ["_map", "_cands", "_ts", "_scale", ["_style", "FULL"], ["_symPx", 21]];
// Taille, place et niveau de détail : fn_mapLbl (appelée pendant son image de calcul). Ligne 2 en vue rapprochée seulement ;
// en vue lointaine, indicatifs courts et seulement la sélection, mon groupe, les contacts et les chefs.
private _LF = uiNamespace getVariable ["COMSPEC_ATAK_LblF", createHashMap];
if !(_LF getOrDefault ["lay", false]) exitWith {};
private _lod = _LF get "lod";
private _now = diag_tickTime;
private _two = _style isEqualTo "FULL" && {_lod isEqualTo 2};
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


// Tailles en pixels réels (fn_mapLbl) ; largeurs mesurées à la taille exacte : le fond est à la taille du texte.
(ctrlPosition _map) params ["_mx", "_my", "_mw", "_mh"];
private _fs1 = (_LF get "fs") get "u";
private _fs2 = (_LF get "fs") get "u2";
private _kh = _LF getOrDefault ["kh", 0.92];
private _padX = 4 * pixelW;
private _padY = 2 * pixelH;

// Candidats visibles, triés par priorité puis du plus proche au plus loin.
private _me = [] call comspec_atak_native_fnc_gpsPos;
private _vis = [];
{
    _x params ["_prio", "_id", "_pos", "_cs", ["_hostile", false], ["_a", 1], ["_fresh", "LIVE"], ["_symH", _symPx * 0.6]];
    private _sp = _map ctrlMapWorldToScreen _pos;
    if ((_sp select 0) < _mx || {(_sp select 0) > (_mx + _mw)} || {(_sp select 1) < _my} || {(_sp select 1) > (_my + _mh)}) then { continue };
    private _c = _cache getOrDefault [_id, []];
    if ((count _c) < 4 || {_now > (_c select 0)} || {(_c select 3) isNotEqualTo _cs}) then {
        private _d = _me distance2D _pos;
        _c = [_now + 0.5, _d, ["dist", _d, _me getDir _pos] call comspec_atak_native_fnc_mapFmt, _cs];
        _cache set [_id, _c];
    };
    // Allié proche (moins de 400 m) : rangé avec mon groupe, devant les contacts ENI.
    if (_prio isEqualTo 3 && {(_c select 1) < 400}) then { _prio = 1; };
    // Vue lointaine : autres alliés masqués, indicatifs courts.
    if (_lod isEqualTo 0 && {_prio >= 3}) then { continue };
    if (_lod isEqualTo 0 && {_prio > 0}) then { _cs = ["short", _cs] call comspec_atak_native_fnc_mapLbl; };
    _vis pushBack [_prio * 1e6 + (_c select 1), _id, _pos, _sp, _cs, _c select 2, _hostile, _a, _symH];
} forEach _cands;
if (_eniOn) then {
    {
        _x params ["_id", "_pos", "_title", "_line2"];
        private _sp = _map ctrlMapWorldToScreen _pos;
        if ((_sp select 0) < _mx || {(_sp select 0) > (_mx + _mw)} || {(_sp select 1) < _my} || {(_sp select 1) > (_my + _mh)}) then { continue };
        private _c = _cache getOrDefault [_id, []];
        if ((count _c) < 2 || {_now > (_c select 0)}) then { _c = [_now + 0.5, (_me distance2D _pos) min 999999]; _cache set [_id, _c]; };
        if (_lod isEqualTo 0) then { _title = ["cut", [_title, 10]] call comspec_atak_native_fnc_mapLbl; };
        _vis pushBack [2e6 + (_c select 1), _id, _pos, _sp, _title, _line2, true, 1, _symPx * 0.6];
    } forEach (_eni select 1);
};
_vis sort true;

// Regroupement : case d'environ un cartouche à l'écran, par camp ; le premier (le plus prioritaire) garde le cartouche.
private _cluX = 80 * pixelW;
private _cluY = 26 * pixelH;
private _clu = createHashMap;
private _keep = [];
{
    private _sp = _x select 3;
    private _ck = [floor ((_sp select 0) / _cluX), floor ((_sp select 1) / _cluY), _x select 6];
    private _i = _clu getOrDefault [_ck, -1];
    // Unités regroupées : nombre et noms gardés (deux joueurs côte à côte n'affichaient qu'un nom, « +1 » sans dire qui).
    if (_i < 0) then { _clu set [_ck, count _keep]; _keep pushBack [_x, 0, []]; } else { private _kp = _keep select _i; _kp set [1, (_kp select 1) + 1]; (_kp select 2) pushBack (_x select 4); };
} forEach _vis;

// Unités dessinées à cette image : leur cartouche suit le symbole ; contacts ENI (repères) : position fixe.
private _upos = uiNamespace getVariable ["COMSPEC_ATAK_LblUPos", createHashMap];
private _sel = ((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["selectedEntity", createHashMap]) getOrDefault ["id", ""];
private _max = [20, 28] select (_style isEqualTo "CALLSIGN");
private _n = 0;
private _fill = "#(rgb,8,8,3)color(1,1,1,1)";
private _bg = _pal get "lblBg";
private _line = _pal get "lblLine";
private _edge = _pal get "lblEdge";
{
    if (_n >= _max) then { break };
    _x params ["_e", "_more", ["_mNames", []]];
    _e params ["_k", "_id", "_pos", "", "_l1", "_l2", "_hostile", "_a", "_symH"];
    if (_more > 0) then {
        // Deux ou trois unités groupées (noms courts) : les noms sont écrits, « +N » seulement au-delà.
        private _chars = count _l1;
        { _chars = _chars + 3 + (count _x); } forEach _mNames;
        if (_more <= 2 && {_chars <= 44} && {_lod > 0}) then {
            { _l1 = _l1 + " · " + _x; } forEach _mNames;
        } else {
            _l1 = format ["%1 +%2", _l1, _more];
        };
    };
    private _w1 = ["w", [_l1, _fB, _fs1]] call comspec_atak_native_fnc_mapLbl;
    private _w2 = [0, ["w", [_l2 regexReplace ["[0-9]", "0"], "EtelkaMonospacePro", _fs2]] call comspec_atak_native_fnc_mapLbl] select _two;
    private _w = (_w1 max _w2) + _padX * 2;
    private _h = _fs1 * _kh + ([0, _fs2 * _kh] select _two) + _padY * 2;
    private _pr = [];
    // Trait du symbole au coin le plus proche du cartouche.
    _pr pushBack ["L", [[_line select 0, _line select 1, _line select 2, (_line select 3) * _a], [_edge select 0, _edge select 1, _edge select 2, 0.8]] select _hostile];
    _pr pushBack ["R", 0, 0, _w, _h, [_bg select 0, _bg select 1, _bg select 2, (_bg select 3) * (_a max 0.6)], _fill];
    if (_hostile) then { _pr pushBack ["R", 0, 0, _w, _h, _edge, ""]; };
    if (_id isEqualTo _sel) then { _pr pushBack ["R", 0, 0, _w, _h, _pal get "sel", ""]; };
    private _ty = _padY + _fs1 * _kh / 2;
    private _tc = [_pal get "lblTxt", _pal get "lblHostile"] select _hostile;
    _pr pushBack ["T", _padX, _ty, _l1, [_tc select 0, _tc select 1, _tc select 2, [_a, 1] select _hostile], _fs1, _fB, 0, "right"];
    if (_two) then {
        private _dc = _pal get "lblDim";
        _pr pushBack ["T", _padX, _ty + _fs1 * _kh / 2 + _fs2 * _kh / 2, _l2, [_dc select 0, _dc select 1, _dc select 2, _a max 0.8], _fs2, "EtelkaMonospacePro", 0, "right"];
    };
    // Coins (haut droite, haut gauche, bas droite, bas gauche), puis les côtés ; sélection toujours écrite.
    // Priorité fn_mapLbl : sélection 1, mon groupe et alliés proches 3, contacts 2, autres alliés 4.
    private _p = floor (_k / 1e6);
    ["add", [[[[4, 3] select (_p isEqualTo 1), 2] select _hostile, 1] select (_id isEqualTo _sel), [_pos select 0, _pos select 1], ["", _id] select (_id in _upos), _symH, _symH, [_w, _h, _pr], _id isEqualTo _sel, [4, 6, 5, 7, 0, 1, 2, 3]]] call comspec_atak_native_fnc_mapLbl;
    _n = _n + 1;
} forEach _keep;
