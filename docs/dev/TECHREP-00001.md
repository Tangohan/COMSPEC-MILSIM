# TECHREP #00001

reported on August 24, 2026

SPOTREP #00001

```
FROM:     Commissaire outils
TO:       Moddeurs / intégrateurs Athena
MATERIEL CATEGORY: Portail Athena + Overwatch + extension
CIRCUMSTANCES: Semaine 17–24 août 2026 (PR #182–#198)
SIZE:     Portail 1.5.30 · connect 1.4.63 · atak_athena 1.0.45 · COMSPECExtension 2.0.12
```

# CHANGELOG

### ATHENA (PHP / carte)

- Added: `AdminAtakHubController` + routes `GET /back-office/atak`, `POST …/localisation-telephone` — PR #198
- Added: Ordres `PHONE_GEOLOC` / `PHONE_GEOLOC_OFF` (`AtakOrderRepository`, canal terminal, `target_type=all`, `radio_sim=false`) — PR #198
- Added: Planification (`MissionPlanningService`, overlay carte, poll jeu `fn_pollMissionPlan`) — PR #197
- Added: Fiches RENS (portail + menu ATAK + panneau web) — PR #184, #185, #186
- Added: Rapports de théâtre + déclenchement charge TOC — PR #186, #188
- Added: `ReplayTimelineBuilder` (kinds `player|ally_ai|phone|gps`) + PDF AAR — PR #198
- Added: `AtakPhoneProximity` (alerte proximité web) — PR #198
- Added: Templates AAR (`AarCustomForm`, champs métier) — PR #198
- Tweaked: `AtakRealismRepository::deleteTerminals` (détache certificats puis DELETE) — PR #198
- Tweaked: `AtakMotionMath` / traces / ETA — PR #193, #198
- Fixed: `AtakTerrainRepository` — colonne `grid_rows` (MariaDB `ROWS` réservé) — PR #195
- Fixed: `TYPE_TACTICAL_ALERT` manquant → équipes de feu — PR #187
- Fixed: Rate-limit / 503 polls carte — PR #191, notes 2026-08-24
- Changed: Journal produit `ChangelogCatalog` + `/nouveautes` — PR #193

### OVERWATCH (SQF)

- Added: `fn_sampleTerrain.sqf` — AO ~4 km autour du joueur, abort 401, `getTerrainHeightASL` — PR #198
- Added: `terrain_z` dans extra position (`fn_updatePosition.sqf`) en plus de `asl_z` — PR #198
- Added: `fn_applyPhoneGeolocOrder.sqf` + handler `fn_receiveOrder.sqf` — PR #198
- Added: `fn_applyAiMoveOrder.sqf`, `fn_pollAiOrders.sqf` — PR #198
- Added: Panneaux Zeus `fn_zeusAttributes*.sqf` / `fn_registerZeusAttributeButtons.sqf` — PR #198
- Added: `fn_aceDisablePhoneTrack.sqf`, `fn_phoneTrackConfigure.sqf` — PR #198
- Added: Proximité téléphone Athena `fn_athena_phoneProximity*.sqf` — PR #198
- Tweaked: `fn_playAtakEnhancedSound.sqf` — anti-spam bips vanilla — PR #198
- Tweaked: Overlays `fn_updateDeviceOverlay.sqf` / PP roleplay — PR #198
- Tweaked: Fenêtre CGU / bêta au menu principal (plus de parade en mission) — PR #193
- Fixed: Photo ATAK JPEG sans second `screenshot` PNG synchrone — PR #191
- Fixed: Doublon `athena_noteOnOpened` CfgFunctions — PR #189
- Fixed: `BIS_fnc_replaceString` PBO Workshop périmé (SSE) — note 2026-08-23
- Changed: connect `versionStr` 1.4.63 · atak_athena 1.0.45

### EXTENSION (C#)

