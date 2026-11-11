/*
    Tableau des écrans TOC (demande de TTRD, 4.8.0 : « les menus des modules TOC sont très laids, il faudrait un tableau
    des écrans comme le TBR d'Eden mais en Zeus + Eden pour générer tous les écrans et flux », puis « un vrai module UI
    en jeu pour personnaliser chaque dalle, chaque écran, sélectionner telle source, telle sortie »).
    « TBR » : le tableau des relais du menu COMSPEC ATAK d'Eden (fn_relayEden « table »). Même idée ici, pour les
    écrans : une fenêtre, une ligne par DALLE (écran simple, chaque dalle d'un mur d'images, chaque moniteur d'un poste
    PC), dans Eden, au Zeus et en jeu (menu de l'écran, PC du poste). Une seule fenêtre et un seul code ; seule la
    lecture et l'écriture des lignes changent (table _eden : attributs des objets de l'éditeur ; en jeu : variables
    publiques des écrans, par les chemins du serveur de fn_tocScreen, fn_tocHall, fn_tocBroadcast, fn_tocLayout).
    Colonnes : coche, nom, type et dalle, salle, distance, source montrée, luminosité, état.
    À droite, la ligne choisie : plan du mur (une case par dalle, clic = choisir la dalle), nom, source, luminosité,
    salle, titre de la diffusion, portée de l'application (cette dalle, les lignes cochées, toute la salle, tout).
    Sources : chaque affichage permis par le format de la dalle, satellites 1 et 2, mosaïques, escouades, caméras en
    direct, mire, recopie d'un autre écran (une source, plusieurs sorties : colonne Source « recopie de nom »), éteint.
    Barre d'outils : ajouter (type choisi, à la caméra du Zeus ou devant le joueur ; Eden : au centre de la vue),
    dupliquer, supprimer, lier à la salle, générer la salle (S, M, L), générer tous les flux (mur : situation ; écrans
    de côté : satellite, caméras, réseau, flux ; bandeaux : horloge, texte ; plaques : escouades ; postes : console),
    disposition d'un mur ou d'un écran incurvé (fn_tocLayout), aller à l'écran, l'identifier, écrire un texte,
    enregistrer et charger un préréglage (profil du joueur, par carte).
    Les résultats s'écrivent dans la ligne d'état de la fenêtre, jamais par-dessus le jeu.
    Modes :
      ["open", [objet à choisir]]     ouvre le tableau (Eden : display 313 ; Zeus : 312 ; en jeu : 46)
      ["rows"]                        lignes : [clé, objet ou entité, nom, type, salle, distance, clé de source, libellé de
                                      source, luminosité, état, jeton d'état, hôte, dalle]
      ["sources", objet]              sources proposées pour une dalle : [[libellé, clé]...]
      ["sourceOf", objet] / ["sourceLabel", clé]
      ["frames"] / ["frameOf", clé] / ["setView", [objet, cadrage, réglage du satellite, point regardé]]
                                      cadrage de la carte d'une dalle, zoom, centre, capteur et suivi de son satellite
      ["category", clé] / ["groups", sources]   famille d'une source ; sources rangées par famille (fonction pure) :
                                      [[titre, famille, [[libellé, clé]...]]...] (CARTES, CAMÉRAS, SATELLITE, DONNÉES, TEMPS, DIFFUSION, SPÉCIAL)
      ["setSource", [objet, clé]]     pose la source (false : refusée par le format de la dalle)
      ["feeds", [objets]]             flux standard d'un lot d'écrans -> [[objet, clé]...] (sans effet)
      ["uniqueName", nom] / ["uniqueHall", nom]   nom rendu unique dans la mission (« TOC », « TOC 2 »…)
      ["canEdit"]                     ce joueur peut-il poser et supprimer des écrans (Zeus, administrateur, hôte, Eden)
      ["refresh"] / ["pick", ligne] / ["check"] / ["act", action] / ["say", texte]   fenêtre ouverte
*/
params [["_mode", "open"], ["_a", []]];
disableSerialization;
private _fn = comspec_atak_native_fnc_tocManager;
private _ts = comspec_atak_native_fnc_tocScreen;
private _tr = { _this call comspec_atak_native_fnc_tr };
private _st = uiNamespace getVariable ["COMSPEC_ATAK_TocMgr", createHashMap];
private _hostOf = { params ["_o"]; private _h = _o getVariable ["COMSPEC_ATAK_TocHost", objNull]; [_o, _h] select (!isNull _h) };
private _reg = { (missionNamespace getVariable ["COMSPEC_ATAK_TocScreens", []]) select { !isNull _x && {alive _x} && {(count (_x getVariable ["COMSPEC_ATAK_TocScr", []])) >= 8} } };
private _halls = { (missionNamespace getVariable ["COMSPEC_ATAK_TocHalls", []]) select { _x isEqualType [] && {(count _x) >= 3} } };
// ---- TABLE _eden (JSON) : attributs Eden d'un écran lus et écrits par le tableau [champ, attribut, défaut].
private _eden = [["mode", "COMSPEC_ATAK_EdScreenMode", ""], ["name", "COMSPEC_ATAK_EdScreenName", ""], ["view", "COMSPEC_ATAK_EdScreenView", "SPLIT"], ["bright", "COMSPEC_ATAK_EdScreenBright", 100], ["hall", "COMSPEC_ATAK_EdScreenHall", ""]];
// ---- FIN
private _edGet = { params ["_e", "_k"]; private _row = (_eden select { (_x select 0) isEqualTo _k }) param [0, ["", "", ""]]; (_e get3DENAttribute (_row select 1)) param [0, _row select 2] };
private _edSet = { params ["_e", "_k", "_v"]; private _row = (_eden select { (_x select 0) isEqualTo _k }) param [0, []]; if (_row isNotEqualTo []) then { _e set3DENAttribute [_row select 1, _v]; }; };
// Point de vue : caméra d'Eden, caméra du Zeus, sinon le joueur.
private _eye = {
    if (is3DEN) exitWith { getPosATL get3DENCamera };
    if (!isNull curatorCamera && {!isNull (findDisplay 312)}) exitWith { getPosATL curatorCamera };
    getPosATL player
};
// Grille d'un mur : colonnes x rangées lues dans le nom de la classe (« Wall4x3 »), sinon une rangée.
private _gridOf = {
    params ["_h"];
    private _n = (getNumber (configOf _h >> "comspec_screenPanels")) max 1;
    private _m = (typeOf _h) regexFind ["([0-9])x([0-9])"];
    if (_m isEqualTo []) exitWith { [_n, 1] };
    private _cols = parseNumber (((_m select 0) select 1) select 0);
    private _rws = parseNumber (((_m select 0) select 2) select 0);
    if (_cols * _rws < _n || {_cols < 1}) exitWith { [_n, 1] };
    [_cols, _rws]
};

