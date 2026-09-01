# Menu ACE — COMSPEC Athena invisible

## Contexte

En jeu, menu d’interaction ACE (sur soi). Les opérateurs cherchent **COMSPEC Athena**.

## Symptôme

L’entrée n’apparaissait plus. Compte Athena et le téléphone n’étaient pas dans ACE, sauf si un réglage optionnel « menus étendus » était coché.

## Cause

Les menus ACE Overwatch étaient **désactivés par défaut** (pour éviter des erreurs avec d’autres mods). L’entrée s’appelait « COMSPEC Overwatch », pas Athena. Sans ce réglage, ACE ne montrait rien.

## Correctif

- Menu ACE **COMSPEC Athena** toujours installé : Compte Athena (sans exiger le téléphone) et ouverture du téléphone si le terminal est porté.
- Les rapports ATAK supplémentaires restent derrière le réglage « Menus ACE Overwatch étendus ».

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_initACEAthena.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_initACE.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/XEH_postInit.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/XEH_preInit.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp`
- `tests/Unit/OverwatchAceAthenaMenuAssetTest.php`

## Vérification

ACE sur soi → **COMSPEC Athena** → Compte Athena. Recharger le pack jeu et relancer Arma.

## Statut

Corrigé (pack à recharger).
