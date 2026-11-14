# Mobilité « prise en compte » sans effet et tâches de Mon service closes par un autre membre

## Contexte

Audit du workflow des unités (moteur de circuits unique, lot 1). Deux défauts trouvés dans les circuits existants.

## Symptôme

1. Une demande de mobilité « changement d'unité » ou « affectation » pouvait être marquée prise en compte sans que le
   membre change d'unité ; le bureau devait refaire l'affectation à la main. Le statut « prise en compte » n'était
   d'ailleurs proposé par aucun bouton, et une demande déjà acceptée ne pouvait plus passer à « prise en compte ».
2. Dans Mon service, accuser réception, démarrer ou terminer une tâche ne vérifiait que la communauté : un membre
   pouvait changer l'état de la tâche d'un autre en envoyant son numéro.

## Cause

1. `RhDossierWorkspaceController::resolveMobility` ne faisait qu'émettre le PDF RH ; seule l'élévation appliquait
   l'unité (`ElevationApprovalService::applyApprovedChanges`). `PersonnelMobilityRequestRepository::resolve` n'acceptait
   qu'une demande « en attente ».
2. `ActionCenterController` appelait `WorkTaskService` sans contrôler que la tâche figurait dans les tâches de la
   personne connectée.

## Correctif

1. « Muter dans l'unité » (statut `applied`, depuis « en attente » ou « acceptée ») affecte le membre par le même
   service que l'élévation, puis émet le PDF RH comme avant.
2. Les trois actions passent par `changeTask`, qui retrouve la tâche dans les tâches de la personne (à son nom, à son
   billet ou à sa permanence) ; une tâche d'avis du moteur de circuits se clôt seulement en donnant l'avis.

## Fichiers touchés

`app/Controllers/Admin/RhDossierWorkspaceController.php`, `app/Repositories/PersonnelMobilityRequestRepository.php`,
`app/Controllers/Web/ActionCenterController.php`, `views/admin/effectifs_workspace/rh_mobility.php`.

## Vérification

`tests/Unit/WorkflowEngineTest.php` ; sur le site : une mobilité « changement d'unité » vers une unité, « Muter dans
l'unité », la fiche du membre montre la nouvelle unité.

## Statut

corrigé, à vérifier sur le site après déploiement
