# Positions En formation / En service actif mélangées aux fonctions

## Contexte

L’annuaire du back-office (Personnel → Membres) affichait dans la colonne Fonction à la fois le statut de service (« En service actif ») et les métiers (opérateur, instructeur…). D’autres membres n’avaient aucun statut. Grade et section restaient vides. Les organisateurs n’avaient pas de parcours clair à l’arrivée d’un membre.

## Symptôme

- « En service actif » apparaît au début de la liste des fonctions, ou est absent.
- « En formation » n’est pas posé automatiquement à l’arrivée.
- Aucune action simple pour passer un membre en service actif.

## Cause

Les rôles de statut (`status_in_training`, `status_active_duty`) étaient traités comme n’importe quel rôle d’organisation. Rien ne les attribuait à l’arrivée ni à la fin de l’accueil. L’annuaire concaténait tous les rôles dans Fonction, et les colonnes Grade / Section étaient figées à « — ».

## Correctif

- À l’arrivée : position **En formation** (sans retirer les fonctions).
- Accueil terminé : passage automatique en **En service actif**.
- Bouton « Passer en service actif » sur la fiche, et « Attribuer les positions manquantes » sur l’annuaire.
- Colonne Position distincte des fonctions.
- Les membres déjà en service actif ne redescendent pas en formation.

## Fichiers touchés

- `app/Services/Personnel/PersonnelDutyPositionService.php`
- `app/Services/MemberIntegration/MemberIntegrationEntryHook.php`
- `app/Services/MemberIntegration/MemberIntegrationService.php`
- `app/Services/MemberIntegration/MemberIntegrationAutomationService.php`
- `app/Controllers/Admin/Organization/UserAdminController.php`
- `views/admin/organization/users/index.php`
- `views/admin/organization/users/show.php`
- `app/Repositories/UserRepository.php`

## Vérification

- Tests `PersonnelDutyPositionServiceTest` (règle d’exclusivité) et `PersonnelDutyPositionAssetTest` (branchements).
- À l’arrivée d’un membre, l’annuaire doit afficher En formation. Après accueil, En service actif. Les fonctions restent listées à part.

## Statut

corrigé
