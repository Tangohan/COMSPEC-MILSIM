# Menu ACE — rapports, appui et réparation disparus

## Contexte

En jeu, menu d’interaction ACE (sur soi). Les opérateurs cherchent les actions ATAK : rapports, demande d’appui, réparation du téléphone.

## Symptôme

Sous **COMSPEC Athena**, il ne restait que Connexion Athena et Ouvrir le téléphone. Plus de rapports, plus d’appui, plus de service véhicule, plus de réparation.

## Cause

Ces actions n’étaient installées que si un réglage optionnel « menus étendus » était coché. Le réglage était **décoché par défaut**, donc le menu ATAK Tactique et la réparation n’apparaissaient pas.

## Correctif

Les menus ATAK ACE sont installés de nouveau dès le lancement : **ATAK Tactique** (rapports, appui, service véhicule) sous COMSPEC Athena, et **réparation du téléphone** dans ACE Équipement. Connexion Athena reste en tête.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/XEH_postInit.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/XEH_preInit.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_initATAK.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_initACE.sqf`

## Vérification

ACE sur soi → **COMSPEC Athena** → **ATAK Tactique** : Observation, Contact, Situation, Demander appui. ACE Équipement : Rallumer / Réparer écran. Pack 1.5.5.

## Statut

corrigé
