# Dossier « Pas d’organisation » affiché en double

## Contexte

Une même personne a un compte plateforme unique et un dossier par communauté. L’espace « Pas d’organisation » sert uniquement d’accueil avant de rejoindre une unité, ou après avoir quitté la dernière.

## Symptôme

Sur le dossier personne (administration du site), une fiche SOAR et une fiche « Pas d’organisation » affichaient les mêmes nom, indicatif, matricule et affectation, avec un second identifiant Athena. Impression de duplication alors que la personne n’a qu’une communauté réelle.

## Cause

1. L’appartenance au tenant système restait active après l’adhésion à une vraie communauté.
2. La fusion des comptes recopiait le dossier métier (grade, indicatif, fiche RH) sur cet espace d’accueil.
3. L’affectation principale était lue sans tenir compte de la communauté, donc la même unité apparaissait sur les deux cartes.

## Correctif

- Dès qu’une communauté réelle existe, l’espace sans organisation n’est plus affiché sur le dossier personne. Il reste visible pour les comptes orphelins.
- Rejoindre une communauté, ou fusionner les comptes, désactive cet espace et n’y recopie plus le dossier métier.
- L’affectation affichée sur un dossier est celle de la communauté concernée. Steam et l’identité civile restent sur l’identité plateforme, pas recopiés sur chaque carte.

## Fichiers touchés

- `app/Support/PortalAccessChoice.php`
- `app/Controllers/Admin/System/SystemUsersController.php`
- `views/admin/system/user_person.php`
- `app/Repositories/UserCommunityMembershipRepository.php`
- `app/Repositories/UserRepository.php`
- `app/Repositories/PersonnelAssignmentRepository.php`
- `app/Services/Identity/UserIdentityMergeService.php`
- `app/Services/Identity/UserIdentityProfileRestoreService.php`
- `bootstrap/user_community_identity_migration.php`

## Vérification

- Tests unitaires : masquage du dossier d’accueil si une communauté réelle existe ; conservation pour un orphelin ; pas de recopie RH vers l’espace sans organisation.
- Tests d’assemblage : adhésion et fusion appellent la sortie de l’espace d’accueil.
- Parcours manuel : ouvrir le dossier d’une personne membre d’une communauté ; seul ce dossier doit apparaître.

## Statut

Corrigé (appliquer la mise à jour du portail).
