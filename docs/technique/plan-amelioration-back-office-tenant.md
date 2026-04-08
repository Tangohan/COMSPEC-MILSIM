# Plan d’amélioration majeur — Back-office TENANT

## 1) Périmètre analysé

Ce plan couvre l’espace `/back-office` orienté administration de communauté (tenant), incluant le tableau de bord, identité communauté, utilisateurs/invitations, gouvernance des rôles, modération, conformité, événements, configuration et alertes locales. Le périmètre applicatif est exposé par les routes dédiées au préfixe `/back-office`.

## 2) Cartographie fonctionnelle actuelle

### 2.1 Gouvernance et pilotage
- Dashboard organisation (`/back-office`) avec KPIs, file d’activité, candidatures récentes, modération et flux formation.
- Audit organisation (`/back-office/audit`).
- Analytics organisation (`/back-office/analytics`).

### 2.2 Identité communauté et onboarding
- Paramètres d’identité (`/back-office/community`) : nom, slug, code communauté.
- Présentation publique (`/back-office/community/presentation`) : fiche registre/contact.
- Assistant de rattrapage (`/back-office/onboarding-recovery`) pour communautés historiques.

### 2.3 Administration RH et structure
- Utilisateurs (`/back-office/users/*`) : création, édition, désactivation, notifications profil/vérification.
- Invitations (`/back-office/invitations*`).
- Rôles/permissions (`/back-office/roles*`, `/back-office/roles-functions*`).
- Référentiels et structure : grades, catégories, postes, groupes, équipes, positions.

### 2.4 Opérations et conformité
- Modération organisation (`/back-office/moderation*`) + blocklist.
- Événements org (`/back-office/events*`) avec export présences et actions de pointage.
- Intégrations (`/back-office/integrations*`) et export conformité (`/back-office/conformite/export-dossier*`).
- Alertes locales (`/back-office/alerts*`) et épingles dashboard (`/back-office/dashboard-pins*`).

### 2.5 Recrutement
- Dossiers de recrutement (`/back-office/recruitments*`) + messages préfaits.

## 3) Constats clés (techniques et produit)

1. **Très bonne couverture fonctionnelle**, mais **fragmentation UX forte** : la profondeur du menu et la multiplicité des écrans ralentissent les administrateurs occasionnels.
2. **Pilotage opérationnel partiellement éclaté** : dashboards, audit, modération, recrutements et événements restent séparés sans “centre de commandement unifié”.
3. **Chaînes d’actions incomplètes** entre modules (ex. un signalement lié à un membre n’ouvre pas forcément un flux assisté RH/modération).
4. **Risque de dette de gouvernance** : permissions riches mais compréhension difficile sans vues de synthèse d’impact (“qui peut quoi sur quoi”).
5. **Fonctions conformité présentes mais non orchestrées** : export dossier utile, mais manque de checklists et de statut de préparation continue.

## 4) Plan d’amélioration (majeur)

## Axe A — Expérience back-office “Control Tower”

### Objectif
Créer un cockpit opérationnel unique pour les admins tenant.

### Évolutions proposées
- Nouvelle vue **“Centre des opérations”** agrégeant:
  - incidents modération,
  - candidatures en attente,
  - événements à J+7/J+1,
  - alertes locales actives,
  - anomalies onboarding/configuration.
- Vues filtrées par profil admin (commandement, RH, modération, formation).
- Raccourcis d’action contextuels (“traiter”, “assigner”, “escalader”, “archiver”).

### Gains attendus
- Baisse du temps de navigation inter-modules.
- Réduction du délai de traitement des actions critiques.

## Axe B — Gouvernance RBAC explicable

### Objectif
Rendre les droits lisibles, auditables et sûrs.

### Évolutions proposées
- **Matrice de permissions explicable** (rôle → permission → écrans impactés).
- Simulateur “voir comme” (lecture seule) pour contrôler l’effet d’un rôle.
- Détection des rôles “à privilèges excessifs” + alertes de dérive.
- Pack de rôles recommandés par type d’organisation (petite unité, multi-section, académie).

### Gains attendus
- Moins d’erreurs de configuration.
- Onboarding administrateur accéléré.

## Axe C — Workflow transverse Modération / RH / Recrutement

### Objectif
Unifier le traitement des cas sensibles liés aux membres.

### Évolutions proposées
- Dossier unifié par membre (incidents, sanctions, candidature, historique décisionnel).
- Workflow d’instruction multi-étapes (qualifier, décider, notifier, contrôler).
- Journal décisionnel structuré (motif, pièce, validateur, horodatage).
- Notifications internes ciblées par rôle.

### Gains attendus
- Cohérence décisionnelle accrue.
- Traçabilité plus robuste en cas de contestation.

## Axe D — Conformité continue et preuves

### Objectif
Passer d’un export ponctuel à une conformité pilotée en continu.

### Évolutions proposées
- Score de conformité tenant (complet / partiel / bloquant).
- Checklist dynamique (RGPD, sécurité, modération, journaux, accès).
- “Manques de preuve” avant export.
- Export dossier avec manifeste signé (hash + métadonnées d’intégrité).

### Gains attendus
- Réduction du risque opérationnel.
- Audits externes facilités.

## Axe E — Performance d’administration et qualité de données

### Objectif
Accélérer les opérations de masse et limiter les incohérences.

### Évolutions proposées
- Actions bulk (assignation rôle, activation/désactivation, relances).
- Prévalidation des formulaires critiques (slug/code/identité).
- Contrôles d’intégrité inter-modules (utilisateur sans rôle, rôle orphelin, unité sans responsable).
- Journal d’erreurs admin orienté support.

## 5) Corrections prioritaires (quick wins)

1. Uniformiser les patterns d’UI (filtres, pagination, exports, confirmations).
2. Ajouter des liens croisés intelligents entre audit, profil membre, modération et recrutement.
3. Introduire un mode “revue hebdomadaire admin” (résumé automatique des éléments bloquants).
4. Ajouter des garde-fous de validation côté serveur sur tous les formulaires d’admin sensible.
5. Rendre les messages d’erreur actionnables (cause + action recommandée).

## 6) Roadmap recommandée

### Lot 1 (0–6 semaines)
- Quick wins UX + liens transverses.
- Score de santé tenant minimal (onboarding/config critique).
- Première matrice lisible des permissions.

### Lot 2 (6–12 semaines)
- Centre des opérations V1.
- Workflow modération/RH unifié V1.
- Actions bulk principales utilisateurs/invitations.

### Lot 3 (12–20 semaines)
- Simulateur RBAC “voir comme”.
- Conformité continue + export renforcé.
- Instrumentation analytique de performance admin.

## 7) KPIs de succès

- Temps médian de traitement d’un incident modération.
- Temps médian de validation d’une candidature.
- Taux de configurations tenant invalides détectées pré-production.
- Taux d’utilisation du cockpit unifié.
- Nombre d’erreurs admin critiques par mois.

## 8) Risques et garde-fous

- **Risque**: surcharge fonctionnelle du cockpit.
  **Mitigation**: progressive disclosure par rôle + préférences d’affichage.
- **Risque**: régression permissions.
  **Mitigation**: tests de non-régression RBAC + snapshots de matrices.
- **Risque**: adoption lente côté admins historiques.
  **Mitigation**: mode compatibilité + migration incrémentale des usages.
