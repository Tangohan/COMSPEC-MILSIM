# Tableau de bord — fenêtre de mise à jour absente

## Contexte

Lorsqu’une nouvelle version du portail est déployée, une fenêtre invite le membre connecté à actualiser la page. Elle existe déjà sur le chrome du site (`views/layout/main.php`, back-office) et sur la carte ATAK.

## Symptôme

Sur `https://athena.ttrd.fr/public/dashboard`, la fenêtre n’apparaissait pas. Un onglet resté ouvert pendant une mise à jour du site ne proposait pas d’actualiser, contrairement au back-office ou à la carte.

## Cause

Le tableau de bord membre n’utilise pas le layout portail : c’est un document HTML autonome (`views/dashboard.php`, `views/dashboard_atak.php`, `views/dashboard_effectifs.php`). La feuille et le script de la fenêtre étaient collés uniquement dans le shell « organisation complète ». Les profils carte ATAK et bureau des effectifs ne les chargeaient pas. Il n’existait pas d’include partagé depuis l’en-tête Dashboard / Hub / Forum.

## Correctif

Même système, sans second moteur : un partial `views/partials/app_update_check.php` (version du site + feuille + script existants). Il est chargé en tête du tableau de bord et depuis `header_dashboard.php`, donc toutes les variantes de `/public/dashboard` affichent la fenêtre. Elle reste informative (Actualiser / Plus tard), jamais bloquante.

Aperçu : ajouter `?preview_update_modal=1` à l’adresse du tableau de bord.

## Fichiers touchés

- `views/partials/app_update_check.php`
- `views/partials/header_dashboard.php`
- `views/dashboard.php`
- `tests/Unit/DashboardAppUpdateModalAssetTest.php`

## Vérification

Test d’assets : le partial, le tableau de bord et l’en-tête dashboard référencent le script et la feuille existants ; ATAK et le layout portail conservent les leurs. Aperçu `?preview_update_modal=1` sur `/public/dashboard`.

## Statut

corrigé
