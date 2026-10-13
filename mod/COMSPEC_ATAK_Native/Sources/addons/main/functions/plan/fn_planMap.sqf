/*
    Carte de briefing de l'app Plan : carte à part, nord en haut et fixe (elle ne suit ni le joueur ni la caméra ISR),
    donc le tracé reste calé sur le terrain pendant que la nacelle du drone tourne.
    Params : [événement, arguments de l'événement de la carte]
      "draw" [carte]                              fond, plan partagé, brouillon, aperçu, tracé en cours
      "down" / "up" [carte, bouton, x, y]         clic gauche : outil actif (ou sélection) ; clic droit sans glisser : terminer / quitter l'outil
      "move" [carte, x, y]                        curseur et crayon
      "rclick" [carte, [x, y]]                    clic droit simple : terminer le tracé, quitter l'outil ou désélectionner
      "dbl"  [carte, bouton, x, y]                double clic : termine un trait ou une zone
      "hit"  [carte, [x, y]]                      -> [id, couche] de l'élément sous le point ("" sinon)
*/
params [["_ev", ""], ["_a", []]];
private _ui = ["ui"] call comspec_atak_native_fnc_planAction;
private _cat = [] call comspec_atak_native_fnc_planCatalog;
private _def = (_cat get "kinds") getOrDefault [_ui get "tool", []];
private _geom = _def param [3, ""];
switch (_ev) do {
    case "draw": {
        _a params ["_map"];
        private _cp = ctrlPosition _map;
        uiNamespace setVariable ["COMSPEC_ATAK_PlanView", [ctrlMapScale _map, _map ctrlMapScreenToWorld [(_cp select 0) + (_cp select 2) / 2, (_cp select 1) + (_cp select 3) / 2]]];
        // Fond assombri (comme une image ISR) : les couleurs du plan ressortent.
        if (profileNamespace getVariable ["COMSPEC_ATAK_PlanDim", true]) then {
            _map drawRectangle [[worldSize / 2, worldSize / 2, 0], worldSize * 1.5, worldSize * 1.5, 0, [0.02, 0.025, 0.03, 0.42], "#(rgb,8,8,3)color(1,1,1,1)"];
        };
        private _sel = _ui get "sel";
        private _selL = _ui get "selLayer";
        [_map, ["shared"] call comspec_atak_native_fnc_planAction, 1, [_sel, ""] select (_selL isNotEqualTo "S")] call comspec_atak_native_fnc_planDraw;
        [_map, ["draft"] call comspec_atak_native_fnc_planAction, 0.8, [_sel, ""] select (_selL isNotEqualTo "D")] call comspec_atak_native_fnc_planDraw;
        // Envoyés au plan partagé, en attente de la réponse du serveur : ils restent visibles.
        [_map, ["pending"] call comspec_atak_native_fnc_planAction, 0.7] call comspec_atak_native_fnc_planDraw;
        private _pv = uiNamespace getVariable ["COMSPEC_ATAK_PlanPreview", []];
        if ((count _pv) > 1) then { [_map, _pv select 1, 0.5] call comspec_atak_native_fnc_planDraw; };
        // Tracé en cours, rendu comme l'élément final (plus le point sous le curseur).
        private _pts = +(_ui get "draft");
        if ((count _pts) > 0 && {(count _def) > 0}) then {
            private _cur = _ui getOrDefault ["cursor", []];
            if (!(_ui get "pen") && {(count _cur) >= 2}) then { _pts pushBack [_cur select 0, _cur select 1, 0]; };
            if (_geom isEqualTo "AREA" && {(count _pts) isEqualTo 2}) then {
                _map drawLine [_pts select 0, _pts select 1, [1, 1, 1, 0.6]];
            };
            [_map, [["", _def select 0, _pts, _ui get "text", _ui get "color", _ui get "dash", _ui get "arrow", _ui get "width", 0]], 0.75] call comspec_atak_native_fnc_planDraw;
            private _mpx = ["mpx", _map] call comspec_atak_native_fnc_planGfx;
            { _map drawEllipse [_x, 2.5 * _mpx, 2.5 * _mpx, 0, [1, 1, 1, 0.9], "#(rgb,8,8,3)color(1,1,1,1)"]; } forEach ((_ui get "draft") select [0, [60, 2] select (_ui get "pen")]);
        };
    };
    case "move": {
        _a params ["_map", "_mx", "_my"];
        private _w = _map ctrlMapScreenToWorld [_mx, _my];
        _ui set ["cursor", _w];
        if (_ui getOrDefault ["drawing", false]) then {
            private _d = _ui get "draft";
            // Un point tous les ~4 pixels ; le lissage se fait au relâchement (fn_strokeSimplify).
            if ((count _d) isEqualTo 0 || {((_map ctrlMapWorldToScreen (_d select -1)) distance2D [_mx, _my]) > pixelW * 4}) then { _d pushBack [_w select 0, _w select 1, 0]; };
        };
    };
    case "down": {
        _a params ["_map", "_btn", "_mx", "_my"];
        if (_btn isEqualTo 1) exitWith {
            _ui set ["rc", [_mx, _my, diag_tickTime]];
            // Le relâchement du clic droit n'arrive pas toujours à la carte (Arma le garde pour le glisser) :
            // le bouton est aussi surveillé, et le premier des deux (« up » ou cette veille) traite le clic.
            [{ (inputMouse 1) isEqualTo 0 }, {
                ["rclick", [_this, getMousePosition]] call comspec_atak_native_fnc_planMap;
            }, _map, 1.5] call CBA_fnc_waitUntilAndExecute;
        };
        if (_btn isNotEqualTo 0) exitWith {};
        private _w = _map ctrlMapScreenToWorld [_mx, _my];
        private _p = [_w select 0, _w select 1, 0];
        private _d = _ui get "draft";
        switch (_geom) do {
            case "": { ["select", ["hit", [_map, [_mx, _my]]] call comspec_atak_native_fnc_planMap] call comspec_atak_native_fnc_planAction; };
            case "POINT": { ["commit", [_p]] call comspec_atak_native_fnc_planAction; };
            case "DIR": { if ((count _d) isEqualTo 0) then { _d pushBack _p; ["refresh"] call comspec_atak_native_fnc_planAction; } else { ["commit", [_d select 0, _p]] call comspec_atak_native_fnc_planAction; }; };
            case "AREA": {
                // Clic près du premier sommet : la zone se ferme.
                if ((count _d) >= 3 && {((_map ctrlMapWorldToScreen (_d select 0)) distance2D [_mx, _my]) < pixelW * 10}) exitWith { ["finish"] call comspec_atak_native_fnc_planAction; };
                _d pushBack _p;
                ["refresh"] call comspec_atak_native_fnc_planAction;
            };
            default {
                if (_ui get "pen") then { _ui set ["draft", [_p]]; _ui set ["drawing", true]; } else { _d pushBack _p; ["refresh"] call comspec_atak_native_fnc_planAction; };
            };
        };
    };
    case "up": {
        _a params ["_map", "_btn", "_mx", "_my"];
        // Carte focalisée : Arma la dessine devant le reste ; le focus revient au bouton hors écran (comme fn_mapUnfocus).
        [{
            private _d = [] call comspec_atak_native_fnc_display;
            if (!isNull _d && {(focusedCtrl _d) isEqualTo ((uiNamespace getVariable ["COMSPEC_ATAK_PlanCtrls", createHashMap]) getOrDefault ["map", controlNull])}) then { ctrlSetFocus (_d displayCtrl 88543); };
        }] call CBA_fnc_execNextFrame;
        if (_btn isEqualTo 1) exitWith { ["rclick", [_map, [_mx, _my]]] call comspec_atak_native_fnc_planMap; };
        if (_btn isEqualTo 0 && {_ui getOrDefault ["drawing", false]}) then {
            _ui set ["drawing", false];
            private _mpx = ["mpx", _map] call comspec_atak_native_fnc_planGfx;
            private _pts = [_ui get "draft", _mpx * 2, true, false, 120] call comspec_atak_native_fnc_strokeSimplify;
            if ((count _pts) >= 2) then { ["commit", _pts] call comspec_atak_native_fnc_planAction; } else { _ui set ["draft", []]; };
        };
    };
    // Clic droit sans glisser (le glisser droit déplace la carte), traité une seule fois :
    // trait ou zone en cours -> terminé ; outil actif -> quitté ; sinon sélection retirée.
    case "rclick": {
        _a params ["_map", "_sp"];
        private _rc = _ui getOrDefault ["rc", []];
        if ((count _rc) < 3) exitWith {};
        _ui set ["rc", []];
        _sp params ["_mx", "_my"];
        if ((abs (_mx - (_rc select 0))) >= pixelW * 6 || {(abs (_my - (_rc select 1))) >= pixelH * 6} || {(diag_tickTime - (_rc select 2)) >= 0.8}) exitWith {};
        private _n = count (_ui get "draft");
        switch (true) do {
            case (_n > 1 && {_geom in ["LINE", "AREA"]}): { ["finish"] call comspec_atak_native_fnc_planAction; };
            case (_n > 0 || {(_ui get "tool") isNotEqualTo ""}): { ["cancel"] call comspec_atak_native_fnc_planAction; };
            case ((_ui get "sel") isNotEqualTo ""): { ["select", ["", ""]] call comspec_atak_native_fnc_planAction; };
        };
    };
    case "dbl": {
        _a params ["_map", "_btn"];
        if (_btn isEqualTo 0 && {_geom in ["LINE", "AREA"]} && {!(_ui get "pen")}) then { ["finish"] call comspec_atak_native_fnc_planAction; };
    };
    case "hit": {
        _a params ["_map", "_sp"];
        private _px = { params ["_p", "_q"]; sqrt ((((_p select 0) - (_q select 0)) / pixelW) ^ 2 + (((_p select 1) - (_q select 1)) / pixelH) ^ 2) };
        private _segPx = {
            params ["_s", "_a", "_b"];
            private _ab = [((_b select 0) - (_a select 0)) / pixelW, ((_b select 1) - (_a select 1)) / pixelH, 0];
            private _as = [((_s select 0) - (_a select 0)) / pixelW, ((_s select 1) - (_a select 1)) / pixelH, 0];
            private _l2 = _ab vectorDotProduct _ab;
            private _t = if (_l2 > 0) then { (((_as vectorDotProduct _ab) / _l2) max 0) min 1 } else { 0 };
            _as distance2D (_ab vectorMultiply _t)
        };
        private _best = ["", ""];
        private _bestD = 14;
        private _inside = ["", ""];
        {
            _x params ["_layer", "_items"];
            {
                _x params ["_id", "_kind", "_pts"];
                private _g = ((_cat get "kinds") getOrDefault [_kind, []]) param [3, ""];
                private _sc = _pts apply { _map ctrlMapWorldToScreen _x };
                if ((count _sc) isEqualTo 0) then { continue };
                private _d = 1e9;
                if (_g in ["POINT", "DIR"]) then { _d = [_sp, _sc select 0] call _px; } else {
                    if (_g isEqualTo "AREA" && {(count _sc) isEqualTo 2}) then {
                        _sc params ["_p0", "_p1"];
                        _sc = [_p0, [_p1 select 0, _p0 select 1], _p1, [_p0 select 0, _p1 select 1]];
                    };
                    private _loop = [_sc, _sc + [_sc select 0]] select (_g isEqualTo "AREA");
                    for "_i" from 0 to ((count _loop) - 2) do { _d = _d min ([_sp, _loop select _i, _loop select (_i + 1)] call _segPx); };
                    // Clic à l'intérieur d'une zone (rayon pair / impair) : sélection si rien de plus proche.
                    if (_g isEqualTo "AREA" && {(_inside select 0) isEqualTo ""}) then {
                        private _in = false;
                        private _n = count _sc;
                        for "_i" from 0 to (_n - 1) do {
                            private _p = _sc select _i; private _q = _sc select ((_i + 1) mod _n);
                            if ((((_p select 1) > (_sp select 1)) isNotEqualTo ((_q select 1) > (_sp select 1))) && {(_sp select 0) < ((_q select 0) - (_p select 0)) * ((_sp select 1) - (_p select 1)) / (((_q select 1) - (_p select 1)) + 1e-9) + (_p select 0)}) then { _in = !_in; };
                        };
                        if (_in) then { _inside = [_id, _layer]; };
                    };
                };
                if (_d < _bestD) then { _bestD = _d; _best = [_id, _layer]; };
            } forEach _items;
        } forEach [["D", ["draft"] call comspec_atak_native_fnc_planAction], ["S", ["shared"] call comspec_atak_native_fnc_planAction]];
        [_best, _inside] select ((_best select 0) isEqualTo "")
    };
};
