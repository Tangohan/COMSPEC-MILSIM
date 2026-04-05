# QA — Onboarding communautaire (wizard v2)

Guide utilisateur détaillé : [GUIDE-ASSISTANT-CREATION-COMMUNAUTE.md](GUIDE-ASSISTANT-CREATION-COMMUNAUTE.md).

## Pré-requis

- Migrations exécutées (`tenant_grade_overrides`, seeds grades FR/US).
- Compte utilisateur connecté.

## Scénarios non-régression

### Création gratuite (Quartier libre)

1. Ouvrir `/communities/create`, parcourir les 5 étapes, remplir le **nom** ; laisser le slug **automatique** (case slug personnalisé décochée) ou définir un slug manuel.
2. Étape 2 (optionnel) : ajouter un **rôle supplémentaire** avec nom + identifiant + cases permissions ; vérifier en base `roles` / `role_permissions` pour ce slug après création.
3. Étape 3 : vérifier l’**arborescence** (chevrons replier/déplier) ; tester **slugs auto** vs **slugs personnalisés** pour les unités ; laisser le démarrage rapide ou éditer le JSON avancé.
4. Étape 4 : choisir FR ou US, vérifier l’aperçu et le grade fondateur.
5. Étape 5 : consulter le **récapitulatif** et l’**aperçu** (nouvel onglet) ; valider ; attendre redirection vers le **tableau de bord** (plus vers `/c/{slug}/setup` seul).
6. Vérifier en base : `tenants.settings` contient `grade_system_code`, `onboarding_wizard_version` = 2, `onboarding_completed_at`, `timezone`.
7. Vérifier `units` : au moins une racine et hiérarchie cohérente.
8. Vérifier rôles : libellés « Fondateur », « Commandement », présence de `hr`, `invite` ; si rôle custom ajouté, ligne correspondante dans `roles` avec `is_system` = 0.

### Création payante (Stripe)

1. Choisir un plan Pro / Pro + avec Stripe configuré.
2. Vérifier que le payload `pending_community_creates.payload_json` inclut `options.wizard_normalized` après soumission (inspection BDD ou log).
3. Simuler ou exécuter `checkout.session.completed` : la communauté créée via webhook doit avoir les mêmes propriétés settings que le scénario gratuit.

### Liste des grades (filtrage tenant)

1. Après création avec `grade_system_code` = `US_CLASSIC`, ouvrir l’admin utilisateurs / liste des grades disponibles pour le tenant : uniquement les grades du système choisi (plus le comportement « tous » si le paramètre est absent — communautés anciennes).

### Assistant legacy `/c/{slug}/setup`

1. Communauté **sans** `onboarding_completed_at` : l’URL setup reste accessible.
2. Après wizard v2 : `onboarding_completed_at` présent → redirection dashboard.

### Rattrapage `/back-office/onboarding-recovery`

1. Sur une communauté sans `grade_system_code` ou sans racine ORBAT, la page liste les écarts.
2. POST « Appliquer le modèle FR… » : `grade_system_code` renseigné si vide ; unités d’exemple créées **uniquement** s’il n’y a aucune racine ; `onboarding_wizard_version` passé à 2.

### Validations bloquantes

1. Soumettre avec JSON d’unités invalide (aucune racine) → message d’erreur flash, pas de tenant créé.
2. Grade fondateur incompatible avec le système → erreur métier à la création (clone utilisateur).

## Points d’attention

- Paiements Stripe en attente créés **avant** déploiement : payload sans `wizard_normalized` → création « legacy » sans étendue wizard (comportement toléré).
- Table `tenant_grade_overrides` : optionnelle pour le MVP ; si absente, `GradeRepository::listForTenant` filtre toujours par `grade_system_code` sans overrides.
