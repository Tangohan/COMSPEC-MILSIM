# Tableau de bord — bandeau d’intervention manquant

## Contexte

Page membre `/dashboard` (adresse `/public/dashboard`). Pendant une intervention, le gestionnaire du site voit un bandeau ambre en haut des pages du portail (journal, changements, quitter l’organisation).

## Symptôme

Le tableau de bord n’affichait pas ce bandeau. Impossible d’y voir qu’une intervention est en cours, d’ouvrir le journal ou de quitter l’organisation depuis cette page.

## Cause

Le tableau de bord a son propre habillage, distinct du reste du portail. Le bandeau d’intervention n’y était pas inclus. Même écart sur le bureau des effectifs, le tableau de bord carte, le forum et la carte ATAK.

## Correctif

Le bandeau d’intervention est inclus en haut de ces pages, comme sur le reste du portail. Le libellé visible indique l’administration de l’organisation, sans vocabulaire interne.

## Fichiers touchés

- `views/partials/tenant_intervention_banner.php`
- `views/dashboard.php`
- `views/dashboard_effectifs.php`
- `views/dashboard_atak.php`
- `views/layout/forum.php`
- `views/atak.php`
- `tests/Unit/TenantInterventionBannerAssetTest.php`

## Vérification

Test unitaire d’inclusion du bandeau en tête de page. Entrer dans une organisation depuis l’administration du site, ouvrir le tableau de bord : le bandeau ambre indique l’organisation, avec Journal et Quitter l’organisation.

## Statut

Corrigé.
