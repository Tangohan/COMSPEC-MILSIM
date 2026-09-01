# Listes Serveur / Carte illisibles sur la carte ATAK

## Contexte

Bandeau de https://athena.ttrd.fr/public/atak, liste **Serveur** (et Carte / Communauté).

## Symptôme

Liste ouverte : fond gris clair, texte blanc. Seule la ligne survolée (Altis) se lit. Stratis, Malden, Tanoa, etc. sont invisibles.

## Cause

Le champ a un texte clair pour le bandeau sombre. Sous Windows, la liste native reste claire et hérite de ce texte.

## Correctif

La page et les listes du bandeau demandent un rendu sombre. Les lignes ont un fond charbon et un texte clair.

## Fichiers touchés

- `public/assets/css/atak.css`
- `tests/Unit/AtakHeaderSelectContrastAssetTest.php`

## Vérification

Ouvrir Serveur sur la carte du poste : chaque théâtre se lit sans survol.

## Statut

Corrigé
