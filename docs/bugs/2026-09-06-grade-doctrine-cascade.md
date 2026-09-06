# Grades figés malgré la doctrine

## Contexte

Formulaire **Nouvel utilisateur** (et fiche membre) : nationalité / doctrine, catégorie de personnel, grade.

## Symptôme

Choisir **Américain** ne changeait pas les listes. La catégorie et le grade restaient vides, ou continuaient d’afficher les grades français (Capitaine, Sergent…).

## Cause

Le formulaire ne chargeait que le référentiel de la communauté (souvent français). Aucun enchaînement ne filtrait catégorie et grade selon la doctrine.

## Correctif

- Les deux doctrines (française et américaine) sont proposées dans le formulaire.
- En choisissant Français ou Américain, la catégorie et le grade se mettent à jour.
- L’enregistrement accepte un grade de la doctrine choisie.

## Fichiers touchés

- `public/assets/js/grade-doctrine-cascade.js`
- `views/admin/organization/partials/user_invite_form_fields.php`
- `views/admin/organization/users/edit.php`
- `views/admin/system/user_edit.php`
- `app/Repositories/GradeRepository.php`
- `app/Controllers/Admin/Organization/UserAdminController.php`
- `app/Controllers/Admin/Organization/OrganizationDashboardController.php`
- `app/Services/Admin/PlatformUserProfileService.php`

## Vérification

- Test `GradeDoctrineCascadeAssetTest`
- Ouvrir Nouvel utilisateur, choisir Américain : grades américains. Choisir Français : grades français.

## Statut

Corrigé.
