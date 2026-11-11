/*
    comspec_fb_fnc_plan : instants et force relative des N détonations d'une grenade, tirés UNE fois par la machine qui
    fait autorité (serveur ou client lanceur), puis envoyés tels quels à toutes les machines (même nombre, mêmes
    instants partout, dans l'ordre, sans doublon).

    Cadence (réglage « Cadence des flashs », ou imposée par la grenade : classes _R = Hyper rapide, ou par le module) :
      0 Normal       intervalle réglé (0,5 s) x facteur log-uniforme +/- irrégularité ; ~1 sur 8 quasi simultané
      1 Rapide       ~0,15 s (0,10 - 0,22 s)
      2 Hyper rapide ~0,065 s (0,045 - 0,09 s) : les 9 bangs partent en moins d'une demi-seconde
      3 Aléatoire    rafales rapides (0,05 - 0,15 s) et pauses (0,4 - 0,9 s) mélangées
    Plus de 9 détonations (GANG BANG BF) en cadence Normal : ~0,35 s en moyenne (rafales 0,1-0,2 s, pauses 0,6-1 s),
    50 coups en 15 à 20 s environ.
    Force : 1er bang à 1 ; les suivants « roulent » entre 0,65 et 1 (certains plus faibles).
    Variation à 0 : intervalles au centre du mode, toutes les forces à 1.
    [_n, _mode] call comspec_fb_fnc_plan -> [[instants depuis le 1er bang], [forces], mode]
*/
params [["_n", 1, [0]], ["_mode", -1, [0]]];
if (_mode < 0) then { _mode = missionNamespace getVariable ["comspec_fb_rateMode", 0] };
_mode = ((round _mode) max 0) min 3;
private _v = missionNamespace getVariable ["comspec_fb_variation", 0.6];
private _interval = missionNamespace getVariable ["comspec_fb_interval", 0.5];
private _jitter = missionNamespace getVariable ["comspec_fb_jitter", 0.12];
// Tirage autour d'un centre : _c x facteur dans [1 - _a v, 1 + _a v].
private _around = { params ["_c", "_a"]; _c * (1 + (random 2 - 1) * _a * _v) };
private _offsets = [];
private _gains = [];
private _t = 0;
for "_i" from 1 to _n do {
    _offsets pushBack _t;
    _gains pushBack ([1, 1 - _v * (0.2 + random 0.38) * (random 1)] select (_i > 1));
    private _dt = switch (_mode) do {
        case 1: { [0.15, 0.45] call _around };
        case 2: { [0.065, 0.45] call _around };
        case 3: { if (random 1 < 0.22) then { 0.4 + random 0.5 } else { 0.05 + random 0.1 } };
        default {
            if (_n > 9) then {
                private _r = random 1;
                switch (true) do {
                    case (_r < 0.35 * (_v max 0.2)): { 0.1 + random 0.1 };
                    case (_r > 1 - 0.1 * (_v max 0.2)): { 0.6 + random 0.4 };
                    default { 0.22 + random 0.23 };
                }
            } else {
                if (random 1 < 0.2 * _v) then {
                    0.06 + random 0.12
                } else {
                    _interval * exp ((random 2 - 1) * 0.55 * _v) + (random 2 - 1) * _jitter
                }
            }
        };
    };
    _t = _t + ((_dt max 0.03) min 1.2);
};
[_offsets, _gains, _mode]
