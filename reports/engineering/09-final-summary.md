# Synthèse finale — audit ATAK / Athena, 27 octobre 2026

## Verdict

Les correctifs retenus sont dans les sources de `release/2.7.0`, non commités. La revue indépendante a **accepté** le lot après trois passages (`07-independent-review.md`). Le pack autonome a été reconstruit. Aucune partie Arma 3 n’a validé le comportement en jeu. Le portail n’a pas été redéployé depuis cette session.

## Ce qui change pour l’opérateur

- Le raccourci Overwatch ouvre le téléphone du pack.
- Les positions déjà tenues par la liaison apparaissent sur la carte du téléphone.
- Un poste joueur ne réécrit plus le temps de jeu ni les distances des autres.
- Après une coupure, la liaison reprend dans la minute et demie, ou selon le délai demandé par le portail (jusqu’à dix minutes).
- Quand le relais serveur des marqueurs est actif, le poste ne double plus les envois.
- Seules les pages de liaison, de carte et de 9-LINE s’ouvrent dans le navigateur du PC.

Bulletin public : UPDATE **1014** dans `DevDispatchCatalog.php`.

## Accepté par la revue

Identité du compte (SEC-041), temps de jeu / distances (SEC-042), avis d’administration (SEC-044), relevé réseau du site (SEC-045), ouverture du navigateur (SEC-046), chemins locaux (SEC-047 / DLL-001), synchro native sans `READY` (ARCH-002), pause `Retry-After`, et marqueurs (hub seul pour les repères globaux).

## Laissé ouvert volontairement

- Index unique des fiches d’effectifs (NET-003) : doublons possibles en production, pas de migration aveugle.
- Rejeu d’un lot partiellement refusé (NET-004) et autres tickets réseau hors lot.
- Plan de camp partagé encore écrivable par un téléphone du camp (SEC-043, comportement du téléphone officiel).
- Flush serveur des marqueurs encore bloquant (reste de SQF-007).
- Pas de mesure FPS ni de banc réseau.

## Pack

`mod/COMSPEC_ATAK_Native/@COMSPEC_ATAK_Native` — cinq modules vérifiés, deux liaisons recompilées. Quitter Arma complètement avant de recharger ce dossier. Le site doit être à jour pour le filtre du temps de jeu et des distances.

---

## Append — reprise 27/10/2026 soir

Lot combat + DLL-005/007 integres en sources sur la branche courante. Revue A8 : ACCEPT. **Arma non execute** : aucun FPS avant/apres. Ouverts : mesures hitch reelles, NET-003/004, flush serveur marqueurs, SEC-043.

Bulletin public UPDATE **1181**.

---

## Append — clôture follow-up

Correctifs supplementaires en sources : télémétrie combat non bloquante, dégâts ATAK legacy court-circuités si natif, routes admin sans doublon, parité temps de jeu / distances (SEC-042). Bulletin UPDATE **1182**. Ouverts documentés : DLL-C01/C02, NET-003/004, SEC-043, flush hub serveur, mesures hitch Arma.

---

## Append — clôture reprise complete (A4–A7 + LOT-DLL/MEM)

Agents A4–A7 livres. LOT-DLL (C01/C02) + LOT-MEM (Iceman purge, log queue) en sources. LOT-NET documente ouvert. Phase 3 : non mesure (Windows / pas unshare). Revue independante a suivre. Bulletin UPDATE **1183**.

---

## Append — reconciliation A4–A7 (apres Steam 2.9.6)

- NET-001 passe a **partial** (TTL BFT 180 vs Retry-After 600) — [A4](a8f9943c-189c-4c0d-877e-c7ba623e8874).
- UI-002 **partial** ; UI-004..008 **open** — [A5](0f833c3a-9d76-4b26-9801-668c0104c0d5) / [A6](25b01e45-5842-414a-b85b-1af29bf0c4d4).
- MEM-C01 deja corrige (Iceman + PendingLogLines) avant le rapport A6 ; MEM-C02 (musique / BT) laisse ouvert.
- SEC-041..047 : [A7](b49fe044-24da-4c04-bbe4-c10df052f645) confirme les statuts ; SEC-043 partial produit.
- LOT-NET : pas de migration sans decision produit. Pack Workshop 2.9.6 deja publie.

---

## Append — Vague A (UI hygiène + MEM-C02)

Decision produit : LOT-NET differe ; vague A maintenant.

- UI-002 / 004 / 005 / 007 / 008 : `fixed_in_source` (atak-v2 + slide.html).
- MEM-C02 : `fixed_in_source` (MusicQueue 32, Bt TTL 30 min / max 32) ; build native OK.
- UI-006 laisse ouvert (IndexedDB).
- MapBus.ack toujours non branche (optionnel).
- NET-001 / 003 / 004 inchanges. Pas de republish Steam dedie.
