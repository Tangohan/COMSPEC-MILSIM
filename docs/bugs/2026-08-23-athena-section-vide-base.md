# Section Athena vide + base injoignable

## Contexte

Session Overwatch 1.4.51, handshake Athena OK. Courriel prod : `SQLSTATE[HY000] [2002] Operation not permitted` sur `POST /api/atak/mod-report` (corrélation `f05abfbe62273583`). Section Athena (notifications / journal / détail) vide. Log : `401` sur les caméras.

## Symptôme

Les cadres Athena s’affichent mais restent noirs, sans texte. Photos : fichiers introuvables côté PC (`srcdir_missing`).

## Cause

- **Base prod** : connexion refusée (hôte / socket / déploiement FTP). Toute écriture qui ouvre la base échoue. Ce n’est pas un bug du panneau Athena.
- **Panneau** : si l’ouverture cTab arrive trop tôt, le groupe de contrôles n’est pas prêt : listes jamais peintes (même bug que la page Paramètres « bleue vide »). Journal vide sans libellé.
- **401 caméras** : envoi jeu sans clé reconnue sur cette route ; indépendant du journal Athena.
- **Photos** : dossiers Captures / Screenshots incomplets sur le PC, pas l’API.

## Correctif

- Peinture Athena réessayée (0,08 s à 1,4 s) + recherche du groupe si besoin.
- Texte d’état vide dans le journal (« Aucune entrée pour le moment ») et couleurs de liste explicites.
- **Prod** : vérifier l’accès base (`127.0.0.1`, pas le socket `localhost`) et qu’un FTP n’est pas en cours.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_onOpened.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updatePanel.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/ui/athena_page.hpp`

## Vérification

Sources relues. La base prod se vérifie sur l’hébergeur (hors dépôt). Rebuild PBO Athena 1.0.43.

## Statut

Partiel — panneau corrigé côté mod ; incident base = configuration serveur
