/*
    comspec_fb_fnc_aiParams : réglages de la réaction des IA, lus UNE fois par détonation (réglages CBA « 4 - IA et équipe »).

    Réglage « Réaction des IA » :
      0 Discrète   comportement d'avant 1.0.1 : compétences réduites, suppression, aucune autre action (rayon, intensité,
                   durée, immunité et animation ne comptent pas)
      1 Réaliste   aveuglement (ciblage coupé 2 à 10 s), à-coup d'arrêt, geste réflexe, compétences très réduites 5 à 20 s
      2 Forte      comme Réaliste, en plus fort (intensité x1,25, durées x1,25, rayon x1,15, moins d'immunité)
    [] call comspec_fb_fnc_aiParams
      -> [mode, rayon (x), plancher de pupille, intensité, durée (x), immunité 0..1, animation, plafond d'unités par détonation]
*/
private _mode = (round (missionNamespace getVariable ["comspec_fb_aiReaction", 1])) max 0 min 2;
private _max = round ((missionNamespace getVariable ["comspec_fb_aiMax", 20]) max 1 min 80);
if (_mode == 0) exitWith { [0, 1, 0, 1, 1, 0, false, _max] };
private _strong = [1, 1.25] select (_mode == 2);
[
    _mode,
    ((missionNamespace getVariable ["comspec_fb_aiRadius", 1.5]) max 0.5 min 3) * ([1, 1.15] select (_mode == 2)),
    [0.55, 0.7] select (_mode == 2),
    ((missionNamespace getVariable ["comspec_fb_aiIntensity", 1]) max 0 min 2) * _strong,
    ((missionNamespace getVariable ["comspec_fb_aiDuration", 1]) max 0.3 min 2) * _strong,
    ((missionNamespace getVariable ["comspec_fb_aiImmunity", 0.08]) max 0 min 0.5) * ([1, 0.5] select (_mode == 2)),
    missionNamespace getVariable ["comspec_fb_aiAnim", true],
    _max
]
