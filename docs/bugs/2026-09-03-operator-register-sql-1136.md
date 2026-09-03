# Fiche opérateur — SQL 1136 / HTTP 503

## Contexte

Envoi de la fiche opérateur depuis Overwatch vers `POST /api/atak/operator/register`.

## Symptôme

Le poste répondait « momentanément indisponible ». Overwatch réessayait en boucle. Journal : HTTP 503, identifiant de corrélation, exception SQL 1136.

## Cause

`OperatorGameProfileRepository::upsertProfile` listait 32 colonnes mais 30 placeholders `?` plus trois `NOW()` (33 valeurs). Le tableau PHP n’en bindait que 29.

## Correctif

VALUES aligné : 29 `?` + `NOW(),NOW(),NOW()`.

## Fichiers touchés

- `app/Repositories/OperatorGameProfileRepository.php`
- `tests/Unit/OperatorGameRegistryContractTest.php`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_syncOperatorProfile.sqf` (backoff exponentiel sur 503)

## Vérification

Test de contrat colonnes / placeholders. Recette : session Athena prête, plus de 503 en boucle sur la fiche.

## Statut

corrigé
