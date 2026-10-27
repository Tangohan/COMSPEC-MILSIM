# Freeze / lag surtout au tir

## Contexte

Reprise audit ingénierie 27/10/2026. Opérateurs rapportent des freezes pendant les engagements. Aucun handler `Fired` COMSPEC dans le pack natif.

## Symptôme

À-coups client pendant le tir, surtout téléphone porté (carte HUD) ou calque Relais actif.

## Cause

Profil de coût **lu dans le code** (Arma non exécuté pour mesurer) :

- Draw carte chaque image : toponymes + jusqu’à ~80 tirets par liaison relais ;
- `SendMarker` synchrone sur le thread de jeu ;
- `foreignHud` à chaque image avec téléphone ouvert.

## Correctif

- HUD porté : pas de toponymes ;
- Combat / HUD : traits relais en ligne pleine ;
- Pistes unités : HashMap réutilisé ;
- `SendMarker` via `extAwait` en spawn ;
- `foreignHud` à 10 Hz ;
- Journal hitch enrichi (comportement + suppression).

## Fichiers touchés

- `fn_mapOnDraw.sqf`, `fn_meshDraw.sqf`, `fn_localDataRefresh.sqf`, `fn_foreignHud.sqf`
- `fn_syncMapMarker.sqf` (connect)
- `XEH_postInitClient.sqf`
- `Extension.cs` (DLL-005, DLL-007)

## Vérification

**Non mesuré en jeu.** Scénario : 60 s de tir, téléphone porté puis rangé ; lire `[PERF] Image figée` (champs combat / suppression). Quitter Arma, republier pack + DLL Link.

## Statut

corrigé (code) — mesure FPS ouverte
