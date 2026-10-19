/*
    Fond de carte choisi par le joueur (profil COMSPEC_ATAK_MapStyle) :
      "TOPO"  carte topographique Arma (défaut), "LIGHT" topo éclaircie (symboles plus lisibles),
      "DARK"  topo assombrie, "NIGHT" voile bleu nuit (ancien calque « Carte nuit »),
      "PHOTO" capture de la carte (mod @COMSPEC_ATAK_Photo, fn_photoMap) ; TOPO sur une carte sans capture.
    Params : [nouvelle valeur (optionnel)] : l'enregistre dans le profil puis la renvoie.
*/
params [["_set", ""]];
if (_set isNotEqualTo "") exitWith {
    profileNamespace setVariable ["COMSPEC_ATAK_MapStyle", _set];
    profileNamespace setVariable ["COMSPEC_ATAK_LayerNight", nil];
    saveProfileNamespace;
    _set
};
private _v = profileNamespace getVariable ["COMSPEC_ATAK_MapStyle", ""];
if !(_v isEqualType "") then { _v = ""; };
if (_v isEqualTo "") then { _v = ["TOPO", "NIGHT"] select (profileNamespace getVariable ["COMSPEC_ATAK_LayerNight", false]); };
if !(_v in ["TOPO", "LIGHT", "DARK", "NIGHT", "PHOTO"]) then { _v = "TOPO"; };
if (_v isEqualTo "PHOTO" && {(["info"] call comspec_atak_native_fnc_photoMap) isEqualTo []}) then { _v = "TOPO"; };
_v
