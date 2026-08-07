# Arma — Variable indéfinie `_zone` dans `fn_captureReconImage`

## Contexte

Capture / envoi d’image de recon (Photo Library, ACE, feed) avec effets roleplay actifs.

## Symptôme

Erreur script in-game :

> Error Variable indéfinie dans une expression: `_zone`  
> `fn_captureReconImage.sqf`, ligne ~54 (`_zone isEqualType createHashMap`)

## Cause

```sqf
private _zone = createHashMap;
_zone = [] call comspec_overwatch_connect_fnc_getPlayerRoleplayZone;
```

Hors zone, `getPlayerRoleplayZone` renvoie `nil`. L’affectation à `_zone` rend la variable indéfinie pour SQF ; l’accès suivant (`isEqualType`) plante.

## Correctif

Lire la zone une fois, tester `!isNil "_zone" && {_zone isEqualType createHashMap}`, puis dériver `_zoneType` (chaîne vide sinon). Même pattern que `fn_applyZoneEffects.sqf`.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_captureReconImage.sqf`
- Rebuild + déploiement de `connect.pbo` (repo + Workshop Arma)

## Vérification

- [x] Rebuild AddonBuilder `connect` — Build Successful
- [x] Déployé vers `workshop\content\107410\3684656708` et `@COMSPECOverwatch` local
- [x] `!Workshop\@COMSPECOverwatch` — sync SHA256 OK (2026-08-06 ; plus de verrou)
- [ ] Relancer Arma, capturer une photo hors zone roleplay → plus d’erreur `_zone`

## Statut

corrigé — PBO reconstruit et déployé Workshop ; confirmation in-game à faire
