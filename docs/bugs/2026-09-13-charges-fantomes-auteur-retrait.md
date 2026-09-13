# Charges ATAK — fantômes après échéance, auteur et retrait

## Contexte

13 septembre 2026. Sur la carte Athena, une charge à retardement restait visible avec « Temps restant : 0s » après échéance. La fiche ne montrait pas qui l’avait posée, et le poste ne pouvait pas la retirer.

## Symptôme

- Marqueur rouge encore cliquable alors que le délai est écoulé (techniquement déclenché).
- Pas d’auteur dans la fiche carte.
- Aucun bouton pour retirer la charge (popup ou menu).

## Cause

1. Affichage carte filtré uniquement sur `status === 'armed'` — une minuterie à 0 s reste « armée » tant que le jeu n’a pas confirmé l’explosion.
2. Le champ `author` existait côté API / panneau Charges, mais pas dans la popup carte.
3. Pas d’action de retrait web (seulement déclenchement pour certaines charges).

## Correctif

1. Masquer sur la carte les charges à retardement dont le temps restant est ≤ 0.
2. Afficher « Posée par … » dans la popup et le survol ; bouton « Retirer de la carte » (statut désamorcée côté poste).
3. Même action au clic droit et dans le panneau Charges ; libellé « Échéance dépassée » dans la liste.

## Fichiers touchés

- `public/assets/js/atak-map.js`
- `public/assets/js/atak-context-menu.js`
- `public/assets/js/atak-explosive-timers.js`
- `public/assets/css/atak.css`
- `docs/bugs/2026-09-13-charges-fantomes-auteur-retrait.md`

## Vérification

1. Poser une charge à retardement courte en jeu → elle apparaît avec auteur.
2. À 0 s → disparaît de la carte ; liste : « Échéance dépassée ».
3. Bouton / clic droit « Retirer de la carte » retire le point côté poste.

## Statut

corrigé (sources web) — à valider après déploiement
