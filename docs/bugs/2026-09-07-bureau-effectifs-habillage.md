# Bureau Effectifs : empilement d’habillages

## Contexte

Le bureau Effectifs a été collé dans le back-office clair, tout en gardant un thème sombre prévu pour un écran autonome, plus un bandeau d’accueil.

## Symptôme

Un responsable voyait un grand bandeau sombre, une triple rangée de puces minuscules, un rappel « section active », puis encore le titre Effectifs et les mêmes boutons. La fiche membre restait sombre sur fond clair. Illisible et peu intuitif.

## Cause

Trois couches se superposaient : le menu du back-office, un habillage marketing (`shell.php`), et les pages internes encore écrites pour un écran sombre.

## Correctif

Un titre, des onglets lisibles, puis le contenu. Le tableur ne répète plus le titre. La fiche membre suit la même présentation claire.

## Fichiers touchés

- `views/admin/effectifs_workspace/shell.php`
- `views/admin/effectifs_workspace/roster.php`
- `public/assets/css/back-office-effectifs-workspace.css`

## Vérification

- Contrôle syntaxe PHP
- Tests `EffectifsBackOfficeShellAssetTest`, `DevDispatchCatalogTest`

## Statut

Corrigé
