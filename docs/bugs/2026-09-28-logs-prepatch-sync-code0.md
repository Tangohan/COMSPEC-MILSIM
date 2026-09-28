# Logs pré-patch — sync / freeze (session Arsenal 28/09/2026)

**Date :** 2026-09-28  
**Statut :** documenté (preuves utilisateur) · correctifs 1.6.14 livrés séparément

## Contexte

Journaux `COMSPEC_2026-09-28_162501_*.log` / session 16:33, mission **Arsenal**, avant Overwatch 1.6.14.

## Symptômes visibles dans le journal

1. Pack ancien : `connect v1.6.9` · Overwatch **1.6.9** · Athena **1.0.165** (pas 1.6.14 / 1.0.167).
2. Rafales `HTTP POST — code 0` sur `/public/api/atak/marker` → « Poste momentanément injoignable », backoff 1 s → 45 s → **600 s**, souvent **plusieurs lignes en même temps**.
3. Plus tard : « Athena est saturé — synchronisation ralentie » (rate limit ×30 s).
4. `Pas de terminal ATAK — boucles de sync en attente` + reprise canal poste.
5. `Steam UID absent — fiche non transmise` (répété).
6. Panneau arsenal enregistré pendant ces échecs de liaison (`[ARSENAL] Panneau Athena ACE Arsenal enregistré`).

## Cause (lecture opérateur → atelier)

| Observation | Interprétation |
| --- | --- |
| HTTP **code 0** | Pas de réponse HTTP (réseau / TLS / timeout / poste injoignable), pas un 4xx/5xx métier. |
| Backoff ×600 s empilé | Plusieurs files d’envoi marqueurs échouent en parallèle → spam WARN + charge client. |
| Rate limit ensuite | Quand la liaison revient, file d’attente / volume trop dense côté Athena. |
| Arsenal + sync tenues (pre-1.6.14) | Ouverture arsenal + sync organisation = freezes rapportés (correctif lazy + loader). |
| Resync historique (pre-1.6.14) | Cumul marqueurs après longue session (correctif budget resync). |

## Correctifs déjà livrés (1.6.14 / 1.0.167)

- Arsenal : pas de sync tenues tant que le bouton n’est pas cliqué + loader.
- Vibrations anti-spam.
- Resync marqueurs budgété.
- Métriques de charge (jeu / Overwatch Beta / BO).

## Correctif complémentaire (1.6.15 / Extension 2.0.52)

- Marqueurs en file drainée (coalescés par `arma_name`) — plus de POST parallèles au boot.
- Escalade backoff : **un cran max par fenêtre** (plus de fusée 45→600 s en ms).
- Callbacks / journal SQF anti-spam « liaison différée ».
- URL marqueur via `TryBuildRequestUri` (préfixe `/public` préservé).

## Ce que ces logs ne prouvent pas encore

- La cause réseau exacte d’un **code 0** isolé (DNS / TLS / poste down) — désormais amortie côté client.

## Vérification après patch

1. Quitter Arma complètement, charger Overwatch **1.6.14** · Athena **1.0.167**.
2. Dans le dump boot : `Version des mods : Overwatch 1.6.14 · Athena 1.0.167`.
3. Arsenal fluide sans clic ATHENA ; au clic → loader puis collections.
4. Si `code 0` persiste : isoler réseau / URL du poste (hors freeze arsenal).

## Fichiers liés

- `docs/bugs/2026-09-28-arsenal-freeze-sync.md`
- `docs/bugs/2026-09-28-sync-lourde-longues-sessions.md`
- Logs utilisateur (session Arsenal 28/09/2026)
