# Carte : bandeau du curseur illisible (« MGRS ALT m17 », « 317° NON ») et app Réseau trop petite

## Contexte

Retour de jeu sur l'ATAK natif 2.9.33 : le bandeau sombre en bas de la carte (case curseur à gauche, case « moi » à
droite) et l'app Réseau.

## Symptôme

- Bandeau : ligne 2 « MGRS 33T XE 1513 1736 · MGRS ALT m17 » (libellé « MGRS » répété, unité avant la valeur), grille à
  10 chiffres affichée deux fois ; à droite « 317° NON » pendant que la gauche affichait « 317° NO » ; la case droite
  (mon cap) n'était pas étiquetée.
- App Réseau : légendes et détails si petits qu'ils étaient illisibles, trop d'informations par ligne.

## Cause

- `fn_mapOverlayUpdate` : le second libellé gris du format de la ligne 2 réutilisait `%1` (« MGRS ») au lieu de « ALT »,
  ce qui décalait tous les arguments suivants.
- « NON » : la case droite posait le point cardinal seul dans sa balise `<t>` ; le texte entier passait par `fn_tr`, et
  en français `i18n/fr.txt` contient `["No", "Non"]` : la recherche en capitales traduisait « NO » en « NON ».
- App Réseau : la page multipliait ses tailles par `fontSmall / (0.024 × safeZoneH)` alors que `fn_fontApply` donne déjà
  au texte structuré la taille du corps : facteur appliqué deux fois, légendes à environ 0,4 du corps, sous
  `COMSPEC_ATAK_FontMin`.

## Correctif

- Nouveau `fn_cardinal` (16 directions, FR / EN), jamais repassé par `fn_tr` ; seuls les libellés du bandeau sont
  traduits, le texte final est posé tel quel. Bandeau refait : grille MGRS complète une seule fois, « ALT 17 m »,
  distance et gisement avec une flèche orientée, « CAP 317° NNO » à droite, passage à la ligne quand la case est étroite.
- App Réseau : facteur retiré, tailles de 0,88 à 1,4 du corps, une information par ligne, sections repliables.

## Fichiers touchés

- `mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/map/fn_cardinal.sqf` (nouveau)
- `mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/map/fn_mapOverlayUpdate.sqf`
- `mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/pages/fn_pageMap.sqf`
- `mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/pages/fn_pageNetwork.sqf`

## Vérification

`hemtt check` : seules les deux erreurs connues (`fn_screenCast`, `fn_slideWebShow`). En jeu : bandeau en français et en
anglais, mesure A-B, téléphone porté, mode mini ; app Réseau en mini et plein écran, taille du texte 0,8 et 1,35.

## Statut

corrigé à vérifier en jeu (ATAK natif 2.9.34)
