# Correction RH — bouton illisible, portrait et historique maigre

## Contexte

Page `/personnel/{id}/correction` (formulaire de correction / anomalie RH).

## Symptôme

- Le bouton « Envoyer pour confirmation » était illisible (texte clair sur fond clair) dès qu’une demande était déjà en attente.
- Le portrait n’apparaissait pas (ou cassait) dans l’en-tête.
- L’historique ne montrait que le statut et la date, sans détail des changements.

## Cause

- La règle CSS `button:disabled` forçait un fond clair sans corriger la couleur du texte du bouton primaire (restée blanche).
- Le portrait n’était résolu que pour le mode responsable.
- L’historique ne lisait pas le détail des champs proposés ni l’auteur / la décision.

## Correctif

- Couleurs explicites pour boutons désactivés (texte sombre lisible).
- Portrait résolu pour tous, avec repli sur initiales si l’image échoue.
- Historique enrichi : badges de statut, auteur, décision, message, liste des changements.
- Aperçu du dossier (indicatif, grade, unité…) sous le titre.

## Fichiers touchés

- `views/personnel/correction_form.php`
- `app/Controllers/Web/PersonnelCorrectionController.php`
- `app/Repositories/PersonnelCorrectionRequestRepository.php`
- `app/Services/Personnel/PersonnelCorrectionRequestService.php`
- `public/assets/css/personnel-dossier.css`
- `tests/Unit/PersonnelCorrectionFormAssetTest.php`

## Vérification

Recharger `/personnel/{id}/correction` : bouton lisible même désactivé, portrait ou initiales, historique détaillé.

## Statut

corrigé
