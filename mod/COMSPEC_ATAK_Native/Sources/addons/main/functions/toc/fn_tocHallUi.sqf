/*
    Onglet SALLE du PC maître (fn_tocLaptop) : gestion de toute la salle TOC du poste (fn_tocHall).
    À la place de la carte : plan du mur vu depuis le poste (chaque écran lié à sa place réelle, murs d'images découpés
    en dalles, écrans proches non liés en pâle) ; un clic choisit un écran ou une dalle. À droite : salle (créer,
    renommer), lier tout ce qui est dans un rayon, viser un écran pour le lier, contenu de l'écran choisi (carte, SAT,
    caméra, mosaïque, escouade, horloge, registres…), nom, allumer / éteindre, identifier (il clignote 2 s), lier /
    délier, réglage de la mosaïque (rotation, source case par case), scènes (enregistrer, remettre, supprimer), tout
    allumer / tout éteindre.
    Modes : ["build"] (contrôles dans le registre du PC, posés par sa grille, fn_tocLaptop) | ["refresh"]
            | ["pick", netId] | ["act", clé] | ["aim"] (viser un écran : le PC se ferme, l'écran regardé est lié)
*/
params [["_mode", "refresh"], ["_a", []]];
disableSerialization;
private _fn = comspec_atak_native_fnc_tocHallUi;
private _st = uiNamespace getVariable ["COMSPEC_ATAK_TocLap", createHashMap];
private _ui = _st getOrDefault ["ui", createHashMap];
private _side = str side group player;
private _pc = _st getOrDefault ["obj", objNull];
private _hid = ["of", _pc] call comspec_atak_native_fnc_tocHall;
private _status = { ["status", _this] call comspec_atak_native_fnc_tocLaptop; };
private _esc = { params ["_t"]; if !(_t isEqualType "") then { _t = str _t; }; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };
private _selObj = { objectFromNetId (_st getOrDefault ["selH", ""]) };
private _contentOf = {
    params ["_o"];
    private _c = _o getVariable ["COMSPEC_ATAK_TocScr", []];
    private _v = _c param [3, "SPLIT"];
    if (_o getVariable ["COMSPEC_ATAK_TocOff", false]) exitWith { "éteint" };
    if ((count (_o getVariable ["COMSPEC_ATAK_ScreenCast", []])) >= 5) exitWith { "diapositives" };
    (createHashMapFromArray [["SPLIT", "carte et flux"], ["MAP", "carte seule"], ["FEED", "flux seul"], ["NET", "réseau"], ["ORBAT", "ORBAT"], ["TOC", "PIP du poste"],
        ["CAM", "caméra"], ["SAT", format ["SAT %1", (_c param [8, 0]) + 1]], ["CLOCK", "horloge"], ["SQUAD", "escouade " + (_c param [9, ""])], ["LOG", "main courante"],
        ["EXCHECK", "exécution"], ["KILLS", "pertes"], ["REQ", "demandes"], ["STRIP", "bandeau horloge"], ["PLATE", "plaque " + (_c param [9, ""])], ["MOSAIC", "mosaïque"]]) getOrDefault [_v, _v]
};
switch (_mode) do {
    case "build": {
        // Contrôles de l'onglet dans le registre du PC (fn_tocLaptop « mk ») : la grille du PC les pose (plan à gauche,
        // gestion à droite) ; les boutons viennent de la table du PC (cible H). Dalles du plan : posées ici à chaque
        // rafraîchissement, hors registre.
        private _mkc = { params ["_cls", "_key", ["_tip", ""]]; private _c = ["mk", [_cls, _key]] call comspec_atak_native_fnc_tocLaptop; if (_tip isNotEqualTo "") then { ["tip", [_c, _tip]] call comspec_atak_native_fnc_tocLaptop; }; _c };
        (["COMSPEC_RscText", "hPlanBg"] call _mkc) ctrlSetBackgroundColor [0.035, 0.045, 0.04, 1];
        (_ui get "hPlanBg") setVariable ["bg", true];
        (["COMSPEC_RscStructuredText", "hPlanTitle"] call _mkc) ctrlSetBackgroundColor [0, 0, 0, 0.5];
        // Écrans et dalles du plan : boutons réutilisés à chaque rafraîchissement.
        private _tiles = [];
        for "_i" from 1 to 48 do {
            private _b = ["mk", ["COMSPEC_RscButton", ""]] call comspec_atak_native_fnc_tocLaptop;
            _b ctrlSetFontHeight (safeZoneH * 0.0145);
            _b ctrlShow false;
            _b ctrlAddEventHandler ["ButtonClick", { params ["_c"]; ["pick", _c getVariable ["COMSPEC_ATAK_Id", ""]] call comspec_atak_native_fnc_tocHallUi; }];
            _tiles pushBack _b;
        };
        _st set ["hallTiles", _tiles];
        ["COMSPEC_RscStructuredText", "hInfo"] call _mkc;
        ["COMSPEC_RscEdit", "hName", "Nom de la salle, montré dans les menus des écrans. Exemple : TOC BRAVO"] call _mkc;
        private _rc = ["COMSPEC_RscCombo", "hRad", "Rayon autour du poste pour LIER TOUT. Exemple : 20 m pour une salle de bâtiment"] call _mkc;
        { private _i = _rc lbAdd ((_x select 0) call comspec_atak_native_fnc_tr); _rc lbSetValue [_i, _x select 1]; } forEach [["10 m", 10], ["20 m", 20], ["40 m", 40]];
        _rc lbSetCurSel 1;
        ["COMSPEC_RscStructuredText", "hSel"] call _mkc;
        ["COMSPEC_RscCombo", "hWhat", "Contenu montré par l'écran ou la dalle choisi"] call _mkc;
        ["COMSPEC_RscEdit", "hScrName", "Nom de l'écran, en tête de son image. Exemple : SAT CENTRE"] call _mkc;
        // Mosaïque choisie : rotation des pages, source case par case.
        private _mr = ["COMSPEC_RscCombo", "hMosRot", "Rotation des pages de la mosaïque"] call _mkc;
        { private _i = _mr lbAdd ((_x select 0) call comspec_atak_native_fnc_tr); _mr lbSetValue [_i, _x select 1]; } forEach [["Sans rotation", 0], ["Rotation 10 s", 10], ["Rotation 20 s", 20], ["Rotation 30 s", 30]];
        _mr lbSetCurSel 1;
        _mr ctrlAddEventHandler ["LBSelChanged", { ["act", "hMosRot"] call comspec_atak_native_fnc_tocHallUi; }];
        private _mc = ["COMSPEC_RscCombo", "hMosCase", "Case de la mosaïque"] call _mkc;
        for "_i" from 1 to 9 do { _mc lbAdd ((format ["Case %1", _i]) call comspec_atak_native_fnc_tr); };
        _mc lbSetCurSel 0;
        ["COMSPEC_RscCombo", "hMosSrc", "Caméra à placer dans la case"] call _mkc;
        ["COMSPEC_RscCombo", "hScene", "Scène enregistrée de la salle"] call _mkc;
        ["COMSPEC_RscEdit", "hSceneName", "Nom de la scène : tout le mur tel qu'il est maintenant. Exemple : BRIEFING, OPÉRATION, NUIT"] call _mkc;
    };
    case "pick": { _st set ["selH", _a]; ["refresh"] call _fn; };
    case "aim": {
        // Viser un écran : PC refermé, l'écran du camp regardé (20 s au plus) est lié, puis le PC se rouvre.
        if (_hid isEqualTo "") exitWith { "Créez d'abord la salle de ce PC" call _status; };
        closeDialog 0;
        ["INFO", "Regardez l'écran à lier à la salle (20 s)", 4, 30] call comspec_atak_native_fnc_notify;
        [{
            params ["_args", "_h"];
            _args params ["_pc", "_hid", "_end"];
            private _t = cursorObject;
            private _hst = _t getVariable ["COMSPEC_ATAK_TocHost", objNull];
            if (!isNull _hst) then { _t = _hst; };
            private _ok = !isNull _t && {(count (_t getVariable ["COMSPEC_ATAK_TocScr", []])) >= 8} && {(player distance _t) < 60};
            if (_ok || {diag_tickTime > _end}) then {
                [_h] call CBA_fnc_removePerFrameHandler;
                if (_ok) then {
                    ["do", ["link", [_hid, _t]]] call comspec_atak_native_fnc_tocHall;
                    ["INFO", format ["Écran %1 lié à la salle", (_t getVariable ["COMSPEC_ATAK_TocScr", []]) param [0, "TOC"]], 3, 30] call comspec_atak_native_fnc_notify;
                };
                if (alive player && {(player distance _pc) < 6}) then { [{ ["open", _this] call comspec_atak_native_fnc_tocPost; }, _pc, 0.5] call comspec_atak_native_fnc_waitAndExec; };
            };
        }, 0.25, [_pc, _hid, diag_tickTime + 20]] call CBA_fnc_addPerFrameHandler;
    };
    case "act": {
        private _o = call _selObj;
        private _txtOf = { trim (ctrlText (_ui get _this)) };
        switch (_a) do {
            case "hCreate": {
                private _nm = toUpper ("hName" call _txtOf);
                if (_nm isEqualTo "") exitWith { "Écrivez le nom de la salle" call _status; };
                if (_hid isEqualTo "") then {
                    ["do", ["create", [_nm, _side, _pc]]] call comspec_atak_native_fnc_tocHall;
                    format ["Salle %1 créée : ce PC en est le poste maître", _nm] call _status;
                } else {
                    ["do", ["rename", [_hid, _nm]]] call comspec_atak_native_fnc_tocHall;
                    format ["Salle renommée : %1", _nm] call _status;
                };
            };
            case "hLinkAll": {
                if (_hid isEqualTo "") exitWith { "Créez d'abord la salle de ce PC" call _status; };
                private _rc = _ui get "hRad";
                ["do", ["linkNear", [_hid, getPosASL _pc, _rc lbValue ((lbCurSel _rc) max 0)]]] call comspec_atak_native_fnc_tocHall;
                format ["Écrans du camp à moins de %1 m liés à la salle", _rc lbValue ((lbCurSel _rc) max 0)] call _status;
            };
            case "hAim": { ["aim"] call _fn; };
            case "hShow": {
                if (isNull _o) exitWith { "Choisissez d'abord un écran sur le plan" call _status; };
                private _w = _ui get "hWhat";
                ["apply", [_o, _w lbData (lbCurSel _w)]] call comspec_atak_native_fnc_tocScreen;
                if (_o getVariable ["COMSPEC_ATAK_TocOff", false]) then { ["do", ["power", [_o, true]]] call comspec_atak_native_fnc_tocHall; };
                format ["%1 : %2", (_o getVariable ["COMSPEC_ATAK_TocScr", []]) param [0, "écran"], _w lbText (lbCurSel _w)] call _status;
            };
            case "hScrRen": {
                private _n = "hScrName" call _txtOf;
                if (isNull _o || {_n isEqualTo ""}) exitWith { "Choisissez un écran et écrivez son nom" call _status; };
                private _c = _o getVariable ["COMSPEC_ATAK_TocScr", []];
                ["add", [_o, _n select [0, 32], _c select 1, _c select 2, _c select 3, _c select 6, _c select 7]] call comspec_atak_native_fnc_tocScreen;
                format ["Écran renommé : %1", _n] call _status;
            };
            case "hPower": {
                if (isNull _o) exitWith { "Choisissez d'abord un écran sur le plan" call _status; };
                private _on = _o getVariable ["COMSPEC_ATAK_TocOff", false];
                ["do", ["power", [_o, _on]]] call comspec_atak_native_fnc_tocHall;
                (["Écran éteint", "Écran rallumé"] select _on) call _status;
            };
            case "hBlink": {
                if (isNull _o) exitWith { "Choisissez d'abord un écran sur le plan" call _status; };
                ["do", ["blink", _o]] call comspec_atak_native_fnc_tocHall;
                "L'écran choisi clignote dans la salle" call _status;
            };
            case "hLink": {
                if (isNull _o) exitWith { "Choisissez d'abord un écran sur le plan" call _status; };
                if (_hid isEqualTo "") exitWith { "Créez d'abord la salle de ce PC" call _status; };
                if ((["of", _o] call comspec_atak_native_fnc_tocHall) isEqualTo _hid) then {
                    ["do", ["unlink", _o]] call comspec_atak_native_fnc_tocHall; "Écran délié de la salle" call _status;
                } else {
                    ["do", ["link", [_hid, _o]]] call comspec_atak_native_fnc_tocHall; "Écran lié à la salle" call _status;
                };
            };
            case "hMosRot": {
                if (isNull _o) exitWith {};
                private _c = +(["cfg", _o] call comspec_atak_native_fnc_tocMosaic);
                private _mr = _ui get "hMosRot";
                _c set [4, _mr lbValue ((lbCurSel _mr) max 0)];
                ["view", [_o, "mosaic", _c]] call comspec_atak_native_fnc_tocScreen;
            };
            case "hMosPut": {
                if (isNull _o) exitWith { "Choisissez d'abord une mosaïque sur le plan" call _status; };
                private _c = +(["cfg", _o] call comspec_atak_native_fnc_tocMosaic);
                private _cs = _ui get "hMosSrc";
                private _k = lbCurSel (_ui get "hMosCase");
                // Passage en choix case par case : on part des sources montrées maintenant.
                if ((_c select 1) isNotEqualTo "MANUAL") then { _c set [3, (["tiles", _o] call comspec_atak_native_fnc_tocMosaic) apply { _x select 0 }]; _c set [1, "MANUAL"]; };
                private _man = +(_c select 3);
                while { (count _man) <= _k } do { _man pushBack ""; };
                _man set [_k, _cs lbData (lbCurSel _cs)];
                _c set [3, _man];
                ["view", [_o, "mosaic", _c]] call comspec_atak_native_fnc_tocScreen;
                ["view", [_o, "mode", "MOSAIC"]] call comspec_atak_native_fnc_tocScreen;
                format ["Case %1 : %2", _k + 1, _cs lbText (lbCurSel _cs)] call _status;
            };
            case "hSceneSave": {
                private _n = toUpper ("hSceneName" call _txtOf);
                if (_hid isEqualTo "" || {_n isEqualTo ""}) exitWith { "Écrivez le nom de la scène" call _status; };
                ["do", ["sceneSave", [_hid, _n]]] call comspec_atak_native_fnc_tocHall;
                format ["Scène %1 enregistrée : tout le mur tel qu'il est", _n] call _status;
            };
            case "hSceneGo"; case "hSceneDel": {
                private _sc = _ui get "hScene";
                private _n = _sc lbData (lbCurSel _sc);
                if (_n isEqualTo "") exitWith { "Choisissez d'abord une scène" call _status; };
                ["do", [["sceneApply", "sceneDel"] select (_a isEqualTo "hSceneDel"), [_hid, _n]]] call comspec_atak_native_fnc_tocHall;
                format [["Scène %1 remise sur le mur", "Scène %1 supprimée"] select (_a isEqualTo "hSceneDel"), _n] call _status;
            };
            case "hAllOn"; case "hAllOff": {
                if (_hid isEqualTo "") exitWith { "Créez d'abord la salle de ce PC" call _status; };
                ["do", ["powerAll", [_hid, _a isEqualTo "hAllOn"]]] call comspec_atak_native_fnc_tocHall;
                (["Tous les écrans de la salle éteints", "Tous les écrans de la salle allumés"] select (_a isEqualTo "hAllOn")) call _status;
            };
        };
        _st set ["hallSig", ""];
        [{ ["refresh"] call comspec_atak_native_fnc_tocHallUi; }, [], 0.4] call comspec_atak_native_fnc_waitAndExec;
    };
    case "refresh": {
        if ((count _ui) isEqualTo 0 || {(_st getOrDefault ["tab", ""]) isNotEqualTo "SALLE"}) exitWith {};
        private _hall = ["get", _hid] call comspec_atak_native_fnc_tocHall;
        private _linked = if (_hid isEqualTo "") then { [] } else { ["screens", _hid] call comspec_atak_native_fnc_tocHall };
        // Écrans du camp proches du poste, liés ailleurs ou à personne : montrés en pâle pour pouvoir les lier.
        private _near = ((missionNamespace getVariable ["COMSPEC_ATAK_TocScreens", []]) select {
            !isNull _x && {isNull (_x getVariable ["COMSPEC_ATAK_TocHost", objNull])} && {(_x distance _pc) < 40} && {!(_x in _linked)}
                && {((_x getVariable ["COMSPEC_ATAK_TocScr", []]) param [1, ""]) in ["", _side]}
        });
        (_ui get "hInfo") ctrlSetStructuredText parseText ((if (_hid isEqualTo "") then {
            "<t size='1.05' font='COMSPEC_BarlowBold'>Ce PC n'a pas encore de salle</t><br/><t size='0.8' color='#8a9a93'>Donnez-lui un nom puis CRÉER LA SALLE : ce PC en sera le poste maître.</t>"
        } else {
            format ["<t size='1.05' font='COMSPEC_BarlowBold'>%1</t>  <t size='0.8' color='#8a9a93'>%2 écran(s) lié(s) · %3 scène(s)</t>", [_hall param [1, ""]] call _esc, count _linked, count (_hall param [3, []])]
        }) call comspec_atak_native_fnc_tr);
        ["label", [["hCreate", "RENOMMER LA SALLE", "RENOMMER"], ["hCreate", "CRÉER LA SALLE", "CRÉER"]] select (_hid isEqualTo "")] call comspec_atak_native_fnc_tocLaptop;
        if ((_ui get "hName") getVariable ["COMSPEC_ATAK_Hid", "-"] isNotEqualTo _hid) then {
            (_ui get "hName") setVariable ["COMSPEC_ATAK_Hid", _hid];
            (_ui get "hName") ctrlSetText (_hall param [1, ""]); // i18n:brut (nom saisi)
        };
        // Plan : direction et hauteur de chaque écran vues depuis le poste (100° de part et d'autre).
        (_st getOrDefault ["hallPlan", [0, 0, 1, 1]]) params ["_px", "_py", "_pw", "_ph"];
        private _top = _py + safeZoneH * 0.04;
        private _ah = _ph - safeZoneH * 0.045;
        private _hSpan = 200;
        private _vSpan = _hSpan * ((_ah / pixelH) / (_pw / pixelW));
        private _eye = (getPosASL _pc) vectorAdd [0, 0, 0.6];
        // Face du plan : vers le centre des écrans liés (le mur), sinon le regard de l'opérateur.
        private _fwd = getDir player;
        if ((count _linked) > 0) then {
            private _sx = 0; private _sy = 0;
            { _sx = _sx + ((getPosASL _x) select 0); _sy = _sy + ((getPosASL _x) select 1); } forEach _linked;
            _fwd = _eye getDir [_sx / (count _linked), _sy / (count _linked), 0];
        };
        private _tiles = _st getOrDefault ["hallTiles", []];
        private _used = 0;
        private _sel = _st getOrDefault ["selH", ""];
        private _sideRgb = (createHashMapFromArray [["WEST", [0.16, 0.36, 0.62]], ["EAST", [0.55, 0.18, 0.15]], ["GUER", [0.2, 0.45, 0.25]], ["CIV", [0.4, 0.28, 0.52]]]) getOrDefault [_side, [0.16, 0.36, 0.62]];
        {
            private _o = _x;
            private _isLinked = _o in _linked;
            (boundingBoxReal _o) params ["_p1", "_p2"];
            // Face de l'écran seulement (le pied, la potence ou le support gonflaient le pavé) : largeur de l'objet, hauteur
            // selon le format (16:9, 4:1), en haut de l'objet (en bas pour un écran suspendu).
            private _w = abs ((_p2 select 0) - (_p1 select 0));
            private _hh = (_w * ([9 / 16, 1 / 4] select ((getNumber (configOf _o >> "comspec_screenRatio")) >= 4))) min (abs ((_p2 select 2) - (_p1 select 2)));
            private _zc = if (((typeOf _o) find "Ceiling") >= 0) then { (_p1 select 2) + _hh / 2 } else { (_p2 select 2) - _hh / 2 };
            private _ctr = _o modelToWorldWorld [((_p1 select 0) + (_p2 select 0)) / 2, ((_p1 select 1) + (_p2 select 1)) / 2, _zc];
            private _d = (_eye distance2D _ctr) max 0.5;
            private _rel = (((_eye getDir _ctr) - _fwd) + 540) mod 360 - 180;
            private _el = ((_ctr select 2) - (_eye select 2)) atan2 _d;
            private _aw = 2 * (atan ((_w / 2) / _d));
            private _ah2 = 2 * (atan ((_hh / 2) / _d));
            private _cxp = _px + _pw * (0.5 + (_rel / _hSpan));
            private _cyp = _top + _ah * (0.5 - (_el / _vSpan));
            private _tw = ((_pw * _aw / _hSpan) max 0.035) min (_pw * 0.45);
            private _th = ((_ah * _ah2 / _vSpan) max 0.03) min (_ah * 0.6);
            private _r = [((_cxp - _tw / 2) max _px) min (_px + _pw - _tw), ((_cyp - _th / 2) max _top) min (_top + _ah - _th), _tw, _th];
            // Mur à plusieurs dalles : découpé en dalles (ordre de lecture), chacune choisie à part.
            private _np = getNumber (configOf _o >> "comspec_screenPanels");
            private _cells = switch (true) do {
                case (_np isEqualTo 4): { [[0, 0, 0.5, 0.5], [0.5, 0, 0.5, 0.5], [0, 0.5, 0.5, 0.5], [0.5, 0.5, 0.5, 0.5]] };
                case (_np isEqualTo 6): { [[0, 0, 1/3, 0.5], [1/3, 0, 1/3, 0.5], [2/3, 0, 1/3, 0.5], [0, 0.5, 1/3, 0.5], [1/3, 0.5, 1/3, 0.5], [2/3, 0.5, 1/3, 0.5]] };
                case (_np isEqualTo 3): { [[0, 0, 2/3, 1], [2/3, 0, 1/3, 0.5], [2/3, 0.5, 1/3, 0.5]] };
                default { [[0, 0, 1, 1]] };
            };
            {
                if (_used >= (count _tiles)) exitWith {};
                private _k = _forEachIndex;
                private _po = ["panelOf", [_o, _k]] call comspec_atak_native_fnc_tocScreen;
                if (isNull _po) then { continue };
                _x params ["_fx", "_fy", "_fw", "_fh"];
                private _b = _tiles select _used;
                _b ctrlSetPosition [(_r select 0) + (_r select 2) * _fx + pixelW, (_r select 1) + (_r select 3) * _fy + pixelH, (_r select 2) * _fw - 2 * pixelW, (_r select 3) * _fh - 2 * pixelH];
                _b ctrlCommit 0;
                private _id = netId _po;
                _b setVariable ["COMSPEC_ATAK_Id", _id];
                private _off = _po getVariable ["COMSPEC_ATAK_TocOff", false];
                _b ctrlSetBackgroundColor (switch (true) do {
                    case (_id isEqualTo _sel): { [0.78, 0.55, 0.12, 1] };
                    case (!_isLinked): { [0.16, 0.17, 0.17, 0.85] };
                    case (_off): { [0.08, 0.08, 0.08, 1] };
                    default { _sideRgb + [1] };
                });
                // Libellé court (le contenu) ; nom, contenu et distance dans l'infobulle.
                _b ctrlSetText ((if (_np > 1) then { format ["%1 · %2", _k + 1, [_po] call _contentOf] } else {
                    format ["%1%2", ["○ ", ""] select _isLinked, [_po] call _contentOf]
                }) call comspec_atak_native_fnc_tr);
                // Nom, contenu et distance sur la ligne d'aide du PC au survol (pas d'infobulle sur le plan).
                ["tip", [_b, (format ["%1 · %2 · %3 m", (_po getVariable ["COMSPEC_ATAK_TocScr", []]) param [0, "TOC"], [_po] call _contentOf, round (_pc distance _o)]) call comspec_atak_native_fnc_tr, true]] call comspec_atak_native_fnc_tocLaptop;
                _b ctrlShow true;
                _used = _used + 1;
            } forEach _cells;
        } forEach (_linked + _near);
        for "_i" from _used to ((count _tiles) - 1) do { (_tiles select _i) ctrlShow false; };
        (_ui get "hPlanTitle") ctrlSetStructuredText parseText ((format ["<t size='0.85'>  Mur vu depuis ce poste · %1 lié(s) · %2 proche(s) non lié(s) · cliquez un écran ou une dalle</t>", count _linked, count _near]) call comspec_atak_native_fnc_tr);
        // Écran choisi.
        private _o = call _selObj;
        private _has = !isNull _o;
        (_ui get "hSel") ctrlSetStructuredText parseText ((if (!_has) then { "<t size='0.8' color='#8a9a93'>Aucun écran choisi : cliquez un écran ou une dalle sur le plan.</t>" } else {
            format ["<t size='1.05' font='COMSPEC_BarlowBold'>%1</t>  <t size='0.8' color='#8a9a93'>%2 · %3</t>", [(_o getVariable ["COMSPEC_ATAK_TocScr", []]) param [0, "TOC"]] call _esc, [_o] call _contentOf,
                [["non lié", "lié à la salle"] select ((["of", _o] call comspec_atak_native_fnc_tocHall) isEqualTo _hid && {_hid isNotEqualTo ""}), "lié à une autre salle"] select ((["of", _o] call comspec_atak_native_fnc_tocHall) isNotEqualTo "" && {(["of", _o] call comspec_atak_native_fnc_tocHall) isNotEqualTo _hid})]
        }) call comspec_atak_native_fnc_tr);
        { (_ui get _x) ctrlEnable _has; } forEach ["hShow", "hScrRen", "hPower", "hBlink", "hLink"];
        ["label", ["hPower", ["ÉTEINDRE", "ALLUMER"] select (_has && {_o getVariable ["COMSPEC_ATAK_TocOff", false]})]] call comspec_atak_native_fnc_tocLaptop;
        ["label", ["hLink", ["LIER", "DÉLIER"] select (_has && {(["of", _o] call comspec_atak_native_fnc_tocHall) isEqualTo _hid} && {_hid isNotEqualTo ""})]] call comspec_atak_native_fnc_tocLaptop;
        { (_ui get _x) setVariable ["why", "Choisissez d'abord un écran sur le plan"]; } forEach ["hShow", "hScrRen", "hPower", "hBlink", "hLink"];
        if (_has && {(_ui get "hScrName") getVariable ["COMSPEC_ATAK_Id", ""] isNotEqualTo (netId _o)}) then {
            (_ui get "hScrName") setVariable ["COMSPEC_ATAK_Id", netId _o];
            (_ui get "hScrName") ctrlSetText ((_o getVariable ["COMSPEC_ATAK_TocScr", []]) param [0, ""]); // i18n:brut (nom saisi)
        };
        // Contenus proposés (comme le menu ACE) ; face 4:1 : bandeau ou plaque seulement.
        private _ratio = 0;
        if (_has) then {
            private _hst = _o getVariable ["COMSPEC_ATAK_TocHost", objNull];
            _ratio = getNumber (configOf ([_o, _hst] select (!isNull _hst)) >> "comspec_screenRatio");
        };
        private _opts = if (_ratio >= 4) then { [["Bandeau horloge ZULU / LOCAL", "STRIP"], ["Plaque de nom", "PLATE"]] } else {
            [["Carte et flux", "SPLIT"], ["Carte seule", "MAP"], ["Flux seul", "FEED"], ["Réseau", "NET"], ["ORBAT du site", "ORBAT"], ["PIP du poste", "TOC"],
             ["Vue satellite SAT 1", "SAT1"], ["Vue satellite SAT 2", "SAT2"], ["Horloge et chronos", "CLOCK"], ["Main courante", "LOG"], ["Liste d'exécution", "EXCHECK"],
             ["Pertes et résultats", "KILLS"], ["Demandes des éléments", "REQ"], ["Diapositive du Briefing", "SLIDES"], ["Mosaïque 2x2 · tout le camp", "MOS|2|AUTO"], ["Mosaïque 3x3 · tout le camp", "MOS|3|AUTO"]]
        };
        private _sqs = ["squads", _side] call comspec_atak_native_fnc_tocScreen;
        if (_ratio >= 4) then { { _opts pushBack [format ["Plaque · %1", _x], "SQ|" + _x]; } forEach _sqs; } else {
            { _opts pushBack [format ["Escouade %1", _x], "SQ|" + _x]; _opts pushBack [format ["Mosaïque 2x2 · groupe %1", _x], "MOS|2|G|" + _x]; } forEach _sqs;
            { _opts pushBack [format ["Caméra · %1", _x select 0], "CAM|" + (_x select 1)]; } forEach (["sources"] call comspec_atak_native_fnc_tocCam);
        };
        private _w = _ui get "hWhat";
        if ((_w getVariable ["COMSPEC_ATAK_Sig", []]) isNotEqualTo _opts) then {
            _w setVariable ["COMSPEC_ATAK_Sig", _opts];
            private _keep = _w lbData ((lbCurSel _w) max 0);
            lbClear _w;
            { private _i = _w lbAdd ((_x select 0) call comspec_atak_native_fnc_tr); _w lbSetData [_i, _x select 1]; } forEach _opts;
            _w lbSetCurSel ((_opts findIf { (_x select 1) isEqualTo _keep }) max 0);
        };
        // Mosaïque : réglages montrés seulement si l'écran choisi est en mosaïque.
        private _isMos = _has && {((_o getVariable ["COMSPEC_ATAK_TocScr", []]) param [3, ""]) isEqualTo "MOSAIC"};
        { (_ui get _x) ctrlShow _isMos; } forEach ["hMosRot", "hMosCase", "hMosSrc", "hMosPut"];
        { (_ui get _x) setVariable ["why", "Créez d'abord la salle de ce PC"]; } forEach ["hLinkAll", "hAim", "hSceneGo", "hSceneDel", "hSceneSave", "hAllOn", "hAllOff"];
        if (_isMos) then {
            private _ms = _ui get "hMosSrc";
            private _src = [["Case vide", ""]] + ((["sources"] call comspec_atak_native_fnc_tocCam) apply { [_x select 0, _x select 1] });
            if ((_ms getVariable ["COMSPEC_ATAK_Sig", []]) isNotEqualTo _src) then {
                _ms setVariable ["COMSPEC_ATAK_Sig", _src];
                lbClear _ms;
                { private _i = _ms lbAdd ((_x select 0) call comspec_atak_native_fnc_tr); _ms lbSetData [_i, _x select 1]; } forEach _src;
                _ms lbSetCurSel 0;
            };
        };
        // Scènes de la salle.
        private _scn = (_hall param [3, []]) apply { _x select 0 };
        private _sc = _ui get "hScene";
        if ((_sc getVariable ["COMSPEC_ATAK_Sig", []]) isNotEqualTo _scn) then {
            _sc setVariable ["COMSPEC_ATAK_Sig", _scn];
            lbClear _sc;
            { private _i = _sc lbAdd _x; _sc lbSetData [_i, _x]; } forEach _scn; // i18n:brut (noms saisis)
            if ((count _scn) isEqualTo 0) then { _sc lbAdd (("Aucune scène enregistrée") call comspec_atak_native_fnc_tr); };
            _sc lbSetCurSel 0;
        };
        { (_ui get _x) ctrlEnable (_hid isNotEqualTo ""); } forEach ["hLinkAll", "hAim", "hScene", "hSceneGo", "hSceneDel", "hSceneName", "hSceneSave", "hAllOn", "hAllOff"];
    };
    default { false };
};
