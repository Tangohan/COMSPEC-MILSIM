# Inconscients et hors combat absents de la liste d’alerte ATAK

## Contexte

Sur le téléphone ATAK en jeu (application Alert, panneau PANIC), la liste restait vide (« No panic alerts received ») alors qu’un opérateur était au sol ou hors combat. Localiser un blessé depuis ce panneau était impossible.

## Symptôme

Un KO ACE ou une mort ne faisait pas apparaître de ligne dans PANIC. Seul un signal d’urgence envoyé à la main remplissait la liste.

## Cause

IceMan n’inscrit dans PANIC que les alertes de type PANIC / EAGLE_DOWN. Les alertes médicales Overwatch partaient vers le chat Athena, sans jamais appeler la réception IceMan. De plus, le suivi médical s’arrêtait dès que l’unité n’était plus en vie : la mort n’était pas signalée.

## Correctif

- À l’inconscience, à l’arrêt cardiaque et à la mort, Overwatch diffuse une alerte EAGLE_DOWN vers IceMan, avec l’intitulé INCONSCIENT, ARRET CARDIAQUE ou KIA et la position.
- Un relais depuis Athena complète les téléphones qui n’auraient pas reçu le signal jeu.
- La mort est prise sur l’événement Killed, hors de la grâce de respawn.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_pushIcemanMedicalAlert.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_reportMedicalAlert.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_pollMedicalAlerts.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_attachAtakDamageHandlers.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_checkMedicalAlerts.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/XEH_postInit.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp`

## Vérification

- Tests unitaires `AtakPanicMedicalAssetTest` : présence du relais EAGLE_DOWN, des intitulés INCONSCIENT / KIA, de l’événement Killed et du bump 1.4.94.
- Contrôle en session : KO ACE → ligne PANIC + Localiser ; mort → ligne KIA ; pas de doublon en moins de 90 s.

## Statut

corrigé (pack Overwatch 1.4.94 à reconstruire)
