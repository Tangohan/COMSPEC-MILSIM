# Menus INTEL Fiches / Personnes trop discrets

## Contexte

Sur le poste ATAK, domaine INTEL / Renseignement, la liste des modules (Fiches, Photos, Personnes, FRS) était gris sur fond sombre. L’œil ne distinguait pas les entrées, y compris celle déjà sélectionnée.

## Symptôme

Fiches et Personnes se confondaient avec le fond. La barre verte de sélection et le texte gris clair ne suffisaient pas à lire le menu d’un coup d’œil.

## Cause

Les boutons du tiroir gauche n’avaient pas de couleur propre : fond transparent, filet gauche transparent, titres gris `#a7b4bc` en 13 px.

## Correctif

Chaque module INTEL a une teinte distincte (ambre Fiches, cyan Personnes, vert Photos, violet FRS), un filet coloré même au repos, et un titre plus grand.

## Fichiers touchés

- `views/atak.php`
- `public/assets/css/atak-c2-shell.css`

## Vérification

Test `AtakIntelModuleColorAssetTest`. Recharger le poste ATAK, ouvrir INTEL : Fiches ambre, Personnes cyan, lisibles hors sélection.

## Statut

corrigé
