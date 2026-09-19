# ATAK — icônes d’applications identiques

## Contexte

Dans le chevron, Athena, Briefing et Tutoriel / WIKI affichaient la même bulle d’exclamation. Messagerie reprenait aussi l’antenne de P2P.

## Symptôme

Impossible de distinguer plusieurs applications au pictogramme : il fallait lire le nom.

## Cause

Trois tuiles Athena réutilisaient le même pictogramme générique. Messagerie réutilisait l’icône radio.

## Correctif

Pictogrammes distincts : tablette (Athena), presse-papiers (Briefing), livre (Tutoriel), bulles (Messagerie), cible (BDA).

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/data/icons/`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp` (1.0.147)

## Vérification

Quitter Arma complètement. Recharger Athena 1.0.147. Chevron : Athena, Briefing, Tutoriel et Messagerie ont des icônes différentes.

## Statut

Corrigé
