# Bug — Appuis n’ouvre plus le panneau

## Contexte

13 septembre 2026. Clic sur Appuis dans le rail gauche du poste. Le domaine ne montre plus la fenêtre 9-Line.

## Symptôme

Le bouton Appuis ne charge pas le module. Panneau vide, ou le titre change sans afficher la 9-Line.

## Cause

Trois effets combinés : le module JTAC était masqué sans la spécialité correspondante, donc Appuis n’avait plus rien à ouvrir ; le changement de domaine ne basculait plus vers JTAC si le filtre d’épinglage renvoyait une liste vide ; l’activation par le rail n’allait pas chercher les 9-Line.

## Correctif

Appuis ouvre toujours le module JTAC. Le poste affiche la 9-Line même sans spécialité JTAC. Au clic, les demandes d’appui et les 9-Line du terrain sont chargées. Un état vide s’affiche s’il n’y a rien en cours.

## Fichiers touchés

- `public/assets/js/atak-section-nav.js`
- `public/assets/js/atak-session-profile.js`
- `public/assets/js/atak-panel-chrome.js`
- `public/assets/js/atak-jtac.js`
- `views/atak.php`

## Vérification

Rechargez le poste, cliquez Appuis : le panneau 9-Line apparaît, avec la liste ou le message « Aucun appui en cours ».

## Statut

Corrigé
