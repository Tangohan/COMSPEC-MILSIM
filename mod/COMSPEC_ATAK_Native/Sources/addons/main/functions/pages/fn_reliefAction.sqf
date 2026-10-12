/*
    Actions de l'app Relief (champ de vision depuis le joueur et carte des altitudes).
      "set"     : [clé d'état, valeur] (rlRadius, rlMode, rlHeight, rlSector, rlObjects) puis redessine la page.
      "compute" : [origine] lance le calcul en tâche de fond (grille 41 x 41 autour de l'observateur) :
                  case visible si aucune pente ne coupe la ligne œil → sol de la case + 1,7 m.
                  Origine : rien = moi ; [x, y] = point de la carte (observateur posé au sol) ; netId d'une unité ou
                  d'un drone = depuis ses yeux / sa caméra. Hauteur ajoutée à l'œil : rlHeight (0, 10, 50 ou 150 m :
                  toit, mât, drone). Secteur : rlSector (360, 120 ou 60°) dans la direction où regarde l'observateur
                  (vers le nord pour un point de la carte), les cases hors secteur ne sont pas calculées.
                  rlObjects (vrai par défaut) : bâtiments, murs et végétation masquent aussi la vue.
      "clear"   : efface le résultat.
      "show"    : affiche / masque le calque sur la carte.
      "locate"  : centre la carte sur le point le plus haut du calcul.
    Résultat : missionNamespace COMSPEC_ATAK_Viewshed = [centre, taille de case, [[pos, visible, altitude, couleur altitude, masqué par un objet]...],
               rayon, altitude mini, altitude maxi, % visible, index du point le plus haut, heure du calcul,
               œil ASL, [direction, ouverture] ou [] (360°), nom de l'observateur].
*/
params [["_act", ""], ["_k", ""], ["_v", ""]];
private _render = { if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "RELIEF") then { [{ ["RELIEF"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }; };
switch (_act) do {
    case "set": {
        (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set [_k, _v];
        call _render;
    };
    case "compute": {
        if (missionNamespace getVariable ["COMSPEC_ATAK_ViewshedBusy", false]) exitWith { ["INFO", "Calcul du relief déjà en cours", 3, 20] call comspec_atak_native_fnc_notify; };
        private _st = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        private _radius = _st getOrDefault ["rlRadius", 1000];
        private _hAdd = _st getOrDefault ["rlHeight", 0];
        private _fov = _st getOrDefault ["rlSector", 360];
        private _eye = (eyePos player) vectorAdd [0, 0, _hAdd];
        private _dir = getDirVisual player;
        private _who = "Moi";
        private _ign = vehicle player;
        switch (true) do {
            case (_k isEqualType [] && {(count _k) >= 2}): {
                _eye = [_k select 0, _k select 1, ((getTerrainHeightASL _k) max 0) + 1.7 + _hAdd];
                _dir = 0; _fov = 360;
                _who = format ["Point %1", [_k] call comspec_atak_native_fnc_gridRef];
                _ign = objNull;
            };
            case (_k isEqualType "" && {_k isNotEqualTo ""}): {
                private _o = objectFromNetId _k;
                if (isNull _o) exitWith {};
                _ign = vehicle _o;
                if (_o isKindOf "CAManBase") then {
                    _eye = (eyePos _o) vectorAdd [0, 0, _hAdd];
                    private _ed = eyeDirection _o;
                    _dir = (_ed select 0) atan2 (_ed select 1);
                    _who = [_o, true] call comspec_atak_native_fnc_unitCallsign;
                } else {
                    // Drone ou véhicule : depuis l'objet, sans hauteur ajoutée ; un drone voit tout autour (tourelle).
                    _eye = (getPosASL _o) vectorAdd [0, 0, -0.5];
                    _dir = getDir _o; _fov = 360;
                    _who = getText (configOf _o >> "displayName");
                };
            };
        };
        missionNamespace setVariable ["COMSPEC_ATAK_ViewshedBusy", true];
        missionNamespace setVariable ["COMSPEC_ATAK_ViewshedProgress", 0];
        call _render;
        [_radius, _render, _eye, [[_dir, _fov], []] select (_fov >= 360), _who, _ign, _st getOrDefault ["rlObjects", true]] spawn {
            params ["_radius", "_render", "_eye", "_sector", "_who", "_ign", "_objs"];
            private _n = 41;
            private _half = floor (_n / 2);
            private _cell = (2 * _radius) / (_n - 1);
            private _center = [_eye select 0, _eye select 1];
            private _cells = [];
            private _minH = 1e9;
            private _maxH = -1e9;
            private _vis = 0;
            private _total = 0;
            private _best = -1;
            for "_i" from 0 to (_n - 1) do {
                for "_j" from 0 to (_n - 1) do {
                    private _dx = (_i - _half) * _cell;
                    private _dy = (_j - _half) * _cell;
                    // Cercle plutôt que carré : on ignore les coins hors du rayon.
                    if (sqrt (_dx * _dx + _dy * _dy) > _radius + _cell / 2) then { continue };
                    if (_sector isNotEqualTo [] && {_i isNotEqualTo _half || {_j isNotEqualTo _half}}) then {
                        private _off = abs ((((_dx atan2 _dy) - (_sector select 0)) + 540) mod 360 - 180);
                        if (_off > (_sector select 1) / 2) then { continue };
                    };
                    private _p = [(_center select 0) + _dx, (_center select 1) + _dy];
                    private _h = getTerrainHeightASL _p;
                    // Mer : on prend la surface de l'eau comme sol.
                    private _ground = _h max 0;
                    private _tgt = [_p select 0, _p select 1, _ground + 1.7];
                    private _here = _i isEqualTo _half && {_j isEqualTo _half};
                    private _seen = _here || {!(terrainIntersectASL [_eye, _tgt])};
                    // Masqué par un bâtiment, un mur ou la végétation (option rlObjects) : case orange au lieu de verte.
                    private _byObj = !_here && {_seen} && {_objs} && {(count (lineIntersectsSurfaces [_eye, _tgt, _ign, objNull, true, 1, "VIEW", "FIRE"])) > 0};
                    if (_byObj) then { _seen = false; };
                    if (_seen) then { _vis = _vis + 1; };
                    _total = _total + 1;
                    if (_h < _minH) then { _minH = _h; };
                    if (_h > _maxH) then { _maxH = _h; _best = count _cells; };
                    _cells pushBack [[_p select 0, _p select 1, _h], _seen, _h, [], _byObj];
                };
                missionNamespace setVariable ["COMSPEC_ATAK_ViewshedProgress", (_i + 1) / _n];
            };
            // Couleurs d'altitude précalculées (bleu bas, vert moyen, rouge haut) : la carte n'a plus qu'à dessiner.
            private _span = (_maxH - _minH) max 1;
            {
                private _t = ((_x select 2) - _minH) / _span;
                private _col = if (_t < 0.5) then {
                    private _u = _t * 2;
                    [0.15 + 0.2 * _u, 0.35 + 0.5 * _u, 0.95 - 0.6 * _u, 0.4]
                } else {
                    private _u = (_t - 0.5) * 2;
                    [0.35 + 0.6 * _u, 0.85 - 0.55 * _u, 0.35 - 0.2 * _u, 0.4]
                };
                _x set [3, _col];
            } forEach _cells;
            missionNamespace setVariable ["COMSPEC_ATAK_Viewshed", [_center, _cell, _cells, _radius, _minH, _maxH, round (100 * _vis / (_total max 1)), _best, dayTime, _eye, _sector, _who]];
            missionNamespace setVariable ["COMSPEC_ATAK_ViewshedShow", true];
            missionNamespace setVariable ["COMSPEC_ATAK_ViewshedBusy", false];
            ["SUCCESS", format ["Champ de vision (%3) : %1 %% visible sur %2 m", round (100 * _vis / (_total max 1)), _radius, _who], 4, 30] call comspec_atak_native_fnc_notify;
            call _render;
        };
    };
    case "clear": {
        missionNamespace setVariable ["COMSPEC_ATAK_Viewshed", nil];
        call _render;
    };
    case "show": {
        missionNamespace setVariable ["COMSPEC_ATAK_ViewshedShow", !(missionNamespace getVariable ["COMSPEC_ATAK_ViewshedShow", true])];
        call _render;
    };
    case "locate": {
        private _vs = missionNamespace getVariable ["COMSPEC_ATAK_Viewshed", []];
        private _target = if ((count _vs) > 7 && {(_vs select 7) >= 0}) then { ((_vs select 2) select (_vs select 7)) select 0 } else { player };
        if (_k isEqualTo "center" && {(count _vs) > 0}) then { _target = _vs select 0; };
        ["MAP"] call comspec_atak_native_fnc_navigate;
        [{ _this call comspec_atak_native_fnc_mapCenter; }, [_target, 0.06]] call CBA_fnc_execNextFrame;
    };
};
true
