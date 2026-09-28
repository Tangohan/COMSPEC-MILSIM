# TypeError — tacticalConfig() renvoie int au lieu d’un tableau

## Contexte

28 septembre 2026. Production. Erreurs signalées sur `GET /api/atak/geo/places` et `GET /api/system/version`.

## Symptôme

`App\Support\ComspecApiKeyAuth::tacticalConfig(): Return value must be of type array, int returned` (ligne ~514).

## Cause

`tacticalConfig()` faisait `return is_file($path) ? require $path : […]`. Si le fichier a déjà été inclus (`require_once`) ou ne contient pas de `return` tableau, PHP renvoie `true` / `1` → TypeError fatale sur chaque requête API passant par le middleware tactique.

## Correctif

- Charger avec `require` hors ternaire.
- Vérifier `is_array` ; sinon repli sur une config minimale (exemptions ping / tiles / appairage…).
- Cache statique de la config pour la durée de la requête / worker.

## Fichiers touchés

- `app/Support/ComspecApiKeyAuth.php`
- `tests/Unit/AtakTilesProxyExemptTest.php`

## Vérification

Après déploiement + reload php-fpm : `/public/api/system/version` et `/public/api/atak/geo/places?mapId=1&bbox=…` ne doivent plus 500.

## Statut

corrigé (à déployer)
