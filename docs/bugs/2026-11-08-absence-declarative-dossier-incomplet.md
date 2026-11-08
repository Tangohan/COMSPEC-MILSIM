# Une absence rendait un dossier « incomplet »

## Contexte
Le site invitait à « compléter les absences » et classait des comptes en dossier incomplet, alors qu'une absence est
seulement déclarative : le membre la déclare quand il est indisponible, rien ne l'oblige à le faire.

## Symptôme
- Tableau de bord de l'organisation, bloc « Profils à compléter » : colonne et filtre « Absence », cellule « Non indiquée ».
- Effectifs, page « À traiter », carte « Dossiers incomplets » : le manque « Absence » comptait dans le pourcentage de la fiche
  et faisait entrer le membre dans la liste.
- Tableur des effectifs et espace RH du membre : le pourcentage de complétion du dossier baissait tant que le « score de
  disponibilité » valait 0.

## Cause
- `PersonnelProfileGapScanService::classifyRow()` posait `missing.absence = !has_active_absence && readiness_score <= 0` :
  tout membre sans absence en cours et sans score de disponibilité était déclaré « absence non indiquée ». Cinq manques
  étaient comptés au lieu de quatre, et un membre n'ayant que ce manque entrait dans la liste.
- Le service alimente le tableau de bord (`OrganizationDashboardController`) et « À traiter » (`RhActionInboxService`).
- `PersonnelDossierCompleteness` comptait aussi « Score de disponibilité renseigné » parmi les champs obligatoires.

## Correctif
- L'exigence est supprimée partout : plus de clé `absence` (clés, libellés, comptages, requête SQL), plus de colonne ni de
  filtre « Absence » sur le tableau de bord, plus de lien « Absences » dans « À traiter ».
- Le score de disponibilité sort de la complétion du dossier.
- Les absences prolongées restent une alerte de suivi (membre absent depuis longtemps), distincte du dossier incomplet.

## Fichiers touchés
- `app/Services/Personnel/PersonnelProfileGapScanService.php`
- `app/Support/PersonnelDossierCompleteness.php`
- `app/Controllers/Admin/Organization/OrganizationDashboardController.php`
- `views/partials/org_dashboard_profile_gaps.php`
- `app/Services/Effectifs/RhActionInboxService.php`
- `tests/Unit/PersonnelProfileGapScanServiceTest.php` (`testAbsenceNeverMakesADossierIncomplete`,
  `testDossierCompletenessIgnoresAvailabilityScore`), `tests/Unit/PersonnelProfileGapDashboardAssetTest.php`

## Vérification
`vendor/bin/phpunit --filter PersonnelProfileGap` : un dossier avec fonction, grade, rôle et image reste complet quelle que
soit la situation d'absence ou de disponibilité.

## Statut
corrigé
