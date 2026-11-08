/*
    Carte de l'app Zones (vue EDIT, fn_pageZones) : nord en haut, elle ne suit pas le joueur.
    Params : [événement, arguments de l'événement de la carte]
      "draw" [carte]               plans (fn_zoneDraw), plan modifié au premier plan, aperçu du découpage (traits fins),
                                   tracé en cours et point d'accroche de l'aimant
      "move" [carte, x, y]         curseur ; outil POLYGONE aimanté : accroche cherchée ~10 fois par seconde au plus
                                   (fn_zoneSnap, voisinage du curseur, cache), ancrage automatique à intervalle (2,5 rayons)
      "down" [carte, bouton, x, y, maj, ctrl, alt] outil (uiNamespace COMSPEC_ATAK_ZnUi « tool ») :
                                     RECT   deux coins (deux clics, ou clic-glisser : « up ») ; CIRCLE centre puis bord
                                     (idem) ; POLY un clic par sommet (aimanté sauf
                                     Alt), clic sur le premier sommet (fermé), Entrée ou clic droit pour terminer ;
                                   sans outil : trait choisi (sommet : le choisir puis cliquer sa nouvelle place ; rond au
                                   milieu d'un segment : ajouter un sommet), autre trait, zone (fiche dans le panneau).
                                   Clic droit sans outil : menu (zone, trait, tracer ici, découper par mes traits, annuler).
      "up" [carte, bouton, x, y]    relâchement : rectangle ou cercle glissé de plus de 10 pixels terminé au relâchement
                                   (écart mesuré à l'écran : juste aussi si la carte a bougé pendant le glisser)
      "menu" [clé]                 ligne du menu choisie ; "menuClose"
    Vue : même calage que les apps Plan et Routes (la vue n'est enregistrée qu'une fois la carte calée sur son but).
*/
params [["_ev", ""], ["_a", []]];
disableSerialization;
private _ui = uiNamespace getVariable ["COMSPEC_ATAK_ZnUi", createHashMap];
private _ZA = comspec_atak_native_fnc_zoneAction;
private _tr = { _this call comspec_atak_native_fnc_tr };
private _refresh = { ["#refresh"] call comspec_atak_native_fnc_pageZones; };
// Rectangle ou cercle terminé (second clic, ou relâchement d'un clic-glisser) : zone de travail du plan.
private _shapeEnd = {
    params ["_id", "_tool", "_p"];
    private _pts = _ui getOrDefault ["draftPts", []];
    if ((count _pts) isEqualTo 0) exitWith { false };
    private _q = _pts select 0;
    private _ok = if (_tool isEqualTo "RECT") then {
        if (((abs ((_q select 0) - (_p select 0))) < 20) || {(abs ((_q select 1) - (_p select 1))) < 20}) then {
            ["INFO", "Rectangle trop étroit", 2, 10] call comspec_atak_native_fnc_notify;
            false
        } else { ["areaSet", [_id, "RECT", [_q, _p]]] call _ZA; true }
    } else {
        private _r = round (_q distance2D _p);
        if (_r < 20) then { false } else { ["areaSet", [_id, "CIRCLE", [_q, _r min 5000]]] call _ZA; true }
    };
    if (!_ok) exitWith { false };
    _ui set ["tool", ""]; _ui set ["draftPts", []]; _ui set ["dragAt", []];
    // Suite proposée sur la consigne de la carte : DÉCOUPER ou TRACER (fn_pageZones « afterArea »).
    _ui set ["next", "AREA"];
    true
};
private _menuWipe = {
    { ctrlDelete _x; } forEach (uiNamespace getVariable ["COMSPEC_ATAK_ZnMenu", []]);
    uiNamespace setVariable ["COMSPEC_ATAK_ZnMenu", []];
};
switch (_ev) do {
    case "draw": {
        _a params ["_map"];
        private _cp = ctrlPosition _map;
        private _mid = [(_cp select 0) + (_cp select 2) / 2, (_cp select 1) + (_cp select 3) / 2];
        if (_map getVariable ["comspec_znReady", false]) then {
            uiNamespace setVariable ["COMSPEC_ATAK_ZnView", [ctrlMapScale _map, _map ctrlMapScreenToWorld _mid]];
        } else {
            private _aim = _map getVariable ["comspec_znAim", []];
            if ((count _aim) < 2) exitWith { _map setVariable ["comspec_znReady", true]; };
            if ((diag_tickTime - (_map getVariable ["comspec_znBorn", 0])) < 0.15 || {!ctrlMapAnimDone _map}) exitWith {};
            private _tries = _map getVariable ["comspec_znFix", 0];
            private _sp = _map ctrlMapWorldToScreen [(_aim select 1) select 0, (_aim select 1) select 1];
            if ((_sp distance2D _mid) < 0.004 || {_tries >= 3}) exitWith { _map setVariable ["comspec_znReady", true]; };
            _map setVariable ["comspec_znFix", _tries + 1];
            private _last = _map getVariable ["comspec_znLast", _aim select 1];
            private _st = _map ctrlMapWorldToScreen [_last select 0, _last select 1];
            private _fix = _map ctrlMapScreenToWorld [(_st select 0) + (_sp select 0) - (_mid select 0), (_st select 1) + (_sp select 1) - (_mid select 1)];
            _map setVariable ["comspec_znLast", _fix];
            _map ctrlMapAnimAdd [0, _aim select 0, [_fix select 0, _fix select 1, 0]];
            ctrlMapAnimCommit _map;
        };
        private _me = getPosATL vehicle player;
        _map drawIcon ["\A3\ui_f\data\map\vehicleicons\iconMan_ca.paa", [0.36, 0.85, 0.42, 1], _me, 22, 22, getDirVisual (vehicle player), "", 0];
        private _k = ((((_cp select 3) / (safeZoneH * 0.7)) max 0.6) min 1.2);
        private _id = _ui getOrDefault ["id", ""];
        [_map, _k, _k max 0.95, _id, _ui getOrDefault ["zid", ""], _ui getOrDefault ["lid", ""]] call comspec_atak_native_fnc_zoneDraw;
        private _b = ["get", _id] call _ZA;
        private _mpx = ["mpx", _map] call comspec_atak_native_fnc_planGfx;
        // Aperçu du découpage (avant GÉNÉRER, onglet DÉCOUPER ou plan sans zones) : contours fins, gardés (fn_zoneAction "preview").
        if ((count _b) > 0 && {(count (_b getOrDefault ["area", []])) > 0} && {(_ui getOrDefault ["ztab", ""]) isEqualTo "CUT" || {(count (_b get "zones")) isEqualTo 0}}) then {
            private _pc = [1, 0.85, 0.2, 0.75];
            {
                if ((count _x) isEqualTo 1) then {
                    (_x select 0) params ["_cx", "_cy", "_h"];
                    _map drawRectangle [[_cx, _cy, 0], _h, _h, 0, _pc, ""];
                } else { _map drawPolygon [_x apply { [_x select 0, _x select 1, 0] }, _pc]; };
            } forEach ((["preview", _b] call _ZA) select [0, 600]);
        };
        // Sommet choisi d'un trait : cerclé.
        private _vtx = _ui getOrDefault ["vtx", -1];
        if (_vtx >= 0) then {
            private _l = ((_b getOrDefault ["lines", []]) select { (_x select 0) isEqualTo (_ui getOrDefault ["lid", ""]) }) param [0, []];
            private _p = (_l param [2, []]) param [_vtx, []];
            if ((count _p) >= 2) then { _map drawIcon ["\A3\ui_f\data\map\markers\military\circle_CA.paa", [1, 0.85, 0.2, 1], [_p select 0, _p select 1, 0], 20, 20, 0, "", 0]; };
        };
        // Tracé en cours.
        private _tool = _ui getOrDefault ["tool", ""];
        if (_tool isEqualTo "") exitWith {};
        private _pts = _ui getOrDefault ["draftPts", []];
        private _cur = _ui getOrDefault ["cursor", []];
        private _snap = _ui getOrDefault ["snap", []];
        if (_tool isEqualTo "POLY" && {(count _snap) > 0} && {!(_ui getOrDefault ["alt", false])}) then { _cur = _snap select 0; };
        private _w = [1, 1, 1, 0.9];
        private _c3 = if ((count _cur) >= 2) then { [_cur select 0, _cur select 1, 0] } else { [] };
        { _map drawIcon ["\A3\ui_f\data\map\markers\military\dot_CA.paa", _w, [_x select 0, _x select 1, 0], 10, 10, 0, "", 0]; } forEach _pts;
        // Accroche de l'aimant : losange jaune et genre (coin, bord, route, rive).
        if (_tool isEqualTo "POLY" && {(count _snap) > 0} && {!(_ui getOrDefault ["alt", false])}) then {
            _map drawIcon ["\A3\ui_f\data\map\markers\military\box_CA.paa", [1, 0.85, 0.2, 1], [(_snap select 0) select 0, (_snap select 0) select 1, 0], 14, 14, 45, "", 0];
        };
        if ((count _pts) isEqualTo 0 || {(count _c3) isEqualTo 0}) exitWith {};
        private _p0 = [(_pts select 0) select 0, (_pts select 0) select 1, 0];
        switch (_tool) do {
            case "RECT": {
                _map drawRectangle [[((_p0 select 0) + (_c3 select 0)) / 2, ((_p0 select 1) + (_c3 select 1)) / 2, 0], abs ((_c3 select 0) - (_p0 select 0)) / 2, abs ((_c3 select 1) - (_p0 select 1)) / 2, 0, _w, ""];
                _map drawIcon ["#(argb,8,8,3)color(0,0,0,0)", _w, _c3, 0, 0, 0, format ["  %1 x %2 m", round (abs ((_c3 select 0) - (_p0 select 0))), round (abs ((_c3 select 1) - (_p0 select 1)))], 2, 0.024, COMSPEC_ATAK_FontSet select 1, "left"];
            };
            case "CIRCLE": {
                private _r = _p0 distance2D _c3;
                _map drawEllipse [_p0, _r, _r, 0, _w, ""];
                _map drawIcon ["#(argb,8,8,3)color(0,0,0,0)", _w, _c3, 0, 0, 0, format ["  R %1 m", round _r], 2, 0.024, COMSPEC_ATAK_FontSet select 1, "left"];
            };
            default {
                private _l = (_pts apply { [_x select 0, _x select 1, 0] }) + [_c3];
                ["line", _map, [_l, _w, 2, false, false, false, _mpx]] call comspec_atak_native_fnc_planGfx;
                if ((count _pts) >= 2 && {!(_ui getOrDefault ["polyOpen", false])}) then { _map drawLine [_c3, _p0, [1, 1, 1, 0.4]]; };
                _map drawIcon ["\A3\ui_f\data\map\markers\military\circle_CA.paa", [1, 1, 1, 0.8], _p0, 22, 22, 0, "", 0];
            };
        };
    };
    case "move": {
        _a params ["_map", "_mx", "_my"];
        private _w = _map ctrlMapScreenToWorld [_mx, _my];
        // Clic-glisser d'un rectangle ou d'un cercle : point courant = premier point + écart à l'écran depuis l'appui.
        private _da = _ui getOrDefault ["dragAt", []];
        private _dp = _ui getOrDefault ["draftPts", []];
        if ((count _da) isEqualTo 2 && {(count _dp) isEqualTo 1} && {(_ui getOrDefault ["tool", ""]) in ["RECT", "CIRCLE"]}) then {
            private _w0 = _map ctrlMapScreenToWorld _da;
            private _p0 = _dp select 0;
            _w = [(_p0 select 0) + (_w select 0) - (_w0 select 0), (_p0 select 1) + (_w select 1) - (_w0 select 1)];
        };
        _ui set ["cursor", _w];
        // Aimant : seulement outil POLYGONE aimanté, sans Alt, ~10 fois par seconde et si le curseur a bougé.
        if ((_ui getOrDefault ["tool", ""]) isNotEqualTo "POLY" || {!(_ui getOrDefault ["polyMag", true])} || {_ui getOrDefault ["alt", false]}) exitWith { _ui set ["snap", []]; };
        private _t = diag_tickTime;
        if ((_t - (_ui getOrDefault ["snapAt", 0])) < 0.1) exitWith {};
        private _lastQ = _ui getOrDefault ["snapQ", [-1e6, -1e6]];
        private _R = _ui getOrDefault ["polyR", 30];
        if ((_w distance2D _lastQ) < (_R * 0.08)) exitWith {};
        _ui set ["snapAt", _t];
        _ui set ["snapQ", _w];
        private _s = ["at", [_w, _R]] call comspec_atak_native_fnc_zoneSnap;
        _ui set ["snap", _s];
        // Ancrage automatique (lasso magnétique) : sur une accroche, à 2,5 rayons du dernier sommet.
        private _pts = _ui getOrDefault ["draftPts", []];
        if ((count _s) > 0 && {(count _pts) > 0} && {(count _pts) < 300} && {((_s select 0) distance2D (_pts select -1)) >= ((_R * 2.5) max 30)}) then {
            _pts pushBack (_s select 0);
            _ui set ["draftPts", _pts];
            // Consigne (nombre de points) : panneau refait au plus deux fois par seconde.
            if ((_t - (_ui getOrDefault ["autoRef", 0])) > 0.5) then { _ui set ["autoRef", _t]; ["#refresh"] call comspec_atak_native_fnc_pageZones; };
        };
    };
    case "up": {
        _a params ["_map", "_btn", "_mx", "_my"];
        private _da = _ui getOrDefault ["dragAt", []];
        _ui set ["dragAt", []];
        if (_btn isNotEqualTo 0 || {(count _da) < 2}) exitWith {};
        private _tool = _ui getOrDefault ["tool", ""];
        private _pts = _ui getOrDefault ["draftPts", []];
        if (!(_tool in ["RECT", "CIRCLE"]) || {(count _pts) isNotEqualTo 1}) exitWith {};
        // Moins de 10 pixels : simple clic, le second clic posera le coin opposé ou le bord.
        if ((((abs (_mx - (_da select 0))) / pixelW) + ((abs (_my - (_da select 1))) / pixelH)) < 10) exitWith {};
        private _w1 = _map ctrlMapScreenToWorld [_mx, _my];
        private _w0 = _map ctrlMapScreenToWorld _da;
        private _p0 = _pts select 0;
        if ([_ui getOrDefault ["id", ""], _tool, [(_p0 select 0) + (_w1 select 0) - (_w0 select 0), (_p0 select 1) + (_w1 select 1) - (_w0 select 1)]] call _shapeEnd) then {
            playSound "ClickSoft";
            ["INFO", "ZONES", format ["Zone de travail tracée en clic-glisser (%1)", _tool]] call comspec_atak_native_fnc_log;
        };
        call _refresh;
    };
    case "down": {
        _a params ["_map", "_btn", "_mx", "_my", ["_shift", false], ["_ctrl", false], ["_alt", false]];
        call _menuWipe;
        _ui set ["dragAt", []];
        // Clic sur la carte sans outil : le choix DÉCOUPER / TRACER proposé sur la consigne est retiré.
        if ((_ui getOrDefault ["tool", ""]) isEqualTo "") then { _ui set ["next", ""]; };
        private _id = _ui getOrDefault ["id", ""];
        private _b = ["get", _id] call _ZA;
        if ((count _b) isEqualTo 0) exitWith {};
        private _mine = ["isMine", _id] call _ZA;
        private _tool = _ui getOrDefault ["tool", ""];
        private _pts = _ui getOrDefault ["draftPts", []];
        private _w = _map ctrlMapScreenToWorld [_mx, _my];
        if (_btn isEqualTo 1) exitWith {
            // Clic droit : termine le polygone (ou quitte l'outil) ; sans outil, menu.
            if (_tool isEqualTo "POLY" && {(count _pts) >= ([3, 2] select (_ui getOrDefault ["polyOpen", false]))}) exitWith { ["#ui", ["polyEnd"]] call comspec_atak_native_fnc_pageZones; };
            if (_tool isNotEqualTo "") exitWith { ["#ui", ["drawCancel"]] call comspec_atak_native_fnc_pageZones; };
            ["menu", [_map, [_mx, _my], _w]] call comspec_atak_native_fnc_zoneMap;
        };
        if (_btn isNotEqualTo 0) exitWith {};
        if (_tool isNotEqualTo "") exitWith {
            if (!_mine) exitWith {};
            private _p = [_w select 0, _w select 1];
            switch (_tool) do {
                case "RECT";
                case "CIRCLE": {
                    // Premier point : gardé avec sa position à l'écran (clic-glisser terminé au relâchement, « up »).
                    if ((count _pts) isEqualTo 0) then { _ui set ["draftPts", [_p]]; _ui set ["dragAt", [_mx, _my]]; } else { [_id, _tool, _p] call _shapeEnd; };
                };
                default {
                    // Clic sur le premier sommet (14 pixels) : polygone fermé.
                    if ((count _pts) >= 3 && {!(_ui getOrDefault ["polyOpen", false])} && {((_map ctrlMapWorldToScreen (_pts select 0)) distance2D [_mx, _my]) < (pixelW * 14)}) exitWith { ["#ui", ["polyEnd"]] call comspec_atak_native_fnc_pageZones; };
                    if ((count _pts) >= 300) exitWith { ["INFO", "300 sommets au plus", 2, 10] call comspec_atak_native_fnc_notify; };
                    // Aimant (sauf Alt) : point d'accroche sous le clic.
                    if ((_ui getOrDefault ["polyMag", true]) && {!_alt} && {!(_ui getOrDefault ["alt", false])}) then {
                        private _s = ["at", [_p, _ui getOrDefault ["polyR", 30]]] call comspec_atak_native_fnc_zoneSnap;
                        if ((count _s) > 0) then { _p = _s select 0; };
                    };
                    _pts pushBack [_p select 0, _p select 1];
                    _ui set ["draftPts", _pts];
                };
            };
            playSound "ClickSoft";
            call _refresh;
        };
        // Sélection : trait choisi (sommets, milieux), puis autres traits, puis zone.
        private _lid = _ui getOrDefault ["lid", ""];
        private _vtx = _ui getOrDefault ["vtx", -1];
        if (_mine && {_lid isNotEqualTo ""} && {_vtx >= 0}) exitWith {
            private _hit = ["lineAt", [_b, _map, [_mx, _my], 10]] call _ZA;
            // Clic sur un autre sommet du même trait : il devient le sommet choisi ; ailleurs : le sommet y va.
            if ((count _hit) > 0 && {(_hit select 0) isEqualTo _lid} && {(_hit select 1) >= 0}) exitWith { _ui set ["vtx", _hit select 1]; playSound "ClickSoft"; call _refresh; };
            ["lineSet", [_id, _lid, "vmove", [_w select 0, _w select 1], _vtx]] call _ZA;
            _ui set ["vtx", -1];
            playSound "ClickSoft";
            call _refresh;
        };
        private _hit = ["lineAt", [_b, _map, [_mx, _my], 10]] call _ZA;
        if ((count _hit) > 0) exitWith {
            _hit params ["_hl", "_hv", "_hm"];
            if (_mine && {_hl isEqualTo _lid} && {_hm >= 0}) then {
                // Rond au milieu d'un segment : nouveau sommet, choisi pour être déplacé.
                ["lineSet", [_id, _hl, "vins", [_w select 0, _w select 1], _hm + 1]] call _ZA;
                _ui set ["vtx", _hm + 1];
            } else {
                _ui set ["vtx", [-1, _hv] select (_mine && {_hl isEqualTo _lid})];
                _ui set ["lid", _hl];
                _ui set ["zid", ""];
            };
            playSound "ClickSoft";
            call _refresh;
        };
        private _z = ["zoneIn", [_b, _w]] call _ZA;
        if ((count _z) isEqualTo 0) exitWith {
            if ((_ui getOrDefault ["lid", ""]) isNotEqualTo "" || {(_ui getOrDefault ["zid", ""]) isNotEqualTo ""}) then { _ui set ["lid", ""]; _ui set ["vtx", -1]; _ui set ["zid", ""]; call _refresh; };
        };
        _ui set ["lid", ""]; _ui set ["vtx", -1];
        // Zone : choisie (encore : plus de zone choisie) ; sa fiche dans le panneau, sauf onglet STYLE (style de la zone).
        if ((_z select 0) isEqualTo (_ui getOrDefault ["zid", ""])) then { _ui set ["zid", ""]; _ui set ["panel", "TAB"]; } else {
            _ui set ["zid", _z select 0];
            if !((_ui getOrDefault ["ztab", ""]) in ["STYLE", "SIT"]) then { _ui set ["panel", "ZONE"]; _ui set ["repPos", [_w select 0, _w select 1]]; };
        };
        playSound "ClickSoft";
        call _refresh;
    };
    // Menu du clic droit de la carte de l'app : contrôles propres (pas de rendu de page), fermé au clic suivant.
    case "menu": {
        _a params ["_map", "_sp", "_w"];
        call _menuWipe;
        private _id = _ui getOrDefault ["id", ""];
        private _b = ["get", _id] call _ZA;
        if ((count _b) isEqualTo 0) exitWith {};
        private _mine = ["isMine", _id] call _ZA;
        private _rows = [];
        private _z = ["zoneIn", [_b, _w]] call _ZA;
        private _hit = ["lineAt", [_b, _map, _sp, 10]] call _ZA;
        _ui set ["menuPos", [_w select 0, _w select 1]];
        _ui set ["menuZone", _z param [0, ""]];
        _ui set ["menuLine", _hit param [0, ""]];
        if ((count _z) > 0) then {
            _rows pushBack ["ZONE", format [("Fiche de la zone %1") call _tr, _z select 1], [0.36, 0.78, 0.42, 1]];
            { _rows pushBack ["ST_" + (_x select 0), format [("État : %1") call _tr, (_x select 1) call _tr], _x select 4]; } forEach ((["states"] call _ZA) select { (_x select 0) isNotEqualTo (_z select 6) });
            if (_mine) then { _rows pushBack ["ZDEL", format [("Supprimer la zone %1") call _tr, _z select 1], [0.95, 0.35, 0.3, 1]]; };
        };
        if ((count _hit) > 0 && {_mine}) then {
            private _ln = (((_b getOrDefault ["lines", []]) select { (_x select 0) isEqualTo (_hit select 0) }) param [0, []]) param [1, ""];
            _rows pushBack ["LSEL", format [("Éditer les sommets de %1") call _tr, _ln], [0.36, 0.78, 0.42, 1]];
            _rows pushBack ["LDEL", format [("Supprimer %1") call _tr, _ln], [0.95, 0.35, 0.3, 1]];
        };
        if (_mine) then {
            _rows pushBack ["MAGC", "Contour aimanté à partir d'ici", [1, 0.85, 0.2, 1]];
            _rows pushBack ["MAGO", "Trait aimanté à partir d'ici", [1, 0.85, 0.2, 1]];
            if ((count (_b getOrDefault ["lines", []])) > 0 && {(count (_b getOrDefault ["area", []])) > 0}) then { _rows pushBack ["CUTL", "Découper par mes traits", [0.36, 0.78, 0.42, 1]]; };
            _rows pushBack ["UNDO", "Annuler (Ctrl+Z)", [0.86, 0.91, 0.88, 1]];
            _rows pushBack ["REDO", "Rétablir (Ctrl+Y)", [0.86, 0.91, 0.88, 1]];
        };
        if ((count _rows) isEqualTo 0) exitWith {};
        private _d = [] call comspec_atak_native_fnc_display;
        if (isNull _d) exitWith {};
        private _l = [] call comspec_atak_native_fnc_layoutGet;
        private _font = _l get "font";
        private _ratio = uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW];
        private _rh = _font * 1.45;
        private _wM = (_font * 13) / _ratio;
        private _hM = _rh * (count _rows) + pixelH * 6;
        (ctrlPosition _map) params ["_cx", "_cy", "_cw", "_ch"];
        private _x0 = ((((_sp select 0) + pixelW * 6) min (_cx + _cw - _wM - pixelW * 4)) max _cx);
        private _y0 = ((((_sp select 1) + pixelH * 6) min (_cy + _ch - _hM - pixelH * 4)) max _cy);
        private _list = [];
        private _bg = _d ctrlCreate ["COMSPEC_RscText", -1];
        _bg ctrlSetPosition [_x0, _y0, _wM, _hM]; _bg ctrlSetBackgroundColor [0.035, 0.042, 0.038, 0.97]; _bg ctrlCommit 0;
        _list pushBack _bg;
        {
            _x params ["_k", "_lbl", "_col"];
            private _t = _d ctrlCreate ["COMSPEC_RscStructuredText", -1];
            _t ctrlSetPosition [_x0 + pixelW * 8, _y0 + pixelH * 3 + _forEachIndex * _rh + (_rh - _font) / 2, _wM - pixelW * 16, _rh];
            _t ctrlSetStructuredText parseText format ["<t font='COMSPEC_BarlowBold' size='0.9' color='%1'>%2</t>", ((_col select [0, 3]) call BIS_fnc_colorRGBtoHTML), ((_lbl) call _tr) regexReplace ["[<>&]", ""]]; // i18n:brut
            _t ctrlCommit 0;
            _list pushBack _t;
            private _o = _d ctrlCreate ["COMSPEC_RscButtonOverlay", -1];
            _o ctrlSetPosition [_x0, _y0 + pixelH * 3 + _forEachIndex * _rh, _wM, _rh]; _o ctrlCommit 0;
            _o ctrlAddEventHandler ["ButtonClick", compile format ["[{ ['pick', %1] call comspec_atak_native_fnc_zoneMap; }] call comspec_atak_native_fnc_nextFrame;", str _k]];
            _list pushBack _o;
        } forEach _rows;
        // Suivis par la page : un rendu complet (changement d'app) les efface aussi.
        private _pc = uiNamespace getVariable ["COMSPEC_ATAK_PageControls", []];
        _pc append _list;
        uiNamespace setVariable ["COMSPEC_ATAK_PageControls", _pc];
        uiNamespace setVariable ["COMSPEC_ATAK_ZnMenu", _list];
        playSound "ClickSoft";
    };
    case "menuClose": { call _menuWipe; };
    case "pick": {
        call _menuWipe;
        private _k = _a;
        private _id = _ui getOrDefault ["id", ""];
        private _p = _ui getOrDefault ["menuPos", []];
        private _zid = _ui getOrDefault ["menuZone", ""];
        private _lid = _ui getOrDefault ["menuLine", ""];
        if ((_k select [0, 3]) isEqualTo "ST_") exitWith { ["#ui", ["state", [_zid, _k select [3]]]] call comspec_atak_native_fnc_pageZones; };
        switch (_k) do {
            case "ZONE": { ["#ui", ["zone", _zid]] call comspec_atak_native_fnc_pageZones; };
            case "ZDEL": { _ui set ["zid", _zid]; _ui set ["lid", ""]; ["#ui", ["delKey"]] call comspec_atak_native_fnc_pageZones; };
            case "LSEL": { _ui set ["lid", _lid]; _ui set ["vtx", -1]; _ui set ["ztab", "DRAW"]; _ui set ["panel", "TAB"]; call _refresh; };
            case "LDEL": { _ui set ["lid", _lid]; _ui set ["vtx", -1]; ["#ui", ["delKey"]] call comspec_atak_native_fnc_pageZones; };
            case "MAGC";
            case "MAGO": {
                _ui set ["tool", "POLY"]; _ui set ["polyMag", true]; _ui set ["polyOpen", _k isEqualTo "MAGO"];
                _ui set ["polyFor", [["LINE", "AREA"] select ((count ((["get", _id] call _ZA) getOrDefault ["area", []])) isEqualTo 0), "LINE"] select (_k isEqualTo "MAGO")];
                private _s = ["at", [_p, _ui getOrDefault ["polyR", 30]]] call comspec_atak_native_fnc_zoneSnap;
                _ui set ["draftPts", [_s param [0, _p]]];
                call _refresh;
            };
            case "CUTL": { ["gp", [_id, "cut", "LINES"]] call _ZA; _ui set ["ztab", "CUT"]; ["#ui", ["gen"]] call comspec_atak_native_fnc_pageZones; };
            case "UNDO": { ["#ui", ["undo"]] call comspec_atak_native_fnc_pageZones; };
            case "REDO": { ["#ui", ["redo"]] call comspec_atak_native_fnc_pageZones; };
        };
    };
};
