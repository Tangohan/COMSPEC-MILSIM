# Bug — bandeau de grade OF-4 alors que le dossier indique O-5

## Contexte

Dossier personnel, onglet Personnage. Grade communauté Lieutenant-colonel (code OF-4). Le membre a saisi « Lieutenant Colonel » comme titre et « O-5 » comme libellé court (équivalent américain d’un lieutenant-colonel).

## Symptôme

Le bandeau en haut du site affichait « Lieutenant-colonel · OF-4 ». Le libellé court O-5 du dossier n’était pas repris.

## Cause

Le bandeau lisait uniquement le grade attribué par la communauté. Il n’utilisait ni le titre ni le libellé court saisis sur le dossier personnage.

## Correctif

Le bandeau (et la ligne de grade du back-office) prennent d’abord le titre et le libellé court du dossier, puis le grade de communauté s’ils sont vides.

## Fichiers touchés

- `app/Services/GradeDisplayService.php`
- `views/partials/athena_caverne_header.php`
- `views/partials/back_office_sidebar.php`
- `views/personnel/edit.php`
- `tests/Unit/GradeDisplayServiceTest.php`
- `tests/Unit/HeaderGradeOverrideAssetTest.php`

## Vérification

`php -l` sur les fichiers touchés. Instanciation de `GradeDisplayService` : titre « Lieutenant Colonel », code « O-5 ».

## Statut

Corrigé
