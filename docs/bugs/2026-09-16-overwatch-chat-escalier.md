# Tchat Overwatch : messages décalés à droite

## Contexte

Onglet Comms, fil de messages d’un canal (exemple : conversation sur plusieurs jours).

## Symptôme

À chaque changement de jour, le bloc suivant était indenté d’un cran vers la droite, comme un escalier. Le fil n’était plus aligné à gauche.

## Cause

Un séparateur de jour ou un message système fermait le regroupement « locuteur » dans la logique, mais le bloc HTML ouvert n’était pas refermé. Le groupe suivant s’imbriquait dedans.

## Correctif

Le groupe ouvert est refermé avant un séparateur de jour, un message système, et un nouveau locuteur. L’aperçu brut du message reste dans la ligne.

## Fichiers touchés

- `public/assets/js/atak-overwatch-beta.js`

## Vérification

Un fil avec plusieurs dates reste aligné à gauche. Un message système n’indente plus les messages suivants.

## Statut

corrigé
