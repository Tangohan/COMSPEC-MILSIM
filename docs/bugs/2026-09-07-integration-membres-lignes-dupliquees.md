# Intégration — le même membre répété dans le tableau

## Contexte

Page **Intégration des nouveaux membres** (`/back-office/integration-membres`), onglet Tableau.

## Symptôme

Une vingtaine de lignes identiques pour la même personne (même statut, même étape, même progression). Le tableau devrait lister une ligne par arrivée suivie.

## Cause

Deux effets se cumulaient :

1. La liste joignait le dossier personnel sans borner à une seule fiche. Sur les communautés où plusieurs dossiers existent pour le même compte, chaque parcours était recopié autant de fois.
2. Un suivi ouvert pouvait être recréé (clé d’unicité non renseignée à l’enregistrement). Plusieurs suivis ouverts pour la même personne apparaissaient alors comme autant de lignes.

## Correctif

- Une seule fiche de dossier est jointe pour le tableau et la fiche.
- À l’ouverture de la page, s’il reste plusieurs suivis ouverts pour la même personne, seul le plus récent est conservé.
- Le nouvel enregistrement d’un suivi renseigne la clé d’unicité pour empêcher un second suivi ouvert.

## Fichiers touchés

- `app/Repositories/MemberIntegrationRepository.php`
- `app/Services/MemberIntegration/MemberIntegrationService.php`
- `app/Services/MemberIntegration/MemberIntegrationAutomationService.php`
- `app/Controllers/Admin/MemberIntegrationAdminController.php`
- `tests/Unit/MemberIntegrationRepositorySchemaTest.php`

## Vérification

- Tests : une fiche en double ne multiplie plus le tableau ; trois suivis ouverts pour le même membre n’en laissent qu’un.
- Contrôle visuel : ouvrir Intégration des nouveaux membres — chaque personne n’occupe qu’une ligne.

## Statut

corrigé
