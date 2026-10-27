# Avancement : liste et avis illisibles

## Contexte

Page RH → Avancement (`/back-office/rh/avancement`) et fiche d’une campagne.

## Symptôme

Les campagnes et les candidatures étaient dans des tableaux serrés. L’avis et la décision affichaient les codes internes (`propose`, `inscrit`) au lieu d’un libellé.

## Cause

Les vues répétaient les colonnes de la base et imprimaient `commission_opinion` et `decision` tels quels.

## Correctif

La liste des campagnes est une suite de liens (année, grade, filière, période, places, statut). La fiche campagne présente chaque candidature en carte, avec les libellés « Proposé », « Inscrit au tableau », « Pas encore d’avis », « En attente ».

## Fichiers touchés

- `views/admin/advancement/campaigns_index.php`
- `views/admin/advancement/campaign_show.php`
- `public/assets/css/back-office-advancement.css`
- `public/assets/css/back-office-dark.generated.css`
- `app/Controllers/Admin/Organization/AdvancementAdminController.php`
- `tests/Unit/AdvancementCareerWiringAssetTest.php`

## Vérification

Rendu local des deux vues avec une campagne ouverte, une campagne en commission, et une candidature « propose » / « inscrit ». Les libellés attendus sont présents, aucun tableau.

## Statut

Corrigé
