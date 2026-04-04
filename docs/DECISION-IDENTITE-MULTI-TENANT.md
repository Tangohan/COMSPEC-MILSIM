# Décision — identité et multi-communautés

## Choix retenu : Option A (objectif produit)

**Objectif** : un utilisateur peut appartenir à **plusieurs communautés** (tenants) avec un contexte actif (forum, ORBAT, etc.), sans créer un compte distinct par communauté pour la même adresse e-mail.

## État actuel du schéma (héritage)

- La table `users` impose `UNIQUE (tenant_id, email)` : la même adresse peut exister **une fois par tenant**, donc **plusieurs lignes** pour la même personne si elle rejoint plusieurs communautés.
- La session stocke `user_id`, `tenant_id`, `role_id` : un couple `(email, mot de passe)` est résolu **dans un tenant choisi** à la connexion (champ ou slug de communauté).

Ce modèle est une **implémentation pragmatique d’Option A** sans table `accounts` globale : l’« identité plateforme » est portée par **l’email** ; chaque communauté a toujours sa **ligne `users`** (profil métier, rôle, grade).

## Évolution future (optionnelle)

- Introduire une table `accounts` (email unique, mot de passe) et des `user_profiles` par `tenant_id` pour éviter la duplication des hashes et simplifier le SSO.
- Non requis pour les premières livraisons « création de communauté + switch + premium ».

## Implications

- **Connexion** : l’utilisateur indique la **communauté** (slug) en plus de l’e-mail et du mot de passe, ou une communauté par défaut est utilisée.
- **Changement de communauté** : recherche de la ligne `users` ayant le même **email** que la session et le `tenant_id` cible, puis réinitialisation de session avec ce `user_id`.

Voir les routes et contrôleurs « communauté » dans l’application pour le comportement effectif.
