# Fantômes hors liaison sur la carte ATAK web

## Contexte

11 septembre 2026. Sur `/atak/`, des opérateurs restent visibles alors qu’ils sont hors liaison, et le poste peut afficher une perte de liaison alors que le journal Liaison reçoit encore des remontées.

## Symptôme

- Symboles d’opérateurs (et pastilles) encore sur la carte après déconnexion.
- Impression de « perte de liaison » côté carte / effectifs alors que le journal Liaison continue de recevoir.
- Cercles de zone de vue / aéronefs / marqueurs peuvent aussi rester après la fin du signal BFT.

## Cause

1. **Carte C2 (`ATAK_MAP_C2_V2`)** : dans `atak-c2-bridge.js`, un joueur `LOST` restait volontairement affiché (« ne jamais faire disparaître un joueur »). Le rendu legacy retirait déjà les humains hors liaison ; le pont C2 non.
2. **Liste Effectifs** : une carte hors liaison était stylée comme « signal différé » (`delayed`), ce qui brouille la lecture.
3. Distinction utile : le journal Liaison trace aussi les appels réseau / incidents d’affichage ; ce n’est pas le même signal que le statut BFT « En liaison » (TTL ~2 min sans position).

## Correctif

- Pont C2 : retirer de la carte tout contact `LOST` sauf IA suivies (`keepLastKnown`), comme le legacy.
- Liste : classe CSS `offline` pour les hors liaison (plus de déguisement en `delayed`).
- Viewshed : déjà expiré côté serveur via `expires_at` (pas de fantôme permanent si migrations OK).

## Fichiers touchés

- `public/assets/js/map/atak-c2-bridge.js`
- `public/assets/js/atak-units.js`
- `docs/bugs/2026-09-11-fantomes-hors-liaison-carte-web.md`

## Vérification

- Opérateur en liaison → symbole présent.
- Plus de position depuis > 2 min → symbole retiré de la carte C2.
- Filtre « En liaison » : hors liaison absents ; « Tous » : hors liaison visibles mais clairement hors liaison.
- Régler « Simulation de liaison » (retard / pertes) si activé : n’affecte que l’affichage carte.

## Statut

corrigé
