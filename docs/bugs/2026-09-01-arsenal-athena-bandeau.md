# 2026-09-01 — Panneau tenues Athena trop grand à l’arsenal

## Contexte
À l’arsenal ACE, les tenues de la communauté s’ouvrent depuis un bandeau Athena.

## Symptôme
Le bandeau s’affiche dès l’ouverture, trop large, et recouvre le haut de l’arsenal. Le texte d’aide en bas est presque illisible.

## Cause
Le panneau était créé ouvert, calé sur toute la largeur de la colonne centrale, avec une hauteur de titre trop faible pour la taille de texte.

## Correctif
Un petit bouton **Athena** reste seul à l’ouverture. La fenêtre ne s’ouvre que sur clic, plus étroite, collée à côté de Mes équipements, avec des textes lisibles. Fermer range la fenêtre.

## Fichiers touchés
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_arsenalOverlayShow.sqf`
- `docs/utilisateur/equipement-modpacks-atak.md`
- `tests/Unit/ArsenalWardrobeRepositoryTest.php`

## Vérification
Ouvrir l’arsenal : seul le bouton Athena. Clic : fenêtre compacte, textes lisibles. Fermer : l’arsenal redevient dégagé.

## Statut
Corrigé (visible après rechargement du pack)
