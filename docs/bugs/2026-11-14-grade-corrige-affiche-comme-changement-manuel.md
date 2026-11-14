# Grade corrigé par une correction de dossier affiché comme « changement de grade » manuel

## Contexte

Moteur de circuits unique des unités, lot 2 (corrections de dossier et comptes rendus).

## Symptôme

Dans la frise des grades de la fiche Effectifs, un grade changé par une correction de dossier validée (ou appliquée
directement par le bureau) apparaissait comme un simple « Changement de grade », sans lien avec la correction.

## Cause

`PersonnelCorrectionRequestService::applyApprovedPayload` modifie le grade par `UserRepository::update`, qui historise
avec l'origine courante de `MemberGradeChangeRepository` ; seule l'élévation fixait une origine (`withSource`).

## Correctif

Nouvelle origine `correction` (`MemberGradeChangeRepository::SOURCE_CORRECTION`), posée autour des deux applications
d'une correction ; la frise affiche « Correction de dossier validée ». Les lignes déjà enregistrées gardent « manual ».

## Fichiers touchés

`app/Repositories/MemberGradeChangeRepository.php`, `app/Services/Personnel/PersonnelCorrectionRequestService.php`,
`views/admin/effectifs_workspace/member.php`.

## Vérification

Sur le site : corriger le grade d'un membre par une correction de dossier, la frise affiche « Correction de dossier
validée par … ».

## Statut

corrigé, à vérifier sur le site après déploiement
