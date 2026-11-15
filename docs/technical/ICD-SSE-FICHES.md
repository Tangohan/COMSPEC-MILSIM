# ICD-SSE-FICHES-001 : les fiches du portail SSE sur le téléphone ATAK

Contrat entre le site (Athena), COMSPEC Link (`COMSPECExtension`), l'ATAK natif (app **Fiches**) et le pack SSE. Lecture seule côté jeu, sauf le
journal d'un partage. Version 1. Voir aussi `ICD-SSE-CATALOGUE.md` (même canal, mêmes pages).

## 1. État avant cette livraison (audit)

Ce que le portail permet de créer ou de modifier, et ce qui en arrivait en jeu :

| Donnée du portail | Canal d'arrivée en jeu | Lecteur côté mod | Écran | État |
|---|---|---|---|---|
| Identité / personne (nom, alias) | `GET /api/sse/identity/pack` (`identities`, `watch`) par `GetIdentityPack` (DLL) | `fn_idAthenaPack` | SEEK II, contrôle d'identité | OK pour les identités de l'opération ; hors état civil du membre |
| Liste de surveillance, personnes prioritaires, dossiers d'intérêt | `GET /api/sse/wanted` (`SseWantedNoticeService`) par `GetWantedNotices` (DLL) | `fn_wantedAction` | App Recherchés | OK (nom, alias, niveau, éléments connus, photo en .paa) |
| Recherchés et HVT de l'opération (`sse_id_op_wanted`) | pack d'identités, section `wanted` (réf. `OPW-n`) | `fn_idAthenaPack`, `fn_idEvaluate` | « AVIS : personne RECHERCHÉE » | OK, par le nom imprimé |
| **Avis de recherche** (`sse_wanted_notices`) : type, menace, armé | catalogue `GET /api/game/v1/sse/catalogue` (#1681), section `wanted` : id, réf., nom, niveau, type | `fn_sseCatalogue` | listes des modules Zeus / Eden seulement | **Partiel** : jamais sur le téléphone |
| Avis : **motifs**, **mesures à prendre**, zone, dernier lieu connu, validité, autorité | aucun (le catalogue n'expose volontairement ni motif ni consigne ni photo) | aucun | aucun | **Absent** |
| Avis posé sur la personne **contrôlée** | `POST /api/sse/identity/checks` répond `wanted` (#1672) | aucun : la DLL ne rend que `OK\|id\|réf` | aucun | **Absent** (« RECHERCHÉ » ne s'affichait pas) |
| Fiches PDF émises (vigilance, identification, renseignement, contrôle, #1711) : texte imprimé, classification, validité | aucun | aucun | aucun | **Absent** |
| Mentions aux fichiers, dossiers, papiers, véhicules, adresses, téléphones, personnes liées | véhicules, adresses, personnes liées : aucun ; papiers : pack (`documents`, `kits`) ; dossiers : catalogue (`cases`) | `fn_idAthenaPack`, `fn_sseCatalogue` | SEEK II, modules | **Partiel** : papiers et dossiers (références), liens absents |
| Classification de diffusion | aucun (jamais envoyée) | aucun | aucun | **Absent** |
| Partage d'une fiche entre téléphones | Bluetooth (fiches SEEK, SSE, contrôle ID) et SMS (`fn_p2pSend`) | `fn_btItems`, `fn_p2pReceive` | app Bluetooth, Messages | **Partiel** : pas la fiche du portail |
| Cache et file hors ligne | `fn_queueOffline` / `fn_flushQueue` (envois seulement), catalogue gardé sur le poste | `fn_sseCatalogue` | — | **Partiel** : rien pour la lecture des fiches |

## 2. Après cette livraison

| Donnée | Canal | Écran | État |
|---|---|---|---|
| Avis : type, menace, armé, motifs, mesures à prendre, zone, dernier lieu connu, validité, autorité, alias | `sse_fiches` (op `list`, `detail`) / `GET /api/game/v1/sse/fiches` | app Fiches | OK |
| Fiches PDF émises : texte imprimé, classification, validité, signalement, observations, mention | idem | app Fiches | OK, filtré par la classification |
| Véhicules, adresses, personnes liées (libellés courts, 3 au plus) | idem | app Fiches | OK, adresses dès Encadrement, personnes liées dès Confidentiel |
| Avis sur la personne contrôlée | `sse_fiches` op `wanted` après `OK\|id\|réf` | contrôle d'identité (bannière, carte de l'avis) | OK, sans nouvelle DLL |
| Partage | Bluetooth (genre `fiche`) et SMS ; journal `sse_fiche_share` | app Fiches, Bluetooth, Messages | OK : référence, titre, genre, niveau seulement |
| Cache | mémoire de la partie ; dernière version des fiches de niveau le plus bas gardée sur le poste 24 h | app Fiches | OK |
| Téléphones d'une fiche, mentions aux fichiers (masques), photos | non envoyés | — | volontairement absent |

Non transportés, par conception : état civil (naissance, pièce), photo (Arma ne charge pas d'image HTTP), nature d'une identité (couverte, falsifiée),
toute identité de membre (`sse_identities.user_id > 0`).

## 3. Contrat

Authentification : clé de communauté ou jeton de jeu (même garde que le catalogue). La communauté est celle de la clé, jamais celle de la requête.
Débit : 120 lectures par minute et par compte, 12 partages par 10 minutes. Maintenance : 503. Niveau de lecture : celui du compte du jeton de jeu
(`SseMemberClearance`, plafond de lecture de la fiche effectif) ; **sans compte, le plus bas** (`interne`).

### Lecture

`GET /api/game/v1/sse/fiches?since_rev=&format=sqf&page=&ref=`, `GET /api/game/v1/sse/fiches/{ref}` (`/` écrit `%2F`), ou, côté jeu,
`POST /api/sse/identity/checks` avec `{"kind":"sse_fiches","op":"list"|"detail"|"wanted","rev":"","page":0,"ref":"","check":0}` (commande `SubmitIdCheck`).
Réponse du canal : `{id, ref, page, next, pages}` ; `ref` est une page `parseSimpleArray` de 7 400 octets au plus :

```
[1, "révision", "généré le", ttl, page, page suivante (0 = fin), [fiche…], [autorité fr, autorité en, niveau, marquage, total, tronqué], [[type, fr, en]…]]
```

Une fiche est le tableau des colonnes `ref, kind, type, name, alias, threat, armed, level, mark, reason, measures, desc, remarks, note, zone, place, seen,
issued, until, updated, vehicles, addresses, people, auth, cut` (`SseFichesGameService::COLUMNS`). `kind` : `avis | identification | vigilance | profile | control`.
`cut` : un texte a été raccourci (la liste porte 360 caractères de motif, le détail 800). `\p` est une barre verticale, `\n` un retour à la ligne.
Révision inchangée (`rev`) : page `[…, [], [], [], true]`. `ETag` `W/"ssefiches-<révision>"`, `304` si `If-None-Match` correspond (route JSON).

`op: "wanted"` : `[1, "wanted", avis ?, réf, type, menace, armé, mesure]` pour l'identité reconnue par le contrôle `check` de la communauté (jamais pour un membre).

### Partage

`{"kind":"sse_fiche_share","ref":"","to":"","via":"bt|nfc|sms"}` : le site vérifie que l'expéditeur lit la fiche (sinon `403`, journalisé `fiches.share_refused`),
limite le débit (`429`) et journalise `fiches.share` dans `sse_audit_log`. Il ne transporte rien : le téléphone envoie la référence, un titre court, le genre
et le niveau (le nom seulement pour un avis ou une fiche de niveau le plus bas). Le destinataire ouvre la fiche **dans sa propre liste**, donc selon son habilitation.

### Journal

`sse_audit_log` (`object_type` `game_fiche`) : `fiches.read` (une fois par 10 minutes et par porteur), `fiches.detail`, `fiches.share`, `fiches.share_refused`, `fiches.wanted_hit`.

## 4. Sécurité

- Filtre à la source par la classification : une fiche dont le niveau dépasse l'habilitation du porteur n'est ni listée, ni donnée en détail (même `404` qu'une référence inconnue).
  Les avis de recherche sont des documents de diffusion : niveau `interne` (constante `SseFichesGameService::NOTICE_LEVEL`).
- Une fiche PDF émise est donnée au niveau où elle a été imprimée : son texte est déjà caviardé à ce niveau.
- Identités de membre, états civils, photos, nature des identités : jamais. Donnée cachée par le MJ : barre de caviardage, sans alias ni lien.
- Les marquages du bandeau (`UNCLASSIFIED//FOUO`, `CONFIDENTIAL`, `SECRET//NOFORN`, `TOP SECRET//NOFORN`) sont fictifs (jeu de rôle) et définis côté site (`SseFichesGameService::MARKINGS`) :
  un changement de niveaux ne demande aucune reconstruction du mod.
- Sur le poste, seules les fiches du niveau le plus bas sont gardées sur disque (24 h) ; les autres ne quittent pas la mémoire de la partie.
- Limite connue : les libellés de véhicules, d'adresses et de personnes liées d'une **fiche PDF émise** sont ceux imprimés sur le PDF (instantané) ; une personne liée y est nommée telle qu'imprimée.

## 5. Côté jeu

Pas de nouvelle commande de la DLL : `SubmitIdCheck` porte les trois canaux. Fichiers : `functions/fiches/fn_fichesAction.sqf` (données, cache, partage),
`fn_pageFiches.sqf` (écrans), `fn_btItems` / `fn_btAction` (Bluetooth), `fn_p2pReceive` / `fn_pageChat` (SMS), pack SSE `fn_idWantedCheck` (marque « recherchée »).
Aucun toast, hint ni systemChat : retours dans la ligne d'état de l'écran.
