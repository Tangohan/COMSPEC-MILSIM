# Journal du dossier vide alors que l’e-mail d’affectation part

## Contexte

Sur la fiche personnel (onglet Suivi), le bloc « Journal du dossier » doit consigner les modifications organisationnelles (grade, affectation, rôles, statut, coordonnées visibles, etc.). Un e-mail ATHENA « Dossier personnel » informe déjà le membre lorsqu’une affectation change.

## Symptôme

Après une mise à jour d’affectation (ex. « Non renseigné » → « 24th STS Gold Team SOF TACP ») enregistrée par un responsable, le membre reçoit bien l’e-mail avec le bouton « Voir mon dossier », mais le journal affiche encore « Aucune modification n’a encore été consignée dans ce journal. »

L’historique de service (événements d’ancienneté / carrière) peut rester vide séparément : ce n’est pas le même registre.

## Cause

L’e-mail est déclenché par `PersonnelStructureChangeNotificationService::notifyFromSnapshots` (Effectifs, dossier, admin compte, etc.).

Le journal (`personnel_org_history`) n’était écrit que sur des chemins partiels :

- édition dossier : seulement si un motif d’affectation / fonction était fourni ;
- admin compte : grade / statut / rôles via `PersonnelOrgHistoryRecorder`, pas l’affectation ;
- affectation rapide Effectifs : notification seule, **aucune** écriture journal.

Résultat : le cas le plus courant (changement d’affectation depuis le bureau Effectifs) envoyait le mail sans ligne de journal.

## Correctif

À chaque détection de changement de structure (grade, affectation, fonction) dans `notifyFromSnapshots`, consigner la même transition dans le journal via `PersonnelOrgHistoryRecorder::recordStructureChanges`, indépendamment des préférences e-mail.

Les écritures journal redondantes sur l’édition dossier (affectation / fonction) et le grade dans `recordUserTableDiff` ont été retirées pour éviter les doublons.

Pas de backfill : les changements déjà notifiés sans journal ne réapparaîtront pas.

## Fichiers touchés

- `app/Services/Personnel/PersonnelStructureChangeNotificationService.php`
- `app/Services/Personnel/PersonnelOrgHistoryRecorder.php`
- `app/Core/Container.php`
- `app/Controllers/Web/PersonnelController.php`
- `tests/Unit/PersonnelOrgHistoryRecorderStructureTest.php`

## Vérification

1. Depuis Effectifs, changer l’affectation d’un membre (comme Liam → Jake).
2. L’e-mail « Dossier personnel » part toujours.
3. Sur la fiche → Suivi → Journal du dossier : une ligne du type `Affectation : Non renseigné → …` apparaît, avec l’auteur.
4. `php vendor/bin/phpunit tests/Unit/PersonnelOrgHistoryRecorderStructureTest.php`

## Statut

Corrigé (nouveaux changements uniquement ; pas de rattrapage des entrées manquantes passées)
