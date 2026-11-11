/*
    Menu des écrans TOC (demande de TTRD, 4.8.0 : « sois plus clair dans les actions ACE »). Un seul arbre, décrit ici,
    sert au menu ACE de l'écran (fn_tocScreen « aceInit »), aux actions de la molette sans ACE (« actions ») et au
    tableau des écrans (aide de chaque entrée). Libellés courts, verbe d'abord, état courant écrit dans le libellé
    (« Luminosité : 75 % », « Salle : TOC 1 », « Carte et flux ✓ »), 7 entrées au plus par niveau (au-delà :
    « Suite… »), et rien de ce qui ne s'applique pas à l'écran (un bandeau 4:1 ne propose que bandeau, plaque et texte).

    Arbre d'un écran désigné (les groupes vides ne sont pas montrés) :
      Ouvrir le PC du poste
      Afficher…           Carte et réseau… · Tableaux du poste… · Texte à LED… · Diapositives… · Cadrer la carte…
                          · Sur une dalle du mur… (mur à plusieurs dalles)
      Disposition…        dispositions d'un écran à plusieurs dalles (fn_tocLayout)
      Source / caméra…    Satellite SAT 1 · Satellite SAT 2 · Escouade… · Caméra en direct… · Mosaïque de caméras…
      Écran…              Luminosité : n % · Modifier le texte LED… · Diffusion (BROADCAST)… · Diagnostic… · Retirer l'écran TOC
      Salle : nom         Lier à la salle la plus proche · Lier à une autre salle… · Délier · Identifier · Éteindre / Allumer
      Gérer les écrans (tableau)…
    Écran pas encore désigné : Ouvrir le PC du poste · Utiliser comme écran TOC · Diapositives… · Écran… · Salle · tableau.

    Nœud : [identifiant, libellé (français, traduit à l'affichage), aide en une ligne avec un exemple, icône,
            clé d'action ("" : groupe), enfants, coché (état courant)]
    Les clés sont celles de COMSPEC_ATAK_TocDo (fn_tocScreen « aceInit ») ; les nouvelles sont exécutées ici (« do ») :
      bright|n · hall|id · unlink · blink · on · off · test · who · info · lay|clé · bc|clé · manager · view|AFFICHAGE
      · plate|groupe · cam|netId · pn|dalle|contenu · pntxt|dalle

    Modes :
      ["tree", écran]            -> arbre de l'écran ([] sans écran)
      ["ace", écran]             -> enfants ACE de la racine « Écran COMSPEC » (gardés 0,5 s par écran)
      ["wheel", [écran, chemin]] pose les actions de la molette du niveau demandé ([] : racine), sans ACE
      ["wheelDo", [écran, [genre "go" | "key", valeur]]]   action de la molette choisie
      ["wheelOk", écran]         condition des actions de la molette (camp, écran désigné) ; remet la racine si l'état a changé
      ["do", [écran, clé]]       exécute une clé propre à ce menu (appelé par COMSPEC_ATAK_TocDo)
    Exemple : ["tree", cursorObject] call comspec_atak_native_fnc_tocMenu;
*/
params [["_mode", "tree"], ["_a", objNull]];
private _fn = comspec_atak_native_fnc_tocMenu;
// État qui change les entrées de la molette : affichage, diapositives en cours, veille, salle.
private _sigOf = {
    params ["_o"];
    [(_o getVariable ["COMSPEC_ATAK_TocScr", []]) param [3, ""], (count (_o getVariable ["COMSPEC_ATAK_ScreenCast", []])) >= 5,
        _o getVariable ["COMSPEC_ATAK_TocOff", false], _o getVariable ["COMSPEC_ATAK_TocHall", ""]]
};
// Condition de la molette, lue à chaque image devant l'écran : rien d'autre n'est préparé avant elle.
if (_mode isEqualTo "wheelOk") exitWith {
    private _o = _a;
    if (isNull _o || {!alive _o}) exitWith { false };
    private _scr = _o getVariable ["COMSPEC_ATAK_TocScr", []];
    if ((count _scr) < 8) exitWith { false };
    private _camp = _scr select 1;
    if (_camp isNotEqualTo "" && {_camp isNotEqualTo (str side group player)}) exitWith { false };
    (_o getVariable ["COMSPEC_ATAK_TocWheelAt", [[], 0, []]]) params ["_was", "_since", "_path"];
    // État changé (par un autre joueur, le PC, le serveur) ou sous-menu laissé ouvert 20 s : retour à la racine, à jour.
    if ((([_o] call _sigOf) isNotEqualTo _was || {_path isNotEqualTo [] && {(diag_tickTime - _since) > 20}}) && {!(_o getVariable ["COMSPEC_ATAK_TocWheelBusy", false])}) then {
        _o setVariable ["COMSPEC_ATAK_TocWheelBusy", true];
        [{ ["wheel", [_this, []]] call comspec_atak_native_fnc_tocMenu; }, _o] call comspec_atak_native_fnc_nextFrame;
    };
    true
};
private _ts = comspec_atak_native_fnc_tocScreen;
private _d = "\z\comspec_atak_native\addons\main\data\";
private _hostOf = { params ["_o"]; private _h = _o getVariable ["COMSPEC_ATAK_TocHost", objNull]; [_o, _h] select (!isNull _h) };
// 7 entrées au plus par niveau : les 6 premières, puis « Suite… ».
private _fit = {
    params ["_list", "_id"];
    if ((count _list) <= 7) exitWith { _list };
    (_list select [0, 6]) + [[_id + "More", "Suite…", "Suite de la liste. Exemple : les escouades ou les dalles suivantes.", "", "", [_list select [6], _id + "M"] call _fit, false]]
};
private _leaf = {
    params ["_id", "_txt", "_help", "_key", ["_icon", ""], ["_on", false]];
    [_id, _txt, _help, [_icon, _d + "ui_check.paa"] select _on, _key, [], _on]
};
private _grp = {
    params ["_id", "_txt", "_help", "_icon", "_kids", ["_on", false]];
    [_id, _txt, _help, _icon, "", [_kids, _id] call _fit, _on]
};

