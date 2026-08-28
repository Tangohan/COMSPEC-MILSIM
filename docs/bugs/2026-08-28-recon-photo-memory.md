# Photo reconnaissance : saturation mémoire PHP

## Contexte

Envoi d’une capture terrain vers le canal reconnaissance (`POST /api/recon/images`), production, limite PHP 128 Mo.

## Symptôme

Le poste signale une erreur interne. Journal : mémoire épuisée en tentant d’allouer ~70 Mo de plus, sur la lecture du corps de requête (ligne `jsonBody` / `preg_replace`).

## Cause

L’envoi est un fichier (`multipart`). Avant d’enregistrer l’image, le contrôleur relisait **tout** le corps brut comme s’il s’agissait de JSON, puis recopiait cette chaîne pour un correctif de virgules. Une capture Arma (~70 Mo) tenait déjà en mémoire ; la copie faisait dépasser 128 Mo. Ce n’est pas rattrapable par un `try/catch`.

## Correctif

- Ne plus lire le corps brut sur un envoi de fichier : s’appuyer sur les champs texte déjà reçus.
- Plafonner la lecture JSON à 2 Mo.
- Ne pas graver le bandeau d’identification sur une image trop lourde ou trop grande.

## Fichiers touchés

- `app/Support/HttpJsonBody.php`
- `app/Support/ComspecApiKeyAuth.php`
- `app/Controllers/Api/AtakApiController.php`
- `app/Controllers/Api/SseApiController.php`
- `app/Services/Media/ReconPhotoHudService.php`

## Vérification

Tests `HttpJsonBodyTest`. Déploiement portail (pas de pack jeu). Refaire un envoi photo depuis le terrain.

## Statut

corrigé
