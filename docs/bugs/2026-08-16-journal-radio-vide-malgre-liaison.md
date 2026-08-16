# Journal Radio vide alors que Liaison montre le message

## Contexte
Envoi Group Messages in-game (« test ») : Liaison affiche **RADIO** « Message de groupe — N-10 », mais le panneau **Journal radio** reste sur « Aucun message ».

## Causes
1. **Masquage local corrompu** : « Vider » le tchat (ou un échec de chargement) stockait `Date.now()` comme seuil d’id → tous les vrais ids BDD (< 1e9) étaient filtrés.
2. **Perte de paquet roleplay** : `applyRoleplayEffects` renvoyait **503** sur `/api/chat` → le journal restait sur l’état vide initial, alors que `/api/atak/activity` pouvait réussir.

## Correctif
- `atak-chat.js` : ne plus utiliser `Date.now()` ; auto-réparer les seuils > 1e9 ; conserver le cache si 503.
- `AtakApiController::applyRoleplayEffects` : plus de 503 « packet_lost » sur les lectures.
- Enrichissement `group` dans `chatIndex`.
- Overlay web écran cassé : vraie texture `broken-screen.png`.

## Vérification
1. F12 → Application → Local Storage → supprimer les clés `atak_chat_cleared_before_v1_*` (ou recharger après correctif).
2. Recharger ATAK web → Journal radio doit lister le message de groupe.
3. Bouton poubelle : vide l’affichage sans masquer les futurs messages.

## Statut
corrigé
