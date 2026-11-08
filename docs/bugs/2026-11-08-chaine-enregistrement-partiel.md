# Chaîne de commandement : enregistrement partiel et membres sans unité invisibles

## Contexte

Page `back-office/ressources/effectifs/chaine`, relue à l’occasion de sa refonte sans rechargement.

## Symptôme

- Tableau des chefs : un refus annonçait « 0 modification » alors que les unités précédentes étaient déjà enregistrées.
- Deux responsables : le second écrasait sans le savoir le chef choisi par le premier.
- Les membres sans unité n’étaient ni comptés ni nommables dès que tous les chefs avaient eux-mêmes une affectation.
- Le lien `#eff-chain-setup` arrivait sur une section restée fermée.

## Cause

- `saveCommanders` écrivait unité par unité et ne vérifiait les chefs qu’au passage.
- Aucun contrôle de l’état vu par l’auteur.
- `memberLabelsForTenant` ne chargeait les membres actifs que si un chef manquait dans les affectations.
- Un `<details>` ne s’ouvre pas quand l’ancre vise le `<details>` lui-même.

## Correctif

- Tout est vérifié avant la première écriture, et l’écriture est dans une transaction.
- `expected_commanders` : un chef changé entre-temps donne un conflit (409), rien n’est écrit.
- Les membres actifs sont toujours chargés.
- `eff-chain.js` ouvre la section visée par l’ancre.

## Fichiers touchés

- `app/Services/Effectifs/PersonnelCommandChainService.php`
- `app/Controllers/Admin/EffectifsWorkspaceController.php`
- `public/assets/js/eff-chain.js`

## Vérification

`tests/Unit/CommandChainAdvisorTest.php`. Parcours du script joués dans Edge sur une page de démonstration, serveur simulé.

## Statut

corrigé
