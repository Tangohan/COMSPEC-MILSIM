/*
    Anti-chevauchement des étiquettes de la carte : grille grossière (cases de 0,06 unité d'écran) qui range les
    rectangles déjà posés. Un rectangle n'est comparé qu'à ceux des cases qu'il touche (avant : à tous les rectangles
    posés, pour chaque étiquette et chaque place essayée).
    Ordre de priorité entre les calques (une image de retard, les positions bougent peu) : cartouches des unités et
    contacts ENI (fn_mapLabels, COMSPEC_ATAK_LblRectsU) > noms des repères (fn_mapMarkerText, COMSPEC_ATAK_LblRects)
    > noms des lieux (fn_mapPlaceText).
    Params : [grille (table), rectangle écran [x0, y0, x1, y1], mode 0 tester | 1 tester et poser si libre | 2 poser]
    Renvoie true si le rectangle chevauche un rectangle déjà posé (mode 2 : false).
*/
params ["_g", "_r", ["_mode", 0]];
_r params ["_x0", "_y0", "_x1", "_y1"];
private _cx0 = floor (_x0 / 0.06); private _cx1 = floor (_x1 / 0.06);
private _cy0 = floor (_y0 / 0.06); private _cy1 = floor (_y1 / 0.06);
private _hit = false;
if (_mode < 2) then {
    for "_cx" from _cx0 to _cx1 do {
        for "_cy" from _cy0 to _cy1 do {
            if (((_g getOrDefault [_cx * 4096 + _cy, []]) findIf { _x0 < (_x select 2) && {_x1 > (_x select 0)} && {_y0 < (_x select 3)} && {_y1 > (_x select 1)} }) >= 0) exitWith { _hit = true; };
        };
        if (_hit) exitWith {};
    };
};
if (_mode isEqualTo 2 || {_mode isEqualTo 1 && {!_hit}}) then {
    for "_cx" from _cx0 to _cx1 do {
        for "_cy" from _cy0 to _cy1 do {
            private _k = _cx * 4096 + _cy;
            if !(_k in _g) then { _g set [_k, []]; };
            (_g get _k) pushBack _r;
        };
    };
};
_hit
