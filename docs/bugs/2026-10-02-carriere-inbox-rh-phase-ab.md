# Carrière unifiée et boîte de travail RH — phase A+B

## Contexte

Le module Effectifs disposait déjà des briques carrière (journal de service, awards, ancienneté, corrections, élévations, alertes) mais elles restaient fragmentées : plusieurs frises, peu d’écritures dans `personnel_service_history`, et pas de file d’actions unique pour le RH.

## Symptôme

- Le joueur voyait une timeline incomplète (sans journal de service).
- Le RH ouvrait plusieurs écrans (corrections, élévations, alertes, dossiers) sans vue « décisions à prendre ».

## Cause

Agrégateur `CareerFileService` limité aux grades/quals/awards/billets/équipement. Writers de `personnel_service_history` quasi uniquement sur ORBAT. Alertes et demandes isolées.

## Correctif

- `CareerFileService` fusionne aussi `personnel_service_history`.
- `PersonnelServiceHistoryWriter` branché sur élévation acceptée, décoration attribuée, correction confirmée / directe.
- `RhActionInboxService` + onglet Effectifs **À traiter** (`/a-traiter`).

## Fichiers touchés

- `app/Services/Personnel/CareerFileService.php`
- `app/Services/Personnel/PersonnelServiceHistoryWriter.php`
- `app/Services/Effectifs/RhActionInboxService.php`
- `app/Controllers/Admin/RhDossierWorkspaceController.php`
- `app/Controllers/Admin/EffectifsWorkspaceController.php`
- `views/admin/effectifs_workspace/rh_inbox.php`, `shell.php`, rail, raccourcis
- `views/admin/member_situation/carriere.php`
- writers : ElevationApprovalService, PersonnelCorrectionRequestService, AwardReferentielController
- `routes/web.php`, `Container.php`, `DevDispatchCatalog.php` (#753)
- `tests/Unit/RhCareerInboxAssetTest.php`

## Vérification

- Asset test `RhCareerInboxAssetTest`
- Contrôle manuel : Ma situation → Carrière ; Effectifs → À traiter

## Statut

corrigé (phase A+B livrée)
