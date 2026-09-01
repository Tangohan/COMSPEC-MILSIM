# Correction RH — formulaire illisible et données manquantes

## Contexte

Formulaire « Signaler un problème » / Correction RH, ouvert depuis la fiche personnel (`/personnel/{id}/correction`).

## Symptôme

Le titre « Correction RH » était blanc sur fond clair, donc presque illisible. Les champs étaient des blocs charcoal sur une carte claire, décalés par rapport au reste du dossier. La grille à deux colonnes laissait un trou sous la date d’engagement. Le membre ne pouvait pas proposer le prénom, le nom, la présentation, les indicatifs secondaires, les autres surnoms, la fonction du dossier ni l’échéance de visite médicale — pourtant déjà présents sur la fiche.

## Cause

Le formulaire réutilisait des classes sombres (titre blanc, fonds ardoise) alors que la page portail est claire. La liste des champs corrigibles était un sous-ensemble incomplet du dossier, sans listes déroulantes pour les choix déjà énumérés (groupe sanguin, sexe).

## Correctif

Le formulaire reprend le chrome du dossier personnel (titre sombre, champs clairs, grilles sans trou). Les informations d’identité et de suivi déjà stockées peuvent être proposées ; à la confirmation organisateur, elles sont bien écrites (profil personnage, indicatifs, surnoms, dossier opérationnel). Groupe sanguin, sexe, situation familiale et statut opérateur se choisissent dans une liste.

## Fichiers touchés

- `views/personnel/correction_form.php`
- `app/Services/Personnel/PersonnelCorrectionRequestService.php`
- `app/Controllers/Web/PersonnelCorrectionController.php`
- `app/Core/Container.php`
- `public/assets/css/personnel-dossier.css`
- `tests/Unit/PersonnelCorrectionFormAssetTest.php`

## Vérification

Test `PersonnelCorrectionFormAssetTest` : chrome dossier, titre contrasté, champs d’identité présents, listes déroulantes, persistance côté service (`applyApprovedPayload`, profil personnage, indicatifs / surnoms).

## Statut

corrigé
