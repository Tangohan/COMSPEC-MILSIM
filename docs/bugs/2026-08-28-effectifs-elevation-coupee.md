# Bug — demande d’élévation coupée par le tableur

## Contexte

Bureau effectifs, tableur des membres. Action Élévation à droite de la ligne.

## Symptôme

Le formulaire (type d’élévation, grade proposé, etc.) s’ouvrait dans la cellule, débordait dans le menu et le bas de page, et une barre horizontale apparaissait. Le champ grade proposé était illisible.

## Cause

Le panneau était collé à la cellule (`position: absolute`) dans un tableur à défilement et à débordement coupé. La cellule impose aussi une ligne sans retour à la ligne, ce qui élargissait encore le panneau.

## Correctif

La demande s’ouvre dans une fenêtre au centre de l’écran, au-dessus du tableur. Tout le formulaire reste visible, avec défilement interne si besoin.

## Fichiers touchés

- `views/admin/effectifs_workspace/roster.php`
- `public/assets/css/effectifs_lms.css`
- `tests/Unit/EffectifsElevationDialogAssetTest.php`

## Vérification

`php -l` sur la vue. Recherche : `showModal`, `eff-elev-dialog`, plus de `eff-sheets__pop--end`.

## Statut

Corrigé
