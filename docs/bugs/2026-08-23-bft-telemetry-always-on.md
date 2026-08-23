# Télémétrie BFT : envoi systématique même immobile

## Contexte

Le connecteur Overwatch remonte la position des opérateurs vers Athena pour
le suivi Blue Force. L’archive utilisait une boucle `while {true}` toutes
les 5 s. Le live avait déjà un PFH et un seuil de distance, mais chaque
envoi restait un POST HTTP immédiat, payload complet, avec un historique
écrit à chaque passage.

## Symptôme

À 60 opérateurs, une mise à jour toutes les 5 s produisait ~12 requêtes/s
rien que pour les positions (~43 200/h), y compris pour des unités
immobiles. Le volume croissait linéairement avec le nombre de joueurs, en
plus des marqueurs, photos, chat et sondes.

## Cause

1. La boucle client renvoyait un paquet complet (position, rôle, santé,
   carburant, munitions, radio) dès qu’un heartbeat ou un seuil de 5 m
   était atteint, sans distinction infanterie / véhicule / aérien ni cap.
2. La DLL transformait chaque `UpdatePosition` en POST HTTP immédiat.
3. Le plafond Athena (150 écritures/min, toutes routes confondues) n’avait
   ni limite dédiée aux positions, ni `Retry-After`.
4. Chaque POST position insérait une ligne d’historique motion, même pour
   un heartbeat sans déplacement.

## Correctif

- Profils réseau CBA (Économie / Standard / Tactique / Temps réel /
  Personnalisé), politique hybride par défaut, autorité serveur.
- Envoi seulement si déplacement, cap, état (radio / médical / véhicule)
  ou heartbeat périodique.
- DLL : coalescing des positions + flush périodique ; respect de
  `Retry-After`.
- Athena : 30 positions/min + rafale 10/5 s par opérateur, réponse 429
  avec délai ; historique ignoré pour un heartbeat quasi immobile.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/XEH_preInit.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/XEH_postInit.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_applyNetworkProfile.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_updatePosition.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_startSyncLoops.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_extensionCallback.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_onPlayerRespawn.sqf`
- `mod/UptoDate/COMSPECExtension/Extension.cs`
- `app/Support/AtakArmaWriteGuard.php`
- `app/Controllers/Api/AtakApiController.php`
- `app/Services/Tactical/AtakUnitMotionService.php`
- `tests/Unit/AtakUnitMotionHistorySkipTest.php`

## Vérification

- Test unitaire : heartbeat sans déplacement ignoré ; premier point et
  vrai mouvement conservés.
- Relire : une unité immobile en profil Standard ne doit plus poster
  toutes les 5 s, seulement un signal de présence au heartbeat.
- Un 429 Athena doit caler le client sur le délai annoncé.

## Statut

Corrigé (côté sources). La DLL 2.0.11 doit être recompilée et le PBO
connect 1.4.51 reconstruit pour le terrain. Le bus d’événements ACE/ACRE
dédié n’est pas encore branché : le médical/radio voyagent encore dans
le paquet position, mais seulement quand l’état change ou au heartbeat.
