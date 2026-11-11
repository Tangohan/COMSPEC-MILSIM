/*
    Dessiner et planifier sur la carte du PC du poste TOC (demande de TTRD, 11/11 : « les mêmes outils que sur l'ATAK ») :
    barre d'outils au bas de la carte du PC (fn_tocLaptop), avec les fonctions du téléphone, sans copie :
      TRAIT    trait point à point (clic A, Ctrl+clic pour chaque étape, clic B), option ROUTES (suit les routes),
               flèche, couleur, épaisseur : fn_markerStroke, comme l'outil TRAIT de la carte ATAK ;
      DESSIN   dessin libre (clic gauche maintenu), option FORME FERMÉE : fn_markerStroke « DRAW » ;
      MARQUEUR camp, type, couleur, texte, durée et canal de la palette du téléphone : fn_markerDrop ;
      PLAN     graphismes de l'app Plan (zones AO, OBJ, NAI…, lignes de phase, limites, axes, attaques, points) : clics,
               clic droit ou TERMINER pour poser ; même plan partagé du camp que l'app Plan (fn_planServer) ;
      EFFACER  clic sur un marqueur de joueur, un trait ou un élément du plan : supprimé pour tous ;
      ANNULER  mon dernier trait ; ANNULER LE PLAN : mon dernier élément du plan (historique du serveur).
    Mêmes objets que l'ATAK (marqueurs du canal choisi, plan du camp) : ils sont sur les téléphones, sur les écrans muraux
    en carte (fn_tocScreen) et dans la sauvegarde de la salle (fn_tocPersist). Réglages de style gardés dans le profil,
    comme ceux du téléphone (COMSPEC_ATAK_DrawColor, DrawWidth, DrawArrow, DrawClosed, DrawRoads ; palette des marqueurs).
    La carte du PC sert de carte aux outils du téléphone le temps qu'il est ouvert (fn_mapCtrl, COMSPEC_ATAK_MapCtrlOverride).
    Modes : ["build"] (réglages dans le registre du PC, posés par sa grille) | ["tool", outil] | ["down", [pos, ctrl]] | ["move", pos] | ["up", pos]
            | ["left", pos] | ["right", pos] -> vrai si l'outil a pris le clic | ["draw", carte] | ["act", clé] | ["unload"]
*/
params [["_mode", "draw"], ["_a", []]];
disableSerialization;
private _fn = comspec_atak_native_fnc_tocDraw;
private _st = uiNamespace getVariable ["COMSPEC_ATAK_TocLap", createHashMap];
private _ui = _st getOrDefault ["ui", createHashMap];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_State", _s];
private _tool = _st getOrDefault ["tool", "SELECT"];
private _status = { ["status", _this] call comspec_atak_native_fnc_tocLaptop; };
private _cat = [] call comspec_atak_native_fnc_planCatalog;
private _planSrv = { params ["_op", "_args"]; ["comspec_atak_native_planSrv", [player, _op, _args, format ["TOC%1", floor (diag_tickTime * 10)], ""]] call CBA_fnc_serverEvent; };
private _cols = [["Rouge", "ColorRed"], ["Orange", "ColorOrange"], ["Jaune", "ColorYellow"], ["Vert", "ColorGreen"], ["Bleu", "ColorBlue"], ["Rose", "ColorPink"], ["Noir", "ColorBlack"], ["Blanc", "ColorWhite"]];
switch (_mode) do {
    case "build": {
        // Réglages des outils (combos, champs) créés dans le registre du PC (fn_tocLaptop « mk ») et posés par sa grille,
        // sous la carte ; les boutons (TRAIT, DESSIN, PLAN, EFFACER, ANNULER, FLÈCHE…) viennent de la table du PC.
        // En 4.4.0 cette barre posait ses contrôles elle-même avec « _h = _H * 0.03 » : le SQF ne distingue pas _h de _H,
        // la hauteur d'écran devenait 0,03 et les libellés tombaient à un demi-pixel (barre sombre, boutons sans texte),
        // et « _W » écrasait la largeur reçue (fond sous le panneau de droite).
        private _mkc = { params ["_cls", "_key", ["_tip", ""]]; private _c = ["mk", [_cls, _key]] call comspec_atak_native_fnc_tocLaptop; if (_tip isNotEqualTo "") then { ["tip", [_c, _tip]] call comspec_atak_native_fnc_tocLaptop; }; _c };
        private _c1 = ["COMSPEC_RscCombo", "dColor", "Couleur du trait, vue par tous les joueurs"] call _mkc;
        { private _i = _c1 lbAdd ((_x select 0) call comspec_atak_native_fnc_tr); _c1 lbSetData [_i, _x select 1]; } forEach _cols;
        _c1 lbSetCurSel ((_cols findIf { (_x select 1) isEqualTo (profileNamespace getVariable ["COMSPEC_ATAK_DrawColor", "ColorRed"]) }) max 0);
        _c1 ctrlAddEventHandler ["LBSelChanged", { params ["_c", "_i"]; profileNamespace setVariable ["COMSPEC_ATAK_DrawColor", _c lbData _i]; [] call comspec_atak_native_fnc_saveProfile; }];
        private _c2 = ["COMSPEC_RscCombo", "dWidth", "Épaisseur du trait, vue par tous les joueurs"] call _mkc;
        { private _i = _c2 lbAdd ((_x select 0) call comspec_atak_native_fnc_tr); _c2 lbSetValue [_i, _x select 1]; } forEach [["Fin", 1], ["Moyen", 2], ["Épais", 3]];
        _c2 lbSetCurSel (((profileNamespace getVariable ["COMSPEC_ATAK_DrawWidth", 2]) - 1) max 0 min 2);
        _c2 ctrlAddEventHandler ["LBSelChanged", { params ["_c", "_i"]; profileNamespace setVariable ["COMSPEC_ATAK_DrawWidth", _c lbValue _i]; [] call comspec_atak_native_fnc_saveProfile; }];
        // Plan : graphisme, texte (TERMINER et ABANDONNER : table du PC).
        private _pk = ["COMSPEC_RscCombo", "dKind", "Graphisme du plan : zone (polygone, 2 clics = rectangle), ligne, point ou direction"] call _mkc;
        {
            _x params ["_cat", "_lbl", "_keys"];
            { private _k = (_cat get "kinds") getOrDefault [_x, []]; if ((count _k) >= 3) then { private _i = _pk lbAdd ((format ["%1 · %2", _lbl, _k select 2]) call comspec_atak_native_fnc_tr); _pk lbSetData [_i, _x]; }; } forEach _keys;
        } forEach ((_cat get "cats") apply { [_cat, _x select 1, _x select 2] });
        _pk lbSetCurSel 0;
        ["COMSPEC_RscEdit", "dPlanTxt", "Texte du graphisme (vide : celui du type). Exemple : OBJ RAVEN, PL BLUE"] call _mkc;
        // Palette des marqueurs du téléphone (camp, type, couleur, texte, durée, canal).
        ([] call comspec_atak_native_fnc_markerPaletteData) params ["_affs", "_typesBy"];
        private _ma = ["COMSPEC_RscCombo", "mAff", "Camp du marqueur, comme la palette de l'ATAK"] call _mkc;
        { private _i = _ma lbAdd ((_x select 1) call comspec_atak_native_fnc_tr); _ma lbSetData [_i, _x select 0]; } forEach _affs;
        _ma lbSetCurSel ((_affs findIf { (_x select 0) isEqualTo (_s getOrDefault ["markerAff", "o"]) }) max 0);
        _ma ctrlAddEventHandler ["LBSelChanged", { params ["_c", "_i"]; ((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap])) set ["markerAff", _c lbData _i]; ["types"] call comspec_atak_native_fnc_tocDraw; }];
        private _mt = ["COMSPEC_RscCombo", "mType", "Type du marqueur"] call _mkc;
        _mt ctrlAddEventHandler ["LBSelChanged", { params ["_c", "_i"]; ((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap])) set ["markerType", _c lbData _i]; }];
        private _mc = ["COMSPEC_RscCombo", "mColor", "Couleur du marqueur"] call _mkc;
        { private _i = _mc lbAdd ((_x select 0) call comspec_atak_native_fnc_tr); _mc lbSetData [_i, _x select 1]; } forEach ([["Couleur du camp", "AUTO"]] + _cols);
        _mc lbSetCurSel 0;
        _mc ctrlAddEventHandler ["LBSelChanged", { params ["_c", "_i"]; ((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap])) set ["markerColor", _c lbData _i]; }];
        ["COMSPEC_RscEdit", "mText", "Nom du marqueur (vide : nom automatique, comme l'ATAK). Exemple : CP NORD"] call _mkc;
        private _md = ["COMSPEC_RscCombo", "mTtl", "Durée du marqueur : effacé pour tous à l'échéance, comme sur l'ATAK"] call _mkc;
        { private _i = _md lbAdd ((_x select 0) call comspec_atak_native_fnc_tr); _md lbSetValue [_i, _x select 1]; } forEach [["Permanent", 0], ["15 min", 15], ["30 min", 30], ["1 h", 60], ["2 h", 120]];
        _md lbSetCurSel ((([0, 15, 30, 60, 120] find (profileNamespace getVariable ["COMSPEC_ATAK_MarkerTtl", 0])) max 0));
        _md ctrlAddEventHandler ["LBSelChanged", { params ["_c", "_i"]; profileNamespace setVariable ["COMSPEC_ATAK_MarkerTtl", _c lbValue _i]; [] call comspec_atak_native_fnc_saveProfile; }];
        private _mn = ["COMSPEC_RscCombo", "mChan", "Canal des marqueurs et des traits (qui les voit), comme la palette de l'ATAK"] call _mkc;
        for "_i" from 0 to 5 do { private _j = _mn lbAdd ((["channelName", _i] call comspec_atak_native_fnc_markerPlace) call comspec_atak_native_fnc_tr); _mn lbSetValue [_j, _i]; };
        _mn lbSetCurSel ((["channel"] call comspec_atak_native_fnc_markerPlace) max 0 min 5);
        _mn ctrlAddEventHandler ["LBSelChanged", { params ["_c", "_i"]; ["setChannel", _c lbValue _i] call comspec_atak_native_fnc_markerPlace; }];
        ["types"] call _fn;
        // La carte du PC sert de carte aux outils du téléphone tant que le PC est ouvert.
        _st set ["mapModeWas", _s getOrDefault ["mapMode", "SELECT"]];
        uiNamespace setVariable ["COMSPEC_ATAK_MapCtrlOverride", _ui getOrDefault ["map", controlNull]];
    };
    case "types": {
        private _mt = _ui getOrDefault ["mType", controlNull];
        if (isNull _mt) exitWith {};
        ([] call comspec_atak_native_fnc_markerPaletteData) params ["", "_typesBy"];
        private _l = _typesBy getOrDefault [_s getOrDefault ["markerAff", "o"], []];
        lbClear _mt;
        { private _i = _mt lbAdd ((_x select 1) call comspec_atak_native_fnc_tr); _mt lbSetData [_i, _x select 0]; _mt lbSetPicture [_i, getText (configFile >> "CfgMarkers" >> (_x select 0) >> "icon")]; } forEach _l;
        _mt lbSetCurSel ((_l findIf { (_x select 0) isEqualTo (_s getOrDefault ["markerType", ""]) }) max 0);
        if ((count _l) > 0) then { _s set ["markerType", (_l select ((lbCurSel _mt) max 0)) select 0]; };
    };
    case "show": {
        // Outil allumé en vert ; ses réglages sont montrés par la mise en page du PC (fn_tocLaptop « layout »).
        private _on = [0.18, 0.38, 0.21, 1]; private _off = [0.07, 0.085, 0.075, 1];
        { (_ui getOrDefault ["tool" + _x, controlNull]) ctrlSetBackgroundColor ([_off, _on] select (_x isEqualTo _tool)); } forEach ["LINE", "DRAW", "PLAN", "ERASE"];
        { (_ui getOrDefault [_x select 0, controlNull]) ctrlSetBackgroundColor ([_off, _on] select (profileNamespace getVariable [_x select 1, false])); } forEach [["dArrow", "COMSPEC_ATAK_DrawArrow"], ["dRoads", "COMSPEC_ATAK_DrawRoads"], ["dClosed", "COMSPEC_ATAK_DrawClosed"]];
        // Outils du téléphone : même mode que l'outil choisi au PC (TRAIT, DESSIN), sinon sélection.
        _s set ["mapMode", ["SELECT", _tool] select (_tool in ["LINE", "DRAW"])];
        if !(_tool in ["LINE", "DRAW"]) then { _s set ["drawStroke", []]; _s set ["drawing", false]; };
        if (_tool isNotEqualTo "PLAN") then { _st set ["planPts", []]; };
    };
    case "act": {
        switch (_a) do {
            case "toolLINE"; case "toolDRAW"; case "toolPLAN"; case "toolERASE": { ["tool", _a select [4]] call comspec_atak_native_fnc_tocLaptop; };
            case "dArrow": { profileNamespace setVariable ["COMSPEC_ATAK_DrawArrow", !(profileNamespace getVariable ["COMSPEC_ATAK_DrawArrow", false])]; };
            case "dRoads": { profileNamespace setVariable ["COMSPEC_ATAK_DrawRoads", !(profileNamespace getVariable ["COMSPEC_ATAK_DrawRoads", false])]; };
            case "dClosed": { profileNamespace setVariable ["COMSPEC_ATAK_DrawClosed", !(profileNamespace getVariable ["COMSPEC_ATAK_DrawClosed", false])]; };
            case "dClear": { ["CLEARMINE"] call comspec_atak_native_fnc_markerStroke; "Mes traits et dessins sont effacés" call _status; };
            case "dUndo": {
                if (_tool isEqualTo "PLAN") then {
                    if ((count (_st getOrDefault ["planPts", []])) > 0) exitWith { (_st getOrDefault ["planPts", []]) deleteAt ((count (_st get "planPts")) - 1); "Dernier point du graphisme retiré" call _status; };
                    ["undo", []] call _planSrv; "Mon dernier élément du plan est retiré" call _status;
                } else {
                    ["UNDO"] call comspec_atak_native_fnc_markerStroke; "Mon dernier trait est effacé" call _status;
                };
            };
            case "dPlanEnd": { ["right", []] call _fn; };
            case "dPlanCancel": { _st set ["planPts", []]; "Graphisme abandonné" call _status; };
        };
        [] call comspec_atak_native_fnc_saveProfile;
        ["show"] call _fn;
    };
    case "down": {
        _a params ["_p", ["_ctrl", false]];
        if !(_tool in ["LINE", "DRAW"]) exitWith { false };
        ["DOWN", [_p select 0, _p select 1, 0], _ctrl] call comspec_atak_native_fnc_markerStroke
    };
    case "move": {
        _st set ["cursorW", _a];
        if (_tool isNotEqualTo "DRAW") exitWith { false };
        ["MOVE", [_a select 0, _a select 1, 0]] call comspec_atak_native_fnc_markerStroke
    };
    case "up": {
        if (_tool isNotEqualTo "DRAW") exitWith { false };
        ["UP", [_a select 0, _a select 1, 0]] call comspec_atak_native_fnc_markerStroke
    };
    case "left": {
        // Clic gauche (relâché sans glisser) : marqueur, point du plan ou effacement.
        private _p = [_a select 0, _a select 1];
        switch (_tool) do {
            case "MARKER": {
                _s set ["markerText", trim (ctrlText (_ui getOrDefault ["mText", controlNull]))];
                [[_p select 0, _p select 1, 0]] call comspec_atak_native_fnc_markerDrop;
                format ["Marqueur posé : grille %1", [[_p select 0, _p select 1, 0], 8] call comspec_atak_native_fnc_gridRef] call _status;
                true
            };
            case "PLAN": {
                private _k = (_cat get "kinds") getOrDefault [(_ui get "dKind") lbData (lbCurSel (_ui get "dKind")), []];
                private _pts = _st getOrDefault ["planPts", []];
                _pts pushBack _p;
                _st set ["planPts", _pts];
                // Point : posé tout de suite ; direction : au second clic.
                if ((_k param [3, ""]) isEqualTo "POINT" || {(_k param [3, ""]) isEqualTo "DIR" && {(count _pts) >= 2}}) then { ["right", []] call _fn; };
                true
            };
            case "ERASE": {
                private _map = _ui getOrDefault ["map", controlNull];
                private _sp = _map ctrlMapWorldToScreen _p;
                // Élément du plan du camp le plus proche (12 px), sinon marqueur ou trait de joueur.
                private _plan = (missionNamespace getVariable [format ["COMSPEC_ATAK_Plan_%1", side group player], [0, [], ""]]) param [1, []];
                private _best = pixelW * 12; private _id = "";
                {
                    private _eid = _x select 0;
                    { private _d = (_map ctrlMapWorldToScreen [_x select 0, _x select 1]) distance2D _sp; if (_d < _best) then { _best = _d; _id = _eid; }; } forEach (_x param [2, []]);
                } forEach _plan;
                if (_id isNotEqualTo "") exitWith { ["del", [_id]] call _planSrv; "Élément du plan supprimé" call _status; true };
                private _m = [_map, _sp] call comspec_atak_native_fnc_markerAt;
                if (_m isEqualTo "") then {
                    // Trait : point de la polyligne le plus proche.
                    private _bd = pixelW * 10;
                    {
                        private _pl = markerPolyline _x;
                        for "_i" from 0 to ((count _pl) - 2) step 2 do {
                            private _d = (_map ctrlMapWorldToScreen [_pl select _i, _pl select (_i + 1)]) distance2D _sp;
                            if (_d < _bd) then { _bd = _d; _m = _x; };
                        };
                    } forEach (allMapMarkers select { (markerShape _x) isEqualTo "POLYLINE" && {(_x find "_USER_DEFINED") isEqualTo 0} });
                };
                if (_m isEqualTo "") exitWith { "Rien à effacer sous le clic" call _status; true };
                if ([_m] call comspec_atak_native_fnc_markerDelete) then { "Supprimé pour tous" call _status; };
                true
            };
            default { false };
        }
    };
    case "right": {
        // Pose du graphisme du plan en cours (clic droit ou TERMINER).
        if (_tool isNotEqualTo "PLAN") exitWith { false };
        private _kk = (_ui get "dKind") lbData (lbCurSel (_ui get "dKind"));
        private _k = (_cat get "kinds") getOrDefault [_kk, []];
        private _pts = +(_st getOrDefault ["planPts", []]);
        _k params ["", "", "", ["_geo", "LINE"], ["_col", "WHITE"], ["_txt0", ""], ["_dash", false], ["_arrow", false]];
        if (_geo isEqualTo "AREA" && {(count _pts) isEqualTo 2}) then {
            // Deux clics : rectangle, comme l'app Plan.
            _pts params ["_p1", "_p2"];
            _pts = [_p1, [_p2 select 0, _p1 select 1], _p2, [_p1 select 0, _p2 select 1]];
        };
        private _need = (createHashMapFromArray [["POINT", 1], ["DIR", 2], ["LINE", 2], ["AREA", 3]]) getOrDefault [_geo, 2];
        if ((count _pts) < _need) exitWith { format ["Il faut au moins %1 point(s) pour ce graphisme", _need] call _status; true };
        private _txt = trim (ctrlText (_ui get "dPlanTxt"));
        private _it = [objNull, "clean", ["", _kk, _pts, [_txt, _txt0] select (_txt isEqualTo ""), _col, _dash, _arrow, profileNamespace getVariable ["COMSPEC_ATAK_DrawWidth", 2], 0, name player, ""]] call comspec_atak_native_fnc_planServer;
        _st set ["planPts", []];
        if ((count _it) isEqualTo 0) exitWith { "Graphisme refusé (points hors carte ?)" call _status; true };
        ["add", [_it]] call _planSrv;
        format ["%1 posé sur le plan du camp", _k param [2, _kk]] call _status;
        true
    };
    case "draw": {
        // Carte du PC : traits stylés et tracé en cours (fn_strokeDraw), plan du camp et graphisme en cours (fn_planDraw),
        // zones (fn_zoneDraw), comme la carte de l'ATAK.
        private _map = _a;
        [_map, _st getOrDefault ["cursorW", []]] call comspec_atak_native_fnc_strokeDraw;
        [_map, (missionNamespace getVariable [format ["COMSPEC_ATAK_Plan_%1", side group player], [0, [], ""]]) param [1, []], 0.85] call comspec_atak_native_fnc_planDraw;
        private _pts = _st getOrDefault ["planPts", []];
        if ((count _pts) > 0) then {
            private _kk = (_ui get "dKind") lbData (lbCurSel (_ui get "dKind"));
            private _k = (_cat get "kinds") getOrDefault [_kk, []];
            private _cur = _st getOrDefault ["cursorW", []];
            private _prev = +_pts;
            if ((count _cur) >= 2) then { _prev pushBack [_cur select 0, _cur select 1]; };
            [_map, [["_draft", _kk, _prev, trim (ctrlText (_ui get "dPlanTxt")), _k param [4, "WHITE"], _k param [6, false], _k param [7, false], profileNamespace getVariable ["COMSPEC_ATAK_DrawWidth", 2], 0, "", ""]], 0.6] call comspec_atak_native_fnc_planDraw;
        };
        if (profileNamespace getVariable ["COMSPEC_ATAK_ZonesShow", true]) then { [_map, 1, 1] call comspec_atak_native_fnc_zoneDraw; };
    };
    case "unload": {
        uiNamespace setVariable ["COMSPEC_ATAK_MapCtrlOverride", controlNull];
        _s set ["mapMode", _st getOrDefault ["mapModeWas", "SELECT"]];
        _s set ["drawStroke", []]; _s set ["drawing", false];
        _st set ["planPts", []];
    };
    default { false };
};
