/*
    Relief des bâtiments sur le fond SATELLITE (fausse 3D, comme la vue 2D de Google Maps) : ombre portée selon la
    hauteur de chaque bâtiment et contour net du toit, dessinés par-dessus la photo aux zooms proches (fn_photoMap).
    Les bâtiments viennent du jeu, pas de l'image : position, orientation, emprise et hauteur exactes (boîte du modèle).
    Relevés par cases de 200 m autour de la vue, gardés pour la mission (COMSPEC_ATAK_PhotoReliefCells) : rien n'est
    recalculé à l'image. Au plus 600 bâtiments dessinés par image ; au-delà de 900 m de largeur de vue, rien.
    Params : [contrôle carte, largeur de la vue en mètres, coin haut gauche (monde), coin bas droit (monde)]
*/
params ["_map", "_wM", "_tl", "_br"];
if (_wM > 900) exitWith {};
private _cells = missionNamespace getVariable "COMSPEC_ATAK_PhotoReliefCells";
if (isNil "_cells") then { _cells = createHashMap; missionNamespace setVariable ["COMSPEC_ATAK_PhotoReliefCells", _cells]; };
// Côté d'une case de relevé (m). Attention : SQF ne distingue pas _C de _c.
private _cellM = 200;
// Une case : [[4 coins au sol (monde, 2D)], hauteur] par bâtiment.
private _cell = {
    params ["_cx", "_cy"];
    private _key = _cx * 10000 + _cy;
    private _v = _cells get _key;
    if (!isNil "_v") exitWith { _v };
    _v = [];
    private _c = [(_cx + 0.5) * _cellM, (_cy + 0.5) * _cellM];
    {
        private _o = _x;
        // Emprise de la case par le centre de l'objet : chaque bâtiment n'est relevé qu'une fois.
        private _p = getPosWorld _o;
        if ((floor ((_p select 0) / _cellM)) isEqualTo _cx && {(floor ((_p select 1) / _cellM)) isEqualTo _cy}) then {
            (0 boundingBoxReal _o) params ["_mn", "_mx"];
            private _h = (_mx select 2) - (_mn select 2);
            private _w = (_mx select 0) - (_mn select 0);
            private _l = (_mx select 1) - (_mn select 1);
            // Bâtiments seulement : assez hauts et assez grands (ni murets, ni poteaux, ni ruines au ras du sol).
            if (_h > 2.5 && {_w * _l > 8} && {_w < 120} && {_l < 120}) then {
                private _d = getDir _o;
                private _cs = cos _d; private _sn = sin _d;
                private _pts = [[_mn select 0, _mn select 1], [_mx select 0, _mn select 1], [_mx select 0, _mx select 1], [_mn select 0, _mx select 1]] apply {
                    _x params ["_mx2", "_my2"];
                    [(_p select 0) + _mx2 * _cs + _my2 * _sn, (_p select 1) - _mx2 * _sn + _my2 * _cs, 0]
                };
                _v pushBack [_pts, _h min 40];
            };
        };
    } forEach (nearestTerrainObjects [_c, ["BUILDING", "HOUSE", "CHURCH", "CHAPEL", "FUELSTATION", "HOSPITAL", "LIGHTHOUSE", "TOWER", "VIEW-TOWER", "TRANSMITTER", "WATERTOWER", "POWERSOLAR", "POWERWIND"], _cellM * 0.75, false, true]);
    _cells set [_key, _v];
    _v
};
// Ombre vers le nord-est (soleil au sud-ouest, 55° de haut) : 0,7 m d'ombre par mètre de hauteur.
private _sx = 0.7 * 0.6; private _sy = 0.7 * 0.8;
private _shadow = [0, 0, 0, 0.32];
private _fill = "#(rgb,8,8,3)color(0,0,0,1)";
private _edge = [0.08, 0.08, 0.08, 0.55];
private _n = 0;
for "_cy" from (floor ((_br select 1) / _cellM)) to (floor ((_tl select 1) / _cellM)) do {
    for "_cx" from (floor ((_tl select 0) / _cellM)) to (floor ((_br select 0) / _cellM)) do {
        {
            if (_n >= 600) then { break };
            _x params ["_pts", "_h"];
            private _ox = _sx * _h; private _oy = _sy * _h;
            // Faces d'ombre : côtés tournés vers l'ombre seulement (hors de l'emprise, pas de toit assombri).
            for "_i" from 0 to 3 do {
                private _a = _pts select _i;
                private _b = _pts select ((_i + 1) mod 4);
                // Normale extérieure du côté (coins dans le sens direct) : (dy, -dx).
                if (((_b select 1) - (_a select 1)) * _ox - ((_b select 0) - (_a select 0)) * _oy > 0) then {
                    private _a2 = [(_a select 0) + _ox, (_a select 1) + _oy, 0];
                    private _b2 = [(_b select 0) + _ox, (_b select 1) + _oy, 0];
                    _map drawTriangle [[_a, _b, _b2, _a, _b2, _a2], _shadow, _fill];
                };
            };
            _map drawPolygon [_pts, _edge];
            _n = _n + 1;
        } forEach ([_cx, _cy] call _cell);
    };
};