switch (_mode) do {
    case "canEdit": {
        is3DEN || {isServer} || {!isNull (getAssignedCuratorLogic player)} || {serverCommandAvailable "#kick"}
    };
    case "uniqueName": {
        private _base = if (_a isEqualType "" && {(trim _a) isNotEqualTo ""}) then { (trim _a) select [0, 28] } else { "TOC" };
        private _used = if (is3DEN) then {
            ((all3DENEntities param [0, []]) apply { toUpper ([_x, "name"] call _edGet) }) select { _x isNotEqualTo "" }
        } else {
            (call _reg) apply { toUpper ((_x getVariable ["COMSPEC_ATAK_TocScr", []]) param [0, ""]) }
        };
        if !((toUpper _base) in _used) exitWith { _base };
        private _n = 2;
        while { (toUpper (format ["%1 %2", _base, _n])) in _used && {_n < 200} } do { _n = _n + 1; };
        format ["%1 %2", _base, _n]
    };
    case "uniqueHall": {
        private _base = if (_a isEqualType "" && {(trim _a) isNotEqualTo ""}) then { (toUpper (trim _a)) select [0, 28] } else { "SALLE TOC" };
        private _used = (call _halls) apply { toUpper (_x select 1) };
        if !(_base in _used) exitWith { _base };
        private _n = 2;
        while { (format ["%1 %2", _base, _n]) in _used && {_n < 200} } do { _n = _n + 1; };
        format ["%1 %2", _base, _n]
    };
    case "sourceLabel": {
        private _k = _a;
        if !(_k isEqualType "") exitWith { "" };
        switch (true) do {
            case (_k isEqualTo "OFF"): { ("éteint (veille)") call _tr };
            case (_k isEqualTo "BARS"): { ("mire (barres de couleur)") call _tr };
            case (_k in ["SAT1", "SAT2"]): { format [("satellite %1") call _tr, _k select [3]] };
            case ((_k select [0, 3]) isEqualTo "SQ|"): { format [("escouade %1") call _tr, _k select [3]] };
            case ((_k select [0, 4]) isEqualTo "MOS|"): { format [("mosaïque de caméras %1 × %1") call _tr, (_k splitString "|") param [1, "2"]] };
            case ((_k select [0, 4]) isEqualTo "CAM|"): {
                private _src = objectFromNetId (_k select [4]);
                if (isNull _src) exitWith { ("caméra perdue") call _tr };
                private _l = ((["sources"] call comspec_atak_native_fnc_tocCam) select { (_x select 1) isEqualTo (_k select [4]) }) param [0, []];
                // Genre et nom de la caméra, distance, état, et combien d'écrans la montrent déjà.
                private _id = _k select [4];
                private _used = { ((_x getVariable ["COMSPEC_ATAK_TocScr", []]) param [3, ""]) isEqualTo "CAM" && {(_x getVariable ["COMSPEC_ATAK_TocCam", ""]) isEqualTo _id} } count (call _reg);
                format ["%1 · %2 m · %3%4", [(_l param [0, "caméra"]) call _tr, (getText (configOf _src >> "displayName")) call _tr] select (_l isEqualTo []), round (_src distance (call _eye)),
                    ([("hors ligne") call _tr, ("en ligne") call _tr] select (alive _src && {_l isNotEqualTo []})), ["", format [(" · sur %1 écran(s)") call _tr, _used]] select (_used > 0)]
            };
            case ((_k select [0, 3]) isEqualTo "BC|"): {
                private _s = ((call _reg) select { (_x getVariable ["COMSPEC_ATAK_TocUid", ""]) isEqualTo (_k select [3]) }) param [0, objNull];
                format [("recopie de %1") call _tr, [(_s getVariable ["COMSPEC_ATAK_TocScr", []]) param [0, "?"], ("source perdue") call _tr] select (isNull _s)]
            };
            default { ((["title", _k] call _ts) param [1, toLower _k]) call _tr };
        }
    };
    case "frames": {
        // ---- TABLE _frames (JSON) : cadrages proposés [libellé, clé, demi-largeur de carte en m (0 : automatique, -1 : inchangée)].
        private _frames = [["inchangé", "", -1], ["automatique (tout le réseau)", "AUTO", 0], ["zone (500 m)", "Z500", 500], ["secteur (1,5 km)", "Z1500", 1500], ["théâtre (4 km)", "Z4000", 4000],
            ["zone, centrée sur le point regardé", "H500", 500], ["secteur, centré sur le point regardé", "H1500", 1500]];
        // ---- FIN
        _frames
    };
    case "frameOf": {
        // Cadrage -> [demi-largeur de carte en m (-1 : inchangée, 0 : automatique), centrer sur le point regardé, zoom
        // affiché du satellite (0 : inchangé ; 250 m de demi-largeur = 1x, borné de 0,25x à 8x)]. Fonction pure.
        private _row = ((["frames"] call _fn) select { (_x select 1) isEqualTo _a }) param [0, ["", "", -1]];
        private _half = _row select 2;
        [_half, (_a select [0, 1]) isEqualTo "H", [0, ((250 / (_half max 1)) max 0.25) min 8] select (_half > 0)]
    };
    case "setView": {
        // Vue d'une dalle : cadrage de la carte (affichages à carte) ou zoom, centre, capteur et suivi du satellite
        // (affichage satellite : réglage du satellite du camp, partagé par tous les écrans qui le montrent).
        _a params [["_o", objNull], ["_frame", ""], ["_satDo", ""], ["_here", []]];
        if (isNull _o || {is3DEN}) exitWith { false };
        private _c = _o getVariable ["COMSPEC_ATAK_TocScr", []];
        private _view = _c param [3, ""];
        private _side = [_c param [1, ""], str side group player] select ((_c param [1, ""]) isEqualTo "");
        private _isSat = _view isEqualTo "SAT";
        private _n = ((_c param [8, 0]) max 0) min 1;
        private _sat = { ["do", [_side, "sat", [_n, _this select 0, _this select 1], ["me"] call comspec_atak_native_fnc_tocRoom, getPlayerUID player]] call comspec_atak_native_fnc_tocRoom; };
        (["frameOf", _frame] call _fn) params ["_half", "_center", "_zoom"];
        if (_view in ["SPLIT", "MAP", "TOC", "SQUAD"]) then {
            if (_half isEqualTo 0) then { ["view", [_o, "auto"]] call _ts; };
            if (_half > 0) then { ["view", [_o, "zoom", _half]] call _ts; };
            if (_center && {(count _here) >= 2}) then { ["view", [_o, "center", [_here select 0, _here select 1]]] call _ts; };
        };
        if (_isSat) then {
            if (_zoom > 0) then { ["zoomTo", _zoom] call _sat; };
            if (_center && {(count _here) >= 2}) then { ["center", [_here select 0, _here select 1]] call _sat; };
            switch (_satDo select [0, 2]) do {
                case "M:": { ["mode", _satDo select [2]] call _sat; };
                case "G:": { ["follow", ["G", _satDo select [2]]] call _sat; };
                default { if (_satDo isEqualTo "U") then { ["unfollow", []] call _sat; }; };
            };
        };
        _o setVariable ["COMSPEC_ATAK_TocAt", -10];
        true
    };
    case "category": {
        // Famille d'une source, pour la liste en deux temps : CARTES, CAMÉRAS, SATELLITE, DONNÉES, TEMPS, DIFFUSION, SPÉCIAL.
        private _h = if (_a isEqualType "") then { (_a splitString "|") param [0, _a] } else { "" };
        switch (true) do {
            case (_h in ["SPLIT", "MAP", "TOC"]): { "MAP" };
            case (_h in ["CAM", "MOS", "MOSAIC", "COLC"]): { "CAM" };
            case (_h in ["SAT", "SAT1", "SAT2"]): { "SAT" };
            case (_h in ["CLOCK", "STRIP", "COLA"]): { "TIME" };
            case (_h in ["BARS", "BC", "BROADCAST"]): { "BC" };
            case (_h in ["TEXT", "OFF"]): { "MISC" };
            default { "DATA" };
        }
    };
    case "groups": {
        // Sources rangées par famille : [[titre, famille, [[libellé, clé]...]]...], familles vides retirées. Sans effet.
        private _list = if (_a isEqualType []) then { _a } else { [] };
        // ---- TABLE _cats (JSON) : familles de sources, dans l'ordre de la liste.
        private _cats = [["CARTES", "MAP"], ["CAMÉRAS", "CAM"], ["SATELLITE", "SAT"], ["DONNÉES", "DATA"], ["TEMPS", "TIME"], ["DIFFUSION", "BC"], ["SPÉCIAL", "MISC"]];
        // ---- FIN
        (_cats apply {
            private _id = _x select 1;
            [_x select 0, _id, _list select { (["category", _x param [1, ""]] call _fn) isEqualTo _id }]
        }) select { (_x select 2) isNotEqualTo [] }
    };
    case "catLabel": {
        (createHashMapFromArray [["MAP", "CARTES"], ["CAM", "CAMÉRAS"], ["SAT", "SATELLITE"], ["DATA", "DONNÉES"], ["TIME", "TEMPS"], ["BC", "DIFFUSION"], ["MISC", "SPÉCIAL"]]) getOrDefault [["category", _a] call _fn, "DONNÉES"]
    };
    case "cat": {
        // Famille choisie : la liste « Source » ne montre plus que les sources de cette famille.
        private _cSrc = _st getOrDefault ["src", controlNull];
        if (isNull _cSrc) exitWith { false };
        private _items = ((_st getOrDefault ["groups", []]) param [_a, ["", "", []]]) select 2;
        lbClear _cSrc;
        { private _i = _cSrc lbAdd (_x select 0); _cSrc lbSetData [_i, _x select 1]; } forEach _items;
        private _want = _st getOrDefault ["srcWant", ""];
        _cSrc lbSetCurSel ((_items findIf { (_x select 1) isEqualTo _want }) max 0);
        true
    };
    case "sourceOf": {
        private _o = _a;
        if (_o getVariable ["COMSPEC_ATAK_TocOff", false]) exitWith { "OFF" };
        private _c = _o getVariable ["COMSPEC_ATAK_TocScr", []];
        private _v = _c param [3, "SPLIT"];
        switch (_v) do {
            case "SAT": { ["SAT1", "SAT2"] select ((_c param [8, 0]) isEqualTo 1) };
            case "SQUAD": { "SQ|" + (_c param [9, ""]) };
            case "MOSAIC": { format ["MOS|%1|AUTO", ((_o getVariable ["COMSPEC_ATAK_TocMosaic", []]) param [0, 2])] };
            case "CAM": { "CAM|" + (_o getVariable ["COMSPEC_ATAK_TocCam", ""]) };
            case "BROADCAST": {
                private _id = (["cfg", _o] call comspec_atak_native_fnc_tocBroadcast) select 0;
                ["BC|" + _id, "BARS"] select (_id isEqualTo "")
            };
            default { _v };
        }
    };
    case "sources": {
        // Sources d'une dalle : affichages permis par son format, puis sources vivantes (escouades, caméras, écrans à recopier).
        private _o = _a;
        private _ok = (["format", _o] call _ts) param [4, []];
        private _out = [];
        {
            switch (_x) do {
                case "SAT": { _out pushBack "SAT1"; _out pushBack "SAT2"; };
                case "MOSAIC": { _out pushBack "MOS|2|AUTO"; _out pushBack "MOS|3|AUTO"; };
                case "SQUAD"; case "CAM": {};
                case "BROADCAST": { _out pushBack "BARS"; };
                default { _out pushBack _x; };
            };
        } forEach ((["views"] call _ts) select { _x in _ok });
        if (!is3DEN) then {
            if ("SQUAD" in _ok || {"PLATE" in _ok}) then {
                private _side = (_o getVariable ["COMSPEC_ATAK_TocScr", []]) param [1, ""];
                { _out pushBack ("SQ|" + _x); } forEach ((["squads", _side] call _ts) select [0, 12]);
            };
            if ("CAM" in _ok) then { { _out pushBack ("CAM|" + (_x select 1)); } forEach ((["sources"] call comspec_atak_native_fnc_tocCam) select [0, 12]); };
            if ("BROADCAST" in _ok) then {
                {
                    private _uid = _x getVariable ["COMSPEC_ATAK_TocUid", ""];
                    if (_x isNotEqualTo _o && {_uid isNotEqualTo ""} && {((_x getVariable ["COMSPEC_ATAK_TocScr", []]) param [3, ""]) isNotEqualTo "BROADCAST"}
                        && {((["format", _x] call _ts) param [3, ""]) isEqualTo "STD"}) then { _out pushBack ("BC|" + _uid); };
                } forEach ((call _reg) select [0, 40]);
            };
        };
        _out pushBack "OFF";
        _out apply { [["sourceLabel", _x] call _fn, _x] }
    };
    case "setSource": {
        _a params [["_o", objNull], ["_k", ""]];
        if (_k isEqualTo "") exitWith { false };
        // Eden : l'attribut « Affichage » de l'objet (et son usage « écran TOC ») ; les sources vivantes n'existent qu'en jeu.
        if (is3DEN) exitWith {
            private _v = switch (true) do { case (_k in ["SAT1", "SAT2"]): { "SAT" }; case (_k isEqualTo "BARS"): { "BROADCAST" }; case ((_k select [0, 4]) isEqualTo "MOS|"): { "MOSAIC" }; default { _k }; };
            if ((_v find "|") >= 0) exitWith { false };
            [_o, "mode", "toc"] call _edSet;
            [_o, "view", _v] call _edSet;
            if (_k in ["SAT1", "SAT2"]) then { _o set3DENAttribute ["COMSPEC_ATAK_EdScreenSat", parseNumber (_k isEqualTo "SAT2")]; };
            true
        };
        if (isNull _o) exitWith { false };
        private _ok = (["format", _o] call _ts) param [4, []];
        private _power = {
            params ["_on"];
            if ((_o getVariable ["COMSPEC_ATAK_TocOff", false]) isEqualTo _on) then {
                if (["local", _o] call comspec_atak_native_fnc_tocConfig && {!isServer}) then { _o setVariable ["COMSPEC_ATAK_TocOff", !_on, true]; } else { ["do", ["power", [_o, _on]]] call comspec_atak_native_fnc_tocHall; };
            };
        };
        if (_k isEqualTo "OFF") exitWith { [false] call _power; true };
        private _view = switch (true) do {
            case (_k in ["SAT1", "SAT2"]): { "SAT" };
            case ((_k select [0, 3]) isEqualTo "SQ|"): { ["SQUAD", "PLATE"] select (!("SQUAD" in _ok) && {"PLATE" in _ok}) };
            case ((_k select [0, 4]) isEqualTo "MOS|"): { "MOSAIC" };
            case ((_k select [0, 4]) isEqualTo "CAM|"): { "CAM" };
            case (_k isEqualTo "BARS" || {(_k select [0, 3]) isEqualTo "BC|"}): { "BROADCAST" };
            default { _k };
        };
        if !(_view in _ok) exitWith { false };
        [true] call _power;
        switch (true) do {
            case (_k in ["SAT1", "SAT2"]): { ["view", [_o, "sat", parseNumber (_k isEqualTo "SAT2")]] call _ts; };
            case ((_k select [0, 3]) isEqualTo "SQ|"): { ["view", [_o, "squad", _k select [3]]] call _ts; };
            case ((_k select [0, 4]) isEqualTo "MOS|"): { ["view", [_o, "mosaic", ["key", _k] call comspec_atak_native_fnc_tocMosaic]] call _ts; };
            case ((_k select [0, 4]) isEqualTo "CAM|"): { ["view", [_o, "cam", _k select [4]]] call _ts; };
            case (_view isEqualTo "BROADCAST"): {
                private _c = ["cfg", _o] call comspec_atak_native_fnc_tocBroadcast;
                private _cur = (_o getVariable ["COMSPEC_ATAK_TocScr", []]) param [3, "SPLIT"];
                _c set [0, ["", _k select [3]] select ((_k select [0, 3]) isEqualTo "BC|")];
                if (_cur isNotEqualTo "BROADCAST") then { _c set [5, _cur]; };
                ["view", [_o, "bc", _c]] call _ts;
            };
        };
        ["view", [_o, "mode", _view]] call _ts;
        _o setVariable ["COMSPEC_ATAK_TocAt", -10];
        true
    };
    case "feeds": {
        // Flux standard d'un lot d'écrans, d'après le format de chaque dalle : le mur d'images montre la situation, les
        // écrans 16:9 de côté tournent sur satellite, caméras, réseau, flux et horloge, les bandeaux l'horloge, les
        // bandeaux à LED le texte, les plaques une escouade chacune, les postes PC la console de l'opérateur.
        private _list = if (_a isEqualType []) then { _a } else { [] };
        private _side = ["SAT1", "MOS|2|AUTO", "NET", "FEED", "CLOCK", "SAT2", "LOG", "EXCHECK"];
        private _si = 0;
        private _sq = [];
        private _qi = 0;
        private _out = [];
        {
            private _o = _x;
            private _h = [_o] call _hostOf;
            (["format", _o] call _ts) params ["", "", "", ["_kind", "STD"], ["_ok", []], ["_def", "SPLIT"]];
            private _k = switch (true) do {
                case ((getNumber (configOf _h >> "comspec_tocPost")) > 0 && {_o isEqualTo _h}): { "TOC" };
                case (_kind isEqualTo "STD" && {(getNumber (configOf _h >> "comspec_screenPanels")) > 1} && {_o isEqualTo _h}): { "SPLIT" };
                case (_kind isEqualTo "STD"): { _si = _si + 1; _side select ((_si - 1) mod (count _side)) };
                case ("PLATE" in _ok && {!("STRIP" in _ok)}): {
                    if (_sq isEqualTo [] && {!is3DEN}) then { _sq = ["squads", (_o getVariable ["COMSPEC_ATAK_TocScr", []]) param [1, ""]] call _ts; };
                    _qi = _qi + 1;
                    if ((count _sq) >= _qi) then { "SQ|" + (_sq select (_qi - 1)) } else { "PLATE" }
                };
                case (_kind isEqualTo "STRIP"): { "STRIP" };
                case (_kind isEqualTo "LED"): { "TEXT" };
                default { _def };
            };
            _out pushBack [_o, _k];
        } forEach _list;
        _out
    };
    case "rows": {
        private _from = call _eye;
        if (is3DEN) exitWith {
            private _rows = [];
            {
                private _cfg = configOf _x;
                if ((getNumber (_cfg >> "comspec_screen")) < 1 && {([_x, "mode"] call _edGet) isNotEqualTo "toc"}) then { continue };
                private _isToc = ([_x, "mode"] call _edGet) isEqualTo "toc";
                private _v = [_x, "view"] call _edGet;
                private _nm = [_x, "name"] call _edGet;
                _rows pushBack [str (get3DENEntityID _x), _x, [_nm, getText (_cfg >> "displayName")] select (_nm isEqualTo ""), getText (_cfg >> "displayName"), [_x, "hall"] call _edGet,
                    round (_x distance _from), _v, [("réglé au lancement") call _tr, ["sourceLabel", _v] call _fn] select _isToc, [_x, "bright"] call _edGet,
                    [("se règle seul") call _tr, ("attributs Eden") call _tr] select _isToc, ["muted", "accent"] select _isToc, _x, 0];
            } forEach (all3DENEntities param [0, []]);
            {
                private _t = typeOf _x;
                if !(_t in ["COMSPEC_ATAK_Module_TocHall", "COMSPEC_ATAK_Module_TocScreen", "COMSPEC_ATAK_Module_TocPc"]) then { continue };
                private _p = _t select [20];
                private _nm = (_x get3DENAttribute format ["COMSPEC_ATAK_Module_%1_Name", _p]) param [0, ""];
                private _src = switch (_p) do {
                    case "TocHall": { format [("salle %1") call _tr, (_x get3DENAttribute "COMSPEC_ATAK_Module_TocHall_Size") param [0, "M"]] };
                    case "TocScreen": { ["sourceLabel", (_x get3DENAttribute "COMSPEC_ATAK_Module_TocScreen_View") param [0, "SPLIT"]] call _fn };
                    default { ("console du poste") call _tr };
                };
                _rows pushBack [str (get3DENEntityID _x), _x, [_nm, _p] select (_nm isEqualTo ""), format [("Module · %1") call _tr, getText (configOf _x >> "displayName")], "", round (_x distance _from), "", _src, 100,
                    ("posé au lancement") call _tr, "blue", _x, 0];
            } forEach (all3DENEntities param [3, []]);
            _rows
        };
        private _hn = createHashMap;
        { _hn set [_x select 0, _x select 1]; } forEach (call _halls);
        private _rows = [];
        {
            private _o = _x;
            private _h = [_o] call _hostOf;
            private _c = _o getVariable ["COMSPEC_ATAK_TocScr", []];
            private _np = (getNumber (configOf _h >> "comspec_screenPanels")) max 1;
            private _k = [0, _o getVariable ["COMSPEC_ATAK_TocPanel", 0]] select (_o isNotEqualTo _h);
            private _mdl = getText (configOf _h >> "displayName");
            private _type = _mdl call _tr;
            if (_np > 1) then { _type = format [("%1 · dalle %2/%3") call _tr, _type, _k + 1, _np]; };
            private _src = ["sourceOf", _o] call _fn;
            // Nom montré : celui donné par quelqu'un ; sinon ce que montre la dalle (« CARTE ET FLUX », « SATELLITE 1 · 2 »),
            // jamais le nom anglais du modèle coupé (« Large curved wall screen, 2 imag… »).
            private _nm = _c param [0, "TOC"];
            if (_nm in ["", "TOC"] || {_mdl isNotEqualTo "" && {(_nm find (_mdl select [0, 20])) >= 0}}) then {
                _nm = toUpper (["sourceLabel", [_src, _c param [3, "SPLIT"]] select (_src isEqualTo "OFF")] call _fn);
                if (_np > 1) then { _nm = format ["%1 · %2", _nm, _k + 1]; };
            };
            private _hall = _h getVariable ["COMSPEC_ATAK_TocHall", ""];
            private _cs = _c param [1, ""];
            private _fed = ((missionNamespace getVariable [format ["COMSPEC_ATAK_TocFeed_%1", [_cs, str side group player] select (_cs isEqualTo "")], []]) param [0, -1]) >= 0;
            private _state = switch (true) do {
                case (_src isEqualTo "OFF"): { ["éteint", "muted"] };
                case ((_c param [3, ""]) in ["SPLIT", "MAP", "TOC", "SQUAD", "FEED", "NET", "COLB", "CAM"] && {!_fed}): { ["aucun signal", "amber"] };
                case (_h getVariable ["COMSPEC_ATAK_TocFb", false]): { ["en ligne (joueur)", "accent"] };
                default { ["en ligne", "accent"] };
            };
            _rows pushBack [netId _o, _o, _nm, _type, _hn getOrDefault [_hall, ""], round (_o distance _from), _src, ["sourceLabel", _src] call _fn,
                ((_h getVariable ["COMSPEC_ATAK_TocOpt", []]) param [0, 100]), (_state select 0) call _tr, _state select 1, _h, _k];
        } forEach (call _reg);
        // Écran hôte puis ses dalles, salles regroupées, du plus proche au plus loin.
        [_rows, [], { format ["%1|%2|%3|%4", _x select 4, 100000 + ((_x select 11) distance _from), str (_x select 11), 100 + (_x select 12)] }, "ASCEND"] call BIS_fnc_sortBy
    };
    case "say": {
        private _c = _st getOrDefault ["status", controlNull];
        if (!isNull _c) then { _c ctrlSetStructuredText parseText format ["<t size='0.9' color='%1'>%2</t>", ["hex", "accent"] call comspec_atak_native_fnc_tocTheme, (_a call _tr)]; };
        true
    };
    case "open": {
        if (!hasInterface) exitWith { false };
        // Lancé depuis un menu (Eden, ACE) ou un formulaire qui se referme : la fenêtre est créée juste après.
        if (!canSuspend) exitWith { [_mode, _a] spawn { uiSleep 0.08; _this call comspec_atak_native_fnc_tocManager; }; true };
        private _parent = switch (true) do {
            case (is3DEN): { findDisplay 313 };
            case (!isNull (findDisplay 312)): { findDisplay 312 };
            default { findDisplay 46 };
        };
        if (isNull _parent) exitWith { false };
        private _old = _st getOrDefault ["d", displayNull];
        if (!isNull _old) then { _old closeDisplay 2; };
        private _d = _parent createDisplay "RscDisplayEmpty";
        if (isNull _d) exitWith { false };
        private _col = { params ["_k", ["_al", 1]]; ["rgba", [_k, _al]] call comspec_atak_native_fnc_tocTheme };
        private _w = safeZoneW * 0.86;
        private _h = safeZoneH * 0.8;
        private _x0 = safeZoneX + (safeZoneW - _w) / 2;
        private _y0 = safeZoneY + (safeZoneH - _h) / 2;
        private _u = safeZoneH * 0.006;
        private _mk = {
            params ["_cls", "_pos", ["_idc", -1]];
            private _c = _d ctrlCreate [_cls, _idc];
            _c ctrlSetPosition _pos;
            _c ctrlCommit 0;
            _c
        };
        private _dim = ["ctrlStatic", [safeZoneX, safeZoneY, safeZoneW, safeZoneH]] call _mk;
        _dim ctrlSetBackgroundColor [0, 0, 0, 0.5];
        private _bg = ["ctrlStatic", [_x0, _y0, _w, _h]] call _mk;
        _bg ctrlSetBackgroundColor (["bg0", 0.98] call _col);
        private _title = ["ctrlStatic", [_x0, _y0, _w, 6 * _u]] call _mk;
        _title ctrlSetBackgroundColor (["bg2"] call _col);
        _title ctrlSetTextColor (["text"] call _col);
        private _rule = ["ctrlStatic", [_x0, _y0 + 6 * _u, _w, 0.4 * _u]] call _mk;
        _rule ctrlSetBackgroundColor (["accent"] call _col);
        // ---- Barre d'outils : deux rangées de boutons [libellé, action, aide (avec un exemple), réservé à qui peut poser].
        private _tools = [
            [["Ajouter", "add", "Pose un écran du type choisi devant vous (Zeus : à la caméra ; Eden : au centre de la vue). Exemple : un écran 98 pouces sur pied.", true],
             ["Dupliquer", "dup", "Pose une copie de l'écran de la ligne choisie, 2 m à sa droite, avec la même source. Exemple : un second écran satellite.", true],
             ["Supprimer", "del", "Retire les lignes cochées (sinon la ligne choisie). L'écran posé par le mod part avec ; un objet de la mission redevient un simple objet.", true],
             ["Lier à la salle", "link", "Lie les lignes cochées à la salle choisie à droite. Exemple : un écran ajouté après coup rejoint SALLE TOC.", false],
             ["Générer la salle", "room", "Pose une salle d'opérations complète (petite, moyenne ou grande) devant vous, écrans et postes déjà liés.", true],
             ["Générer tous les flux", "feeds", "Donne d'un coup un flux standard à chaque écran de la salle : mur = situation, écrans de côté = satellite, caméras, réseau, bandeaux = horloge et texte, plaques = escouades.", false],
             ["Disposition", "layout", "Applique une disposition au mur ou à l'écran incurvé de la ligne choisie. Exemple : Quad (carte, satellite, caméras, réseau).", false]],
            [["Aller", "go", "Amène la caméra (Zeus, Eden) devant l'écran de la ligne choisie.", false],
             ["Identifier", "blink", "L'écran de la ligne choisie clignote 2 s chez les joueurs proches, pour le reconnaître dans la salle.", false],
             ["Écrire un texte", "text", "Ouvre l'éditeur de message des bandeaux et écrans en affichage Texte. Exemple : EXERCICE EN COURS.", false],
             ["Diffuser dans la salle", "bcOn", "Tous les écrans 16:9 de la salle montrent l'écran de la ligne choisie, en même temps. Exemple : le mur recopié sur chaque poste.", false],
             ["Arrêter la diffusion", "bcOff", "Chaque écran de la salle reprend l'affichage qu'il avait avant la diffusion.", false],
             ["Enregistrer", "save", "Garde sous le nom écrit à droite la source et la luminosité de chaque écran (profil du joueur, par carte).", false],
             ["Charger", "load", "Remet les sources et luminosités du préréglage dont le nom est écrit à droite.", false],
             ["Fermer", "close", "Ferme le tableau. Tout est déjà appliqué et gardé avec les écrans.", false]]
        ];
        private _can = ["canEdit"] call _fn;
        private _helps = createHashMap;
        {
            private _row = _x;
            private _ri = _forEachIndex;
            private _bw = (_w - 2 * _u) / 8;
            {
                _x params ["_lbl", "_act", "_help", "_need"];
                private _b = ["ctrlButton", [_x0 + _u + _forEachIndex * _bw, _y0 + 7.4 * _u + _ri * 5.6 * _u, _bw - 0.6 * _u, 5 * _u]] call _mk;
                _b ctrlSetText (_lbl call _tr);
                _b ctrlSetTooltip (_help call _tr);
                _b setVariable ["COMSPEC_ATAK_Act", _act];
                _b ctrlEnable (!_need || {_can});
                _b ctrlAddEventHandler ["ButtonClick", { ["act", (_this select 0) getVariable ["COMSPEC_ATAK_Act", ""]] call comspec_atak_native_fnc_tocManager; }];
                _b ctrlAddEventHandler ["MouseEnter", { ["help", (_this select 0) getVariable ["COMSPEC_ATAK_Act", ""]] call comspec_atak_native_fnc_tocManager; }];
                _helps set [_act, _help];
            } forEach _row;
        } forEach _tools;
        // ---- Tableau (gauche) ----
        private _top = _y0 + 19.4 * _u;
        private _lw = _w * 0.7;
        private _lh = _h - 19.4 * _u - 13 * _u;
        private _head = ["ctrlListNBox", [_x0 + _u, _top, _lw, 4.4 * _u]] call _mk;
        private _list = ["ctrlListNBox", [_x0 + _u, _top + 4.4 * _u, _lw, _lh - 4.4 * _u], 100] call _mk;
        private _cols = [0, 0.03, 0.2, 0.44, 0.55, 0.61, 0.79, 0.85];
        { _x lnbSetColumnsPos _cols; } forEach [_head, _list];
        _head lnbAddRow (["", "Nom", "Type et dalle", "Salle", "Dist.", "Source montrée", "Lum.", "État"] apply { _x call _tr });
        for "_i" from 0 to 7 do { _head lnbSetColor [[0, _i], ["muted"] call _col]; };
        _head ctrlEnable false;
        _list ctrlAddEventHandler ["LBSelChanged", { ["pick", _this select 1] call comspec_atak_native_fnc_tocManager; }];
        _list ctrlAddEventHandler ["LBDblClick", { ["check", _this select 1] call comspec_atak_native_fnc_tocManager; }];
        // ---- Panneau de la ligne choisie (droite) ----
        private _px = _x0 + _lw + 3 * _u;
        private _pw = _w - _lw - 4 * _u;
        private _pbg = ["ctrlStatic", [_px - _u, _top, _pw + _u, _lh]] call _mk;
        _pbg ctrlSetBackgroundColor (["bg1"] call _col);
        private _selTitle = ["ctrlStructuredText", [_px, _top + _u, _pw - _u, 9 * _u]] call _mk;
        // Plan du mur : jusqu'à 24 cases, une par dalle de l'écran choisi.
        private _wallY = _top + 10.5 * _u;
        private _wallH = 15 * _u;
        private _cells = [];
        for "_i" from 0 to 23 do {
            private _b = ["ctrlButton", [0, 0, 0, 0]] call _mk;
            _b setVariable ["COMSPEC_ATAK_Cell", _i];
            _b ctrlAddEventHandler ["ButtonClick", { ["cell", (_this select 0) getVariable ["COMSPEC_ATAK_Cell", 0]] call comspec_atak_native_fnc_tocManager; }];
            _b ctrlShow false;
            _cells pushBack _b;
        };
        private _fy = _wallY + _wallH + 2 * _u;
        // Champs : libellés posés tout de suite, contrôles créés ensuite du bas vers le haut. Une liste déroulante
        // ouverte est dessinée avec son contrôle : créée après ceux du dessous, elle passe au-dessus d'eux (avant, les
        // champs et les boutons du dessous s'écrivaient par-dessus la liste ouverte).
        private _specs = [];
        private _field = {
            params ["_lbl", "_cls", "_key", "_help"];
            private _l = ["ctrlStatic", [_px, _fy, _pw * 0.34, 4.6 * _u]] call _mk;
            _l ctrlSetText (_lbl call _tr);
            _l ctrlSetTextColor (["text2"] call _col);
            _specs pushBack [_cls, [_px + _pw * 0.35, _fy, _pw * 0.63, 4.6 * _u], _key, _help];
            _helps set [_key, _help];
            _fy = _fy + 5.4 * _u;
        };
        ["Nom", "ctrlEdit", "fName", "Nom affiché sur l'écran et dans les menus. Exemple : MUR NORD."] call _field;
        ["Famille de source", "ctrlCombo", "fCat", "Genre de ce que montre la dalle : cartes, caméras, satellite, données du poste, temps, diffusion, spécial. La liste Source dessous ne montre que cette famille. Exemple : CAMÉRAS pour choisir quelle caméra."] call _field;
        ["Source", "ctrlCombo", "fSrc", "Ce que montre la dalle, dans la famille choisie. Une caméra est nommée par son genre et son porteur, avec sa distance et son état. Exemple : Caméra poitrine · TA1 · 120 m · en ligne."] call _field;
        ["Cadrage", "ctrlCombo", "fView", "Cadrage de la carte ou zoom du satellite de la dalle : automatique, zone (500 m), secteur (1,5 km), théâtre (4 km), ou centré sur le point que vous regardez. Appliquer à « toute la salle » copie le cadrage. Exemple : secteur."] call _field;
        ["Satellite", "ctrlCombo", "fSat", "Pour une dalle en satellite : capteur (jour, vision nocturne, thermique) ou suivi d'un groupe, qui garde ses troupes au centre de l'image. Exemple : suivre Alpha 1-1."] call _field;
        ["Luminosité", "ctrlCombo", "fBright", "Image assombrie pour une salle dans le noir. Exemple : 50 % de nuit."] call _field;
        ["Salle", "ctrlCombo", "fHall", "Salle à laquelle l'écran est lié (bouton Lier à la salle). Exemple : SALLE TOC."] call _field;
        ["Titre de diffusion", "ctrlEdit", "fTitle", "Texte écrit sur la mire et à côté du témoin EN DIRECT. Exemple : TOC ALPHA · CANAL 1."] call _field;
        ["Appliquer à", "ctrlCombo", "fScope", "Portée du bouton Appliquer : cette dalle, les lignes cochées (double clic sur une ligne), toute la salle de la ligne, ou tous les écrans."] call _field;
        ["Type à poser", "ctrlCombo", "fKind", "Écran posé par Ajouter, ou taille de la salle posée par Générer la salle. Exemple : Mur d'images 3x2."] call _field;
        ["Préréglage", "ctrlEdit", "fPreset", "Nom du préréglage pour Enregistrer et Charger. Exemple : BRIEFING."] call _field;
        private _apply = ["ctrlButton", [_px, _fy + _u, _pw * 0.48, 5 * _u]] call _mk;
        _apply ctrlSetText (("Appliquer") call _tr);
        _apply ctrlAddEventHandler ["ButtonClick", { ["act", "apply"] call comspec_atak_native_fnc_tocManager; }];
        private _chk = ["ctrlButton", [_px + _pw * 0.5, _fy + _u, _pw * 0.48, 5 * _u]] call _mk;
        _chk ctrlSetText (("Cocher / décocher") call _tr);
        _chk ctrlAddEventHandler ["ButtonClick", { ["check", -1] call comspec_atak_native_fnc_tocManager; }];
        private _ctl = createHashMap;
        reverse _specs;
        {
            _x params ["_cls", "_pos", "_key", "_help"];
            private _c = [_cls, _pos] call _mk;
            _c ctrlSetTooltip (_help call _tr);
            _c setVariable ["COMSPEC_ATAK_Act", _key];
            _c ctrlAddEventHandler ["SetFocus", { ["help", (_this select 0) getVariable ["COMSPEC_ATAK_Act", ""]] call comspec_atak_native_fnc_tocManager; }];
            _ctl set [_key, _c];
        } forEach _specs;
        private _eName = _ctl get "fName";
        private _cCat = _ctl get "fCat";
        private _cSrc = _ctl get "fSrc";
        private _cBright = _ctl get "fBright";
        private _cView = _ctl get "fView";
        private _cSat = _ctl get "fSat";
        { private _i = _cView lbAdd ((_x select 0) call _tr); _cView lbSetData [_i, _x select 1]; } forEach (["frames"] call _fn);
        _cView lbSetCurSel 0;
        private _cHall = _ctl get "fHall";
        private _eTitle = _ctl get "fTitle";
        private _cScope = _ctl get "fScope";
        private _cKind = _ctl get "fKind";
        private _ePreset = _ctl get "fPreset";
        _cCat ctrlAddEventHandler ["LBSelChanged", { ["cat", _this select 1] call comspec_atak_native_fnc_tocManager; }];
        { private _i = _cBright lbAdd format ["%1 %2", _x, "%"]; _cBright lbSetValue [_i, _x]; } forEach [100, 75, 50, 25];
        _cBright lbSetCurSel 0;
        { private _i = _cScope lbAdd ((_x select 0) call _tr); _cScope lbSetData [_i, _x select 1]; } forEach [["cette dalle", "one"], ["les lignes cochées", "checked"], ["toute la salle de la ligne", "hall"], ["tous les écrans", "all"]];
        _cScope lbSetCurSel 0;
        {
            private _i = _cKind lbAdd ((_x select 0) call _tr);
            _cKind lbSetData [_i, _x select 1];
        } forEach ([["Salle petite, 8 x 7 m", "ROOM:S"], ["Salle moyenne, 11 x 8,5 m", "ROOM:M"], ["Salle grande, 16 x 9,5 m", "ROOM:L"]] + ((["classes"] call _ts) apply { [_x select 0, _x select 1] }));
        _cKind lbSetCurSel 3;
        // ---- Aide du réglage survolé, routage de la source, ligne d'état ----
        private _help = ["ctrlStructuredText", [_x0 + _u, _y0 + _h - 12.4 * _u, _w - 2 * _u, 5.6 * _u]] call _mk;
        private _route = ["ctrlStructuredText", [_x0 + _u, _y0 + _h - 6.6 * _u, _w * 0.6, 5.6 * _u]] call _mk;
        private _status = ["ctrlStructuredText", [_x0 + _w * 0.62, _y0 + _h - 6.6 * _u, _w * 0.37, 5.6 * _u]] call _mk;
        _st = createHashMapFromArray [["d", _d], ["list", _list], ["title", _title], ["selTitle", _selTitle], ["cells", _cells], ["wall", [_px, _wallY, _pw - _u, _wallH]],
            ["name", _eName], ["src", _cSrc], ["cat", _cCat], ["view", _cView], ["sat", _cSat], ["bright", _cBright], ["hall", _cHall], ["bcTitle", _eTitle], ["scope", _cScope], ["kind", _cKind], ["preset", _ePreset],
            ["help", _help], ["route", _route], ["status", _status], ["helps", _helps], ["rows", []], ["checked", []], ["cur", ""]];
        uiNamespace setVariable ["COMSPEC_ATAK_TocMgr", _st];
        // Ligne à choisir à l'ouverture (menu de l'écran, clic droit du Zeus).
        private _want = (_a param [0, objNull]);
        if (_want isEqualType objNull && {!isNull _want} && {!is3DEN}) then { _st set ["cur", netId _want]; };
        ["refresh"] call _fn;
        ["help", "open"] call _fn;
        true
    };
    case "help": {
        private _c = _st getOrDefault ["help", controlNull];
        if (isNull _c) exitWith { false };
        private _t = (_st getOrDefault ["helps", createHashMap]) getOrDefault [_a, "Une ligne par dalle. Clic : choisir. Double clic : cocher. Réglez à droite puis Appliquer ; les boutons du haut agissent sur la ligne choisie ou sur les lignes cochées."];
        _c ctrlSetStructuredText parseText format ["<t size='0.9' color='%1'>%2</t>", ["hex", "text2"] call comspec_atak_native_fnc_tocTheme, _t call _tr];
        true
    };
    case "refresh": {
        private _list = _st getOrDefault ["list", controlNull];
        if (isNull _list) exitWith { false };
        private _col = { params ["_k", ["_al", 1]]; ["rgba", [_k, _al]] call comspec_atak_native_fnc_tocTheme };
        private _rows = ["rows"] call _fn;
        _st set ["rows", _rows];
        private _checked = (_st get "checked") select { private _k = _x; (_rows findIf { (_x select 0) isEqualTo _k }) >= 0 };
        _st set ["checked", _checked];
        lnbClear _list;
        {
            _x params ["_key", "", "_nm", "_type", "_hall", "_dist", "", "_srcL", "_bright", "_state", "_tok", "", "_k"];
            private _i = _list lnbAddRow [["", "●"] select (_key in _checked), [_nm, "   " + _nm] select (_k > 0), _type, _hall, format ["%1 m", _dist], _srcL, format ["%1 %2", _bright, "%"], _state];
            _list lnbSetData [[_i, 0], _key];
            _list lnbSetColor [[_i, 0], ["accent"] call _col];
            _list lnbSetColor [[_i, 7], [_tok] call _col];
            if ((_x select 6) isEqualTo "OFF") then { for "_c" from 1 to 6 do { _list lnbSetColor [[_i, _c], ["muted"] call _col]; }; };
        } forEach _rows;
        (_st get "title") ctrlSetText format ["  %1", format [("Tableau des écrans TOC · %1 dalle(s) · %2 salle(s)") call _tr, count _rows, count (call _halls)]];
        private _cur = _st get "cur";
        private _i = (_rows findIf { (_x select 0) isEqualTo _cur }) max 0;
        if ((count _rows) > 0) then { _list lnbSetCurSelRow _i; } else { ["pick", -1] call _fn; };
        true
    };
    case "check": {
        // Coche de la ligne (double clic, ou bouton pour la ligne choisie) : lot visé par « les lignes cochées ».
        private _list = _st getOrDefault ["list", controlNull];
        if (isNull _list) exitWith { false };
        private _i = [_a, lnbCurSelRow _list] select (_a < 0);
        private _key = _list lnbData [_i, 0];
        if (_key isEqualTo "") exitWith { false };
        private _checked = _st get "checked";
        if (_key in _checked) then { _checked = _checked - [_key]; } else { _checked pushBack _key; };
        _st set ["checked", _checked];
        _list lnbSetText [[_i, 0], ["", "●"] select (_key in _checked)];
        ["say", format [("%1 ligne(s) cochée(s)") call _tr, count _checked]] call _fn;
        true
    };
    case "cell": {
        // Clic sur une case du plan du mur : la dalle devient la ligne choisie.
        private _keys = _st getOrDefault ["cellKeys", []];
        private _key = _keys param [_a, ""];
        if (_key isEqualTo "") exitWith { false };
        private _i = (_st get "rows") findIf { (_x select 0) isEqualTo _key };
        if (_i >= 0) then { (_st get "list") lnbSetCurSelRow _i; };
        true
    };
    case "pick": {
        private _rows = _st getOrDefault ["rows", []];
        private _row = _rows param [_a, []];
        private _col = { params ["_k", ["_al", 1]]; ["rgba", [_k, _al]] call comspec_atak_native_fnc_tocTheme };
        private _cells = _st getOrDefault ["cells", []];
        if (_row isEqualTo []) exitWith {
            { _x ctrlShow false; } forEach _cells;
            (_st get "selTitle") ctrlSetStructuredText parseText format ["<t size='1.1' color='%1'>%2</t>", ["hex", "text2"] call comspec_atak_native_fnc_tocTheme,
                ("Aucun écran TOC dans la mission. Ajoutez un écran ou générez une salle avec les boutons du haut.") call _tr];
            false
        };
        _row params ["_key", "_o", "_nm", "_type", "_hall", "", "_src", "_srcL", "_bright", "_state", "", "_host", "_k"];
        _st set ["cur", _key];
        (_st get "selTitle") ctrlSetStructuredText parseText format ["<t size='1.25' font='RobotoCondensedBold' color='%1'>%2</t><br/><t size='0.9' color='%3'>%4 · %5</t>",
            ["hex", "text"] call comspec_atak_native_fnc_tocTheme, _nm, ["hex", "text2"] call comspec_atak_native_fnc_tocTheme, _type, _state];
        (_st get "name") ctrlSetText _nm; // i18n:brut (nom saisi par le joueur)
        // Sources de cette dalle.
        private _opts = ["sources", _o] call _fn;
        if ((_opts findIf { (_x select 1) isEqualTo _src }) < 0) then { _opts pushBack [_srcL, _src]; };
        // Liste en deux temps : la famille (avec le nombre de sources), puis la source dans cette famille.
        private _groups = ["groups", _opts] call _fn;
        _st set ["groups", _groups];
        _st set ["srcWant", _src];
        private _cCat = _st get "cat";
        lbClear _cCat;
        { private _i = _cCat lbAdd format ["%1 (%2)", (_x select 0) call _tr, count (_x select 2)]; _cCat lbSetData [_i, _x select 1]; } forEach _groups;
        private _gi = (_groups findIf { ((_x select 2) findIf { (_x select 1) isEqualTo _src }) >= 0 }) max 0;
        _cCat lbSetCurSel _gi;
        ["cat", _gi] call _fn;
        // Satellite : capteurs, puis un suivi par groupe du camp de l'écran.
        private _cSat = _st get "sat";
        lbClear _cSat;
        private _satOpts = [["inchangé", ""], ["capteur automatique (jour, thermique la nuit)", "M:AUTO"], ["capteur jour", "M:DAY"], ["vision nocturne", "M:NV"], ["thermique blanc chaud", "M:WHOT"], ["thermique noir chaud", "M:BHOT"], ["ne plus suivre personne", "U"]];
        { private _i = _cSat lbAdd ((_x select 0) call _tr); _cSat lbSetData [_i, _x select 1]; } forEach _satOpts;
        if (!is3DEN) then {
            { private _i = _cSat lbAdd format [("suivre le groupe %1") call _tr, _x]; _cSat lbSetData [_i, "G:" + _x]; } forEach ((["squads", (_o getVariable ["COMSPEC_ATAK_TocScr", []]) param [1, ""]] call _ts) select [0, 16]);
        };
        _cSat lbSetCurSel 0;
        (_st get "view") lbSetCurSel 0;
        private _cB = _st get "bright";
        _cB lbSetCurSel (([100, 75, 50, 25] find _bright) max 0);
        private _cH = _st get "hall";
        lbClear _cH;
        private _hl = [[("aucune salle") call _tr, ""]] + ((call _halls) apply { [_x select 1, _x select 0] });
        private _hid = if (is3DEN) then { _hall } else { _host getVariable ["COMSPEC_ATAK_TocHall", ""] };
        if (is3DEN && {_hall isNotEqualTo ""}) then { _hl pushBack [_hall, _hall]; };
        { private _i = _cH lbAdd (_x select 0); _cH lbSetData [_i, _x select 1]; } forEach _hl;
        _cH lbSetCurSel ((_hl findIf { (_x select 1) isEqualTo _hid }) max 0);
        (_st get "bcTitle") ctrlSetText ([(["cfg", _o] call comspec_atak_native_fnc_tocBroadcast) select 1, ""] select is3DEN); // i18n:brut (titre saisi)
        // Plan du mur : une case par dalle de l'écran hôte, la dalle choisie en évidence, sa source écrite dedans.
        (_st get "wall") params ["_wx", "_wy", "_ww", "_wh"];
        private _sib = _rows select { (_x select 11) isEqualTo _host };
        ([_host] call _gridOf) params ["_gc", "_gr"];
        if ((count _sib) <= 1) then { _gc = 1; _gr = 1; };
        private _cw = _ww / _gc;
        private _ch = (_wh / _gr) min (_cw * 0.5625);
        private _keys = [];
        {
            private _b = _x;
            private _r = _sib param [_forEachIndex, []];
            if (_r isEqualTo [] || {_forEachIndex >= _gc * _gr}) then { _b ctrlShow false; _keys pushBack ""; continue };
            private _pk = _r select 12;
            _b ctrlSetPosition [_wx + (_pk mod _gc) * _cw, _wy + (floor (_pk / _gc)) * _ch, _cw * 0.97, _ch * 0.94];
            _b ctrlCommit 0;
            _b ctrlSetText format ["%1 · %2 · %3", _pk + 1, (["catLabel", _r select 6] call _fn) call _tr, _r select 7]; // i18n:brut (numéro, famille et source déjà traduites)
            _b ctrlSetTooltip format ["%1 · %2", _r select 2, _r select 7];
            _b ctrlSetBackgroundColor ([["bg2"] call _col, ["accentDim"] call _col] select ((_r select 0) isEqualTo _key));
            _b ctrlShow true;
            _keys pushBack (_r select 0);
        } forEach _cells;
        _st set ["cellKeys", _keys];
        // Routage : qui montre la même source, et qui recopie cet écran.
        private _same = (_rows select { (_x select 6) isEqualTo _src && {(_x select 0) isNotEqualTo _key} }) apply { _x select 2 };
        private _uid = if (is3DEN) then { "" } else { _o getVariable ["COMSPEC_ATAK_TocUid", ""] };
        private _outs = (_rows select { _uid isNotEqualTo "" && {(_x select 6) isEqualTo ("BC|" + _uid)} }) apply { _x select 2 };
        private _txt = format [("%1 · %2 : aussi sur %3.") call _tr, (["catLabel", _src] call _fn) call _tr, _srcL, [(_same select [0, 8]) joinString ", ", ("aucun autre écran") call _tr] select (_same isEqualTo [])];
        if (_outs isNotEqualTo []) then { _txt = _txt + " " + format [("Recopié par : %1.") call _tr, (_outs select [0, 8]) joinString ", "]; };
        (_st get "route") ctrlSetStructuredText parseText format ["<t size='0.9' color='%1'>%2</t>", ["hex", "muted"] call comspec_atak_native_fnc_tocTheme, _txt];
        true
    };
    case "act": {
        private _list = _st getOrDefault ["list", controlNull];
        if (isNull _list) exitWith { false };
        if (_a isEqualTo "close") exitWith { (_st get "d") closeDisplay 2; true };
        private _rows = _st get "rows";
        private _cur = (_rows select { (_x select 0) isEqualTo (_st get "cur") }) param [0, []];
        private _checked = _st get "checked";
        private _picked = _rows select { (_x select 0) in _checked };
        if (_picked isEqualTo [] && {_cur isNotEqualTo []}) then { _picked = [_cur]; };
        private _data = { params ["_c"]; _c lbData (lbCurSel _c) };
        private _later = { [{ ["refresh"] call comspec_atak_native_fnc_tocManager; }, [], 1.2] call comspec_atak_native_fnc_waitAndExec; };
        // Endroit de pose : 6 m devant la caméra d'Eden ou du Zeus, 3 m devant le joueur.
        private _spot = {
            if (is3DEN) exitWith { private _p = screenToWorld [0.5, 0.5]; [_p select 0, _p select 1, 0] };
            if (!isNull curatorCamera && {!isNull (findDisplay 312)}) exitWith { private _p = screenToWorld [0.5, 0.5]; [_p select 0, _p select 1, 0] };
            player modelToWorld [0, 3, 0]
        };
        private _face = {
            params ["_p"];
            _p getDir (call _eye)
        };
        // Lot visé par « Appliquer » et « Générer tous les flux » selon la portée choisie.
        private _scoped = {
            private _sc = [_st get "scope"] call _data;
            switch (_sc) do {
                case "checked": { _rows select { (_x select 0) in _checked } };
                case "hall": { if (_cur isEqualTo []) then { [] } else { _rows select { (_x select 4) isEqualTo (_cur select 4) } } };
                case "all": { +_rows };
                default { [_cur] select { _x isNotEqualTo [] } };
            }
        };
        switch (_a) do {
            case "apply": {
                private _to = call _scoped;
                if (_to isEqualTo []) exitWith { ["say", "Aucune ligne visée : choisissez une ligne ou cochez-en"] call _fn; };
                private _src = [_st get "src"] call _data;
                private _b = (_st get "bright") lbValue (lbCurSel (_st get "bright"));
                private _nm = trim (ctrlText (_st get "name"));
                private _ttl = trim (ctrlText (_st get "bcTitle"));
                private _ok = 0;
                private _no = 0;
                private _frame = [_st get "view"] call _data;
                private _satDo = [_st get "sat"] call _data;
                private _here = call _spot;
                {
                    private _o = _x select 1;
                    private _one = (_x select 0) isEqualTo (_cur param [0, ""]);
                    if (!is3DEN) then { ["setView", [_o, _frame, _satDo, _here]] call _fn; };
                    if (is3DEN) then {
                        if ((typeOf _o) select [0, 19] isEqualTo "COMSPEC_ATAK_Module") then { _no = _no + 1; continue };
                        if (["setSource", [_o, _src]] call _fn) then { _ok = _ok + 1; } else { _no = _no + 1; };
                        [_o, "bright", _b] call _edSet;
                        if (_one && {_nm isNotEqualTo ""}) then { [_o, "name", _nm] call _edSet; };
                    } else {
                        if (["setSource", [_o, _src]] call _fn) then { _ok = _ok + 1; } else { _no = _no + 1; };
                        if ((_x select 8) isNotEqualTo _b) then { ["view", [_o, "opt", [_b, ((_x select 11) getVariable ["COMSPEC_ATAK_TocOpt", [100, 0]]) param [1, 0]]]] call _ts; };
                        // Nom : seulement la ligne choisie (un lot garde ses noms).
                        if (_one && {_nm isNotEqualTo ""} && {_nm isNotEqualTo (_x select 2)}) then {
                            private _c = _o getVariable ["COMSPEC_ATAK_TocScr", []];
                            ["add", [_o, _nm, _c param [1, ""], _c param [2, 0], _c param [3, "SPLIT"], _c param [6, true], false]] call _ts;
                        };
                        if (_one && {_ttl isNotEqualTo ((["cfg", _o] call comspec_atak_native_fnc_tocBroadcast) select 1)}) then { ["set", [_o, "title", _ttl]] call comspec_atak_native_fnc_tocBroadcast; };
                    };
                } forEach _to;
                ["say", format [(["Appliqué à %1 dalle%2", "Appliqué à %1 dalles%2"] select (_ok > 1)) call _tr, _ok, ["", format [([" · %1 refusée : source impossible sur ce format de dalle", " · %1 refusées : source impossible sur ce format de dalle"] select (_no > 1)) call _tr, _no]] select (_no > 0)]] call _fn;
                call _later;
            };
            case "feeds": {
                private _to = call _scoped;
                if ((count _to) <= 1 && {_cur isNotEqualTo []}) then { _to = _rows select { (_x select 4) isEqualTo (_cur select 4) }; };
                private _plan = ["feeds", _to apply { _x select 1 }] call _fn;
                private _n = 0;
                { if (["setSource", _x] call _fn) then { _n = _n + 1; }; } forEach _plan;
                ["say", format [("Flux standard posés sur %1 dalle(s)") call _tr, _n]] call _fn;
                call _later;
            };
            case "add": {
                private _k = [_st get "kind"] call _data;
                if ((_k select [0, 5]) isEqualTo "ROOM:") exitWith { ["act", "room"] call _fn; };
                private _p = call _spot;
                if (is3DEN) then {
                    private _e = create3DENEntity ["Object", _k, _p];
                    [_e, "mode", "toc"] call _edSet;
                    [_e, "name", ["uniqueName", "TOC"] call _fn] call _edSet;
                } else {
                    ["spawn", [_p, [_p] call _face, _k, ["uniqueName", "TOC"] call _fn, "", "SPLIT", true]] call _ts;
                };
                ["say", "Écran posé devant vous : il apparaît dans le tableau"] call _fn;
                call _later;
            };
            case "dup": {
                if (_cur isEqualTo []) exitWith { ["say", "Choisissez d'abord une ligne"] call _fn; };
                private _h = _cur select 11;
                private _p = _h modelToWorld [2, 0, 0];
                if (is3DEN) then {
                    private _e = create3DENEntity ["Object", typeOf _h, [_p select 0, _p select 1, 0]];
                    _e set3DENAttribute ["rotation", (_h get3DENAttribute "rotation") param [0, [0, 0, 0]]];
                    { [_e, _x select 0, [_h, _x select 0] call _edGet] call _edSet; } forEach _eden;
                    [_e, "name", ["uniqueName", [_h, "name"] call _edGet] call _fn] call _edSet;
                } else {
                    private _c = _h getVariable ["COMSPEC_ATAK_TocScr", []];
                    ["spawn", [[_p select 0, _p select 1, (getPosATL _h) select 2], getDir _h, typeOf _h, ["uniqueName", _c param [0, "TOC"]] call _fn, _c param [1, ""], _c param [3, "SPLIT"], _c param [6, true],
                        _c param [8, 0], _c param [9, ""], ["panelStr", _h] call comspec_atak_native_fnc_tocConfig]] call _ts;
                };
                ["say", "Copie posée 2 m à droite de l'écran"] call _fn;
                call _later;
            };
            case "del": {
                if (_picked isEqualTo []) exitWith { ["say", "Choisissez ou cochez d'abord une ligne"] call _fn; };
                private _hosts = [];
                { _hosts pushBackUnique (_x select 11); } forEach _picked;
                if (is3DEN) then { delete3DENEntities _hosts; } else { { ["remove", _x] call _ts; } forEach _hosts; };
                _st set ["checked", []];
                ["say", format [("%1 écran(s) retiré(s)") call _tr, count _hosts]] call _fn;
                call _later;
            };
            case "link": {
                private _hid = [_st get "hall"] call _data;
                {
                    private _h = _x select 11;
                    if (is3DEN) then { [_h, "hall", (_st get "hall") lbText (lbCurSel (_st get "hall"))] call _edSet; } else {
                        if (_hid isEqualTo "") then { ["do", ["unlink", _h]] call comspec_atak_native_fnc_tocHall; } else { ["do", ["link", [_hid, _h]]] call comspec_atak_native_fnc_tocHall; };
                    };
                } forEach _picked;
                ["say", format [("%1 écran(s) : salle changée") call _tr, count _picked]] call _fn;
                call _later;
            };
            case "room": {
                private _k = [_st get "kind"] call _data;
                private _size = [_k select [5], "M"] select ((_k select [0, 5]) isNotEqualTo "ROOM:");
                private _p = call _spot;
                if (is3DEN) then {
                    private _e = create3DENEntity ["Logic", "COMSPEC_ATAK_Module_TocHall", _p];
                    _e set3DENAttribute ["COMSPEC_ATAK_Module_TocHall_Size", _size];
                    ["say", "Module Salle TOC complète posé : la salle se construit au lancement de la mission"] call _fn;
                } else {
                    ["zeus", [_p, (call _eye) getDir _p, _size, ["uniqueHall", "SALLE TOC"] call _fn, str side group player, ""]] call comspec_atak_native_fnc_tocHallBuild;
                    ["say", "Salle en cours de pose devant vous (quelques secondes)"] call _fn;
                };
                [{ ["refresh"] call comspec_atak_native_fnc_tocManager; }, [], 6] call comspec_atak_native_fnc_waitAndExec;
            };
            case "layout": {
                if (_cur isEqualTo [] || {is3DEN} || {isNil "comspec_atak_native_fnc_tocLayout"}) exitWith { ["say", "Disposition : choisissez en jeu la ligne d'un mur ou d'un écran incurvé"] call _fn; };
                private _h = _cur select 11;
                private _ls = ["list", _h] call comspec_atak_native_fnc_tocLayout;
                if (_ls isEqualTo []) exitWith { ["say", "Cet écran n'a qu'une dalle : choisissez sa source à droite"] call _fn; };
                // Dispositions passées une à une : chaque appui applique la suivante et la nomme dans la ligne d'état.
                private _now = ["current", _h] call comspec_atak_native_fnc_tocLayout;
                private _i = ((_ls findIf { (_x select 1) isEqualTo _now }) + 1) mod (count _ls);
                ["apply", [_h, (_ls select _i) select 1]] call comspec_atak_native_fnc_tocLayout;
                ["say", format [("Disposition %1 / %2 : %3") call _tr, _i + 1, count _ls, ((_ls select _i) select 0) call _tr]] call _fn;
                call _later;
            };
            case "go": {
                if (_cur isEqualTo []) exitWith {};
                private _h = _cur select 11;
                private _p = _h modelToWorld [0, 5, 2];
                if (is3DEN) exitWith { move3DENCamera [getPosATL _h, true]; };
                if (!isNull curatorCamera && {!isNull (findDisplay 312)}) then {
                    curatorCamera setPosATL _p;
                    curatorCamera setVectorDirAndUp [(getPosATL _h) vectorDiff _p, [0, 0, 1]];
                    ["say", "Caméra du Zeus amenée devant l'écran"] call _fn;
                } else {
                    ["say", format [("Écran à %1 m, cap %2°") call _tr, round (player distance _h), round (player getDir _h)]] call _fn;
                };
            };
            case "blink": {
                if (_cur isEqualTo [] || {is3DEN}) exitWith {};
                ["do", ["blink", _cur select 1]] call comspec_atak_native_fnc_tocHall;
                ["say", "L'écran clignote 2 s chez les joueurs proches"] call _fn;
            };
            case "text": {
                if (_cur isEqualTo [] || {is3DEN}) exitWith { ["say", "Texte : en jeu, sur la ligne d'un écran ; dans Eden, attribut Texte de l'écran ou module Afficher un texte"] call _fn; };
                private _o = _cur select 1;
                (_st get "d") closeDisplay 2;
                if (!isNil "comspec_atak_native_fnc_tocTextTool") then { ["dialog", [_o]] call comspec_atak_native_fnc_tocTextTool; } else { [{ ["edit", _this] call comspec_atak_native_fnc_tocText; }, _o] call comspec_atak_native_fnc_nextFrame; };
            };
            case "bcOn": {
                if (_cur isEqualTo [] || {is3DEN}) exitWith {};
                private _h = _cur select 11;
                private _hid = _h getVariable ["COMSPEC_ATAK_TocHall", ""];
                if (_hid isEqualTo "") exitWith { ["say", "Cet écran n'est lié à aucune salle : liez-le d'abord"] call _fn; };
                private _n = ["start", [_cur select 1, "hall", _hid, [trim (ctrlText (_st get "bcTitle")), true, true]]] call comspec_atak_native_fnc_tocBroadcast;
                ["say", format [("Diffusion lancée sur %1 dalle(s) de la salle") call _tr, _n]] call _fn;
                call _later;
            };
            case "bcOff": {
                if (_cur isEqualTo [] || {is3DEN}) exitWith {};
                private _n = ["stop", ["hall", (_cur select 11) getVariable ["COMSPEC_ATAK_TocHall", ""]]] call comspec_atak_native_fnc_tocBroadcast;
                ["say", format [("Diffusion arrêtée : %1 dalle(s) ont repris leur affichage") call _tr, _n]] call _fn;
                call _later;
            };
            case "save": {
                private _nm = (toUpper (trim (ctrlText (_st get "preset")))) select [0, 24];
                if (_nm isEqualTo "") exitWith { ["say", "Écrivez d'abord le nom du préréglage à droite"] call _fn; };
                // [nom de l'écran, dalle, clé de source, luminosité] : retrouvés par nom et dalle (les identifiants changent d'une partie à l'autre).
                private _items = (_rows select { (_x select 6) isNotEqualTo "" }) apply { [_x select 2, _x select 12, _x select 6, _x select 8] };
                private _var = format ["COMSPEC_ATAK_TocMgrPresets_%1", worldName];
                private _all = (profileNamespace getVariable [_var, []]) select { _x isEqualType [] && {(_x param [0, ""]) isNotEqualTo _nm} };
                _all pushBack [_nm, _items];
                profileNamespace setVariable [_var, _all select [0 max ((count _all) - 20)]];
                saveProfileNamespace;
                ["say", format [("Préréglage %1 enregistré (%2 dalles)") call _tr, _nm, count _items]] call _fn;
            };
            case "load": {
                private _nm = (toUpper (trim (ctrlText (_st get "preset")))) select [0, 24];
                private _all = (profileNamespace getVariable [format ["COMSPEC_ATAK_TocMgrPresets_%1", worldName], []]) select { _x isEqualType [] };
                private _p = (_all select { (_x param [0, ""]) isEqualTo _nm }) param [0, []];
                if (_p isEqualTo []) exitWith { ["say", format [("Préréglage introuvable. Enregistrés : %1") call _tr, [(_all apply { _x select 0 }) joinString ", ", "aucun"] select (_all isEqualTo [])]] call _fn; };
                private _n = 0;
                {
                    _x params [["_pn", ""], ["_pk", 0], ["_ps", ""], ["_pb", 100]];
                    private _r = (_rows select { (_x select 2) isEqualTo _pn && {(_x select 12) isEqualTo _pk} }) param [0, []];
                    if (_r isNotEqualTo [] && {["setSource", [_r select 1, _ps]] call _fn}) then {
                        _n = _n + 1;
                        if (!is3DEN && {(_r select 8) isNotEqualTo _pb}) then { ["view", [_r select 1, "opt", [_pb, 0]]] call _ts; };
                    };
                } forEach (_p param [1, []]);
                ["say", format [("Préréglage %1 chargé sur %2 dalle(s)") call _tr, _nm, _n]] call _fn;
                call _later;
            };
        };
        true
    };
    default { false };
};
