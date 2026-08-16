# Photos ATAK — file_not_found / OK|duplicate en ERROR

## Contexte

Journal session `23:32–23:46` : `NotifyNewPhoto OK` puis `PhotoUpload file_not_found`
(`srcdir_missing`). Aucune capture depuis le **29/07** dans
`Documents\Arma 3 - Other Profiles\NewPI\Screenshots`. Aussi : `HTTP 401` ponctuel
sur `/api/atak/position` (chat OK juste après).

## Symptôme

- Remontée photo Athena échoue systématiquement
- Dossier Screenshots figé (pas de nouveau `.png`)
- Spam `OK|duplicate` en ERROR ; `401` position en ERROR

## Cause

1. **`screenshot` Arma exige l’extension `.png`** (wiki BI) — sans elle, **échec silencieux**.
   Les stems `COMSPEC_292_…` / `COMSPEC_AthenaHD` étaient passés **sans** `.png`.
2. Chemins Photo Library / BCE morts (`srcdir_missing`) + `newestFallback` off pour `foo.jpg`
3. `str "OK|duplicate"` en SQF ajoutait des guillemets → détection ratée
4. `401` position : course clé/session (fire-and-forget) — non bloquant si chat passe

## Correctif

- Tous les `screenshot` → `…png` obligatoire (`captureReconImage`, face SEEK, casque HD)
- DLL : `newestFallback` nom seul / parent manquant + attente « newest since enqueue »
- Duplicate : détection sans `str` ; `401` position → WARN + re-`client-init` (30 s)

## Fichiers touchés

- `connect/functions/fn_captureReconImage.sqf`
- `connect/functions/fn_sseCaptureFacePhoto.sqf`
- `connect/functions/fn_ssePersonDialogSubmit.sqf`
- `connect/functions/fn_extensionCallback.sqf`
- `atak_athena/functions/fn_athena_onHelmetMediaRequest.sqf`
- `COMSPECExtension/Extension.cs`

## Vérification

1. Rebuild DLL + PBO `connect` (+ `atak_athena` si casque)
2. **Relancer Arma** (DLL déjà chargée sinon)
3. Prendre une photo → un `.png` **nouveau** doit apparaître sous
   `Documents\Arma 3 - Other Profiles\NewPI\Screenshots`
4. Journal : `PhotoUpload OK|uploaded`
5. Si HDR &lt; medium en jeu, `screenshot` échoue encore (réglage vidéo Arma)

## Relance (23:51)

Journal encore `NotifyNewPhoto …jpg` + `file_not_found|srcdir_missing` puis `401` sur
`/public/api/atak/marker`. Dossier `NewPI\Screenshots` **toujours figé au 29/07** —
aucun `.png` écrit → `screenshot` n’a pas produit de fichier (session antérieure au
correctif `.png`, et/ou HDR &lt; medium).

Suite : re-`client-init` aussi sur 401 marqueur ; diagnostic `newest_Xd` dans
`file_not_found` ; rebuild DLL + connect ; **quitter Arma complètement** puis vérifier
qu’un nouveau `.png` apparaît dans Screenshots après une capture.

## Incident déploiement DLL (00:15)

`dotnet build` a produit le stub managé (~147 Ko) copié par erreur dans
`@COMSPECOverwatch` à la place de la Native AOT (~8 Mo). Conséquence possible :
extension HS / uploads silencieux. Correctif : `dotnet publish -c Release -r win-x64`
puis copie de la DLL native (&gt; 1 Mo).

## Relance (23:55–00:05) — jpg IceMan sans capture Arma

Journal : `NotifyNewPhoto …jpg` OK puis `PhotoUpload file_not_found|srcdir_missing`.
Dossier `NewPI\Screenshots` **toujours figé au 29/07** — aucun `2026_08_16*` ni
`COMSPEC_*` sur le disque.

Cause complémentaire : quand IceMan/BCE fournit un chemin `.jpg` mort,
`captureReconImage` **notifiait seulement** sans `screenshot` Arma → rien à uploader.
`OK|duplicate` loggé en ERROR à cause de guillemets autour du retour extension.

Correctif :
- chemin fourni → toujours `screenshot COMSPEC_….png` + notif du png
- retry avec `skipArmaShot` (pas de double capture)
- strip guillemets sur résultat extension (duplicate)

## Statut

corrigé — rebuild PBO `connect` + quitter Arma + HDR ≥ Moyen + vérif nouveau `.png`
