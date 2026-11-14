/*
    App Zones (battleplan) : plans de zones nommées et numérotées, grands axes MSR / ASR et points de contrôle, situation
    par zone (état, signalements), partage par le réseau ATAK (fn_netSend : sans réseau, l'envoi attend en file ; à la
    réception, fn_offline retient ce qui arrive hors réseau), rejeu serveur aux arrivants, carte d'Arma et Athena
    (fn_markerNet / fn_markerWeb).
    Données :
      missionNamespace COMSPEC_ATAK_ZnMine : id -> plan (gardés dans le profil, par carte : COMSPEC_ATAK_Zn_<carte>) ;
      missionNamespace COMSPEC_ATAK_ZnRx   : id -> plan reçu (pour la partie ; rejoués par le serveur à l'arrivée) ;
      missionNamespace COMSPEC_ATAK_ZnRev  : révision de tous les plans (curseur de la carte, caches).
    Plan (HashMap) : id, name (« OBJ BRAVO »), owner, uid, rev, scope (0 moi, 1 équipe, 2 groupe, 3 camp), live, vis,
      arma, render (FILL surligné | LINE contour | POINT point centré), aff (type par défaut des zones), area (zone de
      travail [RECT [[x0, y0], [x1, y1]] | POLY [points] | CIRCLE [[x, y], rayon]]), gp (réglages du découpage, HashMap),
      zones, axes, cps, lines (contours et traits tracés), st (style du plan, HashMap), from (auteur d'un plan reçu),
      side / grp / ft (destinataires de la situation), alert (prévenir le TOC : chaque porteur du plan partagé signale ses
      entrées et sorties de zone au flux du TOC, "fenceTick", événement serveur comspec_atak_native_zoneMove, via fn_netSend),
      fence (diffusion à l'entrée dans la zone : chez un destinataire
      hors de la zone de travail, le plan reçu reste en sommeil [dormant, mémoire seulement : masqué, sans annonce ni
      alerte de situation] et se réveille à son entrée, action "fenceTick" toutes les 5 s, avec un résumé de ce qu'il
      a manqué ; le rejeu du serveur fait de même pour qui arrive en cours de partie) ; en mémoire seulement : mk / mkz (marqueurs d'Arma et leur clé),
      genTok / busy (calcul en cours), syncTok, geo (boîtes et surfaces), palc (couleurs résolues).
    Zone : [id, nom, points [[x, y]...], point d'étiquette [x, y], type, rendu ("" : celui du plan), état, horodatage
      (serverTime), auteur de l'état, signalements, bâtiments [[x, y, demi-largeur, demi-longueur, cap, étages]...],
      rectangle [x, y, a, b] (case de grille) ou [], style [couleur (classe CfgMarkerColors, "" : celle du plan),
      opacité du remplissage (-1), épaisseur px (-1), trait ("" | SOLID | DASH | DOT | ARROW)]].
    Contour / trait : [id, nom, points, fermé, style [couleur, épaisseur, trait]].
    Style du plan (st) : theme (NATO | HC contraste élevé | NV vision nocturne), fillA, width, dash, lblSize, lblBg,
      lblPos (C centre | N au-dessus), lblMode (NAME_ST | NAME | NUM | NONE), tcol / scol (type / état -> classe de
      couleur, palette de l'app Routes : même couleur sur la carte ATAK, d'Arma et Athena).
    Édition (vue EDIT de l'app, un de mes plans) : missionNamespace COMSPEC_ATAK_ZnEdit (id, undo, redo, dirty) ;
      pendant l'édition, ni renvoi du plan partagé ni marqueurs d'Arma : un seul envoi à la validation (editCommit).
    Changement d'état depuis l'app : appliqué tout de suite, envoyé 5 s plus tard (ANNULER entre-temps : rien ne part).
    Signalement : [id, horodatage, « HH:MM », auteur, nature, effectif, texte, position [x, y]].
    Axe : [id, nom (« MSR TAMPA »), MSR | ASR, points, longueur (m), rendu]. Point de contrôle : [n, « CP3 », [x, y], axe].
    Params : [action, argument] — voir les « case ».
*/
params [["_act", ""], ["_arg", []]];
// Diffusion à l'entrée dans la zone, servie avant la préparation des tables : "inArea" [plan, position] (la position
// est-elle dans la zone de travail du plan, à défaut dans une de ses zones ?) et "fenceTick" (réveil des plans en sommeil).
if (_act isEqualTo "inArea") exitWith {
    _arg params [["_b", createHashMap], ["_p", []]];
    if ((count _p) < 2) exitWith { false };
    (_b getOrDefault ["area", []]) params [["_k", ""], ["_d", []]];
    switch (_k) do {
        case "RECT": {
            _d params [["_c0", [0, 0]], ["_c1", [0, 0]]];
            (_p select 0) >= ((_c0 select 0) min (_c1 select 0)) && {(_p select 0) <= ((_c0 select 0) max (_c1 select 0))}
                && {(_p select 1) >= ((_c0 select 1) min (_c1 select 1))} && {(_p select 1) <= ((_c0 select 1) max (_c1 select 1))}
        };
        case "CIRCLE": { _d params [["_c", [0, 0]], ["_r", 0]]; ([_p select 0, _p select 1] distance2D [_c select 0, _c select 1]) <= _r };
        case "POLY": { (count _d) >= 3 && {[_p select 0, _p select 1, 0] inPolygon (_d apply { [_x select 0, _x select 1, 0] })} };
        default { (count (["zoneIn", [_b, _p]] call comspec_atak_native_fnc_zoneAction)) > 0 };
    }
};
if (_act isEqualTo "fenceTick") exitWith {
    if (isNull player || {!alive player}) exitWith {};
    // Alerte au TOC : changement de zone du joueur, pour chaque plan partagé qui le demande. Le premier passage ne signale
    // rien (le plan vient d'arriver : pas de fausse entrée).
    private _lastZ = uiNamespace getVariable ["COMSPEC_ATAK_ZnAlertLast", createHashMap];
    uiNamespace setVariable ["COMSPEC_ATAK_ZnAlertLast", _lastZ];
    private _pp = getPosATL player;
    {
        private _pl = _x;
        if !(_pl getOrDefault ["alert", false]) then { continue; };
        if (_pl getOrDefault ["dormant", false] || {(_pl getOrDefault ["scope", 0]) isEqualTo 0}) then { continue; };
        private _pid = _pl get "id";
        private _zz = ["zoneIn", [_pl, _pp]] call comspec_atak_native_fnc_zoneAction;
        private _zid = ""; private _zname = "";
        if ((count _zz) > 0) then { _zid = _zz select 0; _zname = _zz select 1; };
        if !(_pid in _lastZ) then { _lastZ set [_pid, [_zid, _zname]]; continue; };
        (_lastZ get _pid) params ["_pzid", "_pzname"];
        if (_zid isEqualTo _pzid) then { continue; };
        _lastZ set [_pid, [_zid, _zname]];
        private _what = switch (true) do {
            case (_pzid isEqualTo ""): { format ["entre en %1", _zname] };
            case (_zid isEqualTo ""): { format ["sort de %1", _pzname] };
            default { format ["sort de %1, entre en %2", _pzname, _zname] };
        };
        [{ ["comspec_atak_native_zoneMove", _this] call CBA_fnc_serverEvent; }, [str side group player, [player] call comspec_atak_native_fnc_unitCallsign, _pl get "name", _what, getPosASL player, mapGridPosition player], "Zone", 1] call comspec_atak_native_fnc_netSend;
    } forEach ((values (missionNamespace getVariable ["COMSPEC_ATAK_ZnMine", createHashMap])) + (values (missionNamespace getVariable ["COMSPEC_ATAK_ZnRx", createHashMap])));
    {
        private _b = _x;
        if ((_b getOrDefault ["dormant", false]) && {["inArea", [_b, getPosATL player]] call comspec_atak_native_fnc_zoneAction}) then {
            _b deleteAt "dormant";
            ["set", [_b get "id", "vis", true]] call comspec_atak_native_fnc_zoneAction;
            private _nRep = 0;
            { _nRep = _nRep + (count (_x param [9, []])); } forEach (_b get "zones");
            ["MESSAGE", format ["Entrée dans la zone %1 : plan de %2 reçu, %3 zone(s), %4 signalement(s)", _b get "name", _b getOrDefault ["from", ""], count (_b get "zones"), _nRep], 6, 50] call comspec_atak_native_fnc_notify;
            [] call comspec_atak_native_fnc_vibrate;
        };
    } forEach (values (missionNamespace getVariable ["COMSPEC_ATAK_ZnRx", createHashMap]));
};
// Lectures faites à chaque image (fn_zoneDraw : chaque plan ; bandeau du curseur : chaque mouvement de souris) servies
// avant la préparation des tables ci-dessous (près de 250 lignes refaites à chaque appel) : caches par révision
// « geo » et « pal », triangles, zone sous un point et texte du curseur déjà calculé.
if (_act isEqualTo "geo" && {((_arg getOrDefault ["geo", []]) param [0, -1]) isEqualTo (_arg get "rev")}) exitWith { _arg get "geo" };
if (_act isEqualTo "pal" && {((_arg getOrDefault ["palc", []]) param [0, -1]) isEqualTo (_arg get "rev")}) exitWith { (_arg get "palc") select 1 };
if (_act isEqualTo "atText" && {missionNamespace getVariable ["comspec_atak_native_zones_cursor", true]} && {
    private _p = _arg param [0, []];
    (count _p) >= 2 && {((count (missionNamespace getVariable ["COMSPEC_ATAK_ZnMine", createHashMap])) + (count (missionNamespace getVariable ["COMSPEC_ATAK_ZnRx", createHashMap]))) > 0} && {
        ((uiNamespace getVariable ["COMSPEC_ATAK_ZnCursor", []]) param [0, []]) isEqualTo [round ((_p select 0) / 3), round ((_p select 1) / 3), missionNamespace getVariable ["COMSPEC_ATAK_ZnRev", 0], (missionNamespace getVariable ["COMSPEC_ATAK_Lang", "en"]) isEqualTo "fr"]
    }
}) exitWith { (uiNamespace getVariable "COMSPEC_ATAK_ZnCursor") select 1 };
// Triangles de remplissage des zones qui ne sont pas des cases (calcul en arrière-plan, gardé par révision) :
// HashMap id de zone -> triangles (vide tant que le calcul n'est pas fini).
if (_act isEqualTo "tri") exitWith {
    private _b = _arg;
    private _cache = uiNamespace getVariable "COMSPEC_ATAK_ZnTri";
    if (isNil "_cache") then { _cache = createHashMap; uiNamespace setVariable ["COMSPEC_ATAK_ZnTri", _cache]; };
    private _key = format ["%1|%2", _b get "id", _b get "rev"];
    private _e = _cache getOrDefault [_b get "id", []];
    if ((_e param [0, ""]) isEqualTo _key) exitWith { _e select 1 };
    _cache set [_b get "id", [_key, createHashMap]];
    [_b get "id", _key, ((_b get "zones") select { (count (_x select 11)) isNotEqualTo 4 }) apply { [_x select 0, _x select 2] }] spawn {
        params ["_id", "_key", "_list"];
        private _h = createHashMap;
        { _h set [_x select 0, ["tri", [_x select 1]] call comspec_atak_native_fnc_zoneGeom]; if ((_forEachIndex mod 4) isEqualTo 3) then { sleep 0.01; }; } forEach _list;
        private _cache = uiNamespace getVariable ["COMSPEC_ATAK_ZnTri", createHashMap];
        if (((_cache getOrDefault [_id, []]) param [0, ""]) isEqualTo _key) then { _cache set [_id, [_key, _h]]; };
    };
    createHashMap
};
// Aperçu du découpage avant GÉNÉRER (grille, secteurs, une zone) : [contours [[x, y]...]...], gardé tant que la
// zone de travail et les réglages ne changent pas. Îlots et traits : [] (calcul trop lourd pour un aperçu).
if (_act isEqualTo "preview") exitWith {
    private _b = _arg;
    private _gp = _b getOrDefault ["gp", createHashMap];
    private _cut = _gp getOrDefault ["cut", "GRID"];
    private _key = str [_b getOrDefault ["area", []], _cut, _gp getOrDefault ["size", 200], _gp getOrDefault ["snap", true], _gp getOrDefault ["sec", 8], _gp getOrDefault ["rings", 1]];
    private _c = uiNamespace getVariable ["COMSPEC_ATAK_ZnPrev", []];
    if ((_c param [0, ""]) isEqualTo _key) exitWith { _c select 1 };
    (["areaPoly", _b] call comspec_atak_native_fnc_zoneAction) params ["_ap", "_ak", "_ac", "_ar"];
    private _out = [];
    if ((count _ap) >= 3) then {
        private _ap3 = _ap apply { [_x select 0, _x select 1, 0] };
        (["bbox", [_ap]] call comspec_atak_native_fnc_zoneGeom) params ["_bx0", "_by0", "_bx1", "_by1"];
        switch (_cut) do {
            case "GRID": {
                private _S = ((_gp getOrDefault ["size", 200]) max 50) min 2000;
                private _x0 = _bx0; private _y0 = _by0;
                if (_gp getOrDefault ["snap", true]) then { _x0 = (floor (_bx0 / _S)) * _S; _y0 = (floor (_by0 / _S)) * _S; };
                private _nx = ceil ((_bx1 - _x0) / _S) max 1; private _ny = ceil ((_by1 - _y0) / _S) max 1;
                if ((_nx * _ny) > 2400) exitWith {};
                for "_j" from 0 to (_ny - 1) do {
                    for "_i" from 0 to (_nx - 1) do {
                        private _cx = _x0 + (_i + 0.5) * _S; private _cy = _y0 + (_j + 0.5) * _S;
                        if ([_cx, _cy, 0] inPolygon _ap3) then { _out pushBack [[_cx, _cy, _S / 2]]; };
                    };
                };
            };
            case "RADIAL": {
                private _k = _gp getOrDefault ["sec", 8]; if !(_k in [4, 8, 16]) then { _k = 8; };
                private _m = ((_gp getOrDefault ["rings", 1]) max 1) min 3;
                private _cc = _ac; private _r0 = _ar;
                if (_ak isNotEqualTo "CIRCLE") then { _cc = ["centroid", [_ap]] call comspec_atak_native_fnc_zoneGeom; _r0 = (((_bx1 - _bx0) max (_by1 - _by0)) / 2) max 50; };
                for "_r" from 1 to _m do { for "_i" from 0 to (_k - 1) do { private _a = _i * 360 / _k; _out pushBack (["ring", [_cc, _r0 * (_r - 1) / _m, _r0 * _r / _m, _a - 180 / _k, _a + 180 / _k]] call comspec_atak_native_fnc_zoneGeom); }; };
            };
            case "SINGLE": { _out pushBack _ap; };
            default {};
        };
    };
    uiNamespace setVariable ["COMSPEC_ATAK_ZnPrev", [_key, _out]];
    _out
};
// Plan par son id (carte de l'app Zones : à chaque image), une fois les plans relus du profil.
if (_act isEqualTo "get" && {!isNil {missionNamespace getVariable "COMSPEC_ATAK_ZnMine"}}) exitWith {
    (missionNamespace getVariable "COMSPEC_ATAK_ZnMine") getOrDefault [_arg, (missionNamespace getVariable ["COMSPEC_ATAK_ZnRx", createHashMap]) getOrDefault [_arg, createHashMap]]
};
// Zone d'un plan qui contient la position (la plus petite) : [plan, position] -> zone ou [].
if (_act isEqualTo "zoneIn") exitWith {
    _arg params ["_b", "_p"];
    private _g = ["geo", _b] call comspec_atak_native_fnc_zoneAction;
    (_g select 1) params ["_X0", "_Y0", "_X1", "_Y1"];
    _p params ["_px", "_py"];
    if (_px < _X0 || {_px > _X1} || {_py < _Y0} || {_py > _Y1}) exitWith { [] };
    private _best = []; private _ba = 1e12;
    private _p3 = [_px, _py, 0];
    {
        (_g select 2 select _forEachIndex) params ["_x0", "_y0", "_x1", "_y1"];
        if (_px >= _x0 && {_px <= _x1} && {_py >= _y0} && {_py <= _y1}) then {
            private _a = (_g select 3) select _forEachIndex;
            if (_a < _ba && {(count (_x select 11)) isEqualTo 4 || {_p3 inPolygon ((_g select 4) select _forEachIndex)}}) then { _ba = _a; _best = _x; };
        };
    } forEach (_b get "zones");
    _best
};
private _tr = { _this call comspec_atak_native_fnc_tr };
private _fr = (missionNamespace getVariable ["COMSPEC_ATAK_Lang", "en"]) isEqualTo "fr";
// États : clé, libellé, abrégé français, abrégé anglais (jamais repassés par la traduction), couleur, couleur d'Arma.
private _states = [
    ["UNREC", "Non reconnue", "NR", "UNR", [0.62, 0.66, 0.64, 1], "ColorGrey"],
    ["CLEAR", "Claire", "RAS", "CLR", [0.30, 0.82, 0.38, 1], "ColorGreen"],
    ["CONT", "Contact", "CTC", "TIC", [1, 0.60, 0.12, 1], "ColorOrange"],
    ["ENY", "Ennemi signalé", "ENI", "ENY", [0.95, 0.20, 0.15, 1], "ColorRed"],
    ["FRD", "Tenue par ami", "AMI", "FRD", [0.25, 0.55, 1, 1], "ColorBlue"],
    ["DENY", "Interdite", "INT", "DNY", [0.72, 0.32, 0.92, 1], "ColorCIV"]
];
// Types de zone (symbologie : ami bleu, ennemi rouge, neutre vert, inconnu jaune) : clé, libellé, couleur, couleur d'Arma.
private _types = [
    ["GEN", "Repère", [0.92, 0.93, 0.92, 1], "ColorWhite"],
    ["OBJ", "Objectif", [1, 0.55, 0.10, 1], "ColorOrange"],
    ["FRD", "Ami", [0.25, 0.55, 1, 1], "ColorBLUFOR"],
    ["HOS", "Ennemi", [0.95, 0.20, 0.15, 1], "ColorOPFOR"],
    ["NEU", "Neutre", [0.25, 0.80, 0.35, 1], "ColorGreen"],
    ["UNK", "Inconnu", [1, 0.85, 0.20, 1], "ColorYellow"],
    ["RES", "Restreinte", [0.75, 0.35, 0.95, 1], "ColorCIV"],
    ["LZ", "LZ / poser", [0.20, 0.85, 0.90, 1], "ColorPink"]
];
// Thèmes de couleurs : types, états, opacité du remplissage, épaisseur par défaut. NATO : symbologie standard (le
// « Repère » est sombre : visible sur la carte claire) ; HC : contraste élevé ; NV : vision nocturne (teintes sourdes).
private _pals = createHashMapFromArray [
    ["NATO", [createHashMapFromArray [["GEN", [0.16, 0.19, 0.18, 1]], ["OBJ", [1, 0.55, 0.10, 1]], ["FRD", [0.25, 0.55, 1, 1]], ["HOS", [0.95, 0.20, 0.15, 1]], ["NEU", [0.25, 0.80, 0.35, 1]], ["UNK", [1, 0.85, 0.20, 1]], ["RES", [0.75, 0.35, 0.95, 1]], ["LZ", [0.20, 0.85, 0.90, 1]]],
        createHashMapFromArray [["UNREC", [0.62, 0.66, 0.64, 1]], ["CLEAR", [0.30, 0.82, 0.38, 1]], ["CONT", [1, 0.60, 0.12, 1]], ["ENY", [0.95, 0.20, 0.15, 1]], ["FRD", [0.25, 0.55, 1, 1]], ["DENY", [0.72, 0.32, 0.92, 1]]], 0.15, 1]],
    ["HC", [createHashMapFromArray [["GEN", [0, 0, 0, 1]], ["OBJ", [1, 0.45, 0, 1]], ["FRD", [0, 0.35, 1, 1]], ["HOS", [1, 0, 0, 1]], ["NEU", [0, 0.7, 0, 1]], ["UNK", [1, 0.9, 0, 1]], ["RES", [0.8, 0, 1, 1]], ["LZ", [0, 0.9, 1, 1]]],
        createHashMapFromArray [["UNREC", [0.35, 0.35, 0.35, 1]], ["CLEAR", [0, 0.85, 0, 1]], ["CONT", [1, 0.5, 0, 1]], ["ENY", [1, 0, 0, 1]], ["FRD", [0, 0.4, 1, 1]], ["DENY", [0.8, 0, 1, 1]]], 0.25, 2]],
    ["NV", [createHashMapFromArray [["GEN", [0.25, 0.45, 0.25, 1]], ["OBJ", [0.6, 0.5, 0.2, 1]], ["FRD", [0.2, 0.45, 0.35, 1]], ["HOS", [0.6, 0.15, 0.1, 1]], ["NEU", [0.2, 0.55, 0.2, 1]], ["UNK", [0.55, 0.5, 0.15, 1]], ["RES", [0.4, 0.3, 0.45, 1]], ["LZ", [0.2, 0.5, 0.45, 1]]],
        createHashMapFromArray [["UNREC", [0.3, 0.38, 0.3, 1]], ["CLEAR", [0.2, 0.55, 0.2, 1]], ["CONT", [0.6, 0.4, 0.1, 1]], ["ENY", [0.65, 0.15, 0.1, 1]], ["FRD", [0.15, 0.35, 0.5, 1]], ["DENY", [0.45, 0.2, 0.5, 1]]], 0.12, 1]]
];
// Style par défaut d'un plan.
private _stDef = { createHashMapFromArray [["theme", "NATO"], ["fillA", -1], ["width", -1], ["dash", "SOLID"], ["lblSize", 1], ["lblBg", false], ["lblPos", "C"], ["lblMode", "NAME_ST"], ["hatch", false], ["ring", false], ["tcol", createHashMap], ["scol", createHashMap]] };
private _natures = [
    ["UNK", "Inconnue"], ["INF", "Infanterie"], ["MOT", "Motorisé"], ["MECA", "Mécanisé / blindé"], ["REC", "Reconnaissance"],
    ["ART", "Tirs indirects"], ["AA", "Antiaérien"], ["AIR", "Aérien / drone"], ["SNI", "Tireur embusqué"], ["IED", "EEI / piège"],
    ["HQ", "Poste de commandement"], ["CIV", "Civils"]
];
private _renders = [["FILL", "Surligné"], ["LINE", "Contour"], ["POINT", "Point centré"]];
// Noms de code (noms propres, jamais traduits ; majuscules courtes, sans accents ni umlauts ; planètes et lunes dans
// la langue du téléphone). CITY : villes américaines (clé gardée pour les plans enregistrés avant la 2.9.41).
private _themes = createHashMapFromArray [
    ["NATO", ["ALPHA", "BRAVO", "CHARLIE", "DELTA", "ECHO", "FOXTROT", "GOLF", "HOTEL", "INDIA", "JULIETT", "KILO", "LIMA", "MIKE", "NOVEMBER", "OSCAR", "PAPA", "QUEBEC", "ROMEO", "SIERRA", "TANGO", "UNIFORM", "VICTOR", "WHISKEY", "XRAY", "YANKEE", "ZULU"]],
    ["CITY", ["TAMPA", "DALLAS", "BOSTON", "DENVER", "ATLANTA", "PHOENIX", "DETROIT", "MEMPHIS", "RALEIGH", "TUCSON", "SEATTLE", "OMAHA", "RENO", "TULSA", "AUSTIN", "DOVER", "MIAMI", "ORLANDO", "SALEM", "TOPEKA",
        "HOUSTON", "CHICAGO", "BUFFALO", "MOBILE", "FRESNO", "BOISE", "DAYTON", "LAREDO"]],
    ["FRCITY", ["PARIS", "LYON", "NANTES", "BREST", "LILLE", "NICE", "TOULON", "REIMS", "METZ", "NANCY", "DIJON", "ROUEN", "CAEN", "TOURS", "BORDEAUX", "LIMOGES",
        "GRENOBLE", "ANGERS", "AMIENS", "PAU", "BAYONNE", "CALAIS", "VANNES", "BLOIS", "COLMAR", "ARRAS", "CHARTRES", "VERDUN"]],
    ["UKCITY", ["LONDON", "LEEDS", "YORK", "BATH", "BRISTOL", "CARDIFF", "OXFORD", "DERBY", "CHESTER", "DURHAM", "EXETER", "LINCOLN", "NEWPORT", "PERTH", "STIRLING", "BANGOR",
        "WELLS", "HULL", "GLASGOW", "DUNDEE", "SWANSEA", "PLYMOUTH", "NORWICH", "IPSWICH", "CARLISLE", "SALISBURY", "WINDSOR", "ABERDEEN"]],
    ["DECITY", ["BERLIN", "HAMBURG", "BREMEN", "KIEL", "DRESDEN", "LEIPZIG", "MAINZ", "BONN", "ESSEN", "ULM", "TRIER", "KASSEL", "ERFURT", "JENA", "AACHEN", "FULDA",
        "PASSAU", "HALLE", "ROSTOCK", "HANNOVER", "AUGSBURG", "BAYREUTH", "KOBLENZ", "LANDAU", "WEIMAR", "GOTHA", "STUTTGART", "SCHWERIN"]],
    ["PLANET", [
        ["MERCURY", "VENUS", "MARS", "JUPITER", "SATURN", "URANUS", "NEPTUNE", "PLUTO", "PHOBOS", "DEIMOS", "IO", "EUROPA", "GANYMEDE", "CALLISTO", "TITAN", "RHEA",
            "DIONE", "TETHYS", "MIMAS", "ENCELADUS", "IAPETUS", "TRITON", "OBERON", "TITANIA", "ARIEL", "MIRANDA", "CHARON", "CERES"],
        ["MERCURE", "VENUS", "MARS", "JUPITER", "SATURNE", "URANUS", "NEPTUNE", "PLUTON", "PHOBOS", "DEIMOS", "IO", "EUROPE", "GANYMEDE", "CALLISTO", "TITAN", "RHEA",
            "DIONE", "TETHYS", "MIMAS", "ENCELADE", "JAPET", "TRITON", "OBERON", "TITANIA", "ARIEL", "MIRANDA", "CHARON", "CERES"]
    ] select _fr],
    ["ANIMAL", ["COBRA", "VIPER", "FALCON", "BISON", "JAGUAR", "MAMBA", "PYTHON", "RAVEN", "WOLF", "BADGER", "HAWK", "LYNX", "PUMA", "RHINO", "TIGER", "WOLVERINE"]]
];
// Thèmes de nommage proposés (app Zones onglet NOMMER, clic droit › Nommer les axes) : clé, libellé, exemples (noms
// propres). MIL : graphiques de contrôle APP-6 / MIL-STD-2525, abréviation choisie selon le genre d'élément (jamais
// traduite, modifiable par élément), suivie d'un nom du sous-thème choisi (« MSR TAMPA », « PL MARS »).
private _themeRows = [
    ["MIL", "MIL-STD / OTAN (doctrine)", "MSR TAMPA, PL MARS, OBJ LYON"],
    ["NATO", "Alphabet OTAN", "ALPHA, BRAVO, CHARLIE"],
    ["CITY", "Villes américaines", "TAMPA, DALLAS, BOSTON"],
    ["FRCITY", "Villes françaises", "PARIS, LYON, BREST"],
    ["UKCITY", "Villes britanniques", "LONDON, LEEDS, YORK"],
    ["DECITY", "Villes allemandes", "BERLIN, BREMEN, KIEL"],
    ["PLANET", "Planètes et lunes", ["MARS, JUPITER, SATURN", "MARS, JUPITER, SATURNE"] select _fr],
    ["ANIMAL", "Animaux", "COBRA, VIPER, FALCON"]
];
// Abréviations doctrinales par genre d'élément (la première est celle par défaut) : axe principal / secondaire, trait
// ouvert (lignes), contour fermé et zone (surfaces), point de contrôle (numéroté).
private _abbrs = createHashMapFromArray [
    ["axisMain", ["MSR", "ASR", "ROUTE", "PL", "LD", "LOA", "FSCL", "CFL"]],
    ["axis", ["ASR", "MSR", "ROUTE", "PL", "LD", "LOA", "FSCL", "CFL"]],
    ["line", ["PL", "LD", "LOA", "ROUTE", "FSCL", "CFL", "MSR", "ASR"]],
    ["area", ["AA", "OBJ", "NAI", "TAI", "EA", "BP", "LZ", "PZ", "DZ", "ACA", "ROZ"]],
    ["cp", ["CP", "PP", "RP", "ORP", "SP"]]
];
// Zone d'un type donné : abréviation par défaut (objectif, ennemi ou inconnu : zone d'intérêt, ami : position de combat…).
private _abbrType = createHashMapFromArray [["OBJ", "OBJ"], ["HOS", "NAI"], ["UNK", "NAI"], ["FRD", "BP"], ["NEU", "AA"], ["RES", "ROZ"], ["LZ", "LZ"], ["GEN", "AA"]];
private _abbrAll = ["MSR", "ASR", "ROUTE", "PL", "LD", "LOA", "FSCL", "CFL", "AA", "OBJ", "NAI", "TAI", "EA", "BP", "LZ", "PZ", "DZ", "ACA", "ROZ", "CP", "PP", "RP", "ORP", "SP"];
// Nom -> [abréviation doctrinale ou "", reste du nom].
private _split = {
    params ["_n"];
    private _w = _n splitString " ";
    if ((count _w) > 1 && {(_w select 0) in _abbrAll}) exitWith { [_w select 0, (_w select [1]) joinString " "] };
    ["", _n]
};
// Noms d'un thème (MIL : ceux de son sous-thème).
private _themeList = {
    params ["_k", ["_sub", "CITY"]];
    if (_k isEqualTo "MIL") then { _k = [_sub, "CITY"] select (_sub isEqualTo "MIL"); };
    _themes getOrDefault [_k, _themes get "CITY"]
};
// Noms complets des éléments d'un plan (zones, axes, traits, points de contrôle), sauf l'élément [genre, id].
private _planNames = {
    params ["_b", ["_skip", ["", ""]]];
    private _out = [];
    { if (["zone", _x select 0] isNotEqualTo _skip) then { _out pushBack (_x select 1); }; } forEach (_b getOrDefault ["zones", []]);
    { if (["axis", _x select 0] isNotEqualTo _skip) then { _out pushBack (_x select 1); }; } forEach (_b getOrDefault ["axes", []]);
    { if (["line", _x select 0] isNotEqualTo _skip) then { _out pushBack (_x select 1); }; } forEach (_b getOrDefault ["lines", []]);
    { if (["cp", str (_x select 0)] isNotEqualTo _skip) then { _out pushBack (_x select 1); }; } forEach (_b getOrDefault ["cps", []]);
    _out
};
// Nom sans doublon dans le plan : « 2 », « 3 »… ajouté (longueur bornée).
private _uniq = {
    params ["_b", "_n", "_max", ["_skip", ["", ""]]];
    private _have = [_b, _skip] call _planNames;
    if !(_n in _have) exitWith { _n };
    private _k = 2;
    private _out = _n;
    while { _out in _have && {_k < 100} } do {
        private _sfx = format [" %1", _k];
        _out = (_n select [0, _max - (count _sfx)]) + _sfx;
        _k = _k + 1;
    };
    _out
};
// Prochains noms libres d'une liste (jamais un nom déjà pris) ; liste épuisée : « ALPHA 2 », « ALPHA 3 »…
private _nextNames = {
    params ["_list", "_used", "_n"];
    private _out = [];
    private _cycle = 1;
    while { (count _out) < _n && {_cycle < 20} } do {
        {
            private _c = [_x, format ["%1 %2", _x, _cycle]] select (_cycle > 1);
            if !(_c in _used) then { _out pushBack _c; _used pushBack _c; };
            if ((count _out) >= _n) exitWith {};
        } forEach _list;
        _cycle = _cycle + 1;
    };
    _out
};
private _stateRow = { params ["_k"]; _states select ((_states findIf { (_x select 0) isEqualTo _k }) max 0) };
private _typeRow = { params ["_k"]; _types select ((_types findIf { (_x select 0) isEqualTo _k }) max 0) };
private _clean = { params ["_t", ["_n", 32]]; if !(_t isEqualType "") exitWith { "" }; ((trim _t) regexReplace ["[<>""\\]", ""]) select [0, _n] };
private _mine = missionNamespace getVariable "COMSPEC_ATAK_ZnMine";
private _rx = missionNamespace getVariable ["COMSPEC_ATAK_ZnRx", createHashMap];
if (isNil "_mine") then {
    // Premier appel : plans de cette carte relus du profil (tables posées avant : fn_zoneUnpack rappelle cette fonction).
    _mine = createHashMap;
    missionNamespace setVariable ["COMSPEC_ATAK_ZnMine", _mine];
    missionNamespace setVariable ["COMSPEC_ATAK_ZnRx", _rx];
    {
        private _b = [_x] call comspec_atak_native_fnc_zoneUnpack;
        // Plan relu d'une ancienne partie : privé (il n'est plus partagé tant qu'on ne le renvoie pas). Plan sans rien dedans
        // (ancien clic droit « Zones » qui créait un plan à chaque tracé) : oublié.
        if ((count _b) > 0 && {((count (_b get "zones")) + (count (_b get "axes")) + (count (_b getOrDefault ["lines", []])) + (count (_b getOrDefault ["area", []]))) > 0}) then {
            { _b set _x; } forEach [["arma", false], ["from", ""], ["scope", 0], ["live", false]];
            _mine set [_b get "id", _b];
        };
    } forEach (profileNamespace getVariable [format ["COMSPEC_ATAK_Zn_%1", worldName], []]);
};
private _get = { params ["_id"]; _mine getOrDefault [_id, _rx getOrDefault [_id, createHashMap]] };
// Édition en cours (un de mes plans) : état empaqueté gardé avant chaque modification (ANNULER), 25 pas au plus.
private _ed = missionNamespace getVariable ["COMSPEC_ATAK_ZnEdit", createHashMap];
private _editing = { params ["_id"]; (_ed getOrDefault ["id", ""]) isEqualTo _id };
private _snap = {
    params ["_b"];
    if !([_b get "id"] call _editing) exitWith {};
    private _u = _ed get "undo";
    _u pushBack +(["pack", _b] call comspec_atak_native_fnc_zoneAction);
    if ((count _u) > 25) then { _u deleteAt 0; };
    _ed set ["redo", []];
    _ed set ["dirty", true];
};
// Plan remis dans un état empaqueté : découpage, axes, traits, style, nom (affichage, partage et situation gardés).
private _restore = {
    params ["_b", "_pk"];
    private _n = [_pk] call comspec_atak_native_fnc_zoneUnpack;
    if ((count _n) isEqualTo 0) exitWith {};
    // Situation des zones gardée (elle se donne hors de l'édition) : reprise par id de zone.
    private _sit = createHashMap;
    { _sit set [_x select 0, [_x select 6, _x select 7, _x select 8, _x select 9]]; } forEach (_b get "zones");
    { private _s = _sit getOrDefault [_x select 0, []]; if ((count _s) > 0) then { _x set [6, _s select 0]; _x set [7, _s select 1]; _x set [8, _s select 2]; _x set [9, _s select 3]; }; } forEach (_n get "zones");
    { _b set [_x, _n get _x]; } forEach ["name", "render", "aff", "area", "gp", "zones", "axes", "cps", "lines", "st"];
    _b set ["genTok", (_b getOrDefault ["genTok", 0]) + 1];
    _b set ["busy", ""];
};
private _hasContent = { params ["_b"]; ((count (_b get "zones")) + (count (_b get "axes")) + (count (_b getOrDefault ["lines", []]))) > 0 };
private _zoneOf = { params ["_b", "_zid"]; private _zs = _b getOrDefault ["zones", []]; private _i = _zs findIf { (_x select 0) isEqualTo _zid }; [[], _zs select _i] select (_i >= 0) };
private _bump = { missionNamespace setVariable ["COMSPEC_ATAK_ZnRev", (missionNamespace getVariable ["COMSPEC_ATAK_ZnRev", 0]) + 1]; };
private _refresh = {
    if ((missionNamespace getVariable ["COMSPEC_ATAK_ZnRefreshAt", -1]) isEqualTo diag_frameNo) exitWith {};
    missionNamespace setVariable ["COMSPEC_ATAK_ZnRefreshAt", diag_frameNo];
    [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "ZONES") then { ["#refresh"] call comspec_atak_native_fnc_pageZones; }; }] call comspec_atak_native_fnc_nextFrame;
};
private _save = {
    private _tok = (missionNamespace getVariable ["COMSPEC_ATAK_ZnSaveTok", 0]) + 1;
    missionNamespace setVariable ["COMSPEC_ATAK_ZnSaveTok", _tok];
    [{
        if (_this isNotEqualTo (missionNamespace getVariable ["COMSPEC_ATAK_ZnSaveTok", 0])) exitWith {};
        private _m = missionNamespace getVariable ["COMSPEC_ATAK_ZnMine", createHashMap];
        profileNamespace setVariable [format ["COMSPEC_ATAK_Zn_%1", worldName], (values _m) apply { ["pack", _x] call comspec_atak_native_fnc_zoneAction }];
        [] call comspec_atak_native_fnc_saveProfile;
    }, _tok, 2] call comspec_atak_native_fnc_waitAndExec;
};
// Plan modifié : révision (géométrie), profil, page, carte d'Arma et partage en direct, regroupés.
private _changed = {
    params ["_b", ["_geo", true], ["_resend", true]];
    if (_geo) then { _b set ["rev", (_b getOrDefault ["rev", 0]) + 1]; _b deleteAt "geo"; };
    call _bump;
    if ((_b get "id") in _mine) then {
        call _save;
        // Plan en cours d'édition : renvoi et marqueurs d'Arma à la validation.
        if ([_b get "id"] call _editing) exitWith { _ed set ["dirty", true]; };
        private _id = _b get "id";
        private _tok = (_b getOrDefault ["syncTok", 0]) + 1;
        _b set ["syncTok", _tok];
        [{
            params ["_id", "_tok", "_resend"];
            private _b = ["get", _id] call comspec_atak_native_fnc_zoneAction;
            if ((count _b) isEqualTo 0 || {_tok isNotEqualTo (_b getOrDefault ["syncTok", -1])} || {(_b getOrDefault ["busy", ""]) isNotEqualTo ""}) exitWith {};
            if ((_b get "arma") || {(count (_b getOrDefault ["mk", []])) > 0}) then { ["armaNow", _id] call comspec_atak_native_fnc_zoneAction; };
            if (_resend && {_b getOrDefault ["live", false]} && {(_b get "scope") > 0}) then { ["share", [_id, _b get "scope", true]] call comspec_atak_native_fnc_zoneAction; };
        }, [_id, _tok, _resend], 1.5] call comspec_atak_native_fnc_waitAndExec;
    };
    call _refresh;
};
// Destinataires d'un plan partagé : camp, groupe ou équipe de son auteur (gardés au partage), et son auteur.
private _aud = {
    params ["_b"];
    private _sc = _b getOrDefault ["scope", 0];
    if (_sc <= 0) exitWith { [] };
    private _side = _b getOrDefault ["side", str side group player];
    private _grp = _b getOrDefault ["grp", netId group player];
    private _ft = _b getOrDefault ["ft", ""];
    private _ou = _b getOrDefault ["uid", ""];
    allPlayers select {
        _x isNotEqualTo player && {(str side group _x) isEqualTo _side} && {
            _sc >= 3 || {(getPlayerUID _x) isEqualTo _ou} || {(netId group _x) isEqualTo _grp && {_sc >= 2 || {_ft isEqualTo "" || {(_x getVariable ["COMSPEC_FT", ""]) isEqualTo _ft}}}}
        }
    }
};
// Noms de code déjà pris : axes, traits et zones typées de tous les plans (sans leur abréviation), nom des plans.
private _usedNames = {
    private _u = [];
    {
        private _bb = _x;
        { _u pushBack (([_x select 1] call _split) select 1); } forEach ((_bb getOrDefault ["axes", []]) + (_bb getOrDefault ["lines", []]));
        { if ((_x select 4) isNotEqualTo "GEN") then { _u pushBack (([_x select 1] call _split) select 1); }; } forEach (_bb getOrDefault ["zones", []]);
        _u pushBack (((_bb get "name") splitString " ") param [1, ""]);
    } forEach ((values _mine) + (values _rx));
    { _u pushBack (([_y get "name"] call _split) select 1); } forEach (missionNamespace getVariable ["COMSPEC_ATAK_RteMine", createHashMap]);
    _u
};
// Situation appliquée à une zone : état plus récent seulement ; signalements ajoutés sans doublon (12 au plus).
private _apply = {
    params ["_b", "_zid", "_st", "_stamp", "_by", "_reps"];
    private _z = [_b, _zid] call _zoneOf;
    if ((count _z) isEqualTo 0) exitWith { false };
    private _ch = false;
    if (_st isNotEqualTo "" && {(_states findIf { (_x select 0) isEqualTo _st }) >= 0} && {_stamp >= (_z select 7)}) then {
        if ((_z select 6) isNotEqualTo _st) then { _ch = true; };
        _z set [6, _st]; _z set [7, _stamp]; _z set [8, [_by, 24] call _clean];
    };
    private _have = (_z select 9) apply { _x select 0 };
    {
        if (_x isEqualType [] && {(count _x) >= 8} && {!((_x select 0) in _have)}) then { (_z select 9) pushBack _x; _ch = true; };
    } forEach _reps;
    if ((count (_z select 9)) > 12) then { private _r = (_z select 9) apply { [_x select 1, _x] }; _r sort true; _z set [9, (_r select [(count _r) - 12]) apply { _x select 1 }]; };
    _ch
};

