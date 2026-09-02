# Avancement du dossier : second « Prénom et nom » toujours à compléter

## Contexte

Fiche personnel, bloc **Avancement du dossier**. Identité du personnage
déjà renseignée (prénom et nom).

## Symptôme

Deux lignes :
- **Prénom et nom du personnage** — coché
- **Prénom et nom — à compléter** — toujours en alerte

## Cause

La liste d’avancement gardait un point issu de l’ancienne identité
civile. Ce point n’existe plus dans le calcul : il s’affichait donc
toujours comme manquant, même avec le nom du personnage rempli.

## Correctif

Ce point est retiré. Seul **Prénom et nom du personnage** reste, aligné
sur l’identité actuelle. Les points absents du calcul ne s’affichent
plus.

## Fichiers touchés

- `views/personnel/file.php`
- `tests/Unit/PersonnelRpIdentityAssetTest.php`

## Vérification

Ouvrir une fiche avec prénom et nom renseignés : un seul point identité,
coché. Plus de « Prénom et nom — à compléter ».

## Statut

corrigé
