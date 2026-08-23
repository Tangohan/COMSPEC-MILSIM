# Raccourci SSE Ctrl+Shift+S n’affiche rien

## Contexte

Raccourci CBA « Ouvrir SEEK II » (`Ctrl+Shift+S`) depuis le mod COMSPEC SSE, utilisé comme entrée terrain pour la fiche source.

## Symptôme

Appui sur Maj+Ctrl+S : aucun écran, aucun message.

## Cause

Le raccourci branchait **d’abord** sur BII-10 Identifi dès que le *mod* BII était chargé, même sans appareil BII-10 en inventaire. `BII_fnc_identifi_open` refusait alors l’ouverture et renvoyait malgré tout un succès (retour de la notif cTab, invisible si le téléphone est fermé). Le raccourci **sortait** sans repli vers le terminal SEEK Overwatch.

Sans le mod SSE, Overwatch n’avait **aucun** raccourci équivalent : la combinaison ne faisait rien.

## Correctif

- BII seulement si l’appareil BII-10 est vraiment porté **et** que la fenêtre existe.
- Sinon : rédacteur de fiche de renseignement (menu RENS / plein cadre ATAK), puis terminal SEEK, puis BII-10.
- Message visible si tout échoue.
- Overwatch enregistre `Ctrl+Shift+S` si SSE n’est pas chargé.
- Un téléphone ATAK compte comme terminal de recueil.

## Fichiers touchés

- `mod/@COMSPEC_SSE/addons/biometrics/functions/fn_openSeekKeybind.sqf`
- `mod/@COMSPEC_SSE/addons/compat_bii/functions/fn_biiOpen.sqf`
- `mod/@COMSPEC_SSE/addons/biometrics/XEH_preInit.sqf`
- `mod/@COMSPEC_SSE/addons/biometrics/functions/fn_seekOnLoad.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_sseOpenFromKeybind.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_sseHasTerminalItem.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/XEH_preInit.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp`

## Vérification

1. Rebuild `connect.pbo` (Overwatch 1.4.21) + `atak_athena.pbo` (1.0.29) + `comspec_sse_biometrics.pbo`.
2. Quitter Arma / launcher, recopier les PBO.
3. En jeu, avec un téléphone ATAK : Maj+Ctrl+S ouvre le rédacteur de fiche de renseignement (plein cadre).
4. Tiroir ATAK : menu **RENS**.
5. Sans aucun terminal : message d’avertissement, plus de silence.

## Statut

corrigé en sources — rebuild PBO requis
