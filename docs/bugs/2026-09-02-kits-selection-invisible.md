# Bug — Kits d’accès : cartes sans case ni compteur

## Contexte

Page **Kits d’accès** (`/public/back-office/personnel-job-roles/kits`). Les
responsables cochent des packs (lecture, modification, recrutement, paramètres)
puis enregistrent pour les attribuer aux membres.

## Symptôme

Cliquer une carte ne changeait rien de visible : toujours « Disponible », aucune
case à cocher, pied de page figé sur « Aucun kit coché pour l’instant. »
L’instruction « Cochez au moins un kit puis enregistrez… » restait affichée.

## Cause

La case était étirée sur toute la carte et rendue invisible (`opacity: 0`). Le
texte « Disponible » et le compteur du pied de page étaient calculés au
chargement uniquement. Sans retour visuel, la sélection paraissait inerte.

## Correctif

Chaque carte affiche une case à cocher. Une carte retenue passe en
« Sélectionné » avec un fond et un contour distincts. Le pied de page compte
les kits cochés au fur et à mesure. L’enregistrement envoie toujours les kits
retenus.

## Fichiers touchés

- `views/admin/organization/personnel_job_roles/kits.php`
- `public/assets/css/back-office-catalog.css`
- `public/assets/js/bo-kits-selection.js`
- `tests/Unit/PersonnelFunctionKitAssetTest.php`

## Vérification

`php vendor/bin/phpunit tests/Unit/PersonnelFunctionKitAssetTest.php tests/Unit/DevDispatchCatalogTest.php`

Ouvrir Kits d’accès, cocher deux cartes : cases visibles, libellé
« Sélectionné », pied de page « 2 kits sélectionnés ». Enregistrer, puis
attribuer un kit à un membre.

## Statut

corrigé
