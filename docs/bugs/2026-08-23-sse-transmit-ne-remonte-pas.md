# Bug — TRANSMETTRE SSE n’arrive pas au registre

## Contexte

SEEK II / terminal : bouton TRANSMETTRE. Hint « Transmission SSE demandée (record courant) ». Rien dans Identités Athena.

## Symptôme

L’opérateur croit que la fiche part. Le registre web reste vide.

## Cause (journal 23/08)

1. Hint qui masquait l’échec.
2. Biométrie avant fiche personne → `person_id empty`.
3. **`str "OK"`** : `["OK","Success"]` lu comme échec → file d’attente même si l’envoi a marché.
4. **`exitWith` dans un `if then`** : ne sortait pas de la fonction → log « unavailable ».
5. **422 identity_required** : JSON HashMap approximatif / noms vides → le registre refuse la fiche.

## Correctif (suite)

- Lecture du retour extension sans `str` ; `if exitWith` au bon niveau.
- JSON fiche personne construit champ par champ (comme le terminal SEEK Overwatch).
- Filet : au moins un alias (nom d’unité ou identifiant SSE).

## Correctif

- Envoi **personne d’abord**, biométrie ensuite si l’id registre est connu.
- Hint honnête : envoyée **ou** mise en attente.
- Journal fichier SSE, même dossier qu’Overwatch (`%LOCALAPPDATA%\Arma 3\COMSPEC\logs`).
- Payload identité : nom Eden / COMSPEC / nom d’unité, pour ne plus partir vide.

## Fichiers touchés

- `mod/@COMSPEC_SSE/addons/network/functions/fn_transmitEntity.sqf` (nouveau)
- `fn_submitPersonRecord.sqf`, `fn_submitBiometricsSim.sqf`, `fn_submitDigitalAcquisition.sqf`
- `fn_buildAthenaPersonPayload.sqf`, `fn_sendViaOverwatch.sqf`, `fn_toJsonPerson.sqf`
- `addons/network/config.cpp`
- `fn_uiTransmitRecord.sqf`, `fn_resultTransmit.sqf`, `fn_seekTransmit.sqf`
- `fn_showResult.sqf`, `fn_identifySubject.sqf`
- `fn_log.sqf`, `fn_initSettings.sqf`

## Vérification

1. Rebuild PBO `core` + `network` + `ui` + `biometrics`.
2. Relancer, TRANSMETTRE sur une requête d’identité.
3. Hint « envoyée au registre » **ou** « mise en attente ».
4. Journal : fichier `COMSPEC_*.log` — lignes `[COMSPEC SSE]` avec `TX done` / `raw=`.
5. Recharger Identités Athena si hint « envoyée ».

## Statut

corrigé (à recopier les PBO)
