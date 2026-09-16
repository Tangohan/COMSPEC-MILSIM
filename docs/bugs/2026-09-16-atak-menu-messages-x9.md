# Menu qui se referme, messages empilés, arrêt avec le téléphone ouvert

## Contexte

Athena 1.0.141, téléphone ouvert (aperçu Altis, 20:05). Bandeau en bas de
carte « TA1 (TOC) — test (x9) ». Ouverture du menu d’applications : il
disparaît tout de suite. Arrêt brutal à 20:06:10.

## Symptôme

1. Les messages de test du poste s’affichent en bandeau sur la carte,
   avec un compteur (x9) au lieu de rester dans la messagerie.
2. Le menu d’applications s’ouvre puis se referme immédiatement.
3. Le jeu se ferme tout seul une dizaine de secondes après l’ouverture
   du téléphone (images par seconde 59 → 3, puis arrêt).

## Cause

IceMan verrouille ses fonctions de calage : Athena ne peut pas les
remplacer. Le chevron IceMan referme le menu en passant sa largeur à
zéro, ce qui arrête le moteur. Athena masquait encore le menu toutes
les demi-secondes, donc il ne restait pas ouvert.

Les messages du poste étaient aussi recopiés en bandeau sur la carte.
Le même texte « test » était empilé à chaque passage, d’où le (x9).

## Correctif

- Le chevron Athena ouvre et ferme le menu à l’écran, sans demander à
  IceMan de changer la largeur.
- IceMan est maintenu en calage « ouvert » pour ne jamais écrire une
  largeur nulle.
- Les messages du poste restent dans la messagerie. Ils ne s’empilent
  plus en bandeau sur la carte. Un texte trop long est raccourci.

## Fichiers touchés

- `atak_athena/functions/fn_athena_enforceDrawer.sqf`
- `atak_athena/functions/fn_athena_toggleDrawer.sqf`
- `atak_athena/XEH_postInitClient.sqf`
- `atak_athena/config.cpp` (Athena 1.0.142)
- `connect/functions/fn_pollChatMessages.sqf`

## Vérification

1. Quitter Arma complètement, recharger le pack (Athena 1.0.142).
2. Ouvrir le téléphone : le menu reste fermé.
3. Ouvrir le menu au chevron : il reste ouvert. Le refermer : il reste
   fermé.
4. Envoyer un message « test » depuis le poste : il arrive dans la
   messagerie, pas en bandeau (x9) sur la carte.
5. Laisser le téléphone ouvert plus d’une minute : le jeu reste ouvert.

## Statut

Corrigé côté sources (Athena 1.0.142, à valider in-game après relance).
