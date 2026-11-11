/*
    Fond de carte choisi par le joueur (profil COMSPEC_ATAK_MapStyle) :
      "TOPO"    carte topographique Arma sur fond sombre (défaut), "LIGHT" topo éclaircie (symboles plus lisibles),
      "DARK"    topo assombrie, "NIGHT" voile bleu nuit (ancien calque « Carte nuit »),
      "CLASSIC" la carte d'origine d'Arma 3 (celle de la touche M) : couleurs et rendu du jeu, aucune image dessous,
                sur son propre contrôle carte (COMSPEC_RscMapAtakClassic). Ne dépend d'aucun mod, le moins coûteux.
      "SLATE"   ardoise : bleu-gris sombre, routes turquoise, sur son propre contrôle carte (couleurs fixées par sa classe),
      "HYBRID"  texture satellite du terrain (celle du jeu) avec routes et pistes, sur son propre contrôle carte,
      "PHOTO"   SATELLITE dans les menus : la vraie photo de la carte (mod @COMSPEC_ATAK_Photo ou téléchargée depuis
                Athena, fn_photoMap) ; CLASSIC quand cette carte n'en a pas.
    "SAT" (texture satellite sans routes) était affiché CLASSIQUE avant 4.4.x : relu comme CLASSIC, le fond que le
    joueur avait choisi sous ce nom.
    Params : [nouvelle valeur (optionnel)] : l'enregistre dans le profil puis la renvoie.
             ["LIST"] : les fonds des menus (fn_pageMap, fn_pageSettings), [[libellé, valeur, description, disponible]...].
*/
params [["_set", ""]];
// Une seule liste pour la carte et les Réglages : libellé, valeur, description d'une ligne, disponible.
// SATELLITE n'est disponible qu'avec la photo de la carte en cours (mod @COMSPEC_ATAK_Photo).
if (_set isEqualTo "LIST") exitWith {
    [
        ["TOPO", "TOPO", "TOPO : la carte topographique d'Arma retouchée pour le téléphone (par défaut)", true],
        ["CLAIR", "LIGHT", "CLAIR : la carte TOPO éclaircie, symboles plus lisibles", true],
        ["SOMBRE", "DARK", "SOMBRE : la carte TOPO assombrie", true],
        ["NUIT", "NIGHT", "NUIT : la carte TOPO sous un voile bleu nuit", true],
        ["ARDOISE", "SLATE", "ARDOISE : bleu-gris sombre, routes turquoise", true],
        ["CLASSIQUE", "CLASSIC", "CLASSIQUE : la carte d'origine d'Arma 3, comme avec la touche M", true],
        ["HYBRIDE", "HYBRID", "HYBRIDE : vue satellite du terrain d'Arma avec routes et pistes", true],
        ["SATELLITE", "PHOTO", "SATELLITE : la vraie photo de la carte (mod COMSPEC ATAK Photo)", (["info"] call comspec_atak_native_fnc_photoMap) isNotEqualTo []]
    ]
};
if (_set isNotEqualTo "") exitWith {
    profileNamespace setVariable ["COMSPEC_ATAK_MapStyle", _set];
    profileNamespace setVariable ["COMSPEC_ATAK_LayerNight", nil];
    [] call comspec_atak_native_fnc_saveProfile;
    _set
};
private _v = profileNamespace getVariable ["COMSPEC_ATAK_MapStyle", ""];
if !(_v isEqualType "") then { _v = ""; };
if (_v isEqualTo "") then { _v = ["TOPO", "NIGHT"] select (profileNamespace getVariable ["COMSPEC_ATAK_LayerNight", false]); };
if (_v isEqualTo "SAT") then { _v = "CLASSIC"; };
if !(_v in ["TOPO", "LIGHT", "DARK", "NIGHT", "CLASSIC", "PHOTO", "HYBRID", "SLATE"]) then { _v = "TOPO"; };
if (_v isEqualTo "PHOTO" && {(["info"] call comspec_atak_native_fnc_photoMap) isEqualTo []}) then { _v = "CLASSIC"; };
_v
