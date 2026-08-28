# Bug — prénom, nom et bio traités comme hors jeu

## Contexte

Édition du dossier personnel, onglet Compte & interface. Les champs Prénom, Nom et Présentation courte étaient présentés comme une identité nominative hors personnage.

## Symptôme

Un membre qui renseignait son prénom, son nom et sa présentation avait l’impression de remplir une identité civile (hors jeu). Ces champs n’apparaissaient pas avec le reste du personnage, et la case de confidentialité les masquait comme des données personnelles.

## Cause

Les trois champs vivaient dans l’onglet Compte, avec des textes « hors personnage » / « vues nominatives ». L’enregistrement recopiait aussi prénom et nom dans le magasin d’identité légale, et la fiche les affichait sous « Identité civile ».

## Correctif

- Déplacer prénom, nom et présentation vers l’onglet Personnage, libellés de jeu.
- L’onglet Compte ne garde que le fuseau, la langue, et le masquage de ces deux réglages.
- La fiche et le tableau administratif les montrent comme identité de personnage.
- L’enregistrement ne recopie plus ces champs dans l’identité légale.

## Fichiers touchés

- `views/personnel/edit.php`
- `views/personnel/file.php`
- `views/partials/personnel/file_tableau_admin_tab.php`
- `views/account/preferences.php`
- `views/account/index.php`
- `app/Controllers/Web/PersonnelController.php`
- `app/Controllers/Web/AccountController.php`
- `app/Services/Admin/ProfileCompletenessService.php`
- `tests/Unit/PersonnelRpIdentityAssetTest.php`

## Vérification

`php -l` sur les contrôleurs et vues touchés ; `phpunit tests/Unit/PersonnelRpIdentityAssetTest.php`.

## Statut

Corrigé
