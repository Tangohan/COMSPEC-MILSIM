# Journal web saturé de « Effectifs en liaison »

## Contexte

11 septembre 2026. Sur la carte ATAK web, le journal affichait toutes les ~20–30 s une remontée « Effectifs en liaison : 2 » sans changement utile.

## Symptôme

Cartes Remontée répétées, même contenu, bouton Fiche — le fil devient illisible.

## Cause

`atak-web-log.js` journalisait un snapshot d’effectifs même sans arrivée/départ. Côté serveur, le throttle ingest (20 s) laissait passer le même message régulièrement.

## Correctif

- Journalisation seulement si : nouveau contact, contact parti, ou roster vide confirmé.
- Plus de snapshot périodique « Effectifs en liaison : N ».
- Serveur : throttle 5 min sur le même libellé `effectifs`.

## Fichiers touchés

- `public/assets/js/atak-web-log.js`
- `app/Services/Tactical/AtakActivityLogService.php`
- `tests/Unit/AtakActivityWebLogTest.php`

## Vérification

Recharger la carte (Ctrl+F5). Avec 2 opérateurs stables : pas de nouvelle Remontée identique. Arrivée/départ : une ligne utile.

## Statut

corrigé — déployer le JS + PHP portail
