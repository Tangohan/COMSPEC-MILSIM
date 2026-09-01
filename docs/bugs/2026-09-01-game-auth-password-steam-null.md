# 2026-09-01 — Connexion Overwatch par mot de passe sans Steam

## Contexte

Signalement production `POST /api/game/v1/auth/password` (corrélation `ad7cf2146b2d6292`). Connexion Athena depuis Overwatch avec e-mail et mot de passe.

## Symptôme

La fenêtre de connexion se ferme en incident. L’opérateur ne peut pas ouvrir la session si aucun identifiant Steam n’est connu (partie solo, Steam pas encore associé, identifiant rejeté).

## Cause

`SteamId::normalize()` renvoie `null` pour une valeur vide ou un identifiant rejeté. La suite testait `$steamId !== ''`, ce qui reste vrai pour `null`, puis appelait `upsertPairing()` avec un troisième argument `null` alors que le type attendu est `string`.

## Correctif

La session est émise même sans Steam. La liaison appareil / Steam n’est créée que lorsqu’un identifiant Steam64 est réellement connu (envoyé par le jeu, ou déjà associé au compte).

## Fichiers touchés

- `app/Services/Game/GameAuthService.php`
- `tests/Unit/GameAuthAssetTest.php`
- `tests/Unit/SteamIdNormalizeTest.php`

## Vérification

Tests unitaires `SteamIdNormalizeTest` (vides et placeholders → aucune valeur) et `GameAuthAssetTest` (résolution Steam toujours en chaîne, y compris `_SP_` / vide). PHPUnit n’était pas installé localement : la résolution a été exécutée via un script PHP isolé, résultat OK.

## Statut

Corrigé
