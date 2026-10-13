/*
    Menu contextuel de la carte (clic droit sans glisser) : VOIR LA CAMÉRA (équipier pointé), PING, MARQUEUR, Y ALLER,
    CENTRER ICI, COPIER LA GRILLE, CHAMP DE VISION ICI (fn_reliefAction depuis le point), EFFACER LE CHAMP DE VISION
    et EFFACER LA LIGNE DE VUE (quand il y en a un sur la carte), ZONE INTERDITE AU DRONE (poser, ou retirer celle
    sous le clic), RETIRER TOUTES LES ZONES (camp), SUPPRIMER (marqueur joueur pointé). Équipier pointé : aussi CHAMP DE VISION de l'équipier
    et LOCALISER PAR RELAIS (triangulation, fn_relayNet).
    ["open", [contrôle carte, position écran]]  ouvre le menu au curseur (en-tête : grille, altitude, distance et azimut)
    ["close"]                                    ferme (clic ailleurs, Échap, choix d'une ligne)
    ["act", clé]                                 exécute une ligne
    ["render"]                                   dessine le menu (fn_pageMap, par-dessus la carte, et l'ouverture)
    Le menu a ses propres contrôles (uiNamespace COMSPEC_ATAK_MapCtxCtrls) : l'ouvrir ou le fermer ne redessine pas
    la page (plus de clignotement de toute la carte au clic droit).
    État : COMSPEC_ATAK_State mapCtx = HashMap [pos (monde), marker (nom ou ""), unit (netId de l'équipier pointé ou ""), at (heure)].
*/
params [["_mode", ""], ["_arg", []]];
disableSerialization;
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _wipe = {
    { ctrlDelete _x; } forEach (uiNamespace getVariable ["COMSPEC_ATAK_MapCtxCtrls", []]);
    uiNamespace setVariable ["COMSPEC_ATAK_MapCtxCtrls", []];
};
private _unbindEsc = {
    private _d = [] call comspec_atak_native_fnc_display;
    private _h = uiNamespace getVariable ["COMSPEC_ATAK_MapCtxKey", -1];
    if (_h >= 0 && {!isNull _d}) then { _d displayRemoveEventHandler ["KeyDown", _h]; };
    uiNamespace setVariable ["COMSPEC_ATAK_MapCtxKey", -1];
};
switch (_mode) do {
    case "open": {
        _arg params ["_map", "_screen"];
        if (isNull _map) exitWith {};
        private _w = _map ctrlMapScreenToWorld _screen;
        private _m = [_map, _screen] call comspec_atak_native_fnc_markerAt;
        // Mêmes règles que la suppression (fn_markerDelete) : seuls les marqueurs posés par les joueurs.
        if ((_m find "_USER_DEFINED") isNotEqualTo 0) then { _m = ""; };
        // Équipier pointé (joueur du camp avec un téléphone, à moins d'environ un symbole du clic) : ligne VOIR CAMÉRA.
        private _near = 0.03;
        private _who = objNull;
        {
            private _sp = _map ctrlMapWorldToScreen (getPosASLVisual _x);
            private _dd2 = [(_sp select 0) - (_screen select 0), (_sp select 1) - (_screen select 1)] distance2D [0, 0];
            if (_dd2 < _near) then { _near = _dd2; _who = _x; };
        } forEach (allPlayers select { _x isNotEqualTo player && {alive _x} && {side group _x isEqualTo side group player} && {[_x] call comspec_atak_native_fnc_hasDevice} });
        _s set ["mapCtx", createHashMapFromArray [["pos", [_w select 0, _w select 1, 0]], ["marker", _m], ["unit", [netId _who, ""] select (isNull _who)], ["at", diag_tickTime]]];
        [] call _unbindEsc;
        private _d = [] call comspec_atak_native_fnc_display;
        if (!isNull _d) then {
            uiNamespace setVariable ["COMSPEC_ATAK_MapCtxKey", _d displayAddEventHandler ["KeyDown", {
                params ["", "_key"];
                if (_key isNotEqualTo 0x01) exitWith { _this call comspec_atak_native_fnc_keyBlock };
                ["close"] call comspec_atak_native_fnc_mapCtxMenu;
                true
            }]];
        };
        playSound "ClickSoft";
        // À l'image suivante : la carte vient de prendre le focus (fn_mapUnfocus le lui retire), le menu passe devant.
        [{ ["render"] call comspec_atak_native_fnc_mapCtxMenu; }] call CBA_fnc_execNextFrame;
    };
    case "close": {
        [] call _unbindEsc;
        call _wipe;
        _s set ["mapCtx", createHashMap];
    };
    case "act": {
        private _c = _s getOrDefault ["mapCtx", createHashMap];
        if ((count _c) isEqualTo 0) exitWith {};
        private _pos = _c get "pos";
        private _m = _c getOrDefault ["marker", ""];
        ["close"] call comspec_atak_native_fnc_mapCtxMenu;
        playSound "ClickSoft";
        switch (_arg) do {
            case "PING": { ["send", _pos] call comspec_atak_native_fnc_mapPing; };
            case "MARKER": { ["", _pos] call comspec_atak_native_fnc_markerEditOpen; };
            case "ROUTE": { [_pos, "Point de la carte"] spawn comspec_atak_native_fnc_routeCompute; };
            case "CENTER": { _s set ["mapFollow", false]; [_pos] call comspec_atak_native_fnc_mapCenter; };
            case "COPY": {
                private _g = [_pos, 10] call comspec_atak_native_fnc_gridRef;
                ["clip", _g] call comspec_atak_native_fnc_planAction;
                [format ["Grille %1 copiée", _g], 2] call comspec_atak_native_fnc_toast;
            };
            case "DELETE": { if (_m isNotEqualTo "") then { [_m] call comspec_atak_native_fnc_markerDelete; }; };
            case "NOFLY": { ["add", [_pos, 200]] call comspec_atak_native_fnc_noFly; };
            case "NOFLYDEL": {
                private _z = ["inside", _pos] call comspec_atak_native_fnc_noFly;
                if ((count _z) > 0) then { ["remove", _z select 0] call comspec_atak_native_fnc_noFly; };
            };
            case "NOFLYALL": { ["clear"] call comspec_atak_native_fnc_noFly; };
            case "VIEWDEL": {
                ["clear"] call comspec_atak_native_fnc_reliefAction;
                ["INFO", "Champ de vision effacé", 2, 20] call comspec_atak_native_fnc_notify;
            };
            case "LOSDEL": {
                ["losClear"] call comspec_atak_native_fnc_reliefAction;
                ["INFO", "Ligne de vue effacée", 2, 20] call comspec_atak_native_fnc_notify;
            };
            case "VIEW": { ["compute", [_pos select 0, _pos select 1]] call comspec_atak_native_fnc_reliefAction; };
            case "VIEWU": { ["compute", _c getOrDefault ["unit", ""]] call comspec_atak_native_fnc_reliefAction; };
            case "LOCATE": {
                profileNamespace setVariable ["COMSPEC_ATAK_MeshOnMap", true];
                ["locate", _c getOrDefault ["unit", ""]] call comspec_atak_native_fnc_relayNet;
            };
            case "CAM": {
                private _u = objectFromNetId (_c getOrDefault ["unit", ""]);
                if (isNull _u || {!alive _u}) exitWith {};
                _s set ["mapCam", netId _u];
                ["MAP"] call comspec_atak_native_fnc_pageRender;
            };
        };
    };
    case "render": {
        call _wipe;
        private _c = _s getOrDefault ["mapCtx", createHashMap];
        if ((count _c) isEqualTo 0) exitWith {};
        // Menu oublié (page quittée puis rouverte) : on ne le ressort pas.
        if ((diag_tickTime - (_c getOrDefault ["at", 0])) > 60 || {(_s getOrDefault ["activePage", ""]) isNotEqualTo "MAP"}) exitWith { _s set ["mapCtx", createHashMap]; [] call _unbindEsc; };
        private _d = [] call comspec_atak_native_fnc_display;
        if (isNull _d) exitWith {};
        private _map = _d displayCtrl 88530;
        if (isNull _map) exitWith {};
        private _l = [] call comspec_atak_native_fnc_layoutGet;
        (_l get "body") params ["_bx", "_by", "_bw", "_bh"];
        private _font = _l get "font";
        private _fs = _l get "fontSmall";
        private _ratio = (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]);
        private _pos = _c get "pos";
        private _m = _c getOrDefault ["marker", ""];
        if (_m isNotEqualTo "" && {(markerShape _m) isEqualTo ""}) then { _m = ""; _c set ["marker", ""]; };
        ([] call comspec_atak_native_fnc_accent) params ["_acc"];
        private _dir = "\z\comspec_atak_native\addons\main\data\";
        private _rows = [
            ["PING", "Ping", "signal au groupe", "\A3\ui_f\data\map\markers\military\circle_CA.paa", [1, 0.85, 0.15, 1]],
            ["MARKER", "Mettre un marqueur", "éditeur complet", _dir + "map_marker.paa", _acc],
            ["ROUTE", "Y aller", "itinéraire GPS", _dir + "map_route.paa", [0.40, 0.65, 1, 1]],
            ["CENTER", "Centrer ici", "", _dir + "map_center.paa", [0.86, 0.91, 0.88, 1]],
            ["COPY", "Copier la grille", "10 chiffres", _dir + "map_grid.paa", [0.86, 0.91, 0.88, 1]],
            ["VIEW", "Champ de vision ici", format ["rayon %1 m", _s getOrDefault ["rlRadius", 1000]], _dir + "app_relief.paa", [0.36, 0.78, 0.42, 1]]
        ];
        private _cu = objectFromNetId (_c getOrDefault ["unit", ""]);
        if (!isNull _cu && {alive _cu}) then {
            private _cs = [_cu, true] call comspec_atak_native_fnc_unitCallsign;
            _rows insert [0, [
                ["CAM", "Voir la caméra", _cs, _dir + "app_photos.paa", [0.36, 0.78, 0.42, 1]],
                ["VIEWU", "Son champ de vision", _cs, _dir + "app_relief.paa", [0.36, 0.78, 0.42, 1]],
                ["LOCATE", "Localiser par relais", "triangulation", _dir + "app_waverelay.paa", [1, 0.65, 0.2, 1]]
            ]];
        };
        // Champ de vision et ligne de vue sur la carte (un seul de chaque, propre au joueur) : les effacer d'ici.
        private _vs = missionNamespace getVariable ["COMSPEC_ATAK_Viewshed", []];
        if ((count _vs) > 0) then {
            _rows pushBack ["VIEWDEL", "Effacer le champ de vision", _vs param [13, ""], _dir + "ui_close.paa", [0.95, 0.30, 0.25, 1]];
        };
        if ((count (missionNamespace getVariable ["COMSPEC_ATAK_ReliefLos", []])) > 0) then {
            _rows pushBack ["LOSDEL", "Effacer la ligne de vue", "", _dir + "ui_close.paa", [0.95, 0.30, 0.25, 1]];
        };
        private _nf = count (["list"] call comspec_atak_native_fnc_noFly);
        private _inNf = (count (["inside", _pos] call comspec_atak_native_fnc_noFly)) > 0;
        if (_inNf) then {
            _rows pushBack ["NOFLYDEL", "Retirer la zone interdite", "drone, tout le camp", _dir + "ui_close.paa", [0.95, 0.30, 0.25, 1]];
        } else {
            _rows pushBack ["NOFLY", "Zone interdite au drone", "rayon 200 m", _dir + "app_drone.paa", [0.95, 0.30, 0.25, 1]];
        };
        if (_nf > 1 || {_nf > 0 && {!_inNf}}) then {
            _rows pushBack ["NOFLYALL", "Retirer toutes les zones", format ["%1 zones, tout le camp", _nf], _dir + "ui_close.paa", [0.95, 0.30, 0.25, 1]];
        };
        if (_m isNotEqualTo "") then { _rows pushBack ["DELETE", "Supprimer le marqueur", [markerText _m, ""] select ((markerText _m) isEqualTo ""), _dir + "ui_close.paa", [0.95, 0.30, 0.25, 1]]; };
        private _rowH = _font * 1.55;
        private _headH = _font * 2.05;
        private _w = ((_font * 15) / _ratio) min (_bw * 0.9); // place pour le raccourci à droite des libellés longs
        private _h = _headH + _rowH * (count _rows) + pixelH * 8;
        private _sp = _map ctrlMapWorldToScreen _pos;
        // Ouvert à droite et sous le curseur, recalé dans la carte s'il déborde (à gauche / au-dessus du point).
        private _x0 = (_sp select 0) + pixelW * 6;
        if (_x0 + _w > _bx + _bw - pixelW * 4) then { _x0 = (_sp select 0) - _w - pixelW * 6; };
        private _y0 = (_sp select 1) + pixelH * 6;
        if (_y0 + _h > _by + _bh - pixelH * 4) then { _y0 = (_sp select 1) - _h - pixelH * 6; };
        _x0 = (_x0 max (_bx + pixelW * 4)) min (_bx + _bw - _w - pixelW * 4);
        _y0 = (_y0 max (_by + pixelH * 4)) min (_by + _bh - _h - pixelH * 4);
        private _list = [];
        private _mk = {
            params ["_class", "_p", ["_text", ""]];
            private _ctl = _d ctrlCreate [_class, -1];
            _ctl ctrlSetPosition _p;
            if (_text isNotEqualTo "") then { _ctl ctrlSetText ((_text) call comspec_atak_native_fnc_tr); };
            _ctl ctrlCommit 0;
            _list pushBack _ctl;
            _ctl
        };
        // Repère du point cliqué (anneau à l'accent), ombre douce, carte sombre bordée, liseré d'accent en haut.
        private _ring = ["COMSPEC_RscIcon", [(_sp select 0) - _font * 0.45 / _ratio, (_sp select 1) - _font * 0.45, _font * 0.9 / _ratio, _font * 0.9], "\A3\ui_f\data\map\markers\military\circle_CA.paa"] call _mk;
        _ring ctrlSetTextColor _acc;
        { private _o = ["COMSPEC_RscText", [_x0 + pixelW * _x, _y0 + pixelH * _x, _w, _h]] call _mk; _o ctrlSetBackgroundColor [0, 0, 0, 0.22]; } forEach [2, 4];
        private _border = ["COMSPEC_RscText", [_x0 - pixelW, _y0 - pixelH, _w + pixelW * 2, _h + pixelH * 2]] call _mk;
        _border ctrlSetBackgroundColor [0.30, 0.36, 0.32, 0.9];
        private _bg = ["COMSPEC_RscText", [_x0, _y0, _w, _h]] call _mk;
        _bg ctrlSetBackgroundColor [0.035, 0.042, 0.038, 0.98];
        private _edge = ["COMSPEC_RscText", [_x0, _y0, _w, pixelH * 3]] call _mk;
        _edge ctrlSetBackgroundColor _acc;
        // En-tête : grille (police à chasse fixe), altitude, distance et azimut depuis moi.
        private _me = getPosATL player;
        private _dist = _me distance2D _pos;
        private _az = round (_me getDir _pos);
        private _hd = ["COMSPEC_RscStructuredText", [_x0 + pixelW * 8, _y0 + pixelH * 5, _w - pixelW * 16, _headH]] call _mk;
        _hd ctrlSetStructuredText parseText ((format ["<t font='EtelkaMonospaceProBold' size='0.95' color='#e6efe9'>%1</t><br/><t size='0.78' color='#8a9a93'>%2 m · %3 · %4°</t>",
            [_pos, 8] call comspec_atak_native_fnc_gridRef, round (getTerrainHeightASL _pos),
            [format ["%1 m", round _dist], format ["%1 km", (_dist / 1000) toFixed 1]] select (_dist >= 1000), _az]) call comspec_atak_native_fnc_tr);
        _hd ctrlSetFontHeight (_fs * 0.95);
        if (_m isNotEqualTo "") then { _hd ctrlSetTooltip ((format ["Marqueur pointé : %1", [markerText _m, _m] select ((markerText _m) isEqualTo "")]) call comspec_atak_native_fnc_tr); };
        private _ry = _y0 + pixelH * 6 + _headH;
        private _ih = _rowH * 0.56;
        private _iw = _ih / _ratio;
        {
            _x params ["_key", "_label", "_sub", "_icon", "_col"];
            private _div = ["COMSPEC_RscText", [_x0 + pixelW * 8, _ry, _w - pixelW * 16, pixelH]] call _mk;
            _div ctrlSetBackgroundColor [1, 1, 1, 0.07];
            // Pastille teintée sous l'icône.
            private _chip = ["COMSPEC_RscText", [_x0 + pixelW * 8, _ry + (_rowH - _ih * 1.45) / 2, _iw * 1.45, _ih * 1.45]] call _mk;
            _chip ctrlSetBackgroundColor [_col select 0, _col select 1, _col select 2, 0.16];
            private _ic = ["COMSPEC_RscIcon", [_x0 + pixelW * 8 + _iw * 0.225, _ry + (_rowH - _ih) / 2, _iw, _ih], _icon] call _mk;
            _ic ctrlSetTextColor _col;
            private _t = ["COMSPEC_RscStructuredText", [_x0 + pixelW * 16 + _iw * 1.45, _ry + (_rowH - _font * 0.95) / 2, _w - _iw * 1.45 - pixelW * 24, _rowH]] call _mk;
            _t ctrlSetStructuredText parseText ((format ["<t font='RobotoCondensedBold' size='0.95' color='%1'>%2</t><t size='0.75' color='#7d8c85' align='right'>%3</t>",
                ["#e6efe9", "#f2685c"] select (_key in ["DELETE", "VIEWDEL", "LOSDEL", "NOFLYALL"]), _label, (_sub regexReplace ["[<>&]", ""]) select [0, 22]]) call comspec_atak_native_fnc_tr);
            _t ctrlSetFontHeight _font;
            private _b = ["COMSPEC_RscButtonOverlay", [_x0, _ry, _w, _rowH]] call _mk;
            _b ctrlAddEventHandler ["ButtonClick", compile format ["['act', '%1'] call comspec_atak_native_fnc_mapCtxMenu;", _key]];
            _ry = _ry + _rowH;
        } forEach _rows;
        // Suivis aussi par la page : un rendu complet (changement d'app) les efface avec le reste.
        private _pc = uiNamespace getVariable ["COMSPEC_ATAK_PageControls", []];
        _pc append _list;
        uiNamespace setVariable ["COMSPEC_ATAK_PageControls", _pc];
        uiNamespace setVariable ["COMSPEC_ATAK_MapCtxCtrls", _list];
    };
};
true
