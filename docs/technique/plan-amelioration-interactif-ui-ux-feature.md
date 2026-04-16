# Plan d’amélioration — Interactif / UI-UX / Features

## 1) Objectif du plan

Proposer une trajectoire d’amélioration pragmatique pour :
- rendre l’interface **plus interactive** (retours immédiats, navigation fluide, réduction des frictions),
- renforcer la **qualité UX/UI** (lisibilité, cohérence, accessibilité),
- prioriser des **features à forte valeur utilisateur** sans alourdir le produit.

Ce plan s’appuie sur les modules déjà présents dans Athena (hub, ORBAT, formations, forum, courrier, documents, mur opérationnel, ATAK/C2), avec une logique “quick wins → gains structurels”.

---

## 2) Diagnostic synthétique (état actuel)

## 2.1 Points forts
- Couverture fonctionnelle large (opérations, RH, LMS, C2, documents, communications).
- Navigation par domaines déjà installée (Hub, Ressources, Personnel, Formations, Administration).
- Permissions rôle/tenant robustes (masquage des entrées non autorisées).

## 2.2 Frictions UX probables
- Densité d’information élevée sur plusieurs pages “métier”.
- Parcours parfois discontinus entre consultation et action (ex. lecture d’état puis édition dans un autre espace).
- États “chargement / vide / erreur / succès” hétérogènes selon les modules.
- Découverte fonctionnelle inégale pour les nouveaux utilisateurs.

## 2.3 Opportunité produit
Le socle est riche ; le gain principal n’est pas d’ajouter immédiatement de nouveaux modules, mais de :
1. rendre les parcours existants plus guidés,
2. uniformiser les interactions,
3. prioriser quelques fonctionnalités transverses perçues comme “premium UX”.

---

## 3) Axes d’amélioration proposés

## Axe A — Interactions instantanées et feedback utilisateur

### But
Réduire la latence perçue et l’incertitude après action.

### Améliorations
- Standardiser les feedbacks UI : toast succès/erreur, indicateurs de progression, confirmation d’action critique.
- Ajouter des “skeleton loaders” sur les listes lourdes (documents, formations, dossiers, opérationnel).
- Harmoniser les messages d’erreurs actionnables (cause + prochaine action suggérée).
- Mettre en place des sauvegardes brouillon automatiques sur formulaires longs (courrier, fiches, contenus).

### KPI
- Baisse du taux d’abandon sur formulaires longs.
- Diminution des erreurs répétées sur actions de gestion.

## Axe B — Cohérence UI et design system léger

### But
Donner une expérience cohérente entre modules sans refonte lourde.

### Améliorations
- Créer un mini design system interne :
  - composants (boutons, badges, tabs, cartes, tableaux),
  - règles d’espacement/typographie,
  - états standard (normal, hover, disabled, loading, error).
- Définir des patterns de page récurrents (liste + filtres + panneau détails + actions rapides).
- Unifier les pages “tableau de bord” autour de sections stables : alertes, tâches prioritaires, activité récente, raccourcis.

### KPI
- Réduction du temps de développement UI pour nouvelles pages.
- Diminution des écarts visuels détectés en QA.

## Axe C — Navigation et découvrabilité des features

### But
Aider les utilisateurs à comprendre rapidement “où aller” et “quoi faire ensuite”.

### Améliorations
- Introduire des CTA contextuels systématiques (“Prochaine étape”) en bas de page.
- Ajouter une recherche globale enrichie par type de contenu + filtres rapides.
- Implémenter des “vues onboarding” par rôle (nouveau membre, instructeur, encadrant, admin).
- Ajouter des raccourcis clavier visibles sur les écrans clés (Hub, ORBAT, documents, formations).

### KPI
- Temps moyen jusqu’à première action utile (TTV: time-to-value).
- Hausse de l’usage des modules secondaires via navigation croisée.

## Axe D — Accessibilité et robustesse d’usage

### But
Améliorer l’inclusivité et la fiabilité perçue.

### Améliorations
- Audit accessibilité WCAG de base : contrastes, focus, labels, navigation clavier.
- Vérification mobile/tablette des parcours critiques (connexion, consultation documents, pointage, briefings).
- Renforcement des états offline/intermittents pour les zones opérationnelles.

### KPI
- Baisse des incidents UX “bloquants”.
- Meilleure satisfaction utilisateur sur terminaux variés.

## Axe E — Features transverses à fort impact

### But
Déployer peu de features, mais avec fort impact d’usage quotidien.

### Feature candidates (priorité)
1. **Centre d’actions unifié** : tâches à traiter (validation, revue, signature, formation, recrutement) triées par urgence.
2. **Journal personnel intelligent** : flux “ce qui a changé pour moi” (et non flux global brut).
3. **Command palette** (Ctrl+K enrichi) : navigation, actions rapides, créations directes.
4. **Préférences d’interface** : densité d’affichage, raccourcis épinglés, modules favoris.
5. **Résumé hebdomadaire automatique** : e-mail/in-app des points clés (actions en attente, progression, alertes).

### KPI
- Taux d’adoption des fonctionnalités transverses.
- Diminution du temps pour compléter les tâches récurrentes.

---

## 4) Priorisation exécutable

## Lot 1 — Quick wins (2 à 4 semaines)
- Standardisation feedback (toasts, erreurs, confirmations).
- États vides/chargement homogènes sur 3 modules prioritaires.
- CTA contextuels et “prochaine action” sur dashboards.

## Lot 2 — Structuration UX (4 à 8 semaines)
- Mini design system + documentation d’usage.
- Patterns de pages unifiés (liste/détail/actions).
- Onboarding par rôle (version initiale).

## Lot 3 — Features différenciantes (8 à 14 semaines)
- Centre d’actions unifié.
- Command palette enrichie.
- Journal personnel intelligent.

## Lot 4 — Consolidation (continu)
- Accessibilité, mobile/tablette, optimisation des formulaires longs.
- Instrumentation produit (analytics UX anonymisées orientées parcours).

---

## 5) Métriques de pilotage recommandées

- **Adoption** : WAU/MAU par module, profondeur de session, usage des raccourcis.
- **Efficacité** : temps moyen tâche clé (ex. traiter un recrutement, publier une fiche opérationnelle).
- **Qualité UX** : taux d’erreur UI, abandon formulaire, rebond après page dashboard.
- **Satisfaction** : score CSAT in-app par domaine (opérations, docs, formation, admin).

---

## 6) Gouvernance de mise en œuvre

- Nommer un **référent UX produit** (décision patterns et cohérence inter-modules).
- Définir un **cadre “Definition of Done UX”** (accessibilité minimale, états d’interface, textes d’erreur).
- Ajouter une **revue mensuelle UX** orientée métriques et irritants utilisateurs.
- Travailler en cycles courts avec validation terrain (staff opérationnel + utilisateurs standard).

---

## 7) Résultat attendu

À horizon 3 à 4 mois, la plateforme peut offrir une expérience :
- plus fluide (moins d’hésitation et de re-travail),
- plus lisible (navigation claire, interactions homogènes),
- plus engageante (features transverses utiles au quotidien),

sans remise en cause du socle fonctionnel déjà riche d’Athena.
