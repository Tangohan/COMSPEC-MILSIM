/*
    Textes de la carte au même format partout (cartouches des unités, noms des repères, mesure, curseur) :
      ["dist", mètres, azimut (facultatif)]  « 48 m », « 1,2 km » (« 1.2 km » en anglais), « 12 km » au-delà de 10 km ;
                                             avec azimut : « 48 m · 034° » (cap toujours sur 3 chiffres) ;
      ["cut", texte, longueur max]           nom trop long coupé proprement : « BRAVO 2-1 RECO AV… » (accents compris).
    Appelée hors de l'image (caches des étiquettes) sauf pour la mesure et le curseur.
*/
params ["_kind", "_v", ["_b", -1]];
if (_kind isEqualTo "cut") exitWith {
    private _a = toArray _v;
    [_v, (trim (toString (_a select [0, (_b - 1) max 1]))) + "…"] select ((count _a) > _b)
};
private _t = if (_v < 1000) then { format ["%1 m", round _v] } else {
    private _km = if (_v < 10000) then { (_v / 1000) toFixed 1 } else { str (round (_v / 1000)) };
    if ((missionNamespace getVariable ["COMSPEC_ATAK_Lang", "en"]) isEqualTo "fr") then { _km = (_km splitString ".") joinString ","; };
    _km + " km"
};
if (_b < 0) exitWith { _t };
format ["%1 · %2°", _t, [(round _b) mod 360, 3] call CBA_fnc_formatNumber]
