/*
    Carte de l'app Zones (vue EDIT, fn_pageZones) : nord en haut, elle ne suit pas le joueur.
    Params : [événement, arguments de l'événement de la carte]
      "draw" [carte]               plans (fn_zoneDraw), plan modifié au premier plan, zone de travail en cours de tracé
      "move" [carte, x, y]         curseur
      "down" [carte, bouton, x, y] outil de zone de travail (uiNamespace COMSPEC_ATAK_ZnUi « tool ») :
                                     RECT   deux coins ; CIRCLE centre puis bord ; POLY un clic par sommet, clic sur le
                                     premier sommet ou clic droit pour fermer (3 sommets au moins) ;
                                   sans outil : clic sur une zone, la choisir (encore : plus de zone choisie).
                                   Clic droit : ferme le polygone, sinon quitte l'outil / plus de zone choisie.
    Vue : même calage que les apps Plan et Routes (la vue n'est enregistrée qu'une fois la carte calée sur son but).
*/
params [["_ev", ""], ["_a", []]];
private _ui = uiNamespace getVariable ["COMSPEC_ATAK_ZnUi", createHashMap];
private _ZA = comspec_atak_native_fnc_zoneAction;
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
        [_map, _k, _k max 0.95, _ui getOrDefault ["id", ""], _ui getOrDefault ["zid", ""]] call comspec_atak_native_fnc_zoneDraw;
        // Zone de travail en cours de tracé.
        private _tool = _ui getOrDefault ["tool", ""];
        if (_tool isEqualTo "") exitWith {};
        private _pts = _ui getOrDefault ["draftPts", []];
        private _cur = _ui getOrDefault ["cursor", []];
        private _mpx = ["mpx", _map] call comspec_atak_native_fnc_planGfx;
        private _w = [1, 1, 1, 0.9];
        private _c3 = if ((count _cur) >= 2) then { [_cur select 0, _cur select 1, 0] } else { [] };
        { _map drawIcon ["\A3\ui_f\data\map\markers\military\dot_CA.paa", _w, [_x select 0, _x select 1, 0], 10, 10, 0, "", 0]; } forEach _pts;
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
                ["line", _map, [_l, _w, 2, true, false, false, _mpx]] call comspec_atak_native_fnc_planGfx;
                if ((count _pts) >= 2) then { _map drawLine [_c3, _p0, [1, 1, 1, 0.4]]; };
                _map drawIcon ["\A3\ui_f\data\map\markers\military\circle_CA.paa", [1, 1, 1, 0.8], _p0, 22, 22, 0, "", 0];
            };
        };
    };
    case "move": {
        _a params ["_map", "_mx", "_my"];
        _ui set ["cursor", _map ctrlMapScreenToWorld [_mx, _my]];
    };
    case "down": {
        _a params ["_map", "_btn", "_mx", "_my"];
        private _id = _ui getOrDefault ["id", ""];
        private _b = ["get", _id] call _ZA;
        if ((count _b) isEqualTo 0) exitWith {};
        private _tool = _ui getOrDefault ["tool", ""];
        private _pts = _ui getOrDefault ["draftPts", []];
        private _w = _map ctrlMapScreenToWorld [_mx, _my];
        private _close = {
            ["areaSet", [_id, "POLY", _pts apply { [_x select 0, _x select 1] }]] call _ZA;
            _ui set ["tool", ""]; _ui set ["draftPts", []];
            ["INFO", "Zone de travail posée : choisissez le découpage, puis GÉNÉRER", 4, 10] call comspec_atak_native_fnc_notify;
        };
        if (_btn isEqualTo 1) exitWith {
            if (_tool isEqualTo "POLY" && {(count _pts) >= 3}) then { call _close; } else { _ui set ["tool", ""]; _ui set ["draftPts", []]; _ui set ["zid", ""]; };
            ["#refresh"] call comspec_atak_native_fnc_pageZones;
        };
        if (_btn isNotEqualTo 0) exitWith {};
        if (_tool isNotEqualTo "") exitWith {
            if !(["isMine", _id] call _ZA) exitWith {};
            private _p = [_w select 0, _w select 1];
            switch (_tool) do {
                case "RECT": {
                    if ((count _pts) isEqualTo 0) then { _ui set ["draftPts", [_p]]; } else {
                        private _q = _pts select 0;
                        if (((abs ((_q select 0) - (_p select 0))) < 20) || {(abs ((_q select 1) - (_p select 1))) < 20}) exitWith { ["INFO", "Rectangle trop étroit", 2, 10] call comspec_atak_native_fnc_notify; };
                        ["areaSet", [_id, "RECT", [_q, _p]]] call _ZA;
                        _ui set ["tool", ""]; _ui set ["draftPts", []];
                        ["INFO", "Zone de travail posée : choisissez le découpage, puis GÉNÉRER", 4, 10] call comspec_atak_native_fnc_notify;
                    };
                };
                case "CIRCLE": {
                    if ((count _pts) isEqualTo 0) then { _ui set ["draftPts", [_p]]; } else {
                        private _r = round ((_pts select 0) distance2D _p);
                        if (_r < 20) exitWith {};
                        ["areaSet", [_id, "CIRCLE", [_pts select 0, _r min 5000]]] call _ZA;
                        _ui set ["tool", ""]; _ui set ["draftPts", []];
                        ["INFO", "Zone de travail posée : choisissez le découpage, puis GÉNÉRER", 4, 10] call comspec_atak_native_fnc_notify;
                    };
                };
                default {
                    // Clic sur le premier sommet (14 pixels) : polygone fermé.
                    if ((count _pts) >= 3 && {((_map ctrlMapWorldToScreen (_pts select 0)) distance2D [_mx, _my]) < (pixelW * 14)}) exitWith { call _close; };
                    if ((count _pts) >= 60) exitWith { ["INFO", "60 sommets au plus", 2, 10] call comspec_atak_native_fnc_notify; };
                    _pts pushBack _p;
                    _ui set ["draftPts", _pts];
                };
            };
            playSound "ClickSoft";
            ["#refresh"] call comspec_atak_native_fnc_pageZones;
        };
        private _z = ["zoneIn", [_b, _w]] call _ZA;
        if ((count _z) isEqualTo 0) exitWith {};
        _ui set ["zid", [_z select 0, ""] select ((_z select 0) isEqualTo (_ui getOrDefault ["zid", ""]))];
        playSound "ClickSoft";
        ["#refresh"] call comspec_atak_native_fnc_pageZones;
    };
};
