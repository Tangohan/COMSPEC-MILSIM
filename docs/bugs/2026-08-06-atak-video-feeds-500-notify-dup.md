# ATAK — HTTP 500 video-feeds + double log NotifyNewPhoto

## Contexte

Journal Overwatch en liaison : roster caméras + uploads photo recon.

## Symptôme

```
[ERROR][Tx] HTTP POST — code 500 · /public/api/atak/video-feeds
[INFO][Tx] NotifyNewPhoto — fichier.jpg   (deux lignes quasi identiques)
```

## Cause

1. **video-feeds** : `SendVideoFeeds` n’appliquait pas `EnrichAtakPayload` / normalisation des guillemets Arma ; une exception dans `attachFeedSnapshots` (ex. table recon) remontait en 500 opaque. Des coords non finies pouvaient aussi casser `json_encode`.
2. **NotifyNewPhoto** : `logTransmission` écrivait tentative + OK avec le même libellé INFO dans le journal technique.

## Correctif

- DLL : `SendVideoFeeds` → `EnrichAtakPayload`.
- API : try/catch → 503 métier ; snapshots isolés ; coords finies seulement.
- SQF : tentatives photo en DEBUG ; OK/ÉCHEC préfixés et restés INFO.

## Fichiers touchés

- `Extension.cs`
- `AtakApiController.php` (`videoFeeds`, `attachFeedSnapshots`)
- `AtakVideoFeedsService.php`
- `fn_logTransmission.sqf`

## Vérification

- Déployer API + recompiler DLL / PBO `connect`.
- Plus de 500 systématique sur video-feeds ; au pire 503 avec message.
- Journal : une ligne INFO `OK · NotifyNewPhoto — …` (tentative en DEBUG).

## Statut

corrigé
