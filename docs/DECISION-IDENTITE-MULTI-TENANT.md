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

## Mise à jour 2026-11 — une seule communauté par compte

La plateforme reste multi-communauté, mais **un compte n'appartient plus qu'à une communauté** et n'a plus de « profils » multiples. Le blocage existe déjà côté données : `UserRepository::addMembershipToTenant` refuse une deuxième communauté active (sauf création de communauté, qui fait quitter les autres).

Retiré côté interface :
- les profils de candidature enregistrés sur le compte (`/account/recruitment-presets`) ;
- la phrase « Vos adhésions avec le même e-mail… » de la page publique d'une communauté.

Encore en place, parce qu'ils ne s'affichent qu'aux comptes qui ont **encore plusieurs adhésions en base** (les retirer les bloquerait sur une seule communauté) :

| Entrée | Où | Condition |
|---|---|---|
| Bascule de communauté (puces « Vous êtes sur / Autres ») | `views/partials/dashboard_idstrip.php`, route `POST /community/switch` | plus d'une adhésion |
| Liste « Communauté » de l'en-tête ATAK web | `views/atak.php` | plus d'une adhésion |
| Choix de la communauté à la connexion (définitif ou temporaire) | `/login/select-community` | plus d'une adhésion |
| « Basculer et continuer » d'une candidature | `views/enlistment.php`, `views/enlistment/simple.php`, `/c/{slug}/enlistment/enter` | adhésion déjà présente sur la communauté visée |

À décider avant de les retirer :
1. Consolider les comptes qui ont encore plusieurs adhésions (le choix définitif de `/login/select-community` le fait déjà au cas par cas via `CommunityMembershipConsolidationService::consolidateToChoice`), par script ou en gardant seulement le choix définitif à la connexion.
2. Ensuite seulement : retirer la bascule du tableau de bord et d'ATAK, la route `/community/switch` (listée aussi dans `FirstLoginGameLinkGate` et `TenantTypeConfig`), « Basculer et continuer » et le réglage communauté « refuser les membres d'une autre communauté », devenu redondant.
3. Éventuellement, une contrainte d'unicité sur l'adhésion active (en excluant la communauté technique « Pas d'organisation »).

À garder : `AuthService::switchToTenant` (utilisé par la création de communauté, le départ d'une communauté et la sortie de « Pas d'organisation »), `user_community_profiles` (porte le grade, le rôle et le matricule de la communauté actuelle : avec une seule adhésion, un seul profil), les préférences d'affichage et de notification (une ligne par compte, la colonne `tenant_id` n'y joue aucun rôle).
