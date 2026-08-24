# Déplacement IA depuis l’ATAK

## Contexte

Les IA alliées peuvent être affichées sur la carte ATAK (Zeus : « IA alliée sur l’ATAK »). Les ordres de déplacement n’allaient qu’aux opérateurs humains.

## Symptôme

Impossible d’envoyer une IA vers un point en cliquant sur la carte ATAK. Les ordres « Se déplacer » ne faisaient pas bouger le groupe en jeu.

## Cause

Les ordres C2 étaient filtrés pour les joueurs (indicatif / Steam). Aucun poll jeu n’exécutait de waypoint sur un groupe d’IA.

## Correctif

- Destinataire **Unité alliée** (ordres + point de mission).
- Clic droit carte : **Déplacer une unité alliée ici**.
- Clic droit sur l’IA : **Ordre de déplacement…** puis clic sur la carte.
- Le jeu reprend l’ordre et pose un waypoint au groupe (là où il est local).

## Fichiers touchés

- `app/Controllers/Api/AtakApiController.php`, `app/Repositories/AtakOrderRepository.php`
- `public/assets/js/atak-*.js`, `views/atak.php`
- `mod/UptoDate/COMSPECExtension/Extension.cs` (`GetAiOrders`)
- `connect/functions/fn_pollAiOrders.sqf`, `fn_applyAiMoveOrder.sqf`, `fn_findAllyTrackUnit.sqf`

## Vérification

1. Zeus : marquer une IA « IA alliée sur l’ATAK ».
2. Sur la carte ATAK, l’unité apparaît.
3. Clic droit → choisir l’unité dans **Déplacer une unité alliée ici**.
4. En jeu, le groupe se met en route. L’ordre passe en « En cours » dans l’onglet Ordres.

Prérequis : Overwatch **connect 1.4.56** + extension rebuild, relancer Arma.

## Statut

Corrigé (sources) — rebuild mod + déploiement web requis
