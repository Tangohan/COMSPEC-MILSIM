# Carte ATAK : toutes les unités en symbole d’infanterie

## Contexte

Les positions BFT (opérateurs et IA alliées) s’affichent sur la carte Athena avec le symbole OTAN ami. L’opérateur attend un glyphe selon le type : à pied, char, artillerie, hélicoptère, etc.

## Symptôme

Toutes les unités apparaissent comme de l’infanterie (rectangle bleu + X), y compris un opérateur à bord d’un véhicule ou d’un aéronef. La queue de cap reste visible ; le cadre d’équipe de feu aussi.

## Cause

Le dessin utilisait le **métier** de l’opérateur (chef d’équipe, tireur…) pour choisir le symbole. Ces libellés ne correspondent pas à un type d’unité, donc le repli infanterie s’appliquait toujours. Le type déjà remonté par le jeu (à pied, hélicoptère, avion, drone, véhicule terrestre) n’était pas lu.

## Correctif

- La carte et la liste des effectifs lisent le type de plateforme, et à défaut le véhicule, avant le métier.
- À pied : infanterie. Char : ovale. VCI / VTT : infanterie mécanisée. Véhicule léger : motorisé. Camion : logistique. Artillerie / mortier, voilure tournante / fixe, drone, embarcation : glyphes dédiés.
- Médecin / état-major seulement si la personne est à pied et que le métier le dit clairement.
- Le pack jeu envoie désormais un type plus fin (char, VCI, artillerie…) ; sans pack à jour, l’air et le sol se distinguent déjà.

## Fichiers touchés

- `public/assets/js/nato-sidc-icons.js`
- `public/assets/js/atak-map.js`
- `public/assets/js/atak-units.js`
- `public/assets/js/comspec-operational-map.js`
- `public/assets/js/arma-map-markers.js`
- `public/assets/js/milstd-catalog.js`
- `app/Services/Tactical/AtakMotionMath.php`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_bftPlatform.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_updatePosition.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_reportAllyPosition.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_updateVehicleTracking.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp`

## Vérification

- Tests : classification de déplacement (char / VCI comptent comme véhicule terrestre).
- Contrôle code : un opérateur à pied → X ; hélicoptère → H ; char → ovale ; type inconnu → infanterie.
- Vérification visuelle en session (à faire) : monter dans un Hunter, un Slammer, un Ghost Hawk et confirmer le glyphe + la queue de cap.

## Statut

Corrigé (à valider en mission)