switch (_act) do {
    case "states": { _states };
    case "state": { [_arg] call _stateRow };
    case "stateShort": { ([_arg] call _stateRow) select ([3, 2] select _fr) };
    case "types": { _types };
    case "type": { [_arg] call _typeRow };
    case "natures": { _natures };
    case "natureName": { ((_natures select ((_natures findIf { (_x select 0) isEqualTo _arg }) max 0)) select 1) call _tr };
    case "renders": { _renders };
    case "themes": { _themes };
    case "scopeName": { (["Moi seul", "Équipe", "Groupe", "Camp"] param [_arg, "Groupe"]) call _tr };
    case "mine": { _mine };
    case "rx": { _rx };
    case "get": { [_arg] call _get };
    case "isMine": { _arg in _mine };
    case "zone": { _arg params ["_id", "_zid"]; [[_id] call _get, _zid] call _zoneOf };
    // Plan -> paires (profil, réseau) ; cases de grille envoyées sans leurs coins (refaits d'après le rectangle).
    case "pack": {
        private _b = _arg;
        ((keys _b) select { !(_x in ["mk", "mkz", "mkTok", "genTok", "busy", "syncTok", "geo", "palc", "dormant"]) }) apply {
            private _v = _b get _x;
            switch (_x) do {
                case "gp": { _v = (keys _v) apply { [_x, _v get _x] }; };
                // Pas de [a, keys _w] select … : SQF évalue les deux branches, keys sur une valeur simple arrêtait l'empaquetage
                // (profil, partage et pile d'annulation des plans perdus sans rien dire).
                case "st": { _v = (keys _v) apply { private _w = _v get _x; if (_w isEqualType createHashMap) then { [_x, (keys _w) apply { [_x, _w get _x] }] } else { [_x, _w] } }; };
                case "zones": { _v = _v apply { if ((count (_x param [11, []])) isEqualTo 4) then { private _z = +_x; _z set [2, []]; _z } else { _x } }; };
            };
            [_x, _v]
        }
    };
    case "new": {
        _arg params [["_name", ""], ["_pre", "PLAN"]];
        if ((count _mine) >= 30) exitWith { ["WARNING", "30 plans de zones au plus : supprimez-en un", 3, 20] call comspec_atak_native_fnc_notify; "" };
        private _id = format ["Z%1_%2_%3", (getPlayerUID player) select [((count getPlayerUID player) - 6) max 0], round (diag_tickTime * 10) mod 1000000, floor random 1000];
        private _used = call _usedNames;
        private _code = ((_themes get "NATO") select { !(_x in _used) }) param [0, "ALPHA"];
        if (_name isEqualTo "") then { _name = _pre + " " + _code; };
        private _gp = createHashMapFromArray [
            ["cut", "GRID"], ["size", 200], ["fmt", "GRG"], ["snap", true], ["sec", 8], ["rings", 1], ["secFmt", "CARD"], ["minA", 2500],
            ["tracks", false], ["prefix", _code select [0, 1]], ["theme", profileNamespace getVariable ["COMSPEC_ATAK_ZnNameTheme", "MIL"]],
            ["milSub", profileNamespace getVariable ["COMSPEC_ATAK_ZnNameSub", "CITY"]], ["asr", 3], ["axMin", 300], ["setPre", "OBJ"], ["setTheme", "NATO"], ["radius", 300]
        ];
        // 2.9.38 : plan partagé d'office (réglage serveur, le camp par défaut) et sur la carte d'Arma, renvoyé à chaque
        // modification validée ; l'état des zones, lui, ne change toujours que par un joueur.
        private _dsc = ((missionNamespace getVariable ["comspec_atak_native_plan_scope", 3]) max 0) min 3;
        if !(missionNamespace getVariable ["comspec_atak_native_zones_share", true]) then { _dsc = 0; };
        private _b = createHashMapFromArray [
            ["id", _id], ["name", [_name, 24] call _clean], ["owner", [player] call comspec_atak_native_fnc_unitCallsign], ["uid", getPlayerUID player],
            ["rev", 1], ["scope", _dsc], ["live", _dsc > 0], ["vis", true], ["fence", false], ["alert", false], ["arma", (missionNamespace getVariable ["comspec_atak_native_zones_arma", true]) && {_dsc > 0}], ["render", "FILL"], ["aff", "GEN"], ["area", []], ["gp", _gp],
            ["zones", []], ["axes", []], ["cps", []], ["lines", []], ["st", call _stDef], ["from", ""], ["side", str side group player], ["grp", netId group player], ["ft", ""], ["mk", []]
        ];
        _mine set [_id, _b];
        call _save; call _bump; call _refresh;
        _id
    };
    // Nom de code automatique : préfixe (OBJ, AO…) et prochain nom libre du thème choisi ; préfixe des zones = initiale.
    case "autoName": {
        private _b = _mine getOrDefault [_arg, createHashMap];
        if ((count _b) isEqualTo 0) exitWith { "" };
        private _gp = _b get "gp";
        private _used = call _usedNames;
        private _list = [_gp getOrDefault ["setTheme", "NATO"], _gp getOrDefault ["milSub", "CITY"]] call _themeList;
        private _cur = ((_b get "name") splitString " ") param [1, ""];
        private _i = ((_list find _cur) + 1) max 0;
        [_b] call _snap;
        private _code = "";
        for "_k" from 0 to ((count _list) - 1) do {
            private _c = _list select ((_i + _k) mod (count _list));
            if !(_c in _used) exitWith { _code = _c; };
        };
        if (_code isEqualTo "") then { _code = _list select (_i mod (count _list)); };
        _b set ["name", format ["%1 %2", _gp getOrDefault ["setPre", "OBJ"], _code]];
        _gp set ["prefix", _code select [0, 1]];
        [_b, false] call _changed;
        _b get "name"
    };
    case "axisNames": {
        _arg params ["_id", "_n"];
        private _b = [_id] call _get;
        private _gp = _b getOrDefault ["gp", createHashMap];
        private _list = [_gp getOrDefault ["theme", "CITY"], _gp getOrDefault ["milSub", "CITY"]] call _themeList;
        private _used = call _usedNames;
        // Axes déjà nommés de ce plan : leurs noms resservent (nouveau calcul).
        { _used = _used - [([_x select 1] call _split) select 1]; } forEach (_b getOrDefault ["axes", []]);
        [_list, _used, _n] call _nextNames
    };
    // Prochain nom de code libre du thème du joueur (route ou trait nommés sans passer par un plan).
    case "nextCode": {
        private _list = [profileNamespace getVariable ["COMSPEC_ATAK_ZnNameTheme", "MIL"], profileNamespace getVariable ["COMSPEC_ATAK_ZnNameSub", "CITY"]] call _themeList;
        ([_list, call _usedNames, 1] call _nextNames) param [0, ""]
    };
    case "abbrOf": { _abbrType getOrDefault [_arg, "AA"] };
    case "themeRows": { _themeRows };
    case "themeLabel": { (((_themeRows select { (_x select 0) isEqualTo _arg }) param [0, ["", _arg]]) select 1) call _tr };
    case "abbrs": { _abbrs };
    case "split": { [_arg] call _split };
    // Thème de nommage d'un plan, et préférence du joueur pour ses prochains plans : [id, thème, sous-thème MIL].
    case "nameTheme": {
        _arg params ["_id", "_k", ["_sub", ""]];
        if ((_themeRows findIf { (_x select 0) isEqualTo _k }) < 0) exitWith { false };
        if (_sub isEqualTo "MIL" || {_sub isNotEqualTo "" && {(_themeRows findIf { (_x select 0) isEqualTo _sub }) < 0}}) then { _sub = ""; };
        profileNamespace setVariable ["COMSPEC_ATAK_ZnNameTheme", _k];
        if (_sub isNotEqualTo "") then { profileNamespace setVariable ["COMSPEC_ATAK_ZnNameSub", _sub]; };
        [] call comspec_atak_native_fnc_saveProfile;
        private _b = _mine getOrDefault [_id, createHashMap];
        if ((count _b) > 0) then {
            (_b get "gp") set ["theme", _k];
            if (_sub isNotEqualTo "") then { (_b get "gp") set ["milSub", _sub]; };
            call _save; call _refresh;
        };
        true
    };
    // Noms du thème du plan appliqués : [id, AXES | LINES | ZONES | CPS | ALL]. Axes : MSR / ASR (MIL : abréviation de
    // chacun gardée) ; traits et zones typées (les cases de grille gardent leur nom) : nom seul ; MIL : abréviation selon
    // le genre (trait PL, contour AA, zone selon son type) ; MIL, points de contrôle : CP1, RP2… Jamais deux fois le
    // même nom dans le plan ni un nom pris par un autre plan. Renvoie le nombre d'éléments renommés.
    case "nameApply": {
        _arg params ["_id", ["_what", "ALL"]];
        private _b = _mine getOrDefault [_id, createHashMap];
        if ((count _b) isEqualTo 0) exitWith { 0 };
        private _gp = _b get "gp";
        private _th = _gp getOrDefault ["theme", "CITY"];
        private _mil = _th isEqualTo "MIL";
        private _list = [_th, _gp getOrDefault ["milSub", "CITY"]] call _themeList;
        [_b] call _snap;
        private _ren = [];
        if (_what in ["AXES", "ALL"]) then { { _ren pushBack ["axis", _x]; } forEach (_b get "axes"); };
        if (_what in ["LINES", "ALL"]) then { { _ren pushBack ["line", _x]; } forEach (_b getOrDefault ["lines", []]); };
        if (_what in ["ZONES", "ALL"]) then { { if ((_x select 4) isNotEqualTo "GEN") then { _ren pushBack ["zone", _x]; }; } forEach (_b get "zones"); };
        private _used = call _usedNames;
        private _olds = _ren apply { (_x select 1) select 1 };
        { _used = _used - [([_x] call _split) select 1]; } forEach _olds;
        // Anciens noms retirés d'abord : un élément ne prend pas « 2 » à cause de l'ancien nom d'un autre renommé ici.
        { (_x select 1) set [1, ""]; } forEach _ren;
        private _names = [_list, _used, count _ren] call _nextNames;
        {
            _x params ["_k", "_e"];
            private _old = _olds select _forEachIndex;
            private _ab = ([_old] call _split) select 0;
            private _nm = _names param [_forEachIndex, str (_forEachIndex + 1)];
            private _full = switch (_k) do {
                case "axis": { format ["%1 %2", [_e select 2, _ab] select (_mil && {_ab isNotEqualTo ""}), _nm] };
                case "line": { [_nm, format ["%1 %2", [["PL", "AA"] select (_e select 3), _ab] select (_ab isNotEqualTo ""), _nm]] select _mil };
                default { [_nm, format ["%1 %2", [_abbrType getOrDefault [_e select 4, "AA"], _ab] select (_ab isNotEqualTo ""), _nm]] select _mil };
            };
            private _max = [16, 24] select (_k isEqualTo "axis");
            _full = [_b, (toUpper _full) select [0, _max], _max, [_k, _e select 0]] call _uniq;
            _e set [1, _full];
            if (_k isEqualTo "axis") then { { if ((_x select 3) isEqualTo _old) then { _x set [3, _full]; }; } forEach (_b get "cps"); };
        } forEach _ren;
        private _nCp = 0;
        if (_mil && {_what in ["CPS", "ALL"]}) then {
            {
                private _ab = (_x select 1) regexReplace ["[0-9]+$", ""];
                if !(_ab in (_abbrs get "cp")) then { _ab = "CP"; };
                _x set [1, format ["%1%2", _ab, _x select 0]];
                _nCp = _nCp + 1;
            } forEach (_b get "cps");
        };
        [_b, false] call _changed;
        ["INFO", "ZONES", format ["Nommage %1 (%2) du plan %3 : %4 élément(s)", _th, _what, _b get "name", (count _ren) + _nCp]] call comspec_atak_native_fnc_log;
        (count _ren) + _nCp
    };
    // Abréviation suivante d'un élément (modifiable par élément) : [id, axis | line | zone | cp, id de l'élément (texte)].
    // Après la dernière : sans abréviation (sauf axes et points de contrôle). Renvoie le nouveau nom.
    case "abbrNext": {
        _arg params ["_id", "_k", "_eid"];
        private _b = _mine getOrDefault [_id, createHashMap];
        if ((count _b) isEqualTo 0) exitWith { "" };
        private _arr = switch (_k) do { case "axis": { _b get "axes" }; case "line": { _b getOrDefault ["lines", []] }; case "cp": { _b get "cps" }; default { _b get "zones" }; };
        private _i = _arr findIf { ([_x select 0, str (_x select 0)] select ((_x select 0) isEqualType 0)) isEqualTo _eid };
        if (_i < 0) exitWith { "" };
        private _e = _arr select _i;
        private _old = _e select 1;
        [_b] call _snap;
        if (_k isEqualTo "cp") then {
            private _list = _abbrs get "cp";
            private _ab = _old regexReplace ["[0-9]+$", ""];
            _e set [1, format ["%1%2", _list select (((_list find _ab) + 1) mod (count _list)), _e select 0]];
        } else {
            ([_old] call _split) params ["_ab", "_rest"];
            private _list = switch (_k) do {
                case "axis": { _abbrs get (["axis", "axisMain"] select ((_e select 2) isEqualTo "MSR")) };
                case "line": { _abbrs get (["line", "area"] select (_e select 3)) };
                default { _abbrs get "area" };
            };
            private _j = _list find _ab;
            private _nx = switch (true) do {
                case (_ab isEqualTo ""): { _list select 0 };
                case ((_j + 1) >= (count _list)): { ["", _list select 0] select (_k isEqualTo "axis") };
                default { _list select (_j + 1) };
            };
            private _max = [16, 24] select (_k isEqualTo "axis");
            private _full = (toUpper ([_rest, format ["%1 %2", _nx, _rest]] select (_nx isNotEqualTo ""))) select [0, _max];
            _full = [_b, _full, _max, [_k, _eid]] call _uniq;
            _e set [1, _full];
            if (_k isEqualTo "axis") then { { if ((_x select 3) isEqualTo _old) then { _x set [3, _full]; }; } forEach (_b get "cps"); };
        };
        [_b, false] call _changed;
        _e select 1
    };
    case "del": {
        private _b = [_arg] call _get;
        if ((count _b) isEqualTo 0) exitWith { false };
        { ["del", _x] call comspec_atak_native_fnc_markerOwned; } forEach (_b getOrDefault ["mk", []]);
        _b set ["mk", []]; _b set ["mkz", createHashMap]; _b set ["mkTok", (_b getOrDefault ["mkTok", 0]) + 1];
        _b set ["arma", false];
        if (_arg in _mine) then {
            if ((_b get "scope") > 0) then { ["unshare", _arg] call comspec_atak_native_fnc_zoneAction; };
            _mine deleteAt _arg;
            call _save;
        } else {
            // Plan reçu retiré ici : plus jamais repris (rejeu du serveur, mise à jour de l'auteur), pour la partie.
            (missionNamespace getVariable ["COMSPEC_ATAK_ZnGone", createHashMap]) set [_arg, 1e9];
            missionNamespace setVariable ["COMSPEC_ATAK_ZnGone", missionNamespace getVariable ["COMSPEC_ATAK_ZnGone", createHashMap]];
            _rx deleteAt _arg;
        };
        call _bump; call _refresh;
        true
    };
    case "set": {
        _arg params ["_id", "_key", "_v"];
        private _b = _mine getOrDefault [_id, createHashMap];
        // Plan reçu : seul l'affichage se règle ici.
        if ((count _b) isEqualTo 0) exitWith {
            private _o = _rx getOrDefault [_id, createHashMap];
            if ((count _o) > 0 && {_key in ["vis", "render"]}) then { _o set [_key, _v]; call _bump; call _refresh; };
            false
        };
        if (_key isEqualTo "name") then { _v = [_v, 24] call _clean; if (_v isEqualTo "") then { _v = _b get "name"; }; };
        if ((_b getOrDefault [_key, ""]) isEqualTo _v) exitWith { false };
        if (_key in ["name", "render", "aff"]) then { [_b] call _snap; };
        _b set [_key, _v];
        switch (_key) do {
            case "vis": { call _save; call _bump; call _refresh; };
            case "live": { call _save; };
            // Type par défaut : appliqué à toutes les zones.
            case "aff": { { _x set [4, _v]; } forEach (_b get "zones"); [_b] call _changed; };
            case "render": { [_b] call _changed; };
            case "arma": {
                if (_v && {!(missionNamespace getVariable ["comspec_atak_native_zones_arma", true])}) exitWith {
                    _b set ["arma", false];
                    ["WARNING", "Zones sur la carte d'Arma désactivées par le serveur", 3, 20] call comspec_atak_native_fnc_notify;
                };
                ["armaNow", _id] call comspec_atak_native_fnc_zoneAction;
                call _save; call _refresh;
            };
            default { [_b, false] call _changed; };
        };
        true
    };
    // Réglages du découpage (gardés dans le plan, sans envoi).
    case "gp": {
        _arg params ["_id", "_key", "_v"];
        private _b = _mine getOrDefault [_id, createHashMap];
        if ((count _b) isEqualTo 0) exitWith { false };
        if (_key isEqualTo "prefix") then { _v = toUpper ([_v, 6] call _clean); };
        (_b get "gp") set [_key, _v];
        call _save; call _refresh;
        true
    };
    // Zone de travail : [id, RECT | POLY | CIRCLE, données].
    case "areaSet": {
        _arg params ["_id", "_kind", "_data"];
        private _b = _mine getOrDefault [_id, createHashMap];
        if ((count _b) isEqualTo 0) exitWith { false };
        [_b] call _snap;
        _b set ["area", [_kind, _data]];
        [_b, true, false] call _changed;
        true
    };
    // Zone de travail en polygone : [points, genre, centre, rayon].
    case "areaPoly": {
        private _b = if (_arg isEqualType "") then { [_arg] call _get } else { _arg };
        (_b getOrDefault ["area", []]) params [["_k", ""], ["_d", []]];
        switch (_k) do {
            case "RECT": {
                _d params [["_p", [0, 0]], ["_q", [0, 0]]];
                private _x0 = (_p select 0) min (_q select 0); private _x1 = (_p select 0) max (_q select 0);
                private _y0 = (_p select 1) min (_q select 1); private _y1 = (_p select 1) max (_q select 1);
                [[[_x0, _y0], [_x1, _y0], [_x1, _y1], [_x0, _y1]], "RECT", [(_x0 + _x1) / 2, (_y0 + _y1) / 2], ((_x1 - _x0) max (_y1 - _y0)) / 2]
            };
            case "CIRCLE": {
                _d params [["_c", [0, 0]], ["_r", 100]];
                [["circle", [_c, _r, 48]] call comspec_atak_native_fnc_zoneGeom, "CIRCLE", _c, _r]
            };
            case "POLY": { [_d, "POLY", ["centroid", [_d]] call comspec_atak_native_fnc_zoneGeom, 0] };
            default { [[], "", [0, 0], 0] };
        }
    };
    // Menu clic droit de la carte, sous-menu Zones : [genre G100 | G200 | G500 | BLOCK | RADIAL | AXES | NEW, point].
    // Nouveau plan, zone de travail en cercle autour du point (rayon choisi dans le menu), puis le calcul demandé.
    case "ctxGen": {
        _arg params [["_kind", ""], ["_pos", []], ["_bid", ""]];
        if ((count _pos) < 2) exitWith { "" };
        // Contour / trait aimanté à partir du point : sur le plan sous le clic s'il est à moi, sinon un nouveau plan ;
        // l'app Zones s'ouvre sur l'outil POLYGONE, premier point posé.
        if (_kind in ["MAGC", "MAGO"]) exitWith {
            private _id = [_bid, ""] select !(_bid in _mine);
            // Plan de travail : retiré tout seul s'il reste vide (tracé abandonné, fn_zoneAction "pruneAuto").
            if (_id isEqualTo "") then {
                _id = ["new", ["", "TRAITS"]] call comspec_atak_native_fnc_zoneAction;
                if (_id isNotEqualTo "") then { (_mine get _id) set ["auto", "DRAW"]; };
            };
            if (_id isEqualTo "") exitWith { "" };
            ["#ui", ["open", _id]] call comspec_atak_native_fnc_pageZones;
            private _u = uiNamespace getVariable ["COMSPEC_ATAK_ZnUi", createHashMap];
            private _open = _kind isEqualTo "MAGO";
            private _s = ["at", [[_pos select 0, _pos select 1], _u getOrDefault ["polyR", 30]]] call comspec_atak_native_fnc_zoneSnap;
            { _u set _x; } forEach [["tool", "POLY"], ["polyMag", true], ["polyOpen", _open], ["polyFor", [["LINE", "AREA"] select ((count ((_mine get _id) get "area")) isEqualTo 0), "LINE"] select _open],
                ["draftPts", [_s param [0, [_pos select 0, _pos select 1]]]], ["ztab", "DRAW"]];
            uiNamespace setVariable ["COMSPEC_ATAK_ZnView", [0.02, [_pos select 0, _pos select 1]]];
            ["ZONES"] call comspec_atak_native_fnc_navigate;
            _id
        };
        private _r = ((profileNamespace getVariable ["COMSPEC_ATAK_ZnCtxRad", 300]) max 50) min 5000;
        // Le plan porte le nom de ce qui est demandé (jamais « OBJ » d'office) ; axes redemandés au même endroit : même plan,
        // recalculé (avant : un plan et un nom de code de plus à chaque fois, traits doublés sur la même route).
        private _id = "";
        if (_kind isEqualTo "AXES") then {
            {
                (_y getOrDefault ["area", []]) params [["_ak", ""], ["_ad", []]];
                if ((_y getOrDefault ["auto", ""]) isEqualTo "AXES" && {_ak isEqualTo "CIRCLE"} && {(((_ad select 0) + [0]) distance2D [_pos select 0, _pos select 1, 0]) < (_ad select 1)}) exitWith { _id = _x; };
            } forEach _mine;
        };
        if (_id isEqualTo "") then {
            _id = ["new", ["", switch (true) do {
                case (_kind isEqualTo "AXES"): { "AXES" };
                case (_kind in ["G100", "G200", "G500"]): { "GRG" };
                case (_kind isEqualTo "NEW"): { "PLAN" };
                default { "ZONES" };
            }]] call comspec_atak_native_fnc_zoneAction;
            if (_id isNotEqualTo "" && {_kind isNotEqualTo "NEW"}) then { (_mine get _id) set ["auto", ["GEN", "AXES"] select (_kind isEqualTo "AXES")]; };
        };
        if (_id isEqualTo "") exitWith { "" };
        private _gp = (_mine get _id) get "gp";
        _gp set ["radius", _r];
        switch (_kind) do {
            case "G100": { _gp set ["cut", "GRID"]; _gp set ["size", 100]; };
            case "G200": { _gp set ["cut", "GRID"]; _gp set ["size", 200]; };
            case "G500": { _gp set ["cut", "GRID"]; _gp set ["size", 500]; };
            case "BLOCK": { _gp set ["cut", "BLOCK"]; };
            case "RADIAL": { _gp set ["cut", "RADIAL"]; };
        };
        ["areaSet", [_id, "CIRCLE", [[_pos select 0, _pos select 1], _r]]] call comspec_atak_native_fnc_zoneAction;
        switch (true) do {
            case (_kind isEqualTo "AXES"): { ["axes", [_id]] call comspec_atak_native_fnc_zoneAction; };
            case (_kind isEqualTo "NEW"): {};
            default { ["gen", [_id]] call comspec_atak_native_fnc_zoneAction; };
        };
        if (_kind isNotEqualTo "NEW") then { ["INFO", format [("%1 : calcul autour du point (%2 m), le résultat s'affichera ici") call _tr, (_mine get _id) get "name", _r], 4, 20] call comspec_atak_native_fnc_notify; };
        // Nouveau plan à partir d'ici : l'app Zones propose tout de suite DÉCOUPER ou TRACER (consigne de la carte, 2.9.41).
        if (_kind isEqualTo "NEW") then {
            ["#ui", ["open", _id]] call comspec_atak_native_fnc_pageZones;
            (uiNamespace getVariable ["COMSPEC_ATAK_ZnUi", createHashMap]) set ["next", "AREA"];
            ["ZONES"] call comspec_atak_native_fnc_navigate;
        };
        _id
    };
    // Calculs en arrière-plan (fn_zoneGen) : découpage, axes, bâtiments.
    case "gen";
    case "axes";
    case "bld": {
        _arg params ["_id", ["_zid", ""]];
        private _b = _mine getOrDefault [_id, createHashMap];
        if ((count _b) isEqualTo 0) exitWith { false };
        if ((count (_b getOrDefault ["area", []])) isEqualTo 0 && {_act isNotEqualTo "bld"}) exitWith { ["INFO", "Tracez d'abord la zone de travail sur la carte de l'app", 3, 10] call comspec_atak_native_fnc_notify; false };
        private _tok = (_b getOrDefault ["genTok", 0]) + 1;
        _b set ["genTok", _tok];
        _b set ["busy", toUpper _act];
        call _refresh;
        [["ZONES", "AXES", "BLD"] select (["gen", "axes", "bld"] find _act), _id, _tok, _zid] spawn comspec_atak_native_fnc_zoneGen;
        // Garde-fou : calcul arrêté par une erreur (plus de réponse au bout de 90 s) : le plan n'attend plus.
        [{
            params ["_id", "_tok"];
            private _b = ["get", _id] call comspec_atak_native_fnc_zoneAction;
            if ((_b getOrDefault ["genTok", -1]) isEqualTo _tok && {(_b getOrDefault ["busy", ""]) isNotEqualTo ""}) then {
                _b set ["busy", ""];
                ["WARNING", "Calcul des zones interrompu : réessayez (zone plus petite, cases plus grandes)", 5, 20] call comspec_atak_native_fnc_notify;
                ["WARNING", "ZONES", format ["Calcul sans réponse au bout de 90 s (plan %1)", _id]] call comspec_atak_native_fnc_log;
                ["pruneAuto", _id] call comspec_atak_native_fnc_zoneAction;
                ["#refresh"] call comspec_atak_native_fnc_pageZones;
            };
        }, [_id, _tok], 90] call comspec_atak_native_fnc_waitAndExec;
        true
    };
    // ANNULER LE CALCUL : le résultat du calcul en cours sera jeté (jeton changé), rien ne change.
    case "genCancel": {
        private _b = _mine getOrDefault [_arg, createHashMap];
        if ((count _b) isEqualTo 0 || {(_b getOrDefault ["busy", ""]) isEqualTo ""}) exitWith { false };
        _b set ["genTok", (_b getOrDefault ["genTok", 0]) + 1];
        _b set ["busy", ""];
        ["INFO", "Calcul annulé : rien n'a changé", 2, 10] call comspec_atak_native_fnc_notify;
        ["pruneAuto", _arg] call comspec_atak_native_fnc_zoneAction;
        call _refresh;
        true
    };
    // Plan créé par un raccourci de la carte et resté sans rien (calcul sans résultat, tracé abandonné) : retiré, aucune
    // étiquette orpheline. Un plan créé à la main (NOUVEAU PLAN) n'est jamais retiré ainsi. -> vrai si retiré.
    case "pruneAuto": {
        private _b = _mine getOrDefault [_arg, createHashMap];
        if ((count _b) isEqualTo 0 || {(_b getOrDefault ["auto", ""]) isEqualTo ""} || {(_b getOrDefault ["busy", ""]) isNotEqualTo ""} || {[_b] call _hasContent}) exitWith { false };
        if ([_arg] call _editing) then { _ed = createHashMap; missionNamespace setVariable ["COMSPEC_ATAK_ZnEdit", _ed]; };
        { ["del", _x] call comspec_atak_native_fnc_markerOwned; } forEach (_b getOrDefault ["mk", []]);
        _mine deleteAt _arg;
        call _save; call _bump; call _refresh;
        ["INFO", "ZONES", format ["Plan vide %1 (%2) retiré", _arg, _b get "name"]] call comspec_atak_native_fnc_log;
        true
    };
    // Nettoyage en une fois : ["count"] -> [plans vides, plans OBJ automatiques, tous mes plans] ; [EMPTY | OBJ | ALL] -> ids
    // mis à la corbeille (8 s, « untrashAll »). OBJ : noms du générateur des anciennes versions (OBJ + alphabet OTAN), sans zone.
    case "cleanup": {
        private _isEmpty = { !([_this] call _hasContent) };
        private _isObj = { private _w = (_this get "name") splitString " "; (_w param [0, ""]) isEqualTo "OBJ" && {(_w param [1, ""]) in (_themes get "NATO")} && {(count (_this get "zones")) isEqualTo 0} };
        private _all = values _mine;
        if (_arg isEqualTo "count") exitWith { [{ _x call _isEmpty } count _all, { _x call _isObj } count _all, count _all] };
        private _pick = switch (_arg) do {
            case "EMPTY": { _all select { _x call _isEmpty } };
            case "OBJ": { _all select { _x call _isObj } };
            case "ALL": { _all };
            default { [] };
        };
        private _ids = _pick apply { _x get "id" };
        { ["trash", _x] call comspec_atak_native_fnc_zoneAction; } forEach _ids;
        _ids
    };
    case "untrashAll": { { ["untrash", _x] call comspec_atak_native_fnc_zoneAction; } forEach _arg; true };
    case "genDone": {
        _arg params ["_id", "_tok", "_zones", ["_msg", ""]];
        private _b = _mine getOrDefault [_id, createHashMap];
        if ((count _b) isEqualTo 0 || {(_b getOrDefault ["genTok", -1]) isNotEqualTo _tok}) exitWith {};
        _b set ["busy", ""];
        if ((count _zones) > 0) then {
            [_b] call _snap;
            _b set ["zones", _zones];
            [_b] call _changed;
            ["SUCCESS", format ["%1 : %2 zone(s) nommée(s) et numérotée(s)", _b get "name", count _zones], 3, 20] call comspec_atak_native_fnc_notify;
        } else {
            if (_msg isEqualTo "") then { _msg = "Aucune zone : zone de travail trop petite pour ce découpage"; };
            ["pruneAuto", _id] call comspec_atak_native_fnc_zoneAction;
            call _refresh;
        };
        if (_msg isNotEqualTo "") then { ["WARNING", _msg, 5, 20] call comspec_atak_native_fnc_notify; };
    };
    case "axesDone": {
        _arg params ["_id", "_tok", "_axes", ["_cps", []], ["_msg", ""]];
        private _b = _mine getOrDefault [_id, createHashMap];
        if ((count _b) isEqualTo 0 || {(_b getOrDefault ["genTok", -1]) isNotEqualTo _tok}) exitWith {};
        _b set ["busy", ""];
        if ((count _axes) > 0) then {
            [_b] call _snap;
            _b set ["axes", _axes];
            _b set ["cps", _cps];
            [_b] call _changed;
            ["SUCCESS", format ["%1 : %2 (%3 point(s) de contrôle)", _b get "name", (_axes apply { _x select 1 }) joinString ", ", count _cps], 5, 20] call comspec_atak_native_fnc_notify;
        } else {
            if (_msg isEqualTo "") then { _msg = "Aucun axe trouvé ici : élargissez le rayon"; };
            ["pruneAuto", _id] call comspec_atak_native_fnc_zoneAction;
            call _refresh;
        };
        if (_msg isNotEqualTo "") then { ["WARNING", _msg, 5, 20] call comspec_atak_native_fnc_notify; };
    };
    case "bldDone": {
        _arg params ["_id", "_tok", "_zid", "_list"];
        private _b = _mine getOrDefault [_id, createHashMap];
        if ((count _b) isEqualTo 0 || {(_b getOrDefault ["genTok", -1]) isNotEqualTo _tok}) exitWith {};
        _b set ["busy", ""];
        private _z = [_b, _zid] call _zoneOf;
        if ((count _z) isEqualTo 0) exitWith { call _refresh; };
        [_b] call _snap;
        _z set [10, _list];
        [_b, false] call _changed;
        ["SUCCESS", format ["Zone %1 : %2 bâtiment(s) numéroté(s)", _z select 1, count _list], 3, 20] call comspec_atak_native_fnc_notify;
    };
    // Zone modifiée par l'auteur du plan : [id, id de zone, label | type | rm | del | bldClear, valeur].
    case "zoneSet": {
        _arg params ["_id", "_zid", "_key", ["_v", ""]];
        private _b = _mine getOrDefault [_id, createHashMap];
        if ((count _b) isEqualTo 0) exitWith { false };
        private _z = [_b, _zid] call _zoneOf;
        if ((count _z) isEqualTo 0) exitWith { false };
        [_b] call _snap;
        switch (_key) do {
            case "label": { _v = [_v, 16] call _clean; if (_v isNotEqualTo "") then { _z set [1, [_b, _v, 16, ["zone", _zid]] call _uniq]; }; };
            case "type": { if ((_types findIf { (_x select 0) isEqualTo _v }) >= 0) then { _z set [4, _v]; }; };
            case "rm": { _z set [5, [_v, ""] select !(_v in ["FILL", "LINE", "POINT"])]; };
            case "bldClear": { _z set [10, []]; };
            case "del": { (_b get "zones") deleteAt ((_b get "zones") findIf { (_x select 0) isEqualTo _zid }); };
        };
        [_b, _key in ["del", "type", "rm"]] call _changed;
        true
    };
    // Zone retirée hors de l'édition (menu du clic droit de l'app Carte) : [id, id de zone] -> [index, zone] pour
    // « zoneRestore » ([id, index, zone]), le temps du bandeau ANNULER.
    case "zoneCut": {
        _arg params ["_id", "_zid"];
        private _b = _mine getOrDefault [_id, createHashMap];
        if ((count _b) isEqualTo 0) exitWith { [] };
        private _i = (_b get "zones") findIf { (_x select 0) isEqualTo _zid };
        if (_i < 0) exitWith { [] };
        [_b] call _snap;
        private _z = (_b get "zones") deleteAt _i;
        [_b] call _changed;
        [_i, _z]
    };
    case "zoneRestore": {
        _arg params ["_id", "_i", "_z"];
        private _b = _mine getOrDefault [_id, createHashMap];
        if ((count _b) isEqualTo 0 || {((_b get "zones") findIf { (_x select 0) isEqualTo (_z select 0) }) >= 0}) exitWith { false };
        (_b get "zones") insert [(_i max 0) min (count (_b get "zones")), [_z]];
        [_b] call _changed;
        true
    };
    case "axisSet": {
        _arg params ["_id", "_aid", "_key", ["_v", ""]];
        private _b = _mine getOrDefault [_id, createHashMap];
        if ((count _b) isEqualTo 0) exitWith { false };
        private _ax = _b get "axes";
        private _i = _ax findIf { (_x select 0) isEqualTo _aid };
        if (_i < 0) exitWith { false };
        [_b] call _snap;
        switch (_key) do {
            case "name": { _v = toUpper ([_v, 24] call _clean); if (_v isNotEqualTo "") then { _v = [_b, _v, 24, ["axis", _aid]] call _uniq; private _old = (_ax select _i) select 1; (_ax select _i) set [1, _v]; { if ((_x select 3) isEqualTo _old) then { _x set [3, _v]; }; } forEach (_b get "cps"); }; };
            case "rm": { (_ax select _i) set [5, [_v, ""] select !(_v in ["FILL", "LINE", "POINT"])]; };
            case "del": { private _n = (_ax select _i) select 1; _ax deleteAt _i; _b set ["cps", (_b get "cps") select { (_x select 3) isNotEqualTo _n }]; };
        };
        [_b, _key isEqualTo "del"] call _changed;
        true
    };
    // Axe ou trait retiré depuis le menu de la carte : [id, AXIS | LINE | CP, élément] -> état pour « elemRestore ».
    case "elemCut": {
        _arg params ["_id", "_k", "_el"];
        private _b = _mine getOrDefault [_id, createHashMap];
        if ((count _b) isEqualTo 0) exitWith { [] };
        private _key = switch (_k) do { case "AXIS": { "axes" }; case "LINE": { "lines" }; default { "cps" }; };
        private _arr = _b getOrDefault [_key, []];
        private _i = _arr findIf { (str (_x select 0)) isEqualTo (str _el) };
        if (_i < 0) exitWith { [] };
        [_b] call _snap;
        private _e = _arr deleteAt _i;
        private _cps = [];
        if (_k isEqualTo "AXIS") then {
            _cps = (_b get "cps") select { (_x select 3) isEqualTo (_e select 1) };
            _b set ["cps", (_b get "cps") select { (_x select 3) isNotEqualTo (_e select 1) }];
        };
        [_b] call _changed;
        [_key, _i, _e, _cps]
    };
    case "elemRestore": {
        _arg params ["_id", "_key", "_i", "_e", ["_cps", []]];
        private _b = _mine getOrDefault [_id, createHashMap];
        if ((count _b) isEqualTo 0) exitWith { false };
        private _arr = _b getOrDefault [_key, []];
        _arr insert [(_i max 0) min (count _arr), [_e]];
        _b set [_key, _arr];
        (_b get "cps") append _cps;
        [_b] call _changed;
        true
    };
    // Élément tracé sous un point de l'écran (clic droit de l'app Carte) : [carte, point écran] -> [[genre, plan, élément, nom]...]
    //   AXIS, LINE, CP (mes plans ou reçus) ; PLAN : plan sans zone dont la zone de travail contient le point.
    case "elemAt": {
        _arg params ["_map", "_sp"];
        private _out = [];
        private _px = pixelW * 10;
        private _segNear = {
            params ["_pts"];
            private _scr = _pts apply { _map ctrlMapWorldToScreen [_x select 0, _x select 1] };
            private _hit = false;
            for "_i" from 1 to ((count _scr) - 1) do {
                private _a = _scr select (_i - 1); private _c = _scr select _i;
                private _ab = [(_c select 0) - (_a select 0), (_c select 1) - (_a select 1)];
                private _l2 = ((_ab select 0) ^ 2) + ((_ab select 1) ^ 2);
                private _t = if (_l2 > 0) then { ((((_sp select 0) - (_a select 0)) * (_ab select 0) + ((_sp select 1) - (_a select 1)) * (_ab select 1)) / _l2) max 0 min 1 } else { 0 };
                if (([(_a select 0) + (_ab select 0) * _t, (_a select 1) + (_ab select 1) * _t] distance2D _sp) < _px) exitWith { _hit = true; };
            };
            _hit
        };
        private _w = _map ctrlMapScreenToWorld _sp;
        {
            private _b = _x;
            private _id = _b get "id";
            if (_b get "vis") then {
                { if ([_x select 3] call _segNear) then { _out pushBack ["AXIS", _id, _x select 0, _x select 1]; }; } forEach (_b get "axes");
                { if ([(_x select 2) + ([[], [(_x select 2) select 0]] select (_x select 3))] call _segNear) then { _out pushBack ["LINE", _id, _x select 0, _x select 1]; }; } forEach (_b getOrDefault ["lines", []]);
                { if (((_map ctrlMapWorldToScreen [(_x select 2) select 0, (_x select 2) select 1]) distance2D _sp) < _px) then { _out pushBack ["CP", _id, _x select 0, _x select 1]; }; } forEach (_b get "cps");
                if ((count (_b get "zones")) isEqualTo 0) then {
                    private _ap = ((["areaPoly", _b] call comspec_atak_native_fnc_zoneAction) select 0) apply { [_x select 0, _x select 1, 0] };
                    if ((count _out) > 0 && {((_out select -1) select 1) isEqualTo _id} || {(count _ap) >= 3 && {[_w select 0, _w select 1, 0] inPolygon _ap}}) then { _out pushBack ["PLAN", _id, "", _b get "name"]; };
                };
            };
        } forEach ((values _mine) + (values _rx));
        _out
    };
    // Marqueur natif retiré par quelqu'un d'autre (carte d'Arma, Athena) : [id, ZONE | AXIS | LINE | CP, élément].
    case "goneEl": {
        _arg params ["_id", "_kind", "_el"];
        if !(_id in _mine) exitWith {};
        if (_kind isEqualTo "ZONE") exitWith { ["zoneCut", [_id, _el]] call comspec_atak_native_fnc_zoneAction; };
        ["elemCut", [_id, _kind, _el]] call comspec_atak_native_fnc_zoneAction;
    };
    // Axe repris en route nommée de l'app Routes (véhicule, par les routes) : départ, ses points de contrôle, arrivée.
    case "axisRoute": {
        _arg params ["_id", "_aid"];
        private _b = [_id] call _get;
        private _a = (_b getOrDefault ["axes", []]) param [(_b getOrDefault ["axes", []]) findIf { (_x select 0) isEqualTo _aid }, []];
        if ((count _a) < 4 || {(count (_a select 3)) < 2}) exitWith { "" };
        _a params ["", "_name", "_cls", "_pts"];
        private _rid = ["new", ["VEH", _name]] call comspec_atak_native_fnc_routeAction;
        if (_rid isEqualTo "") exitWith { "" };
        ["set", [_rid, "color", ["ColorGreen", "ColorBlue"] select (_cls isEqualTo "MSR")]] call comspec_atak_native_fnc_routeAction;
        // Points de contrôle dans l'ordre de l'axe (sommet le plus proche).
        private _cps = ((_b getOrDefault ["cps", []]) select { (_x select 3) isEqualTo _name }) apply {
            private _p = _x select 2;
            private _bi = 0; private _bd = 1e9;
            { private _d = _p distance2D _x; if (_d < _bd) then { _bd = _d; _bi = _forEachIndex; }; } forEach _pts;
            [_bi, _x]
        };
        _cps sort true;
        ["stepAdd", [_rid, (_pts select 0) + [0], -1, "", "SP"]] call comspec_atak_native_fnc_routeAction;
        { ["stepAdd", [_rid, ((_x select 1) select 2) + [0], -1, (_x select 1) select 1, "CP"]] call comspec_atak_native_fnc_routeAction; } forEach _cps;
        ["stepAdd", [_rid, (_pts select -1) + [0], -1, "", "END"]] call comspec_atak_native_fnc_routeAction;
        ["SUCCESS", format ["Route %1 créée dans l'app Routes (%2 point(s) de contrôle)", _name, count _cps], 4, 20] call comspec_atak_native_fnc_notify;
        _rid
    };
    // État donné depuis l'app (un clic) : appliqué ici tout de suite, envoyé dans 5 s ; « sitUndo » entre-temps le
    // retire sans rien envoyer. [id, id de zone, état].
    case "sitSoon": {
        _arg params ["_id", "_zid", ["_st", ""]];
        private _b = [_id] call _get;
        private _z = [_b, _zid] call _zoneOf;
        if ((count _z) isEqualTo 0 || {_st isEqualTo ""} || {(_z select 6) isEqualTo _st}) exitWith { false };
        private _q = missionNamespace getVariable ["COMSPEC_ATAK_ZnSitQ", createHashMap];
        missionNamespace setVariable ["COMSPEC_ATAK_ZnSitQ", _q];
        private _k = _id + "|" + _zid;
        // État d'avant gardé (le premier, si plusieurs clics se suivent) ; heure locale seulement, rien ne part.
        private _old = _q getOrDefault [_k, []];
        private _prev = if ((count _old) > 0) then { _old select 0 } else { [_z select 6, _z select 7, _z select 8] };
        private _tok = diag_tickTime;
        _q set [_k, [_prev, _st, _tok]];
        _z set [6, _st]; _z set [8, [player] call comspec_atak_native_fnc_unitCallsign];
        [_b, false, false] call _changed;
        [{
            params ["_id", "_zid", "_k", "_tok"];
            private _q = missionNamespace getVariable ["COMSPEC_ATAK_ZnSitQ", createHashMap];
            private _e = _q getOrDefault [_k, []];
            if ((_e param [2, -1]) isNotEqualTo _tok) exitWith {};
            _q deleteAt _k;
            ["sit", [_id, _zid, _e select 1, []]] call comspec_atak_native_fnc_zoneAction;
        }, [_id, _zid, _k, _tok], 5] call comspec_atak_native_fnc_waitAndExec;
        true
    };
    case "sitUndo": {
        _arg params ["_id", "_zid"];
        private _q = missionNamespace getVariable ["COMSPEC_ATAK_ZnSitQ", createHashMap];
        private _e = _q getOrDefault [_id + "|" + _zid, []];
        if ((count _e) isEqualTo 0) exitWith { false };
        _q deleteAt (_id + "|" + _zid);
        private _b = [_id] call _get;
        private _z = [_b, _zid] call _zoneOf;
        if ((count _z) isEqualTo 0) exitWith { false };
        (_e select 0) params ["_s0", "_t0", "_by0"];
        _z set [6, _s0]; _z set [7, _t0]; _z set [8, _by0];
        [_b, false, false] call _changed;
        true
    };
    // Situation donnée ici : [id, id de zone, état ("" : inchangé), signalement [nature, effectif, texte, position] ou []].
    case "sit": {
        _arg params ["_id", "_zid", ["_st", ""], ["_rep", []]];
        private _b = [_id] call _get;
        if ((count _b) isEqualTo 0) exitWith { false };
        private _z = [_b, _zid] call _zoneOf;
        if ((count _z) isEqualTo 0) exitWith { false };
        private _who = [player] call comspec_atak_native_fnc_unitCallsign;
        private _stamp = serverTime;
        private _reps = [];
        if ((count _rep) >= 3) then {
            _rep params [["_nat", "UNK"], ["_size", ""], ["_txt", ""], ["_pos", []]];
            if !(_pos isEqualType [] && {(count _pos) >= 2}) then { _pos = _z select 3; };
            _reps pushBack [format ["S%1_%2", (getPlayerUID player) select [((count getPlayerUID player) - 4) max 0], floor (diag_tickTime * 100) mod 10000000], _stamp,
                [dayTime, "HH:MM"] call BIS_fnc_timeToString, _who, _nat, [_size, 12] call _clean, [_txt, 120] call _clean, [round (_pos select 0), round (_pos select 1)]];
        };
        if (_st isEqualTo "" && {(count _reps) isEqualTo 0}) exitWith { false };
        // État en attente d'envoi (sitSoon) remplacé par celui-ci.
        (missionNamespace getVariable ["COMSPEC_ATAK_ZnSitQ", createHashMap]) deleteAt (_id + "|" + _zid);
        // Zone déjà passée localement à cet état (sitSoon) : l'heure la plus récente l'emporte, l'état est bien appliqué.
        if (_st isNotEqualTo "" && {(_z select 7) > _stamp}) then { _z set [7, _stamp]; };
        [_b, _zid, _st, _stamp, _who, _reps] call _apply;
        [_b, false, false] call _changed;
        private _shared = (_b getOrDefault ["scope", 0]) > 0;
        if (_shared && {missionNamespace getVariable ["comspec_atak_native_zones_share", true]}) then {
            private _to = [_b] call _aud;
            private _args = ["sit", _id, _zid, _st, _stamp, _who, _reps];
            private _res = [{
                params ["_args", "_to"];
                if ((count _to) > 0) then { ["comspec_atak_native_zone", _args, _to] call CBA_fnc_targetEvent; };
                ["comspec_atak_native_zoneSrv", _args] call CBA_fnc_serverEvent;
            }, [_args, _to], "Rapport de zone", 1] call comspec_atak_native_fnc_netSend;
            private _what = [((([_st] call _stateRow) select 1) call _tr), ("signalement") call _tr] select (_st isEqualTo "");
            if (_res isEqualTo "QUEUED") then {
                ["WARNING", format ["Zone %1 (%2) : %3 · pas de réseau, envoi au retour du signal", _z select 1, _b get "name", _what], 4, 20] call comspec_atak_native_fnc_notify;
            } else {
                ["SUCCESS", format ["Zone %1 (%2) : %3 · envoyé à %4 joueur(s)", _z select 1, _b get "name", _what, count _to], 3, 20] call comspec_atak_native_fnc_notify;
            };
        } else {
            [format ["Zone %1 : %2", _z select 1, ((([[_st] call _stateRow, ["", "Signalement noté"]] select (_st isEqualTo "")) select 1) call _tr)], 2] call comspec_atak_native_fnc_toast;
        };
        true
    };
    // Situation remise à zéro par l'auteur du plan (états « non reconnue », signalements effacés), partout.
    case "reset": {
        private _b = _mine getOrDefault [_arg, createHashMap];
        if ((count _b) isEqualTo 0) exitWith { false };
        { _x set [6, "UNREC"]; _x set [7, serverTime]; _x set [8, ""]; _x set [9, []]; } forEach (_b get "zones");
        [_b, false, false] call _changed;
        if ((_b get "scope") > 0) then {
            private _to = [_b] call _aud;
            [{
                params ["_id", "_to", "_t"];
                if ((count _to) > 0) then { ["comspec_atak_native_zone", ["reset", _id, _t], _to] call CBA_fnc_targetEvent; };
                ["comspec_atak_native_zoneSrv", ["reset", _id, _t]] call CBA_fnc_serverEvent;
            }, [_arg, _to, serverTime], "Rapport de zone", 1] call comspec_atak_native_fnc_netSend;
        };
        ["INFO", "Situation remise à zéro", 2, 10] call comspec_atak_native_fnc_notify;
        true
    };
    // Partage : [id, portée 1 équipe | 2 groupe | 3 camp, silencieux].
    case "share": {
        _arg params ["_id", ["_sc", 2], ["_quiet", false]];
        private _b = _mine getOrDefault [_id, createHashMap];
        if ((count _b) isEqualTo 0) exitWith { false };
        if !(missionNamespace getVariable ["comspec_atak_native_zones_share", true]) exitWith { ["WARNING", "Partage des zones désactivé par le serveur", 3, 20] call comspec_atak_native_fnc_notify; false };
        // Sans zone, axe ni trait : rien à envoyer (zone de travail seule : une étiquette orpheline chez les autres).
        if !([_b] call _hasContent) exitWith { if !(_quiet) then { ["INFO", "Plan vide : rien à envoyer", 3, 10] call comspec_atak_native_fnc_notify; }; false };
        if ((_b get "scope") isEqualTo 0) then { _b set ["rev", (_b getOrDefault ["rev", 0]) + 1]; _b deleteAt "geo"; };
        if ((_b get "scope") > _sc) then { ["unshare", _id] call comspec_atak_native_fnc_zoneAction; };
        private _sc0 = _b get "scope";
        _b set ["scope", _sc];
        _b set ["live", true];
        // Publié : aussi sur la carte d'Arma et Athena (réglage serveur), sur le canal de sa portée.
        if (_sc0 isEqualTo 0 && {missionNamespace getVariable ["comspec_atak_native_zones_arma", true]}) then { _b set ["arma", true]; };
        if ((_sc0 isEqualTo 0 || {(_sc0 >= 3) isNotEqualTo (_sc >= 3)}) && {!([_id] call _editing)} && {(_b get "arma") || {(count (_b getOrDefault ["mk", []])) > 0}}) then { ["armaNow", _id] call comspec_atak_native_fnc_zoneAction; };
        _b set ["side", str side group player];
        _b set ["grp", netId group player];
        _b set ["ft", [player getVariable ["COMSPEC_FT", ""], ""] select (_sc >= 2)];
        call _save;
        private _to = [_b] call _aud;
        private _pk = ["pack", _b] call comspec_atak_native_fnc_zoneAction;
        private _who = [player] call comspec_atak_native_fnc_unitCallsign;
        private _nPts = 0;
        { _nPts = _nPts + count (_x select 2); } forEach (_b get "zones");
        private _res = [{
            params ["_pk", "_to", "_who", "_sc", "_id", "_b"];
            if ((count _to) > 0) then { ["comspec_atak_native_zone", ["set", _pk, _who, _sc], _to] call CBA_fnc_targetEvent; };
            ["comspec_atak_native_zoneSrv", ["set", _id, _pk, _sc, _b get "side", _b get "grp", _b get "ft", _who]] call CBA_fnc_serverEvent;
        }, [_pk, _to, _who, _sc, _id, _b], "Plan de zones", 2 + (count (_b get "zones")) * 0.05 + _nPts * 0.02, 1, "zn:" + _id] call comspec_atak_native_fnc_netSend;
        if !(_quiet) then {
            switch (true) do {
                case (_res isEqualTo "QUEUED"): { ["WARNING", "Pas de réseau : le plan partira au retour du signal", 4, 20] call comspec_atak_native_fnc_notify; };
                case ((count _to) isEqualTo 0): { ["INFO", format ["Plan partagé (%1) : personne d'autre pour l'instant, il sera remis aux arrivants", ["scopeName", _sc] call comspec_atak_native_fnc_zoneAction], 4, 20] call comspec_atak_native_fnc_notify; };
                default { ["SUCCESS", format ["Plan envoyé à %1 joueur(s) (%2)", count _to, ["scopeName", _sc] call comspec_atak_native_fnc_zoneAction], 3, 20] call comspec_atak_native_fnc_notify; };
            };
        };
        call _refresh;
        true
    };
    case "unshare": {
        private _b = _mine getOrDefault [_arg, createHashMap];
        if ((count _b) isEqualTo 0) exitWith { false };
        private _to = [_b] call _aud;
        [{
            params ["_id", "_to", "_rev"];
            if ((count _to) > 0) then { ["comspec_atak_native_zone", ["del", _id, [player] call comspec_atak_native_fnc_unitCallsign, _rev], _to] call CBA_fnc_targetEvent; };
            ["comspec_atak_native_zoneSrv", ["del", _id]] call CBA_fnc_serverEvent;
        }, [_arg, _to, _b getOrDefault ["rev", 0]], "Plan de zones", 1, 1, "zn:" + _arg] call comspec_atak_native_fnc_netSend;
        _b set ["scope", 0];
        _b set ["live", false];
        // Redevenu privé : ses marqueurs publics ne restent pas chez les autres (plan supprimé : déjà retirés, arma coupé).
        if ((_b get "arma") || {(count (_b getOrDefault ["mk", []])) > 0}) then { ["armaNow", _arg] call comspec_atak_native_fnc_zoneAction; };
        call _save; call _refresh;
        true
    };
    // Réception (événement comspec_atak_native_zone) :
    //   ["set", plan (paires), auteur, portée] | ["del", id, auteur] | ["sit", id, zone, état, horodatage, auteur, signalements]
    //   | ["sitAll", même chose (rejeu serveur)] | ["reset", id, horodatage]
    case "recv": {
        private _op = _arg param [0, ""];
        private _quiet = missionNamespace getVariable ["COMSPEC_ATAK_Replaying", false];
        switch (_op) do {
            case "del": {
                _arg params ["", ["_id", ""], ["_who", ""], ["_rev", 0]];
                if !(_id isEqualType "") exitWith {};
                // Pierre tombale : une version plus ancienne (rejeu, file sans réseau) ne le fait pas revenir, même arrivée après.
                private _gone = missionNamespace getVariable ["COMSPEC_ATAK_ZnGone", createHashMap];
                missionNamespace setVariable ["COMSPEC_ATAK_ZnGone", _gone];
                _gone set [_id, ((_rx getOrDefault [_id, createHashMap]) getOrDefault ["rev", 0]) max ([0, _rev] select (_rev isEqualType 0)) max (_gone getOrDefault [_id, -1])];
                if !(_id in _rx) exitWith {};
                private _n = (_rx get _id) get "name";
                _rx deleteAt _id;
                if !(_quiet) then { ["INFO", format ["Plan de zones retiré par %1 : %2", _who, _n], 3, 20] call comspec_atak_native_fnc_notify; };
                call _bump; call _refresh;
            };
            case "set": {
                _arg params ["", ["_pk", []], ["_who", ""], ["_sc", 2]];
                private _b = [_pk] call comspec_atak_native_fnc_zoneUnpack;
                if ((count _b) isEqualTo 0) exitWith {};
                if ((_b get "uid") isEqualTo getPlayerUID player || {(_b get "id") in _mine}) exitWith {};
                private _id = _b get "id";
                private _old = _rx getOrDefault [_id, createHashMap];
                private _goneRev = (missionNamespace getVariable ["COMSPEC_ATAK_ZnGone", createHashMap]) getOrDefault [_id, -1];
                if ((_b get "rev") <= _goneRev) exitWith { ["INFO", "ZONES", format ["Plan %1 (révision %2) ignoré : retiré depuis", _id, _b get "rev"]] call comspec_atak_native_fnc_log; };
                if ((count _old) > 0 && {(_b get "rev") < (_old getOrDefault ["rev", 0])}) exitWith {};
                (missionNamespace getVariable ["COMSPEC_ATAK_ZnGone", createHashMap]) deleteAt _id;
                _b set ["from", [_who, _b get "owner"] select (_who isEqualTo "")];
                _b set ["scope", (_sc max 1) min 3];
                _b set ["arma", false];
                if ((count _old) > 0) then {
                    _b set ["vis", _old get "vis"]; _b set ["render", _old get "render"];
                    // Situation reçue entre-temps (plus récente que celle du plan) : gardée.
                    { [_b, _x select 0, _x select 6, _x select 7, _x select 8, _x select 9] call _apply; } forEach (_old get "zones");
                };
                // Diffusion à l'entrée dans la zone : reçu hors de la zone de travail, le plan dort (masqué, sans annonce)
                // jusqu'à l'entrée du joueur (fenceTick) ; l'auteur qui retire l'option le réveille.
                private _dorm = false;
                if (_b getOrDefault ["fence", false]) then {
                    _dorm = if ((count _old) > 0) then { _old getOrDefault ["dormant", false] } else { !(["inArea", [_b, getPosATL player]] call comspec_atak_native_fnc_zoneAction) };
                    if (_dorm) then { _b set ["dormant", true]; _b set ["vis", false]; };
                } else {
                    if (_old getOrDefault ["dormant", false]) then { _b set ["vis", true]; };
                };
                _rx set [_id, _b];
                if (!_quiet && {!_dorm}) then {
                    if ((count _old) isEqualTo 0) then {
                        ["MESSAGE", format ["Plan de zones reçu de %1 (%2) : %3, %4 zone(s), %5 axe(s)", _b get "from", ["scopeName", _b get "scope"] call comspec_atak_native_fnc_zoneAction, _b get "name", count (_b get "zones"), count (_b get "axes")], 6, 50] call comspec_atak_native_fnc_notify;
                        [] call comspec_atak_native_fnc_vibrate;
                    } else {
                        private _msg = format ["Plan de zones mis à jour par %1 : %2", _b get "from", _b get "name"];
                        if !(([_msg, 2] call comspec_atak_native_fnc_toast)) then { ["INFO", _msg, 2, 10] call comspec_atak_native_fnc_notify; };
                    };
                };
                call _bump; call _refresh;
            };
            case "ask": {
                _arg params ["", ["_id", ""], ["_zid", ""], ["_st", ""], ["_rep", []], ["_by", ""]];
                if (_quiet || {!(_id isEqualType "" && {_id in _mine} && {_zid isEqualType ""} && {_st isEqualType ""} && {_rep isEqualType []} && {_by isEqualType ""})}) exitWith {};
                _rep = (_rep select [0, 4]) apply { [_x, ""] select !(_x isEqualType "" || {_x isEqualType []}) };
                ["ask", [_id, _zid, _st, _rep, [_by, 24] call _clean]] call comspec_atak_native_fnc_zoneAction;
            };
            case "sit";
            case "sitAll": {
                _arg params ["", ["_id", ""], ["_zid", ""], ["_st", ""], ["_stamp", 0], ["_by", ""], ["_reps", []]];
                if !(_id isEqualType "" && {_zid isEqualType ""} && {_st isEqualType ""} && {_stamp isEqualType 0} && {_reps isEqualType []}) exitWith {};
                private _b = [_id] call _get;
                if ((count _b) isEqualTo 0) exitWith {};
                private _reps2 = (_reps select [0, 12]) select { _x isEqualType [] && {(count _x) >= 8} && {(_x select 0) isEqualType ""} && {(_x select 1) isEqualType 0} };
                _reps2 = _reps2 apply { [(_x select 0) select [0, 24], _x select 1, [_x select 2, 5] call _clean, [_x select 3, 24] call _clean, [_x select 4, 6] call _clean, [_x select 5, 12] call _clean, [_x select 6, 120] call _clean, [[0, 0], _x select 7] select ((_x select 7) isEqualType [] && {(count (_x select 7)) >= 2})] };
                private _z = [_b, _zid] call _zoneOf;
                if ((count _z) isEqualTo 0) exitWith {};
                // Question en attente sur cette zone : un autre a déjà tranché.
                if (_st isNotEqualTo "") then { (missionNamespace getVariable ["COMSPEC_ATAK_ZnAsk", createHashMap]) deleteAt (_id + "|" + _zid); };
                private _was = _z select 6;
                if !([_b, _zid, _st, _stamp, _by, _reps2] call _apply) exitWith {};
                [_b, false, false] call _changed;
                if (!_quiet && {_op isEqualTo "sit"} && {!(_b getOrDefault ["dormant", false])}) then {
                    private _now = _z select 6;
                    private _r = _reps2 param [0, []];
                    private _det = if ((count _r) >= 8) then { ([["natureName", _r select 4] call comspec_atak_native_fnc_zoneAction, _r select 5, _r select 6] select { _x isNotEqualTo "" }) joinString " · " } else { "" };
                    private _msg = format ["Zone %1 (%2) : %3%4 · %5", _z select 1, _b get "name", (([_now] call _stateRow) select 1) call _tr, ["", " · " + _det] select (_det isNotEqualTo ""), _by];
                    if (_now in ["ENY", "CONT"] && {_now isNotEqualTo _was || {(count _r) > 0}}) then {
                        ["WARNING", _msg, 6, 40] call comspec_atak_native_fnc_notify;
                        [] call comspec_atak_native_fnc_vibrate;
                    } else {
                        if !(([_msg, 3] call comspec_atak_native_fnc_toast)) then { ["INFO", _msg, 3, 10] call comspec_atak_native_fnc_notify; };
                    };
                };
            };
            case "reset": {
                _arg params ["", ["_id", ""], ["_t", 0]];
                private _b = [_id] call _get;
                if ((count _b) isEqualTo 0) exitWith {};
                { if ((_x select 7) <= _t) then { _x set [6, "UNREC"]; _x set [7, _t]; _x set [8, ""]; }; _x set [9, []]; } forEach (_b get "zones");
                [_b, false, false] call _changed;
                if !(_quiet) then { ["INFO", format ["Situation remise à zéro : %1", _b get "name"], 3, 10] call comspec_atak_native_fnc_notify; };
            };
        };
    };
    // Édition d'un de mes plans (vue EDIT) : [id]. Une autre édition ouverte est d'abord validée.
    case "editBegin": {
        if !(_arg in _mine) exitWith { false };
        if ([_arg] call _editing) exitWith { true };
        if ((count _ed) > 0) then { ["editCommit"] call comspec_atak_native_fnc_zoneAction; };
        _ed = createHashMapFromArray [["id", _arg], ["undo", []], ["redo", []], ["dirty", false], ["at", diag_tickTime]];
        missionNamespace setVariable ["COMSPEC_ATAK_ZnEdit", _ed];
        true
    };
    case "editInfo": { _ed };
    // Validation : fin de l'édition, puis un seul renvoi (plan partagé, carte d'Arma) si le plan a changé.
    case "editCommit": {
        if ((count _ed) isEqualTo 0) exitWith { false };
        private _id = _ed get "id";
        private _dirty = _ed get "dirty";
        _ed = createHashMap;
        missionNamespace setVariable ["COMSPEC_ATAK_ZnEdit", _ed];
        if (_dirty && {_id in _mine}) then { [_mine get _id, false] call _changed; };
        ["pruneAuto", _id] call comspec_atak_native_fnc_zoneAction;
        _dirty
    };
    // Édition laissée ouverte (autre app, téléphone reposé) : validée (appelé chaque seconde par fn_routeAction "tick").
    case "editTick": {
        if ((count _ed) isEqualTo 0) exitWith {};
        private _sx = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        if ((_sx getOrDefault ["activePage", ""]) isNotEqualTo "ZONES" || {!(_sx getOrDefault ["interactive", false])}) then { ["editCommit"] call comspec_atak_native_fnc_zoneAction; };
    };
    case "undo";
    case "redo": {
        if ((count _ed) isEqualTo 0) exitWith { false };
        private _b = _mine getOrDefault [_ed get "id", createHashMap];
        if ((count _b) isEqualTo 0) exitWith { false };
        private _from = _ed get (["redo", "undo"] select (_act isEqualTo "undo"));
        private _to = _ed get (["undo", "redo"] select (_act isEqualTo "undo"));
        if ((count _from) isEqualTo 0) exitWith { false };
        _to pushBack +(["pack", _b] call comspec_atak_native_fnc_zoneAction);
        [_b, _from deleteAt ((count _from) - 1)] call _restore;
        _ed set ["dirty", true];
        [_b] call _changed;
        true
    };
    // Plan supprimé, gardé 8 s (ANNULER) avant d'être retiré partout.
    case "trash": {
        private _b = _mine getOrDefault [_arg, createHashMap];
        if ((count _b) isEqualTo 0) exitWith { ["del", _arg] call comspec_atak_native_fnc_zoneAction };
        if ([_arg] call _editing) then { _ed = createHashMap; missionNamespace setVariable ["COMSPEC_ATAK_ZnEdit", _ed]; };
        private _bin = missionNamespace getVariable ["COMSPEC_ATAK_ZnTrash", createHashMap];
        missionNamespace setVariable ["COMSPEC_ATAK_ZnTrash", _bin];
        private _tok = diag_tickTime;
        // Retiré tout de suite partout : quitter le jeu pendant le délai d'annulation ne le laisse pas revivre ailleurs.
        private _sc0 = _b get "scope";
        private _arma0 = _b get "arma";
        { ["del", _x] call comspec_atak_native_fnc_markerOwned; } forEach (_b getOrDefault ["mk", []]);
        _b set ["mk", []]; _b set ["mkz", createHashMap]; _b set ["mkTok", (_b getOrDefault ["mkTok", 0]) + 1];
        _b set ["arma", false];
        if (_sc0 > 0) then { ["unshare", _arg] call comspec_atak_native_fnc_zoneAction; };
        _bin set [_arg, [_b, _tok, _sc0, _arma0]];
        _mine deleteAt _arg;
        call _save; call _bump; call _refresh;
        [{
            params ["_id", "_tok"];
            private _bin = missionNamespace getVariable ["COMSPEC_ATAK_ZnTrash", createHashMap];
            private _e = _bin getOrDefault [_id, []];
            if ((_e param [1, -1]) isNotEqualTo _tok) exitWith {};
            _bin deleteAt _id;
            (missionNamespace getVariable ["COMSPEC_ATAK_ZnMine", createHashMap]) set [_id, _e select 0];
            ["del", _id] call comspec_atak_native_fnc_zoneAction;
        }, [_arg, _tok], 8] call comspec_atak_native_fnc_waitAndExec;
        true
    };
    case "untrash": {
        private _bin = missionNamespace getVariable ["COMSPEC_ATAK_ZnTrash", createHashMap];
        private _e = _bin getOrDefault [_arg, []];
        if ((count _e) isEqualTo 0) exitWith { false };
        _bin deleteAt _arg;
        _mine set [_arg, _e select 0];
        (_e select 0) set ["arma", _e param [3, false]];
        if ((_e param [2, 0]) > 0) then { ["share", [_arg, _e select 2, true]] call comspec_atak_native_fnc_zoneAction; };
        if (((_e select 0) get "arma") && {(count ((_e select 0) getOrDefault ["mk", []])) isEqualTo 0}) then { ["armaNow", _arg] call comspec_atak_native_fnc_zoneAction; };
        call _save; call _bump; call _refresh;
        true
    };
    // Contours et traits tracés : [id, points, fermé] -> id du trait ("" : refusé).
    case "lineAdd": {
        _arg params ["_id", "_pts", ["_closed", false], ["_name", ""]];
        private _b = _mine getOrDefault [_id, createHashMap];
        if ((count _b) isEqualTo 0 || {(count _pts) < ([2, 3] select _closed)}) exitWith { "" };
        private _ls = _b getOrDefault ["lines", []];
        if ((count _ls) >= 40) exitWith { ["WARNING", "40 contours ou traits au plus par plan", 3, 10] call comspec_atak_native_fnc_notify; "" };
        [_b] call _snap;
        private _n = 1;
        while { (_ls findIf { (_x select 0) isEqualTo format ["l%1", _n] }) >= 0 } do { _n = _n + 1; };
        private _lid = format ["l%1", _n];
        if (_name isEqualTo "") then {
            _name = format [[("Trait %1") call _tr, ("Contour %1") call _tr] select _closed, _n];
            private _gpL = _b get "gp";
            if ((_gpL getOrDefault ["theme", "CITY"]) isEqualTo "MIL") then {
                private _code = ([[_gpL getOrDefault ["theme", "CITY"], _gpL getOrDefault ["milSub", "CITY"]] call _themeList, call _usedNames, 1] call _nextNames) param [0, ""];
                if (_code isNotEqualTo "") then { _name = (format ["%1 %2", ["PL", "AA"] select _closed, _code]) select [0, 16]; };
            };
        };
        _ls pushBack [_lid, [_name, 16] call _clean, (_pts select [0, 300]) apply { [round ((_x select 0) * 10) / 10, round ((_x select 1) * 10) / 10] }, _closed, ["", -1, ""]];
        _b set ["lines", _ls];
        [_b] call _changed;
        _lid
    };
    // [id, id du trait, clé (name | style | closed | del | vmove | vdel | vins | asArea), valeur, index du sommet].
    case "lineSet": {
        _arg params ["_id", "_lid", "_key", ["_v", ""], ["_i", -1]];
        private _b = _mine getOrDefault [_id, createHashMap];
        if ((count _b) isEqualTo 0) exitWith { false };
        private _ls = _b getOrDefault ["lines", []];
        private _k = _ls findIf { (_x select 0) isEqualTo _lid };
        if (_k < 0) exitWith { false };
        private _l = _ls select _k;
        private _pts = _l select 2;
        if (_key in ["vmove", "vdel", "vins"] && {_i < 0 || {_i > count _pts}}) exitWith { false };
        if (_key isEqualTo "vdel" && {(count _pts) <= ([2, 3] select (_l select 3))}) exitWith { ["INFO", "Pas moins de sommets : supprimez plutôt le trait", 2, 10] call comspec_atak_native_fnc_notify; false };
        [_b] call _snap;
        switch (_key) do {
            case "name": { _v = [_v, 16] call _clean; if (_v isNotEqualTo "") then { _l set [1, [_b, _v, 16, ["line", _lid]] call _uniq]; }; };
            case "style": { _l set [4, _v]; };
            case "closed": { if (_v && {(count _pts) < 3}) exitWith {}; _l set [3, _v]; };
            case "del": { _ls deleteAt _k; };
            case "vmove": { _pts set [_i, [_v select 0, _v select 1]]; };
            case "vdel": { _pts deleteAt _i; };
            case "vins": { _pts insert [_i, [[_v select 0, _v select 1]]]; };
            case "asArea": { if ((count _pts) >= 3) then { _b set ["area", ["POLY", +(_pts select [0, 60])]]; }; };
        };
        [_b] call _changed;
        true
    };
    // Trait ou contour d'un plan sous un point de l'écran : [plan, carte, point écran, px] -> [id, sommet, milieu] ou [].
    //   sommet : index du sommet sous le point (-1) ; milieu : index du segment dont le milieu est sous le point (-1).
    case "lineAt": {
        _arg params ["_b", "_map", "_sp", ["_px", 10]];
        private _best = []; private _bd = pixelW * _px;
        {
            _x params ["_lid", "", "_pts", "_closed"];
            private _n = count _pts;
            private _scr = _pts apply { _map ctrlMapWorldToScreen _x };
            {
                private _d = _x distance2D _sp;
                if (_d < _bd) then { _bd = _d; _best = [_lid, _forEachIndex, -1]; };
            } forEach _scr;
            for "_i" from 0 to (_n - ([2, 1] select _closed)) do {
                private _a = _scr select _i; private _c = _scr select ((_i + 1) mod _n);
                private _m = [((_a select 0) + (_c select 0)) / 2, ((_a select 1) + (_c select 1)) / 2];
                private _dm = _m distance2D _sp;
                if (_dm < _bd * 0.8) then { _bd = _dm; _best = [_lid, -1, _i]; };
                // Distance au segment (écran).
                private _ab = [(_c select 0) - (_a select 0), (_c select 1) - (_a select 1)];
                private _l2 = ((_ab select 0) ^ 2) + ((_ab select 1) ^ 2);
                if (_l2 > 0) then {
                    private _t = ((((_sp select 0) - (_a select 0)) * (_ab select 0) + ((_sp select 1) - (_a select 1)) * (_ab select 1)) / _l2) max 0 min 1;
                    private _ds = [(_a select 0) + (_ab select 0) * _t, (_a select 1) + (_ab select 1) * _t] distance2D _sp;
                    if (_ds < _bd && {(count _best) isEqualTo 0 || {(_best select 0) isNotEqualTo _lid}}) then { _bd = _ds; _best = [_lid, -1, -1]; };
                };
            };
        } forEach (_b getOrDefault ["lines", []]);
        _best
    };
    // Style : [id, clé du style du plan, valeur] ; clés tcol / scol : valeur [type ou état, classe de couleur ("" : thème)].
    case "style": {
        _arg params ["_id", "_key", "_v"];
        private _b = _mine getOrDefault [_id, createHashMap];
        if ((count _b) isEqualTo 0) exitWith { false };
        [_b] call _snap;
        private _st = _b getOrDefault ["st", call _stDef];
        _b set ["st", _st];
        if (_key in ["tcol", "scol"]) then {
            private _m = _st getOrDefault [_key, createHashMap];
            _st set [_key, _m];
            _v params ["_k", "_c"];
            if (_c isEqualTo "") then { _m deleteAt _k; } else { _m set [_k, _c]; };
        } else {
            if (_key isEqualTo "theme") then { _st set ["fillA", -1]; _st set ["width", -1]; };
            _st set [_key, _v];
        };
        [_b] call _changed;
        true
    };
    // Style d'une sélection de zones : [id, ids de zone ([] : toutes celles du type), style [couleur, opacité, épaisseur,
    // trait], type (avec ids [] : toutes les zones de ce type)].
    case "zoneStyle": {
        _arg params ["_id", ["_zids", []], ["_sty", ["", -1, -1, ""]], ["_ty", ""]];
        private _b = _mine getOrDefault [_id, createHashMap];
        if ((count _b) isEqualTo 0) exitWith { 0 };
        [_b] call _snap;
        private _n = 0;
        {
            if ((_x select 0) in _zids || {(count _zids) isEqualTo 0 && {_ty isNotEqualTo ""} && {(_x select 4) isEqualTo _ty}}) then { _x set [12, +_sty]; _n = _n + 1; };
        } forEach (_b get "zones");
        [_b] call _changed;
        _n
    };
    // Couleurs résolues d'un plan (thème, couleurs choisies), gardées par révision : HashMap type, state (clé ->
    // RGBA), fillA, width, dash, lblSize, lblBg, lblPos, lblMode, theme.
    case "pal": {
        private _b = _arg;
        private _c = _b getOrDefault ["palc", []];
        if ((_c param [0, -1]) isEqualTo (_b get "rev")) exitWith { _c select 1 };
        private _st = _b getOrDefault ["st", createHashMap];
        private _th = _st getOrDefault ["theme", "NATO"];
        (_pals getOrDefault [_th, _pals get "NATO"]) params ["_tp", "_sp", "_fa", "_w"];
        private _rgba = { params ["_cls"]; ["rgba", _cls] call comspec_atak_native_fnc_routeAction };
        private _ty = createHashMap; private _ss = createHashMap;
        { _ty set [_x, +(_tp get _x)]; } forEach (keys _tp);
        { _ss set [_x, +(_sp get _x)]; } forEach (keys _sp);
        { _ty set [_x, [((_st get "tcol") get _x)] call _rgba]; } forEach (keys (_st getOrDefault ["tcol", createHashMap]));
        { _ss set [_x, [((_st get "scol") get _x)] call _rgba]; } forEach (keys (_st getOrDefault ["scol", createHashMap]));
        // SQF ne distingue pas la casse : un _fA ici écrasait _fa (opacité du thème), -1 par défaut : remplissage invisible.
        private _fillSet = _st getOrDefault ["fillA", -1];
        private _wd = _st getOrDefault ["width", -1];
        private _p = createHashMapFromArray [["type", _ty], ["state", _ss], ["fillA", [_fa, _fillSet] select (_fillSet >= 0)], ["width", [_w, _wd] select (_wd > 0)],
            ["dash", _st getOrDefault ["dash", "SOLID"]], ["lblSize", _st getOrDefault ["lblSize", 1]], ["lblBg", _st getOrDefault ["lblBg", false]],
            ["lblPos", _st getOrDefault ["lblPos", "C"]], ["lblMode", _st getOrDefault ["lblMode", "NAME_ST"]], ["theme", _th], ["hatch", _st getOrDefault ["hatch", false]], ["ring", _st getOrDefault ["ring", false]]];
        _b set ["palc", [_b get "rev", _p]];
        _p
    };
    case "themes3": { [["NATO", "OTAN standard"], ["HC", "Contraste élevé"], ["NV", "Vision nocturne"]] };
    // Boîtes et surfaces des zones, gardées par révision : [révision, boîte du plan, boîtes des zones, surfaces, points 3D].
    case "geo": {
        private _b = _arg;
        private _g = _b getOrDefault ["geo", []];
        if ((_g param [0, -1]) isEqualTo (_b get "rev")) exitWith { _g };
        private _zb = []; private _za = []; private _p3 = [];
        private _X0 = 1e9; private _Y0 = 1e9; private _X1 = -1e9; private _Y1 = -1e9;
        {
            private _pts = _x select 2;
            private _bb = ["bbox", [_pts]] call comspec_atak_native_fnc_zoneGeom;
            _zb pushBack _bb;
            _za pushBack (abs (["area", [_pts]] call comspec_atak_native_fnc_zoneGeom));
            _p3 pushBack (_pts apply { [_x select 0, _x select 1, 0] });
            _X0 = _X0 min (_bb select 0); _Y0 = _Y0 min (_bb select 1); _X1 = _X1 max (_bb select 2); _Y1 = _Y1 max (_bb select 3);
        } forEach (_b get "zones");
        {
            { _X0 = _X0 min (_x select 0); _Y0 = _Y0 min (_x select 1); _X1 = _X1 max (_x select 0); _Y1 = _Y1 max (_x select 1); } forEach (_x select 3);
        } forEach (_b get "axes");
        {
            { _X0 = _X0 min (_x select 0); _Y0 = _Y0 min (_x select 1); _X1 = _X1 max (_x select 0); _Y1 = _Y1 max (_x select 1); } forEach (_x select 2);
        } forEach (_b getOrDefault ["lines", []]);
        private _ap = (["areaPoly", _b] call comspec_atak_native_fnc_zoneAction) select 0;
        { _X0 = _X0 min (_x select 0); _Y0 = _Y0 min (_x select 1); _X1 = _X1 max (_x select 0); _Y1 = _Y1 max (_x select 1); } forEach _ap;
        _g = [_b get "rev", [_X0, _Y0, _X1, _Y1], _zb, _za, _p3, _ap apply { [_x select 0, _x select 1, 0] }];
        _b set ["geo", _g];
        _g
    };
    // Zone sous une position parmi les plans affichés : [position] -> [plan, zone] ou [].
    case "at": {
        _arg params [["_p", []]];
        if ((count _p) < 2) exitWith { [] };
        private _best = []; private _ba = 1e12;
        {
            if (_x get "vis") then {
                private _z = ["zoneIn", [_x, _p]] call comspec_atak_native_fnc_zoneAction;
                if ((count _z) > 0) then {
                    private _g = ["geo", _x] call comspec_atak_native_fnc_zoneAction;
                    private _a = (_g select 3) select ((_x get "zones") find _z);
                    if (_a < _ba) then { _ba = _a; _best = [_x, _z]; };
                };
            };
        } forEach ((values _mine) + (values _rx));
        _best
    };
    // Bandeau du curseur de la carte : texte de la zone sous le point (gardé par position et révision).
    case "atText": {
        _arg params [["_p", []]];
        if ((count _p) < 2 || {!(missionNamespace getVariable ["comspec_atak_native_zones_cursor", true])}) exitWith { "" };
        if (((count _mine) + (count _rx)) isEqualTo 0) exitWith { "" };
        private _key = [round ((_p select 0) / 3), round ((_p select 1) / 3), missionNamespace getVariable ["COMSPEC_ATAK_ZnRev", 0], _fr];
        private _c = uiNamespace getVariable ["COMSPEC_ATAK_ZnCursor", []];
        if ((_c param [0, []]) isEqualTo _key) exitWith { _c select 1 };
        private _hit = ["at", [_p]] call comspec_atak_native_fnc_zoneAction;
        private _t = "";
        if ((count _hit) > 0) then {
            _hit params ["_b", "_z"];
            private _sr = [_z select 6] call _stateRow;
            private _hex = ((_sr select 4) select [0, 3]) call BIS_fnc_colorRGBtoHTML;
            _t = format ["<t font='COMSPEC_BarlowBold' color='%1'>%2</t> <t size='0.8' color='#b7c1bc'>%3</t>",
                _hex, ((_z select 1) regexReplace ["[<>&]", ""]), (format ["%1 · %2", _b get "name", _sr select ([3, 2] select _fr)]) regexReplace ["[<>&]", ""]];
        };
        uiNamespace setVariable ["COMSPEC_ATAK_ZnCursor", [_key, _t]];
        _t
    };
    // Citation d'une zone dans un message ou un compte rendu : [plan, zone] -> « B-04 (OBJ BRAVO) 33T… · Ennemi signalé ».
    case "cite": {
        _arg params ["_b", "_z"];
        private _p = _z select 3;
        format ["%1 (%2) %3 · %4", _z select 1, _b get "name", [[_p select 0, _p select 1, 0], 8] call comspec_atak_native_fnc_gridRef, (([_z select 6] call _stateRow) select 1) call _tr]
    };
    // Raccourci @zone des messages : zone sous ma position, sinon "".
    case "citeAt": {
        private _hit = ["at", [_arg]] call comspec_atak_native_fnc_zoneAction;
        if ((count _hit) isEqualTo 0) exitWith { "" };
        ["cite", _hit] call comspec_atak_native_fnc_zoneAction
    };
    // Contact ennemi posé ou signalé par ce téléphone (marqueur hostile, TIC, SALUTE, C.R. CONTACT / SPOTREP) :
    // [position, nature, effectif, texte]. Réglage serveur « Contact dans une zone » (zones_contact_mode) :
    //   MANUAL (défaut) rien ; ASK question OUI / NON à ce joueur et à l'auteur du plan (sans réponse : rien) ;
    //   AUTO la zone qui le contient passe « ennemi signalé » (« contact » si tenue par un ami ; interdite ou déjà
    //   ennemie : inchangée) avec un signalement, envoyé comme un rapport de zone.
    case "contact": {
        _arg params [["_p", []], ["_nat", "UNK"], ["_size", ""], ["_txt", ""]];
        private _mode = missionNamespace getVariable ["comspec_atak_native_zones_contact_mode", "MANUAL"];
        if ((count _p) < 2 || {!(_mode in ["ASK", "AUTO"])}) exitWith { 0 };
        private _n = 0;
        {
            private _b = _x;
            private _z = ["zoneIn", [_b, _p]] call comspec_atak_native_fnc_zoneAction;
            if ((count _z) > 0) then {
                private _st = _z select 6;
                private _new = switch (true) do { case (_st in ["UNREC", "CLEAR", "CONT"]): { "ENY" }; case (_st isEqualTo "FRD"): { "CONT" }; default { "" }; };
                private _rep = [_nat, _size, _txt, [_p select 0, _p select 1]];
                if (_mode isEqualTo "AUTO") then {
                    ["sit", [_b get "id", _z select 0, _new, _rep]] call comspec_atak_native_fnc_zoneAction;
                    _n = _n + 1;
                } else {
                    if (_new isEqualTo "") exitWith {};
                    private _q = [_b get "id", _z select 0, _new, _rep, [player] call comspec_atak_native_fnc_unitCallsign];
                    ["ask", _q] call comspec_atak_native_fnc_zoneAction;
                    // Plan reçu : la même question part à son auteur (s'il est là).
                    if !((_b get "id") in _mine) then {
                        private _ou = _b getOrDefault ["uid", ""];
                        private _to = allPlayers select { (getPlayerUID _x) isEqualTo _ou && {_x isNotEqualTo player} };
                        if ((count _to) > 0) then {
                            [{
                                params ["_q", "_to"];
                                ["comspec_atak_native_zone", ["ask"] + _q, _to] call CBA_fnc_targetEvent;
                            }, [_q, _to], "Rapport de zone", 1] call comspec_atak_native_fnc_netSend;
                        };
                    };
                    _n = _n + 1;
                };
            };
        } forEach ((values _mine) + (values _rx));
        _n
    };
    // Question en attente (mode Proposer) : [id, zone, état proposé, signalement, auteur du contact].
    case "ask": {
        _arg params [["_id", ""], ["_zid", ""], ["_st", ""], ["_rep", []], ["_by", ""]];
        private _b = [_id] call _get;
        if ((count _b) isEqualTo 0) exitWith { false };
        private _z = [_b, _zid] call _zoneOf;
        if ((count _z) isEqualTo 0 || {(_states findIf { (_x select 0) isEqualTo _st }) < 0}) exitWith { false };
        private _asks = missionNamespace getVariable ["COMSPEC_ATAK_ZnAsk", createHashMap];
        missionNamespace setVariable ["COMSPEC_ATAK_ZnAsk", _asks];
        _asks set [_id + "|" + _zid, [_id, _zid, _st, _rep, _by, serverTime]];
        ["WARNING", format [("Contact en %1 (%2) : passer la zone en « %3 » ? OUI / NON dans l'app Zones") call _tr, _z select 1, _b get "name", (([_st] call _stateRow) select 1) call _tr], 8, 40, "ZONES"] call comspec_atak_native_fnc_notify;
        [] call comspec_atak_native_fnc_vibrate;
        call _bump; call _refresh;
        true
    };
    case "asks": { values (missionNamespace getVariable ["COMSPEC_ATAK_ZnAsk", createHashMap]) };
    // Réponse : [clé id|zone, oui]. Oui : état et signalement appliqués et envoyés (« sit ») ; non : oubliée.
    case "askAnswer": {
        _arg params [["_k", ""], ["_yes", false]];
        private _asks = missionNamespace getVariable ["COMSPEC_ATAK_ZnAsk", createHashMap];
        private _q = _asks getOrDefault [_k, []];
        _asks deleteAt _k;
        if ((count _q) > 0 && {_yes}) then { ["sit", [_q select 0, _q select 1, _q select 2, _q select 3]] call comspec_atak_native_fnc_zoneAction; };
        call _bump; call _refresh;
        true
    };
    // Marqueur posé par ce joueur (EH MarkerCreated, fn_markerDrop) : contact si c'est un marqueur ennemi, une fois.
    case "markerContact": {
        private _m = _arg;
        if ((markerShape _m) isNotEqualTo "ICON") exitWith { false };
        private _done = missionNamespace getVariable ["COMSPEC_ATAK_ZnMkDone", createHashMap];
        if (_m in _done) exitWith { false };
        private _type = markerType _m;
        private _txt = markerText _m;
        if !(((toLower _type) select [0, 2]) isEqualTo "o_" || {(toUpper (_txt select [0, 4])) in ["ENI ", "ENY "]}) exitWith { false };
        _done set [_m, true];
        missionNamespace setVariable ["COMSPEC_ATAK_ZnMkDone", _done];
        ["contact", [markerPos _m, ["natureOf", _type] call comspec_atak_native_fnc_zoneAction, "", _txt]] call comspec_atak_native_fnc_zoneAction;
        true
    };
    // Nature d'un contact d'après le type de marqueur hostile (o_inf, o_armor…).
    case "natureOf": {
        private _t = toLower _arg;
        switch (true) do {
            case (_t in ["o_inf"]): { "INF" };
            case (_t in ["o_motor_inf"]): { "MOT" };
            case (_t in ["o_mech_inf", "o_armor"]): { "MECA" };
            case (_t in ["o_recon"]): { "REC" };
            case (_t in ["o_art", "o_mortar"]): { "ART" };
            case (_t in ["o_antiair"]): { "AA" };
            case (_t in ["o_air", "o_plane", "o_uav"]): { "AIR" };
            case (_t in ["o_hq"]): { "HQ" };
            default { "UNK" };
        }
    };
    // Carte d'Arma et Athena : cases en rectangles (pleins ou bordés), autres zones et contours en polyligne fermée,
    // traits en polyligne, étiquette au centre (nom et état), axes, points de contrôle. Différentiel (2.9.38) : chaque
    // marqueur a une clé (sa forme, sa couleur, son texte, son canal) ; seuls ceux dont la clé change sont refaits, et
    // les créations sont étalées (30 par image). Athena n'a pas de polygone plein : seules les cases y sont remplies.
    case "armaNow": {
        private _b = _mine getOrDefault [_arg, createHashMap];
        if ((count _b) isEqualTo 0) exitWith {};
        private _old = _b getOrDefault ["mkz", createHashMap];
        private _want = [];
        private _on = (_b get "arma") && {missionNamespace getVariable ["comspec_atak_native_zones_arma", true]};
        private _sc = _b get "scope";
        private _ch = [3, 1] select (_sc >= 3);
        // Plan en cours de calcul, ou zone de travail seule : rien de lisible à poser.
        if (_on) then {
            private _hatch = (["pal", _b] call comspec_atak_native_fnc_zoneAction) get "hatch";
            private _cap = (missionNamespace getVariable ["comspec_atak_native_zones_armamax", 60]) max 10 min 200;
            private _ptsOnly = (count (_b get "zones")) > _cap;
            {
                _x params ["_zid", "_name", "_pts", "_lp", "_ty", "_rm", "_st", "", "", "_reps", "", ["_rect", []], ["_zs", []]];
                if (_rm isEqualTo "") then { _rm = _b get "render"; };
                private _zc = _zs param [0, ""];
                private _own = ["ZONE", _zid];
                private _col = [[([_ty] call _typeRow) select 3, _zc] select (_zc isNotEqualTo ""), ([_st] call _stateRow) select 5] select (_st isNotEqualTo "UNREC");
                if (!_ptsOnly && {_rm isNotEqualTo "POINT"}) then {
                    if ((count _rect) isEqualTo 4) then {
                        _want pushBack ["RECT", [_rect select 0, _rect select 1], [_rect select 2, _rect select 3], ["Border", ["Solid", "BDiagonal"] select _hatch] select (_rm isEqualTo "FILL"), [0.9, [0.45, 0.8] select _hatch] select (_rm isEqualTo "FILL"), _col, _own];
                    } else {
                        if ((count _pts) >= 3) then {
                            private _flat = [];
                            { _flat append [round (_x select 0), round (_x select 1)]; } forEach ((_pts select [0, 199]) + [_pts select 0]);
                            _want pushBack ["POLY", _flat, _col, "", _own];
                        };
                    };
                };
                _want pushBack ["ICON", [round (_lp select 0), round (_lp select 1)], "mil_dot", 0.6,
                    format ["%1%2%3", _name, ["", " · " + ((([_st] call _stateRow) select 1) call _tr)] select (_st isNotEqualTo "UNREC"), ["", format [" (%1)", count _reps]] select ((count _reps) > 0)], _col, _own];
            } forEach (_b get "zones");
            {
                _x params ["_aid", "_name", "_cls", "_pts"];
                if ((count _pts) >= 2) then {
                    private _flat = [];
                    { _flat append [round (_x select 0), round (_x select 1)]; } forEach (_pts select [0, 200]);
                    private _c = ["ColorGreen", "ColorBlue"] select (_cls isEqualTo "MSR");
                    _want pushBack ["POLY", _flat, _c, "", ["AXIS", _aid]];
                    private _mid = _pts select (floor ((count _pts) / 2));
                    _want pushBack ["ICON", [round (_mid select 0), round (_mid select 1)], "mil_dot", 0.5, _name, _c, ["AXIS", _aid]];
                };
            } forEach (_b get "axes");
            { _x params ["_cn", "_lbl", "_p"]; _want pushBack ["ICON", [round (_p select 0), round (_p select 1)], "mil_triangle", 0.6, _lbl, "ColorYellow", ["CP", _cn]]; } forEach (_b get "cps");
            {
                _x params ["_lid", "_ln", "_lpts", "_closed", ["_ls", []]];
                private _c = [([_b get "aff"] call _typeRow) select 3, _ls param [0, ""]] select ((_ls param [0, ""]) isNotEqualTo "");
                private _flat = [];
                { _flat append [round (_x select 0), round (_x select 1)]; } forEach ((_lpts select [0, 199]) + ([[], [_lpts select 0]] select _closed));
                _want pushBack ["POLY", _flat, _c, _ln, ["LINE", _lid]];
            } forEach (_b getOrDefault ["lines", []]);
            if (_ptsOnly && {(count _old) isEqualTo 0}) then { ["INFO", format ["Plus de %1 zones : seules leurs étiquettes vont sur la carte d'Arma et Athena", _cap], 4, 20] call comspec_atak_native_fnc_notify; };
            // Plafond dur par plan (chaque marqueur est un envoi réseau et un envoi au site) : la carte du téléphone garde tout.
            private _hard = (_cap * 3) max 150 min 400;
            if ((count _want) > _hard) then {
                ["WARNING", format ["%1 marqueurs pour ce plan : %2 au plus sur la carte d'Arma et Athena", count _want, _hard], 5, 20] call comspec_atak_native_fnc_notify;
                _want resize _hard;
            };
        };
        // Clés voulues : gardées si déjà posées, sinon à créer ; les autres marqueurs sont retirés.
        private _new = createHashMap;
        private _todo = [];
        {
            private _k = str [_x, _ch, _sc > 0];
            if (_k in _old && {(_old get _k) isNotEqualTo ""}) then { _new set [_k, _old get _k]; _old deleteAt _k; } else { if !(_k in _new) then { _todo pushBack [_k, _x]; _new set [_k, ""]; }; };
        } forEach _want;
        { private _m = _old get _x; if (_m isNotEqualTo "") then { ["del", _m] call comspec_atak_native_fnc_markerOwned; }; } forEach (keys _old);
        _b set ["mkz", _new];
        _b set ["mk", (values _new) select { _x isNotEqualTo "" }];
        _b set ["mkTok", (_b getOrDefault ["mkTok", 0]) + 1];
        if ((count _todo) isEqualTo 0) exitWith {};
        // Créations étalées : 30 marqueurs par image (gros découpage : aucun pic).
        ["mkStep", [_arg, _b get "mkTok", _todo, _ch, _sc]] call comspec_atak_native_fnc_zoneAction;
    };
    // Création d'un lot de marqueurs (armaNow) : 30 par appel, la suite à l'image suivante ; lot d'une ancienne version
    // (armaNow relancé entre-temps) abandonné.
    case "mkStep": {
        _arg params ["_id", "_tok", "_todo", "_ch", ["_sc", 1]];
        private _b = _mine getOrDefault [_id, createHashMap];
        if ((count _b) isEqualTo 0 || {(_b getOrDefault ["mkTok", -1]) isNotEqualTo _tok}) exitWith {};
        private _mkz = _b getOrDefault ["mkz", createHashMap];
        private _mkO = comspec_atak_native_fnc_markerOwned;
        private _made = [];
        // Overwatch (EH MarkerCreated) enverrait un point vide avant la forme : muet le temps de poser les marqueurs.
        missionNamespace setVariable ["COMSPEC_MarkerEhMuted", (missionNamespace getVariable ["COMSPEC_MarkerEhMuted", 0]) + 1];
        {
            _x params ["_k", "_d"];
            private _m = "";
            switch (_d select 0) do {
                case "RECT": {
                    _d params ["", "_p", "_sz", "_br", "_al", "_col", ["_own", []]];
                    _m = ["make", [_sc, _ch, [_p select 0, _p select 1, 0]]] call _mkO;
                    if (_m isNotEqualTo "") then { _m setMarkerShapeLocal "RECTANGLE"; _m setMarkerSizeLocal _sz; _m setMarkerBrushLocal _br; _m setMarkerAlphaLocal _al; ["paint", [_m, _col]] call _mkO; ["reg", [_m, ["ZONE", _id] + _own]] call _mkO; };
                };
                case "POLY": {
                    _d params ["", "_flat", "_col", ["_txt", ""], ["_own", []]];
                    _m = ["make", [_sc, _ch, [_flat select 0, _flat select 1, 0]]] call _mkO;
                    if (_m isNotEqualTo "") then { _m setMarkerShapeLocal "POLYLINE"; _m setMarkerPolylineLocal _flat; if (_txt isNotEqualTo "") then { _m setMarkerTextLocal _txt; }; ["paint", [_m, _col]] call _mkO; ["reg", [_m, ["ZONE", _id] + _own]] call _mkO; };
                };
                default {
                    _d params ["", "_p", "_type", "_sz", "_txt", "_col", ["_own", []]];
                    _m = ["make", [_sc, _ch, [_p select 0, _p select 1, 0]]] call _mkO;
                    if (_m isNotEqualTo "") then { _m setMarkerTypeLocal _type; _m setMarkerSizeLocal [_sz, _sz]; _m setMarkerTextLocal _txt; ["paint", [_m, _col]] call _mkO; ["reg", [_m, ["ZONE", _id] + _own]] call _mkO; };
                };
            };
            if (_m isEqualTo "") then { _mkz deleteAt _k; } else { _mkz set [_k, _m]; _made pushBack _m; };
        } forEach (_todo select [0, 15]);
        missionNamespace setVariable ["COMSPEC_MarkerEhMuted", ((missionNamespace getVariable ["COMSPEC_MarkerEhMuted", 1]) - 1) max 0];
        (_b getOrDefault ["mk", []]) append _made;
        // Site Athena : seulement si le plan est partagé (sinon ses marqueurs ne vont qu'au canal, sans le site).
        // Plan privé : marqueurs locaux à ce poste (fn_markerOwned), rien au site ni au réseau.
        if (_sc > 0) then { { [_x] call comspec_atak_native_fnc_markerWeb; } forEach _made; };
        if ((count _todo) > 15) then { [{ ["mkStep", _this] call comspec_atak_native_fnc_zoneAction; }, [_id, _tok, _todo select [15], _ch, _sc]] call comspec_atak_native_fnc_nextFrame; };
    };
    default { nil };
};
