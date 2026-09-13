# Édition marqueur — libellé Arma non prérempli

## Contexte

13 septembre 2026. Clic droit → Modifier sur un repère remonté du jeu ouvrait le formulaire avec « Marqueur » générique, sans le texte d’origine ni les données Arma.

## Symptôme

- Champ Libellé = « Marqueur » au lieu de « Parcours Bleu » (ou autre texte carte).
- Enregistrer risquait d’écraser le type / la couleur Arma par un « repère simple ».

## Cause

1. Le formulaire lisait seulement `data.label` / `symbolName`, alors que les marqueurs jeu stockent le nom dans `data.text` (et l’indicatif dans `callsign`).
2. La sauvegarde appliquait toujours le patch « épingle simple », sans branche dédiée aux marqueurs Arma.

## Correctif

1. Préremplir via `labelOf` / `text` / `label` / `name`.
2. Pour un marqueur Arma : n’enregistrer que libellé (`text` + `label`) et description, sans écraser type / couleur / forme.
3. Renommer met à jour `text` et `label` ; popup affiche « Posé par » depuis `callsign` et propose Supprimer.

## Fichiers touchés

- `public/assets/js/atak-context-menu.js`
- `public/assets/js/atak-map.js`
- `docs/bugs/2026-09-13-edition-marqueur-libelle-arma.md`

## Vérification

1. Repère nommé en jeu → Modifier : le libellé d’origine est dans le champ.
2. Enregistrer un autre nom → la carte garde l’icône Arma, seul le texte change.
3. Popup : auteur visible si callsign présent ; bouton Supprimer fonctionne.

## Statut

corrigé (sources web) — à valider après déploiement
