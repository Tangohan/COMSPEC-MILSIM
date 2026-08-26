# Changelog COMSPEC SSE

## 0.7.15 — Pack Workshop prêt à publier

- Dossier de publication `publisher/@COMSPEC_SSE` : même flux que Overwatch (compiler, assembler un pack propre, envoyer)
- Le téléchargement Steam ne contient pas les fichiers d’atelier

## 0.7.5 — Compat plaque ACE Medical → SSE

- Addon `compat_ace` : vérification dog tag ACE affiche l'identité SSE (nom, n° ID, groupe sanguin)
- Action `dogtag` dans le brouillard SSE + sync à la génération / `setIdentity`
- Doc `ACE-DOGTAGS.md`

## 0.7.4 — Génération robuste + journal technique

- Durcissement `generateData` / `setPair` (plus de `_data` indéfini après échec)
- Journal technique SSE (tampon + ACE Self « Journal technique ») — WARN/ERROR toujours en RPT
- Overlay debug Zeus : libellés clairs si non généré / données invalides

## 0.7.3 — Région Russie + correctif setToPairs

- Combo Eden **Région narrative** : entrée `Russie / théâtre Est` (`RUSSIA`) + `Aléatoire`
- Pools russes enrichis (prénoms, noms, alias, rôles, apps, SMS)
- Correctif `Inc_setToPairs` : plus d’appel `[_arr, [key,value]]` (STRING attendue en index 1)

## 0.7.2 — Passerelle BII Identifi

- Addon `compat_bii` : import scans / preuves / modules BII → modèle SSE
- Item `BII_Identifi_Device` accepté comme SEEK / empreintes / face / ADN / terminal
- Export optionnel SSE → variables `BII_Identifi_*`
- Doc `BII-BRIDGE.md`

## 0.7.1 — Catalogues d’ères (Irak / Russie)

- Région `RUSSIA` dans les pools narratifs
- Builtins Irak 2010–2020 et Russie / Est 2020–2024
- Correctif normalisation des clés HashMap dans `createModel`

## 0.7.0 — UI multi-écrans (SSE Record unique)

- Terminal SSE terrain (hub) + navigation Digital / SEEK / Site / Graph / Preuves / Mission / Zeus
- SEEK II enrichi : identité, score de correspondance, qualité moyenne, retour Terminal
- Item `COMSPEC_SSE_Terminal` + ACE Self / cible « Ouvrir terminal SSE »
- Module Zeus « Zeus SSE Control (UI) »
- Doc `UI-SCREENS.md`

## 0.6.0 — Moteur Intel

- Niveaux Tactical → Field → Detailed → Fusion
- Métadonnées INTEL_VALUE / TIME_SENSITIVITY / CONFIDENCE / RELEVANCE
- Triage auto, pivot, fusion, dédup, timeline, géospatial
- États preuve / chiffrement / caches / deleted / anti-exploit narratif
- Entités logiques, TECHINT, biométrie enrichie, personnes UNKNOWN
- Packs scénario + générateur brief Zeus
- Site Manager, Spoil Control, AAR, sandbox
- API `isDiscovered`, hooks Zeus, events CBA, SDK `registerModClasses`
- Doc `INTEL-ENGINE.md`

## 0.5.0 — Entités élargies (véhicule / radio / arme / site)

- `resolveEntityType` + générateurs Vehicle / Radio / Weapon / Building
- ACE : fouille véhicules, lecture documents, exploitation radio, kit SSE self
- Lazy gen sur véhicules / caisses / armes au sol
- Alias radio TFAR / ACRE
- Doc `ENTITIES.md`

## 0.4.1 — Compat matériel multi-mods

- Items SSE en `CBA_MiscItem` (ACE Arsenal)
- Alias d’équipement : cTab / ItemAndroid / ACE microDAGR / kits médicaux
- Réglages CBA : substituts ON/OFF + alias custom
- `getEquipmentAliases` / `resolveEquipment`
- Correctif empreintes (SEEK II / kits ne bloquaient plus à tort)

## 0.4.0 — Network / Athena

- Payloads Athena personne / biométrie / digital
- `submitPersonRecord`, `submitBiometricsSim`, `submitDigitalAcquisition`
- Clés d'idempotence + référence dossier SSE
- Adapter Overwatch : extension typée → sendIntel → SendSSE
- File QUEUED + flush auto 45 s
- Réglages CBA mapId / case / preferExtension

## 0.3.0 — Biometrics / SEEK II

- Dialogue terminal SEEK II
- Capture empreintes / iris / visage / ADN / ALL
- Identification simulée (inconnu / signalé / recherché)
- Menus ACE biométrie dédiés
- `compareBiometrics`, `getBiometricSummary`

## 0.2.0 — Digital Exploitation

- Exploitation téléphone complète (contacts, SMS, appels, photos, locs)
- Exploitation ordinateur (système, users, fichiers, browser, mail, USB, credentials)
- Collecte USB/SD inventaire
- Fog of war digital progressif
- Durées CBA ordinateur

## 0.1.0 — Base

- Architecture multi-addons, data model, générateur, Zeus, Eden, ACE inspect, items, journal, modèles
