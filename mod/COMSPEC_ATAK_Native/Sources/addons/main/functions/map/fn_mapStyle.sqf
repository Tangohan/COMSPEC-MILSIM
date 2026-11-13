/*
    Fond de carte choisi par le joueur (profil COMSPEC_ATAK_MapStyle) :
      "CLASSIC" la carte d'origine d'Arma 3 (celle de la touche M), défaut des nouveaux joueurs depuis 4.4.x : contrôle
                carte du jeu sans aucune retouche (COMSPEC_RscMapAtakClassic), le moteur y écrit lui-même les noms de
                lieux, les numéros de grille et les marqueurs, comme sur la carte M (fn_mapOnDraw ne les redessine pas).
      "TOPO"    carte topographique Arma sur fond sombre, "LIGHT" topo éclaircie (symboles plus lisibles),
      "DARK"    topo assombrie, "NIGHT" voile bleu nuit (ancien calque « Carte nuit »),
      "SLATE"   ardoise : bleu-gris sombre, routes turquoise, sur son propre contrôle carte (couleurs fixées par sa classe),
      "HYBRID"  texture satellite du terrain (celle du jeu) avec routes et pistes, sur son propre contrôle carte,
      "PHOTO"   SATELLITE dans les menus : la vraie photo de la carte (mod @COMSPEC_ATAK_Photo ou téléchargée depuis
                Athena, fn_photoMap) ; CLASSIC quand cette carte n'en a pas.
    "SAT" (texture satellite sans routes) était affiché CLASSIQUE avant 4.4.x : relu comme CLASSIC, le fond que le
    joueur avait choisi sous ce nom. Choix déjà enregistrés gardés tels quels (TOPO compris).
    Params : [nouvelle valeur (optionnel)] : l'enregistre dans le profil puis la renvoie.
             ["LIST"] : les fonds des menus (fn_pageMap, fn_pageSettings), [[libellé, valeur, description, disponible]...].
*/
params [["_set", ""]];
// Une seule liste pour la carte et les Réglages : libellé, valeur, description d'une ligne, disponible. CLASSIQUE en
// premier (retour joueur 4.4.0 : sixième bouton, en deuxième rangée, il passait inaperçu).
// SATELLITE n'est disponible qu'avec la photo de la carte en cours (mod @COMSPEC_ATAK_Photo).
if (_set isEqualTo "LIST") exitWith {
    [
        ["CLASSIQUE", "CLASSIC", "CLASSIQUE : la carte d'origine d'Arma 3, celle de la touche M", true],
        ["TOPO", "TOPO", "TOPO : la carte topographique d'Arma retouchée pour le téléphone", true],
        ["CLAIR", "LIGHT", "CLAIR : la carte TOPO éclaircie, symboles plus lisibles", true],
        ["SOMBRE", "DARK", "SOMBRE : la carte TOPO assombrie", true],
        ["NUIT", "NIGHT", "NUIT : la carte TOPO sous un voile bleu nuit", true],
        ["ARDOISE", "SLATE", "ARDOISE : bleu-gris sombre, routes turquoise", true],
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
if (_v isEqualTo "") then { _v = ["CLASSIC", "NIGHT"] select (profileNamespace getVariable ["COMSPEC_ATAK_LayerNight", false]); };
if (_v isEqualTo "SAT") then { _v = "CLASSIC"; };
if !(_v in ["TOPO", "LIGHT", "DARK", "NIGHT", "CLASSIC", "PHOTO", "HYBRID", "SLATE"]) then { _v = "CLASSIC"; };
if (_v isEqualTo "PHOTO" && {(["info"] call comspec_atak_native_fnc_photoMap) isEqualTo []}) then { _v = "CLASSIC"; };
_v
