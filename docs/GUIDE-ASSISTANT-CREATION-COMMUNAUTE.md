# Guide — Assistant « Créer une communauté »

Ce document décrit le parcours `/communities/create` : objectif de chaque étape, champs importants et personnalisation possible avant et après création.

## Accès

- Utilisateur connecté uniquement.
- Après création réussie, le fondateur dispose d’un compte dans la nouvelle communauté avec le rôle « Fondateur » (équivalent propriétaire communauté).

## Vue d’ensemble des étapes

1. **Identité et fuseau** — Nom public, option de segment d’URL (slug), langue, fuseau horaire, éléments de présentation (badges, textes, logo et bannière en URL).
2. **Rôles et permissions** — Modèle **rapide** ou **standard**, matrice récapitulative, et éventuellement **rôles supplémentaires** (profils métier avec cases à cocher pour les autorisations).
3. **Structure ORBAT** — Arborescence d’unités (groupe, section, équipe, escouade), démarrage rapide optionnel, édition JSON avancée pour les besoins pointus.
4. **Référentiel de grades** — Modèle français ou américain, aperçus par catégorie, grade du fondateur.
5. **Validation et accès** — Visibilité ORBAT, choix de formule (gratuite ou abonnement Stripe si configuré), mode d’inscription, message d’accueil, options de verrouillage, JSON optionnel du formulaire MilSim, aperçu dans un nouvel onglet, récapitulatif.

## Étape 1 — Identité et fuseau

- **Nom affiché** : nom de la communauté tel qu’il apparaît sur le site.
- **Slug URL personnalisé** : case à cocher « Définir un slug URL personnalisé ». Si elle est **décochée**, le segment d’adresse web est **dérivé automatiquement** du nom (minuscules, tirets). Si elle est **cochée**, le champ permet de forcer un segment précis (lettres minuscules, chiffres, tirets).
- **Langue** et **fuseau horaire** : utilisés pour l’affichage et le comportement par défaut.
- **Identité publique** (optionnel) : modèle de page, accroche, doctrine courte, badges, mode de présentation, textes, logo et bannière (URLs HTTPS). Affinage possible ensuite dans le back-office communauté.

## Étape 2 — Rôles et permissions

- **Modèle rapide** : jeux de rôles types (Fondateur, Commandement, RH, Instructeur, Membre, Invité, Modérateur forum) avec droits de base.
- **Modèle standard** : comme le rapide, avec des droits de modération forum étendus pour le rôle modérateur (notamment la section organisation du forum).
- **Rôles supplémentaires** : vous pouvez ajouter jusqu’à 15 profils nommés (nom affiché + identifiant technique unique). Pour chaque profil, cochez les autorisations parmi celles proposées (forum, documents, formations, administration légère). Ces rôles existent **uniquement dans votre communauté** ; l’attribution aux membres se fait dans le back-office (rôles).

Les identifiants techniques réservés ne peuvent pas être réutilisés (par ex. `member`, `tenant_admin`, etc.).

## Étape 3 — ORBAT

- **Démarrage rapide** : insère un petit organigramme type (état-major, section, équipe) modifiable.
- **Arborescence** : chaque ligne peut être développée ou repliée ; vous pouvez ajouter une unité racine, des sous-unités, définir le type et l’ordre, changer le parent, supprimer une branche.
- **Slugs d’unités** : si « Slugs d’unités personnalisés » est **décoché**, le segment d’URL de chaque unité est **calculé à partir du nom**. Sinon, un champ permet de le saisir.
- **JSON avancé** : pour import ou retouches fines ; le contenu reste synchronisé avec le champ caché envoyé au serveur.

## Étape 4 — Grades

- Choix du **référentiel** (français ou américain) et **grade du fondateur** dans les listes groupées par catégorie.

## Étape 5 — Validation

- **Visibilité ORBAT** : public, membres uniquement, ou réservé au commandement (selon les règles produit).
- **Formules** : « Quartier libre » (création immédiate) ou abonnements Pro / Pro+ si Stripe est configuré (création après paiement confirmé).
- **Paramètres d’accès** : mode d’inscription, message d’accueil, verrouillage de communauté, confirmation « sans IA », personnalisation JSON du formulaire MilSim.
- **Aperçu** : ouvre une page de simulation (brouillon en session) sans créer la communauté.
- **Récapitulatif** : résumé dynamique des choix principaux.

## Après la création

- Paramétrage fin : back-office organisation (présentation, rôles, utilisateurs, forum, etc.).
- Communautés anciennes sans assistant complet : voir la page de **rattrapage onboarding** (back-office) et le document [QA_COMMUNITY_ONBOARDING.md](QA_COMMUNITY_ONBOARDING.md).

## Paiement Stripe

Si vous choisissez une formule payante, la configuration est stockée jusqu’à confirmation du paiement ; la communauté est créée lors du traitement du webhook Stripe avec la même configuration normalisée que pour une création gratuite.
