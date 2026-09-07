# Emploi et affectation : l’utilité n’était pas lisible

## Contexte

Sur l’onglet Unité & rôle du dossier, un membre voyait deux blocs très proches, chacun avec un interrupteur « Principal », sans comprendre la différence ni à quoi ça sert.

## Symptôme

L’affectation (l’équipe) et l’emploi (la fonction) se ressemblaient. Les libellés parlaient de « rôle métier », de « référentiel » ou de « presets ». Sur certains dossiers, la liste d’emplois reprend même le nom de l’unité, ce qui renforçait la confusion.

## Cause

Les deux notions existent bien séparément dans le dossier, mais les textes d’écran ne le disaient pas. Le mot « rôle » servait à la fois pour la place dans l’équipe et pour la fonction.

## Correctif

Deux encadrés en tête de Unité & rôle : affectation = l’équipe, emploi = la fonction, avec l’utilité (fiche, organigramme, forum) et le rappel que l’emploi n’ouvre aucun droit. Les libellés, la fiche, le guide et la demande de correction reprennent les mêmes mots.

## Fichiers touchés

- `views/personnel/edit.php`
- `views/personnel/file.php`
- `views/personnel/tutorials.php`
- `views/personnel/correction_form.php`
- `views/admin/effectifs_workspace/member.php`
- `app/Services/Personnel/PersonnelCorrectionRequestService.php`

## Vérification

- Test `PersonnelOrbatClarityAssetTest`
- Test `DevDispatchCatalogTest` (UPDATE #470)
- Contrôle syntaxe PHP des vues et du service

## Statut

Corrigé
