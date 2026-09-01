# Tableur effectifs — bandeau ancienneté illisible

## Contexte

Bureau RH, page `back-office/ressources/effectifs` (tableur des effectifs).

## Symptôme

Le bloc « Ancienneté réelle » apparaît comme une large bande sombre presque vide. Le titre, le texte d’aide et le libellé du champ de date sont illisibles. Seuls le champ date et le bouton restent visibles.

Les portraits dans la colonne Identité restent trop petits pour reconnaître l’opérateur.

## Cause

Le bandeau utilisait `.eff-panel`, prévu pour le fond noir du bureau LMS. Il était posé dans `.eff-catalog`, zone claire. Les textes en gris ardoise disparaissaient sur le fond quasi noir.

## Correctif

Bandeau dédié `.eff-catalog__notice` (fond clair, textes lisibles, hauteur compacte). Portrait opérateur agrandi dans le tableur.

## Fichiers touchés

- `views/admin/effectifs_workspace/roster.php`
- `public/assets/css/effectifs_lms.css`
- `tests/Unit/EffectifsSeniorityManagementAssetTest.php`

## Vérification

Relecture du HTML/CSS : plus de `.eff-panel` dans le tableur ; notice claire ; avatar ~3,25 × 4,15 rem.

## Statut

Corrigé (à déployer).
