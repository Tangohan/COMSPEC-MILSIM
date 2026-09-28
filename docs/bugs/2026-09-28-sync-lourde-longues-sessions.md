# Sync lourde après de longues sessions / au relancement

**Date :** 2026-09-28  
**Statut :** corrigé (partiel — charge étalée + métriques)

## Contexte

Sessions longues avec beaucoup de marqueurs / échanges ; relancement du jeu qui semblait recharger tout l’historique.

## Symptôme

Ralentissements de sync, accumulation perçue au boot, freeze dans l’arsenal (voir note arsenal). Peu de visibilité sur la charge côté poste / back-office.

## Cause

1. Resynchronisation pleine des marqueurs carte sans budget (toutes les ~90 s / au boot).
2. Signature web markers peu discriminante → retraitements inutiles.
3. Arsenal : sync tenues dès l’ouverture (note séparée).
4. Aucune métrique de charge exposée au TOC / admin.

## Correctif

- Budget resync marqueurs (~40 / passe) ; suppressions seulement si budget non saturé.
- Skip si signature web inchangée.
- Arsenal lazy + loader (note `2026-09-28-arsenal-freeze-sync.md`).
- Instantané `syncLoadSnapshot` : activité liaison, panneau trafic Overwatch Beta, santé sync back-office, ligne « Charge liaison » en jeu.

## Fichiers touchés

- `fn_resyncAllMapMarkers.sqf`, `fn_pollAthenaMarkers.sqf`, `fn_startSyncLoops.sqf`
- `AtakDataRepository::syncLoadSnapshot`, `AtakApiController::ingestTraffic` / activity
- `views/admin/atak-config/index.php`, `views/atak-overwatch-beta.php`, `atak-overwatch-ops.js`, `atak-activity.js`
- Écran État Athena (`fn_athena_updateStatus.sqf`)

## Vérification

Longue session → sync plus progressive. Relancer Arma → pas de freeze arsenal sans clic ATHENA. BO / Overwatch Beta / État affichent opérateurs, repères, volume récent.

## Statut

Corrigé (étalement + observabilité). Surveillance charge restante possible via les métriques.
