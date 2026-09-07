# Fiche : écran de choix avant le dossier

## Contexte

Après le rangement Effectifs / Organisation / Emplois, ouvrir une fiche depuis le portail affichait encore un écran intermédiaire pour les responsables : « Vue publique » ou « Dossier RH complet ».

## Symptôme

Un responsable qui ouvrait `/personnel/{id}` tombait sur un choix, pas sur le dossier. « Changer de vue » renvoyait au même écran. La fiche n’était pas une page avec deux vues, mais trois portes.

## Cause

La vue sans paramètre était réservée au gate. La fiche et la vue commandement n’existaient qu’après un clic.

## Correctif

Sans paramètre, la fiche s’affiche. Un sélecteur Fiche / Commandement est posé sur le bandeau pour qui a le droit. `?view=rh` ouvre toujours la vue commandement. Le poste Effectifs reste le lieu des mutations, avec un raccourci « Voir la fiche ».

## Fichiers touchés

- `views/personnel/file.php`
- `views/partials/personnel/file_view_switcher.php`
- `views/partials/personnel/file_rh_view.php`
- `views/partials/personnel/file_page_notices.php`
- `app/Controllers/Web/PersonnelController.php`
- `views/admin/effectifs_workspace/member.php`

## Vérification

- Contrôle syntaxe PHP
- Tests `PersonnelRhViewAssetTest`, `PersonnelPublicFileHeroAssetTest`, `PersonnelFileHubAssetTest`, `DevDispatchCatalogTest`

## Statut

Corrigé
