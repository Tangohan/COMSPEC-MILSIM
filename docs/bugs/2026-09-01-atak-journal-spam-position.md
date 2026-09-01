# Journal ATAK — spam des positions reçues

## Contexte

Carte du poste (`/public/atak`). Panneau d’activité (et fenêtre détachée).

## Symptôme

Une carte « Remontée / Position reçue » s’empile toutes les ~30 secondes pour le même opérateur. Le journal devient illisible.

## Cause

Chaque heartbeat BFT écrivait une entrée `ingest` « Position reçue ». L’anti-spam (20 s) est plus court que l’intervalle de position (~30 s), donc chaque ping passait.

## Correctif

Les positions en mission mettent toujours à jour la carte et les effectifs. Elles ne créent plus de carte journal. Les anciennes cartes « Position reçue » sont masquées du panneau (elles restent filtrables dans Données si besoin). Connexion et changement d’indicatif restent affichés.

## Fichiers touchés

- `app/Controllers/Api/AtakApiController.php`
- `app/Services/Tactical/AtakActivityLogService.php`
- `tests/Unit/AtakActivityWebLogTest.php`
- `tests/Unit/AtakActivityPositionIngestAssetTest.php`

## Vérification

Tests unitaires du journal. Contrôle visuel : panneau d’activité pendant une mission, plus de rafale « Position reçue ».

## Statut

Corrigé
