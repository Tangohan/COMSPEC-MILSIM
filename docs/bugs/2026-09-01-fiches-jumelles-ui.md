# Fiches jumelles — UI creuse et bandes étirées

## Contexte

Bureau effectifs, page `back-office/ressources/effectifs/doublons` (Fiches jumelles). Un premier passage (UPDATE 267) avait déjà aligné les textes sur le bureau ; l’écran restait visuellement inachevé.

## Symptôme

Carte blanche isolée sur un fond noir, hiérarchie plate (« Alerte RH » / titre / petit libellé de champs), cases à cocher dans des rectangles trop larges, bandeau vert « Aucun doublon… » déconnecté du reste. La page ne ressemblait pas au tableur ni aux rôles.

## Cause

Le réglage et le résultat s’empilaient en bandes pleine largeur (`1fr` / `1fr 1fr 1fr`). Les puces de champs s’étiraient avec le conteneur. L’état vide n’était qu’une ligne, pas un panneau.

## Correctif

Même langage que la page Rôles : en-tête `eff-page-head`, indicateurs `eff-metrics`, poste à deux colonnes (réglage | résultat). Cartes de champs à largeur bornée. État vide ou groupes à traiter dans un vrai panneau.

## Fichiers touchés

- `views/admin/effectifs_workspace/duplicates.php`
- `public/assets/css/effectifs_lms.css`
- `tests/Unit/EffectifsDuplicatesUiAssetTest.php`
- `app/Support/DevDispatchCatalog.php` (UPDATE 273)

## Vérification

Aperçu HTML local (`public/tmp-duplicates-preview.html`) + capture bureau Edge (état vide et groupe à relire). Production `athena.ttrd.fr` non ouverte (connexion requise).

## Statut

Corrigé (à déployer).
