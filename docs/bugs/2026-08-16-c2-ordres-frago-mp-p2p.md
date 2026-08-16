# C2 unifié — Lot 1 ordres/FRAGO + Lot 2 MP P2P

## Contexte

Objectif produit : canoniser les ordres / FRAGO sur `atak_orders` (onglet Ordres Athena) et archiver les messages privés cTab sur le fil ATAK web (pas la messagerie sociale).

## Lot 1A — FRAGO IceMan listés dans Ordres

### Symptôme
Les FRAGO émis depuis IceMan / alerte tactique apparaissaient surtout dans le chat / journal, pas dans l’onglet Ordres.

### Cause
`chatStore` upsertait seulement les corps `ORDER|…`. Les `ALERTE TACTIQUE|FRAGO|…` restaient hors `atak_orders`. De plus, `ORDER_ID=` était écrasé (pipes → ` · `) dans `sendTacticalAlert`.

### Correctif
- Upsert FRAGO → `atak_orders` (`source=game`) dès qu’une alerte FRAGO arrive.
- Préservation `ORDER_ID=` / `ATHENA_ORDER_ID=` dans `sendTacticalAlert`.
- Parser SMEAC enrichi (HTML IceMan, libellés EN/FR).
- Badge « Terrain » / « Poste de commandement » dans la liste Ordres.

## Lot 1B — Ordre web → IceMan FRAGO + ACK

### Symptôme
Le miroir Athena→IceMan utilisait un `CBA_fnc_localEvent` sous `SuppressMirror`, donc le pont annulait immédiatement l’insertion Reports. Pas d’ACK IceMan → Athena.

### Correctif
- Appel direct `Iceman_fnc_alerts_receive` avec corps FRAGO + `ATHENA_ORDER_ID=`.
- Ouverture message cTab (msgState ≥ 1) → `UpdateOrderStatus ACK`.
- Hors tâches drone / signaux terminal.

## Lot 2 — MP P2P → fil ATAK

### Correctif
- Wrap `cTab_msg_Send` : après envoi joueur, archive `MP|from|to|texte` via `sendIntel`.
- `MpMessageParser` + affichage carte « Message privé » dans le chat ATAK.
- Filtre optionnel `?callsign=` pour ne remonter que les MP concernés.

## Fichiers touchés

**API / web**
- `app/Support/TacticalAlertParser.php`
- `app/Support/MpMessageParser.php`
- `app/Controllers/Api/AtakApiController.php`
- `public/assets/js/atak-orders.js`, `atak-chat.js`, `tacmap-tactical-alerts.js`
- `public/assets/css/atak.css`

**Mod Overwatch (`atak_athena` / `connect`)**
- `fn_sendTacticalAlert.sqf`
- `fn_athena_onOrderReceived.sqf`
- `fn_athena_syncIcemanOrderAck.sqf` (nouveau)
- `fn_athena_archiveMpMessage.sqf` (nouveau)
- `fn_athena_installHqContact.sqf`
- `XEH_postInitClient.sqf`, `config.cpp` (v1.0.18)

## Vérification

1. Déployer API Athena + recompiler PBO `atak_athena` (+ `connect` si sendTacticalAlert).
2. Lot 1A : FRAGO IceMan → apparaît dans onglet Ordres (badge Terrain).
3. Lot 1B : ordre web → Reports IceMan FRAGO ; ouvrir le message → statut ACK côté web.
4. Lot 2 : MP cTab joueur→joueur → carte « Message privé » dans le chat ATAK TOC.
5. Hors scope confirmé : tâches drone inchangées.

## Statut

implémenté — à déployer / recompiler pour validation live
