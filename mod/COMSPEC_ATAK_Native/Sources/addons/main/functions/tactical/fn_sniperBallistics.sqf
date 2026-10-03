/*
    Balistique extérieure du modèle Arma : dv = airFriction · |v| · v · dt, plus la gravité.
    Params : [v0 (m/s), airFriction (négatif), zérotage (m), hauteur de lunette (m), portée max (m), pas (m)]
    Renvoie [[distance, écart à la ligne de visée (m), temps de vol (s), vitesse (m/s)], ...] tous les « pas » mètres,
    pour l'arme zérotée à la distance donnée (angle de tir trouvé par dichotomie).
*/
params ["_v0", "_k", ["_zero", 100], ["_h", 0.06], ["_max", 1500], ["_step", 50]];
private _g = 9.80665;
// Hauteur de la balle à la distance _x pour un angle _th (radians) ; sortie [y, t, v] (y = -1e9 si elle n'y arrive pas).
private _fly = {
    params ["_th", "_xt", ["_rec", false]];
    private _vx = _v0 * cos (_th * 57.29578);
    private _vy = _v0 * sin (_th * 57.29578);
    private _px = 0; private _py = 0; private _t = 0; private _dt = 0.004;
    private _out = []; private _next = _step;
    while { _px < _xt && {_t < 12} && {_vx > 1} } do {
        private _v = sqrt (_vx * _vx + _vy * _vy);
        private _nx = _px + _vx * _dt; private _ny = _py + _vy * _dt;
        if (_rec && {_nx >= _next}) then {
            private _f = (_next - _px) / (_nx - _px);
            _out pushBack [_next, (_py + (_ny - _py) * _f) - _h, _t + _dt * _f, _v];
            _next = _next + _step;
        };
        _px = _nx; _py = _ny;
        _vx = _vx + _k * _v * _vx * _dt;
        _vy = _vy + (_k * _v * _vy - _g) * _dt;
        _t = _t + _dt;
    };
    if (_rec) exitWith { _out };
    if (_px < _xt) exitWith { -1e9 };
    _py
};
private _lo = -0.01; private _hi = 0.06;
for "_i" from 1 to 24 do {
    private _mid = (_lo + _hi) / 2;
    if (([_mid, _zero] call _fly) < _h) then { _lo = _mid; } else { _hi = _mid; };
};
[(_lo + _hi) / 2, _max, true] call _fly
