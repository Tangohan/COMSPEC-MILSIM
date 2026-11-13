/*
    comspec_fb_fnc_rand : tirage aléatoire symétrique, de moyenne nulle, réglé par « Variation aléatoire » (0 à 100 %,
    60 % par défaut) : (random 2 - 1) x _amplitude x variation. Les effets s'en servent en facteur 1 + tirage : la
    moyenne reste celle de la table de calibration, l'écart est borné par _amplitude.
    [_amplitude] call comspec_fb_fnc_rand -> nombre dans [-_amplitude x v, +_amplitude x v]
*/
params [["_amp", 0, [0]]];
(random 2 - 1) * _amp * (missionNamespace getVariable ["comspec_fb_variation", 0.6])
