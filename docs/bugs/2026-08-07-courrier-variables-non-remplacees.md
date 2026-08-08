# Courrier — variables {{…}} non remplacées dans l’éditeur

## Contexte

Éditeur `/courrier/editor/{id}` : alerte bloquante
`Variables non remplacées : document.reference_number, document.uuid, unit.address, unit.city`
alors que la référence est renseignée (ex. CR-2026-0001).

## Symptôme

- Placeholders visibles bruts dans le corps et l’aperçu.
- Conformité bloquée / export PDF empêché.

## Cause

1. Le corps était enregistré tel quel, sans passage par `TemplateRenderService::renderBody`.
2. L’aperçu affichait `body_rendered` sans re-résolution.
3. `unit.address` / `unit.city` étaient toujours des chaînes vides et non injectées de façon fiable.

## Correctif

- Résolution des `{{variables}}` à la sauvegarde (et après création pour l’uuid).
- Auto-guérison à l’ouverture d’un document existant.
- Résolution tardive dans `DocumentBuilderService::buildPreviewHtml`.
- Validation : re-résolution avant détection des placeholders restants.
- Valeurs absentes affichées en « — » (`displayOrDash`).

## Fichiers touchés

- `app/Services/Courrier/TemplateVariableService.php`
- `app/Services/Courrier/TemplateRenderService.php`
- `app/Services/Courrier/DocumentBuilderService.php`
- `app/Services/Courrier/DocumentValidationService.php`
- `app/Controllers/Courrier/CourrierEditorController.php`

## Vérification

1. Ouvrir `/courrier/editor/4` : alerte disparue, placeholders remplacés (réf. / uuid / — pour adresse).
2. Réenregistrer : corps stocké sans `{{…}}`.
3. PDF : plus d’`ArgumentCountError` DI + corps résolu.

## Statut

corrigé
