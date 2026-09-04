# Connexion au poste impossible, puis réseau local sans retour visible

- Date : 2026-09-04
- Statut : corrigé (pack 1.8.21)

## Contexte

Pack 1.8.20, liaison 1.18.12, théâtre Altis. L’opérateur appuie sur Connexion à Athena, puis passe en Peer to Peer. Le poste ne répond pas. Un second journal montre aussi l’échec de la connexion par e-mail, et un identifiant Steam invalide en éditeur.

## Symptôme

Message « Connexion Athena impossible. Voir le statut. » Après le réseau local, « Se reconnecter » semble sans effet : le statut s’écrit sur un écran déjà fermé. Terrain peut rester utilisable, le poste non.

## Cause

Le poste refuse la liaison communautaire (indisponibilité momentanée). Les tentatives suivantes écrasaient le message utile. Après le réseau local, l’écran de connexion est masqué : le statut d’échec n’est plus visible. En éditeur, un identifiant Steam fictif était envoyé. Le journal d’atelier enregistrait aussi la commande de mot de passe en clair.

## Correctif

Dès le premier refus du poste, plus de cascade inutile : message français, formulaire e-mail, écran de connexion rouvert. Depuis le réseau local, Accueil propose Rejoindre le poste. L’identifiant fictif de l’éditeur est ignoré. Les commandes de secret et d’e-mail sont masquées dans le journal.

## Fichiers touchés

- `functions/fn_networkConnectAthena.sqf`
- `functions/fn_networkAuthPassword.sqf`
- `functions/fn_networkSteamUid.sqf`
- `functions/fn_webJSDialog.sqf`
- `web/phone.html`

## Vérification

Lecture du flux : premier refus → formulaire visible ; réseau local → Rejoindre le poste rouvre l’écran ; journal sans secret.

## Statut

Corrigé dans les sources 1.8.21 — reconstruire le pack et relancer Arma complètement.
