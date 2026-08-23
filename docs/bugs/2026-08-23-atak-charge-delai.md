# ATAK — délai de charge affiché à 1 s

## Contexte

Carte ATAK d’une Stick Charge posée à retardement : le délai affiché
était **1 s**, le temps restant **0 s**, alors que le joueur avait réglé
la minuterie ACE (souvent 30 s ou plus). Le bouton Déclencher pouvait
aussi afficher « pas encore disponible sur cette communauté ».

## Symptôme

- Délai : 1 s au lieu de la valeur choisie dans ACE.
- Temps restant : 0 s alors que la charge est encore armée en jeu.
- Libellé « À retardement » sur une pose qui n’avait pas encore de
  minuterie réelle.

## Cause

1. L’événement ACE `ace_explosives_setup` part au moment du **placeholder**
   (avant le choix Timer / déclencheur). Aucun délai n’existe encore : on
   envoyait fuse 0.
2. Côté Athena, un fuse 0 en mode minuterie était **forcé à 1 s**, et le
   compte à rebours partait tout de suite (`NOW() + 1 s`).
3. La pose réelle (curseur ACE, 5–900 s) n’était pas toujours renvoyée :
   le wrap `startTimer` ratait le délai si ACE passait un tableau, et le
   wrap `placeExplosive` **ignorait** les minuteries.

Le bandeau « déclenchement pas encore disponible » est un autre point :
le bouton Déclencher s’affichait alors que les colonnes de commande
n’étaient pas encore en base.

## Correctif

- Ne plus remonter le placeholder ACE.
- Lire le délai dans les arguments de pose (`[_time]`) et dans `startTimer`,
  y compris si le 2ᵉ argument est un tableau.
- Ne plus inventer 1 s quand le délai est inconnu.
- N’afficher un délai que pour une vraie minuterie.
- Masquer Déclencher tant que la communauté n’a pas les colonnes requises.

## Fichiers touchés

- `connect/functions/fn_initExplosiveTimers.sqf`
- `connect/functions/fn_reportExplosiveTimer.sqf`
- `connect/config.cpp` (1.4.45)
- `app/Repositories/AtakExplosiveTimerRepository.php`
- `app/Controllers/Api/AtakApiController.php`
- `public/assets/js/atak-explosive-timers.js`
- `storage/app_version.json` (1.5.16)

## Vérification

- Contrôle statique SQF (délai lu sur les vars de pose, plus d’événement setup).
- Rebuild `connect.pbo` 1.4.45, copie vers le dossier mod Arma.
- Recette in-game : poser une Stick Charge à retardement (ex. 30 s) → la
  carte ATAK doit afficher ce délai, pas 1 s.

## Statut

Corrigé — à valider en jeu après relance complète d’Arma, et à déployer
côté portail Athena (1.5.16).
