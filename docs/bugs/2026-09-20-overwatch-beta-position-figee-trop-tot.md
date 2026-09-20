# Overwatch Beta : « position figée » trop tôt

## Contexte

Poste Overwatch Beta, opérateur encore en liaison et en forme (pastille verte, téléphone ATAK allumé). La position continue d’arriver, parfois avec un léger retard de synchro, ou l’opérateur reste quelques secondes sur place.

## Symptôme

Le bandeau rouge « Positions figées — ce n’est plus du temps réel » s’affichait au bout d’une dizaine de secondes. Le cadre de l’indicatif passait aussi en pointillés (ambre puis rouge) alors que l’opérateur n’avait rien perdu.

## Cause

Le bandeau traitait comme figée toute position de plus de 20 secondes, ou toute pause de réception de plus de 12 secondes. Un opérateur immobile, un cycle de synchro un peu plus long, ou un réglage de rafraîchissement à 15 ou 30 secondes suffisait à allumer l’alerte. Un seul opérateur un peu en retard déclenchait le bandeau pour tout le théâtre.

La liaison réelle, elle, ne passe en différé qu’après plus d’une minute, et hors liaison après deux minutes.

## Correctif

1. Le bandeau n’apparaît que si **tous** les opérateurs encore vus récemment ont dépassé le seuil différé (plus d’une minute), ou si le poste n’a plus reçu le théâtre depuis longtemps (au moins 45 secondes, davantage si le rafraîchissement est plus lent).
2. Le cadre en pointillés n’entoure un indicatif que dans les mêmes délais.
3. Un opérateur encore en liaison n’est plus marqué « relais » au seul motif d’un léger retard.

## Fichiers touchés

- `public/assets/js/atak-overwatch-c2.js`
- `public/assets/js/atak-overwatch-beta.js`

## Vérification

Recharger Overwatch Beta (Ctrl+F5). Un opérateur en ligne et en forme, même immobile quelques secondes, reste sans bandeau rouge et sans cadre en pointillés. Le bandeau n’apparaît que si plus personne n’est à jour, ou si le poste n’entend plus le théâtre.

## Statut

Corrigé
