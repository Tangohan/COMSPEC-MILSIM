# Fiche opérateur jeu — la position ne portait pas l’identité observée

## Contexte

Remontée Arma → Athena via COMSPEC Overwatch. Le portail doit pouvoir comparer la fiche officielle à ce qui est réellement observé en jeu (Steam, visage, groupe sanguin, versions, équipement).

## Symptôme

Chaque mise à jour de position emportait déjà un peu de contexte (indicatif, rôle, groupe, version du pack) mais **pas** une fiche d’identité : visage, loadout, catalogue de versions, groupe sanguin structuré. Impossible de constituer un registre « ce que le système a vu en jeu » sans noyer le suivi temps réel.

## Cause

Un seul canal (`UpdatePosition`) mélangeait localisation fréquente et profil. Le loadout et le visage n’étaient collectés que pour l’arsenal / SEEK, jamais pour l’opérateur local.

## Correctif

Canal dédié côté addon : collecte identité / visage / médical d’identité / équipement / versions, empreinte anti-spam, envoi à l’enregistrement puis seulement en cas de changement. La position reste légère. Liaison uniquement par Steam ID.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_syncOperatorProfile.sqf` et collecteurs `fn_collectOperator*.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_updatePosition.sqf` (commentaire de séparation)
- `mod/UptoDate/COMSPECExtension/Extension.cs` (`OperatorRegister` / `OperatorSync`)
- `tests/Unit/OverwatchOperatorGameProfileAssetTest.php`

## Vérification

`phpunit tests/Unit/OverwatchOperatorGameProfileAssetTest.php`. En jeu : après liaison, un enregistrement part une fois ; un changement de tenue déclenche un sync ; marcher ne renvoie pas le loadout.

## Statut

Corrigé. Aligné avec le registre portail (PR #362) : aliases de champs, fiches non liées
conservées, loadout sérialisé. Voir aussi `docs/bugs/2026-09-02-registre-operateur-pr362.md`.
