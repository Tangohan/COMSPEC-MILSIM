/*
    comspec_fb_fnc_envProbe : intérieur / extérieur du point d'explosion, sans bloquer une image.

    Méthode (11 rayons lineIntersectsSurfaces au total, 3 par image, donc 4 images ; ~0,02 ms par rayon) :
      - 1 rayon vers le haut (25 m) : toit ou plafond au-dessus ?
      - 8 rayons horizontaux en étoile à 0,9 m du sol (portée 20 m) : distance aux murs dans chaque direction.
    Classement (env "class") :
      exterieur    pas de toit, ou moins de 6 directions fermées
      couloir      fermé, axe le plus court < 3,2 m et axe le plus long > 7 m
      petite_piece fermé, distance moyenne aux murs < 3,5 m
      piece        fermé, distance moyenne < 8 m
      hangar       fermé, plus grand
    "mult" : multiplicateur d'intensité (réglage « Multiplicateur intérieur »), "radius" : taille moyenne du local (m).
    Tant que l'analyse n'est pas finie, fn_burst utilise un seul rayon vers le haut (fn_roofAbove).
*/
params ["_ctx"];
private _pos = _ctx get "pos";
private _origin = _pos vectorAdd [0, 0, 0.9];
private _rays = [[_pos vectorAdd [0, 0, 0.3], _pos vectorAdd [0, 0, 25]]];
for "_i" from 0 to 7 do {
    private _a = _i * 45;
    _rays pushBack [_origin, _origin vectorAdd [20 * sin _a, 20 * cos _a, 0]];
};
(_ctx get "env") set ["state", "pending"];

[{
    params ["_args", "_handle"];
    _args params ["_ctx", "_rays", "_results"];
    private _ignore = _ctx get "device";
    for "_k" from 1 to 3 do {
        if (count _results < count _rays) then {
            (_rays select (count _results)) params ["_a", "_b"];
            private _hit = lineIntersectsSurfaces [_a, _b, _ignore, objNull, true, 1, "GEOM", "VIEW"];
            _results pushBack (if (_hit isEqualTo []) then { -1 } else { (_hit select 0 select 0) vectorDistance _a });
        };
    };
    if (count _results < count _rays) exitWith {};
    [_handle] call CBA_fnc_removePerFrameHandler;

    private _roof = (_results select 0) >= 0;
    private _d = (_results select [1, 8]) apply { [_x, 20] select (_x < 0) };
    private _closed = { _x < 20 } count _d;
    private _mean = 0;
    { _mean = _mean + _x } forEach _d;
    _mean = _mean / 8;
    private _axes = [0, 1, 2, 3] apply { (_d select _x) + (_d select (_x + 4)) };
    private _minAxis = selectMin _axes;
    private _maxAxis = selectMax _axes;
    private _m = missionNamespace getVariable ["comspec_fb_indoorMult", 1.4];
    private _class = "exterieur";
    private _mult = 1;
    if (_roof && {_closed >= 6}) then {
        switch (true) do {
            case (_minAxis < 3.2 && {_maxAxis > 7}): { _class = "couloir"; _mult = 1 + (_m - 1) * 0.9 };
            case (_mean < 3.5): { _class = "petite_piece"; _mult = _m };
            case (_mean < 8): { _class = "piece"; _mult = 1 + (_m - 1) * 0.7 };
            default { _class = "hangar"; _mult = 1 + (_m - 1) * 0.35 };
        };
    };
    private _env = _ctx get "env";
    _env set ["state", "done"];
    _env set ["class", _class];
    _env set ["enclosed", _class != "exterieur"];
    _env set ["mult", _mult];
    _env set ["radius", _mean];
    _env set ["maxAxis", _maxAxis];
    ["env", format ["%1 : %2 (toit=%3 fermé=%4/8 moyenne=%5 m axes=%6..%7 m mult=%8)",
        _ctx get "id", _class, _roof, _closed, _mean toFixed 1, _minAxis toFixed 1, _maxAxis toFixed 1, _mult toFixed 2]] call comspec_fb_fnc_log;
}, 0, [_ctx, _rays, []]] call CBA_fnc_addPerFrameHandler;
