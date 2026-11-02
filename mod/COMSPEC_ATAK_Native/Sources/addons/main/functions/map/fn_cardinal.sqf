/*
    Point cardinal d'un angle, dans la langue du téléphone (O / W) : une seule table pour la carte, la boussole et les
    apps. Le résultat ne doit pas repasser par fn_tr (« NO » y deviendrait « NON » : fr.txt traduit « No »).
    Params : [angle (degrés, négatif ou au-delà de 360 accepté), points (16 par défaut, ou 8)]
    Renvoie : « NNO », « NO »… en français ; « NNW », « NW »… sinon.
*/
params [["_a", 0], ["_pts", 16]];
private _fr = (missionNamespace getVariable ["COMSPEC_ATAK_Lang", "en"]) isEqualTo "fr";
_a = ((_a mod 360) + 360) mod 360;
if (_pts isEqualTo 8) exitWith {
    ([["N", "NE", "E", "SE", "S", "SW", "W", "NW"], ["N", "NE", "E", "SE", "S", "SO", "O", "NO"]] select _fr) select ((round (_a / 45)) mod 8)
};
([
    ["N", "NNE", "NE", "ENE", "E", "ESE", "SE", "SSE", "S", "SSW", "SW", "WSW", "W", "WNW", "NW", "NNW"],
    ["N", "NNE", "NE", "ENE", "E", "ESE", "SE", "SSE", "S", "SSO", "SO", "OSO", "O", "ONO", "NO", "NNO"]
] select _fr) select ((round (_a / 22.5)) mod 16)