- Added: `HandleTerrainChunk` — retour `["OK","queued"]`, blocage 90 s après 401 `/terrain/chunk` — PR #198
- Tweaked: `EnqueueOrSend` inchangé pour le reste ; le relief n’est plus fire-and-forget silencieux
- Changed: Bannière `COMSPECExtension 2.0.12`

### SSE

- Added: Atelier modèles Arma — PR #190, #192
- Added: DOMEX Zeus live + file à exploiter — PR #194
- Fixed: Transmissions personne / biométrie / digital ; `lastInsertId` ; labels scientifiques — PR #186, #194
- Fixed: Terminal Android + photos recon + PDF dossiers — PR #182

# PULL REQUESTS (17–24 août 2026)

| PR | Date | Titre |
| --- | --- | --- |
| [#182](https://github.com/Tangohan/COMSPEC-MILSIM/pull/182) | 17/08 | Terminal Android, photos recon, PDF dossiers |
| [#183](https://github.com/Tangohan/COMSPEC-MILSIM/pull/183) | 18/08 | Cloud Agent (PHP 8.4 + MariaDB), install neuve |
| [#184](https://github.com/Tangohan/COMSPEC-MILSIM/pull/184) | 18/08 | Fiches de renseignement simplifiées |
| [#185](https://github.com/Tangohan/COMSPEC-MILSIM/pull/185) | 19/08 | Panneau FRS dans ATAK web |
| [#186](https://github.com/Tangohan/COMSPEC-MILSIM/pull/186) | 23/08 | Fiches, rapports de théâtre, rebuild |
| [#187](https://github.com/Tangohan/COMSPEC-MILSIM/pull/187) | 23/08 | `TYPE_TACTICAL_ALERT` / équipes de feu |
| [#188](https://github.com/Tangohan/COMSPEC-MILSIM/pull/188) | 23/08 | Déclenchement charge depuis le TOC |
| [#189](https://github.com/Tangohan/COMSPEC-MILSIM/pull/189) | 23/08 | Doublon `athena_noteOnOpened` |
| [#190](https://github.com/Tangohan/COMSPEC-MILSIM/pull/190) | 23/08 | Atelier modèles SSE |
| [#191](https://github.com/Tangohan/COMSPEC-MILSIM/pull/191) | 23/08 | JPEG sans gel, polls async, GPS/IA |
| [#192](https://github.com/Tangohan/COMSPEC-MILSIM/pull/192) | 23/08 | Atelier modèles (suite) |
| [#193](https://github.com/Tangohan/COMSPEC-MILSIM/pull/193) | 24/08 | Journal produit, connexion, ATAK test, Overwatch |
| [#194](https://github.com/Tangohan/COMSPEC-MILSIM/pull/194) | 24/08 | DOMEX Zeus, file à exploiter, terrain |
| [#195](https://github.com/Tangohan/COMSPEC-MILSIM/pull/195) | 24/08 | Colonne `rows` relief (MariaDB) |
| [#196](https://github.com/Tangohan/COMSPEC-MILSIM/pull/196) | 24/08 | Barre d’outils / bloc-notes |
| [#197](https://github.com/Tangohan/COMSPEC-MILSIM/pull/197) | 24/08 | Planification de mission sur la carte |
| [#198](https://github.com/Tangohan/COMSPEC-MILSIM/pull/198) | 24/08 | Poste de situation, relief, pack 1.4.63 *(ouvert)* |

# NOTES

- Rebuild : `mod/UptoDate/build_mod.bat` (Native AOT + AddonBuilder). Ne pas copier le stub managé `bin/Release`.
- Heightmap web = échantillonnage `getTerrainHeightASL`, pas l’altitude opérateur (`getPosASL` / `asl_z`).
- Rename CLI `rows` → `grid_rows` encore à lancer en production si la migration HTTP n’a pas été jouée.
- Documentation utilisateur : [SPOTREP #00001](SPOTREP-00001.md). Notes bugs : `docs/bugs/2026-08-2*.md`.