switch (_mode) do {
    case "tree": {
        private _o = _a;
        if (!(_o isEqualType objNull) || {isNull _o}) exitWith { [] };
        private _scr = _o getVariable ["COMSPEC_ATAK_TocScr", []];
        if !(_scr isEqualType []) then { _scr = []; };
        private _has = (count _scr) >= 8;
        private _host = [_o] call _hostOf;
        private _view = _scr param [3, ""];
        private _cast = _o getVariable ["COMSPEC_ATAK_ScreenCast", []];
        private _casting = (count _cast) >= 5;
        private _sideS = _scr param [1, ""];
        if (_sideS isEqualTo "") then { _sideS = str side group player; };
        private _sqList = ["squads", _sideS] call _ts;
        // Format de la dalle (jamais d'erreur de type ici : fn_tocScreen « _entryOf », correctif 4.7.1) et affichages
        // permis par ce format : un bandeau 4:1 ne reçoit ni carte ni caméra.
        private _kind = (["format", _o] call comspec_atak_native_fnc_tocScreen) select 3;
        private _okViews = (["viewList", _o] call comspec_atak_native_fnc_tocConfig) apply { _x select 1 };
        private _std = _kind isEqualTo "STD";
        private _viewTxt = { params ["_v"]; (["title", _v] call _ts) param [1, toLower _v] };
        private _out = [];

        // ---- Ouvrir le PC du poste : en tête, sur tout écran qui sert de poste, désigné ou non (écran COMSPEC).
        if (_std && {[_has && {_o getVariable ["COMSPEC_ATAK_Toc", false]}, (getNumber (configOf _o >> "comspec_screen")) > 0] select !_has} && {isNull objectParent player}) then {
            _out pushBack (["Pc", "Ouvrir le PC du poste", "Ouvre le PC du poste TOC : carte, groupes, flux, ordres. Exemple : donner un ordre à Alpha depuis la salle.", "big", _d + "app_toc.paa"] call _leaf);
        };

        // ---- Diapositives : en cours (suivante, précédente, arrêter) ou à lancer depuis son téléphone.
        private _slides = [];
        if (_casting) then {
            private _acad = (_cast select 2) isEqualTo "ACADEMY";
            _slides pushBack (["Next", ["Diapositive suivante", "Page suivante"] select _acad, "Passe à la diapositive suivante. Exemple : avancer le briefing d'une page.", "slidesNext"] call _leaf);
            _slides pushBack (["Prev", ["Diapositive précédente", "Page précédente"] select _acad, "Revient à la diapositive précédente. Exemple : remontrer la carte de l'objectif.", "slidesPrev"] call _leaf);
            _slides pushBack (["Stop", "Arrêter la diffusion", "Arrête les diapositives : l'écran reprend son affichage. Exemple : fin du briefing.", "slidesStop"] call _leaf);
        } else {
            if ([player] call comspec_atak_native_fnc_hasDevice) then {
                _slides pushBack (["Start", "Diffuser mes diapositives", "Montre sur l'écran les diapositives de votre téléphone. Exemple : le briefing de la mission.", "slides"] call _leaf);
            };
        };
        private _slideGrp = ["Slides", "Diapositives…", "Diapositives du briefing ou leçon Academy sur cet écran. Exemple : briefing avant le départ.", _d + "app_briefing.paa", _slides] call _grp;

        if (!_has) then {
            // Écran du jeu ou d'un autre mod (CfgComspecTocScreens) : il devient un écran TOC, image sur sa surface connue.
            _out pushBack (["Use", "Utiliser comme écran TOC", "Fait de cet objet un écran TOC (carte et flux). Exemple : la TV de la salle de briefing.", "split", _d + "app_toc.paa"] call _leaf);
            if (_slides isNotEqualTo []) then { _out pushBack _slideGrp; };
        } else {
            // ---- Afficher… : les vues permises par le format de la dalle, rangées par famille ; la vue en cours est cochée.
            private _viewDef = createHashMapFromArray [
                ["SPLIT", ["split", "Carte et flux", "Carte du réseau et flux des comptes rendus. Exemple : l'écran principal du TOC."]],
                ["MAP", ["map", "Carte seule", "Carte du réseau en plein écran. Exemple : suivre une progression."]],
                ["FEED", ["feed", "Flux seul", "Comptes rendus, alertes et messages en plein écran. Exemple : l'écran du secrétaire."]],
                ["NET", ["net", "Réseau", "État du réseau ATAK : nœuds, liaisons, groupes. Exemple : voir qui est hors ligne."]],
                ["ORBAT", ["orbat", "ORBAT du site", "Ordre de bataille publié sur le site. Exemple : qui commande quelle section."]],
                ["TOC", ["pip", "PIP du poste", "Recopie ce que l'opérateur voit sur le PC du poste. Exemple : montrer sa carte à toute la salle."]],
                ["CLOCK", ["clock", "Horloge et chronos", "Heures ZULU et locale, chronos de la mission. Exemple : le compte à rebours avant l'heure H."]],
                ["LOG", ["log", "Main courante", "Journal des événements du poste. Exemple : relire ce qui s'est passé depuis 10 min."]],
                ["EXCHECK", ["excheck", "Liste d'exécution", "Étapes de l'opération et leur état. Exemple : « JAGUAR » passé, « LION » attendu."]],
                ["KILLS", ["kills", "Pertes et résultats", "Pertes amies et résultats sur l'ennemi. Exemple : le bilan en fin d'action."]],
                ["REQ", ["req", "Demandes des éléments", "Demandes en attente : MEDEVAC, appui, ravitaillement. Exemple : voir une demande d'évacuation."]],
                ["STRIP", ["strip", "Bandeau horloge ZULU / LOCAL", "Bandeau avec les deux heures. Exemple : au-dessus du mur d'écrans."]],
                ["BROADCAST", ["view|BROADCAST", "Diffusion et mire (BROADCAST)", "Montre un autre écran ou, sans source, la mire. Exemple : le même écran recopié dans toute la salle."]],
                ["TEXT", ["text", "Texte à LED…", "Ouvre la saisie du texte de l'écran. Exemple : « BRIEFING 21:00 ZULU » sur le bandeau."]],
                ["COLA", ["cola", "Colonne horloge et chronos", "Colonne étroite : heures et chronos. Exemple : la colonne gauche d'un écran incurvé."]],
                ["COLB", ["colb", "Colonne réseau et flux", "Colonne étroite : réseau et derniers comptes rendus. Exemple : la colonne droite d'un écran incurvé."]]
            ];
            private _mapK = [];
            private _brdK = [];
            private _other = [];
            {
                private _v = _x;
                // Satellite, escouade, caméra, mosaïque : groupe « Source / caméra… ». Plaque : par groupe, plus bas.
                if (_v in ["SAT", "SQUAD", "CAM", "MOSAIC", "PLATE"]) then { continue };
                private _def = _viewDef getOrDefault [_v, ["view|" + _v, format ["Vue : %1", [_v] call _viewTxt], "Affiche cette vue sur l'écran. Exemple : une colonne d'un écran incurvé."]];
                private _n = ["V" + _v, _def select 1, _def select 2, _def select 0, "", _view isEqualTo _v] call _leaf;
                switch (true) do {
                    case (_v in ["SPLIT", "MAP", "FEED", "NET", "ORBAT", "TOC"]): { _mapK pushBack _n; };
                    case (_v in ["CLOCK", "LOG", "EXCHECK", "KILLS", "REQ"]): { _brdK pushBack _n; };
                    default { _other pushBack _n; };
                };
            } forEach _okViews;
            private _show = [];
            if (_mapK isNotEqualTo []) then {
                private _onMap = (_mapK findIf { _x select 6 }) >= 0;
                _mapK pushBack (["Cycle", "Vue suivante", "Passe à la vue suivante de cette famille. Exemple : de carte et flux à carte seule.", "cycle"] call _leaf);
                _show pushBack (["Map", "Carte et réseau…", "Vues de la carte et du réseau du camp. Exemple : carte et flux pour l'écran principal.", _d + "app_map.paa", _mapK, _onMap] call _grp);
            };
            if (_brdK isNotEqualTo []) then {
                _show pushBack (["Board", "Tableaux du poste…", "Tableaux tenus par le poste. Exemple : la main courante sur l'écran de droite.", _d + "app_toc.paa", _brdK, (_brdK findIf { _x select 6 }) >= 0] call _grp);
            };
            _show append _other;
            if ("PLATE" in _okViews && {_sqList isNotEqualTo []}) then {
                private _pl = [];
                {
                    _pl pushBack ([format ["Pl%1", _forEachIndex], _x, "Plaque au nom de ce groupe. Exemple : « ALPHA 1-1 » au-dessus de son écran.", "plate|" + _x, "", _view isEqualTo "PLATE" && {(_scr param [9, ""]) isEqualTo _x}] call _leaf);
                } forEach (_sqList select [0, 12]);
                _show pushBack (["Plate", "Plaque au nom d'un groupe…", "Plaque de nom d'un groupe du camp. Exemple : nommer l'écran d'une escouade.", _d + "app_group.paa", _pl, _view isEqualTo "PLATE"] call _grp);
            };
            if (_slides isNotEqualTo [] && {_std || {_casting}}) then { _show pushBack _slideGrp; };
            if (_std) then {
                private _frameK = [
                    ["In", "Zoomer", "Carte deux fois plus près. Exemple : voir une rue.", "in"] call _leaf,
                    ["Out", "Dézoomer", "Carte deux fois plus large. Exemple : voir toute la vallée.", "out"] call _leaf,
                    ["Me", "Centrer sur ma position", "Centre la carte sur vous. Exemple : montrer où se trouve le TOC.", "me"] call _leaf,
                    ["Auto", "Cadrer tout le réseau", "Cadrage automatique sur tous les nœuds. Exemple : revenir à la vue d'ensemble.", "auto"] call _leaf
                ];
                _show pushBack (["Frame", "Cadrer la carte…", "Zoom et centre de la carte de l'écran. Exemple : zoomer sur l'objectif.", _d + "app_map.paa", _frameK] call _grp);
            };
            // Mur à plusieurs dalles : un contenu par dalle (dalle 1 en haut à gauche, ordre de lecture).
            private _np = getNumber (configOf _o >> "comspec_screenPanels");
            if (_np > 1) then {
                private _opts = [["Carte et flux", "SPLIT"], ["SAT 1", "SAT1"], ["SAT 2", "SAT2"], ["Horloge et chronos", "CLOCK"], ["Mosaïque 2x2", "MOS|2|AUTO"],
                    ["Main courante", "LOG"], ["Liste d'exécution", "EXCHECK"], ["Pertes et résultats", "KILLS"], ["Demandes des éléments", "REQ"]];
                { _opts pushBack [format ["Escouade %1", _x], "SQ|" + _x]; } forEach (_sqList select [0, 6]);
                private _pans = [];
                for "_i" from 0 to (_np - 1) do {
                    // Dalle en colonne (écran incurvé) : colonne horloge ou flux ; toute dalle : texte à LED.
                    private _po = ["panelOf", [_o, _i]] call _ts;
                    private _pk = (["format", _po] call _ts) select 3;
                    private _items = [];
                    {
                        _items pushBack ([format ["Pn%1x%2", _i, _forEachIndex], _x select 0, "Met ce contenu sur cette dalle seulement. Exemple : SAT 1 sur la dalle 2.", format ["pn|%1|%2", _i, _x select 1]] call _leaf);
                    } forEach ([_opts, [["Colonne horloge et chronos", "COLA"], ["Colonne réseau et flux", "COLB"]]] select (_pk isEqualTo "COL"));
                    _items pushBack ([format ["PnTxt%1", _i], "Texte à LED…", "Ouvre la saisie du texte de cette dalle. Exemple : « SALLE OPS » sur la dalle 3.", format ["pntxt|%1", _i]] call _leaf);
                    _pans pushBack ([format ["Pn%1", _i], format ["Dalle %1", _i + 1], "Contenu de cette dalle du mur. Exemple : dalle 1 en haut à gauche, puis dans l'ordre de lecture.", "", _items] call _grp);
                };
                _show pushBack (["Pans", "Sur une dalle du mur…", "Choisit le contenu d'une seule dalle du mur. Exemple : l'horloge sur la dalle 4.", _d + "map_grid.paa", _pans] call _grp);
            };
            if (_show isNotEqualTo []) then {
                _out pushBack (["Show", "Afficher…", "Choisit ce que montre l'écran ; la vue en cours est cochée. Exemple : Carte et réseau, puis Carte seule.", _d + "ui_eye.paa", _show] call _grp);
            };

            // ---- Disposition… : dispositions d'un écran à plusieurs dalles (fn_tocLayout) ; rien sur un écran à une dalle.
            private _lay = [];
            if (!isNil "comspec_atak_native_fnc_tocLayout") then { _lay = ["list", _o] call comspec_atak_native_fnc_tocLayout; };
            if (isNil "_lay" || {!(_lay isEqualType [])}) then { _lay = []; };
            // Disposition en cours (fn_tocLayout « current »), cochée.
            private _layNow = "";
            if (_lay isNotEqualTo []) then { _layNow = ["current", _o] call comspec_atak_native_fnc_tocLayout; };
            if (isNil "_layNow" || {!(_layNow isEqualType "")}) then { _layNow = ""; };
            private _layK = [];
            {
                if (_x isEqualType [] && {(count _x) >= 2}) then {
                    _layK pushBack ([format ["Lay%1", _forEachIndex], _x select 0, "Applique cette disposition à toutes les dalles. Exemple : carte au centre, tableaux sur les côtés.", "lay|" + (_x select 1), "", _layNow isNotEqualTo "" && {_layNow isEqualTo (_x select 1)}] call _leaf);
                };
            } forEach _lay;
            if (_layK isNotEqualTo []) then {
                _out pushBack (["Layout", "Disposition…", "Répartit les contenus sur les dalles du mur en une fois. Exemple : une disposition de briefing.", _d + "map_grid.paa", _layK] call _grp);
            };

            // ---- Source / caméra… : satellite, escouade, caméra en direct, mosaïque.
            private _src = [];
            if ("SAT" in _okViews) then {
                {
                    _src pushBack ([format ["Sat%1", _x + 1], format ["Satellite SAT %1", _x + 1], "Vue satellite et suivi des alliés. Exemple : SAT 1 sur l'objectif, SAT 2 sur l'itinéraire.", format ["sat%1", _x + 1], "", _view isEqualTo "SAT" && {(_scr param [8, 0]) isEqualTo _x}] call _leaf);
                } forEach [0, 1];
            };
            if ("SQUAD" in _okViews && {_sqList isNotEqualTo []}) then {
                private _sqs = [];
                {
                    _sqs pushBack ([format ["Sq%1", _forEachIndex], _x, "Écran de ce groupe : membres, état ATAK, carte, dernier compte rendu. Exemple : suivre Alpha 1-1.", "sq|" + _x, "", _view isEqualTo "SQUAD" && {(_scr param [9, ""]) isEqualTo _x}] call _leaf);
                } forEach (_sqList select [0, 12]);
                _src pushBack (["Squad", "Escouade…", "Écran consacré à un groupe du camp. Exemple : un écran par escouade engagée.", _d + "app_group.paa", _sqs, _view isEqualTo "SQUAD"] call _grp);
            };
            if ("CAM" in _okViews) then {
                private _camNow = _o getVariable ["COMSPEC_ATAK_TocCam", ""];
                private _cams = [["CamNext", "Caméra suivante", "Passe à la caméra suivante du camp. Exemple : faire défiler drones et caméras fixes.", "camNext"] call _leaf];
                {
                    _x params [["_lbl", ""], ["_cid", ""]];
                    _cams pushBack ([format ["Cam%1", _forEachIndex], _lbl, "Image en direct de cette source. Exemple : le drone au-dessus de l'objectif.", "cam|" + _cid, "", _view isEqualTo "CAM" && {_camNow isEqualTo _cid}] call _leaf);
                } forEach ((["sources"] call comspec_atak_native_fnc_tocCam) select [0, 8]);
                _src pushBack (["Cam", "Caméra en direct…", "Drone, caméra fixe ou caméra partagée du camp. Exemple : la caméra de l'entrée nord.", _d + "app_livecam.paa", _cams, _view isEqualTo "CAM"] call _grp);
            };
            if ("MOSAIC" in _okViews) then {
                private _mos = [
                    ["Mos2", "Mosaïque 2x2 · tout le camp", "Quatre caméras du camp à la fois. Exemple : surveiller quatre accès.", "MOS|2|AUTO"] call _leaf,
                    ["Mos3", "Mosaïque 3x3 · tout le camp", "Neuf caméras du camp à la fois. Exemple : toutes les caméras de la base.", "MOS|3|AUTO"] call _leaf
                ];
                {
                    _mos pushBack ([format ["MosG%1", _forEachIndex], format ["Mosaïque 2x2 · groupe %1", _x], "Quatre caméras de ce groupe. Exemple : les caméras de casque d'Alpha 1-1.", "MOS|2|G|" + _x] call _leaf);
                } forEach (_sqList select [0, 4]);
                _src pushBack (["Mos", "Mosaïque de caméras…", "Plusieurs caméras sur le même écran. Exemple : 2x2 de tout le camp.", _d + "app_livecam.paa", _mos, _view isEqualTo "MOSAIC"] call _grp);
            };
            if (_src isNotEqualTo []) then {
                _out pushBack (["Source", "Source / caméra…", "Choisit la source montrée : satellite, escouade, caméra, mosaïque. Exemple : la caméra d'un drone.", _d + "app_livecam.paa", _src] call _grp);
            };
        };

        // ---- Écran… : luminosité, texte LED, diffusion, diagnostic, retrait.
        private _hall = ["of", _o] call comspec_atak_native_fnc_tocHall;
        private _hallName = if (_hall isEqualTo "") then { "aucune" } else { (["get", _hall] call comspec_atak_native_fnc_tocHall) param [1, "?"] };
        private _scrK = [];
        if (_has) then {
            private _b = (_host getVariable ["COMSPEC_ATAK_TocOpt", []]) param [0, 100];
            if !(_b isEqualType 0) then { _b = 100; };
            _b = round _b;
            private _bk = [100, 75, 50, 25] apply {
                [format ["B%1", _x], format ["%1 %2", _x, "%"], "Règle la luminosité de l'écran pour tous. Exemple : 50 % dans une salle sombre.", format ["bright|%1", _x], "", _b isEqualTo _x] call _leaf
            };
            _scrK pushBack (["Bright", format ["Luminosité : %1 %2", _b, "%"], "Luminosité de l'écran, valeur en cours dans le libellé. Exemple : 25 % pour une opération de nuit.", "", _bk] call _grp);
            if (_view isEqualTo "TEXT") then {
                _scrK pushBack (["Text", "Modifier le texte LED…", "Ouvre la saisie du texte affiché. Exemple : changer « ALERTE » en « FIN D'ALERTE ».", "text"] call _leaf);
            };
            // Diffusion (BROADCAST) : entrées données par fn_tocBroadcast (mire, salle, témoin), s'il est là.
            private _bc = [];
            if (!isNil "comspec_atak_native_fnc_tocBroadcast") then { _bc = ["menu", _o] call comspec_atak_native_fnc_tocBroadcast; };
            if (isNil "_bc" || {!(_bc isEqualType [])}) then { _bc = []; };
            private _bcK = [];
            {
                if (_x isEqualType [] && {(count _x) >= 2}) then {
                    _bcK pushBack ([format ["Bc%1", _forEachIndex], _x select 0, "Réglage de la diffusion de cet écran. Exemple : montrer cet écran dans toute la salle.", "bc|" + (_x select 1)] call _leaf);
                };
            } forEach _bc;
            if (_bcK isNotEqualTo []) then {
                _scrK pushBack (["Bc", "Diffusion (BROADCAST)…", "Recopie un écran sur d'autres, ou affiche la mire. Exemple : le même écran dans toute la salle.", _d + "app_briefing.paa", _bcK, _view isEqualTo "BROADCAST"] call _grp);
            };
        };
        private _fb = ((["mode"] call comspec_atak_native_fnc_tocConfig) param [0, "server"]) isEqualTo "fallback" || {_host getVariable ["COMSPEC_ATAK_TocFb", false]};
        private _diag = [
            ["Test", "Image de test (8 s)", "Pose une image de test 8 s, sur votre poste seulement. Exemple : vérifier le sens et la surface de l'image.", "test"] call _leaf,
            ["Who", format ["Tenu par : %1", ["le serveur", "les joueurs (repli)"] select _fb], "Dit qui tient l'écran ; le détail part sur le téléphone. Exemple : en repli, rien n'est gardé par le serveur.", "who"] call _leaf
        ];
        if (_has) then {
            _diag pushBack (["Name", format ["Écran : %1", _scr param [0, ""]], "Nom de l'écran ; le résumé part sur le téléphone. Exemple : « TOC Bravo ».", "info"] call _leaf);
            _diag pushBack (["View", format ["Vue : %1", [_view] call _viewTxt], "Vue en cours ; le résumé part sur le téléphone. Exemple : « carte et flux ».", "info"] call _leaf);
            _diag pushBack (["Hall", format ["Salle : %1", _hallName], "Salle de l'écran ; le résumé part sur le téléphone. Exemple : « TOC 1 ».", "info"] call _leaf);
        };
        _scrK pushBack (["Diag", "Diagnostic…", "Contrôles de l'écran : image de test, qui le tient, nom, vue, salle. Exemple : un écran resté noir.", _d + "app_status.paa", _diag] call _grp);
        if (_has) then {
            _scrK pushBack (["Remove", "Retirer l'écran TOC", "L'objet n'est plus un écran TOC et reprend son image d'origine. Exemple : libérer la TV pour un briefing.", "remove", _d + "ui_trash.paa"] call _leaf);
        };
        _out pushBack (["Screen", "Écran…", "Réglages de cet écran : luminosité, texte, diffusion, diagnostic, retrait. Exemple : baisser la luminosité.", _d + "ui_gear.paa", _scrK] call _grp);

        // ---- Salle : lier à la salle la plus proche ou à une autre, délier, identifier, éteindre / allumer.
        private _room = [];
        private _halls = (["halls", _sideS] call comspec_atak_native_fnc_tocHall) select { (_x select 0) isNotEqualTo _hall };
        private _known = _halls apply { _x select 0 };
        private _nearId = "";
        if (_known isNotEqualTo []) then {
            // Salle de l'écran ou du PC lié le plus proche, à 30 m au plus (même règle que fn_tocHall « auto »).
            private _best = 1e9;
            {
                private _dist = _x distance _host;
                if (!isNull _x && {_x isNotEqualTo _host} && {_dist < 30} && {_dist < _best} && {(_x getVariable ["COMSPEC_ATAK_TocHall", ""]) in _known}) then {
                    _best = _dist;
                    _nearId = _x getVariable ["COMSPEC_ATAK_TocHall", ""];
                };
            } forEach ((missionNamespace getVariable ["COMSPEC_ATAK_TocScreens", []]) + (nearestObjects [_host, ["COMSPEC_ATAK_TocLaptop"], 30]));
        };
        if (_nearId isNotEqualTo "") then {
            _room pushBack (["Near", format ["Lier à la salle la plus proche : %1", (_halls select (_known find _nearId)) select 1], "Lie l'écran à la salle de l'écran ou du PC le plus proche (30 m). Exemple : un écran ajouté dans la salle TOC 1.", "hall|" + _nearId] call _leaf);
        };
        private _others = [];
        {
            _x params ["_hid", "_hn"];
            if (_hid isNotEqualTo _nearId) then {
                _others pushBack ([format ["H%1", _forEachIndex], _hn, "Lie l'écran à cette salle. Exemple : passer l'écran de TOC 1 à TOC 2.", "hall|" + _hid] call _leaf);
            };
        } forEach _halls;
        if (_others isNotEqualTo []) then {
            _room pushBack (["Other", ["Lier à une salle…", "Lier à une autre salle…"] select (_nearId isNotEqualTo "" || {_hall isNotEqualTo ""}), "Liste des salles du camp. Exemple : choisir la salle du PC maître.", "", _others] call _grp);
        };
        if (_hall isNotEqualTo "") then {
            _room pushBack (["Unlink", format ["Délier de la salle %1", _hallName], "L'écran n'appartient plus à cette salle. Exemple : un écran déplacé dans une autre pièce.", "unlink"] call _leaf);
        };
        if (_has) then {
            _room pushBack (["Blink", "Identifier (clignote 2 s)", "L'écran clignote 2 s chez les joueurs proches. Exemple : retrouver la dalle 3 du mur.", "blink"] call _leaf);
            if (_o getVariable ["COMSPEC_ATAK_TocOff", false]) then {
                _room pushBack (["On", "Allumer l'écran", "Sort l'écran de veille. Exemple : avant le briefing.", "on"] call _leaf);
            } else {
                _room pushBack (["Off", "Éteindre l'écran", "Met l'écran en veille, son réglage est gardé. Exemple : salle vide.", "off"] call _leaf);
            };
        };
        if (_room isNotEqualTo []) then {
            _out pushBack (["Room", format ["Salle : %1", _hallName], "Salle TOC de l'écran, nom en cours dans le libellé. Exemple : lier l'écran à la salle la plus proche.", _d + "app_c2.paa", _room] call _grp);
        };

        // ---- Tableau de tous les écrans (fn_tocManager), s'il est là.
        if (!isNil "comspec_atak_native_fnc_tocManager") then {
            _out pushBack (["Manager", "Gérer les écrans (tableau)…", "Ouvre le tableau de tous les écrans TOC. Exemple : changer la vue de dix écrans d'un coup.", "manager", _d + "app_settings.paa"] call _leaf);
        };
        [_out, "Root"] call _fit
    };
    case "ace": {
        // Enfants ACE de la racine : le même arbre, converti. ACE redemande les enfants à chaque image tant que le menu
        // est ouvert : l'arbre est gardé 0,5 s par écran. Nom d'action propre à l'écran et au chemin : fn_aceAction garde
        // le texte source par nom, deux écrans aux listes différentes ne se mélangent pas.
        private _o = _a;
        if (!(_o isEqualType objNull) || {isNull _o}) exitWith { [] };
        private _cache = _o getVariable ["COMSPEC_ATAK_MenuAce", []];
        if ((count _cache) isEqualTo 2 && {(diag_tickTime - (_cache select 0)) < 0.5} && {(diag_tickTime - (_cache select 0)) >= 0}) exitWith { _cache select 1 };
        private _run = { if ((_this select 2) isNotEqualTo "") then { [_target, _this select 2] call (missionNamespace getVariable ["COMSPEC_ATAK_TocDo", {}]); }; };
        // État courant : coche ajoutée après la traduction du libellé (fonction de modification d'ACE).
        private _tick = { params ["", "", "", "_ad"]; _ad set [1, (_ad select 1) + " ✓"]; };
        private _conv = {
            params ["_nodes", "_pre"];
            _nodes apply {
                _x params ["_id", "_txt", "", "_icon", "_key", "_kids", "_on"];
                private _name = format ["%1_%2%3", _pre, _id, ["", "*"] select _on];
                private _act = if (_on) then {
                    [_name, _txt, _icon, _run, { true }, {}, _key, [0, 0, 0], 5, [false, false, false, false, false], _tick] call comspec_atak_native_fnc_aceAction
                } else {
                    [_name, _txt, _icon, _run, { true }, {}, _key] call comspec_atak_native_fnc_aceAction
                };
                [_act, [_kids, _pre + "_" + _id] call _conv, _o]
            }
        };
        private _out = [["tree", _o] call _fn, "COMSPEC_ATAK_Wall" + (netId _o)] call _conv;
        _o setVariable ["COMSPEC_ATAK_MenuAce", [diag_tickTime, _out]];
        _out
    };
    case "wheel": {
        // Sans ACE : le même arbre, un niveau à la fois dans la molette. « Écran TOC · Afficher · Carte et flux ».
        _a params [["_o", objNull], ["_path", []]];
        if (isNull _o || {!hasInterface}) exitWith { false };
        // Relais d'une dalle de mur (objet invisible) : jamais visé, aucune action.
        if (!isNull (_o getVariable ["COMSPEC_ATAK_TocHost", objNull])) exitWith { false };
        { _o removeAction _x; } forEach (_o getVariable ["COMSPEC_ATAK_TocWheelIds", []]);
        private _level = ["tree", _o] call _fn;
        private _lead = ["Écran TOC"];
        private _at = [];
        {
            private _id = _x;
            private _i = _level findIf { (_x select 0) isEqualTo _id };
            if (_i < 0) exitWith {};
            private _txt = (_level select _i) select 1;
            // « Afficher… » en tête de ligne : sans les points de suspension (longueurs prises sur les chaînes elles-mêmes).
            private _n = (count _txt) - (count "…");
            if (_n > 0 && {(_txt select [_n]) isEqualTo "…"}) then { _txt = _txt select [0, _n]; };
            _lead pushBack _txt;
            _at pushBack _id;
            _level = (_level select _i) select 5;
        } forEach _path;
        private _ids = [];
        private _prio = 1.46;
        private _add = {
            params ["_txt", "_arg", "_on", "_hide"];
            _ids pushBack (_o addAction [format ["<t color='#7fd0b0'>%1%2</t>", ((_lead + [_txt]) joinString " · ") call comspec_atak_native_fnc_tr, ["", " ✓"] select _on],
                { ["wheelDo", [_this select 0, _this select 3]] call comspec_atak_native_fnc_tocMenu; }, _arg, _prio, false, _hide, "",
                "['wheelOk', _target] call comspec_atak_native_fnc_tocMenu", 5]);
            _prio = _prio - 0.01;
        };
        {
            _x params ["_id", "_txt", "", "", "_key", "_kids", "_on"];
            if (_kids isEqualTo []) then { [_txt, ["key", _key], _on, true] call _add; } else { [_txt, ["go", _at + [_id]], _on, false] call _add; };
        } forEach _level;
        if (_at isNotEqualTo []) then { ["Retour", ["go", _at select [0, (count _at) - 1]], false, false] call _add; };
        _o setVariable ["COMSPEC_ATAK_TocWheelIds", _ids];
        _o setVariable ["COMSPEC_ATAK_TocWheelAt", [[_o] call _sigOf, diag_tickTime, _at]];
        _o setVariable ["COMSPEC_ATAK_TocWheelBusy", false];
        true
    };
    case "wheelDo": {
        _a params [["_o", objNull], ["_arg", []]];
        _arg params [["_what", ""], ["_v", ""]];
        if (isNull _o) exitWith { false };
        // Les actions sont remplacées à l'image suivante, jamais pendant que l'une d'elles s'exécute.
        if (_what isEqualTo "go") exitWith { [{ ["wheel", _this] call comspec_atak_native_fnc_tocMenu; }, [_o, _v]] call comspec_atak_native_fnc_nextFrame; true };
        if (_v isNotEqualTo "") then { [_o, _v] call (missionNamespace getVariable ["COMSPEC_ATAK_TocDo", {}]); };
        // Retour à la racine une seconde plus tard : libellés à jour (vue cochée, luminosité, salle).
        [{ if (!isNull _this) then { ["wheel", [_this, []]] call comspec_atak_native_fnc_tocMenu; }; }, _o, 1] call comspec_atak_native_fnc_waitAndExec;
        true
    };
    case "do": {
        _a params [["_o", objNull], ["_k", ""]];
        if (isNull _o || {!(_k isEqualType "")} || {_k isEqualTo ""}) exitWith { false };
        private _host = [_o] call _hostOf;
        // Serveur plus ancien ou sans le mod (fn_tocConfig « local ») : la variable publique est posée par ce poste.
        private _mine = { ["local", _o] call comspec_atak_native_fnc_tocConfig && {!isServer} };
        private _p = _k splitString "|";
        private _rest = (_p select [1]) joinString "|";
        switch (_p param [0, ""]) do {
            case "bright": {
                private _opt = _host getVariable ["COMSPEC_ATAK_TocOpt", []];
                _host setVariable ["COMSPEC_ATAK_TocOpt", [((parseNumber _rest) max 25) min 100, _opt param [1, 0]], true];
                _o setVariable ["COMSPEC_ATAK_TocAt", -10];
            };
            case "hall": {
                if (call _mine) then { _host setVariable ["COMSPEC_ATAK_TocHall", _rest, true]; } else { ["do", ["link", [_rest, _o]]] call comspec_atak_native_fnc_tocHall; };
            };
            case "unlink": {
                if (call _mine) then { _host setVariable ["COMSPEC_ATAK_TocHall", "", true]; } else { ["do", ["unlink", _o]] call comspec_atak_native_fnc_tocHall; };
            };
            case "blink": {
                if (call _mine) then { ["comspec_atak_native_tocBlink", [_o]] call CBA_fnc_globalEvent; } else { ["do", ["blink", _o]] call comspec_atak_native_fnc_tocHall; };
            };
            case "on";
            case "off": {
                // Mur : l'écran et chacune de ses dalles.
                private _lit = _k isEqualTo "on";
                private _local = call _mine;
                {
                    if (!isNull _x) then {
                        if (_local) then { _x setVariable ["COMSPEC_ATAK_TocOff", !_lit, true]; } else { ["do", ["power", [_x, _lit]]] call comspec_atak_native_fnc_tocHall; };
                    };
                } forEach ([_host] + (_host getVariable ["COMSPEC_ATAK_TocPanels", []]));
            };
            case "test": {
                ["test", [_host, ((["surfaces", _host] call _ts) param [1, 0]) max 0]] call _ts;
            };
            case "who": {
                if (((["mode"] call comspec_atak_native_fnc_tocConfig) param [0, "server"]) isEqualTo "fallback" || {_host getVariable ["COMSPEC_ATAK_TocFb", false]}) then {
                    ["WARNING", format ["%1 : %2", "Écran TOC géré par les joueurs, non gardé par le serveur", ["reason"] call comspec_atak_native_fnc_tocConfig], 6, 20] call comspec_atak_native_fnc_notify;
                } else {
                    ["INFO", "Écran TOC tenu par le serveur : réglages gardés d'une partie à l'autre", 4, 20] call comspec_atak_native_fnc_notify;
                };
            };
            case "info": {
                private _scr = _o getVariable ["COMSPEC_ATAK_TocScr", []];
                private _hall = ["of", _o] call comspec_atak_native_fnc_tocHall;
                private _hallName = if (_hall isEqualTo "") then { "aucune" } else { (["get", _hall] call comspec_atak_native_fnc_tocHall) param [1, "?"] };
                ["INFO", [format ["Écran : %1", _scr param [0, "?"]], format ["Vue : %1", (["title", _scr param [3, ""]] call _ts) param [1, "?"]], format ["Salle : %1", _hallName]] joinString " · ", 5, 20] call comspec_atak_native_fnc_notify;
            };
            case "lay": {
                if (!isNil "comspec_atak_native_fnc_tocLayout") then { ["apply", [_o, _rest]] call comspec_atak_native_fnc_tocLayout; };
            };
            case "bc": {
                if (!isNil "comspec_atak_native_fnc_tocBroadcast") then { ["do", [_o, _rest]] call comspec_atak_native_fnc_tocBroadcast; };
            };
            case "manager": {
                // À l'image suivante : le menu ACE se referme d'abord.
                if (!isNil "comspec_atak_native_fnc_tocManager") then { [{ ["open"] call comspec_atak_native_fnc_tocManager; }, []] call comspec_atak_native_fnc_nextFrame; };
            };
            case "view": { ["show", [_o, _rest]] call _ts; };
            case "plate": {
                ["view", [_o, "squad", _rest]] call _ts;
                ["ensure", [_o, "PLATE"]] call _ts;
            };
            case "cam": { ["set", [_o, _rest]] call comspec_atak_native_fnc_tocCam; };
            case "pn": {
                // « pn|dalle|contenu » : contenu d'une seule dalle du mur (le contenu peut lui-même contenir « | »).
                private _pan = ["panelOf", [_o, parseNumber (_p param [1, "0"])]] call _ts;
                ["apply", [_pan, (_p select [2]) joinString "|"]] call _ts;
            };
            case "pntxt": {
                [{ ["edit", _this] call comspec_atak_native_fnc_tocText; }, ["panelOf", [_o, parseNumber _rest]] call _ts] call comspec_atak_native_fnc_nextFrame;
            };
            default { false };
        };
        _o setVariable ["COMSPEC_ATAK_MenuAce", nil];
        true
    };
    default { false };
};
