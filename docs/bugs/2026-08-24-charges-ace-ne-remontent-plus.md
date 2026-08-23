# Charges ACE qui ne remontent plus sur ATAK

## Contexte

Après le correctif du délai affiché à 1 s, poser une charge ACE
(minuterie, clacker, téléphone) n’envoyait plus rien vers la carte ATAK.

## Symptôme

La charge est bien posée en jeu. L’onglet Charges / la carte reste vide.
Le journal Overwatch peut quand même afficher « Suivi des charges ACE actif ».

## Cause

Les fonctions ACE `placeExplosive` / `startTimer` sont compilées en
`compileFinal`. Les remplacer par un wrap Overwatch est **ignoré** par le
moteur : aucune pose n’était interceptée.

L’écoute de l’événement `ace_explosives_setup` avait été retirée (c’était
le placeholder, avant le choix du déclencheur). Plus aucun canal ne
remontait donc la pose réelle.

## Correctif

Écouter les événements ACE officiels, sans wrap :

- `ace_explosives_setup` : mémoriser le type de munition, ne rien envoyer
- `ace_explosives_place` : charge armée → envoi Athena
- `ace_explosives_defuse` : désamorçage (inchangé)
- curseur du minuteur ACE (contrôle 8505) pour le délai réel

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_initExplosiveTimers.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp` (1.4.53)

## Vérification

Contrôle du code. À retester en mission : Stick Charge à retardement
(ex. 30 s) et charge au clacker → elles doivent réapparaître dans
Charges / sur la carte. Rebuild du PBO `connect` et relance d’Arma.

## Statut

Corrigé (sources) — rebuild Overwatch 1.4.53 requis
