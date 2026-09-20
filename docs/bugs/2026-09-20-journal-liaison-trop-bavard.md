# Journal de liaison trop bavard

## Contexte

20 septembre 2026. Session Overwatch avec téléphone en liaison. Le fichier journal se remplit toutes les quelques secondes de lignes identiques : retours formes / messages / ordres / marqueurs à zéro.

## Symptôme

Le journal (fichier et RPT) répète en boucle le même état vide, même quand rien ne change.

## Cause

Chaque lecture vers le poste écrivait une ligne, y compris quand le résultat était identique à la lecture précédente (zéro message, zéro forme, etc.).

## Correctif

Une ligne n’est écrite que si le résultat change. En dépannage, le même résultat peut encore être rappelé, au plus une fois par minute.

Pack : Overwatch **1.6.5**. Relancer Arma complètement.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_noteUplinkReturn.sqf`

## Vérification

1. Quitter Arma. Recharger Overwatch 1.6.5.
2. Entrer en session avec le téléphone.
3. Le journal montre au plus une ligne par type au démarrage. Pas de répétition toutes les cinq secondes tant que rien ne change.

## Statut

corrigé (Overwatch 1.6.5)
