# Correction RH — équipes et emplois illisibles dans Décider

## Contexte

Page Effectifs → À traiter, fenêtre ouverte par « Décider » sur une demande de correction. La page Corrections de fiches montrait le même écart sur une seule ligne trop étroite.

## Symptôme

La fenêtre affichait les affectations et les emplois sous une forme brute, par exemple une liste d’identifiants ou `[]`. Impossible de comprendre quelle équipe était proposée. Sur Corrections de fiches, l’actuel et le proposé se chevauchaient dès que le nom d’équipe était long.

## Cause

La fenêtre reprenait la valeur stockée telle quelle, sans la traduire en nom d’équipe ou d’emploi. La page des corrections alignait les deux textes sur une seule ligne.

## Correctif

- Les équipes (avec leur place dans l’organigramme et la mention « équipe principale ») et les emplois sont écrits en clair.
- Une liste vide se lit « Aucune équipe » ou « Aucun emploi ».
- La fenêtre et la page des corrections présentent « Actuellement » et « Proposé » séparément.

## Fichiers touchés

- `app/Services/Personnel/PersonnelFieldValueFormatter.php`
- `app/Services/Personnel/PersonnelCorrectionRequestService.php`
- `app/Services/Effectifs/RhActionInboxService.php`
- `app/Controllers/Web/PersonnelCorrectionController.php`
- `views/admin/effectifs_workspace/rh_inbox.php`
- `views/personnel/corrections_queue.php`
- `public/assets/css/back-office-corrections.css`
- `public/assets/css/back-office-effectifs-refonte.css`
- `public/assets/css/back-office-effectifs-workspace.css`

## Vérification

Tests unitaires du formateur et des vues. Relecture de la fenêtre Décider et de la page Corrections de fiches avec une demande qui change une équipe et retire les emplois.

## Statut

corrigé
