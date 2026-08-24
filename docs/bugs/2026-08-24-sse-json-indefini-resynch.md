# Resynch SEEK — variable `_json` indéfinie

**Date :** 2026-08-24  
**Statut :** corrigé (sources) — rebuild PBO `comspec_sse_network` requis

## Contexte

Bouton **Resynch** sur l’ATAK. Le renvoi SEEK vide la file SSE (`flushQueue` → `sendViaOverwatch`).

## Symptôme

Overlay SQF en jeu :

- Fichier `z\comspec_sse\addons\network\functions\fn_sendViaOverwatch.sqf`
- `Error Variable indéfinie dans une expression: _json` (l.70 et l.87)
- Le Resynch affiche quand même « envoyé vers le poste de commandement »

## Cause

`_json` était déclaré au niveau de la fonction (`private _json = if then else`), puis lu **dans** le `exitWith` de l’envoi extension. Deux effets se cumulaient :

1. Un `private` du parent n’est pas toujours visible dans un `exitWith`.
2. Si la sérialisation JSON plantait, SQF continuait sans jamais créer `_json`, et la ligne `[_json]` explosait ensuite.

## Correctif

- `_json` est déclaré **dans** le `exitWith`, initialisé à `""` **avant** l’appel de sérialisation.
- Si le texte JSON est vide ou invalide : échec journalisé, pas d’overlay.
- `toJsonApprox` accepte une clé / une valeur non texte au lieu de planter sur `replaceString`.

## Fichiers touchés

- `mod/@COMSPEC_SSE/addons/network/functions/fn_sendViaOverwatch.sqf`
- `mod/@COMSPEC_SSE/addons/network/functions/fn_toJsonApprox.sqf`

## Vérification

Rebuild `comspec_sse_network.pbo`, relancer Arma (pas seulement la mission). Resynch : plus d’overlay `_json` ; les fiches en file partent ou restent en attente avec un message dans le journal COMSPEC.
