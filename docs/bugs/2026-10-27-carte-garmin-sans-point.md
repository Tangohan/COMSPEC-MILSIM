# Carte de la montre sans point ni alliés

## Contexte

La carte de la montre (vue agrandie) montrait le terrain et la grille, au même endroit que le téléphone, mais aucun symbole. Le téléphone, à côté, affichait bien « MOI » et le suivi.

## Symptôme

Cadran carte : photo satellite, route, grille et largeur. Pas de point personnel, pas d’allié.

## Cause

Le dessin posait les icônes à la position GPS avec son altitude. Sur cette carte, un point qui garde sa hauteur sort du cadran. Le centrage, lui, n’utilise que le plan, d’où une carte bien placée et des symboles invisibles. En plus, seuls les équipiers du groupe étaient dessinés, pas les alliés du suivi, et la flèche orange se confondait avec les routes.

## Correctif

`fn_watchMapDraw` remet l’altitude à 0, dessine mon point (cercle et cap, couleur « moi » du téléphone) et les alliés du suivi (mêmes filtres, mêmes symboles, indicatif aux zooms proches). La montre relit le suivi quand le téléphone est rangé. Le cadran affiche le cap et le nombre d’alliés.

## Fichiers touchés

- `mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/garmin/fn_watchMapDraw.sqf`
- `mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/garmin/fn_watch.sqf`
- `mod/COMSPEC_ATAK_Native/Sources/addons/main/config.cpp`

## Vérification

À faire en jeu après avoir quitté Arma complètement : carte de la montre, point au centre, alliés du suivi autour, cap en haut, nombre d’alliés en bas. Comparer avec la carte du téléphone.

## Statut

Corrigé dans les sources (2.9.0). Pas encore confirmé en jeu.
