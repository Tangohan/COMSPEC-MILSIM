/*
    comspec_fb_fnc_tinnitusLevel : niveau (0..1) de l'acouphène à l'instant _now : pic du dernier flash, retombée
    a(t) = 0,6 e^(-t/2,5) + 0,4 e^(-t/20) depuis ce flash (aigu fort qui retombe vite, puis traîne lentement).
    [_etat, _now] call comspec_fb_fnc_tinnitusLevel -> 0..1
*/
params ["_t", "_now"];
private _dt = (_now - (_t getOrDefault ["hit", _now])) max 0;
(_t getOrDefault ["peak", 0]) * (0.6 * exp (-_dt / 2.5) + 0.4 * exp (-_dt / 20))
