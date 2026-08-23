# Resynch ATAK — renvoyer toutes les données Athena

## Contexte

Après une coupure ou un poste de commandement incomplet, le terminal n’envoyait qu’une partie de l’état (surtout la position et les marqueurs). L’opérateur n’avait pas d’action unique pour tout renvoyer.

## Symptôme

Le bouton existant « Transmettre ma position et mes données » ne remontait pas les fiches personnes, le groupe, les messages ni les fiches de renseignement encore en attente.

## Cause

`fn_forceSyncData.sqf` se limitait à la position forcée et aux marqueurs carte. SEEK, FRS hors ligne, effectif de groupe et messagerie partaient par d’autres chemins, jamais regroupés.

## Correctif

- App **Resynch** dans le tiroir d’applications ATAK (à côté d’Athena), raccourci bureau, menu ACE, et bouton hub.
- Le Resynch renvoie : localisation, données carte (marqueurs, cTab, météo, drones, itinéraires, saut), SEEK, fiches encore en file, effectif du groupe, derniers messages de ce terminal.
- Les photos ne sont **pas** rejouées (elles restent celles déjà transmises).
- Les fiches de renseignement déjà validées et parties ne sont pas recréées ; seules celles encore en attente hors ligne sont renvoyées.
- Conflit Git non résolu dans `fn_athena_installDesktopShortcut.sqf` corrigé au passage.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_forceSyncData.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_initACE.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/display_hub.hpp`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_resynchAll.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_resynchOnOpened.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_openResynch.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/ui/resynch_page.hpp`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_installDesktopShortcut.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_sendSeekData.sqf`

## Vérification

- Contrôle du script : plus de marqueurs de conflit Git dans le raccourci bureau.
- Rebuild PBO Overwatch (`connect` + `atak_athena`) nécessaire pour le charger en jeu.
- En session : ouvrir le tiroir d’apps ATAK → **Resynch** → le poste de commandement doit retrouver position, marqueurs, groupe et messages récents.

## Statut

corrigé (sources) — rebuild PBO requis pour le jeu
