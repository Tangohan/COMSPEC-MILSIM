# Bouton « Lier ce terminal au poste » sans effet

- Date : 2026-09-04
- Statut : corrigé (pack 1.8.20)

## Contexte

Écran Compte : session affichée comme authentifiée mais non connectée, état Steam non associé. L’opérateur appuie sur « Lier ce terminal au poste ».

## Symptôme

Le clic ne change rien à l’écran. Aucun formulaire, aucun code, aucun message.

## Cause

Le clic envoyait bien une demande d’appariage, mais le panneau de liaison vit dans l’écran d’accueil de démarrage, déjà fermé. L’opérateur restait sur Compte sans rien voir. L’état Steam non associé était affiché brut, sans action claire.

## Correctif

Sans session, le bouton ouvre le formulaire e-mail / mot de passe sur Compte. Avec une session, l’appariage démarre vraiment. Les états sont rédigés en français. Se déconnecter ferme la session auprès du poste.

## Fichiers touchés

- `web/phone.html`
- `functions/fn_webJSDialog.sqf`
- `functions/fn_networkAuthPassword.sqf`
- `functions/fn_networkDisconnect.sqf`
- `functions/fn_networkConnectAthena.sqf`

## Vérification

Lecture du flux : clic → ouverture du formulaire ou appariage ; déconnexion appelle la fermeture de session.

## Statut

Corrigé dans les sources 1.8.20 — reconstruire le pack et relancer Arma complètement.
