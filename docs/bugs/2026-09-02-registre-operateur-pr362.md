# Fiche opérateur jeu — alignement registre PR #362

## Contexte

Le registre Arma (PR #362) écoute `/api/atak/operator/register` et `/sync`. Le pack Overwatch
envoyait déjà une fiche riche, mais pas sous les mêmes noms de champs. Le stub Codex du pack
ne sérialisait pas le loadout imbriqué et lançait l’enregistrement avant la liaison Athena.

## Symptôme

- Visage, sexe observé, uniforme et mission restaient vides côté portail.
- Un joueur Steam inconnu du tenant n’avait aucune fiche observée (seul un événement « compte
  introuvable »).
- Un loadout détaillé partait vide.
- Une anomalie déjà close n’était pas rouverte si elle réapparaissait.

## Cause

Le portail lisait `identity.player_name`, `sex`, `face_class`, `loadout` à la racine et
`server_name`. Le pack envoyait `arma_player_name`, `sex_detected`, `face.face_class` et
`environment`. Le stub Codex utilisait un encodeur HashMap incapable des tableaux imbriqués.
`user_id` était forcé à 0 si le Steam n’était pas lié. L’empreinte d’anomalie restait unique
même après clôture, donc un `ON DUPLICATE` n’ouvrait plus la ligne.

## Correctif

- Le pack émet les deux familles de clés et sérialise le loadout avec l’encodeur récursif.
- L’enregistrement part après liaison Athena, pas pendant `Connect`.
- Le portail aplatit les alias, enregistre les fiches non liées (`user_id` vide), compare
  l’indicatif du dossier communauté, et rouvre une anomalie close qui réapparaît.
- La DLL traite `OperatorRegister` / `OperatorSync` et l’alias Codex `OperatorProfile` de
  façon synchrone.

## Fichiers touchés

- `app/Services/OperatorGame/OperatorGameObservationNormalizer.php`
- `app/Repositories/OperatorGameProfileRepository.php`
- `app/Controllers/Api/AtakApiController.php`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_buildOperatorProfile.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_syncOperatorProfile.sqf`
- `mod/UptoDate/COMSPECExtension/Extension.cs`
- `tests/Unit/OperatorGameObservationNormalizerTest.php`
- `tests/Unit/OperatorGameRegistryContractTest.php`

## Vérification

`phpunit tests/Unit/OperatorGameObservationNormalizerTest.php tests/Unit/OperatorGameRegistryContractTest.php tests/Unit/OperatorGameReconciliationServiceTest.php tests/Unit/OverwatchOperatorGameProfileAssetTest.php`.

En jeu : après liaison, un enregistrement part une fois ; un Steam inconnu du tenant laisse
tout de même une fiche observée ; marcher ne renvoie pas le loadout.

## Statut

Corrigé.
