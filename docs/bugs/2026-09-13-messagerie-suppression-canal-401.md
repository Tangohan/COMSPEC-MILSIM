# Messagerie — pas de suppression de canal + envois coupés (401)

**Date :** 2026-09-13  
**Statut :** corrigé (pack 1.5.68 + portail)

## Contexte

Dans l’application Messagerie du téléphone ATAK, un opérateur peut créer un canal radio personnalisé, mais ne pouvait pas le supprimer. Les envois semblaient aussi peu fiables : journaux `SendChat` sans confirmation, et rafales `401` sur les transmissions juste après une connexion Athena (mot de passe).

## Symptôme

- Aucun bouton pour retirer un canal créé en jeu (seul « Effacer l’affichage local » existe).
- Après connexion : `HTTP POST … 401` (manifest, scene, marker, photos…), puis `Session refusée par le poste — transmissions arrêtées`.
- `→ SendChat` sans ligne `OK · SendChat` dans le journal.

## Cause

1. **Suppression** : pas d’API, pas d’appel extension, pas de bouton UI — uniquement création + purge d’affichage local.
2. **401 après mot de passe** : la réponse d’auth ne renvoyait pas la clé communauté (contrairement à Appairer). La DLL effaçait la clé et n’envoyait que le Bearer ; certains chemins `/public/` ou un jeton encore froid provoquaient des 401 en chaîne.
3. **Feedback SendChat** : envoi asynchrone sans journalisation de succès côté SQF.

## Correctif

- Portail : `DELETE` métier via `POST /api/chat/channels/delete` (canaux `custom` uniquement) ; `api_key` dans la réponse d’auth jeu.
- Extension 2.0.34 : `DeleteChatChannel` ; `mapId` réel sur Send/Create/Delete ; conservation / application de `api_key` ; miroir Bearer → `X-ATAK-TOKEN` + clé communauté si connue.
- Pack : bouton **Supprimer**, SQF dédiés, `action` + `onButtonClick`, journal `OK · SendChat`.

## Fichiers touchés

- `app/Repositories/AtakDataRepository.php`
- `app/Controllers/Api/AtakApiController.php`
- `app/Services/Game/GameAuthService.php`
- `routes/web.php`
- `mod/UptoDate/COMSPECExtension/Extension.cs`, `GameAuth.cs`, `COMSPECExtension.csproj`
- `mod/UptoDate/Sources/.../fn_deleteChatChannel.sqf`, `fn_athena_commsDeleteChannel.sqf`, `fn_createChatChannel.sqf`, `fn_sendIntel.sqf`
- `comms_page.hpp`, `connect/config.cpp`, `atak_athena/config.cpp`

## Vérification

1. Déployer le portail, rebuild pack 1.5.68.
2. Connexion Athena par mot de passe → plus de rafale 401 après ouverture du canal.
3. Messagerie : créer un canal, envoyer (journal `OK · SendChat`), **Supprimer** → canal retiré ici et au poste.
4. Tentative de supprimer Général / Groupe → message de refus.

## Contournement temporaire (avant déploiement)

Appairage depuis le poste (conserve la clé communauté) au lieu du seul mot de passe.
