/*
    comspec_fb_fnc_visionTable : fonction PURE : table de calibration de l'éblouissement (pupille ouverte : nuit ou
    local sombre ; la réduction de jour est appliquée ensuite par fn_pupil).

    _dn = distance œil-grenade ramenée à l'axe de la table (m aux réglages par défaut), _ang = angle regard-flash (°).

    LOCAL FERMÉ (la lumière est renvoyée par les murs)      EXTÉRIEUR DÉGAGÉ (la lumière se perd : chute rapide)
      distance        0-3,5  5     10    15    25             distance        0-2   5     10    15    19
      champ ≤ 65°     1,00   0,85  0,50  0,30  0,04           champ ≤ 65°     1,00  0,60  0,25  0,08  0
      de côté 90°     0,92   0,50  0,25  0,10  0              de côté 90°     0,90  0,35  0,12  0,03  0
      de dos 180°     0,35   0,20  0,08  0,03  0              de dos 180°     0,30  0,12  0,04  0     0
    Entre 65° et 90°, puis 90° et 180° : interpolation linéaire ; entre deux distances aussi.
    [_dn, _ang, _exterieur] call comspec_fb_fnc_visionTable -> [_facteur, _facteurDansLeChamp]
*/
params [["_dn", 0, [0]], ["_ang", 0, [0]], ["_outdoor", false, [false]]];
private _lerp = {
    params ["_tab", "_x"];
    private _out = (_tab select -1) select 1;
    for "_i" from 0 to (count _tab - 2) do {
        (_tab select _i) params ["_x0", "_y0"];
        (_tab select (_i + 1)) params ["_x1", "_y1"];
        if (_x <= _x1) exitWith { _out = linearConversion [_x0, _x1, _x max _x0, _y0, _y1, true] };
    };
    _out
};
private _tabs = if (_outdoor) then {
    [[[0, 1], [2, 1], [5, 0.6], [10, 0.25], [15, 0.08], [19, 0]],
     [[0, 0.9], [2, 0.9], [5, 0.35], [10, 0.12], [15, 0.03], [19, 0]],
     [[0, 0.3], [2, 0.3], [5, 0.12], [10, 0.04], [15, 0], [19, 0]]]
} else {
    [[[0, 1], [3.5, 1], [5, 0.85], [10, 0.5], [15, 0.3], [25, 0.04], [30, 0]],
     [[0, 0.92], [3.5, 0.92], [5, 0.5], [10, 0.25], [15, 0.1], [25, 0]],
     [[0, 0.35], [3.5, 0.35], [5, 0.2], [10, 0.08], [15, 0.03], [25, 0]]]
};
private _fIn = [_tabs select 0, _dn] call _lerp;
private _fSide = [_tabs select 1, _dn] call _lerp;
private _fBack = [_tabs select 2, _dn] call _lerp;
private _fd = switch (true) do {
    case (_ang <= 65): { _fIn };
    case (_ang <= 90): { linearConversion [65, 90, _ang, _fIn, _fSide, true] };
    default { linearConversion [90, 180, _ang, _fSide, _fBack, true] };
};
[_fd, _fIn]
