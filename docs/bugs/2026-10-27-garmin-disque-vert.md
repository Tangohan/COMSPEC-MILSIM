# Montre Garmin — disque vert sans boîtier

## Contexte

Montre ouverte avec la touche Montre du jeu, sur le pack publié.

## Symptôme

À la place de la montre, un disque vert uni en bas à gauche. Pas de boîtier, pas d’heure, pas d’icônes.

## Cause

Les images de la montre utilisaient la même classe que les icônes du téléphone, teintée en vert. La couleur et la texture étaient posées après la validation du contrôle : sur cette couche, la validation n’est pas reprise. Le disque blanc restait donc vert (la couleur de la classe), et le boîtier 1024, absent de la couche au départ, ne s’affichait pas. Les icônes blanches, teintées du même vert, disparaissaient sur ce disque.

## Correctif

Classe dédiée à la montre, blanche. Couleur et texture validées ensemble. Les quatre boîtiers sont déclarés sur la couche pour que le jeu les charge, y compris le coloris porté.

## Fichiers touchés

- `mod/COMSPEC_ATAK_Native/Sources/addons/main/ui/controls.hpp`
- `mod/COMSPEC_ATAK_Native/Sources/addons/main/ui/displays.hpp`
- `mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/garmin/fn_watch.sqf`

## Vérification

La couleur du disque sur la capture correspond exactement au vert de la classe du téléphone, et aucun pixel sombre de boîtier n’entoure le cercle. Le correctif aligne la classe, la validation et le chargement du boîtier. À confirmer en jeu après publication du pack : boîtier visible, cadran lisible, les quatre coloris.

## Statut

Corrigé dans les sources. Visible en jeu après reconstruction et publication du pack.

## Suite — la touche n’ouvre rien (même jour)

Le correctif du disque vert a fait planter le dessin de la montre. Dans le journal du 27/10 à 13:21, `fn_watch.sqf` ligne 631 : `call` reçoit un contrôle au lieu du calcul de couleur, puis l’erreur se répète à chaque dixième de seconde. À l’écran, O et Ctrl+Maj+O ne montrent rien.

Cause : le script ne distingue pas les majuscules. La fonction de couleur s’appelait `_C`, et le contrôle créé juste avant s’appelait `_c`. Dans le bloc de dessin, les deux noms désignent la même variable, donc la couleur appelle le contrôle.

Correctif : la fonction de couleur s’appelle `_tone`. Fichier : `fn_watch.sqf`. À confirmer en jeu : appui bref sur O, et Ctrl+Maj+O pour la vue agrandie.
