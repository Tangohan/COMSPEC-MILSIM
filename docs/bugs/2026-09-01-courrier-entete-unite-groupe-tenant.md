# Courrier — en-tête papier sans données d’unité

## Contexte

Éditeur de courrier (`/courrier/editor/5`), bloc « En-tête papier ».

## Symptôme

Les champs Communauté, Unité et Groupe affichaient des exemples figés (ministère, unité d’illustration, RH / S1) au lieu du nom de la communauté, de l’unité et du groupe de l’opérateur.

## Cause

L’éditeur ne reprenait pas la fiche (communauté, affectation, groupe). Les exemples du formulaire restaient vides ou étaient enregistrés tels quels.

## Correctif

L’en-tête se remplit avec la communauté, l’unité d’affectation (ou l’affiliation déclarée si l’unité porte le même nom que la communauté) et le groupe, ou à défaut la fonction. Un texte déjà saisi pour ce courrier n’est pas écrasé. Les anciens exemples figés sont ignorés.

## Fichiers touchés

- `app/Services/Courrier/CourrierLetterhead.php`
- `app/Services/Courrier/TemplateVariableService.php`
- `app/Services/Courrier/DocumentAutoFillService.php`
- `app/Services/Courrier/DocumentBuilderService.php`
- `app/Controllers/Courrier/CourrierEditorController.php`
- `views/courrier/editor.php`

## Vérification

Ouvrir un courrier : l’en-tête affiche la communauté, l’unité et le groupe de l’opérateur. L’aperçu et le PDF suivent. Un libellé modifié à la main reste après enregistrement.

## Statut

corrigé
