# Bureau effectifs — page Rôles trop serrée

## Contexte

Page Gouvernance / Rôles du bureau effectifs (`back-office/ressources/effectifs/roles`), thème sombre.

## Symptôme

La page paraît trop dense : contenu collé aux bords, indicateurs avec le chiffre collé au libellé, bandeau Pilotage étroit, cartes de rôles avec peu de marge intérieure et un écart trop faible entre elles, bandeau « Deux couches, un même principe » trop plat.

## Cause

Le chrome partagé du bureau (gouttières 24 px, cartes et indicateurs au padding serré) ne laissait pas assez d’air sur une grille de cartes en fond quasi noir, où les filets fins accentuent l’effet d’entassement.

## Correctif

Enveloppe `.eff-roles-page` et règles de spacing dédiées : gouttières supplémentaires, plus d’espace dans les indicateurs, le bandeau de pilotage, les cartes, la grille et le bandeau de rappel. Le langage visuel (fond sombre, pastilles, libellés français) est conservé.

## Fichiers touchés

- `views/admin/effectifs_workspace/roles.php`
- `public/assets/css/effectifs_lms.css`
- `tests/Unit/EffectifsRolesUiAssetTest.php`
- `app/Support/DevDispatchCatalog.php` (UPDATE 270)

## Vérification

Aperçu local de la page avec le CSS du bureau (cartes, indicateurs, bandeau). Tests unitaires d’assets sur les classes et les valeurs d’espacement.

## Statut

Corrigé (à déployer).
