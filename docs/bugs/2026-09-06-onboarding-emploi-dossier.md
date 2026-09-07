# Intégration recrutement : emploi du dossier absent

## Contexte

Parcours guidé après acceptation (`/back-office/recruitments/{id}/onboarding`), étape 4 « Affectation ».

## Symptôme

L’écran proposait l’unité et une « fonction RH » (liste catalogue, souvent un chemin du type Renseignement › Exploitation technique), plus un libellé d’affectation optionnel vide. Impossible de poser clairement un **emploi du dossier** (celui de l’organigramme, ou le titre de l’offre).

## Cause

Le champ `personnel_job_role_id` était libellé « Fonction RH » et affiché avec le chemin de catégories du ancien catalogue. Le libellé d’affectation n’était pas prérempli, et un nom saisi ne créait pas d’emploi.

## Correctif

- Libellé **Emploi**, liste sans chemin catalogue.
- Champ **Nouvel emploi** si l’intitulé n’est pas encore dans la liste (prérempli avec le titre de l’offre quand aucun emploi n’est lié).
- À l’enregistrement : emploi choisi, ou emploi créé depuis le nom saisi, ou emploi lié à l’unité.

## Fichiers touchés

- `views/admin/recruitments/onboarding.php`
- `app/Controllers/Admin/AdminRecruitmentsController.php`
- `app/Services/Recruitment/EnlistmentAcceptanceProvisioningService.php`

## Vérification

- Test `EnlistmentAcceptanceOnboardingAssetTest`
- Parcours : candidature acceptée → Intégration → étape Affectation → Emploi + Nouvel emploi

## Statut

Corrigé.
