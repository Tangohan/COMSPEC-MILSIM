/*
    Cible d'une image du zoom progressif de la carte (fonction pure, sans effet sur la carte).
    Params : [_z0 échelle de départ, _z échelle visée, _t avancement 0..1, _c centre voulu [x, y], _offN décalage normalisé [x, y]]
    Retour : [_zi échelle de l'image, _aim point à passer à ctrlMapAnimAdd]
    _offN : écart entre le centre interne de la carte (ce que vise ctrlMapAnimAdd) et son centre visible, divisé par l'échelle.
    Cet écart est fixe à l'écran (carte plus grande que l'écran du téléphone, mini rognée) : en monde il suit l'échelle.
    Centre visible de l'image = _aim - _offN * _zi = _c exactement : avec le suivi, _c est ma position du moment, recalculée
    à chaque image (en marchant ou en véhicule, la carte ne glisse plus vers l'ancien point puis ne se recale plus à la fin).
    Auto-contrôle : ["check"] call comspec_atak_native_fnc_mapZoomAim -> true si, pour un joueur qui avance, le centre visible
    reste sur lui à chaque image et si, sans suivi, il reste fixe.
*/
if ((_this param [0, 0]) isEqualTo "check") exitWith {
    private _ok = true;
    private _offN = [312, -147];
    private _fixe = [5000, 5000];
    for "_i" from 0 to 20 do {
        private _t = _i / 20;
        // Joueur en véhicule : 30 m par image.
        private _me = [4000 + _i * 30, 6000 - _i * 12];
        ([0.08, 0.02, _t, _me, _offN] call comspec_atak_native_fnc_mapZoomAim) params ["_zi", "_aim"];
        private _vis = [(_aim select 0) - (_offN select 0) * _zi, (_aim select 1) - (_offN select 1) * _zi];
        if ((_vis distance2D _me) > 0.01) then { _ok = false; };
        ([0.02, 0.08, _t, _fixe, _offN] call comspec_atak_native_fnc_mapZoomAim) params ["_zj", "_aimF"];
        private _visF = [(_aimF select 0) - (_offN select 0) * _zj, (_aimF select 1) - (_offN select 1) * _zj];
        if ((_visF distance2D _fixe) > 0.01) then { _ok = false; };
        // Échelle : départ et arrivée exacts, monotone entre les deux.
        if (_i isEqualTo 0 && {abs (_zi - 0.08) > 1e-6}) then { _ok = false; };
        if (_i isEqualTo 20 && {abs (_zi - 0.02) > 1e-6}) then { _ok = false; };
    };
    _ok
};
params [["_z0", 0.08], ["_z", 0.08], ["_t", 1], ["_c", [0, 0]], ["_offN", [0, 0]]];
_z0 = _z0 max 0.0001;
_t = (_t max 0) min 1;
// Courbe douce (démarrage et arrivée progressifs), échelle interpolée en géométrique : même rapport à chaque image.
private _k = _t * _t * (3 - 2 * _t);
private _zi = _z0 * ((_z / _z0) ^ _k);
[_zi, [(_c select 0) + (_offN select 0) * _zi, (_c select 1) + (_offN select 1) * _zi]]
