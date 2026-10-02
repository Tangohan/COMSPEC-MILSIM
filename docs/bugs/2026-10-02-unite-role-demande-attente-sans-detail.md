# Unité & rôle — demande en attente sans détail ni annulation

## Contexte

Sur Modifier ma fiche → Unité & rôle (`#edit-orbat`), hors encadrement, un changement d’affectation part en demande de correction. Quand une demande était déjà ouverte, un bandeau avertissait seulement qu’il fallait attendre.

## Symptôme

Le membre voyait « Une demande est déjà en attente » sans pouvoir relire le contenu proposé, consulter l’historique, ni retirer sa demande pour en envoyer une autre.

## Cause

L’UI se limitait à un verrouillage (`orbatFrozen`) + message d’info. Pas de panneau de détail, pas d’historique filtré Unité & rôle, pas d’action d’annulation côté membre.

## Correctif

- Panneau « Demande en attente » avec date, auteur, lignes de changement et message.
- Tableau « Historique des demandes » (en attente / confirmées / refusées / annulées).
- Bouton « Annuler la demande » (formulaire hors formulaire principal, comme le matricule) → `POST …/correction/{id}/annuler`.
- Service `cancelByMember` + statut `cancelled`.

## Fichiers touchés

- `views/partials/personnel/edit_orbat_section.php`
- `views/personnel/edit.php`
- `public/assets/css/personnel-edit-refresh.css`
- `app/Controllers/Web/PersonnelController.php` (`buildOrbatCorrectionHistory`)
- `app/Controllers/Web/PersonnelCorrectionController.php` (`cancelOwn`)
- `app/Services/Personnel/PersonnelCorrectionRequestService.php` (`cancelByMember`)
- `routes/web.php`
- `app/Support/DevDispatchCatalog.php` (UPDATE #752)
- `tests/Unit/PersonnelEditFormAssetTest.php`

## Vérification

- Tests asset : `PersonnelEditFormAssetTest::testOrbatTabCollectsAssignmentFieldsAndQueuesMemberChanges`
- Contrôle manuel : demande en attente → détail visible, annulation possible, formulaire déverrouillé ensuite.

## Statut

corrigé
