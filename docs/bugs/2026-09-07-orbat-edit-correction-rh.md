# Affectation dossier — champs manquants et validation RH

## Contexte

L’onglet Unité & rôle du dossier (`/personnel/{id}/edit#edit-orbat`) permettait à n’importe quel membre d’enregistrer tout de suite une unité, un emploi ou un titre. Le grade attribué et la date d’engagement n’y figuraient pas. Les demandes de correction RH existaient déjà, mais sans ces champs.

## Symptôme

- Un membre changeait son affectation : le dossier, l’organigramme et le forum suivaient immédiatement, sans passage par un responsable.
- Grade officiel, date d’engagement et date de début d’affectation étaient absents ou ailleurs.

## Cause

`PersonnelController::update` appliquait l’unité et les emplois dès que la personne éditait sa propre fiche (`isSelf`). Le formulaire de correction ne couvrait pas l’affectation.

## Correctif

- Grade attribué, date d’engagement, titre affiché et date de début sont réunis sur Unité & rôle.
- Sans droits Ressources humaines / Gestionnaire (`EffectifsLmsAccess::canApplyOrbatImmediately`), un changement d’affectation crée une demande de correction. La fiche n’est mise à jour qu’après confirmation.
- Les responsables enregistrent encore immédiatement.

## Fichiers touchés

- `app/Support/EffectifsLmsAccess.php`
- `app/Services/Personnel/PersonnelCorrectionRequestService.php`
- `app/Controllers/Web/PersonnelController.php`
- `app/Controllers/Web/PersonnelCorrectionController.php`
- `views/personnel/edit.php`
- `views/personnel/correction_form.php`
- `views/personnel/corrections_queue.php`

## Vérification

- Tests d’assets dossier / correction et `PersonnelOrbatCorrectionGateTest`.
- Contrôle syntaxe PHP des fichiers modifiés.

## Statut

corrigé
