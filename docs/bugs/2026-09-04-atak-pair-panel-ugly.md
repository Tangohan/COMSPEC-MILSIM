# Panneau d’appairage ATAK illisible

## Contexte

Panneau Compte de la carte ATAK, bloc « Appairer un terminal ».

## Symptôme

Encadré vert fluo, titre et bouton violets, champ de code trop étroit.
Le panneau ne ressemblait pas au reste du poste.

## Cause

Le bloc gardait un habillage vert d’une ancienne teinte, alors que le
poste est passé au violet. Les styles du champ de code étaient dans
`app/assets/css/atak.css`, pas dans la feuille réellement servie
(`public/assets/css/atak.css`).

## Correctif

Même surface que le panneau Compte, filet d’accent à gauche, champ de
code sur toute la largeur, bouton lisible (texte clair sur la couleur
du poste).

## Fichiers touchés

- `views/atak.php`
- `public/assets/css/atak.css`
- `app/assets/css/atak.css`
- `tests/Unit/AtakDeviceAuthContractTest.php`

## Vérification

Recharger la carte (Ctrl+F5), ouvrir Compte. Le panneau d’appairage
reprend les couleurs du poste ; le champ de code occupe toute la
largeur.

## Statut

corrigé
