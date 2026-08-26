# Relevé de relief refusé (401) tant qu’Athena n’est pas liée

## Contexte

Le relevé du sol autour de l’équipe envoie des blocs vers le poste Athena. En
production, le chemin ATAK exige la clé de communauté. La liaison peut
répondre « joignable » sans que le compte soit réellement lié (clé encore
vide).

## Symptôme

Dans le journal Overwatch, des avertissements du type :

- envoi refusé (code 401) pour le bloc de relief

Le relevé partait quand même, le poste refusait chaque bloc, le calque de
relief ne se remplissait pas.

## Cause

1. L’extension envoyait le bloc de relief même sans clé d’accès. Les autres
   envois attendaient déjà une clé.
2. Connect peut indiquer un portail joignable sans compte lié. Le relevé
   ne vérifiait pas l’état « lié ».
3. L’authentification côté poste lisait surtout l’en-tête de clé, pas le
   corps de la requête. Un corps lu une première fois n’était plus
   disponible pour la suite.

## Correctif

- Extension : pas d’envoi HTTP si la clé est absente (refus local).
- En-tête de clé **et** jeton Bearer, plus la clé dans le corps si elle
  manque encore.
- Le poste relit le corps une seule fois et accepte la clé qui s’y trouve.
- Le relevé en jeu refuse de démarrer tant qu’Athena n’est pas liée, avec
  un message clair pour l’opérateur.

## Fichiers touchés

- `app/Support/ComspecApiKeyAuth.php`
- `app/Controllers/Api/AtakTerrainApiController.php`
- `mod/UptoDate/COMSPECExtension/Extension.cs`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_sampleTerrain.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp`
- `tests/Unit/ComspecApiKeyAuthTest.php`

## Vérification

- Tests unitaires de la clé (en-tête, Bearer, corps).
- Catalogue journal : UPDATE #00211.
- En jeu : sans liaison, le relevé annonce qu’Athena doit être liée et
  n’envoie rien. Après liaison, le relevé transmet les blocs sans 401.

## Statut

corrigé
