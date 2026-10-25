/*
    Adresse courte à taper sur un autre appareil : sans « https:// » ni « /public », jamais deux barres d'affilée.
    « https://athena.ttrd.fr/public/lier » -> « athena.ttrd.fr/lier ».
    Sans expression régulière : dans regexReplace, Arma lit le dernier « / » du motif comme le début des options
    (« ^https?:// » devenait « ^https?:/ », « /public/ » devenait « /public »), d'où l'adresse « //lier » affichée.
    Params : [adresse]  Retour : adresse courte ("" si vide)
*/
params [["_uri", "", [""]]];
private _s = trim _uri;
{
    if (((toLower _s) find _x) isEqualTo 0) exitWith { _s = _s select [count _x]; };
} forEach ["https://", "http://"];
private _p = (toLower _s) find "/public/";
if (_p >= 0) then { _s = (_s select [0, _p]) + (_s select [_p + 7]); };
if ((count _s) >= 7 && {((toLower _s) select [(count _s) - 7]) isEqualTo "/public"}) then { _s = _s select [0, (count _s) - 7]; };
while { (_s find "//") >= 0 } do {
    private _i = _s find "//";
    _s = (_s select [0, _i]) + (_s select [_i + 1]);
};
_s
