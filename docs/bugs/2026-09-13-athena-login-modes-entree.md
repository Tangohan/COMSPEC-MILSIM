# Formulaire Athena — modes mot de passe / code + Entrée

## Contexte

13 septembre 2026. Section « Ou compte Athena » : mot de passe et code mélangeés, texte d’aide illisible, pas de validation clavier.

## Symptôme

- Champ code et mot de passe peu clairs / affichés ensemble.
- Pas de boutons distincts pour choisir le mode.
- Impossible de valider avec Entrée.

## Correctif

- Boutons **Mot de passe** / **Code e-mail** : n’affichent que le champ du mode choisi.
- Mode code : **Recevoir le code** + **Valider le code**.
- Touche Entrée sur e-mail, mot de passe, code OTP et code Appairer.

## Fichiers touchés

- `ui/athena_page.hpp`
- `fn_athena_authAction.sqf`
- `fn_athena_applyHomeLayout.sqf`
- `config.cpp` (1.0.106)

## Vérification

1. Pack 1.0.106, quitter Arma.
2. Mot de passe → champ MDP → Entrée / Se connecter.
3. Code e-mail → champ code → Recevoir → Valider / Entrée.

## Statut

corrigé — Athena 1.0.106
