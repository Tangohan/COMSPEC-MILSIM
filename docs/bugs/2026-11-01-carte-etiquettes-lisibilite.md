# Carte : étiquettes qui se chevauchent, caps à 2 chiffres, marqueurs noirs invisibles sur fond sombre

## Contexte

Demande de refonte de la lisibilité de la carte de l'ATAK natif (S7 par défaut, jour et nuit). En relisant le dessin de
la carte, plusieurs défauts concrets sont apparus.

## Symptôme

- Les cartouches des unités recouvraient les noms des marqueurs (et l'inverse) ; mon propre symbole pouvait être caché.
- Azimut des cartouches et des noms de marqueurs sans zéro devant (« 48 m · 34° » au lieu de « 034° »).
- Heure des contacts ENI illisible : « 150179 » (heures, minutes, secondes collées).
- Noms de marqueurs et indicatifs coupés par le bord droit de la carte.
- Marqueur noir (ColorBlack) invisible sur les fonds SOMBRE, NUIT et satellite ; liseré noir invisible sur la plaque sombre.
- Anneau de sélection vert, confondu avec la couleur « neutre » de l'OTAN.

## Cause

- Chaque calque d'étiquettes (`fn_mapLabels`, `fn_mapMarkerText`, `fn_mapPlaceText`) évitait seulement ses propres textes.
- Formats écrits à la main dans chaque fichier, sans `CBA_fnc_formatNumber` pour le cap.
- Pas de test de bord à droite ; couleurs codées en dur, dispersées, sans variante pour fond sombre.

## Correctif

Table de couleurs unique `fn_mapColors` (teintes APP-6 adaptées au fond et au mode nuit, couleurs Arma trop sombres
éclaircies sur fond sombre), format commun `fn_mapFmt`, anti-chevauchement partagé par grille grossière `fn_mapLblGrid`
(cartouches > noms des repères > lieux, mon symbole et la sélection protégés), bascule à gauche ou abandon au bord de la
carte, heure ENI « 15:01 », sélection blanche cernée de noir.

## Fichiers touchés

`mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/` : map/fn_mapColors (nouveau), map/fn_mapFmt (nouveau),
map/fn_mapLblGrid (nouveau), map/fn_mapOnDraw, map/fn_mapLabels, map/fn_mapMarkerText, map/fn_mapPlaceText,
map/fn_mapGameMarkers, map/fn_symbology, pages/fn_pageMap, pages/fn_pageSettings ; config.cpp ; i18n/en.txt

## Vérification

`hemtt check` sans nouvelle erreur. En jeu (S7) : carte en main et portée, fonds TOPO / SOMBRE / NUIT, mode nuit ROUGE et
SOMBRE ; zoom avant / arrière avec beaucoup d'unités et de marqueurs ; marqueur noir sur fond sombre ; volet
CONFIGURATION > Étiquettes (taille, fond, chaque type masqué).

## Statut

corrigé à vérifier en jeu (2.9.22)
