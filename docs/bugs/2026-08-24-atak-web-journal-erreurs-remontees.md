# Journal ATAK web — incidents et remontées

**Date :** 2026-08-24  
**Statut :** corrigé

## Contexte

Sur la carte ATAK web, les pannes d’affichage et les données reçues du terrain n’étaient pas visibles en un seul endroit. Le journal TOC ignorait les heartbeats de position (anti-spam) et ne recevait pas les erreurs du navigateur.

## Symptôme

Impossible de savoir, depuis la carte, pourquoi une donnée n’apparaît pas, ni de voir les positions / effectifs qui remontent vraiment.

## Cause

- Les positions jeu n’écrivaient au journal que les connexions et changements d’indicatif.
- Aucune capture des toasts, des lectures refusées, ni des erreurs d’affichage.
- Le panneau « État de la liaison » ne montrait pas l’historique de session.

## Correctif

- Journal local sur la carte (incidents + remontées), recopié vers le journal TOC avec anti-spam.
- Positions jeu : une entrée « Position reçue » au plus toutes les 20 secondes par indicatif.
- Filtres « Incidents » et « Remontées de données » sur la page Journal de liaison.

## Fichiers touchés

- `app/Services/Tactical/AtakActivityLogService.php`
- `app/Controllers/Api/AtakApiController.php`
- `routes/web.php`
- `public/assets/js/atak-web-log.js`
- `public/assets/js/atak-activity.js`
- `views/atak.php`
- `views/atak-liaison.php`
- `public/assets/css/atak.css`
- `tests/Unit/AtakActivityWebLogTest.php`

## Vérification

- Tests unitaires `AtakActivityWebLogTest`.
- Carte ATAK : panneau État de la liaison → Journal de la liaison ; onglet Liaison → Incidents et remontées.

## Statut

corrigé
