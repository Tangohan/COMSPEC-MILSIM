# Grade et portrait opérateur — effectifs

## Contexte

Tableau rapide Effectifs et bandeau Rang de la fiche personnel.

## Symptôme

- La colonne **Grade** affichait le rôle d’accès (ex. « Administrateur système ») ou un code court (ex. « O-5 ») au lieu du grade attribué (ex. « Colonel »).
- La photo de la colonne Membre était la photo de compte, trop petite, et non le portrait opérateur.

## Cause

L’affichage préférait `rank_display_override` puis `rank_display` (titre libre / code bandeau) au grade du référentiel. Le tableau lisait `avatar_url` sans le portrait opérateur.

## Correctif

- Libellé Grade / Rang = grade attribué (`label_long`, puis `label_short`).
- Portrait opérateur en priorité dans le tableau, l’annuaire et le bureau effectifs.
- Le code court reste en sous-ligne sur la fiche, pas à la place du grade.

## Fichiers touchés

- `app/Support/helpers.php`
- `app/Repositories/UserRepository.php`
- `views/partials/dashboard_effectifs_table.php`
- `views/personnel/directory.php`
- `views/personnel/file.php`
- `views/partials/personnel/file_tableau_admin_tab.php`
- `views/admin/effectifs_workspace/roster.php`

## Vérification

Tests unitaires `PersonnelAssignedGradeAndPortraitTest` : Colonel reste le libellé même si le rôle et O-5 sont renseignés ; le portrait opérateur est choisi avant la photo de compte.

## Statut

corrigé
