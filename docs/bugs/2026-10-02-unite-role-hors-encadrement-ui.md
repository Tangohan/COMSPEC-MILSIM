# Confusion Unité & rôle hors encadrement

## Contexte

Onglet **Unité & rôle** de « Modifier ma fiche », pour un membre sans droits RH.

## Symptôme

Le formulaire d’affectation / emploi / grade était pleinement interactif (ajout d’unités, modèles de fonction, etc.), ce qui donnait l’impression de gérer le dossier comme l’encadrement, alors que seuls une demande de correction et une application immédiate RH existent.

## Cause

Les champs ORBAT n’étaient pas verrouillés côté UI pour les membres : le workflow « proposer une demande » réutilisait le même formulaire que la gestion RH, sans hero ni sections qui distinguent consultation / proposition / gestion. Une demande déjà en attente n’empêchait pas non plus de retoucher le formulaire.

## Correctif

- Hero + résumé de situation (unité, place, emploi, grade).
- Sections distinctes : grade/dates, affectations actuelles (tableau), proposer / modifier affectations, emploi.
- Hors RH : libellés « Proposer… » ; formulaire verrouillé si une demande est déjà en attente.
- Serveur : ne pas resoumettre une demande ORBAT tant qu’une correction est pending (évite un POST vide à cause des champs `disabled`).

## Fichiers touchés

- `views/partials/personnel/edit_orbat_section.php`
- `views/personnel/edit.php`
- `public/assets/css/personnel-edit-refresh.css`
- `app/Controllers/Web/PersonnelController.php`
- `tests/Unit/PersonnelEditFormAssetTest.php`

## Vérification

- Compte membre sans droit RH + demande pending : formulaire grisé, message d’attente, bouton « Enregistrer le reste du dossier ».
- Compte membre sans pending : hero « Votre situation », sections « Proposer… », enregistrement → demande.
- Compte RH : hero « Gestion du dossier », application immédiate.

## Statut

corrigé
