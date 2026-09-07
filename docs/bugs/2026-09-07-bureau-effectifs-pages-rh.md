# Bureau Effectifs : pages RH illisibles

## Contexte

Les pages Documents, Mobilité, Vivier, Alertes, Fiches jumelles et Anciens membres du bureau Effectifs gardaient l’habillage sombre prévu pour un écran autonome, collé dans le back-office clair.

## Symptôme

Formulaires et tuiles noirs sur fond blanc. Le coffre des pièces était difficile à lire. Roleplay, intégration et réglages n’apparaissaient pas dans cet espace.

## Cause

Les classes `.eff-rh-form` et `.eff-rh-tile` gardaient un fond sombre. Le coffre n’établissait pas de pièce : dépôt de fichier seulement. Les avancements de grade n’existaient pas, et les seuils d’alerte n’étaient pas réglables.

## Correctif

Même présentation claire que le tableur. Une pièce peut être établie et rangée dans le coffre. Roleplay, Intégration et Réglages sont des onglets du bureau. Les avancements se proposent selon l’ancienneté, après activation.

## Fichiers touchés

- `public/assets/css/back-office-effectifs-workspace.css`
- `views/admin/effectifs_workspace/shell.php`
- `views/admin/effectifs_workspace/rh_documents.php`
- `app/Support/PersonnelHrPdfService.php`
- `app/Services/Effectifs/PersonnelHrWorkspaceSettings.php`
- `app/Services/Effectifs/PersonnelAutoAdvancementService.php`

## Vérification

- Tests `PersonnelHrDeskAssetTest`, `PersonnelHrWorkspaceSettingsTest`, `EffectifsBackOfficeShellAssetTest`, `DevDispatchCatalogTest`

## Statut

Corrigé
