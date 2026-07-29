# Écran cassé masquait l’opérateur du web

## Contexte

Réalisme ATAK : niveau « écran endommagé » doit transmettre la **position seule** sur le portail (marqueur dégradé), pas faire disparaître l’opérateur.

## Symptôme

- In-game, panneau **État ATAK** affichait « éteint · écran endommagé » alors que l’interface restait utilisable.
- **Hors liaison** côté terminal ; opérateur **absent de la carte web** jusqu’à réparation Zeus/ACE.
- Après **Réparé**, réapparition immédiate sur le web.

## Cause

1. Lors d’un écran cassé (choc, blessure torse, Zeus), le mod posait **à la fois** `screen_destroyed=true` **et** `powered_on=false`.
2. `fn_canTransmit` testait **éteint avant écran cassé** → `can_transmit=false` → plus de sync position → TTL web expiré → statut `offline` → marqueur masqué.
3. Le panneau État ATAK cumulait les libellés « éteint » + « écran endommagé » alors que seul l’écran était concerné.

## Correctif

- **Écran cassé** : ne plus couper `powered_on` (sauf destruction totale appareil).
- **`fn_canTransmit`** : traiter l’écran endommagé **avant** l’état éteint ; mode `position_only` + `link_state=degraded`.
- **`fn_updatePosition`** : propager `COMSPEC_LinkState` dégradée après envoi position.
- **`fn_athena_updateStatus`** : libellé « Liaison dégradée » pour l’état `degraded`.

## Fichiers touchés

- `mod/.../connect/functions/fn_canTransmit.sqf`
- `mod/.../connect/functions/fn_checkAtakDamage.sqf`
- `mod/.../connect/functions/fn_applyZeusAtakEffect.sqf`
- `mod/.../connect/functions/fn_updatePosition.sqf`
- `mod/.../atak_athena/functions/fn_athena_updateStatus.sqf`

## Vérification

1. Rebuild PBO `connect` + `atak_athena`, redémarrage Arma complet.
2. Zeus → **Casser l’écran** sur un opérateur en liaison.
3. Attendu in-game : terminal « **écran endommagé** » (sans « éteint »), liaison **dégradée**.
4. Attendu web : opérateur **toujours visible** sur la carte (position seule), pas de disparition après TTL.
5. Réparation ACE toolkit : retour liaison complète.

## Statut

Corrigé (mod 1.4.11+)
