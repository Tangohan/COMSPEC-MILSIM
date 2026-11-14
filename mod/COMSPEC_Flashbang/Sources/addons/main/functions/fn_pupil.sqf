/*
    comspec_fb_fnc_pupil : fonction PURE : adaptation de l'œil à la lumière ambiante (pupille fermée en plein soleil,
    grande ouverte la nuit ou dans un local sombre).

    _lum = luminosité ambiante 0..1 vue par l'unité : sunOrMoon x (1 - 0,3 x couverture nuageuse) ; sous un toit, au
    plus 0,25 (fn_exposure).
        luminosité   0 (nuit, local sombre)   0,5 (aube, crépuscule, ciel très couvert)   1 (plein soleil)
        facteur      _nuit (réglage, 1,0)      0,60                                        0,30
    Écart nuit / plein soleil : x3,3 (études de cécité au flash : il faut un flash 3 à 5 fois plus lumineux pour le
    même temps de récupération chez un œil adapté au jour que chez un œil adapté à l'obscurité).
    Tout près, le flash sature la rétine quelle que soit la pupille : la réduction ne s'applique pas sous 2 m et
    s'installe progressivement jusqu'à 6 m.
    [_lum, _distance, _nuit] call comspec_fb_fnc_pupil -> facteur
*/
params [["_lum", 0, [0]], ["_d", 0, [0]], ["_night", 1, [0]]];
_lum = (_lum max 0) min 1;
private _p = if (_lum <= 0.5) then { linearConversion [0, 0.5, _lum, _night, 0.6, true] } else { linearConversion [0.5, 1, _lum, 0.6, 0.3, true] };
if (_p < 1) then { _p = linearConversion [2, 6, _d, 1, _p, true] };
_p
